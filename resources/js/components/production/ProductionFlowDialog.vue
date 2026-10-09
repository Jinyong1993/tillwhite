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
          v-if="
            (
              type !== 'loss' &&
              type !== 'waste' &&
              remainingAvailable < currentQuantity
            ) ||
            sourceOverages.length ||
            invalidSourceRows.length
          "
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
          <div>
            <span>총 수량</span>
            <strong>{{ available }}개</strong>
          </div>
          <p>{{ dialogDescription }}</p>
        </div>

        <div v-if="isReasonMode" class="flow-guide">
          <div>
            <span>{{ dialogTitle }} · 마지막 선택 기록 잔여량</span>
            <strong>{{ selectedSourceRemaining }}개</strong>
          </div>
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
            :today-production-total="Number(product?.production || 0)"
            :stock-sources="editableStockSources"
            :work-date="workDate"
            :show-stock-source="isReasonMode"
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
          <div class="production-dialog-section-title">
            다음 영업일로 넘길 수량
          </div>
          <!-- 오늘 생산분과 기존 이월분을 구분합니다. -->
          <v-select
            v-if="product?.carryover_in > 0"
            v-model="carryoverSource"
            :items="carryoverSources"
            item-title="title"
            item-value="value"
            label="이월할 재고 출처"
            variant="outlined"
            density="compact"
            @update:model-value="changeCarryoverSource"
          />
          <!-- 이월할 생산 기록을 직접 선택합니다. -->
          <v-select
            v-model="selectedCarryoverLotId"
            :items="carryoverLotOptions"
            item-title="title"
            item-value="value"
            label="이월할 생산 기록"
            variant="outlined"
            density="compact"
            clearable
            :disabled="carryoverLotOptions.length === 0"
            @update:model-value="changeCarryoverLot"
          />
          <!-- 선택한 생산 기록의 재고만 사용합니다. -->
          <v-number-input
            v-model="carryoverQuantity"
            label="이월 수량"
            variant="outlined"
            density="compact"
            :min="0"
            :max="Math.min(remainingAvailable, carryoverSourceAvailable)"
            :disabled="selectedCarryoverLotId == null"
          />
          <!-- 재이월하더라도 최초 생산일은 유지합니다. -->
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
        <v-btn 
          variant="text" 
          :disabled="saving" 
          @click="requestClose"
        >
          닫기
        </v-btn>
        <v-spacer />
        <v-btn
          variant="flat"
          :loading="saving"
          :disabled="
            saving
            || (
              type !== 'loss' &&
              type !== 'waste' &&
              remainingAvailable < currentQuantity
            )
            || sourceOverages.length > 0
            || invalidSourceRows.length > 0
            || (
              type === 'carryover'
              && currentQuantity > carryoverSourceAvailable
            )
          "
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
    validator: (value) => ['carryover', 'loss', 'waste'].includes(value),
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

// 이월할 생산 기록을 선택합니다.
const selectedCarryoverLotId = ref(null);

const note = ref('');
const initialReasonSnapshot = ref('[]');

// 다이얼로그를 열었을 때의 이월 출처를 보관합니다.
const initialCarryoverSource = ref('today');

const carryoverSources = [
  { value: 'today', title: '오늘 생산분' },
  { value: 'incoming', title: '기존 이월분 · 재이월' },
];

/**
 * 이월할 제품의 생산 내역과 사용 가능 수량을 표시합니다.
 * 오늘 생산분과 기존 이월 재고를 구분합니다.
 * 같은 날짜의 생산 내역은 목록 번호로 구분합니다.
 */
