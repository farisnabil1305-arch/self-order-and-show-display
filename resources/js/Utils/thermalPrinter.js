/**
 * Thermal Printer Service for POS Cashier and Kiosk
 * Supports:
 * 1. Web Bluetooth API (Direct wireless ESC/POS to portable/desktop Bluetooth printers)
 * 2. Web Serial API (Direct USB / COM port ESC/POS stream)
 * 3. System / Browser Print (Universal fallback for all Windows USB/LAN printer drivers)
 */

import { formatRupiah, formatDateTime } from '@/Utils/formatters';

// Common Bluetooth Thermal Printer Service & Characteristic UUIDs
const BT_SERVICES = [
    '000018f0-0000-1000-8000-00805f9b34fb', // Standard POS Printer Service
    '0000ffe0-0000-1000-8000-00805f9b34fb', // Common Chinese/Indonesian BT Printer (RPP02N, Panda, Eppos, Iware)
    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC Transparent
    'e7810a71-73ae-499d-8c15-fa9aae00c3f2',
];

const BT_CHARACTERISTICS = [
    '00002af1-0000-1000-8000-00805f9b34fb',
    '0000ffe1-0000-1000-8000-00805f9b34fb',
    '49535343-8841-43f4-a8d4-ecbe34729bb3',
    'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f',
];

class ThermalPrinterService {
    constructor() {
        this.bluetoothDevice = null;
        this.bluetoothCharacteristic = null;
        this.serialPort = null;
        this.serialWriter = null;
    }

    /**
     * Check browser capabilities
     */
    isBluetoothSupported() {
        return typeof navigator !== 'undefined' && 'bluetooth' in navigator;
    }

    isSerialSupported() {
        return typeof navigator !== 'undefined' && 'serial' in navigator;
    }

    /**
     * Load settings from localStorage
     * @param {string} profileKey - 'pos_printer_settings' or 'kiosk_printer_settings'
     */
    loadSettings(profileKey = 'pos_printer_settings') {
        const defaults = {
            mode: 'system', // 'system' | 'bluetooth' | 'serial'
            paperSize: '58mm', // '58mm' | '80mm'
            autoPrint: true,
            printKitchen: true,
            deviceName: '',
            autoCut: true,
        };

        try {
            const saved = localStorage.getItem(profileKey);
            return saved ? { ...defaults, ...JSON.parse(saved) } : defaults;
        } catch {
            return defaults;
        }
    }

    /**
     * Save settings to localStorage
     */
    saveSettings(profileKey, settings) {
        try {
            localStorage.setItem(profileKey, JSON.stringify(settings));
        } catch (e) {
            console.error('Failed to save printer settings:', e);
        }
    }

