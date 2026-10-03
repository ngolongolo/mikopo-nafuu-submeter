<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void {Schema::table('applications',fn(Blueprint $t)=>$t->string('id_type',40)->default('national_id')->after('phone'));}
 public function down():void {Schema::table('applications',fn(Blueprint $t)=>$t->dropColumn('id_type'));}
};
