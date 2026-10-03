<template>
  <!-- 제품 상세보기 다이얼로그 -->
  <v-dialog
    :model-value="modelValue"
    max-width="560"
    persistent
    @update:model-value="handleDialogChange"
    @after-leave="$emit('closed')"
  >
    <v-card
      v-if="product"
      class="product-detail-dialog"
      rounded="lg"
    >
      <!-- 제품 상세 헤더 -->
      <div class="detail-header">
        <div class="detail-header-content">
          <!-- 제품명 / 카테고리 -->
          <div class="min-width-0">
            <div class="detail-product-name text-h6 font-weight-bold">
              {{ product.name ?? '제품 상세' }}
            </div>

            <div class="text-body-2 text-medium-emphasis mt-1">
              {{ product.category?.name ?? '카테고리 없음' }}
            </div>
          </div>

          <!-- 상태 / 관리 메뉴 -->
          <div class="detail-header-actions">
            <!--
              관리 권한이 있는 경우 상태 칩을 눌러
              제품 상태를 변경할 수 있습니다.
            -->
            <v-chip
              size="small"
              :color="statusChipColor"
              variant="tonal"
              :clickable="canChangeStatus"
              :append-icon="
                canChangeStatus
                  ? 'mdi-chevron-down'
                  : undefined
              "
              @click="openStatusDialog"
            >
              {{ statusText }}
            </v-chip>

            <!--
              기존 관리 메뉴는 유지합니다.
              상태 칩이나 레시피 영역에서도 같은 기능에 접근할 수 있습니다.
            -->
            <v-menu
              v-if="
                !product.deleted_at
                && (canManage || canManageRecipe)
              "
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

              <v-list
                density="compact"
                min-width="190"
              >
                <!-- 레시피 등록 / 수정 -->
                <v-list-item
                  v-if="canManageRecipe"
                  :prepend-icon="
                    hasRecipe
                      ? 'mdi-notebook-edit-outline'
                      : 'mdi-notebook-plus-outline'
                  "
                  :title="
                    hasRecipe
                      ? '레시피 수정'
                      : '레시피 등록'
                  "
                  @click="$emit('recipe')"
                />

                <!-- 취급 상태 변경 -->
                <v-list-item
                  v-if="canManage"
                  prepend-icon="mdi-swap-horizontal"
                  :title="
                    product.is_active
                      ? '취급중단'
                      : '취급재개'
                  "
                  @click="$emit('toggle')"
                />
              </v-list>
            </v-menu>
          </div>
        </div>
      </div>

      <v-divider />

      <!--
        상세 내용만 스크롤합니다.
        헤더와 하단 버튼은 다이얼로그에 고정합니다.
      -->
      <div class="detail-scroll-area">
        <!-- 기본 정보 -->
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon
              icon="mdi-information-outline"
              size="small"
            />

            <span>
              기본 정보
            </span>
          </div>

          <div class="detail-grid">
            <InfoItem
              label="제품명"
              :value="product.name"
            />

            <InfoItem
              label="점포"
              :value="product.store?.name"
            />

            <InfoItem
              label="카테고리"
              :value="product.category?.name"
            />

            <InfoItem
              label="판매가"
              :value="formatPrice(currentPrice)"
            />

            <InfoItem
              label="생산 부서"
              :value="
                departmentName(
                  product.production_department,
                )
              "
            />

            <InfoItem
              label="관리 부서"
              :value="
                departmentName(
                  product.management_department,
                )
              "
            />
          </div>
        </section>

        <v-divider />

        <!-- 판매 정보 -->
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon
              icon="mdi-calendar-check-outline"
              size="small"
            />

            <span>
              판매 정보
            </span>
          </div>

          <div class="detail-grid">
            <InfoItem
              label="취급 상태"
              :value="
                product.is_active
                  ? '취급중'
                  : '취급중단'
              "
            />

            <InfoItem
              label="판매 유형"
              :value="
                product.sales_type === 'limited'
                  ? '기간 한정'
                  : '상시'
              "
            />

            <!-- 기간한정 제품만 판매기간 표시 -->
            <InfoItem
              v-if="product.sales_type === 'limited'"
              label="판매 시작일"
              :value="
                formatDate(
                  product.sales_start_date,
                )
              "
            />

            <InfoItem
              v-if="product.sales_type === 'limited'"
              label="판매 종료일"
              :value="
                formatDate(
                  product.sales_end_date,
                )
              "
            />
          </div>
        </section>

        <v-divider />

        <!-- 레시피 -->
        <section class="detail-section">
          <div class="detail-section-title recipe-section-title">
            <div class="d-flex align-center ga-2 min-width-0">
              <v-icon
                icon="mdi-notebook-outline"
                size="small"
              />

              <span>
                레시피
              </span>
            </div>

            <!--
              레시피가 등록된 경우 상세 화면에서도
              바로 수정할 수 있도록 접근 경로를 제공합니다.
            -->
            <v-btn
              v-if="hasRecipe && canManageRecipe"
              size="small"
              variant="text"
              prepend-icon="mdi-pencil-outline"
              @click="$emit('recipe')"
            >
              레시피 수정
            </v-btn>
          </div>

          <ProductRecipeCard
            :recipe="product.recipes?.[0] ?? null"
            :can-manage="canManageRecipe"
            @edit="$emit('recipe')"
          />
        </section>

        <v-divider />

        <!-- 시스템 정보 -->
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon
              icon="mdi-clock-outline"
              size="small"
            />

            <span>
              시스템 정보
            </span>
          </div>

          <div class="detail-grid">
            <InfoItem
              label="제품 등록 일시"
              :value="
                formatDateTime(
                  product.created_at,
                )
              "
            />

            <InfoItem
              label="마지막 수정 일시"
              :value="
                formatDateTime(
                  product.updated_at,
                )
              "
            />

            <!-- 삭제된 제품만 삭제 일시 표시 -->
            <InfoItem
              v-if="product.deleted_at"
              label="삭제 일시"
              :value="
                formatDateTime(
                  product.deleted_at,
                )
              "
            />
          </div>
        </section>
      </div>

      <v-divider />

      <!-- 상세보기 하단 버튼 -->
      <v-card-actions class="detail-actions">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="close"
        >
          닫기
        </v-btn>

        <v-spacer />

        <!-- 삭제된 제품 -->
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

        <!-- 정상 제품 -->
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

  <!-- 제품 상태 변경 다이얼로그 -->
  <v-dialog
    v-model="statusDialog"
    max-width="380"
    persistent
  >
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">
        제품 상태 변경
      </v-card-title>

      <v-card-text class="px-5">
        <!-- 현재 상태 -->
        <div class="text-body-2 text-medium-emphasis mb-3">
          현재 상태:
          {{ product?.is_active ? '취급중' : '취급중단' }}
        </div>

        <!-- 변경할 상태 -->
        <v-radio-group
          v-model="nextActive"
          hide-details
        >
          <v-radio
            label="취급중"
            :value="true"
          />

          <v-radio
            label="취급중단"
            :value="false"
          />
        </v-radio-group>
      </v-card-text>

      <v-card-actions class="px-5 pb-4">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="closeStatusDialog"
        >
          취소
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          :disabled="!canSubmitStatus"
          :loading="loading"
          @click="submitStatus"
        >
          변경
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>


