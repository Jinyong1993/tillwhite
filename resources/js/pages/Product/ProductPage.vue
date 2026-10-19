<template>
  <AppShell ref="appShellRef" :title="pageTitle">
    <template #default="{ user, can, setError, setSuccess }">
      <!-- 제품 등록 / 카테고리 관리 -->
      <div class="product-primary-actions mb-5">
        <v-btn
          class="product-primary-action"
          block
          prepend-icon="mdi-package-variant-plus"
          variant="flat"
          @click="openRegisterDialog(user, can, setError)"
        >
          <span class="product-primary-action-label">제품 등록</span>
        </v-btn>

        <v-btn
          class="product-primary-action"
          block
          prepend-icon="mdi-shape-plus-outline"
          variant="flat"
          @click="openCategoryDialog(can, setError)"
        >
          <span class="product-primary-action-label">카테고리 관리</span>
        </v-btn>
      </div>

      <!-- 최근 본 제품 -->
      <div v-if="recentViewed.length" class="recent-viewed mb-5">
        <span class="recent-viewed-label">최근 본 제품</span>
        <v-chip
          v-for="item in recentViewed"
          :key="item.id"
          class="recent-viewed-chip"
          size="small"
          variant="tonal"
          @click="openRecentItem(item, user, setError)"
        >
          {{ item.title }}
        </v-chip>
      </div>

      <!-- 검색 및 필터 -->
      <ProductSearchFilter
        v-model:search-query="searchQuery"
        v-model:store-filter="storeFilter"
        v-model:category-filter="categoryFilter"
        v-model:status-filter="statusFilter"
        v-model:sales-type-filter="salesTypeFilter"
        v-model:items-per-page="itemsPerPage"
        :store-items="storeFilterItems"
        :category-items="categoryFilterItems"
        :status-items="statusFilterItems"
        :sales-type-items="salesTypeFilterItems"
        :page-size-items="itemsPerPageOptions"
        :show-store="visibleStores.length > 1"
        :total="products.length"
        :filtered="filteredProducts.length"
        :has-active-filters="hasActiveFilters"
        @reset="resetFilters"
      />

      <!-- 제품 목록 -->
      <div
        v-if="paginatedProducts.length > 0"
        class="d-flex flex-column ga-3"
      >
        <ProductCard
          v-for="product in paginatedProducts"
          :key="product.id"
          :product="product"
          :can-manage="canManageProduct(product, user, can)"
          :loading="productActionLoading === 'toggle'"
          @detail="openDetailDialog($event, user, setError)"
          @status-change="requestCardProductStatus(product, $event)"
        />
      </div>

      <v-card v-else variant="outlined" rounded="lg">
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

      <!-- 페이지네이션 -->
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
        :draft="productDraft"
        :loading="['create', 'draft', 'clear-draft'].includes(productActionLoading)"
        @close="registerDialog = false"
        @save="requestProductCreate($event, setError, setSuccess)"
        @draft="requestSaveProductDraft($event, setError, setSuccess)"
        @clear-draft="requestClearProductDraft(setError, setSuccess)"
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
        @clone="openCloneProductDialog(false)"
        @toggle="requestProductAction('toggle')"
        @delete="requestProductAction('delete')"
        @restore="requestProductAction('restore')"
        @recipe="recipeDialogRef?.openEditor()"
        @recipe-part="recipeDialogRef?.openPart($event)"
        @recipe-copy="recipeDialogRef?.openCopy()"
        @recipe-delete="recipeDialogRef?.requestDelete($event)"
        @recipe-restore="recipeDialogRef?.requestRestore($event)"
        @recipe-create-product="openCloneProductDialog(true)"
        @recipe-view-product="focusProductInfo"
        @permission-denied="setError($event)"
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

      <!-- 카테고리 관리 -->
      <ProductCategoryDialog
        v-model="categoryDialog"
        :categories="categories"
        :stores="visibleStores"
        :loading="productActionLoading === 'category'"
        @create="requestCategoryCreate($event, setError, setSuccess)"
        @rename="requestCategoryRename($event, setError, setSuccess)"
        @toggle="requestCategoryToggle($event, setError, setSuccess)"
        @reorder="requestCategoryReorder($event, setError, setSuccess)"
      />

      <!-- 새 제품 만들기 / 제품 복사 -->
      <AppDialogLayout
        :model-value="cloneProductDialog"
        :title="cloneFromRecipe ? '새 제품 만들기' : '제품 복사'"
        :subtitle="cloneFromRecipe
          ? '레시피를 기반으로 새 제품을 생성합니다.'
          : '원본 제품을 기반으로 새 제품을 생성합니다.'"
        :max-width="480"
        persistent
        closable
        card-class="app-dialog-card--compact"
        :busy="productActionLoading === 'clone-product'"
        @close="requestCloseCloneProductDialog"
        @update:model-value="value => {
          if (!value) requestCloseCloneProductDialog();
        }"
      >
        <div class="d-flex flex-column ga-2 px-2 pt-0 pb-2">
          <AppInfoAlert>
            {{
              cloneFromRecipe
                ? '이 레시피를 기반으로 새 제품을 만듭니다. 원본 제품 정보는 변경되지 않습니다.'
                : '원본 제품 정보는 변경하지 않고 별도의 새 제품을 생성합니다.'
            }}
          </AppInfoAlert>

          <div class="app-form-section app-form-stack pa-3">
            <div class="app-form-section-title">
              기본 내용
            </div>

            <v-text-field
              v-model="cloneProductName"
              label="제품명 *"
              variant="outlined"
              density="compact"
              class="app-input"
              hide-details
              :disabled="productActionLoading === 'clone-product'"
            />

            <v-checkbox
              v-if="!cloneFromRecipe && activeSelectedRecipe"
              v-model="cloneWithRecipe"
              label="레시피도 함께 복사"
              density="compact"
              hide-details
              :disabled="productActionLoading === 'clone-product'"
            />
          </div>
        </div>

        <template #footer>
          <AppDialogFooter
            :loading="productActionLoading === 'clone-product'"
            cancel-text="취소"
            confirm-text="등록"
            @cancel="requestCloseCloneProductDialog"
            @confirm="createClonedProduct(setError, setSuccess)"
          />
        </template>
      </AppDialogLayout>

      <!-- 공통 확인창 -->
      <ConfirmDialog
        v-model="confirmDialog.open"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :loading="Boolean(productActionLoading)"
        @confirm="executeConfirmedAction(setError, setSuccess)"
        @cancel="clearConfirmDialog"
      />

      <!-- 레시피 관리 -->
      <ProductRecipeDialog
        ref="recipeDialogRef"
        :product="selectedProduct"
        :products="products"
        @error="setError($event)"
        @success="setSuccess($event)"
        @refresh-product="refreshSelectedProduct(
          setError,
          '레시피 작업은 완료되었지만 화면을 새로고침하지 못했습니다.'
        )"
        @refresh-list="refreshListAfterAction(
          setError,
          '레시피 복사는 완료되었지만 제품 목록을 새로고침하지 못했습니다.'
        )"
      />
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
import AppDialogLayout from '../../components/common/AppDialogLayout.vue';
import AppDialogFooter from '../../components/common/AppDialogFooter.vue';
import AppInfoAlert from '../../components/common/AppInfoAlert.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';

