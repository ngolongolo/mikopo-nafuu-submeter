<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            $table->string('request_type', 30)->nullable()->after('product_id')->index();
            $table->string('phone_normalized', 12)->nullable()->after('phone')->index();
            $table->unsignedTinyInteger('meter_quantity')->default(1)->after('utility_type');
            $table->string('confirmation_code_hash')->nullable()->after('status');
            $table->unsignedTinyInteger('confirmation_attempts')->default(0)->after('confirmation_code_hash');
            $table->timestamp('confirmation_sent_at')->nullable()->after('confirmation_attempts');
            $table->timestamp('confirmation_expires_at')->nullable()->after('confirmation_sent_at');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_expires_at');
            $table->index(['phone_normalized', 'request_type'], 'customer_leads_phone_type_index');
        });

        DB::table('customer_leads')
            ->join('products', 'products.id', '=', 'customer_leads.product_id')
            ->select('customer_leads.id', 'customer_leads.phone', 'customer_leads.created_at', 'products.category')
            ->orderBy('customer_leads.id')
            ->each(function ($lead): void {
                $digits = preg_replace('/\D+/', '', $lead->phone);
                if (str_starts_with($digits, '0')) {
                    $digits = '255'.substr($digits, 1);
                } elseif (strlen($digits) === 9) {
                    $digits = '255'.$digits;
                }

                DB::table('customer_leads')->where('id', $lead->id)->update([
                    'request_type' => $lead->category,
                    'phone_normalized' => preg_match('/^255\d{9}$/', $digits) ? $digits : null,
                    'confirmed_at' => $lead->created_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            $table->dropIndex('customer_leads_phone_type_index');
            $table->dropIndex(['request_type']);
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn([
                'request_type',
                'phone_normalized',
                'meter_quantity',
                'confirmation_code_hash',
                'confirmation_attempts',
                'confirmation_sent_at',
                'confirmation_expires_at',
                'confirmed_at',
            ]);
        });
    }
};
