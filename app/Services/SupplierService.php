<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

class SupplierService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Supplier::query()
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
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        try {
            $supplier->delete();
        } catch (QueryException $exception) {
            throw new BusinessRuleException(
                'Nao foi possivel remover o fornecedor por possuir registros vinculados.',
                previous: $exception
            );
        }
    }
}
