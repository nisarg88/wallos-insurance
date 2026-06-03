<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';

include_once 'includes/list_insurances.php';

$sort = "renewal_date";
$sortOrder = $sort;

$allowedSortCriteria = ['name', 'id', 'renewal_date', 'premium', 'insurance_type', 'inactive'];
$order = ($sort === "premium" || $sort === "id") ? "DESC" : "ASC";

if (!in_array($sort, $allowedSortCriteria)) {
    $sort = "renewal_date";
}

$sql = "SELECT * FROM insurances WHERE user_id = :userId";

$params = [];

if (isset($_GET['state']) && $_GET['state'] !== "") {
    $sql .= " AND inactive = :inactive";
    $params[':inactive'] = intval($_GET['state']);
}

$orderByClauses = ["inactive ASC", "$sort $order"];
if ($sort !== "renewal_date") {
    $orderByClauses[] = "renewal_date ASC";
}

$sql .= " ORDER BY " . implode(", ", $orderByClauses);

$stmt = $db->prepare($sql);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, SQLITE3_INTEGER);
}
$result = $stmt->execute();

$insurances = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $insurances[] = $row;
}

// Get currencies keyed by id
$currenciesById = [];
foreach ($currencies as $c) {
    $currenciesById[$c['id']] = $c;
}

// Attach currency info to each insurance
foreach ($insurances as &$ins) {
    $cid = $ins['currency_id'] ?? 1;
    $ins['currency_code'] = $currenciesById[$cid]['code'] ?? 'INR';
    $ins['currency_symbol'] = $currenciesById[$cid]['symbol'] ?? '₹';
}
unset($ins);

// Insurance types
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

$headerClass = count($insurances) > 0 ? "main-actions" : "main-actions hidden";
?>

<section class="contain">
  <header class="<?= $headerClass ?>" id="main-actions">
    <button class="button" onClick="openAddInsurance(event)">
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
          <div class="filter-item" data-type="<?= $type ?>" onClick="filterByTypeInsurance('<?= $type ?>')"><?= $label ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="sort-container">
        <button class="button secondary-button" value="Sort" onClick="toggleSortOptions()" id="sort-button-insurance"
          title="<?= translate('sort', $i18n) ?>">
          <i class="fa-solid fa-arrow-down-wide-short"></i>
        </button>
        <div class="sort-options" id="sort-options-insurance">
          <div class="filter-title"><?= translate('sort', $i18n) ?></div>
          <div class="filter-item" data-sort="renewal_date" onClick="sortInsurancesBy('renewal_date')"><?= translate('next_renewal', $i18n) ?></div>
          <div class="filter-item" data-sort="name" onClick="sortInsurancesBy('name')"><?= translate('name', $i18n) ?></div>
          <div class="filter-item" data-sort="premium" onClick="sortInsurancesBy('premium')"><?= translate('premium', $i18n) ?></div>
          <div class="filter-item" data-sort="coverage_amount" onClick="sortInsurancesBy('coverage_amount')"><?= translate('coverage_amount', $i18n) ?></div>
          <div class="filter-item" data-sort="insurance_type" onClick="sortInsurancesBy('insurance_type')"><?= translate('insurance_type', $i18n) ?></div>
        </div>
      </div>
    </div>
  </header>

  <div class="subscriptions" id="insurances-list">
    <?php
    if (!empty($insurances)) {
        printInsurances($insurances, $sort, $insuranceTypes, $i18n, "", $currenciesById, $lang, $colorTheme);
    }

    if (count($insurances) == 0) {
    ?>
    <div class="empty-page">
      <img src="images/siteimages/empty.png" alt="<?= translate('empty_page', $i18n) ?>" />
      <p><?= translate('no_insurances_yet', $i18n) ?></p>
      <button class="button" onClick="openAddInsurance(event)">
        <i class="fa-solid fa-circle-plus"></i>
        <?= translate('add_first_insurance', $i18n) ?>
      </button>
    </div>
    <?php } ?>
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
        <select id="ins-cycle" name="cycle" style="width:70%" onchange="calculateInsuranceRenewalDate()">
          <?php foreach ($cycles as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($c['id'] == 4) ? 'selected' : '' ?>>
            <?= translate(strtolower($c['name']), $i18n) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Section: Dates -->
    <div class="form-section-title"><?= translate('policy_dates', $i18n) ?></div>
    <div class="form-group">
      <label for="ins-start-date"><?= translate('start_date', $i18n) ?></label>
      <div class="date-wrapper">
        <input type="date" id="ins-start-date" name="start_date" autocomplete="off"
          onchange="calculateInsuranceRenewalDate()">
      </div>
    </div>
    <div class="form-group">
      <label for="ins-tenure">
        <?= translate('policy_tenure_years', $i18n) ?>
      </label>
      <div class="date-wrapper">
        <input type="number" id="ins-tenure" name="tenure_years" min="1" max="50"
          value="1" autocomplete="off"
          onchange="calculateInsuranceRenewalDate()"
          placeholder="<?= translate('tenure_years', $i18n) ?>">
      </div>
    </div>
    <div class="form-group">
      <label for="ins-renewal-date">
        <?= translate('next_renewal', $i18n) ?>
        <button type="button" class="button secondary-button" style="margin-left:8px;padding:2px 8px;font-size:0.8em;"
          onClick="calculateInsuranceRenewalDate()" title="<?= translate('auto_calculate_renewal', $i18n) ?>">
          <i class="fa-solid fa-wand-magic-sparkles"></i>
        </button>
      </label>
      <div class="date-wrapper">
        <input type="date" id="ins-renewal-date" name="renewal_date" autocomplete="off">
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

<script src="scripts/insurances.js?v=1.1.0"></script>
<script>
window.translatedInsuranceTypes = <?= json_encode($insuranceTypes) ?>;
</script>

<?php
if (isset($db)) { $db->close(); }
require_once 'includes/footer.php';
?>