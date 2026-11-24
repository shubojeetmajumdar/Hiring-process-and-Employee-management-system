<?php
// Include configuration and database connection
require_once 'config.php';

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Clear and destroy the session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

// Check authentication state
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : '';
$userName = $isLoggedIn ? $_SESSION['name'] : '';
$userEmail = $isLoggedIn ? $_SESSION['email'] : '';
$userEmpId = ($isLoggedIn && $userRole === 'employee') ? $_SESSION['employee_id'] : '';

// Redirect if already logged in
if ($isLoggedIn) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure portal to access the employee dashboard and HR recruiter management panel.">
    <title>HR & Employee Portal | <?php echo $isLoggedIn ? 'Dashboard' : 'Login'; ?></title>
    
    <!-- Link to premium typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Core Custom Stylesheet -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div class="login-container<?php echo ($isLoggedIn && $userRole === 'hr') ? ' role-hr' : ''; ?>">
        
        <!-- Left Side: Interactive Graphic Panel -->
        <aside class="visual-panel">
            <!-- Background Image -->
            <img src="images/login_illustration.jpg" alt="HR Talent Ecosystem Visualization" class="visual-panel-bg">
            
            <header class="visual-header">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <!-- Interlinked human resource geometric nodes -->
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/>
                        <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>
                        <circle cx="6" cy="7" r="1" />
                        <circle cx="18" cy="7" r="1" />
                        <circle cx="6" cy="17" r="1" />
                        <circle cx="18" cy="17" r="1" />
                    </svg>
                </div>
                <span class="logo-text">NexusLink</span>
            </header>

            <div class="visual-content">
                <h1 class="visual-title">Connecting Talent with Enterprise.</h1>
                <p class="visual-description">
                    Access our unified system designed for streamlined recruitment channels and optimized workforce management analytics.
                </p>
            </div>

            <footer class="visual-footer">
                <div class="system-status">
                    <span class="status-dot"></span>
                    <span>All Enterprise Systems Operational</span>
                </div>
                <span class="copyright-text">&copy; 2026 NexusLink Corp.</span>
            </footer>
        </aside>

        <!-- Right Side: Dashboard or Credentials Form Panel -->
        <main class="form-panel">
            
            <?php if ($isLoggedIn): ?>
                <!-- SECURE DASHBOARD WELCOME CARD -->
                <header class="form-header">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: <?php echo $userRole === 'hr' ? 'var(--accent-teal)' : 'var(--primary-color)'; ?>">
                        <span class="status-dot" style="background-color: <?php echo $userRole === 'hr' ? 'var(--accent-teal)' : 'var(--primary-color)'; ?>; box-shadow: 0 0 8px <?php echo $userRole === 'hr' ? 'var(--accent-teal)' : 'var(--primary-color)'; ?>;"></span>
                        Session Verified
                    </div>
                    <h2 class="form-title" style="margin-bottom: 4px;">Welcome back,</h2>
                    <h2 class="form-title" style="font-size: 2.25rem; font-weight: 800; background: linear-gradient(to right, #ffffff, var(--text-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 16px;">
                        <?php echo htmlspecialchars($userName); ?>
                    </h2>
                    <p class="form-subtitle">Authenticated Access Role: <strong><?php echo $userRole === 'hr' ? 'HR / Recruiter' : 'Employee'; ?></strong></p>
                </header>

                <div style="background-color: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 24px; margin-bottom: 36px;">
                    <div style="display: flex; flex-direction: column; gap: 14px; font-size: 0.95rem;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                            <span style="color: var(--text-secondary);">Registered Email:</span>
                            <span style="font-weight: 600;"><?php echo htmlspecialchars($userEmail); ?></span>
                        </div>
                        <?php if ($userRole === 'employee'): ?>
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                                <span style="color: var(--text-secondary);">Employee ID:</span>
                                <span style="font-weight: 600; font-family: monospace; letter-spacing: 0.5px;"><?php echo htmlspecialchars($userEmpId); ?></span>
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Portal Permission:</span>
                            <span style="font-weight: 600; color: <?php echo $userRole === 'hr' ? 'var(--accent-teal)' : 'var(--primary-color)'; ?>;">
                                <?php echo $userRole === 'hr' ? 'Admin Full Access' : 'Dashboard Member'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <a href="?action=logout" class="submit-btn" style="text-decoration: none;">
                    <span class="btn-text">Log Out Session</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                    </svg>
                </a>

            <?php else: ?>
                <!-- LOGIN FORMS VIEW -->
                <header class="form-header">
                    <h2 class="form-title" id="formTitle">Employee Login</h2>
                    <p class="form-subtitle" id="formSubtitle">Access your employee portal & workspace dashboard</p>
                </header>

                <!-- Role Selector Toggle -->
                <div class="role-selector">
                    <button type="button" class="role-btn active" data-role="employee">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Employee
                    </button>
                    <button type="button" class="role-btn" data-role="hr">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                        </svg>
                        HR / Recruiter
                    </button>
                    <div class="role-slider"></div>
                </div>

                <!-- Dynamic Alert Messaging Container -->
                <div class="alert-box" id="formAlert"></div>

                <!-- Forms Wrapper (Slide Animations Area) -->
                <div class="form-wrapper">
                    
                    <!-- Form 1: Employee Credentials -->
                    <form action="" method="POST" class="form-slide active" id="employeeLoginForm" novalidate>
                        <div class="form-group">
                            <input type="text" id="employee_id" class="input-control" placeholder=" " required autocomplete="username">
                            <label for="employee_id" class="floating-label">Employee ID or Email</label>
                        </div>

                        <div class="form-group">
                            <input type="password" id="employee_password" class="input-control" placeholder=" " required autocomplete="current-password">
                            <label for="employee_password" class="floating-label">Password</label>
                            <button type="button" class="password-toggle-btn" data-target="employee_password" aria-label="Toggle Password Visibility">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12.5c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                </svg>
                            </button>
                        </div>

                        <div class="form-actions">
                            <label class="remember-me">
                                <input type="checkbox" id="emp_remember">
                                <span class="checkbox-custom">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                    </svg>
                                </span>
                                Remember me
                            </label>
                            <a href="#" class="forgot-password">Forgot Password?</a>
                        </div>

                        <button type="submit" class="submit-btn" id="employeeSubmitBtn">
                            <span class="btn-text">Sign In</span>
                            <div class="spinner"></div>
                        </button>
                    </form>

                    <!-- Form 2: HR Recruiting Credentials -->
                    <form action="" method="POST" class="form-slide" id="hrLoginForm" novalidate>
                        <div class="form-group">
                            <input type="email" id="hr_email" class="input-control" placeholder=" " required autocomplete="email">
                            <label for="hr_email" class="floating-label">Corporate Email</label>
                        </div>

                        <div class="form-group">
                            <input type="password" id="hr_password" class="input-control" placeholder=" " required autocomplete="current-password">
                            <label for="hr_password" class="floating-label">Security Password</label>
                            <button type="button" class="password-toggle-btn" data-target="hr_password" aria-label="Toggle Password Visibility">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12.5c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                </svg>
                            </button>
                        </div>

                        <div class="form-actions">
                            <label class="remember-me">
                                <input type="checkbox" id="hr_remember">
                                <span class="checkbox-custom">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                    </svg>
                                </span>
                                Remember me
                            </label>
                            <a href="#" class="forgot-password">Recover HR Key?</a>
                        </div>

                        <button type="submit" class="submit-btn" id="hrSubmitBtn">
                            <span class="btn-text">Sign In</span>
                            <div class="spinner"></div>
                        </button>
                    </form>

                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Client-Side Animations & Interactivity script -->
    <script src="js/main.js"></script>
</body>
</html>
