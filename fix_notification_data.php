<?php
/**
 * Fix Notification Data - Clear or migrate notifications with missing user_id
 * Run this script once to clean up notifications
 */

require_once 'db_connect.php';

echo "Checking notifications with user_id = 0 or NULL...\n";

// Check for notifications with user_id = 0 or NULL
$check_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = 0 OR user_id IS NULL";
$result = mysqli_query($conn, $check_query);
$row = mysqli_fetch_assoc($result);
$bad_count = $row['count'];

echo "Found " . $bad_count . " notifications with missing/invalid user_id\n";

if ($bad_count > 0) {
    echo "\nDeleting notifications with missing user_id...\n";
    
    // Delete notifications with user_id = 0 or NULL
    $delete_query = "DELETE FROM notifications WHERE user_id = 0 OR user_id IS NULL";
    if (mysqli_query($conn, $delete_query)) {
        echo "✓ Deleted " . mysqli_affected_rows($conn) . " notifications\n";
    } else {
        echo "✗ Error deleting notifications: " . mysqli_error($conn) . "\n";
    }
}

// Show remaining notifications
echo "\nRemaining notifications by user:\n";
$stats_query = "
    SELECT user_id, user_type, COUNT(*) as count 
    FROM notifications 
    WHERE user_id > 0 
    GROUP BY user_id, user_type 
    ORDER BY user_id
";
$result = mysqli_query($conn, $stats_query);

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- User ID: " . $row['user_id'] . " (Type: " . $row['user_type'] . ") - " . $row['count'] . " notifications\n";
    }
} else {
    echo "No valid notifications found\n";
}

echo "\n✓ Notification data has been cleaned!\n";
echo "You can now delete this file (fix_notification_data.php)\n";

mysqli_close($conn);
?>
