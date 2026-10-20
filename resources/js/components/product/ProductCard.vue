<template>
  <v-card
    class="product-card"
    :class="{ 'product-card--deleted': product.deleted_at }"
    variant="outlined"
    rounded="lg"
  >
    <v-card-text class="pa-0">
      <!-- 제품명 / 카테고리 / 상태 -->
      <div
        class="product-card-header d-flex align-start justify-space-between ga-2 pa-3"
      >
        <div class="product-card-identity d-flex flex-wrap align-center ga-2">
          <div class="product-name">
            {{ product.name || '-' }}
          </div>

          <div class="product-category">
            {{ product.category?.name ?? '-' }}
          </div>
        </div>

        <div class="product-status-area d-flex align-center">
          <v-chip
            v-if="product.deleted_at"
            size="small"
            prepend-icon="mdi-delete-clock-outline"
            variant="tonal"
          >
            삭제됨
          </v-chip>

          <v-menu
            v-else-if="canManage"
            location="bottom end"
          >
            <template #activator="{ props: menuProps }">
              <v-chip
                v-bind="menuProps"
                class="status-chip--interactive"
                size="small"
                :color="product.is_active ? 'success' : 'warning'"
                variant="tonal"
                append-icon="mdi-chevron-down"
                :disabled="loading"
              >
                {{ product.is_active ? '취급중' : '취급중단' }}
              </v-chip>
            </template>

            <v-list
              density="compact"
              min-width="150"
            >
              <v-list-item
                title="취급중"
                prepend-icon="mdi-check-circle-outline"
                :disabled="product.is_active || loading"
                @click="$emit('status-change', true)"
              />

              <v-list-item
                title="취급중단"
                prepend-icon="mdi-pause-circle-outline"
                :disabled="!product.is_active || loading"
                @click="$emit('status-change', false)"
              />
            </v-list>
          </v-menu>

          <v-chip
            v-else
            size="small"
            :color="product.is_active ? 'success' : 'warning'"
            variant="tonal"
          >
            {{ product.is_active ? '취급중' : '취급중단' }}
          </v-chip>
        </div>
      </div>

      <!-- 제품 정보 -->
      <div class="px-3 pb-3">
        <div class="product-info-grid">
          <!-- 판매유형 -->
          <div class="product-info-item">
            <div class="product-info-label d-flex align-center ga-1">
              <v-icon
                icon="mdi-tag-outline"
                size="16"
              />
              판매유형
            </div>

            <div class="product-info-value">
              {{ product.sales_type === 'limited' ? '기간한정' : '상시' }}
            </div>
          </div>

          <!-- 판매가 -->
          <div class="product-info-item">
            <div class="product-info-label d-flex align-center ga-1">
              <v-icon
                icon="mdi-currency-krw"
                size="16"
              />
              판매가
            </div>

            <div class="product-info-value">
              {{ formatPrice(currentPrice) }}
            </div>
          </div>

          <!-- 점포 -->
          <div
            class="product-info-item product-info-item--store d-flex flex-wrap align-center justify-space-between ga-2"
          >
            <div class="product-info-label d-flex align-center ga-1">
              <v-icon
                icon="mdi-store-outline"
                size="16"
              />
              점포
            </div>

            <div class="product-info-value product-store-value">
              {{ product.store?.name ?? '-' }}
            </div>
          </div>
        </div>

        <!-- 기간한정 제품의 판매기간 -->
        <div
          v-if="product.sales_type === 'limited'"
          class="product-period d-flex flex-wrap align-center justify-space-between ga-2 mt-2"
        >
          <div class="product-info-label d-flex align-center ga-1">
            <v-icon
              icon="mdi-calendar-range-outline"
              size="16"
            />
            판매기간
          </div>

          <div class="product-period-value">
            {{ formatDate(product.sales_start_date) }}
            ~
            {{ formatDate(product.sales_end_date) }}
          </div>
        </div>
      </div>

      <v-divider />

      <!-- 상세보기 -->
      <div class="d-flex justify-center pa-3">
        <v-btn
          class="app-btn"
          block
          variant="tonal"
          @click="$emit('detail', product)"
        >
          상세보기
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
  canManage: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'detail',
  'status-change',
]);

// 기존 API의 최신 가격 우선 정책 유지
const currentPrice = computed(() => {
  return props.product.prices?.[0]?.price ?? null;
});

function formatPrice(value) {
  if (value === null || value === undefined) {
    return '-';
  }

  return `${Number(value).toLocaleString('ko-KR')}원`;
}

function formatDate(value) {
  if (!value) {
    return '-';
  }

  return String(value)
    .slice(0, 10)
    .replaceAll('-', '.');
}
</script>

<style scoped>
.product-card {
  min-width: 0;
  overflow: hidden;
  border-color: rgba(var(--v-theme-on-surface), 0.12);
  transition:
    opacity 160ms ease,
    background-color 160ms ease;
}

.product-card--deleted {
  background: rgba(var(--v-theme-on-surface), 0.025);
  opacity: 0.58;
}

.product-card--deleted:hover {
  opacity: 0.76;
}

.product-card-header {
  min-width: 0;
}

.product-card-identity {
  flex: 1 1 0;
  min-width: 0;
}

.product-name {
  min-width: 0;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.product-category {
  min-width: 0;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.8rem;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.product-status-area {
  flex: 0 0 auto;
}

.status-chip--interactive {
  cursor: pointer;
}

.product-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
}

.product-info-item {
  min-width: 0;
  padding: 10px 12px;
}

.product-info-item:nth-child(2) {
  border-left: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.product-info-item--store {
  grid-column: 1 / -1;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.product-info-label {
  min-width: 0;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.72rem;
  line-height: 1.3;
  white-space: nowrap;
}

.product-info-label .v-icon {
  flex: 0 0 auto;
}

.product-info-value {
  min-width: 0;
  margin-top: 6px;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.4;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.product-store-value {
  flex: 1 1 120px;
  margin-top: 0;
  text-align: right;
}

.product-period {
  min-width: 0;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
}

.product-period-value {
  min-width: 0;
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.4;
  text-align: right;
  overflow-wrap: anywhere;
}
</style>