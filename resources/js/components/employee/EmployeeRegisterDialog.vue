<template>
  <v-dialog
    :model-value="modelValue"
    :persistent="isProcessing"
    max-width="620"
    @update:model-value="handleDialogChange"
  >
    <v-card
      class="employee-register-dialog"
      rounded="lg"
    >
      <!--
        직원 등록 고정 헤더

        헤더는 스크롤 영역 밖에 두어
        등록 항목이 많아져도 항상 위에 표시합니다.
      -->
      <div class="register-header">
        <div class="register-header-icon">
          <v-icon
            icon="mdi-account-plus-outline"
            size="22"
          />
        </div>

        <div class="min-width-0">
          <div class="text-h6 font-weight-bold">
            직원 등록
          </div>

          <div class="text-body-2 text-medium-emphasis mt-1">
            새로운 직원의 기본 정보와 소속 정보를 등록합니다.
          </div>
        </div>
      </div>

      <v-divider />

      <!--
        직원 등록 내용

        다이얼로그 높이를 넘어가는 경우
        가운데 영역에만 스크롤을 적용합니다.
      -->
      <div
        ref="scrollAreaRef"
        class="register-scroll-area"
      >
        <!--
          등록 검증 공통 알림

          직접 Vuetify Alert를 만들지 않고
          애플리케이션 공통 알림(AppAlert)을 사용합니다.

          모든 알림은 사용자가 직접 닫을 수 있습니다.

          검증 알림을 닫더라도 입력칸의 오류 상태는 유지하여
          어떤 입력값을 수정해야 하는지는 계속 확인할 수 있습니다.
        -->
        <div
          v-if="validationMessage"
          class="validation-alert-wrap"
        >
          <AppAlert
            v-model="validationMessage"
            type="warning"
            variant="tonal"
          />
        </div>

        <!-- 기본 정보 -->
        <section class="register-section">
          <div class="register-section-header">
            <div class="register-section-icon">
              <v-icon
                icon="mdi-account-outline"
                size="18"
              />
            </div>

            <div>
              <div class="register-section-title">
                기본 정보
              </div>

              <div class="register-section-description">
                직원의 기본 정보와 입사일을 입력합니다.
              </div>
            </div>
          </div>

          <div class="register-fields">
            <!-- 이름(name) -->
            <div
              ref="nameRef"
              class="field-anchor"
            >
              <v-text-field
                :model-value="form.name"
                label="이름"
                placeholder="직원 이름"
                variant="outlined"
                prepend-inner-icon="mdi-account-outline"
                autocomplete="off"
                maxlength="50"
                counter="50"
                :error-messages="fieldError('name')"
                @update:model-value="updateField('name', $event)"
              />
            </div>

            <!-- 휴대폰 번호(phone) -->
            <div
              ref="phoneRef"
              class="field-anchor"
            >
              <v-text-field
                :model-value="form.phone"
                label="휴대폰 번호"
                placeholder="예: 하이픈(-) 없이 숫자만 입력해주세요."
                variant="outlined"
                prepend-inner-icon="mdi-cellphone"
                autocomplete="off"
                inputmode="numeric"
                maxlength="11"
                counter="11"
                persistent-hint
                :error-messages="fieldError('phone')"
                @update:model-value="updateNumericField('phone', $event, 11)"
              />
            </div>

            <!-- 생년월일(birth_date) -->
            <div
              ref="birthDateRef"
              class="field-anchor"
            >
              <v-text-field
                :model-value="form.birth_date"
                label="생년월일"
                type="date"
                variant="outlined"
                prepend-inner-icon="mdi-cake-variant-outline"
                :max="today"
                :error-messages="fieldError('birth_date')"
                @update:model-value="updateField('birth_date', $event)"
              />
            </div>

            <!-- 입사일(hired_at) -->
            <div
              ref="hiredAtRef"
              class="field-anchor"
            >
              <v-text-field
                :model-value="form.hired_at"
                label="입사일"
                type="date"
                variant="outlined"
                prepend-inner-icon="mdi-calendar-check-outline"
                :error-messages="fieldError('hired_at')"
                @update:model-value="updateField('hired_at', $event)"
              />
            </div>
          </div>
        </section>

        <v-divider />

        <!-- 계정 정보 -->
        <section class="register-section">
          <div class="register-section-header">
            <div class="register-section-icon">
              <v-icon
                icon="mdi-lock-outline"
                size="18"
              />
            </div>

            <div>
              <div class="register-section-title">
                계정 정보
              </div>

              <div class="register-section-description">
                직원이 로그인할 사원번호와 초기 비밀번호를 설정합니다.
              </div>
            </div>
          </div>

          <div class="register-fields">
            <!-- 사원번호(employee_code) -->
            <div
              ref="employeeCodeRef"
              class="field-anchor"
            >
              <v-text-field
                :model-value="form.employee_code"
                label="사원번호"
                placeholder="숫자만 입력"
                variant="outlined"
                prepend-inner-icon="mdi-card-account-details-outline"
                autocomplete="off"
                inputmode="numeric"
                maxlength="20"
                counter="20"
                :error-messages="fieldError('employee_code')"
                @update:model-value="updateNumericField('employee_code', $event, 20)"
              />
            </div>

            <!-- 초기 비밀번호(password) -->
            <div
              ref="passwordRef"
              class="field-anchor"
            >
              <v-text-field
                :model-value="form.password"
                label="초기 비밀번호"
                placeholder="8자 이상 입력"
                :type="showPassword ? 'text' : 'password'"
                variant="outlined"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="
                  showPassword
                    ? 'mdi-eye-off-outline'
                    : 'mdi-eye-outline'
                "
                autocomplete="new-password"
                maxlength="72"
                counter="72"
                hint="8자 이상 72자 이하로 입력해주세요."
                persistent-hint
                :error-messages="fieldError('password')"
                @click:append-inner="showPassword = !showPassword"
                @update:model-value="updateField('password', $event)"
              />
            </div>
          </div>
        </section>

        <v-divider />

        <!-- 소속 정보 -->
        <section class="register-section">
          <div class="register-section-header">
            <div class="register-section-icon">
              <v-icon
                icon="mdi-office-building-outline"
                size="18"
              />
            </div>

            <div>
              <div class="register-section-title">
                소속 정보
              </div>

              <div class="register-section-description">
                직원의 근무 부서와 점포, 직급, 권한 역할을 설정합니다.
              </div>
            </div>
          </div>

          <!--
            PC에서는 2열,
            모바일에서는 1열로 표시합니다.
          -->
          <div class="register-grid">
            <!-- 소속 부서(department) -->
            <div
              ref="departmentRef"
              class="field-anchor"
            >
              <v-select
                :model-value="form.department"
                :items="departments"
                label="부서"
                variant="outlined"
                prepend-inner-icon="mdi-office-building-outline"
                :error-messages="fieldError('department')"
                @update:model-value="updateDepartment"
              />
            </div>

            <!--
              소속 점포(store_id)

              본사(head_office)는 특정 점포에
              소속되지 않으므로 표시하지 않습니다.
            -->
            <div
              v-if="form.department !== 'head_office'"
              ref="storeRef"
              class="field-anchor"
            >
              <v-select
                :model-value="form.store_id"
                :items="stores"
                item-title="name"
                item-value="id"
                label="점포"
                variant="outlined"
                prepend-inner-icon="mdi-store-outline"
                :error-messages="fieldError('store_id')"
                @update:model-value="updateField('store_id', $event)"
              />
            </div>

            <!--
              본사(head_office)를 선택한 경우
              점포 선택 대신 본사 소속 안내를 표시합니다.
            -->
            <div
              v-else
              class="head-office-info"
            >
              <div class="head-office-info-icon">
                <v-icon
                  icon="mdi-office-building-marker-outline"
                  size="20"
                />
              </div>

              <div class="min-width-0">
                <div class="head-office-info-title">
                  본사 소속
                </div>

                <div class="head-office-info-description">
                  본사 직원은 특정 점포에 소속되지 않습니다.
                </div>
              </div>
            </div>

            <!-- 회사 직급(position_id) -->
            <div
              ref="positionRef"
              class="field-anchor"
            >
              <v-select
                :model-value="form.position_id"
                :items="positions"
                item-title="name"
                item-value="id"
                label="직급"
                variant="outlined"
                prepend-inner-icon="mdi-badge-account-outline"
                :error-messages="fieldError('position_id')"
                @update:model-value="updateField('position_id', $event)"
              />
            </div>

            <!-- 권한 역할(role_id) -->
            <div
              ref="roleRef"
              class="field-anchor"
            >
              <v-select
                :model-value="form.role_id"
                :items="filteredRoles"
                item-title="name"
                item-value="id"
                label="권한 역할"
                variant="outlined"
                prepend-inner-icon="mdi-shield-account-outline"
                :disabled="!form.department"
                :error-messages="fieldError('role_id')"
                @update:model-value="updateField('role_id', $event)"
              />
            </div>
          </div>

          <!--
            권한 역할(role)은 실제 접근 권한에 영향을 주므로
            회사 직급(position)과 별개의 값임을 안내합니다.
          -->
          <div class="role-notice">
            <v-icon
              icon="mdi-information-outline"
              size="17"
            />

            <span>
              권한 역할에 따라 사용할 수 있는 메뉴와 기능이 달라집니다.
            </span>
          </div>
        </section>
      </div>

      <v-divider />

      <!--
        직원 등록 고정 하단 영역

        순서:
        취소 → 전체 삭제 → 임시저장 → 등록

        loadingAction에는 현재 실행 중인 작업 하나만 저장합니다.

        가능한 값:
        - null
        - delete
        - draft
        - submit

        따라서 실제로 실행한 버튼에만
        로딩 표시가 나타납니다.

        다른 버튼은 처리 중에 비활성화만 하여
        중복 요청을 방지합니다.
      -->
      <div class="register-actions">
        <v-btn
          variant="text"
          :disabled="isProcessing"
          @click="close"
        >
          취소
        </v-btn>

        <v-spacer />

        <div class="register-action-buttons">
          <!-- 직원 등록 내용 전체 삭제 -->
          <v-btn
            variant="text"
            prepend-icon="mdi-delete-outline"
            :loading="loadingAction === 'delete'"
            :disabled="isProcessing"
            @click="deleteDraft"
          >
            전체 삭제
          </v-btn>

          <!-- 직원 등록 내용 임시저장 -->
          <v-btn
            variant="flat"
            prepend-icon="mdi-content-save-outline"
            :loading="loadingAction === 'draft'"
            :disabled="isProcessing"
            @click="saveDraft"
          >
            임시저장
          </v-btn>

          <!-- 실제 직원 등록 -->
          <v-btn
            variant="flat"
            prepend-icon="mdi-account-plus-outline"
            :loading="loadingAction === 'submit'"
            :disabled="isProcessing"
            @click="validateAndSubmit"
          >
            등록
          </v-btn>
        </div>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import {
  computed,
  nextTick,
  ref,
  watch,
} from 'vue';

