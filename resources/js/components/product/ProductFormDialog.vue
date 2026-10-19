<template>
  <!-- 제품 등록 및 수정 -->
  <AppDialogLayout
    :model-value="modelValue"
    :title="isEdit ? '제품 수정' : '제품 등록'"
    :subtitle="
      isEdit
        ? '제품의 기본 정보와 판매 정보를 수정합니다.'
        : '새로운 제품의 기본 정보와 판매 정보를 등록합니다.'
    "
    :max-width="620"
    persistent
    card-class="product-form-dialog"
    body-class="product-form-body"
    :busy="loading"
    @update:model-value="handleDialogChange"
  >
    <div class="product-form-content d-flex flex-column ga-3">
      <!-- 필수 입력 안내 -->
      <v-alert
        type="info"
        icon="mdi-information-outline"
        variant="tonal"
        density="compact"
        class="app-supporting-alert"
      >
        * 표시는 필수 입력 항목입니다.
      </v-alert>

      <!-- 기본 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section app-form-stack pa-3"
      >
        <div class="app-form-section-title">
          기본 정보
        </div>

        <!-- 일반 점포 직원은 자신의 점포를 읽기 전용으로 표시합니다. -->
        <v-text-field
          v-if="storeLocked"
          :model-value="lockedStoreName"
          label="점포 *"
          variant="outlined"
          density="compact"
          class="app-input"
          hide-details="auto"
          readonly
          :disabled="loading"
        />

        <!-- 최고 관리자는 접근 가능한 점포를 선택합니다. -->
        <v-select
          v-else
          v-model="form.store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="점포 *"
          variant="outlined"
          density="compact"
          class="app-select"
          hide-details="auto"
          clearable
          :disabled="loading"
        />

        <!-- 카테고리 -->
        <v-select
          v-model="form.product_category_id"
          :items="availableCategories"
          item-title="name"
          item-value="id"
          label="카테고리 *"
          variant="outlined"
          density="compact"
          class="app-select"
          hide-details="auto"
          :disabled="!form.store_id || loading"
        />

        <!-- 제품명 -->
        <v-text-field
          v-model.trim="form.name"
          label="제품명 *"
          placeholder="제품명을 입력하세요"
          variant="outlined"
          density="compact"
          maxlength="255"
          class="app-input"
          hide-details="auto"
          :rules="[requiredRule, nameLengthRule]"
          :disabled="loading"
        />

        <!-- 판매가 -->
        <v-number-input
          v-model="form.price"
          label="판매가 *"
          variant="outlined"
          density="compact"
          :min="0"
          :step="100"
          :rules="[requiredRule, priceRule]"
          class="product-form-number"
          hide-details="auto"
          :disabled="loading"
        />
      </v-card>

      <!-- 담당 부서 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section app-form-stack pa-3"
      >
        <div class="app-form-section-title">
          담당 부서
        </div>

        <!-- 생산 부서 -->
        <v-select
          v-model="form.production_department"
          :items="departments"
          label="생산 부서 *"
          variant="outlined"
          density="compact"
          class="app-select"
          hide-details="auto"
          :disabled="loading"
        />

        <!-- 관리 부서: 로그인 사용자의 소속 부서와 별개입니다. -->
        <v-select
          v-model="form.management_department"
          :items="departments"
          label="관리 부서 *"
          variant="outlined"
          density="compact"
          class="app-select"
          hide-details="auto"
          :disabled="loading"
        />
      </v-card>

      <!-- 판매 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section app-form-stack pa-3"
      >
        <div class="app-form-section-title">
          판매 정보
        </div>

        <!-- 판매 유형 -->
        <v-select
          v-model="form.sales_type"
          :items="salesTypes"
          label="판매 유형 *"
          variant="outlined"
          density="compact"
          class="app-select"
          hide-details="auto"
          :disabled="loading"
        />

        <!-- 기간 한정 제품의 판매 기간 -->
        <div
          v-if="form.sales_type === 'limited'"
          class="product-form-date-fields app-form-stack"
        >
          <v-text-field
            v-model="form.sales_start_date"
            label="판매 시작일 *"
            type="date"
            variant="outlined"
            density="compact"
            class="app-input"
            hide-details="auto"
            :disabled="loading"
          />

          <v-text-field
            v-model="form.sales_end_date"
            label="판매 종료일 *"
            type="date"
            variant="outlined"
            density="compact"
            class="app-input"
            hide-details="auto"
            :min="form.sales_start_date || undefined"
            :disabled="loading"
          />
        </div>
      </v-card>
    </div>

    <!-- 공통 푸터 -->
    <template #footer>
      <AppDialogFooter
        :button-count="isEdit ? 2 : 4"
        cancel-text="취소"
        :confirm-text="isEdit ? '저장' : '등록'"
        confirm-action="save"
        :loading="loading"
        @cancel="requestClose"
        @confirm="submit"
      >
        <!-- 등록 시에만 전체삭제 버튼을 표시합니다. -->
        <template v-if="!isEdit" #secondary>
          <v-btn
            block
            variant="tonal"
            color="error"
            min-height="44"
            :disabled="loading || !hasDraftAndInput"
            @click="clearDraft"
          >
            전체삭제
          </v-btn>
        </template>

        <!-- 등록 시에만 임시저장 버튼을 표시합니다. -->
        <template v-if="!isEdit" #actions>
          <v-btn
            block
            variant="tonal"
            color="primary"
            min-height="44"
            :disabled="loading || !hasChanges"
            @click="saveDraft"
          >
            임시저장
          </v-btn>
        </template>

        <!-- 기존 필수값과 변경 여부 조건을 그대로 사용합니다. -->
        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="success"
            min-height="44"
            :loading="loading"
            :disabled="loading || !canSubmit"
            @click="submit"
          >
            {{ isEdit ? '저장' : '등록' }}
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>

  <!-- 작성 중인 내용 폐기 확인 -->
  <AppDialogLayout
    :model-value="discardDialog"
    title="입력을 취소하시겠습니까?"
    :max-width="360"
    persistent
    card-class="app-dialog-card--compact"
    @update:model-value="handleDiscardDialogChange"
  >
    <div class="product-form-discard-content d-flex flex-column">
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section pa-3"
      >
        <div class="app-supporting-text">
          저장하지 않은 내용은 사라집니다.
        </div>
      </v-card>
    </div>

    <template #footer>
      <AppDialogFooter
        cancel-text="아니오"
        confirm-text="예"
        confirm-action="confirm"
        @cancel="discardDialog = false"
        @confirm="discardChanges"
      />
    </template>
  </AppDialogLayout>
