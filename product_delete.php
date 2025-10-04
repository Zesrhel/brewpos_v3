<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$id = $_GET['id'] ?? null;
if ($id) {
  $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
}
header('Location: products_list.php');
exit;
?>