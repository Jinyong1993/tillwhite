<template>
  <div class="inventory-dialog inventory-dialog__carryover-tab">
    <!-- 이월 관리 -->
    <div class="inventory-dialog__placeholder">
      <div class="inventory-dialog__management-heading">
        <strong>이월 관리</strong>

        <span
          class="inventory-dialog__status"
          :class="{
            'inventory-dialog__status--confirmed': carryoverConfirmed
          }"
        >
          <v-icon
            :icon="
              carryoverConfirmed
                ? 'mdi-check-circle-outline'
                : 'mdi-clock-outline'
            "
            size="15"
          />

          {{ carryoverConfirmed ? "확인 완료" : "미확인" }}
        </span>
      </div>

      <div class="inventory-dialog__carryover-summary">
        <div>
          <span>이월 재고</span>
          <strong>{{ displayQuantity(product?.carryover_in) }}</strong>
        </div>

        <div>
          <span>이월 예정</span>
          <strong>{{ displayQuantity(product?.carryover_out) }}</strong>
        </div>
      </div>

      <p class="inventory-dialog__form-help">
        생산 기록을 선택해 다음 영업일로 넘길 수량을 관리합니다.
        기존 기록은 '기록 확인'에서 선택하여 수정할 수 있습니다.
      </p>

      <!-- 이월 업무 선택 버튼 -->
      <div class="inventory-dialog__action-grid">
        <v-btn
          block
          variant="tonal"
          :disabled="saving || (!canMutate && !editorOpen)"
          :prepend-icon="editorOpen ? 'mdi-chevron-up' : 'mdi-plus'"
          @click="toggleEditor"
        >
          {{ editorOpen ? "입력 닫기" : "추가 / 수정" }}
        </v-btn>

        <v-btn
          block
          variant="tonal"
          :disabled="saving"
          prepend-icon="mdi-clipboard-text-outline"
          @click="toggleHistory"
        >
          {{ historyOpen ? "기록 접기" : "기록 확인" }}
        </v-btn>
      </div>

      <!-- 기존 이월 기록 -->
      <div
        v-if="historyOpen"
        class="inventory-dialog__inner-card"
      >
        <strong>현재 이월 기록</strong>

        <div v-if="savedCarryoverLots.length">
          <button
            v-for="item in savedCarryoverLots"
            :key="`${item.source}-${item.stock_lot_id}`"
            type="button"
            class="inventory-dialog__detail-row inventory-dialog__history-button"
            :disabled="saving || !canMutate"
            @click="editCarryoverLot(item)"
          >
            <span>
              {{ item.origin_production_date || "생산일 미확인" }}
              ·
              {{ item.source === "carryover" ? "기존 이월분 · 재이월" : "생산" }}
            </span>

            <strong>
              {{ displayQuantity(item.carryover_out_quantity) }}
              <v-icon
                v-if="canMutate"
                icon="mdi-pencil-outline"
                size="16"
                class="ml-1"
              />
            </strong>
          </button>
        </div>

        <div v-else class="inventory-dialog__stock-empty">
          등록된 이월 기록이 없습니다.
        </div>
      </div>

      <!-- 이월 입력창 -->
      <div
        v-if="editorOpen"
        class="inventory-dialog__inner-card"
      >
        <div class="inventory-dialog__field-heading">
          <strong>다음 영업일로 넘길 수량</strong>
        </div>

        <!-- 이월할 재고 출처 -->
        <v-select
          v-if="
            Number(product?.carryover_in || 0) > 0 ||
            (product?.stock_sources || []).some(
              (source) => source.source === 'carryover'
            )
          "
          v-model="carryoverSource"
          :items="carryoverSources"
          item-title="title"
          item-value="value"
          label="이월할 재고 출처"
          variant="outlined"
          density="compact"
          @update:model-value="changeCarryoverSource"
        />

        <!-- 이월할 생산 기록 -->
        <v-select
          v-model="selectedCarryoverLotId"
          :items="carryoverLotOptions"
          item-title="title"
          item-value="value"
          label="이월할 생산 기록"
          variant="outlined"
          density="compact"
          hide-details="auto"
          clearable
          :disabled="carryoverLotOptions.length === 0"
          @update:model-value="changeCarryoverLot"
        />

        <!-- 생산 기록을 선택하지 않은 경우 -->
        <p
          v-if="selectedCarryoverLotId == null"
          class="inventory-dialog__form-help"
        >
          {{
            savedCarryoverLots.length > 0
              ? "기존 이월을 수정하려면 생산 기록을 선택해 주세요."
              : "이월할 생산 기록을 선택해 주세요. 이월이 없다면 0개로 확인할 수 있습니다."
          }}
        </p>

        <!-- 선택한 생산 기록의 재고 정보 -->
        <div
          v-if="selectedCarryoverLot"
          class="inventory-dialog__selected-stock"
        >
          <div class="inventory-dialog__selected-stock-row">
            <span>최초 생산일</span>
            <strong>
              {{
                selectedCarryoverLot.origin_production_date ||
                "생산일 미확인"
              }}
            </strong>
          </div>

          <div class="inventory-dialog__selected-stock-row">
            <span>기존 이월 처리</span>
            <strong>
              {{
                displayQuantity(
                  selectedCarryoverLot.carryover_out_quantity || 0
                )
              }}
            </strong>
          </div>

          <div class="inventory-dialog__selected-stock-row">
            <span>수정 가능 최대 수량</span>
            <strong>
              {{ displayQuantity(carryoverSourceAvailable) }}
            </strong>
          </div>
        </div>

        <!-- 선택한 생산 기록의 이월 수량 -->
        <v-number-input
          v-model="carryoverQuantity"
          label="이월 수량"
          variant="outlined"
          density="compact"
          :min="0"
          :max="carryoverSourceAvailable"
          :disabled="selectedCarryoverLotId == null"
        />

        <!-- 이월 수량 0개 확인 안내 -->
        <v-alert
          v-if="
            carryoverQuantity !== null &&
            carryoverQuantity !== undefined &&
            String(carryoverQuantity).trim() !== '' &&
            Number(carryoverQuantity) === 0
          "
          type="info"
          variant="tonal"
          density="compact"
        >
          <template v-if="selectedCarryoverLotId == null && !hasSavedCarryover">
            이월할 제품이 없다면 생산 기록을 선택하지 않고
            이월 없음(0개)으로 확인할 수 있습니다.
          </template>

          <template v-else-if="selectedCarryoverLotId != null">
            현재 0개가 입력되어 있습니다.
            저장하면 선택한 생산 기록의 이월 수량이 0개로 변경됩니다.
          </template>

          <template v-else>
            기존 이월 기록을 0개로 변경하려면
            수정할 생산 기록을 먼저 선택해 주세요.
          </template>
        </v-alert>

        <!-- 이월 가능 수량 초과 안내 -->
        <v-alert
          v-if="quantityExceeded"
          type="error"
          variant="tonal"
          density="compact"
        >
          선택한 생산 기록의 이월 가능 수량을 초과했습니다.
        </v-alert>

        <!-- 재이월 안내 -->
        <v-alert
          v-if="carryoverQuantity > 0 && carryoverSource === 'incoming'"
          type="warning"
          variant="tonal"
          density="compact"
        >
          이미 이월된 제품을 다시 이월합니다. 원래 생산일은 유지됩니다.
        </v-alert>

        <p class="inventory-dialog__form-help">
          특이사항을 메모로 남길 수 있습니다.
        </p>

        <v-textarea
          v-model="note"
          label="메모 · 선택"
          variant="outlined"
          density="compact"
          rows="2"
          maxlength="1000"
          counter="1000"
          hide-details="auto"
          placeholder="특이사항이나 다음 근무자에게 전달할 내용을 입력하세요."
        />
      </div>
    </div>
  </div>

  <!-- 이월 저장 확인창 -->
  <ConfirmDialog
    v-model="confirmSave"
    title="이월 저장"
    :message="confirmMessage"
    :loading="saving"
    @confirm="save"
  />

  <!-- 미저장 작성 내용 취소 확인창 -->
  <ConfirmDialog
    v-model="confirmDiscard"
    title="작성 취소"
    message="작성 중인 내용이 있습니다. 닫으시겠습니까?"
    :loading="saving"
    @update:model-value="handleDiscardDialogChange"
    @confirm="confirmDiscardChanges"
  />
