<template>
  <!--
    Till White Vue 애플리케이션의 최상위 레이아웃입니다.

    v-app은 Vuetify 애플리케이션의 최상위 컨테이너이며,
    모든 Vuetify 컴포넌트가 정상적으로 동작하기 위한 기준이 됩니다.
  -->
  <v-app>
    <!-- 애플리케이션의 메인 콘텐츠 영역 -->
    <v-main>
      <!--
        Vue Router에서 현재 URL에 해당하는
        페이지 컴포넌트를 이 위치에 표시합니다.
      -->
      <router-view />
    </v-main>
  </v-app>
</template>