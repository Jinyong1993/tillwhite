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
        class="d-flex flex-column ga-3"
      >
        <span class="app-form-section-title">
          분석 기간
        </span>

        <div class="recommendation-analysis__date-range">
          {{ details.period.from }} ~ {{ details.period.to }}
        </div>

        <div class="recommendation-analysis__row">
          <span class="recommendation-analysis__label">
            분석한 영업일
          </span>

          <span class="recommendation-analysis__value">
            {{ formatCount(totals?.valid_days) }}일치
          </span>
        </div>

        <p class="recommendation-analysis__note">
          최근 최대 12개월의 영업 기록입니다.
        </p>
      </section>

      <!-- 추천 생산량 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          추천 생산량
        </span>

        <span class="recommendation-analysis__main-quantity">
          {{ formatQuantity(recommendation?.center) }}
        </span>

        <div class="recommendation-analysis__row">
          <span class="recommendation-analysis__label">
            추천 범위
          </span>

          <span class="recommendation-analysis__value">
            {{ formatQuantity(recommendation?.min) }}
            ~
            {{ formatQuantity(recommendation?.max) }}
          </span>
        </div>

        <div class="recommendation-analysis__row align-center">
          <span class="recommendation-analysis__label">
            추천 신뢰도
          </span>

          <v-chip
            size="small"
            variant="tonal"
            :color="confidenceColor"
          >
            {{ confidenceText }}
          </v-chip>
        </div>
      </section>

      <!-- 추천 이유 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          이 수량을 추천하는 이유
        </span>

        <!-- 서버에서 전달한 추천 이유를 모두 표시 -->
        <ol
          v-if="organizedReasons.length"
          class="recommendation-analysis__reason-list"
        >
          <li
            v-for="(item, index) in organizedReasons"
            :key="index"
            class="recommendation-analysis__reason"
          >
            <span>{{ item.text }}</span>

            <!-- 해당 이유의 날짜별 폐기·이월 기록 -->
            <div
              v-if="item.records.length"
              class="app-dialog-subcard d-flex flex-column ga-2 mt-2"
            >
              <span class="app-form-section-title">
                참고한 날짜별 폐기·이월 기록
              </span>

              <p class="recommendation-analysis__note">
                아래는 추천 이유에 표시된 날짜별 기록입니다.
              </p>

              <div
                v-for="(record, recordIndex) in item.records"
                :key="recordIndex"
                class="recommendation-analysis__reason-record d-flex align-start justify-space-between ga-2"
              >
                <span class="recommendation-analysis__label">
                  {{ record.dateLabel }}
                </span>

                <span class="recommendation-analysis__value">
                  {{ record.result }}
                </span>
              </div>
            </div>
          </li>
        </ol>

        <!-- 추천 수량에 대한 최종 설명 -->
        <p
          v-if="organizedReasons.length && recommendationSummary"
          class="recommendation-analysis__note"
        >
          {{ recommendationSummary }}
        </p>

        <p
          v-if="!organizedReasons.length"
          class="recommendation-analysis__note"
        >
          추천 이유를 확인할 수 없습니다.
        </p>
      </section>

      <!-- 추천에 참고한 판매 기록 -->
      <section
        v-if="referenceRecords.length > 0"
        class="d-flex flex-column ga-3"
      >
        <!-- 제목과 기록 수 -->
        <div class="d-flex flex-column ga-1">
          <span class="app-form-section-title">
            추천에 참고한 판매 기록
          </span>

          <p class="recommendation-analysis__note">
            추천 생산량을 계산할 때 참고한
            {{ formatCount(referenceRecords.length) }}일치 기록입니다.
          </p>
        </div>

        <!-- 항목 제목 -->
        <div class="recommendation-analysis__row">
          <span class="recommendation-analysis__label">
            날짜
          </span>

          <span class="recommendation-analysis__label text-right">
            추정 판매량
          </span>
        </div>

        <!-- 날짜별 추정 판매량 -->
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
      </section>

      <!-- 추천 생산량 상세 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          추천 생산량 상세
        </span>

        <!-- 예상 판매량과 이월재고 -->
        <div class="recommendation-analysis__rows">
          <div
            v-for="row in calculationRows.filter(
              (item) => !item.highlight
            )"
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

        <!-- 추천 생산량 -->
        <div
          v-for="row in calculationRows.filter(
            (item) => item.highlight
          )"
          :key="row.label"
          class="recommendation-analysis__calculation-result"
        >
          <span class="font-weight-medium">
            {{ row.label }}
          </span>

          <span class="font-weight-medium text-right">
            {{ row.value }}
          </span>
        </div>
      </section>

      <!-- 참고한 기록 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          참고한 기록
        </span>

        <!-- 참고한 영업일 -->
        <div class="recommendation-analysis__rows">
          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              참고한 영업일
            </span>

            <span class="recommendation-analysis__value">
              {{ formatCount(history?.valid_record_count) }}일치
            </span>
          </div>

          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              같은 요일 기록
            </span>

            <span class="recommendation-analysis__value">
              {{ formatCount(history?.same_weekday_count) }}일치
            </span>
          </div>

          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              제외된 기록
            </span>

            <span class="recommendation-analysis__value">
              {{ formatCount(history?.excluded_record_count) }}일치
            </span>
          </div>
        </div>

        <!-- 기간별 기록 반영 비율 -->
        <div class="app-dialog-subcard d-flex flex-column ga-3">
          <span class="app-form-section-title">
            기간별 기록 반영 비율
          </span>

          <div class="recommendation-analysis__rows">
            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                최근 3개월
              </span>

              <span class="recommendation-analysis__value">
                {{ formatPercent(history?.recent_weight) }}
              </span>
            </div>

            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                이전 9개월
              </span>

              <span class="recommendation-analysis__value">
                {{ formatPercent(history?.older_weight) }}
              </span>
            </div>
          </div>

          <p class="recommendation-analysis__note">
            판매량을 예상할 때 최근 기록을 더 많이 참고했습니다.
          </p>
        </div>

        <!-- 같은 요일 기록 부족 안내 -->
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

        <!-- 제외된 기록 안내 -->
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

      <!-- 기간별 추정 판매량 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          기간별 추정 판매량
        </span>

        <!-- 최근 3개월 -->
        <div class="app-dialog-subcard d-flex flex-column ga-3">
          <span class="app-form-section-title">
            최근 3개월
          </span>

          <div class="recommendation-analysis__rows">
            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                참고한 영업일
              </span>

              <span class="recommendation-analysis__value">
                {{ formatCount(history?.recent_record_count) }}일치
              </span>
            </div>

            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                하루 평균 추정 판매량
              </span>

              <span class="recommendation-analysis__value">
                {{ formatQuantity(history?.recent_average) }}
              </span>
            </div>
          </div>
        </div>

        <!-- 이전 9개월 -->
        <div class="app-dialog-subcard d-flex flex-column ga-3">
          <span class="app-form-section-title">
            이전 9개월
          </span>

          <div class="recommendation-analysis__rows">
            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                참고한 영업일
              </span>

              <span class="recommendation-analysis__value">
                {{ formatCount(history?.older_record_count) }}일치
              </span>
            </div>

            <div class="recommendation-analysis__row">
              <span class="recommendation-analysis__label">
                하루 평균 추정 판매량
              </span>

              <span class="recommendation-analysis__value">
                {{ formatQuantity(history?.older_average) }}
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- 월별 추정 판매량 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          월별 추정 판매량
        </span>

        <p class="recommendation-analysis__note">
          월별 하루 평균 추정 판매량을 비교합니다.
        </p>

        <!-- 월별 기록이 있는 경우 -->
        <div
          v-if="monthly.length"
          class="d-flex flex-column ga-3"
        >
          <div
            v-for="month in monthly"
            :key="month.month"
            class="app-dialog-subcard d-flex flex-column ga-2"
          >
            <!-- 월과 하루 평균 추정 판매량 -->
            <div class="recommendation-analysis__row">
              <span class="font-weight-medium">
                {{ month.month }}
              </span>

              <span class="font-weight-medium text-right">
                {{ formatNumber(month.average_sales) }}개
              </span>
            </div>

            <!-- 월별 판매량 비교 그래프 -->
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

            <!-- 해당 월에 참고한 기록 수 -->
            <p class="recommendation-analysis__note">
              참고한 영업일
              {{ formatCount(month.record_count) }}일치
            </p>
          </div>
        </div>

        <!-- 월별 기록이 없는 경우 -->
        <p
          v-else
          class="recommendation-analysis__note"
        >
          월별 기록이 없습니다.
        </p>
      </section>

      <!-- 최근 추정 판매량 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          최근 추정 판매량
        </span>

        <p class="recommendation-analysis__note">
          날짜를 선택하면 해당 날짜의 기록을 확인할 수 있습니다.
        </p>

        <!-- 최근 기록이 있는 경우 -->
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
              <!-- 날짜 -->
              <span class="recommendation-analysis__day-tile-date">
                {{ day.date.slice(5) }}
              </span>

              <!-- 추정 판매량 -->
              <span class="recommendation-analysis__day-tile-quantity">
                {{ formatQuantity(day.estimated_sales) }}
              </span>

              <!-- 날짜별 추정 판매량 비교 -->
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

          <!-- 선택한 날짜의 상세 기록 -->
          <div
            v-if="selectedDailyRecord"
            class="app-dialog-subcard d-flex flex-column ga-3"
          >
            <!-- 선택한 날짜 -->
            <div class="d-flex flex-column ga-1">
              <span class="app-form-section-title">
                {{ selectedDailyRecord.date }} 생산·판매 기록
              </span>

              <p class="recommendation-analysis__note">
                선택한 날짜의 기록입니다.
              </p>
            </div>

            <!-- 특별 영업일 안내 -->
            <span
              v-if="selectedDailyRecord.special_day"
              class="recommendation-analysis__note"
            >
              행사·특별 영업일
            </span>

            <!-- 생산·판매·재고 상세 그래프 -->
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
          </div>
        </div>

        <!-- 최근 기록이 없는 경우 -->
        <p
          v-else
          class="recommendation-analysis__note"
        >
          최근 기록이 없습니다.
        </p>
      </section>

      <!-- 요일별 추정 판매량 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          요일별 추정 판매량
        </span>

        <p class="recommendation-analysis__note">
          행사일을 제외한 요일별 하루 평균 추정 판매량입니다.
        </p>

        <div class="d-flex flex-column ga-3">
          <div
            v-for="day in weekdays"
            :key="day.weekday"
            class="recommendation-analysis__weekday-item d-flex flex-column ga-2"
            :class="{
              'recommendation-analysis__weekday-item--target':
                isTargetWeekday(day),
            }"
          >
            <!-- 요일 및 추정 판매량 -->
            <div class="recommendation-analysis__row">
              <div class="d-flex flex-column ga-1">
                <span class="font-weight-medium">
                  {{ day.name }}
                </span>

                <!-- 추천 날짜와 같은 요일만 표시 -->
                <span
                  v-if="isTargetWeekday(day)"
                  class="recommendation-analysis__note"
                >
                  추천 날짜의 요일
                </span>
              </div>

              <span class="font-weight-medium text-right">
                {{
                  hasWeekdaySales(day)
                    ? `${formatNumber(day.average_sales)}개`
                    : '기록 없음'
                }}
              </span>
            </div>

            <!-- 기록이 있는 요일만 그래프 표시 -->
            <v-progress-linear
              v-if="hasWeekdaySales(day)"
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

            <!-- 해당 요일의 판매 기록 수 -->
            <p class="recommendation-analysis__note">
              판매 기록 {{ formatCount(day.record_count) }}일치
            </p>
          </div>
        </div>
      </section>

      <!-- 생산·판매·폐기 현황 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          전체 기간 수량 현황
        </span>

        <!-- 생산·판매·재고 수량 -->
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
      </section>

      <!-- 수량별 현황 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          수량별 비율
        </span>

        <!-- 항목별 수량 및 비율 -->
        <div class="d-flex flex-column ga-3">
          <div
            v-for="row in inventoryRateRows"
            :key="row.label"
            class="recommendation-analysis__usage-item d-flex flex-column ga-2"
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

            <!-- 전체 수량 대비 비율 -->
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

        <!-- 폐기·이월예정 비율 -->
        <div class="app-dialog-subcard">
          <div class="recommendation-analysis__row align-center">
            <span class="font-weight-medium">
              폐기·이월예정 비율
            </span>

            <span class="font-weight-medium text-right">
              {{ formatPercent(totals?.remaining_rate) }}
            </span>
          </div>
        </div>

        <p class="recommendation-analysis__note">
          생산량과 이월재고를 합친 전체 수량을 기준으로 합니다.
        </p>
      </section>

      <!-- 품절 가능성과 추가 판매 기회 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          품절 가능성과 추가 판매 기회
        </span>

        <!-- 품절 가능성이 있었던 날 -->
        <div
          class="app-dialog-subcard d-flex flex-column ga-2"
        >
          <div class="recommendation-analysis__row">
            <span class="font-weight-medium">
              품절 가능성이 있었던 날
            </span>

            <span class="font-weight-medium text-right">
              {{ formatCount(inventory?.possible_sellout_days) }}일치
            </span>
          </div>

          <p class="recommendation-analysis__note">
            제품이 모두 팔려 더 판매할 기회가 있었을 수 있습니다.
          </p>
        </div>

        <!-- 남은 제품 관련 기록 -->
        <div class="recommendation-analysis__rows">
          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              폐기·이월예정이 없던 날
            </span>

            <span class="recommendation-analysis__value">
              {{ formatCount(inventory?.zero_remaining_days) }}일치
            </span>
          </div>

          <div class="recommendation-analysis__row">
            <span class="recommendation-analysis__label">
              폐기·이월예정이 1개였던 날
            </span>

            <span class="recommendation-analysis__value">
              {{ formatCount(inventory?.one_remaining_days) }}일치
            </span>
          </div>
        </div>
      </section>

      <!-- 최근 판매 변화 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          최근 판매 변화
        </span>

        <!-- 최근 판매 변화가 확인된 경우 -->
        <template v-if="trend?.recent_trend">
          <div
            class="app-dialog-subcard d-flex flex-column ga-2"
          >
            <div class="recommendation-analysis__row align-center">
              <span class="recommendation-analysis__label">
                같은 요일 추정 판매량
              </span>

              <span
                class="recommendation-analysis__value font-weight-medium"
                :class="trendDirectionColor"
              >
                {{ trendDirectionText }}
              </span>
            </div>

            <p class="recommendation-analysis__note">
              {{ trendText }}
            </p>
          </div>

          <p class="recommendation-analysis__note">
            같은 요일 판매 기록
            {{ formatCount(trend.recent_trend.sample_count) }}일치를 비교했습니다.
          </p>
        </template>

        <!-- 최근 판매 변화를 판단하기 어려운 경우 -->
        <p
          v-else
          class="recommendation-analysis__note"
        >
          기록이 충분하지 않아 최근 같은 요일의 판매 변화를 판단하기 어렵습니다.
        </p>
      </section>

      <!-- 추천 수량과 판매량 차이 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          추천 수량과 판매량 차이
        </span>

        <!-- 과거 추천 결과 -->
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
          지난 영업일에 계산한 추천 수량과
          추정 판매량을 비교한 결과입니다.
        </p>
      </section>

      <!-- 저장된 추천량과 마감 결과 -->
      <section class="d-flex flex-column ga-3">
        <span class="app-form-section-title">
          저장된 추천량과 마감 결과
        </span>

        <!-- 비교할 기록이 없는 경우 -->
        <v-alert
          v-if="!comparisonRecords.length"
          type="info"
          variant="tonal"
          density="comfortable"
          class="app-supporting-alert"
        >
          기록이 충분하지 않습니다.
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

            <div class="d-flex flex-column align-center ga-1">
              <span class="text-body-2 text-center">
                {{ selectedComparisonDateLabel }}
              </span>

              <span class="recommendation-analysis__note">
                {{ selectedComparisonIndex + 1 }}
                /
                {{ comparisonRecords.length }}
              </span>
            </div>

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

          <!-- 선택 날짜의 생산 결과 -->
          <div
            v-if="selectedComparisonRecord"
            class="app-dialog-subcard d-flex flex-column ga-3"
          >
            <!-- 기존 그래프 항목 모두 유지 -->
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

            <!-- 선택 날짜의 추천 결과 설명 -->
            <p class="recommendation-analysis__note">
              {{ comparisonResultText }}
            </p>
          </div>

          <p class="recommendation-analysis__note">
            추정 판매량은 실제 판매 기록이 아닌 계산된 수량입니다.
          </p>
        </template>
      </section>

      <!-- 확인 사항 -->
      <section
        v-if="warnings.length"
        class="d-flex flex-column ga-3"
      >
        <!-- 제목과 확인 사항 개수 -->
        <div class="d-flex align-center justify-space-between ga-2">
          <span class="app-form-section-title">
            확인 사항
          </span>

          <span class="recommendation-analysis__note">
            {{ warnings.length }}건
          </span>
        </div>

        <!-- 확인이 필요한 내용 -->
        <div class="d-flex flex-column ga-2">
          <div
            v-for="(warning, index) in warnings"
            :key="index"
            class="recommendation-analysis__warning-item"
          >
            {{ warning }}
          </div>
        </div>
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
import { computed, ref, watch } from "vue";

