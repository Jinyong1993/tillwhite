<template>
  <AppShell :title="pageTitle">
    <template #default="{ can, setError }">
      <!--
        sales.manage 권한이 있는 사용자에게만
        매출 등록 버튼을 표시합니다.
      -->
      <v-btn
        v-if="can('sales.manage')"
        block
        class="mb-3"
        prepend-icon="mdi-plus"
        @click="dialog = true"
      >
        매출 등록
      </v-btn>

      <!-- 현재 조회된 매출 기록의 총 매출액 -->
      <v-card
        variant="outlined"
        class="mb-3"
      >
        <v-card-text>
          <div class="text-caption">
            조회 매출 합계
          </div>

          <div class="text-h5 font-weight-bold">
            {{ total.toLocaleString() }}원
          </div>
        </v-card-text>
      </v-card>

      <!-- 매출 기록 목록 -->
      <v-list lines="three">
        <v-list-item
          v-for="sale in sales"
          :key="sale.id"
          :title="`${sale.sales_date} · ${sale.store?.name}`"
          :subtitle="`${sale.items?.length ?? 0}개 품목 · ${saleTotal(sale).toLocaleString()}원 · ${sale.status}`"
        />
      </v-list>

      <!-- 조회된 매출 기록이 없는 경우 표시 -->
      <v-empty-state
        v-if="!sales.length"
        title="매출 기록이 없습니다."
      />

      <!-- 간편 매출 등록 Dialog -->
      <v-dialog
        v-model="dialog"
        max-width="400"
      >
        <v-card title="간편 매출 등록">
          <v-card-text>
            <!--
              현재 데모에서는 복수 제품을 한 번에 입력하지 않고
              한 번에 한 제품만 빠르게 등록할 수 있습니다.
            -->
            <v-alert
              type="info"
              variant="tonal"
              density="compact"
              class="mb-3"
            >
              데모에서는 한 번에 한 제품을 빠르게 등록할 수 있습니다.
            </v-alert>

            <!-- 매출이 발생한 점포 -->
            <v-select
              v-model="form.store_id"
              :items="stores"
              item-title="name"
              item-value="id"
              label="점포"
              variant="outlined"
            />

            <!-- 매출 기준 날짜 -->
            <v-text-field
              v-model="form.sales_date"
              type="date"
              label="매출일"
              variant="outlined"
            />

            <!-- 판매한 제품 ID -->
            <v-text-field
              v-model="item.product_id"
              type="number"
              label="제품 ID"
              variant="outlined"
            />

            <!-- 판매 수량 -->
            <v-number-input
              v-model="item.quantity"
              label="수량"
              :min="1"
              variant="outlined"
            />

            <!-- 실제 판매 단가 -->
            <v-number-input
              v-model="item.actual_unit_price"
              label="판매 단가"
              :min="0"
              variant="outlined"
            />
          </v-card-text>

          <v-card-actions>
            <v-spacer />

            <!-- 매출 등록 취소 -->
            <v-btn @click="dialog = false">
              취소
            </v-btn>

            <!-- 매출 저장 -->
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
import { computed, onMounted, ref } from 'vue';
import AppShell from '../../components/layout/AppShell.vue';

// 현재 페이지 제목
const pageTitle = '매출 관리';

// 서버에서 조회한 매출 기록
const sales = ref([]);

// 매출 등록 Dialog 열림/닫힘 상태
const dialog = ref(false);

/**
 * 매출 기본 정보 Form입니다.
 *
 * store_id   : 매출이 발생한 점포
 * sales_date : 매출 기준 날짜
 * note       : 매출 관련 추가 메모
 */
const form = ref({
  store_id: null,
  sales_date: new Date().toISOString().slice(0, 10),
  note: '',
});

/**
 * 간편 매출 등록에서 사용하는 판매 품목 정보입니다.
 *
 * product_id        : 판매한 제품 ID
 * quantity          : 판매 수량
 * actual_unit_price : 실제 판매 단가
 */
const item = ref({
  product_id: null,
  quantity: 1,
  actual_unit_price: 0,
});

/**
 * 현재 조회된 매출 데이터에서 점포 목록을 생성합니다.
 *
 * store가 존재하는 매출 기록만 사용하며,
 * Map의 key로 점포 ID를 사용하여 중복 점포를 제거합니다.
 */
const stores = computed(() => {
  return [
    ...new Map(
      sales.value
        .filter((sale) => sale.store)
        .map((sale) => [
          sale.store.id,
          sale.store,
        ]),
    ).values(),
  ];
});

/**
 * 하나의 매출 기록에 포함된
 * 모든 판매 품목의 순매출 금액을 합산합니다.
 *
 * 품목이 없는 경우에는 0원을 반환합니다.
 */
const saleTotal = (sale) => {
  return (
    sale.items?.reduce(
      (sum, saleItem) => sum + saleItem.net_amount,
      0,
    ) ?? 0
  );
};

/**
 * 현재 조회된 모든 매출 기록의
 * 전체 매출 합계를 계산합니다.
 */
const total = computed(() => {
  return sales.value.reduce(
    (sum, sale) => sum + saleTotal(sale),
    0,
  );
});

/**
 * 매출 기록을 서버에서 조회합니다.
 *
 * 조회된 매출 목록을 sales에 저장하여
 * 목록과 합계 영역에 반영합니다.
 */
async function load() {
  const response = await window.axios.get(
    '/tillwhite/api/sales',
  );

  sales.value = response.data.sales;
}

/**
 * 새로운 매출을 서버에 저장합니다.
 *
 * 매출 기본 정보와 한 개의 판매 품목을 합쳐서
 * 서버에 전송합니다.
 *
 * 저장에 성공하면 Dialog를 닫고
 * 매출 목록을 다시 조회합니다.
 */
async function save(setError) {
  try {
    await window.axios.post(
      '/tillwhite/api/sales',
      {
        ...form.value,
        items: [
          item.value,
        ],
      },
    );

    // 매출 등록 Dialog 닫기
    dialog.value = false;

    // 등록된 매출을 반영하기 위해 목록 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '매출 저장 실패',
    );
  }
}

// 화면이 처음 열릴 때 매출 기록 조회
onMounted(load);
</script>