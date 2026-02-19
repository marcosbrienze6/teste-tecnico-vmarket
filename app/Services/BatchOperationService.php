<?php

namespace App\Services;

use App\Models\BatchOperation;
use App\Models\Product;
use InvalidArgumentException;

class BatchOperationService
{
    public function __construct(private readonly ProductSupplierService $productSupplierService)
    {
    }

    public function create(Product $product, string $type, array $supplierIds): BatchOperation
    {
        if (!in_array($type, [BatchOperation::TYPE_LINK, BatchOperation::TYPE_UNLINK], true)) {
            throw new InvalidArgumentException('Tipo de operacao em lote invalido.');
        }

        return BatchOperation::create([
            'type' => $type,
            'product_id' => $product->id,
            'payload' => [
                'supplier_ids' => $this->productSupplierService->normalizeIds($supplierIds),
            ],
            'status' => BatchOperation::STATUS_PENDING,
        ]);
    }

    public function markProcessing(BatchOperation $operation): BatchOperation
    {
        $operation->update([
            'status' => BatchOperation::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        return $operation->fresh();
    }

    public function markDone(BatchOperation $operation, array $result = []): BatchOperation
    {
        $payload = $operation->payload ?? [];
        $payload['result'] = $result;

        $operation->update([
            'status' => BatchOperation::STATUS_DONE,
            'payload' => $payload,
            'processed_at' => now(),
            'error_message' => null,
        ]);

        return $operation->fresh();
    }

    public function markFailed(BatchOperation $operation, string $errorMessage): BatchOperation
    {
        $operation->update([
            'status' => BatchOperation::STATUS_FAILED,
            'error_message' => mb_substr($errorMessage, 0, 2000),
            'processed_at' => now(),
        ]);

        return $operation->fresh();
    }
}
