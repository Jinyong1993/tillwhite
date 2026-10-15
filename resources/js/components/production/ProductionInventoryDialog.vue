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
          />

          <!-- 실제 관리 탭이 없는 항목에만 기존 안내 화면을 표시합니다. -->
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
          </div>

          <v-empty-state
            v-else-if="!activeSection"
            title="업무를 선택해 주세요."
            icon="mdi-clipboard-text-outline"
          />
        </div>
      </v-card-text>

      <v-card-actions class="app-dialog-footer px-4 pb-4">
        <v-btn
          variant="text"
          :disabled="
            (selectedSection === 'production' && productionTabRef?.isSaving) ||
            (selectedSection === 'carryover' && carryoverTabRef?.isSaving) ||
            (selectedSection === 'loss' && lossTabRef?.isSaving) ||
            (selectedSection === 'waste' && disposalTabRef?.isSaving)
          "
          @click="requestClose"
        >
          닫기
        </v-btn>

        <v-spacer />

        <v-btn
          v-if="selectedSection === 'production' && productionTabRef?.editingBatch"
          variant="text"
          :disabled="productionTabRef?.isSaving"
          @click="productionTabRef?.cancelEdit()"
        >
          수정 취소
        </v-btn>

        <v-btn
          v-if="selectedSection === 'production' && productionTabRef?.productionFormOpen"
          variant="flat"
          :loading="productionTabRef?.isSaving"
          :disabled="!canMutate || productionTabRef?.isSaving"
          @click="productionTabRef?.askSave()"
        >
          {{ productionTabRef?.editingBatch ? "수정 내용 저장" : "생산 기록 저장" }}
        </v-btn>

        <!-- 이월 저장: 추가/수정 입력창을 열었을 때만 표시 -->
        <v-btn
          v-if="
            selectedSection === 'carryover' &&
            carryoverTabRef?.editorOpen
          "
          variant="flat"
          :loading="carryoverTabRef?.isSaving"
          :disabled="
            !canMutate ||
            carryoverTabRef?.isSaving
          "
          @click="carryoverTabRef?.askSave()"
        >
          이월 기록 저장
        </v-btn>

        <!-- 로스탭에서 입력 중일 때 저장 버튼을 표시합니다. -->
        <v-btn
          v-if="selectedSection === 'loss' && lossTabRef?.editorOpen"
          variant="flat"
          :loading="lossTabRef?.isSaving"
          :disabled="!canMutate || lossTabRef?.isSaving"
          @click="lossTabRef?.askSave()"
        >
          로스 기록 저장
        </v-btn>

        <!-- 폐기탭에서 입력 중일 때 저장 버튼을 표시합니다. -->
        <v-btn
          v-if="selectedSection === 'waste' && disposalTabRef?.editorOpen"
          variant="flat"
          :loading="disposalTabRef?.isSaving"
          :disabled="!canMutate || disposalTabRef?.isSaving"
          @click="disposalTabRef?.askSave()"
        >
          폐기 기록 저장
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, ref, watch } from "vue";
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

// 통합 다이얼로그에서 현재 선택한 업무를 관리합니다.
const selectedSection = ref("");

// 생산 탭 컴포넌트 참조
const productionTabRef = ref(null);

const carryoverTabRef = ref(null);

const lossTabRef = ref(null);
const disposalTabRef = ref(null);

// 부모의 업무 메뉴 설정에서 현재 선택된 항목을 조회합니다.
const activeSection = computed(
  () => props.sections.find((section) => section.value === selectedSection.value) || null
);

// 다이얼로그를 닫기 전에 현재 탭의 작성 중인 내용을 확인합니다.
function requestClose() {
  const closeDialog = () => emit("update:modelValue", false);

  // 각 탭의 작성 취소 확인 기능을 연결합니다.
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
 * v-dialog에서 전달되는 닫기 요청도
 * 동일한 작성 내용 보호 절차를 사용합니다.
 */
function onDialogModelChange(value) {
  if (!value) {
    requestClose();
  }
}

// 다른 탭으로 이동하기 전에 현재 탭의 미저장 내용을 확인합니다.
function selectSection(value) {
  if (
    !props.sections.some((section) => section.value === value) ||
    value === selectedSection.value
  ) {
    return;
  }

  // 탭 변경을 실제로 실행합니다.
  const changeSection = () => {
    selectedSection.value = value;
    emit("section-change", value);
  };

  // 현재 탭의 작성 취소 확인 기능을 연결합니다.
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
</style>
