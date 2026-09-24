<template>
  <!--
    로그인 이후 사용하는 공통 내비게이션 메뉴입니다.

    PC와 모바일 모두 temporary Drawer 형태로 표시하며,
    현재 사용 가능한 기능과 개발 중인 기능을 함께 표시합니다.
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
        메인 화면으로 이동하고 Drawer를 닫습니다.

        상단 공통 헤더의 Till White와 동일하게
        시스템 이름 자체를 홈 링크처럼 사용할 수 있습니다.
      -->
      <v-list-subheader
        class="font-weight-black cursor-pointer"
        @click="goToMain"
      >
        Till White
      </v-list-subheader>

      <!--
        현재 사용자가 볼 수 있는 메뉴 목록입니다.

        사용 가능한 메뉴:
        - 정상적으로 페이지 이동 가능

        개발 중인 메뉴:
        - 메뉴 목록에는 표시
        - 클릭 불가능
        - 오른쪽에 '개발 중' 표시

        기존 Permission 검사는 그대로 유지하여
        사용자가 볼 수 있는 메뉴만 표시합니다.
      -->
      <template
        v-for="item in visibleItems"
        :key="item.title"
      >
        <v-list-item
          :prepend-icon="item.icon"
          :title="item.title"
          :to="item.developing ? undefined : item.to"
          :disabled="item.developing"
          @click="handleMenuClick(item)"
        >
          <!-- 개발 중인 기능 표시 -->
          <template
            v-if="item.developing"
            #append
          >
            <v-chip
              size="x-small"
              variant="tonal"
            >
              개발 중
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
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

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
 * loading:
 * - 공통 로딩 상태 전달
 */
const emit = defineEmits([
  'update:modelValue',
  'error',
  'loading',
]);

// Vue Router
const router = useRouter();

/**
 * 로그인 Session 공통 기능
 *
 * can:
 * - 현재 사용자의 Permission 보유 여부 확인
 *
 * clear:
 * - 프론트엔드에 저장된 로그인 사용자 정보 초기화
 */
const {
  can,
  clear,
} = useSession();

// 로그아웃 처리 중인지 여부
const isLoggingOut = ref(false);

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
 * 4. 근무 관리
 * 5. 매출 관리
 * 6. 직원 관리
 * 7. 점포 관리
 * 8. 시스템
 * 9. 감사 로그
 *
 * 현재 실제 사용 기능:
 * - 메인
 * - 제품 관리
 * - 생산·폐기 관리
 *
 * 나머지 기능은 삭제하지 않고
 * 향후 개발을 위해 '개발 중' 상태로 유지합니다.
 *
 * title:
 * - 화면에 표시할 메뉴 이름
 *
 * icon:
 * - Material Design Icons 아이콘
 *
 * to:
 * - 클릭 시 이동할 Vue Router 경로
 *
 * permission:
 * - 메뉴 표시 여부를 판단할 Permission 코드
 *
 * developing:
 * - true이면 현재 개발 중인 기능
 * - 메뉴에는 표시하지만 클릭할 수 없도록 비활성화합니다.
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
    title: '직원 관리',
    icon: 'mdi-account-group-outline',
    to: '/tillwhite/employees',
    permission: 'employee.view',
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
 * 현재 사용자가 볼 수 있는 메뉴만 추려냅니다.
 *
 * permission이 없는 메뉴는 항상 표시하고,
 * permission이 있는 메뉴는 can()을 통해 권한을 확인합니다.
 *
 * 개발 중인 메뉴도 사용자가 해당 Permission을 가지고 있다면
 * 목록에는 표시하되 클릭할 수 없도록 처리합니다.
 */
const visibleItems = computed(() => {
  return items.filter((item) => {
    return !item.permission || can(item.permission);
  });
});

/**
 * Drawer 상단의 Till White 클릭 처리
 *
 * Till White를 클릭하면 Drawer를 닫고
 * 메인 화면으로 이동합니다.
 *
 * 이미 메인 화면에 있는 경우에도
 * 동일하게 Drawer만 닫히므로 자연스럽게 동작합니다.
 */
async function goToMain() {
  drawer.value = false;

  await router.push({
    name: 'main',
  });
}

/**
 * 내비게이션 메뉴 클릭 처리
 *
 * 사용 가능한 메뉴를 클릭하면 Drawer를 닫습니다.
 *
 * 개발 중인 메뉴는 disabled 상태이므로
 * 실제 페이지 이동이 발생하지 않습니다.
 */
function handleMenuClick(item) {
  if (item.developing) {
    return;
  }

  drawer.value = false;
}

/**
 * 로그아웃을 처리합니다.
 *
 * 로그아웃 요청 중에는 중복 클릭을 방지하고,
 * 성공하면 세션 정보를 초기화한 뒤 로그인 화면으로 이동합니다.
 *
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
  } catch (error) {
    // 로그아웃 실패 시 전체 화면 로딩 해제
    emit('loading', false);

    // 서버에서 전달된 메시지가 없으면 기본 오류 메시지 사용
    emit(
      'error',
      error.response?.data?.message ?? '로그아웃 중 오류가 발생했습니다.',
    );

    isLoggingOut.value = false;
  }
}
</script>