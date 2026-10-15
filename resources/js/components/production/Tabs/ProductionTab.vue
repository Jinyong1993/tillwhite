<template>
  <div class="inventory-dialog inventory-dialog__production-tab">
    <div class="inventory-dialog__placeholder">
      <strong>생산 관리</strong>

      <p class="inventory-dialog__form-help">
        생산 수량을 기록하고 생산 내역을 관리합니다.
        등록한 생산 기록은 아래 목록에서 확인하거나 수정할 수 있습니다.
      </p>

      <!-- 생산 입력 및 생산 0개 확인 버튼 -->
      <div class="inventory-dialog__action-grid">
        <v-btn
          block
          variant="tonal"
          :disabled="saving || Boolean(editingBatch)"
          :prepend-icon="productionFormOpen ? 'mdi-chevron-up' : 'mdi-plus'"
          @click="
            productionFormOpen = !productionFormOpen;
            zeroCardOpen = false;
          "
        >
          {{ productionFormOpen ? "취소" : editingBatch ? "생산 수량 수정" : "생산 수량 추가" }}
        </v-btn>

        <v-btn
          block
          variant="tonal"
          :disabled="saving || Boolean(editingBatch)"
          :prepend-icon="zeroCardOpen ? 'mdi-chevron-up' : 'mdi-minus-circle-outline'"
          @click="
            zeroCardOpen = !zeroCardOpen;
            productionFormOpen = false;
          "
        >
          생산 없음
        </v-btn>
      </div>

      <div
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

            <span>
              {{ productionCardIndex + 1 }} / {{ productionCardTitles.length }}
            </span>

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
            <div
              v-if="!editingBatch"
              class="inventory-dialog__previous-production"
            >
              <div>
                <span>전날 생산량</span>

                <strong v-if="previousProductionLoading">
                  조회 중...
                </strong>

                <strong v-else-if="previousProduction !== null">
                  {{ previousProduction }}개
                </strong>

                <strong v-else>기록 없음</strong>
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
              :error-messages="
                productionErrors.quantity
                  ? '올바른 생산 수량을 입력해 주세요.'
                  : []
              "
              @update:model-value="productionErrors.quantity = false"
              label="생산 수량"
              variant="outlined"
              density="compact"
              :min="DEFAULT_PRODUCTION_QUANTITY"
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
                productionErrors.workers
                  ? '작업자를 선택해 주세요.'
                  : []
              "
              @update:model-value="productionErrors.workers = false"
            />
          </div>

          <!-- 카드 2: 레시피 및 추천 정보 -->
          <div
            v-show="productionCardIndex === 1"
            class="inventory-dialog__swipe-card"
          >
            <v-alert
              type="info"
              variant="tonal"
              density="compact"
              class="mb-2"
            >
              <template v-if="editingBatch">
                레시피 변경 및 추천 생산량 정보는 최초 등록 시에만 입력할 수
                있습니다. 기존 기록 수정시 생산 수량, 작업자, 메모만 변경할 수
                있습니다.
              </template>

              <template v-else>
                실제 작업 과정에서 레시피를 변경했거나 추천 생산량을 참고했다면
                해당 내용을 기록해 주세요. 입력한 정보는 이후 생산 분석에서
                활용됩니다.
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
              :error-messages="
                productionErrors.recipeDeviationNote
                  ? '변경한 작업 내용을 입력해 주세요.'
                  : []
              "
              @update:model-value="
                productionErrors.recipeDeviationNote = false
              "
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
              :disabled="
                Boolean(editingBatch) ||
                !productionForm.recommendationReferenced
              "
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
            <p class="inventory-dialog__form-help">
              특이사항을 메모로 남길 수 있습니다.
            </p>

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
        v-show="zeroCardOpen"
        class="inventory-dialog__zero-card"
      >
        <div
          v-show="zeroCardOpen"
          class="inventory-dialog__zero-card-content"
        >
          <strong>생산 없음 확인</strong>

          <p class="inventory-dialog__form-help">
            실제 생산이 없었다면 사유를 선택해 0개로 확인합니다.
            목록에 없는 사유는 직접입력을 선택해 주세요.
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
              !canMutate ||
              saving ||
              Boolean(editingBatch) ||
              !canConfirmZero
            "
            @click="confirmZeroOpen = true"
          >
            생산 0개 확인
          </v-btn>
        </div>
      </section>

      <!-- 생산 합계 및 기존 생산 기록 목록 -->
      <section class="inventory-dialog__production">
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
                생산 수량
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
                <div>
                  {{ batch.workers.map((worker) => worker.name).join(", ") }}
                </div>
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
                <div>
                  {{ batch.recipe_deviation_note || "변경 내용 없음" }}
                </div>
              </div>

              <!-- 추천 생산량 참고 -->
              <div
                v-if="
                  batch.recommendation_referenced ||
                  batch.recommendation_deviation_reason
                "
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
                    {{
                      batch.recommendation_referenced
                        ? "참고함"
                        : "참고 여부 미기록"
                    }}
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
              <div class="inventory-dialog__action-grid">
                <v-btn
                  variant="outlined"
                  block
                  prepend-icon="mdi-pencil-outline"
                  :disabled="!canMutate"
                  @click="editBatch(batch)"
                >
                  수정
                </v-btn>

                <v-btn
                  variant="outlined"
                  block
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

        <!-- 생산 기록이 없을 때 생산 0개 확인 여부에 따라 문구를 표시합니다. -->
        <div
          v-else
          class="inventory-dialog__production-empty"
        >
          {{
            product?.production_confirmed &&
            Number(product?.production ?? 0) === 0
              ? "생산 없음"
              : "생산 기록이 없습니다."
          }}
        </div>
      </section>
    </div>
  </div>

  <!-- 생산 기록 신규 등록 및 수정 확인창 -->
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
    :recipe-deviated="productionForm.recipeDeviated"
    :recipe-deviation-note="productionForm.recipeDeviationNote"
    :recommendation-referenced="productionForm.recommendationReferenced"
    :recommendation-deviation-reason="productionForm.recommendationDeviationReason"
    :loading="saving"
    @confirm="saveProduction"
  />

  <!-- 생산 0개 최종 확인 -->
  <ConfirmDialog
    v-model="confirmZeroOpen"
    title="생산 0개 확인"
    message="오늘 이 제품을 생산하지 않은 것으로 확정하시겠습니까? 선택한 사유는 이후 분석에서 일반 수요와 구분해 사용합니다."
    :loading="saving"
    @confirm="saveZero"
  />

  <!-- 미저장 내용 취소 확인 -->
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
import ConfirmDialog from "../../common/ConfirmDialog.vue";
import ProductionSaveConfirmDialog from "../ProductionSaveConfirmDialog.vue";
import { addLocalDays } from "../../../utils/localDate";

