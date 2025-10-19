<?php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    try {
        // Ensure product_sales table exists (idempotent)
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Validate stock levels BEFORE starting transaction
        foreach ($input['items'] as $item) {
            $product_id = $item['product_id'];
            $requested_quantity = (int)$item['quantity'];
            
            // Check current stock level
            $stock_check = $pdo->prepare("
                SELECT COALESCE(quantity, 0) as current_stock 
                FROM inventory 
                WHERE product_id = ?
            ");
            $stock_check->execute([$product_id]);
            $current_stock = $stock_check->fetchColumn();
            
            if ($current_stock < $requested_quantity) {
                echo json_encode([
                    'error' => "Insufficient stock for {$item['name']}. Available: {$current_stock}, Requested: {$requested_quantity}"
                ]);
                exit;
            }
        }
        
        // Validate raw materials BEFORE starting transaction
        $total_drinks = 0;
        $small_cups = 0;
        $medium_cups = 0;
        $large_cups = 0;
        
        foreach ($input['items'] as $item) {
            $quantity = (int)$item['quantity'];
            $total_drinks += $quantity;
            
            $cup_size = strtolower($item['cup_size'] ?? 'medium');
            if ($cup_size === 'small') {
                $small_cups += $quantity;
            } elseif ($cup_size === 'large') {
                $large_cups += $quantity;
            } else {
                $medium_cups += $quantity;
            }
        }
        
        // Check raw materials availability
        $raw_materials_check = [
            ['name' => 'Tea Cups (Small)', 'needed' => $small_cups],
            ['name' => 'Tea Cups (Medium)', 'needed' => $medium_cups],
            ['name' => 'Tea Cups (Large)', 'needed' => $large_cups],
        ];
        
        foreach ($raw_materials_check as $material) {
            if ($material['needed'] <= 0) continue;
            
            $check = $pdo->prepare("SELECT COALESCE(quantity, 0) FROM inventory_items WHERE item_name = ?");
            $check->execute([$material['name']]);
            $available = $check->fetchColumn();
            
            if ($available < $material['needed']) {
                echo json_encode([
                    'error' => "Insufficient raw material: {$material['name']}. Available: {$available}, Needed: {$material['needed']}"
                ]);
                exit;
            }
        }
        
        $pdo->beginTransaction();
        
        // Generate order number
        $order_number = 'ORD' . date('YmdHis');
        
        // Insert order
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, subtotal, tax, total, payment_type, amount_received, change_amount, gcash_ref, cashier_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $order_number,
            $input['subtotal'],
            $input['tax'],
            $input['total'],
            $input['payment_type'],
            $input['amount_received'] ?? $input['total'],
            $input['change'] ?? 0,
            $input['gcash_ref'] ?? null,
            $_SESSION['user_id']
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Insert order items and update inventory
        foreach ($input['items'] as $item) {
            // Insert order item
            $item_stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, total_price, cup_size, sugar_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $item_stmt->execute([
                $order_id,
                $item['product_id'],
                $item['name'],
                $item['unit_price'],
                $item['quantity'],
                $item['total_price'],
                $item['cup_size'],
                $item['sugar_level']
            ]);
            
            // Update inventory (reduce stock)
            $update_stmt = $pdo->prepare("
                UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?
            ");
            $update_stmt->execute([$item['quantity'], $item['product_id']]);

            // Record into product_sales for reporting
            $sales_stmt = $pdo->prepare("INSERT INTO product_sales (order_id, product_id, product_name, quantity, total_amount) VALUES (?, ?, ?, ?, ?)");
            $sales_stmt->execute([
                $order_id,
                $item['product_id'],
                $item['name'],
                (int)$item['quantity'],
                $item['total_price']
            ]);
        }
        
        // Automatically deduct ingredients from inventory_items
        // Calculate total quantity of drinks ordered and track cup sizes
        $total_drinks = 0;
        $small_cups = 0;
        $medium_cups = 0;
        $large_cups = 0;
        
        foreach ($input['items'] as $item) {
            $quantity = (int)$item['quantity'];
            $total_drinks += $quantity;
            
            // Track cup sizes based on cup_size field
            $cup_size = strtolower($item['cup_size'] ?? 'medium');
            if ($cup_size === 'small') {
                $small_cups += $quantity;
            } elseif ($cup_size === 'large') {
                $large_cups += $quantity;
            } else {
                $medium_cups += $quantity; // default to medium
            }
        }
        
        // Deduct ingredients if there are drinks
        if ($total_drinks > 0) {
            // Deduct cups based on size
            if ($small_cups > 0) {
                $small_cups_stmt = $pdo->prepare("
                    UPDATE inventory_items 
                    SET quantity = quantity - ? 
                    WHERE item_name = 'Tea Cups (Small)'
                ");
                $small_cups_stmt->execute([$small_cups]);
            }
            
            if ($medium_cups > 0) {
                $medium_cups_stmt = $pdo->prepare("
                    UPDATE inventory_items 
                    SET quantity = quantity - ? 
                    WHERE item_name = 'Tea Cups (Medium)'
                ");
                $medium_cups_stmt->execute([$medium_cups]);
            }
            
            if ($large_cups > 0) {
                $large_cups_stmt = $pdo->prepare("
                    UPDATE inventory_items 
                    SET quantity = quantity - ? 
                    WHERE item_name = 'Tea Cups (Large)'
                ");
                $large_cups_stmt->execute([$large_cups]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'order_number' => $order_number,
            'order_id' => $order_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Order failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>