<template>
  <v-dialog v-model="open" max-width="620" :persistent="saving">
    <v-card rounded="lg" class="app-dialog-card">
      <v-card-title class="app-dialog-header d-flex align-center justify-space-between">
        <div>
          <div>{{ dialogTitle }}</div>
          <div class="app-supporting-text text-medium-emphasis mt-1">
            {{ product?.name || '-' }} · {{ dialogDescription }}
          </div>
        </div>
        <v-btn
          icon="mdi-close"
          size="small"
          variant="text"
          :disabled="saving"
          @click="requestClose"
        />
      </v-card-title>

      <v-card-text class="app-dialog-body">
        <v-alert
          v-if="remainingAvailable < currentQuantity"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4 app-supporting-alert"
        >
          입력한 수량이 남은 수량보다 많습니다.
        </v-alert>

        <div class="flow-summary mb-4">
          <div>
            <span>사용 가능</span>
            <strong>{{ available }}개</strong>
          </div>
          <div>
            <span>이미 기록됨</span>
            <strong>{{ otherAllocated }}개</strong>
          </div>
          <div>
            <span>남은 수량</span>
            <strong>{{ remainingAvailable }}개</strong>
          </div>
        </div>

        <template v-if="isReasonMode">
          <div class="flow-input-heading">{{ dialogTitle }} 수량과 사유</div>
          <ReasonRows v-model="reasonRows" :reason-options="reasonOptions" />
          <div class="reason-actions">
            <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="reasonRows.push(emptyReason())">사유 추가</v-btn>
            <v-btn size="small" variant="outlined" @click="setZero">{{ dialogTitle }} 없음</v-btn>
          </div>
        </template>

        <template v-else>
          <div class="flow-input-heading">다음 영업일로 넘길 수량</div>
          <v-number-input
            v-model="carryoverQuantity"
            label="다음 영업일 이월 수량"
            variant="outlined"
            :min="0"
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

        <v-textarea v-model="note" label="메모" variant="outlined" rows="2" class="mt-3" />
      </v-card-text>

      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" :disabled="saving" @click="requestClose">취소</v-btn>
        <v-spacer />
        <v-btn
          variant="flat"
          :loading="saving"
          :disabled="saving || remainingAvailable < currentQuantity"
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
  defineComponent,
  h,
  ref,
  resolveComponent,
  watch,
} from 'vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';

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

const carryoverSources = [
  { value: 'today', title: '오늘 생산분' },
  { value: 'incoming', title: '기존 이월분 · 재이월' },
];

const otherReasons = [
  { value: 'tasting', title: '시식' },
  { value: 'service', title: '고객 서비스' },
  { value: 'gift', title: '무료 증정' },
  { value: 'staff_use', title: '직원 사용' },
  { value: 'other', title: '기타' },
];

const open = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const isReasonMode = computed(() => props.type !== 'carryover');
const available = computed(() => Number(props.product?.production || 0) + Number(props.product?.carryover_in || 0));

const dialogTitle = computed(() => ({
  carryover: '이월',
  loss: '로스',
  waste: '폐기',
  other_outflow: '기타 출고',
}[props.type]));

const dialogDescription = computed(() => ({
  carryover: '다음 영업일로 넘길 수량만 확인합니다.',
  loss: '로스 사유와 수량만 기록합니다.',
  waste: '폐기 사유와 수량만 기록합니다.',
  other_outflow: '시식·서비스 등 기타 출고 수량을 기록합니다.',
}[props.type]));

const reasonOptions = computed(() => ({
  loss: props.lossReasons,
  waste: props.wasteReasons,
  other_outflow: otherReasons,
}[props.type] || []));

const otherAllocated = computed(() => {
  const row = props.product || {};
  const allocations = {
    carryover: Number(row.loss || 0) + Number(row.operational_waste ?? row.waste ?? 0) + Number(row.other_outflow || 0),
    loss: Number(row.operational_waste ?? row.waste ?? 0) + Number(row.other_outflow || 0) + Number(row.carryover_out || 0),
    waste: Number(row.loss || 0) + Number(row.other_outflow || 0) + Number(row.carryover_out || 0),
    other_outflow: Number(row.loss || 0) + Number(row.operational_waste ?? row.waste ?? 0) + Number(row.carryover_out || 0),
  };

  return allocations[props.type] || 0;
});

const remainingAvailable = computed(() => Math.max(0, available.value - otherAllocated.value));
const currentQuantity = computed(() => (
  props.type === 'carryover'
    ? Number(carryoverQuantity.value || 0)
    : sumRows(reasonRows.value)
));

const confirmMessage = computed(() => {
  const message = `${dialogTitle.value} ${currentQuantity.value}개로 저장하시겠습니까?`;

  if (props.type === 'carryover' && carryoverQuantity.value > 0 && carryoverSource.value === 'incoming') {
    return `이미 이월된 제품입니다. 다시 이월하시겠습니까?\n\n${message}`;
  }

  return message;
});