import AppDialogLayout from "../common/AppDialogLayout.vue";
import AppDialogFooter from "../common/AppDialogFooter.vue";

/* 다이얼로그 입력값 */
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

/* 서버에서 전달받은 추천 결과 */
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

const totals = computed(
  () => details.value?.totals ?? null
);

const monthly = computed(
  () => details.value?.monthly ?? []
);

const weekdays = computed(
  () => details.value?.weekdays ?? []
);

/*
 * 추천 날짜의 요일
 *
 * 서버에서 전달한 추천 대상 날짜를 사용합니다.
 * 날짜를 확인할 수 없으면 특정 요일을 강조하지 않습니다.
 */
const targetWeekdayName = computed(() => {
  const date =
    props.data?.target_date ??
    recommendation.value?.target_date;

  if (
    typeof date !== "string" ||
    !/^\d{4}-\d{2}-\d{2}$/.test(date)
  ) {
    return null;
  }

  const [year, month, day] = date
    .split("-")
    .map(Number);

  const parsed = new Date(
    Date.UTC(year, month - 1, day)
  );

  /* 잘못된 날짜는 사용하지 않습니다. */
  if (
    parsed.getUTCFullYear() !== year ||
    parsed.getUTCMonth() !== month - 1 ||
    parsed.getUTCDate() !== day
  ) {
    return null;
  }

  const names = [
    "일", "월", "화", "수",
    "목", "금", "토",
  ];

  return names[parsed.getUTCDay()];
});

