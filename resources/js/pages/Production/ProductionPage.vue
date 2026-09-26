<template>
  <!--
    Till White 생산·폐기 관리 화면

    생산·폐기·로스 기록의 입력, 조회, 통계를 관리합니다.

    페이지 최초 데이터 조회의 전체 화면 로딩은
    애플리케이션 공통 로딩(useAppLoading)에서 관리합니다.
  -->
  <AppShell :title="pageTitle">
    <template #default="{ can, setError }">
      <!--
        생산·폐기 관리 화면의 탭입니다.

        입력 : 생산/폐기/로스 기록 등록
        조회 : 등록된 기록 조회 및 삭제
        통계 : 현재 조회된 기록의 합계 확인
      -->
      <v-tabs
        v-model="tab"
        grow
        density="compact"
      >
        <v-tab value="input">
          입력
        </v-tab>

        <v-tab value="list">
          조회
        </v-tab>

        <v-tab value="stats">
          통계
        </v-tab>
      </v-tabs>

      <v-window
        v-model="tab"
        class="mt-4"
      >
        <!-- 생산·폐기·로스 입력 탭 -->
        <v-window-item value="input">
          <v-form @submit.prevent="save(setError)">
            <!-- 작업 기준 날짜 -->
            <v-text-field
              v-model="form.work_date"
              type="date"
              label="작업일"
              variant="outlined"
            />

            <!-- 생산/폐기/로스를 기록할 제품 -->
            <v-select
              v-model="form.product_id"
              :items="products"
              item-title="name"
              item-value="id"
              label="제품"
              variant="outlined"
            />

            <!-- 해당 작업을 수행한 직원 -->
            <v-select
              v-model="form.worker_id"
              :items="workers"
              item-title="name"
              item-value="id"
              label="작업자"
              variant="outlined"
            />

            <!-- 생산, 폐기, 로스 수량 입력 -->
            <div class="d-flex ga-2">
              <v-number-input
                v-model="form.production_quantity"
                label="생산"
                variant="outlined"
                :min="0"
              />

              <v-number-input
                v-model="form.waste_quantity"
                label="폐기"
                variant="outlined"
                :min="0"
              />

              <v-number-input
                v-model="form.loss_quantity"
                label="로스"
                variant="outlined"
                :min="0"
              />
            </div>

            <!-- 폐기 수량이 있을 경우에만 폐기 사유 표시 -->
            <v-text-field
              v-if="form.waste_quantity > 0"
              v-model="form.waste_reason"
              label="폐기 사유"
              variant="outlined"
            />

            <!-- 로스 수량이 있을 경우에만 로스 사유 표시 -->
            <v-text-field
              v-if="form.loss_quantity > 0"
              v-model="form.loss_reason"
              label="로스 사유"
              variant="outlined"
            />

            <!-- 생산 기록에 대한 추가 메모 -->
            <v-textarea
              v-model="form.note"
              label="메모"
              variant="outlined"
              rows="2"
            />

            <!--
              생산 기록 등록 권한(production.create)이 있는 사용자에게만
              기록 저장 버튼을 표시합니다.
            -->
            <v-btn
              v-if="can('production.create')"
              type="submit"
              block
              :loading="saving"
            >
              기록 저장
            </v-btn>
          </v-form>
        </v-window-item>

        <!-- 생산·폐기·로스 조회 탭 -->
        <v-window-item value="list">
          <!--
            특정 작업일의 기록만 조회하기 위한 날짜 필터입니다.

            날짜를 변경하거나 초기화하면
            서버에서 목록을 다시 조회합니다.

            최초 페이지 로딩이 아닌 일반 재조회이므로
            공통 전체 화면 로딩은 표시하지 않습니다.
          -->
          <v-text-field
            v-model="filterDate"
            type="date"
            label="날짜 필터"
            variant="outlined"
            clearable
            @update:model-value="load"
          />

          <!-- 생산 기록 목록 -->
          <v-list lines="three">
            <v-list-item
              v-for="record in records"
              :key="record.id"
            >
              <!-- 제품명 및 작업자 -->
              <v-list-item-title>
                {{ record.product?.name }} · {{ record.worker?.name }}
              </v-list-item-title>

              <!-- 작업일 및 생산/폐기/로스 수량 -->
              <v-list-item-subtitle>
                {{ record.work_date }}
                |
                생산 {{ record.production_quantity }}
                /
                폐기 {{ record.waste_quantity }}
                /
                로스 {{ record.loss_quantity }}
              </v-list-item-subtitle>

              <!--
                생산 기록 삭제 권한(production.delete)이 있는 사용자에게만
                기록 삭제 버튼을 표시합니다.
              -->
              <template #append>
                <v-btn
                  v-if="can('production.delete')"
                  icon="mdi-delete-outline"
                  size="small"
                  variant="text"
                  @click="remove(record, setError)"
                />
              </template>
            </v-list-item>
          </v-list>

          <!-- 조회된 생산 기록이 없는 경우 표시 -->
          <v-empty-state
            v-if="!records.length"
            title="기록이 없습니다."
            icon="mdi-clipboard-text-outline"
          />
        </v-window-item>

        <!-- 생산·폐기·로스 통계 탭 -->
        <v-window-item value="stats">
          <v-card variant="outlined">
            <v-card-text>
              <div class="text-caption">
                조회 기록 합계
              </div>

              <!-- 생산 수량 합계 -->
              <div class="text-h5 font-weight-bold">
                생산 {{ totals.production }}개
              </div>

              <!-- 폐기 및 로스 수량 합계 -->
              <div class="text-body-2 mt-2">
                폐기 {{ totals.waste }}개 · 로스 {{ totals.loss }}개
              </div>
            </v-card-text>
          </v-card>
        </v-window-item>
      </v-window>
    </template>
  </AppShell>