import ProductCard from '../../components/product/ProductCard.vue';
import ProductSearchFilter from '../../components/product/ProductSearchFilter.vue';
import ProductCategoryDialog from '../../components/product/ProductCategoryDialog.vue';
import ProductDetailDialog from '../../components/product/ProductDetailDialog.vue';
import ProductFormDialog from '../../components/product/ProductFormDialog.vue';
import ProductRecipeDialog from '../../components/product/ProductRecipeDialog.vue';

import { useAppLoading } from '../../composables/useAppLoading';
import { compareDisplayName } from '../../utils/naturalSort';

const pageTitle = '제품 관리';

const appShellRef = ref(null);
const recipeDialogRef = ref(null);

const { completePageLoading } = useAppLoading();

const products = ref([]);
const categories = ref([]);
const visibleStores = ref([]);

const currentUser = ref(null);
const selectedProduct = ref(null);
const productDraft = ref(null);

const registerDialog = ref(false);
const detailDialog = ref(false);
const editDialog = ref(false);
const categoryDialog = ref(false);

const cloneProductDialog = ref(false);
const cloneProductName = ref('');
const cloneWithRecipe = ref(false);
const cloneFromRecipe = ref(false);
const cloneInitialSnapshot = ref('');

const recentViewed = ref([]);
const productActionLoading = ref(null);

