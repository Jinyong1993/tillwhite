<template>
  <div class="inventory-dialog inventory-dialog__carryover-tab">
    <!-- 이월 관리 -->
    <div class="inventory-dialog__placeholder d-flex flex-column ga-3">
      <strong>이월 관리</strong>

      <p class="inventory-dialog__form-help">
        생산 기록을 선택해 다음 영업일로 넘길 수량을 관리합니다.
        기존 기록은 아래 이월 기록 목록에서 수정할 수 있습니다.
      </p>

      <!-- 이월 업무 선택 버튼 -->
      <div class="inventory-dialog__action-grid">
        <v-btn
          block
          variant="outlined"
          :color="editorOpen ? undefined : 'success'"
          :disabled="saving || !canMutate"
          :prepend-icon="editorOpen ? 'mdi-chevron-up' : undefined"
          @click="toggleEditor"
        >
          {{ editorOpen ? "취소" : "이월 수량 설정" }}
        </v-btn>

        <v-btn
          block
          variant="outlined"
          :disabled="saving || !canMutate"
          :prepend-icon="zeroCardOpen ? 'mdi-chevron-up' : undefined"
          @click="toggleZeroCard"
        >
          이월 없음
        </v-btn>
      </div>

      <!-- 이월 입력 화면 -->
      <div
        v-if="editorOpen"
        class="inventory-dialog__production-form d-flex flex-column ga-3"
      >
        <div class="inventory-dialog__field-heading d-flex align-center justify-space-between flex-wrap">
          <strong>이월 수량 설정 / 수정</strong>
          <span>
            현재 이월 {{ displayQuantity(product?.carryover_out) }}
          </span>
        </div>

        <!-- 이월 입력 카드 탐색 -->
        <div class="inventory-dialog__card-navigation">
          <div class="inventory-dialog__card-steps">
            <v-btn
              v-for="(title, index) in carryoverCardTitles"
              :key="index"
              size="small"
              :variant="carryoverCardIndex === index ? 'flat' : 'outlined'"
              :color="carryoverCardIndex === index ? 'primary' : undefined"
              @click="carryoverCardIndex = index"
            >
              {{ title }}
            </v-btn>
          </div>

          <div class="inventory-dialog__card-controls d-flex align-center justify-space-between">
            <v-btn
              size="small"
              variant="text"
              prepend-icon="mdi-chevron-left"
              :disabled="carryoverCardIndex === 0"
              @click="carryoverCardIndex--"
            >
              이전
            </v-btn>

            <span>
              {{ carryoverCardIndex + 1 }}
              /
              {{ carryoverCardTitles.length }}
            </span>

            <v-btn
              size="small"
              variant="text"
              append-icon="mdi-chevron-right"
              :disabled="carryoverCardIndex === lastCarryoverCardIndex"
              @click="carryoverCardIndex++"
            >
              다음
            </v-btn>
          </div>
        </div>

        <!--
          카드 전환은 단계 버튼과 이전/다음 버튼으로 수행.
          모바일 입력·세로 스크롤과의 충돌을 방지하기 위해
          포인터 스와이프 이벤트는 연결하지 않습니다.
        -->
        <div class="inventory-dialog__swipe-area">
          <!-- 카드 1: 재고 선택 -->
          <div
            v-show="carryoverCardIndex === 0"
            class="inventory-dialog__swipe-card d-flex flex-column ga-3"
          >
            <div class="inventory-dialog__field-heading">
              <strong>재고 출처</strong>
            </div>

            <!-- 출처 선택: 기존 선택 상태 표시 유지 -->
            <div class="inventory-dialog__action-grid">
              <v-btn
                block
                :variant="carryoverSource === 'today' ? 'tonal' : 'outlined'"
                @click="changeCarryoverSource('today')"
              >
                당일 생산분
              </v-btn>

              <v-btn
                block
                :variant="carryoverSource === 'incoming' ? 'tonal' : 'outlined'"
                @click="changeCarryoverSource('incoming')"
              >
                기존 이월분
              </v-btn>
            </div>

            <div class="inventory-dialog__field-heading d-flex align-center justify-space-between">
              <strong>이월할 재고 선택</strong>
              <span>{{ carryoverLotOptions.length }}건</span>
            </div>

            <!-- 선택 가능한 재고 기록 -->
            <div v-if="carryoverLotOptions.length">
              <div
                v-for="option in carryoverLotOptions"
                :key="option.value"
                class="inventory-dialog__production-record"
              >
                <button
                  type="button"
                  class="inventory-dialog__production-record-header"
                  :class="{
                    'inventory-dialog__production-record-header--selected':
                      String(selectedCarryoverLotId) === String(option.value)
                  }"
                  :aria-pressed="
                    String(selectedCarryoverLotId) === String(option.value)
                  "
                  @click="changeCarryoverLot(option.value)"
                >
                  <div class="inventory-dialog__carryover-record-content">
                    <strong class="inventory-dialog__production-record-label">
                      {{ option.recordName }}
                    </strong>

                    <span class="inventory-dialog__carryover-record-date">
                      최초 생산일 {{ option.productionDate }}
                    </span>

                    <div class="inventory-dialog__carryover-record-quantity">
                      <span>
                        이월 처리
                        <strong>
                          {{
                            option.processedQuantity === null
                              ? "미확인"
                              : `${option.processedQuantity}개`
                          }}
                        </strong>
                      </span>

                      <span class="inventory-dialog__carryover-record-divider">
                        ·
                      </span>

                      <span>
                        이월 가능
                        <strong>
                          {{
                            option.availableQuantity === null
                              ? "미확인"
                              : `${option.availableQuantity}개`
                          }}
                        </strong>
                      </span>
                    </div>
                  </div>

                  <!-- 화살표만 유지, 선택 강조는 기존 CSS 이용 -->
                  <v-icon
                    icon="mdi-chevron-right"
                    size="20"
                  />
                </button>
              </div>
            </div>

            <p v-else class="inventory-dialog__form-help">
              선택할 수 있는 재고 기록이 없습니다.
            </p>

            <p
              v-if="selectedCarryoverLotId == null"
              class="inventory-dialog__form-help"
            >
              이월할 생산 기록을 선택해 주세요.
              이월할 재고가 없는 경우에는 상단의 '이월 없음' 버튼을 이용해 주세요.
            </p>
          </div>

          <!-- 카드 2: 이월 수량 입력 -->
          <div
            v-show="carryoverCardIndex === 1"
            class="inventory-dialog__swipe-card d-flex flex-column ga-3"
            :style="!selectedCarryoverLot ? { minHeight: 'auto' } : {}"
          >
            <v-alert
              v-if="!selectedCarryoverLot"
              type="info"
              variant="tonal"
              density="compact"
            >
              먼저 재고 선택 카드에서 이월할 생산 기록을 선택해 주세요.
            </v-alert>

            <!-- 선택한 재고 정보 -->
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
                <span>설정 가능한 최대 수량</span>
                <strong>
                  {{ displayQuantity(carryoverSourceAvailable) }}
                </strong>
              </div>
            </div>

            <template v-if="selectedCarryoverLot">
              <p class="inventory-dialog__form-help">
                입력한 수량이 해당 생산 기록의 최종 이월 수량으로 저장됩니다.
                기존 이월 수량에 추가로 합산되지 않습니다.
              </p>

              <!-- 이월 수량 -->
              <v-number-input
                v-model="carryoverQuantity"
                class="app-number-input"
                inputmode="numeric"
                label="이월 수량"
                variant="outlined"
                density="compact"
                hide-details
                :min="0"
                :max="carryoverSourceAvailable"
                control-variant="split"
                :disabled="selectedCarryoverLotId == null"
              />

              <!-- 기존 기록을 0개로 수정할 때 -->
              <v-alert
                v-if="
                  selectedCarryoverLotId != null &&
                  selectedCarryoverLot &&
                  Number(selectedCarryoverLot.carryover_out_quantity || 0) > 0 &&
                  carryoverQuantity !== null &&
                  carryoverQuantity !== undefined &&
                  String(carryoverQuantity).trim() !== '' &&
                  Number(carryoverQuantity) === 0
                "
                type="info"
                variant="tonal"
                density="compact"
              >
                현재 0개가 입력되어 있습니다.
                저장하면 선택한 생산 기록의 기존 이월 처리가 취소됩니다.
              </v-alert>

              <!-- 재고 초과 -->
              <v-alert
                v-if="quantityExceeded"
                type="error"
                variant="tonal"
                density="compact"
              >
                선택한 생산 기록의 이월 가능 수량을 초과했습니다.
              </v-alert>

              <!-- 재이월 -->
              <v-alert
                v-if="
                  carryoverQuantity > 0 &&
                  carryoverSource === 'incoming'
                "
                type="warning"
                variant="tonal"
                density="compact"
              >
                이미 이월된 제품을 다시 이월합니다.
                원래 생산일은 유지됩니다.
              </v-alert>
            </template>

            <v-btn
              block
              variant="outlined"
              :disabled="saving || !canMutate"
              @click="carryoverCardIndex = 0"
            >
              다른 재고 선택
            </v-btn>
          </div>

          <!-- 카드 3: 메모 -->
          <div
            v-show="carryoverCardIndex === 2"
            class="inventory-dialog__swipe-card d-flex flex-column ga-3"
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
              maxlength="1000"
              counter="1000"
              hide-details="auto"
              placeholder="특이사항이나 다음 근무자에게 전달할 내용을 입력하세요."
            />
          </div>
        </div>
      </div>

      <!-- 이월 없음 확인 -->
      <div
        v-if="zeroCardOpen"
        class="inventory-dialog__inner-card d-flex flex-column ga-3"
      >
        <strong>이월 없음 확인</strong>

        <p class="inventory-dialog__form-help">
          다음 영업일로 넘길 재고가 없다면 0개로 확인합니다.
          이미 등록한 이월 기록은 아래 목록에서 개별 수정해야 합니다.
        </p>

        <v-btn
          block
          variant="outlined"
          :disabled="saving || !canMutate || hasSavedCarryover"
          @click="askZeroSave"
        >
          이월 0개 확인
        </v-btn>
      </div>

      <!-- 이월 기록 목록 -->
      <section class="inventory-dialog__production">
        <div class="inventory-dialog__production-summary d-flex align-center justify-space-between">
          <strong>이월 합계</strong>
          <strong>
            {{ displayQuantity(product?.carryover_out) }}
          </strong>
        </div>

        <div class="inventory-dialog__production-heading d-flex align-center justify-space-between">
          <strong>이월 기록</strong>
          <strong>{{ savedCarryoverLots.length }}건</strong>
        </div>

        <template v-if="savedCarryoverLots.length">
          <div
            v-for="item in savedCarryoverLots"
            :key="`${item.source}-${item.stock_lot_id}`"
            class="inventory-dialog__production-record"
          >
            <!-- 이월 기록 펼침/접기 -->
            <button
              type="button"
              class="inventory-dialog__production-record-header"
              :aria-expanded="
                expandedCarryoverKeys.includes(carryoverKey(item))
              "
              @click="toggleCarryoverRecord(item)"
            >
              <strong class="inventory-dialog__production-record-label">
                이월 수량
              </strong>

              <span class="inventory-dialog__production-record-header-right d-flex align-center">
                <strong class="inventory-dialog__production-record-quantity">
                  {{ displayQuantity(item.carryover_out_quantity) }}
                </strong>

                <v-icon
                  :icon="
                    expandedCarryoverKeys.includes(carryoverKey(item))
                      ? 'mdi-chevron-up'
                      : 'mdi-chevron-down'
                  "
                  size="20"
                />
              </span>
            </button>

            <div
              v-if="expandedCarryoverKeys.includes(carryoverKey(item))"
            >
              <div class="inventory-dialog__record-detail">
                <strong>최초 생산일</strong>
                <div>
                  {{ item.origin_production_date || "생산일 미확인" }}
                </div>
              </div>

              <div class="inventory-dialog__record-detail">
                <strong>재고 출처</strong>
                <div>
                  {{
                    item.source === "carryover"
                      ? "기존 이월분 · 재이월"
                      : "당일 생산분"
                  }}
                </div>
              </div>

              <!-- 수정은 primary, 삭제는 error -->
              <div class="inventory-dialog__action-grid">
                <v-btn
                  block
                  variant="outlined"
                  color="primary"
                  :disabled="!canMutate || saving"
                  @click="editCarryoverLot(item)"
                >
                  수정
                </v-btn>

                <v-btn
                  block
                  variant="outlined"
                  color="error"
                  :disabled="!canMutate || saving"
                  @click="askDeleteCarryover(item)"
                >
                  삭제
                </v-btn>
              </div>
            </div>
          </div>
        </template>

        <div
          v-else
          class="inventory-dialog__production-empty"
        >
          이월 기록이 없습니다.
        </div>
      </section>
    </div>
  </div>

  <!-- 기존 이월 저장 확인창 유지 -->
  <ConfirmDialog
    v-model="confirmSave"
    title="이월 저장"
    :message="confirmMessage"
    confirm-text="저장"
    cancel-text="취소"
    confirm-action="save"
    :loading="saving"
    @confirm="save"
  />

  <!-- 미저장 내용 보호 확인창 유지 -->
  <ConfirmDialog
    v-model="confirmDiscard"
    title="작성 취소"
    message="작성 중인 내용을 삭제하고 입력을 종료하시겠습니까?"
    confirm-text="작성 취소"
    cancel-text="계속 작성"
    confirm-action="close"
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

