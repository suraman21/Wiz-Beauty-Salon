/* First paint and the moment the words are on screen, under three connection
 * profiles, against the gzipped build — so the numbers match what the host does
 * rather than what a local server without compression would. */
import { chromium, BASE } from "./_base.mjs";

const b = await chromium.launch({ args: ["--no-sandbox"] });

async function run(name, condition, path = "/about") {
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 } });
  const pg = await ctx.newPage();
  const cdp = await ctx.newCDPSession(pg);

  await cdp.send("Network.enable");
  await cdp.send("Network.emulateNetworkConditions", { offline: false, ...condition });

  const started = Date.now();
  await pg.goto(BASE + path, { waitUntil: "commit" });

  let readable = null;
  for (let i = 0; i < 300; i += 1) {
    const state = await pg
      .evaluate(() => {
        const h1 = document.querySelector("h1");
        return {
          opacity: h1 ? +getComputedStyle(h1).opacity : 0,
          paint: performance.getEntriesByName("first-contentful-paint")[0]?.startTime ?? 0,
        };
      })
      .catch(() => null);

    if (state && state.opacity > 0.9 && state.paint > 0) {
      readable = Date.now() - started;
      break;
    }

    await pg.waitForTimeout(100);
  }

  const nav = await pg.evaluate(() => {
    const n = performance.getEntriesByType("navigation")[0] ?? {};
    const fcp = performance.getEntriesByName("first-contentful-paint")[0] ?? {};
    return {
      firstPaint: Math.round(fcp.startTime ?? 0),
      domContentLoaded: Math.round(n.domContentLoadedEventEnd ?? 0),
      htmlClass: document.documentElement.className,
    };
  });

  console.log(name, JSON.stringify({ wordsReadableMs: readable, ...nav }));
  await ctx.close();
}

await run("SLOW-3G 400kbps/400ms", {
  latency: 400,
  downloadThroughput: (400 * 1024) / 8,
  uploadThroughput: (400 * 1024) / 8,
});
await run("FAST-3G 1.6Mbps/150ms", {
  latency: 150,
  downloadThroughput: (1.6 * 1024 * 1024) / 8,
  uploadThroughput: (750 * 1024) / 8,
});
await run(
  "2G 250kbps/700ms",
  { latency: 700, downloadThroughput: (250 * 1024) / 8, uploadThroughput: (250 * 1024) / 8 },
  "/services",
);

await b.close();
