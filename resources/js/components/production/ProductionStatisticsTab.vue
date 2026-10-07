<template>
  <div class="statistics-page">
    <header class="tab-heading"><h3>통계</h3><p>기간을 정해 생산·이월·로스·폐기 흐름과 제품별 결과를 비교합니다.</p></header>

    <div class="period-fields">
      <v-text-field
          v-model="from"
          type="date"
          label="시작일"
          variant="outlined"
          density="compact"
          hide-details
      />
      <v-text-field
          v-model="to"
          type="date"
          label="종료일"
          variant="outlined"
          density="compact"
          hide-details
      />
      <v-btn variant="flat" :loading="loading" :disabled="loading" @click="load">조회</v-btn>
    </div>

    <section class="statistics-section">
      <h4>요약</h4>
      <div class="stat-cards">
        <button v-for="card in cards" :key="card.key" type="button" @click="openMetric(card.key)"><span>{{ card.title }}</span><strong>{{ card.value }}</strong></button>
      </div>
    </section>

    <section class="statistics-section">
      <div class="section-title-row"><div><h4>흐름</h4><p>기간을 일간·주간·월간·년간 단위로 다시 묶어 비교합니다.</p></div></div>
      <v-btn-toggle v-model="flowUnit" mandatory density="compact" variant="text" class="flow-tabs"><v-btn value="day">일간</v-btn><v-btn value="week">주간</v-btn><v-btn value="month">월간</v-btn><v-btn value="year">년간</v-btn></v-btn-toggle>
      <div class="statistics-table-wrap">
        <table class="statistics-table"><thead><tr><th>기간</th><th>생산</th><th>이월</th><th>로스</th><th>폐기</th><th>폐기율</th></tr></thead>
          <tbody><tr v-for="row in groupedSeries" :key="row.label"><td>{{ row.label }}</td><td>{{ row.production }}</td><td>{{ row.carryover }}</td><td>{{ row.loss }}</td><td>{{ row.waste }}</td><td>{{ row.waste_rate == null ? '-' : `${row.waste_rate}%` }}</td></tr></tbody>
        </table>
      </div>
    </section>

    <section class="statistics-section">
      <div class="section-title-row"><div><h4>제품별 {{ activeDonutLabel }} 비중</h4><p>선택한 항목이 어떤 제품에서 많이 발생했는지 확인합니다.</p></div></div>
      <v-btn-toggle v-model="donutMetric" mandatory density="compact" variant="text" class="donut-tabs"><v-btn v-for="item in metricOptions" :key="item.key" :value="item.key">{{ item.label }}</v-btn></v-btn-toggle>
      <div v-if="donutRows.length" class="donut-layout">
        <div class="donut" :style="{background:donutBackground}"><div class="donut-center"><span>총 {{ activeDonutLabel }}</span><strong>{{ donutTotal }}</strong></div></div>
        <div class="donut-ranking"><div v-for="(row,index) in donutRows.slice(0,8)" :key="row.product_id" class="donut-row"><i :style="{background:mixColor(index)}"/><span>{{ row.product_name }}</span><strong>{{ row.value }}</strong><small>{{ row.share }}%</small></div></div>
      </div>
      <div v-else class="statistics-empty"><v-icon icon="mdi-chart-donut" size="22"/><div><strong>{{ activeDonutLabel }} 기록이 없습니다.</strong><span>선택한 기간에 기록된 {{ activeDonutLabel }} 수량이 없습니다.</span></div></div>
    </section>

    <v-dialog
        v-model="metricOpen"
        max-width="620"
        persistent
    >
      <v-card
          rounded="lg"
          class="app-dialog-card"
      >
        <v-card-title class="app-dialog-header">{{ activeMetricTitle }} 통계</v-card-title>
        <v-card-text class="app-dialog-body">
          <div class="metric-dialog-summary"><div><span>기간 합계</span><strong>{{ metricTotalText }}</strong></div><div><span>일평균</span><strong>{{ metricAverageText }}</strong></div><div><span>발생 제품</span><strong>{{ metricProducts.length }}개</strong></div></div>
          <div class="metric-insight"><strong>{{ metricInsight.title }}</strong><span>{{ metricInsight.text }}</span><span>{{ comparisonText }}</span></div>
          <h5>제품 순위</h5><div class="metric-ranking"><div v-for="(row,index) in metricProducts.slice(0,5)" :key="row.product_id"><span>{{ index+1 }}. {{ row.product_name }}</span><strong>{{ metricValue(row) }}</strong></div></div>
        </v-card-text>
        <v-card-actions class="app-dialog-footer"><v-btn variant="text" @click="metricOpen=false">닫기</v-btn><v-spacer/></v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import {
    computed,
    ref,
    watch,
} from 'vue';
import { addLocalDays } from '../../utils/localDate';
const props=defineProps({storeId:Number,workDate:String}); const emit=defineEmits(['error']);
const from=ref(addLocalDays(props.workDate,-29)),to=ref(props.workDate),series=ref([]),totals=ref({}),previousTotals=ref({}),productTotals=ref([]),loading=ref(false),donutMetric=ref('production'),flowUnit=ref('day'),metricOpen=ref(false),metricKey=ref('production');
const metricOptions=[{key:'production',label:'생산'},{key:'carryover',label:'이월'},{key:'loss',label:'로스'},{key:'waste',label:'폐기'}];
const cards=computed(()=>[{key:'production',title:'생산',value:totals.value.production||0},{key:'carryover',title:'이월',value:totals.value.carryover||0},{key:'loss',title:'로스',value:totals.value.loss||0},{key:'waste',title:'폐기',value:totals.value.waste||0},{key:'waste_rate',title:'폐기율',value:totals.value.waste_rate==null?'-':`${totals.value.waste_rate}%`}]);
const activeDonutLabel=computed(()=>metricOptions.find(i=>i.key===donutMetric.value)?.label||'기록');
const activeMetricTitle=computed(()=>cards.value.find(i=>i.key===metricKey.value)?.title||'기록');
const donutTotal=computed(()=>productTotals.value.reduce((sum,row)=>sum+Number(row[donutMetric.value]||0),0));
const donutRows=computed(()=>productTotals.value.map(row=>({...row,value:Number(row[donutMetric.value]||0),share:donutTotal.value?(Number(row[donutMetric.value]||0)/donutTotal.value*100).toFixed(1):'0.0'})).filter(row=>row.value>0).sort((a,b)=>b.value-a.value));
const palette=['#5C6BC0','#26A69A','#7E57C2','#42A5F5','#AB47BC','#78909C','#66BB6A','#FFA726'];
const donutBackground=computed(()=>{if(!donutRows.value.length)return 'rgba(var(--v-theme-on-surface),.08)';let pos=0;const parts=donutRows.value.slice(0,8).map((row,i)=>{const start=pos;pos+=Number(row.share);return `${mixColor(i)} ${start}% ${Math.min(pos,100)}%`;});if(pos<100)parts.push(`rgba(var(--v-theme-on-surface),.12) ${pos}% 100%`);return `conic-gradient(${parts.join(',')})`;});
const groupedSeries=computed(()=>groupSeries(series.value,flowUnit.value));
const metricProducts=computed(()=>{const key=metricKey.value==='waste_rate'?'waste':metricKey.value;return productTotals.value.filter(row=>Number(row[key]||0)>0).sort((a,b)=>Number(b[key]||0)-Number(a[key]||0));});
const metricTotalText=computed(()=>metricKey.value==='waste_rate'?(totals.value.waste_rate==null?'-':`${totals.value.waste_rate}%`):`${Number(totals.value[metricKey.value]||0)}개`);
const metricAverageText=computed(()=>{const days=Math.max(1,series.value.length);if(metricKey.value==='waste_rate')return '-';return `${(Number(totals.value[metricKey.value]||0)/days).toFixed(1)}개`;});
const metricInsight=computed(()=>{if(!metricProducts.value.length)return{title:`${activeMetricTitle.value} 기록이 없습니다.`,text:'선택한 기간에 해당 기록이 없습니다.'};const top=metricProducts.value[0];return{title:`${top.product_name}에서 가장 많이 발생했습니다.`,text:`기간 중 ${metricValue(top)}로 가장 높은 수치입니다.`};});
const comparisonText=computed(()=>{const current=totals.value[metricKey.value];const previous=previousTotals.value[metricKey.value];if(current==null||previous==null)return '이전 기간과 비교할 데이터가 없습니다.';if(metricKey.value==='waste_rate'){const diff=Number(current)-Number(previous);return `직전 동일 기간 대비 ${diff===0?'변화 없음':`${diff>0?'+':''}${diff.toFixed(1)}%p`}`;}if(Number(previous)===0)return Number(current)===0?'직전 동일 기간과 동일합니다.':'직전 동일 기간에는 기록이 없어 증감률을 계산하지 않습니다.';const diff=(Number(current)-Number(previous))/Number(previous)*100;return `직전 동일 기간 대비 ${diff>0?'+':''}${diff.toFixed(1)}%`;});
watch([()=>props.storeId,()=>props.workDate],()=>{from.value=addLocalDays(props.workDate,-29);to.value=props.workDate;load();},{immediate:true});
async function load(){if(!props.storeId||loading.value)return;if(from.value>to.value){emit('error','시작일은 종료일보다 늦을 수 없습니다.');return;}loading.value=true;try{const {data}=await window.axios.get('/tillwhite/api/production-management/statistics',{params:{store_id:props.storeId,from:from.value,to:to.value}});series.value=data.series||[];totals.value=data.totals||{};productTotals.value=data.product_totals||[];previousTotals.value=data.previous_totals||{};}catch(error){emit('error',error.response?.data?.message||'통계를 불러오지 못했습니다.');}finally{loading.value=false;}}
function openMetric(key) {
    metricKey.value = key;
    metricOpen.value = true;
}
function metricValue(row){if(metricKey.value==='waste_rate'){const p=Number(row.production||0);return p?`${(Number(row.waste||0)/p*100).toFixed(1)}%`:'-';}return `${Number(row[metricKey.value]||0)}개`;}
function mixColor(i) {
    return palette[i % palette.length];
}
function groupSeries(rows,unit){const groups=new Map();for(const row of rows){const d=new Date(`${row.date}T00:00:00`);let key,label;if(unit==='day'){key=row.date;label=`${d.getMonth()+1}/${d.getDate()}`;}else if(unit==='week'){const copy=new Date(d);const day=(copy.getDay()+6)%7;copy.setDate(copy.getDate()-day);key=copy.toLocaleDateString('sv-SE');label=`${copy.getMonth()+1}/${copy.getDate()} 주`;}else if(unit==='month'){key=row.date.slice(0,7);label=`${Number(key.slice(5))}월`;}else{key=row.date.slice(0,4);label=`${key}년`;}if(!groups.has(key))groups.set(key,{label,production:0,carryover:0,loss:0,waste:0});const g=groups.get(key);for(const k of ['production','carryover','loss','waste'])g[k]+=Number(row[k]||0);}return [...groups.values()].map(g=>({...g,waste_rate:g.production?Number((g.waste/g.production*100).toFixed(1)):null}));}
</script>

