<?php
session_start();
require_once '../PROCESS/db_config.php';

$orderId = $_GET['order_id'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

if (!$orderId) {
    header('Location: ../index.php');
    exit;
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Order not found');
}

$order = $result->fetch_assoc();

// Handle payment submission
$paymentProcessed = false;
$paymentError = '';
$paymentSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'process_payment') {
        $method = $_POST['payment_method'] ?? '';
        $referenceNumber = trim($_POST['reference_number'] ?? '');
        
        if ($method === 'gcash' || $method === 'paymaya') {
            if (empty($referenceNumber)) {
                $paymentError = 'Reference number is required for ' . strtoupper($method);
            } else {
                // Verify payment (simulate)
                $isVerified = verifyPayment($method, $referenceNumber, $order['total_amount']);
                
                if ($isVerified) {
                    // Update order payment status
                    $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
                    $updateStmt->bind_param("i", $orderId);
                    $updateStmt->execute();
                    
                    $paymentSuccess = true;
                } else {
                    $paymentError = 'Payment verification failed. Please check your reference number.';
                }
            }
        } elseif ($method === 'cod') {
            // COD - Just update status
            $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'pending', status = 'processing' WHERE id = ?");
            $updateStmt->bind_param("i", $orderId);
            $updateStmt->execute();
            
            $paymentSuccess = true;
        }
        
        if ($paymentSuccess) {
            header("Location: confirmation.php?order_id=$orderId&payment_method=$method");
            exit;
        }
    }
}

function verifyPayment($method, $refNumber, $amount) {
    // In real implementation, this would call GCash/PayMaya API
    // For now, we simulate by checking if refNumber is not empty
    
    if ($method === 'gcash') {
        // GCash: Ref number format is usually 10-12 digits
        return preg_match('/^\d{10,12}$/', $refNumber);
    } elseif ($method === 'paymaya') {
        // PayMaya: Ref number format is usually alphanumeric
        return preg_match('/^[A-Z0-9]{8,}$/i', $refNumber);
    }
    
    return false;
}