<script setup>
import {
  computed,
  defineComponent,
  h,
  ref,
} from 'vue';

import ProductRecipeCard from './ProductRecipeCard.vue';


/*
 * Props
 */
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


/*
 * Events
 */
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


/*
 * 상태 변경 다이얼로그
 */
const statusDialog = ref(false);
const nextActive = ref(true);


/*
 * 직원 상세보기와 동일한 라벨 / 값 구조를
 * 제품 상세에서도 재사용하기 위한 내부 컴포넌트입니다.
 */
const InfoItem = defineComponent({
  props: {
    label: {
      type: String,
      required: true,
    },

    value: {
      type: [
        String,
        Number,
      ],
      default: '-',
    },
  },

  setup(itemProps) {
    return () => [
      h(
        'div',
        {
          class: 'detail-label',
        },
        itemProps.label,
      ),

      h(
        'div',
        {
          class: 'detail-value',
        },
        itemProps.value ?? '-',
      ),
    ];
  },
});


/*
 * 현재 적용 중인 판매가
 */
const currentPrice = computed(() => {
  return props.product?.prices?.[0]?.price ?? null;
});


/*
 * 레시피 등록 여부
 */
const hasRecipe = computed(() => {
  return Boolean(
    props.product?.recipes?.length,
  );
});


/*
 * 제품 상태 표시 문구
 */
const statusText = computed(() => {
  if (props.product?.deleted_at) {
    return '삭제됨';
  }

  return props.product?.is_active
    ? '취급중'
    : '취급중단';
});


/*
 * 제품 상태 칩 색상
 */
const statusChipColor = computed(() => {
  if (props.product?.deleted_at) {
    return undefined;
  }

  return props.product?.is_active
    ? 'success'
    : 'warning';
});


/*
 * 상태 칩을 통한 상태 변경 가능 여부
 */
const canChangeStatus = computed(() => {
  return Boolean(
    props.product
    && !props.product.deleted_at
    && props.canManage
    && !props.loading
  );
});


/*
 * 상태 변경 버튼 활성화 여부
 *
 * 현재 상태와 동일한 값을 선택한 경우에는
 * 불필요한 API 요청을 보내지 않도록 변경 버튼을 비활성화합니다.
 */
const canSubmitStatus = computed(() => {
  if (
    !props.product
    || props.loading
  ) {
    return false;
  }

  return (
    nextActive.value
    !== Boolean(props.product.is_active)
  );
});


/*
 * 상태 변경 다이얼로그 열기
 */
function openStatusDialog() {
  if (!canChangeStatus.value) {
    return;
  }

  nextActive.value = Boolean(
    props.product.is_active,
  );

  statusDialog.value = true;
}


