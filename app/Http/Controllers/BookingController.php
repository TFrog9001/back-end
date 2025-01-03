<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\FieldPrice;
use App\Models\User;
use App\Models\Bill;
use App\Models\Service;
use App\Models\BillService;

use App\Services\SmsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;


class BookingController extends Controller
{
    /**
     * Lấy danh sách tất cả các booking.
     */
    public function index(Request $request)
    {
        $bookings = Booking::with(['field', 'user', 'bill'])
            ->when($request->booking_date, function ($query, $booking_date) {
                return $query->where('booking_date', $booking_date);
            })
            ->when($request->status, function ($query, $status) {
                if ($status === 'nofail') {
                    return $query->where('status', '!=', 'Hủy')
                        ->whereNotIn('status', ['Đã hoàn tiền', 'Hoàn tiền']);
                }
                return $query->where('status', $status)
                    ->whereNotIn('status', ['Đã hoàn tiền', 'Hoàn tiền']);
            })
            ->get();

        return response()->json($bookings);
    }

    public function getFailBooking(Request $request)
    {
        $bookings = Booking::with(['field', 'user'])->where('booking_date', $request->booking_date)->where('status', '=', 'Hủy')->get();
        return response()->json($bookings);
    }


    /**
     * Hiển thị thông tin một booking cụ thể.
     */
    public function show($id)
    {
        $booking = Booking::with(['field', 'user', 'bill.supplies.supply', 'bill.services.staff', 'bill.services.service', 'comment'])->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ($booking->comment && $booking->comment->hidden) {
            $booking->comment = null; // Nếu comment bị ẩn, không trả về comment
        }

        return response()->json($booking);
    }

    public function getUserBooking($id)
    {
        // Tìm tất cả các booking có user_id = $id
        $bookings = Booking::with(['field', 'user'])
            ->where('user_id', $id)
            ->orderBy('booking_date', 'desc')
            ->get();


        // Kiểm tra nếu không có booking nào
        if ($bookings->isEmpty()) {
            return response()->json(['message' => 'No bookings found for this user'], 404);
        }

        // Trả về danh sách các booking của user
        return response()->json($bookings);
    }


