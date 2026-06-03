let allInsurances = [];
let currentFilterType = '';
let currentSort = 'renewal_date';

// ── Auto-calculate Renewal Date from Start Date + Tenure ─────────────────
function calculateInsuranceRenewalDate() {
    const startDateInput = document.getElementById('ins-start-date');
    const tenureInput = document.getElementById('ins-tenure');
    const renewalDateInput = document.getElementById('ins-renewal-date');
    const cycleSelect = document.getElementById('ins-cycle');
    const frequencySelect = document.getElementById('ins-frequency');

    if (!startDateInput?.value || !tenureInput?.value) return;

    const startDate = new Date(startDateInput.value);
    const tenure = parseInt(tenureInput.value) || 1;
    const cycle = parseInt(cycleSelect?.value) || 4;

    if (isNaN(startDate.getTime())) return;

    const renewalDate = new Date(startDate);
    switch (cycle) {
        case 1: renewalDate.setDate(renewalDate.getDate() + (tenure * 365)); break;
        case 2: renewalDate.setDate(renewalDate.getDate() + (tenure * 52 * 7)); break;
        case 3: renewalDate.setMonth(renewalDate.getMonth() + (tenure * 12)); break;
        case 4: default: renewalDate.setFullYear(renewalDate.getFullYear() + tenure); break;
    }

    if (renewalDateInput) {
        renewalDateInput.value = renewalDate.toISOString().split('T')[0];
    }
}

let csrfToken = '';

// ── Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    csrfToken = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').content
        : (window.CSRF_TOKEN || '');

    document.getElementById('ins-form').addEventListener('submit', saveInsurance);
    loadInsurances();
});

// ── Load & Render ────────────────────────────────────────────────────────
function loadInsurances() {
    let url = 'endpoints/insurance/get.php';
    if (currentFilterType) url += '?type=' + currentFilterType;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allInsurances = data.insurances || [];
                renderInsurances(data.insurances, data.summary);
            }
        })
        .catch(err => console.error('Failed to load insurances:', err));
}

