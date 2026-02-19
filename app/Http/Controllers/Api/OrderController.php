<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AddOrderItemRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(): JsonResponse
    {
        $orders = Order::query()
            ->with('supplier')
            ->when(request()->filled('status'), function ($query): void {
                $query->where('status', (string) request('status'));
            })
            ->when(request()->filled('supplier_id'), function ($query): void {
                $query->where('supplier_id', (int) request('supplier_id'));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            $items = $payload['items'] ?? [];
            unset($payload['items']);

            $order = $this->orderService->create($payload, $items);

            return response()->json([
                'message' => 'Pedido criado com sucesso.',
                'data' => $order->load(['supplier', 'items.product']),
            ], 201);
        } catch (BusinessRuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $order->load(['supplier', 'items.product']),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        try {
            $updatedOrder = $this->orderService->update($order, $request->validated());

            return response()->json([
                'message' => 'Pedido atualizado com sucesso.',
                'data' => $updatedOrder->load(['supplier', 'items.product']),
            ]);
        } catch (BusinessRuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function addItem(AddOrderItemRequest $request, Order $order): JsonResponse
    {
        try {
            $item = $this->orderService->addItem($order, $request->validated());

            return response()->json([
                'message' => 'Item adicionado com sucesso.',
                'data' => $item->load('product'),
            ], 201);
        } catch (BusinessRuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function removeItem(Order $order, OrderItem $item): JsonResponse
    {
        if ((int) $item->order_id !== (int) $order->id) {
            return response()->json(['message' => 'Item nao pertence ao pedido informado.'], 422);
        }

        try {
            $this->orderService->removeItem($item);

            return response()->json(['message' => 'Item removido com sucesso.']);
        } catch (BusinessRuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        try {
            $updatedOrder = $this->orderService->updateStatus($order, (string) $request->validated('status'));

            return response()->json([
                'message' => 'Status do pedido atualizado com sucesso.',
                'data' => $updatedOrder->load(['supplier', 'items.product']),
            ]);
        } catch (BusinessRuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
