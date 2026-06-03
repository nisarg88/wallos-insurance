<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';

function formatPrice($price, $currencyCode, $currencies)
{
    $formattedPrice = CurrencyFormatter::format($price, $currencyCode);
    if (strstr($formattedPrice, $currencyCode)) {
        $symbol = $currencyCode;

        foreach ($currencies as $currency) {

            if ($currency['code'] === $currencyCode) {
                if ($currency['symbol'] != "") {
                    $symbol = $currency['symbol'];
                }
                break;
            }
        }
        $formattedPrice = str_replace($currencyCode, $symbol, $formattedPrice);
    }

    return $formattedPrice;
}

function formatDate($date, $lang = 'en')
{
    $currentYear = date('Y');
    $dateYear = date('Y', strtotime($date));

    // Determine the date format based on whether the year matches the current year
    $dateFormat = ($currentYear == $dateYear) ? 'MMM d' : 'MMM yyyy';

    // Try to create an IntlDateFormatter; if it fails, fallback to 'en'
    try {
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );

        if (!$formatter) {
            throw new Exception('Failed to create IntlDateFormatter with language: ' . $lang);
        }
    } catch (Throwable $e) {
        $lang = 'en'; // Fallback to English on error
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );
    }

    // Format the date
    $formattedDate = $formatter->format(new DateTime($date));

    return $formattedDate;
}

// Get the first name of the user
$stmt = $db->prepare("SELECT username, firstname FROM user WHERE id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);
$first_name = $user['firstname'] ?? $user['username'] ?? '';

// Fetch the next 3 enabled subscriptions up for payment
$stmt = $db->prepare("SELECT id, logo, name, price, currency_id, next_payment, inactive FROM subscriptions WHERE user_id = :userId AND next_payment >= date('now') AND inactive = 0 ORDER BY next_payment ASC LIMIT 3");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$upcomingSubscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $upcomingSubscriptions[] = $row;
}

// Fetch enabled subscriptions with manual renewal that are overdue
$stmt = $db->prepare("SELECT id, logo, name, price, currency_id, next_payment, inactive, auto_renew FROM subscriptions WHERE user_id = :userId AND next_payment < date('now') AND auto_renew = 0 AND inactive = 0 ORDER BY next_payment ASC");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$overdueSubscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $overdueSubscriptions[] = $row;
}
$hasOverdueSubscriptions = !empty($overdueSubscriptions);

require_once 'includes/stats_calculations.php';

// ── Insurance data for dashboard ──────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) as cnt, SUM(coverage_amount) as total_coverage,
    MIN(renewal_date) as next_renewal FROM insurances
    WHERE user_id = :userId AND inactive = 0 AND renewal_date IS NOT NULL");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$insStats = $result->fetchArray(SQLITE3_ASSOC);
$activeInsurances = $insStats['cnt'] ?? 0;
$totalCoverage = $insStats['total_coverage'] ?? 0;
$nextInsuranceRenewal = $insStats['next_renewal'] ?? null;

