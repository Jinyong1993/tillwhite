<template>
  <!-- 직원 검색 및 필터 영역 -->
  <v-card
    class="employee-toolbar mb-5"
    variant="flat"
    rounded="lg"
  >
    <v-card-text>
      <div class="employee-toolbar-grid">
        <!-- 직원 검색 -->
        <v-text-field
          class="employee-search-field"
          :model-value="searchQuery"
          prepend-inner-icon="mdi-magnify"
          label="직원 검색"
          placeholder="이름, 사번, 연락처"
          variant="outlined"
          density="comfortable"
          clearable
          hide-details
          @update:model-value="
            $emit('update:searchQuery', $event ?? '')
          "
        />

        <!-- 재직 상태 필터 -->
        <v-select
          class="employee-status-filter"
          :model-value="statusFilter"
          :items="statusItems"
          label="상태"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="
            $emit('update:statusFilter', $event)
          "
        />

        <!-- 페이지당 표시 개수 -->
        <v-select
          class="employee-page-size"
          :model-value="itemsPerPage"
          :items="pageSizeItems"
          label="페이지당"
          variant="outlined"
          density="comfortable"
          hide-details
          @update:model-value="
            $emit('update:itemsPerPage', $event)
          "
        />
      </div>

      <!-- 전체 직원 수 / 현재 검색 결과 -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-2 mt-3"
      >
        <div class="text-caption text-medium-emphasis">
          전체 {{ total }}명 · 검색 결과 {{ filtered }}명
        </div>

        <!-- 검색 조건이 적용된 경우에만 초기화 버튼 표시 -->
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
/*
 * 부모 컴포넌트에서 관리하는 검색 / 필터 상태를 전달받는다.
 */
defineProps({
  searchQuery: String,
  statusFilter: String,
  itemsPerPage: Number,
  statusItems: Array,
  pageSizeItems: Array,
  total: Number,
  filtered: Number,
  hasActiveFilters: Boolean,
});

/*
 * 검색 / 필터 값이 변경되면 부모 컴포넌트에 전달한다.
 */
defineEmits([
  'update:searchQuery',
  'update:statusFilter',
  'update:itemsPerPage',
  'reset',
]);
</script>

<style scoped>
/* 검색 / 필터 영역 */
.employee-toolbar {
  overflow: hidden;

  background: rgba(var(--v-theme-on-surface), 0.025);
  border: 1px solid rgba(var(--v-border-color), 0.14);
}

/* 검색창은 한 줄, 상태와 페이지당 항목은 2열로 표시 */
.employee-toolbar-grid {
  display: grid;

  grid-template-columns:
    minmax(0, 1fr)
    minmax(0, 1fr);

  grid-template-areas:
    "search search"
    "status page-size";

  gap: 10px;
}

.employee-search-field {
  grid-area: search;
}

.employee-status-filter {
  grid-area: status;
}

.employee-page-size {
  grid-area: page-size;
}

/* 입력 필드 테두리 */
.employee-toolbar :deep(.v-field) {
  --v-field-border-opacity: 0.18;
}

.employee-toolbar :deep(.v-field--focused) {
  --v-field-border-opacity: 0.34;
}

/* 작은 화면에서는 모든 항목을 한 줄씩 표시 */
@media (max-width: 340px) {
  .employee-toolbar-grid {
    grid-template-columns: 1fr;

    grid-template-areas:
      "search"
      "status"
      "page-size";
  }
}
</style>