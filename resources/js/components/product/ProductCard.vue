<template>
  <!--
    제품 카드

    목록에서는 제품을 빠르게 식별하는 데 필요한 핵심 정보만 표시하고,
    가격 이력/레시피/등록일 같은 상세정보는 상세 다이얼로그에서 확인합니다.
  -->
  <v-card
    class="product-card"
    :class="{ 'product-card--deleted': product.deleted_at }"
    variant="outlined"
    rounded="lg"
  >
    <v-card-text class="pa-0">
      <div class="product-card-header">
        <div class="min-width-0">
          <div class="product-name">
            {{ product.name || '-' }}
          </div>

          <div class="product-category">
            {{ product.category?.name ?? '카테고리 없음' }}
          </div>
        </div>

        <div class="product-status-chips">
          <v-chip
            v-if="product.deleted_at"
            size="small"
            prepend-icon="mdi-delete-clock-outline"
            variant="tonal"
          >
            삭제됨
          </v-chip>

          <template v-else>
            <v-chip
              size="small"
              :color="product.is_active ? 'success' : 'warning'"
              variant="tonal"
            >
              {{ product.is_active ? '취급중' : '취급중단' }}
            </v-chip>

            <v-chip
              v-if="product.sales_type === 'limited'"
              size="small"
              variant="tonal"
            >
              기간한정
            </v-chip>
          </template>
        </div>
      </div>

      <div class="product-info-grid">
        <div class="product-info-item">
          <div class="product-info-label">
            <v-icon icon="mdi-currency-krw" size="16" />
            판매가
          </div>
          <div class="product-info-value">
            {{ formatPrice(currentPrice) }}
          </div>
        </div>

        <div class="product-info-item">
          <div class="product-info-label">
            <v-icon icon="mdi-store-outline" size="16" />
            점포
          </div>
          <div class="product-info-value">
            {{ product.store?.name ?? '-' }}
          </div>
        </div>
      </div>

      <div
        v-if="product.sales_type === 'limited'"
        class="product-period"
      >
        <v-icon icon="mdi-calendar-range-outline" size="16" />
        {{ formatDate(product.sales_start_date) }} ~ {{ formatDate(product.sales_end_date) }}
      </div>

      <v-divider />

      <div class="product-card-actions">
        <v-btn
          block
          variant="text"
          prepend-icon="mdi-package-variant-closed"
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
});

defineEmits([
  'detail',
]);

/** 현재 적용 중인 최신 가격을 반환합니다. */
const currentPrice = computed(() => props.product.prices?.[0]?.price ?? null);

/** 원화 가격을 읽기 쉬운 형식으로 표시합니다. */
function formatPrice(value) {
  if (value === null || value === undefined) {
    return '-';
  }

  return `${Number(value).toLocaleString('ko-KR')}원`;
}

/** API 날짜를 화면용 YYYY.MM.DD 형식으로 표시합니다. */
function formatDate(value) {
  if (!value) {
    return '-';
  }

  return String(value).slice(0, 10).replaceAll('-', '.');
}
</script>

<style scoped>
.product-card {
  overflow: hidden;
}

.product-card--deleted {
  opacity: 0.62;
}

.product-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 16px;
}

.min-width-0 {
  min-width: 0;
}

.product-name {
  overflow: hidden;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-category {
  margin-top: 3px;
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.78rem;
}

.product-status-chips {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}

.product-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  padding: 0 16px 14px;
}

.product-info-label {
  display: flex;
  align-items: center;
  gap: 5px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.74rem;
}

.product-info-value {
  margin-top: 4px;
  overflow: hidden;
  font-size: 0.88rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-period {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 16px 14px;
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-size: 0.78rem;
}

.product-card-actions {
  padding: 4px 8px;
}

@media (max-width: 360px) {
  .product-card-header {
    flex-direction: column;
  }

  .product-status-chips {
    justify-content: flex-start;
  }
}
</style>