    /**
     * Connect to Web Bluetooth Printer
     */
    async connectBluetooth() {
        if (!this.isBluetoothSupported()) {
            throw new Error('Web Bluetooth tidak didukung pada browser ini. Gunakan Google Chrome atau Edge.');
        }

        // Request Bluetooth device with common thermal printer filters
        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: BT_SERVICES,
        });

        if (!device) throw new Error('Tidak ada perangkat yang dipilih.');

        const server = await device.gatt.connect();

        // Find primary service & write characteristic
        let foundChar = null;
        for (const serviceUuid of BT_SERVICES) {
            try {
                const service = await server.getPrimaryService(serviceUuid);
                const characteristics = await service.getCharacteristics();
                for (const char of characteristics) {
                    if (char.properties.write || char.properties.writeWithoutResponse) {
                        foundChar = char;
                        break;
                    }
                }
                if (foundChar) break;
            } catch {
                // Try next service
            }
        }

        // Fallback: search all available services if known ones failed
        if (!foundChar) {
            try {
                const services = await server.getPrimaryServices();
                for (const s of services) {
                    const chars = await s.getCharacteristics();
                    for (const c of chars) {
                        if (c.properties.write || c.properties.writeWithoutResponse) {
                            foundChar = c;
                            break;
                        }
                    }
                    if (foundChar) break;
                }
            } catch {
                // Ignore
            }
        }

        if (!foundChar) {
            throw new Error('Gagal menemukan jalur pengiriman data (Write Characteristic) pada printer Bluetooth.');
        }

        this.bluetoothDevice = device;
        this.bluetoothCharacteristic = foundChar;

        device.addEventListener('gattserverdisconnected', () => {
            this.bluetoothDevice = null;
            this.bluetoothCharacteristic = null;
        });

        return {
            name: device.name || 'Thermal Bluetooth Printer',
            id: device.id,
        };
    }

    /**
     * Disconnect Bluetooth
     */
    disconnectBluetooth() {
        if (this.bluetoothDevice && this.bluetoothDevice.gatt.connected) {
            this.bluetoothDevice.gatt.disconnect();
        }
        this.bluetoothDevice = null;
        this.bluetoothCharacteristic = null;
    }

    isBluetoothConnected() {
        return Boolean(this.bluetoothDevice && this.bluetoothDevice.gatt && this.bluetoothDevice.gatt.connected);
    }

    /**
     * Connect to Web Serial / USB Port
     */
    async connectSerial(baudRate = 9600) {
        if (!this.isSerialSupported()) {
            throw new Error('Web Serial tidak didukung pada browser ini. Gunakan Google Chrome atau Edge versi Desktop.');
        }

        const port = await navigator.serial.requestPort();
        await port.open({ baudRate });

        this.serialPort = port;
        this.serialWriter = port.writable.getWriter();

        return {
            name: 'Printer USB/Serial Port',
        };
    }

    disconnectSerial() {
        try {
            if (this.serialWriter) {
                this.serialWriter.releaseLock();
                this.serialWriter = null;
            }
            if (this.serialPort) {
                this.serialPort.close();
                this.serialPort = null;
            }
        } catch (e) {
            console.error('Serial close error:', e);
        }
    }

    isSerialConnected() {
        return Boolean(this.serialPort && this.serialWriter);
    }

    /**
     * Send raw ESC/POS bytes in chunks (safe for Bluetooth BLE packet limits)
     */
    async sendBytes(bytes) {
        if (this.isBluetoothConnected() && this.bluetoothCharacteristic) {
            const chunkSize = 100; // BLE characteristic max safe payload
            for (let i = 0; i < bytes.length; i += chunkSize) {
                const chunk = bytes.slice(i, i + chunkSize);
                if (this.bluetoothCharacteristic.writeValueWithoutResponse) {
                    await this.bluetoothCharacteristic.writeValueWithoutResponse(chunk);
                } else {
                    await this.bluetoothCharacteristic.writeValue(chunk);
                }
                // Small delay to prevent buffer overflow on low-cost thermal microcontrollers
                await new Promise((r) => setTimeout(r, 20));
            }
            return true;
        }

        if (this.isSerialConnected() && this.serialWriter) {
            await this.serialWriter.write(bytes);
            return true;
        }

        return false;
    }

    /**
     * Build ESC/POS command array for Cashier Receipt
     */
    buildReceiptEscPos(order, paperSize = '58mm', storeSettings = {}) {
        const encoder = new TextEncoder();
        const maxChars = paperSize === '80mm' ? 48 : 32;
        const separator = '-'.repeat(maxChars) + '\n';

        const chunks = [];
        const addBytes = (arr) => chunks.push(new Uint8Array(arr));
        const addText = (str) => chunks.push(encoder.encode(str));

        // Format two columns (left title, right value)
        const formatRow = (left, right) => {
            const availableSpace = maxChars - right.length;
            if (left.length > availableSpace) {
                left = left.substring(0, availableSpace - 1) + ' ';
            }
            const spaces = Math.max(1, maxChars - left.length - right.length);
            return left + ' '.repeat(spaces) + right + '\n';
        };

        // ESC/POS Commands
        const CMD_INIT = [0x1B, 0x40];
        const CMD_ALIGN_CENTER = [0x1B, 0x61, 0x01];
        const CMD_ALIGN_LEFT = [0x1B, 0x61, 0x00];
        const CMD_BOLD_ON = [0x1B, 0x45, 0x01];
        const CMD_BOLD_OFF = [0x1B, 0x45, 0x00];
        const CMD_DOUBLE_SIZE = [0x1D, 0x21, 0x11];
        const CMD_DOUBLE_HEIGHT = [0x1D, 0x21, 0x01];
        const CMD_NORMAL_SIZE = [0x1D, 0x21, 0x00];
        const CMD_FEED_CUT = [0x1D, 0x56, 0x41, 0x10]; // Feed and partial cut

        const storeName = (storeSettings.store_name || 'SOTO BETAWI PARSOEY').toUpperCase();
        const storeTagline = storeSettings.store_tagline || 'Cita Rasa Gurih Kuah Santan & Susu Rempah Asli Betawi';
        const storeAddress = storeSettings.store_address || 'Jl. Raya Betawi No. 88, Jakarta Selatan';
        const storePhone = storeSettings.store_phone || '0812-9876-5432';

        // 1. Initialize
        addBytes(CMD_INIT);

        // 2. Header
        addBytes(CMD_ALIGN_CENTER);
        addBytes(CMD_BOLD_ON);
        addText(`${storeName}\n`);
        addBytes(CMD_BOLD_OFF);
        if (storeTagline) addText(`${storeTagline}\n`);
        if (storeAddress) addText(`${storeAddress}\n`);
        if (storePhone) addText(`Telp: ${storePhone}\n`);
        addText(separator);

        // 3. Queue Number
        addText('NOMOR ANTREAN\n');
        addBytes(CMD_DOUBLE_SIZE);
        addBytes(CMD_BOLD_ON);
        addText(`${order.order_number}\n`);
        addBytes(CMD_NORMAL_SIZE);
        addBytes(CMD_BOLD_OFF);

        const typeLabel = order.order_type === 'dine_in'
            ? `[ MAKAN DI TEMPAT - MEJA ${order.table_number || '-'} ]`
            : '[ BAWA PULANG / TAKEAWAY ]';
        addText(`${typeLabel}\n`);
        addText(separator);

        // 4. Order Meta
        addBytes(CMD_ALIGN_LEFT);
        addText(formatRow('Waktu:', formatDateTime(order.created_at || new Date())));
        addText(formatRow('Pelanggan:', order.customer_name || '-'));
        addText(formatRow('Status:', order.status === 'paid' ? 'LUNAS' : 'BELUM BAYAR'));
        if (order.payment_method) {
            addText(formatRow('Metode Bayar:', order.payment_method.toUpperCase()));
        }
        addText(separator);

        // 5. Items
        if (Array.isArray(order.items)) {
            for (const item of order.items) {
                const productName = item.product?.name || item.name || 'Menu';
                const qtyPrice = `${item.quantity}x @${formatRupiah(item.unit_price)}`;
                const itemTotal = formatRupiah(item.subtotal || item.unit_price * item.quantity);

                addBytes(CMD_BOLD_ON);
                addText(`${productName}\n`);
                addBytes(CMD_BOLD_OFF);
                addText(formatRow(`  ${qtyPrice}`, itemTotal));

                if (item.notes) {
                    addText(`  * Catatan: ${item.notes}\n`);
                }
            }
        }
        addText(separator);

        // 6. Totals
        addText(formatRow('Subtotal:', formatRupiah(order.subtotal || order.total_amount)));
        if (order.tax && order.tax > 0) {
            addText(formatRow('Pajak (PB1):', formatRupiah(order.tax)));
        }
        addBytes(CMD_BOLD_ON);
        addText(formatRow('TOTAL BAYAR:', formatRupiah(order.total_amount)));
        addBytes(CMD_BOLD_OFF);

        if (order.payment_method === 'cash' && order.amount_paid) {
            addText(formatRow('Tunai Diterima:', formatRupiah(order.amount_paid)));
            addText(formatRow('Kembalian:', formatRupiah(order.change_amount || 0)));
        }
        addText(separator);

        // 7. Footer
        addBytes(CMD_ALIGN_CENTER);
        if (storeSettings.receipt_footer) {
            addText(`${storeSettings.receipt_footer}\n`);
        } else {
            addText('Terima Kasih atas Kunjungan Anda!\n');
            addText('Selamat Menikmati Hidangan Kami\n');
        }

        if (storeSettings.wifi_ssid || storeSettings.wifi_password) {
            addText('\n');
            addBytes(CMD_BOLD_ON);
            addText('*** FREE WIFI RESTORAN ***\n');
            addBytes(CMD_BOLD_OFF);
            if (storeSettings.wifi_ssid) addText(`SSID: ${storeSettings.wifi_ssid}\n`);
            if (storeSettings.wifi_password) addText(`Pass: ${storeSettings.wifi_password}\n`);
        }

        if (storeSettings.store_instagram) {
            addText(`IG: ${storeSettings.store_instagram}\n`);
        }

        addText('\n\n\n\n'); // Feed lines before cut
        addBytes(CMD_FEED_CUT);

        return this.combineChunks(chunks);
    }

    /**
     * Build ESC/POS command array for Kiosk Queue Ticket
     */
    buildKioskTicketEscPos(order, paperSize = '58mm', storeSettings = {}) {
        const encoder = new TextEncoder();
        const maxChars = paperSize === '80mm' ? 48 : 32;
        const separator = '-'.repeat(maxChars) + '\n';

        const chunks = [];
        const addBytes = (arr) => chunks.push(new Uint8Array(arr));
        const addText = (str) => chunks.push(encoder.encode(str));

        const formatRow = (left, right) => {
            const availableSpace = maxChars - right.length;
            if (left.length > availableSpace) {
                left = left.substring(0, availableSpace - 1) + ' ';
            }
            const spaces = Math.max(1, maxChars - left.length - right.length);
            return left + ' '.repeat(spaces) + right + '\n';
        };

        const CMD_INIT = [0x1B, 0x40];
        const CMD_ALIGN_CENTER = [0x1B, 0x61, 0x01];
        const CMD_ALIGN_LEFT = [0x1B, 0x61, 0x00];
        const CMD_BOLD_ON = [0x1B, 0x45, 0x01];
        const CMD_BOLD_OFF = [0x1B, 0x45, 0x00];
        const CMD_DOUBLE_SIZE = [0x1D, 0x21, 0x11];
        const CMD_NORMAL_SIZE = [0x1D, 0x21, 0x00];
        const CMD_FEED_CUT = [0x1D, 0x56, 0x41, 0x10];

        addBytes(CMD_INIT);
        addBytes(CMD_ALIGN_CENTER);
        addBytes(CMD_BOLD_ON);
        addText('SOTO BETAWI PARSOEY\n');
        addBytes(CMD_BOLD_OFF);
        addText('Self-Order Kiosk Mandiri\n');
        addText(separator);

        addText('KARCIS ANTREAN PEMESANAN\n');
        addText(separator);

        addText('NOMOR ANTREAN ANDA:\n');
        addBytes(CMD_DOUBLE_SIZE);
        addBytes(CMD_BOLD_ON);
        addText(`${order.order_number}\n`);
        addBytes(CMD_NORMAL_SIZE);
        addBytes(CMD_BOLD_OFF);

        const typeLabel = order.order_type === 'dine_in'
            ? `[ MAKAN DI TEMPAT - MEJA ${order.table_number || '-'} ]`
            : '[ BAWA PULANG / TAKEAWAY ]';
        addText(`${typeLabel}\n`);
        addText(separator);

        addBytes(CMD_ALIGN_LEFT);
        addText(formatRow('Waktu Pesan:', formatDateTime(order.created_at || new Date())));
        addText(formatRow('Nama Pemesan:', order.customer_name || '-'));
        addText(separator);

        // List item brief
        if (Array.isArray(order.items)) {
            addBytes(CMD_BOLD_ON);
            addText('Rincian Pesanan:\n');
            addBytes(CMD_BOLD_OFF);
            for (const item of order.items) {
                const name = item.product?.name || item.name || 'Menu';
                addText(`${item.quantity}x ${name}\n`);
            }
            addText(separator);
        }

        addBytes(CMD_BOLD_ON);
        addText(formatRow('Total Pembayaran:', formatRupiah(order.total_amount)));
        addBytes(CMD_BOLD_OFF);
        addText(separator);

        addBytes(CMD_ALIGN_CENTER);
        addText('Silakan bawa karcis ini ke kasir\n');
        addText('untuk konfirmasi & pembayaran.\n');
        if (storeSettings.wifi_ssid || storeSettings.wifi_password) {
            addText('\n');
            addBytes(CMD_BOLD_ON);
            addText('*** FREE WIFI RESTORAN ***\n');
            addBytes(CMD_BOLD_OFF);
            if (storeSettings.wifi_ssid) addText(`SSID: ${storeSettings.wifi_ssid}\n`);
            if (storeSettings.wifi_password) addText(`Pass: ${storeSettings.wifi_password}\n`);
        }
        addText('\n\n\n\n');
        addBytes(CMD_FEED_CUT);

        return this.combineChunks(chunks);
    }

    /**
     * Build ESC/POS command array for Kitchen Checker Slip (Tiket Dapur / Koki)
     * No prices, focused strictly on items, quantities, and preparation notes.
     */
    buildKitchenTicketEscPos(order, paperSize = '58mm') {
        const encoder = new TextEncoder();
        const maxChars = paperSize === '80mm' ? 48 : 32;
        const separator = '-'.repeat(maxChars) + '\n';

        const chunks = [];
        const addBytes = (arr) => chunks.push(new Uint8Array(arr));
        const addText = (str) => chunks.push(encoder.encode(str));

        const CMD_INIT = [0x1B, 0x40];
        const CMD_ALIGN_CENTER = [0x1B, 0x61, 0x01];
        const CMD_ALIGN_LEFT = [0x1B, 0x61, 0x00];
        const CMD_BOLD_ON = [0x1B, 0x45, 0x01];
        const CMD_BOLD_OFF = [0x1B, 0x45, 0x00];
        const CMD_DOUBLE_SIZE = [0x1D, 0x21, 0x11];
        const CMD_NORMAL_SIZE = [0x1D, 0x21, 0x00];
        const CMD_FEED_CUT = [0x1D, 0x56, 0x41, 0x10];

        // 1. Init
        addBytes(CMD_INIT);

        // 2. Header
        addBytes(CMD_ALIGN_CENTER);
        addBytes(CMD_BOLD_ON);
        addText('*** TIKET DAPUR / KOKI ***\n');
        addText('SOTO BETAWI PARSOEY\n');
        addBytes(CMD_BOLD_OFF);
        addText(separator);

        // 3. Queue & Table
        addText('ANTREAN: ');
        addBytes(CMD_DOUBLE_SIZE);
        addBytes(CMD_BOLD_ON);
        addText(`${order.order_number}\n`);
        addBytes(CMD_NORMAL_SIZE);

        const typeLabel = order.order_type === 'dine_in'
            ? `MEJA: ${order.table_number || '-'}`
            : '>> BAWA PULANG <<';
        addText(`[ ${typeLabel} ]\n`);
        addBytes(CMD_BOLD_OFF);
        addText(separator);

        // 4. Meta
        addBytes(CMD_ALIGN_LEFT);
        addText(`Pemesan: ${order.customer_name || '-'}\n`);
        addText(`Waktu: ${formatDateTime(order.created_at || new Date())}\n`);
        addText(separator);

        // 5. Items
        addBytes(CMD_BOLD_ON);
        addText('-- DAFTAR RACIKAN MENU --\n');
        addBytes(CMD_BOLD_OFF);

        let totalPortions = 0;
        if (Array.isArray(order.items)) {
            for (const item of order.items) {
                const qty = item.quantity || 1;
                totalPortions += Number(qty);
                const name = (item.product?.name || item.name || 'Menu').toUpperCase();

                addBytes(CMD_BOLD_ON);
                addText(`${qty}x ${name}\n`);
                addBytes(CMD_BOLD_OFF);

                if (item.notes) {
                    addText(`  * CATATAN: ${item.notes.toUpperCase()}\n`);
                }
            }
        }
        addText(separator);

        // 6. Total items
        addBytes(CMD_BOLD_ON);
        addText(`TOTAL: ${totalPortions} Porsi\n`);
        addBytes(CMD_BOLD_OFF);
        addText(separator);

        addBytes(CMD_ALIGN_CENTER);
        addText('Segera racik & sajikan selagi hangat\n');
        addText('\n\n\n\n');
        addBytes(CMD_FEED_CUT);

        return this.combineChunks(chunks);
    }

    /**
     * Build ESC/POS Test Slip
     */
    buildTestSlipEscPos(profileName = 'POS Kasir', paperSize = '58mm') {
        const encoder = new TextEncoder();
        const maxChars = paperSize === '80mm' ? 48 : 32;
        const separator = '-'.repeat(maxChars) + '\n';

        const chunks = [];
        const addBytes = (arr) => chunks.push(new Uint8Array(arr));
        const addText = (str) => chunks.push(encoder.encode(str));

        addBytes([0x1B, 0x40]); // Init
        addBytes([0x1B, 0x61, 0x01]); // Center
        addBytes([0x1B, 0x45, 0x01]); // Bold on
        addText('SOTO BETAWI PARSOEY\n');
        addText('*** TEST PRINT THERMAL ***\n');
        addBytes([0x1B, 0x45, 0x00]); // Bold off
        addText(separator);

        addText(`Profil: ${profileName}\n`);
        addText(`Ukuran Kertas: ${paperSize}\n`);
        addText(`Waktu Test: ${formatDateTime(new Date())}\n`);
        addText(separator);

        addText('Koneksi Printer Berhasil!\n');
        addText('Printer siap digunakan untuk mencetak struk & karcis antrean.\n');
        addText(separator);
        addText('\n\n\n\n');
        addBytes([0x1D, 0x56, 0x41, 0x10]);

        return this.combineChunks(chunks);
    }

    combineChunks(chunks) {
        let totalLen = 0;
        for (const c of chunks) totalLen += c.length;
        const result = new Uint8Array(totalLen);
        let offset = 0;
        for (const c of chunks) {
            result.set(c, offset);
            offset += c.length;
        }
        return result;
    }
}

export const thermalPrinter = new ThermalPrinterService();
export default thermalPrinter;