import AppAlert from '../common/AppAlert.vue';

/**
 * 직원 등록 다이얼로그(EmployeeRegisterDialog)
 *
 * 이 컴포넌트는 직원 등록 화면과
 * 화면에서 먼저 수행하는 입력값 검사를 담당합니다.
 *
 * 프론트 검사는 사용자가 잘못 입력한 값을
 * 빠르게 확인할 수 있도록 하기 위한 화면 검사입니다.
 *
 * 실제 등록 권한과 최종 입력값 검사는
 * Laravel 서버에서 반드시 다시 수행합니다.
 *
 * API 요청과 공통 확인창(ConfirmDialog)은
 * 부모 화면(EmployeePage)에서 관리합니다.
 */
const props = defineProps({
  /**
   * 다이얼로그 열림/닫힘 상태입니다.
   */
  modelValue: {
    type: Boolean,
    default: false,
  },

  /**
   * 부모 화면(EmployeePage)이 관리하는
   * 직원 등록 양식(form)입니다.
   */
  form: {
    type: Object,
    required: true,
  },

  /**
   * Laravel 서버에서 받은
   * 등록 가능한 점포 목록입니다.
   */
  stores: {
    type: Array,
    default: () => [],
  },

  /**
   * Laravel 서버에서 받은
   * 등록 가능한 직급 목록입니다.
   */
  positions: {
    type: Array,
    default: () => [],
  },

  /**
   * Laravel 서버에서 받은
   * 등록 가능한 권한 역할(role) 목록입니다.
   *
   * 최고 관리자(super_admin) 선택 가능 여부 등은
   * Laravel 서버가 반환한 목록을 그대로 사용합니다.
   */
  roles: {
    type: Array,
    default: () => [],
  },

  /**
   * 현재 실행 중인 직원 등록 관련 작업입니다.
   *
   * null:
   * - 실행 중인 작업 없음
   *
   * delete:
   * - 전체 삭제 진행 중
   *
   * draft:
   * - 임시저장 진행 중
   *
   * submit:
   * - 실제 직원 등록 진행 중
   *
   * 하나의 값으로 현재 작업을 관리하여
   * 여러 버튼의 로딩 상태가 동시에 켜지는 것을 방지합니다.
   */
  loadingAction: {
    type: String,
    default: null,
    validator: (value) => (
      value === null
      || [
        'delete',
        'draft',
        'submit',
      ].includes(value)
    ),
  },
});

