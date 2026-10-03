<template>
  <!--
    Till White 제품 관리 화면

    직원 관리 화면과 동일한 사용 흐름을 유지합니다.
    등록 → 검색/필터 → 카드 목록 → 페이지네이션 → 상세/수정 순서로 구성하며,
    실제 데이터 접근 범위와 변경 권한은 Laravel 서버에서 다시 검증합니다.
  -->
  <AppShell
    ref="appShellRef"
    :title="pageTitle"
  >
    <template #default="{ user, can, setError, setSuccess }">
      <!-- 제품 등록 -->
      <v-btn
        block
        class="mb-5"
        prepend-icon="mdi-package-variant-plus"
        variant="flat"
        @click="openRegisterDialog(user, can, setError)"
      >
        제품 등록

        <v-chip
          v-if="!can('product.manage')"
          class="ml-2"
          size="x-small"
          variant="tonal"
        >
          권한 없음
        </v-chip>
      </v-btn>

      <!-- 검색 / 필터 -->
      <v-card
        class="product-toolbar mb-5"
        variant="flat"
        rounded="lg"
      >
        <v-card-text>
          <div class="product-toolbar-grid">
            <v-text-field
              class="product-search-field"
              v-model="searchQuery"
              prepend-inner-icon="mdi-magnify"
              label="제품 검색"
              placeholder="제품명"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
            />

            <v-select
              class="product-category-filter"
              v-model="categoryFilter"
              :items="categoryFilterItems"
              label="카테고리"
              variant="outlined"
              density="comfortable"
              hide-details
            />

            <v-select
              class="product-status-filter"
              v-model="statusFilter"
              :items="statusFilterItems"
              label="상태"
              variant="outlined"
              density="comfortable"
              hide-details
            />

            <v-select
              class="product-sales-filter"
              v-model="salesTypeFilter"
              :items="salesTypeFilterItems"
              label="판매 유형"
              variant="outlined"
              density="comfortable"
              hide-details
            />

            <v-select
              v-if="visibleStores.length > 1"
              class="product-store-filter"
              v-model="storeFilter"
              :items="storeFilterItems"
              label="점포"
              variant="outlined"
              density="comfortable"
              hide-details
            />

            <v-select
              class="product-page-size"
              v-model="itemsPerPage"
              :items="itemsPerPageOptions"
              label="페이지당"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </div>

          <div class="d-flex align-center justify-space-between flex-wrap ga-2 mt-3">
            <div class="text-caption text-medium-emphasis">
              전체 {{ products.length }}개 · 검색 결과 {{ filteredProducts.length }}개
            </div>

            <v-btn
              v-if="hasActiveFilters"
              size="small"
              variant="text"
              prepend-icon="mdi-filter-remove-outline"
              @click="resetFilters"
            >
              검색 초기화
            </v-btn>
          </div>
        </v-card-text>
      </v-card>

      <!-- 제품 카드 목록 -->
      <div
        v-if="paginatedProducts.length > 0"
        class="d-flex flex-column ga-3"
      >
        <ProductCard
          v-for="product in paginatedProducts"
          :key="product.id"
          :product="product"
          @detail="openDetailDialog($event, user, setError)"
        />
      </div>

      <!-- 검색 결과 없음 -->
      <v-card
        v-else
        variant="outlined"
        rounded="lg"
      >
        <v-card-text class="text-center py-8">
          <v-icon
            icon="mdi-package-variant-closed-remove"
            size="36"
            class="mb-3 text-medium-emphasis"
          />

          <div class="font-weight-medium">
            검색 결과가 없습니다.
          </div>

          <div class="text-caption text-medium-emphasis mt-1">
            다른 제품명이나 필터 조건으로 검색해보세요.
          </div>

          <v-btn
            v-if="hasActiveFilters"
            class="mt-4"
            size="small"
            variant="tonal"
            prepend-icon="mdi-filter-remove-outline"
            @click="resetFilters"
          >
            검색/필터 초기화
          </v-btn>
        </v-card-text>
      </v-card>

      <!-- 클라이언트 페이지네이션 -->
      <div
        v-if="filteredProducts.length > 0"
        ref="paginationRef"
        class="d-flex flex-column align-center ga-2 mt-5"
      >
        <v-pagination
          :model-value="currentPage"
          :length="totalPages"
          :total-visible="4"
          class="product-pagination"
          rounded="circle"
          @update:model-value="handlePageChange"
        />

        <div class="text-caption text-medium-emphasis">
          {{ pageStart }}–{{ pageEnd }} / {{ filteredProducts.length }}개
        </div>
      </div>

      <!-- 제품 등록 -->
      <ProductFormDialog
        v-model="registerDialog"
        :stores="visibleStores"
        :categories="categories"
        :user="currentUser"
        :loading="productActionLoading === 'create'"
        @close="registerDialog = false"
        @save="saveProduct($event, setError, setSuccess)"
      />

      <!-- 제품 상세 -->
      <ProductDetailDialog
        v-model="detailDialog"
        :product="selectedProduct"
        :can-manage="canManageSelectedProduct(can)"
        :can-manage-recipe="canManageSelectedRecipe(can)"
        :loading="Boolean(productActionLoading)"
        @close="closeDetailDialog"
        @closed="clearClosedDetailState"
        @edit="openEditDialog"
        @toggle="requestProductAction('toggle')"
        @delete="requestProductAction('delete')"
        @restore="requestProductAction('restore')"
        @recipe="openRecipeDialog"
      />

      <!-- 제품 수정 -->
      <ProductFormDialog
        v-model="editDialog"
        :product="selectedProduct"
        :stores="visibleStores"
        :categories="categories"
        :user="currentUser"
        :loading="productActionLoading === 'edit'"
        @close="editDialog = false"
        @save="requestProductEdit($event, setError, setSuccess)"
      />

      <!-- 삭제/복구/취급상태/중요 수정 확인 -->
      <ConfirmDialog
        v-model="confirmDialog.open"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirm-text="confirmDialog.confirmText"
        cancel-text="취소"
        :loading="productActionLoading === confirmDialog.action"
        @confirm="executeConfirmedAction(setError, setSuccess)"
        @cancel="clearConfirmDialog"
      />

      <!--
        기존 레시피 등록 기능은 그대로 유지합니다.
        제품 상세에서 레시피 버튼을 눌렀을 때만 표시합니다.
      -->
      <v-dialog
        v-model="recipeDialog"
        max-width="520"
        persistent
      >
        <v-card rounded="lg">
          <v-card-title class="d-flex align-center ga-2 pa-5 pb-3">
            <v-icon :icon="editingRecipeId ? 'mdi-notebook-edit-outline' : 'mdi-notebook-plus-outline'" />
            {{ editingRecipeId ? '레시피 수정' : '레시피 등록' }}
          </v-card-title>

          <v-divider />

          <v-card-text class="recipe-scroll pa-5">
            <v-text-field
              v-model="recipe.name"
              label="레시피명 *"
              variant="outlined"
            />

            <v-textarea
              v-model="recipe.description"
              label="설명"
              variant="outlined"
              auto-grow
            />

            <v-textarea
              v-model="recipe.ingredientsText"
              label="재료 (한 줄에 이름,수량,단위)"
              placeholder="강력분,100,g"
              variant="outlined"
              auto-grow
            />

            <v-textarea
              v-model="recipe.stepsText"
              label="공정 (한 줄에 한 단계)"
              placeholder="재료를 계량한다."
              variant="outlined"
              auto-grow
            />
          </v-card-text>

          <v-divider />

          <v-card-actions class="pa-4 px-5">
            <v-btn
              variant="text"
              :disabled="productActionLoading === 'recipe'"
              @click="recipeDialog = false"
            >
              취소
            </v-btn>

            <v-spacer />

            <v-btn
              variant="flat"
              prepend-icon="mdi-content-save-outline"
              :loading="productActionLoading === 'recipe'"
              :disabled="!recipe.name.trim()"
              @click="saveRecipe(setError, setSuccess)"
            >
              {{ editingRecipeId ? '수정 저장' : '등록' }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>
  </AppShell>
</template>

<script setup>
import {
  computed,
  nextTick,
  onMounted,
  reactive,
  ref,
  watch,
} from 'vue';

import AppShell from '../../components/layout/AppShell.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import ProductCard from '../../components/product/ProductCard.vue';
import ProductDetailDialog from '../../components/product/ProductDetailDialog.vue';
import ProductFormDialog from '../../components/product/ProductFormDialog.vue';
import { useAppLoading } from '../../composables/useAppLoading';

const pageTitle = '제품 관리';
const appShellRef = ref(null);

const {
  completePageLoading,
} = useAppLoading();

/** 서버 원본 데이터 */
const products = ref([]);
const categories = ref([]);
const visibleStores = ref([]);

/** 현재 화면 사용자 및 제품 선택 상태 */
const currentUser = ref(null);
const selectedProduct = ref(null);

/** 다이얼로그 상태 */
const registerDialog = ref(false);
const detailDialog = ref(false);
const editDialog = ref(false);
const recipeDialog = ref(false);

/** 제품 API 중복 요청을 막기 위한 현재 작업 상태 */
const productActionLoading = ref(null);

/** 목록 검색/필터/페이지 상태 */
const searchQuery = ref('');
const categoryFilter = ref('all');
const statusFilter = ref('active');
const salesTypeFilter = ref('all');
const storeFilter = ref('all');
const itemsPerPage = ref(10);
const currentPage = ref(1);
const paginationRef = ref(null);

/** 삭제/복구/취급상태 변경/중요 수정에서 재사용하는 확인창 */
const confirmDialog = reactive({
  open: false,
  action: null,
  title: '',
  message: '',
  confirmText: '확인',
  payload: null,
});

/** 레시피 등록/수정 입력 상태 */
const recipe = reactive(createEmptyRecipe());
const editingRecipeId = ref(null);

const statusFilterItems = [
  { title: '전체', value: 'all' },
  { title: '취급중', value: 'active' },
  { title: '취급중단', value: 'inactive' },
  { title: '삭제', value: 'deleted' },
];

const salesTypeFilterItems = [
  { title: '전체', value: 'all' },
  { title: '상시', value: 'regular' },
  { title: '기간한정', value: 'limited' },
];

const itemsPerPageOptions = [
  { title: '5개', value: 5 },
  { title: '10개', value: 10 },
  { title: '30개', value: 30 },
];

const categoryFilterItems = computed(() => [
  { title: '전체', value: 'all' },
  ...categories.value
    .filter((category) => storeFilter.value === 'all'
      || Number(category.store_id) === Number(storeFilter.value))
    .map((category) => ({
      title: category.name,
      value: String(category.id),
    })),
]);

const storeFilterItems = computed(() => [
  { title: '전체', value: 'all' },
  ...visibleStores.value.map((store) => ({
    title: store.name,
    value: String(store.id),
  })),
]);

const hasActiveFilters = computed(() => Boolean(
  searchQuery.value
  || categoryFilter.value !== 'all'
  || statusFilter.value !== 'active'
  || salesTypeFilter.value !== 'all'
  || storeFilter.value !== 'all',
));

/** 모든 검색/필터는 서버 재요청 없이 현재 조회된 제품 배열에서 즉시 처리합니다. */
const filteredProducts = computed(() => {
  const keyword = String(searchQuery.value ?? '')
    .trim()
    .toLocaleLowerCase('ko-KR');

  return products.value.filter((product) => {
    const isDeleted = Boolean(product.deleted_at);

    const statusMatched = statusFilter.value === 'all'
      || (statusFilter.value === 'deleted' && isDeleted)
      || (statusFilter.value === 'active' && !isDeleted && product.is_active)
      || (statusFilter.value === 'inactive' && !isDeleted && !product.is_active);

    const categoryMatched = categoryFilter.value === 'all'
      || String(product.product_category_id) === String(categoryFilter.value);

    const salesTypeMatched = salesTypeFilter.value === 'all'
      || product.sales_type === salesTypeFilter.value;

    const storeMatched = storeFilter.value === 'all'
      || String(product.store_id) === String(storeFilter.value);

    const keywordMatched = !keyword
      || String(product.name ?? '').toLocaleLowerCase('ko-KR').includes(keyword);

    return statusMatched
      && categoryMatched
      && salesTypeMatched
      && storeMatched
      && keywordMatched;
  });
});

const totalPages = computed(
  () => Math.max(1, Math.ceil(filteredProducts.value.length / itemsPerPage.value)),
);

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredProducts.value.slice(start, start + itemsPerPage.value);
});

