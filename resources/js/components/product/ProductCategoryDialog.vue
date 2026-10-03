```vue
<template>
  <v-dialog
    :model-value="modelValue"
    max-width="620"
    persistent
    @update:model-value="handleMainDialogChange"
  >
    <v-card
      class="category-dialog"
      rounded="lg"
    >
      <!--
        헤더와 관리 도구는 목록 스크롤과 분리합니다.
        목록이 길어져도 제목과 관리 영역은 항상 같은 위치를 유지합니다.
      -->
      <div class="category-header">
        <div class="category-header-icon">
          <v-icon
            icon="mdi-shape-outline"
            size="22"
          />
        </div>

        <div class="min-width-0">
          <div class="text-h6 font-weight-bold">
            카테고리 관리
          </div>

          <div class="category-description text-medium-emphasis mt-1">
            점포별 카테고리를 관리합니다. 사용중단해도 기존 제품과 기록은 유지됩니다.
          </div>
        </div>
      </div>

      <v-divider />

      <!--
        점포 선택과 카테고리 등록, 검색 기능을 한 영역에서 관리합니다.
        실제 카테고리 목록과 분리해 목록 스크롤의 영향을 받지 않도록 합니다.
      -->
      <div class="category-controls">
        <!-- 여러 점포를 관리하는 경우에만 점포를 직접 선택할 수 있습니다. -->
        <v-select
          v-if="stores.length > 1"
          v-model="storeId"
          :items="stores"
          item-title="name"
          item-value="id"
          label="점포 *"
          prepend-inner-icon="mdi-store-outline"
          variant="outlined"
          hide-details
          :disabled="loading"
        />

        <!--
          관리 가능한 점포가 하나뿐이면 선택 기능이 필요하지 않습니다.
          현재 점포를 읽기 전용으로 표시해 변경할 수 없는 값임을 명확히 합니다.
        -->
        <v-text-field
          v-else
          :model-value="stores[0]?.name ?? '-'"
          label="점포"
          prepend-inner-icon="mdi-store-outline"
          append-inner-icon="mdi-lock-outline"
          readonly
          variant="outlined"
          hide-details
        />

        <!-- 새 카테고리 등록 -->
        <div class="category-create-row">
          <v-text-field
            v-model="newName"
            label="새 카테고리명 *"
            placeholder="카테고리명을 입력하세요"
            maxlength="100"
            prepend-inner-icon="mdi-shape-plus-outline"
            variant="outlined"
            hide-details
            :disabled="loading"
            @keyup.enter="add"
          />

          <v-btn
            class="category-add-button"
            icon="mdi-plus"
            variant="flat"
            :disabled="!canCreateCategory"
            aria-label="카테고리 추가"
            title="카테고리 추가"
            @click="add"
          />
        </div>

        <!-- 카테고리 검색 및 상태 필터 -->
        <div class="category-filter-row">
          <v-text-field
            v-model="searchQuery"
            label="카테고리명 검색"
            prepend-inner-icon="mdi-magnify"
            clearable
            variant="outlined"
            density="comfortable"
            hide-details
          />

          <v-select
            v-model="statusFilter"
            :items="statusItems"
            label="상태"
            prepend-inner-icon="mdi-list-status"
            variant="outlined"
            density="comfortable"
            hide-details
          />
        </div>

        <div class="category-result-count text-caption text-medium-emphasis">
          검색 결과 {{ filteredCategories.length }}개
        </div>
      </div>

      <v-divider />

      <!--
        실제 카테고리 목록만 스크롤합니다.
        헤더와 검색, 페이지네이션, 닫기 버튼의 위치는 그대로 유지됩니다.
      -->
      <div class="category-list-scroll">
        <div
          v-if="paginatedCategories.length"
          class="category-list"
        >
          <div
            v-for="category in paginatedCategories"
            :key="category.id"
            class="category-row"
          >
            <div class="category-main">
              <div class="category-name">
                {{ category.name }}
              </div>

              <div
                class="category-state"
                :class="{
                  'category-state--inactive': !category.is_active,
                }"
              >
                <span class="category-state-dot" />
                {{ category.is_active ? '사용중' : '사용중단' }}
              </div>
            </div>

            <div class="category-row-actions">
              <v-btn
                size="small"
                variant="text"
                prepend-icon="mdi-pencil-outline"
                :disabled="loading"
                @click="openRenameDialog(category)"
              >
                수정
              </v-btn>

              <!--
                사용중단과 재사용은 이 컴포넌트에서 바로 처리하지 않습니다.
                상위 화면으로 대상을 전달해 공통 확인 다이얼로그를 거치도록 합니다.
              -->
              <v-btn
                size="small"
                variant="text"
                :prepend-icon="
                  category.is_active
                    ? 'mdi-pause-circle-outline'
                    : 'mdi-play-circle-outline'
                "
                :disabled="loading"
                @click="$emit('toggle', category)"
              >
                {{ category.is_active ? '중단' : '재사용' }}
              </v-btn>
            </div>
          </div>
        </div>

        <!-- 검색 또는 상태 조건에 맞는 카테고리가 없을 때 표시합니다. -->
        <div
          v-else
          class="category-empty"
        >
          <v-icon
            icon="mdi-shape-outline"
            size="30"
            class="mb-2 text-medium-emphasis"
          />

          <div class="text-body-2 font-weight-medium">
            조건에 맞는 카테고리가 없습니다.
          </div>

          <div class="text-caption text-medium-emphasis mt-1">
            검색어나 상태 필터를 확인해주세요.
          </div>
        </div>
      </div>

      <v-divider />

      <!--
        페이지네이션은 목록 스크롤 영역 밖에 둡니다.
        목록이 길어져도 페이지 이동 영역이 함께 움직이지 않도록 합니다.
      -->
      <div class="category-pagination-area">
        <v-pagination
          v-if="totalPages > 1"
          v-model="currentPage"
          :length="totalPages"
          :total-visible="4"
          density="comfortable"
          rounded="circle"
        />

        <div class="text-caption text-medium-emphasis">
          {{ pageStart }}–{{ pageEnd }} / {{ filteredCategories.length }}개
        </div>
      </div>

      <v-divider />

      <div class="category-actions">
        <v-btn
          variant="text"
          prepend-icon="mdi-close"
          :disabled="loading"
          @click="requestCloseMainDialog"
        >
          닫기
        </v-btn>
      </div>
    </v-card>
  </v-dialog>

  <!-- 카테고리명 수정 -->
  <v-dialog
    v-model="renameDialog"
    max-width="420"
    persistent
  >
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">
        카테고리명 수정
      </v-card-title>

      <v-card-text class="px-5">
        <v-text-field
          v-model="renameName"
          label="카테고리명 *"
          maxlength="100"
          variant="outlined"
          :disabled="loading"
          autofocus
          @keyup.enter="submitRename"
        />
      </v-card-text>

      <v-card-actions class="px-5 pb-4">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="requestCloseRenameDialog"
        >
          취소
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          prepend-icon="mdi-content-save-outline"
          :disabled="!canRenameCategory"
          :loading="loading"
          @click="submitRename"
        >
          저장
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!--
    새 카테고리명이나 수정 중인 이름이 남아 있을 때만 표시합니다.
    사용자가 실수로 작성 중인 내용을 잃지 않도록 이탈 여부를 확인합니다.
  -->
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
        저장하지 않은 카테고리 입력 내용은 사라집니다.
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
import { computed, ref, watch } from 'vue';

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

/*
 * 등록 요청 직후 입력값을 지우지 않고 요청 내용을 임시로 보관합니다.
 * 실제 목록에 등록 결과가 반영된 것이 확인된 뒤에만 입력값을 초기화합니다.
 */
const pendingCreate = ref(null);

const statusItems = [
  { title: '전체', value: 'all' },
  { title: '사용중', value: 'active' },
  { title: '사용중단', value: 'inactive' },
];

/*
 * 다이얼로그를 처음 열었을 때 선택된 점포가 없다면
 * 현재 사용자가 접근할 수 있는 첫 번째 점포를 기본값으로 사용합니다.
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
 * 점포나 검색 조건이 변경되면 기존 페이지 번호가
 * 새로운 검색 결과 범위를 벗어날 수 있으므로 첫 페이지로 이동합니다.
 */
watch([storeId, searchQuery, statusFilter], () => {
  currentPage.value = 1;
});

/*
 * 현재 선택한 점포의 카테고리만 가져온 뒤
 * 카테고리명 검색과 사용 상태 필터를 함께 적용합니다.
 */
const filteredCategories = computed(() => {
  const query = searchQuery.value
    .trim()
    .toLocaleLowerCase('ko-KR');

  return props.categories.filter((category) => {
    if (Number(category.store_id) !== Number(storeId.value)) {
      return false;
    }

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

/*
 * 검색 결과가 없는 경우에도 페이지 계산값이 0이 되지 않도록
 * 전체 페이지 수는 최소 1페이지를 유지합니다.
 */
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
 * 검색 결과가 줄어 현재 페이지가 존재하지 않게 된 경우
 * 마지막으로 사용할 수 있는 페이지로 자동 이동합니다.
 */
watch(totalPages, (value) => {
  if (currentPage.value > value) {
    currentPage.value = value;
  }
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
 * API 요청 결과가 실제 categories 데이터에 반영된 뒤에만
 * 등록 입력값을 비우거나 수정 다이얼로그를 닫습니다.
 *
 * 요청이 실패한 경우에는 사용자가 작성한 내용을 그대로 유지합니다.
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
 * v-dialog 자체에서 닫기가 요청된 경우에도 바로 닫지 않습니다.
 * 작성 중인 내용이 있는지 확인하기 위해 공통 닫기 처리를 사용합니다.
 */
function handleMainDialogChange(value) {
  if (value || props.loading) {
    return;
  }

  requestCloseMainDialog();
}

/*
 * 새 카테고리명이 입력된 상태라면 작성 내용을 바로 버리지 않고
 * 이탈 확인 다이얼로그를 먼저 표시합니다.
 */
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

/*
 * 처음 불러온 카테고리명과 현재 입력값이 실제로 다른 경우에만
 * 수정 내용을 버릴 것인지 확인합니다.
 */
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

/*
 * 어느 화면에서 이탈을 요청했는지 확인한 뒤
 * 해당 화면에 필요한 입력 상태만 초기화합니다.
 */
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
 * 등록 요청 직후에는 입력값을 유지합니다.
 * API 성공이 categories에 반영된 것이 확인된 경우에만 입력값을 비웁니다.
 */
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

// 수정할 카테고리와 현재 이름을 보관한 뒤 수정 다이얼로그를 엽니다.
function openRenameDialog(category) {
  if (props.loading) {
    return;
  }

  renameTarget.value = category;
  renameName.value = category.name ?? '';
  renameDialog.value = true;
}

// 수정 작업이 끝나면 다음 수정에 이전 값이 남지 않도록 관련 상태를 초기화합니다.
function closeRenameDialog() {
  if (props.loading) {
    return;
  }

  renameDialog.value = false;
  renameTarget.value = null;
  renameName.value = '';
}

/*
 * 실제 API 요청은 상위 화면에서 처리합니다.
 * 이 컴포넌트에서는 수정 대상과 변경할 이름만 전달합니다.
 */
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
.category-dialog {
  display: flex;
  flex-direction: column;
  max-height: min(92dvh, 760px);
  overflow: hidden;
}

.category-header {
  display: flex;
  flex: 0 0 auto;
  align-items: flex-start;
  gap: 12px;
  padding: 20px;
}

.category-header-icon {
  display: grid;
  flex: 0 0 40px;
  width: 40px;
  height: 40px;
  place-items: center;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.08);
}

/*
 * 긴 제목이나 카테고리명이 flex 영역의 너비를 밀어내면서
 * 모바일 화면에 가로 스크롤이 생기는 것을 방지합니다.
 */
.min-width-0 {
  min-width: 0;
}

/* 다이얼로그 설명은 제목보다 작은 보조 텍스트로 표시합니다. */
.category-description {
  font-size: 0.76rem;
  line-height: 1.45;
}

.category-controls {
  display: flex;
  flex: 0 0 auto;
  flex-direction: column;
  gap: 12px;
  padding: 16px 20px;
}

.category-create-row,
.category-filter-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
}

.category-filter-row {
  grid-template-columns: minmax(0, 1fr) minmax(130px, 0.42fr);
}

.category-add-button {
  width: 48px;
  height: 48px;
}

.category-result-count {
  text-align: right;
}

/*
 * 목록만 스크롤되도록 하여 카테고리가 많아져도
 * 상단 관리 영역과 하단 페이지네이션의 위치를 유지합니다.
 */
.category-list-scroll {
  min-height: 180px;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.category-list {
  display: flex;
  flex-direction: column;
}

.category-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 13px 20px;
  border-bottom: 1px solid rgba(
    var(--v-border-color),
    var(--v-border-opacity)
  );
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
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 5px;
  color: rgb(var(--v-theme-success));
  font-size: 0.75rem;
}

.category-state--inactive {
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.category-state-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.category-row-actions {
  display: flex;
  flex: 0 0 auto;
  gap: 2px;
}

.category-empty {
  display: grid;
  min-height: 180px;
  padding: 24px;
  place-content: center;
  text-align: center;
}

.category-pagination-area {
  display: flex;
  flex: 0 0 auto;
  min-height: 58px;
  max-width: 100%;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  padding: 6px 12px;
  overflow: hidden;
}

/*
 * Vuetify 내부 페이지네이션도 부모 영역의 최대 너비를 따르게 해
 * 작은 화면에서 페이지 버튼 때문에 가로로 넘치는 것을 방지합니다.
 */
.category-pagination-area :deep(.v-pagination) {
  max-width: 100%;
}

.category-pagination-area :deep(.v-pagination__list) {
  max-width: 100%;
  justify-content: center;
  margin: 0;
  padding: 0;
}

.category-actions {
  display: flex;
  flex: 0 0 auto;
  justify-content: flex-start;
  padding: 10px 16px;
}

@media (max-width: 480px) {
  .category-dialog {
    max-height: calc(100dvh - 16px);
  }

  .category-header {
    padding: 16px;
  }

  .category-controls {
    padding: 14px 16px;
  }

  /*
   * 검색창과 상태 필터를 한 줄에 유지하면 각각의 너비가 지나치게 좁아집니다.
   * 모바일에서는 세로로 배치해 입력 영역을 충분히 확보합니다.
   */
  .category-filter-row {
    grid-template-columns: 1fr;
  }

  .category-row {
    align-items: flex-start;
    padding: 12px 16px;
  }

  /*
   * 수정/중단 버튼이 카테고리명을 과도하게 압축하지 않도록
   * 좁은 화면에서는 액션 버튼을 세로로 배치합니다.
   */
  .category-row-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .category-row-actions .v-btn {
    justify-content: flex-start;
  }
}

@media (max-width: 350px) {
  /*
   * 매우 작은 화면에서도 입력창이 화면 밖으로 밀리지 않도록 조정하면서
   * 추가 버튼은 누르기 편한 최소 크기를 유지합니다.
   */
  .category-create-row {
    grid-template-columns: minmax(0, 1fr) 44px;
    gap: 6px;
  }

  .category-add-button {
    width: 44px;
    height: 44px;
  }

  /*
   * 카테고리명과 액션 영역을 분리해 긴 이름이 들어와도
   * 수정/중단 버튼과 겹치거나 가로로 넘치지 않도록 합니다.
   */
  .category-row {
    flex-direction: column;
  }

  .category-row-actions {
    width: 100%;
    flex-direction: row;
  }

  .category-row-actions .v-btn {
    flex: 1 1 0;
    justify-content: center;
  }
}
</style>
```