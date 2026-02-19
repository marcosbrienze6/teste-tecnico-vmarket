<?php

namespace App\Jobs;

use App\Models\BatchOperation;
use App\Models\Product;
use App\Services\BatchOperationService;
use App\Services\ProductSupplierService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class BulkUnlinkSuppliersFromProductJob implements ShouldQueue
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
            $affectedRows = $productSupplierService->unlinkMany($product, $supplierIds);

            $batchOperationService->markDone($operation, [
                'affected_rows' => $affectedRows,
            ]);
        } catch (Throwable $exception) {
            $batchOperationService->markFailed($operation, $exception->getMessage());
            throw $exception;
        }
    }
}
