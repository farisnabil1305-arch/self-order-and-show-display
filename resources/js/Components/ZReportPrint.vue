<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    report: {
        type: Object,
        default: null,
    },
    paperSize: {
        type: String,
        default: '58mm',
    },
});

const store = computed(() => props.report?.store_settings || {});

const receiptContainerRef = ref(null);
const pageStyleId = 'dynamic-thermal-page-style';

function applyPrintStyle() {
    let style = document.getElementById(pageStyleId);
    if (!style) {
        style = document.createElement('style');
        style.id = pageStyleId;
        document.head.appendChild(style);
    }
    const width = props.paperSize === '80mm' ? '80mm' : '58mm';
    
    // Calculate exact content height in mm (96px = 1 inch = 25.4mm)
    let heightMm = 180; // baseline fallback for Z-Report
    if (receiptContainerRef.value) {
        const heightPx = receiptContainerRef.value.scrollHeight || receiptContainerRef.value.offsetHeight;
        if (heightPx > 0) {
            heightMm = Math.ceil((heightPx * 25.4) / 96) + 4;
        }
    } else if (props.report?.product_sales) {
        heightMm = Math.max(160, 120 + (props.report.product_sales.length * 14));
    }

    style.innerHTML = `@page { size: ${width} ${heightMm}mm; margin: 0mm; }`;
}

function removePrintStyle() {
    const style = document.getElementById(pageStyleId);
    if (style) {
        style.remove();
    }
}

onMounted(() => {
    nextTick(() => {
        applyPrintStyle();
    });
});

watch(
    () => props.report,
    () => {
        nextTick(() => {
            applyPrintStyle();
        });
    },
    { deep: true }
);

onUnmounted(() => {
    removePrintStyle();
});
</script>

<template>
    <div
        v-if="report"
        class="thermal-receipt-print-area bg-white text-black font-mono leading-tight"
    >
        <div
            ref="receiptContainerRef"
            :class="paperSize === '80mm' ? 'w-[80mm] max-w-[80mm] text-[12px] px-2.5' : 'w-[58mm] max-w-[58mm] text-[10.5px] px-1.5'"
            class="thermal-receipt-container mx-auto py-2 text-left box-border pb-6"
        >
            <!-- Header -->
            <div class="text-center pb-2">
                <div class="text-xs font-black uppercase tracking-wider">{{ store.store_name || 'SOTO BETAWI PARSOEY' }}</div>
                <div class="text-[10px] text-gray-800">{{ store.store_branch || 'Cabang Utama - POS 01' }}</div>
                <div class="text-[9px] text-gray-700">{{ store.store_address || 'Jl. Raya Betawi No. 88' }}</div>
                <div v-if="store.store_phone" class="text-[9px] text-gray-700">Telp: {{ store.store_phone }}</div>

                <div class="my-1.5 border-y-2 border-black py-0.5 font-black text-xs uppercase tracking-widest">
                    LAPORAN TUTUP KASIR (Z-REPORT)
                </div>
            </div>

            <!-- Meta info -->
            <div class="space-y-0.5 text-[10px] py-1 border-b border-dashed border-black">
                <div class="flex justify-between">
                    <span>Waktu Cetak:</span>
                    <span class="font-bold">{{ report.date_formatted }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Terminal:</span>
                    <span>{{ report.terminal }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Kasir:</span>
                    <span>{{ report.cashier }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Total Transaksi Lunas:</span>
                    <span class="font-bold">{{ report.total_orders }} Transaksi</span>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="py-2 border-b-2 border-black space-y-1">
                <div class="text-[10px] font-black uppercase tracking-wider mb-1">
                    -- RINGKASAN OMSET KASIR --
                </div>

                <div class="flex justify-between text-[10px]">
                    <span>Tunai ({{ report.cash?.count || 0 }}x):</span>
                    <span class="font-bold">{{ formatRupiah(report.cash?.revenue || 0) }}</span>
                </div>
                <div class="flex justify-between text-[10px]">
                    <span>QRIS ({{ report.qris?.count || 0 }}x):</span>
                    <span class="font-bold">{{ formatRupiah(report.qris?.revenue || 0) }}</span>
                </div>

                <div class="pt-1 border-t border-black flex justify-between font-black text-xs">
                    <span>TOTAL PENDAPATAN:</span>
                    <span>{{ formatRupiah(report.total_revenue || 0) }}</span>
                </div>

                <div class="pt-1 border-t border-dashed border-gray-500 text-[10px] space-y-0.5 bg-gray-100 p-1">
                    <div class="flex justify-between font-bold">
                        <span>Uang Fisik Kas (Drawer):</span>
                        <span>{{ formatRupiah(report.cash?.cash_in_drawer || 0) }}</span>
                    </div>
                    <div class="flex justify-between text-[9px] text-gray-600">
                        <span>Tunai Diterima:</span>
                        <span>{{ formatRupiah(report.cash?.amount_received || 0) }}</span>
                    </div>
                    <div class="flex justify-between text-[9px] text-gray-600">
                        <span>Kembalian Dikeluarkan:</span>
                        <span>{{ formatRupiah(report.cash?.change_given || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- Product Quantities Sold -->
            <div class="py-2 border-b border-dashed border-black">
                <div class="text-[10px] font-black uppercase tracking-wider mb-1">
                    -- MENU TERJUAL HARI INI --
                </div>

                <div class="space-y-1 text-[10px]">
                    <div
                        v-for="(p, idx) in report.product_sales"
                        :key="idx"
                        class="flex justify-between"
                    >
                        <span class="truncate pr-1">{{ p.quantity }}x {{ p.name }}</span>
                        <span class="font-bold shrink-0">{{ formatRupiah(p.total) }}</span>
                    </div>
                </div>

                <div class="mt-1.5 pt-1 border-t border-black flex justify-between font-bold text-[10px]">
                    <span>TOTAL PORSI TERJUAL:</span>
                    <span>{{ report.total_portions_sold || 0 }} Porsi</span>
                </div>
            </div>

            <!-- Tables status -->
            <div class="py-1.5 border-b border-dashed border-black text-[10px]">
                <div class="flex justify-between">
                    <span>Status Meja Santap:</span>
                    <span class="font-bold">{{ report.tables?.available }} Kosong / {{ report.tables?.occupied }} Terisi</span>
                </div>
            </div>

            <!-- Signatures -->
            <div class="py-4 grid grid-cols-2 text-center text-[10px]">
                <div>
                    <div>Petugas Kasir,</div>
                    <div class="h-10"></div>
                    <div class="border-t border-black mx-2 pt-0.5">( .................... )</div>
                </div>
                <div>
                    <div>Owner / Supervisor,</div>
                    <div class="h-10"></div>
                    <div class="border-t border-black mx-2 pt-0.5">( .................... )</div>
                </div>
            </div>

            <!-- Footer note -->
            <div class="text-center text-[9px] text-gray-600 italic">
                * Dokumen Z-Report Sah Dicetak Langsung Dari Terminal Kasir *
            </div>
            <div class="border-b-2 border-black mt-2"></div>
        </div>
    </div>
</template>
