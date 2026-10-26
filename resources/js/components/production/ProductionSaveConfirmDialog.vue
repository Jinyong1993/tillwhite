<template>
  <AppDialogLayout
    :model-value="modelValue"
    :title="editing ? '생산 기록 수정 확인' : '생산 기록 등록 확인'"
    :max-width="480"
    :persistent="true"
    :busy="loading"
    card-class="production-save-confirm"
    body-class="production-save-confirm__body"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- 이상 수량 경고 -->
    <v-alert
      v-if="abnormal"
      type="warning"
      variant="tonal"
      density="compact"
      class="app-supporting-alert production-save-confirm__alert"
    >
      <div
        v-if="warningTitle"
        class="font-weight-medium"
      >
        {{ warningTitle }}
      </div>

      <div
        v-if="warningMessage"
        class="mt-1"
      >
        {{ warningMessage }}
      </div>
    </v-alert>

    <!-- 제품명 및 등록·수정 수량 -->
    <div
      class="production-save-confirm__summary d-flex align-center justify-space-between flex-wrap ga-3"
    >
      <div
        class="production-save-confirm__product-info d-flex flex-column ga-1 flex-grow-1"
      >
        <span class="production-save-confirm__label">
          {{ editing ? '수정할 제품' : '등록할 제품' }}
        </span>

        <strong class="production-save-confirm__product">
          {{ productName || '제품' }}
        </strong>
      </div>

      <div
        class="production-save-confirm__quantity-info d-flex flex-column align-end ga-1"
      >
        <span class="production-save-confirm__label">
          {{ editing ? '변경 수량' : '등록 수량' }}
        </span>

        <strong class="production-save-confirm__quantity">
          {{ quantity }}개
        </strong>
      </div>
    </div>

    <!-- 현재 및 예상 총생산량 -->
    <div
      class="production-save-confirm__section d-flex flex-column ga-2"
    >
      <div
        class="production-save-confirm__row d-flex align-start justify-space-between ga-3"
      >
        <span>현재 총생산량</span>

        <strong>
          {{ formatQuantity(currentTotal) }}
        </strong>
      </div>

      <div
        class="production-save-confirm__row d-flex align-start justify-space-between ga-3"
      >
        <span>
          {{ editing ? '수정 후 총생산량' : '등록 후 총생산량' }}
        </span>

        <strong class="production-save-confirm__expected">
          {{ formatQuantity(expectedTotal) }}
        </strong>
      </div>
    </div>

    <!-- 작업자 -->
    <div class="production-save-confirm__section">
      <div class="production-save-confirm__section-title">
        선택한 작업자
      </div>

      <div
        class="production-save-confirm__workers-list d-flex flex-wrap ga-2"
      >
        <v-chip
          v-for="worker in selectedWorkers"
          :key="worker.id"
          size="small"
          variant="tonal"
          class="production-save-confirm__worker-chip"
        >
          {{ worker.name }}
        </v-chip>

        <span
          v-if="!selectedWorkers.length"
          class="production-save-confirm__empty"
        >
          선택한 작업자가 없습니다.
        </span>
      </div>
    </div>

    <!-- 레시피 변경 내용 -->
    <div
      v-if="!editing && recipeDeviated"
      class="production-save-confirm__section d-flex flex-column ga-2"
    >
      <strong class="production-save-confirm__section-title">
        레시피 변경
      </strong>

      <div class="production-save-confirm__detail-text">
        {{ recipeDeviationNote || '변경 내용 없음' }}
      </div>
    </div>

    <!-- 추천 생산량 참고 -->
    <div
      v-if="!editing && recommendationReferenced"
      class="production-save-confirm__section d-flex flex-column ga-2"
    >
      <strong class="production-save-confirm__section-title">
        추천 생산량 참고
      </strong>

      <div class="production-save-confirm__detail-text">
        {{ recommendationDeviationReason || '추천 범위 변경 사유 없음' }}
      </div>
    </div>

    <!-- 메모 -->
    <div
      v-if="typeof note === 'string' && note.trim()"
      class="production-save-confirm__section d-flex flex-column ga-2"
    >
      <strong class="production-save-confirm__section-title">
        메모
      </strong>

      <div class="production-save-confirm__detail-text">
        {{ note }}
      </div>
    </div>

    <!-- 최근 생산량 비교 -->
    <div
      class="production-save-confirm__section d-flex flex-column ga-3"
    >
      <div class="production-save-confirm__section-title">
        생산량 비교
      </div>

      <div
        class="production-save-confirm__group d-flex flex-column ga-2"
      >
        <div
          class="production-save-confirm__row d-flex align-start justify-space-between ga-3"
        >
          <span>최근 7일 평균</span>

          <strong>
            {{ formatQuantity(averageQuantity) }}
          </strong>
        </div>

        <div
          class="production-save-confirm__row d-flex align-start justify-space-between ga-3"
        >
          <span>전날 생산량</span>

          <strong>
            {{ formatQuantity(previousQuantity) }}
          </strong>
        </div>
      </div>

      <v-divider />

      <div
        class="production-save-confirm__group d-flex flex-column ga-2"
      >
        <div
          class="production-save-confirm__row d-flex align-start justify-space-between ga-3"
        >
          <span>7일 평균 대비</span>

          <strong>
            {{ formatComparison(averageQuantity) }}
          </strong>
        </div>

        <div
          class="production-save-confirm__row d-flex align-start justify-space-between ga-3"
        >
          <span>전날 대비</span>

          <strong>
            {{ formatComparison(previousQuantity) }}
          </strong>
        </div>
      </div>
    </div>

    <!-- 최종 확인 안내 -->
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

    <!-- 공통 다이얼로그 하단 버튼 -->
    <template #footer>
      <AppDialogFooter
        :loading="loading"
        :confirm-action="editing ? 'edit' : 'save'"
        cancel-text="아니오"
        confirm-text="예"
        @cancel="emit('update:modelValue', false)"
        @confirm="emit('confirm')"
      >
        <template #cancel>
          <v-btn
            block
            variant="outlined"
            min-height="44"
            :disabled="loading"
            @click="emit('update:modelValue', false)"
          >
            아니오
          </v-btn>
        </template>

        <template #confirm>
          <v-btn
            block
            variant="flat"
            :color="editing ? 'primary' : 'success'"
            min-height="44"
            :loading="loading"
            :disabled="loading"
            @click="emit('confirm')"
          >
            예
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>
</template>

