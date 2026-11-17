#!/usr/bin/env node
/**
 * Serve a production build the way the server will see it.
 *
 * `vite preview` is not usable here: this project emits prerendered HTML, not an
 * SSR entry, and the preview plugin looks for a server bundle that is never
 * written. What the deploy actually ships is a directory of `index.html` files
 * (one per route) plus hashed assets — so this serves exactly that, with the
 * same 404 behaviour the docroot's rewrite rules give in production.
 *
 *   node scripts/serve-build.mjs            # http://127.0.0.1:4173
 *   PORT=8081 node scripts/serve-build.mjs
 *
 * Serving over file:// will not work: routes are directories and assets are
 * absolute paths.
 */
import { createServer } from "node:http";
import { createReadStream, existsSync, statSync } from "node:fs";
import { extname, join, normalize, sep } from "node:path";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../.output/public", import.meta.url));
const port = Number(process.env.PORT ?? 4173);
const host = process.env.HOST ?? "0.0.0.0";

const types = {
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".webp": "image/webp",
  ".ico": "image/x-icon",
  ".woff2": "font/woff2",
  ".woff": "font/woff",
  ".txt": "text/plain; charset=utf-8",
  ".webmanifest": "application/manifest+json",
};

if (!existsSync(join(root, "index.html"))) {
  console.error(
    `No build found at ${root}.\nRun \`bun run build\` first — this script serves .output/public, it does not create it.`,
  );
  process.exit(1);
}

/** Never let a request escape the build directory. */
function safeJoin(pathname) {
  const clean = normalize(pathname).replace(/^(\.\.[/\\])+/, "");
  const full = join(root, clean);

  return full.startsWith(root + sep) || full === root ? full : root;
}

function fileFor(pathname) {
  const candidates = [
    safeJoin(pathname),
    safeJoin(join(pathname, "index.html")),
    safeJoin(`${pathname.replace(/\/$/, "")}.html`),
  ];

  for (const candidate of candidates) {
    if (existsSync(candidate) && statSync(candidate).isFile()) return candidate;
  }

  return null;
}

createServer((request, response) => {
  const pathname = decodeURIComponent((request.url ?? "/").split("?")[0]);
  const file = fileFor(pathname);

  if (file === null) {
    // Production returns the site's own 404 page, not a directory listing.
    const notFound = join(root, "404.html");
    response.writeHead(404, { "Content-Type": types[".html"] });

    if (existsSync(notFound)) {
      createReadStream(notFound).pipe(response);
      return;
    }

    response.end("Not found");
    return;
  }

  response.writeHead(200, {
    "Content-Type": types[extname(file)] ?? "application/octet-stream",
    // Same policy the build declares in _headers for hashed assets.
    "Cache-Control": /\/assets\//.test(file) ? "public, max-age=31536000, immutable" : "no-cache",
  });
  createReadStream(file).pipe(response);
}).listen(port, host, () => {
  console.log(`Serving ${root}\n  http://localhost:${port}`);
});
