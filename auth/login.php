<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Attendance System</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 15px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
            animation: slideIn 0.3s ease-in-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .login-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }

        .login-header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .login-body {
            padding: 30px 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group .form-control {
            flex: 1;
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password:hover {
            color: #764ba2;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
            color: #999;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e0e0;
            z-index: 0;
        }

        .divider span {
            background: white;
            padding: 0 10px;
            position: relative;
            z-index: 1;
            font-size: 13px;
        }

        .test-credentials {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 0;
        }

        .test-credentials h6 {
            margin: 0 0 10px 0;
            font-size: 13px;
            font-weight: 600;
            color: #333;
        }

        .test-credentials p {
            margin: 5px 0;
            font-size: 12px;
            color: #555;
            font-family: 'Monaco', 'Menlo', monospace;
        }

        .test-credentials p strong {
            color: #667eea;
        }

        /* Mobile (0px - 576px) */
        @media (max-width: 575.98px) {
            .login-wrapper {
                max-width: 100%;
            }

            .login-header {
                padding: 25px 15px;
            }

            .login-header h2 {
                font-size: 24px;
            }

            .login-body {
                padding: 25px 15px;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-control {
                font-size: 16px;
                padding: 12px 12px;
            }

            .login-btn {
                font-size: 15px;
                padding: 11px;
            }

            .test-credentials p {
                font-size: 11px;
            }
        }

        /* Tablet (576px - 768px) */
        @media (min-width: 576px) and (max-width: 768px) {
            .login-wrapper {
                max-width: 400px;
            }

            .login-header {
                padding: 28px 18px;
            }

            .login-header h2 {
                font-size: 26px;
            }

            .login-body {
                padding: 28px 18px;
            }
        }

        /* Desktop (768px and up) */
        @media (min-width: 769px) {
            .login-wrapper {
                max-width: 450px;
            }

            .login-header {
                padding: 30px 20px;
            }
        }

        /* Landscape orientation */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                min-height: auto;
                padding: 10px;
            }

            .login-header {
                padding: 15px 20px;
            }

            .login-header h2 {
                font-size: 22px;
            }

            .login-body {
                padding: 20px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .divider {
                margin: 15px 0;
            }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <h2>Attendance System</h2>
            <p>Employee Check-In</p>
        </div>
        
        <div class="login-body">
            <form method="POST" action="login_process.php">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input 
                        type="tel" 
                        id="phone"
                        name="phone" 
                        class="form-control" 
                        placeholder="Enter your phone number" 
                        required
                        inputmode="tel"
                        pattern="[0-9]{10,}"
                        title="Phone number must contain only digits (minimum 10 digits)"
                        onkeypress="return /[0-9]/.test(String.fromCharCode(event.which))"
                        onpaste="event.preventDefault()"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <input 
                            type="password" 
                            id="password"
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password" 
                            required
                        >
                        <button 
                            type="button" 
                            class="toggle-password" 
                            id="togglePassword" 
                            onclick="togglePasswordVisibility()"
                            aria-label="Toggle password visibility"
                        >
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn">Sign In</button>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="../admin/reset_password.php" style="color: #667eea; text-decoration: none; font-weight: 600; font-size: 14px;">
                        🔐 Password Reset
                    </a>
                </div>
            </form>
            
            <div class="divider"><span>Sanmarg Pvt Ltd</span></div>
            
  <!--          <div class="test-credentials">
                <h6>👤 Super Admin </h6>
                <p><strong>Phone:</strong> 9830376202</p>
                <p><strong>Password:</strong> admin123</p>

                <h6 style="margin-top: 10px;">👥 Admin</h6>
                <p><strong>Phone:</strong> 9830376200</p>
                <p><strong>Password:</strong> admin123</p>
                
                <h6 style="margin-top: 10px;">👥 Employee</h6>
                <p><strong>Phone:</strong> 9830376201</p>
                <p><strong>Password:</strong> admin123</p>

                <h6 style="margin-top: 10px;">👥 Operators</h6>
                <p><strong>Phone:</strong> 9830376203</p>
                <p><strong>Password:</strong> admin123</p>
            </div> -->
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script>
function togglePasswordVisibility() {
    const passwordField = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePassword');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        passwordField.type = 'password';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}
</script>

<script>
<?php
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $messages = [
        'invalid_phone' => 'Invalid phone number',
        'invalid_password' => 'Invalid phone number or password',
        'user_not_found' => 'User not found',
        'database_error' => 'Database error occurred',
        'invalid_request' => 'Invalid request'
    ];
    $msg = $messages[$error] ?? 'Login failed';
    $msg_escaped = addslashes($msg);
    echo "Swal.fire({
        icon: 'error',
        title: 'Login Failed',
        text: '$msg_escaped',
        confirmButtonColor: '#3085d6',
        confirmButtonText: 'Try Again'
    });";
}
?>
</script>

</body>
</html>
