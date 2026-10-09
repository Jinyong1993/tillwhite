
<template>
  <div class="production-reason-list">
    <article
      v-for="(row, index) in modelValue"
      :key="index"
      class="production-reason-card"
    >
      <!-- 처리할 재고 종류를 선택합니다. -->
      <v-select
        v-if="showStockSource"
        :model-value="row.stock_source"
        :items="stockSourceOptions"
        item-title="title"
        item-value="value"
        label="재고 구분"
        variant="outlined"
        density="compact"
        hide-details="auto"
        @update:model-value="updateStockSource(index, $event)"
      />

      <!-- 오늘 생산과 이월 재고를 각각의 생산 기록으로 구분합니다. -->
      <v-select
        v-if="showStockSource && row.stock_source"
        :model-value="row.stock_lot_id"
        :items="stockLotOptions(row.stock_source)"
        item-title="title"
        item-value="value"
        label="생산 기록 선택"
        placeholder="처리할 생산 기록을 선택하세요"
        variant="outlined"
        density="compact"
        hide-details="auto"
        :no-data-text="'선택할 생산 기록이 없습니다.'"
        @update:model-value="updateStockLot(index, $event)"
      />

      <!-- 로스 또는 폐기 사유를 선택합니다. -->
      <v-select
        :model-value="row.reason_code"
        :items="displayReasonOptions"
        item-title="title"
        item-value="value"
        label="사유"
        variant="outlined"
        density="compact"
        hide-details="auto"
        @update:model-value="updateReason(index, $event)"
      />

      <!-- 직접입력을 선택한 경우에만 사유를 입력합니다. -->
      <v-text-field
        v-if="row.reason_code === 'other'"
        :model-value="row.reason_text"
        label="사유 직접입력"
        variant="outlined"
        density="compact"
        hide-details="auto"
        @update:model-value="update(index, 'reason_text', $event)"
      />

      <!-- 선택한 생산 기록의 남은 수량 안에서 조절합니다. -->
      <div class="production-reason-quantity">
        <span class="production-reason-quantity__label">
          수량
        </span>

        <div class="production-reason-quantity__control">
          <v-btn
            icon="mdi-minus"
            size="small"
            variant="outlined"
            :disabled="Number(row.quantity || 0) <= 0"
            aria-label="수량 줄이기"
            @click="changeQuantity(index, -1)"
          />

          <strong>
            {{ Number(row.quantity || 0) }}개
          </strong>

          <v-btn
            icon="mdi-plus"
            size="small"
            variant="outlined"
            :disabled="Number(row.quantity || 0) >= rowMaximum(row, index)"
            aria-label="수량 늘리기"
            @click="changeQuantity(index, 1)"
          />
        </div>

        <small v-if="showStockSource">
          {{ stockAvailabilityText(row) }}
        </small>
      </div>

      <!-- 선택한 사유 기록을 제거합니다. -->
      <div class="production-reason-remove">
        <v-btn
          prepend-icon="mdi-close"
          size="small"
          variant="text"
          @click="remove(index)"
        >
          삭제
        </v-btn>
      </div>
    </article>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: Array,
    required: true,
  },
  reasonOptions: {
    type: Array,
    default: () => [],
  },
  maxQuantity: {
    type: Number,
    default: 0,
  },
  stockSources: {
    type: Array,
    default: () => [],
  },
  workDate: {
    type: String,
    default: '',
  },
  showStockSource: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

// 기존 사유 코드를 유지하고 화면 명칭만 정리합니다.
const displayReasonOptions = computed(() =>
  props.reasonOptions.map((option) => ({
    ...option,
    title: option.value === 'other' ? '직접입력' : option.title,
  }))
);

// 오늘 생산과 이월 재고를 구분합니다.
const stockSourceOptions = computed(() => [
  { value: 'today', title: '오늘 생산' },
  { value: 'carryover', title: '이월 재고' },
]);

/**
 * 생산 기록마다 실제 사용 가능한 수량을 표시합니다.
 */
function stockLotOptions(type) {
  return props.stockSources
    .filter((source) => (
      source.source === type &&
      source.stock_lot_id != null
    ))
    .map((source) => {
      const quantity = sourceAvailableQuantity(source);

      const amountText = quantity === null
        ? '미확인'
        : quantity < 0
          ? `초과 처리 ${Math.abs(quantity)}개`
          : `${quantity}개`;

      return {
        value: source.stock_lot_id,
        title: `${formatOriginDate(source.origin_production_date)} 생산 · ${amountText}`,
      };
    });
}

/**
 * 선택한 생산 기록을 찾습니다.
 * 다른 생산 기록의 재고는 사용하지 않습니다.
 */
function findStockSource(row) {
  if (
    !row?.stock_source ||
    row.stock_lot_id == null ||
    row.stock_lot_id === ''
  ) {
    return null;
  }

  return props.stockSources.find((source) => (
    source.source === row.stock_source &&
    source.stock_lot_id != null &&
    String(source.stock_lot_id) === String(row.stock_lot_id)
  )) || null;
}

/**
 * 실제 계산된 재고 수량을 반환합니다.
 * 미확인 상태와 0개를 구분합니다.
 */
function sourceAvailableQuantity(source) {
  if (!source) {
    return null;
  }

  const rawQuantity = Object.prototype.hasOwnProperty.call(
    source,
    'editable_raw_quantity'
  )
    ? source.editable_raw_quantity
    : (
        Object.prototype.hasOwnProperty.call(
          source,
          'unallocated_quantity'
        )
          ? source.unallocated_quantity
          : source.remaining_quantity
      );

  if (rawQuantity == null || rawQuantity === '') {
    return null;
  }

  const quantity = Number(rawQuantity);

  return Number.isFinite(quantity) ? quantity : null;
}

