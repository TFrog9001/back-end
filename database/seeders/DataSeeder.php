<?php

namespace Database\Seeders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'role_name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'role_name' => 'Staff', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'role_name' => 'User', 'created_at' => now(), 'updated_at' => now()],
        ]);
        
        DB::table('users')->insert([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'), // Hãy đảm bảo rằng mật khẩu được mã hóa bằng Hash::make()
            'role_id' => 1, // Đặt role_id cho admin tại đây
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }
}
