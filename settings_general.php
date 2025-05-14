<?php
// session_start(); // Tidak perlu di sini, sudah di admin_panel.php
// Diasumsikan helpers.php (dan fungsi load_settings, save_settings) sudah global
// atau didefinisikan seperti di admin_panel.php atau settings_github.php jika diakses langsung.

if (!function_exists('load_settings')) {
    // Fallback jika fungsi belum ada (misalnya diakses langsung)
    // Pastikan path ke config.php benar
    if (!defined('CONFIG_FILE_PATH_GENERAL_SETTINGS')) {
        // Path ke config.php dari root (jika settings_general.php di root)
        // Berdasarkan konfirmasi pengguna: admin_panel.php di root, config.php di /input/
        // Jadi, jika settings_general.php juga di root, path ini benar.
        define('CONFIG_FILE_PATH_GENERAL_SETTINGS', __DIR__ . '/input/config.php');
    }
     function get_default_settings_general() { // Fungsi default lokal
        return [
            'admin_pin' => '0000', // Default PIN
            'github_api_token' => '', 'github_repo_owner' => '', 'github_repo_name' => '',
            'github_branch' => 'main', 'github_data_file' => 'data.json',
            'github_pelanggan_file' => 'pelanggan.json', 'whatsapp_api_url' => '',
        ];
    }
    function load_settings() {
        if (file_exists(CONFIG_FILE_PATH_GENERAL_SETTINGS)) {
            $settings = include CONFIG_FILE_PATH_GENERAL_SETTINGS;
            if (is_array($settings)) {
                return array_merge(get_default_settings_general(), $settings);
            }
        }
        return get_default_settings_general();
    }
    function save_settings($settings) {
        if (!is_array($settings)) return false;
        $defaults = get_default_settings_general();
        $settings_to_save = array_merge($defaults, $settings);
        $content = "<?php\n\n// File konfigurasi otomatis - JANGAN EDIT MANUAL KECUALI TAHU APA YANG ANDA LAKUKAN\n\nreturn " . var_export($settings_to_save, true) . ";\n";
        if (@file_put_contents(CONFIG_FILE_PATH_GENERAL_SETTINGS, $content, LOCK_EX) !== false) {
            @chmod(CONFIG_FILE_PATH_GENERAL_SETTINGS, 0600);
            return true;
        }
        error_log("Gagal menyimpan ke file config di: " . CONFIG_FILE_PATH_GENERAL_SETTINGS);
        return false;
    }
}