const emit = defineEmits([
  'update:modelValue',
  'update:form',
  'submit',
  'draft',
  'delete',
  'close',
]);

/**
 * 현재 직원 등록 관련 요청이
 * 하나라도 진행 중인지 확인합니다.
 *
 * 실행 중인 작업이 있으면
 * 다른 버튼과 다이얼로그 닫기 동작을 막아
 * 중복 요청을 방지합니다.
 */
const isProcessing = computed(
  () => props.loadingAction !== null,
);

/**
 * 초기 비밀번호 표시 여부입니다.
 */
const showPassword = ref(false);

/**
 * 현재 화면에 표시할 첫 번째 입력 오류입니다.
 *
 * 한 번에 여러 오류를 표시하지 않고
 * 화면 위쪽에서 가장 먼저 발견된 오류 하나만 표시합니다.
 */
const invalidField = ref('');
const validationMessage = ref('');

/**
 * 입력 영역 스크롤 위치를 제어하기 위한 참조입니다.
 */
const scrollAreaRef = ref(null);

/**
 * 각 입력칸으로 이동하기 위한 참조입니다.
 */
const employeeCodeRef = ref(null);
const nameRef = ref(null);
const phoneRef = ref(null);
const birthDateRef = ref(null);
const passwordRef = ref(null);
const departmentRef = ref(null);
const storeRef = ref(null);
const positionRef = ref(null);
const roleRef = ref(null);
const hiredAtRef = ref(null);

