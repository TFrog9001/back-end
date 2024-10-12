<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImportReceipt;

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

    public function show($id)
    {
        $importReceipts = ImportReceipt::findOrFail($id);

        return response()->json($importReceipts);
    }

    public function delete($id) {
        $receipt = ImportReceipt::findOrFail($id);
        $receipt->delete();
        return response()->json(['message' => 'Xóa phiếu nhập thành công'], 200);
    }
}
