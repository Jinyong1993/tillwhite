<template>
  <AppShell ref="appShellRef" :title="pageTitle">
    <template #default="{ can, setError }">
      <div class="d-flex flex-wrap align-center ga-2 mb-3">
        <v-select
          v-if="options.stores?.length > 1"
          :model-value="storeId"
          :items="options.stores"
          item-title="name"
          item-value="id"
          label="점포"
          variant="outlined"
          density="compact"
          hide-details
          class="store-select"
          @update:model-value="changeStore"
        />
        <v-spacer />
        <span class="app-supporting-text text-medium-emphasis">{{ currentStoreName }}</span>
      </div>
      <v-progress-linear v-if="loading" indeterminate class="mb-3" />
      <AppErrorState
        v-if="loadError"
        :message="loadError"
        :loading="loading"
        @retry="loadPage"
      />
      <div v-if="ready" v-show="!loading && !loadError">
        <v-tabs v-model="tab" grow density="compact">
          <v-tab value="list">목록</v-tab>
          <v-tab value="calendar">캘린더</v-tab>
          <v-tab value="analysis">분석</v-tab>
          <v-tab value="statistics">통계</v-tab>
        </v-tabs>
        <v-window v-model="tab" class="mt-4">
          <v-window-item value="list">
            <ProductionDailyTab
              :daily="daily"
              :options="options"
              :store-id="storeId"
              :work-date="workDate"
              :can-mutate="can('production.create') || can('production.update')"
              :can-correct="can('production.correct')"
              @update:work-date="changeDate"
              @reload="loadPage"
              @error="setError"
              @success="showSuccess"
            />
          </v-window-item>
          <v-window-item value="calendar">
            <ProductionCalendarTab
              :store-id="storeId"
              :work-date="workDate"
              :products="options.products || []"
              @jump-date="jumpToDate"
              @error="setError"
              @success="showSuccess"
            />
          </v-window-item>
          <v-window-item value="analysis">
            <ProductionAnalysisTab
              :store-id="storeId"
              :work-date="workDate"
              @error="setError"
            />
          </v-window-item>
          <v-window-item value="statistics">
            <ProductionStatisticsTab
              :store-id="storeId"
              :work-date="workDate"
              @error="setError"
            />
          </v-window-item>
        </v-window>
      </div>
      <AppAlert v-model="successMessage" type="success" />
    </template>
  </AppShell>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppAlert from '../../components/common/AppAlert.vue';
import AppErrorState from '../../components/common/AppErrorState.vue';
import AppShell from '../../components/layout/AppShell.vue';
import ProductionDailyTab from '../../components/production/ProductionDailyTab.vue';
import ProductionCalendarTab from '../../components/production/ProductionCalendarTab.vue';
import ProductionAnalysisTab from '../../components/production/ProductionAnalysisTab.vue';
import ProductionStatisticsTab from '../../components/production/ProductionStatisticsTab.vue';
import { useAppLoading } from '../../composables/useAppLoading';
import { useSession } from '../../composables/useSession';
import { toLocalDateString } from '../../utils/localDate';

const router = useRouter();
const { completePageLoading, cancelLoading } = useAppLoading();
const { clear } = useSession();
const appShellRef = ref(null);
const pageTitle = '생산·폐기 관리';
const tab = ref('list');
const workDate = ref(toLocalDateString());
const storeId = ref(null);
const options = ref({ stores: [], products: [], workers: [] });
const daily = ref({ rows: [], totals: {}, complete_count: 0, required_count: 0 });
const successMessage = ref('');
const loadError = ref('');
const loading = ref(false);
const ready = ref(false);
let activeRequest = null;
let disposed = false;

const currentStoreName = computed(() => (
  options.value.stores?.find((store) => store.id === storeId.value)?.name
  || daily.value.store?.name
  || '-'
));

/** 점포·날짜에 맞는 선택 목록과 일일 데이터를 순서대로 불러옵니다. */
async function loadPage() {
  // 날짜를 빠르게 바꾸면 이전 응답이 새 날짜의 화면을 덮어쓰지 않게 합니다.
  activeRequest?.abort();
  const controller = new AbortController();
  activeRequest = controller;
  const date = workDate.value;
  const requestedStoreId = storeId.value;
  loading.value = true;
  loadError.value = '';

  try {
    const { data: nextOptions } = await window.axios.get(
      '/tillwhite/api/production-management/options',
      {
        params: { date, store_id: requestedStoreId || undefined },
        signal: controller.signal,
        timeout: 20000,
      },
    );

    if (controller.signal.aborted) return;

    const selectedStoreId = requestedStoreId
      || nextOptions.stores?.find((store) => store.name === '무역점')?.id
      || nextOptions.stores?.[0]?.id;

    if (!selectedStoreId) {
      throw new Error('조회할 수 있는 점포가 없습니다. 소속 점포를 확인해주세요.');
    }

    const { data: nextDaily } = await window.axios.get(
      '/tillwhite/api/production-management/daily',
      {
        params: { date, store_id: selectedStoreId },
        signal: controller.signal,
        timeout: 20000,
      },
    );

    if (controller.signal.aborted) return;

    // 두 요청이 모두 성공한 데이터만 함께 반영하여 서로 다른 날짜의 혼합을 막습니다.
    options.value = nextOptions;
    storeId.value = selectedStoreId;
    daily.value = nextDaily;
    ready.value = true;
  } catch (error) {
    if (controller.signal.aborted || disposed) return;

    if (error.response?.status === 401) {
      // 인증 실패를 빈 목록으로 숨기지 않고 재로그인하도록 안내합니다.
      clear();
      cancelLoading();
      await router.replace({ name: 'login', query: { sessionExpired: '1' } });
      return;
    }

    loadError.value = error.response?.data?.message
      || (error.code === 'ECONNABORTED'
        ? '응답 시간이 초과되었습니다. 잠시 후 다시 시도해주세요.'
        : error.response || error.request
          ? '생산·폐기 정보를 불러오지 못했습니다. 다시 시도해주세요.'
          : error.message || '생산·폐기 정보를 불러오지 못했습니다.');
    appShellRef.value?.setError(loadError.value);
  } finally {
    if (activeRequest === controller) {
      loading.value = false;
      activeRequest = null;
    }
  }
}

/** 점포 선택 변경은 한 번의 조회 흐름으로 처리하여 중복 요청을 방지합니다. */
async function changeStore(value) {
  if (!value || value === storeId.value) return;

  storeId.value = value;
  await loadPage();
}

/** 날짜를 변경하면 해당 날짜의 근무자와 일일 데이터를 함께 갱신합니다. */
async function changeDate(value) {
  workDate.value = value;
  await loadPage();
}

/** 캘린더에서 선택한 날짜를 유지한 채 목록 탭으로 이동합니다. */
async function jumpToDate(value) {
  tab.value = 'list';
  await changeDate(value);
}

/** 서버 저장 성공 후 전달받은 메시지를 공통 알림으로 표시합니다. */
function showSuccess(message) {
  successMessage.value = message;
}

/** 최초 조회의 성공·실패와 관계없이 메뉴 이동 시 시작한 공통 로딩을 종료합니다. */
async function initializePage() {
  try {
    await loadPage();
  } finally {
    if (!disposed) await completePageLoading();
  }
}

/** 화면을 떠난 뒤에는 진행 중인 응답이 데이터나 다른 화면의 로딩을 변경하지 않게 합니다. */
function disposePage() {
  disposed = true;
  activeRequest?.abort();
}

onMounted(initializePage);
onBeforeUnmount(disposePage);
</script>

<style scoped>
.store-select {
  max-width: 240px;
}
</style>
