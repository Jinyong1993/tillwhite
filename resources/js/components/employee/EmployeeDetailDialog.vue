<template>
  <v-dialog
    :model-value="modelValue"
    max-width="560"
    @update:model-value="handleDialogChange"
  >
    <v-card
      v-if="employee"
      class="employee-detail-dialog"
      rounded="lg"
    >
      <!--
        직원 상세보기 고정 헤더

        직원 이름, 사번 / 로그인 ID, 재직 상태를 표시합니다.

        이 영역은 스크롤 영역 밖에 있기 때문에
        직원 정보가 많아져도 헤더에는 스크롤바가 표시되지 않습니다.
      -->
      <div class="detail-header">
        <div class="d-flex align-start justify-space-between ga-4">
          <div class="min-width-0">
            <div class="text-h6 font-weight-bold">
              {{ displayValue(employee.name) }}
            </div>

            <div class="detail-subtitle text-medium-emphasis mt-1">
              {{ displayValue(employee.employee_code) }}
            </div>
          </div>

          <div class="detail-header-actions">
            <!-- 재직 상태를 먼저 보여주고 관리 메뉴는 헤더의 가장 오른쪽에 고정합니다. -->
            <v-chip
              size="small"
              :color="
                employee.deleted_at
                  ? undefined
                  : employmentStatusColor(employee.employment_status)
              "
              variant="tonal"
              :clickable="!employee.deleted_at && canManage"
              :append-icon="
                !employee.deleted_at && canManage
                  ? 'mdi-chevron-down'
                  : undefined
              "
              @click="openStatusDialog"
            >
              {{
                employee.deleted_at
                  ? '삭제됨'
                  : employmentStatus(employee.employment_status)
              }}
            </v-chip>

            <!-- 자주 쓰지 않는 관리 기능은 상단 관리 메뉴에 모읍니다. -->
            <v-menu
              v-if="!employee.deleted_at && canManage"
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

              <v-list density="compact" min-width="190">
                <v-list-item
                  prepend-icon="mdi-lock-reset"
                  title="비밀번호 초기화"
                  @click="$emit('password-reset')"
                />
              </v-list>
            </v-menu>
          </div>
        </div>
      </div>

      <v-divider />

      <!--
        직원 상세정보 스크롤 영역

        내용이 화면보다 길어지는 경우
        이 영역에만 스크롤이 적용됩니다.

        위의 직원 이름 / 사번 헤더와
        아래의 버튼 영역에는 스크롤을 적용하지 않습니다.
      -->
      <div class="detail-scroll-area">
        <v-alert
          v-if="employee.deleted_at"
          class="employee-detail-guide ma-5 mb-0"
          type="warning"
          variant="tonal"
          density="compact"
          icon="mdi-delete-clock-outline"
        >
          삭제된 직원입니다. 정보와 기존 재직 상태는 보존되며 복구 후 다시 관리할 수 있습니다.
        </v-alert>

        <!-- 기본 정보 -->
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon
              icon="mdi-account-outline"
              size="small"
            />

            기본 정보
          </div>

          <div class="employee-detail-grid">
            <div class="detail-label">
              이름
            </div>

            <div class="detail-value">
              {{ displayValue(employee.name) }}
            </div>

            <div class="detail-label">
              사번 / 로그인 ID
            </div>

            <div class="detail-value">
              {{ displayValue(employee.employee_code) }}
            </div>

            <div class="detail-label">
              연락처
            </div>

            <div class="detail-value">
              {{ displayValue(employee.phone) }}
            </div>

            <div class="detail-label">
              생년월일
            </div>

            <div class="detail-value">
              {{ formatDate(employee.birth_date) }}
            </div>
          </div>
        </section>

        <v-divider />

        <!-- 소속 정보 -->
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon
              icon="mdi-office-building-outline"
              size="small"
            />

            소속 정보
          </div>

          <div class="employee-detail-grid">
            <div class="detail-label">
              소속 점포
            </div>

            <div class="detail-value">
              {{ employeeStoreName(employee) }}
            </div>

            <div class="detail-label">
              소속 부서
            </div>

            <div class="detail-value">
              {{ department(employee.department) }}
            </div>

            <div class="detail-label">
              직급
            </div>

            <div class="detail-value">
              {{ employee.position?.name ?? '-' }}
            </div>

            <div class="detail-label">
              시스템 역할
            </div>

            <div class="detail-value">
              {{ employee.role?.name ?? '-' }}
            </div>
          </div>
        </section>

        <v-divider />

        <!-- 재직 정보 -->
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon
              icon="mdi-briefcase-outline"
              size="small"
            />

            재직 정보
          </div>

          <div class="employee-detail-grid">
            <div class="detail-label">
              재직 상태
            </div>

            <div class="detail-value">
              <v-chip
                size="x-small"
                :color="employmentStatusColor(employee.employment_status)"
                variant="tonal"
              >
                {{ employmentStatus(employee.employment_status) }}
              </v-chip>
            </div>

            <div class="detail-label">
              입사일
            </div>

            <div class="detail-value">
              {{ formatDate(employee.hired_at) }}
            </div>

            <div class="detail-label">
              퇴사일
            </div>

            <div class="detail-value">
              {{ formatDate(employee.resigned_at) }}
            </div>
          </div>
        </section>

        <v-divider />

        <!--
          시스템 정보

          계정 활성 상태(is_active)는 조회만 합니다.
          화면에서 직접 변경하지 않습니다.
        -->
        <section class="detail-section">
          <div class="detail-section-title">
            <v-icon
              icon="mdi-shield-account-outline"
              size="small"
            />

            시스템 정보
          </div>

          <div class="employee-detail-grid">
            <div class="detail-label">
              계정 상태
            </div>

            <div class="detail-value">
              <v-chip
                size="x-small"
                :color="employee.is_active ? 'success' : undefined"
                variant="tonal"
              >
                {{ accountStatus(employee.is_active) }}
              </v-chip>
            </div>

            <div class="detail-label">
              로그인일
            </div>
            <div class="detail-value">
              {{ formatDateTime(employee.last_login_at) }}
            </div>

            <div class="detail-label">
              비밀번호 변경일
            </div>
            <div class="detail-value">
              {{ formatDateTime(employee.password_changed_at) }}
            </div>

            <div class="detail-label">
              계정생성일
            </div>
            <div class="detail-value">
              {{ formatDateTime(employee.created_at) }}
            </div>

            <div class="detail-label">등록자</div>
            <div class="detail-value">{{ historyActor(employee.management_history?.created) }}</div>
            <div class="detail-label">등록일</div>
            <div class="detail-value">{{ historyAt(employee.management_history?.created, employee.created_at) }}</div>

            <div class="detail-label">수정자</div>
            <div class="detail-value">{{ historyActor(employee.management_history?.updated) }}</div>
            <div class="detail-label">수정일</div>
            <div class="detail-value">{{ historyAt(employee.management_history?.updated, employee.updated_at) }}</div>

            <div class="detail-label">삭제자</div>
            <div class="detail-value">{{ employee.deleted_at ? historyActor(employee.management_history?.deleted) : '-' }}</div>
            <div class="detail-label">삭제일</div>
            <div class="detail-value">{{ employee.deleted_at ? historyAt(employee.management_history?.deleted, employee.deleted_at) : '-' }}</div>
          </div>

          <details
            v-if="employee.audit_history?.length"
            class="employee-audit-history"
          >
            <summary>변경 이력 보기</summary>
            <div
              v-for="entry in employee.audit_history"
              :key="entry.id"
              class="employee-audit-entry"
            >
              <span>{{ auditActionText(entry.action) }}</span>
              <span>{{ entry.user?.name ?? '-' }} · {{ formatDateTime(entry.at) }}</span>
            </div>
          </details>
        </section>

      </div>

      <v-divider />

      <!--
        직원 상세보기 고정 하단 버튼

        이 영역 역시 스크롤 영역 밖에 있습니다.
      -->
      <v-card-actions class="detail-actions">
        <v-btn
          variant="text"
          @click="close"
        >
          닫기
        </v-btn>

        <v-spacer />

        <!-- 삭제 직원은 일반 관리 동작 대신 복구만 제공합니다. -->
        <v-btn
          v-if="employee.deleted_at"
          variant="flat"
          prepend-icon="mdi-restore"
          @click="requestManageAction('restore')"
        >
          복구
        </v-btn>

        <template v-else>
          <!-- 닫기는 왼쪽, 위험 동작은 오른쪽 그룹의 왼쪽, 최종 수정은 가장 오른쪽에 둡니다. -->
          <v-btn
            color="error"
            variant="text"
            prepend-icon="mdi-delete-outline"
            @click="requestManageAction('delete')"
          >
            삭제
          </v-btn>

          <v-btn
            variant="text"
            prepend-icon="mdi-pencil-outline"
            @click="requestManageAction('edit')"
          >
            수정
          </v-btn>
        </template>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <!-- 재직 상태 변경 -->
  <v-dialog
    v-model="statusDialog"
    max-width="380"
    persistent
  >
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-2">
        재직 상태 변경
      </v-card-title>

      <v-card-text class="px-5">
        <div class="app-supporting-text text-medium-emphasis mb-3">
          현재 상태: {{ employmentStatus(employee?.employment_status) }}
        </div>

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
      </v-card-text>

      <v-card-actions class="px-5 pb-4">
        <v-btn
          variant="text"
          @click="statusDialog = false"
        >
          취소
        </v-btn>

        <v-spacer />

        <v-btn
          variant="flat"
          :disabled="nextStatus === employee?.employment_status"
          @click="submitStatus"
        >
          변경
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue';
/**
 * 직원 상세보기 다이얼로그입니다.
 * 상세 정보 표시와 관리 동작 진입만 담당하며 실제 API 요청은 EmployeePage가 처리합니다.
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  employee: { type: Object, default: null },
  canManage: { type: Boolean, default: false },
});

const emit = defineEmits([
  'update:modelValue',
  'edit',
  'delete',
  'restore',
  'password-reset',
  'status-change',
  'close',
  'permission-denied',
]);

const statusDialog = ref(false);
const nextStatus = ref('active');

/** 권한이 없는 관리 버튼도 동일한 외형을 유지하고 클릭 시 이유를 안내합니다. */
function requestManageAction(action) {
  if (!props.canManage) {
    emit('permission-denied', '직원 정보를 관리할 권한이 없습니다.');
    return;
  }

  emit(action);
}

