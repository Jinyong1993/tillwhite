<template>
  <section
    v-for="group in groupedRows"
    :key="group.name"
    class="category-block"
  >
    <!-- 카테고리 제목 및 기록 진행률 -->
    <button
      type="button"
      class="category-header d-flex align-center justify-space-between ga-2"
      :aria-expanded="!collapsed.has(group.name)"
      @click="emit('toggle-category', group.name)"
    >
      <div class="category-heading-copy d-flex flex-column">
        <strong>{{ group.name }}</strong>
        <span>{{ categoryProgressText(group.allRows) }}</span>
      </div>

      <div class="category-heading-side d-flex align-center ga-2">
        <span>
          기록 완료
          {{ categoryCompleteCount(group.allRows) }}
          /
          {{ group.allRows.filter((row) => row.is_active).length }}
        </span>

        <v-icon
          :icon="
            collapsed.has(group.name)
              ? 'mdi-chevron-down'
              : 'mdi-chevron-up'
          "
          size="small"
        />
      </div>
    </button>

    <div
      class="category-progress"
      aria-hidden="true"
    >
      <span
        :style="{
          width: `${categoryProgress(group.allRows)}%`
        }"
      />
    </div>

    <v-expand-transition>
      <div v-show="!collapsed.has(group.name)">
        <div class="mobile-product-list">
          <div
            v-for="row in group.rows"
            :key="row.id"
            class="mobile-product-item"
            :class="{
              'row-inactive': !row.is_active,
              'row-needs-check': !row.complete && row.is_active
            }"
          >
            <!-- 첫 줄: 제품명 / 미확인 / 폐기율 / 상세 -->
            <div class="mobile-product-heading d-flex align-center ga-2">
              <button
                type="button"
                class="mobile-product-name"
                :title="row.name"
                @click="emit('product', row)"
              >
                {{ row.name }}
              </button>

              <span
                v-if="!row.complete && row.is_active"
                class="mobile-pending-label"
              >
                미확인
              </span>

              <button
                type="button"
                class="mobile-rate-button d-flex align-center justify-center"
                :aria-label="`${row.name} 폐기율 조회`"
                @click="emit('waste-rate', row, 'waste_rate')"
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

              <button
                type="button"
                class="mobile-detail-toggle d-flex align-center justify-center"
                :aria-label="
                  `${row.name} 상세 ${
                    expandedRows.has(row.id) ? '닫기' : '보기'
                  }`
                "
                :aria-expanded="expandedRows.has(row.id)"
                @click="emit('toggle-row-detail', row.id)"
              >
                <v-icon
                  :icon="
                    expandedRows.has(row.id)
                      ? 'mdi-chevron-up'
                      : 'mdi-chevron-down'
                  "
                  size="18"
                />
              </button>
            </div>

            <!-- 둘째 줄: 생산 / 이월 / 로스 / 폐기 -->
            <div class="mobile-product-values d-flex">
              <!-- 생산 -->
              <button
                type="button"
                class="mobile-value-button d-flex flex-column align-center justify-center"
                :class="{
                  pending: !row.production_confirmed
                }"
                @click="emit('production', row)"
              >
                <span class="mobile-value-label">생산</span>
                <strong>
                  {{
                    row.production_confirmed
                      ? row.production
                      : '-'
                  }}
                </strong>
              </button>

              <!-- 이월: 재고와 예정 구분 -->
              <button
                type="button"
                class="mobile-value-button d-flex flex-column align-center justify-center"
                :class="{
                  pending: !row.disposition_confirmed
                }"
                @click="emit('flow', row, 'carryover')"
              >
                <span class="mobile-value-label">이월</span>

                <span
                  v-if="
                    Number(row.carryover_in || 0) > 0 ||
                    Number(row.carryover_out || 0) > 0
                  "
                  class="mobile-carryover-values d-flex flex-column align-center"
                >
                  <strong
                    v-if="Number(row.carryover_in || 0) > 0"
                  >
                    재고 {{ row.carryover_in }}
                  </strong>

                  <strong
                    v-if="Number(row.carryover_out || 0) > 0"
                  >
                    예정 {{ row.carryover_out }}
                  </strong>
                </span>

                <strong v-else>
                  {{
                    row.disposition_confirmed
                      ? '0'
                      : '-'
                  }}
                </strong>
              </button>

              <!-- 로스 -->
              <button
                type="button"
                class="mobile-value-button d-flex flex-column align-center justify-center"
                :class="{
                  pending: !row.loss_confirmed
                }"
                @click="emit('flow', row, 'loss')"
              >
                <span class="mobile-value-label">로스</span>
                <strong>
                  {{
                    row.loss_confirmed
                      ? Number(row.operational_loss || 0)
                      : '-'
                  }}
                </strong>
              </button>

              <!-- 폐기: 최초 생산일 귀속 기준 -->
              <button
                type="button"
                class="mobile-value-button d-flex flex-column align-center justify-center"
                :class="{
                  pending:
                    !row.waste_confirmed &&
                    Number(row.attributed_waste || 0) === 0
                }"
                @click="emit('flow', row, 'waste')"
              >
                <span class="mobile-value-label">폐기</span>
                <strong>
                  {{
                    row.waste_confirmed ||
                    Number(row.attributed_waste || 0) > 0
                      ? Number(row.attributed_waste || 0)
                      : '-'
                  }}
                </strong>
              </button>
            </div>

            <!-- 제품 상세 -->
            <div
              v-if="expandedRows.has(row.id)"
              class="mobile-product-detail"
            >
              <div class="mobile-detail-grid">
                <div
                  v-if="Number(row.carryover_in || 0) > 0"
                  class="mobile-detail-fact d-flex flex-column"
                >
                  <span>이월 재고</span>
                  <strong>{{ row.carryover_in }}개</strong>
                </div>

                <div
                  v-if="Number(row.carryover_out || 0) > 0"
                  class="mobile-detail-fact d-flex flex-column"
                >
                  <span>이월 예정</span>
                  <strong>{{ row.carryover_out }}개</strong>
                </div>

                <div class="mobile-detail-fact d-flex flex-column">
                  <span>폐기</span>
                  <strong>
                    {{ Number(row.attributed_waste || 0) }}개
                  </strong>
                </div>

                <div
                  v-if="carryoverWaste(row) > 0"
                  class="mobile-detail-fact d-flex flex-column"
                >
                  <span>이월 재고 폐기</span>
                  <strong>{{ carryoverWaste(row) }}개</strong>
                </div>
              </div>

              <button
                type="button"
                class="mobile-stock-flow d-flex align-center justify-space-between ga-2"
                @click="emit('stock-flow', row)"
              >
                <span>재고 흐름</span>
                <v-icon
                  icon="mdi-chevron-right"
                  size="18"
                />
              </button>

              <p class="mobile-stock-flow-text">
                {{ stockFlowText(row) }}
              </p>
            </div>
          </div>

          <!-- 카테고리 소계 -->
          <div class="mobile-subtotal">
            <div class="mobile-subtotal-heading d-flex align-center justify-space-between ga-2">
              <strong>소계</strong>

              <div class="d-flex align-center ga-2">
                <button
                  type="button"
                  class="mobile-subtotal-help"
                  @click="emit('waste-guide')"
                >
                  폐기율 안내
                </button>

                <span>{{ groupWasteRate(group.rows) }}</span>
              </div>
            </div>

            <div class="mobile-subtotal-values d-flex">
              <div class="mobile-subtotal-value d-flex flex-column align-center justify-center">
                <span>생산</span>
                <strong>
                  {{ sum(group.rows, 'production') }}
                </strong>
              </div>

              <div class="mobile-subtotal-value d-flex flex-column align-center justify-center">
                <span>이월</span>

                <strong
                  v-if="sum(group.rows, 'carryover_in') > 0"
                >
                  재고 {{ sum(group.rows, 'carryover_in') }}
                </strong>

                <strong
                  v-if="sum(group.rows, 'carryover_out') > 0"
                >
                  예정 {{ sum(group.rows, 'carryover_out') }}
                </strong>

                <strong
                  v-if="
                    sum(group.rows, 'carryover_in') === 0 &&
                    sum(group.rows, 'carryover_out') === 0
                  "
                >
                  0
                </strong>
              </div>

              <div class="mobile-subtotal-value d-flex flex-column align-center justify-center">
                <span>로스</span>
                <strong>{{ sum(group.rows, 'loss') }}</strong>
              </div>

              <div class="mobile-subtotal-value d-flex flex-column align-center justify-center">
                <span>폐기</span>
                <strong>
                  {{ sum(group.rows, 'attributed_waste') }}
                </strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </v-expand-transition>
  </section>
