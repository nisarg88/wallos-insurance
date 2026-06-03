let csrfToken = '';
let allInsurances = [];

// ── Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    csrfToken = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').content
        : (window.CSRF_TOKEN || '');

    // Form submit handler
    const form = document.getElementById('ins-form');
    if (form) {
        form.addEventListener('submit', saveInsurance);
    }

    // Outside-click closes dropdowns
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.sort-container')) {
            const sortEl = document.getElementById('sort-options-insurance');
            if (sortEl) sortEl.classList.remove('is-open');
        }
        if (!e.target.closest('.filtermenu')) {
            const filterEl = document.getElementById('filter-menu-insurance');
            if (filterEl) filterEl.classList.remove('show');
        }
    });
});

// ── Toggle sort / filter dropdowns ─────────────────────────────────────────
function toggleSortOptions() {
    document.getElementById('sort-options-insurance').classList.toggle('is-open');
}

function toggleFilterMenu() {
    document.getElementById('filter-menu-insurance').classList.toggle('show');
}

// ── Sort ───────────────────────────────────────────────────────────────────
function sortInsurancesBy(criteria) {
    document.cookie = `sortOrder=${criteria};path=/`;
    window.location.search = window.location.search
        .replace(/state=[^&]*/g, '')
        .replace(/&+/g, '&')
        .replace(/^\?&/, '?');
    // Reload page to get server-sorted data
    const url = new URL(window.location);
    const params = new URLSearchParams(url.search);
    params.set('state', '');
    const currentState = new URLSearchParams(window.location.search).get('state') || '';
    window.location.search = '?sort=' + criteria + (currentState ? '&state=' + currentState : '');
}

// ── Filter by type ──────────────────────────────────────────────────────────
function filterByTypeInsurance(type) {
    const url = new URL(window.location.href);
    url.searchParams.set('insurance_type', type);
    window.location.href = url.toString();
}

// ── Search ─────────────────────────────────────────────────────────────────
function searchInsurances() {
    const q = document.getElementById('search').value.toLowerCase().trim();
    const cards = document.querySelectorAll('#insurances-list .subscription-container');

    cards.forEach(container => {
        const el = container.querySelector('.subscription');
        if (!el) return;
        const name = (el.dataset.name || '').toLowerCase();
        const detail = container.textContent.toLowerCase();
        const match = !q || name.includes(q) || detail.includes(q);
        container.style.display = match ? '' : 'none';
    });

    // Toggle clear button
    const clearBtn = document.querySelector('.clear-search');
    const searchInput = document.getElementById('search');
    if (clearBtn) clearBtn.style.display = searchInput.value ? 'block' : 'none';
}

function clearSearchInsurance() {
    document.getElementById('search').value = '';
    searchInsurances();
}

// ── Auto-calculate Renewal Date ────────────────────────────────────────────
function calculateInsuranceRenewalDate() {
    const startDateInput = document.getElementById('ins-start-date');
    const tenureInput = document.getElementById('ins-tenure');
    const renewalDateInput = document.getElementById('ins-renewal-date');
    const cycleSelect = document.getElementById('ins-cycle');

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

// ── Open / Close Modal ──────────────────────────────────────────────────────
function openAddInsurance(e) {
    if (e) e.stopPropagation();
    document.getElementById('insurance-form-title').textContent = translate_i18n('add_insurance');
    document.getElementById('ins-form').reset();
    document.getElementById('ins-id').value = '';
    document.getElementById('ins-renewal-date').value = '';
    document.getElementById('ins-start-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('ins-tenure').value = 1;
    document.getElementById('ins-inactive').checked = false;
    document.getElementById('ins-notify').checked = true;
    document.getElementById('ins-auto-renew').checked = true;
    document.getElementById('insurance-delete-btn').style.display = 'none';
    document.getElementById('insurance-form').style.display = 'block';
    document.getElementById('ins-name').focus();
}

function openEditInsurance(e, id) {
    if (e) e.stopPropagation();
    const form = document.getElementById('insurance-form');
    form.style.display = 'block';
    document.getElementById('insurance-delete-btn').style.display = 'block';

    // Fetch and populate
    fetch('endpoints/insurance/get.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const ins = (data.insurances || []).find(i => i.id == id);
            if (!ins) return;
            populateForm(ins);
        });
}

function populateForm(ins) {
    document.getElementById('insurance-form-title').textContent = translate_i18n('edit_insurance');
    document.getElementById('ins-id').value = ins.id || '';
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
}

function closeInsuranceForm() {
    document.getElementById('insurance-form').style.display = 'none';
}

// ── Save (Add/Update) ────────────────────────────────────────────────────────
function saveInsurance(e) {
    e.preventDefault();
    const form = document.getElementById('ins-form');
    const isEdit = document.getElementById('ins-id').value !== '';

    const formData = new FormData(form);
    formData.append('csrf_token', csrfToken);
    formData.set('notify', document.getElementById('ins-notify').checked ? '1' : '0');
    formData.set('auto_renew', document.getElementById('ins-auto-renew').checked ? '1' : '0');
    formData.set('inactive', document.getElementById('ins-inactive').checked ? '1' : '0');

    fetch('endpoints/insurance/add.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'Success') {
            showSuccessToast(data.message);
            closeInsuranceForm();
            setTimeout(() => window.location.reload(), 500);
        } else {
            showErrorToast(data.message || 'Error saving insurance');
        }
    })
    .catch(err => {
        console.error(err);
        showErrorToast('Network error saving insurance');
    });
}

// ── Delete ──────────────────────────────────────────────────────────────────
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
            setTimeout(() => window.location.reload(), 500);
        } else {
            showErrorToast(data.message || 'Error deleting insurance');
        }
    })
    .catch(err => showErrorToast('Network error deleting insurance'));
}

// ── Toggle open/close detail view ───────────────────────────────────────────
function toggleOpenInsurance(id) {
    const detail = document.getElementById('subscription-detail-' + id);
    if (!detail) return;

    const wasOpen = detail.style.display === 'block';
    // Close all first (single-open accordion)
    document.querySelectorAll('.subscription-detail').forEach(el => {
        el.style.display = 'none';
    });
    if (!wasOpen) {
        detail.style.display = 'block';
    }
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function translate_i18n(key) {
    const map = window.translatedInsuranceTypes || {};
    return map[key] || key;
}

function showSuccessToast(msg) {
    let toast = document.getElementById('successToast');
    if (!toast) {
        toast = createToast('success');
    }
    toast.querySelector('.text-2').textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function showErrorToast(msg) {
    let toast = document.getElementById('errorToast');
    if (!toast) {
        toast = createToast('error');
    }
    toast.querySelector('.text-2').textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 4000);
}

function createToast(type) {
    const toast = document.createElement('div');
    toast.id = type + 'Toast';
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fa-solid fa-${type === 'success' ? 'check' : 'x'} toast-icon ${type}"></i>
            <div class="message">
                <span class="text text-1">${type === 'success' ? 'Success' : 'Error'}</span>
                <span class="text text-2"></span>
            </div>
        </div>
        <i class="fa-solid fa-xmark close"></i>
        <div class="progress ${type}"></div>`;
    toast.querySelector('.close').addEventListener('click', () => toast.classList.remove('show'));
    document.body.appendChild(toast);
    return toast;
}