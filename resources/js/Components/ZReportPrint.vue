<script setup>
import { computed } from 'vue';
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
</script>

<template>
    <div
        v-if="report"
        class="hidden print:block fixed inset-0 z-[99999] bg-white text-black p-2 font-mono leading-tight"
        :class="paperSize === '80mm' ? 'text-[11px]' : 'text-[10px]'"
    >
        <div :class="paperSize === '80mm' ? 'w-[72mm]' : 'w-[48mm]'" class="mx-auto text-left">
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
