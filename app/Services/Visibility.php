<?php
namespace App\Services;
use App\Models\User;
class Visibility {
 public static function scope($query,User $user){return match($user->role){'admin','officer'=>$query,'financier'=>$query->where('financier_id',$user->financier_id ?? 0),'supplier'=>$query->whereHas('customer',fn($q)=>$q->where('onboarded_by',$user->id)),default=>$query->where('user_id',$user->id)};}
 public static function check($record,User $user):void {abort_unless(in_array($user->role,['admin','officer'])||($user->role==='financier' && $user->financier_id && $record->financier_id===$user->financier_id)||($user->role==='supplier' && $record->customer()->where('onboarded_by',$user->id)->exists())||($user->role==='customer' && $record->user_id===$user->id),403);}
}
