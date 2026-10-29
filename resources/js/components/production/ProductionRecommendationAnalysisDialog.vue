<template>
  <AppDialogLayout
    v-model="dialogOpen"
    :title="`${productName || '제품'} - 분석과 추천 이유`"
    :max-width="620"
    :loading="loading"
    loading-text="불러오는 중..."
    :error="error"
    :retryable="Boolean(error)"
    :empty="!recommendation"
    empty-text="분석할 기록이 없습니다."
    @retry="emit('retry')"
  >
    <div class="recommendation-analysis d-flex flex-column ga-3">
      <!-- 분석 기간 -->
      <section
        v-if="details?.period"
        class="d-flex flex-column ga-2"
      >
        <span class="app-form-section-title">
          분석 기간
        </span>

        <div class="recommendation-analysis__rows">
          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              기간
            </span>

            <span class="recommendation-analysis__value">
              {{ details.period.from }}
              ~
              {{ details.period.to }}
            </span>
          </div>

          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              분석한 영업일
            </span>

            <span class="recommendation-analysis__value">
              {{ formatCount(totals?.valid_days) }}일치
            </span>
          </div>
        </div>

        <p class="recommendation-analysis__note">
          최근 최대 12개월의 영업 기록입니다.
        </p>
      </section>

      <v-divider v-if="details?.period" />

      <!-- 추천 생산량 -->
      <section class="d-flex flex-column ga-2">
        <span class="app-form-section-title">
          추천 생산량
        </span>

        <div class="d-flex align-center justify-space-between ga-3">
          <div class="d-flex flex-column ga-1">
            <span class="recommendation-analysis__main-quantity">
              {{ formatQuantity(recommendation?.center) }}
            </span>

            <span class="recommendation-analysis__note">
              추천 범위
              {{ formatQuantity(recommendation?.min) }}
              ~
              {{ formatQuantity(recommendation?.max) }}
            </span>
          </div>

          <v-chip
            size="small"
            variant="tonal"
            :color="confidenceColor"
          >
            {{ confidenceText }}
          </v-chip>
        </div>
      </section>

      <v-divider />

      <!-- 추천 이유 -->
      <section class="d-flex flex-column ga-2">
        <span class="app-form-section-title">
          이 수량을 추천하는 이유
        </span>

        <p
          v-for="(reason, index) in reasons"
          :key="index"
          class="recommendation-analysis__reason"
        >
          {{ reason }}
        </p>

        <p
          v-if="!reasons.length"
          class="recommendation-analysis__note"
        >
          추천 이유를 확인할 수 없습니다.
        </p>
      </section>

      <v-divider />

      <!-- 실제 참고한 판매 기록 -->
      <section
        v-if="referenceRecords.length > 0"
        class="d-flex flex-column ga-2"
      >
        <span class="app-form-section-title">
          참고한 판매 기록
        </span>

        <div class="recommendation-analysis__rows">
          <div
            v-for="record in referenceRecords"
            :key="record.date"
            class="recommendation-analysis__row"
          >
            <span class="recommendation-analysis__label">
              {{ record.label }}
            </span>

            <span class="recommendation-analysis__value">
              {{ record.quantity }}
            </span>
          </div>
        </div>

        <p class="recommendation-analysis__note">
          표시된 수량은 추정 판매량입니다.
        </p>
      </section>

      <v-divider v-if="referenceRecords.length > 0" />

      <!-- 추천 생산량 상세 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          추천 생산량 상세
        </span>

        <div class="recommendation-analysis__rows">
          <div
            v-for="row in calculationRows"
            :key="row.label"
            class="recommendation-analysis__row"
          >
            <span class="recommendation-analysis__label">
              {{ row.label }}
            </span>

            <span
              class="recommendation-analysis__value"
              :class="row.highlight ? 'text-primary font-weight-medium' : ''"
            >
              {{ row.value }}
            </span>
          </div>
        </div>

        <v-divider />

        <!-- 참고한 기록 -->
        <span class="app-form-section-title">
          참고한 기록
        </span>

        <div class="recommendation-analysis__rows">
          <div
            v-for="row in historyRows"
            :key="row.label"
            class="recommendation-analysis__row"
          >
            <span class="recommendation-analysis__label">
              {{ row.label }}
            </span>

            <span class="recommendation-analysis__value">
              {{ row.value }}
            </span>
          </div>
        </div>

        <v-divider />

        <!-- 기간별 추정 판매량 -->
        <span class="app-form-section-title">
          기간별 추정 판매량
        </span>

        <div class="recommendation-analysis__rows">
          <div
            v-for="row in periodRows"
            :key="row.label"
            class="recommendation-analysis__row"
          >
            <span class="recommendation-analysis__label">
              {{ row.label }}
            </span>

            <span class="recommendation-analysis__value">
              {{ row.value }}
            </span>
          </div>
        </div>

        <v-alert
          v-if="history && history.same_weekday_count < 2"
          type="info"
          variant="tonal"
          density="comfortable"
          class="app-supporting-alert"
        >
          같은 요일의 기록이 부족해
          다른 영업일도 참고했습니다.
        </v-alert>

        <v-alert
          v-if="history && history.excluded_record_count > 0"
          type="warning"
          variant="tonal"
          density="comfortable"
          class="app-supporting-alert"
        >
          확인이 필요한 기록은 분석에서 제외했습니다.
        </v-alert>
      </section>

      <v-divider />

      <!-- 월별 추정 판매량 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          월별 추정 판매량
        </span>

        <p class="recommendation-analysis__note">
          월별 하루 평균 추정 판매량입니다.
        </p>

        <div
          v-if="monthly.length"
          class="d-flex flex-column ga-3"
        >
          <div
            v-for="month in monthly"
            :key="month.month"
            class="recommendation-analysis__chart-item d-flex flex-column ga-2"
          >
            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                {{ month.month }}
              </span>

              <span class="recommendation-analysis__value">
                {{ formatNumber(month.average_sales) }}개
              </span>
            </div>

            <v-progress-linear
              :model-value="
                barPercent(
                  month.average_sales,
                  maxMonthlySales
                )
              "
              color="primary"
              height="8"
              rounded
            />

            <span class="recommendation-analysis__note">
              {{ formatCount(month.record_count) }}일치 기록
            </span>
          </div>
        </div>

        <p
          v-else
          class="recommendation-analysis__note"
        >
          월별 기록이 없습니다.
        </p>
      </section>

      <v-divider />

      <!-- 최근 추정 판매량 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          최근 추정 판매량
        </span>

        <p class="recommendation-analysis__note">
          날짜를 선택하면 상세 기록을 볼 수 있습니다.
        </p>

        <div
          v-if="recentDaily.length"
          class="d-flex flex-column ga-3"
        >
          <!-- 날짜 선택 -->
          <div class="recommendation-analysis__day-grid">
            <button
              v-for="day in recentDaily"
              :key="day.date"
              type="button"
              class="recommendation-analysis__day-tile"
              :class="{
                'recommendation-analysis__day-tile--active':
                  selectedDailyRecord?.date === day.date,
              }"
              :aria-pressed="
                selectedDailyRecord?.date === day.date
              "
              :aria-label="
                `${day.date}, 추정 판매량 ${formatQuantity(day.estimated_sales)}`
              "
              @click="selectedDailyDate = day.date"
            >
              <span class="recommendation-analysis__day-tile-date">
                {{ day.date.slice(5) }}
              </span>

              <span class="recommendation-analysis__day-tile-quantity">
                {{ formatQuantity(day.estimated_sales) }}
              </span>

              <span class="recommendation-analysis__mini-bar">
                <span
                  class="recommendation-analysis__mini-fill"
                  :style="{
                    width: `${
                      barPercent(
                        day.estimated_sales,
                        maxRecentSales
                      )
                    }%`,
                  }"
                />
              </span>
            </button>
          </div>

          <p class="recommendation-analysis__note">
            막대가 길수록 추정 판매량이 많습니다.
          </p>

          <v-divider />

          <!-- 선택 날짜 상세 -->
          <div
            v-if="selectedDailyRecord"
            class="d-flex flex-column ga-3"
          >
            <span class="app-form-section-title">
              {{ selectedDailyRecord.date }} 생산·판매 기록
            </span>

            <span
              v-if="selectedDailyRecord.special_day"
              class="recommendation-analysis__note"
            >
              행사·특별 영업일
            </span>

            <div
              v-for="row in dailyDetailRows"
              :key="row.label"
              class="d-flex flex-column ga-1"
            >
              <div class="recommendation-analysis__row">
                <span class="recommendation-analysis__label">
                  {{ row.label }}
                </span>

                <span class="recommendation-analysis__value">
                  {{ formatQuantity(row.quantity) }}
                </span>
              </div>

              <v-progress-linear
                :model-value="
                  barPercent(
                    row.quantity,
                    dailyAvailable
                  )
                "
                :color="row.color"
                height="8"
                rounded
              />
            </div>

            <p class="recommendation-analysis__note">
              막대는 생산량과 이월재고의 합계를 기준으로 합니다.
            </p>
          </div>
        </div>

        <p
          v-else
          class="recommendation-analysis__note"
        >
          최근 기록이 없습니다.
        </p>
      </section>

      <v-divider />

      <!-- 요일별 추정 판매량 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          요일별 추정 판매량
        </span>

        <p class="recommendation-analysis__note">
          행사일을 제외한 하루 평균 추정 판매량입니다.
        </p>

        <div class="d-flex flex-column ga-3">
          <div
            v-for="day in weekdays"
            :key="day.weekday"
            class="recommendation-analysis__chart-item d-flex flex-column ga-2"
          >
            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                {{ day.name }}
              </span>

              <span class="recommendation-analysis__value">
                {{
                  day.average_sales == null
                    ? '기록 없음'
                    : `${formatNumber(day.average_sales)}개`
                }}
              </span>
            </div>

            <v-progress-linear
              :model-value="
                barPercent(
                  day.average_sales,
                  maxWeekdaySales
                )
              "
              color="primary"
              height="8"
              rounded
            />

            <span class="recommendation-analysis__note">
              {{ formatCount(day.record_count) }}일치 기록
            </span>
          </div>
        </div>
      </section>

      <v-divider />

      <!-- 생산·판매·폐기 현황 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          생산·판매·폐기 현황
        </span>

        <div class="recommendation-analysis__rows">
          <div
            v-for="row in totalRows"
            :key="row.label"
            class="recommendation-analysis__row"
          >
            <span class="recommendation-analysis__label">
              {{ row.label }}
            </span>

            <span class="recommendation-analysis__value">
              {{ row.value }}
            </span>
          </div>
        </div>

        <v-divider />

        <div class="d-flex flex-column ga-2">
          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              폐기·이월예정 비율
            </span>

            <span class="recommendation-analysis__value">
              {{ formatPercent(totals?.remaining_rate) }}
            </span>
          </div>

          <v-progress-linear
            :model-value="rateBar(totals?.remaining_rate)"
            color="secondary"
            height="8"
            rounded
          />
        </div>

        <v-divider />

        <span class="app-form-section-title">
          수량별 사용 현황
        </span>

        <div class="d-flex flex-column ga-3">
          <div
            v-for="row in inventoryRateRows"
            :key="row.label"
            class="d-flex flex-column ga-2"
          >
            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                {{ row.label }}
              </span>

              <span class="recommendation-analysis__value">
                {{ formatQuantity(row.quantity) }}
                ·
                {{ formatPercent(inventoryRate(row.quantity)) }}
              </span>
            </div>

            <v-progress-linear
              :model-value="
                rateBar(inventoryRate(row.quantity))
              "
              :color="row.color"
              height="8"
              rounded
            />
          </div>
        </div>

        <p class="recommendation-analysis__note">
          생산량과 이월재고를 합친 수량 중
          폐기·이월예정의 비율입니다.
          생산일 기준 폐기율과는 다릅니다.
        </p>
      </section>

      <v-divider />

      <!-- 완판과 판매 기회 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          완판과 판매 기회
        </span>

        <div class="recommendation-analysis__rows">
          <div
            v-for="row in inventoryRows"
            :key="row.label"
            class="recommendation-analysis__row"
          >
            <span class="recommendation-analysis__label">
              {{ row.label }}
            </span>

            <span class="recommendation-analysis__value">
              {{ row.value }}
            </span>
          </div>
        </div>

        <p class="recommendation-analysis__note">
          재고가 모두 소진된 날은
          더 팔 수 있었을 가능성이 있습니다.
        </p>
      </section>

      <v-divider />

      <!-- 최근 판매 변화 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          최근 판매 변화
        </span>

        <p class="recommendation-analysis__note">
          {{ trendText }}
        </p>

        <div
          v-if="trend?.recent_trend"
          class="d-flex flex-column ga-2"
        >
          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              같은 요일 추정 판매량 변화
            </span>

            <span
              class="recommendation-analysis__value font-weight-medium"
              :class="
                trend.recent_trend.direction === 'increasing'
                  ? 'text-success'
                  : 'text-error'
              "
            >
              {{
                trend.recent_trend.direction === 'increasing'
                  ? '증가'
                  : '감소'
              }}
              {{
                formatPercent(
                  Math.abs(trend.recent_trend.change_rate)
                )
              }}
            </span>
          </div>

          <span class="recommendation-analysis__note">
            같은 요일 {{ formatCount(trend.recent_trend.sample_count) }}일치 기록을 비교했습니다.
          </span>
        </div>
      </section>

      <v-divider />

      <!-- 과거 기록을 기준으로 계산한 추천 수량의 정확도 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          추천 수량 정확도
        </span>

        <div class="recommendation-analysis__rows">
          <div
            v-for="row in accuracyRows"
            :key="row.label"
            class="recommendation-analysis__row"
          >
            <span class="recommendation-analysis__label">
              {{ row.label }}
            </span>

            <span class="recommendation-analysis__value">
              {{ row.value }}
            </span>
          </div>
        </div>

        <p class="recommendation-analysis__note">
          지난 영업일의 기록으로 계산한 추천 수량과
          추정 판매량의 차이입니다.
        </p>

        <v-divider />

        <span class="app-form-section-title">
          저장된 추천량과 마감 결과
        </span>

        <v-alert
          v-if="!comparisonRecords.length"
          type="info"
          variant="tonal"
          density="comfortable"
          class="app-supporting-alert"
        >
          아직 비교할 기록이 없습니다.
        </v-alert>

        <template v-else>
          <!-- 날짜 이동 -->
          <div class="d-flex align-center justify-space-between ga-2">
            <v-btn
              variant="outlined"
              color="primary"
              size="small"
              min-height="44"
              :disabled="selectedComparisonIndex <= 0"
              @click="moveComparisonDate(-1)"
            >
              이전
            </v-btn>

            <span class="text-body-2 text-center">
              {{ selectedComparisonDateLabel }}
            </span>

            <v-btn
              variant="outlined"
              color="primary"
              size="small"
              min-height="44"
              :disabled="
                selectedComparisonIndex >= comparisonRecords.length - 1
              "
              @click="moveComparisonDate(1)"
            >
              다음
            </v-btn>
          </div>

          <p class="recommendation-analysis__note text-center">
            {{ selectedComparisonIndex + 1 }}
            /
            {{ comparisonRecords.length }}
          </p>

          <!-- 선택한 날짜의 생산 결과 -->
          <div
            v-if="selectedComparisonRecord"
            class="d-flex flex-column ga-3"
          >
            <div
              v-for="item in comparisonChartRows"
              :key="item.label"
              class="d-flex flex-column ga-1"
            >
              <div class="recommendation-analysis__row">
                <span class="recommendation-analysis__label">
                  {{ item.label }}
                </span>

                <span class="recommendation-analysis__value">
                  {{ formatQuantity(item.value) }}
                </span>
              </div>

              <v-progress-linear
                :model-value="item.percent"
                :color="item.color"
                bg-color="surface-variant"
                rounded
                height="9"
              />
            </div>

            <p class="recommendation-analysis__note">
              {{ comparisonResultText }}
            </p>
          </div>

          <p class="recommendation-analysis__note">
            추정 판매량은 실제 판매 기록이 아닌 계산된 수량입니다.
          </p>
        </template>
      </section>

      <v-divider />

      <!-- 확인 사항 -->
      <section
        v-if="warnings.length"
        class="d-flex flex-column ga-2"
      >
        <span class="app-form-section-title">
          확인 사항
        </span>

        <v-alert
          v-for="(warning, index) in warnings"
          :key="index"
          type="warning"
          variant="tonal"
          density="comfortable"
          class="app-supporting-alert"
        >
          {{ warning }}
        </v-alert>
      </section>
    </div>

    <template #footer>
      <AppDialogFooter
        :button-count="1"
        :show-confirm="false"
        cancel-text="닫기"
        @cancel="dialogOpen = false"
      />
    </template>
  </AppDialogLayout>
