<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import {
    Store,
    Save,
    CheckCircle2,
    X,
    FileText,
    Phone,
    MapPin,
    Instagram,
    Sparkles,
    Percent,
    AlertCircle,
    Eye,
    Wifi,
    KeyRound
} from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    initialSettings: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
    store_name: 'Soto Betawi Parsoey',
    store_tagline: 'Cita Rasa Gurih Kuah Santan & Susu Rempah Asli Betawi',
    store_branch: 'Cabang Utama - POS 01',
    store_address: 'Jl. Raya Betawi No. 88, Jakarta Selatan',
    store_phone: '0812-9876-5432',
    store_instagram: '@sotoparsoey.id',
    wifi_ssid: 'SOTO PARSOEY FREE',
    wifi_password: 'sotobetawi88',
    receipt_footer: "Terima kasih atas kunjungan Anda!\nKritik & saran via WhatsApp: 0812-9876-5432\nSemoga harimu menyenangkan!",
    tax_percentage: '0',
});

const isSaving = ref(false);
const toastMessage = ref('');
const errorMessage = ref('');

watch(
    () => props.initialSettings,
    (newVal) => {
        if (newVal && Object.keys(newVal).length > 0) {
            form.value = {
                store_name: newVal.store_name || form.value.store_name,
                store_tagline: newVal.store_tagline ?? form.value.store_tagline,
                store_branch: newVal.store_branch ?? form.value.store_branch,
                store_address: newVal.store_address ?? form.value.store_address,
                store_phone: newVal.store_phone ?? form.value.store_phone,
                store_instagram: newVal.store_instagram ?? form.value.store_instagram,
                wifi_ssid: newVal.wifi_ssid ?? form.value.wifi_ssid,
                wifi_password: newVal.wifi_password ?? form.value.wifi_password,
                receipt_footer: newVal.receipt_footer ?? form.value.receipt_footer,
                tax_percentage: newVal.tax_percentage ?? form.value.tax_percentage,
            };
        }
    },
    { immediate: true, deep: true }
);

function showToast(msg) {
    toastMessage.value = msg;
    setTimeout(() => {
        if (toastMessage.value === msg) toastMessage.value = '';
    }, 3500);
}

