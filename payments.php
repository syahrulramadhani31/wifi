<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Set page title
$page_title = 'Pembayaran - WiFi Payment System';

// Initialize variables
$error = '';
$success = '';
$paymentData = [];
$areas = getAllAreas();

// Get filter parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$area = isset($_GET['area']) && $_GET['area'] !== '' ? (int)$_GET['area'] : null;
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null; // paid or unpaid
$userId = isset($_GET['user']) && $_GET['user'] !== '' ? (int)$_GET['user'] : null;
$searchQuery = isset($_GET['q']) && $_GET['q'] !== '' ? $_GET['q'] : null;

// Handle delete payment - VERSI DIPERBAIKI
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $paymentId = (int)$_GET['delete'];
    
    // Periksa apakah pembayaran ada
    $checkQuery = "SELECT * FROM payments WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $paymentId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $_SESSION['error'] = "Pembayaran tidak ditemukan!";
    } else {
        $query = "DELETE FROM payments WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $paymentId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Pembayaran berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus pembayaran: " . $conn->error;
        }
    }
    
    // Redirect dengan parameter yang sama
    $redirectParams = [];
    if ($month != date('n')) $redirectParams['month'] = $month;
    if ($year != date('Y')) $redirectParams['year'] = $year;
    if ($area !== null) $redirectParams['area'] = $area;
    if ($status !== null) $redirectParams['status'] = $status;
    if ($userId !== null) $redirectParams['user'] = $userId;
    if ($searchQuery !== null) $redirectParams['q'] = $searchQuery;
    
    $redirectUrl = 'payments.php';
    if (!empty($redirectParams)) {
        $redirectUrl .= '?' . http_build_query($redirectParams);
    }
    
    header("Location: $redirectUrl");
    exit;
}

// Handle add payment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selectedUserId = $_POST['user_id'];
    $paymentMonth = $_POST['payment_month'];
    $paymentYear = $_POST['payment_year'];
    $amount = $_POST['amount'];
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $notes = $_POST['notes'] ?? '';
    
    // Retrieve filter params from hidden fields if present
    $filterMonth = isset($_POST['filter_month']) ? (int)$_POST['filter_month'] : $month;
    $filterYear = isset($_POST['filter_year']) ? (int)$_POST['filter_year'] : $year;
    $filterArea = isset($_POST['filter_area']) && $_POST['filter_area'] !== '' ? (int)$_POST['filter_area'] : $area;
    $filterStatus = isset($_POST['filter_status']) && $_POST['filter_status'] !== '' ? $_POST['filter_status'] : $status;
    $filterUser = isset($_POST['filter_user']) && $_POST['filter_user'] !== '' ? (int)$_POST['filter_user'] : $userId;
    
    // Check if user already paid for the selected month/year
    $query = "SELECT id FROM payments WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $selectedUserId, $paymentMonth, $paymentYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Pengguna sudah membayar untuk bulan " . getMonthName($paymentMonth) . " " . $paymentYear . "!";
    } else {
        // Insert payment
        $query = "INSERT INTO payments (user_id, month, year, amount, payment_date, notes) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiidss", $selectedUserId, $paymentMonth, $paymentYear, $amount, $paymentDate, $notes);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Pembayaran berhasil dicatat!";
            
            // Redirect with same filter params
            $redirectParams = array();
            if ($filterMonth != date('n')) $redirectParams['month'] = $filterMonth;
            if ($filterYear != date('Y')) $redirectParams['year'] = $filterYear;
            if ($filterArea !== null) $redirectParams['area'] = $filterArea;
            if ($filterStatus !== null) $redirectParams['status'] = $filterStatus;
            if ($filterUser !== null) $redirectParams['user'] = $filterUser;
            
            $redirectUrl = 'payments.php';
            if (!empty($redirectParams)) {
                $redirectUrl .= '?' . http_build_query($redirectParams);
            }
            
            header("Location: $redirectUrl");
            exit;
        } else {
            $error = "Gagal mencatat pembayaran: " . $conn->error;
        }
    }
}

// Query untuk mendapatkan data pembayaran dengan filter dan pencarian
$queryBase = "SELECT u.id as user_id, u.name, u.phone, u.monthly_fee, a.area_name, 
          CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END as has_paid,
          p.payment_date, p.amount, p.notes, p.id as payment_id
          FROM users u
          LEFT JOIN areas a ON u.area_id = a.id
          LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?";

