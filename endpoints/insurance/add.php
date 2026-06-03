<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/inputvalidation.php';

header('Content-Type: application/json');

// Valid insurance types
const INSURANCE_TYPES = [
    'vehicle',
    'health',
    'term',
    'endowment',
    'pension',
    'professional_indemnity',
    'home',
    'travel',
    'life',
    'ulip',
    'other',
];

$isEdit = isset($_POST['id']) && $_POST['id'] != "";

// Core fields
$name = validate($_POST['name'] ?? '');
$insuranceType = validate($_POST['insurance_type'] ?? 'other');
$policyNumber = validate($_POST['policy_number'] ?? '');
$insurerName = validate($_POST['insurer_name'] ?? '');
$coverageType = validate($_POST['coverage_type'] ?? '');
$coverageAmount = floatval($_POST['coverage_amount'] ?? 0);
$sumAssured = floatval($_POST['sum_assured'] ?? 0);
$premium = floatval($_POST['premium'] ?? 0);
$currencyId = intval($_POST['currency_id'] ?? 1);
$cycle = intval($_POST['cycle'] ?? 4);
$frequency = intval($_POST['frequency'] ?? 1);
$renewalDate = $_POST['renewal_date'] ?? null;
$startDate = $_POST['start_date'] ?? null;
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
$logo = "";

// Validate insurance type
if (!in_array($insuranceType, INSURANCE_TYPES)) {
    $insuranceType = 'other';
}

// Handle logo upload from URL
if (!empty($_POST['logo_url'])) {
    $logoUrl = validate($_POST['logo_url']);
    // Attempt to download and save logo from URL (reuse subscription logo logic)
    $uploadDir = '../../images/uploads/logos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    // Reuse the logo download logic from subscription add endpoint
    require_once '../../endpoints/subscription/add.php';
    // If logo download succeeded, $logo will be set
}

// Handle uploaded logo file
if (!empty($_FILES['logo']['name'])) {
    $fileType = mime_content_type($_FILES['logo']['tmp_name']);
    if (strpos($fileType, 'image') !== false) {
        require_once '../../endpoints/subscription/add.php';
        // $logo will be set by resizeAndUploadLogo
    }
}

if ($isEdit) {
    $id = intval($_POST['id']);

    $sql = "UPDATE insurances SET
                name = :name,
                insurance_type = :insuranceType,
                policy_number = :policyNumber,
                insurer_name = :insurerName,
                coverage_type = :coverageType,
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
                inactive = :inactive,
                updated_at = CURRENT_TIMESTAMP";

    if ($logo != "") {
        $sql .= ", logo = :logo";
    }

    $sql .= " WHERE id = :id AND user_id = :userId";
} else {
    $sql = "INSERT INTO insurances (
                name, insurance_type, logo, policy_number, insurer_name, coverage_type,
                coverage_amount, sum_assured, premium, currency_id, cycle, frequency,
                renewal_date, start_date, payment_method_id, payer_user_id, notify,
                notify_days_before, portal_url, portal_username, portal_password,
                nominee, beneficiary, notes, url, auto_renew, inactive, user_id
            ) VALUES (
                :name, :insuranceType, :logo, :policyNumber, :insurerName, :coverageType,
                :coverageAmount, :sumAssured, :premium, :currencyId, :cycle, :frequency,
                :renewalDate, :startDate, :paymentMethodId, :payerUserId, :notify,
                :notifyDaysBefore, :portalUrl, :portalUsername, :portalPassword,
                :nominee, :beneficiary, :notes, :url, :autoRenew, :inactive, :userId
            )";
}

$stmt = $db->prepare($sql);
$stmt->bindParam(':name', $name, SQLITE3_TEXT);
$stmt->bindParam(':insuranceType', $insuranceType, SQLITE3_TEXT);
if ($logo != "") {
    $stmt->bindParam(':logo', $logo, SQLITE3_TEXT);
}
$stmt->bindParam(':policyNumber', $policyNumber, SQLITE3_TEXT);
$stmt->bindParam(':insurerName', $insurerName, SQLITE3_TEXT);
$stmt->bindParam(':coverageType', $coverageType, SQLITE3_TEXT);
$stmt->bindParam(':coverageAmount', $coverageAmount, SQLITE3_FLOAT);
$stmt->bindParam(':sumAssured', $sumAssured, SQLITE3_FLOAT);
$stmt->bindParam(':premium', $premium, SQLITE3_FLOAT);
$stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
$stmt->bindParam(':cycle', $cycle, SQLITE3_INTEGER);
$stmt->bindParam(':frequency', $frequency, SQLITE3_INTEGER);
$stmt->bindParam(':renewalDate', $renewalDate, SQLITE3_TEXT);
$stmt->bindParam(':startDate', $startDate, SQLITE3_TEXT);
$stmt->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
$stmt->bindParam(':payerUserId', $payerUserId, SQLITE3_INTEGER);
$stmt->bindParam(':notify', $notify, SQLITE3_INTEGER);
$stmt->bindParam(':notifyDaysBefore', $notifyDaysBefore, SQLITE3_INTEGER);
$stmt->bindParam(':portalUrl', $portalUrl, SQLITE3_TEXT);
$stmt->bindParam(':portalUsername', $portalUsername, SQLITE3_TEXT);
$stmt->bindParam(':portalPassword', $portalPassword, SQLITE3_TEXT);
$stmt->bindParam(':nominee', $nominee, SQLITE3_TEXT);
$stmt->bindParam(':beneficiary', $beneficiary, SQLITE3_TEXT);
$stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
$stmt->bindParam(':url', $url, SQLITE3_TEXT);
$stmt->bindParam(':autoRenew', $autoRenew, SQLITE3_INTEGER);
$stmt->bindParam(':inactive', $inactive, SQLITE3_INTEGER);
if ($isEdit) {
    $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
}
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    $success = ["status" => "Success"];
    $action = $isEdit ? "updated" : "added";
    $success["message"] = translate("insurance_$action", $i18n);
    echo json_encode($success);
} else {
    echo json_encode(["status" => "Error", "message" => translate("error", $i18n) . ": " . $db->lastErrorMsg()]);
}
$db->close();
?>