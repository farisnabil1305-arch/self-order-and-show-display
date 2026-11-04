<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Flame,
    CheckCircle2,
    Clock,
    Volume2,
    VolumeX,
    RotateCcw,
    UtensilsCrossed,
    ShoppingBag,
    Printer,
    FileText,
    Bell,
    Check,
    Store,
    Tv,
    Maximize,
    Minimize
} from 'lucide-vue-next';
import KitchenTicketPrint from '@/Components/KitchenTicketPrint.vue';

const props = defineProps({
    initialData: {
        type: Object,
        required: true,
    },
});

// Orders State
const orders = ref(props.initialData?.orders || []);
const counts = ref(props.initialData?.counts || { queued: 0, cooking: 0, ready: 0, completed_today: 0 });
const activeTab = ref('all'); // 'all' | 'queued' | 'cooking' | 'ready'
const isSyncing = ref(false);
const soundEnabled = ref(true);
const isFullscreen = ref(false);

// Thermal Slip Print
const printingOrder = ref(null);

// Clock
const currentTime = ref('');
function updateClock() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

// Elapsed timer calculator
function getElapsedMinutes(createdAt) {
    if (!createdAt) return 0;
    const diffMs = new Date() - new Date(createdAt);
    return Math.floor(diffMs / 60000);
}

function getElapsedFormatted(createdAt) {
    if (!createdAt) return '00:00';
    const diffSec = Math.floor((new Date() - new Date(createdAt)) / 1000);
    const m = Math.floor(diffSec / 60);
    const s = diffSec % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

// Sound notification
function playNewOrderBeep() {
    if (!soundEnabled.value) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // A5
        osc.frequency.setValueAtTime(1174.66, audioCtx.currentTime + 0.12); // D6
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.35);
    } catch (e) {
        // audio fail
    }
}

// Polling
let pollingInterval = null;
let clockInterval = null;
let lastKnownOrderCount = orders.value.length;

async function fetchOrdersSilently() {
    try {
        isSyncing.value = true;
        const res = await axios.get('/kitchen/api/orders');
        if (res.data?.success && res.data.data) {
            const newOrders = res.data.data.orders || [];
            if (newOrders.length > lastKnownOrderCount) {
                playNewOrderBeep();
            }
            lastKnownOrderCount = newOrders.length;
            orders.value = newOrders;
            counts.value = res.data.data.counts || counts.value;
        }
    } catch (e) {
        console.error('Kitchen sync error:', e);
    } finally {
        isSyncing.value = false;
    }
}

// Actions
async function startCooking(order) {
    try {
        await axios.post(`/kitchen/orders/${order.id}/start`);
        await fetchOrdersSilently();
    } catch (e) {
        alert('Gagal memulai masak.');
    }
}

async function markReady(order) {
    try {
        await axios.post(`/kitchen/orders/${order.id}/ready`);
        await fetchOrdersSilently();
    } catch (e) {
        alert('Gagal menandai siap saji.');
    }
}

async function markCompleted(order) {
    try {
        await axios.post(`/kitchen/orders/${order.id}/complete`);
        await fetchOrdersSilently();
    } catch (e) {
        alert('Gagal menyelesaikan pesanan.');
    }
}

async function recallOrder(order) {
    try {
        await axios.post(`/kitchen/orders/${order.id}/recall`);
        await fetchOrdersSilently();
    } catch (e) {
        alert('Gagal memanggil antrean.');
    }
}

function printKitchenSlip(order) {
    printingOrder.value = order;
    setTimeout(() => {
        window.print();
        setTimeout(() => {
            printingOrder.value = null;
        }, 500);
    }, 150);
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(() => {
            isFullscreen.value = true;
        }).catch(() => {});
    } else {
        document.exitFullscreen().then(() => {
            isFullscreen.value = false;
        }).catch(() => {});
    }
}

