<template>
  <AppShell ref="appShellRef" :title="pageTitle">
    <template #default="{ can, setError }">
      <section class="store-context mb-3">
        <div class="store-context-copy">
          <span class="store-context-label">조회 점포</span>
          <strong>{{ currentStoreName }}</strong>
          <span v-if="options.store_read_only" class="store-read-only">다른 점포의 생산·폐기 기록은 조회만 가능합니다.</span>
        </div>
        <v-select
          v-if="options.stores?.length > 1"
          :model-value="storeId"
          :items="options.stores"
          item-title="name"
          item-value="id"
          label="점포 선택"
          variant="outlined"
          density="compact"
          hide-details
          class="store-select"
          @update:model-value="changeStore"
        />
      </section>

      <AppErrorState
        v-if="loadError && !ready"
        :message="loadError"
        :loading="loading"
        @retry="loadPage({ force: true })"
      />

      <div v-if="ready">
        <v-tabs v-model="tab" grow density="compact" class="production-tabs">
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
              :refreshing="loading"
              :can-mutate="!options.store_read_only && (can('production.create') || can('production.update'))"
              :can-correct="can('production.correct')"
              @update:work-date="changeDate"
              @reload="reloadCurrentDate"
              @error="setError"
              @success="showSuccess"
            />
          </v-window-item>

          <v-window-item value="calendar">
            <!-- 캘린더는 실제 탭에 진입할 때만 마운트하여 최초 페이지 로딩을 가볍게 유지합니다. -->
            <ProductionCalendarTab
              :store-id="storeId"
              :work-date="workDate"
              :products="options.products || []"
              :can-mutate="!options.store_read_only && can('production.update')"
              @jump-date="jumpToDate"
              @error="setError"
              @success="showSuccess"
            />
          </v-window-item>

          <v-window-item value="analysis">
            <ProductionAnalysisTab
              v-if="tab === 'analysis'"
              :store-id="storeId"
              :work-date="workDate"
              @error="setError"
            />
          </v-window-item>

          <v-window-item value="statistics">
            <ProductionStatisticsTab
              v-if="tab === 'statistics'"
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
import ProductionAnalysisTab from '../../components/production/ProductionAnalysisTab.vue';
import ProductionCalendarTab from '../../components/production/ProductionCalendarTab.vue';
import ProductionDailyTab from '../../components/production/ProductionDailyTab.vue';
import ProductionStatisticsTab from '../../components/production/ProductionStatisticsTab.vue';
import { useAppLoading } from '../../composables/useAppLoading';
import { useSession } from '../../composables/useSession';
import { addLocalDays, toLocalDateString } from '../../utils/localDate';

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

const dailyCache = new Map();
const optionsCache = new Map();
let activeRequest = null;
let disposed = false;

const currentStoreName = computed(() => (
  options.value.stores?.find((store) => store.id === storeId.value)?.name
  || daily.value.store?.name
  || '-'
));

/** 점포와 날짜 조합을 캐시 키로 변환합니다. */
function cacheKey(date, targetStoreId) {
  return `${targetStoreId || 'auto'}:${date}`;
}

/** API 오류를 사용자가 이해할 수 있는 공통 조회 메시지로 변환합니다. */
function getLoadErrorMessage(error) {
  if (error.code === 'ECONNABORTED') {
    return '응답 시간이 초과되었습니다. 잠시 후 다시 시도해주세요.';
  }

  return error.response?.data?.message
    || (error.response || error.request
      ? '생산·폐기 정보를 불러오지 못했습니다. 다시 시도해주세요.'
      : error.message || '생산·폐기 정보를 불러오지 못했습니다.');
}

/** 선택 날짜의 옵션과 일일 데이터를 조회합니다. 이미 확인한 날짜는 캐시를 우선 사용합니다. */
async function loadPage({ force = false, date = workDate.value } = {}) {
  activeRequest?.abort();

  const controller = new AbortController();
  activeRequest = controller;
  const requestedStoreId = storeId.value;
  const requestedKey = cacheKey(date, requestedStoreId);

  loading.value = true;
  loadError.value = '';

  try {
    let nextOptions = !force ? optionsCache.get(requestedKey) : null;
    let selectedStoreId = requestedStoreId;

    if (!nextOptions) {
      const response = await window.axios.get('/tillwhite/api/production-management/options', {
        params: { date, store_id: requestedStoreId || undefined },
        signal: controller.signal,
        timeout: 20000,
      });
      nextOptions = response.data;
    }

    if (controller.signal.aborted) return false;

    selectedStoreId = selectedStoreId
      || nextOptions.stores?.find((store) => store.name === '무역점')?.id
      || nextOptions.stores?.[0]?.id;

    if (!selectedStoreId) {
      throw new Error('조회할 수 있는 점포가 없습니다. 소속 점포를 확인해주세요.');
    }

    const resolvedKey = cacheKey(date, selectedStoreId);
    let nextDaily = !force ? dailyCache.get(resolvedKey) : null;

    if (!nextDaily) {
      const response = await window.axios.get('/tillwhite/api/production-management/daily', {
        params: { date, store_id: selectedStoreId },
        signal: controller.signal,
        timeout: 20000,
      });
      nextDaily = response.data;
    }

    if (controller.signal.aborted) return false;

    optionsCache.set(resolvedKey, nextOptions);
    dailyCache.set(resolvedKey, nextDaily);
    options.value = nextOptions;
    storeId.value = selectedStoreId;
    daily.value = nextDaily;
    workDate.value = date;
    ready.value = true;

    prefetchAdjacentDates(date, selectedStoreId);
    return true;
  } catch (error) {
    if (controller.signal.aborted || disposed) return false;

    if (error.response?.status === 401) {
      clear();
      cancelLoading();
      await router.replace({ name: 'login', query: { sessionExpired: '1' } });
      return false;
    }

    loadError.value = getLoadErrorMessage(error);
    appShellRef.value?.setError(loadError.value);
    return false;
  } finally {
    if (activeRequest === controller) {
      loading.value = false;
      activeRequest = null;
    }
  }
}

