<template>
  <div class="d-flex align-center justify-space-between">
    <!--
      Till White 헤더 영역

      시스템 이름과 현재 시스템의 용도를 표시한다.
    -->
    <div>
      <div class="text-h6 font-weight-black">
        Till White
      </div>

      <div class="text-caption text-medium-emphasis">
        베이커리 관리 시스템
      </div>
    </div>

    <!--
      메뉴 버튼

      클릭하면 부모 컴포넌트인 MainPage로
      menu 이벤트를 전달하여 사이드 메뉴를 연다.
    -->
    <v-btn
      icon="mdi-menu"
      variant="text"
      aria-label="메뉴"
      @click="openMenu"
    />
  </div>
</template>

<script setup>
/**
 * AppHeader에서 부모 컴포넌트로 전달하는 이벤트
 *
 * menu:
 * 햄버거 메뉴가 클릭되었음을 부모 컴포넌트에 전달한다.
 *
 * error:
 * 헤더 내부에서 발생한 오류 메시지를 부모 컴포넌트에 전달한다.
 */
const emit = defineEmits([
  'menu',
  'error',
]);

/**
 * 사이드 메뉴 열기
 *
 * MainPage의 drawer 상태를 true로 변경하도록
 * 부모 컴포넌트에 menu 이벤트를 전달한다.
 */
function openMenu() {
  emit('menu');
}
</script>