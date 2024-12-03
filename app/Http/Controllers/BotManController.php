<?php
namespace App\Http\Controllers;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Actions\Select;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Menu;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Field;
use App\Models\Service;
use App\Models\Booking;
use App\Models\User;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;

use Carbon\Carbon;
use DateTime;

class BotManController extends Controller
{
    public function handle(Request $request)
    {

        $botman = app('botman');


        // Xử lý các tin nhắn khác
        $botman->hears('{message}', function ($botman, $message) use ($request) {
            $message = mb_strtolower($message, 'UTF-8');
            if (strpos($message, 'đặt sân') !== false || strpos($message, 'đặt') !== false) {
                $botman->startConversation(new BookingConversation);
            } else if (
                strpos($message, 'hello') !== false ||
                strpos($message, 'chào') !== false
            ) {
                $cookies = $request->header('Cookie');
                preg_match('/user_id=([^;]*)/', $cookies, $matches);
                $user_id = $matches[1] ?? null;

                $user = User::find($user_id);

                if($user){
                    $botman->reply("Xin chào, <b>{$user->name}</b>!");
                }
                else {
                    $botman->reply("Xin chào, bạn cần đăng nhập để sử dụng nhiều chức năng hơn!");
                }

            } else {
                $botman->reply("Xin lỗi, tôi không hiểu yêu cầu của bạn. Bạn có thể nói 'đặt sân' để bắt đầu hoặc 'chào' để chào hỏi.");
            }
        });

        $botman->listen();
    }
}

class BookingConversation extends Conversation
{
    protected $field;
    protected $startTime;
    protected $endTime;
    protected $bookingDate;
    protected $services = [];
    protected $selectedServices = [];

    public function askField()
    {
        // Tạo các nút bấm cho loại sân 5 người, 7 người và 11 người
        $buttons = [
            Button::create('Sân 5 người')->value('5'),
            Button::create('Sân 7 người')->value('7'),
            Button::create('Sân 11 người')->value('11'),
        ];

        // Tạo câu hỏi với các nút bấm
        $question = Question::create('Bạn muốn loại sân mấy người?')
            ->callbackId('field_selection')
            ->addButtons($buttons);

        // Hiển thị câu hỏi với các nút bấm
        $this->ask($question, function (Answer $answer) {
            $fieldType = $answer->getValue();

            // Lọc sân theo loại người
            $field = Field::where('type', $fieldType)->first();

            if ($field) {
                $this->field = $field;
                $this->say("Bạn đã chọn loại <b>{$fieldType} người</b>.");
                $this->askDate();  // Hỏi ngày đặt sân
            } else {
                $this->say("Không tìm thấy sân loại <b>{$fieldType}người</b>. Vui lòng chọn lại.");
                $this->repeat();  // Lặp lại nếu không tìm thấy sân
            }
        });
    }

    public function askDate()
    {
        // Hỏi ngày đặt sân
        $this->ask('Bạn muốn đặt sân vào ngày nào? (Ví dụ: 10-12-2024)', function (Answer $answer) {
            try {
                // Lấy dữ liệu ngày từ người dùng
                $dateText = $answer->getText();

                // Tạo đối tượng DateTime từ chuỗi ngày nhập vào theo định dạng 'd-m-Y'
                $date = DateTime::createFromFormat('d-m-Y', $dateText);

                // Lấy ngày hiện tại
                $currentDate = Carbon::now()->format('d-m-Y');

                // Kiểm tra nếu ngày hợp lệ và ngày không phải là quá khứ
                if ($date && $date->format('d-m-Y') === $dateText && $date >= Carbon::createFromFormat('d-m-Y', $currentDate)) {
                    // Nếu ngày hợp lệ và không phải quá khứ, lưu ngày đặt và in log
                    $this->bookingDate = $date;
                    \Log::info("Ngày đặt sân hợp lệ: " . $this->bookingDate->format('Y-m-d'));

                    // Hỏi thời gian sau khi có ngày
                    $this->askTime();
                } else {
                    // Nếu ngày không hợp lệ hoặc quá khứ, gửi thông báo lỗi và yêu cầu người dùng nhập lại
                    $this->say("Ngày bạn nhập không hợp lệ hoặc đã qua. Vui lòng nhập đúng định dạng và chọn ngày trong tương lai.");
                    $this->repeat(); // Lặp lại yêu cầu người dùng nhập ngày
                }
            } catch (\Exception $e) {
                // Xử lý ngoại lệ và ghi lại lỗi vào log
                \Log::error("Lỗi khi xử lý ngày: " . $e->getMessage());
                $this->say("Lỗi khi xử lý ngày. Vui lòng thử lại.");
                $this->repeat(); // Lặp lại yêu cầu nếu có lỗi khi tạo DateTime từ ngày
            }
        });
    }

