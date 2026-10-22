import { ref } from 'vue';

/**
 * Till White 로그인 사용자 공통 상태
 *
 * 모든 페이지가 동일한 사용자 및 권한 정보를
 * 공유하도록 모듈 범위에서 관리합니다.
 */
const user = ref(null);
const loaded = ref(false);

/**
 * 동시에 발생하는 동일 사용자 조회 요청을
 * 하나로 공유합니다.
 */
let pendingRequest = null;

/**
 * 로그인 및 로그아웃 시 이전 요청 결과가
 * 현재 세션을 덮어쓰지 못하도록 관리합니다.
 */
let sessionVersion = 0;

export function useSession() {
  /**
   * 현재 로그인 사용자 조회
   *
   * force = false:
   * - 조회한 사용자 정보가 있으면 캐시 사용
   *
   * force = true:
   * - Laravel 서버에서 사용자 정보를 다시 조회
   *
   * 동일 시점의 중복 조회는 하나의 요청을 공유합니다.
   */
  async function loadUser(force = false) {
    if (!force && loaded.value && user.value) {
      return user.value;
    }

    if (pendingRequest) {
      return pendingRequest;
    }

    const requestVersion = sessionVersion;

    const request = window.axios
      .get('/tillwhite/auth/me')
      .then(({ data }) => {
        const nextUser = data?.user;

        if (!nextUser) {
          throw new Error(
            '로그인 사용자 정보를 확인할 수 없습니다.',
          );
        }

        if (requestVersion === sessionVersion) {
          user.value = nextUser;
          loaded.value = true;
        }

        return requestVersion === sessionVersion
          ? nextUser
          : user.value;
      })
      .catch((error) => {
        if (requestVersion === sessionVersion) {
          user.value = null;
          loaded.value = false;
        }

        throw error;
      })
      .finally(() => {
        if (pendingRequest === request) {
          pendingRequest = null;
        }
      });

    pendingRequest = request;

    return request;
  }

  /**
   * 로그인 성공 후 사용자 정보 저장
   *
   * 로그인 API가 반환한 최신 사용자와
   * 권한 정보를 즉시 반영합니다.
   *
   * 기존 비동기 요청의 결과는 무효화합니다.
   */
  function setUser(nextUser) {
    sessionVersion += 1;
    pendingRequest = null;

    user.value = nextUser ?? null;
    loaded.value = Boolean(nextUser);
  }

  /**
   * 특정 권한 보유 여부 확인
   *
   * 기존 화면과 메뉴에서 사용하던
   * can(permission) 인터페이스를 유지합니다.
   */
  function can(permission) {
    return user.value?.permissions?.includes(permission) ?? false;
  }

  /**
   * 프런트엔드 로그인 사용자 상태 초기화
   *
   * Laravel 세션이나 쿠키를 직접 삭제하지 않습니다.
   *
   * 서버에서 인증이 만료되었거나
   * 사용자가 정상적으로 로그아웃한 경우
   * 호출합니다.
   */
  function clear() {
    sessionVersion += 1;
    pendingRequest = null;

    user.value = null;
    loaded.value = false;
  }

  return {
    user,
    loaded,
    loadUser,
    setUser,
    can,
    clear,
  };
}