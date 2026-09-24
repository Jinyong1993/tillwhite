<template>
  <!--
    Till White 공통 업무 화면 레이아웃

    로그인 이후 각 업무 페이지에서 공통으로 사용하는
    전체 화면 구조를 관리한다.

    주요 역할:
    - 페이지 전체 영역 관리
    - 로그인 사용자 정보 조회
    - 전체 화면 로딩 상태 표시
    - 사이드 메뉴 열림/닫힘 관리
    - 공통 페이지 카드 표시
    - 공통 헤더 표시
    - 공통 오류 메시지 표시
    - 하위 페이지에 사용자 및 권한 정보 전달
  -->
  <AppPageContainer>
    <!--
      전체 화면 로딩

      로그인 사용자 정보를 불러오거나
      공통 메뉴에서 로딩 상태를 전달받은 동안 표시한다.
    -->
    <AppLoadingOverlay :model-value="loading" />

    <!--
      공통 내비게이션 메뉴

      drawer를 통해 메뉴의 열림/닫힘 상태를 관리한다.

      메뉴 내부에서 오류가 발생하면 error 이벤트로 전달받고,
      로딩 상태가 변경되면 loading 이벤트로 전달받는다.
    -->
    <AppNavigationDrawer
      v-model="drawer"
      @error="errorMessage = $event"
      @loading="loading = $event"
    />

    <!--
      공통 페이지 카드

      초기 사용자 정보 조회가 완료된 이후에 표시한다.

      현재 페이지의 title을 subtitle로 전달하여
      페이지 이름을 공통 헤더에 표시할 수 있도록 한다.
    -->
    <AppPageCard
      v-if="!loading"
      :subtitle="title"
    >
      <!--
        공통 페이지 헤더

        현재 페이지 이름을 표시하고,
        메뉴 버튼을 누르면 내비게이션 메뉴를 연다.
      -->
      <template #header>
        <AppHeader
          :subtitle="title"
          @menu="drawer = true"
        />
      </template>

      <!--
        공통 오류 알림

        사용자 정보 조회 또는 하위 페이지에서 전달된
        오류 메시지를 카드 상단에 표시한다.
      -->
      <AppAlert v-model="errorMessage" />

      <!--
        각 업무 페이지의 실제 내용

        AppShell을 사용하는 하위 페이지에
        공통으로 필요한 사용자 및 권한 관련 기능을 전달한다.

        user:
        - 현재 로그인한 사용자 정보

        can:
        - 특정 Permission 보유 여부를 확인하는 함수

        setError:
        - 하위 페이지에서 AppShell의
          공통 오류 메시지를 설정하는 함수
      -->
      <slot
        :user="user"
        :can="can"
        :set-error="setError"
      />
    </AppPageCard>
  </AppPageContainer>
</template>

<script setup>
import { onMounted, ref } from 'vue';

import { useSession } from '../../composables/useSession';

import AppAlert from '../common/AppAlert.vue';
import AppLoadingOverlay from '../common/AppLoadingOverlay.vue';
import AppHeader from './AppHeader.vue';
import AppNavigationDrawer from './AppNavigationDrawer.vue';
import AppPageCard from './AppPageCard.vue';
import AppPageContainer from './AppPageContainer.vue';

/**
 * AppShell 속성
 *
 * title:
 * - 현재 업무 페이지의 이름
 * - AppPageCard와 AppHeader에 전달하여 화면에 표시한다.
 */
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
});

/**
 * 공통 화면 상태
 *
 * drawer:
 * - 내비게이션 메뉴의 열림/닫힘 상태
 *
 * loading:
 * - 공통 로딩 상태
 * - true인 동안 전체 화면 로딩 오버레이를 표시한다.
 *
 * errorMessage:
 * - AppShell에서 표시할 공통 오류 메시지
 */
const drawer = ref(false);
const loading = ref(true);
const errorMessage = ref('');

/**
 * 로그인 Session 공통 기능
 *
 * user:
 * - 현재 로그인한 사용자
 *
 * loadUser:
 * - 서버에서 현재 로그인 사용자 정보를 조회한다.
 *
 * can:
 * - 현재 사용자가 특정 Permission을 가지고 있는지 확인한다.
 */
const {
  user,
  loadUser,
  can,
} = useSession();

/**
 * 공통 오류 메시지 설정
 *
 * 하위 업무 페이지에서 오류가 발생했을 때
 * AppShell의 AppAlert에 표시할 메시지를 설정한다.
 */
function setError(message) {
  errorMessage.value = message;
}

/**
 * AppShell 초기화
 *
 * AppShell이 처음 화면에 표시되면
 * 현재 로그인한 사용자 정보를 불러온다.
 *
 * 사용자 정보 조회에 실패하면
 * 공통 오류 메시지를 표시한다.
 *
 * 성공 또는 실패 여부와 관계없이
 * 요청이 끝나면 초기 로딩 상태를 종료한다.
 */
onMounted(async () => {
  try {
    await loadUser();
  } catch (error) {
    errorMessage.value = '사용자 정보를 불러오지 못했습니다.';
  } finally {
    loading.value = false;
  }
});
</script>