<?php
// Mulai session. Ini harus ada di awal file PHP yang menggunakan session.
session_start();

// --- Konfigurasi ---
$correct_pin = "0000"; // Ganti dengan PIN angka yang Anda inginkan
$redirect_url = "/display"; // URL yang akan ditampilkan di browser setelah berhasil (gunakan URL yang Anda buat di .htaccess)
// ------------------

// Variabel untuk pesan error
$error_message = "";

// Cek apakah PIN sudah tersimpan di session (sudah terautentikasi sebelumnya)
if (isset($_SESSION['pin_authenticated']) && $_SESSION['pin_authenticated'] === true) {
    // Jika sudah terautentikasi, tampilkan konten file paket.php
    // Konten file paket.php dimulai dari sini:
?>
<?php
// display.php - Menampilkan dan mengedit data paket XL/AXIS langsung ke data.json di GitHub API (TIDAK AMAN UNTUK PUBLIK)

// Sertakan file function.php yang berisi fungsi-fungsi helper
require_once 'function.php';

// === BACA DATA DARI GITHUB ===
// Perhatian: Menyimpan API token langsung di kode PHP yang diakses web
// sangat tidak disarankan untuk keamanan. Gunakan variabel lingkungan atau
// cara yang lebih aman di lingkungan produksi.
// Token ini hanya untuk demo dan harus diganti atau dihapus di lingkungan produksi.
$api_token = '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'; // Ganti dengan token Anda
$repo_owner = 'andrias97'; // Ganti dengan username pemilik repo
$repo_name = 'databasepaket'; // Ganti dengan nama repo
$file_path = 'data.json'; // Path ke file data.json di repo
$branch = 'main'; // Branch yang digunakan
$github_api_url = "https://api.github.com/repos/$repo_owner/$repo_name/contents/$file_path?ref=$branch";

$ch = curl_init($github_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-cURL'); // User-Agent diperlukan oleh GitHub API
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: token $api_token",
    "Accept: application/vnd.github.v3+json" // Minta format JSON v3
]);
$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data_arr = [];
$file_sha = null; // Variabel untuk menyimpan SHA file

if ($http_status === 200) {
    $response_data = json_decode($response, true);
    // Pastikan 'content' dan 'sha' ada dan bukan null sebelum decode base64
    if (isset($response_data['content']) && $response_data['content'] !== null && isset($response_data['sha']) && $response_data['sha'] !== null) {
        $json_data = base64_decode($response_data['content']);
        $data_arr = json_decode($json_data, true);
         // Pastikan hasil decode JSON adalah array
         if (!is_array($data_arr)) {
             $data_arr = []; // Set ke array kosong jika decode gagal atau bukan array
         }
         $file_sha = $response_data['sha']; // Simpan SHA file
    } else {
         // Handle kasus jika 'content' atau 'sha' tidak ada atau null
         error_log("GitHub API response content or sha is missing or null for $file_path");
         $data_arr = [];
    }
} else {
    // Handle error response dari GitHub API
    error_log("Failed to fetch data from GitHub API. Status: $http_status, Response: $response");
    $data_arr = [];
}

// Urutkan data berdasarkan nomor secara descending
usort($data_arr, function($a, $b) {
    // Pastikan 'nomor' ada di kedua elemen sebelum membandingkan
    $nomor_a = isset($a['nomor']) ? (int)$a['nomor'] : 0;
    $nomor_b = isset($b['nomor']) ? (int)$b['nomor'] : 0;
    return $nomor_b - $nomor_a;
});


// Hitung total rekap (ini akan dihitung ulang di JS setelah filtering)
$total_harga_jual = $total_harga_modal = $total_untung = 0;
$totalData = count($data_arr); // Total jumlah data
foreach ($data_arr as $data) {
     // Pastikan kunci ada sebelum mengaksesnya
    $harga_jual = isset($data['harga_jual']) ? (float)$data['harga_jual'] : 0;
    $harga_modal = isset($data['harga_modal']) ? (float)$data['harga_modal'] : 0;

    $total_harga_jual += $harga_jual;
    $total_harga_modal += $harga_modal;
    $total_untung += ($harga_jual - $harga_modal);
}

// Data dan SHA yang akan dilewatkan ke JavaScript
$jsonData = json_encode($data_arr);
$jsonSha = json_encode($file_sha); // Encode SHA untuk dilewatkan ke JS

?>


