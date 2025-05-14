<?php
// Sertakan file helpers.php untuk mengakses fungsi-fungsi bantuan
require_once __DIR__ . '/helpers.php';

// Muat pengaturan dari file konfigurasi
$settings = load_settings();

// --- Pengambilan Data Pelanggan dari GitHub ---
$pelanggan_data = [];
$error_pelanggan = null;
if (!empty($settings['github_api_token']) && !empty($settings['github_repo_owner']) && !empty($settings['github_repo_name']) && !empty($settings['github_pelanggan_file'])) {
    $api_token_pelanggan = $settings['github_api_token'];
    $repo_owner_pelanggan = $settings['github_repo_owner'];
    $repo_name_pelanggan = $settings['github_repo_name'];
    $file_path_pelanggan = $settings['github_pelanggan_file'];
    $branch_pelanggan = $settings['github_branch'];
    $url_pelanggan = "https://api.github.com/repos/{$repo_owner_pelanggan}/{$repo_name_pelanggan}/contents/{$file_path_pelanggan}?ref={$branch_pelanggan}";

    $ch_pelanggan = curl_init();
    curl_setopt($ch_pelanggan, CURLOPT_URL, $url_pelanggan);
    curl_setopt($ch_pelanggan, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_pelanggan, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch_pelanggan, CURLOPT_HTTPHEADER, [
        "Authorization: token {$api_token_pelanggan}",
        "User-Agent: PHP-IndexFetchPelanggan"
    ]);
    // curl_setopt($ch_pelanggan, CURLOPT_SSL_VERIFYPEER, false); // Uncomment jika ada masalah SSL
    // curl_setopt($ch_pelanggan, CURLOPT_SSL_VERIFYHOST, false); // Uncomment jika ada masalah SSL

    $response_pelanggan = curl_exec($ch_pelanggan);
    $http_status_pelanggan = curl_getinfo($ch_pelanggan, CURLINFO_HTTP_CODE);
    $curl_error_pelanggan = curl_error($ch_pelanggan);
    curl_close($ch_pelanggan);

    if ($curl_error_pelanggan) {
         $error_msg = "cURL Error fetching pelanggan.json: " . $curl_error_pelanggan;
         error_log($error_msg); $error_pelanggan = "Gagal menghubungi server pelanggan."; $pelanggan_data = [];
    } elseif ($http_status_pelanggan === 200) {
        $data_pelanggan_api = json_decode($response_pelanggan, true);
        if (isset($data_pelanggan_api['content'])) {
            $json_data_pelanggan = base64_decode($data_pelanggan_api['content']);
            if ($json_data_pelanggan === false) {
                 $error_msg = "Gagal base64 decode content dari {$file_path_pelanggan}";
                 error_log($error_msg); $error_pelanggan = "Format data pelanggan tidak valid."; $pelanggan_data = [];
            } else {
                $decoded_data = json_decode($json_data_pelanggan, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $error_msg = "Gagal decode JSON dari {$file_path_pelanggan}: " . json_last_error_msg();
                    error_log($error_msg); $error_pelanggan = "Format data pelanggan tidak valid."; $pelanggan_data = [];
                } elseif (is_array($decoded_data)) {
                    $pelanggan_data = $decoded_data;
                } else {
                    $error_msg = "Decoded JSON dari {$file_path_pelanggan} bukan array.";
                    error_log($error_msg); $error_pelanggan = "Format data pelanggan tidak valid."; $pelanggan_data = [];
                }
            }
        } else {
            $error_msg = "GitHub API response content is missing or null for {$file_path_pelanggan}";
            error_log($error_msg); $error_pelanggan = "Data pelanggan tidak ditemukan di server."; $pelanggan_data = [];
        }
    } else {
        $response_body = substr($response_pelanggan ?: '', 0, 200);
        $error_msg = "Gagal fetch {$file_path_pelanggan}. Status: {$http_status_pelanggan}";
        error_log($error_msg . ", Response: " . $response_body);
        $error_pelanggan = "Gagal memuat data pelanggan (Status: {$http_status_pelanggan})."; $pelanggan_data = [];
    }
} else {
     $error_msg = "Pengaturan GitHub untuk file pelanggan tidak lengkap di config.";
     error_log($error_msg); $error_pelanggan = "Pengaturan GitHub tidak lengkap."; $pelanggan_data = [];
}