<script setup>
import AppDialogLayout from '../common/AppDialogLayout.vue';
import AppDialogFooter from '../common/AppDialogFooter.vue';

// 기존 부모 컴포넌트 속성 유지
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

  // 선택 날짜의 해당 제품 총생산량
  currentTotal: {
    type: Number,
    default: null,
  },

  // 등록·수정 후 예상 총생산량
  expectedTotal: {
    type: Number,
    default: null,
  },

  selectedWorkers: {
    type: Array,
    default: () => [],
  },

  recipeDeviated: {
    type: Boolean,
    default: false,
  },

  recipeDeviationNote: {
    type: String,
    default: '',
  },

  recommendationReferenced: {
    type: Boolean,
    default: false,
  },

  recommendationDeviationReason: {
    type: String,
    default: '',
  },

  note: {
    type: String,
    default: '',
  },

  averageQuantity: {
    type: Number,
    default: null,
  },

  previousQuantity: {
    type: Number,
    default: null,
  },

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

// 기존 이벤트 유지
const emit = defineEmits([
  'update:modelValue',
  'confirm',
]);

/**
 * 생산 이력 조회 상태에 따른 수량 표시.
 * 조회 실패, 미확인 및 실제 0개를 구분합니다.
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
 * 예상 총생산량과 기존 생산량의 증감 수량·증감률 표시.
 */
