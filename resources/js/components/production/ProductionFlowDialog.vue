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
        <div>{{ dialogHeaderTitle }}</div>
      </v-card-title>

      <v-card-text class="app-dialog-body">
        <v-alert
          v-if="remainingAvailable < currentQuantity || sourceOverages.length || invalidSourceRows.length"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4 app-supporting-alert"
        >
          입력한 수량이 남은 수량보다 많습니다.
        </v-alert>

        <div class="production-dialog-overview">
          <div><span>오늘 생산</span><strong>{{ Number(product?.production || 0) }}개</strong></div>
          <div><span>이월 재고</span><strong>{{ Number(product?.carryover_in || 0) }}개</strong></div>
          <div><span>확인 상태</span><strong>{{ flowConfirmed ? '완료' : '미확인' }}</strong></div>
        </div>

        <div class="flow-guide">
          <div><span>총 수량</span><strong>{{ available }}개</strong></div>
          <p>{{ dialogDescription }}</p>
        </div>

        <div class="flow-summary">
          <div><span>사용 가능</span><strong>{{ selectedSourceRemaining }}개</strong></div>
        </div>

        <div v-if="otherAllocationDetails.length" class="allocation-details">
          <div class="flow-input-heading">이미 처리된 내역</div>
          <div v-for="item in otherAllocationDetails" :key="item.label" class="allocation-detail-row">
            <span>{{ item.label }}</span>
            <strong>{{ item.quantity }}개</strong>
          </div>
        </div>

        <div v-if="savedDetails.length" class="saved-flow-details">
          <div class="flow-input-heading">현재 기록</div>
          <div v-for="(item, index) in savedDetails" :key="`${item.reason_code || 'reason'}-${index}`" class="saved-flow-row">
            <span>{{ savedReasonLabel(item) }}</span><strong>{{ item.quantity }}개</strong>
          </div>
        </div>

        <template v-if="isReasonMode">
          <div class="production-dialog-section-title">{{ dialogTitle }} 수량과 사유</div>
          <ProductionReasonRows
              v-model="reasonRows"
              :reason-options="reasonOptions"
              :max-quantity="remainingAvailable"
              :stock-sources="editableStockSources"
              :work-date="workDate"
              :show-stock-source="type === 'loss' || type === 'waste'"
          />
          <div class="production-dialog-actions">
            <v-btn
                size="small"
                variant="text"
                prepend-icon="mdi-plus"
                class="production-dialog-add-button"
                @click="reasonRows.push(emptyReason())"
            >
              사유 추가
            </v-btn>
            <v-btn size="small" variant="outlined" @click="setZero">{{ dialogTitle }} 없음</v-btn>
          </div>
        </template>

        <template v-else>
          <div class="production-dialog-section-title">다음 영업일로 넘길 수량</div>
          <v-number-input
            v-model="carryoverQuantity"
            label="이월 수량"
            variant="outlined"
            density="compact"
            :min="0"
            :max="remainingAvailable"
          />
          <v-select
            v-if="carryoverQuantity > 0 && product?.carryover_in > 0"
            v-model="carryoverSource"
            :items="carryoverSources"
            item-title="title"
            item-value="value"
            label="이월할 재고 출처"
            variant="outlined"
          />
          <v-alert
            v-if="carryoverQuantity > 0 && carryoverSource === 'incoming'"
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-3 app-supporting-alert"
          >
            이미 이월된 제품을 다시 이월합니다. 원래 생산일은 유지됩니다.
          </v-alert>
        </template>

        <div class="production-dialog-guide mt-3">
          특이사항, 처리 과정에서 확인한 내용처럼 다음 근무자가 참고할 내용을 적어 주세요.
        </div>
        <v-textarea
            v-model="note"
            label="메모 · 선택"
            variant="outlined"
            density="compact"
            rows="2"
        />
      </v-card-text>

      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" :disabled="saving" @click="requestClose">닫기</v-btn>
        <v-spacer />
        <v-btn
          variant="flat"
          :loading="saving"
          :disabled="saving || remainingAvailable < currentQuantity || sourceOverages.length || invalidSourceRows.length > 0"
          @click="askSave"
        >
          저장
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <ConfirmDialog
    v-model="confirmSave"
    :title="`${dialogTitle} 저장`"
    :message="confirmMessage"
    :loading="saving"
    @confirm="save"
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
    ref,
    watch,
} from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import ProductionReasonRows from './ProductionReasonRows.vue';
import { productionReasonLabel } from '../../utils/productionReasons';