const searchQuery = ref('');
const categoryFilter = ref('all');
const statusFilter = ref('active');
const salesTypeFilter = ref('all');
const storeFilter = ref('all');
const itemsPerPage = ref(10);
const currentPage = ref(1);
const paginationRef = ref(null);

const confirmDialog = reactive({
  open: false,
  action: null,
  title: '',
  message: '',
  confirmText: '확인',
  payload: null,
});

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

const activeSelectedRecipe = computed(() =>
  selectedProduct.value?.recipes?.find(
    item => !item.deleted_at
  ) ?? null
);

const categoryFilterItems = computed(() => {
  const source = storeFilter.value === 'all'
    ? categories.value
    : categories.value.filter(
        category =>
          Number(category.store_id) === Number(storeFilter.value)
      );

  const names = [
    ...new Set(
      source
        .map(category => category.name)
        .filter(Boolean)
    ),
  ].sort(compareDisplayName);

  return [
    { title: '전체', value: 'all' },
    ...names.map(name => ({
      title: name,
      value: name,
    })),
  ];
});

const storeFilterItems = computed(() => [
  { title: '전체', value: 'all' },
  ...visibleStores.value.map(store => ({
    title: store.name,
    value: String(store.id),
  })),
]);

const hasActiveFilters = computed(() => Boolean(
  searchQuery.value
  || categoryFilter.value !== 'all'
  || statusFilter.value !== 'active'
  || salesTypeFilter.value !== 'all'
  || storeFilter.value !== 'all'
));

const filteredProducts = computed(() => {
  const keyword = String(searchQuery.value ?? '')
    .trim()
    .toLocaleLowerCase('ko-KR');

  return products.value.filter(product => {
    const isDeleted = Boolean(product.deleted_at);

    const statusMatched =
      statusFilter.value === 'all'
      || (statusFilter.value === 'deleted' && isDeleted)
      || (
        statusFilter.value === 'active'
        && !isDeleted
        && product.is_active
      )
      || (
        statusFilter.value === 'inactive'
        && !isDeleted
        && !product.is_active
      );

    const categoryMatched =
      categoryFilter.value === 'all'
      || String(product.category?.name ?? '')
        === String(categoryFilter.value);

    const salesTypeMatched =
      salesTypeFilter.value === 'all'
      || product.sales_type === salesTypeFilter.value;

    const storeMatched =
      storeFilter.value === 'all'
      || String(product.store_id) === String(storeFilter.value);

    const keywordMatched =
      !keyword
      || String(product.name ?? '')
        .toLocaleLowerCase('ko-KR')
        .includes(keyword);

    return statusMatched
      && categoryMatched
      && salesTypeMatched
      && storeMatched
      && keywordMatched;
  });
});

const totalPages = computed(() =>
  Math.max(
    1,
    Math.ceil(
      filteredProducts.value.length / itemsPerPage.value
    )
  )
);

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;

  return filteredProducts.value.slice(
    start,
    start + itemsPerPage.value
  );
});

const pageStart = computed(() =>
  filteredProducts.value.length === 0
    ? 0
    : ((currentPage.value - 1) * itemsPerPage.value) + 1
);

const pageEnd = computed(() =>
  Math.min(
    currentPage.value * itemsPerPage.value,
    filteredProducts.value.length
  )
);

watch(
  [
    searchQuery,
    categoryFilter,
    statusFilter,
    salesTypeFilter,
    storeFilter,
    itemsPerPage,
  ],
  () => {
    currentPage.value = 1;
  }
);

watch(storeFilter, () => {
  if (!categoryFilterItems.value.some(
    item => String(item.value) === String(categoryFilter.value)
  )) {
    categoryFilter.value = 'all';
  }
});

watch(totalPages, pages => {
  if (currentPage.value > pages) {
    currentPage.value = pages;
  }
});

async function load() {
  const response = await window.axios.get(
    '/tillwhite/api/products'
  );

  products.value = response.data.products ?? [];
  categories.value = response.data.categories ?? [];
  visibleStores.value = response.data.stores ?? [];
}

async function initialLoad() {
  try {
    await load();

    try {
      const response = await window.axios.get(
        '/tillwhite/api/products/recent-viewed'
      );

      recentViewed.value = response.data.items ?? [];
    } catch (_) {
      recentViewed.value = [];
    }
  } catch (error) {
    appShellRef.value?.setError?.(
      errorMessage(
        error,
        '제품 정보를 불러오지 못했습니다.'
      )
    );
  } finally {
    await completePageLoading();
  }
}