</template>

<script setup>
import {
  computed,
  reactive,
  ref,
  watch,
} from 'vue';

import AppDialogLayout from '../common/AppDialogLayout.vue';
import AppDialogFooter from '../common/AppDialogFooter.vue';
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

const isSuperAdmin = computed(() => {
  return props.user?.role?.code === 'super_admin';
});

const storeLocked = computed(() => !isSuperAdmin.value);

/*
 * 일반 점포 직원은 소속 점포명을 읽기 전용으로 확인합니다.
 */
const lockedStoreName = computed(() => {
  return props.user?.store?.name ?? '-';
});

/*
 * 현재 선택한 점포의 활성 카테고리만 표시합니다.
 * 기존 자연 정렬을 유지합니다.
 */
const availableCategories = computed(() => {
  const categories = props.categories.filter((category) => {
    return Number(category.store_id) === Number(form.store_id)
      && category.is_active !== false;
  });

  return sortByDisplayName(categories);
});

/*
 * 최초 입력 상태와 현재 상태를 비교합니다.
 */
const hasChanges = computed(() => {
  return Boolean(initialSnapshot.value)
    && snapshot() !== initialSnapshot.value;
});

/*
 * 서버에 저장된 임시저장 데이터 또는
 * 현재 작성 중인 변경사항이 있는지 확인합니다.
 */