const props = defineProps({
  modelValue: Boolean,
  product: Object,
  storeId: Number,
  workDate: String,
  type: {
    type: String,
    required: true,
    validator: (value) => ['carryover', 'loss', 'waste', 'other_outflow'].includes(value),
  },
  lossReasons: {
    type: Array,
    default: () => [],
  },
  wasteReasons: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['update:modelValue', 'saved', 'error']);
const saving = ref(false);
const confirmSave = ref(false);
const confirmClose = ref(false);
const reasonRows = ref([]);
const carryoverQuantity = ref(0);
const carryoverSource = ref('today');
const note = ref('');
const initialReasonSnapshot = ref('[]');

const carryoverSources = [
  { value: 'today', title: '오늘 생산분' },
  { value: 'incoming', title: '기존 이월분 · 재이월' },
];

const otherReasons = [
  { value: 'tasting', title: '시식' },
  { value: 'service', title: '고객 서비스' },
  { value: 'gift', title: '무료 증정' },
  { value: 'staff_use', title: '직원 사용' },
  { value: 'other', title: '직접입력' },
];

const open = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const isReasonMode = computed(() => props.type !== 'carryover');
const flowConfirmed = computed(() => ({
  carryover: props.product?.disposition_confirmed,
  loss: props.product?.loss_confirmed,
  waste: props.product?.waste_confirmed,
  other_outflow: props.product?.disposition_confirmed,
}[props.type] ?? false));
const savedDetails = computed(() => {
  if (props.type === 'loss') return props.product?.operational_loss_details || props.product?.loss_details || [];
  if (props.type === 'waste') return props.product?.operational_waste_details || props.product?.waste_details || [];
  if (props.type === 'other_outflow') return props.product?.other_outflow_details || [];
  return [];
});

// 수정 중인 업무가 이미 사용한 수량은 다시 입력할 수 있도록 해당 재고의 편집 가능 수량에 되돌려 줍니다.
const editableStockSources = computed(() => (props.product?.stock_sources || []).map((source) => {
  const currentSaved = savedDetails.value
    .filter((item) => Number(item.stock_lot_id) === Number(source.stock_lot_id))
    .reduce((sum, item) => sum + Number(item.quantity || 0), 0);

  return {
    ...source,
    // 서버의 음수 포함 미배정 수량으로 기존 기록을 복원합니다.
    // 표시용 remaining_quantity(최소 0)만 사용하면 초과 차감된 기존 데이터에서
    // 수정 가능한 수량을 실제보다 크게 계산할 수 있습니다.
    remaining_quantity: Math.max(0, Number(source.unallocated_quantity ?? source.remaining_quantity ?? 0) + currentSaved),
  };
}));

// 총 수량은 선택 날짜의 생산분과 들어온 이월 재고를 합친 값입니다.
const available = computed(() => Number(props.product?.production || 0) + Number(props.product?.carryover_in || 0));

// 입력 중인 행을 lot별로 묶어 실제로 처리 가능한 재고를 계산합니다.
// 기존 기록은 editableStockSources에서 편집 가능 수량에 복원되어 있습니다.
// 저장된 화면 정보가 오래되어 출처가 사라졌다면 다른 재고로 대체하지 않습니다.
const invalidSourceRows = computed(() => {
  if (!isReasonMode.value || !['loss', 'waste', 'other_outflow'].includes(props.type)) return [];
  const sourceIds = new Set(editableStockSources.value.map((source) => Number(source.stock_lot_id)));
  return reasonRows.value.filter((row) => row.stock_lot_id && !sourceIds.has(Number(row.stock_lot_id)));
});

const sourceOverages = computed(() => {
  if (!isReasonMode.value || !['loss', 'waste', 'other_outflow'].includes(props.type)) return [];
  return editableStockSources.value.filter((source) => {
    const requested = reasonRows.value
      .filter((row) => Number(row.stock_lot_id) === Number(source.stock_lot_id))
      .reduce((sum, row) => sum + Number(row.quantity || 0), 0);
    return requested > Number(source.remaining_quantity || 0);
  });
});

const selectedSourceRemaining = computed(() => {
  const selected = reasonRows.value.at(-1);
  const source = editableStockSources.value.find((item) => Number(item.stock_lot_id) === Number(selected?.stock_lot_id));
  if (!source) return remainingAvailable.value - currentQuantity.value;
  const allocated = reasonRows.value
    .filter((row) => Number(row.stock_lot_id) === Number(source.stock_lot_id))
    .reduce((sum, row) => sum + Number(row.quantity || 0), 0);
  return Math.max(0, Number(source.remaining_quantity || 0) - allocated);
});

const dialogTitle = computed(() => ({
  carryover: '이월',
  loss: '로스',
  waste: '폐기',
  other_outflow: '기타 출고',
}[props.type]));

// 업무명과 제품명을 한 줄에 표시해 어떤 제품을 수정하는지 헤더에서 바로 확인합니다.
const dialogHeaderTitle = computed(() => `${dialogTitle.value} - ${props.product?.name || '-'}`);

const dialogDescription = computed(() => ({
  carryover: '남은 수량 안에서 다음 영업일로 넘길 수량을 입력해 주세요.',
  loss: '남은 수량 안에서 로스 수량과 사유를 입력해 주세요. 로스가 없으면 ‘로스 없음’을 누르세요.',
  waste: '남은 수량 안에서 폐기 수량과 사유를 입력해 주세요. 폐기가 없으면 ‘폐기 없음’을 누르세요.',
  other_outflow: '시식·서비스 등 기타 출고 수량을 기록합니다.',
}[props.type]));

const reasonOptions = computed(() => ({
  loss: props.lossReasons,
  waste: props.wasteReasons,
  other_outflow: otherReasons,
}[props.type] || []));

const otherAllocationDetails = computed(() => {
  const row = props.product || {};
  const values = {
    carryover: [
      ['로스', Number(row.operational_loss ?? row.loss ?? 0)],
      ['폐기', Number(row.operational_waste ?? row.waste ?? 0)],
      ['기타 출고', Number(row.other_outflow || 0)],
    ],
    loss: [
      ['폐기', Number(row.operational_waste ?? row.waste ?? 0)],
      ['이월 예정', Number(row.carryover_out || 0)],
      ['기타 출고', Number(row.other_outflow || 0)],
    ],
    waste: [
      ['로스', Number(row.operational_loss ?? row.loss ?? 0)],
      ['이월 예정', Number(row.carryover_out || 0)],
      ['기타 출고', Number(row.other_outflow || 0)],
    ],
    other_outflow: [
      ['로스', Number(row.operational_loss ?? row.loss ?? 0)],
      ['폐기', Number(row.operational_waste ?? row.waste ?? 0)],
      ['이월 예정', Number(row.carryover_out || 0)],
    ],
  };

  return (values[props.type] || [])
    .filter(([, quantity]) => quantity > 0)
    .map(([label, quantity]) => ({ label, quantity }));
});

const otherAllocated = computed(() => otherAllocationDetails.value
  .reduce((sum, item) => sum + item.quantity, 0));

const remainingAvailable = computed(() => Math.max(0, available.value - otherAllocated.value));
const currentQuantity = computed(() => (
  props.type === 'carryover'
    ? Number(carryoverQuantity.value || 0)
    : sumRows(reasonRows.value)
));

// 입력과 동시에 저장 후 남을 수량을 보여줘 초과 입력을 저장 전에 발견할 수 있게 합니다.
const remainingAfterSave = computed(() => Math.max(0, remainingAvailable.value - currentQuantity.value));

const confirmMessage = computed(() => {
  const message = `${dialogTitle.value} ${currentQuantity.value}개로 저장하시겠습니까?`;

  if (props.type === 'carryover' && carryoverQuantity.value > 0 && carryoverSource.value === 'incoming') {
    return `이미 이월된 제품입니다. 다시 이월하시겠습니까?\n\n${message}`;
  }

  return message;
});

// 저장된 이월 재고 기록은 원 생산일까지 함께 보여줘 어느 재고를 처리했는지 바로 확인합니다.
function savedReasonLabel(item) {
  const reason = productionReasonLabel(item, reasonOptions.value);
  if (!item.origin_production_date || item.origin_production_date === props.workDate) {
    return `오늘 생산 · ${reason}`;
  }

  const [, month, day] = String(item.origin_production_date).split('-');
  return `${Number(month)}/${Number(day)} 생산 이월 재고 · ${reason}`;
}

// 현재 업무의 선택 목록을 함께 넘겨 과거 사유 코드도 같은 한글 명칭으로 표시합니다.
function reasonLabel(item) {
    return productionReasonLabel(item, reasonOptions.value);
}

// 당일 생산 재고가 있으면 우선 사용하고, 없으면 이월 재고를 기본 선택합니다.
const defaultStockSource = computed(() => (
  'today'
));
const defaultStockLotId = computed(() => (
  editableStockSources.value.find((source) => source.source === 'today')?.stock_lot_id ?? null
));

// 기존 기록을 다시 열면 저장된 재고 출처와 수량을 그대로 편집할 수 있게 복원합니다.
function savedReasonRow(item) {
  const source = editableStockSources.value.find((stock) => Number(stock.stock_lot_id) === Number(item.stock_lot_id));
  return {
    reason_code: item.reason_code || '',
    reason_text: item.reason_text || null,
    quantity: Number(item.quantity || 0),
    stock_source: source?.source || (item.origin_production_date === props.workDate ? 'today' : 'carryover'),
    stock_lot_id: item.stock_lot_id || source?.stock_lot_id || null,
  };
}

// 사유 행의 기본값을 생성합니다.
function emptyReason() {
  return {
    reason_code: '',
    reason_text: null,
    quantity: 0,
    stock_source: defaultStockSource.value,
    stock_lot_id: defaultStockLotId.value,
  };
}

// 여러 사유 행의 수량 합계를 계산합니다.
function sumRows(rows) {
  return rows.reduce((sum, row) => sum + Number(row.quantity || 0), 0);
}

// 다이얼로그가 열릴 때 선택 기능에 필요한 입력값만 초기화합니다.
watch(() => props.modelValue, (value) => {
  if (!value) return;

  reasonRows.value = isReasonMode.value
    ? (savedDetails.value.length ? savedDetails.value.map(savedReasonRow) : [emptyReason()])
    : [];
  carryoverQuantity.value = Number(props.product?.carryover_out || 0);
  carryoverSource.value = props.product?.carryover_in > 0 ? 'incoming' : 'today';
  note.value = '';
  initialReasonSnapshot.value = JSON.stringify(reasonRows.value);
});

// 로스·폐기가 없을 때 0개 확인을 한 번의 행동으로 입력합니다.
function setZero() {
  reasonRows.value = [];
  askSave();
}

// 사용자가 실제로 입력한 내용이 있는지 확인합니다.
function isDirty() {
  if (props.type === 'carryover') {
    return carryoverQuantity.value !== Number(props.product?.carryover_out || 0) || Boolean(note.value);
  }

  return JSON.stringify(reasonRows.value) !== initialReasonSnapshot.value || Boolean(note.value);
}

// 작성 중인 값이 있으면 확인창을 거친 뒤 닫습니다.
function requestClose() {
  if (saving.value) return;

  if (isDirty()) {
    confirmClose.value = true;
    return;
  }

  forceClose();
}

// 작성 취소를 확인한 뒤 현재 업무 다이얼로그를 닫습니다.
function forceClose() {
  confirmClose.value = false;
  open.value = false;
}

// 사유 필수값과 수량 범위를 확인한 뒤 저장 확인창을 엽니다.
function askSave() {
  if (isReasonMode.value && reasonRows.value.length > 0 && reasonRows.value.some((row) => !row.reason_code || Number(row.quantity || 0) <= 0)) {
    emit('error', '추가한 사유와 수량을 모두 입력해 주세요.');
    return;
  }

  if ((props.type === 'loss' || props.type === 'waste') && reasonRows.value.some((row) => !row.stock_lot_id)) {
    emit('error', '처리할 재고의 생산일을 선택해 주세요.');
    return;
  }

  if (reasonRows.value.some((row) => row.reason_code === 'other' && !String(row.reason_text || '').trim())) {
    emit('error', '직접입력 사유를 입력해주세요.');
    return;
  }

  if (invalidSourceRows.value.length) {
    emit('error', '선택한 재고 출처를 찾을 수 없습니다. 재고를 다시 선택해 주세요.');
    return;
  }

  if (sourceOverages.value.length) {
    emit('error', '선택한 생산일의 잔여 재고보다 입력한 수량이 많습니다.');
    return;
  }

  if (currentQuantity.value > remainingAvailable.value) {
    emit('error', '입력한 수량이 남은 수량보다 많습니다.');
    return;
  }

  confirmSave.value = true;
}

// 선택한 한 가지 업무만 서버에 저장하여 다른 수량 기록을 덮어쓰지 않습니다.
async function save() {
  saving.value = true;

  try {
    const payload = {
      store_id: props.storeId,
      product_id: props.product.id,
      work_date: props.workDate,
      type: props.type,
      note: note.value || null,
    };

    if (props.type === 'carryover') {
      payload.quantity = Number(carryoverQuantity.value || 0);
      payload.carryover_source = carryoverSource.value;
    } else {
      payload.reasons = reasonRows.value;
    }

    const { data } = await window.axios.put('/tillwhite/api/production-management/flow', payload);
    confirmSave.value = false;
    open.value = false;
    emit('saved', data.message || `${dialogTitle.value}을 저장했습니다.`, data.daily || null);
  } catch (error) {
    emit('error', error.response?.data?.message || `${dialogTitle.value}을 저장하지 못했습니다.`);
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>

.allocation-details {
    margin: -4px 0 14px;
    padding: 8px 10px;
    border: 1px solid rgba(var(--v-border-color), 0.5);
    border-radius: 8px;
}

.allocation-detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 28px;
    font-size: 0.7rem;
}

.allocation-detail-row span {
    color: rgba(var(--v-theme-on-surface), 0.62);
}

.saved-flow-details {
    margin: 12px 0;
}

.saved-flow-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 32px;
    padding: 5px 2px;
    border-bottom: 1px solid rgba(var(--v-border-color), 0.5);
    font-size: 0.72rem;
}

.saved-flow-row strong {
    font-variant-numeric: tabular-nums;
}

.flow-guide {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(var(--v-theme-on-surface), 0.04);
}

.flow-guide div {
    flex: none;
}

.flow-guide span,
.flow-guide strong {
    display: block;
}

.flow-guide span {
    font-size: 0.66rem;
    color: rgba(var(--v-theme-on-surface), 0.56);
}

.flow-guide strong {
    margin-top: 1px;
    font-size: 1rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.flow-guide p {
    margin: 0;
    font-size: 0.68rem;
    line-height: 1.45;
    color: rgba(var(--v-theme-on-surface), 0.62);
    text-align: right;
}

.flow-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    margin-bottom: 14px;
}

.flow-summary > div {
    padding: 7px 9px;
    border: 1px solid rgba(var(--v-border-color), 0.5);
    border-radius: 8px;
}

.flow-summary span,
.flow-summary strong {
    display: block;
}

.flow-summary span {
    font-size: 0.63rem;
    color: rgba(var(--v-theme-on-surface), 0.52);
}

.flow-summary strong {
    margin-top: 1px;
    font-size: 0.78rem;
    font-weight: 600;
}

@media (max-width: 520px) {
    .flow-guide {
        align-items: flex-start;
    }

    .flow-guide p {
        max-width: 68%;
    }
}
</style>
