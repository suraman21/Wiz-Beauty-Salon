/* The app never arrives — as if the connection died after the first few KB.
 * The words still have to be there: the reveal animations must let go. */
import { chromium, BASE, OUT } from "./_base.mjs";

const b = await chromium.launch({ args: ["--no-sandbox"] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });

for (const path of ["/about", "/services"]) {
  const pg = await ctx.newPage();
  let blocked = 0;

  await pg.route(/\/assets\/.*\.js(\?|$)/, (route) => {
    blocked += 1;
    return route.abort("failed");
  });

  await pg.goto(BASE + path, { waitUntil: "load" });
  await pg.waitForTimeout(3600); // past the 2.6 s fallback

  const s = await pg.evaluate(() => ({
    cls: document.documentElement.className,
    hero: document.querySelector("h1")
      ? {
          opacity: +getComputedStyle(document.querySelector("h1")).opacity,
          text: document.querySelector("h1").textContent.trim().slice(0, 26),
        }
      : null,
    sections: [...document.querySelectorAll(".story-line, .masonry-item")].map(
      (el) => +getComputedStyle(el).opacity,
    ),
  }));

  console.log(path, "blocked=" + blocked, JSON.stringify(s));
  await pg.screenshot({
    path: `${OUT}/low-bandwidth-bundle-missing${path === "/services" ? "-services" : ""}.png`,
  });
  await pg.close();
}

await b.close();
