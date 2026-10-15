<template>
  <v-dialog
    :model-value="modelValue"
    max-width="760"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card rounded="lg" class="app-dialog-card close-dialog">
      <!-- 고정 헤더 -->
      <v-card-title class="app-dialog-header close-dialog__header">
        <div class="close-dialog__heading">
          <div class="close-dialog__heading-info">
            <span>생산·폐기 관리</span>
            <strong>마감 최종확인</strong>
          </div>

          <div class="close-dialog__heading-date">
            <v-icon icon="mdi-calendar-blank-outline" size="16" />
            <span>{{ workDate }}</span>
          </div>
        </div>
      </v-card-title>

      <!-- 고정 영역: 전체 요약 및 제품별 확인 제목 -->
      <div class="close-dialog__fixed">
        <p class="close-dialog__help">
          생산·이월·로스·폐기 기록을 확인한 후 마감해 주세요.
        </p>

        <!-- 전체 생산 제품 6개 지표 -->
        <div class="close-dialog__summary">
          <div
            v-for="metric in totalMetrics"
            :key="metric.key"
            class="close-dialog__summary-item"
          >
            <span>{{ metric.title }}</span>
            <strong>{{ metric.value }}</strong>
          </div>
        </div>

        <!-- 마감 불가 사유 -->
        <v-alert
          v-if="preview && !preview.can_close"
          type="warning"
          variant="tonal"
          density="compact"
          class="close-dialog__notice"
        >
          <template v-if="preview.stock_issues?.length">
            <strong>재고 흐름을 확인해 주세요.</strong>

            <div
              v-for="(issue, index) in preview.stock_issues"
              :key="index"
              class="close-dialog__issue"
            >
              {{ issue }}
            </div>
          </template>

          <template v-else>
            미확인 제품 {{ incompleteCount }}개가 있습니다.
            아래에서 확인해 주세요.
          </template>
        </v-alert>

        <!-- 제품별 확인 제목도 고정 -->
        <div class="close-dialog__section-heading">
          <strong>제품별 확인</strong>
          <span>
            {{ completeCount }} / {{ rows.length }}개 완료
          </span>
        </div>

        <div
          class="close-dialog__progress"
          role="progressbar"
          :aria-valuenow="completeCount"
          :aria-valuemin="0"
          :aria-valuemax="rows.length"
          aria-label="제품별 확인 진행률"
        >
          <span :style="{ width: `${progressPercent}%` }" />
        </div>
      </div>

      <!-- 제품 목록만 스크롤 -->
      <div
        ref="listRef"
        class="close-dialog__scroll"
        tabindex="0"
        aria-label="제품별 마감 확인 목록"
      >
        <div class="close-dialog__records">
          <article
            v-for="row in pagedRows"
            :key="row.id"
            class="close-dialog__record"
          >
            <div class="close-dialog__record-heading">
              <div class="close-dialog__record-info">
                <strong>{{ row.name }}</strong>

                <span v-if="!row.complete">
                  {{ missingText(row) }}
                </span>
              </div>

              <span
                class="close-dialog__status"
                :class="{
                  'close-dialog__status--pending': !row.complete
                }"
              >
                {{ row.complete ? '확인 완료' : '확인 필요' }}
              </span>
            </div>

            <!-- 개별 제품 6개 지표 -->
            <div class="close-dialog__quantities">
              <!-- 생산 -->
              <button
                type="button"
                @click="emit('production', row)"
              >
                <span>생산</span>
                <strong>
                  {{
                    row.production_confirmed
                      ? Number(row.production || 0)
                      : '-'
                  }}
                </strong>
              </button>

              <!-- 이월 재고 -->
              <button
                type="button"
                @click="emit('flow', row, 'carryover')"
              >
                <span>이월 재고</span>
                <strong>
                  {{
                    row.disposition_confirmed ||
                    Number(row.carryover_in || 0) > 0
                      ? Number(row.carryover_in || 0)
                      : '-'
                  }}
                </strong>
              </button>

              <!-- 이월 예정 -->
              <button
                type="button"
                @click="emit('flow', row, 'carryover')"
              >
                <span>이월 예정</span>
                <strong>
                  {{
                    row.disposition_confirmed ||
                    Number(row.carryover_out || 0) > 0
                      ? Number(row.carryover_out || 0)
                      : '-'
                  }}
                </strong>
              </button>

              <!-- 로스 -->
              <button
                type="button"
                @click="emit('flow', row, 'loss')"
              >
                <span>로스</span>
                <strong>
                  {{
                    row.loss_confirmed
                      ? Number(row.operational_loss || 0)
                      : '-'
                  }}
                </strong>
              </button>

              <!-- 폐기 -->
              <button
                type="button"
                @click="emit('flow', row, 'waste')"
              >
                <span>폐기</span>
                <strong>
                  {{
                    row.waste_confirmed ||
                    Number(row.attributed_waste || 0) > 0
                      ? Number(row.attributed_waste || 0)
                      : '-'
                  }}
                </strong>
              </button>

              <!-- 폐기율: 서버 계산값 -->
              <button
                type="button"
                @click="emit('product', row)"
              >
                <span>폐기율</span>
                <strong>
                  {{
                    row.waste_rate == null
                      ? '-'
                      : `${row.waste_rate}%`
                  }}
                </strong>
              </button>
            </div>

            <button
              type="button"
              class="close-dialog__product-link"
              @click="emit('product', row)"
            >
              제품 정보
              <v-icon icon="mdi-chevron-right" size="15" />
            </button>
          </article>

          <div
            v-if="!rows.length"
            class="close-dialog__empty"
          >
            확인할 제품이 없습니다.
          </div>
        </div>

        <!-- 페이지당 10개 유지 -->
        <v-pagination
          v-if="pageCount > 1"
          :model-value="page"
          :length="pageCount"
          :total-visible="5"
          density="compact"
          class="close-dialog__pagination"
          @update:model-value="changePage"
        />

        <div
          v-if="preview?.can_close"
          class="close-dialog__ready"
        >
          <v-icon icon="mdi-check-circle-outline" size="17" />
          <span>모든 제품 확인이 완료되었습니다.</span>
        </div>
      </div>

      <!-- 하단 버튼 고정 -->
      <v-card-actions class="app-dialog-footer px-4 pb-4 close-dialog__footer">
        <v-btn
          variant="text"
          @click="emit('update:modelValue', false)"
        >
          취소
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          :disabled="!preview?.can_close"
          @click="emit('confirm')"
        >
          마감
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  preview: {
    type: Object,
    default: null,
  },
  metrics: {
    type: Array,
    default: () => [],
  },
  rows: {
    type: Array,
    default: () => [],
  },
  page: {
    type: Number,
    default: 1,
  },
  workDate: {
    type: String,
    default: '',
  },
});

