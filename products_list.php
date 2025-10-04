<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}

// Handle product deletion
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    try {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        $_SESSION['success'] = "Product deleted successfully";
        header('Location: products_list.php');
        exit;
    } catch (Exception $e) {
        $error = "Error deleting product: " . $e->getMessage();
    }
}

// Get all products with stock information
$stmt = $pdo->query("
    SELECT p.*, COALESCE(i.quantity, 0) as stock_quantity 
    FROM products p 
    LEFT JOIN inventory i ON p.id = i.product_id 
    ORDER BY p.name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_products = count($products);
$out_of_stock = 0;
$low_stock = 0;
$in_stock = 0;

foreach ($products as $product) {
    if ($product['stock_quantity'] <= 0) {
        $out_of_stock++;
    } elseif ($product['stock_quantity'] < 10) {
        $low_stock++;
    } else {
        $in_stock++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - BrewPOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .header-container {
            background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #6f42c1;
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        .table th {
            background-color: #e9ecef;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .btn-primary {
            background: linear-gradient(to right, #6f42c1, #d63384);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, #5a32a3, #b52a6f);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .stock-low {
            color: #dc3545;
            font-weight: 600;
        }
        .stock-available {
            color: #198754;
            font-weight: 600;
        }
        .product-image {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #dee2e6;
        }
        .action-buttons .btn {
            margin-left: 5px;
            border-radius: 6px;
        }
        .search-box {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <!-- Header -->
        <div class="header-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-cubes me-2"></i>Product Inventory</h2>
                    <p class="mb-0 mt-2">Manage your products and stock levels</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-light me-2"><i class="fas fa-arrow-left me-1"></i> Back to POS</a>
                    <a href="product_form.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Product</a>
                </div>
            </div>
        </div>

        <!-- Display Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted">Total Products</h6>
                                <h3 class="mb-0"><?php echo $total_products; ?></h3>
                            </div>
                            <div class="bg-primary p-3 rounded">
                                <i class="fas fa-cube fa-2x text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted">Out of Stock</h6>
                                <h3 class="mb-0"><?php echo $out_of_stock; ?></h3>
                            </div>
                            <div class="bg-danger p-3 rounded">
                                <i class="fas fa-exclamation-triangle fa-2x text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted">Low Stock</h6>
                                <h3 class="mb-0"><?php echo $low_stock; ?></h3>
                            </div>
                            <div class="bg-warning p-3 rounded">
                                <i class="fas fa-arrow-down fa-2x text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted">In Stock</h6>
                                <h3 class="mb-0"><?php echo $in_stock; ?></h3>
                            </div>
                            <div class="bg-success p-3 rounded">
                                <i class="fas fa-check-circle fa-2x text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>All Products</span>
                <div class="d-flex">
                    <input type="text" class="form-control search-box me-2" placeholder="Search products..." id="searchInput">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): 
                                $status = 'In Stock';
                                $status_class = 'bg-success';
                                if ($product['stock_quantity'] <= 0) {
                                    $status = 'Out of Stock';
                                    $status_class = 'bg-danger';
                                } elseif ($product['stock_quantity'] < 10) {
                                    $status = 'Low Stock';
                                    $status_class = 'bg-warning';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                <td class="<?php echo $product['stock_quantity'] <= 0 ? 'stock-low' : 'stock-available'; ?>">
                                    <?php echo $product['stock_quantity']; ?>
                                </td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                <td class="action-buttons">
                                    <a href="product_form.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="products_list.php?delete_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($product['name']); ?>?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($products)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No products found. <a href="product_form.php">Add your first product</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stock Alert -->
        <?php if ($out_of_stock > 0): ?>
        <div class="alert alert-warning mt-4" role="alert">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Stock Alert</h5>
            <p class="mb-0">You have <?php echo $out_of_stock; ?> products that are out of stock. Consider restocking to avoid sales disruption.</p>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const productName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const sku = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                
                if (productName.includes(searchText) || sku.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>