$params = [$month, $year];
$types = "ii";
$whereConditions = [];

// Filter area
if ($area !== null) {
    $whereConditions[] = "u.area_id = ?";
    $params[] = $area;
    $types .= "i";
}

// Filter user
if ($userId !== null) {
    $whereConditions[] = "u.id = ?";
    $params[] = $userId;
    $types .= "i";
}

// Search berdasarkan nama, telepon, atau area
if ($searchQuery !== null) {
    $searchTerm = "%{$searchQuery}%";
    $whereConditions[] = "(u.name LIKE ? OR u.phone LIKE ? OR a.area_name LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Gabungkan kondisi WHERE
if (!empty($whereConditions)) {
    $queryBase .= " WHERE " . implode(" AND ", $whereConditions);
}

// Filter status (paid/unpaid)
if ($status !== null) {
    if ($status == 'paid') {
        $queryBase .= (empty($whereConditions) ? " WHERE " : " AND ") . "p.id IS NOT NULL";
    } else if ($status == 'unpaid') {
        $queryBase .= (empty($whereConditions) ? " WHERE " : " AND ") . "p.id IS NULL";
    }
}

$queryBase .= " ORDER BY u.name";

$stmt = $conn->prepare($queryBase);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $paymentData[] = $row;
}

// Query untuk statistik
$queryCount = "SELECT 
              SUM(CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END) as paid_count,
              SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) as unpaid_count,
              COUNT(u.id) as total_count,
              SUM(CASE WHEN p.id IS NOT NULL THEN p.amount ELSE 0 END) as total_paid
              FROM users u
              LEFT JOIN areas a ON u.area_id = a.id
              LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?";

$countParams = [$month, $year];
$countTypes = "ii";
$countWhereConditions = [];

// Filter area untuk statistik
if ($area !== null) {
    $countWhereConditions[] = "u.area_id = ?";
    $countParams[] = $area;
    $countTypes .= "i";
}

// Filter user untuk statistik
if ($userId !== null) {
    $countWhereConditions[] = "u.id = ?";
    $countParams[] = $userId;
    $countTypes .= "i";
}

// Search untuk statistik
if ($searchQuery !== null) {
    $searchTerm = "%{$searchQuery}%";
    $countWhereConditions[] = "(u.name LIKE ? OR u.phone LIKE ? OR a.area_name LIKE ?)";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= "sss";
}

// Gabungkan kondisi WHERE untuk statistik
if (!empty($countWhereConditions)) {
    $queryCount .= " WHERE " . implode(" AND ", $countWhereConditions);
}

$stmtCount = $conn->prepare($queryCount);
$stmtCount->bind_param($countTypes, ...$countParams);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$countData = $resultCount->fetch_assoc();

// Handle null values in count data
$countData['paid_count'] = $countData['paid_count'] ?? 0;
$countData['unpaid_count'] = $countData['unpaid_count'] ?? 0;
$countData['total_count'] = $countData['total_count'] ?? 0;
$countData['total_paid'] = $countData['total_paid'] ?? 0;

// Get all users for payment form
$query = "SELECT u.id, u.name, u.monthly_fee, a.area_name, u.phone
          FROM users u
          LEFT JOIN areas a ON u.area_id = a.id
          ORDER BY u.name";
$result = $conn->query($query);
$allUsers = [];
while ($row = $result->fetch_assoc()) {
    $allUsers[] = $row;
}

