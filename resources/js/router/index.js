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

import { useAppLoading } from '../composables/useAppLoading';

/**
 * Till White 단일 페이지 애플리케이션(SPA)에서 사용하는
 * 전체 화면 경로(Route) 목록입니다.
 *
 * 현재 실제 사용하는 기능:
 * - 로그인
 * - 메인
 * - 제품 관리
 * - 생산·폐기 관리
 * - 직원 관리
 *
 * 현재 개발 중인 기능:
 * - 근무 관리
 * - 매출 관리
 * - 점포 관리
 * - 시스템
 * - 감사 로그
 *
 * 개발 중인 페이지도 화면 경로(Route)와
 * 화면 컴포넌트(Component) 자체는 삭제하지 않습니다.
 *
 * 대신 개발 중 상태(developing)를 true로 지정하여
 * 전역 페이지 이동 가드(Navigation Guard)에서 접근을 차단합니다.
 *
 * 로그인 필요 여부(requiresAuth):
 * - true = 로그인이 필요한 업무 화면
 *
 * 개발 중 상태(developing):
 * - true = 현재 개발 중인 화면
 * - 메뉴뿐만 아니라 URL 직접 입력으로도 접근할 수 없음
 *
 * 필요한 권한(requiredPermission):
 * - 해당 화면에 접근하기 위해 필요한 권한(Permission) 코드
 *
 * 권한 없음 메시지(permissionDeniedMessage):
 * - 필요한 권한이 없는 사용자가 접근했을 때
 *   메인 화면에서 표시할 안내 메시지
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
   * 로그인 필요 여부(requiresAuth)를 지정하지 않습니다.
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
   * 직원 관리
   *
   * 현재 사용 중인 기능입니다.
   *
   * 직원 개인정보가 포함될 수 있으므로
   * 직원 조회 권한(employee.view)을 가진 사용자만 접근할 수 있습니다.
   *
   * 현재 권한 구조:
   * - 일반 직원(staff) = 접근 불가
   * - 주방 헤드 셰프(kitchen_head) = 접근 불가
   * - 홀 매니저(hall_manager) = 접근 불가
   * - 본사 직원(head_office_staff) = 조회 가능
   * - 본사 관리자(head_office_manager) = 조회 및 관리 가능
   * - 최고 관리자(super_admin) = 조회 및 관리 가능
   *
   * 직원 등록 및 상태 변경 같은 관리 작업은
   * 별도의 직원 관리 권한(employee.manage)을
   * Laravel 서버에서 다시 검사합니다.
   */
  {
    path: '/tillwhite/employees',
    name: 'employees',
    component: EmployeePage,
    meta: {
      requiresAuth: true,
      requiredPermission: 'employee.view',
      permissionDeniedMessage:
        '직원 정보를 열람할 권한이 없습니다.',
    },
  },

  /**
   * 근무 관리
   *
   * 현재 개발 중인 기능이므로
   * URL을 직접 입력해도 접근할 수 없습니다.
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
   * URL을 직접 입력해도 접근할 수 없습니다.
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
   * 점포 관리
   *
   * 현재 개발 중인 기능이므로
   * URL을 직접 입력해도 접근할 수 없습니다.
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
   * URL을 직접 입력해도 접근할 수 없습니다.
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
   * URL을 직접 입력해도 접근할 수 없습니다.
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
 * 일반 주소 방식(createWebHistory)을 사용하므로
 * URL에 #이 붙지 않습니다.
 */
const router = createRouter({
  history: createWebHistory(),
  routes,
});

/**
 * Till White 애플리케이션 공통 전체 화면 로딩입니다.
 *
 * 화면 이동 로딩의 시작은
 * Vue Router가 공통으로 담당합니다.
 *
 * 따라서 다음과 같은 모든 화면 이동이
 * 같은 로딩 구조를 사용합니다.
 *
 * - 왼쪽 내비게이션 메뉴
 * - 메인 화면 빠른 메뉴
 * - router.push()
 * - router.replace()
 * - 주소 직접 이동
 * - 권한 검사 후 리다이렉트
 *
 * beginNavigationLoading:
 * - 로그인된 업무 화면으로 이동하기 시작할 때 로딩 시작
 *
 * cancelLoading:
 * - 로그인 화면으로 이동하거나
 *   정상적인 업무 페이지 완료 처리를 할 수 없는 경우 로딩 취소
 *
 * 실제 로딩 종료는 목적지 업무 페이지가
 * 자신의 최초 데이터 준비를 완료한 뒤
 * completePageLoading()으로 처리합니다.
 */
const {
  beginNavigationLoading,
  cancelLoading,
} = useAppLoading();

/**
 * 페이지 이동 전에 실행되는
 * 전역 페이지 이동 가드(Navigation Guard)입니다.
 *
 * 처리 순서:
 *
 * 1. 업무 화면으로 이동한다면 공통 로딩 시작
 * 2. 개발 중인 페이지인지 확인
 * 3. 개발 중이면 메인 화면으로 이동
 * 4. 로그인이 필요하지 않은 페이지라면 접근 허용
 * 5. 로그인이 필요한 페이지라면 Laravel 세션 확인
 * 6. 로그인되지 않았다면 로그인 화면으로 이동
 * 7. 서버 응답에서 실제 사용자 정보(user)를 추출
 * 8. 화면에 필요한 권한(Permission)이 있는지 확인
 * 9. 권한이 없다면 메인 화면으로 이동하면서 안내 메시지 전달
 * 10. 모든 검사를 통과하면 요청한 화면 접근 허용
 *
 * 프론트엔드의 권한 검사는
 * 사용자가 접근할 수 없는 화면을 보여주지 않기 위한
 * 사용자 화면(UX) 처리입니다.
 *
 * 실제 데이터 보안은 Laravel 서버에서
 * 권한(Permission)과 데이터 조회 범위(Scope)를
 * 다시 검사하여 처리합니다.
 *
 * 공통 로딩 시작을 Router 한 곳에서 담당하므로
 * 개별 메뉴나 버튼에서 로딩을 직접 시작할 필요가 없습니다.
 */
