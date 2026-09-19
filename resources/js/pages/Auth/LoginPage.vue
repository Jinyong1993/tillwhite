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
          label="아이디"
          variant="outlined"
        />

        <v-text-field
          v-model="password"
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
  import { ref } from 'vue';

  // 로그인 관련
  const id = ref('');
  const password = ref('');

  // 로그인 버튼 클릭시
  function login() {
    console.log(id.value);
    console.log(password.value);
  }
</script>