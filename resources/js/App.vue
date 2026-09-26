<template>
  <!--
    Till White Vue 애플리케이션의 최상위 레이아웃입니다.

    v-app은 Vuetify 애플리케이션의 최상위 컨테이너이며,
    모든 Vuetify 컴포넌트가 정상적으로 동작하기 위한 기준이 됩니다.

    공통 전체 화면 로딩 오버레이도 이 위치에서 관리합니다.

    App.vue는 Vue Router로 업무 페이지가 변경되어도
    제거되지 않는 최상위 컴포넌트이므로,
    메인 → 직원 관리처럼 화면이 변경되는 동안에도
    로딩 오버레이가 끊기지 않고 유지됩니다.
  -->
  <v-app>
    <!--
      애플리케이션 공통 전체 화면 로딩

      공통 로딩 상태(useAppLoading)가 활성화되어 있으면
      현재 어떤 업무 페이지를 보고 있든
      전체 화면 로딩 오버레이를 표시합니다.

      router-view 내부의 페이지가 교체되어도
      이 컴포넌트는 제거되지 않습니다.
    -->
    <AppLoadingOverlay
      :model-value="isLoading"
    />

    <!-- 애플리케이션의 메인 콘텐츠 영역 -->
    <v-main>
      <!--
        Vue Router에서 현재 URL에 해당하는
        페이지 컴포넌트를 이 위치에 표시합니다.

        페이지가 변경되는 동안에도
        위의 공통 로딩 오버레이는 그대로 유지됩니다.
      -->
      <router-view />
    </v-main>
  </v-app>
</template>

<script setup>
import AppLoadingOverlay from './components/common/AppLoadingOverlay.vue';

import { useAppLoading } from './composables/useAppLoading';

/**
 * Till White 애플리케이션 공통 로딩 상태
 *
 * 특정 업무 페이지에 종속되지 않으므로
 * Vue Router로 페이지가 변경되어도
 * 동일한 로딩 상태가 유지됩니다.
 */
const {
  isLoading,
} = useAppLoading();
</script>