</template>

<script setup>
import {
  computed,
  ref,
  watch,
} from "vue";

import AppDialogLayout from "../common/AppDialogLayout.vue";
import AppDialogFooter from "../common/AppDialogFooter.vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  productName: {
    type: String,
    default: "",
  },

  data: {
    type: Object,
    default: null,
  },

  loading: {
    type: Boolean,
    default: false,
  },

  error: {
    type: String,
    default: "",
  },
});

const emit = defineEmits([
  "update:modelValue",
  "retry",
]);

const dialogOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

/* 서버 응답 */
const recommendation = computed(
  () => props.data?.recommendation ?? null
);

const details = computed(
  () => props.data?.analysis_details ?? null
);

const explanation = computed(
  () => recommendation.value?.explanation ?? null
);

const calculation = computed(
  () => explanation.value?.calculation ?? null
);

const history = computed(
  () => explanation.value?.history ?? null
);

/*
 * 추천 생산량 계산에 사용한 실제 참고 기록입니다.
 *
 * 백엔드에서 전달한 날짜·요일·추정 판매량을
 * 그대로 표시합니다.
 *
 * 프론트엔드에서 임의로 날짜를 선택하거나
 * 추천량을 다시 계산하지 않습니다.
 */
const referenceRecords = computed(() => {
  const records = history.value?.reference_records;

  if (!Array.isArray(records)) {
    return [];
  }

  return records
    .filter((record) => {
      return (
        record &&
        record.date_label &&
        record.weekday_label &&
        record.estimated_sales !== null &&
        record.estimated_sales !== undefined
      );
    })
    .map((record) => {
      const quantity = Number(record.estimated_sales);

      return {
        date: record.date,
        label: `${record.date_label}(${record.weekday_label})`,
        quantity: Number.isFinite(quantity)
          ? `${quantity.toLocaleString("ko-KR")}개`
          : "-",
      };
    });
});

