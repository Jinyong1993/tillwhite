<?php

namespace App\Services\Production;

use App\Models\Product;
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
        $losses = ProductionLoss::query()->where('store_id', $storeId)->whereDate('work_date', $date)->get()->groupBy('product_id');
        $attributedWastes = ProductionWaste::query()->where('store_id', $storeId)->whereDate('attribution_date', $date)->get()->groupBy('product_id');
        $operationalWastes = ProductionWaste::query()->where('store_id', $storeId)->whereDate('work_date', $date)->get()->groupBy('product_id');
        $outflows = ProductionOtherOutflow::query()->where('store_id', $storeId)->whereDate('work_date', $date)->get()->groupBy('product_id');
        $incoming = ProductStockMovement::query()->where('store_id', $storeId)->whereDate('work_date', $date)->where('movement_type', 'carryover_in')->get()->groupBy('product_id');
        $outgoing = ProductStockMovement::query()->where('store_id', $storeId)->whereDate('work_date', $date)->where('movement_type', 'carryover_out')->get()->groupBy('product_id');
        $confirmations = ProductionConfirmation::query()->where('store_id', $storeId)->whereDate('work_date', $date)->get()->keyBy('product_id');

        $rows = $products->map(function (Product $product) use ($batches, $losses, $attributedWastes, $operationalWastes, $outflows, $incoming, $outgoing, $confirmations, $productHistories, $categoryHistories) {
            $history = $productHistories->get($product->id);
            $historicalCategoryId = $history?->product_category_id ?? $product->product_category_id;
            $categoryHistory = $categoryHistories->get($historicalCategoryId);
            $isActive = $history
                ? (bool) $history->is_active && !(bool) $history->is_deleted
                : (bool) $product->is_active && $product->deleted_at === null;

            $production = $this->sum($batches->get($product->id));
            $carryIn = $this->sum($incoming->get($product->id));
            $loss = $this->sum($losses->get($product->id));
            $waste = $this->sum($attributedWastes->get($product->id));
            $operationalWaste = $this->sum($operationalWastes->get($product->id));
            $other = $this->sum($outflows->get($product->id));
            $carryOut = $this->sum($outgoing->get($product->id));
            $confirmation = $confirmations->get($product->id);
            $wasteRate = $production > 0 ? round($waste / $production * 100, 1) : null;

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
                'carryover_in' => $carryIn,
                'loss' => $loss,
                'waste' => $waste,
                'operational_waste' => $operationalWaste,
                'other_outflow' => $other,
                'carryover_out' => $carryOut,
                'waste_rate' => $wasteRate,
                'production_confirmed' => (bool) ($confirmation?->production_confirmed ?? false),
                'loss_confirmed' => (bool) ($confirmation?->loss_confirmed ?? false),
                'waste_confirmed' => (bool) ($confirmation?->waste_confirmed ?? false),
                'disposition_confirmed' => (bool) ($confirmation?->disposition_confirmed ?? false),
                'zero_production_reason' => $confirmation?->zero_production_reason,
                'complete' => $this->isComplete($isActive, $confirmation),
            ];
        })->values();

        $activeRows = $rows->where('is_active', true);
        $totals = [
            'production' => $activeRows->sum('production'),
            'carryover' => $activeRows->sum('carryover_in'),
            'loss' => $activeRows->sum('loss'),
            'waste' => $activeRows->sum('waste'),
            'other_outflow' => $activeRows->sum('other_outflow'),
        ];
        $totals['waste_rate'] = $totals['production'] > 0
            ? round($totals['waste'] / $totals['production'] * 100, 1)
            : null;

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

    /** 전달된 기록 묶음의 quantity 합계를 안전하게 계산합니다. */
    private function sum(?Collection $records): int
    {
        return (int) ($records?->sum('quantity') ?? 0);
    }

    /** 활성 제품이 마감에 필요한 네 가지 확인을 모두 마쳤는지 판단합니다. */
    private function isComplete(bool $isActive, ?ProductionConfirmation $confirmation): bool
    {
        if (!$isActive) {
            return true;
        }

        return (bool) ($confirmation?->production_confirmed ?? false)
            && (bool) ($confirmation?->loss_confirmed ?? false)
            && (bool) ($confirmation?->waste_confirmed ?? false)
            && (bool) ($confirmation?->disposition_confirmed ?? false);
    }
}