function openCategoryDialog(can, setError) {
  if (!can('product.manage')) {
    setError('카테고리를 관리할 권한이 없습니다.');
    return;
  }

  categoryDialog.value = true;
}

function requestCategoryCreate(payload, setError, setSuccess) {
  openConfirm(
    'category-create',
    '카테고리를 등록하시겠습니까?',
    `‘${payload.name}’ 카테고리를 등록합니다.`,
    '예',
    { payload, setError, setSuccess }
  );
}

function requestCategoryRename(payload, setError, setSuccess) {
  openConfirm(
    'category-rename',
    '카테고리명을 수정하시겠습니까?',
    `‘${payload.category.name}’ → ‘${payload.name}’으로 변경합니다.`,
    '예',
    { payload, setError, setSuccess }
  );
}

function requestCategoryToggle(category, setError, setSuccess) {
  const actionText = category.is_active
    ? '사용을 중단'
    : '다시 사용';

  openConfirm(
    'category-toggle',
    `카테고리를 ${actionText}하시겠습니까?`,
    `‘${category.name}’ 카테고리를 ${actionText}합니다. 기존 제품과 기록은 유지됩니다.`,
    '예',
    { category, setError, setSuccess }
  );
}

function requestCategoryReorder(
  { category, direction },
  setError,
  setSuccess
) {
  const directionText = direction < 0 ? '위로' : '아래로';

  openConfirm(
    'category-reorder',
    '카테고리 순서를 변경하시겠습니까?',
    `‘${category.name}’ 카테고리를 한 칸 ${directionText} 이동합니다.`,
    '예',
    { category, direction, setError, setSuccess }
  );
}

async function runCategoryRequest(
  request,
  successMessage,
  setError,
  setSuccess
) {
  if (productActionLoading.value) return;

  productActionLoading.value = 'category';

  try {
    await request();
    setSuccess(successMessage);
    await load();
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '카테고리 처리에 실패했습니다.'
      )
    );
  } finally {
    productActionLoading.value = null;
  }
}

function createCategory(payload, setError, setSuccess) {
  return runCategoryRequest(
    () => window.axios.post(
      '/tillwhite/api/product-categories',
      payload
    ),
    '카테고리를 등록했습니다.',
    setError,
    setSuccess
  );
}

function renameCategory(
  { category, name },
  setError,
  setSuccess
) {
  return runCategoryRequest(
    () => window.axios.put(
      `/tillwhite/api/product-categories/${category.id}`,
      { name }
    ),
    '카테고리를 수정했습니다.',
    setError,
    setSuccess
  );
}

function toggleCategory(category, setError, setSuccess) {
  const successMessage = category.is_active
    ? '카테고리 사용을 중단했습니다.'
    : '카테고리를 다시 사용합니다.';

  return runCategoryRequest(
    () => window.axios.put(
      `/tillwhite/api/product-categories/${category.id}/toggle`
    ),
    successMessage,
    setError,
    setSuccess
  );
}

function reorderCategory(
  category,
  direction,
  setError,
  setSuccess
) {
  return runCategoryRequest(
    () => window.axios.put(
      `/tillwhite/api/product-categories/${category.id}/reorder`,
      { direction }
    ),
    '카테고리 순서를 변경했습니다.',
    setError,
    setSuccess
  );
}

async function openRegisterDialog(user, can, setError) {
  if (!can('product.manage')) {
    setError('제품을 등록할 권한이 없습니다.');
    return;
  }

  if (productActionLoading.value) return;

  currentUser.value = user;
  productActionLoading.value = 'draft-load';

  try {
    const response = await window.axios.get(
      '/tillwhite/api/products/draft'
    );

    productDraft.value = response.data.draft ?? null;
    registerDialog.value = true;
  } catch (error) {
    setError(
      errorMessage(
        error,
        '제품 등록 정보를 준비하지 못했습니다.'
      )
    );
  } finally {
    productActionLoading.value = null;
  }
}

function requestSaveProductDraft(
  payload,
  setError,
  setSuccess
) {
  if (productActionLoading.value) return;

  confirmDialog.action = 'draft';
  confirmDialog.title = '임시저장하시겠습니까?';
  confirmDialog.message =
    '현재 입력된 제품 등록 내용을 로그인 세션에 임시저장합니다.';
  confirmDialog.confirmText = '임시저장';
  confirmDialog.payload = {
    payload,
    setError,
    setSuccess,
  };
  confirmDialog.open = true;
}

