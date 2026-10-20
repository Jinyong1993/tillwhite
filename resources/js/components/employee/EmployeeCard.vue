<template>
  <!-- 직원 카드 -->
  <v-card
    class="app-card"
    :class="{ 'opacity-50': employee.deleted_at }"
    variant="outlined"
    rounded="lg"
    style="border-color: rgba(var(--v-theme-on-surface), 0.12)"
  >
    <v-card-text class="pa-0">
      <!-- 직원명 / 사번 / 재직 상태 -->
      <div
        class="d-flex align-start justify-space-between ga-2 pa-3"
      >
        <div
          class="app-card-content d-flex flex-wrap align-center ga-2 flex-grow-1"
        >
          <div class="text-subtitle-1 font-weight-bold">
            {{ displayValue(employee.name) }}
          </div>

          <div class="text-body-2 text-medium-emphasis">
            {{ displayValue(employee.employee_code) }}
          </div>
        </div>

        <!-- 재직 상태 -->
        <div class="d-flex align-center flex-shrink-0">
          <!-- 삭제된 직원 -->
          <v-chip
            v-if="employee.deleted_at"
            size="small"
            prepend-icon="mdi-delete-clock-outline"
            variant="tonal"
          >
            삭제됨
          </v-chip>

          <!-- 재직 상태 변경 메뉴 -->
          <v-menu
            v-else-if="canManage"
            location="bottom end"
          >
            <template #activator="{ props: menuProps }">
              <v-chip
                v-bind="menuProps"
                class="cursor-pointer"
                size="small"
                :color="
                  employmentStatusColor(employee.employment_status)
                "
                variant="tonal"
                append-icon="mdi-chevron-down"
                :disabled="loading"
              >
                {{ employmentStatus(employee.employment_status) }}
              </v-chip>
            </template>

            <v-list
              density="compact"
              min-width="140"
            >
              <v-list-item
                v-for="status in statusOptions"
                :key="status.value"
                :title="status.title"
                :prepend-icon="status.icon"
                :disabled="
                  employee.employment_status === status.value ||
                  loading
                "
                @click="$emit('status-change', status.value)"
              />
            </v-list>
          </v-menu>

          <!-- 조회 권한만 있는 경우 -->
          <v-chip
            v-else
            size="small"
            :color="
              employmentStatusColor(employee.employment_status)
            "
            variant="tonal"
          >
            {{ employmentStatus(employee.employment_status) }}
          </v-chip>
        </div>
      </div>

      <!-- 직원 정보 -->
      <div class="px-3 pb-3">
        <!-- 부서 / 직급 / 점포 -->
        <div
          class="rounded-lg overflow-hidden"
          style="border: 1px solid rgba(var(--v-theme-on-surface), 0.12)"
        >
          <v-row no-gutters>
            <!-- 부서 -->
            <v-col
              cols="6"
              class="pa-3"
              style="border-right: 1px solid rgba(var(--v-theme-on-surface), 0.1)"
            >
              <div class="app-card-content">
                <div
                  class="d-flex align-center ga-1 text-caption text-medium-emphasis"
                >
                  <v-icon
                    icon="mdi-office-building-outline"
                    size="16"
                  />
                  부서
                </div>

                <div class="text-body-2 font-weight-medium mt-2">
                  {{ department(employee.department) }}
                </div>
              </div>
            </v-col>

            <!-- 직급 -->
            <v-col
              cols="6"
              class="pa-3"
            >
              <div class="app-card-content">
                <div
                  class="d-flex align-center ga-1 text-caption text-medium-emphasis"
                >
                  <v-icon
                    icon="mdi-badge-account-outline"
                    size="16"
                  />
                  직급
                </div>

                <div class="text-body-2 font-weight-medium mt-2">
                  {{ employee.position?.name ?? '-' }}
                </div>
              </div>
            </v-col>

            <!-- 점포 -->
            <v-col
              cols="12"
              class="pa-3"
              style="border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1)"
            >
              <div
                class="d-flex flex-wrap align-center justify-space-between ga-2"
              >
                <div
                  class="d-flex align-center ga-1 text-caption text-medium-emphasis"
                >
                  <v-icon
                    icon="mdi-store-outline"
                    size="16"
                  />
                  점포
                </div>

                <div
                  class="app-card-content text-body-2 font-weight-medium text-right"
                >
                  {{ storeName(employee) }}
                </div>
              </div>
            </v-col>
          </v-row>
        </div>

        <!-- 시스템 역할 -->
        <div
          class="d-flex flex-wrap align-center justify-space-between ga-2 rounded-lg pa-3 mt-2"
          style="border: 1px solid rgba(var(--v-theme-on-surface), 0.12)"
        >
          <div
            class="d-flex align-center ga-1 text-caption text-medium-emphasis"
          >
            <v-icon
              icon="mdi-shield-account-outline"
              size="16"
            />
            시스템 역할
          </div>

          <div
            class="app-card-content text-body-2 font-weight-medium text-right"
          >
            {{ employee.role?.name ?? '-' }}
          </div>
        </div>
      </div>

      <!-- 상세보기 구분선 -->
      <v-divider style="opacity: 0.12" />

      <!-- 상세보기 -->
      <div class="d-flex justify-center pa-3">
        <v-btn
          class="app-btn"
          block
          variant="tonal"
          @click="$emit('detail', employee)"
        >
          상세보기
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
defineProps({
  employee: {
    type: Object,
    required: true,
  },

  canManage: {
    type: Boolean,
    default: false,
  },

  loading: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'detail',
  'status-change',
]);

// 재직 상태 변경 메뉴
const statusOptions = [
  {
    title: '재직',
    value: 'active',
    icon: 'mdi-check-circle-outline',
  },
  {
    title: '휴직',
    value: 'leave',
    icon: 'mdi-pause-circle-outline',
  },
  {
    title: '퇴사',
    value: 'resigned',
    icon: 'mdi-account-off-outline',
  },
];

// 빈 값 표시
function displayValue(value) {
  if (
    value === null ||
    value === undefined ||
    value === ''
  ) {
    return '-';
  }

  return value;
}

// 소속 점포
function storeName(employee) {
  if (employee?.store?.name) {
    return employee.store.name;
  }

  if (employee?.department === 'head_office') {
    return '본사';
  }

  return '-';
}

// 부서 표시
function department(value) {
  const departments = {
    kitchen: '주방',
    hall: '홀',
    head_office: '본사',
  };

  return departments[value] ?? value ?? '-';
}

// 재직 상태 표시
function employmentStatus(value) {
  const statuses = {
    active: '재직',
    leave: '휴직',
    resigned: '퇴사',
  };

  return statuses[value] ?? value ?? '-';
}

// 재직 상태 색상
function employmentStatusColor(value) {
  const colors = {
    active: 'success',
    leave: 'warning',
    resigned: 'error',
  };

  return colors[value] ?? undefined;
}
</script>