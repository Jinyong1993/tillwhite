<template>
  <div class="inventory-dialog inventory-dialog__reason-tab">
    <div class="inventory-dialog__placeholder">
      <!-- 관리 제목 -->
      <strong>{{ dialogTitle }} 관리</strong>

      <p class="inventory-dialog__form-help">
        {{ dialogDescription }}
      </p>

      <!-- 수량 초과 안내 -->
      <v-alert
        v-if="sourceOverages.length || invalidSourceRows.length"
        type="error"
        variant="tonal"
        density="compact"
      >
        입력한 수량이 남은 수량보다 많습니다.
      </v-alert>

      <!-- 생산탭·이월탭과 동일한 작업 버튼 -->
      <div class="inventory-dialog__action-grid">
        <v-btn
          block
          variant="tonal"
          :disabled="saving || !canMutate"
          :prepend-icon="editorOpen ? 'mdi-chevron-up' : 'mdi-plus'"
          @click="toggleEditor"
        >
          {{ editorOpen ? "취소" : `${dialogTitle} 기록 설정` }}
        </v-btn>

        <v-btn
          block
          variant="tonal"
          :disabled="saving || !canMutate"
          :prepend-icon="
            zeroCardOpen ? 'mdi-chevron-up' : 'mdi-minus-circle-outline'
          "
          @click="toggleZeroCard"
        >
          {{ dialogTitle }} 없음
        </v-btn>
      </div>

      <!-- 로스·폐기 입력 -->
      <div
        v-if="editorOpen"
        class="inventory-dialog__production-form"
      >
        <div class="inventory-dialog__field-heading">
          <strong>{{ dialogTitle }} 수량과 사유</strong>

          <span>
            현재 {{ dialogTitle }} {{ displayQuantity(currentTotal) }}
          </span>
        </div>

        <!-- 기존 다이얼로그의 재고 현황 -->
        <div class="inventory-dialog__summary-grid">
          <div>
            <span>오늘 생산</span>
            <strong>{{ displayQuantity(product?.production) }}</strong>
          </div>

          <div>
            <span>이월 재고</span>
            <strong>{{ displayQuantity(product?.carryover_in) }}</strong>
          </div>

          <div>
            <span>확인 상태</span>
            <strong>{{ flowConfirmed ? "완료" : "미확인" }}</strong>
          </div>
        </div>

        <div class="inventory-dialog__information">
          <span>총 수량</span>
          <strong>{{ displayQuantity(available) }}</strong>
        </div>

        <div class="inventory-dialog__information">
          <span>{{ dialogTitle }} · 마지막 선택 기록 잔여량</span>
          <strong>
            {{ displayQuantity(selectedSourceRemaining) }}
          </strong>
        </div>

        <!-- 이미 처리된 다른 업무 -->
        <div
          v-if="otherAllocationDetails.length"
          class="inventory-dialog__detail-section"
        >
          <strong>이미 처리된 내역</strong>

          <div
            v-for="item in otherAllocationDetails"
            :key="item.label"
            class="inventory-dialog__record-detail"
          >
            <strong>{{ item.label }}</strong>
            <span>{{ displayQuantity(item.quantity) }}</span>
          </div>
        </div>

        <!-- 단계 이동 -->
        <div class="inventory-dialog__card-navigation">
          <div class="inventory-dialog__card-steps">
            <v-btn
              v-for="(title, index) in cardTitles"
              :key="title"
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

        <!-- 입력 카드 -->
        <div
          class="inventory-dialog__swipe-area"
          @pointerdown="onSwipeStart"
          @pointerup="onSwipeEnd"
          @pointercancel="onSwipeCancel"
        >
          <!-- 카드 1: 수량과 사유 -->
          <div
            v-show="cardIndex === 0"
            class="inventory-dialog__swipe-card"
          >
            <div class="inventory-dialog__field-heading">
              <strong>{{ dialogTitle }} 수량과 사유</strong>
              <span>{{ reasonRows.length }}건</span>
            </div>

            <!-- 기존 입력 기능 그대로 재사용 -->
            <ProductionReasonRows
              v-model="reasonRows"
              :reason-options="reasonOptions"
              :max-quantity="remainingAvailable"
              :stock-sources="editableStockSources"
              :work-date="workDate"
              :show-stock-source="true"
              :flow-type="type"
              :saved-details="savedDetails"
            />

            <div class="inventory-dialog__row-actions">
              <v-btn
                size="small"
                variant="text"
                prepend-icon="mdi-plus"
                :disabled="saving || !canMutate"
                @click="addReason"
              >
                사유 추가
              </v-btn>
            </div>
          </div>

          <!-- 카드 2: 메모 -->
          <div
            v-show="cardIndex === 1"
            class="inventory-dialog__swipe-card"
          >
            <p class="inventory-dialog__form-help">
              특이사항, 처리 과정에서 확인한 내용처럼 다음 근무자가
              참고할 내용을 적어 주세요.
            </p>

            <v-textarea
              v-model="note"
              label="메모 · 선택"
              variant="outlined"
              density="compact"
              rows="2"
            />
          </div>
        </div>
      </div>

      <!-- 로스·폐기 없음 -->
      <div
        v-if="zeroCardOpen"
        class="inventory-dialog__inner-card"
      >
        <strong>{{ dialogTitle }} 없음 확인</strong>

        <p class="inventory-dialog__form-help">
          {{ dialogDescription }}
        </p>

        <v-btn
          block
          variant="outlined"
          :disabled="saving || !canMutate"
          @click="askZeroSave"
        >
          {{ dialogTitle }} 0개 확인
        </v-btn>
      </div>

      <!-- 기록 목록 -->
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
            :key="`${item.stock_lot_id}-${item.reason_code}-${index}`"
            class="inventory-dialog__production-record"
          >
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
                <span>{{ savedReasonLabel(item) }}</span>
              </div>

              <div class="inventory-dialog__record-detail">
                <strong>최초 생산일</strong>
                <span>
                  {{ item.origin_production_date || "생산일 미확인" }}
                </span>
              </div>

              <div class="inventory-dialog__record-actions">
                <v-btn
                  size="small"
                  variant="outlined"
                  prepend-icon="mdi-pencil-outline"
                  :disabled="saving || !canMutate"
                  @click="openForEdit"
                >
                  수정
                </v-btn>
              </div>
            </div>
          </div>
        </template>

        <div
          v-else
          class="inventory-dialog__production-empty"
        >
          등록된 {{ dialogTitle }} 기록이 없습니다.
        </div>
      </section>
    </div>

    <!-- 저장 확인 -->
    <ConfirmDialog
      v-model="confirmSave"
      :title="`${dialogTitle} 저장`"
      :message="confirmMessage"
      :loading="saving"
      @confirm="save"
    />

    <!-- 작성 취소 확인 -->
    <ConfirmDialog
      v-model="confirmClose"
      title="작성 취소"
      message="작성 중인 내용이 있습니다. 닫으시겠습니까?"
      @confirm="forceClose"
    />
  </div>
