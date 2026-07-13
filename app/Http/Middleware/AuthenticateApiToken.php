<?php
namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) return ApiResponse::error('UNAUTHENTICATED', 'Bearer token wajib diisi.', 401);
        try { $claims = app(JwtService::class)->decode($token); }
        catch (\Throwable) { return ApiResponse::error('UNAUTHENTICATED', 'Token tidak valid atau kedaluwarsa.', 401); }
        $session = DB::table('user_sessions')->where('id', $claims['sid'])->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        $user = $session ? User::query()->whereKey($claims['sub'])->where('is_active', true)->first() : null;
        if (!$user || (int)$session->user_id !== (int)$user->id) return ApiResponse::error('UNAUTHENTICATED', 'Sesi tidak aktif.', 401);
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_session_id', (int)$session->id);
        return $next($request);
    }
}
