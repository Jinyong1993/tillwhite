import {
  computed,
  ref,
} from 'vue';

/**
 * Till White 전체 애플리케이션에서 공유하는
 * 공통 전체 화면 로딩 상태입니다.
 *
 * 특정 업무 페이지(AppShell)나
 * 특정 내비게이션 메뉴에 종속되지 않습니다.
 *
 * 따라서 Drawer, 빠른 메뉴, router.push(),
 * 권한 리다이렉트 등 어떤 방식으로 화면이 변경되더라도
 * 동일한 공통 로딩 상태를 사용할 수 있습니다.
 *
 * 기본 처리 흐름:
 *
 * Vue Router 화면 이동 시작
 * → 화면 이동 로딩 시작(beginNavigationLoading)
 * → 인증 및 권한 검사
 * → 목적지 페이지 이동
 * → 목적지 페이지 최초 데이터 조회
 * → 페이지 준비 완료(completePageLoading)
 * → 최소 표시 시간 확인
 * → 전체 화면 로딩 종료
 */

/**
 * 전체 화면 로딩 최소 표시 시간입니다.
 *
 * API 요청이 빠르게 끝나더라도
 * 로딩 화면이 순간적으로 깜빡이지 않도록
 * 최소 1초 동안 표시합니다.
 *
 * API 요청 자체가 1초 이상 걸렸다면
 * 추가로 기다리지 않습니다.
 */
const MINIMUM_LOADING_MS = 1000;

// 공통 전체 화면 로딩 표시 여부
const loading = ref(false);

/**
 * 현재 공통 로딩이 시작된 시간입니다.
 *
 * 페이지 준비가 완료된 뒤
 * 최소 표시 시간을 계산할 때 사용합니다.
 */
let loadingStartedAt = 0;

/**
 * 현재 진행 중인 로딩의 요청 번호입니다.
 *
 * 완전히 새로운 화면 이동 로딩이 시작되거나
 * 현재 로딩이 취소될 때 증가합니다.
 *
 * 이전 화면에서 실행된 비동기 종료 작업이
 * 새로운 화면의 로딩을 잘못 종료하지 못하도록 보호합니다.
 */
let loadingRequestId = 0;

/**
 * 현재 공통 전체 화면 로딩 상태입니다.
 *
 * true:
 * - 전체 화면 로딩 표시
 *
 * false:
 * - 전체 화면 로딩 숨김
 */
const isLoading = computed(() => {
  return loading.value;
});

/**
 * 지정한 시간만큼 기다립니다.
 *
 * 공통 전체 화면 로딩의
 * 최소 표시 시간을 맞출 때 사용합니다.
 */
function wait(milliseconds) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, milliseconds);
  });
}

/**
 * 화면 이동용 공통 로딩을 시작합니다.
 *
 * Vue Router에서 새로운 업무 화면으로
 * 이동하기 시작할 때 호출합니다.
 *
 * 이미 공통 화면 이동 로딩이 진행 중이라면
 * 새로운 로딩으로 다시 시작하지 않습니다.
 *
 * 이 처리가 중요한 이유:
 *
 * 예:
 *
 * 직원 관리 클릭
 * → 공통 로딩 시작
 * → 직원 조회 권한(employee.view) 없음
 * → Router가 메인으로 리다이렉트
 * → Router 가드가 다시 실행
 *
 * 위 상황에서 메인 리다이렉트 때문에
 * 로딩 시작 시간을 다시 0초부터 계산하면 안 됩니다.
 *
 * 최초 직원 관리 이동 시점부터 이어진
 * 하나의 화면 이동으로 처리해야 합니다.
 *
 * 따라서 이미 loading이 true라면
 * 현재 요청 번호와 시작 시간을 그대로 유지합니다.
 */
function beginNavigationLoading() {
  if (loading.value) {
    return;
  }

  loadingRequestId += 1;
  loadingStartedAt = Date.now();
  loading.value = true;
}