async function saveSettings() {
    isSaving.value = true;
    errorMessage.value = '';

    try {
        const res = await axios.post('/pos/api/store-settings', form.value);
        if (res.data?.success) {
            showToast(res.data.message || 'Pengaturan berhasil disimpan!');
            emit('saved', res.data.settings);
        }
    } catch (err) {
        if (err.response?.data?.message) {
            errorMessage.value = err.response.data.message;
        } else {
            errorMessage.value = 'Gagal menyimpan pengaturan toko.';
        }
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/60 backdrop-blur-2xs"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-4xl bg-white rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <!-- Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-amber-900 via-amber-800 to-amber-950 text-white flex items-center justify-between shadow-md shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/20">
                        <Store class="w-5 h-5 text-amber-300" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold">Pengaturan Identitas Toko & Kustomisasi Struk</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-stone-950">
                                PROFIL RESTORAN
                            </span>
                        </div>
                        <p class="text-xs text-amber-200/80">Atur nama resto, cabang, alamat, kontak, dan pesan ucapan di kaki struk kasir.</p>
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
                v-if="toastMessage"
                class="px-6 py-2.5 bg-emerald-50 text-emerald-800 border-b border-emerald-200 text-xs font-bold flex items-center gap-2 shrink-0 animate-in fade-in"
            >
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>
            <div
                v-if="errorMessage"
                class="px-6 py-2.5 bg-rose-50 text-rose-800 border-b border-rose-200 text-xs font-bold flex items-center gap-2 shrink-0 animate-in fade-in"
            >
                <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>

            <!-- Modal Content: Form on Left, Live Receipt Preview on Right -->
            <div class="flex-1 overflow-y-auto overscroll-contain p-6 grid grid-cols-1 lg:grid-cols-12 gap-6 bg-slate-50/50">
                <!-- FORM COLUMN (7 cols) -->
                <div class="lg:col-span-7 space-y-4">
                    <!-- Store Name & Branch -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Nama Restoran <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.store_name"
                                type="text"
                                placeholder="Contoh: Soto Betawi Parsoey"
                                class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Nama Cabang / Unit POS
                            </label>
                            <input
                                v-model="form.store_branch"
                                type="text"
                                placeholder="Contoh: Cabang Utama - POS 01"
                                class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Tagline / Slogan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Slogan / Tagline Resto
                        </label>
                        <input
                            v-model="form.store_tagline"
                            type="text"
                            placeholder="Contoh: Cita Rasa Gurih Kuah Santan & Susu Rempah Asli Betawi"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                        />
                    </div>

                    <!-- Address -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Alamat Lengkap Resto
                        </label>
                        <input
                            v-model="form.store_address"
                            type="text"
                            placeholder="Contoh: Jl. Raya Betawi No. 88, Jakarta Selatan"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                        />
                    </div>

                    <!-- Phone & Instagram -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Nomor WhatsApp / Telepon
                            </label>
                            <input
                                v-model="form.store_phone"
                                type="text"
                                placeholder="Contoh: 0812-9876-5432"
                                class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Akun Instagram / Sosmed
                            </label>
                            <input
                                v-model="form.store_instagram"
                                type="text"
                                placeholder="Contoh: @sotoparsoey.id"
                                class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Fasilitas WiFi Pengunjung (SSID & Password) -->
                    <div class="p-3.5 bg-amber-50/80 border border-amber-200/90 rounded-2xl space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-bold text-amber-950">
                                <Wifi class="w-4 h-4 text-amber-700" />
                                <span>Akses WiFi Pelanggan (Dicetak di Struk)</span>
                            </div>
                            <span class="text-[10px] font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">
                                Struk Kasir & Kiosk
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Nama WiFi (SSID)
                                </label>
                                <input
                                    v-model="form.wifi_ssid"
                                    type="text"
                                    placeholder="Contoh: SOTO PARSOEY FREE"
                                    class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Password WiFi
                                </label>
                                <input
                                    v-model="form.wifi_password"
                                    type="text"
                                    placeholder="Contoh: sotobetawi88"
                                    class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                                />
                            </div>
                        </div>
                        <p class="text-[11px] text-amber-900/80">
                            Info WiFi ini akan tercetak otomatis di bagian bawah struk kasir & karcis kiosk agar pelanggan mudah terhubung ke internet. Kosongkan jika tidak menyediakan WiFi.
                        </p>
                    </div>

                    <!-- Receipt Footer Message -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Pesan Kaki Struk (Ucapan Terima Kasih di Bagian Bawah Kertas)
                        </label>
                        <textarea
                            v-model="form.receipt_footer"
                            rows="3"
                            placeholder="Tulis ucapan terima kasih, info WiFi, atau kontak kritik & saran..."
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                        ></textarea>
                    </div>

                    <!-- Tax Percentage -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Pajak Restoran PB1 (%)
                        </label>
                        <div class="flex items-center gap-2 max-w-xs">
                            <input
                                v-model="form.tax_percentage"
                                type="number"
                                min="0"
                                max="100"
                                placeholder="0"
                                class="w-28 px-3.5 py-2 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 transition-colors"
                            />
                            <span class="text-xs text-slate-500 font-semibold">% (Set 0 jika sudah termasuk pajak / bebas pajak)</span>
                        </div>
                    </div>
                </div>

                <!-- LIVE PREVIEW COLUMN (5 cols) -->
                <div class="lg:col-span-5 flex flex-col items-center">
                    <div class="w-full flex items-center justify-between mb-2 px-1">
                        <span class="text-xs font-bold text-slate-600 flex items-center gap-1.5">
                            <Eye class="w-3.5 h-3.5 text-amber-600" />
                            <span>Simulasi Tampilan Kertas Struk:</span>
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">58mm Thermal</span>
                    </div>

                    <!-- Mock Thermal Paper -->
                    <div class="w-full max-w-[280px] bg-white p-4 rounded-xl border border-slate-300 shadow-md font-mono text-[10px] leading-tight text-black select-none">
                        <!-- Top Header -->
                        <div class="text-center pb-2 border-b border-dashed border-gray-400">
                            <div class="text-xs font-black uppercase">{{ form.store_name || 'NAMA TOKO' }}</div>
                            <div class="text-[9px] text-gray-700">{{ form.store_branch }}</div>
                            <div class="text-[8px] text-gray-600 mt-0.5">{{ form.store_address }}</div>
                            <div v-if="form.store_phone" class="text-[8px] text-gray-600">Telp: {{ form.store_phone }}</div>
                        </div>

                        <!-- Sample Body -->
                        <div class="py-2 text-center border-b border-dashed border-gray-400">
                            <div class="text-[8px] uppercase text-gray-500">Nomor Antrean</div>
                            <div class="text-xl font-black tracking-wider my-0.5">SOTO-008</div>
                            <div class="text-[9px] font-bold">[ MAKAN DI TEMPAT - MEJA 03 ]</div>
                        </div>

                        <!-- Sample Items -->
                        <div class="py-2 border-b border-dashed border-gray-400 space-y-1">
                            <div class="flex justify-between">
                                <span>2x Soto Betawi Daging</span>
                                <span>Rp 70.000</span>
                            </div>
                            <div class="flex justify-between">
                                <span>2x Es Jeruk Peras</span>
                                <span>Rp 16.000</span>
                            </div>
                            <div class="pt-1 border-t border-black flex justify-between font-bold">
                                <span>TOTAL:</span>
                                <span>Rp 86.000</span>
                            </div>
                            <div class="flex justify-between text-[9px] text-gray-600">
                                <span>Pembayaran:</span>
                                <span>TUNAI</span>
                            </div>
                        </div>

                        <!-- Footer Preview -->
                        <div class="text-center pt-2 space-y-0.5 text-[8px] text-gray-700 whitespace-pre-line">
                            {{ form.receipt_footer || 'Terima kasih atas kunjungan Anda!' }}
                            
                            <!-- WiFi Preview on Mock Receipt -->
                            <div v-if="form.wifi_ssid || form.wifi_password" class="my-1 py-1 border-y border-dashed border-gray-400 text-center font-bold text-[8px] leading-tight">
                                <div>*** FREE WIFI ***</div>
                                <div v-if="form.wifi_ssid">SSID: {{ form.wifi_ssid }}</div>
                                <div v-if="form.wifi_password">PASS: {{ form.wifi_password }}</div>
                            </div>

                            <div v-if="form.store_instagram" class="pt-1 text-[8px] font-bold">
                                IG: {{ form.store_instagram }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="px-6 py-4 bg-white border-t border-slate-200 flex items-center justify-between shrink-0">
                <span class="text-xs text-slate-500">
                    Pengaturan ini berlaku instan ke cetakan struk kasir, slip dapur, dan karcis kiosk.
                </span>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>

                    <button
                        type="button"
                        @click="saveSettings"
                        :disabled="isSaving"
                        class="px-6 py-2.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 active:bg-amber-800 disabled:opacity-50 text-white shadow-xs transition-colors flex items-center gap-2 cursor-pointer"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Profil & Struk' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