const carryoverLotOptions = computed(() => {
  const sourceType = carryoverSource.value === 'incoming'
    ? 'carryover'
    : 'today';

  const countsByDate = new Map();

  return (props.product?.stock_sources || [])
    .filter((source) => (
      source.source === sourceType &&
      source.stock_lot_id != null
    ))
    .map((source) => {
      const productionDate =
        source.origin_production_date || '생산일 미확인';

      // 동일 생산일의 내역을 목록에서 구분합니다.
      const sequence = (countsByDate.get(productionDate) || 0) + 1;
      countsByDate.set(productionDate, sequence);

      // 실제 남은 수량과 기존 이월 수량을 확인합니다.
      const rawRemaining = source.unallocated_quantity
        ?? source.remaining_quantity;

      const remaining = rawRemaining == null
        ? null
        : Number(rawRemaining);

      const existing = Number(source.carryover_out_quantity || 0);

      const available = remaining !== null && Number.isFinite(remaining)
        ? remaining + existing
        : null;

      const quantityText = available === null
        ? '수량 미확인'
        : available < 0
          ? `초과 처리 ${Math.abs(available)}개`
          : `이월 가능 ${available}개`;

      return {
        value: source.stock_lot_id,
        title: `${productionDate} 생산 · 생산 내역 ${sequence} · ${quantityText}`,
      };
    });
});

const open = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const isReasonMode = computed(() => props.type !== 'carryover');

// 선택한 업무의 완료 여부만 확인합니다.
const flowConfirmed = computed(() => ({
  carryover: props.product?.disposition_confirmed,
  loss: props.product?.loss_confirmed,
  waste: props.product?.waste_confirmed,
}[props.type] ?? false));

// 선택한 로스 또는 폐기의 기록만 불러옵니다.
const savedDetails = computed(() => {
  if (props.type === 'loss') {
    return props.product?.operational_loss_details
      || props.product?.loss_details
      || [];
  }

  if (props.type === 'waste') {
    return props.product?.operational_waste_details
      || props.product?.waste_details
      || [];
  }

  return [];
});

/**
 * 기존 기록을 수정할 때 사용할 수 있는 재고를 계산합니다.
 * 선택한 업무에서 이미 처리한 수량만 복원합니다.
 */
const editableStockSources = computed(() => {
  const sources = props.product?.stock_sources || [];
  const details = savedDetails.value || [];

  return sources.map((source) => {
    const stockLotId = source.stock_lot_id;

    // 현재 업무에서 이 재고로 처리한 기존 수량입니다.
    const currentSaved = stockLotId == null
      ? 0
      : details
          .filter((item) => (
            item.stock_lot_id != null &&
            String(item.stock_lot_id) === String(stockLotId)
          ))
          .reduce((sum, item) => {
            const quantity = Number(item.quantity);

            return Number.isSafeInteger(quantity) && quantity >= 0
              ? sum + quantity
              : sum;
          }, 0);

    // 재고 계산 결과를 그대로 사용합니다.
    const rawRemaining = Object.prototype.hasOwnProperty.call(
      source,
      'unallocated_quantity'
    )
      ? source.unallocated_quantity
      : source.remaining_quantity;

    const remaining = rawRemaining == null || rawRemaining === ''
      ? null
      : Number(rawRemaining);

    // 기존 수량만 복원하고 다른 재고의 수량은 합치지 않습니다.
    const editableQuantity = remaining !== null && Number.isFinite(remaining)
      ? remaining + currentSaved
      : null;

    return {
      ...source,
      editable_raw_quantity: editableQuantity,
      remaining_quantity: editableQuantity === null
        ? null
        : Math.max(0, editableQuantity),
    };
  });
});

// 총 수량은 선택 날짜의 생산분과 들어온 이월 재고를 합친 값입니다.
const available = computed(() => Number(props.product?.production || 0) + Number(props.product?.carryover_in || 0));

/**
 * 로스·폐기·기타 출고에 입력한 재고 출처를 검사합니다.
 * 수량이 입력된 행은 생산 기록 ID와 출처가 모두 일치해야 합니다.
 * 잘못된 기록이나 사라진 재고를 다른 생산 기록으로 대체하지 않습니다.
 */