// 이월 0개 확인 관련
const zeroCardOpen = ref(false);

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

// 생산 탭과 동일한 3단계 입력 카드 탐색
const carryoverCardIndex = ref(0);
const carryoverCardTitles = [
  "재고 선택",
  "이월 수량",
  "메모",
];

// 마지막 카드 인덱스
const lastCarryoverCardIndex = carryoverCardTitles.length - 1;

// 카드 이동은 템플릿의 단계 버튼 및 이전/다음 버튼에서 처리합니다.

// 최초 입력 상태
const initialCarryoverSource = ref("today");
const initialCarryoverLotId = ref(null);
const initialCarryoverQuantity = ref(0);

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
        remainingRaw === null ||
        remainingRaw === undefined ||
        remainingRaw === ""
          ? null
          : Number(remainingRaw);

      const existing =
        Number(source.carryover_out_quantity ?? 0);

      // 기존 이월 처리 수량
      const processedQuantity =
        Number.isSafeInteger(existing) && existing >= 0
          ? existing
          : null;

      // 추가 이월 가능 수량
      // 잘못된 재고값을 임의로 0개로 표시하지 않음
      const availableQuantity =
        remaining !== null &&
        Number.isSafeInteger(remaining) &&
        remaining >= 0
          ? remaining
          : null;

      const recordType =
        isIncoming ? "이월 기록" : "생산 기록";

      return {
        value: source.stock_lot_id,
        recordName: `${recordType} ${sequence}`,
        productionDate,
        processedQuantity,
        availableQuantity,
      };
    });
});

