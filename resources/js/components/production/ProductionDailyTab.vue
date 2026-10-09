<template>
<div class="daily-page">
  <section class="daily-section date-section">
    <div class="app-date-toolbar">
      <div class="app-date-navigation">
        <v-btn
            icon="mdi-chevron-left"
            variant="text"
            size="small"
            aria-label="이전 날짜"
            @click="moveDate(-1)"
        />
        <v-menu
            v-model="dateMenu"
            :close-on-content-click="false"
        >
          <template #activator="{ props: menuProps }">
            <button v-bind="menuProps" type="button" class="app-date-main">
              <span>{{ formatKoreanDate(workDate) }}</span>
              <small>날짜 선택</small>
            </button>
          </template>
          <v-date-picker
              :model-value="workDate"
              @update:model-value="selectPickerDate"
          />
        </v-menu>
        <v-btn
            icon="mdi-chevron-right"
            variant="text"
            size="small"
            aria-label="다음 날짜"
            @click="moveDate(1)"
        />
      </div>
      <div class="app-date-today-slot">
        <v-btn v-show="workDate !== today" size="small" variant="outlined" class="app-date-today" @click="emit('update:workDate', today)">오늘</v-btn>
      </div>
    </div>
  </section>

  <v-alert
      v-if="daily.blocking_previous_date"
      type="warning"
      variant="tonal"
      density="compact"
      class="previous-close-alert app-supporting-alert"
  >
    <div class="previous-close-copy">
      <strong>이전 업무 마감이 필요합니다</strong>
      <span>{{ daily.blocking_previous_date }} 업무를 먼저 마감해 주세요.</span>
    </div>
    <template #append>
      <v-btn
          size="small"
          variant="outlined"
          class="previous-close-action"
          @click="emit('update:workDate', daily.blocking_previous_date)"
      >
        {{ shortDateLabel(daily.blocking_previous_date) }}로 이동
      </v-btn>
    </template>
  </v-alert>

  <section class="daily-section summary-section">
    <div class="section-heading">
      <div>
        <h3>요약</h3>
      </div>
    </div>
    <div class="daily-metrics">
      <button v-for="metric in metrics" :key="metric.key" type="button" class="metric-item" @click="openMetric(metric.key)">
        <span>{{ metric.title }}</span>
        <strong>{{ metric.value }}</strong>
      </button>
    </div>
  </section>

  <v-divider />

  <section class="daily-section product-section product-section-framed">
    <div class="section-heading product-heading">
      <div>
        <h3>제품별 현황 <span class="product-count-heading">{{ activeRows.length }}개</span></h3>
        <p>{{ progressText }}</p>
        <div class="work-progress" aria-label="제품 기록 진행률"><span :style="{ width: `${progressPercent}%` }" /></div>
        <div v-if="missingSummaryText" class="missing-summary">{{ missingSummaryText }}</div>
        <div class="missing-type-grid">
          <button
            v-for="item in missingItems"
            :key="item.key"
            type="button"
            :class="['missing-type-button', { active: missingType === item.key, complete: item.count === 0 }]"
            @click="toggleMissingType(item.key)"
          >
            <span>{{ item.label }} 미확인</span>
            <strong>{{ item.count ? `${item.count}개` : '완료' }}</strong>
          </button>
        </div>
      </div>
      <div v-if="canMutate" class="product-heading-actions">
        <v-btn v-if="nextMissingRow" size="small" variant="outlined" class="missing-action-button" prepend-icon="mdi-skip-next" @click="openNextMissing">미확인 제품</v-btn>
        <div v-else class="missing-complete-state">모든 제품 확인 완료</div>
      <v-menu location="bottom end">
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" size="small" variant="outlined" class="missing-action-button" prepend-icon="mdi-check-all" :disabled="!hasMissingItems">미확인 일괄확인</v-btn>
        </template>
        <v-list
            class="bulk-menu"
            min-width="280"
        >
          <v-list-item
              :disabled="!hasMissingItems"
              @click="askBulkZero('all')"
          >
            <v-list-item-title>전체 미확인 항목을 0으로 확인</v-list-item-title>
            <v-list-item-subtitle>생산·이월·로스·폐기 중 미확인 항목만 없음(0)으로 확인합니다.</v-list-item-subtitle>
          </v-list-item>
          <v-divider />
          <v-list-item
            v-for="item in missingItems"
            :key="item.key"
            :disabled="item.count === 0"
            @click="askBulkZero(item.key)"
          >
            <v-list-item-title>{{ item.label }} 없음(0)으로 확인 <span>{{ item.count ? `${item.count}개` : '완료' }}</span></v-list-item-title>
            <v-list-item-subtitle>아직 확인하지 않은 제품만 적용하며 기존 기록은 유지합니다.</v-list-item-subtitle>
          </v-list-item>
        </v-list>
      </v-menu>
      </div>
    </div>

    <v-text-field
        v-model="search"
        prepend-inner-icon="mdi-magnify"
        label="제품 검색"
        placeholder="제품명을 입력하세요"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="product-search mb-3"
    />

    <!-- 선택 날짜에 최근 기록을 변경한 제품으로 빠르게 이동합니다. -->
    <div v-if="recentProducts.length" class="recent-products">
      <span class="recent-products-label">최근 입력</span>
      <div class="recent-products-list">
        <button
          v-for="row in recentProducts"
          :key="row.id"
          type="button"
          class="recent-product-button"
          :title="`${row.name} 찾기`"
          @click="selectRecentProduct(row)"
        >
          {{ row.name }}
        </button>
      </div>
    </div>

    <div class="product-filter-row mb-4">
      <v-btn-toggle
          v-model="filter"
          mandatory
          density="compact"
          variant="text"
          class="status-filter"
      >
        <v-btn value="all">전체</v-btn>
        <v-btn value="missing">기록 미완료</v-btn>
        <v-btn value="occurred">로스·폐기 발생</v-btn>
      </v-btn-toggle>
      <span class="app-supporting-text text-medium-emphasis">{{ filterResultLabel }}</span>
    </div>

    <section v-for="group in groupedRows" :key="group.name" class="category-block">
      <button type="button" class="category-header" @click="toggleCategory(group.name)">
        <div class="category-heading-copy">
          <strong>{{ group.name }}</strong>
          <span>{{ categoryProgressText(group.allRows) }}</span>
        </div>
        <div class="category-heading-side">
          <span>기록 완료 {{ categoryCompleteCount(group.allRows) }} / {{ group.allRows.filter((row) => row.is_active).length }}</span>
          <v-icon
              :icon="collapsed.has(group.name) ? 'mdi-chevron-down' : 'mdi-chevron-up'"
              size="small"
          />
        </div>
      </button>
      <div class="category-progress" aria-hidden="true">
        <span :style="{ width: `${categoryProgress(group.allRows)}%` }" />
      </div>

      <v-expand-transition>
        <div
          v-show="!collapsed.has(group.name)"
          class="product-table-wrap"
        >
          <table class="product-table">
            <colgroup>
              <col class="product-column" />
              <col v-for="key in 5" :key="key" class="number-column" />
            </colgroup>
            <thead>
              <tr>
                <th>제품명</th>
                <th>생산</th>
                <th>이월</th>
                <th>로스</th>
                <th>폐기</th>
                <th><button type="button" class="waste-rate-header-button" @click.stop="wasteGuideOpen = true">폐기율</button></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="row in group.rows" :key="row.id">
                <tr
                  :class="{
                    'row-inactive': !row.is_active,
                    'row-needs-check': !row.complete && row.is_active
                  }"
                >
                  <!-- 제품명 및 상세 펼치기 -->
                  <td>
                    <div class="product-name-cell">
                      <!-- 기존 제품별 상세 정보 펼치기 기능 유지 -->
                      <button
                        type="button"
                        class="row-detail-toggle"
                        :aria-label="`${row.name} 상세 ${isRowExpanded(row.id) ? '닫기' : '보기'}`"
                        @click="toggleRowDetail(row.id)"
                      >
                        <v-icon
                          :icon="isRowExpanded(row.id) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                          size="16"
                        />
                      </button>

                      <!-- 제품명 클릭 시 기존 제품 상세 다이얼로그 표시 -->
                      <button
                        type="button"
                        class="product-name"
                        :title="row.name"
                        @click="openProduct(row)"
                      >
                        {{ row.name }}
                      </button>
                    </div>
                  </td>

                  <!-- 생산: 통합 재고 관리의 생산 메뉴 표시 -->
                  <td>
                    <button
                      type="button"
                      class="table-value production-value"
                      :class="{ pending: !row.production_confirmed }"
                      @click="openInventoryDialog(row, 'production')"
                    >
                      {{ row.production_confirmed ? row.production : '-' }}
                    </button>
                  </td>

                  <!-- 이월: 재고와 예정 수량을 구분하여 표시 -->
                  <td>
                    <button
                      type="button"
                      class="table-value carryover-value"
                      :class="{ pending: !row.disposition_confirmed }"
                      @click="openInventoryDialog(row, 'carryover')"
                    >
                      <template
                        v-if="
                          Number(row.carryover_in || 0) > 0 ||
                          Number(row.carryover_out || 0) > 0
                        "
                      >
                        <span v-if="Number(row.carryover_in || 0) > 0">
                          재고 {{ row.carryover_in }}
                        </span>

                        <span v-if="Number(row.carryover_out || 0) > 0">
                          예정 {{ row.carryover_out }}
                        </span>
                      </template>

                      <template v-else>
                        {{ row.disposition_confirmed ? '0' : '-' }}
                      </template>
                    </button>
                  </td>

                  <!-- 로스: 통합 재고 관리의 로스 메뉴 표시 -->
                  <td>
                    <button
                      type="button"
                      class="table-value"
                      :class="{ pending: !row.loss_confirmed }"
                      @click="openInventoryDialog(row, 'loss')"
                    >
                      {{ row.loss_confirmed ? row.loss : '-' }}
                    </button>
                  </td>

                  <!-- 폐기: 최초 생산일 귀속 폐기량 표시 -->
                  <td>
                    <button
                      type="button"
                      class="table-value waste-value"
                      :class="{
                        pending:
                          !row.waste_confirmed &&
                          Number(row.attributed_waste || 0) === 0
                      }"
                      @click="openInventoryDialog(row, 'waste')"
                    >
                      {{
                        row.waste_confirmed ||
                        Number(row.attributed_waste || 0) > 0
                          ? Number(row.attributed_waste || 0)
                          : '-'
                      }}
                    </button>
                  </td>

                  <!-- 폐기율: 서버에서 계산한 값 표시, 조회 전용 -->
                  <td>
                    <button
                      type="button"
                      class="table-value rate-value"
                      @click="openInventoryDialog(row, 'waste_rate')"
                    >
                      {{ row.waste_rate == null ? '-' : `${row.waste_rate}%` }}
                    </button>
                  </td>
                </tr>

                <!-- 제품별 상세 -->
                <tr
                  v-if="isRowExpanded(row.id)"
                  class="product-detail-row"
                >
                  <td colspan="6">
                    <div class="product-row-detail">
                      <!-- 이전 날짜에서 넘어온 이월 재고입니다. -->
                      <div v-if="Number(row.carryover_in || 0) > 0">
                        <span>이월 재고</span>
                        <strong>{{ row.carryover_in }}개</strong>
                      </div>

                      <!-- 다음 날짜로 넘길 이월 예정 수량입니다. -->
                      <div v-if="Number(row.carryover_out || 0) > 0">
                        <span>이월 예정</span>
                        <strong>{{ row.carryover_out }}개</strong>
                      </div>

                      <!-- 최초 생산일에 귀속된 폐기량입니다. -->
                      <div>
                        <span>폐기</span>
                        <strong>{{ Number(row.attributed_waste || 0) }}개</strong>
                      </div>

                      <!-- 이월 재고 폐기가 있을 때만 표시합니다. -->
                      <div v-if="carryoverWaste(row) > 0">
                        <span>이월 재고 폐기</span>
                        <strong>{{ carryoverWaste(row) }}개</strong>
                      </div>

                      <!-- 재고 흐름 -->
                      <button
                        type="button"
                        class="product-row-flow stock-flow-trigger"
                        @click="openStockFlow(row)"
                      >
                        <span>재고 흐름</span>
                        <strong>{{ stockFlowText(row) }}</strong>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>

              <!-- 카테고리 소계: 생산·이월·로스·폐기를 제품별 표시 기준과 동일하게 집계 -->
              <tr class="subtotal-row">
                <td>소계</td>

                <!-- 생산: 해당 날짜 생산 수량 합계 -->
                <td>
                  {{ sum(group.rows, 'production') }}
                </td>

                <!--
                  카테고리 소계의 이월 수량을 표시합니다.
                  재고와 예정이 모두 존재하면 각각 별도의 줄에 표시하여
                  옆의 로스 열을 침범하지 않도록 합니다.
                  기존 집계 기준과 0 표시 조건은 유지합니다.
                -->
                <td>
                  <div v-if="sum(group.rows, 'carryover_in') > 0">
                    재고 {{ sum(group.rows, 'carryover_in') }}
                  </div>

                  <div v-if="sum(group.rows, 'carryover_out') > 0">
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

                <!-- 로스: 기존 기준 유지 -->
                <td>
                  {{ sum(group.rows, 'loss') }}
                </td>

                <!-- 폐기: 실제 처리일이 아닌 원 생산일 기준 합계 -->
                <td>
                  {{ sum(group.rows, 'attributed_waste') }}
                </td>

                <!-- 폐기율: 카테고리 생산량 대비 귀속 폐기량 -->
                <td>
                  {{ groupWasteRate(group.rows) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </v-expand-transition>
    </section>

    <v-empty-state
        v-if="!groupedRows.length"
        :title="emptyProductTitle"
        :text="emptyProductText"
        icon="mdi-bread-slice-outline"
    />
  </section>

  <div class="daily-actions">
    <v-btn variant="text" prepend-icon="mdi-history" @click="openHistory">변경 이력</v-btn>
    <v-spacer />
    <v-btn v-if="daily.closure_status === 'closed'" variant="outlined" :disabled="!canCorrect" @click="correctionOpen=true">마감 수정</v-btn>
    <v-btn v-else variant="flat" :disabled="daily.closure_status === 'store_closed' || !canMutate" @click="previewClose">마감</v-btn>
  </div>
  <ProductionBatchDialog
      v-model="batchOpen"
      :product="selectedProduct"
      :store-id="storeId"
      :work-date="workDate"
      :workers="options.workers || []"
      :zero-reasons="options.zero_reasons || []"
      @saved="handleSaved"
      @error="emit('error', $event)"
  />
  <ProductionFlowDialog
    v-model="flowOpen"
    :product="selectedProduct"
    :store-id="storeId"
    :work-date="workDate"
    :type="flowType"
    :loss-reasons="options.loss_reasons || []"
    :waste-reasons="options.waste_reasons || []"
    @saved="handleSaved"
    @error="emit('error', $event)"
  />
  
  <!-- 생산·재고 통합 관리 -->
  <ProductionInventoryDialog
    v-model="inventoryOpen"
    :product="selectedProduct"
    :stock-sources="selectedProduct?.stock_sources || []"
    :store-id="storeId"
    :work-date="workDate"
    :workers="options.workers || []"
    :zero-reasons="options.zero_reasons || []"
    :can-mutate="canMutate"
    :sections="inventorySections"
    :initial-section="inventoryInitialSection"
    @saved="handleSaved"
    @error="emit('error', $event)"
  />

  <ProductDetailDialog
    v-model="productDetailOpen"
    :product="productDetail"
    :can-manage="false"
    :can-manage-recipe="false"
    :loading="productDetailLoading"
  >
    <template #extra-detail>
      <!-- 선택 날짜의 제품별 생산 및 재고 처리 현황 -->
      <section class="production-product-section">
        <div class="production-product-title">
          선택 날짜 생산 현황
        </div>

        <div class="production-product-date">
          {{ formatWorkDateWithWeekday(workDate) }}
        </div>

        <div class="product-detail-metrics">
          <div
            v-for="metric in selectedProductMetrics"
            :key="metric.label"
          >
            <span>{{ metric.label }}</span>
            <strong>{{ metric.value }}</strong>
          </div>
        </div>
      </section>

      <v-divider />

      <!-- 생산 기록 비교: 선택 날짜 기준 최근 7일 생산량을 조회 전용으로 표시합니다. -->
      <section class="production-product-section">
        <div class="production-product-title">
          생산 기록 비교
        </div>

        <!-- 선택 날짜, 전날, 증감량을 각각 구분하여 표시합니다. -->
        <div class="production-comparison-cards">
          <div class="production-comparison-card">
            <span>선택 날짜</span>
            <strong>{{ productionComparison.currentText }}</strong>
          </div>

          <div class="production-comparison-card">
            <span>전날</span>
            <strong>{{ productionComparison.previousText }}</strong>
          </div>

          <div class="production-comparison-card">
            <span>증감</span>
            <strong>{{ productionComparison.differenceText }}</strong>
          </div>
        </div>

        <!-- 차트와 평균은 같은 생산 기록 비교 섹션에 포함합니다. -->
        <div class="production-comparison-chart-heading">
          <strong>최근 7일 생산량</strong>
          <span>
            평균
            {{
              productionSevenDayAverage === null
                ? '미확인'
                : `${Number(productionSevenDayAverage.toFixed(1))}개`
            }}
          </span>
        </div>

        <div
          v-if="productionComparisonRows.length"
          class="production-comparison-chart"
          role="img"
          aria-label="최근 7일 날짜별 생산량 세로 막대차트"
        >
          <div
            v-for="item in productionComparisonRows"
            :key="item.date"
            class="production-comparison-chart-item"
          >
            <span class="production-comparison-chart-value">
              {{ item.quantity === null ? '-' : item.quantity }}
            </span>

            <div class="production-comparison-chart-track">
              <div
                v-if="item.quantity !== null"
                class="production-comparison-chart-bar"
                :style="{
                  height: `${productionChartHeight(item.quantity)}%`,
                }"
              />
            </div>

            <span class="production-comparison-chart-date">
              {{ item.label }}
            </span>
          </div>
        </div>

        <p v-else class="production-comparison-empty">
          조회할 생산 기록이 없습니다.
        </p>

        <p class="production-comparison-note">
          미확인 날짜는 '-'로 표시하며 평균에서 제외합니다.
          생산 없음(0개)으로 확인한 날짜는 평균에 포함합니다.
        </p>
      </section>

      <v-divider />

      <!-- 기존 제품 현황 분석 문구 유지 -->
      <section class="production-product-section">
        <div class="production-product-title">
          제품 현황
        </div>

        <p class="product-analysis-copy">
          {{ selectedProductAnalysis }}
        </p>
      </section>

      <!-- 등록된 레시피가 없는 경우 기존 안내 유지 -->
      <template v-if="!productDetail?.recipes?.length">
        <v-divider />

        <section
          class="production-product-section empty-inline-state"
        >
          <v-icon
            icon="mdi-book-open-variant-outline"
            size="20"
          />

          <div>
            <strong>등록된 레시피가 없습니다.</strong>
            <span>
              제품 관리에서 레시피를 등록하면
              여기에서 확인할 수 있습니다.
            </span>
          </div>
        </section>
      </template>
    </template>
  </ProductDetailDialog>

  <v-dialog
      v-model="detailOpen"
      max-width="680"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">{{ detailTitle }}</v-card-title>
      <v-card-text class="app-dialog-body">
        <div class="summary-detail-hero">
          <span>총 {{ detailMetricTitle }}</span>
          <strong>{{ detailMetricValue }}</strong>
          <small>{{ detailInsight }}</small>
        </div>
        <div class="summary-detail-facts">
          <div><span>기록된 제품</span><strong>{{ detailRecordedCount }}개</strong></div>
          <div><span>확인 현황</span><strong>{{ detailConfirmedCount }} / {{ activeRows.length }}</strong></div>
          <div><span>미확인</span><strong>{{ Math.max(0, activeRows.length - detailConfirmedCount) }}개</strong></div>
          <div><span>날짜</span><strong>{{ shortDateLabel(workDate) }}</strong></div>
        </div>
        <div v-if="detailMetricKey === 'waste_rate'" class="waste-analysis-note">
          <strong>폐기율 안내</strong>
          <span>폐기율은 해당 날짜에 만든 제품의 폐기만 계산합니다. 이월된 제품을 폐기하면 생산한 날짜의 폐기로 반영됩니다.</span>
          <span><b>계산식</b> · 해당 생산일의 폐기 수량 ÷ 해당 생산일의 생산 수량 × 100</span>
          <span><b>예시</b> · 10/3에 10개 생산한 제품 중 이월 재고 2개를 10/5에 폐기하면 10/3 폐기율은 2 ÷ 10 × 100 = 20%입니다.</span>
        </div>
        <v-divider class="dialog-full-divider" />

        <div class="summary-detail-heading">제품별 {{ detailMetricTitle }}</div>
        <div v-if="detailRows.length" class="summary-detail-list">
          <button v-for="row in detailRows" :key="row.id" type="button" class="summary-detail-row" @click="openMetricProduct(row)">
            <span>
              <b>{{ row.name }}</b>
              <small>{{ row.share }} · {{ row.confirmed ? '확인 완료' : '미확인' }}</small>
              <small v-if="row.reasonSummary">{{ row.reasonSummary }}</small>
            </span>
            <strong>{{ row.value }}</strong>
          </button>
        </div>
        <div v-else class="summary-detail-empty">
          <strong>{{ detailEmptyTitle }}</strong>
          <span>{{ detailEmptyText }}</span>
        </div>

      </v-card-text>
      <v-card-actions class="app-dialog-footer history-footer">
        <v-btn variant="text" @click="detailOpen=false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog v-model="stockFlowOpen" max-width="620" persistent>
    <v-card class="app-dialog-card" rounded="lg">
      <v-card-title class="app-dialog-header">재고 흐름 - {{ stockFlowProduct?.name || '-' }}</v-card-title>
      <v-card-text class="app-dialog-body">
        <!-- 재고 현황: 기존 수량과 계산 기준 유지 -->
        <div class="production-dialog-overview stock-flow-overview">
          <div class="stock-flow-summary">
            <span class="stock-flow-summary__label">오늘 생산</span>
            <strong class="stock-flow-summary__value">
              {{ stockFlowProduct?.production || 0 }}
              <small>개</small>
            </strong>
          </div>

          <div class="stock-flow-summary">
            <span class="stock-flow-summary__label">이월 재고</span>
            <strong class="stock-flow-summary__value">
              {{ stockFlowProduct?.carryover_in || 0 }}
              <small>개</small>
            </strong>
          </div>

          <div class="stock-flow-summary">
            <span class="stock-flow-summary__label">해당 날짜 폐기</span>
            <strong class="stock-flow-summary__value">
              {{ stockFlowProduct?.operational_waste || 0 }}
              <small>개</small>
            </strong>
          </div>
        </div>

        <!-- 재고 처리 기록: 기존 목록과 표시 조건 유지 -->
        <section class="stock-flow-history">
          <div class="stock-flow-history__heading">
            <h3 class="production-dialog-section-title">
              재고 처리 내역
            </h3>
            <span class="stock-flow-history__count">
              {{ stockFlowEvents.length }}건
            </span>
          </div>

          <div
            v-for="item in stockFlowEvents"
            :key="item.key"
            class="stock-flow-event stock-flow-history__item"
          >
            <div class="stock-flow-history__item-header">
              <strong>{{ item.title }}</strong>
              <span class="stock-flow-history__quantity">
                {{ item.quantity }}개
              </span>
            </div>

            <small
              v-if="item.origin"
              class="stock-flow-history__meta"
            >
              생산일 {{ item.origin }}
            </small>

            <small
              v-if="item.reason"
              class="stock-flow-history__reason"
            >
              {{ item.reason }}
            </small>
          </div>

          <!-- 기존 빈 목록 안내 유지 -->
          <div
            v-if="!stockFlowEvents.length"
            class="production-friendly-empty"
          >
            <v-icon icon="mdi-clipboard-text-outline" />
            <span>아직 처리된 재고 기록이 없습니다.</span>
          </div>
        </section>

        <!-- 기존 폐기 집계 기준 안내 유지 -->
        <div class="production-dialog-guide stock-flow-guide">
          <v-icon
            icon="mdi-information-outline"
            size="18"
            aria-hidden="true"
          />
          <span>
            이월 재고를 폐기한 수량은 해당 날짜 폐기로 포함됩니다.
            생산일별 폐기율은 최초 생산일 기준으로 계산됩니다.
          </span>
        </div>
      </v-card-text>
      <v-card-actions class="app-dialog-footer history-footer"><v-btn variant="text" @click="stockFlowOpen = false">닫기</v-btn></v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog
      v-model="wasteGuideOpen"
      max-width="560"
      persistent
  >
    <v-card rounded="lg" class="app-dialog-card">
      <v-card-title class="app-dialog-header">폐기율 안내</v-card-title>
      <v-card-text class="app-dialog-body waste-guide-dialog">
        <p>폐기율은 해당 날짜에 만든 제품의 폐기만 계산합니다. 이월된 제품을 폐기하면 처음 생산한 날짜의 폐기로 반영됩니다.</p>
        <div><strong>계산식</strong><span>해당 생산일의 폐기 수량 ÷ 해당 생산일의 생산 수량 × 100</span></div>
        <div><strong>예시</strong><span>10/3에 10개 생산한 제품 중 이월 재고 2개를 10/5에 폐기하면 10/3 폐기율은 2 ÷ 10 × 100 = 20%입니다.</span></div>
      </v-card-text>
      <v-card-actions class="app-dialog-footer history-footer">
        <v-btn variant="text" @click="wasteGuideOpen = false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog
      v-model="historyOpen"
      max-width="720"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">변경 이력</v-card-title>
      <v-card-text class="app-dialog-body history-body">
        <div class="history-context">{{ formatKoreanDate(workDate) }}의 생산·이월·로스·폐기 변경 기록입니다.</div>
        <v-divider class="app-section-divider" />
        <div v-if="historyLogs.length" ref="historyListRef" class="history-list">
          <article
            v-for="log in pagedHistoryLogs"
            :key="log.id"
            class="history-item"
          >
            <div class="history-item-head">
              <strong>
                <template v-if="log.product_name">
                  {{ log.product_name }} ·
                </template>
                {{ historyDescription(log) }}
              </strong>

              <span class="history-action">
                {{ historyActionLabel(log.action) }}
              </span>
            </div>

            <div
              v-if="historyQuantityText(log)"
              class="history-quantity"
            >
              {{ historyQuantityText(log) }}
            </div>

            <div class="history-meta">
              {{ log.user?.name || '-' }} ·
              {{ new Date(log.created_at).toLocaleString('ko-KR') }}
            </div>
          </article>
        </div>
        <v-empty-state
            v-else
            title="변경 이력이 없습니다."
            icon="mdi-history"
        />
        <v-pagination
            v-if="historyPageCount > 1"
            v-model="historyPage"
            :length="historyPageCount"
            :total-visible="5"
            density="compact"
            class="history-pagination"
            @update:model-value="scrollHistoryTop"
        />
        <div v-if="historyLogs.length" class="history-page-count">{{ historyPage }} / {{ historyPageCount }} 페이지 · 페이지당 10개</div>
      </v-card-text>
      <v-card-actions class="app-dialog-footer history-footer justify-start">
        <v-btn variant="text" @click="historyOpen=false">닫기</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog
      v-model="closeOpen"
      max-width="720"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">마감 최종확인</v-card-title>
      <v-card-text class="app-dialog-body">
        <div class="app-supporting-text text-medium-emphasis mb-3">저장 전에 생산·이월·로스·폐기와 미확인 항목을 다시 확인합니다.</div>
        <div class="daily-metrics mb-4">
          <div v-for="metric in closeMetrics" :key="metric.key" class="metric-item static">
            <span>{{ metric.title }}</span>
            <strong>{{ metric.value }}</strong>
          </div>
        </div>
        <v-alert
            v-if="closePreview && !closePreview.can_close"
            type="warning"
            variant="tonal"
            density="compact"
            class="app-supporting-alert"
        >
          <template v-if="closePreview.stock_issues?.length">
            <strong>재고 흐름을 확인해 주세요.</strong>
            <div v-for="issue in closePreview.stock_issues" :key="issue">{{ issue }}</div>
          </template>
          <template v-else>
            아직 확인할 제품이 {{ closePreview.incomplete?.length || 0 }}개 있습니다. 아래에서 필요한 항목을 바로 입력할 수 있습니다.
          </template>
        </v-alert>
        <div class="close-progress-copy">
          확인 {{ closeCompleteCount }} / {{ closeRows.length }} · 확인 필요 {{ closeIncompleteCount }}개
        </div>
        <div
          v-if="closeRows.length"
          ref="closeListRef"
          class="close-check-list mt-3"
        >
          <article
            v-for="row in pagedCloseRows"
            :key="row.id"
            class="close-check-item"
          >
            <div class="close-check-copy">
              <strong>{{ row.name }}</strong>
              <span>{{ row.complete ? '확인 완료' : missingReasonText(row) }}</span>
            </div>

            <div class="close-product-values">
              <!-- 생산은 기존 확인 상태와 입력 동작을 유지합니다. -->
              <button
                class="production"
                type="button"
                @click="openProduction(row)"
              >
                <span>생산</span>
                <strong>
                  {{ row.production_confirmed ? `${row.production}` : '-' }}
                </strong>
              </button>

              <!-- 이월: 기존 재고와 다음 날 이월 예정 수량의 합계만 표시 -->
              <button
                type="button"
                @click="openFlow(row, 'carryover')"
              >
                <span>이월</span>
                <strong>
                  {{
                    Number(row.carryover_in || 0) > 0 ||
                    Number(row.carryover_out || 0) > 0
                      ? Number(row.carryover_in || 0) +
                        Number(row.carryover_out || 0)
                      : row.disposition_confirmed ? 0 : '-'
                  }}
                </strong>
              </button>

              <!-- 로스는 기존 수량과 확인 상태를 유지합니다. -->
              <button
                type="button"
                @click="openFlow(row, 'loss')"
              >
                <span>로스</span>
                <strong>
                  {{ row.loss_confirmed ? `${row.loss}` : '-' }}
                </strong>
              </button>

              <!-- 폐기는 원 생산일 귀속 수량을 표시하고 실제 폐기 입력 기능은 유지합니다. -->
              <button
                class="waste"
                type="button"
                @click="openFlow(row, 'waste')"
              >
                <span>폐기</span>
                <strong>
                  {{
                    row.waste_confirmed || Number(row.attributed_waste || 0) > 0
                      ? `${Number(row.attributed_waste || 0)}`
                      : '-'
                  }}
                </strong>
              </button>
            </div>

            <div class="close-check-actions">
              <v-btn
                size="small"
                variant="text"
                @click="openProduct(row)"
              >
                제품 정보
              </v-btn>
            </div>
          </article>
        </div>
        <v-pagination
            v-if="closePageCount > 1"
            v-model="closePage"
            :length="closePageCount"
            :total-visible="5"
            density="compact"
            class="close-pagination"
            @update:model-value="scrollCloseTop"
        />
        <div v-if="closePreview?.can_close" class="close-ready mt-3">모든 제품 확인이 완료되었습니다. 입력 수량을 마지막으로 확인한 뒤 마감해 주세요.</div>
      </v-card-text>
      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" @click="closeOpen=false">취소</v-btn>
        <v-spacer />
        <v-btn variant="flat" :disabled="!closePreview?.can_close" @click="confirmCloseOpen=true">마감</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <v-dialog
      v-model="correctionOpen"
      max-width="560"
      persistent
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header">마감 후 수정</v-card-title>
      <v-card-text class="app-dialog-body">
        <div class="app-supporting-text text-medium-emphasis mb-3">과거 기록을 수정하면 통계와 현재 분석이 다시 계산됩니다. 당시 추천 스냅샷은 변경하지 않습니다.</div>
        <v-textarea
            v-model="correctionReason"
            label="수정 사유"
            variant="outlined"
            rows="3"
        />
      </v-card-text>
      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" @click="correctionOpen=false">취소</v-btn>
        <v-spacer/>
        <v-btn variant="flat" :disabled="correctionReason.trim().length < 2" @click="correctionConfirmOpen=true">수정 시작</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <ConfirmDialog
      v-model="correctionConfirmOpen"
      title="마감 후 수정"
      message="마감된 기록의 수정을 시작하시겠습니까? 연결된 이월 기록이 있으면 영향 날짜도 함께 확인해야 합니다."
      @confirm="openCorrection"
  />
  <ConfirmDialog
      v-model="bulkZeroConfirmOpen"
      :title="`${bulkZeroLabel} 일괄 확인`"
      :message="`미확인 제품 ${bulkZeroCount}개를 없음(0)으로 확인합니다. 기존에 입력된 기록은 변경하지 않습니다.`"
      :loading="bulkZeroLoading"
      @confirm="bulkZero"
  />
  <ConfirmDialog
      v-model="confirmCloseOpen"
      title="마감 최종확인"
      message="현재 확인한 내용으로 하루 업무를 마감하시겠습니까? 마감 후 일반 수정은 제한됩니다."
      :loading="closing"
      @confirm="closeDay"
  />
</div>
</template>

<script setup>
import {
    computed,
    ref,
    watch,
} from 'vue';

import ConfirmDialog from '../common/ConfirmDialog.vue';
import ProductDetailDialog from '../product/ProductDetailDialog.vue';
import ProductionBatchDialog from './ProductionBatchDialog.vue';
import ProductionFlowDialog from './ProductionFlowDialog.vue';
// 제품별 생산 및 재고 관리를 위한 통합 다이얼로그입니다.
import ProductionInventoryDialog from './ProductionInventoryDialog.vue';

import {
    addLocalDays,
    formatKoreanDate,
    toLocalDateString,
} from '../../utils/localDate';
import { productionReasonLabel } from '../../utils/productionReasons';
const props = defineProps({
  daily: {
    type: Object,
    default: () => ({
      rows: [],
      totals: {},
    }),
  },
  options: {
    type: Object,
    default: () => ({}),
  },
  storeId: Number,
  workDate: String,
  canMutate: Boolean,
  canCorrect: Boolean,
});
const emit = defineEmits([
  'update:workDate',
  'reload',
  'replaceDaily',
  'error',
  'success',
]);
const today = toLocalDateString();
const dateMenu = ref(false);
const filter = ref('all');

const search = ref('');
const missingType = ref(null);

// 동일한 점포·날짜의 중복 요청을 공유합니다.
let historyRequest = null;
let historyRequestKey = null;

/**
 * 최근 입력과 변경 이력이 동일한 API를 사용할 때
 * 진행 중인 요청을 중복 실행하지 않습니다.
 */
function fetchHistory(storeId, workDate, force = false) {
  const key = `${storeId}:${workDate}`;

  if (!force && historyRequest && historyRequestKey === key) {
    return historyRequest;
  }

  const request = window.axios.get(
    '/tillwhite/api/production-management/history',
    {
      params: {
        store_id: storeId,
        work_date: workDate
      }
    }
  ).then(({ data }) => data.logs || []);

  historyRequest = request;
  historyRequestKey = key;

  // 완료된 요청은 보관하지 않아 이후 조회 시 최신 기록을 읽습니다.
  request.finally(() => {
    if (historyRequest === request) {
      historyRequest = null;
      historyRequestKey = null;
    }
  }).catch(() => {});

  return request;
}

const collapsed = ref(new Set());
const selectedProduct = ref(null);
const productDetail = ref(null);
const productDetailOpen = ref(false);
const productDetailLoading = ref(false);

// 제품 상세 다이얼로그에 표시할 최근 7일 생산 기록입니다.
// quantity가 null이면 미확인, 0이면 생산 없음으로 확인된 날짜입니다.
const productProductionHistory = ref([]);

const batchOpen = ref(false);
const flowOpen = ref(false);
const flowType = ref('carryover');

// 통합 재고 관리 다이얼로그의 열림 상태를 관리합니다.
const inventoryOpen = ref(false);

// 통합 다이얼로그를 열 때 처음 표시할 업무 유형입니다.
const inventoryInitialSection = ref('');

/**
 * 통합 재고 관리 화면의 업무 메뉴를 정의합니다.
 *
 * 기존 생산·이월·로스·폐기 업무 유형을 그대로 사용하며,
 * 화면에 표시할 명칭과 순서를 한곳에서 관리합니다.
 *
 * 재이월은 이월 업무에서 재고 출처를 선택하여 처리하고,
 * 폐기율은 저장 기능이 없는 조회 전용 항목으로 관리합니다.
 */
const inventorySections = [
  { value: 'production', title: '생산' },
  { value: 'carryover', title: '이월' },
  { value: 'loss', title: '로스' },
  { value: 'waste', title: '폐기' },
  { value: 'waste_rate', title: '폐기율' },
];

const detailOpen = ref(false);
const wasteGuideOpen = ref(false);
const stockFlowOpen = ref(false);
const stockFlowProduct = ref(null);
const detailTitle = ref('');
const detailMetricKey = ref('production');
const detailSelectedRow = ref(null);
const historyOpen = ref(false);
const historyLogs = ref([]);
const historyPage = ref(1);
const historyListRef = ref(null);
const expandedRows = ref(new Set());
const correctionOpen = ref(false);
const correctionConfirmOpen = ref(false);
const correctionReason = ref('');
const closeOpen = ref(false);
const confirmCloseOpen = ref(false);
const bulkZeroConfirmOpen = ref(false);
const bulkZeroType = ref('loss');
const bulkZeroLoading = ref(false);
const closePreview = ref(null);
const closing = ref(false);
const closePage = ref(1);
const closeListRef = ref(null);
const closePageSize = 10;

const activeRows = computed(() => (props.daily.rows || []).filter((row) => row.is_active));

/**
 * 일일 생산 현황 응답에 포함된 최근 입력 제품을 표시합니다.
 *
 * 별도 API 요청 없이 현재 화면의 제품 데이터에서 찾으므로
 * 제품별 현황과 최근 입력이 함께 렌더링됩니다.
 */
const recentProducts = computed(() => {
  const rowsById = new Map(
    activeRows.value.map((row) => [Number(row.id), row])
  );

  return (props.daily.recent_product_ids || [])
    .map((id) => rowsById.get(Number(id)))
    .filter(Boolean)
    .slice(0, 5);
});

/**
 * 최근 입력 제품을 선택하면 기존 검색 기능으로 해당 제품을 찾습니다.
 * 다른 필터에 가려지지 않도록 필터를 초기화하고 카테고리를 펼칩니다.
 */
function selectRecentProduct(row) {
  search.value = row.name;
  filter.value = 'all';
  missingType.value = null;

  const next = new Set(collapsed.value);
  next.delete(row.category_name);
  collapsed.value = next;
}

// 마감 최종확인은 미확인 제품을 먼저 보여주고 페이지당 10개로 고정합니다.
const closeRows = computed(() => {
  const rows = closePreview.value?.daily?.rows || [];
  return rows
    .filter((row) => row.is_active)
    .sort((a, b) => Number(a.complete) - Number(b.complete));
});
const closeIncompleteCount = computed(() => closeRows.value.filter((row) => !row.complete).length);
const closeCompleteCount = computed(() => closeRows.value.length - closeIncompleteCount.value);
const closePageCount = computed(() => Math.max(1, Math.ceil(closeRows.value.length / closePageSize)));
const pagedCloseRows = computed(() => {
  const start = (closePage.value - 1) * closePageSize;
  return closeRows.value.slice(start, start + closePageSize);
});
const historyPageSize = 10;
const historyPageCount = computed(() => Math.max(1, Math.ceil(historyLogs.value.length / historyPageSize)));
const pagedHistoryLogs = computed(() => {
  const start = (historyPage.value - 1) * historyPageSize;
  return historyLogs.value.slice(start, start + historyPageSize);
});
const missingLossCount = computed(() => activeRows.value.filter((row) => !row.loss_confirmed).length);
const missingWasteCount = computed(() => activeRows.value.filter((row) => !row.waste_confirmed).length);
const missingProductionCount = computed(() => activeRows.value.filter((row) => !row.production_confirmed).length);
const missingDispositionCount = computed(() => activeRows.value.filter((row) => !row.disposition_confirmed).length);
const bulkZeroItem = computed(() => missingItems.value.find((item) => item.key === bulkZeroType.value));
const bulkZeroLabel = computed(() => bulkZeroType.value === 'all' ? '전체 미확인 항목' : (bulkZeroItem.value?.label || '기록'));
const bulkZeroCount = computed(() => bulkZeroType.value === 'all'
  ? missingItems.value.reduce((sum, item) => sum + item.count, 0)
  : (bulkZeroItem.value?.count || 0));
const progressPercent = computed(() => {
  const required = Number(props.daily.required_count || 0);
  return required ? Math.round((Number(props.daily.complete_count || 0) / required) * 100) : 100;
});
const missingItems = computed(() => [
  { key: 'production', label: '생산', count: missingProductionCount.value },
  { key: 'carryover', label: '이월', count: missingDispositionCount.value },
  { key: 'loss', label: '로스', count: missingLossCount.value },
  { key: 'waste', label: '폐기', count: missingWasteCount.value },
]);
const hasMissingItems = computed(() => missingItems.value.some((item) => item.count > 0));
const missingSummaryText = computed(() => (
  missingItems.value.some((item) => item.count > 0)
    ? '확인이 필요한 기록을 선택하면 해당 제품만 빠르게 확인할 수 있습니다.'
    : '모든 제품의 필수 기록이 확인되었습니다.'
));
const detailMetricTitle = computed(() => metrics.value.find((metric) => metric.key === detailMetricKey.value)?.title || '상세');
const detailMetricValue = computed(() => metrics.value.find((metric) => metric.key === detailMetricKey.value)?.value ?? '-');
/**
 * 요약 상세 다이얼로그의 제품별 수량, 폐기율, 비중, 확인 상태 및 사유를 구성합니다.
 * 폐기는 원 생산일 귀속 수량을 사용하며, 기존 필터와 정렬 기준은 유지합니다.
 */
const detailRows = computed(() => {
  const metricKey = detailMetricKey.value;

  // 이월은 유입 재고, 폐기는 원 생산일 귀속 폐기량을 참조합니다.
  const key =
    metricKey === 'carryover'
      ? 'carryover_in'
      : metricKey === 'waste'
        ? 'attributed_waste'
        : metricKey;

  // 폐기율의 전체 대비 비중은 제품별 귀속 폐기 수량의 합계를 기준으로 계산합니다.
  const total =
    metricKey === 'waste_rate'
      ? activeRows.value.reduce(
          (sum, row) => sum + Number(row.attributed_waste || 0),
          0
        )
      : activeRows.value.reduce(
          (sum, row) => sum + Number(row[key] || 0),
          0
        );

  return activeRows.value
    .map((row) => {
      // 폐기율은 서버 계산값을 사용하고 나머지 항목은 해당 수량을 사용합니다.
      const rawValue =
        metricKey === 'waste_rate'
          ? Number(row.waste_rate || 0)
          : Number(row[key] || 0);

      // 폐기율 상세에서는 비율 자체가 아닌 귀속 폐기 수량으로 비중을 계산합니다.
      const shareBase =
        metricKey === 'waste_rate'
          ? Number(row.attributed_waste || 0)
          : rawValue;

      return {
        id: row.id,
        name: row.name,
        source: row,
        rawValue,
        value:
          metricKey === 'waste_rate'
            ? `${rawValue}%`
            : rawValue,
        share:
          total > 0 && shareBase > 0
            ? `전체의 ${((shareBase / total) * 100).toFixed(1)}%`
            : '기록 확인',
        confirmed: Boolean(row[detailConfirmationField.value]),
        reasonSummary:
          metricKey === 'waste_rate' || metricKey === 'waste'
            ? reasonSummary(row.waste_details, '폐기 사유')
            : metricKey === 'loss'
              ? reasonSummary(row.loss_details, '로스 사유')
              : '',
      };
    })
    .filter((row) => row.rawValue > 0)
    .sort((a, b) => b.rawValue - a.rawValue);
});
const detailRecordedCount = computed(() => detailRows.value.length);
const detailConfirmationField = computed(() => ({
  production: 'production_confirmed',
  carryover: 'disposition_confirmed',
  loss: 'loss_confirmed',
  waste: 'waste_confirmed',
  waste_rate: 'waste_confirmed',
}[detailMetricKey.value]));
const detailConfirmedCount = computed(() => activeRows.value.filter((row) => row[detailConfirmationField.value]).length);

/**
 * 요약 상세에서 가장 높은 값을 가진 제품을 안내합니다.
 *
 * - 기존 제품별 집계 및 정렬 기준을 그대로 사용합니다.
 * - 최고값이 같은 제품은 누락하지 않고 모두 표시합니다.
 * - 단독 최고값과 공동 최고값의 안내 문구를 구분합니다.
 * - 생산·이월·로스·폐기·폐기율에 동일하게 적용됩니다.
 */
const detailInsight = computed(() => {
  const rows = detailRows.value;

  // 기존 기록이 없는 경우의 안내 문구를 유지합니다.
  if (!rows.length) {
    return `${detailConfirmedCount.value}개 제품이 확인을 완료했습니다.`;
  }

  // detailRows는 기존 로직에서 수량 또는 비율 내림차순으로 정렬됩니다.
  const highestValue = rows[0].rawValue;

  // 최고 수량 또는 최고 폐기율과 동일한 모든 제품을 찾습니다.
  const topRows = rows.filter(
    (row) => row.rawValue === highestValue
  );

  // 기존에 표시하던 값과 단위를 그대로 사용합니다.
  const displayValue = rows[0].value;

  // 단독 최고값일 때는 기존 안내 문구를 유지합니다.
  if (topRows.length === 1) {
    return `${topRows[0].name}이(가) 가장 높습니다 · ${displayValue}`;
  }

  // 공동 최고값인 제품을 모두 표시합니다.
  const productNames = topRows
    .map((row) => row.name)
    .join(', ');

  return `${productNames}이(가) 공동으로 가장 높습니다 · ${displayValue}`;
});

const detailEmptyTitle = computed(() => `이 날짜에는 ${detailMetricTitle.value} 기록이 없습니다.`);
const detailEmptyText = computed(() => detailConfirmedCount.value === activeRows.value.length
  ? '모든 제품이 없음(0)으로 확인된 상태입니다.'
  : `아직 확인하지 않은 제품이 ${activeRows.value.length - detailConfirmedCount.value}개 있습니다.`);

/**
 * 일일 요약은 기존 생산일 기준 폐기 집계를 유지합니다.
 * 이월 재고 폐기는 해당 제품의 최초 생산일에 귀속됩니다.
 */
const metrics = computed(() => [
  {
    key: 'production',
    title: '생산',
    value: Number(props.daily.totals?.production || 0),
  },
  {
    key: 'carryover',
    title: '이월',
    value:
      Number(props.daily.totals?.carryover || 0) +
      Number(props.daily.totals?.carryover_out || 0),
  },
  {
    key: 'loss',
    title: '로스',
    value: Number(props.daily.totals?.loss || 0),
  },
  {
    key: 'waste',
    title: '폐기',
    // 최초 생산일에 귀속된 폐기량입니다.
    value: Number(props.daily.totals?.attributed_waste || 0),
  },
  {
    key: 'waste_rate',
    title: '폐기율',
    // 서버에서 계산한 생산일 기준 폐기율을 사용합니다.
    value:
      props.daily.totals?.waste_rate == null
        ? '-'
        : `${props.daily.totals.waste_rate}%`,
  },
]);

/**
 * 선택 날짜에 실제 처리한 폐기를 생산일별로 구분합니다.
 * 이월 재고 폐기가 없다면 빈 배열을 반환합니다.
 */
function wasteDetailGroups(row, workDate) {
  const details = row?.operational_waste_details || [];

  const current = [];
  const carryover = [];

  for (const detail of details) {
    const quantity = Number(detail.quantity || 0);

    if (quantity <= 0) continue;

    // 최초 생산일과 선택 날짜를 비교합니다.
    if (detail.origin_production_date === workDate) {
      current.push(detail);
    } else {
      carryover.push(detail);
    }
  }

  return {
    current,
    carryover,
    hasCarryover: carryover.length > 0,
  };
}

/**
 * 마감 최종확인은 서버의 최신 검증 결과를 사용합니다.
 * 폐기량과 폐기율은 최초 생산일 기준을 유지합니다.
 */
const closeMetrics = computed(() => {
  const daily = closePreview.value?.daily || props.daily;
  const totals = daily?.totals || {};

  return [
    {
      key: 'production',
      title: '생산',
      value: Number(totals.production || 0),
    },
    {
      key: 'carryover',
      title: '이월',
      value:
        Number(totals.carryover || 0) +
        Number(totals.carryover_out || 0),
    },
    {
      key: 'loss',
      title: '로스',
      value: Number(totals.loss || 0),
    },
    {
      key: 'waste',
      title: '폐기',
      // 최초 생산일에 귀속된 폐기량입니다.
      value: Number(totals.attributed_waste || 0),
    },
    {
      key: 'waste_rate',
      title: '폐기율',
      value:
        totals.waste_rate == null
          ? '-'
          : `${totals.waste_rate}%`,
    },
  ];
});

// 제품 검색과 미확인 항목, 기록 상태 및 로스·폐기 발생 조건을 적용합니다.
const filteredRows = computed(() => (props.daily.rows || []).filter((row) => {
  const q = search.value?.trim().toLocaleLowerCase('ko-KR');

  if (q && !row.name.toLocaleLowerCase('ko-KR').includes(q)) {
    return false;
  }

  if (missingType.value) {
    const field = {
      production: 'production_confirmed',
      carryover: 'disposition_confirmed',
      loss: 'loss_confirmed',
      waste: 'waste_confirmed',
    }[missingType.value];

    if (field && row[field]) {
      return false;
    }
  }

  if (filter.value === 'missing') {
    return !row.complete;
  }

  // 로스는 기존 기준을 유지하고 폐기는 원 생산일에 귀속된 수량으로 판단합니다.
  if (filter.value === 'occurred') {
    return Number(row.loss || 0) > 0 || Number(row.attributed_waste || 0) > 0;
  }

  return true;
}));

// 다음 미확인 제품은 기존처럼 활성 제품 중 가장 먼저 발견된 제품을 선택합니다.
const nextMissingRow = computed(() =>
  activeRows.value.find((row) => !row.complete) || null
);

const filterResultLabel = computed(() => {
  if (search.value?.trim()) return `검색 결과 ${filteredRows.value.length}개`;
  if (missingType.value) {
    const label = missingItems.value.find((item) => item.key === missingType.value)?.label || '기록';
    return `${label} 미확인 · ${filteredRows.value.length}개`;
  }
  if (filter.value === 'missing') return `확인이 필요한 제품 ${filteredRows.value.length}개`;
  if (filter.value === 'occurred') return `로스·폐기가 발생한 제품 ${filteredRows.value.length}개`;
  return `전체 제품 ${filteredRows.value.length}개`;
});
const emptyProductTitle = computed(() => search.value?.trim() ? '검색 결과가 없습니다.' : '표시할 제품이 없습니다.');
const emptyProductText = computed(() => missingType.value ? '선택한 항목은 모두 확인되었습니다. 전체 목록으로 돌아가 다른 기록을 확인할 수 있습니다.' : '검색어나 필터를 변경해 주세요.');
const groupedRows = computed(() => {
  const map = new Map();

  for (const row of filteredRows.value) {
    if (!map.has(row.category_name)) {
      map.set(row.category_name, []);
    }

    map.get(row.category_name).push(row);
  }

  return [...map.entries()].map(([name, rows]) => ({
    name,
    rows,
    allRows: (props.daily.rows || []).filter((row) => row.category_name === name),
  }));
});
const progressText = computed(() => {
  const required = Number(props.daily.required_count || 0);
  const complete = Number(props.daily.complete_count || 0);
  const remaining = Math.max(0, required - complete);
  return remaining > 0
    ? `전체 ${required}개 중 ${remaining}개 제품의 확인이 필요합니다.`
    : `전체 ${required}개 제품의 기록이 확인되었습니다.`;
});


/**
 * 제품 상세정보에 표시할 생산 및 재고 처리 현황을 구성합니다.
 *
 * - 생산·이월·로스는 기존 수량을 유지합니다.
 * - 해당 날짜 폐기는 실제 처리일 기준 수량을 표시합니다.
 * - 이월 재고 폐기는 해당 날짜에 폐기한 이전 생산분만 집계합니다.
 * - 폐기율은 서버에서 계산한 최초 생산일 기준 값을 유지합니다.
 */
const selectedProductMetrics = computed(() => {
  const row = selectedProduct.value;

  if (!row) {
    return [];
  }

  return [
    {
      label: '생산',
      value: row.production,
    },
    {
      label: '이월 재고',
      value: row.carryover_in,
    },
    {
      label: '이월 예정',
      value: row.carryover_out,
    },
    {
      label: '로스',
      value: row.loss,
    },
    {
      // 선택한 날짜에 실제로 폐기한 전체 수량
      label: '해당 날짜 폐기',
      value: row.attributed_waste,
    },
    // {
    //   // 실제 폐기 수량 중 이전 생산일에서 넘어온 재고의 폐기량
    //   label: '이월 재고 폐기',
    //   value: carryoverWaste(row),
    // },
    {
      // 최초 생산일에 귀속된 폐기 수량을 기준으로 계산된 폐기율
      label: '폐기율',
      value: row.waste_rate == null ? '-' : `${row.waste_rate}%`,
    },
  ];
});

/**
 * 선택 날짜 기준 최근 7일 생산 기록입니다.
 *
 * 서버에서 날짜순으로 받은 데이터를 사용합니다.
 * - quantity === null: 생산 기록 미확인
 * - quantity === 0: 생산 없음(0개) 확인
 * - quantity > 0: 실제 생산 수량
 *
 * 기존 생산량이나 폐기율 계산에는 영향을 주지 않습니다.
 */
const productionComparisonRows = computed(() =>
  productProductionHistory.value.map((item) => ({
    date: item.date,
    quantity: item.quantity === null ? null : Number(item.quantity),
    label: item.date.slice(5).replace('-', '/'),
  }))
);

/**
 * 선택 날짜와 전날 생산 수량을 비교합니다.
 *
 * 기록이 확인되지 않은 날짜는 0으로 취급하지 않으며,
 * 증감량도 계산하지 않습니다.
 */
const productionComparison = computed(() => {
  const rows = productionComparisonRows.value;

  const today = rows.find(
    (item) => item.date === props.workDate
  );

  const previousDate = addLocalDays(props.workDate, -1);

  const yesterday = rows.find(
    (item) => item.date === previousDate
  );

  const currentQuantity = today?.quantity ?? null;
  const previousQuantity = yesterday?.quantity ?? null;

  const hasComparison =
    currentQuantity !== null &&
    previousQuantity !== null;

  const difference = hasComparison
    ? currentQuantity - previousQuantity
    : null;

  return {
    currentQuantity,
    previousQuantity,
    difference,

    currentText:
      currentQuantity === null
        ? '미확인'
        : `${currentQuantity}개`,

    previousText:
      previousQuantity === null
        ? '미확인'
        : `${previousQuantity}개`,

    differenceText:
      difference === null
        ? '비교 불가'
        : `${difference > 0 ? '+' : ''}${difference}개`,
  };
});

/**
 * 최근 7일 평균 생산량입니다.
 *
 * 미확인 날짜는 평균에서 제외합니다.
 * 명시적으로 0개를 확인한 날짜는 평균에 포함합니다.
 */
const productionSevenDayAverage = computed(() => {
  const confirmedRows = productionComparisonRows.value.filter(
    (item) => item.quantity !== null
  );

  if (!confirmedRows.length) {
    return null;
  }

  const total = confirmedRows.reduce(
    (sum, item) => sum + item.quantity,
    0
  );

  return total / confirmedRows.length;
});

/**
 * 세로 막대차트의 높이를 계산할 기준값입니다.
 *
 * 가장 큰 생산량을 100%로 표시합니다.
 * 전부 0개인 경우에도 0으로 나누지 않도록 최소값을 1로 둡니다.
 */
const productionChartMaximum = computed(() =>
  Math.max(
    1,
    ...productionComparisonRows.value
      .filter((item) => item.quantity !== null)
      .map((item) => item.quantity)
  )
);

/**
 * 각 날짜의 세로 막대 높이를 백분율로 반환합니다.
 *
 * 미확인 날짜는 막대를 표시하지 않습니다.
 * 0개 확인 날짜는 높이 0%로 처리하고 별도로 0개라고 표시합니다.
 */
function productionChartHeight(quantity) {
  if (quantity === null) {
    return 0;
  }

  return (quantity / productionChartMaximum.value) * 100;
}

// 제품 현황은 원 생산일 귀속 폐기, 실제 폐기, 로스, 이월 재고 순서로 안내합니다.
const selectedProductAnalysis = computed(() => {
  const row = selectedProduct.value;

  if (!row) {
    return '-';
  }

  if (!row.complete) {
    return `확인이 필요한 항목이 있습니다. ${missingReasonText(row)}`;
  }

  // 원 생산일 귀속 폐기량과 실제 처리일의 폐기량을 구분합니다.
  if (Number(row.attributed_waste || 0) > 0) {
    return `이 날짜 생산분의 폐기 ${row.attributed_waste}개가 반영되어 폐기율은 ${row.waste_rate ?? 0}%입니다.${
      Number(row.operational_waste || 0) > 0
        ? ` 해당 날짜 실제 폐기는 ${row.operational_waste}개이며, 이월 재고 폐기는 ${carryoverWaste(row)}개입니다.`
        : ''
    }`;
  }

  // 원 생산일 귀속 폐기가 없어도 실제 폐기 기록이 있다면 안내합니다.
  if (Number(row.operational_waste || 0) > 0) {
    return `해당 날짜 실제 폐기는 ${row.operational_waste}개이며, 이월 재고 폐기는 ${carryoverWaste(row)}개입니다. 이 날짜 생산분에 귀속된 폐기는 없습니다.`;
  }

  if (row.loss > 0) {
    return `로스 ${row.loss}개가 기록되어 있습니다. 사유와 수량을 확인해 주세요.`;
  }

  if (row.carryover_in > 0) {
    return `이월 재고 ${row.carryover_in}개가 있습니다. 원 생산일별 재고를 확인할 수 있습니다.`;
  }

  return '특이사항 없이 필수 기록이 모두 확인되었습니다.';
});

// 제품 행의 상세 영역을 열고 닫되 다른 입력 기능과는 독립적으로 유지합니다.
function toggleRowDetail(productId) {
  const next = new Set(expandedRows.value);
  next.has(productId) ? next.delete(productId) : next.add(productId);
  expandedRows.value = next;
}

function isRowExpanded(productId) {
  return expandedRows.value.has(productId);
}

// 실제 폐기 기록 중 최초 생산일이 조회 날짜와 다른 수량만 이월 재고 폐기로 집계합니다.
function carryoverWaste(row) {
  return (row.operational_waste_details || [])
    .filter((item) =>
      item.origin_production_date &&
      item.origin_production_date !== props.workDate
    )
    .reduce(
      (total, item) =>
        total + Number(item.quantity || 0),
      0
    );
}

function openStockFlow(row) {
  stockFlowProduct.value = row;
  stockFlowOpen.value = true;
}

// 재고 흐름 상세에 생산, 이월 재고, 로스, 폐기 및 이월 예정 기록을 구성합니다.
const stockFlowEvents = computed(() => {
  const row = stockFlowProduct.value;

  if (!row) {
    return [];
  }

  const items = [];

  // 수량이 0보다 큰 기록만 상세 목록에 추가합니다.
  const add = (title, quantity, origin = null, reason = null) => {
    if (Number(quantity) > 0) {
      items.push({
        key: `${title}-${items.length}`,
        title,
        quantity,
        origin,
        reason,
      });
    }
  };

  // 선택 날짜에 생산한 수량을 표시합니다.
  add('오늘 생산', row.production);

  // 최초 생산일이 다른 재고만 이월 재고로 표시합니다.
  for (const source of row.stock_sources || []) {
    if (source.origin_production_date !== props.workDate) {
      add(
        '이월 재고',
        source.incoming_quantity || source.quantity || 0,
        source.origin_production_date
      );
    }
  }

  // 실제 처리일 기준 로스 기록과 사유를 표시합니다.
  for (const detail of row.operational_loss_details || []) {
    add(
      '로스',
      detail.quantity,
      detail.origin_production_date,
      productionReasonLabel(detail)
    );
  }

  // 실제 폐기 기록을 최초 생산일에 따라 당일 생산분과 이월 재고로 구분합니다.
  for (const detail of row.operational_waste_details || []) {
    add(
      detail.origin_production_date !== props.workDate
        ? '이월 재고 폐기'
        : '오늘 생산분 폐기',
      detail.quantity,
      detail.origin_production_date,
      productionReasonLabel(detail)
    );
  }

  // 다음 날로 넘길 예정 수량을 표시합니다.
  add('이월 예정', row.carryover_out);

  return items;
});

// 실제 처리일 기준으로 생산, 이월 재고, 로스, 폐기 및 다음 날 이월 흐름을 구성합니다.
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

  // 실제 폐기 수량에서 이월 재고 폐기를 제외해 당일 생산분 폐기를 계산합니다.
  const carryover = carryoverWaste(row);

  if (row.operational_waste > carryover) {
    parts.push(`오늘 생산분 폐기 ${row.operational_waste - carryover}개`);
  }

  if (carryover) {
    parts.push(`이월 재고 폐기 ${carryover}개`);
  }

  if (row.carryover_out) {
    parts.push(`다음날 이월 ${row.carryover_out}개`);
  }

  return parts.join(' → ');
}

