<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\FieldPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


use Illuminate\Validation\ValidationException;
use Exception;

class BookingController extends Controller
{
    /**
     * Lấy danh sách tất cả các booking.
     */
    public function index(Request $request)
    {
        $bookings = Booking::with(['field', 'user'])->where('booking_date', $request->booking_date)->get();
        return response()->json($bookings);
    }

    /**
     * Tạo một booking mới.
     */
    public function isTimeSlotAvailable($field_id, $booking_date, $start_time, $end_time)
    {
        return !Booking::where('field_id', $field_id)
            ->where('booking_date', $booking_date)
            ->where(function ($query) use ($start_time, $end_time) {
                $query->where(function ($q) use ($start_time) {
                    $q->where('start_time', '<=', $start_time)
                        ->where('end_time', '>', $start_time);
                })
                    ->orWhere(function ($q) use ($end_time) {
                        $q->where('start_time', '<', $end_time)
                            ->where('end_time', '>=', $end_time);
                    })
                    ->orWhere(function ($q) use ($start_time, $end_time) {
                        $q->where('start_time', '>=', $start_time)
                            ->where('end_time', '<=', $end_time);
                    });
            })
            ->exists();
    }

    /**
     * Tính tổng giá tiền sân cho khoảng thời gian đã đặt.
     */
    public function calculateFieldPrice($field_id, $start_time, $end_time)
    {
        $fieldPrices = FieldPrice::where('field_id', $field_id)
            ->where(function ($query) use ($start_time, $end_time) {
                $query->where(function ($q) use ($start_time) {
                    $q->where('start_time', '<=', $start_time)
                        ->where('end_time', '>', $start_time);
                })
                    ->orWhere(function ($q) use ($end_time) {
                        $q->where('start_time', '<', $end_time)
                            ->where('end_time', '>=', $end_time);
                    })
                    ->orWhere(function ($q) use ($start_time, $end_time) {
                        $q->where('start_time', '>=', $start_time)
                            ->where('end_time', '<=', $end_time);
                    });
            })
            ->get();

        $totalPrice = 0;

        foreach ($fieldPrices as $price) {
            $actualStart = max($price->start_time, $start_time);
            $actualEnd = min($price->end_time, $end_time);

            $startMinutes = strtotime($actualStart);
            $endMinutes = strtotime($actualEnd);

            $minutesBooked = ($endMinutes - $startMinutes) / 60;
            $priceForThisSlot = ($minutesBooked / 60) * $price->price;

            $totalPrice += $priceForThisSlot;
        }

        return $totalPrice;
    }

    /**
     * Hàm tạo booking.
     */
    public function store(Request $request)
    {   
        Log::info('booking '. $request);
        try {
            $request->validate([
                'field_id' => 'required|exists:fields,id',
                'user_id' => 'required|exists:users,id',
                'booking_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
            ]);

            if (!$this->isTimeSlotAvailable($request->field_id, $request->booking_date, $request->start_time, $request->end_time)) {
                return response()->json([
                    'message' => 'Khung giờ này đã được đặt. Vui lòng chọn khung giờ khác.',
                ], 422);
            }

            $totalPrice = $this->calculateFieldPrice($request->field_id, $request->start_time, $request->end_time);

            $booking = Booking::create([
                'field_id' => $request->field_id,
                'user_id' => $request->user_id,
                'booking_date' => $request->booking_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'field_price' => $totalPrice,
                'deposit' => $request->deposit ?? 0,
                'status' => 'Đã đặt',
            ]);

            return response()->json($booking, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Dữ liệu không hợp lệ, vui lòng kiểm tra lại thông tin đã nhập.',
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Có lỗi xảy ra, vui lòng thử lại.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hiển thị thông tin một booking cụ thể.
     */
    public function show($id)
    {
        $booking = Booking::with(['field', 'user'])->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json($booking);
    }

    /**
     * Cập nhật thông tin một booking cụ thể.
     */
    public function update(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $request->validate([
            'field_id' => 'exists:fields,id',
            'user_id' => 'exists:users,id',
            'booking_date' => 'date',
            'start_time' => 'date_format:H:i',
            'end_time' => 'date_format:H:i|after:start_time',
            'status' => 'in:Đã đặt,Đã thanh toán,Hủy',
        ]);

        $booking->update($request->all());
        return response()->json($booking);
    }

    /**
     * Xóa một booking cụ thể.
     */
    public function delete($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->delete();
        return response()->json(['message' => 'Booking deleted successfully']);
    }
}