// 펼쳐진 이월 기록 카드의 고유 키 목록
const expandedCarryoverKeys = ref([]);

// 이월 기록의 재고 출처와 생산 기록 ID를 결합한 고유 키
function carryoverKey(item) {
  return `${item.source}-${item.stock_lot_id}`;
}

// 생산 탭과 동일하게 이월 기록 카드 펼침·접힘
function toggleCarryoverRecord(item) {
  const key = carryoverKey(item);

  if (expandedCarryoverKeys.value.includes(key)) {
    expandedCarryoverKeys.value = expandedCarryoverKeys.value.filter(
      (value) => value !== key
    );
    return;
  }

  expandedCarryoverKeys.value.push(key);
}

// 기존 이월 기록 삭제 요청
// 별도 삭제 API 대신 기존 이월 수량을 0개로 수정합니다.
function askDeleteCarryover(item) {
  if (
    !props.canMutate ||
    saving.value ||
    confirmSave.value ||
    confirmDiscard.value ||
    !item ||
    item.stock_lot_id == null
  ) {
    return;
  }

  // 삭제할 기록을 선택하고 이월 수량을 0개로 설정
  const prepareDelete = () => {
    // 기존 입력창을 닫고 삭제 대상 기록을 엽니다.
    editorOpen.value = false;

    editCarryoverLot(item);

    // 삭제 대상이 정상적으로 선택되었는지 확인
    const expectedSource =
      item.source === "carryover" ? "incoming" : "today";

    if (
      !editorOpen.value ||
      carryoverSource.value !== expectedSource ||
      String(selectedCarryoverLotId.value) !==
        String(item.stock_lot_id)
    ) {
      return;
    }

    // 삭제 대상 기록의 이월 수량을 0개로 변경
    carryoverQuantity.value = 0;

    // 기존 저장 확인창 표시
    askSave();
  };

  // 다른 기록을 작성 중이라면 먼저 작성 취소 확인
  if (editorOpen.value) {
    requestLeave(prepareDelete);
    return;
  }

  // 작성 중인 내용이 없다면 바로 삭제 확인
  prepareDelete();
}

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

  if (
    remainingRaw === null ||
    remainingRaw === undefined ||
    remainingRaw === ""
  ) {
    return 0;
  }

  const remaining = Number(remainingRaw);
  const existing = Number(
    source.carryover_out_quantity ?? 0
  );

  // 잘못된 재고 데이터는 사용 가능한 수량으로 인정하지 않습니다.
  if (
    !Number.isSafeInteger(remaining) ||
    remaining < 0 ||
    !Number.isSafeInteger(existing) ||
    existing < 0
  ) {
    return 0;
  }

  const maximumQuantity = remaining + existing;

  if (!Number.isSafeInteger(maximumQuantity)) {
    return 0;
  }

  return maximumQuantity;
});

