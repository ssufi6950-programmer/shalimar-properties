(() => {
 'use strict';
 const data=window.PortalRecordCharts;if(!data||!window.ApexCharts)return;
 const monthly=document.querySelector('#portalRecordsMonthly'),sources=document.querySelector('#portalRecordsSources'),charts=[];
 const theme=()=>document.documentElement.dataset.theme==='dark'?'dark':'light';
 if(monthly){const chart=new ApexCharts(monthly,{chart:{type:'bar',height:300,background:'transparent',toolbar:{show:false}},theme:{mode:theme()},series:[{name:'Resale listings',data:data.monthly.map(row=>row.count)}],xaxis:{categories:data.monthly.map(row=>row.month)},colors:['#0c807b'],dataLabels:{enabled:false},yaxis:{min:0,forceNiceScale:true,decimalsInFloat:0},noData:{text:'No records found.'}});chart.render();charts.push(chart);}
 if(sources){const chart=new ApexCharts(sources,{chart:{type:'donut',height:300,background:'transparent'},theme:{mode:theme()},series:data.sources.map(row=>row.count),labels:data.sources.map(row=>row.name),colors:['#0c807b','#4676d9','#9b73d2','#d3a044'],legend:{position:'bottom'},noData:{text:'No records found.'}});chart.render();charts.push(chart);}
 const observer=new MutationObserver(()=>charts.forEach(chart=>chart.updateOptions({theme:{mode:theme()},chart:{background:'transparent'}})));
 observer.observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});window.addEventListener('pagehide',()=>observer.disconnect());
})();
