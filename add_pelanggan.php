<?php
// add_pelanggan.php - Disesuaikan untuk Admin Panel

// Diasumsikan fungsi load_settings() sudah tersedia secara global dari admin_panel.php (via helpers.php)
if (!function_exists('load_settings')) {
    if (!defined('CONFIG_FILE_PATH_ADD_PELANGGAN')) {
        // Path ke config.php dari root (jika add_pelanggan.php di root)
        define('CONFIG_FILE_PATH_ADD_PELANGGAN', __DIR__ . '/input/config.php');
    }
    if (!function_exists('get_default_settings_add_pelanggan')) {
        function get_default_settings_add_pelanggan() {
            return [
                'github_api_token' => '', 'github_repo_owner' => '', 'github_repo_name' => '',
                'github_branch' => 'main', 'github_pelanggan_file' => 'pelanggan.json',
            ];
        }
    }
    function load_settings() {
        if (file_exists(CONFIG_FILE_PATH_ADD_PELANGGAN)) {
            $settings_include = include CONFIG_FILE_PATH_ADD_PELANGGAN;
            if (is_array($settings_include)) {
                return array_merge(get_default_settings_add_pelanggan(), $settings_include);
            }
        }
        return get_default_settings_add_pelanggan();
    }
}

$settings = load_settings();
$page_message = '';
$page_error = '';

$api_token = $settings['github_api_token'] ?? null;
$repo_owner = $settings['github_repo_owner'] ?? null;
$repo_name = $settings['github_repo_name'] ?? null;
// Pastikan path file pelanggan diambil dengan benar dari settings
$file_path_pelanggan = $settings['github_pelanggan_file'] ?? 'pelanggan.json'; 
$branch = $settings['github_branch'] ?? 'main';

$array_data_pelanggan = [];
$sha_pelanggan = null;
$github_fetch_error = false;

// Logika untuk memuat data pelanggan dari GitHub
if (empty($api_token) || empty($repo_owner) || empty($repo_name)) {
    $page_error = "Konfigurasi GitHub API tidak lengkap. Silakan periksa Pengaturan di Admin Panel.";
    $github_fetch_error = true;
} else {
    $url_pelanggan_github = "https://api.github.com/repos/$repo_owner/$repo_name/contents/" . rawurlencode($file_path_pelanggan) . "?ref=$branch";
    $ch_fetch = curl_init();
    curl_setopt($ch_fetch, CURLOPT_URL, $url_pelanggan_github);
    curl_setopt($ch_fetch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_fetch, CURLOPT_HTTPHEADER, [
        "Authorization: token $api_token",
        "User-Agent: PHP-YazpayAdminAddPelanggan"
    ]);
    curl_setopt($ch_fetch, CURLOPT_TIMEOUT, 15);
    $response_fetch = curl_exec($ch_fetch);
    $http_status_fetch = curl_getinfo($ch_fetch, CURLINFO_HTTP_CODE);
    $curl_error_fetch = curl_error($ch_fetch);
    curl_close($ch_fetch);

    if ($curl_error_fetch) {
        $page_error = "Gagal memuat data pelanggan dari GitHub (cURL Error): " . htmlspecialchars($curl_error_fetch);
        error_log("Add Pelanggan cURL Error: " . $curl_error_fetch);
        $github_fetch_error = true;
    } elseif ($http_status_fetch === 200) {
        $data_github = json_decode($response_fetch, true);
        if (isset($data_github['content']) && isset($data_github['sha'])) {
            $json_data_pelanggan = base64_decode($data_github['content']);
            $decoded_pelanggan = json_decode($json_data_pelanggan, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_pelanggan)) {
                $array_data_pelanggan = $decoded_pelanggan;
            } elseif (empty($json_data_pelanggan) && $data_github['content'] === "") {
                 $array_data_pelanggan = [];
            } else {
                $page_error = "Format data pelanggan dari GitHub tidak valid (JSON error).";
                error_log("Add Pelanggan JSON Decode Error: " . json_last_error_msg());
            }
            $sha_pelanggan = $data_github['sha'];
        } else {
            if (isset($data_github['sha'])) {
                $sha_pelanggan = $data_github['sha'];
                $array_data_pelanggan = []; 
            } else {
                $page_error = "Respons GitHub tidak mengandung konten atau SHA yang diharapkan.";
                error_log("Add Pelanggan GitHub API response missing content/sha. Status: $http_status_fetch. Response: " . substr($response_fetch, 0, 200));
            }
        }
    } elseif ($http_status_fetch === 404) {
        $array_data_pelanggan = [];
        $sha_pelanggan = null; 
    } else {
        $page_error = "Gagal memuat data pelanggan dari GitHub (Status: $http_status_fetch).";
        error_log("Add Pelanggan GitHub API Error: Status $http_status_fetch. Response: " . substr($response_fetch, 0, 200));
        $github_fetch_error = true;
    }
}