    /**
     * Tạo một booking mới.
     */
    public function isTimeSlotAvailable($field_id, $booking_date, $start_time, $end_time)
    {
        return !Booking::where('field_id', $field_id)
            ->where('booking_date', $booking_date)
            ->where('status', '!=', 'Hủy')
            ->whereNotIn('status', ['Đã hoàn tiền', 'Hoàn tiền'])
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
        Log::error('Hello');
        Log::info($request->all());
        DB::beginTransaction();
        try {
            // Validate các dữ liệu đầu vào
            $request->validate([
                'field_id' => 'required|exists:fields,id',
                'booking_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'user_name' => 'required_if:user_id,null',
                'user_phone' => 'required_if:user_id,null',
                'services' => 'array',
                'services.*.service_id' => 'required|exists:services,id',
                'services.*.staff_id' => 'required|exists:users,id',
            ]);
            // Nếu user_id rỗng, tạo user mới
            if (empty($request->user_id)) {
                $user = User::create([
                    'name' => $request->user_name,
                    'phone' => $request->user_phone,
                    'role_id' => 3,
                ]);
                $request->merge(['user_id' => $user->id]); // Cập nhật user_id vào request
            }

            // Kiểm tra khung giờ có khả dụng không
            if (!$this->isTimeSlotAvailable($request->field_id, $request->booking_date, $request->start_time, $request->end_time)) {
                return response()->json([
                    'message' => 'Khung giờ này đã được đặt. Vui lòng chọn khung giờ khác.',
                ], 422);
            }

            // Tính tổng giá tiền cho việc đặt sân
            $totalPriceBooking = $this->calculateFieldPrice($request->field_id, $request->start_time, $request->end_time);
            $totalPrice = $this->calculateFieldPrice($request->field_id, $request->start_time, $request->end_time);


            // Tính thêm tiền dịch vụ
            foreach ($request->services as $service) {
                $serviceDetails = Service::find($service['service_id']);
                $startTime = strtotime($request->start_time);
                $endTime = strtotime($request->end_time);
                $durationInHours = ($endTime - $startTime) / 3600;

                // Tính phí dịch vụ
                $totalPrice += ($serviceDetails->fee * $durationInHours);

            }
            if ($request->payment_method == "full") {
                $status = "Đã thanh toán";
                $deposit = $totalPrice;
            } else if ($request->payment_method == "partial") {
                $status = "Đã cọc";
                $deposit = $totalPrice * 0.4;
            } else if ($request->payment_method == "none") {
                $status = "Đã đặt";
                $request->merge(['payment_type' => 'direct']);
            }
            // Tạo mới booking
            $booking = Booking::create([
                'field_id' => $request->field_id,
                'user_id' => $request->user_id,
                'booking_date' => $request->booking_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'field_price' => $totalPriceBooking,
                'deposit' => $deposit ?? 0,
                'payment_type' => $request->payment_type ?? 'direct',
                'paypal_id' => $request->paypal_id ?? null,
                'status' => $status,
            ]);

            $bill = Bill::firstOrCreate(
                ['booking_id' => $booking->id],
            );

            $bill->total_amount = $totalPrice;

            $bill->save();

            foreach ($request->services as $service) {
                $serviceDetails = Service::find($service['service_id']);

                // Lưu vào bảng bill_services
                $billService = new BillService([
                    'bill_id' => $bill->id,
                    'service_id' => $service['service_id'],
                    'staff_id' => $service['staff_id'],
                    'fee' => $serviceDetails->fee,
                ]);
                $billService->save();
            }

            $smsService = new SmsService();  // Giả sử bạn đã tạo dịch vụ SmsService
            $smsService->sendBookingConfirmation($booking->id, $booking->user->phone, $booking->field->name, $booking->booking_date, $booking->start_time, $booking->end_time);


            DB::commit();
            return response()->json($booking, 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Dữ liệu không hợp lệ, vui lòng kiểm tra lại thông tin đã nhập.',
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Có lỗi xảy ra, vui lòng thử lại.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    public function cancel($id)
    {
        $booking = Booking::findOrFail($id);
        $smsService = new SmsService();
        if (!$booking) {
            return response()->json(['message' => 'Không tìm thấy booking'], 404);
        }

        $bill = $booking->bill; // Lấy hóa đơn liên quan

        // Thực hiện logic hủy booking như trước đây
        $bookingStartTime = strtotime($booking->booking_date . ' ' . $booking->start_time);
        $currentTime = time();

        if ($bookingStartTime - $currentTime >= 3600) {
            if ($booking->payment_type === 'paypal') {
                try {
                    $refundSuccess = $this->refundPayment($booking->paypal_id);
                    if ($refundSuccess) {
                        $booking->status = 'Đã hoàn tiền';
                        if ($bill) {
                            $bill->status = 'Đã hoàn tiền';
                            $bill->save();
                        }
                        $booking->save();

                        $smsService->sendCancellationWithRefund($booking->user->phone);
                        return response()->json(['message' => 'Booking đã bị hủy và hoàn tiền thành công.'], 200);
                    } else {
                        return response()->json(['message' => 'Không thể xử lý hoàn tiền.'], 500);
                    }
                } catch (Exception $e) {
                    return response()->json(['message' => 'Đã xảy ra lỗi trong quá trình hoàn tiền.', 'error' => $e->getMessage()], 500);
                }
            } else if ($booking->payment_type === 'zalopay') {
                $booking->status = 'Hoàn tiền';
                if ($bill) {
                    $bill->status = 'Hoàn tiền';
                    $bill->save();
                }
                $booking->save();

                $smsService->sendCancellationWithError($booking->user->phone);
                return response()->json(['message' => 'Booking đã bị hủy thành công. Bạn cần liên hệ với văn phòng để hoàn tiền.'], 200);
            } else if ($booking->payment_type === 'direct') {
                if ($booking->deposit == 0) {
                    $booking->status = 'Đã hoàn tiền';
                    $bill->status = 'Đã hoàn tiền';
                    $bill->save();
                } else {
                    $booking->status = 'Hoàn tiền';
                    $bill->status = 'Hoàn tiền';
                    $bill->save();
                }
                $booking->save();
                $smsService->sendCancellationWithError($booking->user->phone);
                return response()->json(['message' => 'Booking đã bị hủy thành công. Bạn cần liên hệ với văn phòng để hoàn tiền.'], 200);
            }
        } else {
            $booking->status = 'Hủy';
            if ($bill) {
                $bill->status = 'Hủy';
                $bill->save();
            }
            $booking->save();
            $smsService->sendCancellationWithNoRefund($booking->user->phone);

            return response()->json(['message' => 'Booking đã bị hủy. Không hoàn tiền cọc trong vòng 1 giờ trước giờ đá.'], 200);
        }
    }



    protected function refundPayment($paypalId)
    {
        // Thông tin API PayPal (Sử dụng token truy cập)
        $clientId = "AR-__Sbyg9YxPWMoLn7Aj2HJJY0ymqMe6JpsDq_xd2tk_V5-KKAr4JYIQ_ldqRfuJ3GwC7x0-eJ9V62d";
        $secret = "EIc--r_538vcm1rB8_hPqbEkwo_xxr9s4oTPHZp8JW4ezXxy7V3Og20Yu4S-FVUBAp8inuRI2KRu4zoW";

        // Lấy access token từ PayPal API
        $accessToken = $this->getPayPalAccessToken($clientId, $secret);
        Log::info($accessToken);
        if (!$accessToken) {
            throw new Exception('Failed to retrieve PayPal access token.');
        }

        // Lấy thông tin booking để tính toán số tiền hoàn trả
        $booking = Booking::where('paypal_id', $paypalId)->first();
        Log::info($paypalId);
        if (!$booking) {
            throw new Exception('Booking not found for the given PayPal ID.');
        }

        // Số tiền deposit trong VND
        $depositVND = $booking->deposit;

        // Chuyển đổi deposit từ VND sang USD
        $depositUSD = $depositVND / 24000;  // Tỷ giá 24000 VND = 1 USD
        Log::info($depositUSD);

        // Gửi yêu cầu hoàn tiền
        $refundUrl = "https://api-m.sandbox.paypal.com/v2/payments/captures/{$paypalId}/refund";

        $response = $this->sendRefundRequest($accessToken, $refundUrl, $depositUSD);

        // Kiểm tra kết quả phản hồi
        if ($response['status'] === 'success') {
            return true; // Hoàn tiền thành công
        } else {
            throw new Exception('Refund failed: ' . $response['message']);
        }
    }

    protected function sendRefundRequest($accessToken, $url, $amount)
    {
        // Gửi yêu cầu hoàn tiền với số tiền đã chuyển sang USD
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->post($url, [
                    'amount' => [
                        'value' => number_format($amount, 2, '.', ''), // Đảm bảo giá trị tiền có 2 chữ số thập phân
                        'currency_code' => 'USD', // Hoặc mã tiền tệ phù hợp
                    ],
                ]);

        if ($response->successful()) {
            return [
                'status' => 'success',
                'message' => 'Refund successful',
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Refund failed',
        ];
    }

    protected function getPayPalAccessToken($clientId, $secret)
    {
        $auth = base64_encode("{$clientId}:{$secret}");

        $response = Http::withHeaders([
            'Authorization' => "Basic {$auth}",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post('https://api-m.sandbox.paypal.com/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        return null; // Trả về null nếu không lấy được access token
    }

}
