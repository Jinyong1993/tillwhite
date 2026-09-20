<template>
  <AppPageContainer>
    <!--
      전체 화면 로딩 오버레이

      메인 페이지 최초 진입 시 사용자 정보를 불러오거나
      로그아웃 요청이 진행되는 동안 표시한다.

      조회 실패 후 다시 시도하는 경우에는
      전체 화면 로딩을 사용하지 않고
      다시 시도 버튼 자체에 로딩 상태를 표시한다.
    -->
    <AppLoadingOverlay
      :model-value="isLoadingUser"
    />

    <!--
      사이드 메뉴

      AppHeader의 햄버거 버튼으로 열고 닫는다.

      로그아웃 요청이 진행되는 동안에는
      전체 화면 로딩 상태를 표시한다.
    -->
    <AppNavigationDrawer
      v-model="drawer"
      @error="errorMessage = $event"
      @loading="isLoadingUser = $event"
    />

    <!--
      메인 화면

      최초 사용자 정보 조회가 완료된 이후에만 표시하여
      헤더와 페이지 내용이 한 번에 나타나도록 한다.
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
        공통 일반 오류 알림

        로그아웃 등 페이지의 핵심 데이터 조회와 관계없는
        작업에서 오류가 발생했을 때 사용한다.
      -->
      <AppAlert
        v-model="errorMessage"
      />

      <!--
        로그인 사용자 정보

        사용자 정보 조회에 성공한 경우
        UserInfoCard를 통해 사용자 정보를 표시한다.
      -->
      <UserInfoCard
        v-if="user"
        :user="user"
      />

      <!--
        공통 조회 실패 상태

        데이터 조회에 실패하거나
        정상적인 데이터를 전달받지 못한 경우 표시한다.

        오류 문구와 디자인은 AppErrorState에서
        공통으로 관리한다.

        다시 시도 버튼을 누르면 전체 화면 로딩 대신
        버튼 자체에 로딩 상태를 표시하면서 재조회한다.
      -->
      <AppErrorState
        v-else-if="hasLoadError"
        title="사용자 정보를 불러오지 못했습니다."
        message="사용자 정보를 불러오는 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요."
        :loading="isRetryingUser"
        @retry="retryLoadUser"
      />
    </AppPageCard>
  </AppPageContainer>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppAlert from '../components/common/AppAlert.vue';
import AppErrorState from '../components/common/AppErrorState.vue';
import AppLoadingOverlay from '../components/common/AppLoadingOverlay.vue';
import AppHeader from '../components/layout/AppHeader.vue';
import AppNavigationDrawer from '../components/layout/AppNavigationDrawer.vue';
import AppPageCard from '../components/layout/AppPageCard.vue';
import AppPageContainer from '../components/layout/AppPageContainer.vue';
import UserInfoCard from '../components/user/UserInfoCard.vue';

/**
 * 전체 화면 로딩 상태
 *
 * 메인 페이지 최초 진입 시 사용자 정보를 조회하거나
 * 로그아웃 요청이 진행되는 동안 사용한다.
 *
 * 다시 시도 요청에서는 사용하지 않는다.
 */
const isLoadingUser = ref(true);

/**
 * 사용자 정보 재조회 상태
 *
 * 최초 조회 실패 후 사용자가 다시 시도 버튼을 눌렀을 때
 * 버튼 자체의 로딩 상태를 표시하기 위해 사용한다.
 */
const isRetryingUser = ref(false);

// 사이드 메뉴 표시 상태
const drawer = ref(false);

/**
 * 현재 로그인 사용자 정보
 *
 * Laravel의 /tillwhite/auth/me API에서 전달받은
 * 현재 로그인 사용자의 정보를 저장한다.
 */
const user = ref(null);

/**
 * 사용자 정보 조회 실패 상태
 *
 * true이면 사용자 정보 요청에 실패했거나
 * 정상적인 사용자 데이터를 전달받지 못한 상태임을 의미한다.
 *
 * 조회 실패 화면 자체의 문구와 디자인은
 * 공통 AppErrorState 컴포넌트에서 관리한다.
 */
