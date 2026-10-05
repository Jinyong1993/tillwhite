<template>
  <div class="analysis-page">
    <header class="tab-heading">
      <div>
        <h3>분석</h3>
        <p>최근 28일의 흐름과 어떤 제품에서 생산·이월·로스·폐기가 많이 발생했는지 확인합니다.</p>
      </div>
      <v-btn size="small" variant="text" prepend-icon="mdi-refresh" :loading="loading" @click="load">새로고침</v-btn>
    </header>

    <section v-if="hasAnyData" class="analysis-section">
      <div class="section-title-row">
        <div><h4>일별 흐름</h4><p>날짜별 수량 변화를 한눈에 비교합니다.</p></div>
        <span>최근 28일</span>
      </div>
      <div class="flow-chart" aria-label="최근 28일 생산과 폐기 흐름">
        <div v-for="row in flowWithScale" :key="row.date" class="flow-column" :title="`${row.date} · 생산 ${row.production} · 폐기 ${row.waste}`">
          <div class="flow-bars"><i class="production-bar" :style="{ height: `${row.productionHeight}%` }"/><i class="waste-bar" :style="{ height: `${row.wasteHeight}%` }"/></div>
          <span>{{ shortDay(row.date) }}</span>
        </div>
      </div>
      <div class="chart-legend"><span><i class="production-dot"/>생산</span><span><i class="waste-dot"/>폐기</span></div>
    </section>

    <section class="analysis-section">
      <div class="section-title-row">
        <div><h4>제품별 비중</h4><p>항목을 선택하면 어떤 제품의 비중이 큰지 확인할 수 있습니다.</p></div>
      </div>
      <v-btn-toggle v-model="mixType" mandatory density="compact" variant="text" class="mix-tabs">
        <v-btn value="production">생산</v-btn><v-btn value="waste">폐기</v-btn><v-btn value="loss">로스</v-btn><v-btn value="carryover">이월</v-btn>
      </v-btn-toggle>

      <div v-if="mixTotal > 0" class="mix-layout">
        <div class="donut" :style="{ background: donutBackground }"><div><strong>{{ mixTotal }}</strong><span>총 {{ mixLabel }}</span></div></div>
        <div class="mix-ranking">
          <div v-for="(item,index) in mixRows" :key="item.product_id">
            <span>{{ index + 1 }}. {{ item.product_name }}</span><strong>{{ item[mixType] }}개 · {{ percent(item[mixType]) }}%</strong>
          </div>
        </div>
      </div>
      <div v-else class="empty-analysis"><v-icon icon="mdi-chart-donut" size="24"/><div><strong>이 기간에는 {{ mixLabel }} 기록이 없습니다.</strong><span>다른 항목을 선택하거나 기록이 쌓인 뒤 다시 확인해 주세요.</span></div></div>
    </section>

    <section class="analysis-section">
      <div class="section-title-row"><div><h4>확인할 제품</h4><p>반복되는 폐기·이월과 생산 참고 정보를 제품별로 정리합니다.</p></div><span>{{ flaggedCount }}개</span></div>
      <div v-if="sortedItems.length" class="analysis-list">
        <article v-for="item in sortedItems" :key="item.product_id" class="analysis-item">
          <div class="analysis-item-head"><div><strong>{{ item.product_name }}</strong><span>{{ item.flags.length ? `확인할 내용 ${item.flags.length}건` : '특이사항 없음' }}</span></div><span class="confidence-chip">{{ confidenceText(item.recommendation.confidence) }}</span></div>
          <div v-if="item.flags.length" class="flag-list"><div v-for="flag in item.flags" :key="flag">{{ flag }}</div></div>
          <div class="recommendation-row"><span>추천 생산량</span><strong>{{ recommendationText(item.recommendation) }}</strong></div>
        </article>
      </div>
      <div v-else class="empty-analysis"><v-icon icon="mdi-chart-box-outline" size="24"/><div><strong>아직 분석할 기록이 없습니다.</strong><span>생산 기록이 쌓이면 제품별 변화와 참고 정보를 확인할 수 있습니다.</span></div></div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  storeId: Number,
  workDate: String,
});