<!DOCTYPE html>
<html lang="id" class="scroll-smooth transition-colors duration-500">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Data Paket XL/AXIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#ee4d2d', // Warna utama Shopee
                        primaryLight: '#FF6F61',
                        gold: '#FFD700',
                        surface: '#F9FAFB',
                        gray: '#f5f5f5',
                        darkGray: '#333333',
                    },
                    boxShadow: {
                        'glow-primary': '0 4px 12px rgba(238, 77, 45, 0.3)',
                        'glow-gold': '0 4px 12px rgba(255, 215, 0, 0.3)',
                        'shopee': '0 1px 3px rgba(0, 0, 0, 0.1)',
                        'shopee-lg': '0 4px 15px rgba(0, 0, 0, 0.08)',
                    },
                },
            },
        };
    </script>
    <style>
        /* Custom styles for glass effect */
        .glass {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .glass:hover {
            transform: translateY(-2px); /* Slightly less lift than index page */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08); /* Slightly less shadow */
        }

        .dark .glass {
            background: rgba(20, 20, 20, 0.5);
            border-color: rgba(255, 255, 255, 0.1);
        }

        /* Styles for responsive table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 16px;
        }
        .table-responsive > .table {
            margin-bottom: 0;
            width: 100%; /* Ensure table takes full width of container */
            border-collapse: collapse; /* Collapse borders */
        }
        .table-responsive > .table th,
        .table-responsive > .table td {
             /* Adjusted padding for more compact view */
             padding: 8px 16px; /* Reduced padding */
             text-align: left;
             border-bottom: 1px solid #e5e7eb; /* Tailwind border-gray-200 */
             white-space: nowrap; /* Prevent text wrapping in cells */
        }
        .dark .table-responsive > .table th,
        .dark .table-responsive > .table td {
             border-bottom-color: #374151; /* Tailwind border-slate-700 */
        }

        .table-responsive > .table th {
             font-size: 0.75rem; /* Tailwind text-xs */
             font-weight: 500; /* Tailwind font-medium */
             color: #6b7280; /* Tailwind text-gray-500 */
             text-transform: uppercase; /* Tailwind uppercase */
             letter-spacing: 0.05em; /* Tailwind tracking-wider */
             background-color: #f9fafb; /* Tailwind bg-gray-50 */
        }
         .dark .table-responsive > .table th {
             color: #9ca3af; /* Tailwind dark:text-gray-400 */
             background-color: #1e293b; /* Tailwind dark:bg-slate-800 */
         }


        .table-responsive > .table tbody tr:nth-child(even) {
            background-color: #f3f4f6; /* Tailwind bg-gray-100 */
        }
         .dark .table-responsive > .table tbody tr:nth-child(even) {
             background-color: rgba(30, 41, 59, 0.5); /* Tailwind dark:bg-slate-800/50 */
         }

        /* Style for clickable rows */
        .table-responsive > .table tbody tr {
            cursor: pointer; /* Indicate rows are clickable */
        }


        .table-responsive > .table tbody td {
             font-size: 0.875rem; /* Tailwind text-sm, keeping this size for readability */
             color: #4b5563; /* Tailwind text-gray-700 */
        }
         .dark .table-responsive > .table tbody td {
             color: #d1d5db; /* Tailwind dark:text-gray-300 */
         }

        /* Mobile responsiveness for table */
        @media (max-width: 768px) {
           .table-responsive {
             border: 1px solid #ddd; /* Add border on mobile */
             border-radius: 8px; /* Match glass border radius */
             overflow: hidden; /* Hide overflow */
           }
           .table-responsive > .table {
             border: none; /* Remove inner table borders */
           }
           .table-responsive > .table thead {
                display: none; /* Hide table header on mobile */
           }
           .table-responsive > .table tbody tr {
             display: block; /* Make table rows block elements */
             margin-bottom: 16px; /* Add space between rows */
             border: 1px solid #e5e7eb; /* Add border to each row */
             border-radius: 8px; /* Rounded corners for rows */
             background-color: #fff; /* White background for rows */
             padding: 12px; /* Padding inside the row */
             box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); /* Shadow */
           }
            .dark .table-responsive > .table tbody tr {
                 border-color: #374151; /* Dark mode border */
                 background-color: #1e293b; /* Dark mode background */
                 box-shadow: none; /* Remove shadow in dark mode glass */
            }

           .table-responsive > .table tbody tr:nth-child(even) {
                background-color: #fff; /* Remove alternating row background on mobile */
           }
            .dark .table-responsive > .table tbody tr:nth-child(even) {
                 background-color: #1e293b; /* Keep dark mode background */
            }


           .table-responsive > .table tbody tr td {
             display: block; /* Make table cells block elements */
             text-align: right; /* Align text to the right */
             padding-left: 50%; /* Make space for label */
             position: relative; /* Needed for ::before pseudo-element */
             border-bottom: 1px solid #e5e7eb; /* Add border between cells */
             white-space: normal; /* Allow text wrapping */
           }
            .dark .table-responsive > .table tbody tr td {
                 border-bottom-color: #374151; /* Dark mode border */
            }

           .table-responsive > .table tbody tr td:last-child {
             border-bottom: none; /* Remove border on the last cell */
           }

           .table-responsive > .table tbody tr td:before {
             content: attr(data-title); /* Use data-title for label */
             position: absolute;
             left: 12px; /* Padding on the left */
             width: 45%;
             font-weight: bold;
             text-align: left;
             color: #4b5563; /* Label color */
           }
            .dark .table-responsive > .table tbody tr td:before {
                 color: #d1d5db; /* Dark mode label color */
            }

           /* Specific data-title content for each column */
           .table-responsive > .table tbody tr td:nth-child(1):before { content: "No"; }
           .table-responsive > .table tbody tr td:nth-child(2):before { content: "Tanggal"; }
           .table-responsive > .table tbody tr td:nth-child(3):before { content: "Masa Aktif"; }
           .table-responsive > .table tbody tr td:nth-child(4):before { content: "Jenis Paket"; }
           .table-responsive > .table tbody tr td:nth-child(5):before { content: "Nomor HP"; }
           .table-responsive > .table tbody tr td:nth-child(6):before { content: "Pembeli"; }
           .table-responsive > .table tbody tr td:nth-child(7):before { content: "Jual"; }
           .table-responsive > .table tbody tr td:nth-child(8):before { content: "Modal"; }
           .table-responsive > .table tbody tr td:nth-child(9):before { content: "Seller"; }
           .table-responsive > .table tbody tr td:nth-child(10):before { content: "Status"; }
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 24px;
        }

        .pagination button {
            padding: 8px 16px;
            margin: 0 4px;
            border: 1px solid #d1d5db; /* gray-300 */
            border-radius: 4px;
            background-color: #fff; /* white */
            color: #4b5563; /* gray-700 */
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
        }

        .dark .pagination button {
             border-color: #4b5563; /* slate-600 */
             background-color: #374151; /* slate-700 */
             color: #d1d5db; /* gray-300 */
        }

        .pagination button:hover:not(:disabled) {
            background-color: #f3f4f6; /* gray-100 */
        }
         .dark .pagination button:hover:not(:disabled) {
             background-color: #4b5563; /* slate-600 */
         }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .page-info {
            margin: 0 16px;
            font-size: 0.875rem; /* text-sm */
            color: #4b5563; /* gray-700 */
        }
         .dark .pagination .page-info {
             color: #d1d5db; /* gray-300 */
         }

         /* Modal Styles (similar to admin panel) */
         #editModal {
             position: fixed;
             inset: 0;
             background-color: rgba(0, 0, 0, 0.5);
             backdrop-filter: blur(5px); /* Slightly less blur */
             display: flex;
             align-items: center;
             justify-content: center;
             z-index: 50;
             opacity: 0;
             visibility: hidden;
             transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
             padding: 16px; /* Add padding for small screens */
         }

         #editModal.open {
             opacity: 1;
             visibility: visible;
         }

         #editModalBox {
             background-color: #fff; /* white */
             padding: 24px; /* p-6 */
             border-radius: 12px; /* rounded-xl */
             width: 100%;
             max-width: 448px; /* max-w-md */
             box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); /* shadow-xl */
             transform: scale(0.95);
             transition: all 0.3s ease-in-out;
             position: relative;
             /* Added for responsiveness */
             max-height: 90vh; /* Limit height to 90% of viewport height */
             overflow-y: auto; /* Add vertical scroll if content overflows */
         }
          .dark #editModalBox {
              background-color: #1e293b; /* slate-800 */
          }

         #editModal.open #editModalBox {
             transform: scale(1);
             opacity: 1;
         }

         #editModalBox .close-button {
             position: absolute;
             top: 12px; /* top-3 */
             right: 12px; /* right-3 */
             color: #6b7280; /* gray-500 */
             font-size: 20px; /* text-xl */
             transition: color 0.2s ease-in-out;
             cursor: pointer;
             background: none; /* Remove button background */
             border: none; /* Remove button border */
             padding: 0; /* Remove button padding */
         }

         #editModalBox .close-button:hover {
             color: #ef4444; /* red-500 */
         }

         #editModalTitle {
             font-size: 20px; /* text-xl */
             font-weight: 600; /* font-semibold */
             color: #4f46e5; /* indigo-600 */
             margin-bottom: 16px; /* mb-4 */
         }
          .dark #editModalTitle {
              color: #ffD700; /* gold */
          }

          #editForm .form-group {
              margin-bottom: 12px; /* mb-3 */
          }

          #editForm label {
              display: block;
              font-size: 0.875rem; /* text-sm */
              font-weight: 500; /* font-medium */
              color: #374151; /* gray-700 */
              margin-bottom: 4px; /* mb-1 */
          }
           .dark #editForm label {
               color: #d1d5db; /* gray-300 */
           }

           #editForm input[type="text"],
           #editForm input[type="date"],
           #editForm input[type="number"],
           #editForm select {
               display: block;
               width: 100%;
               padding: 10px; /* p-2.5 */
               border: 1px solid #d1d5db; /* border-gray-300 */
               border-radius: 6px; /* rounded-md */
               box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); /* shadow-sm */
               outline: none;
               transition: border-color 0.2s ease-in-out, ring 0.2s ease-in-out;
               background-color: #fff; /* bg-white */
               color: #1f2937; /* gray-800 */
           }
            .dark #editForm input[type="text"],
            .dark #editForm input[type="date"],
            .dark #editForm input[type="number"],
            .dark #editForm select {
                border-color: #4b5563; /* dark:border-slate-600 */
                background-color: #374151; /* dark:bg-slate-700 */
                color: #f9fafb; /* dark:text-white */
            }

           #editForm input[type="text"]:focus,
           #editForm input[type="date"]:focus,
           #editForm input[type="number"]:focus,
           #editForm select:focus {
               border-color: #ee4d2d; /* focus:border-primary */
               ring: 2px;
               ring-color: #ee4d2d; /* focus:ring-primary */
           }
            .dark #editForm input[type="text"]:focus,
            .dark #editForm input[type="date"]:focus,
            .dark #editForm input[type="number"]:focus,
            .dark #editForm select:focus {
                border-color: #FFD700; /* dark:focus:border-gold */
                ring-color: #FFD700; /* dark:focus:ring-gold */
            }

           #editForm .button-group {
               display: flex;
               /* Changed to space-between to push delete/cancel left and save right */
               justify-content: space-between;
               align-items: center; /* Align items vertically */
               margin-top: 24px; /* mt-6 */
               /* Added for responsiveness */
               flex-wrap: wrap; /* Allow buttons to wrap on small screens */
               gap: 8px; /* Add space between wrapped buttons */
           }

            /* Container for Delete and Cancel buttons */
            #editForm .button-group .left-buttons {
                display: flex;
                gap: 8px; /* Space between delete and cancel */
                flex-wrap: wrap; /* Allow wrapping if needed */
            }


           #editForm .button-group button {
               padding: 8px 16px; /* px-4 py-2 */
               border-radius: 6px; /* rounded-md */
               font-weight: 600; /* font-semibold */
               transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
               box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); /* shadow-sm */
               cursor: pointer; /* Add cursor pointer */
           }

           #editForm .button-group .cancel-button {
               color: #374151; /* gray-700 */
               background-color: #e5e7eb; /* gray-200 */
           }
            .dark #editForm .button-group .cancel-button {
                color: #d1d5db; /* dark:text-gray-300 */
                background-color: #4b5563; /* dark:bg-slate-700 */
            }
           #editForm .button-group .cancel-button:hover {
               background-color: #d1d5db; /* hover:bg-gray-300 */
           }
            .dark #editForm .button-group .cancel-button:hover {
                background-color: #6b7280; /* dark:hover:bg-slate-600 */
            }

            /* Style for Delete Button */
            #editForm .button-group .delete-button {
                background-color: #ef4444; /* red-500 */
                color: #fff; /* text-white */
            }
            #editForm .button-group .delete-button:hover {
                background-color: #dc2626; /* hover:bg-red-600 */
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* hover:shadow-md */
            }


           #editForm .button-group .save-button {
               background-color: #ee4d2d; /* bg-primary */
               color: #fff; /* text-white */
           }
           #editForm .button-group .save-button:hover {
               background-color: #dc2626; /* hover:bg-red-600 */
               box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* hover:shadow-md */
           }

           /* Status coloring */
           .status-lunas {
               color: #10b981; /* green-500 */
               font-weight: 600; /* font-semibold */
           }
            .dark .status-lunas {
                color: #34d399; /* green-400 */
            }

           .status-hutang {
               color: #f59e0b; /* yellow-500 */
               font-weight: 600; /* font-semibold */
           }
            .dark .status-hutang {
                color: #fbbf24; /* yellow-400 */
            }

            .status-lain {
                color: #6b7280; /* gray-500 */
            }
             .dark .status-lain {
                 color: #9ca3af; /* gray-400 */
             }

