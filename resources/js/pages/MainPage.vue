<template>
  <AppPageContainer>
    <!--
      전체 화면 로딩 오버레이

      현재 로그인 사용자 정보를 불러오는 동안
      화면 전체를 덮고 중앙에 로딩 상태를 표시한다.
    -->
    <AppLoadingOverlay
      :model-value="isLoadingUser"
    />

    <!--
      사이드 메뉴

      AppHeader의 햄버거 버튼으로 열고 닫는다.
    -->
    <AppNavigationDrawer
      v-model="drawer"
      @error="errorMessage = $event"
      @loading="isLoadingUser = $event"
    />

    <!--
      메인 화면

      사용자 정보 조회가 완료된 이후에만 표시하여
      헤더가 먼저 나타나고 사용자 정보가 나중에 나타나는
      화면 깜빡임을 방지한다.
    -->
    <AppPageCard
      v-if="!isLoadingUser"
    >
      <!--
        메인 페이지 공통 헤더

        로그인 페이지와 동일한 제목 및 부제목 디자인을 유지하면서
        로그인 이후 화면에서 필요한 햄버거 메뉴 버튼을 표시한다.
      -->
      <template #header>
        <AppHeader
          @menu="drawer = true"
        />
      </template>

      <!--
        공통 오류 알림

        사용자 정보 조회 또는 사이드 메뉴에서
        발생한 오류 메시지를 표시한다.
      -->
      <AppAlert
        v-model="errorMessage"
      />

      <!--
        현재 로그인 사용자 정보

        현재 로그인한 사용자의 이름, 점포,
        부서 및 역할 정보를 표시한다.
      -->
      <div
        v-if="user"
        class="mb-4"
      >
        <div>
          이름: {{ user.name }}
        </div>

        <div>
          점포: {{ user.store.name }}
        </div>

        <div>
          부서: {{ departmentNames[user.department] ?? user.department }}
        </div>

        <div>
          역할: {{ user.role.name }}
        </div>
      </div>
    </AppPageCard>
  </AppPageContainer>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppAlert from '../components/common/AppAlert.vue';
import AppLoadingOverlay from '../components/common/AppLoadingOverlay.vue';
import AppHeader from '../components/layout/AppHeader.vue';
import AppNavigationDrawer from '../components/layout/AppNavigationDrawer.vue';
import AppPageCard from '../components/layout/AppPageCard.vue';
import AppPageContainer from '../components/layout/AppPageContainer.vue';

/**
 * 사용자 정보 로딩 상태
 *
 * 메인 페이지에 처음 진입하면 true 상태로 시작한다.
 *
 * 현재 로그인 사용자 정보 조회가 완료될 때까지
 * 전체 화면 로딩 오버레이를 표시한다.
 */
const isLoadingUser = ref(true);

// 사이드 메뉴 표시 상태
const drawer = ref(false);

/**
 * 현재 로그인 사용자 정보
 *
 * Laravel의 /tillwhite/auth/me API에서 전달받은
 * 현재 로그인 사용자의 정보를 저장한다.
 *
 * 초기에는 아직 사용자 정보를 불러오지 않았으므로
 * null 상태로 시작한다.
 */
const user = ref(null);

/**
 * 메인 페이지 오류 메시지
 *
 * 사용자 정보 조회 또는 사이드 메뉴에서
 * 오류가 발생했을 때 화면에 표시할 메시지를 저장한다.
 *
 * 빈 문자열인 경우 AppAlert는 화면에 표시되지 않는다.
 */
const errorMessage = ref('');

/**
 * 부서 코드별 화면 표시 이름
 *
 * DB에는 변경하기 쉬운 영문 코드를 저장하고
 * 사용자 화면에서는 이해하기 쉬운 한글 이름으로 표시한다.
 */
const departmentNames = {
  kitchen: '주방',
  hall: '홀',
  operations: '운영진',
};

/**
 * 현재 로그인 사용자 조회
 *
 * 메인 페이지가 표시될 때 Laravel 세션을 기준으로
 * 현재 로그인 사용자의 정보를 조회한다.
 *
 * 사용자 정보 조회가 완료되기 전까지는
 * 전체 화면 로딩 오버레이를 유지한다.
 */
async function loadUser() {
  // 이전 오류 메시지를 초기화한다.
  errorMessage.value = '';

  // 사용자 정보 로딩 상태를 활성화한다.
  isLoadingUser.value = true;

  try {
    /**
     * 현재 로그인 사용자 API 호출
     *
     * Laravel 세션에 인증된 사용자가 존재하면
     * 현재 사용자 정보를 JSON 형식으로 반환한다.
     */
    const response = await window.axios.get('/tillwhite/auth/me');

    // 현재 로그인 사용자 정보를 Vue 상태에 저장한다.
    user.value = response.data.user;
  } catch (error) {
    /**
     * 사용자 정보 조회 실패 처리
     *
     * Laravel에서 전달한 오류 메시지가 존재하면 해당 메시지를 사용하고,
     * 예상하지 못한 오류라면 기본 오류 메시지를 표시한다.
     */
    errorMessage.value =
      error.response?.data?.message ??
      '사용자 정보를 불러오는 중 오류가 발생했습니다.';
  } finally {
    /**
     * 사용자 정보 조회 완료
     *
     * 성공 또는 실패 여부와 관계없이
     * 서버 요청이 끝나면 전체 화면 로딩을 종료한다.
     */
    isLoadingUser.value = false;
  }
}

/**
 * 메인 페이지 마운트
 *
 * 페이지가 처음 마운트되면
 * 현재 로그인 사용자 정보를 조회한다.
 */
onMounted(() => {
  loadUser();
});
</script>