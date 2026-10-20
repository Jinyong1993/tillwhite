<template>
  <!-- 레시피 상세 카드 -->
  <div
    v-if="recipe"
    class="recipe-card app-form-section d-flex flex-column"
    :class="{ 'recipe-card--deleted': recipe.deleted_at }"
  >
    <!-- 레시피 기본 정보 -->
    <button
      v-if="canManage && !recipe.deleted_at"
      type="button"
      class="recipe-heading recipe-touchable d-flex align-start justify-space-between"
      @click="$emit('edit-part', { type: 'basic' })"
    >
      <span class="min-width-0">
        <span class="recipe-name">
          {{ recipe.name || '레시피' }}
        </span>

        <span
          v-if="recipe.description"
          class="recipe-description"
        >
          {{ recipe.description }}
        </span>
      </span>

      <!-- 수정 가능한 영역 표시 -->
      <v-icon
        icon="mdi-chevron-right"
        size="18"
        class="recipe-item-arrow"
      />
    </button>

    <!-- 조회 전용 레시피 정보 -->
    <div
      v-else
      class="recipe-heading d-flex align-start justify-space-between"
    >
      <span class="min-width-0">
        <span class="recipe-name">
          {{ recipe.name || '레시피' }}
        </span>

        <span
          v-if="recipe.description"
          class="recipe-description"
        >
          {{ recipe.description }}
        </span>
      </span>
    </div>

    <!-- 레시피 완성 상태 -->
    <div class="recipe-status-row d-flex justify-start mt-2">
      <v-chip
        v-if="isIncomplete"
        size="x-small"
        variant="tonal"
      >
        미완성
      </v-chip>
    </div>

    <!-- 재료 -->
    <div class="recipe-block">
      <div class="recipe-label app-form-section-title">
        <span>재료</span>
      </div>

      <div
        v-if="sortedIngredients.length"
        class="recipe-items d-flex flex-column ga-2"
      >
        <!-- 재료별 부분 수정 -->
        <component
          :is="canManage && !recipe.deleted_at ? 'button' : 'div'"
          v-for="(item, index) in sortedIngredients"
          :key="item.id ?? `${item.name}-${item.sort_order}`"
          :type="canManage ? 'button' : undefined"
          class="recipe-item d-flex align-center ga-2"
          :class="{
            'recipe-touchable': canManage && !recipe.deleted_at,
          }"
          @click="
            canManage &&
            $emit('edit-part', {
              type: 'ingredient',
              index,
            })
          "
        >
          <!-- 재료 순서 -->
          <span class="recipe-item-index">
            {{ formatOrder(index) }}
          </span>

          <!-- 재료명 / 사용량 -->
          <span class="recipe-item-content d-flex align-baseline justify-space-between">
            <span class="recipe-item-name">
              {{ item.name || '-' }}
            </span>

            <span class="recipe-item-meta d-inline-flex align-baseline">
              <span>
                {{ ingredientQuantity(item) }}
              </span>

              <span class="recipe-item-unit">
                {{ ingredientUnit(item) }}
              </span>
            </span>
          </span>

          <!-- 클릭 가능한 재료에만 화살표 표시 -->
          <v-icon
            v-if="canManage && !recipe.deleted_at"
            icon="mdi-chevron-right"
            size="18"
            class="recipe-item-arrow"
          />
        </component>
      </div>

      <div
        v-else
        class="app-supporting-text text-medium-emphasis"
      >
        등록된 재료가 없습니다.
      </div>
    </div>

    <!-- 공정 -->
    <div class="recipe-block">
      <div class="recipe-label app-form-section-title">
        <span>공정</span>
      </div>

      <div
        v-if="sortedSteps.length"
        class="recipe-steps d-flex flex-column ga-2"
      >
        <!-- 공정별 부분 수정 -->
        <component
          :is="canManage && !recipe.deleted_at ? 'button' : 'div'"
          v-for="(step, index) in sortedSteps"
          :key="step.id ?? step.sort_order"
          :type="canManage ? 'button' : undefined"
          class="recipe-step d-flex align-center ga-2"
          :class="{
            'recipe-touchable': canManage && !recipe.deleted_at,
          }"
          @click="
            canManage &&
            $emit('edit-part', {
              type: 'step',
              index,
            })
          "
        >
          <!-- 공정 순서 -->
          <span class="recipe-step-number">
            {{ formatOrder(index) }}
          </span>

          <!-- 공정 설명 -->
          <span class="recipe-step-description">
            {{ step.description }}
          </span>

          <!-- 클릭 가능한 공정에만 화살표 표시 -->
          <v-icon
            v-if="canManage && !recipe.deleted_at"
            icon="mdi-chevron-right"
            size="18"
            class="recipe-item-arrow"
          />
        </component>
      </div>

      <div
        v-else
        class="app-supporting-text text-medium-emphasis"
      >
        등록된 공정이 없습니다.
      </div>
    </div>

    <!-- 레시피 관리 버튼 -->
    <div
      v-if="canManage && !parentDeleted"
      class="recipe-actions d-flex align-center flex-wrap ga-2 mt-4"
    >
      <!-- 삭제 -->
      <v-btn
        v-if="!recipe.deleted_at"
        class="recipe-action-button app-btn"
        color="error"
        variant="outlined"
        @click="requestAction('delete')"
      >
        삭제
      </v-btn>

      <!-- 삭제된 레시피 복구 -->
      <v-btn
        v-if="recipe.deleted_at"
        class="recipe-action-button app-btn"
        color="success"
        variant="outlined"
        @click="requestAction('restore')"
      >
        복구
      </v-btn>

      <!-- 레시피 전체 수정 -->
      <v-btn
        v-else
        class="recipe-action-button app-btn"
        color="primary"
        variant="outlined"
        @click="requestAction('edit')"
      >
        수정
      </v-btn>
    </div>

    <!-- 부모 제품이 삭제된 경우 -->
    <div
      v-if="parentDeleted"
      class="recipe-parent-deleted-hint app-supporting-text mt-4"
    >
      제품을 복구하면 제품 삭제로 함께 삭제된 레시피도 자동으로 복구됩니다.
    </div>

    <!-- 레시피 관리 이력 -->
    <div class="recipe-history d-flex flex-column ga-1 mt-4 pt-3">
      <div class="recipe-history-label mt-1">
        등록
      </div>

      <div>
        {{
          historyText(
            recipe.management_history?.created,
            recipe.created_at,
          )
        }}
      </div>

      <div class="recipe-history-label mt-1">
        수정
      </div>

      <div>
        {{
          historyText(
            recipe.management_history?.updated,
            recipe.updated_at,
          )
        }}
      </div>

      <div class="recipe-history-label mt-1">
        삭제
      </div>

      <div>
        {{
          recipe.deleted_at
            ? historyText(
                recipe.management_history?.deleted,
                recipe.deleted_at,
              )
            : '- · -'
        }}
      </div>
    </div>

    <!-- 변경 이력 -->
    <details
      v-if="recipe.audit_history?.length"
      class="recipe-audit-history"
    >
      <summary>
        변경 이력 보기
      </summary>

      <div
        v-for="entry in recipe.audit_history"
        :key="entry.id"
        class="recipe-audit-entry d-flex justify-space-between"
      >
        <span>
          {{ auditActionText(entry.action) }}
        </span>

        <span>
          {{ entry.user?.name ?? '-' }}
          ·
          {{ formatHistoryDateTime(entry.at) }}
        </span>
      </div>
    </details>
  </div>

  <!-- 레시피 미등록 상태 -->
  <div
    v-else
    class="empty-recipe d-flex flex-column"
  >
    <div class="app-supporting-text text-medium-emphasis">
      아직 등록된 레시피가 없습니다.
    </div>

    <v-btn
      v-if="canManage"
      block
      class="app-btn mt-3"
      variant="outlined"
      color="primary"
      @click="$emit('edit')"
    >
      레시피 등록
    </v-btn>
  </div>