const pageStart = computed(() => filteredProducts.value.length === 0
  ? 0
  : ((currentPage.value - 1) * itemsPerPage.value) + 1);

const pageEnd = computed(() => Math.min(
  currentPage.value * itemsPerPage.value,
  filteredProducts.value.length,
));

/** 검색 조건이 바뀌면 존재하지 않는 높은 페이지에 남지 않도록 1페이지로 이동합니다. */
watch(
  [searchQuery, categoryFilter, statusFilter, salesTypeFilter, storeFilter, itemsPerPage],
  () => {
    currentPage.value = 1;
  },
);

/** 점포 필터 변경 후 선택 카테고리가 범위를 벗어나면 카테고리를 초기화합니다. */
watch(storeFilter, () => {
  if (!categoryFilterItems.value.some(
    (item) => String(item.value) === String(categoryFilter.value),
  )) {
    categoryFilter.value = 'all';
  }
});

watch(totalPages, (pages) => {
  if (currentPage.value > pages) {
    currentPage.value = pages;
  }
});

/** 제품 관리 화면 데이터를 조회합니다. */
async function load() {
  const response = await window.axios.get('/tillwhite/api/products');

  products.value = response.data.products ?? [];
  categories.value = response.data.categories ?? [];
  visibleStores.value = response.data.stores ?? [];
}

