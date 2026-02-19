<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps({
  orderId: {
    type: [Number, String],
    required: true,
  },
});

const order = ref(null);
const orderItems = computed(() => order.value?.items ?? []);
const loading = ref(false);
const savingOrder = ref(false);
const savingStatus = ref(false);
const addingItem = ref(false);
const removingItemId = ref(null);
const loadingLinkedProducts = ref(false);

const suppliers = ref([]);
const linkedProducts = ref([]);

const orderForm = reactive({
  supplier_id: '',
  order_date: '',
  notes: '',
});

const statusForm = reactive({
  status: 'open',
});

const itemForm = reactive({
  product_id: '',
  quantity: 1,
  unit_price: '0.00',
});

const formErrors = reactive({
  supplier_id: '',
  order_date: '',
  notes: '',
  status: '',
  item: '',
});

const flash = reactive({ type: '', message: '' });

function showFlash(type, message) {
  flash.type = type;
  flash.message = message;

  setTimeout(() => {
    flash.type = '';
    flash.message = '';
  }, 3000);
}

function getErrorMessage(error, fallbackMessage) {
  const responseMessage = error?.response?.data?.message;

  if (typeof responseMessage === 'string' && responseMessage.trim() !== '') {
    return responseMessage;
  }

  const validationErrors = error?.response?.data?.errors;

  if (validationErrors && typeof validationErrors === 'object') {
    const firstField = Object.keys(validationErrors)[0];
    const firstError = Array.isArray(validationErrors[firstField]) ? validationErrors[firstField][0] : null;

    if (typeof firstError === 'string' && firstError.trim() !== '') {
      return firstError;
    }
  }

  const status = error?.response?.status;

  if (status) {
    return `${fallbackMessage} (HTTP ${status})`;
  }

  return fallbackMessage;
}

function resetErrors() {
  Object.keys(formErrors).forEach((key) => {
    formErrors[key] = '';
  });
}

function statusLabel(status) {
  const map = {
    open: 'Aberto',
    processing: 'Processando',
    completed: 'Concluido',
    cancelled: 'Cancelado',
  };

  return map[status] || status;
}

function statusClass(status) {
  if (status === 'open') {
    return 'bg-blue-100 text-blue-700';
  }

  if (status === 'processing') {
    return 'bg-amber-100 text-amber-700';
  }

  if (status === 'completed') {
    return 'bg-emerald-100 text-emerald-700';
  }

  return 'bg-gray-100 text-gray-700';
}

function formatCurrency(value) {
  const amount = Number(value || 0);

  return amount.toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  });
}

function syncFormsFromOrder() {
  if (!order.value) {
    return;
  }

  orderForm.supplier_id = order.value.supplier_id;
  orderForm.order_date = order.value.order_date;
  orderForm.notes = order.value.notes || '';
  statusForm.status = order.value.status;
}

function resetItemForm() {
  itemForm.product_id = '';
  itemForm.quantity = 1;
  itemForm.unit_price = '0.00';
}

function keepSelectedProductIfLinked() {
  const allowedIds = new Set(linkedProducts.value.map((product) => Number(product.id)));

  if (itemForm.product_id && !allowedIds.has(Number(itemForm.product_id))) {
    itemForm.product_id = '';
  }
}

async function loadSuppliers() {
  try {
    const response = await window.axios.get('/api/v1/suppliers', {
      params: {
        status: 'active',
      },
    });

    suppliers.value = response.data.data ?? [];
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao carregar fornecedores.'));
  }
}

async function loadLinkedProductsBySupplier(supplierId) {
  const id = Number(supplierId);

  if (!id) {
    linkedProducts.value = [];
    keepSelectedProductIfLinked();
    return;
  }

  loadingLinkedProducts.value = true;

  try {
    const response = await window.axios.get(`/api/v1/suppliers/${id}/products`, {
      params: {
        status: 'active',
      },
    });

    linkedProducts.value = response.data.data ?? [];
    keepSelectedProductIfLinked();
  } catch (error) {
    linkedProducts.value = [];
    showFlash('error', getErrorMessage(error, 'Falha ao carregar produtos vinculados ao fornecedor.'));
  } finally {
    loadingLinkedProducts.value = false;
  }
}

