<template>
  <!--
    Till White 애플리케이션 공통 확인창

    데이터 저장, 수정, 삭제, 작성 취소 등
    사용자의 최종 확인이 필요한 작업에서 공통으로 사용합니다.

    특정 업무 화면에 종속되지 않으며
    애플리케이션 전체에서 재사용할 수 있습니다.

    처리 중(loading)에는:
    - 확인 버튼만 로딩 표시
    - 취소 버튼 비활성화
    - 우측 상단 닫기 버튼 비활성화
    - ESC로 닫기 차단
    - 바깥 영역 클릭으로 닫기 차단

    이를 통해 처리 중인 요청이 중복 실행되거나
    확인창이 의도치 않게 닫히는 것을 방지합니다.
  -->
  <v-dialog
    :model-value="modelValue"
    :persistent="loading"
    max-width="420"
    @update:model-value="handleModelValue"
  >
    <v-card rounded="lg">
      <!-- 확인창 제목 -->
      <v-card-title
        class="d-flex align-center justify-space-between"
      >
        <span>{{ title }}</span>

        <!--
          닫기 버튼

          일반 상태에서는 취소와 동일하게 동작합니다.
          처리 중에는 닫을 수 없습니다.
        -->
        <v-btn
          icon="mdi-close"
          size="small"
          variant="text"
          :disabled="loading"
          @click="cancel"
        />
      </v-card-title>

      <!-- 확인 메시지 -->
      <v-card-text class="text-body-1">
        {{ message }}
      </v-card-text>

      <!-- 확인창 버튼 -->
      <v-card-actions class="px-4 pb-4">
        <v-spacer />

        <!--
          취소 버튼

          처리 중에는 비활성화하지만
          로딩 아이콘은 표시하지 않습니다.
        -->
        <v-btn
          variant="text"
          :disabled="loading"
          @click="cancel"
        >
          {{ cancelText }}
        </v-btn>

        <!--
          확인 버튼

          실제 작업을 실행하는 버튼이므로
          처리 중에는 이 버튼에만 로딩을 표시합니다.
        -->
        <v-btn
          variant="flat"
          :loading="loading"
          :disabled="loading"
          @click="confirm"
        >
          {{ confirmText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
/**
 * 공통 확인창 속성
 *
 * modelValue:
 * - 확인창의 열림/닫힘 상태
 *
 * title:
 * - 확인창 상단 제목
 *
 * message:
 * - 사용자에게 확인할 내용
 *
 * confirmText:
 * - 확인 버튼 문구
 *
 * cancelText:
 * - 취소 버튼 문구
 *
 * loading:
 * - 확인 후 실제 작업이 진행 중인지 여부
 * - true인 동안 확인 버튼만 로딩 표시
 * - 나머지 닫기 동작은 차단
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
    default: '확인',
  },

  cancelText: {
    type: String,
    default: '취소',
  },

  loading: {
    type: Boolean,
    default: false,
  },
});

/**
 * 부모 컴포넌트에 전달할 이벤트
 *
 * update:modelValue:
 * - 확인창의 열림/닫힘 상태 변경
 *
 * confirm:
 * - 사용자가 확인 버튼을 눌렀을 때 전달
 *
 * cancel:
 * - 사용자가 취소하거나 확인창을 닫았을 때 전달
 */
const emit = defineEmits([
  'update:modelValue',
  'confirm',
  'cancel',
]);

/**
 * 확인창을 닫습니다.
 *
 * 처리 중(loading)에는 닫지 않습니다.
 */
function close() {
  if (props.loading) {
    return;
  }

  emit('update:modelValue', false);
}

/**
 * 사용자가 취소하거나
 * 닫기 버튼을 눌렀을 때 실행합니다.
 *
 * 부모가 필요한 추가 처리를 할 수 있도록
 * cancel 이벤트도 함께 전달합니다.
 */
function cancel() {
  if (props.loading) {
    return;
  }

  close();
  emit('cancel');
}

/**
 * 사용자가 확인 버튼을 눌렀을 때 실행합니다.
 *
 * 실제 API 요청이나 데이터 변경은
 * 공통 확인창에서 직접 처리하지 않습니다.
 *
 * 부모 화면이 confirm 이벤트를 받아
 * 필요한 업무 작업을 실행합니다.
 */
function confirm() {
  if (props.loading) {
    return;
  }

  emit('confirm');
}

/**
 * Vuetify Dialog에서 발생하는
 * modelValue 변경 요청을 처리합니다.
 *
 * ESC 또는 바깥 영역 클릭 등으로
 * 확인창을 닫으려는 경우에도
 * 처리 중이라면 닫기를 허용하지 않습니다.
 *
 * 일반 상태에서 닫기 요청이 발생하면
 * 취소와 동일하게 처리합니다.
 */
function handleModelValue(value) {
  if (value) {
    emit('update:modelValue', true);
    return;
  }

  cancel();
}
</script>