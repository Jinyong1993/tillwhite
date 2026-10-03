<template>
  <!-- 카테고리 관리 다이얼로그 -->
  <v-dialog
    :model-value="modelValue"
    max-width="560"
    persistent
    @update:model-value="
      !loading && $emit('update:modelValue', $event)
    "
  >
    <v-card
      class="category-dialog"
      rounded="lg"
    >
      <!-- 제목 -->
      <v-card-title class="pa-5 pb-2">
        카테고리 관리
      </v-card-title>

      <v-card-text class="px-5">
        <!-- 안내 문구 -->
        <div class="text-caption text-medium-emphasis mb-4">
          점포별 카테고리를 관리합니다.
          사용중단해도 기존 제품과 기록은 유지됩니다.
        </div>

        <!-- 여러 점포를 관리할 수 있는 경우 점포 선택 -->
        <v-select
          v-if="stores.length > 1"
          v-model="storeId"
          :items="stores"
          item-title="name"
          item-value="id"
          label="점포 *"
          variant="outlined"
        />

        <!-- 관리 가능한 점포가 하나인 경우 고정값으로 표시 -->
        <v-text-field
          v-else
          :model-value="stores[0]?.name ?? '-'"
          label="점포"
          readonly
          variant="outlined"
        />

        <!-- 새 카테고리 등록 -->
        <div class="category-create-row">
          <v-text-field
            v-model.trim="newName"
            label="새 카테고리명 *"
            maxlength="100"
            variant="outlined"
            :disabled="loading"
          />

          <v-btn
            class="mt-1"
            variant="flat"
            :disabled="!canCreateCategory"
            @click="add"
          >
            등록
          </v-btn>
        </div>

        <v-divider class="my-3" />

        <!-- 등록된 카테고리 목록 -->
        <div
          v-if="visibleCategories.length"
          class="category-list"
        >
          <div
            v-for="category in visibleCategories"
            :key="category.id"
            class="category-row"
          >
            <!-- 카테고리명 -->
            <div class="category-name">
              {{ category.name }}
            </div>

            <!-- 사용 상태 -->
            <v-chip
              size="x-small"
              :color="category.is_active ? 'success' : undefined"
              variant="tonal"
            >
              {{ category.is_active ? '사용중' : '사용중단' }}
            </v-chip>

            <!-- 카테고리명 수정 -->
            <v-btn
              size="small"
              variant="text"
              :disabled="loading"
              @click="openRenameDialog(category)"
            >
              수정
            </v-btn>

            <!-- 사용중단 / 재사용 -->
            <v-btn
              size="small"
              variant="text"
              :disabled="loading"
              @click="$emit('toggle', category)"
            >
              {{ category.is_active ? '중단' : '재사용' }}
            </v-btn>
          </div>
        </div>

        <!-- 등록된 카테고리가 없는 경우 -->
        <div
          v-else
          class="text-center text-body-2 text-medium-emphasis py-6"
        >
          등록된 카테고리가 없습니다.
        </div>
      </v-card-text>

      <!-- 하단 버튼 -->
      <v-card-actions class="px-5 pb-4">
        <v-spacer />

        <v-btn
          variant="text"
          :disabled="loading"
          @click="$emit('update:modelValue', false)"
        >
          닫기
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- 카테고리명 수정 -->
  <v-dialog
    v-model="renameDialog"
    max-width="420"
    persistent
  >
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">
        카테고리명 수정
      </v-card-title>

      <v-card-text class="px-5">
        <v-text-field
          v-model.trim="renameName"
          label="카테고리명 *"
          maxlength="100"
          variant="outlined"
          :disabled="loading"
          autofocus
          @keyup.enter="submitRename"
        />
      </v-card-text>

      <v-card-actions class="px-5 pb-4">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="closeRenameDialog"
        >
          취소
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          :disabled="!canRenameCategory"
          :loading="loading"
          @click="submitRename"
        >
          수정
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>


<script setup>
import {
  computed,
  ref,
  watch,
} from 'vue';


/*
 * Props
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  categories: {
    type: Array,
    default: () => [],
  },

  stores: {
    type: Array,
    default: () => [],
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
  'create',
  'rename',
  'toggle',
]);


/*
 * 입력 상태
 */
const storeId = ref(null);
const newName = ref('');
const renameDialog = ref(false);
const renameTarget = ref(null);
const renameName = ref('');
const pendingCreate = ref(null);


