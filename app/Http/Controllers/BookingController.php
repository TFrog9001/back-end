<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * Lấy danh sách tất cả các booking.
     */
    public function index()
    {
        $bookings = Booking::with(['field', 'user'])->get();
        return response()->json($bookings);
    }

    /**
     * Tạo một booking mới.
     */
    public function store(Request $request)
    {
        $request->validate([
            'field_id' => 'required|exists:fields,id',
            'user_id' => 'required|exists:users,id',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'required|in:Đã đặt,Đã thanh toán,Hủy',
        ]);

        $booking = Booking::create($request->all());
        return response()->json($booking, 201);
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
    public function destroy($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->delete();
        return response()->json(['message' => 'Booking deleted successfully']);
    }
}
