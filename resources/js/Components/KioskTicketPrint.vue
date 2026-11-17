<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { formatRupiah, formatDateTime } from '@/Utils/formatters';

const props = defineProps({
    order: {
        type: Object,
        default: null,
    },
    paperSize: {
        type: String,
        default: '58mm',
    },
    storeSettings: {
        type: Object,
        default: () => ({}),
    },
});

const isDineIn = computed(() => props.order?.order_type === 'dine_in');
const store = computed(() => props.storeSettings || {});

// Dynamic @page size injection for thermal printer roll
const pageStyleId = 'dynamic-thermal-page-style';

function applyPrintStyle() {
    let style = document.getElementById(pageStyleId);
    if (!style) {
        style = document.createElement('style');
        style.id = pageStyleId;
        document.head.appendChild(style);
    }
    const width = props.paperSize === '80mm' ? '80mm' : '58mm';
    style.innerHTML = `@page { size: ${width} auto; margin: 0mm; }`;
}

function removePrintStyle() {
    const style = document.getElementById(pageStyleId);
    if (style) {
        style.remove();
    }
}

onMounted(() => {
    applyPrintStyle();
});

onUnmounted(() => {
    removePrintStyle();
});
</script>

<template>
    <div
        v-if="order"
        class="thermal-receipt-print-area hidden print:block bg-white text-black font-mono leading-tight"
    >
        <div
            :class="paperSize === '80mm' ? 'w-[80mm] max-w-[80mm] text-[12px] px-2.5' : 'w-[58mm] max-w-[58mm] text-[10.5px] px-1.5'"
            class="mx-auto py-2 text-center box-border pb-6"
        >
            <!-- Header -->
            <div class="pb-1">
                <div class="text-sm font-black uppercase tracking-wider">{{ store.store_name || 'SOTO BETAWI PARSOEY' }}</div>
                <div class="text-[10px]">{{ store.store_branch || 'Self-Order Kiosk Mandiri' }}</div>
                <div v-if="store.store_address" class="text-[9px] text-black">{{ store.store_address }}</div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Title -->
            <div class="text-[11px] font-bold uppercase tracking-wider py-0.5">
                KARCIS ANTREAN PEMESANAN
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Big Queue Number -->
            <div class="py-2 bg-black text-white p-2 my-1 border border-black">
                <div class="text-[10px] uppercase font-bold text-gray-200">NOMOR ANTREAN ANDA:</div>
                <div class="text-3xl font-black tracking-widest my-1 font-mono">
                    {{ order.order_number }}
                </div>
                <div class="text-xs font-bold mt-1">
                    {{ isDineIn ? `[ MAKAN DI TEMPAT - MEJA ${order.table_number || '-'} ]` : '[ BAWA PULANG / TAKEAWAY ]' }}
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Order Meta -->
            <div class="text-left text-[10px] space-y-0.5 py-1">
                <div class="flex justify-between">
                    <span>Waktu Pesan:</span>
                    <span>{{ formatDateTime(order.created_at) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Nama Pemesan:</span>
                    <span class="font-bold">{{ order.customer_name }}</span>
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Items Summary -->
            <div class="text-left py-1 space-y-1">
                <div class="text-[10px] font-bold uppercase pb-0.5">Daftar Menu:</div>
                <div v-for="item in order.items" :key="item.id" class="text-[10px]">
                    <div class="flex justify-between font-semibold">
                        <span>{{ item.quantity }}x {{ item.product?.name || 'Menu Soto' }}</span>
                        <span>{{ formatRupiah(item.subtotal) }}</span>
                    </div>
                    <div v-if="item.notes" class="text-[9px] italic pl-2">
                        * {{ item.notes }}
                    </div>
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Total Amount -->
            <div class="py-1">
                <div class="flex justify-between text-xs font-black">
                    <span>TOTAL TAGIHAN:</span>
                    <span>{{ formatRupiah(order.total_amount) }}</span>
                </div>
                <div class="text-[10px] font-bold text-black uppercase mt-0.5">
                    STATUS: [ BELUM DIBAYAR ]
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Instructions Footer -->
            <div class="pt-2 pb-1 space-y-1 text-[10px]">
                <div class="font-bold uppercase tracking-tight">
                    >>> SILAKAN BAWA KARCIS INI KE KASIR <<<
                </div>
                <div class="text-[9px]">
                    Tunjukkan nomor antrean ini kepada kasir untuk pembayaran via Tunai atau QRIS.
                </div>
                <div class="text-[8px] pt-1">
                    Pesanan Anda langsung disiapkan di dapur setelah pembayaran diverifikasi di kasir.
                </div>

                <!-- WiFi Info on Kiosk Queue Slip -->
                <div v-if="storeSettings?.wifi_ssid || storeSettings?.wifi_password" class="pt-2 border-t border-dashed border-black text-center text-[9px] font-bold leading-tight">
                    <div>*** FREE WIFI RESTORAN ***</div>
                    <div v-if="storeSettings.wifi_ssid">SSID: {{ storeSettings.wifi_ssid }}</div>
                    <div v-if="storeSettings.wifi_password">Password: {{ storeSettings.wifi_password }}</div>
                </div>
            </div>
            <div class="border-b border-dashed border-black mt-2"></div>
        </div>
    </div>
</template>
