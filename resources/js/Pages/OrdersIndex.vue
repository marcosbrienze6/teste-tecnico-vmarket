<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';

const orders = ref([]);
const meta = ref(null);
const links = ref([]);
const loading = ref(false);
const creating = ref(false);
const loadingLinkedProducts = ref(false);

const suppliers = ref([]);
const linkedProducts = ref([]);

const filters = reactive({
  status: '',
  supplier_id: '',
});

const createForm = reactive({
  supplier_id: '',
  order_date: new Date().toISOString().slice(0, 10),
  notes: '',
  items: [],
});

const createErrors = reactive({
  supplier_id: '',
  order_date: '',
  notes: '',
  items: '',
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

function resetCreateErrors() {
  Object.keys(createErrors).forEach((key) => {
    createErrors[key] = '';
  });
}

function addItemRow() {
  createForm.items.push({
    product_id: '',
    quantity: 1,
    unit_price: '0.00',
  });
}

function removeItemRow(index) {
  createForm.items.splice(index, 1);
}

function keepOnlyLinkedProductSelections() {
  const allowedIds = new Set(linkedProducts.value.map((product) => Number(product.id)));

  createForm.items.forEach((item) => {
    if (item.product_id && !allowedIds.has(Number(item.product_id))) {
      item.product_id = '';
    }
  });
}

function resetCreateForm() {
  createForm.supplier_id = '';
  createForm.order_date = new Date().toISOString().slice(0, 10);
  createForm.notes = '';
  createForm.items = [];
  linkedProducts.value = [];
  addItemRow();
  resetCreateErrors();
}

function applyCreateValidationErrors(error) {
  resetCreateErrors();

  if (error?.response?.status !== 422 || !error.response.data?.errors) {
    return;
  }

  const errors = error.response.data.errors;

  ['supplier_id', 'order_date', 'notes'].forEach((field) => {
    if (Array.isArray(errors[field]) && errors[field].length > 0) {
      createErrors[field] = errors[field][0];
    }
  });

  if (Object.keys(errors).some((key) => key.startsWith('items'))) {
    const firstItemsKey = Object.keys(errors).find((key) => key.startsWith('items'));

    if (firstItemsKey && Array.isArray(errors[firstItemsKey]) && errors[firstItemsKey].length > 0) {
      createErrors.items = errors[firstItemsKey][0];
    }
  }
}

function formatCurrency(value) {
  const amount = Number(value || 0);

  return amount.toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  });
}

function formatDateTime(value) {
  if (!value) {
    return '-';
  }

  const raw = String(value);
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(raw) ? `${raw}T00:00:00` : raw;
  const date = new Date(normalized);

  if (Number.isNaN(date.getTime())) {
    return raw;
  }

  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = String(date.getFullYear()).slice(-2);
  const hour = String(date.getHours()).padStart(2, '0');
  const minute = String(date.getMinutes()).padStart(2, '0');

  return `${day}/${month}/${year} ${hour}:${minute}`;
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
    keepOnlyLinkedProductSelections();
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
    keepOnlyLinkedProductSelections();
  } catch (error) {
    linkedProducts.value = [];
    showFlash('error', getErrorMessage(error, 'Falha ao carregar produtos vinculados ao fornecedor.'));
  } finally {
    loadingLinkedProducts.value = false;
  }
}

async function handleCreateSupplierChange() {
  await loadLinkedProductsBySupplier(createForm.supplier_id);
}

async function loadOrders(url = null) {
  if (typeof url !== 'string') {
    url = null;
  }

  loading.value = true;

  try {
    const response = await window.axios.get(url ?? '/api/v1/orders', {
      params: {
        status: filters.status || undefined,
        supplier_id: filters.supplier_id || undefined,
      },
    });

    orders.value = response.data.data ?? [];
    meta.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to,
    };
    links.value = response.data.links ?? [];
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao carregar pedidos.'));
  } finally {
    loading.value = false;
  }
}

async function submitCreateOrder() {
  creating.value = true;
  resetCreateErrors();

  try {
    const payload = {
      supplier_id: Number(createForm.supplier_id),
      order_date: createForm.order_date,
      notes: createForm.notes || null,
      items: createForm.items
        .filter((item) => item.product_id)
        .map((item) => ({
          product_id: Number(item.product_id),
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
        })),
    };

    await window.axios.post('/api/v1/orders', payload);

    showFlash('success', 'Pedido criado com sucesso.');
    resetCreateForm();
    await loadOrders();
  } catch (error) {
    applyCreateValidationErrors(error);
    showFlash('error', getErrorMessage(error, 'Falha ao criar pedido.'));
  } finally {
    creating.value = false;
  }
}

onMounted(async () => {
  addItemRow();

  await Promise.all([
    loadSuppliers(),
    loadOrders(),
  ]);
});
</script>

