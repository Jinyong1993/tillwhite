<template>
  <v-dialog
    :model-value="modelValue"
    max-width="620"
    persistent
    @update:model-value="!loading && $emit('update:modelValue', $event)"
  >
    <v-card class="category-dialog" rounded="lg">
      <!-- 헤더와 관리 도구는 목록 스크롤과 분리해 항상 같은 위치에 둡니다. -->
      <div class="category-header">
        <div class="category-header-icon">
          <v-icon icon="mdi-shape-outline" size="22" />
        </div>

        <div class="min-width-0">
          <div class="text-h6 font-weight-bold">카테고리 관리</div>
          <div class="text-body-2 text-medium-emphasis mt-1">
            점포별 카테고리를 관리합니다. 사용중단해도 기존 제품과 기록은 유지됩니다.
          </div>
        </div>
      </div>

      <v-divider />

      <div class="category-controls">
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

      <!-- 목록만 스크롤되어 헤더/검색/페이지네이션/닫기 위치가 흔들리지 않습니다. -->
      <div class="category-list-scroll">
        <div v-if="paginatedCategories.length" class="category-list">
          <div
            v-for="category in paginatedCategories"
            :key="category.id"
            class="category-row"
          >
            <div class="category-main">
              <div class="category-name">{{ category.name }}</div>
              <div class="category-state" :class="{ 'category-state--inactive': !category.is_active }">
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

              <v-btn
                size="small"
                variant="text"
                :prepend-icon="category.is_active ? 'mdi-pause-circle-outline' : 'mdi-play-circle-outline'"
                :disabled="loading"
                @click="$emit('toggle', category)"
              >
                {{ category.is_active ? '중단' : '재사용' }}
              </v-btn>
            </div>
          </div>
        </div>

        <div v-else class="category-empty">
          <v-icon icon="mdi-shape-outline" size="30" class="mb-2 text-medium-emphasis" />
          <div class="text-body-2 font-weight-medium">조건에 맞는 카테고리가 없습니다.</div>
          <div class="text-caption text-medium-emphasis mt-1">검색어나 상태 필터를 확인해주세요.</div>
        </div>
      </div>

      <v-divider />

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
          @click="$emit('update:modelValue', false)"
        >
          닫기
        </v-btn>
      </div>
    </v-card>
  </v-dialog>

  <v-dialog v-model="renameDialog" max-width="420" persistent>
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">카테고리명 수정</v-card-title>
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
        <v-btn variant="text" :disabled="loading" @click="closeRenameDialog">취소</v-btn>
        <v-spacer />
        <v-btn variant="flat" prepend-icon="mdi-content-save-outline" :disabled="!canRenameCategory" :loading="loading" @click="submitRename">저장</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  stores: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'create', 'rename', 'toggle']);

const ITEMS_PER_PAGE = 10;
const storeId = ref(null);
const newName = ref('');
const searchQuery = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);
const renameDialog = ref(false);
const renameTarget = ref(null);
const renameName = ref('');
const pendingCreate = ref(null);

const statusItems = [
  { title: '전체', value: 'all' },
  { title: '사용중', value: 'active' },
  { title: '사용중단', value: 'inactive' },
];

watch(
  () => [props.modelValue, props.stores],
  () => {
    if (props.modelValue && !storeId.value) {
      storeId.value = props.stores[0]?.id ?? null;
    }
  },
  { immediate: true },
);

watch([storeId, searchQuery, statusFilter], () => {
  currentPage.value = 1;
});

const filteredCategories = computed(() => {
  const query = searchQuery.value.trim().toLocaleLowerCase('ko-KR');

  return props.categories.filter((category) => {
    if (Number(category.store_id) !== Number(storeId.value)) return false;
    if (query && !String(category.name ?? '').toLocaleLowerCase('ko-KR').includes(query)) return false;
    if (statusFilter.value === 'active' && !category.is_active) return false;
    if (statusFilter.value === 'inactive' && category.is_active) return false;
    return true;
  });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredCategories.value.length / ITEMS_PER_PAGE)));
const pageStart = computed(() => filteredCategories.value.length ? ((currentPage.value - 1) * ITEMS_PER_PAGE) + 1 : 0);
const pageEnd = computed(() => Math.min(currentPage.value * ITEMS_PER_PAGE, filteredCategories.value.length));
const paginatedCategories = computed(() => filteredCategories.value.slice(pageStart.value ? pageStart.value - 1 : 0, pageEnd.value));

