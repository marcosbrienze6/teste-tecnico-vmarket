<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function create(array $data, array $items = []): Order
    {
        return DB::transaction(function () use ($data, $items): Order {
            $supplier = Supplier::query()->findOrFail((int) $data['supplier_id']);
            $this->assertSupplierIsActive($supplier);

            $order = Order::create([
                'supplier_id' => $supplier->id,
                'order_date' => $data['order_date'],
                'status' => $data['status'] ?? 'open',
                'notes' => $data['notes'] ?? null,
                'total_amount' => 0,
            ]);

            foreach ($items as $itemData) {
                $this->addItem($order, $itemData);
            }

            return $order->fresh(['items']);
        });
    }

    public function update(Order $order, array $data): Order
    {
        $this->assertOrderEditable($order);

        if (isset($data['supplier_id']) && (int) $data['supplier_id'] !== (int) $order->supplier_id) {
            $supplier = Supplier::query()->findOrFail((int) $data['supplier_id']);
            $this->assertSupplierIsActive($supplier);
        }

        $order->update($data);

        return $order->fresh();
    }

    public function addItem(Order $order, array $data): OrderItem
    {
        return DB::transaction(function () use ($order, $data): OrderItem {
            $this->assertOrderEditable($order);

            $product = Product::query()->findOrFail((int) $data['product_id']);
            $this->assertProductCanBeOrdered($order, $product);

            $quantity = (int) $data['quantity'];
            $unitPrice = (float) $data['unit_price'];

            if ($quantity <= 0) {
                throw new BusinessRuleException('A quantidade do item deve ser maior que zero.');
            }

            if ($unitPrice < 0) {
                throw new BusinessRuleException('O valor unitario do item nao pode ser negativo.');
            }

            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $quantity * $unitPrice,
            ]);

            $this->recalculateTotal($order);

            return $item->fresh();
        });
    }

    public function removeItem(OrderItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $order = $item->order()->firstOrFail();
            $this->assertOrderEditable($order);

            $item->delete();
            $this->recalculateTotal($order);
        });
    }

    public function updateStatus(Order $order, string $status): Order
    {
        if ($status === 'completed') {
            $this->assertOrderHasItems($order);
        }

        $order->update(['status' => $status]);

        return $order->fresh();
    }

    public function recalculateTotal(Order $order): Order
    {
        $total = (float) $order->items()->sum('total_price');
        $order->update(['total_amount' => $total]);

        return $order->fresh();
    }

    private function assertSupplierIsActive(Supplier $supplier): void
    {
        if ($supplier->status !== 'active') {
            throw new BusinessRuleException('Nao e permitido criar pedido para fornecedor inativo.');
        }
    }

    private function assertOrderEditable(Order $order): void
    {
        if ($order->status === 'completed') {
            throw new BusinessRuleException('Pedidos concluidos nao podem ser editados.');
        }
    }

    private function assertProductCanBeOrdered(Order $order, Product $product): void
    {
        if ($product->status !== 'active') {
            throw new BusinessRuleException('Somente produtos ativos podem ser adicionados ao pedido.');
        }

        $isLinked = $product->suppliers()
            ->where('suppliers.id', $order->supplier_id)
            ->exists();

        if (!$isLinked) {
            throw new BusinessRuleException(
                'O produto precisa estar vinculado ao fornecedor do pedido.'
            );
        }
    }

    private function assertOrderHasItems(Order $order): void
    {
        if (!$order->items()->exists()) {
            throw new BusinessRuleException('O pedido deve possuir ao menos um item.');
        }
    }
}
