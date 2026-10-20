<template>
  <section
    v-for="group in groupedRows"
    :key="group.name"
    class="category-block"
  >
    <button
      type="button"
      class="category-header"
      @click="emit('toggle-category', group.name)"
    >
      <div class="category-heading-copy">
        <strong>{{ group.name }}</strong>
        <span>{{ categoryProgressText(group.allRows) }}</span>
      </div>

      <div class="category-heading-side">
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

    <div class="category-progress" aria-hidden="true">
      <span
        :style="{
          width: `${categoryProgress(group.allRows)}%`
        }"
      />
    </div>

    <v-expand-transition>
      <div
        v-show="!collapsed.has(group.name)"
        class="product-table-wrap"
      >
        <table class="product-table">
          <colgroup>
            <col class="product-column" />
            <col
              v-for="key in 5"
              :key="key"
              class="number-column"
            />
          </colgroup>

          <thead>
            <tr>
              <th>제품명</th>
              <th>생산</th>
              <th>이월</th>
              <th>로스</th>
              <th>폐기</th>
              <th>
                <button
                  type="button"
                  class="waste-rate-header-button"
                  @click.stop="emit('waste-guide')"
                >
                  폐기율
                </button>
              </th>
            </tr>
          </thead>

          <tbody>
            <template
              v-for="row in group.rows"
              :key="row.id"
            >
              <tr
                :class="{
                  'row-inactive': !row.is_active,
                  'row-needs-check': !row.complete && row.is_active
                }"
              >
                <!-- 제품명 및 상세 펼치기 -->
                <td>
                  <div class="product-name-cell">
                    <button
                      type="button"
                      class="row-detail-toggle"
                      :aria-label="
                        `${row.name} 상세 ${
                          expandedRows.has(row.id) ? '닫기' : '보기'
                        }`
                      "
                      @click="emit('toggle-row-detail', row.id)"
                    >
                      <v-icon
                        :icon="
                          expandedRows.has(row.id)
                            ? 'mdi-chevron-up'
                            : 'mdi-chevron-down'
                        "
                        size="16"
                      />
                    </button>

                    <button
                      type="button"
                      class="product-name"
                      :title="row.name"
                      @click="emit('product', row)"
                    >
                      {{ row.name }}
                    </button>
                  </div>
                </td>

                <!-- 생산 -->
                <td>
                  <button
                    type="button"
                    class="table-value production-value"
                    :class="{
                      pending: !row.production_confirmed
                    }"
                    @click="emit('production', row)"
                  >
                    {{
                      row.production_confirmed
                        ? row.production
                        : '-'
                    }}
                  </button>
                </td>

                <!-- 이월 -->
                <td>
                  <button
                    type="button"
                    class="table-value carryover-value"
                    :class="{
                      pending: !row.disposition_confirmed
                    }"
                    @click="emit('flow', row, 'carryover')"
                  >
                    <template
                      v-if="
                        Number(row.carryover_in || 0) > 0 ||
                        Number(row.carryover_out || 0) > 0
                      "
                    >
                      <span
                        v-if="Number(row.carryover_in || 0) > 0"
                      >
                        재고 {{ row.carryover_in }}
                      </span>

                      <span
                        v-if="Number(row.carryover_out || 0) > 0"
                      >
                        예정 {{ row.carryover_out }}
                      </span>
                    </template>

                    <template v-else>
                      {{
                        row.disposition_confirmed
                          ? '0'
                          : '-'
                      }}
                    </template>
                  </button>
                </td>

                <!-- 로스 -->
                <td>
                  <button
                    type="button"
                    class="table-value"
                    :class="{
                      pending: !row.loss_confirmed
                    }"
                    @click="emit('flow', row, 'loss')"
                  >
                    {{
                      row.loss_confirmed
                        ? Number(row.operational_loss || 0)
                        : '-'
                    }}
                  </button>
                </td>

                <!-- 폐기: 최초 생산일 귀속 -->
                <td>
                  <button
                    type="button"
                    class="table-value waste-value"
                    :class="{
                      pending:
                        !row.waste_confirmed &&
                        Number(row.attributed_waste || 0) === 0
                    }"
                    @click="emit('flow', row, 'waste')"
                  >
                    {{
                      row.waste_confirmed ||
                      Number(row.attributed_waste || 0) > 0
                        ? Number(row.attributed_waste || 0)
                        : '-'
                    }}
                  </button>
                </td>

                <!-- 폐기율: 조회 전용 -->
                <td>
                  <button
                    type="button"
                    class="table-value rate-value"
                    @click="emit('waste-rate', row, 'waste_rate')"
                  >
                    {{
                      row.waste_rate == null
                        ? '-'
                        : `${row.waste_rate}%`
                    }}
                  </button>
                </td>
              </tr>

              <!-- 제품별 상세 -->
              <tr
                v-if="expandedRows.has(row.id)"
                class="product-detail-row"
              >
                <td colspan="6">
                  <div class="product-row-detail">
                    <div
                      v-if="Number(row.carryover_in || 0) > 0"
                    >
                      <span>이월 재고</span>
                      <strong>
                        {{ row.carryover_in }}개
                      </strong>
                    </div>

                    <div
                      v-if="Number(row.carryover_out || 0) > 0"
                    >
                      <span>이월 예정</span>
                      <strong>
                        {{ row.carryover_out }}개
                      </strong>
                    </div>

                    <div>
                      <span>폐기</span>
                      <strong>
                        {{ Number(row.attributed_waste || 0) }}개
                      </strong>
                    </div>

                    <div
                      v-if="carryoverWaste(row) > 0"
                    >
                      <span>이월 재고 폐기</span>
                      <strong>
                        {{ carryoverWaste(row) }}개
                      </strong>
                    </div>

                    <button
                      type="button"
                      class="product-row-flow stock-flow-trigger"
                      @click="emit('stock-flow', row)"
                    >
                      <span>재고 흐름</span>
                      <strong>
                        {{ stockFlowText(row) }}
                      </strong>
                    </button>
                  </div>
                </td>
              </tr>
            </template>

            <!-- 카테고리 소계 -->
            <tr class="subtotal-row">
              <td>소계</td>

              <td>
                {{ sum(group.rows, 'production') }}
              </td>

              <td>
                <div
                  v-if="sum(group.rows, 'carryover_in') > 0"
                >
                  재고 {{ sum(group.rows, 'carryover_in') }}
                </div>

                <div
                  v-if="sum(group.rows, 'carryover_out') > 0"
                >
                  예정 {{ sum(group.rows, 'carryover_out') }}
                </div>

                <span
                  v-if="
                    sum(group.rows, 'carryover_in') === 0 &&
                    sum(group.rows, 'carryover_out') === 0
                  "
                >
                  0
                </span>
              </td>

              <td>
                {{ sum(group.rows, 'loss') }}
              </td>

              <td>
                {{ sum(group.rows, 'attributed_waste') }}
              </td>

              <td>
                {{ groupWasteRate(group.rows) }}
              </td>
            </tr>
          </tbody>
        </table>
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