// Add chart.js
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Flash messages from session
// Pindahkan unset($_SESSION['success']) dan unset($_SESSION['error']) ke setelah blok HTML yang menampilkan alert
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
}

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php
        unset($_SESSION['success']); // Pindahkan unset ke sini
    endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php
        unset($_SESSION['error']);   // Pindahkan unset ke sini
    endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="dashboard-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <h4 class="mb-0">Pengelolaan Pembayaran</h4>
        </div>
        
        <div class="d-flex">
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="fas fa-plus-circle me-2"></i> Catat Pembayaran
            </button>
            
            <button class="btn btn-primary filter-toggle" id="filterToggle">
                <i class="fas fa-filter me-2"></i>
                <span>Filter Data</span>
                <i class="fas fa-chevron-down ms-2" id="toggleIcon"></i>
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form action="payments.php" method="GET" class="search-form">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control" name="q" id="searchInput" 
                                   placeholder="Cari nama pengguna, nomor telepon, atau area..." 
                                   value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                            
                            <?php if (isset($_GET['month']) && $_GET['month'] != date('n')): ?>
                                <input type="hidden" name="month" value="<?php echo (int)$_GET['month']; ?>">
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['year']) && $_GET['year'] != date('Y')): ?>
                                <input type="hidden" name="year" value="<?php echo (int)$_GET['year']; ?>">
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['area']) && $_GET['area'] !== ''): ?>
                                <input type="hidden" name="area" value="<?php echo (int)$_GET['area']; ?>">
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['status']) && $_GET['status'] !== ''): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($_GET['status']); ?>">
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['user']) && $_GET['user'] !== ''): ?>
                                <input type="hidden" name="user" value="<?php echo (int)$_GET['user']; ?>">
                            <?php endif; ?>
                            
                            <button class="btn btn-primary" type="submit">
                                Cari
                            </button>
                            <?php if (isset($_GET['q']) && $_GET['q'] !== ''): ?>
                                <a href="<?php 
                                    $params = $_GET;
                                    unset($params['q']);
                                    echo 'payments.php' . (!empty($params) ? '?' . http_build_query($params) : '');
                                ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4" id="filterSection" style="display: none;">
        <div class="card-body">
            <form action="payments.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="month" class="form-label">Bulan</label>
                    <select class="form-select" id="month" name="month">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $month) ? 'selected' : ''; ?>>
                                <?php echo getMonthName($i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Tahun</label>
                    <select class="form-select" id="year" name="year">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $year) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                        </select>
                </div>
                <div class="col-md-3">
                    <label for="area" class="form-label">Area</label>
                    <select class="form-select" id="area" name="area">
                        <option value="">Semua Area</option>
                        <?php foreach ($areas as $areaItem): ?>
                            <option value="<?php echo $areaItem['id']; ?>" <?php echo ($area !== null && $areaItem['id'] == $area) ? 'selected' : ''; ?>>
                                <?php echo $areaItem['area_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="paid" <?php echo ($status == 'paid') ? 'selected' : ''; ?>>Sudah Bayar</option>
                        <option value="unpaid" <?php echo ($status == 'unpaid') ? 'selected' : ''; ?>>Belum Bayar</option>
                    </select>
                </div>
                <?php if ($userId): ?>
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="keepUser" name="user" value="<?php echo $userId; ?>" checked>
                        <label class="form-check-label" for="keepUser">
                            Filter untuk pengguna tertentu
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i> Tampilkan
                        </button>
                        <a href="payments.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i> Reset Filter
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">TOTAL PENGGUNA</div>
                            <div class="stats-value"><?php echo $countData['total_count']; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--secondary-color);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">SUDAH BAYAR</div>
                            <div class="stats-value"><?php echo $countData['paid_count']; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo ($countData['total_count'] > 0) ? ($countData['paid_count'] / $countData['total_count'] * 100) : 0; ?>%">
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="payments.php?status=paid&month=<?php echo $month; ?>&year=<?php echo $year; ?><?php echo $area !== null ? '&area='.$area : ''; ?><?php echo $userId !== null ? '&user='.$userId : ''; ?>" 
                            class="btn btn-sm btn-outline-success">
                            <i class="fas fa-eye me-1"></i> Lihat Detail
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--danger-color);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">BELUM BAYAR</div>
                            <div class="stats-value"><?php echo $countData['unpaid_count']; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" role="progressbar" 
                             style="width: <?php echo ($countData['total_count'] > 0) ? ($countData['unpaid_count'] / $countData['total_count'] * 100) : 0; ?>%">
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="payments.php?status=unpaid&month=<?php echo $month; ?>&year=<?php echo $year; ?><?php echo $area !== null ? '&area='.$area : ''; ?><?php echo $userId !== null ? '&user='.$userId : ''; ?>" 
                           class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-eye me-1"></i> Lihat Detail
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">TOTAL TERKUMPUL</div>
                            <div class="stats-value"><?php echo formatCurrency($countData['total_paid']); ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" role="progressbar" style="width: 100%"></div>
                    </div>
                    <div class="mt-3">
                        <a href="reports.php?month=<?php echo $month; ?>&year=<?php echo $year; ?><?php echo $area !== null ? '&area='.$area : ''; ?>" 
                           class="btn btn-sm btn-outline-info">
                            <i class="fas fa-file-alt me-1"></i> Lihat Laporan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i> Daftar Pembayaran
                <span class="badge bg-primary ms-2"><?php echo getMonthName($month); ?> <?php echo $year; ?></span>
                <?php if ($area !== null): ?>
                    <?php foreach ($areas as $areaItem): ?>
                        <?php if ($areaItem['id'] == $area): ?>
                            <span class="badge bg-secondary ms-2">Area: <?php echo $areaItem['area_name']; ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($status == 'paid'): ?>
                    <span class="badge bg-success ms-2">Status: Sudah Bayar</span>
                <?php elseif ($status == 'unpaid'): ?>
                    <span class="badge bg-danger ms-2">Status: Belum Bayar</span>
                <?php endif; ?>
                <?php if ($userId !== null): ?>
                    <?php foreach ($allUsers as $user): ?>
                        <?php if ($user['id'] == $userId): ?>
                            <span class="badge bg-info ms-2">Pengguna: <?php echo $user['name']; ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($searchQuery !== null): ?>
                    <span class="badge bg-warning text-dark ms-2">
                        <i class="fas fa-search me-1"></i> "<?php echo htmlspecialchars($searchQuery); ?>"
                    </span>
                <?php endif; ?>
            </h5>
            <span class="badge bg-info fs-6"><?php echo count($paymentData); ?> Data</span>
        </div>
        <div class="card-body">
            <?php if (count($paymentData) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>No. HP</th>
                            <th>Area</th>
                            <th>Status</th>
                            <th>Iuran</th>
                            <th>Tgl. Bayar</th>
                            <th>Catatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentData as $index => $payment): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo $payment['name']; ?></td>
                                <td><?php echo $payment['phone'] ?? '-'; ?></td>
                                <td>
                                    <?php if (isset($payment['area_name']) && $payment['area_name']): ?>
                                        <span class="badge bg-light text-dark"><?php echo $payment['area_name']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tanpa Area</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment['has_paid']): ?>
                                        <span class="badge bg-success">Sudah Bayar</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Belum Bayar</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($payment['amount'] ?? $payment['monthly_fee']); ?></td>
                                <td><?php echo $payment['payment_date'] ?? '-'; ?></td>
                                <td><?php echo $payment['notes'] ?? '-'; ?></td>
                                <td>
                                    <?php if ($payment['has_paid']): ?>
                                        <a href="#" class="btn btn-sm btn-danger delete-payment"
                                           data-bs-toggle="modal" data-bs-target="#deletePaymentModal"
                                           data-id="<?php echo $payment['payment_id']; ?>"
                                           data-name="<?php echo $payment['name']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-primary record-payment"
                                                data-bs-toggle="modal" data-bs-target="#addPaymentModal"
                                                data-id="<?php echo $payment['user_id']; ?>"
                                                data-fee="<?php echo $payment['monthly_fee']; ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Tidak ada data pembayaran untuk filter yang dipilih.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Status Pembayaran</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="paymentStatusChart" height="300"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex flex-column justify-content-center h-100">
                                <div class="mb-4">
                                    <h6 class="fw-bold">Persentase Pembayaran:</h6>
                                    <div class="progress mt-2" style="height: 20px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo ($countData['total_count'] > 0) ? ($countData['paid_count'] / $countData['total_count'] * 100) : 0; ?>%" 
                                             aria-valuenow="<?php echo ($countData['total_count'] > 0) ? ($countData['paid_count'] / $countData['total_count'] * 100) : 0; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo ($countData['total_count'] > 0) ? round(($countData['paid_count'] / $countData['total_count'] * 100)) : 0; ?>%
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-2">
                                        <span class="text-success fw-bold"><?php echo $countData['paid_count']; ?></span> dari 
                                        <span class="fw-bold"><?php echo $countData['total_count']; ?></span> pengguna telah membayar 
                                        untuk <?php echo getMonthName($month); ?> <?php echo $year; ?>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="fw-bold">Status Pembayaran:</h6>
                                    <div class="d-flex justify-content-between mt-2">
                                        <span class="text-success"><i class="fas fa-circle me-1"></i> Sudah Bayar</span>
                                        <span class="fw-bold"><?php echo $countData['paid_count']; ?> pengguna</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <span class="text-danger"><i class="fas fa-circle me-1"></i> Belum Bayar</span>
                                        <span class="fw-bold"><?php echo $countData['unpaid_count']; ?> pengguna</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Ringkasan Pembayaran</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>Periode</th>
                            <td class="text-end"><?php echo getMonthName($month); ?> <?php echo $year; ?></td>
                        </tr>
                        <tr>
                            <th>Total Pengguna</th>
                            <td class="text-end"><?php echo $countData['total_count']; ?></td>
                        </tr>
                        <tr>
                            <th>Sudah Bayar</th>
                            <td class="text-end">
                                <?php echo $countData['paid_count']; ?> 
                                (<?php echo ($countData['total_count'] > 0) ? round(($countData['paid_count'] / $countData['total_count'] * 100)) : 0; ?>%)
                            </td>
                        </tr>
                        <tr>
                            <th>Belum Bayar</th>
                            <td class="text-end">
                                <?php echo $countData['unpaid_count']; ?>
                                (<?php echo ($countData['total_count'] > 0) ? round(($countData['unpaid_count'] / $countData['total_count'] * 100)) : 0; ?>%)
                            </td>
                        </tr>
                        <tr>
                            <th>Target Pendapatan</th>
                            <td class="text-end">
                                <?php
                                    $targetIncome = 0;
                                    foreach ($paymentData as $payment) {
                                        $targetIncome += $payment['monthly_fee'];
                                    }
                                    echo formatCurrency($targetIncome);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Terkumpul</th>
                            <td class="text-end">
                                <?php echo formatCurrency($countData['total_paid']); ?>
                                (<?php echo ($targetIncome > 0) ? round(($countData['total_paid'] / $targetIncome * 100)) : 0; ?>%)
                            </td>
                        </tr>
                    </table>
                    <div class="mt-3">
                        <a href="reports.php?month=<?php echo $month; ?>&year=<?php echo $year; ?><?php echo $area !== null ? '&area='.$area : ''; ?>" class="btn btn-info w-100">
                            <i class="fas fa-file-export me-2"></i> Buat Laporan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Catat Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="payments.php" method="POST">
                <div class="modal-body">
                    <?php if ($month != date('n')): ?>
                        <input type="hidden" name="filter_month" value="<?php echo $month; ?>">
                    <?php endif; ?>
                    <?php if ($year != date('Y')): ?>
                        <input type="hidden" name="filter_year" value="<?php echo $year; ?>">
                    <?php endif; ?>
                    <?php if ($area !== null): ?>
                        <input type="hidden" name="filter_area" value="<?php echo $area; ?>">
                    <?php endif; ?>
                    <?php if ($status !== null): ?>
                        <input type="hidden" name="filter_status" value="<?php echo $status; ?>">
                    <?php endif; ?>
                    <?php if ($userId !== null): ?>
                        <input type="hidden" name="filter_user" value="<?php echo $userId; ?>">
                    <?php endif; ?>
                    <?php if ($searchQuery !== null): ?>
                        <input type="hidden" name="filter_q" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label for="user_search" class="form-label fw-medium">Pengguna</label>
                        
                        <div class="position-relative search-container mb-2">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0" 
                                       id="user_search" placeholder="Cari nama pengguna..." autocomplete="off">
                            </div>
                            <div id="searchResults" class="search-results d-none"></div>
                        </div>
                        
                        <div class="dropdown-select">
                            <input type="hidden" name="user_id" id="user_id_hidden" required>
                            <div class="form-select dropdown-toggle" id="selectedUserDisplay" data-bs-toggle="dropdown" aria-expanded="false">
                                Pilih Pengguna
                            </div>
                            <ul class="dropdown-menu w-100" id="userDropdown">
                                <?php foreach ($allUsers as $user): ?>
                                    <li>
                                        <a class="dropdown-item user-option" href="#" 
                                           data-id="<?php echo $user['id']; ?>"
                                           data-fee="<?php echo $user['monthly_fee']; ?>"
                                           data-name="<?php echo addslashes($user['name']); ?>"
                                           data-area="<?php echo htmlspecialchars($user['area_name'] ?? 'Tanpa Area'); ?>"
                                           data-search="<?php echo strtolower($user['name'].' '.$user['phone'].' '.($user['area_name'] ?? 'Tanpa Area')); ?>">
                                            <?php echo $user['name']; ?>
                                            <span class="text-muted small">
                                                (<?php echo isset($user['area_name']) && $user['area_name'] ? $user['area_name'] : 'Tanpa Area'; ?>)
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="form-text">Ketik nama pengguna untuk mencari</div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="payment_month" class="form-label fw-medium">Bulan</label>
                            <select class="form-select" id="payment_month" name="payment_month" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == $month) ? 'selected' : ''; ?>>
                                        <?php echo getMonthName($i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="payment_year" class="form-label fw-medium">Tahun</label>
                            <select class="form-select" id="payment_year" name="payment_year" required>
                                <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == $year) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="amount" class="form-label fw-medium">Jumlah</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="amount" name="amount" required min="0" step="1000">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="payment_date" class="form-label fw-medium">Tanggal Pembayaran</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="notes" class="form-label fw-medium">Catatan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deletePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus data pembayaran dari <strong id="deletePaymentName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> Tindakan ini tidak dapat dibatalkan.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="confirmDeletePayment" class="btn btn-danger">Hapus</button> 
            </div>
        </div>
    </div>
