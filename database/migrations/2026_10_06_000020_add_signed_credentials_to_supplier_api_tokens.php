<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_api_tokens', function (Blueprint $table) {
            $table->string('key_id', 80)->nullable()->unique()->after('name');
            $table->text('encrypted_secret')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_api_tokens', function (Blueprint $table) {
            $table->dropUnique(['key_id']);
            $table->dropColumn(['key_id', 'encrypted_secret']);
        });
    }
};
