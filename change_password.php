<?php
$host = 'localhost';
$dbname = 'online_voting_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $new_password = 'Terrorblade97';
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE username = ?");
    $stmt->execute([$hashed_password, 'admin']);
    
    echo "✓ Admin password updated successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: Terrorblade97<br>";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
