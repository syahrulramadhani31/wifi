<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Set page title
$page_title = 'Area - WiFi Payment System';

// Initialize variables
$error = '';
$success = '';
$areas = [];

// Handle delete area
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $areaId = (int)$_GET['delete'];
    
    // Check if area has users
    $query = "SELECT COUNT(*) as count FROM users WHERE area_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $areaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userCount = $result->fetch_assoc()['count'];
    
    if ($userCount > 0) {
        $error = "Area ini memiliki pengguna dan tidak dapat dihapus. Hapus atau pindahkan pengguna terlebih dahulu.";
    } else {
        $query = "DELETE FROM areas WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $areaId);
        
        if ($stmt->execute()) {
            $success = "Area berhasil dihapus!";
        } else {
            $error = "Gagal menghapus area: " . $conn->error;
        }
    }
}

// Handle add/update area
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $areaId = isset($_POST['area_id']) ? (int)$_POST['area_id'] : 0;
    $areaName = $_POST['area_name'];
    $description = $_POST['description'] ?? '';
    
    if (empty($areaName)) {
        $error = "Nama area harus diisi!";
    } else {
        if ($areaId > 0) {
            // Update existing area
            $query = "UPDATE areas SET area_name = ?, description = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $areaName, $description, $areaId);
            
            if ($stmt->execute()) {
                $success = "Area berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui area: " . $conn->error;
            }
        } else {
            // Add new area
            $query = "INSERT INTO areas (area_name, description) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $areaName, $description);
            
            if ($stmt->execute()) {
                $success = "Area baru berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan area: " . $conn->error;
            }
        }
    }
}

// Get all areas
$query = "SELECT a.*, 
          (SELECT COUNT(*) FROM users WHERE area_id = a.id) as user_count,
          (SELECT SUM(monthly_fee) FROM users WHERE area_id = a.id) as total_monthly_fee,
          (SELECT COUNT(*) FROM users u 
           INNER JOIN payments p ON u.id = p.user_id 
           WHERE u.area_id = a.id AND p.month = ? AND p.year = ?) as paid_count
          FROM areas a
          ORDER BY a.area_name";

$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $areas[] = $row;
}

// For users without area
$queryNoArea = "SELECT 
                COUNT(*) as user_count,
                SUM(monthly_fee) as total_monthly_fee,
                (SELECT COUNT(*) FROM users u 
                 INNER JOIN payments p ON u.id = p.user_id 
                 WHERE u.area_id IS NULL AND p.month = ? AND p.year = ?) as paid_count
                FROM users
                WHERE area_id IS NULL";

$stmt = $conn->prepare($queryNoArea);
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$noAreaData = $stmt->get_result()->fetch_assoc();

// Calculate metrics for chart
$areaLabels = [];
$userCounts = [];
$paidCounts = [];
$unpaidCounts = [];
$colors = [
    'rgba(78, 115, 223, 0.8)',
    'rgba(28, 200, 138, 0.8)',
    'rgba(246, 194, 62, 0.8)',
    'rgba(54, 185, 204, 0.8)',
    'rgba(231, 74, 59, 0.8)',
    'rgba(133, 135, 150, 0.8)'
];

$colorIndex = 0;
foreach ($areas as $area) {
    $areaLabels[] = $area['area_name'];
    $userCounts[] = $area['user_count'];
    $paidCounts[] = $area['paid_count'];
    $unpaidCounts[] = $area['user_count'] - $area['paid_count'];
    $colorIndex++;
}

// Include no area in chart if there are users without area
if ($noAreaData['user_count'] > 0) {
    $areaLabels[] = 'Tanpa Area';
    $userCounts[] = $noAreaData['user_count'];
    $paidCounts[] = $noAreaData['paid_count'];
    $unpaidCounts[] = $noAreaData['user_count'] - $noAreaData['paid_count'];
}