// 마감 최종확인 페이지를 바꾸면 제품 카드 목록의 시작점으로 이동합니다.
function scrollCloseTop() {
  closeListRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
}
// 변경 이력 페이지를 바꾸면 목록 시작점으로 돌아가 첫 항목부터 바로 읽을 수 있게 합니다.
function scrollHistoryTop() {
  historyListRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
}
// 사유별 기록을 상세 목록에서 짧게 읽을 수 있도록 요약합니다.
function reasonSummary(details, label) {
  if (!Array.isArray(details) || !details.length) return '';
  const names = details.slice(0, 2).map((item) => productionReasonLabel(item));
  return `${label}: ${names.join(' · ')}${details.length > 2 ? ` 외 ${details.length - 2}건` : ''}`;
}

// 제품별 확인 상태를 실제 미확인 항목 이름으로 설명합니다.
function missingReasonText(row) {
  const missing = [];
  if (!row.production_confirmed) missing.push('생산');
  if (!row.loss_confirmed) missing.push('로스');
  if (!row.waste_confirmed) missing.push('폐기');
  if (!row.disposition_confirmed) missing.push('이월');
  return missing.length ? `${missing.join(' · ')} 확인이 필요합니다.` : '확인이 필요한 항목이 있습니다.';
}

// 현황의 미확인 종류를 누르면 해당 제품만 표에 남깁니다.
function toggleMissingType(type) {
  missingType.value = missingType.value === type ? null : type;
  if (missingType.value) filter.value = 'missing';
}

