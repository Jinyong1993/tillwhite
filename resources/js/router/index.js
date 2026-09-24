import {
  createRouter,
  createWebHistory,
} from 'vue-router';

import LoginPage from '../pages/Auth/LoginPage.vue';
import MainPage from '../pages/MainPage.vue';
import ProductionPage from '../pages/Production/ProductionPage.vue';
import WorkPage from '../pages/Work/WorkPage.vue';
import ProductPage from '../pages/Product/ProductPage.vue';
import SalesPage from '../pages/Sales/SalesPage.vue';
import EmployeePage from '../pages/Employee/EmployeePage.vue';
import StorePage from '../pages/Store/StorePage.vue';
import SystemPage from '../pages/System/SystemPage.vue';
import AuditPage from '../pages/System/AuditPage.vue';

/**
 * Till White SPA에서 사용하는 라우트 목록입니다.
 *
 * 로그인 화면을 제외한 모든 업무 화면은
 * Laravel 세션 인증이 필요합니다.
 *
 * requiresAuth가 true인 페이지는
 * router.beforeEach()에서 로그인 여부를 확인합니다.
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
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/tillwhite/production',
    component: ProductionPage,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/tillwhite/work',
    component: WorkPage,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/tillwhite/products',
    component: ProductPage,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/tillwhite/sales',
    component: SalesPage,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/tillwhite/employees',
    component: EmployeePage,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/tillwhite/stores',
    component: StorePage,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/tillwhite/system',
    component: SystemPage,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/tillwhite/audits',
    component: AuditPage,
    meta: {
      requiresAuth: true,
    },
  },
];

/**
 * Vue Router를 생성합니다.
 *
 * createWebHistory()를 사용하므로
 * URL에 #이 붙지 않는 일반적인 History 방식으로 동작합니다.
 */
const router = createRouter({
  history: createWebHistory(),
  routes,
});

/**
 * 페이지 이동 전에 실행되는 전역 Navigation Guard입니다.
 *
 * requiresAuth가 없는 페이지는 바로 접근을 허용합니다.
 *
 * requiresAuth가 true인 페이지는 Laravel의 /auth/me API를 호출하여
 * 현재 세션에 로그인된 사용자가 있는지 확인합니다.
 *
 * 인증 확인 성공 : 요청한 페이지로 이동
 * 인증 확인 실패 : 로그인 페이지로 이동
 * 요청 중 오류   : 로그인 페이지로 이동
 */
router.beforeEach(async (to) => {
  // 인증이 필요하지 않은 페이지는 바로 접근 허용
  if (!to.meta.requiresAuth) {
    return true;
  }

  try {
    // Laravel 세션을 기준으로 현재 로그인 상태 확인
    const response = await fetch(
      '/tillwhite/auth/me',
      {
        headers: {
          Accept: 'application/json',
        },
      },
    );

    // 인증 성공 시 원래 요청한 페이지로 이동
    if (response.ok) {
      return true;
    }

    // 인증되지 않은 경우 로그인 페이지로 이동
    return {
      name: 'login',
    };
  } catch {
    // 인증 확인 요청 자체가 실패한 경우에도 로그인 페이지로 이동
    return {
      name: 'login',
    };
  }
});

export default router;