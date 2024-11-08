<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BookingController;
use App\Models\Bill;
use App\Models\Booking;
use App\Models\Service;

class PaymentController extends Controller
{
    function createZaloPayOrder(Request $request)
    {
        // Log::info('thong tin vao' . $request);
        $config = [
            "app_id" => 2554,
            "key1" => "sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn",
            "key2" => "trMrHtvjo6myautxDUiAcYsVtaeQ8nhf",
            "endpoint" => "https://sb-openapi.zalopay.vn/v2/create"
        ];
        $bookingController = new BookingController();
        $isAvailable = $bookingController->isTimeSlotAvailable(
            field_id: $request->field_id,
            booking_date: $request->booking_date,
            start_time: $request->start_time,
            end_time: $request->end_time
        );
        if (!$isAvailable) {
            return response()->json([
                'message' => 'Khung giờ này đã được đặt. Vui lòng chọn khung giờ khác.',
            ], 422);
        }

        $fieldPrice = $bookingController->calculateFieldPrice(
            field_id: $request->field_id,
            start_time: $request->start_time,
            end_time: $request->end_time
        );

        foreach ($request->services as $service) {
            $serviceDetails = Service::find($service['service_id']);
            $startTime = strtotime($request->start_time);
            $endTime = strtotime($request->end_time);
            $durationInHours = ($endTime - $startTime) / 3600;

            // Tính phí dịch vụ
            $fieldPrice += ($serviceDetails->fee * $durationInHours);
            
        }

        $deposit = $fieldPrice * (40 / 100);

        $amount = 0;
        $title = "";

        if ($request->payment_method == "partial") {
            $amount = $deposit;
            $title = "cọc tiền đặt sân";
        } else {
            $amount = $fieldPrice;
            $title = "đặt sân";
        }
        // $embeddata = '{}';
        $embeddata =
            [
                'booking_date' => $request->booking_date,
                'deposit' => $request->deposit,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'field_id' => $request->field_id,
                'user_id' => $request->user_id,
                'user_name' => $request->user_name,
                'user_phone' => $request->user_phone,
                'payment_method' => $request->payment_method,
                'services' => $request->services,
                'payment_type' => 'zalopay',
                'redirecturl' => "http://127.0.0.1:3002/booking"
            ];
        Log::error('embed_data_create' . json_encode($embeddata));
        $items = '[]';
        $transID = rand(0, 1000000);
        $order = [
            "app_id" => $config["app_id"],
            "app_time" => round(microtime(true) * 1000),
            "app_trans_id" => date("ymd") . "_" . $transID,
            "app_user" => "user123",
            "item" => $items,
            "embed_data" => json_encode($embeddata),
            "amount" => $amount,
            "description" => " Thanh toán $title #$transID",
            "bank_code" => "zalopayapp",
            "callback_url" => "https://mlatbooking.serveo.net/api/zalopay/callback",
        ];

        // appid|app_trans_id|appuser|amount|apptime|embeddata|item
        $data = $order["app_id"] . "|" . $order["app_trans_id"] . "|" . $order["app_user"] . "|" . $order["amount"]
            . "|" . $order["app_time"] . "|" . $order["embed_data"] . "|" . $order["item"];
        $order["mac"] = hash_hmac("sha256", $data, $config["key1"]);

        $context = stream_context_create([
            "http" => [
                "header" => "Content-type: application/x-www-form-urlencoded\r\n",
                "method" => "POST",
                "content" => http_build_query($order)
            ]
        ]);

        $resp = file_get_contents($config["endpoint"], false, $context);
        $result = json_decode($resp, true);

        return response()->json(['zalopay' => $result]);
    }