router.beforeEach(async (to) => {
  /**
   * 로그인된 사용자가 사용하는 업무 화면으로 이동한다면
   * 가장 먼저 공통 전체 화면 로딩을 시작합니다.
   *
   * 권한 검사나 Laravel 세션 확인보다 먼저 실행하므로
   * 기존 화면이 잠깐 보이거나
   * 목적지 화면이 먼저 노출되는 현상을 줄일 수 있습니다.
   *
   * 리다이렉트 때문에 Router 가드가 다시 실행되더라도
   * beginNavigationLoading() 내부에서
   * 이미 로딩 중인지 확인하므로 시작 시간이 초기화되지 않습니다.
   */
  if (to.meta.requiresAuth) {
    beginNavigationLoading();
  }

  /**
   * 개발 중인 페이지 접근 차단
   *
   * 개발 중인 화면은 주소를 직접 입력하더라도
   * 실제 화면을 표시하지 않고 메인 화면으로 이동합니다.
   *
   * 위에서 이미 공통 로딩을 시작했으므로
   * 개발 중인 화면 자체는 노출되지 않습니다.
   *
   * 메인 화면으로 리다이렉트된 뒤
   * MainPage가 자신의 최초 데이터 준비를 완료하면
   * 같은 공통 로딩을 종료합니다.
   */
  if (to.meta.developing) {
    return {
      name: 'main',
    };
  }

  /**
   * 로그인이 필요하지 않은 페이지입니다.
   *
   * 대표적으로 로그인 화면이 해당합니다.
   *
   * 로그인 화면은 업무 페이지의
   * completePageLoading()을 호출하지 않으므로
   * 이전 화면에서 남아 있는 공통 로딩을 즉시 정리합니다.
   */
  if (!to.meta.requiresAuth) {
    cancelLoading();

    return true;
  }

  try {
    /**
     * Laravel 세션을 기준으로
     * 현재 로그인 사용자 정보를 조회합니다.
     *
     * 프론트엔드에 저장된 사용자 정보만 신뢰하지 않고
     * 서버의 실제 로그인 세션을 기준으로 확인합니다.
     *
     * 이 요청이 진행되는 동안에도
     * App.vue의 공통 전체 화면 로딩은 계속 표시됩니다.
     */
    const response = await fetch(
      '/tillwhite/auth/me',
      {
        headers: {
          Accept: 'application/json',
        },
      },
    );

    /**
     * 로그인 세션이 유효하지 않은 경우
     * 로그인 화면으로 이동합니다.
     *
     * 로그인 화면에서는
     * completePageLoading()을 호출하지 않으므로
     * 현재 공통 로딩을 먼저 취소합니다.
     */
    if (!response.ok) {
      cancelLoading();

      return {
        name: 'login',
      };
    }

    /**
     * /tillwhite/auth/me의 실제 응답 구조:
     *
     * {
     *   message: '로그인 상태입니다.',
     *   user: {
     *     id: ...,
     *     employee_code: ...,
     *     permissions: [...]
     *   }
     * }
     *
     * 따라서 응답 전체(responseData)가 아니라
     * responseData.user를 실제 로그인 사용자로 사용합니다.
     */
    const responseData = await response.json();
    const user = responseData.user;

    /**
     * 현재 화면에 필요한 권한(Permission)이 지정되어 있다면
     * 로그인 사용자가 해당 권한을 가지고 있는지 확인합니다.
     *
     * 예:
     * 직원 관리 화면
     * → 직원 조회 권한(employee.view) 필요
     */
    if (to.meta.requiredPermission) {
      const permissionCodes = Array.isArray(user?.permissions)
        ? user.permissions
        : [];

      /**
       * 필요한 권한을 가지고 있지 않은 경우
       * 접근하려던 화면을 표시하지 않고 메인으로 이동합니다.
       *
       * 여기에서는 공통 로딩을 종료하지 않습니다.
       *
       * 메인 화면으로 리다이렉트되어
       * 실제 메인 데이터 준비가 끝날 때까지
       * 같은 공통 로딩을 계속 유지합니다.
       *
       * 메인으로 리다이렉트되면서
       * Router 가드가 다시 실행되더라도
       * beginNavigationLoading()은 이미 진행 중인 로딩을
       * 다시 시작하지 않습니다.
       *
       * query의 accessDenied 값에는
       * 메인 화면에서 표시할 안내 메시지를 전달합니다.
       */
      if (!permissionCodes.includes(to.meta.requiredPermission)) {
        return {
          name: 'main',
          query: {
            accessDenied:
              to.meta.permissionDeniedMessage
              ?? '해당 화면을 이용할 권한이 없습니다.',
          },
        };
      }
    }

    // 모든 인증 및 권한 검사를 통과했으므로 접근 허용
    return true;
  } catch {
    /**
     * 네트워크 오류 등으로
     * 로그인 상태 확인 요청 자체가 실패한 경우
     * 보호된 화면을 표시하지 않고 로그인 화면으로 이동합니다.
     *
     * 로그인 화면에서는 업무 페이지 공통 로딩 완료 처리를
     * 실행하지 않으므로 현재 공통 로딩을 즉시 정리합니다.
     */
    cancelLoading();

    return {
      name: 'login',
    };
  }
});

export default router;