</template>

<script setup>
import { computed, ref, watch } from "vue";
import ConfirmDialog from "../../common/ConfirmDialog.vue";

const emit = defineEmits([
  "update:modelValue",
  "saved",
  "error",
]);

// 이월 입력창 및 기록 확인창 상태
const editorOpen = ref(false);
const historyOpen = ref(false);

// 저장 및 확인창 상태
const saving = ref(false);
const confirmSave = ref(false);
const confirmDiscard = ref(false);

// 미저장 확인 후 실행할 동작
const pendingDiscardAction = ref(null);

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  storeId: {
    type: Number,
    default: null,
  },

  product: {
    type: Object,
    default: null,
  },

  workDate: {
    type: String,
    default: "",
  },

  canMutate: {
    type: Boolean,
    default: false,
  },
});

// 이월 입력 상태
const carryoverQuantity = ref(0);
const carryoverSource = ref("today");
const selectedCarryoverLotId = ref(null);
const note = ref("");

// 최초 입력 상태
const initialCarryoverSource = ref("today");
const initialCarryoverLotId = ref(null);
const initialCarryoverQuantity = ref(0);

// 기존 이월 출처
const carryoverSources = [
  { value: "today", title: "생산" },
  { value: "incoming", title: "기존 이월분 · 재이월" },
];