const inventory = computed(
  () => explanation.value?.inventory ?? null
);

const trend = computed(
  () => explanation.value?.trend ?? null
);

const accuracy = computed(
  () => explanation.value?.accuracy ?? null
);

const totals = computed(
  () => details.value?.totals ?? null
);

const monthly = computed(
  () => details.value?.monthly ?? []
);

const weekdays = computed(
  () => details.value?.weekdays ?? []
);

const recentDaily = computed(
  () => details.value?.recent_daily ?? []
);

/* 날짜 선택 */
const selectedDailyDate = ref(null);

watch(
  () => props.data,
  () => {
    selectedDailyDate.value = null;
  }
);

const selectedDailyRecord = computed(() => {
  const rows = recentDaily.value;

  if (!rows.length) {
    return null;
  }

  const selected = rows.find(
    (row) => row.date === selectedDailyDate.value
  );

  return selected ?? rows[rows.length - 1];
});

/* 날짜별 그래프 계산 기준 */
const maxRecentSales = computed(() =>
  Math.max(
    1,
    ...recentDaily.value.map(
      (item) => Number(item.estimated_sales) || 0
    )
  )
);

const dailyAvailable = computed(() =>
  Math.max(
    1,
    Number(selectedDailyRecord.value?.available) || 0
  )
);

