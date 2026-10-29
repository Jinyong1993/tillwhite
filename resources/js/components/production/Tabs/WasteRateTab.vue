<template>
  <div class="inventory-dialog inventory-dialog__waste-rate-tab">
    <!-- 조회 중 -->
    <div
      v-if="loading"
      class="inventory-dialog__status d-flex align-center justify-center ga-2"
      role="status"
      aria-live="polite"
      aria-busy="true"
    >
      <v-progress-circular
        indeterminate
        size="22"
        width="2"
      />
      <span>폐기율 통계를 불러오는 중입니다.</span>
    </div>

    <!-- 오류 -->
    <div
      v-else-if="error"
      class="inventory-dialog__status d-flex flex-column align-center ga-2"
      role="alert"
    >
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
      <!-- 폐기율 비교 -->
      <section class="inventory-dialog__card">
        <div class="inventory-dialog__heading">
          <strong>폐기율 비교</strong>
        </div>

        <!-- 7일 비교 -->
        <div class="app-dialog-subcard d-flex flex-column ga-2">
          <div class="d-flex align-center justify-space-between ga-2">
            <strong class="inventory-dialog__period-title">
              최근 7일 비교
            </strong>

            <span class="inventory-dialog__period-caption">
              이전 7일 대비
            </span>
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
                생산 {{ formatQuantity(recent?.production) }}개
                / 폐기 {{ formatQuantity(recent?.attributed_waste) }}개
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
                생산 {{ formatQuantity(previous?.production) }}개
                / 폐기 {{ formatQuantity(previous?.attributed_waste) }}개
              </span>
            </div>
          </div>

          <div
            v-if="comparisonAvailable"
            class="inventory-dialog__difference d-flex align-center flex-wrap ga-2"
            :class="differenceClass(difference)"
          >
            <strong>{{ formatDifference(difference) }}</strong>
            <span>{{ differenceDescription(difference) }}</span>
          </div>
          <p
            v-else
            class="inventory-dialog__form-help"
          >
            {{ comparisonUnavailableText(recent, previous) }}
          </p>
        </div>

        <!-- 30일 비교 -->
        <div class="app-dialog-subcard d-flex flex-column ga-2">
          <div class="d-flex align-center justify-space-between ga-2">
            <strong class="inventory-dialog__period-title">
              최근 30일 비교
            </strong>

            <span class="inventory-dialog__period-caption">
              이전 30일 대비
            </span>
          </div>

          <div class="inventory-dialog__comparison">
            <div class="inventory-dialog__metric">
              <span class="inventory-dialog__metric-label">
                최근 30일
              </span>

              <span class="inventory-dialog__metric-period">
                {{ recent30Period }}
              </span>

              <strong class="inventory-dialog__metric-value">
                {{ formatRate(recent30?.waste_rate) }}
              </strong>

              <span class="inventory-dialog__metric-detail">
                생산 {{ formatQuantity(recent30?.production) }}개
                / 폐기 {{ formatQuantity(recent30?.attributed_waste) }}개
              </span>
            </div>

            <div class="inventory-dialog__metric">
              <span class="inventory-dialog__metric-label">
                이전 30일
              </span>

              <span class="inventory-dialog__metric-period">
                {{ previous30Period }}
              </span>

              <strong class="inventory-dialog__metric-value">
                {{ formatRate(previous30?.waste_rate) }}
              </strong>

              <span class="inventory-dialog__metric-detail">
                생산 {{ formatQuantity(previous30?.production) }}개
                / 폐기 {{ formatQuantity(previous30?.attributed_waste) }}개
              </span>
            </div>
          </div>

          <div
            v-if="comparison30Available"
            class="inventory-dialog__difference d-flex align-center flex-wrap ga-2"
            :class="differenceClass(difference30)"
          >
            <strong>{{ formatDifference(difference30) }}</strong>
            <span>{{ differenceDescription(difference30) }}</span>
          </div>
          <p
            v-else
            class="inventory-dialog__form-help"
          >
            {{ comparisonUnavailableText(recent30, previous30) }}
          </p>
        </div>

        <p class="inventory-dialog__form-help">
          폐기율 변화는 두 기간의 폐기율 차이(%p)입니다.
          감소할수록 폐기 비율이 낮아진 것입니다.
        </p>
      </section>

      <!-- 상세 분석 -->
      <section class="inventory-dialog__card">
        <div class="inventory-dialog__heading">
          <strong>상세 분석</strong>
        </div>

        <!-- 내부 탭 -->
        <div
          class="inventory-dialog__tabs d-flex ga-1"
          role="tablist"
          aria-label="폐기율 상세 분석"
        >
          <button
            v-for="tab in analysisTabs"
            :key="tab.value"
            type="button"
            role="tab"
            class="inventory-dialog__tab"
            :class="{
              'inventory-dialog__tab--active':
                activeTab === tab.value,
            }"
            :aria-selected="activeTab === tab.value"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- 폐기율 추이 -->
        <div
          v-if="activeTab === 'trend'"
          class="d-flex flex-column ga-3"
          role="tabpanel"
        >
          <div class="d-flex align-center justify-space-between flex-wrap ga-2">
            <strong class="inventory-dialog__section-title">
              날짜별 폐기율
            </strong>

            <div class="inventory-dialog__range-switch d-flex ga-1">
              <button
                type="button"
                :class="{ 'is-active': chartRange === 7 }"
                :aria-pressed="chartRange === 7"
                @click="chartRange = 7"
              >
                최근 7일
              </button>

              <button
                type="button"
                :class="{ 'is-active': chartRange === 30 }"
                :aria-pressed="chartRange === 30"
                @click="chartRange = 30"
              >
                최근 30일
              </button>
            </div>
          </div>

          <div class="app-dialog-subcard d-flex flex-column ga-3">
            <div
              v-if="chartDays.length"
              class="inventory-dialog__chart-scroll"
              tabindex="0"
              aria-label="날짜별 폐기율 차트. 좌우로 스크롤할 수 있습니다."
            >
              <div
                class="inventory-dialog__chart"
                :class="{
                  'inventory-dialog__chart--monthly':
                    chartRange === 30,
                }"
              >
                <button
                  v-for="day in chartDays"
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
                      :style="{
                        height: barHeight(day.waste_rate),
                      }"
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
            </div>

            <p v-else class="inventory-dialog__empty">
              표시할 날짜별 기록이 없습니다.
            </p>

            <div
              v-if="selectedDay"
              class="inventory-dialog__day-detail d-flex flex-column ga-2"
            >
              <strong>{{ selectedDay.date }}</strong>

              <div class="d-flex flex-wrap ga-3">
                <span>
                  생산
                  {{
                    selectedDay.production === null
                      ? "미확인"
                      : `${formatQuantity(selectedDay.production)}개`
                  }}
                </span>

                <span>
                  폐기
                  {{ formatQuantity(selectedDay.attributed_waste) }}개
                </span>

                <span>
                  폐기율
                  {{ formatRate(selectedDay.waste_rate) }}
                </span>
              </div>
            </div>
          </div>

          <p class="inventory-dialog__form-help">
            막대를 누르면 해당 날짜의 수량을 확인할 수 있습니다.
            생산량이 0개이거나 미확인인 날짜는 폐기율이 표시되지 않습니다.
          </p>
        </div>

        <!-- 폐기 사유 -->
        <div
          v-else-if="activeTab === 'reasons'"
          class="d-flex flex-column ga-3"
          role="tabpanel"
        >
          <div class="d-flex align-center justify-space-between flex-wrap ga-2">
            <strong class="inventory-dialog__section-title">
              폐기 사유별 구성비
            </strong>

            <div class="inventory-dialog__range-switch d-flex ga-1">
              <button
                type="button"
                :class="{ 'is-active': reasonRange === 7 }"
                :aria-pressed="reasonRange === 7"
                @click="reasonRange = 7"
              >
                최근 7일
              </button>

              <button
                type="button"
                :class="{ 'is-active': reasonRange === 30 }"
                :aria-pressed="reasonRange === 30"
                @click="reasonRange = 30"
              >
                최근 30일
              </button>
            </div>
          </div>

          <div class="app-dialog-subcard">
            <div
              v-if="reasonSegments.length"
              class="inventory-dialog__reason-layout"
            >
              <div
                class="inventory-dialog__donut"
                :style="{ background: donutBackground }"
                role="img"
                :aria-label="`총 폐기 수량 ${formatQuantity(totalReasonQuantity)}개`"
              >
                <div class="inventory-dialog__donut-center">
                  <strong>
                    {{ formatQuantity(totalReasonQuantity) }}
                  </strong>

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

            <p v-else class="inventory-dialog__empty">
              해당 기간에 등록된 폐기 사유가 없습니다.
            </p>
          </div>

          <p class="inventory-dialog__form-help">
            선택한 기간에 생산된 제품의 폐기 사유별 수량 비율입니다.
          </p>
        </div>

        <!-- 날짜별 기록 -->
        <div
          v-else-if="activeTab === 'records'"
          class="d-flex flex-column ga-3"
          role="tabpanel"
        >
          <div class="inventory-dialog__heading">
            <strong class="inventory-dialog__section-title">
              최근 30일 날짜별 기록
            </strong>
          </div>

          <div
            v-if="daily30.length"
            class="inventory-dialog__records"
            tabindex="0"
            aria-label="최근 30일 날짜별 폐기율 기록"
          >
            <div
              v-for="day in reversedDaily30"
              :key="day.date"
              class="inventory-dialog__record app-dialog-subcard"
            >
              <div class="d-flex align-center justify-space-between ga-2">
                <strong>{{ day.date }}</strong>

                <strong class="inventory-dialog__record-rate">
                  {{ formatRate(day.waste_rate) }}
                </strong>
              </div>

              <div class="d-flex flex-wrap ga-3">
                <span>
                  생산
                  {{
                    day.production === null
                      ? "미확인"
                      : `${formatQuantity(day.production)}개`
                  }}
                </span>

                <span>
                  폐기 {{ formatQuantity(day.attributed_waste) }}개
                </span>
              </div>
            </div>
          </div>

          <p v-else class="inventory-dialog__empty">
            표시할 날짜별 기록이 없습니다.
          </p>
        </div>
      </section>

      <!-- 폐기율 안내 -->
      <details class="inventory-dialog__guide">
        <summary class="inventory-dialog__guide-summary">
          폐기율 계산 기준
        </summary>

        <div class="inventory-dialog__guide-content d-flex flex-column ga-2">
          <p class="inventory-dialog__form-help">
            폐기율은 최초 생산일 기준으로 계산합니다.
            이월 재고를 나중에 폐기해도 최초 생산일에 반영됩니다.
          </p>

          <p class="inventory-dialog__form-help">
            표시된 폐기율은 조회 시점 기준이며,
            이후 폐기 기록의 추가·수정에 따라 변경될 수 있습니다.
          </p>

          <div class="inventory-dialog__formula">
            <span>계산식</span>
            <strong>폐기 수량 ÷ 생산 수량 × 100</strong>
          </div>

          <p class="inventory-dialog__example">
            <strong>예시</strong>
            10/3 생산 10개 중 2개를 10/5에 폐기
            → 10/3 폐기율 20%
          </p>
        </div>
      </details>
    </template>
  </div>
