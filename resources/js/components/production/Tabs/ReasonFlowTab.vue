<template>
  <div class="inventory-dialog inventory-dialog__reason-tab">
    <div class="inventory-dialog__placeholder">
      <strong>{{ dialogTitle }} 관리</strong>

      <p class="inventory-dialog__form-help">
        {{
          type === "loss"
            ? "생산 기록을 선택해 로스 수량과 사유를 입력하고, 저장된 기록을 관리할 수 있습니다."
            : "생산 기록을 선택해 폐기 수량과 사유를 입력하고, 저장된 기록을 관리할 수 있습니다."
        }}
      </p>

      <!-- 재고 수량을 초과하거나 잘못된 재고가 포함되면 오류를 안내합니다. -->
      <v-alert
        v-if="
          editorOpen &&
          (sourceOverages.length || invalidSourceRows.length)
        "
        type="error"
        variant="tonal"
        density="compact"
      >
        입력한 수량이 남은 수량보다 많습니다.
      </v-alert>

      <!-- 입력 화면과 없음 확인 화면을 전환합니다. -->
      <div class="inventory-dialog__action-grid">
        <v-btn
          block
          variant="outlined"
          :color="editorOpen ? undefined : 'success'"
          :disabled="saving || !canMutate"
          :prepend-icon="editorOpen ? 'mdi-chevron-up' : undefined"
          @click="toggleEditor"
        >
          {{ editorOpen ? "취소" : `${dialogTitle} 수량 설정` }}
        </v-btn>

        <v-btn
          block
          variant="outlined"
          :disabled="saving || !canMutate"
          :prepend-icon="zeroCardOpen ? 'mdi-chevron-up' : undefined"
          @click="toggleZeroCard"
        >
          {{ dialogTitle }} 없음
        </v-btn>
      </div>

      <!-- 이월탭과 동일한 3단계 입력 구조입니다. -->
      <div
        v-if="editorOpen"
        class="inventory-dialog__production-form"
      >
        <div class="inventory-dialog__field-heading">
          <strong>{{ dialogTitle }} 수량 설정 / 수정</strong>
          <span>
            현재 {{ dialogTitle }}
            {{ displayQuantity(currentTotal) }}
          </span>
        </div>

        <!-- 입력 단계 탐색 -->
        <div class="inventory-dialog__card-navigation">
          <div class="inventory-dialog__card-steps">
            <v-btn
              v-for="(title, index) in cardTitles"
              :key="index"
              size="small"
              :variant="cardIndex === index ? 'flat' : 'outlined'"
              :color="cardIndex === index ? 'primary' : undefined"
              @click="cardIndex = index"
            >
              {{ title }}
            </v-btn>
          </div>

          <div class="inventory-dialog__card-controls">
            <v-btn
              size="small"
              variant="text"
              prepend-icon="mdi-chevron-left"
              :disabled="cardIndex === 0"
              @click="cardIndex--"
            >
              이전
            </v-btn>

            <span>
              {{ cardIndex + 1 }} / {{ cardTitles.length }}
            </span>

            <v-btn
              size="small"
              variant="text"
              append-icon="mdi-chevron-right"
              :disabled="cardIndex === cardTitles.length - 1"
              @click="cardIndex++"
            >
              다음
            </v-btn>
          </div>
        </div>

        <!-- 모바일 좌우 스와이프 입력 영역 -->
        <div
          class="inventory-dialog__swipe-area"
        >
          <!-- 카드 1: 재고 선택 -->
          <div
            v-show="cardIndex === 0"
            class="inventory-dialog__swipe-card"
          >
            <div class="inventory-dialog__field-heading">
              <strong>재고 출처</strong>
            </div>

            <!-- 이월탭과 동일한 출처 선택 버튼 -->
            <div class="inventory-dialog__action-grid">
              <v-btn
                block
                :variant="
                  selectedSource === 'today'
                    ? 'tonal'
                    : 'outlined'
                "
                @click="changeStockSource('today')"
              >
                당일 생산분
              </v-btn>

              <v-btn
                block
                :variant="
                  selectedSource === 'carryover'
                    ? 'tonal'
                    : 'outlined'
                "
                @click="changeStockSource('carryover')"
              >
                기존 이월분
              </v-btn>
            </div>

            <div class="inventory-dialog__field-heading">
              <strong>{{ dialogTitle }} 처리할 재고 선택</strong>
              <span>{{ stockLotOptions.length }}건</span>
            </div>

            <!-- 선택 가능한 생산 기록 카드 목록 -->
            <div v-if="stockLotOptions.length">
              <div
                v-for="option in stockLotOptions"
                :key="option.value"
                class="inventory-dialog__production-record"
              >
                <button
                  type="button"
                  class="inventory-dialog__production-record-header"
                  :class="{
                    'inventory-dialog__production-record-header--selected':
                      selectedLotId != null &&
                      String(selectedLotId) ===
                        String(option.value)
                  }"
                  :aria-pressed="
                    selectedLotId != null &&
                    String(selectedLotId) ===
                      String(option.value)
                  "
                  @click="changeStockLot(option.value)"
                >
                  <div class="inventory-dialog__carryover-record-content">
                    <strong
                      class="inventory-dialog__production-record-label"
                    >
                      {{ option.recordName }}
                    </strong>

                    <span
                      class="inventory-dialog__carryover-record-date"
                    >
                      최초 생산일 {{ option.productionDate }}
                    </span>

                    <div
                      class="inventory-dialog__carryover-record-quantity"
                    >
                      <span>
                        {{ dialogTitle }} 처리
                        <strong>
                          {{
                            displayQuantity(
                              option.processedQuantity
                            )
                          }}
                        </strong>
                      </span>

                      <span
                        class="inventory-dialog__carryover-record-divider"
                      >
                        ·
                      </span>

                      <span>
                        사용 가능
                        <strong>
                          {{
                            displayQuantity(
                              option.availableQuantity
                            )
                          }}
                        </strong>
                      </span>
                    </div>
                  </div>

                  <v-icon icon="mdi-chevron-right" size="20" />
                </button>
              </div>
            </div>

            <p
              v-else
              class="inventory-dialog__form-help"
            >
              선택할 수 있는 재고 기록이 없습니다.
            </p>

            <p
              v-if="selectedLotId == null"
              class="inventory-dialog__form-help"
            >
              {{ dialogTitle }} 처리할 생산 기록을 선택해 주세요.
              처리할 재고가 없다면 상단의
              '{{ dialogTitle }} 없음' 버튼을 이용해 주세요.
            </p>
          </div>

          <!-- 카드 2: 수량과 사유 입력 -->
          <div
            v-show="cardIndex === 1"
            class="inventory-dialog__swipe-card"
          >
            <!-- 생산 기록을 선택하지 않았을 때 안내합니다. -->
            <v-alert
              v-if="!selectedStockLot"
              type="info"
              variant="tonal"
              density="compact"
              class="inventory-dialog__compact-alert"
            >
              먼저 재고 선택 카드에서 {{ dialogTitle }} 처리할 생산 기록을 선택해 주세요.
            </v-alert>

            <!-- 선택한 생산 기록의 재고 정보를 표시합니다. -->
            <div
              v-if="selectedStockLot"
              class="inventory-dialog__selected-stock"
            >
              <div class="inventory-dialog__selected-stock-row">
                <span>최초 생산일</span>
                <strong>
                  {{
                    selectedStockLot.origin_production_date ||
                    "생산일 미확인"
                  }}
                </strong>
              </div>

              <div class="inventory-dialog__selected-stock-row">
                <span>기존 {{ dialogTitle }} 처리</span>
                <strong>
                  {{ displayQuantity(selectedSavedQuantity) }}
                </strong>
              </div>

              <div class="inventory-dialog__selected-stock-row">
                <span>설정 가능한 최대 수량</span>
                <strong>
                  {{ displayQuantity(selectedStockAvailable) }}
                </strong>
              </div>

              <!-- 입력 수량을 반영한 현재 잔여 수량을 표시합니다. -->
              <div class="inventory-dialog__selected-stock-row">
                <span>현재 남은 수량</span>
                <strong>
                  {{ displayQuantity(selectedSourceRemaining) }}
                </strong>
              </div>
            </div>

            <!-- 선택 재고의 사유 및 수량 입력 -->
            <template v-if="selectedStockLot">
              <div class="inventory-dialog__field-heading">
                <strong>{{ dialogTitle }} 수량과 사유</strong>
                <span>{{ selectedReasonRows.length }}건</span>
              </div>

              <p class="inventory-dialog__form-help">
                선택한 생산 기록에서 처리할 수량과 사유를
                입력해 주세요.
              </p>

              <!-- 동일한 생산 기록에 여러 사유를 입력할 수 있습니다. -->
              <div
                v-for="(entry, index) in selectedReasonRows"
                :key="entry.key"
                class="inventory-dialog__reason-entry"
              >
                <div class="inventory-dialog__field-heading">
                  <strong>사유 {{ index + 1 }}</strong>
                </div>

                <v-select
                  :model-value="entry.row.reason_code"
                  :items="displayReasonOptions"
                  item-title="title"
                  item-value="value"
                  label="사유 · 필수"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="saving || !canMutate"
                  @update:model-value="
                    (value) =>
                      updateReasonField(
                        entry.key,
                        'reason_code',
                        value
                      )
                  "
                />

                <!-- 직접입력 사유 선택 시 내용을 입력합니다. -->
                <v-text-field
                  v-if="entry.row.reason_code === 'other'"
                  :model-value="entry.row.reason_text"
                  label="사유 직접입력"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="saving || !canMutate"
                  @update:model-value="
                    (value) =>
                      updateReasonField(
                        entry.key,
                        'reason_text',
                        value
                      )
                  "
                />

                <!-- 이월탭과 동일한 수량 입력 컴포넌트 -->
                <v-number-input
                  :model-value="entry.row.quantity"
                  :label="`${dialogTitle} 수량`"
                  variant="outlined"
                  density="compact"
                  :min="0"
                  :max="Math.max(
                    0,
                    (selectedStockAvailable ?? 0) -
                    (selectedEnteredQuantity - Number(entry.row.quantity || 0))
                  )"
                  control-variant="stacked"
                  :disabled="saving || !canMutate"
                  @update:model-value="
                    (value) =>
                      updateReasonField(
                        entry.key,
                        'quantity',
                        value
                      )
                  "
                />

                <!-- 신규 작성 중 추가한 사유만 취소할 수 있습니다. -->
                <div
                  v-if="!entry.row._persisted"
                  class="inventory-dialog__row-actions d-flex w-100"
                >
                  <v-btn
                    block
                    class="flex-grow-1"
                    size="small"
                    variant="outlined"
                    :disabled="saving || !canMutate"
                    @click="cancelNewReason(entry.key)"
                  >
                    사유 추가 취소
                  </v-btn>
                </div>
              </div>

              <div class="inventory-dialog__row-actions d-flex w-100">
                <v-btn
                  block
                  class="flex-grow-1"
                  size="small"
                  variant="outlined"
                  color="success"
                  :disabled="
                    saving ||
                    !canMutate ||
                    selectedSourceRemaining === null ||
                    selectedSourceRemaining <= 0
                  "
                  @click="addReason"
                >
                  사유 추가
                </v-btn>
              </div>

              <!-- 같은 생산 기록에 입력한 전체 수량을 계산합니다. -->
              <div class="inventory-dialog__selected-stock">
                <div class="inventory-dialog__selected-stock-row">
                  <span>입력한 {{ dialogTitle }} 수량</span>
                  <strong>
                    {{ displayQuantity(selectedEnteredQuantity) }}
                  </strong>
                </div>

                <div class="inventory-dialog__selected-stock-row">
                  <span>입력 후 남은 수량</span>
                  <strong>
                    {{ displayQuantity(selectedSourceRemaining) }}
                  </strong>
                </div>
              </div>

              <v-alert
                v-if="
                  selectedStockAvailable !== null &&
                  selectedEnteredQuantity > selectedStockAvailable
                "
                type="error"
                variant="tonal"
                density="compact"
              >
                선택한 생산 기록의 사용 가능 수량을 초과했습니다.
              </v-alert>
            </template>
            
            <v-btn
              block
              variant="outlined"
              prepend-icon="mdi-chevron-left"
              :disabled="saving || !canMutate"
              @click="cardIndex = 0"
            >
              다른 재고 선택
            </v-btn>
          </div>

          <!-- 카드 3: 메모 입력 -->
          <div
            v-show="cardIndex === 2"
            class="inventory-dialog__swipe-card inventory-dialog__memo-card"
          >
            <p class="inventory-dialog__form-help">
              특이사항을 메모로 남길 수 있습니다.
            </p>
            
            <v-textarea
              v-model="note"
              label="메모 · 선택"
              variant="outlined"
              density="compact"
              rows="2"
              hide-details="auto"
              :disabled="saving || !canMutate"
            />
          </div>
        </div>
      </div>

      <!-- 로스 또는 폐기가 없으면 0개로 확인합니다. -->
      <div
        v-if="zeroCardOpen"
        class="inventory-dialog__inner-card"
      >
        <strong>{{ dialogTitle }} 없음 확인</strong>

        <p class="inventory-dialog__form-help">
          다음 작업으로 넘기기 전에 {{ dialogTitle }} 처리할 재고가
          없다면 0개로 확인합니다.
          이미 등록한 {{ dialogTitle }} 기록은 아래 목록에서 개별 수정해야 합니다.
        </p>

        <v-btn
          block
          variant="outlined"
          :disabled="
            saving ||
            !canMutate ||
            savedDetails.length > 0
          "
          @click="askZeroSave"
        >
          {{ dialogTitle }} 0개 확인
        </v-btn>
      </div>

      <!-- 저장된 합계와 개별 처리 기록 -->
      <section class="inventory-dialog__production">
        <div class="inventory-dialog__production-summary">
          <strong>{{ dialogTitle }} 합계</strong>
          <strong>{{ displayQuantity(currentTotal) }}</strong>
        </div>

        <div class="inventory-dialog__production-heading">
          <strong>{{ dialogTitle }} 기록</strong>
          <strong>{{ savedDetails.length }}건</strong>
        </div>

        <template v-if="savedDetails.length">
          <div
            v-for="(item, index) in savedDetails"
            :key="
              `${item.stock_lot_id}-${item.reason_code}-${index}`
            "
            class="inventory-dialog__production-record"
          >
            <!-- 개별 저장 기록 상세 펼침 -->
            <button
              type="button"
              class="inventory-dialog__production-record-header"
              :aria-expanded="expanded.includes(index)"
              @click="toggleExpanded(index)"
            >
              <strong class="inventory-dialog__production-record-label">
                {{ dialogTitle }} 수량
              </strong>

              <span class="inventory-dialog__production-record-header-right">
                <strong class="inventory-dialog__production-record-quantity">
                  {{ displayQuantity(item.quantity) }}
                </strong>

                <v-icon
                  :icon="
                    expanded.includes(index)
                      ? 'mdi-chevron-up'
                      : 'mdi-chevron-down'
                  "
                  size="20"
                />
              </span>
            </button>

            <div v-if="expanded.includes(index)">
              <div class="inventory-dialog__record-detail">
                <strong>사유</strong>
                <div>{{ savedReasonLabel(item) }}</div>
              </div>

              <div class="inventory-dialog__record-detail">
                <strong>최초 생산일</strong>
                <div>
                  {{
                    item.origin_production_date ||
                    "생산일 미확인"
                  }}
                </div>
              </div>

              <!-- 삭제는 이월탭처럼 저장된 기록 상세에서 처리합니다. -->
              <div class="inventory-dialog__action-grid">
                <v-btn
                  variant="outlined"
                  color="primary"
                  block
                  :disabled="saving || !canMutate"
                  @click="openForEdit(item)"
                >
                  수정
                </v-btn>

                <v-btn
                  variant="outlined"
                  color="error"
                  block
                  :disabled="saving || !canMutate"
                  @click="askDeleteReason(index)"
                >
                  삭제
                </v-btn>
              </div>
            </div>
          </div>
        </template>

        <!-- 기록이 없을 때 확인 상태에 맞는 문구를 표시합니다. -->
        <div
          v-else
          class="inventory-dialog__production-empty"
        >
          {{
            flowConfirmed && Number(currentTotal || 0) === 0
              ? `${dialogTitle} 없음`
              : `${dialogTitle} 기록이 없습니다.`
          }}
        </div>
      </section>
    </div>

    <!-- 일반 저장 전 최종 확인 -->
    <ConfirmDialog
      v-model="confirmSave"
      :title="`${dialogTitle} 저장`"
      :message="confirmMessage"
      :loading="saving"
      @confirm="save"
    />

    <!-- 미저장 입력 내용 취소 확인 -->
    <ConfirmDialog
      v-model="confirmClose"
      title="작성 취소"
      message="작성 중인 내용이 있습니다. 닫으시겠습니까?"
      :loading="saving"
      @update:model-value="handleCloseConfirmChange"
      @confirm="forceClose"
    />

    <!-- 개별 저장 기록 삭제 확인 -->
    <ConfirmDialog
      v-model="deleteConfirmOpen"
      :title="`${dialogTitle} 기록 삭제`"
      message="선택한 기록을 삭제하시겠습니까? 다른 사유와 생산 기록은 유지됩니다."
      :loading="saving"
      @confirm="deleteSavedReason"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import ConfirmDialog from "../../common/ConfirmDialog.vue";
