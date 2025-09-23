<?php
require_once 'config.php';
require_once 'JalaliDate.php';

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$jalali_date = $data['log_date'] ?? null;
$is_checked = $data['is_checked'] ?? null;

if (empty($jalali_date) || !isset($is_checked)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($jalali_date);
if (!$gregorian_date_obj) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit;
}
$gregorian_date = $gregorian_date_obj->format('Y-m-d');
$user_id = $_SESSION['id'];

try {
    if ($is_checked) {
        // Use REPLACE INTO to either insert a new row or update an existing one if a unique key conflicts.
        // We need a UNIQUE key on (user_id, log_date) for this to work as expected.
        // Let's assume the table has this constraint. If not, this is safer than INSERT IGNORE.
        $sql = "REPLACE INTO day_properties (user_id, log_date, day_type, status) VALUES (:user_id, :log_date, 'friday_work', 'approved')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':log_date' => $gregorian_date
        ]);
        echo json_encode(['success' => true, 'message' => 'Day marked as overtime.']);

    } else {
        // Delete the property if the box is unchecked
        $sql = "DELETE FROM day_properties WHERE user_id = :user_id AND log_date = :log_date AND day_type = 'friday_work'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':log_date' => $gregorian_date
        ]);
        echo json_encode(['success' => true, 'message' => 'Overtime marking removed.']);
    }
} catch (Exception $e) {
    error_log("Error in update_day_property.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>
