<template>
  <!--
    로그인 이후 공통 레이아웃입니다.
    현재 페이지 제목을 AppShell에 전달합니다.
  -->
  <AppShell :title="pageTitle">
    <!--
      AppShell에서 현재 로그인 사용자(user)와
      권한 확인 함수(can)를 전달받습니다.
    -->
    <template #default="{ user, can }">
      <!-- 현재 로그인한 사용자 정보 -->
      <UserInfoCard
        v-if="user"
        :user="user"
      />

      <!-- 사용자 정보와 빠른 메뉴 영역 구분 -->
      <v-divider class="my-4" />

      <!-- 빠른 메뉴 제목 -->
      <SectionTitle
        title="빠른 메뉴"
        icon="mdi-view-grid-outline"
      />

      <!--
        빠른 메뉴 목록입니다.
        현재 사용자가 해당 메뉴의 권한을 가지고 있는 경우에만 표시합니다.
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

          {{ item.title }}
        </v-btn>
      </div>

      <!-- 권한에 따른 메뉴 표시 안내 -->
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
import AppShell from '../components/layout/AppShell.vue';
import SectionTitle from '../components/common/SectionTitle.vue';
import UserInfoCard from '../components/user/UserInfoCard.vue';

// 현재 페이지 제목
const pageTitle = '메인';

/**
 * 메인 화면의 빠른 메뉴 목록입니다.
 *
 * title      : 화면에 표시할 메뉴 이름
 * to         : 클릭 시 이동할 Vue Router 경로
 * permission : 메뉴 표시 여부를 판단할 권한 코드
 * icon       : 메뉴에 표시할 Material Design Icons 아이콘
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