function renderInsurances(insurances, summary) {
    const container = document.getElementById('insurances-list');
    if (!container) return;

    if (!insurances || insurances.length === 0) {
        container.innerHTML = `
            <div class="empty-page">
                <img src="images/siteimages/empty.png" alt="empty" />
                <p>${translate('no_insurances_yet')}</p>
                <button class="button" onClick="addInsurance()">
                    <i class="fa-solid fa-circle-plus"></i> ${translate('add_first_insurance')}
                </button>
            </div>`;
        return;
    }

    // Sort
    insurances.sort((a, b) => {
        let aVal = a[currentSort] ?? '';
        let bVal = b[currentSort] ?? '';
        if (currentSort === 'premium' || currentSort === 'coverage_amount') {
            return parseFloat(bVal) - parseFloat(aVal);
        }
        return String(aVal).localeCompare(String(bVal));
    });

    let html = '<div class="subscriptions">';
    insurances.forEach(ins => {
        const logoFile = ins.logo ? 'images/uploads/logos/' + ins.logo : '';
        const typeLabel = window.translatedInsuranceTypes?.[ins.insurance_type] || ins.insurance_type;
        const typeClass = 'type-' + ins.insurance_type;
        const currencySymbol = ins.currency_symbol || '₹';
        const isInactive = ins.inactive == 1;

        const renewal = ins.renewal_date
            ? new Date(ins.renewal_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
            : '—';

        const daysToRenewal = ins.renewal_date
            ? Math.ceil((new Date(ins.renewal_date) - new Date()) / (1000 * 60 * 60 * 24))
            : null;
        const renewalClass = daysToRenewal !== null && daysToRenewal <= 30 && daysToRenewal >= 0 ? 'color: #f39c12;' : '';
        const renewalClassDanger = daysToRenewal !== null && daysToRenewal < 0 ? 'color: #e74c3c;' : '';

        html += `
        <div class="subscription ${isInactive ? 'inactive' : ''}" data-id="${ins.id}" onclick="editInsurance(${ins.id})">
            <div class="sub-logo">
                ${logoFile
                    ? `<img src="${logoFile}" alt="${ins.name}">`
                    : `<div class="sub-logo-placeholder"><i class="fa-solid fa-shield-halved"></i></div>`
                }
            </div>
            <div class="sub-details">
                <div class="sub-name-row">
                    <div class="sub-name">${escHtml(ins.name)}</div>
                    <span class="insurance-type-badge ${typeClass}">${escHtml(typeLabel)}</span>
                </div>
                ${ins.policy_number ? `<div class="sub-note">Policy: ${escHtml(ins.policy_number)}</div>` : ''}
                ${ins.insurer_name ? `<div class="sub-note">${escHtml(ins.insurer_name)}</div>` : ''}
                <div class="sub-meta">
                    <span style="${renewalClass}${renewalClassDanger}">
                        <i class="fa-solid fa-calendar"></i> ${translate('renewal_date')}: ${renewal}
                        ${daysToRenewal !== null ? `(${daysToRenewal}d)` : ''}
                    </span>
                </div>
                <div class="sub-tags">
                    ${ins.coverage_amount ? `<span class="sub-tag" style="background:#2ecc71;color:white">Cover: ${currencySymbol}${numberFormat(ins.coverage_amount)}</span>` : ''}
                    ${ins.sum_assured ? `<span class="sub-tag" style="background:#9b59b6;color:white">Sum: ${currencySymbol}${numberFormat(ins.sum_assured)}</span>` : ''}
                    <span class="sub-tag">${currencySymbol}${numberFormat(ins.premium)}/${getCycleLabel(ins.cycle)}</span>
                </div>
                ${ins.nominee ? `<div class="sub-note"><i class="fa-solid fa-user"></i> ${translate('nominee')}: ${escHtml(ins.nominee)}</div>` : ''}
            </div>
            <div class="sub-price">
                <span class="price">${currencySymbol}${numberFormat(ins.premium)}</span>
                <span class="billing-cycle">/${getCycleLabelShort(ins.cycle)}</span>
            </div>
            <div class="sub-actions" onclick="event.stopPropagation()">
                ${ins.url || ins.portal_url
                    ? `<a href="${escHtml(ins.url || ins.portal_url)}" target="_blank" class="icon-link" title="Visit portal"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>`
                    : ''}
            </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

// ── Add / Edit ────────────────────────────────────────────────────────────
function addInsurance() {
    document.getElementById('insurance-form-title').textContent = translate('add_insurance');
    document.getElementById('ins-form').reset();
    document.getElementById('ins-id').value = '';
    document.getElementById('ins-renewal-date').value = '';
    document.getElementById('ins-start-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('ins-inactive').checked = false;
    document.getElementById('ins-tenure').value = 1;
    document.getElementById('ins-notify').checked = true;
    document.getElementById('ins-auto-renew').checked = true;
    document.getElementById('insurance-delete-btn').style.display = 'none';
    document.getElementById('insurance-form').style.display = 'block';
    document.getElementById('ins-name').focus();
}

function editInsurance(id) {
    const ins = allInsurances.find(i => i.id == id);
    if (!ins) return;

    document.getElementById('insurance-form-title').textContent = translate('edit_insurance');
    document.getElementById('ins-id').value = ins.id;
    document.getElementById('ins-name').value = ins.name || '';
    document.getElementById('ins-type').value = ins.insurance_type || 'other';
    document.getElementById('ins-policy-number').value = ins.policy_number || '';
    document.getElementById('ins-insurer').value = ins.insurer_name || '';
    document.getElementById('ins-url').value = ins.url || '';
    document.getElementById('ins-coverage-amount').value = ins.coverage_amount || '';
    document.getElementById('ins-sum-assured').value = ins.sum_assured || '';
    document.getElementById('ins-premium').value = ins.premium || '';
    document.getElementById('ins-currency').value = ins.currency_id || 1;
    document.getElementById('ins-frequency').value = ins.frequency || 1;
    document.getElementById('ins-cycle').value = ins.cycle || 4;
    document.getElementById('ins-renewal-date').value = ins.renewal_date || '';
    document.getElementById('ins-start-date').value = ins.start_date || '';
    document.getElementById('ins-tenure').value = ins.tenure_years || 1;
    document.getElementById('ins-portal-url').value = ins.portal_url || '';
    document.getElementById('ins-portal-username').value = ins.portal_username || '';
    document.getElementById('ins-portal-password').value = ins.portal_password || '';
    document.getElementById('ins-nominee').value = ins.nominee || '';
    document.getElementById('ins-beneficiary').value = ins.beneficiary || '';
    document.getElementById('ins-notes').value = ins.notes || '';
    document.getElementById('ins-payment-method').value = ins.payment_method_id || '';
    document.getElementById('ins-payer').value = ins.payer_user_id || '';
    document.getElementById('ins-inactive').checked = ins.inactive == 1;
    document.getElementById('ins-notify').checked = ins.notify == 1;
    document.getElementById('ins-auto-renew').checked = ins.auto_renew == 1;
    document.getElementById('insurance-delete-btn').style.display = 'block';
    document.getElementById('insurance-form').style.display = 'block';
    document.getElementById('ins-name').focus();
}

function closeInsuranceForm() {
    document.getElementById('insurance-form').style.display = 'none';
}

// ── Save (Add/Update) ─────────────────────────────────────────────────────
function saveInsurance(e) {
    e.preventDefault();
    const form = document.getElementById('ins-form');
    const isEdit = document.getElementById('ins-id').value !== '';

    const formData = new FormData(form);
    formData.append('csrf_token', csrfToken);

    // Convert checkbox values properly
    formData.set('notify', document.getElementById('ins-notify').checked ? '1' : '0');
    formData.set('auto_renew', document.getElementById('ins-auto-renew').checked ? '1' : '0');
    formData.set('inactive', document.getElementById('ins-inactive').checked ? '1' : '0');

    const endpoint = 'endpoints/insurance/add.php' + (isEdit ? '' : '');
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'Success') {
            showSuccessToast(data.message);
            closeInsuranceForm();
            loadInsurances();
        } else {
            showErrorToast(data.message || 'Error saving insurance');
        }
    })
    .catch(err => {
        console.error(err);
        showErrorToast('Network error saving insurance');
    });
}

// ── Delete ────────────────────────────────────────────────────────────────
function deleteInsurance() {
    const id = document.getElementById('ins-id').value;
    if (!id) return;
    if (!confirm('Delete this insurance?')) return;

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('id', id);

    fetch('endpoints/insurance/delete.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccessToast(data.message);
            closeInsuranceForm();
            loadInsurances();
        } else {
            showErrorToast(data.message || 'Error deleting insurance');
        }
    })
    .catch(err => showErrorToast('Network error deleting insurance'));
}

// ── Search ───────────────────────────────────────────────────────────────
function searchInsurances() {
    const q = document.getElementById('search').value.toLowerCase();
    if (!q) {
        renderInsurances(allInsurances);
        return;
    }
    const filtered = allInsurances.filter(ins =>
        (ins.name || '').toLowerCase().includes(q) ||
        (ins.policy_number || '').toLowerCase().includes(q) ||
        (ins.insurer_name || '').toLowerCase().includes(q) ||
        (ins.nominee || '').toLowerCase().includes(q) ||
        (ins.beneficiary || '').toLowerCase().includes(q)
    );
    renderInsurances(filtered);
}

function clearSearchInsurance() {
    document.getElementById('search').value = '';
    renderInsurances(allInsurances);
}

// ── Filter & Sort ─────────────────────────────────────────────────────────
function filterByType(type) {
    currentFilterType = currentFilterType === type ? '' : type;
    document.querySelectorAll('#filter-menu-insurance .filter-item').forEach(el => {
        el.classList.toggle('selected', el.dataset.type === currentFilterType);
    });
    loadInsurances();
}

function sortInsurances(sort) {
    currentSort = sort;
    document.querySelectorAll('#sort-options-insurance .filter-item').forEach(el => {
        el.classList.toggle('selected', el.dataset.sort === sort);
    });
    renderInsurances(allInsurances);
}

function toggleSortOptionsInsurance() {
    document.getElementById('sort-options-insurance').classList.toggle('is-open');
}

// ── Helpers ───────────────────────────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function numberFormat(num) {
    return parseFloat(num || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function getCycleLabel(cycle) {
    const labels = { 1: 'daily', 2: 'weekly', 3: 'monthly', 4: 'yearly' };
    return translate(labels[cycle] || 'monthly');
}

function getCycleLabelShort(cycle) {
    const labels = { 1: 'd', 2: 'w', 3: 'mo', 4: 'yr' };
    return labels[cycle] || 'mo';
}

function translate(key) {
    // Fallback if i18n global not available
    const translations = {
        'no_insurances_yet': '<?= addslashes(translate("no_insurances_yet", $i18n ?? [])) ?>',
        'add_first_insurance': '<?= addslashes(translate("add_first_insurance", $i18n ?? [])) ?>',
        'add_insurance': '<?= addslashes(translate("add_insurance", $i18n ?? [])) ?>',
        'edit_insurance': '<?= addslashes(translate("edit_insurance", $i18n ?? [])) ?>',
        'renewal_date': '<?= addslashes(translate("renewal_date", $i18n ?? [])) ?>',
        'nominee': '<?= addslashes(translate("nominee", $i18n ?? [])) ?>',
        'save': '<?= addslashes(translate("save", $i18n ?? [])) ?>',
        'delete': '<?= addslashes(translate("delete", $i18n ?? [])) ?>',
        'daily': '<?= addslashes(translate("daily", $i18n ?? [])) ?>',
        'weekly': '<?= addslashes(translate("weekly", $i18n ?? [])) ?>',
        'monthly': '<?= addslashes(translate("monthly", $i18n ?? [])) ?>',
        'yearly': '<?= addslashes(translate("yearly", $i18n ?? [])) ?>',
    };
    return translations[key] || key;
}

function showSuccessToast(msg) {
    const el = document.getElementById('successToast') || createToast('success');
    el.querySelector('.successMessage').textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 3000);
}

function showErrorToast(msg) {
    const el = document.getElementById('errorToast') || createToast('error');
    el.querySelector('.errorMessage').textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 4000);
}

function createToast(type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<div class="toast-content"><i class="fa-solid fa-${type === 'success' ? 'check' : 'x'} toast-icon ${type}"></i><div class="message"><span class="text text-1">${type === 'success' ? 'Success' : 'Error'}</span><span class="text text-2"></span></div></div><i class="fa-solid fa-xmark close"></i><div class="progress ${type}"></div>`;
    document.body.appendChild(toast);
    return toast;
}