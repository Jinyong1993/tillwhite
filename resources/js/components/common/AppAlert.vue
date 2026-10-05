<template>
  <Teleport to="body">
    <!--
      Till White 애플리케이션 공통 알림

      성공, 오류, 경고, 안내 메시지를 하나의 공통 컴포넌트에서 표시합니다.
      메시지가 있을 때만 렌더링하며 성공·안내는 자동 종료되고 오류·경고는 사용자가 직접 확인합니다.
    -->
    <Transition name="app-alert">
      <v-alert
        v-if="modelValue"
        class="app-global-alert"
        :type="type"
        :variant="variant"
        closable
        @click:close="close"
      >
        {{ modelValue }}
      </v-alert>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, watch } from 'vue';

/**
 * 공통 알림의 메시지와 Vuetify 표시 방식을 전달받습니다.
 * 닫기 기능은 모든 화면에서 동일하게 제공하므로 별도 속성으로 노출하지 않습니다.
 */
const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },

  type: {
    type: String,
    default: 'error',
  },

  variant: {
    type: String,
    default: 'flat',
  },
});

/** 부모의 v-model 메시지를 비우기 위한 이벤트입니다. */
const emit = defineEmits([
  'update:modelValue',
]);


let autoCloseTimer = null;

/** 성공·일반 안내는 업무를 가리지 않도록 5초 뒤 자동으로 닫고 오류·경고는 직접 확인하게 둡니다. */
watch(() => props.modelValue, (message) => {
  if (autoCloseTimer) clearTimeout(autoCloseTimer);
  autoCloseTimer = null;

  if (message && ['success', 'info'].includes(props.type)) {
    autoCloseTimer = setTimeout(close, 5000);
  }
}, { immediate: true });

onBeforeUnmount(() => {
  if (autoCloseTimer) clearTimeout(autoCloseTimer);
});

/** 사용자가 닫기 버튼을 누르면 현재 공통 알림을 종료합니다. */
function close() {
  emit('update:modelValue', '');
}
</script>

<style scoped>
/* 모든 Dialog/Overlay보다 위에서 보이는 애플리케이션 공통 알림 레이어입니다. */
.app-global-alert {
  position: fixed;
  z-index: 10050;
  top: calc(env(safe-area-inset-top, 0px) + 12px);
  left: 50%;
  width: min(calc(100vw - 24px), 420px);
  margin: 0;
  transform: translateX(-50%);
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.24);
  overflow-wrap: anywhere;
  word-break: break-word;
}

/* 공통 알림은 짧은 이동과 투명도 변화만 사용해 업무 흐름을 방해하지 않습니다. */
.app-alert-enter-active,
.app-alert-leave-active {
  transition:
    opacity 180ms ease,
    transform 180ms ease;
}

.app-alert-enter-from,
.app-alert-leave-to {
  opacity: 0;
  transform: translate(-50%, -8px);
}

/* 사용자가 모션 감소를 요청한 환경에서는 위치 이동 없이 즉시 읽을 수 있게 합니다. */
@media (prefers-reduced-motion: reduce) {
  .app-alert-enter-active,
  .app-alert-leave-active {
    transition-duration: 1ms;
  }

  .app-alert-enter-from,
  .app-alert-leave-to {
    transform: translateX(-50%);
  }
}
</style>