// 통합 다이얼로그에서 전달받는 데이터
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  storeId: {
    type: Number,
    default: null,
  },

  zeroReasons: {
    type: Array,
    default: () => [],
  },

  canMutate: {
    type: Boolean,
    default: false,
  },

  workers: {
    type: Array,
    default: () => [],
  },

  product: {
    type: Object,
    default: null,
  },

  workDate: {
    type: String,
    default: "",
  },
});

// 생산 탭에서 통합 다이얼로그로 전달할 이벤트
const emit = defineEmits([
  "update:modelValue",
  "saved",
  "error",
]);

// 생산 입력 기본 수량
const DEFAULT_PRODUCTION_QUANTITY = 1;

// 생산 입력 상태
const productionForm = reactive({
  quantity: DEFAULT_PRODUCTION_QUANTITY,
  workerIds: [],
  recipeDeviated: false,
  recipeDeviationNote: "",
  recommendationReferenced: false,
  recommendationDeviationReason: "",
  note: "",
});

// 생산 입력 오류
const productionErrors = reactive({
  quantity: false,
  workers: false,
  recipeDeviationNote: false,
});

// 생산 기록 저장·삭제 중복 실행 방지
const saving = ref(false);

// 생산 기록 수정 대상
const editingBatch = ref(null);

// 생산 기록 상세 펼침 상태
const expandedBatchIds = ref([]);

// 생산 입력 화면
const productionFormOpen = ref(false);
const productionEditor = ref(null);

// 생산 0개 확인 화면
const zeroCardOpen = ref(false);
const zeroReason = ref(null);
const zeroReasonText = ref("");
const zeroNote = ref("");

// 각종 확인창 상태
const saveConfirmOpen = ref(false);
const confirmZeroOpen = ref(false);
const discardConfirmOpen = ref(false);
const deleteConfirmOpen = ref(false);

