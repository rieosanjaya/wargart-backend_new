<?php
namespace App\Services;

use RuntimeException;

class JwtService
{
    public function issue(int $userId, int $sessionId, string $role): string
    {
        $now = time();
        return $this->encode(['iss' => config('app.url'), 'sub' => (string)$userId, 'sid' => $sessionId,
            'role' => $role, 'iat' => $now, 'exp' => $now + ((int)env('AUTH_ACCESS_TTL_MINUTES', 15) * 60)]);
    }
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new RuntimeException('Token tidak valid.');
        [$header, $payload, $signature] = $parts;
        $expected = $this->b64(hash_hmac('sha256', "$header.$payload", $this->secret(), true));
        if (!hash_equals($expected, $signature)) throw new RuntimeException('Signature token tidak valid.');
        $claims = json_decode($this->unb64($payload), true, flags: JSON_THROW_ON_ERROR);
        if (($claims['exp'] ?? 0) <= time()) throw new RuntimeException('Token kedaluwarsa.');
        return $claims;
    }
    private function encode(array $claims): string
    {
        $header = $this->b64(json_encode(['alg'=>'HS256','typ'=>'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->b64(json_encode($claims, JSON_THROW_ON_ERROR));
        return "$header.$payload.".$this->b64(hash_hmac('sha256', "$header.$payload", $this->secret(), true));
    }
    private function secret(): string
    {
        $secret = (string)env('JWT_SECRET', config('app.key'));
        $secret = str_starts_with($secret, 'base64:') ? base64_decode(substr($secret, 7)) : $secret;
        if (strlen($secret) < 32) throw new RuntimeException('JWT secret minimal 32 byte.');
        return $secret;
    }
    private function b64(string $v): string { return rtrim(strtr(base64_encode($v), '+/', '-_'), '='); }
    private function unb64(string $v): string { return base64_decode(strtr($v, '-_', '+/').str_repeat('=', (4-strlen($v)%4)%4), true); }
}
