<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;


class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 管理者
        User::updateOrCreate(
            ['email' => 'admin@a.com'],
                [
                    'name' => '管理者',
                    'password' => Hash::make('12345678'),
                    'role' => 'admin',
                    'email_verified_at' => now(),
                ]
        );

        // 一般ユーザー
        for ($i = 1; $i <= 5; $i++) {
            User::updateOrCreate(
                ['email' => "user{$i}@a.com"],
                    [
                        'name' => "一般ユーザー{$i}",
                        'password' => Hash::make('12345678'),
                        'email_verified_at'=>'2025-11-20 19:32:06',
                        'role' => 'user',
                    ]
            );
        }
    }
}