// 제품 정보에서는 선택 날짜를 요일까지 함께 보여줘 조회 기준을 분명하게 합니다.
function formatWorkDateWithWeekday(date) {
  const parsed = new Date(`${date}T00:00:00`);
  const weekday = new Intl.DateTimeFormat('ko-KR', { weekday: 'long' }).format(parsed);
  return `${String(date).replaceAll('-', '.')} ${weekday}`;
}

// 날짜를 연도 없이 월/일 형식으로 표시합니다.
function shortDateLabel(date) {
  const [, month, day] = String(date).split('-');

  return `${Number(month)}월 ${Number(day)}일`;
}

// 활성 제품 중 모든 필수 기록이 완료된 제품 수를 계산합니다.
function categoryCompleteCount(rows) {
  return rows.filter(
    (row) => row.is_active && row.complete
  ).length;
}

// 활성 제품 수를 기준으로 카테고리 기록 완료율을 계산합니다.
function categoryProgress(rows) {
  const active = rows.filter((row) => row.is_active);

  return active.length
    ? Math.round(
        (categoryCompleteCount(rows) / active.length) * 100
      )
    : 100;
}

// 카테고리의 전체 제품 수와 미완료 제품 수를 안내합니다.
function categoryProgressText(rows) {
  const active = rows.filter((row) => row.is_active);
  const remaining = active.length - categoryCompleteCount(rows);

  return remaining > 0
    ? `${active.length}개 제품 · ${remaining}개 기록 미완료`
    : `${active.length}개 제품 · 확인 완료`;
}

