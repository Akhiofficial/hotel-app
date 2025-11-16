<?php
session_start();
require_once __DIR__ . '/../db.php';
$config = require __DIR__ . '/../config.php';

if(!empty($_SESSION['admin'])){
    header('Location: dashboard.php');
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // try DB lookup
    $stmt = $DB->prepare("SELECT * FROM admins WHERE username=?");
    $stmt->bind_param('s',$user);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if($res){
        if(hash('sha256',$pass) === $res['password_hash']){
            $_SESSION['admin']= $res['username'];
            header('Location: dashboard.php'); exit;
        }
    }
    // fallback to config file
    if($user === $config['admin_user'] && $pass === $config['admin_pass']){
        $_SESSION['admin'] = $user;
        header('Location: dashboard.php'); exit;
    }
    $error = "Invalid credentials";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Hotel Reservation System</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1A4D2E 0%, #50B848 100%);
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1920') center/cover;
            opacity: 0.1;
        }
        
        .login-box {
            background: white;
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo i {
            font-size: 48px;
            color: #1A4D2E;
            margin-bottom: 10px;
        }
        
        .login-box h2 {
            color: #1A4D2E;
            margin-bottom: 8px;
            text-align: center;
            font-size: 28px;
            font-weight: 600;
        }
        
        .login-box p {
            text-align: center;
            color: #808080;
            margin-bottom: 35px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2C3E50;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #50B848;
            box-shadow: 0 0 0 3px rgba(80, 184, 72, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #1A4D2E;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            background: #50B848;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 77, 46, 0.3);
        }
        
        .error-message {
            background: #F8D7DA;
            color: #721C24;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #F5C6CB;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <i class="fas fa-hotel"></i>
            </div>
            <h2>Login to your account</h2>
            <p>Hotel Reservation System</p>
            
            <?php if(!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" required autofocus placeholder="Enter your username">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" required placeholder="Enter your password">
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
    </div>
</body>
</html>
