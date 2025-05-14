<?php
// Sesuaikan path jika helpers.php tidak satu level di atas folder 'input'
require_once "/home/dsemmdlx/andrias.web.id/input/helpers.php"; // Asumsi helpers.php ada di root, satu level di atas folder 'input'

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
    $branch_pelanggan = $settings['github_branch']; // Menggunakan branch dari settings
    $url_pelanggan = "https://api.github.com/repos/{$repo_owner_pelanggan}/{$repo_name_pelanggan}/contents/{$file_path_pelanggan}?ref={$branch_pelanggan}";

    $ch_pelanggan = curl_init();
    curl_setopt($ch_pelanggan, CURLOPT_URL, $url_pelanggan);
    curl_setopt($ch_pelanggan, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_pelanggan, CURLOPT_TIMEOUT, 15); // Timeout 15 detik
    curl_setopt($ch_pelanggan, CURLOPT_HTTPHEADER, [
        "Authorization: token {$api_token_pelanggan}",
        "User-Agent: PHP-YazpayAdminInput" // User agent yang lebih spesifik
    ]);

    $response_pelanggan = curl_exec($ch_pelanggan);
    $http_status_pelanggan = curl_getinfo($ch_pelanggan, CURLINFO_HTTP_CODE);
    $curl_error_pelanggan = curl_error($ch_pelanggan);
    curl_close($ch_pelanggan);

    if ($curl_error_pelanggan) {
         $error_msg = "cURL Error fetching pelanggan.json: " . $curl_error_pelanggan;
         error_log($error_msg); $error_pelanggan = "Gagal menghubungi server pelanggan (cURL)."; $pelanggan_data = [];
    } elseif ($http_status_pelanggan === 200) {
        $data_pelanggan_api = json_decode($response_pelanggan, true);
        if (isset($data_pelanggan_api['content'])) {
            $json_data_pelanggan = base64_decode($data_pelanggan_api['content']);
            if ($json_data_pelanggan === false) {
                 $error_msg = "Gagal base64 decode content dari {$file_path_pelanggan}";
                 error_log($error_msg); $error_pelanggan = "Format data pelanggan (base64) tidak valid."; $pelanggan_data = [];
            } else {
                $decoded_data = json_decode($json_data_pelanggan, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $error_msg = "Gagal decode JSON dari {$file_path_pelanggan}: " . json_last_error_msg();
                    error_log($error_msg); $error_pelanggan = "Format data pelanggan (JSON) tidak valid."; $pelanggan_data = [];
                } elseif (is_array($decoded_data)) {
                    $pelanggan_data = $decoded_data;
                } else {
                    $error_msg = "Decoded JSON dari {$file_path_pelanggan} bukan array.";
                    error_log($error_msg); $error_pelanggan = "Struktur data pelanggan tidak valid."; $pelanggan_data = [];
                }
            }
        } else {
            $error_msg = "GitHub API response content is missing or null for {$file_path_pelanggan}";
            error_log($error_msg); $error_pelanggan = "Konten data pelanggan tidak ditemukan di server."; $pelanggan_data = [];
        }
    } else {
        $response_body = substr($response_pelanggan ?: '', 0, 200); // Log sebagian kecil response body
        $error_msg = "Gagal fetch {$file_path_pelanggan}. Status: {$http_status_pelanggan}";
        error_log($error_msg . ", Response: " . $response_body);
        $error_pelanggan = "Gagal memuat data pelanggan dari server (Status: {$http_status_pelanggan})."; $pelanggan_data = [];
    }
} else {
     $error_msg = "Pengaturan GitHub untuk file pelanggan tidak lengkap di config.";
     error_log($error_msg); $error_pelanggan = "Pengaturan GitHub untuk pelanggan tidak lengkap."; $pelanggan_data = [];
}

