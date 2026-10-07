<template>
  <v-dialog
    :model-value="modelValue"
    max-width="460"
    persistent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card
        rounded="lg"
        class="app-dialog-card"
    >
      <v-card-title class="app-dialog-header pa-5 pb-2">
        비밀번호 초기화
      </v-card-title>

      <v-card-text class="app-dialog-body px-5 pb-5">
        <div class="app-supporting-text text-medium-emphasis mb-5">
          {{ employee?.name }}님의 새로운 비밀번호를 입력해주세요.
        </div>

        <v-text-field
          v-model="password"
          label="새 비밀번호"
          placeholder="8자 이상 입력"
          :type="showPassword ? 'text' : 'password'"
          variant="outlined"
          prepend-inner-icon="mdi-lock-outline"
          :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
          autocomplete="new-password"
          maxlength="72"
          hint="8자 이상 72자 이하로 입력해주세요."
          persistent-hint
          @click:append-inner="showPassword = !showPassword"
        />

        <v-text-field
          v-model="confirmation"
          class="mt-2"
          label="비밀번호 확인"
          placeholder="새 비밀번호를 다시 입력"
          :type="showConfirmation ? 'text' : 'password'"
          variant="outlined"
          prepend-inner-icon="mdi-lock-check-outline"
          :append-inner-icon="showConfirmation ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
          autocomplete="new-password"
          maxlength="72"
          :error-messages="confirmation && password !== confirmation ? ['비밀번호가 일치하지 않습니다.'] : []"
          @click:append-inner="showConfirmation = !showConfirmation"
        />
      </v-card-text>

      <v-divider />

      <v-card-actions class="app-dialog-footer pa-4 px-5">
        <v-btn
          variant="text"
          :disabled="loading"
          @click="$emit('close')"
        >
          취소
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          prepend-icon="mdi-lock-reset"
          :loading="loading"
          :disabled="!password || password.length < 8 || password !== confirmation"
          @click="$emit('save', { password, password_confirmation: confirmation })"
        >
          초기화
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import {
  ref,
  watch,
} from 'vue';

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

defineEmits([
  'update:modelValue',
  'close',
  'save',
]);

const password = ref('');
const confirmation = ref('');
const showPassword = ref(false);
const showConfirmation = ref(false);

/**
 * 다이얼로그를 새로 열 때
 * 이전 비밀번호 입력값과 표시 상태를 모두 초기화합니다.
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
</script>
