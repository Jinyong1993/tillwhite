<template>
  <AppShell :title="pageTitle">
    <template #default="{ can, setError }">
      <!--
        근무 관리 화면의 탭입니다.

        스케줄   : 근무 스케줄 조회
        휴가     : 휴가 신청 및 승인/반려
        희망휴무 : 희망휴무 신청 및 승인/반려
      -->
      <v-tabs
        v-model="tab"
        grow
        density="compact"
      >
        <v-tab value="schedule">
          스케줄
        </v-tab>

        <v-tab value="leave">
          휴가
        </v-tab>

        <v-tab value="dayoff">
          희망휴무
        </v-tab>
      </v-tabs>

      <v-window
        v-model="tab"
        class="mt-4"
      >
        <!-- 근무 스케줄 탭 -->
        <v-window-item value="schedule">
          <!-- 등록된 근무 스케줄 목록 -->
          <v-list>
            <v-list-item
              v-for="schedule in data.schedules"
              :key="schedule.id"
              :title="`${schedule.work_date} · ${schedule.user?.name}`"
              :subtitle="`${schedule.work_code?.name ?? schedule.status} ${schedule.scheduled_start_time ?? ''}~${schedule.scheduled_end_time ?? ''}`"
            />
          </v-list>

          <!-- 조회된 스케줄이 없는 경우 표시 -->
          <v-empty-state
            v-if="!data.schedules.length"
            title="스케줄이 없습니다."
          />
        </v-window-item>

        <!-- 휴가 관리 탭 -->
        <v-window-item value="leave">
          <!-- 휴가 신청 Form -->
          <v-form @submit.prevent="applyLeave(setError)">
            <!-- 휴가 종류 -->
            <v-select
              v-model="leave.leave_type"
              :items="leaveTypes"
              label="휴가 종류"
              variant="outlined"
            />

            <!-- 휴가 시작일 및 종료일 -->
            <div class="d-flex ga-2">
              <v-text-field
                v-model="leave.start_date"
                type="date"
                label="시작일"
                variant="outlined"
              />

              <v-text-field
                v-model="leave.end_date"
                type="date"
                label="종료일"
                variant="outlined"
              />
            </div>

            <!-- 차감할 휴가 일수 -->
            <v-number-input
              v-model="leave.amount"
              label="차감 일수"
              :step="0.5"
              :min="0.5"
              variant="outlined"
            />

            <!-- 휴가 신청 사유 -->
            <v-textarea
              v-model="leave.reason"
              label="신청 사유"
              variant="outlined"
              rows="2"
            />

            <!-- 휴가 신청 -->
            <v-btn
              type="submit"
              block
            >
              휴가 신청
            </v-btn>
          </v-form>

          <v-divider class="my-4" />

          <!-- 휴가 신청 내역 -->
          <v-list>
            <v-list-item
              v-for="request in data.leave_requests"
              :key="request.id"
              :title="`${request.user?.name} · ${request.start_date}~${request.end_date}`"
              :subtitle="`${request.leave_type} · ${request.status}`"
            >
              <template #append>
                <!--
                  leave.manage 권한이 있고 신청 상태가 pending인 경우에만
                  승인 및 반려 버튼을 표시합니다.
                -->
                <div
                  v-if="can('leave.manage') && request.status === 'pending'"
                  class="d-flex ga-1"
                >
                  <v-btn
                    size="x-small"
                    @click="
                      review(
                        'leave',
                        request.id,
                        'approved',
                        setError,
                      )
                    "
                  >
                    승인
                  </v-btn>

                  <v-btn
                    size="x-small"
                    variant="outlined"
                    @click="
                      review(
                        'leave',
                        request.id,
                        'rejected',
                        setError,
                      )
                    "
                  >
                    반려
                  </v-btn>
                </div>
              </template>
            </v-list-item>
          </v-list>
        </v-window-item>

        <!-- 희망휴무 관리 탭 -->
        <v-window-item value="dayoff">
          <!-- 희망휴무 날짜 -->
          <v-text-field
            v-model="dayoffDate"
            type="date"
            label="희망휴무일"
            variant="outlined"
          />

          <!-- 희망휴무 신청 사유 -->
          <v-textarea
            v-model="dayoffReason"
            label="사유"
            variant="outlined"
            rows="2"
          />

          <!-- 희망휴무 신청 -->
          <v-btn
            block
            @click="applyDayOff(setError)"
          >
            희망휴무 신청
          </v-btn>

          <v-divider class="my-4" />

          <!-- 희망휴무 신청 내역 -->
          <v-list>
            <v-list-item
              v-for="request in data.day_off_requests"
              :key="request.id"
              :title="`${request.user?.name} · ${request.target_year}.${request.target_month}`"
              :subtitle="`${request.dates?.map((date) => date.request_date).join(', ')} · ${request.status}`"
            >
              <template #append>
                <!--
                  leave.manage 권한이 있고 신청 상태가 pending인 경우에만
                  승인 및 반려 버튼을 표시합니다.
                -->
                <div
                  v-if="can('leave.manage') && request.status === 'pending'"
                  class="d-flex ga-1"
                >
                  <v-btn
                    size="x-small"
                    @click="
                      review(
                        'dayoff',
                        request.id,
                        'approved',
                        setError,
                      )
                    "
                  >
                    승인
                  </v-btn>

                  <v-btn
                    size="x-small"
                    variant="outlined"
                    @click="
                      review(
                        'dayoff',
                        request.id,
                        'rejected',
                        setError,
                      )
                    "
                  >
                    반려
                  </v-btn>
                </div>
              </template>
            </v-list-item>
          </v-list>
        </v-window-item>
      </v-window>
    </template>
  </AppShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppShell from '../../components/layout/AppShell.vue';

