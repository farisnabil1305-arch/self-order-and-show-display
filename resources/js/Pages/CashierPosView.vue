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
import ConfirmModal from '@/Components/ConfirmModal.vue';
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

// Confirmation Modal State (Smooth Animated Custom Dialog, NO native confirm)
const confirmDialog = ref({
    show: false,
    title: '',
    message: '',
    confirmText: 'Ya, Lanjutkan',
    type: 'danger',
    isLoading: false,
    onConfirm: null,
});

function openConfirmDialog({ title, message, confirmText = 'Ya, Lanjutkan', type = 'danger', onConfirm }) {
    confirmDialog.value = {
        show: true,
        title,
        message,
        confirmText,
        type,
        isLoading: false,
        onConfirm,
    };
}

async function handleConfirmAction() {
    if (confirmDialog.value.onConfirm) {
        confirmDialog.value.isLoading = true;
        try {
            await confirmDialog.value.onConfirm();
        } finally {
            confirmDialog.value.isLoading = false;
            confirmDialog.value.show = false;
        }
    } else {
        confirmDialog.value.show = false;
    }
}

// Cancel Order Action
function cancelCurrentOrder() {
    if (!selectedOrder.value) return;
    openConfirmDialog({
        title: 'Batalkan Pesanan Ini?',
        message: `Batalkan pesanan ${selectedOrder.value.order_number} milik ${selectedOrder.value.customer_name}? Status pesanan akan diubah menjadi Dibatalkan.`,
        confirmText: 'Ya, Batalkan',
        type: 'warning',
        onConfirm: async () => {
            try {
                await axios.post(`/pos/orders/${selectedOrder.value.id}/cancel`);
                await fetchOrdersSilently();
                if (pendingOrders.value.length > 0) {
                    selectOrder(pendingOrders.value[0]);
                }
            } catch (e) {
                alert('Gagal membatalkan pesanan.');
            }
        },
    });
}

