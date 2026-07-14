<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FeatureParityApiTest extends TestCase
{
    use RefreshDatabase;
    private function userToken(string $role,string $name,?int $resident=null):array{$u=User::create(['username'=>$name,'password_hash'=>Hash::make('test'),'role'=>$role,'resident_id'=>$resident,'is_active'=>true]);$sid=DB::table('user_sessions')->insertGetId(['user_id'=>$u->id,'refresh_token_hash'=>hash('sha256',random_bytes(32)),'device_type'=>'OTHER','expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);return [$u,app(JwtService::class)->issue($u->id,$sid,$role)];}
    public function test_admin_can_create_news_period_bill_and_citizen_can_read_services():void
    {
        $hh=DB::table('households')->insertGetId(['family_card_number'=>'3273010101010001','address'=>'Alamat','created_at'=>now(),'updated_at'=>now()]);
        $resident=DB::table('residents')->insertGetId(['nik'=>'3273010101010001','household_id'=>$hh,'full_name'=>'Budi','address'=>'Alamat','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        [$admin,$at]=$this->userToken('ADMIN','admin.test'); [$citizen,$ct]=$this->userToken('CITIZEN','citizen.test',$resident);
        $this->withToken($at)->postJson('/api/v1/admin/news',['title'=>'Pengumuman','content'=>'Isi berita','status'=>'PUBLISHED'])->assertCreated();
        $this->withToken($ct)->getJson('/api/v1/news')->assertOk()->assertJsonPath('data.0.title','Pengumuman');
        $period=$this->withToken($at)->postJson('/api/v1/admin/billing-periods',['period'=>'2026-07','rt_fee'=>50000,'waste_fee'=>25000]);$period->assertCreated();
        $this->withToken($at)->postJson('/api/v1/admin/billing-periods/'.$period->json('data.id').'/publish')->assertOk();
        $this->withToken($ct)->getJson('/api/v1/me/dues-bills')->assertOk()->assertJsonPath('data.0.total_amount',75000);
        $letter=$this->withToken($ct)->postJson('/api/v1/me/letter-requests',['letter_type'=>'Domisili','purpose'=>'Keperluan test']);$letter->assertCreated();
        $this->withToken($at)->postJson('/api/v1/admin/letter-requests/'.$letter->json('data.public_id').'/review')->assertOk()->assertJsonPath('data.status','IN_REVIEW');
    }
    public function test_chat_start_assign_message_and_close():void
    {
        [$admin,$at]=$this->userToken('ADMIN','chat.admin'); [$citizen,$ct]=$this->userToken('CITIZEN','chat.citizen');
        $start=$this->withToken($ct)->postJson('/api/v1/me/chat-sessions');$start->assertCreated();$id=$start->json('data.id');
        $this->withToken($at)->postJson("/api/v1/admin/chat-sessions/$id/assign")->assertOk()->assertJsonPath('data.status','ACTIVE');
        $this->withToken($ct)->postJson("/api/v1/chat-sessions/$id/messages",['body'=>'Halo'])->assertCreated();
        $this->withToken($at)->getJson("/api/v1/chat-sessions/$id/messages")->assertOk()->assertJsonPath('data.0.body','Halo');
        $this->withToken($at)->postJson("/api/v1/chat-sessions/$id/close")->assertOk();
    }

    public function test_citizen_photo_is_attached_when_linking_existing_resident():void
    {
        $hh=DB::table('households')->insertGetId(['family_card_number'=>'3273010101010001','address'=>'Alamat','created_at'=>now(),'updated_at'=>now()]);
        $resident=DB::table('residents')->insertGetId(['nik'=>'3273010101010001','household_id'=>$hh,'full_name'=>'Budi','address'=>'Alamat','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        [$citizen,$token]=$this->userToken('CITIZEN','photo.link.test');
        $media=DB::table('media_files')->insertGetId(['disk'=>'local','path'=>'uploads/photo-link-test.jpg','original_name'=>'photo.jpg','mime_type'=>'image/jpeg','size_bytes'=>100,'checksum_sha256'=>str_repeat('a',64),'uploaded_by'=>$citizen->id,'created_at'=>now(),'updated_at'=>now()]);

        $this->withToken($token)->postJson('/api/v1/me/resident-link',[
            'nik'=>'3273010101010001',
            'family_card_number'=>'3273010101010001',
            'photo_media_id'=>$media,
        ])->assertOk()->assertJsonPath('data.linked',true);

        $this->assertDatabaseHas('users',['id'=>$citizen->id,'resident_id'=>$resident]);
        $this->assertDatabaseHas('residents',['id'=>$resident,'photo_media_id'=>$media]);
    }
}
