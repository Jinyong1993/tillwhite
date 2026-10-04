<template>
<div class="daily-page">
  <section class="daily-section date-section">
    <div class="production-date-nav">
      <v-btn icon="mdi-chevron-left" variant="text" size="small" aria-label="이전 날짜" @click="moveDate(-1)" />
      <v-menu v-model="dateMenu" :close-on-content-click="false">
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" variant="text" class="date-button">{{ formatKoreanDate(workDate) }}</v-btn>
        </template>
        <v-date-picker :model-value="workDate" @update:model-value="selectPickerDate" />
      </v-menu>
      <v-btn icon="mdi-chevron-right" variant="text" size="small" aria-label="다음 날짜" @click="moveDate(1)" />
      <v-btn v-if="workDate !== today" size="small" variant="text" @click="emit('update:workDate', today)">오늘</v-btn>
    </div>
  </section>

  <v-divider />

  <v-alert v-if="daily.blocking_previous_date" type="warning" variant="tonal" density="compact" class="my-3 app-supporting-alert">
    {{ daily.blocking_previous_date }} 업무가 아직 마감되지 않았습니다. 이전 날짜를 먼저 마감해 주세요.
    <template #append>
      <v-btn size="small" variant="text" @click="emit('update:workDate', daily.blocking_previous_date)">이동</v-btn>
    </template>
  </v-alert>

  <section class="daily-section summary-section">
    <div class="section-heading">
      <div>
        <h3>오늘 요약</h3>
        <p>선택한 날짜의 생산 흐름을 한눈에 확인합니다.</p>
      </div>
    </div>
    <div class="daily-metrics">
      <button v-for="metric in metrics" :key="metric.key" type="button" class="metric-item" @click="openMetric(metric.key)">
        <span>{{ metric.title }}</span>
        <strong>{{ metric.value }}</strong>
      </button>
    </div>
  </section>

  <v-divider />

  <section class="daily-section product-section">
    <div class="section-heading product-heading">
      <div>
        <h3>제품별 현황</h3>
        <p>{{ progressText }}</p>
      </div>
      <v-menu v-if="canMutate" location="bottom end">
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" size="small" variant="outlined" prepend-icon="mdi-check-all">미확인 항목 일괄 확인</v-btn>
        </template>
        <v-list density="compact" min-width="230">
          <v-list-item title="로스 없음(0)으로 확인" subtitle="아직 확인하지 않은 제품만 적용" @click="askBulkZero('loss')" />
          <v-list-item title="폐기 없음(0)으로 확인" subtitle="아직 확인하지 않은 제품만 적용" @click="askBulkZero('waste')" />
        </v-list>
      </v-menu>
    </div>

    <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="제품 검색" placeholder="제품명을 입력하세요" variant="outlined" density="compact" hide-details clearable class="product-search mb-3" />

    <div class="product-filter-row mb-4">
      <v-btn-toggle v-model="filter" mandatory density="compact" variant="text" class="status-filter">
        <v-btn value="all">전체</v-btn>
        <v-btn value="missing">기록 미완료</v-btn>
        <v-btn value="occurred">변동 있음</v-btn>
      </v-btn-toggle>
      <span class="app-supporting-text text-medium-emphasis">{{ filteredRows.length }}개 제품</span>
    </div>

    <section v-for="group in groupedRows" :key="group.name" class="category-block">
      <button type="button" class="category-header" @click="toggleCategory(group.name)">
        <div class="category-heading-copy">
          <strong>{{ group.name }}</strong>
          <span>{{ categoryProgressText(group.allRows) }}</span>
        </div>
        <div class="category-heading-side">
          <span>{{ categoryCompleteCount(group.allRows) }} / {{ group.allRows.filter((row) => row.is_active).length }}</span>
          <v-icon :icon="collapsed.has(group.name) ? 'mdi-chevron-down' : 'mdi-chevron-up'" size="small" />
        </div>
      </button>
      <div class="category-progress" aria-hidden="true">
        <span :style="{ width: `${categoryProgress(group.allRows)}%` }" />
      </div>

      <v-expand-transition>
        <div
          v-show="!collapsed.has(group.name)"
          class="product-table-wrap"
          @pointerdown="startTableDrag"
          @pointermove="moveTableDrag"
          @pointerup="endTableDrag"
          @pointercancel="endTableDrag"
          @pointerleave="endTableDrag"
        >
          <table class="product-table">
            <colgroup>
              <col class="product-column" />
              <col v-for="key in 6" :key="key" class="number-column" />
            </colgroup>
            <thead>
              <tr>
                <th>제품명</th><th>생산</th><th>이월</th><th>판매</th><th>로스</th><th>폐기</th><th>폐기율</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in group.rows" :key="row.id" :class="{ 'row-inactive': !row.is_active, 'row-needs-check': !row.complete && row.is_active }">
                <td>
                  <button type="button" class="product-name" :title="row.name" @click="openProduct(row)">{{ row.name }}</button>
                  <span v-if="row.mismatch" class="row-warning">수량 오류</span>
                </td>
                <td><button type="button" class="table-value" :class="{ pending: !row.production_confirmed }" @click="openProduction(row)">{{ row.production_confirmed ? row.production : '-' }}</button></td>
                <td><button type="button" class="table-value" @click="openFlow(row, 'carryover')">{{ row.carryover_in }}</button></td>
                <td><button type="button" class="table-value calculated" @click="openSale(row)">{{ row.sale }}</button></td>
                <td><button type="button" class="table-value" :class="{ attention: row.loss > 0, pending: !row.loss_confirmed }" @click="openFlow(row, 'loss')">{{ row.loss_confirmed ? row.loss : '-' }}</button></td>
                <td><button type="button" class="table-value" :class="{ attention: row.waste > 0, pending: !row.waste_confirmed }" @click="openFlow(row, 'waste')">{{ row.waste_confirmed ? row.waste : '-' }}</button></td>
                <td class="rate-value">{{ row.waste_rate === null ? '-' : `${row.waste_rate}%` }}</td>
              </tr>
              <tr class="subtotal-row">
                <td>소계</td>
                <td>{{ sum(group.rows, 'production') }}</td><td>{{ sum(group.rows, 'carryover_in') }}</td><td>{{ sum(group.rows, 'sale') }}</td><td>{{ sum(group.rows, 'loss') }}</td><td>{{ sum(group.rows, 'waste') }}</td><td>{{ groupWasteRate(group.rows) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </v-expand-transition>
    </section>

    <v-empty-state v-if="!groupedRows.length" title="조건에 맞는 제품이 없습니다." icon="mdi-bread-slice-outline" />
  </section>

  <div class="daily-actions">
    <v-btn variant="text" prepend-icon="mdi-history" @click="openHistory">변경 이력</v-btn>
    <v-spacer />
    <v-btn v-if="daily.closure_status === 'closed'" variant="outlined" :disabled="!canCorrect" @click="correctionOpen=true">마감 후 수정</v-btn>
    <v-btn v-else variant="flat" :disabled="daily.closure_status === 'store_closed' || !canMutate" @click="previewClose">오늘 마감</v-btn>
  </div>
  <ProductionBatchDialog v-model="batchOpen" :product="selectedProduct" :store-id="storeId" :work-date="workDate" :workers="options.workers || []" :zero-reasons="options.zero_reasons || []" @saved="handleSaved" @error="emit('error', $event)" />
  <ProductionFlowDialog
    v-model="flowOpen"
    :product="selectedProduct"
    :store-id="storeId"
    :work-date="workDate"
    :type="flowType"
    :loss-reasons="options.loss_reasons || []"
    :waste-reasons="options.waste_reasons || []"
    @saved="handleSaved"
    @error="emit('error', $event)"
  />
  <ProductDetailDialog
    v-model="productDetailOpen"
    :product="productDetail"
    :can-manage="false"
    :can-manage-recipe="false"
    :loading="productDetailLoading"
  >
    <template #extra-detail>
      <section class="production-product-section">
        <div class="production-product-title">선택 날짜 생산 현황</div>
        <div class="product-detail-metrics">
          <div v-for="metric in selectedProductMetrics" :key="metric.label">
            <span>{{ metric.label }}</span>
            <strong>{{ metric.value }}</strong>
          </div>
        </div>
      </section>
      <v-divider />
      <section class="production-product-section">
        <div class="production-product-title">간단 분석</div>
        <p class="product-analysis-copy">{{ selectedProductAnalysis }}</p>
      </section>
    </template>
  </ProductDetailDialog>

  <v-dialog v-model="detailOpen" max-width="680">
    <v-card rounded="lg" class="app-dialog-card">
      <v-card-title class="app-dialog-header d-flex justify-space-between">
        <span>{{ detailTitle }}</span>
        <v-btn icon="mdi-close" size="small" variant="text" @click="detailOpen=false" />
      </v-card-title>
      <v-card-text class="app-dialog-body">
        <pre class="detail-text">{{ detailText }}</pre>
      </v-card-text>
      <v-card-actions class="app-dialog-footer">
        <v-btn variant="text" @click="detailOpen=false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog v-model="historyOpen" max-width="720">
    <v-card rounded="lg" class="app-dialog-card">
      <v-card-title class="app-dialog-header d-flex justify-space-between">
        <span>변경 이력</span>
        <v-btn icon="mdi-close" size="small" variant="text" @click="historyOpen=false"/>
      </v-card-title>
      <v-card-text class="app-dialog-body">
        <v-list v-if="historyLogs.length" lines="two">
          <v-list-item v-for="log in historyLogs" :key="log.id" :title="log.description" :subtitle="`${log.user?.name || '-'} · ${new Date(log.created_at).toLocaleString('ko-KR')}`"/>
        </v-list>
        <v-empty-state v-else title="변경 이력이 없습니다." icon="mdi-history"/>
      </v-card-text>
      <v-card-actions class="app-dialog-footer">
        <v-btn variant="text" @click="historyOpen=false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog v-model="closeOpen" max-width="720" persistent>
    <v-card rounded="lg" class="app-dialog-card">
      <v-card-title class="app-dialog-header">하루 마감 최종 확인</v-card-title>
      <v-card-text class="app-dialog-body">
        <div class="app-supporting-text text-medium-emphasis mb-3">저장 전에 생산·이월·판매·로스·폐기와 미처리 항목을 다시 확인합니다.</div>
        <div class="daily-metrics mb-4">
          <div v-for="metric in metrics" :key="metric.key" class="metric-item static">
            <span>{{ metric.title }}</span>
            <strong>{{ metric.value }}</strong>
          </div>
        </div>
        <v-alert v-if="closePreview && !closePreview.can_close" type="warning" variant="tonal" density="compact" class="app-supporting-alert">
          아직 확인할 제품이 {{ closePreview.incomplete?.length || 0 }}개 있습니다. 아래에서 필요한 항목을 바로 입력할 수 있습니다.
        </v-alert>
        <div v-if="closePreview?.incomplete?.length" class="close-check-list mt-3">
          <div v-for="row in closePreview.incomplete" :key="row.id" class="close-check-item">
            <div class="close-check-copy">
              <strong>{{ row.name }}</strong>
              <span>{{ missingReasonText(row) }}</span>
            </div>
            <div class="close-check-actions">
              <v-btn v-if="!row.production_confirmed" size="small" variant="outlined" @click="openProduction(row)">생산 확인</v-btn>
              <v-btn v-if="!row.loss_confirmed" size="small" variant="outlined" @click="openFlow(row, 'loss')">로스 확인</v-btn>
              <v-btn v-if="!row.waste_confirmed" size="small" variant="outlined" @click="openFlow(row, 'waste')">폐기 확인</v-btn>
              <v-btn v-if="!row.disposition_confirmed" size="small" variant="outlined" @click="openFlow(row, 'carryover')">이월 확인</v-btn>
              <v-btn size="small" variant="text" @click="openProduct(row)">제품 정보</v-btn>
            </div>
          </div>
        </div>
        <div v-else-if="closePreview?.can_close" class="close-ready mt-3">모든 제품 확인이 완료되었습니다.</div>
        <div class="app-supporting-text mt-3">날씨: {{ closePreview?.weather_status === 'complete' ? '수집 완료' : '수집 대기 · 날씨 수집 실패는 마감을 막지 않습니다.' }}</div>
      </v-card-text>
      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" @click="closeOpen=false">취소</v-btn>
        <v-spacer />
        <v-btn variant="flat" :disabled="!closePreview?.can_close" @click="confirmCloseOpen=true">저장</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog v-model="correctionOpen" max-width="560">
    <v-card rounded="lg" class="app-dialog-card">
      <v-card-title class="app-dialog-header">마감 후 수정</v-card-title>
      <v-card-text class="app-dialog-body">
        <div class="app-supporting-text text-medium-emphasis mb-3">과거 기록을 수정하면 통계와 현재 분석이 다시 계산됩니다. 당시 추천 스냅샷은 변경하지 않습니다.</div>
        <v-textarea v-model="correctionReason" label="수정 사유" variant="outlined" rows="3"/>
      </v-card-text>
      <v-card-actions class="app-dialog-footer px-4 pb-4">
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
} from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import ProductDetailDialog from '../product/ProductDetailDialog.vue';
import ProductionBatchDialog from './ProductionBatchDialog.vue';
import ProductionFlowDialog from './ProductionFlowDialog.vue';
import {
  addLocalDays, formatKoreanDate, toLocalDateString
} from '../../utils/localDate';
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
const productDetail = ref(null);
const productDetailOpen = ref(false);
const productDetailLoading = ref(false);
const batchOpen = ref(false);
const flowOpen = ref(false);
const flowType = ref('carryover');
const tableDrag = { active: false, startX: 0, startScrollLeft: 0, element: null };
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
  key:'production', title:'생산', value: props.daily.totals?.production || 0
}, {
  key:'carryover', title:'이월', value: props.daily.totals?.carryover || 0
}, {
  key:'sale', title:'판매', value: props.daily.totals?.sale || 0
}, {
  key:'loss', title:'로스', value: props.daily.totals?.loss || 0
}, {
  key:'waste', title:'폐기', value: props.daily.totals?.waste || 0
}, {
  key:'waste_rate', title:'폐기율', value: props.daily.totals?.waste_rate == null ? '-' : `${props.daily.totals.waste_rate}%`
}, ]);
const filteredRows = computed(() => (props.daily.rows || []).filter((row) => {
  const q = search.value?.trim().toLocaleLowerCase('ko-KR');

  if (q && !row.name.toLocaleLowerCase('ko-KR').includes(q)) {
    return false;
  }

  if (filter.value === 'missing') {
    return !row.complete;
  }

  if (filter.value === 'occurred') {
    return row.loss > 0 || row.waste > 0;
  }

  return true;
}));
const groupedRows = computed(() => {
  const map = new Map();

  for (const row of filteredRows.value) {
    if (!map.has(row.category_name)) {
      map.set(row.category_name, []);
    }

    map.get(row.category_name).push(row);
  }

  return [...map.entries()].map(([name, rows]) => ({
    name,
    rows,
    allRows: (props.daily.rows || []).filter((row) => row.category_name === name),
  }));
});
const progressText = computed(() => {
  const required = Number(props.daily.required_count || 0);
  const complete = Number(props.daily.complete_count || 0);
  const remaining = Math.max(0, required - complete);
  return remaining > 0 ? `${remaining}개 제품 기록 미완료` : `${required}개 제품 확인 완료`;
});

