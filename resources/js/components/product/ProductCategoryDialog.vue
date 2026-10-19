<template>
  <!-- 카테고리 관리 -->
  <AppDialogLayout
    :model-value="modelValue"
    title="카테고리 관리"
    subtitle="점포별 카테고리를 관리합니다. 사용중단해도 기존 제품과 기록은 유지됩니다."
    :max-width="620"
    persistent
    card-class="category-dialog"
    body-class="category-dialog-body"
    :busy="loading"
    @update:model-value="handleMainDialogChange"
  >
    <div class="category-workspace d-flex flex-column ga-3">
      <!-- 카테고리 등록 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="category-section d-flex flex-column ga-3 pa-3"
      >
        <div class="category-section-title font-weight-medium">
          카테고리 등록
        </div>

        <v-select
          v-if="stores.length > 1"
          v-model="storeId"
          :items="stores"
          item-title="name"
          item-value="id"
          label="점포 *"
          variant="outlined"
          density="compact"
          class="app-select"
          hide-details
          :disabled="loading"
        />

        <v-text-field
          v-else
          :model-value="stores[0]?.name ?? '-'"
          label="점포"
          readonly
          variant="outlined"
          density="compact"
          class="app-input"
          hide-details
        />

        
        <div class="category-create-row d-flex flex-column ga-2">
          <v-text-field
            v-model="newName"
            label="새 카테고리명 *"
            placeholder="카테고리명을 입력하세요"
            maxlength="100"
            variant="outlined"
            density="compact"
            class="app-input w-100"
            hide-details
            :disabled="loading"
            @keyup.enter="add"
          />

          <v-btn
            block
            variant="outlined"
            color="success"
            min-height="44"
            :disabled="!canCreateCategory"
            @click="add"
          >
            추가
          </v-btn>
        </div>
      </v-card>

      <!-- 검색 및 필터 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="category-section d-flex flex-column ga-3 pa-3"
      >
        <div class="category-section-title font-weight-medium">
          검색 및 필터
        </div>

        <div class="category-filter-row d-flex flex-column ga-2">
          <v-text-field
            v-model="searchQuery"
            label="검색"
            clearable
            variant="outlined"
            density="compact"
            class="app-input"
            hide-details
            @click:clear="clearSearch"
          />

          <v-select
            v-model="statusFilter"
            :items="statusItems"
            label="상태"
            variant="outlined"
            density="compact"
            class="app-select"
            hide-details
          />
        </div>

        <div class="app-result-count text-medium-emphasis text-end">
          검색 결과 {{ filteredCategories.length }}개
        </div>
      </v-card>

      <!-- 카테고리 목록 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="category-section category-list-panel d-flex flex-column"
      >
        <div
          class="category-list-heading d-flex align-center justify-space-between ga-2 pa-3"
        >
          <div class="category-section-title font-weight-medium">
            카테고리 목록
          </div>

          <div class="app-result-count text-medium-emphasis">
            총 {{ filteredCategories.length }}개
          </div>
        </div>

        <v-divider />

        <div class="category-list-scroll">
          <div
            v-if="paginatedCategories.length"
            class="category-list d-flex flex-column"
          >
            <div
              v-for="category in paginatedCategories"
              :key="category.id"
              class="category-row d-flex flex-column ga-2 pa-3"
            >
              <div class="category-main">
                <div class="category-name">
                  {{ category.name }}
                </div>

                <div
                  class="category-state d-flex align-center ga-1"
                  :class="{
                    'category-state--inactive': !category.is_active,
                  }"
                >
                  <span class="category-state-dot" />

                  {{ category.is_active ? '사용중' : '사용중단' }}
                </div>
              </div>

              <div class="category-row-actions d-flex align-center ga-2">
                <v-btn
                  variant="outlined"
                  color="primary"
                  class="category-row-button"
                  min-height="44"
                  :disabled="loading"
                  @click="openRenameDialog(category)"
                >
                  수정
                </v-btn>

                <v-btn
                  variant="outlined"
                  :color="category.is_active ? 'warning' : 'success'"
                  class="category-row-button"
                  min-height="44"
                  :disabled="loading"
                  @click="emit('toggle', category)"
                >
                  {{ category.is_active ? '중단' : '재사용' }}
                </v-btn>
              </div>
            </div>
          </div>

          <div
            v-else
            class="category-empty d-flex flex-column align-center justify-center ga-2 pa-5"
          >
            <div class="text-body-2 font-weight-medium text-center">
              조건에 맞는 카테고리가 없습니다.
            </div>

            <div class="app-supporting-text text-medium-emphasis text-center">
              검색어나 상태 필터를 확인해주세요.
            </div>
          </div>
        </div>

        <v-divider />

        <div
          class="category-pagination-area d-flex flex-column align-center justify-center ga-1 pa-2"
        >
          <v-pagination
            v-if="totalPages > 1"
            v-model="currentPage"
            :length="totalPages"
            :total-visible="4"
            density="compact"
            rounded="circle"
          />

          <div class="app-result-count text-medium-emphasis">
            {{ pageStart }}–{{ pageEnd }} / {{ filteredCategories.length }}개
          </div>
        </div>
      </v-card>
    </div>

    <template #footer>
      <AppDialogFooter
        :button-count="1"
        :show-confirm="false"
        cancel-text="닫기"
        :disabled="loading"
        @cancel="requestCloseMainDialog"
      />
    </template>
  </AppDialogLayout>

  <!-- 카테고리명 수정 -->
  <AppDialogLayout
    :model-value="renameDialog"
    title="카테고리명 수정"
    :max-width="420"
    persistent
    card-class="app-dialog-card--compact"
    :busy="loading"
    @update:model-value="handleRenameDialogChange"
  >
    <div class="category-simple-content d-flex flex-column pa-3">
      <v-card
        variant="flat"
        rounded="lg"
        class="category-section d-flex flex-column pa-3"
      >
        <v-text-field
          v-model="renameName"
          label="카테고리명 *"
          maxlength="100"
          variant="outlined"
          density="compact"
          class="app-input"
          :disabled="loading"
          autofocus
          @keyup.enter="submitRename"
        />
      </v-card>
    </div>

    <template #footer>
      <AppDialogFooter
        cancel-text="취소"
        confirm-text="저장"
        confirm-action="save"
        :loading="loading"
        @cancel="requestCloseRenameDialog"
        @confirm="submitRename"
      >
        <template #confirm>
          <v-btn
            block
            color="success"
            variant="flat"
            min-height="44"
            :disabled="!canRenameCategory"
            :loading="loading"
            @click="submitRename"
          >
            저장
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>

  <!-- 입력 취소 확인 -->
  <AppDialogLayout
    :model-value="discardDialog"
    title="입력을 취소하시겠습니까?"
    :max-width="360"
    persistent
    card-class="app-dialog-card--compact"
    @update:model-value="handleDiscardDialogChange"
  >
    <div class="category-simple-content d-flex flex-column pa-3">
      <v-card
        variant="flat"
        rounded="lg"
        class="category-section pa-3"
      >
        <div class="app-supporting-text">
          저장하지 않은 카테고리 입력 내용은 사라집니다.
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

  categories: {
    type: Array,
    default: () => [],
  },

  stores: {
    type: Array,
    default: () => [],
  },

  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  'update:modelValue',
  'create',
  'rename',
  'toggle',
  'reorder',
]);

