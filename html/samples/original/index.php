<?php
$active = 'home';
$pageTitle = 'Shalimar Properties — Home';
require __DIR__ . '/includes/header.php';
?>

<main>
<!-- HERO -->
<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="eyebrow"><b></b>2,410 new listings verified this week</span>
      <h1>
        <span class="ln"><i>Find the</i></span>
        <span class="ln"><i>address you</i></span>
        <span class="ln"><i>keep imagining.</i></span>
      </h1>
      <p class="lede">Every home on Shalimar Properties is visited, measured and photographed by our team before it goes live. What you see is what you walk into.</p>

      <div class="search">
        <div class="tabs" id="tabs">
          <span class="tab-pill" id="pill"></span>
          <button class="tab on">Buy</button>
          <button class="tab">Rent</button>
          <button class="tab">New projects</button>
        </div>
        <div class="fields">
          <div class="field">
            <label for="loc">Where</label>
            <input id="loc" placeholder="DHA Phase 6, Lahore">
          </div>
          <div class="field">
            <label for="typ">Property type</label>
            <select id="typ"><option>House</option><option>Apartment</option><option>Plot</option><option>Farmhouse</option><option>Office</option></select>
          </div>
          <div class="field">
            <label for="bud">Budget up to</label>
            <select id="bud"><option>2 crore</option><option>5 crore</option><option>10 crore</option><option>Any</option></select>
          </div>
          <button class="btn btn-solid" onclick="return false">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
            Search
          </button>
        </div>
      </div>
    </div>

    <div class="stage" id="stage">
      <div class="dots" id="dots"></div>
      <div class="stage-nav">
        <button class="sq" id="prev" aria-label="Previous property"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5 8 12l7 7"/></svg></button>
        <button class="sq" id="next" aria-label="Next property"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg></button>
      </div>
      <div id="slides"></div>
    </div>
  </div>

  <div class="strip">
    <div class="marq" id="marq"></div>
  </div>
</section>

<!-- CATEGORIES -->
<section id="areas">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <h2>Start with the kind of place you need</h2>
        <p>Filters do the sorting. These four cover about 80% of what people search for here.</p>
      </div>
    </div>
    <div class="cats" id="cats"></div>
  </div>
</section>

<!-- FEATURED RAIL -->
<section id="buy" style="padding-top:20px">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <h2>Handpicked this week</h2>
        <p>Drag the row or use the arrows. Prices are what the seller actually accepts — no listing inflation.</p>
      </div>
      <div class="arrows">
        <button class="rq" id="rprev" aria-label="Scroll left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5 8 12l7 7"/></svg></button>
        <button class="rq" id="rnext" aria-label="Scroll right"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg></button>
      </div>
    </div>
    <div class="rail" id="rail"></div>
  </div>
</section>