function requestClearProductDraft(setError, setSuccess) {
  if (productActionLoading.value) return;

  confirmDialog.action = 'clear-draft';
  confirmDialog.title = '입력 내용을 전체 삭제하시겠습니까?';
  confirmDialog.message =
    '현재 입력 내용과 로그인 세션에 임시저장된 제품 등록 내용을 모두 삭제합니다.';
  confirmDialog.confirmText = '전체삭제';
  confirmDialog.payload = {
    setError,
    setSuccess,
  };
  confirmDialog.open = true;
}

async function saveProductDraft(
  payload,
  setError,
  setSuccess
) {
  if (productActionLoading.value) return;

  productActionLoading.value = 'draft';

  try {
    const response = await window.axios.put(
      '/tillwhite/api/products/draft',
      payload
    );

    productDraft.value = response.data.draft ?? null;
    clearConfirmDialog(true);
    registerDialog.value = false;

    setSuccess('제품 등록 내용을 임시저장했습니다.');
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '제품 등록 내용을 임시저장하지 못했습니다.'
      )
    );
  } finally {
    productActionLoading.value = null;
  }
}

async function clearProductDraft(setError, setSuccess) {
  if (productActionLoading.value) return;

  productActionLoading.value = 'clear-draft';

  try {
    await window.axios.delete(
      '/tillwhite/api/products/draft'
    );

    productDraft.value = null;
    clearConfirmDialog(true);

    registerDialog.value = false;
    await nextTick();
    registerDialog.value = true;

    setSuccess('제품 등록 입력 내용을 전체 삭제했습니다.');
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '제품 등록 입력 내용을 삭제하지 못했습니다.'
      )
    );
  } finally {
    productActionLoading.value = null;
  }
}

function requestProductCreate(
  payload,
  setError,
  setSuccess
) {
  openConfirm(
    'create',
    '제품을 등록하시겠습니까?',
    `‘${payload.name}’ 제품을 등록합니다.`,
    '등록',
    { payload, setError, setSuccess }
  );
}

async function saveProduct(payload, setError, setSuccess) {
  if (productActionLoading.value) return;

  productActionLoading.value = 'create';

  try {
    await window.axios.post(
      '/tillwhite/api/products',
      payload
    );

    registerDialog.value = false;
    productDraft.value = null;
    clearConfirmDialog(true);

    setSuccess('제품을 등록했습니다.');

    await refreshListAfterAction(
      setError,
      '제품 등록은 완료되었지만 목록을 새로고침하지 못했습니다.'
    );
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '제품 등록에 실패했습니다.'
      )
    );
  } finally {
    productActionLoading.value = null;
  }
}

async function openDetailDialog(product, user, setError) {
  currentUser.value = user;
  selectedProduct.value = null;
  detailDialog.value = false;

  try {
    const response = await window.axios.get(
      `/tillwhite/api/products/${product.id}`
    );

    selectedProduct.value = response.data.product;
    detailDialog.value = true;

    await rememberRecentViewed(product.id);
  } catch (error) {
    setError(
      errorMessage(
        error,
        '제품 정보를 열람할 수 없습니다.'
      )
    );
  }
}

function closeDetailDialog() {
  if (productActionLoading.value) return;

  detailDialog.value = false;
  editDialog.value = false;
}

function clearClosedDetailState() {
  if (!detailDialog.value) {
    selectedProduct.value = null;
  }
}

function openEditDialog() {
  if (!selectedProduct.value) return;

  if (selectedProduct.value.deleted_at) {
    appShellRef.value?.setError?.(
      '삭제된 제품입니다. 복구 후 수정해주세요.'
    );
    return;
  }

  editDialog.value = true;
}

function canManageProduct(product, user, can) {
  if (!product || !can('product.manage')) {
    return false;
  }

  if (user?.role?.code === 'super_admin') {
    return true;
  }

  return Number(user?.store?.id)
    === Number(product.store_id);
}

function canManageSelectedProduct(can) {
  return canManageProduct(
    selectedProduct.value,
    currentUser.value,
    can
  );
}

function canManageSelectedRecipe(can) {
  if (
    !selectedProduct.value
    || !can('recipe.manage')
  ) {
    return false;
  }

  if (currentUser.value?.role?.code === 'super_admin') {
    return true;
  }

  return Number(currentUser.value?.store?.id)
    === Number(selectedProduct.value.store_id);
}

