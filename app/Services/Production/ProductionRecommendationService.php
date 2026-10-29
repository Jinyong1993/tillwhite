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

    /*
    * 사용자 안내 문장
    *
    * [작성 원칙]
    * 1. 제빵사가 바로 이해할 수 있는 표현을 사용합니다.
    * 2. 실제 판매량을 입력받지 않으므로
    *    반드시 '추정 판매량'이라고 표현합니다.
    * 3. 이월재고와 이월예정은 구분합니다.
    * 4. 내부 계산 방식이나 개발 용어는 표시하지 않습니다.
    * 5. 기존 메시지 키는 유지해 다른 기능에 영향을 주지 않습니다.
    */
    private const MESSAGES = [
        'insufficient' => [
            '추천에 필요한 생산 기록이 없습니다.',
        ],

        'demand_sales' => [
            '생산량, 이월재고, 로스량, 폐기량, 이월예정을 참고했습니다.',
        ],

        'demand_estimated' => [
            '생산량과 남은 수량을 바탕으로 판매량을 추정했습니다.',
        ],

        'weekday_enough' => [
            '같은 요일의 추정 판매량을 참고했습니다.',
        ],

        'weekday_lacking' => [
            '같은 요일의 기록이 부족해 다른 영업일도 참고했습니다.',
        ],

        'stockout' => [
            '제품이 모두 소진된 날이 있어 더 판매할 기회가 있었을 수 있습니다.',
        ],

        'inventory_adjusted' => [
            '폐기량과 이월예정을 함께 고려했습니다.',
        ],

        'weather_applied' => [
            '비슷한 날씨의 추정 판매량을 참고했습니다.',
        ],

        'weather_insufficient' => [
            '비슷한 날씨의 기록이 부족합니다.',
        ],

        'group_order' => [
            '예정된 단체 주문 {quantity}개를 포함했습니다.',
        ],

        'promotion_applied' => [
            '비슷한 할인 행사가 있었던 날의 추정 판매량을 참고했습니다.',
        ],

        'promotion_fallback' => [
            '비슷한 할인 행사 기록이 부족해 평소 추정 판매량을 참고했습니다.',
        ],

        'promotion_warning' => [
            '비슷한 할인 행사 기록이 부족합니다.',
        ],

        'holiday_applied' => [
            '과거 공휴일의 추정 판매량을 참고했습니다.',
        ],

        'holiday_insufficient' => [
            '공휴일 기록이 부족해 평소 추정 판매량을 참고했습니다.',
        ],

        'high_quantity_adjusted' => [
            '최근 추정 판매량에 비해 생산량이 많지 않도록 고려했습니다.',
        ],

        'high_quantity_warning' => [
            '최근 추정 판매량을 고려해 추천 수량을 줄였습니다.',
        ],

        'low_confidence' => [
            '참고할 기록이 부족해 추천 수량에 차이가 있을 수 있습니다.',
        ],

        'invalid_history' => [
            '확인하기 어려운 기록 {count}건은 참고하지 않았습니다.',
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

        /*
        * 추천 이유 안내
        *
        * [목적]
        * 제빵사가 추천 생산량의 근거를
        * 쉽고 빠르게 이해할 수 있도록 안내합니다.
        *
        * [표시 원칙]
        * - 내부 계산 방식은 설명하지 않습니다.
        * - 판매량은 반드시 '추정 판매량'으로 표현합니다.
        * - 같은 날짜와 수량을 반복해서 표시하지 않습니다.
        * - 기존 추천 생산량 계산은 변경하지 않습니다.
        */
        $reasons = [];

        /*
        * 과거 추정 판매량을 참고했다는 사실을 안내합니다.
        */
        $reasons[] =
            '과거 추정 판매량을 참고해 생산량을 계산했습니다.';

        /*
        * 추천 생산량에 참고한 영업일 안내
        *
        * [목적]
        * 어떤 요일의 기록을 몇 일치 참고했는지
        * 사용자가 바로 알 수 있도록 안내합니다.
        *
        * [주의]
        * - 실제 사용한 기록 수만 표시합니다.
        * - 날짜별 수량은 '참고한 판매 기록'에서 표시합니다.
        * - 기존 추천 계산과 기록 선택 기준은 유지합니다.
        */
        $analysisDayType = (string) (
            $baseline['analysis_day_type'] ?? 'all'
        );

        $analysisRecordCount = (int) (
            $baseline['analysis_record_count'] ?? 0
        );

        if ($analysisRecordCount > 0) {
            if ($analysisDayType === 'same_weekday') {
                $reasons[] =
                    "같은 요일의 기록 {$analysisRecordCount}일치를 참고했습니다.";
            } elseif ($analysisDayType === 'weekend') {
                $reasons[] =
                    "주말 기록 {$analysisRecordCount}일치를 참고했습니다.";
            } elseif ($analysisDayType === 'weekday') {
                $reasons[] =
                    "평일 기록 {$analysisRecordCount}일치를 참고했습니다.";
            } else {
                $reasons[] =
                    "영업 기록 {$analysisRecordCount}일치를 참고했습니다.";
            }
        }

        /*
        * 날짜별 추정 판매량은
        * '참고한 판매 기록' 영역에서 표시합니다.
        *
        * 추천 이유에 같은 날짜와 수량을
        * 반복해서 추가하지 않습니다.
        *
        * 기존 reference_records 데이터는 유지합니다.
        */

        /*
        * 동일 요일의 최근 추정 판매량 변화 확인
        *
        * 기존 분석 기준과 추천 계산을 유지합니다.
        */
        $recentTrend = $this->recentDemandTrend(
            $valid,
            $targetDate
        );

        /*
        * 최근 같은 요일의 추정 판매량 변화 안내
        *
        * 기존 분석 및 추천 계산은 유지합니다.
        */
        if ($recentTrend !== null) {
            if ($recentTrend['direction'] === 'increasing') {
                $reasons[] =
                    '최근 같은 요일의 추정 판매량이 이전보다 늘었습니다.';
            } elseif ($recentTrend['direction'] === 'decreasing') {
                $reasons[] =
                    '최근 같은 요일의 추정 판매량이 이전보다 줄었습니다.';
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

        /*
        * 평소와 추정 판매량 차이가 컸던 날짜 안내
        *
        * [목적]
        * 사용자가 해당 날짜와 수량을 바로 확인할 수 있도록
        * 구체적인 판매 기록을 안내합니다.
        *
        * [표시 정보]
        * - 실제 기록 날짜
        * - 요일
        * - 해당 날짜의 추정 판매량
        * - 이전 기록의 보통 추정 판매량
        *
        * [주의]
        * - 수량을 임의로 생성하지 않습니다.
        * - 기존 이상 변동 판단 기준은 변경하지 않습니다.
        * - 해당 기록을 삭제하거나 추천 계산에서 제외하지 않습니다.
        * - 단일 기록만으로 생산량을 변경하지 않습니다.
        */
        if ($recentOutlier !== null) {
            $recordDate = $recentOutlier['date'] ?? null;

            $recordQuantity =
                $recentOutlier['quantity'] ?? null;

            $referenceQuantity =
                $recentOutlier['reference_median'] ?? null;

            /*
            * 실제 날짜와 수량이 모두 있을 때만
            * 구체적인 안내 문장을 생성합니다.
            */
            if (
                $recordDate !== null
                && is_numeric($recordQuantity)
                && is_numeric($referenceQuantity)
            ) {
                $date = Carbon::parse($recordDate);

                $weekdays = [
                    '일', '월', '화', '수',
                    '목', '금', '토',
                ];

                $dateLabel = $date->format('n월 j일');

                $weekdayLabel =
                    $weekdays[$date->dayOfWeek];

                /*
                * 정수 수량은 소수점 없이 표시합니다.
                * 소수점이 필요한 경우에만 표시합니다.
                */
                $quantity = round(
                    (float) $recordQuantity,
                    1
                );

                $reference = round(
                    (float) $referenceQuantity,
                    1
                );

                $quantityText = number_format(
                    $quantity,
                    $quantity == floor($quantity) ? 0 : 1
                );

                $referenceText = number_format(
                    $reference,
                    $reference == floor($reference) ? 0 : 1
                );

                /*
                * 해당 날짜의 추정 판매량이
                * 이전 기록보다 많았는지 적었는지 안내합니다.
                */
                if (
                    $recentOutlier['type'] === 'unusually_high'
                ) {
                    $warnings[] =
                        "{$dateLabel}({$weekdayLabel}) "
                        . "추정 판매량은 {$quantityText}개로, "
                        . "이전 기록의 보통 판매량 "
                        . "{$referenceText}개보다 많았습니다.";
                } elseif (
                    $recentOutlier['type'] === 'unusually_low'
                ) {
                    $warnings[] =
                        "{$dateLabel}({$weekdayLabel}) "
                        . "추정 판매량은 {$quantityText}개로, "
                        . "이전 기록의 보통 판매량 "
                        . "{$referenceText}개보다 적었습니다.";
                }
            }
        }

        /*
        * 기록 1개만으로 계산한 초기 추천 안내
        */
        if ($valid->count() === 1) {
            $reasons[] =
                '생산 기록 1일치를 참고했습니다.';

            $warnings[] =
                '기록이 부족해 추천 범위가 넓습니다. 실제 영업 상황도 확인해 주세요.';
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
        * 폐기량과 이월예정이 모두 0개였던 날짜 안내
        *
        * [목적]
        * 제품이 남지 않았던 날을 실제 날짜와 수량으로 보여줍니다.
        *
        * [기존 기능 유지]
        * - 최근 유효 기록 중 최대 7일치를 확인합니다.
        * - 정상 마감된 기록만 인정합니다.
        * - 실제 사용 가능한 수량이 있어야 합니다.
        * - 폐기량과 이월예정이 모두 0개여야 합니다.
        * - 해당 날짜가 3일 이상일 때 안내합니다.
        *
        * [사용자 안내]
        * - 날짜와 요일
        * - 해당 날짜의 추정 판매량
        * - 폐기량과 이월예정이 없었다는 사실
        *
        * [주의]
        * - 제품이 남지 않았다고 실제 품절을 확정하지 않습니다.
        * - 단순히 이 기록만으로 추천 생산량을 증가시키지 않습니다.
        * - 기존 추천 계산과 분석 결과는 그대로 유지합니다.
        */
        if ($possibleSelloutCount >= 3) {
            /*
            * 먼저 전체 결과를 간단하게 안내합니다.
            */
            $warnings[] =
                "최근 {$possibleSelloutCount}일은 "
                . "폐기량과 이월예정이 모두 0개였습니다. "
                . "더 판매할 기회가 있었을 수 있습니다.";

            /*
            * 기존의 완판 가능성 판단 조건을 그대로 적용해
            * 실제 날짜별 기록을 가져옵니다.
            */
            $selloutEvidenceRows = $recentStockoutRows
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
                        !empty($row['date'])
                        && $available !== null
                        && $available > 0
                        && $remaining !== null
                        && $remaining == 0
                        && $estimatedSales !== null
                        && $estimatedSales > 0;
                })
                ->sortByDesc('date')
                ->take(3)
                ->values();

            /*
            * 가장 최근 날짜부터 최대 3일치만 표시합니다.
            * 모바일에서도 길어지지 않도록 제한합니다.
            */
            foreach ($selloutEvidenceRows as $row) {
                $date = Carbon::parse(
                    $row['date']
                );

                $weekdays = [
                    '일', '월', '화', '수',
                    '목', '금', '토',
                ];

                $dateLabel = $date->format('n월 j일');

                $weekdayLabel =
                    $weekdays[$date->dayOfWeek];

                $estimatedSales = round(
                    (float) $row['estimated_sales_quantity'],
                    1
                );

                $salesText = number_format(
                    $estimatedSales,
                    floor($estimatedSales) == $estimatedSales
                        ? 0
                        : 1
                );

                /*
                * 실제 수량을 이용한 최종 안내 문장입니다.
                *
                * 예시:
                * 10월 24일(토): 추정 판매량 15개,
                * 폐기 0개, 이월예정 0개
                */
                $warnings[] =
                    "{$dateLabel}({$weekdayLabel}): "
                    . "추정 판매량 {$salesText}개, "
                    . "폐기 0개, 이월예정 0개";
            }
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
        * 폐기량과 이월예정이 많았던 날짜 안내
        *
        * [목적]
        * 최근 폐기량과 이월예정이 많았다면
        * 사용자가 실제 날짜와 수량을 확인할 수 있도록 안내합니다.
        *
        * [기존 기능 유지]
        * - 최근 유효한 기록 중 최대 7일치를 사용합니다.
        * - 기록이 최소 3일치 이상이어야 합니다.
        * - 전체 사용 가능 수량 대비 남은 수량이
        *   20% 이상일 때 안내합니다.
        * - 기존 추천 생산량 계산은 변경하지 않습니다.
        *
        * [추가 기능]
        * - 실제 날짜와 요일을 표시합니다.
        * - 해당 날짜의 폐기량을 표시합니다.
        * - 해당 날짜의 이월예정을 표시합니다.
        * - 폐기량과 이월예정이 많은 날짜부터
        *   최대 3일치만 보여줍니다.
        *
        * [주의]
        * - 날짜와 수량을 임의로 생성하지 않습니다.
        * - 생산·폐기·이월이 여러 건이면
        *   기존에 합산된 일별 기록을 사용합니다.
        * - 수량이 없는 항목을 임의로 0개라고 안내하지 않습니다.
        */
        if (
            $surplusRows->count() >= 3
            && $surplusRate !== null
            && $surplusRate >= 0.20
        ) {
            /*
            * 전체 기록을 기준으로 남은 수량의 비율을 안내합니다.
            */
            $surplusPercent = round(
                $surplusRate * 100,
                1
            );

            $recordCount = $surplusRows->count();

            $reasons[] =
                "최근 {$recordCount}일치 기록에서 "
                . "폐기량과 이월예정이 전체 수량의 "
                . "{$surplusPercent}%였습니다.";

            /*
            * 폐기량 또는 이월예정이 실제로 존재하는
            * 날짜만 추출합니다.
            */
            $surplusEvidenceRows = $surplusRows
                ->map(function (array $row): array {
                    /*
                    * 해당 날짜의 폐기량과 이월예정입니다.
                    *
                    * 원본 기록에 값이 없다면 null로 유지해
                    * 실제로 0개였던 기록과 구분합니다.
                    */
                    $waste = $this->numeric(
                        $row,
                        ['operational_waste', 'waste']
                    );

                    $carryover = $this->numeric(
                        $row,
                        ['carryover_out']
                    );

                    return [
                        'date' => $row['date'] ?? null,

                        'waste' => $waste,

                        'carryover' => $carryover,

                        /*
                        * 날짜별 표시 순서를 정하기 위한 합계입니다.
                        * 사용자가 보는 문장에는 각 수량을 구분합니다.
                        */
                        'remaining' =>
                            ($waste ?? 0) + ($carryover ?? 0),
                    ];
                })
                ->filter(
                    fn (array $row) =>
                        !empty($row['date'])
                        && $row['remaining'] > 0
                )
                ->sortByDesc('remaining')
                ->take(3)
                ->values();

            /*
            * 실제 날짜별 폐기량과 이월예정을 안내합니다.
            */
            foreach ($surplusEvidenceRows as $row) {
                $date = Carbon::parse(
                    $row['date']
                );

                $weekdays = [
                    '일', '월', '화', '수',
                    '목', '금', '토',
                ];

                $dateLabel = $date->format(
                    'n월 j일'
                );

                $weekdayLabel =
                    $weekdays[$date->dayOfWeek];

                /*
                * 확인 가능한 수량만 문장에 포함합니다.
                */
                $quantityTexts = [];

                if ($row['waste'] !== null) {
                    $quantityTexts[] =
                        '폐기 '
                        . number_format(
                            $row['waste'],
                            floor($row['waste']) == $row['waste']
                                ? 0
                                : 1
                        )
                        . '개';
                }

                if ($row['carryover'] !== null) {
                    $quantityTexts[] =
                        '이월예정 '
                        . number_format(
                            $row['carryover'],
                            floor($row['carryover']) == $row['carryover']
                                ? 0
                                : 1
                        )
                        . '개';
                }

                if (empty($quantityTexts)) {
                    continue;
                }

                $quantitySummary = implode(
                    ', ',
                    $quantityTexts
                );

                /*
                * 사용자에게 보이는 최종 문장입니다.
                *
                * 예시:
                * 10월 24일(토): 폐기 5개, 이월예정 1개
                */
                $reasons[] =
                    "{$dateLabel}({$weekdayLabel}): "
                    . $quantitySummary;
            }
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
        * 폐기량과 이월예정이 적었던 날짜 안내
        *
        * [기존 기능 유지]
        * - 최근 유효 기록을 사용합니다.
        * - 남은 수량이 0개보다 많고 10% 이하인 날을 확인합니다.
        * - 해당 날짜가 2일 이상일 때 안내합니다.
        *
        * [개선]
        * - 실제 날짜와 요일을 표시합니다.
        * - 폐기량과 이월예정의 수량을 구체적으로 표시합니다.
        * - 날짜별 기록은 최대 3일치만 표시합니다.
        *
        * 기존 추천 계산과 판단 기준은 변경하지 않습니다.
        */
        if (
            $balanceHistoryCount >= 3
            && $lowSurplusDays >= 2
        ) {
            $reasons[] =
                "최근 {$balanceHistoryCount}일치 기록 중 "
                . "{$lowSurplusDays}일은 "
                . "폐기량과 이월예정이 전체 수량의 "
                . "10% 이하였습니다.";

            /*
            * 남은 수량이 전체 수량의 10% 이하인
            * 실제 날짜만 추출합니다.
            */
            $lowSurplusEvidenceRows = $balanceRows
                ->filter(function (array $row): bool {
                    $available = $this->numeric(
                        $row,
                        ['daily_available_quantity']
                    );

                    $remaining = $this->numeric(
                        $row,
                        ['daily_remaining_quantity']
                    );

                    if (
                        empty($row['date'])
                        || $available === null
                        || $available <= 0
                        || $remaining === null
                        || $remaining <= 0
                    ) {
                        return false;
                    }

                    $remainingRate =
                        $remaining / $available;

                    return $remainingRate <= 0.10;
                })
                ->sortByDesc('date')
                ->take(3)
                ->values();

            foreach ($lowSurplusEvidenceRows as $row) {
                $date = Carbon::parse($row['date']);

                $weekdays = [
                    '일', '월', '화', '수',
                    '목', '금', '토',
                ];

                $dateLabel = $date->format('n월 j일');

                $weekdayLabel =
                    $weekdays[$date->dayOfWeek];

                $waste = $this->numeric(
                    $row,
                    ['operational_waste', 'waste']
                );

                $carryover = $this->numeric(
                    $row,
                    ['carryover_out']
                );

                /*
                * 실제로 확인 가능한 수량만 표시합니다.
                */
                $quantities = [];

                if ($waste !== null) {
                    $quantities[] =
                        '폐기 '
                        . number_format($waste, 0)
                        . '개';
                }

                if ($carryover !== null) {
                    $quantities[] =
                        '이월예정 '
                        . number_format($carryover, 0)
                        . '개';
                }

                if (empty($quantities)) {
                    continue;
                }

                $reasons[] =
                    "{$dateLabel}({$weekdayLabel}): "
                    . implode(', ', $quantities);
            }
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
                '이월재고 '
                . $targetCarryoverIn
                . '개를 고려해 추천 생산량을 줄였습니다.';
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
        * 과거 기록을 바탕으로 생산량 감소 가능성 확인
        *
        * 기존 분석 결과와 안내 기능을 유지합니다.
        * 제빵사가 이해하기 쉬운 문장으로 표시합니다.
        */
        if ($adjustmentAssessment === 'promising') {
            $reasons[] =
                '과거 기록에서는 생산량을 조금 줄여도 '
                . '추정 판매량과 큰 차이가 없었습니다. '
                . '다만 이번 추천 생산량에는 적용하지 않았습니다.';
        } elseif (
            $adjustmentAssessment === 'shortage_risk'
        ) {
            $warnings[] =
                '과거 기록에서는 생산량을 줄이면 '
                . '판매 기회를 놓칠 가능성이 있었습니다. '
                . '이번에는 생산량을 줄이지 않았습니다.';
        }

        /*
        * 과거 추천 수량과 추정 판매량 비교
        *
        * [목적]
        * 추천 수량이 추정 판매량보다
        * 많았는지 적었는지 구체적으로 안내합니다.
        *
        * [기존 기능 유지]
        * - 최소 5일치 비교 기록이 필요합니다.
        * - 기존 15% 판단 기준을 유지합니다.
        * - 추천 생산량 계산에는 영향을 주지 않습니다.
        *
        * [표시 원칙]
        * - 실제 비교한 기록 수를 표시합니다.
        * - 평균 수량 차이를 표시합니다.
        * - 판매량은 반드시 추정 판매량으로 표현합니다.
        */
        $backtestBias = $backtest['bias_rate'] ?? null;

        $comparedDays = (int) (
            $backtest['count'] ?? 0
        );

        $averageError = $backtest['mae'] ?? null;

        if (
            $comparedDays >= 5
            && is_numeric($backtestBias)
            && is_numeric($averageError)
        ) {
            /*
            * 평균 차이는 소수점 첫째 자리까지 표시합니다.
            */
            $errorText = number_format(
                (float) $averageError,
                1
            );

            if (str_ends_with($errorText, '.0')) {
                $errorText = substr(
                    $errorText,
                    0,
                    -2
                );
            }

            /*
            * 과거 추천 수량이 추정 판매량보다
            * 대체로 많았던 경우입니다.
            */
            if ($backtestBias >= 0.15) {
                $reasons[] =
                    "과거 {$comparedDays}일치 기록에서 "
                    . "추천 수량과 추정 판매량은 "
                    . "평균 {$errorText}개 차이가 났습니다. "
                    . "추천 수량이 대체로 많았습니다.";
            }

            /*
            * 과거 추천 수량이 추정 판매량보다
            * 대체로 적었던 경우입니다.
            */
            elseif ($backtestBias <= -0.15) {
                $reasons[] =
                    "과거 {$comparedDays}일치 기록에서 "
                    . "추천 수량과 추정 판매량은 "
                    . "평균 {$errorText}개 차이가 났습니다. "
                    . "추천 수량이 대체로 적었습니다.";
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

        /*
        * 최근 추정 판매량에 비해 추천 수량이 지나치게 많은 경우
        *
        * [기존 기능 유지]
        * - 단체 주문 등 별도 조건이 없는 경우에 적용합니다.
        * - 공휴일에는 적용하지 않습니다.
        * - 최근 추정 판매량보다 추천량이 크게 높을 때만 적용합니다.
        * - 기존 생산량 계산 기준은 유지합니다.
        *
        * [안내 문장 개선]
        * 동일한 내용을 추천 이유와 주의사항에
        * 중복해서 표시하지 않습니다.
        */
        if (
            !$strongCondition
            && !$isHoliday
            && $recentAverage > 0
            && $center > $recentAverage * 1.5
        ) {
            /*
            * 기존 추천 수량 제한 방식 유지
            */
            $center = (int) round(
                $recentAverage * 1.25
            );

            $highQuantityAdjusted = true;

            /*
            * 변경된 추천 수량에 맞춰 범위를 다시 계산
            */
            $margin = $this->recommendationMargin(
                max(
                    $center,
                    $expectedDemandBeforeCarryover
                ),
                $evidenceCount,
                $standardDeviation,
                $backtest
            );

            /*
            * 추천 이유에만 표시합니다.
            * 주의사항에는 동일한 문장을 추가하지 않습니다.
            */
            $reasons[] =
                '최근 추정 판매량에 비해 추천 수량이 높아 '
                . '생산량을 줄였습니다.';
        }

        /*
        * 판매량 변화와 남은 수량을 함께 안내
        *
        * [목적]
        * 추정 판매량이 증가하더라도 폐기량과 이월예정이
        * 많을 수 있다는 점을 사용자에게 알려줍니다.
        *
        * [표시 원칙]
        * - 실제 분석 결과가 있을 때만 안내합니다.
        * - 내부 계산 방식은 설명하지 않습니다.
        * - 기존 추천 생산량은 변경하지 않습니다.
        * - 판매 기회와 폐기 최소화를 함께 고려합니다.
        */
        if (
            $recentTrend !== null
            && $surplusRows->count() >= 3
            && $surplusRate !== null
        ) {
            if (
                $recentTrend['direction'] === 'increasing'
                && $surplusRate >= 0.20
            ) {
                $reasons[] =
                    '최근 추정 판매량은 늘었지만 '
                    . '폐기량과 이월예정도 많았습니다. '
                    . '두 기록을 함께 고려했습니다.';
            } elseif (
                $recentTrend['direction'] === 'decreasing'
                && $surplusRate <= 0.10
            ) {
                $reasons[] =
                    '최근 추정 판매량은 줄었지만 '
                    . '폐기량과 이월예정은 적었습니다. '
                    . '판매 기회를 고려해 생산량을 판단했습니다.';
            }
        }

        $confidence = $this->confidence(
            $valid->count(),
            $salesCount,
            $baseline['same_weekday_count'],
            $backtest
        );

        /*
        * 추천 수량의 신뢰도가 낮은 경우
        *
        * [목적]
        * 참고할 기록이 부족한 경우와
        * 과거 추천 수량의 차이가 컸던 경우를 구분합니다.
        *
        * 기존 신뢰도 계산은 변경하지 않습니다.
        */
        if ($confidence === 'low') {
            $historyCount = $valid->count();

            $comparedDays = (int) (
                $backtest['count'] ?? 0
            );

            $errorRate = $backtest['wape'] ?? null;

            if ($historyCount < 3) {
                $warnings[] =
                    "참고할 생산 기록이 {$historyCount}일치입니다. "
                    . "기록이 더 쌓이면 추천 수량을 판단하는 데 도움이 됩니다.";
            } elseif (
                $comparedDays >= 3
                && is_numeric($errorRate)
            ) {
                $warnings[] =
                    '과거 추천 수량과 추정 판매량에 차이가 있었습니다. '
                    . '실제 생산 상황도 함께 고려해 주십시오.';
            } else {
                $warnings[] =
                    '추천 수량을 판단할 기록이 아직 충분하지 않습니다.';
            }
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

                /*
                * 추천 계산에 실제 사용한 대표 기록입니다.
                *
                * 날짜·요일·추정 판매량을 함께 전달합니다.
                */
                'reference_records' =>
                    $baseline['reference_records'] ?? [],

                /*
                * 같은 요일, 평일, 주말 중
                * 어떤 기록을 참고했는지 구분합니다.
                */
                'analysis_day_type' =>
                    $baseline['analysis_day_type'] ?? 'all',
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

                /*
                * 날짜별 추천 수량과 추정 판매량 비교 기록
                *
                * 기존 정확도 수치는 유지하고
                * 화면에 표시할 날짜별 정보만 추가합니다.
                */
                'comparison_records' =>
                    $backtest['comparison_records'] ?? [],

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
        /*
        * 추천 대상 날짜입니다.
        */
        $target = $targetDate->copy()->startOfDay();

        /*
        * 기본 수요 계산에는
        * 최근 최대 12개월의 기록을 사용합니다.
        */
        $historyFrom = $target->copy()->subYear();

        /*
        * 최근 기록과 이전 기록을 구분합니다.
        *
        * 최근 3개월 70%
        * 이전 9개월 30%
        */
        $recentFrom = $target->copy()
            ->subMonthsNoOverflow(3);

        /*
        * 추천 대상일의 요일입니다.
        *
        * 0: 일요일
        * 6: 토요일
        */
        $targetWeekday = $target->dayOfWeek;

        /*
        * 토요일과 일요일을 주말로 구분합니다.
        *
        * 나머지 요일은 평일입니다.
        */
        $targetIsWeekend = in_array(
            $targetWeekday,
            [0, 6],
            true
        );

        /*
        * 추천 대상일 이전의
        * 정상적인 과거 기록만 사용합니다.
        *
        * 기존에 전달된 유효 기록을 대상으로 하며
        * 날짜와 추정 판매량을 확인합니다.
        */
        $history = $rows
            ->filter(function (array $row) use (
                $historyFrom,
                $target
            ): bool {
                if (empty($row['date'])) {
                    return false;
                }

                $date = Carbon::parse(
                    $row['date']
                )->startOfDay();

                return
                    $date->gte($historyFrom)
                    && $date->lt($target)
                    && $this->demandValue($row) !== null;
            })
            ->sortBy('date')
            ->values();

        /*
        * 추천 대상일과 같은 요일의
        * 과거 기록을 추출합니다.
        */
        $sameWeekday = $history
            ->filter(
                fn (array $row): bool =>
                    Carbon::parse($row['date'])->dayOfWeek
                    === $targetWeekday
            )
            ->values();

        $sameCount = $sameWeekday->count();

        /*
        * 평일과 주말을 구분한 과거 기록입니다.
        *
        * 예를 들어 일요일을 추천할 때
        * 일요일 기록이 부족하면
        * 다른 주말인 토요일 기록도 참고합니다.
        */
        $sameDayType = $history
            ->filter(function (array $row) use (
                $targetIsWeekend
            ): bool {
                $weekday = Carbon::parse(
                    $row['date']
                )->dayOfWeek;

                $isWeekend = in_array(
                    $weekday,
                    [0, 6],
                    true
                );

                return $isWeekend === $targetIsWeekend;
            })
            ->values();

        $sameDayTypeCount = $sameDayType->count();

        /*
        * 과거 기록 선택 순서입니다.
        *
        * 1. 같은 요일 기록이 2일치 이상
        *    → 같은 요일만 사용
        *
        * 2. 같은 요일 기록이 부족하지만
        *    평일·주말 구분 기록이 2일치 이상
        *    → 같은 평일·주말 구분의 기록 사용
        *
        * 3. 위 조건을 충족하지 못함
        *    → 전체 정상 영업일 기록 사용
        */
        if ($sameCount >= 2) {
            $analysisRows = $sameWeekday;

            $analysisSource = 'same_weekday';
            $analysisDayType = 'same_weekday';
        } elseif ($sameDayTypeCount >= 2) {
            $analysisRows = $sameDayType;

            /*
            * 기존 분석 출처 값과의 호환성을 위해
            * normal_business_days 값을 유지합니다.
            *
            * 실제 평일·주말 구분 여부는
            * analysis_day_type에 따로 저장합니다.
            */
            $analysisSource = 'normal_business_days';

            $analysisDayType = $targetIsWeekend
                ? 'weekend'
                : 'weekday';
        } else {
            $analysisRows = $history;

            $analysisSource = 'normal_business_days';
            $analysisDayType = 'all';
        }

        /*
        * 최근 3개월 기록입니다.
        */
        $recentRows = $analysisRows
            ->filter(
                fn (array $row): bool =>
                    Carbon::parse($row['date'])
                        ->gte($recentFrom)
            )
            ->values();

        /*
        * 이전 9개월 기록입니다.
        */
        $olderRows = $analysisRows
            ->filter(
                fn (array $row): bool =>
                    Carbon::parse($row['date'])
                        ->lt($recentFrom)
            )
            ->values();

        /*
        * 기존 가중 평균 함수를 사용합니다.
        *
        * 같은 기간 안에서도 최근 기록을
        * 조금 더 중요하게 반영합니다.
        */
        $recentAverage = $recentRows->isNotEmpty()
            ? $this->weightedAverage($recentRows)
            : null;

        $olderAverage = $olderRows->isNotEmpty()
            ? $this->weightedAverage($olderRows)
            : null;

        /*
        * 두 기간에 모두 기록이 있으면
        * 최근 3개월 70%, 이전 9개월 30%를 적용합니다.
        *
        * 한쪽에 기록이 없다면
        * 존재하는 기록만 사용합니다.
        */
        if (
            $recentAverage !== null
            && $olderAverage !== null
        ) {
            $center =
                $recentAverage * 0.70
                + $olderAverage * 0.30;
        } elseif ($recentAverage !== null) {
            $center = $recentAverage;
        } elseif ($olderAverage !== null) {
            $center = $olderAverage;
        } else {
            /*
            * 참고할 수 있는 기록이 없다면
            * 기본 수요를 0으로 반환합니다.
            *
            * 이 함수에서 임의의 추천량을
            * 만들어 내지 않습니다.
            */
            $center = 0;
        }

        /*
        * 사용자에게 보여줄 대표 참고 기록입니다.
        *
        * 실제 추천 계산에 사용한 $analysisRows에서만 선택합니다.
        *
        * 최근 날짜 3개를 보여주되,
        * 추천 계산 자체는 기존 전체 분석 기록을 사용합니다.
        *
        * 날짜별 수량은 현재 추천 계산에 사용한
        * demandValue()와 동일한 값입니다.
        */
        $referenceRecords = $analysisRows
            ->sortByDesc('date')
            ->take(3)
            ->map(function (array $row): array {
                $date = Carbon::parse(
                    $row['date']
                );

                $weekdayNames = [
                    '일',
                    '월',
                    '화',
                    '수',
                    '목',
                    '금',
                    '토',
                ];

                $demand = $this->demandValue($row);

                return [
                    'date' => $date->toDateString(),

                    'date_label' =>
                        $date->format('n월 j일'),

                    'weekday_label' =>
                        $weekdayNames[$date->dayOfWeek],

                    'estimated_sales' =>
                        $demand !== null
                            ? round($demand, 1)
                            : null,
                ];
            })
            ->values()
            ->all();

        /*
        * 기존 반환 항목을 유지합니다.
        *
        * 평일·주말 구분 정보만 추가합니다.
        */
        return [
            'center' => max(
                0,
                (int) round($center)
            ),

            'same_weekday_count' =>
                $sameCount,

            'analysis_record_count' =>
                $analysisRows->count(),

            'analysis_source' =>
                $analysisSource,

            /*
            * 추천 이유에 보여줄 실제 참고 기록입니다.
            */
            'reference_records' => $referenceRecords,

            /*
            * 추가된 분석 정보입니다.
            */
            'analysis_day_type' =>
                $analysisDayType,

            'same_day_type_count' =>
                $sameDayTypeCount,

            /*
            * 기존 기간별 분석 결과입니다.
            */
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
            * 실제 적용된 기간별 비중입니다.
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
     * 최근 같은 요일의 추정 판매량 변화 확인
     *
     * [목적]
     * 최근 판매량이 이전보다 증가하거나 감소했는지 확인합니다.
     *
     * [판단 방법]
     * 1. 추천 대상일과 같은 요일의 기록을 가져옵니다.
     * 2. 가장 최근 6일치 기록을 사용합니다.
     * 3. 이전 3일치와 최근 3일치의 중앙값을 비교합니다.
     * 4. 차이가 15% 미만이면 의미 있는 변화로 판단하지 않습니다.
     * 5. 최근 3일 중 2일 이상 같은 방향의 변화가 있어야 인정합니다.
     *
     * [사용자 안내]
     * 실제 날짜, 요일, 추정 판매량을 함께 반환합니다.
     *
     * [주의]
     * - 수량을 임의로 생성하지 않습니다.
     * - 이 함수에서 추천 생산량을 직접 변경하지 않습니다.
     * - 기존 반환 항목은 유지합니다.
     */
    private function recentDemandTrend(
        Collection $rows,
        Carbon $targetDate
    ): ?array {
        $targetWeekday = $targetDate->dayOfWeek;

        /*
        * 추천 대상일보다 이전인 같은 요일 기록만 사용합니다.
        */
        $sameWeekdayRows = $rows
            ->filter(
                fn (array $row) =>
                    !empty($row['date'])
                    && Carbon::parse($row['date'])->startOfDay()
                        ->lt($targetDate->copy()->startOfDay())
                    && Carbon::parse($row['date'])->dayOfWeek
                        === $targetWeekday
                    && $this->demandValue($row) !== null
            )
            ->sortBy('date')
            ->take(-6)
            ->values();

        /*
        * 6일치보다 적으면 추세를 판단하지 않습니다.
        */
        if ($sameWeekdayRows->count() < 6) {
            return null;
        }

        /*
        * 이전 3일과 최근 3일로 구분합니다.
        */
        $olderRows = $sameWeekdayRows
            ->take(3)
            ->values();

        $recentRows = $sameWeekdayRows
            ->skip(3)
            ->values();

        $olderValues = $olderRows
            ->map(
                fn (array $row) =>
                    (float) $this->demandValue($row)
            );

        $recentValues = $recentRows
            ->map(
                fn (array $row) =>
                    (float) $this->demandValue($row)
            );

        /*
        * 특정 하루의 판매량에 지나치게 영향을 받지 않도록
        * 중앙값을 사용합니다.
        */
        $olderMedian = (float) $olderValues->median();
        $recentMedian = (float) $recentValues->median();

        if ($olderMedian <= 0) {
            return null;
        }

        $changeRate =
            ($recentMedian - $olderMedian)
            / $olderMedian;

        /*
        * 15% 미만의 변화는 일반적인 차이로 판단합니다.
        */
        if (abs($changeRate) < 0.15) {
            return null;
        }

        $direction = $changeRate > 0
            ? 'increasing'
            : 'decreasing';

        /*
        * 최근 3일 중 최소 2일에서
        * 동일한 증가 또는 감소 방향이 확인되어야 합니다.
        */
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

        /*
        * 사용자에게 표시할 날짜와 수량입니다.
        *
        * 반드시 실제 기록을 이용합니다.
        */
        $referenceRecords = $recentRows
            ->map(function (array $row): array {
                $date = Carbon::parse($row['date']);

                $weekdays = [
                    '일', '월', '화', '수',
                    '목', '금', '토',
                ];

                return [
                    'date' => $date->toDateString(),

                    'date_label' =>
                        $date->format('n월 j일'),

                    'weekday_label' =>
                        $weekdays[$date->dayOfWeek],

                    'estimated_sales' =>
                        round(
                            (float) $this->demandValue($row),
                            1
                        ),
                ];
            })
            ->values()
            ->all();

        /*
        * 기존 반환 값은 그대로 유지합니다.
        *
        * 사용자 안내에 필요한 기록만 추가합니다.
        */
        return [
            'direction' => $direction,

            'change_rate' => round(
                $changeRate,
                4
            ),

            'sample_count' => 6,

            'older_median' => round(
                $olderMedian,
                1
            ),

            'recent_median' => round(
                $recentMedian,
                1
            ),

            'reference_records' => $referenceRecords,
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
     * 과거 기록을 이용한 추천 수량 비교
     *
     * [기존 기능]
     * - 각 날짜보다 이전의 기록만 사용합니다.
     * - 추천 수량과 추정 판매량의 차이를 계산합니다.
     * - 평균 수량 차이, 평균 차이율, 차이의 방향을 유지합니다.
     *
     * [추가 기능]
     * - 비교한 날짜를 함께 반환합니다.
     * - 해당 날짜에 계산한 추천 수량을 반환합니다.
     * - 해당 날짜의 추정 판매량을 반환합니다.
     * - 날짜별 수량 차이를 반환합니다.
     *
     * [중요]
     * 여기서 계산한 추천 수량은
     * 당시 데이터베이스에 저장했던 추천 수량이 아니라
     * 이전 기록을 이용해 다시 계산한 수량입니다.
     *
     * 기존 반환 항목은 유지합니다.
     */
    private function backtest(
        Collection $normal
    ): array {
        $rows = $normal->values();

        $errors = [];
        $actualTotal = 0.0;
        $predictionTotal = 0.0;

        /*
        * 날짜별 비교 기록
        */
        $comparisonRecords = [];

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

            /*
            * 비교 대상 날짜 이전의 기록만 사용합니다.
            */
            $past = $rows
                ->slice(0, $i)
                ->values();

            if ($past->count() < self::MIN_HISTORY) {
                continue;
            }

            $actual = $this->demandValue(
                $actualRow
            );

            if (
                $actual === null
                || empty($actualRow['date'])
            ) {
                continue;
            }

            $date = Carbon::parse(
                $actualRow['date']
            );

            /*
            * 해당 날짜의 이전 기록만으로
            * 추천 수량을 계산합니다.
            */
            $prediction = (float) $this->baseline(
                $past,
                $date
            )['center'];

            /*
            * 추천 수량과 추정 판매량의 차이
            */
            $error = abs(
                $prediction - $actual
            );

            $errors[] = $error;

            $actualTotal += $actual;
            $predictionTotal += $prediction;

            $weekdays = [
                '일', '월', '화', '수',
                '목', '금', '토',
            ];

            /*
            * 실제 날짜별 비교 결과를 보관합니다.
            *
            * 과잉: 계산한 수량이 추정 판매량보다 많음
            * 과소: 계산한 수량이 추정 판매량보다 적음
            * 일치: 두 수량이 같음
            */
            $direction = 'equal';

            if ($prediction > $actual) {
                $direction = 'over';
            } elseif ($prediction < $actual) {
                $direction = 'under';
            }

            $comparisonRecords[] = [
                'date' => $date->toDateString(),

                'date_label' =>
                    $date->format('n월 j일'),

                'weekday_label' =>
                    $weekdays[$date->dayOfWeek],

                'calculated_quantity' =>
                    round($prediction, 1),

                'estimated_sales' =>
                    round($actual, 1),

                'difference_quantity' =>
                    round($error, 1),

                'direction' => $direction,
            ];
        }

        $count = count($errors);

        /*
        * 비교 가능한 기록이 없는 경우
        */
        if ($count === 0) {
            return [
                'count' => 0,
                'mae' => null,
                'wape' => null,
                'bias_rate' => null,
                'comparison_records' => [],
            ];
        }

        $absoluteError = array_sum($errors);

        return [
            /*
            * 기존 반환 항목
            */
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

            /*
            * 새로 추가한 날짜별 비교 결과
            */
            'comparison_records' => $comparisonRecords,
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
        /*
        * 정상 영업일의 기록을 날짜순으로 사용합니다.
        *
        * 기존에 전달받은 기록을 그대로 사용하며
        * 별도로 데이터베이스를 조회하지 않습니다.
        */
        $rows = $normal->values();

        $baseErrors = [];
        $candidateErrors = [];

        $baseShortageTotal = 0.0;
        $candidateShortageTotal = 0.0;

        /*
        * 기존 비교 대상 날짜 범위를 유지합니다.
        */
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

            /*
            * 현재 날짜보다 이전의 기록만 사용합니다.
            *
            * 현재 날짜의 마감 결과를 미리 알고
            * 추천량을 계산하는 상황을 방지합니다.
            */
            $past = $rows
                ->slice(0, $i)
                ->values();

            if ($past->count() < self::MIN_HISTORY) {
                continue;
            }

            /*
            * 해당 날짜의 추정 판매량입니다.
            *
            * 사용자가 판매량을 입력한 값이 아니라
            * 기존 기록을 바탕으로 계산한 수량입니다.
            */
            $actual = $this->demandValue(
                $actualRow
            );

            if ($actual === null) {
                continue;
            }

            /*
            * 이전 정상 영업일 중
            * 전체 확보 수량과 남은 수량을
            * 확인할 수 있는 날짜만 사용합니다.
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

            /*
            * 기록이 지나치게 적으면
            * 생산량 감량 여부를 판단하지 않습니다.
            */
            if ($inventoryRows->count() < 3) {
                continue;
            }

            $availableTotal = 0.0;
            $remainingTotal = 0.0;

            /*
            * 생산 규모를 고려하기 위해
            * 날짜별 비율을 단순 평균하지 않고
            * 전체 수량을 합산한 뒤 비율을 계산합니다.
            */
            foreach ($inventoryRows as $row) {
                $availableTotal += (float) (
                    $row['daily_available_quantity'] ?? 0
                );

                $remainingTotal += (float) (
                    $row['daily_remaining_quantity'] ?? 0
                );
            }

            if ($availableTotal <= 0) {
                continue;
            }

            /*
            * 전체 확보 수량 대비 남은 수량 비율입니다.
            *
            * 기존 기준과 동일하게
            * 폐기와 이월예정을 함께 고려합니다.
            */
            $surplusRate =
                $remainingTotal / $availableTotal;

            /*
            * 기존 과잉생산 판단 기준을 유지합니다.
            *
            * 전체 수량의 20% 이상이 남았던 경우에만
            * 감량 후보를 계산합니다.
            */
            if ($surplusRate < 0.20) {
                continue;
            }

            /*
            * 기존 추천 계산 결과입니다.
            */
            $basePrediction = (float) $this->baseline(
                $past,
                Carbon::parse($actualRow['date'])
            )['center'];

            if ($basePrediction <= 0) {
                continue;
            }

            /*
            * 기존에는 무조건 5% 감량을 계산했습니다.
            *
            * 이제는 남은 수량 비율에 따라
            * 감량 폭을 계산합니다.
            *
            * 목표 잔여율 10%는 초기 참고 기준입니다.
            */
            $targetRemainingRate = 0.10;

            $excessAboveTarget = max(
                0.0,
                $surplusRate - $targetRemainingRate
            );

            /*
            * 기록이 적을수록 감량 폭을 낮춥니다.
            *
            * 충분한 기록이 쌓이면
            * 실제 남은 수량 비율을 더 많이 반영합니다.
            */
            $historyCount = $inventoryRows->count();

            $evidenceStrength =
                $historyCount / ($historyCount + 4);

            /*
            * 과도한 감량을 방지하기 위해
            * 한 번의 감량 후보는 최대 30%로 제한합니다.
            *
            * 30%는 초기 안전 범위입니다.
            */
            $decreaseRate = min(
                0.30,
                $excessAboveTarget * $evidenceStrength
            );

            /*
            * 실제 남은 수량 비율에 따른
            * 감량 후보를 계산합니다.
            */
            $candidatePrediction = max(
                0,
                (float) round(
                    $basePrediction * (1 - $decreaseRate)
                )
            );

            /*
            * 감량 후보가 기존 추천량과 같다면
            * 두 수량을 비교할 필요가 없습니다.
            */
            if ($candidatePrediction >= $basePrediction) {
                continue;
            }

            /*
            * 기존 추천량과 감량 후보가
            * 추정 판매량에서 얼마나 차이 나는지
            * 각각 계산합니다.
            */
            $baseErrors[] = abs(
                $basePrediction - $actual
            );

            $candidateErrors[] = abs(
                $candidatePrediction - $actual
            );

            /*
            * 기존 추천량으로 부족했을 수량입니다.
            */
            $baseShortageTotal += max(
                0,
                $actual - $basePrediction
            );

            /*
            * 감량 후보로 생산했다면
            * 부족했을 수량입니다.
            */
            $candidateShortageTotal += max(
                0,
                $actual - $candidatePrediction
            );
        }

        $count = count($baseErrors);

        /*
        * 비교 가능한 기록이 없으면
        * 기존 반환 구조에 맞춰 빈 결과를 반환합니다.
        */
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

        /*
        * 추정 판매량과 추천량의 평균 수량 차이입니다.
        */
        $baseMae =
            array_sum($baseErrors) / $count;

        $candidateMae =
            array_sum($candidateErrors) / $count;

        /*
        * 기존 반환 항목을 유지합니다.
        *
        * 감량 후보가 실제 추천 생산량에
        * 자동 적용되는 것은 아닙니다.
        */
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
     * 특정 생산량 후보를 과거 판매 기록과 비교합니다.
     *
     * 목표:
     * 1. 판매할 수량이 부족할 위험 확인
     * 2. 지나치게 많이 생산할 위험 확인
     *
     * 실제로 해당 수량을 생산한 결과가 아니므로
     * 판매 기회 손실과 예상 잔여 수량을
     * 확정된 결과로 취급하지 않습니다.
     *
     * 실제 생산 기록이나 추천량을 변경하지 않습니다.
     */
    public function evaluateProductionCandidate(
        array $record,
        int $candidateQuantity
    ): array {
        /*
        * 비교할 추천 생산량입니다.
        * 음수는 허용하지 않습니다.
        */
        $candidateQuantity = max(
            0,
            $candidateQuantity
        );

        /*
        * 해당 날짜에 실제 입력한 전체 생산량입니다.
        *
        * 하루에 생산 기록이 여러 건 있어도
        * 이미 날짜별로 합산된 값을 전달받습니다.
        */
        $actualProduction = max(
            0,
            (int) ($record['actual_production'] ?? 0)
        );

        /*
        * 해당 날짜에 사용한 이월재고입니다.
        */
        $carryoverIn = max(
            0,
            (int) ($record['carryover_in'] ?? 0)
        );

        /*
        * 실제 판매량을 직접 입력하지 않습니다.
        *
        * 기존 생산·이월재고·로스·폐기·이월예정
        * 기록을 바탕으로 계산된 추정 판매량입니다.
        */
        $estimatedSales = max(
            0,
            (int) ($record['estimated_sales'] ?? 0)
        );

        /*
        * 해당 날짜에 실제 등록된
        * 폐기량과 이월예정입니다.
        */
        $actualWaste = max(
            0,
            (int) ($record['waste'] ?? 0)
        );

        $actualCarryoverOut = max(
            0,
            (int) ($record['carryover_out'] ?? 0)
        );

        /*
        * 해당 날짜의 실제 로스량입니다.
        *
        * 로스는 판매할 수 없는 수량이므로
        * 실제 판매 가능 수량에서는 제외합니다.
        */
        $actualLoss = max(
            0,
            (int) ($record['loss'] ?? 0)
        );

        /*
        * 실제 확보한 전체 수량입니다.
        *
        * 생산량과 이월재고를 합산한 값이며
        * 아직 로스를 차감하지 않았습니다.
        *
        * 기존 반환값의 의미를 유지합니다.
        */
        $actualAvailable =
            $actualProduction + $carryoverIn;

        /*
        * 로스를 제외한 실제 판매 가능 수량입니다.
        */
        $actualSellableAvailable = max(
            0,
            $actualAvailable - $actualLoss
        );

        /*
        * 비교 대상인 추천량으로 생산했다면
        * 확보할 수 있었던 전체 수량입니다.
        *
        * 실제 로스량을 그대로 차감하지 않습니다.
        *
        * 생산량이 달라지면 로스량도
        * 달라질 수 있기 때문입니다.
        */
        $candidateAvailable =
            $candidateQuantity + $carryoverIn;

        /*
        * 해당 추천량으로 생산했을 때
        * 과거 추정 판매량보다 부족한 수량입니다.
        *
        * 과거 판매 기록을 기준으로 한 비교이며,
        * 미래의 실제 판매 부족을 확정하지 않습니다.
        */
        $confirmedShortfall = max(
            0,
            $estimatedSales - $candidateAvailable
        );

        /*
        * 해당 추천량이 과거 추정 판매량보다
        * 많은 경우의 수량 차이입니다.
        *
        * 실제 폐기 예상량이 아닙니다.
        */
        $possibleExcess = max(
            0,
            $candidateAvailable - $estimatedSales
        );

        /*
        * 실제 기록에서 폐기와 이월예정이
        * 모두 없었던 날짜입니다.
        *
        * 추가 판매 기회가 있었을 가능성은 있지만
        * 실제 품절 여부를 확정하지 않습니다.
        */
        $possibleSellout =
            $actualSellableAvailable > 0
            && $estimatedSales > 0
            && $actualWaste === 0
            && $actualCarryoverOut === 0;

        /*
        * 실제로 폐기하거나 다음 날로
        * 이월할 예정이었던 수량입니다.
        */
        $actualRemaining =
            $actualWaste + $actualCarryoverOut;

        /*
        * 실제 확보한 전체 수량 대비
        * 폐기와 이월예정의 비율입니다.
        *
        * 기존 계산 기준을 유지합니다.
        */
        $actualRemainingRate =
            $actualAvailable > 0
                ? round(
                    $actualRemaining / $actualAvailable,
                    4
                )
                : null;

        /*
        * 추천 생산량의 부족 가능성을 구분합니다.
        *
        * confirmed_shortfall:
        *   과거 추정 판매량보다 추천량이 적음
        *
        * unknown_demand:
        *   폐기·이월예정이 없어 추가 판매 가능성이 있음
        *
        * not_detected:
        *   위 두 상황이 확인되지 않음
        */
        if ($confirmedShortfall > 0) {
            $shortageRisk = 'confirmed_shortfall';
        } elseif ($possibleSellout) {
            $shortageRisk = 'unknown_demand';
        } else {
            $shortageRisk = 'not_detected';
        }

        /*
        * 기존 반환 항목은 그대로 유지합니다.
        *
        * actual_sellable_available 항목만 추가하여
        * 실제 로스를 차감한 수량도 확인할 수 있게 합니다.
        */
        return [
            'date' => $record['date'] ?? null,

            'candidate_quantity' =>
                $candidateQuantity,

            'candidate_available' =>
                $candidateAvailable,

            'actual_production' =>
                $actualProduction,

            'actual_available' =>
                $actualAvailable,

            /*
            * 로스를 제외한 실제 판매 가능 수량
            */
            'actual_sellable_available' =>
                $actualSellableAvailable,

            'estimated_sales' =>
                $estimatedSales,

            /*
            * 실제 마감 기록
            */
            'actual_waste' =>
                $actualWaste,

            'actual_carryover_out' =>
                $actualCarryoverOut,

            'actual_loss' =>
                $actualLoss,

            'actual_remaining_rate' =>
                $actualRemainingRate,

            /*
            * 과거 추정 판매량과 추천량의 비교
            */
            'confirmed_shortfall' =>
                $confirmedShortfall,

            'possible_excess' =>
                $possibleExcess,

            'shortage_risk' =>
                $shortageRisk,

            'possible_sellout' =>
                $possibleSellout,

            /*
            * 해당 추천량으로 실제 생산한 결과가
            * 검증됐다는 뜻은 아닙니다.
            */
            'actual_outcome_verified' => false,
        ];
    }

    /**
     * 과거 추천량과 실제 판매 결과를 이용해
     * 생산량 조정 후보를 계산합니다.
     *
     * 추천 정확도 검증 기록은 전체 기간을 보존하지만,
     * 실제 추천량 조정은 기존 정책에 따라
     * 최근 12개월의 동일 요일을 우선 사용합니다.
     *
     * 최근 3개월 70%, 이전 9개월 30%를 적용합니다.
     *
     * 기존 추천량은 변경하지 않고
     * 조정 후보와 계산 근거만 반환합니다.
     */
    public function calculateAdaptiveAdjustment(
        array $records,
        int $currentQuantity,
        Carbon $targetDate
    ): array {
        /*
        * 기존 반환 구조를 유지합니다.
        *
        * 판매 기회 관련 항목만 추가합니다.
        */
        $result = [
            'original_quantity' => $currentQuantity,
            'suggested_quantity' => $currentQuantity,
            'adjustment_quantity' => 0,
            'adjustment_rate' => 0,

            'record_count' => 0,
            'recent_count' => 0,
            'previous_count' => 0,

            'possible_sellout_days' => 0,
            'shortage_days' => 0,

            'reason' => null,
        ];

        if ($currentQuantity <= 0) {
            return $result;
        }

        $target = $targetDate->copy()->startOfDay();

        /*
        * 최근 기록을 구분하기 위한 날짜입니다.
        *
        * 3개월 이전 기록을 버리지는 않습니다.
        */
        $recentFrom = $target->copy()->subMonths(3);

        $weekday = $target->dayOfWeek;

        $recentRecords = [];
        $previousRecords = [];

        $possibleSelloutDays = 0;
        $shortageDays = 0;

        foreach ($records as $record) {
            if (
                !is_array($record)
                || empty($record['date'])
            ) {
                continue;
            }

            $date = Carbon::parse(
                $record['date']
            )->startOfDay();

            /*
            * 추천 대상일보다 이전의
            * 같은 요일 기록만 사용합니다.
            */
            if (
                !$date->lt($target)
                || $date->dayOfWeek !== $weekday
            ) {
                continue;
            }

            /*
            * 같은 날짜에 서로 다른 추천량이 있다면
            * 어느 수량을 기준으로 비교해야 할지
            * 불명확하므로 제외합니다.
            */
            if (
                $record['has_multiple_recommendations']
                ?? false
            ) {
                continue;
            }

            $recommended = max(
                0,
                (int) (
                    $record['recommended_quantity'] ?? 0
                )
            );

            $carryoverIn = max(
                0,
                (int) ($record['carryover_in'] ?? 0)
            );

            $estimatedSales = max(
                0,
                (int) ($record['estimated_sales'] ?? 0)
            );

            $waste = max(
                0,
                (int) ($record['waste'] ?? 0)
            );

            $carryoverOut = max(
                0,
                (int) ($record['carryover_out'] ?? 0)
            );

            $status = (string) (
                $record['status'] ?? 'uncertain'
            );

            /*
            * 처음 추천한 생산량과
            * 해당 날짜의 이월재고를 합산합니다.
            */
            $available =
                $recommended + $carryoverIn;

            if ($available <= 0) {
                continue;
            }

            /*
            * 판매 기회가 더 있었을 가능성이 있는 날입니다.
            *
            * 실제 판매량을 추가로 만들어 계산하지 않습니다.
            */
            $possibleSellout = (bool) (
                $record['possible_sellout'] ?? false
            );

            /*
            * 전달받은 값이 없는 과거 기록도
            * 동일한 기준으로 확인할 수 있습니다.
            */
            if (!$possibleSellout) {
                $actualProduction = max(
                    0,
                    (int) (
                        $record['actual_production'] ?? 0
                    )
                );

                $loss = max(
                    0,
                    (int) ($record['loss'] ?? 0)
                );

                $actualSellable = max(
                    0,
                    $actualProduction + $carryoverIn - $loss
                );

                $possibleSellout =
                    $actualSellable > 0
                    && $estimatedSales > 0
                    && $waste === 0
                    && $carryoverOut === 0;
            }

            /*
            * 기존에 비교 가능했던 기록과
            * 판매 기회 가능성이 있는 기록을 구분합니다.
            */
            $comparable = in_array(
                $status,
                [
                    'exact',
                    'near',
                    'below_observed_sales',
                    'possible_excess',
                ],
                true
            );

            if (!$comparable && !$possibleSellout) {
                continue;
            }

            /*
            * 추천량과 이월재고의 합계보다
            * 추정 판매량이 많았던 수량입니다.
            */
            $shortage = max(
                0,
                $estimatedSales - $available
            );

            $shortageRate = min(
                1.0,
                max(0.0, $shortage / $available)
            );

            if ($shortage > 0) {
                $shortageDays++;
            }

            if ($possibleSellout) {
                $possibleSelloutDays++;
            }

            /*
            * 최근 기록일수록 높은 비중을 둡니다.
            *
            * 오래된 기록도 분석에 포함합니다.
            */
            $daysAgo = max(
                0,
                $date->diffInDays($target)
            );

            $halfLifeDays = 90.0;

            $weight = pow(
                0.5,
                $daysAgo / $halfLifeDays
            );

            $weightedRecord = [
                'shortage_rate' => $shortageRate,
                'possible_sellout' => $possibleSellout,
                'weight' => $weight,
            ];

            if ($date->gte($recentFrom)) {
                $recentRecords[] = $weightedRecord;
            } else {
                $previousRecords[] = $weightedRecord;
            }
        }

        $recentCount = count($recentRecords);
        $previousCount = count($previousRecords);

        $recordCount = $recentCount + $previousCount;

        $result['record_count'] = $recordCount;
        $result['recent_count'] = $recentCount;
        $result['previous_count'] = $previousCount;

        $result['possible_sellout_days'] =
            $possibleSelloutDays;

        $result['shortage_days'] =
            $shortageDays;

        if ($recordCount === 0) {
            return $result;
        }

        /*
        * 기간별 가중 평균을 계산합니다.
        *
        * 부족 비율과 판매 기회 가능성을
        * 각각 구분해서 집계합니다.
        */
        $summarize = static function (array $items): ?array {
            if ($items === []) {
                return null;
            }

            $totalWeight = 0.0;
            $shortageSum = 0.0;
            $selloutWeight = 0.0;

            foreach ($items as $item) {
                $weight = (float) $item['weight'];

                $totalWeight += $weight;

                $shortageSum +=
                    (float) $item['shortage_rate'] * $weight;

                if ($item['possible_sellout']) {
                    $selloutWeight += $weight;
                }
            }

            if ($totalWeight <= 0) {
                return null;
            }

            return [
                'shortage_rate' =>
                    $shortageSum / $totalWeight,

                'sellout_rate' =>
                    $selloutWeight / $totalWeight,
            ];
        };

        $recentSummary = $summarize($recentRecords);
        $previousSummary = $summarize($previousRecords);

        /*
        * 최근 3개월 70%
        * 그 이전 전체 기록 30%
        *
        * 한쪽 기간에 기록이 없다면
        * 기록이 있는 기간만 사용합니다.
        */
        if (
            $recentSummary !== null
            && $previousSummary !== null
        ) {
            $averageShortageRate =
                $recentSummary['shortage_rate'] * 0.70
                + $previousSummary['shortage_rate'] * 0.30;

            $averageSelloutRate =
                $recentSummary['sellout_rate'] * 0.70
                + $previousSummary['sellout_rate'] * 0.30;
        } elseif ($recentSummary !== null) {
            $averageShortageRate =
                $recentSummary['shortage_rate'];

            $averageSelloutRate =
                $recentSummary['sellout_rate'];
        } else {
            $averageShortageRate =
                $previousSummary['shortage_rate'];

            $averageSelloutRate =
                $previousSummary['sellout_rate'];
        }

        /*
        * 기록이 적으면 추천량 변경 폭을 낮춥니다.
        *
        * 기존 안정화 기준을 유지합니다.
        */
        $evidenceStrength =
            $recordCount / ($recordCount + 4);

        /*
        * 실제로 추천량이 부족했던 기록을
        * 우선적인 증량 근거로 사용합니다.
        */
        $shortageAdjustmentRate =
            $averageShortageRate * $evidenceStrength;

        /*
        * 폐기·이월예정이 없었던 기록은
        * 추가 판매 기회의 가능성으로만 반영합니다.
        *
        * 다음 두 조건이 모두 필요합니다.
        *
        * 1. 추천량 부족 기록이 반복됨
        * 2. 폐기·이월예정이 없는 날도 반복됨
        *
        * 판매 기회만을 근거로
        * 생산량을 무조건 늘리지 않습니다.
        */
        $selloutAdjustmentRate = 0.0;

        if (
            $shortageDays >= 2
            && $possibleSelloutDays >= 2
            && $averageShortageRate > 0
        ) {
            /*
            * 판매 기회 가능성의 추가 반영폭은
            * 초기 기준으로 최대 8%로 제한합니다.
            *
            * 실제 추가 수요는 확인할 수 없으므로
            * 기존 부족 수량에 대한 판단보다
            * 약하게 반영합니다.
            */
            $selloutAdjustmentRate =
                min(
                    0.08,
                    $averageSelloutRate * 0.08
                ) * $evidenceStrength;
        }

        /*
        * 두 정보를 합쳐 최종 증량 비율을 계산합니다.
        *
        * 날짜별 부족 비율의 상한은
        * 앞선 계산에서 이미 적용했습니다.
        */
        $effectiveRate =
            $shortageAdjustmentRate
            + $selloutAdjustmentRate;

        $adjustmentQuantity = max(
            0,
            (int) round(
                $currentQuantity * $effectiveRate
            )
        );

        $suggestedQuantity =
            $currentQuantity + $adjustmentQuantity;

        $result['suggested_quantity'] =
            $suggestedQuantity;

        $result['adjustment_quantity'] =
            $adjustmentQuantity;

        $result['adjustment_rate'] = round(
            $effectiveRate,
            4
        );

        /*
        * 사용자에게 보여주는 이유는
        * 실제 확인된 기록 중심으로 설명합니다.
        */
        if ($adjustmentQuantity > 0) {
            if ($selloutAdjustmentRate > 0) {
                $result['reason'] =
                    "같은 요일의 과거 기록에서 추천 생산량이 부족한 날과 "
                    . "폐기·이월예정이 없는 날이 반복되어 "
                    . "추천 생산량을 {$adjustmentQuantity}개 늘렸습니다.";
            } else {
                $result['reason'] =
                    "같은 요일의 과거 추정 판매량을 참고해 "
                    . "추천 생산량을 {$adjustmentQuantity}개 늘렸습니다.";
            }
        }

        return $result;
    }

    /**
     * 과거 추천 검증 결과를 이용해
     * 기존 추천 생산량을 조정합니다.
     *
     * 기존 추천 알고리즘의 결과를 유지하면서
     * 충분한 검증 근거가 있는 경우에만
     * 데이터로 계산한 조정 후보를 적용합니다.
     *
     * 호출자가 전달한 검증 결과를 사용하므로
     * 이 함수에서는 DB를 조회하지 않습니다.
     */
    public function applyHistoricalValidation(
        array $recommendation,
        array $evidence,
        array $backtest,
        array $candidate
    ): array {
        /*
        * 현재 추천 생산량입니다.
        */
        $originalCenter = $recommendation['center'] ?? null;

        $recommendation['metrics'] ??= [];

        /*
        * 기본값은 추천량을 변경하지 않은 상태입니다.
        *
        * 기존 데이터 구조를 유지합니다.
        */
        $recommendation['metrics']['historical_adjustment'] = [
            'applied' => false,
            'original_quantity' => $originalCenter,
            'adjusted_quantity' => $originalCenter,
            'adjustment_quantity' => 0,
            'reason' => null,
        ];

        /*
        * 추천량이 없거나 0개 이하라면
        * 과거 기록으로 추가 증량하지 않습니다.
        */
        if (
            $originalCenter === null
            || !is_numeric($originalCenter)
            || (int) $originalCenter <= 0
        ) {
            return $recommendation;
        }

        $originalCenter = (int) $originalCenter;

        /*
        * 과거 기록으로 계산한 수량이
        * 현재 추천량을 기준으로 만들어졌는지 확인합니다.
        */
        $candidateOriginal =
            $candidate['original_quantity'] ?? null;

        if (
            !is_numeric($candidateOriginal)
            || (int) $candidateOriginal !== $originalCenter
        ) {
            return $recommendation;
        }

        /*
        * 추천 신뢰도가 충분할 때만
        * 과거 기록에 따른 추가 증량을 허용합니다.
        */
        if (
            ($recommendation['confidence'] ?? null)
            !== 'normal'
        ) {
            return $recommendation;
        }

        /*
        * 같은 요일의 과거 기록에서
        * 추천 생산량이 부족했던 경향이
        * 확인됐는지 확인합니다.
        *
        * 폐기·이월예정이 없다는 사실만으로
        * 증량하지는 않습니다.
        */
        if (
            ($evidence['eligible'] ?? false) !== true
            || ($evidence['direction'] ?? null)
                !== 'increase'
        ) {
            return $recommendation;
        }

        /*
        * 현재 추천량보다 큰 수량이
        * 계산됐는지 먼저 확인합니다.
        */
        $adjustedCenter =
            $candidate['suggested_quantity'] ?? null;

        if (
            !is_numeric($adjustedCenter)
            || (int) $adjustedCenter <= $originalCenter
        ) {
            return $recommendation;
        }

        $adjustedCenter = (int) $adjustedCenter;

        /*
        * 기존 수량 상한을 유지합니다.
        */
        if ($adjustedCenter > 100000) {
            return $recommendation;
        }

        /*
        * 과거 추천량 비교 결과입니다.
        */
        $backtestResult = (string) (
            $backtest['result'] ?? 'insufficient'
        );

        $comparedDays = max(
            0,
            (int) ($backtest['tested_days'] ?? 0)
        );

        $supportedDays = max(
            0,
            (int) (
                $backtest['candidate_supported_days'] ?? 0
            )
        );

        /*
        * 추천량 변경 전후의 부족 수량입니다.
        */
        $originalShortfall = max(
            0,
            (int) (
                $backtest['original_shortfall_total'] ?? 0
            )
        );

        $candidateShortfall = max(
            0,
            (int) (
                $backtest['candidate_shortfall_total'] ?? 0
            )
        );

        /*
        * 추천량 변경 전후의
        * 남을 가능성이 있는 수량입니다.
        *
        * 실제 폐기량과 같은 의미가 아닙니다.
        */
        $originalPossibleExcess = max(
            0,
            (int) (
                $backtest['original_possible_excess_total']
                ?? 0
            )
        );

        $candidatePossibleExcess = max(
            0,
            (int) (
                $backtest['candidate_possible_excess_total']
                ?? 0
            )
        );

        /*
        * 생산량을 늘렸을 때 줄어드는
        * 부족 수량입니다.
        */
        $shortfallReduction = max(
            0,
            $originalShortfall - $candidateShortfall
        );

        /*
        * 생산량을 늘렸을 때 추가로
        * 남을 가능성이 있는 수량입니다.
        */
        $additionalPossibleExcess = max(
            0,
            $candidatePossibleExcess - $originalPossibleExcess
        );

        /*
        * 부족 수량이 줄지 않으면
        * 생산량을 늘리지 않습니다.
        */
        if ($shortfallReduction <= 0) {
            return $recommendation;
        }

        /*
        * 부족 수량 감소보다
        * 남는 수량 증가가 크다면
        * 생산량 변경을 보류합니다.
        */
        if ($additionalPossibleExcess > $shortfallReduction) {
            return $recommendation;
        }

        /*
        * 비교한 날짜 중
        * 생산량 증가를 뒷받침하는 날짜의 비율입니다.
        */
        $supportedRate = $comparedDays > 0
            ? $supportedDays / $comparedDays
            : 0.0;

        /*
        * 기록이 충분한 경우에는
        * 기존 판단 결과를 사용합니다.
        */
        $normalApproval =
            $backtestResult === 'promising';

        /*
        * 기록이 아직 적은 경우입니다.
        *
        * 다음 조건을 모두 충족할 때만
        * 제한적으로 증량을 허용합니다.
        *
        * 1. 비교 가능한 기록이 최소 2일 존재
        * 2. 생산량 증가를 뒷받침하는 날짜가 75% 이상
        * 3. 부족 수량이 실제로 감소
        * 4. 남는 수량 증가가 부족 수량 감소보다 크지 않음
        *
        * 75%는 기록이 적을 때 적용하는
        * 보수적인 초기 기준입니다.
        */
        $limitedApproval =
            $backtestResult === 'insufficient'
            && $comparedDays >= 2
            && $supportedRate >= 0.75
            && $shortfallReduction > 0
            && $additionalPossibleExcess <= $shortfallReduction;

        /*
        * 어느 조건도 충족하지 못하면
        * 기존 추천량을 유지합니다.
        */
        if (!$normalApproval && !$limitedApproval) {
            return $recommendation;
        }

        /*
        * 실제 증량 수량입니다.
        */
        $adjustmentQuantity =
            $adjustedCenter - $originalCenter;

        /*
        * 추천 생산량과 추천 범위를
        * 동일한 수량만큼 이동합니다.
        */
        $originalMin = $recommendation['min'] ?? null;
        $originalMax = $recommendation['max'] ?? null;

        $recommendation['center'] = $adjustedCenter;

        if (
            $originalMin !== null
            && is_numeric($originalMin)
        ) {
            $recommendation['min'] = max(
                0,
                (int) $originalMin + $adjustmentQuantity
            );
        }

        if (
            $originalMax !== null
            && is_numeric($originalMax)
        ) {
            $recommendation['max'] = min(
                100000,
                (int) $originalMax + $adjustmentQuantity
            );
        }

        /*
        * 추천 이유 화면에서 사용하는
        * 수량도 최종 추천량과 일치시킵니다.
        */
        if (
            isset(
                $recommendation['explanation']['calculation']
            )
        ) {
            $calculation = &$recommendation[
                'explanation'
            ]['calculation'];

            $calculation['recommended_production'] =
                $adjustedCenter;

            $calculation['minimum_recommendation'] =
                $recommendation['min'] ?? null;

            $calculation['maximum_recommendation'] =
                $recommendation['max'] ?? null;

            unset($calculation);
        }

        /*
        * 사용자에게 보여줄 안내 문장입니다.
        *
        * 개발 과정이나 내부 판단 방식 대신
        * 실제 참고한 날짜와 증량 수량을 설명합니다.
        */
        $sameWeekdayCount = max(
            0,
            (int) (
                $evidence['same_weekday_count'] ?? 0
            )
        );

        $shortageDays = max(
            0,
            (int) ($evidence['shortage_days'] ?? 0)
        );

        if (
            $sameWeekdayCount > 0
            && $shortageDays > 0
        ) {
            $reason =
                "같은 요일의 과거 {$sameWeekdayCount}일치 기록 중 "
                . "{$shortageDays}일은 추천 생산량이 부족했던 것으로 추정되어 "
                . "생산량을 {$adjustmentQuantity}개 늘렸습니다.";
        } else {
            $reason =
                "과거 추정 판매량을 참고해 "
                . "추천 생산량을 {$adjustmentQuantity}개 늘렸습니다.";
        }

        /*
        * 실제 적용된 추천량과
        * 계산 근거를 기존 구조에 저장합니다.
        */
        $recommendation['metrics']['historical_adjustment'] = [
            'applied' => true,

            'original_quantity' => $originalCenter,

            'adjusted_quantity' => $adjustedCenter,

            'adjustment_quantity' =>
                $adjustmentQuantity,

            'adjustment_rate' =>
                $candidate['adjustment_rate'] ?? null,

            'tested_days' => $comparedDays,

            'shortfall_reduction' =>
                $shortfallReduction,

            'supported_rate' =>
                round($supportedRate, 4),

            'reason' => $reason,
        ];

        /*
        * 기존 추천 이유는 유지합니다.
        *
        * 동일한 문장은 중복으로 추가하지 않습니다.
        */
        $recommendation['reasons'] ??= [];

        if (
            !in_array(
                $reason,
                $recommendation['reasons'],
                true
            )
        ) {
            $recommendation['reasons'][] = $reason;
        }

        return $recommendation;
    }

    /**
     * 생산 기록에서 참고한 추천 결과를 저장합니다.
     *
     * 기존 추천 결과를 보존하면서
     * 과거 기록을 통한 추천량 조정 과정도 함께 저장합니다.
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

        /*
        * 사용자에게 실제 표시한 최종 추천량입니다.
        */
        $finalQuantity = (int) (
            $recommendation['center'] ?? 0
        );

        /*
        * 과거 기록을 통한 추천량 조정 결과입니다.
        */
        $adjustment = $recommendation['metrics']
            ['historical_adjustment'] ?? [];

        /*
        * 조정 전 추천량입니다.
        *
        * 조정 정보가 없다면
        * 현재 최종 추천량을 기준으로 저장합니다.
        */
        $originalQuantity = $adjustment[
            'original_quantity'
        ] ?? $finalQuantity;

        if (
            !is_numeric($originalQuantity)
            || (int) $originalQuantity < 0
        ) {
            $originalQuantity = $finalQuantity;
        }

        $originalQuantity = (int) $originalQuantity;

        /*
        * 실제 조정 여부는 저장할 수량의 차이로 판단합니다.
        */
        $adjustmentApplied =
            $originalQuantity !== $finalQuantity;

        $adjustmentQuantity =
            $finalQuantity - $originalQuantity;

        /*
        * 추천량 조정 과정의 기록입니다.
        *
        * 추후 기존 추천 방식과
        * 개선된 추천 방식의 결과를 비교하는 데 활용합니다.
        */
        $adjustmentMeta = [
            'version' => 1,

            'applied' => $adjustmentApplied,

            'original_quantity' =>
                $originalQuantity,

            'adjusted_quantity' =>
                $finalQuantity,

            'adjustment_quantity' =>
                $adjustmentQuantity,

            'reason' => $adjustmentApplied
                ? ($adjustment['reason'] ?? null)
                : null,
        ];

        /*
        * 기존 추천 결과와 조정 내역을 저장합니다.
        */
        return ProductionRecommendationSnapshot::create([
            'store_id' => $storeId,

            'product_id' => $productId,

            'production_batch_id' => $batchId,

            'target_date' => $date,

            'center_quantity' => $finalQuantity,

            'range_min' =>
                (int) ($recommendation['min'] ?? $finalQuantity),

            'range_max' =>
                (int) ($recommendation['max'] ?? $finalQuantity),

            'confidence' =>
                $recommendation['confidence'],

            'calculation_version' =>
                'v3-adaptive-demand',

            'reasons' =>
                $recommendation['reasons'] ?? [],

            'excluded_conditions' => [],

            'adjustment_meta' => $adjustmentMeta,

            'data_from' => $targetDate
                ->copy()
                ->subYear()
                ->toDateString(),

            'data_to' => $targetDate
                ->copy()
                ->subDay()
                ->toDateString(),

            'referenced' => $referenced,

            'deviation_reason' =>
                $deviationReason,

            'generated_at' => now(),
        ]);
    }
}