const emit = defineEmits([
  'update:modelValue',
  'update:page',
  'production',
  'flow',
  'product',
  'confirm',
]);

const listRef = ref(null);
const pageSize = 10;

/*
 * 전체 수량은 서버 마감 미리보기의 합계를 사용합니다.
 * 기존 부모의 metrics 중 생산·로스·폐기·폐기율을 재사용하고,
 * 이월 재고와 이월 예정은 각각 분리합니다.
 */
const totalMetrics = computed(() => {
  const totals = props.preview?.daily?.totals || {};
  const metricMap = Object.fromEntries(
    props.metrics.map((item) => [item.key, item.value])
  );

  const numberValue = (value) => {
    const result = Number(value ?? 0);
    return Number.isFinite(result) ? result : 0;
  };

  return [
    {
      key: 'production',
      title: '생산',
      value: numberValue(
        totals.production ?? metricMap.production
      ),
    },
    {
      key: 'carryover_in',
      title: '이월 재고',
      value: numberValue(
        totals.carryover ?? totals.carryover_in
      ),
    },
    {
      key: 'carryover_out',
      title: '이월 예정',
      value: numberValue(totals.carryover_out),
    },
    {
      key: 'loss',
      title: '로스',
      value: numberValue(
        totals.loss ?? metricMap.loss
      ),
    },
    {
      key: 'waste',
      title: '폐기',
      value: numberValue(
        totals.attributed_waste ?? metricMap.waste
      ),
    },
    {
      key: 'waste_rate',
      title: '폐기율',
      value: totals.waste_rate == null
        ? metricMap.waste_rate ?? '-'
        : `${totals.waste_rate}%`,
    },
  ];
});