// 카테고리 폐기율은 원 생산일 귀속 폐기량을 생산량으로 나누어 계산합니다.
function groupWasteRate(rows) {
  const production = sum(rows, 'production');

  if (!production) {
    return '-';
  }

  const attributedWaste = sum(rows, 'attributed_waste');
  const wasteRate = (attributedWaste / production) * 100;

  return `${wasteRate.toFixed(1)}%`;
}

// 날짜 화살표를 누르면 선택 날짜를 하루 단위로 이동합니다.
function moveDate(amount) {
  emit('update:workDate', addLocalDays(props.workDate, amount));
}

// 날짜 선택값을 YYYY-MM-DD 형식으로 변환하고 날짜 선택창을 닫습니다.
function selectPickerDate(value) {
  const date = value instanceof Date
    ? toLocalDateString(value)
    : String(value).slice(0, 10);

  emit('update:workDate', date);
  dateMenu.value = false;
}

// 카테고리의 접기 및 펼치기 상태를 변경합니다.
function toggleCategory(name) {
  const next = new Set(collapsed.value);

  if (next.has(name)) {
    next.delete(name);
  } else {
    next.add(name);
  }

  collapsed.value = next;
}

// 지정한 항목의 제품별 수량을 합산합니다.
function sum(rows, key) {
  return rows.reduce(
    (total, row) => total + Number(row[key] || 0),
    0
  );
}

