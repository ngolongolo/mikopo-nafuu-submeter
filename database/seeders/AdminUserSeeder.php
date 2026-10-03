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
            ['name' => env('ADMIN_SEED_NAME', 'Nicholaus Ngolongolo'), 'password' => $password, 'role' => 'admin']
        );
    }
}