function displayQuantity(value) {
  if (value === null || value === undefined || value === "") {
    return "미확인";
  }

  const quantity = Number(value);

  return Number.isFinite(quantity) ? `${quantity}개` : "미확인";
}

// 출처별 생산 기록 선택 목록
const carryoverLotOptions = computed(() => {
  const isIncoming = carryoverSource.value === "incoming";
  const sourceType = isIncoming ? "carryover" : "today";

  const countsByDate = new Map();

  return (props.product?.stock_sources || [])
    .filter(
      (source) =>
        source.source === sourceType &&
        source.stock_lot_id != null
    )
    .map((source) => {
      const productionDate =
        source.origin_production_date || "생산일 미확인";

      const sequence =
        (countsByDate.get(productionDate) || 0) + 1;

      countsByDate.set(productionDate, sequence);

      const remainingRaw =
        source.unallocated_quantity ??
        source.remaining_quantity;

      const remaining =
        remainingRaw == null ? null : Number(remainingRaw);

      const existing =
        Number(source.carryover_out_quantity || 0);

      const available =
        remaining !== null && Number.isFinite(remaining)
          ? remaining + existing
          : null;

      const status =
        existing > 0
          ? `이월 완료 ${existing}개`
          : "이월 전";

      const quantityText =
        available === null
          ? "수량 미확인"
          : available < 0
            ? `초과 처리 ${Math.abs(available)}개`
            : `이월 가능 ${available}개`;

      const recordType =
        isIncoming ? "이월 기록" : "생산 기록";

      return {
        value: source.stock_lot_id,
        title:
          `${recordType} ${sequence} · ` +
          `최초 생산일 ${productionDate} · ` +
          `${status} · ${quantityText}`,
      };
    });
});

// 현재 이월 처리된 생산 기록 목록
const savedCarryoverLots = computed(() => {
  return (props.product?.stock_sources || []).filter(
    (source) =>
      source.stock_lot_id != null &&
      Number(source.carryover_out_quantity || 0) > 0
  );
});

