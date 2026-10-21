<template>
  <!-- 통합 생산·재고 관리 다이얼로그 -->
  <AppDialogLayout
    :model-value="modelValue"
    title="제품별 생산 관리"
    :subtitle="`${product?.name || '제품'} · ${workDate}`"
    :max-width="760"
    persistent
    card-class="inventory-dialog"
    :busy="isCurrentTabSaving"
    @update:model-value="onDialogModelChange"
  >
    <!-- 서버에서 전달받은 제품별 재고 현황 -->
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

    <!-- 선택한 업무의 재고 현황 및 상세 화면 -->
    <div class="inventory-dialog__content">
      <!-- 생산 탭 -->
      <ProductionTab
        v-if="selectedSection === 'production'"
        ref="productionTabRef"
        :model-value="modelValue"
        :store-id="storeId"
        :zero-reasons="zeroReasons"
        :can-mutate="canMutate"
        :workers="workers"
        :product="product"
        :work-date="workDate"
        @update:model-value="(value) => emit('update:modelValue', value)"
        @saved="(message) => emit('saved', message)"
        @error="(message) => emit('error', message)"
      />

      <!-- 이월 탭 -->
      <CarryoverTab
        v-if="selectedSection === 'carryover'"
        ref="carryoverTabRef"
        :model-value="modelValue"
        :store-id="storeId"
        :can-mutate="canMutate"
        :product="product"
        :work-date="workDate"
        @update:model-value="(value) => emit('update:modelValue', value)"
        @saved="(message, daily) => emit('saved', message, daily)"
        @error="(message) => emit('error', message)"
      />

      <!-- 로스 탭 -->
      <LossTab
        v-if="selectedSection === 'loss'"
        ref="lossTabRef"
        :model-value="modelValue"
        :store-id="storeId"
        :product="product"
        :work-date="workDate"
        :can-mutate="canMutate"
        :reason-options="lossReasons"
        @update:model-value="(value) => emit('update:modelValue', value)"
        @saved="(message, daily) => emit('saved', message, daily)"
        @error="(message) => emit('error', message)"
      />

      <!-- 폐기 탭 -->
      <DisposalTab
        v-if="selectedSection === 'waste'"
        ref="disposalTabRef"
        :model-value="modelValue"
        :store-id="storeId"
        :product="product"
        :work-date="workDate"
        :can-mutate="canMutate"
        :reason-options="wasteReasons"
        @update:model-value="(value) => emit('update:modelValue', value)"
        @saved="(message, daily) => emit('saved', message, daily)"
        @error="(message) => emit('error', message)"
      />

      <!-- 폐기율 탭 -->
      <WasteRateTab
        v-if="selectedSection === 'waste_rate'"
        :product="product"
        :work-date="workDate"
        :reason-options="wasteReasons"
      />

      <!-- 등록되지 않은 업무 탭 안내 -->
      <div
        v-if="
          activeSection &&
          !['production', 'carryover', 'loss', 'waste', 'waste_rate'].includes(selectedSection)
        "
        class="inventory-dialog__placeholder"
      >
        <strong>
          {{ activeSection.title }}
        </strong>

        <!-- 로스 현황 -->
        <span v-if="selectedSection === 'loss'">
          로스 {{ displayQuantity(product?.operational_loss ?? product?.loss) }}
        </span>

        <!-- 폐기 현황 -->
        <template v-else-if="selectedSection === 'waste'">
          <span>
            폐기 {{ displayQuantity(product?.attributed_waste) }}
          </span>

          <!-- 당일 생산분 폐기 -->
          <div
            v-if="wasteDetails.currentTotal > 0"
            class="inventory-dialog__waste-details"
          >
            <strong>
              당일 생산분 폐기
              {{ displayQuantity(wasteDetails.currentTotal) }}
            </strong>

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

          <!-- 이월 재고 폐기 -->
          <div
            v-if="wasteDetails.carryoverTotal > 0"
            class="inventory-dialog__waste-details"
          >
            <strong>
              이월 재고 폐기
              {{ displayQuantity(wasteDetails.carryoverTotal) }}
            </strong>

            <div
              v-for="item in wasteDetails.carryover"
              :key="item.originDate"
              class="inventory-dialog__waste-detail"
            >
              <span>
                최초 생산일 {{ item.originDate }}
              </span>

              <strong>
                {{ displayQuantity(item.quantity) }}
              </strong>
            </div>
          </div>
        </template>
      </div>

      <!-- 선택된 업무가 없는 경우 -->
      <v-empty-state
        v-else-if="!activeSection"
        title="업무를 선택해 주세요."
        icon="mdi-clipboard-text-outline"
      />
    </div>

    <!-- 공통 푸터 -->
    <template #footer>
      <AppDialogFooter
        :button-count="
          1 +
          (canCancelProductionEdit ? 1 : 0) +
          (hasSaveButton ? 1 : 0)
        "
        :show-confirm="hasSaveButton"
        :loading="isCurrentTabSaving"
        cancel-text="닫기"
        confirm-action="save"
        @cancel="requestClose"
      >
        <!-- 생산 기록 수정 취소 -->
        <template
          v-if="canCancelProductionEdit"
          #secondary
        >
          <v-btn
            block
            variant="tonal"
            min-height="44"
            :disabled="isCurrentTabSaving"
            @click="productionTabRef?.cancelEdit()"
          >
            수정 취소
          </v-btn>
        </template>

        <!-- 현재 업무 저장 -->
        <template #confirm>
          <v-btn
            v-if="hasSaveButton"
            block
            color="success"
            variant="flat"
            min-height="44"
            :loading="isCurrentTabSaving"
            :disabled="!canMutate || isCurrentTabSaving"
            @click="requestCurrentSave"
          >
            {{ saveButtonText }}
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>
</template>

