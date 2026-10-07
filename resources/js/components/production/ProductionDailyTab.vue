<template>
<div class="daily-page">
  <section class="daily-section date-section">
    <div class="app-date-toolbar">
      <div class="app-date-navigation">
        <v-btn
            icon="mdi-chevron-left"
            variant="text"
            size="small"
            aria-label="이전 날짜"
            @click="moveDate(-1)"
        />
        <v-menu
            v-model="dateMenu"
            :close-on-content-click="false"
        >
          <template #activator="{ props: menuProps }">
            <button v-bind="menuProps" type="button" class="app-date-main">
              <span>{{ formatKoreanDate(workDate) }}</span>
              <small>{{ workDate === today ? '오늘 업무' : '선택 날짜 업무' }}</small>
            </button>
          </template>
          <v-date-picker
              :model-value="workDate"
              @update:model-value="selectPickerDate"
          />
        </v-menu>
        <v-btn
            icon="mdi-chevron-right"
            variant="text"
            size="small"
            aria-label="다음 날짜"
            @click="moveDate(1)"
        />
      </div>
      <div class="app-date-today-slot">
        <v-btn v-show="workDate !== today" size="small" variant="outlined" class="app-date-today" @click="emit('update:workDate', today)">오늘</v-btn>
      </div>
    </div>
  </section>

  <v-alert
      v-if="daily.blocking_previous_date"
      type="warning"
      variant="tonal"
      density="compact"
      class="previous-close-alert app-supporting-alert"
  >
    <div class="previous-close-copy">
      <strong>이전 업무 마감이 필요합니다</strong>
      <span>{{ daily.blocking_previous_date }} 업무를 먼저 마감해 주세요.</span>
    </div>
    <template #append>
      <v-btn
          size="small"
          variant="outlined"
          class="previous-close-action"
          @click="emit('update:workDate', daily.blocking_previous_date)"
      >
        {{ shortDateLabel(daily.blocking_previous_date) }}로 이동
      </v-btn>
    </template>
  </v-alert>

  <section class="daily-section summary-section">
    <div class="section-heading">
      <div>
        <h3>요약</h3>
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
        <div class="work-progress" aria-label="제품 기록 진행률"><span :style="{ width: `${progressPercent}%` }" /></div>
        <div v-if="missingSummaryText" class="missing-summary">{{ missingSummaryText }}</div>
        <div class="missing-type-grid">
          <button
            v-for="item in missingItems"
            :key="item.key"
            type="button"
            :class="['missing-type-button', { active: missingType === item.key, complete: item.count === 0 }]"
            @click="toggleMissingType(item.key)"
          >
            <span>{{ item.label }} 미확인</span>
            <strong>{{ item.count ? `${item.count}개` : '완료' }}</strong>
          </button>
        </div>
      </div>
      <div v-if="canMutate" class="product-heading-actions">
        <v-btn v-if="nextMissingRow" size="small" variant="outlined" class="missing-action-button" prepend-icon="mdi-skip-next" @click="openNextMissing">다음 미확인 제품</v-btn>
        <div v-else class="missing-complete-state">모든 제품 확인 완료</div>
      <v-menu location="bottom end">
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" size="small" variant="outlined" class="missing-action-button" prepend-icon="mdi-check-all" :disabled="!hasMissingItems">미확인 일괄 확인</v-btn>
        </template>
        <v-list
            class="bulk-menu"
            min-width="280"
        >
          <v-list-item
            v-for="item in missingItems"
            :key="item.key"
            :disabled="item.count === 0"
            @click="askBulkZero(item.key)"
          >
            <v-list-item-title>{{ item.label }} 없음(0)으로 확인 <span>{{ item.count ? `${item.count}개` : '완료' }}</span></v-list-item-title>
            <v-list-item-subtitle>아직 확인하지 않은 제품만 적용하며 기존 기록은 유지합니다.</v-list-item-subtitle>
          </v-list-item>
        </v-list>
      </v-menu>
      </div>
    </div>

    <v-text-field
        v-model="search"
        prepend-inner-icon="mdi-magnify"
        label="제품 검색"
        placeholder="제품명을 입력하세요"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="product-search mb-3"
    />

    <div class="product-filter-row mb-4">
      <v-btn-toggle
          v-model="filter"
          mandatory
          density="compact"
          variant="text"
          class="status-filter"
      >
        <v-btn value="all">전체</v-btn>
        <v-btn value="missing">기록 미완료</v-btn>
        <v-btn value="occurred">기록 발생</v-btn>
      </v-btn-toggle>
      <span class="app-supporting-text text-medium-emphasis">{{ filterResultLabel }}</span>
    </div>

    <section v-for="group in groupedRows" :key="group.name" class="category-block">
      <button type="button" class="category-header" @click="toggleCategory(group.name)">
        <div class="category-heading-copy">
          <strong>{{ group.name }}</strong>
          <span>{{ categoryProgressText(group.allRows) }}</span>
        </div>
        <div class="category-heading-side">
          <span>기록 완료 {{ categoryCompleteCount(group.allRows) }} / {{ group.allRows.filter((row) => row.is_active).length }}</span>
          <v-icon
              :icon="collapsed.has(group.name) ? 'mdi-chevron-down' : 'mdi-chevron-up'"
              size="small"
          />
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
              <col v-for="key in 5" :key="key" class="number-column" />
            </colgroup>
            <thead>
              <tr>
                <th>제품명</th><th>생산</th><th>이월</th><th>로스</th><th>폐기</th><th>폐기율</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in group.rows" :key="row.id" :class="{ 'row-inactive': !row.is_active, 'row-needs-check': !row.complete && row.is_active }">
                <td>
                  <button type="button" class="product-name" :title="row.name" @click="openProduct(row)">{{ row.name }}</button>
                </td>
                <td><button type="button" class="table-value production-value" :class="{ pending: !row.production_confirmed }" @click="openProduction(row)">{{ row.production_confirmed ? row.production : '-' }}</button></td>
                <td><button type="button" class="table-value" :class="{ pending: !row.disposition_confirmed }" @click="openFlow(row, 'carryover')">{{ row.disposition_confirmed ? row.carryover_in : '-' }}</button></td>
                <td><button type="button" class="table-value" :class="{ attention: row.loss > 0, pending: !row.loss_confirmed }" @click="openFlow(row, 'loss')">{{ row.loss_confirmed ? row.loss : '-' }}</button></td>
                <td><button type="button" class="table-value waste-value" :class="{ pending: !row.waste_confirmed }" @click="openFlow(row, 'waste')">{{ row.waste_confirmed ? row.waste : '-' }}</button></td>
                <td><button type="button" class="table-value rate-value" @click="openWasteRate(row)">{{ row.waste_rate === null ? '-' : `${row.waste_rate}%` }}</button></td>
              </tr>
              <tr class="subtotal-row">
                <td>소계</td>
                <td>{{ sum(group.rows, 'production') }}</td><td>{{ sum(group.rows, 'carryover_in') }}</td><td>{{ sum(group.rows, 'loss') }}</td><td>{{ sum(group.rows, 'waste') }}</td><td>{{ groupWasteRate(group.rows) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </v-expand-transition>
    </section>

    <v-empty-state
        v-if="!groupedRows.length"
        :title="emptyProductTitle"
        :text="emptyProductText"
        icon="mdi-bread-slice-outline"
    />
  </section>

  <div class="daily-actions">
    <v-btn variant="text" prepend-icon="mdi-history" @click="openHistory">변경 이력</v-btn>
    <v-spacer />
    <v-btn v-if="daily.closure_status === 'closed'" variant="outlined" :disabled="!canCorrect" @click="correctionOpen=true">마감 후 수정</v-btn>
    <v-btn v-else variant="flat" :disabled="daily.closure_status === 'store_closed' || !canMutate" @click="previewClose">마감</v-btn>
  </div>
  <ProductionBatchDialog
      v-model="batchOpen"
      :product="selectedProduct"
      :store-id="storeId"
      :work-date="workDate"
      :workers="options.workers || []"
      :zero-reasons="options.zero_reasons || []"
      @saved="handleSaved"
      @error="emit('error', $event)"
  />
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
      <template v-if="!productDetail?.recipes?.length">
        <v-divider />
        <section class="production-product-section empty-inline-state">
          <v-icon
              icon="mdi-book-open-variant-outline"
              size="20"
          />
          <div><strong>등록된 레시피가 없습니다.</strong><span>제품 관리에서 레시피를 등록하면 여기에서 확인할 수 있습니다.</span></div>
        </section>
      </template>
    </template>
  </ProductDetailDialog>

  <v-dialog
      v-model="detailOpen"
      max-width="680"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">{{ detailTitle }}</v-card-title>
      <v-card-text class="app-dialog-body">
        <div class="summary-detail-hero">
          <span>총 {{ detailMetricTitle }}</span>
          <strong>{{ detailMetricValue }}</strong>
          <small>{{ detailInsight }}</small>
        </div>
        <div class="summary-detail-facts">
          <div><span>기록 제품</span><strong>{{ detailRecordedCount }}개</strong></div>
          <div><span>확인 완료</span><strong>{{ detailConfirmedCount }} / {{ activeRows.length }}</strong></div>
          <div><span>미확인</span><strong>{{ Math.max(0, activeRows.length - detailConfirmedCount) }}개</strong></div>
          <div><span>기준 날짜</span><strong>{{ shortDateLabel(workDate) }}</strong></div>
        </div>
        <div v-if="detailMetricKey === 'waste_rate'" class="waste-analysis-note">
          <strong>폐기율 확인 기준</strong>
          <span>제품별 생산량과 폐기량을 기준으로 계산합니다. 폐기 미확인 제품은 0%로 단정하지 않습니다.</span>
        </div>
        <v-divider class="dialog-full-divider" />

        <div class="summary-detail-heading">제품별 {{ detailMetricTitle }}</div>
        <div v-if="detailRows.length" class="summary-detail-list">
          <button v-for="row in detailRows" :key="row.id" type="button" class="summary-detail-row" @click="openMetricProduct(row)">
            <span>
              <b>{{ row.name }}</b>
              <small>{{ row.share }} · {{ row.confirmed ? '확인 완료' : '미확인' }}</small>
              <small v-if="row.reasonSummary">{{ row.reasonSummary }}</small>
            </span>
            <strong>{{ row.value }}</strong>
          </button>
        </div>
        <div v-else class="summary-detail-empty">
          <strong>{{ detailEmptyTitle }}</strong>
          <span>{{ detailEmptyText }}</span>
        </div>

      </v-card-text>
      <v-card-actions class="app-dialog-footer">
        <v-btn variant="text" @click="detailOpen=false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog
      v-model="historyOpen"
      max-width="720"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">변경 이력</v-card-title>
      <v-card-text class="app-dialog-body history-body">
        <div class="history-context">{{ formatKoreanDate(workDate) }}의 생산·이월·로스·폐기 변경 기록입니다.</div>
        <v-divider class="app-section-divider" />
        <div v-if="historyLogs.length" class="history-list">
          <article v-for="log in historyLogs" :key="log.id" class="history-item">
            <div class="history-item-head">
              <strong>{{ log.description || '업무 기록 변경' }}</strong>
              <span class="history-action">{{ historyActionLabel(log.action) }}</span>
            </div>
            <div class="history-meta">{{ log.user?.name || '-' }} · {{ new Date(log.created_at).toLocaleString('ko-KR') }}</div>
          </article>
        </div>
        <v-empty-state
            v-else
            title="변경 이력이 없습니다."
            icon="mdi-history"
        />
      </v-card-text>
      <v-card-actions class="app-dialog-footer history-footer">
        <v-btn variant="text" @click="historyOpen=false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog
      v-model="closeOpen"
      max-width="720"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">마감 최종확인</v-card-title>
      <v-card-text class="app-dialog-body">
        <div class="app-supporting-text text-medium-emphasis mb-3">저장 전에 생산·이월·로스·폐기와 미확인 항목을 다시 확인합니다.</div>
        <div class="daily-metrics mb-4">
          <div v-for="metric in closeMetrics" :key="metric.key" class="metric-item static">
            <span>{{ metric.title }}</span>
            <strong>{{ metric.value }}</strong>
          </div>
        </div>
        <v-alert
            v-if="closePreview && !closePreview.can_close"
            type="warning"
            variant="tonal"
            density="compact"
            class="app-supporting-alert"
        >
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
      </v-card-text>
      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" @click="closeOpen=false">취소</v-btn>
        <v-spacer />
        <v-btn variant="flat" :disabled="!closePreview?.can_close" @click="confirmCloseOpen=true">마감</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog
      v-model="correctionOpen"
      max-width="560"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">마감 후 수정</v-card-title>
      <v-card-text class="app-dialog-body">
        <div class="app-supporting-text text-medium-emphasis mb-3">과거 기록을 수정하면 통계와 현재 분석이 다시 계산됩니다. 당시 추천 스냅샷은 변경하지 않습니다.</div>
        <v-textarea
            v-model="correctionReason"
            label="수정 사유"
            variant="outlined"
            rows="3"
        />
      </v-card-text>
      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" @click="correctionOpen=false">취소</v-btn>
        <v-spacer/>
        <v-btn variant="flat" :disabled="correctionReason.trim().length < 2" @click="correctionConfirmOpen=true">수정 시작</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <ConfirmDialog
      v-model="correctionConfirmOpen"
      title="마감 후 수정"
      message="마감된 기록의 수정을 시작하시겠습니까? 연결된 이월 기록이 있으면 영향 날짜도 함께 확인해야 합니다."
      @confirm="openCorrection"
  />
  <ConfirmDialog
      v-model="bulkZeroConfirmOpen"
      :title="`${bulkZeroLabel} 일괄 확인`"
      :message="`미확인 제품 ${bulkZeroCount}개를 없음(0)으로 확인합니다. 기존에 입력된 기록은 변경하지 않습니다.`"
      :loading="bulkZeroLoading"
      @confirm="bulkZero"
  />
  <ConfirmDialog
      v-model="confirmCloseOpen"
      title="마감 최종확인"
      message="현재 확인한 내용으로 하루 업무를 마감하시겠습니까? 마감 후 일반 수정은 제한됩니다."
      :loading="closing"
      @confirm="closeDay"
  />
</div>
</template>

<script setup>
import {
    computed,
    ref,
} from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import ProductDetailDialog from '../product/ProductDetailDialog.vue';
import ProductionBatchDialog from './ProductionBatchDialog.vue';
import ProductionFlowDialog from './ProductionFlowDialog.vue';
import {
    addLocalDays,
    formatKoreanDate,
    toLocalDateString,
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
const emit = defineEmits(['update:workDate','reload','replaceDaily','error','success']);
const today = toLocalDateString();
const dateMenu = ref(false);
const filter = ref('all');
const search = ref('');
const missingType = ref(null);
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
const detailMetricKey = ref('production');
const detailSelectedRow = ref(null);
const historyOpen = ref(false);
const historyLogs = ref([]);
const correctionOpen = ref(false);
const correctionConfirmOpen = ref(false);
const correctionReason = ref('');
const closeOpen = ref(false);
const confirmCloseOpen = ref(false);
const bulkZeroConfirmOpen = ref(false);
const bulkZeroType = ref('loss');
const bulkZeroLoading = ref(false);
const closePreview = ref(null);
const closing = ref(false);

const activeRows = computed(() => (props.daily.rows || []).filter((row) => row.is_active));
const missingLossCount = computed(() => activeRows.value.filter((row) => !row.loss_confirmed).length);
const missingWasteCount = computed(() => activeRows.value.filter((row) => !row.waste_confirmed).length);
const missingProductionCount = computed(() => activeRows.value.filter((row) => !row.production_confirmed).length);
const missingDispositionCount = computed(() => activeRows.value.filter((row) => !row.disposition_confirmed).length);
const bulkZeroItem = computed(() => missingItems.value.find((item) => item.key === bulkZeroType.value));
const bulkZeroLabel = computed(() => bulkZeroItem.value?.label || '기록');
const bulkZeroCount = computed(() => bulkZeroItem.value?.count || 0);
const progressPercent = computed(() => {
  const required = Number(props.daily.required_count || 0);
  return required ? Math.round((Number(props.daily.complete_count || 0) / required) * 100) : 100;
});
const missingItems = computed(() => [
  { key: 'production', label: '생산', count: missingProductionCount.value },
  { key: 'carryover', label: '이월', count: missingDispositionCount.value },
  { key: 'loss', label: '로스', count: missingLossCount.value },
  { key: 'waste', label: '폐기', count: missingWasteCount.value },
]);
const hasMissingItems = computed(() => missingItems.value.some((item) => item.count > 0));
const missingSummaryText = computed(() => (
  missingItems.value.some((item) => item.count > 0)
    ? '확인이 필요한 기록을 선택하면 해당 제품만 빠르게 확인할 수 있습니다.'
    : '모든 제품의 필수 기록이 확인되었습니다.'
));
const detailMetricTitle = computed(() => metrics.value.find((metric) => metric.key === detailMetricKey.value)?.title || '상세');
const detailMetricValue = computed(() => metrics.value.find((metric) => metric.key === detailMetricKey.value)?.value ?? '-');
const detailRows = computed(() => {
  const key = detailMetricKey.value === 'carryover' ? 'carryover_in' : detailMetricKey.value;
  const total = detailMetricKey.value === 'waste_rate'
    ? activeRows.value.reduce((sum, row) => sum + Number(row.waste || 0), 0)
    : activeRows.value.reduce((sum, row) => sum + Number(row[key] || 0), 0);

  return activeRows.value.map((row) => {
    const rawValue = detailMetricKey.value === 'waste_rate' ? Number(row.waste_rate || 0) : Number(row[key] || 0);
    const shareBase = detailMetricKey.value === 'waste_rate' ? Number(row.waste || 0) : rawValue;
    return {
      id: row.id,
      name: row.name,
      source: row,
      rawValue,
      value: detailMetricKey.value === 'waste_rate' ? `${rawValue}%` : rawValue,
      share: total > 0 && shareBase > 0 ? `전체의 ${(shareBase / total * 100).toFixed(1)}%` : '기록 확인',
      confirmed: Boolean(row[detailConfirmationField.value]),
      reasonSummary: detailMetricKey.value === 'waste_rate'
        ? reasonSummary(row.waste_details, '폐기 사유')
        : detailMetricKey.value === 'waste'
          ? reasonSummary(row.waste_details, '폐기 사유')
          : detailMetricKey.value === 'loss'
            ? reasonSummary(row.loss_details, '로스 사유')
            : '',
    };
  }).filter((row) => row.rawValue > 0).sort((a, b) => b.rawValue - a.rawValue);
});
const detailRecordedCount = computed(() => detailRows.value.length);
const detailConfirmationField = computed(() => ({
  production: 'production_confirmed',
  carryover: 'disposition_confirmed',
  loss: 'loss_confirmed',
  waste: 'waste_confirmed',
  waste_rate: 'waste_confirmed',
}[detailMetricKey.value]));
const detailConfirmedCount = computed(() => activeRows.value.filter((row) => row[detailConfirmationField.value]).length);
const detailInsight = computed(() => {
  if (!detailRows.value.length) return `${detailConfirmedCount.value}개 제품이 확인을 완료했습니다.`;
  const top = detailRows.value[0];
  return `${top.name}이(가) 가장 높습니다 · ${top.value}`;
});
const detailEmptyTitle = computed(() => `이 날짜에는 ${detailMetricTitle.value} 기록이 없습니다.`);
const detailEmptyText = computed(() => detailConfirmedCount.value === activeRows.value.length
  ? '모든 제품이 없음(0)으로 확인된 상태입니다.'
  : `아직 확인하지 않은 제품이 ${activeRows.value.length - detailConfirmedCount.value}개 있습니다.`);

const metrics = computed(() => [ {
  key:'production', title:'생산', value: props.daily.totals?.production || 0
}, {
  key:'carryover', title:'이월', value: props.daily.totals?.carryover || 0
}, {
  key:'loss', title:'로스', value: props.daily.totals?.loss || 0
}, {
  key:'waste', title:'폐기', value: props.daily.totals?.waste || 0
}, {
  key:'waste_rate', title:'폐기율', value: props.daily.totals?.waste_rate == null ? '-' : `${props.daily.totals.waste_rate}%`
}, ]);

// 마감 다이얼로그는 목록의 이전 상태가 아니라 서버에서 다시 받은 최종 점검 수치를 사용합니다.
const closeMetrics = computed(() => {
  const source = closePreview.value?.daily?.totals || props.daily.totals || {};
  return [
    { key: 'production', title: '생산', value: source.production || 0 },
    { key: 'carryover', title: '이월', value: source.carryover || 0 },
    { key: 'loss', title: '로스', value: source.loss || 0 },
    { key: 'waste', title: '폐기', value: source.waste || 0 },
    { key: 'waste_rate', title: '폐기율', value: source.waste_rate == null ? '-' : `${source.waste_rate}%` },
  ];
});
const filteredRows = computed(() => (props.daily.rows || []).filter((row) => {
  const q = search.value?.trim().toLocaleLowerCase('ko-KR');

  if (q && !row.name.toLocaleLowerCase('ko-KR').includes(q)) {
    return false;
  }

  if (missingType.value) {
    const field = {
      production: 'production_confirmed',
      carryover: 'disposition_confirmed',
      loss: 'loss_confirmed',
      waste: 'waste_confirmed',
    }[missingType.value];
    if (field && row[field]) return false;
  }

  if (filter.value === 'missing') {
    return !row.complete;
  }

  if (filter.value === 'occurred') {
    return row.loss > 0 || row.waste > 0;
  }

  return true;
}));

const nextMissingRow = computed(() => activeRows.value.find((row) => !row.complete) || null);

const filterResultLabel = computed(() => {
  if (search.value?.trim()) return `검색 결과 ${filteredRows.value.length}개`;
  if (missingType.value) {
    const label = missingItems.value.find((item) => item.key === missingType.value)?.label || '기록';
    return `${label} 미확인 · ${filteredRows.value.length}개`;
  }
  if (filter.value === 'missing') return `확인이 필요한 제품 ${filteredRows.value.length}개`;
  if (filter.value === 'occurred') return `로스·폐기가 발생한 제품 ${filteredRows.value.length}개`;
  return `전체 제품 ${filteredRows.value.length}개`;
});
const emptyProductTitle = computed(() => search.value?.trim() ? '검색 결과가 없습니다.' : '표시할 제품이 없습니다.');
const emptyProductText = computed(() => missingType.value ? '선택한 항목은 모두 확인되었습니다. 전체 목록으로 돌아가 다른 기록을 확인할 수 있습니다.' : '검색어나 필터를 변경해 주세요.');
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
  return remaining > 0
    ? `전체 ${required}개 중 ${remaining}개 제품의 확인이 필요합니다.`
    : `전체 ${required}개 제품의 기록이 확인되었습니다.`;
});

const selectedProductMetrics = computed(() => {
  const row = selectedProduct.value;
  if (!row) return [];
  return [
    { label: '생산', value: row.production },
    { label: '이월', value: row.carryover_in },
    { label: '로스', value: row.loss },
    { label: '폐기', value: row.waste },
    { label: '폐기율', value: row.waste_rate == null ? '-' : `${row.waste_rate}%` },
  ];
});

const selectedProductAnalysis = computed(() => {
  const row = selectedProduct.value;
  if (!row) return '-';
  if (!row.complete) return missingReasonText(row);
  if (row.waste > 0 || row.loss > 0) return `로스 ${row.loss}, 폐기 ${row.waste}가 기록되어 있습니다. 필요하면 원인과 수량을 다시 확인해 주세요.`;
  return '선택 날짜의 필수 확인이 모두 완료되었고 수량 흐름도 정상입니다.';
});


// 사유별 기록을 상세 목록에서 짧게 읽을 수 있도록 요약합니다.
function reasonSummary(details, label) {
  if (!Array.isArray(details) || !details.length) return '';
  const names = details.slice(0, 2).map((item) => item.reason_text || item.reason_code || '기타');
  return `${label}: ${names.join(' · ')}${details.length > 2 ? ` 외 ${details.length - 2}건` : ''}`;
}

// 제품별 확인 상태를 실제 미확인 항목 이름으로 설명합니다.
function missingReasonText(row) {
  const missing = [];
  if (!row.production_confirmed) missing.push('생산');
  if (!row.loss_confirmed) missing.push('로스');
  if (!row.waste_confirmed) missing.push('폐기');
  if (!row.disposition_confirmed) missing.push('이월');
  return missing.length ? `${missing.join(' · ')} 확인이 필요합니다.` : '확인이 필요한 항목이 있습니다.';
}

// 현황의 미확인 종류를 누르면 해당 제품만 표에 남깁니다.
function toggleMissingType(type) {
  missingType.value = missingType.value === type ? null : type;
  if (missingType.value) filter.value = 'missing';
}

// 경고 버튼에서 연도 없이 월/일만 간결하게 표시합니다.
function shortDateLabel(date) {
  const [, month, day] = String(date).split('-');
  return `${Number(month)}월 ${Number(day)}일`;
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

// 날짜 화살표로 하루씩 이동합니다.
function moveDate(amount) {
  emit('update:workDate', addLocalDays(props.workDate, amount));
}
// Vuetify 날짜 선택값을 YYYY-MM-DD로 정규화합니다.
function selectPickerDate(value) {
  const date = value instanceof Date ? toLocalDateString(value) : String(value).slice(0,10);
  emit('update:workDate', date);
  dateMenu.value=false;
}
// 카테고리 접기 상태를 화면 내부에서만 변경합니다.
function toggleCategory(name) {
  const next = new Set(collapsed.value);
  next.has(name) ? next.delete(name) : next.add(name);
  collapsed.value = next;
}
// 카테고리 소계를 계산합니다.
function sum(rows,key) {
  return rows.reduce((total,row)=>total+Number(row[key]||0),0);
}
// 권한 또는 이전 날짜 미마감으로 수정할 수 없는 상태를 공통 안내합니다.
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
// 데스크톱에서 표의 빈 영역을 잡아 좌우로 빠르게 이동할 수 있게 합니다.
function startTableDrag(event) {
  if (event.pointerType === 'touch' || event.target.closest('button, .v-chip, a, input')) return;

  tableDrag.active = true;
  tableDrag.startX = event.clientX;
  tableDrag.startScrollLeft = event.currentTarget.scrollLeft;
  tableDrag.element = event.currentTarget;
  event.currentTarget.setPointerCapture?.(event.pointerId);
}

// 드래그한 거리만큼 제품 표의 가로 스크롤 위치를 갱신합니다.
function moveTableDrag(event) {
  if (!tableDrag.active || !tableDrag.element) return;

  tableDrag.element.scrollLeft = tableDrag.startScrollLeft - (event.clientX - tableDrag.startX);
}

// 포인터가 끝나면 표 드래그 상태를 정리합니다.
function endTableDrag() {
  tableDrag.active = false;
  tableDrag.element = null;
}

// 가장 먼저 남아 있는 미확인 항목을 열어 마감 전 연속 확인 동선을 줄입니다.
function openNextMissing() {
  const row = nextMissingRow.value;
  if (!row) return;

  if (!row.production_confirmed) return openProduction(row);
  if (!row.loss_confirmed) return openFlow(row, 'loss');
  if (!row.waste_confirmed) return openFlow(row, 'waste');
  if (!row.disposition_confirmed) return openFlow(row, 'carryover');
}

// 생산 수량을 확인하거나 기록할 수 있는 생산 다이얼로그를 엽니다.
function openProduction(row) {
  if (!ensureMutable()) return;
  selectedProduct.value=row;
  batchOpen.value=true;
}
// 선택한 이월·로스·폐기 업무 다이얼로그를 엽니다.
function openFlow(row, type) {
  if (!ensureMutable()) return;

  selectedProduct.value = row;
  flowType.value = type;
  flowOpen.value = true;
}
// 제품 관리의 상세 데이터를 재사용해 레시피와 선택 날짜 생산 현황을 함께 보여줍니다.
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
// 요약 지표를 누르면 같은 디자인의 제품별 상세 내역을 보여줍니다.
function openMetric(key) {
  detailSelectedRow.value = null;
  detailMetricKey.value = key;
  detailTitle.value = `${metrics.value.find((metric) => metric.key === key)?.title || '상세'} 상세`;
  detailOpen.value = true;
}
// 폐기율 숫자는 같은 요약 상세에서 제품별 폐기율과 비중을 분석합니다.
function openWasteRate(row) {
  detailSelectedRow.value = row;
  detailMetricKey.value = 'waste_rate';
  detailTitle.value = `${row.name} 폐기 분석`;
  detailOpen.value = true;
}
// 요약 상세의 제품을 누르면 기존 제품 상세를 재사용해 더 깊은 기록을 확인합니다.
function openMetricProduct(item) {
  if (!item?.source) return;
  detailOpen.value = false;
  openProduct(item.source);
}
// 아직 확인하지 않은 생산·이월·로스·폐기만 0개 상태로 일괄 확인합니다.
function askBulkZero(type) {
  if (!ensureMutable()) return;
  bulkZeroType.value = type;
  bulkZeroConfirmOpen.value = true;
}
// 확인창에서 선택한 미확인 항목을 0개 상태로 일괄 확인합니다.
async function bulkZero() {
  const type = bulkZeroType.value;
  if (!ensureMutable() || bulkZeroLoading.value) return;
  bulkZeroLoading.value = true;
  try {
    const {
      data
    } = await window.axios.post('/tillwhite/api/production-management/bulk-zero', {
      store_id: props.storeId, work_date: props.workDate, type
    });
    bulkZeroConfirmOpen.value = false;
    if (data.daily) emit('replaceDaily', data.daily);
    emit('success', data.message);
    emit('reload');
  } catch (error) {
    emit('error', error.response?.data?.message || '일괄 확인 중 오류가 발생했습니다.');
  } finally {
    bulkZeroLoading.value = false;
  }
}
// 하위 다이얼로그 저장 성공 후 최신 일일 데이터를 다시 조회합니다.
function handleSaved(message, freshDaily = null) {
  if (freshDaily) emit('replaceDaily', freshDaily);
  emit('success', message);
  emit('reload');

  // 마감 점검 중 입력했다면 마감창을 유지한 채 서버 기준 상태만 다시 계산합니다.
  if (closeOpen.value) {
    refreshClosePreview();
  }
}
// 감사 로그의 내부 action 값을 직원이 이해하기 쉬운 상태명으로 바꿉니다.
function historyActionLabel(action) {
  return {
    create: '등록',
    update: '수정',
    delete: '삭제',
    confirm: '확인',
    close: '마감',
    correction_open: '수정 시작',
  }[action] || '변경';
}

// 선택 날짜의 감사 로그를 불러와 일일 변경 이력 다이얼로그를 엽니다.
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
// 관리자 권한과 필수 사유를 확인한 뒤 마감된 날짜를 수정 상태로 엽니다.
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
// 마감창을 닫지 않고 최신 미확인 제품과 마감 가능 여부만 다시 계산합니다.
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

// 서버에서 마감 가능 여부를 다시 계산해 최종 확인 다이얼로그를 엽니다.
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
// 최종 확인 뒤 서버 마감을 실행하고 성공한 경우에만 완료 상태를 반영합니다.
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
.daily-page {
    display:flex;
    flex-direction:column;
    gap:0;
}
.daily-section {
    padding:18px 0;
}
.section-heading {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    margin-bottom:14px;
}
.section-heading h3 {
    margin:0;
    font-size:.98rem;
    font-weight:650;
    letter-spacing:-.02em;
}
.section-heading p {
    margin:4px 0 0;
    font-size:.76rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.work-progress {
    width:100%;
    height:4px;
    margin-top:8px;
    overflow:hidden;
    border-radius:999px;
    background:rgba(var(--v-theme-on-surface),.07);
}
.work-progress span {
    display:block;
    height:100%;
    border-radius:inherit;
    background:rgb(var(--v-theme-primary));
}
.missing-summary {
    margin-top:6px;
    font-size:.7rem;
    color:rgba(var(--v-theme-on-surface),.62);
}
.bulk-menu :deep(.v-list-item-title) {
    display:flex;
    justify-content:space-between;
    gap:16px;
    font-size:.8rem;
    font-weight:650;
}
.bulk-menu :deep(.v-list-item-subtitle) {
    margin-top:3px;
    font-size:.67rem;
    line-height:1.4;
    white-space:normal;
}
.daily-metrics {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:9px;
}
.metric-item {
    appearance:none;
    text-align:center;
    padding:12px 8px;
    border:1px solid rgba(var(--v-border-color),.7);
    border-radius:12px;
    background:rgb(var(--v-theme-surface));
    color:inherit;
    cursor:pointer;
    box-shadow:0 2px 8px rgba(0,0,0,.055);
    transition:transform .15s ease,box-shadow .15s ease;
}
.metric-item:hover {
    transform:translateY(-1px);
    box-shadow:0 4px 12px rgba(0,0,0,.08);
}
.metric-item.static {
    cursor:default;
}
.metric-item span,.metric-item strong {
    display:block;
}
.metric-item span {
    font-size:.7rem;
    font-weight:500;
    color:rgba(var(--v-theme-on-surface),.58);
}
.metric-item strong {
    margin-top:3px;
    font-size:1.08rem;
    font-weight:650;
    font-variant-numeric:tabular-nums;
}
.product-search {
    max-width:360px;
}
.product-heading-actions {
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:7px;
}
.missing-action-button {
    min-height:34px;
    font-weight:650;
}
.missing-complete-state {
    min-height:34px;
    display:flex;
    align-items:center;
    padding:0 10px;
    font-size:.72rem;
    font-weight:650;
    color:rgba(var(--v-theme-on-surface),.62);
}
.product-filter-row {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}
.status-filter {
    border-bottom:1px solid rgba(var(--v-border-color),.65);
    border-radius:0;
}
.status-filter :deep(.v-btn) {
    min-width:auto;
    padding-inline:12px;
    font-size:.76rem;
    font-weight:500;
}
.category-block {
    margin-bottom:18px;
    border:1px solid rgba(var(--v-border-color),.72);
    border-radius:12px;
    overflow:hidden;
    background:rgb(var(--v-theme-surface));
    box-shadow:0 2px 9px rgba(0,0,0,.045);
}
.category-header {
    width:100%;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:13px 15px 10px;
    border:0;
    background:transparent;
    color:inherit;
    text-align:left;
    cursor:pointer;
}
.category-heading-copy {
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:2px;
}
.category-heading-copy strong {
    font-size:.9rem;
    font-weight:650;
}
.category-heading-copy span,.category-heading-side {
    font-size:.7rem;
    color:rgba(var(--v-theme-on-surface),.56);
}
.category-heading-side {
    display:flex;
    align-items:center;
    gap:8px;
    white-space:nowrap;
}
.category-progress {
    height:2px;
    background:rgba(var(--v-theme-on-surface),.06);
}
.category-progress span {
    display:block;
    height:100%;
    background:rgba(var(--v-theme-primary),.72);
    transition:width .2s ease;
}
.product-table-wrap {
    overflow-x:auto;
    cursor:grab;
    overscroll-behavior-x:contain;
}
.product-table-wrap:active {
    cursor:grabbing;
}
.product-table {
    width:100%;
    min-width:0;
    border-collapse:collapse;
    table-layout:fixed;
    font-size:.75rem;
}
.product-column {
    width:34%;
}
.number-column {
    width:13.2%;
}
.product-table th,.product-table td {
    height:34px;
    padding:5px 4px;
    border-top:1px solid rgba(var(--v-border-color),.58);
    text-align:center;
    white-space:nowrap;
    font-weight:400;
    font-variant-numeric:tabular-nums;
}
.product-table thead th {
    position:sticky;
    top:0;
    z-index:2;
    height:34px;
    background:rgb(var(--v-theme-surface));
    color:rgba(var(--v-theme-on-surface),.58);
    font-size:.69rem;
    font-weight:550;
}
.product-table th:first-child,.product-table td:first-child {
    position:sticky;
    left:0;
    z-index:3;
    width:34%;
    max-width:34%;
    text-align:left;
    background:rgb(var(--v-theme-surface));
}
.product-table thead th:first-child {
    z-index:4;
}
.product-table tbody tr:hover td {
    background:rgb(var(--v-theme-surface-variant));
}
.product-table tbody tr:hover td:first-child {
    background:rgb(var(--v-theme-surface-variant));
}
.product-table th:first-child,.product-table td:first-child {
    padding-left:.65rem;
    border-right:1px solid rgba(var(--v-border-color),.38);
}
.product-name {
    display:block;
    width:100%;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    appearance:none;
    border:0;
    padding:0;
    background:none;
    color:inherit;
    font-size:.76rem;
    font-weight:500;
    text-align:left;
    cursor:pointer;
}
.table-value {
    min-width:30px;
    padding:5px 7px;
    border:0;
    border-radius:6px;
    background:transparent;
    color:inherit;
    font:inherit;
    cursor:pointer;
}
.table-value:hover {
    background:rgba(var(--v-theme-on-surface),.06);
}
.table-value.pending {
    color:rgba(var(--v-theme-on-surface),.42);
}
.table-value.calculated {
    color:rgba(var(--v-theme-on-surface),.66);
    cursor:help;
}
.table-value.attention {
    font-weight:650;
    color:rgb(var(--v-theme-error));
}
.rate-value {
    color:rgba(var(--v-theme-on-surface),.66);
}
.row-warning {
    display:block;
    margin-top:1px;
    color:rgb(var(--v-theme-error));
    font-size:.62rem;
    font-weight:600;
}
.row-inactive {
    opacity:.5;
}
.subtotal-row td {
    background:rgba(var(--v-theme-on-surface),.055) !important;
    color:rgb(var(--v-theme-on-surface)) !important;
    font-weight:650;
}
.subtotal-row:hover td {
    background:rgba(var(--v-theme-on-surface),.055) !important;
}
.subtotal-row td:first-child {
    background:rgba(var(--v-theme-on-surface),.075) !important;
}
.daily-actions {
    display:flex;
    align-items:center;
    padding-top:6px;
}
.summary-detail-hero {
    padding:4px 0 16px;
}
.summary-detail-hero span,.summary-detail-hero strong {
    display:block;
}
.summary-detail-hero span {
    font-size:.72rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.summary-detail-hero strong {
    margin-top:3px;
    font-size:1.7rem;
    font-weight:700;
    font-variant-numeric:tabular-nums;
}
.summary-detail-hero small {
    display:block;
    margin-top:6px;
    font-size:.7rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.summary-detail-facts {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:8px;
    margin-bottom:16px;
}
.summary-detail-facts>div {
    padding:9px 8px;
    border-radius:9px;
    background:rgba(var(--v-theme-on-surface),.04);
    text-align:center;
}
.summary-detail-facts span,.summary-detail-facts strong {
    display:block;
}
.summary-detail-facts span {
    font-size:.64rem;
    color:rgba(var(--v-theme-on-surface),.55);
}
.summary-detail-facts strong {
    margin-top:2px;
    font-size:.78rem;
    font-weight:650;
}
.waste-analysis-note {
    display:flex;
    flex-direction:column;
    gap:3px;
    margin:-4px 0 14px;
    padding:9px 10px;
    border-radius:9px;
    background:rgba(var(--v-theme-on-surface),.04);
    font-size:.68rem;
}
.waste-analysis-note span {
    color:rgba(var(--v-theme-on-surface),.58);
    line-height:1.45;
}
.summary-detail-meta {
    margin-bottom:16px;
    font-size:.7rem;
    color:rgba(var(--v-theme-on-surface),.55);
}
.dialog-full-divider {
    margin-inline:-24px;
}
.summary-detail-heading {
    padding:16px 0 8px;
    font-size:.8rem;
    font-weight:650;
}
.summary-detail-list {
    display:flex;
    flex-direction:column;
}
.summary-detail-row {
    width:100%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    padding:9px 2px;
    border:0;
    border-bottom:1px solid rgba(var(--v-border-color),.55);
    background:transparent;
    color:inherit;
    text-align:left;
    font-size:.76rem;
    cursor:pointer;
}
.summary-detail-row>span {
    display:flex;
    flex-direction:column;
    min-width:0;
}
.summary-detail-row small {
    margin-top:2px;
    font-size:.63rem;
    color:rgba(var(--v-theme-on-surface),.5);
}
.summary-detail-row:hover {
    background:rgba(var(--v-theme-on-surface),.035);
}
.summary-detail-row strong {
    font-variant-numeric:tabular-nums;
}
.summary-detail-empty {
    padding:18px 0;
    font-size:.75rem;
    color:rgba(var(--v-theme-on-surface),.55);
}
.summary-detail-empty strong,.summary-detail-empty span {
    display:block;
}
.summary-detail-empty strong {
    color:rgb(var(--v-theme-on-surface));
}
.summary-detail-empty span {
    margin-top:3px;
    font-size:.68rem;
}
.production-product-section {
    padding:20px 24px;
}
.production-product-title {
    margin-bottom:12px;
    font-size:.88rem;
    font-weight:650;
}
.product-detail-metrics {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:8px;
}
.product-detail-metrics>div {
    padding:10px;
    border-radius:9px;
    background:rgba(var(--v-theme-on-surface),.035);
    text-align:center;
}
.product-detail-metrics span,.product-detail-metrics strong {
    display:block;
}
.product-detail-metrics span {
    font-size:.68rem;
    color:rgba(var(--v-theme-on-surface),.56);
}
.product-detail-metrics strong {
    margin-top:2px;
    font-size:.9rem;
    font-weight:600;
}
.product-analysis-copy {
    margin:0;
    font-size:.78rem;
    line-height:1.65;
    color:rgba(var(--v-theme-on-surface),.72);
}
.history-context {
    padding:2px 0 14px;
    font-size:.72rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.history-list {
    display:flex;
    flex-direction:column;
}
.history-item {
    padding:12px 0;
    border-bottom:1px solid rgba(var(--v-border-color),.52);
}
.history-item-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.history-item-head strong {
    min-width:0;
    font-size:.8rem;
    font-weight:650;
}
.history-action {
    flex:none;
    padding:3px 7px;
    border-radius:999px;
    background:rgba(var(--v-theme-on-surface),.06);
    font-size:.62rem;
    font-weight:650;
}
.history-meta {
    margin-top:4px;
    font-size:.67rem;
    color:rgba(var(--v-theme-on-surface),.55);
}
.close-check-list {
    display:flex;
    flex-direction:column;
    gap:8px;
}
.close-check-item {
    padding:12px;
    border:1px solid rgba(var(--v-border-color),.7);
    border-radius:10px;
}
.close-check-copy {
    display:flex;
    flex-direction:column;
    gap:2px;
}
.close-check-copy strong {
    font-size:.82rem;
    font-weight:600;
}
.close-check-copy span {
    font-size:.7rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.close-check-actions {
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:9px;
}
.close-ready {
    padding:12px;
    border-radius:10px;
    background:rgba(var(--v-theme-success),.08);
    font-size:.78rem;
    font-weight:600;
}

.previous-close-alert {
    margin:2px 0 8px;
}
.previous-close-copy {
    display:flex;
    flex-direction:column;
    gap:2px;
}
.previous-close-copy strong {
    font-size:.78rem;
}
.previous-close-copy span {
    font-size:.69rem;
    line-height:1.4;
}
.previous-close-action {
    min-height:38px;
    font-weight:650;
}
.summary-section {
    padding-top:10px;
}
.daily-metrics {
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:7px;
}
.metric-item {
    padding:10px 6px;
}
.missing-type-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:6px;
    margin-top:10px;
}
.missing-type-button {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:6px;
    min-height:34px;
    padding:6px 8px;
    border:1px solid rgba(var(--v-border-color),.65);
    border-radius:8px;
    background:transparent;
    color:inherit;
    cursor:pointer;
}
.missing-type-button span {
    font-size:.67rem;
    color:rgba(var(--v-theme-on-surface),.58);
}
.missing-type-button strong {
    font-size:.7rem;
    font-weight:700;
    font-variant-numeric:tabular-nums;
}
.missing-type-button.active {
    border-color:rgba(var(--v-theme-primary),.55);
    background:rgba(var(--v-theme-primary),.07);
}
.missing-type-button.complete {
    opacity:.58;
}
.history-footer {
    justify-content:flex-start;
}
.status-filter {
    width:100%;
    display:grid;
    grid-template-columns:repeat(3,1fr);
    border-bottom:1px solid rgba(var(--v-border-color),.65);
}
.status-filter :deep(.v-btn) {
    border-radius:0;
}
.status-filter :deep(.v-btn--active) {
    border-bottom:2px solid rgb(var(--v-theme-primary));
}
.product-filter-row {
    align-items:flex-end;
    flex-wrap:wrap;
}
.product-filter-row > span {
    margin-left:auto;
}
.product-table {
    min-width:100%;
    font-size:.7rem;
}
.product-column {
    width:34%;
}
.number-column {
    width:13.2%;
}
.product-table th,.product-table td {
    height:31px;
    padding:3px 2px;
}
.product-table tbody tr:hover td,.product-table tbody tr:hover td:first-child {
    background:rgba(var(--v-theme-on-surface),.035);
}
.table-value {
    min-width:30px;
    padding:4px 6px;
    border:1px solid transparent;
    border-radius:999px;
    background:rgba(var(--v-theme-on-surface),.045);
}
.table-value.production-value {
    background:rgba(76,175,80,.12);
    color:rgb(46,125,50);
}
.table-value.waste-value {
    background:rgba(239,83,80,.11);
    color:rgb(198,40,40);
}
.table-value.pending {
    background:rgba(var(--v-theme-on-surface),.035);
    color:rgba(var(--v-theme-on-surface),.42);
}
.rate-value {
    font-weight:650;
}
@media(max-width:760px) {
  .daily-section {
      padding:14px 0;
  }
  .daily-metrics {
      grid-template-columns:repeat(6,1fr);
  }
  .daily-metrics .metric-item {
      grid-column:span 2;
  }
  .daily-metrics .metric-item:nth-child(4),.daily-metrics .metric-item:nth-child(5) {
      grid-column:span 3;
  }
  .missing-type-grid {
      grid-template-columns:repeat(2,1fr);
  }
  .previous-close-alert :deep(.v-alert__content) {
      min-width:0;
  }
  .previous-close-alert :deep(.v-alert__append) {
      margin-inline-start:8px;
  }
  .product-heading {
      align-items:stretch;
      flex-direction:column;
  }
  .product-search {
      max-width:none;
  }
  .product-column,.product-table th:first-child,.product-table td:first-child {
      width:34%;
      max-width:34%;
  }
  .number-column {
      width:13.2%;
  }
  .product-table {
      min-width:100%;
      font-size:.64rem;
  }
  .product-table th,.product-table td {
      height:32px;
      padding:4px 3px;
  }
  .product-name {
      font-size:.72rem;
  }
  .product-detail-metrics {
      grid-template-columns:repeat(2,1fr);
  }
  .summary-detail-facts {
      grid-template-columns:repeat(2,1fr);
  }
}
</style>
