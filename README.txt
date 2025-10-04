-- Active: 1713630479832@@127.0.0.1@3306@brewpos_v3
BrewPOS v2 - Tiger Bubble Tea POS (Cashier + POS)
Files:
- login.php, logout.php
- index.php (POS with modal for size/sugar)
- products_list.php, product_form.php, product_delete.php (CRUD)
- db.php (DB connection + session start)
- api/products.php, api/orders.php
- init_db.sql (create DB, tables, seed cashier user and products)
Setup:
1. Copy the 'brewpos_v2' folder to XAMPP htdocs.
2. Start Apache and MySQL.
3. Import 'init_db.sql' into phpMyAdmin (this creates DB Brewpos).
4. Login at /brewpos_v2/login.php with username: cashier and password: 1234
Notes:
- This package keeps everything local (XAMPP). GCash is recorded as manual reference number.
- Product management pages are accessible to the cashier account.
-Created: 2025-09-15T07:55:01.942492 UTC

cashier acct-123@gmail.com
            -12345
localhost: http://localhost/brewpos_v3/login.php
