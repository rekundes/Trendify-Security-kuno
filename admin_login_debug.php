<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: sign-in.html');
    exit;
}

$admin_name = ($_SESSION['first_name'] ?? 'Admin') . ' ' . ($_SESSION['last_name'] ?? '');
$debug_email = isset($_POST['debug_email']) ? trim($_POST['debug_email']) : '';
$test_password = isset($_POST['test_password']) ? $_POST['test_password'] : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';

$debug_result = null;
$message = '';

if ($action === 'check') {
    if (!$debug_email) {
        $message = 'Please enter an email';
    } else {
        // Get user and login attempts
        $user_sql = "SELECT user_id, email, password_hash FROM users WHERE email = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("s", $debug_email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();

        $attempts_sql = "SELECT * FROM login_attempts WHERE email = ?";
        $attempts_stmt = $conn->prepare($attempts_sql);
        $attempts_stmt->bind_param("s", $debug_email);
        $attempts_stmt->execute();
        $attempts_result = $attempts_stmt->get_result();

        $debug_result = [
            'user' => $user_result->num_rows > 0 ? $user_result->fetch_assoc() : null,
            'attempts' => $attempts_result->num_rows > 0 ? $attempts_result->fetch_assoc() : null
        ];

        $user_stmt->close();
        $attempts_stmt->close();
    }
}

if ($action === 'test_password') {
    if (!$debug_email || !$test_password) {
        $message = 'Please enter email and password';
    } else {
        $user_sql = "SELECT user_id, email, password_hash FROM users WHERE email = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("s", $debug_email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();

        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            if (password_verify($test_password, $user['password_hash'])) {
                $message = '✅ PASSWORD CORRECT';
            } else {
                $message = '❌ PASSWORD INCORRECT';
            }
        } else {
            $message = '❌ USER NOT FOUND';
        }
        $user_stmt->close();
    }
}