/**
 * 직원이 소속될 수 있는 부서(department) 목록입니다.
 */
const departments = [
  {
    title: '주방',
    value: 'kitchen',
  },
  {
    title: '홀',
    value: 'hall',
  },
  {
    title: '본사',
    value: 'head_office',
  },
];

/**
 * 소속 부서(department)별로 선택할 수 있는
 * 권한 역할(role) 코드를 정의합니다.
 *
 * 화면에서는 현재 선택한 부서에 맞는 역할만 표시하지만,
 * 실제 권한 역할 지정 가능 여부는 Laravel 서버에서도
 * 동일한 기준으로 다시 검사해야 합니다.
 */
const departmentRoleCodes = {
  kitchen: [
    'staff',
    'kitchen_head',
  ],
  hall: [
    'staff',
    'hall_manager',
  ],
  head_office: [
    'head_office_staff',
    'head_office_manager',
  ],
};

/**
 * 현재 선택한 소속 부서(department)에 맞는
 * 권한 역할(role)만 화면에 표시합니다.
 *
 * 부서를 아직 선택하지 않았다면
 * 권한 역할을 선택할 수 없도록 빈 목록을 반환합니다.
 */
const filteredRoles = computed(() => {
  const allowedCodes = departmentRoleCodes[
    props.form.department
  ];

  if (!allowedCodes) {
    return [];
  }

  return props.roles.filter(
    (role) => allowedCodes.includes(role.code),
  );
});

/**
 * 생년월일에서 미래 날짜를 선택하지 못하도록
 * 오늘 날짜를 YYYY-MM-DD 형식으로 만듭니다.
 */
const today = new Date().toLocaleDateString('sv-SE');

/**
 * 다이얼로그가 닫히면
 * 비밀번호 표시 상태와 입력 오류를 초기화합니다.
 */
watch(
  () => props.modelValue,
  (value) => {
    if (!value) {
      showPassword.value = false;
      clearValidation();
    }
  },
);

/**
 * 현재 오류가 발생한 입력칸에만
 * 오류 메시지를 표시합니다.
 */
function fieldError(field) {
  if (invalidField.value !== field) {
    return [];
  }

  return validationMessage.value
    ? [validationMessage.value]
    : [];
}

/**
 * 입력값이 비어 있는지 확인합니다.
 *
 * 숫자형 ID는 0이 아닌 실제 ID를 사용하므로
 * null, undefined, 빈 문자열만 빈 값으로 판단합니다.
 */
function isEmpty(value) {
  return (
    value === null
    || value === undefined
    || String(value).trim() === ''
  );
}

/**
 * 현재 표시 중인 입력 오류를 제거합니다.
 */
function clearValidation() {
  invalidField.value = '';
  validationMessage.value = '';
}

