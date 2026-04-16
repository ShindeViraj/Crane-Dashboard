<?php
/**
 * Authentication Helper
 * Handles login, logout, session management, password changes
 */

require_once __DIR__ . '/../db/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 days
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login — redirect to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    static $user = null;
    if ($user !== null) return $user;
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, display_name, role FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Attempt login with username/email and password
 */
function attemptLogin($username, $password) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :login OR email = :login LIMIT 1");
        $stmt->execute([':login' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            return ['success' => true, 'user' => $user];
        }
        
        return ['success' => false, 'error' => 'Invalid username or password.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error. Please try again.'];
    }
}

/**
 * Logout — destroy session
 */
function logout() {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Change password
 */
function changePassword($userId, $currentPassword, $newPassword) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'error' => 'User not found.'];
        }
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect.'];
        }
        
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'New password must be at least 6 characters.'];
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $stmt->execute([':hash' => $newHash, ':id' => $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error.'];
    }
}

/**
 * Update profile
 */
function updateProfile($userId, $displayName, $email, $username) {
    try {
        $pdo = getDbConnection();
        
        // Check for duplicate username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
        $stmt->execute([':username' => $username, ':email' => $email, ':id' => $userId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username or email already taken.'];
        }
        
        $stmt = $pdo->prepare("UPDATE users SET display_name = :name, email = :email, username = :username WHERE id = :id");
        $stmt->execute([':name' => $displayName, ':email' => $email, ':username' => $username, ':id' => $userId]);
        
        $_SESSION['display_name'] = $displayName;
        $_SESSION['username'] = $username;
        
        return ['success' => true, 'message' => 'Profile updated successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error.'];
    }
}

/**
 * CSRF token helpers
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