/** 최초 조회 오류는 AppShell 공통 알림으로 표시하고 페이지 로딩은 반드시 종료합니다. */
async function initialLoad() {
  try {
    await load();
  } catch (error) {
    appShellRef.value?.setError?.(
      errorMessage(error, '제품 정보를 불러오지 못했습니다.'),
    );
  } finally {
    await completePageLoading();
  }
}

/** 제품 등록 버튼은 항상 보이되 실제 권한이 없으면 친절한 오류만 표시합니다. */
function openRegisterDialog(user, can, setError) {
  if (!can('product.manage')) {
    setError('제품을 등록할 권한이 없습니다.');
    return;
  }

  currentUser.value = user;
  registerDialog.value = true;
}

/** 신규 제품을 저장합니다. */
async function saveProduct(payload, setError, setSuccess) {
  if (productActionLoading.value) {
    return;
  }

  productActionLoading.value = 'create';

  try {
    await window.axios.post('/tillwhite/api/products', payload);

    registerDialog.value = false;
    setSuccess('제품을 등록했습니다.');

    await refreshListAfterAction(
      setError,
      '제품 등록은 완료되었지만 목록을 새로고침하지 못했습니다.',
    );
  } catch (error) {
    setError(requestFailureMessage(error, '제품 등록에 실패했습니다.'));
  } finally {
    productActionLoading.value = null;
  }
}

