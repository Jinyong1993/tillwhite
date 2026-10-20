<template>
  <!-- 제품 상세보기 -->
  <AppDialogLayout
    :model-value="modelValue"
    title="제품 상세보기"
    :subtitle="product?.name ?? '제품의 상세 정보를 확인합니다.'"
    :max-width="560"
    persistent
    card-class="product-detail-dialog"
    body-class="product-detail-body"
    :busy="loading"
    @update:model-value="handleDialogChange"
    @after-leave="emit('closed')"
  >
    <div
      v-if="product"
      class="product-detail-content d-flex flex-column ga-3"
    >
      <!-- 제품명과 상태 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section pa-3"
      >
        <div class="d-flex flex-column ga-2">
          <!-- 제품명 / 더보기 -->
          <div class="d-flex align-start justify-space-between ga-2">
            <div class="detail-overview-text">
              <div class="detail-product-name">
                {{ product.name ?? '제품 상세' }}
              </div>
            </div>

            <!-- 제품 관리 더보기 -->
            <v-menu
              v-if="!product.deleted_at && canManage"
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
                <v-list-item
                  :title="
                    product.is_active
                      ? '취급중단'
                      : '취급재개'
                  "
                  @click="emit('toggle')"
                />
              </v-list>
            </v-menu>
          </div>

          <!-- 카테고리 / 제품 상태 -->
          <div class="d-flex align-center justify-space-between ga-2">
            <div class="app-supporting-text text-medium-emphasis min-width-0">
              {{ product.category?.name ?? '-' }}
            </div>

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
          </div>
        </div>
      </v-card>

      <!-- 기본 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="detail-section-heading d-flex align-center ga-2">
          <div class="app-form-section-title">
            기본 정보
          </div>
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
      </v-card>

      <!-- 판매 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="detail-section-heading d-flex align-center ga-2">
          <div class="app-form-section-title">
            판매 정보
          </div>
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
        </div>
      </v-card>

      <!-- 레시피 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div
          class="detail-section-heading d-flex align-center justify-space-between ga-2"
        >
          <div class="d-flex align-center ga-2 min-width-0">
            <div class="app-form-section-title">
              레시피
            </div>
          </div>

          <!-- 레시피 더보기 메뉴 -->
          <v-menu
            v-if="
              activeRecipe
              && !product.deleted_at
              && canManageRecipe
            "
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
                title="새 제품 만들기"
                @click="requestRecipeCreateProduct"
              />

              <v-list-item
                title="복사"
                @click="requestRecipeCopy"
              />
            </v-list>
          </v-menu>
        </div>

        <!-- 현재 사용 중인 레시피 -->
        <ProductRecipeCard
          v-if="activeRecipe || !product.deleted_at"
          :recipe="activeRecipe"
          :can-manage="canManageRecipe"
          :parent-deleted="Boolean(product.deleted_at)"
          @edit="requestRecipeEdit"
          @edit-part="emit('recipe-part', $event)"
          @copy="requestRecipeCopy"
          @delete="emit('recipe-delete', activeRecipe)"
          @restore="emit('recipe-restore', activeRecipe)"
          @create-product="emit('recipe-create-product')"
          @view-product="emit('recipe-view-product')"
          @permission-denied="emit('permission-denied', $event)"
        />

        <!-- 삭제된 레시피 이력 -->
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
            @restore="emit('recipe-restore', recipe)"
            @view-product="emit('recipe-view-product')"
            @permission-denied="emit('permission-denied', $event)"
          />
        </div>
      </v-card>

      <!-- 기존 부모 화면의 확장 상세 정보 -->
      <div
        v-if="$slots['extra-detail']"
        class="product-extra-detail"
      >
        <slot name="extra-detail" />
      </div>

      <!-- 시스템 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="detail-section-heading d-flex align-center ga-2">
          <div class="app-form-section-title">
            시스템 정보
          </div>
        </div>

        <div class="detail-grid">
          <InfoItem
            label="등록자"
            :value="
              historyActor(
                product.management_history?.created,
              )
            "
          />

          <InfoItem
            label="등록일"
            :value="
              historyAt(
                product.management_history?.created,
                product.created_at,
              )
            "
          />

          <InfoItem
            label="수정자"
            :value="
              historyActor(
                product.management_history?.updated,
              )
            "
          />

          <InfoItem
            label="수정일"
            :value="
              historyAt(
                product.management_history?.updated,
                product.updated_at,
              )
            "
          />

          <InfoItem
            label="삭제자"
            :value="
              product.deleted_at
                ? historyActor(
                    product.management_history?.deleted,
                  )
                : '-'
            "
          />

          <InfoItem
            label="삭제일"
            :value="
              product.deleted_at
                ? historyAt(
                    product.management_history?.deleted,
                    product.deleted_at,
                  )
                : '-'
            "
          />
        </div>

        <!-- 기존 변경 이력 -->
        <details
          v-if="product.audit_history?.length"
          class="product-audit-history"
        >
          <summary>
            변경 이력 보기
          </summary>

          <div
            v-for="entry in product.audit_history"
            :key="entry.id"
            class="product-audit-entry d-flex justify-space-between ga-3"
          >
            <span>
              {{ auditActionText(entry.action) }}
            </span>

            <span>
              {{ entry.user?.name ?? '-' }}
              ·
              {{ formatDateTime(entry.at) }}
            </span>
          </div>
        </details>
      </v-card>
    </div>

    <!-- 공통 푸터 -->
    <template #footer>
      <!-- 삭제된 제품: 닫기 + 복구 -->
      <AppDialogFooter
        v-if="product?.deleted_at && canManage"
        :button-count="2"
        :disabled="loading"
        cancel-text="닫기"
        confirm-text="복구"
        confirm-action="save"
        @cancel="close"
        @confirm="requestManageAction('restore')"
      >
        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="success"
            min-height="44"
            :loading="loading"
            :disabled="loading"
            @click="requestManageAction('restore')"
          >
            복구
          </v-btn>
        </template>
      </AppDialogFooter>

      <!-- 정상 제품이며 관리 권한이 있는 경우 -->
      <AppDialogFooter
        v-else-if="product && canManage"
        :button-count="4"
        :disabled="loading"
        cancel-text="닫기"
        confirm-text="수정"
        confirm-action="edit"
        @cancel="close"
        @confirm="requestManageAction('edit')"
      >
        <template #secondary>
          <v-btn
            block
            variant="tonal"
            color="error"
            min-height="44"
            :disabled="loading"
            @click="requestManageAction('delete')"
          >
            삭제
          </v-btn>
        </template>

        <template #actions>
          <v-btn
            block
            variant="tonal"
            color="primary"
            min-height="44"
            :disabled="loading"
            @click="requestManageAction('clone')"
          >
            복제
          </v-btn>
        </template>

        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="primary"
            min-height="44"
            :disabled="loading"
            @click="requestManageAction('edit')"
          >
            수정
          </v-btn>
        </template>
      </AppDialogFooter>

      <!-- 관리 권한이 없는 경우 -->
      <AppDialogFooter
        v-else
        :button-count="1"
        :show-confirm="false"
        cancel-text="닫기"
        :disabled="loading"
        @cancel="close"
      />
    </template>
  </AppDialogLayout>

  <!-- 제품 상태 변경 -->
  <AppDialogLayout
    :model-value="statusDialog"
    title="제품 상태 변경"
    subtitle="제품의 취급 상태를 변경합니다."
    :max-width="380"
    persistent
    card-class="app-dialog-card--compact"
    :busy="loading"
    @update:model-value="handleStatusDialogChange"
  >
    <div class="product-status-content d-flex flex-column ga-3">
      <div class="app-form-notice">
        <div>
          현재 상태:
          <strong>
            {{ product?.is_active ? '취급중' : '취급중단' }}
          </strong>
        </div>
      </div>

      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section pa-3"
      >
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
      </v-card>
    </div>

    <template #footer>
      <AppDialogFooter
        :button-count="2"
        cancel-text="취소"
        confirm-text="변경"
        confirm-action="edit"
        :disabled="loading"
        @cancel="closeStatusDialog"
        @confirm="submitStatus"
      >
        <template #confirm>
          <v-btn
            block
            color="primary"
            variant="flat"
            min-height="44"
            :disabled="!canSubmitStatus"
            :loading="loading"
            @click="submitStatus"
          >
            변경
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>
</template>