const invalidSourceRows = computed(() => {
  if (
    !isReasonMode.value ||
    !['loss', 'waste'].includes(props.type)
  ) {
    return [];
  }

  // 수량이 입력된 행만 검사합니다.
  return reasonRows.value.filter((row) => {
    const quantity = Number(row.quantity || 0);

    if (quantity <= 0) return false;

    // 생산 기록 ID와 재고 출처는 필수입니다.
    if (
      !row.stock_source ||
      row.stock_lot_id == null ||
      row.stock_lot_id === ''
    ) {
      return true;
    }

    // ID와 출처가 모두 일치하는 생산 기록만 인정합니다.
    return !editableStockSources.value.some((source) => (
      source.source === row.stock_source &&
      String(source.stock_lot_id) === String(row.stock_lot_id)
    ));
  });
});

/**
 * 생산 기록별로 입력 수량이 재고를 초과하는지 검사합니다.
 * 다른 생산 기록의 재고는 합산하지 않습니다.
 */
const sourceOverages = computed(() => {
  if (
    !isReasonMode.value ||
    !['loss', 'waste'].includes(props.type)
  ) {
    return [];
  }

  const requested = new Map();

  // 같은 생산 기록에 입력한 수량을 합산합니다.
  for (const row of reasonRows.value) {
    const lotId = row.stock_lot_id;
    const quantity = Number(row.quantity || 0);

    if (lotId == null || lotId === '' || quantity <= 0) {
      continue;
    }

    const key = `${row.stock_source}:${lotId}`;

    requested.set(
      key,
      (requested.get(key) || 0) + quantity
    );
  }

  // 선택한 기록의 실제 재고와 비교합니다.
  return [...requested.entries()].filter(([key, quantity]) => {
    const source = editableStockSources.value.find((item) => (
      `${item.source}:${item.stock_lot_id}` === key
    ));

    if (!source) return true;

    const available = source.editable_raw_quantity;

    if (available == null || !Number.isFinite(Number(available))) {
      return true;
    }

    return quantity > Number(available);
  });
});

/**
 * 마지막 사유 행에서 선택한 생산 기록의 남은 수량을 계산합니다.
 * 로스·폐기·기타 출고를 각각 선택한 재고 기준으로 확인합니다.
 * 다른 생산 기록의 수량은 합산하지 않습니다.
 */
const selectedSourceRemaining = computed(() => {
  // 이월 화면에서는 사용하지 않습니다.
  if (!isReasonMode.value) return 0;

  // 마지막 사유 행에서 선택한 생산 기록을 확인합니다.
  const selected = reasonRows.value.at(-1);
  if (selected?.stock_lot_id == null) return 0;

  // 선택한 출처와 생산 기록 ID가 일치하는 재고만 찾습니다.
  const source = editableStockSources.value.find((item) => (
    item.source === selected.stock_source &&
    String(item.stock_lot_id) === String(selected.stock_lot_id)
  ));

  if (!source) return 0;

  // 기존 기록을 수정할 수 있도록 복원된 재고 수량입니다.
  const available = source.editable_raw_quantity;

  // 재고 수량이 확인되지 않으면 0개로 표시합니다.
  if (available == null || !Number.isFinite(Number(available))) {
    return 0;
  }

  // 같은 생산 기록에 입력한 모든 사유의 수량을 합산합니다.
  const allocated = reasonRows.value
    .filter((row) => (
      row.stock_source === selected.stock_source &&
      String(row.stock_lot_id) === String(source.stock_lot_id)
    ))
    .reduce((sum, row) => sum + Number(row.quantity || 0), 0);

  // 선택한 생산 기록의 남은 수량만 반환합니다.
  return Math.max(0, Number(available) - allocated);
});

const dialogTitle = computed(() => ({
  carryover: '이월',
  loss: '로스',
  waste: '폐기',
}[props.type]));

// 업무명과 제품명을 한 줄에 표시해 어떤 제품을 수정하는지 헤더에서 바로 확인합니다.
const dialogHeaderTitle = computed(() => `${dialogTitle.value} - ${props.product?.name || '-'}`);

