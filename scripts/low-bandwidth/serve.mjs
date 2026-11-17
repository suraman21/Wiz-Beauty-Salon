/* A probe, not a test suite: run it against a built site and read the numbers.
 *   node scripts/low-bandwidth/serve.mjs &      # gzip on :4173
 *   node scripts/low-bandwidth/<name>.mjs
 * See docs/PRE-PRODUCTION-AUDIT.md, section 8. */
import http from "node:http";
import fs from "node:fs";
import path from "node:path";
import zlib from "node:zlib";
const ROOT = new URL("../../.output/public/", import.meta.url).pathname;
const TYPES = {
  ".html": "text/html",
  ".js": "text/javascript",
  ".css": "text/css",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".png": "image/png",
  ".webp": "image/webp",
  ".svg": "image/svg+xml",
  ".json": "application/json",
  ".woff2": "font/woff2",
  ".ico": "image/x-icon",
  ".txt": "text/plain",
};
const COMPRESS = new Set([".html", ".js", ".css", ".json", ".svg", ".txt"]);
http
  .createServer((req, res) => {
    const url = decodeURIComponent(req.url.split("?")[0]);
    let f = path.join(ROOT, url);
    if (url.endsWith("/")) f = path.join(f, "index.html");
    if (!fs.existsSync(f) || fs.statSync(f).isDirectory()) {
      if (fs.existsSync(f + "/index.html")) f = f + "/index.html";
      else if (fs.existsSync(f + ".html")) f = f + ".html";
      else f = path.join(ROOT, "404.html");
    }
    const ext = path.extname(f);
    let body = fs.readFileSync(f);
    const headers = {
      "content-type": TYPES[ext] ?? "application/octet-stream",
      "cache-control": "no-store",
    };
    if (COMPRESS.has(ext) && /gzip/.test(req.headers["accept-encoding"] || "")) {
      body = zlib.gzipSync(body, { level: 9 });
      headers["content-encoding"] = "gzip";
    }
    res.writeHead(200, headers);
    res.end(body);
  })
  .listen(4173, "0.0.0.0", () => console.log("static server (gzip) on 4173"));