/*
 * 추천 날짜와 같은 요일인지 확인합니다.
 *
 * '월', '월요일' 형태를 모두 비교할 수 있습니다.
 */
function isTargetWeekday(day) {
  if (!targetWeekdayName.value) {
    return false;
  }

  const name = String(day?.name ?? "")
    .replace("요일", "")
    .trim();

  return name === targetWeekdayName.value;
}

/*
 * 해당 요일의 판매 기록이 있는지 확인합니다.
 *
 * 실제 판매량 0개와 기록 없음을 구분합니다.
 */
function hasWeekdaySales(day) {
  if (!day) {
    return false;
  }

  const count = Number(day.record_count);
  const sales = Number(day.average_sales);

  return (
    day.average_sales !== null &&
    day.average_sales !== undefined &&
    day.average_sales !== "" &&
    Number.isFinite(sales) &&
    Number.isFinite(count) &&
    count > 0
  );
}

const recentDaily = computed(
  () => details.value?.recent_daily ?? []
);

/*
 * 추천 계산에 실제로 사용한 판매 기록
 *
 * 서버가 전달한 날짜와 추정 판매량을 그대로 표시합니다.
 * 프론트엔드에서 추천 수량을 다시 계산하지 않습니다.
 */
const referenceRecords = computed(() => {
  const records = history.value?.reference_records;

  if (!Array.isArray(records)) {
    return [];
  }

  return records
    .filter((record) => (
      record &&
      record.date_label &&
      record.weekday_label &&
      record.estimated_sales !== null &&
      record.estimated_sales !== undefined
    ))
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

/* 최근 날짜별 기록 선택 */
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

/* 최근 추정 판매량 그래프 최대값 */
const maxRecentSales = computed(() =>
  Math.max(
    1,
    ...recentDaily.value.map(
      (item) => Number(item.estimated_sales) || 0
    )
  )
);

/* 선택한 날짜에 판매 가능한 전체 수량 */
const dailyAvailable = computed(() =>
  Math.max(
    1,
    Number(selectedDailyRecord.value?.available) || 0
  )
);

/* 선택한 날짜의 생산·판매·재고 기록 */
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

/*
 * 추천 이유 원문
 *
 * 서버에서 전달한 모든 이유를 유지합니다.
 */
const reasons = computed(
  () =>
    explanation.value?.notices?.reasons ??
    recommendation.value?.reasons ??
    []
);

/*
 * 추천 이유 화면 정리
 *
 * 1. 기존 추천 이유를 임의로 삭제하지 않습니다.
 * 2. 날짜별 폐기·이월 기록만 묶어서 표시합니다.
 * 3. 날짜별 기록은 오래된 날짜부터 정렬합니다.
 * 4. 실제로 전달된 날짜별 기록 수를 표시합니다.
 * 5. 날짜가 없는 일반적인 추천 이유도 유지합니다.
 */
const organizedReasons = computed(() => {
  const source = reasons.value;

  if (!Array.isArray(source)) {
    return [];
  }

  const items = [];
  let wasteReason = null;

  /*
   * 날짜별 기록 형태
   *
   * 예:
   * 10월 26일(월): 폐기 3개, 이월예정 1개
   */
  const datePattern =
    /^(\d{1,2})월\s*(\d{1,2})일\(([일월화수목금토])\)\s*:\s*(.+)$/;

  /*
   * 분석 종료일을 기준으로 날짜의 연도를 판단합니다.
   *
   * 분석 기간이 연도를 넘어가는 경우에도
   * 최근 기록을 오래된 날짜부터 표시하기 위함입니다.
   */
  const periodEnd = details.value?.period?.to ?? "";

  const endMatch =
    /^(\d{4})-(\d{2})-(\d{2})$/.exec(periodEnd);

  function recordOrder(month, day) {
    if (!endMatch) {
      return month * 100 + day;
    }

    const endYear = Number(endMatch[1]);

    const endMonthDay =
      Number(endMatch[2]) * 100 +
      Number(endMatch[3]);

    const monthDay = month * 100 + day;

    const year =
      monthDay <= endMonthDay
        ? endYear
        : endYear - 1;

    return year * 10000 + monthDay;
  }

  for (const value of source) {
    if (typeof value !== "string") {
      continue;
    }

    const text = value.trim();

    if (!text) {
      continue;
    }

    const match = datePattern.exec(text);

    /*
     * 날짜별 폐기·이월 기록
     *
     * 날짜와 수량을 분리하여
     * 화면에서 좌우로 표시합니다.
     */
    if (
      match &&
      text.includes("폐기") &&
      text.includes("이월")
    ) {
      const record = {
        dateLabel:
          `${match[1]}월 ${match[2]}일(${match[3]})`,

        result: match[4].replace(
          /,\s*/g,
          " · "
        ),

        order: recordOrder(
          Number(match[1]),
          Number(match[2])
        ),
      };

      /*
       * 관련 폐기·이월 설명이 있으면
       * 그 아래에 날짜별 기록을 묶습니다.
       */
      if (wasteReason) {
        wasteReason.records.push(record);
      } else {
        /*
         * 관련 설명이 없어도
         * 날짜별 기록을 누락하지 않습니다.
         */
        const standalone = {
          text: "날짜별 폐기·이월 기록입니다.",
          records: [record],
        };

        items.push(standalone);
        wasteReason = standalone;
      }

      continue;
    }

    /*
     * 일반 추천 이유
     *
     * 원문을 삭제하거나
     * 임의로 내용을 바꾸지 않습니다.
     */
    const item = {
      text,
      records: [],
    };

    items.push(item);

    /*
     * 최근 폐기·이월 현황을 설명하는 문장에
     * 뒤따르는 날짜별 기록을 연결합니다.
     */
    if (
      /최근\s*\d+일치 기록에서.*폐기량과 이월예정/.test(text)
    ) {
      wasteReason = item;
    }
  }

  /*
   * 날짜별 기록을 오래된 순서부터 표시합니다.
   */
  for (const item of items) {
    item.records.sort(
      (a, b) => a.order - b.order
    );
  }

  return items;
});

/*
 * 이번 추천 생산량에 대한 최종 설명
 *
 * 서버에서 전달한 값만 사용합니다.
 *
 * - 해당 날짜 예상 판매량
 * - 이월재고
 * - 추천 생산량
 *
 * 계산 결과를 프론트엔드에서 변경하지 않습니다.
 */
const recommendationSummary = computed(() => {
  const demand =
    calculation.value?.expected_demand_before_carryover;

  const carryover =
    calculation.value?.target_carryover_in;

  const quantity =
    recommendation.value?.center;

  /*
   * 필요한 수량이 없는 경우
   * 임의의 숫자로 설명하지 않습니다.
   */
  if (
    [demand, carryover, quantity].some(
      (value) =>
        value === null ||
        value === undefined ||
        !Number.isFinite(Number(value))
    )
  ) {
    return "";
  }

  return (
    `해당 날짜 예상 판매량 ${formatQuantity(demand)}와 ` +
    `이월재고 ${formatQuantity(carryover)}를 고려해 ` +
    `새로 생산할 수량 ${formatQuantity(quantity)}를 추천합니다.`
  );
});

/* 확인 사항 */
const warnings = computed(() => {
  const values =
    explanation.value?.notices?.warnings;

  if (Array.isArray(values)) {
    return values;
  }

  const message = recommendation.value?.warning;

  return message ? [message] : [];
});

/* 월별 그래프 최대값 */
const maxMonthlySales = computed(() =>
  Math.max(
    1,
    ...monthly.value.map(
      (item) => Number(item.average_sales) || 0
    )
  )
);

/* 요일별 그래프 최대값 */
const maxWeekdaySales = computed(() =>
  Math.max(
    1,
    ...weekdays.value.map(
      (item) => Number(item.average_sales) || 0
    )
  )
);

/* 추천 신뢰도 */
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

/* 최근 판매 변화 안내 */
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

/*
 * 최근 판매 변화 표시
 *
 * 증가, 감소, 비슷함을 정확하게 구분합니다.
 * 서버에서 전달한 변화율을 그대로 사용합니다.
 */
const trendDirectionText = computed(() => {
  const value = trend.value?.recent_trend;

  if (!value) {
    return "자료 없음";
  }

  const changeRate = value.change_rate;

  if (
    changeRate === null ||
    changeRate === undefined ||
    !Number.isFinite(Number(changeRate))
  ) {
    return "자료 없음";
  }

  const rate = formatPercent(
    Math.abs(Number(changeRate))
  );

  if (value.direction === "increasing") {
    return `${rate} 증가`;
  }

  if (value.direction === "decreasing") {
    return `${rate} 감소`;
  }

  return "비슷한 수준";
});

/*
 * 판매 변화에 따른 색상
 *
 * 증가: 초록색
 * 감소: 빨간색
 * 비슷함 또는 자료 부족: 기본 색상
 */
const trendDirectionColor = computed(() => {
  const direction = trend.value?.recent_trend?.direction;

  if (direction === "increasing") {
    return "text-success";
  }

  if (direction === "decreasing") {
    return "text-error";
  }

  return "";
});

/*
 * 숫자 표시
 *
 * 기록이 없는 값과
 * 실제 0개를 구분합니다.
 */
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

/* 그래프 막대 길이 */
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
    Math.min(
      100,
      (number / maximum) * 100
    )
  );
}

