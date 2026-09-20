<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConcurrencyException extends Exception
{
    public function __construct(string $message = 'The resource has been modified by another request. Please refresh and try again.')
    {
        parent::__construct($message, 409);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CONCURRENCY_CONFLICT',
                'message' => $this->getMessage(),
            ],
        ], 409);
    }
}