<script setup>
import { computed, ref, watch } from "vue";

import AppDialogLayout from "../common/AppDialogLayout.vue";
import AppDialogFooter from "../common/AppDialogFooter.vue";

import ProductionTab from "./Tabs/ProductionTab.vue";
import CarryoverTab from "./Tabs/CarryoverTab.vue";
import LossTab from "./Tabs/LossTab.vue";
import DisposalTab from "./Tabs/DisposalTab.vue";
import WasteRateTab from "./Tabs/WasteRateTab.vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  // 선택한 점포 ID
  storeId: {
    type: Number,
    default: null,
  },

  // 생산 0개 확인 사유
  zeroReasons: {
    type: Array,
    default: () => [],
  },

  // 일반 수정 가능 여부
  canMutate: {
    type: Boolean,
    default: false,
  },

  // 생산 담당자 목록
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

  sections: {
    type: Array,
    default: () => [],
  },

  initialSection: {
    type: String,
    default: "",
  },

  lossReasons: {
    type: Array,
    default: () => [],
  },

  wasteReasons: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits([
  "update:modelValue",
  "section-change",
  "saved",
  "error",
]);

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

    // 선택 날짜에 생산한 제품의 실제 폐기
    if (originDate === props.workDate) {
      current.push(item);
      continue;
    }

    // 이월 재고 폐기 그룹화
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

  const carryover = [...carryoverByDate.values()].sort(
    (a, b) => a.originDate.localeCompare(b.originDate)
  );

  return {
    current,
    carryover,

    currentTotal: current.reduce(
      (total, item) => total + item.quantity,
      0
    ),

    carryoverTotal: carryover.reduce(
      (total, group) => total + group.quantity,
      0
    ),
  };
});

// 현재 선택한 업무
const selectedSection = ref("");

// 개별 업무 탭 컴포넌트 참조
const productionTabRef = ref(null);
const carryoverTabRef = ref(null);
const lossTabRef = ref(null);
const disposalTabRef = ref(null);

/**
 * 현재 선택된 업무 탭 컴포넌트입니다.
 *
 * 각 탭이 defineExpose로 제공하는 기존 속성과
 * 저장 함수를 공통 푸터에서 사용합니다.
 */
const currentTabRef = computed(() => ({
  production: productionTabRef.value,
  carryover: carryoverTabRef.value,
  loss: lossTabRef.value,
  waste: disposalTabRef.value,
}[selectedSection.value] ?? null));

// 현재 업무 저장 진행 상태
const isCurrentTabSaving = computed(
  () => Boolean(currentTabRef.value?.isSaving)
);

// 생산 기록 수정 취소 버튼 표시 여부
const canCancelProductionEdit = computed(
  () =>
    selectedSection.value === "production" &&
    Boolean(productionTabRef.value?.editingBatch)
);

