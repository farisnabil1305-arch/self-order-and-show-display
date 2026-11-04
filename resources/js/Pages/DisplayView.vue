<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Flame,
    CheckCircle2,
    Clock,
    Volume2,
    VolumeX,
    Maximize,
    Minimize,
    Bell,
    Wifi,
    UtensilsCrossed,
    ShoppingBag,
    Sparkles
} from 'lucide-vue-next';

const props = defineProps({
    initialData: {
        type: Object,
        required: true,
    },
});

// Queue Lists
const preparingOrders = ref(props.initialData?.preparing || []);
const readyOrders = ref(props.initialData?.ready || []);
const latestCall = ref(props.initialData?.latest_call || null);
const storeSettings = ref(props.initialData?.store_settings || {});

// Display state
const isFullscreen = ref(false);
const soundEnabled = ref(true);
const audioUnlocked = ref(false);
const activeCallingOrder = ref(null);
let callBannerTimeout = null;

// Clock
const currentTime = ref('');
const currentDate = ref('');

function updateClock() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    currentDate.value = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

// Sound Synthesizer: Two-tone Ding-Dong Bell
function playDingDong() {
    if (!soundEnabled.value) return;
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();

        // Tone 1 (High: 587.33 Hz - D5)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, ctx.currentTime);
        gain1.gain.setValueAtTime(0.4, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.55);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(ctx.currentTime);
        osc1.stop(ctx.currentTime + 0.55);

        // Tone 2 (Lower chord: 440 Hz - A4)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(440, ctx.currentTime + 0.35);
        gain2.gain.setValueAtTime(0.45, ctx.currentTime + 0.35);
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.2);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(ctx.currentTime + 0.35);
        osc2.stop(ctx.currentTime + 1.2);
    } catch (e) {
        console.error('Audio synthesizer error:', e);
    }
}

// Speech Synthesis in Indonesian: Calls both Queue Number and Customer Name
function speakOrderNumber(order) {
    if (!soundEnabled.value || !window.speechSynthesis) return;

    // Convert e.g. "SOTO-008" to "Soto kosong kosong delapan"
    let spokenNumber = (order.order_number || '').replace('-', ' ');
    // Format digits for natural pronunciation
    spokenNumber = spokenNumber.replace(/0/g, 'kosong ');

    // Customer Name (clean, trimmed)
    const customerName = (order.customer_name || '').trim();

    let text = '';
    if (customerName && customerName !== '-' && customerName.toLowerCase() !== 'pelanggan') {
        text = `Nomor antrean ${spokenNumber}, atas nama ${customerName}, silakan mengambil pesanan Anda.`;
    } else {
        text = `Nomor antrean ${spokenNumber}, silakan mengambil pesanan Anda.`;
    }

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'id-ID';
    utterance.rate = 0.95;
    utterance.pitch = 1.05;

    // Find Indonesian voice if present
    const voices = window.speechSynthesis.getVoices();
    const idVoice = voices.find((v) => v.lang.includes('id') || v.lang.includes('ID'));
    if (idVoice) utterance.voice = idVoice;

    // Delay speech slightly to let ding-dong bell finish
    setTimeout(() => {
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utterance);
    }, 700);
}

// Trigger announcement
function triggerAnnouncement(order) {
    activeCallingOrder.value = order;
    playDingDong();
    speakOrderNumber(order);

    if (callBannerTimeout) clearTimeout(callBannerTimeout);
    callBannerTimeout = setTimeout(() => {
        activeCallingOrder.value = null;
    }, 8000);
}

function unlockAudio() {
    audioUnlocked.value = true;
    playDingDong();
}

// Polling
let pollingInterval = null;
let clockInterval = null;
let lastKnownCallCounter = latestCall.value?.call_counter || 0;
let lastKnownCallId = latestCall.value?.id || null;

