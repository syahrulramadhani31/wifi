<?php
// Identifikasi halaman aktif
$current_page = basename($_SERVER['PHP_SELF']);

// Pastikan variabel page_title sudah didefinisikan sebelumnya
$page_title = $page_title ?? 'WiFi Payment System';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body>
    <header class="app-header">
        <div class="container-fluid">
            <div class="d-flex align-items-center py-3">
                <button class="mobile-menu-btn me-3" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="brand-container">
                    <div class="brand-logo">
                        <i class="fas fa-wifi"></i>
                    </div>
                    <div class="brand-text">
                        <span class="brand-name">WIFI PAYMENT</span>
                        <span class="brand-tagline">Sistem Manajemen Pembayaran</span>
                    </div>
                </div>

                <ul class="nav app-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                            <i class="fas fa-users"></i> Pengguna
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>" href="payments.php">
                            <i class="fas fa-money-bill-wave"></i> Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'areas.php') ? 'active' : ''; ?>" href="areas.php">
                            <i class="fas fa-map-marker-alt"></i> Area
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                            <i class="fas fa-chart-line"></i> Laporan
                        </a>
                    </li>
                </ul>

                <div class="header-actions">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <span class="d-none d-md-inline"><?php echo $_SESSION['username'] ?? 'Admin'; ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="change_password.php">
                                <i class="fas fa-key me-2"></i> Ubah Password
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?logout=1">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="mobile-drawer" id="mobileDrawer">
        <div class="drawer-header">
            <div class="brand-container">
                <div class="brand-logo">
                    <i class="fas fa-wifi"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-name">WIFI PAYMENT</span>
                </div>
            </div>
            <button class="drawer-close" id="drawerClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <ul class="nav flex-column drawer-nav">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i> Pengguna
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>" href="payments.php">
                    <i class="fas fa-money-bill-wave"></i> Pembayaran
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'areas.php') ? 'active' : ''; ?>" href="areas.php">
                    <i class="fas fa-map-marker-alt"></i> Area
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-line"></i> Laporan
                </a>
            </li>
        </ul>

        <div class="drawer-footer">
            <a href="index.php?logout=1" class="btn btn-danger w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <div class="drawer-backdrop" id="drawerBackdrop"></div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="change_password.php" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Password Sekarang</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <span class="alert-icon">
                        <i class="fas fa-check-circle me-2"></i>
                    </span>
                    <div><?php echo $success; ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <span class="alert-icon">
                        <i class="fas fa-exclamation-circle me-2"></i>
                    </span>
                    <div><?php echo $error; ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Bootstrap JS Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Add shadow on scroll
            window.addEventListener('scroll', function() {
                const header = document.querySelector('.app-header');
                if (window.scrollY > 10) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });

            // Perbaikan untuk memastikan dropdown berfungsi
            document.addEventListener('DOMContentLoaded', function() {
                // Inisialisasi dropdown
                var dropdownElementList = document.querySelectorAll('.dropdown-toggle');
                dropdownElementList.forEach(function(dropdownToggleEl) {
                    var dropdown = new bootstrap.Dropdown(dropdownToggleEl);
                });
            });
        </script>

        <!-- Extra JavaScript -->
        <?php if (isset($extra_js)) echo $extra_js; ?>
    </div>
</body>
</html>