</template>


<script setup>
import { computed, ref, watch } from "vue";
import ConfirmDialog from "../../common/ConfirmDialog.vue";
import ProductionReasonRows from "../ProductionReasonRows.vue";
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

// 입력 및 확인창
const editorOpen = ref(false);
const zeroCardOpen = ref(false);
const saving = ref(false);
const confirmSave = ref(false);
const confirmClose = ref(false);

// 작성 중 데이터
const reasonRows = ref([]);
const note = ref("");
const initialReasonSnapshot = ref("[]");

// 입력 카드
const cardIndex = ref(0);
const cardTitles = ["수량과 사유", "메모"];
let swipeStart = null;

// 기록 펼침 상태
const expanded = ref([]);

// 저장 유형: 일반 저장 또는 없음 확인
const savingZero = ref(false);

// 업무 정보
const dialogTitle = computed(() =>
  props.type === "loss" ? "로스" : "폐기"
);

const dialogDescription = computed(() =>
  props.type === "loss"
    ? "남은 수량 안에서 로스 수량과 사유를 입력해 주세요. 로스가 없으면 ‘로스 없음’을 누르세요."
    : "남은 수량 안에서 폐기 수량과 사유를 입력해 주세요. 폐기가 없으면 ‘폐기 없음’을 누르세요."
);

