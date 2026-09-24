<template>
  <AppShell :title="pageTitle">
    <template #default="{ can, setError }">
      <!--
        시스템 관리 화면의 탭입니다.

        설정      : 시스템 공통 설정 조회 및 저장
        역할·권한 : 시스템 역할별 권한 조회
        직급      : 회사 직급 목록 조회
      -->
      <v-tabs
        v-model="tab"
        grow
        density="compact"
      >
        <v-tab value="settings">
          설정
        </v-tab>

        <v-tab value="roles">
          역할·권한
        </v-tab>

        <v-tab value="positions">
          직급
        </v-tab>
      </v-tabs>

      <v-window
        v-model="tab"
        class="mt-4"
      >
        <!-- 시스템 설정 탭 -->
        <v-window-item value="settings">
          <!-- 등록된 시스템 설정 목록 -->
          <v-list>
            <v-list-item
              v-for="systemSetting in data.settings"
              :key="systemSetting.id"
              :title="systemSetting.key"
              :subtitle="`${systemSetting.value} · ${systemSetting.description ?? ''}`"
            />
          </v-list>

          <!--
            system.manage 권한이 있는 사용자에게만
            시스템 설정 저장 Form을 표시합니다.
          -->
          <v-form
            v-if="can('system.manage')"
            class="mt-3"
            @submit.prevent="saveSetting(setError)"
          >
            <!-- 시스템 설정을 구분하는 고유 키 -->
            <v-text-field
              v-model="setting.key"
              label="설정 키"
              variant="outlined"
            />

            <!-- 설정에 저장할 값 -->
            <v-text-field
              v-model="setting.value"
              label="값"
              variant="outlined"
            />

            <!-- 설정값의 데이터 타입 -->
            <v-select
              v-model="setting.type"
              :items="[
                'string',
                'integer',
                'boolean',
                'json',
              ]"
              label="타입"
              variant="outlined"
            />

            <!-- 설정에 대한 설명 -->
            <v-text-field
              v-model="setting.description"
              label="설명"
              variant="outlined"
            />

            <!-- 시스템 설정 저장 -->
            <v-btn
              type="submit"
              block
            >
              설정 저장
            </v-btn>
          </v-form>
        </v-window-item>

        <!-- 역할·권한 탭 -->
        <v-window-item value="roles">
          <v-expansion-panels>
            <!-- 시스템에 등록된 역할 목록 -->
            <v-expansion-panel
              v-for="role in data.roles"
              :key="role.id"
            >
              <!-- 역할명 및 역할 코드 -->
              <v-expansion-panel-title>
                {{ role.name }} ({{ role.code }})
              </v-expansion-panel-title>

              <!-- 해당 역할에 부여된 권한 목록 -->
              <v-expansion-panel-text>
                <v-chip
                  v-for="permission in role.permissions"
                  :key="permission.id"
                  size="small"
                  class="ma-1"
                >
                  {{ permission.code }}
                </v-chip>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-window-item>

        <!-- 직급 탭 -->
        <v-window-item value="positions">
          <!-- 시스템에 등록된 회사 직급 목록 -->
          <v-list>
            <v-list-item
              v-for="position in data.positions"
              :key="position.id"
              :title="position.name"
              :subtitle="`${position.code} · 순서 ${position.sort_order}`"
            />
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
const pageTitle = '시스템';

// 현재 선택된 시스템 관리 탭
const tab = ref('settings');

/**
 * 시스템 관리 화면에서 사용하는 서버 데이터입니다.
 *
 * settings    : 시스템 공통 설정 목록
 * roles       : 시스템 역할 목록
 * permissions : 시스템 권한 목록
 * positions   : 회사 직급 목록
 */
const data = ref({
  settings: [],
  roles: [],
  permissions: [],
  positions: [],
});

/**
 * 시스템 설정 저장 Form입니다.
 *
 * key         : 설정을 구분하는 고유 키
 * value       : 설정값
 * type        : 설정값의 데이터 타입
 * description : 설정에 대한 설명
 */
const setting = ref({
  key: '',
  value: '',
  type: 'string',
  description: '',
});

/**
 * 시스템 관리 화면에 필요한 데이터를 서버에서 조회합니다.
 *
 * 시스템 설정, 역할, 권한, 직급 정보를 한 번에 받아
 * data에 저장합니다.
 */
async function load() {
  const response = await window.axios.get(
    '/tillwhite/api/system',
  );

  data.value = response.data;
}

/**
 * 시스템 설정을 서버에 저장합니다.
 *
 * 저장에 성공하면 시스템 관리 데이터를 다시 조회하여
 * 변경된 설정값을 화면에 반영합니다.
 *
 * 실패하면 AppShell에서 전달받은 오류 처리 함수를 통해
 * 오류 메시지를 표시합니다.
 */
async function saveSetting(setError) {
  try {
    await window.axios.post(
      '/tillwhite/api/system/settings',
      setting.value,
    );

    // 변경된 설정을 반영하기 위해 데이터 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '설정 저장 실패',
    );
  }
}

// 화면이 처음 열릴 때 시스템 관리 데이터 조회
onMounted(load);
</script>