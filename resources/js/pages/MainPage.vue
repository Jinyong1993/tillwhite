<template>
  <!--
    Till White 메인 화면

    로그인 이후 가장 먼저 사용하는 메인 페이지입니다.

    공통 화면 틀(AppShell)을 사용하여
    공통 헤더, 내비게이션 메뉴 및 오류 처리를 관리합니다.

    전체 화면 로딩은
    애플리케이션 공통 로딩(useAppLoading)에서 관리합니다.

    메인 화면 구성:
    1. 접근 권한 안내
    2. 현재 로그인한 사용자 정보
    3. 오늘 생산·폐기·로스 현황
    4. 자주 사용하는 업무의 빠른 메뉴
    5. 권한 및 개발 중 기능 안내
  -->
  <AppShell :title="pageTitle">
    <!--
      공통 화면 틀(AppShell)의 기본 슬롯

      user:
      - 현재 로그인한 사용자 정보

      can:
      - 현재 사용자가 특정 권한(Permission)을
        가지고 있는지 확인하는 함수

      sessionReady:
      - AppShell의 최초 사용자 정보 조회 완료 여부
    -->
    <template #default="{ user, can, sessionReady }">
      <!--
        AppShell의 사용자 정보 준비 상태를
        메인 화면의 준비 상태와 연결합니다.
      -->
      <PageReadyWatcher
        :session-ready="sessionReady"
        @session-ready="handleSessionReady"
      />

      <!--
        접근 권한 안내

        권한이 없는 화면에 접근했을 때
        Vue Router가 메인 화면으로 이동시키면서
        전달한 안내 메시지를 표시합니다.
      -->
      <v-alert
        v-if="accessDeniedMessage"
        class="mb-4"
        type="warning"
        variant="tonal"
        density="compact"
        closable
        @click:close="clearAccessDeniedMessage"
      >
        {{ accessDeniedMessage }}
      </v-alert>

      <!-- 로그인 사용자 정보 -->
      <UserInfoCard
        v-if="user"
        :user="user"
      />

      <!-- 사용자 정보와 오늘 현황 영역 구분선 -->
      <v-divider class="my-4" />

      <!--
        오늘 생산·폐기·로스 현황

        생산·폐기 조회 권한(production.view)을 가진
        사용자에게만 표시합니다.

        최초 API 조회가 완료되면
        준비 완료(ready) 이벤트를 전달받습니다.
      -->
      <ProductionSummaryCard
        v-if="can('production.view')"
        @ready="handleSummaryReady"
      />

      <!--
        생산 현황 조회 권한이 없는 경우에는
        기다릴 생산 현황 API가 없음을 처리합니다.
      -->
      <PageReadyWatcher
        v-else
        :session-ready="sessionReady"
        :summary-not-required="true"
        @summary-ready="handleSummaryReady"
      />

      <!-- 오늘 현황과 빠른 메뉴 영역 구분선 -->
      <v-divider
        v-if="can('production.view')"
        class="my-4"
      />

      <!-- 빠른 메뉴 제목 -->
      <SectionTitle
        title="빠른 메뉴"
        icon="mdi-view-grid-outline"
      />

      <!--
        빠른 메뉴

        Vue Router의 :to를 그대로 사용합니다.

        이제 공통 로딩 시작은 Router가 담당하므로
        빠른 메뉴에서 별도로 로딩을 시작할 필요가 없습니다.
      -->
      <div class="d-grid">
        <v-btn
          v-for="item in quickItems.filter((item) => can(item.permission))"
          :key="item.title"
          :to="item.developing ? undefined : item.to"
          :disabled="item.developing"
          variant="outlined"
          class="mb-2"
          block
        >
          <!-- 메뉴 아이콘 -->
          <v-icon
            start
            :icon="item.icon"
          />

          <!-- 메뉴 이름 -->
          {{ item.title }}

          <!-- 개발 중인 기능 표시 -->
          <v-chip
            v-if="item.developing"
            class="ml-2"
            size="x-small"
            variant="tonal"
          >
            개발 중
          </v-chip>
        </v-btn>
      </div>

      <!-- 메뉴 및 권한 안내 -->
      <v-alert
        class="mt-3"
        type="info"
        variant="tonal"
        density="compact"
      >
        권한에 따라 사용할 수 있는 기능이 제한됩니다.
        개발 중인 기능은 현재 사용할 수 없습니다.
      </v-alert>
    </template>
  </AppShell>
</template>

<script setup>
import {
  computed,
  defineComponent,
  h,
  ref,
  watch,
} from 'vue';

import {
  useRoute,
  useRouter,
} from 'vue-router';

import SectionTitle from '../components/common/SectionTitle.vue';
import AppShell from '../components/layout/AppShell.vue';
import ProductionSummaryCard from '../components/production/ProductionSummaryCard.vue';
import UserInfoCard from '../components/user/UserInfoCard.vue';

import { useAppLoading } from '../composables/useAppLoading';
import { useSession } from '../composables/useSession';

/**
 * 현재 화면의 주소 정보(Route)
 */
const route = useRoute();

/**
 * 현재 주소 변경에 사용하는 Vue Router
 */
const router = useRouter();

