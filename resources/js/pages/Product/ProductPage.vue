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

          <v-chip
            v-if="!can('product.manage')"
            class="ml-2"
            size="x-small"
            variant="tonal"
          >
            권한 없음
          </v-chip>
        </v-btn>

        <v-btn
          class="product-primary-action"
          block
          prepend-icon="mdi-shape-plus-outline"
          variant="flat"
          @click="openCategoryDialog(can, setError)"
        >
          <span class="product-primary-action-label">카테고리 관리</span>

          <v-chip
            v-if="!can('product.manage')"
            class="ml-2"
            size="x-small"
            variant="tonal"
          >
            권한 없음
          </v-chip>
        </v-btn>
      </div>

      <!-- 검색 / 필터: 제품 전용 컴포넌트로 분리하여 페이지 책임을 단순화합니다. -->
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

      <!-- 제품 카드 목록 -->
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
        @toggle="requestProductAction('toggle')"
        @delete="requestProductAction('delete')"
        @restore="requestProductAction('restore')"
        @recipe="openRecipeDialog"
        @recipe-part="openRecipePartDialog"
        @recipe-copy="openRecipeCopyDialog"
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

      <v-dialog
        v-model="recipeCopyDialog"
        max-width="460"
        persistent
      >
        <v-card rounded="lg">
          <v-card-title class="pa-5 pb-2">레시피 복사</v-card-title>
          <v-card-text class="px-5">
            <div class="text-body-2 text-medium-emphasis mb-4">
              원본은 그대로 유지하고 같은 점포의 다른 제품에 새 레시피를 만듭니다.
            </div>
            <v-select
              v-model="recipeCopyTargetId"
              :items="recipeCopyTargets"
              item-title="name"
              item-value="id"
              label="복사할 제품"
              variant="outlined"
              :disabled="productActionLoading === 'recipe-copy'"
            />
          </v-card-text>
          <v-card-actions class="px-5 pb-4">
            <v-btn
              variant="text"
              :disabled="productActionLoading === 'recipe-copy'"
              @click="recipeCopyDialog = false"
            >
              취소
            </v-btn>
            <v-spacer />
            <v-btn
              variant="flat"
              :disabled="!recipeCopyTargetId"
              :loading="productActionLoading === 'recipe-copy'"
              @click="requestRecipeCopy(setError, setSuccess)"
            >
              복사
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- 삭제/복구/취급상태/중요 수정 확인 -->
      <ConfirmDialog
        v-model="confirmDialog.open"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :loading="Boolean(productActionLoading)"
        @confirm="executeConfirmedAction(setError, setSuccess)"
        @cancel="clearConfirmDialog"
      />

      <!-- 레시피는 전체 수정과 재료/공정 단위의 빠른 수정을 같은 데이터 흐름으로 처리합니다. -->
      <v-dialog
        v-model="recipeDialog"
        max-width="620"
        persistent
        @after-leave="resetRecipeDialogState"
      >
        <v-card class="recipe-dialog" rounded="lg">
          <div class="recipe-dialog-header">
            <div class="recipe-dialog-header-icon">
              <v-icon :icon="recipeDialogIcon" size="22" />
            </div>
            <div class="min-width-0">
              <div class="text-h6 font-weight-bold">{{ recipeDialogTitle }}</div>
              <div class="recipe-dialog-description text-medium-emphasis mt-1">{{ recipeDialogSubtitle }}</div>
            </div>
          </div>

          <v-divider />

          <div class="recipe-scroll">
            <v-alert class="recipe-required-guide mb-5" type="info" variant="tonal" density="compact">
              * 표시는 필수 입력 항목입니다. 재료와 공정은 저장된 순서대로 표시됩니다.
            </v-alert>

            <section v-if="recipeEditMode === 'full' || recipeEditMode === 'basic'" class="recipe-form-section recipe-form-section--basic">
              <div class="recipe-form-section-title"><v-icon icon="mdi-notebook-outline" size="18" />기본 내용</div>
              <v-text-field v-model="recipe.name" label="레시피명 *" variant="outlined" />
              <v-textarea v-model="recipe.description" label="설명" variant="outlined" auto-grow rows="2" />
            </section>

            <section v-if="recipeEditMode === 'full' || recipeEditMode === 'ingredient'" class="recipe-form-section">
              <div class="recipe-form-section-title"><v-icon icon="mdi-scale-balance" size="18" />재료</div>
              <div class="recipe-form-hint">{{ ingredientGuideText }}</div>

              <div class="recipe-builder-list">
                <div
                  v-for="(ingredient, index) in visibleRecipeIngredients"
                  :key="ingredient._key"
                  class="recipe-builder-item"
                >
                  <div class="recipe-builder-number">
                    {{ String(actualIngredientIndex(index) + 1).padStart(2, '0') }}
                  </div>
                  <div class="recipe-ingredient-fields">
                    <v-text-field
                      v-model="ingredient.name"
                      label="재료명 *"
                      variant="outlined"
                      density="comfortable"
                      hide-details
                    />

                    <v-text-field
                      v-model="ingredient.quantity"
                      label="사용량"
                      type="number"
                      inputmode="decimal"
                      step="any"
                      min="0"
                      variant="outlined"
                      density="comfortable"
                      hide-details
                    />

                    <v-select
                      v-if="ingredient.unitChoice !== CUSTOM_UNIT"
                      v-model="ingredient.unitChoice"
                      :items="unitItems"
                      label="단위"
                      variant="outlined"
                      density="comfortable"
                      hide-details
                      @update:model-value="applyUnitChoice(ingredient)"
                    />

                    <v-text-field
                      v-else
                      v-model="ingredient.unit"
                      label="단위 직접입력"
                      placeholder="예: 꼬집"
                      variant="outlined"
                      density="comfortable"
                      hide-details
                      append-inner-icon="mdi-menu-down"
                      @click:append-inner="useUnitSelect(ingredient)"
                    />
                  </div>
                  <v-btn
                    v-if="recipeEditMode === 'full'"
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    aria-label="재료 삭제"
                    @click="requestRemoveRecipeIngredient(actualIngredientIndex(index))"
                  />
                </div>
              </div>

              <v-btn
                v-if="recipeEditMode === 'full'"
                class="mt-3"
                block
                variant="tonal"
                prepend-icon="mdi-plus"
                @click="addRecipeIngredient"
              >
                재료 추가
              </v-btn>
            </section>

            <section v-if="recipeEditMode === 'full' || recipeEditMode === 'step'" class="recipe-form-section">
              <div class="recipe-form-section-title"><v-icon icon="mdi-format-list-numbered" size="18" />공정</div>
              <div class="recipe-form-hint">{{ stepGuideText }}</div>

              <div class="recipe-builder-list">
                <div
                  v-for="(step, index) in visibleRecipeSteps"
                  :key="step._key"
                  class="recipe-builder-item recipe-builder-item--step"
                >
                  <div class="recipe-builder-number">
                    {{ String(actualStepIndex(index) + 1).padStart(2, '0') }}
                  </div>
                  <v-textarea v-model="step.description" label="공정 내용 *" variant="outlined" density="comfortable" auto-grow rows="3" hide-details />
                  <div v-if="recipeEditMode === 'full'" class="recipe-step-actions">
                    <v-btn
                      icon="mdi-chevron-up"
                      size="small"
                      variant="text"
                      :disabled="actualStepIndex(index) === 0"
                      aria-label="공정 위로 이동"
                      @click="moveRecipeStep(actualStepIndex(index), -1)"
                    />
                    <v-btn
                      icon="mdi-chevron-down"
                      size="small"
                      variant="text"
                      :disabled="actualStepIndex(index) === recipe.steps.length - 1"
                      aria-label="공정 아래로 이동"
                      @click="moveRecipeStep(actualStepIndex(index), 1)"
                    />
                    <v-btn icon="mdi-close" size="small" variant="text" aria-label="공정 삭제" @click="requestRemoveRecipeStep(actualStepIndex(index))" />
                  </div>
                </div>
              </div>

              <v-btn
                v-if="recipeEditMode === 'full'"
                class="mt-3"
                block
                variant="tonal"
                prepend-icon="mdi-plus"
                @click="addRecipeStep"
              >
                공정 추가
              </v-btn>
            </section>

            <section
              v-if="editingRecipeId"
              class="recipe-form-section"
            >
              <div class="recipe-form-section-title">
                <v-icon icon="mdi-clock-outline" size="18" />
                시스템 정보
              </div>
              <div class="recipe-system-grid">
                <span>등록자</span><strong>{{ recipeHistoryActor(recipeManagementInfo?.management_history?.created) }}</strong>
                <span>등록일</span><strong>{{ recipeHistoryAt(recipeManagementInfo?.management_history?.created, recipeManagementInfo?.created_at) }}</strong>
                <span>수정자</span><strong>{{ recipeHistoryActor(recipeManagementInfo?.management_history?.updated) }}</strong>
                <span>수정일</span><strong>{{ recipeHistoryAt(recipeManagementInfo?.management_history?.updated, recipeManagementInfo?.updated_at) }}</strong>
                <span>삭제자</span><strong>{{ recipeHistoryActor(recipeManagementInfo?.management_history?.deleted) }}</strong>
                <span>삭제일</span><strong>{{ recipeHistoryAt(recipeManagementInfo?.management_history?.deleted, recipeManagementInfo?.deleted_at) }}</strong>
              </div>
            </section>
          </div>

          <v-divider />

          <div class="recipe-dialog-actions">
            <v-btn variant="text" prepend-icon="mdi-close" :disabled="productActionLoading === 'recipe'" @click="requestCloseRecipeDialog">취소</v-btn>
            <v-spacer />
            <v-btn
              variant="flat"
              prepend-icon="mdi-content-save-outline"
              :loading="productActionLoading === 'recipe'"
              :disabled="!canSaveRecipe"
              @click="requestRecipeSave(setError, setSuccess)"
            >
              {{ editingRecipeId ? '저장' : '등록' }}
            </v-btn>
          </div>
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
import ProductSearchFilter from '../../components/product/ProductSearchFilter.vue';
import ProductCategoryDialog from '../../components/product/ProductCategoryDialog.vue';
import ProductDetailDialog from '../../components/product/ProductDetailDialog.vue';
import ProductFormDialog from '../../components/product/ProductFormDialog.vue';
import { useAppLoading } from '../../composables/useAppLoading';
import { compareDisplayName } from '../../utils/naturalSort';

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
const productDraft = ref(null);

