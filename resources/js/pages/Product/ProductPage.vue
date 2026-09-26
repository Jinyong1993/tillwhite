<template>
  <!--
    Till White 제품 관리 화면

    제품 목록 조회, 제품 등록, 제품 사용 상태 변경,
    제품별 레시피 등록 기능을 관리합니다.

    페이지 최초 데이터 조회의 전체 화면 로딩은
    애플리케이션 공통 로딩(useAppLoading)에서 관리합니다.
  -->
  <AppShell :title="pageTitle">
    <template #default="{ can, setError }">
      <!--
        제품 관리 권한(product.manage)이 있는 사용자에게만
        제품 등록 버튼을 표시합니다.
      -->
      <v-btn
        v-if="can('product.manage')"
        block
        class="mb-3"
        prepend-icon="mdi-plus"
        @click="dialog = true"
      >
        제품 등록
      </v-btn>

      <!-- 제품 목록 -->
      <v-expansion-panels>
        <v-expansion-panel
          v-for="product in products"
          :key="product.id"
        >
          <!-- 제품 기본 정보 -->
          <v-expansion-panel-title>
            <div>
              <div class="font-weight-bold">
                {{ product.name }}
              </div>

              <div class="text-caption">
                {{ product.store?.name }}
                · {{ product.category?.name }}
                · {{ product.is_active ? '사용' : '중지' }}
              </div>
            </div>
          </v-expansion-panel-title>

          <!-- 제품 상세 정보 -->
          <v-expansion-panel-text>
            <!-- 현재 적용 중인 제품 가격 -->
            <div class="text-body-2 mb-2">
              현재가 {{ latestPrice(product).toLocaleString() }}원
            </div>

            <!--
              제품 관리 권한(product.manage)이 있는 사용자에게만
              제품의 사용/중지 상태 변경 버튼을 표시합니다.
            -->
            <v-btn
              v-if="can('product.manage')"
              size="small"
              variant="outlined"
              @click="toggle(product, setError)"
            >
              사용 상태 변경
            </v-btn>

            <v-divider class="my-3" />

            <!-- 레시피 영역 -->
            <div class="font-weight-bold mb-2">
              레시피
            </div>

            <!-- 제품에 등록된 레시피 목록 -->
            <div
              v-for="recipeItem in product.recipes"
              :key="recipeItem.id"
              class="mb-3"
            >
              <!-- 레시피명 -->
              <div>
                {{ recipeItem.name }}
              </div>

              <!-- 레시피 재료 목록 -->
              <div class="text-caption">
                재료:
                {{
                  recipeItem.ingredients
                    ?.map(
                      (ingredient) =>
                        `${ingredient.name} ${ingredient.quantity}${ingredient.unit}`,
                    )
                    .join(', ') || '없음'
                }}
              </div>

              <!-- 레시피 공정 순서 -->
              <ol class="text-caption pl-5">
                <li
                  v-for="step in recipeItem.steps"
                  :key="step.id"
                >
                  {{ step.description }}
                </li>
              </ol>
            </div>

            <!-- 레시피 관리 권한이 있는 경우에만 추가 가능 -->
            <v-btn
              v-if="can('recipe.manage')"
              size="small"
              @click="openRecipe(product)"
            >
              레시피 추가
            </v-btn>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>

      <!-- 제품 등록 Dialog -->
      <v-dialog
        v-model="dialog"
        max-width="400"
      >
        <v-card title="제품 등록">
          <v-card-text>
            <!-- 제품을 등록할 점포 -->
            <v-select
              v-model="form.store_id"
              :items="stores"
              item-title="name"
              item-value="id"
              label="점포"
              variant="outlined"
            />

            <!--
              선택한 점포에 등록된 카테고리만 표시합니다.
            -->
            <v-select
              v-model="form.product_category_id"
              :items="
                categories.filter(
                  (category) => category.store_id === form.store_id,
                )
              "
              item-title="name"
              item-value="id"
              label="카테고리"
              variant="outlined"
            />

            <!-- 제품명 -->
            <v-text-field
              v-model="form.name"
              label="제품명"
              variant="outlined"
            />

            <!-- 제품을 실제로 생산하는 부서 -->
            <v-select
              v-model="form.production_department"
              :items="departments"
              label="생산 부서"
              variant="outlined"
            />

            <!-- 제품 정보를 관리하는 부서 -->
            <v-select
              v-model="form.management_department"
              :items="departments"
              label="관리 부서"
              variant="outlined"
            />

            <!-- 최초 판매 가격 -->
            <v-number-input
              v-model="form.price"
              label="판매가"
              :min="0"
              variant="outlined"
            />
          </v-card-text>

          <v-card-actions>
            <v-spacer />

            <!-- 제품 등록 취소 -->
            <v-btn @click="dialog = false">
              취소
            </v-btn>

            <!-- 제품 등록 -->
            <v-btn @click="save(setError)">
              저장
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- 레시피 등록 Dialog -->
      <v-dialog
        v-model="recipeDialog"
        max-width="400"
      >
        <v-card title="레시피 추가">
          <v-card-text>
            <!-- 레시피명 -->
            <v-text-field
              v-model="recipe.name"
              label="레시피명"
              variant="outlined"
            />

            <!-- 레시피에 대한 추가 설명 -->
            <v-textarea
              v-model="recipe.description"
              label="설명"
              variant="outlined"
            />

            <!--
              재료 입력 형식:

              한 줄에 "재료명,수량,단위" 형식으로 입력합니다.

              예:

              강력분,100,g
              버터,20,g
            -->
            <v-textarea
              v-model="recipe.ingredientsText"
              label="재료 (한 줄에 이름,수량,단위)"
              variant="outlined"
            />

            <!--
              제조 공정을 한 줄에 한 단계씩 입력합니다.

              예:

              재료를 계량한다.
              반죽하고 발효한다.
            -->
            <v-textarea
              v-model="recipe.stepsText"
              label="공정 (한 줄에 한 단계)"
              variant="outlined"
            />
          </v-card-text>

          <v-card-actions>
            <v-spacer />

            <!-- 레시피 등록 취소 -->
            <v-btn @click="recipeDialog = false">
              취소
            </v-btn>

            <!-- 레시피 저장 -->
            <v-btn @click="saveRecipe(setError)">
              저장
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
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
 * 메뉴를 통해 제품 관리 화면으로 이동할 때
 * 이미 시작된 공통 로딩을
 * 최초 제품 데이터 조회가 완료된 뒤 종료합니다.
 *
 * 최소 1초 표시 시간은
 * 공통 로딩(useAppLoading)에서 관리합니다.
 */