// Delete Order Action (permanently from history or queue)
function deleteOrder(orderToDelete) {
    const target = orderToDelete || selectedOrder.value;
    if (!target) return;

    openConfirmDialog({
        title: 'Hapus Pesanan Permanen?',
        message: `Hapus pesanan ${target.order_number} (${target.customer_name}) dari riwayat sistem secara permanen? Data yang dihapus tidak dapat dipulihkan.`,
        confirmText: 'Ya, Hapus Pesanan',
        type: 'danger',
        onConfirm: async () => {
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
        },
    });
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
function clearAllOrders(scope = 'history') {
    const scopeLabel = scope === 'history' ? 'SEMUA riwayat pesanan yang sudah selesai' : 'SEMUA antrean pesanan yang menunggu';

    openConfirmDialog({
        title: 'Hapus Semua Pesanan?',
        message: `Apakah Anda yakin ingin menghapus ${scopeLabel}? Tindakan ini bersifat permanen dan data riwayat tidak dapat dipulihkan.`,
        confirmText: 'Ya, Hapus Semua',
        type: 'danger',
        onConfirm: async () => {
            try {
                await axios.post('/pos/orders/clear-all', { scope });

                selectedOrderId.value = null;
                selectedOrder.value = null;

                await fetchOrdersSilently();
            } catch (e) {
                alert('Gagal menghapus semua pesanan.');
            }
        },
    });
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

    <!-- Custom Animated Confirmation Modal (Zero AI Slop) -->
    <ConfirmModal
        :show="confirmDialog.show"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirmText="confirmDialog.confirmText"
        :type="confirmDialog.type"
        :isLoading="confirmDialog.isLoading"
        @confirm="handleConfirmAction"
        @cancel="confirmDialog.show = false"
    />

    <div class="h-screen flex flex-col bg-[#FAF7F2] font-sans text-[#231812] overflow-hidden">
        <!-- Top App Bar (Grouped & Clean - NO Rainbow Button Clutter) -->
        <header class="bg-[#FAF7F2]/95 backdrop-blur-md border-b border-[#E8DFD1] h-18 shrink-0 px-4 sm:px-6 flex items-center justify-between z-20">
            <!-- Left Branding -->
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#231812] text-[#F5EBE1] flex items-center justify-center font-serif font-black text-sm tracking-wider shadow-sm border border-[#3D281F]">
                        SBP
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif text-base font-bold text-[#231812] tracking-tight leading-tight">Soto Betawi Parsoey</h1>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#EFE8DE] text-[#7A6A60] border border-[#E8DFD1]">
                                Kasir POS
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-[#7A6A60] font-medium mt-0.5">
                            <span>Terminal 01</span>
                            <span>•</span>
                            <!-- Live Sync Indicator -->
                            <span class="inline-flex items-center gap-1.5 font-mono text-[11px] text-[#15803D]" title="Koneksi aktif • Data sinkron otomatis">
                                <span class="w-2 h-2 rounded-full bg-[#15803D] inline-block" :class="{ 'opacity-40 animate-pulse': isSyncing }"></span>
                                <span>Live Realtime</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Stats Quick Glance (shown on wide screens only) -->
            <div class="hidden 2xl:flex items-center gap-6 px-4 py-2 bg-white rounded-xl border border-[#E8DFD1] text-xs shrink-0 shadow-2xs">
                <div class="flex items-center gap-2">
                    <Clock class="w-4 h-4 text-[#A8421E]" />
                    <span class="text-[#7A6A60]">Antrean:</span>
                    <span class="font-bold text-[#231812] font-mono">{{ stats.pending_count }} Order</span>
                </div>
                <div class="h-3 w-px bg-[#E8DFD1]"></div>
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="w-4 h-4 text-[#15803D]" />
                    <span class="text-[#7A6A60]">Selesai Hari Ini:</span>
                    <span class="font-bold text-[#231812] font-mono">{{ stats.today_paid_count }} Order</span>
                </div>
                <div class="h-3 w-px bg-[#E8DFD1]"></div>
                <div
                    @click="openRevenueModal"
                    class="flex items-center gap-2 cursor-pointer hover:bg-[#FAF7F2] px-2 py-1 -my-1 rounded-md transition-colors"
                    title="Klik untuk membuka Rekap Omset Lengkap"
                >
                    <TrendingUp class="w-4 h-4 text-[#15803D]" />
                    <span class="text-[#7A6A60]">Omset:</span>
                    <span class="font-bold text-[#15803D] font-mono">{{ formatRupiah(stats.today_revenue) }}</span>
                </div>
            </div>

            <!-- Right Controls: Structured into 3 clear operational groups -->
            <div class="flex items-center gap-2 shrink-0">
                <!-- Group 1: Operasional Resto -->
                <div class="flex items-center gap-1.5">
                    <!-- Denah Meja Button -->
                    <button
                        type="button"
                        @click="showTableMapModal = true"
                        class="px-3 py-2 rounded-xl text-xs font-bold bg-white hover:bg-[#FAF7F2] text-[#231812] border border-[#E8DFD1] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                        title="Buka Denah & Status Meja Restoran"
                    >
                        <LayoutGrid class="w-3.5 h-3.5 text-[#A8421E]" />
                        <span class="hidden md:inline">Denah</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-[#F2FAF4] text-[#15803D] border border-[#86EFAC]/60 font-mono">
                            {{ availableTablesCount }}
                        </span>
                    </button>

                    <!-- Kelola Menu & Kategori Button -->
                    <button
                        type="button"
                        @click="showMenuModal = true"
                        class="px-3 py-2 rounded-xl text-xs font-bold bg-white hover:bg-[#FAF7F2] text-[#231812] border border-[#E8DFD1] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                        title="Kelola Menu & Kategori (Input manual MySQL)"
                    >
                        <UtensilsCrossed class="w-3.5 h-3.5 text-[#A8421E]" />
                        <span class="hidden lg:inline">Menu</span>
                    </button>
                </div>

                <div class="h-6 w-px bg-[#E8DFD1] mx-0.5"></div>

                <!-- Group 2: Laporan & Penutupan -->
                <div class="flex items-center gap-1.5">
                    <!-- Rekap Omset Button -->
                    <button
                        type="button"
                        @click="openRevenueModal"
                        class="px-3 py-2 rounded-xl text-xs font-bold bg-white hover:bg-[#FAF7F2] text-[#231812] border border-[#E8DFD1] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                        title="Buka Rekap Omset Harian, Mingguan, dan Bulanan"
                    >
                        <BarChart3 class="w-3.5 h-3.5 text-[#D97706]" />
                        <span class="hidden lg:inline">Omset</span>
                    </button>

                    <!-- Tutup Kasir (Z-Report) Button -->
                    <button
                        type="button"
                        @click="printZReport"
                        class="px-3 py-2 rounded-xl text-xs font-bold bg-[#231812] hover:bg-[#3D281F] text-[#F5EBE1] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                        title="Cetak Laporan Resmi Tutup Kasir Shift (Z-Report) ke Printer Thermal"
                    >
                        <Printer class="w-3.5 h-3.5 text-[#F5EBE1]" />
                        <span class="hidden sm:inline">Tutup Kasir</span>
                    </button>
                </div>

                <div class="h-6 w-px bg-[#E8DFD1] mx-0.5"></div>

                <!-- Group 3: Sistem & Pengaturan -->
                <div class="flex items-center gap-1.5">
                    <!-- Printer Struk Button -->
                    <button
                        type="button"
                        @click="showPrinterModal = true"
                        class="px-2.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-[#FAF7F2] text-[#231812] border border-[#E8DFD1] transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                        title="Koneksi & Pengaturan Printer Struk Kasir"
                    >
                        <Printer class="w-3.5 h-3.5 text-[#7A6A60]" />
                        <span class="hidden md:inline">Printer</span>
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="isPrinterConnected ? 'bg-[#15803D]' : 'bg-[#D3C5B4]'"
                            :title="isPrinterConnected ? 'Printer Siap' : 'Printer Belum Konek'"
                        ></span>
                    </button>

                    <!-- Profil Toko & Format Struk Button -->
                    <button
                        type="button"
                        @click="showStoreSettingsModal = true"
                        class="p-2 rounded-xl bg-white hover:bg-[#FAF7F2] text-[#5C4A3E] border border-[#E8DFD1] transition-colors flex items-center cursor-pointer shadow-2xs"
                        title="Atur Profil Resto, Kontak, dan Format Struk"
                    >
                        <Settings class="w-4 h-4" />
                    </button>

                    <!-- Sound Toggle -->
                    <button
                        type="button"
                        @click="soundEnabled = !soundEnabled"
                        class="p-2 rounded-xl border border-[#E8DFD1] bg-white text-[#5C4A3E] hover:bg-[#FAF7F2] transition-colors cursor-pointer shadow-2xs"
                        :title="soundEnabled ? 'Notifikasi Suara Aktif' : 'Notifikasi Suara Senyap'"
                    >
                        <Volume2 v-if="soundEnabled" class="w-4 h-4 text-[#A8421E]" />
                        <VolumeX v-else class="w-4 h-4 text-[#7A6A60]" />
                    </button>

                    <!-- Refresh Button -->
                    <button
                        type="button"
                        @click="fetchOrdersSilently"
                        class="p-2 rounded-xl border border-[#E8DFD1] bg-white text-[#5C4A3E] hover:bg-[#FAF7F2] transition-colors cursor-pointer shadow-2xs"
                        title="Refresh Data"
                    >
                        <RotateCcw class="w-4 h-4" :class="{ 'animate-spin': isSyncing }" />
                    </button>
                </div>

                <!-- Navigation Shortcuts to other screens -->
                <div class="h-6 w-px bg-[#E8DFD1] mx-0.5 hidden sm:block"></div>

                <div class="flex items-center gap-1.5">
                    <a
                        href="/kitchen"
                        target="_blank"
                        class="px-2.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-[#FAF7F2] text-[#231812] border border-[#E8DFD1] transition-colors flex items-center gap-1.5 shadow-2xs"
                        title="Buka Layar Tablet Dapur Koki (KDS)"
                    >
                        <Flame class="w-3.5 h-3.5 text-[#A8421E]" />
                        <span class="hidden xl:inline">Layar Dapur</span>
                    </a>

                    <a
                        href="/display"
                        target="_blank"
                        class="px-2.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-[#FAF7F2] text-[#231812] border border-[#E8DFD1] transition-colors flex items-center gap-1.5 shadow-2xs"
                        title="Buka Layar TV Antrean Pelanggan"
                    >
                        <Tv class="w-3.5 h-3.5 text-[#15803D]" />
                        <span class="hidden xl:inline">Layar TV</span>
                    </a>

                    <Link
                        href="/kiosk"
                        class="px-2.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-[#FAF7F2] text-[#231812] border border-[#E8DFD1] transition-colors flex items-center gap-1.5 shadow-2xs"
                        title="Buka Kiosk Pelanggan"
                    >
                        <Store class="w-3.5 h-3.5 text-[#7A6A60]" />
                        <span class="hidden xl:inline">Kiosk</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Split Screen -->
        <div class="flex-1 min-h-0 grid grid-cols-12 overflow-hidden">
            <!-- ================= LEFT COLUMN: ORDER QUEUE (5 Columns) ================= -->
            <aside class="col-span-12 md:col-span-5 lg:col-span-4 border-r border-[#E8DFD1] bg-white flex flex-col h-full min-h-0 overflow-hidden">
                <!-- Tab Selector & Search -->
                <div class="p-3 border-b border-[#E8DFD1] space-y-2.5 bg-[#FAF7F2]/50 shrink-0">
                    <div class="grid grid-cols-2 gap-1 p-1 bg-[#EFE8DE] rounded-xl text-xs font-bold">
                        <button
                            type="button"
                            @click="activeTab = 'pending'"
                            class="py-2 rounded-lg transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                            :class="activeTab === 'pending' ? 'bg-white text-[#231812] shadow-xs' : 'text-[#7A6A60] hover:text-[#231812]'"
                        >
                            <span>Menunggu</span>
                            <span
                                v-if="pendingOrders.length > 0"
                                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-[#A8421E] text-white font-mono"
                            >
                                {{ pendingOrders.length }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'history'"
                            class="py-2 rounded-lg transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                            :class="activeTab === 'history' ? 'bg-white text-[#231812] shadow-xs' : 'text-[#7A6A60] hover:text-[#231812]'"
                        >
                            <span>Selesai Hari Ini</span>
                            <span class="text-[#7A6A60] text-[10px] font-mono">({{ recentPaidOrders.length }})</span>
                        </button>
                    </div>

                    <!-- Search Input & Clear All Action -->
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <Search class="w-4 h-4 text-[#7A6A60] absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari No. Antrean / Pelanggan..."
                                class="w-full pl-9 pr-3 py-2 text-xs bg-white border border-[#E8DFD1] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#A8421E]/20 focus:border-[#A8421E] text-[#231812]"
                            />
                        </div>

                        <!-- Hapus Semua Button for History Tab -->
                        <button
                            v-if="activeTab === 'history' && recentPaidOrders.length > 0"
                            type="button"
                            @click="clearAllOrders('history')"
                            class="px-2.5 py-2 rounded-xl text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors flex items-center gap-1.5 shrink-0 cursor-pointer shadow-2xs"
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
                            class="px-2.5 py-2 rounded-xl text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors flex items-center gap-1.5 shrink-0 cursor-pointer shadow-2xs"
                            title="Batalkan & hapus semua antrean pesanan"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>Hapus Semua</span>
                        </button>
                    </div>
                </div>

                <!-- Orders Scroll List -->
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain divide-y divide-[#E8DFD1]/50 p-2.5 space-y-2">
                    <div v-if="displayedOrders.length === 0" class="py-16 text-center text-[#7A6A60]">
                        <Layers class="w-10 h-10 mx-auto mb-2 stroke-[1.5] text-[#D3C5B4]" />
                        <p class="text-xs font-medium">Tidak ada pesanan {{ activeTab === 'pending' ? 'yang menunggu pembayaran' : 'yang cocok' }}</p>
                    </div>

                    <div
                        v-for="order in displayedOrders"
                        :key="order.id"
                        @click="selectOrder(order)"
                        class="p-3.5 rounded-2xl border transition-all cursor-pointer relative"
                        :class="[
                            selectedOrderId === order.id
                                ? 'bg-[#FDF8F5] border-[#A8421E] shadow-xs ring-2 ring-[#A8421E]/10'
                                : 'bg-white border-[#E8DFD1] hover:border-[#D3C5B4] hover:bg-[#FAF7F2]/50'
                        ]"
                    >
                        <!-- Top line: Queue Number & Time -->
                        <div class="flex items-center justify-between">
                            <span class="font-mono font-bold text-base text-[#231812]">
                                {{ order.order_number }}
                            </span>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-medium text-[#7A6A60] flex items-center gap-1">
                                    <Clock class="w-3 h-3 text-[#7A6A60]" />
                                    {{ formatRelativeTime(order.created_at) }}
                                </span>
                                <button
                                    type="button"
                                    @click.stop="deleteOrder(order)"
                                    class="p-1 rounded text-[#7A6A60] hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                    title="Hapus pesanan ini dari riwayat"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Customer & Table Badge -->
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-xs font-bold text-[#231812] truncate">
                                {{ order.customer_name }}
                            </span>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wide border"
                                :class="order.order_type === 'dine_in' ? 'bg-[#FAF7F2] text-[#A8421E] border-[#E8DFD1]' : 'bg-[#FAF7F2] text-[#5C4A3E] border-[#E8DFD1]'"
                            >
                                {{ order.order_type === 'dine_in' ? `Meja ${order.table_number || '-'}` : 'Bawa Pulang' }}
                            </span>
                        </div>

                        <!-- Items Snippet -->
                        <div class="mt-2 text-[11px] text-[#7A6A60] line-clamp-1">
                            {{ order.items.map((i) => `${i.quantity}x ${i.product?.name || 'Menu'}`).join(', ') }}
                        </div>

                        <!-- Bottom: Total & Status Indicator -->
                        <div class="mt-2.5 pt-2 border-t border-[#E8DFD1]/60 flex items-center justify-between text-xs">
                            <span class="font-bold text-[#231812] font-mono">
                                {{ formatRupiah(order.total_amount) }}
                            </span>

                            <span
                                class="inline-flex items-center gap-1 font-bold text-[10px] uppercase"
                                :class="order.status === 'paid' ? 'text-[#15803D]' : 'text-[#A8421E]'"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="order.status === 'paid' ? 'bg-[#15803D]' : 'bg-[#A8421E]'"></span>
                                {{ order.status === 'paid' ? 'LUNAS' : 'MENUNGGU' }}
                            </span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ================= RIGHT COLUMN: ORDER DETAILS & PAYMENT (7 or 8 Columns) ================= -->
            <main class="col-span-12 md:col-span-7 lg:col-span-8 flex flex-col h-full min-h-0 bg-[#FAF7F2] overflow-hidden">
                <div v-if="selectedOrder" class="flex-1 min-h-0 flex flex-col lg:flex-row overflow-hidden">
                    <!-- Column A: Order Itemized Details (Left side of right pane) -->
                    <div class="flex-1 min-h-0 flex flex-col border-b lg:border-b-0 lg:border-r border-[#E8DFD1] bg-white overflow-hidden">
                        <!-- Order Header -->
                        <div class="p-4 border-b border-[#E8DFD1] bg-[#FAF7F2]/50 flex items-center justify-between shrink-0">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="font-mono text-xl font-bold text-[#231812]">
                                        {{ selectedOrder.order_number }}
                                    </h2>
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider"
                                        :class="selectedOrder.status === 'paid' ? 'bg-[#DCFCE7] text-[#15803D]' : 'bg-[#FEF3C7] text-[#92400E]'"
                                    >
                                        {{ selectedOrder.status === 'paid' ? 'Lunas' : 'Belum Bayar' }}
                                    </span>

                                    <!-- Kitchen Status -->
                                    <span
                                        v-if="selectedOrder.status === 'paid' && selectedOrder.kitchen_status"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 border"
                                        :class="[
                                            selectedOrder.kitchen_status === 'cooking'
                                                ? 'bg-blue-50 text-blue-800 border-blue-200'
                                                : (selectedOrder.kitchen_status === 'ready'
                                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                    : (selectedOrder.kitchen_status === 'completed'
                                                        ? 'bg-slate-50 text-slate-600 border-slate-200'
                                                        : 'bg-amber-50 text-amber-800 border-amber-200'))
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
                                <div class="text-xs text-[#7A6A60] mt-1 font-medium">
                                    {{ selectedOrder.customer_name }} •
                                    {{ selectedOrder.order_type === 'dine_in' ? `Meja ${selectedOrder.table_number || '-'}` : 'Bawa Pulang' }} •
                                    {{ formatDateTime(selectedOrder.created_at) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="printKitchenTicket(selectedOrder)"
                                    class="text-xs font-bold text-[#A8421E] bg-[#FAF7F2] hover:bg-[#EFE8DE] border border-[#E8DFD1] px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                    title="Cetak Tiket Pesanan untuk Dapur / Koki"
                                >
                                    <UtensilsCrossed class="w-3.5 h-3.5 text-[#A8421E]" />
                                    <span>Tiket Dapur</span>
                                </button>

                                <button
                                    v-if="selectedOrder.status === 'pending_payment'"
                                    type="button"
                                    @click="cancelCurrentOrder"
                                    class="text-xs font-semibold text-rose-600 hover:text-rose-700 px-3 py-1.5 rounded-xl border border-rose-200 hover:bg-rose-50 transition-colors cursor-pointer"
                                >
                                    Batalkan Pesanan
                                </button>

                                <button
                                    type="button"
                                    @click="deleteOrder(selectedOrder)"
                                    class="text-xs font-semibold text-rose-600 hover:text-rose-700 px-3 py-1.5 rounded-xl border border-rose-200 hover:bg-rose-50 transition-colors flex items-center gap-1.5 cursor-pointer"
                                    title="Hapus pesanan ini secara permanen dari riwayat"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Hapus</span>
                                </button>
                            </div>
                        </div>

                        <!-- Itemized List -->
                        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-4 space-y-3 divide-y divide-[#E8DFD1]/50">
                            <div
                                v-for="item in selectedOrder.items"
                                :key="item.id"
                                class="pt-3 first:pt-0"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-bold text-sm text-[#231812]">
                                            {{ item.product?.name || 'Menu Soto' }}
                                        </div>
                                        <div class="text-xs text-[#7A6A60] mt-0.5 font-mono">
                                            {{ item.quantity }} x {{ formatRupiah(item.unit_price) }}
                                        </div>
                                    </div>
                                    <div class="font-bold text-sm text-[#231812] font-mono">
                                        {{ formatRupiah(item.subtotal) }}
                                    </div>
                                </div>

                                <!-- Note badge if present -->
                                <div v-if="item.notes" class="mt-1.5 inline-flex items-center gap-1.5 bg-[#FEF3C7] text-[#92400E] border border-[#FDE68A] rounded-md px-2.5 py-1 text-xs font-bold">
                                    <FileText class="w-3 h-3 text-[#B45309] shrink-0" />
                                    <span>Catatan: {{ item.notes }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bill Breakdown Summary -->
                        <div class="p-4 border-t border-[#E8DFD1] bg-[#FAF7F2]/60 text-xs space-y-1.5">
                            <div class="flex justify-between text-[#7A6A60]">
                                <span>Subtotal</span>
                                <span class="font-bold text-[#231812] font-mono">{{ formatRupiah(selectedOrder.subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-[#7A6A60]">
                                <span>Pajak Resto (0%)</span>
                                <span class="font-bold text-[#231812] font-mono">{{ formatRupiah(selectedOrder.tax || 0) }}</span>
                            </div>
                            <div class="flex justify-between text-base font-bold text-[#231812] pt-2 border-t border-[#E8DFD1]">
                                <span class="font-serif">Total Tagihan</span>
                                <span class="text-[#A8421E] text-lg font-mono">{{ formatRupiah(selectedOrder.total_amount) }}</span>
                            </div>

                            <!-- If already paid, show details & reprint button -->
                            <div v-if="selectedOrder.status === 'paid'" class="mt-3 pt-3 border-t border-[#E8DFD1] text-xs space-y-1">
                                <div class="flex justify-between text-[#7A6A60]">
                                    <span>Metode:</span>
                                    <span class="font-bold uppercase text-[#231812]">{{ selectedOrder.payment_method }}</span>
                                </div>
                                <div v-if="selectedOrder.payment_method === 'cash'" class="flex justify-between text-[#7A6A60]">
                                    <span>Uang Diterima:</span>
                                    <span class="font-bold text-[#231812] font-mono">{{ formatRupiah(selectedOrder.amount_paid) }}</span>
                                </div>
                                <div v-if="selectedOrder.payment_method === 'cash'" class="flex justify-between text-[#7A6A60]">
                                    <span>Kembalian:</span>
                                    <span class="font-bold text-[#15803D] font-mono">{{ formatRupiah(selectedOrder.change_amount || 0) }}</span>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3">
                                    <button
                                        type="button"
                                        @click="callOrderToTv(selectedOrder)"
                                        class="py-2.5 px-2 bg-[#15803D] hover:bg-[#166534] text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer shadow-2xs"
                                        title="Panggil nomor antrean ini ke Layar TV Pelanggan dengan suara bel & ucapan"
                                    >
                                        <Bell class="w-4 h-4 text-emerald-100" />
                                        <span>Panggil TV</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="printOrderReceipt(selectedOrder)"
                                        class="py-2.5 px-2 bg-[#231812] hover:bg-[#3D281F] text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                        title="Cetak Ulang Struk Pembayaran Kasir"
                                    >
                                        <Printer class="w-4 h-4" />
                                        <span>Struk Kasir</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="printKitchenTicket(selectedOrder)"
                                        class="py-2.5 px-2 bg-white hover:bg-[#FAF7F2] text-[#A8421E] border border-[#E8DFD1] rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                        title="Cetak Ulang Tiket Dapur / Koki"
                                    >
                                        <UtensilsCrossed class="w-4 h-4 text-[#A8421E]" />
                                        <span>Tiket Dapur</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="deleteOrder(selectedOrder)"
                                        class="py-2.5 px-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
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
                        class="w-full lg:w-[360px] xl:w-[380px] bg-white border-l border-[#E8DFD1] flex flex-col h-full overflow-hidden shrink-0"
                    >
                        <!-- Scrollable Calculator Body -->
                        <div class="flex-1 overflow-y-auto overscroll-contain p-4 space-y-3">
                            <div class="pb-2 border-b border-[#E8DFD1]">
                                <h3 class="font-serif font-bold text-base text-[#231812]">Pembayaran Tagihan</h3>
                                <p class="text-xs text-[#7A6A60]">Pilih metode pembayaran dan hitung uang kembalian</p>
                            </div>

                            <!-- Error alert -->
                            <div v-if="paymentErrorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-center gap-2 font-medium">
                                <AlertCircle class="w-4 h-4 shrink-0" />
                                <span>{{ paymentErrorMessage }}</span>
                            </div>

                            <!-- Payment Method Selector (Cash / QRIS) -->
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="paymentMethod = 'cash'"
                                    class="py-2.5 px-3 rounded-xl border-2 text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer"
                                    :class="paymentMethod === 'cash' ? 'border-[#A8421E] bg-[#FDF8F5] text-[#A8421E] shadow-2xs' : 'border-[#E8DFD1] text-[#7A6A60] hover:bg-[#FAF7F2]'"
                                >
                                    <Banknote class="w-4 h-4 text-[#A8421E]" />
                                    <span>Tunai (Cash)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="paymentMethod = 'qris'"
                                    class="py-2.5 px-3 rounded-xl border-2 text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer"
                                    :class="paymentMethod === 'qris' ? 'border-[#A8421E] bg-[#FDF8F5] text-[#A8421E] shadow-2xs' : 'border-[#E8DFD1] text-[#7A6A60] hover:bg-[#FAF7F2]'"
                                >
                                    <QrCode class="w-4 h-4 text-[#A8421E]" />
                                    <span>QRIS Resto</span>
                                </button>
                            </div>

                            <!-- CASH PAYMENT SECTION -->
                            <div v-if="paymentMethod === 'cash'" class="space-y-3">
                                <!-- Quick Nominal Buttons -->
                                <div>
                                    <span class="text-[11px] font-bold text-[#7A6A60] block mb-1.5 uppercase tracking-wider">Pilihan Cepat Nominal</span>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <button
                                            type="button"
                                            @click="setAmountPaid(selectedOrder.total_amount)"
                                            class="py-2 px-2 bg-[#FAF7F2] hover:bg-[#EFE8DE] border border-[#E8DFD1] rounded-xl text-xs font-bold text-[#231812] font-mono transition-colors cursor-pointer"
                                        >
                                            Uang Pas
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAmountPaid(50000)"
                                            class="py-2 px-2 bg-[#FAF7F2] hover:bg-[#EFE8DE] border border-[#E8DFD1] rounded-xl text-xs font-bold text-[#231812] font-mono transition-colors cursor-pointer"
                                        >
                                            Rp 50.000
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAmountPaid(100000)"
                                            class="py-2 px-2 bg-[#FAF7F2] hover:bg-[#EFE8DE] border border-[#E8DFD1] rounded-xl text-xs font-bold text-[#231812] font-mono transition-colors cursor-pointer"
                                        >
                                            Rp 100.000
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAmountPaid(200000)"
                                            class="py-2 px-2 bg-[#FAF7F2] hover:bg-[#EFE8DE] border border-[#E8DFD1] rounded-xl text-xs font-bold text-[#231812] font-mono transition-colors cursor-pointer"
                                        >
                                            Rp 200.000
                                        </button>
                                    </div>
                                </div>

                                <!-- Amount Tender Display -->
                                <div>
                                    <div class="flex justify-between items-baseline mb-1">
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#7A6A60]">Uang Diterima</span>
                                        <span class="text-xs font-mono font-bold text-[#231812]">{{ formatRupiah(amountPaid) }}</span>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            :value="amountPaid ? Number(amountPaid).toLocaleString('id-ID') : '0'"
                                            readonly
                                            class="w-full py-2 px-3 text-lg font-mono font-bold text-right bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl text-[#231812] focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <!-- Keypad Numbers (3x4) -->
                                <div class="grid grid-cols-3 gap-1.5">
                                    <button
                                        v-for="key in ['1','2','3','4','5','6','7','8','9','000','0','BACK']"
                                        :key="key"
                                        type="button"
                                        @click="handleKeypad(key)"
                                        class="h-9 rounded-xl border border-[#E8DFD1] bg-[#FAF7F2] hover:bg-white active:bg-[#EFE8DE] text-xs font-bold font-mono text-[#231812] transition-colors cursor-pointer shadow-2xs"
                                    >
                                        {{ key === 'BACK' ? '⌫' : key }}
                                    </button>
                                </div>

                                <!-- Change (Kembalian) Calculation Display -->
                                <div class="p-3 rounded-xl border text-xs" :class="isCashEnough ? 'bg-[#F0FDF4] border-[#BBF7D0]' : 'bg-[#FEF2F2] border-[#FECACA]'">
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold" :class="isCashEnough ? 'text-[#15803D]' : 'text-[#B91C1C]'">
                                            {{ isCashEnough ? 'Kembalian:' : 'Uang Kurang:' }}
                                        </span>
                                        <span
                                            class="text-base font-mono font-black"
                                            :class="isCashEnough ? 'text-[#15803D]' : 'text-[#B91C1C]'"
                                        >
                                            {{ formatRupiah(Math.abs(changeAmount)) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- QRIS PAYMENT SECTION -->
                            <div v-else class="p-4 bg-[#FAF7F2]/60 rounded-2xl border border-[#E8DFD1] text-center space-y-2.5">
                                <span class="text-xs font-bold text-[#231812] uppercase tracking-wide block">
                                    SCAN QRIS UNTUK PEMBAYARAN
                                </span>

                                <!-- Dynamic QR Code Mockup -->
                                <div class="w-36 h-36 mx-auto bg-white p-2 rounded-xl border border-[#E8DFD1] shadow-2xs flex flex-col items-center justify-center">
                                    <img
                                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=TOKO_SOTO_${selectedOrder.order_number}_${selectedOrder.total_amount}`"
                                        alt="QRIS Barcode"
                                        class="w-32 h-32 object-contain"
                                        @error="$event.target.src = ''"
                                    />
                                </div>

                                <div class="text-xs text-[#7A6A60]">
                                    Total: <strong class="text-[#231812] font-mono text-sm">{{ formatRupiah(selectedOrder.total_amount) }}</strong>
                                </div>
                                <div class="text-[11px] text-[#7A6A60]">
                                    Mendukung GoPay, OVO, Dana, ShopeePay, BCA, Mandiri
                                </div>
                            </div>
                        </div>

                        <!-- ALWAYS VISIBLE FIXED BOTTOM BUTTON (SHRINK-0) -->
                        <div class="p-3.5 border-t border-[#E8DFD1] bg-white shrink-0 shadow-xs">
                            <button
                                type="button"
                                @click="confirmPayment"
                                :disabled="isProcessingPayment || (paymentMethod === 'cash' && !isCashEnough)"
                                class="w-full h-12 rounded-xl bg-[#15803D] hover:bg-[#166534] active:scale-[0.99] disabled:opacity-40 disabled:pointer-events-none text-white font-bold text-sm flex items-center justify-center gap-2 transition-all duration-150 shadow-xs cursor-pointer"
                            >
                                <Printer class="w-4 h-4" />
                                <span>{{ isProcessingPayment ? 'Memproses...' : 'Konfirmasi Bayar & Cetak Struk' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State if no order selected -->
                <div v-else class="flex-1 flex items-center justify-center p-8 text-center text-[#7A6A60]">
                    <div>
                        <Store class="w-12 h-12 mx-auto mb-3 text-[#D3C5B4] stroke-[1.5]" />
                        <h3 class="font-serif text-lg font-bold text-[#231812]">Pilih Pesanan di Antrean</h3>
                        <p class="text-xs text-[#7A6A60] mt-1 max-w-sm">
                            Pilih salah satu pesanan dari daftar antrean sebelah kiri untuk melihat rincian menu dan melakukan pembayaran kasir.
                        </p>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