if ($action === 'clear_lockout') {
    if (!$debug_email) {
        $message = 'Please enter an email';
    } else {
        $reset_sql = "UPDATE login_attempts SET failed_attempts = 0, is_locked = 0, locked_until = NULL WHERE email = ?";
        $reset_stmt = $conn->prepare($reset_sql);
        $reset_stmt->bind_param("s", $debug_email);
        if ($reset_stmt->execute()) {
            $message = '✅ Lockout cleared and attempts reset for ' . htmlspecialchars($debug_email);
        } else {
            $message = '❌ Error clearing lockout';
        }
        $reset_stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login Debug Tool - Trendify Admin</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    :root{--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--accent:#111827;--danger:#dc2626;--success:#16a34a}
    body{font-family:Inter,system-ui,Segoe UI,Arial,Helvetica,sans-serif;background:var(--bg);margin:0}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:240px;background:#0f1724;color:#fff;padding:20px;box-sizing:border-box}
    .brand{font-weight:700;font-size:18px;margin-bottom:18px}
    .nav a{display:block;color:#cbd5e1;text-decoration:none;padding:10px;border-radius:6px;margin-bottom:6px;transition:all 0.2s}
    .nav a:hover{background:rgba(255,255,255,0.06);color:#fff}
    .nav a.active{background:rgba(59,130,246,0.2);color:#3b82f6}
    main{flex:1;padding:24px}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
    .section{background:var(--card);padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,0.04)}
    .section-title{font-size:18px;font-weight:600;margin-bottom:16px;color:var(--accent)}
    .form-group{margin-bottom:16px}
    label{display:block;margin-bottom:6px;color:#374151;font-weight:500;font-size:14px}
    input{padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;width:100%;box-sizing:border-box}
    input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.1)}
    .btn{padding:10px 16px;border:none;border-radius:6px;cursor:pointer;font-weight:600;transition:all 0.2s;font-size:14px}
    .btn-primary{background:#3b82f6;color:#fff}
    .btn-primary:hover{background:#2563eb}
    .btn-danger{background:var(--danger);color:#fff}
    .btn-danger:hover{background:#b91c1c}
    .message{padding:12px;border-radius:6px;margin-bottom:16px;font-weight:500}
    .message.success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
    .message.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
    .info-box{background:#f3f4f6;padding:16px;border-radius:6px;margin-bottom:16px;border-left:4px solid #3b82f6}
    .info-box p{margin:0 0 8px 0;color:#374151;font-size:14px}
    .info-box code{background:#e5e7eb;padding:2px 6px;border-radius:3px;font-family:monospace;font-size:12px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{text-align:left;padding:12px;border-bottom:1px solid #eef2f7;color:#111827;font-size:13px}
    th{background:#f9fafb;font-weight:600;color:var(--muted)}
    .badge{display:inline-block;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase}
    .badge.locked{background:#fee2e2;color:#991b1b}
    .badge.unlocked{background:#d1fae5;color:#065f46}
    @media (max-width:720px){.sidebar{display:none}.layout{flex-direction:column}main{padding:12px}}
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand"><img src="img/logo.png" alt="Trendify logo" style="width:32px;height:32px;object-fit:contain;vertical-align:middle;margin-right:10px;border-radius:4px">Trendify Admin</div>
      <nav class="nav">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_orders.php">Orders</a>
        <a href="admin_users.php">Users</a>
        <a href="admin_login_debug.php" class="active">Login Debug</a>
        <a href="logout.php">Sign Out</a>
      </nav>
    </aside>
    <main>
      <div class="header">
        <h1 style="margin:0;font-size:24px">Login Debug Tool</h1>
        <div style="color:var(--muted)">Welcome back, <?= htmlspecialchars($admin_name) ?></div>
      </div>

      <?php if ($message): ?>
        <div class="message <?= strpos($message, '✅') !== false || strpos($message, 'CORRECT') !== false ? 'success' : 'error' ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <div class="section">
        <div class="section-title">Check User Login Status</div>
        <div class="info-box">
          <p><strong>Use this tool to:</strong></p>
          <p>✓ Check if a user's account is locked</p>
          <p>✓ Test if a password is correct for an email</p>
          <p>✓ Clear lockouts and reset attempt counters</p>
        </div>

        <form method="POST">
          <div class="form-group">
            <label for="debug_email">User Email</label>
            <input type="email" id="debug_email" name="debug_email" value="<?= htmlspecialchars($debug_email) ?>" required>
          </div>

          <div style="display:flex;gap:12px;margin-bottom:20px">
            <button type="submit" name="action" value="check" class="btn btn-primary">Check Status</button>
            <button type="submit" name="action" value="clear_lockout" class="btn btn-danger">Clear Lockout</button>
          </div>
        </form>
      </div>

      <?php if ($debug_result): ?>
        <div class="section">
          <div class="section-title">User Account Status</div>
          
          <?php if ($debug_result['user']): ?>
            <div class="info-box">
              <p><strong>Email:</strong> <?= htmlspecialchars($debug_result['user']['email']) ?></p>
              <p><strong>User ID:</strong> <?= $debug_result['user']['user_id'] ?></p>
              <p><strong>Password Hash:</strong> <code><?= substr($debug_result['user']['password_hash'], 0, 20) ?>...</code></p>
            </div>
          <?php else: ?>
            <p style="color:#991b1b">❌ User not found in database</p>
          <?php endif; ?>

          <?php if ($debug_result['attempts']): ?>
            <table>
              <tr>
                <th>Failed Attempts</th>
                <td><?= $debug_result['attempts']['failed_attempts'] ?></td>
              </tr>
              <tr>
                <th>Is Locked</th>
                <td>
                  <?php if ($debug_result['attempts']['is_locked']): ?>
                    <span class="badge locked">LOCKED</span>
                  <?php else: ?>
                    <span class="badge unlocked">UNLOCKED</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <th>Locked Until</th>
                <td><?= $debug_result['attempts']['locked_until'] ?? 'N/A' ?></td>
              </tr>
              <tr>
                <th>Last Attempt</th>
                <td><?= $debug_result['attempts']['last_attempt'] ?></td>
              </tr>
            </table>
          <?php else: ?>
            <p style="color:#6b7280">No login attempts recorded yet for this user</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="section">
        <div class="section-title">Test Password</div>
        <p style="color:#6b7280;margin-bottom:16px;font-size:14px">Enter a password to verify if it's correct for the email above</p>
        
        <form method="POST">
          <input type="hidden" name="debug_email" value="<?= htmlspecialchars($debug_email) ?>">
          <div class="form-group">
            <label for="test_password">Password to Test</label>
            <input type="password" id="test_password" name="test_password" required>
          </div>
          <button type="submit" name="action" value="test_password" class="btn btn-primary">Test Password</button>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
<?php
$conn->close();
?>