// 선택한 생산 기록의 이월 가능 수량 초과 여부
const quantityExceeded = computed(() => {
  return (
    Number(carryoverQuantity.value || 0) >
    carryoverSourceAvailable.value
  );
});

// 기존 이월 기록 보유 여부
const hasSavedCarryover = computed(() => {
  return savedCarryoverLots.value.length > 0;
});

// 이월 저장 전 최종 확인 문구

const confirmMessage = computed(() => {
  if (zeroCardOpen.value) {
    return (
      "다음 영업일로 이월할 재고가 없습니다.\n\n" +
      "이월 없음(0개)으로 확인하시겠습니까?"
    );
  }

  const quantity = Number(carryoverQuantity.value);
  const existing = Number(
    selectedCarryoverLot.value?.carryover_out_quantity ?? 0
  );

  if (quantity === 0) {
    return (
      `현재 이월 ${existing}개를 0개로 변경하시겠습니까?\n\n` +
      "해당 생산 기록의 기존 이월 처리가 취소됩니다."
    );
  }

  const sourceMessage =
    carryoverSource.value === "incoming"
      ? "기존 이월분을 다시 이월합니다.\n최초 생산일은 유지됩니다.\n\n"
      : "";

  return (
    sourceMessage +
    `현재 이월 ${existing}개 → 변경 후 ${quantity}개\n\n` +
    "입력한 수량이 최종 이월 수량으로 저장됩니다.\n" +
    "기존 수량에 추가로 합산되지 않습니다.\n\n" +
    "저장하시겠습니까?"
  );
});


