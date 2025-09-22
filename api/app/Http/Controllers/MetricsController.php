<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Compra;
use App\Models\Venda;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class MetricsController extends Controller
{
    public function overview(): JsonResponse
    {
        $now = Carbon::now();
        $y = $now->year;
        $m = $now->month;

        $productsCount   = Produto::count();
        $purchasesCount  = Compra::count();
        $salesCount      = Venda::where('status','completed')->count();

        $revenueMonth = Venda::whereYear('created_at', $y)
            ->whereMonth('created_at', $m)
            ->where('status','completed')
            ->sum('total');

        $profitMonth = Venda::whereYear('created_at', $y)
            ->whereMonth('created_at', $m)
            ->where('status','completed')
            ->sum('lucro');

        $revenueTotal = Venda::where('status','completed')->sum('total');
        $profitTotal  = Venda::where('status','completed')->sum('lucro');

        $lowStock = Produto::where('estoque', '<=', 5)->count();

        return response()->json([
            'products_count'  => $productsCount,
            'purchases_count' => $purchasesCount,
            'sales_count'     => $salesCount,
            'revenue_month'   => number_format((float)$revenueMonth, 2, '.', ''),
            'profit_month'    => number_format((float)$profitMonth, 2, '.', ''),
            'revenue_total'   => number_format((float)$revenueTotal, 2, '.', ''),
            'profit_total'    => number_format((float)$profitTotal, 2, '.', ''),
            'low_stock'       => $lowStock,
            'month_label'     => $now->isoFormat('MMMM/YYYY'),
        ]);
    }
}
