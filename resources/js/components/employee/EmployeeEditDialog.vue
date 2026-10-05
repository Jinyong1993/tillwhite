<template>
  <v-dialog
    :model-value="modelValue"
    max-width="620"
    persistent
    @update:model-value="handleDialogChange"
  >
    <v-card rounded="lg" class="employee-edit-dialog">
      <!-- 직원 등록 다이얼로그와 같은 입력형 헤더 계층을 사용합니다. -->
      <div class="edit-header">
        <div class="edit-header-icon">
          <v-icon icon="mdi-account-edit-outline" size="22" />
        </div>

        <div class="min-width-0">
          <div class="text-h6 font-weight-bold">직원 정보 수정</div>
          <div class="app-supporting-text text-medium-emphasis mt-1">
            {{ employee?.name ?? '-' }} · {{ employee?.employee_code ?? '-' }}
          </div>
        </div>
      </div>

      <v-divider class="app-section-divider" />

      <!-- 직원 등록 화면과 같은 정보 구조와 입력 스타일을 사용합니다. -->
      <v-card-text class="pa-5 edit-scroll">
        <v-alert
          class="app-supporting-alert mb-4"
          type="info"
          variant="tonal"
          density="compact"
        >
          * 표시는 필수 입력 항목입니다.
        </v-alert>
        <section class="edit-section">
          <div class="edit-section-title"><v-icon icon="mdi-account-outline" size="18" /> 기본 정보</div>
          <div class="edit-fields">
            <v-text-field
              v-model="form.name"
              label="이름"
              placeholder="직원 이름"
              variant="outlined"
              prepend-inner-icon="mdi-account-outline"
              maxlength="50"
            />
            <v-text-field
              v-model="form.phone"
              label="휴대폰 번호"
              placeholder="하이픈(-) 없이 숫자만 입력"
              variant="outlined"
              prepend-inner-icon="mdi-cellphone"
              inputmode="numeric"
              maxlength="11"
            />
            <v-text-field
              v-model="form.birth_date"
              label="생년월일"
              type="date"
              variant="outlined"
              prepend-inner-icon="mdi-cake-variant-outline"
              :max="today"
            />
            <v-text-field v-model="form.hired_at" label="입사일" type="date" variant="outlined" prepend-inner-icon="mdi-calendar-check-outline" />
          </div>
        </section>

        <v-divider class="app-section-divider my-5" />

        <section class="edit-section">
          <div class="edit-section-title"><v-icon icon="mdi-lock-outline" size="18" /> 계정 정보</div>
          <div class="edit-fields">
            <v-text-field
              v-model="form.employee_code"
              label="사원번호"
              placeholder="숫자만 입력"
              variant="outlined"
              prepend-inner-icon="mdi-card-account-details-outline"
              inputmode="numeric"
              maxlength="20"
            />
          </div>
        </section>

        <v-divider class="app-section-divider my-5" />

        <section class="edit-section">
          <div class="edit-section-title"><v-icon icon="mdi-office-building-outline" size="18" /> 소속 정보</div>
          <div class="edit-grid">
            <v-select
              v-model="form.department"
              :items="departments"
              label="부서"
              variant="outlined"
              prepend-inner-icon="mdi-office-building-outline"
            />

            <!-- 본사 직원은 점포를 직접 비우는 방식이 아니라 부서 규칙으로 자동 처리합니다. -->
            <v-select
              v-if="form.department !== 'head_office'"
              v-model="form.store_id"
              :items="stores"
              item-title="name"
              item-value="id"
              label="점포"
              variant="outlined"
              prepend-inner-icon="mdi-store-outline"
            />
            <div v-else class="head-office-info">
              <v-icon icon="mdi-office-building-marker-outline" size="20" />
              <div>
                <strong>본사 소속</strong>
                <div class="text-caption text-medium-emphasis">
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
              prepend-inner-icon="mdi-badge-account-outline"
            />
            <v-select
              v-model="form.role_id"
              :items="availableRoles"
              item-title="name"
              item-value="id"
              label="권한 역할"
              variant="outlined"
              prepend-inner-icon="mdi-shield-account-outline"
            />
          </div>
        </section>

        <v-divider class="app-section-divider my-5" />

        <!-- 별도 상태 저장 기능을 없애고 일반 직원 수정에 재직 상태를 통합합니다. -->
        <section class="edit-section">
          <div class="edit-section-title"><v-icon icon="mdi-briefcase-outline" size="18" /> 재직 정보</div>
          <v-select
            v-model="form.employment_status"
            :items="employmentStatuses"
            label="재직 상태"
            variant="outlined"
            prepend-inner-icon="mdi-account-check-outline"
            hide-details
          />
        </section>
      </v-card-text>

      <v-divider class="app-section-divider" />
      <v-card-actions class="pa-4 px-5">
        <v-btn variant="text" :disabled="loading" @click="requestClose">취소</v-btn>
        <v-spacer />
        <v-btn
          variant="flat"
          prepend-icon="mdi-content-save-outline"
          :loading="loading"
          :disabled="!hasChanges"
          @click="$emit('save', { ...form })"
        >
          저장
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- 수정한 내용이 남아 있을 때 실수로 닫는 것을 방지합니다. -->
  <v-dialog v-model="discardDialog" max-width="360" persistent>
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">수정을 취소하시겠습니까?</v-card-title>
      <v-card-text class="px-5 pb-5 app-supporting-text">수정한 내용이 저장되지 않습니다.</v-card-text>
      <v-divider class="app-section-divider" />
      <v-card-actions class="pa-4 px-5">
        <v-btn variant="text" @click="discardDialog = false">아니오</v-btn>
        <v-spacer />
        <v-btn variant="flat" @click="discardChanges">예</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  employee: { type: Object, default: null },
  stores: { type: Array, default: () => [] },
  positions: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'close', 'save']);
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

