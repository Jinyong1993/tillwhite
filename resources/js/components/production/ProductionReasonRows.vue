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
          label="최초 생산일"
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

/**
 * 이월 재고의 최초 생산일과 기록된 잔여 수량을 선택 항목으로 구성합니다.
 *
 * 여러 생산일의 재고가 존재하더라도 각각 구분하여 표시하며,
 * 실제 재고 수량이나 선택 동작은 변경하지 않습니다.
 */
const carryoverLotOptions = computed(() => {
  return props.stockSources
    .filter((source) => source.source === 'carryover')
    .map((source) => ({
      value: source.stock_lot_id,
      title: `${formatOriginDate(source.origin_production_date)} 생산 · 기록된 잔여 ${source.remaining_quantity}개`,
    }));
});

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

/**
 * 선택한 재고 구분에서 현재 사유 행에 입력할 수 있는 최대 수량을 계산합니다.
 *
 * 오늘 생산은 같은 날짜에 여러 번 생산했더라도
 * 모든 생산 기록의 사용 가능 수량을 합산합니다.
 *
 * 이월 재고는 사용자가 선택한 최초 생산일의 재고만 계산하여
 * 다른 생산일의 이월 재고가 임의로 사용되지 않도록 합니다.
 *
 * 동일한 재고를 사용하는 다른 사유 행의 입력 수량도 차감하여
 * 여러 사유에서 같은 재고를 중복 사용하는 상황을 방지합니다.
 *
 * @param {Object} row 현재 수량을 입력하는 사유 행
 * @param {number} rowIndex 현재 사유 행의 배열 인덱스
 * @returns {number} 현재 사유 행에 입력할 수 있는 최대 수량
 */
function rowMaximum(row, rowIndex = -1) {
    /**
     * 재고 구분을 사용하지 않는 화면은 기존 계산 방식을 유지합니다.
     *
     * 부모 컴포넌트에서 전달받은 최대 수량을 그대로 사용하며,
     * 음수 수량이 입력되지 않도록 최소값을 0으로 제한합니다.
     */
    if (!props.showStockSource) {
        return Math.max(
            0,
            Number(props.maxQuantity || 0),
        );
    }

    /**
     * 현재 사유 행에서 선택한 재고에 해당하는 생산 기록을 조회합니다.
     *
     * 오늘 생산은 모든 당일 생산 기록을 대상으로 하고,
     * 이월 재고는 사용자가 직접 선택한 재고 기록만 대상으로 합니다.
     */
    const sources = props.stockSources.filter((source) => {
        // 오늘 생산은 같은 날짜에 생성된 모든 생산 기록을 포함합니다.
        if (row.stock_source === 'today') {
            return source.source === 'today';
        }

        /**
         * 이월 재고는 선택한 재고 기록의 고유 ID를 비교합니다.
         *
         * 다른 최초 생산일의 재고가 계산에 섞이지 않도록
         * 재고 구분과 재고 기록 ID를 모두 확인합니다.
         */
        return source.source === 'carryover'
            && Number(source.stock_lot_id) === Number(row.stock_lot_id);
    });

    /**
     * 선택한 재고 구분에서 사용할 수 있는 전체 수량을 계산합니다.
     *
     * 오늘 생산 기록이 5개와 2개로 나뉘어 있다면
     * 두 기록의 잔여 수량을 합산하여 7개로 계산합니다.
     */
    const available = sources.reduce(
        (sum, source) => (
            sum + Number(source.remaining_quantity || 0)
        ),
        0,
    );

    /**
     * 동일한 재고를 사용하는 다른 사유 행의 입력 수량을 합산합니다.
     *
     * 현재 행의 수량은 제외해야 사용자가 기존 수량을
     * 줄이거나 다시 늘릴 때 올바른 최대 수량을 계산할 수 있습니다.
     */
    const allocatedByOtherRows = props.modelValue.reduce(
        (sum, item, index) => {
            // 현재 수정 중인 사유 행은 중복 차감하지 않습니다.
            if (index === rowIndex) {
                return sum;
            }

            /**
             * 오늘 생산을 선택한 경우에는 다른 사유 행에서도
             * 오늘 생산으로 입력한 수량을 모두 합산합니다.
             *
             * 개별 생산 기록 ID가 서로 다르더라도
             * 동일한 오늘 생산 재고를 사용하는 것으로 계산합니다.
             */
            if (row.stock_source === 'today') {
                return item.stock_source === 'today'
                    ? sum + Number(item.quantity || 0)
                    : sum;
            }

            /**
             * 이월 재고는 동일한 재고 기록을 선택한
             * 다른 사유 행의 수량만 합산합니다.
             *
             * 서로 다른 생산 기록의 이월 재고는
             * 독립적으로 관리하여 수량이 섞이지 않도록 합니다.
             */
            return Number(item.stock_lot_id) === Number(row.stock_lot_id)
                ? sum + Number(item.quantity || 0)
                : sum;
        },
        0,
    );

    /**
     * 전체 사용 가능 수량에서 다른 사유 행의 수량을 차감합니다.
     *
     * 계산 결과가 음수이면 0으로 처리하여
     * 수량 증가 버튼이 허용 범위를 초과하지 않도록 합니다.
     */
    return Math.max(
        0,
        available - allocatedByOtherRows,
    );
}