</div>

<style>
.search-container {
    position: relative;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 250px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.search-result-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f2f2f2;
}

.search-result-item:hover {
    background-color: #f8f9fa;
}

.search-result-item.active {
    background-color: #e9ecef;
}

.search-result-name {
    font-weight: 500;
}

.search-result-area {
    font-size: 0.85em;
    color: #6c757d;
}

.dropdown-select {
    position: relative;
}

.dropdown-select .dropdown-menu {
    width: 100%;
    max-height: 250px;
    overflow-y: auto;
}

.dropdown-select .dropdown-item {
    white-space: normal;
    padding: 0.5rem 1rem;
}

#filterSection {
    transition: all 0.3s ease-in-out;
    overflow: hidden;
    transform-origin: top;
}

#filterSection[style*="display: block"] {
    animation: slideDown 0.3s forwards;
}

#filterSection[style*="display: none"] {
    animation: slideUp 0.3s forwards;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}

.filter-toggle {
    background-color: #1a73e8;
    color: white;
    border-radius: 8px;
    padding: 10px 15px;
    font-weight: 500;
    display: flex;
    align-items: center;
    border: none;
    transition: all 0.3s ease;
}

.filter-toggle:hover {
    background-color: #1967d2;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.filter-toggle:active, .filter-toggle.active {
    background-color: #185abc;
}