/** 제품 카드를 누르면 서버에서 최신 상세정보와 접근 권한을 다시 확인합니다. */
async function openDetailDialog(product, user, setError) {
  currentUser.value = user;
  selectedProduct.value = null;
  detailDialog.value = false;

  try {
    const response = await window.axios.get(
      `/tillwhite/api/products/${product.id}`,
    );

    selectedProduct.value = response.data.product;
    detailDialog.value = true;
  } catch (error) {
    setError(errorMessage(error, '제품 정보를 열람할 수 없습니다.'));
  }
}

function closeDetailDialog() {
  if (productActionLoading.value) {
    return;
  }

  // 먼저 다이얼로그를 닫고, 퇴장 애니메이션이 끝난 뒤 선택값을 비웁니다.
  // 이렇게 해야 닫히는 순간 제품명이 '-'로 바뀌는 장면이 사용자에게 보이지 않습니다.
  detailDialog.value = false;
  editDialog.value = false;
  recipeDialog.value = false;
}

function clearClosedDetailState() {
  if (!detailDialog.value) {
    selectedProduct.value = null;
  }
}

function openEditDialog() {
  if (!selectedProduct.value || selectedProduct.value.deleted_at) {
    return;
  }

  editDialog.value = true;
}

/**
 * 프론트의 버튼 표시를 위한 보조 검사입니다.
 * 실제 수정 가능 여부는 Laravel에서 다시 검사합니다.
 */
