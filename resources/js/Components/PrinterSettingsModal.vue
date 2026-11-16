<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { thermalPrinter } from '@/Utils/thermalPrinter';
import {
    Printer,
    Bluetooth,
    Usb,
    Monitor,
    CheckCircle2,
    AlertCircle,
    X,
    FileText,
    Zap,
    RefreshCw,
    Sliders,
    Power,
    Check
} from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    profileKey: {
        type: String,
        default: 'pos_printer_settings', // 'pos_printer_settings' | 'kiosk_printer_settings'
    },
    title: {
        type: String,
        default: 'Pengaturan & Koneksi Printer',
    },
    subtitle: {
        type: String,
        default: 'Sambungkan ke printer thermal struk Bluetooth, USB, atau Driver Sistem.',
    },
});

const emit = defineEmits(['close', 'saved']);

// Reactive settings state
const settings = reactive({
    mode: 'system', // 'system' | 'bluetooth' | 'serial'
    paperSize: '58mm', // '58mm' | '80mm'
    autoPrint: true,
    printKitchen: true,
    deviceName: '',
    autoCut: true,
});

const isConnecting = ref(false);
const isTesting = ref(false);
const alertMessage = ref(null);
const alertType = ref('success');

function showAlert(msg, type = 'success') {
    alertMessage.value = msg;
    alertType.value = type;
    setTimeout(() => {
        if (alertMessage.value === msg) {
            alertMessage.value = null;
        }
    }, 4500);
}

// Capability check
const supportsBluetooth = ref(false);
const supportsSerial = ref(false);

onMounted(() => {
    supportsBluetooth.value = thermalPrinter.isBluetoothSupported();
    supportsSerial.value = thermalPrinter.isSerialSupported();
});

function loadProfile() {
    const loaded = thermalPrinter.loadSettings(props.profileKey);
    settings.mode = loaded.mode || 'system';
    settings.paperSize = loaded.paperSize || '58mm';
    settings.autoPrint = loaded.autoPrint ?? true;
    settings.printKitchen = loaded.printKitchen ?? true;
    settings.deviceName = loaded.deviceName || '';
    settings.autoCut = loaded.autoCut ?? true;
}

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            loadProfile();
            alertMessage.value = null;
        }
    }
);

// Connection status
const isBtConnected = computed(() => thermalPrinter.isBluetoothConnected());
const isSerialConnected = computed(() => thermalPrinter.isSerialConnected());

const connectionStatusText = computed(() => {
    if (settings.mode === 'bluetooth') {
        return isBtConnected.value
            ? `Terhubung ke Bluetooth (${settings.deviceName || 'Thermal Printer'})`
            : (settings.deviceName ? `Belum Terhubung (${settings.deviceName})` : 'Bluetooth Belum Terhubung');
    }
    if (settings.mode === 'serial') {
        return isSerialConnected.value
            ? 'Terhubung via Port USB / Serial'
            : 'Port USB / Serial Belum Terhubung';
    }
    return 'Driver Sistem Windows Aktif (Siap Cetak)';
});

// Connect Bluetooth Device
async function handleConnectBluetooth() {
    isConnecting.value = true;
    try {
        const info = await thermalPrinter.connectBluetooth();
        settings.deviceName = info.name;
        settings.mode = 'bluetooth';
        saveCurrentSettings();
        showAlert(`Berhasil terhubung ke printer Bluetooth: ${info.name}!`);
    } catch (err) {
        if (err.name !== 'NotFoundError') { // User didn't cancel
            showAlert(err.message || 'Gagal menyambungkan ke printer Bluetooth.', 'error');
        }
    } finally {
        isConnecting.value = false;
    }
}

function handleDisconnectBluetooth() {
    thermalPrinter.disconnectBluetooth();
    showAlert('Printer Bluetooth diputuskan.');
}

// Connect Serial / USB
async function handleConnectSerial() {
    isConnecting.value = true;
    try {
        const info = await thermalPrinter.connectSerial();
        settings.deviceName = info.name;
        settings.mode = 'serial';
        saveCurrentSettings();
        showAlert('Berhasil terhubung ke printer via USB / Serial!');
    } catch (err) {
        if (err.name !== 'NotFoundError') {
            showAlert(err.message || 'Gagal membuka port USB/Serial.', 'error');
        }
    } finally {
        isConnecting.value = false;
    }
}

function handleDisconnectSerial() {
    thermalPrinter.disconnectSerial();
    showAlert('Koneksi USB/Serial diputuskan.');
}

