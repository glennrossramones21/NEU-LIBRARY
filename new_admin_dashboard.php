<?php
session_start();
require_once 'connection.php';

$check_column = mysqli_query($conn, "SHOW COLUMNS FROM visitor_logs LIKE 'approval_status'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE visitor_logs 
        ADD COLUMN approval_status ENUM('pending', 'approved', 'declined') DEFAULT 'pending' AFTER status,
        ADD COLUMN approved_by INT NULL AFTER approval_status,
        ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
        ADD COLUMN decline_reason TEXT NULL AFTER approved_at");
}

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS blocked_users (
        block_id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100),
        full_name VARCHAR(100),
        reason TEXT,
        blocked_at DATETIME,
        is_active BOOLEAN DEFAULT TRUE
    )
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS visitor_logs (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        email VARCHAR(100),
        full_name VARCHAR(100),
        user_type VARCHAR(50),
        program_department VARCHAR(100),
        reason VARCHAR(50),
        reason_details TEXT,
        entry_time DATETIME,
        exit_time DATETIME NULL,
        status VARCHAR(20) DEFAULT 'pending',
        approval_status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
        approved_by INT NULL,
        approved_at DATETIME NULL,
        decline_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'today';
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

switch($filter) {
    case 'today':
        $date_condition = "DATE(entry_time) = CURDATE() AND approval_status = 'approved'";
        $filter_text = "Today";
        break;
    case 'week':
        $date_condition = "YEARWEEK(entry_time, 1) = YEARWEEK(CURDATE(), 1) AND approval_status = 'approved'";
        $filter_text = "This Week";
        break;
    case 'month':
        $date_condition = "MONTH(entry_time) = MONTH(CURDATE()) AND YEAR(entry_time) = YEAR(CURDATE()) AND approval_status = 'approved'";
        $filter_text = "This Month";
        break;
    case 'custom':
        $date_condition = "DATE(entry_time) BETWEEN '$start' AND '$end' AND approval_status = 'approved'";
        $filter_text = "$start to $end";
        break;
    default:
        $date_condition = "DATE(entry_time) = CURDATE() AND approval_status = 'approved'";
        $filter_text = "Today";
}

$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE $date_condition"))['count'];
$unique = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT email) as count FROM visitor_logs WHERE $date_condition"))['count'];
$active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE status = 'active' AND approval_status = 'approved'"))['count'];

$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE approval_status = 'pending'"))['count'];

$today_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_time) = CURDATE()"))['count'];

$week_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE YEARWEEK(entry_time, 1) = YEARWEEK(CURDATE(), 1)"))['count'];

$month_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE MONTH(entry_time) = MONTH(CURDATE()) AND YEAR(entry_time) = YEAR(CURDATE())"))['count'];

$reasons = mysqli_query($conn, "SELECT reason, COUNT(*) as count FROM visitor_logs WHERE $date_condition GROUP BY reason ORDER BY count DESC");

$types = mysqli_query($conn, "SELECT user_type, COUNT(*) as count FROM visitor_logs WHERE $date_condition GROUP BY user_type");

$pending_entries = mysqli_query($conn, "SELECT * FROM visitor_logs WHERE approval_status = 'pending' ORDER BY entry_time DESC");

$approved_active = mysqli_query($conn, "SELECT * FROM visitor_logs WHERE approval_status = 'approved' AND status = 'active' ORDER BY entry_time DESC");

$recent = mysqli_query($conn, "SELECT * FROM visitor_logs WHERE approval_status = 'approved' ORDER BY entry_time DESC LIMIT 20");

if (isset($_GET['approve'])) {
    $log_id = mysqli_real_escape_string($conn, $_GET['approve']);
    $admin_id = 1;
    
    mysqli_query($conn, "UPDATE visitor_logs SET approval_status = 'approved', status = 'active', approved_by = '$admin_id', approved_at = NOW() WHERE log_id = '$log_id'");
    $message = "Entry approved successfully";
}

if (isset($_POST['decline_entry'])) {
    $log_id = mysqli_real_escape_string($conn, $_POST['log_id']);
    $decline_reason = mysqli_real_escape_string($conn, $_POST['decline_reason']);
    
    mysqli_query($conn, "UPDATE visitor_logs SET approval_status = 'declined', decline_reason = '$decline_reason' WHERE log_id = '$log_id'");
    $message = "Entry declined";
}