const {
  completePageLoading,
} = useAppLoading();

// 현재 페이지 제목
const pageTitle = '제품 관리';

// 서버에서 조회한 제품 목록
const products = ref([]);

// 서버에서 조회한 제품 카테고리 목록
const categories = ref([]);

// 제품 등록 Dialog 열림/닫힘 상태
const dialog = ref(false);

// 레시피 등록 Dialog 열림/닫힘 상태
const recipeDialog = ref(false);

// 현재 레시피를 추가하려고 선택한 제품
const selected = ref(null);

/**
 * 신규 제품 등록 Form입니다.
 *
 * store_id              : 제품이 속한 점포
 * product_category_id   : 제품 카테고리
 * name                  : 제품명
 * production_department : 제품 생산 담당 부서
 * management_department : 제품 관리 담당 부서
 * price                 : 최초 판매 가격
 */
const form = ref({
  store_id: null,
  product_category_id: null,
  name: '',
  production_department: 'kitchen',
  management_department: 'kitchen',
  price: 0,
});

/**
 * 신규 레시피 등록 Form입니다.
 *
 * name            : 레시피명
 * description     : 레시피 설명
 * ingredientsText : 사용자가 입력하는 재료 문자열
 * stepsText       : 사용자가 입력하는 공정 문자열
 *
 * 현재 데모에서는 입력 방법을 확인하기 쉽도록
 * 기본 예시 데이터를 넣어두었습니다.
 */
const recipe = ref({
  name: '',
  description: '',
  ingredientsText: '강력분,100,g\n버터,20,g',
  stepsText: '재료를 계량한다.\n반죽하고 발효한다.',
});

/**
 * 제품의 생산 및 관리 부서로 사용할 수 있는 목록입니다.
 *
 * title : 화면에 표시할 부서명
 * value : DB와 API에서 사용하는 부서 코드
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
];

/**
 * 카테고리와 제품 데이터에서 점포 목록을 생성합니다.
 *
 * Map의 key로 store_id를 사용하여
 * 동일한 점포가 여러 번 표시되지 않도록 중복을 제거합니다.
 */
const stores = computed(() => {
  return [
    ...new Map(
      categories.value.map((category) => [
        category.store_id,
        {
          id: category.store_id,
          name:
            products.value.find(
              (product) => product.store_id === category.store_id,
            )?.store?.name ?? `점포 ${category.store_id}`,
        },
      ]),
    ).values(),
  ];
});

/**
 * 제품의 현재 판매 가격을 반환합니다.
 *
 * prices 배열의 첫 번째 가격을 현재 가격으로 사용하며,
 * 등록된 가격이 없으면 0원을 반환합니다.
 */
