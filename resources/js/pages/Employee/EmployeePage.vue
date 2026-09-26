<template>
  <!--
    직원 관리 페이지의 공통 레이아웃(AppShell)입니다.

    appShellRef는 최초 데이터 조회 오류 등에서
    AppShell의 공통 알림을 호출하기 위해 사용합니다.

    페이지 이동 전체 화면 로딩은
    애플리케이션 공통 로딩(useAppLoading)에서 관리합니다.
  -->
  <AppShell
    ref="appShellRef"
    :title="pageTitle"
  >
    <template
      #default="{
        user,
        can,
        setError,
        setSuccess
      }"
    >
      <!--
        직원 등록 버튼

        직원 관리 권한(employee.manage)이 없어도 버튼은 표시합니다.

        버튼을 클릭하면 등록 창을 바로 열지 않고
        Laravel 서버에 직원 관리 권한(employee.manage)을 확인합니다.

        권한이 없으면 등록 창을 열지 않고
        공통 오류 알림을 표시합니다.
      -->
      <v-btn
        block
        class="mb-5"
        prepend-icon="mdi-account-plus-outline"
        variant="flat"
        @click="openRegisterDialog(setError)"
      >
        직원 등록

        <v-chip
          v-if="!can('employee.manage')"
          class="ml-2"
          size="x-small"
          variant="tonal"
        >
          권한 없음
        </v-chip>
      </v-btn>

      <!--
        직원 목록

        직원 한 명의 카드 디자인은
        직원 카드(EmployeeCard) 컴포넌트에서 담당합니다.

        상세보기 버튼을 누르면
        detail 이벤트로 선택한 직원을 전달받습니다.
      -->
      <div
        v-if="data.employees.length > 0"
        class="d-flex flex-column ga-3"
      >
        <EmployeeCard
          v-for="employee in data.employees"
          :key="employee.id"
          :employee="employee"
          @detail="openDetailDialog($event, setError)"
        />
      </div>

      <!-- 직원이 없는 경우 -->
      <v-card
        v-else
        variant="outlined"
        rounded="lg"
      >
        <v-card-text class="text-center text-medium-emphasis py-8">
          등록된 직원이 없습니다.
        </v-card-text>
      </v-card>

      <!--
        직원 등록 컴포넌트

        등록 화면 자체는
        직원 등록(EmployeeRegisterDialog) 컴포넌트가 담당합니다.

        EmployeePage는 다음 역할을 담당합니다.

        - Laravel 등록 권한 확인
        - 등록 가능한 점포 조회
        - 등록 가능한 직급 조회
        - 등록 가능한 권한 역할 조회
        - 등록 취소 확인
        - 임시저장 확인 및 요청 연결
        - 임시저장 전체 삭제 확인 및 요청 연결
        - 실제 직원 등록 확인 및 API 호출

        프론트 화면의 검사는 사용자 편의를 위한 것이며
        실제 권한과 데이터 검증은 Laravel 서버에서 다시 수행합니다.
      -->
      <EmployeeRegisterDialog
        v-model="registerDialog"
        v-model:form="form"
        :stores="data.stores"
        :positions="data.positions"
        :roles="data.roles"
        :loading-action="registerLoadingAction"
        @close="requestCloseRegisterDialog"
        @delete="requestDeleteDraft"
        @draft="requestSaveDraft"
        @submit="requestSaveEmployee"
      />

      <!--
        직원 등록 관련 공통 확인창

        취소 / 전체 삭제 / 임시저장 / 등록에서
        하나의 공통 확인창(ConfirmDialog)을 재사용합니다.

        실제 API 요청이 진행되는 동안에는
        확인 버튼에 로딩을 표시하고
        취소 / X / ESC / 바깥 영역 클릭을 막습니다.
      -->
      <ConfirmDialog
        v-model="confirmDialog.open"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirm-text="confirmDialog.confirmText"
        :cancel-text="confirmDialog.cancelText"
        :loading="isRegisterProcessing"
        @confirm="handleConfirm(
          setError,
          setSuccess
        )"
        @cancel="clearConfirmDialog"
      />

      <!--
        직원 상세보기 컴포넌트

        상세정보 화면 자체는
        직원 상세보기(EmployeeDetailDialog) 컴포넌트가 담당합니다.

        EmployeePage는 다음 역할을 담당합니다.

        - Laravel 상세조회 API 호출
        - 직원 관리 권한(employee.manage) 판단
        - 최고 관리자(super_admin) 보호
        - 재직 상태 저장 API 호출
      -->
      <EmployeeDetailDialog
        v-model="detailDialog"
        v-model:employment-status-value="statusForm.employment_status"
        :employee="selectedEmployee"
        :can-change-status="canChangeEmployeeStatus(
          selectedEmployee,
          user,
          can
        )"
        :status-unavailable-message="statusUnavailableMessage(
          selectedEmployee,
          user,
          can
        )"
        @close="closeDetailDialog"
        @save="saveStatus(setError)"
      />
    </template>
  </AppShell>