// 수정 권한, 이전 날짜 마감 및 현재 날짜 마감 상태를 확인합니다.
function ensureMutable() {
  if (!props.canMutate) {
    emit('error', '해당 기능을 사용할 권한이 없습니다.');
    return false;
  }

  if (props.daily.blocking_previous_date) {
    emit('error', '이전 날짜 마감 확인을 먼저 완료해주세요.');
    return false;
  }

  if (['closed', 'store_closed'].includes(props.daily.closure_status)) {
    emit('error', '현재 날짜는 일반 수정이 제한되어 있습니다.');
    return false;
  }

  return true;
}

// 가장 먼저 발견된 미확인 제품의 입력창을 기존 우선순위대로 엽니다.
function openNextMissing() {
  const row = nextMissingRow.value;

  if (!row) {
    return;
  }

  if (!row.production_confirmed) {
    return openProduction(row);
  }

  if (!row.loss_confirmed) {
    return openFlow(row, 'loss');
  }

  if (!row.waste_confirmed) {
    return openFlow(row, 'waste');
  }

  if (!row.disposition_confirmed) {
    return openFlow(row, 'carryover');
  }
}

/**
 * 선택한 제품의 생산 및 재고 처리 화면을 엽니다.
 *
 * 기존 권한 검사와 다이얼로그 동작을 유지하면서
 * 통합 재고 관리 화면에서 재사용할 공통 진입점을 제공합니다.
 *
 * @param {Object} row 선택한 제품
 * @param {string} type 처리할 업무 유형
 */
