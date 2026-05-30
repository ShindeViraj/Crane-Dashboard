<?php
/**
 * Authentication & RBAC Helper
 * Handles login, logout, registration, session management, 
 * role-based access, and crane assignment
 */

require_once __DIR__ . '/../db/config.php';

// ── Session Hardening ──────────────────────────────────────────────
// Prevent session ID via URL params; reject uninitialized session IDs
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    // Detect HTTPS for dynamic Secure flag
    $isSecure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );

    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 days
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ── Inactivity Timeout (30 minutes) ────────────────────────────────
define('SESSION_TIMEOUT', 1800); // seconds

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        // Session idle too long — force logout
        $isApiRequest = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false);
        logout();

        if ($isApiRequest) {
            // API callers get a JSON response, not a redirect
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Session expired due to inactivity.']);
            exit;
        }

        // Start a fresh session for the flash message
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $_SESSION['flash_error'] = 'Your session has expired due to inactivity. Please log in again.';
        header('Location: login.php');
        exit;
    }
    // Touch the timestamp on every valid request
    $_SESSION['last_activity'] = time();
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
 * Require specific role(s) — redirect with 403 if unauthorized
 * @param string|array $roles Allowed role(s): 'developer', 'admin', 'user'
 */
function requireRole($roles) {
    requireLogin();
    if (is_string($roles)) $roles = [$roles];
    
    $user = getCurrentUser();
    if (!$user || !in_array($user['role'], $roles)) {
        // Redirect to dashboard with access denied
        $_SESSION['flash_error'] = 'Access denied. You do not have permission to view that page.';
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Check if current user has one of the given roles (no redirect)
 */
function hasRole($roles) {
    if (is_string($roles)) $roles = [$roles];
    $user = getCurrentUser();
    return $user && in_array($user['role'], $roles);
}

/**
 * Get current user data (cached per request)
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
 * Get crane IDs assigned to a specific user
 * Returns array of crane_id strings. Developers/admins get ALL cranes.
 */
function getUserAssignedCranes($userId = null) {
    if ($userId === null && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }
    if (!$userId) return [];
    
    $user = getCurrentUser();
    
    // Developers and admins see all cranes
    if ($user && in_array($user['role'], ['developer', 'admin'])) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT crane_id FROM cranes ORDER BY crane_id ASC");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Regular users only see assigned cranes
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT crane_id FROM user_cranes WHERE user_id = :uid ORDER BY crane_id ASC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Check if a user has access to a specific crane
 */
function canAccessCrane($craneId, $userId = null) {
    $user = getCurrentUser();
    if ($user && in_array($user['role'], ['developer', 'admin'])) {
        return true; // Full access
    }
    $assigned = getUserAssignedCranes($userId);
    return in_array($craneId, $assigned);
}

/**
 * Assign a crane to a user
 */
function assignCraneToUser($userId, $craneId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_cranes (user_id, crane_id) VALUES (:uid, :cid)");
        $stmt->execute([':uid' => $userId, ':cid' => $craneId]);
        return ['success' => true, 'message' => 'Crane assigned successfully.'];
    } catch (PDOException $e) {
        error_log('[BML-IOT] assignCraneToUser failed: ' . $e->getMessage() . ' | user=' . $userId . ' crane=' . $craneId . ' | ' . date('c'));
        return ['success' => false, 'error' => 'Failed to assign crane. Please try again.'];
    }
}

/**
 * Unassign a crane from a user
 */
function unassignCraneFromUser($userId, $craneId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM user_cranes WHERE user_id = :uid AND crane_id = :cid");
        $stmt->execute([':uid' => $userId, ':cid' => $craneId]);
        return ['success' => true, 'message' => 'Crane unassigned successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Failed to unassign crane.'];
    }
}

/**
 * Register a new user (default role: 'user')
 */
function registerUser($username, $email, $password, $displayName) {
    $username = trim($username);
    $email = trim($email);
    $displayName = trim($displayName);
    
    // Validation
    if (empty($username) || empty($password)) {
        return ['success' => false, 'error' => 'Username and password are required.'];
    }
    if (strlen($username) < 3 || strlen($username) > 50) {
        return ['success' => false, 'error' => 'Username must be 3-50 characters.'];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['success' => false, 'error' => 'Username can only contain letters, numbers, and underscores.'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters.'];
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.'];
    }
    if (empty($displayName)) {
        $displayName = $username;
    }
    
    try {
        $pdo = getDbConnection();
        
        // Check duplicate username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username is already taken.'];
        }
        
        // Check duplicate email
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :e");
            $stmt->execute([':e' => $email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Email is already registered.'];
            }
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, display_name, role) VALUES (:u, :e, :p, :d, 'user')");
        $stmt->execute([
            ':u' => $username,
            ':e' => !empty($email) ? $email : null,
            ':p' => $hash,
            ':d' => $displayName
        ]);
        
        return ['success' => true, 'message' => 'Account created successfully! You can now log in.', 'user_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            return ['success' => false, 'error' => 'Username or email already exists.'];
        }
        return ['success' => false, 'error' => 'Registration failed. Please try again.'];
    }
}

/**
 * Attempt login with username/email and password
 */
function attemptLogin($username, $password) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, display_name, role, password_hash FROM users WHERE username = :login OR email = :login LIMIT 1");
        $stmt->execute([':login' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time(); // initialise inactivity timer
            
            // Regenerate session ID for security and rotate CSRF token
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']); // discard pre-auth token — new one generated on next form load
            
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
    // Explicitly clear CSRF token before wiping the session
    unset($_SESSION['csrf_token']);
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
        
        // Check for duplicate username/email
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
