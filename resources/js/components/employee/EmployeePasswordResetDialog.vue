<template>
  <v-dialog :model-value="modelValue" max-width="460" persistent @update:model-value="$emit('update:modelValue', $event)">
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">비밀번호 초기화</v-card-title>
      <v-card-text class="px-5 pb-5">
        <div class="text-body-2 text-medium-emphasis mb-5">
          {{ employee?.name }} 직원의 새 비밀번호를 설정합니다. 비밀번호 값은 감사로그에 기록하지 않습니다.
        </div>
        <v-text-field v-model="password" label="새 비밀번호" type="password" variant="outlined" autocomplete="new-password" />
        <v-text-field v-model="confirmation" label="새 비밀번호 확인" type="password" variant="outlined" autocomplete="new-password" hide-details />
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4 px-5">
        <v-btn variant="text" :disabled="loading" @click="$emit('close')">취소</v-btn>
        <v-spacer />
        <v-btn variant="flat" prepend-icon="mdi-lock-reset" :loading="loading" :disabled="!password || password.length < 8 || password !== confirmation" @click="$emit('save', { password, password_confirmation: confirmation })">초기화</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
const props = defineProps({ modelValue: { type: Boolean, default: false }, employee: { type: Object, default: null }, loading: { type: Boolean, default: false } });
defineEmits(['update:modelValue', 'close', 'save']);
const password = ref('');
const confirmation = ref('');
/** 다이얼로그를 새로 열 때 이전에 입력했던 비밀번호를 남기지 않습니다. */
watch(() => props.modelValue, (open) => { if (open) { password.value = ''; confirmation.value = ''; } });
</script>
