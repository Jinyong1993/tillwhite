// Laravel에서 Axios 등의 기본 설정을 불러옵니다.
import './bootstrap';

import { createApp } from 'vue';
import { createVuetify } from 'vuetify';

// Vuetify 기본 스타일
import 'vuetify/styles';

// Material Design Icons 아이콘 폰트
import '@mdi/font/css/materialdesignicons.css';

import App from './App.vue';
import router from './router';

/**
 * Till White에서 사용할 Vuetify 인스턴스를 생성합니다.
 *
 * 생성한 Vuetify 인스턴스는 아래에서 Vue 애플리케이션에
 * 플러그인으로 등록하여 모든 화면에서 Vuetify 컴포넌트를
 * 사용할 수 있도록 합니다.
 */
const vuetify = createVuetify();

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