/* 생산량과 이월재고를 합친 수량 */
const availableTotal = computed(() => {
  if (!totals.value) {
    return null;
  }

  return (
    Number(totals.value.production || 0) +
    Number(totals.value.carryover_in || 0)
  );
});

/* 전체 수량 중 각 항목의 비율 */
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
    Math.min(
      100,
      Number(value) * 100
    )
  );
}

/* 추천 생산량 상세 */
const calculationRows = computed(() => [
  {
    label: "평소 예상 판매량",
    value: formatQuantity(
      calculation.value?.base_demand
    ),
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
    value: formatQuantity(
      recommendation.value?.center
    ),
    highlight: true,
  },
]);

/* 참고한 기록 */
const historyRows = computed(() => [
  {
    label: "참고한 기록",
    value:
      `${formatCount(history.value?.valid_record_count)}일치`,
  },
  {
    label: "제외된 기록",
    value:
      `${formatCount(history.value?.excluded_record_count)}일치`,
  },
  {
    label: "같은 요일 기록",
    value:
      `${formatCount(history.value?.same_weekday_count)}일치`,
  },
  {
    label: "최근 3개월 비중",
    value: formatPercent(
      history.value?.recent_weight
    ),
  },
  {
    label: "이전 9개월 비중",
    value: formatPercent(
      history.value?.older_weight
    ),
  },
]);

