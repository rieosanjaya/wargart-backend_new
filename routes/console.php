<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('wargart:create-admin {username} {phone}', function (string $username, string $phone) {
    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) { $this->error('Username tidak valid.'); return 1; }
    if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) { $this->error('Nomor harus format E.164.'); return 1; }
    if (User::where('username',$username)->orWhere('phone_e164',$phone)->exists()) { $this->error('Username/nomor sudah ada.'); return 1; }
    $password=$this->secret('Password admin minimal 12 karakter');
    if (strlen((string)$password)<12) { $this->error('Password terlalu pendek.'); return 1; }
    User::create(['username'=>$username,'phone_e164'=>$phone,'phone_verified_at'=>now(),'password_hash'=>Hash::make($password),'role'=>'ADMIN','is_active'=>true]);
    $this->info('Admin berhasil dibuat.'); return 0;
})->purpose('Membuat akun admin secara aman melalui CLI');
