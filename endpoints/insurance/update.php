<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/inputvalidation.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'Error', 'message' => translate('invalid_id', $i18n)]);
    $db->close();
    exit;
}

// Core fields
$name = validate($_POST['name'] ?? '');
$insuranceType = validate($_POST['insurance_type'] ?? 'other');
$policyNumber = validate($_POST['policy_number'] ?? '');
$insurerName = validate($_POST['insurer_name'] ?? '');
$coverageAmount = floatval($_POST['coverage_amount'] ?? 0);
$sumAssured = floatval($_POST['sum_assured'] ?? 0);
$premium = floatval($_POST['premium'] ?? 0);
$currencyId = intval($_POST['currency_id'] ?? 1);
$cycle = intval($_POST['cycle'] ?? 4);
$frequency = intval($_POST['frequency'] ?? 1);
$renewalDate = $_POST['renewal_date'] ?: null;
$startDate = $_POST['start_date'] ?: null;
$paymentMethodId = intval($_POST['payment_method_id'] ?? 0) ?: null;
$payerUserId = intval($_POST['payer_user_id'] ?? 0) ?: null;
$notify = isset($_POST['notify']) ? 1 : 0;
$notifyDaysBefore = intval($_POST['notify_days_before'] ?? 30);
$portalUrl = validate($_POST['portal_url'] ?? '');
$portalUsername = validate($_POST['portal_username'] ?? '');
$portalPassword = validate($_POST['portal_password'] ?? '');
$nominee = validate($_POST['nominee'] ?? '');
$beneficiary = validate($_POST['beneficiary'] ?? '');
$notes = validate($_POST['notes'] ?? '');
$url = validate($_POST['url'] ?? '');
$autoRenew = isset($_POST['auto_renew']) ? 1 : 0;
$inactive = isset($_POST['inactive']) ? 1 : 0;

// Validation
if (empty($name)) {
    echo json_encode(['status' => 'Error', 'message' => translate('name_required', $i18n)]);
    $db->close();
    exit;
}

if ($insuranceType && !in_array($insuranceType, ['vehicle','health','term','endowment','pension','professional_indemnity','home','travel','life','ulip','other'])) {
    echo json_encode(['status' => 'Error', 'message' => translate('invalid_insurance_type', $i18n)]);
    $db->close();
    exit;
}

// Check ownership
$checkQuery = "SELECT id FROM insurances WHERE id = :id AND user_id = :userId";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bindValue(':id', $id, SQLITE3_INTEGER);
$checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$checkResult = $checkStmt->execute();
if (!$checkResult->fetchArray()) {
    echo json_encode(['status' => 'Error', 'message' => translate('not_found', $i18n)]);
    $db->close();
    exit;
}

// Handle logo upload
$logoName = null;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../images/uploads/logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $tmpName = $_FILES['logo']['tmp_name'];
    $originalName = basename($_FILES['logo']['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    if (in_array($ext, $allowedExts)) {
        $newName = uniqid('ins_logo_') . '.' . $ext;
        $targetPath = $uploadDir . $newName;
        if (move_uploaded_file($tmpName, $targetPath)) {
            // Delete old logo
            $oldQuery = "SELECT logo FROM insurances WHERE id = :id";
            $oldStmt = $db->prepare($oldQuery);
            $oldStmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $oldResult = $oldStmt->execute();
            if ($oldRow = $oldResult->fetchArray()) {
                $oldLogo = $uploadDir . $oldRow['logo'];
                if ($oldRow['logo'] && file_exists($oldLogo)) {
                    @unlink($oldLogo);
                }
            }
            $logoName = $newName;
        }
    }
}

// Build UPDATE query
$query = "UPDATE insurances SET
    name = :name,
    insurance_type = :insuranceType,
    policy_number = :policyNumber,
    insurer_name = :insurerName,
    coverage_amount = :coverageAmount,
    sum_assured = :sumAssured,
    premium = :premium,
    currency_id = :currencyId,
    cycle = :cycle,
    frequency = :frequency,
    renewal_date = :renewalDate,
    start_date = :startDate,
    payment_method_id = :paymentMethodId,
    payer_user_id = :payerUserId,
    notify = :notify,
    notify_days_before = :notifyDaysBefore,
    portal_url = :portalUrl,
    portal_username = :portalUsername,
    portal_password = :portalPassword,
    nominee = :nominee,
    beneficiary = :beneficiary,
    notes = :notes,
    url = :url,
    auto_renew = :autoRenew,
    inactive = :inactive";

if ($logoName) {
    $query .= ", logo = :logo";
}

$query .= " WHERE id = :id AND user_id = :userId";

$stmt = $db->prepare($query);
$stmt->bindValue(':name', $name, SQLITE3_TEXT);
$stmt->bindValue(':insuranceType', $insuranceType, SQLITE3_TEXT);
$stmt->bindValue(':policyNumber', $policyNumber, SQLITE3_TEXT);
$stmt->bindValue(':insurerName', $insurerName, SQLITE3_TEXT);
$stmt->bindValue(':coverageAmount', $coverageAmount, SQLITE3_FLOAT);
$stmt->bindValue(':sumAssured', $sumAssured, SQLITE3_FLOAT);
$stmt->bindValue(':premium', $premium, SQLITE3_FLOAT);
$stmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
$stmt->bindValue(':cycle', $cycle, SQLITE3_INTEGER);
$stmt->bindValue(':frequency', $frequency, SQLITE3_INTEGER);
$stmt->bindValue(':renewalDate', $renewalDate, SQLITE3_TEXT);
$stmt->bindValue(':startDate', $startDate, SQLITE3_TEXT);
$stmt->bindValue(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
$stmt->bindValue(':payerUserId', $payerUserId, SQLITE3_INTEGER);
$stmt->bindValue(':notify', $notify, SQLITE3_INTEGER);
$stmt->bindValue(':notifyDaysBefore', $notifyDaysBefore, SQLITE3_INTEGER);
$stmt->bindValue(':portalUrl', $portalUrl, SQLITE3_TEXT);
$stmt->bindValue(':portalUsername', $portalUsername, SQLITE3_TEXT);
$stmt->bindValue(':portalPassword', $portalPassword, SQLITE3_TEXT);
$stmt->bindValue(':nominee', $nominee, SQLITE3_TEXT);
$stmt->bindValue(':beneficiary', $beneficiary, SQLITE3_TEXT);
$stmt->bindValue(':notes', $notes, SQLITE3_TEXT);
$stmt->bindValue(':url', $url, SQLITE3_TEXT);
$stmt->bindValue(':autoRenew', $autoRenew, SQLITE3_INTEGER);
$stmt->bindValue(':inactive', $inactive, SQLITE3_INTEGER);
if ($logoName) {
    $stmt->bindValue(':logo', $logoName, SQLITE3_TEXT);
}
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

$result = $stmt->execute();

if ($result) {
    echo json_encode(['status' => 'Success', 'message' => translate('insurance_updated', $i18n)]);
} else {
    echo json_encode(['status' => 'Error', 'message' => translate('error_updating_insurance', $i18n)]);
}

$db->close();
?>