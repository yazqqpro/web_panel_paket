<?php
// function.php - Berisi fungsi-fungsi helper untuk aplikasi data paket

/**
 * Format angka menjadi format Rupiah.
 *
 * @param float|int $angka Angka yang akan diformat.
 * @return string String dalam format Rupiah (contoh: Rp 10.000).
 */
function formatRupiah($angka) {
    // Pastikan input adalah angka sebelum format
    if (!is_numeric($angka)) {
        return 'Rp 0';
    }
    // Gunakan number_format dengan locale Indonesia
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Format tanggal menjadi format Indonesia.
 *
 * @param string $tanggal Tanggal dalam format string yang dapat diparse oleh strtotime.
 * @return string Tanggal dalam format Indonesia (contoh: Senin, 01 Januari 2023).
 */
function formatTanggal($tanggal) {
    // Handle case where tanggal might be null or empty
    if (empty($tanggal)) {
        return '';
    }
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $hari = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];
    $date = strtotime($tanggal);
    // Pastikan tanggal valid sebelum memproses
    if ($date === false) {
        return $tanggal; // Kembalikan tanggal asli jika tidak valid
    }
    $day = date('l', $date);
    $dateNum = date('j', $date);
    $month = date('n', $date);
    $year = date('Y', $date);
    return $hari[$day] . ', ' . $dateNum . ' ' . $bulan[$month] . ' ' . $year;
}

/**
 * Menghitung sisa hari masa aktif dari tanggal awal dan menampilkan status.
 * Masa aktif dihitung 30 hari termasuk tanggal awal.
 *
 * @param string $tanggal Tanggal awal masa aktif dalam format string yang dapat diparse oleh DateTime.
 * @return string HTML span dengan sisa hari dan status (Active, Segera Expired, Expired).
 */
function hitunganMundur($tanggal) {
    // Handle case where tanggal might be null or empty
    if (empty($tanggal)) {
        return '';
    }
    $dateNow = new DateTime();
    $dateTarget = new DateTime($tanggal);
    // Menambahkan 29 hari untuk masa aktif (total 30 hari termasuk tanggal awal)
    // Clone dateTarget to avoid modifying the original object used elsewhere
    $dateTargetEnd = clone $dateTarget;
    $dateTargetEnd->modify('+29 days');

    $interval = $dateNow->diff($dateTargetEnd);
    $daysLeft = (int)$interval->format('%r%a'); // Mengambil selisih hari sebagai integer

    if ($daysLeft < 0) {
        return '<span class="text-red-500 font-semibold">Expired</span>';
    } elseif ($daysLeft <= 7) {
         return '<span class="text-orange-500 font-semibold">' . $daysLeft . ' hari</span> [<span class="text-yellow-500 font-semibold">Segera Expired</span>]';
    } else {
        return '<span class="text-green-600 font-semibold">' . $daysLeft . ' hari</span> [<span class="text-green-500 font-semibold">Active</span>]';
    }
}

?>
