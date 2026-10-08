<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductionManagementController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * 업무일 합계 기준의 재고 처리 가능 수량을 검증합니다.
 * 생산일별 lot 검증은 별도 DB 통합 테스트가 필요합니다.
 */
class DispositionAvailabilityTest extends TestCase
{
    /**
     * 수정 대상 처리 유형의 기존 수량은 교체되므로 중복 차감하지 않습니다.
     * 다른 처리 유형 및 이월 예정 수량은 가용량에서 제외합니다(A 방식).
     *
     * @return iterable<string, array{string, int}>
     */
    public static function dispositionCases(): iterable
    {
        yield 'loss excludes carryover reservation' => ['loss', 4];
        yield 'waste excludes carryover reservation' => ['waste', 5];
        yield 'other outflow excludes carryover reservation' => ['other_outflow', 4];
        yield 'carryover replaces existing reservation' => ['carryover', 6];
    }

    #[DataProvider('dispositionCases')]
    public function test_available_quantity_respects_other_allocations(string $type, int $expected): void
    {
        // 생산 8개 + 이월 2개, 로스 1개, 폐기 2개, 기타 출고 1개, 이월 예정 3개.
        $row = [
            'production' => 8,
            'carryover_in' => 2,
            'operational_loss' => 1,
            'operational_waste' => 2,
            'other_outflow' => 1,
            'carryover_out' => 3,
        ];

        $controller = (new ReflectionClass(ProductionManagementController::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(ProductionManagementController::class, 'availableQuantityForDisposition');

        self::assertSame($expected, $method->invoke($controller, $row, $type));
    }

    public function test_available_quantity_never_becomes_negative(): void
    {
        $row = [
            'production' => 1,
            'carryover_in' => 0,
            'operational_loss' => 1,
            'operational_waste' => 1,
            'other_outflow' => 1,
            'carryover_out' => 1,
        ];

        $controller = (new ReflectionClass(ProductionManagementController::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(ProductionManagementController::class, 'availableQuantityForDisposition');

        self::assertSame(0, $method->invoke($controller, $row, 'loss'));
    }
}