const ITEMS_PER_PAGE = 10;

const storeId = ref(null);
const newName = ref('');

const searchQuery = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);

const renameDialog = ref(false);
const renameTarget = ref(null);
const renameName = ref('');

const discardDialog = ref(false);
const discardTarget = ref(null);

const pendingCreate = ref(null);

const statusItems = [
  { title: '전체', value: 'all' },
  { title: '사용중', value: 'active' },
  { title: '사용중단', value: 'inactive' },
];

/*
 * 관리 가능한 첫 번째 점포를 초기 선택값으로 사용합니다.
 */
watch(
  () => [props.modelValue, props.stores],
  () => {
    if (props.modelValue && !storeId.value) {
      storeId.value = props.stores[0]?.id ?? null;
    }
  },
  { immediate: true },
);

/*
 * 점포 또는 검색 조건이 바뀌면 첫 페이지로 이동합니다.
 */
watch([storeId, searchQuery, statusFilter], () => {
  currentPage.value = 1;
});

/*
 * 검색창의 지우기 버튼이 실행되면 빈 문자열로 초기화합니다.
 * clearable이 null을 전달하는 상황도 필터에서 처리합니다.
 */
function clearSearch() {
  searchQuery.value = '';
}

/*
 * 현재 점포에 속한 카테고리를 이름 기준으로 자연 정렬합니다.
 */