// 기존 확인 상태
const flowConfirmed = computed(() =>
  props.type === "loss"
    ? Boolean(props.product?.loss_confirmed)
    : Boolean(props.product?.waste_confirmed)
);

// 기존 저장 기록
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

// 화면에 표시할 현재 합계
const currentTotal = computed(() => {
  if (props.type === "loss") {
    return props.product?.operational_loss ?? props.product?.loss;
  }

  return props.product?.operational_waste ?? props.product?.waste;
});

// 기존 다이얼로그의 총 수량 계산
const available = computed(() =>
  Number(props.product?.production || 0) +
  Number(props.product?.carryover_in || 0)
);

// 다른 업무에 이미 사용한 재고
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
          [
            "이월 예정",
            Number(product.carryover_out || 0),
          ],
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
          [
            "이월 예정",
            Number(product.carryover_out || 0),
          ],
        ];

  return values
    .filter(([, quantity]) => quantity > 0)
    .map(([label, quantity]) => ({ label, quantity }));
});

const otherAllocated = computed(() =>
  otherAllocationDetails.value.reduce(
    (sum, item) => sum + item.quantity,
    0
  )
);

const remainingAvailable = computed(() =>
  Math.max(0, available.value - otherAllocated.value)
);

// 기존 기록 수정 시 현재 업무에서 이미 사용한 재고 복원
const editableStockSources = computed(() => {
  const sources = props.product?.stock_sources || [];
  const details = savedDetails.value;

  return sources.map((source) => {
    const stockLotId = source.stock_lot_id;

    const currentSaved =
      stockLotId == null
        ? 0
        : details
            .filter(
              (item) =>
                item.stock_lot_id != null &&
                String(item.stock_lot_id) === String(stockLotId)
            )
            .reduce((sum, item) => {
              const quantity = Number(item.quantity);

              return Number.isSafeInteger(quantity) &&
                quantity >= 0
                ? sum + quantity
                : sum;
            }, 0);

    const rawRemaining =
      Object.prototype.hasOwnProperty.call(
        source,
        "unallocated_quantity"
      )
        ? source.unallocated_quantity
        : source.remaining_quantity;

    const remaining =
      rawRemaining == null || rawRemaining === ""
        ? null
        : Number(rawRemaining);

    const editableQuantity =
      remaining !== null && Number.isFinite(remaining)
        ? remaining + currentSaved
        : null;

    return {
      ...source,
      editable_raw_quantity: editableQuantity,
      remaining_quantity:
        editableQuantity === null
          ? null
          : Math.max(0, editableQuantity),
    };
  });
});

// 선택한 생산 기록이 유효하지 않은 행
const invalidSourceRows = computed(() =>
  reasonRows.value.filter((row) => {
    const quantity = Number(row.quantity || 0);

    if (quantity <= 0) return false;

    if (
      !row.stock_source ||
      row.stock_lot_id == null ||
      row.stock_lot_id === ""
    ) {
      return true;
    }

    return !editableStockSources.value.some(
      (source) =>
        source.source === row.stock_source &&
        String(source.stock_lot_id) ===
          String(row.stock_lot_id)
    );
  })
);

// 같은 생산 기록에 입력한 수량 합산 및 초과 검사
const sourceOverages = computed(() => {
  const requested = new Map();

  for (const row of reasonRows.value) {
    const quantity = Number(row.quantity || 0);
    const lotId = row.stock_lot_id;

    if (
      lotId == null ||
      lotId === "" ||
      quantity <= 0
    ) {
      continue;
    }

    const key = `${row.stock_source}:${lotId}`;

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

      if (!source) return true;

      const remaining = source.editable_raw_quantity;

      return (
        remaining == null ||
        !Number.isFinite(Number(remaining)) ||
        quantity > Number(remaining)
      );
    }
  );
});

