<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Product;
class DatabaseSeeder extends Seeder {
 public function run():void {
  $common=['active'=>false];
  Product::firstOrCreate(['code'=>'SUBMETER'], $common+['name'=>'Submeter financing','category'=>'submeter','description'=>'Install a submeter with an upfront payment and three monthly installments.','value'=>100000,'down_payment'=>40000,'commission'=>5000,'commission_financed'=>false,'principal'=>60000,'finance_charge'=>15000,'installments'=>3]);
  Product::firstOrCreate(['code'=>'TOKEN'], $common+['name'=>'Electricity token financing','category'=>'token','description'=>'Finance electricity tokens and repay after one month.','value'=>5000,'down_payment'=>0,'commission'=>500,'commission_financed'=>true,'principal'=>5500,'finance_charge'=>1000,'installments'=>1]);
 }
}