/**
 * 현재 오류가 표시된 입력칸을 수정하면
 * 기존 오류 표시를 제거합니다.
 */
function clearFieldValidation(field) {
  if (invalidField.value === field) {
    clearValidation();
  }
}

/**
 * 직원 등록 양식(form)의 특정 값을 변경합니다.
 *
 * 부모 화면의 객체를 직접 수정하지 않고
 * 새로운 객체를 만들어 부모 화면(EmployeePage)에 전달합니다.
 */
function updateField(field, value) {
  clearFieldValidation(field);

  emit('update:form', {
    ...props.form,
    [field]: value,
  });
}

/**
 * 숫자만 허용하는 입력값을 처리합니다.
 *
 * 사원번호(employee_code)와 휴대폰 번호(phone)는
 * 숫자로 계산하는 값이 아니므로 문자열로 유지합니다.
 *
 * 숫자가 아닌 문자는 입력 단계에서 제거하고
 * 지정한 최대 길이를 넘는 값도 잘라냅니다.
 */
function updateNumericField(field, value, maxLength) {
  const numericValue = String(value ?? '')
    .replace(/\D/g, '')
    .slice(0, maxLength);

  updateField(field, numericValue);
}

/**
 * 소속 부서(department)를 변경합니다.
 *
 * 본사(head_office)를 선택하면
 * 소속 점포(store_id)는 반드시 null로 초기화합니다.
 *
 * 기존에 선택한 권한 역할(role_id)이
 * 새로 선택한 부서에서 사용할 수 없는 역할이라면
 * 잘못된 조합이 남지 않도록 null로 초기화합니다.
 */
function updateDepartment(value) {
  clearFieldValidation('department');

  if (
    invalidField.value === 'store_id'
    || invalidField.value === 'role_id'
  ) {
    clearValidation();
  }

  const nextForm = {
    ...props.form,
    department: value,
  };

  if (value === 'head_office') {
    nextForm.store_id = null;
  }

  const allowedCodes = departmentRoleCodes[value];

  if (!allowedCodes) {
    nextForm.role_id = null;
  } else if (!isEmpty(nextForm.role_id)) {
    const selectedRole = props.roles.find(
      (role) => (
        String(role.id)
        === String(nextForm.role_id)
      ),
    );

    if (
      !selectedRole
      || !allowedCodes.includes(selectedRole.code)
    ) {
      nextForm.role_id = null;
    }
  }

  emit('update:form', nextForm);
}

/**
 * 오류가 발생한 입력칸의 화면 참조를 반환합니다.
 */
function getFieldRef(field) {
  const refs = {
    name: nameRef,
    phone: phoneRef,
    birth_date: birthDateRef,
    hired_at: hiredAtRef,
    employee_code: employeeCodeRef,
    password: passwordRef,
    department: departmentRef,
    store_id: storeRef,
    position_id: positionRef,
    role_id: roleRef,
  };

  return refs[field]?.value ?? null;
}

/**
 * 첫 번째 오류가 발생한 입력칸으로 이동하고
 * 가능한 경우 실제 입력 요소에 포커스를 줍니다.
 */
async function focusInvalidField(field) {
  await nextTick();

  const target = getFieldRef(field);

  if (!target) {
    return;
  }

  target.scrollIntoView({
    behavior: 'smooth',
    block: 'center',
  });

  await nextTick();

  const input = target.querySelector(
    'input, textarea, [tabindex]:not([tabindex="-1"])',
  );

  input?.focus?.({
    preventScroll: true,
  });
}

/**
 * 첫 번째 입력 오류를 화면에 표시합니다.
 */
function showValidationError(field, message) {
  invalidField.value = field;
  validationMessage.value = message;

  focusInvalidField(field);
}

/**
 * 등록 버튼을 눌렀을 때
 * 실제 화면에 표시되는 입력칸 순서대로 검사합니다.
 *
 * 검사 순서:
 * 이름 → 휴대폰 번호 → 생년월일 → 입사일
 * → 사원번호 → 초기 비밀번호
 * → 부서 → 점포 → 직급 → 권한 역할
 *
 * 한 번에 모든 오류를 표시하지 않고
 * 첫 번째 오류만 사용자에게 안내합니다.
 *
 * 이 검사는 화면 편의를 위한 검사이며,
 * Laravel 서버에서도 동일하거나 더 엄격한 검사를 다시 수행합니다.
 */