// 삭제할 생산 기록
const deletingBatch = ref(null);

// 작성 취소 확인 후 실행할 작업
const pendingDiscardAction = ref(null);

// 생산 입력 카드
const productionCardTitles = [
  "생산 입력",
  "레시피 · 추천",
  "메모",
];

const lastProductionCardIndex = productionCardTitles.length - 1;
const productionCardIndex = ref(0);

// 생산 이력 및 이상 생산량 판단 기준
const PRODUCTION_HISTORY_DAYS = 7;
const MIN_PRODUCTION_HISTORY_COUNT = 3;
const ABNORMAL_PRODUCTION_MULTIPLIER = 2;

// 생산 입력 카드 스와이프 기준
const PRODUCTION_SWIPE_MIN_DISTANCE = 60;
const PRODUCTION_SWIPE_DIRECTION_RATIO = 1.25;

// 생산 이력
const productionHistory = ref([]);
const productionHistoryStatus = ref("idle");

// 전날 생산량
const previousProduction = ref(null);
const previousProductionLoading = ref(false);

// 비동기 요청 구분
let previousProductionRequestId = 0;
let productionHistoryRequestId = 0;

// 생산 수량 경고
const productionWarningTitle = "생산 수량 확인 필요";

const productionWarningMessage =
  "최근 생산 기록보다 입력 수량이 크게 증가했습니다. 수량을 다시 확인해 주세요.";

// 서버에서 받은 수량 표시
function displayQuantity(value) {
  if (value === null || value === undefined || value === "") {
    return "미확인";
  }

  const quantity = Number(value);

  return Number.isFinite(quantity)
    ? `${quantity}개`
    : "미확인";
}

// 생산 0개 사유 표시
const displayZeroReasons = computed(() =>
  props.zeroReasons.map((reason) => ({
    ...reason,
    title: reason.value === "other" ? "직접입력" : reason.title,
  }))
);

// 생산 0개 확인 가능 여부
const canConfirmZero = computed(
  () =>
    Boolean(zeroReason.value) &&
    (
      zeroReason.value !== "other" ||
      Boolean(zeroReasonText.value.trim())
    )
);

// 선택한 작업자 정보
const selectedProductionWorkers = computed(() =>
  productionForm.workerIds
    .map((workerId) =>
      props.workers.find(
        (worker) => Number(worker.id) === Number(workerId)
      )
    )
    .filter(Boolean)
);

// 저장 후 예상 생산 합계
const expectedProductionTotal = computed(() => {
  const rawTotal = props.product?.production;
  const quantity = Number(productionForm.quantity);

  if (
    rawTotal === null ||
    rawTotal === undefined ||
    rawTotal === ""
  ) {
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

    if (!Number.isFinite(original)) {
      return null;
    }

    return currentTotal - original + quantity;
  }

  return currentTotal + quantity;
});

// 최근 생산량 평균
const productionAverage = computed(() => {
  if (productionHistoryStatus.value !== "success") {
    return null;
  }

  const quantities = productionHistory.value
    .map((row) => row.quantity)
    .filter(
      (quantity) =>
        quantity !== null &&
        quantity !== undefined &&
        Number.isFinite(Number(quantity))
    )
    .map(Number);

  if (quantities.length < MIN_PRODUCTION_HISTORY_COUNT) {
    return null;
  }

  return quantities.reduce(
    (sum, quantity) => sum + quantity,
    0
  ) / quantities.length;
});

// 전날 생산 이력 수량
const previousProductionQuantity = computed(() => {
  if (productionHistoryStatus.value !== "success") {
    return null;
  }

  const previousDate = addLocalDays(props.workDate, -1);

  const quantity = productionHistory.value.find(
    (row) => row.date === previousDate
  )?.quantity;

  return quantity == null ? null : Number(quantity);
});

// 생산량 이상 증가 여부
const isAbnormalQuantity = computed(() => {
  const average = productionAverage.value;
  const total = expectedProductionTotal.value;

  if (
    average === null ||
    average <= 0 ||
    total === null
  ) {
    return false;
  }

  const previous = previousProductionQuantity.value;

  const baseline =
    previous !== null
      ? Math.max(average, previous)
      : average;

  return total >= baseline * ABNORMAL_PRODUCTION_MULTIPLIER;
});