const orderedStoreCategories = computed(() => {
  const categories = props.categories.filter(
    (category) => Number(category.store_id) === Number(storeId.value),
  );

  return sortByDisplayName(categories);
});

/*
 * 검색어와 사용 상태를 함께 적용합니다.
 */
const filteredCategories = computed(() => {
  const query = String(searchQuery.value ?? '')
    .trim()
    .toLocaleLowerCase('ko-KR');

  return orderedStoreCategories.value.filter((category) => {
    const categoryName = String(category.name ?? '')
      .toLocaleLowerCase('ko-KR');

    if (query && !categoryName.includes(query)) {
      return false;
    }

    if (statusFilter.value === 'active' && !category.is_active) {
      return false;
    }

    if (statusFilter.value === 'inactive' && category.is_active) {
      return false;
    }

    return true;
  });
});

const totalPages = computed(() => {
  return Math.max(
    1,
    Math.ceil(filteredCategories.value.length / ITEMS_PER_PAGE),
  );
});

const pageStart = computed(() => {
  if (!filteredCategories.value.length) {
    return 0;
  }

  return ((currentPage.value - 1) * ITEMS_PER_PAGE) + 1;
});

const pageEnd = computed(() => {
  return Math.min(
    currentPage.value * ITEMS_PER_PAGE,
    filteredCategories.value.length,
  );
});

const paginatedCategories = computed(() => {
  const startIndex = pageStart.value
    ? pageStart.value - 1
    : 0;

  return filteredCategories.value.slice(
    startIndex,
    pageEnd.value,
  );
});

/*
 * 검색 결과가 줄면 현재 페이지를 보정합니다.
 */
watch(totalPages, (value) => {
  if (currentPage.value > value) {
    currentPage.value = value;
  }
});

/*
 * 기존 수동 정렬 로직을 보존합니다.
 */
const canReorderCategories = computed(() => {
  return !String(searchQuery.value ?? '').trim()
    && statusFilter.value === 'all';
});

const canCreateCategory = computed(() => {
  return Boolean(
    storeId.value
    && newName.value.trim()
    && !props.loading,
  );
});

const canRenameCategory = computed(() => {
  const name = renameName.value.trim();

  return Boolean(
    renameTarget.value
    && name
    && name !== renameTarget.value.name
    && !props.loading,
  );
});

/*
 * 서버 결과가 카테고리 목록에 실제로 반영된 후에만
 * 등록 입력값을 지우거나 수정창을 닫습니다.
 */
watch(
  () => props.categories,
  () => {
    if (pendingCreate.value) {
      const created = props.categories.some((category) => {
        const sameStore =
          Number(category.store_id)
          === Number(pendingCreate.value.store_id);

        const sameName =
          category.name === pendingCreate.value.name;

        return sameStore && sameName;
      });

      if (created) {
        newName.value = '';
        pendingCreate.value = null;
      }
    }

    if (!renameDialog.value || !renameTarget.value) {
      return;
    }

    const updated = props.categories.find((category) => {
      return Number(category.id) === Number(renameTarget.value.id);
    });

    if (updated?.name === renameName.value.trim()) {
      closeRenameDialog();
    }
  },
);

/*
 * 다이얼로그 닫기를 요청할 때 기존 입력 폐기 확인을 유지합니다.
 */
function handleMainDialogChange(value) {
  if (value || props.loading) {
    return;
  }

  requestCloseMainDialog();
}

function handleRenameDialogChange(value) {
  if (value || props.loading) {
    return;
  }

  requestCloseRenameDialog();
}

function handleDiscardDialogChange(value) {
  if (!value) {
    discardDialog.value = false;
  }
}

function requestCloseMainDialog() {
  if (props.loading) {
    return;
  }

  if (newName.value.trim()) {
    discardTarget.value = 'main';
    discardDialog.value = true;

    return;
  }

  emit('update:modelValue', false);
}

function requestCloseRenameDialog() {
  if (props.loading) {
    return;
  }

  const originalName = String(renameTarget.value?.name ?? '').trim();
  const currentName = renameName.value.trim();

  if (renameTarget.value && currentName !== originalName) {
    discardTarget.value = 'rename';
    discardDialog.value = true;

    return;
  }

  closeRenameDialog();
}

