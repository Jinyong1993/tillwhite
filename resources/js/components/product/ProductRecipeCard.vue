<template>
  <!--
    레시피가 등록되어 있는 경우 표시하는 상세 카드.
    수정 권한(canManage)에 따라 기본 정보, 재료, 공정을
    직접 터치하여 부분 수정할 수 있다.
  -->
  <div
    v-if="recipe"
    class="recipe-card"
    :class="{ 'recipe-card--deleted': recipe.deleted_at }"
  >
    <!--
      레시피 기본 정보는 권한이 있으면 버튼으로 표시해 부분 수정으로 연결하고,
      권한이 없으면 같은 디자인의 조회 전용 영역으로 표시합니다.
    -->
    <button
      v-if="canManage && !recipe.deleted_at"
      type="button"
      class="recipe-heading recipe-touchable"
      @click="$emit('edit-part', { type: 'basic' })"
    >
      <span class="min-width-0">
        <!-- 레시피명이 비어 있는 경우 기본 명칭을 표시한다. -->
        <span class="recipe-name">
          {{ recipe.name || '레시피' }}
        </span>

        <!-- 설명은 등록된 경우에만 표시한다. -->
        <span
          v-if="recipe.description"
          class="recipe-description"
        >
          {{ recipe.description }}
        </span>
      </span>

      <!-- 수정 가능한 영역임을 자연스럽게 알려주는 표시 -->
      <v-icon
        icon="mdi-chevron-right"
        size="18"
      />
    </button>

    <!--
      수정 권한이 없는 사용자를 위한 조회 전용 영역.
      버튼을 사용하지 않아 클릭 가능한 UI처럼 보이지 않도록 한다.
    -->
    <div
      v-else
      class="recipe-heading"
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

    <div class="recipe-status-row">
      <v-chip
        v-if="isIncomplete"
        size="x-small"
        variant="tonal"
      >
        미완성
      </v-chip>
    </div>

    <!--
      재료는 sort_order 순서로 표시하며 화면 번호는 01, 02... 형태로 다시 만듭니다.
      관리 권한이 있으면 각 재료를 눌러 해당 항목만 빠르게 수정할 수 있습니다.
    -->
    <div class="recipe-block">
      <div class="recipe-label">
        <v-icon
          icon="mdi-scale-balance"
          size="16"
        />

        <span>재료</span>
      </div>

      <!-- 등록된 재료가 있는 경우 -->
      <div
        v-if="sortedIngredients.length"
        class="recipe-items"
      >
        <!--
          수정 권한에 따라 실제 HTML 요소를 변경한다.

          관리 가능 : button
          조회 전용 : div

          권한이 없는 사용자에게 버튼 형태만 숨기는 것이 아니라
          실제 요소 자체를 div로 변경하여 불필요한 클릭 동작을 막는다.
        -->
        <component
          :is="canManage && !recipe.deleted_at ? 'button' : 'div'"
          v-for="(item, index) in sortedIngredients"
          :key="item.id ?? `${item.name}-${item.sort_order}`"
          :type="canManage ? 'button' : undefined"
          class="recipe-item"
          :class="{ 'recipe-touchable': canManage && !recipe.deleted_at }"
          @click="
            canManage &&
            $emit('edit-part', {
              type: 'ingredient',
              index,
            })
          "
        >
          <!--
            재료의 화면 표시 순서.
            실제 DB의 sort_order 값을 그대로 노출하지 않고
            현재 정렬 결과를 기준으로 01, 02, 03... 형태로 표시한다.
          -->
          <span class="recipe-item-index">
            {{ formatOrder(index) }}
          </span>

          <span class="recipe-item-content">
            <!-- 재료명 -->
            <span class="recipe-item-name">
              {{ item.name || '-' }}
            </span>

            <!-- 사용량과 단위는 서로 붙어 보이지 않도록 별도 요소로 표시합니다. -->
            <span class="recipe-item-meta">
              <span>{{ ingredientQuantity(item) }}</span>
              <span class="recipe-item-unit">{{ ingredientUnit(item) }}</span>
            </span>
          </span>

          <!--
            수정 권한이 있을 때만 화살표를 표시한다.
            단순 장식이 아니라 해당 행을 터치할 수 있다는 의미로 사용한다.
          -->
          <v-icon
            v-if="canManage && !recipe.deleted_at"
            icon="mdi-chevron-right"
            size="18"
            class="recipe-item-arrow"
          />
        </component>
      </div>

      <!-- 재료가 하나도 등록되지 않은 경우 -->
      <div
        v-else
        class="empty-text"
      >
        등록된 재료가 없습니다.
      </div>
    </div>

    <!--
      공정도 sort_order 순서로 표시하며 관리 권한이 있으면
      원하는 단계만 눌러 빠르게 수정할 수 있습니다.
    -->
    <div class="recipe-block">
      <div class="recipe-label">
        <v-icon
          icon="mdi-format-list-numbered"
          size="16"
        />

        <span>공정</span>
      </div>

      <!-- 등록된 공정이 있는 경우 -->
      <div
        v-if="sortedSteps.length"
        class="recipe-steps"
      >
        <component
          :is="canManage && !recipe.deleted_at ? 'button' : 'div'"
          v-for="(step, index) in sortedSteps"
          :key="step.id ?? step.sort_order"
          :type="canManage ? 'button' : undefined"
          class="recipe-step"
          :class="{ 'recipe-touchable': canManage && !recipe.deleted_at }"
          @click="
            canManage &&
            $emit('edit-part', {
              type: 'step',
              index,
            })
          "
        >
          <!-- 공정 단계 번호 -->
          <span class="recipe-step-number">
            {{ formatOrder(index) }}
          </span>

          <!--
            공정 설명.
            여러 줄로 입력된 내용도 그대로 읽을 수 있도록
            CSS에서 white-space: pre-wrap을 적용한다.
          -->
          <span class="recipe-step-description">
            {{ step.description }}
          </span>

          <!-- 수정 가능한 공정에만 화살표 표시 -->
          <v-icon
            v-if="canManage && !recipe.deleted_at"
            icon="mdi-chevron-right"
            size="18"
            class="recipe-item-arrow"
          />
        </component>
      </div>

      <!-- 공정이 하나도 등록되지 않은 경우 -->
      <div
        v-else
        class="empty-text"
      >
        등록된 공정이 없습니다.
      </div>
    </div>

    <div
      v-if="!parentDeleted"
      class="recipe-actions mt-4"
    >
      <v-btn
        size="small"
        variant="text"
        prepend-icon="mdi-package-variant"
        @click="$emit('view-product')"
      >
        제품 보기
      </v-btn>

      <v-btn
        v-if="!recipe.deleted_at"
        size="small"
        variant="text"
        prepend-icon="mdi-package-variant-plus"
        @click="requestAction('create-product')"
      >
        새 제품 만들기
      </v-btn>

      <v-btn
        v-if="!recipe.deleted_at"
        size="small"
        variant="text"
        prepend-icon="mdi-content-copy"
        @click="requestAction('copy')"
      >
        복사
      </v-btn>

      <v-spacer />

      <v-btn
        v-if="!recipe.deleted_at"
        color="error"
        size="small"
        variant="text"
        prepend-icon="mdi-delete-outline"
        @click="requestAction('delete')"
      >
        삭제
      </v-btn>

      <v-btn
        v-if="recipe.deleted_at"
        size="small"
        variant="flat"
        prepend-icon="mdi-restore"
        @click="requestAction('restore')"
      >
        복구
      </v-btn>

      <v-btn
        v-else
        size="small"
        variant="flat"
        prepend-icon="mdi-pencil-outline"
        @click="requestAction('edit')"
      >
        수정
      </v-btn>
    </div>

    <div
      v-if="parentDeleted"
      class="recipe-parent-deleted-hint mt-4"
    >
      제품을 복구하면 제품 삭제로 함께 삭제된 레시피도 자동으로 복구됩니다.
    </div>

    <!-- 등록/수정/삭제 이력은 본문을 다 읽은 뒤 확인할 수 있도록 카드 맨 아래에 표시합니다. -->
    <div class="recipe-history">
      <div class="recipe-history-label">등록</div>
      <div>{{ historyText(recipe.management_history?.created, recipe.created_at) }}</div>
      <div class="recipe-history-label">수정</div>
      <div>{{ historyText(recipe.management_history?.updated, recipe.updated_at) }}</div>
      <div class="recipe-history-label">삭제</div>
      <div>{{ historyText(recipe.management_history?.deleted, recipe.deleted_at) }}</div>
    </div>

    <details
      v-if="recipe.audit_history?.length"
      class="recipe-audit-history"
    >
      <summary>변경 이력 보기</summary>
      <div
        v-for="entry in recipe.audit_history"
        :key="entry.id"
        class="recipe-audit-entry"
      >
        <span>{{ auditActionText(entry.action) }}</span>
        <span>{{ entry.user?.name ?? '-' }} · {{ formatHistoryDateTime(entry.at) }}</span>
      </div>
    </details>
  </div>

  <!--
    레시피가 없으면 빈 상태를 표시하고,
    관리 권한이 있는 사용자에게만 등록 버튼을 제공합니다.
  -->
  <div
    v-else
    class="empty-recipe"
  >
    <div class="text-body-2 text-medium-emphasis">
      아직 등록된 레시피가 없습니다.
    </div>

    <v-btn
      v-if="canManage"
      class="mt-3"
      size="small"
      variant="tonal"
      prepend-icon="mdi-notebook-plus-outline"
      @click="$emit('edit')"
    >
      레시피 등록
    </v-btn>
  </div>