function latestPrice(product) {
  return product.prices?.[0]?.price ?? 0;
}

/**
 * 제품 관리 화면에 필요한 데이터를 서버에서 조회합니다.
 *
 * products:
 * - 제품 및 레시피 등의 제품 정보
 *
 * categories:
 * - 제품 카테고리 정보
 */
async function load() {
  const response =
    await window.axios.get('/tillwhite/api/products');

  products.value = response.data.products;
  categories.value = response.data.categories;
}

/**
 * 제품 관리 화면의 최초 데이터를 불러옵니다.
 *
 * 제품 관리 페이지에 처음 진입했을 때만 사용합니다.
 *
 * 처리 순서:
 *
 * 1. Laravel 서버에서 제품과 카테고리 정보를 조회합니다.
 * 2. 조회한 데이터를 화면 상태에 저장합니다.
 * 3. 성공 또는 실패와 관계없이 공통 페이지 로딩 완료를 알립니다.
 *
 * load()에서 발생한 오류는 여기에서 별도로 처리하지 않고
 * 기존 동작과 동일하게 유지합니다.
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
 * 신규 제품을 등록합니다.
 *
 * 저장에 성공하면 Dialog를 닫고
 * 제품 목록을 다시 조회하여 화면을 갱신합니다.
 *
 * 실패하면 AppShell에서 전달받은 오류 처리 함수를 통해
 * 오류 메시지를 화면에 표시합니다.
 */
async function save(setError) {
  try {
    await window.axios.post(
      '/tillwhite/api/products',
      form.value,
    );

    // 제품 등록 Dialog 닫기
    dialog.value = false;

    // 변경된 제품 목록 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '제품 저장 실패',
    );
  }
}

/**
 * 제품의 사용/중지 상태를 변경합니다.
 *
 * 변경 후 제품 목록을 다시 조회하여
 * 최신 상태를 화면에 반영합니다.
 */
async function toggle(product, setError) {
  try {
    await window.axios.put(
      `/tillwhite/api/products/${product.id}/toggle`,
    );

    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '변경 실패',
    );
  }
}

/**
 * 레시피 추가 Dialog를 엽니다.
 *
 * 어떤 제품에 레시피를 추가하는지 알 수 있도록
 * 선택한 제품을 selected에 저장합니다.
 */
function openRecipe(product) {
  selected.value = product;
  recipeDialog.value = true;
}

/**
 * 입력한 레시피 정보를 서버에 저장합니다.
 *
 * ingredientsText와 stepsText는 사용자가 입력하기 편하도록
 * 문자열 형태로 관리하고, 서버에 전송하기 전에
 * ingredients와 steps 배열 형태로 변환합니다.
 */
async function saveRecipe(setError) {
  try {
    /**
     * 재료 문자열을 줄 단위로 분리한 뒤
     * "이름,수량,단위" 구조의 객체 배열로 변환합니다.
     */
    const ingredients = recipe.value.ingredientsText
      .split('\n')
      .filter(Boolean)
      .map((line) => {
        const [name, quantity, unit] = line.split(',');

        return {
          name,
          quantity: Number(quantity),
          unit,
        };
      });

    /**
     * 공정 문자열을 줄 단위로 분리하여
     * description을 가진 객체 배열로 변환합니다.
     */
    const steps = recipe.value.stepsText
      .split('\n')
      .filter(Boolean)
      .map((description) => ({
        description,
      }));

    // 선택한 제품에 새로운 레시피 등록
    await window.axios.post(
      `/tillwhite/api/products/${selected.value.id}/recipes`,
      {
        name: recipe.value.name,
        description: recipe.value.description,
        ingredients,
        steps,
      },
    );

    // 레시피 등록 Dialog 닫기
    recipeDialog.value = false;

    // 새 레시피를 반영하기 위해 제품 목록 다시 조회
    await load();
  } catch (e) {
    setError(
      e.response?.data?.message ?? '레시피 저장 실패',
    );
  }
}

/**
 * 제품 관리 페이지 최초 진입 처리입니다.
 *
 * 페이지가 마운트되면 제품과 카테고리 데이터를 조회합니다.
 *
 * 메뉴 이동 전에 시작된 애플리케이션 공통 로딩은
 * initialLoad()에서 최초 데이터 조회가 끝난 뒤 완료 처리합니다.
 *
 * 제품 등록, 상태 변경, 레시피 등록 후의 재조회는
 * load()만 사용하므로 전체 화면 로딩을 다시 표시하지 않습니다.
 */
onMounted(() => {
  initialLoad();
});
</script>