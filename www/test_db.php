<?php
$host = 'db';
$dbname = 'heatpumpmonitor';
$username = 'heatpumpmonitor';
$password = 'heatpumpmonitor';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create a test table
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    )");

    // Insert a sample record
    $pdo->exec("INSERT INTO test_table (name) VALUES ('Sample Data')");

    // Fetch the record
    $stmt = $pdo->query("SELECT * FROM test_table");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($result);
    echo "</pre>";

    // Drop the test table
    $pdo->exec("DROP TABLE test_table");

    echo "CRUD operations successful!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
