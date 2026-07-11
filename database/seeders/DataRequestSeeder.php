<?php

namespace Database\Seeders;

use App\Models\DataRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DataRequestSeeder extends Seeder
{
    /**
     * Seed a couple of demo compliance requests.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            return;
        }

        DataRequest::firstOrCreate(
            ['user_id' => $user->id, 'type' => 'export', 'status' => 'pending'],
            ['user_email' => $user->email],
        );

        DataRequest::firstOrCreate(
            ['user_id' => $user->id, 'type' => 'deletion', 'status' => 'pending'],
            ['user_email' => $user->email, 'note' => 'User requested account deletion via support.'],
        );
    }
}