function openInventoryWork(row, type) {
  // 기존 수정 권한 및 마감 상태 검사를 유지합니다.
  if (!ensureMutable()) {
    return;
  }

  if (!row) {
    return;
  }

  // 모든 업무에서 동일한 제품을 선택하도록 관리합니다.
  selectedProduct.value = row;

  // 생산은 기존 생산 기록 다이얼로그를 유지합니다.
  if (type === 'production') {
    batchOpen.value = true;
    return;
  }

  // 이월, 로스, 폐기는 기존 재고 처리 다이얼로그를 유지합니다.
  if (['carryover', 'loss', 'waste'].includes(type)) {
    flowType.value = type;
    flowOpen.value = true;
  }
}

/**
 * 선택한 제품의 통합 재고 관리 화면을 엽니다.
 *
 * 제품별 생산·이월·로스·폐기·폐기율 숫자를 누르면
 * 해당 업무 메뉴가 선택된 상태로 표시됩니다.
 *
 * 현재는 조회 기능만 사용합니다.
 */
function openInventoryDialog(row, type = '') {
  if (!row) {
    return;
  }

  const section = inventorySections.find(
    (item) => item.value === type
  );

  selectedProduct.value = row;

  inventoryInitialSection.value =
    section?.value || inventorySections[0]?.value || '';

  inventoryOpen.value = true;
}

// 기존 생산 버튼 및 마감 화면의 호출 방식을 유지합니다.
function openProduction(row) {
  openInventoryWork(row, 'production');
}

// 기존 이월·로스·폐기 버튼 및 마감 화면의 호출 방식을 유지합니다.
function openFlow(row, type) {
  openInventoryWork(row, type);
}

// 기존 제품 상세정보와 선택 날짜 기준 최근 7일 생산 기록을 함께 조회합니다.
async function openProduct(row) {
  selectedProduct.value = row;
  productDetailLoading.value = true;

  // 이전 제품의 생산 기록이 다음 제품에 표시되지 않도록 초기화합니다.
  productProductionHistory.value = [];

  try {
    const { data } = await window.axios.get(
      `/tillwhite/api/production-management/products/${row.id}`,
      {
        params: {
          work_date: props.workDate,
        },
      }
    );

    // 기존 제품 정보 및 레시피 데이터 처리 방식은 유지합니다.
    productDetail.value = data.product || data;

    // 서버에서 반환한 최근 7일 생산 기록만 별도로 보관합니다.
    productProductionHistory.value = Array.isArray(data.production_history)
      ? data.production_history
      : [];

    productDetailOpen.value = true;
  } catch (error) {
    emit(
      'error',
      error.response?.data?.message || '제품 정보를 불러오지 못했습니다.'
    );
  } finally {
    productDetailLoading.value = false;
  }
}

// 선택한 요약 지표의 제품별 상세 내역을 표시합니다.
function openMetric(key) {
  detailSelectedRow.value = null;
  detailMetricKey.value = key;
  detailTitle.value =
    metrics.value.find((metric) => metric.key === key)?.title || '상세';
  detailOpen.value = true;
}

// 선택한 제품의 폐기율 상세 내역을 표시합니다.
function openWasteRate(row) {
  detailSelectedRow.value = row;
  detailMetricKey.value = 'waste_rate';
  detailTitle.value = `폐기율 - ${row.name}`;
  detailOpen.value = true;
}

// 요약 상세에서 선택한 제품의 정보 다이얼로그를 엽니다.
function openMetricProduct(item) {
  if (!item?.source) {
    return;
  }

  detailOpen.value = false;
  openProduct(item.source);
}

// 선택한 미확인 항목의 일괄 확인창을 엽니다.
function askBulkZero(type) {
  if (!ensureMutable()) {
    return;
  }

  bulkZeroType.value = type;
  bulkZeroConfirmOpen.value = true;
}

// 선택한 미확인 항목을 서버에서 0개로 일괄 확인합니다.
async function bulkZero() {
  const type = bulkZeroType.value;

  if (!ensureMutable() || bulkZeroLoading.value) {
    return;
  }

  bulkZeroLoading.value = true;

  try {
    const { data } = await window.axios.post(
      '/tillwhite/api/production-management/bulk-zero',
      {
        store_id: props.storeId,
        work_date: props.workDate,
        type
      }
    );

    bulkZeroConfirmOpen.value = false;

    if (data.daily) {
      emit('replaceDaily', data.daily);
    }

    emit('success', data.message);
    emit('reload');
  } catch (error) {
    emit(
      'error',
      error.response?.data?.message || '일괄 확인 중 오류가 발생했습니다.'
    );
  } finally {
    bulkZeroLoading.value = false;
  }
}

// 하위 다이얼로그 저장 후 최신 데이터를 조회하고 마감 점검 상태를 갱신합니다.
function handleSaved(message, freshDaily = null) {
  if (freshDaily) {
    emit('replaceDaily', freshDaily);
  }

  emit('success', message);
  emit('reload');

  // 마감 점검 중이었다면 마감창을 유지한 채 최신 점검 데이터를 다시 불러옵니다.
  if (closeOpen.value) {
    refreshClosePreview();
  }
}

// 변경 이력의 내부 동작명을 직원이 이해하기 쉬운 문구로 변환합니다.
function historyActionLabel(action) {
  return {
    create: '등록',
    update: '수정',
    delete: '삭제',
    confirm: '확인',
    close: '마감',
    correction_open: '수정 시작'
  }[action] || '변경';
}

/**
 * 변경 이력의 표시용 설명을 반환합니다.
 *
 * 서버에서 제공하는 표시용 설명을 우선 사용하며,
 * 기존 감사 로그의 원본 설명은 변경하지 않습니다.
 *
 * 등록·수정·삭제 등의 작업 유형은 별도 칩에서 표시합니다.
 */
function historyDescription(log) {
  return log.display_description
    || log.description
    || '업무 기록 변경';
}

/**
 * 변경 이력에 저장된 실제 수량을 표시합니다.
 *
 * - 생산 등록·삭제: 해당 시점의 수량을 표시합니다.
 * - 생산 수정: 수정 전후 수량을 구분합니다.
 * - 로스·폐기·이월: 서버 감사 로그의 수량을 사용합니다.
 * - 수량이 기록되지 않았다면 임의로 추측하지 않습니다.
 */
function historyQuantityText(log) {
  const oldValues = log.old_values || {};
  const newValues = log.new_values || {};

  // 감사 로그에 기록된 수량만 사용합니다.
  const readQuantity = (values) => {
    const quantity = values.quantity ?? values.qty;

    return quantity == null ? null : Number(quantity);
  };

  const oldQuantity = readQuantity(oldValues);
  const newQuantity = readQuantity(newValues);

  // 생산 기록은 생성·수정·삭제를 구분합니다.
  if (log.target_type === 'App\\Models\\ProductionBatch') {
    if (log.action === 'update') {
      if (oldQuantity !== null && newQuantity !== null) {
        return `생산 ${oldQuantity}개 → ${newQuantity}개`;
      }
    }

    const quantity = newQuantity ?? oldQuantity;

    return quantity === null ? '' : `생산 ${quantity}개`;
  }

  // 재고 처리 유형은 감사 로그에 저장된 type 값을 기준으로 판단합니다.
  if (log.target_type === 'App\\Models\\Product') {
    const type = newValues.type ?? oldValues.type;

    const labels = {
      loss: '로스',
      waste: '폐기',
      carryover: '이월',
      other_outflow: '기타 출고'
    };

    const label = labels[type];

    if (!label) {
      return '';
    }

    if (
      log.action === 'update' &&
      oldQuantity !== null &&
      newQuantity !== null &&
      oldQuantity !== newQuantity
    ) {
      return `${label} ${oldQuantity}개 → ${newQuantity}개`;
    }

    const quantity = newQuantity ?? oldQuantity;

    return quantity === null ? '' : `${label} ${quantity}개`;
  }

  return '';
}