</template>

<script setup>
import {
  computed,
  onMounted,
  ref,
} from 'vue';

import AppShell from '../../components/layout/AppShell.vue';

import { useAppLoading } from '../../composables/useAppLoading';

/**
 * Till White 애플리케이션 공통 전체 화면 로딩입니다.
 *
 * 메뉴를 통해 생산·폐기 관리 화면으로 이동할 때
 * 이미 시작된 공통 로딩을
 * 최초 생산 데이터 조회가 완료된 뒤 종료합니다.
 *
 * 최소 1초 표시 시간은
 * 공통 로딩(useAppLoading)에서 관리합니다.
 */
const {
  completePageLoading,
} = useAppLoading();

// 현재 페이지 제목
const pageTitle = '생산·폐기 관리';

// 현재 선택된 탭
const tab = ref('input');

// 서버에서 조회한 생산·폐기·로스 기록
const records = ref([]);

// 생산 기록에 사용할 수 있는 제품 목록
const products = ref([]);

// 작업자로 선택할 수 있는 직원 목록
const workers = ref([]);

// 생산 기록 저장 처리 중 여부
const saving = ref(false);

// 조회 탭에서 사용하는 작업일 필터
const filterDate = ref('');

/**
 * 생산·폐기·로스 기록 입력 Form입니다.
 *
 * work_date           : 작업일
 * product_id          : 제품 ID
 * worker_id           : 작업자 ID
 * production_quantity : 생산 수량
 * waste_quantity      : 폐기 수량
 * loss_quantity       : 로스 수량
 * waste_reason        : 폐기 사유
 * loss_reason         : 로스 사유
 * note                : 추가 메모
 */
const form = ref({
  work_date: new Date().toISOString().slice(0, 10),
  product_id: null,
  worker_id: null,
  production_quantity: 0,
  waste_quantity: 0,
  loss_quantity: 0,
  waste_reason: '',
  loss_reason: '',
  note: '',
});

/**
 * 현재 생산 기록(records)을 기준으로
 * 생산, 폐기, 로스 수량의 합계를 계산합니다.
 *
 * 조회 탭에서 날짜 필터를 적용하면 records가 변경되므로
 * 통계 탭의 합계도 자동으로 다시 계산됩니다.
 */
const totals = computed(() => {
  return records.value.reduce(
    (total, record) => {
      return {
        production:
          total.production + record.production_quantity,
        waste:
          total.waste + record.waste_quantity,
        loss:
          total.loss + record.loss_quantity,
      };
    },
    {
      production: 0,
      waste: 0,
      loss: 0,
    },
  );
});

/**
 * 생산·폐기 관리 화면에 필요한 데이터를 조회합니다.
 *
 * 생산 기록과 입력용 선택 항목을 동시에 요청하여
 * records, products, workers에 각각 저장합니다.
 *
 * 날짜 필터(filterDate)가 지정되어 있으면
 * 해당 날짜의 기록만 요청하고,
 * 지정되어 있지 않으면 date 파라미터를 전달하지 않습니다.
 *
 * 이 함수는 최초 조회뿐만 아니라
 * 저장, 삭제, 날짜 필터 변경 후 재조회에도 사용하므로
 * 공통 전체 화면 로딩을 직접 제어하지 않습니다.
 */
