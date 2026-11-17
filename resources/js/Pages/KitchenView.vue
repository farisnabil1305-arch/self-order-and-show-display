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
import { thermalPrinter } from '@/Utils/thermalPrinter';

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
const kitchenPrinterSettings = ref(thermalPrinter.loadSettings('pos_printer_settings'));

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
    <Head title="Layar Dapur & Racikan - Soto Betawi Parsoey" />

    <!-- Thermal Print Layout if staff clicks print -->
    <KitchenTicketPrint
        v-if="printingOrder"
        :order="printingOrder"
        :paperSize="kitchenPrinterSettings.paperSize || '58mm'"
        :storeSettings="initialData.store_settings"
    />

    <div class="min-h-screen bg-[#FAF7F2] text-[#231812] flex flex-col font-sans select-none overflow-x-hidden">
        <!-- Top App Bar (Warm, Authentic Culinary Header matching Self Order) -->
        <header class="bg-[#FAF7F2]/95 backdrop-blur-md border-b border-[#E8DFD1] px-4 sm:px-6 h-20 flex items-center justify-between shrink-0 sticky top-0 z-30">
            <!-- Brand & Station Identity -->
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-[#231812] flex items-center justify-center text-[#F5EBE1] font-serif font-black text-base tracking-wider shadow-sm border border-[#3D281F]">
                    SBP
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-serif text-lg sm:text-xl font-bold text-[#231812] tracking-tight leading-tight">
                            Soto Betawi Parsoey
                        </h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#EFE8DE] text-[#7A6A60] border border-[#E8DFD1]">
                            Layar Dapur
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-[#7A6A60] mt-0.5 font-medium">
                        <span class="font-mono text-[#231812] font-bold">{{ currentTime }}</span>
                        <span>•</span>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-[#15803D]">
                            <span class="w-2 h-2 rounded-full bg-[#15803D] inline-block"></span>
                            <span>{{ isSyncing ? 'Sinkron...' : 'Live Realtime' }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Segmented Filter Control (Clean, Native POS Style - NO AI Neon Glow) -->
            <div class="hidden md:flex items-center gap-1 bg-[#EFE8DE] p-1 rounded-xl border border-[#E8DFD1] text-xs">
                <button
                    type="button"
                    @click="activeTab = 'all'"
                    class="px-3.5 py-2 rounded-lg font-bold transition-all flex items-center gap-2 cursor-pointer"
                    :class="activeTab === 'all' ? 'bg-white text-[#231812] shadow-xs' : 'text-[#7A6A60] hover:text-[#231812]'"
                >
                    <span>Semua Aktif</span>
                    <span
                        class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold"
                        :class="activeTab === 'all' ? 'bg-[#231812] text-white' : 'bg-[#E8DFD1] text-[#5C4A3E]'"
                    >
                        {{ counts.queued + counts.cooking + counts.ready }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'queued'"
                    class="px-3.5 py-2 rounded-lg font-bold transition-all flex items-center gap-2 cursor-pointer"
                    :class="activeTab === 'queued' ? 'bg-white text-[#231812] shadow-xs' : 'text-[#7A6A60] hover:text-[#231812]'"
                >
                    <span class="w-2 h-2 rounded-full bg-[#D97706]"></span>
                    <span>Perlu Dimasak</span>
                    <span
                        class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold"
                        :class="activeTab === 'queued' ? 'bg-[#D97706] text-white' : 'bg-[#E8DFD1] text-[#5C4A3E]'"
                    >
                        {{ counts.queued }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'cooking'"
                    class="px-3.5 py-2 rounded-lg font-bold transition-all flex items-center gap-2 cursor-pointer"
                    :class="activeTab === 'cooking' ? 'bg-white text-[#231812] shadow-xs' : 'text-[#7A6A60] hover:text-[#231812]'"
                >
                    <span class="w-2 h-2 rounded-full bg-[#2563EB]"></span>
                    <span>Sedang Dimasak</span>
                    <span
                        class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold"
                        :class="activeTab === 'cooking' ? 'bg-[#2563EB] text-white' : 'bg-[#E8DFD1] text-[#5C4A3E]'"
                    >
                        {{ counts.cooking }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'ready'"
                    class="px-3.5 py-2 rounded-lg font-bold transition-all flex items-center gap-2 cursor-pointer"
                    :class="activeTab === 'ready' ? 'bg-white text-[#231812] shadow-xs' : 'text-[#7A6A60] hover:text-[#231812]'"
                >
                    <span class="w-2 h-2 rounded-full bg-[#15803D]"></span>
                    <span>Siap Saji</span>
                    <span
                        class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold"
                        :class="activeTab === 'ready' ? 'bg-[#15803D] text-white' : 'bg-[#E8DFD1] text-[#5C4A3E]'"
                    >
                        {{ counts.ready }}
                    </span>
                </button>
            </div>

            <!-- Right Controls: Audio, Fullscreen, Navigation Shortcuts -->
            <div class="flex items-center gap-2">
                <!-- Sound Toggle -->
                <button
                    type="button"
                    @click="soundEnabled = !soundEnabled"
                    class="p-2.5 rounded-xl border border-[#E8DFD1] bg-white text-[#5C4A3E] hover:text-[#231812] hover:bg-[#FAF7F2] transition-colors cursor-pointer shadow-2xs"
                    :title="soundEnabled ? 'Bunyi Notifikasi Aktif' : 'Bunyi Senyap'"
                >
                    <Volume2 v-if="soundEnabled" class="w-4 h-4 text-[#A8421E]" />
                    <VolumeX v-else class="w-4 h-4 text-[#7A6A60]" />
                </button>

                <!-- Fullscreen Button -->
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="p-2.5 rounded-xl border border-[#E8DFD1] bg-white text-[#5C4A3E] hover:text-[#231812] hover:bg-[#FAF7F2] transition-colors cursor-pointer shadow-2xs"
                    title="Layar Penuh KDS"
                >
                    <Maximize v-if="!isFullscreen" class="w-4 h-4" />
                    <Minimize v-else class="w-4 h-4" />
                </button>

                <div class="h-6 w-px bg-[#E8DFD1] mx-1"></div>

                <!-- Kasir POS Link -->
                <a
                    href="/pos"
                    class="px-3 py-2 rounded-xl border border-[#E8DFD1] bg-white hover:bg-[#FAF7F2] text-xs font-bold text-[#231812] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                    title="Buka Kasir POS"
                >
                    <Store class="w-4 h-4 text-[#A8421E]" />
                    <span class="hidden lg:inline">Kasir POS</span>
                </a>

                <!-- Layar TV Link -->
                <a
                    href="/display"
                    target="_blank"
                    class="px-3 py-2 rounded-xl border border-[#E8DFD1] bg-white hover:bg-[#FAF7F2] text-xs font-bold text-[#231812] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                    title="Buka Layar TV Antrean"
                >
                    <Tv class="w-4 h-4 text-[#15803D]" />
                    <span class="hidden lg:inline">Layar TV</span>
                </a>
            </div>
        </header>

        <!-- Main Cards Grid Area -->
        <main class="flex-1 p-4 sm:p-6 overflow-y-auto overscroll-contain">
            <!-- Refined, Authentic Empty State (NO AI Dashed Box, NO Robotic Clichés) -->
            <div
                v-if="filteredOrders.length === 0"
                class="min-h-[50vh] flex flex-col items-center justify-center text-center p-8 max-w-md mx-auto my-12"
            >
                <div class="w-16 h-16 rounded-2xl bg-[#EFE8DE] text-[#7A6A60] flex items-center justify-center mb-4 border border-[#E8DFD1]">
                    <UtensilsCrossed class="w-8 h-8 text-[#A8421E]" />
                </div>
                <h3 class="font-serif text-2xl font-bold text-[#231812] tracking-tight">Dapur Siap Melayani</h3>
                <p class="text-xs text-[#7A6A60] mt-2 leading-relaxed">
                    Belum ada antrean masakan yang menunggu saat ini. Setiap pesanan baru dari Kiosk atau Kasir akan otomatis muncul dan berbunyi di sini.
                </p>
                <button
                    type="button"
                    @click="fetchOrdersSilently"
                    class="mt-5 px-4 py-2 rounded-xl bg-white border border-[#E8DFD1] text-xs font-semibold text-[#5C4A3E] hover:text-[#231812] hover:bg-[#FAF7F2] shadow-2xs transition-colors flex items-center gap-2 cursor-pointer"
                >
                    <RotateCcw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }" />
                    <span>Periksa Antrean</span>
                </button>
            </div>

            <!-- Orders Grid: Physical Ticket Board Aesthetics -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-start">
                <div
                    v-for="order in filteredOrders"
                    :key="order.id"
                    class="bg-white rounded-2xl border flex flex-col justify-between transition-all duration-150 overflow-hidden shadow-xs hover:shadow-md"
                    :class="[
                        order.kitchen_status === 'cooking'
                            ? 'border-[#2563EB]/40 ring-2 ring-[#2563EB]/10'
                            : (order.kitchen_status === 'ready'
                                ? 'border-[#15803D]/40 ring-2 ring-[#15803D]/10'
                                : 'border-[#E8DFD1]')
                    ]"
                >
                    <!-- Ticket Header: Order Number + Table / Bawa Pulang + Timer -->
                    <div class="p-4 border-b border-[#E8DFD1] bg-[#FAF7F2]/60">
                        <div class="flex items-center justify-between gap-2">
                            <!-- Queue Number & Service Type -->
                            <div>
                                <div class="text-2xl font-black font-mono tracking-wider text-[#231812]">
                                    {{ order.order_number }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span
                                        class="px-2.5 py-0.5 rounded-md text-xs font-bold flex items-center gap-1 border"
                                        :class="order.order_type === 'dine_in' ? 'bg-[#FAF7F2] text-[#A8421E] border-[#E8DFD1]' : 'bg-[#FAF7F2] text-[#5C4A3E] border-[#E8DFD1]'"
                                    >
                                        <UtensilsCrossed v-if="order.order_type === 'dine_in'" class="w-3.5 h-3.5 text-[#A8421E]" />
                                        <ShoppingBag v-else class="w-3.5 h-3.5 text-[#7A6A60]" />
                                        <span>{{ order.order_type === 'dine_in' ? `Meja ${order.table_number || '-'}` : 'Bawa Pulang' }}</span>
                                    </span>
                                    <span class="text-xs text-[#5C4A3E] font-semibold truncate max-w-[110px]">
                                        {{ order.customer_name }}
                                    </span>
                                </div>
                            </div>

                            <!-- Cooking Timer & Stage Badge -->
                            <div class="text-right flex flex-col items-end">
                                <!-- Elapsed timer -->
                                <div
                                    class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold flex items-center gap-1 border"
                                    :class="[
                                        getElapsedMinutes(order.created_at) >= 10
                                            ? 'bg-rose-50 text-rose-700 border-rose-200'
                                            : (getElapsedMinutes(order.created_at) >= 5
                                                ? 'bg-amber-50 text-amber-800 border-amber-200'
                                                : 'bg-emerald-50 text-emerald-800 border-emerald-200')
                                    ]"
                                    title="Waktu tunggu sejak pesanan masuk"
                                >
                                    <Clock class="w-3.5 h-3.5" />
                                    <span>{{ getElapsedFormatted(order.created_at) }}</span>
                                </div>

                                <!-- Status Badge -->
                                <span
                                    class="text-[10px] font-bold uppercase mt-1.5 px-2 py-0.5 rounded-md border"
                                    :class="[
                                        order.kitchen_status === 'cooking'
                                            ? 'text-blue-800 bg-blue-50 border-blue-200'
                                            : (order.kitchen_status === 'ready'
                                                ? 'text-emerald-800 bg-emerald-50 border-emerald-200'
                                                : 'text-amber-800 bg-amber-50 border-amber-200')
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

                    <!-- Items List (High-contrast, Large & Clear for Busy Cooks) -->
                    <div class="p-4 space-y-3 max-h-72 overflow-y-auto overscroll-contain divide-y divide-[#E8DFD1]/60">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="pt-2.5 first:pt-0"
                        >
                            <div class="flex items-start gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-[#231812] text-white font-mono font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ item.quantity }}x
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-sm text-[#231812] leading-snug">
                                        {{ item.product?.name || 'Menu' }}
                                    </div>
                                    <!-- High-visibility Kitchen Note (Sambal dipisah, dll) -->
                                    <div
                                        v-if="item.notes"
                                        class="mt-1.5 px-2.5 py-1 rounded-md bg-[#FEF3C7] border border-[#FDE68A] text-[#92400E] text-xs font-bold leading-tight"
                                    >
                                        <span class="text-[#B45309] uppercase text-[10px] font-black mr-1">CATATAN:</span>
                                        <span>{{ item.notes }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer Actions (Tactile, Clear Touch Targets) -->
                    <div class="p-3 border-t border-[#E8DFD1] bg-[#FAF7F2]/40 flex items-center gap-2">
                        <!-- Print Ticket thermal button -->
                        <button
                            type="button"
                            @click="printKitchenSlip(order)"
                            class="p-2.5 rounded-xl border border-[#E8DFD1] bg-white hover:bg-[#FAF7F2] text-[#7A6A60] hover:text-[#231812] transition-colors cursor-pointer shrink-0 shadow-2xs"
                            title="Cetak Tiket Kertas Dapur"
                        >
                            <Printer class="w-4 h-4" />
                        </button>

                        <!-- Primary Stage Action -->
                        <!-- If Queued: Mulai Masak -->
                        <button
                            v-if="order.kitchen_status === 'queued'"
                            type="button"
                            @click="startCooking(order)"
                            class="flex-1 h-11 rounded-xl bg-[#A8421E] hover:bg-[#8F3314] active:scale-[0.99] text-white font-bold text-xs flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                        >
                            <Flame class="w-4 h-4" />
                            <span>Mulai Masak</span>
                        </button>

                        <!-- If Cooking: Siap Saji / Panggil -->
                        <button
                            v-else-if="order.kitchen_status === 'cooking'"
                            type="button"
                            @click="markReady(order)"
                            class="flex-1 h-11 rounded-xl bg-[#15803D] hover:bg-[#166534] active:scale-[0.99] text-white font-bold text-xs flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                        >
                            <CheckCircle2 class="w-4 h-4" />
                            <span>Siap Saji & Panggil</span>
                        </button>

                        <!-- If Ready: Panggil Ulang & Selesai -->
                        <template v-else-if="order.kitchen_status === 'ready'">
                            <button
                                type="button"
                                @click="recallOrder(order)"
                                class="px-3 h-11 rounded-xl bg-white hover:bg-[#FAF7F2] text-[#231812] border border-[#E8DFD1] font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer shadow-2xs"
                                title="Panggil Ulang ke Layar TV"
                            >
                                <Bell class="w-3.5 h-3.5 text-[#A8421E]" />
                                <span>Panggil</span>
                            </button>

                            <button
                                type="button"
                                @click="markCompleted(order)"
                                class="flex-1 h-11 rounded-xl bg-[#231812] hover:bg-[#3D281F] active:scale-[0.99] text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-xs transition-colors cursor-pointer"
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
