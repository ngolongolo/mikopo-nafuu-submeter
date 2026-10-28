<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_leads', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('reference')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80);
            $table->string('phone', 30)->index();
            $table->string('email', 200)->nullable();
            $table->string('region');
            $table->string('district');
            $table->string('ward');
            $table->text('address');
            $table->string('utility_type', 20);
            $table->unsignedBigInteger('financing_amount');
            $table->json('terms');
            $table->timestamp('terms_accepted_at');
            $table->string('status', 30)->default('new')->index();
            $table->foreignId('supplier_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('application_id')->nullable()->constrained('applications')->restrictOnDelete();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_leads');
    }
};
