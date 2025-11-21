<?php
/**
 * ScholarSeek Diagnostic Page
 * Check database connection and system status
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSeek - Diagnostics</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #0f172a;
            margin-bottom: 30px;
            text-align: center;
        }
        .status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        .status.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .status strong {
            display: block;
            margin-bottom: 5px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 0.9rem;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 ScholarSeek Diagnostics</h1>

        <?php
        // Test 1: PHP Version
        echo '<div class="status success">';
        echo '<strong>✅ PHP Version</strong>';
        echo 'PHP ' . phpversion() . ' is running';
        echo '</div>';

        // Test 2: Database Connection
        $host = "sql100.infinityfree.com";
        $user = "if0_40468565";
        $pass = "mFSh9ALReEiE";
        $db = "if0_40468565_scholarseek_db";
        $port = 3306;

        $conn = @mysqli_connect($host, $user, $pass, $db, $port);

        if ($conn) {
            echo '<div class="status success">';
            echo '<strong>✅ Database Connection</strong>';
            echo 'Successfully connected to MySQL database';
            echo '</div>';

            // Test 3: Check tables
            $tables_query = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?";
            $stmt = mysqli_prepare($conn, $tables_query);
            mysqli_stmt_bind_param($stmt, "s", $db);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $table_count = mysqli_num_rows($result);

            if ($table_count > 0) {
                echo '<div class="status success">';
                echo '<strong>✅ Database Tables</strong>';
                echo 'Found ' . $table_count . ' tables in database';
                echo '</div>';
            } else {
                echo '<div class="status warning">';
                echo '<strong>⚠️ No Tables Found</strong>';
                echo 'Database is empty. You need to run setup.php to create tables.';
                echo '</div>';
            }

            mysqli_close($conn);
        } else {
            echo '<div class="status error">';
            echo '<strong>❌ Database Connection Failed</strong>';
            echo 'Error: ' . mysqli_connect_error();
            echo '</div>';

            echo '<div class="info-box">';
            echo '<strong>Troubleshooting:</strong><br>';
            echo '1. Verify database credentials are correct<br>';
            echo '2. Check that database name is correct (currently: ' . htmlspecialchars($db) . ')<br>';
            echo '3. Ensure MySQL is running on InfinityFree<br>';
            echo '4. Contact InfinityFree support if issues persist';
            echo '</div>';
        }
        ?>

        <div class="info-box">
            <strong>📋 Current Configuration:</strong><br>
            Host: <code><?php echo htmlspecialchars($host); ?></code><br>
            User: <code><?php echo htmlspecialchars($user); ?></code><br>
            Database: <code><?php echo htmlspecialchars($db); ?></code><br>
            Port: <code><?php echo htmlspecialchars($port); ?></code>
        </div>

        <div class="info-box">
            <strong>⚠️ Important:</strong><br>
            If database name shows <code>if0_40468565_XXX</code>, replace XXX with your actual database name from InfinityFree Control Panel.
        </div>
    </div>
</body>
</html>