// Save Settings
function saveCurrentSettings() {
    thermalPrinter.saveSettings(props.profileKey, {
        mode: settings.mode,
        paperSize: settings.paperSize,
        autoPrint: settings.autoPrint,
        printKitchen: settings.printKitchen,
        deviceName: settings.deviceName,
        autoCut: settings.autoCut,
    });
    emit('saved', { ...settings });
}

function handleSaveAndClose() {
    saveCurrentSettings();
    showAlert('Pengaturan printer berhasil disimpan.');
    setTimeout(() => {
        emit('close');
    }, 400);
}

// Trigger Test Print
const showSystemTestPreview = ref(false);

async function handleTestPrint() {
    isTesting.value = true;
    try {
        if (settings.mode === 'bluetooth') {
            if (!thermalPrinter.isBluetoothConnected()) {
                showAlert('Printer Bluetooth belum terhubung. Klik "Hubungkan Bluetooth" terlebih dahulu.', 'error');
                return;
            }
            const bytes = thermalPrinter.buildTestSlipEscPos(
                props.profileKey === 'pos_printer_settings' ? 'POS Kasir Parsoey' : 'Kiosk Mandiri Parsoey',
                settings.paperSize
            );
            await thermalPrinter.sendBytes(bytes);
            showAlert('Test print struk berhasil dikirim ke printer Bluetooth!');
        } else if (settings.mode === 'serial') {
            if (!thermalPrinter.isSerialConnected()) {
                showAlert('Port USB/Serial belum terhubung. Klik "Pilih Port USB" terlebih dahulu.', 'error');
                return;
            }
            const bytes = thermalPrinter.buildTestSlipEscPos(
                props.profileKey === 'pos_printer_settings' ? 'POS Kasir Parsoey' : 'Kiosk Mandiri Parsoey',
                settings.paperSize
            );
            await thermalPrinter.sendBytes(bytes);
            showAlert('Test print struk berhasil dikirim ke printer USB/Serial!');
        } else {
            // System print: trigger browser dialog
            showSystemTestPreview.value = true;
            setTimeout(() => {
                window.print();
                showSystemTestPreview.value = false;
                showAlert('Dialog cetak sistem driver terbuka untuk Test Print.');
            }, 300);
        }
    } catch (err) {
        showAlert('Gagal melakukan test print: ' + (err.message || err), 'error');
    } finally {
        isTesting.value = false;
    }
}
</script>

