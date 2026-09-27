<template>
  <v-dialog :model-value="modelValue" max-width="620" persistent @update:model-value="$emit('update:modelValue', $event)">
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">직원 정보 수정</v-card-title>
      <v-card-subtitle class="px-5 pb-4">{{ employee?.name }} · {{ employee?.employee_code }}</v-card-subtitle>
      <v-divider />
      <v-card-text class="pa-5 edit-scroll">
        <!-- 비밀번호와 재직 상태는 별도 기능으로 관리하여 일반 정보 수정과 섞지 않습니다. -->
        <v-row dense>
          <v-col cols="12" sm="6"><v-text-field v-model="form.employee_code" label="사번 / 로그인 ID" variant="outlined" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model="form.name" label="이름" variant="outlined" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model="form.phone" label="연락처" variant="outlined" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model="form.birth_date" label="생년월일" type="date" variant="outlined" /></v-col>
          <v-col cols="12" sm="6"><v-select v-model="form.department" :items="departments" label="부서" variant="outlined" /></v-col>
          <v-col cols="12" sm="6"><v-select v-model="form.store_id" :items="stores" item-title="name" item-value="id" label="소속 점포" variant="outlined" :disabled="form.department === 'head_office'" clearable /></v-col>
          <v-col cols="12" sm="6"><v-select v-model="form.position_id" :items="positions" item-title="name" item-value="id" label="직급" variant="outlined" /></v-col>
          <v-col cols="12" sm="6"><v-select v-model="form.role_id" :items="availableRoles" item-title="name" item-value="id" label="시스템 역할" variant="outlined" /></v-col>
          <v-col cols="12"><v-text-field v-model="form.hired_at" label="입사일" type="date" variant="outlined" hide-details /></v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4 px-5">
        <v-btn variant="text" :disabled="loading" @click="$emit('close')">취소</v-btn>
        <v-spacer />
        <v-btn variant="flat" prepend-icon="mdi-content-save-outline" :loading="loading" @click="$emit('save', { ...form })">수정 저장</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  employee: { type: Object, default: null },
  stores: { type: Array, default: () => [] },
  positions: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'close', 'save']);

const departments = [
  { title: '주방', value: 'kitchen' },
  { title: '홀', value: 'hall' },
  { title: '본사', value: 'head_office' },
];

/** 현재 선택한 부서에서 실제로 사용할 수 있는 역할만 표시합니다. */
const availableRoles = computed(() => {
  const allowed = {
    kitchen: ['staff', 'kitchen_head'],
    hall: ['staff', 'hall_manager'],
    head_office: ['head_office_staff', 'head_office_manager'],
  };

  return props.roles.filter((role) => (allowed[form.department] ?? []).includes(role.code));
});

const form = reactive({ employee_code: '', name: '', phone: '', birth_date: '', store_id: null, department: 'kitchen', position_id: null, role_id: null, hired_at: '' });

/** 상세조회에서 받은 최신 직원 값을 수정 양식에 복사합니다. */
watch(() => [props.modelValue, props.employee], () => {
  if (!props.modelValue || !props.employee) return;
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
  });
}, { immediate: true });

/** 본사로 변경하면 실제 점포 선택값을 즉시 제거합니다. */
watch(() => form.department, (value) => {
  if (value === 'head_office') form.store_id = null;
  if (!availableRoles.value.some((role) => role.id === form.role_id)) form.role_id = null;
});
</script>

<style scoped>
.edit-scroll { max-height: min(70vh, 650px); overflow-y: auto; }
</style>