<script setup>
import {
  computed,
  defineComponent,
  h,
  ref,
} from 'vue';

import AppDialogLayout from '../common/AppDialogLayout.vue';
import AppDialogFooter from '../common/AppDialogFooter.vue';
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
 * 기존 부모 이벤트를 유지합니다.
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
 * 제품 상태 변경 다이얼로그
 */
const statusDialog = ref(false);
const nextActive = ref(true);

/*
 * 상세 정보의 라벨 / 값 표시 컴포넌트입니다.
 * 기존 구조와 클래스를 유지합니다.
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
 * 삭제되지 않은 활성 레시피 한 건입니다.
 */
const activeRecipe = computed(() => {
  return (props.product?.recipes ?? [])
    .find((recipe) => !recipe.deleted_at) ?? null;
});

/*
 * 삭제된 레시피는 최근 삭제 순으로 정렬합니다.
 */
const deletedRecipes = computed(() => {
  return (props.product?.recipes ?? [])
    .filter((recipe) => Boolean(recipe.deleted_at))
    .sort(
      (a, b) => (
        new Date(b.deleted_at).getTime()
        - new Date(a.deleted_at).getTime()
      ),
    );
});

/*
 * 제품 상태 문구
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
 * 제품 상태 색상
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
 * 상태 변경 권한 및 진행 상태
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
 * 실제로 상태가 달라졌을 때만 변경 버튼을 활성화합니다.
 */
const canSubmitStatus = computed(() => {
  if (!props.product || props.loading) {
    return false;
  }

  return (
    nextActive.value
    !== Boolean(props.product.is_active)
  );
});

