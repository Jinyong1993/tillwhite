<template>
  <div class="analysis-page">
    <header class="tab-heading">
      <div>
        <h3>제품 분석</h3>
        <p>확인이 필요한 변화와 생산 참고 정보를 제품별로 확인합니다.</p>
      </div>
      <v-btn size="small" variant="text" prepend-icon="mdi-refresh" @click="load">새로고침</v-btn>
    </header>

    <v-divider class="full-divider" />

    <div class="analysis-summary">
      <div>
        <span>분석 제품</span>
        <strong>{{ items.length }}</strong>
      </div>
      <div>
        <span>확인할 제품</span>
        <strong>{{ flaggedCount }}</strong>
      </div>
      <div>
        <span>추천 확인 가능</span>
        <strong>{{ recommendationCount }}</strong>
      </div>
    </div>

    <section v-if="sortedItems.length" class="analysis-list">
      <article v-for="item in sortedItems" :key="item.product_id" class="analysis-item">
        <div class="analysis-item-head">
          <div>
            <strong>{{ item.product_name }}</strong>
            <span>{{ item.flags.length ? `확인할 내용 ${item.flags.length}건` : '특이사항 없음' }}</span>
          </div>
          <span class="confidence-chip">신뢰도 {{ confidenceText(item.recommendation.confidence) }}</span>
        </div>

        <div v-if="item.flags.length" class="flag-list">
          <div v-for="flag in item.flags" :key="flag">{{ flag }}</div>
        </div>

        <div class="recommendation-row">
          <span>권장 생산량</span>
          <strong>{{ recommendationText(item.recommendation) }}</strong>
        </div>

        <div v-if="item.recommendation.reasons?.length" class="reason-list">
          <span v-for="reason in item.recommendation.reasons" :key="reason">{{ reason }}</span>
        </div>
      </article>
    </section>

    <v-empty-state
      v-else
      title="분석할 기록이 없습니다."
      text="생산 기록이 쌓이면 제품별 변화와 생산 참고 정보를 확인할 수 있습니다."
      icon="mdi-chart-box-outline"
    />
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

/** 확인 항목이 많은 제품을 먼저 보여줘 다음 행동을 빠르게 찾도록 합니다. */
const sortedItems = computed(() => (
  [...items.value].sort((a, b) => b.flags.length - a.flags.length)
));

const flaggedCount = computed(() => items.value.filter((item) => item.flags.length > 0).length);
const recommendationCount = computed(() => (
  items.value.filter((item) => item.recommendation?.min != null).length
));

watch([() => props.storeId, () => props.workDate], load, { immediate: true });

/** 현재 점포의 제품별 분석과 생산 참고 정보를 조회합니다. */
async function load() {
  if (!props.storeId) return;

  try {
    const { data } = await window.axios.get('/tillwhite/api/production-management/analysis', {
      params: {
        store_id: props.storeId,
        date: props.workDate,
      },
    });
    items.value = data.items || [];
  } catch (error) {
    emit('error', error.response?.data?.message || '분석 데이터를 불러오지 못했습니다.');
  }
}

/** 데이터가 부족할 때 임의의 추천 수량을 만들지 않습니다. */
function recommendationText(recommendation) {
  return recommendation?.min == null
    ? '데이터 부족'
    : `${recommendation.min}~${recommendation.max}`;
}

/** 내부 신뢰도 코드를 직원이 이해하기 쉬운 문구로 변환합니다. */
function confidenceText(value) {
  return {
    insufficient: '데이터 부족',
    low: '낮음',
    normal: '보통',
    high: '높음',
  }[value] || '-';
}
</script>

<style scoped>
.analysis-page { display:flex; flex-direction:column; }
.tab-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:2px 0 14px; }
.tab-heading h3 { margin:0; font-size:.98rem; font-weight:650; }
.tab-heading p { margin:4px 0 0; font-size:.72rem; color:rgba(var(--v-theme-on-surface),.56); }
.full-divider { margin-inline:calc(var(--production-content-padding, 0px) * -1); }
.analysis-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; padding:16px 0; }
.analysis-summary>div { padding:11px 8px; border:1px solid rgba(var(--v-border-color),.62); border-radius:11px; text-align:center; box-shadow:0 2px 7px rgba(0,0,0,.04); }
.analysis-summary span,.analysis-summary strong { display:block; }
.analysis-summary span { font-size:.66rem; color:rgba(var(--v-theme-on-surface),.56); }
.analysis-summary strong { margin-top:3px; font-size:1.05rem; font-weight:700; font-variant-numeric:tabular-nums; }
.analysis-list { display:flex; flex-direction:column; gap:10px; }
.analysis-item { overflow:hidden; border:1px solid rgba(var(--v-border-color),.65); border-radius:12px; background:rgb(var(--v-theme-surface)); }
.analysis-item-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; padding:12px; }
.analysis-item-head>div { min-width:0; }
.analysis-item-head strong,.analysis-item-head span { display:block; }
.analysis-item-head strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.84rem; font-weight:650; }
.analysis-item-head div span { margin-top:2px; font-size:.66rem; color:rgba(var(--v-theme-on-surface),.55); }
.confidence-chip { flex:none; padding:3px 7px; border-radius:999px; background:rgba(var(--v-theme-on-surface),.06); font-size:.64rem; }
.flag-list { display:flex; flex-direction:column; gap:5px; padding:0 12px 10px; }
.flag-list div { padding:7px 9px; border-radius:8px; background:rgba(var(--v-theme-warning),.09); font-size:.7rem; line-height:1.4; }
.recommendation-row { display:flex; align-items:center; justify-content:space-between; padding:10px 12px; border-top:1px solid rgba(var(--v-border-color),.55); }
.recommendation-row span { font-size:.7rem; color:rgba(var(--v-theme-on-surface),.58); }
.recommendation-row strong { font-size:.88rem; font-weight:700; }
.reason-list { display:flex; flex-direction:column; gap:3px; padding:0 12px 11px; }
.reason-list span { font-size:.67rem; line-height:1.45; color:rgba(var(--v-theme-on-surface),.6); }
@media (max-width: 430px) {
  .tab-heading p { max-width:230px; }
  .analysis-summary { gap:6px; }
  .analysis-summary>div { padding-inline:5px; }
}
</style>
