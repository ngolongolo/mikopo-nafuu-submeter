<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('users',function(Blueprint $t){$t->date('date_of_birth')->nullable();$t->string('gender',20)->nullable();$t->string('alternative_phone',30)->nullable();$t->string('region_district')->nullable();$t->string('ward_street')->nullable();$t->string('occupation')->nullable();$t->unsignedBigInteger('monthly_income')->nullable();$t->string('next_of_kin')->nullable();$t->string('next_of_kin_phone',30)->nullable();});
  Schema::table('applications',function(Blueprint $t){$t->string('utility_type',20)->nullable();$t->unsignedBigInteger('device_price')->nullable();$t->string('meter_brand_model')->nullable();$t->string('device_serial')->nullable();$t->date('installation_date')->nullable();$t->boolean('quotation_attached')->default(false);});
 }
 public function down():void {
  Schema::table('applications',function(Blueprint $t){$t->dropColumn(['utility_type','device_price','meter_brand_model','device_serial','installation_date','quotation_attached']);});
  Schema::table('users',function(Blueprint $t){$t->dropColumn(['date_of_birth','gender','alternative_phone','region_district','ward_street','occupation','monthly_income','next_of_kin','next_of_kin_phone']);});
 }
};
