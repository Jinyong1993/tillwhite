<template>
  <v-menu
    v-model="menuOpen"
    location="bottom center"
    :close-on-content-click="false"
  >
    <template #activator="{ props: menuProps }">
      <button
        v-bind="menuProps"
        type="button"
        class="app-date-picker__trigger"
        aria-label="업무 날짜 선택"
      >
        <span class="app-date-picker__title">
          {{ formatKoreanDate(modelValue) }}

          <v-icon
            icon="mdi-chevron-down"
            size="15"
            class="app-date-picker__chevron"
          />
        </span>

        <small class="app-date-picker__hint">
          날짜 선택
        </small>
      </button>
    </template>

    <v-locale-provider locale="ko">
      <v-date-picker
        :model-value="modelValue"
        @update:model-value="handleDateChange"
        width="280"
        hide-header
        elevation="0"
        rounded="lg"
        weeks-in-month="dynamic"
        class="app-date-picker__calendar"
      />
    </v-locale-provider>
  </v-menu>
</template>

<script setup>
import { ref } from 'vue';
import { formatKoreanDate } from '../../utils/localDate';

defineProps({
  modelValue: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['update:modelValue']);

const menuOpen = ref(false);

function handleDateChange(value) {
  if (!value) return;

  emit('update:modelValue', value);
  menuOpen.value = false;
}
</script>

<style scoped>
/* 날짜 선택 버튼 */
.app-date-picker__trigger {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-width: 0;
  min-height: 48px;
  padding: 4px 2px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  text-align: center;
}

/* 날짜 및 아이콘 */
.app-date-picker__title {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3px;
  max-width: 100%;
  font-size: .82rem;
  font-weight: 650;
  line-height: 1.4;
  letter-spacing: -.03em;
  white-space: nowrap;
}

.app-date-picker__chevron {
  flex-shrink: 0;
  color: rgba(var(--v-theme-on-surface), .55);
}

/* 날짜 선택 안내 */
.app-date-picker__hint {
  margin-top: 3px;
  font-size: .65rem;
  font-weight: 400;
  line-height: 1.3;
  color: rgba(var(--v-theme-on-surface), .52);
}

/* 키보드 접근성 */
.app-date-picker__trigger:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 320px 모바일 소형 달력 */
.app-date-picker__calendar {
  --v-date-picker-controls-height: 40px;
}

/* 연도 및 월 이동 영역 */
.app-date-picker__calendar :deep(.v-date-picker-controls) {
  width: 100%;
  min-width: 0;
  padding-inline: 4px;
  box-sizing: border-box;
}

/* 연도·월 제목이 화살표 공간을 침범하지 않도록 조정 */
.app-date-picker__calendar :deep(.v-date-picker-controls__month) {
  min-width: 0;
}

/* 달력 날짜 영역 */
.app-date-picker__calendar :deep(.v-date-picker-month) {
  padding: 0 4px 6px;
}

/* 날짜 셀 */
.app-date-picker__calendar :deep(.v-date-picker-month__day) {
  width: 34px;
  height: 34px;
}

/* 날짜 버튼 */
.app-date-picker__calendar :deep(.v-date-picker-month__day .v-btn) {
  width: 32px;
  min-width: 32px;
  height: 32px;
  font-size: .75rem;
}
</style>