/**
 * 현재 탭에서 저장 버튼을 표시할지 결정합니다.
 *
 * 생산: 생산 입력창이 열린 경우
 * 이월/로스/폐기: 각 편집창이 열린 경우
 * 폐기율: 저장 버튼 없음
 */
const hasSaveButton = computed(() => {
  if (selectedSection.value === "production") {
    return Boolean(productionTabRef.value?.productionFormOpen);
  }

  if (["carryover", "loss", "waste"].includes(selectedSection.value)) {
    return Boolean(currentTabRef.value?.editorOpen);
  }

  return false;
});

// 현재 업무의 저장 버튼 이름
const saveButtonText = computed(() => ({
  production: productionTabRef.value?.editingBatch
    ? "수정 내용 저장"
    : "생산 기록 저장",

  carryover: "이월 기록 저장",

  loss: "로스 기록 저장",

  waste: "폐기 기록 저장",
}[selectedSection.value] ?? "저장"));

/**
 * 기존 각 업무 탭의 저장 확인 함수를 호출합니다.
 * API 저장 로직은 탭 내부에 그대로 유지합니다.
 */
function requestCurrentSave() {
  if (
    !props.canMutate ||
    isCurrentTabSaving.value ||
    !hasSaveButton.value
  ) {
    return;
  }

  currentTabRef.value?.askSave?.();
}

// 부모의 업무 메뉴 설정에서 현재 선택 항목 조회
const activeSection = computed(
  () =>
    props.sections.find(
      (section) => section.value === selectedSection.value
    ) || null
);

/**
 * 다이얼로그를 닫기 전에 현재 탭의 작성 내용을 확인합니다.
 */
function requestClose() {
  if (isCurrentTabSaving.value) return;

  const closeDialog = () => emit("update:modelValue", false);

  // 기존 각 탭의 작성 취소 확인 기능
  const tabRefs = {
    production: productionTabRef,
    carryover: carryoverTabRef,
    loss: lossTabRef,
    waste: disposalTabRef,
  };

  const currentTab = tabRefs[selectedSection.value];

  if (currentTab) {
    currentTab.value?.requestLeave(closeDialog);
    return;
  }

  closeDialog();
}

/**
 * 다이얼로그에서 전달되는 닫기 요청도
 * 기존 작성 내용 보호 절차를 사용합니다.
 */
function onDialogModelChange(value) {
  if (!value) {
    requestClose();
  }
}

/**
 * 다른 업무 탭으로 이동하기 전에
 * 현재 탭의 미저장 내용을 확인합니다.
 */
function selectSection(value) {
  if (isCurrentTabSaving.value) return;

  if (
    !props.sections.some(
      (section) => section.value === value
    ) ||
    value === selectedSection.value
  ) {
    return;
  }

  // 탭 변경 실행
  const changeSection = () => {
    selectedSection.value = value;
    emit("section-change", value);
  };

  // 현재 탭의 작성 취소 확인 기능
  const tabRefs = {
    production: productionTabRef,
    carryover: carryoverTabRef,
    loss: lossTabRef,
    waste: disposalTabRef,
  };

  const currentTab = tabRefs[selectedSection.value];

  if (currentTab) {
    currentTab.value?.requestLeave(changeSection);
    return;
  }

  changeSection();
}

/**
 * 다이얼로그를 열면 부모가 지정한 업무를 선택합니다.
 * 지정한 업무가 없다면 첫 번째 메뉴를 선택합니다.
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
</script>

<style scoped>
/* 다이얼로그 전체 기본 글씨 */
.inventory-dialog {
  font-size: 12px;
}

/* 제품별 재고 현황 요약 */
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

/* 개별 업무 탭 */
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

/* 마우스 호버 */
.inventory-dialog__tab:hover {
  color: rgb(var(--v-theme-on-surface));
}

/* 선택된 업무 탭 */
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

/* 업무별 상세 조회 영역 */
.inventory-dialog__content {
  min-height: 220px;
}

/* 등록되지 않은 업무 안내 */
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

/* 폐기 상세 정보 */
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

.inventory-dialog__waste-detail +
.inventory-dialog__waste-detail {
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

/* 모바일 화면 */
@media (max-width: 600px) {
  .inventory-dialog__summary strong {
    font-size: 16px;
  }

  .inventory-dialog__navigation {
    gap: 6px;
  }
}
</style>