<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';

$sort = "renewal_date";
$sortOrder = $sort;

// Get cycles for the form
$query = "SELECT * FROM cycles";
$result = $db->query($query);
$cycles = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycles[] = $row;
}

// Get currencies
$query = "SELECT * FROM currencies WHERE user_id = :userId ORDER BY id ASC";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$currencies = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $currencies[] = $row;
}

// Get payment methods
$query = "SELECT * FROM payment_methods WHERE user_id = :userId AND enabled = 1 ORDER BY 'order' ASC";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$paymentMethods = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $paymentMethods[] = $row;
}

// Get household members
$query = "SELECT * FROM household WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$members = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $members[] = $row;
}

$mainCurrency = null;
$query = "SELECT main_currency FROM user WHERE id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$mainCurrencyId = $row['main_currency'] ?? 1;

// Insurance types with icons
$insuranceTypes = [
    'vehicle' => translate('vehicle_insurance', $i18n),
    'health' => translate('health_insurance', $i18n),
    'term' => translate('term_insurance', $i18n),
    'endowment' => translate('endowment_plan', $i18n),
    'pension' => translate('pension_plan', $i18n),
    'professional_indemnity' => translate('professional_indemnity', $i18n),
    'home' => translate('home_insurance', $i18n),
    'travel' => translate('travel_insurance', $i18n),
    'life' => translate('life_insurance', $i18n),
    'ulip' => translate('ulip', $i18n),
    'other' => translate('other_insurance', $i18n),
];

// Get active insurances for stats display
$query = "SELECT
    COUNT(*) as total_count,
    SUM(CASE WHEN inactive = 0 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN inactive = 0 THEN coverage_amount ELSE 0 END) as total_coverage,
    SUM(CASE WHEN inactive = 0 THEN sum_assured ELSE 0 END) as total_sum_assured,
    SUM(CASE WHEN inactive = 0 THEN premium ELSE 0 END) as total_premium
    FROM insurances WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$stats = $result->fetchArray(SQLITE3_ASSOC);

// Get coverage by type for chart
$query = "SELECT insurance_type, SUM(coverage_amount) as total_coverage, SUM(premium) as total_premium, COUNT(*) as count
          FROM insurances WHERE user_id = :userId AND inactive = 0
          GROUP BY insurance_type";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$coverageByType = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $coverageByType[$row['insurance_type']] = $row;
}

