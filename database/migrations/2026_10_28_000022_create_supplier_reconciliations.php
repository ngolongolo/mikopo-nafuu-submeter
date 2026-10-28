<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('supplier_profile_id')->constrained()->restrictOnDelete();
            $table->string('transfer_reference')->unique();
            $table->unsignedBigInteger('total_amount');
            $table->date('transferred_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('supplier_reconciliation_id')
                ->nullable()
                ->after('supplier_api_token_id')
                ->constrained('supplier_reconciliations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_reconciliation_id');
        });
        Schema::dropIfExists('supplier_reconciliations');
    }
};
