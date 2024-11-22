<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Field;
use App\Models\Supply;
use App\Models\User;
use App\Models\Service;

use App\Models\BillService;
use App\Models\BillSupply;
use App\Models\ImportReceiptDetail;

class RevenueController extends Controller
{
    public function getRevenueStatistics(Request $request)
    {
        // Lấy tham số time từ request
        $time = $request->query('time', now()->year);

        // Kiểm tra xem time là năm hay năm kèm tháng
        if (preg_match('/^\d{4}$/', $time)) {
            // Nếu chỉ có năm, gọi hàm getYearlyRevenue
            return $this->getYearlyRevenue((int) $time);
        } elseif (preg_match('/^\d{4}-\d{2}$/', $time)) {
            // Nếu có cả năm và tháng, gọi hàm getMonthlySupplyStatistics
            [$year, $month] = explode('-', $time);
            return response()->json($this->getMonthlySupplyStatistics((int) $year, (int) $month));
        } else {
            // Nếu không hợp lệ, trả về lỗi
            return response()->json(['error' => 'Invalid time format. Use YYYY or YYYY-MM.'], 400);
        }
    }

    private function getYearlyRevenue($year)
    {
        // Lấy doanh thu hoàn thành
        $completedRevenue = Bill::query()
            ->selectRaw('MONTH(created_at) as month, SUM(total_amount) as total_revenue')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('total_revenue', 'month');

        // Doanh thu từ phí sân
        $revenueFromFields = Bill::query()
            ->join('bookings', 'bills.booking_id', '=', 'bookings.id')
            ->selectRaw('MONTH(bookings.created_at) as month, SUM(bookings.field_price) as field_revenue')
            ->whereYear('bills.created_at', $year)
            ->groupByRaw('MONTH(bookings.created_at)')
            ->pluck('field_revenue', 'month');

        // Doanh thu từ dịch vụ
        $revenueFromServices = BillService::query()
            ->join('bills', 'bill_services.bill_id', '=', 'bills.id')
            ->selectRaw('MONTH(bills.created_at) as month, SUM(bill_services.fee) as service_revenue')
            ->whereYear('bills.created_at', $year)
            ->groupByRaw('MONTH(bills.created_at)')
            ->pluck('service_revenue', 'month');

        // Doanh thu từ sản phẩm
        $revenueFromSupplies = BillSupply::query()
            ->join('supplies', 'bill_supplies.supply_id', '=', 'supplies.id')
            ->join('import_receipt_details', function ($join) {
                $join->on('supplies.id', '=', 'import_receipt_details.item_id')
                    ->where('import_receipt_details.item_type', '=', 'supply');
            })
            ->join('bills', 'bill_supplies.bill_id', '=', 'bills.id')
            ->selectRaw('
                MONTH(bills.created_at) as month,
                SUM(bill_supplies.quantity * (supplies.price - COALESCE(import_receipt_details.price, 0))) as supply_revenue
            ')
            ->whereYear('bills.created_at', $year)
            ->groupByRaw('MONTH(bills.created_at)')
            ->pluck('supply_revenue', 'month');

        // Doanh thu từ các booking bị hủy
        $canceledBookingRevenue = Booking::query()
            ->selectRaw('MONTH(created_at) as month, SUM(deposit) as canceled_revenue')
            ->whereYear('created_at', $year)
            ->where('status', 'Hủy')
            ->groupByRaw('MONTH(created_at)')
            ->pluck('canceled_revenue', 'month');

        // Tổng hợp dữ liệu theo tháng
        $monthlyRevenue = [];
        for ($month = 1; $month <= 12; $month++) {
            $completed = $completedRevenue[$month] ?? 0;
            $fields = $revenueFromFields[$month] ?? 0;
            $services = $revenueFromServices[$month] ?? 0;
            $supplies = $revenueFromSupplies[$month] ?? 0;
            $canceled = $canceledBookingRevenue[$month] ?? 0;

            $monthlyRevenue[] = [
                'month' => $month,
                'completed_revenue' => number_format($completed, 2, '.', ''),
                'revenue_from_fields' => number_format($fields, 2, '.', ''),
                'revenue_from_services' => number_format($services, 2, '.', ''),
                'revenue_from_supplies' => number_format($supplies, 2, '.', ''),
                'canceled_revenue' => number_format($canceled, 2, '.', ''),
                'total_revenue' => number_format($completed + $canceled, 2, '.', ''),
            ];
        }

        // Trả về JSON
        return response()->json([
            'year' => $year,
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }

    private function getMonthlySupplyStatistics($year, $month)
    {
        $formattedMonth = str_pad($month, 2, '0', STR_PAD_LEFT);
        $formattedDate = "$year-$formattedMonth";

        // Lấy danh sách các sản phẩm với dữ liệu cần thiết
        $supplies = Supply::query()
            ->with([
                'billSupplies' => function ($query) use ($formattedDate) {
                    $query->whereHas('bill', function ($billQuery) use ($formattedDate) {
                        $billQuery->where('status', 'Đã thanh toán')
                            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$formattedDate]);
                    });
                },
                'importDetails.receipt' => function ($query) {
                    $query->orderBy('created_at', 'asc'); // Sắp xếp theo thời gian nhập (FIFO)
                }
            ])
            ->get()
            ->map(function ($supply) use ($formattedDate) {
                $soldQuantity = $supply->billSupplies->sum('quantity');


                $soldAmount = $supply->billSupplies->sum(function ($billSupply) {
                    return $billSupply->quantity * $billSupply->price;
                });

                // Áp dụng FIFO để tính chi phí hàng bán (COGS)
                $fifoCost = 0;
                $remainingToSell = $soldQuantity;

                $importedBatches = $supply->importDetails
                    ->filter(function ($importDetail) use ($formattedDate) {
                        // Lấy tất cả lô nhập (cả trước và trong tháng)
                        return strtotime($importDetail->receipt->created_at) <= strtotime("$formattedDate-31");
                    });

                foreach ($importedBatches as $batch) {
                    if ($remainingToSell <= 0)
                        break;

                    $batchQuantity = $batch->quantity;
                    $batchCost = $batch->price;

                    if ($batchQuantity >= $remainingToSell) {
                        $fifoCost += $remainingToSell * $batchCost;
                        $batch->quantity -= $remainingToSell; // Trừ số lượng trong lô
                        $remainingToSell = 0;
                    } else {
                        $fifoCost += $batchQuantity * $batchCost;
                        $remainingToSell -= $batchQuantity;
                        $batch->quantity = 0; // Đánh dấu lô đã hết
                    }
                }

                // Lợi nhuận
                $profit = $soldAmount - $fifoCost;

                // Dữ liệu nhập trong tháng
                $importedQuantity = $supply->importDetails
                    ->where('receipt.created_at', 'like', $formattedDate . '%')
                    ->sum('quantity');
                $importedAmount = $supply->importDetails
                    ->where('receipt.created_at', 'like', $formattedDate . '%')
                    ->sum(function ($importDetail) {
                        return $importDetail->quantity * $importDetail->price;
                    });

                return [
                    'supply_id' => $supply->id,
                    'supply_name' => $supply->name,
                    'total_sold_quantity' => $soldQuantity,
                    'total_sold_amount' => $soldAmount,
                    'fifo_cost' => $fifoCost,
                    'profit' => $profit,
                    'current_stock' => $supply->quantity,
                    'total_imported_quantity' => $importedQuantity,
                    'total_imported_amount' => $importedAmount,
                ];
            });

        $services = Service::all();

        // Lấy tất cả các `bill_service` trong tháng
        $billServices = BillService::query()
            ->whereHas('bill.booking', function ($query) use ($formattedDate) {
                $query->whereRaw("DATE_FORMAT(start_time, '%Y-%m') = ?", [$formattedDate]);
            })
            ->with(['service', 'bill.booking'])
            ->get();

        // Tính doanh thu cho từng dịch vụ
        $servicesRevenue = $services->map(function ($service) use ($billServices, $formattedDate) {
            $billServiceGroup = $billServices->filter(function ($billService) use ($service) {
                return $billService->service_id == $service->id;
            });

            $totalRevenue = $billServiceGroup->sum(function ($billService) {
                $service = $billService->service;
                $booking = $billService->bill->booking;

                // Tính giờ từ thời gian booking
                $hours = max(ceil((strtotime($booking->end_time) - strtotime($booking->start_time)) / 3600), 1);

                return $hours * $service->fee; // Tính doanh thu: số giờ * phí theo giờ
            });

            return [
                'service_name' => $service->service,
                'total_revenue' => $totalRevenue,
            ];
        });

        // Tính lợi nhuận từ phí sân
        $totalRevenueFromBookings = Booking::query()
            ->whereRaw("DATE_FORMAT(booking_date, '%Y-%m') = ?", [$formattedDate])
            ->where('status', 'Đã thanh toán')
            ->sum('field_price'); 

        // Tính lợi nhuận từ tiền cọc của các đơn bị hủy
        $totalDepositFromCanceledBookings = Booking::query()
            ->whereRaw("DATE_FORMAT(start_time, '%Y-%m') = ?", [$formattedDate])
            ->where('status', 'Hủy')
            ->sum('deposit'); // Giả sử tiền cọc được lưu trong trường `deposit`

        // Tổng doanh thu
        // $totalRevenue = $servicesRevenue->sum('total_revenue');

        // Tổng quan thống kê phụ
        $totalRevenue = $supplies->sum('total_sold_amount') + $servicesRevenue->sum('total_revenue') + $totalRevenueFromBookings + $totalDepositFromCanceledBookings;
        $totalCOGS = $supplies->sum('fifo_cost');
        $profit = $totalRevenue - $totalCOGS;

        return [
            'year' => explode('-', $formattedDate)[0],
            'month' => explode('-', $formattedDate)[1],
            'total_bookings' => Booking::query()
            ->whereRaw("DATE_FORMAT(booking_date, '%Y-%m') = ?", [$formattedDate])->count(),
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCOGS,
            'profit_supply' => $supplies->sum('total_sold_amount')- $supplies->sum('fifo_cost'),
            'profit_service' => $servicesRevenue->sum('total_revenue'),
            'profit_court_fee' => $totalRevenueFromBookings, // Lợi nhuận từ phí sân
            'profit_deposit_canceled' => $totalDepositFromCanceledBookings, // Lợi nhuận từ tiền cọc của các đơn hủy
            'supplies' => $supplies,
            'services' => $servicesRevenue,
        ];
    }


    public function getStatistics(Request $request)
    {
        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        // Tổng số lần đặt sân
        $totalBookings = Booking::whereYear('created_at', $year)
            ->count();

        // Tổng doanh thu
        $totalRevenue = Bill::whereYear('created_at', $year)
            ->sum('total_amount');

        // Số khách hàng mới
        $newCustomers = User::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        // Sân phổ biến nhất
        $mostPopularField = Booking::selectRaw('field_id, COUNT(*) as count')
            ->whereYear('created_at', $year)
            ->groupBy('field_id')
            ->orderBy('count', 'desc')
            ->first();

        $fieldName = $mostPopularField
            ? Field::find($mostPopularField->field_id)->name
            : 'N/A';

        // Thống kê số lượng đặt sân theo ngày
        $dailyBookings = Booking::selectRaw('DAY(created_at) as day, COUNT(*) as count')
            ->whereYear('created_at', $year)
            ->groupByRaw('DAY(created_at)')
            ->get();

        $dailyData = [];
        foreach (range(1, 31) as $day) {
            $dailyData[] = [
                'day' => $day,
                'count' => $dailyBookings->firstWhere('day', $day)->count ?? 0,
            ];
        }

        // Danh sách đặt sân gần đây
        $recentBookings = Booking::with(['field', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['field_id', 'user_id', 'start_time', 'end_time', 'created_at', 'booking_date']);

        // Trả về dữ liệu
        return response()->json([
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalRevenue,
            'new_customers' => $newCustomers,
            'most_popular_field' => $fieldName,
            'daily_bookings' => $dailyData,
            'recent_bookings' => $recentBookings->map(function ($booking) {
                return [
                    'field_name' => $booking->field->name,
                    'customer_name' => $booking->user->name,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'date' => $booking->booking_date,
                ];
            }),
        ]);
    }

}
