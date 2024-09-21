<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\FieldPrice;
use Illuminate\Http\Request;

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
    public function store(Request $request)
    {
        try {
            // Validate các trường
            $request->validate([
                'field_id' => 'required|exists:fields,id',
                'user_id' => 'required|exists:users,id',
                'booking_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
            ]);

            // Kiểm tra trùng lặp khung giờ
            $existingBooking = Booking::where('field_id', $request->field_id)
                ->where('booking_date', $request->booking_date)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_time', '<=', $request->start_time)
                            ->where('end_time', '>', $request->start_time);
                    })
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_time', '<', $request->end_time)
                                ->where('end_time', '>=', $request->end_time);
                        })
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_time', '>=', $request->start_time)
                                ->where('end_time', '<=', $request->end_time);
                        });
                })
                ->exists();

            if ($existingBooking) {
                return response()->json([
                    'message' => 'Khung giờ này đã được đặt. Vui lòng chọn khung giờ khác.',
                ], 422);
            }

            // Lấy các giá tiền cho khung giờ đó từ bảng field_prices
            $fieldPrices = FieldPrice::where('field_id', $request->field_id)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        // Khoảng giá bắt đầu trước hoặc bằng thời gian bắt đầu và kết thúc sau thời gian bắt đầu
                        $q->where('start_time', '<=', $request->start_time)
                            ->where('end_time', '>', $request->start_time);
                    })
                        ->orWhere(function ($q) use ($request) {
                            // Khoảng giá bắt đầu trước thời gian kết thúc và kết thúc sau hoặc bằng thời gian kết thúc
                            $q->where('start_time', '<', $request->end_time)
                                ->where('end_time', '>=', $request->end_time);
                        })
                        ->orWhere(function ($q) use ($request) {
                            // Khoảng giá bao gồm hoàn toàn thời gian booking
                            $q->where('start_time', '>=', $request->start_time)
                                ->where('end_time', '<=', $request->end_time);
                        });
                })
                ->get();

            $totalPrice = 0;

            // Tính giá cho từng khoảng thời gian
            foreach ($fieldPrices as $price) {
                // Tính thời gian bắt đầu và kết thúc thực tế cho khoảng giá hiện tại
                $actualStart = max($price->start_time, $request->start_time);
                $actualEnd = min($price->end_time, $request->end_time);

                // Chuyển đổi thời gian sang phút để tính toán dễ hơn
                $startMinutes = strtotime($actualStart);
                $endMinutes = strtotime($actualEnd);

                // Tính số phút trong khoảng thời gian này
                $minutesBooked = ($endMinutes - $startMinutes) / 60;

                // Tính giá cho khoảng thời gian này và cộng vào tổng giá
                $priceForThisSlot = ($minutesBooked / 60) * $price->price;
                $totalPrice += $priceForThisSlot;
            }

            // Tạo booking mới với giá đã tính
            $booking = Booking::create([
                'field_id' => $request->field_id,
                'user_id' => $request->user_id,
                'booking_date' => $request->booking_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'field_price' => $totalPrice,
                'deposit' => $request->deposit ?? 0, // Deposit có thể là optional
                'status' => 'Đã đặt', // Mặc định là Đã đặt
            ]);

            return response()->json($booking, 201);

        } catch (ValidationException $e) {
            // Bắt lỗi validate và trả về thông báo lỗi chi tiết
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Dữ liệu không hợp lệ, vui lòng kiểm tra lại thông tin đã nhập.',
            ], 422);
        } catch (Exception $e) {
            // Bắt các lỗi khác (nếu có)
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
