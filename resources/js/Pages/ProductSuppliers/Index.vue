<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted, reactive, ref } from 'vue';

const products = ref([]);
const productsLoading = ref(false);
const productSearch = ref('');
const selectedProductId = ref(null);

const linkedSuppliers = ref([]);
const linkedMeta = ref(null);
const linkedLinks = ref([]);
const linkedLoading = ref(false);

const availableSuppliers = ref([]);
const availableMeta = ref(null);
const availableLinks = ref([]);
const availableLoading = ref(false);

const allLinkedSupplierIds = ref([]);
const selectedAvailableSupplierIds = ref([]);
const selectedLinkedSupplierIds = ref([]);

const availableFilters = reactive({
  q: '',
  status: 'active',
});

const linkedFilters = reactive({
  q: '',
  status: '',
});

const processingAction = ref(false);
const flash = reactive({ type: '', message: '' });

const batchOperation = reactive({
  id: null,
  type: '',
  status: '',
  error: '',
  result: null,
  running: false,
});

let batchPollingInterval = null;

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

function getCheckedIds(ids) {
  return ids.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0);
}

function clearBatchPolling() {
  if (batchPollingInterval) {
    clearInterval(batchPollingInterval);
    batchPollingInterval = null;
  }
}

async function loadProducts() {
  productsLoading.value = true;

  try {
    const response = await window.axios.get('/api/v1/products', {
      params: {
        q: productSearch.value || undefined,
      },
    });

    products.value = response.data.data ?? [];

    if (!selectedProductId.value && products.value.length > 0) {
      selectedProductId.value = products.value[0].id;
      await reloadProductSuppliersData();
    }
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao carregar produtos.'));
  } finally {
    productsLoading.value = false;
  }
}

async function loadAllLinkedSupplierIds() {
  if (!selectedProductId.value) {
    allLinkedSupplierIds.value = [];
    return;
  }

  let url = `/api/v1/products/${selectedProductId.value}/suppliers`;
  const ids = [];

  while (url) {
    const response = await window.axios.get(url);
    const rows = response.data.data ?? [];

    rows.forEach((supplier) => {
      ids.push(Number(supplier.id));
    });

    url = response.data.next_page_url;
  }

  allLinkedSupplierIds.value = Array.from(new Set(ids));
}

async function loadLinkedSuppliers(url = null) {
  if (typeof url !== 'string') {
    url = null;
  }

  if (!selectedProductId.value) {
    linkedSuppliers.value = [];
    linkedMeta.value = null;
    linkedLinks.value = [];
    return;
  }

  linkedLoading.value = true;

  try {
    const response = await window.axios.get(url ?? `/api/v1/products/${selectedProductId.value}/suppliers`, {
      params: {
        q: linkedFilters.q || undefined,
        status: linkedFilters.status || undefined,
      },
    });

    linkedSuppliers.value = response.data.data ?? [];
    linkedMeta.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to,
    };
    linkedLinks.value = response.data.links ?? [];
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao carregar fornecedores vinculados.'));
  } finally {
    linkedLoading.value = false;
  }
}

async function loadAvailableSuppliers(url = null) {
  if (typeof url !== 'string') {
    url = null;
  }

  availableLoading.value = true;

  try {
    const response = await window.axios.get(url ?? '/api/v1/suppliers', {
      params: {
        q: availableFilters.q || undefined,
        status: availableFilters.status || undefined,
      },
    });

    const linkedIds = new Set(allLinkedSupplierIds.value);
    const rows = response.data.data ?? [];

    availableSuppliers.value = rows.filter((supplier) => !linkedIds.has(Number(supplier.id)));
    availableMeta.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to,
    };
    availableLinks.value = response.data.links ?? [];
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao carregar fornecedores disponiveis.'));
  } finally {
    availableLoading.value = false;
  }
}

async function reloadProductSuppliersData() {
  if (!selectedProductId.value) {
    return;
  }

  selectedAvailableSupplierIds.value = [];
  selectedLinkedSupplierIds.value = [];

  try {
    await loadAllLinkedSupplierIds();
    await Promise.all([
      loadLinkedSuppliers(),
      loadAvailableSuppliers(),
    ]);
  } catch (error) {
    showFlash('error', getErrorMessage(error, 'Falha ao recarregar dados do vinculo.'));
  }
}