/** 현재 날짜 양옆 데이터를 조용히 미리 받아 날짜 이동 체감 속도를 높입니다. */
function prefetchAdjacentDates(date, targetStoreId) {
  for (const offset of [-1, 1]) {
    const adjacentDate = addLocalDays(date, offset);
    const key = cacheKey(adjacentDate, targetStoreId);

    if (!dailyCache.has(key)) {
      window.axios.get('/tillwhite/api/production-management/daily', {
        params: { date: adjacentDate, store_id: targetStoreId },
        timeout: 20000,
      }).then(({ data }) => {
        dailyCache.set(key, data);
      }).catch(() => {});
    }

    if (!optionsCache.has(key)) {
      window.axios.get('/tillwhite/api/production-management/options', {
        params: { date: adjacentDate, store_id: targetStoreId },
        timeout: 20000,
      }).then(({ data }) => {
        optionsCache.set(key, data);
      }).catch(() => {});
    }
  }
}

/** 점포를 변경하면 다른 점포의 동일 날짜 데이터를 새로 조회합니다. */
async function changeStore(value) {
  if (!value || value === storeId.value) return;

  storeId.value = value;
  await loadPage({ force: true });
}

/** 날짜 표시는 즉시 바꾸고, 캐시 또는 최신 서버 데이터로 내용을 갱신합니다. */
async function changeDate(value) {
  if (!value || value === workDate.value) return true;

  workDate.value = value;
  return loadPage({ date: value });
}

/** 저장 후 현재 날짜 캐시만 무효화하여 변경된 값을 서버에서 다시 확인합니다. */
async function reloadCurrentDate() {
  const key = cacheKey(workDate.value, storeId.value);
  dailyCache.delete(key);
  optionsCache.delete(key);
  await loadPage({ force: true });
}

/** 캘린더 날짜 이동이 성공한 뒤에만 목록 탭 전환을 완료하도록 콜백으로 결과를 알립니다. */
async function jumpToDate(value, done) {
  const success = await changeDate(value);

  if (success) {
    tab.value = 'list';
  }

  done?.(success);
}

/** 서버 저장 성공 메시지는 프로젝트 공통 알림으로 표시합니다. */
function showSuccess(message) {
  successMessage.value = message;
}

/** 최초 데이터 준비가 끝난 뒤 메뉴 이동에서 시작된 공통 로딩 오버레이를 종료합니다. */
async function initializePage() {
  try {
    await loadPage();
  } finally {
    if (!disposed) await completePageLoading();
  }
}

/** 화면을 떠날 때 진행 중 요청이 이후 화면 상태를 변경하지 못하도록 취소합니다. */
function disposePage() {
  disposed = true;
  activeRequest?.abort();
}

onMounted(initializePage);
onBeforeUnmount(disposePage);
</script>

<style scoped>
.store-context {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  padding:12px 14px;
  border:1px solid rgba(var(--v-border-color),.7);
  border-radius:12px;
  background:rgb(var(--v-theme-surface));
  box-shadow:0 2px 8px rgba(0,0,0,.045);
}
.store-context-copy { display:flex; flex-direction:column; min-width:0; }
.store-context-label,.store-read-only { font-size:.68rem; color:rgba(var(--v-theme-on-surface),.56); }
.store-context-copy strong { font-size:.9rem; font-weight:650; }
.store-read-only { margin-top:2px; }
.store-select { max-width:240px; }
.production-tabs { border-bottom:1px solid rgba(var(--v-border-color),.7); }
.production-tabs :deep(.v-tab) { font-size:.78rem; font-weight:500; text-transform:none; }
.production-tabs :deep(.v-tab--selected) { font-weight:650; }
@media(max-width:600px) {
  .store-context { align-items:stretch; flex-direction:column; }
  .store-select { max-width:none; }
}
</style>