// Get upcoming insurance renewals (next 3 within 90 days)
$stmt2 = $db->prepare("SELECT name, insurer_name, insurance_type, coverage_amount, premium,
    currency_id, renewal_date FROM insurances
    WHERE user_id = :userId AND inactive = 0 AND renewal_date >= date('now')
    AND renewal_date <= date('now', '+90 days')
    ORDER BY renewal_date ASC LIMIT 3");
$stmt2->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result2 = $stmt2->execute();
$upcomingInsurances = [];
while ($row = $result2->fetchArray(SQLITE3_ASSOC)) { $upcomingInsurances[] = $row; }

// Currency symbol for main currency
$mainCurrencySymbol = $currencies[$mainCurrencyId]['symbol'] ?? '₹';

// Get AI Recommendations for user
$stmt = $db->prepare("SELECT * FROM ai_recommendations WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$aiRecommendations = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $aiRecommendations[] = $row;
}

?>

<section class="contain dashboard">
    <?php
        if ($isAdmin && $settings['update_notification']) {
            if (!is_null($settings['latest_version'])) {
                $latestVersion = $settings['latest_version'];
                if (version_compare($version, $latestVersion) == -1) {
                    ?>
                    <div class="update-banner">
                    <?= translate('new_version_available', $i18n) ?>:
                        <span><a href="https://github.com/ellite/Wallos/releases/tag/<?= htmlspecialchars($latestVersion) ?>"
                        target="_blank" rel="noreferer">
                        <?= htmlspecialchars($latestVersion) ?>
                        </a></span>
                    </div>
                    <?php
                }
            }
        }
        if ($demoMode) {
            ?>
            <div class="demo-banner">
            Running in <b>Demo Mode</b>, certain actions and settings are disabled.<br>
            The database will be reset every 120 minutes.
            </div>
            <?php
        }
    ?>
    <h1><?= translate('hello', $i18n) ?> <?= htmlspecialchars($first_name) ?></h1>

    <?php
    // If there are overdue subscriptions, display them
    if ($hasOverdueSubscriptions) {
        ?>
        <div class="overdue-subscriptions">
            <h2><?= translate('overdue_renewals', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <?php

                    foreach ($overdueSubscriptions as $subscription) {
                        $subscriptionLogo = "images/uploads/logos/" . $subscription['logo'];
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionPrice = $subscription['price'];
                        $subscriptionCurrency = $subscription['currency_id'];
                        $subscriptionNextPayment = $subscription['next_payment'];
                        $subscriptionDisplayNextPayment = formatDate($subscriptionNextPayment, $lang);
                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);

                        ?>
                        <div class="subscription-item">
                            <?php
                            if (empty($subscription['logo'])) {
                                ?>
                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                <?php
                            } else {
                                ?>
                                <img src="<?= $subscriptionLogo ?>" alt="<?= $subscriptionName ?> logo"
                                    class="subscription-item-logo" title="<?= $subscriptionName ?>">
                                <?php
                            }
                            ?>
                            <div class="subscription-item-info">
                                <p class="subscription-item-date"> <?= $subscriptionDisplayNextPayment ?>
                                </p>
                                <p class="subscription-item-price"> <?= $subscriptionDisplayPrice ?></p>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>

    <div class="upcoming-subscriptions">
        <h2><?= translate('upcoming_payments', $i18n) ?></h2>
        <div class="dashboard-subscriptions-container">
            <div class="dashboard-subscriptions-list">
                <?php
                if (empty($upcomingSubscriptions)) {
                    ?>
                    <p><?= translate('no_upcoming_payments', $i18n) ?></p>
                    <?php
                } else {
                    foreach ($upcomingSubscriptions as $subscription) {
                        $subscriptionLogo = "images/uploads/logos/" . $subscription['logo'];
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionPrice = $subscription['price'];
                        $subscriptionCurrency = $subscription['currency_id'];
                        $subscriptionNextPayment = $subscription['next_payment'];
                        $subscriptionDisplayNextPayment = formatDate($subscriptionNextPayment, $lang);
                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);

                        ?>
                        <div class="subscription-item">
                            <?php
                            if (empty($subscription['logo'])) {
                                ?>
                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                <?php
                            } else {
                                ?>
                                <img src="<?= $subscriptionLogo ?>" alt="<?= $subscriptionName ?> logo"
                                    class="subscription-item-logo" title="<?= $subscriptionName ?>">
                                <?php
                            }
                            ?>
                            <div class="subscription-item-info">
                                <p class="subscription-item-date"> <?= $subscriptionDisplayNextPayment ?></p>
                                <p class="subscription-item-price"> <?= $subscriptionDisplayPrice ?></p>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>

        <?php if (!empty($aiRecommendations)) { ?>
            <div class="ai-recommendations">
                <!-- Insurance Summary -->
        <div class="dashboard-insurance-summary" id="dashboard-insurance-summary"<?php if($activeInsurances > 0): ?> style=""<?php endif; ?>>
            <h2><?= translate('insurance_summary', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <?php if ($activeInsurances > 0): ?>
                    <div class="insurance-stats-row">
                        <div class="insurance-stat-box">
                            <span class="stat-value"><?= $activeInsurances ?></span>
                            <span class="stat-label"><?= translate('active_insurances', $i18n) ?></span>
                        </div>
                        <div class="insurance-stat-box">
                            <span class="stat-value"><?= $mainCurrencySymbol ?><?= number_format($totalCoverage, 0) ?></span>
                            <span class="stat-label"><?= translate('total_coverage', $i18n) ?></span>
                        </div>
                        <?php if ($nextInsuranceRenewal): ?>
                        <div class="insurance-stat-box">
                            <span class="stat-value"><?= formatDate($nextInsuranceRenewal, $lang) ?></span>
                            <span class="stat-label"><?= translate('next_renewal', $i18n) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($upcomingInsurances)): ?>
                    <div class="insurance-upcoming-list">
                        <?php foreach ($upcomingInsurances as $ins): ?>
                        <?php $insCurrencySymbol = $currencies[$ins['currency_id']]['symbol'] ?? $mainCurrencySymbol; ?>
                        <div class="dashboard-subscription-card" onClick="window.location.href='insurances.php'">
                            <span class="dashboard-logo">
                                <?php include 'images/siteicons/svg/logo.php'; ?>
                            </span>
                            <span class="dashboard-name"><?= htmlspecialchars($ins['name']) ?></span>
                            <span class="dashboard-next">
                                <i class="fa-solid fa-rotate" title="<?= translate('renewal', $i18n) ?>"></i>
                                <?= formatDate($ins['renewal_date'], $lang) ?>
                            </span>
                            <span class="dashboard-price">
                                <?= $insCurrencySymbol ?><?= number_format($ins['premium'], 2) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <a href="insurances.php" class="dashboard-view-all"><?= translate('view_all_insurances', $i18n) ?> →</a>
                    <?php else: ?>
                    <div class="empty-dashboard-insurance">
                        <p><?= translate('no_active_insurances', $i18n) ?></p>
                        <a href="insurances.php" class="button"><i class="fa-solid fa-plus"></i> <?= translate('add_insurance', $i18n) ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h2><?= translate('ai_recommendations', $i18n) ?></h2>
                <div class="ai-recommendations-container">
                    <ul class="ai-recommendations-list">
                        <?php

                        foreach ($aiRecommendations as $key => $recommendation) { ?>
                            <li class="ai-recommendation-item" data-id="<?= $recommendation['id'] ?>">
                                <div class="ai-recommendation-header">
                                    <h3>
                                        <span><?= ($key + 1) . ". " ?></span>
                                        <?= htmlspecialchars($recommendation['title']) ?>
                                    </h3>
                                    <span class="item-arrow-down fa fa-caret-down"></span>
                                </div>
                                <p class="collapsible"><?= htmlspecialchars($recommendation['description']) ?></p>
                                <p class="ai-recommendation-savings">
                                    <?= htmlspecialchars($recommendation['savings']) ?>
                                    <span>
                                        <a href="#" class="delete-ai-recommendation" title="<?= translate('delete', $i18n) ?>">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </span>
                                </p>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>

        <?php } ?>

        <?php if (isset($amountDueThisMonth) || isset($budget) || isset($budgetUsed) || isset($budgetLeft) || isset($overBudgetAmount)) { ?>
            <div class="budget-subscriptions">
                <h2><?= translate('your_budget', $i18n) ?></h2>
                <div class="dashboard-subscriptions-container">
                    <div class="dashboard-subscriptions-list">
                        <?php if (isset($amountDueThisMonth)) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("amount_due", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= CurrencyFormatter::format($amountDueThisMonth, $currencies[$userData['main_currency']]['code']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($budget) && $budget > 0) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("budget", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= formatPrice($budget, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($budgetUsed)) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("budget_used", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= number_format($budgetUsed, 2) ?>%
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($budgetLeft)) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("budget_remaining", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= formatPrice($budgetLeft, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($overBudgetAmount) && $overBudgetAmount > 0) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("over_budget", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= formatPrice($overBudgetAmount, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <?php if (isset($activeSubscriptions) && $activeSubscriptions > 0) { ?>
        <div class="current-subscriptions">
            <h2><?= translate('your_subscriptions', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <div class="subscription-item thin">
                        <p class="subscription-item-title"><?= translate('active_subscriptions', $i18n) ?></p>
                        <div class="subscription-item-info">
                            <p class="subscription-item-value"><?= $activeSubscriptions ?></p>
                        </div>
                    </div>

                    <?php if (isset($totalCostPerMonth)) { ?>
                        <div class="subscription-item thin">
                            <p class="subscription-item-title"><?= translate('monthly_cost', $i18n) ?></p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-value">
                                    <?= CurrencyFormatter::format($totalCostPerMonth, $currencies[$userData['main_currency']]['code']) ?>
                                </p>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if (isset($totalCostPerYear)) { ?>
                        <div class="subscription-item thin">
                            <p class="subscription-item-title"><?= translate('yearly_cost', $i18n) ?></p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-value">
                                    <?= CurrencyFormatter::format($totalCostPerYear, $currencies[$userData['main_currency']]['code']) ?>
                                </p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php if (isset($inactiveSubscriptions) && $inactiveSubscriptions > 0) { ?>
        <div class="savings-subscriptions">
            <h2><?= translate('your_savings', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <div class="subscription-item thin">
                        <p class="subscription-item-title"><?= translate('inactive_subscriptions', $i18n) ?></p>
                        <div class="subscription-item-info">
                            <p class="subscription-item-value"><?= $inactiveSubscriptions ?></p>
                        </div>
                    </div>

                    <?php if (isset($totalSavingsPerMonth) && $totalSavingsPerMonth > 0) { ?>
                        <div class="subscription-item thin">
                            <p class="subscription-item-title"><?= translate('monthly_savings', $i18n) ?></p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-value">
                                    <?= CurrencyFormatter::format($totalSavingsPerMonth, $currencies[$userData['main_currency']]['code']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="subscription-item thin">
                            <p class="subscription-item-title"><?= translate('yearly_savings', $i18n) ?></p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-value">
                                    <?= CurrencyFormatter::format($totalSavingsPerMonth * 12, $currencies[$userData['main_currency']]['code']) ?>
                                </p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

</section>


<script src="scripts/dashboard.js?<?= $version ?>"></script>

<?php
require_once 'includes/footer.php';
?>