$message_general_settings = '';
$error_general_settings = '';
$current_app_settings = load_settings();
$stored_pin_for_verification = $current_app_settings['admin_pin'] ?? '0000'; // Digunakan untuk verifikasi PIN lama

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pin_settings'])) {
    $current_pin_input = trim($_POST['current_admin_pin'] ?? '');
    $new_pin = trim($_POST['new_admin_pin']);
    $confirm_new_pin = trim($_POST['confirm_new_admin_pin']);

    $pin_validation_errors = [];

    // Validasi PIN saat ini jika diisi
    if (!empty($current_pin_input)) {
        // Cek plain text dan hash (jika PIN di config sudah di-hash suatu saat)
        if (!password_verify($current_pin_input, $stored_pin_for_verification) && $current_pin_input !== $stored_pin_for_verification) {
            $pin_validation_errors[] = "PIN saat ini yang Anda masukkan salah.";
        }
    } elseif (isset($_POST['current_admin_pin']) && empty($current_pin_input) && !empty($stored_pin_for_verification) && $stored_pin_for_verification !== '0000') {
        // Jika PIN sudah pernah di-set (bukan default '0000'), dan user mencoba mengubah tanpa memasukkan PIN lama
        // Anda bisa mewajibkan ini jika mau:
        // $pin_validation_errors[] = "Masukkan PIN saat ini untuk verifikasi.";
    }


    if (empty($new_pin)) {
        $pin_validation_errors[] = "PIN baru tidak boleh kosong.";
    } elseif (!ctype_digit($new_pin) || strlen($new_pin) < 4 || strlen($new_pin) > 6) {
        $pin_validation_errors[] = "PIN baru harus terdiri dari 4-6 angka.";
    } elseif ($new_pin !== $confirm_new_pin) {
        $pin_validation_errors[] = "Konfirmasi PIN baru tidak cocok.";
    }

    if (!empty($pin_validation_errors)) {
        $error_general_settings = "Gagal mengubah PIN:<br>" . implode("<br>", $pin_validation_errors);
    } else {
        $settings_to_update = $current_app_settings;
        
        // PERINGATAN KEAMANAN: Menyimpan PIN sebagai plain text sangat tidak aman.
        // Pertimbangkan untuk menggunakan hashing.
        // Contoh dengan hashing:
        // $settings_to_update['admin_pin'] = password_hash($new_pin, PASSWORD_DEFAULT);
        // Saat login di admin_panel.php, gunakan password_verify($entered_pin, $app_settings['admin_pin'])

        // Untuk saat ini, sesuai permintaan, kita simpan plain text
        $settings_to_update['admin_pin'] = $new_pin; 

        if (save_settings($settings_to_update)) {
            $message_general_settings = "PIN Admin berhasil diubah.";
            $current_app_settings = $settings_to_update; 
            // Jika PIN di-hash, Anda tidak bisa menampilkan PIN lama lagi.
            // Anda mungkin ingin memaksa logout agar PIN baru segera efektif.
            // unset($_SESSION['admin_pin_authenticated']);
        } else {
            $error_general_settings = "Gagal menyimpan pengaturan PIN. Periksa izin tulis file konfigurasi.";
        }
    }
}
?>
<style>
    /* Style spesifik untuk halaman settings_general.php jika diperlukan */
    .input-general-settings {
        display: block; width: 100%; padding: 0.65rem 0.75rem;
        border: 1px solid #cbd5e1; border-radius: 0.375rem; /* rounded-md */
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
        transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        font-size: 0.875rem; /* text-sm */
    }
    .dark .input-general-settings { background-color: #334155; border-color: #475569; color: #f1f5f9; }
    .input-general-settings:focus { border-color: #ee4d2d; outline: none; box-shadow: 0 0 0 3px rgba(238, 77, 45, 0.3); }
    .dark .input-general-settings:focus { border-color: #FFD700; box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.3); }
    .label-general-settings { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #475569; } /* slate-600 */
    .dark .label-general-settings { color: #cbd5e1; } /* slate-300 */
    .btn-general-settings-primary {
        background-color: #ee4d2d; color: white; padding: 0.75rem 1.5rem; /* Lebih besar sedikit */ border-radius: 0.375rem; /* rounded-md */
        font-weight: 600; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .btn-general-settings-primary:hover { background-color: #d94325; } /* primary-dark */
    .dark .btn-general-settings-primary { background-color: #FFD700; color: #1e293b; } /* gold, slate-800 */
    .dark .btn-general-settings-primary:hover { background-color: #e6c200; }
    .info-text-general { color: #64748b; font-size: 0.875rem; margin-top: 0.25rem; } /* slate-500 */
    .dark .info-text-general { color: #94a3b8; } /* slate-400 */

    /* Animasi untuk pesan notifikasi */
    .settings-alert {
        opacity: 0;
        transform: translateY(-20px);
        transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        max-height: 0;
        overflow: hidden;
        padding-top: 0;
        padding-bottom: 0;
        margin-bottom: 0;
    }
    .settings-alert.show {
        opacity: 1;
        transform: translateY(0);
        max-height: 100px; /* Sesuaikan jika pesan lebih panjang */
        padding-top: 1rem;  /* Tailwind p-4 atas bawah */
        padding-bottom: 1rem;
        margin-bottom: 1.5rem; /* Tailwind mb-6 */
    }
</style>

<div class="p-0 md:p-2">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 dark:text-white">Pengaturan Umum</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Kelola pengaturan umum aplikasi Anda, termasuk PIN Admin.</p>
    </header>

    <div class="bg-white dark:bg-slate-800 p-6 md:p-8 rounded-xl shadow-xl max-w-lg mx-auto">
        <div id="generalSettingsMessageContainer">
            <?php if ($message_general_settings): ?>
                <div class="settings-alert p-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-700 dark:text-green-100" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message_general_settings); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_general_settings): ?>
                <div class="settings-alert p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-700 dark:text-red-100" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo nl2br(htmlspecialchars($error_general_settings)); ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" action="" id="generalSettingsForm" class="space-y-6">
            <div>
                <h3 class="text-xl font-semibold text-slate-700 dark:text-slate-200 mb-4">Ubah PIN Admin</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">
                    PIN digunakan untuk mengakses Admin Panel. Jaga kerahasiaan PIN Anda.
                </p>
                <p class="text-xs text-amber-600 dark:text-amber-400 mb-4">
                    <i class="fas fa-exclamation-triangle mr-1"></i> **PERINGATAN:** Menyimpan PIN sebagai teks biasa tidak aman. Pertimbangkan implementasi hashing.
                </p>
            </div>

            <div class="form-group">
                <label for="current_admin_pin" class="label-general-settings">PIN Admin Saat Ini (Opsional)</label>
                <input type="password" id="current_admin_pin" name="current_admin_pin" inputmode="numeric" pattern="[0-9]*"
                       class="input-general-settings text-center tracking-widest" maxlength="6" placeholder="Kosongkan jika baru pertama kali">
                <p class="info-text-general text-xs">Jika Anda ingin memverifikasi PIN lama sebelum mengubah. Biarkan kosong jika ini adalah pengaturan PIN pertama kali atau Anda tidak ingin verifikasi.</p>
            </div>

            <div class="form-group">
                <label for="new_admin_pin" class="label-general-settings">PIN Admin Baru (4-6 Angka)</label>
                <input type="password" id="new_admin_pin" name="new_admin_pin" inputmode="numeric" pattern="[0-9]*"
                       class="input-general-settings text-center tracking-widest" required maxlength="6">
            </div>

            <div class="form-group">
                <label for="confirm_new_admin_pin" class="label-general-settings">Konfirmasi PIN Admin Baru</label>
                <input type="password" id="confirm_new_admin_pin" name="confirm_new_admin_pin" inputmode="numeric" pattern="[0-9]*"
                       class="input-general-settings text-center tracking-widest" required maxlength="6">
            </div>

            <div class="pt-2">
                <button type="submit" name="save_pin_settings" class="btn-general-settings-primary w-full">
                    <i class="fas fa-save mr-2"></i>Simpan PIN Baru
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    function initializeGeneralSettingsScripts() {
        console.log("General settings scripts initialized.");
        const messageContainer = document.getElementById('generalSettingsMessageContainer');
        const alerts = messageContainer ? messageContainer.querySelectorAll('.settings-alert') : [];

        if (alerts.length > 0) {
            alerts.forEach(alertEl => {
                // Tampilkan alert dengan animasi
                setTimeout(() => {
                    alertEl.classList.add('show');
                }, 100); // Delay kecil untuk memastikan transisi CSS diterapkan

                // Sembunyikan alert setelah 5 detik
                setTimeout(() => {
                    alertEl.classList.remove('show');
                    // Setelah animasi selesai, hapus elemen dari DOM atau sembunyikan total
                    setTimeout(() => {
                        // alertEl.remove(); // Atau
                        if (messageContainer) messageContainer.innerHTML = ''; // Hapus semua pesan
                    }, 500); // Sesuaikan dengan durasi transisi CSS
                }, 5000); // 5 detik
            });
        }
    }

    // Panggil fungsi inisialisasi.
    // admin_panel.php harus memanggil ini setelah memuat konten jika diperlukan.
    if (typeof window.callFromAdminPanel === 'undefined') { // Jika tidak dipanggil dari admin_panel (misal, akses langsung)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            initializeGeneralSettingsScripts();
        } else {
            document.addEventListener('DOMContentLoaded', initializeGeneralSettingsScripts);
        }
    } else { // Jika dipanggil dari admin_panel, panggil langsung
        initializeGeneralSettingsScripts();
    }
</script>