</template>

<script setup>
import {
  computed,
  onMounted,
  reactive,
  ref,
} from 'vue';

import AppShell from '../../components/layout/AppShell.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import EmployeeCard from '../../components/employee/EmployeeCard.vue';
import EmployeeDetailDialog from '../../components/employee/EmployeeDetailDialog.vue';
import EmployeeRegisterDialog from '../../components/employee/EmployeeRegisterDialog.vue';
import { useAppLoading } from '../../composables/useAppLoading';

/**
 * 현재 페이지 제목입니다.
 */
const pageTitle = '직원 관리';

/**
 * 공통 레이아웃(AppShell) 참조입니다.
 *
 * 직원 관리 페이지의 최초 데이터 조회에서
 * 오류가 발생했을 때 AppShell의 공통 오류 알림(setError)을
 * 호출하기 위해 사용합니다.
 *
 * 전체 화면 로딩은 AppShell에서 관리하지 않고
 * 애플리케이션 공통 로딩(useAppLoading)에서 관리합니다.
 */
const appShellRef = ref(null);

/**
 * Till White 애플리케이션 공통 전체 화면 로딩입니다.
 *
 * 내비게이션 메뉴에서 화면 이동 전에 시작한 로딩을
 * 직원 관리 페이지의 최초 데이터 조회가 끝난 뒤
 * completePageLoading()으로 완료 처리합니다.
 *
 * 최소 1초 표시 시간은 useAppLoading에서 공통으로 계산하므로
 * EmployeePage에서 별도의 타이머를 관리하지 않습니다.
 */
const {
  completePageLoading,
} = useAppLoading();

/**
 * 직원 등록 창(registerDialog)의 열림/닫힘 상태입니다.
 */
const registerDialog = ref(false);

/**
 * 현재 실행 중인 직원 등록 관련 작업입니다.
 *
 * 가능한 값:
 *
 * - null: 실행 중인 작업 없음
 * - delete: 전체 삭제
 * - draft: 임시저장
 * - submit: 실제 직원 등록
 *
 * 하나의 값으로 현재 작업을 관리하여
 * 실제로 실행한 버튼에만 로딩 표시가 나타나도록 합니다.
 *
 * 다른 등록 관련 동작은 요청이 끝날 때까지 막아
 * 중복 요청을 방지합니다.
 */
const registerLoadingAction = ref(null);

/**
 * 직원 등록 관련 요청이 현재 진행 중인지 확인합니다.
 *
 * 등록 다이얼로그와 공통 확인창(ConfirmDialog)이
 * 동일한 요청 진행 상태를 사용합니다.
 */
const isRegisterProcessing = computed(
  () => registerLoadingAction.value !== null,
);

/**
 * 직원 등록 관련 공통 확인창 상태입니다.
 *
 * action에는 사용자가 최종 확인했을 때
 * 실행할 작업을 저장합니다.
 *
 * 가능한 값:
 *
 * - null
 * - cancel
 * - delete
 * - draft
 * - submit
 */
const confirmDialog = reactive({
  open: false,
  action: null,
  title: '',
  message: '',
  confirmText: '확인',
  cancelText: '취소',
});

/**
 * 직원 상세보기 창(detailDialog)의 열림/닫힘 상태입니다.
 *
 * 실제 상세보기 화면은
 * 직원 상세보기(EmployeeDetailDialog) 컴포넌트에서 표시합니다.
 */
const detailDialog = ref(false);

/**
 * 상세보기에서 현재 선택한 직원입니다.
 *
 * 직원 목록 데이터를 그대로 사용하지 않고
 * 상세보기 버튼을 누른 시점에 Laravel 서버에서
 * 다시 조회한 최신 직원 정보를 저장합니다.
 */
const selectedEmployee = ref(null);

/**
 * 직원 관리 화면에서 사용하는 서버 데이터입니다.
 */
const data = ref({
  employees: [],
  stores: [],
  positions: [],
  roles: [],
});

