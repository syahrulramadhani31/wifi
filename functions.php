<?php
require_once 'config.php';

// Get all users
function getAllUsers() {
    global $conn;
    $query = "SELECT users.*, areas.area_name FROM users LEFT JOIN areas ON users.area_id = areas.id ORDER BY users.name";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get users by area
function getUsersByArea($areaId) {
    global $conn;
    $query = "SELECT * FROM users WHERE area_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $areaId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all areas
function getAllAreas() {
    global $conn;
    $query = "SELECT * FROM areas ORDER BY area_name";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get payment status for a specific month and year
function getPaymentStatus($month, $year) {
    global $conn;
    $query = "SELECT 
                u.id, 
                u.name, 
                u.phone, 
                a.area_name,
                u.monthly_fee,
                CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END AS payment_status
              FROM users u
              LEFT JOIN areas a ON u.area_id = a.id
              LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get dashboard statistics
function getDashboardStats($month = null, $year = null, $area = null) {
    global $conn;
    
    // Set default month and year if not provided
    if (!$month) $month = date('n');
    if (!$year) $year = date('Y');
    
    // Base query
    $params = [];
    $types = "";
    
    // Base queries
    $userQuery = "SELECT COUNT(*) as total FROM users";
    $paidQuery = "SELECT COUNT(*) as total FROM users u JOIN payments p ON u.id = p.user_id WHERE p.month = ? AND p.year = ?";
    $unpaidQuery = "SELECT COUNT(*) as total FROM users WHERE id NOT IN (SELECT user_id FROM payments WHERE month = ? AND year = ?)";
    $totalQuery = "SELECT SUM(amount) as total FROM payments WHERE month = ? AND year = ?";
    $lastMonthQuery = "SELECT SUM(amount) as total FROM payments WHERE month = ? AND year = ?";
    
    // Add parameters for month and year
    array_push($params, $month, $year);
    $types .= "ii";
    
    // Add area filter if specified
    if ($area) {
        $userQuery .= " WHERE area_id = ?";
        $paidQuery .= " AND u.area_id = ?";
        $unpaidQuery .= " AND area_id = ?";
        $totalQuery .= " AND user_id IN (SELECT id FROM users WHERE area_id = ?)";
        $lastMonthQuery .= " AND user_id IN (SELECT id FROM users WHERE area_id = ?)";
        
        array_push($params, $area);
        $types .= "i";
    }
    
    // Calculate last month
    $lastMonth = $month - 1;
    $lastYear = $year;
    if ($lastMonth == 0) {
        $lastMonth = 12;
        $lastYear--;
    }
    
    // Statistics
    $stats = [
        'total_users' => 0,
        'paid_users' => 0,
        'unpaid_users' => 0,
        'total_amount' => 0,
        'last_month_amount' => 0,
        'percentage_change' => 0
    ];
    
    // Get total users
    $stmt = $conn->prepare($userQuery);
    if ($area) $stmt->bind_param("i", $area);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_users'] = $result->fetch_assoc()['total'];
    
    // Get paid users
    $stmt = $conn->prepare($paidQuery);
    if ($area) {
        $stmt->bind_param("iii", $month, $year, $area);
    } else {
        $stmt->bind_param("ii", $month, $year);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['paid_users'] = $result->fetch_assoc()['total'];
    
    // Get unpaid users
    $stmt = $conn->prepare($unpaidQuery);
    if ($area) {
        $stmt->bind_param("iii", $month, $year, $area);
    } else {
        $stmt->bind_param("ii", $month, $year);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['unpaid_users'] = $result->fetch_assoc()['total'];
    
    // Get total amount
    $stmt = $conn->prepare($totalQuery);
    if ($area) {
        $stmt->bind_param("iii", $month, $year, $area);
    } else {
        $stmt->bind_param("ii", $month, $year);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_amount'] = $row['total'] ?? 0;
    
    // Get last month amount
    $stmt = $conn->prepare($lastMonthQuery);
    if ($area) {
        $stmt->bind_param("iii", $lastMonth, $lastYear, $area);
    } else {
        $stmt->bind_param("ii", $lastMonth, $lastYear);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['last_month_amount'] = $row['total'] ?? 0;
    
    // Calculate percentage change
    if ($stats['last_month_amount'] > 0) {
        $stats['percentage_change'] = (($stats['total_amount'] - $stats['last_month_amount']) / $stats['last_month_amount']) * 100;
    }
    
    return $stats;
}

// Get monthly data for chart
function getMonthlyData($year) {
    global $conn;
    $data = [];
    
    $query = "SELECT 
                MONTH(payment_date) as month, 
                SUM(amount) as total 
              FROM payments 
              WHERE YEAR(payment_date) = ? 
              GROUP BY MONTH(payment_date)";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize all months to 0
    for ($i = 1; $i <= 12; $i++) {
        $data[$i] = 0;
    }
    
    // Fill in actual data
    while ($row = $result->fetch_assoc()) {
        $data[$row['month']] = (int)$row['total'];
    }
    
    return $data;
}

// Record payment
function recordPayment($userId, $month, $year, $amount, $notes = '') {
    global $conn;
    
    // Check if payment already exists
    $checkQuery = "SELECT id FROM payments WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing payment
        $paymentId = $result->fetch_assoc()['id'];
        $query = "UPDATE payments SET amount = ?, notes = ?, payment_date = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("dsi", $amount, $notes, $paymentId);
    } else {
        // Insert new payment
        $query = "INSERT INTO payments (user_id, month, year, amount, notes, payment_date) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiids", $userId, $month, $year, $amount, $notes);
    }
    
    return $stmt->execute();
}

// Format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Get month name
function getMonthName($month) {
    $months = [
        1 => 'Januari', 
        2 => 'Februari', 
        3 => 'Maret',
        4 => 'April', 
        5 => 'Mei', 
        6 => 'Juni',
        7 => 'Juli', 
        8 => 'Agustus', 
        9 => 'September',
        10 => 'Oktober', 
        11 => 'November', 
        12 => 'Desember'
    ];
    
    return $months[$month] ?? 'Unknown';
}

/**
 * Generate monthly report
 */
function generateMonthlyReport($month, $year, $area = null) {
    global $conn;
    
    $query = "SELECT u.id as user_id, u.name, u.phone, u.monthly_fee, a.area_name, 
              CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END as has_paid,
              p.payment_date, p.amount, p.notes, p.id as payment_id
              FROM users u
              LEFT JOIN areas a ON u.area_id = a.id
              LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?";
    
    $conditions = [];
    $params = [$month, $year];
    $types = "ii";
    
    if ($area) {
        $conditions[] = "u.area_id = ?";
        $params[] = $area;
        $types .= "i";
    }
    
    if (count($conditions) > 0) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY u.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

/**
 * Generate yearly report
 */
function generateYearlyReport($year, $area = null) {
    global $conn;
    
    $data = [];
    
    // Get data for all months
    for ($month = 1; $month <= 12; $month++) {
        $query = "SELECT 
                COUNT(DISTINCT u.id) as total_users,
                SUM(CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END) as paid_users,
                SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) as unpaid_users,
                SUM(CASE WHEN p.id IS NOT NULL THEN p.amount ELSE 0 END) as total_income
                FROM users u
                LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?";
        
        $params = [$month, $year];
        $types = "ii";
        
        if ($area) {
            $query .= " WHERE u.area_id = ?";
            $params[] = $area;
            $types .= "i";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $data[] = [
            'month' => $month,
            'total_users' => $row['total_users'],
            'paid_users' => $row['paid_users'],
            'unpaid_users' => $row['unpaid_users'],
            'total_income' => $row['total_income']
        ];
    }
    
    return $data;
}

/**
 * Generate area report
 */
function generateAreaReport($month, $year) {
    global $conn;
    
    // Get data for all areas
    $query = "SELECT 
            a.id, a.area_name,
            COUNT(DISTINCT u.id) as total_users,
            SUM(CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END) as paid_users,
            SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) as unpaid_users,
            SUM(CASE WHEN p.id IS NOT NULL THEN p.amount ELSE 0 END) as total_income
            FROM areas a
            LEFT JOIN users u ON a.id = u.area_id
            LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?
            GROUP BY a.id
            ORDER BY a.area_name";
            
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    // Get data for users without area
    $query = "SELECT 
            COUNT(DISTINCT u.id) as total_users,
            SUM(CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END) as paid_users,
            SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) as unpaid_users,
            SUM(CASE WHEN p.id IS NOT NULL THEN p.amount ELSE 0 END) as total_income
            FROM users u
            LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?
            WHERE u.area_id IS NULL";
            
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Add data for users without area if there are any
    if ($row['total_users'] > 0) {
        $data[] = [
            'id' => null,
            'area_name' => null,
            'total_users' => $row['total_users'],
            'paid_users' => $row['paid_users'],
            'unpaid_users' => $row['unpaid_users'],
            'total_income' => $row['total_income']
        ];
    }
    
    return $data;
}

/**
 * Generate unpaid report
 */
function generateUnpaidReport($month, $year, $area = null) {
    global $conn;
    
    $query = "SELECT u.id as user_id, u.name, u.phone, u.address, u.monthly_fee, a.area_name
              FROM users u
              LEFT JOIN areas a ON u.area_id = a.id
              LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?
              WHERE p.id IS NULL"; // Only unpaid users
    
    $params = [$month, $year];
    $types = "ii";
    
    if ($area) {
        $query .= " AND u.area_id = ?";
        $params[] = $area;
        $types .= "i";
    }
    
    $query .= " ORDER BY u.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

/**
 * Get report summary
 */
function getReportSummary($month, $year, $area = null, $reportType = 'monthly') {
    global $conn;
    
    $summary = [
        'total_users' => 0,
        'paid_users' => 0,
        'unpaid_users' => 0,
        'total_income' => 0,
        'target_income' => 0
    ];
    
    if ($reportType === 'monthly' || $reportType === 'unpaid') {
        $query = "SELECT 
                COUNT(DISTINCT u.id) as total_users,
                SUM(CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END) as paid_users,
                SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) as unpaid_users,
                SUM(CASE WHEN p.id IS NOT NULL THEN p.amount ELSE 0 END) as total_income,
                SUM(u.monthly_fee) as target_income
                FROM users u
                LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?";
        
        $params = [$month, $year];
        $types = "ii";
        
        if ($area) {
            $query .= " WHERE u.area_id = ?";
            $params[] = $area;
            $types .= "i";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc();
    } elseif ($reportType === 'yearly') {
        $query = "SELECT 
                COUNT(DISTINCT u.id) as total_users,
                SUM(CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END) as paid_users,
                SUM(u.monthly_fee) * 12 as target_income
                FROM users u
                LEFT JOIN payments p ON u.id = p.user_id AND p.year = ?";
        
        $params = [$year];
        $types = "i";
        
        if ($area) {
            $query .= " WHERE u.area_id = ?";
            $params[] = $area;
            $types .= "i";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $baseData = $result->fetch_assoc();
        
        // Get total income for the year
        $query = "SELECT SUM(p.amount) as total_income
                  FROM payments p
                  INNER JOIN users u ON p.user_id = u.id
                  WHERE p.year = ?";
        
        $params = [$year];
        $types = "i";
        
        if ($area) {
            $query .= " AND u.area_id = ?";
            $params[] = $area;
            $types .= "i";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $incomeData = $result->fetch_assoc();
        
        $summary = [
            'total_users' => $baseData['total_users'],
            'paid_users' => $baseData['paid_users'],
            'unpaid_users' => $baseData['total_users'] * 12 - $baseData['paid_users'], // Approximate
            'total_income' => $incomeData['total_income'],
            'target_income' => $baseData['target_income']
        ];
    } elseif ($reportType === 'area') {
        $query = "SELECT 
                COUNT(DISTINCT u.id) as total_users,
                SUM(CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END) as paid_users,
                SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) as unpaid_users,
                SUM(CASE WHEN p.id IS NOT NULL THEN p.amount ELSE 0 END) as total_income,
                SUM(u.monthly_fee) as target_income
                FROM users u
                LEFT JOIN payments p ON u.id = p.user_id AND p.month = ? AND p.year = ?";
        
        $params = [$month, $year];
        $types = "ii";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc();
    }
    
    return $summary;
}

/**
 * Export monthly report to Excel
 */
function exportMonthlyReport($data, $summary, $month, $year, $area = null) {
    // Create report filename
    $filename = "laporan_bulanan_" . getMonthName($month) . "_" . $year;
    if ($area) {
        $filename .= "_area_" . $area;
    }
    $filename .= "_" . date('Ymd') . ".csv";
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create a file pointer
    $file = fopen('php://output', 'w');
    
    // Set column headers
    fputcsv($file, ['Laporan Bulanan - ' . getMonthName($month) . ' ' . $year]);
    fputcsv($file, []);
    
    // Write summary data
    fputcsv($file, ['Ringkasan']);
    fputcsv($file, ['Total Pengguna', $summary['total_users']]);
    fputcsv($file, ['Sudah Bayar', $summary['paid_users']]);
    fputcsv($file, ['Belum Bayar', $summary['unpaid_users']]);
    fputcsv($file, ['Total Pendapatan', $summary['total_income']]);
    fputcsv($file, ['Target Pendapatan', $summary['target_income']]);
    fputcsv($file, []);
    
    // Write data headers
    fputcsv($file, ['No', 'Nama', 'Area', 'Iuran', 'Status', 'Tanggal Bayar']);
    
    // Write data rows
    $i = 1;
    foreach ($data as $row) {
        $status = $row['has_paid'] ? 'Sudah Bayar' : 'Belum Bayar';
        $area = $row['area_name'] ? $row['area_name'] : 'Tanpa Area';
        $amount = $row['amount'] ? $row['amount'] : $row['monthly_fee'];
        
        fputcsv($file, [
            $i++,
            $row['name'],
            $area,
            $amount,
            $status,
            $row['payment_date'] ?? '-'
        ]);
    }
    
    // Close the file
    fclose($file);
    exit;
}

/**
 * Export yearly report to Excel
 */
function exportYearlyReport($data, $summary, $year, $area = null) {
    // Create report filename
    $filename = "laporan_tahunan_" . $year;
    if ($area) {
        $filename .= "_area_" . $area;
    }
    $filename .= "_" . date('Ymd') . ".csv";
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create a file pointer
    $file = fopen('php://output', 'w');
    
    // Set column headers
    fputcsv($file, ['Laporan Tahunan - ' . $year]);
    fputcsv($file, []);
    
    // Write summary data
    fputcsv($file, ['Ringkasan']);
    fputcsv($file, ['Total Pengguna', $summary['total_users']]);
    fputcsv($file, ['Total Pembayaran', $summary['paid_users']]);
    fputcsv($file, ['Total Pendapatan', $summary['total_income']]);
    fputcsv($file, ['Target Pendapatan', $summary['target_income']]);
    fputcsv($file, []);
    
    // Write data headers
    fputcsv($file, ['No', 'Bulan', 'Total Pengguna', 'Sudah Bayar', 'Belum Bayar', 'Total Pendapatan']);
    
    // Write data rows
    $i = 1;
    foreach ($data as $row) {
        fputcsv($file, [
            $i++,
            getMonthName($row['month']),
            $row['total_users'],
            $row['paid_users'],
            $row['unpaid_users'],
            $row['total_income']
        ]);
    }
    
    // Close the file
    fclose($file);
    exit;
}

/**
 * Export area report to Excel
 */
function exportAreaReport($data, $summary, $month, $year) {
    // Create report filename
    $filename = "laporan_area_" . getMonthName($month) . "_" . $year;
    $filename .= "_" . date('Ymd') . ".csv";
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create a file pointer
    $file = fopen('php://output', 'w');
    
    // Set column headers
    fputcsv($file, ['Laporan Per Area - ' . getMonthName($month) . ' ' . $year]);
    fputcsv($file, []);
    
    // Write summary data
    fputcsv($file, ['Ringkasan']);
    fputcsv($file, ['Total Pengguna', $summary['total_users']]);
    fputcsv($file, ['Sudah Bayar', $summary['paid_users']]);
    fputcsv($file, ['Belum Bayar', $summary['unpaid_users']]);
    fputcsv($file, ['Total Pendapatan', $summary['total_income']]);
    fputcsv($file, []);
    
    // Write data headers
    fputcsv($file, ['No', 'Area', 'Total Pengguna', 'Sudah Bayar', 'Belum Bayar', 'Total Pendapatan']);
    
    // Write data rows
    $i = 1;
    foreach ($data as $row) {
        $areaName = $row['area_name'] ? $row['area_name'] : 'Tanpa Area';
        
        fputcsv($file, [
            $i++,
            $areaName,
            $row['total_users'],
            $row['paid_users'],
            $row['unpaid_users'],
            $row['total_income']
        ]);
    }
    
    // Close the file
    fclose($file);
    exit;
}

/**
 * Export unpaid report to Excel
 */
function exportUnpaidReport($data, $summary, $month, $year, $area = null) {
    // Create report filename
    $filename = "laporan_tunggakan_" . getMonthName($month) . "_" . $year;
    if ($area) {
        $filename .= "_area_" . $area;
    }
    $filename .= "_" . date('Ymd') . ".csv";
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create a file pointer
    $file = fopen('php://output', 'w');
    
    // Set column headers
    fputcsv($file, ['Laporan Tunggakan - ' . getMonthName($month) . ' ' . $year]);
    fputcsv($file, []);
    
    // Write summary data
    fputcsv($file, ['Ringkasan']);
    fputcsv($file, ['Total Pengguna', $summary['total_users']]);
    fputcsv($file, ['Belum Bayar', $summary['unpaid_users']]);
    fputcsv($file, ['Persentase Tunggakan', ($summary['total_users'] > 0) ? round(($summary['unpaid_users'] / $summary['total_users'] * 100)) . '%' : '0%']);
    fputcsv($file, ['Nilai Tunggakan', $summary['target_income'] - $summary['total_income']]);
    fputcsv($file, []);
    
    // Write data headers
    fputcsv($file, ['No', 'Nama', 'Area', 'No. HP', 'Alamat', 'Iuran']);
    
    // Write data rows
    $i = 1;
    foreach ($data as $row) {
        $area = $row['area_name'] ? $row['area_name'] : 'Tanpa Area';
        
        fputcsv($file, [
            $i++,
            $row['name'],
            $area,
            $row['phone'] ?? '-',
            $row['address'] ?? '-',
            $row['monthly_fee']
        ]);
    }
    
    // Close the file
    fclose($file);
    exit;
}

// Add this function to handle user filtering and searching

// Get filtered users
function getFilteredUsers($search = '', $area_id = null, $status = '', $sort = 'name_asc') {
    global $conn;
    
    $conditions = [];
    $params = [];
    $types = '';
    
    // Search condition
    if (!empty($search)) {
        $conditions[] = "(name LIKE ? OR username LIKE ? OR phone LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'sss';
    }
    
    // Area filter
    if (!empty($area_id)) {
        $conditions[] = "area_id = ?";
        $params[] = $area_id;
        $types .= 'i';
    }
    
    // Status filter
    if (!empty($status)) {
        $conditions[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Build the query
    $sql = "SELECT * FROM users";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Add sorting
    switch ($sort) {
        case 'name_desc':
            $sql .= " ORDER BY name DESC";
            break;
        case 'username_asc':
            $sql .= " ORDER BY username ASC";
            break;
        case 'username_desc':
            $sql .= " ORDER BY username DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY created_at DESC";
            break;
        case 'oldest':
            $sql .= " ORDER BY created_at ASC";
            break;
        case 'name_asc':
        default:
            $sql .= " ORDER BY name ASC";
            break;
    }
    
    // Prepare and execute the query
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}

// Search users by name, phone, or address with optional area filter
function searchUsers($search = '', $areaId = null) {
    global $conn;
    
    $sql = "SELECT u.*, a.area_name 
            FROM users u 
            LEFT JOIN areas a ON u.area_id = a.id 
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Add search condition
    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR u.phone LIKE ? OR u.address LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }
    
    // Add area filter
    if ($areaId) {
        $sql .= " AND u.area_id = ?";
        $params[] = $areaId;
        $types .= "i";
    }
    
    $sql .= " ORDER BY u.name";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}
?>