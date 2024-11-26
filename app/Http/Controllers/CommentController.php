<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Booking;
use Illuminate\Http\Request;
use Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Http;

class CommentController extends Controller
{
    // Constructor để kiểm tra người dùng đã đăng nhập
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function reviews()
    {
        $comments = Comment::with('user')
            ->get();

        return response()->json($comments);
    }

    // Lấy danh sách comment của một booking
    public function index($bookingId)
    {
        $comments = Comment::with('user')
            ->where('booking_id', $bookingId)
            ->get();

        return response()->json($comments);
    }

    // Thêm mới bình luận
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rate' => 'required|integer|min:1|max:5',
            'content' => 'required|string|max:500',
        ]);

        $user = JWTAuth::user();

        $response = Http::post('http://127.0.0.1:5000/predict', [
            'sentence' => $validated['content']
        ]);

        if ($response->successful()) {
            $result = $response->json();
            $isToxic = ($result['prediction'] === 1);  // Nếu kết quả là toxic (1), thì là độc hại
        } else {
            // Nếu không thể gọi API hoặc có lỗi
            return response()->json(['error' => 'Không thể kiểm tra tính toxic của bình luận.'], 500);
        }

        $comment = Comment::create([
            'booking_id' => $validated['booking_id'],
            'user_id' => $user->id,
            'rate' => $validated['rate'],
            'content' => $validated['content'],
            'hidden' => $isToxic,
            'isEdit' => false,
        ]);

        return response()->json([
            'message' => 'Comment created successfully.',
            'comment' => $comment,
        ]);
    }

    // Cập nhật bình luận
    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);

        // Kiểm tra quyền cập nhật bình luận
        // $this->authorize('update', $comment);

        $validated = $request->validate([
            'rate' => 'integer|min:1|max:5',
            'content' => 'string|max:500',
        ]);

        // Nếu nội dung bình luận đã thay đổi, kiểm tra lại tính toxic
        if (isset($validated['content'])) {
            $response = Http::post('http://127.0.0.1:5000/predict', [
                'sentence' => $validated['content']
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $isToxic = ($result['prediction'] === 1);  // Nếu toxic thì là độc hại
                $comment->hidden = $isToxic;
            } else {
                // Nếu không thể gọi API hoặc có lỗi
                return response()->json(['error' => 'Không thể kiểm tra tính toxic của bình luận.'], 500);
            }
        }

        // Cập nhật bình luận
        $comment->update([
            'rate' => $validated['rate'] ?? $comment->rate,
            'content' => $validated['content'] ?? $comment->content,
            'isEdit' => true,
            'hidden' => $comment->hidden,
        ]);

        return response()->json([
            'message' => 'Comment updated successfully.',
            'comment' => $comment,
        ]);
    }

    // Xóa bình luận
    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);

        // Kiểm tra quyền xóa bình luận
        // $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }
}
