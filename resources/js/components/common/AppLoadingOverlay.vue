<template>
  <!--
    공통 전체 화면 로딩 오버레이

    서버에서 필요한 데이터를 불러오는 동안
    화면 전체에 로딩 상태를 표시한다.

    persistent를 사용하므로 로딩 중에는
    사용자가 오버레이를 임의로 닫을 수 없다.

    modelValue가 false가 되면
    오버레이가 자동으로 사라진다.
  -->
  <v-overlay
    :model-value="modelValue"
    class="align-center justify-center"
    persistent
  >
    <!--
      로딩 상태 표시

      원형 Progress와 안내 문구를 함께 표시하여
      현재 작업이 진행 중임을 사용자에게 알려준다.
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
 * 공통 전체 화면 로딩 오버레이 속성
 *
 * modelValue:
 * - true이면 전체 화면 로딩 오버레이를 표시한다.
 * - false이면 오버레이를 숨긴다.
 *
 * 부모 컴포넌트가 실제 로딩 상태를 관리하고,
 * 이 컴포넌트는 전달받은 상태에 따라
 * 로딩 화면을 표시하는 역할만 담당한다.
 */
defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});
</script>