// Get order items
$itemsStmt = $conn->prepare("SELECT oi.*, p.product_name FROM order_items oi 
                            LEFT JOIN products p ON oi.product_id = p.id 
                            WHERE oi.order_id = ?");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Clothing Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 0; }
        .payment-container { max-width: 900px; margin: 0 auto; }
        .payment-card { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .payment-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 30px; }
        .order-number { font-size: 14px; color: #666; text-transform: uppercase; letter-spacing: 2px; }
        .payment-method-card { border: 2px solid #e0e0e0; border-radius: 10px; padding: 20px; margin-bottom: 15px; cursor: pointer; transition: all 0.3s; }
        .payment-method-card:hover { border-color: #667eea; background: #f8f9ff; }
        .payment-method-card.active { border-color: #667eea; background: #f8f9ff; }
        .payment-method-icon { font-size: 30px; margin-bottom: 10px; }
        .amount-display { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; text-align: center; }
        .amount-label { font-size: 14px; opacity: 0.9; }
        .amount-value { font-size: 40px; font-weight: 700; }
        .order-summary { background: #f9f9f9; border-radius: 10px; padding: 20px; margin-bottom: 30px; }
        .summary-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .summary-item:last-child { border-bottom: none; }
        .summary-item.total { font-weight: 700; font-size: 18px; color: #667eea; }
        .instruction-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .instruction-box strong { color: #0c5460; }
        .error-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 20px; border-radius: 5px; color: #721c24; }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-card">
            <!-- Header -->
            <div class="payment-header">
                <p class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                <h1 class="mb-0">Complete Your Payment</h1>
            </div>

            <!-- Amount Display -->
            <div class="amount-display">
                <div class="amount-label">Total Amount to Pay</div>
                <div class="amount-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
            </div>

            <!-- Error Message -->
            <?php if ($paymentError): ?>
                <div class="error-box">
                    <strong><i class="bi bi-exclamation-circle"></i> Payment Error</strong><br>
                    <?php echo htmlspecialchars($paymentError); ?>
                </div>
            <?php endif; ?>

            <!-- Order Summary -->
            <div class="order-summary">
                <h5 class="mb-3">Order Items</h5>
                <?php foreach ($items as $item): ?>
                    <div class="summary-item">
                        <span><?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                        <span>₱<?php echo number_format($item['subtotal'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="summary-item" style="border-bottom: 2px solid #ddd; padding-top: 15px;">
                    <span>Subtotal</span>
                    <span>₱<?php echo number_format($order['total_amount'] - 100 - ($order['total_amount'] - 100) * 0.12, 2); ?></span>
                </div>
                <div class="summary-item">
                    <span>Shipping</span>
                    <span>₱100.00</span>
                </div>
                <div class="summary-item">
                    <span>Tax (12%)</span>
                    <span>₱<?php echo number_format(($order['total_amount'] - 100 - ($order['total_amount'] - 100) * 0.12) * 0.12, 2); ?></span>
                </div>
                <div class="summary-item total">
                    <span>Total</span>
                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>

            <!-- Payment Methods -->
            <h5 class="mb-3">Select Payment Method</h5>
            <form method="POST" id="paymentForm">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="payment_method" id="paymentMethodInput" value="gcash">

                <!-- GCash -->
                <div class="payment-method-card active" onclick="selectPaymentMethod(this, 'gcash')">
                    <input type="radio" name="payment_radio" value="gcash" checked style="display: none;">
                    <div class="payment-method-icon" style="color: #2563EB;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <h6 class="mb-2">GCash</h6>
                    <p class="text-muted mb-0" style="font-size: 13px;">Pay directly via GCash app. Please provide your reference number.</p>
                </div>

                <!-- PayMaya -->
                <div class="payment-method-card" onclick="selectPaymentMethod(this, 'paymaya')">
                    <input type="radio" name="payment_radio" value="paymaya" style="display: none;">
                    <div class="payment-method-icon" style="color: #FF6B35;">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <h6 class="mb-2">PayMaya</h6>
                    <p class="text-muted mb-0" style="font-size: 13px;">Pay using PayMaya wallet or credit card. Provide your reference number.</p>
                </div>

                <!-- Cash on Delivery -->
                <div class="payment-method-card" onclick="selectPaymentMethod(this, 'cod')">
                    <input type="radio" name="payment_radio" value="cod" style="display: none;">
                    <div class="payment-method-icon" style="color: #10B981;">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <h6 class="mb-2">Cash on Delivery</h6>
                    <p class="text-muted mb-0" style="font-size: 13px;">Pay when your order arrives. No advance payment needed.</p>
                </div>

                <!-- Reference Number Input (for GCash/PayMaya) -->
                <div id="referenceNumberSection" class="mt-4">
                    <div class="instruction-box">
                        <strong id="instructionTitle"><i class="bi bi-info-circle"></i> GCash Payment Instructions</strong>
                        <div id="instructionText" style="margin-top: 10px; font-size: 14px;">
                            1. Open your GCash app<br>
                            2. Select "Send Money"<br>
                            3. Enter the business account number: <strong>0917-123-4567</strong><br>
                            4. Enter amount: ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                            5. Complete the transaction and copy your reference number<br>
                            6. Paste the reference number below
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" id="refLabel">GCash Reference Number *</label>
                        <input type="text" class="form-control form-control-lg" id="referenceNumber" name="reference_number" placeholder="Enter your 10-12 digit reference number" required>
                        <small class="text-muted">You can find this in your GCash app transaction history</small>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg" id="payBtn">
                        <i class="bi bi-check-circle"></i> Confirm Payment
                    </button>
                    <a href="checkout.php" class="btn btn-outline-secondary btn-lg">Back to Checkout</a>
                </div>
            </form>

            <!-- Payment Info -->
            <div class="mt-4 pt-4 border-top text-center text-muted" style="font-size: 13px;">
                <p><i class="bi bi-shield-check"></i> Your payment information is secure and encrypted</p>
                <p>For payment issues, contact us at support@clothingstore.com or call 0917-123-4567</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPaymentMethod(element, method) {
            // Remove active class from all cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Add active class to clicked card
            element.classList.add('active');
            document.getElementById('paymentMethodInput').value = method;

            const refSection = document.getElementById('referenceNumberSection');
            const refInput = document.getElementById('referenceNumber');
            const payBtn = document.getElementById('payBtn');
            const refLabel = document.getElementById('refLabel');
            const instructionTitle = document.getElementById('instructionTitle');
            const instructionText = document.getElementById('instructionText');

            if (method === 'cod') {
                refSection.style.display = 'none';
                refInput.removeAttribute('required');
                payBtn.innerHTML = '<i class="bi bi-check-circle"></i> Place Order';
            } else if (method === 'gcash') {
                refSection.style.display = 'block';
                refInput.setAttribute('required', '');
                refInput.placeholder = 'Enter your 10-12 digit reference number';
                refLabel.textContent = 'GCash Reference Number *';
                instructionTitle.innerHTML = '<i class="bi bi-info-circle"></i> GCash Payment Instructions';
                instructionText.innerHTML = `
                    1. Open your GCash app<br>
                    2. Select "Send Money"<br>
                    3. Enter the business account number: <strong>0917-123-4567</strong><br>
                    4. Enter amount: ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                    5. Complete the transaction and copy your reference number<br>
                    6. Paste the reference number below
                `;
                payBtn.innerHTML = '<i class="bi bi-check-circle"></i> Pay with GCash';
            } else if (method === 'paymaya') {
                refSection.style.display = 'block';
                refInput.setAttribute('required', '');
                refInput.placeholder = 'Enter your PayMaya reference number';
                refLabel.textContent = 'PayMaya Reference Number *';
                instructionTitle.innerHTML = '<i class="bi bi-info-circle"></i> PayMaya Payment Instructions';
                instructionText.innerHTML = `
                    1. Open PayMaya app or website<br>
                    2. Go to "Pay Bills" or "Send Money"<br>
                    3. Enter business account: <strong>paymaya@clothingstore.com</strong><br>
                    4. Enter amount: ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                    5. Complete payment and save the reference number<br>
                    6. Paste the reference number below
                `;
                payBtn.innerHTML = '<i class="bi bi-check-circle"></i> Pay with PayMaya';
            }
        }

        // Initial setup
        document.addEventListener('DOMContentLoaded', function() {
            selectPaymentMethod(
                document.querySelector('.payment-method-card.active'), 
                'gcash'
            );
        });
    </script>
</body>
</html>