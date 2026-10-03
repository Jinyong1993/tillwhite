<template>
  <v-dialog
    :model-value="modelValue"
    max-width="560"
    @update:model-value="handleDialogChange"
    @after-leave="$emit('closed')"
  >
    <v-card
      v-if="product"
      class="product-detail-dialog"
      rounded="lg"
    >
      <!-- 직원 상세보기와 동일한 구조의 고정 헤더입니다. -->
      <div class="detail-header">
        <div class="d-flex align-start justify-space-between ga-4">
          <div class="min-width-0">
            <div class="text-h6 font-weight-bold text-truncate">
              {{ product.name ?? '제품 상세' }}
            </div>

            <!-- 제품명보다 한 단계 작은 보조 정보로 카테고리를 표시합니다. -->
            <div class="text-body-2 text-medium-emphasis mt-1">
              {{ product.category?.name ?? '카테고리 없음' }}
            </div>
          </div>

          <div class="detail-header-actions">
            <v-chip
              size="small"
              :color="product.deleted_at ? undefined : (product.is_active ? 'success' : 'warning')"
              variant="tonal"
            >
              {{ product.deleted_at ? '삭제됨' : (product.is_active ? '취급중' : '취급중단') }}
            </v-chip>

            <!-- 자주 쓰지 않는 관리 동작은 직원 상세보기처럼 우측 메뉴에 정리합니다. -->
            <v-menu
              v-if="!product.deleted_at && (canManage || canManageRecipe)"
              location="bottom end"
            >
              <template #activator="{ props: menuProps }">
                <v-btn
                  v-bind="menuProps"
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  aria-label="제품 관리 메뉴"
                />
              </template>

              <v-list density="compact" min-width="190">
                <v-list-item
                  v-if="canManageRecipe"
                  :prepend-icon="hasRecipe ? 'mdi-notebook-edit-outline' : 'mdi-notebook-plus-outline'"
                  :title="hasRecipe ? '레시피 수정' : '레시피 등록'"
                  @click="$emit('recipe')"
                />

                <v-list-item
                  v-if="canManage"
                  prepend-icon="mdi-swap-horizontal"
                  :title="product.is_active ? '취급중단' : '취급재개'"
                  @click="$emit('toggle')"
                />
              </v-list>
            </v-menu>
          </div>
        </div>
      </div>

      <v-divider />

      <!-- 상세 내용만 스크롤하고 헤더/하단 버튼은 고정합니다. -->
      <div class="detail-scroll-area">
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon icon="mdi-information-outline" size="small" />
            기본 정보
          </div>

          <div class="detail-grid">
            <InfoItem label="제품명" :value="product.name" />
            <InfoItem label="점포" :value="product.store?.name" />
            <InfoItem label="카테고리" :value="product.category?.name" />
            <InfoItem label="현재 판매가" :value="formatPrice(currentPrice)" />
            <InfoItem label="생산 부서" :value="departmentName(product.production_department)" />
            <InfoItem label="관리 부서" :value="departmentName(product.management_department)" />
          </div>
        </section>

        <v-divider />

        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon icon="mdi-calendar-check-outline" size="small" />
            판매 정보
          </div>

          <div class="detail-grid">
            <InfoItem
              label="취급 상태"
              :value="product.is_active ? '취급중' : '취급중단'"
            />
            <InfoItem
              label="판매 유형"
              :value="product.sales_type === 'limited' ? '기간 한정' : '상시'"
            />
            <InfoItem
              v-if="product.sales_type === 'limited'"
              label="판매 시작일"
              :value="formatDate(product.sales_start_date)"
            />
            <InfoItem
              v-if="product.sales_type === 'limited'"
              label="판매 종료일"
              :value="formatDate(product.sales_end_date)"
            />
            <InfoItem label="표시 순서" :value="String(product.sort_order ?? 0)" />
          </div>
        </section>

        <v-divider />

        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon icon="mdi-notebook-outline" size="small" />
            레시피
          </div>

          <div
            v-if="product.recipes?.length"
            class="recipe-list"
          >
            <div
              v-for="recipe in product.recipes"
              :key="recipe.id"
              class="recipe-item"
            >
              <div class="font-weight-medium">
                {{ recipe.name }}
              </div>

              <div
                v-if="recipe.description"
                class="text-body-2 text-medium-emphasis mt-1"
              >
                {{ recipe.description }}
              </div>

              <div
                v-if="recipe.ingredients?.length"
                class="recipe-sub-info"
              >
                재료 {{ recipe.ingredients.length }}개
              </div>

              <div
                v-if="recipe.steps?.length"
                class="recipe-sub-info"
              >
                공정 {{ recipe.steps.length }}단계
              </div>
            </div>
          </div>

          <div
            v-else
            class="text-body-2 text-medium-emphasis"
          >
            등록된 레시피가 없습니다.
          </div>
        </section>

        <v-divider />

        <!-- 등록/수정 시각까지 포함하여 상세 화면에서 제품의 시스템 정보를 확인할 수 있습니다. -->
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon icon="mdi-clock-outline" size="small" />
            시스템 정보
          </div>

          <div class="detail-grid">
            <InfoItem label="제품 등록 일시" :value="formatDateTime(product.created_at)" />
            <InfoItem label="마지막 수정 일시" :value="formatDateTime(product.updated_at)" />
            <InfoItem
              v-if="product.deleted_at"
              label="삭제 일시"
              :value="formatDateTime(product.deleted_at)"
            />
          </div>
        </section>
      </div>

      <v-divider />

      <!-- 핵심 동작만 하단에 남겨 모바일에서 버튼이 빽빽해지지 않게 합니다. -->
      <v-card-actions class="detail-actions">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="close"
        >
          닫기
        </v-btn>

        <v-spacer />

        <v-btn
          v-if="product.deleted_at"
          variant="flat"
          prepend-icon="mdi-restore"
          :disabled="!canManage"
          :loading="loading"
          @click="$emit('restore')"
        >
          복구
        </v-btn>

        <template v-else-if="canManage">
          <v-btn
            variant="text"
            prepend-icon="mdi-pencil-outline"
            :disabled="loading"
            @click="$emit('edit')"
          >
            수정
          </v-btn>

          <v-btn
            color="error"
            variant="text"
            prepend-icon="mdi-delete-outline"
            :disabled="loading"
            @click="$emit('delete')"
          >
            삭제
          </v-btn>
        </template>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import {
  computed,
  defineComponent,
  h,
} from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  product: { type: Object, default: null },
  canManage: { type: Boolean, default: false },
  canManageRecipe: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  'update:modelValue',
  'close',
  'closed',
  'edit',
  'toggle',
  'delete',
  'restore',
  'recipe',
]);

