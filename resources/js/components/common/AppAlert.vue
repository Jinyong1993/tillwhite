<template>
  <!--
    Till White 애플리케이션 공통 알림

    성공, 오류, 경고, 안내 등
    애플리케이션에서 사용하는 일반적인 알림을
    하나의 공통 컴포넌트로 표시합니다.

    modelValue에 메시지가 있을 때만 표시하며,
    모든 알림은 사용자가 직접 닫을 수 있습니다.

    알림 닫기 여부를 각 화면에서 따로 설정하지 않고
    이 공통 컴포넌트에서 항상 닫기 가능하도록 관리합니다.
  -->
  <v-alert
    v-if="modelValue"
    class="mb-4"
    :type="type"
    :variant="variant"
    closable
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
 * - 빈 문자열이면 알림을 표시하지 않습니다.
 *
 * type:
 * - 알림 종류
 * - error   = 오류
 * - success = 성공
 * - warning = 경고
 * - info    = 안내
 *
 * variant:
 * - Vuetify Alert의 표시 스타일
 * - 기본값은 tonal입니다.
 *
 * 모든 알림은 반드시 사용자가 닫을 수 있어야 하므로
 * closable은 외부에서 변경할 수 있는 속성으로 두지 않습니다.
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
});

/**
 * 부모 컴포넌트에 전달할 이벤트
 *
 * update:modelValue:
 * - Vue의 v-model과 연결되는 이벤트
 * - 사용자가 알림을 닫으면 부모가 관리하는
 *   현재 알림 메시지를 빈 문자열로 변경합니다.
 */
const emit = defineEmits([
  'update:modelValue',
]);

/**
 * 현재 표시 중인 알림을 닫습니다.
 *
 * 공통 Alert의 닫기 버튼을 누르면
 * 부모의 v-model 값을 빈 문자열로 변경하여
 * 화면에서 알림을 제거합니다.
 */
function close() {
  emit('update:modelValue', '');
}
</script>