</template>

<script setup>
const props = defineProps({
  groupedRows: {
    type: Array,
    default: () => [],
  },
  collapsed: {
    type: Set,
    default: () => new Set(),
  },
  expandedRows: {
    type: Set,
    default: () => new Set(),
  },
  workDate: {
    type: String,
    default: '',
  },
});

const emit = defineEmits([
  'toggle-category',
  'toggle-row-detail',
  'product',
  'production',
  'flow',
  'waste-rate',
  'waste-guide',
  'stock-flow',
]);

// 활성 제품 중 필수 기록을 완료한 제품 수
function categoryCompleteCount(rows) {
  return rows.filter(
    (row) => row.is_active && row.complete
  ).length;
}

// 기존 카테고리 진행률 계산 유지
function categoryProgress(rows) {
  const active = rows.filter((row) => row.is_active);

  return active.length
    ? Math.round(
        (categoryCompleteCount(rows) / active.length) * 100
      )
    : 100;
}

function categoryProgressText(rows) {
  const active = rows.filter((row) => row.is_active);
  const remaining = active.length - categoryCompleteCount(rows);

  return remaining > 0
    ? `${active.length}개 제품 · ${remaining}개 기록 미완료`
    : `${active.length}개 제품 · 확인 완료`;
}

// 기존 카테고리 소계 계산 유지
function sum(rows, key) {
  return rows.reduce(
    (total, row) => total + Number(row[key] || 0),
    0
  );
}

