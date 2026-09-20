<template>
  <v-navigation-drawer
    v-model="drawer"
    location="left"
    width="300"
    temporary
  >
    <v-list>
      <!--
        메뉴 상단 제목

        현재 시스템의 이름을 표시한다.
      -->
      <v-list-subheader>
        Till White
      </v-list-subheader>

      <!--
        메인

        현재 로그인 사용자의 기본 정보를 확인하는
        메인 페이지로 이동한다.

        현재 사용자의 이름, 점포, 부서, 역할 등의
        기본 정보를 확인할 수 있다.
      -->
      <v-list-item
        prepend-icon="mdi-home-outline"
        title="메인"
        to="/tillwhite/main"
      />

      <!--
        생산·폐기 관리

        생산 및 폐기와 관련된 기능을
        하나의 메뉴 그룹으로 관리한다.

        메뉴를 선택하면 입력, 조회, 통계
        하위 메뉴가 펼쳐진다.
      -->
      <v-list-group
        value="production-waste"
      >
        <template #activator="{ props }">
          <v-list-item
            v-bind="props"
            prepend-icon="mdi-chart-box-outline"
          >
            <v-list-item-title
              class="nav-menu-title"
            >
              생산·폐기 관리
            </v-list-item-title>
          </v-list-item>
        </template>

        <!--
          입력

          로그인 사용자의 점포에서 취급하는 제품을 기준으로
          생산량과 폐기량을 입력하기 위한 메뉴이다.
        -->
        <v-list-item
          prepend-icon="mdi-pencil-outline"
          title="입력"
        />

        <!--
          조회

          기존에 입력된 생산 및 폐기 기록을
          날짜, 제품 등의 조건으로 조회하기 위한 메뉴이다.
        -->
        <v-list-item
          prepend-icon="mdi-magnify"
          title="조회"
        />

        <!--
          통계

          저장된 생산 및 폐기 기록을 기준으로
          생산량, 폐기량, 폐기율 등의 통계를 확인하기 위한 메뉴이다.
        -->
        <v-list-item
          prepend-icon="mdi-chart-line"
          title="통계"
        />
      </v-list-group>

      <!--
        근무 관리

        직원의 근무 일정 및 근무 현황 등을
        관리하기 위한 메뉴이다.

        현재는 기능을 개발하지 않았으므로
        비활성화 상태로 표시한다.
      -->
      <v-list-item
        prepend-icon="mdi-calendar-outline"
        title="근무 관리"
        disabled
      >
        <template #append>
          <v-chip
            size="x-small"
            variant="tonal"
          >
            개발중
          </v-chip>
        </template>
      </v-list-item>

      <!--
        제품 관리

        Till White에서 사용하는 전체 제품 정보를 관리하고
        향후 점포별 취급 제품을 설정하기 위한 메뉴이다.

        현재는 기능을 개발하지 않았으므로
        비활성화 상태로 표시한다.
      -->
      <v-list-item
        prepend-icon="mdi-bread-slice-outline"
        title="제품 관리"
        disabled
      >
        <template #append>
          <v-chip
            size="x-small"
            variant="tonal"
          >
            개발중
          </v-chip>
        </template>
      </v-list-item>

      <!--
        매출 관리

        향후 점포 및 제품별 매출 데이터를
        입력하고 조회하기 위한 메뉴이다.

        현재는 관련 데이터 구조와 기능을 개발하지 않았으므로
        비활성화 상태로 표시한다.
      -->
      <v-list-item
        prepend-icon="mdi-cash-register"
        title="매출 관리"
        disabled
      >
        <template #append>
          <v-chip
            size="x-small"
            variant="tonal"
          >
            개발중
          </v-chip>
        </template>
      </v-list-item>

      <!--
        직원 관리

        점포에 소속된 직원 정보와
        직원의 역할 및 권한을 관리하기 위한 메뉴이다.

        현재는 기능을 개발하지 않았으므로
        비활성화 상태로 표시한다.
      -->
      <v-list-item
        prepend-icon="mdi-account-group-outline"
        title="직원 관리"
        disabled
      >
        <template #append>
          <v-chip
            size="x-small"
            variant="tonal"
          >
            개발중
          </v-chip>
        </template>
      </v-list-item>

      <!--
        시스템

        향후 변경 이력 등의 시스템 관리 기능을
        제공하기 위한 메뉴이다.

        현재는 기능을 개발하지 않았으므로
        비활성화 상태로 표시한다.
      -->
      <v-list-item
        prepend-icon="mdi-cog-outline"
        title="시스템"
        disabled
      >
        <template #append>
          <v-chip
            size="x-small"
            variant="tonal"
          >
            개발중
          </v-chip>
        </template>
      </v-list-item>

      <!-- 메뉴 영역과 로그아웃 영역을 구분한다. -->
      <v-divider
        class="my-2"
      />

      <!--
        로그아웃

        현재 로그인된 사용자의 세션을 종료한다.

        로그아웃 버튼을 누르면 사이드 메뉴를 즉시 닫고
        전체 화면 로딩 상태를 표시한 뒤 로그아웃을 요청한다.
      -->
      <v-list-item
        prepend-icon="mdi-logout"
        title="로그아웃"
        :disabled="isLoggingOut"
        :loading="isLoggingOut"
        @click="logout"
      />
    </v-list>
  </v-navigation-drawer>