const emit = defineEmits(['error']);
const items = ref([]);
const dailyFlow = ref([]);
const productTotals = ref([]);
const mixType = ref('production');
const loading = ref(false);

/** 확인할 내용이 많은 제품을 먼저 보여줍니다. */
const sortedItems = computed(() => (
  [...items.value].sort((a, b) => b.flags.length - a.flags.length)
));

const flaggedCount = computed(() => items.value.filter((item) => item.flags.length).length);
const hasAnyData = computed(() => dailyFlow.value.some((row) => (
  Number(row.production || 0)
  + Number(row.carryover || 0)
  + Number(row.loss || 0)
  + Number(row.waste || 0)
) > 0));

const mixLabel = computed(() => ({
  production: '생산',
  waste: '폐기',
  loss: '로스',
  carryover: '이월',
}[mixType.value]));

const mixTotal = computed(() => productTotals.value.reduce(
  (sum, row) => sum + Number(row[mixType.value] || 0),
  0,
));

/** 도넛은 너무 많은 조각을 만들지 않고 상위 제품을 우선 표시합니다. */
const mixRows = computed(() => productTotals.value
  .filter((row) => Number(row[mixType.value] || 0) > 0)
  .sort((a, b) => b[mixType.value] - a[mixType.value])
  .slice(0, 6));

/** 생산과 폐기를 같은 높이 기준으로 환산해 날짜별 변화가 바로 비교되게 합니다. */
const flowWithScale = computed(() => {
  const max = Math.max(
    1,
    ...dailyFlow.value.flatMap((row) => [Number(row.production || 0), Number(row.waste || 0)]),
  );

  return dailyFlow.value.map((row) => ({
    ...row,
    productionHeight: Number(row.production || 0) / max * 100,
    wasteHeight: Number(row.waste || 0) / max * 100,
  }));
});

/** 제품 비중을 CSS 도넛으로 표시해 별도 차트 라이브러리 로딩 비용을 만들지 않습니다. */
const donutBackground = computed(() => {
  if (!mixRows.value.length) {
    return 'rgba(var(--v-theme-on-surface),.06)';
  }

  let position = 0;
  const parts = mixRows.value.map((row, index) => {
    const start = position;
    position += Number(row[mixType.value] || 0) / mixTotal.value * 100;
    const alpha = Math.max(0.18, 0.72 - index * 0.09);
    return `rgba(var(--v-theme-primary),${alpha}) ${start}% ${position}%`;
  });

  if (position < 100) {
    parts.push(`rgba(var(--v-theme-on-surface),.08) ${position}% 100%`);
  }

  return `conic-gradient(${parts.join(',')})`;
});

watch([() => props.storeId, () => props.workDate], load, { immediate: true });

/** 최근 흐름, 제품별 비중, 기존 추천 분석을 한 요청으로 받아 탭 전환 대기를 줄입니다. */
async function load() {
  if (!props.storeId || loading.value) return;

  loading.value = true;

  try {
    const { data } = await window.axios.get('/tillwhite/api/production-management/analysis', {
      params: {
        store_id: props.storeId,
        date: props.workDate,
      },
    });

    items.value = data.items || [];
    dailyFlow.value = data.daily_flow || [];
    productTotals.value = data.product_totals || [];
  } catch (error) {
    emit('error', error.response?.data?.message || '분석 데이터를 불러오지 못했습니다.');
  } finally {
    loading.value = false;
  }
}

function recommendationText(recommendation) {
  return recommendation?.min == null ? '데이터 부족' : `${recommendation.min}~${recommendation.max}`;
}

function confidenceText(value) {
  return {
    insufficient: '데이터 부족',
    low: '참고',
    normal: '보통',
    high: '높음',
  }[value] || '-';
}

function percent(value) {
  return mixTotal.value ? Math.round(Number(value || 0) / mixTotal.value * 100) : 0;
}

function shortDay(date) {
  const [, month, day] = String(date).split('-');
  return Number(day) === 1 ? `${Number(month)}/${Number(day)}` : Number(day);
}
</script>

