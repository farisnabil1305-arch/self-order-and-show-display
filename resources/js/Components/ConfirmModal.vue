<script setup>
import { onMounted, onUnmounted } from 'vue';
import { AlertTriangle, Trash2, HelpCircle, X } from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Konfirmasi Tindakan',
    },
    message: {
        type: String,
        default: 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
    },
    confirmText: {
        type: String,
        default: 'Ya, Lanjutkan',
    },
    cancelText: {
        type: String,
        default: 'Batal',
    },
    type: {
        type: String,
        default: 'danger', // 'danger' | 'warning' | 'info'
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['confirm', 'cancel', 'close']);

function handleKeyDown(e) {
    if (!props.show) return;
    if (e.key === 'Escape') {
        emit('cancel');
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <!-- Root Container / Teleport Target -->
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 select-none">
        <!-- Smooth Animated Backdrop -->
        <transition
            appear
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                class="fixed inset-0 bg-[#231812]/50 backdrop-blur-2xs"
                @click="emit('cancel')"
            ></div>
        </transition>

        <!-- Smooth Animated Dialog Card (Refined, Artisanal, Zero AI Slop) -->
        <transition
            appear
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-3"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-2"
        >
            <div
                class="relative w-full max-w-md bg-white rounded-3xl border border-[#E8DFD1] shadow-2xl p-6 sm:p-7 overflow-hidden z-10"
                @click.stop
            >
                <!-- Close Top Corner Button -->
                <button
                    type="button"
                    @click="emit('cancel')"
                    class="absolute top-4 right-4 p-2 text-[#7A6A60] hover:text-[#231812] hover:bg-[#FAF7F2] rounded-xl transition-colors cursor-pointer"
                    title="Batal / Tutup"
                >
                    <X class="w-4 h-4" />
                </button>

                <div class="flex items-start gap-4">
                    <!-- Icon Badge -->
                    <div
                        class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 border"
                        :class="[
                            type === 'danger'
                                ? 'bg-[#FEF2F2] text-[#B91C1C] border-[#FECACA]'
                                : (type === 'warning'
                                    ? 'bg-[#FEF3C7] text-[#D97706] border-[#FDE68A]'
                                    : 'bg-[#FAF7F2] text-[#A8421E] border-[#E8DFD1]')
                        ]"
                    >
                        <Trash2 v-if="type === 'danger'" class="w-6 h-6" />
                        <AlertTriangle v-else-if="type === 'warning'" class="w-6 h-6" />
                        <HelpCircle v-else class="w-6 h-6" />
                    </div>

                    <!-- Text Details -->
                    <div class="flex-1 pr-6">
                        <h3 class="font-serif text-lg font-bold text-[#231812] tracking-tight leading-snug">
                            {{ title }}
                        </h3>
                        <p class="text-xs text-[#7A6A60] mt-1.5 leading-relaxed">
                            {{ message }}
                        </p>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="mt-6 pt-5 border-t border-[#E8DFD1]/80 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        @click="emit('cancel')"
                        class="px-4 py-2.5 rounded-xl border border-[#E8DFD1] bg-white hover:bg-[#FAF7F2] text-[#5C4A3E] font-bold text-xs transition-colors cursor-pointer"
                    >
                        {{ cancelText }}
                    </button>

                    <button
                        type="button"
                        @click="emit('confirm')"
                        :disabled="isLoading"
                        class="px-5 py-2.5 rounded-xl text-white font-bold text-xs shadow-xs transition-all active:scale-[0.98] cursor-pointer flex items-center gap-1.5 disabled:opacity-50"
                        :class="[
                            type === 'danger'
                                ? 'bg-[#B91C1C] hover:bg-[#991B1B]'
                                : (type === 'warning'
                                    ? 'bg-[#D97706] hover:bg-[#B45309]'
                                    : 'bg-[#A8421E] hover:bg-[#8F3314]')
                        ]"
                    >
                        <span v-if="isLoading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <span>{{ confirmText }}</span>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
