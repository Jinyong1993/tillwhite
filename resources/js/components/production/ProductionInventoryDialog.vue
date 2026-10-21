<template>
  <AppDialogLayout
    :model-value="modelValue"
    :title="`제품별 생산 관리 - ${product?.name || '제품'}`"
    :subtitle="workDate"
    :max-width="760"
    persistent
    card-class="inventory-dialog"
    @update:model-value="onDialogModelChange"
  >
    <div class="d-flex flex-column ga-3 min-width-0">
      <!-- 제품별 재고 현황 -->
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

      <!-- 업무 메뉴 -->
      <div
        class="inventory-dialog__navigation d-flex align-stretch flex-wrap ga-1"
        role="group"
        aria-label="생산·재고 업무 선택"
      >
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

      <!-- 업무별 상세 화면 -->
      <div class="inventory-dialog__content min-width-0">
        <!-- 생산 -->
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

        <!-- 이월 -->
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

        <!-- 로스 -->
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

        <!-- 폐기 -->
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

        <!-- 폐기율: 조회 전용 -->
        <WasteRateTab
          v-if="selectedSection === 'waste_rate'"
          :product="product"
          :work-date="workDate"
          :reason-options="wasteReasons"
        />

        <!-- 기존 미지원 업무 안내 -->
        <div
          v-if="
            activeSection &&
            !['production', 'carryover', 'loss', 'waste', 'waste_rate']
              .includes(selectedSection)
          "
          class="inventory-dialog__placeholder"
        >
          <strong>{{ activeSection.title }}</strong>

          <span v-if="selectedSection === 'loss'">
            로스 {{ displayQuantity(product?.operational_loss ?? product?.loss) }}
          </span>

          <template v-else-if="selectedSection === 'waste'">
            <span>
              폐기 {{ displayQuantity(product?.attributed_waste) }}
            </span>

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
                <span>{{ item.reason_text || "폐기 사유 미기재" }}</span>
                <strong>{{ displayQuantity(item.quantity) }}</strong>
              </div>
            </div>

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
                <span>최초 생산일 {{ item.originDate }}</span>
                <strong>{{ displayQuantity(item.quantity) }}</strong>
              </div>
            </div>
          </template>
        </div>

        <v-empty-state
          v-else-if="!activeSection"
          title="업무를 선택해 주세요."
          icon="mdi-clipboard-text-outline"
        />
      </div>
    </div>

    <!-- 상태별 공통 푸터 -->
    <template #footer>
      <!--
        1. 기본 상태
        최초 열기 / 조회 상태 / 폐기율 조회
        기존과 동일하게 닫기 버튼만 렌더링
      -->
      <AppDialogFooter
        v-if="!hasSaveButton && !hasCancelEditButton"
        :button-count="1"
        :show-confirm="false"
        cancel-text="닫기"
        @cancel="requestClose"
      />

      <!--
        2. 생산 기록 수정 상태
        닫기 / 수정 취소 / 수정 내용 저장
      -->
      <AppDialogFooter
        v-else-if="hasCancelEditButton"
        :button-count="3"
        :show-confirm="true"
        cancel-text="닫기"
        confirm-text="수정 내용 저장"
        confirm-action="save"
        :loading="isCurrentTabSaving"
        :disabled="!canMutate || isCurrentTabSaving"
        @cancel="requestClose"
        @confirm="requestCurrentSave"
      >
        <template #secondary>
          <v-btn
            block
            variant="tonal"
            class="app-btn"
            :disabled="productionTabRef?.isSaving"
            @click="productionTabRef?.cancelEdit()"
          >
            수정 취소
          </v-btn>
        </template>
      </AppDialogFooter>

      <!--
        3. 각 업무의 입력창이 열린 상태
        닫기 / 현재 업무 저장
      -->
      <AppDialogFooter
        v-else
        :button-count="2"
        :show-confirm="true"
        cancel-text="닫기"
        :confirm-text="saveButtonText"
        confirm-action="save"
        :loading="isCurrentTabSaving"
        :disabled="!canMutate || isCurrentTabSaving"
        @cancel="requestClose"
        @confirm="requestCurrentSave"
      />
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
 * 기존 폐기 내역 분석 로직 유지.
 * 최초 생산일 기준으로 이월 폐기를 분류합니다.
 */