async function handleSupplierChange() {
  await loadLinkedProductsBySupplier(orderForm.supplier_id);
}

async function loadOrder() {
  loading.value = true;

  try {
    const response = await window.axios.get(`/api/v1/orders/${Number(props.orderId)}`);
    order.value = response.data?.data ?? null;
    syncFormsFromOrder();
    await loadLinkedProductsBySupplier(order.value?.supplier_id);
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao carregar pedido.'));
  } finally {
    loading.value = false;
  }
}

async function saveOrderData() {
  if (!order.value) {
    return;
  }

  savingOrder.value = true;
  resetErrors();

  try {
    const payload = {
      supplier_id: Number(orderForm.supplier_id),
      order_date: orderForm.order_date,
      notes: orderForm.notes || null,
    };

    const response = await window.axios.put(`/api/v1/orders/${order.value.id}`, payload);
    order.value = response.data?.data ?? order.value;
    syncFormsFromOrder();
    await loadLinkedProductsBySupplier(order.value?.supplier_id);
    showFlash('success', response.data?.message || 'Pedido atualizado com sucesso.');
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao atualizar pedido.'));
  } finally {
    savingOrder.value = false;
  }
}

async function updateStatus() {
  if (!order.value) {
    return;
  }

  savingStatus.value = true;
  resetErrors();

  try {
    const response = await window.axios.patch(`/api/v1/orders/${order.value.id}/status`, {
      status: statusForm.status,
    });

    order.value = response.data?.data ?? order.value;
    syncFormsFromOrder();
    showFlash('success', response.data?.message || 'Status atualizado com sucesso.');
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao atualizar status do pedido.'));
  } finally {
    savingStatus.value = false;
  }
}

async function addItem() {
  if (!order.value) {
    return;
  }

  addingItem.value = true;
  resetErrors();

  try {
    await window.axios.post(`/api/v1/orders/${order.value.id}/items`, {
      product_id: Number(itemForm.product_id),
      quantity: Number(itemForm.quantity),
      unit_price: Number(itemForm.unit_price),
    });

    showFlash('success', 'Item adicionado com sucesso.');
    resetItemForm();
    await loadOrder();
  } catch (error) {
    formErrors.item = getErrorMessage(error, 'Falha ao adicionar item.');
    showFlash('error', formErrors.item);
  } finally {
    addingItem.value = false;
  }
}

async function removeItem(itemId) {
  if (!order.value) {
    return;
  }

  removingItemId.value = itemId;

  try {
    await window.axios.delete(`/api/v1/orders/${order.value.id}/items/${itemId}`);

    showFlash('success', 'Item removido com sucesso.');
    await loadOrder();
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao remover item.'));
  } finally {
    removingItemId.value = null;
  }
}

onMounted(async () => {
  await Promise.all([
    loadSuppliers(),
    loadOrder(),
  ]);
});
</script>

