<template>
  <div class="production-reason-list">
    <article
        v-for="(row, index) in modelValue"
        :key="index"
        class="production-reason-card"
    >
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

      <v-select
          v-if="showStockSource && row.stock_source === 'carryover'"
          :model-value="row.stock_lot_id"
          :items="carryoverLotOptions"
          item-title="title"
          item-value="value"
          label="원 생산일"
          variant="outlined"
          density="compact"
          hide-details="auto"
          @update:model-value="update(index, 'stock_lot_id', $event)"
      />

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

      <v-text-field
          v-if="row.reason_code === 'other'"
          :model-value="row.reason_text"
          label="사유 직접입력"
          variant="outlined"
          density="compact"
          hide-details="auto"
          @update:model-value="update(index, 'reason_text', $event)"
      />

      <div class="production-reason-quantity">
        <span class="production-reason-quantity__label">수량</span>
        <div class="production-reason-quantity__control">
          <v-btn
              icon="mdi-minus"
              size="small"
              variant="outlined"
              :disabled="Number(row.quantity || 0) <= 0"
              aria-label="수량 줄이기"
              @click="changeQuantity(index, -1)"
          />
          <strong>{{ Number(row.quantity || 0) }}개</strong>
          <v-btn
              icon="mdi-plus"
              size="small"
              variant="outlined"
              :disabled="Number(row.quantity || 0) >= rowMaximum(row, index)"
              aria-label="수량 늘리기"
              @click="changeQuantity(index, 1)"
          />
        </div>
        <small v-if="showStockSource">{{ stockAvailabilityText(row) }}</small>
      </div>

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

// 서버의 기존 other 코드는 유지하면서 화면 명칭만 현장 사용자가 이해하기 쉽게 통일합니다.
const displayReasonOptions = computed(() => props.reasonOptions.map((option) => ({
    ...option,
    title: option.value === 'other' ? '직접입력' : option.title,
})));

// 당일 생산과 이월 재고를 먼저 구분해 사용자가 어느 재고를 처리하는지 명확하게 합니다.
const stockSourceOptions = computed(() => [
    { value: 'today', title: '오늘 생산' },
    { value: 'carryover', title: '이월 재고' },
]);

// 여러 날의 이월 재고가 섞여 있어도 원 생산일과 남은 수량을 한 번에 확인할 수 있게 표시합니다.
const carryoverLotOptions = computed(() => props.stockSources
    .filter((source) => source.source === 'carryover')
    .map((source) => ({
        value: source.stock_lot_id,
        title: `${formatOriginDate(source.origin_production_date)} 생산 · ${source.remaining_quantity}개 남음`,
    })));

// 한 행만 복사해 갱신하여 다른 사유 입력값을 건드리지 않습니다.
function update(index, key, value) {
    const next = props.modelValue.map((row, rowIndex) => (
        rowIndex === index ? { ...row, [key]: value } : row
    ));

    emit('update:modelValue', next);
}

// 재고 구분을 바꾸면 해당 구분에서 사용할 수 있는 첫 재고 lot를 자동 선택합니다.
function updateStockSource(index, value) {
    const candidates = props.stockSources.filter((source) => source.source === value);
    const nextLotId = candidates[0]?.stock_lot_id ?? null;
    const next = props.modelValue.map((row, rowIndex) => (
        rowIndex === index
            ? { ...row, stock_source: value, stock_lot_id: nextLotId, quantity: 0 }
            : row
    ));

    emit('update:modelValue', next);
}

// 직접입력 이외의 사유로 바꾸면 이전 자유 입력 문구가 함께 저장되지 않도록 비웁니다.
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

// 이월 다이얼로그처럼 현재 수량을 가운데 명확하게 보여주며 한 개씩 증감합니다.
function changeQuantity(index, amount) {
    const row = props.modelValue[index];
    const nextQuantity = Math.min(
        rowMaximum(row, index),
        Math.max(0, Number(row.quantity || 0) + amount),
    );
    update(index, 'quantity', nextQuantity);
}

// 선택한 재고 출처의 남은 수량을 해당 사유 행의 최대값으로 사용합니다.
function rowMaximum(row, rowIndex = -1) {
    if (!props.showStockSource) {
        return Math.max(0, Number(props.maxQuantity || 0));
    }

    const source = props.stockSources.find((item) => item.stock_lot_id === row.stock_lot_id);
    const allocatedByOtherRows = props.modelValue.reduce((sum, item, index) => {
        if (index === rowIndex || Number(item.stock_lot_id) !== Number(row.stock_lot_id)) {
            return sum;
        }

        return sum + Number(item.quantity || 0);
    }, 0);
    return Math.max(0, Number(source?.remaining_quantity ?? props.maxQuantity ?? 0) - allocatedByOtherRows);
}

function stockAvailabilityText(row) {
    const source = props.stockSources.find((item) => item.stock_lot_id === row.stock_lot_id);
    if (!source) {
        return row.stock_source === 'carryover' ? '사용할 이월 재고를 선택해 주세요.' : '사용 가능한 재고를 확인해 주세요.';
    }

    return `${formatOriginDate(source.origin_production_date)} 생산 · ${source.remaining_quantity}개 사용 가능`;
}

function formatOriginDate(date) {
    const [, month, day] = String(date || '').split('-');
    return month && day ? `${Number(month)}/${Number(day)}` : '-';
}

// 사용자가 선택한 사유 카드만 제거합니다.
function remove(index) {
    emit('update:modelValue', props.modelValue.filter((_, rowIndex) => rowIndex !== index));
}
</script>
