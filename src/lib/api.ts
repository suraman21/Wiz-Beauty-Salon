/**
 * The one place the public site talks to the PHP API.
 *
 * The site and the panel are served from the same host in production, so the
 * base URL is empty and every call is same-origin. `VITE_API_BASE_URL` exists
 * only so a local dev server can point at the PHP process on another port.
 */

const BASE = ((import.meta.env["VITE_API_BASE_URL"] as string | undefined) ?? "").replace(
  /\/+$/,
  "",
);

/** An error the server described in words we can show a customer. */
export class ApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = "ApiError";
    this.status = status;
  }
}

const NETWORK_MESSAGE = "We could not reach the salon. Please check your connection and try again.";

async function readError(response: Response): Promise<string> {
  try {
    const body = (await response.json()) as { error?: unknown };

    if (typeof body.error === "string" && body.error.trim() !== "") return body.error;
  } catch {
    // Not JSON, or not our shape — fall through to the generic message.
  }

  return "Something went wrong. Please try again.";
}

export async function apiGet<T>(path: string, signal?: AbortSignal): Promise<T> {
  let response: Response;

  try {
    response = await fetch(`${BASE}${path}`, {
      headers: { Accept: "application/json" },
      ...(signal ? { signal } : {}),
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") throw error;
    throw new ApiError(NETWORK_MESSAGE, 0);
  }

  if (!response.ok) {
    throw new ApiError(await readError(response), response.status);
  }

  return (await response.json()) as T;
}

export async function apiPost<T>(path: string, body: unknown): Promise<T> {
  let response: Response;

  try {
    response = await fetch(`${BASE}${path}`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(body),
    });
  } catch {
    throw new ApiError(NETWORK_MESSAGE, 0);
  }

  if (!response.ok) {
    throw new ApiError(await readError(response), response.status);
  }

  return (await response.json()) as T;
}

/** "1,200 ETB", or null when the salon has not set a price. */
export function formatPrice(cents: number | null): string | null {
  if (cents === null || cents <= 0) return null;

  return `${new Intl.NumberFormat("en-GB").format(Math.round(cents / 100))} ETB`;
}

/** Local calendar date, not the UTC one — Addis Ababa is UTC+3. */
export function isoToday(): string {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");

  return `${now.getFullYear()}-${month}-${day}`;
}
