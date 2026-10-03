<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('users',function(Blueprint $t){$t->string('region')->nullable();$t->string('district')->nullable();$t->string('ward')->nullable();$t->text('address')->nullable();});}
 public function down():void {Schema::table('users',function(Blueprint $t){$t->dropColumn(['region','district','ward','address']);});}
};
