<template>
  <v-card
    class="product-card"
    :class="{ 'product-card--deleted': product.deleted_at }"
    variant="outlined"
    rounded="lg"
  >
    <v-card-text class="pa-0">
      <!--
        제품명과 카테고리를 카드의 대표 정보로 표시합니다.
        삭제된 제품은 상태 영역에서 별도의 삭제 상태를 표시합니다.
      -->
      <div class="product-card-header">
        <div class="min-width-0">
          <div class="product-name">
            {{ product.name || '-' }}
          </div>

          <!-- 카테고리는 제품명의 보조 정보이므로 칩 대신 서브타이틀로 표시합니다. -->
          <div class="product-category">
            {{ product.category?.name ?? '-' }}
          </div>
        </div>

        <!--
          제품 상태를 표시합니다.
          관리 권한이 있는 경우에만 상태 변경 메뉴를 사용할 수 있습니다.
        -->
        <div class="product-status-area">
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

          <!-- 관리 권한이 없으면 상태 확인만 가능하도록 일반 칩으로 표시합니다. -->
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

      <!--
        직원 카드와 동일한 정보 블록 패턴을 사용합니다.
        판매유형, 판매가, 점포처럼 자주 확인하는 정보만 카드에 노출합니다.
      -->
      <div class="product-info-area">
        <div class="product-info-grid">
          <div class="product-info-item">
            <div class="product-info-label">
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

          <div class="product-info-item">
            <div class="product-info-label">
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

          <div class="product-info-item">
            <div class="product-info-label">
              <v-icon
                icon="mdi-store-outline"
                size="16"
              />
              점포
            </div>

            <div class="product-info-value">
              {{ product.store?.name ?? '-' }}
            </div>
          </div>
        </div>

        <!-- 기간한정 제품에서만 실제 판매기간을 추가로 표시합니다. -->
        <div
          v-if="product.sales_type === 'limited'"
          class="product-period"
        >
          <div class="product-info-label">
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

      <!-- 카드에서는 상세보기만 제공하고 나머지 관리 작업은 상세 화면에서 처리합니다. -->
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

// 가격 이력은 최신 데이터가 첫 번째에 전달된다는 기존 API 구조를 그대로 사용합니다.
const currentPrice = computed(() => {
  return props.product.prices?.[0]?.price ?? null;
});

/*
 * 판매가를 원화 형식으로 표시합니다.
 * 가격 정보가 없는 경우 숫자 변환 대신 '-'를 표시합니다.
 */
function formatPrice(value) {
  if (value === null || value === undefined) {
    return '-';
  }

  return `${Number(value).toLocaleString('ko-KR')}원`;
}

/*
 * API 날짜값에서 날짜 부분만 추출해 화면용 형식으로 변환합니다.
 * 날짜가 없는 경우에는 '-'를 표시합니다.
 */
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
  overflow: hidden;
  transition:
    opacity 160ms ease,
    background-color 160ms ease;
}

/*
 * 삭제된 제품은 일반 제품과 명확히 구분하되
 * 상세 내용을 확인할 수 있도록 완전히 숨기지는 않습니다.
 */
.product-card--deleted {
  background: rgba(var(--v-theme-on-surface), 0.025);
  opacity: 0.58;
}

.product-card--deleted:hover {
  opacity: 0.76;
}

.product-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 18px 16px;
}

.min-width-0 {
  min-width: 0;
}

.product-name {
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.product-category {
  margin-top: 3px;
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

.product-info-area {
  padding: 0 18px 18px;
}

.product-info-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.product-info-item {
  min-width: 0;
  padding: 12px;
}

.product-info-item + .product-info-item {
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.product-info-label {
  display: flex;
  align-items: center;
  gap: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.72rem;
  line-height: 1.3;
}

.product-info-value {
  margin-top: 6px;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.4;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.product-period {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 10px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.product-period-value {
  min-width: 0;
  font-size: 0.82rem;
  font-weight: 600;
  text-align: right;
  overflow-wrap: anywhere;
}

.product-card-actions {
  padding: 8px;
}

/*
 * 작은 화면에서는 제품 정보 세 칸을 세로로 배치합니다.
 * 긴 제품명이나 점포명이 있어도 카드 밖으로 넘치지 않도록 합니다.
 */
@media (max-width: 480px) {
  .product-card-header {
    padding: 16px;
  }

  .product-info-area {
    padding: 0 16px 16px;
  }

  .product-info-grid {
    grid-template-columns: 1fr;
  }

  .product-info-item + .product-info-item {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-left: 0;
  }
}

/*
 * 매우 좁은 화면에서는 제품명과 상태를 세로로 분리하고
 * 판매기간도 두 줄 구조로 전환해 겹침을 방지합니다.
 */
@media (max-width: 340px) {
  .product-card-header {
    flex-direction: column;
    gap: 10px;
  }

  .product-period {
    align-items: flex-start;
    flex-direction: column;
    gap: 6px;
  }

  .product-period-value {
    text-align: left;
  }
}
</style>