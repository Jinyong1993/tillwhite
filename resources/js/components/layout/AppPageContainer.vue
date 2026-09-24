<template>
  <!--
    로그인 이후 업무 페이지에서 공통으로 사용하는 컨테이너입니다.
    페이지의 최대 너비, 가운데 정렬, 기본 여백을 관리합니다.
  -->
  <v-container
    class="app-page-container"
  >
    <!-- 각 페이지의 실제 내용을 표시하는 영역 -->
    <slot />
  </v-container>
</template>

<style scoped>
/**
 * 업무 페이지 공통 레이아웃
 *
 * width     : 사용 가능한 너비를 모두 사용
 * max-width : 화면이 넓어져도 콘텐츠 영역을 최대 1200px로 제한
 * margin    : 콘텐츠를 화면 가운데 정렬
 * padding   : 화면 가장자리와 콘텐츠 사이에 16px 여백 적용
 */
.app-page-container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 16px;
}
</style>