</template>

<script setup>
import { computed } from 'vue';

/*
 * 부모 컴포넌트에서 전달받는 레시피 정보와 관리 권한.
 *
 * recipe
 * - 레시피 기본 정보
 * - ingredients 재료 목록
 * - steps 공정 목록
 *
 * canManage
 * - true  : 부분 수정 기능 제공
 * - false : 조회 전용으로 표시
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
 * edit
 * - 레시피 전체 등록/수정 화면을 열 때 사용한다.
 *
 * edit-part
 * - 레시피의 특정 영역만 수정할 때 사용한다.
 * - basic      : 기본 정보
 * - ingredient : 특정 재료
 * - step       : 특정 공정
 *
 * 실제 수정과 저장 처리는 부모 컴포넌트에서 담당하여
 * 상세 카드가 데이터 처리 로직까지 중복해서 가지지 않도록 한다.
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
 * 재료 목록
 *
 * 서버에서 전달받은 원본 배열을 직접 sort()하면
 * props 내부 배열의 순서까지 변경될 수 있다.
 *
 * 따라서 새로운 배열을 만든 후 sort_order 기준으로 정렬하여
 * 화면 표시용 데이터만 별도로 생성한다.
 */
const sortedIngredients = computed(() => {
  const ingredients = props.recipe?.ingredients ?? [];

  return [...ingredients].sort(
    (a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0),
  );
});

