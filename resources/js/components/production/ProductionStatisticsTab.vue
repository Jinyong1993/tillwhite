<template>
  <div class="statistics-page">
    <header class="tab-heading">
      <div>
        <h3>기간 통계</h3>
        <p>선택한 기간의 생산 흐름을 합계와 일별 기록으로 확인합니다.</p>
      </div>
    </header>

    <div class="period-fields">
      <v-text-field v-model="from" type="date" label="시작일" variant="outlined" density="compact" hide-details />
      <v-text-field v-model="to" type="date" label="종료일" variant="outlined" density="compact" hide-details />
      <v-btn variant="flat" :loading="loading" @click="load">조회</v-btn>
    </div>

    <v-divider />

    <section class="statistics-section">
      <h4>기간 요약</h4>
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
        <h4>일별 흐름</h4>
        <span>{{ series.length }}일</span>
      </div>
      <div v-if="series.length" class="statistics-table-wrap">
        <table class="statistics-table">
          <thead>
            <tr>
              <th>날짜</th><th>생산</th><th>이월</th><th>로스</th><th>폐기</th><th>폐기율</th>
            </tr>
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
      <div v-else class="statistics-empty"><v-icon icon="mdi-chart-line-variant" size="22"/><div><strong>선택한 기간에 기록이 없습니다.</strong><span>기간을 변경하거나 생산·폐기 기록이 쌓인 뒤 다시 확인해 주세요.</span></div></div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { addLocalDays } from '../../utils/localDate';

const props = defineProps({
  storeId: Number,
  workDate: String,
});

const emit = defineEmits(['error']);
const from = ref(addLocalDays(props.workDate, -9));
const to = ref(props.workDate);
const series = ref([]);
const totals = ref({});
const loading = ref(false);

const cards = computed(() => [
  { title: '생산', value: totals.value.production || 0 },
  { title: '이월', value: totals.value.carryover || 0 },
  { title: '로스', value: totals.value.loss || 0 },
  { title: '폐기', value: totals.value.waste || 0 },
  { title: '폐기율', value: totals.value.waste_rate == null ? '-' : `${totals.value.waste_rate}%` },
]);

watch([() => props.storeId, () => props.workDate], () => {
  from.value = addLocalDays(props.workDate, -9);
  to.value = props.workDate;
  load();
}, { immediate: true });

/** 선택 기간의 객관적인 일별 통계를 서버에서 조회합니다. */
async function load() {
  if (!props.storeId || loading.value) return;
  loading.value = true;

  try {
    const { data } = await window.axios.get('/tillwhite/api/production-management/statistics', {
      params: {
        store_id: props.storeId,
        from: from.value,
        to: to.value,
      },
    });
    series.value = data.series || [];
    totals.value = data.totals || {};
  } catch (error) {
    emit('error', error.response?.data?.message || '통계를 불러오지 못했습니다.');
  } finally {
    loading.value = false;
  }
}

/** 모바일 표에서 연도를 반복하지 않도록 월/일만 간결하게 표시합니다. */
function shortDate(date) {
  const [, month, day] = String(date).split('-');
  return `${Number(month)}/${Number(day)}`;
}
</script>

<style scoped>
.statistics-page { display:flex; flex-direction:column; }
.tab-heading { padding:2px 0 14px; }
.tab-heading h3,.statistics-section h4 { margin:0; font-size:.92rem; font-weight:650; }
.tab-heading p { margin:4px 0 0; font-size:.72rem; color:rgba(var(--v-theme-on-surface),.56); }
.period-fields { display:grid; grid-template-columns:1fr 1fr auto; gap:8px; align-items:center; padding-bottom:16px; }
.statistics-section { padding:16px 0; }
.stat-cards { display:grid; grid-template-columns:repeat(5,1fr); gap:7px; margin-top:10px; }
.stat-cards>div { padding:10px 6px; border:1px solid rgba(var(--v-border-color),.62); border-radius:10px; text-align:center; box-shadow:0 2px 7px rgba(0,0,0,.04); }
.stat-cards span,.stat-cards strong { display:block; }
.stat-cards span { font-size:.65rem; color:rgba(var(--v-theme-on-surface),.56); }
.stat-cards strong { margin-top:3px; font-size:.98rem; font-weight:700; font-variant-numeric:tabular-nums; }
.section-title-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:9px; }
.section-title-row span { font-size:.68rem; color:rgba(var(--v-theme-on-surface),.55); }
.statistics-table-wrap { overflow-x:auto; }
.statistics-table { width:100%; min-width:410px; border-collapse:collapse; table-layout:fixed; font-size:.68rem; }
.statistics-table th,.statistics-table td { height:31px; padding:4px 3px; border-bottom:1px solid rgba(var(--v-border-color),.55); text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.statistics-table th { color:rgba(var(--v-theme-on-surface),.56); font-weight:550; }
.statistics-table th:first-child,.statistics-table td:first-child { position:sticky; left:0; z-index:1; width:54px; text-align:left; background:rgb(var(--v-theme-surface)); }
.statistics-empty { display:flex; gap:9px; align-items:flex-start; margin-top:10px; padding:12px; border-radius:10px; background:rgba(var(--v-theme-on-surface),.04); }
.statistics-empty strong,.statistics-empty span { display:block; }
.statistics-empty strong { font-size:.73rem; }
.statistics-empty span { margin-top:2px; font-size:.65rem; color:rgba(var(--v-theme-on-surface),.56); }
@media (max-width: 600px) {
  .stat-cards { grid-template-columns:repeat(3,1fr); }
}
@media (max-width: 430px) {
  .period-fields { grid-template-columns:1fr 1fr; }
  .period-fields :deep(.v-btn) { grid-column:1/-1; }
}
</style>