const dialogDescription = computed(() => ({
  carryover: '남은 수량 안에서 다음 영업일로 넘길 수량을 입력해 주세요.',
  loss: '남은 수량 안에서 로스 수량과 사유를 입력해 주세요. 로스가 없으면 ‘로스 없음’을 누르세요.',
  waste: '남은 수량 안에서 폐기 수량과 사유를 입력해 주세요. 폐기가 없으면 ‘폐기 없음’을 누르세요.',
}[props.type]));

const reasonOptions = computed(() => ({
  loss: props.lossReasons,
  waste: props.wasteReasons,
}[props.type] || []));

/**
 * 다른 업무에서 이미 사용한 재고를 표시합니다.
 * 과거 기타 출고 기록도 차감 수량에 포함하여
 * 기존 재고가 잘못 증가하지 않도록 합니다.
 */
const otherAllocationDetails = computed(() => {
  const row = props.product || {};

  const values = {
    carryover: [
      ['로스', Number(row.operational_loss ?? row.loss ?? 0)],
      ['폐기', Number(row.operational_waste ?? row.waste ?? 0)],
      ['기타', Number(row.other_outflow || 0)],
    ],
    loss: [
      ['폐기', Number(row.operational_waste ?? row.waste ?? 0)],
      ['이월 예정', Number(row.carryover_out || 0)],
      ['기타', Number(row.other_outflow || 0)],
    ],
    waste: [
      ['로스', Number(row.operational_loss ?? row.loss ?? 0)],
      ['이월 예정', Number(row.carryover_out || 0)],
      ['기타', Number(row.other_outflow || 0)],
    ],
  };

  return (values[props.type] || [])
    .filter(([, quantity]) => quantity > 0)
    .map(([label, quantity]) => ({ label, quantity }));
});

const otherAllocated = computed(() => otherAllocationDetails.value
  .reduce((sum, item) => sum + item.quantity, 0));

const remainingAvailable = computed(() => Math.max(0, available.value - otherAllocated.value));

/**
 * 선택한 생산 기록의 이월 가능 수량을 계산합니다.
 * 기존에 이월한 수량은 수정할 수 있도록 복원합니다.
 * 다른 생산 기록의 재고는 합산하지 않습니다.
 */
const carryoverSourceAvailable = computed(() => {
  if (props.type !== 'carryover') return 0;
  if (selectedCarryoverLotId.value == null) return 0;

  // 오늘 생산분과 기존 이월분을 구분합니다.
  const sourceType = carryoverSource.value === 'incoming'
    ? 'carryover'
    : 'today';

  // 사용자가 선택한 생산 기록만 조회합니다.
  const source = (props.product?.stock_sources || []).find((item) => (
    item.source === sourceType &&
    String(item.stock_lot_id) === String(selectedCarryoverLotId.value)
  ));

  if (!source) return 0;

  // 실제 남은 수량을 확인합니다.
  const remaining = Number(
    source.unallocated_quantity ?? source.remaining_quantity ?? 0
  );

  // 수정 중인 기존 이월 수량을 복원합니다.
  const existing = Number(source.carryover_out_quantity || 0);

  return Math.max(0, remaining + existing);
});

// 기존 이월 기록이 두 재고 출처에 나뉘어 있는지 확인합니다.
const hasMixedCarryoverSources = computed(() => {
  if (props.type !== 'carryover') {
    return false;
  }

  const sources = props.product?.stock_sources || [];

  const todayQuantity = sources
    .filter((source) => source.source === 'today')
    .reduce(
      (sum, source) => sum + Number(source.carryover_out_quantity || 0),
      0
    );

  const incomingQuantity = sources
    .filter((source) => source.source === 'carryover')
    .reduce(
      (sum, source) => sum + Number(source.carryover_out_quantity || 0),
      0
    );

  return todayQuantity > 0 && incomingQuantity > 0;
});

/**
 * 이월 출처를 변경하면 선택한 생산 기록과 수량을 초기화합니다.
 */
function changeCarryoverSource(value) {
  carryoverSource.value = value;
  selectedCarryoverLotId.value = null;
  carryoverQuantity.value = 0;
}