const completeCount = computed(() =>
  props.rows.filter((row) => row.complete).length
);

const incompleteCount = computed(() =>
  props.rows.length - completeCount.value
);

const progressPercent = computed(() =>
  props.rows.length
    ? Math.round(
        (completeCount.value / props.rows.length) * 100
      )
    : 0
);

const pageCount = computed(() =>
  Math.max(1, Math.ceil(props.rows.length / pageSize))
);

const pagedRows = computed(() => {
  const start = (props.page - 1) * pageSize;
  return props.rows.slice(start, start + pageSize);
});

async function changePage(value) {
  emit('update:page', value);

  await nextTick();

  listRef.value?.scrollTo({
    top: 0,
    behavior: 'smooth',
  });
}

function missingText(row) {
  const items = [];

  if (!row.production_confirmed) {
    items.push('생산');
  }

  if (!row.disposition_confirmed) {
    items.push('이월');
  }

  if (!row.loss_confirmed) {
    items.push('로스');
  }

  if (!row.waste_confirmed) {
    items.push('폐기');
  }

  return items.length
    ? `${items.join(' · ')} 미확인`
    : '확인이 필요합니다.';
}
</script>

<style scoped>
/*
 * 통합 재고관리 디자인 유지
 * 헤더 / 전체 요약 / 제품별 확인 제목 / 하단 버튼 고정
 * 제품별 기록만 스크롤
 */
.close-dialog {
  display: flex;
  flex-direction: column;
  height: min(90dvh, 900px);
  max-height: 90dvh;
  overflow: hidden;
  font-size: 12px;
}

.close-dialog :deep(.v-btn) {
  font-size: 12px;
}

/* 상단 헤더 */
.close-dialog__header {
  flex: 0 0 auto;
}

.close-dialog__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  width: 100%;
}

.close-dialog__heading-info {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 0;
}

.close-dialog__heading-info > span {
  font-size: 11px;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.close-dialog__heading-info > strong {
  font-size: 20px;
  font-weight: 600;
  line-height: 1.3;
}

.close-dialog__heading-date {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  flex-shrink: 0;
  padding: 8px 10px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.05);
  color: rgba(var(--v-theme-on-surface), 0.8);
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
}

/* 고정 요약 영역 */
.close-dialog__fixed {
  flex: 0 0 auto;
  padding: 16px 24px 14px;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.close-dialog__help {
  margin: 0 0 14px;
  font-size: 12px;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

/* 전체 6개 항목: 3열 2행 */
.close-dialog__summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 18px;
}

.close-dialog__summary-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 0;
  padding: 13px 8px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  text-align: center;
}

.close-dialog__summary-item > span {
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.close-dialog__summary-item > strong {
  font-size: 18px;
  font-weight: 600;
  line-height: 1.3;
  font-variant-numeric: tabular-nums;
}

/* 검증 메시지 */
.close-dialog__notice {
  margin-bottom: 16px;
  font-size: 12px;
  line-height: 1.6;
  max-height: 110px;
  overflow-y: auto;
}

.close-dialog__issue {
  margin-top: 6px;
}

/* 고정 섹션 제목 */
.close-dialog__section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.close-dialog__section-heading > strong {
  font-size: 15px;
  font-weight: 600;
}

.close-dialog__section-heading > span {
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  white-space: nowrap;
}

/* 확인 진행률 */
.close-dialog__progress {
  height: 4px;
  overflow: hidden;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.08);
}

