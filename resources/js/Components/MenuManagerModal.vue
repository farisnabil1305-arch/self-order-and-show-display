<script setup>
import { ref, reactive, computed, watch } from 'vue';
import axios from 'axios';
import { formatRupiah } from '@/Utils/formatters';
import {
    UtensilsCrossed,
    FolderPlus,
    Plus,
    Trash2,
    Edit3,
    CheckCircle2,
    AlertCircle,
    X,
    UploadCloud,
    Image as ImageIcon,
    Tag,
    Layers,
    DollarSign,
    Sparkles,
    Eye,
    EyeOff
} from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'updated']);

// State
const activeTab = ref('products'); // 'products' | 'categories'
const isLoading = ref(false);
const categories = ref([]);
const products = ref([]);
const selectedCategoryFilter = ref('all');

// Alert message
const alertMessage = ref(null);
const alertType = ref('success'); // 'success' | 'error'

function showAlert(msg, type = 'success') {
    alertMessage.value = msg;
    alertType.value = type;
    setTimeout(() => {
        if (alertMessage.value === msg) {
            alertMessage.value = null;
        }
    }, 4000);
}

// Fetch menu data from API
async function loadMenuData() {
    isLoading.value = true;
    try {
        const response = await axios.get('/pos/api/menu-data');
        if (response.data?.success) {
            categories.value = response.data.categories || [];
            products.value = response.data.products || [];
        }
    } catch (e) {
        console.error('Gagal memuat data menu:', e);
        showAlert('Gagal memuat data menu dari server.', 'error');
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            loadMenuData();
            showProductForm.value = false;
            editingProductId.value = null;
        }
    }
);

// Filtered products
const filteredProducts = computed(() => {
    if (selectedCategoryFilter.value === 'all') {
        return products.value;
    }
    return products.value.filter(
        (p) => p.category_id === Number(selectedCategoryFilter.value)
    );
});

// Category Form
const newCategoryName = ref('');
const isSubmittingCategory = ref(false);

async function submitCategory() {
    if (!newCategoryName.value.trim()) {
        showAlert('Silakan masukkan nama kategori!', 'error');
        return;
    }
    isSubmittingCategory.value = true;
    try {
        const res = await axios.post('/pos/categories', {
            name: newCategoryName.value.trim(),
        });
        if (res.data?.success) {
            showAlert(res.data.message || 'Kategori berhasil ditambahkan!');
            newCategoryName.value = '';
            await loadMenuData();
            emit('updated');
        }
    } catch (err) {
        const msg = err.response?.data?.message || 'Gagal menambahkan kategori.';
        showAlert(msg, 'error');
    } finally {
        isSubmittingCategory.value = false;
    }
}

async function deleteCategory(category) {
    if (category.products_count > 0) {
        showAlert(`Kategori "${category.name}" memiliki ${category.products_count} menu. Hapus atau pindahkan menu terlebih dahulu!`, 'error');
        return;
    }
    if (!confirm(`Yakin ingin menghapus kategori "${category.name}"?`)) return;

    try {
        const res = await axios.delete(`/pos/categories/${category.id}`);
        if (res.data?.success) {
            showAlert(res.data.message || 'Kategori berhasil dihapus!');
            await loadMenuData();
            emit('updated');
        }
    } catch (err) {
        const msg = err.response?.data?.message || 'Gagal menghapus kategori.';
        showAlert(msg, 'error');
    }
}

// Product Form
const showProductForm = ref(false);
const editingProductId = ref(null);
const isSubmittingProduct = ref(false);
const imageFile = ref(null);
const imagePreviewUrl = ref('');

const productForm = reactive({
    name: '',
    category_id: '',
    price: '',
    description: '',
    taste_profile: '',
    ingredients_input: '',
    image_url: '',
    is_available: true,
});

