<?php
namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if ($request->attributes->get('auth_user')?->role !== strtoupper($role))
            return ApiResponse::error('FORBIDDEN', 'Anda tidak memiliki akses ke fitur ini.', 403);
        return $next($request);
    }
}
