<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Commerce\CommerceMetrics;
use App\Queries\Commerce\InventoryStock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * COMERCIO workspace (spec §10-§11): one screen that answers "¿vendí hoy?
 * ¿este mes? ¿qué se vende más? ¿qué tallas se acaban? ¿qué falta cobrar?
 * ¿qué tengo que entregar? ¿qué está agotado? ¿qué repongo?" — plus the
 * Ventas tab (sales by day and by product). Read-only.
 */
class CommerceController extends Controller
{
    private const array PERIODS = [7, 30, 90];

    public function index(Request $request, CommerceMetrics $metrics, InventoryStock $stock): Response
    {
        $user = $request->user();
        abort_unless(
            $user->canAny(['orders.view', 'products.manage', 'inventory.manage', 'coupons.manage', 'payments.view']),
            403,
        );

        $now = CarbonImmutable::now();
        $canOrders = $user->can('orders.view');
        $canInventory = $user->can('inventory.manage');
        $monthStart = $now->startOfMonth();

        return Inertia::render('admin/commerce/Index', [
            'currency' => $metrics->currency(),
            'can' => [
                'orders' => $canOrders,
                'inventory' => $canInventory,
                'payments' => $user->can('payments.view'),
            ],
            'kpis' => $canOrders ? [
                'today' => $metrics->totals($now->startOfDay()),
                'yesterday' => $metrics->totals($now->startOfDay()->subDay(), $now->startOfDay()),
                'month' => $metrics->totals($monthStart),
                'previous_month' => $metrics->totals($monthStart->subMonth(), $monthStart),
                'orders' => $metrics->orderCounts($monthStart),
            ] : null,
            'series' => $canOrders ? $metrics->series($now->startOfDay()->subDays(13), 14) : [],
            'topProducts' => $canOrders ? $metrics->topProducts($monthStart) : [],
            'toDeliver' => $canOrders ? $metrics->toDeliver() : [],
            'recentPayments' => $user->can('payments.view') ? $metrics->recentPayments() : [],
            'stock' => $canInventory ? [
                'counts' => $stock->counts(),
                'restock' => $stock->needingRestock(8)->values(),
            ] : null,
        ]);
    }

    public function sales(Request $request, CommerceMetrics $metrics): Response
    {
        $days = in_array((int) $request->query('periodo'), self::PERIODS, true) ? (int) $request->query('periodo') : 30;
        $from = CarbonImmutable::now()->startOfDay()->subDays($days - 1);

        return Inertia::render('admin/commerce/Sales', [
            'period' => $days,
            'periods' => self::PERIODS,
            'currency' => $metrics->currency(),
            'totals' => $metrics->totals($from),
            'previous' => $metrics->totals($from->subDays($days), $from),
            'series' => $metrics->series($from, $days),
            'products' => $metrics->byProduct($from),
        ]);
    }
}
