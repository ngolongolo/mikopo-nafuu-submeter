<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Models\{Product,Financier,LoanApplication,Loan,Payment,DisbursementBatch,User,Payout,SupplierProfile};
class AppServiceProvider extends ServiceProvider {
 public function boot(): void {
  RateLimiter::for('login', fn($r) => Limit::perMinute(5)->by(strtolower($r->input('email','')).'|'.$r->ip()));
 RateLimiter::for('register', fn($r) => Limit::perMinute(3)->by($r->ip()));
  foreach([Product::class,Financier::class,LoanApplication::class,Loan::class,Payment::class,DisbursementBatch::class,User::class,Payout::class,SupplierProfile::class] as $model)$model::creating(fn($record)=>$record->public_id??=(string)Str::ulid());
  foreach(['product'=>Product::class,'financier'=>Financier::class,'application'=>LoanApplication::class,'loan'=>Loan::class,'payment'=>Payment::class,'batch'=>DisbursementBatch::class,'supplier'=>SupplierProfile::class,'customer'=>User::class] as $parameter=>$model)Route::bind($parameter,fn($value)=>$model::where(ctype_digit((string)$value)?'id':'public_id',$value)->firstOrFail());
 }
}
