<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Carbon\CarbonImmutable;
return new class extends Migration {
 public function up():void {
  Schema::create('disbursement_batches',function(Blueprint $t){$t->id();$t->string('reference')->unique();$t->date('batch_date')->index();$t->string('status')->default('unpaid')->index();$t->unsignedBigInteger('total_amount');$t->string('payment_reference')->nullable()->unique();$t->string('institution')->nullable();$t->string('account_name')->nullable();$t->string('account_number')->nullable();$t->timestamp('paid_at')->nullable();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();$t->timestamps();});
  Schema::create('disbursement_batch_items',function(Blueprint $t){$t->id();$t->foreignId('disbursement_batch_id')->constrained()->restrictOnDelete();$t->foreignId('loan_id')->unique()->constrained()->restrictOnDelete();$t->unsignedBigInteger('amount');$t->string('device_serial');$t->timestamps();});
  Schema::table('disbursements',function(Blueprint $t){$t->foreignId('disbursement_batch_id')->nullable()->constrained()->restrictOnDelete();});
  foreach(DB::table('loans')->orderBy('id')->get() as $loan){if(DB::table('installments')->where('loan_id',$loan->id)->exists())continue;$terms=json_decode($loan->terms,true);if(!$terms)continue;$start=CarbonImmutable::parse($loan->created_at)->startOfDay();$n=(int)$terms['installments'];for($i=1;$i<=$n;$i++)DB::table('installments')->insert(['loan_id'=>$loan->id,'number'=>$i,'due_date'=>$start->addMonthsNoOverflow($i)->toDateString(),'principal'=>intdiv((int)$terms['principal'],$n)+($i===$n?(int)$terms['principal']%$n:0),'charge'=>intdiv((int)$terms['finance_charge'],$n)+($i===$n?(int)$terms['finance_charge']%$n:0),'paid_principal'=>0,'paid_charge'=>0,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);}
 }
 public function down():void {Schema::table('disbursements',function(Blueprint $t){$t->dropConstrainedForeignId('disbursement_batch_id');});Schema::dropIfExists('disbursement_batch_items');Schema::dropIfExists('disbursement_batches');}
};