.filter-toggle i.fas {
    font-size: 14px;
}

.search-form {
    position: relative;
}

.search-form .input-group {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.search-form .form-control {
    border-left: 0;
    padding-left: 5px;
    height: 46px;
}

.search-form .input-group-text {
    background-color: #fff;
    border-right: 0;
}

.search-form .btn {
    padding-left: 20px;
    padding-right: 20px;
}

.search-highlight {
    background-color: #fff3cd;
    padding: 0 2px;
    border-radius: 2px;
}

.modal-header {
    border-bottom: 1px solid #eaeaea;
}

.modal-footer {
    border-top: 1px solid #eaeaea;
}

.form-label.fw-medium {
    font-weight: 500;
}

.stats-card {
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
}

.stats-title {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.stats-value {
    font-size: 1.5rem;
    font-weight: 600;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle filter section
    const filterToggle = document.getElementById('filterToggle');
    const filterSection = document.getElementById('filterSection');
    const toggleIcon = document.getElementById('toggleIcon');
    
    // Deteksi parameter filter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('area') || urlParams.has('status') || urlParams.has('user') || 
        (urlParams.has('month') && urlParams.get('month') != <?php echo date('n'); ?>) || 
        (urlParams.has('year') && urlParams.get('year') != <?php echo date('Y'); ?>)) {
        filterSection.style.display = 'block';
        toggleIcon.classList.remove('fa-chevron-down');
        toggleIcon.classList.add('fa-chevron-up');
        filterToggle.classList.add('active');
    }
    
    filterToggle.addEventListener('click', function() {
        if (filterSection.style.display === 'none') {
            filterSection.style.display = 'block';
            toggleIcon.classList.remove('fa-chevron-down');
            toggleIcon.classList.add('fa-chevron-up');
            filterToggle.classList.add('active');
        } else {
            filterSection.style.display = 'none';
            toggleIcon.classList.remove('fa-chevron-up');
            toggleIcon.classList.add('fa-chevron-down');
            filterToggle.classList.remove('active');
        }
    });
    
    // Payment Status Chart
    const paymentCtx = document.getElementById('paymentStatusChart').getContext('2d');
    const paymentChart = new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: ['Sudah Bayar', 'Belum Bayar'],
            datasets: [{
                data: [<?php echo $countData['paid_count']; ?>, <?php echo $countData['unpaid_count']; ?>],
                backgroundColor: ['rgba(28, 200, 138, 0.7)', 'rgba(231, 74, 59, 0.7)'],
                borderColor: ['#1cc88a', '#e74a3b'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.formattedValue;
                            const dataset = context.dataset;
                            const total = dataset.data.reduce((acc, data) => acc + data, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${label}: ${value} pengguna (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });

    // Search user functionality
    const userSearch = document.getElementById('user_search');
    const searchResults = document.getElementById('searchResults');
    const selectedUserDisplay = document.getElementById('selectedUserDisplay');
    const userIdHidden = document.getElementById('user_id_hidden');
    const amountInput = document.getElementById('amount');
    const userOptions = Array.from(document.querySelectorAll('.user-option'));

    // Function to filter users based on search term
    function filterUsers(searchTerm) {
        searchTerm = searchTerm.toLowerCase().trim();
        
        // Clear previous results
        searchResults.innerHTML = '';
        
        if (!searchTerm) {
            searchResults.classList.add('d-none');
            return;
        }
        
        // Filter users by name, phone, or area
        const filteredUsers = userOptions.filter(option => {
            const searchData = option.getAttribute('data-search');
            return searchData && searchData.includes(searchTerm);
        });
        
        if (filteredUsers.length > 0) {
            searchResults.classList.remove('d-none');
            
            filteredUsers.forEach(user => {
                const name = user.getAttribute('data-name');
                const area = user.getAttribute('data-area');
                const id = user.getAttribute('data-id');
                const fee = user.getAttribute('data-fee');
                
                const resultItem = document.createElement('div');
                resultItem.className = 'search-result-item';
                resultItem.innerHTML = `
                    <div class="search-result-name">${name}</div>
                    <div class="search-result-area">${area}</div>
                `;
                
                resultItem.addEventListener('click', function() {
                    selectUser(id, name, area, fee);
                    searchResults.classList.add('d-none');
                    userSearch.value = name;
                });
                
                searchResults.appendChild(resultItem);
            });
        } else {
            searchResults.classList.remove('d-none');
            searchResults.innerHTML = '<div class="search-result-item">Pengguna tidak ditemukan</div>';
        }
    }
    
    // Function to select a user
    function selectUser(id, name, area, fee) {
        userIdHidden.value = id;
        selectedUserDisplay.textContent = `${name} (${area})`;
        selectedUserDisplay.classList.add('selected');
        amountInput.value = fee;
    }
    
    // Event listener for search input
    userSearch.addEventListener('input', function() {
        filterUsers(this.value);
    });
    
    // Event listener for user options in dropdown
    userOptions.forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const area = this.getAttribute('data-area');
            const fee = this.getAttribute('data-fee');
            
            selectUser(id, name, area, fee);
            userSearch.value = name;
        });
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!userSearch.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('d-none');
        }
    });
    
    // Record payment buttons functionality
    const recordButtons = document.querySelectorAll('.record-payment');
    recordButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const fee = this.getAttribute('data-fee');
            
            // Find user in options
            const userOption = userOptions.find(option => option.getAttribute('data-id') === userId);
            if (userOption) {
                const name = userOption.getAttribute('data-name');
                const area = userOption.getAttribute('data-area');
                
                // Set values
                selectUser(userId, name, area, fee);
                userSearch.value = name;
                
                // Focus on next field
                setTimeout(() => {
                    document.getElementById('payment_month').focus();
                }, 500);
            }
        });
    });
    
    // Initialize modal with user data if provided
    $('#addPaymentModal').on('shown.bs.modal', function() {
        const userParam = "<?php echo $userId; ?>";
        
        if (userParam) {
            const userOption = userOptions.find(option => option.getAttribute('data-id') === userParam);
            if (userOption) {
                const id = userOption.getAttribute('data-id');
                const name = userOption.getAttribute('data-name');
                const area = userOption.getAttribute('data-area');
                const fee = userOption.getAttribute('data-fee');
                
                selectUser(id, name, area, fee);
                userSearch.value = name;
            }
        }
        
        userSearch.focus();
    });
    
    // Handle delete payment (Updated for button click)
    // Inisialisasi modal Bootstrap sebelum digunakan
    const deletePaymentModalElement = document.getElementById('deletePaymentModal');
    const deletePaymentModal = new bootstrap.Modal(deletePaymentModalElement);

    const deleteButtons = document.querySelectorAll('.delete-payment');
    const confirmDeletePaymentBtn = document.getElementById('confirmDeletePayment');

    let currentPaymentIdToDelete = null; // Variabel untuk menyimpan ID pembayaran yang akan dihapus

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            currentPaymentIdToDelete = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            
            document.getElementById('deletePaymentName').textContent = userName;
            // Tampilkan modal secara eksplisit
            deletePaymentModal.show(); // [New] Pastikan modal ditampilkan saat tombol delete diklik
        });
    });

    confirmDeletePaymentBtn.addEventListener('click', function() {
        if (currentPaymentIdToDelete) {
            const params = new URLSearchParams();
            params.append('delete', currentPaymentIdToDelete);
            
            // Add active filter parameters for redirect
            <?php if ($month != date('n')): ?>
                params.append('month', <?php echo $month; ?>);
            <?php endif; ?>
            
            <?php if ($year != date('Y')): ?>
                params.append('year', <?php echo $year; ?>);
            <?php endif; ?>
            
            <?php if ($area !== null): ?>
                params.append('area', <?php echo $area; ?>);
            <?php endif; ?>
            
            <?php if ($status !== null): ?>
                params.append('status', '<?php echo $status; ?>');
            <?php endif; ?>
            
            <?php if ($userId !== null): ?>
                params.append('user', <?php echo $userId; ?>);
            <?php endif; ?>
            
            <?php if ($searchQuery !== null): ?>
                params.append('q', '<?php echo urlencode($searchQuery); ?>');
            <?php endif; ?>

            // Perform the redirect
            window.location.href = 'payments.php?' + params.toString();
        }
        deletePaymentModal.hide(); // Sembunyikan modal setelah aksi (penting agar tidak macet)
    });
    
    // Highlight search results
    const searchTerm = "<?php echo $searchQuery ? addslashes($searchQuery) : ''; ?>";
    if (searchTerm) {
        const tableContent = document.querySelectorAll('.table td:nth-child(2), .table td:nth-child(3), .table td:nth-child(4)');
        
        tableContent.forEach(cell => {
            if (!cell.innerHTML.includes('badge')) { // Avoid highlighting inside badges
                const regex = new RegExp('(' + searchTerm + ')', 'gi');
                cell.innerHTML = cell.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
            }
        });
        
        // Focus on search input when page loads
        document.getElementById('searchInput').focus();
    }
});
</script>

