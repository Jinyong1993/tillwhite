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
          <div>
            <span>총 수량</span>
            <strong>{{ available }}개</strong>
          </div>
          <p>{{ dialogDescription }}</p>
        </div>

        <div class="flow-guide">
          <div>
            <span>폐기 입력 가능 수량</span>
            <strong>
              {{ Math.max(0, remainingAvailable - currentQuantity) }}개
            </strong>
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
            :max="Math.min(remainingAvailable, carryoverSourceAvailable)"
          />
          <v-select
            v-if="product?.carryover_in > 0"
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
            || remainingAvailable < currentQuantity
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

// 다이얼로그를 열었을 때의 이월 출처를 보관합니다.
const initialCarryoverSource = ref('today');

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

/**
 * 사유별 입력 수량이 실제 사용 가능한 재고를 초과하는지 검사합니다.
 *
 * 오늘 생산은 여러 생산 기록의 잔여 수량을 합산하여 검사하고,
 * 이월 재고는 사용자가 직접 선택한 재고 기록별로 검사합니다.
 *
 * 동일한 재고를 사용하는 여러 사유 행의 수량도 함께 계산하여
 * 중복 입력으로 재고를 초과하는 상황을 방지합니다.
 *
 * @returns {Array} 사용 가능 수량을 초과한 재고 구분 목록
 */
