<template>
  <AppDialogLayout
    :model-value="modelValue"
    title="직원 등록"
    subtitle="새로운 직원의 기본 정보와 소속 정보를 등록합니다."
    :max-width="620"
    :persistent="isProcessing"
    :busy="isProcessing"
    card-class="employee-register-dialog"
    body-class="employee-register-body"
    @update:model-value="handleDialogChange"
  >
    <div
      ref="scrollAreaRef"
      class="employee-register-content d-flex flex-column ga-3"
    >
      <!-- 필수 입력 안내 -->
      <v-alert
        type="info"
        icon="mdi-information-outline"
        variant="tonal"
        density="compact"
        class="app-supporting-alert"
      >
        * 표시는 필수 입력 항목입니다.
      </v-alert>

      <!-- 입력 검증 공통 알림 -->
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
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="employee-register-section-heading">
          <div class="app-form-section-title">
            기본 정보
          </div>

          <div class="app-supporting-text text-medium-emphasis">
            직원의 기본 정보와 입사일을 입력합니다.
          </div>
        </div>

        <div class="app-form-stack">
          <!-- 이름 -->
          <div
            ref="nameRef"
            class="field-anchor"
          >
            <v-text-field
              :model-value="form.name"
              label="이름"
              placeholder="직원 이름"
              variant="outlined"
              density="compact"
              class="app-input"
              autocomplete="off"
              maxlength="50"
              counter="50"
              hide-details="auto"
              :error-messages="fieldError('name')"
              @update:model-value="updateField('name', $event)"
            />
          </div>

          <!-- 휴대폰 번호 -->
          <div
            ref="phoneRef"
            class="field-anchor"
          >
            <v-text-field
              :model-value="form.phone"
              label="휴대폰 번호"
              placeholder="예: 하이픈(-) 없이 숫자만 입력해주세요."
              variant="outlined"
              density="compact"
              class="app-input"
              autocomplete="off"
              inputmode="numeric"
              maxlength="11"
              counter="11"
              persistent-hint
              hide-details="auto"
              :error-messages="fieldError('phone')"
              @update:model-value="
                updateNumericField('phone', $event, 11)
              "
            />
          </div>

          <!-- 생년월일 -->
          <div
            ref="birthDateRef"
            class="field-anchor"
          >
            <v-text-field
              :model-value="form.birth_date"
              label="생년월일"
              type="date"
              variant="outlined"
              density="compact"
              class="app-input"
              hide-details="auto"
              :max="today"
              :error-messages="fieldError('birth_date')"
              @update:model-value="
                updateField('birth_date', $event)
              "
            />
          </div>

          <!-- 입사일 -->
          <div
            ref="hiredAtRef"
            class="field-anchor"
          >
            <v-text-field
              :model-value="form.hired_at"
              label="입사일"
              type="date"
              variant="outlined"
              density="compact"
              class="app-input"
              hide-details="auto"
              :error-messages="fieldError('hired_at')"
              @update:model-value="
                updateField('hired_at', $event)
              "
            />
          </div>
        </div>
      </v-card>

      <!-- 계정 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="employee-register-section-heading">
          <div class="app-form-section-title">
            계정 정보
          </div>

          <div class="app-supporting-text text-medium-emphasis">
            직원이 로그인할 사원번호와 초기 비밀번호를 설정합니다.
          </div>
        </div>

        <div class="app-form-stack">
          <!-- 사원번호 -->
          <div
            ref="employeeCodeRef"
            class="field-anchor"
          >
            <v-text-field
              :model-value="form.employee_code"
              label="사원번호"
              placeholder="숫자만 입력"
              variant="outlined"
              density="compact"
              class="app-input"
              autocomplete="off"
              inputmode="numeric"
              maxlength="20"
              counter="20"
              hide-details="auto"
              :error-messages="fieldError('employee_code')"
              @update:model-value="
                updateNumericField('employee_code', $event, 20)
              "
            />
          </div>

          <!-- 초기 비밀번호 -->
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
              density="compact"
              class="app-input"
              autocomplete="new-password"
              maxlength="72"
              counter="72"
              hint="8자 이상 72자 이하로 입력해주세요."
              persistent-hint
              hide-details="auto"
              :append-inner-icon="
                showPassword
                  ? 'mdi-eye-off-outline'
                  : 'mdi-eye-outline'
              "
              :error-messages="fieldError('password')"
              @click:append-inner="showPassword = !showPassword"
              @update:model-value="
                updateField('password', $event)
              "
            />
          </div>
        </div>
      </v-card>

      <!-- 소속 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="employee-register-section-heading">
          <div class="app-form-section-title">
            소속 정보
          </div>

          <div class="app-supporting-text text-medium-emphasis">
            직원의 근무 부서와 점포, 직급, 권한 역할을 설정합니다.
          </div>
        </div>

        <div class="employee-register-grid">
          <!-- 부서 -->
          <div
            ref="departmentRef"
            class="field-anchor"
          >
            <v-select
              :model-value="form.department"
              :items="departments"
              label="부서"
              variant="outlined"
              density="compact"
              class="app-select"
              hide-details="auto"
              :error-messages="fieldError('department')"
              @update:model-value="updateDepartment"
            />
          </div>

          <!-- 본사 이외의 직원은 점포 선택 -->
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
              density="compact"
              class="app-select"
              hide-details="auto"
              :error-messages="fieldError('store_id')"
              @update:model-value="
                updateField('store_id', $event)
              "
            />
          </div>

          <!-- 본사 소속 안내 -->
          <div
            v-else
            class="app-form-notice"
          >
            <v-icon
              icon="mdi-office-building-outline"
              size="18"
              class="flex-shrink-0"
            />

            <div class="min-width-0">
              <div class="app-form-notice-title">
                본사 소속
              </div>

              <div class="app-supporting-text text-medium-emphasis">
                본사 직원은 특정 점포에 소속되지 않습니다.
              </div>
            </div>
          </div>

          <!-- 직급 -->
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
              density="compact"
              class="app-select"
              hide-details="auto"
              :error-messages="fieldError('position_id')"
              @update:model-value="
                updateField('position_id', $event)
              "
            />
          </div>

          <!-- 권한 역할 -->
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
              density="compact"
              class="app-select"
              hide-details="auto"
              :disabled="!form.department"
              :error-messages="fieldError('role_id')"
              @update:model-value="
                updateField('role_id', $event)
              "
            />
          </div>
        </div>

        <!-- 공통 안내 디자인 -->
        <div class="app-form-notice">
          <v-icon
            icon="mdi-information-outline"
            size="18"
            class="flex-shrink-0"
          />

          <span>
            권한 역할에 따라 사용할 수 있는 메뉴와 기능이 달라집니다.
          </span>
        </div>
      </v-card>
    </div>

    <!-- 공통 푸터 -->
    <template #footer>
      <AppDialogFooter
        :button-count="4"
        cancel-text="취소"
        confirm-text="등록"
        confirm-action="save"
        :disabled="isProcessing"
        @cancel="close"
        @confirm="validateAndSubmit"
      >
        <!-- 전체 삭제 -->
        <template #secondary>
          <v-btn
            block
            variant="tonal"
            color="error"
            min-height="44"
            :loading="loadingAction === 'delete'"
            :disabled="isProcessing || !hasAnyInput"
            @click="deleteDraft"
          >
            전체 삭제
          </v-btn>
        </template>

        <!-- 임시저장 -->
        <template #actions>
          <v-btn
            block
            variant="tonal"
            color="primary"
            min-height="44"
            :loading="loadingAction === 'draft'"
            :disabled="isProcessing || !hasAnyInput"
            @click="saveDraft"
          >
            임시저장
          </v-btn>
        </template>

        <!-- 직원 등록 -->
        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="success"
            min-height="44"
            :loading="loadingAction === 'submit'"
            :disabled="isProcessing || !isFormComplete"
            @click="validateAndSubmit"
          >
            등록
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>
</template>