</template>

<script setup>
import { computed, ref } from 'vue';

/**
 * 사이드 메뉴 컴포넌트 속성
 *
 * modelValue는 부모 컴포넌트가 관리하는
 * 사이드 메뉴의 열림/닫힘 상태이다.
 *
 * true이면 사이드 메뉴가 열리고
 * false이면 사이드 메뉴가 닫힌다.
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});

/**
 * 부모 컴포넌트로 전달할 이벤트
 *
 * update:modelValue:
 * 사이드 메뉴의 열림/닫힘 상태가 변경되었을 때
 * 변경된 값을 부모 컴포넌트에 전달한다.
 *
 * error:
 * 로그아웃 과정에서 오류가 발생했을 때
 * 오류 메시지를 부모 페이지에 전달한다.
 *
 * loading:
 * 로그아웃 요청의 시작과 종료 상태를
 * 부모 페이지에 전달한다.
 *
 * 부모 페이지에서는 loading 상태를 사용하여
 * 공통 전체 화면 로딩 오버레이를 표시할 수 있다.
 */
const emit = defineEmits([
  'update:modelValue',
  'error',
  'loading',
]);

/**
 * 사이드 메뉴 열림/닫힘 상태
 *
 * 부모 컴포넌트의 modelValue와
 * v-navigation-drawer를 연결한다.
 *
 * 메뉴 상태가 변경되면 update:modelValue 이벤트를 발생시켜
 * 부모 컴포넌트의 v-model 값도 함께 변경한다.
 */
const drawer = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
});

/**
 * 로그아웃 요청 진행 상태
 *
 * true이면 현재 로그아웃 요청이 진행 중이다.
 *
 * 로그아웃 요청 중에는 로그아웃 메뉴를 비활성화하여
 * 사용자가 여러 번 요청하는 것을 방지한다.
 */
const isLoggingOut = ref(false);

/**
 * 사용자 로그아웃
 *
 * 로그아웃 버튼을 누르면 먼저 사이드 메뉴를 닫고
 * 전체 화면 로딩 상태를 활성화한다.
 *
 * 로그아웃에 성공하면 로딩 상태를 유지한 채
 * 로그인 페이지로 바로 이동한다.
 *
 * 로그아웃에 실패한 경우에만 로딩 상태를 해제하고
 * 오류 메시지를 부모 페이지에 전달한다.
 */
async function logout() {
  // 로그아웃 요청 진행 상태를 활성화한다.
  isLoggingOut.value = true;

  // 로그아웃 버튼을 누르면 사이드 메뉴를 즉시 닫는다.
  drawer.value = false;

  // 부모 페이지의 전체 화면 로딩을 활성화한다.
  emit('loading', true);

  try {
    // Laravel 로그아웃 API를 호출한다.
    await window.axios.post('/tillwhite/logout');

    /**
     * 로그인 화면으로 이동
     *
     * 로그아웃 성공 후에는 로딩 상태를 해제하지 않는다.
     *
     * 현재 화면을 다시 노출하지 않고
     * 로딩 화면을 유지한 상태에서 로그인 화면으로 이동한다.
     */
    window.location.href = '/tillwhite/login';
  } catch (error) {
    /**
     * 로그아웃 실패 처리
     *
     * 로그아웃에 실패한 경우에는 현재 페이지에 계속 머물러야 하므로
     * 전체 화면 로딩 상태를 해제한다.
     */
    isLoggingOut.value = false;
    emit('loading', false);

    /**
     * 로그아웃 오류 메시지 전달
     *
     * Laravel에서 전달한 오류 메시지가 존재하면 해당 메시지를 사용하고,
     * 서버에서 별도의 메시지를 전달하지 않은 경우에는
     * 기본 로그아웃 오류 메시지를 부모 페이지에 전달한다.
     */
    emit(
      'error',
      error.response?.data?.message ??
        '로그아웃 중 오류가 발생했습니다.'
    );
  }
}
</script>

<style scoped>
/**
 * 사이드 메뉴 제목
 *
 * 기본 v-list-item 제목은 공간이 부족하면 말줄임표(...)를 사용한다.
 * 생산·폐기 관리 메뉴 이름이 잘리지 않고 전체 표시되도록 설정한다.
 */
.nav-menu-title {
  white-space: nowrap;
  overflow: visible;
  text-overflow: clip;
}
</style>