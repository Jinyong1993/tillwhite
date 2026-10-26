<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidth"
    :persistent="persistent"
    :fullscreen="fullscreen"
    @update:model-value="onDialogUpdate"
  >
    <v-card
      :class="[
        'app-dialog-card',
        'app-dialog-layout',
        'd-flex',
        'flex-column',
        cardClass,
      ]"
      :aria-busy="loading || busy ? 'true' : undefined"
      rounded="lg"
      elevation="4"
    >
      <slot name="header">
        <AppDialogHeader
          :title="title"
          :subtitle="subtitle"
        />
      </slot>

      <main
        ref="dialogBodyRef"
        :class="[
          'app-dialog-body',
          bodyClass,
        ]"
        :aria-busy="loading ? 'true' : undefined"
        tabindex="-1"
        @focusin="handleFocusIn"
        @focusout="handleFocusOut"
      >
        <slot v-if="loading" name="loading">
          <div
            class="d-flex flex-column align-center justify-center ga-3 py-10 px-4"
            role="status"
            aria-live="polite"
          >
            <v-progress-circular
              indeterminate
              color="primary"
              size="32"
            />

            <span class="text-body-2 text-medium-emphasis">
              {{ loadingText }}
            </span>
          </div>
        </slot>

        <slot
          v-else-if="error"
          name="error"
          :error="error"
        >
          <div class="d-flex flex-column ga-3 pa-4">
            <v-card
              variant="flat"
              rounded="lg"
              class="app-dialog-inner-card d-flex flex-column ga-3 pa-3"
            >
              <v-alert
                type="error"
                variant="tonal"
                role="alert"
              >
                {{ errorMessage }}
              </v-alert>

              <v-btn
                v-if="retryable"
                variant="tonal"
                color="primary"
                min-height="44"
                class="align-self-end"
                :disabled="loading || busy || retryRequested"
                :loading="retryRequested && !loading"
                @click="requestRetry"
              >
                {{ retryText }}
              </v-btn>
            </v-card>
          </div>
        </slot>

        <slot v-else-if="empty" name="empty">
          <div class="d-flex flex-column pa-4">
            <v-card
              variant="flat"
              rounded="lg"
              class="app-dialog-inner-card d-flex flex-column align-center justify-center ga-2 pa-6"
            >
              <div
                class="text-body-2 text-medium-emphasis text-center"
                role="status"
                aria-live="polite"
              >
                {{ emptyText }}
              </div>
            </v-card>
          </div>
        </slot>

        <div
          v-else
          class="d-flex flex-column flex-grow-1 pa-3"
          style="min-width: 0; min-height: 0;"
        >
          <v-card
            variant="flat"
            rounded="lg"
            class="app-dialog-inner-card d-flex flex-column flex-grow-1 pa-3 ga-3"
          >
            <slot />
          </v-card>
        </div>
      </main>

      <div
        v-if="$slots.footer"
        class="app-dialog-layout-footer flex-shrink-0"
      >
        <slot name="footer" />
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue';

import AppDialogHeader from './AppDialogHeader.vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  title: {
    type: String,
    default: '',
  },

  subtitle: {
    type: String,
    default: '',
  },

  maxWidth: {
    type: [Number, String],
    default: 620,
  },

  persistent: {
    type: Boolean,
    default: false,
  },

  fullscreen: {
    type: Boolean,
    default: false,
  },

  cardClass: {
    type: String,
    default: '',
  },

  bodyClass: {
    type: String,
    default: '',
  },

  loading: {
    type: Boolean,
    default: false,
  },

  busy: {
    type: Boolean,
    default: false,
  },

  loadingText: {
    type: String,
    default: '불러오는 중입니다.',
  },

  error: {
    type: [String, Object],
    default: '',
  },

  empty: {
    type: Boolean,
    default: false,
  },

  emptyText: {
    type: String,
    default: '표시할 데이터가 없습니다.',
  },

  retryable: {
    type: Boolean,
    default: false,
  },

  retryText: {
    type: String,
    default: '다시 시도',
  },
});

const emit = defineEmits([
  'update:modelValue',
  'close',
  'retry',
]);

const retryRequested = ref(false);

// 모바일 키패드 대응
const dialogBodyRef = ref(null);

let focusedInput = null;
let scrollTimers = [];

function clearScrollTimers() {
  scrollTimers.forEach(clearTimeout);
  scrollTimers = [];
}

function resetKeyboardScroll() {
  clearScrollTimers();
  focusedInput = null;

  if (dialogBodyRef.value) {
    dialogBodyRef.value.style.removeProperty('padding-bottom');
  }
}

