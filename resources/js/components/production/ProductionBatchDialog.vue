<template>
<v-dialog
    v-model="open"
    max-width="620"
    persistent
>
  <v-card
      rounded="lg"
      class="app-dialog-card production-dialog-card"
  >
    <v-card-title class="app-dialog-header d-flex align-center justify-space-between">
      <div>{{ editingBatch ? '생산 기록 수정' : '생산' }} - {{ product?.name || '-' }}</div>
    </v-card-title>
    <v-card-text class="app-dialog-body">
      <div class="production-dialog-overview">
        <div><span>현재 생산</span><strong>{{ Number(product?.production || 0) }}개</strong></div>
        <div><span>생산 기록</span><strong>{{ product?.batches?.length || 0 }}건</strong></div>
        <div><span>확인 상태</span><strong>{{ product?.production_confirmed ? '완료' : '미확인' }}</strong></div>
      </div>
      <div class="production-dialog-guide overview-guide">생산 기록을 확인하고 새 생산량을 추가하거나 기존 기록을 수정할 수 있습니다.</div>
      <section class="production-record-section">
        <div class="production-dialog-section-title">오늘 생산 기록</div>
        <div v-if="!product?.batches?.length" class="production-friendly-empty">
          <v-icon icon="mdi-clipboard-text-outline" size="22" />
          <div><strong>아직 생산 기록이 없습니다.</strong><span>아래에서 첫 생산 기록을 등록해 주세요.</span></div>
        </div>
        <div v-for="batch in product?.batches || []" :key="batch.id" class="production-record-card" :class="{ 'record-editing': editingBatch?.id === batch.id }">
          <div class="production-record-main">
            <strong>{{ batch.quantity }}개</strong>
            <span>{{ new Date(batch.created_at).toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' }) }}</span>
          </div>
          <div class="production-record-workers">작업자: {{ batch.workers?.map(worker => worker.name).join(', ') || '기록 없음' }}</div>
          <div v-if="batch.note" class="production-record-note">{{ batch.note }}</div>
          <div class="production-record-actions">
            <v-btn size="small" variant="text" prepend-icon="mdi-pencil-outline" @click="editBatch(batch)">수정</v-btn>
            <v-btn size="small" variant="text" prepend-icon="mdi-delete-outline" @click="askDelete(batch)">삭제</v-btn>
          </div>
        </div>
      </section>
      <v-divider class="my-4" />
      <div ref="editorSection" class="production-record-editor">
        <!-- 기존 생산 기록 수정 시 간결한 안내를 표시합니다. -->
        <v-alert
          v-if="editingBatch"
          type="info"
          variant="tonal"
          density="compact"
          icon="mdi-information-outline"
          class="app-supporting-alert mb-3"
        >
          <div class="font-weight-medium">
            수정 중인 기록 · {{ editingBatch.quantity }}개
          </div>

          <div class="mt-1">
            기존 값을 변경한 후 수정 내용을 저장해 주세요.
          </div>
        </v-alert>
      <div class="production-dialog-section-title">
        {{ editingBatch ? '생산 기록 수정' : '새 생산 기록' }}
      </div>

      <div class="production-dialog-guide">
        오늘 생산한 수량을 입력해 주세요.
      </div>

      <!-- 전날 생산 기록을 참고하는 별도 영역 -->
      <section v-if="!editingBatch" class="previous-production-reference">
        <div class="previous-production-reference__content">
          <span class="previous-production-reference__label">
            전날 생산량 참고
          </span>

          <strong v-if="previousProductionLoading">
            조회 중...
          </strong>

          <strong v-else-if="previousProduction !== null">
            {{ previousProduction }}개
          </strong>

          <strong v-else>
            기록 없음
          </strong>
        </div>

        <v-btn
          size="small"
          variant="outlined"
          :disabled="previousProductionLoading || previousProduction === null || saving"
          @click="applyPreviousProduction"
        >
          불러오기
        </v-btn>
      </section>

      <v-number-input
          v-model="form.quantity"
          label="생산 수량"
          variant="outlined"
          density="compact"
          :min="1"
          class="primary-quantity-input"
      />
      <v-select
        v-model="form.workerIds"
        :items="workers"
        item-title="name"
        item-value="id"
        label="작업자 · 필수"
        :error-messages="workerError ? [workerError] : []"
        @update:model-value="workerError = ''"
        variant="outlined"
        multiple
        chips
        clearable
        no-data-text="선택할 수 있는 작업자가 없습니다"
      />
      <v-divider class="my-3" />
      <div class="production-dialog-section-title">레시피 변경</div>
      <div class="production-dialog-guide">레시피와 다르게 작업한 경우 변경 내용을 기록해 주세요.</div>
      <v-checkbox
          v-model="form.recipeDeviated"
          label="레시피 변경"
          density="compact"
      />
      <v-textarea
          v-if="form.recipeDeviated"
          v-model="form.recipeDeviationNote"
          label="달라진 작업 내용"
          variant="outlined"
          rows="2"
      />
      <v-divider class="my-3" />
      <div class="production-dialog-section-title">추천 생산량 참고</div>
      <div class="production-dialog-guide">추천 정보가 없더라도 참고 여부를 기록할 수 있습니다.</div>
      <v-checkbox
          v-model="form.recommendationReferenced"
          label="추천 생산량 참고"
          density="compact"
          hide-details
      />
      <div v-if="form.recommendationReferenced" class="production-dialog-field-help">
        추천 생산량을 확인하고 생산 수량을 결정한 경우입니다.
      </div>
      <!-- 참고 여부와 추천 범위 이탈 사유는 서로 다른 정보입니다.
           참고 체크 여부에 관계없이 이탈 사유를 선택적으로 기록할 수 있도록 유지합니다. -->
      <v-text-field
          v-model="form.recommendationDeviationReason"
          label="추천 범위와 다르게 생산한 경우의 이유 · 선택"
          hint="추천 범위를 벗어나 생산한 경우에만 입력해 주세요."
          persistent-hint
          variant="outlined"
      />
      <div class="production-dialog-guide">
        생산 중 특이사항, 작업 변경 내용 외에 다음 근무자가 참고할 내용을 적어 주세요.
      </div>
      <v-textarea
          v-model="form.note"
          label="메모 · 선택"
          variant="outlined"
          density="compact"
          rows="2"
      />
      <div v-if="!product?.recipe" class="recipe-empty"><v-icon icon="mdi-book-open-variant-outline" size="18"/><div><strong>등록된 레시피가 없습니다.</strong><span>레시피가 필요한 경우 제품 관리에서 등록해 주세요.</span></div></div>
      </div>
      <v-divider class="my-3" />
      <div class="production-dialog-section-title">오늘 생산하지 않은 경우</div>
      <div class="production-dialog-guide">
        실제 생산이 없었던 날은 사유를 선택해 0개로 확인합니다. 목록에 맞는 사유가 없으면 직접입력을 선택해 주세요.
      </div>
      <v-select
          v-model="zeroReason"
          :items="displayZeroReasons"
          item-title="title"
          item-value="value"
          label="생산 0개 사유"
          variant="outlined"
          density="compact"
          clearable
      />
      <v-text-field
          v-if="zeroReason === 'other'"
          v-model="zeroReasonText"
          label="사유 직접입력"
          variant="outlined"
          density="compact"
      />
      <v-btn
          variant="outlined"
          block
          :disabled="!canConfirmZero || saving"
          @click="confirmZeroOpen = true"
      >
        생산 0개 확인
      </v-btn>
    </v-card-text>
    <v-card-actions class="app-dialog-footer px-4 pb-4">
      <v-btn variant="text" :disabled="saving" @click="requestClose">닫기</v-btn>
      <v-spacer />
      <v-btn v-if="editingBatch" variant="text" :disabled="saving" @click="cancelEdit">수정 취소</v-btn>
      <v-btn variant="flat" :loading="saving" :disabled="saving" @click="askSave">{{ editingBatch ? '수정 내용 저장' : '생산 기록 저장' }}</v-btn>
    </v-card-actions>
  </v-card>
</v-dialog>

<!-- 생산 기록 등록 및 수정 확인 전용 다이얼로그 -->
<ProductionSaveConfirmDialog
  v-model="saveConfirmOpen"
  :editing="Boolean(editingBatch)"
  :product-name="product?.name || '제품'"
  :quantity="Number(form.quantity)"
  :original-quantity="Number(editingBatch?.quantity || 0)"
  :current-total="product?.production == null ? null : Number(product.production)"
  :expected-total="expectedProductionTotal"
  :average-quantity="productionAverage"
  :previous-quantity="previousProductionQuantity"
  :history-status="productionHistoryStatus"
  :selected-workers="selectedProductionWorkers"
  :abnormal="isAbnormalQuantity"
  :warning-title="productionWarningTitle"
  :warning-message="productionWarningMessage"
  :loading="saving"
  @confirm="save"
/>

<ConfirmDialog
    v-model="deleteConfirmOpen"
    title="생산 기록 삭제"
    message="이 생산 기록을 삭제하시겠습니까? 이미 이월과 연결된 기록은 삭제할 수 없습니다."
    :loading="saving"
    @confirm="deleteBatch"
/>
<ConfirmDialog
    v-model="confirmZeroOpen"
    title="생산 0개 확인"
    message="오늘 이 제품을 생산하지 않은 것으로 확정하시겠습니까? 선택한 사유는 추천 분석에서 일반 수요와 구분해 사용합니다."
    :loading="saving"
    @confirm="saveZero"
/>
<ConfirmDialog
    v-model="confirmClose"
    title="작성 취소"
    message="작성 중인 내용이 있습니다. 닫으시겠습니까?"
    @confirm="forceClose"
/>
</template>

<script setup>
import {
    computed,
    reactive,
    ref,
    watch,
} from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import ProductionSaveConfirmDialog from './ProductionSaveConfirmDialog.vue';
import { addLocalDays } from '../../utils/localDate';

const props = defineProps({
    modelValue: Boolean,
    product: Object,
    storeId: Number,
    workDate: String,
    workers: {
        type: Array,
        default: () => [],
    },
    zeroReasons: {
        type: Array,
        default: () => [],
    },
});
const emit = defineEmits(['update:modelValue', 'saved', 'error']);
const saving = ref(false);

// 전날 생산량 참고 영역의 조회 상태와 결과입니다.
// null은 기록이 없는 상태이며, 생산 0개와 구분합니다.
const previousProduction = ref(null);
const previousProductionLoading = ref(false);

// 날짜나 제품이 바뀌는 동안 이전 요청 결과가 섞이지 않도록 구분합니다.
let previousProductionRequestId = 0;

/**
 * 선택한 제품의 전날 생산 기록을 조회합니다.
 * 기존 일일 조회 API를 재사용하며 생산 기록은 수정하지 않습니다.
 */
async function loadPreviousProduction() {
  const requestId = ++previousProductionRequestId;

  previousProduction.value = null;

  if (!props.storeId || !props.workDate || !props.product?.id) {
    previousProductionLoading.value = false;
    return;
  }

  previousProductionLoading.value = true;

  try {
    const previousDate = addLocalDays(props.workDate, -1);

    const response = await window.axios.get(
      '/tillwhite/api/production-management/daily',
      {
        params: {
          date: previousDate,
          store_id: props.storeId,
        },
      }
    );

    if (requestId !== previousProductionRequestId) return;

    const previousRow = (response.data?.rows || []).find(
      (row) => Number(row.id) === Number(props.product.id)
    );

    // 전날 생산 여부가 확인된 제품만 참고 수량으로 표시합니다.
    if (previousRow?.production_confirmed) {
      previousProduction.value = Number(previousRow.production);
    }
  } catch (error) {
    if (requestId !== previousProductionRequestId) return;

    emit(
      'error',
      error.response?.data?.message ||
        '전날 생산량을 조회하지 못했습니다.'
    );
  } finally {
    if (requestId === previousProductionRequestId) {
      previousProductionLoading.value = false;
    }
  }
}

/**
 * 전날 생산량을 신규 생산 기록의 입력값에만 반영합니다.
 * 생산 0개는 기존의 별도 확인 절차를 유지합니다.
 */
function applyPreviousProduction() {
  if (editingBatch.value || previousProduction.value === null) return;

  if (previousProduction.value === 0) {
    emit(
      'error',
      '전날 생산량이 0개입니다. 생산 0개 확인 기능을 이용해 주세요.'
    );
    return;
  }

  form.quantity = previousProduction.value;
}

// 이상 수량 감지를 위한 이전 7일 생산 이력입니다.
// null은 미확인 날짜이며 실제 생산량 0개와 구분합니다.
const productionHistory = ref([]);
const productionHistoryStatus = ref('idle');
let productionHistoryRequestId = 0;

/**
 * 선택 날짜 이전 7일의 생산량을 조회합니다.
 * 기존 제품 상세 API를 재사용하며 생산 기록은 변경하지 않습니다.
 */
async function loadProductionHistory() {
  const requestId = ++productionHistoryRequestId;

  productionHistory.value = [];
  productionHistoryStatus.value = 'idle';

  if (!props.storeId || !props.workDate || !props.product?.id) {
    return;
  }

  productionHistoryStatus.value = 'loading';

  try {
    const previousDate = addLocalDays(props.workDate, -1);

    const response = await window.axios.get(
      `/tillwhite/api/production-management/products/${props.product.id}`,
      {
        params: {
          work_date: previousDate,
        },
      }
    );

    if (requestId !== productionHistoryRequestId) return;

    const history = response.data?.production_history;

    if (!Array.isArray(history) || history.length !== 7) {
      throw new Error('생산 이력 응답이 올바르지 않습니다.');
    }

    productionHistory.value = history;
    productionHistoryStatus.value = 'success';
  } catch (error) {
    if (requestId !== productionHistoryRequestId) return;

    productionHistory.value = [];
    productionHistoryStatus.value = 'error';
  }
}

// 생산 입력창의 제품이나 날짜가 변경되면 이력을 다시 조회합니다.
watch(
  () => [
    props.modelValue,
    props.product?.id,
    props.storeId,
    props.workDate,
  ],
  () => {
    if (props.modelValue) {
      loadProductionHistory();
    } else {
      productionHistoryRequestId++;
      productionHistory.value = [];
      productionHistoryStatus.value = 'idle';
    }
  },
  { immediate: true }
);

/**
 * 최근 7일 중 생산량이 확인된 날짜만 평균을 계산합니다.
 * 미확인 기록은 제외하고 실제 0개는 포함합니다.
 */
const productionAverage = computed(() => {
  if (productionHistoryStatus.value !== 'success') {
    return null;
  }

  const quantities = productionHistory.value
    .map((row) => row.quantity)
    .filter((quantity) =>
      quantity !== null &&
      quantity !== undefined &&
      Number.isFinite(Number(quantity))
    )
    .map(Number);

  // 확인된 날짜가 3일 미만이면 비교하지 않습니다.
  if (quantities.length < 3) {
    return null;
  }

  const total = quantities.reduce(
    (sum, quantity) => sum + quantity,
    0
  );

  return total / quantities.length;
});

/**
 * 저장 후 예상 총생산량을 기준으로 이상 수량을 감지합니다.
 * 최근 7일 평균과 전날 총생산량 중 높은 값의 2배 이상이면 경고합니다.
 * 평균을 계산할 수 없으면 이상 수량으로 판단하지 않습니다.
 */
const isAbnormalQuantity = computed(() => {
  const average = productionAverage.value;
  const total = expectedProductionTotal.value;

  // 평균이나 예상 총생산량을 계산할 수 없으면 비교하지 않습니다.
  if (average === null || average <= 0 || total === null) {
    return false;
  }

  if (!Number.isFinite(total) || total < 1) {
    return false;
  }

  // 최근 7일 평균과 전날 총생산량 중 높은 값을 비교 기준으로 사용합니다.
  const previous = previousProductionQuantity.value;

  const baseline = previous !== null
    ? Math.max(average, previous)
    : average;

  return total >= baseline * 2;
});

/**
 * 최근 생산 이력에서 선택 날짜의 전날 생산량을 조회합니다.
 * 기록이 없거나 조회에 실패한 경우 null로 유지하며 실제 0개와 구분합니다.
 */
const previousProductionQuantity = computed(() => {
  if (productionHistoryStatus.value !== 'success') {
    return null;
  }

  const previousDate = addLocalDays(props.workDate, -1);

  const previousQuantity = productionHistory.value.find(
    (row) => row.date === previousDate
  )?.quantity;

  return previousQuantity == null
    ? null
    : Number(previousQuantity);
});

// 이상 수량 감지 시 저장 확인창에 표시할 안내 문구입니다.
const productionWarningTitle = '생산 수량 확인 필요';
const productionWarningMessage = '최근 생산 기록보다 입력 수량이 크게 증가했습니다. 수량을 다시 확인해 주세요.';

/**
 * 생산 기록에 선택한 작업자 정보를 조회합니다.
 * 작업자 선택 순서를 유지하며 기존 작업자 목록과 연결합니다.
 */
const selectedProductionWorkers = computed(() => {
  return form.workerIds
    .map((workerId) =>
      props.workers.find(
        (worker) => Number(worker.id) === Number(workerId)
      )
    )
    .filter(Boolean);
});

/**
 * 생산 기록 등록 또는 수정 후 예상 총생산량을 계산합니다.
 * 신규 등록은 입력 수량을 더하고 수정은 기존 수량과의 차이만 반영합니다.
 */
const expectedProductionTotal = computed(() => {
  const currentTotal = Number(props.product?.production);
  const quantity = Number(form.quantity);

  // 현재 총생산량이나 입력 수량이 유효하지 않으면 계산하지 않습니다.
  if (!Number.isFinite(currentTotal) || currentTotal < 0) {
    return null;
  }

  if (!Number.isInteger(quantity) || quantity < 1) {
    return null;
  }

  // 수정 중인 기록이 있다면 기존 수량을 제외한 뒤 새 수량을 반영합니다.
  if (editingBatch.value) {
    const originalQuantity = Number(editingBatch.value.quantity);

    if (!Number.isFinite(originalQuantity)) {
      return null;
    }

    return currentTotal - originalQuantity + quantity;
  }

  // 신규 등록인 경우 현재 총생산량에 입력 수량을 추가합니다.
  return currentTotal + quantity;
});

const saveConfirmOpen = ref(false);
const workerError = ref('');
const editorSection = ref(null);
const confirmClose = ref(false);
const confirmZeroOpen = ref(false);
const deleteConfirmOpen = ref(false);
const editingBatch = ref(null);
const deletingBatch = ref(null);
const zeroReason = ref(null);
const zeroReasonText = ref('');
const form = reactive({
    quantity: 1,
    workerIds: [],
    recipeDeviated: false,
    recipeDeviationNote: '',
    recommendationReferenced: false,
    recommendationDeviationReason: '',
    note: '',
});
const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

// 기존 other 코드는 호환성을 위해 유지하고 화면에서는 직접입력으로 안내합니다.
const displayZeroReasons = computed(() => props.zeroReasons.map((reason) => ({
    ...reason,
    title: reason.value === 'other' ? '직접입력' : reason.title,
})));

// 입력 검증을 통과한 뒤 생산 기록 저장 전용 확인창을 엽니다.
function askSave() {
  if (!props.product?.id || !Number.isInteger(Number(form.quantity)) || Number(form.quantity) < 1) {
    emit('error', '올바른 생산 수량을 입력해 주세요.');
    return;
  }
  if (!form.workerIds.length) {
    workerError.value = '작업자를 선택해 주세요.';
    return;
  }
  if (form.recipeDeviated && !form.recipeDeviationNote.trim()) {
    emit('error', '변경한 작업 내용을 입력해 주세요.');
    return;
  }
  saveConfirmOpen.value = true;
}
const canConfirmZero = computed(() => (
    Boolean(zeroReason.value)
    && (zeroReason.value !== 'other' || Boolean(zeroReasonText.value.trim()))
));

// 다이얼로그가 열릴 때 이전 제품의 입력값이 남지 않도록 초기화합니다.
watch(() => props.modelValue, (value) => {
  if (value) {
    reset();
    loadPreviousProduction();
  } else {
    // 닫힌 다이얼로그의 이전 요청 결과를 무효화합니다.
    previousProductionRequestId++;
    previousProductionLoading.value = false;
  }
});

// 열린 상태에서 제품이나 날짜가 변경되는 경우에도 최신 기록을 조회합니다.
watch(
  () => [props.product?.id, props.workDate, props.storeId],
  () => {
    if (props.modelValue) {
      loadPreviousProduction();
    }
  }
);

// 현재 입력값을 새 생산 기록의 기본값으로 되돌립니다.
function reset() {
  editingBatch.value = null;
  workerError.value = '';
  saveConfirmOpen.value = false;
  deletingBatch.value = null;
  zeroReason.value = null;
  zeroReasonText.value = '';
  form.quantity = 1;
  form.workerIds = [];
  form.recipeDeviated = false;
  form.recipeDeviationNote = '';
  form.recommendationReferenced = false;
  form.recommendationDeviationReason = '';
  form.note = '';
}
// 사용자가 입력한 내용이 있는지 확인해 실수로 닫히는 것을 방지합니다.
function isDirty() {
  return form.quantity !== 1
      || form.workerIds.length > 0
      || form.recipeDeviated
      || Boolean(form.recipeDeviationNote)
      || form.recommendationReferenced
      || Boolean(form.recommendationDeviationReason)
      || Boolean(form.note)
      || Boolean(zeroReason.value)
      || Boolean(zeroReasonText.value);
}
// 작성 내용이 있으면 확인창을 거친 뒤 닫습니다.
function requestClose() {
  if (saving.value) return;
  if (isDirty()) {
    confirmClose.value = true;
    return;
  }

  forceClose();
}
// 확인을 마친 뒤 생산 입력 다이얼로그를 닫습니다.
function forceClose() {
  confirmClose.value = false;
  open.value = false;
}
// 기존 생산 기록을 수정할 수 있도록 해당 값을 입력 폼에 불러옵니다.
function editBatch(batch) {
  editingBatch.value = batch;
  form.quantity = batch.quantity;
  form.note = batch.note || '';
  form.workerIds = (batch.workers || []).map(worker => worker.id);
  form.recipeDeviated = Boolean(batch.recipe_deviated);
  form.recipeDeviationNote = batch.recipe_deviation_note || '';
  workerError.value = '';
  editorSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
// 생산 수정 모드를 끝내고 신규 입력 상태로 돌아갑니다.
function cancelEdit() {
  editingBatch.value = null;
  form.quantity = 1;
  form.note = '';
  form.workerIds = [];
  form.recipeDeviated = false;
  form.recipeDeviationNote = '';
  workerError.value = '';
}
// 삭제할 생산 기록을 기억하고 중요 작업 확인창을 엽니다.
function askDelete(batch) {
  deletingBatch.value = batch;
  deleteConfirmOpen.value = true;
}
// 서버 검증 뒤 생산 기록을 Soft Delete하고 성공 시 목록을 다시 조회합니다.
async function deleteBatch() {
  if (!deletingBatch.value) return;
  saving.value = true;
  try {
    await window.axios.delete(`/tillwhite/api/production-management/batches/${deletingBatch.value.id}`, {
      data: {
        memo: null
      }
    });
    deleteConfirmOpen.value = false;
    open.value = false;
    emit('saved', '생산 기록을 삭제했습니다.');
  } catch (error) {
    emit('error', error.response?.data?.message || '생산 기록을 삭제하지 못했습니다.');
  } finally {
    saving.value = false;
  }
}
// 생산하지 않은 날은 0과 사유를 명시적으로 확인해 미입력과 구분합니다.
async function saveZero() {
  saving.value = true;
  try {
    await window.axios.post('/tillwhite/api/production-management/zero-production', {
      store_id: props.storeId,
      product_id: props.product.id,
      work_date: props.workDate,
      reason: zeroReason.value,
      reason_text: zeroReason.value === 'other' ? zeroReasonText.value.trim() : null,
      note: form.note || null,
    });
    confirmZeroOpen.value = false;
    open.value = false;
    emit('saved', '생산 0개를 확인했습니다.');
  } catch (error) {
    emit('error', error.response?.data?.message || '생산 0개를 확인하지 못했습니다.');
  } finally {
    saving.value = false;
  }
}
// 서버 확인이 끝난 생산 기록만 성공 처리하고 실패 시 입력값을 유지합니다.
async function save() {
  if (!props.product?.id || !form.quantity || !form.workerIds.length) return;
  if (form.recipeDeviated && !form.recipeDeviationNote.trim()) {
    emit('error', '변경한 작업 내용을 입력해 주세요.');
    return;
  }
  saving.value = true;
  try {
    if (editingBatch.value) {
      await window.axios.put(`/tillwhite/api/production-management/batches/${editingBatch.value.id}`, {
        quantity: form.quantity, note: form.note || null, lock_version: editingBatch.value.lock_version,
        workers: form.workerIds.map(userId => ({ user_id: userId })),
      });
      saveConfirmOpen.value = false;
      open.value = false;
      emit('saved', '생산 기록을 수정했습니다.');
    } else {
      await window.axios.post('/tillwhite/api/production-management/batches', {
        store_id: props.storeId,
        product_id: props.product.id,
        work_date: props.workDate,
        quantity: form.quantity,
        recipe_deviated: form.recipeDeviated,
        recipe_deviation_note: form.recipeDeviationNote || null,
        note: form.note || null,
        recommendation_referenced: form.recommendationReferenced,
        recommendation_deviation_reason: form.recommendationDeviationReason || null,
        workers: form.workerIds.map((userId) => ({
          user_id: userId,
          process_type: 'all',
        })),
      });
      saveConfirmOpen.value = false;
      open.value = false;
      emit('saved', '생산 기록을 저장했습니다.');
    }
  } catch (error) {
    emit('error', error.response?.data?.message || '생산 기록을 저장하지 못했습니다.');
  }
  finally {
    saving.value = false;
  }
}
</script>

<style scoped>
/* 전날 생산량을 별도 참고 영역으로 표시합니다. */
.previous-production-reference {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 12px 0 16px;
  padding: 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.13);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.previous-production-reference__content {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.previous-production-reference__label {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.previous-production-reference__content strong {
  font-size: 0.95rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.previous-production-reference .v-btn {
  flex-shrink: 0;
}

.production-record-section { margin-bottom: 12px; }
.production-record-card { border: 1px solid rgba(var(--v-theme-on-surface), .13); border-radius: 10px; padding: 12px; margin-top: 9px; }
.production-record-card.record-editing { border-color: rgb(var(--v-theme-primary)); background: rgba(var(--v-theme-primary), .06); }
.production-record-main { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.production-record-main strong { font-size: .95rem; }
.production-record-main span, .production-record-workers, .production-record-note { font-size: .75rem; color: rgba(var(--v-theme-on-surface), .68); }
.production-record-workers, .production-record-note { margin-top: 6px; }
.production-record-actions { display: flex; justify-content: flex-end; border-top: 1px solid rgba(var(--v-theme-on-surface), .08); margin-top: 9px; padding-top: 5px; }
.production-friendly-empty { display: flex; align-items: center; gap: 10px; padding: 16px 12px; border: 1px dashed rgba(var(--v-theme-on-surface), .18); border-radius: 10px; }
.production-friendly-empty strong, .production-friendly-empty span { display: block; font-size: .75rem; }
.production-friendly-empty span { color: rgba(var(--v-theme-on-surface), .6); margin-top: 3px; }
.production-record-editor { scroll-margin-top: 72px; }

.overview-guide {
    margin-bottom: 14px;
}

.primary-quantity-input :deep(input) {
    font-size: 0.9rem;
    font-weight: 650;
    font-variant-numeric: tabular-nums;
}

.recipe-empty {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    margin: 4px 0 10px;
    padding: 9px 10px;
    border-radius: 9px;
    background: rgba(var(--v-theme-on-surface), 0.04);
}

.recipe-empty strong,
.recipe-empty span {
    display: block;
}

.recipe-empty strong {
    font-size: 0.72rem;
    font-weight: 600;
}

.recipe-empty span {
    margin-top: 2px;
    font-size: 0.65rem;
    color: rgba(var(--v-theme-on-surface), 0.58);
}
</style>