<!-- STATS -->
<section style="padding-top:0" id="rent">
  <div class="wrap">
    <div class="band rv">
      <div class="band-grid">
        <div><div class="stat-num" data-to="18400">0</div><p>Homes listed across 9 cities</p></div>
        <div><div class="stat-num" data-to="640">0</div><p>Verified agents on the platform</p></div>
        <div><div class="stat-num" data-to="97" data-suffix="%">0</div><p>Listings visited before publishing</p></div>
        <div><div class="stat-num" data-to="11" data-suffix=" days">0</div><p>Median time from viewing to keys</p></div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how" style="padding-top:20px">
  <div class="wrap split">
    <div class="rv">
      <h2 style="font-size:clamp(28px,3.6vw,46px);margin:0;max-width:15ch">Four steps between a shortlist and a signed file</h2>
      <ol class="steps">
        <li><div><h4>Shortlist without calling anyone</h4><p>Floor plans, room dimensions and a walkthrough video sit on every listing page.</p></div></li>
        <li><div><h4>Book a viewing slot</h4><p>Pick a time from the agent's live calendar. No back-and-forth on WhatsApp.</p></div></li>
        <li><div><h4>We check the paperwork</h4><p>Title, NOC and society dues are verified by our legal desk before you pay a rupee.</p></div></li>
        <li><div><h4>Transfer day, handled</h4><p>A Shalimar Properties coordinator comes with you to the society office and closes the file.</p></div></li>
      </ol>
    </div>
    <div class="visual rv" id="visual">
      <div class="float-card">
        <span class="ring"><i>76%</i></span>
        <div><strong>Legal desk clearance</strong><span>Average file cleared in 6 working days</span></div>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section id="agents">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <h2>What buyers told us afterwards</h2>
        <p>Collected six weeks after possession, not on the day of the sale.</p>
      </div>
      <div class="arrows">
        <button class="rq" id="qprev" aria-label="Previous quote"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5 8 12l7 7"/></svg></button>
        <button class="rq" id="qnext" aria-label="Next quote"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg></button>
      </div>
    </div>
    <div class="quotes rv">
      <div class="qtrack" id="qtrack"></div>
      <div class="qbar" id="qbar"></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section id="list" style="padding-top:0">
  <div class="wrap">
    <div class="cta-box rv">
      <div>
        <h2>Selling? We photograph it, price it and show it.</h2>
        <p>Send your address and a coordinator will visit within 48 hours. Listing is free until the property sells.</p>
      </div>
      <div>
        <div class="cta-form">
          <input type="email" placeholder="you@email.com" aria-label="Your email">
          <button class="btn" id="joinBtn">Get a visit</button>
        </div>
        <p class="note" id="ctaNote">No obligation. You can take the listing down any time.</p>
      </div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
/* ---------------- illustrated property "photos" ---------------- */
const P = {
  dusk:{sky1:"#2B4A5E",sky2:"#7B5B63",wall:"#EDE6DB",wall2:"#D6CCBD",roof:"#252628",glass:"#F5C878",ground:"#1D2A23",tree:"#124B34"},
  day:{sky1:"#BFD8D2",sky2:"#EEF2EA",wall:"#F6F3EC",wall2:"#DDD8CC",roof:"#252628",glass:"#166D47",ground:"#20302A",tree:"#166D47"},
  night:{sky1:"#131C22",sky2:"#24343A",wall:"#3A3C3F",wall2:"#2C2E30",roof:"#1A1B1C",glass:"#F2C46B",ground:"#14201A",tree:"#0E4B31"},
  green:{sky1:"#0E4B31",sky2:"#2FA06B",wall:"#F1F3F0",wall2:"#D3DBD4",roof:"#252628",glass:"#0E4B31",ground:"#16301F",tree:"#0B3B26"}
};
function win(x,y,w,h,c,op){return `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="2" fill="${c}" opacity="${op||1}"/>`}
function grid(x,y,cols,rows,gw,gh,gx,gy,c){let s="";for(let i=0;i<cols;i++)for(let j=0;j<rows;j++){s+=win(x+i*(gw+gx),y+j*(gh+gy),gw,gh,c,.35+Math.random()*.65)}return s}
function tree(x,y,s,c){return `<g transform="translate(${x} ${y}) scale(${s})"><rect x="-2" y="-14" width="4" height="16" fill="#3E2F22"/><path d="M0-56c14 6 20 18 20 28S12-6 0-6s-20-12-20-22 6-22 20-28z" fill="${c}"/></g>`}

