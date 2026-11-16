<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { formatRupiah } from '@/Utils/formatters';
import KioskTicketPrint from '@/Components/KioskTicketPrint.vue';
import PrinterSettingsModal from '@/Components/PrinterSettingsModal.vue';
import { thermalPrinter } from '@/Utils/thermalPrinter';
import {
    UtensilsCrossed,
    ShoppingBag,
    Plus,
    Minus,
    Trash2,
    FileText,
    CheckCircle2,
    Search,
    ChevronRight,
    ArrowLeft,
    Clock,
    X,
    Sparkles,
    AlertCircle,
    Info,
    Printer,
    Store,
    LayoutGrid,
    Users,
    Check,
    RotateCcw,
    ArrowRight,
    MapPin
} from 'lucide-vue-next';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
    products: {
        type: Array,
        required: true,
    },
    tables: {
        type: Array,
        default: () => [],
    },
    store_settings: {
        type: Object,
        default: () => ({}),
    },
});

// Category & Search State
const activeCategory = ref('all');
const searchQuery = ref('');

// Product Detail Modal State
const selectedProduct = ref(null);
const detailQuantity = ref(1);
const detailNotes = ref('');
const modalScrollBody = ref(null);

function openProductModal(product) {
    selectedProduct.value = product;
    detailQuantity.value = 1;
    detailNotes.value = '';
    nextTick(() => {
        if (modalScrollBody.value) {
            modalScrollBody.value.scrollTop = 0;
        }
    });
}

function closeProductModal() {
    selectedProduct.value = null;
    detailQuantity.value = 1;
    detailNotes.value = '';
}

function applyQuickNoteToDetail(note) {
    if (detailNotes.value) {
        detailNotes.value += ', ' + note;
    } else {
        detailNotes.value = note;
    }
}

function addDetailToCart() {
    if (!selectedProduct.value) return;

    const trimmedNotes = detailNotes.value.trim();
    const existingIndex = cart.value.findIndex(
        (item) => item.product.id === selectedProduct.value.id && (item.notes || '') === trimmedNotes
    );

    if (existingIndex > -1) {
        cart.value[existingIndex].quantity += detailQuantity.value;
    } else {
        cart.value.push({
            product: selectedProduct.value,
            quantity: detailQuantity.value,
            notes: trimmedNotes,
        });
    }

    closeProductModal();
}

// Order Configuration (Dine-in / Takeaway) & Table Selection
const orderType = ref('dine_in'); // 'dine_in' | 'takeaway'
const tableNumber = ref('');
const customerName = ref('');
const showOrderInfoModal = ref(false);

// Table First Flow State (Alur Pelanggan Pilih Meja Dulu Baru Pilih Makanan)
const tablesList = ref(props.tables || []);
const hasSelectedTable = ref(false);
const selectedTable = ref(null);
const tableSelectionError = ref('');
const isRefreshingTables = ref(false);

let tablePollingTimer = null;

const availableTablesCount = computed(() => {
    return tablesList.value.filter((t) => t.status === 'available').length;
});

const occupiedTablesCount = computed(() => {
    return tablesList.value.filter((t) => t.status === 'occupied').length;
});

async function refreshTablesSilently() {
    try {
        isRefreshingTables.value = true;
        const res = await axios.get('/kiosk/api/tables');
        if (res.data?.success && res.data.tables) {
            tablesList.value = res.data.tables;
            // If current selected table is now occupied by someone else, notify & unselect
            if (selectedTable.value) {
                const current = tablesList.value.find((t) => t.id === selectedTable.value.id);
                if (current && current.status === 'occupied') {
                    selectedTable.value = null;
                    tableNumber.value = '';
                    tableSelectionError.value = 'Meja yang Anda pilih baru saja terisi pelanggan lain. Silakan pilih meja kosong lainnya.';
                }
            }
        }
    } catch (e) {
        // silent
    } finally {
        isRefreshingTables.value = false;
    }
}

onMounted(() => {
    tablePollingTimer = setInterval(() => {
        if (!hasSelectedTable.value) {
            refreshTablesSilently();
        }
    }, 6000);
});

onUnmounted(() => {
    if (tablePollingTimer) clearInterval(tablePollingTimer);
});

function selectTable(table) {
    if (table.status === 'occupied') return;
    selectedTable.value = table;
    tableNumber.value = table.table_number;
    tableSelectionError.value = '';
}

function proceedToMenu() {
    tableSelectionError.value = '';
    if (!customerName.value.trim()) {
        tableSelectionError.value = 'Silakan masukkan nama Anda terlebih dahulu.';
        return;
    }

    if (orderType.value === 'dine_in') {
        if (!selectedTable.value) {
            tableSelectionError.value = 'Silakan pilih salah satu meja kosong yang berwarna hijau.';
            return;
        }
        if (selectedTable.value.status === 'occupied') {
            tableSelectionError.value = 'Meja ini baru saja terisi. Silakan pilih meja yang masih kosong.';
            return;
        }
        tableNumber.value = selectedTable.value.table_number;
    } else {
        selectedTable.value = null;
        tableNumber.value = '';
    }

    hasSelectedTable.value = true;
}

function changeTable() {
    refreshTablesSilently();
    tableSelectionError.value = '';
    hasSelectedTable.value = false;
}

// Cart State: array of items: { product, quantity, notes }
const cart = ref([]);
const editingNoteIndex = ref(null);
const currentNoteText = ref('');

// Smart category-aware quick notes for kiosk
function getQuickNotesForProduct(product) {
    if (!product) return [];
    const slug = product.category?.slug || '';
    const catName = (product.category?.name || '').toLowerCase();
    const prodName = (product.name || '').toLowerCase();

    // Minuman (Beverages)
    if (
        slug === 'minuman' ||
        catName.includes('minum') ||
        prodName.includes('es ') ||
        prodName.includes('jus') ||
        prodName.includes('teh') ||
        prodName.includes('jeruk') ||
        prodName.includes('cendol') ||
        prodName.includes('kopi') ||
        prodName.includes('air mineral')
    ) {
        return [
            'Sedikit es batu',
            'Tanpa es batu',
            'Es dipisah',
            'Tanpa gula',
            'Gula dipisah',
            'Manis sedang',
            'Hangat'
        ];
    }

    // Soto / Sup / Makanan Berkuah
    if (
        slug === 'varian-soto' ||
        catName.includes('soto') ||
        catName.includes('sop') ||
        prodName.includes('soto') ||
        prodName.includes('sop')
    ) {
        return [
            'Tanpa daun bawang',
            'Kuah dipisah',
            'Koya banyak',
            'Sambal banyak',
            'Jeruk nipis lebih',
            'Kuah sedikit',
            'Pedas mantap'
        ];
    }

    // Lauk & Pendamping (Telur Asin, Perkedel, Sate, Kerupuk, Emping, Tempe, Tahu, dll)
    if (
        slug === 'lauk-pendamping' ||
        catName.includes('lauk') ||
        catName.includes('pendamping') ||
        prodName.includes('telur') ||
        prodName.includes('perkedel') ||
        prodName.includes('sate') ||
        prodName.includes('emping') ||
        prodName.includes('kerupuk')
    ) {
        return [
            'Sambal dipisah',
            'Hangatkan',
            'Potong 2 bagian',
            'Tanpa sambal',
            'Garing / Renyah',
            'Bungkus terpisah'
        ];
    }

    // Default / General
    return [
        'Sambal dipisah',
        'Tanpa sambal',
        'Bungkus terpisah',
        'Porsi normal'
    ];
}

