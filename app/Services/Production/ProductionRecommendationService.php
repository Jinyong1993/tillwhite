<?php

namespace App\Services\Production;

use App\Models\ProductionRecommendationSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductionRecommendationService
{
    private const MAX_HISTORY = 90;
    private const WEIGHT_DECAY = 0.85;
    private const MIN_HISTORY = 3;
    private const BACKTEST_DAYS = 8;

    // 같은 날짜와 상황에서는 동일한 문구를 표시합니다.
    private const MESSAGES = [
        'insufficient' => [
            '생산 기록이 부족해 추천 수량을 계산할 수 없습니다.',
            '생산 기록이 조금 더 쌓이면 추천해 드릴 수 있습니다.',
            '아직 기록이 충분하지 않아 추천 수량을 안내하기 어렵습니다.',
        ],
        'demand_sales' => [
            '최근 판매 흐름을 기준으로 추천 수량을 계산했습니다.',
            '최근 판매량과 생산량을 함께 참고했습니다.',
            '최근 판매 추세를 반영해 추천 수량을 정했습니다.',
        ],
        'demand_estimated' => [
            '최근 생산량과 로스·폐기·이월 수량을 참고했습니다.',
            '생산량에서 로스와 남은 재고를 고려해 계산했습니다.',
            '최근 생산 기록을 분석해 필요한 수량을 계산했습니다.',
        ],
        'weekday_enough' => [
            '같은 요일의 기록을 참고해 추천 수량을 계산했습니다.',
            '최근 기록과 같은 요일의 생산 흐름을 비교했습니다.',
            '같은 요일의 생산 패턴을 추천 수량에 반영했습니다.',
        ],
        'weekday_lacking' => [
            '같은 요일의 기록이 부족해 최근 기록을 참고했습니다.',
            '요일별 기록이 적어 최근 생산 흐름을 기준으로 계산했습니다.',
            '같은 요일의 자료가 부족해 전체 생산 기록을 참고했습니다.',
        ],
        'stockout' => [
            '최근 품절된 날이 있어 생산량이 부족했을 수 있습니다.',
            '품절 기록이 있어 실제 필요한 수량은 더 많을 수 있습니다.',
            '최근 품절된 날을 고려하면 추가 생산이 필요할 수 있습니다.',
        ],
        'inventory_adjusted' => [
            '폐기와 이월 수량을 반영해 추천량을 조정했습니다.',
            '남은 재고를 고려해 추천 수량을 계산했습니다.',
            '폐기·이월 기록을 참고해 생산량을 조정했습니다.',
        ],
        'weather_applied' => [
            '비슷한 날씨의 생산 기록을 참고했습니다.',
            '과거 같은 날씨의 수요를 추천량에 반영했습니다.',
            '날씨에 따른 생산 흐름을 고려해 수량을 조정했습니다.',
        ],
        'weather_insufficient' => [
            '날씨 기록이 부족해 날씨 보정은 적용하지 않았습니다.',
            '비슷한 날씨의 자료가 적어 기본 추천량을 유지했습니다.',
            '날씨에 따른 차이를 확인하기 어려워 추가 조정은 하지 않았습니다.',
        ],
        'group_order' => [
            '단체 주문 {quantity}개를 추천 수량에 추가했습니다.',
            '단체 주문 {quantity}개를 포함해 계산했습니다.',
            '단체 주문을 고려해 {quantity}개를 추가했습니다.',
        ],
        'promotion_applied' => [
            '과거 비슷한 할인 행사를 참고해 수량을 조정했습니다.',
            '이전 할인 행사 기록을 추천량에 반영했습니다.',
            '비슷한 행사 때의 수요를 참고해 계산했습니다.',
        ],
        'promotion_fallback' => [
            '행사 기록이 부족해 기본 행사 기준으로 계산했습니다.',
            '비슷한 행사 자료가 적어 임시 기준을 적용했습니다.',
            '과거 행사 기록이 충분하지 않아 기본 보정값을 사용했습니다.',
        ],
        'promotion_warning' => [
            '행사 추천량은 임시 기준으로 계산한 수량입니다.',
            '행사 기록이 부족하니 추천 수량을 참고용으로 확인해 주세요.',
            '행사 수요를 정확히 판단하기 어려워 주의가 필요합니다.',
        ],
        'holiday_applied' => [
            '과거 공휴일의 생산 기록을 참고했습니다.',
            '공휴일의 수요 흐름을 추천 수량에 반영했습니다.',
            '이전 공휴일 기록을 기준으로 수량을 조정했습니다.',
        ],
        'holiday_insufficient' => [
            '공휴일 기록이 부족해 추가 조정은 하지 않았습니다.',
            '공휴일 자료가 적어 기본 추천량을 유지했습니다.',
            '과거 공휴일 기록이 충분하지 않아 별도 보정은 하지 않았습니다.',
        ],
        'high_quantity_adjusted' => [
            '추천량이 평소보다 높아 최근 평균에 맞춰 조정했습니다.',
            '생산량이 너무 높게 계산되어 수량을 낮췄습니다.',
            '최근 생산 흐름을 고려해 추천 수량을 줄였습니다.',
        ],
        'high_quantity_warning' => [
            '추천 수량이 높게 계산되어 안전하게 조정했습니다.',
            '추천량을 낮췄으니 실제 생산 상황도 확인해 주세요.',
            '평소보다 높은 수량이 예상되어 보수적으로 조정했습니다.',
        ],
        'low_confidence' => [
            '추천 정확도가 아직 낮으니 참고용으로 확인해 주세요.',
            '과거 기록의 차이가 커서 추천 수량은 참고용입니다.',
            '현재 추천 수량은 정확도가 낮아 현장 판단이 필요합니다.',
        ],
        'invalid_history' => [
            '수량이 맞지 않는 생산 기록 {count}건을 제외했습니다.',
            '확인이 필요한 생산 기록 {count}건은 계산에서 제외했습니다.',
            '생산 수량에 차이가 있는 기록 {count}건을 제외하고 계산했습니다.',
        ],
    ];

    private function message(
        string $key,
        Carbon $date,
        array $replacements = []
    ): string {
        $patterns = self::MESSAGES[$key] ?? [];

        if (!$patterns) {
            return '';
        }

        $hash = (int) sprintf(
            '%u',
            crc32($date->toDateString() . ':' . $key)
        );

        $selected = $patterns[$hash % count($patterns)];

        foreach ($replacements as $name => $value) {
            $selected = str_replace(
                '{' . $name . '}',
                (string) $value,
                $selected
            );
        }

        return $selected;
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

        $salesCount = $valid
            ->filter(fn (array $row) =>
                $this->salesValue($row) !== null
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
                $salesCount > 0
                    ? 'demand_sales'
                    : 'demand_estimated',
                $targetDate
            ),
            $this->message(
                $baseline['same_weekday_count'] >= 2
                    ? 'weekday_enough'
                    : 'weekday_lacking',
                $targetDate
            ),
        ];

        // 품절 여부
        $stockoutCount = $valid
            ->take(-7)
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

        // 폐기·이월 수량이 추정에 사용되었는지 확인
        $adjustedCount = $valid
            ->filter(function (array $row) {
                return $this->salesValue($row) === null
                    && (
                        (
                            $this->numeric(
                                $row,
                                ['attributed_waste', 'waste']
                            ) ?? 0
                        ) > 0
                        || (
                            $this->numeric(
                                $row,
                                ['carryover_out']
                            ) ?? 0
                        ) > 0
                    );
            })
            ->count();

        if ($adjustedCount > 0) {
            $reasons[] = $this->message(
                'inventory_adjusted',
                $targetDate
            );
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

        // 과거 추천 오차 계산
        $backtest = $this->backtest($valid);

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
        $margin = max(
            1,
            (int) ceil($center * 0.08),
            (int) ceil($standardDeviation),
            (int) ceil($backtest['mae'] ?? 0)
        );

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

            $margin = max(
                1,
                (int) ceil($center * 0.08),
                (int) ceil($standardDeviation),
                (int) ceil($backtest['mae'] ?? 0)
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
            $backtest
        );

        if ($confidence === 'low') {
            $warnings[] = $this->message(
                'low_confidence',
                $targetDate
            );
        }

        $center = max(0, $center);

        return [
            'center' => $center,
            'min' => max(0, $center - $margin),
            'max' => $center + $margin,
            'confidence' => $confidence,
            'reasons' => array_values(array_unique($reasons)),
            'warning' => $warnings
                ? implode(' ', array_unique($warnings))
                : null,
            'metrics' => [
                'history_count' => $valid->count(),
                'excluded_history_count' => $excludedCount,
                'sales_count' => $salesCount,
                'stockout_count' => $stockoutCount,
                'same_weekday_count' =>
                    $baseline['same_weekday_count'],
                'backtest_count' => $backtest['count'],
                'mae' => $backtest['mae'],
                'wape' => $backtest['wape'],
                'calculation_version' =>
                    'v3-adaptive-demand',
            ],
        ];
    }

    /**
     * 생산량보다 귀속 로스와 폐기량의 합계가
     * 많은 기록은 추천 분석에서 제외합니다.
     */
    private function invalidHistoryRow(array $row): bool
    {
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

    private function baseline(
        Collection $rows,
        Carbon $targetDate
    ): array {
        $sameWeekday = $rows
            ->filter(fn (array $row) =>
                Carbon::parse($row['date'])->dayOfWeek
                    === $targetDate->dayOfWeek
            )
            ->values();

        $recentAverage = $this->weightedAverage(
            $rows->take(-14)
        );

        $sameCount = $sameWeekday->count();

        if ($sameCount >= 2) {
            $weekdayAverage = $this->weightedAverage(
                $sameWeekday->take(-8)
            );

            $weekdayWeight = $sameCount >= 4
                ? 0.75
                : 0.60;

            $center =
                $weekdayAverage * $weekdayWeight
                + $recentAverage * (1 - $weekdayWeight);
        } else {
            $center = $recentAverage;
        }

        return [
            'center' => max(0, (int) round($center)),
            'same_weekday_count' => $sameCount,
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
            0.85,
            1.15
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

    private function backtest(
        Collection $normal
    ): array {
        $rows = $normal->values();
        $errors = [];
        $actualTotal = 0.0;

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
            $past = $rows->slice(0, $i)->values();

            if ($past->count() < self::MIN_HISTORY) {
                continue;
            }

            $prediction = $this->baseline(
                $past,
                Carbon::parse($actualRow['date'])
            )['center'];

            $actual = $this->demandValue($actualRow);

            if ($actual === null) {
                continue;
            }

            $errors[] = abs($prediction - $actual);
            $actualTotal += $actual;
        }

        $count = count($errors);

        if ($count === 0) {
            return [
                'count' => 0,
                'mae' => null,
                'wape' => null,
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
        ];
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

    private function confidence(
        int $historyCount,
        int $salesCount,
        array $backtest
    ): string {
        if ($historyCount < self::MIN_HISTORY) {
            return 'insufficient';
        }

        if (
            $historyCount >= 8
            && $salesCount >= 5
            && ($backtest['count'] ?? 0) >= 5
            && $backtest['wape'] !== null
            && $backtest['wape'] <= 0.25
        ) {
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

    public function snapshot(
        int $storeId,
        int $productId,
        string $date,
        array $recommendation,
        ?int $batchId = null,
        ?string $deviationReason = null,
        bool $referenced = true,
    ): ProductionRecommendationSnapshot {
        return ProductionRecommendationSnapshot::create([
            'store_id' => $storeId,
            'product_id' => $productId,
            'production_batch_id' => $batchId,
            'target_date' => $date,
            'center_quantity' => $recommendation['center'],
            'range_min' => $recommendation['min'],
            'range_max' => $recommendation['max'],
            'confidence' => $recommendation['confidence'],
            'calculation_version' => 'v3-adaptive-demand',
            'reasons' => $recommendation['reasons'],
            'excluded_conditions' => [],
            'data_from' => Carbon::parse($date)
                ->subDays(28)
                ->toDateString(),
            'data_to' => Carbon::parse($date)
                ->subDay()
                ->toDateString(),
            'referenced' => $referenced,
            'deviation_reason' => $deviationReason,
            'generated_at' => now(),
        ]);
    }
}