// Add chart.js
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="dashboard-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h4 class="mb-0">Pengelolaan Area</h4>
        </div>
        
        <div class="d-flex">
            <!-- Tombol Tambah Area tetap ada -->
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addAreaModal">
                <i class="fas fa-plus-circle me-2"></i> Tambah Area
            </button>
            
            <!-- Tombol Filter Data dengan toggle -->
            <button class="btn btn-primary filter-toggle" id="filterToggle">
                <i class="fas fa-filter me-2"></i>
                <span>Filter Data</span>
                <i class="fas fa-chevron-down ms-2" id="toggleIcon"></i>
            </button>
        </div>
    </div>
    
    <!-- Filter form dengan toggle display -->
    <div class="card mb-4" id="filterSection" style="display: none;">
        <div class="card-body">
            <form action="areas.php" method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="month" class="form-label">Bulan</label>
                    <select class="form-select" id="month" name="month">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $currentMonth) ? 'selected' : ''; ?>>
                                <?php echo getMonthName($i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="year" class="form-label">Tahun</label>
                    <select class="form-select" id="year" name="year">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $currentYear) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i> Filter Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">TOTAL AREA</div>
                            <div class="stats-value"><?php echo count($areas); ?></div>
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
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">TOTAL PENGGUNA</div>
                            <div class="stats-value"><?php 
                                $totalUsers = array_sum($userCounts); 
                                echo $totalUsers;
                            ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stats-info">
                            <div class="stats-title">SUDAH BAYAR</div>
                            <div class="stats-value"><?php 
                                $totalPaid = array_sum($paidCounts); 
                                echo $totalPaid;
                            ?></div>
                            <div class="stats-period"><?php echo getMonthName($currentMonth); ?> <?php echo $currentYear; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" role="progressbar" 
                             style="width: <?php echo ($totalUsers > 0) ? ($totalPaid / $totalUsers * 100) : 0; ?>%">
                        </div>
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
                            <div class="stats-value"><?php 
                                $totalUnpaid = array_sum($unpaidCounts); 
                                echo $totalUnpaid;
                            ?></div>
                            <div class="stats-period"><?php echo getMonthName($currentMonth); ?> <?php echo $currentYear; ?></div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" role="progressbar" 
                             style="width: <?php echo ($totalUsers > 0) ? ($totalUnpaid / $totalUsers * 100) : 0; ?>%">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Areas Table -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i> Daftar Area
                    </h5>
                    <span class="badge bg-info fs-6"><?php echo count($areas); ?> Area</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Area</th>
                                    <th>Deskripsi</th>
                                    <th>Jumlah Pengguna</th>
                                    <th>Status Bayar</th>
                                    <th>Total Iuran</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($areas) > 0): ?>
                                    <?php foreach ($areas as $index => $area): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo $area['area_name']; ?></td>
                                            <td><?php echo $area['description'] ? $area['description'] : '-'; ?></td>
                                            <td>
                                                <a href="users.php?area=<?php echo $area['id']; ?>" class="badge bg-primary text-decoration-none">
                                                    <?php echo $area['user_count']; ?> pengguna
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($area['user_count'] > 0): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1" style="height: 7px;">
                                                            <?php $paidPercentage = ($area['user_count'] > 0) ? ($area['paid_count'] / $area['user_count'] * 100) : 0; ?>
                                                            <div class="progress-bar bg-success" role="progressbar" 
                                                                 style="width: <?php echo $paidPercentage; ?>%">
                                                            </div>
                                                        </div>
                                                        <span class="ms-2"><?php echo round($paidPercentage); ?>%</span>
                                                    </div>
                                                    <div class="small mt-1">
                                                        <span class="text-success"><?php echo $area['paid_count']; ?> bayar</span> / 
                                                        <span class="text-danger"><?php echo $area['user_count'] - $area['paid_count']; ?> belum</span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Tidak ada pengguna</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatCurrency($area['total_monthly_fee']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-primary edit-area" 
                                                            data-bs-toggle="modal" data-bs-target="#addAreaModal"
                                                            data-id="<?php echo $area['id']; ?>"
                                                            data-name="<?php echo $area['area_name']; ?>"
                                                            data-desc="<?php echo $area['description']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="#" class="btn btn-danger delete-area" 
                                                       data-bs-toggle="modal" data-bs-target="#deleteAreaModal"
                                                       data-id="<?php echo $area['id']; ?>" 
                                                       data-name="<?php echo $area['area_name']; ?>"
                                                       data-count="<?php echo $area['user_count']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <a href="payments.php?area=<?php echo $area['id']; ?>" class="btn btn-info">
                                                        <i class="fas fa-money-bill"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- No Area Row -->
                                    <?php if ($noAreaData['user_count'] > 0): ?>
                                        <tr class="table-secondary">
                                            <td><?php echo count($areas) + 1; ?></td>
                                            <td>Tanpa Area</td>
                                            <td>Pengguna tanpa area yang ditentukan</td>
                                            <td>
                                                <a href="users.php?area=0" class="badge bg-secondary text-decoration-none">
                                                    <?php echo $noAreaData['user_count']; ?> pengguna
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($noAreaData['user_count'] > 0): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1" style="height: 7px;">
                                                            <?php $noAreaPaidPercentage = ($noAreaData['user_count'] > 0) ? ($noAreaData['paid_count'] / $noAreaData['user_count'] * 100) : 0; ?>
                                                            <div class="progress-bar bg-success" role="progressbar" 
                                                                 style="width: <?php echo $noAreaPaidPercentage; ?>%">
                                                            </div>
                                                        </div>
                                                        <span class="ms-2"><?php echo round($noAreaPaidPercentage); ?>%</span>
                                                    </div>
                                                    <div class="small mt-1">
                                                        <span class="text-success"><?php echo $noAreaData['paid_count']; ?> bayar</span> / 
                                                        <span class="text-danger"><?php echo $noAreaData['user_count'] - $noAreaData['paid_count']; ?> belum</span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Tidak ada pengguna</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatCurrency($noAreaData['total_monthly_fee']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="users.php?area=0" class="btn btn-secondary">
                                                        <i class="fas fa-users"></i>
                                                    </a>
                                                    <a href="payments.php?area=0" class="btn btn-info">
                                                        <i class="fas fa-money-bill"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Tidak ada data area</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Area Charts -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Distribusi Pengguna</h5>
                </div>
                <div class="card-body">
                    <canvas id="userDistributionChart" height="250"></canvas>
                </div>
            </div>

            <!-- Payment Status by Area -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Status Pembayaran per Area</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentStatusChart" height="250"></canvas>
                </div>
                <div class="card-footer text-muted">
                    <small>Periode: <?php echo getMonthName($currentMonth); ?> <?php echo $currentYear; ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Area Modal -->
<div class="modal fade" id="addAreaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="areaModalTitle">Tambah Area Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="areas.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="area_id" id="area_id" value="">
                    
                    <div class="mb-3">
                        <label for="area_name" class="form-label">Nama Area</label>
                        <input type="text" class="form-control" id="area_name" name="area_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        <div class="form-text">Berikan deskripsi singkat tentang area ini</div>
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

<!-- Delete Area Modal -->
<div class="modal fade" id="deleteAreaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Area</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus area <strong id="deleteAreaName"></strong>?</p>
                <p id="deleteAreaWarning" class="text-danger d-none">
                    Area ini memiliki <strong id="deleteAreaCount"></strong> pengguna dan tidak dapat dihapus. 
                    Pindahkan pengguna terlebih dahulu.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="confirmDeleteArea" class="btn btn-danger">Hapus</a>
            </div>
        </div>
    </div>
</div>

<!-- Mobile floating action button -->
<button type="button" class="btn btn-primary btn-float mobile-only" data-bs-toggle="modal" data-bs-target="#addAreaModal">
    <i class="fas fa-plus"></i>
</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // User Distribution Chart
        const distributionCtx = document.getElementById('userDistributionChart').getContext('2d');
        const userDistributionChart = new Chart(distributionCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($areaLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($userCounts); ?>,
                    backgroundColor: <?php echo json_encode(array_slice($colors, 0, count($areaLabels))); ?>,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
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
                }
            }
        });
        
        // Payment Status Chart
        const paymentCtx = document.getElementById('paymentStatusChart').getContext('2d');
        const paymentStatusChart = new Chart(paymentCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($areaLabels); ?>,
                datasets: [
                    {
                        label: 'Sudah Bayar',
                        data: <?php echo json_encode($paidCounts); ?>,
                        backgroundColor: 'rgba(28, 200, 138, 0.7)',
                        borderColor: 'rgba(28, 200, 138, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Belum Bayar',
                        data: <?php echo json_encode($unpaidCounts); ?>,
                        backgroundColor: 'rgba(231, 74, 59, 0.7)',
                        borderColor: 'rgba(231, 74, 59, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Handle edit area
        const editButtons = document.querySelectorAll('.edit-area');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const desc = this.getAttribute('data-desc');
                
                document.getElementById('areaModalTitle').textContent = 'Edit Area';
                document.getElementById('area_id').value = id;
                document.getElementById('area_name').value = name;
                document.getElementById('description').value = desc;
            });
        });
        
        // Reset form when modal is closed
        document.getElementById('addAreaModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('areaModalTitle').textContent = 'Tambah Area Baru';
            document.getElementById('area_id').value = '';
            document.getElementById('area_name').value = '';
            document.getElementById('description').value = '';
        });
        
        // Handle delete area
        const deleteButtons = document.querySelectorAll('.delete-area');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const count = parseInt(this.getAttribute('data-count'));
                
                document.getElementById('deleteAreaName').textContent = name;
                
                // Show warning if area has users
                const warningElement = document.getElementById('deleteAreaWarning');
                if (count > 0) {
                    warningElement.classList.remove('d-none');
                    document.getElementById('deleteAreaCount').textContent = count;
                    document.getElementById('confirmDeleteArea').classList.add('disabled');
                } else {
                    warningElement.classList.add('d-none');
                    document.getElementById('confirmDeleteArea').classList.remove('disabled');
                    document.getElementById('confirmDeleteArea').href = `areas.php?delete=${id}`;
                }
            });
        });
    });
</script>

<!-- CSS untuk styling toggle button dan animasi -->
<style>
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

/* Animation for filter section */
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
</style>

<!-- JavaScript untuk toggle functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle filter section
    const filterToggle = document.getElementById('filterToggle');
    const filterSection = document.getElementById('filterSection');
    const toggleIcon = document.getElementById('toggleIcon');
    
    // Check if filter parameters are present in URL
    // PENTING: Sesuaikan dengan parameter filter yang sebenarnya di halaman area
    const urlParams = new URLSearchParams(window.location.search);
    
    // Pastikan ini sesuai dengan parameter filter yang sebenarnya digunakan di halaman area
    const hasFilterParams = false; 
    // Contoh: Jika filter di area menggunakan parameter month, year, dsb:
    // const hasFilterParams = urlParams.has('month') || urlParams.has('year') || urlParams.has('status') || urlParams.has('search');
    
    if (hasFilterParams) {
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
</script>

<?php include 'includes/footer.php'; ?>