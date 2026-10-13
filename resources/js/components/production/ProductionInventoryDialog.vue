<template>
  <v-dialog
    :model-value="modelValue"
    max-width="760"
    persistent
    scrollable
    @update:model-value="onDialogModelChange"
  >
    <v-card rounded="lg" class="app-dialog-card inventory-dialog">
      <!-- 제품명과 선택한 작업 날짜를 표시합니다. -->
      <v-card-title class="app-dialog-header inventory-dialog__header">
        <div class="inventory-dialog__heading">
          <div class="inventory-dialog__heading-info">
            <span>제품별 생산 관리</span>
            <strong>{{ product?.name || "제품" }}</strong>
          </div>

          <div class="inventory-dialog__heading-date">
            <v-icon icon="mdi-calendar-blank-outline" size="16" />
            <span>{{ workDate }}</span>
          </div>
        </div>
      </v-card-title>

      <v-card-text class="app-dialog-body">
        <!-- 서버에서 전달받은 제품별 재고 현황을 표시합니다. -->
        <div class="inventory-dialog__summary">
          <div>
            <span>생산</span>
            <strong>{{ displayQuantity(product?.production) }}</strong>
          </div>

          <div>
            <span>이월 재고</span>
            <strong>{{ displayQuantity(product?.carryover_in) }}</strong>
          </div>

          <div>
            <span>이월 예정</span>
            <strong>{{ displayQuantity(product?.carryover_out) }}</strong>
          </div>

          <div>
            <span>로스</span>
            <strong>
              {{ displayQuantity(product?.operational_loss ?? product?.loss) }}
            </strong>
          </div>

          <div>
            <span>폐기</span>
            <strong>{{ displayQuantity(product?.attributed_waste) }}</strong>
          </div>

          <div>
            <span>폐기율</span>
            <strong>
              {{ product?.waste_rate == null ? "미확인" : `${product.waste_rate}%` }}
            </strong>
          </div>
        </div>

        <!-- 부모가 전달한 업무 메뉴 설정을 사용합니다. -->
        <div class="inventory-dialog__navigation">
          <button
            v-for="section in sections"
            :key="section.value"
            type="button"
            class="inventory-dialog__tab"
            :class="{
              'inventory-dialog__tab--active':
                selectedSection === section.value
            }"
            :aria-pressed="selectedSection === section.value"
            @click="selectSection(section.value)"
          >
            {{ section.title }}
          </button>
        </div>

        <!-- 선택한 업무의 재고 현황 및 상세 화면을 표시합니다. -->
        <div class="inventory-dialog__content">
          <!-- 서버에서 전달받은 생산 기록별 재고 목록입니다. -->
          <section class="inventory-dialog__stock">
            <div class="inventory-dialog__stock-heading">
              <strong>생산 기록별 재고</strong>
              <span>{{ stockSources.length }}건</span>
            </div>

            <div v-if="stockSources.length" class="inventory-dialog__stock-list">
              <div
                v-for="source in stockSources"
                :key="source.stock_lot_id"
                class="inventory-dialog__stock-item"
              >
                <div class="inventory-dialog__stock-item-top">
                  <div class="inventory-dialog__stock-date">
                    <v-icon icon="mdi-calendar-blank-outline" size="16" />
                    <span>{{ source.origin_production_date || "미확인" }}</span>
                  </div>

                  <strong class="inventory-dialog__stock-quantity">
                    {{
                      displayQuantity(
                        source.unallocated_quantity ?? source.remaining_quantity
                      )
                    }}
                  </strong>
                </div>

                <small>최초 생산일 기준 재고</small>
              </div>
            </div>

            <div v-else class="inventory-dialog__stock-empty">
              표시할 재고 기록이 없습니다.
            </div>
          </section>

          <!-- 선택 업무의 조회 화면입니다. -->
          <div v-if="activeSection" class="inventory-dialog__placeholder">
            <strong>
              {{ selectedSection === "production" ? "생산 관리" : activeSection.title }}
            </strong>

            <!-- 생산 업무: 통합 다이얼로그 내부 입력 화면 -->
            <!-- 생산 입력 영역 열기 -->
            <v-btn
              v-if="selectedSection === 'production'"
              block
              variant="tonal"
              :disabled="saving || Boolean(editingBatch)"
              :prepend-icon="productionFormOpen ? 'mdi-chevron-up' : 'mdi-plus'"
              @click="
                productionFormOpen = !productionFormOpen;
                zeroCardOpen = false;
              "
            >
              {{ productionFormOpen ? "접기" : editingBatch ? "수정" : "추가" }}
            </v-btn>

            <div
              v-if="selectedSection === 'production'"
              v-show="productionFormOpen"
              ref="productionEditor"
              class="inventory-dialog__production-form"
            >
              <div class="inventory-dialog__field-heading">
                <strong>
                  {{ editingBatch ? "생산 기록 수정" : "생산 기록 추가" }}
                </strong>

                <span>현재 생산 {{ displayQuantity(product?.production) }}</span>
              </div>

              <!-- 생산 입력 카드 탐색 -->
              <div class="inventory-dialog__card-navigation">
                <div class="inventory-dialog__card-steps">
                  <v-btn
                    v-for="(title, index) in productionCardTitles"
                    :key="index"
                    size="small"
                    :variant="productionCardIndex === index ? 'flat' : 'outlined'"
                    :color="productionCardIndex === index ? 'primary' : undefined"
                    @click="productionCardIndex = index"
                  >
                    {{ title }}
                  </v-btn>
                </div>

                <div class="inventory-dialog__card-controls">
                  <v-btn
                    size="small"
                    variant="text"
                    prepend-icon="mdi-chevron-left"
                    :disabled="productionCardIndex === 0"
                    @click="productionCardIndex--"
                  >
                    이전
                  </v-btn>

                  <span>{{ productionCardIndex + 1 }} / {{ productionCardTitles.length }}</span>

                  <v-btn
                    size="small"
                    variant="text"
                    append-icon="mdi-chevron-right"
                    :disabled="productionCardIndex === lastProductionCardIndex"
                    @click="productionCardIndex++"
                  >
                    다음
                  </v-btn>
                </div>
              </div>

              <!-- 생산 입력 카드 스와이프 영역 -->
              <div
                class="inventory-dialog__swipe-area"
                @pointerdown="onProductionSwipeStart"
                @pointerup="onProductionSwipeEnd"
                @pointercancel="onProductionSwipeCancel"
              >
                <!-- 카드 1: 생산 입력 -->
                <div
                  v-show="productionCardIndex === 0"
                  class="inventory-dialog__swipe-card"
                >
                  <!-- 전날 생산량 참고 -->
                  <div v-if="!editingBatch" class="inventory-dialog__previous-production">
                    <div>
                      <span>전날 생산량 참고</span>

                      <strong v-if="previousProductionLoading"> 조회 중... </strong>

                      <strong v-else-if="previousProduction !== null">
                        {{ previousProduction }}개
                      </strong>

                      <strong v-else> 기록 없음 </strong>
                    </div>

                    <v-btn
                      size="small"
                      variant="outlined"
                      :disabled="
                        !canMutate ||
                        saving ||
                        previousProductionLoading ||
                        previousProduction === null
                      "
                      @click="applyPreviousProduction"
                    >
                      불러오기
                    </v-btn>
                  </div>

                  <v-number-input
                    v-model="productionForm.quantity"
                    :error="productionErrors.quantity"
                    :error-messages="productionErrors.quantity ? '올바른 생산 수량을 입력해 주세요.' : []"
                    @update:model-value="productionErrors.quantity = false"
                    label="생산 수량"
                    variant="outlined"
                    density="compact"
                    :min="1"
                    control-variant="stacked"
                  />

                  <v-select
                    v-model="productionForm.workerIds"
                    :items="workers"
                    item-title="name"
                    item-value="id"
                    label="작업자 · 필수"
                    variant="outlined"
                    density="compact"
                    multiple
                    chips
                    closable-chips
                    clearable
                    hide-details="auto"
                    no-data-text="선택할 수 있는 작업자가 없습니다"
                    :error="productionErrors.workers"
                    :error-messages="
                      productionErrors.workers ? '작업자를 선택해 주세요.' : []
                    "
                    @update:model-value="productionErrors.workers = false"
                  />
                </div>

                <!-- 카드 2: 레시피 및 추천 정보 -->
                <div
                  v-show="productionCardIndex === 1"
                  class="inventory-dialog__swipe-card"
                >
                  <v-alert type="info" variant="tonal" density="compact" class="mb-2">
                    <template v-if="editingBatch">
                      레시피 변경 및 추천 생산량 정보는 최초 등록 시에만 입력할 수
                      있습니다. 기존 기록 수정시 생산 수량, 작업자, 메모만 변경할 수
                      있습니다.
                    </template>

                    <template v-else>
                      실제 작업 과정에서 레시피를 변경했거나 추천 생산량을 참고했다면 해당
                      내용을 기록해 주세요. 입력한 정보는 이후 생산 분석에서 활용됩니다.
                    </template>
                  </v-alert>

                  <v-divider />

                  <div class="inventory-dialog__field-heading">
                    <strong>레시피 변경</strong>
                  </div>

                  <v-checkbox
                    v-model="productionForm.recipeDeviated"
                    :disabled="Boolean(editingBatch)"
                    label="레시피와 다르게 작업함"
                    density="compact"
                    hide-details
                    @update:model-value="
                      (value) => {
                        if (!value) {
                          productionErrors.recipeDeviationNote = false;
                          productionForm.recipeDeviationNote = '';
                        }
                      }
                    "
                  />

                  <v-textarea
                    v-if="productionForm.recipeDeviated"
                    v-model="productionForm.recipeDeviationNote"
                    :error="productionErrors.recipeDeviationNote"
                    :error-messages="productionErrors.recipeDeviationNote ? '변경한 작업 내용을 입력해 주세요.' : []"
                    @update:model-value="productionErrors.recipeDeviationNote = false"
                    :disabled="Boolean(editingBatch)"
                    label="달라진 작업 내용"
                    variant="outlined"
                    density="compact"
                    rows="2"
                    hide-details="auto"
                  />

                  <v-divider />

                  <div class="inventory-dialog__field-heading">
                    <strong>추천 생산량</strong>
                  </div>

                  <v-checkbox
                    v-model="productionForm.recommendationReferenced"
                    :disabled="Boolean(editingBatch)"
                    label="추천 생산량 참고"
                    density="compact"
                    hide-details
                    @update:model-value="
                      (value) => {
                        if (!value) {
                          productionForm.recommendationDeviationReason = '';
                        }
                      }
                    "
                  />

                  <v-text-field
                    v-model="productionForm.recommendationDeviationReason"
                    :disabled="Boolean(editingBatch) || !productionForm.recommendationReferenced"
                    label="추천 범위와 다르게 생산한 이유 · 선택"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  />
                </div>

                <!-- 카드 3: 메모 -->
                <div
                  v-show="productionCardIndex === 2"
                  class="inventory-dialog__swipe-card"
                >
                  <v-textarea
                    v-model="productionForm.note"
                    label="메모 · 선택"
                    variant="outlined"
                    density="compact"
                    rows="2"
                    hide-details="auto"
                  />
                </div>
              </div>
            </div>

            <!-- 생산 0개 확인 독립 카드 -->
            <section
              v-if="selectedSection === 'production'"
              class="inventory-dialog__zero-card"
            >
              <v-btn
                block
                variant="tonal"
                :disabled="saving || Boolean(editingBatch)"
                :prepend-icon="zeroCardOpen ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                @click="
                  zeroCardOpen = !zeroCardOpen;
                  productionFormOpen = false;
                "
              >
                오늘 생산하지 않은 경우
              </v-btn>

              <div v-show="zeroCardOpen" class="inventory-dialog__zero-card-content">
                <p class="inventory-dialog__form-help">
                  실제 생산이 없었다면 사유를 선택해 0개로 확인합니다. 목록에 없는 사유는
                  직접입력을 선택해 주세요.
                </p>

                <v-select
                  v-model="zeroReason"
                  :items="displayZeroReasons"
                  item-title="title"
                  item-value="value"
                  label="생산 0개 사유"
                  variant="outlined"
                  density="compact"
                  clearable
                  :disabled="!canMutate || saving || Boolean(editingBatch)"
                />

                <v-text-field
                  v-if="zeroReason === 'other'"
                  v-model="zeroReasonText"
                  label="사유 직접입력"
                  variant="outlined"
                  density="compact"
                  :disabled="!canMutate || saving"
                />

                <v-textarea
                  v-model="zeroNote"
                  label="메모 · 선택"
                  variant="outlined"
                  density="compact"
                  rows="2"
                  hide-details="auto"
                  :disabled="!canMutate || saving || Boolean(editingBatch)"
                />

                <v-btn
                  variant="outlined"
                  block
                  :disabled="
                    !canMutate || saving || Boolean(editingBatch) || !canConfirmZero
                  "
                  @click="confirmZeroOpen = true"
                >
                  생산 0개 확인
                </v-btn>
              </div>
            </section>

            <!-- 생산 메뉴: 기존 생산 기록 목록 -->
            <section
              v-if="selectedSection === 'production'"
              class="inventory-dialog__production"
            >
              <div class="inventory-dialog__production-summary">
                <strong>생산 합계</strong>
                <strong>{{ displayQuantity(product?.production) }}</strong>
              </div>

              <div class="inventory-dialog__production-heading">
                <strong>생산 기록</strong>
                <strong>{{ product?.batches?.length || 0 }}건</strong>
              </div>

              <template v-if="product?.batches?.length">
                <div
                  v-for="batch in product.batches"
                  :key="batch.id"
                  class="inventory-dialog__production-record"
                >
                  <!-- 생산 기록의 생산량 헤더 -->
                  <button
                    type="button"
                    class="inventory-dialog__production-record-header"
                    :aria-expanded="expandedBatchIds.includes(batch.id)"
                    @click="toggleBatch(batch.id)"
                  >
                    <strong class="inventory-dialog__production-record-label">
                      생산량
                    </strong>

                    <span class="inventory-dialog__production-record-header-right">
                      <strong class="inventory-dialog__production-record-quantity">
                        {{ displayQuantity(batch.quantity) }}
                      </strong>

                      <v-icon
                        :icon="
                          expandedBatchIds.includes(batch.id)
                            ? 'mdi-chevron-up'
                            : 'mdi-chevron-down'
                        "
                        size="20"
                      />
                    </span>
                  </button>

                  <div v-if="expandedBatchIds.includes(batch.id)">
                    <!-- 작업자 -->
                    <div
                      v-if="batch.workers?.length"
                      class="inventory-dialog__record-detail"
                    >
                      <strong>작업자</strong>
                      <div>{{ batch.workers.map((worker) => worker.name).join(", ") }}</div>
                    </div>

                    <!-- 메모 -->
                    <div
                      v-if="batch.note"
                      class="inventory-dialog__record-detail"
                    >
                      <strong>메모</strong>
                      <div>{{ batch.note }}</div>
                    </div>

                    <!-- 레시피 변경 -->
                    <div
                      v-if="batch.recipe_deviated"
                      class="inventory-dialog__record-detail"
                    >
                      <strong>레시피 변경</strong>
                      <div>{{ batch.recipe_deviation_note || "변경 내용 없음" }}</div>
                    </div>

                    <!-- 추천 생산량 참고 -->
                    <div
                      v-if="batch.recommendation_referenced || batch.recommendation_deviation_reason"
                      class="inventory-dialog__record-detail"
                    >
                      <div class="inventory-dialog__recommendation-header">
                        <strong>추천 생산량 참고</strong>

                        <span
                          class="inventory-dialog__recommendation-status"
                          :class="{
                            'inventory-dialog__recommendation-status--inactive':
                              !batch.recommendation_referenced,
                          }"
                        >
                          {{ batch.recommendation_referenced ? "참고함" : "참고 여부 미기록" }}
                        </span>
                      </div>

                      <div
                        v-if="batch.recommendation_deviation_reason"
                        class="inventory-dialog__recommendation-reason"
                      >
                        <span>추천 범위와 다르게 생산한 이유</span>
                        <p>{{ batch.recommendation_deviation_reason }}</p>
                      </div>
                    </div>

                    <!-- 생산 기록별 수정·삭제 -->
                    <div class="inventory-dialog__record-actions">
                      <v-btn
                        size="small"
                        variant="text"
                        prepend-icon="mdi-pencil-outline"
                        :disabled="!canMutate"
                        @click="editBatch(batch)"
                      >
                        수정
                      </v-btn>

                      <v-btn
                        size="small"
                        variant="text"
                        prepend-icon="mdi-delete-outline"
                        :disabled="!canMutate || saving"
                        @click="askDelete(batch)"
                      >
                        삭제
                      </v-btn>
                    </div>
                  </div>
                </div>
              </template>

              <div v-else class="inventory-dialog__production-empty">
                등록된 생산 기록이 없습니다.
              </div>
            </section>

            <!-- 이월 현황 -->
            <template v-if="selectedSection === 'carryover'">
              <span> 이월 재고 {{ displayQuantity(product?.carryover_in) }} </span>
              <span> 이월 예정 {{ displayQuantity(product?.carryover_out) }} </span>
            </template>

            <!-- 로스 현황 -->
            <span v-else-if="selectedSection === 'loss'">
              로스 {{ displayQuantity(product?.operational_loss ?? product?.loss) }}
            </span>

            <!-- 폐기 현황 -->
            <template v-else-if="selectedSection === 'waste'">
              <!-- 폐기는 최초 생산일 귀속 수량을 표시합니다. -->
              <span> 폐기 {{ displayQuantity(product?.attributed_waste) }} </span>

              <!-- 당일 생산해서 실제 폐기한 기록입니다. -->
              <div
                v-if="wasteDetails.currentTotal > 0"
                class="inventory-dialog__waste-details"
              >
                <strong>
                  당일 생산분 폐기
                  {{ displayQuantity(wasteDetails.currentTotal) }}
                </strong>

                <!-- 당일 생산분의 폐기 사유별 수량입니다. -->
                <div
                  v-for="(item, index) in wasteDetails.current"
                  :key="`${item.stock_lot_id}-${index}`"
                  class="inventory-dialog__waste-detail"
                >
                  <span>
                    {{ item.reason_text || "폐기 사유 미기재" }}
                  </span>
                  <strong>
                    {{ displayQuantity(item.quantity) }}
                  </strong>
                </div>
              </div>

              <!-- 이월 재고 폐기가 존재할 때만 표시합니다. -->
              <div
                v-if="wasteDetails.carryoverTotal > 0"
                class="inventory-dialog__waste-details"
              >
                <strong>
                  이월 재고 폐기
                  {{ displayQuantity(wasteDetails.carryoverTotal) }}
                </strong>

                <!-- 최초 생산일별 폐기 합계입니다. -->
                <div
                  v-for="item in wasteDetails.carryover"
                  :key="item.originDate"
                  class="inventory-dialog__waste-detail"
                >
                  <span> 최초 생산일 {{ item.originDate }} </span>

                  <strong>
                    {{ displayQuantity(item.quantity) }}
                  </strong>
                </div>
              </div>
            </template>

            <!-- 폐기율은 서버 계산값을 사용하는 조회 전용 항목입니다. -->
            <span v-else-if="selectedSection === 'waste_rate'">
              폐기율
              {{ product?.waste_rate == null ? "-" : `${product.waste_rate}%` }}
            </span>
          </div>

          <v-empty-state
            v-else
            title="업무를 선택해 주세요."
            icon="mdi-clipboard-text-outline"
          />
        </div>
      </v-card-text>

      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn variant="text" :disabled="saving" @click="requestClose"> 닫기 </v-btn>

        <v-spacer />

        <v-btn
          v-if="selectedSection === 'production' && editingBatch"
          variant="text"
          :disabled="saving"
          @click="cancelEdit"
        >
          수정 취소
        </v-btn>

        <v-btn
          v-if="selectedSection === 'production' && productionFormOpen"
          variant="flat"
          :loading="saving"
          :disabled="!canMutate || saving"
          @click="askSave"
        >
          {{ editingBatch ? "수정 내용 저장" : "생산 기록 저장" }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- 생산 기록 신규 등록 및 수정 전용 확인창 -->
  <ProductionSaveConfirmDialog
    v-model="saveConfirmOpen"
    :editing="Boolean(editingBatch)"
    :product-name="product?.name || '제품'"
    :quantity="Number(productionForm.quantity)"
    :original-quantity="Number(editingBatch?.quantity || 0)"
    :current-total="product?.production == null ? null : Number(product.production)"
    :expected-total="expectedProductionTotal"
    :average-quantity="productionAverage"
    :previous-quantity="previousProductionQuantity"
    :history-status="productionHistoryStatus"
    :selected-workers="selectedProductionWorkers"
    :note="productionForm.note"
    :abnormal="isAbnormalQuantity"
    :warning-title="productionWarningTitle"
    :warning-message="productionWarningMessage"
    :loading="saving"
    @confirm="saveProduction"
    :recipe-deviated="productionForm.recipeDeviated"
    :recipe-deviation-note="productionForm.recipeDeviationNote"
    :recommendation-referenced="productionForm.recommendationReferenced"
    :recommendation-deviation-reason="productionForm.recommendationDeviationReason"
  />

  <!-- 생산 0개 최종 확인 -->
  <ConfirmDialog
    v-model="confirmZeroOpen"
    title="생산 0개 확인"
    message="오늘 이 제품을 생산하지 않은 것으로 확정하시겠습니까? 선택한 사유는 이후 분석에서 일반 수요와 구분해 사용합니다."
    :loading="saving"
    @confirm="saveZero"
  />

  <!-- 작성 중 닫기 또는 다른 업무 이동 확인 -->
  <ConfirmDialog
    v-model="discardConfirmOpen"
    title="작성 중인 내용 취소"
    message="저장하지 않은 내용이 있습니다. 작성 내용을 취소하고 계속하시겠습니까?"
    :loading="saving"
    @confirm="confirmDiscard"
  />

  <!-- 생산 기록 삭제 확인 -->
  <ConfirmDialog
    v-model="deleteConfirmOpen"
    title="생산 기록 삭제"
    message="이 생산 기록을 삭제하시겠습니까? 이미 이월과 연결된 기록은 삭제할 수 없습니다."
    :loading="saving"
    @confirm="deleteBatch"
  />
</template>

<script setup>
import { computed, reactive, ref, watch, nextTick } from "vue";
import ConfirmDialog from "../common/ConfirmDialog.vue";
import ProductionSaveConfirmDialog from "./ProductionSaveConfirmDialog.vue";
import { addLocalDays } from "../../utils/localDate";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  // 선택한 점포 ID: 기존 생산 API에서 사용합니다.
  storeId: {
    type: Number,
    default: null,
  },

  // 생산 0개 확인에 사용할 기존 사유 목록입니다.
  zeroReasons: {
    type: Array,
    default: () => [],
  },

  // 일반 수정 가능 여부를 부모와 동일하게 유지합니다.
  canMutate: {
    type: Boolean,
    default: false,
  },

  // 생산 담당자 선택에 사용하는 기존 작업자 목록입니다.
  workers: {
    type: Array,
    default: () => [],
  },

  product: {
    type: Object,
    default: null,
  },

  // 서버에서 전달받은 생산 기록별 재고 출처 목록입니다.
  stockSources: {
    type: Array,
    default: () => [],
  },

  workDate: {
    type: String,
    default: "",
  },

  sections: {
    type: Array,
    default: () => [],
  },

  initialSection: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue", "section-change", "saved", "error"]);

/**
 * 선택 날짜에 실제 처리한 폐기 내역을 분류합니다.
 *
 * - 당일 생산분은 폐기 사유별로 보존합니다.
 * - 이월 재고 폐기는 최초 생산일별로 합산합니다.
 * - 원래 재고 기록과 사유는 details 배열에 보존합니다.
 * - 데이터가 없으면 해당 상세 영역을 숨깁니다.
 */
const wasteDetails = computed(() => {
  const current = [];
  const carryoverByDate = new Map();

  for (const detail of props.product?.operational_waste_details || []) {
    const quantity = Number(detail.quantity);
    const originDate = detail.origin_production_date;

    // 날짜나 수량이 유효하지 않으면 임의로 분류하지 않습니다.
    if (!originDate || !Number.isFinite(quantity) || quantity <= 0) {
      continue;
    }

    const item = {
      ...detail,
      quantity,
      originDate,
    };

    // 선택 날짜에 생산한 제품의 실제 폐기입니다.
    if (originDate === props.workDate) {
      current.push(item);
      continue;
    }

    // 이월 재고 폐기를 최초 생산일별로 그룹화합니다.
    if (!carryoverByDate.has(originDate)) {
      carryoverByDate.set(originDate, {
        originDate,
        quantity: 0,
        details: [],
      });
    }

    const group = carryoverByDate.get(originDate);

    group.quantity += quantity;
    group.details.push(item);
  }

  const carryover = [...carryoverByDate.values()].sort((a, b) =>
    a.originDate.localeCompare(b.originDate)
  );

  return {
    current,
    carryover,

    currentTotal: current.reduce((total, item) => total + item.quantity, 0),

    carryoverTotal: carryover.reduce((total, group) => total + group.quantity, 0),
  };
});

/**
 * 통합 다이얼로그의 생산 입력 상태입니다.
 *
 * 기존 ProductionBatchDialog의 필드 구조를 유지합니다.
 */
const productionForm = reactive({
  quantity: 1,
  workerIds: [],
  recipeDeviated: false,
  recipeDeviationNote: "",
  recommendationReferenced: false,
  recommendationDeviationReason: "",
  note: "",
});

const saveConfirmOpen = ref(false);

// 생산 입력 필수 항목 오류 표시
const productionErrors = reactive({
  quantity: false,
  workers: false,
  recipeDeviationNote: false,
});

const productionHistory = ref([]);
const productionHistoryStatus = ref("idle");
const previousProduction = ref(null);

// 전날 생산량 조회 상태
const previousProductionLoading = ref(false);

// 이전 조회 결과가 새 제품에 섞이지 않도록 요청을 구분합니다.
let previousProductionRequestId = 0;

/**
 * 전날 생산량 조회
 *
 * production_confirmed가 true인 경우에만 사용합니다.
 * 생산 0개와 미확인 상태를 구분합니다.
 */
async function loadPreviousProduction() {
  const requestId = ++previousProductionRequestId;

  previousProduction.value = null;

  if (!props.storeId || !props.workDate || !props.product?.id) {
    previousProductionLoading.value = false;
    return;
  }

  previousProductionLoading.value = true;

  try {
    const previousDate = addLocalDays(props.workDate, -1);

    const response = await window.axios.get(
      "/tillwhite/api/production-management/daily",
      {
        params: {
          date: previousDate,
          store_id: props.storeId,
        },
      }
    );

    if (requestId !== previousProductionRequestId) return;

    const previousRow = (response.data?.rows || []).find(
      (row) => Number(row.id) === Number(props.product.id)
    );

    if (previousRow?.production_confirmed) {
      previousProduction.value = Number(previousRow.production);
    }
  } catch (error) {
    if (requestId !== previousProductionRequestId) return;

    emit("error", error.response?.data?.message || "전날 생산량을 조회하지 못했습니다.");
  } finally {
    if (requestId === previousProductionRequestId) {
      previousProductionLoading.value = false;
    }
  }
}

/**
 * 전날 생산량을 신규 생산 수량 입력값에 적용합니다.
 */
function applyPreviousProduction() {
  if (editingBatch.value || !props.canMutate || previousProduction.value === null) {
    return;
  }

  if (previousProduction.value === 0) {
    emit("error", "전날 생산량이 0개입니다. 생산 0개 확인 기능을 이용해 주세요.");
    return;
  }

  productionForm.quantity = previousProduction.value;
}

let productionHistoryRequestId = 0;

const productionWarningTitle = "생산 수량 확인 필요";
const productionWarningMessage =
  "최근 생산 기록보다 입력 수량이 크게 증가했습니다. 수량을 다시 확인해 주세요.";

const selectedProductionWorkers = computed(() =>
  productionForm.workerIds
    .map((workerId) =>
      props.workers.find((worker) => Number(worker.id) === Number(workerId))
    )
    .filter(Boolean)
);

const expectedProductionTotal = computed(() => {
  const rawTotal = props.product?.production;
  const quantity = Number(productionForm.quantity);

  // 미확인 생산량과 실제 0개를 구분
  if (rawTotal === null || rawTotal === undefined || rawTotal === "") {
    return null;
  }

  const currentTotal = Number(rawTotal);

  if (
    !Number.isFinite(currentTotal) ||
    currentTotal < 0 ||
    !Number.isInteger(quantity) ||
    quantity < 1
  ) {
    return null;
  }

  if (editingBatch.value) {
    const original = Number(editingBatch.value.quantity);

    if (!Number.isFinite(original)) return null;

    return currentTotal - original + quantity;
  }

  return currentTotal + quantity;
});

const productionAverage = computed(() => {
  if (productionHistoryStatus.value !== "success") {
    return null;
  }

  const quantities = productionHistory.value
    .map((row) => row.quantity)
    .filter(
      (quantity) =>
        quantity !== null && quantity !== undefined && Number.isFinite(Number(quantity))
    )
    .map(Number);

  if (quantities.length < 3) {
    return null;
  }

  return quantities.reduce((sum, quantity) => sum + quantity, 0) / quantities.length;
});

/**
 * 다이얼로그를 열거나 제품·날짜·점포가 변경되면
 * 전날 생산량을 새로 조회합니다.
 */
watch(
  () => [props.modelValue, props.product?.id, props.workDate, props.storeId],
  () => {
    if (props.modelValue) {
      loadPreviousProduction();
    } else {
      // 닫힌 화면의 이전 요청 결과를 무효화합니다.
      previousProductionRequestId++;
      previousProductionLoading.value = false;
      previousProduction.value = null;
    }
  },
  { immediate: true }
);

const previousProductionQuantity = computed(() => {
  if (productionHistoryStatus.value !== "success") {
    return null;
  }

  const previousDate = addLocalDays(props.workDate, -1);

  const quantity = productionHistory.value.find((row) => row.date === previousDate)
    ?.quantity;

  return quantity == null ? null : Number(quantity);
});

const isAbnormalQuantity = computed(() => {
  const average = productionAverage.value;
  const total = expectedProductionTotal.value;

  if (average === null || average <= 0 || total === null) {
    return false;
  }

  const previous = previousProductionQuantity.value;
  const baseline = previous !== null ? Math.max(average, previous) : average;

  return total >= baseline * 2;
});

/**
 * 최근 7일 생산 이력을 조회합니다.
 * 기존 ProductionBatchDialog와 동일한 API 및 기준입니다.
 */
async function loadProductionHistory() {
  const requestId = ++productionHistoryRequestId;

  productionHistory.value = [];
  productionHistoryStatus.value = "idle";

  if (!props.storeId || !props.workDate || !props.product?.id) {
    return;
  }

  productionHistoryStatus.value = "loading";

  try {
    const previousDate = addLocalDays(props.workDate, -1);

    const response = await window.axios.get(
      `/tillwhite/api/production-management/products/${props.product.id}`,
      {
        params: {
          work_date: previousDate,
        },
      }
    );

    if (requestId !== productionHistoryRequestId) return;

    const history = response.data?.production_history;

    if (!Array.isArray(history) || history.length !== 7) {
      throw new Error("생산 이력 응답이 올바르지 않습니다.");
    }

    productionHistory.value = history;
    productionHistoryStatus.value = "success";
  } catch {
    if (requestId !== productionHistoryRequestId) return;

    productionHistory.value = [];
    productionHistoryStatus.value = "error";
  }
}

/**
 * 다이얼로그가 열리거나 제품·날짜가 바뀌면 이력을 갱신합니다.
 */
watch(
  () => [props.modelValue, props.product?.id, props.storeId, props.workDate],
  () => {
    if (props.modelValue) {
      loadProductionHistory();
    } else {
      productionHistoryRequestId++;
      productionHistory.value = [];
      productionHistoryStatus.value = "idle";
    }
  },
  { immediate: true }
);

/**
 * 저장 전 필수 입력을 검증합니다.
 */
function askSave() {
  if (!props.canMutate || !props.product?.id || saving.value) {
    return;
  }

  // 이전 검사 오류 초기화
  productionErrors.quantity = false;
  productionErrors.workers = false;
  productionErrors.recipeDeviationNote = false;

  const quantity = Number(productionForm.quantity);

  if (!Number.isInteger(quantity) || quantity < 1) {
    productionErrors.quantity = true;
    productionCardIndex.value = 0;
    emit("error", "올바른 생산 수량을 입력해 주세요.");
    return;
  }

  if (!productionForm.workerIds.length) {
    productionErrors.workers = true;
    productionCardIndex.value = 0;
    emit("error", "작업자를 선택해 주세요.");
    return;
  }

  if (
    !editingBatch.value &&
    productionForm.recipeDeviated &&
    !productionForm.recipeDeviationNote.trim()
  ) {
    productionErrors.recipeDeviationNote = true;
    productionCardIndex.value = 1;
    emit("error", "변경한 작업 내용을 입력해 주세요.");
    return;
  }

  saveConfirmOpen.value = true;
}

/**
 * 기존 생산 등록·수정 API를 사용합니다.
 * 서버에서 성공한 경우에만 부모 화면을 갱신합니다.
 */
async function saveProduction() {
  if (!props.canMutate || saving.value) return;

  saving.value = true;

  try {
    if (editingBatch.value) {
      await window.axios.put(
        `/tillwhite/api/production-management/batches/${editingBatch.value.id}`,
        {
          quantity: Number(productionForm.quantity),
          note: productionForm.note || null,
          lock_version: editingBatch.value.lock_version,
          workers: productionForm.workerIds.map((userId) => ({
            user_id: userId,
          })),
        }
      );
    } else {
      await window.axios.post("/tillwhite/api/production-management/batches", {
        store_id: props.storeId,
        product_id: props.product.id,
        work_date: props.workDate,
        quantity: Number(productionForm.quantity),
        recipe_deviated: productionForm.recipeDeviated,
        recipe_deviation_note: productionForm.recipeDeviationNote || null,
        recommendation_referenced: productionForm.recommendationReferenced,
        recommendation_deviation_reason:
          productionForm.recommendationDeviationReason || null,
        note: productionForm.note || null,
        workers: productionForm.workerIds.map((userId) => ({
          user_id: userId,
          process_type: "all",
        })),
      });
    }

    const message = editingBatch.value
      ? "생산 기록을 수정했습니다."
      : "생산 기록을 저장했습니다.";

    saveConfirmOpen.value = false;
    resetProductionEditor();

    emit("update:modelValue", false);
    emit("saved", message);
  } catch (error) {
    emit("error", error.response?.data?.message || "생산 기록을 저장하지 못했습니다.");
  } finally {
    saving.value = false;
  }
}

// 생산 0개 확인
const confirmZeroOpen = ref(false);
const zeroReason = ref(null);
const zeroReasonText = ref("");
const zeroNote = ref("");

// 기존 사유 코드를 유지하고 표시 이름만 변경합니다.
const displayZeroReasons = computed(() =>
  props.zeroReasons.map((reason) => ({
    ...reason,
    title: reason.value === "other" ? "직접입력" : reason.title,
  }))
);

// 사유를 올바르게 입력한 경우에만 확인할 수 있습니다.
const canConfirmZero = computed(
  () =>
    Boolean(zeroReason.value) &&
    (zeroReason.value !== "other" || Boolean(zeroReasonText.value.trim()))
);

// 미저장 내용 취소 확인창
const discardConfirmOpen = ref(false);

// 사용자가 확인한 후 실행할 동작
const pendingDiscardAction = ref(null);

/**
 * 생산 입력값의 기본 상태입니다.
 * 신규 입력 상태에서 기본값과 차이가 있을 때만 변경으로 판단합니다.
 */
function hasUnsavedProductionChanges() {
  if (editingBatch.value) {
    return true;
  }

  return (
    Number(productionForm.quantity) !== 1 ||
    productionForm.workerIds.length > 0 ||
    productionForm.recipeDeviated ||
    Boolean(productionForm.recipeDeviationNote.trim()) ||
    productionForm.recommendationReferenced ||
    Boolean(productionForm.recommendationDeviationReason.trim()) ||
    Boolean(productionForm.note.trim()) ||
    Boolean(zeroReason.value) ||
    Boolean(zeroReasonText.value.trim()) ||
    Boolean(zeroNote.value.trim())
  );
}

/**
 * 변경 내용이 있으면 확인을 요청합니다.
 * 변경 내용이 없으면 지정한 동작을 바로 수행합니다.
 */
function guardUnsavedChanges(action) {
  if (saving.value) {
    return;
  }

  if (selectedSection.value === "production" && hasUnsavedProductionChanges()) {
    pendingDiscardAction.value = action;
    discardConfirmOpen.value = true;
    return;
  }

  action();
}

/**
 * 작성 취소를 확정한 경우에만
 * 입력값을 초기화하고 대기 중이던 동작을 실행합니다.
 */
function confirmDiscard() {
  if (saving.value) {
    return;
  }

  const action = pendingDiscardAction.value;

  pendingDiscardAction.value = null;
  discardConfirmOpen.value = false;

  resetProductionEditor();

  if (typeof action === "function") {
    action();
  }
}

/**
 * 생산 입력 화면을 신규 기록 상태로 초기화합니다.
 */
function resetProductionEditor() {
  productionErrors.quantity = false;
  productionErrors.workers = false;
  productionErrors.recipeDeviationNote = false;
  productionFormOpen.value = false;
  productionCardIndex.value = 0;
  zeroCardOpen.value = false;
  editingBatch.value = null;
  expandedBatchIds.value = [];

  productionForm.quantity = 1;
  productionForm.workerIds = [];
  productionForm.recipeDeviated = false;
  productionForm.recipeDeviationNote = "";
  productionForm.recommendationReferenced = false;
  productionForm.recommendationDeviationReason = "";
  productionForm.note = "";

  zeroReason.value = null;
  zeroReasonText.value = "";
  zeroNote.value = "";
}

/**
 * 현재 수정 중인 생산 기록입니다.
 * null이면 신규 생산 기록 입력 상태입니다.
 */
const editingBatch = ref(null);

// 생산 기록 상세 펼침 상태
const expandedBatchIds = ref([]);

function toggleBatch(batchId) {
  if (expandedBatchIds.value.includes(batchId)) {
    expandedBatchIds.value = expandedBatchIds.value.filter(
      (id) => id !== batchId
    );
  } else {
    expandedBatchIds.value = [...expandedBatchIds.value, batchId];
  }
}

// 삭제 확인 대상 생산 기록
const deletingBatch = ref(null);

// 삭제 확인창
const deleteConfirmOpen = ref(false);

// 생산 기록 저장·삭제 요청 중 중복 실행을 방지합니다.
const saving = ref(false);

const productionEditor = ref(null);

// 생산 입력 영역 펼침 여부
const productionFormOpen = ref(false);

// 생산 0개 확인 영역 펼침 여부
const zeroCardOpen = ref(false);

const productionCardTitles = [
  "생산 입력",
  "레시피 · 추천",
  "메모 · 확인",
];

const lastProductionCardIndex = productionCardTitles.length - 1;

// 생산 입력 카드: 0 기본 입력, 1 레시피·추천, 2 메모
const productionCardIndex = ref(0);

// 카드 스와이프 시작 위치를 기억합니다.
let productionSwipeStart = null;

// 입력창이나 버튼을 조작할 때는 스와이프하지 않습니다.
const productionSwipeIgnoreSelector = [
  "input",
  "textarea",
  "select",
  "button",
  "a",
  "label",
  '[role="button"]',
  '[role="checkbox"]',
  '[role="combobox"]',
  '[contenteditable="true"]',
  ".v-input",
  ".v-btn",
].join(",");

function onProductionSwipeStart(event) {
  productionSwipeStart = null;

  if (!productionFormOpen.value || saving.value) {
    return;
  }

  // 마우스의 경우 왼쪽 버튼 드래그만 허용합니다.
  if (event.pointerType === "mouse" && event.button !== 0) {
    return;
  }

  const target = event.target;

  if (target instanceof Element && target.closest(productionSwipeIgnoreSelector)) {
    return;
  }

  productionSwipeStart = {
    pointerId: event.pointerId,
    x: event.clientX,
    y: event.clientY,
  };

  // 카드 밖에서 마우스를 놓아도 종료 이벤트를 받습니다.
  event.currentTarget?.setPointerCapture?.(event.pointerId);
}

function onProductionSwipeEnd(event) {
  if (event.currentTarget?.hasPointerCapture?.(event.pointerId)) {
    event.currentTarget.releasePointerCapture(event.pointerId);
  }

  const start = productionSwipeStart;
  productionSwipeStart = null;

  if (!start || start.pointerId !== event.pointerId) {
    return;
  }

  if (!productionFormOpen.value || saving.value) {
    return;
  }

  const deltaX = event.clientX - start.x;
  const deltaY = event.clientY - start.y;

  // 짧은 움직임과 세로 스크롤은 카드 전환으로 처리하지 않습니다.
  if (Math.abs(deltaX) < 60 || Math.abs(deltaX) <= Math.abs(deltaY) * 1.25) {
    return;
  }

  if (deltaX < 0) {
    productionCardIndex.value = Math.min(
      lastProductionCardIndex,
      productionCardIndex.value + 1
    );
  } else {
    productionCardIndex.value = Math.max(0, productionCardIndex.value - 1);
  }
}

function onProductionSwipeCancel() {
  productionSwipeStart = null;
}

/**
 * 선택한 생산 기록의 기존 데이터를 편집 폼에 불러옵니다.
 */
function applyBatchToEditor(batch) {
  editingBatch.value = batch;
  productionCardIndex.value = 0;
  zeroCardOpen.value = false;

  // 기존 생산 기록 수정 시 입력 영역을 자동으로 엽니다.
  productionFormOpen.value = true;

  productionForm.quantity = Number(batch.quantity);

  productionForm.workerIds = (batch.workers || []).map((worker) => worker.id);

  productionForm.recipeDeviated = Boolean(batch.recipe_deviated);

  productionForm.recipeDeviationNote = batch.recipe_deviation_note || "";

  productionForm.recommendationReferenced = Boolean(batch.recommendation_referenced);

  productionForm.recommendationDeviationReason =
    batch.recommendation_deviation_reason || "";

  productionForm.note = batch.note || "";

  // 기존 생산 기록을 불러온 후 편집 영역으로 이동
  nextTick(() => {
    productionEditor.value?.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  });
}

/**
 * 다른 생산 기록을 선택하기 전에
 * 현재 작성 중인 내용이 있는지 확인합니다.
 */
function editBatch(batch) {
  if (!props.canMutate || saving.value || !batch?.id) {
    return;
  }

  // 이미 수정 중인 같은 기록을 다시 선택한 경우
  if (editingBatch.value?.id === batch.id) {
    return;
  }

  // 작성 내용이 없다면 바로 기록을 불러옵니다.
  // 작성 내용이 있다면 기존 작성 취소 확인창을 사용합니다.
  guardUnsavedChanges(() => {
    applyBatchToEditor(batch);
  });
}

/**
 * 생산 기록 수정 모드를 종료하고
 * 새 생산 기록 입력 상태로 돌아갑니다.
 */
function cancelEdit() {
  if (saving.value) {
    return;
  }

  guardUnsavedChanges(() => {
    resetProductionEditor();
    productionFormOpen.value = true;
  });
}

/**
 * 삭제 대상을 선택하고 확인창을 표시합니다.
 */
function askDelete(batch) {
  if (!props.canMutate || !batch) {
    return;
  }

  deletingBatch.value = batch;
  deleteConfirmOpen.value = true;
}

/**
 * 생산 0개 확인
 * 기존 ProductionBatchDialog와 동일한 API를 사용합니다.
 */
async function saveZero() {
  if (
    !props.canMutate ||
    !props.storeId ||
    !props.product?.id ||
    !props.workDate ||
    !canConfirmZero.value ||
    Boolean(editingBatch.value) ||
    saving.value
  ) {
    return;
  }

  saving.value = true;

  try {
    await window.axios.post("/tillwhite/api/production-management/zero-production", {
      store_id: props.storeId,
      product_id: props.product.id,
      work_date: props.workDate,
      reason: zeroReason.value,
      reason_text: zeroReason.value === "other" ? zeroReasonText.value.trim() : null,
      note: zeroNote.value || null,
    });

    confirmZeroOpen.value = false;

    // 다음에 다이얼로그를 열 때 이전 입력값이 남지 않도록 정리합니다.
    resetProductionEditor();

    // 성공한 경우에만 부모 화면을 갱신합니다.
    emit("update:modelValue", false);
    emit("saved", "생산 0개를 확인했습니다.");
  } catch (error) {
    emit("error", error.response?.data?.message || "생산 0개를 확인하지 못했습니다.");
  } finally {
    saving.value = false;
  }
}

/**
 * 기존 생산 기록을 삭제합니다.
 *
 * 기존 ProductionBatchDialog와 동일한 API를 사용하며,
 * 이월 연결 여부 등 삭제 가능성은 서버에서 검증합니다.
 */
async function deleteBatch() {
  if (!props.canMutate || !deletingBatch.value?.id || saving.value) {
    return;
  }

  saving.value = true;

  try {
    await window.axios.delete(
      `/tillwhite/api/production-management/batches/${deletingBatch.value.id}`,
      {
        data: {
          memo: null,
        },
      }
    );

    // 삭제 성공 시 확인창과 통합 다이얼로그를 닫습니다.
    deleteConfirmOpen.value = false;
    emit("update:modelValue", false);

    // 부모 화면에서 기존 일일 현황 새로고침을 실행합니다.
    emit("saved", "생산 기록을 삭제했습니다.");

    deletingBatch.value = null;
    editingBatch.value = null;
    expandedBatchIds.value = [];
  } catch (error) {
    // 서버가 삭제를 거부한 경우 확인창과 기존 기록을 유지합니다.
    emit("error", error.response?.data?.message || "생산 기록을 삭제하지 못했습니다.");
  } finally {
    saving.value = false;
  }
}

/**
 * 통합 다이얼로그를 새로 열거나
 * 선택한 제품 및 날짜가 변경되면 생산 입력 상태를 초기화합니다.
 *
 * 같은 제품과 같은 날짜의 일반 데이터 갱신에서는
 * 작성 중인 내용을 초기화하지 않습니다.
 */
watch(
  () => [props.product?.id, props.workDate, props.modelValue],
  ([productId, workDate, isOpen], previous = []) => {
    if (!isOpen || !productId || !workDate) {
      return;
    }

    const [previousProductId, previousWorkDate, wasOpen] = previous;

    if (wasOpen && previousProductId === productId && previousWorkDate === workDate) {
      return;
    }

    resetProductionEditor();

    // 이전 작성 취소 요청 상태 초기화
    pendingDiscardAction.value = null;
    discardConfirmOpen.value = false;

    // 이전 삭제 대상 및 확인창 초기화
    deletingBatch.value = null;
    deleteConfirmOpen.value = false;
  },
  { immediate: true }
);

// 통합 다이얼로그에서 현재 선택한 업무를 관리합니다.
const selectedSection = ref("");

// 부모의 업무 메뉴 설정에서 현재 선택된 항목을 조회합니다.
const activeSection = computed(
  () => props.sections.find((section) => section.value === selectedSection.value) || null
);

/**
 * 작성 내용 검사를 거쳐 다이얼로그를 닫습니다.
 */
function requestClose() {
  guardUnsavedChanges(() => {
    emit("update:modelValue", false);
  });
}

/**
 * v-dialog에서 전달되는 닫기 요청도
 * 동일한 작성 내용 보호 절차를 사용합니다.
 */
function onDialogModelChange(value) {
  if (!value) {
    requestClose();
  }
}

/**
 * 다른 업무 메뉴로 이동할 때
 * 생산 입력 중인 내용이 있으면 확인창을 표시합니다.
 */
function selectSection(value) {
  if (
    !props.sections.some((section) => section.value === value) ||
    value === selectedSection.value
  ) {
    return;
  }

  guardUnsavedChanges(() => {
    selectedSection.value = value;
    emit("section-change", value);
  });
}

/**
 * 다이얼로그를 열면 부모가 지정한 업무를 선택합니다.
 * 지정한 업무가 없으면 첫 번째 메뉴를 선택합니다.
 */
watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) return;

    const requested = props.sections.find(
      (section) => section.value === props.initialSection
    );

    selectedSection.value =
      requested?.value || props.sections[0]?.value || "";
  },
  { immediate: true }
);