function canManageSelectedProduct(can) {
  if (!selectedProduct.value || !can('product.manage')) {
    return false;
  }

  if (currentUser.value?.role?.code === 'super_admin') {
    return true;
  }

  return Number(currentUser.value?.store?.id) === Number(selectedProduct.value.store_id);
}

function canManageSelectedRecipe(can) {
  if (!selectedProduct.value || !can('recipe.manage')) {
    return false;
  }

  if (currentUser.value?.role?.code === 'super_admin') {
    return true;
  }

  return Number(currentUser.value?.store?.id) === Number(selectedProduct.value.store_id);
}

/** 수정 시 가격/상태/판매기간 등 영향이 큰 변경사항은 확인창을 한 번 더 표시합니다. */
function requestProductEdit(payload, setError, setSuccess) {
  if (!selectedProduct.value || productActionLoading.value) {
    return;
  }

  const changes = [];
  const oldPrice = Number(selectedProduct.value.prices?.[0]?.price ?? 0);

  if (oldPrice !== Number(payload.price)) {
    changes.push(`판매가: ${oldPrice.toLocaleString('ko-KR')}원 → ${Number(payload.price).toLocaleString('ko-KR')}원`);
  }

  if (selectedProduct.value.sales_type !== payload.sales_type) {
    changes.push(`판매 유형: ${salesTypeName(selectedProduct.value.sales_type)} → ${salesTypeName(payload.sales_type)}`);
  }

  if (
    String(selectedProduct.value.sales_start_date ?? '').slice(0, 10)
      !== String(payload.sales_start_date ?? '')
    || String(selectedProduct.value.sales_end_date ?? '').slice(0, 10)
      !== String(payload.sales_end_date ?? '')
  ) {
    changes.push('판매 기간이 변경됩니다.');
  }

  if (changes.length === 0) {
    saveProductEdit(payload, setError, setSuccess);
    return;
  }

  confirmDialog.action = 'edit';
  confirmDialog.payload = {
    payload,
    setError,
    setSuccess,
  };
  confirmDialog.title = '제품 정보를 수정하시겠습니까?';
  confirmDialog.message = `중요 정보가 변경됩니다.\n${changes.join('\n')}`;
  confirmDialog.confirmText = '저장';
  confirmDialog.open = true;
}

async function saveProductEdit(payload, setError, setSuccess) {
  if (!selectedProduct.value || productActionLoading.value) {
    return;
  }

  productActionLoading.value = 'edit';

  try {
    await window.axios.put(
      `/tillwhite/api/products/${selectedProduct.value.id}`,
      payload,
    );

    editDialog.value = false;
    clearConfirmDialog(true);
    setSuccess('제품 정보를 수정했습니다.');

    await refreshSelectedProduct(
      setError,
      '제품 수정은 완료되었지만 화면을 새로고침하지 못했습니다.',
    );
  } catch (error) {
    setError(requestFailureMessage(error, '제품 정보 수정에 실패했습니다.'));
  } finally {
    productActionLoading.value = null;
  }
}

