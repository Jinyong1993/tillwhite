<template>
  <!--
    Till White 공통 업무 화면 레이아웃

    로그인 이후 각 업무 페이지에서 공통으로 사용하는
    화면 구조를 관리합니다.

    주요 역할:
    - 페이지 전체 영역 관리
    - 로그인 사용자 정보 조회
    - 사이드 메뉴 열림/닫힘 관리
    - 공통 페이지 카드 표시
    - 공통 헤더 표시
    - 공통 오류 메시지 표시
    - 하위 페이지에 사용자 및 권한 정보 전달
    - 로그인 사용자 정보 준비 상태 전달

    전체 화면 로딩 자체는 AppShell에서 관리하지 않습니다.

    화면 이동과 페이지 최초 데이터 조회에 사용하는
    공통 전체 화면 로딩은 다음 구조에서 관리합니다.

    - App.vue
    - useAppLoading
    - AppLoadingOverlay

    따라서 업무 페이지가 변경되어
    AppShell이 제거되더라도
    전체 화면 로딩 상태는 유지됩니다.
  -->
  <AppPageContainer>
    <!--
      공통 내비게이션 메뉴

      drawer를 통해 메뉴의 열림/닫힘 상태를 관리합니다.

      메뉴 내부에서 오류가 발생하면
      error 이벤트를 통해 공통 오류 알림에 전달합니다.

      일반 업무 화면 이동의 공통 로딩 시작은
      Vue Router 전역 페이지 이동 가드에서 처리합니다.
    -->
    <AppNavigationDrawer
      v-model="drawer"
      @error="setError"
    />

    <!--
      공통 페이지 카드

      각 업무 페이지의 실제 화면을
      동일한 카드 구조 안에 표시합니다.

      전체 화면 로딩 오버레이는
      App.vue에서 애플리케이션 전체를 기준으로 표시하므로
      AppShell에서는 별도의 로딩 화면을 표시하지 않습니다.
    -->
    <AppPageCard :subtitle="title">
      <!--
        공통 페이지 헤더

        현재 페이지 이름을 표시하고,
        메뉴 버튼을 누르면 내비게이션 메뉴를 엽니다.
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
        오류 메시지를 카드 상단에 표시합니다.
      -->
      <AppAlert v-model="errorMessage" />

      <!--
        각 업무 페이지의 실제 내용

        AppShell을 사용하는 하위 페이지에
        공통으로 필요한 사용자 및 권한 관련 기능을 전달합니다.

        user:
        - 현재 로그인한 사용자 정보

        can:
        - 특정 권한(Permission) 보유 여부를 확인하는 함수

        sessionReady:
        - AppShell의 최초 사용자 정보 조회가 끝났는지 여부
        - 성공/실패와 관계없이 최초 조회 처리가 끝나면 true

        setError:
        - 하위 페이지에서 AppShell의
          공통 오류 메시지를 설정하는 함수

        전체 화면 로딩 기능은 slot으로 전달하지 않습니다.

        각 업무 페이지에서는
        자신의 최초 데이터 준비 상태와
        AppShell의 사용자 정보 준비 상태(sessionReady)를 함께 확인한 뒤
        completePageLoading()을 호출할 수 있습니다.

        setError는 onMounted()처럼
        slot 범위 밖에서 발생한 오류에서도 사용할 수 있도록
        defineExpose()를 통해서도 공개합니다.
      -->
      <slot
        :user="user"
        :can="can"
        :session-ready="sessionReady"
        :set-error="setError"
      />
    </AppPageCard>
  </AppPageContainer>
</template>

<script setup>
import {
  onMounted,
  ref,
} from 'vue';

import { useSession } from '../../composables/useSession';

import AppAlert from '../common/AppAlert.vue';
import AppHeader from './AppHeader.vue';
import AppNavigationDrawer from './AppNavigationDrawer.vue';
import AppPageCard from './AppPageCard.vue';
import AppPageContainer from './AppPageContainer.vue';

/**
 * AppShell 속성
 *
 * title:
 * - 현재 업무 페이지의 이름
 * - AppPageCard와 AppHeader에 전달하여 화면에 표시합니다.
 */
defineProps({
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
 * errorMessage:
 * - AppShell에서 표시할 공통 오류 메시지
 *
 * sessionReady:
 * - AppShell의 최초 로그인 사용자 조회 완료 여부
 * - 사용자 조회 성공/실패와 관계없이
 *   최초 요청 처리가 끝나면 true
 *
 * 전체 화면 로딩 상태는
 * AppShell에서 별도로 가지고 있지 않습니다.
 */
const drawer = ref(false);
const errorMessage = ref('');
const sessionReady = ref(false);

/**
 * 로그인 세션(Session) 공통 기능
 *
 * user:
 * - 현재 로그인한 사용자
 *
 * loadUser:
 * - Laravel 서버에서 현재 로그인 사용자 정보를 조회
 *
 * can:
 * - 현재 사용자가 특정 권한(Permission)을 가지고 있는지 확인
 */
const {
  user,
  loadUser,
  can,
} = useSession();

/**
 * 공통 오류 메시지를 설정합니다.
 *
 * 하위 업무 페이지에서 오류가 발생했을 때
 * AppShell의 공통 오류 알림(AppAlert)에 표시합니다.
 *
 * slot을 통해 하위 컴포넌트에 전달하며,
 * onMounted()처럼 slot 범위 밖에서 실행되는 코드에서도
 * 사용할 수 있도록 defineExpose()로 공개합니다.
 */
function setError(message) {
  errorMessage.value = message;
}

/**
 * 하위 업무 페이지에서 사용할 수 있도록
 * AppShell의 공통 기능을 공개합니다.
 *
 * setError:
 * - 페이지 최초 조회처럼 slot 범위 밖에서 발생한 오류를
 *   AppShell의 공통 오류 알림에 표시
 *
 * sessionReady:
 * - AppShell의 로그인 사용자 정보가
 *   준비되었는지 확인할 때 사용
 *
 * 페이지 로딩 기능은 AppShell에서 공개하지 않습니다.
 *
 * 전체 화면 로딩은
 * 애플리케이션 공통 로딩(useAppLoading)에서 관리합니다.
 */
defineExpose({
  setError,
  sessionReady,
});

/**
 * AppShell 초기화
 *
 * AppShell이 처음 화면에 표시되면
 * 현재 로그인한 사용자 정보를 불러옵니다.
 *
 * 사용자 정보 조회에 실패하면
 * 공통 오류 메시지를 표시합니다.
 *
 * 성공/실패와 관계없이 최초 사용자 조회가 끝나면
 * sessionReady를 true로 변경합니다.
 *
 * 여기에서는 공통 전체 화면 로딩을 직접 종료하지 않습니다.
 *
 * 이유:
 * AppShell의 사용자 정보 조회가 끝났더라도
 * 실제 업무 페이지의 최초 데이터 조회가
 * 아직 진행 중일 수 있기 때문입니다.
 *
 * 최종적인 페이지 준비 완료 여부는
 * 각 업무 페이지에서 자신의 데이터 준비 상태와
 * sessionReady를 함께 확인하여 결정합니다.
 */
onMounted(async () => {
  try {
    await loadUser();
  } catch (error) {
    errorMessage.value =
      '사용자 정보를 불러오지 못했습니다.';
  } finally {
    sessionReady.value = true;
  }
});
</script>