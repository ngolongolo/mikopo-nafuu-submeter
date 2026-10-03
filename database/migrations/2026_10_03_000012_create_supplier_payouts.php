<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
return new class extends Migration {
 public function up():void {Schema::table('loans',function(Blueprint $t){$t->string('installation_status')->default('not_required')->index();$t->string('disbursement_status')->default('pending')->index();});Schema::create('payouts',function(Blueprint $t){$t->id();$t->string('public_id',26)->unique();$t->foreignId('loan_id')->unique()->constrained()->restrictOnDelete();$t->unsignedBigInteger('amount');$t->string('status')->default('pending')->index();$t->foreignId('disbursement_batch_id')->nullable()->constrained()->restrictOnDelete();$t->timestamp('paid_at')->nullable();$t->timestamps();});foreach(DB::table('loans')->get() as $loan){$terms=json_decode($loan->terms,true);$category=$terms['category']??null;$installed=!empty($loan->installation_confirmed_at);$paid=!empty($loan->disbursed_at);DB::table('loans')->where('id',$loan->id)->update(['status'=>in_array($loan->status,['paid','overdue'])?$loan->status:'active','installation_status'=>$category==='submeter'?($installed?'installed':'pending'):'not_required','disbursement_status'=>$paid?'paid':'pending']);DB::table('payouts')->insert(['public_id'=>(string)Str::ulid(),'loan_id'=>$loan->id,'amount'=>(int)($terms['principal']??0),'status'=>$paid?'paid':'pending','paid_at'=>$loan->disbursed_at,'created_at'=>now(),'updated_at'=>now()]);}}
 public function down():void {Schema::dropIfExists('payouts');Schema::table('loans',fn(Blueprint $t)=>$t->dropColumn(['installation_status','disbursement_status']));}
};
