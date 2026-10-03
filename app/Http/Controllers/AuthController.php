<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\LoginOtp;
use App\Mail\LoginOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
class AuthController {
 public function login(Request $r){$v=$r->validate(['email'=>'required|email','password'=>'required']);$user=User::where('email',strtolower($v['email']))->first();if(!$user||!Hash::check($v['password'],$user->password))throw ValidationException::withMessages(['email'=>'Email or password is incorrect.']);$r->session()->invalidate();$r->session()->regenerateToken();$r->session()->put('login_otp_user_id',$user->id);$this->sendOtp($user);return redirect('/login/otp');}
 public function otp(Request $r){if(!$r->session()->has('login_otp_user_id'))return redirect('/login');$user=User::find($r->session()->get('login_otp_user_id'));if(!$user)return redirect('/login');return view('otp',compact('user'));}
 public function verifyOtp(Request $r){$v=$r->validate(['code'=>'required|digits:6']);$user=User::find($r->session()->get('login_otp_user_id'));if(!$user)throw ValidationException::withMessages(['code'=>'Your login session expired. Please sign in again.']);$otp=LoginOtp::where('user_id',$user->id)->first();if(!$otp||$otp->expires_at->isPast()||$otp->attempts>=5){LoginOtp::where('user_id',$user->id)->delete();throw ValidationException::withMessages(['code'=>'This verification code has expired. Please sign in again.']);}if(!Hash::check($v['code'],$otp->code_hash)){$otp->increment('attempts');throw ValidationException::withMessages(['code'=>'The verification code is incorrect.']);}$otp->delete();$r->session()->forget('login_otp_user_id');Auth::login($user);$r->session()->regenerate();return redirect()->intended('/dashboard');}
 public function resendOtp(Request $r){$user=User::find($r->session()->get('login_otp_user_id'));if(!$user)return redirect('/login');$existing=LoginOtp::where('user_id',$user->id)->first();if($existing&&$existing->sent_at->gt(now()->subMinute()))throw ValidationException::withMessages(['code'=>'Please wait one minute before requesting another code.']);$this->sendOtp($user);return back()->with('success','A new verification code was sent.');}
 private function sendOtp(User $user):void{$code=(string)random_int(100000,999999);LoginOtp::updateOrCreate(['user_id'=>$user->id],['code_hash'=>Hash::make($code),'attempts'=>0,'expires_at'=>now()->addMinutes(10),'sent_at'=>now()]);Mail::to($user->email,$user->name)->send(new LoginOtpMail($user,$code));}
 public function register(Request $r){$v=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|max:200|unique:users','phone'=>'required|string|max:30','password'=>'required|string|min:12|confirmed']);$u=User::create($v+['role'=>'customer']);Auth::login($u);$r->session()->regenerate();return redirect('/dashboard');}
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/');}
}