// 현재 이월 입력창에서 선택한 생산 기록
const selectedCarryoverLot = computed(() => {
  if (selectedCarryoverLotId.value == null) {
    return null;
  }

  const sourceType =
    carryoverSource.value === "incoming"
      ? "carryover"
      : "today";

  return (
    (props.product?.stock_sources || []).find(
      (source) =>
        source.source === sourceType &&
        source.stock_lot_id != null &&
        String(source.stock_lot_id) ===
          String(selectedCarryoverLotId.value)
    ) || null
  );
});

// 선택한 생산 기록에서 이월 처리할 수 있는 최대 수량
const carryoverSourceAvailable = computed(() => {
  const source = selectedCarryoverLot.value;

  if (!source) {
    return 0;
  }

  const remainingRaw =
    source.unallocated_quantity ??
    source.remaining_quantity;

  // 재고 계산에 필요한 값이 없거나 올바르지 않으면
  // 임의로 이월 가능 수량을 만들지 않습니다.
  if (
    remainingRaw === null ||
    remainingRaw === undefined ||
    remainingRaw === ""
  ) {
    return 0;
  }

  const remaining = Number(remainingRaw);
  const existing = Number(source.carryover_out_quantity ?? 0);

  if (
    !Number.isSafeInteger(remaining) ||
    !Number.isSafeInteger(existing) ||
    existing < 0
  ) {
    return 0;
  }

  return Math.max(0, remaining + existing);
});

// 선택한 생산 기록의 이월 가능 수량 초과 여부
const quantityExceeded = computed(() => {
  return (
    Number(carryoverQuantity.value || 0) >
    carryoverSourceAvailable.value
  );
});

// 서버에서 전달받은 이월 확인 상태
const carryoverConfirmed = computed(() => {
  return Boolean(props.product?.disposition_confirmed);
});

// 기존 이월 기록 보유 여부
const hasSavedCarryover = computed(() => {
  return savedCarryoverLots.value.length > 0;
});

// 이월 저장 전 최종 확인 문구
const confirmMessage = computed(() => {
  const quantity = Number(carryoverQuantity.value);

  // 이월 없음(0개) 확인
  if (quantity === 0 && selectedCarryoverLotId.value == null) {
    return (
      "다음 영업일로 이월할 수량이 없습니다.\n\n" +
      "이월 없음(0개)으로 확인하시겠습니까?"
    );
  }

  // 기존 이월 기록을 0개로 수정
  if (quantity === 0 && selectedCarryoverLotId.value != null) {
    return (
      "선택한 생산 기록의 이월 수량을 0개로 변경하시겠습니까?\n\n" +
      "해당 생산 기록의 기존 이월 처리가 취소됩니다."
    );
  }

  // 기존 이월분 재이월
  if (carryoverSource.value === "incoming") {
    return (
      "이미 이월된 제품을 다시 이월합니다.\n" +
      "최초 생산일은 그대로 유지됩니다.\n\n" +
      `이월 수량을 ${quantity}개로 저장하시겠습니까?`
    );
  }

  // 일반 이월
  return `선택한 생산 기록의 이월 수량을 ${quantity}개로 저장하시겠습니까?`;
});

// 작성 중인 변경 사항 확인
function isDirty() {
  return (
    Number(carryoverQuantity.value) !==
      initialCarryoverQuantity.value ||
    carryoverSource.value !== initialCarryoverSource.value ||
    String(selectedCarryoverLotId.value ?? "") !==
      String(initialCarryoverLotId.value ?? "") ||
    Boolean(note.value)
  );
}

// 재고 출처 변경
function changeCarryoverSource(value) {
  carryoverSource.value = value;
  selectedCarryoverLotId.value = null;
  carryoverQuantity.value = 0;
}