<script>
// Tambahkan kode ini di bagian bawah file payments.php sebelum tag penutup </body>
document.addEventListener('DOMContentLoaded', function() {
    // Tangani tombol delete
    const deleteButtons = document.querySelectorAll('.btn-danger[data-bs-toggle="modal"]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const paymentId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            
            // Update teks pada modal
            document.getElementById('deletePaymentName').textContent = userName;
            
            // Update link pada tombol konfirmasi hapus
            const confirmBtn = document.getElementById('confirmDeletePayment');
            
            // Bangun URL dengan parameter current filter
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            
            let deleteUrl = `delete_payment.php?id=${paymentId}`;
            
            // Tambahkan parameter filter yang ada
            ['month', 'year', 'area', 'status', 'user', 'q'].forEach(param => {
                if (params.has(param)) {
                    deleteUrl += `&${param}=${params.get(param)}`;
                }
            });
            
            // Update tombol dengan URL baru
            confirmBtn.setAttribute('data-url', deleteUrl);
        });
    });
    
    // Handler untuk tombol konfirmasi hapus
    const confirmDeleteBtn = document.getElementById('confirmDeletePayment');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            const deleteUrl = this.getAttribute('data-url');
            if (deleteUrl) {
                window.location.href = deleteUrl;
            }
        });
    }
});
</script>

<?php
include 'includes/footer.php';
?>