async function load() {
  const [
    recordsResponse,
    optionsResponse,
  ] = await Promise.all([
    window.axios.get(
      '/tillwhite/api/production',
      {
        params: {
          date: filterDate.value || undefined,
        },
      },
    ),
    window.axios.get(
      '/tillwhite/api/production/options',
    ),
  ]);

  records.value = recordsResponse.data.records;
  products.value = optionsResponse.data.products;
  workers.value = optionsResponse.data.workers;
}

/**
 * 생산·폐기 관리 화면의 최초 데이터를 불러옵니다.
 *
 * 생산·폐기 관리 페이지에 처음 진입했을 때만 사용합니다.
 *
 * 처리 순서:
 *
 * 1. 생산 기록을 조회합니다.
 * 2. 입력에 필요한 제품 목록을 조회합니다.
 * 3. 입력에 필요한 작업자 목록을 조회합니다.
 * 4. 조회한 데이터를 화면 상태에 저장합니다.
 * 5. 성공 또는 실패와 관계없이 공통 페이지 로딩 완료를 알립니다.
 *
 * completePageLoading()은
 * 화면 이동 시작 시점부터 최소 1초가 지났는지 확인한 뒤
 * App.vue의 공통 전체 화면 로딩을 종료합니다.
 *
 * API 요청이 1초 이상 걸렸다면
 * 추가 대기 없이 요청 완료 후 로딩을 종료합니다.
 */
async function initialLoad() {
  try {
    await load();
  } finally {
    await completePageLoading();
  }
}

/**
 * 새로운 생산·폐기·로스 기록을 저장합니다.
 *
 * 저장 중에는 saving을 true로 변경하여
 * 저장 버튼의 로딩 상태를 표시합니다.
 *
 * 저장에 성공하면 수량을 초기화하고 데이터를 다시 조회한 뒤
 * 조회 탭으로 이동합니다.
 *
 * 저장 작업은 페이지 최초 조회가 아니므로
 * 공통 전체 화면 로딩은 사용하지 않습니다.
 */
async function save(setError) {
  saving.value = true;

  try {
    await window.axios.post(
      '/tillwhite/api/production',
      form.value,
    );

    // 저장 완료 후 수량 입력값 초기화
    form.value.production_quantity = 0;
    form.value.waste_quantity = 0;
    form.value.loss_quantity = 0;

    // 저장된 기록을 반영하기 위해 목록 다시 조회
    await load();

    // 저장 완료 후 조회 탭으로 이동
    tab.value = 'list';
  } catch (e) {
    setError(
      e.response?.data?.message ?? '저장에 실패했습니다.',
    );
  } finally {
    // 성공/실패 여부와 관계없이 저장 로딩 종료
    saving.value = false;
  }
}

/**
 * 선택한 생산 기록을 삭제합니다.
 *
 * 실제 삭제 요청을 보내기 전에 사용자에게 한 번 확인하고,
 * 삭제에 성공하면 목록을 다시 조회합니다.
 *
 * 삭제 작업 역시 페이지 최초 조회가 아니므로
 * 공통 전체 화면 로딩은 사용하지 않습니다.
 */
async function remove(record, setError) {
  // 사용자가 취소하면 삭제하지 않음
  if (!confirm('이 기록을 삭제할까요?')) {
    return;
  }

  try {
    await window.axios.delete(
      `/tillwhite/api/production/${record.id}`,
    );

    // 삭제된 기록을 반영하기 위해 목록 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '삭제에 실패했습니다.',
    );
  }
}

/**
 * 생산·폐기 관리 페이지 최초 진입 처리입니다.
 *
 * 페이지가 마운트되면
 * 생산 기록, 제품 목록, 작업자 목록을 조회합니다.
 *
 * 메뉴 이동 전에 시작된 애플리케이션 공통 로딩은
 * initialLoad()에서 최초 데이터 조회가 끝난 뒤 완료 처리합니다.
 *
 * 저장, 삭제, 날짜 필터 변경 후의 재조회는
 * load()만 사용하므로 전체 화면 로딩을 다시 표시하지 않습니다.
 */
onMounted(() => {
  initialLoad();
});
</script>