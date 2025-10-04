<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>BrewPOS - Tiger Bubble Tea POS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --bg:#fff7f0; --accent:#f7d7c4; --accent-2:#cfead8; --text:#6b4a35;
    }
    body { background: linear-gradient(180deg,#fff,#fff7f0); color:var(--text); padding:18px; }
    .pos-card { border-radius:14px; box-shadow:0 8px 24px rgba(74,50,37,0.06); }
    .product-card { cursor:pointer; border-radius:12px; transition:transform .07s; background:linear-gradient(180deg,#fff,#fff7ea); }
    .product-card:active{ transform:scale(.98); }
    .product-card.disabled { 
      cursor:not-allowed; 
      opacity:0.6; 
      background:linear-gradient(180deg,#f8f9fa,#e9ecef);
      border:2px dashed #dee2e6;
    }
    .product-card.disabled:active{ transform:none; }
    .btn-cta { background:var(--text); color:white; border-radius:12px; }
    .logo { font-weight:800; color:#7a4b2a; font-size:20px; }
    .small-muted { font-size:13px; color:#7a6a65; }
    .receipt { font-family:monospace; white-space:pre-wrap; }
    .rounded-input { border-radius:10px; }
    .badge-stock { background:#fff3; color:var(--text); border-radius:8px; padding:4px 8px; font-weight:600; }
    @media print { body *{visibility:hidden} #printable, #printable *{visibility:visible} #printable{position:absolute;left:0;top:0;width:100%} }
  </style>
</head>
<body>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="logo">🧋 BrewPOS — Tiger Bubble Tea</div>
    <div>
      <span class="me-3 small-muted">Cashier Name: <strong><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></strong></span>
      <a href="sales.php" class="btn btn-sm btn-outline-success me-2">Sales</a>
      <a href="products_list.php" class="btn btn-sm btn-outline-secondary me-2">Products</a> 
      <a href="inventory.php" class="btn btn-sm btn-outline-info me-2">Inventory</a>
      <a href="logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card pos-card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div><strong>Products</strong><div class="small-muted">Tap to add. Choose options in the modal.</div></div>
          <div id="clock" class="small-muted"></div>
        </div>
        <div id="products" class="row g-2"></div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-3 pos-card mb-3">
        <h5>Checkout</h5>
        <div class="mb-2">
          <input id="search" class="form-control rounded-input" placeholder="Search product name" />
        </div>
        <ul id="cart" class="list-group mb-2"></ul>

        <div class="mb-2 small-muted">
          <div class="d-flex justify-content-between"><div>Subtotal:</div><div id="subtotal">0.00</div></div>
          <div class="d-flex justify-content-between"><div>Tax (12%):</div><div id="tax">0.00</div></div>
          <div class="d-flex justify-content-between fw-bold"><div>Total:</div><div id="total">0.00</div></div>
        </div>

        <div class="mb-2">
          <select id="payment_type" class="form-select rounded-input">
            <option value="cash">Cash</option>
            <option value="gcash">GCash (Ref No.)</option>
          </select>
        </div>
        
        <!-- Cash Payment Section -->
        <div class="mb-2" id="cash_payment_div">
          <div class="mb-2">
            <label>Amount Received</label>
            <input id="amount_received" type="number" class="form-control rounded-input" placeholder="Enter amount received" min="0" step="0.01" />
          </div>
          <div class="d-flex justify-content-between fw-bold">
            <div>Change:</div>
            <div id="change_amount">0.00</div>
          </div>
        </div>
        
        <!-- GCash Payment Section -->
        <div class="mb-2 d-none" id="gcash_ref_div">
          <input id="gcash_ref" class="form-control rounded-input" placeholder="Enter GCash Reference No." />
        </div>

        <div class="d-grid gap-2">
          <button id="payBtn" class="btn btn-cta">Complete Sale</button>
          <button id="clearBtn" class="btn btn-outline-secondary">Clear Cart</button>
        </div>
      </div>

      <div class="card p-3 pos-card">
        <h6>Last Receipt</h6>
        <div id="lastReceipt" class="receipt small"></div>
        <div class="mt-2"><button id="printLast" class="btn btn-sm btn-outline-secondary">Print Receipt</button></div>
      </div>
    </div>
  </div>
</div>

<!-- product options modal -->
<div class="modal fade" id="optionsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content rounded-3">
      <div class="modal-header">
        <h5 class="modal-title">Add to cart</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="modalProductName" class="mb-2 fw-bold"></div>
        <div class="mb-2">
          <label>Size</label>
          <select id="modalSize" class="form-select rounded-input">
            <option value="Small">Small (+0)</option>
            <option value="Medium">Medium (+10)</option>
            <option value="Large">Large (+20)</option>
          </select>
        </div>
        <div class="mb-2">
          <label>Sugar</label>
          <select id="modalSugar" class="form-select rounded-input">
            <option value="100%">100%</option>
            <option value="75%">75%</option>
            <option value="50%">50%</option>
            <option value="25%">25%</option>
            <option value="0%">0%</option>
          </select>
        </div>
        <div class="mb-2">
          <label>Qty</label>
          <input id="modalQty" type="number" value="1" class="form-control rounded-input" min="1"/>
        </div>
      </div>
      <div class="modal-footer">
        <button id="modalAddBtn" class="btn btn-primary">Add to Cart</button>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- printable -->
<div id="printable" style="display:none; padding:20px;">
  <div class="text-center">
    <h4>BrewPOS - Tiger Bubble Tea</h4>
    <div id="print_receipt"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/*
  JS wiring added:
  - Click product to open modal (size/sugar/qty)
  - Add to cart, render cart, inc/dec qty
  - Complete sale -> POST to api/orders.php -> on success show receipt and reload lists
  - Show/hide GCash ref input
  - Search filter for products
  Minimal changes only: all HTML and CSS preserved.
*/

const apiBase = 'api';
let products = [], cart = [], selectedProduct = null;
const sizePriceDelta = { 'Small': 0, 'Medium': 10, 'Large': 20 };

// helpers
function $(s){ return document.querySelector(s); }
function format(n){ return parseFloat(n || 0).toFixed(2); }

// load products into POS area
async function loadProducts(){
  try {
    const res = await fetch(apiBase + '/products.php');
    const data = await res.json();
    if (data.error) { console.error('products API error', data); return; }
    products = data;
    renderProductGrid(products);
  } catch (e) {
    console.error('loadProducts error', e);
  }
}

function renderProductGrid(list){
  const container = document.getElementById('products');
  container.innerHTML = '';
  list.forEach(p=>{
    const col = document.createElement('div');
    col.className = 'col-6 col-md-4';
    
    // Check if product is out of stock
    const isOutOfStock = p.stock_quantity <= 0;
    const stockStatus = isOutOfStock ? 'Out of Stock' : `Stock: ${p.stock_quantity}`;
    const stockClass = isOutOfStock ? 'text-danger' : 'text-success';
    const cardClass = isOutOfStock ? 'product-card disabled' : 'product-card';
    
    col.innerHTML = `
      <div class="p-2 ${cardClass} h-100" data-id="${p.id}" role="button">
        <div><strong>${p.name}</strong></div>
        <div class="small-muted">₱${format(p.price)}</div>
        <div class="small-muted ${stockClass}">${stockStatus}</div>
      </div>`;
    
    // add click handler to open modal (only if in stock)
    if (!isOutOfStock) {
      col.querySelector('.product-card').addEventListener('click', ()=> openOptionsModal(p));
    } else {
      col.querySelector('.product-card').addEventListener('click', ()=> {
        alert(`${p.name} is out of stock! Please restock before selling.`);
      });
    }
    container.appendChild(col);
  });
}

// open modal and set defaults
function openOptionsModal(product){
  selectedProduct = product;
  $('#modalProductName').innerText = product.name + ' — ₱' + format(product.price);
  $('#modalSize').value = 'Small';
  $('#modalSugar').value = '100%';
  $('#modalQty').value = 1;
  const modal = new bootstrap.Modal(document.getElementById('optionsModal'));
  modal.show();
}

// add selected item to cart
$('#modalAddBtn').addEventListener('click', ()=>{
  if (!selectedProduct) return;
  
  // Check if product is out of stock
  if (selectedProduct.stock_quantity <= 0) {
    alert(`${selectedProduct.name} is out of stock! Please restock before selling.`);
    return;
  }
  
  const size = $('#modalSize').value;
  const sugar = $('#modalSugar').value;
  const qty = parseInt($('#modalQty').value) || 1;
  
  // Check if requested quantity exceeds available stock
  if (qty > selectedProduct.stock_quantity) {
    alert(`Insufficient stock! Available: ${selectedProduct.stock_quantity}, Requested: ${qty}`);
    return;
  }
  
  const basePrice = parseFloat(selectedProduct.price) || 0;
  const unitPrice = basePrice + (sizePriceDelta[size] || 0);
  const item = {
    product_id: selectedProduct.id,
    name: selectedProduct.name,
    unit_price: unitPrice,
    quantity: qty,
    cup_size: size,
    sugar_level: sugar,
    total_price: parseFloat(unitPrice * qty)
  };
  cart.push(item);
  renderCart();
  // hide modal
  const modalEl = document.getElementById('optionsModal');
  if (bootstrap.Modal.getInstance(modalEl)) bootstrap.Modal.getInstance(modalEl).hide();
});

// render cart UI
function renderCart(){
  const list = $('#cart');
  list.innerHTML = '';
  let subtotal = 0;
  cart.forEach((c, idx) => {
    subtotal += c.total_price;
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-start';
    li.innerHTML = `
      <div>
        <div><strong>${c.name}</strong></div>
        <div class="small-muted">${c.cup_size} • ${c.sugar_level}</div>
        <div class="small-muted">₱${format(c.unit_price)} × ${c.quantity}</div>
      </div>
      <div class="text-end">
        <div>₱${format(c.total_price)}</div>
        <div class="mt-1">
          <button class="btn btn-sm btn-outline-secondary me-1" onclick="dec(${idx})">-</button>
          <button class="btn btn-sm btn-outline-secondary" onclick="inc(${idx})">+</button>
        </div>
      </div>`;
    list.appendChild(li);
  });
  $('#subtotal').innerText = format(subtotal);
  const tax = subtotal * 0.12;
  $('#tax').innerText = format(tax);
  const total = subtotal + tax;
  $('#total').innerText = format(total);
  
  // Update change calculation when cart changes
  updateChangeCalculation(total);
}

// Calculate and display change
function updateChangeCalculation(total) {
  const amountReceived = parseFloat($('#amount_received').value) || 0;
  const change = amountReceived - total;
  $('#change_amount').textContent = format(change >= 0 ? change : 0);
  
  // Highlight if insufficient payment
  if (change < 0) {
    $('#change_amount').classList.add('text-danger');
  } else {
    $('#change_amount').classList.remove('text-danger');
  }
}

// expose inc/dec globally for inline onclick
window.inc = function(i){
  cart[i].quantity++;
  cart[i].total_price = cart[i].unit_price * cart[i].quantity;
  renderCart();
};
window.dec = function(i){
  cart[i].quantity--;
  if (cart[i].quantity <= 0) cart.splice(i,1);
  else cart[i].total_price = cart[i].unit_price * cart[i].quantity;
  renderCart();
};

// clear cart
$('#clearBtn').addEventListener('click', ()=>{ 
  cart = []; 
  renderCart(); 
  $('#amount_received').value = '';
  $('#change_amount').textContent = '0.00';
  $('#change_amount').classList.remove('text-danger');
});

// show/hide payment fields based on payment type
$('#payment_type').addEventListener('change', (e)=>{
  const v = e.target.value;
  const cashDiv = document.getElementById('cash_payment_div');
  const gcashDiv = document.getElementById('gcash_ref_div');
  
  if (v === 'cash') {
    cashDiv.classList.remove('d-none');
    gcashDiv.classList.add('d-none');
  } else {
    cashDiv.classList.add('d-none');
    gcashDiv.classList.remove('d-none');
  }
});

// Update change when amount received changes
$('#amount_received').addEventListener('input', () => {
  const total = parseFloat($('#total').innerText) || 0;
  updateChangeCalculation(total);
});

// Complete Sale -> send order to backend
$('#payBtn').addEventListener('click', async ()=>{
  if (cart.length === 0) { alert('Cart is empty'); return; }

  // Validate payment
  const payment_type = $('#payment_type').value;
  const total = parseFloat($('#total').innerText) || 0;
  
  if (payment_type === 'cash') {
    const amountReceived = parseFloat($('#amount_received').value) || 0;
    if (amountReceived < total) {
      alert('Insufficient payment. Please enter the correct amount received.');
      return;
    }
    if (amountReceived <= 0) {
      alert('Please enter the amount received from customer.');
      return;
    }
  } else if (payment_type === 'gcash') {
    const gcash_ref = $('#gcash_ref').value || null;
    if (!gcash_ref) {
      alert('Please enter GCash reference number.');
      return;
    }
  }

  // prepare payload
  const subtotal = parseFloat($('#subtotal').innerText) || 0;
  const tax = parseFloat($('#tax').innerText) || 0;
  const amount_received = payment_type === 'cash' ? parseFloat($('#amount_received').value) : total;
  const change = payment_type === 'cash' ? amount_received - total : 0;
  const gcash_ref = $('#gcash_ref').value || null;

  // attach each cart item with required fields
  const items = cart.map(it => ({
    product_id: it.product_id,
    name: it.name,
    unit_price: it.unit_price,
    quantity: it.quantity,
    total_price: it.total_price,
    cup_size: it.cup_size,
    sugar_level: it.sugar_level
  }));

  const payload = { 
    items, 
    subtotal, 
    tax, 
    total, 
    payment_type, 
    amount_received,
    change,
    gcash_ref 
  };

  try {
    const res = await fetch(apiBase + '/orders.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data && data.success) {
      // show simple receipt
      let receipt = `Order: ${data.order_number}\nDate: ${new Date().toLocaleString()}\n--------------------\n`;
      items.forEach(it => {
        receipt += `${it.name} ${it.cup_size} ${it.sugar_level} x${it.quantity}  ₱${format(it.total_price)}\n`;
      });
      receipt += `--------------------\nSubtotal: ₱${format(subtotal)}\nTax: ₱${format(tax)}\nTotal: ₱${format(total)}\nPayment: ${payment_type.toUpperCase()}\n`;
      if (payment_type === 'cash') {
        receipt += `Amount Received: ₱${format(amount_received)}\nChange: ₱${format(change)}\n`;
      } else if (gcash_ref) {
        receipt += `GCash Ref: ${gcash_ref}\n`;
      }
      receipt += `\nThank you!`;

      $('#lastReceipt').innerText = receipt;
      cart = [];
      renderCart();
      $('#amount_received').value = '';
      $('#change_amount').textContent = '0.00';
      $('#change_amount').classList.remove('text-danger');
      
      // reload products to show updated stock
      await loadProducts();
    } else {
      alert('Sale failed: ' + (data.error || 'unknown'));
      console.error('orders response', data);
    }
  } catch (err) {
    console.error('complete sale error', err);
    alert('Sale failed, see console for details.');
  }
});

// Print last receipt
$('#printLast').addEventListener('click', ()=>{
  const printable = document.getElementById('printable');
  printable.style.display = 'block';
  document.getElementById('print_receipt').innerText = $('#lastReceipt').innerText || '';
  window.print();
  printable.style.display = 'none';
});

// search filter
$('#search').addEventListener('input', (e)=>{
  const q = (e.target.value || '').toLowerCase();
  const filtered = products.filter(p => (p.name || '').toLowerCase().includes(q) || (p.sku||'').toLowerCase().includes(q));
  renderProductGrid(filtered);
});

// clock
function tick(){ document.getElementById('clock').innerText = new Date().toLocaleString(); }
tick(); setInterval(tick, 1000);

// init
window.addEventListener('DOMContentLoaded', ()=>{
  loadProducts();
});
</script>
</body></html>