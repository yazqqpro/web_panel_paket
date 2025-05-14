<?php
// admin_dashboard_content.php

// Diasumsikan fungsi load_settings() sudah tersedia secara global dari admin_panel.php (via helpers.php)
if (!function_exists('load_settings')) {
    // Fallback darurat jika load_settings tidak ada (ini seharusnya tidak terjadi jika struktur benar)
    if (!defined('CONFIG_FILE_PATH_DASHBOARD')) {
        // Sesuaikan path ini jika struktur direktori Anda berbeda
        // Misalnya, jika admin_dashboard_content.php ada di root dan config.php di /input/
        define('CONFIG_FILE_PATH_DASHBOARD', __DIR__ . '/input/config.php'); 
    }
    if (!function_exists('get_default_settings_dashboard')) {
        function get_default_settings_dashboard() {
            return [
                'admin_pin' => '0000', 'github_api_token' => '', 'github_repo_owner' => '', 
                'github_repo_name' => '', 'github_branch' => 'main', 
                'github_data_file' => 'data.json', 'github_pelanggan_file' => 'pelanggan.json', 
                'whatsapp_api_url' => '',
            ];
        }
    }
    function load_settings() {
        if (file_exists(CONFIG_FILE_PATH_DASHBOARD)) {
            $settings_include = include CONFIG_FILE_PATH_DASHBOARD;
            if (is_array($settings_include)) {
                return array_merge(get_default_settings_dashboard(), $settings_include);
            }
        }
        return get_default_settings_dashboard();
    }
}

// Fungsi helper untuk format angka jika belum ada global
if (!function_exists('formatAngka')) {
    function formatAngka($angka, $desimal = 0) {
        return number_format($angka, $desimal, ',', '.');
    }
}
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

$settings = load_settings();
$all_transactions = [];
$dashboard_error_message = '';

// Ambil data transaksi dari GitHub
if (empty($settings['github_api_token']) || empty($settings['github_repo_owner']) || empty($settings['github_repo_name']) || empty($settings['github_data_file'])) {
    $dashboard_error_message = "Konfigurasi GitHub API tidak lengkap untuk mengambil data transaksi. Silakan periksa Pengaturan.";
} else {
    $api_token = $settings['github_api_token'];
    $repo_owner = $settings['github_repo_owner'];
    $repo_name = $settings['github_repo_name'];
    $file_path = $settings['github_data_file'];
    $branch = $settings['github_branch'];
    $github_api_url = "https://api.github.com/repos/$repo_owner/$repo_name/contents/$file_path?ref=$branch";

    $ch = curl_init($github_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-YazpayDashboard'); // User agent yang jelas
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $api_token",
        "Accept: application/vnd.github.v3+json"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        $dashboard_error_message = "Gagal menghubungi GitHub API (cURL Error): " . htmlspecialchars($curl_error);
        error_log("Dashboard cURL Error fetching $file_path: " . $curl_error);
    } elseif ($http_status === 200) {
        $response_data = json_decode($response, true);
        if (isset($response_data['content']) && $response_data['content'] !== null) {
            $json_data = base64_decode($response_data['content']);
            $decoded_json = json_decode($json_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_json)) {
                $all_transactions = $decoded_json;
            } else {
                $dashboard_error_message = "Format data transaksi dari GitHub tidak valid (JSON error).";
                error_log("Dashboard JSON Decode Error for $file_path: " . json_last_error_msg());
            }
        } else {
            $all_transactions = []; // Anggap data kosong jika konten null
            // error_log("Dashboard: Konten data transaksi kosong atau tidak ada dari GitHub. Path: $file_path");
        }
    } elseif ($http_status === 404) {
        $all_transactions = []; // File tidak ditemukan, anggap data kosong
        // error_log("Dashboard: File data transaksi tidak ditemukan di GitHub (404). Path: $file_path");
    } else {
        $dashboard_error_message = "Gagal memuat data transaksi dari GitHub (Status: $http_status). Periksa token dan path file.";
        error_log("Dashboard GitHub API Error: Status $http_status for $file_path, Response: " . substr($response, 0, 200));
    }
}