/**
 * 이월할 생산 기록을 변경하면 수량을 초기화합니다.
 */
function changeCarryoverLot(value) {
  selectedCarryoverLotId.value = value;
  carryoverQuantity.value = 0;
}

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

/**
 * 로스·폐기·기타 출고의 새 입력 항목을 생성합니다.
 * 재고 구분은 기본값을 유지합니다.
 * 생산 내역은 자동 선택하지 않고 직접 선택하도록 합니다.
 */
function emptyReason() {
  return {
    reason_code: '',
    reason_text: null,
    quantity: 0,
    stock_source: defaultStockSource.value,
    stock_lot_id: null,
  };
}

// 여러 사유 행의 수량 합계를 계산합니다.
function sumRows(rows) {
  return rows.reduce((sum, row) => sum + Number(row.quantity || 0), 0);
}

/**
 * 선택한 생산 기록에서만 로스·폐기 수량을 처리합니다.
 * 다른 생산 기록의 재고는 사용하지 않습니다.
 */
function allocateReasonRows(rows) {
  // 기존 처리 수량을 반영한 재고별 사용 가능 수량입니다.
  const sources = editableStockSources.value.map((source) => {
    // 실제 재고 수량이 미확인이면 다른 값으로 대체하지 않습니다.
    const rawAvailable = Object.prototype.hasOwnProperty.call(
      source,
      'editable_raw_quantity'
    )
      ? source.editable_raw_quantity
      : source.remaining_quantity;

    const available =
      rawAvailable == null || rawAvailable === ''
        ? null
        : Number(rawAvailable);

    return {
      ...source,
      available: Number.isFinite(available) ? available : null,
    };
  });

  const allocatedRows = [];

  for (const row of rows) {
    const quantity = Number(row.quantity);

    // 올바른 수량인지 확인합니다.
    if (
      row.quantity == null ||
      row.quantity === '' ||
      !Number.isSafeInteger(quantity) ||
      quantity < 0
    ) {
      throw new Error('처리 수량을 확인해 주세요.');
    }

    if (quantity === 0) {
      continue;
    }

    // 사용할 생산 기록이 선택되어 있어야 합니다.
    if (
      !row.stock_source ||
      row.stock_lot_id == null ||
      row.stock_lot_id === ''
    ) {
      throw new Error('사용할 생산 기록을 선택해 주세요.');
    }

    // 선택한 생산 기록만 찾습니다.
    const source = sources.find((item) => (
      item.source === row.stock_source &&
      item.stock_lot_id != null &&
      String(item.stock_lot_id) === String(row.stock_lot_id)
    ));

    if (!source) {
      throw new Error('선택한 생산 기록을 확인할 수 없습니다.');
    }

    // 남은 수량이 확인되지 않으면 저장하지 않습니다.
    if (source.available === null) {
      throw new Error('선택한 재고의 남은 수량을 확인할 수 없습니다.');
    }

    // 선택한 기록의 재고를 초과할 수 없습니다.
    if (quantity > source.available) {
      throw new Error('선택한 생산 기록의 남은 수량이 부족합니다.');
    }

    // 같은 재고를 여러 사유에서 사용해도 중복 차감되지 않도록 합니다.
    source.available -= quantity;

    allocatedRows.push({
      ...row,
      stock_source: source.source,
      stock_lot_id: source.stock_lot_id,
      quantity,
    });
  }

  return allocatedRows;
}

/**
 * 다이얼로그가 열릴 때 기존 기록을 복원합니다.
 *
 * 이월 기록이 있으면 실제 재고별 이월 출고 수량을 확인하여
 * 기존에 사용한 재고 출처를 우선 선택합니다.
 *
 * 저장된 이월 기록이 없으면 오늘 생산분을 기본 선택합니다.
 */
