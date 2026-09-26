<template>
  <!--
    생산·폐기·로스 현황 요약

    지정된 날짜의 생산, 폐기, 로스 수량을
    한눈에 확인할 수 있도록 표시하는 공통 컴포넌트입니다.

    기본적으로 오늘 날짜의 현황을 조회합니다.

    최초 현황 조회가 성공하거나 실패하여
    화면에 표시할 상태가 결정되면
    부모 화면에 준비 완료(ready) 이벤트를 전달합니다.
  -->
  <div>
    <!-- 현황 제목 -->
    <SectionTitle
      :title="title"
      icon="mdi-chart-box-outline"
    />

    <!-- 현황 조회 중 -->
    <div
      v-if="loading"
      class="d-flex justify-center py-6"
    >
      <v-progress-circular
        indeterminate
        size="32"
      />
    </div>

    <!-- 현황 조회 실패 -->
    <v-alert
      v-else-if="errorMessage"
      type="error"
      variant="tonal"
      density="compact"
    >
      {{ errorMessage }}
    </v-alert>

    <!-- 현황 조회 완료 -->
    <v-row
      v-else
      dense
    >
      <!-- 생산 수량 -->
      <v-col cols="4">
        <v-card
          variant="outlined"
          class="text-center h-100"
        >
          <v-card-text class="px-2 py-3">
            <div class="text-caption text-medium-emphasis mb-1">
              생산
            </div>

            <div class="text-h6 font-weight-black">
              {{ summary.production_quantity }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- 폐기 수량 -->
      <v-col cols="4">
        <v-card
          variant="outlined"
          class="text-center h-100"
        >
          <v-card-text class="px-2 py-3">
            <div class="text-caption text-medium-emphasis mb-1">
              폐기
            </div>

            <div class="text-h6 font-weight-black">
              {{ summary.waste_quantity }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- 로스 수량 -->
      <v-col cols="4">
        <v-card
          variant="outlined"
          class="text-center h-100"
        >
          <v-card-text class="px-2 py-3">
            <div class="text-caption text-medium-emphasis mb-1">
              로스
            </div>

            <div class="text-h6 font-weight-black">
              {{ summary.loss_quantity }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import {
  onMounted,
  ref,
} from 'vue';

import axios from 'axios';

import SectionTitle from '../common/SectionTitle.vue';

/**
 * 생산 현황 요약 컴포넌트 속성
 *
 * title:
 * - 현황 영역에 표시할 제목
 * - 기본값은 "금일 생산 현황"
 */
defineProps({
  title: {
    type: String,
    default: '금일 생산 현황',
  },
});

/**
 * 부모 컴포넌트로 전달하는 이벤트입니다.
 *
 * ready:
 * - 최초 생산 현황 조회가 끝났음을 알립니다.
 * - 조회 성공뿐만 아니라 실패한 경우에도 전달합니다.
 *
 * 실패한 경우에도 오류 메시지를 표시할 준비가 끝난 상태이므로
 * 페이지 입장에서는 화면 준비가 완료된 것으로 처리합니다.
 */
const emit = defineEmits([
  'ready',
]);

/**
 * 생산·폐기·로스 합계
 *
 * 조회 결과가 없더라도
 * 화면에는 0을 표시할 수 있도록 초기화합니다.
 */
const summary = ref({
  production_quantity: 0,
  waste_quantity: 0,
  loss_quantity: 0,
});

/**
 * 현황 조회 상태
 */
const loading = ref(true);
const errorMessage = ref('');

/**
 * 생산·폐기·로스 현황 조회
 *
 * 서버에서 현재 사용자의 조회 범위에 맞는
 * 오늘 생산·폐기·로스 합계를 가져옵니다.
 *
 * 점포 및 권한에 따른 데이터 범위는
 * Laravel 서버에서 결정합니다.
 *
 * 성공 또는 실패와 관계없이
 * 최초 요청 처리가 끝나면 부모 컴포넌트에
 * 준비 완료(ready) 이벤트를 전달합니다.
 */
async function loadSummary() {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await axios.get(
      '/tillwhite/api/production/summary',
    );

    summary.value = {
      production_quantity:
        response.data.summary?.production_quantity ?? 0,

      waste_quantity:
        response.data.summary?.waste_quantity ?? 0,

      loss_quantity:
        response.data.summary?.loss_quantity ?? 0,
    };
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message
      ?? '오늘 현황을 불러오지 못했습니다.';
  } finally {
    /**
     * 성공/실패 여부와 관계없이
     * 컴포넌트 내부 로딩을 종료합니다.
     */
    loading.value = false;

    /**
     * 화면에 표시할 결과가 결정되었으므로
     * 부모 화면에 준비 완료를 알립니다.
     *
     * 공통 전체 화면 로딩 자체는
     * 이 컴포넌트에서 직접 종료하지 않습니다.
     *
     * 페이지 전체가 준비되었는지는
     * 부모인 MainPage가 판단합니다.
     */
    emit('ready');
  }
}

/**
 * 컴포넌트가 화면에 표시되면
 * 오늘 생산·폐기·로스 현황을 조회합니다.
 */
onMounted(() => {
  loadSummary();
});
</script>