/* Specific styles for the rekap table on mobile */
        @media (max-width: 768px) {
             .rekap-table thead {
                 display: none; /* Hide header on mobile */
             }

             .rekap-table tbody tr {
                 display: block; /* Make row a block */
                 margin-bottom: 12px; /* Add space below the single row */
                 border: 1px solid #e5e7eb; /* Add border */
                 border-radius: 8px; /* Rounded corners */
                 background-color: #fff; /* White background */
                 padding: 12px; /* Padding */
                 box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); /* Shadow */
             }

              .dark .rekap-table tbody tr {
                  border-color: #374151; /* Dark mode border */
                  background-color: #1e293b; /* Dark mode background */
                  box-shadow: none; /* Remove shadow in dark mode glass */
              }

             .rekap-table tbody td {
                 display: block; /* Make cells blocks */
                 text-align: right; /* Align text right */
                 padding: 8px 12px; /* Padding */
                 padding-left: 50%; /* Space for label */
                 position: relative; /* Needed for ::before */
                 border-bottom: 1px solid #e5e7eb; /* Border between cells */
                 white-space: normal; /* Allow text wrap */
             }
              .dark .rekap-table tbody td {
                  border-bottom-color: #374151; /* Dark mode border */
              }

             .rekap-table tbody td:last-child {
                 border-bottom: none; /* No border on last cell */
             }

             /* Make rekap-table td:before rule more specific */
             /* This targets the ::before pseudo-element only within rekap-table td */
             .rekap-table.table tbody tr td:before {
                 content: attr(data-title); /* Use data-title for label */
                 position: absolute;
                 left: 12px; /* Align label left */
                 width: 45%;
                 font-weight: bold;
                 text-align: left;
                 color: #4b5563; /* Label color */
                 /* Ensure this overrides the general nth-child rules by being more specific */
             }
              .dark .rekap-table.table tbody tr td:before {
                  color: #d1d5db; /* Dark mode label color */
              }

              /* Ensure rekap table cells use their data-title and not the general nth-child ones */
              /* Explicitly set content to the data-title for the first three cells */
              .rekap-table.table tbody tr td:nth-child(1):before { content: "Total Harga Jual"; }
              .rekap-table.table tbody tr td:nth-child(2):before { content: "Total Harga Modal"; }
              .rekap-table.table tbody tr td:nth-child(3):before { content: "Total Untung"; }
        }

    </style>