/**
 * 신규 직원 등록 양식(form)입니다.
 */
const form = ref(createEmptyForm());

/**
 * 직원 등록 창을 열었을 때의 최초 양식 상태입니다.
 *
 * 단순히 모든 입력값이 비어 있는지 확인하면
 * 기본 부서(department)인 주방(kitchen) 때문에
 * 아무것도 입력하지 않은 상태도 작성된 것으로 잘못 판단할 수 있습니다.
 *
 * 따라서 등록 창을 열었을 때의 상태와
 * 현재 상태를 비교하여 작성 내용 변경 여부를 판단합니다.
 *
 * Laravel Session에서 draft를 복원한 경우에도
 * 해당 draft가 최초 상태가 됩니다.
 */
const initialRegisterForm = ref(createEmptyForm());

/**
 * 직원 등록 창을 열 때
 * Laravel Session에서 임시저장 내용(draft)이
 * 복원되었는지 기록합니다.
 *
 * 복원된 draft 자체도 사용자가 작성한 내용이므로
 * 아무것도 수정하지 않았더라도
 * 등록 창을 닫을 때 취소 확인창을 표시합니다.
 */
const hasLoadedDraft = ref(false);

/**
 * 직원 재직 상태 변경 양식(statusForm)입니다.
 *
 * 계정 활성 상태(is_active)는 화면에서 관리하지 않습니다.
 * Laravel 서버가 재직 상태(employment_status)를 기준으로 결정합니다.
 */
const statusForm = ref({
  employment_status: 'active',
});

/**
 * 비어 있는 신규 직원 등록 양식(form)을 만듭니다.
 *
 * 사용자가 직접 입력하는 값만 관리합니다.
 *
 * 아래 값들은 Laravel 서버에서 결정하므로
 * 등록 화면에서 직접 입력하지 않습니다.
 *
 * - 재직 상태(employment_status)
 * - 계정 활성 상태(is_active)
 * - 퇴사일(resigned_at)
 * - 마지막 로그인 시간(last_login_at)
 * - 비밀번호 변경 시간(password_changed_at)
 */
function createEmptyForm() {
  return {
    employee_code: '',
    name: '',
    phone: '',
    birth_date: '',
    password: '',
    store_id: null,
    department: 'kitchen',
    position_id: null,
    role_id: null,
    hired_at: '',
  };
}

/**
 * 직원 등록 양식(form)의 현재 상태를 복사합니다.
 *
 * 등록 창을 연 시점의 상태와 현재 상태를
 * 객체 참조가 아닌 실제 값으로 비교하기 위해 사용합니다.
 */
function copyRegisterForm(source) {
  return {
    employee_code: source.employee_code ?? '',
    name: source.name ?? '',
    phone: source.phone ?? '',
    birth_date: source.birth_date ?? '',
    password: source.password ?? '',
    store_id: source.store_id ?? null,
    department: source.department ?? 'kitchen',
    position_id: source.position_id ?? null,
    role_id: source.role_id ?? null,
    hired_at: source.hired_at ?? '',
  };
}

/**
 * Laravel Session에서 복원할 수 있는
 * 직원 등록 임시저장 데이터(draft)를 양식에 적용합니다.
 *
 * 서버에서 임시저장 데이터가 없는 경우에는
 * 새로운 빈 등록 양식을 사용합니다.
 *
 * 비밀번호(password)는 보안을 위해
 * 임시저장 대상에 포함하지 않으며 항상 빈 값으로 시작합니다.
 */
function createFormFromDraft(draft) {
  const emptyForm = createEmptyForm();

  if (!draft || typeof draft !== 'object') {
    return emptyForm;
  }

  return {
    ...emptyForm,

    employee_code:
      draft.employee_code ?? '',

    name:
      draft.name ?? '',

    phone:
      draft.phone ?? '',

    birth_date:
      draft.birth_date ?? '',

    password: '',

    store_id:
      draft.store_id ?? null,

    department:
      draft.department ?? 'kitchen',

    position_id:
      draft.position_id ?? null,

    role_id:
      draft.role_id ?? null,

    hired_at:
      draft.hired_at ?? '',
  };
}

/**
 * 등록 창을 연 이후
 * 사용자가 등록 양식을 변경했는지 확인합니다.
 *
 * 등록 창을 열었을 때의 최초 상태와
 * 현재 상태를 각 필드별로 비교합니다.
 *
 * 비밀번호(password)도 비교 대상이므로
 * 새 비밀번호를 입력한 상태에서 취소하는 경우에도
 * 작성 내용 취소 확인창을 표시합니다.
 */
