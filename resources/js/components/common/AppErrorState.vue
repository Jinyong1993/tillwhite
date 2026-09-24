<template>
  <!--
    공통 데이터 조회 실패 상태

    서버 오류, 네트워크 문제 등으로
    페이지에 필요한 데이터를 불러오지 못했을 때 표시한다.

    특정 업무 데이터에 종속되지 않으므로
    직원, 제품, 생산·폐기, 매출, 근무 등의
    여러 조회 화면에서 공통으로 사용할 수 있다.
  -->
  <div class="app-error-state">
    <!-- 오류 상태 아이콘 -->
    <v-icon
      :icon="icon"
      size="48"
      class="mb-3"
    />

    <!-- 오류 제목 -->
    <div class="text-h6 font-weight-bold mb-2">
      {{ title }}
    </div>

    <!-- 오류 상세 안내 -->
    <div class="text-body-2 text-medium-emphasis mb-4">
      {{ message }}
    </div>

    <!--
      다시 시도 버튼

      retryable이 true인 경우에만 표시한다.

      재조회 중에는 loading 상태를 표시하고
      버튼을 비활성화하여 중복 요청을 방지한다.

      실제 데이터 조회는 이 컴포넌트에서 처리하지 않고,
      retry 이벤트를 부모 페이지로 전달하여
      부모가 해당 데이터를 다시 조회하도록 한다.
    -->
    <v-btn
      v-if="retryable"
      :loading="loading"
      :disabled="loading"
      variant="outlined"
      prepend-icon="mdi-refresh"
      @click="retry"
    >
      다시 시도
    </v-btn>
  </div>
</template>

<script setup>
/**
 * 공통 조회 실패 상태 컴포넌트 속성
 *
 * title:
 * - 조회 실패 상태의 제목
 * - 별도로 지정하지 않으면 공통 오류 제목을 사용한다.
 *
 * message:
 * - 조회 실패에 대한 상세 안내 메시지
 * - 별도로 지정하지 않으면 공통 안내 문구를 사용한다.
 *
 * icon:
 * - 조회 실패 상태에 표시할 Vuetify 아이콘
 *
 * retryable:
 * - 다시 시도 버튼을 표시할지 결정한다.
 *
 * loading:
 * - 데이터 재조회가 진행 중인지 나타낸다.
 * - true이면 다시 시도 버튼에 로딩 상태를 표시하고 비활성화한다.
 */
defineProps({
  title: {
    type: String,
    default: '데이터를 불러오지 못했습니다.',
  },

  message: {
    type: String,
    default: '일시적인 오류가 발생했습니다. 잠시 후 다시 시도해주세요.',
  },

  icon: {
    type: String,
    default: 'mdi-alert-circle-outline',
  },

  retryable: {
    type: Boolean,
    default: true,
  },

  loading: {
    type: Boolean,
    default: false,
  },
});

/**
 * 부모 컴포넌트로 전달할 이벤트
 *
 * retry:
 * - 사용자가 다시 시도 버튼을 눌렀음을 부모에 전달한다.
 * - 실제 데이터 재조회는 부모 페이지에서 처리한다.
 */
const emit = defineEmits([
  'retry',
]);

/**
 * 데이터 재조회 요청
 *
 * 다시 시도 버튼을 누르면
 * 부모 컴포넌트에 retry 이벤트를 전달한다.
 */
function retry() {
  emit('retry');
}
</script>

<style scoped>
/**
 * 공통 조회 실패 상태 영역
 *
 * 오류 내용을 중앙에 배치하여
 * 현재 상태와 다시 시도 기능을 쉽게 확인할 수 있도록 한다.
 */
.app-error-state {
  width: 100%;
  padding: 32px 16px;
  text-align: center;
}
</style>