const hasDraftAndInput = computed(() => {
  return hasChanges.value || Boolean(props.draft);
});

/*
 * 필수값과 기간 한정 날짜 조건을 만족해야 등록할 수 있습니다.
 * 판매가는 0원도 유효한 값입니다.
 *
 * 수정 시에는 실제 변경사항이 있어야 저장할 수 있습니다.
 */
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

/*
 * 다이얼로그를 열 때 기존 제품 또는
 * Laravel Session 임시저장 내용을 적용합니다.
 */
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

/*
 * 점포를 변경하면 이전 점포의 카테고리를 제거합니다.
 */
watch(
  () => form.store_id,
  () => {
    clearUnavailableCategory();
  },
);

/*
 * 상시 제품으로 변경하면 판매 기간 입력값을 초기화합니다.
 */
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

/*
 * 임시저장은 기존 Laravel Session API를 사용하는
 * 부모 컴포넌트에 이벤트를 전달합니다.
 */
function saveDraft() {
  if (isEdit.value || props.loading || !hasChanges.value) {
    return;
  }

  emit('draft', createPayload());
}

/*
 * 전체삭제 확인과 실제 Session 임시저장 삭제는
 * 기존 부모 컴포넌트에서 처리합니다.
 */
function clearDraft() {
  if (isEdit.value || props.loading || !hasDraftAndInput.value) {
    return;
  }

  emit('clear-draft');
}

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

function createFormFromDraft(draft) {
  return {
    ...createEmptyForm(),
    ...(draft && typeof draft === 'object' ? draft : {}),
  };
}

function createFormFromProduct(product) {
  return {
    store_id: product.store_id ?? product.store?.id ?? null,
    product_category_id:
      product.product_category_id ?? product.category?.id ?? null,
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

/*
 * 선택한 점포에서 사용할 수 없는 카테고리 ID를 초기화합니다.
 */
function clearUnavailableCategory() {
  const available = availableCategories.value.some((category) => {
    return Number(category.id) === Number(form.product_category_id);
  });

  if (!available) {
    form.product_category_id = null;
  }
}

/*
 * 입력 상태를 문자열로 만들어 변경 여부를 비교합니다.
 */
function snapshot() {
  return JSON.stringify({
    ...form,
  });
}

/*
 * 변경사항이 있을 때는 폐기 확인창을 표시합니다.
 */
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

function discardChanges() {
  discardDialog.value = false;
  emit('close');
}

function handleDialogChange(value) {
  if (!value) {
    requestClose();
  }
}

function handleDiscardDialogChange(value) {
  if (!value) {
    discardDialog.value = false;
  }
}

/*
 * 기존 저장 검증을 통과하면 부모 저장 이벤트를 전달합니다.
 */
function submit() {
  if (!canSubmit.value || props.loading) {
    return;
  }

  emit('save', createPayload());
}

/*
 * 등록·수정·임시저장 요청의 기존 Payload 구조입니다.
 */
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
/*
 * 제품 등록·수정 화면 고유 레이아웃입니다.
 * 다이얼로그와 카드 디자인은 글로벌 CSS를 사용합니다.
 */
.product-form-content {
  min-width: 0;
  width: 100%;
}

/*
 * 기간 한정 판매 입력 그룹의 가로 넘침을 방지합니다.
 */
.product-form-date-fields {
  min-width: 0;
}

/*
 * 판매가 입력은 v-number-input을 사용하므로
 * 모바일 글꼴 크기를 별도로 보장합니다.
 */
.product-form-number {
  min-width: 0;
  max-width: 100%;
}

@media (max-width: 600px) {
  .product-form-number :deep(input) {
    font-size: 16px;
  }
}

/*
 * 작성 내용 폐기 확인창의 설명 영역입니다.
 */
.product-form-discard-content {
  min-width: 0;
  width: 100%;
}

/*
 * 좁은 모바일 화면에서 카드 간격을 조정합니다.
 */
@media (max-width: 360px) {
  .product-form-content {
    gap: 8px !important;
  }
}
</style>