</head>
<body class="font-sans bg-gray-100 dark:bg-slate-900 text-gray-800 dark:text-gray-100">
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white dark:bg-slate-900 shadow-md dark:shadow-none px-4 py-2 flex items-center justify-between border-b border-gray-200 dark:border-slate-800">
                 <h1 class="text-xl font-bold text-primary dark:text-gold tracking-tight">
              <a href="/"> <span class="font-semibold">Data</span> <span class="font-normal">Pembelian</span></a>
           </h1>
        <div class="flex items-center space-x-4">
             <a href="/input" class="text-gray-700 dark:text-gray-300 hover:text-primary dark:hover:text-gold transition-colors duration-200 text-sm font-medium">Add Data</a>
             <a href="/add_pelanggan.php" class="text-gray-700 dark:text-gray-300 hover:text-primary dark:hover:text-gold transition-colors duration-200 text-sm font-medium">Add Pelanggan</a>
             <a href="/display" class="text-gray-700 dark:text-gray-300 hover:text-primary dark:hover:text-gold transition-colors duration-200 text-sm font-medium">Data Paket</a>
              <button id="darkToggle" class="bg-gray-200 dark:bg-slate-800 text-gray-700 dark:text-white px-3 py-1 rounded-full text-sm
                           hover:bg-gray-300 dark:hover:bg-slate-700 transition-colors duration-200
                           shadow-sm dark:shadow-none">
                  <span role="img" aria-label="dark mode">🌙</span> Mode
              </button>
        </div>
    </nav>

    <header class="text-center py-16 px-4 mt-16 bg-gradient-to-b from-white dark:from-slate-900 to-gray-50 dark:to-slate-800">
        <h2 class="text-2xl sm:text-3xl font-semibold text-primary dark:text-gold drop-shadow-md leading-snug">
            Data Pembelian Paket XL/AXIS
        </h2>
        <p class="mt-2 text-md text-gray-600 dark:text-gray-300 max-w-xl mx-auto">
            Riwayat dan Rekap Data Transaksi.
        </p>
    </header>

    <main class="px-4 max-w-6xl mx-auto mb-28">
         <div class="mb-6">
              <input type="text" id="searchInput" placeholder="Cari berdasarkan nama pembeli, nomor HP atau jenis paket..."
                     class="w-full p-2.5 rounded-md border border-gray-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-primary
                            dark:focus:ring-gold bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm">
         </div>

        <div class="text-center mb-6">
            <a href="index.php" class="inline-block px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-semibold transition-colors duration-200 shadow-md">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Halaman Utama
            </a>
        </div>

        <div class="glass p-4 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 text-center">Rekap Data</h3>
            <div class="table-responsive">
                <table class="table w-full rekap-table"> <thead>
                          <tr>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Harga Jual</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Harga Modal</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Untung</th>
                          </tr>
                      </thead>
                     <tbody>
                          <tr class="bg-white dark:bg-slate-900">
                             <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white" id="totalHargaJual" data-title="Total Harga Jual"><?php echo formatRupiah($total_harga_jual); ?></td>
                             <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" id="totalHargaModal" data-title="Total Harga Modal"><?php echo formatRupiah($total_harga_modal); ?></td>
                             <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" id="totalUntung" data-title="Total Untung"><?php echo formatRupiah($total_untung); ?> <b class="text-gray-700 dark:text-gray-200">[<span id="totalDataCount"><?php echo $totalData; ?></span> Nomor]</b></td>
                          </tr>
                      </tbody>
                </table>
            </div>
        </div>


        <?php if (!empty($data_arr)): // Cek apakah data_arr (semua data) tidak kosong ?>
             <div class="table-responsive glass p-4">
                 <table class="table w-full">
                     <thead class="bg-gray-50 dark:bg-slate-800">
                         <tr>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">No</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Masa Aktif</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis Paket</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nomor HP</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pembeli</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jual</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Modal</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Seller</th>
                             <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                         </tr>
                     </thead>
                     <tbody id="dataBody" class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                          </tbody>
                 </table>
             </div>

             <div id="paginationControls" class="pagination">
                 <button id="prevPageBtn" disabled><i class="fas fa-chevron-left"></i> Sebelumnya</button>
                 <span id="pageInfo" class="page-info">Halaman 1 dari 1</span>
                 <button id="nextPageBtn" disabled>Selanjutnya <i class="fas fa-chevron-right"></i></button>
             </div>


        <?php else: ?>
             <p class="text-center text-gray-600 dark:text-gray-300 mt-8">Tidak ada data untuk ditampilkan.</p>
        <?php endif; ?>
    </main>

    <div id="editModal" class="">
         <div id="editModalBox">
              <button onclick="closeEditModal()" class="close-button">
                  <span role="img" aria-label="close">❌</span>
              </button>
              <h2 id="editModalTitle">Edit Data Paket</h2>

              <form id="editForm">
                  <input type="hidden" id="editNomor" name="nomor">
                  <div class="form-group">
                      <label for="editTanggal">Tanggal</label>
                      <input type="date" id="editTanggal" name="tanggal" required>
                  </div>
                  <div class="form-group">
                      <label for="editJenisPaket">Jenis Paket</label>
                      <input type="text" id="editJenisPaket" name="jenis_paket" required>
                  </div>
                  <div class="form-group">
                      <label for="editNomorHP">Nomor HP</label>
                      <input type="text" id="editNomorHP" name="nomor_hp" required>
                  </div>
                   <div class="form-group">
                      <label for="editNomorWA">Nomor WA</label>
                      <input type="text" id="editNomorWA" name="nomor_wa" readonly>
                      </div>
                  <div class="form-group">
                      <label for="editNamaPembeli">Nama Pembeli</label>
                      <input type="text" id="editNamaPembeli" name="nama_pembeli" readonly>
                  </div>
                  <div class="form-group">
                      <label for="editHargaJual">Harga Jual</label>
                      <input type="number" id="editHargaJual" name="harga_jual" required min="0">
                  </div>
                  <div class="form-group">
                      <label for="editHargaModal">Harga Modal</label>
                      <input type="number" id="editHargaModal" name="harga_modal" required min="0">
                  </div>
                  <div class="form-group">
                      <label for="editNamaSeller">Nama Seller</label>
                      <input type="text" id="editNamaSeller" name="nama_seller" required>
                  </div>
                  <div class="form-group">
                      <label for="editStatusPembayaran">Status Pembayaran</label>
                      <select id="editStatusPembayaran" name="status_pembayaran" required>
                           <option value="LUNAS">Lunas</option>
                           <option value="HUTANG">Hutang</option>
                      </select>
                  </div>

                  <div class="button-group">
                      <div class="left-buttons">
                          <button type="button" onclick="deleteData()" class="delete-button">Hapus</button>
                          <button type="button" onclick="closeEditModal()" class="cancel-button">Batal</button>
                      </div>
                      <button type="submit" class="save-button">Simpan Perubahan</button>
                  </div>
              </form>
         </div>
    </div>

    <div id="saveStatusMessage" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 px-4 py-2 rounded-md text-sm font-semibold text-white shadow-lg hidden">
        </div>