/* 기간별 추정 판매량 */
const periodRows = computed(() => [
  {
    label: "최근 3개월 기록",
    value:
      `${formatCount(history.value?.recent_record_count)}일치`,
  },
  {
    label: "최근 3개월 평균 추정 판매량",
    value: formatQuantity(
      history.value?.recent_average
    ),
  },
  {
    label: "이전 9개월 기록",
    value:
      `${formatCount(history.value?.older_record_count)}일치`,
  },
  {
    label: "이전 9개월 평균 추정 판매량",
    value: formatQuantity(
      history.value?.older_average
    ),
  },
]);

/* 생산·판매·폐기 현황 */
const totalRows = computed(() => [
  {
    label: "생산량",
    value: formatQuantity(
      totals.value?.production
    ),
  },
  {
    label: "이월재고",
    value: formatQuantity(
      totals.value?.carryover_in
    ),
  },
  {
    label: "로스량",
    value: formatQuantity(
      totals.value?.loss
    ),
  },
  {
    label: "폐기량",
    value: formatQuantity(
      totals.value?.waste
    ),
  },
  {
    label: "이월예정",
    value: formatQuantity(
      totals.value?.carryover_out
    ),
  },
  {
    label: "추정 판매량",
    value: formatQuantity(
      totals.value?.estimated_sales
    ),
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
    value:
      `${formatCount(inventory.value?.zero_remaining_days)}일치`,
  },
  {
    label: "완판 가능성이 있었던 날",
    value:
      `${formatCount(inventory.value?.possible_sellout_days)}일치`,
  },
  {
    label: "폐기·이월예정 합계가 1개였던 날",
    value:
      `${formatCount(inventory.value?.one_remaining_days)}일치`,
  },
]);