/**
 * 현재 선택한 재고에서 입력 후 남는 사용 가능 수량을 표시합니다.
 *
 * 오늘 생산은 같은 날짜에 여러 번 생산한 기록을 모두 합산하며,
 * 이월 재고는 사용자가 직접 선택한 최초 생산일의 재고만 계산합니다.
 *
 * 같은 재고를 사용하는 모든 사유 행의 입력 수량을 차감하여
 * 수량 증가 또는 감소 시 화면의 잔여 수량이 즉시 변경되도록 합니다.
 *
 * @param {Object} row 사용 가능 수량을 표시할 사유 행
 * @returns {string} 재고 구분과 잔여 수량을 설명하는 안내 문구
 */
function stockAvailabilityText(row) {
    /**
     * 사용자가 선택한 재고 구분에 해당하는 생산 기록을 조회합니다.
     *
     * 오늘 생산은 모든 당일 생산 기록을 포함하고,
     * 이월 재고는 선택한 재고 기록 ID와 일치하는 항목만 포함합니다.
     */
    const sources = props.stockSources.filter((source) => {
        // 오늘 생산은 개별 생산 기록을 구분하지 않고 합산합니다.
        if (row.stock_source === 'today') {
            return source.source === 'today';
        }

        /**
         * 이월 재고는 선택한 재고 기록만 조회합니다.
         *
         * 최초 생산일이 다른 이월 재고가 계산에 포함되지 않도록
         * 재고 구분과 고유 ID를 함께 비교합니다.
         */
        return source.source === 'carryover'
            && Number(source.stock_lot_id) === Number(row.stock_lot_id);
    });

    /**
     * 선택한 재고에 해당하는 생산 기록이 없으면 안내 문구를 반환합니다.
     *
     * 이월 재고는 사용자가 직접 생산일을 선택해야 하므로
     * 선택 안내를 표시하고, 오늘 생산은 재고 확인 안내를 표시합니다.
     */
    if (sources.length === 0) {
        return row.stock_source === 'carryover'
            ? '사용할 이월 재고를 선택해 주세요.'
            : '사용 가능한 재고를 확인해 주세요.';
    }

    /**
     * 조회한 생산 기록들의 사용 가능 수량을 모두 합산합니다.
     *
     * 오늘 생산이 1차 5개, 2차 2개로 나뉘어 있다면
     * 총 7개를 하나의 오늘 생산 재고로 계산합니다.
     */
    const available = sources.reduce(
        (sum, source) => (
            sum + Number(source.remaining_quantity || 0)
        ),
        0,
    );

    /**
     * 현재 입력 중인 모든 사유 행에서 같은 재고를 사용하는 수량을 계산합니다.
     *
     * 오늘 생산은 개별 생산 기록 ID가 달라도 함께 합산하며,
     * 이월 재고는 선택한 재고 기록 ID가 같은 행만 합산합니다.
     */
    const allocated = props.modelValue.reduce(
        (sum, item) => {
            /**
             * 오늘 생산을 선택한 경우에는 오늘 생산으로 입력한
             * 모든 사유 행의 수량을 합산합니다.
             *
             * 여러 사유 행에서 동일한 재고를 중복 사용하지 않도록
             * 전체 입력 수량을 함께 차감합니다.
             */
            if (row.stock_source === 'today') {
                return item.stock_source === 'today'
                    ? sum + Number(item.quantity || 0)
                    : sum;
            }

            /**
             * 이월 재고는 현재 선택한 생산 기록과
             * 동일한 재고 기록을 사용하는 수량만 합산합니다.
             */
            return item.stock_source === 'carryover'
                && Number(item.stock_lot_id) === Number(row.stock_lot_id)
                ? sum + Number(item.quantity || 0)
                : sum;
        },
        0,
    );

    /**
     * 전체 사용 가능 수량에서 입력 중인 수량을 차감합니다.
     *
     * 계산 결과가 음수일 경우 0으로 제한하여
     * 화면에 음수 재고가 표시되지 않도록 합니다.
     */
    const remaining = Math.max(
        0,
        available - allocated,
    );

    /**
     * 오늘 생산은 여러 생산 기록을 합산한 잔여 수량을 표시합니다.
     *
     * 사용자가 개별 생산 차수를 선택하지 않아도
     * 오늘 생산 전체에서 남은 수량을 확인할 수 있습니다.
     */
    if (row.stock_source === 'today') {
        return `오늘 생산 · ${remaining}개 사용 가능`;
    }

    /**
     * 이월 재고는 기존 최초 생산일 표시 방식을 유지합니다.
     *
     * 사용자가 선택한 생산일과 해당 재고의 잔여 수량을
     * 함께 표시하여 어떤 이월 재고를 처리하는지 확인할 수 있습니다.
     */
    return `${formatOriginDate(sources[0].origin_production_date)} 생산 · ${remaining}개 사용 가능`;
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
