<?php

namespace App\Services;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VariantBackfillService
{
    public function __construct(private VariantInitializationService $initialization, private AuditTrail $audit) {}

    /** @return array{missing:int, products:int, noColors:array, mismatches:int, mismatchIds:array, zeroWithoutMovements:int} */
    public function diagnose(): array
    {
        $summary = ['missing' => 0, 'products' => 0, 'noColors' => [], 'mismatches' => 0, 'mismatchIds' => [], 'zeroWithoutMovements' => 0];
        $sizes = Size::all()->groupBy('id_size_group');
        Product::whereNotNull('id_size_group')->orderBy('id')->chunkById(100, function ($products) use ($sizes, &$summary) {
            foreach ($products as $product) {
                $variants = ProductVariant::where('id_product', $product->id)->get(['id_size', 'id_color']);
                $colors = $variants->pluck('id_color')->unique();
                if ($colors->isEmpty()) {
                    $summary['noColors'][] = $product->id;
                    continue;
                }
                $combos = $variants->mapWithKeys(fn ($variant) => [$variant->id_size.'-'.$variant->id_color => true]);
                $missing = 0;
                foreach ($sizes->get($product->id_size_group, collect()) as $size) {
                    foreach ($colors as $colorId) {
                        $missing += (int) ! $combos->has($size->id.'-'.$colorId);
                    }
                }
                if ($missing > 0) {
                    $summary['missing'] += $missing;
                    $summary['products']++;
                }
            }
        });
        $mismatches = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.id_product')
            ->join('sizes as s', 's.id', '=', 'v.id_size')
            ->where(fn ($query) => $query->whereNull('p.id_size_group')->orWhereColumn('p.id_size_group', '!=', 's.id_size_group'));
        $summary['mismatches'] = (clone $mismatches)->count();
        $summary['mismatchIds'] = $mismatches->orderBy('v.id')->limit(100)->pluck('v.id')->all();
        $summary['zeroWithoutMovements'] = ProductVariant::where('stock', 0)->whereDoesntHave('movements')->count();

        return $summary;
    }

    /** Each product and each batch of existing variants is independently atomic. */
    public function apply(User $user): array
    {
        $this->audit->identify($user);
        $created = 0;
        $regularized = 0;
        Product::whereNotNull('id_size_group')->orderBy('id')->chunkById(100, function ($products) use ($user, &$created) {
            foreach ($products as $candidate) {
                // Avoid audit events for products already complete.
                if (! $this->hasMissing($candidate)) {
                    continue;
                }
                $created += $this->audit->run('products', 'variants.backfilled', function () use ($candidate, $user) {
                    $product = Product::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    $colorIds = ProductVariant::where('id_product', $product->id)->distinct()->pluck('id_color');
                    $colors = Color::whereIn('id', $colorIds)->orderBy('id')->get();
                    $count = 0;
                    foreach (Size::where('id_size_group', $product->id_size_group)->orderBy('id')->lockForUpdate()->get() as $size) {
                        foreach ($colors as $color) {
                            $count += (int) ($this->initialization->createMissing($product, $size, $color, $user->id, VariantInitializationService::BACKFILL_REFERENCE) !== null);
                        }
                    }

                    return $count;
                }, 3);
            }
        });

        ProductVariant::where('stock', 0)->whereDoesntHave('movements')->orderBy('id')->chunkById(100, function ($candidates) use ($user, &$regularized) {
            $regularized += $this->audit->run('inventory', 'variants.zero_stock_backfilled', function () use ($candidates, $user) {
                $variants = ProductVariant::whereIn('id', $candidates->modelKeys())->where('stock', 0)
                    ->whereDoesntHave('movements')->orderBy('id')->lockForUpdate()->get();
                foreach ($variants as $variant) {
                    $this->initialization->recordZero($variant, $user->id, VariantInitializationService::BACKFILL_REFERENCE);
                }

                return $variants->count();
            }, 3);
        });

        return ['created' => $created, 'regularized' => $regularized];
    }

    private function hasMissing(Product $product): bool
    {
        $variants = ProductVariant::where('id_product', $product->id)->get(['id_size', 'id_color']);
        $colors = $variants->pluck('id_color')->unique();
        $combos = $variants->mapWithKeys(fn ($variant) => [$variant->id_size.'-'.$variant->id_color => true]);
        foreach (Size::where('id_size_group', $product->id_size_group)->pluck('id') as $sizeId) {
            foreach ($colors as $colorId) {
                if (! $combos->has($sizeId.'-'.$colorId)) {
                    return true;
                }
            }
        }

        return false;
    }
}