/* 날짜별 추천 생산량과 마감 결과 */
const recommendationOutcomes = computed(
  () => props.data?.recommendation_outcomes ?? null
);

const adjustmentComparison = computed(
  () =>
    recommendationOutcomes.value?.adjustment_comparison ??
    null
);

/*
 * 날짜별 비교 기록
 *
 * 추천 생산량과 추정 판매량이 있는 날짜만 표시합니다.
 * 수량이 없는 기록은 실제 0개와 구분합니다.
 * 날짜를 오래된 순서부터 정렬합니다.
 */
const comparisonRecords = computed(() => {
  const records = adjustmentComparison.value?.records;

  if (!Array.isArray(records)) {
    return [];
  }

  const hasQuantity = (value) => (
    value !== null &&
    value !== undefined &&
    value !== "" &&
    Number.isFinite(Number(value)) &&
    Number(value) >= 0
  );

  return [...records]
    .filter((record) => {
      if (
        !record ||
        typeof record.date !== "string" ||
        !/^\d{4}-\d{2}-\d{2}$/.test(record.date)
      ) {
        return false;
      }

      return (
        hasQuantity(record.adjusted_quantity) &&
        hasQuantity(record.estimated_sales)
      );
    })
    .sort(
      (a, b) => a.date.localeCompare(b.date)
    );
});

