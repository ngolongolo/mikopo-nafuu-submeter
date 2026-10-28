<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            $table->string('first_name', 80)->nullable()->change();
            $table->string('last_name', 80)->nullable()->change();
            $table->string('region')->nullable()->change();
            $table->string('district')->nullable()->change();
            $table->string('ward')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('meter_number', 80)->nullable()->after('utility_type')->index();
            $table->boolean('meter_exists')->nullable()->after('meter_number');
            $table->boolean('meter_eligible')->nullable()->after('meter_exists');
            $table->text('meter_verification_note')->nullable()->after('meter_eligible');
            $table->foreignId('meter_verified_by')->nullable()->after('meter_verification_note')->constrained('users')->restrictOnDelete();
            $table->timestamp('meter_verified_at')->nullable()->after('meter_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            $table->dropForeign(['meter_verified_by']);
            $table->dropIndex(['meter_number']);
            $table->dropColumn([
                'meter_number',
                'meter_exists',
                'meter_eligible',
                'meter_verification_note',
                'meter_verified_by',
                'meter_verified_at',
            ]);
            $table->string('first_name', 80)->nullable(false)->change();
            $table->string('last_name', 80)->nullable(false)->change();
            $table->string('region')->nullable(false)->change();
            $table->string('district')->nullable(false)->change();
            $table->string('ward')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
        });
    }
};
