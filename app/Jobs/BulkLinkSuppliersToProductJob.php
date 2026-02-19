<?php

namespace App\Jobs;

use App\Models\BatchOperation;
use App\Models\Product;
use App\Services\BatchOperationService;
use App\Services\ProductSupplierService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class BulkLinkSuppliersToProductJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $batchOperationId)
    {
    }

    public function handle(
        BatchOperationService $batchOperationService,
        ProductSupplierService $productSupplierService
    ): void {
        $operation = BatchOperation::query()->find($this->batchOperationId);

        if (!$operation) {
            return;
        }

        $batchOperationService->markProcessing($operation);

        try {
            $product = Product::query()->findOrFail((int) $operation->product_id);
            $supplierIds = (array) ($operation->payload['supplier_ids'] ?? []);
            $result = $productSupplierService->linkMany($product, $supplierIds);

            $batchOperationService->markDone($operation, [
                'attached' => count($result['attached'] ?? []),
                'updated' => count($result['updated'] ?? []),
            ]);
        } catch (Throwable $exception) {
            $batchOperationService->markFailed($operation, $exception->getMessage());
            throw $exception;
        }
    }
}