/** 다이얼로그 상태 */
const registerDialog = ref(false);
const detailDialog = ref(false);
const editDialog = ref(false);
const recipeDialog = ref(false);
const categoryDialog = ref(false);
const recipeCopyDialog = ref(false);
const recipeCopyTargetId = ref(null);

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
let recipeRowKey = 0;
const recipe = reactive(createEmptyRecipe());
const editingRecipeId = ref(null);
const recipeEditMode = ref('full');
const recipeEditIndex = ref(null);
const recipeInitialSnapshot = ref('');

const CUSTOM_UNIT = '__custom__';
const DEFAULT_UNITS = ['g', 'kg', 'ml', 'L', '개', '장', '봉', '팩', '병', '캔', '스푼', '작은술', '큰술'];
const recentUnits = ref([]);

const unitItems = computed(() => {
  const units = [...new Set([...recentUnits.value, ...DEFAULT_UNITS])];
  return [
    ...units.map((unit) => ({ title: unit, value: unit })),
    { title: '직접입력', value: CUSTOM_UNIT },
  ];
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

const recipeDialogTitle = computed(() => {
  if (!editingRecipeId.value) return '레시피 등록';
  if (recipeEditMode.value === 'ingredient') return '재료 수정';
  if (recipeEditMode.value === 'step') return '공정 수정';
  if (recipeEditMode.value === 'basic') return '레시피 기본 내용 수정';
  return '레시피 수정';
});

const recipeDialogSubtitle = computed(() => {
  if (recipeEditMode.value === 'ingredient') return '선택한 재료만 빠르게 수정합니다.';
  if (recipeEditMode.value === 'step') return '선택한 공정 단계만 빠르게 수정합니다.';
  if (recipeEditMode.value === 'basic') return '레시피명과 설명을 수정합니다.';
  return '재료와 공정을 순서대로 추가해 레시피를 완성합니다.';
});

const ingredientGuideText = computed(() => {
  if (recipeEditMode.value === 'ingredient') {
    return '선택한 재료의 이름, 사용량, 단위를 확인하고 수정해주세요.';
  }

  const count = meaningfulIngredients().length;
  return count
    ? `현재 재료 ${count}개가 등록되어 있습니다. 필요한 재료를 추가하거나 수정해주세요.`
    : '재료를 하나씩 추가해 레시피를 완성해보세요.';
});

const stepGuideText = computed(() => {
  if (recipeEditMode.value === 'step') {
    return '선택한 공정의 작업 내용을 확인하고 수정해주세요.';
  }

  const count = meaningfulSteps().length;
  return count
    ? `현재 공정 ${count}단계가 등록되어 있습니다. 순서를 확인하거나 필요한 공정을 추가해주세요.`
    : '공정을 하나씩 추가하면 순서 번호는 자동으로 정리됩니다.';
});

const recipeManagementInfo = computed(() => {
  return selectedProduct.value?.recipes?.find(
    (item) => Number(item.id) === Number(editingRecipeId.value),
  ) ?? null;
});

const recipeDialogIcon = computed(() => {
  if (recipeEditMode.value === 'ingredient') return 'mdi-scale-balance';
  if (recipeEditMode.value === 'step') return 'mdi-format-list-numbered';
  return editingRecipeId.value ? 'mdi-notebook-edit-outline' : 'mdi-notebook-plus-outline';
});

const visibleRecipeIngredients = computed(() => {
  if (recipeEditMode.value !== 'ingredient') return recipe.ingredients;
  const item = recipe.ingredients[recipeEditIndex.value];
  return item ? [item] : [];
});

const visibleRecipeSteps = computed(() => {
  if (recipeEditMode.value !== 'step') return recipe.steps;
  const item = recipe.steps[recipeEditIndex.value];
  return item ? [item] : [];
});

const canSaveRecipe = computed(() => {
  if (!recipe.name.trim()) return false;

  const ingredients = meaningfulIngredients();
  const steps = meaningfulSteps();

  if (ingredients.some((item) => !item.name.trim())) return false;
  if (ingredients.some((item) => item.quantity !== '' && item.quantity !== null && Number(item.quantity) < 0)) return false;
  if (steps.some((step) => !step.description.trim())) return false;

  return productActionLoading.value !== 'recipe';
});

const recipeCopyTargets = computed(() => {
  if (!selectedProduct.value) return [];

  return products.value
    .filter((product) => (
      Number(product.store_id) === Number(selectedProduct.value.store_id)
      && Number(product.id) !== Number(selectedProduct.value.id)
      && !product.deleted_at
      && !(product.recipes?.length)
    ))
    .sort((a, b) => compareDisplayName(a.name, b.name));
});

const hasRecipeChanges = computed(() => {
  return Boolean(recipeInitialSnapshot.value)
    && recipeSnapshot() !== recipeInitialSnapshot.value;
});

const categoryFilterItems = computed(() => {
  const source = storeFilter.value === 'all'
    ? categories.value
    : categories.value.filter((category) => Number(category.store_id) === Number(storeFilter.value));

  const names = [...new Set(source.map((category) => category.name).filter(Boolean))]
    .sort(compareDisplayName);

  return [
    { title: '전체', value: 'all' },
    ...names.map((name) => ({ title: name, value: name })),
  ];
});

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
      || String(product.category?.name ?? '') === String(categoryFilter.value);

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

function openCategoryDialog(can, setError) {
  if (!can('product.manage')) {
    setError('카테고리를 관리할 권한이 없습니다.');
    return;
  }

  categoryDialog.value = true;
}

/** 카테고리 변경은 실제 API 요청 전에 공통 확인창을 한 번만 거칩니다. */
function requestCategoryCreate(payload, setError, setSuccess) {
  openConfirm(
    'category-create',
    '카테고리를 등록하시겠습니까?',
    `‘${payload.name}’ 카테고리를 등록합니다.`,
    '예',
    { payload, setError, setSuccess },
  );
}

function requestCategoryRename(payload, setError, setSuccess) {
  openConfirm(
    'category-rename',
    '카테고리명을 수정하시겠습니까?',
    `‘${payload.category.name}’ → ‘${payload.name}’으로 변경합니다.`,
    '예',
    { payload, setError, setSuccess },
  );
}

function requestCategoryToggle(category, setError, setSuccess) {
  const actionText = category.is_active ? '사용을 중단' : '다시 사용';

  openConfirm(
    'category-toggle',
    `카테고리를 ${actionText}하시겠습니까?`,
    `‘${category.name}’ 카테고리를 ${actionText}합니다. 기존 제품과 기록은 유지됩니다.`,
    '예',
    { category, setError, setSuccess },
  );
}

/** 관리 화면의 수동 순서 변경도 다른 영속 변경과 동일한 확인 흐름을 사용합니다. */
function requestCategoryReorder({ category, direction }, setError, setSuccess) {
  const directionText = direction < 0 ? '위로' : '아래로';

  openConfirm(
    'category-reorder',
    '카테고리 순서를 변경하시겠습니까?',
    `‘${category.name}’ 카테고리를 한 칸 ${directionText} 이동합니다.`,
    '예',
    { category, direction, setError, setSuccess },
  );
}

async function runCategoryRequest(
  request,
  successMessage,
  setError,
  setSuccess,
) {
  if (productActionLoading.value) {
    return;
  }

  productActionLoading.value = 'category';

  try {
    await request();
    setSuccess(successMessage);
    await load();
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '카테고리 처리에 실패했습니다.',
      ),
    );
  } finally {
    productActionLoading.value = null;
  }
}

