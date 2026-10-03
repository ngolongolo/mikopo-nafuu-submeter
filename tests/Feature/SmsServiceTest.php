<?php
namespace Tests\Feature;
use App\Services\SmsService;use Illuminate\Support\Facades\Http;use Tests\TestCase;
class SmsServiceTest extends TestCase {public function test_it_sends_an_sms_with_a_normalized_tanzanian_number():void{config(['services.sprint_sms.api_id'=>'test-id','services.sprint_sms.api_password'=>'test-password']);Http::fake(['api.sprintsmsservice.com/*'=>Http::response(['status'=>'success'])]);$result=app(SmsService::class)->send('0712 345 678','Loan approved');$this->assertTrue($result['successful']);Http::assertSent(fn($request)=>$request['phonenumber']==='255712345678'&&$request['textmessage']==='Loan approved'&&$request['sender_id']==='BLUETICK');}}