/*
 * 공정 목록
 *
 * 재료와 동일하게 원본 배열은 변경하지 않고
 * 복사한 배열만 sort_order 기준으로 정렬한다.
 */
const sortedSteps = computed(() => {
  const steps = props.recipe?.steps ?? [];

  return [...steps].sort(
    (a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0),
  );
});

/*
 * 재료의 수량과 단위를 화면 표시용 문자열로 변환한다.
 *
 * 예)
 * quantity: 500, unit: 'g'
 * → "500 g"
 *
 * quantity: 2, unit 없음
 * → "2"
 *
 * 수량이 입력되지 않은 경우에는 빈 화면으로 두지 않고
 * 사용자가 상태를 이해할 수 있도록 "수량 미입력"을 표시한다.
 */
const isIncomplete = computed(() => {
  return sortedIngredients.value.length === 0 || sortedSteps.value.length === 0;
});

/** 레시피 관리 버튼은 동일한 외형을 유지하고 권한이 없으면 알림만 요청합니다. */
function requestAction(action) {
  if (!props.canManage) {
    emit('permission-denied', '레시피를 관리할 권한이 없습니다.');
    return;
  }

  if (props.recipe?.deleted_at && !['restore'].includes(action)) {
    emit('permission-denied', '삭제된 레시피입니다. 복구 후 이용해주세요.');
    return;
  }

  emit(action);
}

