/* The four hero cuts, desktop and phone, with what the browser actually
 * resolved for each: focus, animation names, periods. */
import { chromium, BASE, OUT } from "./_base.mjs";

const pages = ["about", "gallery", "services", "contact"];
const b = await chromium.launch({ args: ["--no-sandbox"] });

async function grab(w, h, tag) {
  const ctx = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 2 });

  for (const name of pages) {
    const pg = await ctx.newPage();
    await pg.goto(`${BASE}/${name}`, { waitUntil: "networkidle" });
    await pg.waitForTimeout(1500);

    const hero = await pg.$(".page-hero");
    const info = await hero.evaluate((node) => {
      const img = node.querySelector("img");
      const style = getComputedStyle(img);
      return {
        hero: node.dataset.hero,
        focus: getComputedStyle(node).getPropertyValue("--hero-focus").trim(),
        animation: style.animationName,
        period: style.animationDuration,
        position: style.objectPosition,
      };
    });

    console.log(tag, name, JSON.stringify(info));
    await hero.screenshot({ path: `${OUT}/hero-${name}${tag === "mob" ? "-mobile" : ""}.png` });
    await pg.close();
  }

  await ctx.close();
}

await grab(1280, 900, "desk");
await grab(390, 844, "mob");
await b.close();
