<?php
/**
 * Registration Page — Public sign-up
 */
require_once 'includes/auth.php';

// Already logged in? Redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $result = registerUser($username, $email, $password, $displayName);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SquareWave VFD Dashboard - Create Account">
    <title>Sign Up — SquareWave VFD Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body.register-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f7fafc 0%, #e5e9eb 50%, #d6e3ff 100%);
            position: relative;
            overflow: hidden;
        }
        body.register-page::before {
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
        .register-container {
            width: 100%;
            max-width: 460px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        .register-card {
            background: #fff;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 20px 60px rgba(0,33,71,0.08);
        }
        .register-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .register-logo-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #002147, #003d7a);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .register-logo-icon img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }
        .register-logo h1 {
            font-family: 'Manrope', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #002147;
            margin-bottom: 4px;
        }
        .register-logo p {
            font-size: 13px;
            color: #74777f;
            margin: 0;
        }
        .register-title {
            font-family: 'Manrope', sans-serif;
            font-size: 17px;
            font-weight: 700;
            color: #181c1e;
            margin-bottom: 20px;
        }
        .form-label-custom {
            font-size: 13px;
            font-weight: 600;
            color: #44474e;
            margin-bottom: 5px;
        }
        .form-input-custom {
            border: 1.5px solid #c4c6cf;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f7fafc;
        }
        .form-input-custom:focus {
            border-color: #002147;
            box-shadow: 0 0 0 3px rgba(0,33,71,0.08);
            background: #fff;
        }
        .register-btn {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #002147, #003d7a);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 6px;
        }
        .register-btn:hover {
            background: linear-gradient(135deg, #003d7a, #004d99);
            transform: translateY(-1px);
        }
        .register-error {
            background: rgba(186,26,26,0.08);
            color: #ba1a1a;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .register-success {
            background: rgba(0,110,37,0.08);
            color: #006e25;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .register-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #74777f;
        }
        .register-footer a {
            color: #002147;
            font-weight: 600;
            text-decoration: none;
        }
        .register-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="register-page">
    <div class="register-container">
        <div class="register-card">
            <div class="register-logo">
                <div class="register-logo-icon">
                    <img src="assets/logo.png" alt="SquareWave">
                </div>
                <h1>SQUAREWAVE</h1>
                <p>VFD Monitoring System</p>
            </div>
            
            <h2 class="register-title">Create your account</h2>
            
            <?php if ($error): ?>
            <div class="register-error">
                <i class="bi bi-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="register-success">
                <i class="bi bi-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
                <a href="login.php" style="margin-left:auto;font-weight:600;color:#006e25;">Log in →</a>
            </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="register.php" id="register-form">
                <div class="row g-2">
                    <div class="col-md-6 mb-2">
                        <label class="form-label-custom" for="username">Username *</label>
                        <input type="text" class="form-control form-input-custom" id="username" name="username" 
                               placeholder="e.g. john_doe" required minlength="3" maxlength="50"
                               pattern="[a-zA-Z0-9_]+"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label-custom" for="display_name">Display Name</label>
                        <input type="text" class="form-control form-input-custom" id="display_name" name="display_name" 
                               placeholder="e.g. John Doe"
                               value="<?php echo htmlspecialchars($_POST['display_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label-custom" for="email">Email Address</label>
                    <input type="email" class="form-control form-input-custom" id="email" name="email" 
                           placeholder="you@company.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-2">
                        <label class="form-label-custom" for="password">Password *</label>
                        <input type="password" class="form-control form-input-custom" id="password" name="password" 
                               placeholder="Min 6 characters" required minlength="6">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label-custom" for="confirm_password">Confirm Password *</label>
                        <input type="password" class="form-control form-input-custom" id="confirm_password" name="confirm_password" 
                               placeholder="Repeat password" required>
                    </div>
                </div>
                <button type="submit" class="register-btn" id="btn-register">
                    <i class="bi bi-person-plus"></i> Create Account
                </button>
            </form>
            <?php endif; ?>
            
            <div class="register-footer">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </div>
</body>
</html>