function art(kind,pal){
  const p=P[pal]||P.day, id="g"+Math.random().toString(36).slice(2,7);
  let core="";
  if(kind==="villa"){
    core=`<rect x="90" y="250" width="420" height="180" fill="${p.wall}"/>
      <rect x="90" y="250" width="420" height="16" fill="${p.roof}"/>
      <rect x="150" y="170" width="300" height="86" fill="${p.wall2}"/>
      <rect x="150" y="160" width="300" height="14" fill="${p.roof}"/>
      ${grid(180,190,5,1,38,44,14,0,p.glass)}
      ${grid(120,290,4,1,52,70,38,0,p.glass)}
      <rect x="276" y="330" width="48" height="100" fill="${p.roof}" opacity=".85"/>
      <rect x="60" y="424" width="480" height="8" fill="${p.roof}" opacity=".5"/>`;
  }else if(kind==="tower"){
    core=`<rect x="150" y="90" width="150" height="342" fill="${p.wall}"/>
      <rect x="300" y="150" width="130" height="282" fill="${p.wall2}"/>
      ${grid(168,110,4,9,22,22,10,12,p.glass)}
      ${grid(316,172,3,8,24,24,14,12,p.glass)}
      <rect x="150" y="90" width="150" height="10" fill="${p.roof}"/>
      <rect x="300" y="150" width="130" height="10" fill="${p.roof}"/>
      <rect x="205" y="380" width="40" height="52" fill="${p.roof}" opacity=".85"/>`;
  }else if(kind==="townhouse"){
    core=`<g>${[0,1,2].map(i=>`<g transform="translate(${110+i*130} 0)">
      <path d="M0 240 60 180 120 240z" fill="${p.roof}"/>
      <rect x="8" y="238" width="104" height="194" fill="${i===1?p.wall2:p.wall}"/>
      ${grid(24,262,2,2,28,34,20,18,p.glass)}
      <rect x="44" y="372" width="32" height="60" fill="${p.roof}" opacity=".8"/>
      </g>`).join("")}</g>`;
  }else if(kind==="farm"){
    core=`<path d="M70 250 300 130 530 250z" fill="${p.roof}"/>
      <rect x="110" y="248" width="380" height="184" fill="${p.wall}"/>
      ${grid(150,280,3,1,60,62,52,0,p.glass)}
      <rect x="270" y="352" width="60" height="80" fill="${p.roof}" opacity=".85"/>
      <rect x="110" y="330" width="380" height="4" fill="${p.wall2}"/>
      ${tree(72,430,.9,p.tree)}${tree(534,430,.8,p.tree)}`;
  }else{
    core=`<rect x="120" y="140" width="360" height="292" fill="${p.wall}"/>
      <rect x="120" y="140" width="360" height="12" fill="${p.roof}"/>
      ${[0,1,2,3,4].map(r=>`<rect x="138" y="${172+r*50}" width="324" height="30" rx="3" fill="${p.glass}" opacity="${.3+r*.12}"/>`).join("")}
      <rect x="258" y="372" width="84" height="60" fill="${p.roof}" opacity=".85"/>`;
  }
  return `<svg viewBox="0 0 600 470" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Illustration of a ${kind}">
  <defs>
    <linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="${p.sky1}"/><stop offset="1" stop-color="${p.sky2}"/>
    </linearGradient>
  </defs>
  <rect width="600" height="470" fill="url(#${id})"/>
  <circle cx="470" cy="92" r="34" fill="#fff" opacity=".18"/>
  <path d="M0 400 Q150 372 300 396 T600 388V470H0z" fill="${p.ground}" opacity=".9"/>
  ${core}
  <path d="M0 432h600v38H0z" fill="${p.ground}"/>
  ${tree(40,438,.7,p.tree)}${tree(566,442,.62,p.tree)}
  <ellipse cx="300" cy="438" rx="230" ry="9" fill="#000" opacity=".18"/>
</svg>`;
}

