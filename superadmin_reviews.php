<?php
require_once 'config.php';

// Check superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: sign-in.html');
    exit;
}

$admin_name = ($_SESSION['first_name'] ?? 'Superadmin') . ' ' . ($_SESSION['last_name'] ?? '');

// Fetch reviews using prepared statement
$reviews = [];
$sql = "SELECT pr.*, u.email, u.first_name, u.last_name
        FROM product_reviews pr
        LEFT JOIN users u ON pr.user_id = u.user_id
        ORDER BY pr.created_at DESC
        LIMIT 200";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) $reviews[] = $r;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Superadmin Reviews - Trendify</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body{font-family:Inter,system-ui,Arial,Helvetica,sans-serif;background:#f5f7fb;margin:0}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:260px;background:#0a0f1f;color:#fff;padding:20px;box-sizing:border-box;overflow-y:auto}
    .nav a{display:block;color:#cbd5e1;text-decoration:none;padding:12px;border-radius:6px;margin-bottom:6px}
    main{flex:1;padding:24px}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden}
    th,td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left}
    th{background:#f8fafc;color:#6b7280;text-transform:uppercase;font-size:12px}
    img.rev{width:100px;height:70px;object-fit:cover;filter:grayscale(100%)}
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div style="font-weight:700;margin-bottom:18px">Trendify Superadmin</div>
      <nav class="nav">
        <a href="superadmin_dashboard.php">Dashboard</a>
        <a href="superadmin_orders.php">Orders</a>
        <a href="superadmin_users.php">Users & Admins</a>
        <a href="superadmin_reviews.php">Reviews</a>
        <a href="superadmin_system.php">System</a>
        <a href="logout.php">Sign Out</a>
      </nav>
    </aside>
    <main>
      <h1 style="margin-top:0">Customer Reviews</h1>
      <p style="color:#6b7280">All reviews submitted by customers.</p>

      <?php if (count($reviews) === 0): ?>
        <div style="margin-top:24px;padding:20px;background:#fff;border-radius:8px;color:#6b7280">No reviews yet.</div>
      <?php else: ?>
        <table style="margin-top:16px">
          <thead>
            <tr><th>Date</th><th>Product</th><th>User</th><th>Rating</th><th>Image</th><th>Image Rating</th><th>Comment</th></tr>
          </thead>
          <tbody>
            <?php foreach ($reviews as $r): ?>
              <tr>
                <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($r['created_at']))) ?></td>
                <td><?= htmlspecialchars($r['product_name']) ?></td>
                <td><?= htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '') . ' (' . ($r['email'] ?? 'unknown') . ')') ?></td>
                <td><?= (int)$r['rating'] ?>/5</td>
                <td>
                  <?php if (!empty($r['image_path'])): ?>
                    <a href="<?= htmlspecialchars($r['image_path']) ?>" target="_blank"><img class="rev" src="<?= htmlspecialchars($r['image_path']) ?>" alt="review image"></a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td><?= $r['image_rating'] !== null ? (int)$r['image_rating'] . '/5' : '—' ?></td>
                <td><?= nl2br(htmlspecialchars($r['comment'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>

<?php $conn->close(); ?>