const selectedProductMetrics = computed(() => {
  const row = selectedProduct.value;
  if (!row) return [];
  return [
    { label: '생산', value: row.production },
    { label: '이월', value: row.carryover_in },
    { label: '판매', value: row.sale },
    { label: '로스', value: row.loss },
    { label: '폐기', value: row.waste },
    { label: '폐기율', value: row.waste_rate == null ? '-' : `${row.waste_rate}%` },
  ];
});

const selectedProductAnalysis = computed(() => {
  const row = selectedProduct.value;
  if (!row) return '-';
  if (row.mismatch) return '수량 흐름이 맞지 않습니다. 생산·이월·로스·폐기 기록을 확인해 주세요.';
  if (!row.complete) return missingReasonText(row);
  if (row.waste > 0 || row.loss > 0) return `로스 ${row.loss}, 폐기 ${row.waste}가 기록되어 있습니다. 필요하면 원인과 수량을 다시 확인해 주세요.`;
  return '선택 날짜의 필수 확인이 모두 완료되었고 수량 흐름도 정상입니다.';
});

/** 제품별 확인 상태를 실제 미확인 항목 이름으로 설명합니다. */
function missingReasonText(row) {
  const missing = [];
  if (!row.production_confirmed) missing.push('생산');
  if (!row.loss_confirmed) missing.push('로스');
  if (!row.waste_confirmed) missing.push('폐기');
  if (!row.disposition_confirmed) missing.push('마감 수량');
  return missing.length ? `${missing.join(' · ')} 확인이 필요합니다.` : '확인이 필요한 항목이 있습니다.';
}

