    <?php

    // Set header untuk memberitahu klien bahwa respons adalah JSON
    header('Content-Type: application/json');

    // Sertakan file helpers.php untuk mengakses fungsi-fungsi bantuan
    require_once __DIR__ . '/helpers.php';

    // Muat pengaturan dari file konfigurasi
    $settings = load_settings();

    // --- Validasi Pengaturan GitHub ---
    if (
        empty($settings['github_api_token']) ||
        empty($settings['github_repo_owner']) ||
        empty($settings['github_repo_name']) ||
        empty($settings['github_data_file']) ||
        empty($settings['github_branch'])
    ) {
        error_log("Pengaturan GitHub tidak lengkap dalam file konfigurasi.");
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'error' => 'Konfigurasi server tidak lengkap. Hubungi administrator.']);
        exit;
    }

    // Gunakan pengaturan yang dimuat
    $api_token = $settings['github_api_token'];
    $repo_owner = $settings['github_repo_owner'];
    $repo_name = $settings['github_repo_name'];
    $file_path = $settings['github_data_file']; // Gunakan path dari settings
    $branch = $settings['github_branch'];

    $github_api_url = "https://api.github.com/repos/{$repo_owner}/{$repo_name}/contents/{$file_path}";

    // Pastikan request method adalah POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan.']);
        exit;
    }

    // Ambil data dari POST request dan bersihkan (trim)
    $new_data_entry = [
        'nomor' => trim($_POST['nomor'] ?? ''),
        'tanggal' => trim($_POST['tanggal'] ?? ''),
        'jenis_paket' => trim($_POST['jenis_paket'] ?? ''),
        'nomor_hp' => trim($_POST['nomor_hp'] ?? ''),
        'nama_pembeli' => trim($_POST['nama_pembeli'] ?? ''),
        'nomor_wa' => trim($_POST['nomor_wa'] ?? ''), // Nomor WA mungkin kosong
        'harga_jual' => trim($_POST['harga_jual'] ?? ''),
        'harga_modal' => trim($_POST['harga_modal'] ?? ''),
        'nama_seller' => trim($_POST['nama_seller'] ?? ''),
        'status_pembayaran' => trim($_POST['status_pembayaran'] ?? ''),
    ];

    // --- Validasi Data Input ---
    $required_fields = ['nomor', 'tanggal', 'jenis_paket', 'nomor_hp', 'nama_pembeli', 'harga_jual', 'harga_modal', 'nama_seller', 'status_pembayaran'];
    $errors = [];
    foreach ($required_fields as $field) {
        // Khusus nomor_wa boleh kosong
        if ($field === 'nomor_wa') continue;

        if (empty($new_data_entry[$field])) {
            $errors[] = "Field '{$field}' tidak boleh kosong.";
        }
    }
    // Validasi numerik
    if (!empty($new_data_entry['harga_jual']) && !is_numeric($new_data_entry['harga_jual'])) {
         $errors[] = "Field 'harga_jual' harus berupa angka.";
    } elseif (isset($new_data_entry['harga_jual']) && is_numeric($new_data_entry['harga_jual']) && $new_data_entry['harga_jual'] < 0) {
         $errors[] = "Field 'harga_jual' tidak boleh negatif.";
    }
    if (!empty($new_data_entry['harga_modal']) && !is_numeric($new_data_entry['harga_modal'])) {
         $errors[] = "Field 'harga_modal' harus berupa angka.";
    } elseif (isset($new_data_entry['harga_modal']) && is_numeric($new_data_entry['harga_modal']) && $new_data_entry['harga_modal'] < 0) {
         $errors[] = "Field 'harga_modal' tidak boleh negatif.";
    }
    // Validasi format nomor HP (contoh: minimal 5 digit, bisa ada + - spasi)
    if (!empty($new_data_entry['nomor_hp']) && !preg_match('/^[0-9\s\-\+]{5,}$/', $new_data_entry['nomor_hp'])) {
         $errors[] = "Format 'nomor_hp' tidak valid.";
    }
     // Validasi nomor WA jika diisi
     if (!empty($new_data_entry['nomor_wa']) && !preg_match('/^[0-9\s\-\+]{5,}$/', $new_data_entry['nomor_wa'])) {
         $errors[] = "Format 'nomor_wa' tidak valid.";
     }


    if (!empty($errors)) {
         http_response_code(400); // Bad Request
         // Gabungkan error menjadi satu string dengan pemisah baris baru
         echo json_encode(['success' => false, 'error' => implode("\n", $errors)]);
         exit;
    }


    // --- Langkah 1: Ambil konten data.json yang ada dan SHA-nya dari GitHub ---
    $ch_fetch = curl_init();
    // Tambah timestamp untuk sedikit membantu bypass cache GitHub API
    $fetch_url = $github_api_url . "?ref=" . $branch . "&t=" . time();
    curl_setopt($ch_fetch, CURLOPT_URL, $fetch_url);
    curl_setopt($ch_fetch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_fetch, CURLOPT_TIMEOUT, 20); // Tingkatkan timeout fetch
    curl_setopt($ch_fetch, CURLOPT_HTTPHEADER, [
        "Authorization: token {$api_token}",
        "User-Agent: PHP-SubmitFetch",
        "Accept: application/vnd.github.v3+json"
    ]);
    // curl_setopt($ch_fetch, CURLOPT_SSL_VERIFYPEER, false); // Uncomment jika perlu
    // curl_setopt($ch_fetch, CURLOPT_SSL_VERIFYHOST, false); // Uncomment jika perlu

    $response_fetch = curl_exec($ch_fetch);
    $http_status_fetch = curl_getinfo($ch_fetch, CURLINFO_HTTP_CODE);
    $curl_error_fetch = curl_error($ch_fetch);
    curl_close($ch_fetch);

    $existing_data = [];
    $current_sha = null;

    if ($curl_error_fetch) {
         error_log("cURL Error fetching {$file_path}: " . $curl_error_fetch);
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'Gagal menghubungi GitHub (Fetch Error). Coba lagi nanti.']);
         exit;
    }

    if ($http_status_fetch === 200) {
        $data = json_decode($response_fetch, true);
        if (isset($data['content']) && isset($data['sha'])) {
            $json_data_content = base64_decode($data['content']);
            $decoded_data = json_decode($json_data_content, true);
            if (is_array($decoded_data)) {
                $existing_data = $decoded_data;
            } else {
                error_log("Konten {$file_path} dari GitHub bukan JSON valid (Status: 200). Memulai dengan array kosong.");
                $existing_data = []; // Anggap kosong jika decode gagal
            }
            $current_sha = $data['sha'];
        } else {
             error_log("GitHub API response for fetching {$file_path} is missing content or sha: " . $response_fetch);
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => 'Gagal membaca format file dari GitHub (Invalid Response).']);
             exit;
        }
    } elseif ($http_status_fetch === 404) {
        error_log("{$file_path} not found on GitHub (Status: 404). Creating a new file.");
        $existing_data = [];
        $current_sha = null;
    } else {
        error_log("Gagal fetch {$file_path} from GitHub. Status: {$http_status_fetch}, Response: {$response_fetch}");
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Gagal mengambil data dari GitHub (Status: {$http_status_fetch})."]);
        exit;
    }

    // --- Langkah 2: Tambahkan data baru ke array ---
    array_unshift($existing_data, $new_data_entry);

    // --- Langkah 3: Encode data kembali ke JSON ---
    $updated_json_content = json_encode($existing_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($updated_json_content === false) {
         error_log("Gagal encode data ke JSON: " . json_last_error_msg());
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'Gagal memproses data internal (JSON Encode).']);
         exit;
    }
    $updated_base64_content = base64_encode($updated_json_content);

    // --- Langkah 4: Siapkan payload untuk update/create file di GitHub ---
    $commit_message = 'Tambah data nomor ' . $new_data_entry['nomor'] . ' (' . $new_data_entry['nama_pembeli'] . ')';
    $payload = [
        'message' => $commit_message,
        'content' => $updated_base64_content,
        'branch' => $branch,
    ];
    if ($current_sha !== null) {
        $payload['sha'] = $current_sha;
    }

    $payload_json = json_encode($payload);
    if ($payload_json === false) {
         error_log("Gagal encode payload ke JSON: " . json_last_error_msg());
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'Gagal memproses data internal (Payload Encode).']);
         exit;
    }

    // --- Langkah 5: Kirim PUT request ke GitHub API ---
    $ch_update = curl_init();
    curl_setopt($ch_update, CURLOPT_URL, $github_api_url);
    curl_setopt($ch_update, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch_update, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($ch_update, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_update, CURLOPT_TIMEOUT, 30); // Tingkatkan timeout update
    curl_setopt($ch_update, CURLOPT_HTTPHEADER, [
        "Authorization: token {$api_token}",
        "User-Agent: PHP-SubmitUpdate",
        "Content-Type: application/json",
        "Accept: application/vnd.github.v3+json"
    ]);
    // curl_setopt($ch_update, CURLOPT_SSL_VERIFYPEER, false); // Uncomment jika perlu
    // curl_setopt($ch_update, CURLOPT_SSL_VERIFYHOST, false); // Uncomment jika perlu

    $response_update = curl_exec($ch_update);
    $http_status_update = curl_getinfo($ch_update, CURLINFO_HTTP_CODE);
    $curl_error_update = curl_error($ch_update);
    curl_close($ch_update);

    // --- Langkah 6: Tangani respons dari GitHub API ---
    if ($curl_error_update) {
         error_log("cURL Error updating {$file_path}: " . $curl_error_update);
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'Gagal menghubungi GitHub saat menyimpan (Update Error). Coba lagi nanti.']);
         exit;
    }

    if ($http_status_update === 200 || $http_status_update === 201) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan ke GitHub.']);
    } else {
        $error_message = "Gagal menyimpan data ke GitHub (Status: {$http_status_update}).";
        $response_data = json_decode($response_update, true);
        if (isset($response_data['message'])) {
            $error_message .= " Pesan API: " . $response_data['message'];
            if (strpos($response_data['message'], 'sha') !== false && $http_status_update === 409) {
                 $error_message .= " Kemungkinan terjadi konflik data. Coba refresh halaman input dan submit ulang.";
            }
        }
        error_log("GitHub API update failed. Status: {$http_status_update}, Response: {$response_update}");
        http_response_code(500); // Atau sesuaikan
        echo json_encode(['success' => false, 'error' => $error_message]);
    }

    ?>
    