function createCategory(payload, setError, setSuccess) {
  return runCategoryRequest(
    () => window.axios.post(
      '/tillwhite/api/product-categories',
      payload,
    ),
    '카테고리를 등록했습니다.',
    setError,
    setSuccess,
  );
}

function renameCategory({ category, name }, setError, setSuccess) {
  return runCategoryRequest(
    () => window.axios.put(
      `/tillwhite/api/product-categories/${category.id}`,
      { name },
    ),
    '카테고리를 수정했습니다.',
    setError,
    setSuccess,
  );
}

function toggleCategory(category, setError, setSuccess) {
  const successMessage = category.is_active
    ? '카테고리 사용을 중단했습니다.'
    : '카테고리를 다시 사용합니다.';

  return runCategoryRequest(
    () => window.axios.put(
      `/tillwhite/api/product-categories/${category.id}/toggle`,
    ),
    successMessage,
    setError,
    setSuccess,
  );
}

function reorderCategory(category, direction, setError, setSuccess) {
  return runCategoryRequest(
    () => window.axios.put(
      `/tillwhite/api/product-categories/${category.id}/reorder`,
      { direction },
    ),
    '카테고리 순서를 변경했습니다.',
    setError,
    setSuccess,
  );
}

/** 제품 등록 권한과 Laravel Session draft를 확인한 뒤 등록창을 엽니다. */
async function openRegisterDialog(user, can, setError) {
  if (!can('product.manage')) {
    setError('제품을 등록할 권한이 없습니다.');
    return;
  }

  if (productActionLoading.value) {
    return;
  }

  currentUser.value = user;
  productActionLoading.value = 'draft-load';

  try {
    const response = await window.axios.get(
      '/tillwhite/api/products/draft',
    );

    productDraft.value = response.data.draft ?? null;
    registerDialog.value = true;
  } catch (error) {
    setError(
      errorMessage(
        error,
        '제품 등록 정보를 준비하지 못했습니다.',
      ),
    );
  } finally {
    productActionLoading.value = null;
  }
}

