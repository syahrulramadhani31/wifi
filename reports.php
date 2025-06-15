<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Set page title
$page_title = 'Laporan - WiFi Payment System';

// Initialize variables
$error = '';
$success = '';
$reportData = [];
$areas = getAllAreas();

// Get filter parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$area = isset($_GET['area']) ? (int)$_GET['area'] : null;
$reportType = isset($_GET['type']) ? $_GET['type'] : 'monthly';

// Generate report data
if ($reportType === 'monthly') {
    $reportData = generateMonthlyReport($month, $year, $area);
} elseif ($reportType === 'yearly') {
    $reportData = generateYearlyReport($year, $area);
} elseif ($reportType === 'area') {
    $reportData = generateAreaReport($month, $year);
} elseif ($reportType === 'unpaid') {
    $reportData = generateUnpaidReport($month, $year, $area);
}

// Get summary data
$summary = getReportSummary($month, $year, $area, $reportType);

// Handle report export
if (isset($_GET['export']) && $_GET['export'] === 'true') {
    if ($reportType === 'monthly') {
        exportMonthlyReport($reportData, $summary, $month, $year, $area);
    } elseif ($reportType === 'yearly') {
        exportYearlyReport($reportData, $summary, $year, $area);
    } elseif ($reportType === 'area') {
        exportAreaReport($reportData, $summary, $month, $year);
    } elseif ($reportType === 'unpaid') {
        exportUnpaidReport($reportData, $summary, $month, $year, $area);
    }
    exit;
}

