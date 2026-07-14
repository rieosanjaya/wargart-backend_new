<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitizenController extends Controller
{
    private function user(Request $r){return $r->attributes->get('auth_user');}
    private function resident(Request $r){$u=$this->user($r);return $u->resident_id?DB::table('residents')->where('id',$u->resident_id)->whereNull('deleted_at')->first():null;}
    public function profile(Request $r){
        $resident=$this->resident($r);$household=null;$members=[];
        if($resident&&$resident->household_id){$household=DB::table('households')->where('id',$resident->household_id)->first();$resident->family_card_number=$household->family_card_number??null;$members=DB::table('residents')->where('household_id',$resident->household_id)->whereNull('deleted_at')->where('is_active',true)->get();}
        return ApiResponse::success(['user'=>$this->user($r),'resident'=>$resident,'household'=>$household,'members'=>$members]);
    }
    public function updateProfile(Request $r){
        $resident=$this->resident($r); if(!$resident)return ApiResponse::error('PROFILE_NOT_LINKED','Akun belum ditautkan ke warga.',409);
        $v=$r->validate(['full_name'=>'sometimes|string|max:160','birth_place'=>'sometimes|nullable|string|max:120','birth_date'=>'sometimes|nullable|date',
            'gender'=>'sometimes|nullable|in:MALE,FEMALE','address'=>'sometimes|string|max:2000','religion'=>'sometimes|nullable|string|max:40',
            'marital_status'=>'sometimes|nullable|string|max:40','occupation'=>'sometimes|nullable|string|max:120']);
        DB::table('residents')->where('id',$resident->id)->update($v+['updated_at'=>now()]); return $this->profile($r);
    }
    public function residentProfileCreate(Request $r){
        $u=$this->user($r); if($u->resident_id)return ApiResponse::error('ALREADY_LINKED','Akun sudah ditautkan.',409);
        $v=$r->validate([
            'nik'=>'required|digits:16|unique:residents,nik',
            'family_card_number'=>'nullable|digits:16',
            'full_name'=>'required|string|max:160',
            'birth_place'=>'nullable|string|max:120',
            'birth_date'=>'nullable|date',
            'gender'=>'nullable|in:MALE,FEMALE',
            'address'=>'required|string|max:2000',
            'religion'=>'nullable|string|max:40',
            'marital_status'=>'nullable|string|max:40',
            'occupation'=>'nullable|string|max:120',
            'nationality'=>'nullable|string|max:80',
            'family_relationship'=>'nullable|string|max:60',
            'photo_media_id'=>'nullable|exists:media_files,id'
        ]);
        return DB::transaction(function()use($u,$v){
            $householdId=null;
            if(!empty($v['family_card_number'])){
                $householdId=DB::table('households')->where('family_card_number',$v['family_card_number'])->value('id');
                if(!$householdId)$householdId=DB::table('households')->insertGetId(['family_card_number'=>$v['family_card_number'],'address'=>$v['address'],'created_at'=>now(),'updated_at'=>now()]);
            }
            unset($v['family_card_number']);
            $id=DB::table('residents')->insertGetId($v+['household_id'=>$householdId,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('users')->where('id',$u->id)->update(['resident_id'=>$id,'updated_at'=>now()]);
            return ApiResponse::success(['resident'=>DB::table('residents')->where('id',$id)->first()],201);
        });
    }
    public function linkResident(Request $r){
        $u=$this->user($r);
        $v=$r->validate(['nik'=>'required|digits:16','family_card_number'=>'required|digits:16','photo_media_id'=>'sometimes|nullable|exists:media_files,id']);
        $resident=DB::table('residents as r')->join('households as h','h.id','=','r.household_id')->where('r.nik',$v['nik'])->where('h.family_card_number',$v['family_card_number'])->select('r.id')->first();
        if(!$resident)return ApiResponse::error('LINK_NOT_FOUND','Data warga dan keluarga tidak cocok.',404);
        if($u->resident_id && (int)$u->resident_id !== (int)$resident->id)return ApiResponse::error('ALREADY_LINKED','Akun sudah ditautkan ke data warga lain.',409);
        $linkedUser=DB::table('users')->where('resident_id',$resident->id)->first(['id','username']);
        if($linkedUser && (int)$linkedUser->id !== (int)$u->id)return ApiResponse::error('RESIDENT_ALREADY_LINKED','Warga sudah ditautkan ke akun lain.',409);
        if(!empty($v['photo_media_id'])&&!DB::table('media_files')->where('id',$v['photo_media_id'])->where('uploaded_by',$u->id)->exists())return ApiResponse::error('INVALID_PHOTO','Foto tidak diunggah oleh akun ini.',422);
        DB::transaction(function()use($u,$resident,$v){
            if(!$u->resident_id)DB::table('users')->where('id',$u->id)->update(['resident_id'=>$resident->id,'updated_at'=>now()]);
            if(!empty($v['photo_media_id']))DB::table('residents')->where('id',$resident->id)->update(['photo_media_id'=>$v['photo_media_id'],'updated_at'=>now()]);
        });
        return ApiResponse::success(['linked'=>true,'resident_id'=>$resident->id]);
    }
    public function household(Request $r){
        $resident=$this->resident($r); if(!$resident)return ApiResponse::error('PROFILE_NOT_LINKED','Akun belum ditautkan.',409);
        $household=$resident->household_id?DB::table('households')->where('id',$resident->household_id)->first():null;
        $members=$household?DB::table('residents')->where('household_id',$household->id)->whereNull('deleted_at')->where('is_active',true)->get():[];
        return ApiResponse::success(['household'=>$household,'members'=>$members]);
    }
    public function mediaUpload(Request $r){
        $v=$r->validate(['file'=>'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf']);
        $f=$v['file'];$path=$f->store('uploads');
        $id=DB::table('media_files')->insertGetId(['disk'=>config('filesystems.default'),'path'=>$path,'original_name'=>$f->getClientOriginalName(),'mime_type'=>$f->getMimeType(),'size_bytes'=>$f->getSize(),'checksum_sha256'=>hash_file('sha256',$f->getRealPath()),'uploaded_by'=>$this->user($r)->id,'created_at'=>now(),'updated_at'=>now()]);
        return ApiResponse::success(DB::table('media_files')->find($id),201);
    }
    public function searchResident(Request $r){
        $v=$r->validate(['nik'=>'required|digits:16']); $x=DB::table('residents')->where('nik',$v['nik'])->whereNull('deleted_at')->where('is_active',true)
            ->select('nik','full_name','gender','address','occupation','family_relationship')->first();
        return $x?ApiResponse::success($x):ApiResponse::error('NOT_FOUND','Warga tidak ditemukan.',404);
    }
    public function news(Request $r){$q=DB::table('news')->where('status','PUBLISHED')->whereNull('deleted_at')->orderByDesc('published_at')->paginate(min((int)$r->input('per_page',20),100));$items=collect($q->items())->map(fn($n)=>$this->withNewsImageUrl($r,$n))->all();return ApiResponse::success($items,200,['current_page'=>$q->currentPage(),'last_page'=>$q->lastPage(),'total'=>$q->total()]);}
    public function newsShow(Request $r,int $id){$x=DB::table('news')->where('id',$id)->where('status','PUBLISHED')->whereNull('deleted_at')->first();return $x?ApiResponse::success($this->withNewsImageUrl($r,$x)):ApiResponse::error('NOT_FOUND','Berita tidak ditemukan.',404);}
    private function withNewsImageUrl(Request $r,object $n):object{if(!empty($n->image_media_id)){$n->image_url=$r->getSchemeAndHttpHost().'/api/v1/public/media/'.$n->image_media_id;}else{$n->image_url=null;}return $n;}
    public function letters(Request $r){$x=$this->resident($r);if(!$x)return ApiResponse::error('PROFILE_NOT_LINKED','Akun belum ditautkan.',409);$rows=DB::table('letter_requests')->where('resident_id',$x->id)->orderByDesc('submitted_at')->get()->map(fn($l)=>$this->withLetterFileUrl($r,$l));return ApiResponse::success($rows);}
    public function letterCreate(Request $r){$x=$this->resident($r);if(!$x)return ApiResponse::error('PROFILE_NOT_LINKED','Akun belum ditautkan.',409);$v=$r->validate(['letter_type'=>'required|string|max:120','purpose'=>'required|string|max:3000']);$id=(string)Str::ulid();DB::table('letter_requests')->insert(['public_id'=>$id,'resident_id'=>$x->id]+$v+['status'=>'SUBMITTED','submitted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);return ApiResponse::success(DB::table('letter_requests')->where('public_id',$id)->first(),201);}
    public function letterShow(Request $r,string $id){$x=$this->resident($r);$row=$x?DB::table('letter_requests')->where('public_id',$id)->where('resident_id',$x->id)->first():null;return $row?ApiResponse::success($this->withLetterFileUrl($r,$row)):ApiResponse::error('NOT_FOUND','Pengajuan tidak ditemukan.',404);}
    private function withLetterFileUrl(Request $r,object $l):object{if(!empty($l->result_media_id)&&!empty($l->public_id)){$l->file_url=$r->getSchemeAndHttpHost().'/api/v1/public/letters/'.$l->public_id.'/file';}else{$l->file_url=null;}return $l;}
    public function bills(Request $r){$x=$this->resident($r);if(!$x)return ApiResponse::error('PROFILE_NOT_LINKED','Akun belum ditautkan.',409);return ApiResponse::success(DB::table('dues_bills as d')->join('billing_periods as b','b.id','=','d.billing_period_id')->where('d.resident_id',$x->id)->select('d.*','b.period')->orderByDesc('b.period')->get());}
    public function notifications(Request $r){$rows=DB::table('notifications')->where('user_id',$this->user($r)->id)->orderByDesc('created_at')->limit(100)->get();$filtered=$rows->filter(fn($n)=>$this->shouldShowNotification($n))->values();return ApiResponse::success($filtered);}
    private function shouldShowNotification(object $n):bool{$type=strtolower((string)($n->type??''));$title=strtolower((string)($n->title??''));$isBillReminder=in_array($type,['dues_reminder','tagihan'],true)||str_contains($title,'pengingat tagihan')||str_contains($title,'pengingat iuran');if(!$isBillReminder)return true;$data=json_decode((string)($n->data_json??''),true);$billId=$data['bill_id']??$data['ref_id']??null;if(!$billId)return false;return DB::table('dues_bills')->where('id',$billId)->where('status','UNPAID')->exists();}
    public function notificationRead(Request $r,string $id){$n=DB::table('notifications')->where('id',$id)->where('user_id',$this->user($r)->id)->update(['read_at'=>now()]);return $n?ApiResponse::success(['read'=>true]):ApiResponse::error('NOT_FOUND','Notifikasi tidak ditemukan.',404);}
}
