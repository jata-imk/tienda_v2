<?php

namespace App\Http\Controllers;

use App\Http\Requests\Currency\CreateCurrencyRequest;
use App\Http\Requests\Currency\UpdateCurrencyRequest;
use App\Http\Resources\Currency\CurrencyResource;
use App\Services\CurrencyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Configuration
 *
 * @authenticated
 */
class CurrencyController extends Controller
{
    public function __construct(private CurrencyService $currencyService) {}

    /**
     * List currencies
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Currencies retrieved.","data":[]}
     */
    public function index(): JsonResponse
    {
        return ApiResponse::ok('Currencies retrieved.', CurrencyResource::collection($this->currencyService->index()));
    }

    /**
     * Get currency
     *
     * @urlParam id integer required Currency ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Currency retrieved.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Currency not found.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $currency = $this->currencyService->show($id);

        if (!$currency) {
            return ApiResponse::error('Currency not found.', 404);
        }

        return ApiResponse::ok('Currency retrieved.', new CurrencyResource($currency));
    }

    /**
     * Create currency
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Currency created.","data":{}}
     */
    public function store(CreateCurrencyRequest $request): JsonResponse
    {
        return ApiResponse::created('Currency created.', new CurrencyResource($this->currencyService->store($request->toDTO())));
    }

    /**
     * Update currency
     *
     * @urlParam id integer required Currency ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Currency updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Currency not found.","data":null}
     */
    public function update(UpdateCurrencyRequest $request, int $id): JsonResponse
    {
        $currency = $this->currencyService->update($id, $request->toDTO());

        if (!$currency) {
            return ApiResponse::error('Currency not found.', 404);
        }

        return ApiResponse::ok('Currency updated.', new CurrencyResource($currency));
    }

    /**
     * Deactivate currency
     *
     * @urlParam id integer required Currency ID. Example: 1
     *
     * Fails with 409 if it is the company's base currency.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Currency deactivated.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Currency not found.","data":null}
     * @response 409 {"ok":false,"code":409,"status":"Conflict","message":"Currency is the company base currency.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        return match ($this->currencyService->destroy($id)) {
            'not_found' => ApiResponse::error('Currency not found.', 404),
            'in_use'    => ApiResponse::error('Currency is the company base currency.', 409),
            default     => ApiResponse::ok('Currency deactivated.'),
        };
    }
}
