<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LastAdminException extends Exception
{
    public function __construct(string $message = 'Cannot modify or suspend the last active administrator for this organization.')
    {
        parent::__construct($message, 422);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'LAST_ADMIN_PROTECTED',
                'message' => $this->getMessage(),
            ],
        ], 422);
    }
}
