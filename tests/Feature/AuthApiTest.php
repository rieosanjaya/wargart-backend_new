<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_verify_refresh_and_logout_flow(): void
    {
        $register=$this->postJson('/api/v1/auth/register',['username'=>'warga.test','phone_e164'=>'+6281234567890','password'=>'VeryStrongPassword!123']);
        $register->assertCreated()->assertJsonPath('data.masked_destination','+628*******890');
        $challenge=$register->json('data.challenge_id');

        $verify=$this->postJson('/api/v1/auth/otp/verify',['challenge_id'=>$challenge,'otp'=>'123456','device_type'=>'ANDROID']);
        $verify->assertOk()->assertJsonStructure(['data'=>['access_token','refresh_token','user']]);
        $token=$verify->json('data.access_token'); $refresh=$verify->json('data.refresh_token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.username','warga.test');
        $rotated=$this->postJson('/api/v1/auth/refresh',['refresh_token'=>$refresh]);
        $rotated->assertOk(); $this->assertNotSame($refresh,$rotated->json('data.refresh_token'));
        $this->postJson('/api/v1/auth/refresh',['refresh_token'=>$refresh])->assertUnauthorized();
        $this->withToken($rotated->json('data.access_token'))->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($rotated->json('data.access_token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_invalid_password_and_invalid_otp_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/register',['username'=>'citizen2','phone_e164'=>'+6281234567000','password'=>'VeryStrongPassword!123'])->assertCreated();
        $this->postJson('/api/v1/auth/login',['identifier'=>'citizen2','password'=>'wrong'])->assertUnauthorized();
        $login=$this->postJson('/api/v1/auth/login',['identifier'=>'citizen2','password'=>'VeryStrongPassword!123']);
        $login->assertOk();
        $this->postJson('/api/v1/auth/otp/verify',['challenge_id'=>$login->json('data.challenge_id'),'otp'=>'000000'])->assertStatus(422);
    }
}
