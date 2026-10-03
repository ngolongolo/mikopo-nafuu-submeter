<?php
namespace App\Services;
use App\Models\{LoanApplication,Loan,Payment,Installment,Disbursement,Allocation,AuditLog,User,Payout};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class LendingService {
 private function require(bool $ok,string $message):void {if(!$ok)throw ValidationException::withMessages(['transaction'=>$message]);}
 private function audit(User $actor,string $action,$record,array $metadata=[]):void{AuditLog::create(['user_id'=>$actor->id,'action'=>$action,'entity'=>class_basename($record),'entity_id'=>$record->id,'metadata'=>$metadata]);}
 public function approve(LoanApplication $application,User $actor,bool $automatic=false):Loan {
  Visibility::check($application,$actor);if($automatic)$this->require($actor->role==='supplier'&&$application->product->auto_approve,'This product requires manual approval.');else $this->require(in_array($actor->role,['admin','financier']),'Approval permission required.');
  return DB::transaction(function() use($application,$actor,$automatic){$a=LoanApplication::lockForUpdate()->findOrFail($application->id);if($a->status==='approved'){$this->createSchedule($a->loan,CarbonImmutable::parse($a->loan->created_at)->startOfDay());$this->attachUpfrontPayment($a,$a->loan);Payout::firstOrCreate(['loan_id'=>$a->loan->id],['public_id'=>(string)Str::ulid(),'amount'=>$a->loan->terms['principal'],'status'=>'pending']);return $a->loan;}
   $this->require($a->status==='submitted','This application cannot be approved.');
   $this->require($a->financier->active,'Financier is inactive.');
   $this->require($actor->id!==$a->user_id,'You cannot approve your own application.');
   $submeter=($a->terms['category']??null)==='submeter';$loan=Loan::create(['reference'=>'MN-'.Str::upper(Str::random(10)),'application_id'=>$a->id,'user_id'=>$a->user_id,'financier_id'=>$a->financier_id,'terms'=>$a->terms,'status'=>'active','installation_status'=>$submeter?'pending':'not_required','disbursement_status'=>'pending']);Payout::create(['public_id'=>(string)Str::ulid(),'loan_id'=>$loan->id,'amount'=>$submeter?(int)$a->terms['principal']-(int)$a->terms['finance_charge']:$a->terms['principal'],'status'=>'pending','platform_commission_amount'=>$submeter?(int)$a->terms['finance_charge']:0,'platform_commission_status'=>$submeter?'pending':'not_applicable']);$this->createSchedule($loan,CarbonImmutable::parse($loan->created_at)->startOfDay());$this->attachUpfrontPayment($a,$loan);
   $a->update(['status'=>'approved','reviewed_by'=>$actor->id]);$this->audit($actor,$automatic?'application.auto_approved':'application.approved',$a);return $loan;});
 }
 public function confirmInstallation(Loan $loan,User $actor,array $data):void {
  Visibility::check($loan,$actor);$this->require($actor->role==='supplier','Only the responsible supplier can confirm installation.');
  DB::transaction(function()use($loan,$actor,$data){$l=Loan::lockForUpdate()->findOrFail($loan->id);$this->require($l->installation_status==='pending','Loan is not awaiting installation.');$this->require(($l->terms['category']??null)==='submeter','Installation applies only to submeter loans.');$l->update(['installation_status'=>'installed','device_serial'=>$data['device_serial'],'installation_address'=>$data['installation_address'],'installation_date'=>$data['installation_date'],'installation_confirmed_by'=>$actor->id,'installation_confirmed_at'=>now()]);$l->application()->update(['device_serial'=>$data['device_serial'],'installation_date'=>$data['installation_date'],'address'=>$data['installation_address']]);$this->audit($actor,'loan.installation_confirmed',$l);});
 }
 public function reject(LoanApplication $application,User $actor,string $reason):void {
  Visibility::check($application,$actor);$this->require(in_array($actor->role,['admin','financier']),'Approval permission required.');
  DB::transaction(function() use($application,$actor,$reason){$a=LoanApplication::lockForUpdate()->findOrFail($application->id);$this->require($a->status==='submitted','Only submitted applications may be rejected.');$this->require($actor->id!==$a->user_id,'You cannot review your own application.');$a->update(['status'=>'rejected','reviewed_by'=>$actor->id,'decision_reason'=>$reason]);$this->audit($actor,'application.rejected',$a);});
 }
 public function disburse(Loan $loan,User $actor,array $data):void {
  Visibility::check($loan,$actor);$this->require(in_array($actor->role,['admin','financier']),'Disbursement permission required.');
  DB::transaction(function() use($loan,$actor,$data){$l=Loan::lockForUpdate()->findOrFail($loan->id);
   if($l->disbursement){$this->require($l->disbursement->reference===$data['reference'],'Loan has already been disbursed.');return;}
   $this->require($l->disbursement_status==='pending','Loan payout has already been disbursed.');
   $t=$l->terms;$this->require((int)$data['amount']===$t['principal'],'Disbursement must equal approved principal.');
   $paid=(int)$l->payments()->where('purpose','upfront')->where('status','confirmed')->sum('amount');$this->require($paid===$t['upfront'],'Confirm the full upfront payment first.');
   if($t['category']==='submeter')$this->require(!empty($data['device_serial']),'Device serial is required.');
   $date=CarbonImmutable::parse($data['date'])->startOfDay();$this->require($date->lte(now()),'Disbursement date cannot be in the future.');
   Disbursement::create(['loan_id'=>$l->id,'disbursement_batch_id'=>$data['disbursement_batch_id']??null,'amount'=>$t['principal'],'reference'=>$data['reference'],'recipient'=>$data['recipient'],'confirmed_by'=>$actor->id,'confirmed_at'=>$date]);$this->createSchedule($l,CarbonImmutable::parse($l->created_at)->startOfDay());
   $l->update(['disbursement_status'=>'paid','disbursed_at'=>$date,'device_serial'=>$data['device_serial']??$l->device_serial]);$l->payout()->update(['status'=>'paid','disbursement_batch_id'=>$data['disbursement_batch_id']??null,'paid_at'=>$date]);$this->refresh($l);$this->audit($actor,'loan.disbursed',$l);
  });
 }
 private function createSchedule(Loan $loan,CarbonImmutable $start):void {$t=$loan->terms;$n=(int)$t['installments'];for($i=1;$i<=$n;$i++)Installment::firstOrCreate(['loan_id'=>$loan->id,'number'=>$i],['due_date'=>$start->addMonthsNoOverflow($i)->toDateString(),'principal'=>intdiv((int)$t['principal'],$n)+($i===$n?(int)$t['principal']%$n:0),'charge'=>intdiv((int)$t['finance_charge'],$n)+($i===$n?(int)$t['finance_charge']%$n:0)]);}
 private function attachUpfrontPayment(LoanApplication $application,Loan $loan):void {$upfront=$application->upfrontPayment;if(!$upfront)return;$payment=Payment::firstOrCreate(['reference'=>$upfront->reference],['loan_id'=>$loan->id,'purpose'=>'upfront','amount'=>$upfront->amount,'channel'=>$upfront->channel,'status'=>'pending','evidence'=>$upfront->evidence,'recorded_by'=>$upfront->recorded_by,'paid_at'=>$upfront->paid_at]);if($payment->status!=='pending'||$payment->channel==='cash')return;$received=(int)$loan->payments()->whereKeyNot($payment->id)->where('purpose','upfront')->where('status','confirmed')->sum('amount');$down=min($payment->amount,max(0,(int)$loan->terms['down_payment']-$received));if($down)$this->allocate($payment,null,'down_payment',$down);if($payment->amount>$down)$this->allocate($payment,null,'platform_commission',$payment->amount-$down);$payment->update(['status'=>'confirmed','confirmed_by'=>$upfront->recorded_by,'confirmed_at'=>now()]);$this->audit(User::findOrFail($upfront->recorded_by),'payment.auto_verified',$payment);}
 public function confirm(Payment $payment,User $actor):void {
  if($actor->role==='supplier'){Visibility::check($payment->loan,$actor);$this->require($payment->recorded_by===$actor->id&&$payment->purpose==='repayment','Suppliers may post only repayments they collected.');}else $this->require($actor->role==='admin','Only administrators confirm payments.');
  DB::transaction(function() use($payment,$actor){$l=Loan::lockForUpdate()->findOrFail($payment->loan_id);$p=Payment::lockForUpdate()->findOrFail($payment->id);if($p->status==='confirmed')return;$this->require($p->status==='pending','Payment is not pending.');
   if($p->purpose==='upfront'){
    $this->require($l->disbursement_status==='pending','Upfront payment must precede disbursement.');
    $received=(int)$l->payments()->where('purpose','upfront')->where('status','confirmed')->sum('amount');$t=$l->terms;$this->require($received+$p->amount<=$t['upfront'],'Upfront amount exceeds the amount due.');
    $down=min($p->amount,max(0,$t['down_payment']-$received));
    if($down)$this->allocate($p,null,'down_payment',$down);if($p->amount>$down)$this->allocate($p,null,'platform_commission',$p->amount-$down);
   } else {
    $this->require(in_array($l->status,['active','overdue']),'Loan must be active for repayment.');$this->require($p->amount<=$l->outstanding(),'Payment exceeds outstanding debt.');
    $remaining=$p->amount;foreach($l->installments()->lockForUpdate()->get() as $s){if(!$remaining)break;$rp=$s->principal-$s->paid_principal;$rc=$s->charge-$s->paid_charge;$total=$rp+$rc;if(!$total)continue;$take=min($remaining,$total);$principal=$take===$total?$rp:intdiv($take*$rp,$total);$charge=$take-$principal;
     if($principal)$this->allocate($p,$s,'principal',$principal);if($charge)$this->allocate($p,$s,'finance_charge',$charge);$s->increment('paid_principal',$principal);$s->increment('paid_charge',$charge);$remaining-=$take;
    }
   }
   $p->update(['status'=>'confirmed','confirmed_by'=>$actor->id,'confirmed_at'=>now()]);$this->refresh($l);$this->audit($actor,'payment.confirmed',$p);
  });
 }
 private function allocate(Payment $p,?Installment $s,string $component,int $amount):void{Allocation::create(['payment_id'=>$p->id,'installment_id'=>$s?->id,'component'=>$component,'amount'=>$amount]);}
 public function reverse(Payment $payment,User $actor,string $reason):void {
  $this->require($actor->role==='admin','Only administrators reverse payments.');
  DB::transaction(function() use($payment,$actor,$reason){$l=Loan::lockForUpdate()->findOrFail($payment->loan_id);$p=Payment::lockForUpdate()->findOrFail($payment->id);$this->require($p->status==='confirmed','Only confirmed payments may be reversed.');$this->require($p->confirmed_by!==$actor->id,'A different administrator must approve the reversal.');$this->require(!($p->purpose==='upfront' && $l->disbursed_at),'Upfront cannot be reversed after disbursement; handle a refund separately.');
   foreach($p->allocations()->whereNull('reversal_of')->get() as $a){Allocation::create(['payment_id'=>$p->id,'installment_id'=>$a->installment_id,'component'=>$a->component,'amount'=>-$a->amount,'reversal_of'=>$a->id]);if($a->installment_id){$s=Installment::lockForUpdate()->findOrFail($a->installment_id);$s->decrement($a->component==='principal'?'paid_principal':'paid_charge',$a->amount);}}
   $p->update(['status'=>'reversed','reversal_reason'=>$reason]);$this->refresh($l);$this->audit($actor,'payment.reversed',$p,['reason'=>$reason]);
  });
 }
 public function refresh(Loan $l):void {
  $overdue=false;$balance=0;
  foreach($l->installments()->get() as $s){$b=$s->outstanding();$balance+=$b;$status=$b===0?'paid':($s->due_date->lt(today())?'overdue':($s->paid_principal+$s->paid_charge>0?'partial':'pending'));$s->update(['status'=>$status]);if($status==='overdue')$overdue=true;}
  $l->update(['status'=>$balance===0?'paid':($overdue?'overdue':'active')]);
 }
}
