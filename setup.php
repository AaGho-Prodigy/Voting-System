<?php

$host = 'localhost';
$dbname = 'online_voting_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "✓ Database created or already exists<br>";
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_registration_number VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            is_candidate TINYINT(1) DEFAULT 0,
            has_voted TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ Users table created<br>";
    } catch (Exception $e) {
        echo "ERROR creating users table: " . $e->getMessage() . "<br>";
    }
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS candidates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            bio TEXT,
            platform TEXT,
            image_path VARCHAR(255),
            approved TINYINT(1) DEFAULT 0,
            votes INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_candidate (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ Candidates table created<br>";
    } catch (Exception $e) {
        echo "ERROR creating candidates table: " . $e->getMessage() . "<br>";
    }
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voter_id INT NOT NULL,
            candidate_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            UNIQUE KEY unique_vote (voter_id, candidate_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ Votes table created<br>";
    } catch (Exception $e) {
        echo "ERROR creating votes table: " . $e->getMessage() . "<br>";
    }
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS allowed_students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_registration_number VARCHAR(255) NOT NULL UNIQUE,
            used TINYINT(1) DEFAULT 0,
            used_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ Allowed Students table created<br>";
    } catch (Exception $e) {
        echo "ERROR creating allowed_students table: " . $e->getMessage() . "<br>";
    }
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ Admin table created<br>";
    } catch (Exception $e) {
        echo "ERROR creating admin table: " . $e->getMessage() . "<br>";
    }
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            value LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ Settings table created<br>";
    } catch (Exception $e) {
        echo "ERROR creating settings table: " . $e->getMessage() . "<br>";
    }
    
    $check = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '$dbname' AND table_name = 'settings'");
    if (!$check || !$check->fetch()) {
        die("ERROR: Settings table creation failed or not visible<br>");
    }
    
    try {
        $pdo->prepare("INSERT IGNORE INTO settings (name, value) VALUES (?, ?)")->execute(['election_status', 'inactive']);
        $pdo->prepare("INSERT IGNORE INTO settings (name, value) VALUES (?, ?)")->execute(['election_start', NULL]);
        $pdo->prepare("INSERT IGNORE INTO settings (name, value) VALUES (?, ?)")->execute(['election_end', NULL]);
        echo "✓ Default settings initialized<br>";
    } catch (Exception $e) {
        echo "Warning: Could not insert default settings: " . $e->getMessage() . "<br>";
    }
    
    $admin_password = password_hash('admin', PASSWORD_BCRYPT);
    try {
        $pdo->prepare("INSERT IGNORE INTO admin (username, password, email) VALUES (?, ?, ?)")
            ->execute(['admin', $admin_password, 'admin@election.local']);
        echo "✓ Default admin account created (username: admin, password: admin)<br>";
    } catch (Exception $e) {
        echo "Warning: Could not create admin account: " . $e->getMessage() . "<br>";
    }
    
    echo "<br><strong>✓ Database setup completed successfully!</strong><br>";
    echo "You can now use the system.<br>";
    echo "<a href='index.php'>Return to Home</a>";
    
} catch (PDOException $e) {
    die("Setup Error: " . $e->getMessage());
}
?>