/** 상세 화면의 취급중단/삭제/복구 버튼에서 공통 확인창을 엽니다. */
function requestProductAction(action) {
  if (!selectedProduct.value || productActionLoading.value) {
    return;
  }

  const settings = {
    toggle: {
      title: selectedProduct.value.is_active ? '제품 취급을 중단하시겠습니까?' : '제품 취급을 재개하시겠습니까?',
      message: selectedProduct.value.is_active
        ? `${selectedProduct.value.name} 제품은 과거 기록을 유지한 채 취급중단 상태로 변경됩니다.`
        : `${selectedProduct.value.name} 제품을 다시 취급중 상태로 변경합니다.`,
      confirmText: selectedProduct.value.is_active ? '취급중단' : '취급재개',
    },
    delete: {
      title: '제품을 삭제하시겠습니까?',
      message: `${selectedProduct.value.name} 제품은 목록에서 삭제 상태가 되지만 과거 생산·폐기·가격·레시피 기록은 보존되며 복구할 수 있습니다.`,
      confirmText: '삭제',
    },
    restore: {
      title: '제품을 복구하시겠습니까?',
      message: `${selectedProduct.value.name} 제품을 제품 목록에 다시 복구합니다.`,
      confirmText: '복구',
    },
  }[action];

  if (!settings) {
    return;
  }

  confirmDialog.action = action;
  confirmDialog.title = settings.title;
  confirmDialog.message = settings.message;
  confirmDialog.confirmText = settings.confirmText;
  confirmDialog.payload = null;
  confirmDialog.open = true;
}

async function executeConfirmedAction(setError, setSuccess) {
  const action = confirmDialog.action;

  if (!action || productActionLoading.value) {
    return;
  }

  if (action === 'edit') {
    const saved = confirmDialog.payload;

    if (!saved?.payload) {
      return;
    }

    await saveProductEdit(
      saved.payload,
      saved.setError ?? setError,
      saved.setSuccess ?? setSuccess,
    );
    return;
  }

  if (!selectedProduct.value) {
    return;
  }

  productActionLoading.value = action;

  try {
    const id = selectedProduct.value.id;

    if (action === 'toggle') {
      await window.axios.put(`/tillwhite/api/products/${id}/toggle`);
    } else if (action === 'delete') {
      await window.axios.delete(`/tillwhite/api/products/${id}`);
    } else if (action === 'restore') {
      await window.axios.put(`/tillwhite/api/products/${id}/restore`);
    }

    const messages = {
      toggle: '제품 취급 상태를 변경했습니다.',
      delete: '제품을 삭제했습니다.',
      restore: '제품을 복구했습니다.',
    };

    setSuccess(messages[action]);
    clearConfirmDialog(true);

    if (action === 'delete' || action === 'restore') {
      closeDetailDialogAfterAction();
      await refreshListAfterAction(setError, '작업은 완료되었지만 제품 목록을 새로고침하지 못했습니다.');
    } else {
      await refreshSelectedProduct(setError, '상태 변경은 완료되었지만 화면을 새로고침하지 못했습니다.');
    }
  } catch (error) {
    setError(requestFailureMessage(error, '제품 작업을 완료하지 못했습니다.'));
  } finally {
    productActionLoading.value = null;
  }
}

/**
 * 레시피가 없으면 등록 모드, 이미 있으면 첫 번째 대표 레시피 수정 모드로 엽니다.
 * 등록 직후 다시 열었을 때 빈 등록창이 뜨지 않도록 현재 상세 데이터에서 값을 채웁니다.
 */
function openRecipeDialog() {
  if (!selectedProduct.value || selectedProduct.value.deleted_at) {
    return;
  }

  const existingRecipe = selectedProduct.value.recipes?.[0] ?? null;

  editingRecipeId.value = existingRecipe?.id ?? null;
  Object.assign(
    recipe,
    existingRecipe
      ? createRecipeForm(existingRecipe)
      : createEmptyRecipe(),
  );

  recipeDialog.value = true;
}

