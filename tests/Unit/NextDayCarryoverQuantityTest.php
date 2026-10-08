<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductionManagementController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 다음 날 이월 재고를 이미 사용한 경우, 전날 이월 수정을 차단하는 규칙을 검증합니다.
 *
 * 실제 DB에서 원 생산일별 합계를 조회하는 부분은 별도 통합 테스트가 필요합니다.
 */
class NextDayCarryoverQuantityTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function quantityCases(): iterable
    {
        yield 'unused carryover may be removed' => [0, 0, true];
        yield 'equal incoming and used is valid' => [7, 7, true];
        yield 'unused balance may remain' => [10, 7, true];
        yield 'cannot reduce below next day usage' => [5, 7, false];
        yield 'zero incoming cannot preserve existing usage' => [0, 1, false];
    }

    #[DataProvider('quantityCases')]
    public function test_next_day_usage_cannot_exceed_incoming(int $incoming, int $used, bool $allowed): void
    {
        // 생성자를 호출하지 않고 순수 수량 검증 함수만 실행합니다.
        $controller = (new ReflectionClass(ProductionManagementController::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(ProductionManagementController::class, 'assertNextDayCarryoverQuantity');

        if (! $allowed) {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(0);
        }

        $method->invoke($controller, $incoming, $used);

        if ($allowed) {
            self::assertTrue(true);
        }
    }
}
