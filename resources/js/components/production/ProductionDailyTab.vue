<template>
<div>
  <div class="production-date-nav mb-3">
    <v-btn icon="mdi-chevron-left" variant="text" @click="moveDate(-1)" />
    <v-menu v-model="dateMenu" :close-on-content-click="false">
      <template #activator="{ props: menuProps }">
        <v-btn v-bind="menuProps" variant="text" class="date-button">{{ formatKoreanDate(workDate) }}</v-btn>
      </template>
      <v-date-picker :model-value="workDate" @update:model-value="selectPickerDate" />
    </v-menu>
    <v-btn icon="mdi-chevron-right" variant="text" @click="moveDate(1)" />
    <v-btn v-if="workDate !== today" size="small" variant="text" @click="emit('update:workDate', today)">오늘</v-btn>
  </div>
  <v-alert v-if="daily.blocking_previous_date" type="warning" variant="tonal" density="compact" class="mb-3 app-supporting-alert">
    {{ daily.blocking_previous_date }} 마감 확인이 필요합니다. 이전 날짜를 먼저 마감한 뒤 신규 업무를 입력할 수 있습니다.
    <template #append>
      <v-btn size="small" variant="text" @click="emit('update:workDate', daily.blocking_previous_date)">이동</v-btn>
    </template>
  </v-alert>
  <div class="daily-metrics mb-4">
    <button v-for="metric in metrics" :key="metric.key" type="button" class="metric-item" @click="openMetric(metric.key)">
      <span>{{ metric.title }}</span>
      <strong>{{ metric.value }}</strong>
    </button>
  </div>
  <div class="d-flex flex-wrap align-center ga-2 mb-3">
    <v-btn-toggle v-model="filter" mandatory density="compact" variant="outlined">
      <v-btn value="all">전체</v-btn>
      <v-btn value="missing">미입력</v-btn>
      <v-btn value="occurred">발생</v-btn>
    </v-btn-toggle>
    <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="제품 검색" variant="outlined" density="compact" hide-details clearable class="product-search" />
    <v-spacer />
    <span class="app-result-count">{{ daily.complete_count }} / {{ daily.required_count }} 입력</span>
    <v-btn size="small" variant="text" @click="askBulkZero('loss')">미입력 로스 전체 0 확인</v-btn>
    <v-btn size="small" variant="text" @click="askBulkZero('waste')">미입력 폐기 전체 0 확인</v-btn>
  </div>
  <v-card v-for="group in groupedRows" :key="group.name" variant="outlined" rounded="lg" class="mb-3">
    <v-card-title class="category-header" @click="toggleCategory(group.name)">
      <div>
        <div class="text-subtitle-1 font-weight-bold">{{ group.name }}</div>
        <div class="app-supporting-text text-medium-emphasis">생산 {{ sum(group.rows, 'production') }} · 이월 {{ sum(group.rows, 'carryover_in') }} · 판매 {{ sum(group.rows, 'sale') }} · 로스 {{ sum(group.rows, 'loss') }} · 폐기 {{ sum(group.rows, 'waste') }}</div>
      </div>
      <v-icon :icon="collapsed.has(group.name) ? 'mdi-chevron-down' : 'mdi-chevron-up'" />
    </v-card-title>
    <v-expand-transition>
      <div v-show="!collapsed.has(group.name)" class="product-table-wrap">
        <table class="product-table">
          <thead>
            <tr>
              <th>제품명</th>
              <th>생산</th>
              <th>이월</th>
              <th>판매</th>
              <th>로스</th>
              <th>폐기</th>
              <th>폐기율</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in group.rows" :key="row.id" :class="{ 'row-inactive': !row.is_active }">
              <td>
                <button type="button" class="product-name" @click="openProduct(row)">{{ row.name }}</button>
                <div v-if="row.mismatch" class="text-error app-result-count">수량 확인 필요</div>
              </td>
              <td>
                <v-chip size="small" :variant="row.production_confirmed ? 'tonal' : 'outlined'" :class="{ 'unconfirmed-chip': !row.production_confirmed }" @click="openProduction(row)">{{ row.production }}</v-chip>
              </td>
              <td>
                <v-chip size="small" variant="tonal" @click="openFlow(row)">{{ row.carryover_in }}</v-chip>
              </td>
              <td>
                <v-chip size="small" variant="tonal" @click="openSale(row)">{{ row.sale }}</v-chip>
              </td>
              <td>
                <v-chip size="small" :variant="row.loss > 0 ? 'flat' : 'tonal'" @click="openFlow(row)">{{ row.loss }}</v-chip>
              </td>
              <td>
                <v-chip size="small" :variant="row.waste > 0 ? 'flat' : 'tonal'" @click="openFlow(row)">{{ row.waste }}</v-chip>
              </td>
              <td>{{ row.waste_rate === null ? '-' : `${row.waste_rate}%` }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </v-expand-transition>
  </v-card>
  <v-empty-state v-if="!groupedRows.length" title="조건에 맞는 제품이 없습니다." icon="mdi-bread-slice-outline" />
  <div class="d-flex align-center mt-4">
    <v-btn variant="text" prepend-icon="mdi-history" @click="openHistory">변경 이력</v-btn>
    <v-spacer />
    <v-btn v-if="daily.closure_status === 'closed'" variant="outlined" :disabled="!canCorrect" @click="correctionOpen=true">마감 후 수정</v-btn>
    <v-btn v-else variant="flat" :disabled="daily.closure_status === 'store_closed'" @click="previewClose">마감</v-btn>
  </div>
  <ProductionBatchDialog v-model="batchOpen" :product="selectedProduct" :store-id="storeId" :work-date="workDate" :workers="options.workers || []" :zero-reasons="options.zero_reasons || []" @saved="handleSaved" @error="emit('error', $event)" />
  <ProductionFlowDialog v-model="flowOpen" :product="selectedProduct" :store-id="storeId" :work-date="workDate" :loss-reasons="options.loss_reasons || []" :waste-reasons="options.waste_reasons || []" @saved="handleSaved" @error="emit('error', $event)" />
  <v-dialog v-model="detailOpen" max-width="680">
    <v-card rounded="lg">
      <v-card-title class="d-flex justify-space-between">
        <span>{{ detailTitle }}</span>
        <v-btn icon="mdi-close" size="small" variant="text" @click="detailOpen=false" />
      </v-card-title>
      <v-card-text>
        <pre class="detail-text">{{ detailText }}</pre>
      </v-card-text>
      <v-card-actions>
        <v-btn variant="text" @click="detailOpen=false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog v-model="historyOpen" max-width="720">
    <v-card rounded="lg">
      <v-card-title class="d-flex justify-space-between">
        <span>변경 이력</span>
        <v-btn icon="mdi-close" size="small" variant="text" @click="historyOpen=false"/>
      </v-card-title>
      <v-card-text>
        <v-list v-if="historyLogs.length" lines="two">
          <v-list-item v-for="log in historyLogs" :key="log.id" :title="log.description" :subtitle="`${log.user?.name || '-'} · ${new Date(log.created_at).toLocaleString('ko-KR')}`"/>
        </v-list>
        <v-empty-state v-else title="변경 이력이 없습니다." icon="mdi-history"/>
      </v-card-text>
      <v-card-actions>
        <v-btn variant="text" @click="historyOpen=false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog v-model="closeOpen" max-width="720" persistent>
    <v-card rounded="lg">
      <v-card-title>하루 마감 최종 확인</v-card-title>
      <v-card-text>
        <div class="app-supporting-text text-medium-emphasis mb-3">저장 전에 생산·이월·판매·로스·폐기와 미처리 항목을 다시 확인합니다.</div>
        <div class="daily-metrics mb-4">
          <div v-for="metric in metrics" :key="metric.key" class="metric-item static">
            <span>{{ metric.title }}</span>
            <strong>{{ metric.value }}</strong>
          </div>
        </div>
        <v-alert v-if="closePreview && !closePreview.can_close" type="warning" variant="tonal" density="compact" class="app-supporting-alert">아직 확인하지 않은 제품 또는 수량 오류가 있습니다. {{ closePreview.incomplete?.length || 0 }}개 제품을 확인해주세요.</v-alert>
        <div v-if="closePreview?.incomplete?.length" class="mt-3">
          <v-chip v-for="row in closePreview.incomplete" :key="row.id" class="mr-1 mb-1" size="small" @click="closeOpen=false; openProduct(row)">{{ row.name }}</v-chip>
        </div>
        <div class="app-supporting-text mt-3">날씨: {{ closePreview?.weather_status === 'complete' ? '수집 완료' : '수집 대기 · 날씨 수집 실패는 마감을 막지 않습니다.' }}</div>
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn variant="text" @click="closeOpen=false">취소</v-btn>
        <v-spacer />
        <v-btn variant="flat" :disabled="!closePreview?.can_close" @click="confirmCloseOpen=true">저장</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog v-model="correctionOpen" max-width="560">
    <v-card rounded="lg">
      <v-card-title>마감 후 수정</v-card-title>
      <v-card-text>
        <div class="app-supporting-text text-medium-emphasis mb-3">과거 기록을 수정하면 통계와 현재 분석이 다시 계산됩니다. 당시 추천 스냅샷은 변경하지 않습니다.</div>
        <v-textarea v-model="correctionReason" label="수정 사유" variant="outlined" rows="3"/>
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn variant="text" @click="correctionOpen=false">취소</v-btn>
        <v-spacer/>
        <v-btn variant="flat" :disabled="correctionReason.trim().length < 2" @click="correctionConfirmOpen=true">수정 시작</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <ConfirmDialog v-model="correctionConfirmOpen" title="마감 후 수정" message="마감된 기록의 수정을 시작하시겠습니까? 연결된 이월 기록이 있으면 영향 날짜도 함께 확인해야 합니다." @confirm="openCorrection"/>
  <ConfirmDialog v-model="bulkZeroConfirmOpen" title="일괄 0개 확인" :message="`아직 입력하지 않은 ${bulkZeroType === 'loss' ? '로스' : '폐기'}만 0개로 확인하시겠습니까? 이미 입력한 제품은 변경하지 않습니다.`" @confirm="bulkZero" />
  <ConfirmDialog v-model="confirmCloseOpen" title="하루 마감" message="현재 확인한 내용으로 하루 업무를 마감하시겠습니까? 마감 후 일반 수정은 제한됩니다." :loading="closing" @confirm="closeDay" />
</div>
</template>

<script setup>
import {
  computed, ref
}  from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import ProductionBatchDialog from './ProductionBatchDialog.vue';
import ProductionFlowDialog from './ProductionFlowDialog.vue';
import {
  addLocalDays, formatKoreanDate, toLocalDateString
}  from '../../utils/localDate';
const props = defineProps({
  daily: {
    type: Object, default: () => ({
      rows: [], totals: {
      }
    })
  }, options: {
    type: Object, default: () => ({
    })
  }, storeId: Number, workDate: String, canMutate: Boolean, canCorrect: Boolean
});
const emit = defineEmits(['update:workDate','reload','error','success']);
const today = toLocalDateString();
const dateMenu = ref(false);
const filter = ref('all');
const search = ref('');
const collapsed = ref(new Set());
const selectedProduct = ref(null);
const batchOpen = ref(false);
const flowOpen = ref(false);
const detailOpen = ref(false);
const detailTitle = ref('');
const detailText = ref('');
const historyOpen = ref(false);
const historyLogs = ref([]);
const correctionOpen = ref(false);
const correctionConfirmOpen = ref(false);
const correctionReason = ref('');
const closeOpen = ref(false);
const confirmCloseOpen = ref(false);
const bulkZeroConfirmOpen = ref(false);
const bulkZeroType = ref('loss');
const closePreview = ref(null);
const closing = ref(false);
const metrics = computed(() => [ {
  key:'production', title:'생산', value:`${props.daily.totals?.production || 0}개`
}, {
  key:'carryover', title:'이월', value:`${props.daily.totals?.carryover || 0}개`
}, {
  key:'sale', title:'판매', value:`${props.daily.totals?.sale || 0}개`
}, {
  key:'loss', title:'로스', value:`${props.daily.totals?.loss || 0}개`
}, {
  key:'waste', title:'폐기', value:`${props.daily.totals?.waste || 0}개`
}, {
  key:'waste_rate', title:'폐기율', value: props.daily.totals?.waste_rate == null ? '-' : `${props.daily.totals.waste_rate}%`
}, ]);
const filteredRows = computed(() => (props.daily.rows || []).filter((row) => {
  const q = search.value?.trim().toLocaleLowerCase('ko-KR'); if (q && !row.name.toLocaleLowerCase('ko-KR').includes(q)) return false;
  if (filter.value === 'missing') return !row.complete; if (filter.value === 'occurred') return row.loss > 0 || row.waste > 0; return true;
}));
const groupedRows = computed(() => {
  const map = new Map(); for (const row of filteredRows.value) {
    if (!map.has(row.category_name)) map.set(row.category_name, []); map.get(row.category_name).push(row);
  }  return [...map.entries()].map(([name,rows])=>({
    name,rows
  }));
});
/** 날짜 화살표로 하루씩 이동합니다. */
function moveDate(amount) {
  emit('update:workDate', addLocalDays(props.workDate, amount));
}
/** Vuetify 날짜 선택값을 YYYY-MM-DD로 정규화합니다. */
function selectPickerDate(value) {
  const date = value instanceof Date ? toLocalDateString(value) : String(value).slice(0,10);
  emit('update:workDate', date);
  dateMenu.value=false;
}
/** 카테고리 접기 상태를 화면 내부에서만 변경합니다. */
function toggleCategory(name) {
  const next = new Set(collapsed.value);
  next.has(name) ? next.delete(name) : next.add(name);
  collapsed.value = next;
}
/** 카테고리 소계를 계산합니다. */
function sum(rows,key) {
  return rows.reduce((total,row)=>total+Number(row[key]||0),0);
}
/** 권한 또는 이전 날짜 미마감으로 수정할 수 없는 상태를 공통 안내합니다. */
function ensureMutable() {
  if (!props.canMutate) {
    emit('error','해당 기능을 사용할 권한이 없습니다.');
    return false;
  }  if (props.daily.blocking_previous_date) {
    emit('error','이전 날짜 마감 확인을 먼저 완료해주세요.');
    return false;
  }  if (['closed','store_closed'].includes(props.daily.closure_status)) {
    emit('error','현재 날짜는 일반 수정이 제한되어 있습니다.');
    return false;
  }  return true;
}
/** 생산 칩에서 생산 배치 입력을 엽니다. */
function openProduction(row) {
  if (!ensureMutable()) return;
  selectedProduct.value=row;
  batchOpen.value=true;
}
/** 이월·로스·폐기 칩에서 수량 처리 다이얼로그를 엽니다. */
function openFlow(row) {
  if (!ensureMutable()) return;
  selectedProduct.value=row;
  flowOpen.value=true;
}
/** 계산 판매량의 근거를 읽기 전용으로 보여줍니다. */
function openSale(row) {
  detailTitle.value=`${row.name} 판매 계산`;
  detailText.value=`사용 가능 ${row.production + row.carryover_in}개\n로스 -${row.loss}개\n폐기 -${row.waste}개\n기타 출고 -${row.other_outflow}개\n다음날 이월 -${row.carryover_out}개\n\n계산 판매 ${row.sale}개`;
  detailOpen.value=true;
}
/** 제품의 선택일 운영 상태와 추천 진입점을 읽기 쉽게 보여줍니다. */
function openProduct(row) {
  detailTitle.value=row.name;
  detailText.value=`생산 ${row.production}개 · 이월 ${row.carryover_in}개 · 판매 ${row.sale}개\n로스 ${row.loss}개 · 폐기 ${row.waste}개 · 폐기율 ${row.waste_rate == null ? '-' : `${
    row.waste_rate
  }%`}\n\n${row.complete ? '필수 확인 완료' : '확인이 필요한 항목이 있습니다.'}`;
  detailOpen.value=true;
}
/** 상단 전체 지표를 누르면 해당 지표의 제품별 값을 읽기 전용으로 보여줍니다. */
function openMetric(key) {
  const title=metrics.value.find((m)=>m.key===key)?.title || '상세';
  detailTitle.value=`${title} 상세`;
  detailText.value=(props.daily.rows||[]).filter((r)=>r.is_active).map((r)=>`${r.name} · ${key==='waste_rate' ? (r.waste_rate==null?'-':`${
    r.waste_rate
  }%`) : `${
    r[key==='carryover'?'carryover_in':key] || 0
  } 개`}`).join('\n') || '표시할 데이터가 없습니다.';
  detailOpen.value=true;
}
/** 아직 확인하지 않은 로스 또는 폐기만 0개로 일괄 확인합니다. */
function askBulkZero(type) {
  if (!ensureMutable()) return;
  bulkZeroType.value = type;
  bulkZeroConfirmOpen.value = true;
}
/** 확인창에서 선택한 로스 또는 폐기 미입력 항목을 0개로 일괄 확인합니다. */
async function bulkZero() {
  const type = bulkZeroType.value;
  if (!ensureMutable()) return;
  try {
    const {
      data
    } = await window.axios.post('/tillwhite/api/production-management/bulk-zero', {
      store_id: props.storeId, work_date: props.workDate, type
    });
    bulkZeroConfirmOpen.value = false;
    emit('success', data.message);
    emit('reload');
  }  catch (error) {
    emit('error', error.response?.data?.message || '일괄 확인하지 못했습니다.');
  }
}
/** 하위 다이얼로그 저장 성공 후 최신 일일 데이터를 다시 조회합니다. */
function handleSaved(message) {
  emit('success',message);
  emit('reload');
}
/** 선택 날짜의 감사 로그를 불러와 일일 변경 이력 다이얼로그를 엽니다. */
async function openHistory() {
  try {
    const {
      data
    } = await window.axios.get('/tillwhite/api/production-management/history', {
      params: {
        store_id: props.storeId, work_date: props.workDate
      }
    });
    historyLogs.value = data.logs || [];
    historyOpen.value = true;
  }  catch (error) {
    emit('error', error.response?.data?.message || '변경 이력을 불러오지 못했습니다.');
  }
}
/** 관리자 권한과 필수 사유를 확인한 뒤 마감된 날짜를 수정 상태로 엽니다. */
async function openCorrection() {
  if (!props.canCorrect) {
    emit('error','마감 후 수정 권한이 없습니다.');
    return;
  }
  try {
    const {
      data
    } = await window.axios.post('/tillwhite/api/production-management/correction/open', {
      store_id: props.storeId, work_date: props.workDate, reason: correctionReason.value
    });
    correctionConfirmOpen.value = false;
    correctionOpen.value = false;
    correctionReason.value = '';
    const affected = data.affected_dates?.length ? ` 영향 날짜: ${data.affected_dates.join(', ')}` : '';
    emit('success', `${data.message}${affected}`);
    emit('reload');
  }  catch (error) {
    emit('error', error.response?.data?.message || '마감 후 수정을 시작하지 못했습니다.');
  }
}
/** 서버에서 마감 가능 여부를 다시 계산해 최종 확인 다이얼로그를 엽니다. */
async function previewClose() {
  if (!ensureMutable()) return;
  try {
    const {
      data
    }=await window.axios.get('/tillwhite/api/production-management/close-preview',{
      params:{
        store_id:props.storeId,work_date:props.workDate
      }
    });
    closePreview.value=data;
    closeOpen.value=true;
  }  catch(error){
    emit('error',error.response?.data?.message||'마감 내용을 확인하지 못했습니다.');
  }
}
/** 최종 확인 뒤 서버 마감을 실행하고 성공한 경우에만 완료 상태를 반영합니다. */
async function closeDay() {
  closing.value=true;
  try {
    await window.axios.post('/tillwhite/api/production-management/close',{
      store_id:props.storeId,work_date:props.workDate
    });
    confirmCloseOpen.value=false;
    closeOpen.value=false;
    emit('success','하루 업무를 마감했습니다.');
    emit('reload');
  }  catch(error){
    emit('error',error.response?.data?.message||'마감하지 못했습니다.');
  }  finally {
    closing.value=false;
  }
}
</script>

<style scoped>
.production-date-nav {
  position: sticky;
  top: 0;
  z-index: 4;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:4px;
  background: rgb(var(--v-theme-surface));
  padding:6px 0;
}
.date-button {
  font-weight:700;
}
.daily-metrics {
  display:grid;
  grid-template-columns:repeat(6,minmax(0,1fr));
  gap:8px;
}
.metric-item {
  appearance:none;
  text-align:left;
  padding:12px;
  border:1px solid rgba(var(--v-border-color),var(--v-border-opacity));
  border-radius:12px;
  background:rgb(var(--v-theme-surface));
  color:inherit;
  cursor:pointer;
}
.metric-item.static {
  cursor:default;
}
.metric-item span,.metric-item strong {
  display:block;
}
.metric-item span {
  font-size:.72rem;
  color:rgba(var(--v-theme-on-surface),.62)
}
.metric-item strong {
  font-size:1.1rem;
  margin-top:3px
}
.product-search {
  max-width:280px;
}
.category-header {
  display:flex;
  align-items:center;
  justify-content:space-between;
  cursor:pointer
}
.product-table-wrap {
  overflow-x:auto
}
.product-table {
  width:100%;
  border-collapse:collapse;
  min-width:680px
}
.product-table th,.product-table td {
  padding:10px 8px;
  border-top:1px solid rgba(var(--v-border-color),var(--v-border-opacity));
  text-align:center;
  white-space:nowrap
}
.product-table th:first-child,.product-table td:first-child {
  text-align:left
}
.product-name {
  appearance:none;
  border:0;
  background:none;
  color:inherit;
  font-weight:600;
  cursor:pointer
}
.unconfirmed-chip {
  opacity:.5
}
.row-inactive {
  opacity:.58
}
.detail-text {
  font-family:inherit;
  white-space:pre-wrap;
  line-height:1.65;
  margin:0
}
@media(max-width:760px) {
  .daily-metrics {
    grid-template-columns:repeat(3,minmax(0,1fr))
  }
  .product-search {
    max-width:none;
    min-width:100%
  }
}
</style>