/* 선택 날짜 상세 항목 */
const dailyDetailRows = computed(() => {
  const day = selectedDailyRecord.value;

  if (!day) {
    return [];
  }

  return [
    {
      label: "생산량",
      quantity: day.production,
      color: "success",
    },
    {
      label: "이월재고",
      quantity: day.carryover_in,
      color: "secondary",
    },
    {
      label: "추정 판매량",
      quantity: day.estimated_sales,
      color: "primary",
    },
    {
      label: "로스량",
      quantity: day.loss,
      color: "warning",
    },
    {
      label: "폐기량",
      quantity: day.waste,
      color: "error",
    },
    {
      label: "이월예정",
      quantity: day.carryover_out,
      color: "secondary",
    },
  ];
});

/* 추천 이유 */
const reasons = computed(
  () => explanation.value?.notices?.reasons
    ?? recommendation.value?.reasons
    ?? []
);

const warnings = computed(() => {
  const values = explanation.value?.notices?.warnings;

  if (Array.isArray(values)) {
    return values;
  }

  const message = recommendation.value?.warning;

  return message ? [message] : [];
});

/* 그래프 최대값 */
const maxMonthlySales = computed(() =>
  Math.max(
    1,
    ...monthly.value.map(
      (item) => Number(item.average_sales) || 0
    )
  )
);

