<template>
  <!-- 레시피 복사 -->
  <AppDialogLayout
    :model-value="copyOpen"
    title="레시피 복사"
    subtitle="원본 레시피는 유지하고 다른 제품에 복사합니다."
    :max-width="460"
    persistent
    closable
    card-class="app-dialog-card--compact"
    :busy="Boolean(loading)"
    @close="requestCloseCopy"
    @update:model-value="value => { if (!value) requestCloseCopy(); }"
  >
    <div class="d-flex flex-column ga-2 px-2 pt-0 pb-2">
      <AppInfoAlert>
        같은 점포의 레시피가 없는 제품에 새 레시피를 만듭니다.
      </AppInfoAlert>

      <v-select
        v-model="copyTargetId"
        :items="copyTargets"
        item-title="name"
        item-value="id"
        label="복사할 제품"
        variant="outlined"
        density="compact"
        class="app-select"
        hide-details
        :disabled="Boolean(loading)"
      />
    </div>

    <template #footer>
      <AppDialogFooter
        :loading="Boolean(loading)"
        cancel-text="취소"
        confirm-text="복사"
        @cancel="requestCloseCopy"
        @confirm="requestCopy"
      />
    </template>
  </AppDialogLayout>

  <!-- 레시피 등록 및 수정 -->
  <AppDialogLayout
    :model-value="editorOpen"
    :title="dialogTitle"
    :subtitle="dialogSubtitle"
    :max-width="620"
    persistent
    closable
    card-class="app-dialog-card--compact"
    :busy="Boolean(loading)"
    @close="requestCloseEditor"
    @update:model-value="value => { if (!value) requestCloseEditor(); }"
  >
    <div class="d-flex flex-column ga-2 px-2 pt-0 pb-2">
      <AppInfoAlert>
        * 표시는 필수 입력 항목입니다. 재료와 공정은 저장된 순서대로 표시됩니다.
      </AppInfoAlert>

      <!-- 기본 내용 -->
      <v-card
        v-if="editMode === 'full' || editMode === 'basic'"
        variant="flat"
        rounded="lg"
        class="app-form-section app-form-stack pa-3"
      >
        <div class="app-form-section-title">
          기본 내용
        </div>

        <v-text-field
          v-model="recipe.name"
          label="레시피명 *"
          variant="outlined"
          density="compact"
          class="app-input"
          hide-details
        />

        <v-textarea
          v-model="recipe.description"
          label="설명"
          variant="outlined"
          density="compact"
          class="app-input"
          rows="2"
          auto-grow
          hide-details
        />
      </v-card>

      <!-- 재료 -->
      <v-card
        v-if="editMode === 'full' || editMode === 'ingredient'"
        variant="flat"
        rounded="lg"
        class="app-form-section app-form-stack pa-3"
      >
        <div class="app-form-section-title">
          재료
        </div>

        <div class="app-supporting-text text-medium-emphasis">
          {{ ingredientGuideText }}
        </div>

        <div class="d-flex flex-column ga-2">
          <div
            v-for="(ingredient, index) in visibleIngredients"
            :key="ingredient._key"
            class="d-flex align-start ga-2 pa-2 border rounded-lg"
          >
            <div class="d-flex flex-column flex-grow-1 ga-2 min-width-0">
              <div class="app-supporting-text font-weight-bold">
                재료 {{ actualIngredientIndex(index) + 1 }}
              </div>

              <div class="app-form-stack">
                <v-text-field
                  v-model="ingredient.name"
                  label="재료명 *"
                  variant="outlined"
                  density="compact"
                  class="app-input"
                  hide-details
                />

                <div class="d-flex flex-column flex-sm-row ga-2 min-width-0">
                  <v-text-field
                    v-model="ingredient.quantity"
                    label="사용량"
                    type="number"
                    inputmode="decimal"
                    step="any"
                    min="0"
                    variant="outlined"
                    density="compact"
                    class="app-input flex-grow-1 min-width-0"
                    hide-details
                  />

                  <div class="d-flex flex-column ga-2 flex-grow-1 min-width-0">
                    <v-select
                      v-model="ingredient.unitChoice"
                      :items="unitItems"
                      label="단위"
                      variant="outlined"
                      density="compact"
                      class="app-select"
                      hide-details
                      @update:model-value="applyUnitChoice(ingredient)"
                    />

                    <v-text-field
                      v-if="ingredient.unitChoice === CUSTOM_UNIT"
                      v-model="ingredient.unit"
                      label="단위 직접입력"
                      placeholder="예: 꼬집"
                      variant="outlined"
                      density="compact"
                      class="app-input"
                      hide-details
                    />
                  </div>
                </div>
              </div>
            </div>

            <v-btn
              v-if="editMode === 'full'"
              icon="mdi-close"
              size="small"
              variant="text"
              aria-label="재료 삭제"
              class="flex-shrink-0"
              @click="requestRemoveIngredient(actualIngredientIndex(index))"
            />
          </div>
        </div>

        <v-btn
          v-if="editMode === 'full'"
          block
          variant="tonal"
          min-height="44"
          @click="addIngredient"
        >
          재료 추가
        </v-btn>
      </v-card>

      <!-- 공정 -->
      <v-card
        v-if="editMode === 'full' || editMode === 'step'"
        variant="flat"
        rounded="lg"
        class="app-form-section app-form-stack pa-3"
      >
        <div class="app-form-section-title">
          공정
        </div>

        <div class="app-supporting-text text-medium-emphasis">
          {{ stepGuideText }}
        </div>

        <div class="d-flex flex-column ga-2">
          <div
            v-for="(step, index) in visibleSteps"
            :key="step._key"
            class="d-flex align-start ga-2 pa-2 border rounded-lg"
          >
            <div class="d-flex flex-column ga-2 flex-grow-1 min-width-0">
              <div class="app-supporting-text font-weight-bold">
                공정 {{ actualStepIndex(index) + 1 }}
              </div>

              <v-textarea
                v-model="step.description"
                label="공정 내용 *"
                variant="outlined"
                density="compact"
                class="app-input"
                rows="3"
                auto-grow
                hide-details
              />
            </div>

            <div
              v-if="editMode === 'full'"
              class="d-flex flex-column ga-1 flex-shrink-0"
            >
              <v-btn
                icon="mdi-chevron-up"
                size="small"
                variant="text"
                :disabled="actualStepIndex(index) === 0"
                aria-label="공정 위로 이동"
                @click="moveStep(actualStepIndex(index), -1)"
              />

              <v-btn
                icon="mdi-chevron-down"
                size="small"
                variant="text"
                :disabled="actualStepIndex(index) === recipe.steps.length - 1"
                aria-label="공정 아래로 이동"
                @click="moveStep(actualStepIndex(index), 1)"
              />

              <v-btn
                icon="mdi-close"
                size="small"
                variant="text"
                aria-label="공정 삭제"
                @click="requestRemoveStep(actualStepIndex(index))"
              />
            </div>
          </div>
        </div>

        <v-btn
          v-if="editMode === 'full'"
          block
          variant="tonal"
          min-height="44"
          @click="addStep"
        >
          공정 추가
        </v-btn>
      </v-card>

      <!-- 시스템 정보 -->
      <v-card
        v-if="editingId"
        variant="flat"
        rounded="lg"
        class="app-form-section app-form-stack pa-3"
      >
        <div class="app-form-section-title">
          시스템 정보
        </div>

        <div class="d-flex flex-column ga-2 app-supporting-text">
          <div
            v-for="item in historyRows"
            :key="item.label"
            class="d-flex justify-space-between ga-2"
          >
            <span class="text-medium-emphasis">
              {{ item.label }}
            </span>
            <span class="text-right min-width-0">
              {{ item.value }}
            </span>
          </div>
        </div>
      </v-card>
    </div>

    <template #footer>
      <AppDialogFooter
        :loading="Boolean(loading)"
        cancel-text="취소"
        :confirm-text="editingId ? '저장' : '등록'"
        @cancel="requestCloseEditor"
        @confirm="requestSave"
      />
    </template>
  </AppDialogLayout>

  <!-- 공통 확인창 -->
  <ConfirmDialog
    v-if="confirm.open"
    :model-value="confirm.open"
    :title="confirm.title"
    :message="confirm.message"
    :loading="Boolean(loading)"
    @update:model-value="value => { if (!value) clearConfirm(); }"
    @cancel="clearConfirm"
    @confirm="executeConfirm"
  />
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import AppDialogLayout from '../common/AppDialogLayout.vue';
import AppDialogFooter from '../common/AppDialogFooter.vue';
import AppInfoAlert from '../common/AppInfoAlert.vue';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import { compareDisplayName } from '../../utils/naturalSort';

