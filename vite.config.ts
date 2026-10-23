// @lovable.dev/vite-tanstack-config already includes the following — do NOT add them manually
// or the app will break with duplicate plugins:
//   - TanStack devtools (dev-only, first), tanstackStart, viteReact, tailwindcss, tsConfigPaths,
//     nitro (build-only using cloudflare as a default target), VITE_* env injection, @ path alias,
//     React/TanStack dedupe, error logger plugins, and sandbox detection (port/host/strictPort).
// You can pass additional config via defineConfig({ vite: { ... }, etc... }) if needed.
import { defineConfig } from "@lovable.dev/vite-tanstack-config";

const routes = ["/", "/services", "/about", "/gallery", "/booking", "/booking/recover", "/contact"];

export default defineConfig({
  tanstackStart: {
    // Redirect TanStack Start's bundled server entry to src/server.ts (our SSR error wrapper).
    // nitro/vite builds from this
    server: { entry: "server" },
    // Prerender every route to static HTML so the site can be served by Apache/cPanel
    // without a Node process. Output lands in .output/public.
    pages: routes.map((path) => ({ path, prerender: { enabled: true } })),
  },
  vite: {
    server: {
      host: "0.0.0.0",
      allowedHosts: true,
      /**
       * In production the site and the PHP panel share one host, so the booking
       * page calls /api/v1 directly. `bun dev` runs on another port, so this
       * forwards those calls to the local PHP server instead.
       * If the PHP server is not running, the booking page falls back to the
       * local catalogue so the UI remains usable.
       */
      proxy: {
        "/api": { target: "http://127.0.0.1:8000", changeOrigin: false },
        "/uploads": { target: "http://127.0.0.1:8000", changeOrigin: false },
      },
    },
  },
});
