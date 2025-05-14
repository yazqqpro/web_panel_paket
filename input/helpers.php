    <?php
    /**
     * File ini berisi fungsi-fungsi bantuan yang digunakan oleh
     * index.php, submit.php, dan admin.php.
     */

    // --- KONFIGURASI DASAR ---
    // Pastikan path ini benar dan file config.php aman.
    define('CONFIG_FILE_PATH', __DIR__ . '/config.php');

    // --- FUNGSI HELPER ---

    /**
     * Memuat pengaturan dari file konfigurasi.
     * @return array Pengaturan yang dimuat atau array default jika file tidak ada/kosong.
     */
    function load_settings() {
        if (file_exists(CONFIG_FILE_PATH)) {
            // Gunakan @ untuk menekan warning jika file tidak valid PHP, tangani di bawah
            $settings = @include CONFIG_FILE_PATH;
            // Pastikan hasil include adalah array
            if (is_array($settings)) {
                // Set nilai default jika kunci tertentu tidak ada
                $defaults = get_default_settings();
                return array_merge($defaults, $settings);
            } else {
                 error_log("File konfigurasi " . CONFIG_FILE_PATH . " tidak mengembalikan array.");
            }
        } else {
             error_log("File konfigurasi " . CONFIG_FILE_PATH . " tidak ditemukan.");
        }
        // Kembalikan default jika file tidak ada atau tidak valid
        return get_default_settings();
    }

    /**
     * Menyimpan pengaturan ke file konfigurasi.
     * @param array $settings Pengaturan yang akan disimpan.
     * @return bool True jika berhasil disimpan, False jika gagal.
     */
    function save_settings($settings) {
        // Validasi dasar
        if (!is_array($settings)) {
            error_log("Gagal menyimpan pengaturan: data bukan array.");
            return false;
        }

        // Format konten untuk file PHP
        $content = "<?php\n\n// File konfigurasi otomatis - JANGAN EDIT MANUAL KECUALI TAHU APA YANG ANDA LAKUKAN\n\nreturn " . var_export($settings, true) . ";\n";

        // Coba tulis ke file dengan locking
        if (@file_put_contents(CONFIG_FILE_PATH, $content, LOCK_EX) !== false) {
            // Coba set permission (opsional, tergantung environment server)
            @chmod(CONFIG_FILE_PATH, 0600); // Hanya pemilik yang bisa baca/tulis
            return true;
        } else {
            // Gagal menulis file (mungkin karena permission)
            error_log("Gagal menulis ke file konfigurasi: " . CONFIG_FILE_PATH . ". Periksa izin file.");
            return false;
        }
    }

    /**
     * Mendapatkan pengaturan default.
     * @return array Pengaturan default.
     */
    function get_default_settings() {
         return [
            'github_api_token' => '',
            'github_repo_owner' => 'andrias97', // Ganti default jika perlu
            'github_repo_name' => 'databasepaket', // Ganti default jika perlu
            'github_branch' => 'main',
            'github_data_file' => 'data.json',
            'github_pelanggan_file' => 'pelanggan.json',
            'whatsapp_api_url' => '', // Kosongkan default URL WA
        ];
    }

    /**
     * Melakukan tes koneksi ke GitHub API (contoh sederhana: get repo info).
     * @param array $settings Pengaturan saat ini.
     * @return array ['success' => bool, 'message' => string]
     */
    function test_github_connection($settings) {
        if (empty($settings['github_api_token']) || empty($settings['github_repo_owner']) || empty($settings['github_repo_name'])) {
            return ['success' => false, 'message' => 'Pengaturan GitHub (token, owner, repo) tidak lengkap untuk tes.'];
        }

        $url = "https://api.github.com/repos/{$settings['github_repo_owner']}/{$settings['github_repo_name']}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout 15 detik
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: token {$settings['github_api_token']}",
            "User-Agent: PHP-Admin-Test",
            "Accept: application/vnd.github.v3+json"
        ]);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Uncomment jika ada masalah SSL (tidak aman)
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Uncomment jika ada masalah SSL (tidak aman)

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
             return ['success' => false, 'message' => "Tes GitHub Gagal (cURL Error): " . $curl_error];
        } elseif ($http_status === 200) {
            return ['success' => true, 'message' => "Tes Koneksi GitHub Berhasil (Status: $http_status). Repositori '{$settings['github_repo_owner']}/{$settings['github_repo_name']}' ditemukan."];
        } elseif ($http_status === 401) {
            return ['success' => false, 'message' => "Tes GitHub Gagal (Status: $http_status): Token API tidak valid atau tidak memiliki izin akses ke repo."];
        } elseif ($http_status === 404) {
            return ['success' => false, 'message' => "Tes GitHub Gagal (Status: $http_status): Repositori '{$settings['github_repo_owner']}/{$settings['github_repo_name']}' tidak ditemukan."];
        } else {
            // Potong response agar tidak terlalu panjang di pesan error
            $short_response = substr(is_string($response) ? $response : '', 0, 150);
            return ['success' => false, 'message' => "Tes GitHub Gagal (Status: $http_status). Respons: " . $short_response . "..."];
        }
    }

    /**
     * Melakukan tes koneksi ke WhatsApp API (contoh sederhana: GET request).
     * @param string $apiUrl URL API WhatsApp.
     * @return array ['success' => bool, 'message' => string]
     */
    function test_whatsapp_connection($apiUrl) {
        if (empty($apiUrl)) {
             return ['success' => false, 'message' => 'URL API WhatsApp belum diatur.'];
        }
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Format URL API WhatsApp tidak valid.'];
        }

        $ch = curl_init();
        // Coba akses URL dasar saja
        $testUrl = $apiUrl;

        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout 10 detik
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Uncomment jika ada masalah SSL (tidak aman)
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Uncomment jika ada masalah SSL (tidak aman)
        // Jangan ikuti redirect otomatis untuk tes ini
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

         if ($curl_error) {
             // Cek error spesifik timeout
             if (strpos(strtolower($curl_error), 'timeout') !== false) {
                 return ['success' => false, 'message' => "Tes WhatsApp API Gagal (Timeout): Tidak ada respons dari {$apiUrl} dalam 10 detik."];
             }
             return ['success' => false, 'message' => "Tes WhatsApp API Gagal (cURL Error): " . $curl_error];
        }
        // Asumsikan status 2xx atau 405 (Method Not Allowed, tapi URL valid) atau 400/401/403 (API merespons tapi request salah) adalah sukses koneksi
        // Status 3xx (redirect) juga bisa dianggap sukses koneksi ke URL awal
         elseif (($http_status >= 200 && $http_status < 300) || in_array($http_status, [400, 401, 403, 405]) || ($http_status >= 300 && $http_status < 400)) {
            return ['success' => true, 'message' => "Tes WhatsApp API Berhasil (Status: $http_status). URL {$apiUrl} dapat diakses."];
        } else {
            return ['success' => false, 'message' => "Tes WhatsApp API Gagal (Status: $http_status). URL {$apiUrl} mungkin tidak benar atau API tidak berjalan."];
        }
    }

    ?>