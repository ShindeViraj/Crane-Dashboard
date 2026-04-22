<?php
/**
 * Login Page
 */
require_once 'includes/auth.php';

// Already logged in? Redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
$error = '';

// Pick up flash errors (e.g. session timeout redirect)
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check — fail closed
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $result = attemptLogin($username, $password);
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SquareWave VFD Dashboard - Login">
    <title>Log In — SquareWave VFD Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body.login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f7fafc 0%, #e5e9eb 50%, #d6e3ff 100%);
            position: relative;
            overflow: hidden;
        }
        body.login-page::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 80%;
            height: 150%;
            background: linear-gradient(135deg, rgba(0,33,71,0.04), rgba(0,33,71,0.02));
            border-radius: 50%;
            transform: rotate(-15deg);
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 20px 60px rgba(0,33,71,0.08);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #002147, #003d7a);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #fff;
            margin-bottom: 16px;
        }
        .login-logo h1 {
            font-family: 'Manrope', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #002147;
            margin-bottom: 4px;
        }
        .login-logo p {
            font-size: 13px;
            color: #74777f;
            margin: 0;
        }
        .login-title {
            font-family: 'Manrope', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #181c1e;
            margin-bottom: 24px;
        }
        .form-label-custom {
            font-size: 13px;
            font-weight: 600;
            color: #44474e;
            margin-bottom: 6px;
        }
        .form-input-custom {
            border: 1.5px solid #c4c6cf;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f7fafc;
        }
        .form-input-custom:focus {
            border-color: #002147;
            box-shadow: 0 0 0 3px rgba(0,33,71,0.08);
            background: #fff;
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #002147, #003d7a);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }
        .login-btn:hover {
            background: linear-gradient(135deg, #003d7a, #004d99);
            transform: translateY(-1px);
        }
        .login-error {
            background: rgba(186,26,26,0.08);
            color: #ba1a1a;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #74777f;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <div class="login-logo-icon" style="overflow:hidden;">
                    <img src="assets/logo.png" alt="SquareWave" style="width:48px;height:48px;object-fit:contain;">
                </div>
                <h1>SQUAREWAVE</h1>
                <p>VFD Monitoring System</p>
            </div>
            
            <h2 class="login-title">Log in to your account</h2>
            
            <?php if ($error): ?>
            <div class="login-error">
                <i class="bi bi-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" id="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <div class="mb-3">
                    <label class="form-label-custom" for="username">Username or Email</label>
                    <input type="text" class="form-control form-input-custom" id="username" name="username" 
                           placeholder="Enter your username" required autofocus
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="password">Password</label>
                    <div class="position-relative">
                        <input type="password" class="form-control form-input-custom" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <button type="button" class="btn position-absolute top-50 end-0 translate-middle-y" 
                                onclick="togglePassword()" style="border:none;background:none;color:#74777f;">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember" style="font-size:13px;color:#44474e;">Remember me</label>
                </div>
                <button type="submit" class="login-btn" id="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Log In
                </button>
            </form>
            
            <div class="login-footer">
                Don't have an account? <a href="register.php" style="color:#002147;font-weight:600;text-decoration:none;">Sign up</a>
                <br><span style="margin-top:8px;display:inline-block;">SquareWave IoT Dashboard &copy; <?php echo date('Y'); ?></span>
            </div>
        </div>
    </div>
    
    <script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }
    </script>
</body>
</html>
