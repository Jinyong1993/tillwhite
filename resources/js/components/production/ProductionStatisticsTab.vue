<template>
  <div class="statistics-page">
    <header class="tab-heading">
      <div>
        <h3>기간 통계</h3>
        <p>기간의 변화와 제품별 비중을 함께 비교해 운영 흐름을 확인합니다.</p>
      </div>
    </header>

    <div class="period-fields">
      <v-text-field v-model="from" type="date" label="시작일" variant="outlined" density="compact" hide-details />
      <v-text-field v-model="to" type="date" label="종료일" variant="outlined" density="compact" hide-details />
      <v-btn variant="flat" :loading="loading" @click="load">조회</v-btn>
    </div>

    <v-divider />

    <section class="statistics-section">
      <div class="section-copy">
        <h4>기간 요약</h4>
        <p>선택 기간에 기록된 전체 수량입니다.</p>
      </div>
      <div class="stat-cards">
        <div v-for="item in cards" :key="item.title">
          <span>{{ item.title }}</span>
          <strong>{{ item.value }}</strong>
        </div>
      </div>
    </section>

    <v-divider />

    <section class="statistics-section">
      <div class="section-title-row">
        <div class="section-copy">
          <h4>일별 흐름</h4>
          <p>날짜별 생산·이월·로스·폐기의 증감을 비교합니다. 막대가 길수록 해당 날짜의 수량이 많습니다.</p>
        </div>
        <span>{{ series.length }}일</span>
      </div>

      <div v-if="hasRecords" class="trend-panel">
        <div class="trend-legend">
          <button v-for="item in metricOptions" :key="item.key" type="button" :class="{ active: trendMetric === item.key }" @click="trendMetric=item.key">
            {{ item.label }}
          </button>
        </div>
        <div class="trend-chart" role="img" :aria-label="`${activeMetricLabel} 일별 흐름`">
          <div v-for="row in series" :key="row.date" class="trend-column" :title="`${shortDate(row.date)} · ${activeMetricLabel} ${row[trendMetric]}`">
            <div class="trend-bar-space"><span :style="{ height: `${barHeight(row[trendMetric])}%` }" /></div>
            <small>{{ shortDate(row.date) }}</small>
          </div>
        </div>
        <div class="trend-insight">
          <strong>{{ trendInsight.title }}</strong>
          <span>{{ trendInsight.text }}</span>
        </div>
      </div>

      <div v-if="series.length" class="statistics-table-wrap">
        <table class="statistics-table">
          <thead>
            <tr><th>날짜</th><th>생산</th><th>이월</th><th>로스</th><th>폐기</th><th>폐기율</th></tr>
          </thead>
          <tbody>
            <tr v-for="row in series" :key="row.date">
              <td>{{ shortDate(row.date) }}</td>
              <td>{{ row.production }}</td>
              <td>{{ row.carryover }}</td>
              <td>{{ row.loss }}</td>
              <td>{{ row.waste }}</td>
              <td>{{ row.waste_rate == null ? '-' : `${row.waste_rate}%` }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!hasRecords" class="statistics-empty">
        <v-icon icon="mdi-chart-line-variant" size="22" />
        <div><strong>선택한 기간에 기록이 없습니다.</strong><span>기간을 변경하거나 생산·폐기 기록이 쌓인 뒤 다시 확인해 주세요.</span></div>
      </div>
    </section>

    <v-divider />

    <section class="statistics-section">
      <div class="section-copy">
        <h4>제품별 비중</h4>
        <p>선택한 항목이 어떤 제품에 집중되어 있는지 비중과 순위로 확인합니다.</p>
      </div>
      <div class="donut-tabs">
        <button v-for="item in metricOptions" :key="item.key" type="button" :class="{ active: donutMetric === item.key }" @click="donutMetric=item.key">{{ item.label }}</button>
      </div>

      <div v-if="donutRows.length" class="donut-layout">
        <div class="donut" :style="{ background: donutBackground }">
          <div class="donut-center"><span>총 {{ activeDonutLabel }}</span><strong>{{ donutTotal }}</strong></div>
        </div>
        <div class="donut-ranking">
          <div v-for="(row,index) in donutRows" :key="row.product_id" class="donut-row">
            <span class="rank">{{ index + 1 }}</span>
            <span class="name">{{ row.product_name }}</span>
            <strong>{{ row.value }}</strong>
            <small>{{ row.share }}%</small>
          </div>
        </div>
      </div>
      <div v-else class="statistics-empty">
        <v-icon icon="mdi-chart-donut" size="22" />
        <div><strong>이 기간에는 {{ activeDonutLabel }} 기록이 없습니다.</strong><span>기록이 생기면 제품별 비중과 순위를 여기에서 확인할 수 있습니다.</span></div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { addLocalDays } from '../../utils/localDate';

const props = defineProps({ storeId: Number, workDate: String });
const emit = defineEmits(['error']);
const from = ref(addLocalDays(props.workDate, -9));
const to = ref(props.workDate);
const series = ref([]);
const totals = ref({});
const productTotals = ref([]);
const loading = ref(false);
const trendMetric = ref('production');
const donutMetric = ref('production');

const metricOptions = [
  { key: 'production', label: '생산' },
  { key: 'carryover', label: '이월' },
  { key: 'loss', label: '로스' },
  { key: 'waste', label: '폐기' },
];
const cards = computed(() => [
  { title: '생산', value: totals.value.production || 0 },
  { title: '이월', value: totals.value.carryover || 0 },
  { title: '로스', value: totals.value.loss || 0 },
  { title: '폐기', value: totals.value.waste || 0 },
  { title: '폐기율', value: totals.value.waste_rate == null ? '-' : `${totals.value.waste_rate}%` },
]);
const hasRecords = computed(() => series.value.some((row) => metricOptions.some((item) => Number(row[item.key] || 0) > 0)));
const activeMetricLabel = computed(() => metricOptions.find((item) => item.key === trendMetric.value)?.label || '기록');
const activeDonutLabel = computed(() => metricOptions.find((item) => item.key === donutMetric.value)?.label || '기록');
const maxTrendValue = computed(() => Math.max(0, ...series.value.map((row) => Number(row[trendMetric.value] || 0))));
const donutTotal = computed(() => productTotals.value.reduce((sum, row) => sum + Number(row[donutMetric.value] || 0), 0));
const donutRows = computed(() => productTotals.value
  .map((row) => ({
    ...row,
    value: Number(row[donutMetric.value] || 0),
    share: donutTotal.value ? (Number(row[donutMetric.value] || 0) / donutTotal.value * 100).toFixed(1) : '0.0',
  }))
  .filter((row) => row.value > 0)
  .sort((a, b) => b.value - a.value));
const donutBackground = computed(() => {
  if (!donutRows.value.length) return 'rgba(var(--v-theme-on-surface),.08)';
  let cursor = 0;
  const stops = donutRows.value.slice(0, 8).map((row, index) => {
    const start = cursor;
    cursor += Number(row.share);
    const hue = (215 + index * 37) % 360;
    return `hsl(${hue} 58% 56%) ${start}% ${Math.min(cursor, 100)}%`;
  });
  if (cursor < 100) stops.push(`rgba(var(--v-theme-on-surface),.12) ${cursor}% 100%`);
  return `conic-gradient(${stops.join(',')})`;
});
const trendInsight = computed(() => {
  if (!hasRecords.value || !maxTrendValue.value) return { title: '비교할 기록이 없습니다.', text: '다른 항목이나 기간을 선택해 주세요.' };
  const top = [...series.value].sort((a, b) => Number(b[trendMetric.value] || 0) - Number(a[trendMetric.value] || 0))[0];
  const recordedDays = series.value.filter((row) => Number(row[trendMetric.value] || 0) > 0).length;
  return {
    title: `${shortDate(top.date)}에 ${activeMetricLabel.value}이(가) 가장 많았습니다.`,
    text: `최대 ${top[trendMetric.value]}개 · 기록이 있는 날 ${recordedDays}일`,
  };
});

watch([() => props.storeId, () => props.workDate], () => {
  from.value = addLocalDays(props.workDate, -9);
  to.value = props.workDate;
  load();
}, { immediate: true });

/** 선택 기간 통계를 한 번의 API 호출로 조회해 표와 그래프가 같은 원본 데이터를 사용하게 합니다. */
async function load() {
  if (!props.storeId || loading.value) return;
  loading.value = true;
  try {
    const { data } = await window.axios.get('/tillwhite/api/production-management/statistics', {
      params: { store_id: props.storeId, from: from.value, to: to.value },
    });
    series.value = data.series || [];
    totals.value = data.totals || {};
    productTotals.value = data.product_totals || [];
  } catch (error) {
    emit('error', error.response?.data?.message || '통계를 불러오지 못했습니다.');
  } finally {
    loading.value = false;
  }
}

function barHeight(value) {
  if (!maxTrendValue.value) return 0;
  return Math.max(Number(value || 0) > 0 ? 8 : 0, Math.round(Number(value || 0) / maxTrendValue.value * 100));
}

function shortDate(date) {
  const [, month, day] = String(date).split('-');
  return `${Number(month)}/${Number(day)}`;
}
</script>

<style scoped>
.statistics-page { display:flex; flex-direction:column; }
.tab-heading { padding:2px 0 14px; }
.tab-heading h3,.statistics-section h4 { margin:0; font-size:.92rem; font-weight:650; }
.tab-heading p,.section-copy p { margin:4px 0 0; font-size:.7rem; line-height:1.45; color:rgba(var(--v-theme-on-surface),.56); }
.period-fields { display:grid; grid-template-columns:1fr 1fr auto; gap:8px; align-items:center; padding-bottom:16px; }
.statistics-section { padding:17px 0; }
.stat-cards { display:grid; grid-template-columns:repeat(5,1fr); gap:7px; margin-top:10px; }
.stat-cards>div { padding:10px 6px; border:1px solid rgba(var(--v-border-color),.58); border-radius:10px; text-align:center; }
.stat-cards span,.stat-cards strong { display:block; }
.stat-cards span { font-size:.64rem; color:rgba(var(--v-theme-on-surface),.56); }
.stat-cards strong { margin-top:3px; font-size:.98rem; font-weight:700; font-variant-numeric:tabular-nums; }
.section-title-row { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:10px; }
.section-title-row>span { flex:none; font-size:.67rem; color:rgba(var(--v-theme-on-surface),.55); }
.trend-panel { padding:12px; border-radius:12px; background:rgba(var(--v-theme-on-surface),.035); }
.trend-legend,.donut-tabs { display:flex; gap:4px; overflow-x:auto; margin-bottom:10px; }
.trend-legend button,.donut-tabs button { flex:none; min-height:30px; padding:5px 11px; border:0; border-radius:999px; background:rgba(var(--v-theme-on-surface),.06); color:inherit; font-size:.68rem; cursor:pointer; }
.trend-legend button.active,.donut-tabs button.active { background:rgba(var(--v-theme-primary),.12); color:rgb(var(--v-theme-primary)); font-weight:700; }
.trend-chart { height:126px; display:flex; align-items:stretch; gap:4px; overflow-x:auto; padding:4px 0 0; }
.trend-column { flex:1 0 24px; min-width:24px; display:flex; flex-direction:column; align-items:center; }
.trend-bar-space { width:100%; flex:1; display:flex; align-items:flex-end; justify-content:center; }
.trend-bar-space span { width:min(15px,72%); min-height:0; border-radius:4px 4px 2px 2px; background:rgba(var(--v-theme-primary),.68); }
.trend-column small { margin-top:5px; font-size:.57rem; color:rgba(var(--v-theme-on-surface),.48); white-space:nowrap; }
.trend-insight { display:flex; flex-direction:column; gap:2px; margin-top:10px; padding-top:10px; border-top:1px solid rgba(var(--v-border-color),.5); }
.trend-insight strong { font-size:.73rem; }
.trend-insight span { font-size:.65rem; color:rgba(var(--v-theme-on-surface),.56); }
.statistics-table-wrap { max-height:280px; overflow:auto; margin-top:12px; border:1px solid rgba(var(--v-border-color),.5); border-radius:10px; }
.statistics-table { width:100%; min-width:390px; border-collapse:collapse; table-layout:fixed; font-size:.67rem; }
.statistics-table th,.statistics-table td { height:31px; padding:4px 3px; border-bottom:1px solid rgba(var(--v-border-color),.45); text-align:center; font-variant-numeric:tabular-nums; white-space:nowrap; }
.statistics-table th { position:sticky; top:0; z-index:2; background:rgb(var(--v-theme-surface)); color:rgba(var(--v-theme-on-surface),.56); font-weight:600; }
.statistics-table th:first-child,.statistics-table td:first-child { position:sticky; left:0; z-index:3; width:52px; background:rgb(var(--v-theme-surface)); text-align:left; padding-left:8px; }
.statistics-table th:first-child { z-index:4; }
.donut-tabs { margin-top:12px; }
.donut-layout { display:grid; grid-template-columns:180px minmax(0,1fr); gap:18px; align-items:center; }
.donut { width:170px; aspect-ratio:1; border-radius:50%; display:grid; place-items:center; }
.donut-center { width:61%; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center; border-radius:50%; background:rgb(var(--v-theme-surface)); text-align:center; }
.donut-center span { font-size:.62rem; color:rgba(var(--v-theme-on-surface),.55); }
.donut-center strong { margin-top:2px; font-size:1.15rem; font-variant-numeric:tabular-nums; }
.donut-ranking { min-width:0; }
.donut-row { display:grid; grid-template-columns:22px minmax(0,1fr) auto 46px; gap:7px; align-items:center; min-height:31px; border-bottom:1px solid rgba(var(--v-border-color),.45); font-size:.68rem; }
.donut-row .rank { color:rgba(var(--v-theme-on-surface),.45); }
.donut-row .name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.donut-row strong,.donut-row small { text-align:right; font-variant-numeric:tabular-nums; }
.donut-row small { color:rgba(var(--v-theme-on-surface),.5); }
.statistics-empty { display:flex; gap:9px; align-items:flex-start; margin-top:10px; padding:13px; border-radius:10px; background:rgba(var(--v-theme-on-surface),.04); }
.statistics-empty strong,.statistics-empty span { display:block; }
.statistics-empty strong { font-size:.73rem; }
.statistics-empty span { margin-top:2px; font-size:.65rem; color:rgba(var(--v-theme-on-surface),.56); }
@media (max-width:600px) {
  .stat-cards { grid-template-columns:repeat(3,1fr); }
  .donut-layout { grid-template-columns:1fr; justify-items:center; }
  .donut-ranking { width:100%; }
  .statistics-table { min-width:360px; }
}
@media (max-width:430px) {
  .period-fields { grid-template-columns:1fr 1fr; }
  .period-fields :deep(.v-btn) { grid-column:1/-1; }
  .statistics-table-wrap { margin-inline:-4px; }
}
</style>
