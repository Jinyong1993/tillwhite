<template>
  <!--
    Till White 메인 화면

    로그인 이후 가장 먼저 사용하는 메인 페이지이다.

    AppShell을 통해 공통 헤더, 내비게이션 메뉴,
    로딩 및 오류 처리 기능을 사용한다.

    메인 화면에서는 현재 로그인한 사용자 정보와
    자주 사용하는 업무 메뉴를 빠른 메뉴로 표시한다.
  -->
  <AppShell :title="pageTitle">
    <!--
      AppShell 기본 슬롯

      AppShell에서 현재 로그인 사용자 정보와
      Permission 확인 함수를 전달받는다.

      user:
      - 현재 로그인한 사용자 정보

      can:
      - 현재 사용자가 특정 Permission을 가지고 있는지 확인하는 함수
    -->
    <template #default="{ user, can }">
      <!--
        로그인 사용자 정보

        현재 로그인한 사용자의
        이름, 점포, 부서, 직급 정보를 표시한다.
      -->
      <UserInfoCard
        v-if="user"
        :user="user"
      />

      <!-- 사용자 정보와 빠른 메뉴 영역 구분선 -->
      <v-divider class="my-4" />

      <!-- 빠른 메뉴 제목 -->
      <SectionTitle
        title="빠른 메뉴"
        icon="mdi-view-grid-outline"
      />

      <!--
        빠른 메뉴

        현재 사용자가 Permission을 가지고 있는 메뉴만 표시한다.

        현재 사용 가능한 기능:
        - 제품 관리
        - 생산·폐기 관리

        개발 중인 기능:
        - 근무 관리
        - 매출 관리

        개발 중인 기능도 메뉴에는 표시하지만
        버튼을 비활성화하여 페이지로 이동할 수 없도록 한다.

        실제 시스템 접근 권한은 프론트 화면만으로 판단하지 않고
        Laravel 서버에서도 별도로 검사한다.
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

        사용자 Permission에 따라 표시되는 메뉴가 달라질 수 있으며,
        아직 사용할 수 없는 기능은 '개발 중'으로 표시한다.
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
import SectionTitle from '../components/common/SectionTitle.vue';
import AppShell from '../components/layout/AppShell.vue';
import UserInfoCard from '../components/user/UserInfoCard.vue';

/**
 * 현재 페이지 제목
 *
 * AppShell에 전달되며
 * 공통 AppHeader의 부제목으로 표시된다.
 */
const pageTitle = '메인';

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
 * - 메뉴를 클릭했을 때 이동할 Vue Router 경로
 *
 * permission:
 * - 메뉴 표시 여부를 판단할 Permission 코드
 *
 * icon:
 * - 메뉴에 표시할 Material Design Icons 아이콘
 *
 * developing:
 * - false이면 현재 사용할 수 있는 기능
 * - true이면 아직 개발 중인 기능
 * - 개발 중인 기능은 메뉴에는 표시하지만 클릭할 수 없다.
 *
 * Permission은 프론트에서 메뉴 표시 여부를 결정하기 위해 사용하며,
 * 실제 데이터 접근 권한은 Laravel 서버에서도 다시 검사한다.
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