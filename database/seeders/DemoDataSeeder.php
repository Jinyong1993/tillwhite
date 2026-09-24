<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductionRecord;
use App\Models\Sale;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkCode;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * 데모 화면에서 즉시 확인할 수 있는
     * Till White 업무 샘플 데이터를 생성합니다.
     *
     * 생성되는 주요 데이터:
     *
     * - 제품 카테고리
     * - 제품
     * - 제품 가격
     * - 주방 근무 코드
     * - 오늘 근무 일정
     * - 오늘 생산 실적
     * - 시스템 기본 설정
     *
     * 실제 운영 데이터가 아니라
     * 개발 및 화면 확인을 위한 샘플 데이터입니다.
     */
    public function run(): void
    {
        /**
         * 현재 운영 중인 모든 점포를 대상으로
         * 점포별 데모 데이터를 생성합니다.
         */
        $stores = Store::query()
            ->where('status', 'active')
            ->get();

        foreach ($stores as $store) {
            /*
            |--------------------------------------------------------------------------
            | 제품 카테고리
            |--------------------------------------------------------------------------
            |
            | 각 점포에 기본 제품 카테고리를 생성합니다.
            |
            | updateOrCreate()를 사용하므로
            | 같은 점포에 같은 이름의 카테고리가 이미 존재하면
            | 새로 중복 생성하지 않고 기존 데이터를 갱신합니다.
            |
            */
            $categories = [
                ['베이커리', 10],
                ['디저트', 20],
                ['음료', 30],
            ];

            foreach ($categories as [$name, $sortOrder]) {
                ProductCategory::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'name' => $name,
                    ],
                    [
                        'sort_order' => $sortOrder,
                        'is_active' => true,
                    ]
                );
            }

            /**
             * 아래 샘플 제품에서 사용할
             * 베이커리 / 디저트 카테고리를 가져옵니다.
             */
            $bakeryCategory = ProductCategory::query()
                ->where('store_id', $store->id)
                ->where('name', '베이커리')
                ->first();

            $dessertCategory = ProductCategory::query()
                ->where('store_id', $store->id)
                ->where('name', '디저트')
                ->first();

            /*
            |--------------------------------------------------------------------------
            | 제품 및 판매 가격
            |--------------------------------------------------------------------------
            |
            | 배열 순서:
            |
            | 제품명
            | 카테고리
            | 담당 부서
            | 판매 가격
            |
            */
            $products = [
                ['소금빵', $bakeryCategory, 'kitchen', 3500],
                ['크루아상', $bakeryCategory, 'kitchen', 4200],
                ['식빵', $bakeryCategory, 'kitchen', 5800],
                ['바스크 치즈케이크', $dessertCategory, 'kitchen', 6900],
            ];

            /**
             * 가격 생성 시 created_by에 사용할
             * 해당 점포의 사용자 한 명을 가져옵니다.
             */
            $storeUser = User::query()
                ->where('store_id', $store->id)
                ->first();

            foreach ($products as $index => [$name, $category, $department, $price]) {
                $product = Product::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'name' => $name,
                    ],
                    [
                        'product_category_id' => $category->id,
                        'production_department' => $department,
                        'management_department' => $department,
                        'sort_order' => ($index + 1) * 10,
                        'is_active' => true,
                    ]
                );

                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'effective_from' => now()
                            ->startOfYear()
                            ->toDateString(),
                    ],
                    [
                        'price' => $price,
                        'created_by' => $storeUser->id,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 주방 근무 코드
            |--------------------------------------------------------------------------
            |
            | 데모용으로 주방의 기본 근무 형태를 생성합니다.
            |
            | A = 오픈
            | B = 미들
            | C = 마감
            |
            | 배열 순서:
            |
            | 코드
            | 이름
            | 시작 시
            | 시작 분
            | 종료 시
            | 종료 분
            |
            */
            $workCodes = [
                ['A', '오픈', 7, 30, 16, 30],
                ['B', '미들', 10, 0, 19, 0],
                ['C', '마감', 12, 30, 21, 30],
            ];

            foreach ($workCodes as [$code, $name, $startHour, $startMinute, $endHour, $endMinute]) {
                WorkCode::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'department' => 'kitchen',
                        'code' => $code,
                    ],
                    [
                        'name' => $name,
                        'start_time' => sprintf(
                            '%02d:%02d',
                            $startHour,
                            $startMinute
                        ),
                        'end_time' => sprintf(
                            '%02d:%02d',
                            $endHour,
                            $endMinute
                        ),
                        'break_minutes' => 60,
                        'sort_order' => ord($code),
                        'is_active' => true,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 데모용 직원 및 제품 조회
            |--------------------------------------------------------------------------
            |
            | 생산 데이터와 근무 일정 생성에 사용할
            | 해당 점포의 주방 직원과 제품을 가져옵니다.
            |
            */
            $workers = User::query()
                ->where('store_id', $store->id)
                ->where('department', 'kitchen')
                ->get();

            $storeProducts = Product::query()
                ->where('store_id', $store->id)
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 오늘 근무 일정
            |--------------------------------------------------------------------------
            |
            | 해당 점포의 모든 주방 직원에게
            | 오늘 날짜의 데모 근무 일정을 생성합니다.
            |
            | WorkCode는 현재 점포 주방에서
            | sort_order가 가장 빠른 코드를 사용합니다.
            |
            */
            $firstWorkCode = WorkCode::query()
                ->where('store_id', $store->id)
                ->where('department', 'kitchen')
                ->orderBy('sort_order')
                ->first();

            foreach ($workers as $worker) {
                WorkSchedule::updateOrCreate(
                    [
                        'user_id' => $worker->id,
                        'work_date' => now()->toDateString(),
                    ],
                    [
                        'store_id' => $store->id,
                        'department' => 'kitchen',
                        'work_code_id' => $firstWorkCode?->id,
                        'scheduled_start_time' => '07:30',
                        'scheduled_end_time' => '16:30',
                        'scheduled_break_minutes' => 60,
                        'status' => 'work',
                        'source' => 'manual',
                        'created_by' => $worker->id,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 오늘 생산 실적
            |--------------------------------------------------------------------------
            |
            | 해당 점포의 제품마다 오늘 생산 데이터를 생성합니다.
            |
            | 데모 화면에서 생산량뿐만 아니라
            | 폐기 및 로스 데이터도 확인할 수 있도록
            | 제품별로 서로 다른 값을 넣습니다.
            |
            */
            $productionWorker = $workers->first();

            foreach ($storeProducts as $index => $product) {
                /**
                 * 주방 직원이 존재하는 경우에만
                 * 생산 기록을 생성합니다.
                 */
                if ($productionWorker) {
                    ProductionRecord::updateOrCreate(
                        [
                            'work_date' => now()->toDateString(),
                            'store_id' => $store->id,
                            'product_id' => $product->id,
                            'worker_id' => $productionWorker->id,
                        ],
                        [
                            'department' => 'kitchen',
                            'production_quantity' => 30 + ($index * 5),
                            'waste_quantity' => $index % 2,
                            'loss_quantity' => $index === 2 ? 1 : 0,
                            'waste_reason' => $index % 2
                                ? '판매 잔량'
                                : null,
                            'loss_reason' => $index === 2
                                ? '성형 불량'
                                : null,
                            'created_by' => $productionWorker->id,
                        ]
                    );
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 시스템 기본 설정
        |--------------------------------------------------------------------------
        |
        | 데모 화면에서 사용할 시스템 설정값을 생성합니다.
        |
        | app.name
        | - 시스템에 표시할 이름
        |
        | day_off.monthly_limit
        | - 직원이 한 달에 신청할 수 있는
        |   희망휴무 최대 일수
        |
        */
        SystemSetting::updateOrCreate(
            [
                'key' => 'app.name',
            ],
            [
                'value' => 'Till White',
                'type' => 'string',
                'description' => '시스템 표시 이름',
            ]
        );

        SystemSetting::updateOrCreate(
            [
                'key' => 'day_off.monthly_limit',
            ],
            [
                'value' => '10',
                'type' => 'integer',
                'description' => '월 희망휴무 최대 신청일',
            ]
        );
    }
}