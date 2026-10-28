<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('supplier_profile_id')
                ->nullable()
                ->after('financier_id')
                ->constrained('supplier_profiles')
                ->restrictOnDelete();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('supplier_profile_id')
                ->nullable()
                ->after('financier_id')
                ->constrained('supplier_profiles')
                ->restrictOnDelete();
        });

        DB::table('applications')->orderBy('id')->eachById(function ($application) {
            $onboardedBy = DB::table('users')->where('id', $application->user_id)->value('onboarded_by');
            if (!$onboardedBy) {
                return;
            }

            $supplierId = DB::table('supplier_user')->where('user_id', $onboardedBy)->value('supplier_profile_id')
                ?? DB::table('supplier_profiles')->where('user_id', $onboardedBy)->value('id');

            if ($supplierId) {
                DB::table('applications')->where('id', $application->id)->update(['supplier_profile_id' => $supplierId]);
            }
        });

        DB::table('loans')->orderBy('id')->eachById(function ($loan) {
            $supplierId = DB::table('applications')->where('id', $loan->application_id)->value('supplier_profile_id');
            if ($supplierId) {
                DB::table('loans')->where('id', $loan->id)->update(['supplier_profile_id' => $supplierId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_profile_id');
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_profile_id');
        });
    }
};