// --- Pengambilan Data Paket (untuk menentukan next_nomor) dari GitHub ---
$next_nomor = 1;
$error_next_nomor = null;
if (!empty($settings['github_api_token']) && !empty($settings['github_repo_owner']) && !empty($settings['github_repo_name']) && !empty($settings['github_data_file'])) {
    $api_token_data = $settings['github_api_token'];
    $repo_owner_data = $settings['github_repo_owner'];
    $repo_name_data = $settings['github_repo_name'];
    $file_path_data = $settings['github_data_file'];
    $branch_data = $settings['github_branch']; // Menggunakan branch dari settings
    $url_data = "https://api.github.com/repos/{$repo_owner_data}/{$repo_name_data}/contents/{$file_path_data}?ref={$branch_data}";

    $ch_data = curl_init();
    curl_setopt($ch_data, CURLOPT_URL, $url_data);
    curl_setopt($ch_data, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_data, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch_data, CURLOPT_HTTPHEADER, [
        "Authorization: token {$api_token_data}",
        "User-Agent: PHP-YazpayAdminInput"
    ]);

    $response_data = curl_exec($ch_data);
    $http_status_data = curl_getinfo($ch_data, CURLINFO_HTTP_CODE);
    $curl_error_data = curl_error($ch_data);
    curl_close($ch_data);

    if ($curl_error_data) {
        $error_msg = "cURL Error fetching data.json: " . $curl_error_data;
        error_log($error_msg); $error_next_nomor = "Gagal menghubungi server data paket (cURL).";
    } elseif ($http_status_data === 200) {
        $data_arr = json_decode($response_data, true);
        if (isset($data_arr['content'])) {
            $json_data_content = base64_decode($data_arr['content']);
            if ($json_data_content !== false) {
                $existing_data = json_decode($json_data_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($existing_data)) {
                    if (!empty($existing_data)) {
                        $max_nomor = 0;
                        foreach ($existing_data as $item) {
                            if (isset($item['nomor']) && is_numeric($item['nomor'])) { $max_nomor = max($max_nomor, (int)$item['nomor']); }
                        }
                        $next_nomor = $max_nomor + 1;
                    } else {
                        // File ada tapi kosong, $next_nomor tetap 1
                    }
                } elseif (json_last_error() !== JSON_ERROR_NONE) {
                     error_log("Gagal decode JSON dari data.json: " . json_last_error_msg()); $error_next_nomor = "Format data server (JSON) tidak valid.";
                }
            } else {
                 error_log("Gagal base64 decode content dari data.json"); $error_next_nomor = "Format data server (base64) tidak valid.";
            }
        } else {
            // Konten tidak ada, mungkin file baru atau kosong, $next_nomor tetap 1
             error_log("GitHub API response content is missing or null for {$file_path_data}. Assuming new file, next number is 1.");
        }
    } elseif ($http_status_data === 404) {
        // File tidak ditemukan, $next_nomor tetap 1
        error_log("data.json not found (Status: 404). Starting with number 1.");
    } else {
        $response_body = substr($response_data ?: '', 0, 200);
        $error_msg = "Gagal fetch data.json untuk next_nomor. Status: {$http_status_data}";
        error_log($error_msg . ", Response: " . $response_body); $error_next_nomor = "Gagal memuat data nomor urut dari server (Status: {$http_status_data}).";
    }
} else {
     $error_msg = "Pengaturan GitHub untuk file data tidak lengkap di config.";
     error_log($error_msg); $error_next_nomor = "Pengaturan GitHub untuk data paket tidak lengkap.";
}

$whatsapp_api_url_js = !empty($settings['whatsapp_api_url']) ? json_encode($settings['whatsapp_api_url']) : 'null';
?>