const wasteDetails = computed(() => {
  const current = [];
  const carryoverByDate = new Map();

  for (const detail of props.product?.operational_waste_details || []) {
    const quantity = Number(detail.quantity);
    const originDate = detail.origin_production_date;

    if (!originDate || !Number.isFinite(quantity) || quantity <= 0) {
      continue;
    }

    const item = {
      ...detail,
      quantity,
      originDate,
    };

    if (originDate === props.workDate) {
      current.push(item);
      continue;
    }

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

// 통합 다이얼로그에서 현재 선택한 업무
const selectedSection = ref("");

// 기존 탭 컴포넌트 참조
const productionTabRef = ref(null);
const carryoverTabRef = ref(null);
const lossTabRef = ref(null);
const disposalTabRef = ref(null);

// 부모가 전달한 업무 메뉴 설정
const activeSection = computed(
  () =>
    props.sections.find(
      (section) => section.value === selectedSection.value
    ) || null
);

/**
 * 현재 선택한 업무 탭의 실제 컴포넌트 참조.
 * 기존 탭이 공개한 상태와 함수만 사용합니다.
 */
const currentTab = computed(() => {
  switch (selectedSection.value) {
    case "production":
      return productionTabRef.value;

    case "carryover":
      return carryoverTabRef.value;

    case "loss":
      return lossTabRef.value;

    case "waste":
      return disposalTabRef.value;

    default:
      return null;
  }
});

// 기존 탭의 저장 진행 상태
const isCurrentTabSaving = computed(
  () => Boolean(currentTab.value?.isSaving)
);

/**
 * 기존 버튼 표시 조건을 그대로 따릅니다.
 *
 * 최초 열기: false
 * 생산 수량 추가: productionFormOpen일 때 true
 * 생산 기록 수정: productionFormOpen일 때 true
 * 이월/로스/폐기: editorOpen일 때 true
 * 폐기율: false
 */
const hasSaveButton = computed(() => {
  switch (selectedSection.value) {
    case "production":
      return Boolean(productionTabRef.value?.productionFormOpen);

    case "carryover":
      return Boolean(carryoverTabRef.value?.editorOpen);

    case "loss":
      return Boolean(lossTabRef.value?.editorOpen);

    case "waste":
      return Boolean(disposalTabRef.value?.editorOpen);

    default:
      return false;
  }
});

// 생산 기록 수정 중에만 수정 취소 표시
const hasCancelEditButton = computed(
  () =>
    selectedSection.value === "production" &&
    Boolean(productionTabRef.value?.editingBatch)
);

// 기존 저장 버튼 문구 유지
const saveButtonText = computed(() => {
  switch (selectedSection.value) {
    case "production":
      return productionTabRef.value?.editingBatch
        ? "수정 내용 저장"
        : "생산 기록 저장";

    case "carryover":
      return "이월 기록 저장";

    case "loss":
      return "로스 기록 저장";

    case "waste":
      return "폐기 기록 저장";

    default:
      return "저장";
  }
});

/**
 * 기존 각 탭이 제공하는 저장 함수 호출.
 * 실제 저장 API 및 검증은 각 탭 내부 로직을 유지합니다.
 */
function requestCurrentSave() {
  if (
    !props.canMutate ||
    isCurrentTabSaving.value ||
    !hasSaveButton.value
  ) {
    return;
  }

  currentTab.value?.askSave?.();
}

/**
 * 닫기 시 기존 작성 내용 보호 절차 실행.
 */
function requestClose() {
  const closeDialog = () =>
    emit("update:modelValue", false);

  const tabRefs = {
    production: productionTabRef,
    carryover: carryoverTabRef,
    loss: lossTabRef,
    waste: disposalTabRef,
  };

  const currentTabRef = tabRefs[selectedSection.value];

  if (currentTabRef) {
    currentTabRef.value?.requestLeave(closeDialog);
    return;
  }

  closeDialog();
}

/**
 * 공통 다이얼로그에서 발생한 닫기 요청.
 */
function onDialogModelChange(value) {
  if (!value) {
    requestClose();
  }
}

/**
 * 다른 탭으로 이동하기 전 미저장 내용 확인.
 */
function selectSection(value) {
  if (
    !props.sections.some(
      (section) => section.value === value
    ) ||
    value === selectedSection.value
  ) {
    return;
  }

  const changeSection = () => {
    selectedSection.value = value;
    emit("section-change", value);
  };

  const tabRefs = {
    production: productionTabRef,
    carryover: carryoverTabRef,
    loss: lossTabRef,
    waste: disposalTabRef,
  };

  const currentTabRef = tabRefs[selectedSection.value];

  if (currentTabRef) {
    currentTabRef.value?.requestLeave(changeSection);
    return;
  }

  changeSection();
}

/**
 * 다이얼로그를 열면 부모가 지정한 업무를 선택합니다.
 */
watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) return;

    const requested = props.sections.find(
      (section) => section.value === props.initialSection
    );

    selectedSection.value =
      requested?.value ||
      props.sections[0]?.value ||
      "";
  },
  { immediate: true }
);

/**
 * 기존 수량 표시 로직.
 * 0개와 미확인을 구분합니다.
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
/*
 * 공통 레이아웃은 높이와 스크롤을 관리하고
 * 본 컴포넌트는 고유한 업무 UI만 관리합니다.
 */

/* 제품별 재고 현황 */
.inventory-dialog__summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  width: 100%;
  min-width: 0;
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
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
  text-align: center;
}

/* 생산·이월·로스·폐기·폐기율 메뉴 */
.inventory-dialog__navigation {
  display: flex;
  align-items: stretch;
  flex-wrap: wrap;
  gap: 4px;
  padding: 5px;
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.05);
}

.inventory-dialog__tab {
  flex: 1 1 auto;
  min-width: 55px;
  min-height: 44px;
  padding: 10px 8px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  touch-action: manipulation;
  transition: background 0.2s, color 0.2s;
}

.inventory-dialog__tab:hover {
  color: rgb(var(--v-theme-on-surface));
}

.inventory-dialog__tab--active {
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.inventory-dialog__tab:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 상세 업무 영역 */
.inventory-dialog__content {
  min-width: 0;
  min-height: 220px;
}

/* 미지원 업무 안내 */
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

/* 모바일 */
@media (max-width: 600px) {
  .inventory-dialog__summary strong {
    font-size: 16px;
  }

  .inventory-dialog__navigation {
    gap: 6px;
  }

  /*
   * iOS Safari 자동 확대 방지:
   * 실제 입력 요소에 최소 16px 적용.
   * 다이얼로그 위치나 스크롤을 강제 변경하지 않습니다.
   */
  :deep(.inventory-dialog input:not([type="checkbox"])),
  :deep(.inventory-dialog textarea),
  :deep(.inventory-dialog .v-field__input) {
    font-size: 16px;
  }
}
</style>