<script setup>
import {
  computed,
  nextTick,
  ref,
  watch,
} from 'vue';

import AppDialogLayout from '../common/AppDialogLayout.vue';
import AppDialogFooter from '../common/AppDialogFooter.vue';
import AppAlert from '../common/AppAlert.vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  form: {
    type: Object,
    required: true,
  },

  stores: {
    type: Array,
    default: () => [],
  },

  positions: {
    type: Array,
    default: () => [],
  },

  roles: {
    type: Array,
    default: () => [],
  },

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

/*
 * 현재 직원 등록 관련 작업이 실행 중인지 확인합니다.
 */
const isProcessing = computed(
  () => props.loadingAction !== null,
);

/*
 * 비밀번호 표시와 입력 오류 상태입니다.
 */
const showPassword = ref(false);
const invalidField = ref('');
const validationMessage = ref('');

/*
 * 기존 입력 오류 자동 이동에 사용하는 참조입니다.
 */
const scrollAreaRef = ref(null);

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

/*
 * 직원 소속 부서 목록입니다.
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

/*
 * 부서별로 선택 가능한 권한 역할 코드입니다.
 * 최종 권한 검증은 Laravel 서버에서 수행합니다.
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

/*
 * 현재 부서에서 선택할 수 있는 역할만 표시합니다.
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

/*
 * 날짜 입력에서 미래 생년월일을 제한합니다.
 */
