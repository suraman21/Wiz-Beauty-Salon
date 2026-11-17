/* Every image request fails, for good. The page must never show a broken icon:
 * what is left is the drawn stand-in, and the words are untouched. */
import { chromium, BASE, OUT } from "./_base.mjs";

const b = await chromium.launch({ args: ["--no-sandbox"] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
const pg = await ctx.newPage();
let requests = 0;

await pg.route(/\.(jpg|jpeg|png|webp|avif)(\?|$)/, (route) => {
  requests += 1;
  return route.abort("failed");
});

await pg.goto(`${BASE}/gallery`, { waitUntil: "load" });
await pg.waitForTimeout(1200);
await pg.evaluate(() =>
  document.querySelector(".masonry, .masonry-item")?.scrollIntoView({ block: "center" }),
);
await pg.waitForTimeout(1000);
await pg.screenshot({ path: `${OUT}/low-bandwidth-images-standing-in.png` });

await pg.waitForTimeout(10000); // every retry spent

const states = await pg.evaluate(() => {
  const counts = {};
  for (const img of document.images) {
    const state = [...img.classList].find((c) => c.startsWith("is-")) ?? "none";
    counts[state] = (counts[state] ?? 0) + 1;
  }
  return {
    broken: [...document.images].filter((i) => i.complete && i.naturalWidth === 0).length,
    counts,
    total: document.images.length,
  };
});

console.log("image requests blocked:", requests, JSON.stringify(states));
await pg.screenshot({ path: `${OUT}/low-bandwidth-images-given-up.png` });
await b.close();
