<template>
  <AppPageContainer>
    <v-card
      width="100%"
    >
      <v-card-title
        class="font-weight-black"
      >
        Till White
      </v-card-title>

      <v-card-subtitle
        class="mb-3"
      >
        베이커리 관리 시스템
      </v-card-subtitle>

      <v-card-text
        class="bg-surface-light"
      >
        <AppAlert
          v-model="errorMessage"
        />

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

        <v-btn
          :loading="isLoggingOut"
          :disabled="isLoggingOut"
          size="large"
          block
          @click="logout"
        >
          로그아웃
        </v-btn>
      </v-card-text>
    </v-card>
  </AppPageContainer>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppAlert from '../components/common/AppAlert.vue';
import AppPageContainer from '../components/layout/AppPageContainer.vue';

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
 * 사용자 정보 조회 또는 로그아웃 과정에서
 * 오류가 발생했을 때 화면에 표시할 메시지를 저장한다.
 *
 * 빈 문자열인 경우 AppAlert는 화면에 표시되지 않는다.
 */
const errorMessage = ref('');

/**
 * 로그아웃 요청 진행 상태
 *
 * 로그아웃 요청이 진행되는 동안 버튼에 로딩 상태를 표시하고
 * 중복 로그아웃 요청이 발생하지 않도록 사용한다.
 */
const isLoggingOut = ref(false);

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
 * Laravel 세션을 기준으로 현재 로그인되어 있는
 * 사용자의 점포, 부서, 역할 등의 정보를 가져온다.
 *
 * Axios를 사용하여 인증 관련 요청 방식을
 * 로그인 및 로그아웃 요청과 동일하게 유지한다.
 */
async function loadUser() {
  // 이전 오류 메시지를 초기화한다.
  errorMessage.value = '';

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
  }
}

/**
 * 메인 페이지가 처음 표시될 때
 * 현재 로그인 사용자 정보를 불러온다.
 */
onMounted(() => {
  loadUser();
});

/**
 * 사용자 로그아웃
 *
 * Laravel의 로그아웃 API를 호출하여
 * 서버에 저장된 인증 세션을 종료한다.
 *
 * Axios가 Laravel의 XSRF-TOKEN 쿠키를 사용하여
 * 현재 CSRF 토큰을 요청에 자동으로 포함한다.
 */
async function logout() {
  // 이전 오류 메시지를 초기화한다.
  errorMessage.value = '';

  try {
    // 로그아웃 요청 진행 상태를 활성화한다.
    isLoggingOut.value = true;

    /**
     * Laravel 로그아웃 API 호출
     *
     * 로그아웃 요청은 서버 상태를 변경하므로
     * POST 방식으로 전송한다.
     */
    await window.axios.post('/tillwhite/logout');

    /**
     * 로그인 화면으로 이동
     *
     * 로그아웃 과정에서 Laravel이 세션과 CSRF 토큰을
     * 새로 생성하므로 로그인 페이지를 전체 새로고침한다.
     */
    window.location.href = '/tillwhite/login';
  } catch (error) {
    /**
     * 로그아웃 실패 메시지 저장
     *
     * Laravel에서 전달한 오류 메시지가 존재하면 해당 메시지를 사용하고,
     * 예상하지 못한 오류라면 기본 오류 메시지를 표시한다.
     */
    errorMessage.value =
      error.response?.data?.message ??
      '로그아웃 중 오류가 발생했습니다.';
  } finally {
    // 로그아웃 요청이 완료되면 로딩 상태를 해제한다.
    isLoggingOut.value = false;
  }
}
</script>