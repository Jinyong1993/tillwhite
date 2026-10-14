
<template>
  <ReasonFlowTab
    ref="reasonFlowTabRef"
    v-bind="$props"
    type="loss"
    :reason-options="lossReasons"
    @update:model-value="(value) => emit('update:modelValue', value)"
    @saved="(message, daily) => emit('saved', message, daily)"
    @error="(message) => emit('error', message)"
  />
</template>

<script setup>
import { computed, ref } from "vue";
import ReasonFlowTab from "./ReasonFlowTab.vue";

defineProps({
  modelValue: Boolean,
  product: Object,
  storeId: Number,
  workDate: String,
  canMutate: Boolean,
  lossReasons: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits([
  "update:modelValue",
  "saved",
  "error",
]);

const reasonFlowTabRef = ref(null);
const isSaving = computed(() => reasonFlowTabRef.value?.isSaving ?? false);
const editorOpen = computed(() => reasonFlowTabRef.value?.editorOpen ?? false);

// 작성 중인 내용이 있으면 확인창을 표시하고 탭 이동이나 닫기를 처리합니다.
function requestLeave(action) {
  return reasonFlowTabRef.value?.requestLeave(action);
}

// 로스 입력 내용을 검증하고 저장 확인창을 표시합니다.
function askSave() {
  return reasonFlowTabRef.value?.askSave();
}

defineExpose({
  requestLeave,
  askSave,
  isSaving,
  editorOpen,
});
</script>