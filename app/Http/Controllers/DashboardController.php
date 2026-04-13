<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', 'week');
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        if ($period === 'month') {
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
        } elseif ($period === 'year') {
            $startDate = now()->startOfYear();
            $endDate = now()->endOfYear();
        }

        $revenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        $totalRevenueAllTime = Order::where('payment_status', 'paid')->sum('total_amount');

        $newOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();

        $newUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();

        $lowStock = Variant::where('stock', '<', 10)->count();

        $chartLabels = [];
        $chartValues = [];

        if ($period === 'year') {
            for ($i = 1; $i <= 12; $i++) {
                $monthStr = now()->month($i)->format('Y-m');
                $chartLabels[] = "T" . $i;
                $chartValues[] = (float) Order::where('payment_status', 'paid')
                    ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$monthStr])
                    ->sum('total_amount');
            }
        } else {
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $dateStr = $current->format('Y-m-d');
                $chartLabels[] = $period === 'week' ? $this->getVnDay($current) : $current->format('d/m');
                $chartValues[] = (float) Order::where('payment_status', 'paid')
                    ->whereDate('created_at', $dateStr)
                    ->sum('total_amount');
                $current->addDay();
            }
        }

        $recentOrders = Order::with('user')->latest()->take(5)->get()->map(function($order) {
            return [
                'id' => $order->id,
                'name' => $order->user->name ?? 'Khách lẻ',
                'initials' => $this->getInitials($order->user->name ?? 'KL'),
                'date' => $order->created_at->format('d/m/Y'),
                'amount' => number_format($order->total_amount, 0, ',', '.') . '₫',
                'status' => $this->mapStatusText($order->order_status),
                'statusClass' => $this->mapStatusClass($order->order_status),
            ];
        });

        return response()->json([
            'stats' => [
                ['label' => 'Doanh thu kỳ này', 'value' => number_format($revenue, 0, ',', '.') . '₫', 'change' => '+12%', 'positive' => true, 'icon' => 'revenue', 'color' => '#2563eb', 'bg' => '#eff6ff'],
                ['label' => 'Đơn hàng mới', 'value' => (string)$newOrders, 'change' => '+5%', 'positive' => true, 'icon' => 'order', 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
                ['label' => 'Thành viên mới', 'value' => (string)$newUsers, 'change' => '+3%', 'positive' => true, 'icon' => 'user', 'color' => '#059669', 'bg' => '#ecfdf5'],
                ['label' => 'Sản phẩm tồn kho', 'value' => (string)$lowStock, 'change' => 'Sắp hết', 'positive' => false, 'icon' => 'product', 'color' => '#dc2626', 'bg' => '#fef2f2'],
            ],
            'chart' => [
                'labels' => $chartLabels,
                'values' => $chartValues,
            ],
            'orders' => $recentOrders
        ]);
    }

    private function getInitials($name) {
        $words = explode(' ', $name);
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
    }

    private function getVnDay($date) {
        $days = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        return $days[$date->dayOfWeek];
    }

    private function mapStatusText($status) {
        $map = ['pending'=>'Chờ xử lý', 'completed'=>'Đã giao', 'shipping'=>'Đang giao', 'cancelled'=>'Đã hủy'];
        return $map[$status] ?? $status;
    }

    private function mapStatusClass($status) {
        $map = ['pending'=>'pending', 'completed'=>'delivered', 'shipping'=>'shipping', 'cancelled'=>'cancelled'];
        return $map[$status] ?? 'pending';
    }
}