/**
 * 현재 페이지의 최초 데이터 준비가 완료되었음을 알립니다.
 *
 * 이 함수가 호출되었다고 해서
 * 무조건 즉시 로딩을 종료하지는 않습니다.
 *
 * 화면 이동 로딩이 시작된 시점부터
 * 최소 1초가 지나지 않았다면
 * 남은 시간만큼 기다린 뒤 종료합니다.
 *
 * 예:
 *
 * 화면 이동 + API 조회 = 0.3초
 * → 약 0.7초 추가 대기
 *
 * 화면 이동 + API 조회 = 1.4초
 * → 추가 대기 없이 종료
 *
 * 기다리는 도중 현재 로딩이 취소되거나
 * 완전히 새로운 로딩이 시작되면
 * 이전 페이지의 완료 요청은 무시됩니다.
 */
async function completePageLoading() {
  /**
   * 현재 공통 로딩이 없는 경우에는
   * 종료할 로딩이 없으므로 아무 작업도 하지 않습니다.
   *
   * 직접 URL 진입 등으로
   * 화면 이동 로딩이 시작되지 않은 상태에서도
   * 안전하게 호출할 수 있습니다.
   */
  if (!loading.value) {
    return;
  }

  /**
   * 현재 완료하려는 로딩의 요청 번호를 저장합니다.
   *
   * 아래에서 최소 표시 시간을 기다리는 동안
   * 현재 로딩이 변경되었는지 확인할 때 사용합니다.
   */
  const requestId = loadingRequestId;

  const elapsed =
    Date.now() - loadingStartedAt;

  const remaining = Math.max(
    MINIMUM_LOADING_MS - elapsed,
    0,
  );

  /**
   * 최소 표시 시간이 아직 지나지 않았다면
   * 남은 시간만큼 기다립니다.
   */
  if (remaining > 0) {
    await wait(remaining);
  }

  /**
   * 기다리는 동안 현재 로딩이 취소되거나
   * 완전히 새로운 로딩이 시작되었다면
   * 현재 완료 요청은 더 이상 유효하지 않습니다.
   *
   * 새로운 화면의 로딩을 종료하면 안 되므로
   * 아무 작업도 하지 않습니다.
   */
  if (requestId !== loadingRequestId) {
    return;
  }

  loadingStartedAt = 0;
  loading.value = false;
}

/**
 * 공통 전체 화면 로딩을 즉시 취소합니다.
 *
 * 예:
 * - 로그인 화면으로 이동
 * - Vue Router 화면 이동 실패
 * - 목적지 업무 페이지가 정상적으로 준비될 수 없는 상황
 *
 * 이 경우에는 최소 1초 표시 시간을 적용하지 않습니다.
 *
 * 요청 번호도 증가시키므로
 * 이전 페이지의 늦은 completePageLoading() 호출이
 * 현재 상태에 영향을 주지 않습니다.
 */
function cancelLoading() {
  loadingRequestId += 1;

  loadingStartedAt = 0;
  loading.value = false;
}

/**
 * Till White 공통 전체 화면 로딩 기능입니다.
 *
 * App.vue:
 * - 실제 공통 전체 화면 로딩 표시
 *
 * Vue Router:
 * - 업무 화면 이동 시작 시
 *   beginNavigationLoading() 호출
 *
 * 각 업무 페이지:
 * - 최초 데이터 조회가 완료되면
 *   completePageLoading() 호출
 *
 * 로그인 화면 또는 이동 실패:
 * - cancelLoading()으로 즉시 정리
 *
 * useAppLoading:
 * - 전체 화면 로딩 상태 관리
 * - 최소 1초 표시 시간 관리
 * - 리다이렉트 중 중복 시작 방지
 * - 이전 화면의 늦은 완료 요청 보호
 *
 * 특정 페이지나 특정 메뉴에 종속되지 않으므로
 * 제품 관리, 생산·폐기 관리, 직원 관리뿐 아니라
 * 앞으로 추가되는 업무 화면에서도
 * 동일한 구조를 사용할 수 있습니다.
 */
export function useAppLoading() {
  return {
    isLoading,
    beginNavigationLoading,
    completePageLoading,
    cancelLoading,
  };
}