/*
 * 상태 변경 다이얼로그 닫기
 */
function closeStatusDialog() {
  if (props.loading) {
    return;
  }

  statusDialog.value = false;
}


/*
 * 제품 상태 변경
 *
 * 기존 toggle 이벤트를 그대로 사용하여
 * 상태 변경 API 로직을 중복해서 만들지 않습니다.
 */
function submitStatus() {
  if (!canSubmitStatus.value) {
    return;
  }

  statusDialog.value = false;

  emit('toggle');
}


/*
 * 제품 상세보기 닫기
 */
function close() {
  if (props.loading) {
    return;
  }

  emit('update:modelValue', false);
  emit('close');
}


/*
 * v-dialog에서 직접 닫힘 상태가 변경된 경우 처리
 */
function handleDialogChange(value) {
  if (props.loading && !value) {
    return;
  }

  emit('update:modelValue', value);

  if (!value) {
    emit('close');
  }
}


/*
 * 원화 가격 표시
 *
 * 예:
 * 3500 -> 3,500원
 */
function formatPrice(value) {
  if (
    value === null
    || value === undefined
  ) {
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

  const dateOnly = String(value).match(
    /^(\d{4})-(\d{2})-(\d{2})/,
  );

  if (!dateOnly) {
    return String(value);
  }

  return `${dateOnly[1]}.${dateOnly[2]}.${dateOnly[3]}`;
}


/*
 * 등록 / 수정 / 삭제 일시 표시
 */
function formatDateTime(value) {
  if (!value) {
    return '-';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat(
    'ko-KR',
    {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    },
  ).format(date);
}


/*
 * 부서 코드 표시명 변환
 */
function departmentName(value) {
  const departmentNames = {
    kitchen: '주방',
    hall: '홀',
  };

  return departmentNames[value] ?? value ?? '-';
}
</script>


<style scoped>
/* 제품 상세보기 다이얼로그 */
.product-detail-dialog {
  display: flex;
  max-height: calc(100vh - 48px);

  flex-direction: column;
  overflow: hidden;
}


/* 상세보기 상단 영역 */
.detail-header {
  flex: 0 0 auto;
  padding: 20px;
}


/* 제품명과 관리 기능을 양쪽에 배치 */
.detail-header-content {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  gap: 16px;
}


/* 긴 제품명은 다른 요소를 밀어내지 않고 개행 */
.detail-product-name {
  overflow-wrap: anywhere;
  word-break: break-word;
}


/* 헤더 상태 / 관리 버튼 */
.detail-header-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;

  gap: 4px;
  margin-left: auto;
}


/* 상세 내용 스크롤 영역 */
.detail-scroll-area {
  min-height: 0;
  overflow-y: auto;
}


/* 각 상세 정보 영역 */
.detail-section {
  padding: 20px;
}


/* 상세 정보 제목 */
.detail-section-title {
  display: flex;
  align-items: center;

  gap: 8px;
  margin-bottom: 16px;

  font-size: 0.875rem;
  font-weight: 700;
}


/* 레시피 제목 / 수정 버튼 */
.recipe-section-title {
  justify-content: space-between;
}


/* 상세 정보 라벨 / 값 */
.detail-grid {
  display: grid;

  grid-template-columns:
    minmax(130px, 0.8fr)
    minmax(0, 1.2fr);

  gap: 14px 20px;
}


/* 상세 정보 라벨 */
:deep(.detail-label) {
  color: rgba(
    var(--v-theme-on-surface),
    0.6
  );

  font-size: 0.875rem;
}


/* 상세 정보 값 */
:deep(.detail-value) {
  min-width: 0;

  font-size: 0.875rem;
  font-weight: 500;
  text-align: right;

  overflow-wrap: anywhere;
  word-break: break-word;
}


/* 하단 기능 버튼 */
.detail-actions {
  flex: 0 0 auto;
  padding: 16px 20px;
}


/* Flex/Grid 내부의 긴 텍스트 overflow 방지 */
.min-width-0 {
  min-width: 0;
}


/*
 * 작은 화면 대응
 *
 * 상세 정보는 한 열로 변경하고 긴 텍스트와 버튼이
 * 다른 정보를 침범하지 않도록 배치합니다.
 */
@media (max-width: 480px) {
  .product-detail-dialog {
    max-height: calc(100vh - 24px);
  }

  .detail-header,
  .detail-section {
    padding: 16px;
  }

  .detail-header-content {
    gap: 10px;
  }

  .detail-grid {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  :deep(.detail-value) {
    margin-bottom: 12px;
    text-align: left;
  }

  .recipe-section-title {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .detail-actions {
    flex-wrap: wrap;
    gap: 4px;
    padding: 12px 16px;
  }
}


/*
 * 초소형 화면에서는 헤더 상태 영역도 아래로 내려
 * 제품명이나 카테고리와 겹치지 않도록 합니다.
 */
@media (max-width: 360px) {
  .detail-header-content {
    flex-direction: column;
  }

  .detail-header-actions {
    width: 100%;
    margin-left: 0;
  }
}
</style>