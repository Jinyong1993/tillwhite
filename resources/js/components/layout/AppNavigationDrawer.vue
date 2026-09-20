<template>
  <v-navigation-drawer
    v-model="drawer"
    location="left"
    temporary
  >
    <v-list>
      <!--
        메뉴 상단 제목

        현재 시스템의 이름을 표시한다.
      -->
      <v-list-subheader>
        Till White
      </v-list-subheader>

      <!--
        메인 페이지

        현재 시스템의 기본 화면으로 이동한다.
      -->
      <v-list-item
        prepend-icon="mdi-home-outline"
        title="메인"
      />

      <!--
        생산 관리

        베이커리 생산 데이터를 관리하는 화면으로 이동한다.
      -->
      <v-list-item
        prepend-icon="mdi-bread-slice-outline"
        title="생산 관리"
      />

      <!--
        폐기 관리

        베이커리 폐기 데이터를 관리하는 화면으로 이동한다.
      -->
      <v-list-item
        prepend-icon="mdi-trash-can-outline"
        title="폐기 관리"
      />

      <!--
        근무 일정

        직원 근무 일정을 관리하는 화면으로 이동한다.
      -->
      <v-list-item
        prepend-icon="mdi-calendar-outline"
        title="근무 일정"
      />

      <v-divider
        class="my-2"
      />

      <!--
        로그아웃

        로그아웃 요청이 진행되는 동안 버튼을 비활성화하여
        중복 요청이 발생하지 않도록 한다.
      -->
      <v-list-item
        prepend-icon="mdi-logout"
        title="로그아웃"
        :disabled="isLoggingOut"
        :loading="isLoggingOut"
        @click="logout"
      />
    </v-list>
  </v-navigation-drawer>
</template>

<script setup>
import { computed, ref } from 'vue';

/**
 * 사이드 메뉴 표시 상태
 *
 * 부모 컴포넌트에서 v-model을 통해
 * 메뉴의 열림/닫힘 상태를 관리한다.
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});

/**
 * 부모 컴포넌트로 전달할 이벤트
 *
 * update:modelValue:
 * 사이드 메뉴의 열림/닫힘 상태를 부모에게 전달한다.
 *
 * error:
 * 로그아웃 과정에서 발생한 오류 메시지를
 * 부모 페이지의 AppAlert로 전달한다.
 */
const emit = defineEmits([
  'update:modelValue',
  'error',
]);

/**
 * v-navigation-drawer에서 사용할
 * 양방향 바인딩 상태
 */
const drawer = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
});

/**
 * 로그아웃 요청 진행 상태
 *
 * 로그아웃 요청 중에는 메뉴의 로그아웃 버튼을
 * 다시 클릭할 수 없도록 한다.
 */
const isLoggingOut = ref(false);

/**
 * 사용자 로그아웃
 *
 * Laravel의 로그아웃 API를 호출하여
 * 현재 사용자의 인증 세션을 종료한다.
 *
 * Axios가 XSRF-TOKEN 쿠키를 사용하여
 * 현재 CSRF 토큰을 요청에 자동으로 포함한다.
 */
async function logout() {
  try {
    // 로그아웃 요청 진행 상태를 활성화한다.
    isLoggingOut.value = true;

    // Laravel 로그아웃 API를 호출한다.
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
     * 로그아웃 실패 메시지 전달
     *
     * Laravel에서 전달한 오류 메시지가 존재하면 해당 메시지를 사용하고,
     * 예상하지 못한 오류라면 기본 오류 메시지를 부모 페이지로 전달한다.
     */
    emit(
      'error',
      error.response?.data?.message ??
        '로그아웃 중 오류가 발생했습니다.'
    );
  } finally {
    // 로그아웃 요청이 완료되면 로딩 상태를 해제한다.
    isLoggingOut.value = false;
  }
}
</script>