const activeDetailQuickNotes = computed(() => {
    return getQuickNotesForProduct(selectedProduct.value);
});

const activeEditingCartQuickNotes = computed(() => {
    if (editingNoteIndex.value === null || !cart.value[editingNoteIndex.value]) return [];
    return getQuickNotesForProduct(cart.value[editingNoteIndex.value].product);
});

// Order submission & Success State
const isSubmitting = ref(false);
const orderSuccess = ref(null);
const errorMessage = ref('');
const isTicketPrinted = ref(false);
const isPrintingTicket = ref(false);

// Kiosk Printer Settings State (Discreet Staff Admin)
const showPrinterModal = ref(false);
const kioskPrinterSettings = ref(thermalPrinter.loadSettings('kiosk_printer_settings'));

function onKioskPrinterSaved(newSettings) {
    kioskPrinterSettings.value = newSettings;
}

async function printQueueTicket() {
    if (!orderSuccess.value) return;
    isPrintingTicket.value = true;

    // Direct Bluetooth Thermal Printer
    if (kioskPrinterSettings.value.mode === 'bluetooth' && thermalPrinter.isBluetoothConnected()) {
        try {
            const bytes = thermalPrinter.buildKioskTicketEscPos(orderSuccess.value, kioskPrinterSettings.value.paperSize, props.store_settings);
            await thermalPrinter.sendBytes(bytes);
            isPrintingTicket.value = false;
            isTicketPrinted.value = true;
            return;
        } catch (e) {
            console.error('Kiosk direct Bluetooth print failed, fallback to system:', e);
        }
    } else if (kioskPrinterSettings.value.mode === 'serial' && thermalPrinter.isSerialConnected()) {
        try {
            const bytes = thermalPrinter.buildKioskTicketEscPos(orderSuccess.value, kioskPrinterSettings.value.paperSize, props.store_settings);
            await thermalPrinter.sendBytes(bytes);
            isPrintingTicket.value = false;
            isTicketPrinted.value = true;
            return;
        } catch (e) {
            console.error('Kiosk direct Serial print failed, fallback to system:', e);
        }
    }

    // Default System Print
    await nextTick();
    setTimeout(() => {
        window.print();
        isPrintingTicket.value = false;
        isTicketPrinted.value = true;
    }, 200);
}

// Filtered Products
const filteredProducts = computed(() => {
    return props.products.filter((product) => {
        const matchesCategory =
            activeCategory.value === 'all' ||
            product.category_id === activeCategory.value;

        const matchesSearch =
            !searchQuery.value ||
            product.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (product.description && product.description.toLowerCase().includes(searchQuery.value.toLowerCase()));

        return matchesCategory && matchesSearch;
    });
});

// Cart Calculations
const cartTotalItems = computed(() => {
    return cart.value.reduce((total, item) => total + item.quantity, 0);
});

const cartSubtotal = computed(() => {
    return cart.value.reduce(
        (total, item) => total + item.product.price * item.quantity,
        0
    );
});

const cartTotal = computed(() => {
    return cartSubtotal.value; // Tax can be added if needed
});

// Quantity of a specific product currently in cart
function getProductCartQty(productId) {
    return cart.value
        .filter((item) => item.product.id === productId)
        .reduce((sum, item) => sum + item.quantity, 0);
}

// Count products in a category
function getCategoryCount(categoryId) {
    return props.products.filter((p) => p.category_id === categoryId).length;
}

// Clear all items in cart
function clearCart() {
    if (confirm('Kosongkan semua menu dari keranjang pesanan?')) {
        cart.value = [];
    }
}

// Cart Actions
function addToCart(product) {
    const existingIndex = cart.value.findIndex(
        (item) => item.product.id === product.id && !item.notes
    );

    if (existingIndex > -1) {
        cart.value[existingIndex].quantity += 1;
    } else {
        cart.value.push({
            product,
            quantity: 1,
            notes: '',
        });
    }
}

function updateQuantity(index, delta) {
    const item = cart.value[index];
    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        cart.value.splice(index, 1);
    } else {
        item.quantity = newQty;
    }
}

function removeFromCart(index) {
    cart.value.splice(index, 1);
}

function openNoteModal(index) {
    editingNoteIndex.value = index;
    currentNoteText.value = cart.value[index].notes || '';
}

function saveNote() {
    if (editingNoteIndex.value !== null && cart.value[editingNoteIndex.value]) {
        cart.value[editingNoteIndex.value].notes = currentNoteText.value.trim();
    }
    editingNoteIndex.value = null;
    currentNoteText.value = '';
}

function applyQuickNote(note) {
    if (currentNoteText.value) {
        currentNoteText.value += ', ' + note;
    } else {
        currentNoteText.value = note;
    }
}

// Open checkout confirmation
function startCheckout() {
    if (cart.value.length === 0) return;
    errorMessage.value = '';
    showOrderInfoModal.value = true;
}