function requestProductEdit(
  payload,
  setError,
  setSuccess
) {
  if (
    !selectedProduct.value
    || productActionLoading.value
  ) return;

  openConfirm(
    'edit',
    '제품 정보를 수정하시겠습니까?',
    `‘${selectedProduct.value.name}’ 제품의 변경 내용을 저장합니다.`,
    '저장',
    { payload, setError, setSuccess }
  );
}

async function saveProductEdit(
  payload,
  setError,
  setSuccess
) {
  if (
    !selectedProduct.value
    || productActionLoading.value
  ) return;

  productActionLoading.value = 'edit';

  try {
    await window.axios.put(
      `/tillwhite/api/products/${selectedProduct.value.id}`,
      payload
    );

    editDialog.value = false;
    clearConfirmDialog(true);

    setSuccess('제품 정보를 수정했습니다.');

    await refreshSelectedProduct(
      setError,
      '제품 수정은 완료되었지만 화면을 새로고침하지 못했습니다.'
    );
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '제품 정보 수정에 실패했습니다.'
      )
    );
  } finally {
    productActionLoading.value = null;
  }
}

function requestCardProductStatus(product, nextActive) {
  if (
    !product
    || product.deleted_at
    || productActionLoading.value
  ) return;

  if (
    Boolean(product.is_active) === Boolean(nextActive)
  ) return;

  selectedProduct.value = product;
  requestProductAction('toggle');
}

function requestProductAction(action) {
  if (
    !selectedProduct.value
    || productActionLoading.value
  ) return;

  const settings = {
    toggle: {
      title: selectedProduct.value.is_active
        ? '제품 취급을 중단하시겠습니까?'
        : '제품 취급을 재개하시겠습니까?',
      message: selectedProduct.value.is_active
        ? `${selectedProduct.value.name} 제품은 과거 기록을 유지한 채 취급중단 상태로 변경됩니다.`
        : `${selectedProduct.value.name} 제품을 다시 취급중 상태로 변경합니다.`,
      confirmText: selectedProduct.value.is_active
        ? '취급중단'
        : '취급재개',
    },
    delete: {
      title: '제품을 삭제하시겠습니까?',
      message: activeSelectedRecipe.value
        ? '이 제품에는 등록된 레시피가 있습니다. 제품을 삭제하면 해당 레시피도 함께 삭제됩니다. 그래도 삭제하시겠습니까?'
        : `${selectedProduct.value.name} 제품을 삭제하시겠습니까? 과거 기록은 보존되며 복구할 수 있습니다.`,
      confirmText: '삭제',
    },
    restore: {
      title: '제품을 복구하시겠습니까?',
      message: selectedProduct.value.recipes?.some(
        recipe =>
          recipe.deleted_at
          && recipe.deletion_source === 'product'
      )
        ? '제품을 복구하면 제품 삭제로 함께 삭제된 레시피도 복구됩니다. 복구하시겠습니까?'
        : `${selectedProduct.value.name} 제품을 제품 목록에 다시 복구합니다.`,
      confirmText: '복구',
    },
  }[action];

  if (!settings) return;

  confirmDialog.action = action;
  confirmDialog.title = settings.title;
  confirmDialog.message = settings.message;
  confirmDialog.confirmText = settings.confirmText;
  confirmDialog.payload = null;
  confirmDialog.open = true;
}