function discardChanges() {
  const target = discardTarget.value;

  discardDialog.value = false;
  discardTarget.value = null;

  if (target === 'rename') {
    closeRenameDialog();

    return;
  }

  if (target === 'main') {
    newName.value = '';
    emit('update:modelValue', false);
  }
}

/*
 * 현재 화면에서 표시하지 않는 정렬 버튼의
 * 내부 기능과 이벤트는 기존대로 유지합니다.
 */
function canMoveCategory(category, direction) {
  if (!canReorderCategories.value) {
    return false;
  }

  const index = orderedStoreCategories.value.findIndex((item) => {
    return Number(item.id) === Number(category.id);
  });

  if (index < 0) {
    return false;
  }

  return direction < 0
    ? index > 0
    : index < orderedStoreCategories.value.length - 1;
}

function requestMoveCategory(category, direction) {
  if (props.loading || !canMoveCategory(category, direction)) {
    return;
  }

  emit('reorder', { category, direction });
}

function add() {
  const name = newName.value.trim();

  if (!name || !storeId.value || props.loading) {
    return;
  }

  pendingCreate.value = {
    store_id: storeId.value,
    name,
  };

  emit('create', pendingCreate.value);
}

function openRenameDialog(category) {
  if (props.loading) {
    return;
  }

  renameTarget.value = category;
  renameName.value = category.name ?? '';
  renameDialog.value = true;
}

function closeRenameDialog() {
  renameDialog.value = false;
  renameTarget.value = null;
  renameName.value = '';
}

function submitRename() {
  if (!canRenameCategory.value) {
    return;
  }

  emit('rename', {
    category: renameTarget.value,
    name: renameName.value.trim(),
  });
}
</script>

<style scoped>
.category-workspace {
  flex: 1 1 auto;
  min-width: 0;
  min-height: 0;
  width: 100%;
}

.category-section {
  min-width: 0;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  box-shadow: none;
}

.category-section-title {
  font-size: 0.85rem;
  line-height: 1.4;
}

.category-create-row {
  min-width: 0;
}

.category-create-input {
  min-width: 0;
}

.category-add-button {
  min-width: 56px;
  min-height: 44px;
}

.category-filter-row {
  min-width: 0;
}

.category-filter-row > * {
  min-width: 0;
}

.category-list-panel {
  flex: 1 1 auto;
  min-width: 0;
  min-height: 200px;
  overflow: hidden;
}

.category-list-heading {
  flex: 0 0 auto;
  min-width: 0;
}

.category-list-scroll {
  flex: 1 1 auto;
  min-width: 0;
  min-height: 120px;
  max-height: 360px;
  overflow-y: auto;
  overflow-x: hidden;
  overscroll-behavior: contain;
}

.category-list {
  min-width: 0;
}

.category-row {
  min-width: 0;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
}

.category-row:last-child {
  border-bottom: 0;
}

.category-main {
  min-width: 0;
}

.category-name {
  font-size: 0.9rem;
  font-weight: 650;
  line-height: 1.4;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.category-state {
  margin-top: 5px;
  font-size: 0.75rem;
  color: rgb(var(--v-theme-success));
}

.category-state--inactive {
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.category-state-dot {
  width: 6px;
  height: 6px;
  flex: 0 0 6px;
  border-radius: 50%;
  background: currentColor;
}

.category-row-actions {
  min-width: 0;
  width: 100%;
}

.category-row-button {
  flex: 1 1 0;
  min-width: 0;
  min-height: 44px;
}

.category-empty {
  min-height: 160px;
}

.category-pagination-area {
  flex: 0 0 auto;
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
}

.category-pagination-area :deep(.v-pagination) {
  max-width: 100%;
}

.category-pagination-area :deep(.v-pagination__list) {
  max-width: 100%;
  justify-content: center;
  margin: 0;
  padding: 0;
}

.category-simple-content {
  min-width: 0;
  width: 100%;
}

@media (max-width: 360px) {
  .category-workspace {
    gap: 8px !important;
  }

  .category-section {
    border-radius: 8px;
  }

  .category-row-actions {
    gap: 6px !important;
  }

  .category-row-button {
    font-size: 0.75rem;
  }

  .category-list-scroll {
    min-height: 120px;
  }
}
</style>