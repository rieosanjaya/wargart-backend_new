<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $role): string
    {
        $u=User::create(['username'=>strtolower($role).'.test','password_hash'=>Hash::make('x'),'role'=>$role,'is_active'=>true]);
        $sid=DB::table('user_sessions')->insertGetId(['user_id'=>$u->id,'refresh_token_hash'=>hash('sha256',random_bytes(32)),'device_type'=>'OTHER','expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
        return app(JwtService::class)->issue($u->id,$sid,$role);
    }

    public function test_admin_and_citizen_routes_enforce_roles(): void
    {
        $citizen=$this->token('CITIZEN');
        $this->withToken($citizen)->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $admin=$this->token('ADMIN');
        $this->withToken($admin)->getJson('/api/v1/me/profile')->assertForbidden();
        $this->withToken($admin)->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonStructure(['data'=>['residents','active_letters','unpaid_bills','arrears','waiting_chats']]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/news')->assertUnauthorized();
    }
}
