import { ref } from 'vue';
const user = ref(null);
const loaded = ref(false);
export function useSession() {
  /** 현재 로그인 사용자와 권한 목록을 서버에서 조회한다. */
  async function loadUser(force = false) { if (loaded.value && !force) return user.value; const response = await window.axios.get('/tillwhite/auth/me'); user.value = response.data.user; loaded.value = true; return user.value; }
  /** 특정 기능 권한 보유 여부 */
  function can(permission) { return user.value?.permissions?.includes(permission) ?? false; }
  /** 로그아웃 이후 공유 사용자 상태 초기화 */
  function clear() { user.value = null; loaded.value = false; }
  return { user, loaded, loadUser, can, clear };
}