watch(() => props.modelValue, (value) => {
  if (!value) return;

  reasonRows.value = isReasonMode.value
    ? (savedDetails.value.length ? savedDetails.value.map(savedReasonRow) : [emptyReason()])
    : [];

  carryoverQuantity.value = Number(props.product?.carryover_out || 0);

  const stockSources = props.product?.stock_sources || [];

  const existingTodayCarryover = stockSources
    .filter((source) => source.source === 'today')
    .reduce(
      (sum, source) => sum + Number(source.carryover_out_quantity || 0),
      0
    );

  const existingIncomingCarryover = stockSources
    .filter((source) => source.source === 'carryover')
    .reduce(
      (sum, source) => sum + Number(source.carryover_out_quantity || 0),
      0
    );

  /**
   * 기존 이월 기록의 출처를 복원합니다.
   * 오늘 생산분과 기존 이월분이 모두 사용된 경우에는
   * 출처를 임의로 합치거나 재배정하지 않습니다.
   */
  if (existingTodayCarryover > 0 && existingIncomingCarryover === 0) {
    carryoverSource.value = 'today';
  } else if (existingIncomingCarryover > 0 && existingTodayCarryover === 0) {
    carryoverSource.value = 'incoming';
  } else {
    carryoverSource.value = 'today';
  }

  note.value = '';
  initialReasonSnapshot.value = JSON.stringify(reasonRows.value);

  // 기존 이월 기록이 하나일 때만 해당 생산 기록을 복원합니다.
  const existingLots = stockSources.filter(
    (source) => Number(source.carryover_out_quantity || 0) > 0
  );

  selectedCarryoverLotId.value = existingLots.length === 1
    ? existingLots[0].stock_lot_id
    : null;

  initialCarryoverSource.value = carryoverSource.value;
});

// 로스·폐기가 없을 때 0개 확인을 한 번의 행동으로 입력합니다.
function setZero() {
  reasonRows.value = [];
  askSave();
}

/**
 * 입력한 내용이 처음 열었을 때와 달라졌는지 확인합니다.
 */