// 선택 날짜의 변경 이력을 불러와 다이얼로그를 엽니다.
async function openHistory() {
  const storeId = props.storeId;
  const workDate = props.workDate;

  try {
    const logs = await fetchHistory(storeId, workDate);

    // 요청 중 날짜나 점포가 바뀌었다면 이전 결과를 표시하지 않습니다.
    if (storeId !== props.storeId || workDate !== props.workDate) {
      return;
    }

    historyLogs.value = logs;
    historyPage.value = 1;
    historyOpen.value = true;
  } catch (error) {
    emit(
      'error',
      error.response?.data?.message || '변경 이력을 불러오지 못했습니다.'
    );
  }
}

// 마감 후 수정 권한을 확인하고 서버에 수정 시작을 요청합니다.
async function openCorrection() {
  if (!props.canCorrect) {
    emit('error', '마감 후 수정 권한이 없습니다.');
    return;
  }

  try {
    const { data } = await window.axios.post(
      '/tillwhite/api/production-management/correction/open',
      {
        store_id: props.storeId,
        work_date: props.workDate,
        reason: correctionReason.value
      }
    );

    correctionConfirmOpen.value = false;
    correctionOpen.value = false;
    correctionReason.value = '';

    const affected = data.affected_dates?.length
      ? ` 날짜: ${data.affected_dates.join(', ')}`
      : '';

    emit('success', `${data.message}${affected}`);
    emit('reload');
  } catch (error) {
    emit(
      'error',
      error.response?.data?.message || '마감 후 수정을 시작하지 못했습니다.'
    );
  }
}

// 마감창을 유지하면서 최신 마감 점검 데이터를 다시 불러옵니다.
async function refreshClosePreview() {
  try {
    const { data } = await window.axios.get(
      '/tillwhite/api/production-management/close-preview',
      {
        params: {
          store_id: props.storeId,
          work_date: props.workDate
        }
      }
    );

    // 최신 마감 점검 데이터를 반영합니다.
    closePreview.value = data;

    // 활성 제품 수를 기준으로 마지막 페이지를 계산합니다.
    const activeCount = (data?.daily?.rows || []).filter(
      (row) => row.is_active
    ).length;

    const lastPage = Math.max(
      1,
      Math.ceil(activeCount / closePageSize)
    );

    // 현재 페이지가 마지막 페이지를 초과한 경우에만 보정합니다.
    if (closePage.value > lastPage) {
      closePage.value = lastPage;
    }
  } catch (error) {
    emit(
      'error',
      error.response?.data?.message || '마감 내용을 다시 확인하지 못했습니다.'
    );
  }
}

// 서버에서 마감 가능 여부를 조회한 뒤 마감 최종확인창을 엽니다.
async function previewClose() {
  if (!ensureMutable()) {
    return;
  }

  try {
    const { data } = await window.axios.get(
      '/tillwhite/api/production-management/close-preview',
      {
        params: {
          store_id: props.storeId,
          work_date: props.workDate
        }
      }
    );

    closePreview.value = data;
    closePage.value = 1;
    closeOpen.value = true;
  } catch (error) {
    emit(
      'error',
      error.response?.data?.message || '마감 내용을 확인하지 못했습니다.'
    );
  }
}

// 서버에 하루 업무 마감을 요청하고 성공한 경우에만 완료 상태를 반영합니다.
async function closeDay() {
  closing.value = true;

  try {
    const { data } = await window.axios.post(
      '/tillwhite/api/production-management/close',
      {
        store_id: props.storeId,
        work_date: props.workDate
      }
    );

    confirmCloseOpen.value = false;
    closeOpen.value = false;

    if (data.daily) {
      emit('replaceDaily', data.daily);
    }

    emit('success', data.message || '하루 업무를 마감했습니다.');
    emit('reload');

    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  } catch (error) {
    emit(
      'error',
      error.response?.data?.message || '마감하지 못했습니다.'
    );
  } finally {
    closing.value = false;
  }
}
</script>

<style scoped>
/* 변경 이력의 수량은 제목과 작업자 정보 사이에 표시합니다. */
.history-quantity {
  margin-top: 5px;
  font-size: .74rem;
  color: rgba(var(--v-theme-on-surface), .72);
}

.daily-page {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.daily-section {
  padding: 18px 0;
}

.date-section {
  padding: 8px 0;
}

.date-section .app-date-toolbar {
  min-height: 46px;
}

.date-section .app-date-main {
  padding-block: 4px;
}

.section-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}

.section-heading h3 {
  margin: 0;
  font-size: .98rem;
  font-weight: 650;
  letter-spacing: -.02em;
}

.section-heading p {
  margin: 4px 0 0;
  font-size: .76rem;
  color: rgba(var(--v-theme-on-surface), .58);
}

.work-progress {
  width: 100%;
  height: 4px;
  margin-top: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), .07);
}

.work-progress span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: rgb(var(--v-theme-primary));
}

.missing-summary {
  margin-top: 6px;
  font-size: .7rem;
  color: rgba(var(--v-theme-on-surface), .62);
}

.bulk-menu :deep(.v-list-item-title) {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  font-size: .8rem;
  font-weight: 650;
}

.bulk-menu :deep(.v-list-item-subtitle) {
  margin-top: 3px;
  font-size: .67rem;
  line-height: 1.4;
  white-space: normal;
}

.daily-metrics {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 9px;
}

.metric-item {
  appearance: none;
  text-align: center;
  padding: 12px 8px;
  border: 1px solid rgba(var(--v-border-color), .7);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  color: inherit;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .055);
  transition: transform .15s ease, box-shadow .15s ease;
}

.metric-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
}

.metric-item.static {
  cursor: default;
}

.metric-item span,
.metric-item strong {
  display: block;
}

.metric-item span {
  font-size: .7rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), .58);
}

.metric-item strong {
  margin-top: 3px;
  font-size: 1.08rem;
  font-weight: 650;
  font-variant-numeric: tabular-nums;
}

.product-search {
  max-width: 360px;
}

/* 제품별 현황의 외곽 테두리만 제거하고 기존 여백을 유지합니다. */
.product-section-framed {
  border: none;
  border-radius: 12px;
  padding: 16px;
}

.product-section-framed .product-heading {
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), .11);
  padding-bottom: 14px;
}

.product-count-heading {
  font-size: .75rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), .58);
  margin-left: 5px;
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

.stock-flow-event {
  border: 1px solid rgba(var(--v-theme-on-surface), .12);
  border-radius: 9px;
  padding: 11px;
  margin-top: 8px;
}

.stock-flow-event > div {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.stock-flow-event small {
  display: block;
  color: rgba(var(--v-theme-on-surface), .6);
  margin-top: 4px;
}

.production-friendly-empty {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px;
  color: rgba(var(--v-theme-on-surface), .65);
}

.product-heading-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: nowrap;
  gap: 7px;
  width: 100%;
}

.product-heading-actions > .missing-action-button,
.product-heading-actions > .v-menu {
  flex: 1 1 50%;
  min-width: 0;
}

.missing-action-button {
  min-height: 48px;
  font-weight: 650;
  width: 100%;
  flex: 1 1 50%;
}

.missing-complete-state {
  min-height: 34px;
  display: flex;
  align-items: center;
  padding: 0 10px;
  font-size: .72rem;
  font-weight: 650;
  color: rgba(var(--v-theme-on-surface), .62);
}

.product-filter-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.status-filter {
  border-bottom: 1px solid rgba(var(--v-border-color), .65);
  border-radius: 0;
}

.status-filter :deep(.v-btn) {
  min-width: auto;
  padding-inline: 12px;
  font-size: .76rem;
  font-weight: 500;
}

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
  min-width: 0;
  border-collapse: collapse;
  table-layout: fixed;
  font-size: .75rem;
}

.product-column {
  width: 34%;
}

.number-column {
  width: 13.2%;
}

.product-table th,
.product-table td {
  height: 34px;
  padding: 5px 4px;
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
}

.product-table thead th:first-child {
  z-index: 4;
}

.product-table tbody tr:hover td {
  background: rgb(var(--v-theme-surface-variant));
}

.product-table tbody tr:hover td:first-child {
  background: rgb(var(--v-theme-surface-variant));
}

.product-table th:first-child,
.product-table td:first-child {
  padding-left: .65rem;
  border-right: 1px solid rgba(var(--v-border-color), .38);
}

.product-name {
  display: block;
  width: 100%;
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

.table-value {
  min-width: 30px;
  padding: 5px 7px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.table-value:hover {
  background: rgba(var(--v-theme-on-surface), .06);
}

.table-value.pending {
  color: rgba(var(--v-theme-on-surface), .42);
}

.table-value.calculated {
  color: rgba(var(--v-theme-on-surface), .66);
  cursor: help;
}

.rate-value {
  color: rgba(var(--v-theme-on-surface), .66);
}

.row-warning {
  display: block;
  margin-top: 1px;
  color: rgb(var(--v-theme-error));
  font-size: .62rem;
  font-weight: 600;
}

.row-inactive {
  opacity: .5;
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

.daily-actions {
  display: flex;
  align-items: center;
  padding-top: 6px;
}
.summary-detail-hero {
  padding: 4px 0 16px;
}

.summary-detail-hero span,
.summary-detail-hero strong {
  display: block;
}

.summary-detail-hero span {
  font-size: .72rem;
  color: rgba(var(--v-theme-on-surface), .58);
}

.summary-detail-hero strong {
  margin-top: 3px;
  font-size: 1.7rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.summary-detail-hero small {
  display: block;
  margin-top: 6px;
  font-size: .7rem;
  color: rgba(var(--v-theme-on-surface), .58);
}

.summary-detail-facts {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-bottom: 16px;
}

.summary-detail-facts > div {
  padding: 9px 8px;
  border-radius: 9px;
  background: rgba(var(--v-theme-on-surface), .04);
  text-align: center;
}

.summary-detail-facts span,
.summary-detail-facts strong {
  display: block;
}

.summary-detail-facts span {
  font-size: .64rem;
  color: rgba(var(--v-theme-on-surface), .55);
}

.summary-detail-facts strong {
  margin-top: 2px;
  font-size: .78rem;
  font-weight: 650;
}

.waste-analysis-note {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin: -4px 0 14px;
  padding: 9px 10px;
  border-radius: 9px;
  background: rgba(var(--v-theme-on-surface), .04);
  font-size: .68rem;
}

.waste-analysis-note span {
  color: rgba(var(--v-theme-on-surface), .58);
  line-height: 1.45;
}

.summary-detail-meta {
  margin-bottom: 16px;
  font-size: .7rem;
  color: rgba(var(--v-theme-on-surface), .55);
}

.dialog-full-divider {
  margin-inline: -24px;
}

.summary-detail-heading {
  padding: 16px 0 8px;
  font-size: .8rem;
  font-weight: 650;
}

.summary-detail-list {
  display: flex;
  flex-direction: column;
}

.summary-detail-row {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 9px 2px;
  border: 0;
  border-bottom: 1px solid rgba(var(--v-border-color), .55);
  background: transparent;
  color: inherit;
  text-align: left;
  font-size: .76rem;
  cursor: pointer;
}

.summary-detail-row > span {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.summary-detail-row small {
  margin-top: 2px;
  font-size: .63rem;
  color: rgba(var(--v-theme-on-surface), .5);
}

.summary-detail-row:hover {
  background: rgba(var(--v-theme-on-surface), .035);
}

.summary-detail-row strong {
  font-variant-numeric: tabular-nums;
}

.summary-detail-empty {
  padding: 18px 0;
  font-size: .75rem;
  color: rgba(var(--v-theme-on-surface), .55);
}

.summary-detail-empty strong,
.summary-detail-empty span {
  display: block;
}

.summary-detail-empty strong {
  color: rgb(var(--v-theme-on-surface));
}

.summary-detail-empty span {
  margin-top: 3px;
  font-size: .68rem;
}

.production-product-section {
  padding: 20px 24px;
}

.production-product-title {
  margin-bottom: 12px;
  font-size: .88rem;
  font-weight: 650;
}

.production-product-date {
  margin-top: -8px;
  margin-bottom: 12px;
  font-size: .68rem;
  color: rgba(var(--v-theme-on-surface), .55);
}

.product-detail-metrics {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.product-detail-metrics > div {
  padding: 10px;
  border-radius: 9px;
  background: rgba(var(--v-theme-on-surface), .035);
  text-align: center;
}

.product-detail-metrics span,
.product-detail-metrics strong {
  display: block;
}

.product-detail-metrics span {
  font-size: .68rem;
  color: rgba(var(--v-theme-on-surface), .56);
}

.product-detail-metrics strong {
  margin-top: 2px;
  font-size: .9rem;
  font-weight: 600;
}

.product-analysis-copy {
  margin: 0;
  font-size: .78rem;
  line-height: 1.65;
  color: rgba(var(--v-theme-on-surface), .72);
}

.history-context {
  padding: 2px 0 14px;
  font-size: .72rem;
  color: rgba(var(--v-theme-on-surface), .58);
}

.history-list {
  display: flex;
  flex-direction: column;
}

.history-item {
  padding: 12px 0;
  border-bottom: 1px solid rgba(var(--v-border-color), .52);
}

.history-item-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.history-item-head strong {
  min-width: 0;
  font-size: .8rem;
  font-weight: 650;
}

.history-action {
  flex: none;
  padding: 3px 7px;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), .06);
  font-size: .62rem;
  font-weight: 650;
}

.history-meta {
  margin-top: 4px;
  font-size: .67rem;
  color: rgba(var(--v-theme-on-surface), .55);
}

.close-check-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.close-check-item {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), .7);
  border-radius: 10px;
}

.close-check-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.close-check-copy strong {
  font-size: .82rem;
  font-weight: 600;
}

.close-check-copy span {
  font-size: .7rem;
  color: rgba(var(--v-theme-on-surface), .58);
}

.close-check-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 9px;
}

.close-progress-copy {
  margin-top: 10px;
  font-size: .72rem;
  color: rgba(var(--v-theme-on-surface), .62);
}

.close-product-values {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
  margin-top: 9px;
}

.close-product-values button {
  padding: 7px 5px;
  border: 1px solid rgba(var(--v-border-color), .55);
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), .025);
  text-align: center;
  cursor: pointer;
}