    public function zalopayCallback()
    {   
        Log::info("Hello callback");
        $result = [];

        try {
            $key2 = "trMrHtvjo6myautxDUiAcYsVtaeQ8nhf";
            $postdata = file_get_contents('php://input');
            $postdatajson = json_decode($postdata, true);
            $mac = hash_hmac("sha256", $postdatajson["data"], $key2);
            $requestmac = $postdatajson["mac"];

            if (strcmp($mac, $requestmac) != 0) {
                $result["return_code"] = -1;
                $result["return_message"] = "mac not equal";
                Log::error("false");
            } else {
                $datajson = json_decode($postdatajson['data'], true);
                $embedData = json_decode($datajson['embed_data'], true);

                $request = new Request($embedData);
                $bookingController = new BookingController();
                $bookingController->store($request);

                $result["return_code"] = 1;
                $result["return_message"] = "success";
            }
        } catch (\Exception $e) {
            Log::info('return'. $e);
            $result["return_code"] = 0;
            $result["return_message"] = $e->getMessage();
        }

        return json_encode($result);
    }

    function createZaloPayForBill(Request $request)
    {
        // Log::info('thong tin vao' . $request);
        $config = [
            "app_id" => 2554,
            "key1" => "sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn",
            "key2" => "trMrHtvjo6myautxDUiAcYsVtaeQ8nhf",
            "endpoint" => "https://sb-openapi.zalopay.vn/v2/create"
        ];
        // $embeddata = '{}';

        $bill = Bill::findOrFail($request->bill_id);

        $amount = intval($bill->total_amount);

        $embeddata =
            [   
                'bill_id' => $request->bill_id,
                'redirecturl' => "http://127.0.0.1:3002/booking"
            ];
        Log::info('embed_data_create' . json_encode($embeddata));
        Log::info($bill->total_amount);
        $items = '[]';
        $transID = rand(0, 1000000);
        $order = [
            "app_id" => $config["app_id"],
            "app_time" => round(microtime(true) * 1000),
            "app_trans_id" => date("ymd") . "_" . $transID,
            "app_user" => "user123",
            "item" => $items,
            "embed_data" => json_encode($embeddata),
            "amount" => $amount,
            "description" => " Thanh toán hóa đơn #$transID",
            "bank_code" => "zalopayapp",
            "callback_url" => "https://mlatbooking.serveo.net/api/zalopay/callbackBill",
        ];

        // appid|app_trans_id|appuser|amount|apptime|embeddata|item
        $data = $order["app_id"] . "|" . $order["app_trans_id"] . "|" . $order["app_user"] . "|" . $order["amount"]
            . "|" . $order["app_time"] . "|" . $order["embed_data"] . "|" . $order["item"];
        $order["mac"] = hash_hmac("sha256", $data, $config["key1"]);

        $context = stream_context_create([
            "http" => [
                "header" => "Content-type: application/x-www-form-urlencoded\r\n",
                "method" => "POST",
                "content" => http_build_query($order)
            ]
        ]);

        $resp = file_get_contents($config["endpoint"], false, $context);
        $result = json_decode($resp, true);

        return response()->json(['zalopay' => $result]);
    }


    public function zalopayCallbackBill()
    {   
        Log::info("Hello callback");
        $result = [];

        try {
            $key2 = "trMrHtvjo6myautxDUiAcYsVtaeQ8nhf";
            $postdata = file_get_contents('php://input');
            $postdatajson = json_decode($postdata, true);
            $mac = hash_hmac("sha256", $postdatajson["data"], $key2);
            $requestmac = $postdatajson["mac"];

            if (strcmp($mac, $requestmac) != 0) {
                $result["return_code"] = -1;
                $result["return_message"] = "mac not equal";
                Log::error("false");
            } else {
                $datajson = json_decode($postdatajson['data'], true);
                $embedData = json_decode($datajson['embed_data'], true);

                Log::info($embedData);
                
                $bill = Bill::findOrFail($embedData['bill_id']);
                $booking = Booking::findOrFail($bill->booking_id);

                $booking->status = "Đã thanh toán";

                $bill->status = "Đã thanh toán";

                $booking->save();
                $bill->save();
                Log::info($bill);

                $result["return_code"] = 1;
                $result["return_message"] = "success";
            }
        } catch (\Exception $e) {
            Log::info('return'. $e);
            $result["return_code"] = 0;
            $result["return_message"] = $e->getMessage();
        }

        return json_encode($result);
    }
}
