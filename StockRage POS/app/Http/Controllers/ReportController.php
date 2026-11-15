<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\SqlDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const PERIODS = ['daily' => 'day', 'monthly' => 'month', 'yearly' => 'year'];

    public function index(Request $request): View
    {
        [$period, $from, $to, $rows] = $this->build($request);

        return view('reports.index', [
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows,
            'totalOrders' => $rows->sum('orders'),
            'totalSales' => $rows->sum('total'),
            'totalProfit' => $rows->sum('profit'),
        ]);
    }

    /** Optional CSV export (Excel-friendly). */
    public function export(Request $request): StreamedResponse
    {
        [$period, $from, $to, $rows] = $this->build($request);

        $filename = "stockrage-{$period}-report-{$from->toDateString()}-to-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($rows, $period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
            fputcsv($out, [ucfirst($period).' period', 'Orders', 'Total sales', 'Profit']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['label'], $row['orders'], number_format($row['total'], 2, '.', ''), number_format($row['profit'], 2, '.', '')]);
            }
            fputcsv($out, ['TOTAL', $rows->sum('orders'), number_format($rows->sum('total'), 2, '.', ''), number_format($rows->sum('profit'), 2, '.', '')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:string,1:Carbon,2:Carbon,3:\Illuminate\Support\Collection} */
    private function build(Request $request): array
    {
        $request->validate([
            'period' => ['nullable', 'in:daily,monthly,yearly'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $period = $request->query('period', 'daily');
        $unit = self::PERIODS[$period];
        $now = now();

        $defaultFrom = match ($period) {
            'daily' => $now->copy()->subDays(29),
            'monthly' => $now->copy()->subMonths(11)->startOfMonth(),
            'yearly' => $now->copy()->subYears(4)->startOfYear(),
        };

        $from = Carbon::parse($request->query('from', $defaultFrom->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', $now->toDateString()))->endOfDay();

        $key = SqlDate::key('created_at', $unit);

        // Profit = (price - cost at the time of sale) x qty, completed sales only.
        $profits = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::COMPLETED)
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw(SqlDate::key('sales.created_at', $unit).' as label, SUM((sale_items.price - sale_items.cost) * sale_items.qty) as profit')
            ->groupBy('label')
            ->pluck('profit', 'label');

        $rows = Sale::completed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("$key as label, COUNT(*) as orders, SUM(total) as total")
            ->groupBy('label')->orderBy('label')
            ->get()
            ->map(fn ($r) => [
                'key' => $r->label,
                'profit' => (float) ($profits[$r->label] ?? 0),
                'label' => $this->prettyLabel($r->label, $unit),
                'orders' => (int) $r->orders,
                'total' => (float) $r->total,
            ]);

        return [$period, $from, $to, $rows];
    }

    private function prettyLabel(string $key, string $unit): string
    {
        return match ($unit) {
            'day' => Carbon::createFromFormat('Y-m-d', $key)->format('M j, Y (D)'),
            'month' => Carbon::createFromFormat('Y-m-d', $key.'-01')->format('F Y'),
            default => $key,
        };
    }
}
