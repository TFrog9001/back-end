<?php
namespace App\Http\Controllers;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Actions\Select;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Menu;


use Illuminate\Http\Request;
use App\Models\Field;
use App\Models\Service;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use DateTime;

class BotManController extends Controller
{
    public function handle(Request $request)
    {   
        
        \Log::info($request);

        $botman = app('botman');

        $botman->hears('{message}', function ($botman, $message) {
            if (strpos($message, 'đặt sân') !== false || strpos($message, 'đặt') !== false) {
                $botman->startConversation(new BookingConversation);
            } else {
                $botman->reply("Lỗi! Bạn có thể nói 'đặt sân' để bắt đầu.");
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
                $this->say("Bạn đã chọn loại {$fieldType} người.");
                $this->askDate();  // Hỏi ngày đặt sân
            } else {
                $this->say("Không tìm thấy sân loại {$fieldType} người. Vui lòng chọn lại.");
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

                // Kiểm tra nếu ngày hợp lệ
                if ($date && $date->format('d-m-Y') === $dateText) {
                    // Nếu ngày hợp lệ, lưu ngày đặt và in log
                    $this->bookingDate = $date;
                    \Log::info("Ngày đặt sân hợp lệ: " . $this->bookingDate->format('Y-m-d'));

                    // Hỏi thời gian sau khi có ngày
                    $this->askTime();
                } else {
                    // Nếu ngày không hợp lệ, gửi thông báo lỗi và yêu cầu người dùng nhập lại
                    $this->say("Ngày bạn nhập không hợp lệ. Vui lòng nhập đúng định dạng: dd-mm-yyyy.");
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
        $this->ask('Bạn muốn đặt sân vào thời gian nào? Ví dụ: 14:00 - 16:00', function (Answer $answer) {
            $times = explode(' - ', $answer->getText());
            \Log::info($times);  // Log để kiểm tra các giá trị nhận được

            if (count($times) == 2) {
                try {
                    // Tạo đối tượng Carbon cho thời gian bắt đầu và kết thúc
                    $startTime = Carbon::createFromFormat('H:i', $times[0]);
                    $endTime = Carbon::createFromFormat('H:i', $times[1]);

                    // Kiểm tra nếu giờ bắt đầu lớn hơn giờ kết thúc
                    if ($startTime->gt($endTime)) {
                        $this->say("Giờ bắt đầu không thể lớn hơn giờ kết thúc. Vui lòng chọn lại.");
                        $this->repeat();  // Lặp lại nếu giờ không hợp lệ
                        return;
                    }

                    // Log thời gian đã ép kiểu thành DateTime
                    \Log::info("Thời gian bắt đầu: " . $startTime->format('H:i'));  // In ra định dạng thời gian: '14:00'
                    \Log::info("Thời gian kết thúc: " . $endTime->format('H:i'));

                    // Lưu thời gian dưới dạng chuỗi 'HH:mm' để sử dụng sau
                    $this->startTime = $startTime->format('H:i');
                    $this->endTime = $endTime->format('H:i');

                    $this->checkAvailability();  // Kiểm tra sự có sẵn của thời gian

                } catch (\Exception $e) {
                    // Log lỗi nếu có vấn đề khi tạo đối tượng Carbon
                    \Log::error("Lỗi khi xử lý giờ: " . $e->getMessage());
                    $this->say("Lỗi khi xử lý giờ. Vui lòng thử lại.");
                    $this->repeat();  // Lặp lại nếu có lỗi khi tạo Carbon từ giờ
                }
            } else {
                $this->say("Vui lòng nhập thời gian theo định dạng '14:00 - 16:00'.");
                $this->repeat();  // Lặp lại nếu không đúng định dạng
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
                $selectedService = $answer->getValue(); // Nhận giá trị của dịch vụ được chọn (service_x)

                if ($selectedService === 'no_more_services') {
                    // Nếu người dùng chọn "Không chọn thêm dịch vụ", chuyển sang bước tiếp theo
                    $this->say('Cảm ơn bạn đã chọn dịch vụ! Chúng tôi sẽ tiếp tục với quá trình tiếp theo.');
                    $this->confirmBooking(); // Chuyển sang bước xác nhận booking
                    return;
                }

                // Lưu dịch vụ đã chọn vào mảng đã chọn
                $selectedServices[] = $selectedService;

                $service = Service::find($selectedService);
                $this->services[] = $service;
                if ($service) {
                    // Bước 2: Hiển thị thông tin chi tiết dịch vụ
                    $this->say('<div class="chatbot">' .
                        '<p>Bạn đã chọn dịch vụ: ' . $service->service . '</p>' .
                        '<p>Mô tả: ' . $service->description . '</p>' .
                        '<p>Giá: ' . $service->fee . 'đ/h</p>' .
                        '</div>');
                }


                // Kiểm tra sau khi chọn dịch vụ nếu còn dịch vụ nào để chọn không
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
            // Nếu hết dịch vụ, chuyển sang bước tiếp theo ngay lập tức
            $this->say('Bạn đã chọn tất cả các dịch vụ có sẵn!');
            $this->confirmBooking();
        }
    }


    public function confirmBooking()
    {
        // Tạo chuỗi thông tin đặt sân và dịch vụ
        $servicesList = '';
        foreach ($this->services as $index => $service) {
            $servicesList .= ($index + 1) . '. ' . $service->service . "\n"; // Hiển thị dịch vụ theo số thứ tự
        }


        // Tạo thông tin về booking
        $bookingDetails = '<div class="chatbot">' .
            '<p><strong>Sân: </strong>' . $this->field->type . ' - ' . $this->field->name . '</p>' .
            '<p><strong>Ngày: </strong>' . $this->bookingDate->format('d-m-Y') . '</p>' .
            '<p><strong>Thời gian: </strong>' . $this->startTime . ' - ' . $this->endTime . '</p>' .
            '<p><strong>Dịch vụ kèm: </strong></p>' .
            '<pre>' . $servicesList . '</pre>' .
            '</div>';

        // Hiển thị thông tin đặt sân và dịch vụ
        $this->say($bookingDetails);
    }





    // public function confirmBooking()
    // {
    //     // Xác nhận thông tin đặt sân
    //     $serviceNames = implode(', ', array_map(function ($service) {
    //         return $service->name;
    //     }, $this->services));

    //     $this->ask("Bạn muốn xác nhận đặt sân {$this->field->name} vào ngày {$this->bookingDate->format('Y-m-d')} từ {$this->startTime->format('H:i')} đến {$this->endTime->format('H:i')} với các dịch vụ: {$serviceNames}? (Có/Không)", function (Answer $answer) {
    //         if (strtolower($answer->getText()) == 'có') {
    //             $this->storeBooking();
    //         } else {
    //             $this->say("Đặt sân đã bị hủy.");
    //         }
    //     });
    // }

    public function storeBooking()
    {
        // Lưu thông tin đặt sân vào cơ sở dữ liệu
        $booking = new Booking();
        $booking->field_id = $this->field->id;
        $booking->start_time = $this->startTime;
        $booking->end_time = $this->endTime;
        $booking->save();

        foreach ($this->services as $service) {
            $booking->services()->attach($service->id);
        }

        $this->say("Đặt sân thành công! Chúc bạn có thời gian vui vẻ.");
    }

    public function run()
    {
        $this->askField();
    }
}

