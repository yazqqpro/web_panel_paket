<?php
// Asumsi file ini ada di folder 'display', jadi path ke helpers/functions disesuaikan
require_once '/home/dsemmdlx/andrias.web.id/input/helpers.php'; // Untuk load_settings()
require_once '/home/dsemmdlx/andrias.web.id/display/function.php'; // Untuk formatRupiah() jika masih digunakan di PHP

// Muat pengaturan untuk mendapatkan API token, dll.
$settings = load_settings();

$api_token = $settings['github_api_token'] ?? null;
$repo_owner = $settings['github_repo_owner'] ?? null;
$repo_name = $settings['github_repo_name'] ?? null;
$file_path = $settings['github_data_file'] ?? 'data.json'; // Default ke data.json jika tidak ada di settings
$branch = $settings['github_branch'] ?? 'main';     // Default ke main jika tidak ada di settings

$error_config_msg = "";
if (!$api_token || !$repo_owner || !$repo_name) {
    $error_config_msg = "Kesalahan: Konfigurasi GitHub API (token, owner, repo name) tidak lengkap. Silakan periksa pengaturan admin.";
    // Hentikan eksekusi lebih lanjut jika konfigurasi dasar tidak ada
    // Atau tampilkan pesan ini di dalam area konten.
}

$data_arr = [];
$file_sha = null;

if (empty($error_config_msg)) {
    $github_api_url = "https://api.github.com/repos/$repo_owner/$repo_name/contents/$file_path?ref=$branch";

    $ch = curl_init($github_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-YazpayAdminDisplay');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $api_token",
        "Accept: application/vnd.github.v3+json"
    ]);
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("cURL Error fetching $file_path: " . $curl_error);
        $error_config_msg = "Gagal menghubungi GitHub API (cURL error). Periksa log server.";
    } elseif ($http_status === 200) {
        $response_data = json_decode($response, true);
        if (isset($response_data['content']) && $response_data['content'] !== null && isset($response_data['sha']) && $response_data['sha'] !== null) {
            $json_data = base64_decode($response_data['content']);
            $decoded_json = json_decode($json_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_json)) {
                $data_arr = $decoded_json;
            } else {
                error_log("Failed to decode JSON from $file_path: " . json_last_error_msg());
                $error_config_msg = "Format data dari GitHub tidak valid (JSON error).";
                $data_arr = [];
            }
            $file_sha = $response_data['sha'];
        } else {
            error_log("GitHub API response content or sha is missing or null for $file_path. HTTP Status: $http_status. Response: " . substr($response, 0, 200));
             // Jika file belum ada (misalnya 404 di status sebelumnya, tapi di sini 200 dengan content null)
            if ($http_status === 200 && (!isset($response_data['content']) || $response_data['content'] === null) ){
                // Ini bisa terjadi jika file itu sendiri adalah null di repo, meskipun pathnya ada.
                // Anggap sebagai data kosong.
                $data_arr = [];
                $file_sha = $response_data['sha'] ?? null; // SHA mungkin ada meskipun konten null
            } else {
                $error_config_msg = "Data atau SHA file tidak ditemukan dalam respons GitHub.";
            }
        }
    } elseif ($http_status === 404) {
        // File tidak ditemukan, ini normal jika file belum pernah dibuat.
        // $data_arr akan tetap kosong, $file_sha akan null.
        error_log("$file_path not found on GitHub (404). Will attempt to create if data is saved.");
        // Tidak perlu set error message di sini, biarkan UI menampilkan "tidak ada data"
    } else {
        error_log("Failed to fetch data from GitHub API. Status: $http_status, Response: " . substr($response, 0, 500));
        $error_config_msg = "Gagal memuat data dari GitHub (Status: $http_status). Periksa token dan path file.";
    }
}

if (!empty($data_arr)) {
    usort($data_arr, function($a, $b) {
        $nomor_a = isset($a['nomor']) ? (int)$a['nomor'] : 0;
        $nomor_b = isset($b['nomor']) ? (int)$b['nomor'] : 0;
        return $nomor_b - $nomor_a;
    });
}

$total_harga_jual = $total_harga_modal = $total_untung = 0;
$totalData = count($data_arr);
foreach ($data_arr as $data) {
    $harga_jual = isset($data['harga_jual']) ? (float)$data['harga_jual'] : 0;
    $harga_modal = isset($data['harga_modal']) ? (float)$data['harga_modal'] : 0;
    $total_harga_jual += $harga_jual;
    $total_harga_modal += $harga_modal;
    $total_untung += ($harga_jual - $harga_modal);
}

