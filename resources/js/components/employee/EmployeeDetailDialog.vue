<template>
  <!-- 직원 상세보기 -->
  <AppDialogLayout
    :model-value="modelValue"
    title="직원 상세보기"
    :subtitle="
      displayedEmployee?.name
      ?? '직원의 상세 정보를 확인합니다.'
    "
    :max-width="560"
    @update:model-value="handleDialogChange"
    @after-leave="handleAfterLeave"
  >
    <!-- 조회 중 -->
    <div
      v-if="detailState === 'loading'"
      class="d-flex flex-column align-center justify-center ga-3 py-8"
      role="status"
      aria-live="polite"
    >
      <v-progress-circular
        indeterminate
        color="primary"
        size="32"
      />

      <span class="app-supporting-text">
        불러오는 중...
      </span>
    </div>

    <!-- 조회 실패 -->
    <div
      v-else-if="detailState === 'error'"
      class="d-flex flex-column ga-3 py-4"
    >
      <v-alert
        type="error"
        variant="tonal"
        density="compact"
        class="app-supporting-alert"
      >
        {{ detailError }}
      </v-alert>

      <v-btn
        variant="tonal"
        color="primary"
        min-height="44"
        block
        @click="emit('retry')"
      >
        다시 시도
      </v-btn>
    </div>

    <!-- 데이터 없음 -->
    <div
      v-else-if="detailState === 'empty'"
      class="d-flex flex-column align-center ga-3 py-6"
    >
      <span class="app-supporting-text">
        조회된 직원 정보가 없습니다.
      </span>

      <v-btn
        variant="tonal"
        color="primary"
        min-height="44"
        block
        @click="emit('retry')"
      >
        다시 시도
      </v-btn>
    </div>

    <!-- 조회 완료 -->
    <div
      v-else-if="detailState === 'ready' && displayedEmployee"
      class="d-flex flex-column ga-3 w-100"
    >
      <!-- 직원 요약 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section pa-3"
      >
        <div class="d-flex flex-column ga-2">
          <div class="d-flex align-start justify-space-between ga-2">
            <div class="app-card-content d-flex flex-column ga-1 flex-grow-1">
              <div class="app-form-section-title font-weight-bold">
                {{ displayValue(displayedEmployee.name) }}
              </div>
            </div>

            <v-menu
              v-if="!displayedEmployee.deleted_at && canManage"
              location="bottom end"
            >
              <template #activator="{ props: menuProps }">
                <v-btn
                  v-bind="menuProps"
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  aria-label="직원 관리 메뉴"
                />
              </template>

              <v-list
                density="compact"
                min-width="190"
              >
                <v-list-item
                  prepend-icon="mdi-lock-reset"
                  title="비밀번호 초기화"
                  @click="emit('password-reset')"
                />
              </v-list>
            </v-menu>
          </div>

          <div class="d-flex align-center justify-space-between ga-2">
            <div
              class="app-card-content app-supporting-text text-medium-emphasis"
            >
              {{ displayValue(displayedEmployee.employee_code) }}
            </div>

            <v-chip
              size="small"
              :color="
                displayedEmployee.deleted_at
                  ? undefined
                  : employmentStatusColor(
                      displayedEmployee.employment_status
                    )
              "
              variant="tonal"
              :clickable="
                !displayedEmployee.deleted_at && canManage
              "
              :append-icon="
                !displayedEmployee.deleted_at && canManage
                  ? 'mdi-chevron-down'
                  : undefined
              "
              @click="openStatusDialog"
            >
              {{
                displayedEmployee.deleted_at
                  ? '삭제됨'
                  : employmentStatus(
                      displayedEmployee.employment_status
                    )
              }}
            </v-chip>
          </div>
        </div>
      </v-card>

      <!-- 삭제 직원 안내 -->
      <v-alert
        v-if="displayedEmployee.deleted_at"
        type="warning"
        variant="tonal"
        density="compact"
        icon="mdi-delete-clock-outline"
        class="app-supporting-alert"
      >
        삭제된 직원입니다. 정보와 기존 재직 상태는 보존되며
        복구 후 다시 관리할 수 있습니다.
      </v-alert>

      <!-- 기본 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="d-flex align-center ga-2">
          <div class="app-form-section-title">
            기본 정보
          </div>
        </div>

        <div class="d-flex flex-column ga-3">
          <div
            v-for="item in basicInfoItems"
            :key="item.label"
            class="d-flex flex-column flex-sm-row align-sm-start justify-space-between ga-1 ga-sm-3"
          >
            <div class="app-supporting-text text-medium-emphasis">
              {{ item.label }}
            </div>

            <div
              class="app-card-content app-supporting-text font-weight-medium text-sm-right"
            >
              {{ item.value }}
            </div>
          </div>
        </div>
      </v-card>

      <!-- 소속 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="d-flex align-center ga-2">
          <div class="app-form-section-title">
            소속 정보
          </div>
        </div>

        <div class="d-flex flex-column ga-3">
          <div
            v-for="item in affiliationItems"
            :key="item.label"
            class="d-flex flex-column flex-sm-row align-sm-start justify-space-between ga-1 ga-sm-3"
          >
            <div class="app-supporting-text text-medium-emphasis">
              {{ item.label }}
            </div>

            <div
              class="app-card-content app-supporting-text font-weight-medium text-sm-right"
            >
              {{ item.value }}
            </div>
          </div>
        </div>
      </v-card>

      <!-- 재직 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="d-flex align-center ga-2">
          <div class="app-form-section-title">
            재직 정보
          </div>
        </div>

        <div class="d-flex flex-column ga-3">
          <div
            class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-1 ga-sm-3"
          >
            <div class="app-supporting-text text-medium-emphasis">
              재직 상태
            </div>

            <div class="d-flex align-center justify-sm-end">
              <v-chip
                size="x-small"
                :color="
                  employmentStatusColor(
                    displayedEmployee.employment_status
                  )
                "
                variant="tonal"
              >
                {{
                  employmentStatus(
                    displayedEmployee.employment_status
                  )
                }}
              </v-chip>
            </div>
          </div>

          <div
            v-for="item in employmentItems"
            :key="item.label"
            class="d-flex flex-column flex-sm-row align-sm-start justify-space-between ga-1 ga-sm-3"
          >
            <div class="app-supporting-text text-medium-emphasis">
              {{ item.label }}
            </div>

            <div
              class="app-card-content app-supporting-text font-weight-medium text-sm-right"
            >
              {{ item.value }}
            </div>
          </div>
        </div>
      </v-card>

      <!-- 시스템 정보 -->
      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section d-flex flex-column ga-3 pa-3"
      >
        <div class="d-flex align-center ga-2">
          <div class="app-form-section-title">
            시스템 정보
          </div>
        </div>

        <div class="d-flex flex-column ga-3">
          <div
            class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-1 ga-sm-3"
          >
            <div class="app-supporting-text text-medium-emphasis">
              계정 상태
            </div>

            <div class="d-flex align-center justify-sm-end">
              <v-chip
                size="x-small"
                :color="
                  displayedEmployee.is_active
                    ? 'success'
                    : undefined
                "
                variant="tonal"
              >
                {{ accountStatus(displayedEmployee.is_active) }}
              </v-chip>
            </div>
          </div>

          <div
            v-for="item in systemInfoItems"
            :key="item.label"
            class="d-flex flex-column flex-sm-row align-sm-start justify-space-between ga-1 ga-sm-3"
          >
            <div class="app-supporting-text text-medium-emphasis">
              {{ item.label }}
            </div>

            <div
              class="app-card-content app-supporting-text font-weight-medium text-sm-right"
            >
              {{ item.value }}
            </div>
          </div>
        </div>

        <!-- 변경 이력 -->
        <template v-if="displayedEmployee.audit_history?.length">
          <v-divider />

          <details>
            <summary
              class="app-supporting-text font-weight-medium cursor-pointer"
            >
              변경 이력 보기
            </summary>

            <div class="d-flex flex-column ga-3 mt-3">
              <div
                v-for="entry in displayedEmployee.audit_history"
                :key="entry.id"
                class="d-flex flex-column flex-sm-row justify-space-between ga-1 ga-sm-3"
              >
                <div class="app-supporting-text font-weight-medium">
                  {{ auditActionText(entry.action) }}
                </div>

                <div
                  class="app-card-content app-supporting-text text-medium-emphasis text-sm-right"
                >
                  {{ entry.user?.name ?? '-' }}
                  ·
                  {{ formatDateTime(entry.at) }}
                </div>
              </div>
            </div>
          </details>
        </template>
      </v-card>
    </div>

    <!-- 공통 푸터 -->
    <template #footer>
      <!-- 조회 중 / 조회 실패 / 데이터 없음 -->
      <AppDialogFooter
        v-if="detailState !== 'ready'"
        :button-count="1"
        :show-confirm="false"
        cancel-text="닫기"
        @cancel="close"
      />

      <!-- 삭제된 직원 -->
      <AppDialogFooter
        v-else-if="displayedEmployee?.deleted_at"
        :button-count="2"
        cancel-text="닫기"
        confirm-text="복구"
        confirm-action="save"
        @cancel="close"
        @confirm="requestManageAction('restore')"
      >
        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="success"
            min-height="44"
            @click="requestManageAction('restore')"
          >
            복구
          </v-btn>
        </template>
      </AppDialogFooter>

      <!-- 정상 직원 -->
      <AppDialogFooter
        v-else-if="displayedEmployee"
        :button-count="3"
        cancel-text="닫기"
        confirm-text="수정"
        confirm-action="edit"
        @cancel="close"
        @confirm="requestManageAction('edit')"
      >
        <template #secondary>
          <v-btn
            block
            variant="tonal"
            color="error"
            min-height="44"
            @click="requestManageAction('delete')"
          >
            삭제
          </v-btn>
        </template>

        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="primary"
            min-height="44"
            @click="requestManageAction('edit')"
          >
            수정
          </v-btn>
        </template>
      </AppDialogFooter>

      <!-- 직원 데이터 없음 -->
      <AppDialogFooter
        v-else
        :button-count="1"
        :show-confirm="false"
        cancel-text="닫기"
        @cancel="close"
      />
    </template>
  </AppDialogLayout>

  <!-- 재직 상태 변경 -->
  <AppDialogLayout
    :model-value="statusDialog"
    title="재직 상태 변경"
    subtitle="직원의 재직 상태를 변경합니다."
    :max-width="380"
    persistent
    card-class="app-dialog-card--compact"
    @update:model-value="handleStatusDialogChange"
  >
    <div class="d-flex flex-column ga-3 w-100">
      <div class="app-form-notice">
        <div>
          현재 상태:
          <strong>
            {{ employmentStatus(displayedEmployee?.employment_status) }}
          </strong>
        </div>
      </div>

      <v-card
        variant="flat"
        rounded="lg"
        class="app-form-section pa-3"
      >
        <v-radio-group
          v-model="nextStatus"
          hide-details
        >
          <v-radio
            label="재직"
            value="active"
          />

          <v-radio
            label="휴직"
            value="leave"
          />

          <v-radio
            label="퇴사"
            value="resigned"
          />
        </v-radio-group>
      </v-card>
    </div>

    <template #footer>
      <AppDialogFooter
        :button-count="2"
        cancel-text="취소"
        confirm-text="변경"
        confirm-action="edit"
        @cancel="closeStatusDialog"
        @confirm="submitStatus"
      >
        <template #confirm>
          <v-btn
            block
            variant="flat"
            color="primary"
            min-height="44"
            :disabled="
              nextStatus === displayedEmployee?.employment_status
            "
            @click="submitStatus"
          >
            변경
          </v-btn>
        </template>
      </AppDialogFooter>
    </template>
  </AppDialogLayout>