function openStatusDialog() {
  if (!canChangeStatus.value) {
    return;
  }

  nextActive.value = Boolean(
    props.product.is_active,
  );

  statusDialog.value = true;
}

function closeStatusDialog() {
  if (props.loading) {
    return;
  }

  statusDialog.value = false;
}

function handleStatusDialogChange(value) {
  if (!value) {
    closeStatusDialog();
  }
}

/*
 * 기존 toggle 이벤트를 재사용합니다.
 */
function submitStatus() {
  if (!canSubmitStatus.value) {
    return;
  }

  statusDialog.value = false;
  emit('toggle');
}

/*
 * 제품 관리 권한을 확인한 후 부모 이벤트를 호출합니다.
 */
function requestManageAction(action) {
  if (!props.canManage) {
    emit(
      'permission-denied',
      '제품을 관리할 권한이 없습니다.',
    );
    return;
  }

  emit(action);
}

/*
 * 레시피 신규 등록 및 기존 레시피 수정
 */
function requestRecipeEdit() {
  if (!props.canManageRecipe) {
    emit(
      'permission-denied',
      '레시피를 관리할 권한이 없습니다.',
    );
    return;
  }

  emit('recipe');
}

/*
 * 레시피로 새 제품 만들기
 */
function requestRecipeCreateProduct() {
  if (!props.canManageRecipe) {
    emit(
      'permission-denied',
      '레시피를 관리할 권한이 없습니다.',
    );
    return;
  }

  emit('recipe-create-product');
}

/*
 * 레시피 복사
 */
function requestRecipeCopy() {
  if (!props.canManageRecipe) {
    emit(
      'permission-denied',
      '레시피를 복사할 권한이 없습니다.',
    );
    return;
  }

  emit('recipe-copy');
}

/*
 * 상세보기 닫기
 */
function close() {
  if (props.loading) {
    return;
  }

  emit('update:modelValue', false);
  emit('close');
}

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
 * 날짜 표시
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
 * 변경 이력 작업 종류
 */
function auditActionText(action) {
  return {
    create: '등록',
    update: '수정',
    delete: '삭제',
    restore: '복구',
  }[action] ?? '-';
}

/*
 * 작업자 이름
 */
function historyActor(entry) {
  return entry?.user?.name ?? '-';
}

/*
 * 관리 이력 일시
 */
function historyAt(entry, fallbackAt = null) {
  return formatDateTime(entry?.at ?? fallbackAt);
}

/*
 * 날짜와 시간 표시
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
 * 부서 코드 변환
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
/*
 * 제품 상세보기 고유 레이아웃입니다.
 * 헤더, 본문 스크롤, 푸터는 공통 컴포넌트가 담당합니다.
 */
.product-detail-content {
  min-width: 0;
  width: 100%;
}

/*
 * 제품명 / 상태 / 관리 메뉴
 */
.detail-overview {
  min-width: 0;
}

.detail-overview-text {
  flex: 1 1 auto;
  min-width: 0;
}

.detail-product-name {
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.detail-overview-actions {
  flex: 0 0 auto;
}

/*
 * 카드별 제목
 */
.detail-section-heading {
  min-width: 0;
}

/*
 * 상세 정보 라벨 / 값
 * PC에서는 2열, 모바일에서는 한 열로 표시합니다.
 */
.detail-grid {
  display: grid;
  grid-template-columns:
    minmax(130px, 0.8fr)
    minmax(0, 1.2fr);
  gap: 14px 20px;
  min-width: 0;
}

:deep(.detail-label) {
  min-width: 0;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.875rem;
}

:deep(.detail-value) {
  min-width: 0;
  font-size: 0.875rem;
  font-weight: 500;
  text-align: right;
  overflow-wrap: anywhere;
  word-break: break-word;
}

/*
 * 삭제된 레시피 목록
 */
.deleted-recipe-list {
  display: grid;
  gap: 10px;
  margin-top: 12px;
  min-width: 0;
}

/*
 * 시스템 변경 이력
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
  padding-top: 8px;
  overflow-wrap: anywhere;
}

/*
 * 상태 변경 다이얼로그
 */
.product-status-content {
  min-width: 0;
  width: 100%;
}

/*
 * 모바일 대응
 */
@media (max-width: 480px) {
  .detail-overview {
    flex-wrap: wrap;
  }

  .detail-grid {
    grid-template-columns: minmax(0, 1fr);
    gap: 4px;
  }

  :deep(.detail-value) {
    margin-bottom: 12px;
    text-align: left;
  }

  .detail-overview-actions {
    max-width: 100%;
  }

  .product-audit-entry {
    flex-direction: column;
    gap: 2px;
  }
}

/*
 * 320px 화면에서 긴 제품명과 관리 메뉴가
 * 서로 겹치지 않도록 합니다.
 */
@media (max-width: 360px) {
  .detail-overview {
    flex-direction: column;
  }

  .detail-overview-actions {
    width: 100%;
  }
}
</style>