/** 감사 로그 동작 코드를 사용자에게 보여줄 한글 이름으로 변환합니다. */
function auditActionText(action) {
  return { create: '등록', update: '수정', delete: '삭제' }[action] ?? '-';
}

/** 관리 이력의 작업자와 일시를 카드용 한 줄 문자열로 만듭니다. */
function historyText(entry, fallbackAt = null) {
  const actor = entry?.user?.name ?? '-';
  const value = entry?.at ?? fallbackAt;
  const at = formatHistoryDateTime(value);

  return `${actor} · ${at}`;
}

/** 관리 이력 일시를 분 단위의 한국어 날짜 형식으로 표시합니다. */
function formatHistoryDateTime(value) {
  if (!value) return '-';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';

  return new Intl.DateTimeFormat('ko-KR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date);
}

/** 재료 사용량이 없으면 하이픈, 있으면 불필요한 소수점 0을 제거해 표시합니다. */
function ingredientQuantity(item) {
  const quantity = item?.quantity;
  return quantity === null || quantity === undefined || quantity === ''
    ? '-'
    : formatQuantity(quantity);
}

/** 재료 단위가 비어 있으면 공통 누락 표시인 하이픈을 반환합니다. */
function ingredientUnit(item) {
  return String(item?.unit ?? '').trim() || '-';
}

/** DB decimal 문자열의 불필요한 뒤쪽 0을 제거해 입력한 수량을 자연스럽게 보여줍니다. */
function formatQuantity(value) {
  const text = String(value);

  if (!text.includes('.')) {
    return text;
  }

  return text.replace(/\.?0+$/, '');
}

/*
 * 재료와 공정의 화면 표시 순서를 만든다.
 *
 * 배열 index는 0부터 시작하기 때문에 1을 더한 뒤,
 * padStart를 사용하여 01, 02, 03... 형태로 통일한다.
 *
 * DB의 sort_order를 직접 표시하지 않기 때문에
 * sort_order 값이 0부터 시작하거나 중간 값이 비어 있어도
 * 사용자에게는 항상 연속된 번호가 표시된다.
 */
function formatOrder(index) {
  return String(index + 1).padStart(2, '0');
}
</script>

<style scoped>
/* 레시피 카드 전체 영역 */

.recipe-card {
  padding: 16px;
  border: 1px solid
    rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;

  /* 긴 재료명이나 설명이 카드 밖으로 밀려나지 않도록 처리한다. */
  overflow-wrap: anywhere;
}

.recipe-card--deleted {
  opacity: 0.58;
}

.recipe-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

/* 레시피 기본 정보 */

.recipe-heading {
  display: flex;
  width: 100%;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;

  padding: 0;
  border: 0;
  background: transparent;

  color: inherit;
  text-align: left;
  font: inherit;
}

/*
 * flex 내부의 긴 텍스트가 부모 너비를 밀어내는 것을 방지한다.
 * 특히 긴 레시피명과 설명이 있는 모바일 화면에서 필요하다.
 */
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

  /* 사용자가 입력한 줄바꿈을 상세 화면에서도 유지한다. */
  white-space: pre-wrap;
}

/* 재료 / 공정 공통 영역 */

.recipe-block {
  margin-top: 20px;
}

.recipe-label {
  display: flex;
  align-items: center;
  gap: 6px;

  margin-bottom: 8px;

  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.8rem;
  font-weight: 700;
}

/*
 * 재료와 공정은 각각 세로 목록으로 표시한다.
 * 항목 사이에는 일정한 간격을 두어 터치 영역을 명확하게 구분한다.
 */
