<?php
$email = "emekaogoh14@gmail.com";
$password = "NACOSfcai@";

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Admin Login Credentials</h2>";
echo "<strong>Email:</strong> " . htmlspecialchars($email) . "<br><br>";
echo "< strong>Plain Password:</strong> " . htmlspecialchars($password) . "<br><br>";
echo "<strong>Password Hash:</strong><br>";
echo "<textarea rows='4' cols='100'>" . $hash . "</textarea>";
?>

