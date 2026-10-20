
<template>
  <!-- 직원 정보 수정 -->
  <AppDialogLayout
    :model-value="modelValue"
    title="직원 정보 수정"
    :subtitle="
      `${employee?.name ?? '-'} · ${employee?.employee_code ?? '-'}`
    "
    :max-width="620"
    persistent
    :busy="loading"
    @update:model-value="handleDialogChange"
  >
    <div class="d-flex flex-column ga-3 w-100">
      <!-- 기본 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="app-form-section-title">
          기본 정보
        </div>

        <div class="d-flex flex-column ga-3">
          <v-text-field
            v-model="form.name"
            label="이름"
            placeholder="직원 이름"
            variant="outlined"
            density="compact"
            class="app-input employee-edit-date"
            hide-details="auto"
            maxlength="50"
            :disabled="loading"
          />

          <v-text-field
            v-model="form.phone"
            label="휴대폰 번호"
            placeholder="하이픈(-) 없이 숫자만 입력"
            variant="outlined"
            density="compact"
            class="app-input employee-edit-date"
            hide-details="auto"
            inputmode="numeric"
            maxlength="11"
            :disabled="loading"
          />

          <v-text-field
            v-model="form.birth_date"
            label="생년월일"
            type="date"
            variant="outlined"
            density="compact"
            class="app-input"
            hide-details="auto"
            :max="today"
            :disabled="loading"
          />

          <v-text-field
            v-model="form.hired_at"
            label="입사일"
            type="date"
            variant="outlined"
            density="compact"
            class="app-input"
            hide-details="auto"
            :disabled="loading"
          />
        </div>
      </v-card>

      <!-- 계정 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="app-form-section-title">
          계정 정보
        </div>

        <v-text-field
          v-model="form.employee_code"
          label="사원번호"
          placeholder="숫자만 입력"
          variant="outlined"
          density="compact"
          class="app-input"
          hide-details="auto"
          inputmode="numeric"
          maxlength="20"
          :disabled="loading"
        />
      </v-card>

      <!-- 소속 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="app-form-section-title">
          소속 정보
        </div>

        <div class="d-flex flex-column ga-3">
          <v-select
            v-model="form.department"
            :items="departments"
            label="부서"
            variant="outlined"
            density="compact"
            class="app-select"
            hide-details="auto"
            :disabled="loading"
          />

          <v-select
            v-if="form.department !== 'head_office'"
            v-model="form.store_id"
            :items="stores"
            item-title="name"
            item-value="id"
            label="점포"
            variant="outlined"
            density="compact"
            class="app-select"
            hide-details="auto"
            :disabled="loading"
          />

          <div
            v-else
            class="app-form-notice d-flex align-center ga-3"
          >
            <v-icon
              icon="mdi-office-building-marker-outline"
              size="20"
              class="flex-shrink-0"
            />

            <div class="d-flex flex-column ga-1">
              <div class="font-weight-medium">
                본사 소속
              </div>

              <div class="app-supporting-text text-medium-emphasis">
                본사 직원은 특정 점포에 소속되지 않습니다.
              </div>
            </div>
          </div>

          <v-select
            v-model="form.position_id"
            :items="positions"
            item-title="name"
            item-value="id"
            label="직급"
            variant="outlined"
            density="compact"
            class="app-select"
            hide-details="auto"
            :disabled="loading"
          />

          <v-select
            v-model="form.role_id"
            :items="availableRoles"
            item-title="name"
            item-value="id"
            label="권한 역할"
            variant="outlined"
            density="compact"
            class="app-select"
            hide-details="auto"
            :disabled="loading"
          />
        </div>
      </v-card>

      <!-- 재직 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="app-form-section-title">
          재직 정보
        </div>

        <v-select
          v-model="form.employment_status"
          :items="employmentStatuses"
          label="재직 상태"
          variant="outlined"
          density="compact"
          class="app-select"
          hide-details
          :disabled="loading"
        />
      </v-card>
    </div>

    <!-- 실제 AppDialogFooter 기본 슬롯 사용 -->
    <template #footer>
      <AppDialogFooter
        :button-count="2"
        cancel-text="취소"
        confirm-text="저장"
        confirm-action="save"
        :disabled="loading"
        @cancel="requestClose"
        @confirm="submitSave"
      >
        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="success"
            min-height="44"
            :loading="loading"
            :disabled="loading"
            @click="submitSave"
          >
            저장
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>

  <!-- 수정 취소 확인 -->
  <AppDialogLayout
    :model-value="discardDialog"
    title="수정을 취소하시겠습니까?"
    subtitle="수정한 내용이 저장되지 않습니다."
    :max-width="360"
    persistent
    card-class="app-dialog-card--compact"
    @update:model-value="handleDiscardDialogChange"
  >
    <div class="app-form-notice">
      수정한 내용이 저장되지 않습니다.
    </div>

    <template #footer>
      <AppDialogFooter
        cancel-text="아니오"
        confirm-text="예"
        @cancel="closeDiscardDialog"
        @confirm="discardChanges"
      />
    </template>
  </AppDialogLayout>