.recipe-items,
.recipe-steps {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

/* 재료 항목 */

.recipe-item {
  display: flex;
  width: 100%;
  min-width: 0;
  align-items: center;
  gap: 10px;

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
 * 재료 번호와 공정 번호는 같은 디자인을 사용한다.
 * 번호가 본문보다 먼저 눈에 들어오되 과하게 강조되지 않도록 한다.
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

.recipe-item-content {
  display: flex;

  min-width: 0;
  flex: 1 1 auto;

  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
}

.recipe-item-name {
  min-width: 0;

  font-size: 0.875rem;
  font-weight: 650;

  /*
   * 재료명이 길어도 수량 영역을 화면 밖으로 밀어내지 않고
   * 필요한 경우 자연스럽게 줄바꿈한다.
   */
  overflow-wrap: anywhere;
}

.recipe-item-meta {
  /*
   * 일반 화면에서는 수량/단위가 불필요하게 줄어들지 않도록 한다.
   * 작은 화면에서는 아래 media query에서 세로 배치로 전환한다.
   */
  flex: 0 0 auto;

  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.8rem;
}

/* 공정 항목 */

.recipe-step {
  display: flex;
  width: 100%;
  min-width: 0;
  align-items: center;
  gap: 10px;

  padding: 10px 11px;

  border: 1px solid
    rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 9px;

  background: transparent;
  color: inherit;
  text-align: left;
  font: inherit;
}

.recipe-step-description {
  min-width: 0;
  flex: 1 1 auto;

  font-size: 0.875rem;
  line-height: 1.5;

  /*
   * 사용자가 입력한 공정의 줄바꿈은 유지하고,
   * 긴 단어나 문자열 때문에 가로 스크롤이 생기지 않도록 한다.
   */
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

/* 수정 가능한 항목의 터치 피드백 */

.recipe-item-arrow {
  flex: 0 0 auto;

  /*
   * 화살표는 수정 가능한 영역이라는 보조 표시이므로
   * 본문보다 약하게 표현한다.
   */
  color: rgba(var(--v-theme-on-surface), 0.42);
}

.recipe-touchable {
  cursor: pointer;

  /*
   * 사용자가 항목을 터치했을 때 가벼운 반응만 제공한다.
   * 업무용 화면이므로 과한 애니메이션은 사용하지 않는다.
   */
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
  /*
   * 모바일에서도 눌렀다는 느낌을 받을 수 있도록
   * 아주 작은 크기 변화만 적용한다.
   */
  transform: scale(0.995);
}

/* 빈 상태 */

.empty-text {
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.8rem;
}

.empty-recipe {
  padding: 10px 0;
  text-align: center;
}

.recipe-item-meta {
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
  white-space: nowrap;
}

.recipe-item-unit {
  color: rgba(var(--v-theme-on-surface), 0.58);
}

.recipe-status-row {
  display: flex;
  justify-content: flex-start;
  margin-top: 8px;
}

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
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding-top: 7px;
  overflow-wrap: anywhere;
}

.recipe-parent-deleted-hint {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.76rem;
  line-height: 1.45;
}

.recipe-history {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.72rem;
  line-height: 1.4;
  text-align: left;
}

.recipe-history-label {
  margin-top: 4px;
  font-weight: 700;
}

/* 390px 이하에서는 재료명과 수량을 세로로 전환해 좁은 화면의 겹침을 방지합니다. */
@media (max-width: 390px) {
  .recipe-card {
    padding: 14px;
  }

  /*
   * 모바일에서는 긴 내용이 여러 줄이 될 수 있으므로
   * 번호와 내용이 위쪽을 기준으로 정렬되도록 한다.
   */
  .recipe-item,
  .recipe-step {
    align-items: flex-start;
    padding: 9px;
  }

  /*
   * 재료명과 수량을 세로로 배치한다.
   *
   * 예)
   * 강력분
   * 500 g
   */
  .recipe-item-content {
    flex-direction: column;
    gap: 2px;
  }

  .recipe-item-meta {
    flex: 1 1 auto;
  }
}
</style>