function hasRegisterFormChanges() {
  const current = copyRegisterForm(form.value);
  const initial = initialRegisterForm.value;

  return Object.keys(current).some(
    (key) => current[key] !== initial[key],
  );
}

/**
 * 현재 사용자가 선택한 직원의
 * 재직 상태를 변경할 수 있는지 확인합니다.
 *
 * 이 검사는 화면 표시를 위한 검사입니다.
 *
 * 실제 직원 관리 권한(employee.manage)과
 * 최고 관리자(super_admin) 보호는
 * Laravel 서버에서 다시 검사합니다.
 */
function canChangeEmployeeStatus(employee, user, can) {
  if (!employee) {
    return false;
  }

  if (!can('employee.manage')) {
    return false;
  }

  /**
   * 변경 대상이 최고 관리자(super_admin)라면
   * 현재 로그인한 사용자 역시 최고 관리자(super_admin)여야 합니다.
   */
  if (
    employee?.role?.code === 'super_admin'
    && user?.role?.code !== 'super_admin'
  ) {
    return false;
  }

  return true;
}

/**
 * 재직 상태를 변경할 수 없는 이유를 표시합니다.
 */
function statusUnavailableMessage(employee, user, can) {
  if (!employee) {
    return '';
  }

  if (!can('employee.manage')) {
    return '직원의 재직 상태를 변경할 권한이 없습니다.';
  }

  if (
    employee?.role?.code === 'super_admin'
    && user?.role?.code !== 'super_admin'
  ) {
    return '최고 관리자 계정의 재직 상태를 변경할 권한이 없습니다.';
  }

  return '';
}

/**
 * Laravel 서버에서 전달한 오류 메시지를 우선 사용하고,
 * 메시지가 없는 경우 기본 오류 메시지를 반환합니다.
 */
function errorMessage(error, fallback) {
  return error.response?.data?.message ?? fallback;
}

/**
 * 직원 등록 관련 공통 확인창을 엽니다.
 */
function openConfirmDialog(
  action,
  title,
  message,
) {
  if (isRegisterProcessing.value) {
    return;
  }

  confirmDialog.action = action;
  confirmDialog.title = title;
  confirmDialog.message = message;
  confirmDialog.confirmText = '확인';
  confirmDialog.cancelText = '취소';
  confirmDialog.open = true;
}

/**
 * 직원 등록 관련 공통 확인창 상태를 초기화합니다.
 *
 * API 요청이 진행 중인 경우에는
 * 확인창을 임의로 닫지 않습니다.
 */
function clearConfirmDialog() {
  if (isRegisterProcessing.value) {
    return;
  }

  confirmDialog.open = false;
  confirmDialog.action = null;
  confirmDialog.title = '';
  confirmDialog.message = '';
  confirmDialog.confirmText = '확인';
  confirmDialog.cancelText = '취소';
}

/**
 * 직원 관리 화면에 필요한 직원 목록을 조회합니다.
 *
 * 직원 조회 권한(employee.view)은
 * Laravel 서버에서 최종적으로 확인합니다.
 *
 * 이 함수는 순수하게 직원 관리 데이터를 다시 조회하는 역할만 담당합니다.
 *
 * 따라서 직원 등록 완료 또는 재직 상태 변경 후
 * 직원 목록을 새로고침할 때는
 * 전체 화면 페이지 로딩을 발생시키지 않습니다.
 */
async function load() {
  const response = await window.axios.get(
    '/tillwhite/api/employees',
  );

  data.value = response.data;
}

/**
 * 직원 관리 화면의 최초 데이터를 불러옵니다.
 *
 * 직원 관리 페이지에 처음 진입했을 때만 사용합니다.
 *
 * 처리 순서:
 *
 * 1. Laravel 서버에서 직원 목록과 필요한 데이터를 조회합니다.
 * 2. 조회한 데이터를 화면 상태(data)에 저장합니다.
 * 3. 오류가 발생하면 AppShell의 공통 오류 알림에 표시합니다.
 * 4. 성공 또는 실패와 관계없이 공통 페이지 로딩 완료를 알립니다.
 *
 * 전체 화면 로딩 자체는 메뉴 이동 전에
 * 공통 로딩(useAppLoading)에서 이미 시작되어 있습니다.
 *
 * completePageLoading()은
 * 화면 이동 시작 시점부터 최소 1초가 지났는지 확인한 뒤
 * App.vue의 공통 전체 화면 로딩을 종료합니다.
 *
 * 따라서 API 요청이 빠르게 끝나더라도
 * 로딩 화면이 순간적으로 깜빡이지 않습니다.
 *
 * 반대로 API 요청이 1초 이상 걸렸다면
 * 추가 대기 없이 요청 완료 후 로딩 화면을 종료합니다.
 */