<div class="p-0 md:p-2"> <header class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 dark:text-white">Input Data Paket XL/AXIS</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Masukkan detail transaksi paket XL/AXIS baru melalui panel admin.</p>
    </header>

    <div classmx-auto max-w-xl"> <div class="fixed top-20 right-5 z-[1055] w-full max-w-xs space-y-2" id="inputFormAlertsContainer">
             <div id="success-alert-input" class="alert alert-success alert-fixed hidden" role="alert"></div>
             <div id="error-alert-input" class="alert alert-danger alert-fixed hidden" role="alert"></div>
             <div id="wa-info-alert-input" class="alert alert-info alert-fixed hidden" role="alert"></div>
             <div id="wa-error-alert-input" class="alert alert-danger alert-fixed hidden" role="alert"></div>
        </div>

       <div class="bg-white dark:bg-slate-800 p-6 md:p-8 rounded-xl shadow-xl">
            <?php if ($error_pelanggan || $error_next_nomor): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 dark:bg-red-900/30 dark:border-red-600 dark:text-red-300 rounded-md" role="alert">
                    <strong class="font-bold">Error Memuat Data Awal!</strong><br>
                    <ul class="list-disc list-inside text-sm mt-1">
                        <?php if ($error_pelanggan): ?>
                            <li><?php echo htmlspecialchars($error_pelanggan); ?></li>
                        <?php endif; ?>
                         <?php if ($error_next_nomor): ?>
                            <li><?php echo htmlspecialchars($error_next_nomor); ?></li>
                        <?php endif; ?>
                    </ul>
                    <span class="text-xs block mt-2">(Periksa koneksi atau pengaturan admin. Detail error ada di log server)</span>
                </div>
            <?php endif; ?>

            <form id="dataFormInput" action="./input/submit.php" method="post" novalidate> <div class="form-step active" id="step1-input">
                    <h3 class="text-xl font-semibold mb-5 text-gray-900 dark:text-white">Langkah 1: Detail Dasar</h3>
                    <div class="form-group">
                        <label for="nomor-input">Nomor Urut</label>
                        <input type="text" class="form-control" id="nomor-input" name="nomor" value="<?php echo $next_nomor; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="tanggal-input">Tanggal Transaksi</label>
                        <input type="date" class="form-control" id="tanggal-input" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="nomor_hp-input">Nomor HP Tujuan</label>
                        <input type="tel" inputmode="numeric" pattern="[0-9\s\-\+]*" class="form-control" id="nomor_hp-input" name="nomor_hp" placeholder="Contoh: 08123456789" required>
                    </div>
                    <button type="button" class="btn btn-primary btn-block mt-6 next-step-input">Selanjutnya <i class="fas fa-arrow-right ml-2"></i></button>
                </div>

                <div class="form-step" id="step2-input">
                     <h3 class="text-xl font-semibold mb-5 text-gray-900 dark:text-white">Langkah 2: Paket & Pembeli</h3>
                    <div class="form-group">
                        <label for="jenis_paket-input">Jenis Paket</label>
                        <select class="form-control" id="jenis_paket-input" name="jenis_paket" required>
                            <option value="" disabled selected>-- Pilih Jenis Paket --</option>
                            <option value="L 48GB">L 48GB</option>
                            <option value="XL 74GB">XL 74GB</option>
                            <option value="XXL Mini 105 GB">XXL Mini 105 GB</option>
                            <option value="XXL 124GB">XXL 124GB</option>
                            <option value="SUPER BIG 84GB">SUPER BIG 84GB</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nama_pembeli-input">Nama Pembeli</label>
                        <select class="form-control" id="nama_pembeli-input" name="nama_pembeli" required <?php echo empty($pelanggan_data) ? 'disabled' : ''; ?>>
                            <option value="" disabled selected>-- Pilih Nama Pembeli --</option>
                            <?php if (!empty($pelanggan_data)): ?>
                                <?php foreach ($pelanggan_data as $pelanggan): ?>
                                    <option value="<?php echo htmlspecialchars($pelanggan['nama']); ?>" data-wa="<?php echo htmlspecialchars($pelanggan['nomor_wa'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($pelanggan['nama']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled><?php echo $error_pelanggan ? 'Gagal memuat data pelanggan.' : 'Data pelanggan kosong.'; ?></option>
                            <?php endif; ?>
                        </select>
                         <?php if (empty($pelanggan_data) && $error_pelanggan): ?>
                             <p class="text-xs text-red-600 dark:text-red-400 mt-1">Gagal memuat data pelanggan. Cek <a href="#" class="nav-link underline" data-url="settings_placeholder.php">Pengaturan Admin</a>.</p>
                         <?php elseif (empty($pelanggan_data)): ?>
                              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Belum ada data pelanggan. Tambahkan di <a href="#" class="nav-link underline" data-url="add_pelanggan.php">Add Pelanggan</a>.</p>
                         <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="nomor_wa-input">Nomor WA Pembeli</label>
                        <input type="tel" inputmode="numeric" pattern="[0-9\s\-\+]*" class="form-control" id="nomor_wa-input" name="nomor_wa" readonly placeholder="Akan terisi otomatis">
                    </div>
                    <div class="flex justify-between mt-6">
                         <button type="button" class="btn btn-secondary prev-step-input"><i class="fas fa-arrow-left mr-2"></i>Sebelumnya</button>
                         <button type="button" class="btn btn-primary next-step-input">Selanjutnya <i class="fas fa-arrow-right ml-2"></i></button>
                    </div>
                </div>

                <div class="form-step" id="step3-input">
                     <h3 class="text-xl font-semibold mb-5 text-gray-900 dark:text-white">Langkah 3: Keuangan & Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="harga_jual-input">Harga Jual (Rp)</label>
                            <input type="number" inputmode="numeric" pattern="[0-9]*" class="form-control" id="harga_jual-input" name="harga_jual" placeholder="Contoh: 50000" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="harga_modal-input">Harga Modal (Rp)</label>
                            <input type="number" inputmode="numeric" pattern="[0-9]*" class="form-control" id="harga_modal-input" name="harga_modal" placeholder="Contoh: 45000" required min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="nama_seller-input">Nama Seller</label>
                        <input type="text" class="form-control" id="nama_seller-input" name="nama_seller" placeholder="Nama Anda atau toko" required>
                    </div>
                    <div class="form-group">
                        <label for="status_pembayaran-input">Status Pembayaran</label>
                        <select class="form-control" id="status_pembayaran-input" name="status_pembayaran" required>
                            <option value="LUNAS">LUNAS</option>
                            <option value="HUTANG">HUTANG</option>
                            <option value="Pending">Pending</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="flex justify-between mt-6">
                        <button type="button" class="btn btn-secondary prev-step-input"><i class="fas fa-arrow-left mr-2"></i>Sebelumnya</button>
                        <button type="submit" class="btn btn-primary submit-form-input" id="submitButton-input">
                             <span id="submitText-input">Submit <i class="fas fa-paper-plane ml-2"></i></span>
                             <span id="submitSpinner-input" style="display: none;">
                                 <i class="fas fa-spinner fa-spin mr-2"></i> Memproses...
                             </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="notaModalInput" tabindex="-1" aria-labelledby="notaModalLabelInput" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content dark:bg-slate-800">
                <div class="modal-header dark:border-slate-700">
                    <h5 class="modal-title dark:text-white" id="notaModalLabelInput">Detail Pesanan Berhasil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="order-details">
                        <p class="text-center text-lg font-bold mb-3">🎉 Selamat! Pembelian paket berhasil!</p>
                        <hr class="dark:border-slate-600">
                        <p>🆔 <strong>ID Pesanan:</strong> Y202<span id="nota-nomor-val"></span></p>
                        <p>🍔 <strong>Jenis Paket:</strong> <span id="nota-jenis_paket-val"></span></p>
                        <p>🗓️ <strong>Tanggal&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> <span id="nota-tanggal-val"></span></p>
                        <p>👤 <strong>Pembeli&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> <span id="nota-nama_pembeli-val"></span></p>
                        <p>📞 <strong>Nomor HP&nbsp;&nbsp;&nbsp;:</strong> <span id="nota-nomor_hp-val"></span></p>
                        <p>💰 <strong>Harga&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> Rp <span id="nota-harga_jual-val"></span></p>
                        <hr class="dark:border-slate-600">
                        <p class="text-center text-sm">Terima kasih telah bertransaksi! 😊</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> <script>