/* ---------------- data ---------------- */
const IMG=u=>`https://images.unsplash.com/${u}?auto=format&fit=crop&w=1000&q=80`;
const heroSlides=[
  {tag:"Featured · DHA Phase 6",t:"Kanal corner house with a walled garden",city:"Lahore",beds:5,baths:6,area:"1 kanal",price:"PKR 9.4 cr",img:IMG("photo-1613977257363-707ba9348227")},
  {tag:"New listing · Clifton Block 4",t:"Sea-facing apartment on the 14th floor",city:"Karachi",beds:3,baths:3,area:"2,850 sq ft",price:"PKR 6.1 cr",img:IMG("photo-1545324418-cc1a3fa10c00")},
  {tag:"Ready to move · F-7",t:"Margalla-view residence with a studio wing",city:"Islamabad",beds:4,baths:5,area:"600 sq yd",price:"PKR 14.8 cr",img:IMG("photo-1600585154340-be6161a56a0c")},
  {tag:"Investor pick · Bahria Orchard",t:"Row of three finished townhouses",city:"Lahore",beds:3,baths:4,area:"5 marla each",price:"PKR 2.35 cr",img:IMG("photo-1570129477492-45c003edd2be")}
];
const listings=[
  {price:"PKR 4.25 cr",per:"10 marla",t:"Modern house with rooftop terrace",addr:"Askari 11, Lahore",b:4,ba:4,a:"2,250 sq ft",tag:"For sale",img:IMG("photo-1512917774080-9991f1c4c750")},
  {price:"PKR 185,000",per:"per month",t:"Furnished 2-bed with parking",addr:"Gulberg III, Lahore",b:2,ba:2,a:"1,400 sq ft",tag:"For rent",rent:1,img:IMG("photo-1502672260266-1c1ef2d93688")},
  {price:"PKR 11.9 cr",per:"2 kanal",t:"Farmhouse with orchard and guest annexe",addr:"Bedian Road, Lahore",b:6,ba:7,a:"4,800 sq ft",tag:"For sale",img:IMG("photo-1600607687939-ce8a6c25118c")},
  {price:"PKR 3.1 cr",per:"5 marla",t:"Newly built townhouse, corner unit",addr:"Bahria Town, Rawalpindi",b:3,ba:4,a:"1,650 sq ft",tag:"For sale",img:IMG("photo-1568605114967-8130f3a36994")},
  {price:"PKR 420,000",per:"per month",t:"Open-plan office floor, 40 desks",addr:"Blue Area, Islamabad",b:0,ba:3,a:"3,600 sq ft",tag:"For rent",rent:1,img:IMG("photo-1497366216548-37526070297c")},
  {price:"PKR 7.6 cr",per:"1 kanal",t:"Double-height living room, park facing",addr:"DHA Phase 8, Lahore",b:5,ba:5,a:"3,100 sq ft",tag:"For sale",img:IMG("photo-1600596542815-ffad4c1539a9")}
];
const cats=[
  {t:"Houses",s:"6,120 listings",d:"M3 10.5 12 3l9 7.5M5.5 9.7V21h13V9.7M10 21v-6h4v6"},
  {t:"Apartments",s:"3,480 listings",d:"M4 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M14 21V10h4a2 2 0 0 1 2 2v9M7 7h2M7 11h2M7 15h2M17 14h1M17 17h1"},
  {t:"Plots",s:"5,940 listings",d:"M3 7h18v10H3zM7 7v10M12 7v10M17 7v10"},
  {t:"Commercial",s:"1,210 listings",d:"M3 21h18M5 21V8l7-4 7 4v13M9 21v-5h6v5M9 11h.01M15 11h.01"}
];
const quotes=[
  {q:"The floor plan on the listing matched the house to the inch. After four months of surprises elsewhere, that alone sold me.",n:"Hina Raza",r:"Bought in DHA Phase 6, Lahore",av:"photo-1494790108377-be9c29b29330"},
  {q:"Our agent booked three viewings in one evening and none of them were the wrong budget. That never happens.",n:"Usman Tariq",r:"Rented in Gulberg, Lahore",av:"photo-1507003211169-0a1dd7228f2d"},
  {q:"The legal desk caught an unpaid society due the seller had forgotten about. It was settled before the transfer date.",n:"Farah Siddiqui",r:"Bought in F-7, Islamabad",av:"photo-1500648767791-00dcc994a43e"},
  {q:"I listed on a Tuesday, photos were up Thursday, and I had six serious viewings by the weekend.",n:"Bilal Ahmed",r:"Sold in Bahria Town, Rawalpindi",av:"photo-1472099645785-5658abf4ff4e"}
];