// 마지막 입력 행의 남은 재고
const selectedSourceRemaining = computed(() => {
  const selected = reasonRows.value.at(-1);

  if (selected?.stock_lot_id == null) return 0;

  const source = editableStockSources.value.find(
    (item) =>
      item.source === selected.stock_source &&
      String(item.stock_lot_id) ===
        String(selected.stock_lot_id)
  );

  if (!source) return 0;

  const availableQuantity = source.editable_raw_quantity;

  if (
    availableQuantity == null ||
    !Number.isFinite(Number(availableQuantity))
  ) {
    return 0;
  }

  const allocated = reasonRows.value
    .filter(
      (row) =>
        row.stock_source === selected.stock_source &&
        String(row.stock_lot_id) ===
          String(source.stock_lot_id)
    )
    .reduce(
      (sum, row) => sum + Number(row.quantity || 0),
      0
    );

  return Math.max(
    0,
    Number(availableQuantity) - allocated
  );
});

// 수량 표시: 0개와 미확인 구분
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

// 기존 저장 사유 표시
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

  const [, month, day] = String(
    item.origin_production_date
  ).split("-");

  return `${Number(month)}/${Number(day)} 생산 이월 재고 · ${reason}`;
}

// 저장 기록을 편집용 행으로 복원
function savedReasonRow(item) {
  const source = editableStockSources.value.find(
    (stock) =>
      stock.stock_lot_id != null &&
      item.stock_lot_id != null &&
      String(stock.stock_lot_id) ===
        String(item.stock_lot_id)
  );

  return {
    reason_code: item.reason_code || "",
    reason_text: item.reason_text || null,
    quantity: Number(item.quantity || 0),
    stock_source:
      source?.source ||
      (item.origin_production_date === props.workDate
        ? "today"
        : "carryover"),
    stock_lot_id:
      item.stock_lot_id ?? source?.stock_lot_id ?? null,
    _persisted: true,
  };
}

// 새로운 사유 입력 행
function emptyReason() {
  return {
    reason_code: "",
    reason_text: null,
    quantity: 0,
    stock_source: "today",
    stock_lot_id: null,
  };
}

// 새로운 로스·폐기 사유 입력 행을 추가합니다.
function addReason() {
  if (!props.canMutate || saving.value) return;

  reasonRows.value.push(emptyReason());
}

// 저장 기록 상세 정보의 펼침 상태를 변경합니다.
function toggleExpanded(index) {
  if (expanded.value.includes(index)) {
    expanded.value = expanded.value.filter(
      (value) => value !== index
    );
  } else {
    expanded.value.push(index);
  }
}

// 저장된 기록을 불러오고 입력 및 확인창 상태를 초기화합니다.
function resetState() {
  reasonRows.value = savedDetails.value.length
    ? savedDetails.value.map(savedReasonRow)
    : [emptyReason()];

  initialReasonSnapshot.value = JSON.stringify(
    reasonRows.value
  );

  note.value = "";
  editorOpen.value = false;
  zeroCardOpen.value = false;
  savingZero.value = false;
  cardIndex.value = 0;
  swipeStart = null;
  expanded.value = [];
  confirmSave.value = false;
  confirmClose.value = false;
}

watch(
  () => props.modelValue,
  (value) => {
    if (!saving.value) {
      resetState();
    }
  },
  { immediate: true }
);

// 작성 변경 여부
function isDirty() {
  return (
    JSON.stringify(reasonRows.value) !==
      initialReasonSnapshot.value ||
    Boolean(note.value)
  );
}