// 최초 생산일 귀속 폐기를 기준으로 폐기율 계산
function groupWasteRate(rows) {
  const production = sum(rows, 'production');

  if (!production) {
    return '-';
  }

  const attributedWaste = sum(rows, 'attributed_waste');
  const wasteRate = (attributedWaste / production) * 100;

  return `${wasteRate.toFixed(1)}%`;
}

// 이월 재고에서 발생한 실제 폐기 수량
function carryoverWaste(row) {
  return (row.operational_waste_details || [])
    .filter(
      (item) =>
        item.origin_production_date &&
        item.origin_production_date !== props.workDate
    )
    .reduce(
      (total, item) =>
        total + Number(item.quantity || 0),
      0
    );
}

// 기존 재고 흐름 안내 문구 유지
function stockFlowText(row) {
  const parts = [
    `오늘 생산 ${Number(row.production || 0)}개`,
  ];

  if (row.carryover_in) {
    parts.push(`이월 재고 ${row.carryover_in}개`);
  }

  if (row.operational_loss) {
    parts.push(`로스 ${row.operational_loss}개`);
  }

  const carryover = carryoverWaste(row);

  if (row.operational_waste > carryover) {
    parts.push(
      `오늘 생산분 폐기 ${row.operational_waste - carryover}개`
    );
  }

  if (carryover) {
    parts.push(`이월 재고 폐기 ${carryover}개`);
  }

  if (row.carryover_out) {
    parts.push(`다음날 이월 ${row.carryover_out}개`);
  }

  return parts.join(' → ');
}
</script>

<style scoped>
/* 카테고리 기본 구조 */
.category-block {
  min-width: 0;
  margin-bottom: 14px;
  border: 1px solid rgba(var(--v-border-color), .72);
  border-radius: 12px;
  overflow: hidden;
  background: rgb(var(--v-theme-surface));
}

.category-header {
  width: 100%;
  min-height: 52px;
  padding: 12px;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.category-heading-copy {
  min-width: 0;
  gap: 3px;
}

.category-heading-copy strong {
  font-size: .84rem;
  font-weight: 650;
  line-height: 1.4;
}

.category-heading-copy span,
.category-heading-side {
  font-size: .65rem;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), .6);
}

.category-heading-side {
  flex: 0 0 auto;
  white-space: nowrap;
}

.category-progress {
  height: 2px;
  background: rgba(var(--v-theme-on-surface), .06);
}

.category-progress span {
  display: block;
  height: 100%;
  background: rgba(var(--v-theme-primary), .72);
  transition: width .2s ease;
}

/* 비활성 제품 */
.row-inactive {
  opacity: .5;
}

/* 제품별 목록 */
.mobile-product-item {
  min-width: 0;
  padding: 12px 11px 13px;
  border-top: 1px solid rgba(var(--v-border-color), .55);
}

.mobile-product-item:first-child {
  border-top: 0;
}

/* 첫 줄: 제품명 및 상태 */
.mobile-product-heading {
  min-width: 0;
  min-height: 44px;
  margin-bottom: 8px;
}

.mobile-product-name {
  flex: 1 1 0;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  border: 0;
  padding: 6px 0;
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  font-size: .8rem;
  font-weight: 650;
  line-height: 1.4;
  text-align: left;
  cursor: pointer;
}

.mobile-pending-label {
  flex: 0 0 auto;
  font-size: .64rem;
  line-height: 1.4;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), .65);
  white-space: nowrap;
}

.mobile-rate-button {
  flex: 0 0 auto;
  min-height: 44px;
  gap: 5px;
  padding: 6px 2px;
  border: 0;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), .7);
  cursor: pointer;
  white-space: nowrap;
}

.mobile-rate-button span {
  font-size: .64rem;
}

.mobile-rate-button strong {
  font-size: .73rem;
  font-weight: 650;
  font-variant-numeric: tabular-nums;
}

.mobile-detail-toggle {
  flex: 0 0 40px;
  width: 40px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), .65);
  cursor: pointer;
}