</template>

<script setup>
import {
  computed,
  reactive,
  ref,
  watch,
} from 'vue';

import AppDialogLayout from '../common/AppDialogLayout.vue';
import AppDialogFooter from '../common/AppDialogFooter.vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  employee: {
    type: Object,
    default: null,
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
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  'update:modelValue',
  'close',
  'save',
]);

const discardDialog = ref(false);
const today = new Date().toISOString().slice(0, 10);

const departments = [
  { title: '주방', value: 'kitchen' },
  { title: '홀', value: 'hall' },
  { title: '본사', value: 'head_office' },
];

const employmentStatuses = [
  { title: '재직', value: 'active' },
  { title: '휴직', value: 'leave' },
  { title: '퇴사', value: 'resigned' },
];

const form = reactive({
  employee_code: '',
  name: '',
  phone: '',
  birth_date: '',
  store_id: null,
  department: 'kitchen',
  position_id: null,
  role_id: null,
  hired_at: '',
  employment_status: 'active',
});

const initialForm = ref('');

/*
 * 기존 부서별 역할 제한
 */
const availableRoles = computed(() => {
  const allowed = {
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

  return props.roles.filter(
    (role) => (
      (allowed[form.department] ?? []).includes(role.code)
    ),
  );
});

/*
 * 기존 변경사항 비교
 */
function snapshot() {
  return JSON.stringify({ ...form });
}

const hasChanges = computed(() => (
  Boolean(initialForm.value)
  && snapshot() !== initialForm.value
));

/*
 * 직원 상세조회 데이터를 수정 폼에 반영
 */
watch(
  () => [props.modelValue, props.employee],
  () => {
    if (!props.modelValue || !props.employee) {
      return;
    }

    Object.assign(form, {
      employee_code: props.employee.employee_code ?? '',
      name: props.employee.name ?? '',
      phone: props.employee.phone ?? '',
      birth_date: String(
        props.employee.birth_date ?? '',
      ).slice(0, 10),
      store_id: props.employee.store?.id ?? null,
      department: props.employee.department ?? 'kitchen',
      position_id: props.employee.position?.id ?? null,
      role_id: props.employee.role?.id ?? null,
      hired_at: String(
        props.employee.hired_at ?? '',
      ).slice(0, 10),
      employment_status:
        props.employee.employment_status ?? 'active',
    });

    initialForm.value = snapshot();
    discardDialog.value = false;
  },
  {
    immediate: true,
  },
);

/*
 * 본사 점포 제거 및 역할 제한 유지
 */
watch(
  () => form.department,
  (value) => {
    if (value === 'head_office') {
      form.store_id = null;
    }

    if (
      !availableRoles.value.some(
        (role) => role.id === form.role_id,
      )
    ) {
      form.role_id = null;
    }
  },
);

/*
 * 저장: 기본 활성, 저장 중 중복 요청 방지
 */
function submitSave() {
  if (props.loading) {
    return;
  }

  emit('save', { ...form });
}

/*
 * 취소: 기존 변경사항 보호
 */
function requestClose() {
  if (props.loading) {
    return;
  }

  if (hasChanges.value) {
    discardDialog.value = true;
    return;
  }

  emit('close');
}

/*
 * 변경사항 폐기
 */
function discardChanges() {
  discardDialog.value = false;
  emit('close');
}

/*
 * 수정창 닫기 요청
 */
function handleDialogChange(value) {
  if (!value) {
    requestClose();
  }
}

/*
 * 취소 확인창 닫기
 */
function closeDiscardDialog() {
  discardDialog.value = false;
}

/*
 * 확인창 닫기 요청
 */
function handleDiscardDialogChange(value) {
  if (!value) {
    closeDiscardDialog();
  }
}
</script>