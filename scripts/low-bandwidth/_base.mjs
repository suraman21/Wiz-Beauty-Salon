/* Shared by the probes: they all talk to the same built site, and all of them
 * need a browser. Run them with `playwright-core` installed and a Chromium
 * downloaded (`node node_modules/playwright-core/cli.js install chromium`). */
import pw from "playwright-core";

export const { chromium } = pw;
export const BASE = process.env.BASE ?? "http://127.0.0.1:4173";
export const OUT = process.argv[2] ?? "qa/pre-production";
