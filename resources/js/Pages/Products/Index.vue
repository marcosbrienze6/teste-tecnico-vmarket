<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';

const products = ref([]);
const meta = ref(null);
const links = ref([]);
const loading = ref(false);
const submitting = ref(false);
const editingId = ref(null);
const flash = reactive({ type: '', message: '' });
const deleteModal = reactive({ open: false, product: null });

const filters = reactive({
  q: '',
  status: '',
});

const form = reactive({
  name: '',
  description: '',
  internal_code: '',
  status: 'active',
});

const formErrors = reactive({
  name: '',
  description: '',
  internal_code: '',
  status: '',
});

function showFlash(type, message) {
  flash.type = type;
  flash.message = message;

  setTimeout(() => {
    flash.type = '';
    flash.message = '';
  }, 3000);
}

function resetErrors() {
  Object.keys(formErrors).forEach((key) => {
    formErrors[key] = '';
  });
}

function resetForm() {
  editingId.value = null;
  form.name = '';
  form.description = '';
  form.internal_code = '';
  form.status = 'active';
  resetErrors();
}

function applyValidationErrors(error) {
  resetErrors();

  if (error?.response?.status !== 422 || !error.response.data?.errors) {
    return;
  }

  const errors = error.response.data.errors;

  Object.keys(formErrors).forEach((key) => {
    if (Array.isArray(errors[key]) && errors[key].length > 0) {
      formErrors[key] = errors[key][0];
    }
  });
}

async function loadProducts(url = null) {
  loading.value = true;

  try {
    const response = await window.axios.get(url ?? '/api/v1/products', {
      params: {
        q: filters.q || undefined,
        status: filters.status || undefined,
      },
    });

    products.value = response.data.data ?? [];
    meta.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to,
    };
    links.value = response.data.links ?? [];
  } catch (error) {
    showFlash('error', 'Falha ao carregar produtos.');
  } finally {
    loading.value = false;
  }
}

function editProduct(product) {
  editingId.value = product.id;
  form.name = product.name;
  form.description = product.description ?? '';
  form.internal_code = product.internal_code;
  form.status = product.status;
  resetErrors();
}

async function submitForm() {
  submitting.value = true;
  resetErrors();

  try {
    if (editingId.value) {
      await window.axios.put(`/api/v1/products/${editingId.value}`, form);
      showFlash('success', 'Produto atualizado com sucesso.');
    } else {
      await window.axios.post('/api/v1/products', form);
      showFlash('success', 'Produto criado com sucesso.');
    }

    resetForm();
    await loadProducts();
  } catch (error) {
    applyValidationErrors(error);
    const message = error?.response?.data?.message ?? 'Falha ao salvar produto.';
    showFlash('error', message);
  } finally {
    submitting.value = false;
  }
}

function requestDelete(product) {
  deleteModal.open = true;
  deleteModal.product = product;
}

function closeDeleteModal() {
  deleteModal.open = false;
  deleteModal.product = null;
}

async function confirmDeleteProduct() {
  if (!deleteModal.product) {
    return;
  }

  try {
    await window.axios.delete(`/api/v1/products/${deleteModal.product.id}`);
    showFlash('success', 'Produto removido com sucesso.');

    if (editingId.value === deleteModal.product.id) {
      resetForm();
    }

    closeDeleteModal();
    await loadProducts();
  } catch (error) {
    const message = error?.response?.data?.message ?? 'Falha ao remover produto.';
    showFlash('error', message);
  }
}

function handleSearch() {
  loadProducts();
}

onMounted(() => {
  loadProducts();
});
</script>

<template>
  <Head title="Produtos" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Produtos</h2>
    </template>

    <div class="py-8">
      <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
        <section class="rounded-lg bg-white p-6 shadow lg:col-span-1">
          <h3 class="mb-4 text-lg font-semibold text-gray-800">
            {{ editingId ? 'Editar produto' : 'Novo produto' }}
          </h3>

          <form class="space-y-4" @submit.prevent="submitForm">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Nome</label>
              <input v-model="form.name" type="text" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              <p v-if="formErrors.name" class="mt-1 text-xs text-red-600">{{ formErrors.name }}</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Descricao</label>
              <textarea v-model="form.description" rows="3" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              <p v-if="formErrors.description" class="mt-1 text-xs text-red-600">{{ formErrors.description }}</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Codigo interno</label>
              <input v-model="form.internal_code" type="text" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              <p v-if="formErrors.internal_code" class="mt-1 text-xs text-red-600">{{ formErrors.internal_code }}</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
              <select v-model="form.status" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="active">Ativo</option>
                <option value="inactive">Inativo</option>
              </select>
              <p v-if="formErrors.status" class="mt-1 text-xs text-red-600">{{ formErrors.status }}</p>
            </div>

            <div class="flex items-center gap-2">
              <button type="submit" :disabled="submitting" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                {{ submitting ? 'Salvando...' : (editingId ? 'Atualizar' : 'Criar') }}
              </button>

              <button v-if="editingId" type="button" @click="resetForm" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancelar
              </button>
            </div>
          </form>
        </section>

        <section class="rounded-lg bg-white p-6 shadow lg:col-span-2">
          <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="grid w-full grid-cols-1 gap-3 md:max-w-2xl md:grid-cols-3">
              <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700">Busca</label>
                <input
                  v-model="filters.q"
                  type="text"
                  placeholder="Nome, codigo ou descricao"
                  class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select v-model="filters.status" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                  <option value="">Todos</option>
                  <option value="active">Ativo</option>
                  <option value="inactive">Inativo</option>
                </select>
              </div>
            </div>

            <button @click="handleSearch" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
              Filtrar
            </button>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Nome</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Codigo</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Descricao</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                  <th class="px-4 py-3 text-right font-semibold text-gray-600">Acoes</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="loading">
                  <td colspan="5" class="px-4 py-6 text-center text-gray-500">Carregando produtos...</td>
                </tr>
                <tr v-else-if="products.length === 0">
                  <td colspan="5" class="px-4 py-6 text-center text-gray-500">Nenhum produto encontrado.</td>
                </tr>
                <tr v-for="product in products" :key="product.id">
                  <td class="px-4 py-3">{{ product.name }}</td>
                  <td class="px-4 py-3">{{ product.internal_code }}</td>
                  <td class="px-4 py-3">{{ product.description || '-' }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="product.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'">
                      {{ product.status === 'active' ? 'Ativo' : 'Inativo' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                      <button @click="editProduct(product)" class="rounded-md border border-indigo-200 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50">
                        Editar
                      </button>
                      <button @click="requestDelete(product)" class="rounded-md border border-red-200 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                        Remover
                      </button>
                    </div>
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
                @click="loadProducts(link.url)"
                class="rounded border px-3 py-1"
                :class="link.active ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50'"
                v-html="link.label"
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

    <div
      v-if="deleteModal.open"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4"
    >
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-gray-900">Confirmar remocao</h3>
        <p class="mt-2 text-sm text-gray-600">
          Deseja remover o produto
          <strong>{{ deleteModal.product?.name }}</strong>?
        </p>
        <div class="mt-5 flex justify-end gap-2">
          <button
            type="button"
            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="closeDeleteModal"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            @click="confirmDeleteProduct"
          >
            Remover
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