async function initialLoad() {
  try {
    await load();
  } catch (error) {
    /**
     * 직원 목록 조회 실패 시
     * AppShell의 공통 오류 알림을 사용합니다.
     *
     * appShellRef는 로딩 제어가 아니라
     * 오류 알림(setError)을 위해서만 사용합니다.
     */
    appShellRef.value?.setError?.(
      errorMessage(
        error,
        '직원 정보를 불러오지 못했습니다.',
      ),
    );
  } finally {
    /**
     * 직원 목록 조회 성공 또는 실패와 관계없이
     * 현재 페이지의 최초 데이터 준비가 끝났음을
     * 애플리케이션 공통 로딩에 전달합니다.
     *
     * 최소 1초 표시 시간은
     * useAppLoading에서 공통으로 처리합니다.
     */
    await completePageLoading();
  }
}

/**
 * 직원 등록 버튼을 클릭했을 때 실행합니다.
 *
 * 등록 창을 바로 열지 않고
 * Laravel 서버에 직원 관리 권한(employee.manage)을 확인합니다.
 *
 * 권한 확인이 완료되면
 * Laravel Session에 저장된 직원 등록 임시저장 데이터(draft)도
 * 함께 받아 등록 양식에 복원합니다.
 */
async function openRegisterDialog(setError) {
  if (isRegisterProcessing.value) {
    return;
  }

  try {
    const response = await window.axios.get(
      '/tillwhite/api/employees/create',
    );

    /**
     * 서버에서 다시 조회한 최신 등록 선택지만 사용합니다.
     *
     * 최고 관리자(super_admin) 선택 가능 여부 등도
     * Laravel 서버가 반환한 결과를 그대로 사용합니다.
     */
    data.value.stores = response.data.stores ?? [];
    data.value.positions = response.data.positions ?? [];
    data.value.roles = response.data.roles ?? [];

    hasLoadedDraft.value = Boolean(
      response.data.draft
      && typeof response.data.draft === 'object'
      && Object.keys(response.data.draft).length > 0
    );

    /**
     * Laravel Session에 저장된 임시저장 데이터(draft)가 있다면
     * 해당 데이터를 등록 양식에 복원합니다.
     *
     * 임시저장 데이터가 없다면
     * 새로운 빈 등록 양식을 사용합니다.
     *
     * 비밀번호(password)는 임시저장되지 않으므로
     * 항상 빈 값으로 시작합니다.
     */
    form.value = createFormFromDraft(
      response.data.draft ?? null,
    );

    /**
     * 등록 창을 연 시점의 상태를 별도로 복사합니다.
     *
     * 이후 취소 / X / ESC / 바깥 영역 클릭 시
     * 이 상태와 현재 상태를 비교하여
     * 작성 내용이 변경되었는지 확인합니다.
     */
    initialRegisterForm.value = copyRegisterForm(
      form.value,
    );

    // 서버 권한 확인이 성공한 경우에만 등록 창을 엽니다.
    registerDialog.value = true;
  } catch (error) {
    registerDialog.value = false;

    setError(
      errorMessage(
        error,
        '직원을 등록할 권한이 없습니다.',
      ),
    );
  }
}

/**
 * 직원 등록 창의 닫기를 요청합니다.
 *
 * 등록 창을 연 이후 작성 내용이 변경되지 않았다면
 * 확인창 없이 바로 닫습니다.
 *
 * 작성 내용이 변경되었다면
 * 실수로 입력 내용을 잃는 것을 방지하기 위해
 * 공통 확인창(ConfirmDialog)을 표시합니다.
 *
 * 취소는 Laravel Session에 이미 저장된
 * 임시저장 내용(draft)을 삭제하지 않습니다.
 */
function requestCloseRegisterDialog() {
  if (isRegisterProcessing.value) {
    return;
  }

  if (
    !hasLoadedDraft.value
    && !hasRegisterFormChanges()
  ) {
    closeRegisterDialog();
    return;
  }

  openConfirmDialog(
    'cancel',
    '직원 등록 취소',
    '입력 중인 내용을 취소하시겠습니까?',
  );
}