/* ---------------- search tabs ---------------- */
const tabs=document.getElementById("tabs"),pill=document.getElementById("pill");
function movePill(el){pill.style.width=el.offsetWidth+"px";pill.style.transform=`translateX(${el.offsetLeft}px)`}
tabs.querySelectorAll(".tab").forEach(t=>t.onclick=()=>{
  tabs.querySelector(".tab.on").classList.remove("on");t.classList.add("on");movePill(t)});
addEventListener("load",()=>movePill(tabs.querySelector(".tab.on")));
addEventListener("resize",()=>movePill(tabs.querySelector(".tab.on")));

/* ---------------- hero slider ---------------- */
const slidesEl=document.getElementById("slides"),dotsEl=document.getElementById("dots");
slidesEl.innerHTML=heroSlides.map((s,i)=>`
 <article class="slide${i===0?" on":""}">
   <div class="art"><img src="${s.img}" alt="${s.t}" loading="${i===0?"eager":"lazy"}" style="width:100%;height:100%;object-fit:cover;display:block"></div>
   <div class="slide-body">
     <span class="slide-tag">${s.tag}</span>
     <h3>${s.t}</h3>
     <div class="slide-meta"><span>${s.city}</span><span>${s.beds} bedrooms</span><span>${s.baths} baths</span><span>${s.area}</span></div>
     <div class="slide-price">${s.price} <span>· asking</span></div>
   </div>
 </article>`).join("");
dotsEl.innerHTML=heroSlides.map((_,i)=>`<button class="dot${i===0?" on":""}" aria-label="Property ${i+1}"><i></i></button>`).join("");
const slides=[...slidesEl.children],dots=[...dotsEl.children];
let cur=0,timer;
function go(n){
  cur=(n+slides.length)%slides.length;
  slides.forEach((s,i)=>s.classList.toggle("on",i===cur));
  dots.forEach((d,i)=>{d.classList.remove("on");if(i===cur){void d.offsetWidth;d.classList.add("on")}});
  restart();
}
function restart(){clearInterval(timer);timer=setInterval(()=>go(cur+1),6500)}
document.getElementById("next").onclick=()=>go(cur+1);
document.getElementById("prev").onclick=()=>go(cur-1);
dots.forEach((d,i)=>d.onclick=()=>go(i));
const stage=document.getElementById("stage");
stage.addEventListener("mouseenter",()=>clearInterval(timer));
stage.addEventListener("mouseleave",restart);
let tx=0;
stage.addEventListener("touchstart",e=>tx=e.touches[0].clientX,{passive:true});
stage.addEventListener("touchend",e=>{const d=e.changedTouches[0].clientX-tx;if(Math.abs(d)>45)go(cur+(d<0?1:-1))});
restart();

/* ---------------- marquee ---------------- */
const cities=["Lahore <em>· 6,120 homes</em>","Karachi <em>· 4,380 homes</em>","Islamabad <em>· 2,905 homes</em>","Rawalpindi <em>· 1,640 homes</em>","Faisalabad <em>· 980 homes</em>","Multan <em>· 760 homes</em>","Peshawar <em>· 610 homes</em>","Sialkot <em>· 415 homes</em>"];
document.getElementById("marq").innerHTML=[...cities,...cities].map(c=>`<span>${c}</span>`).join("");

/* ---------------- categories ---------------- */
document.getElementById("cats").innerHTML=cats.map((c,i)=>`
 <a href="#buy" class="cat rv" style="transition-delay:${i*70}ms">
   <span class="ico"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="${c.d}"/></svg></span>
   <h4>${c.t}</h4><span>${c.s}</span>
 </a>`).join("");

/* ---------------- listings rail ---------------- */
const rail=document.getElementById("rail");
const ic={bed:'M3 17v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5M3 17h18M3 17v2M21 17v2M7 10V7h6v3',
 bath:'M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM7 12V6a2 2 0 0 1 4 0M6 19l-1 2M18 19l1 2',
 area:'M3 9V3h6M21 15v6h-6M21 9V3h-6M3 15v6h6'};
