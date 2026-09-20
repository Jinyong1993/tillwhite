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
        BAKERY - 생산 & 폐기 입력
      </v-card-subtitle>

      <v-card-text
        class="bg-surface-light"
      >
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
              label="비밀번호"
              type="password"
              variant="outlined"
            />

            <v-btn
              type="submit"
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
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppPageContainer from '../../components/layout/AppPageContainer.vue';

const router = useRouter();

// 로그인 Form
const loginForm = ref(null);

// 로그인 입력값
const form = ref({
  id: '',
  password: '',
});

// 로그인 입력값 검증 규칙
const rules = {
  id: [
    value => !!value?.trim() || '아이디를 입력해주세요.',
  ],
  password: [
    value => !!value || '비밀번호를 입력해주세요.',
  ],
};

// CSRF 토큰
const csrfToken = ref('');

/**
 * 페이지 초기화
 *
 * Laravel에서 생성한 CSRF 토큰을 가져온다.
 */
onMounted(() => {
  csrfToken.value = document
    .querySelector('meta[name="csrf-token"]')
    .getAttribute('content');
});

/**
 * 로그인
 */
async function login() {
  // 로그인 입력값 검증
  const { valid } = await loginForm.value.validate();

  // 입력값 검증 실패 시 로그인 중단
  if (!valid) {
    return;
  }

  // Laravel 로그인 요청
  const response = await fetch('/tillwhite/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken.value,
    },
    body: JSON.stringify({
      ...form.value,
      id: form.value.id.trim(),
    }),
  });

  // Laravel에서 전달받은 JSON 응답
  const data = await response.json();

  // 로그인 실패
  if (!response.ok) {
    console.log(data.message);
    return;
  }

  // 로그인 성공
  await router.push({
    name: 'main',
  });
}
</script>