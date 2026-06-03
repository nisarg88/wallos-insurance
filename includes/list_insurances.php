<?php

require_once 'i18n/getlang.php';

function getInsuranceBillingCycle($cycle, $frequency, $i18n)
{
    switch ($cycle) {
        case 1:
            return $frequency == 1 ? translate('Daily', $i18n) : $frequency . " " . translate('days', $i18n);
        case 2:
            return $frequency == 1 ? translate('Weekly', $i18n) : $frequency . " " . translate('weeks', $i18n);
        case 3:
            return $frequency == 1 ? translate('Monthly', $i18n) : $frequency . " " . translate('months', $i18n);
        case 4:
            return $frequency == 1 ? translate('Yearly', $i18n) : $frequency . " " . translate('years', $i18n);
    }
}

function printInsurances($insurances, $sort, $insuranceTypes, $i18n, $imagePath, $currencies, $lang, $colorTheme)
{
    $currentType = null;
    foreach ($insurances as $ins) {
        // Group headers by type if sorting by type
        if ($sort === 'insurance_type' && $ins['insurance_type'] !== $currentType) {
            $typeLabel = $insuranceTypes[$ins['insurance_type']] ?? ucfirst(str_replace('_', ' ', $ins['insurance_type']));
            ?>
            <div class="subscription-list-title"><?= htmlspecialchars($typeLabel) ?></div>
            <?php
            $currentType = $ins['insurance_type'];
        }

        $id = $ins['id'];
        $hasLogo = !empty($ins['logo']);
        $logoFile = $hasLogo ? 'images/uploads/logos/' . $ins['logo'] : '';
        $cycle = intval($ins['cycle'] ?? 4);
        $frequency = intval($ins['frequency'] ?? 1);
        $billingCycle = getInsuranceBillingCycle($cycle, $frequency, $i18n);
        $currencyCode = $ins['currency_code'] ?? 'INR';
        $currencySymbol = $ins['currency_symbol'] ?? '₹';
        $premium = floatval($ins['premium'] ?? 0);
        $coverageAmount = floatval($ins['coverage_amount'] ?? 0);
        $sumAssured = floatval($ins['sum_assured'] ?? 0);
        $renewalDate = !empty($ins['renewal_date']) ? formatInsuranceDate($ins['renewal_date'], $lang) : '—';
        $startDate = !empty($ins['start_date']) ? formatInsuranceDate($ins['start_date'], $lang) : '—';
        $isInactive = intval($ins['inactive'] ?? 0) === 1;
        $autoRenew = intval($ins['auto_renew'] ?? 1) === 1;
        $insExtraClasses = '';
        if ($isInactive) $insExtraClasses .= ' inactive';
        if (!$autoRenew) $insExtraClasses .= ' manual';

        $daysToRenewal = null;
        $renewalClass = '';
        if (!empty($ins['renewal_date'])) {
            $daysToRenewal = ceil((strtotime($ins['renewal_date']) - time()) / 86400);
            if ($daysToRenewal !== null && $daysToRenewal >= 0 && $daysToRenewal <= 30) {
                $renewalClass = 'color:#f39c12';
            } elseif ($daysToRenewal !== null && $daysToRenewal < 0) {
                $renewalClass = 'color:#e74c3c';
            }
        }

        $typeLabel = $insuranceTypes[$ins['insurance_type']] ?? ucfirst(str_replace('_', ' ', $ins['insurance_type']));
        $typeClass = 'type-' . $ins['insurance_type'];
        ?>
        <div class="subscription-container">
            <div class="subscription<?= $insExtraClasses ?>"
                onClick="toggleOpenInsurance(<?= $id ?>)"
                data-id="<?= $id ?>"
                data-name="<?= htmlspecialchars($ins['name'] ?? '') ?>">
                <div class="subscription-main">
                    <span class="logo <?= !$hasLogo ? 'hideOnMobile' : '' ?>">
                        <?php if ($hasLogo): ?>
                            <img src="<?= $logoFile ?>">
                        <?php else: ?>
                            <?php include $imagePath . "images/siteicons/svg/logo.php"; ?>
                        <?php endif; ?>
                    </span>
                    <span class="name <?= $hasLogo ? 'hideOnMobile' : '' ?>"><?= htmlspecialchars($ins['name'] ?? '') ?></span>
                    <span class="cycle"
                        title="<?= $autoRenew ? translate('automatically_renews', $i18n) : translate('manual_renewal', $i18n) ?>">
                        <?php if ($autoRenew): ?>
                            <?php include $imagePath . "images/siteicons/svg/automatic.php"; ?>
                        <?php else: ?>
                            <?php include $imagePath . "images/siteicons/svg/manual.php"; ?>
                        <?php endif; ?>
                        <?= htmlspecialchars($billingCycle) ?>
                    </span>
                    <span class="next" style="<?= $renewalClass ?>">
                        <?= $renewalDate ?>
                        <?php if ($daysToRenewal !== null): ?>
                            <span style="font-size:0.8em;opacity:0.7">(<?= $daysToRenewal ?>d)</span>
                        <?php endif; ?>
                    </span>
                    <span class="price">
                        <span class="value"><?= htmlspecialchars($currencySymbol) ?><?= number_format($premium, 2) ?></span>
                        <span class="billing-cycle">/<?= strtolower(substr($billingCycle, 0, 1)) ?></span>
                    </span>
                    <span class="category hideOnMobile">
                        <span class="insurance-type-badge <?= $typeClass ?>"><?= htmlspecialchars($typeLabel) ?></span>
                    </span>
                </div>

                <div class="subscription-detail" id="subscription-detail-<?= $id ?>">
                    <div class="detail-columns">
                        <div class="detail-column">
                            <?php if (!empty($ins['policy_number'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('policy_number', $i18n) ?></span>
                                    <span class="detail-value"><?= htmlspecialchars($ins['policy_number']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ins['insurer_name'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('insurer_name', $i18n) ?></span>
                                    <span class="detail-value"><?= htmlspecialchars($ins['insurer_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ins['start_date'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('start_date', $i18n) ?></span>
                                    <span class="detail-value"><?= $startDate ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ins['nominee'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('nominee', $i18n) ?></span>
                                    <span class="detail-value"><?= htmlspecialchars($ins['nominee']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ins['beneficiary'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('beneficiary', $i18n) ?></span>
                                    <span class="detail-value"><?= htmlspecialchars($ins['beneficiary']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="detail-column">
                            <?php if ($coverageAmount > 0): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('coverage_amount', $i18n) ?></span>
                                    <span class="detail-value" style="color:#2ecc71"><?= htmlspecialchars($currencySymbol) ?><?= number_format($coverageAmount) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($sumAssured > 0): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('sum_assured', $i18n) ?></span>
                                    <span class="detail-value" style="color:#9b59b6"><?= htmlspecialchars($currencySymbol) ?><?= number_format($sumAssured) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ins['portal_url'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('portal_url', $i18n) ?></span>
                                    <span class="detail-value">
                                        <a href="<?= htmlspecialchars($ins['portal_url']) ?>" target="_blank" class="icon-link">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ins['notes'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= translate('notes', $i18n) ?></span>
                                    <span class="detail-value"><?= htmlspecialchars($ins['notes']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-actions">
                        <button class="button secondary-button"
                            onClick="openEditInsurance(event, <?= $id ?>)">
                            <i class="fa-solid fa-pen"></i>
                            <?= translate('edit', $i18n) ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

function formatInsuranceDate($date, $lang = 'en')
{
    if (empty($date)) return '—';
    $currentYear = date('Y');
    $dateYear = date('Y', strtotime($date));
    $dateFormat = ($currentYear == $dateYear) ? 'MMM d' : 'MMM yyyy';
    try {
        $formatter = new IntlDateFormatter($lang ?: 'en', IntlDateFormatter::SHORT, IntlDateFormatter::NONE, null, null, $dateFormat);
    } catch (Throwable $e) {
        $formatter = new IntlDateFormatter('en', IntlDateFormatter::SHORT, IntlDateFormatter::NONE, null, null, $dateFormat);
    }
    return $formatter->format(new DateTime($date));
}
?>