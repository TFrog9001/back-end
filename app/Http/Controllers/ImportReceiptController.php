<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImportReceipt;
use App\Models\ImportReceiptDetail;
use App\Models\Supply;
use App\Models\Equipment;

use Log;

class ImportReceiptController extends Controller
{
    public function index(Request $request)
    {
        // $currentPage = $request->query('current_page', 1);
        // $pageSize = $request->query('pagesize', 10);

        // // Thực hiện phân trang
        // $importReceipts = ImportReceipt::paginate($pageSize, ['*'], 'page', $currentPage);
        $importReceipts = ImportReceipt::with('user', 'details')->orderBy('created_at', 'desc')->get();

        return response()->json($importReceipts);
    }


    public function store(Request $request)
    {
        Log::info($request->all());

        $validatedData = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'receiper_name' => 'required|string|max:50',
            'items' => 'required|array',
            'items.*.item_type' => 'required|in:supply,equipment',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.serial_number' => 'required|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.image' => 'nullable|file|mimes:jpg,png,jpeg,webp|max:2048',
        ]);

        \DB::beginTransaction();

        try {
            // Tạo phiếu nhập
            $importReceipt = ImportReceipt::create([
                'user_id' => $validatedData['user_id'],
                'receiper_name' => $validatedData['receiper_name'],
                'total_amount' => 0, // Sẽ tính toán sau
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalAmount = 0;

            foreach ($validatedData['items'] as $index => $item) {
                $existingItem = null;
                if ($item['item_type'] === 'supply') {
                    $existingItem = Supply::where('name', $item['item_name'])
                        ->where('serial_number', $item['serial_number'])
                        ->first();
                } elseif ($item['item_type'] === 'equipment') {
                    $existingItem = Equipment::where('name', $item['item_name'])
                        ->where('serial_number', $item['serial_number'])
                        ->first();
                }

                if ($existingItem) {
                    $existingItem->quantity += $item['quantity'];
                    $existingItem->save();

                    ImportReceiptDetail::create([
                        'receipt_id' => $importReceipt->id,
                        'item_type' => $item['item_type'],
                        'item_id' => $existingItem->id,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $totalAmount += $item['quantity'] * $item['price'];
                } else {
                    $newItemData = [
                        'name' => $item['item_name'],
                        'serial_number' => $item['serial_number'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Handle image upload if image is present in request
                    if ($request->hasFile("items.$index.image")) {
                        $image = $request->file("items.$index.image");
                        $imagePath = $image->store('images', 'public'); // Save image to 'storage/public/images'
                        $newItemData['image'] = $imagePath;
                    }

                    if ($item['item_type'] === 'supply') {
                        $newSupply = Supply::create($newItemData);
                        $itemId = $newSupply->id;
                    } elseif ($item['item_type'] === 'equipment') {
                        $newEquipment = Equipment::create($newItemData);
                        $itemId = $newEquipment->id;
                    }

                    ImportReceiptDetail::create([
                        'receipt_id' => $importReceipt->id,
                        'item_type' => $item['item_type'],
                        'item_id' => $itemId,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $totalAmount += $item['quantity'] * $item['price'];
                }
            }

            $importReceipt->update(['total_amount' => $totalAmount]);
            \DB::commit();

            return response()->json([
                'message' => 'Phiếu nhập đã được tạo thành công.',
                'receipt_id' => $importReceipt->id,
            ], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 'Có lỗi xảy ra khi tạo phiếu nhập.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function show($id)
    {
        // Tìm phiếu nhập theo ID
        $importReceipt = ImportReceipt::with('details', 'user')->findOrFail($id);

        // Trả về thông tin phiếu nhập cùng với chi tiết
        return response()->json([
            'id' => $importReceipt->id,
            'user' => $importReceipt->user,
            'receiper_name' => $importReceipt->receiper_name,
            'total_amount' => $importReceipt->total_amount,
            'created_at' => $importReceipt->created_at,
            'items' => $importReceipt->details->map(function ($detail) {
                // Lấy thông tin từ bảng equipment hoặc supply
                $item = null;
                if ($detail->item_type === 'equipment') {
                    $item = Equipment::find($detail->item_id);
                } elseif ($detail->item_type === 'supply') {
                    $item = Supply::find($detail->item_id);
                }

                return [
                    'item_type' => $detail->item_type,
                    'item_id' => $detail->item_id,
                    'quantity' => $detail->quantity,
                    'image' => $item->image,
                    'price' => $detail->price,
                    'serial_number' => $item ? $item->serial_number : null, // Lấy serial_number cho equipment
                    'item_name' => $item ? $item->name : null, // Lấy tên cho supply hoặc equipment
                ];
            }),
        ]);
    }

    public function update(Request $request, $id)
    {
        Log::info($request->all());
        // Xác thực dữ liệu đầu vào
        $validatedData = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'receiper_name' => 'required|string|max:50',
            'items' => 'required|array',
            'items.*.item_type' => 'required|in:supply,equipment',
            'items.*.item_name' => '|required|string|max:255',
            'items.*.serial_number' => 'required|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.item_id' => 'nullable|integer',
        ]);

        // Bắt đầu transaction
        \DB::beginTransaction();

        try {
            // Tìm phiếu nhập
            $importReceipt = ImportReceipt::findOrFail($id);

            // Cập nhật thông tin cơ bản của phiếu nhập
            $importReceipt->update([
                'user_id' => $validatedData['user_id'],
                'receiper_name' => $validatedData['receiper_name'],
                'updated_at' => now(),
            ]);

            // Lấy chi tiết phiếu nhập cũ, keyBy 'item_id' thay vì 'id'
            $oldDetails = $importReceipt->details()->get()->keyBy('item_id');

            // Kiểm tra log thông tin oldDetails
            Log::info('Old Details: ', $oldDetails->toArray());

            // Khởi tạo tổng tiền
            $totalAmount = 0;

            Log::info("Bắt đầu duyệt qua items");
            foreach ($validatedData['items'] as $item) {
                Log::info('Item ID: ', [$item['item_id']]);
                Log::info('Old Details Has Item: ', [$oldDetails->has($item['item_id'])]);

                if (isset($item['item_id']) && $oldDetails->has($item['item_id'])) {
                    // Item đã tồn tại, kiểm tra sự thay đổi
                    $oldDetail = $oldDetails->get($item['item_id']);
                    $quantityDiff = $item['quantity'] - $oldDetail->quantity;
                    $priceChanged = $item['price'] != $oldDetail->price;

                    Log::info("Cập nhật chi tiết phiếu nhập cho item ID: " . $item['item_id']);
                    Log::info("Sự thay đổi số lượng: " . $quantityDiff . ", Giá thay đổi: " . ($priceChanged ? 'Yes' : 'No'));

                    if ($quantityDiff != 0 || $priceChanged) {
                        // Cập nhật chi tiết phiếu nhập
                        $oldDetail->update([
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                        ]);

                        // Cập nhật số lượng trong bảng supplies hoặc equipments
                        if ($item['item_type'] === 'supply') {
                            $supply = Supply::findOrFail($oldDetail->item_id);
                            $supply->quantity += $quantityDiff;
                            $supply->save();
                        } elseif ($item['item_type'] === 'equipment') {
                            $equipment = Equipment::findOrFail($oldDetail->item_id);
                            $equipment->quantity += $quantityDiff;
                            $equipment->save();
                        }
                    }

                    // Loại bỏ item đã xử lý khỏi $oldDetails
                    $oldDetails->forget($item['item_id']);
                } else {
                    Log::info("Item không tồn tại, tạo mới item");

                    // Tạo mới item hoặc cập nhật số lượng nếu đã tồn tại
                    if ($item['item_type'] === 'supply') {
                        // Kiểm tra xem supply có tồn tại dựa trên serial_number hoặc các tiêu chí khác
                        $existingSupply = Supply::where('serial_number', $item['serial_number'])->first();

                        if ($existingSupply) {
                            // Nếu tồn tại, chỉ cập nhật số lượng
                            $existingSupply->quantity += $item['quantity'];
                            $existingSupply->save();
                            $newItem = $existingSupply;
                        } else {
                            // Nếu không tồn tại, tạo mới supply
                            $newItem = Supply::create([
                                'name' => $item['item_name'],
                                'serial_number' => $item['serial_number'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price'],
                            ]);
                        }
                    } else {
                        // Kiểm tra xem equipment có tồn tại dựa trên serial_number hoặc các tiêu chí khác
                        $existingEquipment = Equipment::where('serial_number', $item['serial_number'])->first();

                        if ($existingEquipment) {
                            // Nếu tồn tại, chỉ cập nhật số lượng
                            $existingEquipment->quantity += $item['quantity'];
                            $existingEquipment->save();
                            $newItem = $existingEquipment;
                        } else {
                            // Nếu không tồn tại, tạo mới equipment
                            $newItem = Equipment::create([
                                'name' => $item['item_name'],
                                'serial_number' => $item['serial_number'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price'],
                            ]);
                        }
                    }


                    // Thêm chi tiết phiếu nhập mới
                    ImportReceiptDetail::create([
                        'receipt_id' => $importReceipt->id,
                        'item_type' => $item['item_type'],
                        'item_id' => $newItem->id,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                }

                // Tính tổng tiền
                $totalAmount += $item['quantity'] * $item['price'];
            }

            // Xóa các chi tiết không còn trong danh sách cập nhật
            foreach ($oldDetails as $oldDetail) {
                Log::info("Xóa item: " . $oldDetail->item_id);

                if ($oldDetail->item_type === 'supply') {
                    $supply = Supply::findOrFail($oldDetail->item_id);
                    $supply->quantity -= $oldDetail->quantity;
                    $supply->save();
                } elseif ($oldDetail->item_type === 'equipment') {
                    $equipment = Equipment::findOrFail($oldDetail->item_id);
                    $equipment->quantity -= $oldDetail->quantity;
                    $equipment->save();
                }
                $oldDetail->delete();
            }

            // Cập nhật tổng tiền cho phiếu nhập
            $importReceipt->update(['total_amount' => $totalAmount]);

            // Commit transaction
            \DB::commit();

            return response()->json([
                'message' => 'Phiếu nhập đã được cập nhật thành công.',
                'receipt_id' => $importReceipt->id,
            ], 200);
        } catch (\Exception $e) {
            // Rollback transaction nếu có lỗi
            \DB::rollBack();

            return response()->json([
                'message' => 'Có lỗi xảy ra khi cập nhật phiếu nhập.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete($id)
    {
        // Bắt đầu transaction
        \DB::beginTransaction();

        try {
            // Tìm phiếu nhập
            $receipt = ImportReceipt::findOrFail($id);

            // Lấy chi tiết phiếu nhập
            $details = $receipt->details;

            foreach ($details as $detail) {
                if ($detail->item_type === 'supply') {
                    // Tìm supply trong bảng Supply
                    $supply = Supply::findOrFail($detail->item_id);

                    // Kiểm tra số lượng tồn kho hiện tại
                    if ($supply->quantity >= $detail->quantity) {
                        // Nếu đủ số lượng, trừ số lượng đúng theo detail
                        $supply->quantity -= $detail->quantity;
                    } else {
                        // Nếu không đủ, chỉ trừ số lượng tồn kho hiện tại
                        Log::warning("Không đủ số lượng để trừ cho sản phẩm ID: {$supply->id}. Trừ hết số lượng tồn kho hiện tại.");
                        $supply->quantity = 0;
                    }

                    $supply->save();
                } elseif ($detail->item_type === 'equipment') {
                    // Tìm equipment trong bảng Equipment
                    $equipment = Equipment::findOrFail($detail->item_id);

                    // Kiểm tra số lượng tồn kho hiện tại
                    if ($equipment->quantity >= $detail->quantity) {
                        // Nếu đủ số lượng, trừ số lượng đúng theo detail
                        $equipment->quantity -= $detail->quantity;
                    } else {
                        // Nếu không đủ, chỉ trừ số lượng tồn kho hiện tại
                        Log::warning("Không đủ số lượng để trừ cho thiết bị ID: {$equipment->id}. Trừ hết số lượng tồn kho hiện tại.");
                        $equipment->quantity = 0;
                    }

                    $equipment->save();
                }
            }

            // Xóa chi tiết phiếu nhập
            ImportReceiptDetail::where('receipt_id', $receipt->id)->delete();

            // Xóa phiếu nhập
            $receipt->delete();

            // Commit transaction
            \DB::commit();

            return response()->json(['message' => 'Xóa phiếu nhập thành công và cập nhật số lượng.'], 200);
        } catch (\Exception $e) {
            // Rollback transaction nếu có lỗi
            \DB::rollBack();

            return response()->json([
                'message' => 'Có lỗi xảy ra khi xóa phiếu nhập.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
