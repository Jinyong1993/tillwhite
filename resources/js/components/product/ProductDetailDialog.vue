<template>
  <v-dialog
    :model-value="modelValue"
    max-width="620"
    @update:model-value="handleDialogChange"
  >
    <v-card rounded="lg">
      <v-card-title class="product-detail-header pa-5 pb-3">
        <div class="min-width-0">
          <div class="text-h6 text-truncate">
            {{ product?.name ?? '제품 상세' }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ product?.category?.name ?? '-' }}
          </div>
        </div>

        <div class="product-detail-status">
          <v-chip
            v-if="product?.deleted_at"
            size="small"
            prepend-icon="mdi-delete-clock-outline"
            variant="tonal"
          >
            삭제됨
          </v-chip>

          <template v-else>
            <v-chip
              size="small"
              :color="product?.is_active ? 'success' : 'warning'"
              variant="tonal"
            >
              {{ product?.is_active ? '취급중' : '취급중단' }}
            </v-chip>

            <v-chip
              v-if="product?.sales_type === 'limited'"
              size="small"
              variant="tonal"
            >
              기간한정
            </v-chip>
          </template>
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text class="detail-scroll pa-5">
        <section>
          <div class="section-title">
            <v-icon icon="mdi-information-outline" size="18" />
            기본 정보
          </div>

          <div class="detail-grid">
            <InfoItem label="점포" :value="product?.store?.name" />
            <InfoItem label="카테고리" :value="product?.category?.name" />
            <InfoItem label="판매가" :value="formatPrice(currentPrice)" />
            <InfoItem label="생산 부서" :value="departmentName(product?.production_department)" />
            <InfoItem label="관리 부서" :value="departmentName(product?.management_department)" />
          </div>
        </section>

        <v-divider class="my-5" />

        <section>
          <div class="section-title">
            <v-icon icon="mdi-calendar-check-outline" size="18" />
            판매 정보
          </div>

          <div class="detail-grid">
            <InfoItem
              label="취급 상태"
              :value="product?.is_active ? '취급중' : '취급중단'"
            />
            <InfoItem
              label="판매 유형"
              :value="product?.sales_type === 'limited' ? '기간 한정' : '상시'"
            />

            <template v-if="product?.sales_type === 'limited'">
              <InfoItem label="판매 시작일" :value="formatDate(product?.sales_start_date)" />
              <InfoItem label="판매 종료일" :value="formatDate(product?.sales_end_date)" />
            </template>
          </div>
        </section>

        <template v-if="product?.recipes?.length">
          <v-divider class="my-5" />

          <section>
            <div class="section-title">
              <v-icon icon="mdi-notebook-outline" size="18" />
              레시피
            </div>

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
                class="text-caption text-medium-emphasis mt-1"
              >
                {{ recipe.description }}
              </div>
            </div>
          </section>
        </template>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4 px-5">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="$emit('close')"
        >
          닫기
        </v-btn>

        <v-spacer />

        <v-btn
          v-if="canManageRecipe && !product?.deleted_at"
          variant="text"
          prepend-icon="mdi-notebook-plus-outline"
          :disabled="loading"
          @click="$emit('recipe')"
        >
          레시피
        </v-btn>

        <template v-if="canManage">
          <v-btn
            v-if="product?.deleted_at"
            variant="flat"
            prepend-icon="mdi-restore"
            :loading="loading"
            @click="$emit('restore')"
          >
            복구
          </v-btn>

          <template v-else>
            <v-btn
              variant="text"
              prepend-icon="mdi-pencil-outline"
              :disabled="loading"
              @click="$emit('edit')"
            >
              수정
            </v-btn>

            <v-btn
              variant="text"
              prepend-icon="mdi-swap-horizontal"
              :disabled="loading"
              @click="$emit('toggle')"
            >
              {{ product?.is_active ? '취급중단' : '취급재개' }}
            </v-btn>

            <v-btn
              variant="text"
              prepend-icon="mdi-delete-outline"
              :disabled="loading"
              @click="$emit('delete')"
            >
              삭제
            </v-btn>
          </template>
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
  modelValue: {
    type: Boolean,
    default: false,
  },
  product: {
    type: Object,
    default: null,
  },
  canManage: {
    type: Boolean,
    default: false,
  },
  canManageRecipe: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  'update:modelValue',
  'close',
  'edit',
  'toggle',
  'delete',
  'restore',
  'recipe',
]);

/** 상세화면의 반복적인 라벨/값 표시를 작은 내부 컴포넌트로 통일합니다. */
const InfoItem = defineComponent({
  props: {
    label: { type: String, required: true },
    value: { type: [String, Number], default: '-' },
  },
  setup(itemProps) {
    return () => h('div', { class: 'detail-item' }, [
      h('div', { class: 'detail-label' }, itemProps.label),
      h('div', { class: 'detail-value' }, itemProps.value ?? '-'),
    ]);
  },
});

const currentPrice = computed(() => props.product?.prices?.[0]?.price ?? null);

function formatPrice(value) {
  if (value === null || value === undefined) {
    return '-';
  }

  return `${Number(value).toLocaleString('ko-KR')}원`;
}

function formatDate(value) {
  return value
    ? String(value).slice(0, 10).replaceAll('-', '.')
    : '-';
}

function departmentName(value) {
  return {
    kitchen: '주방',
    hall: '홀',
  }[value] ?? value ?? '-';
}

function handleDialogChange(value) {
  if (!value) {
    emit('close');
  }
}
</script>

<style scoped>
.product-detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.min-width-0 {
  min-width: 0;
}

.product-detail-status {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}

.detail-scroll {
  max-height: min(70vh, 650px);
  overflow-y: auto;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  font-weight: 700;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

:deep(.detail-label) {
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.75rem;
}

:deep(.detail-value) {
  margin-top: 4px;
  font-size: 0.9rem;
  font-weight: 600;
  word-break: break-word;
}

.recipe-item + .recipe-item {
  margin-top: 12px;
}

@media (max-width: 420px) {
  .detail-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}
</style>
