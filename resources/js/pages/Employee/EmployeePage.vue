<template>
  <AppShell :title="pageTitle">
    <template #default="{ can, setError }">
      <!--
        employee.manage 권한이 있는 사용자에게만
        직원 등록 버튼을 표시합니다.
      -->
      <v-btn
        v-if="can('employee.manage')"
        block
        class="mb-3"
        prepend-icon="mdi-account-plus-outline"
        @click="dialog = true"
      >
        직원 등록
      </v-btn>

      <!-- 직원 목록 -->
      <v-list lines="three">
        <v-list-item
          v-for="employee in data.employees"
          :key="employee.id"
          :title="`${employee.name} (${employee.employee_code})`"
          :subtitle="`${employee.store?.name ?? '본사'} · ${department(employee.department)} · ${employee.position?.name ?? '-'} · ${employee.role?.name}`"
        >
          <!-- 재직 상태 표시 -->
          <template #append>
            <v-chip
              size="x-small"
              :variant="employee.employment_status === 'active' ? 'flat' : 'outlined'"
            >
              {{ employee.employment_status }}
            </v-chip>
          </template>
        </v-list-item>
      </v-list>

      <!-- 직원 등록 Dialog -->
      <v-dialog
        v-model="dialog"
        max-width="400"
      >
        <v-card title="직원 등록">
          <v-card-text>
            <!-- 사번은 로그인 ID로도 사용 -->
            <v-text-field
              v-model="form.employee_code"
              label="사번/로그인 ID"
              variant="outlined"
            />

            <!-- 직원 이름 -->
            <v-text-field
              v-model="form.name"
              label="이름"
              variant="outlined"
            />

            <!-- 최초 로그인에 사용할 초기 비밀번호 -->
            <v-text-field
              v-model="form.password"
              label="초기 비밀번호"
              type="password"
              variant="outlined"
            />

            <!-- 소속 부서 -->
            <v-select
              v-model="form.department"
              :items="departments"
              label="부서"
              variant="outlined"
            />

            <!--
              본사 직원은 특정 점포에 소속되지 않으므로
              주방 또는 홀 직원일 때만 점포를 선택합니다.
            -->
            <v-select
              v-if="form.department !== 'head_office'"
              v-model="form.store_id"
              :items="data.stores"
              item-title="name"
              item-value="id"
              label="점포"
              variant="outlined"
            />

            <!-- 회사 직급 -->
            <v-select
              v-model="form.position_id"
              :items="data.positions"
              item-title="name"
              item-value="id"
              label="직급"
              variant="outlined"
            />

            <!-- 시스템에서 사용할 권한 역할 -->
            <v-select
              v-model="form.role_id"
              :items="data.roles"
              item-title="name"
              item-value="id"
              label="시스템 역할"
              variant="outlined"
            />
          </v-card-text>

          <v-card-actions>
            <v-spacer />

            <!-- 직원 등록 취소 -->
            <v-btn @click="dialog = false">
              취소
            </v-btn>

            <!-- 직원 정보 저장 -->
            <v-btn @click="save(setError)">
              저장
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>
  </AppShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppShell from '../../components/layout/AppShell.vue';

// 현재 페이지 제목
const pageTitle = '직원 관리';

// 직원 등록 Dialog 열림/닫힘 상태
const dialog = ref(false);

/**
 * 직원 관리 화면에서 사용하는 서버 데이터입니다.
 *
 * employees : 직원 목록
 * stores    : 점포 목록
 * positions : 직급 목록
 * roles     : 시스템 역할 목록
 */
const data = ref({
  employees: [],
  stores: [],
  positions: [],
  roles: [],
});

/**
 * 신규 직원 등록 Form입니다.
 *
 * employee_code : 사번 및 로그인 ID
 * name          : 직원 이름
 * password      : 초기 비밀번호
 * store_id      : 소속 점포
 * department    : 소속 부서
 * position_id   : 회사 직급
 * role_id       : 시스템 역할
 */
const form = ref({
  employee_code: '',
  name: '',
  password: '',
  store_id: null,
  department: 'kitchen',
  position_id: null,
  role_id: null,
});

/**
 * 직원이 소속될 수 있는 부서 목록입니다.
 *
 * title : 화면에 표시할 부서명
 * value : DB 및 API에서 사용하는 부서 코드
 */
const departments = [
  {
    title: '주방',
    value: 'kitchen',
  },
  {
    title: '홀',
    value: 'hall',
  },
  {
    title: '본사',
    value: 'head_office',
  },
];

/**
 * DB에 저장된 부서 코드를
 * 화면에 표시할 한글 부서명으로 변환합니다.
 *
 * 등록되지 않은 코드가 들어오면 원래 값을 그대로 반환합니다.
 */
const department = (value) => {
  return departments.find((item) => item.value === value)?.title ?? value;
};

/**
 * 직원 관리 화면에 필요한 데이터를 서버에서 조회합니다.
 *
 * 직원, 점포, 직급, 역할 목록을 한 번에 받아
 * data에 저장합니다.
 */
async function load() {
  const response = await window.axios.get('/tillwhite/api/employees');

  data.value = response.data;
}

/**
 * 신규 직원을 서버에 등록합니다.
 *
 * 저장에 성공하면 Dialog를 닫고
 * 직원 목록을 다시 불러와 최신 상태로 갱신합니다.
 *
 * 실패하면 AppShell에서 전달받은 오류 처리 함수를 통해
 * 화면에 오류 메시지를 표시합니다.
 */
async function save(setError) {
  try {
    await window.axios.post(
      '/tillwhite/api/employees',
      form.value,
    );

    // 직원 등록 Dialog 닫기
    dialog.value = false;

    // 등록된 직원을 반영하기 위해 목록 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '직원 저장 실패',
    );
  }
}

// 화면이 처음 열릴 때 직원 관리 데이터 조회
onMounted(load);
</script>