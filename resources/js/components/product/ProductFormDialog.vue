<template>
  <v-dialog
    :model-value="modelValue"
    max-width="620"
    persistent
    @update:model-value="handleDialogChange"
  >
    <v-card
        class="product-form-dialog"
        rounded="lg"
    >
      <!-- 직원 등록 다이얼로그와 같은 구조의 고정 헤더입니다. -->
      <div class="product-form-header">
        <div class="product-form-header-icon">
          <v-icon
            :icon="isEdit ? 'mdi-package-variant' : 'mdi-package-variant-plus'"
            size="22"
          />
        </div>

        <div class="min-width-0">
          <div class="text-h6 font-weight-bold">
            {{ isEdit ? '제품 수정' : '제품 등록' }}
          </div>

          <div class="product-form-description text-medium-emphasis mt-1">
            {{
              isEdit
                ? '제품의 기본 정보와 판매 정보를 수정합니다.'
                : '새로운 제품의 기본 정보와 판매 정보를 등록합니다.'
            }}
          </div>
        </div>
      </div>

      <v-divider />

      <div class="product-form-scroll">
        <div class="required-guide">
          <v-alert
            class="required-guide-alert"
            type="info"
            variant="tonal"
            density="compact"
          >
            * 표시는 필수 입력 항목입니다.
          </v-alert>
        </div>
        <!-- 기본 정보 -->
        <section class="product-form-section">
          <div class="product-form-section-header">
            <v-icon
                icon="mdi-information-outline"
                size="18"
            />
            기본 정보
          </div>

          <div class="form-grid">
            <!--
              일반 점포 직원은 자기 점포만 선택할 수 있으므로 잠급니다.
              최고 관리자는 서버가 내려준 점포 범위 안에서 선택할 수 있습니다.
            -->
            <!--
              일반 점포 직원의 점포는 서버 권한 범위상 변경할 수 없는 값입니다.
              선택 가능한 것처럼 보이는 Select 대신 읽기 전용 TextField로 표시하여
              사용자가 눌러도 선택 메뉴가 열리지 않도록 합니다.
            -->
            <v-text-field
              v-if="storeLocked"
              :model-value="lockedStoreName"
              label="점포 *"
              prepend-inner-icon="mdi-store-outline"
              append-inner-icon="mdi-lock-outline"
              variant="outlined"
              readonly
              :disabled="loading"
            />

            <v-select
              v-else
              v-model="form.store_id"
              :items="stores"
              item-title="name"
              item-value="id"
              label="점포 *"
              prepend-inner-icon="mdi-store-outline"
              variant="outlined"
              clearable
              :disabled="loading"
            />

            <v-select
              v-model="form.product_category_id"
              :items="availableCategories"
              item-title="name"
              item-value="id"
              label="카테고리 *"
              prepend-inner-icon="mdi-shape-outline"
              variant="outlined"
              :disabled="!form.store_id || loading"
            />

            <v-text-field
              v-model.trim="form.name"
              label="제품명 *"
              placeholder="제품명을 입력하세요"
              prepend-inner-icon="mdi-package-variant-closed"
              variant="outlined"
              maxlength="255"
              :rules="[requiredRule, nameLengthRule]"
              :disabled="loading"
            />

            <v-number-input
              v-model="form.price"
              label="판매가 *"
              prepend-inner-icon="mdi-currency-krw"
              variant="outlined"
              :min="0"
              :step="100"
              :rules="[requiredRule, priceRule]"
              :disabled="loading"
            />
          </div>
        </section>

        <v-divider />

        <!-- 담당 부서 -->
        <section class="product-form-section">
          <div class="product-form-section-header">
            <v-icon
                icon="mdi-account-group-outline"
                size="18"
            />
            담당 부서
          </div>

          <div class="form-grid">
            <v-select
              v-model="form.production_department"
              :items="departments"
              label="생산 부서 *"
              prepend-inner-icon="mdi-chef-hat"
              variant="outlined"
              :disabled="loading"
            />

            <!--
              제품의 관리 부서는 로그인 사용자의 소속 부서와 별개의 제품 속성입니다.
              음료는 홀, 제빵 제품은 주방처럼 실제 운영 담당 부서를 선택할 수 있습니다.
            -->
            <v-select
              v-model="form.management_department"
              :items="departments"
              label="관리 부서 *"
              prepend-inner-icon="mdi-account-cog-outline"
              variant="outlined"
              :disabled="loading"
            />
          </div>
        </section>

        <v-divider />

        <!-- 판매 정보 -->
        <section class="product-form-section">
          <div class="product-form-section-header">
            <v-icon
                icon="mdi-calendar-check-outline"
                size="18"
            />
            판매 정보
          </div>

          <v-select
            v-model="form.sales_type"
            :items="salesTypes"
            label="판매 유형 *"
            prepend-inner-icon="mdi-tag-outline"
            variant="outlined"
            :disabled="loading"
          />

          <div
            v-if="form.sales_type === 'limited'"
            class="form-grid"
          >
            <v-text-field
              v-model="form.sales_start_date"
              label="판매 시작일 *"
              type="date"
              prepend-inner-icon="mdi-calendar-start-outline"
              variant="outlined"
              :disabled="loading"
            />

            <v-text-field
              v-model="form.sales_end_date"
              label="판매 종료일 *"
              type="date"
              prepend-inner-icon="mdi-calendar-end-outline"
              variant="outlined"
              :min="form.sales_start_date || undefined"
              :disabled="loading"
            />
          </div>
        </section>
      </div>

      <v-divider />

      <div class="product-form-actions">
        <v-btn
          variant="text"
          prepend-icon="mdi-close"
          :disabled="loading"
          @click="requestClose"
        >
          취소
        </v-btn>

        <v-spacer />

        <div class="product-form-action-buttons">
          <template v-if="!isEdit">
            <v-btn
              variant="text"
              prepend-icon="mdi-delete-sweep-outline"
              :disabled="loading || !hasDraftAndInput"
              @click="clearDraft"
            >
              전체삭제
            </v-btn>

            <v-btn
              variant="flat"
              prepend-icon="mdi-content-save-outline"
              :disabled="loading || !hasChanges"
              @click="saveDraft"
            >
              임시저장
            </v-btn>
          </template>

          <v-btn
            variant="flat"
            :prepend-icon="isEdit ? 'mdi-content-save-check-outline' : 'mdi-package-variant-plus'"
            :loading="loading"
            :disabled="!canSubmit"
            @click="submit"
          >
            {{ isEdit ? '저장' : '등록' }}
          </v-btn>
        </div>
      </div>
    </v-card>
  </v-dialog>

  <!-- 작성/수정 중인 내용을 실수로 잃지 않도록 보호합니다. -->
  <v-dialog
    v-model="discardDialog"
    max-width="360"
    persistent
  >
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">
        입력을 취소하시겠습니까?
      </v-card-title>

      <v-card-text class="px-5 pb-5 app-supporting-text">
        저장하지 않은 내용은 사라집니다.
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4 px-5">
        <v-btn
          variant="text"
          @click="discardDialog = false"
        >
          아니오
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          @click="discardChanges"
        >
          예
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import {
  computed,
  reactive,
  ref,
  watch,
} from 'vue';