// Hitung statistik dari $all_transactions
$jumlah_transaksi_hari_ini = 0;
$total_pendapatan_bulan_ini = 0;
$total_modal_hari_ini = 0;

$default_timezone = 'Asia/Jakarta'; // Zona waktu default
if (isset($settings['timezone']) && !empty($settings['timezone'])) { // Jika ada setting timezone di config
    $default_timezone = $settings['timezone'];
}
date_default_timezone_set($default_timezone);

$tanggal_hari_ini_obj = new DateTime('now'); // Menggunakan zona waktu yang sudah di-set
$tanggal_hari_ini = $tanggal_hari_ini_obj->format('Y-m-d');
$bulan_ini_tahun = $tanggal_hari_ini_obj->format('Y-m');

$paket_counts = [];

if (is_array($all_transactions)) {
    foreach ($all_transactions as $trx) {
        if (isset($trx['tanggal'])) {
            if ($trx['tanggal'] === $tanggal_hari_ini) {
                $jumlah_transaksi_hari_ini++;
                $total_modal_hari_ini += (float)($trx['harga_modal'] ?? 0);
            }
            if (strpos($trx['tanggal'], $bulan_ini_tahun) === 0) {
                $total_pendapatan_bulan_ini += (float)($trx['harga_jual'] ?? 0);
            }
        }
        if (!empty($trx['jenis_paket'])) {
            $jenis_paket = trim($trx['jenis_paket']);
            $paket_counts[$jenis_paket] = ($paket_counts[$jenis_paket] ?? 0) + 1;
        }
    }
}

$top_paket_populer = [];
if (!empty($paket_counts)) {
    arsort($paket_counts);
    $top_paket_populer = array_slice($paket_counts, 0, 4, true);
}

$nama_admin = $settings['admin_name'] ?? "Admin YazPay"; // Ambil nama admin dari settings jika ada
$jam = (int)date('G');
$salam_waktu = "Selamat Datang";
if ($jam >= 5 && $jam < 12) { $salam_waktu = "Selamat Pagi"; }
elseif ($jam >= 12 && $jam < 15) { $salam_waktu = "Selamat Siang"; }
elseif ($jam >= 15 && $jam < 18) { $salam_waktu = "Selamat Sore"; }
else { $salam_waktu = "Selamat Malam"; }
$tanggal_sekarang = date('l, d F Y');

$quick_links = [
    ['url' => 'input/input.php', 'icon' => 'fas fa-cart-plus', 'text' => 'Tambah Transaksi Baru', 'color' => 'bg-primary hover:bg-primary-dark'],
    ['url' => 'display/display.php', 'icon' => 'fas fa-list-alt', 'text' => 'Lihat Data Transaksi', 'color' => 'bg-blue-500 hover:bg-blue-600'],
    ['url' => 'add_pelanggan.php', 'icon' => 'fas fa-user-plus', 'text' => 'Tambah Pelanggan', 'color' => 'bg-green-500 hover:bg-green-600'],
    ['url' => 'settings_general.php', 'icon' => 'fas fa-cogs', 'text' => 'Pengaturan Umum', 'color' => 'bg-slate-500 hover:bg-slate-600'],
];