import { productionReasonLabel } from "../../../utils/productionReasons";

const props = defineProps({
  modelValue: Boolean,
  product: {
    type: Object,
    default: null,
  },
  storeId: Number,
  workDate: String,
  canMutate: Boolean,
  type: {
    type: String,
    required: true,
    validator: (value) => ["loss", "waste"].includes(value),
  },
  reasonOptions: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits([
  "update:modelValue",
  "saved",
  "error",
]);

// 입력창 및 확인창 상태
const editorOpen = ref(false);
const zeroCardOpen = ref(false);
const saving = ref(false);
const confirmSave = ref(false);
const confirmClose = ref(false);
const deleteConfirmOpen = ref(false);
const savingZero = ref(false);

// 로스·폐기 사유 입력 상태
const reasonRows = ref([]);
const note = ref("");
const initialReasonSnapshot = ref("[]");

// 생산 기록 선택 상태
const selectedSource = ref("today");
const selectedLotId = ref(null);

// 이월탭과 동일한 3단계 입력 카드
const cardIndex = ref(0);
const cardTitles = ["재고 선택", "수량과 사유", "메모"];

// 저장 기록 상세 및 삭제 대상
const expanded = ref([]);
const deletingReasonIndex = ref(null);

// 작성 취소 후 실행할 동작
const pendingAction = ref(null);

// 입력 행마다 안정적인 고유 키를 부여합니다.
let reasonRowKeySequence = 0;

// 업무 유형에 따라 로스 또는 폐기 명칭을 반환합니다.
const dialogTitle = computed(() =>
  props.type === "loss" ? "로스" : "폐기"
);

// 기존 사유 목록을 화면에 표시할 한글 명칭으로 변환합니다.
const displayReasonOptions = computed(() =>
  props.reasonOptions.map((option) => ({
    ...option,
    title:
      option.value === "other"
        ? "직접입력"
        : option.title,
  }))
);

// 현재 업무의 확인 상태를 확인합니다.
const flowConfirmed = computed(() =>
  props.type === "loss"
    ? Boolean(props.product?.loss_confirmed)
    : Boolean(props.product?.waste_confirmed)
);

// 서버에서 전달된 현재 업무의 사유별 기록을 가져옵니다.
const savedDetails = computed(() => {
  if (props.type === "loss") {
    return (
      props.product?.operational_loss_details ??
      props.product?.loss_details ??
      []
    );
  }

  return (
    props.product?.operational_waste_details ??
    props.product?.waste_details ??
    []
  );
});

// 현재 로스·폐기 합계를 가져옵니다.
const currentTotal = computed(() => {
  if (props.type === "loss") {
    return props.product?.operational_loss ?? props.product?.loss;
  }

  return props.product?.operational_waste ?? props.product?.waste;
});

// 당일 생산과 들어온 이월 수량의 합계입니다.
const available = computed(() =>
  Number(props.product?.production || 0) +
  Number(props.product?.carryover_in || 0)
);

// 로스·폐기 외 업무에서 이미 처리한 수량을 표시합니다.
const otherAllocationDetails = computed(() => {
  const product = props.product || {};

  const values =
    props.type === "loss"
      ? [
          [
            "폐기",
            Number(
              product.operational_waste ??
                product.waste ??
                0
            ),
          ],
          ["이월 예정", Number(product.carryover_out || 0)],
        ]
      : [
          [
            "로스",
            Number(
              product.operational_loss ??
                product.loss ??
                0
            ),
          ],
          ["이월 예정", Number(product.carryover_out || 0)],
        ];

  return values
    .filter(([, quantity]) => quantity > 0)
    .map(([label, quantity]) => ({
      label,
      quantity,
    }));
});

// 다른 업무에서 사용한 총 수량입니다.
const otherAllocated = computed(() =>
  otherAllocationDetails.value.reduce(
    (sum, item) => sum + item.quantity,
    0
  )
);

// 다른 업무 처리 수량을 제외한 전체 가용 수량입니다.
const remainingAvailable = computed(() =>
  Math.max(0, available.value - otherAllocated.value)
);

// 숫자가 아닌 값이나 미확인 수량을 구분하여 표시합니다.
function displayQuantity(value) {
  if (
    value === null ||
    value === undefined ||
    value === ""
  ) {
    return "미확인";
  }

  const quantity = Number(value);

  return Number.isFinite(quantity)
    ? `${quantity}개`
    : "미확인";
}

// 재고 출처와 생산 기록 ID가 같은 기록인지 비교합니다.
function sameStock(row, source, lotId) {
  return (
    row.stock_source === source &&
    row.stock_lot_id != null &&
    lotId != null &&
    String(row.stock_lot_id) === String(lotId)
  );
}

// 저장된 사유의 생산 기록과 재고 출처를 찾습니다.
function findSavedStock(item) {
  const sources = props.product?.stock_sources || [];

  return sources.find(
    (source) =>
      source.stock_lot_id != null &&
      item.stock_lot_id != null &&
      String(source.stock_lot_id) ===
        String(item.stock_lot_id) &&
      (
        !item.source ||
        source.source === item.source
      )
  ) || null;
}

// 저장된 사유를 수정 가능한 입력 행으로 복원합니다.
function savedReasonRow(item) {
  const source = findSavedStock(item);

  return {
    _key: ++reasonRowKeySequence,
    _persisted: true,
    reason_code: item.reason_code || "",
    reason_text: item.reason_text || null,
    quantity: Number(item.quantity || 0),
    stock_source:
      source?.source ||
      item.source ||
      (
        item.origin_production_date === props.workDate
          ? "today"
          : "carryover"
      ),
    stock_lot_id:
      item.stock_lot_id ?? source?.stock_lot_id ?? null,
  };
}

// 새로운 사유 입력 행을 생성합니다.
function emptyReason(
  stockSource = "today",
  stockLotId = null
) {
  return {
    _key: ++reasonRowKeySequence,
    _persisted: false,
    reason_code: "",
    reason_text: null,
    quantity: 0,
    stock_source: stockSource,
    stock_lot_id: stockLotId,
  };
}

// 입력값 비교 및 서버 전송에 필요한 항목만 추출합니다.
function normalizeReasonRow(row) {
  return {
    reason_code: row.reason_code || "",
    reason_text: row.reason_text || null,
    quantity: Number(row.quantity || 0),
    stock_source: row.stock_source || "",
    stock_lot_id: row.stock_lot_id ?? null,
  };
}

// 입력하지 않은 빈 신규 사유와 기존 사유를 구분합니다.
function isBlankNewReason(row) {
  return (
    !row._persisted &&
    !row.reason_code &&
    !String(row.reason_text || "").trim() &&
    Number(row.quantity || 0) === 0
  );
}

// 기존 처리 수량을 복원하여 수정 가능한 재고 수량을 계산합니다.
const editableStockSources = computed(() => {
  const sources = props.product?.stock_sources || [];

  return sources.map((source) => {
    const existing = savedDetails.value
      .filter(
        (item) =>
          source.stock_lot_id != null &&
          item.stock_lot_id != null &&
          String(item.stock_lot_id) ===
            String(source.stock_lot_id) &&
          (!item.source || item.source === source.source)
      )
      .reduce((sum, item) => {
        const quantity = Number(item.quantity);

        return Number.isSafeInteger(quantity) && quantity >= 0
          ? sum + quantity
          : sum;
      }, 0);

    const rawRemaining =
      source.unallocated_quantity ??
      source.remaining_quantity;

    const remaining =
      rawRemaining == null || rawRemaining === ""
        ? null
        : Number(rawRemaining);

    const maximum =
      Number.isSafeInteger(remaining) &&
      remaining >= 0
        ? remaining + existing
        : null;

    return {
      ...source,
      editable_raw_quantity: maximum,
    };
  });
});

// 특정 생산 기록에 기존 저장된 로스·폐기 수량을 합산합니다.
function savedQuantityForLot(stockSource, stockLotId) {
  if (stockLotId == null) return 0;

  return savedDetails.value.reduce((sum, item) => {
    const source = findSavedStock(item);

    if (
      source?.source !== stockSource ||
      item.stock_lot_id == null ||
      String(item.stock_lot_id) !== String(stockLotId)
    ) {
      return sum;
    }

    const quantity = Number(item.quantity);

    return Number.isSafeInteger(quantity) && quantity > 0
      ? sum + quantity
      : sum;
  }, 0);
}

// 이월탭과 동일한 생산 기록 선택 카드 정보를 구성합니다.
const stockLotOptions = computed(() => {
  const countsByDate = new Map();

  return editableStockSources.value
    .filter(
      (source) =>
        source.source === selectedSource.value &&
        source.stock_lot_id != null
    )
    .map((source) => {
      const productionDate =
        source.origin_production_date || "생산일 미확인";

      const sequence =
        (countsByDate.get(productionDate) || 0) + 1;

      countsByDate.set(productionDate, sequence);

      const quantity = source.editable_raw_quantity;

      return {
        value: source.stock_lot_id,
        recordName: `${
          selectedSource.value === "today"
            ? "생산 기록"
            : "이월 기록"
        } ${sequence}`,
        productionDate,
        processedQuantity: savedQuantityForLot(
          source.source,
          source.stock_lot_id
        ),
        availableQuantity: quantity,
      };
    });
});

// 선택한 재고 출처와 생산 기록을 찾습니다.
const selectedStockLot = computed(() => {
  if (selectedLotId.value == null) return null;

  return editableStockSources.value.find(
    (source) =>
      source.source === selectedSource.value &&
      source.stock_lot_id != null &&
      String(source.stock_lot_id) ===
        String(selectedLotId.value)
  ) || null;
});

// 선택한 재고에 기존 저장된 업무 수량입니다.
const selectedSavedQuantity = computed(() =>
  savedQuantityForLot(
    selectedSource.value,
    selectedLotId.value
  )
);

// 선택한 재고에서 수정 가능한 최대 수량입니다.
const selectedStockAvailable = computed(() => {
  const raw = selectedStockLot.value?.editable_raw_quantity;

  if (
    raw == null ||
    !Number.isSafeInteger(Number(raw)) ||
    Number(raw) < 0
  ) {
    return null;
  }

  return Number(raw);
});

// 선택한 생산 기록의 사유 행만 2단계에 표시합니다.
const selectedReasonRows = computed(() =>
  reasonRows.value
    .filter((row) =>
      sameStock(
        row,
        selectedSource.value,
        selectedLotId.value
      )
    )
    .map((row) => ({
      key: row._key,
      row,
    }))
);

// 선택한 생산 기록에 입력한 사유별 수량을 합산합니다.
const selectedEnteredQuantity = computed(() =>
  selectedReasonRows.value.reduce(
    (sum, entry) =>
      sum + Number(entry.row.quantity || 0),
    0
  )
);

// 선택한 생산 기록의 입력 후 잔여량을 계산합니다.
const selectedSourceRemaining = computed(() => {
  if (selectedStockAvailable.value === null) return null;

  return Math.max(
    0,
    selectedStockAvailable.value -
      selectedEnteredQuantity.value
  );
});

// 선택한 입력 행의 사유·직접입력·수량을 변경합니다.
function updateReasonField(key, field, value) {
  if (!props.canMutate || saving.value) return;

  const row = reasonRows.value.find(
    (item) => item._key === key
  );

  if (!row) return;

  if (field === "reason_code") {
    row.reason_code = value || "";

    // 직접입력이 아닌 사유로 바뀌면 이전 직접입력 내용을 제거합니다.
    if (value !== "other") {
      row.reason_text = null;
    }

    return;
  }

  if (field === "reason_text") {
    row.reason_text = value;
    return;
  }
  
  if (field === "quantity") {
    const maximum = selectedStockAvailable.value;

    if (maximum === null) {
      row.quantity = 0;
      return;
    }

    const usedByOthers = selectedReasonRows.value.reduce(
      (sum, entry) =>
        sum +
        (entry.key === key
          ? 0
          : Number(entry.row.quantity || 0)),
      0
    );

    const allowed = Math.max(0, maximum - usedByOthers);
    const requested = Number(value);

    if (!Number.isFinite(requested)) {
      row.quantity = 0;
      return;
    }

    row.quantity = Math.min(
      allowed,
      Math.max(0, Math.trunc(requested))
    );
  }
}

// 신규 입력 중 추가한 사유만 저장 전 취소할 수 있습니다.
function cancelNewReason(key) {
  if (!props.canMutate || saving.value) return;

  const row = reasonRows.value.find(
    (item) => item._key === key
  );

  if (!row || row._persisted) return;

  reasonRows.value = reasonRows.value.filter(
    (item) => item._key !== key
  );
}

// 재고 출처가 변경되면 생산 기록 선택을 초기화합니다.
function changeStockSource(value) {
  if (!props.canMutate || saving.value) return;

  selectedSource.value = value;
  selectedLotId.value = null;
  cardIndex.value = 0;
}

// 생산 기록을 선택하고 해당 사유·수량 입력으로 이동합니다.
function changeStockLot(value) {
  if (!props.canMutate || saving.value || value == null) {
    return;
  }

  const source = editableStockSources.value.find(
    (item) =>
      item.source === selectedSource.value &&
      item.stock_lot_id != null &&
      String(item.stock_lot_id) === String(value)
  );

  if (!source) {
    emit(
      "error",
      "선택한 생산 기록을 확인할 수 없습니다."
    );
    return;
  }

  selectedLotId.value = source.stock_lot_id;

  // 이미 작성한 사유가 있으면 재사용하고 새 행은 중복 생성하지 않습니다.
  const existing = reasonRows.value.some((row) =>
    sameStock(
      row,
      source.source,
      source.stock_lot_id
    )
  );

  if (!existing) {
    reasonRows.value.push(
      emptyReason(
        source.source,
        source.stock_lot_id
      )
    );
  }

  cardIndex.value = 1;
}

// 선택한 생산 기록에 추가 사유 입력 행을 생성합니다.
function addReason() {
  if (
    !props.canMutate ||
    saving.value ||
    selectedSourceRemaining.value === null ||
    selectedSourceRemaining.value <= 0
  ) return;

  if (!selectedStockLot.value) {
    cardIndex.value = 0;
    emit(
      "error",
      "사용할 생산 기록을 선택해 주세요."
    );
    return;
  }

  reasonRows.value.push(
    emptyReason(
      selectedSource.value,
      selectedLotId.value
    )
  );
}

// 수량이 입력된 사유의 재고 출처를 검증합니다.
const invalidSourceRows = computed(() =>
  reasonRows.value.filter((row) => {
    const quantity = Number(row.quantity || 0);

    if (quantity <= 0) return false;

    return !editableStockSources.value.some(
      (source) =>
        source.source === row.stock_source &&
        source.stock_lot_id != null &&
        row.stock_lot_id != null &&
        String(source.stock_lot_id) ===
          String(row.stock_lot_id)
    );
  })
);

// 같은 생산 기록에 입력한 모든 사유를 합산하여 수량 초과를 확인합니다.
const sourceOverages = computed(() => {
  const requested = new Map();

  for (const row of reasonRows.value) {
    const quantity = Number(row.quantity || 0);

    if (
      row.stock_lot_id == null ||
      !Number.isFinite(quantity) ||
      quantity <= 0
    ) {
      continue;
    }

    const key =
      `${row.stock_source}:${row.stock_lot_id}`;

    requested.set(
      key,
      (requested.get(key) || 0) + quantity
    );
  }

  return [...requested.entries()].filter(
    ([key, quantity]) => {
      const source = editableStockSources.value.find(
        (item) =>
          `${item.source}:${item.stock_lot_id}` === key
      );

      return (
        !source ||
        source.editable_raw_quantity == null ||
        quantity > Number(source.editable_raw_quantity)
      );
    }
  );
});

// 저장된 기록의 사유명을 표시합니다.
function savedReasonLabel(item) {
  const reason = productionReasonLabel(
    item,
    props.reasonOptions
  );

  if (
    !item.origin_production_date ||
    item.origin_production_date === props.workDate
  ) {
    return `오늘 생산 · ${reason}`;
  }

  const [, month, day] =
    String(item.origin_production_date).split("-");

  return `${Number(month)}/${Number(day)} 생산 이월 재고 · ${reason}`;
}

// 저장 기록의 펼침·접힘을 관리합니다.
function toggleExpanded(index) {
  if (expanded.value.includes(index)) {
    expanded.value = expanded.value.filter(
      (item) => item !== index
    );
    return;
  }

  expanded.value.push(index);
}

// 현재 입력 행을 저장 기록과 같은 형태로 초기화합니다.
function restoreSavedRows() {
  reasonRows.value = savedDetails.value.map(savedReasonRow);

  initialReasonSnapshot.value = JSON.stringify(
    reasonRows.value.map(normalizeReasonRow)
  );
}

// 제품이나 날짜가 변경되었을 때 입력 상태를 초기화합니다.
function resetState() {
  restoreSavedRows();

  selectedSource.value = "today";
  selectedLotId.value = null;

  note.value = "";
  editorOpen.value = false;
  zeroCardOpen.value = false;
  savingZero.value = false;

  cardIndex.value = 0;
  expanded.value = [];

  pendingAction.value = null;
  deletingReasonIndex.value = null;

  confirmSave.value = false;
  confirmClose.value = false;
  deleteConfirmOpen.value = false;
}

// 제품·날짜·탭이 변경되면 해당 로스·폐기 기록을 복원합니다.
watch(
  () => [
    props.modelValue,
    props.product?.id,
    props.workDate,
    props.type,
  ],
  ([isOpen, productId, workDate], previous = []) => {
    if (!isOpen || saving.value) return;

    const [
      wasOpen,
      previousProductId,
      previousDate,
    ] = previous;

    if (
      wasOpen &&
      previousProductId === productId &&
      previousDate === workDate
    ) {
      return;
    }

    resetState();
  },
  { immediate: true }
);

// 빈 신규 행은 제외하고 실제 작성 내용만 비교합니다.
function activeReasonRows() {
  return reasonRows.value.filter(
    (row) => !isBlankNewReason(row)
  );
}

// 기존 저장 상태와 현재 입력 내용을 비교합니다.
function isDirty() {
  const original = initialReasonSnapshot.value;

  const current = JSON.stringify(
    activeReasonRows().map(normalizeReasonRow)
  );

  return current !== original || Boolean(note.value);
}

// 작성 중인 내용이 있다면 취소 확인 후 이동합니다.
function requestLeave(action) {
  if (
    saving.value ||
    confirmSave.value ||
    confirmClose.value ||
    deleteConfirmOpen.value
  ) {
    return false;
  }

  if (editorOpen.value && isDirty()) {
    pendingAction.value =
      typeof action === "function" ? action : null;

    confirmClose.value = true;
    return false;
  }

  if (typeof action === "function") {
    action();
  }

  return true;
}

// 작성 취소를 확정하면 변경 사항을 초기화하고 요청된 동작을 실행합니다.
function forceClose() {
  const action = pendingAction.value;

  resetState();

  if (typeof action === "function") {
    action();
  } else {
    emit("update:modelValue", false);
  }
}

// 취소 확인창을 그냥 닫았다면 보류된 동작도 취소합니다.
function handleCloseConfirmChange(value) {
  if (!value) {
    confirmClose.value = false;
    pendingAction.value = null;
  }
}

// 일반 입력 화면 열기·닫기
function toggleEditor() {
  if (!props.canMutate || saving.value) return;

  if (editorOpen.value) {
    requestLeave(() => {
      restoreSavedRows();
      note.value = "";
      editorOpen.value = false;
    });
    return;
  }

  restoreSavedRows();

  selectedSource.value = "today";
  selectedLotId.value = null;
  cardIndex.value = 0;
  note.value = "";

  zeroCardOpen.value = false;
  editorOpen.value = true;
}

// 없음 확인 화면을 열거나 닫습니다.
function toggleZeroCard() {
  if (!props.canMutate || saving.value) return;

  const nextOpen = !zeroCardOpen.value;

  const changeView = () => {
    restoreSavedRows();
    note.value = "";
    editorOpen.value = false;
    zeroCardOpen.value = nextOpen;
  };

  if (editorOpen.value) {
    requestLeave(changeView);
    return;
  }

  changeView();
}

// 선택한 기존 기록을 2단계 수량·사유 입력 화면으로 불러옵니다.
function openForEdit(item) {
  if (
    !props.canMutate ||
    saving.value ||
    item?.stock_lot_id == null
  ) {
    return;
  }

  const source = findSavedStock(item);

  if (!source) {
    emit(
      "error",
      "선택한 생산 기록을 확인할 수 없습니다."
    );
    return;
  }

  const openEditor = () => {
    restoreSavedRows();

    selectedSource.value = source.source;
    selectedLotId.value = source.stock_lot_id;

    note.value = "";
    zeroCardOpen.value = false;
    editorOpen.value = true;
    cardIndex.value = 1;
  };

  if (editorOpen.value) {
    requestLeave(openEditor);
    return;
  }

  openEditor();
}

// 삭제할 저장 기록을 선택하고 삭제 확인창을 엽니다.
function askDeleteReason(index) {
  if (
    !props.canMutate ||
    saving.value ||
    !savedDetails.value[index]
  ) {
    return;
  }

  const openDeleteConfirm = () => {
    deletingReasonIndex.value = index;
    deleteConfirmOpen.value = true;
  };

  if (editorOpen.value) {
    requestLeave(openDeleteConfirm);
    return;
  }

  openDeleteConfirm();
}

// 삭제 대상 외의 사유는 유지하고 기존 PUT API로 삭제를 처리합니다.
async function deleteSavedReason() {
  const index = deletingReasonIndex.value;

  if (
    !props.canMutate ||
    saving.value ||
    index == null ||
    !savedDetails.value[index]
  ) {
    return;
  }

  const originalDetails = savedDetails.value;

  // 기존 기록을 기준으로 삭제 대상만 제거합니다.
  restoreSavedRows();

  reasonRows.value = originalDetails
    .filter((_, itemIndex) => itemIndex !== index)
    .map(savedReasonRow);

  note.value = "";
  savingZero.value = false;
  zeroCardOpen.value = false;
  editorOpen.value = true;
  deleteConfirmOpen.value = false;
  deletingReasonIndex.value = null;

  confirmSave.value = true;
  await save();
}

// 저장 전 재고별 수량을 검증하고 서버 전송용 행을 만듭니다.
function allocateReasonRows(rows) {
  const sources = editableStockSources.value.map(
    (source) => ({
      ...source,
      available: source.editable_raw_quantity,
    })
  );

  const allocatedRows = [];

  for (const row of rows) {
    const quantity = Number(row.quantity);

    if (
      row.quantity == null ||
      row.quantity === "" ||
      !Number.isSafeInteger(quantity) ||
      quantity <= 0
    ) {
      throw new Error("처리 수량을 확인해 주세요.");
    }

    const source = sources.find(
      (item) =>
        item.source === row.stock_source &&
        item.stock_lot_id != null &&
        row.stock_lot_id != null &&
        String(item.stock_lot_id) ===
          String(row.stock_lot_id)
    );

    if (!source) {
      throw new Error(
        "선택한 생산 기록을 확인할 수 없습니다."
      );
    }

    if (
      source.available == null ||
      !Number.isSafeInteger(source.available) ||
      source.available < quantity
    ) {
      throw new Error(
        "선택한 생산 기록의 남은 수량이 부족합니다."
      );
    }

    source.available -= quantity;

    allocatedRows.push({
      reason_code: row.reason_code,
      reason_text: row.reason_text || null,
      quantity,
      stock_source: source.source,
      stock_lot_id: source.stock_lot_id,
    });
  }

  return allocatedRows;
}

// 저장할 사유·수량·재고·메모가 유효한지 검사합니다.
function validateInput() {
  if (
    !props.product?.id ||
    !props.storeId ||
    !props.workDate
  ) {
    return "저장할 제품과 날짜를 확인해 주세요.";
  }

  if (String(note.value || "").length > 1000) {
    return "메모는 1,000자 이내로 입력해 주세요.";
  }

  const rows = activeReasonRows();

  if (!rows.length) {
    return (
      `처리할 재고를 선택해 주세요. ` +
      `${dialogTitle.value}가 없다면 ` +
      `'${dialogTitle.value} 없음'을 이용해 주세요.`
    );
  }

  if (
    rows.some(
      (row) =>
        !row.reason_code ||
        row.quantity == null ||
        row.quantity === "" ||
        !Number.isSafeInteger(Number(row.quantity)) ||
        Number(row.quantity) <= 0
    )
  ) {
    return "추가한 사유와 수량을 모두 입력해 주세요.";
  }

  if (
    rows.some(
      (row) =>
        row.reason_code === "other" &&
        !String(row.reason_text || "").trim()
    )
  ) {
    return "직접입력 사유를 입력해 주세요.";
  }

  if (
    rows.some(
      (row) =>
        !row.stock_source ||
        row.stock_lot_id == null
    )
  ) {
    return "처리할 생산 기록을 선택해 주세요.";
  }

  if (invalidSourceRows.value.length) {
    return (
      "선택한 재고 출처를 찾을 수 없습니다. " +
      "재고를 다시 선택해 주세요."
    );
  }

  if (sourceOverages.value.length) {
    return (
      "선택한 생산 기록의 잔여 수량보다 " +
      "입력한 수량이 많습니다."
    );
  }

  return null;
}

// 저장 전 예상 처리 수량을 확인창에 표시합니다.
const confirmMessage = computed(() => {
  if (savingZero.value) {
    return `${dialogTitle.value} 없음(0개)으로 확인하시겠습니까?`;
  }

  const quantity = activeReasonRows().reduce(
    (sum, row) =>
      sum + Number(row.quantity || 0),
    0
  );

  return (
    `${dialogTitle.value} ${quantity}개로 저장하시겠습니까?\n\n` +
    "변경된 생산 기록의 사유와 수량만 반영됩니다."
  );
});

// 일반 입력값을 검증한 뒤 저장 확인창을 엽니다.
function askSave() {
  if (
    !props.canMutate ||
    saving.value ||
    !editorOpen.value ||
    confirmSave.value ||
    confirmClose.value ||
    deleteConfirmOpen.value
  ) {
    return;
  }

  const error = validateInput();

  if (error) {
    emit("error", error);
    return;
  }

  savingZero.value = false;
  confirmSave.value = true;
}

// 없음 확인 시 기존 저장된 기록이 있다면 일괄 삭제를 막습니다.
function askZeroSave() {
  if (
    !props.canMutate ||
    saving.value ||
    !zeroCardOpen.value ||
    confirmSave.value ||
    confirmClose.value
  ) {
    return;
  }

  if (savedDetails.value.length) {
    emit(
      "error",
      "기존 기록이 있습니다. 기록 목록에서 개별 수정하거나 삭제해 주세요."
    );
    return;
  }

  if (
    !props.product?.id ||
    !props.storeId ||
    !props.workDate
  ) {
    emit(
      "error",
      "저장할 제품과 날짜를 확인해 주세요."
    );
    return;
  }

  savingZero.value = true;
  confirmSave.value = true;
}

// 같은 생산 기록에 연결된 사유를 비교 가능한 형태로 묶습니다.
function groupByLot(rows) {
  const grouped = new Map();

  for (const row of rows) {
    const quantity = Number(row.quantity || 0);

    if (
      row.stock_lot_id == null ||
      !Number.isSafeInteger(quantity) ||
      quantity <= 0
    ) {
      continue;
    }

    const key =
      `${row.stock_source}:${row.stock_lot_id}`;

    if (!grouped.has(key)) {
      grouped.set(key, []);
    }

    grouped.get(key).push({
      reason_code: row.reason_code || "",
      reason_text: row.reason_text || "",
      quantity,
    });
  }

  for (const [key, values] of grouped) {
    grouped.set(
      key,
      JSON.stringify(
        values.sort((a, b) =>
          JSON.stringify(a).localeCompare(
            JSON.stringify(b)
          )
        )
      )
    );
  }

  return grouped;
}

// 기존 API의 생산 기록별 부분 수정 방식을 유지합니다.
async function save() {
  if (
    !props.canMutate ||
    saving.value ||
    !confirmSave.value
  ) {
    return;
  }

  saving.value = true;

  try {
    if (
      !props.product?.id ||
      !props.storeId ||
      !props.workDate
    ) {
      throw new Error(
        "저장할 제품과 날짜를 확인해 주세요."
      );
    }

    const originalRows = JSON.parse(
      initialReasonSnapshot.value || "[]"
    );

    const rowsToSave = savingZero.value
      ? []
      : allocateReasonRows(activeReasonRows());

    const before = groupByLot(originalRows);
    const after = groupByLot(rowsToSave);

    const allKeys = new Set([
      ...before.keys(),
      ...after.keys(),
    ]);

    // 기존 기록과 달라진 생산 기록만 저장 대상으로 지정합니다.
    const changedKeys = [...allKeys].filter(
      (key) => before.get(key) !== after.get(key)
    );

    const changedLotIds = [
      ...new Set(
        changedKeys.map((key) =>
          Number(key.substring(key.indexOf(":") + 1))
        )
      ),
    ];

    const payload = {
      store_id: props.storeId,
      product_id: props.product.id,
      work_date: props.workDate,
      type: props.type,
      note: savingZero.value
        ? null
        : note.value || null,
      reasons: rowsToSave.filter(
        (row) =>
          changedKeys.includes(
            `${row.stock_source}:${row.stock_lot_id}`
          )
      ),
      affected_stock_lot_ids: changedLotIds,
    };

    const { data } = await window.axios.put(
      "/tillwhite/api/production-management/flow",
      payload
    );

    confirmSave.value = false;
    resetState();

    emit(
      "saved",
      data?.message ||
        `${dialogTitle.value}을 저장했습니다.`,
      data?.daily || null
    );

    emit("update:modelValue", false);
  } catch (error) {
    confirmSave.value = false;

    emit(
      "error",
      error.response?.data?.message ||
        error.message ||
        `${dialogTitle.value}을 저장하지 못했습니다.`
    );

    // 삭제 실패 후에는 서버의 기존 데이터를 기준으로 복원합니다.
    if (!savingZero.value) {
      restoreSavedRows();
      editorOpen.value = false;
    }
  } finally {
    saving.value = false;
  }
}

// 부모 다이얼로그에 저장 진행 상태를 제공합니다.
const isSaving = computed(() => saving.value);

// 부모 다이얼로그에서 호출하는 기능을 공개합니다.
defineExpose({
  requestLeave,
  isSaving,
  editorOpen,
  askSave,
});
</script>

<style scoped>
.inventory-dialog__reason-tab {
  font-size: 12px;
}

.inventory-dialog__reason-tab :deep(.v-btn),
.inventory-dialog__reason-tab :deep(.v-field),
.inventory-dialog__reason-tab :deep(.v-label) {
  font-size: 12px;
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

.inventory-dialog__form-help {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

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
  line-height: 1.6;
}

.inventory-dialog__field-heading span {
  font-size: 12px;
  line-height: 1.6;
  opacity: 0.7;
}

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
  min-height: 36px;
}

.inventory-dialog__card-controls span {
  font-size: 12px;
  opacity: 0.7;
}

.inventory-dialog__swipe-area {
  width: 100%;
  min-width: 0;
  margin-top: 4px;
  touch-action: auto;
}

.inventory-dialog__swipe-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  min-width: 0;
  padding: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  text-align: left;
  overflow-wrap: anywhere;
}

.inventory-dialog__swipe-card :deep(.v-label) {
  font-size: 13px;
}

.inventory-dialog__swipe-card :deep(.v-field__input) {
  font-size: 13px;
}

.inventory-dialog__swipe-card :deep(.v-selection-control__wrapper) {
  font-size: 16px;
}

.inventory-dialog__reason-entry {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  min-width: 0;
  padding: 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.inventory-dialog__reason-entry + .inventory-dialog__reason-entry {
  margin-top: 2px;
}

.inventory-dialog__reason-entry .inventory-dialog__field-heading {
  margin-bottom: 2px;
}

.inventory-dialog__reason-entry :deep(.v-input) {
  width: 100%;
  min-width: 0;
}

.inventory-dialog__reason-entry :deep(.v-field) {
  background: rgb(var(--v-theme-surface));
}

.inventory-dialog__reason-entry :deep(.v-field__input) {
  font-size: 13px;
}

.inventory-dialog__reason-entry :deep(.v-label) {
  font-size: 13px;
}

.inventory-dialog__reason-entry .inventory-dialog__row-actions {
  margin-top: 0;
}

.inventory-dialog__production {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  text-align: left;
  font-size: 13px;
}

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
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

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

.inventory-dialog__production-record-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  margin-bottom: 10px;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  font: inherit;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.inventory-dialog__production-record-header:hover {
  background: rgba(var(--v-theme-on-surface), 0.065);
}

.inventory-dialog__production-record-header:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.inventory-dialog__production-record-header--selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
  box-shadow: inset 0 0 0 1px rgb(var(--v-theme-primary));
}

.inventory-dialog__production-record-label {
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

.inventory-dialog__production-record-quantity {
  font-size: 12px;
  font-weight: 400;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.inventory-dialog__production-record-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.inventory-dialog__carryover-record-content {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: flex-start;
  gap: 5px;
  min-width: 0;
}

.inventory-dialog__carryover-record-date {
  font-size: 12px;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.inventory-dialog__carryover-record-quantity {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 12px;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.72);
}

.inventory-dialog__carryover-record-quantity strong {
  font-size: 12px;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
}

.inventory-dialog__carryover-record-divider {
  opacity: 0.5;
}

.inventory-dialog__selected-stock,
.inventory-dialog__allocation {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 4px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.inventory-dialog__allocation > strong {
  padding: 10px 0;
  font-size: 12px;
}

.inventory-dialog__selected-stock-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  font-size: 12px;
}

.inventory-dialog__selected-stock-row +
.inventory-dialog__selected-stock-row {
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.inventory-dialog__selected-stock-row span {
  color: rgba(var(--v-theme-on-surface), 0.68);
}

.inventory-dialog__selected-stock-row strong {
  font-weight: 600;
  text-align: right;
}

.inventory-dialog__row-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.inventory-dialog__row-actions :deep(.v-btn) {
  min-width: 0;
}

.inventory-dialog__inner-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
}

.inventory-dialog__record-detail {
  display: block;
  width: 100%;
  min-width: 0;
  margin-bottom: 10px;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  font-size: 0.8rem;
  line-height: 1.6;
  overflow-wrap: anywhere;
}

.inventory-dialog__record-detail strong {
  display: block;
  margin-bottom: 6px;
  font-size: 0.8rem;
  font-weight: 600;
}

.inventory-dialog__record-detail > div {
  color: rgba(var(--v-theme-on-surface), 0.72);
  white-space: pre-wrap;
  font-size: 0.8rem;
  line-height: 1.6;
}

.inventory-dialog__record-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.inventory-dialog__production-empty {
  padding: 14px;
  border: 1px dashed rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 10px;
  font-size: 12px;
  opacity: 0.7;
  text-align: center;
}

@media (max-width: 600px) {
  .inventory-dialog__placeholder {
    padding: 14px;
  }

  .inventory-dialog__swipe-card {
    padding: 12px;
  }

  .inventory-dialog__reason-entry {
    padding: 12px;
  }

  .inventory-dialog__card-navigation {
    padding: 10px;
  }

  .inventory-dialog__card-steps {
    gap: 6px;
  }

  .inventory-dialog__card-steps :deep(.v-btn) {
    font-size: 11px;
  }
}

.inventory-dialog__memo-card {
  min-height: 220px;
}

@media (max-width: 600px) {
  .inventory-dialog__memo-card {
    min-height: 200px;
  }
}

/* 반응형: 부모 영역 밖으로 입력 카드와 기록 카드가 넘치지 않도록 처리 */
.inventory-dialog__reason-tab,
.inventory-dialog__placeholder,
.inventory-dialog__production-form,
.inventory-dialog__card-navigation,
.inventory-dialog__card-steps,
.inventory-dialog__swipe-card,
.inventory-dialog__reason-entry,
.inventory-dialog__production,
.inventory-dialog__production-record,
.inventory-dialog__selected-stock,
.inventory-dialog__inner-card {
  min-width: 0;
  max-width: 100%;
}

.inventory-dialog__production-record-header {
  min-width: 0;
  min-height: 44px;
  touch-action: manipulation;
}

.inventory-dialog__selected-stock-row strong {
  min-width: 0;
  overflow-wrap: anywhere;
}

.inventory-dialog__row-actions :deep(.v-btn) {
  min-height: 40px;
}

@media (max-width: 600px) {
  /* iOS Safari: 폼 포커스 시 화면 자동 확대 방지를 위한 16px */
  .inventory-dialog__reason-tab :deep(input:not([type="checkbox"])),
  .inventory-dialog__reason-tab :deep(textarea),
  .inventory-dialog__reason-tab :deep(.v-field__input) {
    font-size: 16px;
  }

  .inventory-dialog__card-controls :deep(.v-btn) {
    min-width: 0;
    min-height: 40px;
  }

  .inventory-dialog__card-steps :deep(.v-btn) {
    min-width: 0;
    padding-inline: 2px;
  }

  .inventory-dialog__selected-stock-row {
    min-width: 0;
    gap: 8px;
  }
}

@media (max-width: 360px) {
  .inventory-dialog__placeholder {
    padding: 10px;
  }

  .inventory-dialog__swipe-card,
  .inventory-dialog__inner-card,
  .inventory-dialog__reason-entry {
    padding: 10px;
  }

  .inventory-dialog__card-navigation {
    padding: 8px;
  }
}
</style>