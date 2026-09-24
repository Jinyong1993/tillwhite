<template>
  <!--
    로그인 이후 사용하는 공통 내비게이션 메뉴입니다.
    PC와 모바일 모두 임시(temporary) Drawer 형태로 표시됩니다.
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
      <!-- 내비게이션 제목 -->
      <v-list-subheader class="font-weight-black">
        Till White
      </v-list-subheader>

      <!--
        현재 사용자가 접근할 수 있는 메뉴만 표시합니다.
        메뉴 클릭 후 Drawer를 자동으로 닫습니다.
      -->
      <template
        v-for="item in visibleItems"
        :key="item.title"
      >
        <v-list-item
          :prepend-icon="item.icon"
          :title="item.title"
          :to="item.to"
          @click="drawer = false"
        />
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
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useSession } from '../../composables/useSession';

/**
 * 부모 컴포넌트에서 전달받는 Drawer 상태입니다.
 *
 * modelValue : 내비게이션 메뉴의 열림/닫힘 상태
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
 * update:modelValue : Drawer 상태 변경
 * error             : 오류 메시지 전달
 * loading           : 공통 로딩 상태 전달
 */
const emit = defineEmits([
  'update:modelValue',
  'error',
  'loading',
]);

// Vue Router
const router = useRouter();

// 로그인 세션의 권한 확인 및 초기화 기능
const {
  can,
  clear,
} = useSession();

// 로그아웃 처리 중인지 여부
const isLoggingOut = ref(false);

/**
 * 부모의 modelValue와 현재 Drawer 상태를 연결합니다.
 * 값이 변경되면 update:modelValue 이벤트를 부모에게 전달합니다.
 */
const drawer = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

/**
 * 전체 내비게이션 메뉴 목록입니다.
 *
 * title      : 화면에 표시할 메뉴 이름
 * icon       : Material Design Icons 아이콘
 * to         : 클릭 시 이동할 Vue Router 경로
 * permission : 메뉴 표시 여부를 판단할 권한 코드
 *
 * 메인 메뉴는 로그인한 모든 사용자가 접근하므로
 * 별도의 permission 값을 지정하지 않습니다.
 */
const items = [
  {
    title: '메인',
    icon: 'mdi-home-outline',
    to: '/tillwhite/main',
  },
  {
    title: '생산·폐기 관리',
    icon: 'mdi-baguette',
    to: '/tillwhite/production',
    permission: 'production.view',
  },
  {
    title: '근무 관리',
    icon: 'mdi-calendar-clock',
    to: '/tillwhite/work',
    permission: 'schedule.view',
  },
  {
    title: '제품 관리',
    icon: 'mdi-food-croissant',
    to: '/tillwhite/products',
    permission: 'product.view',
  },
  {
    title: '매출 관리',
    icon: 'mdi-cash-register',
    to: '/tillwhite/sales',
    permission: 'sales.view',
  },
  {
    title: '직원 관리',
    icon: 'mdi-account-group-outline',
    to: '/tillwhite/employees',
    permission: 'employee.view',
  },
  {
    title: '점포 관리',
    icon: 'mdi-store-outline',
    to: '/tillwhite/stores',
    permission: 'store.view',
  },
  {
    title: '시스템',
    icon: 'mdi-cog-outline',
    to: '/tillwhite/system',
    permission: 'system.view',
  },
  {
    title: '감사 로그',
    icon: 'mdi-history',
    to: '/tillwhite/audits',
    permission: 'audit.view',
  },
];

/**
 * 현재 사용자가 볼 수 있는 메뉴만 추려냅니다.
 *
 * permission이 없는 메뉴는 항상 표시하고,
 * permission이 있는 메뉴는 can()을 통해 권한을 확인합니다.
 */
const visibleItems = computed(() => {
  return items.filter((item) => {
    return !item.permission || can(item.permission);
  });
});

/**
 * 로그아웃을 처리합니다.
 *
 * 로그아웃 요청 중에는 중복 클릭을 방지하고,
 * 성공하면 세션 정보를 초기화한 뒤 로그인 화면으로 이동합니다.
 * 실패하면 부모 컴포넌트에 오류 메시지를 전달합니다.
 */
async function logout() {
  isLoggingOut.value = true;
  drawer.value = false;

  emit('loading', true);

  try {
    await window.axios.post('/tillwhite/logout');

    // 프론트엔드에 저장된 로그인 사용자 정보 초기화
    clear();

    // 로그인 화면으로 이동
    await router.push({
      name: 'login',
    });
  } catch (e) {
    // 로그아웃 실패 시 전체 화면 로딩 해제
    emit('loading', false);

    // 서버에서 전달된 메시지가 없으면 기본 오류 메시지 사용
    emit(
      'error',
      e.response?.data?.message ?? '로그아웃 중 오류가 발생했습니다.',
    );

    isLoggingOut.value = false;
  }
}
</script>