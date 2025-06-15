<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Set page title
$page_title = 'Pengguna - WiFi Payment System';

// Initialize variables
$error = '';
$success = '';
$users = [];
$areas = getAllAreas();

// Get filter parameters
$areaFilter = isset($_GET['area']) ? (int)$_GET['area'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle delete user
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    
    // Check if user has payments
    $query = "SELECT COUNT(*) as count FROM payments WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $paymentCount = $result->fetch_assoc()['count'];
    
    if ($paymentCount > 0) {
        $error = "Pengguna ini memiliki data pembayaran dan tidak dapat dihapus. Hapus data pembayaran terlebih dahulu.";
    } else {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $success = "Pengguna berhasil dihapus!";
        } else {
            $error = "Gagal menghapus pengguna: " . $conn->error;
        }
    }
}

// Handle add/update user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $monthlyFee = $_POST['monthly_fee'];
    $areaId = !empty($_POST['area_id']) ? (int)$_POST['area_id'] : null;
    
    if (empty($name)) {
        $error = "Nama pengguna harus diisi!";
    } else {
        if ($userId > 0) {
            // Update existing user
            $query = "UPDATE users SET name = ?, phone = ?, address = ?, monthly_fee = ?, area_id = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssdii", $name, $phone, $address, $monthlyFee, $areaId, $userId);
            
            if ($stmt->execute()) {
                $success = "Data pengguna berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui pengguna: " . $conn->error;
            }
        } else {
            // Add new user
            $query = "INSERT INTO users (name, phone, address, monthly_fee, area_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssdi", $name, $phone, $address, $monthlyFee, $areaId);
            
            if ($stmt->execute()) {
                $success = "Pengguna baru berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan pengguna: " . $conn->error;
            }
        }
    }
}

// Get users based on filter and search
if (!empty($search) || $areaFilter) {
    // Modifikasi function untuk mendukung pencarian dan filter area
    $users = searchUsers($search, $areaFilter);
} else {
    $users = getAllUsers();
}

// Count users by area for chart
$areaLabels = [];
$areaData = [];
$areaColors = [
    'rgba(78, 115, 223, 0.7)',
    'rgba(28, 200, 138, 0.7)',
    'rgba(246, 194, 62, 0.7)',
    'rgba(54, 185, 204, 0.7)',
    'rgba(231, 74, 59, 0.7)',
    'rgba(133, 135, 150, 0.7)'
];

$query = "SELECT a.id, a.area_name, COUNT(u.id) as user_count 
          FROM areas a 
          LEFT JOIN users u ON a.id = u.area_id 
          GROUP BY a.id 
          ORDER BY a.area_name";
$result = $conn->query($query);

$colorIndex = 0;
while ($row = $result->fetch_assoc()) {
    $areaLabels[] = $row['area_name'];
    $areaData[] = $row['user_count'];
    $colorIndex++;
}

// For users without area
$query = "SELECT COUNT(*) as count FROM users WHERE area_id IS NULL";
$result = $conn->query($query);
$noAreaCount = $result->fetch_assoc()['count'];

if ($noAreaCount > 0) {
    $areaLabels[] = 'Tanpa Area';
    $areaData[] = $noAreaCount;
}