watch(totalPages, (value) => {
  if (currentPage.value > value) currentPage.value = value;
});

const canCreateCategory = computed(() => Boolean(storeId.value && newName.value.trim() && !props.loading));
const canRenameCategory = computed(() => {
  const name = renameName.value.trim();
  return Boolean(renameTarget.value && name && name !== renameTarget.value.name && !props.loading);
});

watch(
  () => props.categories,
  () => {
    if (pendingCreate.value) {
      const created = props.categories.some((category) => Number(category.store_id) === Number(pendingCreate.value.store_id) && category.name === pendingCreate.value.name);
      if (created) {
        newName.value = '';
        pendingCreate.value = null;
      }
    }

    if (!renameDialog.value || !renameTarget.value) return;
    const updated = props.categories.find((category) => Number(category.id) === Number(renameTarget.value.id));
    if (updated?.name === renameName.value.trim()) closeRenameDialog();
  },
);

function add() {
  const name = newName.value.trim();
  if (!name || !storeId.value || props.loading) return;
  pendingCreate.value = { store_id: storeId.value, name };
  emit('create', pendingCreate.value);
}

function openRenameDialog(category) {
  if (props.loading) return;
  renameTarget.value = category;
  renameName.value = category.name ?? '';
  renameDialog.value = true;
}

function closeRenameDialog() {
  if (props.loading) return;
  renameDialog.value = false;
  renameTarget.value = null;
  renameName.value = '';
}

function submitRename() {
  if (!canRenameCategory.value) return;
  emit('rename', { category: renameTarget.value, name: renameName.value.trim() });
}
</script>

<style scoped>
.category-dialog { display: flex; flex-direction: column; max-height: min(92dvh, 760px); overflow: hidden; }
.category-header { display: flex; align-items: flex-start; gap: 12px; padding: 20px; flex: 0 0 auto; }
.category-header-icon { display: grid; flex: 0 0 40px; width: 40px; height: 40px; place-items: center; border-radius: 10px; background: rgba(var(--v-theme-primary), 0.08); }
.min-width-0 { min-width: 0; }
.category-controls { display: flex; flex: 0 0 auto; flex-direction: column; gap: 12px; padding: 16px 20px; }
.category-create-row, .category-filter-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: center; }
.category-filter-row { grid-template-columns: minmax(0, 1fr) minmax(130px, 0.42fr); }
.category-add-button { width: 48px; height: 48px; }
.category-result-count { text-align: right; }
.category-list-scroll { min-height: 180px; overflow-y: auto; overscroll-behavior: contain; }
.category-list { display: flex; flex-direction: column; }
.category-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 20px; border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.category-main { min-width: 0; }
.category-name { font-size: 0.9rem; font-weight: 650; line-height: 1.4; overflow-wrap: anywhere; word-break: break-word; }
.category-state { display: flex; align-items: center; gap: 5px; margin-top: 5px; color: rgb(var(--v-theme-success)); font-size: 0.75rem; }
.category-state--inactive { color: rgba(var(--v-theme-on-surface), 0.5); }
.category-state-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.category-row-actions { display: flex; flex: 0 0 auto; gap: 2px; }
.category-empty { display: grid; min-height: 180px; padding: 24px; place-content: center; text-align: center; }
.category-pagination-area { display: flex; flex: 0 0 auto; min-height: 58px; flex-direction: column; align-items: center; justify-content: center; gap: 2px; padding: 6px 12px; }
.category-pagination-area :deep(.v-pagination__list) { margin: 0; padding: 0; }
.category-actions { display: flex; flex: 0 0 auto; justify-content: flex-start; padding: 10px 16px; }

@media (max-width: 480px) {
  .category-dialog { max-height: calc(100dvh - 16px); }
  .category-header { padding: 16px; }
  .category-controls { padding: 14px 16px; }
  .category-filter-row { grid-template-columns: 1fr; }
  .category-row { align-items: flex-start; padding: 12px 16px; }
  .category-row-actions { flex-direction: column; align-items: stretch; }
  .category-row-actions .v-btn { justify-content: flex-start; }
}

@media (max-width: 350px) {
  .category-create-row { grid-template-columns: minmax(0, 1fr) 44px; gap: 6px; }
  .category-add-button { width: 44px; height: 44px; }
  .category-row { flex-direction: column; }
  .category-row-actions { width: 100%; flex-direction: row; }
  .category-row-actions .v-btn { flex: 1 1 0; justify-content: center; }
}
</style>