const maxWeekdaySales = computed(() =>
  Math.max(
    1,
    ...weekdays.value.map(
      (item) => Number(item.average_sales) || 0
    )
  )
);

/* 신뢰도 */
const confidenceText = computed(() => {
  switch (recommendation.value?.confidence) {
    case "normal":
      return "보통 신뢰도";

    case "low":
      return "낮은 신뢰도";

    default:
      return "판단 자료 부족";
  }
});

const confidenceColor = computed(() =>
  recommendation.value?.confidence === "normal"
    ? "success"
    : undefined
);

/* 최근 판매 변화 */
const trendText = computed(() => {
  const value = trend.value?.recent_trend;

  if (!value) {
    return "최근 같은 요일의 판매 변화를 판단하기 어렵습니다.";
  }

  if (value.direction === "increasing") {
    return "최근 같은 요일의 추정 판매량이 늘었습니다.";
  }

  if (value.direction === "decreasing") {
    return "최근 같은 요일의 추정 판매량이 줄었습니다.";
  }

  return "최근 같은 요일의 추정 판매량은 비슷한 수준입니다.";
});

/* 수량 표시 */
function formatNumber(value) {
  if (
    value === null ||
    value === undefined ||
    value === "" ||
    !Number.isFinite(Number(value))
  ) {
    return "자료 없음";
  }

  return Number(value).toLocaleString("ko-KR", {
    maximumFractionDigits: 2,
  });
}

function formatQuantity(value) {
  const number = formatNumber(value);

  return number === "자료 없음"
    ? number
    : `${number}개`;
}

function formatCount(value) {
  if (
    value === null ||
    value === undefined ||
    !Number.isFinite(Number(value))
  ) {
    return 0;
  }

  return Number(value);
}

function formatPercent(value) {
  if (
    value === null ||
    value === undefined ||
    !Number.isFinite(Number(value))
  ) {
    return "자료 없음";
  }

  return `${(Number(value) * 100).toFixed(1)}%`;
}

/* 그래프 비율 */
function barPercent(value, maximum) {
  const number = Number(value);

  if (
    value === null ||
    value === undefined ||
    !Number.isFinite(number) ||
    maximum <= 0
  ) {
    return 0;
  }

  return Math.max(
    0,
    Math.min(100, number / maximum * 100)
  );
}

const availableTotal = computed(() => {
  if (!totals.value) {
    return null;
  }

  return (
    Number(totals.value.production || 0) +
    Number(totals.value.carryover_in || 0)
  );
});

function inventoryRate(quantity) {
  const total = availableTotal.value;

  if (
    total === null ||
    total <= 0 ||
    quantity === null ||
    quantity === undefined ||
    !Number.isFinite(Number(quantity))
  ) {
    return null;
  }

  return Number(quantity) / total;
}

function rateBar(value) {
  if (
    value === null ||
    value === undefined ||
    !Number.isFinite(Number(value))
  ) {
    return 0;
  }

  return Math.max(
    0,
    Math.min(100, Number(value) * 100)
  );
}

/* 추천 생산량 상세 */
const calculationRows = computed(() => [
  {
    label: "평소 예상 판매량",
    value: formatQuantity(calculation.value?.base_demand),
  },
  {
    label: "해당 날짜 예상 판매량",
    value: formatQuantity(
      calculation.value?.expected_demand_before_carryover
    ),
  },
  {
    label: "이월재고",
    value: formatQuantity(
      calculation.value?.target_carryover_in
    ),
  },
  {
    label: "추천 생산량",
    value: formatQuantity(recommendation.value?.center),
    highlight: true,
  },
]);

/* 참고한 기록 */
const historyRows = computed(() => [
  {
    label: "참고한 기록",
    value: `${formatCount(history.value?.valid_record_count)}일치`,
  },
  {
    label: "제외된 기록",
    value: `${formatCount(history.value?.excluded_record_count)}일치`,
  },
  {
    label: "같은 요일 기록",
    value: `${formatCount(history.value?.same_weekday_count)}일치`,
  },
  {
    label: "최근 3개월 비중",
    value: formatPercent(history.value?.recent_weight),
  },
  {
    label: "이전 9개월 비중",
    value: formatPercent(history.value?.older_weight),
  },
]);

/* 기간별 추정 판매량 */
const periodRows = computed(() => [
  {
    label: "최근 3개월 기록",
    value: `${formatCount(history.value?.recent_record_count)}일치`,
  },
  {
    label: "최근 3개월 평균 추정 판매량",
    value: formatQuantity(history.value?.recent_average),
  },
  {
    label: "이전 9개월 기록",
    value: `${formatCount(history.value?.older_record_count)}일치`,
  },
  {
    label: "이전 9개월 평균 추정 판매량",
    value: formatQuantity(history.value?.older_average),
  },
]);