/**
 * 직원 등록 창을 실제로 닫습니다.
 *
 * 이 함수는 확인이 필요하지 않거나
 * 사용자가 취소 확인창에서 최종 확인한 경우에만 호출합니다.
 *
 * Laravel Session의 임시저장 내용(draft)은 삭제하지 않습니다.
 */
function closeRegisterDialog() {
  if (isRegisterProcessing.value) {
    return;
  }

  registerDialog.value = false;
}

/**
 * 전체 삭제 확인창을 표시합니다.
 *
 * 실제 등록된 직원 정보는 삭제하지 않습니다.
 */
function requestDeleteDraft() {
  openConfirmDialog(
    'delete',
    '전체 삭제',
    '입력된 값을 전체 삭제하시겠습니까?',
  );
}

/**
 * 임시저장 확인창을 표시합니다.
 */
function requestSaveDraft() {
  openConfirmDialog(
    'draft',
    '임시저장',
    '입력된 내용을 임시저장하시겠습니까?',
  );
}

/**
 * 직원 등록 확인창을 표시합니다.
 *
 * 이 함수는 EmployeeRegisterDialog의
 * 프론트 입력 검사를 통과한 경우에만 호출됩니다.
 */
function requestSaveEmployee() {
  openConfirmDialog(
    'submit',
    '직원 등록',
    '입력된 내용으로 직원을 등록하시겠습니까?',
  );
}

/**
 * 공통 확인창에서 확인 버튼을 눌렀을 때
 * 현재 action에 맞는 실제 작업을 실행합니다.
 *
 * action 값은 확인 버튼을 누르는 순간 별도로 보관합니다.
 * API 처리 중에는 ConfirmDialog를 유지하여
 * 요청 진행 상태와 결과를 명확하게 관리합니다.
 */
async function handleConfirm(
  setError,
  setSuccess,
) {
  if (isRegisterProcessing.value) {
    return;
  }

  const action = confirmDialog.action;

  if (!action) {
    return;
  }

  /**
   * 등록 취소는 서버 데이터를 변경하지 않으므로
   * API 요청 없이 등록 창과 확인창을 닫습니다.
   */
  if (action === 'cancel') {
    confirmDialog.open = false;
    confirmDialog.action = null;
    confirmDialog.title = '';
    confirmDialog.message = '';

    closeRegisterDialog();
    return;
  }

  if (action === 'delete') {
    await deleteDraft(
      setError,
      setSuccess,
    );

    return;
  }

  if (action === 'draft') {
    await saveDraft(
      setError,
      setSuccess,
    );

    return;
  }

  if (action === 'submit') {
    await saveEmployee(
      setError,
      setSuccess,
    );
  }
}

/**
 * 직원 등록 내용을 Laravel Session에 임시저장합니다.
 *
 * 임시저장은 실제 직원 등록이 아니므로
 * 최종 등록처럼 모든 입력값을 필수로 요구하지 않습니다.
 *
 * 다음 값만 임시저장 대상으로 서버에 전달합니다.
 *
 * - 사원번호(employee_code)
 * - 이름(name)
 * - 휴대폰 번호(phone)
 * - 생년월일(birth_date)
 * - 입사일(hired_at)
 * - 부서(department)
 * - 소속 점포(store_id)
 * - 직급(position_id)
 * - 권한 역할(role_id)
 *
 * 비밀번호(password)는 보안을 위해
 * Laravel Session에 절대로 임시저장하지 않으며
 * 임시저장 API 요청에도 포함하지 않습니다.
 *
 * 임시저장이 정상적으로 완료되면
 * 요청 처리 상태를 먼저 해제한 뒤
 * 확인창과 직원 등록 다이얼로그를 닫고 성공 알림을 표시합니다.
 *
 * 임시저장에 실패한 경우에는
 * 작성 중인 내용을 유지하고 등록 다이얼로그도 닫지 않습니다.
 */