function adjustFocusedInput() {
  const body = dialogBodyRef.value;
  const input = focusedInput;

  if (
    !props.modelValue ||
    !body ||
    !input ||
    !body.contains(input) ||
    document.activeElement !== input
  ) {
    return;
  }

  const viewport = window.visualViewport;

  const visibleTop = viewport?.offsetTop ?? 0;
  const visibleBottom = viewport
    ? viewport.offsetTop + viewport.height
    : window.innerHeight;

  const bodyRect = body.getBoundingClientRect();

  // 키패드에 가려진 본문 하단 영역 확보
  const coveredBottom = Math.max(
    0,
    bodyRect.bottom - visibleBottom
  );

  body.style.paddingBottom = coveredBottom > 0
    ? `${Math.ceil(coveredBottom + 24)}px`
    : '';

  const inputRect = input.getBoundingClientRect();

  const safeTop = Math.max(
    bodyRect.top,
    visibleTop
  ) + 16;

  const safeBottom = Math.min(
    bodyRect.bottom,
    visibleBottom
  ) - 16;

  if (safeBottom <= safeTop) {
    return;
  }

  // 입력창이 키패드 아래에 가려진 경우
  if (inputRect.bottom > safeBottom) {
    body.scrollTop += inputRect.bottom - safeBottom;
  }

  // 입력창이 화면 상단에 가려진 경우
  else if (inputRect.top < safeTop) {
    body.scrollTop -= safeTop - inputRect.top;
  }
}

function scheduleInputScroll() {
  clearScrollTimers();

  // 포커스 직후와 키패드 애니메이션 이후 모두 확인
  [0, 150, 350].forEach((delay) => {
    scrollTimers.push(
      setTimeout(adjustFocusedInput, delay)
    );
  });
}

function handleFocusIn(event) {
  const target = event.target;

  if (
    !(target instanceof HTMLElement) ||
    !target.matches(
      'input, textarea, [contenteditable="true"]'
    )
  ) {
    return;
  }

  focusedInput = target;
  scheduleInputScroll();
}

function handleFocusOut() {
  setTimeout(() => {
    const body = dialogBodyRef.value;

    if (
      !body ||
      !body.contains(document.activeElement)
    ) {
      resetKeyboardScroll();
    }
  }, 0);
}

function handleViewportChange() {
  if (!focusedInput) {
    return;
  }

  scheduleInputScroll();
}

onMounted(() => {
  window.visualViewport?.addEventListener(
    'resize',
    handleViewportChange
  );

  window.visualViewport?.addEventListener(
    'scroll',
    handleViewportChange
  );

  window.addEventListener(
    'resize',
    handleViewportChange
  );
});

onBeforeUnmount(() => {
  resetKeyboardScroll();

  window.visualViewport?.removeEventListener(
    'resize',
    handleViewportChange
  );

  window.visualViewport?.removeEventListener(
    'scroll',
    handleViewportChange
  );

  window.removeEventListener(
    'resize',
    handleViewportChange
  );
});

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) {
      resetKeyboardScroll();
      return;
    }

    await nextTick();
  }
);

watch(
  () => [
    props.modelValue,
    props.loading,
    props.error,
  ],
  ([open, loading, error], oldValues) => {
    const wasLoading = oldValues?.[1] === true;
    const errorChanged = error !== oldValues?.[2];

    if (
      !open ||
      loading ||
      (wasLoading && !loading) ||
      errorChanged ||
      !error
    ) {
      retryRequested.value = false;
    }
  }
);

const errorMessage = computed(() => {
  const value = props.error;

  if (typeof value === 'string') {
    return value;
  }

  const data = value?.response?.data;
  const validationErrors = data?.errors;

  const firstValidationMessage =
    validationErrors && typeof validationErrors === 'object'
      ? Object.values(validationErrors)
          .flat()
          .find(
            (message) =>
              typeof message === 'string' && message.trim()
          )
      : null;

  const candidates = [
    firstValidationMessage,
    data?.message,
    value?.message,
  ];

  return (
    candidates.find(
      (message) =>
        typeof message === 'string' && message.trim()
    ) || '데이터를 불러오지 못했습니다.'
  );
});

function requestRetry() {
  if (
    props.loading ||
    props.busy ||
    retryRequested.value ||
    !props.retryable ||
    !props.error
  ) {
    return;
  }

  retryRequested.value = true;
  emit('retry');
}

function onDialogUpdate(value) {
  if (
    !value &&
    (
      props.loading ||
      props.busy ||
      retryRequested.value
    )
  ) {
    return;
  }

  emit('update:modelValue', value);
}
</script>