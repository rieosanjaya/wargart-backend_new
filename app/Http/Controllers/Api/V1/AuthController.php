<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JwtService;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $r, OtpService $otp)
    {
        $v=$r->validate(['username'=>['required','string','min:3','max:80','regex:/^[A-Za-z0-9._-]+$/','unique:users,username'],
            'phone_e164'=>['required','regex:/^\+[1-9][0-9]{7,14}$/','unique:users,phone_e164'],'password'=>['required','string','min:6','max:128']]);
        $user=User::create(['username'=>$v['username'],'phone_e164'=>$v['phone_e164'],'password_hash'=>Hash::make($v['password']),'role'=>'CITIZEN','is_active'=>true]);
        try { $challenge=$otp->create($user,'REGISTER'); }
        catch (\Throwable $e) { $user->delete(); return ApiResponse::error('OTP_DELIVERY_FAILED',$e->getMessage(),503); }
        return ApiResponse::success($challenge,201);
    }
    public function login(Request $r, OtpService $otp)
    {
        $v=$r->validate(['identifier'=>'required|string|max:120','password'=>'required|string|max:128']);
        $user=User::query()->where('username',$v['identifier'])->orWhere('phone_e164',$v['identifier'])->first();
        if (!$user || !$user->is_active || !Hash::check($v['password'],$user->password_hash)) return ApiResponse::error('INVALID_CREDENTIALS','Username/nomor atau password salah.',401);
        try { return ApiResponse::success($otp->create($user,'LOGIN')); }
        catch (\Throwable $e) { return ApiResponse::error('OTP_DELIVERY_FAILED',$e->getMessage(),503); }
    }
    public function desktopLogin(Request $r, JwtService $jwt)
    {
        $v=$r->validate(['username'=>'required|string|max:120','password'=>'required|string|max:128']);
        $user=User::query()->where('username',$v['username'])->first();
        if (!$user || !$user->is_active || !Hash::check($v['password'],$user->password_hash)) {
            return ApiResponse::error('INVALID_CREDENTIALS','Username atau password salah.',401);
        }
        return $this->issueSession($user, $jwt, 'Desktop Legacy', 'DESKTOP');
    }
    public function desktopRegister(Request $r)
    {
        $v=$r->validate(['username'=>['required','string','min:3','max:80','regex:/^[A-Za-z0-9._-]+$/','unique:users,username'],'password'=>'required|string|min:6|max:128']);
        $user=User::create(['username'=>$v['username'],'password_hash'=>Hash::make($v['password']),'role'=>'CITIZEN','is_active'=>true]);
        return ApiResponse::success(['id'=>$user->id,'username'=>$user->username,'role'=>$user->role],201);
    }
    public function desktopUsernameCheck(Request $r)
    {
        $v=$r->validate(['username'=>'required|string|max:120']);
        $user=User::query()->where('username',$v['username'])->first();
        return ApiResponse::success(['exists'=>(bool)$user,'id'=>$user?->id]);
    }
    public function desktopLinkResident(Request $r)
    {
        $v=$r->validate(['username'=>'required|string|max:120','nik'=>'required|string|max:32']);
        $residentId=DB::table('residents')->where('nik',$v['nik'])->whereNull('deleted_at')->value('id');
        if(!$residentId)return ApiResponse::error('NOT_FOUND','Warga tidak ditemukan.',404);
        $updated=DB::table('users')->where('username',$v['username'])->update(['resident_id'=>$residentId,'updated_at'=>now()]);
        return $updated?ApiResponse::success(['linked'=>true]):ApiResponse::error('NOT_FOUND','User tidak ditemukan.',404);
    }
    public function desktopResidentCreate(Request $r)
    {
        $this->mergeLegacyResidentInput($r);
        $v=$r->validate(['nik'=>'required|string|max:32','family_card_number'=>'sometimes|nullable|string|max:32','full_name'=>'required|string|max:160','birth_place'=>'sometimes|nullable|string|max:120','birth_date'=>'sometimes|nullable|date','gender'=>'sometimes|nullable|in:MALE,FEMALE,Laki-laki,Perempuan','address'=>'required|string|max:2000','religion'=>'sometimes|nullable|string|max:40','marital_status'=>'sometimes|nullable|string|max:40','occupation'=>'sometimes|nullable|string|max:120','nationality'=>'sometimes|string|max:80','id_valid_until'=>'sometimes|nullable|string|max:40','family_relationship'=>'sometimes|nullable|string|max:60','legacy_photo_path'=>'sometimes|nullable|string|max:500','is_active'=>'sometimes|boolean']);
        if(isset($v['gender']))$v['gender']=$this->normalizeDesktopGender($v['gender']);
        if(array_key_exists('id_valid_until',$v))$v['id_valid_until']=$this->normalizeDesktopDate($v['id_valid_until']);
        if(array_key_exists('family_card_number',$v)){ $kk=trim((string)($v['family_card_number']??'')); unset($v['family_card_number']); $v['household_id']=$kk===''?null:$this->ensureDesktopHousehold($kk,$v['address']??'-'); }
        $existing=DB::table('residents')->where('nik',$v['nik'])->first();
        if($existing){if($existing->deleted_at===null)return ApiResponse::error('DUPLICATE_NIK','NIK sudah terdaftar.',409);DB::table('residents')->where('id',$existing->id)->update($v+['is_active'=>true,'deleted_at'=>null,'updated_at'=>now()]);return ApiResponse::success(DB::table('residents')->find($existing->id));}
        $id=DB::table('residents')->insertGetId($v+['is_active'=>$v['is_active']??true,'created_at'=>now(),'updated_at'=>now()]);
        return ApiResponse::success(DB::table('residents')->find($id),201);
    }
    public function desktopResidents(Request $r)
    {
        $q=DB::table('residents as x')->leftJoin('households as h','h.id','=','x.household_id')->whereNull('x.deleted_at')->select('x.*','h.family_card_number');
        if(($s=$r->input('search'))!==null&&$s!==''){$field=$r->input('search_field');$q->where(function($w)use($s,$field){match($field){'nik'=>$w->where('x.nik','like',"%$s%"),'nama','full_name'=>$w->where('x.full_name','like',"%$s%"),'no_kk','family_card_number'=>$w->where('h.family_card_number','like',"%$s%"),default=>$w->where('x.nik','like',"%$s%")->orWhere('x.full_name','like',"%$s%")->orWhere('h.family_card_number','like',"%$s%")};});}
        return ApiResponse::success($q->orderBy('x.full_name')->limit(min((int)$r->input('per_page',100),100))->get());
    }
    private function mergeLegacyResidentInput(Request $r):void{$map=['nama'=>'full_name','tanggal_lahir'=>'birth_date','jenis_kelamin'=>'gender','status_perkawinan'=>'marital_status','kewarganegaraan'=>'nationality','berlaku_hingga'=>'id_valid_until','status_hubungan'=>'family_relationship','foto'=>'legacy_photo_path','no_kk'=>'family_card_number'];foreach($map as $old=>$new){if($r->has($old)&&!$r->has($new))$r->merge([$new=>$r->input($old)]);}}
    private function normalizeDesktopGender(?string $value):?string{return match($value){'Laki-laki','MALE'=>'MALE','Perempuan','FEMALE'=>'FEMALE',default=>$value};}
    private function normalizeDesktopDate(?string $value):?string{$value=trim((string)$value);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)?$value:null;}
    private function ensureDesktopHousehold(string $kk,string $address):int{$existing=DB::table('households')->where('family_card_number',$kk)->value('id');if($existing)return (int)$existing;return DB::table('households')->insertGetId(['family_card_number'=>$kk,'address'=>$address?:'-','created_at'=>now(),'updated_at'=>now()]);}
    public function resend(Request $r, OtpService $otp)
    {
        $v=$r->validate(['challenge_id'=>'required|string|size:26']);
        $old=DB::table('otp_challenges')->where('id',$v['challenge_id'])->first();
        if(!$old) return ApiResponse::error('NOT_FOUND','Challenge tidak ditemukan.',404);
        try { return ApiResponse::success($otp->create(User::findOrFail($old->user_id),$old->purpose)); }
        catch(\Throwable $e){ return ApiResponse::error('OTP_RESEND_FAILED',$e->getMessage(),429); }
    }
    public function verify(Request $r, OtpService $otp, JwtService $jwt)
    {
        $v=$r->validate(['challenge_id'=>'required|string|size:26','otp'=>'required|digits:6','device_name'=>'nullable|string|max:120','device_type'=>['nullable',Rule::in(['ANDROID','DESKTOP','OTHER'])]]);
        try{$user=$otp->verify($v['challenge_id'],$v['otp']);}catch(\Throwable $e){return ApiResponse::error('INVALID_OTP',$e->getMessage(),422);}
        if(!$user->phone_verified_at)$user->forceFill(['phone_verified_at'=>now()])->save();
        return $this->issueSession($user, $jwt, $v['device_name']??null, $v['device_type']??'OTHER');
    }
    public function refresh(Request $r, JwtService $jwt)
    {
        $v=$r->validate(['refresh_token'=>'required|string|size:64']);
        return DB::transaction(function()use($v,$jwt){
            $s=DB::table('user_sessions')->where('refresh_token_hash',hash('sha256',$v['refresh_token']))->lockForUpdate()->first();
            if(!$s||$s->revoked_at||now()->greaterThan($s->expires_at))return ApiResponse::error('INVALID_REFRESH_TOKEN','Refresh token tidak berlaku.',401);
            $user=User::whereKey($s->user_id)->where('is_active',true)->first();
            if(!$user)return ApiResponse::error('UNAUTHENTICATED','Akun tidak aktif.',401);
            $new=bin2hex(random_bytes(32)); DB::table('user_sessions')->where('id',$s->id)->update(['refresh_token_hash'=>hash('sha256',$new),'last_used_at'=>now(),'updated_at'=>now()]);
            return ApiResponse::success(['access_token'=>$jwt->issue($user->id,$s->id,$user->role),'token_type'=>'Bearer','expires_in'=>(int)env('AUTH_ACCESS_TTL_MINUTES',15)*60,'refresh_token'=>$new]);
        });
    }
    public function me(Request $r){return ApiResponse::success($r->attributes->get('auth_user'));}
    public function logout(Request $r){DB::table('user_sessions')->where('id',$r->attributes->get('auth_session_id'))->update(['revoked_at'=>now(),'updated_at'=>now()]);return ApiResponse::success(['logged_out'=>true]);}
    public function logoutAll(Request $r){DB::table('user_sessions')->where('user_id',$r->attributes->get('auth_user')->id)->whereNull('revoked_at')->update(['revoked_at'=>now(),'updated_at'=>now()]);return ApiResponse::success(['logged_out'=>true]);}

    private function issueSession(User $user, JwtService $jwt, ?string $deviceName, string $deviceType)
    {
        return DB::transaction(function()use($user,$jwt,$deviceName,$deviceType){
            $refresh=bin2hex(random_bytes(32));
            $sid=DB::table('user_sessions')->insertGetId(['user_id'=>$user->id,'refresh_token_hash'=>hash('sha256',$refresh),
                'device_name'=>$deviceName,'device_type'=>$deviceType,'ip_address'=>request()->ip(),
                'user_agent'=>request()->userAgent(),'last_used_at'=>now(),'expires_at'=>now()->addDays((int)env('AUTH_REFRESH_TTL_DAYS',30)),
                'created_at'=>now(),'updated_at'=>now()]);
            $user->forceFill(['last_login_at'=>now()])->save();
            $payload=$user->toArray();
            $payload['nik']=$user->resident_id?DB::table('residents')->where('id',$user->resident_id)->value('nik'):null;
            return ApiResponse::success(['access_token'=>$jwt->issue($user->id,$sid,$user->role),'token_type'=>'Bearer',
                'expires_in'=>(int)env('AUTH_ACCESS_TTL_MINUTES',15)*60,'refresh_token'=>$refresh,'user'=>$payload]);
        });
    }
}