import { sortByDisplayName } from '../../utils/naturalSort';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  product: {
    type: Object,
    default: null,
  },
  draft: {
    type: Object,
    default: null,
  },
  stores: {
    type: Array,
    default: () => [],
  },
  categories: {
    type: Array,
    default: () => [],
  },
  user: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  'update:modelValue',
  'close',
  'save',
  'draft',
  'clear-draft',
]);

const discardDialog = ref(false);
const initialSnapshot = ref('');

const departments = [
  {
    title: '주방',
    value: 'kitchen',
  },
  {
    title: '홀',
    value: 'hall',
  },
];

const salesTypes = [
  {
    title: '상시 제품',
    value: 'regular',
  },
  {
    title: '기간 한정 제품',
    value: 'limited',
  },
];

const form = reactive(createEmptyForm());

const isEdit = computed(() => Boolean(props.product?.id));
const isSuperAdmin = computed(() => props.user?.role?.code === 'super_admin');
const storeLocked = computed(() => !isSuperAdmin.value);

// 일반 점포 사용자는 선택 UI 대신 자신의 점포명을 표시합니다.
const lockedStoreName = computed(() => props.user?.store?.name ?? '-');

// 현재 선택한 점포에서 사용 가능한 카테고리만 표시합니다.
const availableCategories = computed(() => {
  const categories = props.categories.filter((category) => {
    return Number(category.store_id) === Number(form.store_id)
      && category.is_active !== false;
  });

  // 선택 드롭다운은 관리용 sort_order와 분리하여 이름으로 찾기 쉽게 정렬합니다.
  return sortByDisplayName(categories);
});

