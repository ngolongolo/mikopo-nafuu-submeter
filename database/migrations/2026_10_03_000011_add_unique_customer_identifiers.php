<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void {
  Schema::table('users',function(Blueprint $t){$t->string('email')->nullable()->change();$t->string('id_type',40)->nullable();$t->string('id_number',80)->nullable();});
  DB::table('applications')->orderBy('id')->get()->unique('user_id')->each(fn($a)=>DB::table('users')->where('id',$a->user_id)->update(['id_type'=>$a->id_type??'national_id','id_number'=>$a->id_number]));
  Schema::table('users',function(Blueprint $t){$t->unique('phone');$t->unique('id_number');});
 }
 public function down():void {Schema::table('users',function(Blueprint $t){$t->dropUnique(['phone']);$t->dropUnique(['id_number']);$t->dropColumn(['id_type','id_number']);$t->string('email')->nullable(false)->change();});}
};
