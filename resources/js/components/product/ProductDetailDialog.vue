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

            <div class="detail-product-category">
              {{ product.category?.name ?? '-' }}
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

            <!-- 제품 헤더의 더보기는 제품 상태 관리만 제공합니다. 레시피 관리는 레시피 영역에서 처리합니다. -->
            <v-menu
              v-if="
                !product.deleted_at
                && canManage
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
              카드 헤더에는 자주 쓰지 않는 복제 계열 기능만 더보기 메뉴로 모읍니다.
              삭제·제품 보기·수정/복구는 공정 아래의 카드 액션 영역에서 제공합니다.
            -->
            <v-menu
              v-if="activeRecipe && !product.deleted_at"
              location="bottom end"
            >
              <template #activator="{ props: menuProps }">
                <v-btn
                  v-bind="menuProps"
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  aria-label="레시피 더보기"
                />
              </template>

              <v-list
                  density="compact"
                  min-width="230"
              >
                <v-list-item
                  prepend-icon="mdi-package-variant-plus"
                  title="새 제품 만들기"
                  @click="requestRecipeCreateProduct"
                />
                <v-list-item
                  prepend-icon="mdi-content-copy"
                  title="복사"
                  @click="requestRecipeCopy"
                />
              </v-list>
            </v-menu>
          </div>

          <ProductRecipeCard
            v-if="activeRecipe"
            :recipe="activeRecipe"
            :can-manage="canManageRecipe"
            :parent-deleted="Boolean(product.deleted_at)"
            @edit="requestRecipeEdit"
            @edit-part="$emit('recipe-part', $event)"
            @copy="requestRecipeCopy"
            @delete="$emit('recipe-delete', activeRecipe)"
            @restore="$emit('recipe-restore', activeRecipe)"
            @create-product="$emit('recipe-create-product')"
            @view-product="$emit('recipe-view-product')"
            @permission-denied="$emit('permission-denied', $event)"
          />

          <!-- 삭제된 과거 레시피도 숨기지 않아 변경 이력과 복구 대상을 보존합니다. -->
          <div
            v-if="deletedRecipes.length"
            class="deleted-recipe-list"
          >
            <div class="app-supporting-text text-medium-emphasis">
              삭제된 레시피
            </div>

            <ProductRecipeCard
              v-for="recipe in deletedRecipes"
              :key="recipe.id"
              :recipe="recipe"
              :can-manage="canManageRecipe"
              :parent-deleted="Boolean(product.deleted_at)"
              @restore="$emit('recipe-restore', recipe)"
              @view-product="$emit('recipe-view-product')"
              @permission-denied="$emit('permission-denied', $event)"
            />
          </div>
        </section>

        <!-- 호출 화면에서 제품 고유 정보 아래에 업무별 상세 정보를 자연스럽게 확장할 수 있습니다. -->
        <template v-if="$slots['extra-detail']">
          <v-divider />
          <slot name="extra-detail" />
        </template>

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

          <!-- 등록/수정/삭제의 작업자와 일시를 같은 규칙으로 항상 표시합니다. -->
          <div class="detail-grid">
            <InfoItem
                label="등록자"
                :value="historyActor(product.management_history?.created)"
            />
            <InfoItem
                label="등록일"
                :value="historyAt(product.management_history?.created, product.created_at)"
            />
            <InfoItem
                label="수정자"
                :value="historyActor(product.management_history?.updated)"
            />
            <InfoItem
                label="수정일"
                :value="historyAt(product.management_history?.updated, product.updated_at)"
            />
            <InfoItem
                label="삭제자"
                :value="product.deleted_at ? historyActor(product.management_history?.deleted) : '-'"
            />
            <InfoItem
                label="삭제일"
                :value="product.deleted_at ? historyAt(product.management_history?.deleted, product.deleted_at) : '-'"
            />
          </div>

          <details
            v-if="product.audit_history?.length"
            class="product-audit-history"
          >
            <summary>변경 이력 보기</summary>
            <div
              v-for="entry in product.audit_history"
              :key="entry.id"
              class="product-audit-entry"
            >
              <span>{{ auditActionText(entry.action) }}</span>
              <span>{{ entry.user?.name ?? '-' }} · {{ formatDateTime(entry.at) }}</span>
            </div>
          </details>
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
          v-if="product.deleted_at && canManage"
          variant="flat"
          prepend-icon="mdi-restore"
          :disabled="loading"
          :loading="loading"
          @click="requestManageAction('restore')"
        >
          복구
        </v-btn>

        <!-- 정상 제품 -->
        <template v-else-if="canManage">
          <v-btn
            color="error"
            variant="text"
            prepend-icon="mdi-delete-outline"
            :disabled="loading"
            @click="requestManageAction('delete')"
          >
            삭제
          </v-btn>

          <v-btn
            variant="text"
            prepend-icon="mdi-content-copy"
            :disabled="loading"
            @click="requestManageAction('clone')"
          >
            복제
          </v-btn>

          <v-btn
            variant="text"
            prepend-icon="mdi-pencil-outline"
            :disabled="loading"
            @click="requestManageAction('edit')"
          >
            수정
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
        <div class="app-supporting-text text-medium-emphasis mb-3">
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
  'clone',
  'toggle',
  'delete',
  'restore',
  'recipe',
  'recipe-part',
  'recipe-copy',
  'recipe-delete',
  'recipe-restore',
  'recipe-create-product',
  'recipe-view-product',
  'permission-denied',
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

