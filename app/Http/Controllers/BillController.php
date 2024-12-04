<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\Supply;
use App\Models\BillSupply;
use App\Models\Booking;
use App\Models\User;

use App\Events\NotificationSent;

use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    public function getBillByBookingId(Request $request)
    {
        $bill = Bill::with('supplies')->where('booking_id', '=', $request->booking_id);
        return response()->json($bill);
    }

    public function show($id, Request $request)
    {
        $bill = Bill::with('supplies')->where('booking_id', '=', $request->booking_id);
        return response()->json($bill);

    }

    public function addItems(Request $request)
    {
        $request->validate([
            'bill_id' => 'required|integer|exists:bills,id',
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:supplies,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $billSupplies = [];

            DB::transaction(function () use ($request, &$billSupplies) {
                $bill = Bill::with('booking')->findOrFail($request->input('bill_id'));

                $total_amount = 0;
                $productDetails = []; // Mảng chứa tên sản phẩm và số lượng

                foreach ($request->input('items') as $itemData) {
                    $supply = Supply::findOrFail($itemData['id']);

                    if ($supply->quantity < $itemData['quantity']) {
                        throw new \Exception('Số lượng yêu cầu vượt quá hàng tồn kho cho sản phẩm: ' . $supply->name);
                    }

                    // Kiểm tra xem sản phẩm đã có trong BillSupply chưa
                    $existingSupply = $bill->supplies()->where('supply_id', $supply->id)->first();

                    if ($existingSupply) {
                        // Nếu sản phẩm đã tồn tại, cập nhật số lượng và giá
                        $existingSupply->quantity += $itemData['quantity'];
                        $existingSupply->price = $supply->price;
                        $existingSupply->save();
                    } else {
                        // Nếu sản phẩm chưa tồn tại, tạo mới
                        $billSupply = $bill->supplies()->create([
                            'supply_id' => $supply->id,
                            'quantity' => $itemData['quantity'],
                            'price' => $supply->price,
                        ]);
                    }

                    // Cập nhật số lượng trong tồn kho
                    $supply->quantity -= $itemData['quantity'];
                    $supply->save();

                    $total_amount += $itemData['quantity'] * $supply->price;

                    // Thêm tên sản phẩm và số lượng vào mảng productDetails
                    $productDetails[] = "{$supply->name} (x{$itemData['quantity']})";

                    // Thêm thông tin vào mảng billSupplies
                    $billSupplies[] = [
                        'supply_id' => $supply->id,
                        'quantity' => $itemData['quantity'],
                        'price' => $supply->price,
                        'bill_id' => $bill->id,
                        'id' => $existingSupply ? $existingSupply->id : $billSupply->id, // ID từ BillSupply
                        'name' => $supply->name,
                        'image' => $supply->image,
                    ];
                }

                // Cập nhật tổng số tiền của hóa đơn
                $bill->total_amount += $total_amount;
                $bill->save();

                // Tạo thông báo với tên sản phẩm và số lượng đã thêm
                $productDetailsList = implode(', ', $productDetails); // Nối tên sản phẩm và số lượng thành chuỗi
                $message = "Người dùng đã thêm sản phẩm: {$productDetailsList} vào phiếu #{$bill->booking->id}. Tổng tiền mới: " . number_format($bill->total_amount) . " VND";
                broadcast(new NotificationSent($message))->toOthers();
            });

            return response()->json([
                'message' => 'Sản phẩm đã được thêm thành công!',
                'supplies' => $billSupplies,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }


    public function createBill($id)
    {
        try {
            $bill = Bill::with('booking', 'supplies')->findOrFail($id);
            return response()->json($bill, 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);

        }
    }

    public function paymentBill($id)
    {
        try {
            // Tìm bill dựa vào ID
            $bill = Bill::findOrFail($id);

            // Tìm booking tương ứng với bill
            $booking = Booking::findOrFail($bill->booking_id);

            // Cập nhật trạng thái của booking và bill
            $booking->status = "Đã thanh toán";
            $bill->status = "Đã thanh toán";

            $booking->save();
            $bill->save();

            // Lấy user từ booking
            $user = User::findOrFail($booking->user_id);

            \Log::info($user);

            // Kiểm tra điều kiện 10 lần đặt phòng liên tiếp "Đã thanh toán" và không có "Hủy"
            $recentBookings = Booking::where('user_id', $user->id)
                ->orderBy('created_at', 'desc') // Lấy danh sách đặt phòng mới nhất
                ->take(10)                     // Lấy 10 lần đặt phòng gần nhất
                ->get();

            $validStatuses = ["Đã thanh toán", "Đã hoàn tiền", "Hoàn tiền", "Đã đặt", "Đã cọc"];

            // Kiểm tra nếu tất cả 10 lần đặt phòng đều "Đã thanh toán" và không có "Hủy"
            $allPaid = $recentBookings->every(function ($booking) use ($validStatuses) {
                return in_array($booking->status, $validStatuses);
            });

            $noCancelled = !$recentBookings->contains(function ($booking) {
                return $booking->status === "Hủy";
            });

            if ($recentBookings->count() == 10 && $allPaid && $noCancelled) {
                \Log::info($allPaid);
                \Log::info($noCancelled);
                $user->vip = 1; // Cập nhật trạng thái VIP
                $user->save();
            }

            return response()->json([$bill, $user, $recentBookings->count(), $allPaid, $noCancelled], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }


    ///

    public function getBillSupplies($id)
    {
        $supplies = BillSupply::with('supply')->where('bill_id', '=', $id)->get();
        return response()->json($supplies, 200);

    }

    public function updateBillSupply(Request $request, $id)
    {

        DB::beginTransaction();
        try {
            $billSupply = BillSupply::findOrFail($id);

            $bill = Bill::findOrFail($billSupply->bill_id);

            $supply = Supply::findOrFail($billSupply->supply_id);

            $bill->total_amount -= (($billSupply->quantity - $request->quantity) * $billSupply->price);

            $supply->quantity += ($billSupply->quantity - $request->quantity);

            $billSupply->quantity = $request->quantity;

            $supply->save();
            $billSupply->save();
            $bill->save();
            DB::commit();
            return response()->json(['message' => 'Cập nhập thành công', (($billSupply->quantity - $request->quantity) * $billSupply->price)]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Đã có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    public function deleteBillSupply($id)
    {
        DB::beginTransaction();
        try {
            $billSupply = BillSupply::findOrFail($id);

            $bill = Bill::findOrFail($billSupply->bill_id);
            $supply = Supply::findOrFail($billSupply->supply_id);

            $supply->quantity += $billSupply->quantity;

            $bill->total_amount -= ($billSupply->quantity * $billSupply->price);

            $supply->save();
            $bill->save();

            $billSupply->delete();

            DB::commit();

            return response()->json(['message' => 'Xóa thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Đã có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }
}