const props = defineProps({
  product: {
    type: Object,
    default: null,
  },
  products: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits([
  'error',
  'success',
  'refresh-product',
  'refresh-list',
]);

const editorOpen = ref(false);
const copyOpen = ref(false);
const copyTargetId = ref(null);
const loading = ref(null);
const editingId = ref(null);
const editMode = ref('full');
const editIndex = ref(null);
const initialSnapshot = ref('');
const recentUnits = ref([]);

let rowKey = 0;

const CUSTOM_UNIT = '__custom__';

const DEFAULT_UNITS = [
  'g',
  'kg',
  'ml',
  'L',
  '개',
  '장',
  '봉',
  '팩',
  '병',
  '캔',
  '스푼',
  '작은술',
  '큰술',
];

const recipe = reactive({
  name: '',
  description: '',
  ingredients: [],
  steps: [],
});

const confirm = reactive({
  open: false,
  action: '',
  title: '',
  message: '',
  payload: null,
});

const activeRecipe = computed(() =>
  props.product?.recipes?.find(item => !item.deleted_at) ?? null
);

const displayedRecipe = computed(() =>
  activeRecipe.value ?? props.product?.recipes?.[0] ?? null
);

const managementInfo = computed(() =>
  props.product?.recipes?.find(
    item => Number(item.id) === Number(editingId.value)
  ) ?? null
);

const unitItems = computed(() => [
  ...[...new Set([...recentUnits.value, ...DEFAULT_UNITS])]
    .map(unit => ({
      title: unit,
      value: unit,
    })),
  {
    title: '직접입력',
    value: CUSTOM_UNIT,
  },
]);

const copyTargets = computed(() => {
  if (!props.product) return [];

  return props.products
    .filter(item =>
      Number(item.store_id) === Number(props.product.store_id)
      && Number(item.id) !== Number(props.product.id)
      && !item.deleted_at
      && !item.recipes?.some(recipeItem => !recipeItem.deleted_at)
    )
    .sort((a, b) => compareDisplayName(a.name, b.name));
});

const dialogTitle = computed(() => {
  if (!editingId.value) return '레시피 등록';

  if (editMode.value === 'ingredient') {
    return '재료 수정';
  }

  if (editMode.value === 'step') {
    return '공정 수정';
  }

  if (editMode.value === 'basic') {
    return '레시피 기본 내용 수정';
  }

  return '레시피 수정';
});

const dialogSubtitle = computed(() => {
  if (editMode.value === 'ingredient') {
    return '선택한 재료만 빠르게 수정합니다.';
  }

  if (editMode.value === 'step') {
    return '선택한 공정 단계만 빠르게 수정합니다.';
  }

  if (editMode.value === 'basic') {
    return '레시피명과 설명을 수정합니다.';
  }

  return '재료와 공정을 순서대로 추가해 레시피를 완성합니다.';
});

const visibleIngredients = computed(() =>
  editMode.value === 'ingredient'
    ? (
      recipe.ingredients[editIndex.value]
        ? [recipe.ingredients[editIndex.value]]
        : []
    )
    : recipe.ingredients
);

const visibleSteps = computed(() =>
  editMode.value === 'step'
    ? (
      recipe.steps[editIndex.value]
        ? [recipe.steps[editIndex.value]]
        : []
    )
    : recipe.steps
);

const meaningfulIngredients = () =>
  recipe.ingredients.filter(item =>
    item.name.trim()
    || (item.quantity !== null && item.quantity !== '')
    || item.unit.trim()
  );

const meaningfulSteps = () =>
  recipe.steps.filter(item => item.description.trim());

const ingredientGuideText = computed(() =>
  editMode.value === 'ingredient'
    ? '선택한 재료의 이름, 사용량, 단위를 확인하고 수정해주세요.'
    : meaningfulIngredients().length
      ? `현재 재료 ${meaningfulIngredients().length}개가 등록되어 있습니다. 필요한 재료를 추가하거나 수정해주세요.`
      : '재료를 하나씩 추가해 레시피를 완성해보세요.'
);

const stepGuideText = computed(() =>
  editMode.value === 'step'
    ? '선택한 공정의 작업 내용을 확인하고 수정해주세요.'
    : meaningfulSteps().length
      ? `현재 공정 ${meaningfulSteps().length}단계가 등록되어 있습니다. 순서를 확인하거나 필요한 공정을 추가해주세요.`
      : '공정을 하나씩 추가하면 순서 번호는 자동으로 정리됩니다.'
);

const canSave = computed(() =>
  Boolean(recipe.name.trim())
  && !meaningfulIngredients().some(item =>
    !item.name.trim()
    || (
      item.quantity !== ''
      && item.quantity !== null
      && Number(item.quantity) < 0
    )
  )
  && !meaningfulSteps().some(item =>
    !item.description.trim()
  )
  && !loading.value
);

const snapshot = () => JSON.stringify({
  name: recipe.name,
  description: recipe.description,
  ingredients: recipe.ingredients.map(
    ({ name, quantity, unit }) => ({
      name,
      quantity,
      unit,
    })
  ),
  steps: recipe.steps.map(
    ({ description }) => ({
      description,
    })
  ),
});

const hasChanges = computed(() =>
  Boolean(initialSnapshot.value)
  && snapshot() !== initialSnapshot.value
);

const historyRows = computed(() => {
  const history = managementInfo.value?.management_history;

  const at = (entry, fallback) => {
    const value = entry?.at ?? fallback;

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
  };

  const actor = entry => entry?.user?.name ?? '-';

  return [
    {
      label: '등록자',
      value: actor(history?.created),
    },
    {
      label: '등록일',
      value: at(
        history?.created,
        managementInfo.value?.created_at
      ),
    },
    {
      label: '수정자',
      value: actor(history?.updated),
    },
    {
      label: '수정일',
      value: at(
        history?.updated,
        managementInfo.value?.updated_at
      ),
    },
    {
      label: '삭제자',
      value: managementInfo.value?.deleted_at
        ? actor(history?.deleted)
        : '-',
    },
    {
      label: '삭제일',
      value: managementInfo.value?.deleted_at
        ? at(
          history?.deleted,
          managementInfo.value?.deleted_at
        )
        : '-',
    },
  ];
});

function formatQuantity(value) {
  if (
    value === null
    || value === undefined
    || value === ''
  ) {
    return null;
  }

  const text = String(value);

  return text.includes('.')
    ? text.replace(/\.?0+$/, '')
    : text;
}

function createIngredientRow(item = {}) {
  const unit = String(item.unit ?? '').trim();

  return {
    _key: `ingredient-${++rowKey}`,
    name: item.name ?? '',
    quantity: formatQuantity(item.quantity),
    unit,
    unitChoice: !unit || DEFAULT_UNITS.includes(unit)
      ? unit
      : CUSTOM_UNIT,
  };
}

function createStepRow(item = {}) {
  return {
    _key: `step-${++rowKey}`,
    description: item.description ?? '',
  };
}

function createForm(source) {
  if (!source) {
    return {
      name: '',
      description: '',
      ingredients: [createIngredientRow()],
      steps: [createStepRow()],
    };
  }

  return {
    name: source.name ?? '',
    description: source.description ?? '',
    ingredients: [...(source.ingredients ?? [])]
      .sort(
        (a, b) =>
          Number(a.sort_order ?? 0)
          - Number(b.sort_order ?? 0)
      )
      .map(createIngredientRow),
    steps: [...(source.steps ?? [])]
      .sort(
        (a, b) =>
          Number(a.sort_order ?? 0)
          - Number(b.sort_order ?? 0)
      )
      .map(createStepRow),
  };
}

function ensureProduct() {
  if (!props.product || props.product.deleted_at) {
    emit(
      'error',
      '삭제된 제품입니다. 복구 후 레시피를 관리해주세요.'
    );

    return false;
  }

  return true;
}

function openEditor() {
  if (!ensureProduct() || loading.value) return;

  const source = activeRecipe.value;

  editingId.value = source?.id ?? null;
  editMode.value = 'full';
  editIndex.value = null;

  Object.assign(recipe, createForm(source));

  initialSnapshot.value = snapshot();
  editorOpen.value = true;
}

function openPart(part) {
  if (!ensureProduct() || loading.value) return;

  const source = activeRecipe.value;

  if (
    !source
    || !['basic', 'ingredient', 'step'].includes(part?.type)
  ) {
    return;
  }

  const index = Number.isInteger(part.index)
    ? part.index
    : null;

  if (
    part.type === 'ingredient'
    && !source.ingredients?.[index]
  ) {
    return;
  }

  if (
    part.type === 'step'
    && !source.steps?.[index]
  ) {
    return;
  }

  editingId.value = source.id;
  editMode.value = part.type;
  editIndex.value = index;

  Object.assign(recipe, createForm(source));

  initialSnapshot.value = snapshot();
  editorOpen.value = true;
}

function openCopy() {
  if (
    !ensureProduct()
    || !activeRecipe.value
    || loading.value
  ) {
    return;
  }

  copyTargetId.value = null;
  copyOpen.value = true;
}

function actualIngredientIndex(index) {
  return editMode.value === 'ingredient'
    ? editIndex.value
    : index;
}

function actualStepIndex(index) {
  return editMode.value === 'step'
    ? editIndex.value
    : index;
}

function applyUnitChoice(item) {
  if (item.unitChoice === CUSTOM_UNIT) {
    if (DEFAULT_UNITS.includes(item.unit)) {
      item.unit = '';
    }
  } else {
    item.unit = item.unitChoice ?? '';
  }
}

function addIngredient() {
  recipe.ingredients.push(createIngredientRow());
}

function addStep() {
  recipe.steps.push(createStepRow());
}

function moveStep(index, direction) {
  const target = index + direction;

  if (
    target < 0
    || target >= recipe.steps.length
  ) {
    return;
  }

  const [item] = recipe.steps.splice(index, 1);

  recipe.steps.splice(target, 0, item);
}

function ask(action, title, message, payload = null) {
  if (loading.value) return;

  Object.assign(confirm, {
    open: true,
    action,
    title,
    message,
    payload,
  });
}

function clearConfirm() {
  if (loading.value) return;

  Object.assign(confirm, {
    open: false,
    action: '',
    title: '',
    message: '',
    payload: null,
  });
}

function requestRemoveIngredient(index) {
  const item = recipe.ingredients[index];

  if (!item) return;

  if (
    !item.name.trim()
    && (item.quantity === null || item.quantity === '')
    && !item.unit.trim()
  ) {
    recipe.ingredients.splice(index, 1);
    return;
  }

  ask(
    'remove-ingredient',
    '재료를 삭제하시겠습니까?',
    `‘${item.name || `재료 ${index + 1}`}’ 항목을 현재 편집 내용에서 삭제합니다.`,
    { index }
  );
}

function requestRemoveStep(index) {
  const item = recipe.steps[index];

  if (!item) return;

  if (!item.description.trim()) {
    recipe.steps.splice(index, 1);
    return;
  }

  ask(
    'remove-step',
    '공정을 삭제하시겠습니까?',
    `공정 ${index + 1}의 입력 내용을 삭제합니다.`,
    { index }
  );
}

function requestCloseEditor() {
  if (loading.value) return;

  if (!hasChanges.value) {
    editorOpen.value = false;
    return;
  }

  ask(
    'discard-editor',
    '레시피 수정을 취소하시겠습니까?',
    '저장하지 않은 레시피 변경 내용은 사라집니다.'
  );
}

function requestCloseCopy() {
  if (loading.value) return;

  if (!copyTargetId.value) {
    copyOpen.value = false;
    return;
  }

  ask(
    'discard-copy',
    '작성을 취소하시겠습니까?',
    '선택한 레시피 복사 대상은 저장되지 않습니다.'
  );
}

function requestSave() {
  if (loading.value) return;

  if (!canSave.value) {
    emit(
      'error',
      '레시피명을 입력하고 재료명 및 사용량을 확인해주세요.'
    );

    return;
  }

  ask(
    'save',
    editingId.value
      ? '레시피를 수정하시겠습니까?'
      : '레시피를 등록하시겠습니까?',
    '현재 입력한 레시피 내용을 저장합니다.'
  );
}

function requestCopy() {
  if (loading.value) return;

  if (!copyTargetId.value) {
    emit('error', '복사할 제품을 선택해주세요.');
    return;
  }

  const target = copyTargets.value.find(
    item => Number(item.id) === Number(copyTargetId.value)
  );

  if (!target) {
    emit('error', '복사할 제품을 다시 선택해주세요.');
    return;
  }

  ask(
    'copy',
    '레시피를 복사하시겠습니까?',
    `현재 레시피를 ‘${target.name}’ 제품에 새 레시피로 복사합니다.`
  );
}

function requestDelete(requested = null) {
  const target = requested ?? displayedRecipe.value;

  if (
    !target
    || target.deleted_at
    || loading.value
  ) {
    return;
  }

  ask(
    'delete',
    '레시피를 삭제하시겠습니까?',
    '레시피는 삭제 상태로 보관되며 나중에 복구할 수 있습니다.',
    { recipeId: target.id }
  );
}

function requestRestore(requested = null) {
  const target = requested ?? displayedRecipe.value;

  if (!target?.deleted_at || loading.value) return;

  ask(
    'restore',
    '레시피를 복구하시겠습니까?',
    '삭제된 레시피의 재료와 공정을 그대로 복구합니다.',
    { recipeId: target.id }
  );
}

function failureMessage(error, fallback) {
  if (!error.response) {
    return '서버 응답을 확인하지 못했습니다. 작업이 이미 반영되었을 수 있으므로 목록을 다시 확인해주세요.';
  }

  const first = Object.values(
    error.response.data?.errors ?? {}
  ).flat().find(Boolean);

  return first
    || error.response.data?.message
    || fallback;
}

async function executeConfirm() {
  const action = confirm.action;
  const payload = confirm.payload;

  if (!action || loading.value) return;

  if (action === 'remove-ingredient') {
    recipe.ingredients.splice(payload.index, 1);
  } else if (action === 'remove-step') {
    recipe.steps.splice(payload.index, 1);
  } else if (action === 'discard-editor') {
    editorOpen.value = false;
  } else if (action === 'discard-copy') {
    copyOpen.value = false;
    copyTargetId.value = null;
  } else if (
    ['save', 'copy', 'delete', 'restore'].includes(action)
  ) {
    await performRequest(action, payload);
  }

  clearConfirm();
}

async function performRequest(action, payload) {
  if (!props.product || loading.value) return;

  const productId = props.product.id;

  loading.value = action;

  try {
    if (action === 'save') {
      const body = {
        name: recipe.name.trim(),
        description: recipe.description.trim() || null,
        ingredients: meaningfulIngredients().map(item => ({
          name: item.name.trim(),
          quantity:
            item.quantity === null || item.quantity === ''
              ? null
              : Number(item.quantity),
          unit: item.unit.trim() || null,
        })),
        steps: meaningfulSteps().map(item => ({
          description: item.description.trim(),
        })),
      };

      const usedUnits = body.ingredients
        .map(item => item.unit)
        .filter(Boolean);

      recentUnits.value = [
        ...new Set([...usedUnits, ...recentUnits.value]),
      ].slice(0, 5);

      if (editingId.value) {
        await window.axios.put(
          `/tillwhite/api/products/${productId}/recipes/${editingId.value}`,
          body
        );
      } else {
        await window.axios.post(
          `/tillwhite/api/products/${productId}/recipes`,
          body
        );
      }

      const wasEditing = Boolean(editingId.value);

      const message =
        editMode.value === 'ingredient'
          ? `재료 ${Number(editIndex.value) + 1}번을 수정했습니다.`
          : editMode.value === 'step'
            ? `공정 ${Number(editIndex.value) + 1}을(를) 수정했습니다.`
            : wasEditing
              ? '레시피를 수정했습니다.'
              : '레시피를 등록했습니다.';

      editorOpen.value = false;

      emit('success', message);
      emit('refresh-product');

    } else if (action === 'copy') {
      const source = activeRecipe.value;

      if (!source || !copyTargetId.value) return;

      await window.axios.post(
        `/tillwhite/api/products/${productId}/recipes/${source.id}/copy`,
        {
          target_product_id: copyTargetId.value,
        }
      );

      copyOpen.value = false;
      copyTargetId.value = null;

      emit('success', '레시피를 복사했습니다.');
      emit('refresh-list');

    } else if (
      action === 'delete'
      || action === 'restore'
    ) {
      const url =
        `/tillwhite/api/products/${productId}/recipes/${payload.recipeId}`
        + (action === 'restore' ? '/restore' : '');

      if (action === 'delete') {
        await window.axios.delete(url);
      } else {
        await window.axios.put(url);
      }

      emit(
        'success',
        action === 'delete'
          ? '레시피를 삭제했습니다.'
          : '레시피를 복구했습니다.'
      );

      emit('refresh-product');
    }

  } catch (error) {
    const fallback = {
      save: editingId.value
        ? '레시피 수정에 실패했습니다.'
        : '레시피 등록에 실패했습니다.',
      copy: '레시피 복사에 실패했습니다.',
      delete: '레시피 작업을 완료하지 못했습니다.',
      restore: '레시피 작업을 완료하지 못했습니다.',
    }[action];

    emit('error', failureMessage(error, fallback));

  } finally {
    loading.value = null;
  }
}

defineExpose({
  openEditor,
  openPart,
  openCopy,
  requestDelete,
  requestRestore,
});
</script>

<style scoped>
.min-width-0 {
  min-width: 0;
}
</style>