/**
 * 애플리케이션 공통 전체 화면 로딩
 *
 * 메인 화면에서는 다음 두 조건이 모두 준비된 뒤
 * 공통 로딩을 완료합니다.
 *
 * 1. 로그인 사용자 정보 준비
 * 2. 생산 현황 데이터 준비
 */
const {
  completePageLoading,
} = useAppLoading();

/**
 * 현재 로그인 사용자의 권한 확인 기능
 */
const {
  can,
} = useSession();

/**
 * 현재 페이지 제목
 */
const pageTitle = '메인';

/**
 * AppShell 사용자 정보 준비 여부
 */
const sessionIsReady = ref(false);

/**
 * 생산 현황 준비 여부
 *
 * 생산 현황 조회 권한(production.view)이 없는 경우에는
 * 조회할 데이터 자체가 없으므로 준비 완료로 처리합니다.
 */
const summaryIsReady = ref(
  !can('production.view'),
);

/**
 * 현재 화면의 공통 로딩 완료 처리가
 * 이미 실행되었는지 여부입니다.
 *
 * 여러 준비 상태가 비슷한 시점에 변경되더라도
 * completePageLoading()을 중복 호출하지 않도록 보호합니다.
 */
const pageLoadingCompleted = ref(false);

/**
 * 권한이 없는 화면에서 전달된
 * 접근 거부 안내 메시지입니다.
 */
const accessDeniedMessage = computed(() => {
  return typeof route.query.accessDenied === 'string'
    ? route.query.accessDenied
    : '';
});

/**
 * 접근 거부 안내 메시지를 URL에서 제거합니다.
 */
async function clearAccessDeniedMessage() {
  if (!route.query.accessDenied) {
    return;
  }

  const query = {
    ...route.query,
  };

  delete query.accessDenied;

  await router.replace({
    name: 'main',
    query,
  });
}

/**
 * 메인 화면이 실제로 준비되었는지 확인합니다.
 *
 * 다음 조건이 모두 충족되어야 합니다.
 *
 * 1. AppShell 사용자 정보 조회 완료
 * 2. 생산 현황 조회 완료
 *
 * 모든 조건이 충족된 뒤에만
 * 애플리케이션 공통 전체 화면 로딩을 종료합니다.
 */
async function tryCompletePageLoading() {
  if (pageLoadingCompleted.value) {
    return;
  }

  if (!sessionIsReady.value) {
    return;
  }

  if (!summaryIsReady.value) {
    return;
  }

  pageLoadingCompleted.value = true;

  await completePageLoading();
}

/**
 * AppShell 사용자 정보 준비 완료 처리
 */
async function handleSessionReady() {
  sessionIsReady.value = true;

  await tryCompletePageLoading();
}

/**
 * 생산 현황 준비 완료 처리
 *
 * API 조회 성공/실패와 관계없이
 * 화면에 표시할 결과가 결정되면 준비 완료로 처리합니다.
 */
async function handleSummaryReady() {
  summaryIsReady.value = true;

  await tryCompletePageLoading();
}

/**
 * AppShell의 slot 값인 sessionReady를
 * 일반 script 상태와 연결하기 위한 내부 컴포넌트입니다.
 *
 * 화면 요소를 실제로 렌더링하지 않으며
 * sessionReady가 true가 되는 순간
 * 부모 MainPage에 이벤트만 전달합니다.
 *
 * summaryNotRequired가 true인 경우에는
 * 생산 현황 조회 권한(production.view)이 없어
 * 별도로 기다릴 생산 현황 API가 없음을 전달합니다.
 */
const PageReadyWatcher = defineComponent({
  name: 'PageReadyWatcher',

  props: {
    sessionReady: {
      type: Boolean,
      default: false,
    },

    summaryNotRequired: {
      type: Boolean,
      default: false,
    },
  },

  emits: [
    'session-ready',
    'summary-ready',
  ],

  setup(props, { emit }) {
    watch(
      () => props.sessionReady,
      (ready) => {
        if (ready) {
          emit('session-ready');
        }
      },
      {
        immediate: true,
      },
    );

    if (props.summaryNotRequired) {
      emit('summary-ready');
    }

    return () => h('span', {
      style: {
        display: 'none',
      },
    });
  },
});

/**
 * 메인 화면 빠른 메뉴 목록
 *
 * 공통 로딩 시작은 Vue Router에서 처리하므로
 * 각 빠른 메뉴가 별도의 로딩 코드를 가지지 않습니다.
 */
const quickItems = [
  {
    title: '제품 관리',
    to: '/tillwhite/products',
    permission: 'product.view',
    icon: 'mdi-food-croissant',
    developing: false,
  },
  {
    title: '생산·폐기 관리',
    to: '/tillwhite/production',
    permission: 'production.view',
    icon: 'mdi-baguette',
    developing: false,
  },
  {
    title: '근무 관리',
    to: '/tillwhite/work',
    permission: 'schedule.view',
    icon: 'mdi-calendar-clock',
    developing: true,
  },
  {
    title: '매출 관리',
    to: '/tillwhite/sales',
    permission: 'sales.view',
    icon: 'mdi-cash-register',
    developing: true,
  },
];
</script>