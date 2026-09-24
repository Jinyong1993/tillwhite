<template>
  <AppShell :title="pageTitle">
    <template #default="{ can, setError }">
      <!--
        store.manage 권한이 있는 사용자에게만
        점포 등록 버튼을 표시합니다.
      -->
      <v-btn
        v-if="can('store.manage')"
        block
        class="mb-3"
        prepend-icon="mdi-store-plus-outline"
        @click="dialog = true"
      >
        점포 등록
      </v-btn>

      <!-- 점포 목록 -->
      <v-list lines="three">
        <v-list-item
          v-for="store in stores"
          :key="store.id"
          :title="store.name"
          :subtitle="`${store.store_code} · ${store.status} · 출퇴근 반경 ${store.attendance_radius_meters}m`"
        >
          <!-- 점포 아이콘 -->
          <template #prepend>
            <v-icon icon="mdi-store-outline" />
          </template>
        </v-list-item>
      </v-list>

      <!-- 점포 등록 Dialog -->
      <v-dialog
        v-model="dialog"
        max-width="400"
      >
        <v-card title="점포 등록">
          <v-card-text>
            <!--
              점포를 구분하기 위한 고유 코드입니다.
              DB의 store_code 값으로 저장됩니다.
            -->
            <v-text-field
              v-model="form.store_code"
              label="점포 코드"
              variant="outlined"
            />

            <!-- 점포명 -->
            <v-text-field
              v-model="form.name"
              label="점포명"
              variant="outlined"
            />

            <!-- 점포 전화번호 -->
            <v-text-field
              v-model="form.phone"
              label="전화번호"
              variant="outlined"
            />

            <!-- 점포 주소 -->
            <v-text-field
              v-model="form.address"
              label="주소"
              variant="outlined"
            />

            <!--
              GPS 출퇴근 기능에서 사용할 허용 반경입니다.
              점포 위치를 기준으로 몇 m 이내에서
              출퇴근을 허용할지 설정합니다.
            -->
            <v-number-input
              v-model="form.attendance_radius_meters"
              label="출퇴근 허용 반경(m)"
              :min="10"
              variant="outlined"
            />

            <!-- 점포 운영 상태 -->
            <v-select
              v-model="form.status"
              :items="[
                'active',
                'inactive',
                'closed',
              ]"
              label="상태"
              variant="outlined"
            />
          </v-card-text>

          <v-card-actions>
            <v-spacer />

            <!-- 점포 등록 취소 -->
            <v-btn @click="dialog = false">
              취소
            </v-btn>

            <!-- 점포 저장 -->
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
const pageTitle = '점포 관리';

// 서버에서 조회한 점포 목록
const stores = ref([]);

// 점포 등록 Dialog 열림/닫힘 상태
const dialog = ref(false);

/**
 * 신규 점포 등록 Form입니다.
 *
 * store_code                : 점포 고유 코드
 * name                      : 점포명
 * phone                     : 점포 전화번호
 * address                   : 점포 주소
 * attendance_radius_meters  : 출퇴근 허용 반경(m)
 * status                    : 점포 운영 상태
 */
const form = ref({
  store_code: '',
  name: '',
  phone: '',
  address: '',
  attendance_radius_meters: 100,
  status: 'active',
});

/**
 * 점포 목록을 서버에서 조회합니다.
 *
 * 조회된 점포 데이터를 stores에 저장하여
 * 점포 관리 화면의 목록에 표시합니다.
 */
async function load() {
  const response = await window.axios.get(
    '/tillwhite/api/stores',
  );

  stores.value = response.data.stores;
}

/**
 * 신규 점포를 서버에 등록합니다.
 *
 * 저장에 성공하면 Dialog를 닫고
 * 점포 목록을 다시 조회하여 최신 상태를 반영합니다.
 *
 * 저장에 실패하면 AppShell에서 전달받은
 * 오류 처리 함수를 통해 메시지를 표시합니다.
 */
async function save(setError) {
  try {
    await window.axios.post(
      '/tillwhite/api/stores',
      form.value,
    );

    // 점포 등록 Dialog 닫기
    dialog.value = false;

    // 등록된 점포를 반영하기 위해 목록 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '점포 저장 실패',
    );
  }
}

// 화면이 처음 열릴 때 점포 목록 조회
onMounted(load);
</script>