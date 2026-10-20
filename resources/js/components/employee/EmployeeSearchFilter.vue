<template>
  <!-- 직원 검색 및 필터 -->
  <div class="app-card border rounded-lg pa-3 pa-sm-4 mb-3">
    <!-- 검색 및 필터 입력 -->
    <div class="d-flex flex-column ga-2">
      <!-- 직원 검색: 전체 너비 -->
      <v-text-field
        class="app-input w-100"
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

      <!-- 상태 / 페이지당 -->
      <div class="d-flex align-start ga-2">
        <!-- 재직 상태 -->
        <v-select
          class="app-select flex-grow-1"
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
          class="app-select flex-grow-1"
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
    </div>

    <!-- 전체 직원 수 / 검색 결과 / 초기화 -->
    <div
      class="d-flex flex-wrap align-center justify-space-between ga-2 mt-3"
    >
      <div class="app-result-summary text-medium-emphasis">
        전체 {{ total }}명 · 검색 결과 {{ filtered }}명
      </div>

      <v-btn
        v-if="hasActiveFilters"
        class="app-btn"
        size="small"
        variant="text"
        @click="$emit('reset')"
      >
        검색 초기화
      </v-btn>
    </div>
  </div>
</template>

<script setup>
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

defineEmits([
  'update:searchQuery',
  'update:statusFilter',
  'update:itemsPerPage',
  'reset',
]);
</script>