async function executeConfirmedAction(setError, setSuccess) {
  const action = confirmDialog.action;

  if (!action || productActionLoading.value) return;

  if (action === 'create') {
    const saved = confirmDialog.payload;

    if (saved?.payload) {
      await saveProduct(
        saved.payload,
        saved.setError ?? setError,
        saved.setSuccess ?? setSuccess
      );
    }
    return;
  }

  if (action === 'category-create') {
    const saved = confirmDialog.payload;

    if (saved?.payload) {
      await createCategory(
        saved.payload,
        saved.setError ?? setError,
        saved.setSuccess ?? setSuccess
      );
    }

    clearConfirmDialog(true);
    return;
  }

  if (action === 'category-rename') {
    const saved = confirmDialog.payload;

    if (saved?.payload) {
      await renameCategory(
        saved.payload,
        saved.setError ?? setError,
        saved.setSuccess ?? setSuccess
      );
    }

    clearConfirmDialog(true);
    return;
  }

  if (action === 'category-toggle') {
    const saved = confirmDialog.payload;

    if (saved?.category) {
      await toggleCategory(
        saved.category,
        saved.setError ?? setError,
        saved.setSuccess ?? setSuccess
      );
    }

    clearConfirmDialog(true);
    return;
  }

  if (action === 'category-reorder') {
    const saved = confirmDialog.payload;

    if (!saved?.category || !saved?.direction) return;

    clearConfirmDialog(true);

    await reorderCategory(
      saved.category,
      saved.direction,
      saved.setError ?? setError,
      saved.setSuccess ?? setSuccess
    );
    return;
  }

  if (action === 'clone-product-discard') {
    cloneProductDialog.value = false;
    clearConfirmDialog(true);
    return;
  }

  if (action === 'draft') {
    const saved = confirmDialog.payload;

    if (!saved?.payload) return;

    await saveProductDraft(
      saved.payload,
      saved.setError ?? setError,
      saved.setSuccess ?? setSuccess
    );
    return;
  }

  if (action === 'clear-draft') {
    const saved = confirmDialog.payload;

    await clearProductDraft(
      saved?.setError ?? setError,
      saved?.setSuccess ?? setSuccess
    );
    return;
  }

  if (action === 'edit') {
    const saved = confirmDialog.payload;

    if (!saved?.payload) return;

    await saveProductEdit(
      saved.payload,
      saved.setError ?? setError,
      saved.setSuccess ?? setSuccess
    );
    return;
  }

  if (!selectedProduct.value) return;

  productActionLoading.value = action;

  try {
    const id = selectedProduct.value.id;

    if (action === 'toggle') {
      await window.axios.put(
        `/tillwhite/api/products/${id}/toggle`
      );
    } else if (action === 'delete') {
      await window.axios.delete(
        `/tillwhite/api/products/${id}`
      );
    } else if (action === 'restore') {
      await window.axios.put(
        `/tillwhite/api/products/${id}/restore`
      );
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

      await refreshListAfterAction(
        setError,
        '작업은 완료되었지만 제품 목록을 새로고침하지 못했습니다.'
      );
    } else {
      await refreshSelectedProduct(
        setError,
        '상태 변경은 완료되었지만 화면을 새로고침하지 못했습니다.'
      );
    }
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '제품 작업을 완료하지 못했습니다.'
      )
    );
  } finally {
    productActionLoading.value = null;
  }
}

function openCloneProductDialog(fromRecipe = false) {
  if (
    !selectedProduct.value
    || selectedProduct.value.deleted_at
  ) {
    appShellRef.value?.setError?.(
      '삭제된 제품은 복제할 수 없습니다.'
    );
    return;
  }

  cloneFromRecipe.value = fromRecipe;

  cloneWithRecipe.value = fromRecipe
    ? true
    : Boolean(activeSelectedRecipe.value);

  cloneProductName.value =
    `${selectedProduct.value.name ?? ''} 복사본`.trim();

  cloneInitialSnapshot.value = JSON.stringify({
    name: cloneProductName.value,
    copyRecipe: cloneWithRecipe.value,
  });

  cloneProductDialog.value = true;
}

function requestCloseCloneProductDialog() {
  if (productActionLoading.value === 'clone-product') {
    return;
  }

  const currentSnapshot = JSON.stringify({
    name: cloneProductName.value,
    copyRecipe: cloneWithRecipe.value,
  });

  if (
    cloneInitialSnapshot.value
    && currentSnapshot !== cloneInitialSnapshot.value
  ) {
    openConfirm(
      'clone-product-discard',
      '작성을 취소하시겠습니까?',
      '저장하지 않은 새 제품 입력 내용은 사라집니다.',
      '예'
    );
    return;
  }

  cloneProductDialog.value = false;
}

async function createClonedProduct(setError, setSuccess) {
  if (
    !selectedProduct.value
    || productActionLoading.value
  ) return;

  if (!cloneProductName.value.trim()) {
    setError('제품명을 입력해주세요.');
    return;
  }

  productActionLoading.value = 'clone-product';

  try {
    const response = await window.axios.post(
      `/tillwhite/api/products/${selectedProduct.value.id}/clone`,
      {
        name: cloneProductName.value.trim(),
        copy_recipe: cloneFromRecipe.value
          ? true
          : cloneWithRecipe.value,
      }
    );

    cloneProductDialog.value = false;

    setSuccess(
      response.data.message ?? '새 제품을 만들었습니다.'
    );

    await load();

    const created = products.value.find(
      item =>
        Number(item.id) === Number(response.data.product_id)
    );

    if (created) {
      await openDetailDialog(
        created,
        currentUser.value,
        setError
      );
    }
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '새 제품을 만들지 못했습니다.'
      )
    );
  } finally {
    productActionLoading.value = null;
  }
}

