<?php
$password = 'admin123';
$hashed = password_hash($password, PASSWORD_BCRYPT);
echo "Hashed Password: " . $hashed;
?>