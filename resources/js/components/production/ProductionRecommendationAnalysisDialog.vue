<template>
  <AppDialogLayout
    v-model="dialogOpen"
    :title="`${productName || '제품'} - 분석과 추천 이유`"
    :max-width="620"
    :loading="loading"
    loading-text="불러오는중.."
    :error="error"
    :retryable="Boolean(error)"
    :empty="!recommendation"
    empty-text="표시할 추천 분석 자료가 없습니다."
    @retry="emit('retry')"
  >
    <div class="recommendation-analysis d-flex flex-column ga-3">

      <!-- 분석 대상 기간 -->
      <section
        v-if="details?.period"
        class="d-flex flex-column ga-2"
      >
        <span class="app-form-section-title">
          분석 대상 기간
        </span>

        <div class="recommendation-analysis__rows">
          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              분석 기간
            </span>

            <span class="recommendation-analysis__value">
              {{ details.period.from }}
              ~
              {{ details.period.to }}
            </span>
          </div>

          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              실제 분석 가능한 기록
            </span>

            <span class="recommendation-analysis__value">
              {{ formatCount(totals?.valid_days) }}일
            </span>
          </div>
        </div>

        <p class="recommendation-analysis__note">
          최근 최대 12개월을 대상으로 분석합니다.
          휴무일이나 기록이 없는 날짜는
          판매량 0개로 계산하지 않습니다.
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
          표시할 추천 이유가 없습니다.
        </p>
      </section>

      <v-divider />

      <!-- 추천량 계산 과정 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          추천량 계산 과정
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

        <p
          v-if="calculation?.high_quantity_adjusted"
          class="recommendation-analysis__note"
        >
          추천 수량이 평소보다 높게 계산되어
          최근 판매 흐름을 기준으로 추가 조정했습니다.
        </p>

        <v-divider />

        <!-- 분석에 사용한 기록 -->
        <span class="app-form-section-title">
          분석 기록
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

        <!-- 기간별 수요 비교 -->
        <span class="app-form-section-title">
          기간별 수요 비교
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
          같은 요일의 기록이 아직 충분하지 않아
          다른 정상 영업일의 기록도 참고했습니다.
          기록이 쌓이면 해당 요일의 판매 흐름을
          더 자세하게 분석할 수 있습니다.
        </v-alert>

        <v-alert
          v-if="history && history.excluded_record_count > 0"
          type="warning"
          variant="tonal"
          density="comfortable"
          class="app-supporting-alert"
        >
          일부 기록은 마감 상태 또는 수량 확인 문제로
          추천 계산에서 제외되었습니다.
          제외된 기록은 판매량 0개로 계산하지 않습니다.
        </v-alert>
      </section>

      <v-divider />

      <!-- 최근 12개월 판매 흐름 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          최근 12개월 판매 흐름
        </span>

        <p class="recommendation-analysis__note">
          각 월의 기록이 있는 날을 기준으로
          하루 평균 판매 수량을 계산했습니다.
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
              기록 {{ formatCount(month.record_count) }}일
            </span>
          </div>
        </div>

        <p
          v-else
          class="recommendation-analysis__note"
        >
          월별 판매 흐름을 보여줄 기록이 없습니다.
        </p>
      </section>

      <v-divider />

      <!-- 최근 판매량 변화 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          최근 판매량 변화
        </span>

        <p class="recommendation-analysis__note">
          최근 최대 28개 정상 기록의 추정 판매량입니다.
          날짜를 선택하면 해당 날짜의 상세 수량을
          확인할 수 있습니다.
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
                `${day.date}, 추정 판매 ${formatQuantity(day.estimated_sales)}`
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
            작은 막대는 추정 판매량을 나타냅니다.
            최근 기록 중 판매량이 가장 높은 날을
            기준으로 비교합니다.
          </p>

          <v-divider />

          <!-- 선택 날짜 상세 -->
          <div
            v-if="selectedDailyRecord"
            class="d-flex flex-column ga-3"
          >
            <span class="app-form-section-title">
              {{ selectedDailyRecord.date }} 상세 기록
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
              상세 그래프는 당일 생산량과 들어온 이월을
              합친 수량을 기준으로 표시합니다.
              폐기와 다음 날 이월은 서로 다른 항목입니다.
            </p>
          </div>
        </div>

        <p
          v-else
          class="recommendation-analysis__note"
        >
          최근 판매량 변화를 보여줄 기록이 없습니다.
        </p>
      </section>

      <v-divider />

      <!-- 요일별 판매 비교 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          요일별 판매 비교
        </span>

        <p class="recommendation-analysis__note">
          특별한 행사일을 제외한 정상 영업일의
          하루 평균 판매 수량입니다.
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
              기록 {{ formatCount(day.record_count) }}일
            </span>
          </div>
        </div>
      </section>

      <v-divider />

      <!-- 생산과 남은 재고 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          생산과 남은 재고
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
              남은 재고 비율
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
          재고 사용 비율
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
          남은 재고는 폐기와 다음 날 이월을 합산한 수량입니다.
          이 비율은 생산일 기준 공식 폐기율과 구분되는
          추천 분석용 수치입니다.
        </p>
      </section>

      <v-divider />

      <!-- 완판과 추가 판매 가능성 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          완판과 추가 판매 가능성
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
          재고가 모두 소진되었다고 해서 반드시
          추가 판매가 가능했던 것은 아닙니다.
          판매 기회가 있었을 가능성을 확인하는 지표입니다.
        </p>
      </section>

      <v-divider />

      <!-- 최근 판매 흐름의 변화 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          최근 판매 흐름의 변화
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
              최근 동일 요일 판매량 변화
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
            같은 요일의 최근 기록
            {{ formatCount(trend.recent_trend.sample_count) }}일을
            비교한 결과입니다.
          </span>
        </div>

        <p
          v-if="trend?.recent_outlier"
          class="recommendation-analysis__note"
        >
          최근 기록 중 평소와 차이가 큰 날이 확인되었습니다.
          일시적인 변화인지 함께 확인해 주세요.
        </p>
      </section>

      <v-divider />

      <!-- 과거 추천 결과 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          과거 추천 결과
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

        <v-divider />

        <span class="app-form-section-title">
          생산량 조정 방식 비교
        </span>

        <p class="recommendation-analysis__note">
          과거 재고가 많이 남았던 날을 대상으로
          기존 방식과 생산량을 5% 줄이는 방식을
          비교한 결과입니다.
        </p>

        <div class="recommendation-analysis__rows">
          <div
            v-for="row in adjustmentRows"
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
          {{ adjustmentText }}
        </p>

        <v-alert
          type="info"
          variant="tonal"
          density="comfortable"
          class="app-supporting-alert"
        >
          이 결과는 과거 기록으로 계산한 비교 결과입니다.
          실제로 생산량을 변경하여 실험한 결과는 아니며,
          현재 추천 생산량에도 자동 적용되지 않습니다.
          예상 부족 수량은 실제 결품 수량과 다를 수 있습니다.
        </v-alert>
      </section>

      <v-divider />

      <!-- 확인이 필요한 내용 -->
      <section
        v-if="warnings.length"
        class="d-flex flex-column ga-2"
      >
        <span class="app-form-section-title">
          확인이 필요한 내용
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