async function saveDraft(
  setError,
  setSuccess,
) {
  if (isRegisterProcessing.value) {
    return;
  }

  registerLoadingAction.value = 'draft';

  let saved = false;

  try {
    /**
     * 현재 작성 중인 직원 등록 내용을
     * Laravel Session 임시저장 API로 전달합니다.
     *
     * 비밀번호(password)는 의도적으로 제외합니다.
     */
    await window.axios.put(
      '/tillwhite/api/employees/draft',
      {
        employee_code:
          form.value.employee_code,

        name:
          form.value.name,

        phone:
          form.value.phone,

        birth_date:
          form.value.birth_date,

        hired_at:
          form.value.hired_at,

        department:
          form.value.department,

        store_id:
          form.value.store_id,

        position_id:
          form.value.position_id,

        role_id:
          form.value.role_id,
      },
    );

    /**
     * 서버 저장이 성공한 시점의 내용을
     * 현재 기준 상태로 갱신합니다.
     *
     * 이후 다시 등록 창을 열 경우에는
     * Laravel Session에서 저장된 draft를 다시 조회합니다.
     */
    initialRegisterForm.value = copyRegisterForm(
      form.value,
    );

    saved = true;
  } catch (error) {
    setError(
      errorMessage(
        error,
        '직원 등록 내용을 임시저장하지 못했습니다.',
      ),
    );
  } finally {
    /**
     * 다이얼로그를 닫기 전에 요청 처리 상태를 먼저 해제합니다.
     *
     * 직원 등록 다이얼로그는 요청 처리 중에는
     * 닫기 동작을 막고 있으므로 처리 상태를 먼저 종료해야 합니다.
     */
    registerLoadingAction.value = null;
    clearConfirmDialog();
  }

  if (!saved) {
    return;
  }

  registerDialog.value = false;

  setSuccess('임시 저장 하였습니다.');
}

/**
 * 직원 등록 작성 내용을 전체 삭제합니다.
 *
 * 전체 삭제는 실제 등록된 직원 정보를
 * 삭제하는 기능이 아닙니다.
 *
 * 현재 로그인 사용자의 Laravel Session에 저장되어 있는
 * 직원 등록 임시저장 내용(draft)을 삭제하고,
 * 현재 화면에 입력되어 있는 등록 양식(form)도 초기화합니다.
 *
 * 서버에서 삭제에 실패한 경우에는
 * 현재 화면의 작성 내용을 그대로 유지합니다.
 */
async function deleteDraft(
  setError,
  setSuccess,
) {
  if (isRegisterProcessing.value) {
    return;
  }

  registerLoadingAction.value = 'delete';

  try {
    /**
     * Laravel 서버에서 직원 관리 권한(employee.manage)을
     * 다시 확인한 뒤 현재 로그인 세션에 저장되어 있는
     * 직원 등록 임시저장 내용(draft)을 삭제합니다.
     */
    await window.axios.delete(
      '/tillwhite/api/employees/draft',
    );

    /**
     * 서버의 임시저장 내용(draft)이
     * 정상적으로 삭제된 경우에만
     * 현재 화면의 등록 양식도 처음 상태로 초기화합니다.
     *
     * 초기 비밀번호(password)를 포함하여
     * 현재 작성 중인 값도 모두 초기화합니다.
     */
    form.value = createEmptyForm();
    hasLoadedDraft.value = false;

    /**
     * 전체 삭제가 성공했으므로
     * 현재 빈 양식을 새로운 기준 상태로 사용합니다.
     *
     * 따라서 전체 삭제 직후 아무것도 입력하지 않은 상태에서
     * 취소하면 추가 취소 확인창 없이 바로 닫힙니다.
     */
    initialRegisterForm.value = copyRegisterForm(
      form.value,
    );

    setSuccess(
      '입력된 내용을 전체 삭제했습니다.',
    );

    /**
     * 전체 삭제 후에는 직원 등록 다이얼로그를 닫지 않습니다.
     *
     * 사용자가 바로 새로운 직원 정보를
     * 입력할 수 있도록 현재 화면을 유지합니다.
     */
  } catch (error) {
    setError(
      errorMessage(
        error,
        '직원 등록 내용을 삭제하지 못했습니다.',
      ),
    );
  } finally {
    registerLoadingAction.value = null;
    clearConfirmDialog();
  }
}

/**
 * 직원 상세보기 버튼을 클릭했을 때 실행합니다.
 *
 * 직원 카드(EmployeeCard)가 전달한 직원 정보를 기준으로
 * Laravel 서버에서 해당 직원의 최신 정보를 다시 조회합니다.
 *
 * 직원 조회 권한(employee.view)과
 * 직원 조회 가능 범위는 Laravel 서버에서 최종 확인합니다.
 */