</template>

<script setup>
import {
  computed,
  ref,
  shallowRef,
  watch,
} from 'vue';

import AppDialogLayout from '../common/AppDialogLayout.vue';
import AppDialogFooter from '../common/AppDialogFooter.vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  employee: {
    type: Object,
    default: null,
  },

  detailState: {
    type: String,
    default: 'ready',
  },

  detailError: {
    type: String,
    default: '',
  },

  canManage: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  'update:modelValue',
  'edit',
  'delete',
  'restore',
  'password-reset',
  'status-change',
  'close',
  'retry',
  'permission-denied',
]);

const statusDialog = ref(false);
const nextStatus = ref('active');

/*
 * 다이얼로그가 닫히는 동안 유지할 화면 표시용 직원 정보
 */
const displayedEmployee = shallowRef(null);

/*
 * 열려 있을 때만 최신 직원 데이터를 반영합니다.
 * 닫히는 동안에는 마지막 직원 정보를 유지합니다.
 */
watch(
  [
    () => props.modelValue,
    () => props.employee,
  ],
  ([isOpen, employee]) => {
    if (isOpen) {
      // 새 직원 조회 시작 시 이전 직원 정보 제거
      displayedEmployee.value = employee ?? null;
    }
  },
  {
    immediate: true,
  },
);

