/**
 * Format number to Indonesian Rupiah currency string.
 * Example: 25000 -> "Rp 25.000"
 */
export function formatRupiah(amount) {
    if (amount === null || amount === undefined || isNaN(amount)) {
        return 'Rp 0';
    }
    return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

/**
 * Format datetime string into Indonesian locale.
 * Example: "1 Nov 2026, 12:30"
 */
export function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Format relative time (e.g., "Baru saja", "3 menit lalu").
 */
export function formatRelativeTime(dateString) {
    if (!dateString) return '';
    const now = new Date();
    const past = new Date(dateString);
    const diffInSeconds = Math.floor((now - past) / 1000);

    if (diffInSeconds < 30) return 'Baru saja';
    if (diffInSeconds < 60) return `${diffInSeconds} dtk lalu`;

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) return `${diffInMinutes} mnt lalu`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours} jam lalu`;

    return formatDateTime(dateString);
}