<style scoped>
.statistics-page{display:flex;flex-direction:column}.tab-heading{padding:2px 0 14px}.tab-heading h3,.statistics-section h4{margin:0;font-size:.92rem;font-weight:650}.tab-heading p,.section-title-row p{margin:4px 0 0;font-size:.7rem;line-height:1.45;color:rgba(var(--v-theme-on-surface),.56)}.period-fields{display:grid;grid-template-columns:1fr;gap:8px;padding-bottom:16px}.period-fields :deep(.v-btn){width:100%}.statistics-section{padding:17px 0;border-top:1px solid rgba(var(--v-border-color),.5)}.stat-cards{display:grid;grid-template-columns:repeat(5,1fr);gap:7px;margin-top:10px}.stat-cards button{border:1px solid rgba(var(--v-border-color),.5);border-radius:10px;background:transparent;padding:10px 5px;cursor:pointer}.stat-cards span,.stat-cards strong{display:block}.stat-cards span{font-size:.64rem;color:rgba(var(--v-theme-on-surface),.56)}.stat-cards strong{margin-top:3px;font-size:.98rem}.section-title-row{display:flex;justify-content:space-between}.flow-tabs,.donut-tabs{display:grid;grid-template-columns:repeat(4,1fr);width:100%;margin-top:10px}.flow-tabs :deep(.v-btn),.donut-tabs :deep(.v-btn){min-width:0;font-size:.68rem}.statistics-table-wrap{margin-top:10px;overflow:hidden;border:1px solid rgba(var(--v-border-color),.5);border-radius:10px}.statistics-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:.64rem}.statistics-table th,.statistics-table td{height:31px;padding:4px 2px;border-bottom:1px solid rgba(var(--v-border-color),.42);text-align:center;white-space:nowrap}.statistics-table th:first-child,.statistics-table td:first-child{width:22%;text-align:left;padding-left:7px}.donut-layout{display:grid;grid-template-columns:160px 1fr;gap:16px;align-items:center;margin-top:14px}.donut{width:150px;aspect-ratio:1;border-radius:50%;display:grid;place-items:center}.donut-center{width:62%;aspect-ratio:1;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgb(var(--v-theme-surface))}.donut-center span{font-size:.61rem;color:rgba(var(--v-theme-on-surface),.55)}.donut-row{display:grid;grid-template-columns:8px minmax(0,1fr) auto 42px;gap:7px;align-items:center;min-height:30px;border-bottom:1px solid rgba(var(--v-border-color),.4);font-size:.67rem}.donut-row i{width:7px;height:7px;border-radius:50%}.donut-row span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.donut-row small{text-align:right}.statistics-empty{display:flex;gap:9px;margin-top:12px;padding:12px;border-radius:10px;background:rgba(var(--v-theme-on-surface),.04)}.statistics-empty strong,.statistics-empty span{display:block;font-size:.7rem}.statistics-empty span{margin-top:2px;font-size:.64rem;color:rgba(var(--v-theme-on-surface),.56)}.metric-dialog-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}.metric-dialog-summary div{padding:10px;border-radius:9px;background:rgba(var(--v-theme-on-surface),.04);text-align:center}.metric-dialog-summary span,.metric-dialog-summary strong{display:block}.metric-dialog-summary span{font-size:.63rem;color:rgba(var(--v-theme-on-surface),.55)}.metric-dialog-summary strong{margin-top:3px;font-size:.86rem}.metric-insight{display:flex;flex-direction:column;gap:3px;margin:14px 0;padding:11px;border-radius:9px;background:rgba(var(--v-theme-on-surface),.04);font-size:.7rem}.metric-insight span{color:rgba(var(--v-theme-on-surface),.58)}.metric-ranking>div{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(var(--v-border-color),.4);font-size:.7rem}
@media(max-width:600px){.stat-cards{grid-template-columns:repeat(3,1fr)}.donut-layout{grid-template-columns:110px 1fr;gap:10px}.donut{width:104px}.statistics-table{font-size:.6rem}.statistics-table th,.statistics-table td{padding-inline:1px}.metric-dialog-summary{grid-template-columns:repeat(3,1fr)}}
</style>
