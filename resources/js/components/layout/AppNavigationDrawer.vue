<template>
  <!--
    로그인 이후 사용하는 공통 내비게이션 메뉴입니다.

    PC와 모바일 모두 임시 내비게이션 메뉴(temporary Drawer) 형태로 표시하며,
    현재 사용 가능한 기능과 개발 중인 기능을 함께 표시합니다.

    직원 관리 메뉴는 직원 개인정보와 관련된 기능이므로
    권한이 없는 사용자에게도 메뉴의 존재는 보여주되
    '권한 없음' 상태를 명확하게 표시합니다.
  -->
  <v-navigation-drawer
    v-model="drawer"
    location="left"
    width="300"
    temporary
  >
    <v-list
      density="comfortable"
      nav
    >
      <!--
        시스템 이름 / 메인 이동

        Drawer 상단의 Till White를 클릭하면
        Vue Router를 통해 메인 화면으로 이동합니다.

        업무 화면 이동용 공통 로딩 시작은
        Vue Router 전역 페이지 이동 가드에서 처리합니다.
      -->
      <v-list-subheader
        class="font-weight-black cursor-pointer"
        @click="goToMain"
      >
        Till White
      </v-list-subheader>

      <!--
        현재 사용자에게 표시할 메뉴 목록입니다.

        일반 메뉴:
        - 필요한 권한(Permission)이 있으면 표시
        - 클릭하면 Vue Router로 해당 화면 이동
        - 공통 전체 화면 로딩은 Router에서 자동 시작

        개발 중 메뉴:
        - 필요한 권한이 있으면 표시
        - 오른쪽에 '개발 중' 표시
        - 클릭 불가

        직원 관리 메뉴:
        - 직원 조회 권한(employee.view)과 관계없이 항상 표시
        - 권한이 있으면 정상적으로 이용 가능
        - 권한이 없으면 오른쪽에 '권한 없음' 표시
        - 권한이 없어도 클릭 자체는 가능
        - 클릭 후 Vue Router에서 권한을 검사
        - 권한이 없으면 메인으로 이동하면서 안내 메시지 표시

        자동 라우팅(:to)은 사용하지 않습니다.

        Drawer에서는 화면 이동 자체만 요청하며
        업무 화면 이동용 공통 로딩은 직접 제어하지 않습니다.

        공통 로딩의 시작은 Vue Router,
        종료는 목적지 업무 페이지가 담당합니다.
      -->
      <template
        v-for="item in visibleItems"
        :key="item.title"
      >
        <v-list-item
          :prepend-icon="item.icon"
          :title="item.title"
          :disabled="item.developing"
          @click="handleMenuClick(item)"
        >
          <!--
            메뉴 오른쪽 상태 표시

            개발 중:
            - 아직 사용할 수 없는 기능

            권한 없음:
            - 기능은 사용 중이지만
              현재 사용자에게 필요한 권한이 없는 기능
          -->
          <template
            v-if="item.developing || hasNoPermission(item)"
            #append
          >
            <v-chip
              size="x-small"
              variant="tonal"
            >
              {{ item.developing ? '개발 중' : '권한 없음' }}
            </v-chip>
          </template>
        </v-list-item>
      </template>

      <!-- 일반 메뉴와 로그아웃 메뉴 구분 -->
      <v-divider class="my-2" />

      <!-- 로그아웃 -->
      <v-list-item
        prepend-icon="mdi-logout"
        title="로그아웃"
        :loading="isLoggingOut"
        :disabled="isLoggingOut"
        @click="logout"
      />
    </v-list>
  </v-navigation-drawer>
</template>

<script setup>
import {
  computed,
  ref,
} from 'vue';

import {
  useRoute,
  useRouter,
} from 'vue-router';

import { useAppLoading } from '../../composables/useAppLoading';
import { useSession } from '../../composables/useSession';

