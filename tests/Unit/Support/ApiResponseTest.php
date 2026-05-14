<?php

namespace Tests\Unit\Support;

use App\Support\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_query_wraps_items_and_count_metadata_inside_data(): void
    {
        $response = ApiResponse::query('Products retrieved.', [['id' => 1]], 4);
        $payload  = $response->getData(true);

        $this->assertSame([
            'ok'      => true,
            'code'    => 200,
            'status'  => 'OK',
            'message' => 'Products retrieved.',
            'data'    => [
                'items'      => [
                    ['id' => 1],
                ],
                'totalCount' => 4,
                'summary'    => [4],
            ],
        ], $payload);

        $this->assertArrayNotHasKey('totalCount', $payload);
        $this->assertArrayNotHasKey('summary', $payload);
    }

    public function test_query_omits_count_metadata_when_total_count_is_null(): void
    {
        $response = ApiResponse::query('Products retrieved.', [['id' => 1]]);
        $payload  = $response->getData(true);

        $this->assertSame([
            'items' => [
                ['id' => 1],
            ],
        ], $payload['data']);

        $this->assertArrayNotHasKey('totalCount', $payload);
        $this->assertArrayNotHasKey('summary', $payload);
        $this->assertArrayNotHasKey('totalCount', $payload['data']);
        $this->assertArrayNotHasKey('summary', $payload['data']);
    }
}