// 생산 기록 변경 시 해당 기록의 기존 이월 수량 복원
function changeCarryoverLot(value) {
  if (saving.value || confirmSave.value) {
    return;
  }

  if (value == null) {
    selectedCarryoverLotId.value = null;
    carryoverQuantity.value = 0;
    return;
  }

  const sourceType =
    carryoverSource.value === "incoming"
      ? "carryover"
      : "today";

  const selected = (props.product?.stock_sources || []).find(
    (source) =>
      source.source === sourceType &&
      source.stock_lot_id != null &&
      String(source.stock_lot_id) === String(value)
  );

  if (!selected) {
    selectedCarryoverLotId.value = null;
    carryoverQuantity.value = 0;

    emit(
      "error",
      "선택한 생산 기록을 찾을 수 없습니다. 다시 선택해 주세요."
    );
    return;
  }

  const existingQuantity = Number(
    selected.carryover_out_quantity ?? 0
  );

  selectedCarryoverLotId.value = selected.stock_lot_id;

  carryoverQuantity.value =
    Number.isSafeInteger(existingQuantity) &&
    existingQuantity >= 0
      ? existingQuantity
      : 0;
}

// 기록 확인에서 특정 이월 내역을 선택하여 수정
function editCarryoverLot(item) {
  if (!props.canMutate || saving.value || !item) {
    return;
  }

  if (item.stock_lot_id == null) {
    emit("error", "생산 기록이 연결되지 않은 이월 내역입니다.");
    return;
  }

  const openSelectedLot = () => {
    carryoverSource.value =
      item.source === "carryover" ? "incoming" : "today";

    selectedCarryoverLotId.value = item.stock_lot_id;

    carryoverQuantity.value = Number(
      item.carryover_out_quantity || 0
    );

    note.value = "";

    // 선택한 기존 기록을 수정 기준값으로 설정합니다.
    initialCarryoverSource.value = carryoverSource.value;
    initialCarryoverLotId.value = selectedCarryoverLotId.value;
    initialCarryoverQuantity.value = carryoverQuantity.value;

    historyOpen.value = false;
    editorOpen.value = true;
  };

  // 기존 입력 중 변경 사항이 있으면 먼저 취소 확인
  if (editorOpen.value) {
    requestLeave(openSelectedLot);
    return;
  }

  openSelectedLot();
}

// 추가/수정 입력창 열기·닫기
function toggleEditor() {
  if (saving.value) {
    return;
  }

  // 입력창을 닫을 때 미저장 내용 확인
  if (editorOpen.value) {
    requestLeave(() => {
      editorOpen.value = false;
    });
    return;
  }

  if (!props.canMutate) {
    emit("error", "현재 이월 기록을 수정할 수 없습니다.");
    return;
  }

  // 기록 확인창은 닫고 입력창을 엽니다.
  historyOpen.value = false;
  editorOpen.value = true;
}

// 이월 기록 확인 화면 열기·닫기
function toggleHistory() {
  // 저장 중이거나 다른 확인창이 열려 있으면 중복 동작 방지
  if (
    saving.value ||
    confirmSave.value ||
    confirmDiscard.value
  ) {
    return;
  }

  const nextOpen = !historyOpen.value;

  // 입력창이 열려 있으면 먼저 작성 내용 확인
  if (nextOpen && editorOpen.value) {
    requestLeave(() => {
      editorOpen.value = false;
      historyOpen.value = true;
    });

    return;
  }

  historyOpen.value = nextOpen;
}

