<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL', 'nicholausngolongolo@gmail.com');
        $password = env('ADMIN_SEED_PASSWORD');

        if (!$password) {
            throw new RuntimeException('ADMIN_SEED_PASSWORD must be configured before running AdminUserSeeder.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_SEED_NAME', 'Nicholaus Ngolongolo'),
                'id_type' => env('ADMIN_SEED_ID_TYPE', 'national_id'),
                'id_number' => env('ADMIN_SEED_ID_NUMBER', 'ADMIN-SEED-001'),
                'password' => $password,
                'role' => 'admin',
            ]
        );
    }
}
