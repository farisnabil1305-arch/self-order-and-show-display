<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import {
    LayoutGrid,
    CheckCircle2,
    Users,
    Clock,
    X,
    RotateCcw,
    AlertCircle,
    UtensilsCrossed,
    Check,
    Coffee
} from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    tables: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'updated']);

const isLoading = ref(false);
const alertMsg = ref('');
const alertType = ref('success');

function showAlert(msg, type = 'success') {
    alertMsg.value = msg;
    alertType.value = type;
    setTimeout(() => {
        if (alertMsg.value === msg) alertMsg.value = '';
    }, 4000);
}

const availableCount = computed(() => {
    return props.tables.filter((t) => t.status === 'available').length;
});

const occupiedCount = computed(() => {
    return props.tables.filter((t) => t.status === 'occupied').length;
});

async function releaseTable(table) {
    isLoading.value = true;
    try {
        const res = await axios.post(`/pos/tables/${table.id}/release`);
        if (res.data?.success) {
            showAlert(res.data.message || `Meja ${table.table_number} berhasil dikosongkan.`);
            emit('updated');
        }
    } catch (err) {
        showAlert('Gagal mengosongkan meja.', 'error');
    } finally {
        isLoading.value = false;
    }
}

async function toggleTable(table) {
    isLoading.value = true;
    try {
        const res = await axios.post(`/pos/tables/${table.id}/toggle`);
        if (res.data?.success) {
            showAlert(res.data.message);
            emit('updated');
        }
    } catch (err) {
        showAlert('Gagal mengubah status meja.', 'error');
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/60 backdrop-blur-2xs"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden border border-slate-200">
            <!-- Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-amber-900 via-amber-800 to-amber-950 text-white flex items-center justify-between shadow-md shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/20">
                        <LayoutGrid class="w-5 h-5 text-amber-300" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold">Denah & Status Meja Makan</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-stone-950">
                                SBP DINING AREA
                            </span>
                        </div>
                        <p class="text-xs text-amber-200/80">Pantau meja terisi atau kosong, dan kosongkan meja setelah pelanggan selesai.</p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="p-2 text-white/70 hover:text-white hover:bg-white/10 rounded-xl transition-all cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Toast / Alert -->
            <div
                v-if="alertMsg"
                class="px-6 py-2.5 flex items-center gap-2 text-xs font-semibold shrink-0 transition-all"
                :class="alertType === 'success' ? 'bg-emerald-50 text-emerald-800 border-b border-emerald-200' : 'bg-rose-50 text-rose-800 border-b border-rose-200'"
            >
                <CheckCircle2 v-if="alertType === 'success'" class="w-4 h-4 text-emerald-600 shrink-0" />
                <AlertCircle v-else class="w-4 h-4 text-rose-600 shrink-0" />
                <span>{{ alertMsg }}</span>
            </div>

            <!-- Stats Bar -->
            <div class="px-6 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-4 text-xs">
                    <div class="flex items-center gap-1.5 font-semibold text-slate-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Kosong / Tersedia: <strong class="text-emerald-700 font-bold font-mono">{{ availableCount }} Meja</strong></span>
                    </div>
                    <div class="flex items-center gap-1.5 font-semibold text-slate-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        <span>Sedang Terisi: <strong class="text-rose-700 font-bold font-mono">{{ occupiedCount }} Meja</strong></span>
                    </div>
                </div>

                <button
                    type="button"
                    @click="emit('updated')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 hover:text-slate-900 bg-white border border-slate-200 hover:bg-slate-100 transition-colors flex items-center gap-1 cursor-pointer"
                >
                    <RotateCcw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" />
                    <span>Perbarui Data</span>
                </button>
            </div>

            <!-- Tables Grid -->
            <div class="flex-1 overflow-y-auto overscroll-contain p-6 bg-slate-50/40">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <div
                        v-for="table in tables"
                        :key="table.id"
                        class="bg-white rounded-2xl border-2 p-4 flex flex-col justify-between shadow-xs transition-all relative overflow-hidden"
                        :class="table.status === 'occupied' ? 'border-rose-300 bg-rose-50/30' : 'border-emerald-300 bg-emerald-50/20'"
                    >
                        <!-- Top Row: Table Name & Capacity -->
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-base font-extrabold text-slate-900">
                                    {{ table.name }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600 flex items-center gap-1">
                                    <Users class="w-3 h-3 text-slate-400" />
                                    {{ table.capacity }} Kursi
                                </span>
                            </div>

                            <!-- Status Badge -->
                            <div class="mb-3">
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold"
                                    :class="table.status === 'occupied'
                                        ? 'bg-rose-100 text-rose-800'
                                        : 'bg-emerald-100 text-emerald-800'"
                                >
                                    <span class="w-2 h-2 rounded-full" :class="table.status === 'occupied' ? 'bg-rose-500 animate-pulse' : 'bg-emerald-500'"></span>
                                    {{ table.status === 'occupied' ? 'Sedang Terisi' : 'Kosong / Tersedia' }}
                                </span>
                            </div>

                            <!-- Occupant Info if occupied -->
                            <div v-if="table.status === 'occupied'" class="text-xs text-slate-600 bg-white/90 p-2.5 rounded-xl border border-rose-200/80 mb-3 space-y-0.5">
                                <div class="font-bold text-rose-900 flex items-center justify-between">
                                    <span>{{ table.current_order?.order_number || 'Pesanan Aktif' }}</span>
                                    <span class="text-[10px] font-normal text-slate-400">Dine-in</span>
                                </div>
                                <div class="text-[11px] truncate text-slate-700">
                                    Pelanggan: <strong>{{ table.current_order?.customer_name || '-' }}</strong>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic mb-3">
                                Meja siap dipilih oleh pelanggan di layar Kiosk mandiri.
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                            <button
                                v-if="table.status === 'occupied'"
                                type="button"
                                @click="releaseTable(table)"
                                class="w-full py-2 px-3 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>Kosongkan Meja</span>
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="toggleTable(table)"
                                class="w-full py-1.5 px-3 rounded-xl text-xs font-semibold text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors flex items-center justify-center gap-1 cursor-pointer border border-slate-200"
                            >
                                <Coffee class="w-3.5 h-3.5 text-slate-400" />
                                <span>Tandai Terisi</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-3.5 bg-white border-t border-slate-200 flex items-center justify-between shrink-0">
                <span class="text-xs text-slate-500">
                    Perubahan status meja langsung sinkron dengan layar pemesanan Kiosk pelanggan.
                </span>
                <button
                    @click="emit('close')"
                    class="px-5 py-2 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-900 text-white shadow-xs transition-colors cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</template>