// Submit Order to backend
async function submitOrder() {
    if (!customerName.value.trim()) {
        errorMessage.value = 'Silakan masukkan nama pemesan.';
        return;
    }

    if (orderType.value === 'dine_in' && !tableNumber.value.trim()) {
        errorMessage.value = 'Silakan masukkan nomor meja untuk makan di tempat.';
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const payload = {
            customer_name: customerName.value.trim(),
            order_type: orderType.value,
            table_number: orderType.value === 'dine_in' ? tableNumber.value.trim() : null,
            items: cart.value.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
                notes: item.notes || null,
            })),
        };

        const response = await axios.post('/kiosk/order', payload);

        if (response.data && response.data.order) {
            orderSuccess.value = response.data.order;
            showOrderInfoModal.value = false;
            // Clear cart
            cart.value = [];

            // If auto-print enabled in kiosk printer settings
            if (kioskPrinterSettings.value.autoPrint) {
                printQueueTicket();
            }
        }
    } catch (error) {
        if (error.response && error.response.data && error.response.data.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = 'Terjadi kesalahan saat memproses pesanan. Silakan coba lagi.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

// Reset for next customer
function resetKiosk() {
    orderSuccess.value = null;
    isTicketPrinted.value = false;
    isPrintingTicket.value = false;
    customerName.value = '';
    tableNumber.value = '';
    selectedTable.value = null;
    orderType.value = 'dine_in';
    hasSelectedTable.value = false;
    cart.value = [];
    activeCategory.value = 'all';
    searchQuery.value = '';
    errorMessage.value = '';
    tableSelectionError.value = '';
    refreshTablesSilently();
}
</script>

<template>
    <Head title="Soto Betawi Parsoey - Pemesanan Mandiri" />

    <div class="min-h-screen bg-[#FAF7F2] flex flex-col font-sans text-[#231812]">
        <!-- Top Navigation / Header -->
        <header class="bg-[#FAF7F2]/95 backdrop-blur-md border-b border-[#E8DFD1] sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-[#231812] flex items-center justify-center text-[#F5EBE1] font-serif font-black text-base tracking-wider shadow-sm border border-[#3D281F]">
                        SBP
                    </div>
                    <div>
                        <h1 class="font-serif text-lg sm:text-xl font-bold text-[#231812] tracking-tight leading-tight">Soto Betawi Parsoey</h1>
                        <p class="text-xs text-[#7A6A60] font-medium">Pemesanan Mandiri Resto</p>
                    </div>
                </div>

                <!-- Active Stage Header Controls -->
                <div class="flex items-center gap-3">
                    <!-- When on Stage 2 (Catalog): Show Current Table & Ganti Meja -->
                    <div v-if="hasSelectedTable" class="flex items-center gap-2">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-white border border-[#E8DFD1] shadow-2xs text-xs">
                            <span class="inline-flex items-center gap-1.5 font-bold text-[#231812]">
                                <UtensilsCrossed v-if="orderType === 'dine_in'" class="w-3.5 h-3.5 text-[#A8421E]" />
                                <ShoppingBag v-else class="w-3.5 h-3.5 text-[#A8421E]" />
                                <span>{{ orderType === 'dine_in' ? (selectedTable?.name || `Meja ${tableNumber}`) : 'Bawa Pulang' }}</span>
                            </span>
                            <span class="text-[#D3C5B4]">•</span>
                            <span class="text-[#5C4A3E] font-medium truncate max-w-[120px]">{{ customerName }}</span>
                            <button
                                type="button"
                                @click="changeTable"
                                class="ml-1 text-[11px] font-bold text-[#A8421E] hover:text-[#7D2E12] transition-colors cursor-pointer flex items-center gap-1"
                                title="Ganti nomor meja atau ubah tipe pesanan"
                            >
                                <RotateCcw class="w-3 h-3" />
                                <span>Ubah</span>
                            </button>
                        </div>
                    </div>

                    <!-- Kiosk Operational Status -->
                    <div class="hidden md:flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white border border-[#E8DFD1] text-xs font-semibold text-[#5C4A3E]">
                        <span class="w-2 h-2 rounded-full bg-[#15803D]"></span>
                        <span>Buka • Siap Melayani</span>
                    </div>

                    <!-- Discreet Staff/Admin Kiosk Printer Button -->
                    <button
                        type="button"
                        @click="showPrinterModal = true"
                        class="p-2.5 rounded-xl text-[#7A6A60] hover:text-[#231812] hover:bg-white border border-transparent hover:border-[#E8DFD1] transition-colors flex items-center justify-center cursor-pointer"
                        title="Pengaturan Printer Tiket Kiosk (Staff/Admin)"
                    >
                        <Printer class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </header>

        <!-- STAGE 1: TABLE & SERVICE SELECTION FIRST (Pelanggan milih meja kosong dulu baru milih makanannya) -->
        <div v-if="!hasSelectedTable" class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-12 flex flex-col justify-center animate-in fade-in duration-200">
            <!-- Editorial Hero Introduction (Authentic Culinary, NO AI Blobs) -->
            <div class="text-center max-w-xl mx-auto mb-8">
                <span class="inline-block px-3 py-1 rounded-full bg-[#EFE8DE] text-[#7A6A60] text-xs font-bold tracking-widest uppercase mb-3">
                    Langkah 1 • Tipe Layanan
                </span>
                <h2 class="font-serif text-3xl sm:text-4xl font-bold text-[#231812] tracking-tight">
                    Mau makan di mana hari ini?
                </h2>
                <p class="text-sm text-[#7A6A60] mt-2 leading-relaxed">
                    Silakan pilih santap hangat langsung di meja makan atau dikemas rapi untuk dibawa pulang.
                </p>
            </div>

            <!-- Selection Box -->
            <div class="bg-white rounded-3xl border border-[#E8DFD1] shadow-sm p-6 sm:p-8 space-y-7">
                <!-- Service Type Toggle Cards -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-[#7A6A60] mb-3">
                        Pilihan Layanan
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Dine-in Option -->
                        <button
                            type="button"
                            @click="orderType = 'dine_in'"
                            class="p-5 rounded-2xl border-2 text-left transition-all flex items-start gap-4 cursor-pointer"
                            :class="orderType === 'dine_in'
                                ? 'border-[#A8421E] bg-[#FDF8F5] ring-2 ring-[#A8421E]/10 shadow-xs'
                                : 'border-[#E8DFD1] hover:border-[#D3C5B4] bg-[#FAF7F2]/40'"
                        >
                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                                :class="orderType === 'dine_in' ? 'bg-[#A8421E] text-white shadow-xs' : 'bg-[#EFE8DE] text-[#5C4A3E]'"
                            >
                                <UtensilsCrossed class="w-6 h-6" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-serif text-lg font-bold text-[#231812]">Makan di Meja</h3>
                                    <span v-if="orderType === 'dine_in'" class="w-5 h-5 rounded-full bg-[#A8421E] text-white flex items-center justify-center text-xs">
                                        <Check class="w-3.5 h-3.5 stroke-[3]" />
                                    </span>
                                </div>
                                <p class="text-xs text-[#7A6A60] mt-1 leading-relaxed">
                                    Santap hangat di resto, pilih nomor meja makan yang masih kosong di bawah.
                                </p>
                            </div>
                        </button>

                        <!-- Takeaway Option -->
                        <button
                            type="button"
                            @click="orderType = 'takeaway'; selectedTable = null; tableNumber = ''"
                            class="p-5 rounded-2xl border-2 text-left transition-all flex items-start gap-4 cursor-pointer"
                            :class="orderType === 'takeaway'
                                ? 'border-[#A8421E] bg-[#FDF8F5] ring-2 ring-[#A8421E]/10 shadow-xs'
                                : 'border-[#E8DFD1] hover:border-[#D3C5B4] bg-[#FAF7F2]/40'"
                        >
                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                                :class="orderType === 'takeaway' ? 'bg-[#A8421E] text-white shadow-xs' : 'bg-[#EFE8DE] text-[#5C4A3E]'"
                            >
                                <ShoppingBag class="w-6 h-6" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-serif text-lg font-bold text-[#231812]">Bawa Pulang</h3>
                                    <span v-if="orderType === 'takeaway'" class="w-5 h-5 rounded-full bg-[#A8421E] text-white flex items-center justify-center text-xs">
                                        <Check class="w-3.5 h-3.5 stroke-[3]" />
                                    </span>
                                </div>
                                <p class="text-xs text-[#7A6A60] mt-1 leading-relaxed">
                                    Pesanan dibungkus rapat & higienis, praktis dibawa pulang tanpa reservasi meja.
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Table Grid Section (Only for Dine-in) -->
                <div v-if="orderType === 'dine_in'" class="pt-5 border-t border-[#E8DFD1]/80">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-[#231812]">
                                Pilih Meja Santap
                            </label>
                            <p class="text-xs text-[#7A6A60] mt-0.5">Pilih salah satu nomor meja yang masih tersedia di area makan.</p>
                        </div>

                        <!-- Legend & Refresh -->
                        <div class="flex items-center gap-3 text-xs">
                            <span class="inline-flex items-center gap-1.5 font-semibold text-[#15803D] bg-[#F2FAF4] px-2.5 py-1 rounded-full border border-[#86EFAC]/60">
                                <span class="w-2 h-2 rounded-full bg-[#15803D]"></span>
                                <span>{{ availableTablesCount }} Kosong</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 font-semibold text-[#B91C1C] bg-[#FEF2F2] px-2.5 py-1 rounded-full border border-[#FECACA]">
                                <span class="w-2 h-2 rounded-full bg-[#B91C1C]"></span>
                                <span>{{ occupiedTablesCount }} Terisi</span>
                            </span>
                            <button
                                type="button"
                                @click="refreshTablesSilently"
                                class="p-1.5 text-[#7A6A60] hover:text-[#231812] transition-colors cursor-pointer rounded-lg hover:bg-[#FAF7F2]"
                                title="Perbarui ketersediaan meja"
                            >
                                <RotateCcw class="w-4 h-4" :class="{ 'animate-spin': isRefreshingTables }" />
                            </button>
                        </div>
                    </div>

                    <!-- Grid of Tables -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3.5">
                        <div
                            v-for="table in tablesList"
                            :key="table.id"
                            @click="selectTable(table)"
                            class="p-4 rounded-2xl border-2 transition-all flex flex-col justify-between select-none relative overflow-hidden"
                            :class="[
                                table.status === 'occupied'
                                    ? 'border-[#FCA5A5]/60 bg-[#FEF2F2]/40 opacity-50 cursor-not-allowed'
                                    : (selectedTable?.id === table.id
                                        ? 'border-[#A8421E] bg-[#FDF8F5] ring-2 ring-[#A8421E]/20 shadow-xs cursor-pointer scale-[1.02]'
                                        : 'border-[#E8DFD1] bg-white hover:border-[#15803D]/60 hover:shadow-xs cursor-pointer active:scale-95')
                            ]"
                        >
                            <div class="flex items-start justify-between gap-1 mb-2">
                                <span class="font-serif font-bold text-base text-[#231812]">
                                    {{ table.name }}
                                </span>
                                <span class="text-[11px] font-semibold text-[#7A6A60] flex items-center gap-1 bg-[#FAF7F2] px-2 py-0.5 rounded-md border border-[#E8DFD1]/60">
                                    <Users class="w-3 h-3 text-[#7A6A60]" />
                                    {{ table.capacity }} Org
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs mt-1">
                                <template v-if="table.status === 'occupied'">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#B91C1C]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#B91C1C]"></span>
                                        Terisi
                                    </span>
                                </template>
                                <template v-else-if="selectedTable?.id === table.id">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-black text-[#A8421E] bg-[#F5E6E0] px-2 py-0.5 rounded-md">
                                        <CheckCircle2 class="w-3.5 h-3.5 text-[#A8421E]" />
                                        Terpilih
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#15803D]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803D]"></span>
                                        Tersedia
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Name Input -->
                <div class="pt-5 border-t border-[#E8DFD1]/80">
                    <label class="block text-xs font-bold uppercase tracking-wider text-[#231812] mb-1.5">
                        Nama Pemesan <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-model="customerName"
                        type="text"
                        placeholder="Masukkan nama Anda (Contoh: Budi, Siti, Pak Joko)..."
                        class="w-full px-4 py-3 text-base font-medium bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#A8421E]/20 focus:border-[#A8421E] transition-colors text-[#231812]"
                        @keyup.enter="proceedToMenu"
                    />
                </div>

                <!-- Validation Warning / Error -->
                <div v-if="tableSelectionError" class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-semibold flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ tableSelectionError }}</span>
                </div>

                <!-- Proceed Action Button -->
                <div class="pt-2">
                    <button
                        type="button"
                        @click="proceedToMenu"
                        class="w-full h-14 rounded-2xl bg-[#A8421E] hover:bg-[#8F3314] active:scale-[0.99] text-white font-bold text-base flex items-center justify-center gap-3 shadow-md transition-all cursor-pointer"
                    >
                        <span>
                            {{
                                orderType === 'dine_in'
                                    ? (selectedTable ? `Lanjut Pilih Menu (${selectedTable.name})` : 'Pilih Nomor Meja Dahulu')
                                    : 'Lanjut Pilih Menu (Bawa Pulang)'
                            }}
                        </span>
                        <ArrowRight class="w-5 h-5 stroke-[2.5]" />
                    </button>
                </div>
            </div>
        </div>

        <!-- STAGE 2: FOOD CATALOG & CART (Muncul setelah pelanggan milih meja / tipe pesanan) -->
        <div v-else class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start animate-in fade-in duration-200">
            <!-- Left Area: Categories & Products (8 Columns) -->
            <main class="lg:col-span-8 flex flex-col gap-6">
                <!-- Search & Category Filter Bar -->
                <div class="bg-white border border-[#E8DFD1] rounded-2xl p-4 flex flex-col sm:flex-row gap-3.5 items-center justify-between shadow-xs">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-72">
                        <Search class="w-4 h-4 text-[#7A6A60] absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari soto, lauk, minuman..."
                            class="w-full pl-10 pr-4 py-2.5 text-sm bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#A8421E]/20 focus:border-[#A8421E] transition-colors text-[#231812]"
                        />
                    </div>

                    <!-- Categories Scrollable Bar -->
                    <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto pt-1 pb-2 category-scroll-bar">
                        <button
                            type="button"
                            @click="activeCategory = 'all'"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold whitespace-nowrap transition-colors duration-150 shrink-0 flex items-center gap-2 cursor-pointer border"
                            :class="activeCategory === 'all' ? 'bg-[#231812] text-white border-[#231812] shadow-xs' : 'bg-[#FAF7F2] text-[#5C4A3E] hover:bg-[#EFE8DE] border-[#E8DFD1]'"
                        >
                            <span>Semua Menu</span>
                            <span
                                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold font-mono"
                                :class="activeCategory === 'all' ? 'bg-white/20 text-white' : 'bg-[#E8DFD1] text-[#5C4A3E]'"
                            >
                                {{ products.length }}
                            </span>
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            type="button"
                            @click="activeCategory = cat.id"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold whitespace-nowrap transition-colors duration-150 shrink-0 flex items-center gap-2 cursor-pointer border"
                            :class="activeCategory === cat.id ? 'bg-[#231812] text-white border-[#231812] shadow-xs' : 'bg-[#FAF7F2] text-[#5C4A3E] hover:bg-[#EFE8DE] border-[#E8DFD1]'"
                        >
                            <span>{{ cat.name }}</span>
                            <span
                                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold font-mono"
                                :class="activeCategory === cat.id ? 'bg-white/20 text-white' : 'bg-[#E8DFD1] text-[#5C4A3E]'"
                            >
                                {{ getCategoryCount(cat.id) }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Products Grid (3 Columns) -->
                <div v-if="filteredProducts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="openProductModal(product)"
                        class="bg-white rounded-2xl overflow-hidden flex flex-col justify-between hover:shadow-md transition-all cursor-pointer group relative border border-[#E8DFD1] hover:border-[#A8421E]/40"
                        :class="[
                            getProductCartQty(product.id) > 0
                                ? 'border-[#A8421E] ring-2 ring-[#A8421E]/15 bg-[#FDF8F5]/30'
                                : ''
                        ]"
                    >
                        <!-- Image Container -->
                        <div class="relative h-48 bg-[#FAF7F2] overflow-hidden">
                            <!-- Already in Cart Indicator Badge -->
                            <div
                                v-if="getProductCartQty(product.id) > 0"
                                class="absolute top-2.5 left-2.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-[#A8421E] text-white shadow-xs flex items-center gap-1 z-10"
                            >
                                <CheckCircle2 class="w-3.5 h-3.5 stroke-[2.5]" />
                                <span>{{ getProductCartQty(product.id) }}x di Pesanan</span>
                            </div>

                            <img
                                :src="product.image_url"
                                :alt="product.name"
                                class="w-full h-full object-cover group-hover:scale-102 transition-transform duration-200"
                                loading="lazy"
                                @error="$event.target.style.display = 'none'"
                            />
                            <div class="absolute top-2.5 right-2.5">
                                <span class="px-2.5 py-1 text-[10px] font-bold tracking-wider uppercase bg-white/95 text-[#231812] rounded-md border border-[#E8DFD1] shadow-2xs">
                                    {{ product.category?.name }}
                                </span>
                            </div>
                        </div>

                        <!-- Product Content -->
                        <div class="p-4 flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="font-serif font-bold text-[#231812] text-base leading-snug line-clamp-1 group-hover:text-[#A8421E] transition-colors">
                                    {{ product.name }}
                                </h3>
                                <p class="text-xs text-[#7A6A60] mt-1 line-clamp-2 leading-relaxed">
                                    {{ product.description }}
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-[#E8DFD1]/60 flex items-center justify-between">
                                <div>
                                    <span class="text-[11px] text-[#7A6A60] block font-medium">Harga</span>
                                    <span class="text-base font-bold text-[#231812] font-mono">
                                        {{ formatRupiah(product.price) }}
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    @click.stop="addToCart(product)"
                                    class="h-10 px-4 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition-colors cursor-pointer shadow-2xs"
                                    :class="getProductCartQty(product.id) > 0 ? 'bg-[#A8421E] hover:bg-[#8F3314]' : 'bg-[#231812] hover:bg-[#3D281F]'"
                                    :title="getProductCartQty(product.id) > 0 ? 'Tambah lagi ke pesanan' : 'Tambah ke pesanan'"
                                >
                                    <Plus class="w-4 h-4 stroke-[2.5]" />
                                    <span>{{ getProductCartQty(product.id) > 0 ? `Tambah (${getProductCartQty(product.id)})` : 'Pilih' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state if database has no products yet -->
                <div v-else-if="products.length === 0" class="bg-white border border-[#E8DFD1] rounded-2xl p-12 text-center shadow-xs">
                    <div class="w-16 h-16 rounded-2xl bg-[#FAF7F2] text-[#A8421E] flex items-center justify-center mx-auto mb-3 border border-[#E8DFD1]">
                        <UtensilsCrossed class="w-8 h-8" />
                    </div>
                    <h3 class="font-serif text-lg font-bold text-[#231812]">Menu Masih Kosong</h3>
                    <p class="text-sm text-[#7A6A60] mt-1 max-w-md mx-auto">
                        Belum ada menu yang didaftarkan. Anda dapat menginput kategori dan menu makanan melalui halaman Kasir POS.
                    </p>
                    <a
                        href="/pos"
                        class="inline-flex items-center gap-2 mt-5 px-5 py-2.5 rounded-xl text-sm font-bold bg-[#A8421E] hover:bg-[#8F3314] text-white shadow-xs transition-colors"
                    >
                        <Store class="w-4 h-4" />
                        <span>Buka Kasir POS & Kelola Menu</span>
                    </a>
                </div>

                <!-- Empty state if search finds nothing -->
                <div v-else class="bg-white border border-[#E8DFD1] rounded-2xl p-12 text-center">
                    <UtensilsCrossed class="w-12 h-12 text-[#D3C5B4] mx-auto mb-3" />
                    <h3 class="font-serif text-lg font-bold text-[#231812]">Menu Tidak Ditemukan</h3>
                    <p class="text-sm text-[#7A6A60] mt-1">
                        Coba gunakan kata kunci lain atau pilih kategori berbeda.
                    </p>
                    <button
                        type="button"
                        @click="searchQuery = ''; activeCategory = 'all'"
                        class="mt-4 px-4 py-2 text-sm font-semibold text-[#A8421E] hover:text-[#8F3314] bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl transition-colors cursor-pointer"
                    >
                        Tampilkan Semua Menu
                    </button>
                </div>
            </main>

            <!-- Right Area: Cart & Checkout (4 Columns, Sticky) -->
            <aside class="lg:col-span-4 lg:sticky lg:top-24">
                <div class="bg-white border border-[#E8DFD1] rounded-2xl overflow-hidden shadow-sm">
                    <!-- Cart Header -->
                    <div class="p-4 border-b border-[#E8DFD1] flex items-center justify-between bg-[#FAF7F2]">
                        <div class="flex items-center gap-2.5">
                            <ShoppingBag class="w-5 h-5 text-[#A8421E]" />
                            <h2 class="font-serif font-bold text-[#231812] text-base">Pesanan Anda</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#EFE8DE] text-[#5C4A3E] font-mono">
                                {{ cartTotalItems }} Menu
                            </span>
                            <button
                                v-if="cart.length > 0"
                                type="button"
                                @click="clearCart"
                                class="text-xs font-medium text-[#7A6A60] hover:text-rose-600 transition-colors ml-1 cursor-pointer"
                                title="Kosongkan seluruh keranjang"
                            >
                                Kosongkan
                            </button>
                        </div>
                    </div>

                    <!-- Cart Items List -->
                    <div class="p-4 max-h-[460px] overflow-y-auto overscroll-contain space-y-3 divide-y divide-[#E8DFD1]/60">
                        <div v-if="cart.length === 0" class="py-10 text-center">
                            <div class="w-14 h-14 rounded-full bg-[#FAF7F2] text-[#D3C5B4] flex items-center justify-center mx-auto mb-3 border border-[#E8DFD1]">
                                <ShoppingBag class="w-6 h-6 stroke-[1.5]" />
                            </div>
                            <h4 class="font-serif font-bold text-sm text-[#231812]">Pesanan Masih Kosong</h4>
                            <p class="text-xs text-[#7A6A60] mt-1">
                                Pilih menu soto atau minuman di sebelah kiri untuk mulai memesan.
                            </p>
                        </div>

                        <div
                            v-for="(item, index) in cart"
                            :key="index"
                            class="pt-3 first:pt-0"
                        >
                            <div class="flex items-start gap-2.5">
                                <img
                                    v-if="item.product.image_url"
                                    :src="item.product.image_url"
                                    :alt="item.product.name"
                                    class="w-11 h-11 rounded-xl object-cover bg-[#FAF7F2] border border-[#E8DFD1] shrink-0"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-1">
                                        <h4 class="font-bold text-xs text-[#231812] leading-tight line-clamp-1">
                                            {{ item.product.name }}
                                        </h4>
                                        <button
                                            type="button"
                                            @click="removeFromCart(index)"
                                            class="text-[#D3C5B4] hover:text-rose-600 p-0.5 rounded transition-colors shrink-0 cursor-pointer"
                                            title="Hapus menu ini"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                    <span class="text-xs font-bold text-[#A8421E] block mt-0.5 font-mono">
                                        {{ formatRupiah(item.product.price) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Note & Quantity Stepper Row -->
                            <div class="mt-2.5 flex items-center justify-between gap-2">
                                <button
                                    v-if="!item.notes"
                                    type="button"
                                    @click="openNoteModal(index)"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#7A6A60] hover:text-[#A8421E] transition-colors cursor-pointer"
                                >
                                    <FileText class="w-3 h-3" />
                                    <span>+ Catatan</span>
                                </button>
                                <div v-else class="flex items-center gap-1 text-[11px] bg-[#FAF7F2] text-[#5C4A3E] border border-[#E8DFD1] rounded px-2 py-0.5 max-w-[150px]">
                                    <span class="italic truncate text-[10px]">{{ item.notes }}</span>
                                    <button
                                        type="button"
                                        @click="openNoteModal(index)"
                                        class="underline ml-0.5 text-[#A8421E] font-bold shrink-0 cursor-pointer"
                                    >
                                        Ubah
                                    </button>
                                </div>

                                <div class="flex items-center gap-1 bg-[#FAF7F2] p-0.5 rounded-lg border border-[#E8DFD1] shrink-0">
                                    <button
                                        type="button"
                                        @click="updateQuantity(index, -1)"
                                        class="w-7 h-7 rounded-md bg-white hover:bg-[#EFE8DE] active:bg-[#E8DFD1] text-[#231812] flex items-center justify-center font-bold text-xs shadow-2xs transition-colors cursor-pointer"
                                    >
                                        <Minus class="w-3 h-3" />
                                    </button>
                                    <span class="w-6 text-center font-bold text-xs text-[#231812] font-mono">
                                        {{ item.quantity }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="updateQuantity(index, 1)"
                                        class="w-7 h-7 rounded-md bg-white hover:bg-[#EFE8DE] active:bg-[#E8DFD1] text-[#231812] flex items-center justify-center font-bold text-xs shadow-2xs transition-colors cursor-pointer"
                                    >
                                        <Plus class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Summary & Checkout -->
                    <div class="p-4 border-t border-[#E8DFD1] bg-[#FAF7F2] space-y-3">
                        <div class="space-y-1.5 text-xs text-[#7A6A60]">
                            <div class="flex justify-between">
                                <span>Tipe Pesanan</span>
                                <span class="font-bold text-[#231812]">
                                    {{ orderType === 'dine_in' ? 'Makan di Meja' : 'Bawa Pulang' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Subtotal</span>
                                <span class="font-semibold text-[#231812] font-mono">{{ formatRupiah(cartSubtotal) }}</span>
                            </div>
                        </div>

                        <div class="pt-2.5 border-t border-[#E8DFD1] flex justify-between items-baseline">
                            <span class="text-sm font-bold text-[#231812]">Total Tagihan</span>
                            <span class="text-lg font-bold text-[#A8421E] font-mono">{{ formatRupiah(cartTotal) }}</span>
                        </div>

                        <button
                            type="button"
                            @click="startCheckout"
                            :disabled="cart.length === 0"
                            class="w-full h-12 bg-[#A8421E] hover:bg-[#8F3314] active:bg-[#7D2E12] disabled:opacity-40 disabled:pointer-events-none text-white font-bold rounded-xl flex items-center justify-center gap-2 transition-colors cursor-pointer shadow-xs"
                        >
                            <span>Lanjut ke Pembayaran</span>
                            <ChevronRight class="w-4 h-4 stroke-[2.5]" />
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Floating Mobile Cart Bar (visible only on small screens when cart has items) -->
        <div
            v-if="hasSelectedTable && cartTotalItems > 0"
            class="lg:hidden fixed bottom-4 inset-x-4 z-40 bg-[#231812] text-white rounded-2xl p-3 px-4 shadow-xl flex items-center justify-between border border-[#3D281F] animate-in slide-in-from-bottom duration-200"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#A8421E] flex items-center justify-center font-bold text-sm font-mono">
                    {{ cartTotalItems }}
                </div>
                <div>
                    <div class="text-xs text-[#D3C5B4]">{{ cartTotalItems }} Menu Dipilih</div>
                    <div class="text-sm font-bold font-mono text-white">{{ formatRupiah(cartTotal) }}</div>
                </div>
            </div>

            <button
                type="button"
                @click="startCheckout"
                class="px-5 py-2.5 rounded-xl bg-[#A8421E] hover:bg-[#8F3314] active:bg-[#7D2E12] text-white font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer"
            >
                <span>Bayar Sekarang</span>
                <ChevronRight class="w-4 h-4" />
            </button>
        </div>

        <!-- Clean Footer for Kiosk -->
        <footer class="mt-auto bg-white border-t border-[#E8DFD1] py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-[#231812] flex items-center justify-center text-[#F5EBE1] font-serif font-black text-xs shadow-xs">
                            SBP
                        </div>
                        <div>
                            <span class="font-serif font-bold text-sm text-[#231812]">Soto Betawi Parsoey</span>
                            <p class="text-xs text-[#7A6A60] mt-0.5">
                                Cita Rasa Asli Soto Betawi • Kuah Santan & Susu Rempah Pilihan
                            </p>
                        </div>
                    </div>

                    <div class="text-center sm:text-right text-xs text-[#7A6A60]">
                        <div>Butuh bantuan memesan? Silakan tanyakan kepada staf kasir kami.</div>
                        <div class="text-[11px] text-[#A69588] mt-0.5 font-mono">
                            © {{ new Date().getFullYear() }} Soto Betawi Parsoey.
                        </div>
                    </div>
                </div>
            </div>
        </footer>

        <!-- MODAL 1: Item Custom Note -->
        <div
            v-if="editingNoteIndex !== null"
            class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4 backdrop-blur-2xs"
        >
            <div class="bg-white rounded-2xl border border-[#E8DFD1] max-w-md w-full p-6 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-[#E8DFD1]">
                    <h3 class="font-serif font-bold text-base text-[#231812]">
                        Catatan: {{ cart[editingNoteIndex]?.product?.name }}
                    </h3>
                    <button
                        type="button"
                        @click="editingNoteIndex = null"
                        class="text-[#7A6A60] hover:text-[#231812] p-1 cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-[#7A6A60] mb-1.5">
                        Pilihan Catatan Cepat
                    </label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button
                            v-for="note in activeEditingCartQuickNotes"
                            :key="note"
                            type="button"
                            @click="applyQuickNote(note)"
                            class="px-2.5 py-1.5 text-xs bg-[#FAF7F2] hover:bg-[#F5E6E0] hover:text-[#A8421E] text-[#5C4A3E] border border-[#E8DFD1] rounded-lg font-medium transition-colors cursor-pointer"
                        >
                            + {{ note }}
                        </button>
                    </div>

                    <label class="block text-xs font-semibold text-[#7A6A60] mb-1.5">
                        Catatan Tambahan
                    </label>
                    <textarea
                        v-model="currentNoteText"
                        rows="3"
                        placeholder="Contoh: Kuah dipisah, koya banyak, jangan pakai emping..."
                        class="w-full p-3 text-sm bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#A8421E]/20 focus:border-[#A8421E] transition-colors text-[#231812]"
                    ></textarea>
                </div>

                <div class="mt-5 flex justify-end gap-2.5">
                    <button
                        type="button"
                        @click="editingNoteIndex = null"
                        class="px-4 py-2.5 rounded-xl text-sm font-semibold text-[#7A6A60] hover:bg-[#FAF7F2] transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="saveNote"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-[#A8421E] hover:bg-[#8F3314] text-white transition-colors cursor-pointer shadow-xs"
                    >
                        Simpan Catatan
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 2: Customer & Table Info Confirmation -->
        <div
            v-if="showOrderInfoModal"
            class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4 backdrop-blur-2xs"
        >
            <div class="bg-white rounded-2xl border border-[#E8DFD1] max-w-md w-full p-6 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-[#E8DFD1]">
                    <h3 class="font-serif font-bold text-base text-[#231812]">
                        Konfirmasi Pesanan
                    </h3>
                    <button
                        type="button"
                        @click="showOrderInfoModal = false"
                        class="text-[#7A6A60] hover:text-[#231812] p-1 cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="errorMessage" class="mt-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>

                <div class="mt-4 space-y-4">
                    <!-- Pre-selected Table & Service Summary Card -->
                    <div class="p-4 bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-[#7A6A60]">Tipe Layanan:</span>
                            <span class="text-xs font-bold text-[#231812] flex items-center gap-1.5">
                                <UtensilsCrossed v-if="orderType === 'dine_in'" class="w-3.5 h-3.5 text-[#A8421E]" />
                                <ShoppingBag v-else class="w-3.5 h-3.5 text-[#A8421E]" />
                                <span>{{ orderType === 'dine_in' ? (selectedTable?.name || `Meja ${tableNumber}`) : 'Bawa Pulang' }}</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-xs text-[#7A6A60]">Nama Pemesan:</span>
                            <span class="text-xs font-bold text-[#231812]">{{ customerName }}</span>
                        </div>

                        <div class="pt-2 border-t border-[#E8DFD1] flex justify-end">
                            <button
                                type="button"
                                @click="showOrderInfoModal = false; changeTable()"
                                class="text-[11px] font-bold text-[#A8421E] hover:text-[#7D2E12] underline cursor-pointer flex items-center gap-1"
                            >
                                <RotateCcw class="w-3 h-3" />
                                <span>Ganti Meja / Layanan</span>
                            </button>
                        </div>
                    </div>

                    <!-- Editable Name Field -->
                    <div>
                        <label class="block text-xs font-semibold text-[#7A6A60] mb-1">
                            Ubah Nama Pemesan (Jika Perlu)
                        </label>
                        <input
                            v-model="customerName"
                            type="text"
                            placeholder="Nama pemesan..."
                            class="w-full px-3.5 py-2.5 text-sm bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#A8421E]/20 focus:border-[#A8421E] transition-colors text-[#231812]"
                        />
                    </div>

                    <!-- Quick recap -->
                    <div class="p-3 bg-[#FAF7F2] rounded-xl border border-[#E8DFD1] text-xs space-y-1.5">
                        <div class="flex justify-between text-[#7A6A60]">
                            <span>Jumlah Menu:</span>
                            <span class="font-bold text-[#231812] font-mono">{{ cartTotalItems }} Porsi</span>
                        </div>
                        <div class="flex justify-between text-[#7A6A60]">
                            <span>Total Pembayaran:</span>
                            <span class="font-bold text-[#A8421E] text-sm font-mono">{{ formatRupiah(cartTotal) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2.5">
                    <button
                        type="button"
                        @click="showOrderInfoModal = false"
                        :disabled="isSubmitting"
                        class="px-4 py-2.5 rounded-xl text-sm font-semibold text-[#7A6A60] hover:bg-[#FAF7F2] transition-colors cursor-pointer"
                    >
                        Kembali
                    </button>
                    <button
                        type="button"
                        @click="submitOrder"
                        :disabled="isSubmitting"
                        class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-[#A8421E] hover:bg-[#8F3314] active:scale-98 text-white flex items-center gap-2 transition-all cursor-pointer shadow-xs"
                    >
                        <span v-if="isSubmitting">Memproses...</span>
                        <span v-else>Kirim Pesanan ke Dapur</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden Thermal Ticket Print layout for Kiosk Customer -->
        <KioskTicketPrint :order="orderSuccess" :paperSize="kioskPrinterSettings.paperSize" :storeSettings="store_settings" />

        <!-- Kiosk Printer Settings Modal (Staff/Admin) -->
        <PrinterSettingsModal
            :show="showPrinterModal"
            profileKey="kiosk_printer_settings"
            title="Printer Karcis Kiosk Mandiri"
            subtitle="Konfigurasi printer thermal untuk karcis antrean pelanggan."
            @close="showPrinterModal = false"
            @saved="onKioskPrinterSaved"
        />

        <!-- MODAL 3: Order Success & Queue Number Display -->
        <div
            v-if="orderSuccess"
            class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4 backdrop-blur-2xs"
        >
            <div class="bg-white rounded-3xl border border-[#E8DFD1] max-w-lg w-full p-8 text-center shadow-2xl animate-in fade-in duration-150">
                <div class="w-16 h-16 rounded-2xl bg-[#F2FAF4] text-[#15803D] flex items-center justify-center mx-auto mb-4 border border-[#86EFAC]">
                    <CheckCircle2 class="w-9 h-9 stroke-[2]" />
                </div>

                <h2 class="font-serif text-2xl font-bold text-[#231812]">Pesanan Berhasil Masuk!</h2>
                <p class="text-xs text-[#7A6A60] mt-1">
                    Silakan ambil nomor antrean di bawah untuk ditunjukkan ke kasir saat pembayaran.
                </p>

                <!-- Queue Ticket Card -->
                <div class="my-6 p-6 rounded-2xl bg-[#FAF7F2] border border-[#E8DFD1]">
                    <span class="text-xs font-bold text-[#7A6A60] uppercase tracking-widest block">
                        NOMOR ANTREAN PESANAN
                    </span>
                    <div class="text-4xl sm:text-5xl font-black text-[#A8421E] tracking-wider my-2 font-mono">
                        {{ orderSuccess.order_number }}
                    </div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-[#E8DFD1] text-[#231812] shadow-2xs">
                        <span>{{ orderSuccess.customer_name }}</span>
                        <span>•</span>
                        <span>{{ orderSuccess.order_type === 'dine_in' ? `Meja ${orderSuccess.table_number || '-'}` : 'Bawa Pulang' }}</span>
                    </div>

                    <div class="mt-4 pt-3 border-t border-[#E8DFD1] flex justify-between text-xs font-medium text-[#7A6A60]">
                        <span>Total Tagihan:</span>
                        <span class="font-bold text-[#231812] text-sm font-mono">{{ formatRupiah(orderSuccess.total_amount) }}</span>
                    </div>
                </div>

                <!-- Info instructions -->
                <div class="p-4 bg-[#FDF8F5] border border-[#E8DFD1] rounded-2xl text-left text-xs text-[#5C4A3E] space-y-1.5 mb-6">
                    <div class="font-bold flex items-center gap-1.5 text-[#A8421E]">
                        <Clock class="w-4 h-4" />
                        <span>Petunjuk Selanjutnya:</span>
                    </div>
                    <p class="text-[#7A6A60] pl-5">
                        1. Klik tombol <strong class="text-[#231812]">"Cetak Karcis Antrean"</strong> di bawah.
                    </p>
                    <p class="text-[#7A6A60] pl-5">
                        2. Tunjukkan kertas antrean ke kasir untuk menyelesaikan pembayaran.
                    </p>
                    <p class="text-[#7A6A60] pl-5">
                        3. Koki langsung meracik pesanan Anda segera setelah diverifikasi kasir.
                    </p>
                </div>

                <!-- Alert after printed -->
                <div v-if="isTicketPrinted" class="mb-5 p-3.5 bg-[#F2FAF4] border border-[#86EFAC] rounded-2xl text-left flex items-start gap-3 text-[#15803D] animate-in fade-in duration-150">
                    <CheckCircle2 class="w-5 h-5 text-[#15803D] shrink-0 mt-0.5" />
                    <div>
                        <div class="font-bold text-xs">Karcis Antrean Berhasil Dicetak</div>
                        <div class="text-[11px] text-[#166534] mt-0.5">
                            Silakan ambil kertas tiket antrean Anda dan segera menuju ke meja kasir.
                        </div>
                    </div>
                </div>

                <!-- Action Buttons: 2-stage flow -->
                <div class="space-y-2.5">
                    <!-- Stage 1: Before printing -> Dapatkan Nomor Antrean -->
                    <button
                        v-if="!isTicketPrinted"
                        type="button"
                        @click="printQueueTicket"
                        :disabled="isPrintingTicket"
                        class="w-full h-13 rounded-2xl bg-[#A8421E] hover:bg-[#8F3314] active:bg-[#7D2E12] text-white font-bold text-base flex items-center justify-center gap-2.5 shadow-md transition-colors cursor-pointer"
                    >
                        <Printer class="w-5 h-5" />
                        <span>{{ isPrintingTicket ? 'Menyiapkan Cetakan...' : 'Cetak Karcis Antrean' }}</span>
                    </button>

                    <!-- Stage 2: After printed -> Selesai / Pesan Baru + Opsi Cetak Ulang -->
                    <template v-else>
                        <button
                            type="button"
                            @click="resetKiosk"
                            class="w-full h-13 rounded-2xl bg-[#231812] hover:bg-[#3D281F] text-white font-bold text-base flex items-center justify-center gap-2 shadow-md transition-colors cursor-pointer"
                        >
                            <CheckCircle2 class="w-5 h-5 text-emerald-400" />
                            <span>Selesai / Pesan Baru</span>
                        </button>

                        <button
                            type="button"
                            @click="printQueueTicket"
                            class="w-full py-2.5 text-xs font-semibold text-[#7A6A60] hover:text-[#231812] flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <Printer class="w-3.5 h-3.5" />
                            <span>Cetak Ulang Karcis</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- MODAL 4: Product Detail, Taste Profile & Ingredients Modal -->
        <div
            v-if="selectedProduct"
            class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-3 sm:p-4"
            @click.self="closeProductModal"
        >
            <div class="bg-white rounded-3xl border border-[#E8DFD1] max-w-xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Image Header (shrink-0) -->
                <div class="relative h-44 sm:h-52 bg-[#FAF7F2] overflow-hidden shrink-0">
                    <img
                        :src="selectedProduct.image_url"
                        :alt="selectedProduct.name"
                        class="w-full h-full object-cover"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>

                    <!-- Category Tag -->
                    <div class="absolute top-4 left-4 flex gap-2">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-white text-[#231812] shadow-xs">
                            {{ selectedProduct.category?.name }}
                        </span>
                    </div>

                    <!-- Close Button -->
                    <button
                        type="button"
                        @click="closeProductModal"
                        class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/90 hover:bg-white text-[#231812] flex items-center justify-center shadow-xs transition-colors cursor-pointer"
                        title="Tutup"
                    >
                        <X class="w-5 h-5" />
                    </button>

                    <!-- Title & Price Overlay -->
                    <div class="absolute bottom-4 left-4 right-4 text-white">
                        <h2 class="font-serif text-2xl font-bold tracking-tight drop-shadow-xs">{{ selectedProduct.name }}</h2>
                        <div class="text-[#F5D5B8] font-bold text-lg font-mono mt-0.5 drop-shadow-xs">
                            {{ formatRupiah(selectedProduct.price) }}
                        </div>
                    </div>
                </div>

                <!-- Modal Content Body -->
                <div
                    ref="modalScrollBody"
                    class="p-5 sm:p-6 space-y-5 flex-1 min-h-0 overflow-y-auto overscroll-contain"
                >
                    <!-- Description -->
                    <div>
                        <p class="text-sm text-[#5C4A3E] leading-relaxed font-medium">
                            {{ selectedProduct.description }}
                        </p>
                    </div>

                    <!-- Taste Profile (Karakter Rasa) -->
                    <div v-if="selectedProduct.taste_profile" class="p-4 rounded-2xl bg-[#FDF8F5] border border-[#E8DFD1]">
                        <div class="text-xs font-bold text-[#A8421E] uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                            <UtensilsCrossed class="w-3.5 h-3.5" />
                            <span>Karakter Rasa & Kuah</span>
                        </div>
                        <p class="text-sm font-medium text-[#231812] leading-relaxed">
                            {{ selectedProduct.taste_profile }}
                        </p>
                    </div>

                    <!-- Ingredients List (Komposisi Bahan Utama) -->
                    <div v-if="selectedProduct.ingredients && selectedProduct.ingredients.length > 0">
                        <div class="text-xs font-bold text-[#7A6A60] uppercase tracking-wider mb-2.5">
                            Bahan Baku Pilihan
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="(ingredient, idx) in selectedProduct.ingredients"
                                :key="idx"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl text-xs font-semibold text-[#231812]"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-[#A8421E]"></span>
                                {{ ingredient }}
                            </span>
                        </div>
                    </div>

                    <!-- Custom Note for this item -->
                    <div class="pt-4 border-t border-[#E8DFD1]">
                        <label class="block text-xs font-bold text-[#231812] mb-1.5">
                            Catatan Khusus untuk Menu Ini (Opsional)
                        </label>
                        <div class="flex flex-wrap gap-1.5 mb-2.5">
                            <button
                                v-for="note in activeDetailQuickNotes"
                                :key="note"
                                type="button"
                                @click="applyQuickNoteToDetail(note)"
                                class="px-2.5 py-1 text-xs bg-[#FAF7F2] hover:bg-[#F5E6E0] hover:text-[#A8421E] text-[#5C4A3E] border border-[#E8DFD1] rounded-lg font-medium transition-colors cursor-pointer"
                            >
                                + {{ note }}
                            </button>
                        </div>
                        <input
                            v-model="detailNotes"
                            type="text"
                            placeholder="Contoh: Kuah dipisah, koya banyak, jangan pakai emping..."
                            class="w-full px-3.5 py-2.5 text-xs bg-[#FAF7F2] border border-[#E8DFD1] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#A8421E]/20 focus:border-[#A8421E] transition-colors text-[#231812] select-text cursor-text"
                        />
                    </div>
                </div>

                <!-- Modal Footer / Add to Cart Action (shrink-0) -->
                <div class="p-4 border-t border-[#E8DFD1] bg-[#FAF7F2] flex items-center justify-between gap-4 shrink-0">
                    <!-- Quantity Stepper -->
                    <div class="flex items-center gap-1.5 bg-white border border-[#E8DFD1] rounded-xl p-1 shadow-2xs">
                        <button
                            type="button"
                            @click="detailQuantity > 1 ? detailQuantity-- : null"
                            class="w-10 h-10 rounded-lg bg-[#FAF7F2] hover:bg-[#EFE8DE] active:bg-[#E8DFD1] disabled:opacity-40 text-[#231812] flex items-center justify-center font-bold text-sm transition-colors cursor-pointer"
                            :disabled="detailQuantity <= 1"
                        >
                            <Minus class="w-4 h-4" />
                        </button>
                        <span class="w-10 text-center font-bold text-base text-[#231812] font-mono">
                            {{ detailQuantity }}
                        </span>
                        <button
                            type="button"
                            @click="detailQuantity++"
                            class="w-10 h-10 rounded-lg bg-[#FAF7F2] hover:bg-[#EFE8DE] active:bg-[#E8DFD1] text-[#231812] flex items-center justify-center font-bold text-sm transition-colors cursor-pointer"
                        >
                            <Plus class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Add Button with dynamic total -->
                    <button
                        type="button"
                        @click="addDetailToCart"
                        class="flex-1 h-12 rounded-xl bg-[#A8421E] hover:bg-[#8F3314] active:bg-[#7D2E12] text-white font-bold text-sm flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                    >
                        <ShoppingBag class="w-4 h-4" />
                        <span>Tambah ke Pesanan • {{ formatRupiah(selectedProduct.price * detailQuantity) }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
