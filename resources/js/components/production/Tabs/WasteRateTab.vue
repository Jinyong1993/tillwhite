<template>
  <div class="inventory-dialog inventory-dialog__waste-rate-tab">
    <!-- 통계 불러오는 중 -->
    <div v-if="loading" class="inventory-dialog__status">
      <v-progress-circular
        indeterminate
        size="22"
        width="2"
      />
      <span>폐기율 통계를 불러오는 중입니다.</span>
    </div>

    <!-- 조회 오류 -->
    <div v-else-if="error" class="inventory-dialog__status">
      <span>{{ error }}</span>

      <v-btn
        size="small"
        variant="outlined"
        class="inventory-dialog__retry-button"
        @click="loadStatistics"
      >
        다시 시도
      </v-btn>
    </div>

    <template v-else-if="statistics">
      <!-- 최근 7일 / 이전 7일 비교 -->
      <section class="inventory-dialog__card">
        <div class="inventory-dialog__heading">
          <strong>폐기율 비교</strong>
        </div>

        <div class="inventory-dialog__comparison">
          <div class="inventory-dialog__metric">
            <span class="inventory-dialog__metric-label">
              최근 7일
            </span>

            <span class="inventory-dialog__metric-period">
              {{ recentPeriod }}
            </span>

            <strong class="inventory-dialog__metric-value">
              {{ formatRate(recent?.waste_rate) }}
            </strong>

            <span class="inventory-dialog__metric-detail">
              생산 {{ formatQuantity(recent?.production) }}개 /
              폐기 {{ formatQuantity(recent?.attributed_waste) }}개
            </span>
          </div>

          <div class="inventory-dialog__metric">
            <span class="inventory-dialog__metric-label">
              이전 7일
            </span>

            <span class="inventory-dialog__metric-period">
              {{ previousPeriod }}
            </span>

            <strong class="inventory-dialog__metric-value">
              {{ formatRate(previous?.waste_rate) }}
            </strong>

            <span class="inventory-dialog__metric-detail">
              생산 {{ formatQuantity(previous?.production) }}개 /
              폐기 {{ formatQuantity(previous?.attributed_waste) }}개
            </span>
          </div>
        </div>

        <div
          v-if="comparisonAvailable"
          class="inventory-dialog__difference"
          :class="{
            'inventory-dialog__difference--good': difference < 0,
            'inventory-dialog__difference--bad': difference > 0,
          }"
        >
          <v-icon
            v-if="difference !== null && difference !== 0"
            :icon="difference > 0 ? 'mdi-arrow-up' : 'mdi-arrow-down'"
            size="16"
          />

          <strong>{{ differenceText }}</strong>
          <span>{{ differenceDescription }}</span>
        </div>

        <p
          v-if="!comparisonAvailable"
          class="inventory-dialog__form-help"
        >
          <template v-if="!recent?.complete || !previous?.complete">
            생산 미확인 날짜가 포함되어 있습니다.
            표시된 폐기율은 잠정 수치이며, 기간 비교는
            양쪽 기간의 생산 확인이 완료된 후 제공됩니다.
          </template>

          <template v-else>
            비교 기간 중 생산량이 0개인 기간이 있어
            폐기율 증감을 계산할 수 없습니다.
          </template>
        </p>
      </section>

      <!-- 날짜별 폐기율 막대그래프 -->
      <section class="inventory-dialog__card">
        <div class="inventory-dialog__heading">
          <strong>최근 7일 날짜별 폐기율</strong>
        </div>

        <div class="inventory-dialog__chart">
          <button
            v-for="day in daily"
            :key="day.date"
            type="button"
            class="inventory-dialog__chart-item"
            :class="{
              'inventory-dialog__chart-item--selected':
                selectedDate === day.date,
            }"
            :aria-pressed="selectedDate === day.date"
            :aria-label="`${day.date} 폐기율 ${formatRate(day.waste_rate)}`"
            @click="selectedDate = day.date"
          >
            <span class="inventory-dialog__chart-value">
              {{ formatRate(day.waste_rate) }}
            </span>

            <span class="inventory-dialog__chart-track">
              <span
                v-if="day.waste_rate != null"
                class="inventory-dialog__chart-bar"
                :class="{
                  'inventory-dialog__chart-bar--zero':
                    Number(day.waste_rate) === 0,
                }"
                :style="{ height: barHeight(day.waste_rate) }"
              />

              <span
                v-else
                class="inventory-dialog__chart-missing"
              />
            </span>

            <span class="inventory-dialog__chart-date">
              {{ formatShortDate(day.date) }}
            </span>
          </button>
        </div>

        <div
          v-if="selectedDay"
          class="inventory-dialog__day-detail"
        >
          <strong>{{ selectedDay.date }}</strong>

          <span>
            생산
            {{
              selectedDay.production === null
                ? "미확인"
                : `${formatQuantity(selectedDay.production)}개`
            }}
          </span>

          <span>
            폐기 {{ formatQuantity(selectedDay.attributed_waste) }}개
          </span>

          <span>
            폐기율 {{ formatRate(selectedDay.waste_rate) }}
          </span>
        </div>

        <p class="inventory-dialog__form-help">
          생산량이 0개이거나 미확인인 날짜는 폐기율을 표시하지 않습니다.
        </p>
      </section>

      <!-- 폐기 사유별 구성비 -->
      <section class="inventory-dialog__card">
        <div class="inventory-dialog__heading">
          <strong>폐기 사유별 구성비</strong>
          <span>최근 7일 생산분 기준</span>
        </div>

        <template v-if="reasonSegments.length">
          <div class="inventory-dialog__reason-layout">
            <div
              class="inventory-dialog__donut"
              :style="{ background: donutBackground }"
              role="img"
              :aria-label="`총 폐기 수량 ${formatQuantity(totalReasonQuantity)}개`"
            >
              <div class="inventory-dialog__donut-center">
                <strong>{{ formatQuantity(totalReasonQuantity) }}</strong>
                <span>총 폐기 수량</span>
              </div>
            </div>

            <div class="inventory-dialog__legend">
              <div
                v-for="reason in reasonSegments"
                :key="reason.key"
                class="inventory-dialog__legend-item"
              >
                <span
                  class="inventory-dialog__legend-color"
                  :style="{ backgroundColor: reason.color }"
                />

                <span class="inventory-dialog__legend-name">
                  {{ reason.label }}
                </span>

                <strong>
                  {{ formatQuantity(reason.quantity) }}개
                </strong>

                <span class="inventory-dialog__legend-rate">
                  {{ reason.percentage.toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>
        </template>

        <p v-else class="inventory-dialog__empty">
          해당 기간에 등록된 폐기 사유가 없습니다.
        </p>
      </section>
    </template>

    <!-- 폐기율 안내 -->
    <div class="inventory-dialog__guide">
      <div class="inventory-dialog__guide-heading">
        <strong>폐기율 안내</strong>
      </div>

      <p class="inventory-dialog__form-help">
        폐기율은 최초 생산일 기준으로 계산하며,
        이월 재고를 나중에 폐기해도 최초 생산일에 반영됩니다.
      </p>

      <p class="inventory-dialog__form-help">
        <strong class="inventory-dialog__notice">
          표시된 폐기율은 조회 시점 기준이며,
          이후 폐기 기록의 추가·수정에 따라 변경될 수 있습니다.
        </strong>
      </p>

      <div class="inventory-dialog__formula">
        <span>계산식</span>
        <strong>폐기 수량 ÷ 생산 수량 × 100</strong>
      </div>

      <p class="inventory-dialog__example">
        <strong>예시</strong>
        10/3 생산 10개 중 2개를 10/5에 폐기 → 10/3 폐기율 20%
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, shallowRef, ref, watch } from "vue";

// 부모 다이얼로그에서 전달받는 제품과 업무 날짜
const props = defineProps({
  product: {
    type: Object,
    default: null,
  },
  workDate: {
    type: String,
    default: "",
  },
  reasonOptions: {
    type: Array,
    default: () => [],
  },
});

// API 조회 상태
const statistics = shallowRef(null);
const loading = ref(false);
const error = ref("");
const selectedDate = ref(null);

// 이전 조회 요청 중단
let controller = null;
let requestId = 0;

// 서버에서 받은 폐기 사유 목록을 조회용 Map으로 변환
const reasonLabelMap = computed(
  () =>
    new Map(
      props.reasonOptions.map((option) => [
        option.value,
        option.title,
      ])
    )
);

// 그래프 색상 설정
const chartColors = [
  "#5278bd",
  "#e4a148",
  "#60a58a",
  "#a28bc8",
  "#d67f8a",
  "#8a9aaa",
];

// 공통 수량 표시
function formatQuantity(value) {
  return Number(value ?? 0).toLocaleString("ko-KR");
}

// 공통 폐기율 표시
function formatRate(value) {
  if (value === null || value === undefined) {
    return "-";
  }

  return `${Number(value).toFixed(1)}%`;
}

// 날짜 표시
function formatShortDate(value) {
  if (!value) return "-";

  return value.slice(5).replace("-", "/");
}

// 서버에서 제품별 폐기율 통계 조회
async function loadStatistics() {
  const currentRequest = ++requestId;

  // 이전 요청이 진행 중이면 중단
  controller?.abort();

  statistics.value = null;
  error.value = "";
  selectedDate.value = null;

  const productId = props.product?.id;
  const workDate = props.workDate;

  if (!productId || !workDate) {
    loading.value = false;
    controller = null;
    return;
  }

  controller = new AbortController();
  const currentController = controller;

  loading.value = true;

  try {
    const response = await window.axios.get(
      `/tillwhite/api/production-management/products/${encodeURIComponent(productId)}/waste-statistics`,
      {
        params: {
          work_date: workDate,
        },
        signal: currentController.signal,
      }
    );

    // 이전 요청의 응답이면 무시
    if (currentRequest !== requestId) return;

    statistics.value = response.data;

    // 선택 날짜를 최근 조회일로 초기화
    const days = response.data?.daily ?? [];

    selectedDate.value = days.at(-1)?.date ?? null;
  } catch (err) {
    if (currentRequest !== requestId) return;

    if (
      err.code === "ERR_CANCELED" ||
      err.name === "CanceledError"
    ) {
      return;
    }

    error.value =
      err.response?.data?.message ||
      "폐기율 통계를 불러오지 못했습니다.";
  } finally {
    if (currentRequest === requestId) {
      loading.value = false;
      controller = null;
    }
  }
}

// 제품 또는 업무 날짜 변경 시에만 재조회
watch(
  () => [props.product?.id, props.workDate],
  loadStatistics,
  { immediate: true }
);

// 탭 제거 시 진행 중인 요청 정리
onBeforeUnmount(() => {
  requestId++;
  controller?.abort();
  controller = null;
});

// YYYY-MM-DD 문자열을 로컬 시간 기준 날짜로 변환
function parseWorkDate(value) {
  const [year, month, day] = value.split("-").map(Number);
  return new Date(year, month - 1, day);
}

// 선택 날짜에서 지정한 일수만큼 이동
function shiftDate(value, days) {
  if (!value) return "";

  const date = parseWorkDate(value);
  date.setDate(date.getDate() + days);

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

// 최근 7일: 선택 날짜 포함 7일
const recentPeriod = computed(() => {
  if (!props.workDate) return "";

  return `${shiftDate(props.workDate, -6)} ~ ${props.workDate}`;
});

// 이전 7일: 최근 7일 바로 이전의 7일
const previousPeriod = computed(() => {
  if (!props.workDate) return "";

  return `${shiftDate(props.workDate, -13)} ~ ${shiftDate(props.workDate, -7)}`;
});

// API 통계 데이터
const recent = computed(() => statistics.value?.recent ?? null);
const previous = computed(() => statistics.value?.previous ?? null);
const daily = computed(() => statistics.value?.daily ?? []);

// 두 기간의 생산 확인이 완료되고 폐기율이 계산된 경우에만 비교
const comparisonAvailable = computed(() => {
  return (
    recent.value?.complete === true &&
    previous.value?.complete === true &&
    recent.value?.waste_rate != null &&
    previous.value?.waste_rate != null
  );
});

// 이전 기간 대비 폐기율 증감
const difference = computed(() => {
  const value = statistics.value?.difference_percentage_points;

  return value === null || value === undefined
    ? null
    : Number(value);
});

const differenceText = computed(() => {
  if (difference.value === null) return "비교 불가";

  if (difference.value === 0) return "변동 없음";

  const sign = difference.value > 0 ? "+" : "";

  return `${sign}${difference.value.toFixed(1)}%p`;
});

const differenceDescription = computed(() => {
  if (difference.value === null) {
    return "비교할 생산 기록이 없습니다.";
  }

  if (difference.value < 0) {
    return "이전 기간보다 폐기율 감소";
  }

  if (difference.value > 0) {
    return "이전 기간보다 폐기율 증가";
  }

  return "이전 기간과 동일";
});

// 날짜별 막대그래프 최대 높이 계산
const maxRate = computed(() => {
  const rates = daily.value
    .map((day) => day.waste_rate)
    .filter((rate) => rate !== null && rate !== undefined)
    .map(Number);

  return Math.max(10, ...rates);
});

// 폐기율에 따라 막대 높이 계산
function barHeight(value) {
  const rate = Number(value);

  if (!Number.isFinite(rate) || rate < 0) {
    return "0%";
  }

  // 실제 폐기율 0%는 얇은 막대로 표시
  if (rate === 0) {
    return "3px";
  }

  return `${Math.max(
    3,
    Math.min(100, (rate / maxRate.value) * 100)
  )}%`;
}

// 선택한 날짜의 상세 정보
const selectedDay = computed(() =>
  daily.value.find((day) => day.date === selectedDate.value) ?? null
);

// 폐기 사유별 집계 데이터를 화면 표시 형태로 변환
const reasons = computed(() => {
  const rows = statistics.value?.reasons ?? [];

  return rows
    .map((row, index) => {
      const code = row.reason_code;
      const quantity = Number(row.quantity ?? 0);
      const customText = String(row.reason_text ?? "").trim();

      return {
        key: `${code}-${customText}-${index}`,
        label:
          code === "other" && customText
            ? customText
            : reasonLabelMap.value.get(code) ?? code ?? "기타",
        quantity,
      };
    })
    .filter(
      (row) =>
        Number.isFinite(row.quantity) &&
        row.quantity > 0
    )
    .sort((a, b) => b.quantity - a.quantity);
});

// 사유별 합계 수량
const totalReasonQuantity = computed(() =>
  reasons.value.reduce((total, row) => total + row.quantity, 0)
);

// 도넛그래프 비율 및 색상 계산
const reasonSegments = computed(() => {
  if (!totalReasonQuantity.value) return [];

  let accumulated = 0;

  return reasons.value.map((reason, index) => {
    const start = accumulated;
    const percentage =
      (reason.quantity / totalReasonQuantity.value) * 100;

    accumulated += percentage;

    return {
      ...reason,
      start,
      end: accumulated,
      percentage,
      color: chartColors[index % chartColors.length],
    };
  });
});

// CSS 원형그래프 생성
const donutBackground = computed(() => {
  if (!reasonSegments.value.length) {
    return "rgba(var(--v-theme-on-surface), 0.08)";
  }

  const stops = reasonSegments.value.map(
    (reason) =>
      `${reason.color} ${reason.start}% ${reason.end}%`
  );

  return `conic-gradient(${stops.join(", ")})`;
});
</script>

<style scoped>
/* 폐기율 탭 기본 영역 */
.inventory-dialog__waste-rate-tab {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  font-size: 12px;
  overflow-wrap: anywhere;
}

/* 통계 카드 및 폐기율 안내 카드 */
.inventory-dialog__card,
.inventory-dialog__guide {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  padding: 14px 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  background: transparent;
  text-align: left;
}

/* 통계 제목 */
.inventory-dialog__heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.inventory-dialog__heading strong {
  font-size: 13px;
  font-weight: 600;
}

.inventory-dialog__heading > span {
  font-size: 11px;
  opacity: 0.7;
}

/* 폐기율 비교 */
.inventory-dialog__comparison {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
}

.inventory-dialog__metric {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  padding: 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
}

.inventory-dialog__metric-label {
  font-size: 11px;
  opacity: 0.7;
}

/* 비교 기간 표시 */
.inventory-dialog__metric-period {
  font-size: 10px;
  line-height: 1.4;
  opacity: 0.65;
  white-space: normal;
  overflow-wrap: anywhere;
}

.inventory-dialog__metric-value {
  font-size: 22px;
  font-weight: 700;
  line-height: 1.2;
}

.inventory-dialog__metric-detail {
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  overflow-wrap: anywhere;
}

/* 이전 기간 대비 증감 */
.inventory-dialog__difference {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 11px;
}

.inventory-dialog__difference strong {
  font-size: 12px;
  font-weight: 600;
}

.inventory-dialog__difference--good {
  color: #388e65;
}

.inventory-dialog__difference--bad {
  color: #d26363;
}

/* 날짜별 막대그래프 */
.inventory-dialog__chart {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 7px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  height: 154px;
}

.inventory-dialog__chart-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 0;
  min-height: 44px;
  padding: 5px 2px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.inventory-dialog__chart-item--selected {
  background: rgba(var(--v-theme-primary), 0.07);
}

.inventory-dialog__chart-item:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.inventory-dialog__chart-value,
.inventory-dialog__chart-date {
  font-size: 10px;
  white-space: nowrap;
}

.inventory-dialog__chart-value {
  font-weight: 600;
}

.inventory-dialog__chart-track {
  display: flex;
  flex: 1;
  align-items: flex-end;
  justify-content: center;
  width: 100%;
  min-height: 0;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}

.inventory-dialog__chart-bar {
  display: block;
  width: 65%;
  max-width: 30px;
  border-radius: 5px 5px 0 0;
  background: rgb(var(--v-theme-primary));
}

/* 실제 폐기율 0%는 얇은 실선 */
.inventory-dialog__chart-bar--zero {
  opacity: 0.5;
}

.inventory-dialog__chart-missing {
  display: block;
  width: 65%;
  height: 2px;
  margin-bottom: 3px;
  border-top: 1px dashed rgba(var(--v-theme-on-surface), 0.35);
}

/* 선택한 날짜 상세 */
.inventory-dialog__day-detail {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  font-size: 11px;
}

.inventory-dialog__day-detail strong {
  font-weight: 600;
}

/* 폐기 사유별 도넛그래프 */
.inventory-dialog__reason-layout {
  display: flex;
  align-items: center;
  gap: 20px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
}

.inventory-dialog__donut {
  display: flex;
  flex: 0 0 130px;
  align-items: center;
  justify-content: center;
  width: 130px;
  height: 130px;
  border-radius: 50%;
}

.inventory-dialog__donut-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
}

.inventory-dialog__donut-center strong {
  font-size: 20px;
  font-weight: 700;
}

.inventory-dialog__donut-center span {
  font-size: 10px;
  opacity: 0.7;
}

/* 폐기 사유 목록 */
.inventory-dialog__legend {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 9px;
  min-width: 0;
}

.inventory-dialog__legend-item {
  display: flex;
  align-items: center;
  gap: 7px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  font-size: 11px;
}

.inventory-dialog__legend-color {
  flex: 0 0 9px;
  width: 9px;
  height: 9px;
  border-radius: 3px;
}

.inventory-dialog__legend-name {
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
}

.inventory-dialog__legend-item strong {
  font-weight: 600;
  white-space: nowrap;
}

.inventory-dialog__legend-rate {
  min-width: 40px;
  text-align: right;
  opacity: 0.7;
}

/* 빈 데이터 */
.inventory-dialog__empty {
  margin: 0;
  padding: 18px 0;
  font-size: 11px;
  line-height: 1.5;
  text-align: center;
  opacity: 0.7;
}

/* 로딩 및 오류 */
.inventory-dialog__status {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  min-height: 90px;
  font-size: 11px;
  line-height: 1.6;
  text-align: center;
}

.inventory-dialog__retry-button {
  min-height: 40px;
}

/* 폐기율 안내 제목 */
.inventory-dialog__guide-heading {
  display: flex;
  align-items: center;
  gap: 7px;
}

.inventory-dialog__guide-heading strong {
  font-size: 13px;
  font-weight: 600;
}

/* 안내 문구 */
.inventory-dialog__form-help {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

/* 계산식 */
.inventory-dialog__formula {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.inventory-dialog__formula span {
  font-size: 11px;
  opacity: 0.7;
}

.inventory-dialog__formula strong {
  font-size: 11px;
  font-weight: 600;
}

/* 계산 예시 */
.inventory-dialog__example {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

.inventory-dialog__example strong {
  margin-right: 6px;
  font-weight: 600;
}

/* 폐기율 통계 변동 안내 */
.inventory-dialog__notice {
  font-size: 11px;
  font-weight: 500;
}

/* 모바일 대응 */
@media (max-width: 600px) {
  .inventory-dialog__card,
  .inventory-dialog__guide {
    padding: 12px;
  }

  .inventory-dialog__chart {
    gap: 3px;
  }

  .inventory-dialog__metric-value {
    font-size: 19px;
  }

  .inventory-dialog__reason-layout {
    gap: 12px;
  }

  .inventory-dialog__donut {
    flex-basis: 110px;
    width: 110px;
    height: 110px;
  }

  .inventory-dialog__donut-center {
    width: 76px;
    height: 76px;
  }

  .inventory-dialog__donut-center strong {
    font-size: 17px;
  }
}

/* 작은 모바일 화면 */
@media (max-width: 430px) {
  .inventory-dialog__comparison {
    gap: 8px;
  }

  .inventory-dialog__metric {
    padding: 10px;
  }

  .inventory-dialog__chart {
    gap: 2px;
  }

  .inventory-dialog__chart-item {
    padding-inline: 0;
  }

  .inventory-dialog__chart-value,
  .inventory-dialog__chart-date {
    font-size: 9px;
  }
}

/* 320px 등 좁은 화면 대응 */
@media (max-width: 360px) {
  .inventory-dialog__card,
  .inventory-dialog__guide {
    padding: 10px;
  }

  .inventory-dialog__metric {
    padding: 8px;
  }

  .inventory-dialog__metric-value {
    font-size: 18px;
  }

  .inventory-dialog__reason-layout {
    flex-wrap: wrap;
    justify-content: center;
  }

  .inventory-dialog__donut {
    flex: 0 0 110px;
  }

  .inventory-dialog__legend {
    flex: 1 1 100%;
    width: 100%;
  }
}
</style>