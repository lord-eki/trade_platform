<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { getEcho } from '../services/echo';

const authStore = useAuthStore();
const router = useRouter();

const profile = ref(null);
const orders = ref([]);
const buyOrders = ref([]);
const sellOrders = ref([]);
const loading = ref(false);
const error = ref('');

// Form data
const orderForm = ref({
  symbol: 'BTC',
  side: 'buy',
  price: '',
  amount: ''
});

const selectedSymbol = ref('BTC');

const fetchProfile = async () => {
  try {
    const response = await api.get('/profile');
    profile.value = response.data;
  } catch (err) {
    console.error('Failed to fetch profile:', err);
  }
};

const fetchOrderbook = async () => {
  try {
    const response = await api.get('/orders', {
      params: { symbol: selectedSymbol.value }
    });
    buyOrders.value = response.data.buy_orders;
    sellOrders.value = response.data.sell_orders;
  } catch (err) {
    console.error('Failed to fetch orderbook:', err);
  }
};

const totalValue = computed(() => {
  if (!orderForm.value.price || !orderForm.value.amount) return '0.00';
  return (parseFloat(orderForm.value.price) * parseFloat(orderForm.value.amount)).toFixed(2);
});

const commission = computed(() => {
  return (parseFloat(totalValue.value) * 0.015).toFixed(2);
});

const totalCost = computed(() => {
  return (parseFloat(totalValue.value) + parseFloat(commission.value)).toFixed(2);
});

const placeOrder = async () => {
  if (!orderForm.value.price || !orderForm.value.amount) {
    error.value = 'Please fill all fields';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    await api.post('/orders', orderForm.value);
    orderForm.value.price = '';
    orderForm.value.amount = '';
    await Promise.all([fetchProfile(), fetchOrderbook()]);
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to place order';
  } finally {
    loading.value = false;
  }
};

const cancelOrder = async (orderId) => {
  try {
    await api.post(`/orders/${orderId}/cancel`);
    await Promise.all([fetchProfile(), fetchOrderbook()]);
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to cancel order';
  }
};

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};

onMounted(async () => {
  await fetchProfile();
  await fetchOrderbook();

  // Setup Echo listener
  const echo = getEcho();
  if (echo && authStore.user) {
    echo.private(`user.${authStore.user.id}`)
      .listen('.order.matched', (event) => {
        console.log('Order matched:', event);
        fetchProfile();
        fetchOrderbook();
      });
  }
});

onUnmounted(() => {
  const echo = getEcho();
  if (echo && authStore.user) {
    echo.leave(`user.${authStore.user.id}`);
  }
});
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Trading Platform</h1>
        <button
          @click="handleLogout"
          class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
        >
          Logout
        </button>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Profile Section -->
      <div v-if="profile" class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Wallet</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-green-50 p-4 rounded-lg">
            <p class="text-sm text-gray-600">USD Balance</p>
            <p class="text-2xl font-bold text-green-600">${{ parseFloat(profile.balance).toFixed(2) }}</p>
          </div>
          <div
            v-for="asset in profile.assets"
            :key="asset.symbol"
            class="bg-blue-50 p-4 rounded-lg"
          >
            <p class="text-sm text-gray-600">{{ asset.symbol }}</p>
            <p class="text-2xl font-bold text-blue-600">{{ parseFloat(asset.amount).toFixed(8) }}</p>
            <p class="text-xs text-gray-500">Locked: {{ parseFloat(asset.locked_amount).toFixed(8) }}</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order Form -->
        <div class="bg-white rounded-xl shadow p-6">
          <h2 class="text-xl font-semibold mb-4">Place Order</h2>
          
          <form @submit.prevent="placeOrder" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
              <select
                v-model="orderForm.symbol"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              >
                <option value="BTC">BTC</option>
                <option value="ETH">ETH</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Side</label>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  @click="orderForm.side = 'buy'"
                  :class="[
                    'px-4 py-2 rounded-lg font-medium transition-colors',
                    orderForm.side === 'buy'
                      ? 'bg-green-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  ]"
                >
                  Buy
                </button>
                <button
                  type="button"
                  @click="orderForm.side = 'sell'"
                  :class="[
                    'px-4 py-2 rounded-lg font-medium transition-colors',
                    orderForm.side === 'sell'
                      ? 'bg-red-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  ]"
                >
                  Sell
                </button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Price (USD)</label>
              <input
                v-model="orderForm.price"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                placeholder="0.00"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
              <input
                v-model="orderForm.amount"
                type="number"
                step="0.00000001"
                min="0"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                placeholder="0.00000000"
              />
            </div>

            <div class="bg-gray-50 p-3 rounded-lg text-sm space-y-1">
              <div class="flex justify-between">
                <span class="text-gray-600">Total Value:</span>
                <span class="font-medium">${{ totalValue }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Commission (1.5%):</span>
                <span class="font-medium">${{ commission }}</span>
              </div>
              <div class="flex justify-between border-t border-gray-200 pt-1">
                <span class="text-gray-700 font-medium">Total Cost:</span>
                <span class="font-bold">${{ totalCost }}</span>
              </div>
            </div>

            <div v-if="error" class="text-red-600 text-sm">{{ error }}</div>

            <button
              type="submit"
              :disabled="loading"
              :class="[
                'w-full py-2 rounded-lg font-medium transition-colors',
                orderForm.side === 'buy'
                  ? 'bg-green-600 hover:bg-green-700 text-white'
                  : 'bg-red-600 hover:bg-red-700 text-white',
                loading && 'opacity-50 cursor-not-allowed'
              ]"
            >
              {{ loading ? 'Placing Order...' : `Place ${orderForm.side === 'buy' ? 'Buy' : 'Sell'} Order` }}
            </button>
          </form>
        </div>

        <!-- Orderbook -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-xl font-semibold">Orderbook</h2>
              <select
                v-model="selectedSymbol"
                @change="fetchOrderbook"
                class="px-3 py-1 border border-gray-300 rounded-lg"
              >
                <option value="BTC">BTC</option>
                <option value="ETH">ETH</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- Buy Orders -->
              <div>
                <h3 class="text-sm font-semibold text-green-600 mb-2">Buy Orders</h3>
                <div class="space-y-1">
                  <div v-if="buyOrders.length === 0" class="text-sm text-gray-500 text-center py-4">
                    No buy orders
                  </div>
                  <div
                    v-for="order in buyOrders.slice(0, 10)"
                    :key="order.id"
                    class="flex justify-between text-sm bg-green-50 p-2 rounded"
                  >
                    <span class="font-medium">${{ parseFloat(order.price).toFixed(2) }}</span>
                    <span class="text-gray-600">{{ parseFloat(order.amount).toFixed(8) }}</span>
                  </div>
                </div>
              </div>

              <!-- Sell Orders -->
              <div>
                <h3 class="text-sm font-semibold text-red-600 mb-2">Sell Orders</h3>
                <div class="space-y-1">
                  <div v-if="sellOrders.length === 0" class="text-sm text-gray-500 text-center py-4">
                    No sell orders
                  </div>
                  <div
                    v-for="order in sellOrders.slice(0, 10)"
                    :key="order.id"
                    class="flex justify-between text-sm bg-red-50 p-2 rounded"
                  >
                    <span class="font-medium">${{ parseFloat(order.price).toFixed(2) }}</span>
                    <span class="text-gray-600">{{ parseFloat(order.amount).toFixed(8) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>