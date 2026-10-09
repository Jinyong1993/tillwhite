<template>
  <!-- 생산 기록 등록 및 수정 전 입력 내용을 확인합니다. -->
  <v-dialog
    :model-value="modelValue"
    max-width="480"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card rounded="lg" class="app-dialog-card production-save-confirm">
      <v-card-title class="app-dialog-header">
        {{ editing ? '생산 기록 수정 확인' : '생산 기록 등록 확인' }}
      </v-card-title>

      <v-card-text class="app-dialog-body">
        <v-alert
          v-if="abnormal"
          type="warning"
          variant="tonal"
          density="compact"
          icon="mdi-alert-outline"
          class="app-supporting-alert mb-3"
        >
          <div v-if="warningTitle" class="font-weight-medium">
            {{ warningTitle }}
          </div>
          <div v-if="warningMessage" class="mt-1">
            {{ warningMessage }}
          </div>
        </v-alert>

        <!-- 제품명과 등록 수량을 간결하게 표시합니다. -->
        <div class="production-save-confirm__summary">
          <div class="production-save-confirm__product-info">
            <span class="production-save-confirm__label">
              {{ editing ? '수정할 제품' : '등록할 제품' }}
            </span>

            <strong class="production-save-confirm__product">
              {{ productName || '제품' }}
            </strong>
          </div>

          <div class="production-save-confirm__quantity-info">
            <span class="production-save-confirm__label">
              {{ editing ? '변경 수량' : '등록 수량' }}
            </span>

            <strong class="production-save-confirm__quantity">
              {{ quantity }}개
            </strong>
          </div>
        </div>

        <!-- 현재 생산량과 저장 후 예상 총생산량을 구분해 표시합니다. -->
        <div class="production-save-confirm__totals">
          <div class="production-save-confirm__row">
            <span>현재 총생산량</span>
            <strong>{{ formatQuantity(currentTotal) }}</strong>
          </div>

          <div class="production-save-confirm__row">
            <span>{{ editing ? '수정 후 예상 총생산량' : '등록 후 예상 총생산량' }}</span>
            <strong class="production-save-confirm__expected">
              {{ formatQuantity(expectedTotal) }}
            </strong>
          </div>
        </div>

        <!-- 선택한 작업자 이름을 표시합니다. -->
        <div class="production-save-confirm__workers">
          <div class="production-save-confirm__workers-label">
            선택한 작업자
          </div>

          <div class="production-save-confirm__workers-list">
            <v-chip
              v-for="worker in selectedWorkers"
              :key="worker.id"
              size="small"
              variant="tonal"
              class="production-save-confirm__worker-chip"
            >
              {{ worker.name }}
            </v-chip>
          </div>
        </div>

        

        <!-- 최근 생산량과 입력 수량의 차이를 구분해 표시합니다. -->
        <div class="production-save-confirm__comparison">
          <div class="production-save-confirm__group">
            <div class="production-save-confirm__row">
              <span>최근 7일 평균</span>
              <strong>{{ formatQuantity(averageQuantity) }}</strong>
            </div>

            <div class="production-save-confirm__row">
              <span>전날 생산량</span>
              <strong>{{ formatQuantity(previousQuantity) }}</strong>
            </div>
          </div>

          <v-divider class="my-2" />

          <div class="production-save-confirm__group">
            <div class="production-save-confirm__row">
              <span>7일 평균 대비</span>
              <strong>{{ formatComparison(averageQuantity) }}</strong>
            </div>

            <div class="production-save-confirm__row">
              <span>전날 대비</span>
              <strong>{{ formatComparison(previousQuantity) }}</strong>
            </div>
          </div>
        </div>

        <!-- 생산 기록 등록 및 수정 전 최종 확인 내용을 안내합니다. -->
        <div class="production-save-confirm__guide">
          <template v-if="editing">
            생산 수량을 {{ originalQuantity }}개에서
            {{ quantity }}개로 수정하시겠습니까?
            작업자 변경도 함께 저장됩니다.
          </template>

          <template v-else>
            위 내용으로 생산 기록을 등록하시겠습니까?
          </template>
        </div>
      </v-card-text>

      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="emit('update:modelValue', false)"
        >
          아니오
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          :loading="loading"
          :disabled="loading"
          @click="emit('confirm')"
        >
          예
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
// 부모 컴포넌트에서 계산한 생산 정보를 전달받습니다.
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  editing: {
    type: Boolean,
    default: false,
  },

  productName: {
    type: String,
    default: '',
  },

  quantity: {
    type: Number,
    default: 0,
  },

  originalQuantity: {
    type: Number,
    default: 0,
  },

  // 선택 날짜에 이미 등록된 해당 제품의 총생산량입니다.
  currentTotal: {
    type: Number,
    default: null,
  },

  // 신규 등록 또는 수정 후 예상되는 하루 총생산량입니다.
  expectedTotal: {
    type: Number,
    default: null,
  },

  // 생산 기록에 선택한 작업자 목록입니다.
  selectedWorkers: {
    type: Array,
    default: () => [],
  },

  averageQuantity: {
    type: Number,
    default: null,
  },

  previousQuantity: {
    type: Number,
    default: null,
  },

  // 생산 이력 API의 조회 상태를 구분합니다.
  historyStatus: {
    type: String,
    default: 'idle',
  },

  abnormal: {
    type: Boolean,
    default: false,
  },

  warningTitle: {
    type: String,
    default: '',
  },

  warningMessage: {
    type: String,
    default: '',
  },

  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