/**
 * 부모 컴포넌트에서 전달받는 Drawer 상태입니다.
 *
 * modelValue:
 * - 내비게이션 메뉴의 열림/닫힘 상태
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});

/**
 * 부모 컴포넌트로 전달하는 이벤트입니다.
 *
 * update:modelValue:
 * - Drawer 상태 변경
 *
 * error:
 * - 오류 메시지 전달
 *
 * 일반 업무 화면 이동용 공통 로딩은
 * Drawer가 직접 관리하지 않습니다.
 *
 * 화면 이동용 로딩 시작은
 * Vue Router 전역 페이지 이동 가드에서 처리합니다.
 */
const emit = defineEmits([
  'update:modelValue',
  'error',
]);

// 현재 경로 확인 및 화면 이동을 처리하는 Vue Router
const route = useRoute();
const router = useRouter();

/**
 * Till White 공통 전체 화면 로딩
 *
 * 일반 업무 화면 이동:
 * - Drawer에서는 로딩을 직접 시작하지 않음
 * - Vue Router가 공통 로딩 시작
 * - 목적지 페이지가 최초 데이터 준비 후 로딩 완료
 *
 * 로그아웃:
 * - 화면 이동 전에 Laravel 로그아웃 API 요청이 먼저 필요함
 * - 따라서 로그아웃 요청을 가리기 위해
 *   Drawer에서 직접 공통 로딩을 시작
 *
 * cancelLoading:
 * - 로그아웃 실패
 * - 로그인 화면 이동 완료
 *
 * 위 상황에서 공통 로딩을 즉시 종료할 때 사용합니다.
 */
const {
  beginNavigationLoading,
  cancelLoading,
} = useAppLoading();

/**
 * 로그인 세션(Session) 공통 기능
 *
 * can:
 * - 현재 사용자가 특정 권한(Permission)을
 *   가지고 있는지 확인합니다.
 *
 * clear:
 * - 프론트엔드에 저장된 로그인 사용자 정보를 초기화합니다.
 */
const {
  can,
  clear,
} = useSession();

// 로그아웃 처리 중인지 여부
const isLoggingOut = ref(false);

// 메뉴 화면 이동 처리 중인지 여부
const isNavigating = ref(false);

/**
 * 부모의 modelValue와 현재 Drawer 상태를 연결합니다.
 *
 * Drawer 상태가 변경되면
 * update:modelValue 이벤트를 부모에 전달합니다.
 */
const drawer = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

/**
 * 전체 내비게이션 메뉴 목록입니다.
 *
 * 메뉴 배치 순서:
 * 1. 메인
 * 2. 제품 관리
 * 3. 생산·폐기 관리
 * 4. 직원 관리
 * 5. 근무 관리
 * 6. 매출 관리
 * 7. 점포 관리
 * 8. 시스템
 * 9. 감사 로그
 *
 * 현재 실제 사용 기능:
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
 * title:
 * - 화면에 표시할 메뉴 이름
 *
 * icon:
 * - Material Design Icons(MDI) 아이콘
 *
 * to:
 * - 클릭 시 이동할 Vue Router 경로
 *
 * permission:
 * - 해당 기능에 필요한 권한(Permission) 코드
 *
 * developing:
 * - true = 현재 개발 중인 기능
 * - 메뉴에는 표시하지만 클릭할 수 없도록 비활성화
 *
 * showWithoutPermission:
 * - true = 필요한 권한이 없어도 메뉴 자체는 표시
 *
 * 현재 직원 관리에 사용합니다.
 *
 * 직원 관리 권한이 없는 사용자는
 * 메뉴 오른쪽에 '권한 없음'이 표시되지만
 * 메뉴 클릭 자체는 가능합니다.
 *
 * 실제 화면 접근은 Vue Router에서 다시 검사하며,
 * 실제 데이터 접근은 Laravel 서버에서 다시 검사합니다.
 */