/** 재직 상태 변경 권한과 현재 상태를 확인한 뒤 상태 변경창을 엽니다. */
function openStatusDialog() {
  if (!props.employee || props.employee.deleted_at || !props.canManage) return;
  nextStatus.value = props.employee.employment_status;
  statusDialog.value = true;
}

/** 실제로 상태가 달라진 경우에만 부모에 변경 요청을 전달합니다. */
function submitStatus() {
  if (nextStatus.value === props.employee?.employment_status) return;
  emit('status-change', nextStatus.value);
  statusDialog.value = false;
}

/** v-dialog의 열림 상태를 부모와 동기화하고 닫힘 이벤트를 전달합니다. */
function handleDialogChange(value) {
  emit('update:modelValue', value);
  if (!value) emit('close');
}

/** 직원 상세 다이얼로그를 닫고 부모의 정리 로직을 호출합니다. */
function close() {
  emit('update:modelValue', false);
  emit('close');
}

/**
 * 값이 없는 경우 하이픈(-)을 표시합니다.
 */
function displayValue(value) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  return value;
}

/**
 * 소속 부서(department)를
 * 화면에 표시할 한글 이름으로 변환합니다.
 */
function department(value) {
  const departments = {
    kitchen: '주방',
    hall: '홀',
    head_office: '본사',
  };

  return departments[value] ?? value ?? '-';
}

