<?php
namespace App\Support;

class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $meta = [])
    {
        $body = ['data' => $data, 'request_id' => request()->attributes->get('request_id')];
        if ($meta !== []) $body['meta'] = $meta;
        return response()->json($body, $status);
    }
    public static function error(string $code, string $message, int $status, array $fields = [])
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) $error['fields'] = $fields;
        return response()->json(['error' => $error, 'request_id' => request()->attributes->get('request_id')], $status);
    }
}
