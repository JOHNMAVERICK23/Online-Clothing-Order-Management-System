<?php
require_once 'db_config.php';

// Sample products data
$sampleProducts = [
    [
        'product_name' => 'Classic White T-Shirt',
        'description' => '100% Cotton, comfortable and breathable',
        'category' => 'T-Shirts',
        'price' => 299.99,
        'quantity' => 50,
        'size' => 'S,M,L,XL',
        'color' => 'White',
        'image_url' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=500&h=500&fit=crop',
        'status' => 'active'
    ],
    [
        'product_name' => 'Blue Denim Jeans',
        'description' => 'Stylish denim jeans, perfect for casual wear',
        'category' => 'Jeans',
        'price' => 899.99,
        'quantity' => 30,
        'size' => '28,30,32,34',
        'color' => 'Blue',
        'image_url' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=500&h=500&fit=crop',
        'status' => 'active'
    ],
    [
        'product_name' => 'Summer Dress',
        'description' => 'Light and flowy summer dress',
        'category' => 'Dresses',
        'price' => 599.99,
        'quantity' => 25,
        'color' => 'Yellow',
        'image_url' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=500&h=500&fit=crop',
        'status' => 'active'
    ],
    [
        'product_name' => 'Leather Jacket',
        'description' => 'Genuine leather jacket for men',
        'category' => 'Jackets',
        'price' => 1999.99,
        'quantity' => 15,
        'size' => 'M,L,XL',
        'color' => 'Black,Brown',
        'image_url' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500&h=500&fit=crop',
        'status' => 'active'
    ],
    [
        'product_name' => 'Running Shoes',
        'description' => 'Comfortable running shoes for all-day wear',
        'category' => 'Shoes',
        'price' => 1299.99,
        'quantity' => 40,
        'size' => '7,8,9,10,11',
        'color' => 'White,Black',
        'image_url' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=500&h=500&fit=crop',
        'status' => 'active'
    ],
    [
        'product_name' => 'Formal Shirt',
        'description' => 'Premium cotton formal shirt',
        'category' => 'Shirts',
        'price' => 499.99,
        'quantity' => 35,
        'size' => 'S,M,L,XL',
        'color' => 'White,Blue',
        'image_url' => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=500&h=500&fit=crop',
        'status' => 'active'
    ],
    [
        'product_name' => 'Casual Pants',
        'description' => 'Comfortable casual pants for everyday wear',
        'category' => 'Pants',
        'price' => 399.99,
        'quantity' => 45,
        'size' => '28,30,32,34,36',
        'color' => 'Black,Gray',
        'image_url' => 'https://images.unsplash.com/photo-1586790170083-2f9ceadc732d?w=500&h=500&fit=crop',
        'status' => 'active'
    ],
    [
        'product_name' => 'Skirt',
        'description' => 'Elegant skirt for office or casual wear',
        'category' => 'Skirts',
        'price' => 349.99,
        'quantity' => 20,
        'size' => 'XS,S,M,L',
        'color' => 'Red,Blue,Black',
        'image_url' => 'https://images.unsplash.com/photo-1594633313593-ba5ccbacffb1?w=500&h=500&fit=crop',
        'status' => 'active'
    ]
];

// Insert sample products
$inserted = 0;
foreach ($sampleProducts as $product) {
    $stmt = $conn->prepare("
        INSERT INTO products (product_name, description, category, price, quantity, size, color, image_url, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->bind_param(
        "sssdissss",
        $product['product_name'],
        $product['description'],
        $product['category'],
        $product['price'],
        $product['quantity'],
        $product['size'] ?? '',
        $product['color'] ?? '',
        $product['image_url'],
        $product['status']
    );
    
    if ($stmt->execute()) {
        $inserted++;
    }
}

echo "<h1>Sample Products Added</h1>";
echo "<p>Successfully added $inserted sample products.</p>";
echo "<p><a href='../index.html'>View Store</a></p>";

$conn->close();
?>