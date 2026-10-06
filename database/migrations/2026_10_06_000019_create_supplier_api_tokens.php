<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_amount')->nullable()->after('amount');
            $table->string('meter_number', 80)->nullable()->after('channel');
            $table->string('customer_phone', 30)->nullable()->after('meter_number');
            $table->string('receipt')->nullable()->after('customer_phone');
            $table->foreignId('supplier_api_token_id')->nullable()->after('recorded_by')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_api_token_id');
            $table->dropColumn(['purchase_amount', 'meter_number', 'customer_phone', 'receipt']);
        });
        Schema::dropIfExists('supplier_api_tokens');
    }
};
