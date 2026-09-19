import { createRouter, createWebHistory } from 'vue-router';

import LoginPage from '../pages/Auth/LoginPage.vue';
import MainPage from '../pages/MainPage.vue';

/**
 * 애플리케이션 라우트 정의
 *
 * Vue에서 사용하는 페이지 URL과 컴포넌트를 연결한다.
 *
 * 로그인이 필요한 페이지에는 requiresAuth를 지정하고,
 * 로그인한 사용자가 접근할 필요가 없는 페이지에는
 * guestOnly를 지정하여 전역 라우터 가드에서 처리한다.
 */
const routes = [
  {
    path: '/tillwhite',
    redirect: '/tillwhite/login',
  },
  {
    path: '/tillwhite/login',
    name: 'login',
    component: LoginPage,
  },
  {
    path: '/tillwhite/main',
    name: 'main',
    component: MainPage,

    /**
     * 로그인 필요 페이지
     *
     * Laravel 세션에서 정상적인 로그인 상태가
     * 확인된 사용자만 접근할 수 있다.
     */
    meta: {
      requiresAuth: true,
    },
  },
];

/**
 * Vue Router 생성
 *
 * createWebHistory()를 사용하여
 * #이 없는 일반적인 URL 형식을 사용한다.
 */
const router = createRouter({
  history: createWebHistory(),
  routes,
});

/**
 * 현재 로그인 상태 확인
 *
 * Laravel의 /tillwhite/auth/me API를 호출하여
 * 현재 브라우저 세션에 정상적인 로그인 정보가 있는지 확인한다.
 *
 * 서버가 정상 응답을 반환하면 true,
 * 로그인되어 있지 않거나 계정을 사용할 수 없으면 false를 반환한다.
 */
async function isAuthenticated() {
  try {
    const response = await fetch('/tillwhite/auth/me', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
    });

    return response.ok;
  } catch (error) {
    /**
     * 서버 연결 오류 등으로 로그인 상태를
     * 정상적으로 확인할 수 없는 경우이다.
     *
     * 인증 상태를 확인할 수 없는 상황에서는
     * 로그인된 것으로 판단하지 않는다.
     */
    console.error('로그인 상태 확인 중 오류가 발생했습니다.', error);

    return false;
  }
}

/**
 * 전역 라우터 가드
 *
 * Vue Router가 페이지를 이동하기 전에 실행되며
 * 각 페이지의 meta 설정을 기준으로 접근 가능 여부를 판단한다.
 *
 * 실제 인증 여부는 Vue의 변수만 신뢰하지 않고
 * Laravel 세션을 기준으로 확인한다.
 */
router.beforeEach(async (to) => {
  /**
   * 로그인이 필요한 페이지 처리
   *
   * requiresAuth가 true인 페이지에 접근할 경우
   * Laravel 세션에서 현재 로그인 상태를 확인한다.
   */
  if (to.meta.requiresAuth) {
    const authenticated = await isAuthenticated();

    /**
     * 로그인되어 있지 않은 경우
     *
     * 보호된 페이지 접근을 허용하지 않고
     * 로그인 페이지로 이동시킨다.
     */
    if (!authenticated) {
      return {
        name: 'login',
      };
    }

    // 정상 로그인 상태이면 요청한 페이지로 이동한다.
    return true;
  }

  /**
   * 별도의 인증 조건이 없는 일반 페이지
   *
   * requiresAuth와 guestOnly가 모두 없다면
   * 추가적인 인증 검사 없이 이동을 허용한다.
   */
  return true;
});

export default router;