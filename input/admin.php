<?php
session_start();
// Diasumsikan helpers.php ada di root dan sudah bisa di-include
// Jika belum, pastikan path-nya benar atau fungsi-fungsi ini didefinisikan di sini.
if (file_exists(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
} elseif (file_exists(__DIR__ . '/settings_github.php')) { // Fallback jika fungsi ada di settings_github.php
    // Ini bukan praktik terbaik, idealnya fungsi helper terpusat.
    // Hanya untuk memastikan fungsi load_settings ada jika helpers.php tidak ditemukan.
    require_once __DIR__ . '/settings_github.php';
} else {
    // Jika tidak ada helpers.php atau settings_github.php yang mendefinisikan load_settings,
    // kita perlu mendefinisikannya di sini atau menghentikan eksekusi.
    // Untuk contoh ini, kita akan coba definisikan versi minimalnya.
    if (!function_exists('load_settings')) {
        if (!defined('CONFIG_FILE_PATH_ADMIN_PANEL')) {
             // Path ke config.php dari root admin_panel.php
            define('CONFIG_FILE_PATH_ADMIN_PANEL', __DIR__ . '/input/config.php');
        }
        function get_default_settings_admin_panel() {
            return [
                'admin_pin' => '0000', // Default PIN
                'github_api_token' => '', 'github_repo_owner' => '', 'github_repo_name' => '',
                'github_branch' => 'main', 'github_data_file' => 'data.json',
                'github_pelanggan_file' => 'pelanggan.json', 'whatsapp_api_url' => '',
            ];
        }
        function load_settings() {
            if (file_exists(CONFIG_FILE_PATH_ADMIN_PANEL)) {
                $settings = include CONFIG_FILE_PATH_ADMIN_PANEL;
                if (is_array($settings)) {
                    return array_merge(get_default_settings_admin_panel(), $settings);
                }
            }
            return get_default_settings_admin_panel();
        }
    }
}

$app_settings = load_settings();
$correct_admin_pin = $app_settings['admin_pin'] ?? '0000'; // Default PIN jika tidak ada di config

$is_admin_pin_authenticated = isset($_SESSION['admin_pin_authenticated']) && $_SESSION['admin_pin_authenticated'] === true;
$pin_login_error = '';

// Proses Logout
if (isset($_GET['admin_logout'])) {
    unset($_SESSION['admin_pin_authenticated']);
    session_destroy(); // Hancurkan semua data session
    header("Location: admin_panel.php"); // Redirect ke halaman login PIN
    exit;
}

// Proses Submit PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login_pin_submit'])) {
    $entered_pin = trim($_POST['admin_pin_input']);
    if (password_verify($entered_pin, $correct_admin_pin) || $entered_pin === $correct_admin_pin) { // Cek plain text jika belum di-hash
        $_SESSION['admin_pin_authenticated'] = true;
        header("Location: admin_panel.php"); // Redirect untuk membersihkan POST dan refresh state
        exit;
    } else {
        $pin_login_error = 'PIN yang Anda masukkan salah.';
    }
}

