<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\Supply;
use App\Models\BillSupply;

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
            $billSupplies = []; // Mảng lưu các bill supplies được thêm vào

            DB::transaction(function () use ($request, &$billSupplies) {
                $bill = Bill::findOrFail($request->input('bill_id'));

                $total_amount = 0;
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

                    // Thêm thông tin vào mảng billSupplies
                    $billSupplies[] = [
                        'supply_id' => $supply->id,
                        'quantity' => $itemData['quantity'],
                        'price' => $supply->price,
                        'bill_id' => $bill->id,
                        'id' => $existingSupply ? $existingSupply->id : $billSupply->id, // ID từ BillSupply
                        'name' => $supply->name,
                    ];
                }

                // Cập nhật tổng số tiền của hóa đơn
                $bill->total_amount += $total_amount;
                $bill->save();
            });

            return response()->json([
                'message' => 'Sản phẩm đã được thêm thành công!',
                'supplies' => $billSupplies,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    ///

    public  function getBillSupplies($id) {
        $supplies = BillSupply::with('supply')->where('bill_id', '=', $id)->get();
        return response()->json($supplies, 200);

    }
}