function categoryCompleteCount(rows) {
  return rows.filter((row) => row.is_active && row.complete).length;
}

function categoryProgress(rows) {
  const active = rows.filter((row) => row.is_active);
  return active.length ? Math.round(categoryCompleteCount(rows) / active.length * 100) : 100;
}

function categoryProgressText(rows) {
  const active = rows.filter((row) => row.is_active);
  const remaining = active.length - categoryCompleteCount(rows);
  return remaining > 0 ? `${active.length}개 제품 · ${remaining}개 기록 미완료` : `${active.length}개 제품 · 확인 완료`;
}

function groupWasteRate(rows) {
  const production = sum(rows, 'production');
  if (!production) return '-';
  return `${(sum(rows, 'waste') / production * 100).toFixed(1)}%`;
}

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
  }

  if (props.daily.blocking_previous_date) {
    emit('error','이전 날짜 마감 확인을 먼저 완료해주세요.');
    return false;
  }

  if (['closed', 'store_closed'].includes(props.daily.closure_status)) {
    emit('error','현재 날짜는 일반 수정이 제한되어 있습니다.');
    return false;
  }

  return true;
}
/** 데스크톱에서 표의 빈 영역을 잡아 좌우로 빠르게 이동할 수 있게 합니다. */
function startTableDrag(event) {
  if (event.pointerType === 'touch' || event.target.closest('button, .v-chip, a, input')) return;

  tableDrag.active = true;
  tableDrag.startX = event.clientX;
  tableDrag.startScrollLeft = event.currentTarget.scrollLeft;
  tableDrag.element = event.currentTarget;
  event.currentTarget.setPointerCapture?.(event.pointerId);
}