.close-product-values span,
.close-product-values strong {
  display: block;
}

.close-product-values span {
  font-size: .62rem;
  color: rgba(var(--v-theme-on-surface), .55);
}

.close-product-values strong {
  margin-top: 2px;
  font-size: .76rem;
  font-weight: 650;
}

.close-product-values .production {
  background: rgba(76, 175, 80, .12);
}

.close-product-values .waste {
  background: rgba(239, 83, 80, .11);
}

.close-pagination {
  margin-top: 12px;
}

.waste-rate-help {
  padding: 0 2px;
  border: 0;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), .55);
  font: inherit;
  cursor: pointer;
}

.waste-guide-dialog p {
  margin: 0 0 14px;
  font-size: .76rem;
  line-height: 1.6;
}

.waste-guide-dialog > div {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin-top: 10px;
}

.waste-guide-dialog strong {
  font-size: .72rem;
}

.waste-guide-dialog span {
  font-size: .72rem;
  line-height: 1.55;
  color: rgba(var(--v-theme-on-surface), .68);
}

.close-ready {
  padding: 12px;
  border-radius: 10px;
  background: rgba(var(--v-theme-success), .08);
  font-size: .78rem;
  font-weight: 600;
}
.previous-close-alert {
  margin: 2px 0 8px;
}

.previous-close-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.previous-close-copy strong {
  font-size: .78rem;
}

.previous-close-copy span {
  font-size: .69rem;
  line-height: 1.4;
}

.previous-close-action {
  min-height: 38px;
  font-weight: 650;
}

.summary-section {
  padding-top: 10px;
}

.daily-metrics {
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 7px;
}

.metric-item {
  padding: 10px 6px;
}

.missing-type-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
  margin-top: 10px;
}

.missing-type-button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  min-height: 34px;
  padding: 6px 8px;
  border: 1px solid rgba(var(--v-border-color), .65);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.missing-type-button span {
  font-size: .67rem;
  color: rgba(var(--v-theme-on-surface), .58);
}

.missing-type-button strong {
  font-size: .7rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.missing-type-button.active {
  border-color: rgba(var(--v-theme-primary), .55);
  background: rgba(var(--v-theme-primary), .07);
}

.missing-type-button.complete {
  opacity: .58;
}

.history-footer {
  justify-content: flex-start;
}

.status-filter {
  width: 100%;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  border-bottom: 1px solid rgba(var(--v-border-color), .65);
}

.status-filter :deep(.v-btn) {
  border-radius: 0;
}

.status-filter :deep(.v-btn--active) {
  border-bottom: 2px solid rgb(var(--v-theme-primary));
}

.product-filter-row {
  align-items: flex-end;
  flex-wrap: wrap;
}

.product-filter-row > span {
  margin-left: auto;
}

.product-table {
  min-width: 100%;
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
}

.product-table tbody tr:hover td,
.product-table tbody tr:hover td:first-child {
  background: rgba(var(--v-theme-on-surface), .035);
}

.table-value {
  min-width: 30px;
  padding: 4px 6px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), .045);
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
  font-weight: 650;
}

/* 최근 입력 제품은 기존 검색 및 필터와 독립된 보조 탐색 영역입니다. */
.recent-products {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: -4px 0 12px;
}

.recent-products-label {
  flex-shrink: 0;
  padding-top: 5px;
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.recent-products-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  min-width: 0;
}

.recent-product-button {
  max-width: 100%;
  padding: 4px 9px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  font-size: 12px;
  line-height: 1.5;
  text-align: left;
  color: rgb(var(--v-theme-on-surface));
  overflow-wrap: anywhere;
}

.recent-product-button:hover {
  background: rgba(var(--v-theme-on-surface), 0.06);
}

@media (max-width: 600px) {
  .recent-products {
    flex-direction: column;
    gap: 5px;
  }

  .recent-products-label {
    padding-top: 0;
  }
}

@media (max-width: 760px) {
  .daily-section {
    padding: 14px 0;
  }

  .date-section {
    padding: 6px 0;
  }

  .daily-metrics {
    grid-template-columns: repeat(6, 1fr);
  }

  .daily-metrics .metric-item {
    grid-column: span 2;
  }

  .daily-metrics .metric-item:nth-child(4),
  .daily-metrics .metric-item:nth-child(5) {
    grid-column: span 3;
  }

  .missing-type-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .previous-close-alert :deep(.v-alert__content) {
    min-width: 0;
  }

  .previous-close-alert :deep(.v-alert__append) {
    margin-inline-start: 8px;
  }

  .product-heading {
    align-items: stretch;
    flex-direction: column;
  }

  .product-search {
    max-width: none;
  }

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

  .product-detail-metrics {
    grid-template-columns: repeat(2, 1fr);
  }

  .summary-detail-facts {
    grid-template-columns: repeat(2, 1fr);
  }
}

.product-name-cell {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 2px;
}

.product-name-cell .product-name {
  min-width: 0;
  flex: 1;
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
  color: rgba(var(--v-theme-on-surface), 0.55);
  cursor: pointer;
  transform: translateX(-6px);
}

.carryover-value span {
  display: block;
  line-height: 1.15;
}

.product-detail-row td {
  position: static !important;
  height: auto !important;
  padding: 0 !important;
  white-space: normal !important;
  background: rgba(var(--v-theme-on-surface), 0.025) !important;
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
  font-size: 0.62rem;
  color: rgba(var(--v-theme-on-surface), 0.52);
}

.product-row-detail strong {
  margin-top: 2px;
  font-size: 0.72rem;
  font-weight: 600;
  white-space: normal;
}

.product-row-flow {
  grid-column: 1 / -1;
  padding-top: 6px;
  border-top: 1px solid rgba(var(--v-border-color), 0.45);
}

.history-pagination {
  margin-top: 12px;
}

.history-page-count {
  margin-top: 2px;
  color: rgba(var(--v-theme-on-surface), 0.52);
  font-size: 0.66rem;
  text-align: center;
}


/* 재고 현황: 모바일에서도 세 항목을 한눈에 확인 */
.stock-flow-overview {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.stock-flow-overview .stock-flow-summary {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 0;
  padding: 14px 6px;
  border-radius: 10px;
  text-align: center;
}

.stock-flow-summary__label {
  font-size: 12px;
  line-height: 1.4;
  word-break: keep-all;
}

.stock-flow-summary__value {
  font-size: 21px;
  line-height: 1.2;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.stock-flow-summary__value small {
  font-size: 12px;
  font-weight: 400;
}

/* 처리 내역의 제목과 기록 건수 */
.stock-flow-history {
  margin-top: 20px;
}

.stock-flow-history__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.stock-flow-history__heading
.production-dialog-section-title {
  margin: 0;
}

.stock-flow-history__count {
  flex-shrink: 0;
  font-size: 12px;
  color: #788392;
}

/* 기록별 제목, 수량, 날짜, 사유 구분 */
.stock-flow-history__item {
  margin-top: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 13px 14px;
  margin-bottom: 8px;
  border-radius: 10px;
}

.stock-flow-history__item-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.stock-flow-history__item-header strong {
  min-width: 0;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.stock-flow-history__quantity {
  flex-shrink: 0;
  font-size: 13px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.stock-flow-history__meta,
.stock-flow-history__reason {
  display: block;
  font-size: 12px;
  line-height: 1.5;
  color: #75808e;
  overflow-wrap: anywhere;
}

/* 폐기 집계 기준 안내 */
.stock-flow-guide {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 16px;
  font-size: 12px;
  line-height: 1.65;
}

.stock-flow-guide .v-icon {
  flex-shrink: 0;
  margin-top: 1px;
}

/*
 * 생산 기록 비교 섹션
 *
 * 기존 제품 상세 섹션과 구분선은 그대로 사용합니다.
 * 이 스타일은 새로 추가한 비교 카드와 차트에만 적용됩니다.
 */

/* 선택 날짜·전날·증감 수량을 나란히 표시합니다. */
.production-comparison-cards {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-top: 14px;
}

.production-comparison-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 0;
  padding: 14px 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  text-align: center;
}

.production-comparison-card span {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 12px;
}

.production-comparison-card strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 17px;
  font-weight: 700;
  overflow-wrap: anywhere;
}

/* 최근 7일 차트 제목과 평균 수량입니다. */
.production-comparison-chart-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 24px;
  margin-bottom: 14px;
}

.production-comparison-chart-heading strong {
  font-size: 14px;
  font-weight: 700;
}

.production-comparison-chart-heading span {
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 12px;
}

/* 최근 7일을 동일한 너비의 세로 막대 7개로 표시합니다. */
.production-comparison-chart {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 8px;
  width: 100%;
}

.production-comparison-chart-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 0;
  gap: 8px;
}

.production-comparison-chart-value {
  font-size: 12px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* 모든 날짜에 동일한 높이의 막대 영역을 확보합니다. */
.production-comparison-chart-track {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  width: 100%;
  height: 140px;
  overflow: hidden;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* 생산량에 비례하여 막대 높이가 달라집니다. */
.production-comparison-chart-bar {
  width: min(100%, 32px);
  margin: 0 auto;
  border-radius: 5px 5px 0 0;
  background: rgb(var(--v-theme-primary));
  transition: height 0.2s ease;
}

.production-comparison-chart-date {
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 11px;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* 기록이 없거나 일부 날짜가 미확인일 때 안내합니다. */
.production-comparison-empty,
.production-comparison-note {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 12px;
  line-height: 1.6;
}

.production-comparison-empty {
  padding: 20px 0;
  text-align: center;
}

.production-comparison-note {
  margin-top: 14px;
  margin-bottom: 0;
}

@media (max-width: 480px) {
  .production-comparison-cards {
    gap: 6px;
  }

  .production-comparison-card {
    padding: 12px 4px;
  }

  .production-comparison-card strong {
    font-size: 14px;
  }

  .production-comparison-chart {
    gap: 4px;
  }

  .production-comparison-chart-track {
    height: 110px;
  }

  .production-comparison-chart-value {
    font-size: 11px;
  }

  .production-comparison-chart-date {
    font-size: 10px;
  }

  .stock-flow-overview {
    gap: 6px;
  }

  .stock-flow-overview .stock-flow-summary {
    padding: 12px 4px;
  }

  .stock-flow-summary__label {
    font-size: 11px;
  }

  .stock-flow-summary__value {
    font-size: 18px;
  }

  .product-row-detail {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

/* 이월 재고 폐기가 존재하는 경우에만 상세 내역을 표시합니다. */
.inventory-dialog__waste-details {
  width: 100%;
  margin-top: 8px;
  padding: 14px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  text-align: left;
}

.inventory-dialog__waste-details > strong {
  display: block;
  margin-bottom: 10px;
  font-size: 14px;
}

.inventory-dialog__waste-detail {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  font-size: 13px;
}

.inventory-dialog__waste-detail + .inventory-dialog__waste-detail {
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
</style>