<template>
    <!-- Hidden Print Template for System Test Print -->
    <div
        v-if="showSystemTestPreview"
        class="hidden print:block fixed inset-0 z-[99999] bg-white text-black p-2 font-mono text-[11px] leading-tight"
    >
        <div :class="settings.paperSize === '80mm' ? 'w-[72mm]' : 'w-[48mm]'" class="mx-auto text-center">
            <div class="text-sm font-bold uppercase">SOTO BETAWI PARSOEY</div>
            <div class="text-xs font-bold my-1">*** TEST PRINT STRUK ***</div>
            <div class="border-b border-dashed border-black my-1"></div>
            <div class="text-[10px] space-y-0.5 text-left">
                <div>Profil: {{ profileKey === 'pos_printer_settings' ? 'Kasir POS' : 'Kiosk Mandiri' }}</div>
                <div>Ukuran Kertas: {{ settings.paperSize }}</div>
                <div>Mode Cetak: Driver Sistem Browser/Windows</div>
                <div>Waktu Test: {{ new Date().toLocaleString('id-ID') }}</div>
            </div>
            <div class="border-b border-dashed border-black my-1"></div>
            <div class="text-[10px] my-2">KONEKSI PRINTER BERHASIL!<br />Printer siap mencetak transaksi toko.</div>
            <div class="border-b border-dashed border-black my-1"></div>
        </div>
    </div>

    <!-- Modal Backdrop -->
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/60"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden border border-slate-200">
            <!-- Header -->
            <div class="px-6 py-4 bg-[#FAF7F2] border-b border-[#E8DFD1] text-[#231812] flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#231812] text-[#F5EBE1] flex items-center justify-center border border-[#3D281F]">
                        <Printer class="w-5 h-5 text-[#F5EBE1]" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-serif text-base font-bold text-[#231812]">{{ title }}</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#EFE8DE] text-[#7A6A60] border border-[#E8DFD1]">
                                {{ profileKey === 'pos_printer_settings' ? 'Kasir POS' : 'Kiosk Mandiri' }}
                            </span>
                        </div>
                        <p class="text-xs text-[#7A6A60]">{{ subtitle }}</p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="p-2 text-[#7A6A60] hover:text-[#231812] hover:bg-[#EFE8DE] rounded-xl transition-all cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Toast / Alert Message -->
            <div
                v-if="alertMessage"
                class="px-6 py-2.5 flex items-center gap-2 text-xs font-semibold shrink-0 transition-all"
                :class="alertType === 'success' ? 'bg-emerald-50 text-emerald-800 border-b border-emerald-200' : 'bg-rose-50 text-rose-800 border-b border-rose-200'"
            >
                <CheckCircle2 v-if="alertType === 'success'" class="w-4 h-4 text-emerald-600 shrink-0" />
                <AlertCircle v-else class="w-4 h-4 text-rose-600 shrink-0" />
                <span>{{ alertMessage }}</span>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto overscroll-contain p-6 space-y-6 bg-slate-50/50">
                <!-- Status Banner -->
                <div class="p-3.5 rounded-xl border flex items-center justify-between gap-3 shadow-2xs"
                     :class="(settings.mode === 'system' || (settings.mode === 'bluetooth' && isBtConnected) || (settings.mode === 'serial' && isSerialConnected))
                        ? 'bg-emerald-50/80 border-emerald-200 text-emerald-900'
                        : 'bg-amber-50/80 border-amber-200 text-amber-900'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                             :class="(settings.mode === 'system' || (settings.mode === 'bluetooth' && isBtConnected) || (settings.mode === 'serial' && isSerialConnected))
                                ? 'bg-emerald-600 text-white'
                                : 'bg-amber-600 text-white'"
                        >
                            <Zap class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold leading-tight">Status Printer:</div>
                            <div class="text-xs font-medium mt-0.5">{{ connectionStatusText }}</div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="handleTestPrint"
                        :disabled="isTesting"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white border shadow-2xs hover:bg-slate-50 transition-colors flex items-center gap-1.5 cursor-pointer shrink-0 disabled:opacity-50"
                        :class="(settings.mode === 'system' || (settings.mode === 'bluetooth' && isBtConnected) || (settings.mode === 'serial' && isSerialConnected))
                            ? 'border-emerald-300 text-emerald-800'
                            : 'border-amber-300 text-amber-800'"
                    >
                        <Printer class="w-3.5 h-3.5" />
                        <span>{{ isTesting ? 'Mencetak...' : 'Test Print Struk' }}</span>
                    </button>
                </div>

                <!-- Section 1: Pilihan Metode Koneksi -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                        <Sliders class="w-4 h-4 text-amber-700" />
                        <span>1. Pilih Metode Koneksi Printer</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- Option A: Driver Sistem Windows / Browser -->
                        <div
                            @click="settings.mode = 'system'; saveCurrentSettings()"
                            class="p-3.5 rounded-xl border-2 transition-all cursor-pointer flex flex-col justify-between"
                            :class="settings.mode === 'system'
                                ? 'border-amber-600 bg-amber-50/60 shadow-xs'
                                : 'border-slate-200 bg-white hover:border-slate-300'"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <Monitor class="w-5 h-5" :class="settings.mode === 'system' ? 'text-amber-700' : 'text-slate-500'" />
                                    <span v-if="settings.mode === 'system'" class="w-4 h-4 rounded-full bg-amber-600 text-white flex items-center justify-center text-[10px]">
                                        <Check class="w-3 h-3 stroke-[3]" />
                                    </span>
                                </div>
                                <h4 class="font-bold text-xs text-slate-800">Driver Sistem</h4>
                                <p class="text-[11px] text-slate-500 mt-1 leading-snug">
                                    USB Windows / LAN. Menggunakan dialog cetak bawaan komputer.
                                </p>
                            </div>
                            <span class="mt-3 text-[10px] font-bold text-emerald-700 block">✓ Siap Digunakan</span>
                        </div>

                        <!-- Option B: Web Bluetooth Thermal -->
                        <div
                            @click="settings.mode = 'bluetooth'; saveCurrentSettings()"
                            class="p-3.5 rounded-xl border-2 transition-all cursor-pointer flex flex-col justify-between"
                            :class="settings.mode === 'bluetooth'
                                ? 'border-amber-600 bg-amber-50/60 shadow-xs'
                                : 'border-slate-200 bg-white hover:border-slate-300'"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <Bluetooth class="w-5 h-5" :class="settings.mode === 'bluetooth' ? 'text-amber-700' : 'text-slate-500'" />
                                    <span v-if="settings.mode === 'bluetooth'" class="w-4 h-4 rounded-full bg-amber-600 text-white flex items-center justify-center text-[10px]">
                                        <Check class="w-3 h-3 stroke-[3]" />
                                    </span>
                                </div>
                                <h4 class="font-bold text-xs text-slate-800">Bluetooth Thermal</h4>
                                <p class="text-[11px] text-slate-500 mt-1 leading-snug">
                                    Langsung cetak ke printer Bluetooth tanpa dialog cetak.
                                </p>
                            </div>

                            <div class="mt-3">
                                <span v-if="isBtConnected" class="text-[10px] font-bold text-emerald-700 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Terhubung
                                </span>
                                <span v-else class="text-[10px] font-bold text-slate-400">Belum Terhubung</span>
                            </div>
                        </div>

                        <!-- Option C: Web USB / Serial Port Direct -->
                        <div
                            @click="settings.mode = 'serial'; saveCurrentSettings()"
                            class="p-3.5 rounded-xl border-2 transition-all cursor-pointer flex flex-col justify-between"
                            :class="settings.mode === 'serial'
                                ? 'border-amber-600 bg-amber-50/60 shadow-xs'
                                : 'border-slate-200 bg-white hover:border-slate-300'"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <Usb class="w-5 h-5" :class="settings.mode === 'serial' ? 'text-amber-700' : 'text-slate-500'" />
                                    <span v-if="settings.mode === 'serial'" class="w-4 h-4 rounded-full bg-amber-600 text-white flex items-center justify-center text-[10px]">
                                        <Check class="w-3 h-3 stroke-[3]" />
                                    </span>
                                </div>
                                <h4 class="font-bold text-xs text-slate-800">Direct USB / COM</h4>
                                <p class="text-[11px] text-slate-500 mt-1 leading-snug">
                                    Komunikasi byte langsung ke port USB printer POS.
                                </p>
                            </div>

                            <div class="mt-3">
                                <span v-if="isSerialConnected" class="text-[10px] font-bold text-emerald-700 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Terhubung
                                </span>
                                <span v-else class="text-[10px] font-bold text-slate-400">Belum Terhubung</span>
                            </div>
                        </div>
                    </div>

                    <!-- Connect Action Controls depending on selected mode -->
                    <div v-if="settings.mode === 'bluetooth'" class="mt-3 p-4 bg-white rounded-xl border border-slate-200">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div>
                                <h5 class="text-xs font-bold text-slate-800">Koneksi Bluetooth Printer</h5>
                                <p class="text-[11px] text-slate-500">
                                    Cocok untuk printer portable Bluetooth seperti RPP02N, Panda, PT-210, Eppos, Iware, dll.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="!isBtConnected"
                                    type="button"
                                    @click="handleConnectBluetooth"
                                    :disabled="isConnecting"
                                    class="px-4 py-2 bg-amber-800 hover:bg-amber-900 text-white rounded-xl text-xs font-bold shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                                >
                                    <Bluetooth class="w-3.5 h-3.5" />
                                    <span>{{ isConnecting ? 'Mencari Perangkat...' : 'Hubungkan Bluetooth' }}</span>
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="handleDisconnectBluetooth"
                                    class="px-3.5 py-2 bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-700 rounded-xl text-xs font-bold border border-slate-200 transition-colors flex items-center gap-1.5 cursor-pointer"
                                >
                                    <Power class="w-3.5 h-3.5" />
                                    <span>Putuskan</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="settings.mode === 'serial'" class="mt-3 p-4 bg-white rounded-xl border border-slate-200">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div>
                                <h5 class="text-xs font-bold text-slate-800">Koneksi Direct USB / Serial</h5>
                                <p class="text-[11px] text-slate-500">
                                    Pilih port USB / Virtual COM printer thermal kasir Anda.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="!isSerialConnected"
                                    type="button"
                                    @click="handleConnectSerial"
                                    :disabled="isConnecting"
                                    class="px-4 py-2 bg-amber-800 hover:bg-amber-900 text-white rounded-xl text-xs font-bold shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                                >
                                    <Usb class="w-3.5 h-3.5" />
                                    <span>{{ isConnecting ? 'Membuka Port...' : 'Pilih Port USB' }}</span>
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="handleDisconnectSerial"
                                    class="px-3.5 py-2 bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-700 rounded-xl text-xs font-bold border border-slate-200 transition-colors flex items-center gap-1.5 cursor-pointer"
                                >
                                    <Power class="w-3.5 h-3.5" />
                                    <span>Putuskan</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="mt-3 p-3.5 bg-slate-100/70 rounded-xl border border-slate-200 text-xs text-slate-600 leading-relaxed">
                        💡 <strong>Tips Driver Sistem:</strong> Menggunakan printer yang sudah terpasang di Windows (USB/LAN). Untuk mencetak langsung tanpa dialog konfirmasi Windows, Anda dapat menjalankan Google Chrome dengan shortcut flag: <code class="bg-white px-1.5 py-0.5 rounded border border-slate-300 font-mono text-[11px]">--kiosk-printing</code>.
                    </div>
                </div>

                <!-- Section 2: Ukuran Kertas & Otomasi -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Ukuran Kertas -->
                    <div class="p-4 bg-white rounded-xl border border-slate-200">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                            <FileText class="w-4 h-4 text-amber-700" />
                            <span>2. Lebar Kertas Struk</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="settings.paperSize = '58mm'; saveCurrentSettings()"
                                class="py-2.5 px-3 rounded-lg border text-xs font-bold text-center transition-all cursor-pointer"
                                :class="settings.paperSize === '58mm'
                                    ? 'bg-amber-800 text-white border-amber-800 shadow-2xs'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                                <div>58 mm</div>
                                <div class="text-[10px] font-normal opacity-80 mt-0.5">Standar Mini/Mobile</div>
                            </button>

                            <button
                                type="button"
                                @click="settings.paperSize = '80mm'; saveCurrentSettings()"
                                class="py-2.5 px-3 rounded-lg border text-xs font-bold text-center transition-all cursor-pointer"
                                :class="settings.paperSize === '80mm'
                                    ? 'bg-amber-800 text-white border-amber-800 shadow-2xs'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                                <div>80 mm</div>
                                <div class="text-[10px] font-normal opacity-80 mt-0.5">Lebar POS Kasir</div>
                            </button>
                        </div>
                    </div>

                    <!-- Otomasi Cetak -->
                    <div class="p-4 bg-white rounded-xl border border-slate-200 flex flex-col justify-between">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                <Zap class="w-4 h-4 text-amber-700" />
                                <span>3. Cetak Otomatis</span>
                            </label>
                            <p class="text-xs text-slate-500 leading-snug">
                                {{ profileKey === 'pos_printer_settings'
                                    ? 'Cetak struk secara otomatis segera setelah kasir menekan tombol konfirmasi bayar.'
                                    : 'Cetak tiket antrean secara otomatis segera setelah pelanggan selesai memesan di Kiosk.' }}
                            </p>
                        </div>

                        <div class="mt-3 flex items-center justify-between pt-2 border-t border-slate-100">
                            <span class="text-xs font-semibold text-slate-700">Auto-Print Struk:</span>
                            <button
                                type="button"
                                @click="settings.autoPrint = !settings.autoPrint; saveCurrentSettings()"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="settings.autoPrint ? 'bg-amber-800' : 'bg-slate-300'"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="settings.autoPrint ? 'translate-x-5' : 'translate-x-0'"
                                />
                            </button>
                        </div>

                        <!-- Kitchen slip toggle (for POS) -->
                        <div v-if="profileKey === 'pos_printer_settings'" class="mt-2 flex items-center justify-between pt-2 border-t border-slate-100">
                            <div>
                                <span class="text-xs font-semibold text-slate-700 block">Cetak Tiket Dapur / Koki:</span>
                                <span class="text-[10px] text-slate-500">Karcis tanpa harga untuk dapur racik soto</span>
                            </div>
                            <button
                                type="button"
                                @click="settings.printKitchen = !settings.printKitchen; saveCurrentSettings()"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="settings.printKitchen ? 'bg-amber-800' : 'bg-slate-300'"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="settings.printKitchen ? 'translate-x-5' : 'translate-x-0'"
                                />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-3.5 bg-white border-t border-slate-200 flex items-center justify-between shrink-0">
                <div class="text-xs text-slate-500">
                    Profil aktif tersimpan di memori browser lokal.
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="handleSaveAndClose"
                        class="px-5 py-2 rounded-xl text-xs font-bold bg-amber-800 hover:bg-amber-900 text-white shadow-xs transition-colors cursor-pointer"
                    >
                        Simpan & Selesai
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