/** 제품 등록 draft 저장 전 직원관리와 동일하게 공통 확인창을 표시합니다. */
function requestSaveProductDraft(payload, setError, setSuccess) {
  if (productActionLoading.value) {
    return;
  }

  confirmDialog.action = 'draft';
  confirmDialog.title = '임시저장하시겠습니까?';
  confirmDialog.message = '현재 입력된 제품 등록 내용을 로그인 세션에 임시저장합니다.';
  confirmDialog.confirmText = '임시저장';
  confirmDialog.payload = {
    payload,
    setError,
    setSuccess,
  };
  confirmDialog.open = true;
}

/** 제품 등록 draft 전체삭제 전 공통 확인창을 표시합니다. */
function requestClearProductDraft(setError, setSuccess) {
  if (productActionLoading.value) {
    return;
  }

  confirmDialog.action = 'clear-draft';
  confirmDialog.title = '입력 내용을 전체 삭제하시겠습니까?';
  confirmDialog.message = '현재 입력 내용과 로그인 세션에 임시저장된 제품 등록 내용을 모두 삭제합니다.';
  confirmDialog.confirmText = '전체삭제';
  confirmDialog.payload = {
    setError,
    setSuccess,
  };
  confirmDialog.open = true;
}

/** 현재 제품 등록 내용을 Laravel Session에 임시저장합니다. */
async function saveProductDraft(payload, setError, setSuccess) {
  if (productActionLoading.value) {
    return;
  }

  productActionLoading.value = 'draft';

  try {
    const response = await window.axios.put(
      '/tillwhite/api/products/draft',
      payload,
    );

    productDraft.value = response.data.draft ?? null;
    clearConfirmDialog(true);
    registerDialog.value = false;
    setSuccess('제품 등록 내용을 임시저장했습니다.');
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '제품 등록 내용을 임시저장하지 못했습니다.',
      ),
    );
  } finally {
    productActionLoading.value = null;
  }
}