// 작성 중인 변경 사항 확인
function isDirty() {
  const rawQuantity = carryoverQuantity.value;

  const quantityChanged =
    rawQuantity === null ||
    rawQuantity === undefined ||
    String(rawQuantity).trim() === "" ||
    Number(rawQuantity) !== initialCarryoverQuantity.value;

  return (
    quantityChanged ||
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

  // 출처 변경 시 재고 선택 카드 유지
  carryoverCardIndex.value = 0;
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

  // 재고 선택 완료 후 이월 수량 카드로 이동
  carryoverCardIndex.value = 1;
}

// 기록 확인에서 특정 이월 내역을 선택하여 수정
function editCarryoverLot(item) {
  // 수정 권한, 저장 상태, 선택 기록 확인
  if (!props.canMutate || saving.value || !item) {
    return;
  }

  // 다른 확인창이 열려 있을 때 중복 동작 방지
  if (confirmSave.value || confirmDiscard.value) {
    return;
  }

  if (item.stock_lot_id == null) {
    emit("error", "생산 기록이 연결되지 않은 이월 내역입니다.");
    return;
  }

  const openSelectedLot = () => {
    // 기존 이월 기록의 재고 출처 복원
    carryoverSource.value =
      item.source === "carryover" ? "incoming" : "today";

    // 수정할 생산 기록과 기존 이월 수량 복원
    selectedCarryoverLotId.value = item.stock_lot_id;

    carryoverQuantity.value = Number(
      item.carryover_out_quantity || 0
    );

    note.value = "";

    // 선택한 기존 기록을 수정 기준값으로 설정합니다.
    initialCarryoverSource.value = carryoverSource.value;
    initialCarryoverLotId.value = selectedCarryoverLotId.value;
    initialCarryoverQuantity.value = carryoverQuantity.value;

    // 다른 화면을 닫고 기존 기록 수정 입력창 표시
    zeroCardOpen.value = false;

    // 기존 기록 수정 시 두 번째 이월 수량 카드 표시
    carryoverCardIndex.value = 1;

    editorOpen.value = true;
  };

  // 기존 입력 중 변경 사항이 있으면 먼저 취소 확인
  if (editorOpen.value) {
    requestLeave(openSelectedLot);
    return;
  }

  openSelectedLot();
}

// 이월 추가 입력창 열기·닫기
function toggleEditor() {
  // 저장 중이거나 확인창이 열려 있으면 중복 동작 방지
  if (
    saving.value ||
    confirmSave.value ||
    confirmDiscard.value
  ) {
    return;
  }

  // 입력창을 닫을 때 미저장 내용 확인
  if (editorOpen.value) {
    requestLeave(() => {
      editorOpen.value = false;
    });
    return;
  }

  // 수정 권한 확인
  if (!props.canMutate) {
    emit("error", "현재 이월 기록을 수정할 수 없습니다.");
    return;
  }

  // 신규 추가를 위한 입력값 초기화
  carryoverSource.value = "today";
  selectedCarryoverLotId.value = null;
  carryoverQuantity.value = 0;
  note.value = "";

  // 미저장 변경 감지 기준값 초기화
  initialCarryoverSource.value = "today";
  initialCarryoverLotId.value = null;
  initialCarryoverQuantity.value = 0;

  // 다른 입력 화면 닫기
  zeroCardOpen.value = false;

  // 첫 번째 재고 선택 카드부터 시작
  carryoverCardIndex.value = 0;

  editorOpen.value = true;
}

// 이월 없음 확인 카드 열기·닫기
function toggleZeroCard() {
  // 저장 중이거나 확인창이 열려 있으면 중복 동작 방지
  if (
    saving.value ||
    confirmSave.value ||
    confirmDiscard.value
  ) {
    return;
  }

  // 수정 권한이 없으면 이월 없음 카드를 열지 않습니다.
  if (!props.canMutate) {
    emit("error", "현재 이월 기록을 수정할 수 없습니다.");
    return;
  }

  const nextOpen = !zeroCardOpen.value;

  // 이월 없음 카드가 열릴 때 다른 입력 영역을 닫습니다.
  const changeView = () => {
    editorOpen.value = false;
    zeroCardOpen.value = nextOpen;
  };

  // 기존 입력 중 변경 사항이 있으면 먼저 작성 취소 확인
  if (editorOpen.value) {
    requestLeave(changeView);
    return;
  }

  changeView();
}

// 일반 이월 저장 요청에 사용할 입력값을 검사합니다.
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
    return "이월 수량을 입력해 주세요.";
  }

  const quantity = Number(rawQuantity);

  // 이월 수량은 0 이상의 정수만 허용합니다.
  if (!Number.isSafeInteger(quantity) || quantity < 0) {
    return "이월 수량은 0 이상의 정수로 입력해 주세요.";
  }

  // 메모 길이 확인
  if (String(note.value || "").length > 1000) {
    return "메모는 1,000자 이내로 입력해 주세요.";
  }

  // 저장에 필요한 기본 정보 확인
  if (!props.product?.id || !props.storeId || !props.workDate) {
    return "저장할 제품과 날짜를 확인해 주세요.";
  }

  // 일반 추가에서는 생산 기록 선택이 필수입니다.
  // 생산 기록 없이 0개 확인하는 기능은 '이월 없음'에서 처리합니다.
  if (lotId == null) {
    return "이월할 생산 기록을 선택해 주세요. 이월이 없다면 '이월 없음'을 이용해 주세요.";
  }

  // 선택한 생산 기록이 실제 재고 출처 목록에 있는지 확인
  const selected = selectedCarryoverLot.value;

  if (!selected) {
    return "선택한 생산 기록을 찾을 수 없습니다. 다시 선택해 주세요.";
  }

  // 0개 저장은 기존 이월 기록을 취소하는 경우에만 허용합니다.
  // 신규 생산 기록을 0개로 확인하는 기능은 별도 '이월 없음'에서 처리합니다.
  if (quantity === 0) {
    const isExisting = savedCarryoverLots.value.some(
      (item) =>
        item.source === selected.source &&
        String(item.stock_lot_id) === String(lotId)
    );

    if (!isExisting) {
      return "신규 이월 수량은 1개 이상 입력해 주세요. 이월이 없다면 '이월 없음'을 이용해 주세요.";
    }
  }

  // 현재 선택 기록의 이월 가능 수량 초과 여부
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
  // 일반 이월 입력창이 열려 있을 때만 저장 요청
  if (
    !props.canMutate ||
    saving.value ||
    !editorOpen.value ||
    confirmSave.value ||
    confirmDiscard.value
  ) {
    return;
  }

  // 입력한 생산 기록 및 이월 수량 검사
  const errorMessage = validateCarryoverInput();

  if (errorMessage) {
    emit("error", errorMessage);
    return;
  }

  confirmSave.value = true;
}