/*
 * 카테고리 관리 다이얼로그가 열리면
 * 관리 가능한 첫 번째 점포를 기본값으로 설정한다.
 */
watch(
  () => [
    props.modelValue,
    props.stores,
  ],
  () => {
    if (!props.modelValue || storeId.value) {
      return;
    }

    storeId.value = props.stores[0]?.id ?? null;
  },
  {
    immediate: true,
  },
);


/*
 * 현재 선택한 점포의 카테고리만 표시한다.
 */
const visibleCategories = computed(() => {
  return props.categories.filter((category) => {
    return Number(category.store_id) === Number(storeId.value);
  });
});


/*
 * 카테고리 등록 가능 여부
 */
const canCreateCategory = computed(() => {
  return Boolean(
    storeId.value
    && newName.value.trim()
    && !props.loading
  );
});


/** 카테고리명이 실제로 변경된 경우에만 수정할 수 있습니다. */
const canRenameCategory = computed(() => {
  const name = renameName.value.trim();

  return Boolean(
    renameTarget.value
    && name
    && name !== renameTarget.value.name
    && !props.loading
  );
});


/**
 * 수정 API가 성공해 categories가 갱신된 경우에만 수정창을 닫습니다.
 * 요청 실패 시에는 입력값과 수정창을 그대로 유지합니다.
 */
watch(
  () => props.categories,
  () => {
    if (pendingCreate.value) {
      const created = props.categories.some((category) => {
        return Number(category.store_id) === Number(pendingCreate.value.store_id)
          && category.name === pendingCreate.value.name;
      });

      if (created) {
        newName.value = '';
        pendingCreate.value = null;
      }
    }

    if (!renameDialog.value || !renameTarget.value) {
      return;
    }

    const updated = props.categories.find((category) => {
      return Number(category.id) === Number(renameTarget.value.id);
    });

    if (updated?.name === renameName.value.trim()) {
      renameDialog.value = false;
      renameTarget.value = null;
      renameName.value = '';
    }
  },
);

/*
 * 새 카테고리 등록
 */
function add() {
  const name = newName.value.trim();

  if (!name || !storeId.value) {
    return;
  }

  pendingCreate.value = {
    store_id: storeId.value,
    name,
  };

  emit('create', pendingCreate.value);
}


/** 카테고리명 수정 다이얼로그를 엽니다. */
function openRenameDialog(category) {
  if (props.loading) {
    return;
  }

  renameTarget.value = category;
  renameName.value = category.name ?? '';
  renameDialog.value = true;
}

function closeRenameDialog() {
  if (props.loading) {
    return;
  }

  renameDialog.value = false;
  renameTarget.value = null;
  renameName.value = '';
}

/** 수정값을 부모 화면의 기존 카테고리 수정 API 흐름으로 전달합니다. */
function submitRename() {
  if (!canRenameCategory.value) {
    return;
  }

  emit('rename', {
    category: renameTarget.value,
    name: renameName.value.trim(),
  });
}
</script>


<style scoped>
/* 카테고리 관리 다이얼로그 */
.category-dialog {
  max-height: calc(100vh - 24px);
  overflow: auto;
}


/* 새 카테고리 등록 영역 */
.category-create-row {
  display: flex;
  align-items: flex-start;

  gap: 8px;
}


/* 카테고리 목록 */
.category-list {
  display: flex;
  flex-direction: column;
}


/* 개별 카테고리 */
.category-row {
  display: grid;
  grid-template-columns:
    minmax(0, 1fr)
    auto
    auto
    auto;

  align-items: center;

  gap: 4px;
  padding: 8px 0;

  border-bottom:
    1px solid
    rgba(
      var(--v-border-color),
      var(--v-border-opacity)
    );
}


/* 긴 카테고리명은 잘라내지 않고 자연스럽게 개행 */
.category-name {
  min-width: 0;

  font-weight: 600;
  line-height: 1.4;

  overflow-wrap: anywhere;
  word-break: break-word;
}


/*
 * 작은 화면에서는 카테고리 정보를 2열로 배치하고
 * 기능 버튼이 다른 내용을 침범하지 않도록 처리한다.
 */
@media (max-width: 420px) {
  .category-create-row {
    flex-direction: column;
  }

  .category-create-row .v-btn {
    width: 100%;
    margin-top: 0 !important;
  }

  .category-row {
    grid-template-columns:
      minmax(0, 1fr)
      auto;
  }

  .category-row .v-btn {
    width: 100%;
  }
}
</style>