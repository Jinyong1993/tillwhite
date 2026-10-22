<template>
  <AppShell :title="pageTitle">
    <template #default="{ user, can, sessionReady }">
      <PageReadyWatcher
        :session-ready="sessionReady"
        @session-ready="handleSessionReady"
      />

      <main class="main-dashboard d-flex flex-column ga-3">
        <!-- 접근 거부 안내 -->
        <v-alert
          v-if="accessDeniedMessage"
          type="warning"
          variant="tonal"
          density="compact"
          closable
          class="app-supporting-alert"
          @click:close="clearAccessDeniedMessage"
        >
          {{ accessDeniedMessage }}
        </v-alert>

        <!-- 사용자 정보 -->
        <section
          v-if="user"
          class="main-dashboard__section"
          aria-label="로그인 사용자 정보"
        >
          <UserInfoCard :user="user" />
        </section>

        <!-- 금일 생산 현황 -->
        <template v-if="can('production.view')">
          <v-divider
            v-if="user"
            class="main-dashboard__divider"
          />

          <section
            class="main-dashboard__section"
            aria-label="금일 생산 현황"
          >
            <ProductionSummaryCard
              @ready="handleSummaryReady"
            />
          </section>
        </template>

        <!-- 생산 조회 권한이 없으면 API 대기 생략 -->
        <PageReadyWatcher
          v-else
          :session-ready="sessionReady"
          :summary-not-required="true"
          @summary-ready="handleSummaryReady"
        />

        <!-- 업무 바로가기 구분선 -->
        <v-divider
          v-if="user || can('production.view')"
          class="main-dashboard__divider"
        />

        <!-- 업무 바로가기 -->
        <section
          class="main-dashboard__section d-flex flex-column ga-3"
          aria-labelledby="main-quick-title"
        >
          <div class="d-flex flex-column ga-1">
            <h3
              id="main-quick-title"
              class="main-dashboard__section-title"
            >
              업무 바로가기
            </h3>

            <span class="main-dashboard__description">
              필요한 업무를 선택하세요.
            </span>
          </div>

          <div
            v-if="availableQuickItems.length"
            class="d-flex flex-column ga-2"
          >
            <v-btn
              v-for="item in availableQuickItems"
              :key="item.title"
              :to="item.developing ? undefined : item.to"
              :disabled="item.developing"
              variant="text"
              block
              class="main-dashboard__menu-button"
            >
              <span
                class="main-dashboard__menu-content d-flex align-center justify-space-between ga-2 w-100"
              >
                <span
                  class="main-dashboard__menu-copy d-flex flex-column align-start ga-1"
                >
                  <strong class="main-dashboard__menu-title">
                    {{ item.title }}
                  </strong>

                  <span class="main-dashboard__description">
                    {{ item.description }}
                  </span>
                </span>

                <span
                  v-if="item.developing"
                  class="main-dashboard__developing flex-shrink-0"
                >
                  개발 중
                </span>

                <v-icon
                  v-else
                  icon="mdi-chevron-right"
                  size="18"
                  class="main-dashboard__arrow flex-shrink-0"
                  aria-hidden="true"
                />
              </span>
            </v-btn>
          </div>

          <div
            v-else
            class="main-dashboard__empty app-card d-flex flex-column ga-1"
          >
            <strong>사용 가능한 메뉴가 없습니다.</strong>
            <span>
              메뉴 접근 권한이 필요한 경우
              관리자에게 문의해 주세요.
            </span>
          </div>
        </section>
      </main>
    </template>
  </AppShell>
</template>

<script setup>
import {
  computed,
  defineComponent,
  h,
  ref,
  watch,
} from 'vue';

import {
  useRoute,
  useRouter,
} from 'vue-router';

import AppShell from '../components/layout/AppShell.vue';
import ProductionSummaryCard from '../components/production/ProductionSummaryCard.vue';
import UserInfoCard from '../components/user/UserInfoCard.vue';

import { useAppLoading } from '../composables/useAppLoading';
import { useSession } from '../composables/useSession';

const route = useRoute();
const router = useRouter();

const {
  completePageLoading,
} = useAppLoading();

const {
  can,
} = useSession();

const pageTitle = '메인';

const sessionIsReady = ref(false);

const summaryIsReady = ref(
  !can('production.view'),
);

const pageLoadingCompleted = ref(false);

/* 접근 거부 메시지 */
const accessDeniedMessage = computed(() => {
  return typeof route.query.accessDenied === 'string'
    ? route.query.accessDenied
    : '';
});

/* 안내 닫기 */
async function clearAccessDeniedMessage() {
  if (!route.query.accessDenied) return;

  const query = {
    ...route.query,
  };

  delete query.accessDenied;

  await router.replace({
    name: 'main',
    query,
  });
}

/*
 * 세션과 생산 현황 조회가 모두 끝나면
 * 기존 애플리케이션 공통 로딩을 종료합니다.
 */
async function tryCompletePageLoading() {
  if (pageLoadingCompleted.value) return;
  if (!sessionIsReady.value) return;
  if (!summaryIsReady.value) return;

  pageLoadingCompleted.value = true;

  await completePageLoading();
}

