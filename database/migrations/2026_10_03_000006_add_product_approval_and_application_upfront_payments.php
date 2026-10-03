<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('products',function(Blueprint $t){$t->boolean('auto_approve')->default(false)->after('active');});Schema::create('application_upfront_payments',function(Blueprint $t){$t->id();$t->foreignId('application_id')->unique()->constrained('applications')->restrictOnDelete();$t->unsignedBigInteger('amount');$t->string('reference')->unique();$t->string('channel');$t->text('evidence');$t->timestamp('paid_at');$t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();$t->timestamps();});}
 public function down():void {Schema::dropIfExists('application_upfront_payments');Schema::table('products',function(Blueprint $t){$t->dropColumn('auto_approve');});}
};
