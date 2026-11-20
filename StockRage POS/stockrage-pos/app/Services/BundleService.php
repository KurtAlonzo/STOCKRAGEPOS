<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sizes and mixing.
 *
 * The POS sells ONLY by size: Solo, Barkada, Family Tray, Party Tray. A size is every product named
 * "<flavor> - <size> (<n> pcs)". Tapping a size lists the flavors from Products. Solo takes one flavor; the
 * trays take one flavor or a mix: the cashier checks two or more flavors and the tray's pieces are split
 * as the cashier decides (the POS starts with an even split: 25 pcs / 2 = 13 + 12).
 *
 * - Price of a flavor in a size: the product's own price when the product is for that size ("Bacon - Family Tray");
 *   for a single-size flavor (Classic...) its price per order spread over the size's pieces, in whole pesos.
 *   Every sale from the POS goes through here, so a tray is always charged the tray price.
 * - "Mix" is already a mix, so it is a ready-made choice: checking it clears the other flavors (and vice versa).
 * - Single-size flavors with "Pieces per order" set (Classic Takoyaki...) can join a mix too.
 * - Price: each flavor's price for that size divided by the pieces in it, times the pieces it gets, minus the size's
 *   discount (config/pos.php, whole pesos). Each flavor needs at least config('pos.min_pieces') pieces in a mix.
 * - SaleService deducts the allocated pieces from each flavor's shared stock.
 */
class BundleService
{
    /** The ready-made flavor (cannot be mixed with others). */
    public const MIX = 'Mix';

    /** Sizes that cannot be mixed: one flavor only. */
    public const NO_MIX = ['solo'];

    /** Most trays of one mix in one go. */
    public const MAX_QTY = 100;

    public const NAME = '/^(.+?) - (.+?) \((\d+) ?pcs\)$/u';

    /** Fewest pieces one flavor can have in a mix of this size (config/pos.php). */
    public function minPieces(string $label): int
    {
        $map = config('pos.min_pieces', []);

        return max(1, (int) ($map[Str::slug($label)] ?? config('pos.default_min_pieces', 5)));
    }

    /** Discount in percent for this size (config/pos.php), 0 for none. */
    public function discount(string $label): int
    {
        $map = config('pos.discounts', []);

        return max(0, min(90, (int) ($map[Str::slug($label)] ?? 0)));
    }

    /**
     * Every size on the menu, smallest first, for the POS size cards.
     *
     * Each flavor's `price` is what ONE tray (or one Solo) of that flavor costs after the size's discount;
     * `list_price` is the same before the discount.
     *
     * @return Collection<int, array{key:string, label:string, pcs:int, mixable:bool, discount:int, min_pieces:int, max_flavors:int, flavors:array<int, array{product_id:int, flavor:string, unit_pcs:int, exclusive:bool, sized:bool, price:float, list_price:float}>}>
     */
    public function trays(): Collection
    {
        $bySize = []; // label => [flavor entries]
        $singles = [];
        $shopMenu = Product::whereNotNull('stock_flavor')->exists();

        foreach (Product::query()->active()->when($shopMenu, fn ($q) => $q->whereNotNull('recipe'))->orderBy('name')->get() as $product) {
            if (preg_match(self::NAME, $product->name, $m)) {
                [, $flavor, $label, $pcs] = $m;
                $bySize[$label][] = [
                    'product_id' => $product->id,
                    'flavor' => $flavor,
                    'unit_pcs' => (int) $pcs,
                    'exclusive' => Str::lower(trim($flavor)) === Str::lower(self::MIX),
                    'sized' => true,
                    'base' => (float) $product->price,
                ];
            } elseif ($product->pieces && Str::lower(trim($product->name)) !== Str::lower(self::MIX)) {
                $singles[] = [
                    'product_id' => $product->id,
                    'flavor' => $product->name,
                    'unit_pcs' => (int) $product->pieces,
                    'exclusive' => false,
                    'sized' => false,
                    'base' => (float) $product->price, // per order; spread over the size's pieces below
                ];
            }
        }

        $trays = collect();
        foreach ($bySize as $label => $flavors) {
            $noMix = in_array(Str::lower(trim($label)), self::NO_MIX, true);

            // The tray's size in pieces is the one most of its flavors agree on.
            $counts = array_count_values(array_column($flavors, 'unit_pcs'));
            arsort($counts);
            $pcs = (int) array_key_first($counts);

            // Single-size flavors (Classic...) are flavors of every size, Solo included.
            foreach ($singles as $single) {
                $flavors[] = $single;
            }

            $discount = $this->discount($label);
            $min = $this->minPieces($label);

            foreach ($flavors as &$f) {
                // price of one whole size of this flavor, before and after the discount
                $list = $f['sized'] ? round($f['base'], 2) : round($f['base'] * $pcs / $f['unit_pcs']);
                $f['list_price'] = $list;
                $f['price'] = $discount > 0 ? round($list * (100 - $discount) / 100) : $list; // whole pesos when discounted
                unset($f['base']);
            }
            unset($f);

            $mixable = ! $noMix && count(array_filter($flavors, fn ($f) => ! $f['exclusive'])) >= 2;

            $trays->push([
                'key' => Str::slug($label) ?: 'size-'.$pcs,
                'label' => $label,
                'pcs' => $pcs,
                'mixable' => $mixable,
                'discount' => $discount,
                'min_pieces' => $min,
                'max_flavors' => max(1, intdiv($pcs, $min)),
                'flavors' => array_values($flavors),
            ]);
        }

        return $trays->sortBy([['pcs', 'asc'], ['label', 'asc']])->values();
    }