/** 현재 부서에서 실제로 선택 가능한 역할만 표시합니다. */
const availableRoles = computed(() => {
  const allowed = {
    kitchen: ['staff', 'kitchen_head'],
    hall: ['staff', 'hall_manager'],
    head_office: ['head_office_staff', 'head_office_manager'],
  };
  return props.roles.filter((role) => (allowed[form.department] ?? []).includes(role.code));
});

/** 직원 수정 폼의 현재 상태를 비교용 문자열로 만듭니다. */
function snapshot() {
  return JSON.stringify({ ...form });
}
const hasChanges = computed(() => Boolean(initialForm.value) && snapshot() !== initialForm.value);

/** 상세조회에서 받은 최신 직원 값을 수정 양식에 복사합니다. */
watch(() => [props.modelValue, props.employee], () => {
  if (!props.modelValue || !props.employee) {
    return;
  }
  Object.assign(form, {
    employee_code: props.employee.employee_code ?? '',
    name: props.employee.name ?? '',
    phone: props.employee.phone ?? '',
    birth_date: String(props.employee.birth_date ?? '').slice(0, 10),
    store_id: props.employee.store?.id ?? null,
    department: props.employee.department ?? 'kitchen',
    position_id: props.employee.position?.id ?? null,
    role_id: props.employee.role?.id ?? null,
    hired_at: String(props.employee.hired_at ?? '').slice(0, 10),
    employment_status: props.employee.employment_status ?? 'active',
  });
  initialForm.value = snapshot();
  discardDialog.value = false;
}, { immediate: true });

/** 본사로 변경하면 점포를 자동 제거하고, 부서와 맞지 않는 역할도 초기화합니다. */
watch(() => form.department, (value) => {
  if (value === 'head_office') {
    form.store_id = null;
  }

  if (!availableRoles.value.some((role) => role.id === form.role_id)) {
    form.role_id = null;
  }
});

/** 수정 내용이 있으면 확인창을 거쳐 닫도록 처리합니다. */
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
/** 직원 수정 내용을 버리고 원래 상태로 닫습니다. */
function discardChanges() {
  discardDialog.value = false;
  emit('close');
}
/** 다이얼로그 외부 닫기 요청도 작성 내용 확인 절차를 거치도록 전달합니다. */
function handleDialogChange(value) {
  if (!value) {
    requestClose();
  }
}
</script>

<style scoped>
.edit-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 20px;
}

.edit-header-icon {
  display: flex;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
}

.min-width-0 {
  min-width: 0;
}

.edit-scroll {
  max-height: min(70vh, 650px);
  overflow-y: auto;
}

.edit-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  font-weight: 700;
}

.edit-fields,
.edit-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 4px;
}

.head-office-info {
  display: flex;
  align-items: center;
  min-height: 56px;
  padding: 10px 12px;
  gap: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}
</style>
