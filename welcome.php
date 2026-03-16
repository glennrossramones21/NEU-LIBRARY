<?php
session_start();

if (!isset($_SESSION['welcome'])) {
    header("Location: visitor_login.php");
    exit();
}

$name = $_SESSION['welcome']['name'];
$program = $_SESSION['welcome']['program'];
unset($_SESSION['welcome']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to NEU Library</title>
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
        }
        .container {
            width: 450px;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            font-size: 28px;
            color: #1e4620;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
        }
        .name {
            font-size: 24px;
            font-weight: 600;
            color: #1e4620;
            margin-bottom: 5px;
        }
        .program {
            color: #666;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .time {
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 10px 30px;
            background: #1e4620;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn:hover {
            background: #143214;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✨ Welcome to NEU Library</h1>
        
        <div class="name"><?php echo htmlspecialchars($name); ?></div>
        <div class="program"><?php echo htmlspecialchars($program); ?></div>
        
        <div class="message">You have successfully logged your entry.</div>
        <div class="time"><?php echo date('l, F j, Y - h:i A'); ?></div>
        
        <a href="visitor_login.php" class="btn">Back to Entry</a>
    </div>
</body>
</html>