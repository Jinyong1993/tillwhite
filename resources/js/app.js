/**
 * Laravel Axios 기본 설정
 */
import './bootstrap';

import { createApp } from 'vue';
import { createVuetify } from 'vuetify';

// Vuetify 한국어 및 영어 번역
import { ko, en } from 'vuetify/locale';

// Vuetify 기본 스타일
import 'vuetify/styles';

// Material Design Icons 아이콘 폰트
import '@mdi/font/css/materialdesignicons.css';

import App from './App.vue';
import router from './router';

import { useSession } from './composables/useSession';

/**
 * 전역 Axios 401 세션 만료 처리
 *
 * 로그인 요청 실패는 기존 LoginPage에서 처리합니다.
 * 인증된 화면에서 발생한 401만 세션 만료로 처리합니다.
 */
let sessionRedirecting = false;

window.axios.interceptors.response.use(
  response => {
    const requestUrl = String(response?.config?.url ?? '');
    const requestMethod = String(
      response?.config?.method ?? ''
    ).toLowerCase();

    // 로그인 성공 시 다음 세션 만료를 처리할 수 있도록 초기화
    if (
      requestMethod === 'post' &&
      /\/tillwhite\/login(?:[?#]|$)/.test(requestUrl)
    ) {
      sessionRedirecting = false;
    }

    return response;
  },
  error => {
    const status = Number(error?.response?.status);
    const requestUrl = String(error?.config?.url ?? '');

    const isLoginRequest =
      /\/tillwhite\/login(?:[?#]|$)/.test(requestUrl);

    if (
      status === 401 &&
      !isLoginRequest &&
      router.currentRoute.value.name !== 'login' &&
      !sessionRedirecting
    ) {
      sessionRedirecting = true;

      useSession().clear();

      router.replace({
        name: 'login',
        query: { sessionExpired: '1' },
      }).then(
        () => {
          sessionRedirecting = false;
        },
        () => {
          sessionRedirecting = false;
        }
      );
    }

    return Promise.reject(error);
  }
);

/**
 * Till White에서 사용할 Vuetify 인스턴스를 생성합니다.
 *
 * 한국어를 기본 언어로 지정하고,
 * 한국어 번역이 없는 경우 영어를 사용합니다.
 *
 * 날짜 선택기 및 Vuetify 공통 컴포넌트의
 * 번역 경고를 방지합니다.
 */
const vuetify = createVuetify({
  locale: {
    locale: 'ko',
    fallback: 'en',
    messages: {
      ko,
      en,
    },
  },
});

/**
 * Till White Vue 애플리케이션을 생성합니다.
 *
 * App.vue를 최상위 컴포넌트로 사용하고,
 * Vuetify와 Vue Router를 등록한 뒤
 * Blade에 있는 #app 요소에 애플리케이션을 연결합니다.
 */
createApp(App)
  .use(vuetify)
  .use(router)
  .mount('#app');