<?php
require_once 'config.php';

// Check superadmin email status
$sql = "SELECT user_id, email, role, is_admin FROM users WHERE email='superadmin@trendify.com'";
$result = $conn->query($sql);

if($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<pre>";
    echo "Superadmin Account Found:\n";
    echo "Email: " . $row['email'] . "\n";
    echo "Role: " . $row['role'] . "\n";
    echo "Is Admin: " . $row['is_admin'] . "\n";
    echo "</pre>";
    
    // Fix if needed
    if($row['role'] !== 'superadmin') {
        echo "<p style='color:red'>Role is not 'superadmin', fixing...</p>";
        $update = "UPDATE users SET role='superadmin', is_admin=1 WHERE email='superadmin@trendify.com'";
        if($conn->query($update)) {
            echo "<p style='color:green'>✓ Fixed! Role updated to 'superadmin'</p>";
        }
    } else {
        echo "<p style='color:green'>✓ Role is correct!</p>";
    }
} else {
    echo "<p style='color:red'>Superadmin account not found!</p>";
}

// Also check admin account
echo "<hr>";
$sql2 = "SELECT user_id, email, role, is_admin FROM users WHERE email='admin@trendify.com'";
$result2 = $conn->query($sql2);

if($result2->num_rows > 0) {
    $row2 = $result2->fetch_assoc();
    echo "<pre>";
    echo "Admin Account Found:\n";
    echo "Email: " . $row2['email'] . "\n";
    echo "Role: " . $row2['role'] . "\n";
    echo "Is Admin: " . $row2['is_admin'] . "\n";
    echo "</pre>";
} else {
    echo "<p style='color:red'>Admin account not found!</p>";
}
?>