// 활성 제품 중 기록이 완료된 제품 수입니다.
function categoryCompleteCount(rows) {
  return rows.filter(
    (row) => row.is_active && row.complete
  ).length;
}

// 기존 카테고리 진행률 계산을 유지합니다.
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
  const remaining =
    active.length - categoryCompleteCount(rows);

  return remaining > 0
    ? `${active.length}개 제품 · ${remaining}개 기록 미완료`
    : `${active.length}개 제품 · 확인 완료`;
}

// 기존 소계 계산입니다.
function sum(rows, key) {
  return rows.reduce(
    (total, row) => total + Number(row[key] || 0),
    0
  );
}

// 폐기율은 최초 생산일 귀속 폐기량을 기준으로 합니다.
function groupWasteRate(rows) {
  const production = sum(rows, 'production');

  if (!production) {
    return '-';
  }

  const attributedWaste = sum(rows, 'attributed_waste');
  const wasteRate = (attributedWaste / production) * 100;

  return `${wasteRate.toFixed(1)}%`;
}

// 조회일과 최초 생산일이 다른 폐기 수량을 집계합니다.
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

// 기존 재고 흐름 요약 문구입니다.
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
.category-block {
  margin-bottom: 18px;
  border: 1px solid rgba(var(--v-border-color), .72);
  border-radius: 12px;
  overflow: hidden;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 2px 9px rgba(0, 0, 0, .045);
}