// Pastikan jQuery dan Bootstrap sudah dimuat oleh admin_panel.php
// Jika tidak, Anda perlu memuatnya di sini atau memastikan admin_panel.php memuatnya sebelum konten ini.
// Untuk saat ini, kita asumsikan sudah dimuat.

// Variabel global khusus untuk form ini (untuk menghindari konflik)
const WHATSAPP_API_URL_INPUT = <?php echo $whatsapp_api_url_js; ?>;
let submitAjaxRequestInput = null;
let notaModalInstanceInput = null;

// Fungsi showAlert dan hideAlert bisa jadi global di admin_panel.php
// Jika tidak, definisikan di sini dengan nama unik atau pastikan tidak konflik.
// Untuk contoh ini, kita buat versi lokal dengan ID unik.
function showAlertInput(alertId, message, duration = 4000) {
    const alertElement = $('#' + alertId); // Menggunakan ID yang sudah diberi suffix -input
    if (!alertElement.length) {
        console.warn("Alert element #" + alertId + " not found for showAlertInput.");
        return;
    }
    alertElement.removeClass('hidden d-none').text(message).addClass('show').fadeIn();
    setTimeout(() => {
        alertElement.fadeOut(function() { $(this).addClass('hidden d-none').removeClass('show'); });
    }, duration);
}