    /**
     * The sizes that can be mixed, with the ready-made Mix left out.
     *
     * @return Collection<int, array{key:string, label:string, pcs:int, mixable:bool, flavors:array<int, array{product_id:int, flavor:string, unit_pcs:int, exclusive:bool}>}>
     */
    public function sizes(): Collection
    {
        return $this->trays()
            ->where('mixable', true)
            ->map(function (array $t) {
                $t['flavors'] = array_values(array_filter($t['flavors'], fn ($f) => ! $f['exclusive']));

                return $t;
            })
            ->values();
    }

    /**
     * Turn one tray order (one flavor, or a mix of two or more) into sale lines, one per flavor.
     *
     * @param  array{size:string, qty?:int, parts:array<int, array{product_id:int, pieces?:int}>}  $bundle
     * @return array<int, array{product:Product, qty:int, price:float, cost:float, note:string}>
     *
     * @throws RuntimeException when the order does not add up
     */
    public function resolve(array $bundle): array
    {
        $size = $this->trays()->firstWhere('label', $bundle['size'] ?? null);
        if (! $size) {
            throw new RuntimeException('"'.($bundle['size'] ?? '?').'" is not a size on the menu');
        }

        $qty = (int) ($bundle['qty'] ?? 1);
        if ($qty < 1 || $qty > self::MAX_QTY) {
            throw new RuntimeException('The number of trays must be between 1 and '.self::MAX_QTY);
        }

        $parts = $bundle['parts'] ?? [];
        if (count($parts) < 1) {
            throw new RuntimeException('Choose a flavor for the '.$size['label']);
        }
        if (count($parts) > 1 && ! $size['mixable']) {
            throw new RuntimeException('A '.$size['label'].' has one flavor only');
        }

        $allowed = collect($size['flavors'])->keyBy('product_id');
        $label = "{$size['label']} ({$size['pcs']} pcs)";

        // One flavor: the whole size at that flavor's price for the size.
        if (count($parts) === 1) {
            $id = (int) ($parts[0]['product_id'] ?? 0);
            if (! $allowed->has($id)) {
                throw new RuntimeException('That flavor is not available for a '.$size['label']);
            }
            $flavor = $allowed[$id];
            $product = Product::query()->findOrFail($id);
            $cost = $flavor['sized']
                ? round((float) ($product->cost_price ?? 0), 2)
                : round(round(((float) ($product->cost_price ?? 0)) * 100) * $size['pcs'] / $flavor['unit_pcs']) / 100;

            return [[
                'product' => $product,
                'qty' => $qty,
                'price' => (float) $flavor['price'],
                'cost' => $cost,
                'note' => $flavor['sized'] ? $size['label'].' ('.$flavor['unit_pcs'].' pcs)' : $label,
            ]];
        }

        // Two or more flavors: the cashier decides the pieces of each (at least min_pieces each, adding up to the size).
        $seen = [];
        $discount = $size['discount'];
        $sum = 0;
        $lines = [];

        foreach ($parts as $part) {
            $id = (int) ($part['product_id'] ?? 0);
            $pieces = (int) ($part['pieces'] ?? 0);

            if (! $allowed->has($id) || isset($seen[$id])) {
                throw new RuntimeException('A mixed tray has a flavor that is not available for a '.$size['label'].' or is listed twice');
            }
            if ($allowed[$id]['exclusive']) {
                throw new RuntimeException($allowed[$id]['flavor'].' is already a mix: it cannot be combined with other flavors');
            }
            if ($pieces < $size['min_pieces']) {
                throw new RuntimeException('Each flavor in a mixed '.$size['label'].' needs at least '.$size['min_pieces'].' pieces');
            }
            $seen[$id] = true;
            $sum += $pieces;

            $product = Product::query()->findOrFail($id);
            $unit = $allowed[$id]['unit_pcs'];

            $lines[] = [
                'product' => $product,
                'qty' => $qty,
                'allocated_pieces' => $pieces,
                // whole pesos: this flavor's share of the tray, after the size's discount (the POS screen uses the same formula)
                'price' => (float) round(round(((float) $product->price) * 100) * $pieces * (100 - $discount) / ($unit * 10000)),
                'cost' => round(round(((float) ($product->cost_price ?? 0)) * 100) * $pieces / $unit) / 100,
                'note' => "Mix bundle · {$label}: {$pieces} pcs",
            ];
        }

        if ($sum !== $size['pcs']) {
            throw new RuntimeException("A {$size['label']} tray needs exactly {$size['pcs']} pieces (you picked {$sum})");
        }

        return $lines;
    }
}
