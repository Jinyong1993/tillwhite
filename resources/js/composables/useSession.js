import { ref } from 'vue';

// 현재 로그인한 사용자 정보를 여러 컴포넌트에서 공유하기 위한 상태
const user = ref(null);

// 사용자 정보를 서버에서 한 번 이상 불러왔는지 여부
const loaded = ref(false);

/**
 * 로그인 세션과 사용자 권한을 관리하는 공통 Composable입니다.
 *
 * 사용자 정보를 여러 페이지에서 공유하고,
 * 서버에 불필요하게 사용자 정보를 반복 요청하지 않도록 관리합니다.
 */
export function useSession() {
  /**
   * 현재 로그인한 사용자 정보를 서버에서 조회합니다.
   *
   * force가 false이고 이미 사용자 정보를 불러온 상태라면
   * 서버에 다시 요청하지 않고 기존 사용자 정보를 반환합니다.
   *
   * force가 true이면 기존 정보가 있어도 서버에서 다시 조회합니다.
   */
  async function loadUser(force = false) {
    // 이미 조회한 사용자 정보가 있으면 기존 정보 반환
    if (loaded.value && !force) {
      return user.value;
    }

    // 현재 로그인한 사용자 정보 조회
    const response = await window.axios.get('/tillwhite/auth/me');

    // 서버에서 받은 사용자 정보 저장
    user.value = response.data.user;

    // 사용자 정보 조회 완료 상태로 변경
    loaded.value = true;

    return user.value;
  }

  /**
   * 현재 사용자가 특정 기능 권한을 가지고 있는지 확인합니다.
   *
   * 예:
   * can('production.view')
   * can('employee.manage')
   *
   * 권한이 있으면 true,
   * 권한이 없거나 사용자 정보가 없으면 false를 반환합니다.
   */
  function can(permission) {
    return user.value?.permissions?.includes(permission) ?? false;
  }

  /**
   * 로그아웃 이후 프론트엔드에 저장된
   * 사용자 및 세션 관련 상태를 초기화합니다.
   */
  function clear() {
    user.value = null;
    loaded.value = false;
  }

  /**
   * 다른 컴포넌트에서 사용할 수 있도록
   * 세션 상태와 관련 함수들을 반환합니다.
   */
  return {
    user,
    loaded,
    loadUser,
    can,
    clear,
  };
}