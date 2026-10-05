<template>
  <div class="analysis-page">
    <header class="tab-heading">
      <div>
        <h3>분석</h3>
        <p>선택한 기간의 생산·이월·로스·폐기 흐름과 제품별 현황을 확인합니다.</p>
      </div>
    </header>

    <v-btn-toggle v-model="periodDays" mandatory density="compact" variant="outlined" class="period-tabs">
      <v-btn :value="7">최근 1주</v-btn>
      <v-btn :value="30">최근 1개월</v-btn>
      <v-btn :value="90">최근 3개월</v-btn>
      <v-btn :value="365">최근 1년</v-btn>
    </v-btn-toggle>

    <section class="analysis-section">
      <div class="section-title-row">
        <div><h4>기간 흐름</h4><p>{{ periodLabel }} 동안의 생산과 폐기 변화를 확인합니다.</p></div>
      </div>
      <div v-if="hasAnyData" class="flow-chart" :aria-label="`${periodLabel} 생산과 폐기 흐름`">
        <div v-for="row in sampledFlow" :key="row.date" class="flow-column">
          <div class="flow-bars"><i class="production-bar" :style="{height:`${row.productionHeight}%`}"/><i class="waste-bar" :style="{height:`${row.wasteHeight}%`}"/></div>
          <span>{{ shortDay(row.date) }}</span>
        </div>
      </div>
      <div v-else class="empty-analysis"><v-icon icon="mdi-chart-line" size="24"/><div><strong>선택한 기간에 기록이 없습니다.</strong><span>생산·이월·로스·폐기 기록이 등록되면 기간 흐름을 확인할 수 있습니다.</span></div></div>
      <div class="chart-legend"><span><i class="production-dot"/>생산</span><span><i class="waste-dot"/>폐기</span></div>
    </section>

    <section class="analysis-section">
      <div class="section-title-row"><div><h4>제품별 {{ mixLabel }} 비중</h4><p>선택한 항목이 어떤 제품에서 많이 발생했는지 비교합니다.</p></div></div>
      <v-btn-toggle v-model="mixType" mandatory density="compact" variant="text" class="mix-tabs">
        <v-btn value="production">생산</v-btn><v-btn value="waste">폐기</v-btn><v-btn value="loss">로스</v-btn><v-btn value="carryover">이월</v-btn>
      </v-btn-toggle>
      <div v-if="mixTotal > 0" class="mix-layout">
        <div class="donut" :style="{ background: donutBackground }"><div><strong>{{ mixTotal }}</strong><span>총 {{ mixLabel }}</span></div></div>
        <div class="mix-ranking">
          <div v-for="(item,index) in mixRows" :key="item.product_id">
            <i class="legend-dot" :style="{ background: mixColor(index) }" />
            <span>{{ item.product_name }}</span><strong>{{ item[mixType] }}개 · {{ percent(item[mixType]) }}%</strong>
          </div>
        </div>
      </div>
      <div v-else class="empty-analysis"><v-icon icon="mdi-chart-donut" size="24"/><div><strong>{{ mixLabel }} 기록이 없습니다.</strong><span>선택한 기간에 기록된 {{ mixLabel }} 수량이 없습니다.</span></div></div>
    </section>

    <section class="analysis-section">
      <div class="section-title-row"><div><h4>제품별 분석</h4><p>제품별 생산·이월·로스·폐기 현황과 확인할 내용을 비교합니다.</p></div><span>{{ filteredItems.length }}개</span></div>
      <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="제품명 검색" variant="outlined" density="compact" hide-details clearable class="analysis-search" />
      <div v-if="pagedItems.length" class="analysis-list">
        <article v-for="item in pagedItems" :key="item.product_id" class="analysis-item">
          <div class="analysis-item-head"><div><strong>{{ item.product_name }}</strong><span>{{ item.flags.length ? `확인할 내용 ${item.flags.length}건` : '특이사항 없음' }}</span></div><span class="confidence-chip">{{ confidenceText(item.recommendation.confidence) }}</span></div>
          <div class="product-metrics"><span>생산 <b>{{ totalsFor(item).production }}</b></span><span>이월 <b>{{ totalsFor(item).carryover }}</b></span><span>로스 <b>{{ totalsFor(item).loss }}</b></span><span>폐기 <b>{{ totalsFor(item).waste }}</b></span></div>
          <div v-if="item.flags.length" class="flag-list"><div v-for="flag in item.flags" :key="flag">{{ flag }}</div></div>
          <div class="recommendation-row"><span>추천 생산량</span><strong>{{ recommendationText(item.recommendation) }}</strong></div>
        </article>
      </div>
      <div v-else class="empty-analysis"><v-icon icon="mdi-magnify" size="24"/><div><strong>{{ search ? '검색 결과가 없습니다.' : '분석할 제품이 없습니다.' }}</strong><span>{{ search ? '다른 제품명으로 검색해 주세요.' : '기록이 쌓이면 제품별 현황을 확인할 수 있습니다.' }}</span></div></div>
      <div v-if="pageCount > 1" class="pagination-row"><v-btn size="small" variant="text" :disabled="page===1" @click="page--">이전</v-btn><span>{{ page }} / {{ pageCount }}</span><v-btn size="small" variant="text" :disabled="page===pageCount" @click="page++">다음</v-btn></div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
