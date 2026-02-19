<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductSupplierService
{
    public function paginateSuppliers(Product $product, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $product->suppliers()
            ->when(!empty($filters['q']), function ($query) use ($filters): void {
                $term = trim((string) $filters['q']);
                $query->where(function ($innerQuery) use ($term): void {
                    $innerQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('cnpj', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when(!empty($filters['status']), function ($query) use ($filters): void {
                $query->where('status', (string) $filters['status']);
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateProductsBySupplier(
        Supplier $supplier,
        array $filters = [],
        int $perPage = 50
    ): LengthAwarePaginator {
        return $supplier->products()
            ->when(!empty($filters['q']), function ($query) use ($filters): void {
                $term = trim((string) $filters['q']);
                $query->where(function ($innerQuery) use ($term): void {
                    $innerQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('internal_code', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when(!empty($filters['status']), function ($query) use ($filters): void {
                $query->where('status', (string) $filters['status']);
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findSupplierOrFail(int $supplierId): Supplier
    {
        return Supplier::query()->findOrFail($supplierId);
    }

    public function link(Product $product, Supplier $supplier): void
    {
        $product->suppliers()->syncWithoutDetaching([$supplier->id]);
    }

    public function unlink(Product $product, Supplier $supplier): int
    {
        return $product->suppliers()->detach([$supplier->id]);
    }

    public function linkMany(Product $product, array $supplierIds): array
    {
        return $product->suppliers()->syncWithoutDetaching($this->normalizeIds($supplierIds));
    }

    public function unlinkMany(Product $product, array $supplierIds): int
    {
        return $product->suppliers()->detach($this->normalizeIds($supplierIds));
    }

    public function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
    }
}
