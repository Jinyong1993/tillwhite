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
        :class="[
          'app-dialog-body',
          bodyClass,
        ]"
        :aria-busy="loading ? 'true' : undefined"
        tabindex="-1"
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