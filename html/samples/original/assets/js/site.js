(function () {
  const hdr = document.getElementById("hdr");
  const up = document.getElementById("up");
  const burger = document.getElementById("burger");
  const drawer = document.getElementById("drawer");
  const drawerClose = document.getElementById("drawerClose");
  const drawerOverlay = document.getElementById("drawerOverlay");

  if (hdr && up) {
    addEventListener("scroll", () => {
      hdr.classList.toggle("stuck", scrollY > 24);
      up.classList.toggle("on", scrollY > 800);
    }, { passive: true });
    up.onclick = () => scrollTo({ top: 0, behavior: "smooth" });
  }

  function openDrawer() {
    if (!drawer) return;
    drawer.classList.add("on");
    drawerOverlay?.classList.add("active");
    burger?.classList.add("on");
    burger?.setAttribute("aria-expanded", "true");
    document.body.style.overflow = "hidden";
  }
  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove("on");
    drawerOverlay?.classList.remove("active");
    burger?.classList.remove("on");
    burger?.setAttribute("aria-expanded", "false");
    document.body.style.overflow = "";
  }

  if (burger && drawer) {
    burger.onclick = () => (drawer.classList.contains("on") ? closeDrawer() : openDrawer());
    drawerClose?.addEventListener("click", closeDrawer);
    drawerOverlay?.addEventListener("click", closeDrawer);
    drawer.querySelectorAll("a").forEach((a) => (a.onclick = closeDrawer));
    addEventListener("resize", () => { if (innerWidth > 980) closeDrawer(); });
  }

  const io = new IntersectionObserver((es) => es.forEach((e) => {
    if (!e.isIntersecting) return;
    e.target.classList.add("in");
    e.target.querySelectorAll?.("[data-to]")?.forEach(count);
    io.unobserve(e.target);
  }), { threshold: 0.18 });
  document.querySelectorAll(".rv").forEach((el) => io.observe(el));

  function count(el) {
    const to = +el.dataset.to;
    const suf = el.dataset.suffix || "";
    const t0 = performance.now();
    const dur = 1400;
    (function tick(t) {
      const p = Math.min(1, (t - t0) / dur);
      const e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(to * e).toLocaleString("en-US") + suf;
      if (p < 1) requestAnimationFrame(tick);
    })(t0);
  }

  document.querySelectorAll(".find-tabs").forEach((tabs) => {
    tabs.querySelectorAll(".find-tab").forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.querySelectorAll(".find-tab").forEach((t) => t.classList.remove("on"));
        tab.classList.add("on");
      });
    });
  });
  document.querySelectorAll(".find-chip").forEach((chip) => {
    chip.addEventListener("click", () => chip.classList.toggle("on"));
  });
})();
