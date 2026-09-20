<template>
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
 * 화면에 표시할 알림 메시지이다.
 * 빈 문자열이면 알림을 표시하지 않는다.
 *
 * type:
 * 알림 종류를 지정한다.
 * error, success, warning, info 등의 Vuetify 타입을 사용할 수 있다.
 *
 * variant:
 * Vuetify Alert의 표시 스타일을 지정한다.
 *
 * closable:
 * 사용자가 알림을 직접 닫을 수 있는지 결정한다.
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
 * v-model을 사용한 메시지 상태 변경을 지원한다.
 */
const emit = defineEmits([
  'update:modelValue',
]);

/**
 * 알림 닫기
 *
 * 알림의 닫기 버튼을 누르면 부모가 가지고 있는
 * 메시지 값을 빈 문자열로 변경하여 알림을 숨긴다.
 */
function close() {
  emit('update:modelValue', '');
}
</script>