const items = [
  {
    title: '메인',
    icon: 'mdi-home-outline',
    to: '/tillwhite/main',
    developing: false,
  },
  {
    title: '제품 관리',
    icon: 'mdi-food-croissant',
    to: '/tillwhite/products',
    permission: 'product.view',
    developing: false,
  },
  {
    title: '생산·폐기 관리',
    icon: 'mdi-baguette',
    to: '/tillwhite/production',
    permission: 'production.view',
    developing: false,
  },
  {
    title: '직원 관리',
    icon: 'mdi-account-group-outline',
    to: '/tillwhite/employees',
    permission: 'employee.view',
    developing: false,
    showWithoutPermission: true,
  },
  {
    title: '근무 관리',
    icon: 'mdi-calendar-clock',
    to: '/tillwhite/work',
    permission: 'schedule.view',
    developing: true,
  },
  {
    title: '매출 관리',
    icon: 'mdi-cash-register',
    to: '/tillwhite/sales',
    permission: 'sales.view',
    developing: true,
  },
  {
    title: '점포 관리',
    icon: 'mdi-store-outline',
    to: '/tillwhite/stores',
    permission: 'store.view',
    developing: true,
  },
  {
    title: '시스템',
    icon: 'mdi-cog-outline',
    to: '/tillwhite/system',
    permission: 'system.view',
    developing: true,
  },
  {
    title: '감사 로그',
    icon: 'mdi-history',
    to: '/tillwhite/audits',
    permission: 'audit.view',
    developing: true,
  },
];

/**
 * 현재 사용자에게 표시할 메뉴를 결정합니다.
 *
 * permission이 없는 메뉴:
 * - 항상 표시
 *
 * 필요한 권한을 가진 메뉴:
 * - 표시
 *
 * showWithoutPermission이 true인 메뉴:
 * - 필요한 권한이 없어도 표시
 *
 * 현재 직원 관리 메뉴가 이 방식을 사용하므로
 * 일반 직원에게도 직원 관리 메뉴가 표시됩니다.
 *
 * 단, 메뉴가 보인다는 것이
 * 실제 기능 접근 권한을 의미하지는 않습니다.
 */
const visibleItems = computed(() => {
  return items.filter((item) => {
    return (
      !item.permission
      || can(item.permission)
      || item.showWithoutPermission
    );
  });
});

/**
 * 현재 사용자가 해당 메뉴의
 * 필요한 권한(Permission)을 가지고 있지 않은지 확인합니다.
 *
 * showWithoutPermission이 true인 메뉴에 대해서만
 * '권한 없음' 상태를 표시합니다.
 *
 * 현재는 직원 관리 메뉴에 사용합니다.
 */
function hasNoPermission(item) {
  return (
    item.showWithoutPermission
    && item.permission
    && !can(item.permission)
  );
}

/**
 * 메뉴를 통해 다른 업무 화면으로 이동합니다.
 *
 * 처리 순서:
 *
 * 1. 현재 페이지와 같은 메뉴인지 확인
 * 2. Drawer 닫기
 * 3. Vue Router 화면 이동
 * 4. Router 전역 페이지 이동 가드에서 공통 로딩 시작
 *
 * Drawer는 화면 이동을 요청하는 역할만 담당합니다.
 *
 * 공통 전체 화면 로딩의 시작은 Router,
 * 종료는 목적지 업무 페이지가 담당합니다.
 *
 * 이렇게 하면 Drawer뿐만 아니라
 * 메인 빠른 메뉴나 다른 router.push() 이동도
 * 동일한 공통 로딩 구조를 사용하게 됩니다.
 */