// 최초 상태와 현재 입력값을 비교하여 실제 변경 여부를 판단합니다.
const hasChanges = computed(() => {
  return Boolean(initialSnapshot.value)
    && snapshot() !== initialSnapshot.value;
});

// 서버에 저장된 draft 또는 현재 작성 중인 내용이 있는지 확인합니다.
const hasDraftAndInput = computed(() => {
  return hasChanges.value || Boolean(props.draft);
});

// 필수값과 기간한정 날짜 조건을 모두 만족해야 최종 저장할 수 있습니다.
const canSubmit = computed(() => {
  const hasRequiredFields = Boolean(
    form.store_id
    && form.product_category_id
    && String(form.name ?? '').trim()
    && form.production_department
    && form.management_department
    && form.sales_type
    && form.price !== null
    && form.price !== undefined
    && Number.isInteger(Number(form.price))
    && Number(form.price) >= 0,
  );

  if (!hasRequiredFields) {
    return false;
  }

  if (form.sales_type === 'limited') {
    if (!form.sales_start_date || !form.sales_end_date) {
      return false;
    }

    if (form.sales_end_date < form.sales_start_date) {
      return false;
    }
  }

  return isEdit.value
    ? hasChanges.value
    : true;
});

// 다이얼로그가 열릴 때 수정 데이터 또는 Laravel Session draft를 적용합니다.
watch(
  () => [
    props.modelValue,
    props.product,
    props.draft,
    props.user,
  ],
  () => {
    if (!props.modelValue) {
      return;
    }

    const initialForm = props.product
      ? createFormFromProduct(props.product)
      : createFormFromDraft(props.draft);

    Object.assign(form, initialForm);

    // 일반 점포 사용자는 서버 권한 범위와 동일하게 자신의 점포로 고정합니다.
    if (!isSuperAdmin.value) {
      form.store_id = props.user?.store?.id ?? null;
    }

    clearUnavailableCategory();

    discardDialog.value = false;
    initialSnapshot.value = snapshot();
  },
  {
    immediate: true,
  },
);

// 점포를 바꾸면 이전 점포의 카테고리 선택값을 제거합니다.
watch(
  () => form.store_id,
  () => {
    clearUnavailableCategory();
  },
);

// 상시 제품으로 바꾸면 기간한정 날짜를 폼에 남기지 않습니다.
watch(
  () => form.sales_type,
  (value) => {
    if (value !== 'regular') {
      return;
    }

    form.sales_start_date = '';
    form.sales_end_date = '';
  },
);

const requiredRule = (value) => {
  const valid = value !== null
    && value !== undefined
    && String(value).trim() !== '';

  return valid || '필수 입력 항목입니다.';
};

const nameLengthRule = (value) => {
  return String(value ?? '').trim().length <= 255
    || '255자 이하로 입력해주세요.';
};

const priceRule = (value) => {
  const number = Number(value);

  return Number.isInteger(number) && number >= 0
    || '판매가는 0 이상의 정수로 입력해주세요.';
};

// 현재 입력 내용을 Laravel Session 임시저장 API로 전달합니다.
function saveDraft() {
  if (isEdit.value || props.loading || !hasChanges.value) {
    return;
  }

  emit('draft', createPayload());
}

// 전체삭제 확인과 실제 Session draft 삭제는 부모 화면에서 처리합니다.
function clearDraft() {
  if (isEdit.value || props.loading || !hasDraftAndInput.value) {
    return;
  }

  emit('clear-draft');
}

// 빈 제품 등록 폼의 기본값을 생성합니다.
function createEmptyForm() {
  return {
    store_id: null,
    product_category_id: null,
    name: '',
    price: null,
    production_department: '',
    management_department: '',
    sales_type: 'regular',
    sales_start_date: '',
    sales_end_date: '',
    sort_order: 0,
  };
}

// Laravel Session의 제품 등록 draft를 폼 형태로 변환합니다.
function createFormFromDraft(draft) {
  return {
    ...createEmptyForm(),
    ...(draft && typeof draft === 'object' ? draft : {}),
  };
}