<script>
        // Data paket dari PHP (semua data)
        let allPaketData = <?php echo $jsonData; ?>; // Use let as we will modify this array
        let currentFileSha = <?php echo $jsonSha; ?>; // SHA dari file data.json saat ini

        // === PERHATIAN KEAMANAN TINGGI ===
        // Menyimpan GitHub Personal Access Token (PAT) di kode JavaScript
        // yang diakses publik SANGAT TIDAK AMAN. Siapa pun dapat melihat token ini
        // dan mendapatkan akses ke repositori GitHub Anda.
        // Gunakan metode sisi server (seperti PHP) untuk interaksi API yang aman.
        const GITHUB_PAT = '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'; // Ganti dengan token Anda
        const REPO_OWNER = 'andrias97'; // Ganti dengan username pemilik repo
        const REPO_NAME = 'databasepaket'; // Ganti dengan nama repo
        const FILE_PATH = 'data.json'; // Path ke file data.json di repo
        const BRANCH = 'main'; // Branch yang digunakan
        const GITHUB_API_FILE_URL = `https://api.github.com/repos/${REPO_OWNER}/${REPO_NAME}/contents/${FILE_PATH}`;
        // === AKHIR PERHATIAN KEAMANAN ===


        console.log("Initial allPaketData from PHP:", allPaketData); // Debugging: Initial data
        console.log("Initial file SHA from PHP:", currentFileSha); // Debugging: Initial SHA

        const itemsPerPage = 20; // Jumlah data per halaman
        let currentPage = 1;
        let filteredData = []; // Data setelah filtering

        const dataBody = document.getElementById('dataBody');
        const searchInput = document.getElementById('searchInput');
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const pageInfoSpan = document.getElementById('pageInfo');
        const totalHargaJualSpan = document.getElementById('totalHargaJual');
        const totalHargaModalSpan = document.getElementById('totalHargaModal');
        const totalUntungSpan = document.getElementById('totalUntung');
        const totalDataCountSpan = document.getElementById('totalDataCount'); // Span for total data count
        const saveStatusMessageDiv = document.getElementById('saveStatusMessage'); // Message area

        // Edit Modal Elements
        const editModal = document.getElementById('editModal');
        const editForm = document.getElementById('editForm');
        const editNomorInput = document.getElementById('editNomor');
        const editTanggalInput = document.getElementById('editTanggal');
        const editJenisPaketInput = document.getElementById('editJenisPaket');
        const editNomorHPInput = document.getElementById('editNomorHP');
        const editNomorWAInput = document.getElementById('editNomorWA');
        const editNamaPembeliInput = document.getElementById('editNamaPembeli');
        const editHargaJualInput = document.getElementById('editHargaJual');
        const editHargaModalInput = document.getElementById('editHargaModal');
        const editNamaSellerInput = document.getElementById('editNamaSeller');
        const editStatusPembayaranSelect = document.getElementById('editStatusPembayaran');


        // Fungsi untuk menampilkan pesan status
        function showStatusMessage(message, type = 'success') {
            saveStatusMessageDiv.textContent = message;
            saveStatusMessageDiv.classList.remove('hidden', 'bg-green-500', 'bg-red-500', 'bg-blue-500');
            if (type === 'success') {
                saveStatusMessageDiv.classList.add('bg-green-500');
            } else if (type === 'error') {
                saveStatusMessageDiv.classList.add('bg-red-500');
            } else {
                saveStatusMessageDiv.classList.add('bg-blue-500'); // Info or other types
            }
            saveStatusMessageDiv.style.display = 'block'; // Ensure it's displayed

            setTimeout(() => {
                saveStatusMessageDiv.classList.add('hidden');
                 saveStatusMessageDiv.style.display = 'none'; // Hide after animation/timeout
            }, 5000); // Hide after 5 seconds
        }


        // Fungsi format Rupiah untuk JavaScript
        function formatRupiah(angka) {
            const number = parseFloat(angka);
            if (isNaN(number)) {
                return 'Rp 0';
            }
            // Use toLocaleString for better formatting with thousands separator
            return 'Rp ' + number.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        // Fungsi untuk merender data ke dalam tabel untuk halaman saat ini
        function renderTable(dataToDisplay) {
            console.log("Rendering table with dataToDisplay:", dataToDisplay); // Debugging
            dataBody.innerHTML = ''; // Kosongkan tabel
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const paginatedData = dataToDisplay.slice(startIndex, endIndex);
            console.log("Paginated data for current page:", paginatedData); // Debugging

            if (paginatedData.length === 0 && dataToDisplay.length > 0) {
                 // If current page is empty but there's filtered data, go back to the previous page
                 console.log("Current page is empty, going back to previous page."); // Debugging
                 // Check if previous page is valid before going back
                 if (currentPage > 1) {
                      goToPage(currentPage - 1);
                 } else {
                      // If already on page 1 and no data, show empty message
                      console.log("No data on page 1 after filtering, rendering empty table message."); // Debugging
                      dataBody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-gray-500 dark:text-gray-400">Tidak ada data yang cocok dengan pencarian.</td></tr>';
                 }
                 return; // Stop rendering for this empty page
            } else if (paginatedData.length === 0 && dataToDisplay.length === 0) {
                 // If no data at all, just render empty table
                 console.log("No data to display, rendering empty table message."); // Debugging
                 dataBody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-gray-500 dark:text-gray-400">Tidak ada data yang cocok dengan pencarian.</td></tr>';
                 return;
            }


            paginatedData.forEach((data, index) => {
                // console.log("Processing data for row:", data); // Debugging each row
                const row = document.createElement('tr');
                // Gunakan index global (termasuk offset halaman) untuk warna baris bergantian
                row.className = (startIndex + index) % 2 === 0 ? 'bg-white dark:bg-slate-900' : 'bg-gray-50 dark:bg-slate-800/50';

                 // Tambahkan atribut data-harga-jual dan data-harga-modal untuk rekap
                 const hargaJual = data.harga_jual !== undefined ? parseFloat(data.harga_jual) : 0;
                 const hargaModal = data.harga_modal !== undefined ? parseFloat(data.harga_modal) : 0;
                 row.setAttribute('data-harga-jual', hargaJual);
                 row.setAttribute('data-harga-modal', hargaModal);

                 // Tambahkan atribut data-id untuk memudahkan identifikasi saat klik
                 // Pastikan data.nomor ada dan valid
                 const dataId = data.nomor !== undefined ? parseInt(data.nomor, 10) : null;
                 // Tambahkan log untuk memeriksa dataId saat rendering
                 console.log(`Rendering row for item with nomor: ${data.nomor}, setting data-id: ${dataId}, type: ${typeof dataId}`);

                 if (dataId !== null) {
                      row.setAttribute('data-id', dataId);
                 }


                 // Tentukan kelas CSS untuk status pembayaran
                 let statusClass = 'status-lain'; // Default class
                 // Gunakan toUpperCase() untuk perbandingan case-insensitive
                 const status = data.status_pembayaran !== undefined ? String(data.status_pembayaran).toUpperCase() : '';
                 // console.log("Status data:", data.status_pembayaran, "Processed status:", status); // Debugging status
                 if (status === 'LUNAS') {
                      statusClass = 'status-lunas';
                 } else if (status === 'HUTANG') {
                      statusClass = 'status-hutang';
                 }


                 row.innerHTML = `
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white" data-title="No">${data.nomor !== undefined ? data.nomor : ''}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" data-title="Tanggal">${data.tanggal !== undefined ? formatTanggal(data.tanggal) : ''}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" data-title="Hitungan Mundur">${data.tanggal !== undefined ? hitunganMundur(data.tanggal) : ''}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" data-title="Jenis Paket">${data.jenis_paket !== undefined ? data.jenis_paket : ''}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" data-title="Nomor HP">${data.nomor_hp !== undefined ? data.nomor_hp : ''}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" data-title="Pembeli">${data.nama_pembeli !== undefined ? data.nama_pembeli : ''}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" data-title="Jual">${formatRupiah(hargaJual)}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm ${hargaModal > hargaJual ? 'text-red-500 dark:text-red-400' : 'text-gray-500 dark:text-gray-300'}" data-title="Modal">
                           ${formatRupiah(hargaModal)}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300" data-title="Seller">${data.nama_seller !== undefined ? data.nama_seller : ''}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm ${statusClass}" data-title="Status">${data.status_pembayaran !== undefined ? data.status_pembayaran : ''}</td>
                  `;
                 // Hanya tambahkan event listener jika dataId valid
                 if (dataId !== null) {
                      row.addEventListener('click', () => {
                           const clickedDataId = parseInt(row.getAttribute('data-id'), 10);
                           console.log(`Row clicked. data-id attribute: ${row.getAttribute('data-id')}, Parsed clickedDataId: ${clickedDataId}, Type: ${typeof clickedDataId}`); // Debugging: Klik baris
                           const dataToEdit = allPaketData.find(item => {
                                console.log(`Comparing item.nomor (${item.nomor}, type: ${typeof item.nomor}) with clickedDataId (${clickedDataId}, type: ${typeof clickedDataId})`); // Debugging: Perbandingan find
                                // Pastikan kedua nilai dikonversi ke number untuk perbandingan yang aman
                                // Menggunakan Number() juga bisa menjadi alternatif parseInt()
                                return Number(item.nomor) === clickedDataId;
                           });

                           if (dataToEdit) {
                                console.log("Data found for editing:", dataToEdit); // Debugging: Data ditemukan
                                showEditModal(dataToEdit);
                           } else {
                                console.error("Data not found for ID:", clickedDataId); // Debugging: Data tidak ditemukan
                           }
                      });
                 }


                dataBody.appendChild(row);
            });
        }

         // Fungsi untuk memperbarui total rekap
         function updateRekap(dataToCalculate) {
             console.log("Updating rekap with data:", dataToCalculate); // Debugging
             let totalJual = 0;
             let totalModal = 0;
             let totalNomor = dataToCalculate.length; // Hitung dari data yang difilter

             dataToCalculate.forEach(data => {
                  const hargaJual = data.harga_jual !== undefined ? parseFloat(data.harga_jual) : 0;
                  const hargaModal = data.harga_modal !== undefined ? parseFloat(data.harga_modal) : 0;
                  totalJual += hargaJual;
                  totalModal += hargaModal;
             });

             const totalUntung = totalJual - totalModal;

             totalHargaJualSpan.textContent = formatRupiah(totalJual);
             totalHargaModalSpan.textContent = formatRupiah(totalModal);
             // Update total data count span separately
             totalDataCountSpan.textContent = totalNomor;
             totalUntungSpan.innerHTML = `${formatRupiah(totalUntung)} <b class="text-gray-700 dark:text-gray-200">[<span id="totalDataCount">${totalNomor}</span> Nomor]</b>`;
         }


        // Fungsi untuk memperbarui kontrol pagination
        function updatePaginationControls(dataLength) {
            const totalPages = Math.ceil(dataLength / itemsPerPage);
            pageInfoSpan.textContent = `Halaman ${currentPage} dari ${totalPages}`;

            prevPageBtn.disabled = currentPage === 1;
            nextPageBtn.disabled = currentPage === totalPages || totalPages === 0; // Disable next if no data or on last page
        }

        // Fungsi untuk berpindah halaman (digunakan oleh tombol pagination)
        function goToPage(page) {
            console.log("Attempting to go to page:", page); // Debugging
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            // Ensure page is within bounds
            const targetPage = Math.max(1, Math.min(page, totalPages > 0 ? totalPages : 1)); // Ensure targetPage is at least 1, even if totalPages is 0
            console.log("Calculated target page:", targetPage, "Total pages:", totalPages); // Debugging

             // Hanya update jika halaman berubah
             if (currentPage !== targetPage) {
                 currentPage = targetPage;
                 console.log("Current page updated to:", currentPage); // Debugging
                 renderTable(filteredData); // Render tabel dengan data yang sudah difilter
                 updatePaginationControls(filteredData.length);
             } else {
                 console.log("Already on target page."); // Debugging
             }
        }


        // Event listeners untuk tombol pagination
        prevPageBtn.addEventListener('click', () => {
            goToPage(currentPage - 1);
        });

        nextPageBtn.addEventListener('click', () => {
            goToPage(currentPage + 1);
        });

        // Fungsi untuk menerapkan filter pencarian
        function applySearchFilter() {
             const searchText = searchInput.value.toLowerCase();
             console.log("Applying search filter with text:", searchText); // Debugging
             filteredData = allPaketData.filter(data => {
                  // Gabungkan semua nilai string dari objek data untuk pencarian
                  // Pastikan nilai ada sebelum mengaksesnya
                  const nomor = data.nomor !== undefined ? String(data.nomor).toLowerCase() : '';
                  const tanggal = data.tanggal !== undefined ? String(data.tanggal).toLowerCase() : '';
                  const jenis_paket = data.jenis_paket !== undefined ? String(data.jenis_paket).toLowerCase() : '';
                  const nomor_hp = data.nomor_hp !== undefined ? String(data.nomor_hp).toLowerCase() : '';
                  const nomor_wa = data.nomor_wa !== undefined ? String(data.nomor_wa).toLowerCase() : '';
                  const nama_pembeli = data.nama_pembeli !== undefined ? String(data.nama_pembeli).toLowerCase() : '';
                  const harga_jual = data.harga_jual !== undefined ? String(data.harga_jual).toLowerCase() : '';
                  const harga_modal = data.harga_modal !== undefined ? String(data.harga_modal).toLowerCase() : '';
                  const nama_seller = data.nama_seller !== undefined ? String(data.nama_seller).toLowerCase() : '';
                  const status_pembayaran = data.status_pembayaran !== undefined ? String(data.status_pembayaran).toLowerCase() : '';

                  const dataString = `${nomor} ${tanggal} ${jenis_paket} ${nomor_hp} ${nomor_wa} ${nama_pembeli} ${harga_jual} ${harga_modal} ${nama_seller} ${status_pembayaran}`;

                  return dataString.includes(searchText);
             });

             console.log("Filtered data after search:", filteredData); // Debugging
             currentPage = 1; // Kembali ke halaman pertama setelah filtering

             // Langsung panggil render dan update pagination di sini
             renderTable(filteredData);
             updatePaginationControls(filteredData.length);

             updateRekap(filteredData); // Perbarui rekap berdasarkan data yang difilter
        }


        // Event listener untuk filter pencarian
        searchInput.addEventListener('input', applySearchFilter);

        // Toggle Dark Mode
        document.getElementById('darkToggle').addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
        });

        // --- Edit Modal Functions ---

        // Fungsi untuk menampilkan modal edit
        function showEditModal(data) {
            console.log("showEditModal function called with data:", data); // Debugging: Awal showEditModal
            // Pastikan data valid sebelum mengisi form
            if (!data) {
                console.error("showEditModal called with invalid data:", data);
                return;
            }

            editModalTitle.textContent = 'Edit Data Paket #' + (data.nomor !== undefined ? data.nomor : '');
            editNomorInput.value = data.nomor !== undefined ? data.nomor : '';
            editTanggalInput.value = data.tanggal !== undefined ? data.tanggal : ''; // Format YYYY-MM-DD
            editJenisPaketInput.value = data.jenis_paket !== undefined ? data.jenis_paket : '';
            editNomorHPInput.value = data.nomor_hp !== undefined ? data.nomor_hp : '';
            editNomorWAInput.value = data.nomor_wa !== undefined ? data.nomor_wa : ''; // Set WA value
            editNamaPembeliInput.value = data.nama_pembeli !== undefined ? data.nama_pembeli : '';
            editHargaJualInput.value = data.harga_jual !== undefined ? data.harga_jual : '';
            editHargaModalInput.value = data.harga_modal !== undefined ? data.harga_modal : '';
            editNamaSellerInput.value = data.nama_seller !== undefined ? data.nama_seller : '';
            editStatusPembayaranSelect.value = data.status_pembayaran !== undefined ? data.status_pembayaran : ''; // Set selected option

            editModal.classList.add('open');
            console.log("editModal.classList.add('open') executed."); // Debugging: Class 'open' ditambahkan
        }

        // Fungsi untuk menutup modal edit
        function closeEditModal() {
            editModal.classList.remove('open');
            editForm.reset(); // Reset form saat ditutup
            console.log("closeEditModal function executed."); // Debugging: Modal ditutup
        }

        // Event listener untuk submit form edit
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const updatedData = {
                nomor: parseInt(editNomorInput.value, 10),
                tanggal: editTanggalInput.value,
                jenis_paket: editJenisPaketInput.value.trim(),
                nomor_hp: editNomorHPInput.value.trim(),
                nomor_wa: editNomorWAInput.value.trim(), // Get WA value
                nama_pembeli: editNamaPembeliInput.value.trim(),
                harga_jual: parseFloat(editHargaJualInput.value),
                harga_modal: parseFloat(editHargaModalInput.value),
                nama_seller: editNamaSellerInput.value.trim(),
                status_pembayaran: editStatusPembayaranSelect.value,
            };

            console.log("Submitting updated data:", updatedData); // Debugging submit

            // Validasi dasar
            if (isNaN(updatedData.nomor) || !updatedData.tanggal || !updatedData.jenis_paket || !updatedData.nomor_hp || !updatedData.nomor_wa || !updatedData.nama_pembeli || isNaN(updatedData.harga_jual) || isNaN(updatedData.harga_modal) || !updatedData.nama_seller || !updatedData.status_pembayaran) {
                 showStatusMessage("Mohon lengkapi semua field dengan benar.", 'error');
                 return;
            }


            // Temukan index data yang akan diupdate di allPaketData
            const dataIndex = allPaketData.findIndex(data => Number(data.nomor) === updatedData.nomor); // Pastikan perbandingan dengan Number()

            if (dataIndex !== -1) {
                // Update data di allPaketData
                allPaketData[dataIndex] = updatedData;

                // Perbarui juga di filteredData dengan menerapkan kembali filter pencarian
                applySearchFilter(); // Re-filter data based on current search term

                // Kirim seluruh allPaketData yang sudah diperbarui ke server
                saveAllDataToGithub(allPaketData); // Panggil fungsi save ke GitHub API

                closeEditModal(); // Tutup modal setelah menyimpan
            } else {
                showStatusMessage("Data dengan nomor tersebut tidak ditemukan.", 'error');
                closeEditModal();
            }
        });

        // Fungsi untuk menghapus data
        function deleteData() {
            const nomorToDelete = parseInt(editNomorInput.value, 10);

            if (isNaN(nomorToDelete)) {
                showStatusMessage("Nomor data tidak valid.", 'error');
                return;
            }

            // Konfirmasi sebelum menghapus
            if (confirm(`Apakah Anda yakin ingin menghapus data dengan Nomor ${nomorToDelete}?`)) {
                // Temukan index data yang akan dihapus di allPaketData
                const dataIndex = allPaketData.findIndex(data => Number(data.nomor) === nomorToDelete); // Pastikan perbandingan dengan Number()

                if (dataIndex !== -1) {
                    // Hapus data dari allPaketData
                    allPaketData.splice(dataIndex, 1);

                    // Perbarui filteredData dengan menerapkan kembali filter pencarian
                    applySearchFilter(); // Re-filter data based on current search term

                    // Kirim seluruh allPaketData yang sudah diperbarui ke server
                    saveAllDataToGithub(allPaketData); // Panggil fungsi save ke GitHub API

                    closeEditModal(); // Tutup modal setelah menghapus
                } else {
                    showStatusMessage("Data dengan nomor tersebut tidak ditemukan.", 'error');
                    closeEditModal();
                }
            }
        }


        // Fungsi untuk mengirim seluruh data ke GitHub API (PUT request)
        async function saveAllDataToGithub(dataToSave) {
             console.log("Saving data to GitHub API:", dataToSave); // Debugging save
             showStatusMessage("Menyimpan data...", 'info'); // Show saving status

             const jsonDataString = JSON.stringify(dataToSave, null, 2); // Format JSON dengan indentasi 2 spasi
             // Menggunakan btoa(unescape(encodeURIComponent())) untuk menangani karakter non-ASCII
             const base64Content = btoa(unescape(encodeURIComponent(jsonDataString)));

             // Coba temukan nomor item yang sedang diedit/dihapus untuk pesan commit
             const editedItemNomor = editNomorInput.value ? `#${editNomorInput.value}` : 'unknown';
             const commitMessage = `Update data for item ${editedItemNomor}`;

             const requestBody = {
                 message: commitMessage,
                 content: base64Content,
                 sha: currentFileSha, // Gunakan SHA file saat ini
                 branch: BRANCH // Tentukan branch
             };

             try {
                 const response = await fetch(GITHUB_API_FILE_URL, {
                     method: 'PUT',
                     headers: {
                         'Authorization': `token ${GITHUB_PAT}`, // Menggunakan PAT (TIDAK AMAN DI CLIENT-SIDE)
                         'Accept': 'application/vnd.github.v3+json',
                         'Content-Type': 'application/json'
                     },
                     body: JSON.stringify(requestBody),
                 });

                 console.log("GitHub API response status:", response.status); // Debugging status
                 const responseData = await response.json();
                 console.log("GitHub API response data:", responseData); // Debugging response data

                 if (response.ok) {
                     showStatusMessage('Perubahan berhasil disimpan di GitHub.', 'success');
                     // Update SHA file setelah berhasil disimpan
                     currentFileSha = responseData.content.sha;
                     console.log("New file SHA:", currentFileSha); // Debugging new SHA

                     // Setelah berhasil disimpan, render ulang tabel dan update rekap
                     // Data lokal (allPaketData dan filteredData) sudah diperbarui sebelum fetch
                     renderTable(filteredData); // Render ulang halaman saat ini
                     updateRekap(filteredData); // Perbarui rekap

                 } else {
                     const errorMessage = responseData.message || `Error ${response.status}: Gagal menyimpan data ke GitHub.`;
                     console.error("GitHub API error response:", responseData); // Debugging GitHub error
                     showStatusMessage('Gagal menyimpan perubahan di GitHub: ' + errorMessage, 'error');
                     // Mungkin perlu refresh halaman atau mengambil ulang data jika SHA tidak cocok
                     // location.reload(); // Opsi: refresh halaman jika terjadi error
                 }
             } catch (error) {
                 console.error('Error saat menyimpan ke GitHub:', error); // Debugging fetch error
                 // Tangani error spesifik seperti network error (Status 0)
                 let userErrorMessage = 'Terjadi kesalahan saat menyimpan perubahan. Cek koneksi internet Anda.';
                 if (error.message) {
                     userErrorMessage += ` Detail: ${error.message}`;
                 }
                 showStatusMessage(userErrorMessage, 'error');
                 // Jika error terjadi, data lokal mungkin tidak sinkron dengan GitHub
                 // Pertimbangkan untuk memberi tahu pengguna untuk refresh atau mencoba lagi
             }
         }


        // Inisialisasi: Muat data, set filteredData, render tabel pertama, dan update kontrol
        document.addEventListener('DOMContentLoaded', () => {
             console.log("DOM fully loaded and parsed."); // Debugging DOM ready
             // Pastikan allPaketData sudah terisi dari PHP
             if (allPaketData && allPaketData.length > 0) {
                 console.log("Initial data loaded:", allPaketData); // Debugging initial data
                 filteredData = [...allPaketData]; // Awalnya, data yang difilter adalah salinan semua data

                 // Panggil renderTable dan updatePaginationControls langsung di sini
                 renderTable(filteredData);
                 updatePaginationControls(filteredData.length);

                 updateRekap(filteredData); // Hitung rekap awal dari semua data
             } else {
                 // Handle case where no data was loaded from PHP
                 allPaketData = []; // Ensure it's an empty array
                 filteredData = [];
                 renderTable([]); // Render empty table (akan menampilkan pesan "Tidak ada data...")
                 updatePaginationControls(0);
                 updateRekap([]);
                 console.warn("Tidak ada data paket yang dimuat atau data kosong.");
             }
        });

         // Fungsi formatTanggal untuk digunakan di JavaScript (untuk hitungan mundur)
         function formatTanggal(tanggal) {
             if (!tanggal) return ''; // Handle null or empty date

             const bulan = [
                 '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
             ];
             const hari = [
                 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
             ];
             const date = new Date(tanggal);
              // Validasi tanggal
              if (!(date instanceof Date && !isNaN(date))) return tanggal;

             const dayName = hari[date.getDay()];
             const dateNum = date.getDate();
             const monthName = bulan[date.getMonth() + 1]; // getMonth() is 0-indexed
             const year = date.getFullYear();

             return `${dayName}, ${dateNum} ${monthName} ${year}`;
         }

         // Fungsi hitunganMundur untuk digunakan di JavaScript
         function hitunganMundur(tanggal) {
             if (!tanggal) return ''; // Handle null or empty date

             const dateNow = new Date();
             const dateTarget = new Date(tanggal);
              // Validasi tanggal
              if (!(dateTarget instanceof Date && !isNaN(dateTarget))) return '';

             const dateTargetEnd = new Date(dateTarget); // Clone date object
             dateTargetEnd.setDate(dateTargetEnd.getDate() + 29); // Add 29 days for 30 days total

             const timeDiff = dateTargetEnd.getTime() - dateNow.getTime();
             const daysLeft = Math.ceil(timeDiff / (1000 * 3600 * 24)); // Calculate days left

             if (daysLeft < 0) {
                 return '<span class="text-red-500 font-semibold">Expired</span>';
             } else if (daysLeft <= 7) {
                  return '<span class="text-orange-500 font-semibold">' + daysLeft + ' hari</span> [<span class="text-yellow-500 font-semibold">Segera Expired</span>]';
             } else {
                 return '<span class="text-green-600 font-semibold">' + daysLeft + ' hari</span> [<span class="text-green-500 font-semibold">Active</span>]';
             }
         }


    </script>