const today = new Date().toLocaleDateString('sv-SE');

/*
 * 하나라도 입력한 항목이 있는지 판단합니다.
 *
 * 전체 삭제와 임시저장 버튼의 활성화 조건입니다.
 * 부서, 점포, 직급, 권한 역할 선택도 입력으로 인정합니다.
 *
 * 객체 안의 다른 시스템 필드는 판단에 포함하지 않습니다.
 */
const hasAnyInput = computed(() => {
  const fields = [
    'name',
    'phone',
    'birth_date',
    'hired_at',
    'employee_code',
    'password',
    'department',
    'store_id',
    'position_id',
    'role_id',
  ];

  return fields.some(
    (field) => !isEmpty(props.form[field]),
  );
});

/*
 * 등록에 필요한 입력 항목이 모두 채워졌는지 판단합니다.
 *
 * 본사 직원은 점포 선택이 필수가 아닙니다.
 * 실제 값의 형식 검증은 validateAndSubmit()이 담당합니다.
 */
const isFormComplete = computed(() => {
  const requiredFields = [
    'name',
    'phone',
    'birth_date',
    'hired_at',
    'employee_code',
    'password',
    'department',
    'position_id',
    'role_id',
  ];

  const allRequiredFilled = requiredFields.every(
    (field) => !isEmpty(props.form[field]),
  );

  if (!allRequiredFilled) {
    return false;
  }

  if (props.form.department !== 'head_office') {
    return !isEmpty(props.form.store_id);
  }

  return true;
});

/*
 * 다이얼로그를 닫으면 비밀번호 표시 및 검증 상태를 초기화합니다.
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

/*
 * 해당 입력 필드에 속한 오류만 표시합니다.
 */
function fieldError(field) {
  if (invalidField.value !== field) {
    return [];
  }

  return validationMessage.value
    ? [validationMessage.value]
    : [];
}

/*
 * 빈 문자열, null, undefined만 빈 값으로 판정합니다.
 */
function isEmpty(value) {
  return (
    value === null
    || value === undefined
    || String(value).trim() === ''
  );
}

/*
 * 입력 오류를 초기화합니다.
 */
function clearValidation() {
  invalidField.value = '';
  validationMessage.value = '';
}

/*
 * 오류가 발생한 필드를 수정하면 해당 오류를 제거합니다.
 */
function clearFieldValidation(field) {
  if (invalidField.value === field) {
    clearValidation();
  }
}

/*
 * 부모 컴포넌트의 form을 직접 변경하지 않습니다.
 */
function updateField(field, value) {
  clearFieldValidation(field);

  emit('update:form', {
    ...props.form,
    [field]: value,
  });
}

/*
 * 휴대폰 번호 및 사원번호를 숫자 문자열로 관리합니다.
 */
function updateNumericField(field, value, maxLength) {
  const numericValue = String(value ?? '')
    .replace(/\D/g, '')
    .slice(0, maxLength);

  updateField(field, numericValue);
}

/*
 * 부서를 변경할 때 소속 점포 및 권한 역할을 조정합니다.
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

/*
 * 오류 필드의 DOM 참조를 반환합니다.
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

/*
 * 첫 번째 오류 필드로 스크롤하고 포커스를 이동합니다.
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

/*
 * 입력 검증 실패 메시지를 표시합니다.
 */
