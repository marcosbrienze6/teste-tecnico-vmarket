<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchOperation;
use Illuminate\Http\JsonResponse;

class BatchOperationController extends Controller
{
    public function show(BatchOperation $batchOperation): JsonResponse
    {
        return response()->json([
            'data' => $batchOperation,
        ]);
    }
}