rail.innerHTML=listings.map(l=>`
 <article class="card">
   <div class="card-art">
     <img src="${l.img}" alt="${l.t}" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
     <span class="chip${l.rent?" rent":""}">${l.tag}</span>
     <button class="fav" aria-label="Save this property"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20s-7-4.6-7-9.4A3.9 3.9 0 0 1 12 8a3.9 3.9 0 0 1 7 2.6C19 15.4 12 20 12 20z"/></svg></button>
   </div>
   <div class="card-body">
     <p class="price">${l.price} <b>${l.per}</b></p>
     <h4>${l.t}</h4>
     <p class="addr"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>${l.addr}</p>
     <div class="feats">
       ${l.b?`<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="${ic.bed}"/></svg>${l.b} bed</span>`:""}
       <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="${ic.bath}"/></svg>${l.ba} bath</span>
       <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="${ic.area}"/></svg>${l.a}</span>
     </div>
   </div>
 </article>`).join("");
rail.querySelectorAll(".fav").forEach(b=>b.onclick=e=>{e.preventDefault();b.classList.toggle("on");
  b.querySelector("svg").setAttribute("fill",b.classList.contains("on")?"currentColor":"none")});
function step(){return rail.querySelector(".card").offsetWidth+20}
document.getElementById("rnext").onclick=()=>rail.scrollBy({left:step(),behavior:"smooth"});
document.getElementById("rprev").onclick=()=>rail.scrollBy({left:-step(),behavior:"smooth"});
let down=false,sx=0,sl=0;
rail.addEventListener("pointerdown",e=>{down=true;sx=e.clientX;sl=rail.scrollLeft;rail.classList.add("drag")});
addEventListener("pointerup",()=>{down=false;rail.classList.remove("drag")});
rail.addEventListener("pointermove",e=>{if(!down)return;e.preventDefault();rail.scrollLeft=sl-(e.clientX-sx)});

/* ---------------- visual ---------------- */
document.getElementById("visual").insertAdjacentHTML("afterbegin",
 `<img src="${IMG("photo-1600210492486-724fe5c67fb0")}" alt="Living room interior" style="width:100%;height:100%;object-fit:cover;display:block">`);

/* ---------------- quotes ---------------- */
const qt=document.getElementById("qtrack"),qb=document.getElementById("qbar");
qt.innerHTML=quotes.map(q=>`
 <div class="quote">
   <blockquote>"${q.q}"</blockquote>
   <div class="who">
     <span class="av"><img src="${IMG(q.av)}" alt="${q.n}" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block"></span>
     <div><strong>${q.n}</strong><span>${q.r}</span></div>
   </div>
 </div>`).join("");
qb.innerHTML=quotes.map((_,i)=>`<button class="${i===0?"on":""}" aria-label="Quote ${i+1}"></button>`).join("");
let qi=0;
function qgo(n){qi=(n+quotes.length)%quotes.length;qt.style.transform=`translateX(-${qi*100}%)`;
  [...qb.children].forEach((b,i)=>b.classList.toggle("on",i===qi))}
document.getElementById("qnext").onclick=()=>qgo(qi+1);
document.getElementById("qprev").onclick=()=>qgo(qi-1);
[...qb.children].forEach((b,i)=>b.onclick=()=>qgo(i));
setInterval(()=>qgo(qi+1),8000);

/* ---------------- cta ---------------- */
document.getElementById("joinBtn").onclick=()=>{
  const inp=document.querySelector(".cta-form input"),note=document.getElementById("ctaNote");
  if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(inp.value)){note.textContent="Add a valid email so the coordinator can confirm the visit.";inp.focus();return}
  note.textContent="Booked. A coordinator will email you within 48 hours.";
  inp.value="";
};
</script>
</body>
</html>