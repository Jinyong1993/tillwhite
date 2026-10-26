<template>
  <!-- 제품 목록 검색 / 필터 -->
  <v-card
    class="search-filter mb-5"
    variant="flat"
    rounded="lg"
  >
    <v-card-text>
      <div
        class="filter-grid"
        :class="{
          'filter-grid--with-store': showStore,
          'filter-grid--expanded': mobileFiltersOpen,
        }"
      >
        <!-- 제품 검색 -->
        <v-text-field
          class="search-field app-input"
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

        <!-- 점포 -->
        <v-select
          v-if="showStore"
          class="store-field mobile-extra-filter app-select"
          :model-value="storeFilter"
          :items="storeItems"
          label="점포"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:storeFilter', $event)"
        />

        <!-- 카테고리 -->
        <v-select
          class="category-field mobile-extra-filter app-select"
          :model-value="categoryFilter"
          :items="categoryItems"
          label="카테고리"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:categoryFilter', $event)"
        />

        <!-- 상태 -->
        <v-select
          class="status-field app-select"
          :model-value="statusFilter"
          :items="statusItems"
          label="상태"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:statusFilter', $event)"
        />

        <!-- 판매 유형 -->
        <v-select
          class="sales-type-field mobile-extra-filter app-select"
          :model-value="salesTypeFilter"
          :items="salesTypeItems"
          label="판매 유형"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:salesTypeFilter', $event)"
        />

        <!-- 페이지당 표시 개수 -->
        <v-select
          class="last-filter mobile-extra-filter app-select"
          :model-value="itemsPerPage"
          :items="pageSizeItems"
          label="페이지당"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="$emit('update:itemsPerPage', $event)"
        />

        <!-- 모바일 상세 필터 -->
        <v-btn
          class="app-btn filter-toggle"
          variant="tonal"
          block
          :aria-expanded="mobileFiltersOpen"
          @click="mobileFiltersOpen = !mobileFiltersOpen"
        >
          {{
            mobileFiltersOpen
              ? '상세 필터 접기'
              : hasHiddenFilters
                ? '상세 필터 적용 중 · 펼치기'
                : '상세 필터 펼치기'
          }}
        </v-btn>
      </div>

      <!-- 검색 결과 요약 -->
      <div
        class="d-flex flex-wrap align-center justify-space-between ga-2 mt-3"
      >
        <div class="app-result-summary text-medium-emphasis">
          전체 {{ total }}개 · 검색 결과 {{ filtered }}개
        </div>

        <v-btn
          v-if="hasActiveFilters"
          size="small"
          variant="text"
          @click="$emit('reset')"
        >
          검색 초기화
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
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

const mobileFiltersOpen = ref(false);

const hasHiddenFilters = computed(() => (
  (props.showStore && String(props.storeFilter) !== 'all')
  || String(props.categoryFilter) !== 'all'
  || props.salesTypeFilter !== 'all'
  || props.itemsPerPage !== 10
));
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

.filter-grid > * {
  min-width: 0;
}

.search-field {
  grid-column: 1 / -1;
}

.filter-grid--with-store .last-filter {
  grid-column: 1 / -1;
}

.filter-toggle {
  display: none;
}

@media (max-width: 599px) {
  .filter-grid {
    display: flex;
    flex-direction: column;
  }

  .search-field {
    order: 1;
  }

  .status-field {
    order: 2;
  }

  .filter-toggle {
    display: flex;
    order: 3;
    min-width: 0;
    height: auto;
    min-height: 44px;
    padding-block: 8px;
    white-space: normal;
  }

  .store-field {
    order: 4;
  }

  .category-field {
    order: 5;
  }

  .sales-type-field {
    order: 6;
  }

  .last-filter {
    order: 7;
  }

  .mobile-extra-filter {
    display: none;
  }

  .filter-grid--expanded .mobile-extra-filter {
    display: grid;
  }
}
</style>