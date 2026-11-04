<script setup>
import { computed } from 'vue';
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
</script>

<template>
    <div v-if="order" class="hidden print:block fixed inset-0 z-[99999] bg-white text-black p-2 font-mono leading-tight" :class="paperSize === '80mm' ? 'text-[11px]' : 'text-[10px]'">
        <div :class="paperSize === '80mm' ? 'w-[72mm]' : 'w-[48mm]'" class="mx-auto">
            <!-- Header -->
            <div class="text-center pb-2">
                <div class="text-sm font-bold uppercase tracking-wide">{{ store.store_name || 'SOTO BETAWI PARSOEY' }}</div>
                <div v-if="store.store_branch" class="text-[10px] font-semibold text-gray-800">{{ store.store_branch }}</div>
                <div v-if="store.store_tagline" class="text-[9px] text-gray-700">{{ store.store_tagline }}</div>
                <div v-if="store.store_address" class="text-[9px] text-gray-700">{{ store.store_address }}</div>
                <div v-if="store.store_phone" class="text-[9px] text-gray-700">Telp/WA: {{ store.store_phone }}</div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Queue & Order Info -->
            <div class="text-center py-1">
                <div class="text-[10px] uppercase text-black font-semibold">Nomor Antrean</div>
                <div class="text-2xl font-bold tracking-wider my-0.5">{{ order.order_number }}</div>
                <div class="text-[11px] font-bold">
                    {{ isDineIn ? `[ MAKAN DI TEMPAT - MEJA ${order.table_number || '-'} ]` : '[ BAWA PULANG / TAKEAWAY ]' }}
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Metadata -->
            <div class="space-y-0.5 text-[10px] py-1">
                <div class="flex justify-between">
                    <span>Waktu:</span>
                    <span>{{ formatDateTime(order.created_at) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Pelanggan:</span>
                    <span class="font-bold">{{ order.customer_name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Status:</span>
                    <span class="font-bold uppercase">{{ order.status === 'paid' ? 'LUNAS' : 'BELUM BAYAR' }}</span>
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Items -->
            <div class="py-1 space-y-1.5">
                <div v-for="item in order.items" :key="item.id" class="text-[11px]">
                    <div class="font-bold flex justify-between">
                        <span>{{ item.product?.name || 'Menu Soto' }}</span>
                    </div>
                    <div class="flex justify-between text-[10px] text-black">
                        <span>{{ item.quantity }} x {{ formatRupiah(item.unit_price) }}</span>
                        <span class="font-bold">{{ formatRupiah(item.subtotal) }}</span>
                    </div>
                    <div v-if="item.notes" class="text-[9px] italic pl-2">
                        * {{ item.notes }}
                    </div>
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Totals & Payment -->
            <div class="space-y-1 text-[11px] py-1">
                <div class="flex justify-between">
                    <span>Subtotal:</span>
                    <span>{{ formatRupiah(order.subtotal) }}</span>
                </div>
                <div class="flex justify-between text-[10px]">
                    <span>PB1 (Pajak Resto 0%):</span>
                    <span>{{ formatRupiah(order.tax || 0) }}</span>
                </div>
                <div class="flex justify-between font-bold text-xs pt-1 border-t border-black">
                    <span>TOTAL:</span>
                    <span>{{ formatRupiah(order.total_amount) }}</span>
                </div>

                <div v-if="order.payment_method" class="pt-1 border-t border-dashed border-black text-[10px] space-y-0.5">
                    <div class="flex justify-between">
                        <span>Pembayaran:</span>
                        <span class="uppercase font-bold">{{ order.payment_method === 'cash' ? 'TUNAI' : 'QRIS / NON-TUNAI' }}</span>
                    </div>
                    <div v-if="order.payment_method === 'cash'" class="flex justify-between">
                        <span>Bayar Tunai:</span>
                        <span>{{ formatRupiah(order.amount_paid) }}</span>
                    </div>
                    <div v-if="order.payment_method === 'cash'" class="flex justify-between font-bold">
                        <span>Kembalian:</span>
                        <span>{{ formatRupiah(order.change_amount || 0) }}</span>
                    </div>
                </div>
            </div>

            <div class="border-b border-dashed border-black my-1"></div>

            <!-- Footer -->
            <div class="text-center pt-2 space-y-0.5 text-[10px]">
                <div v-if="store.receipt_footer" class="whitespace-pre-line text-[9px] text-gray-800">
                    {{ store.receipt_footer }}
                </div>
                <template v-else>
                    <div class="font-bold">Terima Kasih Atas Kunjungan Anda!</div>
                    <div>Selamat Menikmati Soto Hangat</div>
                </template>

                <!-- WiFi Info on Customer Receipt -->
                <div v-if="store.wifi_ssid || store.wifi_password" class="my-1.5 py-1 border-y border-dashed border-black text-center font-bold text-[9px] leading-tight">
                    <div>*** FREE WIFI RESTORAN ***</div>
                    <div v-if="store.wifi_ssid">SSID: {{ store.wifi_ssid }}</div>
                    <div v-if="store.wifi_password">Password: {{ store.wifi_password }}</div>
                </div>

                <div v-if="store.store_instagram" class="text-[9px] font-bold pt-1">
                    Instagram: {{ store.store_instagram }}
                </div>
                <div class="text-[8px] text-gray-500 pt-1">Simpan struk ini sebagai bukti transaksi sah</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    :global(body) {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }
}
</style>
