<?php
/**
 * Insurance Renewal Notifications Cronjob
 * Sends renewal reminders for insurance policies before their renewal date.
 * Mirrors the subscription notification system but for insurances.
 *
 * Usage: php sendinsurancerenewalnotifications.php
 * Recommended: run daily via cron
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';
require __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
require __DIR__ . '/../../libs/PHPMailer/SMTP.php';
require __DIR__ . '/../../libs/PHPMailer/Exception.php';
require __DIR__ . '/../../includes/currency_formatter.php';
require 'settimezone.php';

$date = new DateTime('now');
if (php_sapi_name() == 'cli') {
    echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . " - Insurance Renewal Notifications\n";
    echo str_repeat("=", 50) . "\n";
}

// Get all user IDs
$query = "SELECT id, username, email FROM user";
$stmt = $db->prepare($query);
$usersResult = $stmt->execute();

while ($user = $usersResult->fetchArray(SQLITE3_ASSOC)) {
    $userId = $user['id'];
    $username = $user['username'];
    $userEmail = $user['email'];

    if (php_sapi_name() !== 'cli') {
        echo "For user: $username<br/><br/>";
    }

    $emailNotificationsEnabled = false;
    $telegramNotificationsEnabled = false;
    $gotifyNotificationsEnabled = false;
    $webhookNotificationsEnabled = false;
    $pushoverNotificationsEnabled = false;
    $pushplusNotificationsEnabled = false;
    $discordNotificationsEnabled = false;
    $ntfyNotificationsEnabled = false;
    $mattermostNotificationsEnabled = false;
    $serverchanNotificationsEnabled = false;

    $email = [];
    $discord = [];
    $gotify = [];
    $webhook = [];
    $pushover = [];
    $pushplus = [];
    $mattermost = [];
    $ntfy = [];
    $serverchan = [];

    // Get notification settings
    $query = "SELECT days FROM notification_settings WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $notifResult = $stmt2->execute();
    $defaultDays = 30;
    if ($row = $notifResult->fetchArray(SQLITE3_ASSOC)) {
        $defaultDays = $row['days'];
    }

    // ---- Load all notification channels ----
    $channelQuery = "SELECT * FROM email_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($channelQuery);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $emailNotificationsEnabled = $row['enabled'];
        $email = ['smtp_address' => $row['smtp_address'], 'smtp_port' => $row['smtp_port'],
            'encryption' => $row['encryption'], 'smtp_username' => $row['smtp_username'],
            'smtp_password' => $row['smtp_password'], 'from_email' => $row['from_email'] ?: 'wallos@wallosapp.com',
            'other_emails' => $row['other_emails']];
    }

    $query = "SELECT * FROM telegram_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $telegramNotificationsEnabled = $row['enabled'];
        $telegramChatId = $row['chat_id'];
        $telegramBotToken = $row['bot_token'];
    }

    $query = "SELECT * FROM gotify_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $gotifyNotificationsEnabled = $row['enabled'];
        $gotify = ['url' => $row['url'], 'token' => $row['token']];
    }

    $query = "SELECT * FROM webhook_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $webhookNotificationsEnabled = $row['enabled'];
        $webhook = ['url' => $row['url'], 'method' => $row['request_method'],
            'headers' => $row['headers'], 'payload' => $row['payload']];
    }

    $query = "SELECT * FROM discord_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $discordNotificationsEnabled = $row['enabled'];
        $discord = ['webhook_url' => $row['webhook_url'], 'bot_username' => $row['bot_username'],
            'bot_avatar_url' => $row['bot_avatar_url']];
    }

    $query = "SELECT * FROM pushover_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pushoverNotificationsEnabled = $row['enabled'];
        $pushover = ['user_key' => $row['user_key'], 'token' => $row['token']];
    }

    $query = "SELECT * FROM pushplus_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pushplusNotificationsEnabled = $row['enabled'];
        $pushplus = ['token' => $row['token']];
    }

    $query = "SELECT * FROM mattermost_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $mattermostNotificationsEnabled = $row['enabled'];
        $mattermost = ['webhook_url' => $row['webhook_url'], 'bot_username' => $row['bot_username'],
            'bot_icon_emoji' => $row['bot_icon_emoji']];
    }

    $query = "SELECT * FROM ntfy_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $ntfyNotificationsEnabled = $row['enabled'];
        $ntfy = ['host' => $row['host'], 'topic' => $row['topic'], 'headers' => $row['headers']];
    }

    $query = "SELECT * FROM serverchan_notifications WHERE user_id = :userId";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt2->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $serverchanNotificationsEnabled = $row['enabled'];
        $serverchan = ['sendkey' => $row['sendkey']];
    }

    $anyNotificationEnabled = $emailNotificationsEnabled || $telegramNotificationsEnabled
        || $gotifyNotificationsEnabled || $webhookNotificationsEnabled
        || $discordNotificationsEnabled || $pushoverNotificationsEnabled
        || $pushplusNotificationsEnabled || $mattermostNotificationsEnabled
        || $ntfyNotificationsEnabled || $serverchanNotificationsEnabled;

    if (!$anyNotificationEnabled) {
        if (php_sapi_name() !== 'cli') {
            echo "No notifications enabled for $username<br/>";
        }
        continue;
    }

    // ---- Get insurances due for notification ----
    $query = "SELECT * FROM insurances WHERE user_id = :userId AND notify = 1 AND inactive = 0 AND renewal_date IS NOT NULL AND renewal_date != ''";
    $stmt2 = $db->prepare($query);
    $stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $insResult = $stmt2->execute();

    $notifiedCount = 0;

    while ($ins = $insResult->fetchArray(SQLITE3_ASSOC)) {
        $notifyDays = $ins['notify_days_before'] ?? $defaultDays;
        $renewalDate = new DateTime($ins['renewal_date']);
        $today = new DateTime('today');
        $daysDiff = (int)$today->diff($renewalDate)->days;
        $isPast = $renewalDate < $today;

        // Only notify if renewal is within notify_days_before range (or overdue)
        if (!$isPast && $daysDiff > $notifyDays) {
            continue; // Too far in the future
        }

        // Get currency
        $currencyCode = 'INR';
        $currencySymbol = '₹';
        $query = "SELECT code, symbol FROM currencies WHERE id = :currId AND user_id = :userId";
        $stmt3 = $db->prepare($query);
        $stmt3->bindValue(':currId', $ins['currency_id'] ?? 1, SQLITE3_INTEGER);
        $stmt3->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $currResult = $stmt3->execute();
        if ($currRow = $currResult->fetchArray(SQLITE3_ASSOC)) {
            $currencyCode = $currRow['code'] ?? 'INR';
            $currencySymbol = $currRow['symbol'] ?? '₹';
        }

        $formattedCoverage = CurrencyFormatter::format($ins['coverage_amount'] ?? 0, $currencyCode);
        $formattedPremium = CurrencyFormatter::format($ins['premium'] ?? 0, $currencyCode);
        $formattedRenewalDate = $renewalDate->format('M j, Y');

        $insTypeLabel = $ins['insurance_type'] ?? 'Insurance';

        // Build notification message
        $subject = "Insurance Renewal Reminder: {$ins['name']}";
        $body = "Insurance: {$ins['name']}\n";
        $body .= "Type: " . ucfirst(str_replace('_', ' ', $insTypeLabel)) . "\n";
        if ($ins['policy_number']) $body .= "Policy #: {$ins['policy_number']}\n";
        if ($ins['insurer_name']) $body .= "Insurer: {$ins['insurer_name']}\n";
        $body .= "Renewal Date: $formattedRenewalDate\n";
        if ($ins['coverage_amount']) $body .= "Coverage: $formattedCoverage\n";
        if ($ins['premium']) $body .= "Premium: $formattedPremium\n";
        if ($ins['nominee']) $body .= "Nominee: {$ins['nominee']}\n";
        if ($isPast) $body .= "\n⚠️ This policy is OVERDUE for renewal!\n";

        $daysText = $isPast ? "OVERDUE by " . abs($daysDiff) . " day(s)" : "in $daysDiff day(s)";

        $shortMsg = "🔔 {$ins['name']} renews $daysText";

        // Send email
        if ($emailNotificationsEnabled) {
            sendInsuranceEmailNotification($email, $userEmail, $subject, $body, $ins, $currencyCode, $currencySymbol);
        }

        // Send Telegram
        if ($telegramNotificationsEnabled) {
            sendInsuranceTelegramNotification($telegramBotToken, $telegramChatId, $shortMsg, $ins, $currencySymbol);
        }

        // Send Discord
        if ($discordNotificationsEnabled) {
            sendInsuranceDiscordNotification($discord, $ins, $formattedRenewalDate, $currencySymbol, $isPast);
        }

        // Send Gotify
        if ($gotifyNotificationsEnabled) {
            sendInsuranceGotifyNotification($gotify, $shortMsg, $body);
        }

        // Send Webhook
        if ($webhookNotificationsEnabled) {
            sendInsuranceWebhookNotification($webhook, $ins, $shortMsg);
        }

        // Send Pushover
        if ($pushoverNotificationsEnabled) {
            sendInsurancePushoverNotification($pushover, $subject, $shortMsg);
        }

        // Send PushPlus
        if ($pushplusNotificationsEnabled) {
            sendInsurancePushplusNotification($pushplus, $subject, $body);
        }

        // Send Mattermost
        if ($mattermostNotificationsEnabled) {
            sendInsuranceMattermostNotification($mattermost, $ins, $formattedRenewalDate, $currencySymbol, $isPast);
        }

        // Send Ntfy
        if ($ntfyNotificationsEnabled) {
            sendInsuranceNtfyNotification($ntfy, $shortMsg);
        }

        // Send Serverchan
        if ($serverchanNotificationsEnabled) {
            sendInsuranceServerchanNotification($serverchan, $ins, $formattedRenewalDate, $currencySymbol, $isPast);
        }

        if (php_sapi_name() !== 'cli') {
            echo "Notified for: {$ins['name']} ($daysText)<br/>";
        }
        $notifiedCount++;
    }

    if (php_sapi_name() === 'cli') {
        echo "  User $username: $notifiedCount insurance notification(s) sent\n";
    }
}

if (php_sapi_name() === 'cli') {
    echo "\nDone.\n";
}

// ─── Channel-specific senders ────────────────────────────────────────────

function sendInsuranceEmailNotification($email, $toEmail, $subject, $body, $ins, $currencyCode, $currencySymbol) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $email['smtp_address'];
        $mail->SMTPAuth = true;
        $mail->Username = $email['smtp_username'];
        $mail->Password = $email['smtp_password'];
        $mail->Port = $email['smtp_port'];

        if ($email['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($email['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->setFrom($email['from_email'], 'Wallos Insurance Tracker');
        $recipients = array_filter(array_merge([$toEmail], explode(';', $email['other_emails'])));
        foreach ($recipients as $r) {
            $mail->addAddress(trim($r));
        }
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        if (php_sapi_name() === 'cli') {
            echo "    Email error: " . $e->getMessage() . "\n";
        }
    }
}

function sendInsuranceTelegramNotification($botToken, $chatId, $message, $ins, $currencySymbol) {
    $text = "$message\n";
    $text .= "━━━━━━━━━━━━━━━━━━━━\n";
    $text .= "🏷️ Type: " . ucfirst(str_replace('_', ' ', $ins['insurance_type'] ?? 'Insurance')) . "\n";
    if ($ins['policy_number']) $text .= "📋 Policy: {$ins['policy_number']}\n";
    if ($ins['insurer_name']) $text .= "🏢 Insurer: {$ins['insurer_name']}\n";
    if ($ins['coverage_amount']) $text .= "🛡️ Cover: {$currencySymbol}" . number_format($ins['coverage_amount']) . "\n";
    if ($ins['premium']) $text .= "💰 Premium: {$currencySymbol}" . number_format($ins['premium']) . "\n";
    if ($ins['nominee']) $text .= "👤 Nominee: {$ins['nominee']}\n";

    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    @file_get_contents($url, false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => json_encode($data)]
    ]));
}

function sendInsuranceDiscordNotification($discord, $ins, $renewalDate, $currencySymbol, $isPast) {
    $embed = [
        'title' => '🔔 Insurance Renewal: ' . $ins['name'],
        'color' => $isPast ? 15158332 : 3066993,
        'fields' => [
            ['name' => 'Type', 'value' => ucfirst(str_replace('_', ' ', $ins['insurance_type'] ?? 'Insurance')), 'inline' => true],
            ['name' => 'Renewal Date', 'value' => $renewalDate, 'inline' => true],
        ]
    ];
    if ($ins['policy_number']) $embed['fields'][] = ['name' => 'Policy #', 'value' => $ins['policy_number'], 'inline' => true];
    if ($ins['insurer_name']) $embed['fields'][] = ['name' => 'Insurer', 'value' => $ins['insurer_name'], 'inline' => true];
    if ($ins['coverage_amount']) $embed['fields'][] = ['name' => 'Coverage', 'value' => $currencySymbol . number_format($ins['coverage_amount']), 'inline' => true];
    if ($ins['premium']) $embed['fields'][] = ['name' => 'Premium', 'value' => $currencySymbol . number_format($ins['premium']) . '/yr', 'inline' => true];
    if ($isPast) $embed['description'] = '⚠️ **OVERDUE** - Please renew immediately!';
    $payload = ['embeds' => [$embed]];
    if ($discord['bot_username']) $payload['username'] = $discord['bot_username'];
    if ($discord['bot_avatar_url']) $payload['avatar_url'] = $discord['bot_avatar_url'];
    @file_get_contents($discord['webhook_url'], false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => json_encode($payload)]
    ]));
}

function sendInsuranceGotifyNotification($gotify, $title, $message) {
    $payload = json_encode(['title' => $title, 'message' => $message, 'priority' => 5]);
    @file_get_contents($gotify['url'] . '/message?token=' . $gotify['token'], false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => $payload]
    ]));
}

function sendInsuranceWebhookNotification($webhook, $ins, $message) {
    $variables = [
        '{{name}}' => $ins['name'],
        '{{type}}' => $ins['insurance_type'] ?? '',
        '{{policy_number}}' => $ins['policy_number'] ?? '',
        '{{insurer}}' => $ins['insurer_name'] ?? '',
        '{{renewal_date}}' => $ins['renewal_date'] ?? '',
        '{{coverage_amount}}' => $ins['coverage_amount'] ?? '',
        '{{premium}}' => $ins['premium'] ?? '',
        '{{nominee}}' => $ins['nominee'] ?? '',
        '{{message}}' => $message,
    ];
    $payload = str_replace(array_keys($variables), array_values($variables), $webhook['payload'] ?: '{"text": "{{message}}"}');
    $headers = ['Content-Type: application/json'];
    if ($webhook['headers']) {
        foreach (explode(',', $webhook['headers']) as $h) {
            $parts = explode(':', $h, 2);
            if (count($parts) == 2) $headers[] = trim($parts[0]) . ': ' . trim($parts[1]);
        }
    }
    @file_get_contents($webhook['url'], false, stream_context_create([
        'http' => ['method' => $webhook['method'] ?: 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload]
    ]));
}

function sendInsurancePushoverNotification($pushover, $title, $message) {
    $payload = http_build_query([
        'token' => $pushover['token'], 'user' => $pushover['user_key'],
        'title' => $title, 'message' => $message, 'priority' => 1
    ]);
    @file_get_contents('https://api.pushover.net/1/messages.json', false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload]
    ]));
}

function sendInsurancePushplusNotification($pushplus, $title, $body) {
    $payload = json_encode(['token' => $pushplus['token'], 'title' => $title, 'content' => $body]);
    @file_get_contents('http://www.pushplus.plus/send', false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => $payload]
    ]));
}

function sendInsuranceMattermostNotification($mattermost, $ins, $renewalDate, $currencySymbol, $isPast) {
    $text = "### 🔔 Insurance Renewal: {$ins['name']}\n\n";
    $text .= "| Field | Value |\n|:------|:-------|\n";
    $text .= "| Type | " . ucfirst(str_replace('_', ' ', $ins['insurance_type'] ?? 'Insurance')) . " |\n";
    $text .= "| Renewal Date | $renewalDate |\n";
    if ($ins['policy_number']) $text .= "| Policy # | {$ins['policy_number']} |\n";
    if ($ins['insurer_name']) $text .= "| Insurer | {$ins['insurer_name']} |\n";
    if ($ins['coverage_amount']) $text .= "| Coverage | {$currencySymbol}" . number_format($ins['coverage_amount']) . " |\n";
    if ($ins['premium']) $text .= "| Premium | {$currencySymbol}" . number_format($ins['premium']) . "/yr |\n";
    if ($isPast) $text .= "\n**⚠️ OVERDUE** |\n";
    $payload = json_encode(['text' => $text]);
    @file_get_contents($mattermost['webhook_url'], false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => $payload]
    ]));
}

function sendInsuranceNtfyNotification($ntfy, $message) {
    $url = rtrim($ntfy['host'], '/') . '/' . $ntfy['topic'];
    $headers = ['Content-Type: text/plain'];
    if ($ntfy['headers']) {
        foreach (explode(',', $ntfy['headers']) as $h) {
            $parts = explode(':', $h, 2);
            if (count($parts) == 2) $headers[] = trim($parts[0]) . ': ' . trim($parts[1]);
        }
    }
    @file_get_contents($url, false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $message]
    ]));
}

function sendInsuranceServerchanNotification($serverchan, $ins, $renewalDate, $currencySymbol, $isPast) {
    $text = "🔔 {$ins['name']} renews $renewalDate\n";
    if ($ins['policy_number']) $text .= "Policy: {$ins['policy_number']}\n";
    if ($ins['insurer_name']) $text .= "Insurer: {$ins['insurer_name']}\n";
    if ($ins['coverage_amount']) $text .= "Cover: {$currencySymbol}" . number_format($ins['coverage_amount']) . "\n";
    if ($isPast) $text .= "\n⚠️ **OVERDUE**\n";
    $payload = http_build_query(['sendkey' => $serverchan['sendkey'], 'text' => $text]);
    @file_get_contents('https://sct2.ft07.com/send', false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload]
    ]));
}

$db->close();