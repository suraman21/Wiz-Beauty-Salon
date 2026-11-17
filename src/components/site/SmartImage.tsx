import { useEffect, useRef, useState } from "react";
import type { ImgHTMLAttributes } from "react";

/**
 * An image that assumes the network will let it down.
 *
 * On a phone in a taxi the salon's photographs arrive slowly, half-arrive, or
 * not at all — and a broken-image icon is worse than a blank space: it reads as
 * "this site is broken". So this component:
 *
 *  - keeps the `<img>` itself, so every rule already written against `img`
 *    (aspect ratios, `object-fit`, absolute positioning) keeps working;
 *  - paints a soft shimmer underneath while the first attempt is in flight;
 *  - the moment an attempt fails it stops being a picture and becomes a drawn
 *    stand-in — the broken-file glyph has nothing left to break;
 *  - keeps retrying by itself, three times, waiting longer each time, each
 *    attempt against a cache-busted URL so a half-downloaded file is not reused;
 *  - swaps the picture back in, with a short fade, as soon as a retry succeeds,
 *    however long that takes;
 *  - and, if every attempt fails, settles quietly on that drawn stand-in: the
 *    picture's initials, never a broken icon.
 *
 * The retries are made with a detached `Image`, not by re-pointing the visible
 * one: that is what keeps the box filled by the stand-in instead of flashing a
 * broken glyph on every attempt.
 */

type SmartImageProps = Omit<ImgHTMLAttributes<HTMLImageElement>, "onError" | "onLoad" | "src"> & {
  src: string | null | undefined;
  /** How many times to retry after the first failure. */
  retries?: number;
  /** First wait in milliseconds; doubles each time. */
  retryDelay?: number;
};

type Status = "loading" | "retrying" | "loaded" | "missing";

const RETRY_DELAYS = [900, 2200, 4500];

function withAttempt(src: string, attempt: number): string {
  if (attempt === 0) return src;

  // A different URL, so neither the browser cache nor the CDN can hand back the
  // same truncated bytes that failed a moment ago.
  const sep = src.includes("?") ? "&" : "?";

  return `${src}${sep}retry=${attempt}`;
}

/**
 * A stand-in drawn as SVG and inlined as a data URI, so the box is filled by a
 * real image and no broken-file icon is possible. It carries the picture's
 * initials, which is enough to say "a photograph belongs here".
 */
function placeholder(alt: string): string {
  const label = alt
    .split(/\s+/)
    .filter((word) => word.length > 2)
    .slice(0, 2)
    .map((word) => word[0]?.toUpperCase() ?? "")
    .join("");

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5" preserveAspectRatio="xMidYMid slice">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
<stop offset="0" stop-color="#3a0f36"/><stop offset="0.55" stop-color="#4a1342"/><stop offset="1" stop-color="#2b0a28"/>
</linearGradient></defs>
<rect width="4" height="5" fill="url(#g)"/>
<circle cx="2" cy="2.1" r="0.62" fill="none" stroke="#f6c7ea" stroke-opacity="0.5" stroke-width="0.035"/>
<text x="2" y="2.28" text-anchor="middle" font-family="Georgia,serif" font-size="0.5" fill="#f6c7ea" fill-opacity="0.75">${label}</text>
</svg>`;

  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

export function SmartImage({
  src,
  alt = "",
  retries = RETRY_DELAYS.length,
  retryDelay,
  className = "",
  loading = "lazy",
  decoding = "async",
  ...rest
}: SmartImageProps) {
  const empty = src === null || src === undefined || src === "";

  const [status, setStatus] = useState<Status>(empty ? "missing" : "loading");
  const [attempt, setAttempt] = useState(0);
  /** The URL that actually came back, once a retry has succeeded. */
  const [painted, setPainted] = useState<string | null>(null);
  const node = useRef<HTMLImageElement | null>(null);
  const statusRef = useRef<Status>("loading");

  statusRef.current = status;

  // A new source is a new picture: start over rather than keeping the old state.
  useEffect(() => {
    setStatus(empty ? "missing" : "loading");
    setAttempt(0);
    setPainted(null);
  }, [src, empty]);

  /**
   * The events are listened for on the element itself, not through React props,
   * because this page is prerendered: the browser starts — and can finish or
   * fail — these images while the markup is still being parsed, long before
   * React has hydrated. A load event nobody listened for leaves a photograph
   * marked as loading; an error event nobody listened for means an image that
   * never retries. So on mount we also ask the element how it is doing.
   */
  useEffect(() => {
    const img = node.current;

    if (!img) return;

    const onLoad = () => {
      // The drawn stand-in is an image too: its load says nothing about whether
      // the photograph arrived, so it must not close the chapter.
      if ((img.currentSrc || img.src).startsWith("data:")) return;
      setStatus("loaded");
    };
    const onError = () => {
      // If a picture was already on screen, the failure is the swap's, not the
      // original's — spend another attempt so the chain always moves forward.
      if (statusRef.current === "loaded") setAttempt((current) => current + 1);
      setStatus("retrying");
    };

    img.addEventListener("load", onLoad);
    img.addEventListener("error", onError);

    if (img.complete) {
      if (img.naturalWidth > 0) {
        setStatus("loaded");
      } else if (img.currentSrc) {
        // `currentSrc` is only set once the browser has actually picked a
        // resource, so this is a real failure — not a lazy image that simply
        // has not been reached yet.
        onError();
      }
    }

    return () => {
      img.removeEventListener("load", onLoad);
      img.removeEventListener("error", onError);
    };
  }, [src]);

  /**
   * The retry chain. Each run waits its turn, then asks for a cache-busted copy
   * with an off-screen `Image`; a failure lands back here with one more attempt
   * spent, a success swaps the picture in.
   */
  useEffect(() => {
    if (status !== "retrying" || empty) return;

    if (attempt >= retries) {
      setStatus("missing");
      return;
    }

    const delay =
      retryDelay !== undefined ? retryDelay * 2 ** attempt : (RETRY_DELAYS[attempt] ?? 4500);
    const url = withAttempt(src as string, attempt + 1);

    const timer = window.setTimeout(() => {
      const probe = new Image();

      probe.onload = () => {
        setPainted(url);
        setStatus("loaded");
      };
      probe.onerror = () => setAttempt((current) => current + 1);
      probe.src = url;
    }, delay);

    return () => window.clearTimeout(timer);
  }, [status, attempt, retries, retryDelay, src, empty]);

  // The stand-in is for when there is nothing better to show; once a picture has
  // loaded — whether through the element itself or through a retry — that is
  // what the box shows.
  const standIn = status === "retrying" || status === "missing";
  const shown = empty
    ? placeholder(alt)
    : (painted ?? (standIn ? placeholder(alt) : (src as string)));

  return (
    <img
      {...rest}
      ref={node}
      src={shown}
      alt={alt}
      loading={loading}
      decoding={decoding}
      className={`smart-image is-${status} ${className}`.trim()}
      data-attempt={status === "missing" ? "failed" : attempt}
    />
  );
}