/*
 * 기본 정보
 */
const basicInfoItems = computed(() => {
  const employee = displayedEmployee.value;

  if (!employee) {
    return [];
  }

  return [
    {
      label: '이름',
      value: displayValue(employee.name),
    },
    {
      label: '사번 / 로그인 ID',
      value: displayValue(employee.employee_code),
    },
    {
      label: '연락처',
      value: displayValue(employee.phone),
    },
    {
      label: '생년월일',
      value: formatDate(employee.birth_date),
    },
  ];
});

/*
 * 소속 정보
 */
const affiliationItems = computed(() => {
  const employee = displayedEmployee.value;

  if (!employee) {
    return [];
  }

  return [
    {
      label: '소속 점포',
      value: employeeStoreName(employee),
    },
    {
      label: '소속 부서',
      value: department(employee.department),
    },
    {
      label: '직급',
      value: employee.position?.name ?? '-',
    },
    {
      label: '시스템 역할',
      value: employee.role?.name ?? '-',
    },
  ];
});

/*
 * 재직 정보
 */
const employmentItems = computed(() => {
  const employee = displayedEmployee.value;

  if (!employee) {
    return [];
  }

  return [
    {
      label: '입사일',
      value: formatDate(employee.hired_at),
    },
    {
      label: '퇴사일',
      value: formatDate(employee.resigned_at),
    },
  ];
});