/* 세션 준비 완료 */
async function handleSessionReady() {
  sessionIsReady.value = true;

  if (!can('production.view')) {
    summaryIsReady.value = true;
  }

  await tryCompletePageLoading();
}

/* 생산 현황 준비 완료 */
async function handleSummaryReady() {
  summaryIsReady.value = true;

  await tryCompletePageLoading();
}

/*
 * AppShell 슬롯의 sessionReady를
 * 기존 페이지 로딩 이벤트에 연결합니다.
 */
const PageReadyWatcher = defineComponent({
  name: 'PageReadyWatcher',

  props: {
    sessionReady: {
      type: Boolean,
      default: false,
    },

    summaryNotRequired: {
      type: Boolean,
      default: false,
    },
  },

  emits: [
    'session-ready',
    'summary-ready',
  ],

  setup(props, { emit }) {
    watch(
      () => props.sessionReady,
      (ready) => {
        if (ready) {
          emit('session-ready');
        }
      },
      {
        immediate: true,
      },
    );

    watch(
      () => props.summaryNotRequired,
      (notRequired) => {
        if (notRequired) {
          emit('summary-ready');
        }
      },
      {
        immediate: true,
      },
    );

    return () => h('span', {
      style: {
        display: 'none',
      },
    });
  },
});

/*
 * 기존 권한과 라우터 경로 유지
 */
const quickItems = [
  {
    title: '생산·폐기 관리',
    description: '생산·이월·로스·폐기 기록',
    to: '/tillwhite/production',
    permission: 'production.view',
    developing: false,
  },
  {
    title: '제품 관리',
    description: '제품 정보 및 레시피 확인',
    to: '/tillwhite/products',
    permission: 'product.view',
    developing: false,
  },
  {
    title: '근무 관리',
    description: '근무 일정 및 업무 관리',
    to: '/tillwhite/work',
    permission: 'schedule.view',
    developing: true,
  },
  {
    title: '매출 관리',
    description: '매출 현황 및 실적 관리',
    to: '/tillwhite/sales',
    permission: 'sales.view',
    developing: true,
  },
];

/* 권한별 접근 가능 메뉴 */
const availableQuickItems = computed(() =>
  quickItems.filter((item) => can(item.permission))
);
</script>

<style scoped>
/*
 * 메인 전용 디자인.
 * 기본 배치는 Vuetify 유틸리티,
 * 공통 카드와 안내는 글로벌 CSS 재사용.
 */

.main-dashboard {
  width: 100%;
  min-width: 0;
  padding-bottom: 12px;

  --main-line: rgba(var(--v-theme-on-surface), 0.12);
  --main-fill: rgba(var(--v-theme-on-surface), 0.025);
}

/* 섹션 경계 */
.main-dashboard__section {
  width: 100%;
  min-width: 0;
}

.main-dashboard__divider {
  border-color: var(--main-line);
  opacity: 1;
}

/* 제목 */
.main-dashboard__section-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.45;
}

/* 보조 설명 */
.main-dashboard__description {
  font-size: 0.75rem;
  font-weight: 400;
  line-height: 1.45;
  color: rgba(var(--v-theme-on-surface), 0.6);
  overflow-wrap: anywhere;
}

/* 업무 메뉴 */
.main-dashboard__menu-button {
  width: 100%;
  min-width: 0;
  min-height: 60px;
  height: auto !important;
  padding: 11px 13px;

  border: 1px solid var(--main-line);
  border-radius: 10px;
  background: var(--main-fill);

  box-shadow: none;
  color: rgb(var(--v-theme-on-surface));
  text-transform: none;
  letter-spacing: normal;
}

/* Vuetify 버튼 콘텐츠 */
.main-dashboard__menu-button :deep(.v-btn__content) {
  width: 100%;
  min-width: 0;
  white-space: normal;
  text-align: left;
}

.main-dashboard__menu-content {
  min-width: 0;
}

.main-dashboard__menu-copy {
  flex: 1 1 0;
  min-width: 0;
}

.main-dashboard__menu-title {
  width: 100%;
  min-width: 0;
  font-size: 0.85rem;
  font-weight: 600;
  line-height: 1.45;
  overflow-wrap: anywhere;
}

.main-dashboard__arrow {
  opacity: 0.45;
}

.main-dashboard__developing {
  font-size: 0.7rem;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), 0.55);
  white-space: nowrap;
}

/* 비활성 메뉴 */
.main-dashboard__menu-button.v-btn--disabled {
  opacity: 0.55;
}

/* 메뉴 없음 */
.main-dashboard__empty {
  padding: 14px;
  border: 1px solid var(--main-line);
  border-radius: 10px;
  background: var(--main-fill);
}

.main-dashboard__empty strong {
  font-size: 0.8rem;
}

.main-dashboard__empty span {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

/* 마우스 및 키보드 조작 */
@media (hover: hover) {
  .main-dashboard__menu-button:not(.v-btn--disabled):hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
}

.main-dashboard__menu-button:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* 320px 모바일 */
@media (max-width: 360px) {
  .main-dashboard__menu-button {
    min-height: 56px;
    padding: 10px;
  }

  .main-dashboard__menu-title {
    font-size: 0.8rem;
  }

  .main-dashboard__description {
    font-size: 0.7rem;
  }
}
</style>