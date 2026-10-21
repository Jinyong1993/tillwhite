<template>
  <!-- Till White 최상위 애플리케이션 -->
  <v-app class="app-gesture-control">
    <!-- 공통 전체 화면 로딩 -->
    <AppLoadingOverlay
      :model-value="isLoading"
    />

    <!-- 애플리케이션 메인 콘텐츠 -->
    <v-main>
      <router-view />
    </v-main>
  </v-app>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

import AppLoadingOverlay from './components/common/AppLoadingOverlay.vue';
import { useAppLoading } from './composables/useAppLoading';

/**
 * 애플리케이션 공통 로딩 상태
 */
const {
  isLoading,
} = useAppLoading();

/**
 * iOS Safari의 확대 제스처 제한
 *
 * 두 손가락 확대·축소 동작을 제한합니다.
 * 일반적인 한 손가락 스크롤과 클릭은 유지합니다.
 */
function preventGesture(event) {
  event.preventDefault();
}

/**
 * 두 손가락 터치로 발생하는 확대 제스처 제한
 */
function preventMultiTouch(event) {
  if (event.touches.length > 1) {
    event.preventDefault();
  }
}

onMounted(() => {
  document.addEventListener(
    'gesturestart',
    preventGesture,
    { passive: false }
  );

  document.addEventListener(
    'gesturechange',
    preventGesture,
    { passive: false }
  );

  document.addEventListener(
    'touchmove',
    preventMultiTouch,
    { passive: false }
  );
});

onUnmounted(() => {
  document.removeEventListener(
    'gesturestart',
    preventGesture
  );

  document.removeEventListener(
    'gesturechange',
    preventGesture
  );

  document.removeEventListener(
    'touchmove',
    preventMultiTouch
  );
});
</script>

<style>
/* 모바일 입력창 포커스 시 자동 확대 방지 */
@media (pointer: coarse) {
  input,
  textarea,
  select,
  .v-field__input {
    font-size: 16px;
  }
}

/* 앱 터치 제스처 */
.app-gesture-control {
  touch-action: pan-y;
}
</style>