/** Laravel Session draft와 현재 등록 폼을 함께 초기화합니다. */
async function clearProductDraft(setError, setSuccess) {
  if (productActionLoading.value) {
    return;
  }

  productActionLoading.value = 'clear-draft';

  try {
    await window.axios.delete(
      '/tillwhite/api/products/draft',
    );

    productDraft.value = null;
    clearConfirmDialog(true);

    // modelValue를 다시 열어 폼이 빈 draft를 기준으로 확실히 초기화되게 합니다.
    registerDialog.value = false;
    await nextTick();
    registerDialog.value = true;

    setSuccess('제품 등록 입력 내용을 전체 삭제했습니다.');
  } catch (error) {
    setError(
      requestFailureMessage(
        error,
        '제품 등록 입력 내용을 삭제하지 못했습니다.',
      ),
    );
  } finally {
    productActionLoading.value = null;
  }
}

/** 제품 등록은 서버 반영 직전에 한 번 확인하여 오등록을 방지합니다. */
function requestProductCreate(payload, setError, setSuccess) {
  openConfirm('create', '제품을 등록하시겠습니까?', `‘${payload.name}’ 제품을 등록합니다.`, '등록', { payload, setError, setSuccess });
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
    productDraft.value = null;
    clearConfirmDialog(true);
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
  if (!selectedProduct.value) return;

  if (selectedProduct.value.deleted_at) {
    appShellRef.value?.setError?.('삭제된 제품입니다. 복구 후 수정해주세요.');
    return;
  }

  editDialog.value = true;
}

/**
 * 프론트의 버튼 표시를 위한 보조 검사입니다.
 * 실제 수정 가능 여부는 Laravel에서 다시 검사합니다.
 */
function canManageProduct(product, user, can) {
  if (!product || !can('product.manage')) {
    return false;
  }

  if (user?.role?.code === 'super_admin') {
    return true;
  }

  return Number(user?.store?.id) === Number(product.store_id);
}