function validateAndSubmit() {
  if (isProcessing.value) {
    return;
  }

  clearValidation();

  const name = String(props.form.name ?? '').trim();
  const phone = String(props.form.phone ?? '');
  const birthDate = String(props.form.birth_date ?? '');
  const hiredAt = String(props.form.hired_at ?? '');
  const employeeCode = String(props.form.employee_code ?? '');
  const password = String(props.form.password ?? '');
  const department = props.form.department;
  const storeId = props.form.store_id;
  const positionId = props.form.position_id;
  const roleId = props.form.role_id;

  /**
   * 1. 이름(name)
   */
  if (isEmpty(name)) {
    showValidationError(
      'name',
      '이름을 입력해주세요.',
    );

    return;
  }

  if (name.length > 50) {
    showValidationError(
      'name',
      '이름은 최대 50자까지 입력할 수 있습니다.',
    );

    return;
  }

  /**
   * 2. 휴대폰 번호(phone)
   */
  if (isEmpty(phone)) {
    showValidationError(
      'phone',
      '휴대폰 번호를 입력해주세요.',
    );

    return;
  }

  if (!/^\d+$/.test(phone)) {
    showValidationError(
      'phone',
      '휴대폰 번호는 하이픈(-) 없이 숫자만 입력해주세요.',
    );

    return;
  }

  if (!/^010\d{8}$/.test(phone)) {
    showValidationError(
      'phone',
      '휴대폰 번호는 010으로 시작하는 11자리 숫자로 입력해주세요.',
    );

    return;
  }

  /**
   * 3. 생년월일(birth_date)
   */
  if (isEmpty(birthDate)) {
    showValidationError(
      'birth_date',
      '생년월일을 입력해주세요.',
    );

    return;
  }

  if (birthDate > today) {
    showValidationError(
      'birth_date',
      '생년월일은 오늘 이후 날짜를 선택할 수 없습니다.',
    );

    return;
  }

  /**
   * 4. 입사일(hired_at)
   */
  if (isEmpty(hiredAt)) {
    showValidationError(
      'hired_at',
      '입사일을 입력해주세요.',
    );

    return;
  }

  /**
   * 5. 사원번호(employee_code)
   */
  if (isEmpty(employeeCode)) {
    showValidationError(
      'employee_code',
      '사원번호를 입력해주세요.',
    );

    return;
  }

  if (!/^\d+$/.test(employeeCode)) {
    showValidationError(
      'employee_code',
      '사원번호는 숫자만 입력할 수 있습니다.',
    );

    return;
  }

  if (employeeCode.length > 20) {
    showValidationError(
      'employee_code',
      '사원번호는 최대 20자리까지 입력할 수 있습니다.',
    );

    return;
  }

  /**
   * 6. 초기 비밀번호(password)
   */
  if (isEmpty(password)) {
    showValidationError(
      'password',
      '초기 비밀번호를 입력해주세요.',
    );

    return;
  }

  if (password.length < 8) {
    showValidationError(
      'password',
      '초기 비밀번호는 8자 이상 입력해주세요.',
    );

    return;
  }

  if (password.length > 72) {
    showValidationError(
      'password',
      '초기 비밀번호는 최대 72자까지 입력할 수 있습니다.',
    );

    return;
  }

  /**
   * 7. 소속 부서(department)
   */
  if (isEmpty(department)) {
    showValidationError(
      'department',
      '부서를 선택해주세요.',
    );

    return;
  }

  if (
    ![
      'kitchen',
      'hall',
      'head_office',
    ].includes(department)
  ) {
    showValidationError(
      'department',
      '올바른 부서를 선택해주세요.',
    );

    return;
  }

  /**
   * 8. 소속 점포(store_id)
   *
   * 본사(head_office)는 특정 점포에 소속되지 않으므로
   * 점포 선택 검사를 하지 않습니다.
   */
  if (
    department !== 'head_office'
    && isEmpty(storeId)
  ) {
    showValidationError(
      'store_id',
      '점포를 선택해주세요.',
    );

    return;
  }

  /**
   * 9. 직급(position_id)
   */
  if (isEmpty(positionId)) {
    showValidationError(
      'position_id',
      '직급을 선택해주세요.',
    );

    return;
  }

  /**
   * 10. 권한 역할(role_id)
   *
   * 권한 역할이 선택되어 있는지만 확인하지 않고,
   * 실제 서버에서 받은 역할인지와 현재 선택한
   * 소속 부서(department)에서 사용할 수 있는 역할인지도 확인합니다.
   */
  if (isEmpty(roleId)) {
    showValidationError(
      'role_id',
      '권한 역할을 선택해주세요.',
    );

    return;
  }

  const selectedRole = props.roles.find(
    (role) => (
      String(role.id)
      === String(roleId)
    ),
  );

  const allowedRoleCodes = departmentRoleCodes[
    department
  ];

  if (
    !selectedRole
    || !allowedRoleCodes
    || !allowedRoleCodes.includes(selectedRole.code)
  ) {
    showValidationError(
      'role_id',
      '선택한 부서에서 사용할 수 없는 권한 역할입니다.',
    );

    return;
  }

  /**
   * 프론트 입력 검사를 모두 통과한 경우에만
   * 부모 화면(EmployeePage)에 등록 확인 요청을 전달합니다.
   *
   * 여기에서 직접 API를 호출하지 않습니다.
   * 부모 화면에서 공통 확인창(ConfirmDialog)을 표시한 뒤
   * 사용자가 최종 확인한 경우에만 실제 등록 요청을 실행합니다.
   */
  emit('submit');
}