// 전날 생산량 조회
async function loadPreviousProduction() {
  const requestId = ++previousProductionRequestId;

  previousProduction.value = null;

  if (
    !props.storeId ||
    !props.workDate ||
    !props.product?.id
  ) {
    previousProductionLoading.value = false;
    return;
  }

  previousProductionLoading.value = true;

  try {
    const previousDate = addLocalDays(
      props.workDate,
      -1
    );

    const response = await window.axios.get(
      "/tillwhite/api/production-management/daily",
      {
        params: {
          date: previousDate,
          store_id: props.storeId,
        },
      }
    );

    if (requestId !== previousProductionRequestId) {
      return;
    }

    const previousRow = (response.data?.rows || []).find(
      (row) =>
        Number(row.id) === Number(props.product.id)
    );

    if (previousRow?.production_confirmed) {
      previousProduction.value = Number(
        previousRow.production
      );
    }
  } catch (error) {
    if (requestId !== previousProductionRequestId) {
      return;
    }

    emit(
      "error",
      error.response?.data?.message ||
        "전날 생산량을 조회하지 못했습니다."
    );
  } finally {
    if (requestId === previousProductionRequestId) {
      previousProductionLoading.value = false;
    }
  }
}

// 전날 생산량 입력창에 적용
function applyPreviousProduction() {
  if (
    editingBatch.value ||
    !props.canMutate ||
    previousProduction.value === null
  ) {
    return;
  }

  if (previousProduction.value === 0) {
    emit(
      "error",
      "전날 생산량이 0개입니다. 생산 0개 확인 기능을 이용해 주세요."
    );
    return;
  }

  productionForm.quantity = previousProduction.value;
}

// 최근 7일 생산 이력 조회
async function loadProductionHistory() {
  const requestId = ++productionHistoryRequestId;

  productionHistory.value = [];
  productionHistoryStatus.value = "idle";

  if (
    !props.storeId ||
    !props.workDate ||
    !props.product?.id
  ) {
    return;
  }

  productionHistoryStatus.value = "loading";

  try {
    const previousDate = addLocalDays(
      props.workDate,
      -1
    );

    const response = await window.axios.get(
      `/tillwhite/api/production-management/products/${props.product.id}`,
      {
        params: {
          work_date: previousDate,
        },
      }
    );

    if (requestId !== productionHistoryRequestId) {
      return;
    }

    const history = response.data?.production_history;

    if (
      !Array.isArray(history) ||
      history.length !== PRODUCTION_HISTORY_DAYS
    ) {
      throw new Error("생산 이력 응답이 올바르지 않습니다.");
    }

    productionHistory.value = history;
    productionHistoryStatus.value = "success";
  } catch {
    if (requestId !== productionHistoryRequestId) {
      return;
    }

    productionHistory.value = [];
    productionHistoryStatus.value = "error";
  }
}

// 다이얼로그 열림 / 제품 변경 시 전날 생산량 갱신
watch(
  () => [
    props.modelValue,
    props.product?.id,
    props.workDate,
    props.storeId,
  ],
  () => {
    if (props.modelValue) {
      loadPreviousProduction();
    } else {
      previousProductionRequestId++;
      previousProductionLoading.value = false;
      previousProduction.value = null;
    }
  },
  { immediate: true }
);

// 다이얼로그 열림 / 제품 변경 시 생산 이력 갱신
watch(
  () => [
    props.modelValue,
    props.product?.id,
    props.storeId,
    props.workDate,
  ],
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

// 저장 전 필수 입력 검증
function askSave() {
  if (!props.canMutate || !props.product?.id || saving.value) {
    return;
  }

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

// 생산 기록 신규 등록 및 수정
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
      await window.axios.post(
        "/tillwhite/api/production-management/batches",
        {
          store_id: props.storeId,
          product_id: props.product.id,
          work_date: props.workDate,
          quantity: Number(productionForm.quantity),
          recipe_deviated: productionForm.recipeDeviated,
          recipe_deviation_note:
            productionForm.recipeDeviationNote || null,
          recommendation_referenced:
            productionForm.recommendationReferenced,
          recommendation_deviation_reason:
            productionForm.recommendationDeviationReason || null,
          note: productionForm.note || null,
          workers: productionForm.workerIds.map((userId) => ({
            user_id: userId,
            process_type: "all",
          })),
        }
      );
    }

    const message = editingBatch.value
      ? "생산 기록을 수정했습니다."
      : "생산 기록을 저장했습니다.";

    saveConfirmOpen.value = false;
    resetProductionEditor();

    // 통합 다이얼로그에 닫기 및 새로고침 요청
    emit("update:modelValue", false);
    emit("saved", message);
  } catch (error) {
    emit(
      "error",
      error.response?.data?.message ||
        "생산 기록을 저장하지 못했습니다."
    );
  } finally {
    saving.value = false;
  }
}

