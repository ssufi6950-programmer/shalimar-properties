/* Shalimar Properties — shared UI (vanilla, no dependencies) */
(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* sticky header + back-to-top */
  const hdr = $("#hdr"), up = $("#up");
  const onScroll = () => {
    hdr && hdr.classList.toggle("stuck", scrollY > 10);
    up && up.classList.toggle("on", scrollY > 700);
  };
  addEventListener("scroll", onScroll, { passive: true });
  onScroll();
  up && (up.onclick = () => scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" }));

  /* drawer (desktop burger + mobile "More" tab) */
  const drawer = $("#drawer"), overlay = $("#drawerOverlay"), burger = $("#burger");
  function openDrawer() {
    if (!drawer) return;
    drawer.classList.add("on"); overlay && overlay.classList.add("active");
    burger && (burger.classList.add("on"), burger.setAttribute("aria-expanded", "true"));
    document.body.style.overflow = "hidden";
  }
  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove("on"); overlay && overlay.classList.remove("active");
    burger && (burger.classList.remove("on"), burger.setAttribute("aria-expanded", "false"));
    document.body.style.overflow = "";
  }
  burger && (burger.onclick = () => (drawer.classList.contains("on") ? closeDrawer() : openDrawer()));
  const tabMore = $("#tabMore");
  tabMore && (tabMore.onclick = () => (drawer.classList.contains("on") ? closeDrawer() : openDrawer()));
  $("#drawerClose") && ($("#drawerClose").onclick = closeDrawer);
  overlay && (overlay.onclick = closeDrawer);
  addEventListener("keydown", (e) => { if (e.key === "Escape") closeDrawer(); });

  /* scroll reveal + counters */
  function count(el) {
    const to = parseFloat(el.dataset.to || "0"), suf = el.dataset.suffix || "", pre = el.dataset.prefix || "";
    const dec = (el.dataset.to || "").includes(".") ? 1 : 0;
    if (reduce) { el.textContent = pre + to.toLocaleString("en-US", { maximumFractionDigits: dec }) + suf; return; }
    const t0 = performance.now(), dur = 1300;
    (function tick(t) {
      const p = Math.min(1, (t - t0) / dur), k = 1 - Math.pow(1 - p, 3);
      el.textContent = pre + (to * k).toLocaleString("en-US", { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suf;
      if (p < 1) requestAnimationFrame(tick);
    })(t0);
  }
  const reveal = (el) => { el.classList.add("in"); $$("[data-to]", el).forEach(count); if (el.dataset && el.dataset.to) count(el); };
  if ("IntersectionObserver" in window && !reduce) {
    const io = new IntersectionObserver((es) => es.forEach((e) => {
      if (!e.isIntersecting) return;
      reveal(e.target); io.unobserve(e.target);
    }), { threshold: 0.12, rootMargin: "0px 0px -30px 0px" });
    $$(".rv").forEach((el) => io.observe(el));
    $$("[data-to]").filter((el) => !el.closest(".rv")).forEach((el) => io.observe(el));
  } else {
    $$(".rv").forEach(reveal);
    $$("[data-to]").forEach(count);
  }

  /* filter chips (label > checkbox) and tabs that set a hidden input */
  $$(".find-chip input[type=checkbox]").forEach((cb) => {
    const sync = () => cb.closest(".find-chip").classList.toggle("on", cb.checked);
    cb.addEventListener("change", sync); sync();
  });
  $$(".find-tabs[data-target]").forEach((tabs) => {
    const input = document.getElementById(tabs.dataset.target);
    $$(".find-tab", tabs).forEach((tab) => tab.addEventListener("click", () => {
      $$(".find-tab", tabs).forEach((t) => t.classList.remove("on"));
      tab.classList.add("on");
      if (input) { input.value = tab.dataset.value || ""; if (tabs.dataset.autosubmit) input.form.submit(); }
    }));
  });

  /* desktop drag-to-scroll for rails */
  $$(".rail").forEach((rail) => {
    let down = false, sx = 0, sl = 0, moved = false;
    rail.addEventListener("pointerdown", (e) => { if (e.pointerType !== "mouse") return; down = true; moved = false; sx = e.clientX; sl = rail.scrollLeft; });
    addEventListener("pointerup", () => { down = false; rail.style.scrollSnapType = ""; });
    rail.addEventListener("pointermove", (e) => {
      if (!down) return;
      const dx = e.clientX - sx;
      if (Math.abs(dx) > 4) { moved = true; rail.style.scrollSnapType = "none"; rail.scrollLeft = sl - dx; }
    });
    rail.addEventListener("click", (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); } }, true);
    const box = rail.closest("section");
    const prev = box && box.querySelector("[data-rail-prev]"), next = box && box.querySelector("[data-rail-next]");
    const step = () => (rail.firstElementChild ? rail.firstElementChild.offsetWidth + 16 : 300);
    prev && (prev.onclick = () => rail.scrollBy({ left: -step(), behavior: "smooth" }));
    next && (next.onclick = () => rail.scrollBy({ left: step(), behavior: "smooth" }));
  });

  /* image upload preview */
  $$("input[type=file][data-preview]").forEach((inp) => {
    const box = document.getElementById(inp.dataset.preview);
    const zone = inp.closest(".dropzone");
    inp.addEventListener("change", () => {
      if (!box) return;
      box.innerHTML = "";
      [...inp.files].slice(0, 6).forEach((f) => {
        if (!/^image\//.test(f.type)) return;
        const img = document.createElement("img");
        img.src = URL.createObjectURL(f); img.alt = f.name; box.appendChild(img);
      });
    });
    if (zone) {
      ["dragenter", "dragover"].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add("drag"); }));
      ["dragleave", "drop"].forEach((ev) => zone.addEventListener(ev, () => zone.classList.remove("drag")));
      zone.addEventListener("drop", (e) => { e.preventDefault(); inp.files = e.dataTransfer.files; inp.dispatchEvent(new Event("change")); });
    }
  });

  /* dependent selects: sector/block options filtered by project (data-project / data-sector) */
  $$("select[data-depends]").forEach((sel) => {
    const parent = document.getElementById(sel.dataset.depends);
    if (!parent) return;
    const attr = sel.dataset.attr || "project";
    const sync = () => {
      const v = parent.value;
      $$("option", sel).forEach((o) => {
        const ok = !o.dataset[attr] || !v || o.dataset[attr] === v;
        o.hidden = !ok; o.disabled = !ok;
      });
      if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) sel.value = "";
      sel.dispatchEvent(new Event("refresh"));
    };
    parent.addEventListener("change", sync);
    parent.addEventListener("refresh", sync);
    sync();
  });

  /* confirm buttons */
  $$("[data-confirm]").forEach((b) => b.addEventListener("click", (e) => { if (!confirm(b.dataset.confirm)) e.preventDefault(); }));
})();