/* 둘째 줄: 수량 표시 */
.mobile-product-values,
.mobile-subtotal-values {
  width: 100%;
  min-width: 0;
}

.mobile-value-button,
.mobile-subtotal-value {
  flex: 1 1 0;
  min-width: 0;
  text-align: center;
}

.mobile-value-button {
  min-height: 50px;
  gap: 5px;
  padding: 7px 3px;
  border: 0;
  border-right: 1px solid rgba(var(--v-border-color), .5);
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  font-variant-numeric: tabular-nums;
}

.mobile-value-button:last-child {
  border-right: 0;
}

.mobile-value-label {
  font-size: .67rem;
  font-weight: 400;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), .62);
}

.mobile-value-button strong {
  max-width: 100%;
  font-size: .85rem;
  font-weight: 650;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

/* 미확인 수량 */
.mobile-value-button.pending > strong,
.mobile-value-button.pending > .mobile-carryover-values {
  color: rgba(var(--v-theme-on-surface), .55);
}

/* 터치 피드백 */
.mobile-value-button:active,
.mobile-detail-toggle:active,
.mobile-rate-button:active {
  background: rgba(var(--v-theme-on-surface), .045);
}

/* 이월 재고 및 예정 */
.mobile-carryover-values {
  gap: 3px;
  max-width: 100%;
  min-width: 0;
}

.mobile-carryover-values strong {
  max-width: 100%;
  font-size: .68rem;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

/* 제품 상세 */
.mobile-product-detail {
  margin-top: 12px;
  padding: 12px 2px 3px;
  border-top: 1px solid rgba(var(--v-border-color), .45);
}

.mobile-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 10px;
}

.mobile-detail-fact {
  gap: 5px;
  min-width: 0;
}

.mobile-detail-fact span {
  font-size: .66rem;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), .6);
}

.mobile-detail-fact strong {
  font-size: .76rem;
  font-weight: 600;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

/* 재고 흐름 */
.mobile-stock-flow {
  width: 100%;
  min-height: 44px;
  margin-top: 11px;
  padding: 9px 2px;
  border: 0;
  border-top: 1px solid rgba(var(--v-border-color), .45);
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  font-size: .75rem;
  text-align: left;
  cursor: pointer;
}

.mobile-stock-flow-text {
  margin: 0;
  padding: 2px 2px 7px;
  font-size: .68rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), .65);
  overflow-wrap: anywhere;
}

/* 카테고리 소계 */
.mobile-subtotal {
  padding: 13px 11px 15px;
  border-top: 1px solid rgba(var(--v-border-color), .7);
  background: rgba(var(--v-theme-on-surface), .04);
}

.mobile-subtotal-heading {
  min-width: 0;
  margin-bottom: 11px;
}

.mobile-subtotal-heading > strong {
  font-size: .78rem;
  font-weight: 700;
  line-height: 1.4;
}

.mobile-subtotal-heading > div {
  flex: 0 0 auto;
}

.mobile-subtotal-heading > div > span {
  font-size: .74rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.mobile-subtotal-help {
  border: 0;
  padding: 5px 2px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), .62);
  font-size: .64rem;
  text-decoration: underline;
  text-decoration-style: dotted;
  text-underline-offset: 2px;
  cursor: pointer;
}

.mobile-subtotal-value {
  gap: 5px;
  padding: 7px 3px;
  border-right: 1px solid rgba(var(--v-border-color), .5);
}

.mobile-subtotal-value:last-child {
  border-right: 0;
}

.mobile-subtotal-value span {
  font-size: .66rem;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), .62);
}

.mobile-subtotal-value strong {
  max-width: 100%;
  font-size: .76rem;
  font-weight: 700;
  line-height: 1.4;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

/* 최소 320px 모바일 환경 */
@media (max-width: 360px) {
  .mobile-product-item {
    padding-inline: 9px;
  }

  .mobile-product-heading {
    gap: 4px;
  }

  .mobile-product-name {
    font-size: .76rem;
  }

  .mobile-pending-label {
    font-size: .61rem;
  }

  .mobile-rate-button {
    gap: 3px;
  }

  .mobile-rate-button span {
    font-size: .6rem;
  }

  .mobile-rate-button strong {
    font-size: .68rem;
  }

  .mobile-detail-toggle {
    flex-basis: 36px;
    width: 36px;
  }

  .mobile-value-button {
    padding-inline: 2px;
  }

  .mobile-value-button strong {
    font-size: .8rem;
  }

  .mobile-carryover-values strong {
    font-size: .63rem;
  }

  .mobile-subtotal {
    padding-inline: 9px;
  }

  .mobile-subtotal-value {
    padding-inline: 2px;
  }

  .mobile-subtotal-value strong {
    font-size: .7rem;
  }
}
</style>