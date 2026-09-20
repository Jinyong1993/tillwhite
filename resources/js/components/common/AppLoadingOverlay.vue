<template>
  <!--
    공통 전체 화면 로딩 오버레이

    서버에서 필요한 데이터를 불러오는 동안
    사용자가 기존 화면을 조작하지 못하도록
    전체 화면 위에 로딩 상태를 표시한다.

    로딩이 완료되면 자동으로 오버레이가 사라진다.
  -->
  <v-overlay
    :model-value="modelValue"
    class="align-center justify-center"
    persistent
  >
    <!--
      원형 로딩 표시

      indeterminate 상태를 사용하여
      작업이 진행 중임을 사용자에게 표시한다.
    -->
    <div class="d-flex flex-column align-center ga-3">
      <v-progress-circular
        indeterminate
        size="48"
        width="4"
      />

      <div class="text-body-2">
        로딩 중...
      </div>
    </div>
  </v-overlay>
</template>

<script setup>
/**
 * 공통 로딩 오버레이 속성
 *
 * modelValue:
 * true이면 전체 화면 로딩 오버레이를 표시하고
 * false이면 오버레이를 숨긴다.
 */
defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});
</script>