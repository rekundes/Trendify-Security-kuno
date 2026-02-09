<?php
require_once 'config.php';

// Create/Update Users Table
$users_sql = "CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    is_admin TINYINT DEFAULT 0,
    role ENUM('customer', 'admin', 'superadmin') DEFAULT 'customer',
    reset_token VARCHAR(255),
    reset_token_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Check if role column exists, if not add it
$check_role = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
if ($check_role && $check_role->num_rows === 0) {
    $add_role = "ALTER TABLE users ADD COLUMN role ENUM('customer', 'admin', 'superadmin') DEFAULT 'customer' AFTER is_admin";
    if ($conn->query($add_role)) {
        echo "✓ Added role column to users table<br>";
    } else {
        echo "Note: Role column already exists or couldn't be added<br>";
    }
}

// Check if reset_token columns exist, if not add them
$check_reset = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'reset_token'");
if ($check_reset && $check_reset->num_rows === 0) {
    $add_reset = "ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) AFTER role, ADD COLUMN reset_token_expiry DATETIME AFTER reset_token";
    if ($conn->query($add_reset)) {
        echo "✓ Added reset token columns to users table<br>";
    } else {
        echo "Note: Reset token columns already exist or couldn't be added<br>";
    }
}

if ($conn->query($users_sql) === TRUE) {
    echo "✓ Users table created/verified<br>";
} else {
    echo "Error with users table: " . $conn->error . "<br>";
}

// Check and update Orders Table - ADD MISSING COLUMNS
$check_order_table = $conn->query("SHOW COLUMNS FROM orders");
$order_columns = [];
if ($check_order_table) {
    while ($col = $check_order_table->fetch_assoc()) {
        $order_columns[] = $col['Field'];
    }
}

// Add missing columns
$columns_needed = [
    'order_date' => "ALTER TABLE orders ADD COLUMN order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER order_id",
    'status' => "ALTER TABLE orders ADD COLUMN status VARCHAR(50) DEFAULT 'Processing' AFTER order_date",
    'total_amount' => "ALTER TABLE orders ADD COLUMN total_amount DECIMAL(10,2) AFTER status",
    'shipping_address' => "ALTER TABLE orders ADD COLUMN shipping_address TEXT AFTER total_amount",
    'first_name' => "ALTER TABLE orders ADD COLUMN first_name VARCHAR(100) AFTER shipping_address",
    'last_name' => "ALTER TABLE orders ADD COLUMN last_name VARCHAR(100) AFTER first_name",
    'email' => "ALTER TABLE orders ADD COLUMN email VARCHAR(255) AFTER last_name",
    'phone' => "ALTER TABLE orders ADD COLUMN phone VARCHAR(20) AFTER email",
    'city' => "ALTER TABLE orders ADD COLUMN city VARCHAR(100) AFTER phone",
    'postcode' => "ALTER TABLE orders ADD COLUMN postcode VARCHAR(20) AFTER city"
];

foreach ($columns_needed as $col => $sql) {
    if (!in_array($col, $order_columns)) {
        if ($conn->query($sql)) {
            echo "✓ Added column '$col' to orders table<br>";
        } else {
            echo "Error adding column '$col': " . $conn->error . "<br>";
        }
    }
}

echo "✓ Orders table verified/updated<br>";

// Create/Update Order Items Table
$order_items_sql = "CREATE TABLE IF NOT EXISTS order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_size VARCHAR(10),
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
)";

if ($conn->query($order_items_sql) === TRUE) {
    echo "✓ Order Items table created/verified<br>";
} else {
    echo "Error with order items table: " . $conn->error . "<br>";
}

// Create/Update Products Table
$products_sql = "CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    category VARCHAR(100),
    stock INT DEFAULT 0,
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($products_sql) === TRUE) {
    echo "✓ Products table created/verified<br>";
} else {
    echo "Error with products table: " . $conn->error . "<br>";
}

echo "<br><strong>✓ Database setup completed!</strong><br>";
echo "You can now place orders. They will appear in the admin dashboard.";
$conn->close();
?>