// Add chart.js
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Flash messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="dashboard-icon">
                <i class="fas fa-users"></i>
            </div>
            <h4 class="mb-0">Pengelolaan Pengguna</h4>
        </div>
        
        <div class="d-flex">
            <!-- Tambah Pengguna Button -->
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-1"></i> Tambah Pengguna
            </button>
            
            <!-- Filter Data button dengan styling baru -->
            <button class="btn btn-primary filter-toggle" id="filterToggle">
                <i class="fas fa-filter me-2"></i>
                <span>Filter Data</span>
                <i class="fas fa-chevron-down ms-2" id="toggleIcon"></i>
            </button>
        </div>
    </div>
    
    <!-- Filter Area dengan toggle display dan search feature -->
    <div class="card mb-4" id="filterSection" style="display: none;">
        <div class="card-body">
            <form action="users.php" method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Cari Pengguna</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Nama, No HP, atau Alamat" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="area" class="form-label">Area</label>
                    <select class="form-select" id="area" name="area">
                        <option value="">Semua Area</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo $area['id']; ?>" <?php echo ($area['id'] == $areaFilter) ? 'selected' : ''; ?>>
                                <?php echo $area['area_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Tampilkan
                    </button>
                    <?php if (!empty($search) || $areaFilter): ?>
                        <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i> Daftar Pengguna
                <?php if ($areaFilter): ?>
                    <?php foreach ($areas as $area): ?>
                        <?php if ($area['id'] == $areaFilter): ?>
                            <span class="badge bg-primary ms-2">Area: <?php echo $area['area_name']; ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <span class="badge bg-info ms-2">Pencarian: "<?php echo htmlspecialchars($search); ?>"</span>
                <?php endif; ?>
            </h5>
            <span class="badge bg-info fs-6"><?php echo count($users); ?> Pengguna</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>No. HP</th>
                            <th>Alamat</th>
                            <th>Area</th>
                            <th>Iuran Bulanan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $index => $user): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo $user['name']; ?></td>
                                    <td><?php echo $user['phone']; ?></td>
                                    <td><?php echo $user['address']; ?></td>
                                    <td>
                                        <?php if (isset($user['area_name']) && $user['area_name']): ?>
                                            <span class="badge bg-light text-dark"><?php echo $user['area_name']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tanpa Area</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatCurrency($user['monthly_fee']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary edit-user" 
                                                data-bs-toggle="modal" data-bs-target="#addUserModal"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-name="<?php echo $user['name']; ?>"
                                                data-phone="<?php echo $user['phone']; ?>"
                                                data-address="<?php echo $user['address']; ?>"
                                                data-fee="<?php echo $user['monthly_fee']; ?>"
                                                data-area="<?php echo $user['area_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="#" class="btn btn-danger delete-user" 
                                               data-bs-toggle="modal" data-bs-target="#deleteUserModal" 
                                               data-id="<?php echo $user['id']; ?>" 
                                               data-name="<?php echo $user['name']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="payments.php?user=<?php echo $user['id']; ?>" class="btn btn-info">
                                                <i class="fas fa-money-bill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <?php if (!empty($search) || $areaFilter): ?>
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Tidak ada data pengguna yang sesuai dengan filter atau pencarian
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Tidak ada data pengguna
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Stats Row -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Distribusi Pengguna per Area</h5>
                </div>
                <div class="card-body">
                    <canvas id="usersByAreaChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Ringkasan</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th>Total Pengguna</th>
                                <td class="text-end"><?php echo array_sum($areaData); ?></td>
                            </tr>
                            <tr>
                                <th>Total Area</th>
                                <td class="text-end"><?php echo count($areas); ?></td>
                            </tr>
                            <tr>
                                <th>Pengguna Tanpa Area</th>
                                <td class="text-end"><?php echo $noAreaCount; ?></td>
                            </tr>
                            <tr>
                                <th>Iuran Bulanan Rata-rata</th>
                                <td class="text-end">
                                    <?php 
                                    $query = "SELECT AVG(monthly_fee) as avg_fee FROM users";
                                    $result = $conn->query($query);
                                    $avgFee = $result->fetch_assoc()['avg_fee'];
                                    echo formatCurrency($avgFee); 
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Total Iuran Bulanan</th>
                                <td class="text-end">
                                    <?php 
                                    $query = "SELECT SUM(monthly_fee) as total_fee FROM users";
                                    $result = $conn->query($query);
                                    $totalFee = $result->fetch_assoc()['total_fee'];
                                    echo formatCurrency($totalFee); 
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Tambah Pengguna Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="user_id" value="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Nama</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">No. HP</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="monthly_fee" class="form-label">Iuran Bulanan</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" id="monthly_fee" name="monthly_fee" step="1000" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="area_id" class="form-label">Area</label>
                            <select class="form-select" id="area_id" name="area_id">
                                <option value="">Tanpa Area</option>
                                <?php foreach ($areas as $area): ?>
                                    <option value="<?php echo $area['id']; ?>"><?php echo $area['area_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pengguna <strong id="deleteUserName"></strong>?</p>
                <p class="text-danger">Catatan: Pengguna tidak dapat dihapus jika memiliki data pembayaran.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Hapus</a>
            </div>
        </div>
    </div>
</div>

<!-- Mobile floating action button -->
<button type="button" class="btn btn-primary btn-float mobile-only" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="fas fa-plus"></i>
</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle filter section
        const filterToggle = document.getElementById('filterToggle');
        const filterSection = document.getElementById('filterSection');
        const toggleIcon = document.getElementById('toggleIcon');
        
        // Check if filter or search parameters are present in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('area') || urlParams.has('search')) {
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
        
        // User Chart
        const userCtx = document.getElementById('usersByAreaChart').getContext('2d');
        const userChart = new Chart(userCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($areaLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($areaData); ?>,
                    backgroundColor: <?php echo json_encode(array_slice($areaColors, 0, count($areaLabels))); ?>,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15,
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
        
        // Edit User
        const editButtons = document.querySelectorAll('.edit-user');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const phone = this.getAttribute('data-phone');
                const address = this.getAttribute('data-address');
                const fee = this.getAttribute('data-fee');
                const area = this.getAttribute('data-area');
                
                document.getElementById('userModalTitle').textContent = 'Edit Pengguna';
                document.getElementById('user_id').value = id;
                document.getElementById('name').value = name;
                document.getElementById('phone').value = phone;
                document.getElementById('address').value = address;
                document.getElementById('monthly_fee').value = fee;
                
                const areaSelect = document.getElementById('area_id');
                if (area) {
                    for (let i = 0; i < areaSelect.options.length; i++) {
                        if (areaSelect.options[i].value === area) {
                            areaSelect.selectedIndex = i;
                            break;
                        }
                    }
                } else {
                    areaSelect.selectedIndex = 0;
                }
            });
        });
        
        // Reset form when modal is closed
        document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('userModalTitle').textContent = 'Tambah Pengguna Baru';
            document.getElementById('user_id').value = '';
            document.getElementById('name').value = '';
            document.getElementById('phone').value = '';
            document.getElementById('address').value = '';
            document.getElementById('monthly_fee').value = '';
            document.getElementById('area_id').selectedIndex = 0;
        });
        
        // Delete User
        const deleteButtons = document.querySelectorAll('.delete-user');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('deleteUserName').textContent = name;
                document.getElementById('confirmDelete').href = `users.php?delete=${id}`;
            });
        });
    });
</script>

<!-- CSS untuk filter toggle -->
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
</style>

<?php include 'includes/footer.php'; ?>