<template>
  <AppPageContainer>
    <v-card
      class="mx-auto"
      width="100%"
      max-width="400"
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
          v-model="loginError"
        />

        <v-form
          ref="loginForm"
          @submit.prevent="login"
        >
          <div class="d-flex flex-column ga-2">
            <v-text-field
              v-model="form.id"
              :rules="rules.id"
              label="아이디"
              variant="outlined"
            />

            <v-text-field
              v-model="form.password"
              :rules="rules.password"
              :type="showPassword ? 'text' : 'password'"
              :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
              label="비밀번호"
              variant="outlined"
              @click:append-inner="showPassword = !showPassword"
            />

            <v-btn
              type="submit"
              :loading="isLoggingIn"
              :disabled="isLoggingIn"
              size="large"
              block
            >
              로그인
            </v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </AppPageContainer>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import AppAlert from '../../components/common/AppAlert.vue';
import AppPageContainer from '../../components/layout/AppPageContainer.vue';

/**
 * 로그인 실패 메시지
 *
 * Laravel 로그인 요청이 실패했을 때
 * 서버에서 전달받은 오류 메시지를 저장한다.
 *
 * 빈 문자열인 경우 AppAlert는 화면에 표시되지 않는다.
 */
const loginError = ref('');

// 로그인 요청 진행 상태
const isLoggingIn = ref(false);

// 비밀번호 표시 여부
const showPassword = ref(false);

/**
 * Vue Router 사용
 *
 * 로그인이 정상적으로 완료된 이후
 * 메인 페이지로 이동하기 위해 사용한다.
 */
const router = useRouter();

/**
 * 로그인 Form
 *
 * Vuetify의 입력값 검증을 실행하기 위해
 * v-form 컴포넌트의 참조를 저장한다.
 */
const loginForm = ref(null);

/**
 * 로그인 입력값
 *
 * 사용자가 입력한 아이디와 비밀번호를 저장한다.
 */
const form = ref({
  id: '',
  password: '',
});

/**
 * 로그인 입력값 검증 규칙
 *
 * 아이디와 비밀번호가 입력되었는지
 * Laravel 서버에 요청하기 전에 화면에서 먼저 검사한다.
 */
const rules = {
  id: [
    value => !!value?.trim() || '아이디를 입력해주세요.',
  ],
  password: [
    value => !!value || '비밀번호를 입력해주세요.',
  ],
};

/**
 * 사용자 로그인
 *
 * 입력값 검증이 완료되면 Laravel 로그인 API를 호출한다.
 *
 * Axios가 Laravel의 XSRF-TOKEN 쿠키를 사용하여
 * 현재 CSRF 토큰을 요청에 자동으로 포함한다.
 */
async function login() {
  /**
   * 이전 로그인 실패 메시지 초기화
   *
   * 사용자가 다시 로그인을 시도할 때
   * 이전 요청에서 발생한 오류 메시지를 먼저 제거한다.
   */
  loginError.value = '';

  // 로그인 입력값 검증
  const { valid } = await loginForm.value.validate();

  // 입력값 검증 실패 시 로그인 요청을 보내지 않는다.
  if (!valid) {
    return;
  }

  try {
    isLoggingIn.value = true;
    
    /**
     * Laravel 로그인 API 호출
     *
     * 아이디 앞뒤의 불필요한 공백은 제거한 뒤
     * 비밀번호와 함께 서버로 전달한다.
     */
    await window.axios.post('/tillwhite/login', {
      ...form.value,
      id: form.value.id.trim(),
    });

    /**
     * 메인 페이지로 이동
     *
     * 로그인 성공 후에는 Vue Router를 사용하여
     * 페이지 전체를 새로고침하지 않고 메인 화면으로 이동한다.
     */
    await router.push({
      name: 'main',
    });
  } catch (error) {
    /**
     * 로그인 실패 메시지 저장
     *
     * Laravel에서 전달한 오류 메시지가 존재하면 해당 메시지를 사용하고,
     * 예상하지 못한 오류라면 기본 오류 메시지를 표시한다.
     */
    loginError.value =
      error.response?.data?.message ?? '로그인 중 오류가 발생했습니다.';
  } finally {
    // 로그인 요청이 완료되면 로딩 상태를 해제한다.
    isLoggingIn.value = false;
  }
}
</script>