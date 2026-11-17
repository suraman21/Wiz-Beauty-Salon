/* A photograph that fails twice and then arrives: no broken icon, a drawn
 * stand-in in the meantime, and the real picture back as soon as a retry wins. */
import { chromium, BASE, OUT } from "./_base.mjs";

const b = await chromium.launch({ args: ["--no-sandbox"] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
const pg = await ctx.newPage();
const hits = new Map();

await pg.route(/hero-wiz-purple.*\.jpg/, (route) => {
  const n = (hits.get("hero") ?? 0) + 1;
  hits.set("hero", n);
  if (n <= 2) return route.abort("failed");
  return route.continue();
});

await pg.goto(`${BASE}/about`, { waitUntil: "load" });

for (let i = 0; i < 40; i += 1) {
  const s = await pg.evaluate(() => {
    const img = document.querySelector(".hero-band-image");
    return {
      cls: document.documentElement.className,
      att: img.dataset.attempt,
      state: [...img.classList].find((c) => c.startsWith("is-")),
      nw: img.naturalWidth,
      src: img.currentSrc.startsWith("data:") ? "drawn stand-in" : img.currentSrc.split("/").pop(),
    };
  });

  console.log(`${i * 400}ms`, JSON.stringify(s));
  if (s.nw > 0 && s.state === "is-loaded") break;
  await pg.waitForTimeout(400);
}

console.log("hero requests attempted:", hits.get("hero"));
await pg.screenshot({ path: `${OUT}/low-bandwidth-retry-recovered.png` });
await b.close();
