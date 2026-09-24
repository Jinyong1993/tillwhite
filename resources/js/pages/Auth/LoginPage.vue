<template>
  <AppPageContainer>
    <!--
      Till White 로그인 카드

      전체 시스템의 기준 디자인인 AppPageCard를 사용한다.

      카드 너비, 중앙 정렬, 시스템 이름,
      기본 부제목과 본문 배경은 AppPageCard에서 관리한다.

      subtitle을 별도로 전달하지 않으므로
      기본값인 '베이커리 관리 시스템'이 표시된다.
    -->
    <AppPageCard>
      <!--
        로그인 오류 알림

        로그인 요청이 실패했을 때
        서버에서 전달받은 오류 메시지를 표시한다.

        loginError가 빈 문자열이면 표시되지 않는다.
      -->
      <AppAlert v-model="loginError" />

      <!--
        로그인 입력 Form

        아이디와 비밀번호를 입력받고
        Vuetify의 입력값 검증을 통과한 경우에만
        Laravel 로그인 API를 호출한다.
      -->
      <v-form
        ref="loginForm"
        @submit.prevent="login"
      >
        <div class="d-flex flex-column ga-2">
          <!--
            로그인 아이디

            Till White에서는 users.employee_code를
            로그인 아이디로 사용한다.
          -->
          <v-text-field
            v-model="form.id"
            :rules="rules.id"
            label="아이디"
            variant="outlined"
          />

          <!--
            로그인 비밀번호

            기본적으로 비밀번호를 숨기고,
            오른쪽 아이콘을 누르면 표시 여부를 전환한다.
          -->
          <v-text-field
            v-model="form.password"
            :rules="rules.password"
            :type="showPassword ? 'text' : 'password'"
            :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
            label="비밀번호"
            variant="outlined"
            @click:append-inner="showPassword = !showPassword"
          />

          <!--
            로그인 버튼

            로그인 요청이 진행되는 동안
            로딩 상태를 표시하고 버튼을 비활성화하여
            중복 로그인 요청을 방지한다.
          -->
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
    </AppPageCard>
  </AppPageContainer>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

import AppAlert from '../../components/common/AppAlert.vue';
import AppPageCard from '../../components/layout/AppPageCard.vue';
import AppPageContainer from '../../components/layout/AppPageContainer.vue';

/**
 * 로그인 실패 메시지
 *
 * Laravel 로그인 요청이 실패했을 때
 * 서버에서 전달받은 오류 메시지를 저장한다.
 *
 * 빈 문자열이면 AppAlert가 표시되지 않는다.
 */
const loginError = ref('');

/**
 * 로그인 요청 진행 상태
 *
 * true인 동안 로그인 버튼에 로딩 상태를 표시하고
 * 버튼을 비활성화하여 중복 요청을 방지한다.
 */
const isLoggingIn = ref(false);

/**
 * 비밀번호 표시 여부
 *
 * false:
 * - 비밀번호를 숨긴다.
 *
 * true:
 * - 입력한 비밀번호를 화면에 표시한다.
 */
const showPassword = ref(false);

/**
 * Vue Router
 *
 * 로그인 성공 후 메인 페이지로 이동하기 위해 사용한다.
 */
const router = useRouter();

/**
 * 로그인 Form 참조
 *
 * Vuetify v-form의 validate()를 호출하여
 * 로그인 입력값을 검사하기 위해 사용한다.
 */
const loginForm = ref(null);

/**
 * 로그인 입력값
 *
 * id:
 * - 사용자가 입력하는 로그인 아이디
 * - 서버에서는 users.employee_code와 비교한다.
 *
 * password:
 * - 사용자가 입력하는 로그인 비밀번호
 */
const form = ref({
  id: '',
  password: '',
});

/**
 * 로그인 입력값 검증 규칙
 *
 * Laravel 서버에 로그인 요청을 보내기 전에
 * 필수 입력값이 존재하는지 화면에서 먼저 검사한다.
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
 * 처리 순서:
 *
 * 1. 기존 오류 메시지 초기화
 * 2. 입력값 검증
 * 3. 로그인 로딩 상태 활성화
 * 4. Laravel 로그인 API 호출
 * 5. 로그인 성공 시 메인 페이지 이동
 * 6. 실패 시 오류 메시지 표시
 * 7. 로그인 로딩 상태 해제
 */
async function login() {
  // 이전 로그인 시도에서 발생한 오류 메시지를 제거한다.
  loginError.value = '';

  // 아이디와 비밀번호 입력값을 검사한다.
  const { valid } = await loginForm.value.validate();

  // 입력값 검증에 실패하면 서버에 요청하지 않는다.
  if (!valid) {
    return;
  }

  try {
    // 로그인 요청 진행 상태를 활성화한다.
    isLoggingIn.value = true;

    /**
     * Laravel 로그인 API 호출
     *
     * 아이디의 앞뒤 공백을 제거한 뒤
     * 비밀번호와 함께 서버로 전달한다.
     *
     * window.axios는 bootstrap.js에서
     * Laravel Session 및 XSRF 처리를 위한
     * 공통 설정이 적용되어 있다.
     */
    await window.axios.post('/tillwhite/login', {
      ...form.value,
      id: form.value.id.trim(),
    });

    /**
     * 로그인 성공
     *
     * Vue Router를 사용하여 페이지 전체를 새로고침하지 않고
     * Till White 메인 화면으로 이동한다.
     */
    await router.push({
      name: 'main',
    });
  } catch (error) {
    /**
     * 로그인 실패
     *
     * 서버가 message를 반환했다면 해당 내용을 표시한다.
     *
     * 서버 메시지가 없는 예상하지 못한 오류라면
     * 기본 오류 메시지를 표시한다.
     */
    loginError.value =
      error.response?.data?.message ??
      '로그인 중 오류가 발생했습니다.';
  } finally {
    // 성공 또는 실패와 관계없이 로그인 요청 상태를 종료한다.
    isLoggingIn.value = false;
  }
}
</script>