const sourceOverages = computed(() => {
  /**
   * 재고별 수량 검사가 필요한 업무에만 적용합니다.
   *
   * 로스, 폐기, 기타 출고 이외의 업무에는 영향을 주지 않도록
   * 기존 검사 대상과 반환 방식을 유지합니다.
   */
  if (
      !isReasonMode.value
      || !['loss', 'waste', 'other_outflow'].includes(props.type)
  ) {
      return [];
  }

  /**
   * 동일한 재고를 사용하는 사유 행들의 입력 수량을 합산합니다.
   *
   * 오늘 생산은 하나의 그룹으로 관리하고,
   * 이월 재고는 재고 기록 ID별로 구분하여 관리합니다.
   */
  const groups = new Map();

  for (const row of reasonRows.value) {
      /**
       * 현재 사유 행이 사용하는 재고 그룹을 결정합니다.
       *
       * 오늘 생산은 개별 생산 차수와 관계없이 합산하며,
       * 이월 재고는 사용자가 선택한 기록을 그대로 유지합니다.
       */
      const key = row.stock_source === 'today'
          ? 'today'
          : `carryover:${row.stock_lot_id}`;

      // 같은 재고 그룹에 입력된 수량을 누적합니다.
      groups.set(
          key,
          (groups.get(key) || 0) + Number(row.quantity || 0),
      );
  }

  /**
   * 각 재고 그룹의 입력 수량과 사용 가능 수량을 비교합니다.
   *
   * 입력 수량이 실제 재고보다 많은 그룹만 반환하여
   * 기존 저장 차단 기능에서 초과 여부를 확인할 수 있게 합니다.
   */
  return [...groups.entries()].filter(([key, quantity]) => {
      /**
       * 현재 그룹에서 사용할 수 있는 생산 기록을 조회합니다.
       *
       * 오늘 생산은 모든 당일 생산 기록을 포함하고,
       * 이월 재고는 선택한 재고 기록만 포함합니다.
       */
      const available = editableStockSources.value
          .filter((source) => {
              if (key === 'today') {
                  return source.source === 'today';
              }

              return key === `carryover:${source.stock_lot_id}`;
          })
          .reduce(
              (sum, source) => (
                  sum + Number(source.remaining_quantity || 0)
              ),
              0,
          );

      // 입력 수량이 사용 가능 수량보다 크면 초과 항목으로 반환합니다.
      return quantity > available;
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

/**
 * 선택한 재고 출처에서 이월할 수 있는 수량을 계산합니다.
 *
 * 오늘 생산분과 기존 이월분을 분리하여 계산하며,
 * 이미 저장된 이월 수량은 해당 출처에만 복원합니다.
 *
 * 다른 출처의 재고를 자동으로 사용하지 않습니다.
 */
const carryoverSourceAvailable = computed(() => {
  if (props.type !== 'carryover') {
    return 0;
  }

  const selectedSource = carryoverSource.value === 'incoming'
    ? 'carryover'
    : 'today';

  return (props.product?.stock_sources || [])
    .filter((source) => source.source === selectedSource)
    .reduce((sum, source) => {
      const remaining = Number(
        source.unallocated_quantity ?? source.remaining_quantity ?? 0
      );

      const existingCarryover = Number(
        source.carryover_out_quantity || 0
      );

      return sum + Math.max(0, remaining + existingCarryover);
    }, 0);
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

/**
 * 사유별 입력 수량을 실제 생산 기록별로 배분합니다.
 *
 * 기존 기록은 원래 연결된 생산 기록에 우선 배분하여
 * 불필요하게 여러 기록으로 분할되는 문제를 방지합니다.
 *
 * 오늘 생산의 신규 입력 또는 기존 기록의 초과 수량은
 * 다른 당일 생산 기록의 사용 가능 수량에 맞춰 배분합니다.
 *
 * 이월 재고는 사용자가 선택한 생산 기록만 사용합니다.
 *
 * @param {Array} rows 사용자가 입력한 사유별 수량 목록
 * @returns {Array} 생산 기록별로 배분한 저장용 사유 목록
 * @throws {Error} 사용 가능한 재고가 부족한 경우
 */
function allocateReasonRows(rows) {
  /**
   * 원본 재고를 변경하지 않도록 별도 객체를 생성합니다.
   *
   * 각 생산 기록의 사용 가능 수량은
   * 배분 과정에서 독립적으로 관리합니다.
   */
  const sources = editableStockSources.value.map((source) => ({
    ...source,
    available: Math.max(
      0,
      Number(source.remaining_quantity || 0),
    ),
  }));

  // 실제 서버에 전달할 사유 목록입니다.
  const allocatedRows = [];

  /**
   * 첫 번째 단계에서는 기존 생산 기록과의 연결을 우선 유지합니다.
   *
   * 다른 사유 행이 먼저 재고를 사용하여
   * 기존 기록의 배분이 변경되는 상황을 방지합니다.
   */
  const pendingRows = [];

  for (const row of rows) {
    let remaining = Number(row.quantity || 0);

    // 수량이 없는 행은 저장 대상에서 제외합니다.
    if (remaining <= 0) {
      continue;
    }

    /**
     * 사용자가 선택한 재고 기록을 찾습니다.
     *
     * 오늘 생산은 오늘 생산 기록에서만 찾고,
     * 이월 재고는 선택한 이월 기록에서만 찾습니다.
     */
    const selectedSource = sources.find((source) => (
      source.source === row.stock_source
      && Number(source.stock_lot_id) === Number(row.stock_lot_id)
    ));

    /**
     * 선택된 생산 기록에 우선 수량을 배분합니다.
     *
     * 기존 기록이 5개에서 4개로 변경된 경우
     * 같은 생산 기록에 4개를 그대로 유지합니다.
     */
    if (selectedSource && selectedSource.available > 0) {
      const quantity = Math.min(
        remaining,
        selectedSource.available,
      );

      allocatedRows.push({
        ...row,
        stock_source: selectedSource.source,
        stock_lot_id: selectedSource.stock_lot_id,
        quantity,
      });

      selectedSource.available -= quantity;
      remaining -= quantity;
    }

    /**
     * 선택된 기록에 배분하지 못한 수량은
     * 두 번째 단계에서 처리합니다.
     */
    if (remaining > 0) {
      pendingRows.push({
        row,
        remaining,
      });
    }
  }

  /**
   * 두 번째 단계에서는 아직 배분하지 못한 수량을 처리합니다.
   *
   * 오늘 생산은 다른 당일 생산 기록을 사용할 수 있지만,
   * 이월 재고는 사용자가 선택한 생산 기록 외에는 사용하지 않습니다.
   */
  for (const pending of pendingRows) {
    const { row } = pending;
    let remaining = pending.remaining;

    const candidates = sources.filter((source) => {
      if (row.stock_source === 'today') {
        return source.source === 'today';
      }

      return source.source === 'carryover'
        && Number(source.stock_lot_id) === Number(row.stock_lot_id);
    });

    /**
     * 사용 가능한 생산 기록을 순서대로 확인하면서
     * 아직 배분하지 못한 수량을 처리합니다.
     */
    for (const source of candidates) {
      if (remaining <= 0) {
        break;
      }

      const quantity = Math.min(
        remaining,
        source.available,
      );

      if (quantity <= 0) {
        continue;
      }

      allocatedRows.push({
        ...row,
        stock_source: source.source,
        stock_lot_id: source.stock_lot_id,
        quantity,
      });

      source.available -= quantity;
      remaining -= quantity;
    }

    /**
     * 모든 생산 기록을 확인한 후에도 수량이 남으면
     * 재고 부족 오류를 발생시켜 저장을 중단합니다.
     */
    if (remaining > 0) {
      throw new Error(
        '선택한 재고의 사용 가능 수량이 부족합니다.',
      );
    }
  }

  // 기존 기록을 우선 유지하면서 배분한 결과를 반환합니다.
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
  initialCarryoverSource.value = carryoverSource.value;
});

// 로스·폐기가 없을 때 0개 확인을 한 번의 행동으로 입력합니다.
function setZero() {
  reasonRows.value = [];
  askSave();
}

// 사용자가 실제로 입력한 내용이 있는지 확인합니다.
function isDirty() {
  if (props.type === 'carryover') {
    return carryoverQuantity.value !== Number(props.product?.carryover_out || 0)
      || (
        Number(carryoverQuantity.value || 0) > 0
        && carryoverSource.value !== initialCarryoverSource.value
      )
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

  /**
   * 기존 이월 기록이 두 출처에 나뉘어 있는 경우
   * 단일 출처로 잘못 덮어쓰는 것을 방지합니다.
   */
  if (hasMixedCarryoverSources.value) {
    emit(
      'error',
      '기존 이월 기록이 오늘 생산분과 기존 이월분에 나뉘어 있어 이 화면에서 수정할 수 없습니다.'
    );
    return;
  }

  if (currentQuantity.value > remainingAvailable.value) {
    emit('error', '입력한 수량이 남은 수량보다 많습니다.');
    return;
  }

  /**
   * 이월은 사용자가 선택한 재고 출처의 수량만 검사합니다.
   * 선택한 출처의 재고가 부족하더라도
   * 다른 출처의 재고로 자동 대체하지 않습니다.
   */
  if (
    props.type === 'carryover'
    && currentQuantity.value > carryoverSourceAvailable.value
  ) {
    emit(
      'error',
      carryoverSource.value === 'incoming'
        ? '기존 이월분의 남은 수량이 부족합니다.'
        : '오늘 생산분의 남은 수량이 부족합니다.'
    );
    return;
  }

  confirmSave.value = true;
}

/**
 * 현재 선택한 업무의 입력 내용을 서버에 저장합니다.
 *
 * 이월 업무는 기존 수량과 이월 구분을 그대로 전송하며,
 * 로스·폐기·기타 출고는 사유별 입력 수량을 생산 기록별로 배분합니다.
 *
 * 저장에 성공하면 다이얼로그를 닫고 최신 데이터를 부모 화면에 전달합니다.
 * 오류가 발생하면 다이얼로그를 유지하고 사용자에게 원인을 안내합니다.
 *
 * @returns {Promise<void>} 저장 요청 처리 결과
 */
async function save() {
  // 중복 저장을 방지하기 위해 저장 진행 상태를 활성화합니다.
  saving.value = true;

  try {
      /**
       * 모든 업무에서 공통으로 사용하는 저장 데이터를 구성합니다.
       *
       * 점포, 제품, 작업 날짜, 업무 구분과 특이사항을 전달하며,
       * 실제 수량 정보는 업무 유형에 따라 별도로 추가합니다.
       */
      const payload = {
          store_id: props.storeId,
          product_id: props.product.id,
          work_date: props.workDate,
          type: props.type,
          note: note.value || null,
      };

      /**
       * 이월 업무는 기존 저장 방식을 그대로 유지합니다.
       *
       * 이월 수량과 재고 출처를 전송하며,
       * 로스·폐기 등의 사유별 재고 배분 로직은 적용하지 않습니다.
       */
      if (props.type === 'carryover') {
          payload.quantity = Number(carryoverQuantity.value || 0);
          payload.carryover_source = carryoverSource.value;
      } else {
          /**
           * 사유별 입력 수량을 실제 생산 기록별로 배분합니다.
           *
           * 오늘 생산이 여러 차례 이루어진 경우 각 생산 기록의
           * 사용 가능 수량에 맞춰 수량을 나누어 저장합니다.
           *
           * 이월 재고는 사용자가 선택한 재고 기록만 사용하며,
           * 기존 사유 코드와 직접입력 내용은 그대로 유지합니다.
           *
           * 재고가 부족하면 오류를 발생시켜
           * 잘못된 수량이 서버에 저장되지 않도록 합니다.
           */
          payload.reasons = allocateReasonRows(reasonRows.value);
      }

      /**
       * 구성한 데이터를 생산관리 API에 전송합니다.
       *
       * 기존 PUT 요청 방식과 API 주소를 유지하여
       * 다른 업무의 저장 처리에 영향을 주지 않도록 합니다.
       */
      const { data } = await window.axios.put(
          '/tillwhite/api/production-management/flow',
          payload,
      );

      /**
       * 저장이 성공하면 확인창과 업무 다이얼로그를 닫습니다.
       *
       * 서버에서 반환한 최신 일별 데이터를 부모 화면에 전달하여
       * 저장된 수량이 화면에 반영되도록 합니다.
       */
      confirmSave.value = false;
      open.value = false;

      emit(
          'saved',
          data.message || `${dialogTitle.value}을 저장했습니다.`,
          data.daily || null,
      );
  } catch (error) {
      /**
       * 저장 과정에서 발생한 오류를 사용자에게 안내합니다.
       *
       * 서버에서 반환한 오류 메시지를 우선 사용하고,
       * 재고 배분 함수에서 발생한 오류도 표시할 수 있도록 합니다.
       *
       * 오류가 발생한 경우에는 다이얼로그를 닫지 않아
       * 사용자가 입력 내용을 확인하고 수정할 수 있게 합니다.
       */
      emit(
          'error',
          error.response?.data?.message
              || error.message
              || `${dialogTitle.value}을 저장하지 못했습니다.`,
      );
  } finally {
      /**
       * 저장 성공 여부와 관계없이 진행 상태를 해제합니다.
       *
       * 오류가 발생한 뒤에도 저장 버튼을 다시 사용할 수 있도록
       * 로딩 상태가 남지 않게 처리합니다.
       */
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
