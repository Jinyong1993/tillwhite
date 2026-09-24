<template>
  <!--
    Till White 메인 화면

    로그인 이후 가장 먼저 사용하는 메인 페이지이다.

    공통 화면 틀(AppShell)을 사용하여
    공통 헤더, 내비게이션 메뉴, 로딩 및 오류 처리를 관리한다.

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

      AppShell에서 현재 로그인한 사용자 정보와
      권한 확인에 필요한 기능을 전달받는다.

      user:
      - 현재 로그인한 사용자 정보

      can:
      - 현재 사용자가 특정 권한(Permission)을
        가지고 있는지 확인하는 함수
    -->
    <template #default="{ user, can }">
      <!--
        접근 권한 안내

        권한이 없는 화면에 URL을 직접 입력하는 등의 방법으로
        접근했을 때 전역 페이지 이동 가드(Navigation Guard)가
        메인 화면으로 이동시키면서 전달한 메시지를 표시한다.

        예:
        직원 관리 화면에 employee.view 권한 없이 접근
        → 메인 화면으로 이동
        → "직원 정보를 열람할 권한이 없습니다." 표시
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

      <!--
        로그인 사용자 정보

        현재 로그인한 사용자의
        이름, 점포, 부서, 직급 정보를 표시한다.
      -->
      <UserInfoCard
        v-if="user"
        :user="user"
      />

      <!-- 사용자 정보와 오늘 현황 영역 구분선 -->
      <v-divider class="my-4" />

      <!--
        오늘 생산·폐기·로스 현황

        생산·폐기 조회 권한(production.view)을 가진
        사용자에게만 오늘 현황을 표시한다.

        현황 화면과 서버 API 호출은
        생산 현황 공통 컴포넌트(ProductionSummaryCard)에서 처리한다.

        실제 사용자가 조회할 수 있는 점포의 데이터 범위는
        Laravel 서버의 생산 기록 조회 범위(ProductionRecordScope)에서 결정한다.

        따라서 메인 화면에서는
        점포나 부서에 따른 데이터 필터링을 직접 처리하지 않는다.
      -->
      <ProductionSummaryCard
        v-if="can('production.view')"
      />

      <!--
        오늘 현황과 빠른 메뉴 영역 구분선

        생산·폐기 조회 권한(production.view)이 있어
        오늘 현황이 표시되는 경우에만 구분선을 표시한다.

        권한이 없는 사용자에게
        불필요한 구분선이 표시되는 것을 방지한다.
      -->
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

        현재 사용자가 각 메뉴에 필요한
        조회 권한(Permission)을 가지고 있는 경우에만 표시한다.

        현재 사용 가능한 기능:
        - 제품 관리
        - 생산·폐기 관리

        현재 개발 중인 기능:
        - 근무 관리
        - 매출 관리

        개발 중인 기능도 사용자에게 메뉴는 보여주지만
        버튼을 비활성화하여 해당 화면으로 이동할 수 없도록 한다.

        프론트 화면에서 메뉴를 숨기거나 비활성화하는 것은
        사용자 화면을 제어하기 위한 처리이다.

        실제 기능 사용 가능 여부와 데이터 접근 범위는
        Laravel 서버에서도 다시 검사한다.
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

      <!--
        메뉴 및 권한 안내

        사용자가 가지고 있는 권한(Permission)에 따라
        화면에 표시되거나 사용할 수 있는 기능이 달라질 수 있다.

        아직 개발이 완료되지 않은 기능은
        '개발 중' 상태로 표시하고 사용할 수 없도록 한다.
      -->
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
  onMounted,
} from 'vue';

import {
  useRoute,
  useRouter,
} from 'vue-router';

import SectionTitle from '../components/common/SectionTitle.vue';
import AppShell from '../components/layout/AppShell.vue';
import ProductionSummaryCard from '../components/production/ProductionSummaryCard.vue';
import UserInfoCard from '../components/user/UserInfoCard.vue';