</template>

<script setup>
import {
  computed,
  onBeforeUnmount,
  shallowRef,
  ref,
  watch,
} from "vue";

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

// API 조회
const statistics = shallowRef(null);
const loading = ref(false);
const error = ref("");
const selectedDate = ref(null);

let controller = null;
let requestId = 0;

// 내부 탭
const activeTab = ref("trend");

const analysisTabs = [
  { value: "trend", label: "폐기율 추이" },
  { value: "reasons", label: "폐기 사유" },
  { value: "records", label: "날짜별 기록" },
];

// 조회 기간 선택
const chartRange = ref(7);
const reasonRange = ref(7);

// 폐기 사유 명칭
const reasonLabelMap = computed(
  () =>
    new Map(
      props.reasonOptions.map((option) => [
        option.value,
        option.title,
      ])
    )
);

// 기존 차트 색상 유지
const chartColors = [
  "#5278bd",
  "#e4a148",
  "#60a58a",
  "#a28bc8",
  "#d67f8a",
  "#8a9aaa",
];

function formatQuantity(value) {
  return Number(value ?? 0).toLocaleString("ko-KR");
}

function formatRate(value) {
  if (value === null || value === undefined) {
    return "-";
  }

  return `${Number(value).toFixed(1)}%`;
}

function formatShortDate(value) {
  if (!value) return "-";

  return value.slice(5).replace("-", "/");
}

