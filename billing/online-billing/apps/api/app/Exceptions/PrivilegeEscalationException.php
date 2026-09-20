<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivilegeEscalationException extends Exception
{
    public function __construct(string $message = 'Cannot grant roles or permissions exceeding your own authority.')
    {
        parent::__construct($message, 403);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'PRIVILEGE_ESCALATION_DENIED',
                'message' => $this->getMessage(),
            ],
        ], 403);
    }
}
