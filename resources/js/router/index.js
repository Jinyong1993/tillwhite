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
 * Till White SPA에서 사용하는 전체 라우트 목록입니다.
 *
 * 현재 실제 사용하는 기능:
 * - 로그인
 * - 메인
 * - 제품 관리
 * - 생산·폐기 관리
 *
 * 현재 개발 중인 기능:
 * - 근무 관리
 * - 매출 관리
 * - 직원 관리
 * - 점포 관리
 * - 시스템
 * - 감사 로그
 *
 * 개발 중인 페이지도 라우트와 컴포넌트 자체는 삭제하지 않습니다.
 * 대신 meta.developing 값을 true로 지정하여
 * 전역 Navigation Guard에서 접근을 차단합니다.
 *
 * 이렇게 구성하면 나중에 해당 기능의 개발이 완료되었을 때
 * developing 설정만 제거하면 다시 사용할 수 있습니다.
 *
 * meta.requiresAuth:
 * - true이면 로그인한 사용자만 접근 가능
 *
 * meta.developing:
 * - true이면 현재 개발 중인 페이지
 * - 메뉴 클릭뿐만 아니라 URL 직접 입력으로도 접근할 수 없음
 */
const routes = [
  /**
   * Till White 기본 주소
   *
   * /tillwhite로 접근하면 로그인 화면으로 이동합니다.
   */
  {
    path: '/tillwhite',
    redirect: '/tillwhite/login',
  },

  /**
   * 로그인
   *
   * 로그인 전에도 접근할 수 있으므로
   * requiresAuth를 사용하지 않습니다.
   */
  {
    path: '/tillwhite/login',
    name: 'login',
    component: LoginPage,
  },

  /**
   * 메인
   *
   * 로그인 이후 사용하는 기본 화면입니다.
   */
  {
    path: '/tillwhite/main',
    name: 'main',
    component: MainPage,
    meta: {
      requiresAuth: true,
    },
  },

  /**
   * 제품 관리
   *
   * 현재 사용 중인 기능입니다.
   */
  {
    path: '/tillwhite/products',
    component: ProductPage,
    meta: {
      requiresAuth: true,
    },
  },

  /**
   * 생산·폐기 관리
   *
   * 현재 사용 중인 기능입니다.
   */
  {
    path: '/tillwhite/production',
    component: ProductionPage,
    meta: {
      requiresAuth: true,
    },
  },

  /**
   * 근무 관리
   *
   * 현재 개발 중인 기능이므로
   * 직접 URL을 입력해도 접근할 수 없습니다.
   */
  {
    path: '/tillwhite/work',
    component: WorkPage,
    meta: {
      requiresAuth: true,
      developing: true,
    },
  },

  /**
   * 매출 관리
   *
   * 현재 개발 중인 기능이므로
   * 직접 URL을 입력해도 접근할 수 없습니다.
   */
  {
    path: '/tillwhite/sales',
    component: SalesPage,
    meta: {
      requiresAuth: true,
      developing: true,
    },
  },

  /**
   * 직원 관리
   *
   * 현재 개발 중인 기능이므로
   * 직접 URL을 입력해도 접근할 수 없습니다.
   */
  {
    path: '/tillwhite/employees',
    component: EmployeePage,
    meta: {
      requiresAuth: true,
      developing: true,
    },
  },

  /**
   * 점포 관리
   *
   * 현재 개발 중인 기능이므로
   * 직접 URL을 입력해도 접근할 수 없습니다.
   */
  {
    path: '/tillwhite/stores',
    component: StorePage,
    meta: {
      requiresAuth: true,
      developing: true,
    },
  },

  /**
   * 시스템
   *
   * 현재 개발 중인 기능이므로
   * 직접 URL을 입력해도 접근할 수 없습니다.
   */
  {
    path: '/tillwhite/system',
    component: SystemPage,
    meta: {
      requiresAuth: true,
      developing: true,
    },
  },

  /**
   * 감사 로그
   *
   * 현재 개발 중인 기능이므로
   * 직접 URL을 입력해도 접근할 수 없습니다.
   */
  {
    path: '/tillwhite/audits',
    component: AuditPage,
    meta: {
      requiresAuth: true,
      developing: true,
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
 * 처리 순서:
 *
 * 1. 개발 중인 페이지인지 확인
 * 2. 개발 중이면 메인 화면으로 이동
 * 3. 인증이 필요하지 않은 페이지라면 접근 허용
 * 4. 인증이 필요한 페이지라면 Laravel 세션 확인
 * 5. 로그인 상태이면 접근 허용
 * 6. 로그인 상태가 아니면 로그인 화면으로 이동
 *
 * 개발 중 페이지 접근 차단을 인증 확인보다 먼저 처리하여
 * 주소창에 URL을 직접 입력하더라도 해당 페이지를 열 수 없게 합니다.
 */
router.beforeEach(async (to) => {
  /**
   * 개발 중인 페이지 접근 차단
   *
   * 예:
   * /tillwhite/work
   * /tillwhite/sales
   * /tillwhite/employees
   * /tillwhite/stores
   * /tillwhite/system
   * /tillwhite/audits
   *
   * 위 주소를 직접 입력하더라도
   * 실제 페이지를 표시하지 않고 메인 화면으로 이동합니다.
   */
  if (to.meta.developing) {
    return {
      name: 'main',
    };
  }

  // 인증이 필요하지 않은 페이지는 바로 접근 허용
  if (!to.meta.requiresAuth) {
    return true;
  }

  try {
    /**
     * Laravel 세션을 기준으로 현재 로그인 상태를 확인합니다.
     *
     * 프론트엔드에 저장된 사용자 정보만 신뢰하지 않고
     * 서버의 실제 로그인 세션을 확인합니다.
     */
    const response = await fetch(
      '/tillwhite/auth/me',
      {
        headers: {
          Accept: 'application/json',
        },
      },
    );

    // 인증된 사용자라면 요청한 페이지 접근 허용
    if (response.ok) {
      return true;
    }

    // 인증되지 않은 사용자는 로그인 화면으로 이동
    return {
      name: 'login',
    };
  } catch {
    /**
     * 네트워크 오류 등으로 인증 확인 요청 자체가 실패한 경우에도
     * 보호된 페이지를 보여주지 않고 로그인 화면으로 이동합니다.
     */
    return {
      name: 'login',
    };
  }
});

export default router;