function isDirty() {
  if (props.type === 'carryover') {
    const initialLotId = (props.product?.stock_sources || [])
      .filter((source) => Number(source.carryover_out_quantity || 0) > 0);

    const originalLotId = initialLotId.length === 1
      ? initialLotId[0].stock_lot_id
      : null;

    return carryoverQuantity.value !== Number(props.product?.carryover_out || 0)
      || carryoverSource.value !== initialCarryoverSource.value
      || String(selectedCarryoverLotId.value ?? '') !== String(originalLotId ?? '')
      || Boolean(note.value);
  }

  return JSON.stringify(reasonRows.value) !== initialReasonSnapshot.value
    || Boolean(note.value);
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

/**
 * 사유와 생산 기록별 재고 수량을 검증한 뒤 저장 확인창을 엽니다.
 * 로스·폐기·기타 출고는 선택한 생산 기록 기준으로 검사합니다.
 * 이월은 선택한 생산 기록의 이월 가능 수량을 검사합니다.
 */
function askSave() {
  // 사유와 수량을 모두 입력했는지 확인합니다.
  if (
    isReasonMode.value &&
    reasonRows.value.length > 0 &&
    reasonRows.value.some((row) => !row.reason_code || Number(row.quantity || 0) <= 0)
  ) {
    emit('error', '추가한 사유와 수량을 모두 입력해 주세요.');
    return;
  }

  // 로스·폐기는 사용할 생산 기록을 반드시 선택해야 합니다.
  if (
    (props.type === 'loss' || props.type === 'waste') &&
    reasonRows.value.some((row) => !row.stock_lot_id)
  ) {
    emit('error', '처리할 재고의 생산일을 선택해 주세요.');
    return;
  }

  // 직접입력 사유의 내용을 확인합니다.
  if (
    reasonRows.value.some(
      (row) => row.reason_code === 'other' && !String(row.reason_text || '').trim()
    )
  ) {
    emit('error', '직접입력 사유를 입력해주세요.');
    return;
  }

  // 선택한 생산 기록 ID와 재고 출처가 유효한지 확인합니다.
  if (invalidSourceRows.value.length) {
    emit('error', '선택한 재고 출처를 찾을 수 없습니다. 재고를 다시 선택해 주세요.');
    return;
  }

  // 생산 기록별로 입력한 수량이 잔여 재고를 초과하는지 확인합니다.
  if (sourceOverages.value.length) {
    emit('error', '선택한 생산일의 잔여 재고보다 입력한 수량이 많습니다.');
    return;
  }

  // 서로 다른 출처의 기존 이월 기록을 하나로 덮어쓰지 않습니다.
  if (hasMixedCarryoverSources.value) {
    emit(
      'error',
      '기존 이월 기록이 오늘 생산분과 기존 이월분에 나뉘어 있어 이 화면에서 수정할 수 없습니다.'
    );
    return;
  }

  // 이월만 전체 가용 수량을 추가 검사합니다.
  if (
    props.type === 'carryover' &&
    currentQuantity.value > remainingAvailable.value
  ) {
    emit('error', '입력한 수량이 남은 수량보다 많습니다.');
    return;
  }

  // 이월할 생산 기록의 남은 수량을 검사합니다.
  if (
    props.type === 'carryover' &&
    currentQuantity.value > carryoverSourceAvailable.value
  ) {
    emit(
      'error',
      carryoverSource.value === 'incoming'
        ? '기존 이월분의 남은 수량이 부족합니다.'
        : '오늘 생산분의 남은 수량이 부족합니다.'
    );
    return;
  }

  // 이월할 생산 기록이 선택되어 있는지 확인합니다.
  if (props.type === 'carryover' && currentQuantity.value > 0) {
    if (selectedCarryoverLotId.value == null) {
      emit('error', '이월할 생산 기록을 선택해 주세요.');
      return;
    }

    if (currentQuantity.value > carryoverSourceAvailable.value) {
      emit('error', '선택한 생산 기록의 이월 가능 수량이 부족합니다.');
      return;
    }
  }

  // 모든 검증을 통과하면 저장 확인창을 엽니다.
  confirmSave.value = true;
}

/**
 * 이월·로스·폐기 내역을 저장합니다.
 * 저장 전 수량을 확인하고, 실패하면 입력 내용을 유지합니다.
 */
async function save() {
  // 저장 중에는 중복 요청을 막습니다.
  if (saving.value) {
    return;
  }

  saving.value = true;

  try {
    if (!props.product?.id || !props.storeId || !props.workDate) {
      throw new Error('저장할 제품과 날짜를 확인해 주세요.');
    }

    // 모든 업무에 공통으로 필요한 정보입니다.
    const payload = {
      store_id: props.storeId,
      product_id: props.product.id,
      work_date: props.workDate,
      type: props.type,
      note: note.value || null,
    };

    if (props.type === 'carryover') {
      // 이월은 기존 저장 방식을 유지합니다.
      const quantity = Number(carryoverQuantity.value);

      if (
        !Number.isSafeInteger(quantity) ||
        quantity < 0
      ) {
        throw new Error('이월 수량을 확인해 주세요.');
      }

      payload.quantity = quantity;
      payload.carryover_source = carryoverSource.value;

      // 선택한 생산 기록을 서버에 전달합니다.
      payload.stock_lot_id = payload.quantity > 0
        ? selectedCarryoverLotId.value
        : null;
    } else {
      // 로스·폐기는 선택한 생산 기록에서만 처리합니다.
      payload.reasons = allocateReasonRows(reasonRows.value);
    }

    // 기존 저장 주소와 요청 방식을 유지합니다.
    const { data } = await window.axios.put(
      '/tillwhite/api/production-management/flow',
      payload
    );

    // 저장 성공 후 최신 현황을 반영합니다.
    confirmSave.value = false;
    open.value = false;

    emit(
      'saved',
      data.message || `${dialogTitle.value}을 저장했습니다.`,
      data.daily || null
    );
  } catch (error) {
    // 저장 실패 시 입력창은 닫지 않습니다.
    emit(
      'error',
      error.response?.data?.message ||
        error.message ||
        `${dialogTitle.value}을 저장하지 못했습니다.`
    );
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
