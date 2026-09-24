import axios from 'axios';

// Axios를 전역에서 사용할 수 있도록 window 객체에 등록합니다.
// 이후 Vue 컴포넌트에서 window.axios.get(), post() 등의 형태로 사용할 수 있습니다.
window.axios = axios;

/**
 * Laravel에 Axios 요청임을 알려주는 기본 HTTP Header입니다.
 *
 * 서버에서 일반 페이지 요청과 AJAX 요청을
 * 구분할 때 사용할 수 있습니다.
 */
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Axios 요청에 쿠키와 인증 정보를 포함하도록 설정합니다.
 *
 * Till White는 Laravel 세션 인증을 사용하므로
 * 로그인 세션 쿠키가 요청에 함께 전달될 수 있도록 합니다.
 */
window.axios.defaults.withCredentials = true;

/**
 * Laravel의 CSRF 보호를 위해
 * XSRF 토큰을 Axios 요청에 포함하도록 설정합니다.
 */
window.axios.defaults.withXSRFToken = true;