// Jika belum terautentikasi, tampilkan form PIN
if (!$is_admin_pin_authenticated) {
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel Login - YazPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 dark:bg-slate-900 flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-sm bg-white dark:bg-slate-800 p-8 rounded-xl shadow-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-primary dark:text-gold tracking-tight">
                Yaz<span class="font-light">Pay</span>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Admin Panel Login</p>
        </div>

        <?php if ($pin_login_error): ?>
            <div class="mb-4 p-3 text-sm text-red-700 bg-red-100 dark:bg-red-700 dark:text-red-100 rounded-lg text-center" role="alert">
                <?php echo htmlspecialchars($pin_login_error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_panel.php" class="space-y-6">
            <div>
                <label for="admin_pin_input" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Masukkan PIN Admin</label>
                <input type="password" id="admin_pin_input" name="admin_pin_input" inputmode="numeric" pattern="[0-9]*"
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary dark:focus:ring-gold dark:bg-slate-700 dark:text-white text-center text-lg tracking-widest"
                       required autofocus maxlength="6">
            </div>
            <button type="submit" name="admin_login_pin_submit"
                    class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-3 px-4 rounded-lg shadow-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-light dark:focus:ring-offset-slate-800">
                Login
            </button>
        </form>
    </div>
</body>
</html>
<?php
    exit; // Hentikan eksekusi lebih lanjut jika belum login
}

// Jika sudah terautentikasi, lanjutkan dengan HTML admin panel yang sudah ada:
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - YazPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], },
                    colors: {
                        primary: '#ee4d2d', 'primary-light': '#ff6f61', 'primary-dark': '#d94325',
                        gold: '#FFD700',
                        slate: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a', }
                    },
                },
            },
            plugins: [],
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        #sidebar { transition: transform 0.3s ease-in-out; overflow-y: auto; display: flex; flex-direction: column; }
        .nav-link.active { background-color: #ee4d2d; color: white; }
        .dark .nav-link.active { background-color: #FFD700; color: #0f172a; }
        .nav-link i.fa-fw { text-align: center; }
        .nav-link:hover i { transform: scale(1.1); }
        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out, padding-top 0.3s ease-in-out, padding-bottom 0.3s ease-in-out; padding-left: 1.5rem; }
        .submenu.open { max-height: 500px; padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .submenu a { padding-top: 0.5rem; padding-bottom: 0.5rem; font-size: 0.875rem; }
        .submenu a i.fa-fw { margin-right: 0.75rem; }
        .submenu-toggle-icon { transition: transform 0.3s ease-in-out; margin-left: auto; }
        .submenu-toggle-icon.open { transform: rotate(90deg); }
        #content-area { transition: opacity 0.3s ease-in-out; }
        .content-loading { opacity: 0.3; }
        .content-loaded { opacity: 1; }
        #sidebar::-webkit-scrollbar, main#content-area::-webkit-scrollbar { width: 6px; height: 6px; }
        #sidebar::-webkit-scrollbar-track, main#content-area::-webkit-scrollbar-track { background: transparent; }
        #sidebar::-webkit-scrollbar-thumb, main#content-area::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 3px; }
        #sidebar::-webkit-scrollbar-thumb:hover, main#content-area::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
        .dark #sidebar::-webkit-scrollbar-thumb, .dark main#content-area::-webkit-scrollbar-thumb { background-color: #475569; }
        .dark #sidebar::-webkit-scrollbar-thumb:hover, .dark main#content-area::-webkit-scrollbar-thumb:hover { background-color: #334155; }
        #sidebar, main#content-area { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
        .dark #sidebar, .dark main#content-area { scrollbar-color: #475569 transparent; }
    </style>
</head>
<body class="bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200 flex min-h-screen">

    <aside id="sidebar" class="w-64 bg-white dark:bg-slate-800 h-screen p-4 shadow-xl fixed lg:sticky top-0 z-40 transform -translate-x-full lg:translate-x-0">
        <div class="text-center py-2 mb-6">
            <a href="#" class="text-3xl font-bold text-primary dark:text-gold tracking-tight nav-link" data-url="admin_dashboard_content.php">
                Yaz<span class="font-light">Pay</span>
            </a>
            <p class="text-xs text-slate-500 dark:text-slate-400">Admin Panel</p>
        </div>

        <nav class="flex-grow">
            <ul class="space-y-1">
                <li>
                    <a href="admin_dashboard_content.php" class="nav-link flex items-center py-2.5 px-4 rounded-lg transition duration-200 text-slate-700 dark:text-slate-300 hover:bg-primary-light hover:text-white dark:hover:bg-gold dark:hover:text-slate-900">
                        <i class="fas fa-tachometer-alt fa-fw w-6 mr-3"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="input/input.php" class="nav-link flex items-center py-2.5 px-4 rounded-lg transition duration-200 text-slate-700 dark:text-slate-300 hover:bg-primary-light hover:text-white dark:hover:bg-gold dark:hover:text-slate-900">
                        <i class="fas fa-plus-circle fa-fw w-6 mr-3"></i> Add Data
                    </a>
                </li>
                <li>
                    <a href="add_pelanggan.php" class="nav-link flex items-center py-2.5 px-4 rounded-lg transition duration-200 text-slate-700 dark:text-slate-300 hover:bg-primary-light hover:text-white dark:hover:bg-gold dark:hover:text-slate-900">
                        <i class="fas fa-user-plus fa-fw w-6 mr-3"></i> Add Pelanggan
                    </a>
                </li>
                <li>
                    <a href="display/display.php" class="nav-link flex items-center py-2.5 px-4 rounded-lg transition duration-200 text-slate-700 dark:text-slate-300 hover:bg-primary-light hover:text-white dark:hover:bg-gold dark:hover:text-slate-900">
                        <i class="fas fa-list-alt fa-fw w-6 mr-3"></i> Data Paket
                    </a>
                </li>
                <li>
                    <button type="button" class="submenu-toggle w-full flex items-center justify-between py-2.5 px-4 rounded-lg transition duration-200 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 focus:outline-none">
                        <span class="flex items-center">
                            <i class="fas fa-cog fa-fw w-6 mr-3"></i> Settings
                        </span>
                        <i class="fas fa-chevron-right submenu-toggle-icon fa-xs"></i>
                    </button>
                    <ul class="submenu space-y-1">
                        <li>
                            <a href="settings_general.php" class="nav-link flex items-center py-2 px-4 rounded-md text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-800 dark:hover:text-slate-200">
                                <i class="fas fa-sliders-h fa-fw w-5 mr-2.5"></i> General
                            </a>
                        </li>
                        <li>
                            <a href="settings_github.php" class="nav-link flex items-center py-2 px-4 rounded-md text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-800 dark:hover:text-slate-200">
                                <i class="fab fa-github fa-fw w-5 mr-2.5"></i> GitHub API
                            </a>
                        </li>
                        <li>
                            <a href="settings_whatsapp.php" class="nav-link flex items-center py-2 px-4 rounded-md text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-800 dark:hover:text-slate-200">
                                <i class="fab fa-whatsapp fa-fw w-5 mr-2.5"></i> WhatsApp API
                            </a>
                        </li>
                         <li>
                            <a href="settings_profile.php" class="nav-link flex items-center py-2 px-4 rounded-md text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-800 dark:hover:text-slate-200">
                                <i class="fas fa-user-circle fa-fw w-5 mr-2.5"></i> Profile
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

        <div class="mt-auto pt-4 border-t border-slate-200 dark:border-slate-700 space-y-2">
            <a href="?admin_logout=1" class="w-full flex items-center justify-center text-sm py-2 px-4 rounded-lg bg-red-500 hover:bg-red-600 text-white transition-colors duration-200">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
            <button id="darkToggleSidebar" class="w-full flex items-center justify-center text-sm py-2 px-4 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors duration-200">
                <i class="fas fa-moon mr-2"></i> <span id="darkToggleText">Dark Mode</span>
            </button>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white dark:bg-slate-800 shadow-md p-3 lg:hidden flex justify-between items-center sticky top-0 z-30 h-16">
            <button id="sidebarToggle" class="text-slate-600 dark:text-slate-300 focus:outline-none p-2 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            <h1 id="pageTitle" class="text-lg font-semibold text-primary dark:text-gold">Dashboard</h1>
            <div class="w-8"></div> </header>

        <main id="content-area" class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
            <div class="flex items-center justify-center h-full">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-3x text-primary dark:text-gold"></i>
                    <p class="mt-3 text-slate-500 dark:text-slate-400">Loading Content...</p>
                </div>
            </div>
        </main>
    </div>

    <script>
    $(document).ready(function() {
        const $contentArea = $('#content-area');
        const $sidebar = $('#sidebar');
        const $sidebarToggle = $('#sidebarToggle');
        const $pageTitle = $('#pageTitle');

        function loadContent(url, targetLinkElement) {
            // ... (fungsi loadContent yang sudah ada, pastikan memanggil fungsi inisialisasi JS jika ada)
            $contentArea.html('<div class="flex items-center justify-center h-full"><div class="text-center"><i class="fas fa-spinner fa-spin fa-3x text-primary dark:text-gold"></i><p class="mt-3 text-slate-500 dark:text-slate-400">Loading Content...</p></div></div>');
            $contentArea.removeClass('content-loaded').addClass('content-loading');

            $.ajax({
                url: url, type: 'GET', dataType: 'html', timeout: 10000,
                success: function(response) {
                    setTimeout(function() {
                        $contentArea.html(response);
                        $contentArea.removeClass('content-loading').addClass('content-loaded');
                        // Panggil fungsi inisialisasi spesifik halaman jika ada
                        if (typeof initializeInputForm === 'function' && url.includes('input/input.php')) {
                            window.callFromAdminPanel = true; initializeInputForm(); delete window.callFromAdminPanel;
                        } else if (typeof initializeDisplayData === 'function' && url.includes('display/display.php')) {
                            window.callFromAdminPanel = true; initializeDisplayData(); delete window.callFromAdminPanel;
                        } else if (typeof initializeSettingsFormScripts === 'function' && (url.includes('settings_github.php') || url.includes('settings_general.php'))) {
                            window.callFromAdminPanel = true; initializeSettingsFormScripts(); delete window.callFromAdminPanel;
                        }
                    }, 150);

                    $('.nav-link').removeClass('active');
                    if (targetLinkElement && $(targetLinkElement).hasClass('nav-link')) {
                        $(targetLinkElement).addClass('active');
                        $pageTitle.text($(targetLinkElement).clone().children('i').remove().end().text().trim());
                    }
                    if ($(window).width() < 1024) { $sidebar.addClass('-translate-x-full'); }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // ... (error handling yang sudah ada)
                     setTimeout(function() {
                        let errorMsg = '<div class="flex items-center justify-center h-full"><div class="text-center bg-red-100 dark:bg-red-900/30 p-8 rounded-lg shadow-xl max-w-md mx-auto">';
                        errorMsg += '<i class="fas fa-exclamation-triangle fa-3x text-red-500 dark:text-red-400 mb-4"></i>';
                        errorMsg += '<h3 class="text-xl font-semibold text-red-700 dark:text-red-300 mb-2">Failed to Load Content</h3>';
                        errorMsg += '<p class="text-slate-600 dark:text-slate-400 mb-1">Could not retrieve content from: <code class="text-sm bg-red-200 dark:bg-red-800 px-1 rounded">' + url + '</code></p>';
                        if (textStatus === 'timeout') {
                            errorMsg += '<p class="text-sm text-slate-500 dark:text-slate-500">The request timed out.</p>';
                        } else if (jqXHR.status === 0) {
                            errorMsg += '<p class="text-sm text-slate-500 dark:text-slate-500">Network error or CORS issue. Check browser console.</p>';
                        } else {
                             errorMsg += '<p class="text-sm text-slate-500 dark:text-slate-500">Status: ' + jqXHR.status + ' | Error: ' + errorThrown + '</p>';
                        }
                        errorMsg += '<button onclick="location.reload()" class="mt-6 px-5 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition duration-150 text-sm">Refresh Page</button>';
                        errorMsg += '</div></div>';
                        $contentArea.html(errorMsg);
                        $contentArea.removeClass('content-loading').addClass('content-loaded');
                    }, 150);
                    console.error("AJAX Error loading " + url + ": ", textStatus, errorThrown, jqXHR);
                }
            });
        }

        const defaultUrl = 'admin_dashboard_content.php';
        $(document).on('click', '.nav-link', function(e) { /* ... (event listener nav-link yang sudah ada) ... */
            e.preventDefault();
            const url = $(this).attr('href');
            const targetUrl = (url && url !== '#') ? url : $(this).data('url');
            if (targetUrl) {
                loadContent(targetUrl, this);
            }
        });
        $sidebarToggle.on('click', function(e) { /* ... (event listener sidebarToggle yang sudah ada) ... */
            e.stopPropagation();
            $sidebar.toggleClass('-translate-x-full');
        });
        $(document).on('click', function(event) { /* ... (event listener document click yang sudah ada) ... */
            if ($(window).width() < 1024) {
                if (!$sidebar.hasClass('-translate-x-full') && !$sidebar.is(event.target) && $sidebar.has(event.target).length === 0 && !$sidebarToggle.is(event.target) && $sidebarToggle.has(event.target).length === 0) {
                    $sidebar.addClass('-translate-x-full');
                }
            }
        });
        $('.submenu-toggle').on('click', function() { /* ... (event listener submenu-toggle yang sudah ada) ... */
            $(this).next('.submenu').toggleClass('open');
            $(this).find('.submenu-toggle-icon').toggleClass('open');
        });

        const darkToggleSidebar = $('#darkToggleSidebar'); /* ... (logika dark mode yang sudah ada) ... */
        const htmlElement = $('html');
        const darkToggleText = $('#darkToggleText');
        function applyTheme(isDark) {
            if (isDark) {
                htmlElement.addClass('dark');
                darkToggleSidebar.find('i').removeClass('fa-moon').addClass('fa-sun');
                darkToggleText.text('Light Mode');
            } else {
                htmlElement.removeClass('dark');
                darkToggleSidebar.find('i').removeClass('fa-sun').addClass('fa-moon');
                darkToggleText.text('Dark Mode');
            }
        }
        let isDarkMode = localStorage.getItem('adminDarkModeYazPay') === 'true';
        if (localStorage.getItem('adminDarkModeYazPay') === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            isDarkMode = true;
        }
        applyTheme(isDarkMode);
        darkToggleSidebar.on('click', function() {
            isDarkMode = !isDarkMode;
            applyTheme(isDarkMode);
            localStorage.setItem('adminDarkModeYazPay', isDarkMode);
        });

        // Muat konten default
        const initialLinkElement = $('.nav-link[href="' + defaultUrl + '"]');
        if (initialLinkElement.length) {
            loadContent(defaultUrl, initialLinkElement[0]);
        } else {
            // Fallback jika link default tidak ditemukan, mungkin muat dashboard secara langsung
            loadContent('admin_dashboard_content.php', null);
        }
    });
    </script>
</body>
</html>