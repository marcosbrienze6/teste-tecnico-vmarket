<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

class ProductService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
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
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        try {
            $product->delete();
        } catch (QueryException $exception) {
            throw new BusinessRuleException(
                'Nao foi possivel remover o produto por possuir registros vinculados.',
                previous: $exception
            );
        }
    }
}
