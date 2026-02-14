<?php
session_start();

// Clear the wishlist
if (isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Redirect back to wishlist page
header('Location: wishlist.php');
exit;
?>