/** 사유별 수량을 입력하는 반복 UI를 로스·폐기·기타 출고에서 공유합니다. */
const ReasonRows = defineComponent({
  props: {
    modelValue: {
      type: Array,
      required: true,
    },
    reasonOptions: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['update:modelValue'],
  setup(innerProps, { emit: innerEmit }) {
    const update = (index, key, value) => {
      const next = innerProps.modelValue.map((row, rowIndex) => (
        rowIndex === index ? { ...row, [key]: value } : row
      ));
      innerEmit('update:modelValue', next);
    };

    const remove = (index) => {
      innerEmit('update:modelValue', innerProps.modelValue.filter((_, rowIndex) => rowIndex !== index));
    };

    const VSelect = resolveComponent('v-select');
    const VNumberInput = resolveComponent('v-number-input');
    const VBtn = resolveComponent('v-btn');

    return () => h('div', innerProps.modelValue.map((row, index) => h('div', {
      class: 'reason-row',
    }, [
      h(VSelect, {
        modelValue: row.reason_code,
        'onUpdate:modelValue': (value) => update(index, 'reason_code', value),
        items: innerProps.reasonOptions,
        'item-title': 'title',
        'item-value': 'value',
        label: '사유',
        variant: 'outlined',
        density: 'compact',
      }),
      h(VNumberInput, {
        modelValue: row.quantity,
        'onUpdate:modelValue': (value) => update(index, 'quantity', value),
        label: '수량',
        variant: 'outlined',
        density: 'compact',
        min: 1,
      }),
      h(VBtn, {
        icon: 'mdi-close',
        size: 'small',
        variant: 'text',
        onClick: () => remove(index),
      }),
    ])));
  },
});

/** 사유 행의 기본값을 생성합니다. */
function emptyReason() {
  return {
    reason_code: '',
    reason_text: null,
    quantity: 1,
  };
}

/** 여러 사유 행의 수량 합계를 계산합니다. */
function sumRows(rows) {
  return rows.reduce((sum, row) => sum + Number(row.quantity || 0), 0);
}

/** 다이얼로그가 열릴 때 선택 기능에 필요한 입력값만 초기화합니다. */
watch(() => props.modelValue, (value) => {
  if (!value) return;

  reasonRows.value = isReasonMode.value ? [emptyReason()] : [];
  carryoverQuantity.value = Number(props.product?.carryover_out || 0);
  carryoverSource.value = props.product?.carryover_in > 0 ? 'incoming' : 'today';
  note.value = '';
});

/** 로스·폐기가 없을 때 0개 확인을 한 번의 행동으로 입력합니다. */
function setZero() {
  reasonRows.value = [];
  askSave();
}

/** 사용자가 실제로 입력한 내용이 있는지 확인합니다. */
function isDirty() {
  if (props.type === 'carryover') {
    return carryoverQuantity.value !== Number(props.product?.carryover_out || 0) || Boolean(note.value);
  }

  return reasonRows.value.length !== 1 || reasonRows.value.some((row) => row.reason_code || Number(row.quantity || 0) !== 1) || Boolean(note.value);
}

/** 작성 중인 값이 있으면 확인창을 거친 뒤 닫습니다. */
function requestClose() {
  if (saving.value) return;

  if (isDirty()) {
    confirmClose.value = true;
    return;
  }

  forceClose();
}

/** 작성 취소를 확인한 뒤 현재 업무 다이얼로그를 닫습니다. */
function forceClose() {
  confirmClose.value = false;
  open.value = false;
}

/** 사유 필수값과 수량 범위를 확인한 뒤 저장 확인창을 엽니다. */
function askSave() {
  if (isReasonMode.value && reasonRows.value.length > 0 && reasonRows.value.some((row) => !row.reason_code || !row.quantity)) {
    emit('error', '추가한 사유와 수량을 모두 입력해주세요.');
    return;
  }

  if (currentQuantity.value > remainingAvailable.value) {
    emit('error', '입력한 수량이 남은 수량보다 많습니다.');
    return;
  }

  confirmSave.value = true;
}

/** 선택한 한 가지 업무만 서버에 저장하여 다른 수량 기록을 덮어쓰지 않습니다. */
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
    emit('saved', data.message || `${dialogTitle.value}을 저장했습니다.`);
  } catch (error) {
    emit('error', error.response?.data?.message || `${dialogTitle.value}을 저장하지 못했습니다.`);
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.flow-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.flow-summary > div {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
}

.flow-summary span,
.flow-summary strong {
  display: block;
}

.flow-summary span {
  font-size: 0.76rem;
  color: rgba(var(--v-theme-on-surface), 0.62);
}

.flow-input-heading { margin-bottom: 8px; font-size: .8rem; font-weight: 650; }
.reason-actions { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:2px; }

.reason-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 120px auto;
  gap: 8px;
  align-items: start;
}

@media (max-width: 520px) {
  .flow-summary {
    grid-template-columns: 1fr;
  }

  .flow-input-heading { margin-bottom: 8px; font-size: .8rem; font-weight: 650; }
.reason-actions { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:2px; }

.reason-row {
    grid-template-columns: minmax(0, 1fr) 100px auto;
  }
}
</style>