// 생산 0개 확인 저장
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
    await window.axios.post(
      "/tillwhite/api/production-management/zero-production",
      {
        store_id: props.storeId,
        product_id: props.product.id,
        work_date: props.workDate,
        reason: zeroReason.value,
        reason_text:
          zeroReason.value === "other"
            ? zeroReasonText.value.trim()
            : null,
        note: zeroNote.value || null,
      }
    );

    confirmZeroOpen.value = false;
    resetProductionEditor();

    emit("update:modelValue", false);
    emit("saved", "생산 0개를 확인했습니다.");
  } catch (error) {
    emit(
      "error",
      error.response?.data?.message ||
        "생산 0개를 확인하지 못했습니다."
    );
  } finally {
    saving.value = false;
  }
}

// 생산 기록 삭제 확인창 표시
function askDelete(batch) {
  if (!props.canMutate || !batch) {
    return;
  }

  deletingBatch.value = batch;
  deleteConfirmOpen.value = true;
}

// 생산 기록 삭제
async function deleteBatch() {
  if (
    !props.canMutate ||
    !deletingBatch.value?.id ||
    saving.value
  ) {
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

    deleteConfirmOpen.value = false;

    emit("update:modelValue", false);
    emit("saved", "생산 기록을 삭제했습니다.");

    deletingBatch.value = null;
    editingBatch.value = null;
    expandedBatchIds.value = [];
  } catch (error) {
    emit(
      "error",
      error.response?.data?.message ||
        "생산 기록을 삭제하지 못했습니다."
    );
  } finally {
    saving.value = false;
  }
}