$is_editing_pelanggan = false;
$edit_nama_pelanggan = '';
$edit_wa_pelanggan = '';
$id_edit_pelanggan = '';

$current_url_path = "add_pelanggan.php"; // Sesuaikan jika file ini ada di subfolder relatif terhadap root admin_panel.php

if (isset($_GET['edit_pelanggan_id'])) {
    $is_editing_pelanggan = true;
    $id_edit_pelanggan = htmlspecialchars($_GET['edit_pelanggan_id']);
    if (is_array($array_data_pelanggan)) {
        foreach ($array_data_pelanggan as $p) {
            if (isset($p['id']) && $p['id'] === $id_edit_pelanggan) {
                $edit_nama_pelanggan = htmlspecialchars($p['nama'] ?? '');
                $edit_wa_pelanggan = htmlspecialchars($p['nomor_wa'] ?? '');
                break;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pelanggan'])) {
    if ($github_fetch_error && $sha_pelanggan === null && !$is_editing_pelanggan) {
         error_log("Add Pelanggan: Fetch error but attempting to create new file as SHA is null.");
    } elseif ($github_fetch_error && $sha_pelanggan !== null) {
        $page_error = "Tidak dapat menyimpan perubahan karena data awal gagal dimuat sepenuhnya. Silakan muat ulang halaman dan coba lagi.";
        goto render_page_add_pelanggan;
    }

    $nama_input = trim(htmlspecialchars($_POST['nama_pelanggan_input']));
    $wa_input = trim(htmlspecialchars($_POST['nomor_wa_input']));
    $id_input = htmlspecialchars($_POST['id_pelanggan_input'] ?? '');

    if (empty($nama_input) || empty($wa_input)) {
        $page_error = "Nama pelanggan dan Nomor WA tidak boleh kosong.";
    } elseif (!preg_match('/^[0-9\s\-\+]{8,15}$/', $wa_input)) {
        $page_error = "Format Nomor WA tidak valid (harus 8-15 digit, boleh ada spasi/+/).";
    } else {
        $data_pelanggan_changed = false;
        $temp_array_data = $array_data_pelanggan; 

        if (!empty($id_input)) { // Mode Edit
            $found = false;
            foreach ($temp_array_data as $key => &$p_ref) {
                if (isset($p_ref['id']) && $p_ref['id'] === $id_input) {
                    if ($p_ref['nama'] !== $nama_input || $p_ref['nomor_wa'] !== $wa_input) {
                        $p_ref['nama'] = $nama_input;
                        $p_ref['nomor_wa'] = $wa_input;
                        $data_pelanggan_changed = true;
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) $page_error = "Pelanggan yang akan diedit tidak ditemukan.";
        } else { // Mode Tambah
            $new_pelanggan = [
                'id' => uniqid('pel_'),
                'nama' => $nama_input,
                'nomor_wa' => $wa_input,
            ];
            if(!is_array($temp_array_data)) $temp_array_data = [];
            $temp_array_data[] = $new_pelanggan;
            $data_pelanggan_changed = true;
        }

        if ($data_pelanggan_changed && empty($page_error)) {
            $updated_json_pelanggan = json_encode($temp_array_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $updated_base64_pelanggan = base64_encode($updated_json_pelanggan);
            $commit_message = !empty($id_input) ? "Update pelanggan ID: $id_input via Admin Panel" : "Tambah pelanggan baru: $nama_input via Admin Panel";
            
            $update_data_payload = [
                'message' => $commit_message,
                'content' => $updated_base64_pelanggan,
                'branch' => $branch
            ];
            if ($sha_pelanggan !== null) {
                $update_data_payload['sha'] = $sha_pelanggan;
            }

            $ch_update = curl_init();
            curl_setopt($ch_update, CURLOPT_URL, "https://api.github.com/repos/$repo_owner/$repo_name/contents/" . rawurlencode($file_path_pelanggan)); 
            curl_setopt($ch_update, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_update, CURLOPT_HTTPHEADER, [
                "Authorization: token $api_token", "User-Agent: PHP-YazpayAdminAddPelanggan", "Content-Type: application/json"
            ]);
            curl_setopt($ch_update, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch_update, CURLOPT_POSTFIELDS, json_encode($update_data_payload));
            curl_setopt($ch_update, CURLOPT_TIMEOUT, 20);

            $response_update = curl_exec($ch_update);
            $http_status_update = curl_getinfo($ch_update, CURLINFO_HTTP_CODE);
            $curl_error_update = curl_error($ch_update);
            curl_close($ch_update);

            if ($curl_error_update) {
                $page_error = "Gagal menyimpan data pelanggan ke GitHub (cURL Error): " . htmlspecialchars($curl_error_update);
            } elseif ($http_status_update === 200 || $http_status_update === 201) {
                $_SESSION['add_pelanggan_message'] = !empty($id_input) ? "Data pelanggan berhasil diperbarui." : "Pelanggan baru berhasil ditambahkan.";
                header("Location: " . $current_url_path . "?message_id=" . uniqid()); 
                exit;
            } else {
                $error_details = json_decode($response_update, true);
                $github_error_msg = $error_details['message'] ?? 'Tidak ada detail error dari GitHub.';
                $page_error = "Gagal menyimpan data pelanggan ke GitHub (Status: $http_status_update). Pesan: " . htmlspecialchars($github_error_msg);
            }
        } elseif (!$data_pelanggan_changed && empty($page_error)) {
            $page_message = "Tidak ada perubahan data untuk disimpan.";
        }
    }
}

if (isset($_GET['hapus_pelanggan_id'])) {
    if ($github_fetch_error && $sha_pelanggan === null) {
        $page_error = "Tidak dapat menghapus: File pelanggan tidak ditemukan atau gagal dimuat.";
    } elseif ($github_fetch_error) {
        $page_error = "Tidak dapat menghapus karena data awal gagal dimuat. Silakan muat ulang halaman dan coba lagi.";
    } elseif ($sha_pelanggan === null) { 
        $page_error = "Tidak ada data untuk dihapus (file kosong atau baru).";
    } else {
        $id_hapus = htmlspecialchars($_GET['hapus_pelanggan_id']);
        $temp_array_data_hapus = $array_data_pelanggan;
        $pelanggan_ditemukan_untuk_hapus = false;
        $key_to_delete = null;

        foreach ($temp_array_data_hapus as $index => $p_hapus) {
            if (isset($p_hapus['id']) && $p_hapus['id'] === $id_hapus) {
                $key_to_delete = $index;
                $pelanggan_ditemukan_untuk_hapus = true;
                break;
            }
        }

        if ($pelanggan_ditemukan_untuk_hapus) {
            unset($temp_array_data_hapus[$key_to_delete]);
            $temp_array_data_hapus = array_values($temp_array_data_hapus);

            $updated_json_pelanggan_hapus = json_encode($temp_array_data_hapus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $updated_base64_pelanggan_hapus = base64_encode($updated_json_pelanggan_hapus);

            $delete_payload = [
                'message' => "Hapus pelanggan ID: $id_hapus via Admin Panel",
                'content' => $updated_base64_pelanggan_hapus,
                'sha' => $sha_pelanggan,
                'branch' => $branch
            ];

            $ch_delete = curl_init();
            curl_setopt($ch_delete, CURLOPT_URL, "https://api.github.com/repos/$repo_owner/$repo_name/contents/" . rawurlencode($file_path_pelanggan));
            curl_setopt($ch_delete, CURLOPT_RETURNTRANSFER, true);
             curl_setopt($ch_delete, CURLOPT_HTTPHEADER, [
                "Authorization: token $api_token", "User-Agent: PHP-YazpayAdminAddPelanggan", "Content-Type: application/json"
            ]);
            curl_setopt($ch_delete, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch_delete, CURLOPT_POSTFIELDS, json_encode($delete_payload));
            curl_setopt($ch_delete, CURLOPT_TIMEOUT, 20);

            $response_delete = curl_exec($ch_delete);
            $http_status_delete = curl_getinfo($ch_delete, CURLINFO_HTTP_CODE);
            curl_close($ch_delete);

            if ($http_status_delete === 200) {
                $_SESSION['add_pelanggan_message'] = "Data pelanggan berhasil dihapus.";
                header("Location: " . $current_url_path . "?message_id=" . uniqid());
                exit;
            } else {
                $error_details_delete = json_decode($response_delete, true);
                $github_error_msg_delete = $error_details_delete['message'] ?? 'Tidak ada detail error dari GitHub.';
                $page_error = "Gagal menghapus data pelanggan di GitHub (Status: $http_status_delete). Pesan: " . htmlspecialchars($github_error_msg_delete);
            }
        } else {
            $page_error = "Pelanggan dengan ID tersebut tidak ditemukan untuk dihapus.";
        }
    }
}

if (isset($_SESSION['add_pelanggan_message'])) {
    $page_message = $_SESSION['add_pelanggan_message'];
    unset($_SESSION['add_pelanggan_message']);
}
render_page_add_pelanggan:
?>
<style>
    .form-input-addp {
        @apply block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm 
               focus:outline-none focus:ring-2 focus:ring-primary dark:focus:ring-gold focus:border-primary dark:focus:border-gold 
               bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 text-sm transition-colors duration-150;
    }
    .label-addp {
        @apply block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1;
    }
    .btn-addp { /* Tombol submit utama form */
        @apply w-full py-2.5 px-4 border border-transparent rounded-lg shadow-md text-sm font-semibold text-white 
               bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-light
               dark:bg-gold dark:text-slate-900 dark:hover:bg-yellow-500 dark:focus:ring-gold transition-all duration-150 ease-in-out
               flex items-center justify-center; /* Untuk ikon dan teks */
    }
    .btn-addp i {
        @apply mr-2;
    }
    /* Tombol Aksi di Tabel (Edit & Hapus) */
    .btn-action-pelanggan {
        @apply inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md shadow-sm 
               focus:outline-none focus:ring-2 focus:ring-offset-1 transition-colors duration-150;
    }
    .btn-edit-pelanggan {
        @apply text-white bg-amber-500 hover:bg-amber-600 focus:ring-amber-400 dark:focus:ring-offset-slate-800 mr-1;
    }
    .btn-delete-pelanggan {
        @apply text-white bg-red-600 hover:bg-red-700 focus:ring-red-500 dark:focus:ring-offset-slate-800;
    }
    /* Styling untuk tabel agar lebih konsisten */
    .table-addp thead th {
        @apply px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider bg-slate-50 dark:bg-slate-700/50;
    }
    .table-addp tbody td {
        @apply px-4 py-3 whitespace-nowrap text-sm;
    }
    .table-addp tbody tr:nth-child(even) {
        @apply bg-slate-50/50 dark:bg-slate-800/50;
    }
    .table-addp tbody tr:hover {
        @apply bg-slate-100 dark:bg-slate-700/60;
    }

    /* Modal styling (menggunakan Bootstrap jika dimuat oleh admin_panel.php) */
    #successModalAddPelanggan.modal.fade.show { display: flex !important; align-items: center; justify-content: center; }
    #successModalAddPelanggan .modal-dialog { max-width: 400px; }
</style>

<div class="p-0 md:p-2">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 dark:text-white">Manajemen Data Pelanggan</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Tambah, edit, atau hapus data pelanggan Anda.</p>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-xl">
                <h2 class="text-xl font-semibold text-slate-700 dark:text-slate-200 mb-5">
                    <?php echo $is_editing_pelanggan ? 'Edit Data Pelanggan' : 'Tambah Pelanggan Baru'; ?>
                </h2>

                <div id="addPelangganMessageContainer">
                    <?php if ($page_message && !isset($_GET['edit_pelanggan_id']) && !isset($_GET['hapus_pelanggan_id']) ): ?>
                        <div class="mb-4 p-3 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-700 dark:text-green-100" role="alert">
                            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($page_message); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($page_error): ?>
                        <div class="mb-4 p-3 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-700 dark:text-red-100" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i><?php echo nl2br(htmlspecialchars($page_error)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form action="<?php echo $current_url_path; ?>" method="POST" class="space-y-5" id="formAddPelanggan">
                    <input type="hidden" name="id_pelanggan_input" value="<?php echo $id_edit_pelanggan; ?>">
                    <div>
                        <label for="nama_pelanggan_input" class="label-addp">Nama Pelanggan</label>
                        <input type="text" id="nama_pelanggan_input" name="nama_pelanggan_input" placeholder="Masukkan Nama Lengkap" value="<?php echo $edit_nama_pelanggan; ?>" required class="form-input-addp">
                    </div>
                    <div>
                        <label for="nomor_wa_input" class="label-addp">Nomor WA (Contoh: 6281234567890)</label>
                        <input type="tel" id="nomor_wa_input" name="nomor_wa_input" placeholder="Masukkan Nomor WhatsApp Aktif" value="<?php echo $edit_wa_pelanggan; ?>" required class="form-input-addp" pattern="^[0-9\s\-\+]{8,15}$">
                    </div>
                    <button type="submit" name="submit_pelanggan" class="btn-addp">
                        <i class="fas <?php echo $is_editing_pelanggan ? 'fa-save' : 'fa-plus-circle'; ?>"></i>
                        <span><?php echo $is_editing_pelanggan ? 'Update Data' : 'Tambah Data'; ?></span>
                    </button>
                    <?php if ($is_editing_pelanggan): ?>
                        <a href="<?php echo $current_url_path; ?>" class="nav-link block text-center mt-3 text-sm text-slate-500 hover:text-primary dark:hover:text-gold transition-colors">
                            <i class="fas fa-times mr-1"></i>Batal Edit
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-xl">
                <h2 class="text-xl font-semibold text-slate-700 dark:text-slate-200 mb-5">Daftar Pelanggan</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 table-addp">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>Nomor WA</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700" id="tableBodyPelanggan">
                            <?php if (!empty($array_data_pelanggan) && is_array($array_data_pelanggan)): ?>
                                <?php foreach ($array_data_pelanggan as $index => $p): ?>
                                    <tr>
                                        <td class="text-slate-500 dark:text-slate-400"><?php echo $index + 1; ?></td>
                                        <td class="font-medium text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars($p['nama'] ?? '-'); ?></td>
                                        <td class="text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($p['nomor_wa'] ?? '-'); ?></td>
                                        <td>
                                            <a href="<?php echo $current_url_path; ?>?edit_pelanggan_id=<?php echo htmlspecialchars($p['id'] ?? ''); ?>" class="nav-link btn-action-pelanggan btn-edit-pelanggan">
                                                <i class="fas fa-edit mr-1"></i>Edit
                                            </a>
                                            <a href="<?php echo $current_url_path; ?>?hapus_pelanggan_id=<?php echo htmlspecialchars($p['id'] ?? ''); ?>" class="nav-link btn-action-pelanggan btn-delete-pelanggan" onclick="return confirm('Yakin ingin menghapus pelanggan <?php echo htmlspecialchars(addslashes($p['nama'] ?? '')); ?>?')">
                                                <i class="fas fa-trash-alt mr-1"></i>Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-sm text-slate-500 dark:text-slate-400 py-6">
                                        <?php echo $github_fetch_error ? 'Gagal memuat data pelanggan.' : 'Belum ada data pelanggan.'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="successModalAddPelanggan" tabindex="-1" aria-labelledby="successModalLabelAddPelanggan" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-white dark:bg-slate-800">
      <div class="modal-header border-b-0">
        <h5 class="modal-title text-lg font-semibold text-green-600 dark:text-green-400" id="successModalLabelAddPelanggan">
            <i class="fas fa-check-circle mr-2"></i>Berhasil!
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-slate-700 dark:text-slate-300" id="successModalMessageAddPelanggan">
      </div>
      <div class="modal-footer border-t-0">
        <button type="button" class="btn btn-secondary text-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
    function initializeAddPelangganScripts() {
        console.log("add_pelanggan.php scripts initialized (AJAX link fix attempt, refined CSS).");
        const messageContainer = document.getElementById('addPelangganMessageContainer');

        <?php if ($page_message && !empty($page_message)): ?>
        const successMessageFromPHP = <?php echo json_encode($page_message); ?>;
        if (successMessageFromPHP && typeof bootstrap !== 'undefined') {
            const successModalElement = document.getElementById('successModalAddPelanggan');
            if (successModalElement) {
                const modalMessageElement = document.getElementById('successModalMessageAddPelanggan');
                if(modalMessageElement) modalMessageElement.textContent = successMessageFromPHP;
                
                var existingModalInstance = bootstrap.Modal.getInstance(successModalElement);
                if (existingModalInstance) { existingModalInstance.dispose(); }
                var successModal = new bootstrap.Modal(successModalElement);
                successModal.show();
                
                if (window.history.replaceState) {
                    const cleanUrl = window.location.pathname + window.location.search.replace(/&?message_id=[^&]*/, '').replace(/^\?$/, '');
                    window.history.replaceState({path: cleanUrl}, '', cleanUrl);
                }
            }
        }
        <?php elseif ($page_error && !empty($page_error)): ?>
            if(messageContainer){ // Tampilkan error langsung di container, bukan modal
                 messageContainer.innerHTML = `<div class="mb-4 p-3 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-700 dark:text-red-100" role="alert"><i class="fas fa-exclamation-triangle mr-2"></i>${<?php echo json_encode(nl2br(htmlspecialchars($page_error))); ?>}</div>`;
            }
        <?php endif; ?>

        const form = document.getElementById('formAddPelanggan');
        if(form){
            form.addEventListener('submit', function(event){
                const namaInput = document.getElementById('nama_pelanggan_input');
                const waInput = document.getElementById('nomor_wa_input');
                let error = false;
                
                // Hapus pesan error lama (jika menggunakan jQuery untuk ini)
                if(typeof $ === 'function') {
                    $('#formAddPelanggan .form-error-msg').remove();
                } else { // Fallback jika jQuery tidak ada
                    document.querySelectorAll('#formAddPelanggan .form-error-msg').forEach(el => el.remove());
                }


                if(namaInput.value.trim() === ''){
                    $(namaInput).after('<p class="text-xs text-red-500 mt-1 form-error-msg">Nama tidak boleh kosong.</p>');
                    error = true;
                }
                if(waInput.value.trim() === ''){
                    $(waInput).after('<p class="text-xs text-red-500 mt-1 form-error-msg">Nomor WA tidak boleh kosong.</p>');
                    error = true;
                } else if(!/^[0-9\s\-\+]{8,15}$/.test(waInput.value.trim())){
                     $(waInput).after('<p class="text-xs text-red-500 mt-1 form-error-msg">Format Nomor WA tidak valid (8-15 digit).</p>');
                    error = true;
                }

                if(error){
                    event.preventDefault(); 
                }
            });
        }
    }

    if (typeof window.callFromAdminPanel !== 'undefined') {
        initializeAddPelangganScripts();
    } else {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            initializeAddPelangganScripts();
        } else {
            document.addEventListener('DOMContentLoaded', initializeAddPelangganScripts);
        }
    }
</script>
