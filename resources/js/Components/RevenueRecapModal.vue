<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { formatRupiah, formatDateTime } from '@/Utils/formatters';
import {
    BarChart3,
    TrendingUp,
    Calendar,
    CalendarDays,
    CalendarRange,
    Banknote,
    QrCode,
    UtensilsCrossed,
    ShoppingBag,
    Printer,
    X,
    Award,
    RotateCcw,
    Clock,
    CheckCircle2,
    Download,
    FileSpreadsheet
} from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'print-zreport']);

// Active Filter: 'day' | 'week' | 'month'
const activePeriod = ref('day');
const isLoading = ref(false);
const recapData = ref(null);
const printedAt = ref(new Date());

function downloadCsv(period = activePeriod.value) {
    let p = 'today';
    if (period === 'week') p = 'week';
    if (period === 'month') p = 'month';
    window.location.href = `/pos/reports/export-csv?period=${p}`;
}

async function fetchRecap(period = activePeriod.value) {
    activePeriod.value = period;
    isLoading.value = true;
    try {
        const response = await axios.get(`/pos/api/revenue-recap?period=${period}`);
        if (response.data && response.data.success) {
            recapData.value = response.data;
        }
    } catch (e) {
        console.error('Gagal memuat rekap omset:', e);
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            printedAt.value = new Date();
            fetchRecap(activePeriod.value);
        }
    }
);

// Active period metrics
const currentMetrics = computed(() => {
    if (!recapData.value?.summary) return null;
    return recapData.value.summary[activePeriod.value] || null;
});

// Period label text
const periodLabel = computed(() => {
    switch (activePeriod.value) {
        case 'week':
            return 'Minggu Ini';
        case 'month':
            return 'Bulan Ini';
        default:
            return 'Hari Ini';
    }
});

// Max revenue in breakdown for relative bar calculations
const maxBreakdownRevenue = computed(() => {
    if (!recapData.value?.breakdown || !Array.isArray(recapData.value.breakdown)) return 1;
    const revenues = recapData.value.breakdown.map((item) => item.revenue || 0);
    return Math.max(...revenues, 1);
});

// Print Thermal Recap Slip
function printRecapSlip() {
    printedAt.value = new Date();
    setTimeout(() => {
        window.print();
    }, 200);
}
</script>