<style scoped>
.analysis-page{display:flex;flex-direction:column}.tab-heading,.section-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.tab-heading{padding:2px 0 14px}.tab-heading h3,.section-title-row h4{margin:0;font-size:.92rem;font-weight:650}.tab-heading p,.section-title-row p{margin:3px 0 0;font-size:.68rem;line-height:1.45;color:rgba(var(--v-theme-on-surface),.56)}.section-title-row>span{font-size:.66rem;color:rgba(var(--v-theme-on-surface),.52)}.analysis-section{padding:16px 0;border-top:1px solid rgba(var(--v-border-color),.55)}.flow-chart{display:flex;align-items:flex-end;height:150px;margin-top:14px;padding:8px 4px 0;border-bottom:1px solid rgba(var(--v-border-color),.55)}.flow-column{flex:1;min-width:0;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center}.flow-bars{height:116px;width:72%;display:flex;align-items:flex-end;justify-content:center;gap:1px}.flow-bars i{width:42%;min-height:1px;border-radius:3px 3px 0 0}.production-bar{background:rgba(76,175,80,.62)}.waste-bar{background:rgba(239,83,80,.58)}.flow-column>span{height:18px;margin-top:4px;font-size:.55rem;color:rgba(var(--v-theme-on-surface),.48)}.chart-legend{display:flex;justify-content:flex-end;gap:12px;margin-top:7px;font-size:.64rem;color:rgba(var(--v-theme-on-surface),.58)}.chart-legend span{display:flex;align-items:center;gap:4px}.chart-legend i{width:7px;height:7px;border-radius:2px}.production-dot{background:rgba(76,175,80,.62)}.waste-dot{background:rgba(239,83,80,.58)}.mix-tabs{width:100%;display:grid;grid-template-columns:repeat(4,1fr);margin-top:10px;border-bottom:1px solid rgba(var(--v-border-color),.6);border-radius:0}.mix-tabs :deep(.v-btn){border-radius:0;font-size:.7rem}.mix-tabs :deep(.v-btn--active){border-bottom:2px solid rgb(var(--v-theme-primary))}.mix-layout{display:grid;grid-template-columns:150px 1fr;gap:20px;align-items:center;padding-top:16px}.donut{width:138px;height:138px;border-radius:50%;display:grid;place-items:center}.donut>div{width:82px;height:82px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgb(var(--v-theme-surface))}.donut strong{font-size:1.05rem}.donut span{font-size:.62rem;color:rgba(var(--v-theme-on-surface),.55)}.mix-ranking{display:flex;flex-direction:column;gap:7px}.mix-ranking div{display:flex;justify-content:space-between;gap:10px;padding-bottom:6px;border-bottom:1px solid rgba(var(--v-border-color),.42);font-size:.68rem}.mix-ranking span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.mix-ranking strong{flex:none;font-weight:600}.analysis-list{display:flex;flex-direction:column;gap:8px;margin-top:10px}.analysis-item{border:1px solid rgba(var(--v-border-color),.58);border-radius:10px;overflow:hidden}.analysis-item-head{display:flex;justify-content:space-between;gap:8px;padding:10px}.analysis-item-head strong,.analysis-item-head span{display:block}.analysis-item-head strong{font-size:.78rem}.analysis-item-head div span{margin-top:2px;font-size:.64rem;color:rgba(var(--v-theme-on-surface),.55)}.confidence-chip{font-size:.62rem;color:rgba(var(--v-theme-on-surface),.55)}.flag-list{padding:0 10px 8px}.flag-list div{padding:6px 8px;border-radius:7px;background:rgba(var(--v-theme-warning),.08);font-size:.66rem}.recommendation-row{display:flex;justify-content:space-between;padding:8px 10px;border-top:1px solid rgba(var(--v-border-color),.45);font-size:.68rem}.empty-analysis{display:flex;align-items:flex-start;gap:9px;margin-top:12px;padding:12px;border-radius:10px;background:rgba(var(--v-theme-on-surface),.04)}.empty-analysis strong,.empty-analysis span{display:block}.empty-analysis strong{font-size:.73rem}.empty-analysis span{margin-top:2px;font-size:.65rem;color:rgba(var(--v-theme-on-surface),.56)}
@media(max-width:520px){.mix-layout{grid-template-columns:112px 1fr;gap:12px}.donut{width:108px;height:108px}.donut>div{width:64px;height:64px}.flow-chart{height:132px}.flow-bars{height:100px}}
</style>
