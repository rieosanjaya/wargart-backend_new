<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function publicNews(Request $r,int $id)
    {
        $m=DB::table('media_files')->where('id',$id)->first();
        if(!$m)return ApiResponse::error('NOT_FOUND','File tidak ditemukan.',404);
        $allowed=DB::table('news')->where('image_media_id',$id)->where('status','PUBLISHED')->whereNull('deleted_at')->exists();
        if(!$allowed)return ApiResponse::error('FORBIDDEN','File tidak tersedia untuk publik.',403);
        if(!Storage::disk($m->disk)->exists($m->path))return ApiResponse::error('FILE_MISSING','File tidak tersedia pada storage.',404);
        return Storage::disk($m->disk)->response($m->path,$m->original_name,['Content-Type'=>$m->mime_type]);
    }

    public function publicLetter(Request $r,string $publicId)
    {
        $letter=DB::table('letter_requests')
            ->where('public_id',$publicId)
            ->whereNotNull('result_media_id')
            ->whereIn('status',['APPROVED','COMPLETED'])
            ->first();
        if(!$letter)return ApiResponse::error('NOT_FOUND','File surat tidak ditemukan.',404);
        $m=DB::table('media_files')->where('id',$letter->result_media_id)->first();
        if(!$m)return ApiResponse::error('NOT_FOUND','File surat tidak ditemukan.',404);
        if(!Storage::disk($m->disk)->exists($m->path))return ApiResponse::error('FILE_MISSING','File tidak tersedia pada storage.',404);
        return Storage::disk($m->disk)->response($m->path,$m->original_name,['Content-Type'=>$m->mime_type]);
    }

    public function download(Request $r,int $id)
    {
        $u=$r->attributes->get('auth_user'); $m=DB::table('media_files')->where('id',$id)->first();
        if(!$m)return ApiResponse::error('NOT_FOUND','File tidak ditemukan.',404);
        $allowed=$u->role==='ADMIN'||DB::table('news')->where('image_media_id',$id)->where('status','PUBLISHED')->whereNull('deleted_at')->exists();
        if(!$allowed&&$u->resident_id)$allowed=DB::table('residents')->where('id',$u->resident_id)->where('photo_media_id',$id)->exists()||DB::table('letter_requests')->where('resident_id',$u->resident_id)->where('result_media_id',$id)->exists();
        if(!$allowed)$allowed=DB::table('chat_messages as m')->join('chat_sessions as s','s.id','=','m.chat_session_id')->where('m.media_file_id',$id)->where(fn($q)=>$q->where('s.citizen_user_id',$u->id)->orWhere('s.assigned_admin_id',$u->id))->exists();
        if(!$allowed)return ApiResponse::error('FORBIDDEN','Anda tidak berhak mengakses file ini.',403);
        if(!Storage::disk($m->disk)->exists($m->path))return ApiResponse::error('FILE_MISSING','File tidak tersedia pada storage.',404);
        return Storage::disk($m->disk)->download($m->path,$m->original_name,['Content-Type'=>$m->mime_type]);
    }
}
