
<template>
  <AppDialogLayout
    :model-value="modelValue"
    title="비밀번호 초기화"
    :subtitle="
      `${employee?.name ?? '-'} · ${employee?.employee_code ?? '-'}`
    "
    :max-width="460"
    persistent
    :busy="loading"
    @update:model-value="handleDialogChange"
  >
    <div class="d-flex flex-column ga-3 w-100">
      <!-- 안내 -->
      <v-alert
        type="info"
        variant="tonal"
        density="compact"
        class="app-supporting-alert"
      >
        새로운 비밀번호를 입력해주세요.
      </v-alert>

      <!-- 비밀번호 설정 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="app-form-section-title">
          비밀번호 설정
        </div>

        <div class="d-flex flex-column ga-3 w-100">
          <!-- 새 비밀번호 -->
          <v-text-field
            v-model="password"
            label="새 비밀번호"
            placeholder="8자 이상 입력"
            :type="showPassword ? 'text' : 'password'"
            variant="outlined"
            density="compact"
            class="app-input"
            autocomplete="new-password"
            maxlength="72"
            hint="8자 이상 72자 이하로 입력해주세요."
            persistent-hint
            :append-inner-icon="
              showPassword
                ? 'mdi-eye-off-outline'
                : 'mdi-eye-outline'
            "
            :disabled="loading"
            @click:append-inner="togglePasswordVisibility"
          />

          <!-- 비밀번호 확인 -->
          <v-text-field
            v-model="confirmation"
            label="비밀번호 확인"
            placeholder="새 비밀번호를 다시 입력"
            :type="
              showConfirmation
                ? 'text'
                : 'password'
            "
            variant="outlined"
            density="compact"
            class="app-input"
            autocomplete="new-password"
            maxlength="72"
            hide-details="auto"
            :append-inner-icon="
              showConfirmation
                ? 'mdi-eye-off-outline'
                : 'mdi-eye-outline'
            "
            :error-messages="
              confirmation && password !== confirmation
                ? ['비밀번호가 일치하지 않습니다.']
                : []
            "
            :disabled="loading"
            @click:append-inner="toggleConfirmationVisibility"
          />
        </div>
      </v-card>
    </div>

    <!-- 공통 푸터 -->
    <template #footer>
      <AppDialogFooter
        :button-count="2"
        cancel-text="취소"
        confirm-text="초기화"
        confirm-action="save"
        :disabled="loading"
        @cancel="requestClose"
        @confirm="submitPasswordReset"
      >
        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="success"
            min-height="44"
            :loading="loading"
            :disabled="loading || !canSubmit"
            @click="submitPasswordReset"
          >
            초기화
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>
</template>

<script setup>
import {
  computed,
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

const password = ref('');
const confirmation = ref('');

const showPassword = ref(false);
const showConfirmation = ref(false);

/*
 * 기존 비밀번호 입력 조건
 */
const canSubmit = computed(() => {
  return (
    Boolean(password.value)
    && password.value.length >= 8
    && password.value === confirmation.value
  );
});

/*
 * 다시 열 때 비밀번호 입력값과 표시 상태 초기화
 */
watch(
  () => props.modelValue,
  (open) => {
    if (!open) {
      return;
    }

    password.value = '';
    confirmation.value = '';
    showPassword.value = false;
    showConfirmation.value = false;
  },
);

/*
 * 새 비밀번호 보기 및 숨기기
 */
function togglePasswordVisibility() {
  showPassword.value = !showPassword.value;
}

/*
 * 비밀번호 확인 보기 및 숨기기
 */
function toggleConfirmationVisibility() {
  showConfirmation.value = !showConfirmation.value;
}

/*
 * 기존 취소 이벤트
 */
function requestClose() {
  if (props.loading) {
    return;
  }

  emit('close');
}

/*
 * 기존 저장 이벤트 및 payload 유지
 */
function submitPasswordReset() {
  if (
    props.loading
    || !canSubmit.value
  ) {
    return;
  }

  emit('save', {
    password: password.value,
    password_confirmation: confirmation.value,
  });
}

/*
 * 공통 다이얼로그 닫기 요청
 */
function handleDialogChange(value) {
  if (!value) {
    requestClose();
  }
}
</script>