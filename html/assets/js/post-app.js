/* Post-a-property — app-style interactions */
(function () {
  "use strict";
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const buzz = (ms = 8) => { try { navigator.vibrate && navigator.vibrate(ms); } catch (e) {} };
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* selection micro-feedback */
  $$(".ppa-card input, .ppa-prop input, .ppa-seg input, .ppa-proj input").forEach((r) => r.addEventListener("change", () => buzz()));

  /* uppercase IDs */
  $$("input[data-upper]").forEach((i) => i.addEventListener("input", () => { const p = i.selectionStart; i.value = i.value.toUpperCase(); try { i.setSelectionRange(p, p); } catch (e) {} }));

  /* phone auto-format 03xx-xxxxxxx + inline error */
  $$("input[data-phone]").forEach((i) => {
    const msg = $('[data-msg-for="' + i.id + '"]');
    const fmt = () => { let d = i.value.replace(/\D/g, ""); if (d.startsWith("92")) d = "0" + d.slice(2); d = d.slice(0, 11); i.value = d.length > 4 ? d.slice(0, 4) + "-" + d.slice(4) : d; };
    const check = () => { const d = i.value.replace(/\D/g, ""); const bad = d.length > 0 && (d.length !== 11 || d[0] !== "0"); i.setAttribute("aria-invalid", bad ? "true" : "false"); if (msg) { msg.textContent = bad ? "Enter the 11-digit mobile number, e.g. 0300-1234567" : ""; msg.classList.toggle("err", bad); } return !bad && d.length === 11; };
    i.addEventListener("input", () => { fmt(); if (i.getAttribute("aria-invalid") === "true") check(); });
    i.addEventListener("blur", check);
    fmt();
    const f = i.form; f && f.addEventListener("submit", (e) => { if (!check()) { e.preventDefault(); e.stopImmediatePropagation(); buzz([10, 40, 10]); i.focus(); } }, true);
  });

  /* PKR demand: commas + Lac / Crore words */
  const words = (n) => {
    if (!n) return "\u00a0";
    if (n >= 1e7) return "PKR " + +(n / 1e7).toFixed(2) + " Crore";
    if (n >= 1e5) return "PKR " + +(n / 1e5).toFixed(2) + " Lac";
    if (n >= 1e3) return "PKR " + +(n / 1e3).toFixed(1) + " Thousand";
    return "PKR " + n;
  };
  $$("input[data-money]").forEach((i) => {
    const hint = $("#priceHint");
    const run = () => { const n = +i.value.replace(/\D/g, "").slice(0, 12); i.value = n ? n.toLocaleString("en-PK") : ""; if (hint) hint.textContent = words(n); };
    i.addEventListener("input", run); run();
  });
  const lbl = $("#priceLbl");
  if (lbl) {
    const sync = () => { const r = $('input[name="purpose"]:checked'); const rent = r && r.value === "rent"; lbl.textContent = rent ? "Your demand — monthly rent" : "Your demand — asking price"; const p = $("#price"); if (p) p.placeholder = rent ? "e.g. 85,000" : "e.g. 5,250,000"; };
    $$('input[name="purpose"]').forEach((r) => r.addEventListener("change", sync)); sync();
  }

  /* OTP boxes */
  const box = $("#otpBoxes");
  if (box) {
    const ins = $$("input", box), hid = $("#otpVal"), form = $("#otpForm");
    const val = () => ins.map((x) => x.value).join("");
    const paint = () => ins.forEach((x) => x.classList.toggle("filled", !!x.value));
    const spread = (s, from) => { const d = s.replace(/\D/g, "").split(""); let k = from; d.forEach((c) => { if (k < 6) ins[k++].value = c; }); paint(); (ins[Math.min(k, 5)]).focus(); done(); };
    let sent = false;
    const done = () => { hid.value = val(); if (hid.value.length === 6 && !sent) { sent = true; buzz(12); setTimeout(() => form.requestSubmit ? form.requestSubmit() : form.submit(), 220); } };
    ins.forEach((x, i) => {
      x.addEventListener("input", () => {
        const v = x.value.replace(/\D/g, "");
        if (v.length > 1) { x.value = ""; spread(v, i); return; }
        x.value = v; paint(); if (v) { buzz(5); if (i < 5) ins[i + 1].focus(); } done();
      });
      x.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && !x.value && i > 0) { ins[i - 1].value = ""; ins[i - 1].focus(); paint(); sent = false; e.preventDefault(); }
        if (e.key === "ArrowLeft" && i > 0) ins[i - 1].focus();
        if (e.key === "ArrowRight" && i < 5) ins[i + 1].focus();
      });
      x.addEventListener("focus", () => x.select());
      x.addEventListener("paste", (e) => { const t = (e.clipboardData || window.clipboardData).getData("text"); if (t) { e.preventDefault(); spread(t, i); } });
    });
    form.addEventListener("submit", (e) => { hid.value = val(); if (hid.value.length !== 6) { e.preventDefault(); e.stopImmediatePropagation(); sent = false; box.classList.remove("bad"); void box.offsetWidth; box.classList.add("bad"); buzz([10, 40, 10]); ins[Math.min(hid.value.length, 5)].focus(); } }, true);
    if (document.querySelector(".ppa-err")) { box.classList.add("bad"); }
  }
  const rs = $("#resendBtn");
  if (rs) {
    let w = +rs.dataset.wait || 0; const t = $(".t", rs);
    const tick = () => { if (w > 0) { rs.disabled = true; t.textContent = " in 0:" + String(w).padStart(2, "0"); w--; setTimeout(tick, 1000); } else { rs.disabled = false; t.textContent = ""; } };
    tick();
  }

  /* swipeable property rail + dots */
  const rail = $("#holdRail"), dots = $$("#holdDots i");
  if (rail && dots.length) {
    const cards = $$(".ppa-prop", rail);
    const upd = () => { const c = rail.scrollLeft + rail.clientWidth / 2; let best = 0, bd = 1e9; cards.forEach((k, i) => { const m = k.offsetLeft + k.offsetWidth / 2, d = Math.abs(m - c); if (d < bd) { bd = d; best = i; } }); dots.forEach((d, i) => d.classList.toggle("on", i === best)); };
    rail.addEventListener("scroll", () => requestAnimationFrame(upd), { passive: true });
    cards.forEach((k) => $("input", k).addEventListener("change", () => k.scrollIntoView({ behavior: reduce ? "auto" : "smooth", inline: "center", block: "nearest" })));
  }


  /* property search + category / subcategory / type filters (Which property screen) */
  const ps = $("#propSearch"), prail = $("#holdRail");
  if (ps && prail) {
    const q = $("#propQ");
    if (innerWidth < 430) q.placeholder = "Plot no., category, size";
    const clr = $("#propClear"), cnt = $("#propCount"), empty = $("#propEmpty"), dotsBox = $("#holdDots");
    const cards = $$(".ppa-prop", prail);
    const rowC = $('[data-chip="c"]', ps), rowB = $('[data-chip="b"]', ps), rowX = $('[data-chip="extra"]', ps);
    const ROM = { i: 1, ii: 2, iii: 3, iv: 4, v: 5, vi: 6, vii: 7, viii: 8, ix: 9, x: 10, xi: 11, xii: 12 };
    const norm = (t) => (" " + String(t).toLowerCase().replace(/[,·–—\-_/]+/g, " ") + " ").replace(/\b(xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)\b/g, (m) => String(ROM[m])).replace(/\s+/g, " ");
    const esc = (t) => t.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    cards.forEach((c) => { c._q = norm(c.dataset.q + " " + c.textContent); $$(".bd b, .bd small", c).forEach((n) => { n._html = n.innerHTML; }); });
    const filt = { c: "", b: "", x: "" };
    const mark = (c, toks) => {
      $$(".bd b, .bd small", c).forEach((n) => {
        n.innerHTML = n._html;
        if (!toks.length) return;
        const walker = document.createTreeWalker(n, NodeFilter.SHOW_TEXT); const texts = []; while (walker.nextNode()) texts.push(walker.currentNode);
        const re = new RegExp("(" + toks.map(esc).join("|") + ")", "gi");
        texts.forEach((tn) => { if (!re.test(tn.nodeValue)) return; re.lastIndex = 0; const span = document.createElement("span"); span.innerHTML = tn.nodeValue.replace(/[&<>]/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;" }[ch])).replace(re, "<mark>$1</mark>"); tn.replaceWith(...span.childNodes); });
      });
    };
    const syncSub = () => {
      if (!rowB) return;
      const cat = filt.c.startsWith("c:") ? filt.c.slice(2) : "";
      let any = false;
      $$("button", rowB).forEach((b) => {
        if (!b.dataset.f) { b.hidden = false; return; }
        const ok = !cat || b.dataset.cat === cat;
        b.hidden = !ok;
        if (ok && b.dataset.f) any = true;
      });
      rowB.hidden = !!(rowC && !cat);
      if (cat && filt.b.startsWith("b:")) {
        const on = $$("button", rowB).find((b) => !b.hidden && b.dataset.f === filt.b);
        if (!on) { filt.b = ""; $$("button", rowB).forEach((b, i) => b.classList.toggle("on", i === 0)); }
      }
      if (!any && cat) rowB.hidden = true;
    };
    const fCat = $("#fCat"), fSub = $("#fSub"), pick = $("#propPick"), go = $(".ppa-actions .ppa-primary", ps.closest("form"));
    const cs = $("#catStep"); let selC = null, selB = null; // sequential: category, then sub-category
    const run = () => {
      syncSub();
      if (cs) filt.b = selC !== null && selB !== null ? "b:" + (selC === "_other" ? "" : selC) + "|" + selB : "";
      const chosen = !(rowB || cs) || !!filt.b; // category + sub-category are compulsory
      if (pick && cs) $("span", pick).textContent = selC === null ? "Select a category to see your properties" : "Now select a sub-category";
      if (rowB || cs) {
        if (fCat) fCat.value = filt.b.includes("|") ? (filt.b.slice(2).split("|")[0] || "_other") : "";
        if (fSub) fSub.value = filt.b.startsWith("b:") ? filt.b.slice(2).split("|").pop() : "";
      }
      const raw = q.value.trim(), nq = norm(raw).trim(), toks = nq ? nq.split(" ") : [];
      const rawToks = raw.toLowerCase().split(/[\s,]+/).filter((t) => t.length > 0);
      const active = !!(raw || filt.c || filt.b || filt.x);
      let shown = 0;
      const isMatch = (c) => {
        const okC = !filt.c || c.dataset.c === filt.c.slice(2);
        const bk = filt.b.slice(2), okB = !filt.b || (bk.includes("|") ? c.dataset.c + "|" + c.dataset.b === bk : c.dataset.b === bk);
        const okX = !filt.x || (filt.x.startsWith("t:") ? c.dataset.t === filt.x.slice(2) : c.dataset.s === filt.x.slice(2));
        return okC && okB && okX && toks.every((t) => c._q.includes(t));
      };
      // chips choose the property: if the selected one is outside the chosen category/sub-category, select the first match
      if (chosen && (filt.c || filt.b || filt.x)) {
        const cur = cards.find((c) => $("input", c).checked), first = cards.find(isMatch);
        if (first && (!cur || !isMatch(cur))) $("input", first).checked = true;
      }
      cards.forEach((c) => {
        const match = isMatch(c), sel = $("input", c).checked;
        c.hidden = !chosen || (!match && !sel);
        c.classList.toggle("pinned", chosen && !match && sel);
        if (match) shown++;
        mark(c, match ? rawToks : []);
      });
      cnt.textContent = (active ? shown + " of " + cards.length : cards.length + " of " + cards.length) + (cards.length === 1 ? " property" : " properties");
      clr.hidden = !raw;
      empty.hidden = !chosen || shown > 0;
      if (pick) pick.hidden = chosen;
      if (go) go.disabled = !(chosen && cards.some((c) => !c.hidden && $("input", c).checked));
      if (!chosen) cnt.textContent = "Select a category";
      if (!shown) {
        const onBtn = $(".ppa-chips button.on", ps);
        $("span", empty).textContent = raw || (onBtn && onBtn.textContent) || "";
      }
      if (dotsBox) dotsBox.style.visibility = active || !chosen ? "hidden" : "";
      prail.classList.toggle("filtering", active);
      if (active) prail.scrollTo({ left: 0 });
    };
    q.addEventListener("input", run);
    q.addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); q.blur(); } if (e.key === "Escape") { q.value = ""; run(); } });
    const bindRow = (row, key) => {
      if (!row) return;
      $$("button", row).forEach((b) => b.addEventListener("click", () => {
        filt[key] = b.dataset.f || "";
        if (key === "c") filt.b = "";
        $$("button", row).forEach((x) => x.classList.toggle("on", x === b));
        if (key === "c" && rowB) $$("button", rowB).forEach((x, i) => x.classList.toggle("on", i === 0));
        buzz(6); run();
      }));
    };
    bindRow(rowC, "c"); bindRow(rowB, "b"); bindRow(rowX, "x");
    const reset = () => {
      q.value = ""; filt.c = ""; filt.b = ""; filt.x = "";
      [rowC, rowB, rowX].forEach((row) => row && $$("button", row).forEach((b, i) => b.classList.toggle("on", row !== rowB && i === 0)));
      run(); q.focus(); buzz(6);
    };
    clr.addEventListener("click", reset); $("#propReset").addEventListener("click", reset);
    const pform = ps.closest("form");
    if (pform) pform.addEventListener("submit", (e) => {
      const sc = cards.find((c) => $("input", c).checked);
      if (!sc || ((rowB || cs) && !filt.b)) { e.preventDefault(); e.stopImmediatePropagation(); buzz(10); return; }
      if (sc.classList.contains("pinned")) { e.preventDefault(); e.stopImmediatePropagation(); q.value = ""; run(); }
    }, true);
    cards.forEach((c) => $("input", c).addEventListener("change", run));
    if (cs) {
      const pickC = (c, auto) => {
        selC = c; selB = null;
        $$("[data-cbtn]", cs).forEach((x) => x.classList.toggle("on", x.dataset.cbtn === c));
        cards.forEach((x) => { if (!auto) $("input", x).checked = false; });
        $$("[data-subrow]", cs).forEach((r) => {
          const on = r.dataset.subrow === c; r.hidden = !on;
          if (!on) return;
          $$("[data-bbtn]", r).forEach((x) => x.classList.remove("on"));
          r.classList.remove("rv-in"); void r.offsetWidth; r.classList.add("rv-in");
          if (r.dataset.autoB !== undefined) selB = r.dataset.autoB;
        });
        q.value = ""; run();
      };
      $$("[data-cbtn]", cs).forEach((b) => b.addEventListener("click", () => { buzz(6); pickC(b.dataset.cbtn, false); }));
      $$("[data-bbtn]", cs).forEach((b) => b.addEventListener("click", () => {
        selB = b.dataset.bbtn; $$("[data-bbtn]", b.closest("[data-subrow]")).forEach((x) => x.classList.toggle("on", x === b));
        prail.classList.remove("rv-in"); void prail.offsetWidth; prail.classList.add("rv-in"); buzz(6); run();
      }));
      if (cs.dataset.autoC !== undefined) pickC(cs.dataset.autoC, true);
    }
    run();
  }


  /* service charges: default 1% of price (sale) or half month's rent (rent); editable */
  const svc = $("#service");
  if (svc) {
    const price = $("#price"), hint = $("#svcHint"), docs = $("#docs");
    let edited = svc.value !== "";
    const fmtN = (n) => (n ? n.toLocaleString("en-PK") : "");
    const calc = () => {
      const r = $('input[name="purpose"]:checked'), rent = r && r.value === "rent";
      const n = +(price.value || "").replace(/\D/g, "");
      hint.textContent = "Service charges default: " + (rent ? "half month’s rent" : "1% of price") + " — you can change it.";
      if (!edited) svc.value = fmtN(Math.round(rent ? n / 2 : n * 0.01));
      total();
    };
    const total = () => {
      const r = $('input[name="purpose"]:checked'), rent = r && r.value === "rent";
      const num = (i) => +((i && i.value) || "").replace(/\D/g, "");
      const t = num(price) + num(svc) + num(docs);
      $("#gtLbl").textContent = rent ? "Total (first month)" : "Grand total";
      $("#gtVal").textContent = "PKR " + (t ? t.toLocaleString("en-PK") : "0");
      $("#gtWords").textContent = t ? words(t) + (rent ? " · rent + charges" : " · demand + charges") : "\u00a0";
    };
    [svc, docs].forEach((i) => i.addEventListener("input", () => { const n = +i.value.replace(/\D/g, "").slice(0, 12); i.value = fmtN(n); if (i === svc) edited = i.value !== ""; total(); }));
    price.addEventListener("input", calc);
    $$('input[name="purpose"]').forEach((r) => r.addEventListener("change", () => { edited = false; calc(); }));
    calc();
  }

  /* photo picker grid: + tile, previews, remove */
  $$(".ppa-photos").forEach((grid) => {
    const inp = $("input[type=file]", grid), add = $(".ppa-add", grid);
    const max = +grid.dataset.max || 6, maxB = (+grid.dataset.maxmb || 4) * 1048576;
    let files = [];
    const canDT = (() => { try { return !!new DataTransfer(); } catch (e) { return false; } })();
    const sync = () => { if (canDT) { const dt = new DataTransfer(); files.forEach((f) => dt.items.add(f)); inp.files = dt.files; } };
    const render = () => {
      $$(".ppa-ph", grid).forEach((n) => { URL.revokeObjectURL($("img", n).src); n.remove(); });
      files.forEach((f, i) => {
        const d = document.createElement("div"); d.className = "ppa-ph";
        d.innerHTML = '<img alt=""><button type="button" aria-label="Remove photo"><i class="fa-solid fa-xmark"></i></button>' + (i === 0 ? '<span class="cov">Cover</span>' : "");
        $("img", d).src = URL.createObjectURL(f);
        $("button", d).addEventListener("click", () => { files.splice(i, 1); buzz(6); sync(); render(); });
        grid.insertBefore(d, add);
      });
      add.classList.toggle("full", files.length >= max);
    };
    inp.addEventListener("change", () => {
      const picked = [...inp.files];
      if (!canDT) { files = picked.slice(0, max); render(); return; }
      let skipped = 0;
      picked.forEach((f) => { if (!/^image\/(jpeg|png|webp)$/.test(f.type) || f.size > maxB) { skipped++; return; } if (files.length < max && !files.some((g) => g.name === f.name && g.size === f.size)) files.push(f); });
      if (skipped) alert(skipped + " photo(s) skipped — use JPG, PNG or WEBP under " + (maxB / 1048576) + " MB.");
      buzz(); sync(); render();
    });
  });

  /* screen transitions */
  $$("form.ppa-screen").forEach((f) => f.addEventListener("submit", (e) => {
    if (e.defaultPrevented) return;
    const b = e.submitter || $(".ppa-primary", f);
    if (!reduce) f.classList.add("out");
    setTimeout(() => { if (b) { b.disabled = true; b.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Please wait'; } }, 0);
  }));
  $$("a[data-back]").forEach((a) => a.addEventListener("click", (e) => {
    if (e.metaKey || e.ctrlKey) return;
    e.preventDefault(); buzz(6);
    try { sessionStorage.setItem("paBack", "1"); } catch (x) {}
    const s = $(".ppa-screen"); if (s && !reduce) s.classList.add("out-back");
    setTimeout(() => { location.href = a.href; }, reduce ? 0 : 170);
  }));
  window.addEventListener("pageshow", (e) => { if (e.persisted) $$(".ppa-screen").forEach((s) => s.classList.remove("out", "out-back")); });
})();