function showValidationError(field, message) {
  invalidField.value = field;
  validationMessage.value = message;

  focusInvalidField(field);
}

/*
 * 기존 직원 등록 검증 순서와 조건을 유지합니다.
 */
function validateAndSubmit() {
  if (isProcessing.value || !isFormComplete.value) {
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

  /*
   * 1. 이름
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

  /*
   * 2. 휴대폰 번호
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

  /*
   * 3. 생년월일
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

  /*
   * 4. 입사일
   */
  if (isEmpty(hiredAt)) {
    showValidationError(
      'hired_at',
      '입사일을 입력해주세요.',
    );
    return;
  }

  /*
   * 5. 사원번호
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

  /*
   * 6. 초기 비밀번호
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

  /*
   * 7. 소속 부서
   */
  if (isEmpty(department)) {
    showValidationError(
      'department',
      '부서를 선택해주세요.',
    );
    return;
  }

  if (![
    'kitchen',
    'hall',
    'head_office',
  ].includes(department)) {
    showValidationError(
      'department',
      '올바른 부서를 선택해주세요.',
    );
    return;
  }

  /*
   * 8. 소속 점포
   * 본사 직원은 점포를 선택하지 않습니다.
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

  /*
   * 9. 직급
   */
  if (isEmpty(positionId)) {
    showValidationError(
      'position_id',
      '직급을 선택해주세요.',
    );
    return;
  }

  /*
   * 10. 권한 역할
   */
  if (isEmpty(roleId)) {
    showValidationError(
      'role_id',
      '권한 역할을 선택해주세요.',
    );
    return;
  }

  const selectedRole = props.roles.find(
    (role) => String(role.id) === String(roleId),
  );

  const allowedRoleCodes = departmentRoleCodes[department];

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

  /*
   * 검증 통과 후 기존 부모 확인창으로 전달합니다.
   */
  emit('submit');
}

/*
 * 입력값이 있는 경우에만 임시저장을 요청합니다.
 * 기존처럼 필수 입력 검증은 수행하지 않습니다.
 */
function saveDraft() {
  if (isProcessing.value || !hasAnyInput.value) {
    return;
  }

  clearValidation();
  emit('draft');
}

/*
 * 입력값이 있는 경우에만 전체 삭제를 요청합니다.
 * 실제 삭제 확인 및 Session 처리는 기존 부모가 담당합니다.
 */
function deleteDraft() {
  if (isProcessing.value || !hasAnyInput.value) {
    return;
  }

  clearValidation();
  emit('delete');
}

/*
 * 기존 부모 화면의 닫기 승인 흐름을 유지합니다.
 */
function handleDialogChange(value) {
  if (value || isProcessing.value) {
    return;
  }

  emit('close');
}

function close() {
  if (isProcessing.value) {
    return;
  }

  emit('close');
}
</script>

<style scoped>
/*
 * 직원 등록 내용 전체입니다.
 * 본문 스크롤은 AppDialogLayout에서 담당합니다.
 */
.employee-register-content {
  min-width: 0;
  width: 100%;
}

/*
 * 공통 카드 디자인과 섹션 제목은
 * app-form-section, app-form-section-title을 사용합니다.
 */
.employee-register-section-heading {
  min-width: 0;
}

/*
 * 기존 검증 오류의 입력 위치 이동을 위한 영역입니다.
 */
.field-anchor {
  min-width: 0;
  width: 100%;
}

/*
 * 소속 정보는 PC에서 2열,
 * 모바일에서 1열로 표시합니다.
 */
.employee-register-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 12px;
  min-width: 0;
}

/*
 * 검증 알림은 기존 공통 AppAlert를 사용합니다.
 */
.validation-alert-wrap {
  min-width: 0;
}

.validation-alert-wrap :deep(.v-alert) {
  margin-bottom: 0 !important;
}

/*
 * 모바일 소속 정보 레이아웃 및
 * iOS 입력 자동 확대 방지입니다.
 */
@media (max-width: 600px) {
  .employee-register-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .employee-register-content {
    gap: 8px !important;
  }

  .field-anchor :deep(input) {
    font-size: 16px;
  }
}
</style>