<template>
  <div class="production-reason-list">
    <article
        v-for="(row, index) in modelValue"
        :key="index"
        class="production-reason-card"
    >
      <div class="production-reason-card__fields">
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
        <v-number-input
            :model-value="row.quantity"
            label="수량"
            variant="outlined"
            density="compact"
            hide-details="auto"
            :min="1"
            :max="maxQuantity || undefined"
            @update:model-value="update(index, 'quantity', $event)"
        />
        <v-btn
            icon="mdi-close"
            size="small"
            variant="text"
            aria-label="사유 삭제"
            class="production-reason-card__remove"
            @click="remove(index)"
        />
      </div>

      <v-text-field
          v-if="row.reason_code === 'other'"
          :model-value="row.reason_text"
          label="사유 직접입력"
          variant="outlined"
          density="compact"
          hide-details="auto"
          class="production-reason-card__custom"
          @update:model-value="update(index, 'reason_text', $event)"
      />
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
});

const emit = defineEmits(['update:modelValue']);

// 서버의 기존 other 코드는 유지하면서 화면 명칭만 현장 사용자가 이해하기 쉽게 통일합니다.
const displayReasonOptions = computed(() => props.reasonOptions.map((option) => ({
    ...option,
    title: option.value === 'other' ? '직접입력' : option.title,
})));

// 한 행만 복사해 갱신하여 다른 사유 입력값을 건드리지 않습니다.
function update(index, key, value) {
    const next = props.modelValue.map((row, rowIndex) => (
        rowIndex === index ? { ...row, [key]: value } : row
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

// 사용자가 선택한 사유 카드만 제거합니다.
function remove(index) {
    emit('update:modelValue', props.modelValue.filter((_, rowIndex) => rowIndex !== index));
}
</script>
