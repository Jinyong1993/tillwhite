<template>
  <!--
    공통 알림 메시지

    modelValue에 메시지가 있을 때만 표시한다.
    닫기 버튼을 누르면 modelValue를 빈 문자열로 변경한다.
  -->
  <v-alert
    v-if="modelValue"
    class="mb-4"
    :type="type"
    :variant="variant"
    :closable="closable"
    @click:close="close"
  >
    {{ modelValue }}
  </v-alert>
</template>

<script setup>
/**
 * 공통 알림 컴포넌트 속성
 *
 * modelValue:
 * - 화면에 표시할 알림 메시지
 * - 빈 문자열이면 알림을 표시하지 않는다.
 *
 * type:
 * - Vuetify Alert의 알림 종류
 * - error, success, warning, info 등을 사용할 수 있다.
 *
 * variant:
 * - Vuetify Alert의 표시 스타일
 * - 기본값은 tonal이다.
 *
 * closable:
 * - 사용자가 알림을 직접 닫을 수 있는지 결정한다.
 */
defineProps({
  modelValue: {
    type: String,
    default: '',
  },

  type: {
    type: String,
    default: 'error',
  },

  variant: {
    type: String,
    default: 'tonal',
  },

  closable: {
    type: Boolean,
    default: true,
  },
});

/**
 * 부모 컴포넌트에 전달할 이벤트
 *
 * update:modelValue:
 * - Vue의 v-model과 연결되는 이벤트
 * - 알림을 닫을 때 부모가 관리하는 메시지 값을 변경한다.
 */
const emit = defineEmits([
  'update:modelValue',
]);

/**
 * 알림 닫기
 *
 * modelValue를 빈 문자열로 변경하여
 * 현재 표시 중인 알림을 숨긴다.
 */
function close() {
  emit('update:modelValue', '');
}
</script>