// 이월 저장 요청에 사용할 입력값을 공통으로 검사합니다.
// 문제가 없으면 null, 문제가 있으면 오류 문구를 반환합니다.
function validateCarryoverInput() {
  const rawQuantity = carryoverQuantity.value;
  const lotId = selectedCarryoverLotId.value;

  // 입력하지 않은 값과 실제 0개 입력을 구분합니다.
  if (
    rawQuantity === null ||
    rawQuantity === undefined ||
    String(rawQuantity).trim() === ""
  ) {
    return "이월 수량을 입력해 주세요. 이월이 없다면 0을 입력해 주세요.";
  }

  const quantity = Number(rawQuantity);

  if (!Number.isSafeInteger(quantity) || quantity < 0) {
    return "이월 수량은 0 이상의 정수로 입력해 주세요.";
  }

  if (String(note.value || "").length > 1000) {
    return "메모는 1,000자 이내로 입력해 주세요.";
  }

  if (!props.product?.id || !props.storeId || !props.workDate) {
    return "저장할 제품과 날짜를 확인해 주세요.";
  }

  const existingLots = savedCarryoverLots.value;

  // 생산 기록 선택 없이 이월 없음(0개) 확인
  if (lotId == null) {
    if (quantity > 0) {
      return "이월할 생산 기록을 선택해 주세요.";
    }

    if (existingLots.length > 0) {
      return "기존 이월을 0개로 수정하려면 해당 생산 기록을 선택해 주세요.";
    }

    return null;
  }

  // 선택한 생산 기록이 실제 재고 출처 목록에 있는지 확인
  const selected = selectedCarryoverLot.value;

  if (!selected) {
    return "선택한 생산 기록을 찾을 수 없습니다. 다시 선택해 주세요.";
  }

  // 기존 이월을 0개로 취소하는 경우에는
  // 실제 이월 처리된 생산 기록인지 확인
  if (quantity === 0 && existingLots.length > 0) {
    const isExisting = existingLots.some(
      (item) =>
        item.source === selected.source &&
        String(item.stock_lot_id) === String(lotId)
    );

    if (!isExisting) {
      return "0개로 수정할 기존 이월 기록을 선택해 주세요.";
    }
  }

  // 현재 선택 기록의 재고 부족 여부
  if (
    !Number.isFinite(carryoverSourceAvailable.value) ||
    quantity > carryoverSourceAvailable.value
  ) {
    return "선택한 생산 기록의 이월 가능 수량이 부족합니다.";
  }

  return null;
}

// 이월 저장 확인창 열기
function askSave() {
  if (
    !props.canMutate ||
    saving.value ||
    !editorOpen.value ||
    confirmSave.value ||
    confirmDiscard.value
  ) {
    return;
  }

  const errorMessage = validateCarryoverInput();

  if (errorMessage) {
    emit("error", errorMessage);
    return;
  }

  confirmSave.value = true;
}

// 이월 저장 실행
async function save() {
  if (
    !props.canMutate ||
    saving.value ||
    !editorOpen.value ||
    !confirmSave.value
  ) {
    return;
  }

  // 실제 저장 직전에도 동일한 검증 수행
  const errorMessage = validateCarryoverInput();

  if (errorMessage) {
    confirmSave.value = false;
    emit("error", errorMessage);
    return;
  }

  saving.value = true;

  try {
    const response = await window.axios.put(
      "/tillwhite/api/production-management/flow",
      {
        store_id: props.storeId,
        product_id: props.product.id,
        work_date: props.workDate,
        type: "carryover",

        quantity: Number(carryoverQuantity.value),
        carryover_source: carryoverSource.value,
        stock_lot_id: selectedCarryoverLotId.value,

        note: note.value || null,
      }
    );

    // 저장 성공
    confirmSave.value = false;
    confirmDiscard.value = false;
    pendingDiscardAction.value = null;

    // 입력값 및 미저장 변경 감지 상태 정리
    carryoverQuantity.value = 0;
    carryoverSource.value = "today";
    selectedCarryoverLotId.value = null;
    note.value = "";

    initialCarryoverQuantity.value = 0;
    initialCarryoverSource.value = "today";
    initialCarryoverLotId.value = null;

    editorOpen.value = false;
    historyOpen.value = false;

    // 최신 서버 데이터는 기존 부모의 saved 이벤트로 전달
    emit(
      "saved",
      response.data?.message || "이월을 저장했습니다.",
      response.data?.daily || null
    );

    emit("update:modelValue", false);
  } catch (error) {
    // 저장 실패 시 입력값 유지
    confirmSave.value = false;

    emit(
      "error",
      error.response?.data?.message ||
        error.message ||
        "이월을 저장하지 못했습니다."
    );
  } finally {
    saving.value = false;
  }
}