const hasLoadError = ref(false);

/**
 * 일반 오류 메시지
 *
 * 로그아웃 등 페이지의 핵심 데이터 조회와 관계없는
 * 작업에서 오류가 발생했을 때 사용한다.
 */
const errorMessage = ref('');

/**
 * 사용자 정보 요청
 *
 * Laravel 세션을 기준으로 현재 로그인 사용자의
 * 정보를 서버에서 조회한다.
 *
 * HTTP 요청이 성공했더라도 응답에 사용자 정보가 없으면
 * 정상적인 조회 결과로 처리하지 않고 오류를 발생시킨다.
 *
 * 최초 조회와 다시 시도에서 동일한 API 요청을
 * 중복 작성하지 않도록 공통 함수로 관리한다.
 */
async function fetchUser() {
  /**
   * 현재 로그인 사용자 API 호출
   *
   * 현재는 공통 조회 실패 화면을 테스트하기 위해
   * 존재하지 않는 테스트 주소를 임시로 사용한다.
   *
   * 테스트가 완료되면 /tillwhite/auth/me로 되돌린다.
   */
  const response = await window.axios.get('/tillwhite/auth/me');
  // const response = await window.axios.get('/tillwhite/auth/me-test');

  /**
   * 사용자 정보 응답 검증
   *
   * 서버 요청 자체가 성공했더라도
   * 응답에 user 데이터가 존재하지 않으면
   * 정상적인 사용자 정보 조회로 처리하지 않는다.
   */
  if (!response.data?.user) {
    throw new Error('사용자 정보가 존재하지 않습니다.');
  }

  // 현재 로그인 사용자 정보를 Vue 상태에 저장한다.
  user.value = response.data.user;
}

/**
 * 최초 사용자 정보 조회
 *
 * 메인 페이지에 처음 진입했을 때 실행한다.
 *
 * 최초 조회 중에는 페이지 내용을 먼저 노출하지 않고
 * 전체 화면 로딩 오버레이를 표시한다.
 */
async function loadUser() {
  // 이전 조회 실패 상태를 초기화한다.
  hasLoadError.value = false;

  // 전체 화면 로딩 상태를 활성화한다.
  isLoadingUser.value = true;

  try {
    // 현재 로그인 사용자 정보를 조회한다.
    await fetchUser();
  } catch (error) {
    // 이전 사용자 정보가 남아있지 않도록 초기화한다.
    user.value = null;

    // 사용자 정보 조회 실패 상태를 활성화한다.
    hasLoadError.value = true;
  } finally {
    // 최초 사용자 정보 조회가 끝나면 전체 화면 로딩을 종료한다.
    isLoadingUser.value = false;
  }
}

/**
 * 사용자 정보 다시 조회
 *
 * 최초 조회에 실패한 이후 사용자가
 * 다시 시도 버튼을 눌렀을 때 실행한다.
 *
 * 전체 화면 로딩 오버레이는 사용하지 않고
 * 다시 시도 버튼 자체에 로딩 상태를 표시한다.
 */
async function retryLoadUser() {
  // 중복 재조회 요청을 방지한다.
  if (isRetryingUser.value) {
    return;
  }

  // 다시 시도 버튼의 로딩 상태를 활성화한다.
  isRetryingUser.value = true;

  try {
    // 현재 로그인 사용자 정보를 다시 조회한다.
    await fetchUser();

    // 재조회에 성공하면 조회 실패 상태를 해제한다.
    hasLoadError.value = false;
  } catch (error) {
    // 재조회에 실패하면 사용자 정보를 비운다.
    user.value = null;

    // 조회 실패 화면을 계속 유지한다.
    hasLoadError.value = true;
  } finally {
    // 재조회가 완료되면 버튼 로딩 상태를 해제한다.
    isRetryingUser.value = false;
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