async function rememberRecentViewed(productId) {
  try {
    const response = await window.axios.post(
      '/tillwhite/api/products/recent-viewed',
      { id: productId }
    );

    recentViewed.value =
      response.data.items ?? recentViewed.value;
  } catch (_) {
    // 최근 본 제품 기록 실패는 상세 조회를 막지 않습니다.
  }
}

async function openRecentItem(item, user, setError) {
  const product = products.value.find(
    candidate =>
      Number(candidate.id) === Number(item.id)
  );

  if (!product) {
    setError(
      '최근 본 제품을 더 이상 열람할 수 없습니다.'
    );
    return;
  }

  await openDetailDialog(product, user, setError);
}

function focusProductInfo() {
  document
    .querySelector(
      '.product-detail-dialog .detail-scroll-area'
    )
    ?.scrollTo({
      top: 0,
      behavior: 'smooth',
    });
}

async function refreshSelectedProduct(
  setError,
  failureMessage
) {
  const productId = selectedProduct.value?.id;

  try {
    await load();

    if (productId && detailDialog.value) {
      const response = await window.axios.get(
        `/tillwhite/api/products/${productId}`
      );

      selectedProduct.value = response.data.product;
    }
  } catch (error) {
    setError(failureMessage);
  }
}

async function refreshListAfterAction(
  setError,
  failureMessage
) {
  try {
    await load();
  } catch (error) {
    setError(failureMessage);
  }
}

async function handlePageChange(nextPage) {
  const oldTop =
    paginationRef.value?.getBoundingClientRect().top ?? null;

  currentPage.value = nextPage;
  await nextTick();

  if (oldTop === null || !paginationRef.value) return;

  const newTop =
    paginationRef.value.getBoundingClientRect().top;

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

function openConfirm(
  action,
  title,
  message,
  confirmText = '예',
  payload = null
) {
  if (productActionLoading.value) return;

  confirmDialog.action = action;
  confirmDialog.title = title;
  confirmDialog.message = message;
  confirmDialog.confirmText = confirmText;
  confirmDialog.payload = payload;
  confirmDialog.open = true;
}

function clearConfirmDialog(force = false) {
  if (productActionLoading.value && !force) return;

  confirmDialog.open = false;
  confirmDialog.action = null;
  confirmDialog.title = '';
  confirmDialog.message = '';
  confirmDialog.confirmText = '예';
  confirmDialog.payload = null;
}

function closeDetailDialogAfterAction() {
  detailDialog.value = false;
  editDialog.value = false;
}

function errorMessage(error, fallback) {
  return error.response?.data?.message ?? fallback;
}

function requestFailureMessage(error, fallback) {
  if (!error.response) {
    return '서버 응답을 확인하지 못했습니다. 작업이 이미 반영되었을 수 있으므로 목록을 다시 확인해주세요.';
  }

  const validationErrors = error.response?.data?.errors;

  if (
    validationErrors
    && typeof validationErrors === 'object'
  ) {
    const firstMessage = Object.values(
      validationErrors
    )
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
.product-primary-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.product-primary-action {
  min-width: 0;
  min-height: 44px;
  height: auto;
  padding-block: 8px;
}

.product-primary-action :deep(.v-btn__content) {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px 6px;
  white-space: normal;
}

.product-primary-action-label {
  min-width: 0;
  overflow-wrap: anywhere;
  text-align: center;
}

.recent-viewed {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.recent-viewed-chip {
  max-width: min(240px, 100%);
}

.recent-viewed-chip :deep(.v-chip__content) {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.recent-viewed-label {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.76rem;
  font-weight: 700;
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
  justify-content: center;
  gap: 2px;
  margin: 0;
  padding: 0;
}

.product-pagination :deep(.v-pagination__list > li) {
  flex: 0 0 auto;
}

.product-pagination :deep(.v-btn) {
  flex: 0 0 36px;
  width: 36px;
  min-width: 36px;
  height: 36px;
}
</style>