// 생산 입력값이 변경되었는지 확인
function hasUnsavedProductionChanges() {
  if (editingBatch.value) {
    return true;
  }

  return (
    Number(productionForm.quantity) !== DEFAULT_PRODUCTION_QUANTITY ||
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

// 미저장 내용이 있으면 사용자에게 확인 요청
function guardUnsavedChanges(action) {
  if (saving.value) {
    return;
  }

  if (hasUnsavedProductionChanges()) {
    pendingDiscardAction.value = action;
    discardConfirmOpen.value = true;
    return;
  }

  action();
}

// 작성 취소 확인 후 대기 중인 동작 실행
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

// 생산 입력 상태 초기화
function resetProductionEditor() {
  productionErrors.quantity = false;
  productionErrors.workers = false;
  productionErrors.recipeDeviationNote = false;

  productionFormOpen.value = false;
  productionCardIndex.value = 0;
  zeroCardOpen.value = false;

  editingBatch.value = null;
  expandedBatchIds.value = [];

  productionForm.quantity = DEFAULT_PRODUCTION_QUANTITY;
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

// 생산 기록 상세 펼치기 / 접기
function toggleBatch(batchId) {
  if (expandedBatchIds.value.includes(batchId)) {
    expandedBatchIds.value = expandedBatchIds.value.filter(
      (id) => id !== batchId
    );
  } else {
    expandedBatchIds.value = [
      ...expandedBatchIds.value,
      batchId,
    ];
  }
}

// 선택한 생산 기록을 수정 폼에 적용
function applyBatchToEditor(batch) {
  editingBatch.value = batch;

  productionCardIndex.value = 0;
  zeroCardOpen.value = false;
  productionFormOpen.value = true;

  productionForm.quantity = Number(batch.quantity);

  productionForm.workerIds = (batch.workers || []).map(
    (worker) => worker.id
  );

  productionForm.recipeDeviated =
    Boolean(batch.recipe_deviated);

  productionForm.recipeDeviationNote =
    batch.recipe_deviation_note || "";

  productionForm.recommendationReferenced =
    Boolean(batch.recommendation_referenced);

  productionForm.recommendationDeviationReason =
    batch.recommendation_deviation_reason || "";

  productionForm.note = batch.note || "";

  nextTick(() => {
    productionEditor.value?.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  });
}

// 기존 생산 기록 수정 시작
function editBatch(batch) {
  if (
    !props.canMutate ||
    saving.value ||
    !batch?.id
  ) {
    return;
  }

  if (editingBatch.value?.id === batch.id) {
    return;
  }

  guardUnsavedChanges(() => {
    applyBatchToEditor(batch);
  });
}

// 생산 기록 수정 취소
function cancelEdit() {
  if (saving.value) {
    return;
  }

  guardUnsavedChanges(() => {
    resetProductionEditor();
    productionFormOpen.value = true;
  });
}

// 카드 스와이프 시작 위치
let productionSwipeStart = null;

// 입력 요소 조작 시 스와이프 방지
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

// 스와이프 시작
function onProductionSwipeStart(event) {
  productionSwipeStart = null;

  if (!productionFormOpen.value || saving.value) {
    return;
  }

  if (
    event.pointerType === "mouse" &&
    event.button !== 0
  ) {
    return;
  }

  const target = event.target;

  if (
    target instanceof Element &&
    target.closest(productionSwipeIgnoreSelector)
  ) {
    return;
  }

  productionSwipeStart = {
    pointerId: event.pointerId,
    x: event.clientX,
    y: event.clientY,
  };

  event.currentTarget?.setPointerCapture?.(
    event.pointerId
  );
}

// 스와이프 종료 및 카드 변경
function onProductionSwipeEnd(event) {
  if (
    event.currentTarget?.hasPointerCapture?.(
      event.pointerId
    )
  ) {
    event.currentTarget.releasePointerCapture(
      event.pointerId
    );
  }

  const start = productionSwipeStart;
  productionSwipeStart = null;

  if (
    !start ||
    start.pointerId !== event.pointerId
  ) {
    return;
  }

  if (!productionFormOpen.value || saving.value) {
    return;
  }

  const deltaX = event.clientX - start.x;
  const deltaY = event.clientY - start.y;

  if (
    Math.abs(deltaX) < PRODUCTION_SWIPE_MIN_DISTANCE ||
    Math.abs(deltaX) <=
      Math.abs(deltaY) * PRODUCTION_SWIPE_DIRECTION_RATIO
  ) {
    return;
  }

  if (deltaX < 0) {
    productionCardIndex.value = Math.min(
      lastProductionCardIndex,
      productionCardIndex.value + 1
    );
  } else {
    productionCardIndex.value = Math.max(
      0,
      productionCardIndex.value - 1
    );
  }
}

// 스와이프 취소
function onProductionSwipeCancel() {
  productionSwipeStart = null;
}

// 제품·날짜가 변경되거나 다이얼로그를 새로 열면 초기화
watch(
  () => [props.product?.id, props.workDate, props.modelValue],
  ([productId, workDate, isOpen], previous = []) => {
    if (!isOpen || !productId || !workDate) {
      return;
    }

    const [previousProductId, previousWorkDate, wasOpen] = previous;

    // 동일 제품·날짜의 일반 데이터 갱신에서는 입력 내용 유지
    if (
      wasOpen &&
      previousProductId === productId &&
      previousWorkDate === workDate
    ) {
      return;
    }

    resetProductionEditor();

    pendingDiscardAction.value = null;
    discardConfirmOpen.value = false;

    deletingBatch.value = null;
    deleteConfirmOpen.value = false;
  },
  { immediate: true }
);

// 부모 다이얼로그에 저장 진행 상태 제공
const isSaving = computed(() => saving.value);

// 부모에서 닫기 또는 탭 이동을 요청하면 실행
function requestLeave(action) {
  if (saving.value) {
    return false;
  }

  if (hasUnsavedProductionChanges()) {
    guardUnsavedChanges(action);
    return false;
  }

  action();
  return true;
}

// 부모 다이얼로그에서 호출할 기능만 공개
defineExpose({
  requestLeave,
  isSaving,
  editingBatch,
  productionFormOpen,
  askSave,
  cancelEdit,
});

</script>

<style scoped>
/* 생산 기록: 생산량 헤더 */
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

/* 생산 기록 헤더 - 마우스 올림 */
.inventory-dialog__production-record-header:hover {
  background: rgba(var(--v-theme-on-surface), 0.065);
}

/* 생산 기록 헤더 - 키보드 포커스 */
.inventory-dialog__production-record-header:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 생산량 제목 */
.inventory-dialog__production-record-label {
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

/* 생산 수량 */
.inventory-dialog__production-record-quantity {
  font-size: 1rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* 생산 기록 상세 정보 */
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

/* 생산 기록 상세 항목 제목 */
.inventory-dialog__production-record .inventory-dialog__record-detail strong {
  display: block;
  margin-bottom: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

.inventory-dialog__production-record .inventory-dialog__record-detail > div {
  color: rgba(var(--v-theme-on-surface), 0.72);
  white-space: pre-wrap;
}

/* 생산 탭 기본 글씨 */
.inventory-dialog__production-tab {
  font-size: 12px;
}

.inventory-dialog__production-tab :deep(.v-btn),
.inventory-dialog__production-tab :deep(.v-field),
.inventory-dialog__production-tab :deep(.v-label) {
  font-size: 12px;
}

/* 생산 관리 영역 */
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

/* 생산 기록 목록 */
.inventory-dialog__production {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  text-align: left;
  font-size: 13px;
}

/* 생산 합계 및 기록 제목 */
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

.inventory-dialog__production-summary {
  margin-top: 6px;
  border-radius: 12px 12px 0 0;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.inventory-dialog__production-heading {
  margin-bottom: 6px;
  border-radius: 0 0 12px 12px;
}

.inventory-dialog__production-summary > strong,
.inventory-dialog__production-heading > strong {
  font-size: 15px;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.6;
}

/* 생산 기록 카드 */
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

/* 카드 좌우 스와이프 */
.inventory-dialog__swipe-area {
  width: 100%;
  min-width: 0;
  touch-action: pan-y;
  margin-top: 4px;
}

/* 생산 입력 카드 */
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

/* 카드 전환 애니메이션 */
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

/* 움직임 줄이기 설정 */
@media (prefers-reduced-motion: reduce) {
  .inventory-dialog__swipe-card {
    animation: none;
  }
}

/* 모바일 생산 카드 */
@media (max-width: 600px) {
  .inventory-dialog__swipe-card {
    padding: 12px;
    min-height: 200px;
  }
}

/* 생산 입력 폼 */
.inventory-dialog__production-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  text-align: left;
  font-size: 13px;
}

/* 입력 항목 제목 */
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

/* 생산 입력 필드 */
.inventory-dialog__production-form :deep(.v-label) {
  font-size: 13px;
}

.inventory-dialog__production-form :deep(.v-field__input) {
  font-size: 13px;
}

.inventory-dialog__production-form :deep(.v-selection-control__wrapper) {
  font-size: 16px;
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
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
}

/* 입력 안내 문구 */
.inventory-dialog__form-help {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

@media (max-width: 600px) {
  .inventory-dialog__zero-card-content {
    padding: 12px;
  }
}

/* 생산 기록 헤더 오른쪽 영역 */
.inventory-dialog__production-record-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* 생산 기록 상세 영역 */
.inventory-dialog__record-detail {
  width: 100%;
  min-width: 0;
}

/* 추천 생산량 참고 헤더 */
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

/* 추천 생산량 참고 여부 */
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

/* 생산 기록별 수정·삭제 버튼 */
.inventory-dialog__record-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

/* 화면이 좁을 때 카드 간격 조정 */
@media (max-width: 600px) {
  .inventory-dialog__placeholder {
    padding: 12px;
  }

  .inventory-dialog__production-form {
    padding: 10px;
  }

  .inventory-dialog__card-navigation {
    padding: 10px;
  }
}

/* 생산 입력 / 생산 0개 버튼 가로 배치 */
.inventory-dialog__action-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  width: 100%;
}

.inventory-dialog__action-grid :deep(.v-btn) {
  min-width: 0;
  min-height: 44px;
  height: auto;
  padding: 8px 10px;
  white-space: normal;
}

.inventory-dialog__action-grid :deep(.v-btn__content) {
  white-space: normal;
  text-align: center;
  line-height: 1.4;
}

.inventory-dialog__production-empty {
  padding: 14px;
  border: 1px dashed rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 10px;
  font-size: 12px;
  opacity: 0.7;
  text-align: center;
}
</style>