<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('field_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('field_price', 15, 2);
            $table->enum('status', ['Đã đặt', 'Đã cọc', 'Đã thanh toán', 'Hủy'])->default('Đã đặt');
            $table->string('payment_type')->default('direct');
            $table->decimal('deposit', 15, 2);
            $table->timestamps();

            $table->foreign('field_id')->references('id')->on('fields')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