<template>
  <Head :title="`Pedido #${props.orderId}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Detalhe do Pedido</h2>
        <Link :href="route('orders.index.page')" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Voltar
        </Link>
      </div>
    </template>

    <div class="py-8">
      <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
        <section class="rounded-lg bg-white p-6 shadow lg:col-span-1">
          <h3 class="mb-4 text-lg font-semibold text-gray-800">Dados do pedido</h3>

          <div v-if="loading" class="text-sm text-gray-500">Carregando pedido...</div>

          <form v-else-if="order" class="space-y-4" @submit.prevent="saveOrderData">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Fornecedor</label>
              <select
                v-model="orderForm.supplier_id"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @change="handleSupplierChange"
              >
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Data do pedido</label>
              <input v-model="orderForm.order_date" type="date" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Observações</label>
              <textarea v-model="orderForm.notes" rows="3" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div class="rounded-md border border-gray-200 p-3 text-sm">
              <div class="flex items-center justify-between">
                <span class="font-medium text-gray-700">Status atual</span>
                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(order.status)">
                  {{ statusLabel(order.status) }}
                </span>
              </div>
              <div class="mt-2 flex items-center justify-between">
                <span class="font-medium text-gray-700">Total</span>
                <span>{{ formatCurrency(order.total_amount) }}</span>
              </div>
            </div>

            <button type="submit" :disabled="savingOrder" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
              {{ savingOrder ? 'Salvando...' : 'Salvar dados' }}
            </button>
          </form>
        </section>

        <section class="rounded-lg bg-white p-6 shadow lg:col-span-2">
          <div class="mb-6 rounded-md border border-gray-200 p-4">
            <h3 class="mb-3 text-lg font-semibold text-gray-800">Atualizar status</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
              <div class="md:col-span-3">
                <select v-model="statusForm.status" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                  <option value="open">Aberto</option>
                  <option value="processing">Processando</option>
                  <option value="completed">Concluido</option>
                  <option value="cancelled">Cancelado</option>
                </select>
              </div>
              <button type="button" :disabled="savingStatus" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60" @click="updateStatus">
                {{ savingStatus ? 'Salvando...' : 'Atualizar' }}
              </button>
            </div>
          </div>

          <div class="mb-6 rounded-md border border-gray-200 p-4">
            <h3 class="mb-3 text-lg font-semibold text-gray-800">Adicionar item</h3>

            <p v-if="orderForm.supplier_id && !loadingLinkedProducts && linkedProducts.length === 0" class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
              Nenhum produto ativo vinculado a este fornecedor.
              <Link :href="route('product-suppliers.page')" class="font-semibold underline">Ir para tela de vinculos</Link>
            </p>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
              <div class="md:col-span-2">
                <select v-model="itemForm.product_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="loadingLinkedProducts || linkedProducts.length === 0">
                  <option disabled value="">Produto</option>
                  <option v-for="product in linkedProducts" :key="product.id" :value="product.id">
                    {{ product.name }}
                  </option>
                </select>
              </div>
              <input v-model.number="itemForm.quantity" min="1" type="number" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Qtd" />
              <input v-model="itemForm.unit_price" min="0" step="0.01" type="number" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Valor" />
            </div>
            <div class="mt-3">
              <button type="button" :disabled="addingItem || loadingLinkedProducts || linkedProducts.length === 0" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60" @click="addItem">
                {{ addingItem ? 'Adicionando...' : 'Adicionar item' }}
              </button>
            </div>
            <p v-if="formErrors.item" class="mt-2 text-xs text-red-600">{{ formErrors.item }}</p>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Produto</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Quantidade</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Valor unitario</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Total item</th>
                  <th class="px-4 py-3 text-right font-semibold text-gray-600">Acao</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="loading">
                  <td colspan="5" class="px-4 py-6 text-center text-gray-500">Carregando itens...</td>
                </tr>
                <tr v-else-if="!order || orderItems.length === 0">
                  <td colspan="5" class="px-4 py-6 text-center text-gray-500">Nenhum item no pedido.</td>
                </tr>
                <tr v-for="item in orderItems" :key="item.id">
                  <td class="px-4 py-3">{{ item.product?.name || '-' }}</td>
                  <td class="px-4 py-3">{{ item.quantity }}</td>
                  <td class="px-4 py-3">{{ formatCurrency(item.unit_price) }}</td>
                  <td class="px-4 py-3">{{ formatCurrency(item.total_price) }}</td>
                  <td class="px-4 py-3 text-right">
                    <button type="button" class="rounded-md border border-red-200 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="removingItemId === item.id" @click="removeItem(item.id)">
                      {{ removingItemId === item.id ? 'Removendo...' : 'Remover' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>

    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="flash.message"
        class="fixed bottom-5 right-5 z-50 rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
        :class="flash.type === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'"
      >
        {{ flash.message }}
      </div>
    </Transition>
  </AuthenticatedLayout>
</template>