/* 비교할 날짜 선택 */
const selectedComparisonIndex = ref(0);

watch(
  () => props.data,
  () => {
    selectedComparisonIndex.value = 0;
  }
);

const selectedComparisonRecord = computed(() =>
  comparisonRecords.value[
    selectedComparisonIndex.value
  ] ?? null
);

/*
 * 선택한 날짜를 읽기 쉬운 형태로 표시합니다.
 *
 * 예:
 * 2026-10-26 → 10월 26일(월)
 *
 * UTC를 사용해 브라우저 시간대에 따른
 * 날짜 변경을 방지합니다.
 */
const selectedComparisonDateLabel = computed(() => {
  const date = selectedComparisonRecord.value?.date;

  if (
    typeof date !== "string" ||
    !/^\d{4}-\d{2}-\d{2}$/.test(date)
  ) {
    return "-";
  }

  const [year, month, day] =
    date.split("-").map(Number);

  const parsed = new Date(
    Date.UTC(year, month - 1, day)
  );

  if (
    parsed.getUTCFullYear() !== year ||
    parsed.getUTCMonth() !== month - 1 ||
    parsed.getUTCDate() !== day
  ) {
    return "-";
  }

  const weekdayNames = [
    "일", "월", "화", "수",
    "목", "금", "토",
  ];

  const weekday =
    weekdayNames[parsed.getUTCDay()];

  return `${month}월 ${day}일(${weekday})`;
});

/* 이전·다음 날짜 이동 */
function moveComparisonDate(direction) {
  const next =
    selectedComparisonIndex.value + direction;

  if (
    next < 0 ||
    next >= comparisonRecords.value.length
  ) {
    return;
  }

  selectedComparisonIndex.value = next;
}

/*
 * 날짜별 생산 결과 그래프
 *
 * 추천 생산량, 실제 생산량, 추정 판매량,
 * 폐기량, 이월재고, 이월예정을 표시합니다.
 *
 * 기록이 없는 수량은 0개로 표시하지 않습니다.
 * 실제 0개인 경우에만 0개를 표시합니다.
 */
