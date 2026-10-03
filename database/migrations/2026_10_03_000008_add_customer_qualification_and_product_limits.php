<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
 public function up():void {
  Schema::table('users',function(Blueprint $t){$t->string('public_id',26)->nullable()->unique()->after('id');$t->unsignedBigInteger('submeter_qualifying_amount')->default(100000);$t->unsignedBigInteger('token_qualifying_amount')->default(5000);});
  DB::table('users')->whereNull('public_id')->orderBy('id')->eachById(fn($u)=>DB::table('users')->where('id',$u->id)->update(['public_id'=>(string)Str::ulid()]));
  Schema::table('products',fn(Blueprint $t)=>$t->unsignedInteger('max_loans_per_customer')->default(1));
 }
 public function down():void {Schema::table('products',fn(Blueprint $t)=>$t->dropColumn('max_loans_per_customer'));Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['public_id','submeter_qualifying_amount','token_qualifying_amount']));}
};