</body>
</html>
<?php
    // Hentikan eksekusi script setelah menampilkan konten
    exit;

} else {
    // Jika belum terautentikasi, cek apakah form PIN disubmit
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Ambil PIN yang dimasukkan oleh pengguna
        $entered_pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';

        // Validasi PIN (pastikan hanya angka jika memang harus angka)
        if (!ctype_digit($entered_pin)) {
             $error_message = "PIN harus berupa angka.";
        } else {
            // Cek apakah PIN yang dimasukkan benar
            if ($entered_pin === $correct_pin) {
                // PIN benar, set session untuk menandai bahwa pengguna sudah terautentikasi
                $_SESSION['pin_authenticated'] = true;

                // Redirect ke URL yang bersih (misalnya /paket)
                // Ini untuk mencegah masalah refresh halaman dan form resubmission
                header("Location: " . $redirect_url, true, 302);
                exit; // Penting untuk menghentikan eksekusi setelah redirect
            } else {
                // PIN salah
                $error_message = "PIN salah. Silakan coba lagi.";
            }
        }
    }

    // Cek jika ada permintaan logout
    if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
        // Hapus semua variabel session
        $_SESSION = array();

        // Hapus session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Hancurkan session
        session_destroy();

        // Redirect kembali ke halaman PIN
        header("Location: " . $redirect_url, true, 302);
        exit;
    }

    // Tampilkan form permintaan PIN jika belum terautentikasi dan bukan request POST yang berhasil
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masukkan PIN</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .pin-container { background-color: #f9f9f9; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .pin-container h1 { margin-top: 0; }
        .pin-container input[type="password"] { padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; font-size: 1.2em; text-align: center; }
        .pin-container button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1.1em; }
        .pin-container button:hover { background-color: #0056b3; }
        .error { color: red; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="pin-container">
        <h1>Akses Terproteksi</h1>
        <p>Masukkan PIN untuk melanjutkan:</p>
        <?php if (!empty($error_message)): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <input type="password" name="pin" required autofocus><br>
            <button type="submit">Submit PIN</button>
        </form>
    </div>
</body>
</html>

<?php
}
?>