// 작성 내용 보호
function requestLeave(action) {
  if (saving.value || confirmSave.value || confirmClose.value) {
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

const pendingAction = ref(null);

// 작성 취소 확인 후 상태를 초기화하고 보류된 동작을 실행합니다.
function forceClose() {
  const action = pendingAction.value;

  pendingAction.value = null;
  confirmClose.value = false;

  resetState();

  if (typeof action === "function") {
    action();
  } else {
    emit("update:modelValue", false);
  }
}

// 로스·폐기 입력창을 열거나 작성 취소 절차를 거쳐 닫습니다.
function toggleEditor() {
  if (!props.canMutate || saving.value) return;

  if (editorOpen.value) {
    requestLeave(() => {
      editorOpen.value = false;
    });
    return;
  }

  zeroCardOpen.value = false;
  editorOpen.value = true;
  cardIndex.value = 0;
}

// 로스·폐기 없음 확인 카드를 열거나 닫습니다.
function toggleZeroCard() {
  if (!props.canMutate || saving.value) return;

  const nextOpen = !zeroCardOpen.value;

  if (editorOpen.value) {
    requestLeave(() => {
      editorOpen.value = false;
      zeroCardOpen.value = nextOpen;
    });
    return;
  }

  zeroCardOpen.value = nextOpen;
}

// 기존 기록을 수정할 수 있도록 입력창을 엽니다.
function openForEdit() {
  if (!props.canMutate || saving.value) return;

  editorOpen.value = true;
  zeroCardOpen.value = false;
  cardIndex.value = 0;
}

// 터치 또는 펜 입력의 스와이프 시작 좌표를 저장합니다.
function onSwipeStart(event) {
  swipeStart = null;

  if (
    event.pointerType !== "touch" &&
    event.pointerType !== "pen"
  ) {
    return;
  }

  if (
    event.target.closest(
      "button, input, textarea, select, a"
    )
  ) {
    return;
  }

  swipeStart = {
    pointerId: event.pointerId,
    x: event.clientX,
    y: event.clientY,
  };
}

// 스와이프 방향과 거리를 확인해 입력 카드를 전환합니다.
function onSwipeEnd(event) {
  const start = swipeStart;
  swipeStart = null;

  if (!start || start.pointerId !== event.pointerId) {
    return;
  }

  const dx = event.clientX - start.x;
  const dy = event.clientY - start.y;

  if (
    Math.abs(dx) < 55 ||
    Math.abs(dx) <= Math.abs(dy)
  ) {
    return;
  }

  if (dx < 0 && cardIndex.value < cardTitles.length - 1) {
    cardIndex.value++;
  } else if (dx > 0 && cardIndex.value > 0) {
    cardIndex.value--;
  }
}

// 취소된 스와이프의 시작 정보를 초기화합니다.
function onSwipeCancel() {
  swipeStart = null;
}

// 저장 전에 사용하는 행별 재고 검증
function allocateReasonRows(rows) {
  const sources = editableStockSources.value.map(
    (source) => {
      const raw = source.editable_raw_quantity;

      const available =
        raw == null || raw === ""
          ? null
          : Number(raw);

      return {
        ...source,
        available: Number.isFinite(available)
          ? available
          : null,
      };
    }
  );

  const allocatedRows = [];

  for (const row of rows) {
    const quantity = Number(row.quantity);

    if (
      row.quantity == null ||
      row.quantity === "" ||
      !Number.isSafeInteger(quantity) ||
      quantity < 0
    ) {
      throw new Error("처리 수량을 확인해 주세요.");
    }

    if (quantity === 0) continue;

    if (
      !row.stock_source ||
      row.stock_lot_id == null ||
      row.stock_lot_id === ""
    ) {
      throw new Error(
        "사용할 생산 기록을 선택해 주세요."
      );
    }

    const source = sources.find(
      (item) =>
        item.source === row.stock_source &&
        String(item.stock_lot_id) ===
          String(row.stock_lot_id)
    );

    if (!source) {
      throw new Error(
        "선택한 생산 기록을 확인할 수 없습니다."
      );
    }

    if (source.available === null) {
      throw new Error(
        "선택한 재고의 남은 수량을 확인할 수 없습니다."
      );
    }

    if (quantity > source.available) {
      throw new Error(
        "선택한 생산 기록의 남은 수량이 부족합니다."
      );
    }

    source.available -= quantity;

    allocatedRows.push({
      ...row,
      stock_source: source.source,
      stock_lot_id: source.stock_lot_id,
      quantity,
    });
  }

  return allocatedRows;
}

// 입력 유효성 검사
function validateInput() {
  if (!props.product?.id || !props.storeId || !props.workDate) {
    return "저장할 제품과 날짜를 확인해 주세요.";
  }

  if (
    reasonRows.value.length > 0 &&
    reasonRows.value.some(
      (row) =>
        !row.reason_code ||
        Number(row.quantity || 0) <= 0
    )
  ) {
    return "추가한 사유와 수량을 모두 입력해 주세요.";
  }

  if (
    reasonRows.value.some(
      (row) => !row.stock_lot_id
    )
  ) {
    return "처리할 재고의 생산일을 선택해 주세요.";
  }

  if (
    reasonRows.value.some(
      (row) =>
        row.reason_code === "other" &&
        !String(row.reason_text || "").trim()
    )
  ) {
    return "직접입력 사유를 입력해주세요.";
  }

  if (invalidSourceRows.value.length) {
    return "선택한 재고 출처를 찾을 수 없습니다. 재고를 다시 선택해 주세요.";
  }

  if (sourceOverages.value.length) {
    return "선택한 생산일의 잔여 재고보다 입력한 수량이 많습니다.";
  }

  return null;
}

// 저장 확인 문구
const confirmMessage = computed(() => {
  const quantity = savingZero.value
    ? 0
    : reasonRows.value.reduce(
        (sum, row) => sum + Number(row.quantity || 0),
        0
      );

  return `${dialogTitle.value} ${quantity}개로 저장하시겠습니까?`;
});

// 일반 저장 전에 입력 내용을 검증하고 저장 확인창을 엽니다.
function askSave() {
  if (
    !props.canMutate ||
    saving.value ||
    !editorOpen.value ||
    confirmSave.value ||
    confirmClose.value
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

// 로스·폐기 0개 저장을 위한 확인창을 엽니다.
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

  if (!props.product?.id || !props.storeId || !props.workDate) {
    emit("error", "저장할 제품과 날짜를 확인해 주세요.");
    return;
  }

  savingZero.value = true;
  confirmSave.value = true;
}

// 기존 로스·폐기 저장 방식 유지
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
      : allocateReasonRows(reasonRows.value);

    // 생산 기록별 사유와 수량을 비교할 수 있도록 그룹화합니다.
    const groupByLot = (rows) => {
      const grouped = new Map();

      for (const row of rows) {
        const quantity = Number(row.quantity || 0);
        const lotId = row.stock_lot_id;

        if (
          lotId == null ||
          lotId === "" ||
          !Number.isSafeInteger(quantity) ||
          quantity <= 0
        ) {
          continue;
        }

        const key = String(lotId);

        if (!grouped.has(key)) {
          grouped.set(key, []);
        }

        grouped.get(key).push({
          reason_code: row.reason_code || "",
          reason_text: row.reason_text || "",
          quantity,
          stock_source: row.stock_source || "",
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
    };

    const before = groupByLot(originalRows);
    const after = groupByLot(rowsToSave);

    const allLotIds = new Set([
      ...before.keys(),
      ...after.keys(),
    ]);

    const changedLotIds = [...allLotIds].filter(
      (id) => before.get(id) !== after.get(id)
    );

    const payload = {
      store_id: props.storeId,
      product_id: props.product.id,
      work_date: props.workDate,
      type: props.type,
      note: savingZero.value
        ? null
        : note.value || null,
      reasons: rowsToSave.filter((row) =>
        changedLotIds.includes(
          String(row.stock_lot_id)
        )
      ),
      affected_stock_lot_ids: changedLotIds.map(Number),
    };

    const { data } = await window.axios.put(
      "/tillwhite/api/production-management/flow",
      payload
    );

    confirmSave.value = false;
    pendingAction.value = null;

    resetState();

    emit(
      "saved",
      data.message ||
        `${dialogTitle.value}을 저장했습니다.`,
      data.daily || null
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
  } finally {
    saving.value = false;
  }
}

const isSaving = computed(() => saving.value);

defineExpose({
  requestLeave,
  isSaving,
  editorOpen,
  askSave,
});
</script>


<style scoped>
/* 로스·폐기 공통 기본 디자인*/
.inventory-dialog__reason-tab {
  font-size: 12px;
}

.inventory-dialog__reason-tab :deep(.v-btn),
.inventory-dialog__reason-tab :deep(.v-field),
.inventory-dialog__reason-tab :deep(.v-label) {
  font-size: 12px;
}

/* 관리 카드 */
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

/* 안내 문구 */
.inventory-dialog__form-help {
  margin: 0;
  font-size: 12px;
  line-height: 1.6;
  opacity: 0.75;
}

/* 작업 버튼*/
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

/* 입력 영역*/
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
  font-weight: 400;
  line-height: 1.6;
  opacity: 0.7;
  text-align: right;
}

/* 기존 재고 현황 */
.inventory-dialog__summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.inventory-dialog__summary-grid > div {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 0;
  padding: 14px 8px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.inventory-dialog__summary-grid span {
  font-size: 12px;
  opacity: 0.7;
}

.inventory-dialog__summary-grid strong {
  font-size: 18px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

/* 총 수량 및 선택 기록 잔여량 */
.inventory-dialog__information {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.inventory-dialog__information span {
  font-size: 12px;
  opacity: 0.7;
}

.inventory-dialog__information strong {
  font-size: 13px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* 다른 업무에서 이미 처리된 내역 */
.inventory-dialog__detail-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.inventory-dialog__detail-section > strong {
  font-size: 13px;
  font-weight: 600;
}

/* 입력 카드 탐색*/
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
  grid-template-columns: repeat(2, minmax(0, 1fr));
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

.inventory-dialog__card-controls :deep(.v-btn) {
  min-width: 76px;
}

.inventory-dialog__card-controls span {
  font-size: 12px;
  opacity: 0.7;
}

/* 모바일 스와이프 */
.inventory-dialog__swipe-area {
  width: 100%;
  min-width: 0;
  margin-top: 4px;
  touch-action: pan-y;
}

/* 입력 카드 */
.inventory-dialog__swipe-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  min-width: 0;
  min-height: 220px;
  padding: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  text-align: left;
  overflow-wrap: anywhere;
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

/* 사유 추가 버튼 */
.inventory-dialog__row-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
}

/* 로스 없음 / 폐기 없음 카드*/
.inventory-dialog__inner-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
}

/* 합계 및 저장 기록 생산탭과 동일한 디자인*/
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
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.6;
}

/* 저장 기록 카드 */
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

/* 저장 기록 수량 헤더 */
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

.inventory-dialog__production-record-header:hover {
  background: rgba(var(--v-theme-on-surface), 0.065);
}

.inventory-dialog__production-record-header:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 수량 헤더 제목 */
.inventory-dialog__production-record-label {
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

/* 수량 */
.inventory-dialog__production-record-quantity {
  font-size: 1rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* 생산탭 기록 헤더 굵기와 통일 */
.inventory-dialog__production-record-header
  .inventory-dialog__production-record-label {
  font-size: 0.8rem;
  font-weight: 600;
}

.inventory-dialog__production-record-header
  .inventory-dialog__production-record-quantity {
  font-size: 1rem;
  font-weight: 700;
}

.inventory-dialog__production-record-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  opacity: 0.7;
}

/* 기록 상세 정보*/
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

.inventory-dialog__production-record
.inventory-dialog__record-detail strong {
  display: block;
  margin-bottom: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

.inventory-dialog__record-detail span {
  display: block;
  font-size: 0.8rem;
  line-height: 1.6;
}

/* 수정 버튼 */
.inventory-dialog__record-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

/* 기록이 없을 때 */
.inventory-dialog__production-empty {
  padding: 14px;
  border: 1px dashed rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 10px;
  font-size: 12px;
  opacity: 0.7;
  text-align: center;
}

/* 모바일*/
@media (max-width: 600px) {
  .inventory-dialog__placeholder {
    padding: 14px;
  }

  .inventory-dialog__swipe-card {
    padding: 12px;
    min-height: 200px;
  }

  .inventory-dialog__card-navigation {
    padding: 10px;
    gap: 10px;
  }

  .inventory-dialog__card-steps {
    gap: 4px;
  }

  .inventory-dialog__card-controls :deep(.v-btn) {
    min-width: 64px;
  }

  .inventory-dialog__summary-grid strong {
    font-size: 15px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .inventory-dialog__swipe-card {
    animation: none;
  }
}
</style>