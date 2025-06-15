<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Set page title
$page_title = 'Dashboard - WiFi Payment System';

// Get filter parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$area = isset($_GET['area']) ? (int)$_GET['area'] : null;

// Get dashboard statistics
$stats = getDashboardStats($month, $year, $area);

// Get all areas for filter
$areas = getAllAreas();

// Get monthly data for chart
$chartData = getMonthlyData($year);

// Handle flash messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear flash messages
unset($_SESSION['success']);
unset($_SESSION['error']);

// Additional JavaScript for charts
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Bagian Filter Data Button dan Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-tachometer-alt me-2"></i>
            <h4 class="mb-0">Dashboard</h4>
        </div
        
        <!-- Filter Data button dengan styling yang dioptimalkan -->
        <button class="btn btn-primary filter-toggle" id="filterToggle">
            <i class="fas fa-filter me-2"></i>
            <span>Filter Data</span>
            <i class="fas fa-chevron-down ms-2" id="toggleIcon"></i>
        </button>
    </div>

    <!-- Filter section yang sebelumnya tidak tampil -->
    <div class="card mb-4" id="filterSection" style="display: none;">
        <div class="card-body">
            <form action="dashboard.php" method="GET" class="row g-3">
                <!-- Month Filter -->
                <div class="col-md-4">
                    <label for="month" class="form-label">Bulan</label>
                    <select name="month" id="month" class="form-select">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($month == $i) ? 'selected' : ''; ?>>
                                <?php echo getMonthName($i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <!-- Year Filter -->
                <div class="col-md-4">
                    <label for="year" class="form-label">Tahun</label>
                    <select name="year" id="year" class="form-select">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($year == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <!-- Area Filter -->
                <div class="col-md-4">
                    <label for="area" class="form-label">Area</label>
                    <select name="area" id="area" class="form-select">
                        <option value="">Semua Area</option>
                        <?php foreach ($areas as $areaItem): ?>
                            <option value="<?php echo $areaItem['id']; ?>" <?php echo ($area == $areaItem['id']) ? 'selected' : ''; ?>>
                                <?php echo $areaItem['area_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Tampilkan
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="row">
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
                            <div class="stats-value"><?php echo $stats['total_users']; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
                    </div>
                    <div class="mt-3">
                        <a href="users.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> Lihat Detail
                        </a>
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
                            <div class="stats-value"><?php echo $stats['paid_users']; ?></div>
                            <div class="stats-period"><?php echo getMonthName($month); ?> <?php echo $year; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo ($stats['total_users'] > 0) ? ($stats['paid_users'] / $stats['total_users'] * 100) : 0; ?>%">
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="payments.php?status=paid&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                           class="btn btn-sm btn-outline-success">
                            <i class="fas fa-eye me-1"></i> Lihat Detail
                        </a>
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
                            <div class="stats-value"><?php echo $stats['unpaid_users']; ?></div>
                            <div class="stats-period"><?php echo getMonthName($month); ?> <?php echo $year; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" role="progressbar" 
                             style="width: <?php echo ($stats['total_users'] > 0) ? ($stats['unpaid_users'] / $stats['total_users'] * 100) : 0; ?>%">
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="payments.php?status=unpaid&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                           class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-eye me-1"></i> Lihat Detail
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Terkumpul -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">TOTAL TERKUMPUL</div>
                            <div class="stats-value"><?php echo formatCurrency($stats['total_amount']); ?></div>
                            <div class="stats-period"><?php echo getMonthName($month); ?> <?php echo $year; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" role="progressbar" style="width: 100%"></div>
                    </div>
                    <div class="mt-3">
                        <a href="reports.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                           class="btn btn-sm btn-outline-info">
                            <i class="fas fa-file-alt me-1"></i> Lihat Laporan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row">
        <!-- Bar Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i> Grafik Pembayaran <?php echo $year; ?>
                    </h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                id="chartTypeDropdown" data-bs-toggle="dropdown">
                            Tipe Grafik
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="chartTypeDropdown">
                            <li><a class="dropdown-item chart-type" data-type="bar" href="#">Bar Chart</a></li>
                            <li><a class="dropdown-item chart-type" data-type="line" href="#">Line Chart</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="paymentsChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Pie Chart -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Proporsi Pembayaran</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentProportionChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile floating action button -->
<a href="payments.php" class="btn btn-primary btn-float mobile-only">
    <i class="fas fa-plus"></i>
</a>

<script>
    // Toggle filter section
    document.addEventListener('DOMContentLoaded', function() {
        const filterToggle = document.getElementById('filterToggle');
        const filterSection = document.getElementById('filterSection');
        const toggleIcon = document.getElementById('toggleIcon');
        
        // Check if filter parameters are present in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('month') || urlParams.has('year') || urlParams.has('area')) {
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
    });
    
    // Payment chart
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const chartData = <?php echo json_encode(array_values($chartData)); ?>;
    
    let chartType = 'bar';
    let paymentsChart;
    
    function initializePaymentsChart() {
        const ctx = document.getElementById('paymentsChart').getContext('2d');
        paymentsChart = new Chart(ctx, {
            type: chartType,
            data: {
                labels: months,
                datasets: [{
                    label: 'Pendapatan Bulanan (Rp)',
                    data: chartData,
                    backgroundColor: 'rgba(67, 97, 238, 0.5)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    // Payment proportion chart (pie chart)
    function initializeProportionChart() {
        const ctx = document.getElementById('paymentProportionChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Sudah Bayar', 'Belum Bayar'],
                datasets: [{
                    data: [<?php echo $stats['paid_users']; ?>, <?php echo $stats['unpaid_users']; ?>],
                    backgroundColor: [
                        'rgba(28, 200, 138, 0.7)', // green for paid
                        'rgba(231, 74, 59, 0.7)'  // red for unpaid
                    ],
                    borderColor: [
                        'rgba(28, 200, 138, 1)',
                        'rgba(231, 74, 59, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Initialize charts on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializePaymentsChart();
        initializeProportionChart();
        
        // Chart type switcher
        document.querySelectorAll('.chart-type').forEach(item => {
            item.addEventListener('click', event => {
                event.preventDefault();
                chartType = event.target.getAttribute('data-type');
                paymentsChart.destroy();
                initializePaymentsChart();
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>