<?php
// Diasumsikan fungsi-fungsi helper sudah global atau akan didefinisikan jika belum.
if (!function_exists('load_settings')) {
    if (!defined('CONFIG_FILE_PATH_SETTINGS')) {
       define('CONFIG_FILE_PATH_SETTINGS', __DIR__ . '/input/config.php'); // Sesuaikan path ini!
    }
    function load_settings() { /* ... implementasi load_settings ... */
        if (file_exists(CONFIG_FILE_PATH_SETTINGS)) {
            $settings = include CONFIG_FILE_PATH_SETTINGS;
            if (is_array($settings)) {
                $defaults = get_default_settings_local();
                return array_merge($defaults, $settings);
            }
        }
        return get_default_settings_local();
    }
    function save_settings($settings) { /* ... implementasi save_settings ... */
        if (!is_array($settings)) return false;
        $defaults = get_default_settings_local();
        $settings_to_save = array_merge($defaults, $settings);
        $content = "<?php\n\nreturn " . var_export($settings_to_save, true) . ";\n";
        if (@file_put_contents(CONFIG_FILE_PATH_SETTINGS, $content, LOCK_EX) !== false) {
            @chmod(CONFIG_FILE_PATH_SETTINGS, 0600);
            return true;
        } else {
            error_log("Gagal menulis ke file konfigurasi: " . CONFIG_FILE_PATH_SETTINGS);
            return false;
        }
    }
    function get_default_settings_local() { /* ... implementasi get_default_settings_local ... */
         return [
            'github_api_token' => '', 'github_repo_owner' => 'andrias97',
            'github_repo_name' => 'databasepaket', 'github_branch' => 'main',
            'github_data_file' => 'data.json', 'github_pelanggan_file' => 'pelanggan.json',
            'whatsapp_api_url' => '',
        ];
    }
}
if (!function_exists('test_github_connection')) {
    function test_github_connection($settings) { /* ... implementasi test_github_connection ... */
        if (empty($settings['github_api_token']) || empty($settings['github_repo_owner']) || empty($settings['github_repo_name'])) {
            return ['success' => false, 'message' => 'Pengaturan GitHub (Token, Owner, Repo) tidak lengkap untuk tes.'];
        }
        $url = "https://api.github.com/repos/{$settings['github_repo_owner']}/{$settings['github_repo_name']}";
        $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: token {$settings['github_api_token']}", "User-Agent: PHP-AdminSettings-TestGitHub", "Accept: application/vnd.github.v3+json"]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); $response = curl_exec($ch); $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch); curl_close($ch);
        if ($curl_error) return ['success' => false, 'message' => "Tes GitHub Gagal (cURL Error): " . $curl_error];
        if ($http_status === 200) return ['success' => true, 'message' => "Tes Koneksi GitHub Berhasil (Status: $http_status). Repositori ditemukan."];
        if ($http_status === 401) return ['success' => false, 'message' => "Tes GitHub Gagal (Status: $http_status): Token API tidak valid atau tidak memiliki izin."];
        if ($http_status === 404) return ['success' => false, 'message' => "Tes GitHub Gagal (Status: $http_status): Repositori tidak ditemukan."];
        return ['success' => false, 'message' => "Tes GitHub Gagal (Status: $http_status). Respons: " . substr($response, 0, 100) . "..."];
    }
}
if (!function_exists('test_whatsapp_connection')) {
    function test_whatsapp_connection($apiUrl) { /* ... implementasi test_whatsapp_connection ... */
        if (empty($apiUrl) || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'URL API WhatsApp tidak valid untuk tes.'];
        }
        $ch = curl_init(); $testUrl = $apiUrl; curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch); $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch); curl_close($ch);
        if ($curl_error) return ['success' => false, 'message' => "Tes WhatsApp API Gagal (cURL Error): " . $curl_error];
        if ($http_status >= 200 && $http_status < 300) return ['success' => true, 'message' => "Tes WhatsApp API Berhasil (Status: $http_status). URL dapat diakses."];
        if ($http_status === 405) return ['success' => true, 'message' => "Tes WhatsApp API Berhasil (Status: $http_status). URL dapat diakses (Method Not Allowed - mungkin normal untuk tes GET)."];
        return ['success' => false, 'message' => "Tes WhatsApp API Gagal (Status: $http_status). URL mungkin tidak benar atau API tidak berjalan."];
    }
}

$message_settings = '';
$error_settings = '';
$current_settings = load_settings();