/* 생산·판매·폐기 현황 */
const totalRows = computed(() => [
  {
    label: "생산량",
    value: formatQuantity(totals.value?.production),
  },
  {
    label: "이월재고",
    value: formatQuantity(totals.value?.carryover_in),
  },
  {
    label: "로스량",
    value: formatQuantity(totals.value?.loss),
  },
  {
    label: "폐기량",
    value: formatQuantity(totals.value?.waste),
  },
  {
    label: "이월예정",
    value: formatQuantity(totals.value?.carryover_out),
  },
  {
    label: "추정 판매량",
    value: formatQuantity(totals.value?.estimated_sales),
  },
]);

/* 생산·판매·폐기 비율 */
const inventoryRateRows = computed(() => [
  {
    label: "추정 판매량",
    quantity: totals.value?.estimated_sales,
    color: "primary",
  },
  {
    label: "로스량",
    quantity: totals.value?.loss,
    color: "warning",
  },
  {
    label: "폐기량",
    quantity: totals.value?.waste,
    color: "error",
  },
  {
    label: "이월예정",
    quantity: totals.value?.carryover_out,
    color: "secondary",
  },
]);

/* 완판과 판매 기회 */
const inventoryRows = computed(() => [
  {
    label: "폐기·이월예정이 없었던 날",
    value: `${formatCount(inventory.value?.zero_remaining_days)}일치`,
  },
  {
    label: "완판 가능성이 있었던 날",
    value: `${formatCount(inventory.value?.possible_sellout_days)}일치`,
  },
  {
    label: "폐기·이월예정 합계가 1개였던 날",
    value: `${formatCount(inventory.value?.one_remaining_days)}일치`,
  },
]);

/* 날짜별 생산 결과 */
const recommendationOutcomes = computed(
  () => props.data?.recommendation_outcomes ?? null
);

const adjustmentComparison = computed(
  () => recommendationOutcomes.value?.adjustment_comparison ?? null
);

/*
 * 날짜별 생산 결과 목록
 *
 * [목적]
 * 실제 날짜와 추천 생산량을 확인할 수 있는
 * 기록만 목록에 표시합니다.
 *
 * [개선]
 * - 수량이 없는 데이터를 0개로 판단하지 않습니다.
 * - 날짜가 없는 기록은 표시하지 않습니다.
 * - 날짜순으로 정렬합니다.
 * - 기존 날짜 이동 기능은 유지합니다.
 *
 * [주의]
 * 실제 생산량, 폐기량, 이월예정이 없는 경우에도
 * 기록 자체를 삭제하지 않습니다.
 *
 * 해당 항목은 화면에서 '자료 없음'으로 표시합니다.
 */
const comparisonRecords = computed(() => {
  const records = adjustmentComparison.value?.records;

  if (!Array.isArray(records)) {
    return [];
  }

  /*
   * 실제 숫자가 기록되어 있는지 확인합니다.
   *
   * null이나 빈 문자열은
   * 실제 0개 기록과 구분합니다.
   */
  const hasQuantity = (value) => {
    return (
      value !== null
      && value !== undefined
      && value !== ""
      && Number.isFinite(Number(value))
      && Number(value) >= 0
    );
  };

  return [...records]
    .filter((record) => {
      if (
        !record
        || typeof record.date !== "string"
        || !/^\d{4}-\d{2}-\d{2}$/.test(record.date)
      ) {
        return false;
      }

      /*
       * 날짜별 비교에 꼭 필요한
       * 추천 생산량과 추정 판매량만 확인합니다.
       */
      return (
        hasQuantity(record.adjusted_quantity)
        && hasQuantity(record.estimated_sales)
      );
    })
    .sort((a, b) => {
      return a.date.localeCompare(b.date);
    });
});

const selectedComparisonIndex = ref(0);

watch(
  () => props.data,
  () => {
    selectedComparisonIndex.value = 0;
  }
);

const selectedComparisonRecord = computed(() => {
  return comparisonRecords.value[
    selectedComparisonIndex.value
  ] ?? null;
});

/*
 * 선택한 날짜를 읽기 쉬운 형태로 표시
 *
 * 예: 2026-10-26 → 10월 26일(월)
 *
 * 날짜 문자열을 직접 분리해
 * 브라우저 시간대에 따른 날짜 변경을 방지합니다.
 */
const selectedComparisonDateLabel = computed(() => {
  const date = selectedComparisonRecord.value?.date;

  if (
    typeof date !== "string"
    || !/^\d{4}-\d{2}-\d{2}$/.test(date)
  ) {
    return "-";
  }

  const [year, month, day] = date
    .split("-")
    .map(Number);

  const parsed = new Date(
    Date.UTC(year, month - 1, day)
  );

  if (
    parsed.getUTCFullYear() !== year
    || parsed.getUTCMonth() !== month - 1
    || parsed.getUTCDate() !== day
  ) {
    return "-";
  }

  const weekdays = [
    "일", "월", "화", "수",
    "목", "금", "토",
  ];

  const weekday = weekdays[
    parsed.getUTCDay()
  ];

  return `${month}월 ${day}일(${weekday})`;
});