async function fetchQueueSilently() {
    try {
        const res = await axios.get('/display/api/queue');
        if (res.data?.success && res.data.data) {
            preparingOrders.value = res.data.data.preparing || [];
            readyOrders.value = res.data.data.ready || [];
            if (res.data.data.store_settings) {
                storeSettings.value = res.data.data.store_settings;
            }

            const newLatestCall = res.data.data.latest_call;
            if (newLatestCall) {
                const isNewCall =
                    newLatestCall.call_counter > lastKnownCallCounter ||
                    newLatestCall.id !== lastKnownCallId;

                if (isNewCall) {
                    lastKnownCallCounter = newLatestCall.call_counter;
                    lastKnownCallId = newLatestCall.id;
                    latestCall.value = newLatestCall;
                    triggerAnnouncement(newLatestCall);
                }
            }
        }
    } catch (e) {
        console.error('Queue sync error:', e);
    }
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(() => {
            isFullscreen.value = true;
        }).catch(() => {});
    } else {
        document.exitFullscreen().then(() => {
            isFullscreen.value = false;
        }).catch(() => {});
    }
}

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
    pollingInterval = setInterval(fetchQueueSilently, 2500);

    // Auto-detect if audio is already allowed
    document.addEventListener('click', () => {
        audioUnlocked.value = true;
    }, { once: true });
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    if (pollingInterval) clearInterval(pollingInterval);
    if (callBannerTimeout) clearTimeout(callBannerTimeout);
});
</script>

