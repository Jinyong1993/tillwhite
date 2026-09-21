<template>
  <!--
    로그인 이후 각 업무 페이지에서 공통으로 사용하는 전체 화면 영역입니다.
    페이지의 최대 너비와 기본 여백을 담당합니다.
  -->
  <AppPageContainer>
    <!-- 사용자 정보 등을 불러오는 동안 표시하는 로딩 화면 -->
    <AppLoadingOverlay :model-value="loading" />

    <!--
      좌측 또는 모바일 내비게이션 메뉴입니다.
      drawer 값으로 메뉴의 열림/닫힘 상태를 관리합니다.
    -->
    <AppNavigationDrawer
      v-model="drawer"
      @error="errorMessage = $event"
      @loading="loading = $event"
    />

    <!--
      사용자 정보 로딩이 완료된 후 실제 페이지를 표시합니다.
      현재 페이지 제목을 카드의 부제목으로 전달합니다.
    -->
    <AppPageCard
      v-if="!loading"
      :subtitle="title"
    >
      <!-- 페이지 공통 상단 헤더 -->
      <template #header>
        <AppHeader
          :subtitle="title"
          @menu="drawer = true"
        />
      </template>

      <!-- API 요청이나 사용자 정보 조회 중 발생한 오류 메시지 -->
      <AppAlert v-model="errorMessage" />

      <!--
        각 페이지의 실제 내용을 표시하는 영역입니다.

        user     : 현재 로그인한 사용자 정보
        can      : 특정 권한 보유 여부를 확인하는 함수
        setError : 하위 페이지에서 공통 오류 메시지를 설정하는 함수
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
 * 각 페이지에서 전달받는 속성입니다.
 *
 * title : 현재 페이지의 제목
 */
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
});

// 내비게이션 메뉴 열림/닫힘 상태
const drawer = ref(false);

// 사용자 정보 로딩 상태
const loading = ref(true);

// 화면에 표시할 공통 오류 메시지
const errorMessage = ref('');

/**
 * 로그인 세션 관련 공통 기능입니다.
 *
 * user     : 현재 로그인한 사용자
 * loadUser : 서버에서 현재 사용자 정보를 조회하는 함수
 * can      : 현재 사용자의 권한을 확인하는 함수
 */
const {
  user,
  loadUser,
  can,
} = useSession();

// 하위 페이지에서 전달한 오류 메시지를 공통 알림 영역에 설정합니다.
function setError(message) {
  errorMessage.value = message;
}

/**
 * AppShell이 화면에 처음 표시될 때
 * 현재 로그인한 사용자 정보를 서버에서 불러옵니다.
 */
onMounted(async () => {
  try {
    await loadUser();
  } catch (e) {
    errorMessage.value = '사용자 정보를 불러오지 못했습니다.';
  } finally {
    // 성공/실패 여부와 관계없이 사용자 정보 조회가 끝나면 로딩을 종료합니다.
    loading.value = false;
  }
});
</script>