async function selectProduct(productId) {
  selectedProductId.value = Number(productId);
  await reloadProductSuppliersData();
}

async function linkSupplier(supplierId) {
  if (!selectedProductId.value) {
    return;
  }

  processingAction.value = true;

  try {
    await window.axios.post(`/api/v1/products/${selectedProductId.value}/suppliers`, {
      supplier_id: Number(supplierId),
    });

    showFlash('success', 'Fornecedor vinculado com sucesso.');
    await reloadProductSuppliersData();
  } catch (error) {
    const message = getErrorMessage(error, 'Falha ao vincular fornecedor.');
    showFlash('error', message);
  } finally {
    processingAction.value = false;
  }
}

async function unlinkSupplier(supplierId) {
  if (!selectedProductId.value) {
    return;
  }

  processingAction.value = true;

  try {
    await window.axios.delete(`/api/v1/products/${selectedProductId.value}/suppliers/${Number(supplierId)}`);

    showFlash('success', 'Fornecedor desvinculado com sucesso.');
    await reloadProductSuppliersData();
  } catch (error) {
    const message = getErrorMessage(error, 'Falha ao desvincular fornecedor.');
    showFlash('error', message);
  } finally {
    processingAction.value = false;
  }
}

async function fetchBatchOperationStatus() {
  if (!batchOperation.id) {
    return;
  }

  try {
    const response = await window.axios.get(`/api/v1/batch-operations/${batchOperation.id}`);
    const payload = response.data?.data ?? {};

    batchOperation.status = payload.status ?? '';
    batchOperation.error = payload.error_message ?? '';
    batchOperation.result = payload.payload?.result ?? null;

    if (batchOperation.status === 'done') {
      batchOperation.running = false;
      clearBatchPolling();
      showFlash('success', 'Operação em massa concluida com sucesso.');
      await reloadProductSuppliersData();
    }

    if (batchOperation.status === 'failed') {
      batchOperation.running = false;
      clearBatchPolling();
      showFlash('error', batchOperation.error || 'Falha ao processar operação em massa.');
    }
  } catch (error) {
    batchOperation.running = false;
    clearBatchPolling();
    showFlash('error', getErrorMessage(error, 'Falha ao consultar status da operação em massa.'));
  }
}

async function startBatchPolling(batchOperationId, type) {
  clearBatchPolling();

  batchOperation.id = batchOperationId;
  batchOperation.type = type;
  batchOperation.status = 'pending';
  batchOperation.error = '';
  batchOperation.result = null;
  batchOperation.running = true;

  await fetchBatchOperationStatus();

  if (!batchOperation.running) {
    return;
  }

  batchPollingInterval = setInterval(() => {
    fetchBatchOperationStatus();
  }, 2000);
}

async function bulkLinkSuppliers() {
  if (!selectedProductId.value) {
    showFlash('error', 'Selecione um produto antes de vincular fornecedores.');
    return;
  }

  const supplierIds = getCheckedIds(selectedAvailableSupplierIds.value);

  if (supplierIds.length === 0) {
    showFlash('error', 'Selecione ao menos um fornecedor para vincular em massa.');
    return;
  }

  processingAction.value = true;

  try {
    const response = await window.axios.post(`/api/v1/products/${selectedProductId.value}/suppliers/bulk-link`, {
      supplier_ids: supplierIds,
    });

    selectedAvailableSupplierIds.value = [];
    showFlash('success', response.data?.message || 'Operacao enviada para processamento.');

    const operationId = response.data?.data?.batch_operation_id;
    if (operationId) {
      await startBatchPolling(Number(operationId), 'link');
    }
  } catch (error) {
    const message = getErrorMessage(error, 'Falha ao solicitar vinculo em massa.');
    showFlash('error', message);
  } finally {
    processingAction.value = false;
  }
}