function hideAlertInput(alertId) {
     const alertElement = $('#' + alertId);
     if (alertElement.length) {
         alertElement.stop(true, true).fadeOut(function() { $(this).addClass('hidden d-none').removeClass('show'); });
     }
}


// Inisialisasi hanya jika belum ada (untuk mencegah re-inisialisasi saat konten dimuat ulang)
if (typeof window.inputFormInitialized === 'undefined' || !window.inputFormInitialized) {
    $(document).ready(function () {
        console.log("Input form content DOM ready. Initializing specific components...");

        const $form = $('#dataFormInput'); // ID form yang unik
        if (!$form.length) {
            console.error("Form #dataFormInput not found. Aborting initialization.");
            return;
        }

        const notaModalElement = document.getElementById('notaModalInput');
        if (notaModalElement && typeof bootstrap !== 'undefined') {
            try {
                 // Hancurkan instance modal lama jika ada, sebelum membuat yang baru
                if (bootstrap.Modal.getInstance(notaModalElement)) {
                    bootstrap.Modal.getInstance(notaModalElement).dispose();
                }
                notaModalInstanceInput = new bootstrap.Modal(notaModalElement);
                console.log("Bootstrap Modal #notaModalInput initialized successfully.");
            } catch (e) {
                 console.error("Error initializing Bootstrap Modal #notaModalInput:", e);
                 showAlertInput('error-alert-input', 'Gagal menginisialisasi komponen modal nota.', 10000);
            }
        } else {
            console.warn("Modal element #notaModalInput not found or Bootstrap JS not loaded.");
        }

        let currentStepInput = 1;
        const $formStepsInput = $form.find('.form-step'); // Cari step di dalam form ini
        const totalStepsInput = $formStepsInput.length;

        function showStepInput(step) {
            if (step < 1 || step > totalStepsInput) return;
            $formStepsInput.removeClass('active').hide();
            $formStepsInput.eq(step - 1).addClass('active').fadeIn(300);
            currentStepInput = step;
        }
        
        if (totalStepsInput > 0) {
            showStepInput(currentStepInput); // Tampilkan step awal
        } else {
            console.warn("No form steps found for #dataFormInput.");
        }


        function validateStepInput(step) {
             let isValid = true;
             const $currentStepFields = $formStepsInput.eq(step - 1).find('[required]');
             $currentStepFields.removeClass('is-invalid border-red-500 dark:border-red-500'); // Hapus kelas error Tailwind & Bootstrap
             $currentStepFields.each(function() {
                  const $input = $(this); let inputValid = true;
                  const fieldName = $input.attr('name') || $input.attr('id');
                  if ($input.is('select')) { if (!$input.val() || $input.val() === "") inputValid = false; }
                  else if ($input.attr('type') === 'number') { const minVal = parseFloat($input.attr('min')); const currentVal = parseFloat($input.val()); if ($input.val() === "" || isNaN(currentVal) || (!isNaN(minVal) && currentVal < minVal)) { inputValid = false; } }
                  else if ($input.attr('type') === 'tel') { const phonePattern = /^[+\-\s\d]{5,}$/; const isWaField = $input.attr('id') === 'nomor_wa-input'; const isEmpty = !$input.val() || !$input.val().trim(); if (!isWaField && isEmpty) { inputValid = false; } else if (!isEmpty && !phonePattern.test($input.val().trim())) { inputValid = false; } }
                  else if ($input.attr('type') === 'date') { if (!$input.val()) inputValid = false; }
                  else { if (!$input.val() || !$input.val().trim()) inputValid = false; }
                  if (!inputValid) { isValid = false; $input.addClass('is-invalid border-red-500 dark:border-red-500'); }
             });
             return isValid;
        }

        // Gunakan event delegation karena konten dimuat AJAX
        // Pastikan selector unik untuk form ini
        $(document).off('click', '.next-step-input').on('click', '.next-step-input', function() {
            if (validateStepInput(currentStepInput)) { if (currentStepInput < totalStepsInput) { showStepInput(currentStepInput + 1); } }
            else { showAlertInput('error-alert-input', 'Harap periksa kembali field yang ditandai merah!'); }
        });

        $(document).off('click', '.prev-step-input').on('click', '.prev-step-input', function() {
            if (currentStepInput > 1) {
                $formStepsInput.eq(currentStepInput - 1).find('.form-control').removeClass('is-invalid border-red-500 dark:border-red-500');
                showStepInput(currentStepInput - 1);
            }
        });

        $(document).off('change', '#nama_pembeli-input').on('change', '#nama_pembeli-input', function() {
            const selectedOption = $(this).find('option:selected'); const waNumber = selectedOption.data('wa') || '';
            $('#nomor_wa-input').val(waNumber);
            $('#nomor_wa-input').removeClass('is-invalid border-red-500 dark:border-red-500');
        });

        function sendWhatsAppMessageInput(formValues) {
            return new Promise((resolve) => {
                const message = `\nHai *${formValues.nama_pembeli}*, 👋\n\nTerima kasih telah membeli paket XL/AXIS di YazPay! Berikut adalah detail pesanan Anda:\n\n🆔 *ID Pesanan*: Y202${formValues.nomor}\n📞 *Nomor Tujuan*: ${formValues.nomor_hp}\n🗓️ *Tanggal*: ${formValues.tanggal}\n🍔 *Jenis Paket*: ${formValues.jenis_paket}\n💰 *Harga*: Rp ${formValues.harga_jual}\n\n🙏 Kami sangat menghargai kepercayaan Anda. Jika ada pertanyaan atau membutuhkan bantuan lebih lanjut, silakan hubungi kami.\n\n#yazpay\nhttps://andrias.web.id/ 😊`;
                const nomorWA = formValues.nomor_wa;

                if (!nomorWA) {
                    resolve({ success: true, message: "Nomor WA tidak tersedia, notifikasi WA dilewati." });
                    return;
                }
                if (!WHATSAPP_API_URL_INPUT || WHATSAPP_API_URL_INPUT === 'null') {
                    showAlertInput('wa-error-alert-input', 'URL WhatsApp API belum diatur.', 6000);
                    resolve({ success: false, message: "URL WhatsApp API tidak diatur." });
                    return;
                }

                hideAlertInput('wa-error-alert-input'); showAlertInput('wa-info-alert-input', 'Mengirim notifikasi WhatsApp...');
                $.ajax({
                    url: WHATSAPP_API_URL_INPUT, type: 'GET', data: { message: message, wa: nomorWA }, timeout: 15000,
                    success: function (waResponse) {
                        hideAlertInput('wa-info-alert-input');
                        showAlertInput('success-alert-input', 'Notifikasi WhatsApp berhasil dikirim.', 3000);
                        resolve({ success: true, message: "Notifikasi WhatsApp terkirim." });
                    },
                    error: function (xhr, status, error) {
                        hideAlertInput('wa-info-alert-input');
                        let waErrorMsg = 'Gagal mengirim notifikasi WhatsApp.';
                        if (status === 'timeout') { waErrorMsg += ' API tidak merespons.'; } else { waErrorMsg += ' Status: ' + status; }
                        showAlertInput('wa-error-alert-input', waErrorMsg, 6000);
                        resolve({ success: false, message: waErrorMsg });
                    }
                });
            });
        }

        // Gunakan event delegation untuk form submit
        $(document).off('submit', '#dataFormInput').on('submit', '#dataFormInput', function (e) {
            e.preventDefault();
             if (!validateStepInput(currentStepInput)) { showAlertInput('error-alert-input', 'Harap periksa kembali field yang ditandai merah!'); return; }

            const formData = $(this).serializeArray(); const formValues = {};
            formData.forEach(function (field) { formValues[field.name] = field.value.trim(); });

            $('#submitText-input').hide(); $('#submitSpinner-input').css('display', 'inline-flex'); $('.submit-form-input').prop('disabled', true);
            if (submitAjaxRequestInput) { submitAjaxRequestInput.abort(); }

            submitAjaxRequestInput = $.ajax({
                url: $(this).attr('action'), // Ambil URL dari form action
                type: 'POST', data: formValues, dataType: 'json', timeout: 30000,
                success: function (response) {
                    if (response && response.success) {
                        hideAlertInput('error-alert-input');
                        showAlertInput('success-alert-input', response.message || 'Data berhasil disimpan!', 4000);

                        $('#nota-nomor-val').text(formValues.nomor || 'N/A');
                        $('#nota-tanggal-val').text(formValues.tanggal || 'N/A');
                        $('#nota-jenis_paket-val').text(formValues.jenis_paket || 'N/A');
                        $('#nota-nama_pembeli-val').text(formValues.nama_pembeli || 'N/A');
                        $('#nota-nomor_hp-val').text(formValues.nomor_hp || 'N/A');
                        $('#nota-harga_jual-val').text(formValues.harga_jual || 'N/A');

                        if (notaModalInstanceInput) {
                             try { notaModalInstanceInput.show(); } catch(modalError) {
                                 console.error("Error calling notaModalInstanceInput.show():", modalError);
                                 showAlertInput('error-alert-input', 'Data disimpan, tapi gagal menampilkan nota (JS Error).', 6000);
                             }
                        } else {
                             showAlertInput('error-alert-input', 'Data disimpan, tapi gagal menampilkan nota (Instance Error).', 6000);
                        }

                        sendWhatsAppMessageInput(formValues); // Kirim WA tanpa menunggu hasilnya untuk UI

                        // Reset form atau update nomor urut jika perlu
                        // $form[0].reset();
                        // showStepInput(1);
                        // $('#nomor-input').val(parseInt(formValues.nomor, 10) + 1); // Perlu logika server untuk nomor berikutnya yang akurat

                    } else {
                        const serverError = (response && response.error) ? response.error : 'Error tidak diketahui dari server.';
                        showAlertInput('error-alert-input', 'Gagal menyimpan data: ' + serverError.replace(/\n/g, '<br>'), 8000);
                    }
                },
                error: function (xhr, status, error) {
                    if (status === 'abort') { return; }
                    let errorMessage = 'Terjadi kesalahan saat mengirim data.';
                     if (status === 'timeout') { errorMessage = 'Request timeout.'; }
                     else if (status === 'parsererror') { errorMessage += ' Respons server tidak valid.'; }
                     else if (xhr.status === 404) { errorMessage += ' Endpoint tidak ditemukan.'; }
                     else if (xhr.status === 500) { errorMessage += ' Kesalahan internal server.'; }
                     else if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) { errorMessage = 'Input tidak valid:<br>' + xhr.responseJSON.error.replace(/\n/g, '<br>'); }
                     else if (error) { errorMessage += ' Detail: ' + error; }
                    showAlertInput('error-alert-input', errorMessage, 10000);
                },
                complete: function() {
                    $('#submitSpinner-input').hide(); $('#submitText-input').show(); $('.submit-form-input').prop('disabled', false);
                    submitAjaxRequestInput = null;
                }
            });
        });

        // Set tanggal hari ini untuk form input ini
        try {
            const todayInput = new Date();
            const formattedDateInput = todayInput.getFullYear() + '-' + String(todayInput.getMonth() + 1).padStart(2, '0') + '-' + String(todayInput.getDate()).padStart(2, '0');
            $('#tanggal-input').val(formattedDateInput);
        } catch (e) { console.error("Error setting default date for input form:", e); }
        
        // Dark mode toggle tidak diperlukan di sini karena dihandle oleh admin_panel.php
        // Pastikan class .dark pada <html> di admin_panel.php sudah meng-cover styling elemen di sini.

        window.inputFormInitialized = true; // Tandai bahwa form ini sudah diinisialisasi
        console.log("Input form content fully initialized.");
    });
} else {
    console.log("Input form already initialized. Skipping re-initialization of event handlers.");
    // Jika konten dimuat ulang, Anda mungkin perlu me-refresh instance modal jika dihancurkan oleh jQuery.html()
    // atau memastikan instance lama masih valid.
    // Untuk Bootstrap 5, instance modal biasanya tetap ada kecuali dihancurkan manual atau elemen DOM dihapus total.
    // Jika ada masalah dengan modal setelah load ulang, re-inisialisasi modal di sini mungkin diperlukan.
    const notaModalElementRecheck = document.getElementById('notaModalInput');
    if (notaModalElementRecheck && typeof bootstrap !== 'undefined' && !bootstrap.Modal.getInstance(notaModalElementRecheck)) {
        try {
            notaModalInstanceInput = new bootstrap.Modal(notaModalElementRecheck);
            console.log("Bootstrap Modal #notaModalInput re-initialized after content reload.");
        } catch (e) {
            console.error("Error re-initializing Bootstrap Modal #notaModalInput:", e);
        }
    }
}
</script>