function moveComparisonDate(direction) {
  const next = selectedComparisonIndex.value + direction;

  if (
    next < 0
    || next >= comparisonRecords.value.length
  ) {
    return;
  }

  selectedComparisonIndex.value = next;
}

/*
 * 날짜별 생산 결과 그래프
 *
 * [목적]
 * 추천 생산량, 실제 생산량, 추정 판매량,
 * 폐기량, 이월예정을 비교합니다.
 *
 * [개선]
 * - 기록이 없는 수량은 0개로 표시하지 않습니다.
 * - 실제 0개로 기록된 경우에만 0개를 표시합니다.
 * - 수량이 없는 항목은 막대를 표시하지 않습니다.
 * - 기존 색상과 항목 순서를 유지합니다.
 */
const comparisonChartRows = computed(() => {
  const record = selectedComparisonRecord.value;

  if (!record) {
    return [];
  }

  /*
   * 기록이 없는 값과 실제 0개를 구분합니다.
   */
  const readQuantity = (value) => {
    if (
      value === null
      || value === undefined
      || value === ""
      || !Number.isFinite(Number(value))
    ) {
      return null;
    }

    const quantity = Number(value);

    return quantity >= 0
      ? quantity
      : null;
  };

  /*
   * 기존 그래프 항목과 색상을 유지합니다.
   */
  const rows = [
    {
      label: "추천 생산량",
      value: readQuantity(
        record.adjusted_quantity
      ),
      color: "primary",
    },
    {
      label: "실제 생산량",
      value: readQuantity(
        record.actual_production
      ),
      color: "success",
    },
    {
      label: "추정 판매량",
      value: readQuantity(
        record.estimated_sales
      ),
      color: "info",
    },
    {
      label: "폐기량",
      value: readQuantity(
        record.actual_waste
      ),
      color: "error",
    },
    /*
    * 해당 날짜의 이월재고
    *
    * 이미 확보된 수량이므로
    * 실제 생산량과 구분해 표시합니다.
    *
    * 기존 그래프 계산과 디자인은 유지합니다.
    */
    {
      label: "이월재고",
      value: readQuantity(
        record.carryover_in
      ),
      color: "secondary",
    },
    {
      label: "이월예정",
      value: readQuantity(
        record.actual_carryover
      ),
      color: "secondary",
    },
  ];

  /*
   * 실제로 기록된 수량만 사용해
   * 그래프 최대값을 결정합니다.
   */
  const maxValue = Math.max(
    1,
    ...rows
      .filter((row) => row.value !== null)
      .map((row) => row.value)
  );

  /*
   * 수량이 없는 항목은 막대 길이를 0으로 처리합니다.
   *
   * 화면의 수량 표시는 null을 유지하므로
   * formatQuantity()에서 '자료 없음'으로 표시됩니다.
   */
  return rows.map((row) => ({
    ...row,

    percent: row.value === null
      ? 0
      : Math.max(
          0,
          Math.min(
            100,
            (row.value / maxValue) * 100
          )
        ),
  }));
});

/*
 * 선택한 날짜의 추천 생산량과 실제 결과 비교
 *
 * [목적]
 * 추천 생산량에 이월재고를 더한 수량과
 * 해당 날짜의 추정 판매량을 비교합니다.
 *
 * [표시 원칙]
 * - 기록이 없는 수량은 0개로 처리하지 않습니다.
 * - 이월재고를 중복 계산하지 않습니다.
 * - 판매 기회 손실은 가능성으로만 안내합니다.
 * - 실제 폐기량과 이월예정도 함께 표시합니다.
 *
 * 기존 날짜 이동과 그래프 기능은 유지합니다.
 */