/**
 * 임시저장 버튼을 눌렀을 때
 * 현재 작성 중인 내용을 부모 화면에 전달합니다.
 *
 * 임시저장은 작성 중인 데이터를 보관하는 기능이므로
 * 필수 입력값 검사를 수행하지 않습니다.
 *
 * 실제 저장 전 확인 여부와 Laravel Session 저장은
 * 부모 화면(EmployeePage)에서 처리합니다.
 */
function saveDraft() {
  if (isProcessing.value) {
    return;
  }

  clearValidation();
  emit('draft');
}

/**
 * 전체 삭제 버튼을 눌렀을 때
 * 부모 화면(EmployeePage)에 삭제 확인 요청을 전달합니다.
 *
 * 이 컴포넌트에서는 직접 등록 양식을 초기화하거나
 * Laravel Session의 임시저장 내용(draft)을 삭제하지 않습니다.
 *
 * 실제 등록된 직원 데이터에는 영향을 주지 않습니다.
 */
function deleteDraft() {
  if (isProcessing.value) {
    return;
  }

  clearValidation();
  emit('delete');
}

/**
 * X / ESC / 바깥 영역 클릭으로
 * Vuetify가 다이얼로그를 닫으려고 할 때 실행합니다.
 *
 * 여기에서는 modelValue를 직접 false로 변경하지 않습니다.
 *
 * 작성 내용이 있는 경우에는 부모 화면(EmployeePage)이
 * 공통 확인창(ConfirmDialog)을 먼저 표시해야 하기 때문입니다.
 *
 * 부모 화면이 닫기를 승인한 경우에만
 * registerDialog를 false로 변경하여 실제로 닫습니다.
 *
 * API 요청 처리 중에는 닫기 요청 자체를 무시합니다.
 */
function handleDialogChange(value) {
  if (value) {
    return;
  }

  if (isProcessing.value) {
    return;
  }

  emit('close');
}

/**
 * 취소 버튼을 눌렀을 때
 * 부모 화면(EmployeePage)에 닫기 요청만 전달합니다.
 *
 * 여기에서는 modelValue를 직접 변경하거나
 * 작성 중인 내용을 삭제하지 않습니다.
 *
 * 부모 화면이 작성 여부를 확인한 뒤
 * 바로 닫거나 공통 확인창(ConfirmDialog)을 표시합니다.
 */
function close() {
  if (isProcessing.value) {
    return;
  }

  emit('close');
}
</script>

<style scoped>
/*
 * 직원 등록 다이얼로그 전체입니다.
 *
 * 다이얼로그 전체에는 스크롤을 적용하지 않고
 * 가운데 입력 영역에만 스크롤을 적용합니다.
 */
.employee-register-dialog {
  display: flex;
  max-height: calc(100vh - 48px);
  flex-direction: column;
  overflow: hidden;
}

/*
 * 직원 등록 헤더입니다.
 */
.register-header {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 14px;
  padding: 20px;
}

/*
 * 헤더 왼쪽 직원 등록 아이콘입니다.
 */
.register-header-icon {
  display: flex;
  width: 42px;
  height: 42px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(
    var(--v-border-color),
    var(--v-border-opacity)
  );
  border-radius: 10px;
}

/*
 * 실제 입력 폼이 들어가는 영역입니다.
 *
 * 입력 항목이 화면보다 많아지는 경우
 * 이 영역에만 세로 스크롤이 표시됩니다.
 */
