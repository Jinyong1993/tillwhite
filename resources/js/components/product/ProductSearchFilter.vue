<template>
  <!-- 제품 목록 검색 / 필터 -->
  <v-card
    class="search-filter mb-5"
    variant="flat"
    rounded="lg"
  >
    <v-card-text>
      <div class="filter-grid">
        <v-text-field
          class="search-field"
          :model-value="searchQuery"
          prepend-inner-icon="mdi-magnify"
          label="제품 검색"
          placeholder="제품명"
          variant="outlined"
          density="comfortable"
          clearable
          hide-details
          @update:model-value="$emit('update:searchQuery', $event ?? '')"
        />

        <v-select
          v-if="showStore"
          :model-value="storeFilter"
          :items="storeItems"
          label="점포"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:storeFilter', $event)"
        />

        <v-select
          :model-value="categoryFilter"
          :items="categoryItems"
          label="카테고리"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:categoryFilter', $event)"
        />

        <v-select
          :model-value="statusFilter"
          :items="statusItems"
          label="상태"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:statusFilter', $event)"
        />

        <v-select
          :model-value="salesTypeFilter"
          :items="salesTypeItems"
          label="판매 유형"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:salesTypeFilter', $event)"
        />

        <v-select
          class="last-filter"
          :model-value="itemsPerPage"
          :items="pageSizeItems"
          label="페이지당"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:itemsPerPage', $event)"
        />
      </div>

      <div class="filter-summary mt-3">
        <div class="text-caption text-medium-emphasis">
          전체 {{ total }}개 · 검색 결과 {{ filtered }}개
        </div>

        <v-btn
          v-if="hasActiveFilters"
          size="small"
          variant="text"
          prepend-icon="mdi-filter-remove-outline"
          @click="$emit('reset')"
        >
          검색 초기화
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
defineProps({
  searchQuery: {
    type: String,
    default: '',
  },
  storeFilter: {
    type: [String, Number],
    default: 'all',
  },
  categoryFilter: {
    type: [String, Number],
    default: 'all',
  },
  statusFilter: {
    type: String,
    default: 'active',
  },
  salesTypeFilter: {
    type: String,
    default: 'all',
  },
  itemsPerPage: {
    type: Number,
    default: 10,
  },
  storeItems: {
    type: Array,
    default: () => [],
  },
  categoryItems: {
    type: Array,
    default: () => [],
  },
  statusItems: {
    type: Array,
    default: () => [],
  },
  salesTypeItems: {
    type: Array,
    default: () => [],
  },
  pageSizeItems: {
    type: Array,
    default: () => [],
  },
  showStore: {
    type: Boolean,
    default: false,
  },
  total: {
    type: Number,
    default: 0,
  },
  filtered: {
    type: Number,
    default: 0,
  },
  hasActiveFilters: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'update:searchQuery',
  'update:storeFilter',
  'update:categoryFilter',
  'update:statusFilter',
  'update:salesTypeFilter',
  'update:itemsPerPage',
  'reset',
]);
</script>

<style scoped>
.search-filter {
  overflow: hidden;
  background: rgba(var(--v-theme-on-surface), 0.025);
  border: 1px solid rgba(var(--v-border-color), 0.14);
}

.search-filter :deep(.v-field) {
  --v-field-border-opacity: 0.18;
}

.search-filter :deep(.v-field--focused) {
  --v-field-border-opacity: 0.34;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.search-field {
  grid-column: 1 / -1;
}

.last-filter:nth-child(even) {
  grid-column: 1 / -1;
}

.filter-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
}

@media (max-width: 360px) {
  .filter-grid {
    grid-template-columns: 1fr;
  }

  .search-field,
  .last-filter {
    grid-column: auto;
  }
}
</style>