?>
<style>
.insurance-card .coverage-badge {
    font-size: 0.75em;
    padding: 2px 8px;
    border-radius: 12px;
    background: var(--main-color, #4a90d9);
    color: white;
    margin-left: 6px;
}
.insurance-portal-section {
    background: var(--hover-color, #f5f5f5);
    border-radius: 8px;
    padding: 10px 14px;
    margin: 8px 0;
}
.insurance-portal-section .cred-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 3px 0;
    font-size: 0.9em;
}
.insurance-portal-section .cred-row .label {
    color: #888;
}
.insurance-portal-section .cred-row .value {
    font-family: monospace;
}
.insurance-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.insurance-summary-card {
    background: var(--card-bg, #1e1e1e);
    border-radius: 10px;
    padding: 14px;
    text-align: center;
}
.insurance-summary-card .value {
    font-size: 1.4em;
    font-weight: bold;
    color: var(--main-color, #4a90d9);
}
.insurance-summary-card .label {
    font-size: 0.8em;
    color: #888;
    margin-top: 4px;
}
.insurance-type-badge {
    display: inline-block;
    font-size: 0.7em;
    padding: 2px 8px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.type-vehicle { background: #3498db; color: white; }
.type-health { background: #2ecc71; color: white; }
.type-term { background: #e74c3c; color: white; }
.type-endowment { background: #9b59b6; color: white; }
.type-pension { background: #f39c12; color: white; }
.type-professional_indemnity { background: #1abc9c; color: white; }
.type-home { background: #e67e22; color: white; }
.type-travel { background: #3498db; color: white; }
.type-life { background: #c0392b; color: white; }
.type-ulip { background: #8e44ad; color: white; }
.type-other { background: #7f8c8d; color: white; }
</style>

<section class="contain">
  <header class="main-actions" id="main-actions">
    <button class="button" onClick="addInsurance()">
      <i class="fa-solid fa-circle-plus"></i>
      <?= translate('new_insurance', $i18n) ?>
    </button>
    <div class="top-actions">
      <div class="search">
        <input type="text" autocomplete="off" name="search" id="search" placeholder="<?= translate('search', $i18n) ?>"
          onkeyup="searchInsurances()" />
        <span class="fa-solid fa-magnifying-glass search-icon"></span>
        <span class="fa-solid fa-xmark clear-search" onClick="clearSearchInsurance()"></span>
      </div>
      <div class="filtermenu on-dashboard">
        <button class="button secondary-button" id="filtermenu-button" title="<?= translate("filter", $i18n) ?>">
          <i class="fa-solid fa-filter"></i>
        </button>
        <div class="filtermenu-content" id="filter-menu-insurance">
          <div class="filter-title"><?= translate('insurance_type', $i18n) ?></div>
          <?php foreach ($insuranceTypes as $type => $label): ?>
          <div class="filter-item" data-type="<?= $type ?>" onClick="filterByType('<?= $type ?>')"><?= $label ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="sort-container">
        <button class="button secondary-button" value="Sort" onClick="toggleSortOptionsInsurance()" id="sort-button-insurance"
          title="<?= translate('sort', $i18n) ?>">
          <i class="fa-solid fa-arrow-down-wide-short"></i>
        </button>
        <div class="sort-options-content" id="sort-options-insurance">
          <div class="filter-title"><?= translate('sort', $i18n) ?></div>
          <div class="filter-item" data-sort="renewal_date" onClick="sortInsurances('renewal_date')"><?= translate('next_renewal', $i18n) ?></div>
          <div class="filter-item" data-sort="name" onClick="sortInsurances('name')"><?= translate('name', $i18n) ?></div>
          <div class="filter-item" data-sort="premium" onClick="sortInsurances('premium')"><?= translate('premium', $i18n) ?></div>
          <div class="filter-item" data-sort="coverage_amount" onClick="sortInsurances('coverage_amount')"><?= translate('coverage_amount', $i18n) ?></div>
          <div class="filter-item" data-sort="insurance_type" onClick="sortInsurances('insurance_type')"><?= translate('insurance_type', $i18n) ?></div>
        </div>
      </div>
    </div>
  </header>

  <?php if ($stats['active_count'] > 0): ?>
  <div class="insurance-summary-cards">
    <div class="insurance-summary-card">
      <div class="value"><?= $stats['active_count'] ?></div>
      <div class="label"><?= translate('active_insurances', $i18n) ?></div>
    </div>
    <div class="insurance-summary-card">
      <div class="value"><?= number_format($stats['total_coverage'] ?? 0) ?></div>
      <div class="label"><?= translate('total_coverage', $i18n) ?> (<?= $mainCurrency ? $currencies[0]['code'] ?? 'INR' : 'INR' ?>)</div>
    </div>
    <div class="insurance-summary-card">
      <div class="value"><?= number_format($stats['total_sum_assured'] ?? 0) ?></div>
      <div class="label"><?= translate('total_sum_assured', $i18n) ?></div>
    </div>
    <div class="insurance-summary-card">
      <div class="value"><?= number_format($stats['total_premium'] ?? 0) ?>/mo</div>
      <div class="label"><?= translate('total_premium_monthly', $i18n) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="insurances" id="insurances-list">
    <!-- Insurances loaded via JS -->
  </div>
</section>

<!-- Insurance Form Modal -->
<section class="subscription-form" id="insurance-form" style="display:none;">
  <header>
    <h3 id="insurance-form-title"><?= translate('add_insurance', $i18n) ?></h3>
    <span class="fa-solid fa-xmark close-form" onClick="closeInsuranceForm()"></span>
  </header>
  <form id="ins-form">

    <!-- Section: Policy Details -->
    <div class="form-section-title"><?= translate('insurance_policy_details', $i18n) ?></div>
    <div class="form-group">
      <input type="text" id="ins-name" name="name" autocomplete="off"
        placeholder="<?= translate('insurance_name', $i18n) ?>" required>
    </div>
    <div class="form-group">
      <select id="ins-type" name="insurance_type" required>
        <?php foreach ($insuranceTypes as $type => $label): ?>
        <option value="<?= $type ?>"><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <input type="text" id="ins-policy-number" name="policy_number" autocomplete="off"
        placeholder="<?= translate('policy_number', $i18n) ?>">
    </div>
    <div class="form-group">
      <input type="text" id="ins-insurer" name="insurer_name" autocomplete="off"
        placeholder="<?= translate('insurer_name', $i18n) ?>">
    </div>
    <div class="form-group">
      <input type="text" id="ins-url" name="url" autocomplete="off"
        placeholder="<?= translate('url', $i18n) ?>">
    </div>

    <!-- Section: Coverage & Premium -->
    <div class="form-section-title"><?= translate('insurance_coverage', $i18n) ?></div>
    <div class="form-group-inline">
      <input type="number" step="0.01" id="ins-coverage-amount" name="coverage_amount" autocomplete="off"
        placeholder="<?= translate('coverage_amount', $i18n) ?>">
      <input type="number" step="0.01" id="ins-sum-assured" name="sum_assured" autocomplete="off"
        placeholder="<?= translate('sum_assured', $i18n) ?>">
    </div>
    <div class="form-group-inline">
      <input type="number" step="0.01" id="ins-premium" name="premium" autocomplete="off"
        placeholder="<?= translate('premium_amount', $i18n) ?>">
      <select id="ins-currency" name="currency_id">
        <?php foreach ($currencies as $currency): ?>
        <option value="<?= $currency['id'] ?>" <?= ($currency['id'] == $mainCurrencyId) ? 'selected' : '' ?>>
          <?= $currency['name'] ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="ins-cycle"><?= translate('premium_payment_every', $i18n) ?? 'Premium every' ?></label>
      <div class="inline">
        <select id="ins-frequency" name="frequency" style="width:30%">
          <?php for ($i = 1; $i <= 12; $i++): ?>
          <option value="<?= $i ?>"><?= $i ?></option>
          <?php endfor; ?>
        </select>
        <select id="ins-cycle" name="cycle" style="width:70%">
          <?php foreach ($cycles as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($c['id'] == 4) ? 'selected' : '' ?>>
            <?= translate(strtolower($c['name']), $i18n) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Section: Dates -->
    <div class="form-section-title"><?= translate('renewal_date', $i18n) ?></div>
    <div class="form-group">
      <label for="ins-renewal-date"><?= translate('next_renewal', $i18n) ?></label>
      <div class="date-wrapper">
        <input type="date" id="ins-renewal-date" name="renewal_date" autocomplete="off">
      </div>
    </div>
    <div class="form-group">
      <label for="ins-start-date"><?= translate('start_date', $i18n) ?></label>
      <div class="date-wrapper">
        <input type="date" id="ins-start-date" name="start_date" autocomplete="off">
      </div>
    </div>

    <!-- Section: Portal Credentials -->
    <div class="form-section-title"><?= translate('insurance_portal_credentials', $i18n) ?></div>
    <div class="form-group">
      <input type="text" id="ins-portal-url" name="portal_url" autocomplete="off"
        placeholder="<?= translate('portal_url', $i18n) ?>">
    </div>
    <div class="form-group">
      <input type="text" id="ins-portal-username" name="portal_username" autocomplete="off"
        placeholder="<?= translate('portal_username', $i18n) ?>">
    </div>
    <div class="form-group">
      <input type="password" id="ins-portal-password" name="portal_password" autocomplete="off"
        placeholder="<?= translate('portal_password', $i18n) ?>">
    </div>

    <!-- Section: Nominee & Beneficiary -->
    <div class="form-section-title"><?= translate('insurance_nominee_beneficiary', $i18n) ?></div>
    <div class="form-group-inline">
      <input type="text" id="ins-nominee" name="nominee" autocomplete="off"
        placeholder="<?= translate('nominee', $i18n) ?>">
      <input type="text" id="ins-beneficiary" name="beneficiary" autocomplete="off"
        placeholder="<?= translate('beneficiary', $i18n) ?>">
    </div>

    <!-- Section: Misc -->
    <div class="form-group">
      <select id="ins-payment-method" name="payment_method_id">
        <option value=""><?= translate('payment_method', $i18n) ?></option>
        <?php foreach ($paymentMethods as $pm): ?>
        <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <select id="ins-payer" name="payer_user_id">
        <option value=""><?= translate('paid_by', $i18n) ?></option>
        <?php foreach ($members as $m): ?>
        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <input type="text" id="ins-notes" name="notes" autocomplete="off"
        placeholder="<?= translate('notes', $i18n) ?>">
    </div>

    <div class="form-group-inline grow">
      <input type="checkbox" id="ins-notify" name="notify" checked>
      <label for="ins-notify" class="grow"><?= translate('enable_notifications', $i18n) ?></label>
    </div>
    <div class="form-group-inline grow">
      <input type="checkbox" id="ins-auto-renew" name="auto_renew" checked>
      <label for="ins-auto-renew" class="grow"><?= translate('automatically_renews', $i18n) ?></label>
    </div>
    <div class="form-group-inline grow">
      <input type="checkbox" id="ins-inactive" name="inactive">
      <label for="ins-inactive" class="grow"><?= translate('inactive', $i18n) ?></label>
    </div>

    <input type="hidden" id="ins-id" name="id">

    <div class="form-group">
      <div class="inline">
        <button type="button" id="insurance-delete-btn" class="button danger-button" style="display:none;" onClick="deleteInsurance()">
          <i class="fa-solid fa-trash"></i> <?= translate('delete', $i18n) ?>
        </button>
        <button type="submit" class="button">
          <i class="fa-solid fa-check"></i> <?= translate('save', $i18n) ?>
        </button>
      </div>
    </div>
  </form>
</section>

<script src="scripts/insurances.js?v=1.0.0"></script>
<script>
window.translatedInsuranceTypes = <?= json_encode($insuranceTypes) ?>;
</script>

<?php
if (isset($db)) { $db->close(); }
require_once 'includes/footer.php';
?>