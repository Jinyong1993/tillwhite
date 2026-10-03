<template>
  <!-- 제품 목록에 표시되는 제품 카드 -->
  <v-card
    class="product-card"
    :class="{ 'product-card--deleted': product.deleted_at }"
    variant="outlined"
    rounded="lg"
  >
    <v-card-text class="pa-0">
      <!-- 제품명 / 카테고리 / 상태 -->
      <div class="product-card-header">
        <div class="min-width-0">
          <!-- 제품명 -->
          <div class="product-name">
            {{ product.name || '-' }}
          </div>

          <!-- 제품 카테고리 -->
          <div class="product-category mt-2">
            <v-chip
              size="small"
              variant="tonal"
            >
              {{ product.category?.name ?? '카테고리 없음' }}
            </v-chip>
          </div>
        </div>

        <!-- 제품 상태 -->
        <div class="product-status-chips">
          <!-- 삭제된 제품 -->
          <v-chip
            v-if="product.deleted_at"
            size="small"
            prepend-icon="mdi-delete-clock-outline"
            variant="tonal"
          >
            삭제됨
          </v-chip>

          <!-- 정상 제품 -->
          <template v-else>
            <!-- 취급 상태 -->
            <v-chip
              size="small"
              :color="product.is_active ? 'success' : 'warning'"
              variant="tonal"
            >
              {{ product.is_active ? '취급중' : '취급중단' }}
            </v-chip>

            <!-- 기간한정 제품 -->
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

      <!-- 제품 기본 정보 -->
      <div class="product-info-grid">
        <!-- 판매유형 -->
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

        <!-- 판매가 -->
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

        <!-- 점포 -->
        <div class="product-info-item product-info-item--full">
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

      <!-- 기간한정 제품의 판매기간 -->
      <div
        v-if="product.sales_type === 'limited'"
        class="product-period"
      >
        <v-icon
          icon="mdi-calendar-range-outline"
          size="16"
        />

        <span>
          {{ formatDate(product.sales_start_date) }}
          ~
          {{ formatDate(product.sales_end_date) }}
        </span>
      </div>

      <v-divider />

      <!-- 카드 하단 기능 -->
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


/*
 * Props
 */
const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
});


/*
 * Events
 */
defineEmits([
  'detail',
]);


/*
 * 현재 적용 중인 판매가
 *
 * 제품 API에서 가격 이력이 최신순으로 전달되므로
 * 첫 번째 가격을 현재 판매가로 사용한다.
 */
const currentPrice = computed(() => {
  return props.product.prices?.[0]?.price ?? null;
});


/*
 * 원화 가격 표시
 *
 * 예:
 * 3500 -> 3,500원
 */
function formatPrice(value) {
  if (value === null || value === undefined) {
    return '-';
  }

  return `${Number(value).toLocaleString('ko-KR')}원`;
}


/*
 * 날짜 표시 형식 변환
 *
 * 예:
 * 2026-10-03 -> 2026.10.03
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
/* 제품 카드 */
.product-card {
  overflow: hidden;
}


/* 삭제된 제품은 일반 제품과 시각적으로 구분 */
.product-card--deleted {
  opacity: 0.62;
}


/* 제품명 / 카테고리 / 상태 영역 */
.product-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  gap: 12px;
  padding: 18px 18px 16px;
}


/* Flex/Grid 내부의 긴 텍스트가 영역을 밀어내지 않도록 처리 */
.min-width-0 {
  min-width: 0;
}


/* 제품명 */
.product-name {
  min-width: 0;

  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;

  overflow-wrap: anywhere;
  word-break: break-word;
}


/* 카테고리 */
.product-category {
  margin-top: 4px;
  line-height: 1.4;
}


/* 상태 칩 영역 */
.product-status-chips {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  justify-content: flex-end;

  gap: 6px;
}


/* 제품 기본 정보 */
.product-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));

  gap: 12px;
  padding: 0 18px 16px;
}


/* 정보 제목 */
.product-info-label {
  display: flex;
  align-items: center;

  gap: 5px;

  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.74rem;
}


/* 정보 값 */
.product-info-value {
  min-width: 0;
  margin-top: 4px;

  font-size: 0.88rem;
  font-weight: 600;

  overflow-wrap: anywhere;
  word-break: break-word;
}


/* 마지막 단독 정보는 전체 너비 사용 */
.product-info-item--full {
  grid-column: 1 / -1;
}


/* 기간한정 판매기간 */
.product-period {
  display: flex;
  align-items: flex-start;

  gap: 6px;
  margin: 0 18px 16px;

  color: rgba(var(--v-theme-on-surface), 0.68);
  font-size: 0.78rem;

  overflow-wrap: anywhere;
  word-break: break-word;
}


/* 카드 하단 버튼 */
.product-card-actions {
  padding: 4px 8px;
}


/*
 * 작은 화면 대응
 *
 * 제품명과 상태 칩을 한 줄에 억지로 배치하지 않고
 * 상태 영역을 아래로 내려 텍스트 겹침을 방지한다.
 */
@media (max-width: 360px) {
  .product-card-header {
    flex-direction: column;
  }

  .product-status-chips {
    justify-content: flex-start;
  }
}
</style>