/**
 * 서버에서 받은 수량을 표시합니다.
 * 0개와 미확인 상태를 구분합니다.
 */
function displayQuantity(value) {
  if (value === null || value === undefined || value === "") {
    return "미확인";
  }

  const quantity = Number(value);

  return Number.isFinite(quantity) ? `${quantity}개` : "미확인";
}
</script>

<style scoped>
/* 다이얼로그 전체 기본 글씨 */
.inventory-dialog {
  font-size: 12px;
}

/* 버튼과 입력 항목 */
.inventory-dialog :deep(.v-btn),
.inventory-dialog :deep(.v-field),
.inventory-dialog :deep(.v-label) {
  font-size: 12px;
}

/* 기존 생산 기록: 생산량 헤더 */
.inventory-dialog__production-record-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  width: 100%;
  font: inherit;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

/* 왼쪽 생산량 제목 */
.inventory-dialog__production-record-label {
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

/* 오른쪽 생산 수량 */
.inventory-dialog__production-record-quantity {
  font-size: 1rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* 기존 생산 기록 상세 정보 박스 */
.inventory-dialog__production-record .inventory-dialog__record-detail {
  display: block;
  margin-bottom: 10px;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  font-size: 0.8rem;
  line-height: 1.6;
  overflow-wrap: anywhere;
}

.inventory-dialog__production-record .inventory-dialog__record-detail strong {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
}

.inventory-dialog__production-record .inventory-dialog__record-detail > div {
  color: rgba(var(--v-theme-on-surface), 0.72);
  white-space: pre-wrap;
}

/* 제품명과 날짜 헤더 */
.inventory-dialog__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  width: 100%;
}

/* 제품 정보 */
.inventory-dialog__heading-info {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 0;
}

.inventory-dialog__heading-info span {
  font-size: 11px;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.inventory-dialog__heading-info strong {
  font-size: 20px;
  font-weight: 600;
  line-height: 1.3;
  overflow-wrap: anywhere;
}

/* 날짜 */
.inventory-dialog__heading-date {
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

/* 제품별 재고 현황 요약입니다. */
.inventory-dialog__summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 20px;
}

.inventory-dialog__summary > div {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 0;
  padding: 14px 8px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.inventory-dialog__summary span {
  font-size: 12px;
  opacity: 0.7;
}

.inventory-dialog__summary strong {
  font-size: 18px;
}

/* 업무 탭 전체 */
.inventory-dialog__navigation {
  display: flex;
  align-items: stretch;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 18px;
  padding: 5px;
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.05);
}

/* 개별 탭 */
.inventory-dialog__tab {
  flex: 1 1 auto;
  min-width: 55px;
  padding: 10px 12px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
}

/* 마우스를 올렸을 때 */
.inventory-dialog__tab:hover {
  color: rgb(var(--v-theme-on-surface));
}

/* 현재 선택된 탭 */
.inventory-dialog__tab--active {
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

/* 키보드 접근성 */
.inventory-dialog__tab:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 업무별 상세 조회 영역입니다. */
.inventory-dialog__content {
  min-height: 220px;
}

.inventory-dialog__placeholder {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
  min-height: 220px;
  padding: 18px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  background: transparent;
  text-align: left;
}

.inventory-dialog__placeholder span {
  font-size: 13px;
  opacity: 0.7;
}

/* 폐기 상세정보는 실제 기록이 있을 때만 표시합니다. */
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

/* 모바일에서도 요약과 메뉴를 보기 쉽게 표시합니다. */
@media (max-width: 600px) {
  .inventory-dialog__summary strong {
    font-size: 16px;
  }

  .inventory-dialog__navigation {
    gap: 6px;
  }
}

/* 기존 다이얼로그와 동일한 밀도의 생산 기록 영역입니다. */
.inventory-dialog__production {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  text-align: left;
  font-size: 13px;
}

/* 생산 합계 · 생산 기록 미니멀 스타일 */
.inventory-dialog__production-summary,
.inventory-dialog__production-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  margin: 0;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

/* 첫 번째 줄 */
.inventory-dialog__production-summary {
  margin-top: 6px;
  border-radius: 12px 12px 0 0;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

/* 두 번째 줄 */
.inventory-dialog__production-heading {
  margin-bottom: 6px;
  border-radius: 0 0 12px 12px;
}

/* 생산 합계 · 생산 기록 모든 텍스트 통일 */
.inventory-dialog__production-summary > strong,
.inventory-dialog__production-heading > strong {
  font-size: 15px;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.6;
}

/* 생산 기록은 작은 카드로 간결하게 표시합니다. */
.inventory-dialog__production-record {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 6px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.07);
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.025);
  font-size: 12px;
  line-height: 1.5;
}

.inventory-dialog__production-record-header strong {
  font-size: 0.8rem;
  font-weight: 600;
}

/* 생산 입력 카드 탐색 */
.inventory-dialog__card-navigation {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 12px;
}

.inventory-dialog__card-steps {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
}

.inventory-dialog__card-steps :deep(.v-btn) {
  min-width: 0;
  padding-inline: 4px;
  font-size: 11px;
}

.inventory-dialog__card-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.inventory-dialog__card-controls span {
  font-size: 12px;
  opacity: 0.7;
}

/* 카드 좌우 스와이프 영역 */
.inventory-dialog__swipe-area {
  width: 100%;
  min-width: 0;

  /* 세로 스크롤은 유지하고 가로 제스처는 카드에서 처리합니다. */
  touch-action: pan-y;

  margin-top: 4px;
}

/* 생산 입력 카드 공통 디자인 */
.inventory-dialog__swipe-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  min-height: 220px;
  padding: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  text-align: left;
  animation: inventory-card-enter 0.2s ease-out;
}

@keyframes inventory-card-enter {
  from {
    opacity: 0;
    transform: translateY(6px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .inventory-dialog__swipe-card {
    animation: none;
  }
}

/* 모바일 카드 여백 */
@media (max-width: 600px) {
  .inventory-dialog__swipe-card {
    padding: 12px;
    min-height: 200px;
  }
}

/* 생산 입력 영역 */
.inventory-dialog__production-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  text-align: left;
  font-size: 13px;
}

.inventory-dialog__field-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.inventory-dialog__field-heading strong {
  font-size: 13px;
  font-weight: 600;
}

.inventory-dialog__field-heading span {
  font-size: 12px;
  opacity: 0.7;
}

.inventory-dialog__production-form :deep(.v-label) {
  font-size: 13px;
}

.inventory-dialog__production-form :deep(.v-field__input) {
  font-size: 13px;
}

.inventory-dialog__production-form :deep(.v-selection-control__wrapper) {
  font-size: 16px;
}

.inventory-dialog__form-notice {
  padding: 8px 10px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
}

/* 생산 기록별 수정·삭제 버튼 */
.inventory-dialog__record-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

.inventory-dialog__form-help {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

/* 전날 생산량 참고 */
.inventory-dialog__previous-production {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 8px;
  text-align: left;
}

.inventory-dialog__previous-production > div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.inventory-dialog__previous-production span {
  font-size: 11px;
  opacity: 0.7;
}

.inventory-dialog__previous-production strong {
  font-size: 15px;
}

/* 생산 0개 확인 카드 */
.inventory-dialog__zero-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  margin-top: 12px;
  text-align: left;
}

.inventory-dialog__zero-card-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
}

@media (max-width: 600px) {
  .inventory-dialog__zero-card-content {
    padding: 12px;
  }
}

/* 오른쪽 생산 수량만 별도로 강조 */
.inventory-dialog__production-record-header
.inventory-dialog__production-record-quantity {
  font-size: 1rem;
  font-weight: 700;
}

/* 추천 생산량 제목과 참고 여부 */
.inventory-dialog__recommendation-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.inventory-dialog__recommendation-header strong {
  margin-bottom: 0;
  font-size: inherit;
  font-weight: inherit;
  line-height: inherit;
}

.inventory-dialog__recommendation-status {
  flex-shrink: 0;
  padding: 3px 9px;
  border-radius: 999px;
  background: rgba(var(--v-theme-success), 0.12);
  color: rgb(var(--v-theme-success));
  font-size: 0.72rem;
  font-weight: 600;
}

.inventory-dialog__recommendation-status--inactive {
  background: rgba(var(--v-theme-on-surface), 0.07);
  color: rgba(var(--v-theme-on-surface), 0.6);
}

/* 추천 생산량 변경 사유 */
.inventory-dialog__production-record
.inventory-dialog__recommendation-reason {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.inventory-dialog__recommendation-reason span {
  display: block;
  margin-bottom: 5px;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.inventory-dialog__recommendation-reason p {
  margin: 0;
  font-size: 0.8rem;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

/* 생산 기록의 모든 항목 제목 통일 */
.inventory-dialog__production-record .inventory-dialog__record-detail strong {
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

.inventory-dialog__production-record-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.inventory-dialog__production-record-header:hover {
  background: rgba(var(--v-theme-on-surface), 0.065);
}

.inventory-dialog__production-record-header:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 생산 기록별 재고 - 통합 카드 */
.inventory-dialog__stock {
  width: 100%;
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  background: transparent;
  text-align: left;
}

/* 제목 */
.inventory-dialog__stock-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.inventory-dialog__stock-heading strong,
.inventory-dialog__stock-heading span {
  font-size: 13px;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
}

/* 재고 목록 */
.inventory-dialog__stock-list {
  display: flex;
  flex-direction: column;
}

/* 개별 기록 - 카드 대신 구분선 */
.inventory-dialog__stock-item {
  padding: 14px 0;
  border: none;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 0;
  background: transparent;
}

.inventory-dialog__stock-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

/* 날짜와 수량 */
.inventory-dialog__stock-item-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.inventory-dialog__stock-date {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  font-weight: 500;
}

.inventory-dialog__stock-quantity {
  font-size: 17px;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
  white-space: nowrap;
}

/* 설명 */
.inventory-dialog__stock-item small {
  display: block;
  margin-top: 6px;
  font-size: 11px;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

/* 기록 없음 */
.inventory-dialog__stock-empty {
  padding: 14px 0 0;
  font-size: 12px;
  text-align: center;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
</style>
