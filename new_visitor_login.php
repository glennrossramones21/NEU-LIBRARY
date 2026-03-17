<?php
session_start();
require_once 'connection.php';

$welcome_message = '';
if (isset($_GET['welcome']) && !empty($_GET['welcome'])) {
    $student_name = htmlspecialchars($_GET['welcome']);
    $welcome_message = "Welcome to the library, " . $student_name . "! Your entry has been approved.";
}

$message = '';
$message_type = '';

$check_column = mysqli_query($conn, "SHOW COLUMNS FROM visitor_logs LIKE 'approval_status'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE visitor_logs 
        ADD COLUMN approval_status ENUM('pending', 'approved', 'declined') DEFAULT 'pending' AFTER status,
        ADD COLUMN approved_by INT NULL AFTER approval_status,
        ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
        ADD COLUMN decline_reason TEXT NULL AFTER approved_at");
}

if (isset($_POST['visitor_login'])) {
    if (!isset($_POST['identifier']) || empty($_POST['identifier'])) {
        $message = "Please enter your email or student ID.";
        $message_type = "error";
    } else {
        $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
        $reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, $_POST['reason']) : '';
        
        $user_category = isset($_POST['user_category']) ? mysqli_real_escape_string($conn, $_POST['user_category']) : 'student';
        
        if ($user_category == 'student' && empty($reason)) {
            $message = "Please select a reason for your visit.";
            $message_type = "error";
        } else {
            $check_blocked = mysqli_query($conn, "SELECT * FROM blocked_users WHERE email = '$identifier' AND is_active = 1");
            
            if ($check_blocked && mysqli_num_rows($check_blocked) > 0) {
                $message = "Access Denied: You are blocked from using the library.";
                $message_type = "error";
            } else {
                $user_query = "SELECT * FROM users WHERE email = '$identifier' OR student_id = '$identifier' OR username = '$identifier'";
                $user_result = mysqli_query($conn, $user_query);
                
                if ($user_result && mysqli_num_rows($user_result) > 0) {
                    $user = mysqli_fetch_assoc($user_result);
                    
                    if ($user_category == 'admin') {
                        if ($user['user_type'] != 'admin' && $user['user_type'] != 'staff') {
                            $message = "Access Denied: You are not authorized as an administrator.";
                            $message_type = "error";
                        } else {
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['user_type'] = $user['user_type'];
                            $_SESSION['full_name'] = $user['full_name'];
                            header("Location: admin_dashboard.php");
                            exit();
                        }
                    } else {
                        $entry_time = date('Y-m-d H:i:s');
                        $user_type = ($user['user_type'] == 'staff' || $user['user_type'] == 'admin') ? 'faculty' : 'student';
                        $program = $user['department'] ?? 'N/A';
                        
                        $log_sql = "INSERT INTO visitor_logs (user_id, email, full_name, user_type, program_department, reason, entry_time, status) 
                                    VALUES ('{$user['user_id']}', '{$user['email']}', '{$user['full_name']}', '$user_type', '$program', '$reason', '$entry_time', 'pending')";
                        
                        if (mysqli_query($conn, $log_sql)) {
                            $log_id = mysqli_insert_id($conn);
                            
                            mysqli_query($conn, "UPDATE visitor_logs SET approval_status = 'pending' WHERE log_id = '$log_id'");
                            
                            // Store student name for notification
                            $student_name = $user['full_name'];
                            $message = "pending_notification:" . $student_name;
                            $message_type = "pending";
                        } else {
                            $message = "Error: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                } else {
                    $message = "Invalid credentials. Please check your username and try again.";
                    $message_type = "error";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NEU Library Entry</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
        }
        .container {
            width: 400px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            color: #1e4620;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-size: 14px;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #1e4620;
        }
        
        .notification-bubble {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            font-size: 16px;
            font-weight: 500;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.5s ease-out, float 3s ease-in-out infinite;
            max-width: 350px;
            pointer-events: none;
        }
        
        .notification-bubble::before {
            content: '👋';
            font-size: 20px;
            animation: wave 1s ease-in-out infinite;
        }
        
        .pending-bubble {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        
        .pending-bubble::before {
            content: '⏳';
            font-size: 20px;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        .approved-bubble {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }
        
        .approved-bubble::before {
            content: '✅';
            font-size: 20px;
        }
        
        .declined-bubble {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .declined-bubble::before {
            content: '❌';
            font-size: 20px;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }
        
        @keyframes wave {
            0%, 100% {
                transform: rotate(0deg);
            }
            25% {
                transform: rotate(10deg);
            }
            75% {
                transform: rotate(-10deg);
            }
        }
        
        .notification-bubble.fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        .welcome-message {
            background: #4CAF50;
            color: white;
            text-align: center;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: slideDown 0.5s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .success {
            background: #e8f5e9;
            color: #1e4620;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .pending {
            background: #fff3e0;
            color: #f57c00;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #1e4620;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #143214;
        }
        .admin-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .admin-link a {
            color: #666;
            text-decoration: none;
        }
        .admin-link a:hover {
            color: #1e4620;
        }
        .note {
            background: #fff3e0;
            color: #f57c00;
            padding: 10px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 20px;
            text-align: center;
        }
        .category-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .category-option {
            flex: 1;
            text-align: center;
        }
        .category-option input[type="radio"] {
            display: none;
        }
        .category-option label {
            display: block;
            padding: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0;
        }
        .category-option input[type="radio"]:checked + label {
            background: #1e4620;
            color: white;
            border-color: #1e4620;
        }
        .info-box {
            background: #e3f2fd;
            color: #0d47a1;
            padding: 10px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 10px;
        }

        .logo {
            position: absolute;
            top: 40px;
            right: 1000px;
            width: 150px;
            height: auto;
            transition: 0.5s ease;
}

        .logo:hover {
            transform: scale(1.1);
}
        
    </style>
</head>
<img src="images/images.png" alt="NEU Library Logo" class="logo" />
<body>
    <div id="notificationContainer"></div>
    
    <div class="container">
        <h1>New Era University Library</h1>
        <div class="subtitle">Please select your category and login</div>
        
        <?php if ($welcome_message): ?>
            <script>
                window.onload = function() {
                    showApprovedNotification("<?php echo $welcome_message; ?>");
                };
            </script>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <?php if (strpos($message, 'pending_notification:') === 0): ?>
                <?php $student_name = substr($message, 19); ?>
                <script>
                    window.onload = function() {
                        showPendingNotification("Welcome to NEU Library, <?php echo $student_name; ?>! Please wait for admin approval before entering.");
                    };
                </script>
            <?php else: ?>
                <div class="<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="category-selector">
                <div class="category-option">
                    <input type="radio" name="user_category" id="student" value="student" checked>
                    <label for="student">🎓 Student</label>
                </div>
                <div class="category-option">
                    <input type="radio" name="user_category" id="admin" value="admin">
                    <label for="admin">👤 Admin</label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="identifier" placeholder="Input Username" required>
            </div>
            
            <div class="form-group" id="reason-group">
                <label>Reason for Visit</label>
                <select name="reason">
                    <option value="">Select reason</option>
                    <option value="reading">Reading</option>
                    <option value="researching">Researching</option>
                    <option value="computer">Use Computer</option>
                    <option value="meeting">Meeting</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <button type="submit" name="visitor_login" id="submit-btn">Request Entry</button>
        </form>
        
        <div class="note" id="student-note">
            Students: Your entry will be pending admin approval
        </div>
        
        <div class="info-box" id="admin-note" style="display: none;">
            Administrators: You will be redirected to the admin dashboard after successful login
        </div>
    </div>

    <script>
        function showPendingNotification(message) {
            const container = document.getElementById('notificationContainer');
            
            const notification = document.createElement('div');
            notification.className = 'notification-bubble pending-bubble';
            notification.textContent = message;
            
            container.appendChild(notification);
            
            setTimeout(function() {
                notification.classList.add('fade-out');
                
                setTimeout(function() {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }, 5000);
        }
        
        function showApprovedNotification(message) {
            const container = document.getElementById('notificationContainer');
            
            const notification = document.createElement('div');
            notification.className = 'notification-bubble approved-bubble';
            notification.textContent = message;
            
            container.appendChild(notification);
            
            setTimeout(function() {
                notification.classList.add('fade-out');
                
                setTimeout(function() {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }, 5000);
        }
        
        function showDeclinedNotification(message) {
            const container = document.getElementById('notificationContainer');
            
            const notification = document.createElement('div');
            notification.className = 'notification-bubble declined-bubble';
            notification.textContent = message;
            
            container.appendChild(notification);
            
            setTimeout(function() {
                notification.classList.add('fade-out');
                
                setTimeout(function() {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }, 5000);
        }
        
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const welcomeParam = urlParams.get('welcome');
            
            if (welcomeParam) {
                showApprovedNotification('Welcome to the library, ' + decodeURIComponent(welcomeParam) + '! Your entry has been approved.');
            }
            
            const statusParam = urlParams.get('status');
            const nameParam = urlParams.get('name');
            const reasonParam = urlParams.get('reason');
            
            if (statusParam === 'declined' && nameParam) {
                let message = 'Sorry, ' + decodeURIComponent(nameParam) + '. Your entry request has been declined.';
                if (reasonParam) {
                    message += ' Reason: ' + decodeURIComponent(reasonParam);
                }
                showDeclinedNotification(message);
            }
        });
        
        const studentRadio = document.getElementById('student');
        const adminRadio = document.getElementById('admin');
        const reasonGroup = document.getElementById('reason-group');
        const reasonSelect = document.querySelector('select[name="reason"]');
        const submitBtn = document.getElementById('submit-btn');
        const studentNote = document.getElementById('student-note');
        const adminNote = document.getElementById('admin-note');
        
        function updateUI() {
            if (adminRadio.checked) {
                reasonGroup.style.display = 'none';
                reasonSelect.required = false;
                submitBtn.textContent = 'Login as Admin';
                studentNote.style.display = 'none';
                adminNote.style.display = 'block';
            } else {
                reasonGroup.style.display = 'block';
                reasonSelect.required = true;
                submitBtn.textContent = 'Request Entry';
                studentNote.style.display = 'block';
                adminNote.style.display = 'none';
            }
        }
        
        studentRadio.addEventListener('change', updateUI);
        adminRadio.addEventListener('change', updateUI);
        
        updateUI();
    </script>
</body>
</html>