<template>
  <Head title="Pedidos" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Pedidos</h2>
    </template>

    <div class="py-8">
      <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
        <section class="rounded-lg bg-white p-6 shadow lg:col-span-1">
          <h3 class="mb-4 text-lg font-semibold text-gray-800">Novo pedido</h3>

          <form class="space-y-4" @submit.prevent="submitCreateOrder">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Fornecedor</label>
              <select
                v-model="createForm.supplier_id"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @change="handleCreateSupplierChange"
              >
                <option disabled value="">Selecione</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
              <p v-if="createErrors.supplier_id" class="mt-1 text-xs text-red-600">{{ createErrors.supplier_id }}</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Data do pedido</label>
              <input v-model="createForm.order_date" type="date" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              <p v-if="createErrors.order_date" class="mt-1 text-xs text-red-600">{{ createErrors.order_date }}</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Observações</label>
              <textarea v-model="createForm.notes" rows="2" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              <p v-if="createErrors.notes" class="mt-1 text-xs text-red-600">{{ createErrors.notes }}</p>
            </div>

            <div class="rounded-md border border-gray-200 p-3">
              <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-700">Itens do pedido</h4>
                <button type="button" class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="addItemRow">
                  + Item
                </button>
              </div>

              <p v-if="createForm.supplier_id && !loadingLinkedProducts && linkedProducts.length === 0" class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Nenhum produto ativo vinculado a este fornecedor.
                <Link :href="route('product-suppliers.page')" class="font-semibold underline">Ir para tela de vinculos</Link>
              </p>

              <div class="space-y-3">
                <div v-for="(item, index) in createForm.items" :key="index" class="rounded-md border border-gray-100 p-3">
                  <div class="grid grid-cols-1 gap-2">
                    <select v-model="item.product_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!createForm.supplier_id || loadingLinkedProducts || linkedProducts.length === 0">
                      <option disabled value="">Produto</option>
                      <option v-for="product in linkedProducts" :key="product.id" :value="product.id">
                        {{ product.name }}
                      </option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                      <input v-model.number="item.quantity" min="1" type="number" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Qtd" />
                      <input v-model="item.unit_price" min="0" step="0.01" type="number" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Valor" />
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-500">
                      <span>Total item: {{ formatCurrency((Number(item.quantity) || 0) * (Number(item.unit_price) || 0)) }}</span>
                      <button
                        v-if="createForm.items.length > 1"
                        type="button"
                        class="text-red-600 hover:text-red-700"
                        @click="removeItemRow(index)"
                      >
                        Remover item
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <p v-if="createErrors.items" class="mt-2 text-xs text-red-600">{{ createErrors.items }}</p>
            </div>

            <div class="flex items-center gap-2">
              <button type="submit" :disabled="creating" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                {{ creating ? 'Salvando...' : 'Criar pedido' }}
              </button>
              <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="resetCreateForm">
                Limpar
              </button>
            </div>
          </form>
        </section>

        <section class="rounded-lg bg-white p-6 shadow lg:col-span-2">
          <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700">Fornecedor</label>
              <select v-model="filters.supplier_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
              <select v-model="filters.status" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos</option>
                <option value="open">Aberto</option>
                <option value="processing">Processando</option>
                <option value="completed">Concluido</option>
                <option value="cancelled">Cancelado</option>
              </select>
            </div>

            <div class="flex items-end">
              <button type="button" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800" @click="loadOrders()">
                Filtrar
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">ID</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Fornecedor</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Data</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Total</th>
                  <th class="px-4 py-3 text-right font-semibold text-gray-600">Ações</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="loading">
                  <td colspan="6" class="px-4 py-6 text-center text-gray-500">Carregando pedidos...</td>
                </tr>
                <tr v-else-if="orders.length === 0">
                  <td colspan="6" class="px-4 py-6 text-center text-gray-500">Nenhum pedido encontrado.</td>
                </tr>
                <tr v-for="order in orders" :key="order.id">
                  <td class="px-4 py-3 font-medium text-gray-800">#{{ order.id }}</td>
                  <td class="px-4 py-3">{{ order.supplier?.name || '-' }}</td>
                  <td class="px-4 py-3">{{ formatDateTime(order.created_at || order.order_date) }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(order.status)">
                      {{ statusLabel(order.status) }}
                    </span>
                  </td>
                  <td class="px-4 py-3">{{ formatCurrency(order.total_amount) }}</td>
                  <td class="px-4 py-3 text-right">
                    <Link :href="route('orders.details.page', order.id)" class="rounded-md border border-indigo-200 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50">
                      Detalhes
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4 flex flex-col gap-2 text-sm text-gray-600 md:flex-row md:items-center md:justify-between" v-if="meta">
            <span>Exibindo {{ meta.from ?? 0 }} a {{ meta.to ?? 0 }} de {{ meta.total ?? 0 }}</span>
            <div class="flex items-center gap-1">
              <button
                v-for="link in links"
                :key="link.label"
                :disabled="!link.url || link.active"
                class="rounded border px-3 py-1"
                :class="link.active ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50'"
                v-html="link.label"
                @click="loadOrders(link.url)"
              />
            </div>
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