/**
 * 재직 상태(employment_status)를
 * 화면에 표시할 한글 이름으로 변환합니다.
 */
function employmentStatus(value) {
  const statuses = {
    active: '재직',
    leave: '휴직',
    resigned: '퇴사',
  };

  return statuses[value] ?? value ?? '-';
}

/**
 * 재직 상태(employment_status)에 맞는
 * 상태 칩 색상을 반환합니다.
 */
function employmentStatusColor(value) {
  const colors = {
    active: 'success',
    leave: 'warning',
    resigned: 'error',
  };

  return colors[value] ?? undefined;
}

/**
 * 직원의 소속 점포를 표시합니다.
 *
 * 본사(head_office)는 소속 점포가 없으므로
 * "본사"라고 표시합니다.
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

/**
 * 계정 활성 상태(is_active)를
 * 화면에 표시할 한글 상태로 변환합니다.
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

/**
 * 날짜 값을 YYYY.MM.DD 형식으로 표시합니다.
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

/**
 * 날짜와 시간 값을
 * 현재 브라우저 시간대로 변환하여 표시합니다.
 */
function auditActionText(action) {
  return { create: '등록', update: '수정', delete: '삭제', restore: '복구' }[action] ?? '-';
}

/** 등록·수정·삭제 작업자 이름을 표시하며 누락 시 하이픈을 사용합니다. */
function historyActor(entry) {
  return entry?.user?.name ?? '-';
}

