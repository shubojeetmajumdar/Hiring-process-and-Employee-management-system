<?php
// download_payslip.php - Interactive Printable Payslip
require_once 'config.php';

// Validate login session
if (!isset($_SESSION['user_id'])) {
    die("Error: Unauthorized access.");
}

$payslipId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($payslipId <= 0) {
    die("Error: Invalid payslip request.");
}

try {
    // Fetch payslip and employee join data
    $stmt = $pdo->prepare("SELECT p.*, e.name, e.employee_id, e.designation, e.department, e.location, e.employment_type
                           FROM `payslips` p
                           JOIN `employees` e ON p.employee_id = e.employee_id
                           WHERE p.id = :id LIMIT 1");
    $stmt->execute(['id' => $payslipId]);
    $payslip = $stmt->fetch();

    if (!$payslip) {
        die("Error: Payslip not found.");
    }

    // Secure checking
    if ($_SESSION['role'] !== 'hr') {
        $empId = isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] : '';
        if (empty($empId) || $payslip['employee_id'] !== $empId) {
            die("Error: Access Denied. You do not have permissions to view this payslip.");
        }
    }

    // Financial arithmetic
    $basic = (float)$payslip['basic_salary'];
    $allowances = (float)$payslip['allowances'];
    $deductions = (float)$payslip['deductions'];
    $gross = (float)$payslip['gross_salary'];
    $net = (float)$payslip['net_salary'];
    
    // Detailed breakdown breakdown
    $hra = $allowances * 0.40;
    $specialAllow = $allowances * 0.60;
    
    $providentFund = $deductions * 0.70;
    $healthInsurance = $deductions * 0.30;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($payslip['month']); ?> - <?php echo htmlspecialchars($payslip['employee_id']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0b0f19;
            --bg-secondary: #131b2e;
            --bg-tertiary: #1e293b;
            --primary: #6366f1;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-green: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .actions-panel {
            width: 100%;
            max-width: 800px;
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
            z-index: 10;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            border: none;
        }

        .btn-secondary {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: var(--bg-tertiary);
        }

        .btn-primary {
            background-color: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #4f46e5;
            box-shadow: 0 0 12px rgba(99, 102, 241, 0.4);
        }

        .payslip-container {
            width: 100%;
            max-width: 800px;
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 24px;
            margin-bottom: 30px;
        }

        .company-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-box {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, #14b8a6 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-box svg {
            width: 20px;
            height: 20px;
            fill: #fff;
        }

        .company-name {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .payslip-title {
            text-align: right;
        }

        .payslip-title h2 {
            font-size: 1.5rem;
            color: var(--primary);
            font-weight: 800;
            text-transform: uppercase;
        }

        .payslip-title p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 36px;
            background-color: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
        }

        .info-col h3 {
            font-size: 1rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
            margin-bottom: 12px;
            color: var(--primary);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .info-label {
            color: var(--text-secondary);
        }

        .info-val {
            font-weight: 600;
        }

        .table-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .table-col h3 {
            font-size: 1.1rem;
            margin-bottom: 16px;
            padding-bottom: 6px;
            border-bottom: 2px solid var(--primary);
        }

        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }

        .breakdown-table th, .breakdown-table td {
            padding: 10px 12px;
            text-align: left;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border-color);
        }

        .breakdown-table th {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .breakdown-table td.amount {
            text-align: right;
            font-weight: 600;
        }

        .totals-section {
            border-top: 2px solid var(--border-color);
            padding-top: 20px;
            margin-bottom: 40px;
        }

        .totals-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            text-align: center;
        }

        .total-card {
            background-color: rgba(255,255,255,0.01);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
        }

        .total-card.highlight {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(20, 184, 166, 0.05) 100%);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .total-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .total-val {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .total-card.highlight .total-val {
            color: var(--primary);
        }

        .footer-note {
            text-align: center;
            border-top: 1px dashed var(--border-color);
            padding-top: 24px;
            color: var(--text-secondary);
            font-size: 0.8rem;
            line-height: 1.5;
        }

        .status-stamp {
            position: absolute;
            top: 20px;
            right: 40px;
            border: 3px double var(--accent-green);
            color: var(--accent-green);
            font-size: 1.1rem;
            font-weight: 800;
            padding: 6px 16px;
            transform: rotate(-12deg) translateY(90px) translateX(-50px);
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.85;
            background-color: var(--bg-secondary);
        }

        /* Printable Style Formats */
        @media print {
            body {
                background-color: #fff;
                color: #000;
                padding: 0;
            }

            .actions-panel {
                display: none;
            }

            .payslip-container {
                border: none;
                box-shadow: none;
                padding: 0;
                background-color: #fff;
                color: #000;
                max-width: 100%;
            }

            .status-stamp {
                background-color: #fff;
                color: #10b981;
                border-color: #10b981;
            }

            .details-grid {
                background-color: #fff;
                border: 1px solid #ddd;
                color: #000;
            }

            .info-label {
                color: #555;
            }

            .table-col h3 {
                border-bottom: 2px solid #000;
                color: #000;
            }

            .breakdown-table th {
                color: #444;
                border-bottom: 1px solid #ccc;
            }

            .breakdown-table td {
                border-bottom: 1px solid #eee;
                color: #111;
            }

            .total-card {
                border: 1px solid #ddd;
                background-color: #fff;
            }

            .total-card.highlight {
                border: 1px solid #000;
                background-color: #f9f9f9;
            }

            .total-card.highlight .total-val {
                color: #000;
            }

            .total-label {
                color: #555;
            }

            .footer-note {
                border-top: 1px dashed #ccc;
                color: #666;
            }
        }
    </style>
</head>
<body>

    <div class="actions-panel">
        <a href="dashboard.php?page=salary" class="btn btn-secondary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Dashboard
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <path d="M6 14h12v8H6z"/>
            </svg>
            Print / Save PDF
        </button>
    </div>

    <div class="payslip-container">
        
        <div class="status-stamp"><?php echo htmlspecialchars($payslip['payment_status']); ?></div>

        <div class="header">
            <div class="company-logo">
                <div class="logo-box">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/>
                        <circle cx="12" cy="12" r="3" fill="none" stroke="#fff" stroke-width="1.5"/>
                    </svg>
                </div>
                <span class="company-name">NexusLink Corp</span>
            </div>
            
            <div class="payslip-title">
                <h2>Payslip</h2>
                <p>Pay Period: <?php echo htmlspecialchars($payslip['month']); ?></p>
            </div>
        </div>

        <div class="details-grid">
            <div class="info-col">
                <h3>Employee Details</h3>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-val"><?php echo htmlspecialchars($payslip['name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employee ID:</span>
                    <span class="info-val"><?php echo htmlspecialchars($payslip['employee_id']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Designation:</span>
                    <span class="info-val"><?php echo htmlspecialchars($payslip['designation']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Department:</span>
                    <span class="info-val"><?php echo htmlspecialchars($payslip['department']); ?></span>
                </div>
            </div>
            <div class="info-col">
                <h3>Payment Details</h3>
                <div class="info-row">
                    <span class="info-label">Payment Mode:</span>
                    <span class="info-val">Bank Transfer</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Work Location:</span>
                    <span class="info-val"><?php echo htmlspecialchars($payslip['location']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employment Type:</span>
                    <span class="info-val"><?php echo htmlspecialchars($payslip['employment_type']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Disbursement Date:</span>
                    <span class="info-val"><?php echo htmlspecialchars($payslip['payment_date']); ?></span>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-col">
                <h3>Earnings</h3>
                <table class="breakdown-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th style="text-align: right;">Amount ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="amount"><?php echo number_format($basic, 2); ?></td>
                        </tr>
                        <tr>
                            <td>House Rent Allowance (HRA)</td>
                            <td class="amount"><?php echo number_format($hra, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Special Allowance</td>
                            <td class="amount"><?php echo number_format($specialAllow, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-col">
                <h3>Deductions</h3>
                <table class="breakdown-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th style="text-align: right;">Amount ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Provident Fund (PF)</td>
                            <td class="amount"><?php echo number_format($providentFund, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Health & Dental Insurance Deductible</td>
                            <td class="amount"><?php echo number_format($healthInsurance, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Income Tax / Tax Deductions</td>
                            <td class="amount">0.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="totals-section">
            <div class="totals-grid">
                <div class="total-card">
                    <div class="total-label">Gross Earnings</div>
                    <div class="total-val">$<?php echo number_format($gross, 2); ?></div>
                </div>
                <div class="total-card">
                    <div class="total-label">Total Deductions</div>
                    <div class="total-val">$<?php echo number_format($deductions, 2); ?></div>
                </div>
                <div class="total-card highlight">
                    <div class="total-label">Net Pay</div>
                    <div class="total-val">$<?php echo number_format($net, 2); ?></div>
                </div>
            </div>
        </div>

        <div class="footer-note">
            <p>This is a system-generated document and does not require a physical signature.</p>
            <p style="margin-top: 4px;">NexusLink Corp &bull; 100 Pine St, San Francisco, CA 94111 &bull; hr@nexuslink.com</p>
        </div>

    </div>

</body>
</html>
