<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * 제품관리 화면 검증용 현실적인 샘플 제품을 생성합니다.
     *
     * 각 운영 점포에서 검색/필터/페이지네이션/상태/기간한정/삭제 복구를
     * 모두 확인할 수 있도록 같은 30개 제품 구성을 점포별로 생성합니다.
     * updateOrCreate()를 사용하므로 Seeder를 다시 실행해도 중복되지 않습니다.
     */
    public function run(): void
    {
        $stores = Store::query()
            ->where('status', 'active')
            ->get();

        foreach ($stores as $store) {
            $createdBy = User::query()
                ->where('store_id', $store->id)
                ->orderBy('id')
                ->first();

            if (! $createdBy) {
                continue;
            }

            $categories = $this->categoriesForStore($store->id);

            foreach ($this->sampleProducts() as $index => $sample) {
                $category = $categories[$sample['category']] ?? null;

                if (! $category) {
                    continue;
                }

                $product = Product::withTrashed()->updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'name' => $sample['name'],
                    ],
                    [
                        'product_category_id' => $category->id,
                        'production_department' => $sample['production_department'],
                        'management_department' => $sample['management_department'],
                        'sort_order' => ($index + 10) * 10,
                        'is_active' => $sample['is_active'],
                        'sales_type' => $sample['sales_type'],
                        'sales_start_date' => $sample['sales_start_date'],
                        'sales_end_date' => $sample['sales_end_date'],
                    ]
                );

                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'effective_from' => '2026-01-01',
                    ],
                    [
                        'price' => $sample['price'],
                        'effective_to' => null,
                        'created_by' => $createdBy->id,
                    ]
                );

                // 삭제 상태 카드와 복구 UX도 실제 데이터로 확인할 수 있게 일부만 Soft Delete합니다.
                if ($sample['deleted']) {
                    if (! $product->trashed()) {
                        $product->delete();
                    }
                } elseif ($product->trashed()) {
                    $product->restore();
                }
            }
        }
    }

    /**
     * 제품 데이터에서 사용할 카테고리를 점포별로 준비합니다.
     */
    private function categoriesForStore(int $storeId): array
    {
        $definitions = [
            '베이커리' => 10,
            '디저트' => 20,
            '브런치' => 30,
            '커피' => 40,
            '음료' => 50,
        ];

        $result = [];

        foreach ($definitions as $name => $sortOrder) {
            $result[$name] = ProductCategory::updateOrCreate(
                [
                    'store_id' => $storeId,
                    'name' => $name,
                ],
                [
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );
        }

        return $result;
    }

    /**
     * 카테고리와 담당 부서가 실제 매장 운영상 자연스럽도록 구성한 30개 샘플입니다.
     */
    private function sampleProducts(): array
    {
        $regular = fn (
            string $name,
            string $category,
            int $price,
            string $production,
            string $management,
            bool $active = true,
            bool $deleted = false
        ) => [
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'production_department' => $production,
            'management_department' => $management,
            'is_active' => $active,
            'sales_type' => 'regular',
            'sales_start_date' => null,
            'sales_end_date' => null,
            'deleted' => $deleted,
        ];

        $limited = fn (
            string $name,
            string $category,
            int $price,
            string $production,
            string $management,
            string $start,
            string $end,
            bool $active = true
        ) => [
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'production_department' => $production,
            'management_department' => $management,
            'is_active' => $active,
            'sales_type' => 'limited',
            'sales_start_date' => $start,
            'sales_end_date' => $end,
            'deleted' => false,
        ];

        return [
            $regular('버터 소금빵', '베이커리', 3800, 'kitchen', 'kitchen'),
            $regular('플레인 베이글', '베이커리', 3900, 'kitchen', 'kitchen'),
            $regular('올리브 치아바타', '베이커리', 5200, 'kitchen', 'kitchen'),
            $regular('무화과 깜빠뉴', '베이커리', 6800, 'kitchen', 'kitchen'),
            $regular('초코 크루아상', '베이커리', 4900, 'kitchen', 'kitchen'),
            $regular('아몬드 크루아상', '베이커리', 5500, 'kitchen', 'kitchen'),
            $regular('잠봉뵈르 바게트', '베이커리', 8500, 'kitchen', 'hall'),
            $regular('단팥빵', '베이커리', 3200, 'kitchen', 'kitchen', false),

            $regular('레몬 마들렌', '디저트', 3300, 'kitchen', 'hall'),
            $regular('바닐라 휘낭시에', '디저트', 3500, 'kitchen', 'hall'),
            $regular('초코 휘낭시에', '디저트', 3700, 'kitchen', 'hall'),
            $regular('티라미수', '디저트', 7200, 'kitchen', 'hall'),
            $regular('당근 케이크', '디저트', 6800, 'kitchen', 'hall'),
            $limited('딸기 생크림 케이크', '디저트', 7900, 'kitchen', 'hall', '2026-11-01', '2027-02-28'),
            $limited('밤 몽블랑', '디저트', 7500, 'kitchen', 'hall', '2026-09-01', '2026-11-30'),

            $regular('햄치즈 크루아상 샌드위치', '브런치', 8900, 'kitchen', 'hall'),
            $regular('에그마요 샌드위치', '브런치', 7800, 'kitchen', 'hall'),
            $regular('프렌치토스트', '브런치', 12500, 'kitchen', 'hall'),
            $regular('햄카츠 산도', '브런치', 11800, 'kitchen', 'hall'),
            $regular('리코타 샐러드', '브런치', 10900, 'kitchen', 'hall', true, true),

            $regular('아메리카노', '커피', 4800, 'hall', 'hall'),
            $regular('카페라떼', '커피', 5500, 'hall', 'hall'),
            $regular('바닐라 라떼', '커피', 6000, 'hall', 'hall'),
            $regular('플랫화이트', '커피', 5600, 'hall', 'hall'),
            $regular('콜드브루', '커피', 5800, 'hall', 'hall', false),

            $regular('레몬 에이드', '음료', 6500, 'hall', 'hall'),
            $regular('자몽 에이드', '음료', 6500, 'hall', 'hall'),
            $regular('얼그레이 밀크티', '음료', 6800, 'hall', 'hall'),
            $limited('딸기 라떼', '음료', 7000, 'hall', 'hall', '2026-12-01', '2027-03-31'),
            $limited('청귤 에이드', '음료', 6800, 'hall', 'hall', '2026-07-01', '2026-09-30', false),
        ];
    }
}