// 이월 없음(0개) 저장 확인창 열기
function askZeroSave() {
  // 이월 없음 카드가 열려 있을 때만 저장 요청
  if (
    !props.canMutate ||
    saving.value ||
    !zeroCardOpen.value ||
    confirmSave.value ||
    confirmDiscard.value
  ) {
    return;
  }

  // 기존 이월 기록을 일괄적으로 삭제하지 않도록 보호
  if (hasSavedCarryover.value) {
    emit(
      "error",
      "기존 이월 기록이 있습니다. 기록 확인에서 개별 수정해 주세요."
    );
    return;
  }

  // 저장할 제품, 점포, 날짜 확인
  if (!props.product?.id || !props.storeId || !props.workDate) {
    emit("error", "저장할 제품과 날짜를 확인해 주세요.");
    return;
  }

  // 최종 저장 확인창 표시
  confirmSave.value = true;
}

// 이월 저장 실행
async function save() {
  // 별도의 이월 없음 카드에서 저장하는지 확인
  const savingZero = zeroCardOpen.value;

  // 일반 이월 입력창 또는 이월 없음 카드가 열려 있어야 저장 가능
  if (
    !props.canMutate ||
    saving.value ||
    !(editorOpen.value || savingZero) ||
    !confirmSave.value
  ) {
    return;
  }

  // 저장 유형별 입력값 검증
  let errorMessage = null;

  if (savingZero) {
    // 기존 이월 기록이 있으면 일괄 0개 처리를 차단
    if (hasSavedCarryover.value) {
      errorMessage =
        "기존 이월 기록이 있습니다. 기록 확인에서 개별 수정해 주세요.";
    } else if (!props.product?.id || !props.storeId || !props.workDate) {
      errorMessage = "저장할 제품과 날짜를 확인해 주세요.";
    }
  } else {
    // 일반 이월 입력값 검사
    errorMessage = validateCarryoverInput();
  }

  if (errorMessage) {
    confirmSave.value = false;
    emit("error", errorMessage);
    return;
  }

  saving.value = true;

  try {
    // 이월 없음일 경우 생산 기록 없이 0개로 저장
    // 일반 이월일 경우 선택한 생산 기록과 수량으로 저장
    const response = await window.axios.put(
      "/tillwhite/api/production-management/flow",
      {
        store_id: props.storeId,
        product_id: props.product.id,
        work_date: props.workDate,
        type: "carryover",

        quantity: savingZero ? 0 : Number(carryoverQuantity.value),
        carryover_source: savingZero ? "today" : carryoverSource.value,
        stock_lot_id: savingZero ? null : selectedCarryoverLotId.value,

        note: savingZero ? null : note.value || null,
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

    // 일반 입력창, 이월 없음 카드, 기록 확인창 초기화
    editorOpen.value = false;
    zeroCardOpen.value = false;

    // 저장 완료 후 카드 탐색 초기화
    carryoverCardIndex.value = 0;

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

    const status = Number(error?.response?.status);
    const serverMessage = error?.response?.data?.message;

    let message = "이월 수량을 저장하지 못했습니다.";

    if (status === 401) {
      message = "로그인이 만료되었습니다. 다시 로그인해 주세요.";
    } else if (status === 403) {
      message = "이 작업을 수행할 권한이 없습니다.";
    } else if (status === 422) {
      message =
        typeof serverMessage === "string" && serverMessage.trim()
          ? serverMessage
          : "입력 내용을 확인해 주세요.";
    } else if (status === 409) {
      message =
        typeof serverMessage === "string" && serverMessage.trim()
          ? serverMessage
          : "다른 사용자가 데이터를 변경했습니다. 최신 내용을 확인해 주세요.";
    } else if (status >= 500) {
      message = "서버 처리 중 문제가 발생했습니다. 다시 시도해 주세요.";
    } else if (!error?.response) {
      message = "서버에 연결하지 못했습니다. 네트워크를 확인해 주세요.";
    }

    emit("error", message);
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
  zeroCardOpen.value = false;

  // 카드 탐색 상태 초기화
  carryoverCardIndex.value = 0;

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

  // 취소 후 카드 탐색 초기화
  carryoverCardIndex.value = 0;

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

/* 생산 탭과 동일한 이월 입력 폼 */
.inventory-dialog__production-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  text-align: left;
  font-size: 13px;
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
  min-height: 36px;
}

.inventory-dialog__card-controls :deep(.v-btn) {
  min-width: 76px;
}

.inventory-dialog__card-controls span {
  font-size: 12px;
  opacity: 0.7;
}

.inventory-dialog__swipe-area {
  width: 100%;
  min-width: 0;
  touch-action: pan-y;
  margin-top: 4px;
}

/* 생산 탭과 동일한 입력 카드 */
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
}

/* 카드 등장 애니메이션 제거: 모바일 화면 흔들림 방지 */

/* 모바일 입력 카드 및 탐색 */
@media (max-width: 600px) {
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
  line-height: 1.6;
}

.inventory-dialog__field-heading span {
  font-size: 12px;
  font-weight: 400;
  line-height: 1.6;
  opacity: 0.7;
  text-align: right;
}

/* 생산 탭과 동일한 입력 필드 */
.inventory-dialog__production-form :deep(.v-label) {
  font-size: 13px;
}

.inventory-dialog__production-form :deep(.v-field__input) {
  font-size: 13px;
}

.inventory-dialog__production-form :deep(.v-selection-control__wrapper) {
  font-size: 16px;
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

/* 생산 탭과 동일한 이월 기록 목록 */
.inventory-dialog__production {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  text-align: left;
  font-size: 13px;
}

/* 생산 합계 및 기록 제목과 동일한 디자인 */
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

/* 생산 탭과 동일한 기록 카드 */
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

/* 생산 탭과 동일한 기록 헤더 */
.inventory-dialog__production-record-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 0;
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

.inventory-dialog__production-record-label {
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
  overflow-wrap: anywhere;
  min-width: 0;
}

.inventory-dialog__production-record-quantity {
  font-size: 1rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* 생산탭과 동일한 이월 기록 헤더 글씨 */
.inventory-dialog__production
  .inventory-dialog__production-record-header strong {
  font-size: 0.8rem;
  font-weight: 600;
}


.inventory-dialog__production-record-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* 생산탭과 동일한 기록 수량 표시 */
.inventory-dialog__production
  .inventory-dialog__production-record-header-right {
  font-size: 13px;
  opacity: 0.7;
}


/* 선택한 생산 기록 강조 */
.inventory-dialog__production-record-header--selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
  box-shadow: inset 0 0 0 1px rgb(var(--v-theme-primary));
}

/* 생산 탭과 동일한 이월 기록 상세 정보 */
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

/* 생산 탭과 동일한 상세 항목 제목 */
.inventory-dialog__production-record .inventory-dialog__record-detail strong {
  display: block;
  margin-bottom: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.6;
  color: rgb(var(--v-theme-on-surface));
}

/* 생산 탭과 동일한 상세 항목 내용 */
.inventory-dialog__production-record .inventory-dialog__record-detail > div {
  color: rgba(var(--v-theme-on-surface), 0.72);
  white-space: pre-wrap;
}

/* 생산 탭과 동일한 기록별 수정·삭제 버튼 */
.inventory-dialog__record-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
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
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.inventory-dialog__selected-stock-row span {
  color: rgba(var(--v-theme-on-surface), 0.68);
}

.inventory-dialog__selected-stock-row strong {
  font-weight: 600;
  text-align: right;
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

/* 이월 재고 카드 정보 */
.inventory-dialog__carryover-record-content {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: flex-start;
  gap: 5px;
  min-width: 0;
}

/* 최초 생산일 */
.inventory-dialog__carryover-record-date {
  font-size: 12px;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

/* 이월 처리 및 이월 가능 수량 */
.inventory-dialog__carryover-record-quantity {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 12px;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.72);
}

/* 수량 강조 */
.inventory-dialog__carryover-record-quantity strong {
  font-size: 12px;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
}

/* 수량 구분점 */
.inventory-dialog__carryover-record-divider {
  opacity: 0.5;
}

/* 1. 안내 문구 */
.inventory-dialog__form-help {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  opacity: 0.7;
  text-align: left;
}

/* 2. 입력 항목 제목 */
.inventory-dialog__field-heading strong {
  font-size: 13px;
  font-weight: 600;
  line-height: normal;
}

.inventory-dialog__field-heading span {
  font-size: 12px;
  font-weight: normal;
  line-height: normal;
  opacity: 0.7;
}

/* 3. 카드 탐색 컨트롤 */
.inventory-dialog__card-controls {
  min-height: 0;
}

/* 4. 이월 기록 헤더 간격 */
.inventory-dialog__production-record-header {
  margin-bottom: 10px;
}

/* 재고 선택 카드는 기존 간격 유지 */
.inventory-dialog__swipe-card
  .inventory-dialog__production-record-header {
  margin-bottom: 0;
}

/* 5. 모바일 공통 여백 */
@media (max-width: 600px) {
  .inventory-dialog__placeholder {
    padding: 12px;
  }

  .inventory-dialog__production-form {
    padding: 10px;
  }

  .inventory-dialog__card-navigation {
    padding: 10px;
    gap: 12px;
  }

  .inventory-dialog__card-steps {
    gap: 6px;
  }

  .inventory-dialog__card-controls {
    min-height: 0;
  }
}

/* 좁은 모바일 화면에서 플렉스 요소가 가로폭을 밀어내지 않도록 처리 */
.inventory-dialog__carryover-tab,
.inventory-dialog__placeholder,
.inventory-dialog__production-form,
.inventory-dialog__card-navigation,
.inventory-dialog__card-steps,
.inventory-dialog__swipe-card,
.inventory-dialog__inner-card,
.inventory-dialog__production,
.inventory-dialog__production-record,
.inventory-dialog__selected-stock {
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

@media (max-width: 600px) {
  /* iOS Safari 포커스 자동 확대를 줄이기 위해 실제 편집 요소만 16px */
  .inventory-dialog__carryover-tab :deep(input:not([type="checkbox"])),
  .inventory-dialog__carryover-tab :deep(textarea),
  .inventory-dialog__carryover-tab :deep(.v-field__input) {
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
  .inventory-dialog__inner-card {
    padding: 10px;
  }

  .inventory-dialog__card-navigation {
    padding: 8px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .inventory-dialog__carryover-tab *,
  .inventory-dialog__carryover-tab *::before,
  .inventory-dialog__carryover-tab *::after {
    scroll-behavior: auto;
    animation-duration: 0.01ms;
  }
}
</style>