// 수량이 확인되지 않았으면 0개로 표시하지 않습니다.
function validQuantity(value) {
  if (value == null || value === '') {
    return null;
  }

  const quantity = Number(value);

  return Number.isFinite(quantity) && quantity >= 0
    ? quantity
    : null;
}

// 직원에게 보여줄 수량을 만듭니다.
function quantityText(value) {
  const quantity = validQuantity(value);

  return quantity === null
    ? '미확인'
    : `${quantity}개`;
}

// 선택한 사유 행만 변경합니다.
function update(index, key, value) {
  const next = props.modelValue.map((row, rowIndex) => (
    rowIndex === index
      ? { ...row, [key]: value }
      : row
  ));

  emit('update:modelValue', next);
}

/**
 * 재고 종류를 변경합니다.
 * 이전에 선택했던 생산 기록과 수량을 초기화합니다.
 */
function updateStockSource(index, value) {
  const next = props.modelValue.map((row, rowIndex) => (
    rowIndex === index
      ? {
          ...row,
          stock_source: value,
          stock_lot_id: null,
          quantity: 0,
        }
      : row
  ));

  emit('update:modelValue', next);
}

/**
 * 사용할 생산 기록을 변경합니다.
 * 다른 기록에 입력한 수량이 남지 않도록 초기화합니다.
 */
function updateStockLot(index, value) {
  const next = props.modelValue.map((row, rowIndex) => {
    if (rowIndex !== index) {
      return row;
    }

    if (
      row.stock_lot_id != null &&
      value != null &&
      String(row.stock_lot_id) === String(value)
    ) {
      return row;
    }

    return {
      ...row,
      stock_lot_id: value,
      quantity: 0,
    };
  });

  emit('update:modelValue', next);
}

// 직접입력이 아닌 사유로 바꾸면 기존 입력 문구를 비웁니다.
function updateReason(index, value) {
  const next = props.modelValue.map((row, rowIndex) => {
    if (rowIndex !== index) {
      return row;
    }

    return {
      ...row,
      reason_code: value,
      reason_text: value === 'other' ? row.reason_text : null,
    };
  });

  emit('update:modelValue', next);
}

/**
 * 수량을 한 개씩 변경합니다.
 * 증가할 때만 선택한 재고의 최대 수량을 적용합니다.
 */
function changeQuantity(index, amount) {
  const row = props.modelValue[index];

  if (!row) {
    return;
  }

  const current = Number(row.quantity || 0);

  if (!Number.isFinite(current)) {
    return;
  }

  const nextQuantity = amount > 0
    ? Math.min(
        rowMaximum(row, index),
        Math.max(0, current + amount)
      )
    : Math.max(0, current + amount);

  if (nextQuantity === current) {
    return;
  }

  update(index, 'quantity', nextQuantity);
}

/**
 * 같은 생산 기록을 사용하는 다른 사유의 수량을 계산합니다.
 */
function usedByOtherRows(row, rowIndex = -1) {
  if (
    row.stock_lot_id == null ||
    row.stock_lot_id === ''
  ) {
    return 0;
  }

  return props.modelValue.reduce((sum, item, index) => {
    if (index === rowIndex) {
      return sum;
    }

    if (
      item.stock_source !== row.stock_source ||
      item.stock_lot_id == null ||
      String(item.stock_lot_id) !== String(row.stock_lot_id)
    ) {
      return sum;
    }

    return sum + (validQuantity(item.quantity) ?? 0);
  }, 0);
}

/**
 * 선택한 생산 기록의 최대 입력 수량을 계산합니다.
 */
function rowMaximum(row, rowIndex = -1) {
  if (!props.showStockSource) {
    return Math.max(0, Number(props.maxQuantity) || 0);
  }

  const source = findStockSource(row);
  const available = sourceAvailableQuantity(source);

  if (available === null) {
    return 0;
  }

  const used = usedByOtherRows(row, rowIndex);

  return Math.max(0, available - used);
}

/**
 * 선택한 생산 기록의 입력 후 남는 수량을 표시합니다.
 */
function stockAvailabilityText(row) {
  if (!row.stock_source) {
    return '재고 구분을 선택해 주세요.';
  }

  if (row.stock_lot_id == null || row.stock_lot_id === '') {
    return '생산 기록을 선택해 주세요.';
  }

  const source = findStockSource(row);

  if (!source) {
    return '선택한 생산 기록을 확인할 수 없습니다.';
  }

  const available = sourceAvailableQuantity(source);

  if (available === null) {
    return '남은 수량을 확인할 수 없습니다.';
  }

  // 같은 생산 기록으로 입력한 모든 사유를 합산합니다.
  const allocated = props.modelValue.reduce((sum, item) => {
    if (
      item.stock_source !== row.stock_source ||
      item.stock_lot_id == null ||
      String(item.stock_lot_id) !== String(row.stock_lot_id)
    ) {
      return sum;
    }

    return sum + (validQuantity(item.quantity) ?? 0);
  }, 0);

  const remaining = available - allocated;

  if (remaining < 0) {
    return `입력 수량이 남은 재고보다 ${Math.abs(remaining)}개 많습니다.`;
  }

  return `${formatOriginDate(source.origin_production_date)} 생산 · ${remaining}개 사용 가능`;
}

// 최초 생산일을 월/일 형태로 표시합니다.
function formatOriginDate(date) {
  const [, month, day] = String(date || '').split('-');

  return month && day
    ? `${Number(month)}/${Number(day)}`
    : '날짜 미확인';
}

// 선택한 사유만 삭제합니다.
function remove(index) {
  emit(
    'update:modelValue',
    props.modelValue.filter((_, rowIndex) => rowIndex !== index)
  );
}
</script>
