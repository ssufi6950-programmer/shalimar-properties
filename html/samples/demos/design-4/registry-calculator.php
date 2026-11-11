<?php
$active = 'registry';
$pageTitle = 'Registry Calculator — Shalimar Properties';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Registry Calculator</span></div>
    <h1>Estimate stamp duty &amp; registry cost</h1>
    <p class="lede">Indicative figures for planning only. Final amounts depend on DC valuation, province rules and the registrar’s assessment on the day.</p>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap calc-layout">
    <div class="panel rv">
      <h3>Property details</h3>
      <p class="muted" style="margin:0 0 14px;font-size:13.5px">Fill what you know — the estimate updates as you type.</p>
      <form class="calc-form" id="regForm" onsubmit="return false">
        <div class="field">
          <label for="province">Province</label>
          <select id="province">
            <option value="punjab">Punjab</option>
            <option value="sindh">Sindh</option>
            <option value="ict">Islamabad Capital Territory</option>
            <option value="kpk">Khyber Pakhtunkhwa</option>
          </select>
        </div>
        <div class="field">
          <label for="city">City / district</label>
          <select id="city">
            <option>Sargodha</option>
            <option>Lahore</option>
            <option>Islamabad</option>
            <option>Rawalpindi</option>
            <option>Faisalabad</option>
            <option>Karachi</option>
          </select>
        </div>
        <div class="form-grid" style="margin-bottom:12px">
          <div class="field">
            <label for="ptype">Property type</label>
            <select id="ptype">
              <option value="residential">Residential</option>
              <option value="commercial">Commercial</option>
              <option value="plot">Plot / land</option>
            </select>
          </div>
          <div class="field">
            <label for="buyer">Buyer type</label>
            <select id="buyer">
              <option value="individual">Individual</option>
              <option value="company">Company</option>
              <option value="first">First-time (if eligible)</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="value">Declared / DC value (PKR)</label>
          <input id="value" type="number" min="0" step="100000" value="42500000" placeholder="e.g. 42500000">
        </div>
        <div class="field">
          <label for="area">Covered / plot area (optional)</label>
          <input id="area" placeholder="e.g. 10 marla or 2250 sq ft">
        </div>
        <button class="btn btn-solid" type="button" id="calcBtn" style="width:100%;margin-top:4px">
          <i class="fa-solid fa-calculator"></i> Calculate estimate
        </button>
      </form>
    </div>

    <div class="calc-result rv" id="resultBox">
      <h3>Estimated total</h3>
      <p style="margin:0;color:rgba(255,255,255,.75);font-size:13.5px">Stamp duty + registration (indicative)</p>
      <div class="big" id="totalOut">PKR 0</div>
      <div class="rows">
        <div><span>Stamp duty</span><strong id="stampOut">—</strong></div>
        <div><span>Registration fee</span><strong id="regOut">—</strong></div>
        <div><span>CVT / transfer (est.)</span><strong id="cvtOut">—</strong></div>
        <div><span>Effective rate</span><strong id="rateOut">—</strong></div>
      </div>
      <p style="margin:14px 0 0;font-size:12.5px;color:rgba(255,255,255,.65)">Not legal advice. Confirm with your registrar or our legal desk before paying token.</p>
      <a href="blog.php" class="btn btn-light" style="margin-top:14px;position:relative;z-index:1">Read buyer guides</a>
    </div>
  </div>
</section>

<section class="sec-tight">
  <div class="wrap soft-band rv two-col">
    <div>
      <h2 style="margin:0;font-size:clamp(22px,2.6vw,30px)">Transferring a Shalimar plot?</h2>
      <p class="muted" style="margin:8px 0 0;max-width:44ch">Our office guides you through transfer, NDC and registry for all Shalimar projects. Check current prices before you negotiate.</p>
    </div>
    <div style="display:flex;align-items:center">
      <a href="insights.php" class="btn btn-solid"><i class="fa-solid fa-chart-line"></i> Market prices</a>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
(function(){
  const rates = {
    punjab: { stamp: 0.05, reg: 0.01, cvt: 0.02 },
    sindh: { stamp: 0.03, reg: 0.01, cvt: 0.025 },
    ict: { stamp: 0.04, reg: 0.01, cvt: 0.02 },
    kpk: { stamp: 0.02, reg: 0.01, cvt: 0.02 }
  };
  function fmt(n){
    if (n >= 1e7) return 'PKR ' + (n/1e7).toFixed(2) + ' cr';
    if (n >= 1e5) return 'PKR ' + (n/1e5).toFixed(2) + ' lac';
    return 'PKR ' + Math.round(n).toLocaleString('en-PK');
  }
  function calc(){
    const prov = document.getElementById('province').value;
    let val = +document.getElementById('value').value || 0;
    const ptype = document.getElementById('ptype').value;
    const buyer = document.getElementById('buyer').value;
    const r = { ...rates[prov] };
    if (ptype === 'commercial') { r.stamp += 0.01; r.cvt += 0.005; }
    if (buyer === 'company') r.stamp += 0.005;
    if (buyer === 'first') r.stamp = Math.max(0.01, r.stamp - 0.01);
    const stamp = val * r.stamp;
    const reg = val * r.reg;
    const cvt = val * r.cvt;
    const total = stamp + reg + cvt;
    document.getElementById('totalOut').textContent = fmt(total);
    document.getElementById('stampOut').textContent = fmt(stamp);
    document.getElementById('regOut').textContent = fmt(reg);
    document.getElementById('cvtOut').textContent = fmt(cvt);
    document.getElementById('rateOut').textContent = ((total / (val || 1)) * 100).toFixed(2) + '% of value';
  }
  document.getElementById('calcBtn').onclick = calc;
  ['province','ptype','buyer','value'].forEach(id => {
    document.getElementById(id).addEventListener('change', calc);
    document.getElementById(id).addEventListener('input', calc);
  });
  calc();
})();
</script>
</body>
</html>