// 기존 제품 데이터를 수정 폼에 맞는 값으로 변환합니다.
function createFormFromProduct(product) {
  return {
    store_id: product.store_id ?? product.store?.id ?? null,
    product_category_id: product.product_category_id ?? product.category?.id ?? null,
    name: product.name ?? '',
    price: product.prices?.[0]?.price ?? null,
    production_department: product.production_department ?? '',
    management_department: product.management_department ?? '',
    sales_type: product.sales_type ?? 'regular',
    sales_start_date: String(product.sales_start_date ?? '').slice(0, 10),
    sales_end_date: String(product.sales_end_date ?? '').slice(0, 10),
    sort_order: Number(product.sort_order ?? 0),
  };
}

// 현재 점포에서 사용할 수 없는 카테고리 선택값을 정리합니다.
function clearUnavailableCategory() {
  const available = availableCategories.value.some((category) => {
    return Number(category.id) === Number(form.product_category_id);
  });

  if (!available) {
    form.product_category_id = null;
  }
}

// 현재 폼 상태를 비교 가능한 문자열로 만들어 변경 여부를 판단합니다.
function snapshot() {
  return JSON.stringify({
    ...form,
  });
}

// 변경사항이 있으면 확인 후 닫고, 없으면 즉시 닫습니다.
function requestClose() {
  if (props.loading) {
    return;
  }

  if (hasChanges.value) {
    discardDialog.value = true;
    return;
  }

  emit('close');
}

// 작성 중 변경사항을 버리고 다이얼로그를 닫습니다.
function discardChanges() {
  discardDialog.value = false;
  emit('close');
}

// 외부에서 변경된 다이얼로그 상태를 안전한 닫기 흐름으로 연결합니다.
function handleDialogChange(value) {
  if (!value) {
    requestClose();
  }
}

// 현재 입력값을 정리해 부모의 저장 흐름으로 전달합니다.
function submit() {
  if (!canSubmit.value || props.loading) {
    return;
  }

  emit('save', createPayload());
}

// 등록/수정 및 draft 저장에 사용할 요청 데이터를 만듭니다.
function createPayload() {
  return {
    ...form,
    name: String(form.name ?? '').trim(),
    price: form.price === null || form.price === ''
      ? null
      : Number(form.price),
    sort_order: Number(form.sort_order ?? 0),
    sales_start_date: form.sales_type === 'limited'
      ? form.sales_start_date || null
      : null,
    sales_end_date: form.sales_type === 'limited'
      ? form.sales_end_date || null
      : null,
  };
}
</script>

<style scoped>
/* 직원 등록 다이얼로그와 동일하게 헤더/본문/하단 버튼을 분리합니다. */
.product-form-dialog {
  display: flex;
  max-height: calc(100vh - 48px);
  flex-direction: column;
  overflow: hidden;
}

.product-form-header {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 12px;
  padding: 20px;
}

.product-form-header-icon {
  display: flex;
  width: 38px;
  height: 38px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
}

.required-guide {
  padding: 14px 20px 0;
}

.product-form-scroll {
  min-height: 0;
  overflow-y: auto;
}

/* 섹션 안쪽만 여백을 주고 Divider는 스크롤 영역의 끝에서 끝까지 이어지게 합니다. */
.product-form-section {
  padding: 22px 20px;
}

.product-form-section-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
  font-size: 0.9rem;
  font-weight: 700;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2px;
}

.product-form-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  padding: 14px 20px;
  background: rgb(var(--v-theme-surface));
}

.product-form-action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.min-width-0 {
  min-width: 0;
}

.product-form-description,
.required-guide-alert :deep(.v-alert__content) {
  font-size: 0.76rem;
  line-height: 1.45;
}

.product-form-dialog {
  width: 100%;
  max-width: 100%;
}

.product-form-actions,
.product-form-action-buttons {
  min-width: 0;
}

@media (max-width: 480px) {
  .product-form-dialog {
    max-height: calc(100dvh - 16px);
  }

  .product-form-header,
  .product-form-section {
    padding-right: 16px;
    padding-left: 16px;
  }

  .required-guide {
    padding-right: 16px;
    padding-left: 16px;
  }

  .product-form-actions {
    align-items: stretch;
    padding: 12px 16px;
  }

  .product-form-action-buttons {
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
  }
}

@media (max-width: 390px) {
  .product-form-actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .product-form-actions > .v-btn {
    justify-self: start;
  }

  .product-form-actions > .v-spacer {
    display: none;
  }

  .product-form-action-buttons {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .product-form-action-buttons .v-btn:last-child {
    grid-column: 2;
  }
}
</style>
