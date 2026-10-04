<?php

namespace App\Services\Production;

use App\Models\ProductionRecommendationSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
class ProductionRecommendationService
{
    /**
     * 최근 실적을 우선한 설명 가능한 추천 범위를 계산합니다.
     *
     * 추천은 실제 업무 데이터를 변경하지 않으며, 표본이 부족하면 숫자를
     * 억지로 만들지 않습니다. 최근 같은 요일 판매량에 높은 가중치를 주고
     * 반복 폐기가 있으면 소폭 낮추며, 최종 범위는 중심값의 ±5%입니다.
     */
    /** 최근 실적과 특수 조건을 바탕으로 과도한 급등을 방지한 생산 권장 범위를 계산합니다. */
    public function recommend(Collection $history, Carbon $targetDate, array $conditions = []): array
    {
        $valid = $history->filter(fn(array $row) => !($row['mismatch'] ?? false))->filter(fn(array $row) => ($row['special_day'] ?? false) === false)->values();
        if ($valid->count() < 3) {
            return ['center' => null, 'min' => null, 'max' => null, 'confidence' => 'insufficient', 'reasons' => ['아직 추천에 사용할 정상 영업일 데이터가 충분하지 않습니다.'], 'warning' => null];
        }
        $weekday = $targetDate->dayOfWeek;
        $sameWeekday = $valid->filter(fn(array $row) => Carbon::parse($row['date'])->dayOfWeek === $weekday);
        $source = $sameWeekday->count() >= 2 ? $sameWeekday : $valid;
        $weighted = 0.0;
        $weightSum = 0.0;
        foreach ($source->reverse()->values() as $index => $row) {
            $weight = max(1, 6 - $index);
            $weighted += (int) $row['sale'] * $weight;
            $weightSum += $weight;
        }
        $center = (int) round($weighted / max(1, $weightSum));
        $recentWasteRate = (float) $valid->take(-5)->avg('waste_rate');
        if ($recentWasteRate >= 10) {
            $center = max(0, $center - 1);
        }
        $conditionReasons = [];
        $strongCondition = false;
        foreach ($conditions as $condition) {
            if (($condition['event_type'] ?? null) === 'group_order' && (int) ($condition['order_quantity'] ?? 0) > 0) {
                $center += (int) $condition['order_quantity'];
                $conditionReasons[] = '단체주문 ' . (int) $condition['order_quantity'] . '개를 별도 수요로 반영했습니다.';
                $strongCondition = true;
            }
            if (($condition['discount_type'] ?? null) === 'one_plus_one') {
                $center = (int) round($center * 1.5);
                $conditionReasons[] = '1+1 행사를 큰 수요 변화 조건으로 반영했습니다.';
                $strongCondition = true;
            } elseif (($condition['discount_type'] ?? null) === 'percent' && (float) ($condition['discount_value'] ?? 0) >= 50) {
                $center = (int) round($center * 1.25);
                $conditionReasons[] = '큰 폭의 할인 행사를 수요 변화 조건으로 반영했습니다.';
                $strongCondition = true;
            }
        }
        $margin = max(1, (int) ceil($center * 0.05));
        $recentAverage = (float) $valid->take(-7)->avg('production');
        $warning = null;
        if ($recentAverage > 0 && $center > $recentAverage * 1.5 && !$strongCondition) {
            $center = (int) round($recentAverage * 1.25);
            $margin = max(1, (int) ceil($center * 0.05));
            $warning = '평소 생산량보다 크게 높은 추천이 감지되어 안전 범위로 조정했습니다.';
        }
        return ['center' => $center, 'min' => max(0, $center - $margin), 'max' => $center + $margin, 'confidence' => $valid->count() >= 8 ? 'normal' : 'low', 'reasons' => ['최근 실제 판매 흐름을 우선 반영했습니다.', $sameWeekday->count() >= 2 ? '같은 요일의 최근 기록을 비교했습니다.' : '같은 요일 표본이 적어 최근 전체 기록을 함께 참고했습니다.', '폐기와 이월은 판매 기회를 해치지 않는 범위에서 보조 신호로 사용했습니다.', ...$conditionReasons], 'warning' => $strongCondition && $recentAverage > 0 && $center > $recentAverage * 1.5 ? '행사·단체주문 때문에 평소보다 크게 높은 추천입니다.' : $warning];
    }

    /** 추천을 실제로 참고한 시점의 계산 결과를 변경되지 않는 스냅샷으로 저장합니다. */
    public function snapshot(int $storeId, int $productId, string $date, array $recommendation, ?int $batchId = null, ?string $deviationReason = null): ProductionRecommendationSnapshot
    {
        return ProductionRecommendationSnapshot::create(['store_id' => $storeId, 'product_id' => $productId, 'production_batch_id' => $batchId, 'target_date' => $date, 'center_quantity' => $recommendation['center'], 'range_min' => $recommendation['min'], 'range_max' => $recommendation['max'], 'confidence' => $recommendation['confidence'], 'calculation_version' => 'v1', 'reasons' => $recommendation['reasons'], 'excluded_conditions' => [], 'data_from' => Carbon::parse($date)->subMonths(6)->toDateString(), 'data_to' => Carbon::parse($date)->subDay()->toDateString(), 'referenced' => true, 'deviation_reason' => $deviationReason, 'generated_at' => now()]);
    }
}
