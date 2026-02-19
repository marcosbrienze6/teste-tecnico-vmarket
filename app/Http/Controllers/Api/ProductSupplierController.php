<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductSupplier\BulkSupplierRequest;
use App\Http\Requests\ProductSupplier\LinkSupplierRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductSupplierService;
use Illuminate\Http\JsonResponse;

class ProductSupplierController extends Controller
{
    public function __construct(private readonly ProductSupplierService $productSupplierService)
    {
    }

    public function index(Product $product): JsonResponse
    {
        $suppliers = $this->productSupplierService->paginateSuppliers($product, request()->only(['q', 'status']));

        return response()->json($suppliers);
    }

    public function store(LinkSupplierRequest $request, Product $product): JsonResponse
    {
        $supplier = Supplier::query()->findOrFail((int) $request->validated('supplier_id'));
        $this->productSupplierService->link($product, $supplier);

        return response()->json(['message' => 'Fornecedor vinculado com sucesso.']);
    }

    public function destroy(Product $product, Supplier $supplier): JsonResponse
    {
        $this->productSupplierService->unlink($product, $supplier);

        return response()->json(['message' => 'Fornecedor desvinculado com sucesso.']);
    }

    public function bulkStore(BulkSupplierRequest $request, Product $product): JsonResponse
    {
        $supplierIds = $request->validated('supplier_ids');
        $result = $this->productSupplierService->linkMany($product, $supplierIds);

        return response()->json([
            'message' => 'Vinculo em massa executado com sucesso.',
            'data' => $result,
        ]);
    }

    public function bulkDestroy(BulkSupplierRequest $request, Product $product): JsonResponse
    {
        $supplierIds = $request->validated('supplier_ids');
        $affectedRows = $this->productSupplierService->unlinkMany($product, $supplierIds);

        return response()->json([
            'message' => 'Desvinculo em massa executado com sucesso.',
            'data' => ['affected_rows' => $affectedRows],
        ]);
    }
}