/** 관리 이력 일시를 공통 날짜·시간 형식으로 표시합니다. */
function historyAt(entry, fallbackAt = null) {
  return formatDateTime(entry?.at ?? fallbackAt);
}

/** 날짜·시간 값을 사용자 화면용 형식으로 변환합니다. */
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

<style scoped>
/*
 * 상세보기 전체 카드입니다.
 *
 * 카드 전체가 화면 높이를 넘지 않도록 제한하고
 * 가운데 상세정보 영역만 스크롤할 수 있도록 구성합니다.
 */
.employee-detail-dialog {
  display: flex;
  max-height: calc(100vh - 48px);
  flex-direction: column;
  overflow: hidden;
}

/*
 * 직원 이름 / 사번 / 재직 상태가 표시되는 헤더입니다.
 *
 * 스크롤 영역과 분리되어 있으므로
 * 헤더에는 스크롤바가 표시되지 않습니다.
 */
.detail-header {
  flex: 0 0 auto;
  padding: 20px;
}

/*
 * 실제 직원정보가 표시되는 스크롤 영역입니다.
 *
 * 내용이 길어질 때 이 부분에만
 * 세로 스크롤이 적용됩니다.
 */
.detail-scroll-area {
  min-height: 0;
  flex: 1 1 auto;
  overflow-x: hidden;
  overflow-y: auto;
}

/*
 * 기본 정보 / 소속 정보 / 재직 정보 /
 * 시스템 정보 영역입니다.
 */
.detail-section {
  padding: 20px;
}

/* 각 상세정보 영역의 제목입니다. */
.detail-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  font-size: 0.875rem;
  font-weight: 700;
}

/*
 * 상세정보를
 * 항목명 / 실제 값 형태의 2열 구조로 표시합니다.
 */
.employee-detail-grid {
  display: grid;
  grid-template-columns: minmax(130px, 0.8fr) minmax(0, 1.2fr);
  gap: 14px 20px;
}

/* 상세정보 항목명입니다. */
.detail-label {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.875rem;
}

/* 상세정보 실제 값입니다. */
.detail-value {
  min-width: 0;
  font-size: 0.875rem;
  font-weight: 500;
  text-align: right;
  word-break: break-word;
}

/*
 * 직원 이름 영역이 좁아져도
 * 재직 상태 칩을 밀어내지 않도록 합니다.
 */
.min-width-0 {
  min-width: 0;
}

.detail-header-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 4px;
  margin-left: auto;
}

/*
 * 상세보기 고정 하단 버튼 영역입니다.
 *
 * 스크롤 영역과 분리되어 항상 하단에 표시됩니다.
 */
.detail-subtitle,
.employee-detail-guide :deep(.v-alert__content) {
  font-size: 0.78rem;
  line-height: 1.45;
}

.detail-actions {
  flex: 0 0 auto;
  padding: 16px 20px;
}

/*
 * 모바일 화면에서는 상세정보를
 * 항목명 → 값 순서의 세로 구조로 표시합니다.
 */
.employee-audit-history {
  margin-top: 16px;
  font-size: 0.8rem;
}

.employee-audit-history summary {
  cursor: pointer;
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-weight: 600;
}

.employee-audit-entry {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding-top: 8px;
  overflow-wrap: anywhere;
}

@media (max-width: 480px) {
  .employee-detail-dialog {
    max-height: calc(100vh - 24px);
  }

  .detail-header,
  .detail-section {
    padding: 16px;
  }

  .employee-detail-grid {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  .detail-value {
    margin-bottom: 12px;
    text-align: left;
  }

  .detail-actions {
    padding: 12px 16px;
  }
}
</style>