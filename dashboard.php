<?php
// dashboard.php - HR hiring and Employee Management Dashboard Hub
require_once 'config.php';

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$userName = $_SESSION['name'];

// Define standard employee ID context
$userEmpId = '';
if ($userRole === 'employee') {
    $userEmpId = $_SESSION['employee_id'];
} else if ($userRole === 'hr') {
    $userEmpId = 'HR-ADMIN'; // Admin placeholder context
}

// Toast status messages
$successMsg = '';
$errorMsg = '';

if (isset($_SESSION['success_msg'])) {
    $successMsg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $errorMsg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// 2. Fetch Detailed Profile Info
try {
    if ($userRole === 'employee') {
        $stmtProfile = $pdo->prepare("SELECT * FROM `employees` WHERE `id` = :id LIMIT 1");
        $stmtProfile->execute(['id' => $userId]);
        $profile = $stmtProfile->fetch();
    } else {
        // HR/Recruiter profile info
        $stmtProfile = $pdo->prepare("SELECT * FROM `recruiters` WHERE `id` = :id LIMIT 1");
        $stmtProfile->execute(['id' => $userId]);
        $profile = $stmtProfile->fetch();
        // Set HR specific fallback keys for uniform card display
        $profile['employee_id'] = 'HR-ADMIN';
        $profile['designation'] = 'Lead Talent Acquisition';
        $profile['department'] = 'Human Resources';
        $profile['manager'] = 'Executive Board';
        $profile['phone'] = '+1 (555) 099-1100';
        $profile['dob'] = '1988-11-20';
        $profile['gender'] = 'Female';
        $profile['address'] = 'Corporate Headquaters, Suite 800, New York';
        $profile['emergency_contact'] = 'Security desk (+1-555-099-1111)';
        $profile['joining_date'] = '2020-01-15';
        $profile['location'] = 'New York Office';
        $profile['employment_type'] = 'Full Time';
        $profile['photo'] = 'images/hr_avatar.jpg';
        $profile['status'] = 'Active';
    }
} catch (PDOException $e) {
    die("Profile retrieve failure: " . $e->getMessage());
}

// 3. Handle AJAX API Actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'track_requisition_ajax') {
        header('Content-Type: application/json');
        $reqId = isset($_GET['requisition_id']) ? trim($_GET['requisition_id']) : '';
        
        try {
            $stmt = $pdo->prepare("SELECT r.*, COALESCE(e.name, r.employee_id) as employee_name FROM `hiring_requisitions` r LEFT JOIN `employees` e ON r.employee_id = e.employee_id WHERE r.requisition_id = :req_id LIMIT 1");
            $stmt->execute(['req_id' => $reqId]);
            $req = $stmt->fetch();

            if (!$req) {
                echo json_encode(['success' => false, 'message' => 'Requisition not found.']);
                exit;
            }

            // Verify access (employee can track their own, HR can track any)
            if ($userRole !== 'hr' && $req['employee_id'] !== $userEmpId) {
                echo json_encode(['success' => false, 'message' => 'Access denied.']);
                exit;
            }

            // Map workflow status stages
            $status = $req['status'];
            $stages = [];

            // Stage 1: Submitted
            $stages[] = [
                'label' => 'Requisition Submitted',
                'description' => 'Raised by ' . htmlspecialchars($req['employee_name'] ?? $req['employee_id']) . ' on ' . htmlspecialchars($req['request_date']),
                'status' => 'completed'
            ];

            // Stage 2: Department Head Approval
            $deptStatus = 'pending';
            $deptDesc = 'Awaiting review from Department Head.';
            if (in_array($status, ['Pending HR Approval', 'Pending Management Approval', 'Approved', 'Recruitment In Progress', 'Closed'])) {
                $deptStatus = 'completed';
                $deptDesc = 'Approved by Engineering Department Head.';
            } else if ($status === 'Pending Department Approval') {
                $deptStatus = 'current';
            } else if ($status === 'Rejected' && $req['comments'] === 'Dept Rejected') {
                $deptStatus = 'rejected';
                $deptDesc = 'Rejected by Department Head.';
            }
            $stages[] = ['label' => 'Department Review', 'description' => $deptDesc, 'status' => $deptStatus];

            // Stage 3: HR Vet & Review
            $hrStatus = 'pending';
            $hrDesc = 'Awaiting HR vetting and recruitment validation.';
            if (in_array($status, ['Pending Management Approval', 'Approved', 'Recruitment In Progress', 'Closed'])) {
                $hrStatus = 'completed';
                $hrDesc = 'Vetted and validated by HR Manager (Sarah Jenkins).';
            } else if ($status === 'Pending HR Approval') {
                $hrStatus = 'current';
            } else if ($status === 'Rejected' && strpos($req['comments'], 'HR') !== false) {
                $hrStatus = 'rejected';
                $hrDesc = 'Rejected during HR Vetting.';
            }
            $stages[] = ['label' => 'HR Operations Vetting', 'description' => $hrDesc, 'status' => $hrStatus];

            // Stage 4: Management Budget Board Approval
            $mgmtStatus = 'pending';
            $mgmtDesc = 'Awaiting executive budget clearance.';
            if (in_array($status, ['Approved', 'Recruitment In Progress', 'Closed'])) {
                $mgmtStatus = 'completed';
                $mgmtDesc = 'Budget cleared and requisition approved.';
            } else if ($status === 'Pending Management Approval') {
                $mgmtStatus = 'current';
            } else if ($status === 'On Hold') {
                $mgmtStatus = 'current';
                $mgmtDesc = 'Placed on hold pending Q3 balance reviews.';
            } else if ($status === 'Rejected' && strpos($req['comments'], 'Budget') !== false) {
                $mgmtStatus = 'rejected';
                $mgmtDesc = 'Rejected: Exceeds annual departmental budget allocation.';
            }
            $stages[] = ['label' => 'Executive Clearance', 'description' => $mgmtDesc, 'status' => $mgmtStatus];

            // Stage 5: Recruiter Channel Funnel
            $recStatus = 'pending';
            $recDesc = 'Awaiting talent search channel mapping.';
            if ($status === 'Recruitment In Progress') {
                $recStatus = 'current';
                $recDesc = 'Active recruiter searching. CV screening in progress.';
            } else if ($status === 'Closed') {
                $recStatus = 'completed';
                $recDesc = 'Position filled and hiring closed.';
            }
            $stages[] = ['label' => 'Recruitment & CV Sourcing', 'description' => $recDesc, 'status' => $recStatus];

            echo json_encode([
                'success' => true,
                'current_status' => $status,
                'department' => $req['department'],
                'request_date' => $req['request_date'],
                'stages' => $stages
            ]);
            exit;

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'AJAX DB Error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// 4. Handle POST Actions (Forms Submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Action 4.1: Apply Leave Request
    if (isset($_POST['action_apply_leave'])) {
        $type = trim($_POST['leave_type']);
        $from = trim($_POST['from_date']);
        $to = trim($_POST['to_date']);
        $days = (int)$_POST['num_days'];
        $reason = trim($_POST['reason']);

        if (empty($type) || empty($from) || empty($to) || $days <= 0 || empty($reason)) {
            $_SESSION['error_msg'] = "All fields are required. Check dates.";
        } else {
            try {
                // Insert leave request
                $stmt = $pdo->prepare("INSERT INTO `leave_requests` (`employee_id`, `leave_type`, `from_date`, `to_date`, `num_days`, `reason`, `status`) 
                    VALUES (:emp_id, :type, :from, :to, :days, :reason, 'Pending')");
                $stmt->execute([
                    'emp_id' => $userEmpId,
                    'type' => $type,
                    'from' => $from,
                    'to' => $to,
                    'days' => $days,
                    'reason' => $reason
                ]);

                // Create system notification
                $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`employee_id`, `title`, `message`, `type`, `is_read`) 
                    VALUES (:emp_id, 'Leave Request Submitted', :msg, 'leave', 0)");
                $stmtNotif->execute([
                    'emp_id' => $userEmpId,
                    'msg' => "Your request for {$days} days of {$type} (from {$from} to {$to}) was submitted successfully."
                ]);

                $_SESSION['success_msg'] = "Leave application submitted successfully!";
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Failed to submit leave: " . $e->getMessage();
            }
        }
        header("Location: dashboard.php?page=leave");
        exit;
    }

    // Action 4.2: Cancel Leave Request
    if (isset($_POST['action_cancel_leave'])) {
        $leaveId = (int)$_POST['leave_id'];

        try {
            $stmt = $pdo->prepare("UPDATE `leave_requests` SET `status` = 'Cancelled' WHERE `id` = :id AND `employee_id` = :emp_id AND `status` = 'Pending'");
            $stmt->execute(['id' => $leaveId, 'emp_id' => $userEmpId]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success_msg'] = "Leave request cancelled successfully.";
            } else {
                $_SESSION['error_msg'] = "Leave request could not be cancelled. It may have already been processed.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Cancellation error: " . $e->getMessage();
        }
        header("Location: dashboard.php?page=leave");
        exit;
    }

    // Action 4.3: Raise Hiring Requisition
    if (isset($_POST['action_raise_requisition'])) {
        $jobTitle = trim($_POST['job_title']);
        $numPositions = (int)$_POST['num_positions'];
        $empType = trim($_POST['employment_type']);
        $reason = trim($_POST['reason_for_hiring']);
        $reqQual = trim($_POST['required_qualification']);
        $prefQual = trim($_POST['preferred_qualification']);
        $reqSkills = trim($_POST['required_skills']);
        $prefSkills = trim($_POST['preferred_skills']);
        $minExp = (int)$_POST['min_experience'];
        $maxExp = (int)$_POST['max_experience'];
        $jobDesc = trim($_POST['job_description']);
        $responsibilities = trim($_POST['key_responsibilities']);
        $minSal = (float)$_POST['min_salary'];
        $maxSal = (float)$_POST['max_salary'];
        $salType = trim($_POST['salary_type']);
        $budget = (float)$_POST['salary_budget'];
        $benefits = trim($_POST['other_benefits']);
        $joiningDate = trim($_POST['joining_date']);
        $priority = trim($_POST['priority']);
        $priorityReason = trim($_POST['priority_reason']);
        $justification = trim($_POST['business_justification']);
        $comments = trim($_POST['comments']);
        
        $isDraft = isset($_POST['save_draft']) ? true : false;
        $status = $isDraft ? 'Draft' : 'Pending Department Approval';

        if (empty($jobTitle) || $numPositions <= 0 || empty($reqQual) || empty($reqSkills) || empty($jobDesc) || empty($justification)) {
            $_SESSION['error_msg'] = "Hiring Requisition failed: Core required fields cannot be left empty.";
            header("Location: dashboard.php?page=raise_requisition");
            exit;
        }

        // Generate requisition ID: REQ-YYYY-XXXX
        $reqId = 'REQ-' . date('Y') . '-' . rand(1000, 9999);

        try {
            $stmt = $pdo->prepare("INSERT INTO `hiring_requisitions` (
                `requisition_id`, `request_date`, `employee_id`, `department`, `designation`, `manager`, `location`, `hiring_type`, 
                `job_title`, `num_positions`, `employment_type`, `reason_for_hiring`, `required_qualification`, `preferred_qualification`, 
                `required_skills`, `preferred_skills`, `min_experience`, `max_experience`, `job_description`, `key_responsibilities`, 
                `min_salary`, `max_salary`, `salary_type`, `salary_budget`, `other_benefits`, `joining_date`, `priority`, `priority_reason`, 
                `business_justification`, `comments`, `status`
            ) VALUES (
                :req_id, CURDATE(), :emp_id, :dept, :desig, :mgr, :loc, :htype, :job, :positions, :etype, :reason, :req_qual, :pref_qual,
                :req_skills, :pref_skills, :minexp, :maxexp, :jd, :resp, :minsal, :maxsal, :saltype, :budget, :benefits, :jdate, :priority,
                :p_reason, :justification, :comments, :status
            )");

            $stmt->execute([
                'req_id' => $reqId,
                'emp_id' => $userEmpId,
                'dept' => $profile['department'],
                'desig' => $profile['designation'],
                'mgr' => $profile['manager'],
                'loc' => $profile['location'],
                'htype' => $reason,
                'job' => $jobTitle,
                'positions' => $numPositions,
                'etype' => $empType,
                'reason' => $reason,
                'req_qual' => $reqQual,
                'pref_qual' => $prefQual,
                'req_skills' => $reqSkills,
                'pref_skills' => $prefSkills,
                'minexp' => $minExp,
                'maxexp' => $maxExp,
                'jd' => $jobDesc,
                'resp' => $responsibilities,
                'minsal' => $minSal,
                'maxsal' => $maxSal,
                'saltype' => $salType,
                'budget' => $budget,
                'benefits' => $benefits,
                'jdate' => $joiningDate,
                'priority' => $priority,
                'p_reason' => $priorityReason,
                'justification' => $justification,
                'comments' => $comments,
                'status' => $status
            ]);

            // Notification
            $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`employee_id`, `title`, `message`, `type`, `is_read`) 
                VALUES (:emp_id, 'Hiring Requisition Created', :msg, 'requisition', 0)");
            $notifMsg = $isDraft ? "Hiring Requisition draft saved: {$reqId} ({$jobTitle})." : "Hiring Requisition raised: {$reqId} ({$jobTitle}). Approval workflow initiated.";
            $stmtNotif->execute([
                'emp_id' => $userEmpId,
                'msg' => $notifMsg
            ]);

            $_SESSION['success_msg'] = $isDraft ? "Requisition draft saved successfully!" : "Requisition raised and submitted successfully!";
            header("Location: dashboard.php?page=my_requisitions");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Requisition database insertion failure: " . $e->getMessage();
            header("Location: dashboard.php?page=raise_requisition");
            exit;
        }
    }

    // Action 4.4: Cancel/Delete Draft Requisition
    if (isset($_POST['action_cancel_requisition'])) {
        $reqId = trim($_POST['requisition_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM `hiring_requisitions` WHERE `requisition_id` = :req_id AND `employee_id` = :emp_id AND `status` = 'Draft'");
            $stmt->execute(['req_id' => $reqId, 'emp_id' => $userEmpId]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['success_msg'] = "Draft Requisition deleted.";
            } else {
                $_SESSION['error_msg'] = "Cannot delete requisition. Non-draft items must remain archived.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Delete error: " . $e->getMessage();
        }
        header("Location: dashboard.php?page=my_requisitions");
        exit;
    }

    // Action 4.5: Update Settings Profile details
    if (isset($_POST['action_update_profile'])) {
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $emerg = trim($_POST['emergency_contact']);

        try {
            if ($userRole === 'employee') {
                $stmt = $pdo->prepare("UPDATE `employees` SET `phone` = :phone, `address` = :address, `emergency_contact` = :emerg WHERE `id` = :id");
                $stmt->execute(['phone' => $phone, 'address' => $address, 'emerg' => $emerg, 'id' => $userId]);
                $_SESSION['success_msg'] = "Profile details updated successfully!";
            } else {
                $_SESSION['success_msg'] = "HR Admin profile changes saved successfully (Local Mock).";
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Failed to update profile: " . $e->getMessage();
        }
        header("Location: dashboard.php?page=settings");
        exit;
    }

    // Action 4.6: Update Settings Security (Password)
    if (isset($_POST['action_update_password'])) {
        $curr = $_POST['current_password'];
        $new = $_POST['new_password'];
        $conf = $_POST['confirm_password'];

        if (empty($curr) || empty($new) || empty($conf)) {
            $_SESSION['error_msg'] = "All password fields are required.";
        } else if ($new !== $conf) {
            $_SESSION['error_msg'] = "New passwords do not match.";
        } else {
            try {
                // Get password hash
                $table = ($userRole === 'employee') ? 'employees' : 'recruiters';
                $stmt = $pdo->prepare("SELECT `password` FROM `$table` WHERE `id` = :id LIMIT 1");
                $stmt->execute(['id' => $userId]);
                $hash = $stmt->fetchColumn();

                if ($hash && password_verify($curr, $hash)) {
                    $newHash = password_hash($new, PASSWORD_BCRYPT);
                    $stmtUpdate = $pdo->prepare("UPDATE `$table` SET `password` = :pass WHERE `id` = :id");
                    $stmtUpdate->execute(['pass' => $newHash, 'id' => $userId]);
                    $_SESSION['success_msg'] = "Security password changed successfully!";
                } else {
                    $_SESSION['error_msg'] = "Current password verification failed.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Password update database error: " . $e->getMessage();
            }
        }
        header("Location: dashboard.php?page=settings");
        exit;
    }

    // Action 4.7: HR Admin approval of leave
    if (isset($_POST['action_hr_leave_approval']) && $userRole === 'hr') {
        $reqId = (int)$_POST['leave_id'];
        $decision = (!empty($_POST['action_hr_leave_approval']) && $_POST['action_hr_leave_approval'] !== '1')
            ? trim($_POST['action_hr_leave_approval']) 
            : (trim($_POST['decision'] ?? ''));
        
        try {
            $stmt = $pdo->prepare("UPDATE `leave_requests` SET `status` = :status, `actioned_by` = :act_by, `actioned_at` = NOW() WHERE `id` = :id");
            $stmt->execute(['status' => $decision, 'act_by' => $userName, 'id' => $reqId]);

            // Notify employee
            $stmtDetails = $pdo->prepare("SELECT `employee_id`, `leave_type` FROM `leave_requests` WHERE `id` = :id LIMIT 1");
            $stmtDetails->execute(['id' => $reqId]);
            $leave = $stmtDetails->fetch();

            if ($leave) {
                $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`employee_id`, `title`, `message`, `type`, `is_read`) 
                    VALUES (:emp_id, :title, :msg, 'leave', 0)");
                $stmtNotif->execute([
                    'emp_id' => $leave['employee_id'],
                    'title' => "Leave Request " . $decision,
                    'msg' => "Your request for {$leave['leave_type']} has been {$decision} by HR Administrator {$userName}."
                ]);
            }

            $_SESSION['success_msg'] = "Leave request has been successfully " . strtolower($decision) . ".";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "DB Approval failure: " . $e->getMessage();
        }
        header("Location: dashboard.php?page=leave");
        exit;
    }

    // Action 4.8: HR Admin approval of hiring requisition
    if (isset($_POST['action_hr_req_approval']) && $userRole === 'hr') {
        $requisitionId = trim($_POST['requisition_id']);
        $decision = trim($_POST['decision'] ?? '');

        if (empty($decision)) {
            $_SESSION['error_msg'] = "Please select a valid requisition status transition.";
            header("Location: dashboard.php?page=my_requisitions");
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE `hiring_requisitions` SET `status` = :status, `comments` = :comments WHERE `requisition_id` = :req_id");
            $stmt->execute([
                'status' => $decision,
                'comments' => 'HR Update: ' . $decision,
                'req_id' => $requisitionId
            ]);

            // Notify employee
            $stmtDetails = $pdo->prepare("SELECT `employee_id`, `job_title` FROM `hiring_requisitions` WHERE `requisition_id` = :req_id LIMIT 1");
            $stmtDetails->execute(['req_id' => $requisitionId]);
            $req = $stmtDetails->fetch();

            if ($req) {
                $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`employee_id`, `title`, `message`, `type`, `is_read`) 
                    VALUES (:emp_id, 'Requisition Status Updated', :msg, 'requisition', 0)");
                $stmtNotif->execute([
                    'emp_id' => $req['employee_id'],
                    'msg' => "Hiring Requisition {$requisitionId} for '{$req['job_title']}' has been updated to: {$decision}."
                ]);
            }

            $_SESSION['success_msg'] = "Requisition status updated to '{$decision}' successfully.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Requisition update failure: " . $e->getMessage();
        }
        header("Location: dashboard.php?page=my_requisitions");
        exit;
    }
}

// 5. Parse current routing sub-view
$currentPage = isset($_GET['page']) ? trim($_GET['page']) : 'dashboard';
$validPages = ['dashboard', 'profile', 'leave', 'salary', 'holidays', 'raise_requisition', 'my_requisitions', 'attendance', 'documents', 'notifications', 'help', 'settings'];
if (!in_array($currentPage, $validPages)) {
    $currentPage = 'dashboard';
}

// 6. Get Context-wide Unread notifications count
try {
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `employee_id` = :emp_id AND `is_read` = 0");
    $stmtUnread->execute(['emp_id' => $userEmpId]);
    $unreadCount = (int)$stmtUnread->fetchColumn();
} catch (PDOException $e) {
    $unreadCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure organizational cockpit. Access profile details, log leave applications, print payroll histories, and manage requisitions.">
    <title>NexusLink Portal | Dashboard Hub</title>
    
    <!-- Fonts and global style stylesheets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body class="dashboard-body">

    <div class="app-container">
        
        <!-- SIDEBAR NAVIGATION MENU -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <div class="logo-icon" style="width:34px; height:34px; border-radius:6px; box-shadow:0 4px 10px rgba(99,102,241,0.25);">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/>
                        </svg>
                    </div>
                    <span class="logo-text" style="font-size:1.1rem;">NexusLink</span>
                </div>
                <button class="mobile-nav-toggle" style="display:none;" aria-label="Toggle Navigation">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <div class="sidebar-menu-container">
                <nav>
                    <ul class="sidebar-menu">
                        <li class="menu-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=dashboard">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                                Dashboard
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=profile">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v-2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                My Profile
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'leave' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=leave">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                Leave Management
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'salary' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=salary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                                Salary / Payslips
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'holidays' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=holidays">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="6" x2="12" y2="12"></line>
                                    <line x1="12" y1="12" x2="16" y2="14"></line>
                                </svg>
                                Holiday Calendar
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'raise_requisition' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=raise_requisition">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="17" y1="11" x2="23" y2="11"></line>
                                </svg>
                                Hiring Requisition
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'my_requisitions' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=my_requisitions">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                My Requisitions
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'attendance' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=attendance">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                Attendance
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'documents' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=documents">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                </svg>
                                Documents
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'notifications' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=notifications">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                </svg>
                                Notifications
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'help' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=help">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                </svg>
                                Help / Support
                            </a>
                        </li>
                        <li class="menu-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                            <a href="dashboard.php?page=settings">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                                Settings
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

            <!-- Sidebar profile block & quick actions -->
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <img src="<?php echo htmlspecialchars($profile['photo']); ?>" alt="User Avatar" class="user-avatar" onerror="this.src='images/login_illustration.jpg';">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($profile['name']); ?></div>
                        <div class="user-role"><?php echo $userRole === 'hr' ? 'HR / Recruiter' : 'Employee ID: ' . htmlspecialchars($profile['employee_id']); ?></div>
                    </div>
                </div>
                <a href="index.php?action=logout" style="display:block; text-align:center; font-size:0.8rem; color:var(--text-secondary); text-decoration:none; margin-top:12px; font-weight:600; transition:var(--transition-fast);" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='var(--text-secondary)'">
                    Logout Portal Session
                </a>
            </div>
        </aside>

        <!-- MAIN WRAPPER CONTAINER -->
        <main class="main-content">
            
            <!-- TOP NAVBAR PANEL -->
            <header class="top-header">
                <!-- Mobile Navigation Toggler Button -->
                <button class="mobile-nav-toggle" style="background:var(--bg-secondary); border:1px solid var(--border-color); width:40px; height:40px; border-radius:var(--radius-md); display:none; align-items:center; justify-content:center;" onclick="document.querySelector('.sidebar').classList.add('mobile-open')">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <div class="welcome-widget">
                    <h1>Welcome Back, <?php echo htmlspecialchars($profile['name']); ?></h1>
                    <p>Current Portal Role: <strong style="color:var(--primary-color)"><?php echo $userRole === 'hr' ? 'HR Administrator' : 'Department Member'; ?></strong></p>
                </div>

                <div class="header-actions">
                    <a href="dashboard.php?page=notifications" class="notification-bell" aria-label="View notifications">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <?php if ($unreadCount > 0): ?>
                            <span class="bell-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="index.php?action=logout" class="logout-header-btn">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                        </svg>
                        Log Out
                    </a>
                </div>
            </header>

            <!-- SUB-VIEW HUB ROUTER CONTAINER -->
            <div class="content-wrapper">

                <!-- 1. DASHBOARD HUB PAGE PANEL -->
                <?php if ($currentPage === 'dashboard'): 
                    // Dynamic Database Aggregations for summary cards
                    // Leaves calculations
                    try {
                        $stmtApprovedLeaves = $pdo->prepare("SELECT SUM(`num_days`) FROM `leave_requests` WHERE `employee_id` = :emp_id AND `status` = 'Approved'");
                        $stmtApprovedLeaves->execute(['emp_id' => $userEmpId]);
                        $leavesUsed = (int)$stmtApprovedLeaves->fetchColumn();
                        
                        $leaveEntitlement = 30;
                        $leaveBalance = $leaveEntitlement - $leavesUsed;

                        $stmtPendingLeaves = $pdo->prepare("SELECT COUNT(*) FROM `leave_requests` WHERE `employee_id` = :emp_id AND `status` = 'Pending'");
                        $stmtPendingLeaves->execute(['emp_id' => $userEmpId]);
                        $pendingLeaves = (int)$stmtPendingLeaves->fetchColumn();

                        $stmtApprCount = $pdo->prepare("SELECT COUNT(*) FROM `leave_requests` WHERE `employee_id` = :emp_id AND `status` = 'Approved'");
                        $stmtApprCount->execute(['emp_id' => $userEmpId]);
                        $approvedLeavesCount = (int)$stmtApprCount->fetchColumn();

                        // Requisition Aggregations
                        $stmtOpenReqs = $pdo->prepare("SELECT COUNT(*) FROM `hiring_requisitions` WHERE `status` = 'Recruitment In Progress'");
                        $stmtOpenReqs->execute();
                        $openRequisitions = (int)$stmtOpenReqs->fetchColumn();

                        $stmtPendingReqs = $pdo->prepare("SELECT COUNT(*) FROM `hiring_requisitions` WHERE `employee_id` = :emp_id AND `status` LIKE 'Pending%'");
                        $stmtPendingReqs->execute(['emp_id' => $userEmpId]);
                        $pendingRequisitions = (int)$stmtPendingReqs->fetchColumn();

                        // Next Upcoming Holiday
                        $stmtNextHoliday = $pdo->query("SELECT * FROM `holidays` WHERE `date` >= CURDATE() ORDER BY `date` ASC LIMIT 1");
                        $nextHoliday = $stmtNextHoliday->fetch();

                        // Recent Requisitions List
                        $stmtRecentReqs = $pdo->prepare("SELECT * FROM `hiring_requisitions` WHERE `employee_id` = :emp_id ORDER BY `created_at` DESC LIMIT 5");
                        $stmtRecentReqs->execute(['emp_id' => $userEmpId]);
                        $recentReqs = $stmtRecentReqs->fetchAll();

                    } catch (PDOException $e) {
                        $leavesUsed = 0; $leaveBalance = 30; $pendingLeaves = 0; $approvedLeavesCount = 0;
                        $openRequisitions = 0; $pendingRequisitions = 0; $nextHoliday = null; $recentReqs = [];
                    }
                ?>
                    <!-- METRIC CARDS ROW -->
                    <div class="summary-cards-grid">
                        <div class="summary-card">
                            <div class="summary-card-icon teal">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Leave Balance</span>
                                <span class="summary-card-val"><?php echo $leaveBalance; ?> Days</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon primary">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                                </svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Leave Days Used</span>
                                <span class="summary-card-val"><?php echo $leavesUsed; ?> Days</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon amber">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Pending Leaves</span>
                                <span class="summary-card-val"><?php echo $pendingLeaves; ?> Requests</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon green">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Approved Leaves</span>
                                <span class="summary-card-val"><?php echo $approvedLeavesCount; ?> Approved</span>
                            </div>
                        </div>
                    </div>

                    <div class="summary-cards-grid">
                        <div class="summary-card">
                            <div class="summary-card-icon green">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Open Requisitions</span>
                                <span class="summary-card-val"><?php echo $openRequisitions; ?> Positions</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon amber">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                </svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">My Pending Reqs</span>
                                <span class="summary-card-val"><?php echo $pendingRequisitions; ?> Pending</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon primary">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Upcoming Holiday</span>
                                <span class="summary-card-val" style="font-size:0.92rem; font-weight:700; margin-top:8px;">
                                    <?php echo $nextHoliday ? htmlspecialchars($nextHoliday['name']) . ' (' . htmlspecialchars($nextHoliday['date']) . ')' : 'None Scheduled'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon red">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                </svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Unread Notices</span>
                                <span class="summary-card-val"><?php echo $unreadCount; ?> New</span>
                            </div>
                        </div>
                    </div>

                    <!-- PROFILE SUMMARY WIDGET -->
                    <div class="section-panel">
                        <div class="section-header">
                            <h2 class="section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v-2"/><circle cx="12" cy="7" r="4"/></svg>
                                Employee Profile Overview
                            </h2>
                            <a href="dashboard.php?page=profile" class="action-btn-sm">View Profile</a>
                        </div>
                        <div class="profile-summary-box">
                            <img src="<?php echo htmlspecialchars($profile['photo']); ?>" alt="Profile avatar portrait" class="profile-avatar-lg" onerror="this.src='images/login_illustration.jpg';">
                            <div class="profile-meta-info">
                                <div class="profile-meta-name"><?php echo htmlspecialchars($profile['name']); ?></div>
                                <div class="profile-meta-title"><?php echo htmlspecialchars($profile['designation']); ?> &bull; <?php echo htmlspecialchars($profile['department']); ?></div>
                                <span class="profile-meta-badge"><?php echo htmlspecialchars($profile['employment_type']); ?></span>
                            </div>
                        </div>
                        <div class="profile-details-list">
                            <div class="profile-detail-row">
                                <span class="profile-detail-label">Reporting Manager:</span>
                                <span class="profile-detail-val"><?php echo htmlspecialchars($profile['manager']); ?></span>
                            </div>
                            <div class="profile-detail-row">
                                <span class="profile-detail-label">Office Work Location:</span>
                                <span class="profile-detail-val"><?php echo htmlspecialchars($profile['location']); ?></span>
                            </div>
                            <div class="profile-detail-row">
                                <span class="profile-detail-label">Direct Corporate Email:</span>
                                <span class="profile-detail-val"><?php echo htmlspecialchars($profile['email']); ?></span>
                            </div>
                            <div class="profile-detail-row">
                                <span class="profile-detail-label">Official Date of Joining:</span>
                                <span class="profile-detail-val"><?php echo htmlspecialchars($profile['joining_date']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- QUICK ACTIONS BAR -->
                    <h3 style="font-size:1.1rem; font-weight:700; margin-bottom:16px;">Quick Operations</h3>
                    <div class="quick-actions-bar">
                        <a href="dashboard.php?page=leave" class="quick-action-card">
                            <div class="quick-action-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                            <span class="quick-action-title">Apply Leave</span>
                        </a>
                        <a href="dashboard.php?page=raise_requisition" class="quick-action-card" style="border: 1.5px solid var(--primary-color); background-color:rgba(99, 102, 241, 0.05);">
                            <div class="quick-action-icon" style="background-color:var(--primary-color); color:#fff;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                            <span class="quick-action-title" style="color:var(--primary-color); font-weight:700;">+ Raise Requisition</span>
                        </a>
                        <a href="dashboard.php?page=salary" class="quick-action-card">
                            <div class="quick-action-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                            <span class="quick-action-title">View Payslip</span>
                        </a>
                        <a href="dashboard.php?page=holidays" class="quick-action-card">
                            <div class="quick-action-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="6" x2="12" y2="12"></line><line x1="12" y1="12" x2="16" y2="14"></line></svg></div>
                            <span class="quick-action-title">Holidays Calendar</span>
                        </a>
                        <a href="dashboard.php?page=profile" class="quick-action-card">
                            <div class="quick-action-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v-2"/><circle cx="12" cy="7" r="4"/></svg></div>
                            <span class="quick-action-title">Edit Profile</span>
                        </a>
                        <a href="dashboard.php?page=my_requisitions" class="quick-action-card">
                            <div class="quick-action-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg></div>
                            <span class="quick-action-title">My Requisitions</span>
                        </a>
                    </div>

                    <!-- COLUMNS SPLIT LAYOUT -->
                    <div class="dashboard-grid">
                        
                        <!-- Left side panel: Recent Requisitions -->
                        <div class="section-panel" style="margin-bottom:0;">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg>
                                    Recent Requisitions Raised
                                </h2>
                                <a href="dashboard.php?page=my_requisitions" class="action-btn-sm">See All</a>
                            </div>

                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Req ID</th>
                                            <th>Job Title</th>
                                            <th>Date Raised</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($recentReqs) > 0): ?>
                                            <?php foreach ($recentReqs as $r): ?>
                                                <tr>
                                                    <td style="font-family:monospace;"><?php echo htmlspecialchars($r['requisition_id']); ?></td>
                                                    <td style="font-weight:600;"><?php echo htmlspecialchars($r['job_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($r['request_date']); ?></td>
                                                    <td>
                                                        <span class="status-badge" style="background-color:rgba(255,255,255,0.03); color:var(--text-primary); border:1px solid var(--border-color);">
                                                            <?php echo htmlspecialchars($r['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo strtolower(explode(' ', $r['status'])[0]); ?>" data-status="<?php echo htmlspecialchars($r['status']); ?>">
                                                            <?php echo htmlspecialchars($r['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" style="text-align:center; color:var(--text-secondary);">No hiring requisitions raised yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Right side panel: Announcements and Alerts -->
                        <div class="section-panel" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div class="section-header" style="margin-bottom:16px;">
                                    <h2 class="section-title">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path></svg>
                                        Internal Announcements
                                    </h2>
                                </div>
                                
                                <div style="display:flex; flex-direction:column; gap:16px;">
                                    <div style="padding:14px; border-radius:var(--radius-sm); background-color:rgba(99, 102, 241, 0.04); border:1px solid rgba(99, 102, 241, 0.2);">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                            <strong style="font-size:0.85rem; color:var(--primary-color);">HR Announcement</strong>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">2026-08-16</span>
                                        </div>
                                        <p style="font-size:0.82rem; line-height:1.4; color:var(--text-primary);">
                                            NexusLink Foundation Day celebration scheduled for Aug 20, 2026. Official company holiday. Headquaters office closed.
                                        </p>
                                    </div>
                                    <div style="padding:14px; border-radius:var(--radius-sm); background-color:rgba(20, 184, 166, 0.04); border:1px solid rgba(20, 184, 166, 0.2);">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                            <strong style="font-size:0.85rem; color:var(--accent-teal);">Systems Vetting</strong>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">2026-08-10</span>
                                        </div>
                                        <p style="font-size:0.82rem; line-height:1.4; color:var(--text-primary);">
                                            Server database migration completed successfully. Employee portal and ATS requisition status dashboards fully operational.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                <!-- 2. MY PROFILE MODULE -->
                <?php elseif ($currentPage === 'profile'): 
                    // Retrieve documents for links
                    try {
                        $stmtDocs = $pdo->prepare("SELECT * FROM `employee_documents` WHERE `employee_id` = :emp_id");
                        $stmtDocs->execute(['emp_id' => $userEmpId]);
                        $docs = $stmtDocs->fetchAll();
                    } catch (PDOException $e) {
                        $docs = [];
                    }
                ?>
                    <div style="display:grid; grid-template-columns:1fr 2fr; gap:30px;">
                        
                        <!-- Left Column: Portrait & Actions -->
                        <div>
                            <div class="section-panel" style="text-align:center;">
                                <img src="<?php echo htmlspecialchars($profile['photo']); ?>" alt="Employee Headshot Portrait" style="width:140px; height:140px; border-radius:var(--radius-lg); object-fit:cover; margin:0 auto 20px auto; border:3px solid var(--border-color);">
                                <h2 style="font-size:1.3rem; font-weight:800;"><?php echo htmlspecialchars($profile['name']); ?></h2>
                                <p style="color:var(--text-secondary); font-size:0.9rem; margin-top:4px;"><?php echo htmlspecialchars($profile['designation']); ?></p>
                                <p style="font-size:0.8rem; font-family:monospace; color:var(--primary-color); margin-top:6px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                                    ID: <?php echo htmlspecialchars($profile['employee_id']); ?>
                                </p>
                                
                                <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--border-color); display:flex; flex-direction:column; gap:10px;">
                                    <a href="dashboard.php?page=settings" class="action-btn-sm" style="justify-content:center; width:100%; padding:10px;">Edit Profile Fields</a>
                                    <a href="dashboard.php?page=settings" class="action-btn-sm action-btn-danger" style="justify-content:center; width:100%; padding:10px;">Change Password</a>
                                </div>
                            </div>

                            <!-- HR Associated Documents -->
                            <div class="section-panel">
                                <h3 style="font-size:1rem; font-weight:700; margin-bottom:16px;">Core Folders & Documents</h3>
                                <div style="display:flex; flex-direction:column; gap:12px;">
                                    <?php if (count($docs) > 0): ?>
                                        <?php foreach ($docs as $d): ?>
                                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem; padding-bottom:8px; border-bottom:1px dashed var(--border-color);">
                                                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px;" title="<?php echo htmlspecialchars($d['document_name']); ?>">
                                                    <strong><?php echo htmlspecialchars($d['document_type']); ?></strong>
                                                </div>
                                                <a href="download_doc.php?id=<?php echo $d['id']; ?>" class="action-btn-sm">
                                                    Download
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="color:var(--text-secondary); font-size:0.82rem;">No documents attached to employee record.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Personal & Employment details -->
                        <div style="display:flex; flex-direction:column; gap:30px;">
                            
                            <div class="section-panel" style="margin-bottom:0;">
                                <h3 style="font-size:1.1rem; font-weight:700; color:var(--primary-color); margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">Personal Information</h3>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:0.9rem;">
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Date of Birth</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['dob']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Gender</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['gender']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Direct Phone Number</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['phone']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Corporate Email</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['email']); ?></div>
                                    </div>
                                    <div style="grid-column:span 2;">
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Residential Address</div>
                                        <div style="font-weight:600; line-height:1.4;"><?php echo htmlspecialchars($profile['address'] ?? 'Address not updated.'); ?></div>
                                    </div>
                                    <div style="grid-column:span 2;">
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Emergency Contact Details</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['emergency_contact'] ?? 'No contact added.'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="section-panel" style="margin-bottom:0;">
                                <h3 style="font-size:1.1rem; font-weight:700; color:var(--accent-teal); margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">Employment Information</h3>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:0.9rem;">
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Department</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['department']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Designation</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['designation']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Reporting Manager</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['manager']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Employment Status</div>
                                        <div style="font-weight:600; color:var(--accent-green);"><?php echo htmlspecialchars($profile['status']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Date of Joining</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['joining_date']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Employment Type</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['employment_type']); ?></div>
                                    </div>
                                    <div style="grid-column:span 2;">
                                        <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:4px;">Office Location</div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($profile['location']); ?></div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                <!-- 3. LEAVE MANAGEMENT MODULE -->
                <?php elseif ($currentPage === 'leave'): 
                    // Leaves queries
                    try {
                        $stmtApprovedLeaves = $pdo->prepare("SELECT SUM(`num_days`) FROM `leave_requests` WHERE `employee_id` = :emp_id AND `status` = 'Approved'");
                        $stmtApprovedLeaves->execute(['emp_id' => $userEmpId]);
                        $leavesUsed = (int)$stmtApprovedLeaves->fetchColumn();
                        
                        $leaveEntitlement = 30;
                        $leaveBalance = $leaveEntitlement - $leavesUsed;

                        $stmtPendingLeaves = $pdo->prepare("SELECT SUM(`num_days`) FROM `leave_requests` WHERE `employee_id` = :emp_id AND `status` = 'Pending'");
                        $stmtPendingLeaves->execute(['emp_id' => $userEmpId]);
                        $pendingDays = (int)$stmtPendingLeaves->fetchColumn();

                        // Leaves History List
                        $stmtLeavesHistory = $pdo->prepare("SELECT * FROM `leave_requests` WHERE `employee_id` = :emp_id ORDER BY `created_at` DESC");
                        $stmtLeavesHistory->execute(['emp_id' => $userEmpId]);
                        $leavesList = $stmtLeavesHistory->fetchAll();

                        // For HR Admin role: Retrieve ALL leave requests
                        if ($userRole === 'hr') {
                            $stmtAllLeaves = $pdo->query("SELECT l.*, e.name as employee_name FROM `leave_requests` l JOIN `employees` e ON l.employee_id = e.employee_id ORDER BY l.status = 'Pending' DESC, l.created_at DESC");
                            $adminLeavesList = $stmtAllLeaves->fetchAll();
                        }
                    } catch (PDOException $e) {
                        $leavesUsed = 0; $leaveBalance = 30; $pendingDays = 0; $leavesList = []; $adminLeavesList = [];
                    }
                ?>
                    <div class="summary-cards-grid">
                        <div class="summary-card">
                            <div class="summary-card-icon teal">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Total Entitlement</span>
                                <span class="summary-card-val"><?php echo $leaveEntitlement; ?> Days</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon green">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Available Balance</span>
                                <span class="summary-card-val"><?php echo $leaveBalance; ?> Days</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon primary">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Used Leave</span>
                                <span class="summary-card-val"><?php echo $leavesUsed; ?> Days</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon amber">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line></svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Pending Approval</span>
                                <span class="summary-card-val"><?php echo $pendingDays; ?> Days</span>
                            </div>
                        </div>
                    </div>

                    <?php if ($userRole === 'hr'): ?>
                        <!-- HR ADMIN LEAVES REVIEW SCREEN -->
                        <div class="section-panel">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                    Leave Management Review Desk (HR Admin Access)
                                </h2>
                            </div>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Duration</th>
                                            <th>Days</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Operations</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($adminLeavesList) > 0): ?>
                                            <?php foreach ($adminLeavesList as $l): ?>
                                                <tr>
                                                    <td>
                                                        <div style="font-weight:600;"><?php echo htmlspecialchars($l['employee_name']); ?></div>
                                                        <span style="font-size:0.75rem; color:var(--text-secondary);"><?php echo htmlspecialchars($l['employee_id']); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($l['leave_type']); ?></td>
                                                    <td>
                                                        <div style="font-size:0.85rem;"><?php echo htmlspecialchars($l['from_date']); ?> to <?php echo htmlspecialchars($l['to_date']); ?></div>
                                                    </td>
                                                    <td style="font-weight:600; text-align:center;"><?php echo htmlspecialchars($l['num_days']); ?></td>
                                                    <td style="font-size:0.8rem; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($l['reason']); ?>">
                                                        <?php echo htmlspecialchars($l['reason']); ?>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo strtolower($l['status']); ?>">
                                                            <?php echo htmlspecialchars($l['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($l['status'] === 'Pending'): ?>
                                                            <form action="dashboard.php?page=leave" method="POST" style="display:inline-flex; gap:6px;">
                                                                <input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>">
                                                                <button type="submit" name="action_hr_leave_approval" value="Approved" class="action-btn-sm" style="background-color:rgba(16, 185, 129, 0.15); color:var(--accent-green); border-color:rgba(16,185,129,0.3);">
                                                                    Approve
                                                                </button>
                                                                <button type="submit" name="action_hr_leave_approval" value="Rejected" class="action-btn-sm action-btn-danger" style="padding:6px 10px;">
                                                                    Reject
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span style="font-size:0.8rem; color:var(--text-secondary); font-style:italic;">
                                                                Processed by: <?php echo htmlspecialchars($l['actioned_by'] ?? 'System'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" style="text-align:center; color:var(--text-secondary);">No team leaves requests submitted.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns:1fr 1.8fr; gap:30px;">
                        <!-- Apply Leave Form -->
                        <div class="section-panel" style="margin-bottom:0; height:fit-content;">
                            <h3 style="font-size:1.05rem; font-weight:700; color:var(--primary-color); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                Apply for Leave
                            </h3>
                            <form action="dashboard.php?page=leave" method="POST" id="leaveForm" novalidate>
                                <div style="display:flex; flex-direction:column; gap:16px;">
                                    <div>
                                        <label for="leave_type" class="form-label">Leave Category</label>
                                        <select id="leave_type" name="leave_type" class="form-control-select" required>
                                            <option value="">-- Choose Category --</option>
                                            <option value="Casual Leave">Casual Leave (Personal Reasons)</option>
                                            <option value="Sick Leave">Sick Leave (Medical/Health)</option>
                                            <option value="Maternity Leave">Maternity Leave</option>
                                            <option value="Paternity Leave">Paternity Leave</option>
                                            <option value="Unpaid Leave">Unpaid / Loss of Pay (LOP)</option>
                                        </select>
                                    </div>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                        <div>
                                            <label for="from_date" class="form-label">From Date</label>
                                            <input type="date" id="from_date" name="from_date" class="form-control-input" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div>
                                            <label for="to_date" class="form-label">To Date</label>
                                            <input type="date" id="to_date" name="to_date" class="form-control-input" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="num_days" class="form-label">Duration (Days)</label>
                                        <input type="text" id="num_days" name="num_days" class="form-control-input" readonly placeholder="Auto-calculated" style="background-color:rgba(0,0,0,0.15); cursor:not-allowed; font-weight:600;">
                                    </div>

                                    <div>
                                        <label for="reason" class="form-label">Reason for Request</label>
                                        <textarea id="reason" name="reason" class="form-control-textarea" required placeholder="Describe the reason for applying..."></textarea>
                                    </div>

                                    <div class="form-actions-row" style="margin-top:10px; padding-top:16px;">
                                        <button type="submit" name="action_apply_leave" class="submit-btn" style="width:100%; justify-content:center; padding:12px;">
                                            Submit Leave Request
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Previous Leave Applications -->
                        <div class="section-panel" style="margin-bottom:0;">
                            <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                Personal Leave History
                            </h3>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Leave Type</th>
                                            <th>Duration</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($leavesList) > 0): ?>
                                            <?php foreach ($leavesList as $l): ?>
                                                <tr>
                                                    <td style="font-weight:600;"><?php echo htmlspecialchars($l['leave_type']); ?></td>
                                                    <td>
                                                        <div style="font-size:0.85rem;"><?php echo htmlspecialchars($l['from_date']); ?> to <?php echo htmlspecialchars($l['to_date']); ?></div>
                                                    </td>
                                                    <td style="text-align:center; font-weight:600;"><?php echo htmlspecialchars($l['num_days']); ?></td>
                                                    <td>
                                                        <span class="status-badge <?php echo strtolower($l['status']); ?>">
                                                            <?php echo htmlspecialchars($l['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($l['status'] === 'Pending'): ?>
                                                            <form action="dashboard.php?page=leave" method="POST" onsubmit="return confirm('Are you sure you want to cancel this leave request?');">
                                                                <input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>">
                                                                <button type="submit" name="action_cancel_leave" class="action-btn-sm action-btn-danger">
                                                                    Cancel
                                                                </button>
                                                            </form>
                                                        <?php elseif ($l['status'] === 'Approved'): ?>
                                                            <span style="font-size:0.75rem; color:var(--accent-green);">
                                                                Approved by: <?php echo htmlspecialchars($l['actioned_by'] ?? 'HR Admin'); ?>
                                                            </span>
                                                        <?php elseif ($l['status'] === 'Rejected'): ?>
                                                            <span style="font-size:0.75rem; color:var(--accent-red);">
                                                                Rejected by: <?php echo htmlspecialchars($l['actioned_by'] ?? 'HR Admin'); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="font-size:0.75rem; color:var(--text-muted); font-style:italic;"><?php echo htmlspecialchars($l['status'] ?? 'Processed'); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" style="text-align:center; color:var(--text-secondary);">No previous leave applications.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <!-- 4. SALARY / PAYSLIPS MODULE -->
                <?php elseif ($currentPage === 'salary'): 
                    // Retrieve payslips list
                    try {
                        $stmtPayslips = $pdo->prepare("SELECT * FROM `payslips` WHERE `employee_id` = :emp_id ORDER BY `payment_date` DESC");
                        $stmtPayslips->execute(['emp_id' => $userEmpId]);
                        $payslips = $stmtPayslips->fetchAll();
                        
                        // Pick latest payslip details if exists
                        $latestPayslip = count($payslips) > 0 ? $payslips[0] : null;
                    } catch (PDOException $e) {
                        $payslips = []; $latestPayslip = null;
                    }
                ?>
                    <div style="display:grid; grid-template-columns:1fr 1.6fr; gap:30px;">
                        
                        <!-- Left Side: Breakdown Summary card -->
                        <div>
                            <div class="section-panel">
                                <h3 style="font-size:1.05rem; font-weight:700; color:var(--primary-color); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                    Monthly Payroll Summary
                                </h3>
                                
                                <?php if ($latestPayslip): 
                                    $basic = (float)$latestPayslip['basic_salary'];
                                    $allow = (float)$latestPayslip['allowances'];
                                    $deduct = (float)$latestPayslip['deductions'];
                                    $gross = (float)$latestPayslip['gross_salary'];
                                    $net = (float)$latestPayslip['net_salary'];
                                    $month = $latestPayslip['month'];
                                ?>
                                    <div style="display:flex; flex-direction:column; gap:14px; font-size:0.9rem;">
                                        <div style="text-align:center; padding:15px; border-radius:var(--radius-sm); background-color:rgba(99,102,241,0.04); border:1px solid rgba(99,102,241,0.15); margin-bottom:10px;">
                                            <span style="font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Net Disbursed Pay (<?php echo htmlspecialchars($month); ?>)</span>
                                            <h2 style="font-size:2rem; font-weight:800; color:var(--accent-green); margin-top:6px;">$<?php echo number_format($net, 2); ?></h2>
                                            <span class="status-badge approved" style="margin-top:8px;">PAID</span>
                                        </div>
                                        
                                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:8px;">
                                            <span style="color:var(--text-secondary);">Employee Corporate ID:</span>
                                            <span style="font-weight:600; font-family:monospace;"><?php echo htmlspecialchars($profile['employee_id']); ?></span>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:8px;">
                                            <span style="color:var(--text-secondary);">Basic Salary:</span>
                                            <span style="font-weight:600;">$<?php echo number_format($basic, 2); ?></span>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:8px;">
                                            <span style="color:var(--text-secondary);">House & Special Allowances:</span>
                                            <span style="font-weight:600; color:var(--primary-color);">+$<?php echo number_format($allow, 2); ?></span>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:8px;">
                                            <span style="color:var(--text-secondary);">PF & Insurance Deductions:</span>
                                            <span style="font-weight:600; color:var(--accent-red);">-$<?php echo number_format($deduct, 2); ?></span>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:8px;">
                                            <span style="color:var(--text-secondary);">Gross Salary:</span>
                                            <span style="font-weight:600;">$<?php echo number_format($gross, 2); ?></span>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; padding-top:6px;">
                                            <span style="color:var(--text-secondary);">Payment Date:</span>
                                            <span style="font-weight:600;"><?php echo htmlspecialchars($latestPayslip['payment_date']); ?></span>
                                        </div>
                                    </div>
                                    <div style="margin-top:20px; display:flex; gap:10px;">
                                        <a href="download_payslip.php?id=<?php echo $latestPayslip['id']; ?>" class="action-btn-sm" style="width:100%; justify-content:center; padding:10px;">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            View Full Payslip Invoice
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <p style="color:var(--text-secondary); text-align:center;">No payslip details available.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Right Side: History Payslips Table -->
                        <div class="section-panel" style="margin-bottom:0;">
                            <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                Payslip Disbursement History
                            </h3>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Month Period</th>
                                            <th>Gross Salary</th>
                                            <th>Deductions</th>
                                            <th>Net Pay</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($payslips) > 0): ?>
                                            <?php foreach ($payslips as $p): ?>
                                                <tr>
                                                    <td style="font-weight:600;"><?php echo htmlspecialchars($p['month']); ?></td>
                                                    <td>$<?php echo number_format($p['gross_salary'], 2); ?></td>
                                                    <td>$<?php echo number_format($p['deductions'], 2); ?></td>
                                                    <td style="font-weight:700; color:var(--accent-green);">$<?php echo number_format($p['net_salary'], 2); ?></td>
                                                    <td>
                                                        <span class="status-badge approved">Paid</span>
                                                    </td>
                                                    <td>
                                                        <a href="download_payslip.php?id=<?php echo $p['id']; ?>" class="action-btn-sm">
                                                            View / Print
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" style="text-align:center; color:var(--text-secondary);">No payroll distributions recorded.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                <!-- 5. HOLIDAY CALENDAR MODULE -->
                <?php elseif ($currentPage === 'holidays'): 
                    // Retrieve holidays list
                    try {
                        $stmtHolidays = $pdo->query("SELECT * FROM `holidays` ORDER BY `date` ASC");
                        $holidays = $stmtHolidays->fetchAll();
                    } catch (PDOException $e) {
                        $holidays = [];
                    }
                ?>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px;">
                        
                        <!-- Visual Monthly Grid (August 2026 Focus) -->
                        <div class="calendar-widget">
                            <div class="calendar-header">
                                <h3 class="calendar-month-title">August 2026 Calendar</h3>
                                <span style="font-size:0.85rem; color:var(--text-secondary); font-weight:600;">Office Schedule</span>
                            </div>
                            
                            <div class="calendar-weekdays">
                                <div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div><div>S</div>
                            </div>
                            
                            <div class="calendar-grid">
                                <?php 
                                // August 2026 starts on Saturday (5 empty cells)
                                for ($i = 0; $i < 5; $i++) {
                                    echo '<div class="calendar-day-cell empty"></div>';
                                }

                                // Days in August: 31
                                for ($day = 1; $day <= 31; $day++) {
                                    $currentDateStr = sprintf("2026-08-%02d", $day);
                                    
                                    $cellClass = '';
                                    $badge = '';

                                    // Special Holiday Highlight
                                    if ($day === 20) {
                                        $cellClass = 'company-holiday';
                                        $badge = '<span class="day-badge-label">Foundation</span>';
                                    }

                                    // Weekend check
                                    $dayOfWeek = date('N', strtotime($currentDateStr));
                                    if ($dayOfWeek >= 6 && $day !== 20) {
                                        $cellClass = 'attendance-absent'; // Gray out weekend visual
                                    }

                                    echo '<div class="calendar-day-cell ' . $cellClass . '">';
                                    echo '<span class="day-number">' . $day . '</span>';
                                    echo $badge;
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Holidays List Table -->
                        <div class="section-panel" style="margin-bottom:0;">
                            <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                NexusLink Calendar Holidays (2026)
                            </h3>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Holiday Event</th>
                                            <th>Calendar Date</th>
                                            <th>Weekday</th>
                                            <th>Category</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($holidays) > 0): ?>
                                            <?php foreach ($holidays as $h): 
                                                // Highlight upcoming holidays
                                                $isUpcoming = (strtotime($h['date']) >= strtotime('2026-08-17'));
                                                $rowStyle = $isUpcoming ? 'style="background-color:rgba(99, 102, 241, 0.03); font-weight:600;"' : '';
                                            ?>
                                                <tr <?php echo $rowStyle; ?>>
                                                    <td>
                                                        <div style="display:flex; align-items:center; gap:8px;">
                                                            <?php if ($isUpcoming): ?>
                                                                <span class="status-dot" style="background-color:var(--accent-teal); box-shadow:0 0 6px var(--accent-teal);"></span>
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($h['name']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($h['date']); ?></td>
                                                    <td><?php echo htmlspecialchars($h['day']); ?></td>
                                                    <td>
                                                        <span class="status-badge" style="font-size:0.75rem; background-color:rgba(255,255,255,0.03); border:1px solid var(--border-color); color:var(--text-primary)">
                                                            <?php echo htmlspecialchars($h['type']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" style="text-align:center; color:var(--text-secondary);">No holidays loaded.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                <!-- 6. HIRING REQUISITION FORM (RAISE) -->
                <?php elseif ($currentPage === 'raise_requisition'): ?>
                    <div class="section-panel" style="max-width:960px; margin:0 auto;">
                        <div class="section-header">
                            <h2 class="section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
                                Raise New Hiring Requisition
                            </h2>
                            <span style="font-size:0.82rem; color:var(--text-secondary);">Requisition ID: <strong>REQ-<?php echo date('Y'); ?>-XXXX (Auto Generated)</strong></span>
                        </div>

                        <form action="dashboard.php?page=raise_requisition" method="POST" id="requisitionForm" novalidate onsubmit="return confirm('Do you want to submit this hiring request details to the approval chain?');">
                            
                            <!-- 6.1 Basic Request Info -->
                            <h3 style="font-size:0.95rem; font-weight:700; color:var(--primary-color); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px; margin-top:10px;">
                                I. Basic Requisition Information
                            </h3>
                            <div class="form-grid">
                                <div>
                                    <label class="form-label">Requesting Employee Name</label>
                                    <input type="text" class="form-control-input" readonly value="<?php echo htmlspecialchars($profile['name']); ?>" style="background-color:rgba(0,0,0,0.15); cursor:not-allowed; font-weight:600;">
                                </div>
                                <div>
                                    <label class="form-label">Requesting Corporate ID</label>
                                    <input type="text" class="form-control-input" readonly value="<?php echo htmlspecialchars($profile['employee_id']); ?>" style="background-color:rgba(0,0,0,0.15); cursor:not-allowed; font-family:monospace;">
                                </div>
                                <div>
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control-input" readonly value="<?php echo htmlspecialchars($profile['department']); ?>" style="background-color:rgba(0,0,0,0.15); cursor:not-allowed;">
                                </div>
                                <div>
                                    <label class="form-label">Hiring Office Location</label>
                                    <input type="text" class="form-control-input" readonly value="<?php echo htmlspecialchars($profile['location']); ?>" style="background-color:rgba(0,0,0,0.15); cursor:not-allowed;">
                                </div>
                            </div>

                            <!-- 6.2 Position Details -->
                            <h3 style="font-size:0.95rem; font-weight:700; color:var(--primary-color); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px; margin-top:24px;">
                                II. Target Position Details
                            </h3>
                            <div class="form-grid">
                                <div>
                                    <label for="job_title" class="form-label">Job Title / Vacancy Name *</label>
                                    <input type="text" id="job_title" name="job_title" class="form-control-input" required placeholder="e.g. Senior Backend Engineer (Node.js)">
                                </div>
                                <div>
                                    <label for="num_positions" class="form-label">Number of Positions Required *</label>
                                    <input type="number" id="num_positions" name="num_positions" class="form-control-input" required min="1" value="1">
                                </div>
                                <div>
                                    <label for="employment_type" class="form-label">Employment Classification Type</label>
                                    <select id="employment_type" name="employment_type" class="form-control-select">
                                        <option value="Full Time">Full Time</option>
                                        <option value="Part Time">Part Time</option>
                                        <option value="Contract">Contract</option>
                                        <option value="Internship">Internship</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="reason_for_hiring" class="form-label">Reason for Hiring / Justification</label>
                                    <select id="reason_for_hiring" name="reason_for_hiring" class="form-control-select">
                                        <option value="New Position">New Position (Department Expansion)</option>
                                        <option value="Replacement">Replacement (Backfill departed employee)</option>
                                        <option value="Project Requirement">Project Requirement (Temporary Scope)</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <!-- 6.3 Candidate Requirements -->
                            <h3 style="font-size:0.95rem; font-weight:700; color:var(--primary-color); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px; margin-top:24px;">
                                III. Candidate Academic & Technical Requirements
                            </h3>
                            <div class="form-grid">
                                <div>
                                    <label for="required_qualification" class="form-label">Minimum Required Qualification *</label>
                                    <input type="text" id="required_qualification" name="required_qualification" class="form-control-input" required placeholder="e.g. Bachelor's in CS / IT">
                                </div>
                                <div>
                                    <label for="preferred_qualification" class="form-label">Preferred Qualification</label>
                                    <input type="text" id="preferred_qualification" name="preferred_qualification" class="form-control-input" placeholder="e.g. Master's in Software Engineering">
                                </div>
                                <div class="form-group-full">
                                    <label for="required_skills" class="form-label">Required Skills Set (Comma-separated) *</label>
                                    <input type="text" id="required_skills" name="required_skills" class="form-control-input" required placeholder="e.g. PHP, MySQL, Javascript, Git">
                                </div>
                                <div class="form-group-full">
                                    <label for="preferred_skills" class="form-label">Preferred / Desired Skills Set (Comma-separated)</label>
                                    <input type="text" id="preferred_skills" name="preferred_skills" class="form-control-input" placeholder="e.g. TailwindCSS, Docker, CI/CD, AWS">
                                </div>
                                <div>
                                    <label for="min_experience" class="form-label">Minimum Experience Required (Years)</label>
                                    <input type="number" id="min_experience" name="min_experience" class="form-control-input" min="0" value="3">
                                </div>
                                <div>
                                    <label for="max_experience" class="form-label">Maximum Experience Limit (Years)</label>
                                    <input type="number" id="max_experience" name="max_experience" class="form-control-input" min="0" value="6">
                                </div>
                                <div class="form-group-full">
                                    <label for="job_description" class="form-label">Brief Job Summary Description *</label>
                                    <textarea id="job_description" name="job_description" class="form-control-textarea" required placeholder="Provide a concise description of the candidate's core duties..."></textarea>
                                </div>
                                <div class="form-group-full">
                                    <label for="key_responsibilities" class="form-label">Key Work Responsibilities (New line separated)</label>
                                    <textarea id="key_responsibilities" name="key_responsibilities" class="form-control-textarea" placeholder="- Manage codebases and repositories&#10;- Conduct unit tests&#10;- Attend daily Scrum standups"></textarea>
                                </div>
                            </div>

                            <!-- 6.4 Salary Details -->
                            <h3 style="font-size:0.95rem; font-weight:700; color:var(--primary-color); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px; margin-top:24px;">
                                IV. Budget & Compensation Details
                            </h3>
                            <div class="form-grid">
                                <div>
                                    <label for="min_salary" class="form-label">Expected Minimum Salary ($)</label>
                                    <input type="number" id="min_salary" name="min_salary" class="form-control-input" min="0" value="4000">
                                </div>
                                <div>
                                    <label for="max_salary" class="form-label">Expected Maximum Salary ($)</label>
                                    <input type="number" id="max_salary" name="max_salary" class="form-control-input" min="0" value="6000">
                                </div>
                                <div>
                                    <label for="salary_type" class="form-label">Salary Denomination Type</label>
                                    <select id="salary_type" name="salary_type" class="form-control-select">
                                        <option value="Monthly">Monthly</option>
                                        <option value="Annual">Annual</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="salary_budget" class="form-label">Total Allocated Position Budget ($)</label>
                                    <input type="number" id="salary_budget" name="salary_budget" class="form-control-input" min="0" value="6000">
                                </div>
                                <div class="form-group-full">
                                    <label for="other_benefits" class="form-label">Other Benefits / Perks / Allowances</label>
                                    <input type="text" id="other_benefits" name="other_benefits" class="form-control-input" placeholder="e.g. Dental care, flexible remote scheduling, gym membership">
                                </div>
                            </div>

                            <!-- 6.5 Hiring Timeline & Priority -->
                            <h3 style="font-size:0.95rem; font-weight:700; color:var(--primary-color); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px; margin-top:24px;">
                                V. Timeline & Justification
                            </h3>
                            <div class="form-grid">
                                <div>
                                    <label for="joining_date" class="form-label">Expected Joining Date *</label>
                                    <input type="date" id="joining_date" name="joining_date" class="form-control-input" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <label for="priority" class="form-label">Hiring Priority Level</label>
                                    <select id="priority" name="priority" class="form-control-select">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="form-group-full">
                                    <label for="priority_reason" class="form-label">Reason for Priority Choice (If High / Urgent)</label>
                                    <input type="text" id="priority_reason" name="priority_reason" class="form-control-input" placeholder="Explain why this position hiring is critical...">
                                </div>
                                <div class="form-group-full">
                                    <label for="business_justification" class="form-label">Business Justification / Department Return On Investment *</label>
                                    <textarea id="business_justification" name="business_justification" class="form-control-textarea" required placeholder="Why does the department require this hire immediately?"></textarea>
                                </div>
                                <div class="form-group-full">
                                    <label for="comments" class="form-label">Additional Comments / Vetting Notes</label>
                                    <input type="text" id="comments" name="comments" class="form-control-input" placeholder="Any extra operational context...">
                                </div>
                            </div>

                            <div class="form-actions-row">
                                <button type="submit" name="save_draft" class="action-btn-sm" style="padding:12px 24px; font-size:0.95rem;">
                                    Save as Draft
                                </button>
                                <button type="submit" name="action_raise_requisition" class="submit-btn" style="padding:12px 30px; font-size:0.95rem;">
                                    Submit Requisition Request
                                </button>
                            </div>

                        </form>
                    </div>

                <!-- 7. MY REQUISITIONS LIST MODULE -->
                <?php elseif ($currentPage === 'my_requisitions'): 
                    // Retrieve requisitions raised by user
                    try {
                        if ($userRole === 'hr') {
                            // HR Admin can view ALL requisitions raised in the company
                            $stmtReqs = $pdo->query("SELECT r.*, COALESCE(e.name, r.employee_id) as requesting_employee FROM `hiring_requisitions` r LEFT JOIN `employees` e ON r.employee_id = e.employee_id ORDER BY r.created_at DESC");
                            $reqsList = $stmtReqs->fetchAll();
                        } else {
                            $stmtReqs = $pdo->prepare("SELECT * FROM `hiring_requisitions` WHERE `employee_id` = :emp_id ORDER BY `created_at` DESC");
                            $stmtReqs->execute(['emp_id' => $userEmpId]);
                            $reqsList = $stmtReqs->fetchAll();
                        }
                    } catch (PDOException $e) {
                        $reqsList = [];
                    }
                ?>
                    <div class="section-panel" style="margin-bottom:0;">
                        <div class="section-header">
                            <h2 class="section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg>
                                <?php echo $userRole === 'hr' ? 'All Hiring Requisitions (HR Admin Vetting Desk)' : 'Hiring Requisitions Raised'; ?>
                            </h2>
                            <a href="dashboard.php?page=raise_requisition" class="action-btn-sm" style="background-color:var(--primary-color); border-color:var(--primary-color); color:#fff;">+ Raise Requisition</a>
                        </div>

                        <!-- SEARCH AND FILTERS BAR -->
                        <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:16px; margin-bottom:20px; padding:16px; border-radius:var(--radius-sm); background-color:rgba(0,0,0,0.15); border:1px solid var(--border-color);">
                            <div>
                                <label for="reqSearchInput" class="form-label">Search Keywords</label>
                                <input type="text" id="reqSearchInput" class="form-control-input" placeholder="Search Req ID, Job Title or Dept...">
                            </div>
                            <div>
                                <label for="reqStatusFilter" class="form-label">Filter by Status</label>
                                <select id="reqStatusFilter" class="form-control-select">
                                    <option value="">-- All Statuses --</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Submitted">Submitted</option>
                                    <option value="Pending Department Approval">Pending Department Approval</option>
                                    <option value="Pending HR Approval">Pending HR Approval</option>
                                    <option value="Pending Management Approval">Pending Management Approval</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="On Hold">On Hold</option>
                                    <option value="Recruitment In Progress">Recruitment In Progress</option>
                                    <option value="Closed">Closed</option>
                                </select>
                            </div>
                            <div>
                                <label for="reqPriorityFilter" class="form-label">Filter by Priority</label>
                                <select id="reqPriorityFilter" class="form-control-select">
                                    <option value="">-- All Priorities --</option>
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <!-- DATA TABLE -->
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Req ID</th>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Positions</th>
                                        <th>Salary Limit</th>
                                        <th>Priority</th>
                                        <th>Approval Stage</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="reqTableBody">
                                    <?php if (count($reqsList) > 0): ?>
                                        <?php foreach ($reqsList as $r): ?>
                                            <tr>
                                                <td style="font-family:monospace;"><?php echo htmlspecialchars($r['requisition_id']); ?></td>
                                                <td>
                                                    <div style="font-weight:600;"><?php echo htmlspecialchars($r['job_title']); ?></div>
                                                    <?php if ($userRole === 'hr'): ?>
                                                        <span style="font-size:0.75rem; color:var(--text-secondary);">Raised by: <?php echo htmlspecialchars($r['requesting_employee'] ?? $r['employee_id']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['department']); ?></td>
                                                <td style="text-align:center; font-weight:600;"><?php echo htmlspecialchars($r['num_positions']); ?></td>
                                                <td>$<?php echo number_format($r['max_salary'], 0); ?> / <?php echo htmlspecialchars($r['salary_type'] === 'Monthly' ? 'Mo' : 'Yr'); ?></td>
                                                <td class="priority-cell" data-priority="<?php echo htmlspecialchars($r['priority']); ?>">
                                                    <span class="status-badge" style="font-size:0.75rem; background-color:rgba(255,255,255,0.03); border:1px solid var(--border-color); color:var(--text-primary)">
                                                        <?php echo htmlspecialchars($r['priority']); ?>
                                                    </span>
                                                </td>
                                                <td style="font-size:0.82rem; font-weight:600; color:var(--primary-color);">
                                                    <?php 
                                                    $stage = 'Workflow Initiated';
                                                    if ($r['status'] === 'Draft') $stage = 'Concept / Drafting';
                                                    else if ($r['status'] === 'Submitted' || $r['status'] === 'Pending Department Approval') $stage = '1/4 Department Head';
                                                    else if ($r['status'] === 'Pending HR Approval') $stage = '2/4 HR Operations';
                                                    else if ($r['status'] === 'Pending Management Approval') $stage = '3/4 Finance Executive';
                                                    else if ($r['status'] === 'Approved') $stage = '4/4 Ready for Recruitment';
                                                    else if ($r['status'] === 'Recruitment In Progress') $stage = 'Active Funnel Sourcing';
                                                    else if ($r['status'] === 'Closed') $stage = 'Complete';
                                                    else if ($r['status'] === 'Rejected') $stage = 'Rejected';
                                                    else if ($r['status'] === 'On Hold') $stage = 'Budget Vetting Freeze';
                                                    
                                                    echo $stage;
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower(explode(' ', $r['status'])[0]); ?>" data-status="<?php echo htmlspecialchars($r['status']); ?>">
                                                        <?php echo htmlspecialchars($r['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="display:flex; gap:6px;">
                                                        <button type="button" class="action-btn-sm" onclick="trackRequisition('<?php echo $r['requisition_id']; ?>')">
                                                            Track status
                                                        </button>
                                                        
                                                        <?php if ($userRole === 'hr'): ?>
                                                            <!-- HR ADMIN STATUS TRANSITIONS DROPDOWN -->
                                                            <form action="dashboard.php?page=my_requisitions" method="POST" style="display:inline-block;">
                                                                <input type="hidden" name="requisition_id" value="<?php echo $r['requisition_id']; ?>">
                                                                <select name="decision" onchange="this.form.submit()" class="form-control-select" style="padding:6px; font-size:0.8rem; height:auto; width:130px; background-color:var(--bg-tertiary);">
                                                                    <option value="">-- Change --</option>
                                                                    <option value="Pending HR Approval">Send to HR</option>
                                                                    <option value="Pending Management Approval">Send to Executive</option>
                                                                    <option value="Approved">Approve Position</option>
                                                                    <option value="Rejected">Reject Request</option>
                                                                    <option value="On Hold">Hold Hiring</option>
                                                                    <option value="Recruitment In Progress">Start Sourcing</option>
                                                                    <option value="Closed">Close Hiring</option>
                                                                </select>
                                                                <input type="hidden" name="action_hr_req_approval" value="1">
                                                            </form>
                                                        <?php elseif ($r['status'] === 'Draft'): ?>
                                                            <form action="dashboard.php?page=my_requisitions" method="POST" onsubmit="return confirm('Do you want to permanently delete this draft requisition?');">
                                                                <input type="hidden" name="requisition_id" value="<?php echo $r['requisition_id']; ?>">
                                                                <button type="submit" name="action_cancel_requisition" class="action-btn-sm action-btn-danger">
                                                                    Delete Draft
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" style="text-align:center; color:var(--text-secondary);">No hiring requisitions raised.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <!-- 8. ATTENDANCE MODULE -->
                <?php elseif ($currentPage === 'attendance'): 
                    // Calculate stats
                    try {
                        // Days present
                        $stmtPres = $pdo->prepare("SELECT COUNT(*) FROM `attendance` WHERE `employee_id` = :emp_id AND `status` = 'Present'");
                        $stmtPres->execute(['emp_id' => $userEmpId]);
                        $present = (int)$stmtPres->fetchColumn();

                        // Days late
                        $stmtLate = $pdo->prepare("SELECT COUNT(*) FROM `attendance` WHERE `employee_id` = :emp_id AND `status` = 'Late'");
                        $stmtLate->execute(['emp_id' => $userEmpId]);
                        $late = (int)$stmtLate->fetchColumn();

                        // Days absent
                        $stmtAbs = $pdo->prepare("SELECT COUNT(*) FROM `attendance` WHERE `employee_id` = :emp_id AND `status` = 'Absent'");
                        $stmtAbs->execute(['emp_id' => $userEmpId]);
                        $absent = (int)$stmtAbs->fetchColumn();

                        // Days leave
                        $stmtLve = $pdo->prepare("SELECT COUNT(*) FROM `attendance` WHERE `employee_id` = :emp_id AND `status` = 'Leave'");
                        $stmtLve->execute(['emp_id' => $userEmpId]);
                        $leaveDays = (int)$stmtLve->fetchColumn();

                        $workingDays = $present + $late + $absent + $leaveDays;

                        // Fetch calendar entries for August 2026
                        $stmtAttList = $pdo->prepare("SELECT * FROM `attendance` WHERE `employee_id` = :emp_id AND `date` BETWEEN '2026-08-01' AND '2026-08-31'");
                        $stmtAttList->execute(['emp_id' => $userEmpId]);
                        $attList = $stmtAttList->fetchAll();
                        
                        // Map key dates
                        $attMap = [];
                        foreach ($attList as $a) {
                            $attMap[$a['date']] = $a;
                        }

                    } catch (PDOException $e) {
                        $present = 0; $late = 0; $absent = 0; $leaveDays = 0; $workingDays = 0; $attMap = [];
                    }
                ?>
                    <div class="summary-cards-grid">
                        <div class="summary-card">
                            <div class="summary-card-icon green">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Present Days</span>
                                <span class="summary-card-val"><?php echo $present; ?> Days</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon amber">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line></svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Late Arrivals</span>
                                <span class="summary-card-val"><?php echo $late; ?> Days</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon red">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Absent Days</span>
                                <span class="summary-card-val"><?php echo $absent; ?> Days</span>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-icon primary">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line></svg>
                            </div>
                            <div class="summary-card-info">
                                <span class="summary-card-label">Leave Days</span>
                                <span class="summary-card-val"><?php echo $leaveDays; ?> Days</span>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1.6fr; gap:30px;">
                        
                        <!-- Attendance details widget -->
                        <div class="section-panel" style="margin-bottom:0;">
                            <h3 style="font-size:1.05rem; font-weight:700; color:var(--primary-color); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                Monthly Work Hours (August 2026)
                            </h3>
                            <div style="display:flex; flex-direction:column; gap:16px; font-size:0.9rem;">
                                <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:8px;">
                                    <span style="color:var(--text-secondary);">Total Tracked Days:</span>
                                    <span style="font-weight:600;"><?php echo $workingDays; ?> Working Days</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:8px;">
                                    <span style="color:var(--text-secondary);">Average Clock In Time:</span>
                                    <span style="font-weight:600;">08:52 AM</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:8px;">
                                    <span style="color:var(--text-secondary);">Average Working Hours:</span>
                                    <span style="font-weight:600;">8.4 Hrs / Day</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding-bottom:8px;">
                                    <span style="color:var(--text-secondary);">Office Punctuality Rating:</span>
                                    <span style="font-weight:600; color:var(--accent-green);">Excellent (92%)</span>
                                </div>
                            </div>
                            
                            <div style="margin-top:20px; border-top:1px dashed var(--border-color); padding-top:20px; font-size:0.8rem; color:var(--text-secondary); line-height:1.4;">
                                <p><strong>Note:</strong> Attendance calendar tracking is updated automatically from biometric door swipes and portal check-ins.</p>
                            </div>
                        </div>

                        <!-- Monthly Attendance calendar grid -->
                        <div class="calendar-widget">
                            <div class="calendar-header">
                                <h3 class="calendar-month-title">August 2026 Attendance Grid</h3>
                                <span style="font-size:0.8rem; color:var(--text-secondary);">Active logging</span>
                            </div>
                            
                            <div class="calendar-weekdays">
                                <div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div><div>S</div>
                            </div>
                            
                            <div class="calendar-grid">
                                <?php 
                                // August 2026 starts on Saturday (5 empty cells)
                                for ($i = 0; $i < 5; $i++) {
                                    echo '<div class="calendar-day-cell empty"></div>';
                                }

                                // Days in August: 31
                                for ($day = 1; $day <= 31; $day++) {
                                    $currentDateStr = sprintf("2026-08-%02d", $day);
                                    
                                    $cellClass = '';
                                    
                                    // Weekend check
                                    $dayOfWeek = date('N', strtotime($currentDateStr));
                                    
                                    if (isset($attMap[$currentDateStr])) {
                                        $status = $attMap[$currentDateStr]['status'];
                                        if ($status === 'Present') $cellClass = 'attendance-present';
                                        else if ($status === 'Late') $cellClass = 'attendance-late';
                                        else if ($status === 'Absent') $cellClass = 'attendance-absent';
                                        else if ($status === 'Leave') $cellClass = 'attendance-leave';
                                    } else if ($dayOfWeek >= 6) {
                                        // Weekends without swipe data
                                        $cellClass = '';
                                    } else if (strtotime($currentDateStr) > strtotime('2026-08-17')) {
                                        // Future days
                                        $cellClass = '';
                                    }

                                    echo '<div class="calendar-day-cell ' . $cellClass . '">';
                                    echo '<span class="day-number">' . $day . '</span>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>

                    </div>

                <!-- 9. DOCUMENTS MODULE -->
                <?php elseif ($currentPage === 'documents'): 
                    // Retrieve documents for directory
                    try {
                        $stmtDocs = $pdo->prepare("SELECT * FROM `employee_documents` WHERE `employee_id` = :emp_id");
                        $stmtDocs->execute(['emp_id' => $userEmpId]);
                        $docs = $stmtDocs->fetchAll();
                    } catch (PDOException $e) {
                        $docs = [];
                    }
                ?>
                    <div class="section-panel" style="margin-bottom:0;">
                        <div class="section-header">
                            <h2 class="section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                                Document Management Center
                            </h2>
                        </div>

                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Document Name</th>
                                        <th>Category Type</th>
                                        <th>Disbursement Date</th>
                                        <th>Format</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($docs) > 0): ?>
                                        <?php foreach ($docs as $d): ?>
                                            <tr>
                                                <td style="font-weight:600;"><?php echo htmlspecialchars($d['document_name']); ?></td>
                                                <td>
                                                    <span class="status-badge" style="font-size:0.75rem; background-color:rgba(255,255,255,0.03); border:1px solid var(--border-color); color:var(--text-primary);">
                                                        <?php echo htmlspecialchars($d['document_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($d['uploaded_at']); ?></td>
                                                <td style="font-family:monospace; font-weight:700; color:var(--accent-red);">PDF</td>
                                                <td>
                                                    <a href="download_doc.php?id=<?php echo $d['id']; ?>" class="action-btn-sm">
                                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                                                        Download PDF
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align:center; color:var(--text-secondary);">No documents mapped to your record yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <!-- 10. NOTIFICATIONS MODULE -->
                <?php elseif ($currentPage === 'notifications'): 
                    // Fetch notifications list
                    try {
                        $stmtNotifs = $pdo->prepare("SELECT * FROM `notifications` WHERE `employee_id` = :emp_id ORDER BY `created_at` DESC");
                        $stmtNotifs->execute(['emp_id' => $userEmpId]);
                        $notifsList = $stmtNotifs->fetchAll();

                        // Mark all as read
                        $stmtMarkRead = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `employee_id` = :emp_id");
                        $stmtMarkRead->execute(['emp_id' => $userEmpId]);
                    } catch (PDOException $e) {
                        $notifsList = [];
                    }
                ?>
                    <div class="section-panel" style="margin-bottom:0; max-width:860px; margin:0 auto;">
                        <div class="section-header">
                            <h2 class="section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                                Notification Center & HR Bulletins
                            </h2>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:2px; border:1px solid var(--border-color); border-radius:var(--radius-sm); overflow:hidden;">
                            <?php if (count($notifsList) > 0): ?>
                                <?php foreach ($notifsList as $n): ?>
                                    <div class="notification-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                                        <div class="notification-icon-box <?php echo htmlspecialchars($n['type']); ?>">
                                            <?php 
                                            $icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path></svg>';
                                            if ($n['type'] === 'leave') {
                                                $icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
                                            } else if ($n['type'] === 'requisition') {
                                                $icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg>';
                                            } else if ($n['type'] === 'salary') {
                                                $icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
                                            }
                                            echo $icon;
                                            ?>
                                        </div>
                                        
                                        <div class="notification-info">
                                            <div class="notification-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                            <div class="notification-text"><?php echo htmlspecialchars($n['message']); ?></div>
                                            <div class="notification-time"><?php echo htmlspecialchars($n['created_at']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align:center; padding:40px; color:var(--text-secondary);">
                                    No notifications received.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- 11. HELP / SUPPORT MODULE -->
                <?php elseif ($currentPage === 'help'): ?>
                    <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:30px; max-width:1080px; margin:0 auto;">
                        
                        <!-- FAQ Accordion -->
                        <div class="section-panel" style="margin-bottom:0;">
                            <h3 style="font-size:1.1rem; font-weight:700; color:var(--primary-color); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                Frequently Asked Questions
                            </h3>
                            
                            <div style="display:flex; flex-direction:column; gap:16px;">
                                <div style="padding-bottom:14px; border-bottom:1px solid var(--border-color);">
                                    <strong style="display:block; font-size:0.92rem; margin-bottom:6px;">How do I request an update to my designated office location?</strong>
                                    <p style="font-size:0.85rem; color:var(--text-secondary); line-height:1.4;">
                                        Office relocations must be pre-cleared by department managers. Submit a requisition request or contact HR Ops (hr@nexuslink.com) directly.
                                    </p>
                                </div>
                                <div style="padding-bottom:14px; border-bottom:1px solid var(--border-color);">
                                    <strong style="display:block; font-size:0.92rem; margin-bottom:6px;">When is the monthly salary payslip disbursed?</strong>
                                    <p style="font-size:0.85rem; color:var(--text-secondary); line-height:1.4;">
                                        Payslips are generated on the last working day of each month. Automated bank routing handles disbursements within 24 hours.
                                    </p>
                                </div>
                                <div>
                                    <strong style="display:block; font-size:0.92rem; margin-bottom:6px;">How does the hiring requisition approval workflow proceed?</strong>
                                    <p style="font-size:0.85rem; color:var(--text-secondary); line-height:1.4;">
                                        Submitting a requisition automatically triggers a sequentially chained approval tree: Department Head approval -> HR Operations vetting -> Management Board clearance -> Recruiter channel funnel.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Support Ticket submission form -->
                        <div class="section-panel" style="margin-bottom:0; height:fit-content;">
                            <h3 style="font-size:1.1rem; font-weight:700; color:var(--accent-teal); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                Submit HR Support Ticket
                            </h3>
                            
                            <form action="" method="POST" onsubmit="alert('Support ticket submitted successfully to HR desk!'); this.reset(); return false;">
                                <div style="display:flex; flex-direction:column; gap:14px;">
                                    <div>
                                        <label for="ticket_subject" class="form-label">Subject Topic</label>
                                        <input type="text" id="ticket_subject" class="form-control-input" required placeholder="Brief title...">
                                    </div>
                                    <div>
                                        <label for="ticket_msg" class="form-label">Ticket Details Description</label>
                                        <textarea id="ticket_msg" class="form-control-textarea" required placeholder="Describe the operational support required..."></textarea>
                                    </div>
                                    <button type="submit" class="submit-btn" style="width:100%; justify-content:center; padding:12px;">
                                        Submit Support Ticket
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>

                <!-- 12. SETTINGS MODULE -->
                <?php elseif ($currentPage === 'settings'): ?>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; max-width:960px; margin:0 auto;">
                        
                        <!-- Account Details Updates Form -->
                        <div class="section-panel" style="margin-bottom:0;">
                            <h3 style="font-size:1.1rem; font-weight:700; color:var(--primary-color); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                Personal Settings Update
                            </h3>
                            <form action="dashboard.php?page=settings" method="POST" novalidate>
                                <div style="display:flex; flex-direction:column; gap:16px;">
                                    <div>
                                        <label for="phone" class="form-label">Direct Contact Number</label>
                                        <input type="text" id="phone" name="phone" class="form-control-input" required value="<?php echo htmlspecialchars($profile['phone']); ?>">
                                    </div>
                                    <div>
                                        <label for="address" class="form-label">Residential Address</label>
                                        <textarea id="address" name="address" class="form-control-textarea" required><?php echo htmlspecialchars($profile['address']); ?></textarea>
                                    </div>
                                    <div>
                                        <label for="emergency_contact" class="form-label">Emergency Contacts Info</label>
                                        <input type="text" id="emergency_contact" name="emergency_contact" class="form-control-input" required value="<?php echo htmlspecialchars($profile['emergency_contact']); ?>">
                                    </div>
                                    
                                    <div class="form-actions-row" style="margin-top:10px; padding-top:16px;">
                                        <button type="submit" name="action_update_profile" class="submit-btn" style="width:100%; justify-content:center; padding:12px;">
                                            Save Settings Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Change Password Form -->
                        <div class="section-panel" style="margin-bottom:0; height:fit-content;">
                            <h3 style="font-size:1.1rem; font-weight:700; color:var(--accent-red); margin-bottom:18px; padding-bottom:8px; border-bottom:1px solid var(--border-color);">
                                Change Account Password
                            </h3>
                            <form action="dashboard.php?page=settings" method="POST" novalidate>
                                <div style="display:flex; flex-direction:column; gap:16px;">
                                    <div>
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" class="form-control-input" required placeholder="Enter current security password">
                                    </div>
                                    <div>
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" id="new_password" name="new_password" class="form-control-input" required placeholder="Minimum 6 characters">
                                    </div>
                                    <div>
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control-input" required placeholder="Re-type new password">
                                    </div>
                                    
                                    <div class="form-actions-row" style="margin-top:10px; padding-top:16px;">
                                        <button type="submit" name="action_update_password" class="submit-btn" style="width:100%; justify-content:center; padding:12px; background-color:var(--accent-red);">
                                            Confirm Password Reset
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                    </div>
                <?php endif; ?>

            </div>
            
            <footer style="padding:20px 40px; border-top:1px solid var(--border-color); text-align:center; font-size:0.8rem; color:var(--text-secondary); background-color:rgba(0,0,0,0.05);">
                &copy; 2026 NexusLink Corp. All Enterprise Systems Operational &bull; Secure Session ID Active
            </footer>

        </main>
    </div>

    <!-- 13. WORKFLOW TRACKER MODAL OVERLAY -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Track Requisition Status</h3>
                <button class="modal-close-btn" id="modalCloseBtn" aria-label="Close dialog">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Injected via AJAX -->
            </div>
        </div>
    </div>

    <!-- TOAST POPUPS FOR ALERTS -->
    <div class="toast-msg-container">
        <?php if (!empty($successMsg)): ?>
            <div class="toast-msg success">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($errorMsg)): ?>
            <div class="toast-msg error">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts integrations -->
    <script src="js/dashboard.js"></script>
</body>
</html>