async function bulkUnlinkSuppliers() {
  if (!selectedProductId.value) {
    showFlash('error', 'Selecione um produto antes de desvincular fornecedores.');
    return;
  }

  const supplierIds = getCheckedIds(selectedLinkedSupplierIds.value);

  if (supplierIds.length === 0) {
    showFlash('error', 'Selecione ao menos um fornecedor para desvincular em massa.');
    return;
  }

  processingAction.value = true;

  try {
    const response = await window.axios.post(`/api/v1/products/${selectedProductId.value}/suppliers/bulk-unlink`, {
      supplier_ids: supplierIds,
    });

    selectedLinkedSupplierIds.value = [];
    showFlash('success', response.data?.message || 'Operacao enviada para processamento.');

    const operationId = response.data?.data?.batch_operation_id;
    if (operationId) {
      await startBatchPolling(Number(operationId), 'unlink');
    }
  } catch (error) {
    const message = getErrorMessage(error, 'Falha ao solicitar desvinculo em massa.');
    showFlash('error', message);
  } finally {
    processingAction.value = false;
  }
}

onMounted(async () => {
  await loadProducts();
});

onUnmounted(() => {
  clearBatchPolling();
});
</script>

<template>
  <Head title="Vinculo Produto x Fornecedor" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Vinculo Produto x Fornecedor</h2>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <section class="rounded-lg bg-white p-6 shadow">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700">Buscar produto</label>
              <input
                v-model="productSearch"
                type="text"
                placeholder="Nome, codigo ou descricao"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
            </div>
            <div class="flex items-end">
              <button
                type="button"
                class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                @click="loadProducts"
              >
                Buscar produtos
              </button>
            </div>
          </div>

          <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Produto selecionado</label>
              <select
                :value="selectedProductId || ''"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @change="selectProduct($event.target.value)"
              >
                <option disabled value="">Selecione um produto</option>
                <option v-for="product in products" :key="product.id" :value="product.id">
                  {{ product.name }} ({{ product.internal_code }})
                </option>
              </select>
            </div>

            <div class="flex items-end">
              <button
                type="button"
                class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="productsLoading || !selectedProductId"
                @click="reloadProductSuppliersData"
              >
                {{ productsLoading ? 'Carregando...' : 'Recarregar vinculos' }}
              </button>
            </div>
          </div>
        </section>

        <section
          v-if="batchOperation.id"
          class="mt-6 rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900"
        >
          <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <p class="font-semibold">Operação em massa</p>
              <p>
                Tipo: {{ batchOperation.type === 'link' ? 'Vinculo' : 'Desvinculo' }} |
                Status: {{ batchOperation.status }}
              </p>
              <p v-if="batchOperation.error" class="text-red-700">Erro: {{ batchOperation.error }}</p>
            </div>

            <button
              type="button"
              class="rounded-md border border-indigo-300 px-3 py-2 text-xs font-medium text-indigo-800 hover:bg-indigo-100"
              @click="fetchBatchOperationStatus"
            >
              Atualizar status
            </button>
          </div>
        </section>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
          <section class="rounded-lg bg-white p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-800">Fornecedores disponiveis</h3>
              <button
                type="button"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="processingAction || batchOperation.running"
                @click="bulkLinkSuppliers"
              >
                Vincular selecionados
              </button>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
              <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700">Busca</label>
                <input
                  v-model="availableFilters.q"
                  type="text"
                  placeholder="Nome, CNPJ ou e-mail"
                  class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select v-model="availableFilters.status" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                  <option value="">Todos</option>
                  <option value="active">Ativo</option>
                  <option value="inactive">Inativo</option>
                </select>
              </div>
            </div>

            <button
              type="button"
              class="mb-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
              @click="loadAvailableSuppliers()"
            >
              Filtrar disponiveis
            </button>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                      <input
                        type="checkbox"
                        :checked="availableSuppliers.length > 0 && selectedAvailableSupplierIds.length === availableSuppliers.length"
                        @change="selectedAvailableSupplierIds = $event.target.checked ? availableSuppliers.map((item) => item.id) : []"
                      />
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Nome</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Contato</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Acao</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                  <tr v-if="availableLoading">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">Carregando fornecedores...</td>
                  </tr>
                  <tr v-else-if="!selectedProductId">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">Selecione um produto para visualizar fornecedores.</td>
                  </tr>
                  <tr v-else-if="availableSuppliers.length === 0">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">Nenhum fornecedor disponivel encontrado.</td>
                  </tr>
                  <tr v-for="supplier in availableSuppliers" :key="supplier.id">
                    <td class="px-4 py-3">
                      <input
                        v-model="selectedAvailableSupplierIds"
                        type="checkbox"
                        :value="supplier.id"
                      />
                    </td>
                    <td class="px-4 py-3">
                      <div class="font-medium text-gray-800">{{ supplier.name }}</div>
                      <div class="text-xs text-gray-500">{{ supplier.cnpj }}</div>
                    </td>
                    <td class="px-4 py-3">
                      <div>{{ supplier.email }}</div>
                      <div class="text-xs text-gray-500">{{ supplier.phone }}</div>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <button
                        type="button"
                        class="rounded-md border border-indigo-200 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processingAction || batchOperation.running"
                        @click="linkSupplier(supplier.id)"
                      >
                        Vincular
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex flex-col gap-2 text-sm text-gray-600 md:flex-row md:items-center md:justify-between" v-if="availableMeta">
              <span>Exibindo {{ availableMeta.from ?? 0 }} a {{ availableMeta.to ?? 0 }} de {{ availableMeta.total ?? 0 }}</span>
              <div class="flex items-center gap-1">
                <button
                  v-for="link in availableLinks"
                  :key="link.label"
                  :disabled="!link.url || link.active"
                  class="rounded border px-3 py-1"
                  :class="link.active ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50'"
                  v-html="link.label"
                  @click="loadAvailableSuppliers(link.url)"
                />
              </div>
            </div>
          </section>

          <section class="rounded-lg bg-white p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-800">Fornecedores vinculados</h3>
              <button
                type="button"
                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="processingAction || batchOperation.running"
                @click="bulkUnlinkSuppliers"
              >
                Desvincular selecionados
              </button>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
              <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700">Busca</label>
                <input
                  v-model="linkedFilters.q"
                  type="text"
                  placeholder="Nome, CNPJ ou e-mail"
                  class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select v-model="linkedFilters.status" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                  <option value="">Todos</option>
                  <option value="active">Ativo</option>
                  <option value="inactive">Inativo</option>
                </select>
              </div>
            </div>

            <button
              type="button"
              class="mb-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
              @click="loadLinkedSuppliers()"
            >
              Filtrar vinculados
            </button>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                      <input
                        type="checkbox"
                        :checked="linkedSuppliers.length > 0 && selectedLinkedSupplierIds.length === linkedSuppliers.length"
                        @change="selectedLinkedSupplierIds = $event.target.checked ? linkedSuppliers.map((item) => item.id) : []"
                      />
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Nome</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Contato</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Acao</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                  <tr v-if="linkedLoading">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">Carregando fornecedores vinculados...</td>
                  </tr>
                  <tr v-else-if="!selectedProductId">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">Selecione um produto para visualizar fornecedores vinculados.</td>
                  </tr>
                  <tr v-else-if="linkedSuppliers.length === 0">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">Nenhum fornecedor vinculado encontrado.</td>
                  </tr>
                  <tr v-for="supplier in linkedSuppliers" :key="supplier.id">
                    <td class="px-4 py-3">
                      <input
                        v-model="selectedLinkedSupplierIds"
                        type="checkbox"
                        :value="supplier.id"
                      />
                    </td>
                    <td class="px-4 py-3">
                      <div class="font-medium text-gray-800">{{ supplier.name }}</div>
                      <div class="text-xs text-gray-500">{{ supplier.cnpj }}</div>
                    </td>
                    <td class="px-4 py-3">
                      <div>{{ supplier.email }}</div>
                      <div class="text-xs text-gray-500">{{ supplier.phone }}</div>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <button
                        type="button"
                        class="rounded-md border border-red-200 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processingAction || batchOperation.running"
                        @click="unlinkSupplier(supplier.id)"
                      >
                        Desvincular
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex flex-col gap-2 text-sm text-gray-600 md:flex-row md:items-center md:justify-between" v-if="linkedMeta">
              <span>Exibindo {{ linkedMeta.from ?? 0 }} a {{ linkedMeta.to ?? 0 }} de {{ linkedMeta.total ?? 0 }}</span>
              <div class="flex items-center gap-1">
                <button
                  v-for="link in linkedLinks"
                  :key="link.label"
                  :disabled="!link.url || link.active"
                  class="rounded border px-3 py-1"
                  :class="link.active ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50'"
                  v-html="link.label"
                  @click="loadLinkedSuppliers(link.url)"
                />
              </div>
            </div>
          </section>
        </div>
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
