<template>
  <v-dialog
    :model-value="modelValue"
    max-width="620"
    persistent
    @update:model-value="handleDialogChange"
  >
    <v-card rounded="lg">
      <v-card-title class="d-flex align-center ga-2 pa-5 pb-3">
        <v-icon :icon="isEdit ? 'mdi-package-variant' : 'mdi-package-variant-plus'" />
        {{ isEdit ? '제품 수정' : '제품 등록' }}
      </v-card-title>

      <v-divider />

      <v-card-text class="product-form-scroll pa-5">
        <!-- 기본 정보 -->
        <section>
          <div class="section-title">
            <v-icon icon="mdi-information-outline" size="18" />
            기본 정보
          </div>

          <div class="form-grid">
            <!--
              일반 점포 직원은 자기 점포만 선택할 수 있으므로 잠급니다.
              최고 관리자는 서버가 내려준 점포 범위 안에서 선택할 수 있습니다.
            -->
            <v-select
              v-model="form.store_id"
              :items="stores"
              item-title="name"
              item-value="id"
              label="점포 *"
              prepend-inner-icon="mdi-store-outline"
              variant="outlined"
              :readonly="storeLocked"
              :clearable="!storeLocked"
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
              :disabled="loading"
            />

            <v-number-input
              v-model="form.price"
              label="판매가 *"
              prepend-inner-icon="mdi-currency-krw"
              variant="outlined"
              :min="0"
              :step="100"
              :disabled="loading"
            />
          </div>
        </section>

        <v-divider class="my-5" />

        <!-- 담당 부서 -->
        <section>
          <div class="section-title">
            <v-icon icon="mdi-account-group-outline" size="18" />
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
              일반 점포 관리자는 자신의 부서 데이터만 관리할 수 있으므로
              관리 부서는 로그인 사용자의 부서로 고정합니다.
            -->
            <v-select
              v-model="form.management_department"
              :items="departments"
              label="관리 부서 *"
              prepend-inner-icon="mdi-account-cog-outline"
              variant="outlined"
              :readonly="departmentLocked"
              :disabled="loading"
            />
          </div>
        </section>

        <v-divider class="my-5" />

        <!-- 판매 정보 -->
        <section>
          <div class="section-title">
            <v-icon icon="mdi-calendar-check-outline" size="18" />
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
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4 px-5 product-form-actions">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="requestClose"
        >
          취소
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          prepend-icon="mdi-content-save-outline"
          :loading="loading"
          :disabled="!canSubmit"
          @click="submit"
        >
          {{ isEdit ? '저장' : '제품 등록' }}
        </v-btn>
      </v-card-actions>
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

      <v-card-text class="px-5 pb-5 text-body-2">
        저장하지 않은 내용은 사라집니다.
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4 px-5">
        <v-btn
          variant="text"
          @click="discardDialog = false"
        >
          계속 작성
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          @click="discardChanges"
        >
          나가기
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

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  product: {
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
]);

const discardDialog = ref(false);
const initialSnapshot = ref('');

const departments = [
  { title: '주방', value: 'kitchen' },
  { title: '홀', value: 'hall' },
];

const salesTypes = [
  { title: '상시 제품', value: 'regular' },
  { title: '기간 한정 제품', value: 'limited' },
];

const form = reactive(createEmptyForm());

const isEdit = computed(() => Boolean(props.product?.id));
const isSuperAdmin = computed(() => props.user?.role?.code === 'super_admin');
const storeLocked = computed(() => !isSuperAdmin.value);
const departmentLocked = computed(() => !isSuperAdmin.value);

/** 선택한 점포에서 현재 사용 가능한 카테고리만 표시합니다. */
const availableCategories = computed(() => props.categories.filter(
  (category) => Number(category.store_id) === Number(form.store_id)
    && category.is_active !== false,
));

/** 최초 상태와 현재 입력값을 비교하여 변경 여부를 판단합니다. */
const hasChanges = computed(
  () => Boolean(initialSnapshot.value)
    && snapshot() !== initialSnapshot.value,
);

/** 필수값과 기간한정 날짜 조건을 모두 만족해야 저장할 수 있습니다. */
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

  return isEdit.value ? hasChanges.value : true;
});

/** 다이얼로그가 열릴 때 등록/수정 모드에 맞는 값을 준비합니다. */
watch(
  () => [props.modelValue, props.product, props.user],
  () => {
    if (!props.modelValue) {
      return;
    }

    Object.assign(
      form,
      props.product
        ? createFormFromProduct(props.product)
        : createEmptyForm(),
    );

    /** 일반 점포 직원은 자기 점포와 자기 부서로 자동 고정합니다. */
    if (!isSuperAdmin.value) {
      form.store_id = props.user?.store?.id ?? null;
      form.management_department = ['kitchen', 'hall'].includes(props.user?.department)
        ? props.user.department
        : '';
    }

    if (!availableCategories.value.some(
      (category) => Number(category.id) === Number(form.product_category_id),
    )) {
      form.product_category_id = null;
    }

    discardDialog.value = false;
    initialSnapshot.value = snapshot();
  },
  { immediate: true },
);

/** 점포를 바꾸면 이전 점포의 카테고리 선택값을 자동 제거합니다. */
watch(
  () => form.store_id,
  () => {
    if (!availableCategories.value.some(
      (category) => Number(category.id) === Number(form.product_category_id),
    )) {
      form.product_category_id = null;
    }
  },
);

/** 상시 제품으로 바꾸면 기간한정 날짜를 서버에 남기지 않습니다. */
watch(
  () => form.sales_type,
  (value) => {
    if (value === 'regular') {
      form.sales_start_date = '';
      form.sales_end_date = '';
    }
  },
);

function createEmptyForm() {
  return {
    store_id: null,
    product_category_id: null,
    name: '',
    price: null,
    production_department: 'kitchen',
    management_department: '',
    sales_type: 'regular',
    sales_start_date: '',
    sales_end_date: '',
    sort_order: 0,
  };
}

function createFormFromProduct(product) {
  return {
    store_id: product.store_id ?? product.store?.id ?? null,
    product_category_id: product.product_category_id ?? product.category?.id ?? null,
    name: product.name ?? '',
    price: product.prices?.[0]?.price ?? null,
    production_department: product.production_department ?? 'kitchen',
    management_department: product.management_department ?? '',
    sales_type: product.sales_type ?? 'regular',
    sales_start_date: String(product.sales_start_date ?? '').slice(0, 10),
    sales_end_date: String(product.sales_end_date ?? '').slice(0, 10),
    sort_order: Number(product.sort_order ?? 0),
  };
}

function snapshot() {
  return JSON.stringify({ ...form });
}

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

function submit() {
  if (!canSubmit.value || props.loading) {
    return;
  }

  emit('save', {
    ...form,
    name: String(form.name).trim(),
    price: Number(form.price),
    sort_order: Number(form.sort_order ?? 0),
    sales_start_date: form.sales_type === 'limited'
      ? form.sales_start_date
      : null,
    sales_end_date: form.sales_type === 'limited'
      ? form.sales_end_date
      : null,
  });
}
</script>

<style scoped>
.product-form-scroll {
  max-height: min(70vh, 650px);
  overflow-y: auto;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  font-weight: 700;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 4px;
}

.product-form-actions {
  position: sticky;
  bottom: 0;
  background: rgb(var(--v-theme-surface));
}
</style>