// 서버 조회
async function loadStatistics() {
  const currentRequest = ++requestId;

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

    if (currentRequest !== requestId) return;

    statistics.value = response.data;

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

// 제품 또는 업무 날짜 변경 시 재조회
watch(
  () => [props.product?.id, props.workDate],
  loadStatistics,
  { immediate: true }
);

// 요청 정리
onBeforeUnmount(() => {
  requestId++;
  controller?.abort();
  controller = null;
});

// 날짜 처리
function parseWorkDate(value) {
  const [year, month, day] = value.split("-").map(Number);
  return new Date(year, month - 1, day);
}

function shiftDate(value, days) {
  if (!value) return "";

  const date = parseWorkDate(value);
  date.setDate(date.getDate() + days);

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

// 7일 기간
const recentPeriod = computed(() => {
  if (!props.workDate) return "";

  return `${shiftDate(props.workDate, -6)} ~ ${props.workDate}`;
});

const previousPeriod = computed(() => {
  if (!props.workDate) return "";

  return `${shiftDate(props.workDate, -13)} ~ ${shiftDate(props.workDate, -7)}`;
});

// 30일 기간
const recent30Period = computed(() => {
  if (!props.workDate) return "";

  return `${shiftDate(props.workDate, -29)} ~ ${props.workDate}`;
});

const previous30Period = computed(() => {
  if (!props.workDate) return "";

  return `${shiftDate(props.workDate, -59)} ~ ${shiftDate(props.workDate, -30)}`;
});

// 기존 API 데이터
const recent = computed(() => statistics.value?.recent ?? null);
const previous = computed(() => statistics.value?.previous ?? null);

const daily = computed(() => statistics.value?.daily ?? []);

// 신규 30일 API 데이터
const recent30 = computed(
  () => statistics.value?.recent_30 ?? null
);

const previous30 = computed(
  () => statistics.value?.previous_30 ?? null
);

const daily30 = computed(
  () => statistics.value?.daily_30 ?? []
);

const reversedDaily30 = computed(
  () => [...daily30.value].reverse()
);

// 비교 가능 여부
function canCompare(current, past) {
  return (
    current?.waste_rate != null &&
    past?.waste_rate != null &&
    Number.isFinite(Number(current.waste_rate)) &&
    Number.isFinite(Number(past.waste_rate))
  );
}

const comparisonAvailable = computed(() =>
  canCompare(recent.value, previous.value)
);

const comparison30Available = computed(() =>
  canCompare(recent30.value, previous30.value)
);

// 기존 7일 변화
const difference = computed(() => {
  const value = statistics.value?.difference_percentage_points;

  return value === null || value === undefined
    ? null
    : Number(value);
});

// 신규 30일 변화
const difference30 = computed(() => {
  const value =
    statistics.value?.difference_30_percentage_points;

  return value === null || value === undefined
    ? null
    : Number(value);
});

// 퍼센트포인트 표시
function formatDifference(value) {
  if (value === null || !Number.isFinite(value)) {
    return "비교 불가";
  }

  if (value === 0) {
    return "변동 없음";
  }

  const sign = value > 0 ? "+" : "";

  return `${sign}${value.toFixed(1)}%p`;
}

function differenceDescription(value) {
  if (value === null || !Number.isFinite(value)) {
    return "비교할 생산 기록이 없습니다.";
  }

  if (value < 0) {
    return "이전 기간보다 폐기율 감소";
  }

  if (value > 0) {
    return "이전 기간보다 폐기율 증가";
  }

  return "이전 기간과 동일";
}

function differenceClass(value) {
  return {
    "inventory-dialog__difference--good":
      Number.isFinite(value) && value < 0,

    "inventory-dialog__difference--bad":
      Number.isFinite(value) && value > 0,
  };
}

function comparisonUnavailableText(current, past) {
  if (!current || !past) {
    return "비교할 기록이 없습니다.";
  }

  if (!current.complete || !past.complete) {
    return "생산 기록이 확인되지 않은 날짜가 있어 폐기율을 비교할 수 없습니다.";
  }

  if (
    current.waste_rate == null ||
    past.waste_rate == null
  ) {
    return "비교 기간의 생산량이 0개여서 폐기율을 비교할 수 없습니다.";
  }

  return "";
}

// 차트 조회 기간
const chartDays = computed(() =>
  chartRange.value === 30
    ? daily30.value
    : daily.value
);

// 기간 변경 시 선택 날짜 조정
watch(chartRange, () => {
  selectedDate.value =
    chartDays.value.at(-1)?.date ?? null;
});

// 그래프 최대 폐기율
const maxRate = computed(() => {
  const rates = chartDays.value
    .map((day) => day.waste_rate)
    .filter((rate) => rate !== null && rate !== undefined)
    .map(Number)
    .filter(Number.isFinite);

  return Math.max(10, ...rates);
});

// 막대 높이
function barHeight(value) {
  if (value === null || value === undefined) {
    return "0%";
  }

  const rate = Number(value);

  if (!Number.isFinite(rate) || rate < 0) {
    return "0%";
  }

  if (rate === 0) {
    return "3px";
  }

  return `${Math.max(
    3,
    Math.min(100, (rate / maxRate.value) * 100)
  )}%`;
}

// 선택한 날짜
const selectedDay = computed(() =>
  chartDays.value.find(
    (day) => day.date === selectedDate.value
  ) ?? null
);

// 폐기 사유별 데이터
const reasonRows = computed(() => {
  return reasonRange.value === 30
    ? statistics.value?.reasons_30 ?? []
    : statistics.value?.reasons ?? [];
});

const reasons = computed(() => {
  return reasonRows.value
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

const totalReasonQuantity = computed(() =>
  reasons.value.reduce(
    (total, row) => total + row.quantity,
    0
  )
);

// 도넛그래프 구성
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

/* 주요 카드 */
.inventory-dialog__card,
.inventory-dialog__guide {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  padding: 14px 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  background: transparent;
}

/* 제목 */
.inventory-dialog__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px;
}

.inventory-dialog__heading strong {
  font-size: 13px;
  font-weight: 600;
}

.inventory-dialog__period-title,
.inventory-dialog__section-title {
  font-size: 12px;
  font-weight: 600;
}

.inventory-dialog__period-caption {
  font-size: 11px;
  opacity: 0.7;
}

/* 7일·30일 비교 */
.inventory-dialog__comparison {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  min-width: 0;
}

.inventory-dialog__metric {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  padding: 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
}

.inventory-dialog__metric-label {
  font-size: 11px;
  opacity: 0.7;
}

.inventory-dialog__metric-period {
  font-size: 10px;
  line-height: 1.4;
  opacity: 0.65;
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
}

/* 폐기율 변화 */
.inventory-dialog__difference {
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

/* 상세 분석 내부 탭 */
.inventory-dialog__tabs {
  width: 100%;
  min-width: 0;
  padding: 4px;
  border-radius: 9px;
  background: rgba(var(--v-theme-on-surface), 0.05);
}

.inventory-dialog__tab {
  flex: 1 1 0;
  min-width: 0;
  min-height: 40px;
  padding: 8px 5px;
  border: none;
  border-radius: 7px;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.4;
  cursor: pointer;
  touch-action: manipulation;
}

.inventory-dialog__tab--active {
  background: rgb(var(--v-theme-surface));
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.inventory-dialog__tab:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

/* 7일·30일 전환 */
.inventory-dialog__range-switch {
  min-width: 0;
}

.inventory-dialog__range-switch button {
  min-height: 34px;
  padding: 6px 10px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 7px;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 11px;
  cursor: pointer;
  touch-action: manipulation;
}

.inventory-dialog__range-switch button.is-active {
  border-color: rgba(var(--v-theme-primary), 0.45);
  background: rgba(var(--v-theme-primary), 0.08);
  font-weight: 600;
}

.inventory-dialog__range-switch button:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 날짜별 차트 */
.inventory-dialog__chart-scroll {
  width: 100%;
  min-width: 0;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  scrollbar-width: thin;
  -webkit-overflow-scrolling: touch;
}

.inventory-dialog__chart {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 7px;
  width: 100%;
  min-width: 0;
  height: 154px;
}

.inventory-dialog__chart--monthly {
  grid-template-columns: repeat(30, minmax(28px, 1fr));
  min-width: 900px;
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

/* 선택 날짜 */
.inventory-dialog__day-detail {
  min-width: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  font-size: 11px;
}

.inventory-dialog__day-detail strong {
  font-weight: 600;
}

/* 폐기 사유 */
.inventory-dialog__reason-layout {
  display: flex;
  align-items: center;
  gap: 20px;
  width: 100%;
  min-width: 0;
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
  min-width: 0;
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

/* 날짜별 기록 */
.inventory-dialog__records {
  display: flex;
  flex-direction: column;
  gap: 7px;
  width: 100%;
  min-width: 0;
  max-height: 280px;
  overflow-y: auto;
  overscroll-behavior-y: contain;
  padding-right: 2px;
  scrollbar-width: thin;
}

.inventory-dialog__record {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  font-size: 11px;
}

.inventory-dialog__record strong {
  font-weight: 600;
}

.inventory-dialog__record-rate {
  white-space: nowrap;
}

/* 빈 상태 */
.inventory-dialog__empty {
  margin: 0;
  padding: 18px 0;
  font-size: 11px;
  line-height: 1.5;
  text-align: center;
  opacity: 0.7;
}

/* 로딩·오류 */
.inventory-dialog__status {
  width: 100%;
  min-width: 0;
  min-height: 90px;
  font-size: 11px;
  line-height: 1.6;
  text-align: center;
}

.inventory-dialog__retry-button {
  min-height: 40px;
}

/* 안내 */
.inventory-dialog__guide {
  gap: 0;
}

.inventory-dialog__guide-summary {
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  touch-action: manipulation;
}

.inventory-dialog__guide-content {
  margin-top: 12px;
}

.inventory-dialog__form-help {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

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

.inventory-dialog__example {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
}

.inventory-dialog__example strong {
  margin-right: 6px;
  font-weight: 600;
}

/* 모바일 */
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

/* 320px 대응 */
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

  .inventory-dialog__tab {
    padding-inline: 3px;
  }
}
</style>