$jsonData = json_encode($data_arr);
$jsonSha = json_encode($file_sha);

// Untuk JavaScript, kita juga perlu mengirimkan konfigurasi GitHub API yang aman
// JANGAN KIRIM TOKEN KE CLIENT SIDE JIKA INTERAKSI API DILAKUKAN DI PHP (SERVER SIDE)
// Jika JavaScript akan melakukan PUT request langsung ke GitHub (TIDAK DISARANKAN),
// maka token ini akan diperlukan di JS, TAPI INI SANGAT TIDAK AMAN.
// Untuk contoh ini, JavaScript akan melakukan PUT, jadi kita teruskan (dengan peringatan keamanan).
$jsConfig = json_encode([
    'github_pat' => $api_token, // SANGAT TIDAK AMAN DI CLIENT SIDE
    'repo_owner' => $repo_owner,
    'repo_name' => $repo_name,
    'file_path' => $file_path,
    'branch' => $branch
]);

?>
<style>
    /* Custom styles for responsive table, pagination, and edit modal specific to this display page */
    /* Disesuaikan dari file asli, pastikan tidak konflik dengan admin_panel.php */

    .table-responsive-display { /* Nama kelas unik */
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1rem; /* Tailwind mb-4 */
    }
    .table-responsive-display > .table {
        width: 100%;
        border-collapse: collapse;
    }
    .table-responsive-display > .table th,
    .table-responsive-display > .table td {
        padding: 0.75rem 1rem; /* Tailwind p-3 atau px-4 py-3 */
        text-align: left;
        border-bottom: 1px solid #e2e8f0; /* Tailwind slate-200 */
        white-space: nowrap;
    }
    .dark .table-responsive-display > .table th,
    .dark .table-responsive-display > .table td {
        border-bottom-color: #334155; /* Tailwind slate-700 */
    }
    .table-responsive-display > .table th {
        font-size: 0.75rem; /* Tailwind text-xs */
        font-weight: 600; /* Tailwind font-semibold */
        color: #64748b; /* Tailwind slate-500 */
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background-color: #f8fafc; /* Tailwind slate-50 */
    }
    .dark .table-responsive-display > .table th {
        color: #94a3b8; /* Tailwind slate-400 */
        background-color: #1e293b; /* Tailwind slate-800 */
    }
    .table-responsive-display > .table tbody tr:nth-child(even) {
        background-color: #f1f5f9; /* Tailwind slate-100 */
    }
    .dark .table-responsive-display > .table tbody tr:nth-child(even) {
        background-color: rgba(30, 41, 59, 0.5); /* slate-800/50 */
    }
    .table-responsive-display > .table tbody tr { cursor: pointer; }
    .table-responsive-display > .table tbody td {
        font-size: 0.875rem; /* Tailwind text-sm */
        color: #334155; /* Tailwind slate-700 */
    }
    .dark .table-responsive-display > .table tbody td {
        color: #cbd5e1; /* Tailwind slate-300 */
    }

    @media (max-width: 768px) {
        /* Mobile responsive table styles (data-title technique) */
        .table-responsive-display > .table thead { display: none; }
        .table-responsive-display > .table tbody tr {
            display: block; margin-bottom: 1rem;
            border: 1px solid #e2e8f0; border-radius: 0.5rem; /* rounded-lg */
            background-color: #ffffff; /* bg-white */
            padding: 0.75rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1); /* shadow-md */
        }
        .dark .table-responsive-display > .table tbody tr {
            border-color: #334155; background-color: #1e293b; box-shadow: none;
        }
        .table-responsive-display > .table tbody tr:nth-child(even) { background-color: #ffffff; }
        .dark .table-responsive-display > .table tbody tr:nth-child(even) { background-color: #1e293b; }
        .table-responsive-display > .table tbody tr td {
            display: block; text-align: right; padding-left: 50%; position: relative;
            border-bottom: 1px solid #f1f5f9; white-space: normal;
            padding-top: 0.5rem; padding-bottom: 0.5rem;
        }
        .dark .table-responsive-display > .table tbody tr td { border-bottom-color: #334155; }
        .table-responsive-display > .table tbody tr td:last-child { border-bottom: none; }
        .table-responsive-display > .table tbody tr td:before {
            content: attr(data-title); position: absolute; left: 0.75rem; width: 45%;
            font-weight: 600; text-align: left; color: #475569;
        }
        .dark .table-responsive-display > .table tbody tr td:before { color: #94a3b8; }
    }

    .pagination-display { /* Nama kelas unik */
        display: flex; justify-content: center; align-items: center; margin-top: 1.5rem; /* mt-6 */
    }
    .pagination-display button {
        padding: 0.5rem 1rem; margin: 0 0.25rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; /* rounded-md */
        background-color: #ffffff; color: #475569; cursor: pointer; transition: all 0.2s;
    }
    .dark .pagination-display button {
        border-color: #475569; background-color: #334155; color: #cbd5e1;
    }
    .pagination-display button:hover:not(:disabled) { background-color: #f1f5f9; }
    .dark .pagination-display button:hover:not(:disabled) { background-color: #475569; }
    .pagination-display button:disabled { opacity: 0.5; cursor: not-allowed; }
    .pagination-display .page-info-display { margin: 0 1rem; font-size: 0.875rem; color: #475569; }
    .dark .pagination-display .page-info-display { color: #cbd5e1; }

    #editModalDisplay { /* Nama ID unik */
        /* Style mirip dengan #editModal di file asli, pastikan Bootstrap CSS menghandle ini atau sesuaikan */
        /* Jika menggunakan class .modal .fade dari Bootstrap, ini tidak perlu di-style manual untuk visibilitas */
    }
    .status-lunas { color: #10b981; font-weight: 600; } /* Tailwind green-500 */
    .dark .status-lunas { color: #34d399; } /* Tailwind green-400 */
    .status-hutang { color: #f59e0b; font-weight: 600; } /* Tailwind amber-500 */
    .dark .status-hutang { color: #fbbf24; } /* Tailwind amber-400 */
    .status-lain { color: #64748b; } /* Tailwind slate-500 */
    .dark .status-lain { color: #94a3b8; } /* Tailwind slate-400 */
</style>

<div class="p-0 md:p-2"> <header class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 dark:text-white">Data Pembelian Paket</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Riwayat dan Rekap Data Transaksi Paket XL/AXIS.</p>
    </header>

    <?php if (!empty($error_config_msg)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 dark:bg-red-900/30 dark:border-red-600 dark:text-red-300 rounded-md" role="alert">
            <strong class="font-bold">Kesalahan Konfigurasi!</strong>
            <p><?php echo htmlspecialchars($error_config_msg); ?></p>
        </div>
    <?php endif; ?>

    <div class="mb-6">
        <input type="text" id="searchInputDisplay" placeholder="Cari berdasarkan nama pembeli, nomor HP, atau jenis paket..."
               class="w-full p-3 rounded-lg border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-primary dark:focus:ring-gold bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 shadow-sm">
    </div>

    <div class="bg-white dark:bg-slate-800 p-4 sm:p-6 rounded-xl shadow-xl mb-8">
        <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-4 text-center sm:text-left">Rekap Data Keseluruhan</h3>
        <div class="table-responsive-display rekap-table"> <table class="table w-full">
                <thead>
                    <tr>
                        <th>Total Harga Jual</th>
                        <th>Total Harga Modal</th>
                        <th>Total Untung (<span id="totalDataCountDisplay"><?php echo $totalData; ?></span> Transaksi)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td id="totalHargaJualDisplay" data-title="Total Harga Jual"><?php echo formatRupiah($total_harga_jual); ?></td>
                        <td id="totalHargaModalDisplay" data-title="Total Harga Modal"><?php echo formatRupiah($total_harga_modal); ?></td>
                        <td id="totalUntungDisplay" data-title="Total Untung"><?php echo formatRupiah($total_untung); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (empty($error_config_msg) && !empty($data_arr)): ?>
        <div class="table-responsive-display bg-white dark:bg-slate-800 p-0 sm:p-2 rounded-xl shadow-xl">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Masa Aktif</th>
                        <th>Jenis Paket</th>
                        <th>Nomor HP</th>
                        <th>Pembeli</th>
                        <th>Jual</th>
                        <th>Modal</th>
                        <th>Seller</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="dataBodyDisplay">
                    </tbody>
            </table>
        </div>
        <div id="paginationControlsDisplay" class="pagination-display">
            <button id="prevPageBtnDisplay" disabled><i class="fas fa-chevron-left"></i> Sebelumnya</button>
            <span id="pageInfoDisplay" class="page-info-display">Halaman 1 dari 1</span>
            <button id="nextPageBtnDisplay" disabled>Selanjutnya <i class="fas fa-chevron-right"></i></button>
        </div>
    <?php elseif (empty($error_config_msg)): ?>
        <div class="text-center text-slate-500 dark:text-slate-400 mt-8 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
            <i class="fas fa-folder-open fa-3x mb-3"></i>
            <p>Tidak ada data transaksi untuk ditampilkan.</p>
            <p class="text-sm mt-1">Anda bisa menambahkan data baru melalui menu "Add Data".</p>
        </div>
    <?php endif; ?>
</div> <div class="modal fade" id="editModalDisplay" tabindex="-1" aria-labelledby="editModalLabelDisplay" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"> <div class="modal-content bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-xl">
            <div class="modal-header border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <h5 class="modal-title text-xl font-semibold" id="editModalLabelDisplay">Edit Data Paket</h5>
                <button type="button" class="btn-close text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" data-bs-dismiss="modal" aria-label="Close">
                     </button>
            </div>
            <div class="modal-body px-6 py-5">
                <form id="editFormDisplay">
                    <input type="hidden" id="editNomorDisplay" name="nomor">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="editTanggalDisplay" class="block text-sm font-medium mb-1">Tanggal</label>
                            <input type="date" id="editTanggalDisplay" name="tanggal" class="form-control w-full" required>
                        </div>
                        <div>
                            <label for="editJenisPaketDisplay" class="block text-sm font-medium mb-1">Jenis Paket</label>
                            <input type="text" id="editJenisPaketDisplay" name="jenis_paket" class="form-control w-full" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="editNomorHPDisplay" class="block text-sm font-medium mb-1">Nomor HP</label>
                            <input type="text" id="editNomorHPDisplay" name="nomor_hp" class="form-control w-full" required>
                        </div>
                        <div>
                            <label for="editNomorWADisplay" class="block text-sm font-medium mb-1">Nomor WA</label>
                            <input type="text" id="editNomorWADisplay" name="nomor_wa" class="form-control w-full" readonly>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="editNamaPembeliDisplay" class="block text-sm font-medium mb-1">Nama Pembeli</label>
                        <input type="text" id="editNamaPembeliDisplay" name="nama_pembeli" class="form-control w-full" readonly>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="editHargaJualDisplay" class="block text-sm font-medium mb-1">Harga Jual</label>
                            <input type="number" id="editHargaJualDisplay" name="harga_jual" class="form-control w-full" required min="0">
                        </div>
                        <div>
                            <label for="editHargaModalDisplay" class="block text-sm font-medium mb-1">Harga Modal</label>
                            <input type="number" id="editHargaModalDisplay" name="harga_modal" class="form-control w-full" required min="0">
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="editNamaSellerDisplay" class="block text-sm font-medium mb-1">Nama Seller</label>
                            <input type="text" id="editNamaSellerDisplay" name="nama_seller" class="form-control w-full" required>
                        </div>
                        <div>
                            <label for="editStatusPembayaranDisplay" class="block text-sm font-medium mb-1">Status Pembayaran</label>
                            <select id="editStatusPembayaranDisplay" name="status_pembayaran" class="form-select w-full" required>
                                <option value="LUNAS">LUNAS</option>
                                <option value="HUTANG">HUTANG</option>
                                <option value="Pending">Pending</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-t border-slate-200 dark:border-slate-700 px-6 py-4 flex flex-wrap justify-between items-center gap-2">
                <div class="flex gap-2">
                     <button type="button" id="deleteDataBtnDisplay" class="btn btn-danger text-sm">
                        <i class="fas fa-trash-alt mr-1"></i> Hapus
                    </button>
                    <button type="button" class="btn btn-secondary text-sm" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                </div>
                <button type="button" id="saveChangesBtnDisplay" class="btn btn-primary text-sm">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>

<div id="saveStatusMessageDisplay" class="fixed bottom-5 right-5 z-[1060] px-5 py-3 rounded-lg text-sm font-semibold text-white shadow-xl hidden animate-pulse">
</div>


<script>
function initializeDisplayData() {
    if (window.displayDataInitialized && $('#dataBodyDisplay').data('initialized')) {
        console.log("Display data page already initialized. Re-checking modal.");
        const editModalElementRecheck = document.getElementById('editModalDisplay');
        if (editModalElementRecheck && typeof bootstrap !== 'undefined' && !bootstrap.Modal.getInstance(editModalElementRecheck)) {
             try {
                if (window.editModalInstanceDisplay && typeof window.editModalInstanceDisplay.dispose === 'function') {
                    window.editModalInstanceDisplay.dispose();
                }
                window.editModalInstanceDisplay = new bootstrap.Modal(editModalElementRecheck);
                console.log("Bootstrap Modal #editModalDisplay re-initialized (check).");
            } catch (e) { console.error("Error re-initializing #editModalDisplay (check):", e); }
        }
        return;
    }
    console.log("Initializing display data page script...");

    let allPaketDataDisplay = <?php echo $jsonData; ?>;
    let currentFileShaDisplay = <?php echo $jsonSha; ?>;
    const jsConfigDisplay = <?php echo $jsConfig; ?>; // Konfigurasi GitHub

    // PENTING: Jangan pernah hardcode PAT di JavaScript sisi klien untuk produksi.
    // Ini hanya untuk contoh dan harus diganti dengan mekanisme aman.
    const GITHUB_PAT_DISPLAY = jsConfigDisplay.github_pat;
    const GITHUB_API_FILE_URL_DISPLAY = `https://api.github.com/repos/${jsConfigDisplay.repo_owner}/${jsConfigDisplay.repo_name}/contents/${jsConfigDisplay.file_path}`;

    const itemsPerPageDisplay = 20;
    let currentPageDisplay = 1;
    let filteredDataDisplay = [];

    const dataBody = document.getElementById('dataBodyDisplay');
    const searchInput = document.getElementById('searchInputDisplay');
    const prevPageBtn = document.getElementById('prevPageBtnDisplay');
    const nextPageBtn = document.getElementById('nextPageBtnDisplay');
    const pageInfoSpan = document.getElementById('pageInfoDisplay');
    const totalHargaJualSpan = document.getElementById('totalHargaJualDisplay');
    const totalHargaModalSpan = document.getElementById('totalHargaModalDisplay');
    const totalUntungSpan = document.getElementById('totalUntungDisplay');
    const totalDataCountSpan = document.getElementById('totalDataCountDisplay');
    const saveStatusMessageDiv = document.getElementById('saveStatusMessageDisplay');

    const editModalElement = document.getElementById('editModalDisplay');
    let editModalInstanceDisplay = null;
    if (editModalElement && typeof bootstrap !== 'undefined') {
        try {
            if (bootstrap.Modal.getInstance(editModalElement)) {
                bootstrap.Modal.getInstance(editModalElement).dispose();
            }
            editModalInstanceDisplay = new bootstrap.Modal(editModalElement);
            window.editModalInstanceDisplay = editModalInstanceDisplay; // Store globally
            console.log("Bootstrap Modal #editModalDisplay initialized.");
        } catch (e) { console.error("Error initializing #editModalDisplay:", e); }
    }


    const editForm = document.getElementById('editFormDisplay');
    // ... (lanjutan deklarasi elemen form modal edit dengan suffix -Display)
    const editNomorInput = document.getElementById('editNomorDisplay');
    const editTanggalInput = document.getElementById('editTanggalDisplay');
    const editJenisPaketInput = document.getElementById('editJenisPaketDisplay');
    const editNomorHPInput = document.getElementById('editNomorHPDisplay');
    const editNomorWAInput = document.getElementById('editNomorWADisplay');
    const editNamaPembeliInput = document.getElementById('editNamaPembeliDisplay');
    const editHargaJualInput = document.getElementById('editHargaJualDisplay');
    const editHargaModalInput = document.getElementById('editHargaModalDisplay');
    const editNamaSellerInput = document.getElementById('editNamaSellerDisplay');
    const editStatusPembayaranSelect = document.getElementById('editStatusPembayaranDisplay');


    function showStatusMessageDisplay(message, type = 'success', duration = 5000) {
        saveStatusMessageDiv.textContent = message;
        saveStatusMessageDiv.className = 'fixed bottom-5 right-5 z-[1060] px-5 py-3 rounded-lg text-sm font-semibold text-white shadow-xl hidden'; // Reset classes
        saveStatusMessageDiv.classList.remove('hidden');
        if (type === 'success') saveStatusMessageDiv.classList.add('bg-green-500');
        else if (type === 'error') saveStatusMessageDiv.classList.add('bg-red-500');
        else saveStatusMessageDiv.classList.add('bg-blue-500');
        
        setTimeout(() => { saveStatusMessageDiv.classList.add('hidden'); }, duration);
    }

    function formatRupiahDisplay(angka) {
        const number = parseFloat(angka);
        if (isNaN(number)) return 'Rp 0';
        return 'Rp ' + number.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function formatTanggalDisplay(tanggal) { /* ... implementasi sama ... */ 
        if (!tanggal) return '';
        const bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        try {
            const date = new Date(tanggal);
            if (isNaN(date.getTime())) return tanggal; // Kembalikan tanggal asli jika tidak valid
            const dateNum = date.getDate();
            const monthName = bulan[date.getMonth() + 1];
            const year = date.getFullYear();
            return `${dateNum} ${monthName} ${year}`;
        } catch (e) { return tanggal; }
    }

    function hitunganMundurDisplay(tanggal) { /* ... implementasi sama ... */ 
        if (!tanggal) return '';
        const dateNow = new Date();
        const dateTarget = new Date(tanggal);
        if (isNaN(dateTarget.getTime())) return '';
        const dateTargetEnd = new Date(dateTarget);
        dateTargetEnd.setDate(dateTargetEnd.getDate() + 29);
        const timeDiff = dateTargetEnd.getTime() - dateNow.getTime();
        const daysLeft = Math.ceil(timeDiff / (1000 * 3600 * 24));
        if (daysLeft < 0) return '<span class="text-red-500 font-semibold">Expired</span>';
        else if (daysLeft <= 7) return `<span class="text-orange-500 font-semibold">${daysLeft} hari</span> <span class="text-xs text-yellow-500">[Segera Expired]</span>`;
        else return `<span class="text-green-600 font-semibold">${daysLeft} hari</span> <span class="text-xs text-green-500">[Active]</span>`;
    }

    function renderTableDisplay(dataToDisplay) {
        if (!dataBody) { console.error("#dataBodyDisplay not found"); return; }
        dataBody.innerHTML = '';
        const startIndex = (currentPageDisplay - 1) * itemsPerPageDisplay;
        const endIndex = startIndex + itemsPerPageDisplay;
        const paginatedData = dataToDisplay.slice(startIndex, endIndex);

        if (paginatedData.length === 0) {
            dataBody.innerHTML = '<tr><td colspan="10" class="text-center py-6 text-slate-500 dark:text-slate-400">Tidak ada data yang cocok.</td></tr>';
            return;
        }

        paginatedData.forEach((data, index) => {
            const row = document.createElement('tr');
            row.className = (startIndex + index) % 2 === 0 ? 'bg-white dark:bg-slate-900/50' : 'bg-slate-50 dark:bg-slate-800/50';
            row.className += ' hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors';
            
            const dataId = data.nomor !== undefined ? parseInt(data.nomor, 10) : null;
            if (dataId !== null) row.setAttribute('data-id', dataId);

            let statusClass = 'status-lain';
            const status = data.status_pembayaran !== undefined ? String(data.status_pembayaran).toUpperCase() : '';
            if (status === 'LUNAS') statusClass = 'status-lunas';
            else if (status === 'HUTANG') statusClass = 'status-hutang';

            row.innerHTML = `
                <td data-title="No">${data.nomor ?? ''}</td>
                <td data-title="Tanggal">${formatTanggalDisplay(data.tanggal)}</td>
                <td data-title="Masa Aktif">${hitunganMundurDisplay(data.tanggal)}</td>
                <td data-title="Jenis Paket">${data.jenis_paket ?? ''}</td>
                <td data-title="Nomor HP">${data.nomor_hp ?? ''}</td>
                <td data-title="Pembeli">${data.nama_pembeli ?? ''}</td>
                <td data-title="Jual">${formatRupiahDisplay(data.harga_jual)}</td>
                <td data-title="Modal" class="${(parseFloat(data.harga_modal) > parseFloat(data.harga_jual)) ? 'text-red-500 dark:text-red-400' : ''}">${formatRupiahDisplay(data.harga_modal)}</td>
                <td data-title="Seller">${data.nama_seller ?? ''}</td>
                <td data-title="Status" class="${statusClass}">${data.status_pembayaran ?? ''}</td>
            `;
            if (dataId !== null) {
                row.addEventListener('click', () => {
                    const clickedDataId = parseInt(row.getAttribute('data-id'), 10);
                    const dataToEdit = allPaketDataDisplay.find(item => Number(item.nomor) === clickedDataId);
                    if (dataToEdit) showEditModalDisplay(dataToEdit);
                    else console.error("Data not found for ID:", clickedDataId);
                });
            }
            dataBody.appendChild(row);
        });
    }

    function updateRekapDisplay(dataToCalculate) { /* ... implementasi sama ... */ 
        let totalJual = 0, totalModal = 0;
        dataToCalculate.forEach(d => { totalJual += parseFloat(d.harga_jual || 0); totalModal += parseFloat(d.harga_modal || 0); });
        totalHargaJualSpan.textContent = formatRupiahDisplay(totalJual);
        totalHargaModalSpan.textContent = formatRupiahDisplay(totalModal);
        totalUntungSpan.textContent = formatRupiahDisplay(totalJual - totalModal);
        if(totalDataCountSpan) totalDataCountSpan.textContent = dataToCalculate.length;
    }

    function updatePaginationControlsDisplay(dataLength) { /* ... implementasi sama ... */ 
        const totalPages = Math.ceil(dataLength / itemsPerPageDisplay);
        if(pageInfoSpan) pageInfoSpan.textContent = `Halaman ${currentPageDisplay} dari ${Math.max(totalPages,1)}`;
        if(prevPageBtn) prevPageBtn.disabled = currentPageDisplay === 1;
        if(nextPageBtn) nextPageBtn.disabled = currentPageDisplay === totalPages || totalPages === 0;
    }
    
    function goToPageDisplay(page) { /* ... implementasi sama ... */ 
        const totalPages = Math.ceil(filteredDataDisplay.length / itemsPerPageDisplay);
        const targetPage = Math.max(1, Math.min(page, totalPages > 0 ? totalPages : 1));
        if (currentPageDisplay !== targetPage) {
            currentPageDisplay = targetPage;
            renderTableDisplay(filteredDataDisplay);
            updatePaginationControlsDisplay(filteredDataDisplay.length);
        }
    }

    if(prevPageBtn) prevPageBtn.addEventListener('click', () => goToPageDisplay(currentPageDisplay - 1));
    if(nextPageBtn) nextPageBtn.addEventListener('click', () => goToPageDisplay(currentPageDisplay + 1));

    function applySearchFilterDisplay() { /* ... implementasi sama ... */ 
        const searchText = searchInput.value.toLowerCase();
        filteredDataDisplay = allPaketDataDisplay.filter(data => 
            Object.values(data).some(val => String(val).toLowerCase().includes(searchText))
        );
        currentPageDisplay = 1;
        renderTableDisplay(filteredDataDisplay);
        updatePaginationControlsDisplay(filteredDataDisplay.length);
        updateRekapDisplay(filteredDataDisplay);
    }
    if(searchInput) searchInput.addEventListener('input', applySearchFilterDisplay);

    function showEditModalDisplay(data) { /* ... implementasi sama, sesuaikan ID elemen form ... */ 
        if (!data || !editModalInstanceDisplay) return;
        $('#editModalLabelDisplay').text('Edit Data Paket #' + (data.nomor ?? ''));
        $(editNomorInput).val(data.nomor ?? '');
        $(editTanggalInput).val(data.tanggal ?? '');
        $(editJenisPaketInput).val(data.jenis_paket ?? '');
        $(editNomorHPInput).val(data.nomor_hp ?? '');
        $(editNomorWAInput).val(data.nomor_wa ?? '');
        $(editNamaPembeliInput).val(data.nama_pembeli ?? '');
        $(editHargaJualInput).val(data.harga_jual ?? '');
        $(editHargaModalInput).val(data.harga_modal ?? '');
        $(editNamaSellerInput).val(data.nama_seller ?? '');
        $(editStatusPembayaranSelect).val(data.status_pembayaran ?? '');
        editModalInstanceDisplay.show();
    }

    async function saveAllDataToGithubDisplay(dataToSave) { /* ... implementasi sama ... */ 
        showStatusMessageDisplay("Menyimpan perubahan...", 'info');
        const jsonDataString = JSON.stringify(dataToSave, null, 2);
        const base64Content = btoa(unescape(encodeURIComponent(jsonDataString)));
        const editedItemNomor = editNomorInput.value ? `#${editNomorInput.value}` : 'batch update';
        const commitMessage = `Update data from admin panel for item ${editedItemNomor}`;
        const requestBody = { message: commitMessage, content: base64Content, sha: currentFileShaDisplay, branch: jsConfigDisplay.branch };

        try {
            const response = await fetch(GITHUB_API_FILE_URL_DISPLAY, {
                method: 'PUT',
                headers: { 'Authorization': `token ${GITHUB_PAT_DISPLAY}`, 'Accept': 'application/vnd.github.v3+json', 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody),
            });
            const responseData = await response.json();
            if (response.ok) {
                showStatusMessageDisplay('Perubahan berhasil disimpan di GitHub.', 'success');
                currentFileShaDisplay = responseData.content.sha; // Update SHA
                // Data lokal sudah diupdate, re-render untuk konsistensi
                applySearchFilterDisplay(); // Ini akan re-render dan update rekap
            } else {
                showStatusMessageDisplay(`Gagal menyimpan: ${responseData.message || response.statusText}`, 'error');
                // Pertimbangkan untuk reload atau fetch ulang data dari GitHub jika ada konflik SHA
                // Contoh: if (response.status === 409) { fetch fresh data }
            }
        } catch (error) {
            showStatusMessageDisplay(`Error koneksi: ${error.message}`, 'error');
        }
    }
    
    // Handler untuk tombol Simpan di modal
    $('#saveChangesBtnDisplay').off('click.display').on('click.display', function() {
        const updatedData = {
            nomor: parseInt($(editNomorInput).val(), 10),
            tanggal: $(editTanggalInput).val(),
            jenis_paket: $(editJenisPaketInput).val().trim(),
            nomor_hp: $(editNomorHPInput).val().trim(),
            nomor_wa: $(editNomorWAInput).val().trim(),
            nama_pembeli: $(editNamaPembeliInput).val().trim(),
            harga_jual: parseFloat($(editHargaJualInput).val()),
            harga_modal: parseFloat($(editHargaModalInput).val()),
            nama_seller: $(editNamaSellerInput).val().trim(),
            status_pembayaran: $(editStatusPembayaranSelect).val(),
        };
        // Validasi sederhana
        if (isNaN(updatedData.nomor) || !updatedData.tanggal || !updatedData.jenis_paket) {
            showStatusMessageDisplay("Data tidak lengkap atau format salah.", 'error');
            return;
        }
        const dataIndex = allPaketDataDisplay.findIndex(d => Number(d.nomor) === updatedData.nomor);
        if (dataIndex !== -1) {
            allPaketDataDisplay[dataIndex] = updatedData;
            saveAllDataToGithubDisplay(allPaketDataDisplay);
            if(editModalInstanceDisplay) editModalInstanceDisplay.hide();
        } else {
            showStatusMessageDisplay("Data tidak ditemukan untuk diupdate.", 'error');
        }
    });

    // Handler untuk tombol Hapus di modal
    $('#deleteDataBtnDisplay').off('click.display').on('click.display', function() {
        const nomorToDelete = parseInt($(editNomorInput).val(), 10);
        if (isNaN(nomorToDelete)) { showStatusMessageDisplay("Nomor data tidak valid.", 'error'); return; }
        if (confirm(`Yakin ingin menghapus data dengan Nomor Urut ${nomorToDelete}?`)) {
            allPaketDataDisplay = allPaketDataDisplay.filter(d => Number(d.nomor) !== nomorToDelete);
            saveAllDataToGithubDisplay(allPaketDataDisplay);
            if(editModalInstanceDisplay) editModalInstanceDisplay.hide();
        }
    });


    // Inisialisasi data awal
    if (typeof allPaketDataDisplay !== 'undefined' && Array.isArray(allPaketDataDisplay)) {
        filteredDataDisplay = [...allPaketDataDisplay];
        renderTableDisplay(filteredDataDisplay);
        updatePaginationControlsDisplay(filteredDataDisplay.length);
        updateRekapDisplay(filteredDataDisplay);
    } else {
        console.warn("allPaketDataDisplay is not defined or not an array. Cannot initialize table.");
        allPaketDataDisplay = []; // Pastikan array kosong jika tidak ada data
        filteredDataDisplay = [];
        renderTableDisplay([]);
        updatePaginationControlsDisplay(0);
        updateRekapDisplay([]);
    }
    
    window.displayDataInitialized = true;
    $('#dataBodyDisplay').data('initialized', true);
    console.log("Display data page script fully initialized and marked.");
}

// Panggil fungsi inisialisasi
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initializeDisplayData();
} else {
    $(document).ready(initializeDisplayData);
}
</script>
