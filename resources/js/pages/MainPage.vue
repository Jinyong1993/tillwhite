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
        BAKERY - 생산 & 폐기 입력
      </v-card-subtitle>

      <v-card-text
        class="bg-surface-light"
      >
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
          @click="logout"
          block
        >
          로그아웃
        </v-btn>
      </v-card-text>
    </v-card>
  </AppPageContainer>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppPageContainer from '../components/layout/AppPageContainer.vue';

/**
 * Vue Router 사용
 *
 * 로그아웃이 정상적으로 완료된 후
 * 로그인 화면으로 이동하기 위해 사용한다.
 */
const router = useRouter();

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
 */
async function loadUser() {
  const response = await fetch('/tillwhite/auth/me', {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
  });

  /**
   * 사용자 정보를 정상적으로 조회하지 못한 경우
   *
   * 라우터에서도 인증 여부를 검사하고 있으므로
   * 여기서는 사용자 정보를 저장하지 않고 종료한다.
   */
  if (!response.ok) {
    return;
  }

  // Laravel에서 반환한 JSON 응답을 읽는다.
  const data = await response.json();

  // 현재 로그인 사용자 정보를 Vue 상태에 저장한다.
  user.value = data.user;
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
 * 로그아웃 요청은 서버 상태를 변경하는 요청이므로
 * GET이 아닌 POST 방식으로 전송한다.
 */
async function logout() {
  /**
   * Blade에 저장된 CSRF 토큰 조회
   *
   * Laravel web.php의 POST 라우트는 CSRF 보호를 받으므로
   * 현재 페이지의 meta 태그에서 토큰을 가져와 요청 헤더에 전달한다.
   */
  const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    .getAttribute('content');

  /**
   * Laravel 로그아웃 API 호출
   *
   * Accept 헤더를 application/json으로 지정하여
   * 서버에서 JSON 응답을 받을 수 있도록 한다.
   */
  const response = await fetch('/tillwhite/logout', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
  });

  /**
   * 로그아웃 실패 처리
   *
   * 서버에서 정상적인 성공 응답을 반환하지 않은 경우
   * 로그인 화면으로 이동하지 않고 오류 내용을 확인한다.
   */
  if (!response.ok) {
    const data = await response.json();

    console.log(data.message);

    return;
  }

  /**
   * 로그인 화면으로 이동
   *
   * 서버의 인증 세션이 정상적으로 종료된 이후
   * Vue Router를 사용하여 로그인 페이지로 이동한다.
   */
  router.push({
    name: 'login',
  });
}
</script>