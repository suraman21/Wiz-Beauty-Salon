/* Runs as an async script in the head: `async` because a classic script — inline
 * or not — waits for the stylesheet ahead of it, and on a slow connection the
 * stylesheet is exactly what is late.
 *
 * The reveal animations keep their sections at opacity 0 until an observer
 * turns them on. That is a flourish on a fast connection and a blank page on a
 * slow one, so if the app has not reported itself alive after 2.6 seconds the
 * document is marked `reveal-off` and every waiting element is shown as it is:
 * plain, but readable — the words are never held hostage by JavaScript. */
(function () {
  var d = document.documentElement;
  d.classList.add("js-reveal");

  var timer = setTimeout(function () {
    if (d.getAttribute("data-app") !== "ready") {
      d.classList.remove("js-reveal");
      d.classList.add("reveal-off");
    }
  }, 2600);

  window.__wizReady = function () {
    clearTimeout(timer);
    d.setAttribute("data-app", "ready");
    /* If the plain fallback has already been switched on it stays on: the words
       are on screen and they will not be taken away again. */
    if (d.classList.contains("reveal-off")) return;
    d.classList.add("js-reveal");
  };
})();