function canManageSelectedProduct(can) {
  return canManageProduct(selectedProduct.value, currentUser.value, can);
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
  if (!selectedProduct.value || productActionLoading.value) return;

  openConfirm(
    'edit',
    '제품 정보를 수정하시겠습니까?',
    `‘${selectedProduct.value.name}’ 제품의 변경 내용을 저장합니다.`,
    '저장',
    { payload, setError, setSuccess },
  );
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

/**
 * 제품 카드의 상태 선택도 상세 화면과 같은 확인/API 흐름을 사용합니다.
 * 현재 상태를 다시 선택한 경우에는 아무 요청도 보내지 않습니다.
 */
function requestCardProductStatus(product, nextActive) {
  if (!product || product.deleted_at || productActionLoading.value) {
    return;
  }

  if (Boolean(product.is_active) === Boolean(nextActive)) {
    return;
  }

  selectedProduct.value = product;
  requestProductAction('toggle');
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
      message: (
        `${selectedProduct.value.name} 제품은 목록에서 삭제 상태가 되지만 `
        + '과거 생산·폐기·가격·레시피 기록은 보존되며 복구할 수 있습니다.'
      ),
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

  if (action === 'create') {
    const saved = confirmDialog.payload;
    if (saved?.payload) await saveProduct(saved.payload, saved.setError ?? setError, saved.setSuccess ?? setSuccess);
    return;
  }

  if (action === 'category-create') {
    const saved = confirmDialog.payload;
    if (saved?.payload) await createCategory(saved.payload, saved.setError ?? setError, saved.setSuccess ?? setSuccess);
    clearConfirmDialog(true);
    return;
  }

  if (action === 'category-rename') {
    const saved = confirmDialog.payload;
    if (saved?.payload) await renameCategory(saved.payload, saved.setError ?? setError, saved.setSuccess ?? setSuccess);
    clearConfirmDialog(true);
    return;
  }

  if (action === 'category-toggle') {
    const saved = confirmDialog.payload;
    if (saved?.category) await toggleCategory(saved.category, saved.setError ?? setError, saved.setSuccess ?? setSuccess);
    clearConfirmDialog(true);
    return;
  }

  if (action === 'category-reorder') {
    const saved = confirmDialog.payload;
    if (!saved?.category || !saved?.direction) return;
    clearConfirmDialog(true);
    await reorderCategory(saved.category, saved.direction, saved.setError ?? setError, saved.setSuccess ?? setSuccess);
    return;
  }
  if (action === 'recipe-remove-ingredient') {
    removeRecipeIngredient(confirmDialog.payload?.index);
    clearConfirmDialog(true);
    return;
  }

  if (action === 'recipe-remove-step') {
    removeRecipeStep(confirmDialog.payload?.index);
    clearConfirmDialog(true);
    return;
  }

  if (action === 'recipe-discard') {
    closeRecipeDialog();
    await nextTick();
    clearConfirmDialog(true);
    return;
  }

  if (action === 'recipe-save') {
    const saved = confirmDialog.payload;
    await saveRecipe(saved?.setError ?? setError, saved?.setSuccess ?? setSuccess);
    clearConfirmDialog(true);
    return;
  }

  if (action === 'recipe-copy') {
    const saved = confirmDialog.payload;
    await copyRecipe(saved?.setError ?? setError, saved?.setSuccess ?? setSuccess);
    clearConfirmDialog(true);
    return;
  }

  if (action === 'draft') {
    const saved = confirmDialog.payload;

    if (!saved?.payload) {
      return;
    }

    await saveProductDraft(
      saved.payload,
      saved.setError ?? setError,
      saved.setSuccess ?? setSuccess,
    );
    return;
  }

  if (action === 'clear-draft') {
    const saved = confirmDialog.payload;

    await clearProductDraft(
      saved?.setError ?? setError,
      saved?.setSuccess ?? setSuccess,
    );
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

function openRecipeCopyDialog() {
  if (!selectedProduct.value || selectedProduct.value.deleted_at) {
    appShellRef.value?.setError?.('삭제된 제품입니다. 복구 후 레시피를 관리해주세요.');
    return;
  }

  if (!selectedProduct.value.recipes?.[0]) return;

  recipeCopyTargetId.value = null;
  recipeCopyDialog.value = true;
}

function requestRecipeCopy(setError, setSuccess) {
  if (!recipeCopyTargetId.value) return;

  const target = recipeCopyTargets.value.find(
    (product) => Number(product.id) === Number(recipeCopyTargetId.value),
  );

  openConfirm(
    'recipe-copy',
    '레시피를 복사하시겠습니까?',
    `현재 레시피를 ‘${target?.name ?? '-'}’ 제품에 새 레시피로 복사합니다.`,
    '예',
    { setError, setSuccess },
  );
}

async function copyRecipe(setError, setSuccess) {
  const sourceRecipe = selectedProduct.value?.recipes?.[0];
  if (!sourceRecipe || !recipeCopyTargetId.value || productActionLoading.value) return;

  productActionLoading.value = 'recipe-copy';

  try {
    await window.axios.post(
      `/tillwhite/api/products/${selectedProduct.value.id}/recipes/${sourceRecipe.id}/copy`,
      { target_product_id: recipeCopyTargetId.value },
    );

    recipeCopyDialog.value = false;
    recipeCopyTargetId.value = null;
    setSuccess('레시피를 복사했습니다.');
    await refreshListAfterAction(setError, '복사는 완료되었지만 제품 목록을 새로고침하지 못했습니다.');
  } catch (error) {
    setError(requestFailureMessage(error, '레시피 복사에 실패했습니다.'));
  } finally {
    productActionLoading.value = null;
  }
}

/** 레시피 전체 등록/수정 화면을 엽니다. */
function openRecipeDialog() {
  if (!selectedProduct.value) return;

  if (selectedProduct.value.deleted_at) {
    appShellRef.value?.setError?.('삭제된 제품입니다. 복구 후 레시피를 관리해주세요.');
    return;
  }

  const existingRecipe = selectedProduct.value.recipes?.[0] ?? null;
  editingRecipeId.value = existingRecipe?.id ?? null;
  recipeEditMode.value = 'full';
  recipeEditIndex.value = null;
  Object.assign(recipe, existingRecipe ? createRecipeForm(existingRecipe) : createEmptyRecipe());
  recipeInitialSnapshot.value = recipeSnapshot();
  recipeDialog.value = true;
}

/** 상세 카드에서 선택한 재료/공정/기본 내용만 빠르게 수정합니다. */
function openRecipePartDialog(part) {
  if (selectedProduct.value?.deleted_at) {
    appShellRef.value?.setError?.('삭제된 제품입니다. 복구 후 레시피를 관리해주세요.');
    return;
  }

  const existingRecipe = selectedProduct.value?.recipes?.[0] ?? null;
  if (!existingRecipe || !part?.type) return;

  editingRecipeId.value = existingRecipe.id;
  recipeEditMode.value = part.type;
  recipeEditIndex.value = Number.isInteger(part.index) ? part.index : null;
  Object.assign(recipe, createRecipeForm(existingRecipe));
  recipeInitialSnapshot.value = recipeSnapshot();
  recipeDialog.value = true;
}

function actualIngredientIndex(index) {
  return recipeEditMode.value === 'ingredient' ? recipeEditIndex.value : index;
}

function actualStepIndex(index) {
  return recipeEditMode.value === 'step' ? recipeEditIndex.value : index;
}

function addRecipeIngredient() {
  recipe.ingredients.push(createIngredientRow());
}

function removeRecipeIngredient(index) {
  recipe.ingredients.splice(index, 1);
}

function requestRemoveRecipeIngredient(index) {
  const item = recipe.ingredients[index];
  if (!item) return;

  if (!item.name.trim() && (item.quantity === null || item.quantity === '') && !item.unit.trim()) {
    removeRecipeIngredient(index);
    return;
  }

  openConfirm('recipe-remove-ingredient', '재료를 삭제하시겠습니까?', `‘${item.name || `재료 ${index + 1}`}’ 항목을 현재 편집 내용에서 삭제합니다.`, '삭제', { index });
}

function addRecipeStep() {
  recipe.steps.push(createStepRow());
}

function removeRecipeStep(index) {
  recipe.steps.splice(index, 1);
}

function requestRemoveRecipeStep(index) {
  const step = recipe.steps[index];
  if (!step) return;

  if (!step.description.trim()) {
    removeRecipeStep(index);
    return;
  }

  openConfirm('recipe-remove-step', '공정을 삭제하시겠습니까?', `공정 ${index + 1}의 입력 내용을 삭제합니다.`, '삭제', { index });
}

function moveRecipeStep(index, direction) {
  const target = index + direction;
  if (target < 0 || target >= recipe.steps.length) return;
  const [step] = recipe.steps.splice(index, 1);
  recipe.steps.splice(target, 0, step);
}

function requestCloseRecipeDialog() {
  if (productActionLoading.value === 'recipe') return;

  if (!hasRecipeChanges.value) {
    closeRecipeDialog();
    return;
  }

  openConfirm(
    'recipe-discard',
    '레시피 수정을 취소하시겠습니까?',
    '저장하지 않은 레시피 변경 내용은 사라집니다.',
    '예',
  );
}

function requestRecipeSave(setError, setSuccess) {
  if (!canSaveRecipe.value) return;

  openConfirm(
    'recipe-save',
    editingRecipeId.value ? '레시피를 수정하시겠습니까?' : '레시피를 등록하시겠습니까?',
    '현재 입력한 레시피 내용을 저장합니다.',
    editingRecipeId.value ? '저장' : '등록',
    { setError, setSuccess },
  );
}

function closeRecipeDialog() {
  // 퇴장 애니메이션 중 편집 모드를 바꾸면 다른 레시피 화면이 순간적으로 보일 수 있습니다.
  // 실제 상태 초기화는 @after-leave에서 처리합니다.
  recipeDialog.value = false;
}

function resetRecipeDialogState() {
  editingRecipeId.value = null;
  recipeEditMode.value = 'full';
  recipeEditIndex.value = null;
  recipeInitialSnapshot.value = '';
}

async function saveRecipe(setError, setSuccess) {
  if (!selectedProduct.value || !canSaveRecipe.value) return;

  productActionLoading.value = 'recipe';

  try {
    const payload = {
      name: recipe.name.trim(),
      description: recipe.description.trim() || null,
      ingredients: meaningfulIngredients().map((item) => ({
        name: item.name.trim(),
        quantity: item.quantity === null || item.quantity === '' ? null : Number(item.quantity),
        unit: item.unit.trim() || null,
      })),
      steps: meaningfulSteps().map((step) => ({ description: step.description.trim() })),
    };

    const usedUnits = payload.ingredients.map((item) => item.unit).filter(Boolean);
    recentUnits.value = [...new Set([...usedUnits, ...recentUnits.value])].slice(0, 5);

    if (editingRecipeId.value) {
      await window.axios.put(
        `/tillwhite/api/products/${selectedProduct.value.id}/recipes/${editingRecipeId.value}`,
        payload,
      );
    } else {
      await window.axios.post(`/tillwhite/api/products/${selectedProduct.value.id}/recipes`, payload);
    }

    const wasEditing = Boolean(editingRecipeId.value);
    const successMessage = recipeEditMode.value === 'ingredient'
      ? `재료 ${Number(recipeEditIndex.value) + 1}번을 수정했습니다.`
      : recipeEditMode.value === 'step'
        ? `공정 ${Number(recipeEditIndex.value) + 1}을(를) 수정했습니다.`
        : wasEditing
          ? '레시피를 수정했습니다.'
          : '레시피를 등록했습니다.';

    closeRecipeDialog();
    setSuccess(successMessage);

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

function meaningfulIngredients() {
  return recipe.ingredients.filter((item) => (
    item.name.trim()
    || (item.quantity !== null && item.quantity !== '')
    || item.unit.trim()
  ));
}

function meaningfulSteps() {
  return recipe.steps.filter((step) => step.description.trim());
}

function recipeSnapshot() {
  return JSON.stringify({
    name: recipe.name,
    description: recipe.description,
    ingredients: recipe.ingredients.map(({ name, quantity, unit }) => ({ name, quantity, unit })),
    steps: recipe.steps.map(({ description }) => ({ description })),
  });
}

function recipeHistoryActor(entry) {
  return entry?.user?.name ?? '-';
}

function recipeHistoryAt(entry, fallbackAt = null) {
  const value = entry?.at ?? fallbackAt;
  if (!value) return '-';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';

  return new Intl.DateTimeFormat('ko-KR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date);
}

/** DB decimal 문자열에서 의미 없는 뒤쪽 0을 제거해 입력 당시 형태에 가깝게 표시합니다. */
function formatRecipeQuantity(value) {
  if (value === null || value === undefined || value === '') return null;

  const text = String(value);
  return text.includes('.') ? text.replace(/\.?0+$/, '') : text;
}

function createIngredientRow(ingredient = {}) {
  recipeRowKey += 1;
  const unit = String(ingredient.unit ?? '').trim();

  return {
    _key: `ingredient-${recipeRowKey}`,
    name: ingredient.name ?? '',
    quantity: formatRecipeQuantity(ingredient.quantity),
    unit,
    unitChoice: !unit || DEFAULT_UNITS.includes(unit) ? unit : CUSTOM_UNIT,
  };
}

function applyUnitChoice(ingredient) {
  if (ingredient.unitChoice === CUSTOM_UNIT) {
    if (DEFAULT_UNITS.includes(ingredient.unit)) ingredient.unit = '';
    return;
  }

  ingredient.unit = ingredient.unitChoice ?? '';
}

function useUnitSelect(ingredient) {
  ingredient.unitChoice = ingredient.unit && DEFAULT_UNITS.includes(ingredient.unit)
    ? ingredient.unit
    : '';
  ingredient.unit = ingredient.unitChoice;
}

function createStepRow(step = {}) {
  recipeRowKey += 1;
  return {
    _key: `step-${recipeRowKey}`,
    description: step.description ?? '',
  };
}

function createRecipeForm(existingRecipe) {
  return {
    name: existingRecipe.name ?? '',
    description: existingRecipe.description ?? '',
    ingredients: [...(existingRecipe.ingredients ?? [])]
      .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))
      .map(createIngredientRow),
    steps: [...(existingRecipe.steps ?? [])]
      .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))
      .map(createStepRow),
  };
}

function createEmptyRecipe() {
  return {
    name: '',
    description: '',
    ingredients: [createIngredientRow()],
    steps: [createStepRow()],
  };
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

/** 제품 화면의 영속 변경 확인을 하나의 상태와 공통 다이얼로그로 관리합니다. */
function openConfirm(action, title, message, confirmText = '예', payload = null) {
  if (productActionLoading.value) return;

  confirmDialog.action = action;
  confirmDialog.title = title;
  confirmDialog.message = message;
  confirmDialog.confirmText = confirmText;
  confirmDialog.payload = payload;
  confirmDialog.open = true;
}

/** 요청 처리 중에는 확인창이 임의로 닫히지 않도록 하고, 완료 후에만 강제로 초기화합니다. */
function clearConfirmDialog(force = false) {
  if (productActionLoading.value && !force) {
    return;
  }

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
  recipeDialog.value = false;
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

.product-primary-action :deep(.v-chip) {
  flex: 0 0 auto;
}

.recipe-dialog-description,
.recipe-form-hint,
.recipe-required-guide :deep(.v-alert__content) {
  font-size: 0.76rem;
  line-height: 1.45;
}

.recipe-form-section--basic .recipe-form-section-title {
  margin-bottom: 14px;
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

.recipe-dialog {
  display: flex;
  max-height: min(92dvh, 780px);
  flex-direction: column;
  overflow: hidden;
}

.recipe-dialog-header {
  display: flex;
  flex: 0 0 auto;
  align-items: flex-start;
  gap: 12px;
  padding: 20px;
}

.recipe-dialog-header-icon {
  display: grid;
  flex: 0 0 40px;
  width: 40px;
  height: 40px;
  place-items: center;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.08);
}

.recipe-scroll {
  min-height: 0;
  padding: 20px;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.recipe-form-section + .recipe-form-section {
  margin-top: 24px;
  padding-top: 22px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.recipe-form-section-title {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 6px;
  font-size: 0.9rem;
  font-weight: 700;
}

.recipe-form-hint {
  margin-bottom: 12px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.76rem;
}

.recipe-system-grid {
  display: grid;
  grid-template-columns: minmax(90px, auto) minmax(0, 1fr);
  gap: 10px 16px;
  font-size: 0.82rem;
}

.recipe-system-grid > span {
  color: rgba(var(--v-theme-on-surface), 0.58);
}

.recipe-system-grid > strong {
  min-width: 0;
  font-weight: 500;
  text-align: right;
  overflow-wrap: anywhere;
}

.recipe-builder-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.recipe-builder-item {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
}

.recipe-builder-number {
  display: grid;
  width: 32px;
  height: 32px;
  place-items: center;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
  font-size: 0.72rem;
  font-weight: 800;
}

.recipe-ingredient-fields {
  display: grid;
  min-width: 0;
  grid-template-columns: minmax(0, 1.5fr) minmax(90px, 0.7fr) minmax(80px, 0.6fr);
  gap: 12px;
}

.recipe-builder-item--step {
  align-items: flex-start;
}

.recipe-step-actions {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.recipe-dialog-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  padding: 14px 20px;
}

.min-width-0 {
  min-width: 0;
}

@media (max-width: 480px) {
  .recipe-dialog {
    max-height: calc(100dvh - 16px);
  }

  .recipe-dialog-header,
  .recipe-scroll,
  .recipe-dialog-actions {
    padding-right: 16px;
    padding-left: 16px;
  }

  .recipe-ingredient-fields {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }

  .recipe-ingredient-fields > :first-child {
    grid-column: 1 / -1;
  }
}

@media (max-width: 390px) {
  .product-primary-actions {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 360px) {

  .recipe-builder-item {
    grid-template-columns: 30px minmax(0, 1fr);
    align-items: flex-start;
  }

  .recipe-builder-item > .v-btn,
  .recipe-step-actions {
    grid-column: 2;
    justify-self: end;
  }

  .recipe-step-actions {
    flex-direction: row;
  }
}

@media (max-width: 340px) {
  .recipe-dialog-header-icon { flex-basis: 36px; width: 36px; height: 36px; }
  .recipe-dialog-actions { gap: 6px; }
  .recipe-dialog-actions .v-btn { min-width: 0; }
  .product-pagination {
    width: calc(100% - 16px);
    max-width: calc(100% - 16px);
  }

  .product-pagination :deep(.v-btn) {
    flex-basis: 32px;
    width: 32px;
    min-width: 32px;
    height: 32px;
  }
}
</style>
