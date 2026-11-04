<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { formatRupiah, formatDateTime, formatRelativeTime } from '@/Utils/formatters';
import ReceiptPrint from '@/Components/ReceiptPrint.vue';
import KitchenTicketPrint from '@/Components/KitchenTicketPrint.vue';
import ZReportPrint from '@/Components/ZReportPrint.vue';
import RevenueRecapModal from '@/Components/RevenueRecapModal.vue';
import MenuManagerModal from '@/Components/MenuManagerModal.vue';
import PrinterSettingsModal from '@/Components/PrinterSettingsModal.vue';
import TableMapModal from '@/Components/TableMapModal.vue';
import StoreSettingsModal from '@/Components/StoreSettingsModal.vue';
import { thermalPrinter } from '@/Utils/thermalPrinter';
import {
    UtensilsCrossed,
    ShoppingBag,
    Printer,
    CheckCircle2,
    Clock,
    AlertCircle,
    RotateCcw,
    Search,
    CreditCard,
    Banknote,
    QrCode,
    X,
    FileText,
    TrendingUp,
    Store,
    Layers,
    Volume2,
    VolumeX,
    Trash2,
    BarChart3,
    LayoutGrid,
    Bell,
    Flame,
    Tv,
    Settings,
    FileSpreadsheet
} from 'lucide-vue-next';

const props = defineProps({
    initialData: {
        type: Object,
        required: true,
    },
});

// Orders State
const pendingOrders = ref(props.initialData?.pending_orders || []);
const recentPaidOrders = ref(props.initialData?.recent_paid_orders || []);
const stats = ref(props.initialData?.stats || { pending_count: 0, today_paid_count: 0, today_revenue: 0 });

// Active Selection & Filter Tab
const activeTab = ref('pending'); // 'pending' | 'history'
const selectedOrderId = ref(pendingOrders.value.length > 0 ? pendingOrders.value[0].id : null);
const searchQuery = ref('');

// Payment State
const paymentMethod = ref('cash'); // 'cash' | 'qris'
const amountPaid = ref(0);
const amountPaidString = ref('');
const isProcessingPayment = ref(false);
const paymentErrorMessage = ref('');

// Print Receipt & Kitchen Slip State
const receiptOrder = ref(null);
const kitchenOrder = ref(null);
const isPrintingKitchen = ref(false);

// Table Management State
const tables = ref(props.initialData?.tables || []);
const showTableMapModal = ref(false);

const availableTablesCount = computed(() => {
    return tables.value.filter((t) => t.status === 'available').length;
});

// Store Settings & Profile State
const storeSettings = ref(props.initialData?.store_settings || {});
const showStoreSettingsModal = ref(false);

function onStoreSettingsSaved(newSettings) {
    storeSettings.value = newSettings;
}

// Z-Report Thermal State
const zReportData = ref(null);
const isPrintingZReport = ref(false);

async function printZReport() {
    try {
        const res = await axios.get('/pos/api/z-report');
        if (res.data?.success && res.data.report) {
            zReportData.value = res.data.report;
            isPrintingZReport.value = true;
            isPrintingKitchen.value = false;
            await nextTick();
            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    isPrintingZReport.value = false;
                }, 500);
            }, 250);
        }
    } catch (e) {
        alert('Gagal memuat data Z-Report tutup kasir.');
    }
}

// Revenue Recap Modal State
const showRevenueModal = ref(false);

// Menu & Category Management Modal State
const showMenuModal = ref(false);

// Printer Settings State
const showPrinterModal = ref(false);
const posPrinterSettings = ref(thermalPrinter.loadSettings('pos_printer_settings'));

function onPosPrinterSaved(newSettings) {
    posPrinterSettings.value = newSettings;
}

const isPrinterConnected = computed(() => {
    if (posPrinterSettings.value.mode === 'bluetooth') {
        return thermalPrinter.isBluetoothConnected();
    }
    if (posPrinterSettings.value.mode === 'serial') {
        return thermalPrinter.isSerialConnected();
    }
    return true; // System driver ready
});

function openRevenueModal() {
    showRevenueModal.value = true;
}

function closeRevenueModal() {
    showRevenueModal.value = false;
}

// Audio Alert for New Orders
const soundEnabled = ref(true);
let previousPendingCount = pendingOrders.value.length;

function playNewOrderChime() {
    if (!soundEnabled.value) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.1); // A5
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.4);
    } catch {
        // AudioContext might be restricted until user gesture
    }
}

// Polling interval
let pollTimer = null;
const isSyncing = ref(false);

async function fetchOrdersSilently() {
    try {
        isSyncing.value = true;
        const response = await axios.get('/pos/api/orders');
        if (response.data) {
            const newPending = response.data.pending_orders || [];
            
            // Check if there are new orders arrived
            if (newPending.length > previousPendingCount) {
                playNewOrderChime();
            }
            previousPendingCount = newPending.length;

            pendingOrders.value = newPending;
            recentPaidOrders.value = response.data.recent_paid_orders || [];
            stats.value = response.data.stats || stats.value;
            tables.value = response.data.tables || tables.value;

            // If selected order was removed or none selected, pick the first pending
            const orderStillExists = [...pendingOrders.value, ...recentPaidOrders.value].some(
                (o) => o.id === selectedOrderId.value
            );
            if (!orderStillExists && pendingOrders.value.length > 0) {
                selectOrder(pendingOrders.value[0]);
            }
        }
    } catch (e) {
        // Silent poll error handling
    } finally {
        isSyncing.value = false;
    }
}