<template>
    <!-- Hidden Thermal Print Layout for Recap (Visible during print) -->
    <div v-if="show && recapData && currentMetrics" class="hidden print:block fixed inset-0 z-[99999] bg-white text-black p-3 font-mono text-[11px] leading-tight">
        <div class="w-[72mm] mx-auto text-center">
            <!-- Header -->
            <div class="pb-1">
                <div class="text-sm font-black uppercase tracking-wider">SOTO BETAWI PARSOEY</div>
                <div class="text-[10px]">Laporan Rekap Penjualan POS</div>
                <div class="text-[9px]">Terminal Kasir 01</div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <div class="text-left text-[10px] space-y-0.5 py-1">
                <div class="flex justify-between">
                    <span>Periode:</span>
                    <span class="font-bold uppercase">{{ periodLabel }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Waktu Cetak:</span>
                    <span>{{ formatDateTime(printedAt) }}</span>
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Big Revenue -->
            <div class="py-2">
                <div class="text-[10px] uppercase font-semibold">TOTAL OMSET:</div>
                <div class="text-2xl font-black font-mono my-1">
                    {{ formatRupiah(currentMetrics.total_revenue) }}
                </div>
                <div class="text-xs font-bold">
                    {{ currentMetrics.total_orders }} Transaksi Berhasil
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Metric Summary -->
            <div class="text-left text-[10px] space-y-1 py-1">
                <div class="flex justify-between">
                    <span>Rata-rata/Trx:</span>
                    <span>{{ formatRupiah(currentMetrics.average_order) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Tunai (Cash):</span>
                    <span>{{ formatRupiah(currentMetrics.cash_revenue) }} ({{ currentMetrics.cash_count }}x)</span>
                </div>
                <div class="flex justify-between">
                    <span>QRIS / Non-Tunai:</span>
                    <span>{{ formatRupiah(currentMetrics.qris_revenue) }} ({{ currentMetrics.qris_count }}x)</span>
                </div>
                <div class="flex justify-between">
                    <span>Makan Di Tempat:</span>
                    <span>{{ currentMetrics.dine_in_count }} Pesanan</span>
                </div>
                <div class="flex justify-between">
                    <span>Bawa Pulang:</span>
                    <span>{{ currentMetrics.takeaway_count }} Pesanan</span>
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Top Products -->
            <div v-if="recapData.top_products && recapData.top_products.length > 0" class="text-left py-1">
                <div class="text-[10px] font-bold uppercase pb-1">5 MENU TERLARIS:</div>
                <div v-for="(prod, idx) in recapData.top_products" :key="prod.id" class="text-[10px] flex justify-between py-0.5">
                    <span class="truncate pr-1">{{ idx + 1 }}. {{ prod.name }} ({{ prod.total_qty }}x)</span>
                    <span class="font-mono">{{ formatRupiah(prod.total_revenue) }}</span>
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <div class="pt-2 text-[9px] text-center">
                *** LAPORAN TUTUP BUKU RESMI ***<br />
                Soto Betawi Parsoey POS System
            </div>
        </div>
    </div>

    <!-- Screen Modal (Hidden on Print) -->
    <div
        v-if="show"
        class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-3 sm:p-5 backdrop-blur-xs print:hidden animate-in fade-in duration-150"
        @click.self="emit('close')"
    >
        <div class="bg-white rounded-2xl border border-slate-200 max-w-4xl w-full max-h-[92vh] flex flex-col shadow-xl overflow-hidden">
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 border-b border-[#E8DFD1] bg-[#FAF7F2] flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#231812] text-[#F5EBE1] flex items-center justify-center border border-[#3D281F]">
                        <BarChart3 class="w-5 h-5 text-[#F5EBE1]" />
                    </div>
                    <div>
                        <h2 class="font-serif text-base sm:text-lg font-bold text-[#231812]">Rekap Omset & Laporan Penjualan</h2>
                        <p class="text-xs text-[#7A6A60]">Ringkasan pendapatan kasir harian, mingguan, dan bulanan</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="fetchRecap(activePeriod)"
                        class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        title="Segarkan Rekap"
                    >
                        <RotateCcw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
                    </button>
                    <button
                        type="button"
                        @click="emit('close')"
                        class="p-2 rounded-lg border border-slate-200 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                        title="Tutup"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Period Switcher Tabs -->
            <div class="px-4 sm:px-6 pt-4 pb-2 border-b border-slate-100 bg-white shrink-0">
                <div class="grid grid-cols-3 gap-2 p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-bold">
                    <button
                        type="button"
                        @click="fetchRecap('day')"
                        class="py-2.5 px-3 rounded-lg flex items-center justify-center gap-2 transition-all cursor-pointer"
                        :class="activePeriod === 'day' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <Calendar class="w-4 h-4 text-amber-600" />
                        <span>Harian (Hari Ini)</span>
                    </button>
                    <button
                        type="button"
                        @click="fetchRecap('week')"
                        class="py-2.5 px-3 rounded-lg flex items-center justify-center gap-2 transition-all cursor-pointer"
                        :class="activePeriod === 'week' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <CalendarDays class="w-4 h-4 text-amber-600" />
                        <span>Mingguan (Minggu Ini)</span>
                    </button>
                    <button
                        type="button"
                        @click="fetchRecap('month')"
                        class="py-2.5 px-3 rounded-lg flex items-center justify-center gap-2 transition-all cursor-pointer"
                        :class="activePeriod === 'month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <CalendarRange class="w-4 h-4 text-amber-600" />
                        <span>Bulanan (Bulan Ini)</span>
                    </button>
                </div>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="flex-1 overflow-y-auto overscroll-contain p-4 sm:p-6 space-y-6">
                <!-- Loading State -->
                <div v-if="isLoading && !recapData" class="py-20 text-center text-slate-400">
                    <RotateCcw class="w-8 h-8 mx-auto animate-spin mb-2 text-amber-600" />
                    <p class="text-xs font-medium">Memuat data omset {{ periodLabel }}...</p>
                </div>

                <template v-else-if="currentMetrics">
                    <!-- 1. Key Metrics 4-Cards Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Card 1: Total Omset -->
                        <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200/80">
                            <span class="text-[11px] font-bold text-amber-800 uppercase tracking-wider block">
                                Total Omset {{ periodLabel }}
                            </span>
                            <div class="text-2xl font-black font-mono text-slate-900 mt-1">
                                {{ formatRupiah(currentMetrics.total_revenue) }}
                            </div>
                            <div class="text-xs text-amber-900 font-semibold mt-1 flex items-center gap-1">
                                <CheckCircle2 class="w-3.5 h-3.5 text-amber-600" />
                                <span>{{ currentMetrics.total_orders }} Transaksi Lunas</span>
                            </div>
                        </div>

                        <!-- Card 2: Average Order -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                Rata-rata per Transaksi
                            </span>
                            <div class="text-2xl font-black font-mono text-slate-900 mt-1">
                                {{ formatRupiah(currentMetrics.average_order) }}
                            </div>
                            <div class="text-xs text-slate-500 font-medium mt-1">
                                Nilai keranjang pelanggan
                            </div>
                        </div>

                        <!-- Card 3: Payment Split (Cash vs QRIS) -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                Pecahan Pembayaran
                            </span>
                            <div class="mt-2 space-y-1 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="flex items-center gap-1 text-slate-600 font-medium">
                                        <Banknote class="w-3.5 h-3.5 text-emerald-600" /> Tunai:
                                    </span>
                                    <span class="font-bold text-slate-900 font-mono">{{ formatRupiah(currentMetrics.cash_revenue) }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="flex items-center gap-1 text-slate-600 font-medium">
                                        <QrCode class="w-3.5 h-3.5 text-blue-600" /> QRIS:
                                    </span>
                                    <span class="font-bold text-slate-900 font-mono">{{ formatRupiah(currentMetrics.qris_revenue) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 4: Order Type Split -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                Tipe Konsumsi
                            </span>
                            <div class="mt-2 space-y-1 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="flex items-center gap-1 text-slate-600 font-medium">
                                        <UtensilsCrossed class="w-3.5 h-3.5 text-amber-600" /> Makan Tempat:
                                    </span>
                                    <span class="font-bold text-slate-900">{{ currentMetrics.dine_in_count }} Order</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="flex items-center gap-1 text-slate-600 font-medium">
                                        <ShoppingBag class="w-3.5 h-3.5 text-amber-600" /> Bawa Pulang:
                                    </span>
                                    <span class="font-bold text-slate-900">{{ currentMetrics.takeaway_count }} Order</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Breakdown Section & Top Products (2 Columns on Desktop) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        <!-- Left Column: Period Specific Breakdown (7 Columns) -->
                        <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-4 sm:p-5">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                                <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                    <TrendingUp class="w-4 h-4 text-amber-600" />
                                    <span v-if="activePeriod === 'day'">Rincian Transaksi Hari Ini</span>
                                    <span v-else-if="activePeriod === 'week'">Distribusi Omset Mingguan (Senin - Minggu)</span>
                                    <span v-else>Grafik Penjualan Harian Bulan Ini</span>
                                </h3>
                                <span class="text-[11px] font-semibold text-slate-400">
                                    {{ periodLabel }}
                                </span>
                            </div>

                            <!-- DAILY BREAKDOWN: List of today's transactions -->
                            <div v-if="activePeriod === 'day'" class="space-y-2">
                                <div v-if="!recapData.breakdown || recapData.breakdown.length === 0" class="py-8 text-center text-slate-400 text-xs">
                                    Belum ada transaksi lunas hari ini.
                                </div>
                                <div v-else class="max-h-72 overflow-y-auto overscroll-contain divide-y divide-slate-100 pr-1">
                                    <div
                                        v-for="item in recapData.breakdown"
                                        :key="item.id"
                                        class="py-2.5 flex items-center justify-between text-xs"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="font-mono text-[11px] text-slate-400 flex items-center gap-1">
                                                <Clock class="w-3 h-3" />
                                                {{ item.time }}
                                            </span>
                                            <div>
                                                <div class="font-bold text-slate-900 font-mono">{{ item.order_number }}</div>
                                                <div class="text-[11px] text-slate-500">
                                                    {{ item.customer_name }} •
                                                    <span class="capitalize">{{ item.order_type === 'dine_in' ? `Meja ${item.table_number || '-'}` : 'Bawa Pulang' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <div class="font-bold font-mono text-slate-900">{{ formatRupiah(item.total_amount) }}</div>
                                            <span
                                                class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                                :class="item.payment_method === 'cash' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700'"
                                            >
                                                {{ item.payment_method }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- WEEKLY BREAKDOWN: 7 Days (Mon-Sun) with progress bar -->
                            <div v-else-if="activePeriod === 'week'" class="space-y-3">
                                <div
                                    v-for="day in recapData.breakdown"
                                    :key="day.date"
                                    class="p-2.5 rounded-lg border transition-colors text-xs"
                                    :class="day.is_today ? 'bg-amber-50/50 border-amber-300' : 'bg-slate-50 border-slate-200'"
                                >
                                    <div class="flex items-center justify-between mb-1.5 font-medium">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900">{{ day.day_name }}</span>
                                            <span class="text-slate-400 font-mono text-[11px]">{{ day.date_formatted }}</span>
                                            <span v-if="day.is_today" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-600 text-white">
                                                Hari Ini
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-[11px] text-slate-500">{{ day.order_count }} Order</span>
                                            <span class="font-bold font-mono text-slate-900">{{ formatRupiah(day.revenue) }}</span>
                                        </div>
                                    </div>

                                    <!-- Relative Progress Bar -->
                                    <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-300"
                                            :class="day.is_today ? 'bg-amber-500' : 'bg-slate-700'"
                                            :style="{ width: `${Math.max((day.revenue / maxBreakdownRevenue) * 100, day.revenue > 0 ? 5 : 0)}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <!-- MONTHLY BREAKDOWN: Days of month scrollable list -->
                            <div v-else class="space-y-2">
                                <div class="max-h-72 overflow-y-auto overscroll-contain space-y-1.5 pr-1">
                                    <div
                                        v-for="day in recapData.breakdown"
                                        :key="day.date"
                                        class="p-2 rounded-lg border flex items-center justify-between text-xs"
                                        :class="day.is_today ? 'bg-amber-50 border-amber-300' : 'bg-slate-50/70 border-slate-200'"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold w-6 text-center text-slate-600">{{ day.day_number }}</span>
                                            <span class="font-semibold text-slate-800">{{ day.day_name }}</span>
                                            <span v-if="day.is_today" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-600 text-white">
                                                Hari Ini
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <span class="text-[11px] text-slate-500">{{ day.order_count }} Trx</span>
                                            <span class="font-bold font-mono text-slate-900">{{ formatRupiah(day.revenue) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Top 5 Best Selling Items (5 Columns) -->
                        <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 p-4 sm:p-5">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                                <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                    <Award class="w-4 h-4 text-amber-600" />
                                    <span>5 Menu Terlaris</span>
                                </h3>
                                <span class="text-[11px] font-semibold text-slate-400">Periode Ini</span>
                            </div>

                            <div v-if="!recapData.top_products || recapData.top_products.length === 0" class="py-8 text-center text-slate-400 text-xs">
                                Belum ada data penjualan menu pada periode ini.
                            </div>
                            <div v-else class="space-y-2.5">
                                <div
                                    v-for="(prod, idx) in recapData.top_products"
                                    :key="prod.id"
                                    class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/50 flex items-center justify-between text-xs"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <!-- Rank Badge -->
                                        <div
                                            class="w-6 h-6 rounded-md font-bold font-mono flex items-center justify-center text-xs shrink-0"
                                            :class="[
                                                idx === 0 ? 'bg-amber-500 text-white' :
                                                idx === 1 ? 'bg-slate-300 text-slate-800' :
                                                idx === 2 ? 'bg-amber-700/80 text-white' :
                                                'bg-slate-200 text-slate-600'
                                            ]"
                                        >
                                            #{{ idx + 1 }}
                                        </div>

                                        <!-- Mini Image -->
                                        <img
                                            v-if="prod.image_url"
                                            :src="prod.image_url"
                                            :alt="prod.name"
                                            class="w-8 h-8 rounded-lg object-cover bg-slate-100 shrink-0 border border-slate-200"
                                        />

                                        <div>
                                            <div class="font-bold text-slate-900 leading-tight truncate max-w-[140px] sm:max-w-[170px]">
                                                {{ prod.name }}
                                            </div>
                                            <div class="text-[10px] text-slate-400">
                                                {{ prod.category_name }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right shrink-0">
                                        <div class="font-bold text-amber-700 font-mono">
                                            {{ prod.total_qty }} porsi
                                        </div>
                                        <div class="text-[11px] font-mono text-slate-600">
                                            {{ formatRupiah(prod.total_revenue) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-slate-200 bg-slate-50/80 flex flex-wrap items-center justify-between gap-2 shrink-0">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Download CSV Button -->
                    <button
                        type="button"
                        @click="downloadCsv(activePeriod)"
                        class="py-2.5 px-3.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 text-white font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                        title="Unduh laporan penjualan format spreadsheet Excel / CSV"
                    >
                        <FileSpreadsheet class="w-4 h-4 text-emerald-200" />
                        <span>Unduh Excel (.csv)</span>
                    </button>

                    <!-- Print Z-Report Thermal Button -->
                    <button
                        type="button"
                        @click="emit('print-zreport')"
                        class="py-2.5 px-3.5 rounded-xl bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                        title="Cetak struk resmi tutup kasir shift (Z-Report) ke printer thermal"
                    >
                        <Printer class="w-4 h-4 text-amber-200" />
                        <span>Cetak Tutup Kasir (Z-Report)</span>
                    </button>

                    <!-- Thermal Print Recap Slip -->
                    <button
                        type="button"
                        @click="printRecapSlip"
                        :disabled="isLoading || !currentMetrics || currentMetrics.total_orders === 0"
                        class="py-2.5 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 active:bg-slate-950 disabled:opacity-40 disabled:pointer-events-none text-white font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                        title="Cetak struk rekap penjualan ringkas"
                    >
                        <Printer class="w-4 h-4 text-slate-300" />
                        <span>Slip Ringkas</span>
                    </button>
                </div>

                <button
                    type="button"
                    @click="emit('close')"
                    class="py-2.5 px-5 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</template>