// Tangani permintaan AJAX untuk tes koneksi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response_ajax = ['success' => false, 'message' => 'Aksi tidak diketahui.'];

    if ($_POST['action'] === 'test_github') {
        // Ambil data token terbaru dari POST jika ada, atau gunakan yang tersimpan
        $test_settings = $current_settings;
        if (!empty($_POST['github_api_token'])) { // Jika user mengetik token baru di form
            $test_settings['github_api_token'] = trim($_POST['github_api_token']);
        }
        if (!empty($_POST['github_repo_owner'])) {
            $test_settings['github_repo_owner'] = trim($_POST['github_repo_owner']);
        }
        if (!empty($_POST['github_repo_name'])) {
            $test_settings['github_repo_name'] = trim($_POST['github_repo_name']);
        }
        $response_ajax = test_github_connection($test_settings);
    } elseif ($_POST['action'] === 'test_whatsapp') {
        $whatsapp_url_to_test = !empty($_POST['whatsapp_api_url']) ? trim($_POST['whatsapp_api_url']) : $current_settings['whatsapp_api_url'];
        $response_ajax = test_whatsapp_connection($whatsapp_url_to_test);
    }
    echo json_encode($response_ajax);
    exit;
}


// Proses jika formulir "Simpan Semua Pengaturan" disimpan (bukan AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_settings'])) {
    $new_settings_data = [
        'github_api_token' => trim($_POST['github_api_token']),
        'github_repo_owner' => trim($_POST['github_repo_owner']),
        'github_repo_name' => trim($_POST['github_repo_name']),
        'github_branch' => trim($_POST['github_branch']),
        'github_data_file' => trim($_POST['github_data_file']),
        'github_pelanggan_file' => trim($_POST['github_pelanggan_file']),
        'whatsapp_api_url' => trim($_POST['whatsapp_api_url']),
    ];

    $validation_errors = [];
    if (empty($new_settings_data['github_repo_owner'])) $validation_errors[] = "Pemilik Repositori GitHub tidak boleh kosong.";
    // ... (validasi lainnya seperti sebelumnya) ...
    if (empty($new_settings_data['whatsapp_api_url'])) {
         $validation_errors[] = "URL Endpoint WhatsApp API tidak boleh kosong.";
    } elseif (!filter_var($new_settings_data['whatsapp_api_url'], FILTER_VALIDATE_URL)) {
         $validation_errors[] = "Format URL Endpoint WhatsApp API tidak valid.";
    }

    if (!empty($validation_errors)) {
        $error_settings = "Terdapat kesalahan validasi:<br>" . implode("<br>", $validation_errors);
    } else {
        $settings_to_save = $current_settings;
        $settings_to_save['github_repo_owner'] = $new_settings_data['github_repo_owner'];
        // ... (update field lainnya) ...
        $settings_to_save['whatsapp_api_url'] = $new_settings_data['whatsapp_api_url'];
        if (!empty($new_settings_data['github_api_token'])) {
            $settings_to_save['github_api_token'] = $new_settings_data['github_api_token'];
        }

        if (save_settings($settings_to_save)) {
            $message_settings = "Semua pengaturan berhasil disimpan.";
            $current_settings = $settings_to_save;
        } else {
            $error_settings = "Gagal menyimpan pengaturan. Periksa izin tulis file konfigurasi di server.";
        }
    }
}
?>
<style>
    /* Style spesifik untuk halaman settings_github.php jika diperlukan */
    .input-settings {
        display: block; width: 100%; padding: 0.65rem 0.75rem;
        border: 1px solid #cbd5e1; border-radius: 0.375rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        font-size: 0.875rem;
    }
    .dark .input-settings { background-color: #334155; border-color: #475569; color: #f1f5f9; }
    .input-settings:focus { border-color: #ee4d2d; outline: none; box-shadow: 0 0 0 3px rgba(238, 77, 45, 0.3); }
    .dark .input-settings:focus { border-color: #FFD700; box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.3); }
    .label-settings { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #475569; }
    .dark .label-settings { color: #cbd5e1; }
    .btn-settings {
        padding: 0.6rem 1.2rem; border-radius: 0.375rem; font-weight: 600;
        transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-right: 0.5rem; margin-bottom: 0.5rem;
    }
    .btn-settings-primary { background-color: #ee4d2d; color: white; }
    .btn-settings-primary:hover { background-color: #d94325; }
    .btn-settings-secondary { background-color: #64748b; color: white; }
    .btn-settings-secondary:hover { background-color: #475569; }
    .dark .btn-settings-primary { background-color: #FFD700; color: #1e293b; }
    .dark .btn-settings-primary:hover { background-color: #e6c200; }
    .dark .btn-settings-secondary { background-color: #475569; color: #e2e8f0; }
    .dark .btn-settings-secondary:hover { background-color: #334155; }
    .relative { position: relative; }
    .password-toggle-settings {
         cursor: pointer; position: absolute; right: 0.75rem; top: 50%;
         transform: translateY(-50%); color: #64748b;
    }
    .dark .password-toggle-settings { color: #94a3b8; }
    .password-toggle-settings:hover { color: #1e293b; }
    .dark .password-toggle-settings:hover { color: #f1f5f9; }
    .info-text-settings { color: #64748b; font-size: 0.875rem; margin-top: 0.25rem; }
    .dark .info-text-settings { color: #94a3b8; }
    .warning-text-settings { color: #ca8a04; font-size: 0.875rem; margin-top: 0.25rem; }
    .dark .warning-text-settings { color: #facc15; }
    .section-divider { margin-top: 2rem; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; }
    .dark .section-divider { border-bottom-color: #334155; }
</style>

<div class="p-0 md:p-2">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 dark:text-white">Pengaturan API</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Konfigurasi koneksi ke GitHub dan WhatsApp API.</p>
    </header>

    <div class="bg-white dark:bg-slate-800 p-6 md:p-8 rounded-xl shadow-xl">
        <div id="settingsMessagesContainer" class="mb-6">
            <?php if ($message_settings): ?>
                <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-700 dark:text-green-100" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message_settings); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_settings): ?>
                <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-700 dark:text-red-100" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo nl2br(htmlspecialchars($error_settings)); ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" action="" id="settingsApiForm"> <section id="github-settings-section">
                <h2 class="text-2xl font-semibold text-slate-700 dark:text-slate-200 mb-1">Pengaturan GitHub</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Detail untuk mengakses repositori data Anda di GitHub.</p>
                <div class="space-y-6">
                    <div>
                        <label for="github_api_token_settings" class="label-settings">API Token GitHub</label>
                        <div class="relative">
                            <input type="password" id="github_api_token_settings" name="github_api_token" placeholder="Kosongkan jika tidak ingin mengubah" class="input-settings pr-10">
                            <span class="password-toggle-settings" onclick="togglePasswordVisibilitySettings('github_api_token_settings')">
                                <i class="fa fa-eye" id="toggleIcon_github_api_token_settings"></i>
                            </span>
                        </div>
                        <p class="warning-text-settings"><i class="fas fa-exclamation-triangle mr-1"></i>Token ini sangat sensitif. Jaga kerahasiaannya.</p>
                        <?php if (!empty($current_settings['github_api_token'])): ?>
                            <p class="info-text-settings">Token saat ini: &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;<?php echo htmlspecialchars(substr($current_settings['github_api_token'], -6)); ?> </p>
                        <?php else: ?>
                             <p class="info-text-settings">Token GitHub saat ini belum diatur.</p>
                        <?php endif; ?>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <label for="github_repo_owner_settings" class="label-settings">Pemilik Repositori</label>
                            <input type="text" id="github_repo_owner_settings" name="github_repo_owner" value="<?php echo htmlspecialchars($current_settings['github_repo_owner']); ?>" required class="input-settings">
                        </div>
                        <div>
                            <label for="github_repo_name_settings" class="label-settings">Nama Repositori</label>
                            <input type="text" id="github_repo_name_settings" name="github_repo_name" value="<?php echo htmlspecialchars($current_settings['github_repo_name']); ?>" required class="input-settings">
                        </div>
                    </div>
                     <div>
                        <label for="github_branch_settings" class="label-settings">Branch</label>
                        <input type="text" id="github_branch_settings" name="github_branch" value="<?php echo htmlspecialchars($current_settings['github_branch']); ?>" required class="input-settings">
                        <p class="info-text-settings">Contoh: main, master, dev</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <label for="github_data_file_settings" class="label-settings">Path File Data Transaksi</label>
                            <input type="text" id="github_data_file_settings" name="github_data_file" value="<?php echo htmlspecialchars($current_settings['github_data_file']); ?>" required class="input-settings">
                            <p class="info-text-settings">Contoh: data/transaksi.json</p>
                        </div>
                         <div>
                            <label for="github_pelanggan_file_settings" class="label-settings">Path File Data Pelanggan</label>
                            <input type="text" id="github_pelanggan_file_settings" name="github_pelanggan_file" value="<?php echo htmlspecialchars($current_settings['github_pelanggan_file']); ?>" required class="input-settings">
                            <p class="info-text-settings">Contoh: data/pelanggan.json</p>
                        </div>
                    </div>
                    <div>
                        <button type="button" id="testGithubBtn" class="btn-settings btn-settings-secondary">
                            <i class="fab fa-github mr-2"></i>Tes Koneksi GitHub
                        </button>
                    </div>
                </div>
            </section>

            <section id="whatsapp-settings-section" class="section-divider">
                <h2 class="text-2xl font-semibold text-slate-700 dark:text-slate-200 mb-1">Pengaturan WhatsApp API</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">URL endpoint untuk mengirim notifikasi WhatsApp.</p>
                <div class="space-y-6">
                    <div>
                        <label for="whatsapp_api_url_settings" class="label-settings">URL Endpoint WhatsApp API</label>
                        <input type="url" id="whatsapp_api_url_settings" name="whatsapp_api_url" value="<?php echo htmlspecialchars($current_settings['whatsapp_api_url']); ?>" placeholder="https://domain.com/api/kirimwa" required class="input-settings">
                        <p class="info-text-settings">Pastikan URL ini dapat diakses dan menerima parameter yang sesuai.</p>
                    </div>
                    <div>
                        <button type="button" id="testWhatsappBtn" class="btn-settings btn-settings-secondary">
                            <i class="fab fa-whatsapp mr-2"></i>Tes API WhatsApp
                        </button>
                    </div>
                </div>
            </section>

            <div class="mt-10 pt-6 border-t border-slate-200 dark:border-slate-700">
                <button type="submit" name="save_all_settings" class="btn-settings btn-settings-primary w-full sm:w-auto">
                    <i class="fas fa-save mr-2"></i>Simpan Semua Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Pastikan jQuery sudah dimuat oleh admin_panel.php
    if (typeof $ !== 'undefined') {
        function initializeSettingsFormScripts() {
            if ($('#settingsApiForm').data('initialized')) {
                console.log("Settings API form scripts already initialized.");
                return;
            }
            console.log("Initializing Settings API form scripts...");

            function displaySettingMessage(message, isError = false) {
                const container = $('#settingsMessagesContainer');
                container.empty(); // Hapus pesan lama
                const alertClass = isError ? 'text-red-700 bg-red-100 dark:bg-red-700 dark:text-red-100' : 'text-green-700 bg-green-100 dark:bg-green-700 dark:text-green-100';
                const iconClass = isError ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle';
                const messageDiv = $(`
                    <div class="p-4 text-sm rounded-lg ${alertClass}" role="alert">
                        <i class="${iconClass} mr-2"></i>${message}
                    </div>
                `);
                container.append(messageDiv);
            }

            $('#testGithubBtn').on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                $button.addClass('testing').prop('disabled', true).find('i').removeClass('fa-github').addClass('fa-spinner fa-spin');
                displaySettingMessage('Menguji koneksi GitHub...', false);

                $.ajax({
                    url: '', // Submit ke URL saat ini (settings_github.php)
                    type: 'POST',
                    data: {
                        action: 'test_github',
                        // Kirim nilai field saat ini untuk tes yang akurat jika user telah mengubahnya
                        github_api_token: $('#github_api_token_settings').val(),
                        github_repo_owner: $('#github_repo_owner_settings').val(),
                        github_repo_name: $('#github_repo_name_settings').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        displaySettingMessage(response.message, !response.success);
                    },
                    error: function() {
                        displaySettingMessage('Gagal menghubungi server untuk tes GitHub.', true);
                    },
                    complete: function() {
                        $button.removeClass('testing').prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-github');
                    }
                });
            });

            $('#testWhatsappBtn').on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                $button.addClass('testing').prop('disabled', true).find('i').removeClass('fa-whatsapp').addClass('fa-spinner fa-spin');
                displaySettingMessage('Menguji koneksi WhatsApp API...', false);

                $.ajax({
                    url: '', // Submit ke URL saat ini
                    type: 'POST',
                    data: {
                        action: 'test_whatsapp',
                        whatsapp_api_url: $('#whatsapp_api_url_settings').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        displaySettingMessage(response.message, !response.success);
                    },
                    error: function() {
                        displaySettingMessage('Gagal menghubungi server untuk tes WhatsApp API.', true);
                    },
                    complete: function() {
                        $button.removeClass('testing').prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-whatsapp');
                    }
                });
            });
            
            $('#settingsApiForm').data('initialized', true);
            console.log("Settings API form scripts initialized.");
        }

        // Panggil inisialisasi saat dokumen siap atau jika konten dimuat via AJAX
        // admin_panel.php harus memanggil ini setelah memuat konten jika diperlukan
         if (document.readyState === 'complete' || document.readyState === 'interactive') {
            initializeSettingsFormScripts();
        } else {
            $(document).ready(initializeSettingsFormScripts);
        }

    } else {
        console.error("jQuery is not loaded. Settings page AJAX functionality will not work.");
    }

    function togglePasswordVisibilitySettings(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById('toggleIcon_' + inputId);
        if (input && icon) {
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }
    // console.log("settings_github.php (with WhatsApp & AJAX Test) content script executed.");
</script>
