<template>
  <section class="production-summary d-flex flex-column ga-3">
    <!-- 제목 하나만 표시 -->
    <h3 class="production-summary__title">
      {{ title }}
    </h3>

    <!-- 데이터 조회 중 -->
    <div
      v-if="loading"
      class="d-flex justify-center align-center py-6"
      aria-label="생산 현황 조회 중"
    >
      <v-progress-circular
        indeterminate
        size="32"
      />
    </div>

    <!-- 데이터 조회 실패 -->
    <v-alert
      v-else-if="errorMessage"
      type="error"
      variant="tonal"
      density="compact"
      class="app-supporting-alert"
    >
      {{ errorMessage }}
    </v-alert>

    <!-- 조회 성공 -->
    <div
      v-else
      class="production-summary__metrics d-flex ga-2"
    >
      <!-- 생산 -->
      <div
        class="production-summary__metric app-card d-flex flex-column align-center justify-center ga-1"
      >
        <span class="production-summary__label">
          생산
        </span>

        <strong class="production-summary__value">
          {{ formatQuantity(summary.production_quantity) }}
        </strong>
      </div>

      <!-- 폐기 -->
      <div
        class="production-summary__metric app-card d-flex flex-column align-center justify-center ga-1"
      >
        <span class="production-summary__label">
          폐기
        </span>

        <strong class="production-summary__value">
          {{ formatQuantity(summary.waste_quantity) }}
        </strong>
      </div>

      <!-- 폐기율 -->
      <div
        class="production-summary__metric app-card d-flex flex-column align-center justify-center ga-1"
      >
        <span class="production-summary__label">
          폐기율
        </span>

        <strong class="production-summary__value">
          {{ formatWasteRate(summary.waste_rate) }}
        </strong>
      </div>
    </div>
  </section>
</template>

<script setup>
import {
  onMounted,
  ref,
} from 'vue';

import axios from 'axios';
import { toLocalDateString } from '../../utils/localDate';

/*
 * 제목은 기존 기본값을 유지합니다.
 */
defineProps({
  title: {
    type: String,
    default: '금일 생산 현황',
  },
});

/*
 * 기존 ready 이벤트 유지.
 * 조회 성공과 실패 모두 완료 후 전달합니다.
 */
const emit = defineEmits([
  'ready',
]);

/*
 * API에서 읽는 요약 정보.
 * 수치 미제공과 실제 0을 구분합니다.
 */
const summary = ref({
  production_quantity: null,
  waste_quantity: null,
  waste_rate: null,
});

const loading = ref(true);
const errorMessage = ref('');

/*
 * 생산 수량 표시
 */
function formatQuantity(value) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  const number = Number(value);

  if (!Number.isFinite(number)) {
    return '-';
  }

  return number.toLocaleString('ko-KR');
}

/*
 * 폐기율 표시
 *
 * 서버에서 계산한 폐기율을 사용합니다.
 * 서버 값이 없으면 임의 계산하지 않습니다.
 */
function formatWasteRate(value) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  const number = Number(value);

  if (!Number.isFinite(number)) {
    return '-';
  }

  return `${Number(number.toFixed(1))}%`;
}

/*
 * 기존 생산 관리 API 유지.
 * 날짜, 점포 및 권한 범위는 서버 기준입니다.
 */
async function loadSummary() {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await axios.get(
      '/tillwhite/api/production-management/daily',
      {
        params: {
          date: toLocalDateString(),
        },
      },
    );

    const totals = response.data?.totals || {};

    summary.value = {
      production_quantity:
        totals.production ?? 0,

      waste_quantity:
        totals.waste ?? 0,

      waste_rate:
        totals.waste_rate ?? null,
    };
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message
      ?? '오늘 현황을 불러오지 못했습니다.';
  } finally {
    loading.value = false;

    // 기존 페이지 로딩 완료 처리 유지
    emit('ready');
  }
}

onMounted(() => {
  loadSummary();
});
</script>

<style scoped>
.production-summary {
  width: 100%;
  min-width: 0;
}

/* 제목 */
.production-summary__title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.45;
}

/* 세 항목을 한 줄에 동일한 너비로 배치 */
.production-summary__metrics {
  width: 100%;
  min-width: 0;
}

/*
 * 기존 v-card outlined 제거.
 * 연한 회색 테두리와 약한 배경색만 사용.
 */
.production-summary__metric {
  flex: 1 1 0;
  min-width: 0;
  min-height: 84px;
  padding: 12px 6px;

  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 10px;

  background: rgba(var(--v-theme-on-surface), 0.025);
  box-shadow: none;

  text-align: center;
}

/* 항목명 */
.production-summary__label {
  font-size: 0.72rem;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), 0.62);
}

/* 수치 */
.production-summary__value {
  max-width: 100%;
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1.4;

  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

/* 320px 모바일 */
@media (max-width: 360px) {
  .production-summary__metric {
    min-height: 76px;
    padding: 10px 4px;
  }

  .production-summary__value {
    font-size: 1rem;
  }

  .production-summary__label {
    font-size: 0.7rem;
  }
}
</style>