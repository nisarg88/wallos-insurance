<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(["success" => false, "message" => translate('session_expired', $i18n)]);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : null;

if (!$id) {
    echo json_encode(["success" => false, "message" => translate("fields_missing", $i18n)]);
    exit;
}

$stmt = $db->prepare("DELETE FROM insurances WHERE id = :id AND user_id = :userId");
$stmt->bindParam(':id', $id, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    $changes = $db->changes();
    if ($changes > 0) {
        echo json_encode(["success" => true, "message" => translate("insurance_deleted", $i18n)]);
    } else {
        echo json_encode(["success" => false, "message" => translate("error_deleting_insurance", $i18n)]);
    }
} else {
    echo json_encode(["success" => false, "message" => translate("error_deleting_insurance", $i18n) . ": " . $db->lastErrorMsg()]);
}

$db->close();
?>