</template>

<script setup>
import { computed } from 'vue';

/*
 * 레시피 정보와 관리 권한
 */
const props = defineProps({
  recipe: {
    type: Object,
    default: null,
  },

  canManage: {
    type: Boolean,
    default: false,
  },

  parentDeleted: {
    type: Boolean,
    default: false,
  },
});

/*
 * 기존 이벤트 유지
 */
const emit = defineEmits([
  'edit',
  'edit-part',
  'copy',
  'delete',
  'restore',
  'create-product',
  'view-product',
  'permission-denied',
]);

/*
 * 재료는 원본 배열을 변경하지 않고 정렬합니다.
 */
const sortedIngredients = computed(() => {
  const ingredients = props.recipe?.ingredients ?? [];

  return [...ingredients].sort(
    (a, b) => (
      Number(a.sort_order ?? 0)
      - Number(b.sort_order ?? 0)
    ),
  );
});

/*
 * 공정은 원본 배열을 변경하지 않고 정렬합니다.
 */
const sortedSteps = computed(() => {
  const steps = props.recipe?.steps ?? [];

  return [...steps].sort(
    (a, b) => (
      Number(a.sort_order ?? 0)
      - Number(b.sort_order ?? 0)
    ),
  );
});

/*
 * 재료 또는 공정이 없으면 미완성 상태로 표시합니다.
 */