// --- Pengambilan Data Paket (untuk menentukan next_nomor) dari GitHub ---
$next_nomor = 1;
$error_next_nomor = null;
if (!empty($settings['github_api_token']) && !empty($settings['github_repo_owner']) && !empty($settings['github_repo_name']) && !empty($settings['github_data_file'])) {
    $api_token_data = $settings['github_api_token'];
    $repo_owner_data = $settings['github_repo_owner'];
    $repo_name_data = $settings['github_repo_name'];
    $file_path_data = $settings['github_data_file'];
    $branch_data = $settings['github_branch'];
    $url_data = "https://api.github.com/repos/{$repo_owner_data}/{$repo_name_data}/contents/{$file_path_data}?ref={$branch_data}";

    $ch_data = curl_init();
    curl_setopt($ch_data, CURLOPT_URL, $url_data);
    curl_setopt($ch_data, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_data, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch_data, CURLOPT_HTTPHEADER, [
        "Authorization: token {$api_token_data}",
        "User-Agent: PHP-IndexFetchData"
    ]);

    $response_data = curl_exec($ch_data);
    $http_status_data = curl_getinfo($ch_data, CURLINFO_HTTP_CODE);
    $curl_error_data = curl_error($ch_data);
    curl_close($ch_data);

    if ($curl_error_data) {
        $error_msg = "cURL Error fetching data.json: " . $curl_error_data;
        error_log($error_msg); $error_next_nomor = "Gagal menghubungi server data.";
    } elseif ($http_status_data === 200) {
        $data_arr = json_decode($response_data, true);
        if (isset($data_arr['content'])) {
            $json_data_content = base64_decode($data_arr['content']);
            if ($json_data_content !== false) {
                $existing_data = json_decode($json_data_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($existing_data) && !empty($existing_data)) {
                    $max_nomor = 0;
                    foreach ($existing_data as $item) {
                        if (isset($item['nomor']) && is_numeric($item['nomor'])) { $max_nomor = max($max_nomor, (int)$item['nomor']); }
                    }
                    $next_nomor = $max_nomor + 1;
                } elseif (json_last_error() !== JSON_ERROR_NONE) {
                     error_log("Gagal decode JSON dari data.json: " . json_last_error_msg()); $error_next_nomor = "Format data server tidak valid.";
                }
            } else {
                 error_log("Gagal base64 decode content dari data.json"); $error_next_nomor = "Format data server tidak valid.";
            }
        }
    } elseif ($http_status_data === 404) {
        error_log("data.json not found (Status: 404). Starting with number 1.");
    } else {
        $response_body = substr($response_data ?: '', 0, 200);
        $error_msg = "Gagal fetch data.json untuk next_nomor. Status: {$http_status_data}";
        error_log($error_msg . ", Response: " . $response_body); $error_next_nomor = "Gagal memuat data nomor urut (Status: {$http_status_data}).";
    }
} else {
     $error_msg = "Pengaturan GitHub untuk file data tidak lengkap di config.";
     error_log($error_msg); $error_next_nomor = "Pengaturan GitHub tidak lengkap.";
}

