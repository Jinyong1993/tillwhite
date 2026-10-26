<template>
  <AppPageContainer>
    <AppPageCard>
      <!-- 로그인 오류 안내 -->
      <AppAlert v-model="loginError" />

      <!-- 로그인 폼 -->
      <v-form
        ref="loginForm"
        @submit.prevent="login"
      >
        <div class="d-flex flex-column ga-2">
          <!-- 사번 -->
          <v-text-field
            v-model="form.id"
            :rules="rules.id"
            label="사원번호"
            variant="outlined"
            class="app-input"
            autocomplete="username"
            :disabled="isLoggingIn"
          />

          <!-- 비밀번호 -->
          <v-text-field
            v-model="form.password"
            :rules="rules.password"
            :type="showPassword ? 'text' : 'password'"
            :append-inner-icon="
              showPassword ? 'mdi-eye-off' : 'mdi-eye'
            "
            label="비밀번호"
            variant="outlined"
            class="app-input"
            autocomplete="current-password"
            :disabled="isLoggingIn"
            @click:append-inner="
              showPassword = !showPassword
            "
          />

          <!-- 로그인 -->
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
import {
  ref,
  watch,
} from 'vue';

import {
  useRoute,
  useRouter,
} from 'vue-router';

import AppAlert from '../../components/common/AppAlert.vue';
import AppPageCard from '../../components/layout/AppPageCard.vue';
import AppPageContainer from '../../components/layout/AppPageContainer.vue';

import { useSession } from '../../composables/useSession';

const route = useRoute();
const router = useRouter();

const {
  clear,
  setUser,
} = useSession();

/**
 * 로그인 오류 메시지
 */
const loginError = ref(
  route.query.sessionExpired === '1'
    ? '로그인 세션이 만료되었습니다. 다시 로그인해주세요.'
    : '',
);

/**
 * 동일 LoginPage가 재사용되는 경우에도
 * 세션 만료 안내를 갱신합니다.
 */
watch(
  () => route.query.sessionExpired,
  (expired) => {
    if (expired === '1') {
      loginError.value =
        '로그인 세션이 만료되었습니다. 다시 로그인해주세요.';
    }
  },
);

/**
 * 로그인 중복 요청 방지
 */
const isLoggingIn = ref(false);

/**
 * 비밀번호 표시 여부
 */
const showPassword = ref(false);

/**
 * 로그인 폼
 */
const loginForm = ref(null);

/**
 * 로그인 입력값
 *
 * id = employee_code
 */
const form = ref({
  id: '',
  password: '',
});

/**
 * 입력값 검증
 */
const rules = {
  id: [
    (value) =>
      !!value?.trim() || '아이디를 입력해주세요.',
  ],

  password: [
    (value) =>
      !!value || '비밀번호를 입력해주세요.',
  ],
};

/**
 * 최신 Laravel CSRF 토큰 조회
 *
 * 로그인하지 않은 사용자도 접근 가능한
 * GET /tillwhite/auth/csrf를 이용합니다.
 *
 * Axios에서 기존 세션 쿠키를 전달하고,
 * 응답으로 받은 토큰을 로그인 POST에 사용합니다.
 *
 * 로그인 요청에만 토큰을 전달하므로,
 * 로그인 성공 후 세션 재생성 시
 * 낡은 토큰이 전역 헤더에 남지 않습니다.
 */
async function fetchCsrfToken() {
  const { data } = await window.axios.get(
    '/tillwhite/auth/csrf',
    {
      headers: {
        'Cache-Control': 'no-cache',
      },
    },
  );

  const token = data?.csrf_token;

  if (typeof token !== 'string' || !token.length) {
    throw new Error(
      'CSRF 토큰을 발급받지 못했습니다.',
    );
  }

  return token;
}

