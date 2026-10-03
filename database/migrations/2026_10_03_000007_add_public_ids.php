<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Support\Str;
return new class extends Migration {
 private array $tables=['products','financiers','applications','loans','payments','disbursement_batches'];
 public function up():void {foreach($this->tables as $table){Schema::table($table,function(Blueprint $t){$t->string('public_id',26)->nullable()->unique();});foreach(DB::table($table)->whereNull('public_id')->pluck('id') as $id)DB::table($table)->where('id',$id)->update(['public_id'=>(string)Str::ulid()]);}}
 public function down():void {foreach(array_reverse($this->tables) as $table)Schema::table($table,function(Blueprint $t){$t->dropColumn('public_id');});}
};