const props = defineProps({ storeId:Number, workDate:String });
const emit = defineEmits(['error']);
const items=ref([]), dailyFlow=ref([]), productTotals=ref([]), mixType=ref('production'), loading=ref(false), search=ref(''), page=ref(1), periodDays=ref(30);
const pageSize=10;
const periodLabel=computed(()=>({7:'최근 1주',30:'최근 1개월',90:'최근 3개월',365:'최근 1년'}[periodDays.value]));
const totalsMap=computed(()=>new Map(productTotals.value.map(row=>[row.product_id,row])));
const sortedItems=computed(()=>[...items.value].sort((a,b)=>b.flags.length-a.flags.length||a.product_name.localeCompare(b.product_name,'ko')));
const filteredItems=computed(()=>{const q=search.value.trim().toLocaleLowerCase('ko-KR'); return q?sortedItems.value.filter(i=>i.product_name.toLocaleLowerCase('ko-KR').includes(q)):sortedItems.value;});
const pageCount=computed(()=>Math.max(1,Math.ceil(filteredItems.value.length/pageSize)));
const pagedItems=computed(()=>filteredItems.value.slice((page.value-1)*pageSize,page.value*pageSize));
const hasAnyData=computed(()=>dailyFlow.value.some(row=>Number(row.production||0)+Number(row.carryover||0)+Number(row.loss||0)+Number(row.waste||0)>0));
const mixLabel=computed(()=>({production:'생산',waste:'폐기',loss:'로스',carryover:'이월'}[mixType.value]));
const mixTotal=computed(()=>productTotals.value.reduce((sum,row)=>sum+Number(row[mixType.value]||0),0));
const mixRows=computed(()=>productTotals.value.filter(row=>Number(row[mixType.value]||0)>0).sort((a,b)=>b[mixType.value]-a[mixType.value]).slice(0,7));
const flowWithScale=computed(()=>{const max=Math.max(1,...dailyFlow.value.flatMap(row=>[Number(row.production||0),Number(row.waste||0)]));return dailyFlow.value.map(row=>({...row,productionHeight:Number(row.production||0)/max*100,wasteHeight:Number(row.waste||0)/max*100}));});
const sampledFlow=computed(()=>{const step=periodDays.value<=30?1:periodDays.value<=90?7:30;return flowWithScale.value.filter((_,i)=>i%step===0||i===flowWithScale.value.length-1);});
const palette=['#5C6BC0','#26A69A','#7E57C2','#42A5F5','#AB47BC','#78909C','#66BB6A'];
const donutBackground=computed(()=>{if(!mixRows.value.length)return 'rgba(var(--v-theme-on-surface),.06)';let pos=0;const parts=mixRows.value.map((row,i)=>{const start=pos;pos+=Number(row[mixType.value]||0)/mixTotal.value*100;return `${mixColor(i)} ${start}% ${pos}%`;});if(pos<100)parts.push(`rgba(var(--v-theme-on-surface),.08) ${pos}% 100%`);return `conic-gradient(${parts.join(',')})`;});
watch([()=>props.storeId,()=>props.workDate,periodDays],load,{immediate:true});
watch(search,()=>{page.value=1;});
watch(periodDays,()=>{page.value=1;});
async function load(){if(!props.storeId||loading.value)return;loading.value=true;try{const {data}=await window.axios.get('/tillwhite/api/production-management/analysis',{params:{store_id:props.storeId,date:props.workDate,days:periodDays.value}});items.value=data.items||[];dailyFlow.value=data.daily_flow||[];productTotals.value=data.product_totals||[];}catch(error){emit('error',error.response?.data?.message||'분석 데이터를 불러오지 못했습니다.');}finally{loading.value=false;}}
function totalsFor(item){return totalsMap.value.get(item.product_id)||{production:0,carryover:0,loss:0,waste:0};}
function recommendationText(r){return r?.min==null?'데이터 부족':`${r.min}~${r.max}`;}
function confidenceText(v){return {insufficient:'데이터 부족',low:'참고',normal:'보통',high:'높음'}[v]||'-';}
function percent(v){return mixTotal.value?(Number(v||0)/mixTotal.value*100).toFixed(1):'0.0';}
function shortDay(date){const [,m,d]=String(date).split('-');return `${Number(m)}/${Number(d)}`;}
function mixColor(index){return palette[index%palette.length];}
</script>

