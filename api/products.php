<?php
require_once '../db.php';
header('Content-Type: application/json');

try {
    // Get products with inventory quantities
    $stmt = $pdo->query("
        SELECT p.*, COALESCE(i.quantity, 0) as stock_quantity 
        FROM products p 
        LEFT JOIN inventory i ON p.id = i.product_id 
        ORDER BY p.name
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>