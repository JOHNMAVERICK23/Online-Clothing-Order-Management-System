<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'customer') {
    header('Location: login_register.php');
    exit;
}

// Check if order success flag is set
if (!isset($_SESSION['order_success']) || $_SESSION['order_success'] !== true) {
    header('Location: shop.php');
    exit;
}

$order_number = $_SESSION['order_number'] ?? $_GET['order'] ?? '';

// Clear the success flag to prevent refresh from showing same page
unset($_SESSION['order_success']);
unset($_SESSION['order_number']);

$user_name = $_SESSION['user_name'] ?? 'Customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful - Elegance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #ef233c;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #e9ecef;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* SUCCESS CARD - COMPACT VERSION */
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 2.5rem;
            text-align: center;
            width: 100%;
            max-width: 550px;
            animation: fadeIn 0.6s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #4361ee, #7209b7);
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        .success-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.8rem;
            color: white;
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.25);
            animation: bounce 1s ease infinite alternate;
        }

        @keyframes bounce {
            0% { transform: scale(0.95); }
            100% { transform: scale(1.05); }
        }

        .success-card h1 {
            font-size: 2.2rem;
            color: #22c55e;
            margin-bottom: 0.8rem;
            font-weight: 700;
        }

        .success-card p {
            color: var(--gray);
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            max-width: 450px;
            margin-left: auto;
            margin-right: auto;
        }

        .order-details {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            padding: 1.8rem;
            margin: 1.8rem 0;
            text-align: center;
        }

        .order-number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .order-number i {
            color: #22c55e;
            font-size: 1.8rem;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .info-item {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .info-label i {
            font-size: 0.9rem;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .confirmation-message {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            text-align: left;
            font-size: 0.9rem;
        }

        .confirmation-message i {
            color: #f59e0b;
            font-size: 1.2rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .confirmation-message div {
            flex: 1;
        }

        .confirmation-message strong {
            color: #92400e;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.9rem 1.8rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            min-width: 160px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(67, 97, 238, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(34, 197, 94, 0.3);
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .success-card {
                padding: 2rem 1.5rem;
                max-width: 450px;
            }
            
            .success-card h1 {
                font-size: 1.8rem;
            }
            
            .order-number {
                font-size: 1.4rem;
            }
            
            .order-info {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }
            
            .info-item {
                padding: 0.9rem;
            }
            
            .button-group {
                flex-direction: column;
                gap: 0.8rem;
            }
            
            .btn {
                min-width: 100%;
                padding: 0.9rem 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .success-card {
                padding: 1.8rem 1.2rem;
                border-radius: 15px;
            }
            
            .success-icon {
                width: 75px;
                height: 75px;
                font-size: 2.2rem;
                margin-bottom: 1.2rem;
            }
            
            .success-card h1 {
                font-size: 1.6rem;
                margin-bottom: 0.5rem;
            }
            
            .success-card p {
                font-size: 0.95rem;
                margin-bottom: 1.2rem;
            }
            
            .order-details {
                padding: 1.5rem 1.2rem;
                margin: 1.5rem 0;
            }
            
            .order-number {
                font-size: 1.3rem;
                gap: 8px;
            }
            
            .order-number i {
                font-size: 1.5rem;
            }
            
            .confirmation-message {
                padding: 0.9rem;
                font-size: 0.85rem;
                gap: 0.6rem;
                margin-top: 1.2rem;
            }
            
            .confirmation-message i {
                font-size: 1.1rem;
            }
            
            .button-group {
                margin-top: 1.5rem;
            }
        }

        @media (max-width: 350px) {
            .success-card {
                padding: 1.5rem 1rem;
            }
            
            .success-icon {
                width: 65px;
                height: 65px;
                font-size: 2rem;
            }
            
            .success-card h1 {
                font-size: 1.4rem;
            }
            
            .order-number {
                font-size: 1.2rem;
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Success Content -->
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>Order Successful!</h1>
        <p>Thank you for your purchase. Your order has been confirmed and will be processed shortly.</p>
        
        <div class="order-details">
            <div class="order-number">
                <i class="fas fa-receipt"></i>
                <?php echo htmlspecialchars($order_number); ?>
            </div>
            
            <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 0;">
                Confirmation sent to your email. Track order in "My Orders".
            </p>
            
            <div class="order-info">
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Delivery</span>
                    </div>
                    <div class="info-value">3-5 Days</div>
                </div>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-credit-card"></i>
                        <span>Payment</span>
                    </div>
                    <div class="info-value" style="color: #22c55e;">Confirmed</div>
                </div>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-truck"></i>
                        <span>Status</span>
                    </div>
                    <div class="info-value" style="color: #f59e0b;">Processing</div>
                </div>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-user"></i>
                        <span>Customer</span>
                    </div>
                    <div class="info-value"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
            </div>
        </div>
        
        <div class="confirmation-message">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Note:</strong> Keep this order number for reference. Customer service may contact you for delivery details.
            </div>
        </div>
        
        <div class="button-group">
            <a href="shop.php" class="btn btn-primary">
                <i class="fas fa-store"></i> Continue Shopping
            </a>
            <a href="orders.php" class="btn btn-success">
                <i class="fas fa-clipboard-list"></i> My Orders
            </a>
        </div>
    </div>

    <script>
        // Auto-hide confirmation message after 10 seconds
        setTimeout(() => {
            const confirmationMessage = document.querySelector('.confirmation-message');
            if (confirmationMessage) {
                confirmationMessage.style.transition = 'opacity 0.5s ease';
                confirmationMessage.style.opacity = '0.6';
            }
        }, 10000);
    </script>
</body>
</html>