/**
 * 생산 이력 조회 상태에 따라 수량을 표시합니다.
 * 조회 실패, 미확인 기록 및 실제 0개를 구분합니다.
 */
function formatQuantity(value) {
  if (props.historyStatus === 'loading') {
    return '조회 중...';
  }

  if (props.historyStatus === 'error') {
    return '조회 실패';
  }

  if (props.historyStatus !== 'success') {
    return '미확인';
  }

  if (value === null || !Number.isFinite(value)) {
    return '미확인';
  }

  return `${Number(value.toFixed(1))}개`;
}

/**
 * 저장 후 예상 총생산량과 기존 생산량의 증감 수량 및 증감률을 표시합니다.
 * 조회 실패, 미확인 기록 및 실제 생산량 0개를 구분합니다.
 */
function formatComparison(baseline) {
  // 생산 이력 조회 상태를 먼저 확인합니다.
  if (props.historyStatus === 'loading') {
    return '조회 중...';
  }

  if (props.historyStatus === 'error') {
    return '조회 실패';
  }

  if (props.historyStatus !== 'success') {
    return '미확인';
  }

  // 비교 기준과 저장 후 예상 총생산량이 유효한지 확인합니다.
  const total = props.expectedTotal;

  if (
    baseline === null ||
    !Number.isFinite(baseline) ||
    total === null ||
    !Number.isFinite(total)
  ) {
    return '미확인';
  }

  const difference = total - baseline;
  const differenceText =
    `${difference > 0 ? '+' : ''}${Number(difference.toFixed(1))}개`;

  // 기준 생산량이 0개이면 증감률을 계산하지 않습니다.
  if (baseline === 0) {
    return `${differenceText} (증감률 계산 불가)`;
  }

  const percentage = (difference / baseline) * 100;

  return [
    differenceText,
    `(${percentage > 0 ? '+' : ''}${percentage.toFixed(0)}%)`,
  ].join(' ');
}
</script>

<style scoped>
/* 제품 정보와 등록 수량을 기존 다이얼로그에 맞춰 간결하게 정렬합니다. */
.production-save-confirm__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 14px;
  margin-bottom: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.production-save-confirm__product-info,
.production-save-confirm__quantity-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.production-save-confirm__quantity-info {
  flex-shrink: 0;
  align-items: flex-end;
}

.production-save-confirm__label {
  font-size: 0.7rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.production-save-confirm__product {
  font-size: 0.875rem;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.production-save-confirm__quantity {
  font-size: 1rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

/* 생산량 비교 항목을 동일한 간격으로 정렬합니다. */
.production-save-confirm__comparison {
  padding: 2px 0;
}

.production-save-confirm__group {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.production-save-confirm__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  font-size: 0.8rem;
  line-height: 1.5;
}

.production-save-confirm__row span {
  flex-shrink: 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.production-save-confirm__row strong {
  min-width: 0;
  text-align: right;
  font-size: 0.8rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

/* 등록 및 수정 확인 안내를 구분선 아래에 간결하게 표시합니다. */
.production-save-confirm__guide {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  font-size: 0.8rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), 0.72);
  overflow-wrap: anywhere;
}

/* 현재 총생산량과 저장 후 예상 총생산량을 강조합니다. */
.production-save-confirm__totals {
  display: flex;
  flex-direction: column;
  gap: 9px;
  padding: 12px 14px;
  margin-bottom: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
}

.production-save-confirm__expected {
  color: rgb(var(--v-theme-primary));
}

/* 선택한 작업자 이름을 작은 칩 형태로 표시합니다. */
.production-save-confirm__workers {
  margin-bottom: 16px;
}

.production-save-confirm__workers-label {
  margin-bottom: 8px;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.production-save-confirm__workers-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.production-save-confirm__worker-chip {
  font-size: 0.75rem;
  font-weight: 500;
}

/* 작은 화면에서도 생산 정보와 비교 수량을 읽기 쉽게 표시합니다. */
@media (max-width: 600px) {
  .production-save-confirm__summary {
    gap: 10px;
    padding: 11px 12px;
    margin-bottom: 12px;
  }

  .production-save-confirm__product-info {
    flex: 1;
    min-width: 0;
  }

  .production-save-confirm__product {
    font-size: 0.8rem;
    line-height: 1.4;
    overflow-wrap: anywhere;
  }

  .production-save-confirm__quantity {
    font-size: 0.9rem;
  }

  .production-save-confirm__group {
    gap: 8px;
  }

  .production-save-confirm__row {
    align-items: flex-start;
    gap: 8px;
    font-size: 0.75rem;
  }

  .production-save-confirm__row span {
    flex-shrink: 0;
  }

  .production-save-confirm__row strong {
    font-size: 0.75rem;
    line-height: 1.5;
  }

  .production-save-confirm__guide {
    margin-top: 14px;
    padding-top: 10px;
    font-size: 0.75rem;
  }
}
</style>