<template>
  <!-- 제품 상세에 표시되는 레시피 요약 카드 -->
  <div
    v-if="recipe"
    class="recipe-card"
  >
    <div class="recipe-name">
      {{ recipe.name || '레시피' }}
    </div>

    <div
      v-if="recipe.description"
      class="recipe-description"
    >
      {{ recipe.description }}
    </div>

    <!-- 재료 -->
    <div class="recipe-block">
      <div class="recipe-label">
        재료
      </div>

      <div
        v-if="sortedIngredients.length"
        class="recipe-lines"
      >
        <div
          v-for="item in sortedIngredients"
          :key="item.id ?? `${item.name}-${item.sort_order}`"
        >
          {{ ingredientText(item) }}
        </div>
      </div>

      <div
        v-else
        class="empty-text"
      >
        등록된 재료가 없습니다.
      </div>
    </div>

    <!-- 공정 -->
    <div class="recipe-block">
      <div class="recipe-label">
        공정
      </div>

      <ol
        v-if="sortedSteps.length"
        class="recipe-steps"
      >
        <li
          v-for="step in sortedSteps"
          :key="step.id ?? step.sort_order"
        >
          {{ step.description }}
        </li>
      </ol>

      <div
        v-else
        class="empty-text"
      >
        등록된 공정이 없습니다.
      </div>
    </div>
  </div>

  <!-- 아직 레시피가 없는 제품 -->
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

const props = defineProps({
  recipe: {
    type: Object,
    default: null,
  },
  canManage: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'edit',
]);

/** 재료는 저장된 표시 순서대로 보여줍니다. */
const sortedIngredients = computed(() => {
  return [...(props.recipe?.ingredients ?? [])].sort(
    (a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0),
  );
});

/** 공정은 저장된 표시 순서대로 보여주고 화면에서 자동 번호를 붙입니다. */
const sortedSteps = computed(() => {
  return [...(props.recipe?.steps ?? [])].sort(
    (a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0),
  );
});

function ingredientText(item) {
  const parts = [item.name];

  if (
    item.quantity !== null
    && item.quantity !== undefined
    && item.quantity !== ''
  ) {
    const quantity = item.unit
      ? `${item.quantity} ${item.unit}`
      : String(item.quantity);

    parts.push(quantity);
  }

  return parts
    .filter(Boolean)
    .join(' · ');
}
</script>

<style scoped>
.recipe-card {
  padding: 16px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  overflow-wrap: anywhere;
}

.recipe-name {
  font-size: 1rem;
  font-weight: 700;
}

.recipe-description {
  margin-top: 5px;
  color: rgba(var(--v-theme-on-surface), 0.68);
  white-space: pre-wrap;
}

.recipe-block {
  margin-top: 18px;
}

.recipe-label {
  margin-bottom: 7px;
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.8rem;
  font-weight: 700;
}

.recipe-lines {
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 0.875rem;
}

.recipe-steps {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin: 0;
  padding-left: 1.35rem;
  font-size: 0.875rem;
}

.empty-text {
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.8rem;
}

.empty-recipe {
  padding: 10px 0;
  text-align: center;
}
</style>
