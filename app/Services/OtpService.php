<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OtpService
{
    public function create(User $user, string $purpose): array
    {
        if (!$user->phone_e164) throw new RuntimeException('Nomor telepon akun belum tersedia.');
        $recent = DB::table('otp_challenges')->where('user_id', $user->id)->where('purpose', $purpose)
            ->where('last_sent_at', '>', now()->subSeconds((int)env('OTP_RESEND_COOLDOWN_SECONDS', 60)))->exists();
        if ($recent) throw new RuntimeException('Tunggu sebelum meminta OTP kembali.');
        $fixedCode = (string) env('OTP_FIXED_CODE', '');
        $code = ($fixedCode !== '' && !app()->environment('production'))
            ? $fixedCode
            : (app()->environment('testing') ? '123456' : (string)random_int(100000, 999999));
        $id = (string)Str::ulid();
        DB::table('otp_challenges')->insert(['id'=>$id,'user_id'=>$user->id,'purpose'=>$purpose,
            'destination'=>$user->phone_e164,'code_hash'=>$this->hash($code),'attempts'=>0,
            'max_attempts'=>(int)env('OTP_MAX_ATTEMPTS',5),'expires_at'=>now()->addSeconds((int)env('OTP_TTL_SECONDS',300)),
            'last_sent_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $provider = env('OTP_PROVIDER', 'log');
        if ($provider === 'log' && app()->environment('production') && !filter_var(env('OTP_LOG_ALLOW_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('OTP_PROVIDER=log dilarang di production tanpa OTP_LOG_ALLOW_PRODUCTION=true.');
        }
        if ($provider === 'log') Log::info('OTP development dibuat', ['challenge_id'=>$id,'destination_masked'=>$this->mask($user->phone_e164),'otp_code'=>app()->environment('production') ? null : $code]);
        else throw new RuntimeException('OTP provider produksi belum dikonfigurasi.');
        return ['challenge_id'=>$id,'masked_destination'=>$this->mask($user->phone_e164),'expires_in'=>(int)env('OTP_TTL_SECONDS',300)];
    }
    public function verify(string $id, string $code): User
    {
        return DB::transaction(function () use ($id,$code) {
            $c = DB::table('otp_challenges')->where('id',$id)->lockForUpdate()->first();
            if (!$c || $c->consumed_at || now()->greaterThan($c->expires_at) || $c->attempts >= $c->max_attempts) throw new RuntimeException('OTP tidak berlaku.');
            if (!hash_equals($c->code_hash,$this->hash($code))) {
                DB::table('otp_challenges')->where('id',$id)->increment('attempts');
                throw new RuntimeException('OTP salah.');
            }
            DB::table('otp_challenges')->where('id',$id)->update(['consumed_at'=>now(),'updated_at'=>now()]);
            return User::findOrFail($c->user_id);
        });
    }
    private function hash(string $code): string { return hash_hmac('sha256',$code,(string)config('app.key')); }
    private function mask(string $phone): string { return substr($phone,0,4).str_repeat('*',max(0,strlen($phone)-7)).substr($phone,-3); }
}
