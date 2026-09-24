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

            <div class="text-body-2 text-medium-emphasis mt-1">
              {{ displayValue(employee.employee_code) }}
            </div>
          </div>

          <v-chip
            size="small"
            :color="employmentStatusColor(employee.employment_status)"
            variant="tonal"
          >
            {{ employmentStatus(employee.employment_status) }}
          </v-chip>
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
              마지막 로그인
            </div>

            <div class="detail-value">
              {{ formatDateTime(employee.last_login_at) }}
            </div>

            <div class="detail-label">
              마지막 비밀번호 변경
            </div>

            <div class="detail-value">
              {{ formatDateTime(employee.password_changed_at) }}
            </div>

            <div class="detail-label">
              계정 생성
            </div>

            <div class="detail-value">
              {{ formatDateTime(employee.created_at) }}
            </div>

            <div class="detail-label">
              마지막 정보 수정
            </div>

            <div class="detail-value">
              {{ formatDateTime(employee.updated_at) }}
            </div>
          </div>
        </section>

        <v-divider />

        <!--
          재직 상태 관리

          직원 관리 권한(employee.manage)이 있는 경우에만
          재직 상태를 변경할 수 있습니다.

          실제 권한 검사는 Laravel 서버에서 다시 수행합니다.
        -->
        <section class="detail-section">
          <div class="d-flex align-center justify-space-between ga-3 mb-4">
            <div>
              <div class="detail-section-title mb-0">
                <v-icon
                  icon="mdi-account-cog-outline"
                  size="small"
                />

                재직 상태 관리
              </div>

              <div class="text-caption text-medium-emphasis mt-2">
                재직, 휴직, 퇴사 상태를 변경합니다.
              </div>
            </div>

            <v-chip
              v-if="!canChangeStatus"
              size="x-small"
              variant="tonal"
            >
              변경 권한 없음
            </v-chip>
          </div>

          <!--
            재직 상태(employment_status)

            계정 활성 상태(is_active)는 직접 변경하지 않습니다.

            재직(active) → 계정 활성
            휴직(leave) → 계정 비활성
            퇴사(resigned) → 계정 비활성
          -->
          <v-select
            :model-value="employmentStatusValue"
            :items="employmentStatuses"
            label="재직 상태"
            variant="outlined"
            hide-details
            :disabled="!canChangeStatus"
            @update:model-value="updateEmploymentStatus"
          />

          <v-alert
            v-if="!canChangeStatus && statusUnavailableMessage"
            class="mt-4"
            type="info"
            variant="tonal"
            density="compact"
          >
            {{ statusUnavailableMessage }}
          </v-alert>
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

        <v-btn
          variant="flat"
          prepend-icon="mdi-content-save-outline"
          :disabled="!canChangeStatus"
          @click="$emit('save')"
        >
          상태 저장
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
/**
 * 직원 상세보기 다이얼로그(EmployeeDetailDialog)
 *
 * 이 컴포넌트는 직원 상세정보 표시와
 * 재직 상태 변경 화면만 담당합니다.
 *
 * 직원 상세조회 API와 상태 저장 API는
 * 직원 관리 화면(EmployeePage)에서 담당합니다.
 *
 * 즉:
 *
 * EmployeePage
 * - Laravel API 호출
 * - 최신 직원 정보 조회
 * - 상태 저장
 *
 * EmployeeDetailDialog
 * - 직원 상세정보 표시
 * - 재직 상태 선택
 * - 닫기 / 저장 이벤트 전달
 */

const props = defineProps({
  /**
   * 다이얼로그 열림/닫힘 상태입니다.
   */
  modelValue: {
    type: Boolean,
    default: false,
  },

  /**
   * Laravel 서버에서 다시 조회한
   * 최신 직원 정보입니다.
   */
  employee: {
    type: Object,
    default: null,
  },

  /**
   * 현재 선택된 재직 상태(employment_status)입니다.
   */
  employmentStatusValue: {
    type: String,
    default: 'active',
  },

  /**
   * 현재 사용자가 해당 직원의
   * 재직 상태를 변경할 수 있는지 나타냅니다.
   *
   * 화면 표시용 권한이며
   * 실제 보안 검사는 Laravel에서 다시 수행합니다.
   */
  canChangeStatus: {
    type: Boolean,
    default: false,
  },

  /**
   * 재직 상태를 변경할 수 없는 경우
   * 화면에 표시할 안내 메시지입니다.
   */
  statusUnavailableMessage: {
    type: String,
    default: '',
  },
});

const emit = defineEmits([
  'update:modelValue',
  'update:employmentStatusValue',
  'save',
  'close',
]);

/**
 * 직원의 재직 상태(employment_status) 목록입니다.
 */
const employmentStatuses = [
  {
    title: '재직',
    value: 'active',
  },
  {
    title: '휴직',
    value: 'leave',
  },
  {
    title: '퇴사',
    value: 'resigned',
  },
];

/**
 * 다이얼로그 상태가 변경되었을 때
 * 부모 화면(EmployeePage)에 전달합니다.
 */
function handleDialogChange(value) {
  emit('update:modelValue', value);

  if (!value) {
    emit('close');
  }
}

/**
 * 닫기 버튼을 눌렀을 때
 * 부모 화면(EmployeePage)에 닫기 요청을 전달합니다.
 */
function close() {
  emit('update:modelValue', false);
  emit('close');
}

/**
 * 재직 상태 선택값을
 * 부모 화면(EmployeePage)에 전달합니다.
 */
function updateEmploymentStatus(value) {
  emit('update:employmentStatusValue', value);
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
 * 시스템 정보 / 재직 상태 관리 영역입니다.
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

/*
 * 닫기 / 상태 저장 버튼 영역입니다.
 *
 * 스크롤 영역과 분리되어 항상 하단에 표시됩니다.
 */
.detail-actions {
  flex: 0 0 auto;
  padding: 16px 20px;
}

/*
 * 모바일 화면에서는 상세정보를
 * 항목명 → 값 순서의 세로 구조로 표시합니다.
 */
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