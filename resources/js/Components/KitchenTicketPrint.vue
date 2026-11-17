<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { formatDateTime } from '@/Utils/formatters';

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
const storeName = computed(() => props.storeSettings?.store_name || 'SOTO BETAWI PARSOEY');

const totalPortions = computed(() => {
    if (!props.order?.items) return 0;
    return props.order.items.reduce((sum, item) => sum + Number(item.quantity || 1), 0);
});

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
            :class="paperSize === '80mm' ? 'w-[80mm] max-w-[80mm] text-[12px] px-2.5' : 'w-[58mm] max-w-[58mm] text-[11px] px-1.5'"
            class="mx-auto py-2 text-left box-border pb-6"
        >
            <!-- Header -->
            <div class="text-center pb-1">
                <div class="text-xs font-black uppercase tracking-wider">{{ storeName }}</div>
                <div class="text-sm font-black uppercase tracking-widest my-0.5 border-y-2 border-black py-0.5">
                    *** TIKET DAPUR / KOKI ***
                </div>
            </div>

            <!-- Queue & Table Highlight -->
            <div class="text-center py-1.5 bg-black text-white p-1.5 my-1.5 border border-black">
                <div class="text-[10px] uppercase font-bold text-gray-200">NO. ANTREAN:</div>
                <div class="text-2xl font-black tracking-widest my-0.5">{{ order.order_number }}</div>
                <div class="text-xs font-black tracking-wide mt-0.5">
                    {{ isDineIn ? `MEJA: ${order.table_number || '-'}` : '>> BAWA PULANG / BUNGKUS <<' }}
                </div>
            </div>

            <!-- Meta info -->
            <div class="text-[10px] space-y-0.5 py-1">
                <div class="flex justify-between">
                    <span>Pemesan:</span>
                    <span class="font-bold">{{ order.customer_name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Waktu Masuk:</span>
                    <span>{{ formatDateTime(order.created_at || new Date()) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Tipe:</span>
                    <span class="font-bold uppercase">{{ isDineIn ? 'Makan di Tempat' : 'Bawa Pulang' }}</span>
                </div>
            </div>

            <div class="border-b-2 border-black my-1"></div>

            <!-- Item List for Cooking -->
            <div class="py-1">
                <div class="text-[10px] font-bold uppercase tracking-wider mb-1.5 text-center">
                    -- DAFTAR RACIKAN MENU --
                </div>

                <div class="space-y-2">
                    <div
                        v-for="item in order.items"
                        :key="item.id"
                        class="pb-1.5 border-b border-dashed border-gray-400"
                    >
                        <div class="flex items-start gap-1 text-[13px] font-black leading-tight">
                            <span class="w-6 shrink-0 text-base font-black">{{ item.quantity }}x</span>
                            <span class="uppercase flex-1">{{ item.product?.name || item.name }}</span>
                        </div>

                        <!-- Highlighted Kitchen Notes -->
                        <div
                            v-if="item.notes"
                            class="mt-1 pl-6 text-[10px] font-bold text-black uppercase"
                        >
                            <span class="bg-black text-white px-1 py-0.2 rounded text-[9px] mr-1">NOTE:</span>
                            <span>{{ item.notes }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-b-2 border-black my-1"></div>

            <!-- Footer for Cook -->
            <div class="flex justify-between text-[11px] font-black py-0.5">
                <span>TOTAL PORSI:</span>
                <span>{{ totalPortions }} Porsi</span>
            </div>

            <div class="text-center text-[9px] text-gray-700 mt-2 italic">
                * Harap periksa catatan racikan sebelum disajikan *
            </div>
            <div class="border-b border-dashed border-black mt-2"></div>
        </div>
    </div>
</template>