// 현재 페이지 제목
const pageTitle = '근무 관리';

// 현재 선택된 근무 관리 탭
const tab = ref('schedule');

/**
 * 근무 관리 화면에서 사용하는 서버 데이터입니다.
 *
 * schedules        : 근무 스케줄 목록
 * attendances      : 출퇴근 기록 목록
 * leave_requests   : 휴가 신청 목록
 * day_off_requests : 희망휴무 신청 목록
 * work_codes       : 근무 코드 목록
 */
const data = ref({
  schedules: [],
  attendances: [],
  leave_requests: [],
  day_off_requests: [],
  work_codes: [],
});

// 휴가 및 희망휴무 Form의 기본값으로 사용할 오늘 날짜
const today = new Date().toISOString().slice(0, 10);

/**
 * 휴가 신청 Form입니다.
 *
 * leave_type : 휴가 종류
 * day_unit   : 휴가 사용 단위
 * start_date : 휴가 시작일
 * end_date   : 휴가 종료일
 * amount     : 차감할 휴가 일수
 * reason     : 신청 사유
 */
const leave = ref({
  leave_type: 'annual',
  day_unit: 'full_day',
  start_date: today,
  end_date: today,
  amount: 1,
  reason: '',
});

/**
 * 신청할 수 있는 휴가 종류입니다.
 *
 * title : 화면에 표시할 휴가명
 * value : DB 및 API에서 사용하는 휴가 코드
 */
const leaveTypes = [
  {
    title: '연차',
    value: 'annual',
  },
  {
    title: '반차',
    value: 'half_day',
  },
];

// 신청할 희망휴무 날짜
const dayoffDate = ref(today);

// 희망휴무 신청 사유
const dayoffReason = ref('');

/**
 * 근무 관리 화면에 필요한 데이터를 서버에서 조회합니다.
 *
 * 스케줄, 출퇴근 기록, 휴가 신청, 희망휴무 신청,
 * 근무 코드 등의 데이터를 한 번에 받아 저장합니다.
 */
async function load() {
  const response = await window.axios.get(
    '/tillwhite/api/work',
  );

  data.value = response.data;
}

/**
 * 휴가를 신청합니다.
 *
 * 신청에 성공하면 근무 관리 데이터를 다시 조회하여
 * 새로운 신청 내역을 화면에 반영합니다.
 */
async function applyLeave(setError) {
  try {
    await window.axios.post(
      '/tillwhite/api/work/leave',
      leave.value,
    );

    // 신청 내역을 반영하기 위해 데이터 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '신청 실패',
    );
  }
}

/**
 * 희망휴무를 신청합니다.
 *
 * 현재 화면에서는 한 번에 한 날짜를 입력하고,
 * 서버에는 dates 배열 형태로 전달합니다.
 */
async function applyDayOff(setError) {
  try {
    await window.axios.post(
      '/tillwhite/api/work/day-off',
      {
        dates: [
          dayoffDate.value,
        ],
        reason: dayoffReason.value,
      },
    );

    // 신청 내역을 반영하기 위해 데이터 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '신청 실패',
    );
  }
}

/**
 * 휴가 또는 희망휴무 신청을 승인/반려합니다.
 *
 * type   : 신청 종류 (leave / dayoff)
 * id     : 처리할 신청 ID
 * status : 변경할 상태 (approved / rejected)
 */
async function review(
  type,
  id,
  status,
  setError,
) {
  try {
    await window.axios.put(
      `/tillwhite/api/work/requests/${type}/${id}`,
      {
        status,
      },
    );

    // 처리 결과를 반영하기 위해 데이터 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '처리 실패',
    );
  }
}

// 화면이 처음 열릴 때 근무 관리 데이터 조회
onMounted(load);
</script>