/*
 * 시스템 정보
 */
const systemInfoItems = computed(() => {
  const employee = displayedEmployee.value;

  if (!employee) {
    return [];
  }

  return [
    {
      label: '로그인일',
      value: formatDateTime(employee.last_login_at),
    },
    {
      label: '비밀번호 변경일',
      value: formatDateTime(employee.password_changed_at),
    },
    {
      label: '계정생성일',
      value: formatDateTime(employee.created_at),
    },
    {
      label: '등록자',
      value: historyActor(
        employee.management_history?.created,
      ),
    },
    {
      label: '등록일',
      value: historyAt(
        employee.management_history?.created,
        employee.created_at,
      ),
    },
    {
      label: '수정자',
      value: historyActor(
        employee.management_history?.updated,
      ),
    },
    {
      label: '수정일',
      value: historyAt(
        employee.management_history?.updated,
        employee.updated_at,
      ),
    },
    {
      label: '삭제자',
      value: employee.deleted_at
        ? historyActor(
            employee.management_history?.deleted,
          )
        : '-',
    },
    {
      label: '삭제일',
      value: employee.deleted_at
        ? historyAt(
            employee.management_history?.deleted,
            employee.deleted_at,
          )
        : '-',
    },
  ];
});

/*
 * 기존 직원 관리 권한 검사
 */