    public function askTime()
    {
        // Hỏi thời gian bắt đầu và kết thúc
        $this->ask('Bạn muốn đặt sân vào thời gian nào? Ví dụ: 14:00 - 16:30', function (Answer $answer) {
            $input = $answer->getText();

            // Kiểm tra định dạng đầu vào có phải là "HH:mm - HH:mm" không
            if (!preg_match('/^\d{2}:\d{2} - \d{2}:\d{2}$/', $input)) {
                $this->say("Định dạng thời gian không đúng. Vui lòng nhập theo định dạng: HH:mm - HH:mm.");
                return $this->repeat();
            }

            // Tách giờ bắt đầu và kết thúc
            $times = explode(' - ', $input);

            // Kiểm tra xem các phút có phải là 00 hoặc 30 không
            foreach ($times as $time) {
                list($hour, $minute) = explode(':', $time);
                if (!in_array($minute, ['00', '30'])) {
                    $this->say("Phút phải là 00 hoặc 30. Vui lòng nhập lại.");
                    return $this->repeat();
                }
            }

            // Kiểm tra nếu giờ bắt đầu lớn hơn giờ kết thúc
            try {
                $startTime = Carbon::createFromFormat('H:i', $times[0]);
                $endTime = Carbon::createFromFormat('H:i', $times[1]);
                $now = Carbon::now(); // Lấy thời gian hiện tại

                // Kiểm tra xem giờ bắt đầu có phải là trong quá khứ không
                if ($this->bookingDate->format('d-m-Y') == Carbon::now()->format('d-m-Y') && $startTime->lt($now)) {
                    $this->say("Giờ bắt đầu không thể trong quá khứ. Vui lòng chọn lại.");
                    return $this->repeat();
                }

                // Kiểm tra nếu giờ bắt đầu lớn hơn giờ kết thúc
                if ($startTime->gt($endTime)) {
                    $this->say("Giờ bắt đầu không thể lớn hơn giờ kết thúc. Vui lòng chọn lại.");
                    return $this->repeat();
                }

                // Lưu thời gian dưới dạng chuỗi 'HH:mm' để sử dụng sau
                $this->startTime = $startTime->format('H:i');
                $this->endTime = $endTime->format('H:i');

                $this->checkAvailability();  // Kiểm tra sự có sẵn của thời gian

            } catch (\Exception $e) {
                $this->say("Có lỗi xảy ra khi xử lý thời gian. Vui lòng thử lại.");
                return $this->repeat();
            }
        });
    }