.close-dialog__progress > span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: rgb(var(--v-theme-primary));
  transition: width 0.2s ease;
}

/* 스크롤 영역 */
.close-dialog__scroll {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 16px 24px 20px;
  scrollbar-width: thin;
}

.close-dialog__records {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* 제품별 카드 */
.close-dialog__record {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.07);
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.025);
  font-size: 12px;
  line-height: 1.5;
}

.close-dialog__record-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.close-dialog__record-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.close-dialog__record-info > strong {
  font-size: 13px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.close-dialog__record-info > span {
  font-size: 11px;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.close-dialog__status {
  flex-shrink: 0;
  padding: 3px 8px;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.05);
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 11px;
  font-weight: 500;
}

.close-dialog__status--pending {
  background: rgba(var(--v-theme-on-surface), 0.09);
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
}

/* 제품별 6개 항목: 통합 다이얼로그 스타일 */
.close-dialog__quantities {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.close-dialog__quantities > button {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 0;
  min-height: 64px;
  padding: 10px 5px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.07);
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  color: inherit;
  font: inherit;
  cursor: pointer;
  transition: background 0.2s ease;
}

.close-dialog__quantities > button:hover {
  background: rgba(var(--v-theme-on-surface), 0.065);
}

.close-dialog__quantities > button:focus-visible,
.close-dialog__product-link:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.close-dialog__quantities > button > span {
  font-size: 11px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.close-dialog__quantities > button > strong {
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
  font-variant-numeric: tabular-nums;
}

/* 제품 상세 */
.close-dialog__product-link {
  display: inline-flex;
  align-items: center;
  align-self: flex-end;
  gap: 3px;
  padding: 3px 0;
  border: 0;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}

.close-dialog__product-link:hover {
  color: rgb(var(--v-theme-on-surface));
}

/* 페이지 이동 */
.close-dialog__pagination {
  margin-top: 14px;
}

.close-dialog__empty {
  padding: 28px 12px;
  text-align: center;
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

/* 마감 준비 완료 */
.close-dialog__ready {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-top: 16px;
  padding: 12px 14px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  color: rgba(var(--v-theme-on-surface), 0.8);
  font-size: 12px;
}

/* 하단 버튼 고정 */
.close-dialog__footer {
  flex: 0 0 auto;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

/* 모바일 */
@media (max-width: 600px) {
  .close-dialog {
    height: 92dvh;
    max-height: 92dvh;
  }

  .close-dialog__heading {
    gap: 10px;
  }

  .close-dialog__heading-info > strong {
    font-size: 18px;
  }

  .close-dialog__heading-date {
    gap: 5px;
    padding: 7px 8px;
    font-size: 11px;
  }

  .close-dialog__fixed {
    padding: 12px 16px;
  }

  .close-dialog__help {
    margin-bottom: 10px;
  }

  .close-dialog__summary {
    gap: 6px;
    margin-bottom: 13px;
  }

  .close-dialog__summary-item {
    padding: 10px 5px;
  }

  .close-dialog__summary-item > span {
    font-size: 11px;
  }

  .close-dialog__summary-item > strong {
    font-size: 16px;
  }

  .close-dialog__scroll {
    padding: 12px 16px 16px;
  }

  .close-dialog__record {
    padding: 12px;
  }

  .close-dialog__quantities {
    gap: 6px;
  }

  .close-dialog__quantities > button {
    min-height: 60px;
    padding: 8px 3px;
  }

  .close-dialog__quantities > button > strong {
    font-size: 13px;
  }
}
</style>