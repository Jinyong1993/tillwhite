<template>
  <v-card
    class="product-card"
    :class="{ 'product-card--deleted': product.deleted_at }"
    variant="outlined"
    rounded="lg"
  >
    <v-card-text class="pa-0">
      <div class="product-card-header">
        <div class="min-width-0">
          <div class="product-name">{{ product.name || '-' }}</div>
          <!-- 카테고리는 제품명의 보조 정보이므로 칩 대신 서브타이틀로 표시합니다. -->
          <div class="product-category">{{ product.category?.name ?? '카테고리 없음' }}</div>
        </div>

        <div class="product-status-area">
          <v-chip
            v-if="product.deleted_at"
            size="small"
            prepend-icon="mdi-delete-clock-outline"
            variant="tonal"
          >
            삭제됨
          </v-chip>

          <v-menu v-else-if="canManage" location="bottom end">
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

            <v-list density="compact" min-width="150">
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

      <!-- 직원 카드와 같은 정보 블록 패턴으로 핵심 제품 정보를 정리합니다. -->
      <div class="product-info-area">
        <div class="product-info-grid">
          <div class="product-info-item">
            <div class="product-info-label"><v-icon icon="mdi-tag-outline" size="16" />판매유형</div>
            <div class="product-info-value">{{ product.sales_type === 'limited' ? '기간한정' : '상시' }}</div>
          </div>
          <div class="product-info-item">
            <div class="product-info-label"><v-icon icon="mdi-currency-krw" size="16" />판매가</div>
            <div class="product-info-value">{{ formatPrice(currentPrice) }}</div>
          </div>
          <div class="product-info-item">
            <div class="product-info-label"><v-icon icon="mdi-store-outline" size="16" />점포</div>
            <div class="product-info-value">{{ product.store?.name ?? '-' }}</div>
          </div>
        </div>

        <div v-if="product.sales_type === 'limited'" class="product-period">
          <div class="product-info-label"><v-icon icon="mdi-calendar-range-outline" size="16" />판매기간</div>
          <div class="product-period-value">{{ formatDate(product.sales_start_date) }} ~ {{ formatDate(product.sales_end_date) }}</div>
        </div>
      </div>

      <v-divider />
      <div class="product-card-actions">
        <v-btn block variant="text" prepend-icon="mdi-package-variant-closed" @click="$emit('detail', product)">상세보기</v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  product: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

defineEmits(['detail', 'status-change']);

const currentPrice = computed(() => props.product.prices?.[0]?.price ?? null);

function formatPrice(value) {
  if (value === null || value === undefined) return '-';
  return `${Number(value).toLocaleString('ko-KR')}원`;
}

function formatDate(value) {
  if (!value) return '-';
  return String(value).slice(0, 10).replaceAll('-', '.');
}
</script>

<style scoped>
.product-card { overflow: hidden; transition: opacity 160ms ease, background-color 160ms ease; }
.product-card--deleted { background: rgba(var(--v-theme-on-surface), 0.025); opacity: 0.58; }
.product-card--deleted:hover { opacity: 0.76; }
.product-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 18px 16px; }
.min-width-0 { min-width: 0; }
.product-name { font-size: 1rem; font-weight: 700; line-height: 1.4; overflow-wrap: anywhere; word-break: break-word; }
.product-category { margin-top: 3px; color: rgba(var(--v-theme-on-surface), 0.55); font-size: 0.8rem; line-height: 1.4; overflow-wrap: anywhere; }
.product-status-area { flex: 0 0 auto; }
.status-chip--interactive { cursor: pointer; }
.product-info-area { padding: 0 18px 18px; }
.product-info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 8px; }
.product-info-item { min-width: 0; padding: 12px; }
.product-info-item + .product-info-item { border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.product-info-label { display: flex; align-items: center; gap: 5px; color: rgba(var(--v-theme-on-surface), 0.55); font-size: 0.72rem; line-height: 1.3; }
.product-info-value { margin-top: 6px; font-size: 0.875rem; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; word-break: break-word; }
.product-period { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 10px; padding: 10px 12px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 8px; }
.product-period-value { min-width: 0; font-size: 0.82rem; font-weight: 600; text-align: right; overflow-wrap: anywhere; }
.product-card-actions { padding: 8px; }

@media (max-width: 480px) {
  .product-card-header { padding: 16px; }
  .product-info-area { padding: 0 16px 16px; }
  .product-info-grid { grid-template-columns: 1fr; }
  .product-info-item + .product-info-item { border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-left: 0; }
}

@media (max-width: 340px) {
  .product-card-header { flex-direction: column; gap: 10px; }
  .product-period { align-items: flex-start; flex-direction: column; gap: 6px; }
  .product-period-value { text-align: left; }
}
</style>