// 현재 제품에서 실제로 사용 중인 활성 레시피 한 건을 반환합니다.
const activeRecipe = computed(() => {
  return (props.product?.recipes ?? []).find((recipe) => !recipe.deleted_at) ?? null;
});

// 삭제된 레시피는 최신 삭제 건부터 보여 과거 기록을 찾기 쉽게 합니다.
const deletedRecipes = computed(() => {
  return (props.product?.recipes ?? [])
    .filter((recipe) => Boolean(recipe.deleted_at))
    .sort((a, b) => new Date(b.deleted_at).getTime() - new Date(a.deleted_at).getTime());
});

/* 제품 상태에 따라 상세 화면에 표시할 문구를 계산합니다. */
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

// 권한이 필요한 제품 관리 동작은 버튼을 숨기지 않고 클릭 시 이유를 안내합니다.
function requestManageAction(action) {
  if (!props.canManage) {
    emit('permission-denied', '제품을 관리할 권한이 없습니다.');
    return;
  }

  emit(action);
}

// 활성 레시피 수정 진입 전에 관리 권한을 확인합니다.
function requestRecipeEdit() {
  if (!props.canManageRecipe) {
    emit('permission-denied', '레시피를 관리할 권한이 없습니다.');
    return;
  }

  if (!activeRecipe.value) return;

  emit('recipe');
}

// 새 제품 만들기 역시 레시피 관리 권한을 확인한 뒤 상위 화면으로 전달합니다.
function requestRecipeCreateProduct() {
  if (!props.canManageRecipe) {
    emit('permission-denied', '레시피를 관리할 권한이 없습니다.');
    return;
  }

  emit('recipe-create-product');
}

// 레시피 복사 역시 같은 권한 안내 규칙을 사용합니다.
function requestRecipeCopy() {
  if (!props.canManageRecipe) {
    emit('permission-denied', '레시피를 복사할 권한이 없습니다.');
    return;
  }

  emit('recipe-copy');
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
function auditActionText(action) {
  return { create: '등록', update: '수정', delete: '삭제', restore: '복구' }[action] ?? '-';
}

// 관리 이력의 작업자 이름을 표시하고 누락 시 하이픈을 사용합니다.
function historyActor(entry) {
  return entry?.user?.name ?? '-';
}

// 관리 이력 일시와 모델 일시를 공통 형식으로 표시합니다.
function historyAt(entry, fallbackAt = null) {
  return formatDateTime(entry?.at ?? fallbackAt);
}

// 날짜·시간 값을 사용자 화면용 형식으로 변환합니다.
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
.detail-product-category {
  margin-top: 4px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.78rem;
  font-weight: 500;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

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
.product-audit-history {
  margin-top: 16px;
  font-size: 0.8rem;
}

.product-audit-history summary {
  cursor: pointer;
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-weight: 600;
}

.product-audit-entry {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding-top: 8px;
  overflow-wrap: anywhere;
}

@media (max-width: 480px) {
  .product-detail-dialog {
    max-height: calc(100dvh - 24px);
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

.deleted-recipe-list {
  display: grid;
  gap: 10px;
  margin-top: 12px;
}
</style>
