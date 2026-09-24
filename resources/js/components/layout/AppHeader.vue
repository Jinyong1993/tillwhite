<template>
  <!--
    Till White 공통 헤더

    로그인 페이지와 동일한 제목 디자인을 유지하면서
    현재 페이지의 이름을 부제목으로 표시한다.

    로그인 이후 화면에서는 오른쪽에 메뉴 버튼을 표시하고,
    버튼 클릭 시 부모 컴포넌트에 menu 이벤트를 전달한다.
  -->
  <div>
    <div class="d-flex align-center">
      <!--
        시스템 이름

        모든 업무 페이지에서 Till White라는
        동일한 시스템 이름과 디자인을 사용한다.

        로그인 페이지의 제목 스타일과 통일하기 위해
        font-weight-black을 적용한다.
      -->
      <v-card-title class="font-weight-black flex-grow-1">
        Till White
      </v-card-title>

      <!--
        사이드 메뉴 버튼

        실제 메뉴를 직접 열지는 않고
        부모 컴포넌트에 menu 이벤트를 전달한다.

        사이드 메뉴의 열림/닫힘 상태는
        상위 레이아웃 컴포넌트에서 관리한다.
      -->
      <v-btn
        class="mr-2"
        icon="mdi-menu"
        variant="text"
        aria-label="메뉴"
        @click="openMenu"
      />
    </div>

    <!--
      현재 페이지 이름

      로그인 페이지에서는 '베이커리 관리 시스템'을 표시하지만,
      로그인 이후 업무 화면에서는 현재 페이지 이름을 표시한다.

      예:
      - 메인
      - 생산·폐기 관리
      - 근무 관리
      - 제품 관리
      - 매출 관리
    -->
    <v-card-subtitle class="mb-3">
      {{ subtitle }}
    </v-card-subtitle>
  </div>
</template>

<script setup>
/**
 * 공통 헤더 속성
 *
 * subtitle:
 * - 현재 사용자가 보고 있는 페이지의 이름
 * - 각 페이지 또는 상위 레이아웃에서 전달한다.
 * - AppHeader를 사용하는 화면에서는 반드시 지정해야 한다.
 */
defineProps({
  subtitle: {
    type: String,
    required: true,
  },
});

/**
 * 부모 컴포넌트로 전달할 이벤트
 *
 * menu:
 * - 사용자가 햄버거 메뉴 버튼을 클릭했음을 전달한다.
 * - 실제 사이드 메뉴 상태 변경은 부모가 처리한다.
 */
const emit = defineEmits([
  'menu',
]);

/**
 * 사이드 메뉴 열기 요청
 *
 * 메뉴 버튼을 클릭하면 부모 컴포넌트에
 * menu 이벤트를 전달한다.
 *
 * AppHeader는 메뉴 상태를 직접 관리하지 않으며,
 * 상위 레이아웃이 이벤트를 받아 사이드 메뉴를 연다.
 */
function openMenu() {
  emit('menu');
}
</script>