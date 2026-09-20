<template>
  <!--
    공통 헤더

    로그인 페이지와 동일한 제목 디자인을 유지하면서
    현재 페이지의 이름을 부제목으로 표시한다.

    로그인 이후 화면에서는 오른쪽에 햄버거 메뉴 버튼을 표시하여
    공통 사이드 메뉴를 열 수 있도록 한다.
  -->
  <div>
    <div class="d-flex align-center">
      <!--
        시스템 이름

        로그인 페이지의 v-card-title과 동일한 스타일을 사용하여
        페이지별로 헤더 디자인이 달라지지 않도록 한다.
      -->
      <v-card-title
        class="font-weight-black flex-grow-1"
      >
        Till White
      </v-card-title>

      <!--
        메뉴 버튼

        클릭하면 부모 컴포넌트로 menu 이벤트를 전달하여
        사이드 메뉴를 열도록 한다.
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

      로그인 이후에는 '베이커리 관리 시스템' 대신
      현재 사용자가 보고 있는 페이지의 이름을 표시한다.
    -->
    <v-card-subtitle
      class="mb-3"
    >
      {{ subtitle }}
    </v-card-subtitle>
  </div>
</template>

<script setup>
/**
 * 공통 헤더 속성
 *
 * subtitle:
 * 현재 페이지의 이름을 표시한다.
 *
 * 각 페이지에서 '메인', '생산·폐기 입력',
 * '생산·폐기 조회' 등의 이름을 전달하여 사용한다.
 */
defineProps({
  subtitle: {
    type: String,
    required: true,
  },
});

/**
 * AppHeader에서 부모 컴포넌트로 전달하는 이벤트
 *
 * menu:
 * 햄버거 메뉴가 클릭되었음을 부모 컴포넌트에 전달한다.
 */
const emit = defineEmits([
  'menu',
]);

/**
 * 사이드 메뉴 열기
 *
 * 햄버거 메뉴 버튼을 클릭하면 부모 컴포넌트에
 * menu 이벤트를 전달한다.
 *
 * 부모 컴포넌트는 전달받은 이벤트를 통해
 * 사이드 메뉴의 열림 상태를 변경한다.
 */
function openMenu() {
  emit('menu');
}
</script>