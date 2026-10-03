<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('users',function(Blueprint $t){$t->string('first_name')->nullable();$t->string('middle_name')->nullable();$t->string('last_name')->nullable();});DB::table('users')->orderBy('id')->eachById(function($u){$p=preg_split('/\s+/',trim($u->name),3);DB::table('users')->where('id',$u->id)->update(['first_name'=>$p[0]??null,'middle_name'=>count($p)===3?($p[1]??null):null,'last_name'=>count($p)===3?($p[2]??null):($p[1]??null)]);});Schema::table('loans',function(Blueprint $t){$t->text('installation_address')->nullable();$t->date('installation_date')->nullable();$t->foreignId('installation_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();$t->timestamp('installation_confirmed_at')->nullable();});}
 public function down():void {Schema::table('loans',function(Blueprint $t){$t->dropForeign(['installation_confirmed_by']);$t->dropColumn(['installation_address','installation_date','installation_confirmed_by','installation_confirmed_at']);});Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['first_name','middle_name','last_name']));}
};
