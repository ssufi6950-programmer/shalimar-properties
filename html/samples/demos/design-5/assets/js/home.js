(function () {
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* search tabs */
  const form = $("#heroSearch"), tabs = $("#tabs"), pill = $("#pill");
  if (form && tabs && pill) {
  const movePill = (el) => { pill.style.width = el.offsetWidth + "px"; pill.style.transform = "translateX(" + el.offsetLeft + "px)"; };
  $$(".tab", tabs).forEach((t) => t.addEventListener("click", () => {
    $(".tab.on", tabs).classList.remove("on"); t.classList.add("on"); movePill(t); form.action = t.dataset.action;
  }));
  const syncPill = () => movePill($(".tab.on", tabs));
  addEventListener("load", syncPill); addEventListener("resize", syncPill); syncPill();
  }

  /* hero stage: gentle pointer tilt */
  const stage = $("#hero");
  if (stage && !reduce && matchMedia("(hover:hover)").matches) {
    stage.addEventListener("pointermove", (e) => {
      const r = stage.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      stage.style.setProperty("--px", (x * 18).toFixed(1) + "px"); stage.style.setProperty("--py", (y * 14).toFixed(1) + "px");
    });
    stage.addEventListener("pointerleave", () => { stage.style.setProperty("--px", "0px"); stage.style.setProperty("--py", "0px"); });
  }

  /* project spotlight (auto-advance with progress) */
  const spot = $("#spot");
  if (spot) {
  const stabs = $$(".spot-tab", spot), panels = $$(".spot-panel", spot);
  let si = 0, stimer, inView = false;
  const DUR = 7000;
  function sgo(n, user) {
    si = (n + panels.length) % panels.length;
    stabs.forEach((t, i) => { t.classList.toggle("on", i === si); t.setAttribute("aria-selected", i === si ? "true" : "false"); t.classList.remove("run"); });
    panels.forEach((p, i) => { p.classList.toggle("on", i === si); p.setAttribute("aria-hidden", i === si ? "false" : "true"); });
    if (user) spot.classList.add("paused");
    srun();
  }
  function srun() {
    clearTimeout(stimer);
    if (reduce || !inView || spot.classList.contains("paused") || panels.length < 2) return;
    const t = stabs[si]; void t.offsetWidth; t.classList.add("run");
    stimer = setTimeout(() => sgo(si + 1), DUR);
  }
  stabs.forEach((t, i) => (t.onclick = () => sgo(i, true)));
  if ("IntersectionObserver" in window) new IntersectionObserver((es) => { inView = es[0].isIntersecting; inView ? srun() : (clearTimeout(stimer), stabs[si].classList.remove("run")); }, { threshold: .35 }).observe(spot);
  let sx = 0;
  spot.addEventListener("touchstart", (e) => (sx = e.touches[0].clientX), { passive: true });
  spot.addEventListener("touchend", (e) => { const d = e.changedTouches[0].clientX - sx; if (Math.abs(d) > 50 && e.target.closest(".spot-stage")) sgo(si + (d < 0 ? 1 : -1), true); });
  }

  let moved = false;
  /* story viewer */
  const S = window.SP_STORIES || [];
  const sv = $("#sv");
  if (sv && S.length) {
  document.body.appendChild(sv); const segs = $("#svSegs"), img = $("#svImg"), content = $("#svContent");
  const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  const FR = 2, FDUR = reduce ? 0 : 6500;
  let cs = 0, cf = 0, lastFocus = null;
  function render() {
    const s = S[cs];
    $("#svAv").textContent = s.ini; $("#svName").textContent = s.name; $("#svWhen").textContent = s.role + " · " + s.when;
    img.style.backgroundImage = "url('" + s.img + "')";
    img.classList.toggle("blur", cf === 1);
    segs.innerHTML = Array.from({ length: FR }, (_, i) => '<span class="' + (i < cf ? "done" : i === cf ? "run" : "") + '"><i></i></span>').join("");
    content.className = "sv-content f" + cf;
    content.innerHTML = cf === 0
      ? '<span class="sv-proj"><i class="fa-solid fa-location-dot"></i> ' + esc(s.project) + '</span><blockquote>“' + esc(s.quote) + '”</blockquote>'
      : '<span class="sv-proj"><i class="fa-solid fa-circle-check"></i> The deal, on record</span><ul class="sv-facts">' + s.facts.map((f, i) => '<li style="--i:' + i + '"><b>' + esc(f) + "</b></li>").join("") + '</ul><a class="btn btn-gold btn-sm" href="projects.php#' + esc(s.slug) + '">Explore ' + esc(s.project) + ' <i class="fa-solid fa-arrow-right-long"></i></a>';
    const run = $(".run i", segs);
    if (run) { if (FDUR) { run.style.animationDuration = FDUR + "ms"; run.addEventListener("animationend", () => next(), { once: true }); } else run.style.width = "100%"; }
  }
  function next() { if (cf < FR - 1) cf++; else if (cs < S.length - 1) { cs++; cf = 0; } else return close(); render(); }
  function prev() { if (cf > 0) cf--; else if (cs > 0) { cs--; cf = 0; } render(); }
  function open(i) { lastFocus = document.activeElement; cs = i; cf = 0; sv.hidden = false; requestAnimationFrame(() => sv.classList.add("on")); document.body.style.overflow = "hidden"; render(); $("#svClose").focus(); }
  function close() { sv.classList.remove("on"); document.body.style.overflow = ""; setTimeout(() => (sv.hidden = true), 250); segs.innerHTML = ""; lastFocus && lastFocus.focus && lastFocus.focus(); }
  $$("[data-open]").forEach((el) => {
    el.addEventListener("click", () => { if (moved) return; open(+el.dataset.open); });
    el.addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(+el.dataset.open); } });
  });
  $("#svClose").onclick = close; $("#svTapR").onclick = next; $("#svTapL").onclick = prev; $("#svNext").onclick = next; $("#svPrev").onclick = prev;
  sv.addEventListener("click", (e) => { if (e.target === sv) close(); });
  addEventListener("keydown", (e) => { if (sv.hidden) return; if (e.key === "Escape") close(); if (e.key === "ArrowRight") next(); if (e.key === "ArrowLeft") prev(); });
  const frame = $("#svFrame");
  const hold = (on) => frame.classList.toggle("hold", on);
  frame.addEventListener("pointerdown", () => hold(true)); frame.addEventListener("pointerup", () => hold(false)); frame.addEventListener("pointerleave", () => hold(false));
  let ty = 0, tx2 = 0;
  frame.addEventListener("touchstart", (e) => { ty = e.touches[0].clientY; tx2 = e.touches[0].clientX; }, { passive: true });
  frame.addEventListener("touchend", (e) => { const dy = e.changedTouches[0].clientY - ty, dx = e.changedTouches[0].clientX - tx2; if (dy > 90 && Math.abs(dx) < 60) close(); });
  }

  /* accordion (communities) */
  const ps = $$(".c-panel");
  const act = (p) => ps.forEach((x) => x.classList.toggle("on", x === p));
  ps.forEach((p) => { p.addEventListener("mouseenter", () => matchMedia("(hover:hover)").matches && act(p)); p.addEventListener("click", () => act(p)); p.addEventListener("focus", () => act(p)); });

  /* project rows: floating preview follows the pointer */
  $$(".pr-rows").forEach((box) => {
    const fl = $(".pr-float", box); if (!fl) return;
    $$(".pr-row", box).forEach((r) => r.addEventListener("mouseenter", () => { $$("img", fl).forEach((im) => im.classList.toggle("on", im.dataset.k === r.dataset.k)); }));
    box.addEventListener("mousemove", (e) => { const b = box.getBoundingClientRect(); fl.style.transform = "translate(" + (e.clientX - b.left + 28) + "px," + (e.clientY - b.top - 120) + "px)"; });
  });

  /* D2 full-bleed slider + rotating brand card */
  const d2 = $$(".d2-slide"), dd = $$("#d2Dots button"), hl = $$(".d2-brand .hl");
  if (d2.length > 1) {
    let hi = 0, ht;
    const hgo = (n) => {
      hi = (n + d2.length) % d2.length;
      d2.forEach((s, k) => s.classList.toggle("on", k === hi));
      hl.forEach((s, k) => s.classList.toggle("on", k === hi % hl.length));
      dd.forEach((b, k) => { b.classList.remove("on"); if (k === hi) { void b.offsetWidth; b.classList.add("on"); } });
      clearTimeout(ht); if (!reduce) ht = setTimeout(() => hgo(hi + 1), 6500);
    };
    dd.forEach((b, k) => (b.onclick = () => hgo(k)));
    hgo(0);
  }
})();