async function openDetailDialog(employee, setError) {
  selectedEmployee.value = null;
  detailDialog.value = false;

  try {
    const response = await window.axios.get(
      `/tillwhite/api/employees/${employee.id}`,
    );

    const latestEmployee = response.data.employee;

    selectedEmployee.value = latestEmployee;

    statusForm.value = {
      employment_status:
        latestEmployee.employment_status ?? 'active',
    };

    /**
     * Laravel 서버의 직원 상세조회가 성공한 경우에만
     * 직원 상세보기 컴포넌트를 엽니다.
     */
    detailDialog.value = true;
  } catch (error) {
    selectedEmployee.value = null;
    detailDialog.value = false;

    setError(
      errorMessage(
        error,
        '직원 정보를 열람할 권한이 없습니다.',
      ),
    );
  }
}

/**
 * 직원 상세보기 창을 닫고
 * 선택된 직원 정보를 초기화합니다.
 */
function closeDetailDialog() {
  detailDialog.value = false;
  selectedEmployee.value = null;

  statusForm.value = {
    employment_status: 'active',
  };
}

/**
 * 신규 직원을 서버에 등록합니다.
 *
 * EmployeeRegisterDialog에서 프론트 입력 검사를
 * 통과한 경우에만 이 함수가 호출됩니다.
 *
 * 하지만 프론트 검사는 개발자 도구 등으로 우회할 수 있으므로
 * Laravel 서버에서 모든 값을 다시 검증해야 합니다.
 *
 * 실제 등록 요청 순간에도 Laravel 서버가
 * 직원 관리 권한(employee.manage)을 다시 검사합니다.
 *
 * 등록이 정상적으로 완료되면 Laravel 서버에서
 * 직원 등록 임시저장 데이터(draft)도 삭제합니다.
 */
async function saveEmployee(
  setError,
  setSuccess,
) {
  if (isRegisterProcessing.value) {
    return;
  }

  registerLoadingAction.value = 'submit';

  try {
    await window.axios.post(
      '/tillwhite/api/employees',
      form.value,
    );

    /**
     * 등록 성공 후 등록 창과 확인창을 닫고
     * 현재 작성 양식을 초기화합니다.
     */
    registerDialog.value = false;

    form.value = createEmptyForm();
    hasLoadedDraft.value = false;
    initialRegisterForm.value = copyRegisterForm(
      form.value,
    );

    /**
     * 등록된 직원을 목록에 반영하기 위해 다시 조회합니다.
     *
     * 최초 페이지 진입이 아니므로
     * 공통 전체 화면 로딩은 사용하지 않습니다.
     */
    await load();

    setSuccess('직원을 등록했습니다.');
  } catch (error) {
    setError(
      errorMessage(
        error,
        '직원 등록에 실패했습니다.',
      ),
    );
  } finally {
    registerLoadingAction.value = null;
    clearConfirmDialog();
  }
}

/**
 * 선택한 직원의 재직 상태를 서버에 저장합니다.
 *
 * 화면에서는 재직 상태(employment_status)만 전송합니다.
 *
 * 계정 활성 상태(is_active)와 퇴사일(resigned_at)은
 * Laravel 서버가 재직 상태를 기준으로 결정합니다.
 *
 * 실제 직원 관리 권한(employee.manage)은
 * Laravel 서버에서 다시 확인합니다.
 */
async function saveStatus(setError) {
  if (!selectedEmployee.value) {
    return;
  }

  try {
    await window.axios.put(
      `/tillwhite/api/employees/${selectedEmployee.value.id}/status`,
      {
        employment_status:
          statusForm.value.employment_status,
      },
    );

    closeDetailDialog();

    /**
     * 변경된 재직 상태를 직원 카드에도 반영합니다.
     *
     * 최초 페이지 진입이 아니므로
     * 공통 전체 화면 로딩은 사용하지 않습니다.
     */
    await load();
  } catch (error) {
    setError(
      errorMessage(
        error,
        '직원 재직 상태 변경에 실패했습니다.',
      ),
    );
  }
}

/**
 * 직원 관리 페이지 최초 진입 처리입니다.
 *
 * EmployeePage가 마운트되면
 * 최초 직원 데이터를 불러옵니다.
 *
 * 메뉴 이동 전에 시작된 애플리케이션 공통 로딩은
 * initialLoad()에서 직원 데이터 조회가 끝난 뒤 완료 처리합니다.
 *
 * 직원 등록 완료 또는 재직 상태 변경 후의 재조회는
 * load()만 사용하므로 전체 화면 로딩은 다시 발생하지 않습니다.
 */
onMounted(() => {
  initialLoad();
});
</script>