/**
 * 현재 화면의 주소 정보(Route)를 가져온다.
 *
 * 권한이 없는 화면에서 메인으로 이동했을 때
 * 전달된 접근 거부 메시지를 확인하기 위해 사용한다.
 */
const route = useRoute();

/**
 * 현재 주소를 변경하기 위한
 * Vue Router 객체를 가져온다.
 *
 * 접근 거부 메시지를 확인한 뒤
 * 주소에서 accessDenied 값을 제거하기 위해 사용한다.
 */
const router = useRouter();

/**
 * 현재 페이지 제목
 *
 * 공통 화면 틀(AppShell)에 전달되며
 * 공통 헤더(AppHeader)의 부제목으로 표시된다.
 */
const pageTitle = '메인';

/**
 * 권한이 없는 화면에서 전달된
 * 접근 거부 안내 메시지이다.
 *
 * 예:
 * /tillwhite/employees 접근
 * → 직원 조회 권한(employee.view) 없음
 * → 메인으로 이동
 * → accessDenied에 안내 메시지 전달
 *
 * accessDenied 값이 없거나 문자열이 아닌 경우에는
 * 빈 문자열을 반환하여 알림을 표시하지 않는다.
 */
const accessDeniedMessage = computed(() => {
  return typeof route.query.accessDenied === 'string'
    ? route.query.accessDenied
    : '';
});

/**
 * 접근 거부 안내 메시지를 URL에서 제거한다.
 *
 * 현재 주소의 다른 Query 값은 그대로 유지하고
 * accessDenied 값만 제거한다.
 *
 * router.replace()를 사용하므로
 * 브라우저 방문 기록에 불필요한 이동 기록을 추가하지 않는다.
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
 * 메인 화면이 열린 뒤
 * 접근 거부 메시지가 URL에 남아 있다면
 * 주소에서는 해당 값을 제거한다.
 *
 * 화면에 표시되는 메시지는 현재 렌더링 과정에서 확인할 수 있지만
 * URL에는 접근 거부 문구가 계속 남지 않도록 정리한다.
 */
onMounted(async () => {
  if (!route.query.accessDenied) {
    return;
  }

  /**
   * 메시지를 바로 제거하면 computed 값도 함께 사라지므로
   * 현재 구현에서는 사용자가 알림의 닫기 버튼을 눌렀을 때
   * URL의 accessDenied 값을 제거한다.
   *
   * 따라서 여기서는 별도의 자동 제거 작업을 하지 않는다.
   */
});

/**
 * 메인 화면 빠른 메뉴 목록
 *
 * 사용자가 자주 접근하는 주요 업무 기능을
 * 메인 화면에서 바로 이동할 수 있도록 표시한다.
 *
 * 메뉴 배치 순서:
 * 1. 제품 관리
 * 2. 생산·폐기 관리
 * 3. 근무 관리
 * 4. 매출 관리
 *
 * title:
 * - 화면에 표시할 메뉴 이름
 *
 * to:
 * - 메뉴를 클릭했을 때 이동할
 *   Vue Router의 화면 경로
 *
 * permission:
 * - 메뉴 표시 여부를 판단하는 권한(Permission) 코드
 *
 * - product.view = 제품 조회 권한
 * - production.view = 생산·폐기 조회 권한
 * - schedule.view = 근무 일정 조회 권한
 * - sales.view = 매출 조회 권한
 *
 * icon:
 * - 메뉴에 표시할 아이콘
 * - Material Design Icons(MDI)의 아이콘 코드를 사용한다.
 *
 * developing:
 * - false = 현재 사용할 수 있는 기능
 * - true = 현재 개발 중인 기능
 *
 * 개발 중인 기능은 메뉴에는 표시하지만
 * 버튼을 비활성화하여 화면으로 이동할 수 없도록 한다.
 *
 * 권한(Permission)은 프론트 화면에서
 * 메뉴 표시 여부를 판단하기 위해 사용한다.
 *
 * 실제 기능 접근 가능 여부와 데이터 조회 범위는
 * Laravel 서버에서도 다시 검사한다.
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