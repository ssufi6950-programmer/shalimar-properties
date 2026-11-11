<?php
/* Shalimar Properties — "launching soon" page (self-contained: this file + coming-soon/) */
declare(strict_types=1);
const CS_PHONE = 'UAN +92-48-111-222-084';
const CS_TEL = '+9248111222084';
const CS_EMAIL = 'info@shalimarco.com';
const CS_WHATSAPP = '923272222627';
const CS_ADDRESS = '2km Mall Road, Sargodha';

$state = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
        $state = 'bad';
    } else {
        $file = __DIR__ . '/coming-soon/notify-list.php';
        if (!is_file($file)) { @file_put_contents($file, "<?php exit; ?>\n"); }
        $ok = @file_put_contents($file, json_encode(['email' => $email, 'at' => date('c')]) . "\n", FILE_APPEND | LOCK_EX);
        $state = $ok === false ? 'saved-local' : 'ok';
    }
}
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$slides = ['villa', 'villa-twilight', 'villa-pool', 'villa-boulevard'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Shalimar Properties — Launching soon</title>
<meta name="description" content="The official property portal of Shalimar Group is under construction and launching soon.">
<meta name="theme-color" content="#0E4B31">
<link rel="icon" href="coming-soon/img/favicon.ico?v=7" sizes="any">
<link rel="icon" href="coming-soon/img/favicon.svg?v=7" type="image/svg+xml">
<link rel="icon" href="coming-soon/img/favicon-32.png?v=7" sizes="32x32" type="image/png">
<link rel="icon" href="coming-soon/img/favicon-16.png?v=7" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="coming-soon/img/apple-touch-icon.png?v=7">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--green:#166D47;--green-deep:#0E4B31;--green-bright:#2FA06B;--gold:#B08D57;--gold-2:#C9A86A;--gold-soft:#DCC598;--ink:#0D1511;--soft:#3D4A43;--muted:#6B766F;--ivory:#FBF8F2;--ease:cubic-bezier(.22,1,.36,1)}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;color:var(--ink);background:var(--ivory);-webkit-font-smoothing:antialiased;overflow-x:hidden}
a{color:inherit;text-decoration:none}
.bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(60% 70% at 85% 30%,rgba(201,168,106,.16),transparent 70%),radial-gradient(50% 60% at 0% 100%,rgba(22,109,71,.08),transparent 70%),linear-gradient(180deg,#FCFAF5,#F4EDE0)}
.bg::after{content:"";position:absolute;inset:0;opacity:.35;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56'%3E%3Cpath d='M28 4l24 24-24 24L4 28z' fill='none' stroke='%23C9A86A' stroke-opacity='.35'/%3E%3Ccircle cx='28' cy='28' r='3' fill='none' stroke='%23C9A86A' stroke-opacity='.35'/%3E%3C/svg%3E");-webkit-mask:linear-gradient(90deg,#000,transparent 60%);mask:linear-gradient(90deg,#000,transparent 60%)}
.page{min-height:100vh;min-height:100svh;display:flex;flex-direction:column;max-width:1240px;margin:0 auto;padding:28px 32px 22px}
header{display:flex;align-items:center;justify-content:space-between;gap:16px;animation:up 1s var(--ease) both}
.brand{display:flex;align-items:center;gap:12px}
.brand img{width:44px;height:44px}
.brand b{display:block;font-family:"Cormorant Garamond",serif;font-size:25px;font-weight:600;letter-spacing:.2em;line-height:1;color:var(--green-deep)}
.brand small{display:block;font-size:9.5px;font-weight:700;letter-spacing:.42em;color:var(--gold);margin-top:5px;padding-top:4px;border-top:1px solid rgba(201,168,106,.6)}
.hcall{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:99px;border:1px solid rgba(201,168,106,.55);background:rgba(255,255,255,.7);font-size:13px;font-weight:600;color:var(--green-deep);transition:background .3s}
.hcall:hover{background:#fff}
main{flex:1;display:grid;grid-template-columns:1.15fr .85fr;gap:48px;align-items:center;padding:30px 0}
.kick{display:inline-flex;align-items:center;gap:10px;font-size:11px;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:var(--gold)}
.kick i{position:relative;width:8px;height:8px;border-radius:50%;background:var(--green-bright)}
.kick i::after{content:"";position:absolute;inset:-3px;border-radius:50%;border:1px solid var(--green-bright);animation:ping 2s ease-out infinite}
h1{font-weight:500;font-size:clamp(34px,3.7vw,52px);line-height:1.06;letter-spacing:-.04em;margin:18px 0 18px}
h1 .ln{display:block;overflow:hidden;padding-bottom:.05em}
h1 .ln span{display:block;animation:lineUp 1.1s var(--ease) both}
h1 .ln:nth-child(2) span{animation-delay:.12s}
h1 em{font-style:normal;color:var(--green)}
.lede{max-width:520px;font-size:16px;line-height:1.7;color:var(--soft);animation:up 1s .3s var(--ease) both}
.prog{max-width:460px;margin:26px 0 24px;animation:up 1s .4s var(--ease) both}
.prog-t{display:flex;justify-content:space-between;font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);margin-bottom:9px}
.prog-t b{color:var(--green-deep)}
.bar{position:relative;height:6px;border-radius:9px;background:rgba(14,75,49,.1);overflow:hidden}
.bar i{position:absolute;inset:0;width:82%;border-radius:9px;background:linear-gradient(90deg,var(--green-deep),var(--green-bright));transform-origin:left;animation:grow 2s .6s var(--ease) both}
.bar i::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);animation:shine 2.4s 2.6s ease-in-out infinite;transform:translateX(-100%)}
form{display:flex;gap:8px;max-width:500px;padding:6px;border-radius:99px;background:#fff;border:1px solid rgba(201,168,106,.45);box-shadow:0 20px 40px -30px rgba(14,75,49,.55);animation:up 1s .5s var(--ease) both}
form input{flex:1;min-width:0;border:0;outline:0;background:none;padding:0 18px;font:inherit;font-size:14.5px;color:var(--ink)}
form button{flex:none;border:0;cursor:pointer;padding:13px 22px;border-radius:99px;background:var(--green);color:#fff;font:inherit;font-size:14px;font-weight:700;transition:background .3s,transform .3s var(--ease)}
form button:hover{background:var(--green-deep);transform:translateY(-1px)}
form:focus-within{border-color:var(--green)}
.msg{margin-top:10px;font-size:13px;font-weight:600}
.msg.ok{color:var(--green)}.msg.bad{color:#B3261E}
.note{margin-top:10px;font-size:12px;color:var(--muted)}
.comm{margin-top:28px;animation:up 1s .6s var(--ease) both}
.comm>span{display:block;font-size:10.5px;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:var(--muted);margin-bottom:12px}
.logos{display:flex;flex-wrap:wrap;gap:10px}
.logos span{display:flex;align-items:center;justify-content:center;height:52px;padding:0 18px;border-radius:14px;background:#fff;border:1px solid rgba(201,168,106,.3);box-shadow:0 10px 24px -22px rgba(14,75,49,.6);transition:transform .4s var(--ease),border-color .4s}
.logos span:hover{transform:translateY(-3px);border-color:var(--gold-2)}
.logos img{height:30px;width:auto;max-width:130px;object-fit:contain}
/* arch slider */
.vis{position:relative;justify-self:center;width:min(100%,460px);animation:fade 1.4s .2s ease both}
.arch{position:relative;aspect-ratio:4/5;border-radius:999px 999px 26px 26px;overflow:hidden;box-shadow:0 50px 80px -50px rgba(14,75,49,.7);background:#1a2a21}
.vis::before{content:"";position:absolute;inset:-14px;border-radius:999px 999px 34px 34px;border:1px solid var(--gold-2);opacity:.75}
.vis::after{content:"";position:absolute;left:28px;right:-28px;top:28px;bottom:-28px;z-index:-1;border-radius:999px 999px 30px 30px;border:1px solid rgba(201,168,106,.5);background:rgba(201,168,106,.08)}
.sl{position:absolute;inset:0;opacity:0;transition:opacity 1.6s ease}
.sl.on{opacity:1}
.sl img{width:100%;height:100%;object-fit:cover;transform:scale(1.04)}
.sl.on img{animation:kb 9s linear both}
.arch::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 55%,rgba(7,13,10,.7))}
.cap{position:absolute;left:0;right:0;bottom:22px;z-index:2;text-align:center;color:#fff}
.cap b{display:block;font-family:"Cormorant Garamond",serif;font-size:26px;font-weight:600;letter-spacing:.02em}
.cap small{display:block;font-size:10.5px;font-weight:700;letter-spacing:.26em;text-transform:uppercase;color:var(--gold-soft);margin-top:4px}
.seal{position:absolute;right:-30px;top:16%;z-index:3;width:116px;height:116px;border-radius:50%;background:var(--green-deep);display:grid;place-items:center;box-shadow:0 20px 40px -20px rgba(14,75,49,.8)}
.seal svg{position:absolute;inset:0;animation:spin 26s linear infinite}
.seal img{width:42px;height:42px}
.dots{display:flex;justify-content:center;gap:8px;margin-top:40px}
.dots i{width:22px;height:2px;border-radius:2px;background:rgba(14,75,49,.18);transition:background .5s,width .5s}
.dots i.on{width:40px;background:var(--gold)}
footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px 24px;padding-top:18px;border-top:1px solid rgba(201,168,106,.35);font-size:12.5px;color:var(--muted);animation:up 1s .7s var(--ease) both}
.ct{display:flex;flex-wrap:wrap;gap:8px 22px}
.ct a,.ct span{display:inline-flex;align-items:center;gap:7px}
.ct a{color:var(--green-deep);font-weight:600}
.ct a:hover{color:var(--green)}
.ct svg{width:15px;height:15px;flex:none;fill:currentColor}
@keyframes up{from{opacity:0;transform:translateY(18px)}}
@keyframes fade{from{opacity:0}}
@keyframes lineUp{from{transform:translateY(105%)}}
@keyframes grow{from{transform:scaleX(0)}}
@keyframes shine{to{transform:translateX(100%)}}
@keyframes ping{from{transform:scale(.6);opacity:1}to{transform:scale(1.8);opacity:0}}
@keyframes kb{from{transform:scale(1.04)}to{transform:scale(1.14)}}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:900px){
  .page{padding:16px 18px 18px}
  main{grid-template-columns:1fr;gap:26px;padding:18px 0 26px}
  .vis{order:-1;width:min(76%,300px);margin-top:10px}
  .seal{width:84px;height:84px;right:-22px}.seal img{width:30px;height:30px}
  .cap b{font-size:20px}.dots{margin-top:30px}
  h1{font-size:clamp(30px,8.4vw,40px);margin:14px 0}
  .lede{font-size:14.5px}
  .hcall span{display:none}.hcall{padding:10px 12px}
  .brand b{font-size:21px}.brand img{width:38px;height:38px}
  form button{padding:12px 16px}
  .logos span{height:46px;padding:0 12px;flex:1}.logos img{height:24px;max-width:100%}
  footer{flex-direction:column;align-items:flex-start}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
.brand img.logo{width:auto;height:52px}
@media(max-width:900px){.brand img.logo{height:40px}}
</style>
</head>
<body>
<div class="bg" aria-hidden="true"></div>
<div class="page">
  <header>
    <a class="brand" href="coming-soon.php" aria-label="Shalimar Properties"><img class="logo" src="coming-soon/img/sp-logo.svg?v=6" alt="Shalimar Properties" width="214" height="52"></a>
    <a class="hcall" href="tel:<?= CS_TEL ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg><span><?= $h(CS_PHONE) ?></span></a>
  </header>

  <main>
    <section>
      <span class="kick"><i></i> Under construction · Launching soon</span>
      <h1><span class="ln"><span>We’re building something</span></span><span class="ln"><span>worth the <em>wait.</em></span></span></h1>
      <p class="lede">The official property portal of Shalimar Group is getting its final touches — verified listings, authorized realtors and transparent market prices for every Shalimar community, all in one place.</p>
      <div class="prog" aria-label="Launch progress"><div class="prog-t"><span>Website progress</span><b>Almost there</b></div><div class="bar"><i></i></div></div>
      <form method="post" action="coming-soon.php#notify" id="notify">
        <label for="em" style="position:absolute;left:-9999px">Email address</label>
        <input id="em" type="email" name="email" required maxlength="160" placeholder="Enter your email for launch updates" autocomplete="email">
        <button type="submit">Notify me</button>
      </form>
      <?php if ($state === 'ok' || $state === 'saved-local'): ?><p class="msg ok" role="status">Thank you — we’ll let you know the moment we go live.</p>
      <?php elseif ($state === 'bad'): ?><p class="msg bad" role="alert">Please enter a valid email address.</p>
      <?php else: ?><p class="note">No spam — one email when we launch.</p><?php endif; ?>
      <div class="comm">
        <span>The Shalimar Group communities</span>
        <div class="logos">
          <span><img src="coming-soon/img/ssc-logo.svg" alt="Shalimar Smart City"></span>
          <span><img src="coming-soon/img/gulberg-city-logo.svg" alt="Gulberg City"></span>
          <span><img src="coming-soon/img/zalmi-x.png" alt="Zalmi X"></span>
        </div>
      </div>
    </section>

    <div class="vis" aria-hidden="true">
      <div class="arch">
        <?php foreach ($slides as $k => $s): ?><div class="sl<?= $k ? '' : ' on' ?>"><img src="coming-soon/img/<?= $s ?>-md.webp" alt="" <?= $k ? 'loading="lazy"' : '' ?>></div><?php endforeach; ?>
        <div class="cap"><b>Shalimar Properties</b><small>Coming soon</small></div>
      </div>
      <div class="seal"><svg viewBox="0 0 116 116"><defs><path id="cp" d="M58 58m-44 0a44 44 0 1 1 88 0a44 44 0 1 1-88 0"/></defs><text font-family="Plus Jakarta Sans,sans-serif" font-size="9.4" font-weight="700" fill="#DCC598"><textPath href="#cp" textLength="272" lengthAdjust="spacing">OFFICIAL PORTAL · SHALIMAR GROUP · </textPath></text></svg><img src="coming-soon/img/sp-mark-white.svg?v=5" alt=""></div>
      <div class="dots"><?php foreach ($slides as $k => $s): ?><i class="<?= $k ? '' : 'on' ?>"></i><?php endforeach; ?></div>
    </div>
  </main>

  <footer>
    <div class="ct">
      <a href="tel:<?= CS_TEL ?>"><svg viewBox="0 0 24 24"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg><?= $h(CS_PHONE) ?></a>
      <a href="tel:+92483722644">048-3722644</a>
      <a href="tel:+92483723326">048-3723326</a>
      <a href="mailto:<?= CS_EMAIL ?>"><svg viewBox="0 0 24 24"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 7.2L3.8 7v10h16.4V7L12 12.2zM5 7l7 4.4L19 7H5z"/></svg><?= $h(CS_EMAIL) ?></a>
      <a href="https://wa.me/<?= CS_WHATSAPP ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2s.8 2.3.9 2.5c.1.2 1.6 2.5 4 3.5 1.5.6 2 .7 2.8.6.4-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3z"/></svg>WhatsApp us</a>
      <span><svg viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>Head Office: <?= $h(CS_ADDRESS) ?></span>
    </div>
    <span>© <?= date('Y') ?> Shalimar Properties · A Shalimar Group company</span>
  </footer>
</div>
<script>
(function () {
  if (matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  const s = document.querySelectorAll(".sl"), d = document.querySelectorAll(".dots i"); let i = 0;
  setInterval(() => { s[i].classList.remove("on"); d[i].classList.remove("on"); i = (i + 1) % s.length; s[i].classList.add("on"); d[i].classList.add("on"); }, 6000);
})();
</script>
</body>
</html>