.register-scroll-area {
  min-height: 0;
  flex: 1 1 auto;
  overflow-x: hidden;
  overflow-y: auto;
}

/*
 * 등록 입력값에 오류가 있을 때
 * 입력 영역 가장 위쪽에 표시하는 안내입니다.
 */
.validation-alert-wrap {
  padding: 16px 20px 0;
}

/*
 * 공통 Alert 자체에 기본 하단 여백이 있으므로
 * 검증 알림 아래에 불필요한 공간이 생기지 않도록
 * 이 영역에서 마지막 여백을 제거합니다.
 */
.validation-alert-wrap :deep(.v-alert) {
  margin-bottom: 0 !important;
}

/*
 * 기본 정보 / 계정 정보 / 소속 정보 영역입니다.
 */
.register-section {
  padding: 22px 20px;
}

/*
 * 각 영역 제목입니다.
 */
.register-section-header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 20px;
}

/*
 * 각 영역의 작은 아이콘입니다.
 */
.register-section-icon {
  display: flex;
  width: 30px;
  height: 30px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(
    var(--v-border-color),
    var(--v-border-opacity)
  );
  border-radius: 8px;
}

/*
 * 각 영역 제목입니다.
 */
.register-section-title {
  font-size: 0.9rem;
  font-weight: 700;
  line-height: 1.35;
}

/*
 * 각 영역 제목 아래 설명입니다.
 */
.register-section-description {
  margin-top: 3px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.75rem;
  line-height: 1.4;
}

/*
 * 한 줄 전체를 사용하는 입력 영역입니다.
 */
.register-fields {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/*
 * 입력 오류가 발생했을 때
 * 해당 입력칸으로 스크롤하기 위한 기준 영역입니다.
 */
.field-anchor {
  min-width: 0;
}

/*
 * 소속 정보 입력 영역입니다.
 *
 * PC에서는 2열로 표시하여
 * 다이얼로그의 세로 길이를 줄입니다.
 */
.register-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 2px 12px;
}

/*
 * 본사(head_office)를 선택했을 때
 * 점포 선택 대신 표시하는 안내 영역입니다.
 */
.head-office-info {
  display: flex;
  min-height: 56px;
  align-items: center;
  gap: 10px;
  margin-bottom: 22px;
  padding: 8px 12px;
  border: 1px solid rgba(
    var(--v-border-color),
    var(--v-border-opacity)
  );
  border-radius: 4px;
}

/*
 * 본사 안내 아이콘입니다.
 */
.head-office-info-icon {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
}

/*
 * 본사 안내 제목입니다.
 */
.head-office-info-title {
  font-size: 0.8rem;
  font-weight: 600;
}

/*
 * 본사 안내 설명입니다.
 */
.head-office-info-description {
  margin-top: 2px;
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.7rem;
  line-height: 1.3;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/*
 * 권한 역할(role)에 대한 안내입니다.
 */
.role-notice {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  padding: 10px 12px;
  border: 1px solid rgba(
    var(--v-border-color),
    var(--v-border-opacity)
  );
  border-radius: 8px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.72rem;
  line-height: 1.5;
}

/*
 * 취소 / 전체 삭제 / 임시저장 / 등록 버튼 영역입니다.
 *
 * 스크롤 영역 밖에 있기 때문에
 * 항상 다이얼로그 하단에 표시됩니다.
 */
.register-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  padding: 14px 20px;
}

/*
 * 전체 삭제 / 임시저장 / 등록 버튼을
 * 오른쪽에 나란히 배치합니다.
 */
.register-action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

/*
 * 좁은 영역에서 텍스트가
 * 다른 요소를 밀어내지 않도록 합니다.
 */
.min-width-0 {
  min-width: 0;
}

/*
 * 모바일에서는 소속 정보를 1열로 변경하고
 * 하단 버튼 크기도 화면에 맞게 조정합니다.
 */
@media (max-width: 600px) {
  .employee-register-dialog {
    max-height: calc(100vh - 24px);
  }

  .register-header {
    padding: 16px;
  }

  .validation-alert-wrap {
    padding: 14px 16px 0;
  }

  .register-section {
    padding: 20px 16px;
  }

  .register-grid {
    grid-template-columns: 1fr;
  }

  .register-actions {
    padding: 12px 16px;
  }

  .register-action-buttons {
    gap: 6px;
  }

  .head-office-info-description {
    white-space: normal;
  }
}
</style>