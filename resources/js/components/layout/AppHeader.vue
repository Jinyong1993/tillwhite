<template>
  <!--
    Till White 공통 헤더

    로그인 이후 모든 업무 화면에서 사용하는 공통 헤더입니다.

    시스템 이름인 'Till White'를 클릭하면
    언제든지 메인 화면으로 이동할 수 있습니다.

    오른쪽 햄버거 메뉴 버튼을 클릭하면
    부모 컴포넌트에 menu 이벤트를 전달하여
    사이드 내비게이션 메뉴를 엽니다.
  -->
  <div>
    <div class="d-flex align-center">
      <!--
        시스템 이름 / 메인 이동

        모든 업무 페이지에서 동일한
        Till White 시스템 이름을 표시합니다.

        제목을 클릭하면 Vue Router를 통해
        /tillwhite/main으로 이동합니다.

        로그인 페이지의 제목 디자인과 통일하기 위해
        font-weight-black 스타일을 유지합니다.
      -->
      <v-card-title class="font-weight-black flex-grow-1">
        <RouterLink
          to="/tillwhite/main"
          class="text-decoration-none text-high-emphasis"
          aria-label="Till White 메인으로 이동"
        >
          Till White
        </RouterLink>
      </v-card-title>

      <!--
        사이드 메뉴 버튼

        실제 메뉴를 직접 열지는 않고
        부모 컴포넌트에 menu 이벤트를 전달합니다.

        사이드 메뉴의 열림/닫힘 상태는
        상위 레이아웃 컴포넌트에서 관리합니다.
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
      로그인 이후 업무 화면에서는 현재 페이지 이름을 표시합니다.

      예:
      - 메인
      - 제품 관리
      - 생산·폐기 관리
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
 * - 각 페이지 또는 상위 레이아웃에서 전달합니다.
 * - AppHeader를 사용하는 화면에서는 반드시 지정해야 합니다.
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
 * - 사용자가 햄버거 메뉴 버튼을 클릭했음을 전달합니다.
 * - 실제 사이드 메뉴 상태 변경은 부모가 처리합니다.
 */
const emit = defineEmits([
  'menu',
]);

/**
 * 사이드 메뉴 열기 요청
 *
 * 메뉴 버튼을 클릭하면 부모 컴포넌트에
 * menu 이벤트를 전달합니다.
 *
 * AppHeader는 메뉴 상태를 직접 관리하지 않으며,
 * 상위 레이아웃이 이벤트를 받아 사이드 메뉴를 엽니다.
 */
function openMenu() {
  emit('menu');
}
</script>