// 이월 탭을 새로 열 때 입력·확인창 상태 초기화
function resetCarryoverState() {
  const sources = props.product?.stock_sources || [];

  const existingLots = sources.filter(
    (source) =>
      source.stock_lot_id != null &&
      Number(source.carryover_out_quantity || 0) > 0
  );

  // 기존 이월 기록이 정확히 한 건일 때만 자동 선택
  const selected =
    existingLots.length === 1 ? existingLots[0] : null;

  const source =
    selected?.source === "carryover"
      ? "incoming"
      : "today";

  const lotId = selected?.stock_lot_id ?? null;

  const quantity = selected
    ? Number(selected.carryover_out_quantity || 0)
    : 0;

  // 입력값 복원
  carryoverSource.value = source;
  selectedCarryoverLotId.value = lotId;
  carryoverQuantity.value = quantity;
  note.value = "";

  // 미저장 변경 감지의 기준값 갱신
  initialCarryoverSource.value = source;
  initialCarryoverLotId.value = lotId;
  initialCarryoverQuantity.value = quantity;

  // 화면 펼침 상태 초기화
  editorOpen.value = false;
  historyOpen.value = false;

  // 이전 확인창 및 보류 작업 제거
  confirmSave.value = false;
  confirmDiscard.value = false;
  pendingDiscardAction.value = null;
}

// 다이얼로그를 열거나 정상적으로 닫았을 때 상태 초기화
watch(
  () => props.modelValue,
  (isOpen) => {
    // 저장 중에는 입력 상태를 변경하지 않습니다.
    if (saving.value) {
      return;
    }

    if (isOpen) {
      resetCarryoverState();
      return;
    }

    // 닫힌 뒤 이전 입력 상태를 남기지 않습니다.
    resetCarryoverState();
  },
  { immediate: true }
);

// 부모 닫기·탭 이동·입력창 닫기 요청 처리
function requestLeave(action) {
  // 저장 중에는 다른 작업을 허용하지 않습니다.
  if (saving.value) {
    return false;
  }

  // 다른 확인창이 열려 있으면 중복 실행 방지
  if (confirmSave.value || confirmDiscard.value) {
    return false;
  }

  // 미저장 변경 사항 확인
  if (editorOpen.value && isDirty()) {
    pendingDiscardAction.value =
      typeof action === "function" ? action : null;

    confirmDiscard.value = true;

    return false;
  }

  // 변경 사항이 없으면 안전하게 이동
  pendingDiscardAction.value = null;

  if (typeof action === "function") {
    action();
  }

  return true;
}

// 작성 취소 확인창을 닫을 때 보류 중인 이동 요청 정리
function handleDiscardDialogChange(isOpen) {
  confirmDiscard.value = isOpen;

  // 사용자가 확인창에서 취소했을 때
  // 작성 중인 입력값은 그대로 유지합니다.
  if (!isOpen) {
    pendingDiscardAction.value = null;
  }
}

// 미저장 내용 취소 확인 후 이전 상태 복원
function confirmDiscardChanges() {
  if (saving.value) {
    return;
  }

  // 확인창에서 실행 예정이었던 작업
  const action = pendingDiscardAction.value;

  // 확인창과 보류 작업 초기화
  pendingDiscardAction.value = null;
  confirmDiscard.value = false;
  confirmSave.value = false;

  // 마지막으로 저장된 기준값 복원
  carryoverSource.value = initialCarryoverSource.value;
  selectedCarryoverLotId.value = initialCarryoverLotId.value;
  carryoverQuantity.value = initialCarryoverQuantity.value;
  note.value = "";

  // 이전에 요청한 작업 실행
  if (typeof action === "function") {
    action();
  }
}

