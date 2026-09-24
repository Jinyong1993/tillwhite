<template>
  <!--
    직원 카드(EmployeeCard)

    직원 목록에서 직원 한 명의 핵심정보를 표시합니다.

    상세한 개인정보는 이 카드에서 모두 표시하지 않고
    직원 상세보기(EmployeeDetailDialog)에서 확인합니다.
  -->
  <v-card
    class="employee-card"
    variant="outlined"
    rounded="lg"
  >
    <v-card-text class="pa-0">
      <!--
        직원 기본정보

        이름, 사번 / 로그인 ID,
        현재 재직 상태(employment_status)를 표시합니다.
      -->
      <div class="employee-card-header">
        <div class="min-width-0">
          <div class="employee-name">
            {{ displayValue(employee.name) }}
          </div>

          <div class="employee-code">
            {{ displayValue(employee.employee_code) }}
          </div>
        </div>

        <!-- 재직 상태(employment_status) -->
        <v-chip
          class="flex-shrink-0"
          size="small"
          :color="employmentStatusColor(employee.employment_status)"
          variant="tonal"
        >
          {{ employmentStatus(employee.employment_status) }}
        </v-chip>
      </div>

      <!--
        직원 소속정보

        점포, 부서, 직급을 각각 분리해서 표시하여
        기존 한 줄 문자열보다 쉽게 확인할 수 있도록 합니다.
      -->
      <div class="employee-info-area">
        <div class="employee-info-grid">
          <!-- 소속 점포(store) -->
          <div class="employee-info-item">
            <div class="employee-info-label">
              <v-icon
                icon="mdi-store-outline"
                size="16"
              />

              점포
            </div>

            <div class="employee-info-value">
              {{ storeName(employee) }}
            </div>
          </div>

          <!-- 소속 부서(department) -->
          <div class="employee-info-item">
            <div class="employee-info-label">
              <v-icon
                icon="mdi-office-building-outline"
                size="16"
              />

              부서
            </div>

            <div class="employee-info-value">
              {{ department(employee.department) }}
            </div>
          </div>

          <!-- 회사 직급(position) -->
          <div class="employee-info-item">
            <div class="employee-info-label">
              <v-icon
                icon="mdi-badge-account-outline"
                size="16"
              />

              직급
            </div>

            <div class="employee-info-value">
              {{ employee.position?.name ?? '-' }}
            </div>
          </div>
        </div>

        <!--
          시스템 역할(role)

          직급(position)과 시스템 역할(role)은
          서로 다른 정보이므로 별도로 표시합니다.
        -->
        <div class="employee-role">
          <div class="employee-info-label">
            <v-icon
              icon="mdi-shield-account-outline"
              size="16"
            />

            시스템 역할
          </div>

          <div class="employee-role-value">
            {{ employee.role?.name ?? '-' }}
          </div>
        </div>
      </div>

      <v-divider />

      <!--
        상세보기 버튼

        버튼을 누르면 부모 화면(EmployeePage)에
        해당 직원 정보를 전달합니다.

        실제 상세정보는 부모 화면에서 Laravel 서버를
        다시 조회한 뒤 상세보기 창을 엽니다.
      -->
      <div class="employee-card-actions">
        <v-btn
          block
          variant="text"
          prepend-icon="mdi-account-details-outline"
          @click="$emit('detail', employee)"
        >
          상세보기
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
/**
 * 직원 카드(EmployeeCard)
 *
 * 이 컴포넌트는 직원 목록의
 * 한 명의 직원 정보를 표시하는 역할만 담당합니다.
 *
 * Laravel API 호출이나 권한 검사는 하지 않습니다.
 *
 * 상세보기 버튼을 누르면
 * 부모 화면(EmployeePage)에 detail 이벤트를 전달하고,
 * 실제 직원 상세조회 및 권한 확인은
 * EmployeePage와 Laravel 서버에서 처리합니다.
 */

defineProps({
  /**
   * 직원 목록 API에서 받은 직원 정보입니다.
   */
  employee: {
    type: Object,
    required: true,
  },
});

defineEmits([
  'detail',
]);

/**
 * 값이 없는 경우
 * 하이픈(-)을 표시합니다.
 */
function displayValue(value) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  return value;
}

/**
 * 직원의 소속 점포를 표시합니다.
 *
 * 본사(head_office)는 특정 점포에 소속되지 않으므로
 * "본사"라고 표시합니다.
 */
function storeName(employee) {
  if (employee?.store?.name) {
    return employee.store.name;
  }

  if (employee?.department === 'head_office') {
    return '본사';
  }

  return '-';
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
</script>

<style scoped>
/*
 * 직원 카드 전체입니다.
 *
 * 카드 안에서 정보 영역과 버튼 영역을
 * 명확하게 구분합니다.
 */
.employee-card {
  overflow: hidden;
}

/*
 * 직원 이름 / 사번 / 재직 상태 영역입니다.
 */
.employee-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 18px 16px;
}

/* 직원 이름입니다. */
.employee-name {
  overflow: hidden;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* 사번 / 로그인 ID입니다. */
.employee-code {
  margin-top: 3px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.8rem;
  line-height: 1.4;
}

/*
 * 점포 / 부서 / 직급 /
 * 시스템 역할이 표시되는 영역입니다.
 */
.employee-info-area {
  padding: 0 18px 18px;
}

/*
 * 점포 / 부서 / 직급을
 * 3개의 동일한 영역으로 표시합니다.
 */
.employee-info-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

/* 점포 / 부서 / 직급 각각의 영역입니다. */
.employee-info-item {
  min-width: 0;
  padding: 12px;
}

/*
 * 첫 번째 항목을 제외하고
 * 항목 사이에 구분선을 표시합니다.
 */
.employee-info-item + .employee-info-item {
  border-left: 1px solid rgba(
    var(--v-border-color),
    var(--v-border-opacity)
  );
}

/* 점포 / 부서 / 직급 등의 항목명입니다. */
.employee-info-label {
  display: flex;
  align-items: center;
  gap: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.72rem;
  line-height: 1.3;
}

/* 실제 점포 / 부서 / 직급 값입니다. */
.employee-info-value {
  overflow: hidden;
  margin-top: 6px;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/*
 * 시스템 역할(role) 영역입니다.

 * 직급(position)과 혼동하지 않도록
 * 별도 행으로 표시합니다.
 */
.employee-role {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 10px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

/* 실제 시스템 역할 이름입니다. */
.employee-role-value {
  min-width: 0;
  overflow: hidden;
  font-size: 0.82rem;
  font-weight: 600;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/*
 * 상세보기 버튼 영역입니다.
 */
.employee-card-actions {
  padding: 8px;
}

/*
 * 긴 직원 이름이 있어도
 * 오른쪽 재직 상태 칩을 밀어내지 않도록 합니다.
 */
.min-width-0 {
  min-width: 0;
}

/*
 * 모바일에서는 점포 / 부서 / 직급을
 * 한 줄씩 세로로 표시합니다.
 */
@media (max-width: 480px) {
  .employee-card-header {
    padding: 16px;
  }

  .employee-info-area {
    padding: 0 16px 16px;
  }

  .employee-info-grid {
    grid-template-columns: 1fr;
  }

  .employee-info-item + .employee-info-item {
    border-top: 1px solid rgba(
      var(--v-border-color),
      var(--v-border-opacity)
    );
    border-left: 0;
  }

  .employee-info-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 12px;
  }

  .employee-info-value {
    margin-top: 0;
    text-align: right;
  }
}
</style>