// Add chart.js
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Header dengan judul laporan dan tombol filter -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="fas fa-chart-line me-2"></i> Laporan Pembayaran</h4>
        
        <div class="d-flex">
            <!-- Export Laporan button tetap ada -->
            <button type="button" class="btn btn-success me-2" id="exportButton">
                <i class="fas fa-file-export me-2"></i> Export Laporan
            </button>
            
            <!-- Tombol Filter sekarang menjadi toggle -->
            <button class="btn btn-primary" id="filterToggle">
                <i class="fas fa-filter me-2"></i>
                Filter Laporan
                <i class="fas fa-chevron-down ms-2" id="toggleIcon"></i>
            </button>
        </div>
    </div>
    
    <!-- Filter section dengan toggle display -->
    <div class="card mb-4" id="filterSection" style="display: none;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Filter Laporan</h5>
            </div>
            <form action="reports.php" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="type" class="form-label">Jenis Laporan</label>
                    <select class="form-select" id="type" name="type">
                        <option value="monthly" <?php echo ($reportType == 'monthly') ? 'selected' : ''; ?>>Laporan Bulanan</option>
                        <option value="yearly" <?php echo ($reportType == 'yearly') ? 'selected' : ''; ?>>Laporan Tahunan</option>
                        <option value="area" <?php echo ($reportType == 'area') ? 'selected' : ''; ?>>Laporan Per Area</option>
                        <option value="unpaid" <?php echo ($reportType == 'unpaid') ? 'selected' : ''; ?>>Laporan Tunggakan</option>
                    </select>
                </div>
                <div class="col-md-3 monthly-option yearly-option unpaid-option">
                    <label for="month" class="form-label">Bulan</label>
                    <select class="form-select" id="month" name="month">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $month) ? 'selected' : ''; ?>>
                                <?php echo getMonthName($i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 monthly-option yearly-option area-option unpaid-option">
                    <label for="year" class="form-label">Tahun</label>
                    <select class="form-select" id="year" name="year">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $year) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 monthly-option yearly-option unpaid-option">
                    <label for="area" class="form-label">Area</label>
                    <select class="form-select" id="area" name="area">
                        <option value="">Semua Area</option>
                        <?php foreach ($areas as $areaItem): ?>
                            <option value="<?php echo $areaItem['id']; ?>" <?php echo ($areaItem['id'] == $area) ? 'selected' : ''; ?>>
                                <?php echo $areaItem['area_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Tampilkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Summary Cards -->
    <div class="row mb-4">
        <!-- Total Pengguna -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">TOTAL PENGGUNA</div>
                            <div class="stats-value"><?php echo $summary['total_users']; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sudah Bayar -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--secondary-color);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">SUDAH BAYAR</div>
                            <div class="stats-value"><?php echo $summary['paid_users']; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" 
                            style="width: <?php echo ($summary['total_users'] > 0) ? ($summary['paid_users'] / $summary['total_users'] * 100) : 0; ?>%">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Belum Bayar -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--danger-color);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">BELUM BAYAR</div>
                            <div class="stats-value"><?php echo $summary['unpaid_users']; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" role="progressbar" 
                            style="width: <?php echo ($summary['total_users'] > 0) ? ($summary['unpaid_users'] / $summary['total_users'] * 100) : 0; ?>%">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Pendapatan -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">TOTAL PENDAPATAN</div>
                            <div class="stats-value"><?php echo formatCurrency($summary['total_income']); ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Report Table -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($reportType === 'monthly'): ?>
                            <i class="fas fa-file-alt me-2"></i> Laporan Bulanan: <?php echo getMonthName($month); ?> <?php echo $year; ?>
                        <?php elseif ($reportType === 'yearly'): ?>
                            <i class="fas fa-file-alt me-2"></i> Laporan Tahunan: <?php echo $year; ?>
                        <?php elseif ($reportType === 'area'): ?>
                            <i class="fas fa-file-alt me-2"></i> Laporan Per Area: <?php echo getMonthName($month); ?> <?php echo $year; ?>
                        <?php elseif ($reportType === 'unpaid'): ?>
                            <i class="fas fa-file-alt me-2"></i> Laporan Tunggakan: <?php echo getMonthName($month); ?> <?php echo $year; ?>
                        <?php endif; ?>

                        <?php if ($area): ?>
                            <?php foreach ($areas as $areaItem): ?>
                                <?php if ($areaItem['id'] == $area): ?>
                                    <span class="badge bg-secondary ms-2">Area: <?php echo $areaItem['area_name']; ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </h5>
                    <span class="badge bg-info fs-6"><?php echo count($reportData); ?> Data</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <?php if ($reportType === 'monthly'): ?>
                            <!-- Monthly Report Table -->
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>Area</th>
                                        <th>Iuran</th>
                                        <th>Status</th>
                                        <th>Tanggal Bayar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo $item['name']; ?></td>
                                                <td>
                                                    <?php if ($item['area_name']): ?>
                                                        <span class="badge bg-light text-dark"><?php echo $item['area_name']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Tanpa Area</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatCurrency($item['amount'] ?? $item['monthly_fee']); ?></td>
                                                <td>
                                                    <?php if ($item['has_paid']): ?>
                                                        <span class="badge bg-success">Sudah Bayar</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Belum Bayar</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $item['payment_date'] ?? '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Tidak ada data</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        
                        <?php elseif ($reportType === 'yearly'): ?>
                            <!-- Yearly Report Table -->
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Bulan</th>
                                        <th>Total Pengguna</th>
                                        <th>Sudah Bayar</th>
                                        <th>Belum Bayar</th>
                                        <th>Total Pendapatan</th>
                                        <th>Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo getMonthName($item['month']); ?></td>
                                                <td><?php echo $item['total_users']; ?></td>
                                                <td><?php echo $item['paid_users']; ?></td>
                                                <td><?php echo $item['unpaid_users']; ?></td>
                                                <td><?php echo formatCurrency($item['total_income']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 6px;">
                                                        <?php $percentage = ($item['total_users'] > 0) ? ($item['paid_users'] / $item['total_users'] * 100) : 0; ?>
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <small><?php echo round($percentage); ?>%</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Tidak ada data</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        
                        <?php elseif ($reportType === 'area'): ?>
                            <!-- Area Report Table -->
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Area</th>
                                        <th>Total Pengguna</th>
                                        <th>Sudah Bayar</th>
                                        <th>Belum Bayar</th>
                                        <th>Total Pendapatan</th>
                                        <th>Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo $item['area_name'] ?? 'Tanpa Area'; ?></td>
                                                <td><?php echo $item['total_users']; ?></td>
                                                <td><?php echo $item['paid_users']; ?></td>
                                                <td><?php echo $item['unpaid_users']; ?></td>
                                                <td><?php echo formatCurrency($item['total_income']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 6px;">
                                                        <?php $percentage = ($item['total_users'] > 0) ? ($item['paid_users'] / $item['total_users'] * 100) : 0; ?>
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <small><?php echo round($percentage); ?>%</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Tidak ada data</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        
                        <?php elseif ($reportType === 'unpaid'): ?>
                            <!-- Unpaid Report Table -->
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>Area</th>
                                        <th>No. HP</th>
                                        <th>Alamat</th>
                                        <th>Iuran</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo $item['name']; ?></td>
                                                <td>
                                                    <?php if ($item['area_name']): ?>
                                                        <span class="badge bg-light text-dark"><?php echo $item['area_name']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Tanpa Area</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $item['phone'] ?? '-'; ?></td>
                                                <td><?php echo $item['address'] ?? '-'; ?></td>
                                                <td><?php echo formatCurrency($item['monthly_fee']); ?></td>
                                                <td><span class="badge bg-danger">Belum Bayar</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Tidak ada data tunggakan</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart and Summary -->
        <div class="col-lg-4">
            <!-- Report Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Visualisasi Data</h5>
                </div>
                <div class="card-body">
                    <?php if ($reportType === 'monthly' || $reportType === 'unpaid'): ?>
                        <canvas id="paymentStatusChart" height="250"></canvas>
                    <?php elseif ($reportType === 'yearly'): ?>
                        <canvas id="yearlyChart" height="250"></canvas>
                    <?php elseif ($reportType === 'area'): ?>
                        <canvas id="areaChart" height="250"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Report Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Ringkasan Laporan</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th>Periode</th>
                                <td class="text-end">
                                    <?php if ($reportType === 'yearly'): ?>
                                        Tahun <?php echo $year; ?>
                                    <?php else: ?>
                                        <?php echo getMonthName($month); ?> <?php echo $year; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($area): ?>
                                <tr>
                                    <th>Area</th>
                                    <td class="text-end">
                                        <?php 
                                        foreach ($areas as $areaItem) {
                                            if ($areaItem['id'] == $area) {
                                                echo $areaItem['area_name'];
                                                break;
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Total Pengguna</th>
                                <td class="text-end"><?php echo $summary['total_users']; ?></td>
                            </tr>
                            <tr>
                                <th>Sudah Bayar</th>
                                <td class="text-end">
                                    <?php echo $summary['paid_users']; ?> 
                                    (<?php echo ($summary['total_users'] > 0) ? round(($summary['paid_users'] / $summary['total_users'] * 100)) : 0; ?>%)
                                </td>
                            </tr>
                            <tr>
                                <th>Belum Bayar</th>
                                <td class="text-end">
                                    <?php echo $summary['unpaid_users']; ?>
                                    (<?php echo ($summary['total_users'] > 0) ? round(($summary['unpaid_users'] / $summary['total_users'] * 100)) : 0; ?>%)
                                </td>
                            </tr>
                            <tr>
                                <th>Target Pendapatan</th>
                                <td class="text-end"><?php echo formatCurrency($summary['target_income']); ?></td>
                            </tr>
                            <tr>
                                <th>Total Pendapatan</th>
                                <td class="text-end">
                                    <?php echo formatCurrency($summary['total_income']); ?>
                                    (<?php echo ($summary['target_income'] > 0) ? round(($summary['total_income'] / $summary['target_income'] * 100)) : 0; ?>%)
                                </td>
                            </tr>
                            <?php if ($reportType === 'yearly'): ?>
                                <tr>
                                    <th>Rata-rata Bulanan</th>
                                    <td class="text-end">
                                        <?php 
                                        $avgMonthly = count($reportData) > 0 ? $summary['total_income'] / count($reportData) : 0;
                                        echo formatCurrency($avgMonthly); 
                                        ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <a href="reports.php?export=true&type=<?php echo $reportType; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>&area=<?php echo $area; ?>" 
                       class="btn btn-success w-100">
                        <i class="fas fa-file-export me-2"></i> Export ke Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS untuk styling filter toggle -->
<style>
#filterToggle {
    background-color: #0d6efd;
    color: white;
    border-radius: 0.25rem;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

#filterToggle:hover {
    background-color: #0b5ed7;
}

#filterToggle.active {
    background-color: #0a58ca;
}

#filterSection {
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

/* Animasi untuk filter section */
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

#filterSection[style*="display: block"] {
    animation: slideDown 0.3s forwards;
}

#filterSection[style*="display: none"] {
    animation: slideUp 0.3s forwards;
}
</style>

<!-- JavaScript untuk toggle functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterToggle = document.getElementById('filterToggle');
    const filterSection = document.getElementById('filterSection');
    const toggleIcon = document.getElementById('toggleIcon');
    
    // Cek URL parameters untuk menentukan status toggle awal
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.toString() !== '') {
        // Jika ada parameter filter, tampilkan filter
        filterSection.style.display = 'block';
        toggleIcon.classList.remove('fa-chevron-down');
        toggleIcon.classList.add('fa-chevron-up');
        filterToggle.classList.add('active');
    }
    
    // Toggle functionality
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
    
    // Existing export button functionality
    document.getElementById('exportButton').addEventListener('click', function() {
        // Asumsi bahwa ini akan mengarah ke fungsi export yang sudah ada
        window.location.href = 'export.php?' + urlParams.toString();
    });
});
</script>

<?php include 'includes/footer.php'; ?>