const isIncomplete = computed(() => {
  return (
    sortedIngredients.value.length === 0
    || sortedSteps.value.length === 0
  );
});

/*
 * 관리 버튼 이벤트를 부모 화면에 전달합니다.
 */
function requestAction(action) {
  if (!props.canManage || props.parentDeleted) {
    return;
  }

  if (action === 'restore' && props.recipe?.deleted_at) {
    emit('restore');
  } else if (
    action === 'delete'
    && props.recipe
    && !props.recipe.deleted_at
  ) {
    emit('delete');
  } else if (
    action === 'edit'
    && props.recipe
    && !props.recipe.deleted_at
  ) {
    emit('edit');
  }
}

/*
 * 감사 로그 동작 코드 표시
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
 * 관리 이력의 작업자 및 일시
 */
function historyText(entry, fallbackAt = null) {
  const actor = entry?.user?.name ?? '-';
  const value = entry?.at ?? fallbackAt;
  const at = formatHistoryDateTime(value);

  return `${actor} · ${at}`;
}

/*
 * 날짜 및 시간 표시
 */
function formatHistoryDateTime(value) {
  if (!value) {
    return '-';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return '-';
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

/*
 * 재료 사용량
 */
function ingredientQuantity(item) {
  const quantity = item?.quantity;

  return (
    quantity === null
    || quantity === undefined
    || quantity === ''
  )
    ? '-'
    : formatQuantity(quantity);
}

/*
 * 재료 단위
 */
function ingredientUnit(item) {
  return String(item?.unit ?? '').trim() || '-';
}

/*
 * DB decimal 문자열의 불필요한 소수점 0을 제거합니다.
 */
function formatQuantity(value) {
  const text = String(value);

  if (!text.includes('.')) {
    return text;
  }

  return text.replace(/\.?0+$/, '');
}

/*
 * 재료와 공정의 표시 순서
 */
function formatOrder(index) {
  return String(index + 1).padStart(2, '0');
}
</script>

<style scoped>
/*
 * 레시피 카드
 * 테두리는 글로벌 app-form-section 사용
 */
.recipe-card {
  padding: 16px;
  border-radius: 12px;
  overflow-wrap: anywhere;
}

.recipe-card--deleted {
  opacity: 0.58;
}

/*
 * 레시피 기본 정보
 */
.recipe-heading {
  width: 100%;
  min-width: 0;
  gap: 10px;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  font: inherit;
}

.min-width-0 {
  min-width: 0;
}

.recipe-name {
  display: block;
  font-size: 1rem;
  font-weight: 700;
}

.recipe-description {
  display: block;
  margin-top: 5px;
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-size: 0.84rem;
  line-height: 1.5;
  white-space: pre-wrap;
}

/*
 * 재료 / 공정 공통 영역
 */
.recipe-block {
  margin-top: 20px;
}

.recipe-label {
  margin-bottom: 8px;
  color: rgba(var(--v-theme-on-surface), 0.62);
}

/*
 * 재료와 공정 항목
 */
.recipe-item,
.recipe-step {
  width: 100%;
  min-width: 0;
  min-height: 44px;
  padding: 10px 11px;
  border: 1px solid
    rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 9px;
  background: transparent;
  color: inherit;
  text-align: left;
  font: inherit;
}

/*
 * 재료 / 공정 순서 번호
 */
.recipe-item-index,
.recipe-step-number {
  display: grid;
  flex: 0 0 30px;
  width: 30px;
  height: 30px;
  place-items: center;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
  font-size: 0.72rem;
  font-weight: 800;
}

/*
 * 재료명과 수량
 */
.recipe-item-content {
  min-width: 0;
  flex: 1 1 auto;
  gap: 10px;
}

.recipe-item-name {
  min-width: 0;
  font-size: 0.875rem;
  font-weight: 650;
  overflow-wrap: anywhere;
}

.recipe-item-meta {
  flex: 0 0 auto;
  gap: 8px;
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.8rem;
  white-space: nowrap;
}

.recipe-item-unit {
  color: rgba(var(--v-theme-on-surface), 0.58);
}

/*
 * 공정 설명
 */
.recipe-step-description {
  min-width: 0;
  flex: 1 1 auto;
  font-size: 0.875rem;
  line-height: 1.5;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

/*
 * 클릭 가능한 영역의 화살표
 */
.recipe-item-arrow {
  flex: 0 0 auto;
  color: rgba(var(--v-theme-on-surface), 0.42);
}

/*
 * 터치 가능한 영역의 피드백
 */
.recipe-touchable {
  cursor: pointer;
  transition:
    background-color 140ms ease,
    border-color 140ms ease,
    transform 80ms ease;
}

.recipe-touchable:hover {
  background: rgba(var(--v-theme-primary), 0.035);
  border-color: rgba(var(--v-theme-primary), 0.3);
}

.recipe-touchable:active {
  transform: scale(0.995);
}

/*
 * 하단 작업 버튼
 * 높이는 글로벌 app-btn 사용
 */
.recipe-action-button {
  flex: 1 1 auto;
  min-width: 0;
}

/*
 * 감사 로그
 */
.recipe-audit-history {
  margin-top: 12px;
  font-size: 0.75rem;
}

.recipe-audit-history summary {
  cursor: pointer;
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-weight: 600;
}

.recipe-audit-entry {
  gap: 12px;
  padding-top: 7px;
  overflow-wrap: anywhere;
}

/*
 * 삭제된 부모 제품 안내
 */
.recipe-parent-deleted-hint {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.76rem;
  line-height: 1.45;
}

/*
 * 등록 / 수정 / 삭제 이력
 */
.recipe-history {
  border-top: 1px solid
    rgba(var(--v-border-color), var(--v-border-opacity));
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.72rem;
  line-height: 1.4;
  text-align: left;
}

.recipe-history-label {
  font-weight: 700;
}

/*
 * 좁은 모바일 화면
 */
@media (max-width: 390px) {
  .recipe-card {
    padding: 14px;
  }

  .recipe-item,
  .recipe-step {
    align-items: flex-start !important;
    padding: 9px;
  }

  .recipe-item-content {
    flex-direction: column;
    gap: 2px;
  }

  .recipe-item-meta {
    flex: 1 1 auto;
  }
}
</style>