async function saveRecipe(setError, setSuccess) {
  if (!selectedProduct.value || productActionLoading.value || !recipe.name.trim()) {
    return;
  }

  let ingredients;

  try {
    ingredients = parseIngredients(recipe.ingredientsText);
  } catch (error) {
    setError(error.message);
    return;
  }

  const steps = recipe.stepsText
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .map((description) => ({ description }));

  productActionLoading.value = 'recipe';

  try {
    const payload = {
      name: recipe.name.trim(),
      description: recipe.description.trim() || null,
      ingredients,
      steps,
    };

    if (editingRecipeId.value) {
      await window.axios.put(
        `/tillwhite/api/products/${selectedProduct.value.id}/recipes/${editingRecipeId.value}`,
        payload,
      );
    } else {
      await window.axios.post(
        `/tillwhite/api/products/${selectedProduct.value.id}/recipes`,
        payload,
      );
    }

    const wasEditing = Boolean(editingRecipeId.value);

    recipeDialog.value = false;
    editingRecipeId.value = null;
    setSuccess(wasEditing ? '레시피를 수정했습니다.' : '레시피를 등록했습니다.');

    await refreshSelectedProduct(
      setError,
      wasEditing
        ? '레시피 수정은 완료되었지만 화면을 새로고침하지 못했습니다.'
        : '레시피 등록은 완료되었지만 화면을 새로고침하지 못했습니다.',
    );
  } catch (error) {
    setError(requestFailureMessage(error, editingRecipeId.value ? '레시피 수정에 실패했습니다.' : '레시피 등록에 실패했습니다.'));
  } finally {
    productActionLoading.value = null;
  }
}

/** 재료 입력은 "이름,수량,단위" 세 값이 모두 정상일 때만 서버로 보냅니다. */
function parseIngredients(text) {
  return String(text ?? '')
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line, index) => {
      const [name, quantity, unit, ...extra] = line
        .split(',')
        .map((value) => value.trim());

      if (extra.length > 0 || !name || !unit || quantity === '' || Number.isNaN(Number(quantity))) {
        throw new Error(`${index + 1}번째 재료를 "재료명,수량,단위" 형식으로 입력해주세요.`);
      }

      if (Number(quantity) < 0) {
        throw new Error(`${index + 1}번째 재료 수량은 0 이상이어야 합니다.`);
      }

      return {
        name,
        quantity: Number(quantity),
        unit,
      };
    });
}

/** 목록과 현재 상세정보를 서버의 최신 상태로 맞춥니다. */
async function refreshSelectedProduct(setError, failureMessage) {
  const productId = selectedProduct.value?.id;

  try {
    await load();

    if (productId && detailDialog.value) {
      const response = await window.axios.get(`/tillwhite/api/products/${productId}`);
      selectedProduct.value = response.data.product;
    }
  } catch (error) {
    setError(failureMessage);
  }
}

async function refreshListAfterAction(setError, failureMessage) {
  try {
    await load();
  } catch (error) {
    setError(failureMessage);
  }
}

/** 직원관리와 동일하게 페이지 변경 전후 페이지네이션의 화면 위치를 유지합니다. */
async function handlePageChange(nextPage) {
  const oldTop = paginationRef.value?.getBoundingClientRect().top ?? null;

  currentPage.value = nextPage;
  await nextTick();

  if (oldTop === null || !paginationRef.value) {
    return;
  }

  const newTop = paginationRef.value.getBoundingClientRect().top;
  const difference = newTop - oldTop;

  if (difference !== 0) {
    window.scrollBy({
      top: difference,
      behavior: 'auto',
    });
  }
}

function resetFilters() {
  searchQuery.value = '';
  categoryFilter.value = 'all';
  statusFilter.value = 'active';
  salesTypeFilter.value = 'all';
  storeFilter.value = 'all';
  currentPage.value = 1;
}

function clearConfirmDialog(force = false) {
  if (productActionLoading.value && !force) {
    return;
  }

  confirmDialog.open = false;
  confirmDialog.action = null;
  confirmDialog.title = '';
  confirmDialog.message = '';
  confirmDialog.confirmText = '확인';
  confirmDialog.payload = null;
}