$whatsapp_api_url_js = !empty($settings['whatsapp_api_url']) ? json_encode($settings['whatsapp_api_url']) : 'null';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth transition-colors duration-500">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Data Paket</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], },
                    colors: {
                        primary: '#ee4d2d', primaryLight: '#FF6F61', gold: '#FFD700', surface: '#F9FAFB',
                        gray: { 50: '#F9FAFB', 100: '#F3F4F6', 200: '#E5E7EB', 300: '#D1D5DB', 600: '#4B5563', 700: '#374151', 800: '#1F2937', },
                        darkGray: '#333333',
                        slate: { 700: '#334155', 800: '#1e293b', 900: '#0f172a', }
                    },
                    boxShadow: {
                        'glow-primary': '0 4px 12px rgba(238, 77, 45, 0.3)', 'glow-gold': '0 4px 12px rgba(255, 215, 0, 0.3)',
                        'shopee': '0 1px 3px rgba(0, 0, 0, 0.1)', 'shopee-lg': '0 4px 15px rgba(0, 0, 0, 0.08)',
                    },
                },
            },
        };
    </script>
    <style>
        /* Efek Glass */
        .glass { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 12px; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .glass:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1); }
        .dark .glass { background: rgba(30, 41, 59, 0.7); border-color: rgba(51, 65, 85, 0.5); }
        /* Alert Tetap */
        .alert-fixed { position: fixed; right: 20px; z-index: 1055; display: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); max-width: 350px; border: 1px solid transparent; }
        /* Posisi Alert */
        #success-alert, #error-alert { top: 80px; }
        #wa-info-alert, #wa-error-alert { top: 140px; }
        /* Warna Alert (Bootstrap mungkin sudah menangani ini, tapi bisa untuk override) */
        .alert-success { background-color: #d1fae5; color: #065f46; border-color: #34d399; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-color: #f87171; }
        .alert-info { background-color: #DBEAFE; color: #1E40AF; border-color: #93C5FD; }
        /* Modal Styling (Beberapa mungkin sudah di-cover Bootstrap, sesuaikan jika perlu) */
        /* .modal { z-index: 1050; } */ /* Bootstrap sudah punya ini */
        .modal-content { background-color: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); border: none; }
        .dark .modal-content { background-color: #1e293b; }
        .modal-header { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
        .dark .modal-header { border-bottom-color: #334155; }
        .modal-title { font-size: 20px; font-weight: 600; color: #111827; }
        .dark .modal-title { color: #f1f5f9; }
        .dark .btn-close { filter: invert(1) grayscale(100%) brightness(200%); } /* Pastikan ini bekerja dengan baik dengan Bootstrap btn-close */
        .modal-body { padding: 24px; }
        /* Detail Pesanan di Modal */
        .order-details { padding: 16px; background-color: #f0f9ff; border-radius: 8px; border: 1px solid #e0f2fe; color: #075985; max-width: 500px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .dark .order-details { background-color: #0f172a; border-color: #1e293b; color: #e2e8f0; }
        hr { border-top: 1px solid #d1d5db; margin: 16px 0; }
        .dark hr { border-top-color: #4b5563; }
        .order-details p { margin: 8px 0; font-size: 14px; }
        .dark .order-details p { color: #cbd5e1; }
        .order-details strong { color: #0c4a6e; font-weight: 700; }
        .dark .order-details strong { color: #f8fafc; }
        /* Form Styling */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px; }
        .dark .form-group label { color: #d1d5db; }
        .form-control { display: block; width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); outline: none; transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out; background-color: #fff; color: #1f2937; }
        .dark .form-control { border-color: #4b5563; background-color: #374151; color: #f9fafb; }
        .form-control:focus { border-color: #ee4d2d; box-shadow: 0 0 0 3px rgba(238, 77, 45, 0.3); }
        .dark .form-control:focus { border-color: #FFD700; box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.3); }
        .form-control[readonly] { background-color: #f3f4f6; cursor: not-allowed; }
        .dark .form-control[readonly] { background-color: #4b5563; color: #9ca3af; }
        .form-control.border-red-500 { border-color: #ef4444 !important; } /* Tailwind might conflict, use !important if needed */
        .dark .form-control.border-red-500 { border-color: #f87171 !important; }
        /* Button Styling */
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 6px; font-weight: 600; transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); cursor: pointer; border: none; }
        .btn-block { display: flex; width: 100%; margin-top: 8px; text-align: center; }
        .btn-primary { background-color: #ee4d2d; color: #fff; }
        .btn-primary:hover { background-color: #d94325; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn-primary:disabled { background-color: #fdba74; cursor: not-allowed; box-shadow: none; }
        .btn-secondary { background-color: #e5e7eb; color: #374151; }
        .dark .btn-secondary { background-color: #4b5563; color: #d1d5db; }
        .btn-secondary:hover { background-color: #d1d5db; }
        .dark .btn-secondary:hover { background-color: #6b7280; }
        /* Form Step Animation */
        .form-step { display: none; }
        .form-step.active { display: block; animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="font-sans bg-gray-100 dark:bg-slate-900 text-gray-800 dark:text-gray-100">

    <nav class="fixed top-0 left-0 right-0 z-50 bg-white dark:bg-slate-800 shadow-md dark:shadow-gray-900/50 px-4 py-2 flex items-center justify-between border-b border-gray-200 dark:border-slate-700">
        <h1 class="text-xl font-bold text-primary dark:text-gold tracking-tight">
            <a href="/"><span class="font-semibold">Yaz</span><span class="font-normal">Pay</span></a>
        </h1>
        <div class="flex items-center space-x-3 md:space-x-4">
             <a href="/input" class="text-gray-700 dark:text-gray-300 hover:text-primary dark:hover:text-gold transition-colors duration-200 text-sm font-medium">Add Data</a>
             <a href="/add_pelanggan.php" class="text-gray-700 dark:text-gray-300 hover:text-primary dark:hover:text-gold transition-colors duration-200 text-sm font-medium">Add Pelanggan</a>
             <a href="/display" class="text-gray-700 dark:text-gray-300 hover:text-primary dark:hover:text-gold transition-colors duration-200 text-sm font-medium">Data Paket</a>
             <a href="admin.php" class="text-gray-700 dark:text-gray-300 hover:text-primary dark:hover:text-gold transition-colors duration-200 text-sm font-medium">Admin</a>
              <button id="darkToggle" class="bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-white px-3 py-1 rounded-full text-sm hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors duration-200 shadow-sm dark:shadow-none">
                <i class="fas fa-moon"></i> <span class="hidden sm:inline ml-1">Mode</span>
            </button>
        </div>
    </nav>

    <header class="text-center pt-24 pb-10 px-4 bg-gradient-to-b from-white dark:from-slate-900 to-gray-50 dark:to-slate-800">
         <h2 class="text-2xl sm:text-3xl font-semibold text-primary dark:text-gold drop-shadow-md leading-snug">
            Input Data Paket XL/AXIS
        </h2>
        <p class="mt-2 text-md text-gray-600 dark:text-gray-300 max-w-xl mx-auto">
            Masukkan detail transaksi paket XL/AXIS baru.
        </p>
    </header>

    <div class="container mx-auto mt-[-2rem] px-4 max-w-md pb-12 relative z-10">
        <div class="fixed top-20 right-5 z-[1055] w-full max-w-xs space-y-2">
             <div id="success-alert" class="alert alert-success alert-fixed hidden" role="alert"></div>
             <div id="error-alert" class="alert alert-danger alert-fixed hidden" role="alert"></div>
             <div id="wa-info-alert" class="alert alert-info alert-fixed hidden" role="alert"></div>
             <div id="wa-error-alert" class="alert alert-danger alert-fixed hidden" role="alert"></div>
        </div>

       <div class="glass p-6 md:p-8">
            <?php if ($error_pelanggan || $error_next_nomor): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 dark:bg-red-900/30 dark:border-red-600 dark:text-red-300" role="alert">
                    <strong class="font-bold">Error Memuat Data Awal!</strong><br>
                    <ul class="list-disc list-inside text-sm">
                        <?php if ($error_pelanggan): ?>
                            <li><?php echo htmlspecialchars($error_pelanggan); ?></li>
                        <?php endif; ?>
                         <?php if ($error_next_nomor): ?>
                            <li><?php echo htmlspecialchars($error_next_nomor); ?></li>
                        <?php endif; ?>
                    </ul>
                    <span class="text-xs block mt-1">(Periksa koneksi atau <a href='admin.php' class='underline'>pengaturan admin</a>. Detail error ada di log server)</span>
                </div>
            <?php endif; ?>

            <form id="dataForm" action="submit.php" method="post" novalidate>
                <div class="form-step active" id="step1">
                    <h3 class="text-xl font-semibold mb-5 text-gray-900 dark:text-white">Langkah 1: Detail Dasar</h3>
                    <div class="form-group">
                        <label for="nomor">Nomor Urut</label>
                        <input type="text" class="form-control" id="nomor" name="nomor" value="<?php echo $next_nomor; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="tanggal">Tanggal Transaksi</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="nomor_hp">Nomor HP Tujuan</label>
                        <input type="tel" inputmode="numeric" pattern="[0-9\s\-\+]*" class="form-control" id="nomor_hp" name="nomor_hp" placeholder="Contoh: 08123456789" required>
                    </div>
                    <button type="button" class="btn btn-primary btn-block mt-5 next-step">Selanjutnya <i class="fas fa-arrow-right ml-2"></i></button>
                </div>

                <div class="form-step" id="step2">
                     <h3 class="text-xl font-semibold mb-5 text-gray-900 dark:text-white">Langkah 2: Paket & Pembeli</h3>
                    <div class="form-group">
                        <label for="jenis_paket">Jenis Paket</label>
                        <select class="form-control" id="jenis_paket" name="jenis_paket" required>
                            <option value="" disabled selected>-- Pilih Jenis Paket --</option>
                            <option value="L 48GB">L 48GB</option>
                            <option value="XL 74GB">XL 74GB</option>
                            <option value="XXL Mini 105 GB">XXL Mini 105 GB</option>
                            <option value="XXL 124GB">XXL 124GB</option>
                            <option value="SUPER BIG 84GB">SUPER BIG 84GB</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nama_pembeli">Nama Pembeli</label>
                        <select class="form-control" id="nama_pembeli" name="nama_pembeli" required <?php echo empty($pelanggan_data) ? 'disabled' : ''; ?>>
                            <option value="" disabled selected>-- Pilih Nama Pembeli --</option>
                            <?php if (!empty($pelanggan_data)): ?>
                                <?php foreach ($pelanggan_data as $pelanggan): ?>
                                    <option value="<?php echo htmlspecialchars($pelanggan['nama']); ?>" data-wa="<?php echo htmlspecialchars($pelanggan['nomor_wa'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($pelanggan['nama']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled><?php echo $error_pelanggan ? 'Gagal memuat data' : 'Data kosong'; ?></option>
                            <?php endif; ?>
                        </select>
                         <?php if (empty($pelanggan_data) && $error_pelanggan): ?>
                             <p class="text-xs text-red-600 dark:text-red-400 mt-1">Gagal memuat data pelanggan. Cek <a href="admin.php" class="underline">Admin Panel</a>.</p>
                         <?php elseif (empty($pelanggan_data)): ?>
                              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Belum ada data pelanggan. Tambahkan di <a href="add_pelanggan.php" class="underline">Add Pelanggan</a>.</p>
                         <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="nomor_wa">Nomor WA Pembeli</label>
                        <input type="tel" inputmode="numeric" pattern="[0-9\s\-\+]*" class="form-control" id="nomor_wa" name="nomor_wa" readonly placeholder="Akan terisi otomatis">
                    </div>
                    <div class="flex justify-between mt-5">
                         <button type="button" class="btn btn-secondary prev-step"><i class="fas fa-arrow-left mr-2"></i>Sebelumnya</button>
                         <button type="button" class="btn btn-primary next-step">Selanjutnya <i class="fas fa-arrow-right ml-2"></i></button>
                    </div>
                </div>

                <div class="form-step" id="step3">
                     <h3 class="text-xl font-semibold mb-5 text-gray-900 dark:text-white">Langkah 3: Keuangan & Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="harga_jual">Harga Jual (Rp)</label>
                            <input type="number" inputmode="numeric" pattern="[0-9]*" class="form-control" id="harga_jual" name="harga_jual" placeholder="Contoh: 50000" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="harga_modal">Harga Modal (Rp)</label>
                            <input type="number" inputmode="numeric" pattern="[0-9]*" class="form-control" id="harga_modal" name="harga_modal" placeholder="Contoh: 45000" required min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="nama_seller">Nama Seller</label>
                        <input type="text" class="form-control" id="nama_seller" name="nama_seller" placeholder="Nama Anda atau toko" required>
                    </div>
                    <div class="form-group">
                        <label for="status_pembayaran">Status Pembayaran</label>
                        <select class="form-control" id="status_pembayaran" name="status_pembayaran" required>
                            <option value="LUNAS">LUNAS</option>
                            <option value="HUTANG">HUTANG</option>
                            <option value="Pending">Pending</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="flex justify-between mt-5">
                        <button type="button" class="btn btn-secondary prev-step"><i class="fas fa-arrow-left mr-2"></i>Sebelumnya</button>
                        <button type="submit" class="btn btn-primary submit-form" id="submitButton">
                             <span id="submitText">Submit <i class="fas fa-paper-plane ml-2"></i></span>
                             <span id="submitSpinner" style="display: none;">
                                 <i class="fas fa-spinner fa-spin mr-2"></i> Memproses...
                             </span>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <div class="modal fade" id="notaModal" tabindex="-1" aria-labelledby="notaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content dark:bg-slate-800">
                <div class="modal-header dark:border-slate-700">
                    <h5 class="modal-title dark:text-white" id="notaModalLabel">Detail Pesanan Berhasil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> </div>
                <div class="modal-body">
                    <div class="order-details">
                        <p class="text-center text-lg font-bold mb-3">🎉 Selamat! Pembelian paket berhasil!</p>
                        <hr class="dark:border-slate-600">
                        <p>🆔 <strong>ID Pesanan:</strong> Y202<span id="nota-nomor"></span></p>
                        <p>🍔 <strong>Jenis Paket:</strong> <span id="nota-jenis_paket"></span></p>
                        <p>🗓️ <strong>Tanggal&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> <span id="nota-tanggal"></span></p>
                        <p>👤 <strong>Pembeli&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> <span id="nota-nama_pembeli"></span></p>
                        <p>📞 <strong>Nomor HP&nbsp;&nbsp;&nbsp;:</strong> <span id="nota-nomor_hp"></span></p>
                        <p>💰 <strong>Harga&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> Rp <span id="nota-harga_jual"></span></p>
                        <hr class="dark:border-slate-600">
                        <p class="text-center text-sm">Terima kasih telah bertransaksi! 😊</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variabel global
        const WHATSAPP_API_URL = <?php echo $whatsapp_api_url_js; ?>;
        let submitAjaxRequest = null;
        let notaModalInstance = null;

        // Fungsi untuk menampilkan alert dan menyembunyikannya
        function showAlert(alertId, message, duration = 4000) {
            const alertElement = $('#' + alertId);
            if (!alertElement.length) return;
            // Menggunakan kelas Bootstrap untuk alert jika tersedia, atau fallback ke custom
            alertElement.removeClass('hidden d-none').text(message).addClass('show').fadeIn(); // d-none adalah kelas Bootstrap untuk display:none
            setTimeout(() => {
                alertElement.fadeOut(function() { $(this).addClass('hidden d-none').removeClass('show'); });
            }, duration);
        }
        function hideAlert(alertId) {
             const alertElement = $('#' + alertId);
             if (alertElement.length) {
                 alertElement.stop(true, true).fadeOut(function() { $(this).addClass('hidden d-none').removeClass('show'); });
             }
         }

        // Jalankan setelah DOM siap
        $(document).ready(function () {
            console.log("DOM ready. Initializing Bootstrap Modal...");
            const notaModalElement = document.getElementById('notaModal');
            if (notaModalElement) {
                 // Dengan CSS Bootstrap, .fade sudah cukup untuk menyembunyikan.
                 // $(notaModalElement).removeClass('show'); // Mungkin tidak perlu jika CSS Bootstrap dimuat dengan benar
                 console.log("Modal element #notaModal found.");
                 try {
                     notaModalInstance = new bootstrap.Modal(notaModalElement);
                     console.log("Bootstrap Modal initialized successfully in DOM ready.");

                     // Tidak perlu memanggil .hide() secara eksplisit jika CSS Bootstrap sudah benar
                     // Bootstrap secara otomatis akan menangani state awal modal .fade
                     // if (notaModalInstance) {
                     //     console.log("Attempting to explicitly hide modal after initialization...");
                     //     notaModalInstance.hide();
                     //     console.log("Modal hide() called after initialization.");
                     // }

                 } catch (e) {
                     console.error("Error initializing Bootstrap Modal in DOM ready:", e);
                     showAlert('error-alert', 'Gagal menginisialisasi komponen modal.', 10000);
                 }
            } else {
                 console.error("Modal element #notaModal not found in DOM ready.");
            }

            let currentStep = 1;
            const $formSteps = $('.form-step');
            const totalSteps = $formSteps.length;

            function showStep(step) {
                console.log("Showing step:", step);
                if (step < 1 || step > totalSteps) return;
                $formSteps.removeClass('active').hide(); // Sembunyikan semua step
                $formSteps.eq(step - 1).addClass('active').fadeIn(300); // Tampilkan step yang benar
                currentStep = step;
            }
            showStep(currentStep); // Tampilkan step awal

            function validateStep(step) {
                 let isValid = true;
                 const $currentStepFields = $formSteps.eq(step - 1).find('[required]');
                 console.log("Validating step:", step);
                 $currentStepFields.removeClass('is-invalid'); // Gunakan kelas Bootstrap untuk validasi jika memungkinkan
                 $currentStepFields.each(function() {
                      const $input = $(this); let inputValid = true;
                      const fieldName = $input.attr('name') || $input.attr('id');
                      if ($input.is('select')) { if (!$input.val() || $input.val() === "") inputValid = false; }
                      else if ($input.attr('type') === 'number') { const minVal = parseFloat($input.attr('min')); const currentVal = parseFloat($input.val()); if ($input.val() === "" || isNaN(currentVal) || (!isNaN(minVal) && currentVal < minVal)) { inputValid = false; } }
                      else if ($input.attr('type') === 'tel') { const phonePattern = /^[+\-\s\d]{5,}$/; const isWaField = $input.attr('id') === 'nomor_wa'; const isEmpty = !$input.val() || !$input.val().trim(); if (!isWaField && isEmpty) { inputValid = false; } else if (!isEmpty && !phonePattern.test($input.val().trim())) { inputValid = false; } }
                      else if ($input.attr('type') === 'date') { if (!$input.val()) inputValid = false; }
                      else { if (!$input.val() || !$input.val().trim()) inputValid = false; }
                      if (!inputValid) { console.log("Validation failed for field:", fieldName); isValid = false; $input.addClass('is-invalid'); } // Gunakan kelas Bootstrap
                 });
                 console.log("Step", step, "validation result:", isValid);
                 return isValid;
            }

            $('.next-step').on('click', function() {
                console.log("Next button clicked. Current step:", currentStep);
                if (validateStep(currentStep)) { if (currentStep < totalSteps) { showStep(currentStep + 1); } }
                else { showAlert('error-alert', 'Harap periksa kembali field yang ditandai merah!'); }
            });

            $('.prev-step').on('click', function() {
                console.log("Previous button clicked. Current step:", currentStep);
                if (currentStep > 1) {
                    $formSteps.eq(currentStep - 1).find('.form-control').removeClass('is-invalid'); // Hapus kelas error Bootstrap
                    showStep(currentStep - 1);
                }
            });

            $('#nama_pembeli').on('change', function() {
                const selectedOption = $(this).find('option:selected'); const waNumber = selectedOption.data('wa') || '';
                $('#nomor_wa').val(waNumber);
                $('#nomor_wa').removeClass('is-invalid'); // Hapus kelas error Bootstrap
                console.log("Nama pembeli changed, WA number set to:", waNumber);
            });

            function sendWhatsAppMessage(formValues) {
                console.log("Attempting to send WhatsApp message to:", formValues.nomor_wa, "via API:", WHATSAPP_API_URL);
                return new Promise((resolve) => {
                    const message = `\nHai *${formValues.nama_pembeli}*, 👋\n\nTerima kasih telah membeli paket XL/AXIS di YazPay! Berikut adalah detail pesanan Anda:\n\n🆔 *ID Pesanan*: Y202${formValues.nomor}\n📞 *Nomor Tujuan*: ${formValues.nomor_hp}\n🗓️ *Tanggal*: ${formValues.tanggal}\n🍔 *Jenis Paket*: ${formValues.jenis_paket}\n💰 *Harga*: Rp ${formValues.harga_jual}\n\n🙏 Kami sangat menghargai kepercayaan Anda. Jika ada pertanyaan atau membutuhkan bantuan lebih lanjut, silakan hubungi kami.\n\n#yazpay\nhttps://andrias.web.id/ 😊`;
                    const nomorWA = formValues.nomor_wa;

                    if (!nomorWA) {
                        console.warn("Nomor WA tidak tersedia, pesan WA tidak dikirim.");
                        resolve({ success: true, message: "Nomor WA tidak tersedia, notifikasi WA dilewati." });
                        return;
                    }
                    if (!WHATSAPP_API_URL || WHATSAPP_API_URL === 'null') {
                        console.warn("URL WhatsApp API tidak diatur di konfigurasi.");
                        showAlert('wa-error-alert', 'URL WhatsApp API belum diatur di admin panel.', 6000);
                        resolve({ success: false, message: "URL WhatsApp API tidak diatur." });
                        return;
                    }

                    hideAlert('wa-error-alert'); showAlert('wa-info-alert', 'Mengirim notifikasi WhatsApp...');
                    $.ajax({
                        url: WHATSAPP_API_URL, type: 'GET', data: { message: message, wa: nomorWA }, timeout: 15000,
                        success: function (waResponse) {
                            console.log("WhatsApp AJAX Success:", waResponse); hideAlert('wa-info-alert');
                            showAlert('success-alert', 'Notifikasi WhatsApp berhasil dikirim.', 3000);
                            resolve({ success: true, message: "Notifikasi WhatsApp terkirim." });
                        },
                        error: function (xhr, status, error) {
                            console.error("WhatsApp AJAX Error:", status, error, xhr); hideAlert('wa-info-alert');
                            let waErrorMsg = 'Gagal mengirim notifikasi WhatsApp.';
                            if (status === 'timeout') { waErrorMsg += ' API tidak merespons.'; } else { waErrorMsg += ' Status: ' + status; }
                            showAlert('wa-error-alert', waErrorMsg, 6000);
                            resolve({ success: false, message: waErrorMsg });
                        }
                    });
                });
            }

            $('#dataForm').on('submit', function (e) {
                console.log("Form submit event triggered."); e.preventDefault();
                 if (!validateStep(currentStep)) { showAlert('error-alert', 'Harap periksa kembali field yang ditandai merah sebelum mengirim!'); return; }
                const formData = $(this).serializeArray(); const formValues = {};
                formData.forEach(function (field) { formValues[field.name] = field.value.trim(); });
                console.log("Form data collected:", formValues);
                $('#submitText').hide(); $('#submitSpinner').css('display', 'inline-flex'); $('.submit-form').prop('disabled', true);
                console.log("Submit button disabled, spinner shown.");
                if (submitAjaxRequest) { console.log("Aborting previous submit request."); submitAjaxRequest.abort(); }

                console.log("Initiating AJAX request to submit.php...");
                submitAjaxRequest = $.ajax({
                    url: 'submit.php', type: 'POST', data: formValues, dataType: 'json', timeout: 30000,
                    success: function (response) {
                        console.log('AJAX Success - Response from submit.php:', response);
                        if (response && response.success) { // GitHub save successful
                            hideAlert('error-alert');
                            showAlert('success-alert', response.message || 'Data berhasil disimpan ke GitHub!', 4000);

                            // 1. Update modal content
                            $('#nota-nomor').text(formValues.nomor || 'N/A');
                            $('#nota-tanggal').text(formValues.tanggal || 'N/A');
                            $('#nota-jenis_paket').text(formValues.jenis_paket || 'N/A');
                            $('#nota-nama_pembeli').text(formValues.nama_pembeli || 'N/A');
                            $('#nota-nomor_hp').text(formValues.nomor_hp || 'N/A');
                            $('#nota-harga_jual').text(formValues.harga_jual || 'N/A');
                            console.log("Modal content updated.");

                            // 2. Show the modal
                            if (notaModalInstance) {
                                 console.log("Attempting to show modal via notaModalInstance.show()...");
                                 try {
                                     notaModalInstance.show();
                                     console.log("Modal show() called successfully.");
                                 } catch(modalError) {
                                     console.error("Error calling notaModalInstance.show():", modalError);
                                     showAlert('error-alert', 'Data disimpan, tapi gagal menampilkan nota popup (JS Error).', 6000);
                                 }
                            } else {
                                 console.error("Cannot show modal, notaModalInstance is null or undefined.");
                                 showAlert('error-alert', 'Data disimpan, tapi gagal menampilkan nota popup (Instance Error).', 6000);
                            }

                            // 3. Send WhatsApp message
                            sendWhatsAppMessage(formValues)
                                .then(waResult => {
                                    console.log("WhatsApp send result:", waResult);
                                    if (!waResult.success && waResult.message) {
                                         // showAlert('wa-info-alert', 'Info WA: ' + waResult.message, 5000); // Contoh notifikasi non-blocking
                                    }
                                })
                                .catch(waError => {
                                    console.error("Error in sendWhatsAppMessage promise:", waError);
                                });

                            // 4. Re-enable submit button
                            $('#submitSpinner').hide();
                            $('#submitText').show();
                            $('.submit-form').prop('disabled', false);
                            console.log("Submit button re-enabled after successful GitHub save.");

                        } else { // GitHub save failed
                            const serverError = (response && response.error) ? response.error : 'Error tidak diketahui dari server.';
                            console.error("Server returned error (GitHub save):", serverError);
                            showAlert('error-alert', 'Gagal menyimpan data ke GitHub: ' + serverError.replace(/\n/g, '<br>'), 8000);
                            $('#submitSpinner').hide(); $('#submitText').show(); $('.submit-form').prop('disabled', false);
                            console.log("Submit button re-enabled after GitHub server error.");
                        }
                    },
                    error: function (xhr, status, error) {
                        if (status === 'abort') { console.log('Submit request aborted.'); return; }
                        console.error("AJAX Error (Submit):", status, error, xhr);
                        let errorMessage = 'Terjadi kesalahan saat mengirim data ke server.';
                         if (status === 'timeout') { errorMessage = 'Request timeout. Server tidak merespons.'; }
                         else if (status === 'parsererror') { errorMessage += ' Respons server tidak valid.'; }
                         else if (xhr.status === 404) { errorMessage += ' Endpoint tidak ditemukan (submit.php?).'; }
                         else if (xhr.status === 500) { errorMessage += ' Kesalahan internal server.'; }
                         else if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) { errorMessage = 'Input tidak valid:<br>' + xhr.responseJSON.error.replace(/\n/g, '<br>'); }
                         else if (error) { errorMessage += ' Detail: ' + error; }
                         else { errorMessage += ' Status: ' + status; }
                        showAlert('error-alert', errorMessage, 10000);
                        $('#submitSpinner').hide(); $('#submitText').show(); $('.submit-form').prop('disabled', false);
                        console.log("Submit button re-enabled after AJAX error.");
                    },
                    complete: function() {
                        console.log("AJAX Submit request completed.");
                        submitAjaxRequest = null;
                    }
                });
            });

            // Dark Mode Toggle Logic
            const darkToggle = $('#darkToggle'); const htmlElement = $('html');
            const moonIcon = '<i class="fas fa-moon"></i> <span class="hidden sm:inline ml-1">Mode</span>';
            const sunIcon = '<i class="fas fa-sun"></i> <span class="hidden sm:inline ml-1">Mode</span>';
            function applyTheme(isDark) { if (isDark) { htmlElement.addClass('dark'); darkToggle.html(sunIcon); } else { htmlElement.removeClass('dark'); darkToggle.html(moonIcon); } }
            let isDarkMode = localStorage.getItem('darkMode') === 'true';
            if (localStorage.getItem('darkMode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches) { isDarkMode = true; }
            applyTheme(isDarkMode);
            darkToggle.on('click', function() { isDarkMode = !isDarkMode; applyTheme(isDarkMode); localStorage.setItem('darkMode', isDarkMode); });

            // Set tanggal hari ini
             try { const today = new Date(); const formattedDate = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0'); $('#tanggal').val(formattedDate); console.log("Default date set to:", formattedDate); }
             catch (e) { console.error("Error setting default date:", e); }

        }); // End $(document).ready()
    </script>
</body>
</html>
