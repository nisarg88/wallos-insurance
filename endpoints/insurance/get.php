<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(["success" => false, "message" => translate('session_expired', $i18n)]);
    exit;
}

$insuranceId = isset($_GET['id']) ? intval($_GET['id']) : null;

// Get single insurance
if ($insuranceId) {
    $sql = "SELECT i.*, c.code as currency_code, c.symbol as currency_symbol,
                   pm.name as payment_method_name, h.name as payer_name
            FROM insurances i
            LEFT JOIN currencies c ON i.currency_id = c.id
            LEFT JOIN payment_methods pm ON i.payment_method_id = pm.id
            LEFT JOIN household h ON i.payer_user_id = h.id
            WHERE i.id = :id AND i.user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $insuranceId, SQLITE3_INTEGER);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $insurance = $result->fetchArray(SQLITE3_ASSOC);

    if ($insurance) {
        echo json_encode(["success" => true, "insurance" => $insurance]);
    } else {
        echo json_encode(["success" => false, "message" => translate("error", $i18n)]);
    }
    $db->close();
    exit;
}

// Get all insurances with filters
$sql = "SELECT i.*, c.code as currency_code, c.symbol as currency_symbol,
               pm.name as payment_method_name, pm.icon as payment_method_icon,
               h.name as payer_name
        FROM insurances i
        LEFT JOIN currencies c ON i.currency_id = c.id
        LEFT JOIN payment_methods pm ON i.payment_method_id = pm.id
        LEFT JOIN household h ON i.payer_user_id = h.id
        WHERE i.user_id = :userId";
$params = [];
$params[':userId'] = $userId;

// Filter by type
if (isset($_GET['type']) && $_GET['type'] != '') {
    $sql .= " AND i.insurance_type = :type";
    $params[':type'] = $_GET['type'];
}

// Filter by inactive status
if (isset($_GET['state'])) {
    $state = intval($_GET['state']);
    $sql .= " AND i.inactive = :inactive";
    $params[':inactive'] = $state;
}

// Filter by member
if (isset($_GET['member']) && $_GET['member'] != '') {
    $memberIds = explode(',', $_GET['member']);
    $placeholders = array_map(function ($key) {
        return ":member" . $key;
    }, array_keys($memberIds));
    $sql .= " AND i.payer_user_id IN (" . implode(',', $placeholders) . ")";
    foreach ($memberIds as $key => $memberId) {
        $params[":member" . $key] = $memberId;
    }
}

// Sort
$sort = $_GET['sort'] ?? 'renewal_date';
$order = ($sort == 'premium' || $sort == 'coverage_amount' || $sort == 'sum_assured') ? 'DESC' : 'ASC';
$allowedSorts = ['name', 'id', 'renewal_date', 'premium', 'insurance_type', 'coverage_amount', 'sum_assured', 'inactive'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'renewal_date';
}

$sql .= " ORDER BY i.inactive ASC, i.$sort $order";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    if (strpos($key, 'userId') !== false) {
        $stmt->bindValue($key, $value, SQLITE3_INTEGER);
    } elseif (strpos($key, 'inactive') !== false) {
        $stmt->bindValue($key, $value, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue($key, $value, SQLITE3_TEXT);
    }
}

$result = $stmt->execute();
$insurances = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $insurances[] = $row;
}

// Get summary stats
$summarySql = "SELECT
    COUNT(*) as total_count,
    SUM(CASE WHEN inactive = 0 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN inactive = 1 THEN 1 ELSE 0 END) as inactive_count,
    SUM(CASE WHEN inactive = 0 THEN coverage_amount ELSE 0 END) as total_coverage,
    SUM(CASE WHEN inactive = 0 THEN sum_assured ELSE 0 END) as total_sum_assured,
    SUM(CASE WHEN inactive = 0 THEN premium ELSE 0 END) as total_premium
    FROM insurances WHERE user_id = :userId";
$summaryStmt = $db->prepare($summarySql);
$summaryStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$summaryResult = $summaryStmt->execute();
$summary = $summaryResult->fetchArray(SQLITE3_ASSOC);

// Get upcoming renewals (next 90 days)
$renewalSql = "SELECT i.*, c.symbol as currency_symbol
               FROM insurances i
               LEFT JOIN currencies c ON i.currency_id = c.id
               WHERE i.user_id = :userId
               AND i.inactive = 0
               AND i.renewal_date IS NOT NULL
               AND i.renewal_date != ''
               AND date(i.renewal_date) <= date('now', '+90 days')
               ORDER BY date(i.renewal_date) ASC
               LIMIT 10";
$renewalStmt = $db->prepare($renewalSql);
$renewalStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$renewalResult = $renewalStmt->execute();
$upcomingRenewals = [];
while ($row = $renewalResult->fetchArray(SQLITE3_ASSOC)) {
    $upcomingRenewals[] = $row;
}

echo json_encode([
    "success" => true,
    "insurances" => $insurances,
    "summary" => $summary,
    "upcoming_renewals" => $upcomingRenewals
]);

$db->close();
?>