/** 드래그한 거리만큼 제품 표의 가로 스크롤 위치를 갱신합니다. */
function moveTableDrag(event) {
  if (!tableDrag.active || !tableDrag.element) return;

  tableDrag.element.scrollLeft = tableDrag.startScrollLeft - (event.clientX - tableDrag.startX);
}

/** 포인터가 끝나면 표 드래그 상태를 정리합니다. */
function endTableDrag() {
  tableDrag.active = false;
  tableDrag.element = null;
}

/** 생산 칩에서 생산 배치 입력을 엽니다. */
function openProduction(row) {
  if (!ensureMutable()) return;
  selectedProduct.value=row;
  batchOpen.value=true;
}
/** 이월·로스·폐기 칩에서 수량 처리 다이얼로그를 엽니다. */
function openFlow(row, type) {
  if (!ensureMutable()) return;

  selectedProduct.value = row;
  flowType.value = type;
  flowOpen.value = true;
}
/** 계산 판매량의 근거를 읽기 전용으로 보여줍니다. */
function openSale(row) {
  detailTitle.value=`${row.name} 판매 계산`;
  detailText.value=`사용 가능 ${row.production + row.carryover_in}개\n로스 -${row.loss}개\n폐기 -${row.waste}개\n기타 출고 -${row.other_outflow}개\n다음날 이월 -${row.carryover_out}개\n\n계산 판매 ${row.sale}개`;
  detailOpen.value=true;
}
/** 제품 관리의 상세 데이터를 재사용해 레시피와 선택 날짜 생산 현황을 함께 보여줍니다. */
async function openProduct(row) {
  selectedProduct.value = row;
  productDetailLoading.value = true;

  try {
    const { data } = await window.axios.get(`/tillwhite/api/production-management/products/${row.id}`);
    productDetail.value = data.product || data;
    productDetailOpen.value = true;
  } catch (error) {
    emit('error', error.response?.data?.message || '제품 정보를 불러오지 못했습니다.');
  } finally {
    productDetailLoading.value = false;
  }
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
  } catch (error) {
    emit('error', error.response?.data?.message || '일괄 확인하지 못했습니다.');
  }
}
/** 하위 다이얼로그 저장 성공 후 최신 일일 데이터를 다시 조회합니다. */
function handleSaved(message) {
  emit('success', message);
  emit('reload');

  // 마감 점검 중 입력했다면 마감창을 유지한 채 서버 기준 상태만 다시 계산합니다.
  if (closeOpen.value) {
    refreshClosePreview();
  }
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
  } catch (error) {
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
  } catch (error) {
    emit('error', error.response?.data?.message || '마감 후 수정을 시작하지 못했습니다.');
  }
}
/** 마감창을 닫지 않고 최신 미확인 제품과 마감 가능 여부만 다시 계산합니다. */
async function refreshClosePreview() {
  try {
    const { data } = await window.axios.get('/tillwhite/api/production-management/close-preview', {
      params: { store_id: props.storeId, work_date: props.workDate },
    });
    closePreview.value = data;
  } catch (error) {
    emit('error', error.response?.data?.message || '마감 내용을 다시 확인하지 못했습니다.');
  }
}