<template>
    <Head title="Layar Antrean Pelanggan - Soto Betawi Parsoey" />

    <div class="h-screen w-screen bg-slate-100 flex flex-col font-sans select-none overflow-hidden relative text-slate-900">
        <!-- Audio Unlock Notification (Discreet pill if browser blocks sound before click) -->
        <div
            v-if="!audioUnlocked"
            @click="unlockAudio"
            class="fixed top-24 left-1/2 -translate-x-1/2 z-50 bg-white/95 border border-amber-300 text-amber-950 px-5 py-2.5 rounded-full shadow-xl flex items-center gap-2.5 cursor-pointer hover:bg-amber-50 transition-all backdrop-blur-md"
        >
            <Volume2 class="w-4 h-4 text-amber-700" />
            <span class="text-xs font-bold">Sentuh layar untuk mengaktifkan bel panggilan suara</span>
        </div>

        <!-- Floating Announcement Card during Voice Calling -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-4 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 -translate-y-4 scale-95"
        >
            <div
                v-if="activeCallingOrder"
                class="fixed top-6 inset-x-4 max-w-xl mx-auto z-40 bg-white border-2 border-emerald-500 rounded-3xl p-6 sm:p-7 text-slate-900 shadow-2xl flex flex-col items-center text-center animate-in zoom-in-95 duration-200"
            >
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-100 text-emerald-900 text-xs font-bold tracking-wider uppercase mb-2 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-600 animate-ping"></span>
                    <span>Panggilan Pengambilan Pesanan</span>
                </div>

                <div class="text-5xl sm:text-6xl font-black font-mono tracking-widest my-1 text-emerald-700">
                    {{ activeCallingOrder.order_number }}
                </div>

                <div class="text-lg sm:text-xl font-bold flex flex-wrap items-center justify-center gap-2 mt-1">
                    <span class="text-slate-800">Atas Nama: <strong class="text-amber-900">{{ activeCallingOrder.customer_name || 'Pelanggan' }}</strong></span>
                    <span class="text-slate-300">•</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-200">
                        {{ activeCallingOrder.order_type === 'dine_in' ? `Meja ${activeCallingOrder.table_number || '-'}` : 'Bawa Pulang' }}
                    </span>
                </div>

                <p class="text-xs text-slate-500 mt-2 font-medium">
                    Silakan menuju konter pengambilan pesanan untuk menikmati hidangan Anda.
                </p>
            </div>
        </transition>

        <!-- Modern Header matching Self-Order Kiosk theme -->
        <header class="h-20 bg-white border-b border-slate-200 px-6 sm:px-8 flex items-center justify-between shrink-0 shadow-2xs">
            <!-- Brand / Logo matching KioskView -->
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-900 via-amber-800 to-amber-950 flex items-center justify-center text-white font-black text-sm tracking-wider shadow-xs border border-amber-900/30">
                    SBP
                </div>
                <div>
                    <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight uppercase">
                        {{ storeSettings.store_name || 'SOTO BETAWI PARSOEY' }}
                    </h1>
                    <p class="text-xs text-slate-500 font-semibold">Layar Antrean Pelanggan</p>
                </div>
            </div>

            <!-- Middle Free WiFi Info (Kiosk pill style) -->
            <div v-if="storeSettings.wifi_ssid || storeSettings.wifi_password" class="hidden md:flex items-center gap-2.5 px-4 py-1.5 rounded-xl bg-amber-50 border border-amber-200/80 shadow-2xs text-xs font-semibold text-amber-950">
                <Wifi class="w-3.5 h-3.5 text-amber-700" />
                <span>Wi-Fi: <strong class="text-amber-950 font-bold">{{ storeSettings.wifi_ssid || 'Resto' }}</strong></span>
                <span class="text-amber-300">•</span>
                <span>Sandi: <strong class="text-amber-900 font-mono font-bold">{{ storeSettings.wifi_password || '-' }}</strong></span>
            </div>

            <!-- Right Clock & Controls -->
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <div class="text-xl sm:text-2xl font-black font-mono tracking-tight text-slate-900 leading-tight">
                        {{ currentTime }}
                    </div>
                    <div class="text-[11px] text-slate-500 font-medium capitalize">
                        {{ currentDate }}
                    </div>
                </div>

                <div class="h-7 w-px bg-slate-200"></div>

                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="soundEnabled = !soundEnabled"
                        class="p-2.5 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors border border-slate-200 cursor-pointer shadow-2xs"
                        :title="soundEnabled ? 'Matikan Suara Bel' : 'Nyalakan Suara Bel'"
                    >
                        <Volume2 v-if="soundEnabled" class="w-4 h-4 text-amber-700" />
                        <VolumeX v-else class="w-4 h-4 text-slate-400" />
                    </button>

                    <button
                        type="button"
                        @click="toggleFullscreen"
                        class="p-2.5 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors border border-slate-200 cursor-pointer shadow-2xs"
                        title="Layar Penuh TV"
                    >
                        <Maximize v-if="!isFullscreen" class="w-4 h-4 text-slate-700" />
                        <Minimize v-else class="w-4 h-4 text-slate-700" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Body: Two Clean Cards with Kiosk Aesthetic -->
        <main class="flex-1 min-h-0 p-4 sm:p-6 grid grid-cols-1 lg:grid-cols-2 gap-5 overflow-hidden">
            <!-- ================= LEFT COLUMN: SEDANG DISIAPKAN (PREPARING) ================= -->
            <section class="bg-white rounded-3xl border border-slate-200 shadow-sm flex flex-col h-full overflow-hidden">
                <!-- Column Header -->
                <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between shrink-0 bg-gradient-to-r from-amber-50/60 via-white to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-900 flex items-center justify-center border border-amber-200/80 shadow-2xs">
                            <Flame class="w-6 h-6 text-amber-700" />
                        </div>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Sedang Disiapkan</h2>
                            <p class="text-xs text-slate-500 font-medium mt-0.5">Pesanan sedang diracik hangat oleh koki di dapur</p>
                        </div>
                    </div>

                    <span class="px-3.5 py-1.5 rounded-full text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200/80 shadow-2xs font-mono">
                        {{ preparingOrders.length }} Pesanan
                    </span>
                </div>

                <!-- Numbers List -->
                <div class="flex-1 overflow-y-auto overscroll-contain p-5 sm:p-6 min-h-0">
                    <div v-if="preparingOrders.length === 0" class="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                        <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-3">
                            <Clock class="w-8 h-8 text-slate-300" />
                        </div>
                        <p class="text-sm font-bold text-slate-600">Tidak ada antrean dalam proses racikan</p>
                        <p class="text-xs text-slate-400 mt-1">Pesanan baru akan muncul otomatis di sini.</p>
                    </div>

                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3.5">
                        <div
                            v-for="order in preparingOrders"
                            :key="order.id"
                            class="p-4 rounded-2xl bg-slate-50/90 hover:bg-amber-50/50 border border-slate-200/80 hover:border-amber-300 shadow-2xs flex flex-col justify-between transition-all"
                        >
                            <div class="text-2xl sm:text-3xl font-black font-mono tracking-wider text-slate-900">
                                {{ order.order_number }}
                            </div>
                            <div class="mt-3 pt-2.5 border-t border-slate-200/70 flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-700 truncate max-w-[100px]">
                                    {{ order.customer_name }}
                                </span>
                                <span class="text-[11px] font-bold text-amber-900 bg-amber-100 px-2 py-0.5 rounded-md border border-amber-200/60">
                                    {{ order.order_type === 'dine_in' ? `Meja ${order.table_number || '-'}` : 'Bungkus' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ================= RIGHT COLUMN: SIAP DIAMBIL (READY FOR PICKUP) ================= -->
            <section class="bg-white rounded-3xl border border-emerald-200 shadow-sm flex flex-col h-full overflow-hidden">
                <!-- Column Header -->
                <div class="p-5 sm:p-6 border-b border-emerald-100 flex items-center justify-between shrink-0 bg-gradient-to-r from-emerald-50/70 via-white to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-100 text-emerald-900 flex items-center justify-center border border-emerald-200/80 shadow-2xs">
                            <CheckCircle2 class="w-6 h-6 text-emerald-700" />
                        </div>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Siap Diambil</h2>
                            <p class="text-xs text-slate-500 font-medium mt-0.5">Silakan menuju ke konter pengambilan pesanan</p>
                        </div>
                    </div>

                    <span class="px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs font-mono">
                        {{ readyOrders.length }} Siap Saji
                    </span>
                </div>

                <!-- Numbers List -->
                <div class="flex-1 overflow-y-auto overscroll-contain p-5 sm:p-6 min-h-0">
                    <div v-if="readyOrders.length === 0" class="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                        <div class="w-16 h-16 rounded-full bg-emerald-50 flex items-center justify-center mb-3">
                            <CheckCircle2 class="w-8 h-8 text-emerald-300" />
                        </div>
                        <p class="text-sm font-bold text-slate-600">Belum ada pesanan yang siap saji</p>
                        <p class="text-xs text-slate-400 mt-1">Nomor pesanan yang selesai dimasak akan tampil di sini.</p>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div
                            v-for="order in readyOrders"
                            :key="order.id"
                            class="p-5 rounded-2xl border-2 flex flex-col justify-between transition-all duration-300 shadow-xs"
                            :class="[
                                activeCallingOrder?.id === order.id
                                    ? 'bg-gradient-to-br from-emerald-600 via-emerald-600 to-teal-700 text-white border-emerald-500 ring-4 ring-emerald-300/50 shadow-xl scale-[1.02]'
                                    : 'bg-emerald-50/60 hover:bg-emerald-50 border-emerald-300/80 hover:border-emerald-400'
                            ]"
                        >
                            <div class="flex items-baseline justify-between">
                                <span
                                    class="text-4xl sm:text-5xl font-black font-mono tracking-wider"
                                    :class="activeCallingOrder?.id === order.id ? 'text-white' : 'text-emerald-700'"
                                >
                                    {{ order.order_number }}
                                </span>
                                <span
                                    class="px-2.5 py-0.5 rounded-full text-xs font-black tracking-wider uppercase"
                                    :class="activeCallingOrder?.id === order.id ? 'bg-white/20 text-white' : 'bg-emerald-200/70 text-emerald-900'"
                                >
                                    {{ activeCallingOrder?.id === order.id ? 'DIPANGGIL' : 'SIAP' }}
                                </span>
                            </div>

                            <div
                                class="mt-4 pt-3 border-t flex items-center justify-between"
                                :class="activeCallingOrder?.id === order.id ? 'border-white/20' : 'border-emerald-200/70'"
                            >
                                <div
                                    class="text-base font-bold truncate max-w-[150px]"
                                    :class="activeCallingOrder?.id === order.id ? 'text-white' : 'text-slate-900'"
                                >
                                    {{ order.customer_name }}
                                </div>
                                <span
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold shadow-2xs"
                                    :class="activeCallingOrder?.id === order.id
                                        ? 'bg-white text-emerald-900'
                                        : 'bg-white text-emerald-800 border border-emerald-200'"
                                >
                                    {{ order.order_type === 'dine_in' ? `Meja ${order.table_number || '-'}` : 'Bawa Pulang' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Running Marquee Ticker Footer (Teks Berjalan Kiosk-style) -->
        <footer class="h-12 bg-white border-t border-slate-200 px-4 sm:px-6 shrink-0 flex items-center justify-between text-xs text-slate-600 overflow-hidden relative shadow-xs">
            <!-- Left Info Badge -->
            <div class="flex items-center gap-2 px-3 py-1 rounded-xl bg-gradient-to-r from-amber-900 via-amber-800 to-amber-950 text-white font-bold text-xs shrink-0 shadow-2xs z-10 border border-amber-900/30">
                <Sparkles class="w-3.5 h-3.5 text-amber-300 shrink-0" />
                <span class="tracking-wider uppercase text-[11px] font-black">INFO RESTO</span>
            </div>

            <!-- Continuous Smooth Running Text (Marquee) -->
            <div class="flex-1 overflow-hidden relative mx-3 sm:mx-4 h-full flex items-center">
                <div class="animate-marquee">
                    <!-- Segment 1 -->
                    <div class="inline-flex items-center gap-6 text-xs font-semibold text-slate-700 pr-6 shrink-0">
                        <span class="inline-flex items-center gap-1.5">
                            <strong class="text-amber-900 font-extrabold">{{ storeSettings.store_name || 'Soto Betawi Parsoey' }}</strong>
                            <span class="text-slate-300">—</span>
                            <span class="text-slate-600">{{ storeSettings.store_tagline || 'Cita Rasa Gurih Kuah Santan & Susu Rempah Asli Betawi' }}</span>
                        </span>

                        <span class="text-slate-300">•</span>

                        <span v-if="storeSettings.store_address" class="inline-flex items-center gap-1.5 text-slate-600">
                            <span class="text-slate-400 font-medium">Alamat:</span>
                            <span>{{ storeSettings.store_address }}</span>
                            <span class="text-slate-300">•</span>
                        </span>

                        <span v-if="storeSettings.store_phone" class="inline-flex items-center gap-1.5 text-slate-600">
                            <span class="text-slate-400 font-medium">WhatsApp:</span>
                            <span class="text-emerald-700 font-bold font-mono">{{ storeSettings.store_phone }}</span>
                            <span class="text-slate-300">•</span>
                        </span>

                        <span v-if="storeSettings.wifi_ssid" class="inline-flex items-center gap-1.5 text-slate-700">
                            <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 text-[10px] font-bold">Free Wi-Fi</span>
                            <span class="font-bold">{{ storeSettings.wifi_ssid }}</span>
                            <span class="text-slate-400">(Sandi: <strong class="font-mono text-slate-700">{{ storeSettings.wifi_password || '-' }}</strong>)</span>
                            <span class="text-slate-300">•</span>
                        </span>

                        <span class="inline-flex items-center gap-1.5 text-amber-900 font-bold">
                            <span>🍲 Tunjukkan karcis nomor antrean saat mengambil hidangan di konter • Selamat menikmati hidangan Soto Betawi Parsoey!</span>
                        </span>
                    </div>

                    <!-- Segment 2 (Duplicate for seamless continuous loop) -->
                    <div class="inline-flex items-center gap-6 text-xs font-semibold text-slate-700 pr-6 shrink-0">
                        <span class="inline-flex items-center gap-1.5">
                            <strong class="text-amber-900 font-extrabold">{{ storeSettings.store_name || 'Soto Betawi Parsoey' }}</strong>
                            <span class="text-slate-300">—</span>
                            <span class="text-slate-600">{{ storeSettings.store_tagline || 'Cita Rasa Gurih Kuah Santan & Susu Rempah Asli Betawi' }}</span>
                        </span>

                        <span class="text-slate-300">•</span>

                        <span v-if="storeSettings.store_address" class="inline-flex items-center gap-1.5 text-slate-600">
                            <span class="text-slate-400 font-medium">Alamat:</span>
                            <span>{{ storeSettings.store_address }}</span>
                            <span class="text-slate-300">•</span>
                        </span>

                        <span v-if="storeSettings.store_phone" class="inline-flex items-center gap-1.5 text-slate-600">
                            <span class="text-slate-400 font-medium">WhatsApp:</span>
                            <span class="text-emerald-700 font-bold font-mono">{{ storeSettings.store_phone }}</span>
                            <span class="text-slate-300">•</span>
                        </span>

                        <span v-if="storeSettings.wifi_ssid" class="inline-flex items-center gap-1.5 text-slate-700">
                            <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 text-[10px] font-bold">Free Wi-Fi</span>
                            <span class="font-bold">{{ storeSettings.wifi_ssid }}</span>
                            <span class="text-slate-400">(Sandi: <strong class="font-mono text-slate-700">{{ storeSettings.wifi_password || '-' }}</strong>)</span>
                            <span class="text-slate-300">•</span>
                        </span>

                        <span class="inline-flex items-center gap-1.5 text-amber-900 font-bold">
                            <span>🍲 Tunjukkan karcis nomor antrean saat mengambil hidangan di konter • Selamat menikmati hidangan Soto Betawi Parsoey!</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right Fixed Year & Title -->
            <div class="shrink-0 text-slate-400 font-mono text-[11px] hidden sm:flex items-center gap-1.5 pl-3 border-l border-slate-200 bg-white z-10">
                <span>© {{ new Date().getFullYear() }}</span>
                <span>•</span>
                <span>Layar Antrean</span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@keyframes marquee {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(-50%, 0, 0);
    }
}

.animate-marquee {
    display: inline-flex;
    white-space: nowrap;
    will-change: transform;
    animation: marquee 35s linear infinite;
}

.animate-marquee:hover {
    animation-play-state: paused;
}
</style>
