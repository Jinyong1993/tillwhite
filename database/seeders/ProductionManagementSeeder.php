<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductStockLot;
use App\Models\ProductStockMovement;
use App\Models\ProductionBatch;
use App\Models\ProductionConfirmation;
use App\Models\ProductionDailyClosure;
use App\Models\ProductionLoss;
use App\Models\ProductionLossReason;
use App\Models\ProductionOtherOutflow;
use App\Models\ProductionRecommendationSnapshot;
use App\Models\ProductionWaste;
use App\Models\ProductionWasteReason;
use App\Models\Store;
use App\Models\StoreCalendarEvent;
use App\Models\StoreDailyStatus;
use App\Models\StoreDailyWeather;
use App\Models\User;
use App\Models\WorkCode;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class ProductionManagementSeeder extends Seeder
{
    /**
     * 무역점 생산·폐기 관리의 최근 10일 검증 데이터를 생성합니다.
     *
     * 기준일은 2026-10-04이며 2026-09-25부터 10일을 구성합니다.
     * 공개 자료로 확인되는 무역센터점 메뉴와 기존 베이커리 샘플을 섞어
     * 목록·캘린더·분석·통계·이월 흐름을 실제 화면에서 확인할 수 있게 합니다.
     */
    public function run(): void
    {
        $store = Store::query()->where('name', '무역점')->first();
        if (!$store) {
            return;
        }
        $user = User::query()->where('store_id', $store->id)->where('department', 'kitchen')->orderBy('id')->first();
        if (!$user) {
            return;
        }
        $workers = User::query()->where('store_id', $store->id)->where('department', 'kitchen')->where('is_active', true)->take(3)->get();
        $workCode = WorkCode::query()->where('store_id', $store->id)->where('department', 'kitchen')->orderBy('sort_order')->first();
        $products = $this->prepareProducts($store->id, $user->id);
        $start = Carbon::create(2026, 9, 25);
        $end = Carbon::create(2026, 10, 4);
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            foreach ($workers as $worker) {
                WorkSchedule::updateOrCreate(['user_id' => $worker->id, 'work_date' => $dateString], ['store_id' => $store->id, 'department' => 'kitchen', 'work_code_id' => $workCode?->id, 'scheduled_start_time' => '07:30', 'scheduled_end_time' => '16:30', 'scheduled_break_minutes' => 60, 'status' => 'work', 'source' => 'manual', 'created_by' => $user->id]);
            }
            foreach ($products as $index => $product) {
                $base = 18 + $index * 3 % 11;
                $weekdayBoost = $date->isWeekend() ? 3 : 0;
                $production = $base + $weekdayBoost + ($date->day + $index) % 3;
                $loss = ($date->day + $index) % 13 === 0 ? 1 : 0;
                $waste = ($date->day + $index) % 7 === 0 ? 2 : (($date->day + $index) % 5 === 0 ? 1 : 0);
                $other = ($date->day + $index) % 17 === 0 ? 1 : 0;
                $carry = ($date->day + $index) % 9 === 0 ? 1 : 0;
                $batch = ProductionBatch::updateOrCreate(['store_id' => $store->id, 'product_id' => $product->id, 'work_date' => $dateString, 'note' => '최근 10일 화면 검증용 데이터'], ['quantity' => $production, 'recipe_deviated' => false, 'created_by' => $user->id]);
                DB::table('production_batch_workers')->updateOrInsert(['production_batch_id' => $batch->id, 'user_id' => $workers[$index % max(1, $workers->count())]->id, 'process_type' => 'all'], ['created_at' => now(), 'updated_at' => now()]);
                $lot = ProductStockLot::updateOrCreate(['production_batch_id' => $batch->id], ['store_id' => $store->id, 'product_id' => $product->id, 'origin_production_date' => $dateString, 'initial_quantity' => $production, 'remaining_quantity' => $carry, 'status' => $carry > 0 ? 'active' : 'exhausted']);
                if ($loss > 0) {
                    $lossRecord = ProductionLoss::updateOrCreate(['store_id' => $store->id, 'product_id' => $product->id, 'work_date' => $dateString, 'note' => '검증용 로스'], ['quantity' => $loss, 'created_by' => $user->id]);
                    ProductionLossReason::updateOrCreate(['production_loss_id' => $lossRecord->id, 'reason_code' => 'shape_failure'], ['reason_text' => null, 'quantity' => $loss]);
                }
                if ($waste > 0) {
                    $wasteRecord = ProductionWaste::updateOrCreate(['store_id' => $store->id, 'product_id' => $product->id, 'work_date' => $dateString, 'attribution_date' => $dateString, 'note' => '검증용 폐기'], ['stock_lot_id' => $lot->id, 'quantity' => $waste, 'created_by' => $user->id]);
                    ProductionWasteReason::updateOrCreate(['production_waste_id' => $wasteRecord->id, 'reason_code' => 'unsold'], ['reason_text' => null, 'quantity' => $waste]);
                }
                if ($other > 0) {
                    ProductionOtherOutflow::updateOrCreate(['store_id' => $store->id, 'product_id' => $product->id, 'work_date' => $dateString, 'reason_code' => 'tasting'], ['stock_lot_id' => $lot->id, 'quantity' => $other, 'reason_text' => null, 'note' => '시식 검증용', 'created_by' => $user->id]);
                }
                if ($carry > 0 && $date->lt($end)) {
                    ProductStockMovement::updateOrCreate(['stock_lot_id' => $lot->id, 'work_date' => $dateString, 'movement_type' => 'carryover_out'], ['store_id' => $store->id, 'product_id' => $product->id, 'quantity' => $carry, 'created_by' => $user->id]);
                    ProductStockMovement::updateOrCreate(['stock_lot_id' => $lot->id, 'work_date' => $date->copy()->addDay()->toDateString(), 'movement_type' => 'carryover_in'], ['store_id' => $store->id, 'product_id' => $product->id, 'quantity' => $carry, 'created_by' => $user->id]);
                }
                ProductionConfirmation::updateOrCreate(['store_id' => $store->id, 'product_id' => $product->id, 'work_date' => $dateString], ['production_confirmed' => true, 'loss_confirmed' => true, 'waste_confirmed' => true, 'disposition_confirmed' => true, 'confirmed_by' => $user->id]);
                $center = max(1, $production - $waste - $carry);
                $margin = max(1, (int) ceil($center * 0.05));
                ProductionRecommendationSnapshot::updateOrCreate(['store_id' => $store->id, 'product_id' => $product->id, 'target_date' => $dateString, 'calculation_version' => 'seed-v1'], ['center_quantity' => $center, 'range_min' => max(0, $center - $margin), 'range_max' => $center + $margin, 'confidence' => $date->diffInDays($start) >= 6 ? 'normal' : 'low', 'reasons' => ['최근 판매 흐름과 같은 요일 기록을 기준으로 한 화면 검증용 추천입니다.'], 'excluded_conditions' => [], 'data_from' => $start->copy()->subMonth()->toDateString(), 'data_to' => $date->copy()->subDay()->toDateString(), 'referenced' => $index % 2 === 0, 'generated_at' => $date->copy()->setTime(6, 30)]);
            }
            StoreDailyWeather::updateOrCreate(['store_id' => $store->id, 'work_date' => $dateString], ['average_temperature' => 18 + ($date->day % 5 - 2), 'minimum_temperature' => 14 + ($date->day % 4 - 1), 'maximum_temperature' => 23 + ($date->day % 4 - 1), 'average_humidity' => 58 + $date->day % 12, 'precipitation' => $dateString === '2026-10-01' ? 18.5 : 0, 'condition' => $dateString === '2026-10-01' ? '비' : '맑음', 'collection_status' => 'complete', 'initial_snapshot' => ['source' => '화면 검증용 시드 데이터']]);
            if ($date->lt($end)) {
                ProductionDailyClosure::updateOrCreate(['store_id' => $store->id, 'work_date' => $dateString], ['status' => 'closed', 'closed_by' => $user->id, 'closed_at' => $date->copy()->setTime(21, 30), 'updated_by' => $user->id]);
            }
        }
        // 이월 → 재이월 → 원 생산일 귀속 폐기를 실제 화면에서 확인할 수 있는 연결 사례입니다.
        $carryProduct = $products->first();
        $originBatch = ProductionBatch::query()->where('store_id', $store->id)->where('product_id', $carryProduct?->id)->whereDate('work_date', '2026-09-30')->first();
        $originLot = $originBatch ? ProductStockLot::query()->where('production_batch_id', $originBatch->id)->first() : null;
        if ($carryProduct && $originLot) {
            foreach ([['2026-09-30', 'carryover_out', 2], ['2026-10-01', 'carryover_in', 2], ['2026-10-01', 'carryover_out', 1], ['2026-10-02', 'carryover_in', 1]] as [$movementDate, $type, $quantity]) {
                ProductStockMovement::updateOrCreate(['stock_lot_id' => $originLot->id, 'work_date' => $movementDate, 'movement_type' => $type], ['store_id' => $store->id, 'product_id' => $carryProduct->id, 'quantity' => $quantity, 'reason_code' => $type === 'carryover_out' ? 'recarryover' : null, 'created_by' => $user->id]);
            }
            $carryWaste = ProductionWaste::updateOrCreate(['store_id' => $store->id, 'product_id' => $carryProduct->id, 'work_date' => '2026-10-02', 'attribution_date' => '2026-09-30', 'note' => '재이월 후 폐기 검증용'], ['stock_lot_id' => $originLot->id, 'quantity' => 1, 'created_by' => $user->id]);
            ProductionWasteReason::updateOrCreate(['production_waste_id' => $carryWaste->id, 'reason_code' => 'carryover_waste'], ['reason_text' => '품질 저하', 'quantity' => 1]);
        }
        StoreCalendarEvent::updateOrCreate(['store_id' => $store->id, 'title' => '개천절', 'start_date' => '2026-10-03'], ['event_type' => 'holiday', 'end_date' => '2026-10-03', 'memo' => '대한민국 공휴일', 'created_by' => $user->id]);
        StoreCalendarEvent::updateOrCreate(['store_id' => $store->id, 'title' => '가을 베이커리 프로모션', 'start_date' => '2026-10-02'], ['event_type' => 'promotion', 'end_date' => '2026-10-04', 'discount_type' => 'percent', 'discount_value' => 20, 'memo' => '분석·캘린더 화면 검증용 행사', 'created_by' => $user->id]);
    }

    // 무역점에서 확인할 10개 제품과 가격을 준비합니다.
    private function prepareProducts(int $storeId, int $userId)
    {
        $bakery = ProductCategory::updateOrCreate(['store_id' => $storeId, 'name' => '베이커리'], ['sort_order' => 10, 'is_active' => true]);
        $brunch = ProductCategory::updateOrCreate(['store_id' => $storeId, 'name' => '브런치'], ['sort_order' => 30, 'is_active' => true]);
        $samples = [['버터 소금빵', $bakery, 3800], ['우유 식빵', $bakery, 7000], ['피스타치오 식빵', $bakery, 12000], ['브리오슈 식빵', $bakery, 8500], ['보늬밤 식빵', $bakery, 9500], ['시즈널 프렌치 토스트', $brunch, 17000], ['햄카츠 산도', $brunch, 11800], ['토마토 부라타 샐러드', $brunch, 19000], ['치킨 시저 샐러드', $brunch, 19000], ['크리미 감자 수프', $brunch, 9800]];
        $result = collect();
        foreach ($samples as $index => [$name, $category, $price]) {
            $product = Product::withTrashed()->updateOrCreate(['store_id' => $storeId, 'name' => $name], ['product_category_id' => $category->id, 'production_department' => 'kitchen', 'management_department' => 'kitchen', 'sort_order' => ($index + 1) * 10, 'is_active' => true, 'sales_type' => 'regular', 'sales_start_date' => null, 'sales_end_date' => null]);
            if ($product->trashed()) {
                $product->restore();
            }
            ProductPrice::updateOrCreate(['product_id' => $product->id, 'effective_from' => '2026-09-01'], ['price' => $price, 'effective_to' => null, 'created_by' => $userId]);
            $result->push($product);
        }
        return $result;
    }
}