/** 직원 상세보기와 같은 라벨/값 2열 구조를 재사용하기 위한 내부 컴포넌트입니다. */
const InfoItem = defineComponent({
  props: {
    label: { type: String, required: true },
    value: { type: [String, Number], default: '-' },
  },
  setup(itemProps) {
    return () => [
      h('div', { class: 'detail-label' }, itemProps.label),
      h('div', { class: 'detail-value' }, itemProps.value ?? '-'),
    ];
  },
});

const currentPrice = computed(() => props.product?.prices?.[0]?.price ?? null);
const hasRecipe = computed(() => Boolean(props.product?.recipes?.length));

function close() {
  if (props.loading) {
    return;
  }

  emit('update:modelValue', false);
  emit('close');
}

function handleDialogChange(value) {
  emit('update:modelValue', value);

  if (!value) {
    emit('close');
  }
}

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

  const dateOnly = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
  return dateOnly ? `${dateOnly[1]}.${dateOnly[2]}.${dateOnly[3]}` : String(value);
}

function formatDateTime(value) {
  if (!value) {
    return '-';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat('ko-KR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date);
}

function departmentName(value) {
  return {
    kitchen: '주방',
    hall: '홀',
  }[value] ?? value ?? '-';
}
</script>

<style scoped>
.product-detail-dialog {
  display: flex;
  max-height: calc(100vh - 48px);
  flex-direction: column;
  overflow: hidden;
}

.detail-header {
  flex: 0 0 auto;
  padding: 20px;
}

.detail-header-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 4px;
  margin-left: auto;
}

.detail-scroll-area {
  min-height: 0;
  overflow-y: auto;
}

.detail-section {
  padding: 20px;
}

.detail-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  font-size: 0.875rem;
  font-weight: 700;
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(130px, 0.8fr) minmax(0, 1.2fr);
  gap: 14px 20px;
}

:deep(.detail-label) {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.875rem;
}

:deep(.detail-value) {
  min-width: 0;
  font-size: 0.875rem;
  font-weight: 500;
  text-align: right;
  word-break: break-word;
}

.recipe-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.recipe-item {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
}

.recipe-sub-info {
  margin-top: 6px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.75rem;
}

.detail-actions {
  flex: 0 0 auto;
  padding: 16px 20px;
}

.min-width-0 {
  min-width: 0;
}

@media (max-width: 480px) {
  .product-detail-dialog {
    max-height: calc(100vh - 24px);
  }

  .detail-header,
  .detail-section {
    padding: 16px;
  }

  .detail-grid {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  :deep(.detail-value) {
    margin-bottom: 12px;
    text-align: left;
  }

  .detail-actions {
    padding: 12px 16px;
  }
}
</style>