.category-header {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 13px 15px 10px;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.category-heading-copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.category-heading-copy strong {
  font-size: .9rem;
  font-weight: 650;
}

.category-heading-copy span,
.category-heading-side {
  font-size: .7rem;
  color: rgba(var(--v-theme-on-surface), .56);
}

.category-heading-side {
  display: flex;
  align-items: center;
  gap: 8px;
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

.product-table-wrap {
  overflow-x: hidden;
}

.product-table {
  width: 100%;
  min-width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  font-size: .7rem;
}

.product-column {
  width: 34%;
}

.number-column {
  width: 13.2%;
}

.product-table th,
.product-table td {
  height: 31px;
  padding: 3px 2px;
  border-top: 1px solid rgba(var(--v-border-color), .58);
  text-align: center;
  white-space: nowrap;
  font-weight: 400;
  font-variant-numeric: tabular-nums;
}

.product-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  height: 34px;
  background: rgb(var(--v-theme-surface));
  color: rgba(var(--v-theme-on-surface), .58);
  font-size: .69rem;
  font-weight: 550;
}

.product-table th:first-child,
.product-table td:first-child {
  position: sticky;
  left: 0;
  z-index: 3;
  width: 34%;
  max-width: 34%;
  text-align: left;
  background: rgb(var(--v-theme-surface));
  padding-left: .65rem;
  border-right: 1px solid rgba(var(--v-border-color), .38);
}

.product-table thead th:first-child {
  z-index: 4;
}

.product-table tbody tr:hover td,
.product-table tbody tr:hover td:first-child {
  background: rgba(var(--v-theme-on-surface), .035);
}

.waste-rate-header-button {
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
  text-decoration-style: dotted;
  text-underline-offset: 3px;
}

.product-name-cell {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 2px;
}

.product-name {
  display: block;
  width: 100%;
  min-width: 0;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  appearance: none;
  border: 0;
  padding: 0;
  background: none;
  color: inherit;
  font-size: .76rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
  transform: translateX(-5px);
}

.row-detail-toggle {
  display: grid;
  width: 24px;
  height: 24px;
  flex: 0 0 24px;
  place-items: center;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), .55);
  cursor: pointer;
  transform: translateX(-6px);
}

.table-value {
  min-width: 30px;
  padding: 4px 6px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), .045);
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.table-value:hover {
  background: rgba(var(--v-theme-on-surface), .06);
}

.table-value.production-value {
  background: rgba(76, 175, 80, .12);
  color: rgb(46, 125, 50);
}

.table-value.waste-value {
  background: rgba(239, 83, 80, .11);
  color: rgb(198, 40, 40);
}

.table-value.pending {
  background: rgba(var(--v-theme-on-surface), .035);
  color: rgba(var(--v-theme-on-surface), .42);
}

.rate-value {
  color: rgba(var(--v-theme-on-surface), .66);
  font-weight: 650;
}

.carryover-value span {
  display: block;
  line-height: 1.15;
}

.row-inactive {
  opacity: .5;
}

.product-detail-row td {
  position: static !important;
  height: auto !important;
  padding: 0 !important;
  white-space: normal !important;
  background: rgba(var(--v-theme-on-surface), .025) !important;
}

.product-row-detail {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  padding: 10px 12px;
}

.product-row-detail > div {
  min-width: 0;
}

.product-row-detail span,
.product-row-detail strong {
  display: block;
}

.product-row-detail span {
  font-size: .62rem;
  color: rgba(var(--v-theme-on-surface), .52);
}

.product-row-detail strong {
  margin-top: 2px;
  font-size: .72rem;
  font-weight: 600;
  white-space: normal;
}

.product-row-flow {
  grid-column: 1 / -1;
  padding-top: 6px;
  border-top: 1px solid rgba(var(--v-border-color), .45);
}

.stock-flow-trigger {
  width: 100%;
  text-align: left;
  border: 1px solid rgba(var(--v-theme-on-surface), .12);
  border-radius: 8px;
  background: transparent;
  padding: 8px;
  cursor: pointer;
}

.stock-flow-trigger span,
.stock-flow-trigger strong {
  display: block;
}

.subtotal-row td {
  background: rgba(var(--v-theme-on-surface), .055) !important;
  color: rgb(var(--v-theme-on-surface)) !important;
  font-weight: 650;
}

.subtotal-row:hover td {
  background: rgba(var(--v-theme-on-surface), .055) !important;
}

.subtotal-row td:first-child {
  background: rgba(var(--v-theme-on-surface), .075) !important;
}

@media (max-width: 760px) {
  .product-column,
  .product-table th:first-child,
  .product-table td:first-child {
    width: 34%;
    max-width: 34%;
  }

  .number-column {
    width: 13.2%;
  }

  .product-table {
    min-width: 100%;
    font-size: .64rem;
  }

  .product-table th,
  .product-table td {
    height: 32px;
    padding: 4px 3px;
  }

  .product-name {
    font-size: .72rem;
  }
}

@media (max-width: 480px) {
  .product-row-detail {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>