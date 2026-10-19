
<template>
  <footer
    :class="[
      'app-dialog-footer',
      'd-flex',
      'align-center',
      'ga-2',
      'px-4',
      'py-3',
      `app-dialog-footer--count-${buttonCount}`,
    ]"
    role="group"
    :aria-busy="isBusy ? 'true' : undefined"
  >
    <!--
      취소·닫기·아니오 등 왼쪽 작업 그룹입니다.
      버튼 개수에 따라 남는 공간을 균등하게 사용합니다.
    -->
    <div
      v-if="showCancel || $slots.cancel || $slots.secondary"
      class="app-dialog-footer-left d-flex align-center ga-2 flex-grow-1"
    >
      <div
        v-if="showCancel || $slots.cancel"
        class="app-dialog-footer-item app-dialog-footer-cancel d-flex flex-grow-1"
      >
        <slot name="cancel">
          <v-btn
            block
            variant="tonal"
            class="app-dialog-footer-button"
            min-height="44"
            :disabled="isBusy"
            @click="onCancel"
          >
            {{ cancelText }}
          </v-btn>
        </slot>
      </div>

      <!-- 추가 보조 버튼은 기존 secondary 슬롯을 사용합니다. -->
      <div
        v-if="$slots.secondary"
        class="app-dialog-footer-item app-dialog-footer-secondary d-flex flex-grow-1"
      >
        <slot name="secondary" />
      </div>
    </div>

    <!--
      저장·수정·삭제 등 오른쪽 실행 그룹입니다.
      주요 실행 버튼은 항상 마지막에 배치합니다.
    -->
    <div
      v-if="showConfirm || $slots.confirm || $slots.actions"
      class="app-dialog-footer-right d-flex align-center justify-end ga-2 flex-grow-1"
    >
      <div
        v-if="$slots.actions"
        class="app-dialog-footer-item app-dialog-footer-actions d-flex flex-grow-1 ga-2"
      >
        <slot name="actions" />
      </div>

      <div
        v-if="showConfirm || $slots.confirm"
        class="app-dialog-footer-item app-dialog-footer-confirm d-flex flex-grow-1"
      >
        <slot name="confirm">
          <v-btn
            block
            :color="confirmColor"
            :variant="confirmVariant"
            class="app-dialog-footer-button"
            min-height="44"
            :loading="loading"
            :disabled="isBusy"
            @click="onConfirm"
          >
            {{ confirmText }}
          </v-btn>
        </slot>
      </div>
    </div>
  </footer>
</template>

<script setup>
import {
  computed,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue';

const props = defineProps({
  cancelText: {
    type: String,
    default: '취소',
  },

  confirmText: {
    type: String,
    default: '저장',
  },

  /*
   * 버튼 문구가 아닌 실제 업무 역할을 기준으로
   * 실행 버튼의 색상과 디자인을 결정합니다.
   */
  confirmAction: {
    type: String,
    default: 'save',
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

  /*
   * 표시할 실제 버튼 개수를 지정합니다.
   * 각 개수의 모바일 배치는 common-ui.css에서 관리합니다.
   */
  buttonCount: {
    type: Number,
    default: 2,
    validator: (value) => (
      Number.isInteger(value) &&
      value >= 1 &&
      value <= 5
    ),
  },

  showCancel: {
    type: Boolean,
    default: true,
  },

  showConfirm: {
    type: Boolean,
    default: true,
  },

  loading: {
    type: Boolean,
    default: false,
  },

  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  'cancel',
  'confirm',
]);

const confirmQueued = ref(false);
const cancelQueued = ref(false);

let unlockTimer = null;

/*
 * 실행 버튼의 역할별 색상 정책을 한곳에서 관리합니다.
 * 중립 확인 버튼에는 강조 색상을 강제하지 않습니다.
 */
const actionStyles = {
  save: {
    color: 'success',
    variant: 'flat',
  },

  edit: {
    color: 'primary',
    variant: 'flat',
  },

  confirm: {
    color: undefined,
    variant: 'tonal',
  },

  delete: {
    color: 'error',
    variant: 'flat',
  },

  disable: {
    color: 'warning',
    variant: 'flat',
  },

  enable: {
    color: 'success',
    variant: 'flat',
  },

  restore: {
    color: 'primary',
    variant: 'flat',
  },

  close: {
    color: undefined,
    variant: 'tonal',
  },
};

const confirmStyle = computed(() => {
  return actionStyles[props.confirmAction] ?? actionStyles.confirm;
});

const confirmColor = computed(() => {
  return confirmStyle.value.color;
});

const confirmVariant = computed(() => {
  return confirmStyle.value.variant;
});

/*
 * 부모의 loading 상태와 내부 임시 잠금을 함께 확인하여
 * 기본 버튼의 빠른 연속 클릭을 방지합니다.
 */
const isBusy = computed(() => {
  return (
    props.loading ||
    props.disabled ||
    confirmQueued.value ||
    cancelQueued.value
  );
});

function clearUnlockTimer() {
  if (unlockTimer !== null) {
    clearTimeout(unlockTimer);
    unlockTimer = null;
  }
}

function unlock() {
  clearUnlockTimer();

  confirmQueued.value = false;
  cancelQueued.value = false;
}

function scheduleUnlock() {
  clearUnlockTimer();

  /*
   * 부모의 loading 상태가 반영되기 전까지 발생하는
   * 연속 클릭을 방지합니다.
   *
   * loading을 사용하지 않는 일반 확인창은
   * 700ms 이후 임시 잠금을 해제합니다.
   */
  unlockTimer = setTimeout(() => {
    unlockTimer = null;

    if (!props.loading) {
      unlock();
    }
  }, 700);
}

watch(
  () => props.loading,
  (loading, wasLoading) => {
    if (loading) {
      clearUnlockTimer();
      return;
    }

    if (wasLoading && !loading) {
      unlock();
    }
  }
);

onBeforeUnmount(() => {
  clearUnlockTimer();
});

function onCancel() {
  if (isBusy.value || !props.showCancel) {
    return;
  }

  cancelQueued.value = true;

  emit('cancel');

  scheduleUnlock();
}

function onConfirm() {
  if (isBusy.value || !props.showConfirm) {
    return;
  }

  confirmQueued.value = true;

  emit('confirm');

  scheduleUnlock();
}
</script>