    public function checkAvailability()
    {
        // Kiểm tra xem thời gian đã chọn có sẵn không
        $existingBooking = Booking::where('field_id', $this->field->id)
            ->where('booking_date', $this->bookingDate->format('Y-m-d')) // Thêm điều kiện về ngày
            ->where('status', '!=', 'Hủy') // Kiểm tra không phải các booking bị hủy
            ->whereNotIn('status', ['Đã hoàn tiền', 'Hoàn tiền']) // Kiểm tra không phải booking đã hoàn tiền
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('start_time', '<=', $this->startTime)
                        ->where('end_time', '>', $this->startTime);
                })
                    ->orWhere(function ($q) {
                        $q->where('start_time', '<', $this->endTime)
                            ->where('end_time', '>=', $this->endTime);
                    })
                    ->orWhere(function ($q) {
                        $q->where('start_time', '>=', $this->startTime)
                            ->where('end_time', '<=', $this->endTime);
                    });
            })
            ->exists();

        if ($existingBooking) {
            $this->say("Khung giờ này đã được đặt từ {$this->startTime} đến {$this->endTime}, vui lòng chọn thời gian khác.");
            $this->repeat(); // Lặp lại yêu cầu người dùng chọn thời gian khác
        } else {
            // Nếu không có booking trùng khớp
            $this->askServices(); // Tiến hành hỏi các dịch vụ
        }
    }

    public function askServices($selectedServices = [])
    {
        // Lấy danh sách dịch vụ từ cơ sở dữ liệu
        $services = Service::with('role')->get()->map(function ($service) {
            // Lấy các user có role_id trùng với role_id trong Service
            $service->staffs = User::where('role_id', $service->role_id)->get();
            return $service;
        })->filter(function ($service) {
            return $service->staffs->isNotEmpty();
        })->values();

        // Loại bỏ dịch vụ đã được chọn trước đó khỏi danh sách
        $services = $services->filter(function ($service) use ($selectedServices) {
            return !in_array($service->id, $selectedServices);
        });

        // Tạo danh sách các button cho người dùng chọn dịch vụ
        $buttons = [];
        foreach ($services as $service) {
            $buttons[] = Button::create($service->service . ' (' . $service->fee . 'đ)')
                ->value($service->id); // Mỗi button mang giá trị là ID dịch vụ
        }

        // Thêm nút "Không" cho việc kết thúc chọn dịch vụ
        $buttons[] = Button::create('Không chọn thêm dịch vụ')
            ->value('no_more_services');

        // Kiểm tra nếu không còn dịch vụ nào để chọn
        if ($buttons) {
            // Tạo câu hỏi để gửi button
            $question = Question::create('Vui lòng chọn dịch vụ bạn muốn:')
                ->callbackId('service_selection')
                ->addButtons($buttons);

            // Bước 1: Gửi câu hỏi cho người dùng chọn dịch vụ
            $this->ask($question, function (Answer $answer) use ($selectedServices, $services) {
                $selectedService = $answer->getValue(); // Nhận giá trị của dịch vụ được chọn

                if ($selectedService === 'no_more_services') {
                    // Nếu người dùng chọn "Không chọn thêm dịch vụ", chuyển sang bước tiếp theo
                    $this->say('Cảm ơn bạn đã chọn dịch vụ! Chúng tôi sẽ tiếp tục với quá trình tiếp theo.');
                    $this->confirmBooking(); // Chuyển sang bước xác nhận booking
                    return;
                }

                // Lưu dịch vụ đã chọn vào mảng đã chọn
                // $this->selectedServices[] = $selectedService;
                $selectedServices[] = $selectedService;

                $service = Service::with('role.users')->find($selectedService);
                $this->services[] = $service;
                if ($service) {
                    // Bước 2: Hiển thị thông tin chi tiết dịch vụ
                    $this->say('<div class="chatbot">' .
                        '<p>Bạn đã chọn dịch vụ: ' . $service->service . '</p>' .
                        '<p>Mô tả: ' . $service->description . '</p>' .
                        '<p>Giá: ' . number_format($service->fee, 0, ',', '.') . 'đ/h</p>' .
                        '</div>');
                }

                // Bước 3: Hỏi người dùng chọn nhân viên cho dịch vụ
                $this->askStaffForService($service, $selectedServices, $services);
            });
        } else {
            // Nếu hết dịch vụ, chuyển sang bước tiếp theo ngay lập tức
            $this->say('Bạn đã chọn tất cả các dịch vụ có sẵn!');
            $this->confirmBooking();
        }
    }

    public function askStaffForService($service, $selectedServices, $services)
    {
        // Tạo danh sách nhân viên cho dịch vụ này
        $staffButtons = [];
        foreach ($service->role->users as $staff) {
            $staffButtons[] = Button::create($staff->name)
                ->value($staff->id); // Mỗi button mang giá trị là ID nhân viên
        }

        // Nếu có nhân viên để chọn
        if ($staffButtons) {
            // Tạo câu hỏi để người dùng chọn nhân viên
            $question = Question::create('Vui lòng chọn nhân viên cho dịch vụ ' . $service->service . ':')
                ->callbackId('staff_selection_' . $service->id)
                ->addButtons($staffButtons);

            // Bước 4: Gửi câu hỏi cho người dùng chọn nhân viên
            $this->ask($question, function (Answer $answer) use ($service, $selectedServices, $services) {
                $selectedStaff = $answer->getValue(); // Nhận giá trị nhân viên được chọn

                // Lưu nhân viên đã chọn vào dịch vụ
                $service->selected_staff_id = $selectedStaff;
                $this->services[] = [
                    'service' => $service,   // Store the entire service object
                    'staff_id' => $selectedStaff // Store the selected staff ID
                ];

                $this->selectedServices[] = [
                    'service_id' => $service->id,
                    'staff_id' => $selectedStaff
                ];


                // Kiểm tra sau khi chọn nhân viên, có còn dịch vụ nào để chọn không
                $remainingServices = $services->filter(function ($service) use ($selectedServices) {
                    return !in_array($service->id, $selectedServices);
                });

                if ($remainingServices->isEmpty()) {
                    // Nếu hết dịch vụ, chuyển sang bước tiếp theo
                    $this->say('Bạn đã chọn tất cả các dịch vụ có sẵn!');
                    $this->confirmBooking(); // Chuyển sang bước tiếp theo (xác nhận đặt sân)
                    return; // Dừng lại để không hỏi thêm
                }

                // Nếu còn dịch vụ để chọn, gọi lại hàm để hiển thị dịch vụ tiếp theo
                $this->askServices($selectedServices); // Gọi lại hàm askServices để tiếp tục chọn dịch vụ
            });
        } else {
            // Nếu không có nhân viên, chuyển sang bước tiếp theo ngay lập tức
            $this->say('Dịch vụ này không có nhân viên để chọn, chuyển sang bước tiếp theo.');
            $this->askServices($selectedServices); // Tiếp tục chọn dịch vụ tiếp theo
        }
    }


    public function confirmBooking()
    {
        $bookingController = new BookingController();
        $isAvailable = $bookingController->isTimeSlotAvailable(
            field_id: $this->field->id,
            booking_date: $this->bookingDate->format('Y-m-d'),
            start_time: $this->startTime,
            end_time: $this->endTime
        );
        if (!$isAvailable) {
            return response()->json([
                'message' => 'Khung giờ này đã được đặt. Vui lòng chọn khung giờ khác.',
            ], 422);
        }

        $fieldPrice = $bookingController->calculateFieldPrice(
            field_id: $this->field->id,
            start_time: $this->startTime,
            end_time: $this->endTime
        );

        foreach ($this->selectedServices as $service) {
            $serviceDetails = Service::find($service['service_id']);
            $startTime = strtotime($this->startTime);
            $endTime = strtotime($this->endTime);
            $durationInHours = ($endTime - $startTime) / 3600;

            // Tính phí dịch vụ
            $fieldPrice += ($serviceDetails->fee * $durationInHours);

        }

        $servicesHtml = '<div>';
        $stt = 1; // Biến đếm bắt đầu từ 1

        // Sử dụng $this->selectedService thay vì $this->services
        foreach ($this->selectedServices as $service) {
            // Kiểm tra nếu dịch vụ và nhân viên hợp lệ
            if (isset($service['service_id']) && isset($service['staff_id'])) {
                // Tìm dịch vụ theo service_id
                $serviceDetails = Service::find($service['service_id']); // Giả sử bạn có model Service
                $staffDetails = User::find($service['staff_id']); // Giả sử bạn có model User cho nhân viên

                // Đảm bảo dịch vụ và nhân viên tồn tại
                if ($serviceDetails && $staffDetails) {
                    $servicesHtml .= '<p>' .
                        $stt . '. ' .
                        htmlspecialchars($serviceDetails->service) . ' - ' .
                        htmlspecialchars($staffDetails->name) .
                        ' (' . number_format($serviceDetails->fee, 0, ',', '.') . 'đ/h)' .
                        '</p>';
                    $stt++; // Tăng biến đếm sau mỗi lần lặp
                }
            }
        }

        $servicesHtml .= '</div>';

        // Tạo thông tin về booking
        $bookingDetails = '<div class="chatbot">' .
            '<p><strong>Sân: </strong>' . $this->field->type . ' - ' . $this->field->name . '</p>' .
            '<p><strong>Ngày: </strong>' . $this->bookingDate->format('d-m-Y') . '</p>' .
            '<p><strong>Thời gian: </strong>' . $this->startTime . ' - ' . $this->endTime . '</p>' .
            '<p><strong>Dịch vụ kèm: </strong></p>' .
            $servicesHtml .
            '<p><strong>Tổng: </strong>' . number_format($fieldPrice, 0, ',') . 'đ</p>' .
            '</div>';

        // Hiển thị thông tin đặt sân và dịch vụ
        $this->say($bookingDetails);
        $this->askPaymentMethod($fieldPrice);
        // $this->askBookingThis();
    }

    public function askPaymentMethod($fieldPrice)
    {
        $full = number_format($fieldPrice, 0, ',');
        $deposit = number_format($fieldPrice * 0.4, 0, ',');

        $buttons = [
            Button::create("Thanh toán toàn bộ: $full đ")->value('full'),
            Button::create("Thanh toán cọc (40%): $deposit đ")->value('partial'),
        ];


        $cookies = request()->header('Cookie');
        preg_match('/user_id=([^;]*)/', $cookies, $matches);
        $user_id = $matches[1] ?? null;

        if (!$user_id) {
            $this->say("Không tìm thấy thông tin người dùng. Vui lòng thử lại.");
            return;
        }

        // Lấy thông tin người dùng từ database
        $user = User::find($user_id);
        if ($user->vip) {
            $buttons[] = Button::create("Đặt sân chỉ dành cho VIP")->value('vip_booking');
        }

        $question = Question::create("Bạn muốn thanh toán toàn bộ hay đặt cọc? \n(Lưu ý: Thanh toán chỉ hỗ trợ qua *ZaloPay*)")
            ->callbackId('payment_method')
            ->addButtons($buttons);

        // Hỏi người dùng phương thức thanh toán
        $this->ask($question, function (Answer $answer) use ($fieldPrice, $user) {
            $paymentMethod = $answer->getValue();
            $data = [
                'booking_date' => $this->bookingDate->format('Y-m-d'),
                'field_id' => $this->field->id,
                'user_id' => $user->id,
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'services' => $this->selectedServices,
                'payment_method' => $paymentMethod,
                'payment_type' => 'zalopay',
            ];
            if ($paymentMethod === 'full') {
                // Tạo Request và gọi BookingController
                $request = new Request($data);
                $paymentController = new PaymentController();
                $response = $paymentController->createZaloPayOrder($request);

                $responseArray = json_decode($response->getContent(), true);

                // Kiểm tra sự tồn tại của order_url trong phản hồi
                if (isset($responseArray['zalopay']['order_url'])) {
                    $orderUrl = $responseArray['zalopay']['order_url'];
                    $this->say("Vui lòng nhấn vào liên kết dưới đây để thanh toán:\n
                        <a href=$orderUrl >Zalopay</a>
                    ");
                } else {
                    $this->say('Không thể tạo đơn thanh toán. Vui lòng thử lại.');
                }
                // $this->confirmPayment($totalPrice, 'toàn bộ');
            } elseif ($paymentMethod === 'partial') {
                $request = new Request($data);
                $paymentController = new PaymentController();
                $response = $paymentController->createZaloPayOrder($request);

                $responseArray = json_decode($response->getContent(), true);

                // Kiểm tra sự tồn tại của order_url trong phản hồi
                if (isset($responseArray['zalopay']['order_url'])) {
                    $orderUrl = $responseArray['zalopay']['order_url'];
                    $this->say("Vui lòng nhấn vào liên kết dưới đây để thanh toán:\n
                        <a href=$orderUrl >Zalopay</a>
                    ");
                } else {
                    $this->say('Không thể tạo đơn thanh toán. Vui lòng thử lại.');
                }
            } elseif ($paymentMethod === 'vip_booking') {
                // Người dùng chọn thanh toán cọc
                $depositPrice = $fieldPrice * 0.4;
                $this->createBooking();
            }
        });
    }

    public function createBooking()
    {
        try {
            // Lấy user_id từ cookie
            $cookies = request()->header('Cookie');
            preg_match('/user_id=([^;]*)/', $cookies, $matches);
            $user_id = $matches[1] ?? null;

            if (!$user_id) {
                $this->say("Không tìm thấy thông tin người dùng. Vui lòng thử lại.");
                return;
            }

            // Lấy thông tin người dùng từ database
            $user = User::find($user_id);

            if (!$user) {
                $this->say("Không tìm thấy người dùng với ID: $user_id");
                return;
            }

            // Dữ liệu cần gửi tới controller BookingController
            $data = [
                'field_id' => $this->field->id,
                'user_id' => $user->id,
                'booking_date' => $this->bookingDate->format('Y-m-d'),
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'services' => $this->selectedServices,
                'payment_method' => 'none',
            ];

            // Tạo Request và gọi BookingController
            $request = new Request($data);
            $bookingController = new BookingController();
            $response = $bookingController->store($request);

            \Log::info($response);
            // Kiểm tra trạng thái phản hồi
            if ($response->getStatusCode() === 201) { // 201: Created
                \Log::info($response);
                $this->say("Đặt sân của bạn đã thành công!");
            } else {
                $this->say("Có lỗi xảy ra khi tạo booking. Vui lòng thử lại.");
            }
        } catch (\Exception $e) {
            \Log::error("Lỗi khi tạo booking: " . $e->getMessage());
            $this->say("Có lỗi xảy ra khi tạo booking. Vui lòng thử lại.");
        }
    }


    public function run()
    {
        $this->askField();
    }
}

