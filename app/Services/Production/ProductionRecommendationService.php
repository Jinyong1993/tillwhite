<?php

namespace App\Services\Production;

use App\Models\ProductionRecommendationSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductionRecommendationService
{
    private const MAX_HISTORY = 366;
    private const WEIGHT_DECAY = 0.85;
    private const MIN_HISTORY = 1;
    private const BACKTEST_DAYS = 28;

    /**
     * 생산 추천 분석 메시지
     *
     * 같은 분석 결과에는 동일한 문구를 사용합니다.
     * 계산 근거와 실제 보정 여부를 구분합니다.
     *
     * 상세 분석 화면에서는 metrics와 함께
     * 각 메시지를 보조 설명으로 활용합니다.
     */
    private const MESSAGES = [
        'insufficient' => [
            '추천할 수 있는 유효한 생산 기록이 없습니다.',
        ],

        'demand_sales' => [
            '확정된 판매 기록을 참고해 예상 수요를 계산했습니다.',
        ],

        'demand_estimated' => [
            '생산·이월 입고·로스·폐기·이월 출고 기록으로 판매량을 자동 추정했습니다.',
        ],

        'weekday_enough' => [
            '추천 대상일과 같은 요일의 과거 수요를 우선 분석했습니다.',
        ],

        'weekday_lacking' => [
            '동일 요일 기록이 부족해 다른 정상 영업일의 수요를 참고했습니다.',
        ],

        'stockout' => [
            '과거 품절 기록이 있어 실제 수요가 관측된 판매량보다 많았을 가능성이 있습니다.',
        ],

        'inventory_adjusted' => [
            '폐기와 이월 기록을 판매량 추정 및 잔여 재고 분석에 반영했습니다.',
        ],

        'weather_applied' => [
            '과거 비슷한 날씨의 수요를 참고해 제한적인 날씨 보정을 적용했습니다.',
        ],

        'weather_insufficient' => [
            '비슷한 날씨의 기록이 부족해 날씨 보정을 적용하지 않았습니다.',
        ],

        'group_order' => [
            '단체 주문 {quantity}개를 예상 수요에 추가했습니다.',
        ],

        'promotion_applied' => [
            '과거 유사한 할인 행사 기록을 이용해 예상 수요를 보정했습니다.',
        ],

        'promotion_fallback' => [
            '유사한 할인 행사 기록이 부족해 임시 보정 기준을 적용했습니다.',
        ],

        'promotion_warning' => [
            '할인 행사 보정에 사용할 과거 기록이 부족하므로 추천 결과를 주의해서 확인해 주세요.',
        ],

        'holiday_applied' => [
            '과거 공휴일 수요를 참고해 예상 수요를 보정했습니다.',
        ],

        'holiday_insufficient' => [
            '비교할 공휴일 기록이 부족해 공휴일 보정을 적용하지 않았습니다.',
        ],

        'high_quantity_adjusted' => [
            '일반 영업일의 추천량이 최근 수요보다 지나치게 높아 상한을 조정했습니다.',
        ],

        'high_quantity_warning' => [
            '높게 계산된 추천량을 제한했으므로 실제 영업 상황도 확인해 주세요.',
        ],

        'low_confidence' => [
            '현재 분석 근거나 과거 예측 성능이 충분하지 않아 추천 신뢰도가 낮습니다.',
        ],

        'invalid_history' => [
            '마감 상태 또는 수량 정합성을 확인할 수 없는 기록 {count}건을 분석에서 제외했습니다.',
        ],
    ];

    /**
     * 분석 결과에 대응하는 일관된 메시지 반환
     *
     * 기존 호출부와의 호환성을 유지합니다.
     * 날짜 인수는 기존 함수 시그니처를 위해 보존합니다.
     */
    private function message(
        string $key,
        Carbon $date,
        array $replacements = []
    ): string {
        $patterns = self::MESSAGES[$key] ?? [];

        if (empty($patterns)) {
            return '';
        }

        $message = $patterns[0];

        foreach ($replacements as $name => $value) {
            $message = str_replace(
                '{' . $name . '}',
                (string) $value,
                $message
            );
        }

        return $message;
    }

    public function recommend(
        Collection $history,
        Carbon $targetDate,
        array $conditions = []
    ): array {
        $targetDate = $targetDate->copy()->startOfDay();

        $rows = $history
            ->filter(fn ($row) =>
                is_array($row)
                && !empty($row['date'])
                && Carbon::parse($row['date'])->lt($targetDate)
            )
            ->sortBy('date')
            ->take(-self::MAX_HISTORY)
            ->values();

        // 이상 기록은 일반일뿐 아니라 행사·공휴일 분석에서도 제외합니다.
        $invalidRows = $rows
            ->filter(fn (array $row) =>
                $this->invalidHistoryRow($row)
            )
            ->values();

        $cleanRows = $rows
            ->reject(fn (array $row) =>
                $this->invalidHistoryRow($row)
            )
            ->values();

        $normal = $cleanRows
            ->filter(fn (array $row) =>
                !($row['special_day'] ?? false)
            )
            ->values();

        $valid = $normal
            ->filter(fn (array $row) =>
                $this->demandValue($row) !== null
            )
            ->values();

        $excludedCount = $invalidRows->count();
        $warnings = [];

        if ($excludedCount > 0) {
            $warnings[] = $this->message(
                'invalid_history',
                $targetDate,
                ['count' => $excludedCount]
            );
        }

        if ($valid->count() < self::MIN_HISTORY) {
            return [
                'center' => null,
                'min' => null,
                'max' => null,
                'confidence' => 'insufficient',
                'reasons' => [
                    $this->message('insufficient', $targetDate),
                ],
                'warning' => $warnings
                    ? implode(' ', array_unique($warnings))
                    : null,
                'metrics' => [
                    'history_count' => $valid->count(),
                    'excluded_history_count' => $excludedCount,
                    'sales_count' => 0,
                    'stockout_count' => 0,
                    'same_weekday_count' => 0,
                    'backtest_count' => 0,
                    'mae' => null,
                    'wape' => null,
                    'calculation_version' => 'v3-adaptive-demand',
                ],
            ];
        }

        /*
        * 추천 분석에 사용된 판매량 자료의 출처 구분
        *
        * 기존 sales_count는 호환성을 위해 유지합니다.
        * 자동 추정 판매량도 유효한 분석 자료로 사용합니다.
        */
        $salesCount = $valid
            ->filter(fn (array $row) =>
                $this->salesValue($row) !== null
            )
            ->count();

        $estimatedSalesCount = $valid
            ->filter(fn (array $row) =>
                ($row['sales_source'] ?? null)
                    === 'inventory_estimate'
            )
            ->count();

        $confirmedSalesCount = $valid
            ->filter(fn (array $row) =>
                ($row['sales_source'] ?? null)
                    === 'confirmed'
            )
            ->count();

        // 최근 기록과 같은 요일을 이용해 기본 추천량 계산
        $baseline = $this->baseline(
            $valid,
            $targetDate
        );

        $center = $baseline['center'];

        $reasons = [
            $this->message(
                $estimatedSalesCount > 0
                    ? 'demand_estimated'
                    : (
                        $confirmedSalesCount > 0
                            ? 'demand_sales'
                            : 'demand_estimated'
                    ),
                $targetDate
            ),
            $this->message(
                $baseline['same_weekday_count'] >= 2
                    ? 'weekday_enough'
                    : 'weekday_lacking',
                $targetDate
            ),
        ];

        /*
        * 동일 요일의 최근 수요 추세 확인
        *
        * 기존 최근 3개월 70% / 이전 9개월 30%
        * 가중치를 유지합니다.
        *
        * 동일한 변화가 추천량에 중복 반영되지 않도록
        * 현재 단계에서는 추천 근거만 추가합니다.
        */
        $recentTrend = $this->recentDemandTrend(
            $valid,
            $targetDate
        );

        if ($recentTrend !== null) {
            if ($recentTrend['direction'] === 'increasing') {
                $reasons[] =
                    '최근 동일 요일의 추정 판매량에서 '
                    . '지속적인 증가 추세가 확인되었습니다.';
            } else {
                $reasons[] =
                    '최근 동일 요일의 추정 판매량에서 '
                    . '지속적인 감소 추세가 확인되었습니다.';
            }
        }

        /*
        * 최근 수요 이상 변동 가능성 확인
        *
        * 기록을 삭제하거나 추천 계산에서 제외하지 않습니다.
        */
        $recentOutlier = $this->recentDemandOutlier(
            $valid,
            $targetDate
        );

        if ($recentOutlier !== null) {
            if ($recentOutlier['type'] === 'unusually_high') {
                $warnings[] =
                    '최근 추정 판매량이 이전 정상 영업일보다 '
                    . '크게 증가한 기록이 있습니다. '
                    . '일시적인 수요 증가인지 확인해 주세요.';
            } else {
                $warnings[] =
                    '최근 추정 판매량이 이전 정상 영업일보다 '
                    . '크게 감소한 기록이 있습니다. '
                    . '영업 상황이나 기록 누락 여부를 확인해 주세요.';
            }
        }

        /*
        * 기록 1개만으로 계산한 초기 추천 안내
        */
        if ($valid->count() === 1) {
            $reasons[] =
                '유효한 생산 기록 1개를 바탕으로 '
                . '초기 참고 수량을 계산했습니다.';

            $warnings[] =
                '분석 기록이 1개뿐이므로 추천 범위가 넓습니다. '
                . '실제 생산량은 현장 상황을 함께 고려해 주세요.';
        }

        /*
        * 품절 및 완판 가능성 분석
        *
        * 명시적인 품절 기록과
        * 재고 흐름에서 관찰되는 완판 가능성을 구분합니다.
        *
        * 폐기와 이월이 모두 0이라고 해서
        * 실제 품절이 확정된 것은 아닙니다.
        */
        $recentStockoutRows = $valid->take(-7);

        // 명시적으로 기록된 품절
        $stockoutCount = $recentStockoutRows
            ->filter(fn (array $row) =>
                $this->isStockout($row)
            )
            ->count();

        if ($stockoutCount > 0) {
            $warnings[] = $this->message(
                'stockout',
                $targetDate
            );
        }

        /*
        * 재고가 남지 않은 정상 영업일 확인
        *
        * - 정상적인 마감 기록
        * - 유효한 당일 수량
        * - 실제 사용 가능 수량이 존재
        * - 추정 판매량이 0보다 큼
        * - 당일 폐기와 다음 날 이월이 모두 0
        *
        * 모두 충족해야 완판 가능성으로 집계합니다.
        */
        $possibleSelloutCount = $recentStockoutRows
            ->filter(function (array $row): bool {
                if (
                    ($row['closure_status'] ?? null)
                        !== 'closed'
                ) {
                    return false;
                }

                if (
                    ($row['daily_quantity_valid'] ?? false)
                        !== true
                ) {
                    return false;
                }

                $available = $this->numeric(
                    $row,
                    ['daily_available_quantity']
                );

                $remaining = $this->numeric(
                    $row,
                    ['daily_remaining_quantity']
                );

                $estimatedSales = $this->numeric(
                    $row,
                    ['estimated_sales_quantity']
                );

                return
                    $available !== null
                    && $available > 0
                    && $remaining !== null
                    && $remaining == 0
                    && $estimatedSales !== null
                    && $estimatedSales > 0;
            })
            ->count();

        /*
        * 최근 7개 유효 기록 중
        * 재고가 남지 않은 날이 3회 이상이면 안내합니다.
        *
        * 이 조건만으로 추천 생산량을 증가시키지 않습니다.
        * 실제 결품 여부는 추가 데이터와 함께 판단해야 합니다.
        */
        if ($possibleSelloutCount >= 3) {
            $warnings[] =
                '최근 유효 생산 기록에서 재고가 모두 소진된 날이 '
                . $possibleSelloutCount
                . '회 확인되었습니다. '
                . '추가 판매 기회가 있었는지 확인해 주세요.';
        }

        /*
        * 자동 추정 판매량에 폐기·이월이 사용되었는지 확인
        *
        * 정상 마감된 기록에는 sales_quantity가 존재하므로
        * 기존 salesValue() === null 조건만으로는
        * 자동 추정 판매 기록을 판단할 수 없습니다.
        */
        $adjustedCount = $valid
            ->filter(function (array $row): bool {
                $isEstimated =
                    ($row['sales_source'] ?? null)
                        === 'inventory_estimate';

                $hasNoSalesValue =
                    $this->salesValue($row) === null;

                if (!$isEstimated && !$hasNoSalesValue) {
                    return false;
                }

                $waste = $this->numeric(
                    $row,
                    ['operational_waste', 'waste']
                ) ?? 0;

                $carryover = $this->numeric(
                    $row,
                    ['carryover_out']
                ) ?? 0;

                return $waste > 0 || $carryover > 0;
            })
            ->count();

        if ($adjustedCount > 0) {
            $reasons[] = $this->message(
                'inventory_adjusted',
                $targetDate
            );
        }

        /*
        * 최근 정상 영업일의 과잉 재고 비율 분석
        *
        * 폐기와 이월은 동일한 과잉 재고로 취급합니다.
        *
        * 개별 날짜의 비율을 단순 평균하지 않고
        * 전체 사용 가능 수량 대비 전체 잔여 수량으로
        * 계산해 생산 규모 차이를 반영합니다.
        *
        * 기존 원 생산일 귀속 폐기율은 변경하지 않습니다.
        */
        $surplusRows = $valid
            ->take(-7)
            ->filter(function (array $row): bool {
                $available = $this->numeric(
                    $row,
                    ['daily_available_quantity']
                );

                $remaining = $this->numeric(
                    $row,
                    ['daily_remaining_quantity']
                );

                return
                    ($row['daily_quantity_valid'] ?? false)
                        === true
                    && $available !== null
                    && $available > 0
                    && $remaining !== null;
            })
            ->values();

        $surplusAvailableTotal = 0.0;
        $surplusRemainingTotal = 0.0;
        $surplusWasteTotal = 0.0;
        $surplusCarryoverTotal = 0.0;

        foreach ($surplusRows as $row) {
            $surplusAvailableTotal +=
                (float) $row['daily_available_quantity'];

            $surplusRemainingTotal +=
                (float) $row['daily_remaining_quantity'];

            $surplusWasteTotal +=
                $this->numeric(
                    $row,
                    ['operational_waste']
                ) ?? 0;

            $surplusCarryoverTotal +=
                $this->numeric(
                    $row,
                    ['carryover_out']
                ) ?? 0;
        }

        $surplusRate = $surplusAvailableTotal > 0
            ? round(
                $surplusRemainingTotal
                    / $surplusAvailableTotal,
                4
            )
            : null;

        $operationalWasteRate = $surplusAvailableTotal > 0
            ? round(
                $surplusWasteTotal
                    / $surplusAvailableTotal,
                4
            )
            : null;

        $carryoverRate = $surplusAvailableTotal > 0
            ? round(
                $surplusCarryoverTotal
                    / $surplusAvailableTotal,
                4
            )
            : null;

        /*
        * 과잉 재고율 안내
        *
        * 최근 유효 기록이 최소 3개일 때만 판단합니다.
        *
        * 20%는 초기 검토 기준이며
        * 향후 제품별 실적 검증으로 조정할 수 있습니다.
        */
        if (
            $surplusRows->count() >= 3
            && $surplusRate !== null
            && $surplusRate >= 0.20
        ) {
            $reasons[] =
                '최근 생산·이월 재고 대비 남은 재고의 비율이 '
                . round($surplusRate * 100, 1)
                . '%로 확인되어 과잉 생산 가능성을 검토했습니다.';
        }

        /*
        * 적정 생산 달성 기록 분석
        *
        * 폐기와 이월을 합산한 잔여 수량을 사용합니다.
        *
        * 생산 규모에 따른 비율도 함께 고려해
        * 소량 생산과 대량 생산을 구분합니다.
        *
        * 정확히 1개 남은 날은 좋은 생산 결과로
        * 별도 집계합니다.
        *
        * 잔여 0개는 추가 판매 기회가 있었을
        * 가능성이 있으므로 별도 집계합니다.
        */
        $balanceRows = $surplusRows;

        $oneRemainingDays = 0;
        $zeroRemainingDays = 0;
        $lowSurplusDays = 0;

        foreach ($balanceRows as $row) {
            $available = (float) (
                $row['daily_available_quantity'] ?? 0
            );

            $remaining = (float) (
                $row['daily_remaining_quantity'] ?? 0
            );

            if ($available <= 0) {
                continue;
            }

            if ($remaining == 0) {
                $zeroRemainingDays++;
            }

            if ($remaining == 1) {
                $oneRemainingDays++;
            }

            /*
            * 잔여율 10% 이하는 초기 참고 기준입니다.
            * 제품별 실적 검증을 통해 조정할 수 있습니다.
            */
            $remainingRate =
                $remaining / $available;

            if ($remainingRate > 0 && $remainingRate <= 0.10) {
                $lowSurplusDays++;
            }
        }

        $balanceHistoryCount = $balanceRows->count();

        $lowSurplusAchievementRate =
            $balanceHistoryCount > 0
                ? round(
                    $lowSurplusDays / $balanceHistoryCount,
                    4
                )
                : null;

        /*
        * 최근 적정 생산 결과를 추천 근거에 표시합니다.
        */
        if (
            $balanceHistoryCount >= 3
            && $lowSurplusDays >= 2
        ) {
            $reasons[] =
                '최근 '
                . $balanceHistoryCount
                . '개 유효 기록 중 '
                . $lowSurplusDays
                . '개 기록에서 잔여 재고율이 '
                . '10% 이하로 관리되었습니다.';
        }

        // 날씨 보정
        $weatherCode = $this->targetWeatherCode(
            $conditions
        );

        if ($weatherCode !== null) {
            $weatherFactor = $this->weatherFactor(
                $valid,
                $weatherCode
            );

            if ($weatherFactor !== null) {
                $center = (int) round(
                    $center * $weatherFactor
                );

                $reasons[] = $this->message(
                    'weather_applied',
                    $targetDate
                );
            } else {
                $warnings[] = $this->message(
                    'weather_insufficient',
                    $targetDate
                );
            }
        }

        // 할인 행사 및 단체 주문
        $strongCondition = false;
        $groupOrderQuantity = 0;

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            if (
                ($condition['event_type'] ?? null)
                    === 'group_order'
            ) {
                $quantity = max(
                    0,
                    (int) ($condition['order_quantity'] ?? 0)
                );

                if ($quantity > 0) {
                    $groupOrderQuantity += $quantity;
                    $strongCondition = true;
                }
            }

            $discountType = $condition['discount_type']
                ?? null;

            $isStrongDiscount =
                $discountType === 'one_plus_one'
                || (
                    $discountType === 'percent'
                    && (float) (
                        $condition['discount_value'] ?? 0
                    ) >= 50
                );

            if ($isStrongDiscount) {
                $factor = $this->promotionFactor(
                    $cleanRows,
                    $valid,
                    $discountType
                );

                if ($factor !== null) {
                    $center = (int) round(
                        $center * $factor
                    );

                    $reasons[] = $this->message(
                        'promotion_applied',
                        $targetDate
                    );
                } else {
                    $fallbackFactor =
                        $discountType === 'one_plus_one'
                            ? 1.5
                            : 1.25;

                    $center = (int) round(
                        $center * $fallbackFactor
                    );

                    $reasons[] = $this->message(
                        'promotion_fallback',
                        $targetDate
                    );

                    $warnings[] = $this->message(
                        'promotion_warning',
                        $targetDate
                    );
                }

                $strongCondition = true;
            }
        }

        // 공휴일 보정
        $isHoliday = collect($conditions)->contains(
            fn ($condition) =>
                is_array($condition)
                && (
                    ($condition['is_holiday'] ?? false)
                    || ($condition['event_type'] ?? null)
                        === 'holiday'
                )
        );

        if ($isHoliday) {
            $holidayFactor = $this->holidayFactor(
                $cleanRows,
                $valid
            );

            if ($holidayFactor !== null) {
                $center = (int) round(
                    $center * $holidayFactor
                );

                $reasons[] = $this->message(
                    'holiday_applied',
                    $targetDate
                );
            } else {
                $warnings[] = $this->message(
                    'holiday_insufficient',
                    $targetDate
                );
            }
        }

        // 단체 주문은 할인·공휴일 배율에 곱하지 않고 마지막에 더합니다.
        if ($groupOrderQuantity > 0) {
            $center += $groupOrderQuantity;

            $reasons[] = $this->message(
                'group_order',
                $targetDate,
                ['quantity' => $groupOrderQuantity]
            );
        }

        /*
        * 추천 대상 날짜의 이월 입고 재고 반영
        *
        * 예상 수요와 신규 생산 필요량을 구분합니다.
        *
        * 과거 판매 추정량에서는 이월 재고를 포함해
        * 총 수요를 계산했으므로,
        * 추천 대상일에 이미 확보된 이월 재고만
        * 신규 생산 필요량에서 한 번 차감합니다.
        */
        $targetCarryoverIn = 0;

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            if (
                ($condition['condition_type'] ?? null)
                    !== 'target_inventory'
            ) {
                continue;
            }

            $targetCarryoverIn = max(
                0,
                (int) ($condition['carryover_in'] ?? 0)
            );

            break;
        }

        /*
        * 이월 차감 전 예상 총수요 보관
        *
        * 신규 생산 추천량은 이월 재고를 차감하지만,
        * 추천 범위의 불확실성은 총수요를 기준으로도
        * 평가할 수 있도록 유지합니다.
        */
        $expectedDemandBeforeCarryover = $center;

        if ($targetCarryoverIn > 0) {
            $center = max(
                0,
                $center - $targetCarryoverIn
            );

            $reasons[] =
                '당일 이월 입고 재고 '
                . $targetCarryoverIn
                . '개를 고려해 신규 생산 추천량을 조정했습니다.';
        }

        // 과거 추천 오차 계산
        $backtest = $this->backtest($valid);

        /*
        * 과잉 재고 감산 후보의 A/B 백테스트
        *
        * 결과만 계산하며 실제 추천량은 변경하지 않습니다.
        */
        $adjustmentBacktest =
            $this->surplusAdjustmentBacktest($valid);

        /*
        * 과잉 재고 보정 후보의 검증 결과 평가
        *
        * 최소 5개 검증 기록이 필요합니다.
        *
        * 평균 오차가 5% 이상 감소하고
        * 과소 추천 수량이 증가하지 않은 경우에만
        * 유망한 후보로 판단합니다.
        *
        * 이 판단은 실제 추천량에 자동 적용하지 않습니다.
        */
        $adjustmentAssessment = 'insufficient';

        $adjustmentCount = (int) (
            $adjustmentBacktest['count'] ?? 0
        );

        $adjustmentImprovement =
            $adjustmentBacktest['improvement_rate'] ?? null;

        $baseShortage =
            $adjustmentBacktest['base_shortage_total'] ?? null;

        $candidateShortage =
            $adjustmentBacktest['candidate_shortage_total'] ?? null;

        if (
            $adjustmentCount >= 5
            && $adjustmentImprovement !== null
            && $baseShortage !== null
            && $candidateShortage !== null
        ) {
            $hasImprovement =
                $adjustmentImprovement >= 0.05;

            $hasIncreasedShortage =
                $candidateShortage > $baseShortage;

            if ($hasIncreasedShortage) {
                $adjustmentAssessment = 'shortage_risk';
            } elseif ($hasImprovement) {
                $adjustmentAssessment = 'promising';
            } else {
                $adjustmentAssessment = 'not_better';
            }
        }

        /*
        * A/B 검증 결과 안내
        *
        * 실험 결과가 충분히 확보된 경우에만
        * 추천 근거에 표시합니다.
        */
        if ($adjustmentAssessment === 'promising') {
            $reasons[] =
                '과거 기록 검증에서 과잉 재고 보정 후보가 '
                . '기존 방식보다 좋은 결과를 보였습니다. '
                . '현재 추천량에는 아직 적용하지 않았습니다.';
        } elseif (
            $adjustmentAssessment === 'shortage_risk'
        ) {
            $warnings[] =
                '과잉 재고 보정 후보에서 과소 추천 위험이 '
                . '증가해 기존 추천 방식을 유지했습니다.';
        }

        /*
        * 과거 추천의 과잉·과소 방향 분석
        *
        * 검증 기록이 적으면 방향을 판단하지 않습니다.
        * 일시적인 오차를 과잉·과소 생산으로 단정하지 않습니다.
        */
        $backtestBias = $backtest['bias_rate'] ?? null;

        if (
            ($backtest['count'] ?? 0) >= 5
            && $backtestBias !== null
        ) {
            if ($backtestBias >= 0.15) {
                $reasons[] =
                    '과거 추천량이 실제 추정 수요보다 '
                    . '높게 계산되는 경향이 확인되었습니다.';
            } elseif ($backtestBias <= -0.15) {
                $reasons[] =
                    '과거 추천량이 실제 추정 수요보다 '
                    . '낮게 계산되는 경향이 확인되었습니다.';
            }
        }

        $demandValues = $valid
            ->take(-14)
            ->map(fn (array $row) =>
                (float) $this->demandValue($row)
            )
            ->all();

        $standardDeviation = $this->standardDeviation(
            $demandValues
        );

        // 추천 범위 계산
        // 동일 요일 기록이 충분하면 해당 요일의 표본 수를 우선 적용합니다.
        $evidenceCount =
            $baseline['same_weekday_count'] >= 2
                ? $baseline['same_weekday_count']
                : $valid->count();

        $margin = $this->recommendationMargin(
            max($center, $expectedDemandBeforeCarryover),
            $evidenceCount,
            $standardDeviation,
            $backtest
        );

        /*
        * 비정상적으로 높은 추천량이 조정되었는지
        * 상세 분석 화면에 전달하기 위한 상태
        */
        $highQuantityAdjusted = false;

        // 비정상적으로 높은 일반일 추천량 제한
        $recentAverage = (float) $valid
            ->take(-7)
            ->avg(fn (array $row) =>
                $this->demandValue($row)
            );

        if (
            !$strongCondition
            && !$isHoliday
            && $recentAverage > 0
            && $center > $recentAverage * 1.5
        ) {
            $center = (int) round(
                $recentAverage * 1.25
            );

            $highQuantityAdjusted = true;

            $margin = $this->recommendationMargin(
                max($center, $expectedDemandBeforeCarryover),
                $evidenceCount,
                $standardDeviation,
                $backtest
            );

            $reasons[] = $this->message(
                'high_quantity_adjusted',
                $targetDate
            );

            $warnings[] = $this->message(
                'high_quantity_warning',
                $targetDate
            );
        }

        $confidence = $this->confidence(
            $valid->count(),
            $salesCount,
            $baseline['same_weekday_count'],
            $backtest
        );

        if ($confidence === 'low') {
            $warnings[] = $this->message(
                'low_confidence',
                $targetDate
            );
        }

        $center = max(0, $center);

        /*
        * 추천 상세 화면용 계산 근거
        *
        * 기존 추천 결과와 분석 지표를 변경하지 않고
        * 사용자가 이해하기 쉬운 구조로 묶어 전달합니다.
        */
        $explanation = [
            'calculation' => [
                'base_demand' =>
                    $baseline['center'],

                'expected_demand_before_carryover' =>
                    $expectedDemandBeforeCarryover,

                'target_carryover_in' =>
                    $targetCarryoverIn,

                'recommended_production' =>
                    $center,

                'minimum_recommendation' =>
                    max(0, $center - $margin),

                'maximum_recommendation' =>
                    $center + $margin,

                'high_quantity_adjusted' =>
                    $highQuantityAdjusted,
            ],

            'history' => [
                'valid_record_count' =>
                    $valid->count(),

                'excluded_record_count' =>
                    $excludedCount,

                'same_weekday_count' =>
                    $baseline['same_weekday_count'],

                'analysis_record_count' =>
                    $baseline['analysis_record_count'],

                'analysis_source' =>
                    $baseline['analysis_source'],

                'recent_record_count' =>
                    $baseline['recent_record_count'],

                'older_record_count' =>
                    $baseline['older_record_count'],

                'recent_average' =>
                    $baseline['recent_average'],

                'older_average' =>
                    $baseline['older_average'],

                'recent_weight' =>
                    $baseline['applied_recent_weight'],

                'older_weight' =>
                    $baseline['applied_older_weight'],
            ],

            'inventory' => [
                'remaining_rate' =>
                    $surplusRate,

                'waste_rate' =>
                    $operationalWasteRate,

                'carryover_rate' =>
                    $carryoverRate,

                'one_remaining_days' =>
                    $oneRemainingDays,

                'zero_remaining_days' =>
                    $zeroRemainingDays,

                'low_surplus_days' =>
                    $lowSurplusDays,

                'low_surplus_achievement_rate' =>
                    $lowSurplusAchievementRate,

                'possible_sellout_days' =>
                    $possibleSelloutCount,
            ],

            'trend' => [
                'recent_trend' =>
                    $recentTrend,

                'recent_outlier' =>
                    $recentOutlier,
            ],

            'accuracy' => [
                'confidence' =>
                    $confidence,

                'tested_days' =>
                    $backtest['count'],

                'average_error_quantity' =>
                    $backtest['mae'],

                'overall_error_rate' =>
                    $backtest['wape'],

                'over_under_prediction_rate' =>
                    $backtest['bias_rate'],

                'adjustment_test' =>
                    $adjustmentBacktest,

                'adjustment_assessment' =>
                    $adjustmentAssessment,
            ],

            'notices' => [
                'reasons' =>
                    array_values(array_unique($reasons)),

                'warnings' =>
                    array_values(array_unique($warnings)),
            ],
        ];

        return [
            'center' => $center,
            'min' => max(0, $center - $margin),
            'max' => $center + $margin,
            'confidence' => $confidence,
            'explanation' => $explanation,
            'reasons' => array_values(array_unique($reasons)),
            'warning' => $warnings
                ? implode(' ', array_unique($warnings))
                : null,
            'metrics' => [
                'history_count' => $valid->count(),
                'excluded_history_count' => $excludedCount,
                'sales_count' => $salesCount,
                'estimated_sales_count' => $estimatedSalesCount,
                'confirmed_sales_count' => $confirmedSalesCount,
                'stockout_count' => $stockoutCount,
                'surplus_history_count' => $surplusRows->count(),
                'surplus_rate' => $surplusRate,
                'one_remaining_days' => $oneRemainingDays,
                'zero_remaining_days' => $zeroRemainingDays,
                'low_surplus_days' => $lowSurplusDays,
                'low_surplus_achievement_rate' => $lowSurplusAchievementRate,
                'operational_waste_rate' => $operationalWasteRate,
                'carryover_rate' => $carryoverRate,
                'same_weekday_count' => $baseline['same_weekday_count'],
                'backtest_count' => $backtest['count'],
                'mae' => $backtest['mae'],
                'wape' => $backtest['wape'],
                'bias_rate' => $backtest['bias_rate'],
                'surplus_adjustment_backtest' => $adjustmentBacktest,
                'surplus_adjustment_assessment' => $adjustmentAssessment,
                'recent_demand_outlier' => $recentOutlier,
                'calculation_version' => 'v3-adaptive-demand',
            ],
        ];
    }

    /**
     * 생산 추천 분석에 사용할 수 없는 기록인지 검사합니다.
     *
     * 기존 원 생산일 귀속 로스·폐기 검증을 유지하면서
     * 마감 상태와 당일 재고 수량 정합성을 추가 확인합니다.
     *
     * 필드가 없는 기존 기록과의 호환성은 유지합니다.
     */
    private function invalidHistoryRow(array $row): bool
    {
        /*
        * 마감되지 않은 날짜는 아직 수량이 변동될 수 있으므로
        * 확정된 과거 수요로 사용하지 않습니다.
        *
        * 마감 상태 필드가 없는 기존 데이터는
        * 기존 처리 방식을 유지합니다.
        */
        if (
            array_key_exists('closure_status', $row)
            && $row['closure_status'] !== 'closed'
        ) {
            return true;
        }

        /*
        * 당일 재고 흐름이 유효하지 않으면 제외합니다.
        *
        * 생산 + 이월 입고보다
        * 로스 + 폐기 + 이월 출고가 많은 경우입니다.
        */
        if (
            array_key_exists('daily_quantity_valid', $row)
            && $row['daily_quantity_valid'] !== true
        ) {
            return true;
        }

        /*
        * 기존 원 생산일 귀속 수량 검증
        *
        * 이월 제품을 나중에 폐기했더라도
        * 원 생산일에 귀속되는 기존 폐기율 정책은
        * 변경하지 않습니다.
        */
        $production = $this->numeric(
            $row,
            ['production']
        );

        if ($production === null) {
            return false;
        }

        $loss = $this->numeric(
            $row,
            ['attributed_loss', 'loss']
        ) ?? 0;

        $waste = $this->numeric(
            $row,
            ['attributed_waste', 'waste']
        ) ?? 0;

        return ($loss + $waste) > $production;
    }

    /**
     * 제품별 기본 수요를 계산합니다.
     *
     * 최대 12개월 유효 기록을 사용하며
     * 동일 요일 기록을 우선 분석합니다.
     *
     * 최근 3개월: 70%
     * 이전 9개월: 30%
     *
     * 한쪽 기간에 기록이 없으면
     * 실제 존재하는 기간의 데이터만 사용합니다.
     */
    private function baseline(
        Collection $rows,
        Carbon $targetDate
    ): array {
        $target = $targetDate->copy()->startOfDay();

        // 최대 12개월
        $historyFrom = $target->copy()
            ->subYear();

        // 최근 3개월 시작
        $recentFrom = $target->copy()
            ->subMonthsNoOverflow(3);

        /*
        * 추천 대상일 이전 최대 12개월의
        * 유효한 기록만 사용합니다.
        */
        $history = $rows
            ->filter(
                fn (array $row) =>
                    !empty($row['date']) &&
                    Carbon::parse($row['date'])
                        ->gte($historyFrom) &&
                    Carbon::parse($row['date'])
                        ->lt($target) &&
                    $this->demandValue($row) !== null
            )
            ->sortBy('date')
            ->values();

        /*
        * 추천 대상일과 동일한 요일을 추출합니다.
        */
        $sameWeekday = $history
            ->filter(
                fn (array $row) =>
                    Carbon::parse($row['date'])->dayOfWeek
                        === $target->dayOfWeek
            )
            ->values();

        $sameCount = $sameWeekday->count();

        /*
        * 동일 요일 기록이 2개 이상이면 우선 사용합니다.
        *
        * 부족하면 다른 정상 영업일의 기록으로
        * 기본 수요를 추정합니다.
        */
        $analysisRows = $sameCount >= 2
            ? $sameWeekday
            : $history;

        /*
        * 최근 3개월 기록
        */
        $recentRows = $analysisRows
            ->filter(
                fn (array $row) =>
                    Carbon::parse($row['date'])
                        ->gte($recentFrom)
            )
            ->values();

        /*
        * 이전 9개월 기록
        */
        $olderRows = $analysisRows
            ->filter(
                fn (array $row) =>
                    Carbon::parse($row['date'])
                        ->lt($recentFrom)
            )
            ->values();

        /*
        * 기존 가중 평균 함수를 재사용합니다.
        *
        * 각 기간 내부에서도 최근 기록을
        * 조금 더 중요하게 평가합니다.
        */
        $recentAverage = $recentRows->isNotEmpty()
            ? $this->weightedAverage($recentRows)
            : null;

        $olderAverage = $olderRows->isNotEmpty()
            ? $this->weightedAverage($olderRows)
            : null;

        /*
        * 최근 3개월 70% + 이전 9개월 30%
        *
        * 두 기간에 모두 유효 기록이 있을 때
        * 지정한 가중치를 적용합니다.
        */
        if (
            $recentAverage !== null &&
            $olderAverage !== null
        ) {
            $center =
                $recentAverage * 0.70 +
                $olderAverage * 0.30;
        }

        /*
        * 최근 3개월 기록만 있는 경우
        */
        elseif ($recentAverage !== null) {
            $center = $recentAverage;
        }

        /*
        * 이전 9개월 기록만 있는 경우
        */
        elseif ($olderAverage !== null) {
            $center = $olderAverage;
        }

        /*
        * 유효한 기록이 없는 경우
        */
        else {
            $center = 0;
        }

        return [
            'center' => max(
                0,
                (int) round($center)
            ),

            'same_weekday_count' => $sameCount,

            'analysis_record_count' =>
                $analysisRows->count(),

            'analysis_source' =>
                $sameCount >= 2
                    ? 'same_weekday'
                    : 'normal_business_days',

            'recent_record_count' =>
                $recentRows->count(),

            'older_record_count' =>
                $olderRows->count(),

            'recent_average' =>
                $recentAverage !== null
                    ? round($recentAverage, 2)
                    : null,

            'older_average' =>
                $olderAverage !== null
                    ? round($olderAverage, 2)
                    : null,

            /*
            * 실제 적용된 기간별 비중
            *
            * 양쪽 기간 모두 기록이 있을 때만
            * 최근 70% / 이전 30%를 적용합니다.
            */
            'applied_recent_weight' =>
                $recentAverage !== null
                    ? (
                        $olderAverage !== null
                            ? 0.70
                            : 1.00
                    )
                    : 0.00,

            'applied_older_weight' =>
                $olderAverage !== null
                    ? (
                        $recentAverage !== null
                            ? 0.30
                            : 1.00
                    )
                    : 0.00,
        ];
    }

    /**
     * 최근 수요의 일시적인 이상 변동 가능성 확인
     *
     * 우선 동일 요일 기록을 활용합니다.
     * 동일 요일 기록이 충분하지 않다면
     * 전체 정상 영업일 기록을 사용합니다.
     *
     * 최근 기록 1개를 이전 6개와 비교합니다.
     *
     * 단일 이상값을 자동 제거하지 않고
     * 검토가 필요한지 여부만 반환합니다.
     */
    private function recentDemandOutlier(
        Collection $rows,
        Carbon $targetDate
    ): ?array {
        $sameWeekday = $rows
            ->filter(
                fn (array $row) =>
                    Carbon::parse($row['date'])->dayOfWeek
                        === $targetDate->dayOfWeek
            )
            ->sortBy('date')
            ->values();

        $useSameWeekday =
            $sameWeekday->count() >= 7;

        $analysisRows = $useSameWeekday
            ? $sameWeekday
            : $rows->sortBy('date')->values();

        $recentRows = $analysisRows
            ->take(-7)
            ->values();

        if ($recentRows->count() < 7) {
            return null;
        }

        $latestRow = $recentRows->last();

        /*
        * 오래전 기록을 최근 이상치로 표시하지 않습니다.
        */
        $latestDate = Carbon::parse(
            $latestRow['date']
        );

        $maximumAge = $useSameWeekday
            ? 35
            : 14;

        if (
            $latestDate->diffInDays($targetDate)
                > $maximumAge
        ) {
            return null;
        }

        $referenceValues = $recentRows
            ->take(6)
            ->map(
                fn (array $row) =>
                    (float) $this->demandValue($row)
            );

        $latestValue = (float) $this->demandValue(
            $latestRow
        );

        $median = (float) $referenceValues->median();

        if ($median <= 0) {
            return null;
        }

        /*
        * 이전 기록의 중앙값으로부터
        * 얼마나 떨어져 있는지 계산합니다.
        */
        $deviations = $referenceValues
            ->map(
                fn (float $value) =>
                    abs($value - $median)
            );

        $mad = (float) $deviations->median();

        $minimumVariation = max(
            1.0,
            $mad
        );

        $upperLimit = max(
            $median * 2.5,
            $median + 3 * $minimumVariation
        );

        $lowerLimit = max(
            0,
            min(
                $median / 3,
                $median - 3 * $minimumVariation
            )
        );

        if ($latestValue > $upperLimit) {
            return [
                'type' => 'unusually_high',
                'date' => $latestDate->toDateString(),
                'quantity' => $latestValue,
                'reference_median' => $median,
            ];
        }

        if ($latestValue < $lowerLimit) {
            return [
                'type' => 'unusually_low',
                'date' => $latestDate->toDateString(),
                'quantity' => $latestValue,
                'reference_median' => $median,
            ];
        }

        return null;
    }

    /**
     * 동일 요일의 지속적인 수요 변화 분석
     *
     * 최근 동일 요일 6개 기록을 사용합니다.
     *
     * 이전 3회와 최근 3회의 중앙값을 비교하여
     * 일시적인 급증·급감의 영향을 줄입니다.
     *
     * 최근 3회 중 최소 2회가 이전 중앙값보다
     * 같은 방향으로 움직인 경우만 추세로 판단합니다.
     *
     * 추세 판단 결과는 추천 근거로 사용하며
     * 여기서 추천량을 직접 증감하지 않습니다.
     */
    private function recentDemandTrend(
        Collection $rows,
        Carbon $targetDate
    ): ?array {
        $targetWeekday = $targetDate->dayOfWeek;

        $sameWeekdayRows = $rows
            ->filter(
                fn (array $row) =>
                    Carbon::parse($row['date'])->dayOfWeek
                        === $targetWeekday
                    && $this->demandValue($row) !== null
            )
            ->sortBy('date')
            ->take(-6)
            ->values();

        if ($sameWeekdayRows->count() < 6) {
            return null;
        }

        $olderValues = $sameWeekdayRows
            ->take(3)
            ->map(
                fn (array $row) =>
                    (float) $this->demandValue($row)
            );

        $recentValues = $sameWeekdayRows
            ->skip(3)
            ->map(
                fn (array $row) =>
                    (float) $this->demandValue($row)
            );

        $olderMedian = (float) $olderValues->median();
        $recentMedian = (float) $recentValues->median();

        if ($olderMedian <= 0) {
            return null;
        }

        $changeRate =
            ($recentMedian - $olderMedian)
            / $olderMedian;

        // 일시적인 작은 변동은 추세로 판단하지 않습니다.
        if (abs($changeRate) < 0.15) {
            return null;
        }

        $direction = $changeRate > 0
            ? 'increasing'
            : 'decreasing';

        $consistentCount = $recentValues
            ->filter(
                fn (float $value) =>
                    $direction === 'increasing'
                        ? $value > $olderMedian
                        : $value < $olderMedian
            )
            ->count();

        if ($consistentCount < 2) {
            return null;
        }

        return [
            'direction' => $direction,
            'change_rate' => round(
                $changeRate,
                4
            ),
            'sample_count' => 6,
        ];
    }

    private function weightedAverage(
        Collection $rows
    ): float {
        $weighted = 0.0;
        $weightSum = 0.0;

        foreach (
            $rows->reverse()->values() as $index => $row
        ) {
            $value = $this->demandValue($row);

            if ($value === null) {
                continue;
            }

            $weight = pow(
                self::WEIGHT_DECAY,
                $index
            );

            $weighted += $value * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0
            ? $weighted / $weightSum
            : 0.0;
    }

    /**
     * 실제 수요 필드가 있으면 사용합니다.
     * 그렇지 않으면 생산·로스·폐기·이월로 추정합니다.
     */
    private function demandValue(
        array $row
    ): ?float {
        $sales = $this->salesValue($row);

        if ($sales !== null) {
            return $sales;
        }

        $production = $this->numeric(
            $row,
            ['production']
        );

        if ($production === null) {
            return null;
        }

        $loss = $this->numeric(
            $row,
            ['attributed_loss', 'loss']
        ) ?? 0;

        $waste = $this->numeric(
            $row,
            ['attributed_waste', 'waste']
        ) ?? 0;

        $carryover = $this->numeric(
            $row,
            ['carryover_out']
        ) ?? 0;

        $sellableProduction = max(
            0,
            $production - $loss
        );

        // 폐기·이월에 따른 추가 차감은 최대 15%입니다.
        $estimatedOversupply = min(
            $sellableProduction * 0.15,
            max(0, $waste + $carryover)
        );

        return max(
            0,
            $sellableProduction - $estimatedOversupply
        );
    }

    private function salesValue(
        array $row
    ): ?float {
        return $this->numeric(
            $row,
            [
                'sales_quantity',
                'sold_quantity',
                'sales',
                'sold',
            ]
        );
    }

    private function numeric(
        array $row,
        array $keys
    ): ?float {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;

            if (
                $value !== null
                && $value !== ''
                && is_numeric($value)
                && (float) $value >= 0
            ) {
                return (float) $value;
            }
        }

        return null;
    }

    private function isStockout(
        array $row
    ): bool {
        return filter_var(
            $row['stockout']
                ?? $row['sold_out']
                ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function targetWeatherCode(
        array $conditions
    ): ?string {
        foreach ($conditions as $condition) {
            if (
                is_array($condition)
                && !empty($condition['weather_code'])
            ) {
                return (string) $condition['weather_code'];
            }
        }

        return null;
    }

    private function weatherFactor(
        Collection $normal,
        string $weatherCode
    ): ?float {
        $known = $normal
            ->filter(fn (array $row) =>
                !empty($row['weather_code'])
            );

        $matched = $known
            ->filter(fn (array $row) =>
                (string) $row['weather_code']
                    === $weatherCode
            );

        $other = $known
            ->reject(fn (array $row) =>
                (string) $row['weather_code']
                    === $weatherCode
            );

        if (
            $matched->count() < 3
            || $other->count() < 3
        ) {
            return null;
        }

        $matchedAverage = $matched->avg(
            fn (array $row) =>
                $this->demandValue($row)
        );

        $otherAverage = $other->avg(
            fn (array $row) =>
                $this->demandValue($row)
        );

        if ($otherAverage <= 0) {
            return null;
        }

        return $this->clamp(
            $matchedAverage / $otherAverage,
            0.95,
            1.05
        );
    }

    private function promotionFactor(
        Collection $allRows,
        Collection $normal,
        string $discountType
    ): ?float {
        $matched = $allRows
            ->filter(fn (array $row) =>
                ($row['discount_type'] ?? null)
                    === $discountType
                && $this->demandValue($row) !== null
            );

        if (
            $matched->count() < 2
            || $normal->count() < 3
        ) {
            return null;
        }

        $regularAverage = $normal->avg(
            fn (array $row) =>
                $this->demandValue($row)
        );

        $eventAverage = $matched->avg(
            fn (array $row) =>
                $this->demandValue($row)
        );

        if ($regularAverage <= 0) {
            return null;
        }

        return $this->clamp(
            $eventAverage / $regularAverage,
            0.80,
            2.00
        );
    }

    private function holidayFactor(
        Collection $allRows,
        Collection $normal
    ): ?float {
        $holidays = $allRows
            ->filter(fn (array $row) =>
                ($row['is_holiday'] ?? false) === true
                && $this->demandValue($row) !== null
            );

        if (
            $holidays->count() < 3
            || $normal->count() < 3
        ) {
            return null;
        }

        $regularAverage = $normal->avg(
            fn (array $row) =>
                $this->demandValue($row)
        );

        $holidayAverage = $holidays->avg(
            fn (array $row) =>
                $this->demandValue($row)
        );

        if ($regularAverage <= 0) {
            return null;
        }

        return $this->clamp(
            $holidayAverage / $regularAverage,
            0.80,
            1.30
        );
    }

    /**
     * 과거 기록을 이용한 추천 정확도 검증
     *
     * 각 날짜의 추천량을 해당 날짜보다 이전의
     * 기록만으로 계산합니다.
     *
     * MAE: 평균 절대 오차
     * WAPE: 전체 수요 대비 절대 오차 비율
     * bias_rate: 과잉·과소 추천 방향
     *
     * bias_rate > 0 : 실제 추정 수요보다 많이 추천
     * bias_rate < 0 : 실제 추정 수요보다 적게 추천
     *
     * 여기서 실제 수요는 별도 판매 입력값이 아닌
     * 기존 재고 기반 자동 추정 판매량일 수 있습니다.
     */
    private function backtest(
        Collection $normal
    ): array {
        $rows = $normal->values();

        $errors = [];
        $actualTotal = 0.0;
        $predictionTotal = 0.0;

        $start = max(
            self::MIN_HISTORY,
            $rows->count() - self::BACKTEST_DAYS
        );

        for (
            $i = $start;
            $i < $rows->count();
            $i++
        ) {
            $actualRow = $rows[$i];

            $past = $rows
                ->slice(0, $i)
                ->values();

            if ($past->count() < self::MIN_HISTORY) {
                continue;
            }

            $actual = $this->demandValue(
                $actualRow
            );

            if ($actual === null) {
                continue;
            }

            $prediction = (float) $this->baseline(
                $past,
                Carbon::parse($actualRow['date'])
            )['center'];

            $errors[] = abs(
                $prediction - $actual
            );

            $actualTotal += $actual;
            $predictionTotal += $prediction;
        }

        $count = count($errors);

        if ($count === 0) {
            return [
                'count' => 0,
                'mae' => null,
                'wape' => null,
                'bias_rate' => null,
            ];
        }

        $absoluteError = array_sum($errors);

        return [
            'count' => $count,

            'mae' => round(
                $absoluteError / $count,
                2
            ),

            'wape' => $actualTotal > 0
                ? round(
                    $absoluteError / $actualTotal,
                    4
                )
                : null,

            'bias_rate' => $actualTotal > 0
                ? round(
                    ($predictionTotal - $actualTotal)
                        / $actualTotal,
                    4
                )
                : null,
        ];
    }

    /**
     * 과잉 재고 보정 후보의 과거 성능 비교
     *
     * A: 기존 기본 추천량
     * B: 이전 기록의 과잉 재고율이 20% 이상이면
     *    기본 추천량을 5% 감산하는 실험 후보
     *
     * 각 검증 날짜보다 과거의 기록만 사용합니다.
     * 두 방식 모두 동일한 날짜를 대상으로 평가합니다.
     *
     * 이 함수는 추천량을 변경하지 않습니다.
     */
    private function surplusAdjustmentBacktest(
        Collection $normal
    ): array {
        $rows = $normal->values();

        $baseErrors = [];
        $candidateErrors = [];

        $baseShortageTotal = 0.0;
        $candidateShortageTotal = 0.0;

        $start = max(
            self::MIN_HISTORY,
            $rows->count() - self::BACKTEST_DAYS
        );

        for (
            $i = $start;
            $i < $rows->count();
            $i++
        ) {
            $actualRow = $rows[$i];

            $past = $rows
                ->slice(0, $i)
                ->values();

            if ($past->count() < self::MIN_HISTORY) {
                continue;
            }

            $actual = $this->demandValue(
                $actualRow
            );

            if ($actual === null) {
                continue;
            }

            /*
            * 검증일 이전의 최근 유효 재고 기록만
            * 과잉 재고 비율 계산에 사용합니다.
            */
            $inventoryRows = $past
                ->filter(function (array $row): bool {
                    $available = $this->numeric(
                        $row,
                        ['daily_available_quantity']
                    );

                    $remaining = $this->numeric(
                        $row,
                        ['daily_remaining_quantity']
                    );

                    return
                        ($row['daily_quantity_valid'] ?? false)
                            === true
                        && $available !== null
                        && $available > 0
                        && $remaining !== null;
                })
                ->take(-7)
                ->values();

            if ($inventoryRows->count() < 3) {
                continue;
            }

            $availableTotal = 0.0;
            $remainingTotal = 0.0;

            foreach ($inventoryRows as $row) {
                $availableTotal +=
                    (float) $row['daily_available_quantity'];

                $remainingTotal +=
                    (float) $row['daily_remaining_quantity'];
            }

            if ($availableTotal <= 0) {
                continue;
            }

            $surplusRate =
                $remainingTotal / $availableTotal;

            /*
            * 이번 실험은 실제로 감산 조건을 충족한
            * 날짜에서만 A와 B를 비교합니다.
            */
            if ($surplusRate < 0.20) {
                continue;
            }

            $basePrediction = (float) $this->baseline(
                $past,
                Carbon::parse($actualRow['date'])
            )['center'];

            $candidatePrediction = max(
                0,
                (float) round($basePrediction * 0.95)
            );

            $baseErrors[] = abs(
                $basePrediction - $actual
            );

            $candidateErrors[] = abs(
                $candidatePrediction - $actual
            );

            /*
            * 실제 추정 수요보다 적게 추천한 수량입니다.
            * 실제 결품이 확정되었다는 의미는 아닙니다.
            */
            $baseShortageTotal += max(
                0,
                $actual - $basePrediction
            );

            $candidateShortageTotal += max(
                0,
                $actual - $candidatePrediction
            );
        }

        $count = count($baseErrors);

        if ($count === 0) {
            return [
                'count' => 0,
                'base_mae' => null,
                'candidate_mae' => null,
                'base_shortage_total' => null,
                'candidate_shortage_total' => null,
                'improvement_rate' => null,
            ];
        }

        $baseMae = array_sum($baseErrors) / $count;

        $candidateMae =
            array_sum($candidateErrors) / $count;

        return [
            'count' => $count,

            'base_mae' => round(
                $baseMae,
                2
            ),

            'candidate_mae' => round(
                $candidateMae,
                2
            ),

            'base_shortage_total' => round(
                $baseShortageTotal,
                2
            ),

            'candidate_shortage_total' => round(
                $candidateShortageTotal,
                2
            ),

            'improvement_rate' => $baseMae > 0
                ? round(
                    ($baseMae - $candidateMae)
                        / $baseMae,
                    4
                )
                : null,
        ];
    }

    /**
     * 기록 수와 실제 변동성에 따른 추천 범위
     *
     * 기록 1개는 초기 참고 수준이므로
     * 중심값의 ±100%까지 넓은 범위를 허용합니다.
     *
     * 기록이 쌓이면 기본 불확실성을 줄이되
     * 실제 변동성과 백테스트 오차는 유지합니다.
     */
    private function recommendationMargin(
        int $center,
        int $historyCount,
        float $standardDeviation,
        array $backtest
    ): int {
        if ($historyCount <= 1) {
            $minimumRate = 1.00;
        } elseif ($historyCount <= 2) {
            $minimumRate = 0.50;
        } elseif ($historyCount <= 4) {
            $minimumRate = 0.35;
        } elseif ($historyCount <= 7) {
            $minimumRate = 0.25;
        } elseif ($historyCount <= 15) {
            $minimumRate = 0.15;
        } else {
            $minimumRate = 0.08;
        }

        $sampleMargin = (int) ceil(
            max(0, $center) * $minimumRate
        );

        $variationMargin = (int) ceil(
            max(0, $standardDeviation)
        );

        $backtestMargin = (int) ceil(
            max(0, (float) ($backtest['mae'] ?? 0))
        );

        return max(
            1,
            $sampleMargin,
            $variationMargin,
            $backtestMargin
        );
    }

    private function standardDeviation(
        array $values
    ): float {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $average = array_sum($values) / $count;
        $variance = 0.0;

        foreach ($values as $value) {
            $variance += pow(
                $value - $average,
                2
            );
        }

        return sqrt($variance / $count);
    }

    /**
     * 추천 신뢰도 판단
     *
     * 전체 유효 기록 수와 동일 요일 기록 수를 함께 확인합니다.
     * 과거 예측 결과가 충분하고 오차가 낮을 때만
     * 일반 신뢰도로 판단합니다.
     *
     * 실제 판매 입력 없이 계산된 추정 판매량도
     * 유효한 관측값으로 사용할 수 있지만,
     * 동일 요일 표본과 백테스트 조건을 생략하지 않습니다.
     */
    private function confidence(
        int $historyCount,
        int $salesCount,
        int $sameWeekdayCount,
        array $backtest
    ): string {
        if ($historyCount < self::MIN_HISTORY) {
            return 'insufficient';
        }

        $backtestCount = (int) (
            $backtest['count'] ?? 0
        );

        $wape = $backtest['wape'] ?? null;

        // 충분한 과거 기록과 동일 요일 근거가 필요합니다.
        $enoughHistory =
            $historyCount >= 8
            && $sameWeekdayCount >= 4
            && $salesCount >= 5;

        // 과거 추천의 실제 오차도 검증합니다.
        $reliableBacktest =
            $backtestCount >= 5
            && $wape !== null
            && is_numeric($wape)
            && (float) $wape <= 0.25;

        if ($enoughHistory && $reliableBacktest) {
            return 'normal';
        }

        return 'low';
    }

    private function clamp(
        float $value,
        float $minimum,
        float $maximum
    ): float {
        return max(
            $minimum,
            min($maximum, $value)
        );
    }

    /**
     * 생산 기록에서 참고한 추천 결과를 저장합니다.
     *
     * 기존 추천 스냅샷 데이터 구조를 유지하면서
     * 분석 기간을 최근 최대 12개월로 통일합니다.
     */
    public function snapshot(
        int $storeId,
        int $productId,
        string $date,
        array $recommendation,
        ?int $batchId = null,
        ?string $deviationReason = null,
        bool $referenced = true,
    ): ProductionRecommendationSnapshot {
        $targetDate = Carbon::parse($date);

        return ProductionRecommendationSnapshot::create([
            'store_id' => $storeId,
            'product_id' => $productId,
            'production_batch_id' => $batchId,
            'target_date' => $date,

            'center_quantity' =>
                $recommendation['center'],

            'range_min' =>
                $recommendation['min'],

            'range_max' =>
                $recommendation['max'],

            'confidence' =>
                $recommendation['confidence'],

            'calculation_version' =>
                'v3-adaptive-demand',

            'reasons' =>
                $recommendation['reasons'],

            'excluded_conditions' => [],

            /*
            * 추천 대상일 이전 최대 12개월
            *
            * 컨트롤러의 추천 데이터 수집 기간과
            * 동일한 날짜 계산을 사용합니다.
            */
            'data_from' => $targetDate->copy()
                ->subYear()
                ->toDateString(),

            'data_to' => $targetDate->copy()
                ->subDay()
                ->toDateString(),

            'referenced' => $referenced,

            'deviation_reason' =>
                $deviationReason,

            'generated_at' => now(),
        ]);
    }
}