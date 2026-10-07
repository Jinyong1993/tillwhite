<?php

namespace App\Services\Production;

use App\Models\Product;
use App\Models\ProductStockLot;
use App\Models\ProductStockMovement;
use App\Models\ProductionBatch;
use App\Models\ProductionConfirmation;
use App\Models\ProductionDailyClosure;
use App\Models\ProductionLoss;
use App\Models\ProductionOtherOutflow;
use App\Models\ProductionWaste;
use App\Models\StoreDailyStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductionDailyService
{
    /**
     * 선택한 점포와 날짜의 생산 업무 현황을 조립합니다.
     *
     * 마감 완료 여부는
     * 생산·로스·폐기·이월 확인 상태만으로 판단하고, 수량 합계는 각 업무 기록의
     * 실제 저장값을 그대로 사용합니다.
     */
    public function build(int $storeId, string $date, bool $includeDetails = false): array
    {
        $productsQuery = Product::query()->with('category');
        if ($includeDetails) {
            $productsQuery->with(['recipes.ingredients', 'recipes.steps']);
        }

        $products = $productsQuery
            ->where('store_id', $storeId)
            ->withTrashed()
            ->orderBy('product_category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $target = Carbon::parse($date);
        $productHistories = DB::table('product_master_histories')
            ->whereIn('product_id', $products->pluck('id'))
            ->where('effective_from', '<=', $target->copy()->endOfDay())
            ->where(function ($query) use ($target) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $target->copy()->startOfDay());
            })
            ->orderByDesc('effective_from')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        $categoryHistories = DB::table('product_category_histories')
            ->whereIn('product_category_id', $products->pluck('product_category_id'))
            ->where('effective_from', '<=', $target->copy()->endOfDay())
            ->where(function ($query) use ($target) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $target->copy()->startOfDay());
            })
            ->orderByDesc('effective_from')
            ->get()
            ->unique('product_category_id')
            ->keyBy('product_category_id');

        $batches = ProductionBatch::query()->where('store_id', $storeId)->whereDate('work_date', $date)->get()->groupBy('product_id');
        // 화면의 실적은 원 생산일 귀속 기준, 당일 재고 차감은 실제 업무 발생일 기준으로 분리합니다.
        $losses = ProductionLoss::query()
            ->with('reasons')
            ->where('store_id', $storeId)
            ->whereDate('attribution_date', $date)
            ->get()
            ->groupBy('product_id');
        $operationalLosses = ProductionLoss::query()
            ->with('reasons')
            ->where('store_id', $storeId)
            ->whereDate('work_date', $date)
            ->get()
            ->groupBy('product_id');
        $attributedWastes = ProductionWaste::query()
            ->with('reasons')
            ->where('store_id', $storeId)
            ->whereDate('attribution_date', $date)
            ->get()
            ->groupBy('product_id');
        $operationalWastes = ProductionWaste::query()
            ->with('reasons')
            ->where('store_id', $storeId)
            ->whereDate('work_date', $date)
            ->get()
            ->groupBy('product_id');
        $outflows = ProductionOtherOutflow::query()->where('store_id', $storeId)->whereDate('work_date', $date)->get()->groupBy('product_id');
        $incoming = ProductStockMovement::query()->where('store_id', $storeId)->whereDate('work_date', $date)->where('movement_type', 'carryover_in')->get()->groupBy('product_id');
        $outgoing = ProductStockMovement::query()->where('store_id', $storeId)->whereDate('work_date', $date)->where('movement_type', 'carryover_out')->get()->groupBy('product_id');
        $confirmations = ProductionConfirmation::query()->where('store_id', $storeId)->whereDate('work_date', $date)->get()->keyBy('product_id');

        $rows = $products->map(function (Product $product) use ($batches, $losses, $operationalLosses, $attributedWastes, $operationalWastes, $outflows, $incoming, $outgoing, $confirmations, $productHistories, $categoryHistories, $date, $storeId) {
            $history = $productHistories->get($product->id);
            $historicalCategoryId = $history?->product_category_id ?? $product->product_category_id;
            $categoryHistory = $categoryHistories->get($historicalCategoryId);
            $isActive = $history
                ? (bool) $history->is_active && !(bool) $history->is_deleted
                : (bool) $product->is_active && $product->deleted_at === null;

            $production = $this->sum($batches->get($product->id));
            $carryIn = $this->sum($incoming->get($product->id));
            $loss = $this->sum($losses->get($product->id));
            $operationalLoss = $this->sum($operationalLosses->get($product->id));
            $waste = $this->sum($attributedWastes->get($product->id));
            $operationalWaste = $this->sum($operationalWastes->get($product->id));
            $other = $this->sum($outflows->get($product->id));
            $carryOut = $this->sum($outgoing->get($product->id));
            $confirmation = $confirmations->get($product->id);
            $wasteRate = $this->wasteRate($waste, $production);
            $stockSources = $this->stockSourcesForDate($storeId, $product->id, $date);

            // 실제 기록이 존재하는데 과거 확인 플래그만 비어 있는 경우도 완료로 복구합니다.
            // 0개 확인은 기록 행이 없으므로 반드시 ProductionConfirmation의 명시적 플래그를 사용합니다.
            $productionConfirmed = (bool) ($confirmation?->production_confirmed ?? false) || ($batches->get($product->id)?->isNotEmpty() ?? false);
            $lossConfirmed = (bool) ($confirmation?->loss_confirmed ?? false) || ($losses->get($product->id)?->isNotEmpty() ?? false);
            $wasteConfirmed = (bool) ($confirmation?->waste_confirmed ?? false) || ($operationalWastes->get($product->id)?->isNotEmpty() ?? false);
            $dispositionConfirmed = (bool) ($confirmation?->disposition_confirmed ?? false)
                || ($outgoing->get($product->id)?->isNotEmpty() ?? false)
                || ($outflows->get($product->id)?->isNotEmpty() ?? false);

            return [
                'id' => $product->id,
                'name' => $history?->name ?? $product->name,
                'category_id' => $historicalCategoryId,
                'category_name' => $categoryHistory?->name ?? $product->category?->name ?? '-',
                'recipe' => $product->relationLoaded('recipes') ? $product->recipes->sortByDesc('id')->first()?->toArray() : null,
                'is_active' => $isActive,
                'production' => $production,
                'batches' => ($batches->get($product->id) ?? collect())->map(fn ($batch) => [
                    'id' => $batch->id,
                    'quantity' => $batch->quantity,
                    'note' => $batch->note,
                    'lock_version' => $batch->lock_version,
                    'created_at' => $batch->created_at,
                ])->values(),
                'loss_details' => ($losses->get($product->id) ?? collect())->flatMap(fn ($loss) => $loss->reasons->map(fn ($reason) => [
                    'reason_code' => $reason->reason_code,
                    'reason_text' => $reason->reason_text,
                    'quantity' => (int) $reason->quantity,
                    'origin_production_date' => $loss->attribution_date?->toDateString() ?? $date,
                    'stock_lot_id' => $loss->stock_lot_id,
                ]))->values(),
                'waste_details' => ($attributedWastes->get($product->id) ?? collect())->flatMap(fn ($wasteRow) => $wasteRow->reasons->map(fn ($reason) => [
                    'reason_code' => $reason->reason_code,
                    'reason_text' => $reason->reason_text,
                    'quantity' => (int) $reason->quantity,
                    'origin_production_date' => $wasteRow->attribution_date?->toDateString() ?? $date,
                    'stock_lot_id' => $wasteRow->stock_lot_id,
                ]))->values(),
                'operational_loss_details' => ($operationalLosses->get($product->id) ?? collect())->flatMap(fn ($lossRow) => $lossRow->reasons->map(fn ($reason) => [
                    'reason_code' => $reason->reason_code,
                    'reason_text' => $reason->reason_text,
                    'quantity' => (int) $reason->quantity,
                    'origin_production_date' => $lossRow->attribution_date?->toDateString() ?? $date,
                    'stock_lot_id' => $lossRow->stock_lot_id,
                ]))->values(),
                'operational_waste_details' => ($operationalWastes->get($product->id) ?? collect())->flatMap(fn ($wasteRow) => $wasteRow->reasons->map(fn ($reason) => [
                    'reason_code' => $reason->reason_code,
                    'reason_text' => $reason->reason_text,
                    'quantity' => (int) $reason->quantity,
                    'origin_production_date' => $wasteRow->attribution_date?->toDateString() ?? $date,
                    'stock_lot_id' => $wasteRow->stock_lot_id,
                ]))->values(),
                'carryover_in' => $carryIn,
                // 목록과 당일 요약은 실제 처리일 기준 수량을 사용합니다.
                'loss' => $operationalLoss,
                'operational_loss' => $operationalLoss,
                'attributed_loss' => $loss,
                'waste' => $operationalWaste,
                'operational_waste' => $operationalWaste,
                // 폐기율과 생산 성과는 원 생산일에 귀속된 폐기만 사용합니다.
                'attributed_waste' => $waste,
                'other_outflow' => $other,
                'carryover_out' => $carryOut,
                'stock_sources' => $stockSources,
                'waste_rate' => $wasteRate,
                'production_confirmed' => $productionConfirmed,
                'loss_confirmed' => $lossConfirmed,
                'waste_confirmed' => $wasteConfirmed,
                'disposition_confirmed' => $dispositionConfirmed,
                'zero_production_reason' => $confirmation?->zero_production_reason,
                'zero_production_reason_text' => $confirmation?->zero_production_reason_text,
                'complete' => ! $isActive || ($productionConfirmed && $lossConfirmed && $wasteConfirmed && $dispositionConfirmed),
            ];
        })->filter(function (array $row) {
            if ($row['is_active']) {
                return true;
            }

            // 삭제·비활성 제품도 선택 날짜에 실제 흐름이 남아 있으면 과거 기록 보존을 위해 표시합니다.
            return $row['production'] > 0
                || $row['carryover_in'] > 0
                || $row['carryover_out'] > 0
                || $row['loss'] > 0
                || $row['waste'] > 0
                || $row['other_outflow'] > 0;
        })->values();

        $activeRows = $rows->where('is_active', true);
        $totals = [
            'production' => $activeRows->sum('production'),
            'carryover' => $activeRows->sum('carryover_in'),
            'carryover_out' => $activeRows->sum('carryover_out'),
            'loss' => $activeRows->sum('operational_loss'),
            'waste' => $activeRows->sum('operational_waste'),
            'attributed_loss' => $activeRows->sum('attributed_loss'),
            'attributed_waste' => $activeRows->sum('attributed_waste'),
            'other_outflow' => $activeRows->sum('other_outflow'),
        ];
        $totals['waste_rate'] = $this->wasteRate(
            (int) $totals['attributed_waste'],
            (int) $totals['production'],
        );

        $closure = ProductionDailyClosure::query()->where('store_id', $storeId)->whereDate('work_date', $date)->first();
        $dailyStatus = StoreDailyStatus::query()->where('store_id', $storeId)->whereDate('work_date', $date)->first();
        $closureStatus = $dailyStatus?->status === 'closed' ? 'store_closed' : $closure?->status ?? 'in_progress';

        if ($closureStatus === 'in_progress' && Carbon::parse($date)->lt(Carbon::today('Asia/Seoul')) && $confirmations->isNotEmpty()) {
            $closureStatus = 'needs_confirmation';
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'complete_count' => $activeRows->where('complete', true)->count(),
            'required_count' => $activeRows->count(),
            'closure_status' => $closureStatus,
        ];
    }

    /**
     * 선택 날짜에 실제 사용할 수 있는 재고를 원 생산일별로 반환합니다.
     * 당일 생산과 이월 재고를 분리해 로스·폐기가 다른 생산일 재고를 중복 사용하지 않게 합니다.
     */
    private function stockSourcesForDate(int $storeId, int $productId, string $date): array
    {
        $incomingLotIds = ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->where('movement_type', 'carryover_in')
            ->pluck('stock_lot_id');

        $lots = ProductStockLot::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where(function ($query) use ($date, $incomingLotIds) {
                $query->whereDate('origin_production_date', $date);
                if ($incomingLotIds->isNotEmpty()) {
                    $query->orWhereIn('id', $incomingLotIds);
                }
            })
            ->orderBy('origin_production_date')
            ->get();

        return $lots->map(function (ProductStockLot $lot) use ($storeId, $productId, $date) {
            $incomingQuantity = (int) ProductStockMovement::query()
                ->where('stock_lot_id', $lot->id)
                ->whereDate('work_date', $date)
                ->where('movement_type', 'carryover_in')
                ->sum('quantity');
            $baseQuantity = $lot->origin_production_date->toDateString() === $date
                ? (int) $lot->initial_quantity
                : $incomingQuantity;

            $loss = (int) ProductionLoss::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('stock_lot_id', $lot->id)
                ->whereDate('work_date', $date)
                ->sum('quantity');
            $waste = (int) ProductionWaste::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('stock_lot_id', $lot->id)
                ->whereDate('work_date', $date)
                ->sum('quantity');
            $outflow = (int) ProductionOtherOutflow::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('stock_lot_id', $lot->id)
                ->whereDate('work_date', $date)
                ->sum('quantity');
            $carryover = (int) ProductStockMovement::query()
                ->where('stock_lot_id', $lot->id)
                ->whereDate('work_date', $date)
                ->where('movement_type', 'carryover_out')
                ->sum('quantity');

            return [
                'stock_lot_id' => $lot->id,
                'source' => $lot->origin_production_date->toDateString() === $date ? 'today' : 'carryover',
                'origin_production_date' => $lot->origin_production_date->toDateString(),
                'base_quantity' => $baseQuantity,
                'remaining_quantity' => max(0, $baseQuantity - $loss - $waste - $outflow - $carryover),
            ];
        })->values()->all();
    }

    // 폐기율은 이월 재고를 섞지 않고 원 생산일에 귀속된 폐기와 해당 생산일 생산량만 사용합니다.
    private function wasteRate(int $waste, int $production): ?float
    {
        return $production > 0 ? round($waste / $production * 100, 1) : null;
    }

    // 전달된 기록 묶음의 quantity 합계를 안전하게 계산합니다.
    private function sum(?Collection $records): int
    {
        return (int) ($records?->sum('quantity') ?? 0);
    }
}
