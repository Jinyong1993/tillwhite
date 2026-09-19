<template>
  <v-container>
    <v-card
      class="mx-auto"
      width="400"
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
        <v-text-field
          v-model="id"
          :error-messages="idError"
          label="아이디"
          variant="outlined"
        />

        <v-text-field
          v-model="password"
          :error-messages="passwordError"
          label="비밀번호"
          type="password"
          variant="outlined"
        />
        <v-btn
          @click="login()"
          block
        >
          로그인
        </v-btn>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
  import { onMounted, ref } from 'vue';

  // 로그인 관련
  const id = ref('');
  const password = ref('');

  // 로그인 입력 오류 메시지
  const idError = ref('');
  const passwordError = ref('');

  // CSRF 토큰
  const csrfToken = ref('');

  /**
   * 페이지 초기화
   *
   * LoginPage가 화면에 마운트된 후
   * Laravel에서 생성한 CSRF 토큰을 가져온다.
   */
  onMounted(() => {
    csrfToken.value = document
      .querySelector('meta[name="csrf-token"]')
      .getAttribute('content');
  });

  /**
   * 로그인 입력값 검증
   *
   * 아이디와 비밀번호 입력값을 확인하고
   * 문제가 있으면 오류 메시지를 설정한다.
   */
  function validateLogin() {
    // 기존 오류 메시지 초기화
    idError.value = '';
    passwordError.value = '';

    // 아이디 입력 검증
    if (!id.value.trim()) {
      idError.value = '아이디를 입력해주세요.';
    }

    // 비밀번호 입력 검증
    if (!password.value) {
      passwordError.value = '비밀번호를 입력해주세요.';
    }

    // 오류가 없으면 true, 있으면 false 반환
    return !idError.value && !passwordError.value;
  }

  /**
   * 로그인
   *
   * 입력값 검증 후 Laravel 로그인 API에
   * 아이디와 비밀번호를 전달한다.
   */
  async function login() {
    // 입력값 검증 실패 시 로그인 중단
    if (!validateLogin()) {
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
        id: id.value,
        password: password.value,
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
    console.log(data.message);
    console.log(data.user);
  }
</script>