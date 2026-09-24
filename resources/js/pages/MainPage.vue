<template>
  <!--
    Till White 메인 화면

    로그인 이후 사용하는 공통 레이아웃인 AppShell을 사용하고,
    현재 페이지 이름인 '메인'을 전달한다.
  -->
  <AppShell :title="pageTitle">
    <!--
      AppShell 기본 슬롯

      AppShell에서 현재 로그인 사용자와
      Permission 확인 함수를 전달받는다.

      user:
      - 현재 로그인한 사용자 정보

      can:
      - 현재 사용자가 특정 Permission을 가지고 있는지 확인하는 함수
    -->
    <template #default="{ user, can }">
      <!--
        로그인 사용자 정보

        사용자 정보가 정상적으로 존재하는 경우에만
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

        quickItems에 등록된 메뉴 중
        현재 사용자가 필요한 Permission을 가지고 있는
        메뉴만 화면에 표시한다.

        여기에서 메뉴를 숨기는 것은 사용자 화면을 위한 처리이며,
        실제 접근 권한은 Laravel 서버에서도 별도로 검사한다.
      -->
      <div class="d-grid">
        <v-btn
          v-for="item in quickItems.filter((item) => can(item.permission))"
          :key="item.title"
          :to="item.to"
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
        </v-btn>
      </div>

      <!--
        권한 안내

        로그인한 사용자의 Permission에 따라
        사용할 수 있는 메뉴가 달라질 수 있음을 안내한다.
      -->
      <v-alert
        class="mt-3"
        type="info"
        variant="tonal"
        density="compact"
      >
        권한에 따라 사용할 수 있는 메뉴만 표시됩니다.
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
 * 메인 화면 빠른 메뉴
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
 * 실제 데이터 접근 권한은 Laravel에서 다시 검사하며,
 * 여기의 permission은 프론트 화면에서
 * 사용할 수 없는 메뉴를 숨기기 위해 사용한다.
 */
const quickItems = [
  {
    title: '생산·폐기 입력',
    to: '/tillwhite/production',
    permission: 'production.view',
    icon: 'mdi-baguette',
  },
  {
    title: '근무 관리',
    to: '/tillwhite/work',
    permission: 'schedule.view',
    icon: 'mdi-calendar-clock',
  },
  {
    title: '제품·레시피',
    to: '/tillwhite/products',
    permission: 'product.view',
    icon: 'mdi-food-croissant',
  },
  {
    title: '매출',
    to: '/tillwhite/sales',
    permission: 'sales.view',
    icon: 'mdi-cash-register',
  },
];
</script>