function formatComparison(baseline) {
  if (props.historyStatus === 'loading') {
    return '조회 중...';
  }

  if (props.historyStatus === 'error') {
    return '조회 실패';
  }

  if (props.historyStatus !== 'success') {
    return '미확인';
  }

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
/*
 * 생산 기록 등록·수정 확인 다이얼로그
 *
 * 레이아웃 및 푸터는 공통 컴포넌트 사용.
 * 이 파일에서는 내부 카드 디자인과 간격만 조정.
 */

/* 다이얼로그 */
.production-save-confirm {
  min-width: 0;
}

/*
 * 본문 카드 사이 간격:
 * PC 8px / 모바일 6px
 *
 * 공통 AppDialogLayout의 gap보다 우선 적용.
 */
:deep(.production-save-confirm__body) {
  min-width: 0;
  gap: 8px !important;
  row-gap: 8px !important;
}

/* 이상 수량 경고 */
.production-save-confirm__alert {
  margin: 0 !important;
}

/* 공통 정보 카드 */
.production-save-confirm__summary,
.production-save-confirm__section {
  min-width: 0;
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  font-size: 0.8rem;
  line-height: 1.6;
}

/* 제품 / 수량 요약 */
.production-save-confirm__summary {
  gap: 12px;
}

.production-save-confirm__product-info {
  flex: 1 1 auto;
  min-width: 0;
}

.production-save-confirm__quantity-info {
  flex: 0 0 auto;
  min-width: 0;
  margin-left: auto;
}

.production-save-confirm__label {
  font-size: 0.7rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.production-save-confirm__product {
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.45;
  overflow-wrap: anywhere;
}

.production-save-confirm__quantity {
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* 각 정보 섹션 제목 */
.production-save-confirm__section-title {
  display: block;
  margin-bottom: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.4;
}

/* flex 섹션에서는 gap으로 간격 관리 */
.production-save-confirm__section.d-flex
  > .production-save-confirm__section-title {
  margin-bottom: 0;
}

/* 카드 내부 간격 */
.production-save-confirm__section.d-flex {
  gap: 8px !important;
}

/* 정보 행 */
.production-save-confirm__row {
  min-width: 0;
  font-size: 0.8rem;
  line-height: 1.5;
}

.production-save-confirm__row > span {
  flex: 0 1 auto;
  min-width: 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
  overflow-wrap: anywhere;
}

.production-save-confirm__row > strong {
  flex: 0 1 auto;
  min-width: 0;
  font-size: 0.8rem;
  font-weight: 600;
  text-align: right;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

/* 등록·수정 후 예상 생산량 */
.production-save-confirm__expected {
  color: rgb(var(--v-theme-primary));
}

/* 작업자 목록 */
.production-save-confirm__workers-list {
  min-width: 0;
}

.production-save-confirm__worker-chip {
  max-width: 100%;
  font-size: 0.75rem;
  font-weight: 500;
}

.production-save-confirm__empty {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

/* 레시피 / 추천 생산량 / 메모 */
.production-save-confirm__detail-text {
  min-width: 0;
  font-size: 0.8rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), 0.72);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

/* 생산량 비교 */
.production-save-confirm__group {
  min-width: 0;
}

/* 최종 확인 안내 */
.production-save-confirm__guide {
  min-width: 0;
  margin: 0;
  padding: 8px 2px 0;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  font-size: 0.8rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), 0.72);
  overflow-wrap: anywhere;
}

/* 공통 푸터 버튼 동일 너비 */
.production-save-confirm :deep(.app-dialog-footer-item) {
  flex: 1 1 0;
  min-width: 0;
}

.production-save-confirm :deep(.app-dialog-footer-item > .v-btn) {
  width: 100%;
  min-width: 0;
  min-height: 44px;
}

/* 모바일 */
@media (max-width: 600px) {
  :deep(.production-save-confirm__body) {
    gap: 6px !important;
    row-gap: 6px !important;
  }

  .production-save-confirm__summary,
  .production-save-confirm__section {
    padding: 11px 12px;
  }

  .production-save-confirm__summary {
    gap: 10px;
  }

  .production-save-confirm__product {
    font-size: 0.8rem;
  }

  .production-save-confirm__quantity {
    font-size: 0.9rem;
  }

  .production-save-confirm__row,
  .production-save-confirm__row > strong,
  .production-save-confirm__detail-text {
    font-size: 0.75rem;
  }

  .production-save-confirm__guide {
    font-size: 0.75rem;
  }
}

/* 320px 등 좁은 화면 */
@media (max-width: 360px) {
  .production-save-confirm__summary,
  .production-save-confirm__section {
    padding: 10px;
  }

  .production-save-confirm__summary {
    gap: 8px;
  }

  .production-save-confirm__row {
    gap: 8px;
  }

  .production-save-confirm__quantity {
    font-size: 0.85rem;
  }
}
</style>