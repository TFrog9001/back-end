<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use HTTP_Request2;

class SmsService
{
    public function sendBookingConfirmation($bookingId, $phone, $fieldName, $bookingDate, $startTime, $endTime)
    {
        $message = "Đặt sân thành công: Phiếu {$bookingId} Sân: {$fieldName}, Ngày: {$bookingDate}, Giờ: {$startTime} - {$endTime}.";
        $this->sendSms($phone, $message);
    }

    public function sendCancellationWithRefund($phone)
    {
        $message = "Hủy đơn thành công. Nếu bạn đã thanh toán qua PayPal, tiền đã được hoàn vào ví. Nếu có thắc mắc, vui lòng liên hệ 0948330411 hoặc đến trực tiếp văn phòng.";
        $this->sendSms($phone, $message);
    }

    public function sendCancellationWithError($phone)
    {
        $message = "Hủy đơn thành công. Lỗi hệ thống. Nếu có thắc mắc, vui lòng liên hệ 0948330411 hoặc đến trực tiếp văn phòng để nhận lại tiền.";
        $this->sendSms($phone, $message);
    }

    public function sendCancellationWithNoRefund($phone)
    {
        $message = "Hủy đơn thành công. Nếu có thắc mắc vui lòng liên hệ 0948330411 hoặc đến trực tiếp văn phòng";
        $this->sendSms($phone, $message);
    }

    private function formatPhoneNumber($phone)
    {
        if (substr($phone, 0, 1) == '0') {
            return '84' . substr($phone, 1);
        }

        return $phone;
    }

    private function sendSms($phone, $message)
    {

        $formattedPhone = $this->formatPhoneNumber($phone);
        // Tạo một đối tượng HTTP_Request2
        $request = new HTTP_Request2();
        $request->setUrl('https://kqpv2n.api.infobip.com/sms/2/text/advanced');
        $request->setMethod(HTTP_Request2::METHOD_POST);
        $request->setConfig([
            'follow_redirects' => true
        ]);

        // Lấy API Key từ environment
        $apiKey = env('INFOBIP_API_KEY');

        $request->setHeader([
            'Authorization' => "App {$apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]);

        // Set nội dung tin nhắn
        $request->setBody(json_encode([
            'messages' => [
                [
                    'destinations' => [
                        ['to' => $formattedPhone],
                    ],
                    'from' => '447491163443',
                    'text' => trim($message),
                ],
            ],
        ]));

        // Gửi yêu cầu và xử lý phản hồi
        try {
            $response = $request->send();
            if ($response->getStatus() == 200) {
                return $response->getBody();
            } else {
                throw new \Exception('Lỗi gửi tin nhắn: ' . $response->getStatus() . ' ' . $response->getReasonPhrase());
            }
        } catch (\HTTP_Request2_Exception $e) {
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    public function sendSmsWithRateLimit($phone, $message)
    {
        $formattedPhone = $this->formatPhoneNumber($phone);
        $cacheKey = 'sms_limit_' . $formattedPhone;
        $limit = 3; // Số lần tối đa
        $duration = 3600; // Thời gian giới hạn (giây)

        $attempts = Cache::get($cacheKey, 0);
        if ($attempts >= $limit) {
            throw new \Exception('Bạn đã vượt quá số lần gửi tin nhắn trong một giờ.');
        }

        Cache::put($cacheKey, $attempts + 1, $duration);
        $this->sendSms($phone, $message);
    }
}
