<template>
  <AppShell :title="pageTitle">
    <template #default="{ user, can, setError }">
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
        - 등록 가능한 시스템 역할 조회
        - 실제 직원 등록 API 호출
      -->
      <EmployeeRegisterDialog
        v-model="registerDialog"
        v-model:form="form"
        :stores="data.stores"
        :positions="data.positions"
        :roles="data.roles"
        :loading="registerLoading"
        @close="closeRegisterDialog"
        @submit="saveEmployee(setError)"
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
  onMounted,
  ref,
} from 'vue';

import AppShell from '../../components/layout/AppShell.vue';
import EmployeeCard from '../../components/employee/EmployeeCard.vue';
import EmployeeDetailDialog from '../../components/employee/EmployeeDetailDialog.vue';
import EmployeeRegisterDialog from '../../components/employee/EmployeeRegisterDialog.vue';

/**
 * 현재 페이지 제목입니다.
 */
const pageTitle = '직원 관리';

/**
 * 직원 등록 창(registerDialog)의 열림/닫힘 상태입니다.
 */
const registerDialog = ref(false);

/**
 * 직원 등록 요청 진행 상태입니다.
 *
 * 등록 요청이 진행되는 동안
 * 등록 버튼을 로딩 상태로 표시하고
 * 중복 등록 요청을 막습니다.
 */
const registerLoading = ref(false);

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
 */
function createEmptyForm() {
  return {
    employee_code: '',
    name: '',
    password: '',
    store_id: null,
    department: 'kitchen',
    position_id: null,
    role_id: null,
  };
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
 * 직원 관리 화면에 필요한 직원 목록을 조회합니다.
 *
 * 직원 조회 권한(employee.view)은
 * Laravel 서버에서 최종적으로 확인합니다.
 */
async function load() {
  const response = await window.axios.get(
    '/tillwhite/api/employees',
  );

  data.value = response.data;
}

/**
 * 직원 등록 버튼을 클릭했을 때 실행합니다.
 *
 * 등록 창을 바로 열지 않고
 * Laravel 서버에 직원 관리 권한(employee.manage)을 확인합니다.
 */
async function openRegisterDialog(setError) {
  if (registerLoading.value) {
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

    form.value = createEmptyForm();

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
 * 직원 등록 창을 닫습니다.
 *
 * 등록 요청이 진행 중일 때는
 * 중간에 등록 창을 닫지 않습니다.
 */
function closeRegisterDialog() {
  if (registerLoading.value) {
    return;
  }

  registerDialog.value = false;
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
 * 실제 등록 요청 순간에도 Laravel 서버가
 * 직원 관리 권한(employee.manage)을 다시 검사합니다.
 *
 * 등록 요청이 진행되는 동안
 * 추가 등록 요청은 실행하지 않습니다.
 */
async function saveEmployee(setError) {
  if (registerLoading.value) {
    return;
  }

  registerLoading.value = true;

  try {
    await window.axios.post(
      '/tillwhite/api/employees',
      form.value,
    );

    registerDialog.value = false;
    form.value = createEmptyForm();

    await load();
  } catch (error) {
    setError(
      errorMessage(
        error,
        '직원 등록에 실패했습니다.',
      ),
    );
  } finally {
    registerLoading.value = false;
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

    // 변경된 재직 상태를 직원 카드에도 반영합니다.
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

// 화면이 처음 열릴 때 직원 목록을 조회합니다.
onMounted(load);
</script>