onMounted(() => {
    // Select first order on initial load if available
    if (pendingOrders.value.length > 0) {
        selectOrder(pendingOrders.value[0]);
    }

    // Short-polling every 3 seconds
    pollTimer = setInterval(fetchOrdersSilently, 3000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

// Currently Selected Order
const selectedOrder = computed(() => {
    return (
        pendingOrders.value.find((o) => o.id === selectedOrderId.value) ||
        recentPaidOrders.value.find((o) => o.id === selectedOrderId.value) ||
        null
    );
});

// Filtered Orders List based on search & tab
const displayedOrders = computed(() => {
    const list = activeTab.value === 'pending' ? pendingOrders.value : recentPaidOrders.value;
    if (!searchQuery.value) return list;

    const q = searchQuery.value.toLowerCase();
    return list.filter((order) => {
        return (
            order.order_number.toLowerCase().includes(q) ||
            order.customer_name.toLowerCase().includes(q) ||
            (order.table_number && order.table_number.toLowerCase().includes(q))
        );
    });
});

// Change (Kembalian) Calculation
const changeAmount = computed(() => {
    if (!selectedOrder.value || paymentMethod.value !== 'cash') return 0;
    const paid = Number(amountPaid.value) || 0;
    return paid - selectedOrder.value.total_amount;
});

const isCashEnough = computed(() => {
    if (!selectedOrder.value) return false;
    return (Number(amountPaid.value) || 0) >= selectedOrder.value.total_amount;
});

// Select order handler
function selectOrder(order) {
    selectedOrderId.value = order.id;
    paymentErrorMessage.value = '';
    // Reset tender input to exact total
    if (order.status === 'pending_payment') {
        paymentMethod.value = 'cash';
        setAmountPaid(order.total_amount);
    }
}

// Payment Tender Helpers
function setAmountPaid(val) {
    amountPaid.value = Number(val);
    amountPaidString.value = val ? String(val) : '';
}

function handleKeypad(digit) {
    if (digit === 'CLEAR') {
        amountPaidString.value = '';
    } else if (digit === 'BACK') {
        amountPaidString.value = amountPaidString.value.slice(0, -1);
    } else if (digit === '000') {
        if (amountPaidString.value && amountPaidString.value !== '0') {
            amountPaidString.value += '000';
        }
    } else {
        if (amountPaidString.value === '0') {
            amountPaidString.value = digit;
        } else {
            amountPaidString.value += digit;
        }
    }
    amountPaid.value = Number(amountPaidString.value) || 0;
}

// Process Payment Action
async function confirmPayment() {
    if (!selectedOrder.value || isProcessingPayment.value) return;

    if (paymentMethod.value === 'cash' && !isCashEnough.value) {
        paymentErrorMessage.value = 'Jumlah uang tunai kurang dari total tagihan.';
        return;
    }

    isProcessingPayment.value = true;
    paymentErrorMessage.value = '';

    try {
        const payload = {
            payment_method: paymentMethod.value,
            amount_paid: paymentMethod.value === 'cash' ? amountPaid.value : selectedOrder.value.total_amount,
        };

        const response = await axios.post(`/pos/orders/${selectedOrder.value.id}/pay`, payload);

        if (response.data && response.data.order) {
            const updatedOrder = response.data.order;
            receiptOrder.value = updatedOrder;

            // Trigger print if autoPrint is enabled
            if (posPrinterSettings.value.autoPrint) {
                await printOrderReceipt(updatedOrder);

                // If printKitchen is enabled: also print kitchen ticket
                if (posPrinterSettings.value.printKitchen) {
                    setTimeout(() => {
                        printKitchenTicket(updatedOrder);
                    }, 600);
                }
            }

            // Refetch pos data immediately
            await fetchOrdersSilently();

            // Select next pending order if available
            if (pendingOrders.value.length > 0) {
                selectOrder(pendingOrders.value[0]);
            }
        }
    } catch (error) {
        if (error.response && error.response.data && error.response.data.message) {
            paymentErrorMessage.value = error.response.data.message;
        } else {
            paymentErrorMessage.value = 'Gagal memproses pembayaran. Silakan coba kembali.';
        }
    } finally {
        isProcessingPayment.value = false;
    }
}

// Print Receipt (Direct Bluetooth/Serial or System Browser)
async function printOrderReceipt(order) {
    if (!order) return;
    receiptOrder.value = order;
    isPrintingKitchen.value = false;

    // Check direct Bluetooth
    if (posPrinterSettings.value.mode === 'bluetooth' && thermalPrinter.isBluetoothConnected()) {
        try {
            const bytes = thermalPrinter.buildReceiptEscPos(order, posPrinterSettings.value.paperSize, storeSettings.value);
            await thermalPrinter.sendBytes(bytes);
            return;
        } catch (e) {
            console.error('Direct BT print failed, fallback to system print:', e);
        }
    } else if (posPrinterSettings.value.mode === 'serial' && thermalPrinter.isSerialConnected()) {
        try {
            const bytes = thermalPrinter.buildReceiptEscPos(order, posPrinterSettings.value.paperSize, storeSettings.value);
            await thermalPrinter.sendBytes(bytes);
            return;
        } catch (e) {
            console.error('Direct Serial print failed, fallback to system print:', e);
        }
    }

    // Default System Print
    await nextTick();
    setTimeout(() => {
        window.print();
    }, 200);
}

// Print Kitchen Ticket (Direct Bluetooth/Serial or System Browser)
async function printKitchenTicket(order) {
    if (!order) return;
    kitchenOrder.value = order;

    // Direct Bluetooth
    if (posPrinterSettings.value.mode === 'bluetooth' && thermalPrinter.isBluetoothConnected()) {
        try {
            const bytes = thermalPrinter.buildKitchenTicketEscPos(order, posPrinterSettings.value.paperSize);
            await thermalPrinter.sendBytes(bytes);
            return;
        } catch (e) {
            console.error('Direct BT kitchen print failed, fallback to system:', e);
        }
    } else if (posPrinterSettings.value.mode === 'serial' && thermalPrinter.isSerialConnected()) {
        try {
            const bytes = thermalPrinter.buildKitchenTicketEscPos(order, posPrinterSettings.value.paperSize);
            await thermalPrinter.sendBytes(bytes);
            return;
        } catch (e) {
            console.error('Direct Serial kitchen print failed, fallback to system:', e);
        }
    }

    // Default system print: show kitchen slip and trigger browser print
    isPrintingKitchen.value = true;
    await nextTick();
    setTimeout(() => {
        window.print();
        setTimeout(() => {
            isPrintingKitchen.value = false;
        }, 500);
    }, 200);
}

// Cancel Order Action
async function cancelCurrentOrder() {
    if (!selectedOrder.value) return;
    if (!confirm(`Batalkan pesanan ${selectedOrder.value.order_number} milik ${selectedOrder.value.customer_name}?`)) {
        return;
    }

    try {
        await axios.post(`/pos/orders/${selectedOrder.value.id}/cancel`);
        await fetchOrdersSilently();
        if (pendingOrders.value.length > 0) {
            selectOrder(pendingOrders.value[0]);
        }
    } catch (e) {
        alert('Gagal membatalkan pesanan.');
    }
}

// Delete Order Action (permanently from history or queue)
async function deleteOrder(orderToDelete) {
    const target = orderToDelete || selectedOrder.value;
    if (!target) return;

    if (!confirm(`Hapus pesanan ${target.order_number} (${target.customer_name}) dari riwayat sistem?`)) {
        return;
    }

    try {
        await axios.delete(`/pos/orders/${target.id}`);
        
        const wasSelected = selectedOrderId.value === target.id;
        if (wasSelected) {
            selectedOrderId.value = null;
            selectedOrder.value = null;
        }

        await fetchOrdersSilently();

        // If the deleted order was selected, select next available order
        if (wasSelected) {
            if (activeTab.value === 'pending' && pendingOrders.value.length > 0) {
                selectOrder(pendingOrders.value[0]);
            } else if (activeTab.value === 'history' && recentPaidOrders.value.length > 0) {
                selectOrder(recentPaidOrders.value[0]);
            }
        }
    } catch (e) {
        alert('Gagal menghapus pesanan dari riwayat.');
    }
}

// Panggil antrean ke TV Display
async function callOrderToTv(order) {
    if (!order) return;
    try {
        await axios.post(`/pos/orders/${order.id}/call`);
        await fetchOrdersSilently();
    } catch (e) {
        alert('Gagal memanggil nomor antrean ke layar TV.');
    }
}

// Clear All Orders Action (History or Pending)
async function clearAllOrders(scope = 'history') {
    const scopeLabel = scope === 'history' ? 'SEMUA riwayat pesanan yang sudah selesai' : 'SEMUA antrean pesanan yang menunggu';

    if (!confirm(`PERINGATAN: Apakah Anda yakin ingin MENGHAPUS ${scopeLabel}? Tindakan ini bersifat permanen dan tidak dapat dipulihkan.`)) {
        return;
    }

    try {
        await axios.post('/pos/orders/clear-all', { scope });

        selectedOrderId.value = null;
        selectedOrder.value = null;

        await fetchOrdersSilently();
    } catch (e) {
        alert('Gagal menghapus semua pesanan.');
    }
}
</script>

<template>
    <Head title="Kasir Tablet POS" />

    <!-- Thermal Receipt Print Component (hidden on screen, visible during window.print) -->
    <ReceiptPrint v-if="!isPrintingKitchen && !isPrintingZReport && receiptOrder" :order="receiptOrder" :paperSize="posPrinterSettings.paperSize" :storeSettings="storeSettings" />

    <!-- Thermal Kitchen Slip Print Component (hidden on screen, visible during window.print) -->
    <KitchenTicketPrint v-if="isPrintingKitchen && kitchenOrder" :order="kitchenOrder" :paperSize="posPrinterSettings.paperSize" />

    <!-- Thermal Z-Report Print Component (hidden on screen, visible during window.print) -->
    <ZReportPrint v-if="isPrintingZReport && zReportData" :report="zReportData" :paperSize="posPrinterSettings.paperSize" />

    <!-- Table Map Modal (Denah Meja Restoran) -->
    <TableMapModal
        :show="showTableMapModal"
        :tables="tables"
        @close="showTableMapModal = false"
        @updated="fetchOrdersSilently"
    />

    <!-- Revenue Recap Modal (Daily, Weekly, Monthly) -->
    <RevenueRecapModal :show="showRevenueModal" @close="closeRevenueModal" @print-zreport="printZReport" />

    <!-- Store Profile & Settings Modal -->
    <StoreSettingsModal
        :show="showStoreSettingsModal"
        :initialSettings="storeSettings"
        @close="showStoreSettingsModal = false"
        @saved="onStoreSettingsSaved"
    />

    <!-- Menu & Category Manager Modal (Manual Input MySQL) -->
    <MenuManagerModal
        :show="showMenuModal"
        @close="showMenuModal = false"
        @updated="fetchOrdersSilently"
    />

    <!-- Printer Settings Modal -->
    <PrinterSettingsModal
        :show="showPrinterModal"
        profileKey="pos_printer_settings"
        title="Printer Struk Kasir POS"
        subtitle="Hubungkan ke printer thermal struk Bluetooth, USB, atau Driver Sistem."
        @close="showPrinterModal = false"
        @saved="onPosPrinterSaved"
    />

    <div class="h-screen flex flex-col bg-slate-50 font-sans text-slate-900 overflow-hidden">
        <!-- Top App Bar -->
        <header class="bg-white border-b border-slate-200 h-16 shrink-0 px-4 sm:px-6 flex items-center justify-between z-20">
            <!-- Left Branding -->
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-xs tracking-wider">
                        SBP
                    </div>
                    <div>
                        <h1 class="text-sm font-bold text-slate-900 tracking-tight leading-tight">SOTO BETAWI PARSOEY KASIR</h1>
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium">
                            <span>POS Terminal 01</span>
                            <span>•</span>
                            <!-- Live Sync Indicator -->
                            <span class="inline-flex items-center gap-1.5 font-mono text-[10px] text-emerald-600" title="Koneksi aktif • Data sinkron otomatis">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block" :class="{ 'opacity-40 animate-pulse': isSyncing }"></span>
                                <span>Live Sync</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Stats Quick Glance (shown on wide screens only to prevent header clutter) -->
            <div class="hidden 2xl:flex items-center gap-6 px-4 py-1.5 bg-slate-50 rounded-lg border border-slate-200 text-xs shrink-0">
                <div class="flex items-center gap-2">
                    <Clock class="w-4 h-4 text-amber-600" />
                    <span class="text-slate-500">Antrean:</span>
                    <span class="font-bold text-slate-900 font-mono">{{ stats.pending_count }} Order</span>
                </div>
                <div class="h-3 w-px bg-slate-200"></div>
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                    <span class="text-slate-500">Selesai Hari Ini:</span>
                    <span class="font-bold text-slate-900 font-mono">{{ stats.today_paid_count }} Order</span>
                </div>
                <div class="h-3 w-px bg-slate-200"></div>
                <div
                    @click="openRevenueModal"
                    class="flex items-center gap-2 cursor-pointer hover:bg-slate-100/80 px-2 py-1 -my-1 rounded-md transition-colors"
                    title="Klik untuk membuka Rekap Omset Lengkap"
                >
                    <TrendingUp class="w-4 h-4 text-emerald-600" />
                    <span class="text-slate-500">Omset:</span>
                    <span class="font-bold text-emerald-700 font-mono">{{ formatRupiah(stats.today_revenue) }}</span>
                </div>
            </div>

            <!-- Right Controls -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <!-- Denah Meja Button -->
                <button
                    type="button"
                    @click="showTableMapModal = true"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold bg-white hover:bg-slate-100 text-slate-800 border border-slate-200 transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs"
                    title="Buka Denah & Status Meja Restoran"
                >
                    <LayoutGrid class="w-3.5 h-3.5 text-amber-700" />
                    <span class="hidden md:inline">Denah</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 font-mono">
                        {{ availableTablesCount }}
                    </span>
                </button>

                <!-- Printer Struk Button -->
                <button
                    type="button"
                    @click="showPrinterModal = true"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-800 hover:bg-slate-900 active:bg-slate-950 text-white transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs"
                    title="Koneksi & Pengaturan Printer Struk Kasir"
                >
                    <Printer class="w-3.5 h-3.5 text-amber-400" />
                    <span class="hidden md:inline">Printer</span>
                    <span
                        class="w-2 h-2 rounded-full"
                        :class="isPrinterConnected ? 'bg-emerald-400 animate-pulse' : 'bg-slate-400'"
                        :title="isPrinterConnected ? 'Printer Siap' : 'Printer Belum Konek'"
                    ></span>
                </button>

                <!-- Kelola Menu & Kategori Button -->
                <button
                    type="button"
                    @click="showMenuModal = true"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-800 hover:bg-amber-900 active:bg-amber-950 text-white transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs"
                    title="Kelola Menu & Kategori (Input manual MySQL)"
                >
                    <UtensilsCrossed class="w-3.5 h-3.5 text-amber-300" />
                    <span class="hidden lg:inline">Menu</span>
                </button>

                <!-- Rekap Omset Button -->
                <button
                    type="button"
                    @click="openRevenueModal"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs"
                    title="Buka Rekap Omset Harian, Mingguan, dan Bulanan"
                >
                    <BarChart3 class="w-3.5 h-3.5" />
                    <span class="hidden lg:inline">Omset</span>
                </button>

                <!-- Tutup Kasir (Z-Report) Button -->
                <button
                    type="button"
                    @click="printZReport"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-900 hover:bg-slate-800 text-amber-300 transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs"
                    title="Cetak Laporan Resmi Tutup Kasir Shift (Z-Report) ke Printer Thermal"
                >
                    <Printer class="w-3.5 h-3.5 text-amber-400" />
                    <span class="hidden sm:inline">Tutup Kasir</span>
                </button>

                <!-- Profil Toko & Format Struk Button -->
                <button
                    type="button"
                    @click="showStoreSettingsModal = true"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold bg-white hover:bg-slate-100 text-slate-800 border border-slate-200 transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs"
                    title="Atur Nama Resto, Cabang, Alamat, Kontak, dan Format Struk"
                >
                    <Settings class="w-3.5 h-3.5 text-amber-700" />
                    <span class="hidden lg:inline">Profil Resto</span>
                </button>

                <!-- Sound Toggle -->
                <button
                    type="button"
                    @click="soundEnabled = !soundEnabled"
                    class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors"
                    :title="soundEnabled ? 'Notifikasi Suara Aktif' : 'Notifikasi Suara Senyap'"
                >
                    <Volume2 v-if="soundEnabled" class="w-4 h-4 text-amber-600" />
                    <VolumeX v-else class="w-4 h-4 text-slate-400" />
                </button>

                <!-- Refresh Button -->
                <button
                    type="button"
                    @click="fetchOrdersSilently"
                    class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors"
                    title="Refresh Data"
                >
                    <RotateCcw class="w-4 h-4" :class="{ 'animate-spin': isSyncing }" />
                </button>

                <!-- Navigation Shortcuts to other screens -->
                <div class="h-6 w-px bg-slate-200 mx-1 hidden sm:block"></div>

                <div class="flex items-center gap-1.5">
                    <a
                        href="/kitchen"
                        target="_blank"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-slate-900 hover:bg-slate-800 text-amber-300 transition-colors flex items-center gap-1.5 shadow-2xs"
                        title="Buka Layar Tablet Dapur Koki (KDS)"
                    >
                        <Flame class="w-3.5 h-3.5 text-amber-400" />
                        <span class="hidden xl:inline">Layar Dapur</span>
                    </a>

                    <a
                        href="/display"
                        target="_blank"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white transition-colors flex items-center gap-1.5 shadow-2xs"
                        title="Buka Layar TV Antrean Pelanggan"
                    >
                        <Tv class="w-3.5 h-3.5 text-emerald-200" />
                        <span class="hidden xl:inline">Layar TV</span>
                    </a>

                    <Link
                        href="/kiosk"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-800 transition-colors flex items-center gap-1.5"
                        title="Buka Kiosk Pelanggan"
                    >
                        <Store class="w-3.5 h-3.5 text-slate-600" />
                        <span class="hidden xl:inline">Kiosk</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Split Screen -->
        <div class="flex-1 min-h-0 grid grid-cols-12 overflow-hidden">
            <!-- ================= LEFT COLUMN: ORDER QUEUE (5 Columns) ================= -->
            <aside class="col-span-12 md:col-span-5 lg:col-span-4 border-r border-slate-200 bg-white flex flex-col h-full min-h-0 overflow-hidden">
                <!-- Tab Selector & Search -->
                <div class="p-3 border-b border-slate-200 space-y-2 bg-slate-50/50 shrink-0">
                    <div class="grid grid-cols-2 gap-1 p-1 bg-slate-200/70 rounded-lg text-xs font-semibold">
                        <button
                            type="button"
                            @click="activeTab = 'pending'"
                            class="py-2 rounded-md transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                            :class="activeTab === 'pending' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        >
                            <span>Menunggu</span>
                            <span
                                v-if="pendingOrders.length > 0"
                                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-500 text-white"
                            >
                                {{ pendingOrders.length }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'history'"
                            class="py-2 rounded-md transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                            :class="activeTab === 'history' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        >
                            <span>Selesai Hari Ini</span>
                            <span class="text-slate-400 text-[10px]">({{ recentPaidOrders.length }})</span>
                        </button>
                    </div>

                    <!-- Search Input & Clear All Action -->
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari No. Antrean / Pelanggan..."
                                class="w-full pl-9 pr-3 py-2 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                            />
                        </div>

                        <!-- Hapus Semua Button for History Tab -->
                        <button
                            v-if="activeTab === 'history' && recentPaidOrders.length > 0"
                            type="button"
                            @click="clearAllOrders('history')"
                            class="px-2.5 py-2 rounded-lg text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors flex items-center gap-1.5 shrink-0 cursor-pointer shadow-2xs"
                            title="Hapus seluruh riwayat pesanan hari ini"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>Hapus Semua</span>
                        </button>

                        <!-- Hapus Semua Button for Pending Tab -->
                        <button
                            v-else-if="activeTab === 'pending' && pendingOrders.length > 0"
                            type="button"
                            @click="clearAllOrders('pending')"
                            class="px-2.5 py-2 rounded-lg text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors flex items-center gap-1.5 shrink-0 cursor-pointer shadow-2xs"
                            title="Batalkan & hapus semua antrean pesanan"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>Hapus Semua</span>
                        </button>
                    </div>
                </div>

                <!-- Orders Scroll List -->
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain divide-y divide-slate-100 p-2 space-y-1.5">
                    <div v-if="displayedOrders.length === 0" class="py-16 text-center text-slate-400">
                        <Layers class="w-10 h-10 mx-auto mb-2 stroke-[1.5] text-slate-300" />
                        <p class="text-xs font-medium">Tidak ada pesanan {{ activeTab === 'pending' ? 'yang menunggu pembayaran' : 'yang cocok' }}</p>
                    </div>

                    <div
                        v-for="order in displayedOrders"
                        :key="order.id"
                        @click="selectOrder(order)"
                        class="p-3 rounded-xl border transition-all cursor-pointer relative"
                        :class="[
                            selectedOrderId === order.id
                                ? 'bg-amber-50/40 border-amber-500 shadow-xs ring-1 ring-amber-500/20'
                                : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/60'
                        ]"
                    >
                        <!-- Top line: Queue Number & Time -->
                        <div class="flex items-center justify-between">
                            <span class="font-mono font-black text-base text-slate-900">
                                {{ order.order_number }}
                            </span>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-medium text-slate-500 flex items-center gap-1">
                                    <Clock class="w-3 h-3 text-slate-400" />
                                    {{ formatRelativeTime(order.created_at) }}
                                </span>
                                <button
                                    type="button"
                                    @click.stop="deleteOrder(order)"
                                    class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                    title="Hapus pesanan ini dari riwayat"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Customer & Table Badge -->
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 truncate">
                                {{ order.customer_name }}
                            </span>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wide"
                                :class="order.order_type === 'dine_in' ? 'bg-slate-100 text-slate-700' : 'bg-blue-50 text-blue-700'"
                            >
                                {{ order.order_type === 'dine_in' ? `Meja ${order.table_number || '-'}` : 'Bawa Pulang' }}
                            </span>
                        </div>

                        <!-- Items Snippet -->
                        <div class="mt-2 text-[11px] text-slate-500 line-clamp-1">
                            {{ order.items.map((i) => `${i.quantity}x ${i.product?.name || 'Menu'}`).join(', ') }}
                        </div>

                        <!-- Bottom: Total & Status Indicator -->
                        <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-900">
                                {{ formatRupiah(order.total_amount) }}
                            </span>

                            <span
                                class="inline-flex items-center gap-1 font-semibold text-[10px] uppercase"
                                :class="order.status === 'paid' ? 'text-emerald-700' : 'text-amber-700'"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="order.status === 'paid' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                {{ order.status === 'paid' ? 'LUNAS' : 'MENUNGGU' }}
                            </span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ================= RIGHT COLUMN: ORDER DETAILS & PAYMENT (7 or 8 Columns) ================= -->
            <main class="col-span-12 md:col-span-7 lg:col-span-8 flex flex-col h-full min-h-0 bg-slate-50 overflow-hidden">
                <div v-if="selectedOrder" class="flex-1 min-h-0 flex flex-col lg:flex-row overflow-hidden">
                    <!-- Column A: Order Itemized Details (Left side of right pane) -->
                    <div class="flex-1 min-h-0 flex flex-col border-b lg:border-b-0 lg:border-r border-slate-200 bg-white overflow-hidden">
                        <!-- Order Header -->
                        <div class="p-3 sm:p-4 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between shrink-0">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="font-mono text-xl font-black text-slate-900">
                                        {{ selectedOrder.order_number }}
                                    </h2>
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider"
                                        :class="selectedOrder.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ selectedOrder.status === 'paid' ? 'Lunas' : 'Belum Bayar' }}
                                    </span>

                                    <!-- Kitchen Status -->
                                    <span
                                        v-if="selectedOrder.status === 'paid' && selectedOrder.kitchen_status"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider flex items-center gap-1"
                                        :class="[
                                            selectedOrder.kitchen_status === 'cooking'
                                                ? 'bg-blue-100 text-blue-800 ring-1 ring-blue-300'
                                                : (selectedOrder.kitchen_status === 'ready'
                                                    ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                    : (selectedOrder.kitchen_status === 'completed'
                                                        ? 'bg-slate-100 text-slate-600'
                                                        : 'bg-amber-100 text-amber-800'))
                                        ]"
                                    >
                                        <Flame v-if="selectedOrder.kitchen_status === 'cooking'" class="w-3 h-3 text-blue-600" />
                                        <CheckCircle2 v-else-if="selectedOrder.kitchen_status === 'ready'" class="w-3 h-3 text-emerald-600" />
                                        <span>
                                            {{
                                                selectedOrder.kitchen_status === 'cooking' ? 'Sedang Dimasak' :
                                                (selectedOrder.kitchen_status === 'ready' ? 'Siap Saji' :
                                                (selectedOrder.kitchen_status === 'completed' ? 'Selesai' : 'Antrean Dapur'))
                                            }}
                                        </span>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ selectedOrder.customer_name }} •
                                    {{ selectedOrder.order_type === 'dine_in' ? `Meja ${selectedOrder.table_number || '-'}` : 'Bawa Pulang (Takeaway)' }} •
                                    {{ formatDateTime(selectedOrder.created_at) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="printKitchenTicket(selectedOrder)"
                                    class="text-xs font-bold text-amber-900 bg-amber-50 hover:bg-amber-100 border border-amber-300 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                    title="Cetak Tiket Pesanan untuk Dapur / Koki"
                                >
                                    <UtensilsCrossed class="w-3.5 h-3.5 text-amber-700" />
                                    <span>Tiket Dapur</span>
                                </button>

                                <button
                                    v-if="selectedOrder.status === 'pending_payment'"
                                    type="button"
                                    @click="cancelCurrentOrder"
                                    class="text-xs font-semibold text-rose-600 hover:text-rose-700 px-3 py-1.5 rounded-lg border border-rose-200 hover:bg-rose-50 transition-colors cursor-pointer"
                                >
                                    Batalkan Pesanan
                                </button>

                                <button
                                    type="button"
                                    @click="deleteOrder(selectedOrder)"
                                    class="text-xs font-semibold text-rose-600 hover:text-rose-700 px-3 py-1.5 rounded-lg border border-rose-200 hover:bg-rose-50 transition-colors flex items-center gap-1.5 cursor-pointer"
                                    title="Hapus pesanan ini secara permanen dari riwayat"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Hapus Pesanan</span>
                                </button>
                            </div>
                        </div>

                        <!-- Itemized List -->
                        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-4 space-y-3 divide-y divide-slate-100">
                            <div
                                v-for="item in selectedOrder.items"
                                :key="item.id"
                                class="pt-3 first:pt-0"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-bold text-sm text-slate-900">
                                            {{ item.product?.name || 'Menu Soto' }}
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            {{ item.quantity }} x {{ formatRupiah(item.unit_price) }}
                                        </div>
                                    </div>
                                    <div class="font-bold text-sm text-slate-900">
                                        {{ formatRupiah(item.subtotal) }}
                                    </div>
                                </div>

                                <!-- Note badge if present -->
                                <div v-if="item.notes" class="mt-1.5 inline-flex items-center gap-1.5 bg-amber-50 text-amber-900 border border-amber-200/60 rounded px-2 py-0.5 text-xs font-medium">
                                    <FileText class="w-3 h-3 text-amber-600 shrink-0" />
                                    <span>Catatan: {{ item.notes }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bill Breakdown Summary -->
                        <div class="p-4 border-t border-slate-200 bg-slate-50/70 text-xs space-y-1.5">
                            <div class="flex justify-between text-slate-600">
                                <span>Subtotal</span>
                                <span class="font-semibold text-slate-900">{{ formatRupiah(selectedOrder.subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Pajak Resto (0%)</span>
                                <span class="font-semibold text-slate-900">{{ formatRupiah(selectedOrder.tax || 0) }}</span>
                            </div>
                            <div class="flex justify-between text-base font-bold text-slate-900 pt-2 border-t border-slate-200">
                                <span>Total Tagihan</span>
                                <span class="text-amber-600 text-lg">{{ formatRupiah(selectedOrder.total_amount) }}</span>
                            </div>

                            <!-- If already paid, show details & reprint button -->
                            <div v-if="selectedOrder.status === 'paid'" class="mt-3 pt-3 border-t border-slate-200 text-xs space-y-1">
                                <div class="flex justify-between text-slate-600">
                                    <span>Metode:</span>
                                    <span class="font-bold uppercase text-slate-900">{{ selectedOrder.payment_method }}</span>
                                </div>
                                <div v-if="selectedOrder.payment_method === 'cash'" class="flex justify-between text-slate-600">
                                    <span>Uang Diterima:</span>
                                    <span class="font-bold text-slate-900">{{ formatRupiah(selectedOrder.amount_paid) }}</span>
                                </div>
                                <div v-if="selectedOrder.payment_method === 'cash'" class="flex justify-between text-slate-600">
                                    <span>Kembalian:</span>
                                    <span class="font-bold text-emerald-700">{{ formatRupiah(selectedOrder.change_amount || 0) }}</span>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3">
                                    <button
                                        type="button"
                                        @click="callOrderToTv(selectedOrder)"
                                        class="py-2.5 px-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer shadow-2xs"
                                        title="Panggil nomor antrean ini ke Layar TV Pelanggan dengan suara bel & ucapan"
                                    >
                                        <Bell class="w-4 h-4 text-emerald-100" />
                                        <span>Panggil TV</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="printOrderReceipt(selectedOrder)"
                                        class="py-2.5 px-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                        title="Cetak Ulang Struk Pembayaran Kasir"
                                    >
                                        <Printer class="w-4 h-4" />
                                        <span>Struk Kasir</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="printKitchenTicket(selectedOrder)"
                                        class="py-2.5 px-2 bg-amber-100 hover:bg-amber-200 text-amber-900 border border-amber-300 rounded-lg font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                        title="Cetak Ulang Tiket Dapur / Koki (Tanpa Harga)"
                                    >
                                        <UtensilsCrossed class="w-4 h-4 text-amber-700" />
                                        <span>Tiket Dapur</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="deleteOrder(selectedOrder)"
                                        class="py-2.5 px-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                        title="Hapus riwayat pesanan ini"
                                    >
                                        <Trash2 class="w-4 h-4 text-rose-600" />
                                        <span>Hapus</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column B: Payment Calculator Panel (Right side of right pane, active for pending orders) -->
                    <div
                        v-if="selectedOrder.status === 'pending_payment'"
                        class="w-full lg:w-[360px] xl:w-[380px] bg-white border-l border-slate-200 flex flex-col h-full overflow-hidden shrink-0"
                    >
                        <!-- Scrollable Calculator Body -->
                        <div class="flex-1 overflow-y-auto overscroll-contain p-3 sm:p-3.5 space-y-2.5">
                            <div class="pb-2 border-b border-slate-200">
                                <h3 class="font-bold text-sm text-slate-900">Pembayaran Tagihan</h3>
                                <p class="text-xs text-slate-500">Pilih metode pembayaran dan hitung kembalian</p>
                            </div>

                            <!-- Error alert -->
                            <div v-if="paymentErrorMessage" class="p-2.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-lg flex items-center gap-2">
                                <AlertCircle class="w-4 h-4 shrink-0" />
                                <span>{{ paymentErrorMessage }}</span>
                            </div>

                            <!-- Payment Method Selector (Cash / QRIS) -->
                            <div class="grid grid-cols-2 gap-1.5">
                                <button
                                    type="button"
                                    @click="paymentMethod = 'cash'"
                                    class="py-2 px-3 rounded-lg border text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                    :class="paymentMethod === 'cash' ? 'border-amber-600 bg-amber-50 text-amber-900 ring-1 ring-amber-600 shadow-xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                >
                                    <Banknote class="w-4 h-4 text-amber-600" />
                                    <span>Tunai (Cash)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="paymentMethod = 'qris'"
                                    class="py-2 px-3 rounded-lg border text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                    :class="paymentMethod === 'qris' ? 'border-amber-600 bg-amber-50 text-amber-900 ring-1 ring-amber-600 shadow-xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                >
                                    <QrCode class="w-4 h-4 text-amber-600" />
                                    <span>QRIS / Non-Tunai</span>
                                </button>
                            </div>

                            <!-- CASH PAYMENT SECTION -->
                            <div v-if="paymentMethod === 'cash'" class="space-y-2">
                                <!-- Quick Nominal Buttons -->
                                <div>
                                    <span class="text-[11px] font-semibold text-slate-500 block mb-1">Pilihan Cepat Nominal</span>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <button
                                            type="button"
                                            @click="setAmountPaid(selectedOrder.total_amount)"
                                            class="py-1.5 px-2 bg-slate-100 hover:bg-amber-100 hover:text-amber-900 rounded-md text-xs font-bold text-slate-800 transition-colors cursor-pointer"
                                        >
                                            Uang Pas ({{ formatRupiah(selectedOrder.total_amount) }})
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAmountPaid(50000)"
                                            class="py-1.5 px-2 bg-slate-100 hover:bg-amber-100 hover:text-amber-900 rounded-md text-xs font-bold text-slate-800 transition-colors cursor-pointer"
                                        >
                                            Rp 50.000
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAmountPaid(100000)"
                                            class="py-1.5 px-2 bg-slate-100 hover:bg-amber-100 hover:text-amber-900 rounded-md text-xs font-bold text-slate-800 transition-colors cursor-pointer"
                                        >
                                            Rp 100.000
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAmountPaid(200000)"
                                            class="py-1.5 px-2 bg-slate-100 hover:bg-amber-100 hover:text-amber-900 rounded-md text-xs font-bold text-slate-800 transition-colors cursor-pointer"
                                        >
                                            Rp 200.000
                                        </button>
                                    </div>
                                </div>

                                <!-- Amount Tender Display -->
                                <div>
                                    <div class="flex justify-between items-baseline mb-0.5">
                                        <span class="text-[11px] font-semibold text-slate-500">Uang Diterima</span>
                                        <span class="text-xs font-mono font-bold text-slate-900">{{ formatRupiah(amountPaid) }}</span>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            :value="amountPaid ? Number(amountPaid).toLocaleString('id-ID') : '0'"
                                            readonly
                                            class="w-full py-1.5 px-3 text-base font-mono font-bold text-right bg-slate-50 border border-slate-200 rounded-lg focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <!-- Keypad Numbers (3x4) -->
                                <div class="grid grid-cols-3 gap-1">
                                    <button
                                        v-for="key in ['1','2','3','4','5','6','7','8','9','000','0','BACK']"
                                        :key="key"
                                        type="button"
                                        @click="handleKeypad(key)"
                                        class="h-8.5 rounded-md border border-slate-200 bg-white hover:bg-slate-100 active:bg-slate-200 text-xs font-bold font-mono text-slate-800 transition-colors cursor-pointer"
                                    >
                                        {{ key === 'BACK' ? '⌫' : key }}
                                    </button>
                                </div>

                                <!-- Change (Kembalian) Calculation Display -->
                                <div class="p-2 rounded-lg border text-xs" :class="isCashEnough ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200'">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-slate-700">
                                            {{ isCashEnough ? 'Kembalian:' : 'Uang Kurang:' }}
                                        </span>
                                        <span
                                            class="text-sm font-mono font-black"
                                            :class="isCashEnough ? 'text-emerald-700' : 'text-rose-600'"
                                        >
                                            {{ formatRupiah(Math.abs(changeAmount)) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- QRIS PAYMENT SECTION -->
                            <div v-else class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center space-y-2">
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wide block">
                                    SCAN QRIS UNTUK PEMBAYARAN
                                </span>

                                <!-- Dynamic QR Code Mockup -->
                                <div class="w-36 h-36 mx-auto bg-white p-2 rounded-lg border border-slate-300 shadow-xs flex flex-col items-center justify-center">
                                    <img
                                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=TOKO_SOTO_${selectedOrder.order_number}_${selectedOrder.total_amount}`"
                                        alt="QRIS Barcode"
                                        class="w-32 h-32 object-contain"
                                        @error="$event.target.src = ''"
                                    />
                                </div>

                                <div class="text-xs text-slate-600">
                                    Total: <strong class="text-slate-900 font-mono text-sm">{{ formatRupiah(selectedOrder.total_amount) }}</strong>
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    Mendukung GoPay, OVO, Dana, BCA, Mandiri, dll.
                                </div>
                            </div>
                        </div>

                        <!-- ALWAYS VISIBLE FIXED BOTTOM BUTTON (SHRINK-0) -->
                        <div class="p-3 border-t border-slate-200 bg-white shrink-0 shadow-xs">
                            <button
                                type="button"
                                @click="confirmPayment"
                                :disabled="isProcessingPayment || (paymentMethod === 'cash' && !isCashEnough)"
                                class="w-full h-12 rounded-xl bg-amber-600 hover:bg-amber-700 active:bg-amber-800 disabled:opacity-40 disabled:pointer-events-none text-white font-bold text-sm flex items-center justify-center gap-2 transition-colors duration-150 shadow-xs cursor-pointer"
                            >
                                <Printer class="w-4 h-4" />
                                <span>{{ isProcessingPayment ? 'Memproses...' : 'Konfirmasi Bayar & Cetak Struk' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State if no order selected -->
                <div v-else class="flex-1 flex items-center justify-center p-8 text-center text-slate-400">
                    <div>
                        <Store class="w-12 h-12 mx-auto mb-3 text-slate-300 stroke-[1.5]" />
                        <h3 class="text-base font-bold text-slate-700">Pilih Pesanan di Antrean</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm">
                            Pilih salah satu pesanan dari daftar antrean sebelah kiri untuk melihat rincian menu dan melakukan pembayaran.
                        </p>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