function closeDetailDialogAfterAction() {
  detailDialog.value = false;
  editDialog.value = false;
  recipeDialog.value = false;
}

function createRecipeForm(existingRecipe) {
  return {
    name: existingRecipe.name ?? '',
    description: existingRecipe.description ?? '',
    ingredientsText: (existingRecipe.ingredients ?? [])
      .map((ingredient) => `${ingredient.name},${ingredient.quantity},${ingredient.unit}`)
      .join('\n'),
    stepsText: (existingRecipe.steps ?? [])
      .map((step) => step.description ?? '')
      .filter(Boolean)
      .join('\n'),
  };
}

function createEmptyRecipe() {
  return {
    name: '',
    description: '',
    ingredientsText: '',
    stepsText: '',
  };
}

function salesTypeName(value) {
  return value === 'limited' ? '기간 한정' : '상시';
}

function errorMessage(error, fallback) {
  return error.response?.data?.message ?? fallback;
}

/**
 * 응답을 받지 못한 네트워크 오류에서는 실제 서버 반영 여부를 확정하지 않습니다.
 * 사용자가 같은 요청을 즉시 반복하여 중복 데이터가 생기는 것을 줄이기 위한 안내입니다.
 */
function requestFailureMessage(error, fallback) {
  if (!error.response) {
    return '서버 응답을 확인하지 못했습니다. 작업이 이미 반영되었을 수 있으므로 목록을 다시 확인해주세요.';
  }

  const validationErrors = error.response?.data?.errors;

  if (validationErrors && typeof validationErrors === 'object') {
    const firstMessage = Object.values(validationErrors)
      .flat()
      .find(Boolean);

    if (firstMessage) {
      return firstMessage;
    }
  }

  return errorMessage(error, fallback);
}

onMounted(() => {
  initialLoad();
});
</script>

<style scoped>
/* 직원관리 검색 영역과 동일한 배경/경계 톤을 사용합니다. */
.product-toolbar {
  overflow: hidden;
  background: rgba(var(--v-theme-on-surface), 0.025);
  border: 1px solid rgba(var(--v-border-color), 0.14);
}

/* 검색은 한 줄 전체, 나머지 필터는 모바일에서도 두 칸씩 정돈합니다. */
.product-toolbar-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  grid-template-areas:
    "search search"
    "category status"
    "sales page-size"
    "store store";
  gap: 10px;
}

.product-search-field { grid-area: search; }
.product-category-filter { grid-area: category; }
.product-status-filter { grid-area: status; }
.product-sales-filter { grid-area: sales; }
.product-page-size { grid-area: page-size; }
.product-store-filter { grid-area: store; }

.product-toolbar :deep(.v-field) {
  --v-field-border-opacity: 0.18;
}

.product-toolbar :deep(.v-field--focused) {
  --v-field-border-opacity: 0.34;
}

.product-pagination {
  width: calc(100% - 8px);
  max-width: calc(100% - 8px);
  margin-inline: auto;
  box-sizing: border-box;
}

.product-pagination :deep(.v-pagination__list) {
  width: 100%;
  max-width: 100%;
  margin: 0;
  padding: 0;
  justify-content: center;
  gap: 2px;
}

.product-pagination :deep(.v-pagination__list > li) {
  flex: 0 0 auto;
}

.product-pagination :deep(.v-btn) {
  flex: 0 0 36px;
  min-width: 36px;
  width: 36px;
  height: 36px;
}

.recipe-scroll {
  max-height: min(70vh, 650px);
  overflow-y: auto;
}

@media (max-width: 340px) {
  .product-pagination {
    width: calc(100% - 16px);
    max-width: calc(100% - 16px);
  }

  .product-pagination :deep(.v-btn) {
    flex-basis: 32px;
    min-width: 32px;
    width: 32px;
    height: 32px;
  }
}
</style>