$aktivitas_terkini = [];
if (is_array($all_transactions) && count($all_transactions) > 0) {
    $sorted_transactions_for_log = $all_transactions;
    // Urutkan berdasarkan nomor jika ada, untuk mendapatkan yang terbaru (asumsi nomor increment)
    if (isset($sorted_transactions_for_log[0]['nomor'])) {
        usort($sorted_transactions_for_log, function($a, $b) { return ($b['nomor'] ?? 0) <=> ($a['nomor'] ?? 0); });
    } elseif (isset($sorted_transactions_for_log[0]['tanggal'])) { // Fallback ke tanggal jika tidak ada nomor
         usort($sorted_transactions_for_log, function($a, $b) { return strtotime($b['tanggal'] ?? 0) <=> strtotime($a['tanggal'] ?? 0); });
    }
    
    $transaksi_terbaru_untuk_log = array_slice($sorted_transactions_for_log, 0, 3); // Ambil 3 terbaru
    foreach ($transaksi_terbaru_untuk_log as $log_trx) {
        $aktivitas_terkini[] = [
            'icon' => 'fas fa-receipt text-green-500',
            'text' => 'Transaksi #' . htmlspecialchars($log_trx['nomor'] ?? 'N/A') . ' - ' . htmlspecialchars($log_trx['jenis_paket'] ?? 'N/A') . ' oleh ' . htmlspecialchars($log_trx['nama_pembeli'] ?? 'N/A'),
            'waktu' => isset($log_trx['tanggal']) ? date("d M Y, H:i", strtotime($log_trx['tanggal'])) : 'Baru-baru ini' // Tambahkan waktu
        ];
    }
}
if(empty($aktivitas_terkini)){
     $aktivitas_terkini[] = ['icon' => 'fas fa-info-circle text-blue-500', 'text' => 'Belum ada transaksi terbaru yang tercatat.', 'waktu' => 'Saat ini'];
}
?>
<div class="p-0 md:p-2">
    <header class="mb-10 p-6 bg-gradient-to-r from-primary to-orange-500 dark:from-gold dark:to-yellow-500 rounded-xl shadow-lg text-white">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold"><?php echo $salam_waktu; ?>, <?php echo htmlspecialchars($nama_admin); ?>!</h1>
                <p class="mt-2 text-lg opacity-90">Selamat datang kembali di pusat kendali YazPay.</p>
            </div>
            <div class="text-right hidden sm:block">
                <p class="text-sm font-semibold"><?php echo $tanggal_sekarang; ?></p>
                <p class="text-xs opacity-80">Panel Versi 1.0.3</p>
            </div>
        </div>
    </header>

    <?php if ($dashboard_error_message): ?>
        <div class="mb-6 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-700 dark:text-red-100" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Error Data Dashboard:</strong> <?php echo htmlspecialchars($dashboard_error_message); ?>
        </div>
    <?php endif; ?>

    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 transform hover:-translate-y-1">
            <div class="flex items-center">
                <div class="p-4 bg-primary/20 text-primary dark:bg-gold/20 dark:text-gold rounded-full mr-5">
                    <i class="fas fa-receipt fa-2x"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 uppercase tracking-wider">Transaksi Hari Ini</p>
                    <p class="text-3xl font-bold text-slate-700 dark:text-slate-200"><?php echo formatAngka($jumlah_transaksi_hari_ini); ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 transform hover:-translate-y-1">
            <div class="flex items-center">
                <div class="p-4 bg-green-500/20 text-green-500 rounded-full mr-5">
                    <i class="fas fa-wallet fa-2x"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Modal Hari Ini</p>
                    <p class="text-3xl font-bold text-slate-700 dark:text-slate-200"><?php echo formatRupiah($total_modal_hari_ini); ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 transform hover:-translate-y-1">
            <div class="flex items-center">
                <div class="p-4 bg-blue-500/20 text-blue-500 rounded-full mr-5">
                     <i class="fas fa-chart-line fa-2x"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pendapatan Bulan Ini</p>
                    <p class="text-3xl font-bold text-slate-700 dark:text-slate-200"><?php echo formatRupiah($total_pendapatan_bulan_ini); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
        <div class="lg:col-span-1 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-semibold text-slate-700 dark:text-white mb-5 border-b dark:border-slate-700 pb-3">Akses Cepat</h2>
            <div class="space-y-3">
                <?php foreach ($quick_links as $link): ?>
                    <a href="<?php echo htmlspecialchars($link['url']); ?>" class="nav-link flex items-center p-3 rounded-lg <?php echo htmlspecialchars($link['color']); ?> text-white transition-transform duration-200 hover:scale-105 group">
                        <i class="<?php echo htmlspecialchars($link['icon']); ?> fa-lg mr-3 group-hover:animate-pulse"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($link['text']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-semibold text-slate-700 dark:text-white mb-5 border-b dark:border-slate-700 pb-3">Aktivitas Transaksi Terkini</h2>
            <ul class="space-y-4 max-h-80 overflow-y-auto pr-2"> <?php if (!empty($aktivitas_terkini)): ?>
                    <?php foreach ($aktivitas_terkini as $aktivitas): ?>
                        <li class="flex items-start p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-600 flex items-center justify-center mr-4">
                                <i class="<?php echo htmlspecialchars($aktivitas['icon']); ?>"></i>
                            </div>
                            <div>
                                <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed"><?php echo $aktivitas['text']; ?></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><?php echo htmlspecialchars($aktivitas['waktu']); ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-center text-slate-500 dark:text-slate-400 py-4">
                        <i class="fas fa-history fa-2x mb-2"></i><br>
                        Belum ada aktivitas terbaru.
                    </li>
                <?php endif; ?>
            </ul>
            <?php if (count($all_transactions) > 3): ?>
            <div class="mt-6 text-right">
                <a href="display/display.php" class="nav-link text-sm font-medium text-primary dark:text-gold hover:underline">Lihat Semua Transaksi &rarr;</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-slate-700 dark:text-white mb-4">Top Paket Populer</h3>
            <?php if (!empty($top_paket_populer)): ?>
                <ul class="space-y-3">
                    <?php $rank = 1; foreach ($top_paket_populer as $paket => $jumlah): ?>
                        <li class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                            <div class="flex items-center">
                                <span class="mr-3 font-semibold text-primary dark:text-gold w-5 text-center"><?php echo $rank++; ?>.</span>
                                <span class="text-sm text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($paket); ?></span>
                            </div>
                            <span class="text-sm font-semibold text-slate-600 dark:text-slate-400"><?php echo formatAngka($jumlah); ?> trx</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="flex items-center p-4 bg-slate-100 dark:bg-slate-700 rounded-lg">
                    <i class="fas fa-box-open fa-2x text-slate-400 mr-4"></i>
                    <p class="text-slate-500 dark:text-slate-400">Belum ada data transaksi paket untuk ditampilkan.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-slate-700 dark:text-white mb-3">Status Sistem</h3>
             <div class="flex items-center p-4 bg-green-100 dark:bg-green-700/30 rounded-lg">
                <i class="fas fa-check-circle fa-2x text-green-500 dark:text-green-400 mr-4"></i>
                <div>
                    <p class="font-semibold text-slate-800 dark:text-slate-100">Semua Sistem Beroperasi Normal</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Update terakhir: <?php echo date('H:i'); ?> WIB</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="text-center mt-12 py-6 border-t border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400">&copy; <?php echo date('Y'); ?> YazPay Admin Panel. Dirancang dengan <i class="fas fa-heart text-red-500"></i>.</p>
    </footer>
</div>
<script>
    function initializeDashboardScripts() {
        console.log("Dashboard scripts initialized (v1.0.3 - data from GitHub, updated stats).");
        // Jika ada grafik atau interaksi JS spesifik dashboard, tambahkan di sini.
    }
    // Panggil inisialisasi. admin_panel.php akan memanggil ini setelah konten dimuat.
    if (typeof window.callFromAdminPanel !== 'undefined') {
        initializeDashboardScripts();
    } else { // Fallback jika diakses langsung (meskipun seharusnya tidak)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            initializeDashboardScripts();
        } else {
            document.addEventListener('DOMContentLoaded', initializeDashboardScripts);
        }
    }
</script>