/**
 * Laravel 로그인 요청
 *
 * 1. 최신 CSRF 토큰 조회
 * 2. 로그인 POST
 * 3. 419 발생 시 새 토큰으로 한 번만 재시도
 *
 * 일반 401, 403, 422 등의 오류는
 * 재시도하지 않고 그대로 호출자에게 전달합니다.
 */
async function requestLogin(payload) {
  for (let attempt = 0; attempt < 2; attempt += 1) {
    const csrfToken = await fetchCsrfToken();

    try {
      const response = await window.axios.post(
        '/tillwhite/login',
        payload,
        {
          headers: {
            'X-CSRF-TOKEN': csrfToken,
          },
        },
      );

      return response.data;
    } catch (error) {
      const isCsrfError =
        error.response?.status === 419;

      const canRetry = attempt === 0;

      if (!isCsrfError || !canRetry) {
        throw error;
      }
    }
  }

  throw new Error(
    '로그인 요청을 완료하지 못했습니다.',
  );
}

/**
 * 사용자 로그인
 *
 * 기존 로그인 검증 및 라우팅을 유지하면서
 * CSRF 갱신과 사용자 세션 동기화를 처리합니다.
 */
async function login() {
  if (isLoggingIn.value) {
    return;
  }

  loginError.value = '';

  const validation = await loginForm.value.validate();

  if (!validation.valid) {
    return;
  }

  isLoggingIn.value = true;

  try {
    const payload = {
      ...form.value,
      id: form.value.id.trim(),
    };

    /**
     * 기존 사용자 캐시 초기화
     *
     * 이전 로그인 세션의 사용자 정보를
     * 새 로그인 이후 재사용하지 않게 합니다.
     */
    clear();

    /**
     * Laravel 로그인 요청
     */
    const data = await requestLogin(payload);

    /**
     * 로그인 응답에 포함된 실제 사용자 정보 저장
     *
     * 기존 AuthController가
     * user 객체를 반환하는 구조를 그대로 사용합니다.
     */
    if (!data?.user) {
      throw new Error(
        '로그인 사용자 정보를 확인할 수 없습니다.',
      );
    }

    setUser(data.user);

    /**
     * 로그인 성공 후 메인 이동
     *
     * sessionExpired 쿼리를 가지고 가지 않습니다.
     */
    await router.push({
      name: 'main',
    });
  } catch (error) {
    const status = error?.response?.status;

    if (status === 419) {
      loginError.value =
        '로그인 보안 세션이 만료되었습니다. 다시 시도해주세요.';
    } else if (status === 401) {
      loginError.value =
        '아이디 또는 비밀번호를 확인해주세요.';
    } else if (status === 403) {
      loginError.value =
        error.response?.data?.message
        ?? '현재 로그인할 수 없는 계정입니다.';
    } else if (status === 422) {
      loginError.value =
        error.response?.data?.message
        ?? '입력 내용을 확인해주세요.';
    } else if (
      error?.message ===
      '로그인 사용자 정보를 확인할 수 없습니다.'
    ) {
      loginError.value =
        '로그인 응답을 확인하지 못했습니다. 다시 시도해 주세요.';
    } else if (
      error?.message ===
      'CSRF 토큰을 발급받지 못했습니다.'
    ) {
      loginError.value =
        '로그인 보안 토큰을 확인하지 못했습니다. 다시 시도해 주세요.';
    } else if (
      error?.message ===
      '로그인 요청을 완료하지 못했습니다.'
    ) {
      loginError.value =
        '로그인 요청을 완료하지 못했습니다. 다시 시도해 주세요.';
    } else if (!error?.response) {
      loginError.value =
        '서버에 연결하지 못했습니다. 네트워크를 확인해 주세요.';
    } else if (status >= 500) {
      loginError.value =
        '서버 처리 중 문제가 발생했습니다. 잠시 후 다시 시도해 주세요.';
    } else {
      loginError.value =
        '로그인 중 오류가 발생했습니다. 다시 시도해 주세요.';
    }
  } finally {
    isLoggingIn.value = false;
  }
}
</script>