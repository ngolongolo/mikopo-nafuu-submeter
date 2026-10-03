<?php
return ['sprint_sms'=>['url'=>env('SPRINT_SMS_URL','https://api.sprintsmsservice.com/api/SendSMS'),'api_id'=>env('SPRINT_SMS_API_ID'),'api_password'=>env('SPRINT_SMS_API_PASSWORD'),'sender_id'=>env('SPRINT_SMS_SENDER_ID','BLUETICK'),'timeout'=>(int)env('SPRINT_SMS_TIMEOUT',15)]];