async function navigateTo(to) {
  if (isNavigating.value) {
    return;
  }

  /**
   * 현재 보고 있는 페이지를 다시 선택한 경우
   * 새로운 화면 이동이나 로딩이 필요하지 않습니다.
   */
  if (route.path === to) {
    drawer.value = false;
    return;
  }

  isNavigating.value = true;

  // 화면 이동 전에 Drawer 닫기
  drawer.value = false;

  try {
    /**
     * Vue Router에 화면 이동을 요청합니다.
     *
     * 공통 전체 화면 로딩은
     * Router 전역 페이지 이동 가드에서 시작됩니다.
     */
    await router.push(to);
  } catch (error) {
    /**
     * 화면 이동 자체가 실패한 경우
     * 사용자에게 오류 메시지를 표시합니다.
     *
     * Router에서 시작된 공통 로딩이 남을 수 있으므로
     * 안전하게 즉시 취소합니다.
     */
    cancelLoading();

    emit(
      'error',
      '화면을 이동하지 못했습니다.',
    );

    isNavigating.value = false;
  }
}

/**
 * Drawer 상단의 Till White 클릭 처리
 *
 * Till White를 클릭하면
 * Vue Router를 통해 메인 화면으로 이동합니다.
 *
 * 공통 전체 화면 로딩은
 * Router 전역 페이지 이동 가드에서 시작됩니다.
 */
async function goToMain() {
  await navigateTo('/tillwhite/main');
}

/**
 * 내비게이션 메뉴 클릭 처리
 *
 * 정상 메뉴:
 * - Drawer 닫기
 * - Vue Router 화면 이동
 * - Router에서 공통 로딩 시작
 *
 * 권한 없음 메뉴:
 * - 클릭 가능
 * - Vue Router에서 공통 로딩 시작
 * - Vue Router에서 권한 검사
 * - 권한이 없으면 메인 화면으로 이동
 *
 * 개발 중 메뉴:
 * - disabled 상태이므로 클릭할 수 없음
 */
async function handleMenuClick(item) {
  if (item.developing) {
    return;
  }

  await navigateTo(item.to);
}

/**
 * 로그아웃을 처리합니다.
 *
 * 일반 업무 화면 이동과 달리
 * 로그아웃은 Vue Router 이동 전에
 * Laravel 로그아웃 API 요청이 먼저 실행됩니다.
 *
 * 따라서 로그아웃 API 요청이 시작되는 순간부터
 * 화면을 가리기 위해 여기에서 공통 로딩을 직접 시작합니다.
 *
 * 처리 순서:
 *
 * 1. 공통 전체 화면 로딩 시작
 * 2. Drawer 닫기
 * 3. Laravel 로그아웃 요청
 * 4. 프론트 로그인 세션 초기화
 * 5. 로그인 화면으로 이동
 * 6. 공통 전체 화면 로딩 종료
 *
 * 로그인 화면은 업무 페이지처럼
 * 최초 데이터 조회 후 completePageLoading()을 호출하지 않으므로
 * 로그인 화면 이동이 완료되면 직접 로딩을 종료합니다.
 *
 * 로그아웃 실패 시에도
 * 공통 로딩을 즉시 종료하고 오류 메시지를 표시합니다.
 */
async function logout() {
  if (isLoggingOut.value) {
    return;
  }

  isLoggingOut.value = true;
  drawer.value = false;

  // 로그아웃 API 요청을 가리기 위해 공통 전체 화면 로딩 시작
  beginNavigationLoading();

  try {
    await window.axios.post('/tillwhite/logout');

    // 프론트엔드에 저장된 로그인 사용자 정보 초기화
    clear();

    // 로그인 화면으로 이동
    await router.push({
      name: 'login',
    });

    /**
     * 로그인 화면은 업무 페이지의
     * completePageLoading()을 사용하지 않으므로
     * 공통 로딩을 즉시 종료합니다.
     */
    cancelLoading();
  } catch (error) {
    // 로그아웃 실패 시 공통 전체 화면 로딩 즉시 종료
    cancelLoading();

    // 서버에서 전달된 메시지가 없으면 기본 오류 메시지 사용
    emit(
      'error',
      error.response?.data?.message
        ?? '로그아웃 중 오류가 발생했습니다.',
    );

    isLoggingOut.value = false;
  }
}
</script>