const comparisonResultText = computed(() => {
  const record = selectedComparisonRecord.value;

  if (!record) {
    return "";
  }

  /*
   * 실제로 기록된 숫자만 사용합니다.
   */
  const readQuantity = (value) => {
    if (
      value === null
      || value === undefined
      || value === ""
      || !Number.isFinite(Number(value))
      || Number(value) < 0
    ) {
      return null;
    }

    return Number(value);
  };

  const recommended = readQuantity(
    record.adjusted_quantity
  );

  const carryoverIn = readQuantity(
    record.carryover_in
  );

  const produced = readQuantity(
    record.actual_production
  );

  const estimatedSales = readQuantity(
    record.estimated_sales
  );

  const waste = readQuantity(
    record.actual_waste
  );

  const carryoverOut = readQuantity(
    record.actual_carryover
  );

  const messages = [];

  /*
   * 추천 생산량과 실제 생산량 비교
   */
  if (
    recommended !== null
    && produced !== null
  ) {
    const difference = produced - recommended;

    if (difference > 0) {
      messages.push(
        `추천보다 ${formatNumber(difference)}개 더 생산했습니다.`
      );
    } else if (difference < 0) {
      messages.push(
        `추천보다 ${formatNumber(Math.abs(difference))}개 적게 생산했습니다.`
      );
    } else {
      messages.push(
        "추천 생산량과 실제 생산량이 같았습니다."
      );
    }
  }

  /*
   * 추천대로 생산했을 경우 확보 가능한 수량
   *
   * 추천 생산량 + 해당 날짜의 이월재고
   */
  if (
    recommended !== null
    && carryoverIn !== null
    && estimatedSales !== null
  ) {
    const recommendedAvailable =
      recommended + carryoverIn;

    const difference =
      estimatedSales - recommendedAvailable;

    /*
     * 추천 수량만으로는
     * 추정 판매량을 충족하지 못했을 가능성
     */
    if (difference > 0) {
      messages.push(
        `추천대로 생산했다면 `
        + `${formatNumber(difference)}개가 부족했을 수 있습니다.`
      );
    }

    /*
     * 추정 판매량을 충족할 수 있었던 경우
     */
    else {
      messages.push(
        "추천 수량으로 추정 판매량을 충족할 수 있었습니다."
      );
    }
  }

  /*
   * 실제 폐기량과 이월예정
   */
  if (
    waste !== null
    && carryoverOut !== null
  ) {
    messages.push(
      `폐기 ${formatNumber(waste)}개, `
      + `이월예정 ${formatNumber(carryoverOut)}개였습니다.`
    );

    if (
      waste === 0
      && carryoverOut === 0
    ) {
      messages.push(
        "제품이 남지 않아 더 판매할 기회가 있었을 수 있습니다."
      );
    }
  } else if (waste !== null) {
    messages.push(
      `폐기 ${formatNumber(waste)}개였습니다.`
    );
  } else if (carryoverOut !== null) {
    messages.push(
      `이월예정 ${formatNumber(carryoverOut)}개였습니다.`
    );
  }

  if (!messages.length) {
    return "해당 날짜의 생산 결과를 확인할 수 없습니다.";
  }

  return messages.join(" ");
});

/* 추천 생산량과 실제 결과 */
const accuracyRows = computed(() => [
  {
    label: "비교한 영업일",
    value: `${formatCount(accuracy.value?.tested_days)}일치`,
  },
  {
    label: "평균 수량 차이",
    value: formatQuantity(
      accuracy.value?.average_error_quantity
    ),
  },
  {
    label: "평균 차이율",
    value: formatPercent(
      accuracy.value?.overall_error_rate
    ),
  },
]);
</script>

<style scoped>
/*
 * 추천 분석 다이얼로그
 * 기존 글로벌 CSS를 기반으로 구성합니다.
 */

/* 기본 글자 */
.recommendation-analysis {
  min-width: 0;
  font-size: 0.76rem;
  line-height: 1.45;
}

/* 반복되는 정보 행 */
.recommendation-analysis__rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.recommendation-analysis__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
}

/* 왼쪽 항목명 */
.recommendation-analysis__label {
  flex: 1 1 auto;
  min-width: 0;
  color: rgba(var(--v-theme-on-surface), 0.7);
  overflow-wrap: anywhere;
}

/* 오른쪽 수량 */
.recommendation-analysis__value {
  flex: 0 0 auto;
  max-width: 48%;
  text-align: right;
  color: rgb(var(--v-theme-on-surface));
  font-weight: 400;
  overflow-wrap: anywhere;
}

/* 최종 추천 생산량 */
.recommendation-analysis__main-quantity {
  font-size: 1.125rem;
  font-weight: 600;
  line-height: 1.4;
  color: rgb(var(--v-theme-on-surface));
}

/* 보조 설명 */
.recommendation-analysis__note {
  margin: 0;
  font-size: inherit;
  line-height: inherit;
  font-weight: 400;
  color: rgba(var(--v-theme-on-surface), 0.7);
  overflow-wrap: anywhere;
}

/* 추천 이유 */
.recommendation-analysis__reason {
  margin: 0;
  font-size: inherit;
  line-height: inherit;
  font-weight: 400;
  color: rgb(var(--v-theme-on-surface));
  overflow-wrap: anywhere;
}

/*
 * 월별·요일별 그래프
 */
.recommendation-analysis__chart-item {
  min-width: 0;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
}

.recommendation-analysis__chart-item:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

/*
 * 최근 날짜별 기록
 *
 * 320px에서도 5열로 표시합니다.
 */
.recommendation-analysis__day-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 6px;
  min-width: 0;
}

/* 날짜 선택 버튼 */
.recommendation-analysis__day-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;

  min-width: 0;
  min-height: 56px;
  padding: 6px 3px;

  border: 1px solid rgba(var(--v-border-color), 0.2);
  border-radius: 8px;

  background: transparent;
  color: rgb(var(--v-theme-on-surface));

  font: inherit;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

/* 선택 날짜 */
.recommendation-analysis__day-tile--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

/* 키보드 포커스 */
.recommendation-analysis__day-tile:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 날짜 */
.recommendation-analysis__day-tile-date {
  font-size: 0.72rem;
  line-height: 1.25;
  font-weight: 500;
  white-space: nowrap;
}

/* 추정 판매량 */
.recommendation-analysis__day-tile-quantity {
  max-width: 100%;
  font-size: 0.7rem;
  line-height: 1.25;
  font-weight: 400;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/*
 * 날짜별 작은 판매량 그래프
 */
.recommendation-analysis__mini-bar {
  display: block;
  width: 80%;
  height: 4px;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), 0.1);
}

.recommendation-analysis__mini-fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: rgb(var(--v-theme-primary));
}
</style>