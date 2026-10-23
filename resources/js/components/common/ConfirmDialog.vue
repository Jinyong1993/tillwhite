<template>
  <!--
    Till White 애플리케이션 공통 확인창

    등록, 수정, 삭제, 작성 취소 등
    사용자의 최종 확인이 필요한 작업에서 사용합니다.

    처리 중에는:
    - 확인 버튼 로딩 표시
    - 취소 버튼 비활성화
    - ESC 및 바깥 영역 클릭으로 닫기 차단
    - 중복 확인 요청 차단
  -->
  <AppDialogLayout
    :model-value="modelValue"
    :title="title"
    :persistent="true"
    :busy="loading"
    :max-width="420"
    card-class="app-dialog-card--compact"
    @update:model-value="handleModelValue"
  >
    <!-- 공통 확인 메시지 -->
    <div
      class="confirm-dialog-content d-flex align-start ga-2"
      role="note"
    >
      <div class="app-supporting-text confirm-message">
        {{ message }}
      </div>
    </div>

    <!-- 공통 확인 버튼 -->
    <template #footer>
      <AppDialogFooter
        :loading="loading"
        :cancel-text="cancelText"
        :confirm-text="confirmText"
        :confirm-action="confirmAction"
        @cancel="cancel"
        @confirm="confirm"
      />
    </template>
  </AppDialogLayout>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

import AppDialogLayout from './AppDialogLayout.vue';
import AppDialogFooter from './AppDialogFooter.vue';

/**
 * 공통 확인창 속성
 *
 * modelValue:
 * - 확인창 열림/닫힘 상태
 *
 * title:
 * - 확인창 제목
 *
 * message:
 * - 사용자에게 확인할 내용
 *
 * confirmText / cancelText:
 * - 기본값은 예 / 아니오
 * - 호출부에서 전달한 문구는 그대로 유지
 *
 * loading:
 * - 실제 작업 진행 여부
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  title: {
    type: String,
    required: true,
  },

  message: {
    type: String,
    required: true,
  },

  confirmText: {
    type: String,
    default: '예',
  },

  confirmAction: {
    type: String,
    default: 'confirm',
    validator: (value) => [
      'save',
      'edit',
      'confirm',
      'delete',
      'disable',
      'enable',
      'restore',
      'close',
    ].includes(value),
  },

  cancelText: {
    type: String,
    default: '아니오',
  },

  loading: {
    type: Boolean,
    default: false,
  },
});

/**
 * 기존 이벤트 인터페이스 유지
 */
const emit = defineEmits([
  'update:modelValue',
  'confirm',
  'cancel',
]);

/**
 * 확인 버튼 연속 클릭 방지
 *
 * 부모 컴포넌트의 loading 반영이 늦어져도
 * 동일한 확인 이벤트가 연속 전송되지 않도록 합니다.
 */
const actionLocked = ref(false);

let unlockTimer = null;

function releaseAction() {
  if (unlockTimer !== null) {
    clearTimeout(unlockTimer);
  }

  unlockTimer = null;
  actionLocked.value = false;
}

function lockAction() {
  actionLocked.value = true;

  if (unlockTimer !== null) {
    clearTimeout(unlockTimer);
  }

  unlockTimer = setTimeout(() => {
    unlockTimer = null;

    if (!props.loading) {
      actionLocked.value = false;
    }
  }, 700);
}

/**
 * 로딩 상태 변경 감시
 */
watch(
  () => props.loading,
  (loading, wasLoading) => {
    if (loading && unlockTimer !== null) {
      clearTimeout(unlockTimer);
      unlockTimer = null;
    }

    if (wasLoading && !loading) {
      releaseAction();
    }
  },
);

/**
 * 확인창이 닫히면 클릭 잠금 해제
 */
watch(
  () => props.modelValue,
  (visible) => {
    if (!visible) {
      releaseAction();
    }
  },
);

/**
 * 컴포넌트 제거 시 타이머 정리
 */
onBeforeUnmount(() => {
  if (unlockTimer !== null) {
    clearTimeout(unlockTimer);
  }
});

/**
 * 확인창 닫기
 *
 * 로딩 중에는 닫기 차단
 */
function close() {
  if (props.loading) {
    return;
  }

  emit('update:modelValue', false);
}

/**
 * 취소 버튼 처리
 *
 * 부모 컴포넌트의 기존 취소 로직을 유지합니다.
 */
function cancel() {
  if (
    props.loading
    || actionLocked.value
  ) {
    return;
  }

  lockAction();

  close();
  emit('cancel');
}

/**
 * 확인 버튼 처리
 *
 * 실제 API 요청이나 데이터 변경은
 * 부모 컴포넌트에서 처리합니다.
 */
function confirm() {
  if (
    props.loading
    || actionLocked.value
  ) {
    return;
  }

  lockAction();

  emit('confirm');
}

/**
 * 다이얼로그 열림/닫힘 요청 처리
 *
 * persistent 설정으로 ESC 및
 * 바깥 영역 클릭에 의한 닫기를 방지합니다.
 */
function handleModelValue(value) {
  if (value) {
    emit('update:modelValue', true);
    return;
  }

  cancel();
}
</script>

<style scoped>
/*
 * 공통 확인 메시지 영역
 */
.confirm-dialog-content {
  min-width: 0;
}

/*
 * 긴 메시지와 줄바꿈 대응
 */
.confirm-message {
  flex: 1 1 auto;
  min-width: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
}
</style>