function requestManageAction(action) {
  if (!props.canManage) {
    emit(
      'permission-denied',
      '직원 정보를 관리할 권한이 없습니다.',
    );

    return;
  }

  emit(action);
}

/*
 * 기존 재직 상태 변경창 열기
 */
function openStatusDialog() {
  if (
    !props.employee
    || props.employee.deleted_at
    || !props.canManage
  ) {
    return;
  }

  nextStatus.value = props.employee.employment_status;
  statusDialog.value = true;
}

/*
 * 재직 상태 변경창 취소
 */
function closeStatusDialog() {
  statusDialog.value = false;
}

/*
 * 재직 상태 변경창 닫힘 처리
 */
function handleStatusDialogChange(value) {
  if (!value) {
    closeStatusDialog();
  }
}

/*
 * 기존 재직 상태 변경 요청
 */
function submitStatus() {
  if (
    nextStatus.value
    === props.employee?.employment_status
  ) {
    return;
  }

  emit('status-change', nextStatus.value);
  statusDialog.value = false;
}

/*
 * 상세보기 열림 상태 변경
 *
 * 닫힐 때는 데이터 정리 이벤트를 즉시 발생시키지 않습니다.
 */
function handleDialogChange(value) {
  if (!value) {
    close();
    return;
  }

  emit('update:modelValue', value);
}

/*
 * 닫힘 요청
 *
 * 먼저 다이얼로그만 닫습니다.
 * 부모 정리 이벤트는 닫힘 애니메이션 후에 발생합니다.
 */
function close() {
  emit('update:modelValue', false);
}

/*
 * 닫힘 애니메이션 종료
 */
function handleAfterLeave() {
  if (props.modelValue) {
    return;
  }

  statusDialog.value = false;
  nextStatus.value = 'active';
  displayedEmployee.value = null;

  emit('close');
}

/*
 * 값 표시
 */
function displayValue(value) {
  if (
    value === null
    || value === undefined
    || value === ''
  ) {
    return '-';
  }

  return value;
}

/*
 * 부서명
 */
function department(value) {
  const departments = {
    kitchen: '주방',
    hall: '홀',
    head_office: '본사',
  };

  return departments[value] ?? value ?? '-';
}

/*
 * 재직 상태명
 */
function employmentStatus(value) {
  const statuses = {
    active: '재직',
    leave: '휴직',
    resigned: '퇴사',
  };

  return statuses[value] ?? value ?? '-';
}

/*
 * 재직 상태 색상
 */
function employmentStatusColor(value) {
  const colors = {
    active: 'success',
    leave: 'warning',
    resigned: 'error',
  };

  return colors[value] ?? undefined;
}

/*
 * 소속 점포명
 */
function employeeStoreName(employee) {
  if (employee?.store?.name) {
    return employee.store.name;
  }

  if (employee?.department === 'head_office') {
    return '본사';
  }

  return '-';
}

/*
 * 계정 상태명
 */
function accountStatus(value) {
  if (value === true) {
    return '활성';
  }

  if (value === false) {
    return '비활성';
  }

  return '-';
}

/*
 * 날짜 표시
 */
function formatDate(value) {
  if (!value) {
    return '-';
  }

  const dateOnly = String(value).match(
    /^(\d{4})-(\d{2})-(\d{2})/,
  );

  if (dateOnly) {
    return `${dateOnly[1]}.${dateOnly[2]}.${dateOnly[3]}`;
  }

  return String(value);
}

/*
 * 변경 이력 작업명
 */
function auditActionText(action) {
  return {
    create: '등록',
    update: '수정',
    delete: '삭제',
    restore: '복구',
  }[action] ?? '-';
}

/*
 * 관리 이력 작업자
 */
function historyActor(entry) {
  return entry?.user?.name ?? '-';
}

/*
 * 관리 이력 일시
 */
function historyAt(entry, fallbackAt = null) {
  return formatDateTime(entry?.at ?? fallbackAt);
}

/*
 * 날짜 및 시간 표시
 */
function formatDateTime(value) {
  if (!value) {
    return '-';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat('ko-KR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date);
}
</script>