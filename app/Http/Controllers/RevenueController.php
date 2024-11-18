<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Field;

class RevenueController extends Controller
{
    public function revenueByYear(Request $request)
    {
        // Nhận tham số năm từ request (mặc định là năm hiện tại)
        $year = $request->query('year', now()->year);

        $completedRevenue = DB::table('bookings')
            ->selectRaw('MONTH(created_at) as month, SUM(field_price) as total_completed_revenue')
            ->where('status', 'Đã thanh toán')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('total_completed_revenue', 'month');

        // Doanh thu từ đơn bị hủy (status: 'Hủy', chỉ tính deposit)
        $cancelledRevenue = DB::table('bookings')
            ->selectRaw('MONTH(created_at) as month, SUM(deposit) as total_cancelled_revenue')
            ->where('status', 'Hủy')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('total_cancelled_revenue', 'month');

        // Kết hợp dữ liệu theo từng tháng
        $monthlyRevenue = [];
        for ($month = 1; $month <= 12; $month++) {
            $completed = $completedRevenue[$month] ?? 0;
            $cancelled = $cancelledRevenue[$month] ?? 0;

            $monthlyRevenue[] = [
                'month' => $month,
                'completed_revenue' => number_format($completed, 2, '.', ''),
                'cancelled_revenue' => number_format($cancelled, 2, '.', ''),
                'total_revenue' => number_format($completed + $cancelled, 2, '.', ''),
            ];
        }

        return response()->json([
            'year' => $year,
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }
}
