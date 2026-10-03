<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('users',function(Blueprint $t){$t->foreignId('onboarded_by')->nullable()->after('financier_id')->constrained('users')->nullOnDelete();});}
 public function down():void {Schema::table('users',function(Blueprint $t){$t->dropConstrainedForeignId('onboarded_by');});}
};