/** 서버에서 마감 가능 여부를 다시 계산해 최종 확인 다이얼로그를 엽니다. */
async function previewClose() {
  if (!ensureMutable()) return;
  try {
    const {
      data
    } = await window.axios.get('/tillwhite/api/production-management/close-preview',{
      params:{
        store_id: props.storeId, work_date: props.workDate
      }
    });
    closePreview.value=data;
    closeOpen.value=true;
  } catch (error) {
    emit('error',error.response?.data?.message||'마감 내용을 확인하지 못했습니다.');
  }
}
/** 최종 확인 뒤 서버 마감을 실행하고 성공한 경우에만 완료 상태를 반영합니다. */
async function closeDay() {
  closing.value=true;
  try {
    await window.axios.post('/tillwhite/api/production-management/close',{
      store_id: props.storeId, work_date: props.workDate
    });
    confirmCloseOpen.value=false;
    closeOpen.value=false;
    emit('success','하루 업무를 마감했습니다.');
    emit('reload');
  } catch (error) {
    emit('error',error.response?.data?.message||'마감하지 못했습니다.');
  } finally {
    closing.value=false;
  }
}
</script>

<style scoped>
.daily-page { display:flex; flex-direction:column; gap:0; }
.daily-section { padding:18px 0; }
.date-section { padding-top:0; padding-bottom:12px; }
.production-date-nav { display:flex; align-items:center; justify-content:center; gap:4px; min-height:42px; }
.date-button { font-weight:600; letter-spacing:-.02em; }
.section-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:14px; }
.section-heading h3 { margin:0; font-size:.98rem; font-weight:650; letter-spacing:-.02em; }
.section-heading p { margin:4px 0 0; font-size:.76rem; color:rgba(var(--v-theme-on-surface),.58); }
.daily-metrics { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:9px; }
.metric-item { appearance:none; text-align:center; padding:12px 8px; border:1px solid rgba(var(--v-border-color),.7); border-radius:12px; background:rgb(var(--v-theme-surface)); color:inherit; cursor:pointer; box-shadow:0 2px 8px rgba(0,0,0,.055); transition:transform .15s ease,box-shadow .15s ease; }
.metric-item:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.08); }
.metric-item.static { cursor:default; }
.metric-item span,.metric-item strong { display:block; }
.metric-item span { font-size:.7rem; font-weight:500; color:rgba(var(--v-theme-on-surface),.58); }
.metric-item strong { margin-top:3px; font-size:1.08rem; font-weight:650; font-variant-numeric:tabular-nums; }
.product-search { max-width:360px; }
.product-filter-row { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.status-filter { border-bottom:1px solid rgba(var(--v-border-color),.65); border-radius:0; }
.status-filter :deep(.v-btn) { min-width:auto; padding-inline:12px; font-size:.76rem; font-weight:500; }
.category-block { margin-bottom:18px; border:1px solid rgba(var(--v-border-color),.72); border-radius:12px; overflow:hidden; background:rgb(var(--v-theme-surface)); box-shadow:0 2px 9px rgba(0,0,0,.045); }
.category-header { width:100%; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:13px 15px 10px; border:0; background:transparent; color:inherit; text-align:left; cursor:pointer; }
.category-heading-copy { min-width:0; display:flex; flex-direction:column; gap:2px; }
.category-heading-copy strong { font-size:.9rem; font-weight:650; }
.category-heading-copy span,.category-heading-side { font-size:.7rem; color:rgba(var(--v-theme-on-surface),.56); }
.category-heading-side { display:flex; align-items:center; gap:8px; white-space:nowrap; }
.category-progress { height:2px; background:rgba(var(--v-theme-on-surface),.06); }
.category-progress span { display:block; height:100%; background:rgba(var(--v-theme-primary),.72); transition:width .2s ease; }
.product-table-wrap { overflow-x:auto; cursor:grab; overscroll-behavior-x:contain; }
.product-table-wrap:active { cursor:grabbing; }
.product-table { width:100%; min-width:610px; border-collapse:collapse; table-layout:fixed; font-size:.75rem; }
.product-column { width:180px; }
.number-column { width:68px; }
.product-table th,.product-table td { height:38px; padding:7px 6px; border-top:1px solid rgba(var(--v-border-color),.58); text-align:center; white-space:nowrap; font-weight:400; font-variant-numeric:tabular-nums; }
.product-table thead th { position:sticky; top:0; z-index:2; height:34px; background:rgb(var(--v-theme-surface)); color:rgba(var(--v-theme-on-surface),.58); font-size:.69rem; font-weight:550; }
.product-table th:first-child,.product-table td:first-child { position:sticky; left:0; z-index:3; width:180px; max-width:180px; text-align:left; background:rgb(var(--v-theme-surface)); }
.product-table thead th:first-child { z-index:4; }
.product-table tbody tr:hover td { background:rgb(var(--v-theme-surface-variant)); }
.product-table tbody tr:hover td:first-child { background:rgb(var(--v-theme-surface-variant)); }
.product-name { display:block; width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; appearance:none; border:0; padding:0; background:none; color:inherit; font-size:.76rem; font-weight:500; text-align:left; cursor:pointer; }
.table-value { min-width:30px; padding:5px 7px; border:0; border-radius:6px; background:transparent; color:inherit; font:inherit; cursor:pointer; }
.table-value:hover { background:rgba(var(--v-theme-on-surface),.06); }
.table-value.pending { color:rgba(var(--v-theme-on-surface),.42); }
.table-value.calculated { color:rgba(var(--v-theme-on-surface),.66); cursor:help; }
.table-value.attention { font-weight:650; color:rgb(var(--v-theme-error)); }
.rate-value { color:rgba(var(--v-theme-on-surface),.66); }
.row-warning { display:block; margin-top:1px; color:rgb(var(--v-theme-error)); font-size:.62rem; font-weight:600; }
.row-inactive { opacity:.5; }
.subtotal-row td { background:rgba(var(--v-theme-on-surface),.035); font-weight:600; }
.subtotal-row td:first-child { background:rgb(var(--v-theme-surface-variant)); }
.daily-actions { display:flex; align-items:center; padding-top:6px; }
.detail-text { font-family:inherit; white-space:pre-wrap; line-height:1.65; margin:0; }
.production-product-section { padding:20px 24px; }
.production-product-title { margin-bottom:12px; font-size:.88rem; font-weight:650; }
.product-detail-metrics { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
.product-detail-metrics>div { padding:10px; border-radius:9px; background:rgba(var(--v-theme-on-surface),.035); text-align:center; }
.product-detail-metrics span,.product-detail-metrics strong { display:block; }
.product-detail-metrics span { font-size:.68rem; color:rgba(var(--v-theme-on-surface),.56); }
.product-detail-metrics strong { margin-top:2px; font-size:.9rem; font-weight:600; }
.product-analysis-copy { margin:0; font-size:.78rem; line-height:1.65; color:rgba(var(--v-theme-on-surface),.72); }
.close-check-list { display:flex; flex-direction:column; gap:8px; }
.close-check-item { padding:12px; border:1px solid rgba(var(--v-border-color),.7); border-radius:10px; }
.close-check-copy { display:flex; flex-direction:column; gap:2px; }
.close-check-copy strong { font-size:.82rem; font-weight:600; }
.close-check-copy span { font-size:.7rem; color:rgba(var(--v-theme-on-surface),.58); }
.close-check-actions { display:flex; flex-wrap:wrap; gap:6px; margin-top:9px; }
.close-ready { padding:12px; border-radius:10px; background:rgba(var(--v-theme-success),.08); font-size:.78rem; font-weight:600; }
@media(max-width:760px) {
  .daily-section { padding:14px 0; }
  .daily-metrics { grid-template-columns:repeat(3,1fr); }
  .product-heading { align-items:stretch; flex-direction:column; }
  .product-search { max-width:none; }
  .product-column,.product-table th:first-child,.product-table td:first-child { width:132px; max-width:132px; }
  .number-column { width:60px; }
  .product-table { min-width:500px; font-size:.71rem; }
  .product-name { font-size:.72rem; }
  .product-detail-metrics { grid-template-columns:repeat(2,1fr); }
}
</style>
