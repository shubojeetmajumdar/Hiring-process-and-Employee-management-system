<?php
// Set JSON response header
header('Content-Type: application/json');

// Include global database configuration
require_once 'config.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Get the raw input JSON payload
$inputRaw = file_get_contents('php://input');
$data = json_decode($inputRaw, true);

// Fallback to form parameters if JSON decoding is empty
if (!$data) {
    $data = $_POST;
}

$role = isset($data['role']) ? trim($data['role']) : '';
$identifier = isset($data['identifier']) ? trim($data['identifier']) : '';
$password = isset($data['password']) ? $data['password'] : '';

// Validate fields
if (empty($role) || empty($identifier) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.'
    ]);
    exit;
}

// Determine query based on role
try {
    if ($role === 'employee') {
        // Employees can sign in with Employee ID or Email address
        // Note: PDO native prepared statements require unique parameter names even for same value
        $stmt = $pdo->prepare("SELECT * FROM `employees` WHERE (`employee_id` = :id1 OR `email` = :id2) LIMIT 1");
        $stmt->execute(['id1' => $identifier, 'id2' => $identifier]);
        $user = $stmt->fetch();

        if ($user && $user['status'] !== 'Active') {
            echo json_encode([
                'success' => false,
                'message' => 'This employee account is currently inactive.'
            ]);
            exit;
        }

    } else if ($role === 'hr') {
        // HR/Recruiters sign in via corporate email
        $stmt = $pdo->prepare("SELECT * FROM `recruiters` WHERE `email` = :identifier LIMIT 1");
        $stmt->execute(['identifier' => $identifier]);
        $user = $stmt->fetch();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user role selected.'
        ]);
        exit;
    }

    // Verify Password against stored Bcrypt hash
    if ($user && password_verify($password, $user['password'])) {
        // Set user session parameters
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $role;
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        if ($role === 'employee') {
            $_SESSION['employee_id'] = $user['employee_id'];
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login successful!'
        ]);
    } else {
        // Standard non-revealing error response for security
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials or password.'
        ]);
    }

} catch (PDOException $e) {
    // Database query error logging (avoid showing SQL details to the client in production)
    echo json_encode([
        'success' => false,
        'message' => 'A system database error occurred. Please try again later.'
    ]);
}
?>