// 부모 컴포넌트에서 사용할 기능 공개
const isSaving = computed(() => saving.value);

defineExpose({
  requestLeave,
  isSaving,
  editorOpen,
  askSave,
});
</script>

<style scoped>
/* 생산 탭과 동일한 기본 글씨 */
.inventory-dialog__carryover-tab {
  font-size: 12px;
}

.inventory-dialog__carryover-tab :deep(.v-btn),
.inventory-dialog__carryover-tab :deep(.v-field),
.inventory-dialog__carryover-tab :deep(.v-label) {
  font-size: 12px;
}

/* 생산 탭과 동일한 관리 카드 */
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

/* 이월 재고 및 이월 예정 */
.inventory-dialog__carryover-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.inventory-dialog__carryover-summary > div {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.inventory-dialog__carryover-summary span {
  font-size: 12px;
  opacity: 0.7;
}

.inventory-dialog__carryover-summary strong {
  font-size: 15px;
  font-weight: 600;
}

/* 안내 문구 */
.inventory-dialog__form-help {
  margin: 0;
  font-size: 12px;
  line-height: 1.6;
  opacity: 0.75;
}

@media (max-width: 600px) {
  .inventory-dialog__placeholder {
    padding: 14px;
  }
}

/* 생산 탭과 동일한 작업 버튼 */
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

/* 생산 탭과 동일한 입력창 제목 */
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

/* 입력 및 기록 확인 영역 */
.inventory-dialog__inner-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
}

/* 기존 이월 기록 한 줄 */
.inventory-dialog__detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  font-size: 12px;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.inventory-dialog__detail-row:last-child {
  border-bottom: none;
}

.inventory-dialog__detail-row strong {
  text-align: right;
}

/* 기존 이월 기록 선택 버튼 */
.inventory-dialog__history-button {
  width: 100%;
  border-top: none;
  border-right: none;
  border-left: none;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
  font: inherit;
}

.inventory-dialog__history-button:hover:not(:disabled) {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.inventory-dialog__history-button:disabled {
  cursor: default;
  opacity: 1;
}

.inventory-dialog__history-button:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

@media (max-width: 600px) {
  .inventory-dialog__inner-card {
    padding: 12px;
  }
}

/* 선택한 생산 기록의 재고 안내 */
.inventory-dialog__selected-stock {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 4px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
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
  border-top: 1px solid
    rgba(var(--v-theme-on-surface), 0.08);
}

.inventory-dialog__selected-stock-row span {
  color: rgba(var(--v-theme-on-surface), 0.68);
}

.inventory-dialog__selected-stock-row strong {
  font-weight: 600;
  text-align: right;
}

/* 이월 관리 제목 및 확인 상태 */
.inventory-dialog__management-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}

.inventory-dialog__management-heading > strong {
  font-size: 14px;
  font-weight: 600;
}

/* 기본: 미확인 */
.inventory-dialog__status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 9px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.06);
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 11px;
  font-weight: 600;
}

/* 확인 완료 */
.inventory-dialog__status--confirmed {
  background: rgba(var(--v-theme-success), 0.1);
  color: rgb(var(--v-theme-success));
}

/* 모바일에서도 작업 버튼 2개 유지 */
@media (max-width: 600px) {
  .inventory-dialog__action-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  .inventory-dialog__action-grid :deep(.v-btn) {
    min-height: 44px;
    font-size: 12px;
  }

  .inventory-dialog__selected-stock-row {
    align-items: flex-start;
  }

  .inventory-dialog__selected-stock-row strong {
    overflow-wrap: anywhere;
  }
}
</style>