<template>
  <!--
    공통 전체 화면 로딩 오버레이

    서버에서 필요한 데이터를 불러오는 동안
    화면 전체를 완전히 가리고 로딩 상태를 표시합니다.

    Till White 공통 화면 디자인에 맞게
    검정 배경과 흰색 로딩 표시를 사용합니다.

    뒤에 있는 업무 화면이 비치지 않도록
    별도의 전체 화면 배경을 사용합니다.

    persistent를 사용하므로 로딩 중에는
    사용자가 오버레이를 임의로 닫을 수 없습니다.

    modelValue가 false가 되면
    오버레이가 자동으로 사라집니다.
  -->
  <v-overlay
    :model-value="modelValue"
    class="app-loading-overlay"
    persistent
  >
    <!--
      불투명 전체 화면 배경

      Vuetify Overlay의 기본 반투명 배경에 의존하지 않고
      실제 검정 배경을 화면 전체에 표시합니다.

      따라서 로딩 중에는
      뒤의 업무 페이지 내용이 보이지 않습니다.
    -->
    <div class="app-loading-overlay__background">
      <!--
        로딩 상태 표시

        Till White 화면의 검정/흰색 디자인에 맞춰
        흰색 원형 Progress와 안내 문구를 표시합니다.
      -->
      <div class="app-loading-overlay__content">
        <v-progress-circular
          color="white"
          indeterminate
          size="48"
          width="4"
        />

        <div class="text-body-2 text-white">
          로딩 중...
        </div>
      </div>
    </div>
  </v-overlay>
</template>

<script setup>
/**
 * 공통 전체 화면 로딩 오버레이 속성
 *
 * modelValue:
 * - true이면 전체 화면 로딩 오버레이를 표시합니다.
 * - false이면 오버레이를 숨깁니다.
 *
 * 부모 컴포넌트가 실제 로딩 상태를 관리하고,
 * 이 컴포넌트는 전달받은 상태에 따라
 * 로딩 화면을 표시하는 역할만 담당합니다.
 *
 * 최소 로딩 표시 시간 등의 로직은
 * 이 컴포넌트에서 관리하지 않습니다.
 *
 * 실제 페이지 로딩 시간은
 * 공통 레이아웃(AppShell)에서 관리합니다.
 */
defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});
</script>

<style scoped>
/**
 * 공통 로딩 오버레이 자체를
 * 화면 전체 영역에 표시합니다.
 */
.app-loading-overlay {
  z-index: 9999;
}

/**
 * Till White 공통 로딩 배경
 *
 * 화면 전체를 완전한 검정색으로 덮어서
 * 뒤의 업무 페이지가 보이지 않도록 합니다.
 */
.app-loading-overlay__background {
  position: fixed;
  inset: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  width: 100vw;
  height: 100vh;

  background: #000;
}

/**
 * 로딩 표시 영역
 *
 * 원형 Progress와 안내 문구를
 * 화면 중앙에 세로로 배치합니다.
 */
.app-loading-overlay__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  gap: 12px;
}
</style>