const comparisonChartRows = computed(() => {
  const record = selectedComparisonRecord.value;

  if (!record) {
    return [];
  }

  const readQuantity = (value) => {
    if (
      value === null ||
      value === undefined ||
      value === "" ||
      !Number.isFinite(Number(value))
    ) {
      return null;
    }

    const quantity = Number(value);

    return quantity >= 0
      ? quantity
      : null;
  };

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
   * 실제로 기록된 수량만
   * 그래프 최대값 계산에 사용합니다.
   */
  const maxValue = Math.max(
    1,
    ...rows
      .filter((row) => row.value !== null)
      .map((row) => row.value)
  );

  return rows.map((row) => ({
    ...row,

    percent:
      row.value === null
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
 * 추천 생산량과 실제 결과 비교
 *
 * 추천 생산량 + 이월재고를
 * 해당 날짜의 추정 판매량과 비교합니다.
 *
 * 기록이 없는 수량은 계산하지 않으며
 * 판매 기회 손실은 가능성으로만 안내합니다.
 */
const comparisonResultText = computed(() => {
  const record = selectedComparisonRecord.value;

  if (!record) {
    return "";
  }

  const readQuantity = (value) => {
    if (
      value === null ||
      value === undefined ||
      value === "" ||
      !Number.isFinite(Number(value)) ||
      Number(value) < 0
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

  /* 추천 생산량과 실제 생산량 비교 */
  if (
    recommended !== null &&
    produced !== null
  ) {
    const difference =
      produced - recommended;

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
   * 추천대로 생산했을 경우 확보할 수량
   *
   * 추천 생산량 + 해당 날짜의 이월재고
   */
  if (
    recommended !== null &&
    carryoverIn !== null &&
    estimatedSales !== null
  ) {
    const recommendedAvailable =
      recommended + carryoverIn;

    const difference =
      estimatedSales - recommendedAvailable;

    if (difference > 0) {
      messages.push(
        `추천대로 생산했다면 ` +
        `${formatNumber(difference)}개가 부족했을 수 있습니다.`
      );
    } else {
      messages.push(
        "추천 수량으로 추정 판매량을 충족할 수 있었습니다."
      );
    }
  }

  /* 실제 폐기량과 이월예정 */
  if (
    waste !== null &&
    carryoverOut !== null
  ) {
    messages.push(
      `폐기 ${formatNumber(waste)}개, ` +
      `이월예정 ${formatNumber(carryoverOut)}개였습니다.`
    );

    if (
      waste === 0 &&
      carryoverOut === 0
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

/* 추천 생산량 정확도 */
const accuracyRows = computed(() => [
  {
    label: "비교한 영업일",
    value:
      `${formatCount(accuracy.value?.tested_days)}일치`,
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
 * 카드형 섹션
 *
 * 글로벌 다이얼로그의 색상과 글꼴 정책을 사용합니다.
 * 기존 글자 크기와 기능은 변경하지 않습니다.
 */
.recommendation-analysis > section {
  min-width: 0;
  width: 100%;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
  box-shadow: none;
}

/*
 * 카드 사이의 중복 구분선만 숨깁니다.
 * 카드 내부 구분선은 유지합니다.
 */
.recommendation-analysis > .v-divider {
  display: none;
}

/* 모바일 화면에서 카드 여백 조정 */
@media (max-width: 480px) {
  .recommendation-analysis > section {
    padding: 10px;
  }
}

/*
 * 추천 분석 다이얼로그
 *
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

/*
 * 추천 이유
 *
 * 기존 글자 크기와 색상을 유지합니다.
 */
.recommendation-analysis__reason {
  margin: 0;
  font-size: inherit;
  line-height: inherit;
  font-weight: 400;
  color: rgb(var(--v-theme-on-surface));
  overflow-wrap: anywhere;
}

/*
 * 추천 이유 목록
 *
 * 번호를 사용해 각 추천 근거를 구분합니다.
 * 기존 글자 크기는 변경하지 않습니다.
 */
.recommendation-analysis__reason-list {
  display: flex;
  flex-direction: column;
  gap: 8px;

  min-width: 0;
  margin: 0;
  padding-left: 20px;

  list-style-type: decimal;
  list-style-position: outside;
}

/* 긴 추천 이유도 모바일 너비에 맞춰 표시 */
.recommendation-analysis__reason-list > li {
  min-width: 0;
  padding-left: 2px;
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
 * 모바일 320px에서도 5열로 표시합니다.
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

/* 선택한 날짜 */
.recommendation-analysis__day-tile--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

/* 키보드로 날짜를 선택할 때 포커스 표시 */
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

/* 날짜별 작은 판매량 그래프 */
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

/*
 * 분석 기간 날짜
 *
 * 기존 글자 크기를 사용합니다.
 */
.recommendation-analysis__date-range {
  min-width: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: inherit;
  line-height: inherit;
  overflow-wrap: anywhere;
}

/*
 * 날짜별 기록
 *
 * 날짜는 왼쪽, 폐기·이월 수량은 오른쪽에 표시합니다.
 * 기존 d-flex 유틸리티로 반응형 배치를 처리합니다.
 */
.recommendation-analysis__reason-record {
  min-width: 0;
  font-size: inherit;
  line-height: inherit;
  font-weight: 400;
  color: rgb(var(--v-theme-on-surface));
  overflow-wrap: anywhere;
}

/*
 * 최종 추천 생산량
 *
 * 기존 글자 크기를 유지하면서
 * 계산 결과만 별도 영역으로 구분합니다.
 */
.recommendation-analysis__calculation-result {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  min-width: 0;
  padding: 10px;

  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);

  color: rgb(var(--v-theme-on-surface));
  font-size: inherit;
  line-height: inherit;
}

.recommendation-analysis__calculation-result > span {
  min-width: 0;
  overflow-wrap: anywhere;
}

/*
 * 추천 날짜와 같은 요일
 *
 * 기존 글자 크기와 색상 체계를 유지합니다.
 * 카드 배경만 은은하게 구분합니다.
 */
.recommendation-analysis__weekday-item--target {
  background: rgba(var(--v-theme-primary), 0.06);
  border: 1px solid rgba(var(--v-theme-primary), 0.18);
}

/*
 * 수량별 현황
 *
 * 판매·로스·폐기·이월예정의 수량과 비율을 표시합니다.
 * 기존 그래프 색상과 글자 크기를 유지합니다.
 */
.recommendation-analysis__usage-item {
  min-width: 0;
}

/*
 * 확인 사항
 *
 * 경고 아이콘 대신 문장으로 안내합니다.
 * 기존 글자 크기와 글로벌 색상을 유지합니다.
 */
.recommendation-analysis__warning-item {
  min-width: 0;
  padding: 10px;
  border-radius: 8px;

  background: rgba(var(--v-theme-on-surface), 0.04);
  color: rgb(var(--v-theme-on-surface));

  font-size: inherit;
  line-height: inherit;
  font-weight: 400;

  overflow-wrap: anywhere;
}
</style>