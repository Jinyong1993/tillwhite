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
import {
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  watch,
} from 'vue';

import { useRoute } from 'vue-router';

import AppLoadingOverlay from './components/common/AppLoadingOverlay.vue';
import { useAppLoading } from './composables/useAppLoading';

/**
 * 애플리케이션 공통 로딩 상태
 *
 * 기존 기능을 그대로 유지합니다.
 */
const {
  isLoading,
} = useAppLoading();

/**
 * 현재 라우터 정보
 *
 * 메인 업무 바로가기,
 * 햄버거 메뉴,
 * router.push / router.replace 등
 * 공통 화면 이동을 감지합니다.
 */
const route = useRoute();

/**
 * 화면 이동 후 스크롤 초기화가 필요한지 관리합니다.
 *
 * 공통 로딩이 진행 중인 경우
 * 로딩이 끝난 뒤에도 스크롤을 확인합니다.
 */
const pendingScrollReset = ref(false);

/**
 * 컴포넌트가 해제된 뒤 예약된
 * 스크롤 작업을 실행하지 않도록 관리합니다.
 */
let scrollFrameId = null;

/**
 * 실제 스크롤 초기화
 *
 * 브라우저 문서 스크롤과
 * Vuetify의 공통 화면 영역을 초기화합니다.
 *
 * 제품 테이블, 다이얼로그 등
 * 개별 업무 컴포넌트의 내부 스크롤은
 * 임의로 초기화하지 않습니다.
 */
function resetAppScroll() {
  if (typeof window === 'undefined') {
    return;
  }

  // 브라우저 문서 스크롤
  window.scrollTo({
    top: 0,
    left: 0,
    behavior: 'instant',
  });

  // 문서 스크롤 요소
  const scrollingElement = document.scrollingElement;

  if (scrollingElement) {
    scrollingElement.scrollTop = 0;
    scrollingElement.scrollLeft = 0;
  }

  // 브라우저별 스크롤 요소
  document.documentElement.scrollTop = 0;
  document.body.scrollTop = 0;

  /**
   * 공통 레이아웃 영역
   *
   * 별도 overflow 스크롤이 지정되어 있는 경우
   * 해당 영역의 위치도 초기화합니다.
   */
  const layoutElements = document.querySelectorAll(
    '.v-main, .v-application__wrap, .v-application'
  );

  layoutElements.forEach((element) => {
    if (element.scrollTop !== 0) {
      element.scrollTop = 0;
    }

    if (element.scrollLeft !== 0) {
      element.scrollLeft = 0;
    }
  });
}

/**
 * Vue 렌더링 이후 스크롤 초기화
 *
 * 같은 프레임에서 중복 예약되는 작업은 취소하고
 * 마지막 요청만 실행합니다.
 */
async function scheduleScrollReset() {
  await nextTick();

  if (scrollFrameId !== null) {
    cancelAnimationFrame(scrollFrameId);
  }

  scrollFrameId = requestAnimationFrame(() => {
    scrollFrameId = null;
    resetAppScroll();
  });
}

/**
 * 실제 페이지 경로 변경 감지
 *
 * 다른 메뉴로 이동하는 경우에만 적용합니다.
 *
 * 같은 경로에서 날짜나 query가 바뀌는 경우
 * 사용자가 보고 있던 스크롤 위치를 유지합니다.
 */
watch(
  () => route.path,
  (newPath, oldPath) => {
    if (newPath === oldPath) {
      return;
    }

    pendingScrollReset.value = true;

    // 새 화면 렌더링 후 스크롤 초기화
    scheduleScrollReset();
  },
  {
    flush: 'post',
  },
);

/**
 * 공통 로딩 완료 후 스크롤 초기화
 *
 * 페이지 이동 중 로딩 오버레이가
 * 문서의 스크롤을 잠그거나 복원하는 경우를 대비합니다.
 *
 * 경로 이동이 있었을 때만 실행하므로
 * 같은 화면에서 수행하는 일반 업무에는
 * 영향을 주지 않습니다.
 */
watch(
  isLoading,
  (loading) => {
    if (loading || !pendingScrollReset.value) {
      return;
    }

    pendingScrollReset.value = false;

    scheduleScrollReset();
  },
  {
    flush: 'post',
  },
);

/**
 * iOS Safari의 확대 제스처 제한
 *
 * 기존 기능 유지
 */
function preventGesture(event) {
  event.preventDefault();
}

/**
 * 두 손가락 터치로 발생하는 확대 제스처 제한
 *
 * 일반적인 한 손가락 스크롤은 유지합니다.
 */
function preventMultiTouch(event) {
  if (event.touches.length > 1) {
    event.preventDefault();
  }
}

/**
 * 애플리케이션 시작 시
 * iOS 제스처 이벤트 등록
 */
onMounted(() => {
  document.addEventListener(
    'gesturestart',
    preventGesture,
    { passive: false },
  );

  document.addEventListener(
    'gesturechange',
    preventGesture,
    { passive: false },
  );

  document.addEventListener(
    'touchmove',
    preventMultiTouch,
    { passive: false },
  );
});

/**
 * 컴포넌트 해제 시
 * 이벤트와 예약된 스크롤 작업 정리
 */
onUnmounted(() => {
  document.removeEventListener(
    'gesturestart',
    preventGesture,
  );

  document.removeEventListener(
    'gesturechange',
    preventGesture,
  );

  document.removeEventListener(
    'touchmove',
    preventMultiTouch,
  );

  if (scrollFrameId !== null) {
    cancelAnimationFrame(scrollFrameId);
    scrollFrameId = null;
  }
});
</script>

<style>
/*
 * 모바일 입력창 포커스 시
 * Safari 자동 확대 방지
 *
 * 기존 기능 유지
 */
@media (pointer: coarse) {
  input,
  textarea,
  select,
  .v-field__input {
    font-size: 16px;
  }
}

/*
 * 앱 터치 제스처
 *
 * 세로 스크롤 허용
 * 기존 설정 유지
 */
.app-gesture-control {
  touch-action: pan-y;
}
</style>