// Filtered Orders
const filteredOrders = computed(() => {
    if (activeTab.value === 'all') {
        return orders.value.filter((o) => o.kitchen_status !== 'completed');
    }
    return orders.value.filter((o) => o.kitchen_status === activeTab.value);
});

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
    pollingInterval = setInterval(fetchOrdersSilently, 3500);
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    if (pollingInterval) clearInterval(pollingInterval);
});
</script>

<template>
    <Head title="Kitchen Display System (KDS) - Layar Dapur" />

    <!-- Thermal Print Layout if staff clicks print -->
    <KitchenTicketPrint v-if="printingOrder" :order="printingOrder" paperSize="58mm" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans select-none overflow-x-hidden">
        <!-- Top App Bar -->
        <header class="bg-slate-900/90 border-b border-slate-800 px-4 sm:px-6 h-18 flex items-center justify-between shrink-0 sticky top-0 z-30 backdrop-blur-md">
            <!-- Brand & Station Name -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-600 to-amber-800 flex items-center justify-center text-white shadow-md shadow-amber-950/40">
                    <Flame class="w-6 h-6 animate-pulse" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-black tracking-tight text-white leading-tight">SBP KITCHEN DISPLAY</h1>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            Station Koki
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-400 mt-0.5 font-mono">
                        <span class="text-emerald-400 font-bold">{{ currentTime }}</span>
                        <span>•</span>
                        <span :class="isSyncing ? 'text-amber-400 animate-pulse' : 'text-slate-400'">
                            {{ isSyncing ? 'Sinkron...' : 'Live Realtime' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Stats Bar Pill Buttons -->
            <div class="hidden md:flex items-center gap-2 bg-slate-950/80 p-1.5 rounded-xl border border-slate-800 text-xs">
                <button
                    type="button"
                    @click="activeTab = 'all'"
                    class="px-3 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'all' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                >
                    <span>Semua Aktif</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/30 font-mono">{{ counts.queued + counts.cooking + counts.ready }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'queued'"
                    class="px-3 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'queued' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                >
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>Perlu Dimasak</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-950/60 text-amber-300 font-mono">{{ counts.queued }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'cooking'"
                    class="px-3 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'cooking' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                >
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-ping"></span>
                    <span>Sedang Dimasak</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-blue-950/60 text-blue-300 font-mono">{{ counts.cooking }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'ready'"
                    class="px-3 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'ready' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                >
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Siap Saji</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-emerald-950/60 text-emerald-300 font-mono">{{ counts.ready }}</span>
                </button>
            </div>

            <!-- Right Controls: Audio, Navigation, Fullscreen -->
            <div class="flex items-center gap-2">
                <!-- Sound Toggle -->
                <button
                    type="button"
                    @click="soundEnabled = !soundEnabled"
                    class="p-2 rounded-xl border border-slate-800 text-slate-300 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                    :title="soundEnabled ? 'Bunyi Notifikasi Aktif' : 'Bunyi Senyap'"
                >
                    <Volume2 v-if="soundEnabled" class="w-5 h-5 text-amber-400" />
                    <VolumeX v-else class="w-5 h-5 text-slate-500" />
                </button>

                <!-- Fullscreen Button -->
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="p-2 rounded-xl border border-slate-800 text-slate-300 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                    title="Layar Penuh Kiosk/TV"
                >
                    <Maximize v-if="!isFullscreen" class="w-5 h-5" />
                    <Minimize v-else class="w-5 h-5" />
                </button>

                <!-- Shortcut links to other screens -->
                <div class="h-6 w-px bg-slate-800 mx-1"></div>

                <a
                    href="/pos"
                    class="px-3 py-1.5 rounded-xl border border-slate-800 bg-slate-900 hover:bg-slate-800 text-xs font-bold text-slate-200 transition-colors flex items-center gap-1.5 cursor-pointer"
                    title="Buka Kasir POS"
                >
                    <Store class="w-4 h-4 text-amber-400" />
                    <span class="hidden lg:inline">Kasir POS</span>
                </a>

                <a
                    href="/display"
                    target="_blank"
                    class="px-3 py-1.5 rounded-xl border border-slate-800 bg-slate-900 hover:bg-slate-800 text-xs font-bold text-slate-200 transition-colors flex items-center gap-1.5 cursor-pointer"
                    title="Buka Layar TV Antrean"
                >
                    <Tv class="w-4 h-4 text-emerald-400" />
                    <span class="hidden lg:inline">Layar TV</span>
                </a>
            </div>
        </header>

        <!-- Main Cards Grid Area -->
        <main class="flex-1 p-4 sm:p-6 overflow-y-auto overscroll-contain">
            <!-- Empty State -->
            <div
                v-if="filteredOrders.length === 0"
                class="h-96 flex flex-col items-center justify-center text-center p-8 rounded-3xl border-2 border-dashed border-slate-800/80 bg-slate-900/30 text-slate-500 max-w-xl mx-auto my-12"
            >
                <div class="w-16 h-16 rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4 text-slate-400">
                    <CheckCircle2 class="w-9 h-9 stroke-[1.5]" />
                </div>
                <h3 class="text-lg font-bold text-slate-200">Semua Masakan Sudah Selesai!</h3>
                <p class="text-xs text-slate-400 mt-1 max-w-sm">
                    Tidak ada pesanan aktif pada filter ini. Pesanan baru dari Kiosk / Kasir akan otomatis muncul dan membunyikan bel.
                </p>
                <button
                    type="button"
                    @click="fetchOrdersSilently"
                    class="mt-4 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-white transition-colors flex items-center gap-1.5 cursor-pointer"
                >
                    <RotateCcw class="w-3.5 h-3.5" />
                    <span>Cek Ulang Pesanan</span>
                </button>
            </div>

            <!-- Orders Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-start">
                <div
                    v-for="order in filteredOrders"
                    :key="order.id"
                    class="rounded-2xl border flex flex-col justify-between transition-all duration-150 overflow-hidden shadow-lg"
                    :class="[
                        order.kitchen_status === 'cooking'
                            ? 'bg-slate-900/90 border-blue-500/50 ring-1 ring-blue-500/30'
                            : (order.kitchen_status === 'ready'
                                ? 'bg-slate-900/90 border-emerald-500/50 ring-1 ring-emerald-500/30'
                                : 'bg-slate-900/90 border-slate-800')
                    ]"
                >
                    <!-- Card Header: Queue Number + Table + Timer -->
                    <div class="p-3.5 border-b border-slate-800/80 bg-slate-950/60">
                        <div class="flex items-center justify-between gap-2">
                            <!-- Queue Number & Table -->
                            <div>
                                <div class="text-2xl font-black font-mono tracking-wider text-white">
                                    {{ order.order_number }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span
                                        class="px-2 py-0.5 rounded-md text-xs font-black uppercase flex items-center gap-1"
                                        :class="order.order_type === 'dine_in' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30'"
                                    >
                                        <UtensilsCrossed v-if="order.order_type === 'dine_in'" class="w-3.5 h-3.5" />
                                        <ShoppingBag v-else class="w-3.5 h-3.5" />
                                        <span>{{ order.order_type === 'dine_in' ? `Meja ${order.table_number || '-'}` : 'Bawa Pulang' }}</span>
                                    </span>
                                    <span class="text-xs text-slate-400 font-semibold truncate max-w-[100px]">
                                        {{ order.customer_name }}
                                    </span>
                                </div>
                            </div>

                            <!-- Cooking Timer & Status Badge -->
                            <div class="text-right flex flex-col items-end">
                                <!-- Elapsed timer -->
                                <div
                                    class="px-2.5 py-1 rounded-lg text-xs font-mono font-black flex items-center gap-1 border"
                                    :class="[
                                        getElapsedMinutes(order.created_at) >= 10
                                            ? 'bg-rose-500/20 text-rose-400 border-rose-500/50 animate-pulse'
                                            : (getElapsedMinutes(order.created_at) >= 5
                                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                                                : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30')
                                    ]"
                                    title="Waktu tunggu sejak pesanan masuk"
                                >
                                    <Clock class="w-3 h-3" />
                                    <span>{{ getElapsedFormatted(order.created_at) }}</span>
                                </div>

                                <!-- Status Badge -->
                                <span
                                    class="text-[10px] font-bold uppercase mt-1 px-1.5 py-0.2 rounded"
                                    :class="[
                                        order.kitchen_status === 'cooking'
                                            ? 'text-blue-400 bg-blue-500/10'
                                            : (order.kitchen_status === 'ready'
                                                ? 'text-emerald-400 bg-emerald-500/10'
                                                : 'text-amber-400 bg-amber-500/10')
                                    ]"
                                >
                                    {{
                                        order.kitchen_status === 'cooking'
                                            ? 'Sedang Dimasak'
                                            : (order.kitchen_status === 'ready' ? 'Siap Saji' : 'Antrean Masuk')
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Items List (Large & Clear for Cooks) -->
                    <div class="p-4 space-y-2.5 max-h-72 overflow-y-auto overscroll-contain divide-y divide-slate-800/50">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="pt-2 first:pt-0"
                        >
                            <div class="flex items-start gap-2">
                                <span class="w-7 h-7 rounded-lg bg-slate-800 text-white font-mono font-black text-sm flex items-center justify-center shrink-0 border border-slate-700">
                                    {{ item.quantity }}x
                                </span>
                                <div class="flex-1">
                                    <div class="font-bold text-sm text-slate-100 leading-snug">
                                        {{ item.product?.name || 'Menu' }}
                                    </div>
                                    <!-- High-visibility Kitchen Note -->
                                    <div
                                        v-if="item.notes"
                                        class="mt-1 px-2 py-0.5 rounded-md bg-amber-500/20 border border-amber-500/30 text-amber-300 text-xs font-bold leading-tight"
                                    >
                                        <span class="text-amber-400 uppercase text-[10px] font-black mr-1">NOTE:</span>
                                        <span>{{ item.notes }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="p-3 border-t border-slate-800/80 bg-slate-950/40 flex items-center gap-2">
                        <!-- Print Ticket button -->
                        <button
                            type="button"
                            @click="printKitchenSlip(order)"
                            class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors cursor-pointer shrink-0"
                            title="Cetak Tiket Kertas Thermal"
                        >
                            <Printer class="w-4 h-4" />
                        </button>

                        <!-- Primary Stage Action -->
                        <!-- If Queued: Mulai Masak -->
                        <button
                            v-if="order.kitchen_status === 'queued'"
                            type="button"
                            @click="startCooking(order)"
                            class="flex-1 h-11 rounded-xl bg-amber-600 hover:bg-amber-500 active:bg-amber-700 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-colors cursor-pointer"
                        >
                            <Flame class="w-4 h-4" />
                            <span>Mulai Masak</span>
                        </button>

                        <!-- If Cooking: Siap Saji / Panggil -->
                        <button
                            v-else-if="order.kitchen_status === 'cooking'"
                            type="button"
                            @click="markReady(order)"
                            class="flex-1 h-11 rounded-xl bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-colors cursor-pointer"
                        >
                            <CheckCircle2 class="w-4 h-4" />
                            <span>Siap Saji & Panggil</span>
                        </button>

                        <!-- If Ready: Panggil Ulang & Selesai -->
                        <template v-else-if="order.kitchen_status === 'ready'">
                            <button
                                type="button"
                                @click="recallOrder(order)"
                                class="px-3 h-11 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-500/30 font-bold text-xs flex items-center justify-center gap-1 transition-colors cursor-pointer"
                                title="Panggil Ulang ke Layar TV"
                            >
                                <Bell class="w-3.5 h-3.5" />
                                <span>Panggil</span>
                            </button>

                            <button
                                type="button"
                                @click="markCompleted(order)"
                                class="flex-1 h-11 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition-colors cursor-pointer"
                            >
                                <Check class="w-4 h-4 stroke-[2.5]" />
                                <span>Selesai / Diambil</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