function openAddProduct() {
    editingProductId.value = null;
    productForm.name = '';
    productForm.category_id = categories.value.length > 0 ? categories.value[0].id : '';
    productForm.price = '';
    productForm.description = '';
    productForm.taste_profile = '';
    productForm.ingredients_input = '';
    productForm.image_url = '';
    productForm.is_available = true;
    imageFile.value = null;
    imagePreviewUrl.value = '';
    showProductForm.value = true;
}

function openEditProduct(product) {
    editingProductId.value = product.id;
    productForm.name = product.name;
    productForm.category_id = product.category_id;
    productForm.price = product.price;
    productForm.description = product.description || '';
    productForm.taste_profile = product.taste_profile || '';
    productForm.ingredients_input = Array.isArray(product.ingredients)
        ? product.ingredients.join(', ')
        : (product.ingredients || '');
    productForm.image_url = product.image_url || '';
    productForm.is_available = Boolean(product.is_available);
    imageFile.value = null;
    imagePreviewUrl.value = product.image_url || '';
    showProductForm.value = true;
}

function handleFileChange(event) {
    const file = event.target.files[0];
    if (file) {
        imageFile.value = file;
        imagePreviewUrl.value = URL.createObjectURL(file);
    }
}

async function submitProduct() {
    if (!productForm.name.trim()) {
        showAlert('Silakan isi nama menu!', 'error');
        return;
    }
    if (!productForm.category_id) {
        showAlert('Silakan pilih kategori menu!', 'error');
        return;
    }
    if (productForm.price === '' || Number(productForm.price) < 0) {
        showAlert('Silakan isi harga menu yang valid!', 'error');
        return;
    }

    isSubmittingProduct.value = true;
    try {
        const formData = new FormData();
        formData.append('name', productForm.name.trim());
        formData.append('category_id', productForm.category_id);
        formData.append('price', Math.round(Number(productForm.price)));
        formData.append('description', productForm.description.trim());
        formData.append('taste_profile', productForm.taste_profile.trim());
        formData.append('ingredients', productForm.ingredients_input.trim());
        formData.append('is_available', productForm.is_available ? '1' : '0');

        if (imageFile.value) {
            formData.append('image_file', imageFile.value);
        } else if (productForm.image_url) {
            formData.append('image_url', productForm.image_url.trim());
        }

        let res;
        if (editingProductId.value) {
            res = await axios.post(`/pos/products/${editingProductId.value}/update`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
        } else {
            res = await axios.post('/pos/products', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
        }

        if (res.data?.success) {
            showAlert(res.data.message || 'Menu berhasil disimpan!');
            showProductForm.value = false;
            await loadMenuData();
            emit('updated');
        }
    } catch (err) {
        const msg = err.response?.data?.message || 'Gagal menyimpan menu.';
        showAlert(msg, 'error');
    } finally {
        isSubmittingProduct.value = false;
    }
}

async function toggleAvailability(product) {
    try {
        const res = await axios.post(`/pos/products/${product.id}/toggle`);
        if (res.data?.success) {
            product.is_available = res.data.is_available;
            showAlert(`Status "${product.name}" diubah menjadi ${product.is_available ? 'Tersedia' : 'Habis'}.`);
            emit('updated');
        }
    } catch (err) {
        showAlert('Gagal mengubah status menu.', 'error');
    }
}

async function deleteProduct(product) {
    if (!confirm(`Hapus menu "${product.name}" secara permanen dari database?`)) return;

    try {
        const res = await axios.delete(`/pos/products/${product.id}`);
        if (res.data?.success) {
            showAlert(res.data.message || 'Menu berhasil dihapus!');
            await loadMenuData();
            emit('updated');
        }
    } catch (err) {
        const msg = err.response?.data?.message || 'Gagal menghapus menu.';
        showAlert(msg, 'error');
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/60 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden border border-slate-200">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-[#FAF7F2] border-b border-[#E8DFD1] text-[#231812] flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#231812] text-[#F5EBE1] flex items-center justify-center border border-[#3D281F]">
                        <UtensilsCrossed class="w-5 h-5 text-[#F5EBE1]" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-serif text-lg font-bold text-[#231812]">Kelola Menu & Kategori</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#EFE8DE] text-[#7A6A60] border border-[#E8DFD1]">
                                Master Data
                            </span>
                        </div>
                        <p class="text-xs text-[#7A6A60]">Tambah, edit, dan atur ketersediaan menu makanan & minuman resto.</p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="p-2 text-[#7A6A60] hover:text-[#231812] hover:bg-[#EFE8DE] rounded-xl transition-all cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Toast / Alert Notification -->
            <div
                v-if="alertMessage"
                class="px-6 py-2.5 flex items-center gap-2 text-sm font-medium transition-all"
                :class="alertType === 'success' ? 'bg-emerald-50 text-emerald-800 border-b border-emerald-200' : 'bg-red-50 text-red-800 border-b border-red-200'"
            >
                <CheckCircle2 v-if="alertType === 'success'" class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                <AlertCircle v-else class="w-4 h-4 text-red-600 flex-shrink-0" />
                <span>{{ alertMessage }}</span>
            </div>

            <!-- Tab Navigation & Action Bar -->
            <div class="px-6 py-3 border-b border-slate-200 bg-slate-50/70 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button
                        @click="activeTab = 'products'; showProductForm = false;"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                        :class="activeTab === 'products' ? 'bg-amber-800 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                    >
                        <UtensilsCrossed class="w-4 h-4" />
                        Daftar Menu ({{ products.length }})
                    </button>
                    <button
                        @click="activeTab = 'categories'"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                        :class="activeTab === 'categories' ? 'bg-amber-800 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                    >
                        <Layers class="w-4 h-4" />
                        Kategori Menu ({{ categories.length }})
                    </button>
                </div>

                <div v-if="activeTab === 'products' && !showProductForm" class="flex items-center gap-2">
                    <button
                        @click="openAddProduct"
                        class="px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-all flex items-center gap-1.5"
                    >
                        <Plus class="w-4 h-4" />
                        Tambah Menu Baru
                    </button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="flex-1 overflow-y-auto overscroll-contain p-6 bg-slate-50/30">
                <!-- TAB 1: PRODUCT MANAGEMENT -->
                <div v-if="activeTab === 'products'">
                    <!-- Form Tambah / Edit Menu -->
                    <div v-if="showProductForm" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm mb-6">
                        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">
                                    {{ editingProductId ? 'Edit Menu' : 'Tambah Menu Baru' }}
                                </h3>
                                <p class="text-xs text-slate-500">Isi data produk makanan atau minuman di bawah</p>
                            </div>
                            <button
                                @click="showProductForm = false"
                                class="text-xs font-semibold text-slate-500 hover:text-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-100"
                            >
                                Tutup Form
                            </button>
                        </div>

                        <form @submit.prevent="submitProduct" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Nama Menu -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Nama Menu *
                                    </label>
                                    <input
                                        v-model="productForm.name"
                                        type="text"
                                        placeholder="Contoh: Soto Betawi Daging Sapi"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20"
                                        required
                                    />
                                </div>

                                <!-- Kategori -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Kategori *
                                    </label>
                                    <div class="flex gap-2">
                                        <select
                                            v-model="productForm.category_id"
                                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20"
                                            required
                                        >
                                            <option value="" disabled>-- Pilih Kategori --</option>
                                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                                {{ cat.name }}
                                            </option>
                                        </select>
                                        <button
                                            type="button"
                                            @click="activeTab = 'categories'"
                                            title="Buat Kategori Baru"
                                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold whitespace-nowrap border border-slate-200"
                                        >
                                            + Buat Kategori
                                        </button>
                                    </div>
                                    <p v-if="categories.length === 0" class="text-xs text-rose-500 mt-1">
                                        Belum ada kategori! Silakan buat kategori terlebih dahulu.
                                    </p>
                                </div>

                                <!-- Harga (Rp) -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Harga (Rp) *
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3.5 top-2.5 text-sm font-semibold text-slate-400">Rp</span>
                                        <input
                                            v-model="productForm.price"
                                            type="number"
                                            min="0"
                                            placeholder="35000"
                                            class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 font-semibold"
                                            required
                                        />
                                    </div>
                                </div>

                                <!-- Status Ketersediaan -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Status Stok
                                    </label>
                                    <div class="flex items-center gap-4 py-2">
                                        <label class="flex items-center gap-2 cursor-pointer text-sm font-medium">
                                            <input
                                                type="radio"
                                                :value="true"
                                                v-model="productForm.is_available"
                                                class="text-amber-700 focus:ring-amber-600"
                                            />
                                            <span class="text-emerald-700 font-semibold flex items-center gap-1">
                                                <Eye class="w-4 h-4" /> Tersedia
                                            </span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer text-sm font-medium">
                                            <input
                                                type="radio"
                                                :value="false"
                                                v-model="productForm.is_available"
                                                class="text-amber-700 focus:ring-amber-600"
                                            />
                                            <span class="text-rose-600 font-semibold flex items-center gap-1">
                                                <EyeOff class="w-4 h-4" /> Habis (Sold Out)
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Deskripsi Singkat -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Deskripsi Singkat Menu
                                </label>
                                <textarea
                                    v-model="productForm.description"
                                    rows="2"
                                    placeholder="Contoh: Potongan daging sapi empuk dengan kuah susu santan rempah gurih khas Betawi..."
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20"
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Profil Rasa -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Profil Rasa (Opsional)
                                    </label>
                                    <input
                                        v-model="productForm.taste_profile"
                                        type="text"
                                        placeholder="Contoh: Gurih Santan Berempah, Segar Jeruk Limau"
                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20"
                                    />
                                </div>

                                <!-- Bahan Baku / Komposisi -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Bahan Baku / Komposisi (Pisahkan dengan koma)
                                    </label>
                                    <input
                                        v-model="productForm.ingredients_input"
                                        type="text"
                                        placeholder="Contoh: Daging Sapi Halal, Santan & Susu Segar, Tomat Merah, Emping"
                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20"
                                    />
                                </div>
                            </div>

                            <!-- Foto Menu: Upload File atau Masukkan URL -->
                            <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Foto / Gambar Menu
                                </label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                                    <div>
                                        <div class="text-xs font-medium text-slate-600 mb-1">Opsi A: Upload Foto dari Komputer</div>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            @change="handleFileChange"
                                            class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200"
                                        />
                                        <div class="text-xs font-medium text-slate-600 mt-2 mb-1">Opsi B: Atau Tempel URL Gambar Web</div>
                                        <input
                                            v-model="productForm.image_url"
                                            type="url"
                                            placeholder="https://... atau /images/..."
                                            class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none focus:border-amber-600"
                                        />
                                    </div>
                                    <!-- Image Preview -->
                                    <div class="flex items-center gap-3">
                                        <div class="w-20 h-20 rounded-xl border-2 border-dashed border-slate-300 bg-white flex items-center justify-center overflow-hidden flex-shrink-0">
                                            <img
                                                v-if="imagePreviewUrl || productForm.image_url"
                                                :src="imagePreviewUrl || productForm.image_url"
                                                alt="Preview"
                                                class="w-full h-full object-cover"
                                            />
                                            <ImageIcon v-else class="w-8 h-8 text-slate-300" />
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            <p class="font-medium text-slate-700">Preview Tampilan</p>
                                            <p>Foto akan tampil di layar Kiosk mandiri pelanggan.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="showProductForm = false"
                                    class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="isSubmittingProduct"
                                    class="px-6 py-2.5 rounded-xl text-sm font-bold bg-amber-800 hover:bg-amber-900 text-white shadow-md transition-all flex items-center gap-2 disabled:opacity-50"
                                >
                                    <CheckCircle2 class="w-4 h-4" />
                                    {{ isSubmittingProduct ? 'Menyimpan...' : (editingProductId ? 'Simpan Perubahan' : 'Tambahkan Menu') }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Category Filter Pills -->
                    <div v-if="categories.length > 0" class="flex items-center gap-2 overflow-x-auto pb-3 mb-4 scrollbar-none">
                        <button
                            @click="selectedCategoryFilter = 'all'"
                            class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all whitespace-nowrap"
                            :class="selectedCategoryFilter === 'all' ? 'bg-amber-800 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                        >
                            Semua Menu ({{ products.length }})
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="selectedCategoryFilter = String(cat.id)"
                            class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all whitespace-nowrap flex items-center gap-1.5"
                            :class="selectedCategoryFilter === String(cat.id) ? 'bg-amber-800 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                        >
                            <span>{{ cat.name }}</span>
                            <span class="text-[10px] px-1.5 py-0.2 rounded-full" :class="selectedCategoryFilter === String(cat.id) ? 'bg-amber-950 text-amber-200' : 'bg-slate-100 text-slate-500'">
                                {{ cat.products_count || 0 }}
                            </span>
                        </button>
                    </div>

                    <!-- Products Grid / Empty State -->
                    <div v-if="products.length === 0 && !showProductForm" class="text-center py-12 bg-white rounded-2xl border border-slate-200">
                        <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-800 flex items-center justify-center mx-auto mb-3">
                            <UtensilsCrossed class="w-8 h-8" />
                        </div>
                        <h4 class="font-bold text-slate-800 text-base mb-1">Database Menu Masih Kosong</h4>
                        <p class="text-sm text-slate-500 max-w-md mx-auto mb-5">
                            Belum ada menu yang diinput ke database MySQL `sotoparsoey_db`. Silakan tambahkan kategori dan menu pertama Anda sekarang!
                        </p>
                        <div class="flex items-center justify-center gap-3">
                            <button
                                v-if="categories.length === 0"
                                @click="activeTab = 'categories'"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold bg-amber-800 hover:bg-amber-900 text-white shadow-md transition-all flex items-center gap-2"
                            >
                                <FolderPlus class="w-4 h-4" />
                                1. Tambah Kategori Dahulu
                            </button>
                            <button
                                v-else
                                @click="openAddProduct"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md transition-all flex items-center gap-2"
                            >
                                <Plus class="w-4 h-4" />
                                Tambah Menu Pertama
                            </button>
                        </div>
                    </div>

                    <!-- Product Cards Grid -->
                    <div v-else-if="!showProductForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col justify-between"
                        >
                            <div class="p-4">
                                <div class="flex items-start gap-3">
                                    <!-- Thumbnail -->
                                    <div class="w-16 h-16 rounded-lg bg-amber-50 border border-amber-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                        <img
                                            v-if="product.image_url"
                                            :src="product.image_url"
                                            :alt="product.name"
                                            class="w-full h-full object-cover"
                                            @error="$event.target.style.display='none'"
                                        />
                                        <UtensilsCrossed class="w-7 h-7 text-amber-700" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1 mb-1">
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-900 truncate">
                                                {{ product.category?.name || 'Umum' }}
                                            </span>
                                            <span
                                                class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                :class="product.is_available ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700'"
                                            >
                                                {{ product.is_available ? 'Tersedia' : 'Habis' }}
                                            </span>
                                        </div>
                                        <h4 class="font-bold text-sm text-slate-800 truncate" :title="product.name">
                                            {{ product.name }}
                                        </h4>
                                        <p class="text-sm font-extrabold text-amber-900 mt-0.5">
                                            {{ formatRupiah(product.price) }}
                                        </p>
                                    </div>
                                </div>

                                <p v-if="product.description" class="text-xs text-slate-500 line-clamp-2 mt-2.5">
                                    {{ product.description }}
                                </p>

                                <div v-if="product.taste_profile" class="mt-2 text-[11px] text-amber-800 font-medium bg-amber-50/70 px-2 py-1 rounded">
                                    ✨ {{ product.taste_profile }}
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                                <button
                                    @click="toggleAvailability(product)"
                                    class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-all flex items-center gap-1"
                                    :class="product.is_available ? 'border-amber-200 text-amber-800 hover:bg-amber-100' : 'border-emerald-200 text-emerald-800 hover:bg-emerald-100'"
                                >
                                    <EyeOff v-if="product.is_available" class="w-3.5 h-3.5" />
                                    <Eye v-else class="w-3.5 h-3.5" />
                                    <span>{{ product.is_available ? 'Set Habis' : 'Set Tersedia' }}</span>
                                </button>

                                <div class="flex items-center gap-1.5">
                                    <button
                                        @click="openEditProduct(product)"
                                        title="Edit Menu"
                                        class="p-1.5 text-slate-600 hover:text-amber-800 hover:bg-slate-200 rounded-lg transition-all"
                                    >
                                        <Edit3 class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="deleteProduct(product)"
                                        title="Hapus Menu"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: CATEGORY MANAGEMENT -->
                <div v-if="activeTab === 'categories'" class="max-w-2xl mx-auto space-y-6">
                    <!-- Form Tambah Kategori -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <h3 class="font-bold text-slate-800 text-sm mb-1 flex items-center gap-2">
                            <FolderPlus class="w-4 h-4 text-amber-700" />
                            Tambah Kategori Baru
                        </h3>
                        <p class="text-xs text-slate-500 mb-4">
                            Kategori digunakan untuk mengelompokkan menu pada layar Kiosk Pelanggan (contoh: Varian Soto, Minuman, Lauk Tambahan).
                        </p>

                        <form @submit.prevent="submitCategory" class="flex gap-2">
                            <input
                                v-model="newCategoryName"
                                type="text"
                                placeholder="Nama Kategori (Contoh: Varian Soto)"
                                class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20"
                                required
                            />
                            <button
                                type="submit"
                                :disabled="isSubmittingCategory"
                                class="px-5 py-2.5 bg-amber-800 hover:bg-amber-900 text-white rounded-xl text-sm font-bold shadow-sm transition-all flex items-center gap-1.5 disabled:opacity-50 whitespace-nowrap"
                            >
                                <Plus class="w-4 h-4" />
                                {{ isSubmittingCategory ? 'Menyimpan...' : 'Simpan Kategori' }}
                            </button>
                        </form>
                    </div>

                    <!-- Daftar Kategori yang ada -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                                <Layers class="w-4 h-4 text-amber-700" />
                                Kategori Terdaftar ({{ categories.length }})
                            </h3>
                            <span class="text-xs text-slate-400">Total Kategori</span>
                        </div>

                        <div v-if="categories.length === 0" class="text-center py-8 text-slate-400 text-xs">
                            Belum ada kategori yang ditambahkan. Buat kategori pertama di atas!
                        </div>

                        <div v-else class="divide-y divide-slate-100">
                            <div
                                v-for="cat in categories"
                                :key="cat.id"
                                class="py-3 flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-sm text-slate-800">{{ cat.name }}</h4>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 font-mono">
                                            slug: {{ cat.slug }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ cat.products_count || 0 }} menu terdaftar dalam kategori ini
                                    </p>
                                </div>

                                <button
                                    @click="deleteCategory(cat)"
                                    class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all"
                                    title="Hapus Kategori"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-white border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                <span class="flex items-center gap-1.5 font-medium">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Database: <span class="font-bold text-slate-700">MySQL (sotoparsoey_db)</span>
                </span>
                <button
                    @click="emit('close')"
                    class="px-4 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-all"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</template>