<style scoped>
.analysis-page{display:flex;flex-direction:column}.tab-heading{padding:2px 0 10px}.tab-heading h3,.section-title-row h4{margin:0;font-size:.92rem;font-weight:650}.tab-heading p,.section-title-row p{margin:3px 0 0;font-size:.68rem;line-height:1.45;color:rgba(var(--v-theme-on-surface),.56)}.period-tabs{display:grid;grid-template-columns:repeat(4,1fr);width:100%;margin-bottom:6px}.period-tabs :deep(.v-btn){min-width:0;padding-inline:4px;font-size:.68rem}.section-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.section-title-row>span{font-size:.66rem;color:rgba(var(--v-theme-on-surface),.52)}.analysis-section{padding:16px 0;border-top:1px solid rgba(var(--v-border-color),.55)}.flow-chart{display:flex;align-items:flex-end;height:145px;margin-top:14px;padding:8px 4px 0;border-bottom:1px solid rgba(var(--v-border-color),.55);overflow:hidden}.flow-column{flex:1;min-width:5px;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center}.flow-bars{height:112px;width:90%;display:flex;align-items:flex-end;justify-content:center;gap:1px}.flow-bars i{width:42%;min-height:1px;border-radius:3px 3px 0 0}.production-bar{background:rgba(76,175,80,.62)}.waste-bar{background:rgba(239,83,80,.58)}.flow-column>span{height:18px;margin-top:4px;font-size:.53rem;color:rgba(var(--v-theme-on-surface),.48);white-space:nowrap}.chart-legend{display:flex;justify-content:flex-end;gap:12px;margin-top:7px;font-size:.64rem;color:rgba(var(--v-theme-on-surface),.58)}.chart-legend span{display:flex;align-items:center;gap:4px}.chart-legend i,.legend-dot{width:7px;height:7px;border-radius:50%;flex:none}.production-dot{background:rgba(76,175,80,.62)}.waste-dot{background:rgba(239,83,80,.58)}.mix-tabs{width:100%;display:grid;grid-template-columns:repeat(4,1fr);margin-top:10px;border-bottom:1px solid rgba(var(--v-border-color),.6);border-radius:0}.mix-tabs :deep(.v-btn){border-radius:0;font-size:.7rem}.mix-layout{display:grid;grid-template-columns:150px 1fr;gap:20px;align-items:center;padding-top:16px}.donut{width:138px;height:138px;border-radius:50%;display:grid;place-items:center}.donut>div{width:82px;height:82px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgb(var(--v-theme-surface))}.donut strong{font-size:1.05rem}.donut span{font-size:.62rem;color:rgba(var(--v-theme-on-surface),.55)}.mix-ranking{display:flex;flex-direction:column;gap:7px}.mix-ranking div{display:grid;grid-template-columns:10px minmax(0,1fr) auto;align-items:center;gap:7px;padding-bottom:6px;border-bottom:1px solid rgba(var(--v-border-color),.42);font-size:.68rem}.mix-ranking span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.analysis-search{margin-top:12px}.analysis-list{display:flex;flex-direction:column;gap:8px;margin-top:10px}.analysis-item{border:1px solid rgba(var(--v-border-color),.5);border-radius:10px;overflow:hidden}.analysis-item-head{display:flex;justify-content:space-between;gap:8px;padding:10px}.analysis-item-head strong,.analysis-item-head span{display:block}.analysis-item-head strong{font-size:.78rem}.analysis-item-head div span,.confidence-chip{margin-top:2px;font-size:.64rem;color:rgba(var(--v-theme-on-surface),.55)}.product-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;padding:0 10px 8px;font-size:.64rem;color:rgba(var(--v-theme-on-surface),.6)}.product-metrics span{text-align:center}.product-metrics b{display:block;margin-top:2px;color:rgb(var(--v-theme-on-surface));font-size:.73rem}.flag-list{padding:0 10px 8px}.flag-list div{padding:6px 8px;border-radius:7px;background:rgba(var(--v-theme-warning),.08);font-size:.66rem}.recommendation-row{display:flex;justify-content:space-between;padding:8px 10px;border-top:1px solid rgba(var(--v-border-color),.45);font-size:.68rem}.pagination-row{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:12px;font-size:.68rem}.empty-analysis{display:flex;align-items:flex-start;gap:9px;margin-top:12px;padding:12px;border-radius:10px;background:rgba(var(--v-theme-on-surface),.04)}.empty-analysis strong,.empty-analysis span{display:block}.empty-analysis strong{font-size:.73rem}.empty-analysis span{margin-top:2px;font-size:.65rem;color:rgba(var(--v-theme-on-surface),.56)}
@media(max-width:520px){.mix-layout{grid-template-columns:112px 1fr;gap:12px}.donut{width:108px;height:108px}.donut>div{width:64px;height:64px}.period-tabs :deep(.v-btn){font-size:.62rem}.product-metrics{padding-inline:6px}}
</style>