const inventory = computed(
  () => explanation.value?.inventory ?? null
);

const trend = computed(
  () => explanation.value?.trend ?? null
);

const accuracy = computed(
  () => explanation.value?.accuracy ?? null
);

const adjustment = computed(
  () => accuracy.value?.adjustment_test ?? null
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
      label: "당일 생산",
      quantity: day.production,
      color: "success",
    },
    {
      label: "이월 재고",
      quantity: day.carryover_in,
      color: "secondary",
    },
    {
      label: "판매(추정)",
      quantity: day.estimated_sales,
      color: "primary",
    },
    {
      label: "로스",
      quantity: day.loss,
      color: "warning",
    },
    {
      label: "폐기",
      quantity: day.waste,
      color: "error",
    },
    {
      label: "이월 예정",
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

/* 최근 추세 */
const trendText = computed(() => {
  const value = trend.value?.recent_trend;

  if (!value) {
    return "최근 동일 요일에서 뚜렷한 증가·감소 추세가 확인되지 않았거나 비교할 기록이 부족합니다.";
  }

  return value.direction === "increasing"
    ? "최근 동일 요일의 추정 판매량이 증가하는 흐름입니다."
    : "최근 동일 요일의 추정 판매량이 감소하는 흐름입니다.";
});

/* 과거 생산량 조정 방식 비교 */
const adjustmentText = computed(() => {
  const status = accuracy.value?.adjustment_assessment;

  switch (status) {
    case "promising":
      return "과거 기록에서 생산량을 조금 줄이는 실험이 좋은 결과를 보였습니다. 실제 추천량에는 아직 적용하지 않았습니다.";

    case "shortage_risk":
      return "생산량을 줄이는 실험에서 판매 수량이 부족해질 가능성이 높아졌습니다.";

    case "not_better":
      return "생산량을 줄이는 실험이 현재 추천 방식보다 좋은 결과를 보여주지 못했습니다.";

    default:
      return "생산량 조정 방법을 비교하기에는 아직 기록이 부족합니다.";
  }
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

/* 추천량 계산 과정 */
const calculationRows = computed(() => [
  {
    label: "과거 기록으로 계산한 기본 수요",
    value: formatQuantity(calculation.value?.base_demand),
  },
  {
    label: "날씨·행사 등을 고려한 예상 수요",
    value: formatQuantity(
      calculation.value?.expected_demand_before_carryover
    ),
  },
  {
    label: "당일 이월 재고",
    value: formatQuantity(calculation.value?.target_carryover_in),
  },
  {
    label: "최종 신규 생산 추천량",
    value: formatQuantity(recommendation.value?.center),
    highlight: true,
  },
]);

/* 분석 기록 */
const historyRows = computed(() => [
  {
    label: "분석에 사용한 기록",
    value: `${formatCount(history.value?.valid_record_count)}일`,
  },
  {
    label: "분석에서 제외된 기록",
    value: `${formatCount(history.value?.excluded_record_count)}일`,
  },
  {
    label: "같은 요일 기록",
    value: `${formatCount(history.value?.same_weekday_count)}일`,
  },
  {
    label: "최근 3개월 반영 비중",
    value: formatPercent(history.value?.recent_weight),
  },
  {
    label: "이전 9개월 반영 비중",
    value: formatPercent(history.value?.older_weight),
  },
]);

/* 기간별 수요 비교 */
const periodRows = computed(() => [
  {
    label: "최근 3개월 분석 기록",
    value: `${formatCount(history.value?.recent_record_count)}일`,
  },
  {
    label: "최근 3개월 평균 수요",
    value: formatQuantity(history.value?.recent_average),
  },
  {
    label: "이전 9개월 분석 기록",
    value: `${formatCount(history.value?.older_record_count)}일`,
  },
  {
    label: "이전 9개월 평균 수요",
    value: formatQuantity(history.value?.older_average),
  },
]);

/* 생산·이월·로스·폐기 합계 */
const totalRows = computed(() => [
  {
    label: "생산",
    value: formatQuantity(totals.value?.production),
  },
  {
    label: "이월 입고",
    value: formatQuantity(totals.value?.carryover_in),
  },
  {
    label: "로스",
    value: formatQuantity(totals.value?.loss),
  },
  {
    label: "폐기",
    value: formatQuantity(totals.value?.waste),
  },
  {
    label: "다음 날로 이월",
    value: formatQuantity(totals.value?.carryover_out),
  },
  {
    label: "추정 판매량",
    value: formatQuantity(totals.value?.estimated_sales),
  },
]);

/* 재고 사용 비율 */
const inventoryRateRows = computed(() => [
  {
    label: "추정 판매",
    quantity: totals.value?.estimated_sales,
    color: "primary",
  },
  {
    label: "로스",
    quantity: totals.value?.loss,
    color: "warning",
  },
  {
    label: "폐기",
    quantity: totals.value?.waste,
    color: "error",
  },
  {
    label: "다음 날 이월",
    quantity: totals.value?.carryover_out,
    color: "secondary",
  },
]);

/* 완판 및 추가 판매 가능성 */
const inventoryRows = computed(() => [
  {
    label: "최근 재고가 모두 소진된 날",
    value: `${formatCount(inventory.value?.zero_remaining_days)}일`,
  },
  {
    label: "완판 가능성이 확인된 날",
    value: `${formatCount(inventory.value?.possible_sellout_days)}일`,
  },
  {
    label: "폐기·이월 합계가 1개였던 날",
    value: `${formatCount(inventory.value?.one_remaining_days)}일`,
  },
]);

/* 과거 추천 정확도 */
const accuracyRows = computed(() => [
  {
    label: "과거 추천을 시험한 기록",
    value: `${formatCount(accuracy.value?.tested_days)}일`,
  },
  {
    label: "평균적으로 차이 난 수량",
    value: formatQuantity(accuracy.value?.average_error_quantity),
  },
  {
    label: "전체 추천 차이 비율",
    value: formatPercent(accuracy.value?.overall_error_rate),
  },
]);

/* 생산량 조정 방식 비교 */
const adjustmentRows = computed(() => [
  {
    label: "비교한 기록",
    value: `${formatCount(adjustment.value?.count)}일`,
  },
  {
    label: "기존 방식의 평균 차이",
    value: formatQuantity(adjustment.value?.base_mae),
  },
  {
    label: "생산량을 줄인 방식의 평균 차이",
    value: formatQuantity(adjustment.value?.candidate_mae),
  },
  {
    label: "평균 차이의 개선 비율",
    value: formatPercent(adjustment.value?.improvement_rate),
  },
  {
    label: "기존 방식의 예상 부족 수량",
    value: formatQuantity(adjustment.value?.base_shortage_total),
  },
  {
    label: "생산량을 줄인 방식의 예상 부족 수량",
    value: formatQuantity(adjustment.value?.candidate_shortage_total),
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