<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public static function ok(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'ok'      => true,
            'code'    => $code,
            'status'  => Response::$statusTexts[$code] ?? 'OK',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    public static function created(string $message, mixed $data): JsonResponse
    {
        return self::ok($message, $data, 201);
    }

    public static function query(string $message, mixed $data, ?int $totalCount = null): JsonResponse
    {
        $payload = [
            'ok'      => true,
            'code'    => 200,
            'status'  => Response::$statusTexts[200],
            'message' => $message,
            'data'    => $data,
        ];

        if ($totalCount !== null) {
            $payload['totalCount'] = $totalCount;
            $payload['summary']    = [$totalCount];
        }

        return response()->json($payload);
    }

    public static function error(string $message, int $code, mixed $data = null): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'code'    => $code,
            'status'  => Response::$statusTexts[$code] ?? 'Error',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }
}