$search_results = null;
$search_term = '';
if (isset($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    $search_results = mysqli_query($conn, "SELECT * FROM visitor_logs 
        WHERE full_name LIKE '%$search_term%' 
           OR program_department LIKE '%$search_term%' 
           OR reason LIKE '%$search_term%'
           OR email LIKE '%$search_term%'
        ORDER BY entry_time DESC");
}

if (isset($_POST['block_user'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $reason = mysqli_real_escape_string($conn, $_POST['block_reason']);
    
    mysqli_query($conn, "INSERT INTO blocked_users (email, full_name, reason, blocked_at, is_active) 
                         VALUES ('$email', '$name', '$reason', NOW(), 1)");
    $message = "User blocked successfully";
}

$blocked = mysqli_query($conn, "SELECT * FROM blocked_users WHERE is_active = 1 ORDER BY blocked_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <img src="images/images.png" alt="NEU Library Logo" class="logo" />
    <meta charset="UTF-8">
    <title>Admin Dashboard - NEU Library</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #f5f5f5;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header h1 {
            font-size: 20px;
            font-weight: 500;
            color: #1e4620;
        }
        .header-actions {
            display: flex;
            gap: 10px;
        }
        .back-btn {
            padding: 6px 12px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
        }
        .filter-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-btn {
            padding: 6px 12px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            color: #333;
        }
        .filter-btn.active {
            background: #1e4620;
            color: white;
            border-color: #1e4620;
        }
        .date-input {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-left: 3px solid #1e4620;
        }
        .stat-card.warning {
            border-left-color: #f57c00;
        }
        .stat-card.info {
            border-left-color: #1976d2;
        }
        .stat-card h3 {
            font-size: 13px;
            color: #666;
            font-weight: 400;
            margin-bottom: 8px;
        }
        .stat-number {
            font-size: 28px;
            font-weight: 500;
            color: #1e4620;
        }
        .stat-number.warning {
            color: #f57c00;
        }
        .stat-number.info {
            color: #1976d2;
        }
        .stat-label {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .card-header h2 {
            font-size: 16px;
            font-weight: 500;
            color: #1e4620;
        }
        .badge {
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        .badge.pending {
            background: #fff3e0;
            color: #f57c00;
        }
        .badge.approved {
            background: #e8f5e9;
            color: #1e4620;
        }
        .badge.declined {
            background: #ffebee;
            color: #c62828;
        }
        .badge.active {
            background: #e8f5e9;
            color: #1e4620;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-weight: 500;
            color: #666;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #f5f5f5;
        }
        .actions {
            display: flex;
            gap: 5px;
        }
        .btn-approve {
            background: #e8f5e9;
            color: #1e4620;
            border: none;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
        }
        .btn-decline {
            background: #ffebee;
            color: #c62828;
            border: none;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }
        .btn-block {
            background: none;
            border: none;
            color: #c62828;
            cursor: pointer;
            font-size: 11px;
        }
        .decline-form {
            display: inline;
        }
        .decline-input {
            padding: 3px;
            font-size: 11px;
            border: 1px solid #ddd;
            border-radius: 3px;
            width: 120px;
        }
        .message {
            background: #e8f5e9;
            color: #1e4620;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        .tabs {
            display: flex;
            gap: 2px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .tab {
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 13px;
        }
        .tab.active {
            background: #1e4620;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        hr {
            margin: 15px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        .logo {
            position: absolute;
            top: 1px;
            right: 600px;
            width: 150px;
            height: auto;
            transition: 0.5s ease;
}

        .logo:hover {
            transform: scale(1.1);
}

    </style>
</head>
<body>
    <div class="header">
        <h1>New Era University Library - Admin Dashboard</h1>
        <div class="header-actions">
            <a href="visitor_login.php" class="back-btn">← Back to Entry</a>
        </div>
    </div>
    
    <div class="filter-bar">
        <a href="?filter=today" class="filter-btn <?php echo $filter == 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?filter=week" class="filter-btn <?php echo $filter == 'week' ? 'active' : ''; ?>">This Week</a>
        <a href="?filter=month" class="filter-btn <?php echo $filter == 'month' ? 'active' : ''; ?>">This Month</a>
        
        <form method="GET" style="display: flex; gap: 5px; margin-left: auto;">
            <input type="hidden" name="filter" value="custom">
            <input type="date" name="start" class="date-input" value="<?php echo $start; ?>">
            <span style="color: #666;">to</span>
            <input type="date" name="end" class="date-input" value="<?php echo $end; ?>">
            <button type="submit" class="filter-btn">Go</button>
        </form>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Visitors (Approved)</h3>
            <div class="stat-number"><?php echo $total; ?></div>
            <div class="stat-label"><?php echo $filter_text; ?></div>
        </div>
        <div class="stat-card">
            <h3>Unique Visitors</h3>
            <div class="stat-number"><?php echo $unique; ?></div>
            <div class="stat-label">Different people</div>
        </div>
        <div class="stat-card">
            <h3>Currently Inside</h3>
            <div class="stat-number"><?php echo $active; ?></div>
            <div class="stat-label">Active now</div>
        </div>
        <div class="stat-card warning">
            <h3>Pending Approval</h3>
            <div class="stat-number warning"><?php echo $pending_count; ?></div>
            <div class="stat-label">Waiting for decision</div>
        </div>
        <div class="stat-card info">
            <h3>Today's Entries</h3>
            <div class="stat-number info"><?php echo $today_count; ?></div>
            <div class="stat-label">All statuses</div>
        </div>
        <div class="stat-card">
            <h3>This Week</h3>
            <div class="stat-number"><?php echo $week_count; ?></div>
            <div class="stat-label">Total entries</div>
        </div>
        <div class="stat-card">
            <h3>This Month</h3>
            <div class="stat-number"><?php echo $month_count; ?></div>
            <div class="stat-label">Total entries</div>
        </div>
    </div>
    
    <div class="tabs">
        <div class="tab active" onclick="showTab('pending')">Pending (<?php echo $pending_count; ?>)</div>
        <div class="tab" onclick="showTab('approved')">Approved & Active</div>
        <div class="tab" onclick="showTab('recent')">Recent Activity</div>
        <div class="tab" onclick="showTab('stats')">Statistics</div>
        <div class="tab" onclick="showTab('blocked')">Blocked Users</div>
    </div>
    
    <div id="pending-tab" class="tab-content active">
        <div class="card">
            <div class="card-header">
                <h2>Pending Approval</h2>
                <span class="badge pending"><?php echo $pending_count; ?> waiting</span>
            </div>
            
            <?php if (mysqli_num_rows($pending_entries) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email/ID</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($entry = mysqli_fetch_assoc($pending_entries)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($entry['email']); ?></td>
                        <td><?php echo ucfirst($entry['user_type']); ?></td>
                        <td><?php echo ucfirst($entry['reason']); ?></td>
                        <td><?php echo date('h:i A', strtotime($entry['entry_time'])); ?></td>
                        <td class="actions">
                            <a href="?approve=<?php echo $entry['log_id']; ?>" class="btn-approve" onclick="return confirm('Approve this entry?')">✅ Approve</a>
                            
                            <form method="POST" class="decline-form" onsubmit="return confirm('Decline this entry?')">
                                <input type="hidden" name="log_id" value="<?php echo $entry['log_id']; ?>">
                                <input type="text" name="decline_reason" placeholder="Reason" class="decline-input" required>
                                <button type="submit" name="decline_entry" class="btn-decline">❌ Decline</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color: #999; padding: 20px; text-align: center;">No pending entries</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="approved-tab" class="tab-content">
        <div class="card">
            <h2>Currently Inside Library</h2>
            
            <?php if (mysqli_num_rows($approved_active) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Reason</th>
                        <th>Entry Time</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($entry = mysqli_fetch_assoc($approved_active)): 
                        $entry_time = strtotime($entry['entry_time']);
                        $duration = time() - $entry_time;
                        $hours = floor($duration / 3600);
                        $minutes = floor(($duration % 3600) / 60);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($entry['program_department']); ?></td>
                        <td><?php echo ucfirst($entry['reason']); ?></td>
                        <td><?php echo date('h:i A', $entry_time); ?></td>
                        <td><?php echo $hours > 0 ? $hours . 'h ' : ''; ?><?php echo $minutes; ?>m</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color: #999; padding: 20px; text-align: center;">No one is currently inside</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="recent-tab" class="tab-content">
        <div class="card">
            <h2>Recent Visitor Log</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($v = mysqli_fetch_assoc($recent)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($v['full_name']); ?></td>
                        <td><?php echo ucfirst($v['user_type']); ?></td>
                        <td><?php echo ucfirst($v['reason']); ?></td>
                        <td><?php echo date('M d, h:i A', strtotime($v['entry_time'])); ?></td>
                        <td>
                            <span class="badge <?php echo $v['approval_status']; ?>">
                                <?php echo ucfirst($v['approval_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="stats-tab" class="tab-content">
        <div class="row">
            <div class="card">
                <h2>By Reason</h2>
                <table>
                    <?php 
                    mysqli_data_seek($reasons, 0);
                    while($r = mysqli_fetch_assoc($reasons)): 
                    ?>
                    <tr>
                        <td><?php echo ucfirst($r['reason']); ?></td>
                        <td style="text-align: right;"><?php echo $r['count']; ?></td>
                        <td style="width: 100px;">
                            <div style="background: #e8f5e9; height: 6px; border-radius: 3px;">
                                <div style="background: #1e4620; width: <?php echo ($r['count'] / max($total, 1)) * 100; ?>%; height: 6px; border-radius: 3px;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
            
            <div class="card">
                <h2>By User Type</h2>
                <table>
                    <?php 
                    mysqli_data_seek($types, 0);
                    while($t = mysqli_fetch_assoc($types)): 
                    ?>
                    <tr>
                        <td><?php echo ucfirst($t['user_type']); ?></td>
                        <td style="text-align: right;"><?php echo $t['count']; ?></td>
                        <td style="width: 100px;">
                            <div style="background: #e8f5e9; height: 6px; border-radius: 3px;">
                                <div style="background: #1e4620; width: <?php echo ($t['count'] / max($total, 1)) * 100; ?>%; height: 6px; border-radius: 3px;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    </div>
    
    <div id="blocked-tab" class="tab-content">
        <div class="card">
            <h2> Blocked Users</h2>
            
            <?php if (mysqli_num_rows($blocked) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Reason</th>
                        <th>Blocked Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($b = mysqli_fetch_assoc($blocked)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($b['email']); ?></td>
                        <td><?php echo htmlspecialchars($b['reason']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($b['blocked_at'])); ?></td>
                        <td><a href="?unblock=<?php echo $b['block_id']; ?>" class="btn-approve" onclick="return confirm('Unblock this user?')">Unblock</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color: #999; padding: 20px; text-align: center;">No blocked users</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <h2> Search Visitors</h2>
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search by name, program, reason, or email..." value="<?php echo htmlspecialchars($search_term); ?>">
        </form>
        
        <?php if ($search_results && mysqli_num_rows($search_results) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Program</th>
                    <th>Reason</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while($v = mysqli_fetch_assoc($search_results)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($v['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($v['email']); ?></td>
                    <td><?php echo htmlspecialchars($v['program_department']); ?></td>
                    <td><?php echo ucfirst($v['reason']); ?></td>
                    <td><?php echo date('M d, h:i A', strtotime($v['entry_time'])); ?></td>
                    <td><span class="badge <?php echo $v['approval_status']; ?>"><?php echo $v['approval_status']; ?></span></td>
                    <td>
                        <form method="POST" class="decline-form" onsubmit="return confirm('Block this user?')">
                            <input type="hidden" name="email" value="<?php echo $v['email']; ?>">
                            <input type="hidden" name="name" value="<?php echo $v['full_name']; ?>">
                            <input type="text" name="block_reason" placeholder="Block reason" class="decline-input" required>
                            <button type="submit" name="block_user" class="btn-block">🚫 Block</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php elseif ($search_term): ?>
            <p style="color: #666; padding: 20px; text-align: center;">No results found</p>
        <?php endif; ?>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.add('active');
            
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
</body>
</html>