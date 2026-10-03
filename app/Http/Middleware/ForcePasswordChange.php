<?php
namespace App\Http\Middleware;use Closure;use Illuminate\Http\Request;
class ForcePasswordChange {public function handle(Request $request,Closure $next){if($request->user()?->must_change_password&&!$request->is('password/change')&&!$request->is('logout'))return redirect('/password/change');return $next($request);}}
