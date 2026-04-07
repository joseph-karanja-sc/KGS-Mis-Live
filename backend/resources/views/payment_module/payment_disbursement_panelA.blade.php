<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Disbursement</title>
    <style>
                /* ─────────────────────────────────────────────
        GLOBAL
        ─────────────────────────────────────────────── */
        body {
            font-family: "Inter", sans-serif;
            margin: 0;
            background: #f5f7fa;
            color: #333;
        }

        .container {
            padding: 20px;
            max-width: 1800px;
            margin: 0 auto;
        }


        /* ─────────────────────────────────────────────
        HEADER
        ─────────────────────────────────────────────── */
        .header {
            background: #1b5e20;
            padding: 15px 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }

        .export-btn {
            background: #2e7d32;
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .export-btn:hover {
            background: #256628;
        }


        /* ─────────────────────────────────────────────
        BREADCRUMBS
        ─────────────────────────────────────────────── */
        #breadcrumbs {
            margin: 15px 0;
            font-size: 14px;
        }

        #breadcrumbs a {
            color: #1b5e20;
            text-decoration: none;
            font-weight: 500;
        }

        #breadcrumbs a:hover {
            text-decoration: underline;
        }


        /* ─────────────────────────────────────────────
        TABS
        ─────────────────────────────────────────────── */
        #tabs {
            margin-bottom: 20px;
        }

        #tabs button {
            padding: 10px 18px;
            margin-right: 8px;
            font-size: 14px;
            border: none;
            background: #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
        }

        #tabs button:hover {
            background: #d5d5d5;
        }

        #tabs .activeTab {
            background: #1b5e20;
            color: white;
            font-weight: 600;
        }


        /* ─────────────────────────────────────────────
        FILTER ROW
        ─────────────────────────────────────────────── */
        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
            padding: 20px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
        }

        .select-box,
        .search-box {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: #fff;
            font-size: 14px;
        }

        .search-box {
            width: 220px;
        }


        /* ─────────────────────────────────────────────
        TABLES
        ─────────────────────────────────────────────── */
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            margin-top: 0;
            border-radius: 6px;
            overflow: hidden;
        }

        /* Fix table hiding dropdown */
        table, tr, td {
            overflow: visible !important;
        }

        th {
            background: #f0f0f0;
            padding: 12px;
            text-align: left;
            font-size: 14px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f1f1f1;
            font-size: 14px;
        }

        tr:hover td {
            background: #f7f7f7;
            cursor: pointer;
        }


        /* ─────────────────────────────────────────────
        EMPTY STATE
        ─────────────────────────────────────────────── */
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #888;
            font-size: 15px;
        }


        /* ─────────────────────────────────────────────
        HEADINGS WITHIN APP CONTAINER
        ─────────────────────────────────────────────── */
        #appContainer h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
        }


        /* ─────────────────────────────────────────────
        BUTTONS (VIEW, FUTURE ACTIONS)
        ─────────────────────────────────────────────── */
        button {
            font-family: inherit;
        }

        /* Force dropdown scope */
        /* Wrapper */
        /* Wrapper */
        .kgs-menu {
            position: relative;
            display: inline-block;
        }

        /* Button */
        .kgs-menu-btn {
            background: #1b5e20;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        /* Dropdown */
        .kgs-menu-list {
            display: none; /* hidden by default */
            position: absolute;
            top: 36px;
            left: 0;
            background: #fff;
            border: 1px solid #ccc;
            min-width: 160px;
            list-style: none;
            padding: 0;
            margin: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 9999;
        }

        .kgs-menu-list li {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 14px;
        }

        .kgs-menu-list li:hover {
            background: #f4f4f4;
        }

        /* CONFIRM OVERLAY */
        .confirm-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 99999;
        }

        .confirm-box {
            background: #fff;
            padding: 30px;
            width: 430px;
            border-radius: 12px;
            box-shadow: 0 5px 35px rgba(0,0,0,0.25);
            animation: fadeIn 0.25s ease;
        }

        .confirm-box h3 {
            margin: 0;
            margin-bottom: 12px;
            font-size: 20px;
            color: #1b5e20;
            font-weight: 700;
        }

        .confirm-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-cancel {
            background: #ddd;
            padding: 8px 18px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        .btn-proceed {
            background: #1b5e20;
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        /* TOASTS */
        #toastContainer {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 999999;
        }

        .toast {
            padding: 14px 22px;
            border-radius: 8px;
            color: white;
            font-size: 15px;
            margin-top: 10px;
            box-shadow: 0px 4px 14px rgba(0,0,0,0.25);
            animation: fadeIn 0.3s ease-out;
            max-width: 90%;
            text-align: center;
        }

        .toast-success { background: #2E6F40; }
        .toast-error { background: #d9534f; }

    </style>

</head>

<body>

    <!-- HEADER -->
    <div class="header">
        <h2>Payment Disbursement(Panel A)</h2>
        <button class="export-btn" id="exportBtn" style="display:none;" onclick="exportData()">Export ▼</button>
    </div>

    <div class="container">

        <!-- Breadcrumbs -->
        <div id="breadcrumbs"></div>

        <!-- Tabs -->
        <div id="tabs"></div>

        <!-- Main Content -->
        <div id="appContainer">Loading...</div>

    </div>

<script>
// ─────────────────────────────────────────────
//  NAVIGATION STATE
// ─────────────────────────────────────────────
let breadcrumbStack = [];
let currentCategory = null;
let currentPaymentRefNo = null;

function toggleExportBtn() {
    const btn = document.getElementById("exportBtn");
    btn.style.display = currentCategory ? "block" : "none";
}

function exportData() {
    if (!currentCategory || !currentPaymentRefNo) return;
    
    const table = document.querySelector("#appContainer table");
    if (!table) {
        showToast("No data to export", "error");
        return;
    }
    
    let csv = [];
    table.querySelectorAll("tr").forEach(row => {
        let cells = [];
        row.querySelectorAll("th, td").forEach(cell => {
            cells.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(cells.join(","));
    });
    
    const csvContent = csv.join("\n");
    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Panel A_${currentCategory}_${currentPaymentRefNo}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}


function showToast(message, type = "success") {
    const container = document.getElementById("toastContainer");
    const toast = document.createElement("div");
    toast.classList.add("toast", type === "success" ? "toast-success" : "toast-error");
    toast.innerText = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = "0";
        setTimeout(() => toast.remove(), 400);
    }, 2500);
}

function showConfirm(message, callback) {
    document.getElementById("confirmTitle").innerText = "Confirm Action";
    document.getElementById("confirmMessage").innerText = message;
    document.getElementById("confirmModal").style.display = "flex";

    const yesBtn = document.getElementById("confirmYesBtn");
    yesBtn.onclick = () => {
        closeConfirm();
        callback(true);
    };
}

function closeConfirm() {
    document.getElementById("confirmModal").style.display = "none";
}


function renderBreadcrumbs() {
    let html = `<a href="#" onclick="goHome()">Payments</a>`;

    breadcrumbStack.forEach((item, index) => {
        html += ` > <a href="#" onclick="jumpToBreadcrumb(${index})">${item.label}</a>`;
    });

    document.getElementById('breadcrumbs').innerHTML = html;
}

function goHome() {
    breadcrumbStack = [];
    loadSummary();
}

function jumpToBreadcrumb(index) {
    const action = breadcrumbStack[index].action;
    breadcrumbStack = breadcrumbStack.slice(0, index + 1);
    action();
}

function toggleMenu(event, element) {
    event.stopPropagation(); // prevents table click events

    const menu = element.querySelector(".kgs-menu-list");
    const isOpen = menu.style.display === "block";

    // Close all menus first
    document.querySelectorAll(".kgs-menu-list").forEach(m => m.style.display = "none");

    // Open this one if it was closed
    if (!isOpen) {
        menu.style.display = "block";
    }
}

// Close any open dropdown when clicking outside
document.addEventListener("click", function() {
    document.querySelectorAll(".kgs-menu-list").forEach(m => m.style.display = "none");
});



// ─────────────────────────────────────────────
//  TABS
// ─────────────────────────────────────────────
function renderTabs(tabs = [], active = "") {
    const container = document.getElementById("tabs");
    let html = "";

    tabs.forEach(t => {
        html += `
            <button onclick="${t.action}" class="${active === t.title ? 'activeTab' : ''}">
                ${t.title}
            </button>
        `;
    });

    container.innerHTML = html;
}

// ─────────────────────────────────────────────
//  SUBMIT TO PANEL B
// ─────────────────────────────────────────────
function submitToPanelB(refNo) {

    showConfirm("Submit this payment to Panel B?", confirmed => {
        if (!confirmed) return;

        fetch('/api/zispis/v1/submit-panel-b', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ payment_ref_no: refNo })
        })
        .then(res => res.json())
        .then(json => {
            showToast(json.message, json.status ? "success" : "error");
            if (json.status) loadSummary();
        })
        .catch(err => {
            console.error("Error submitting to Panel B:", err);
            showToast("An unexpected error occurred.", "error");
        });
    });
}


// ─────────────────────────────────────────────
//  LEVEL 1 — PAYMENT SUMMARY
// ─────────────────────────────────────────────
function loadSummary() {

    breadcrumbStack = [];  
    renderBreadcrumbs();
    renderTabs([], "");

    fetch('/api/zispis/v1/payments-summary')
        .then(res => res.json())
        .then(json => {
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Payment Ref No</th>
                            <th>Year</th>
                            <th>Category</th>
                            <th># Schools</th>
                            <th># Beneficiaries</th>
                            <th>Amount</th>
                            <th>Prepared By</th>
                            <th>Status</th>
                            <th>workflow status</th>
                            <th> </th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            json.data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.payment_ref_no}</td>
                        <td>${row.payment_year}</td>
                        <td>${row.payment_category}</td>
                        <td>${row.number_of_schools}</td>
                        <td>${row.number_of_beneficiaries}</td>
                        <td>${Number(row.request_amount).toLocaleString()}</td>
                        <td>${row.prepared_by}</td>
                        <td>${row.approval_status}</td>
                        <td>${row.workflow_status}</td>
                        <td>
                            <div class="kgs-menu" onclick="toggleMenu(event, this)">
                                <button class="kgs-menu-btn">Actions ▼</button>

                                <ul class="kgs-menu-list">
                                    <li onclick="handleView('${row.payment_ref_no}', '${row.payment_category}')">View</li>
                                    <li onclick="submitToPanelB('${row.payment_ref_no}')">Submit to Panel B</li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += "</tbody></table>";

            document.getElementById("appContainer").innerHTML = html;
        });
}


// ─────────────────────────────────────────────
//  LEVEL 2 — PAYMENT DETAILS (BENEFICIARIES)
// ─────────────────────────────────────────────
function openPayment(refNo, category) {

    breadcrumbStack.push({
        label: refNo,
        action: () => openPayment(refNo, category)
    });

    renderBreadcrumbs();

    renderTabs([
        { title: "Beneficiaries", action: `loadBeneficiaries('${refNo}')` },
        { title: "Payment Info", action: `loadPaymentInfo('${refNo}')` },
        { title: "Logs", action: `loadPaymentLogs('${refNo}')` },
    ], "Beneficiaries");

    loadBeneficiaries(refNo);
}


function loadBeneficiaries(refNo) {

    renderBreadcrumbs();

    fetch(`/api/zispis/v1/payments-details?payment_ref_no=${refNo}`)
        .then(res => res.json())
        .then(json => {

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Beneficiary No</th>
                            <th>School</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            json.data.forEach(row => {
                html += `
                    <tr onclick="openBeneficiary('${refNo}', '${row.id}', '${row.beneficiary_no ?? ''}')">
                        <td>${row.beneficiary_no ?? '-'}</td>
                        <td>${row.school_name ?? '-'}</td>
                        <td>${row.status ?? '-'}</td>
                    </tr>
                `;
            });

            html += "</tbody></table>";

            document.getElementById("appContainer").innerHTML = html;
        });
}

function handleView(refNo, category) {

    currentCategory = category;
    currentPaymentRefNo = refNo;
    toggleExportBtn();

    if (category === 'School Fees') {
        openSchoolSummary(refNo);
    } 
    else if (category === 'Education Grant') {
        openDistrictSummary(refNo);
    } 
    else {
        // fallback to existing behavior
        openPaymentPhases(refNo);
    }
}

function openSchoolSummary(refNo) {

    breadcrumbStack.push({
        label: refNo,
        action: () => openSchoolSummary(refNo)
    });

    renderBreadcrumbs();
    renderTabs([], "School Summary");

    document.getElementById("appContainer").innerHTML = `<h3>Loading school summary...</h3>`;

    fetch(`/api/zispis/v1/pg-schoolfee_summary`)
        .then(res => res.json())
        .then(json => {

            if (!json.success || json.data.length === 0) {
                document.getElementById("appContainer").innerHTML =
                    `<div class="empty-state">No school summary data found.</div>`;
                return;
            }

            let html = `
                <h3>School Fee Summary</h3>
                <table>
                    <thead>
                        <tr>
                            <th>School Name</th>
                            <th>EMIS Code</th>
                            <th>Bank Name</th>
                            <th>Branch Name</th>
                            <th>Account Number</th>
                            <th>Sort Code</th>
                            <th>Total Beneficiaries</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            json.data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.school_name}</td>
                        <td>${row.school_emis}</td>
                        <td>${row.school_bank ?? '-'}</td>
                        <td>${row.school_branch ?? '-'}</td>
                        <td>${row.school_bank_account ?? '-'}</td>
                        <td>${row.school_sort_code ?? '-'}</td>
                        <td>${row.total_beneficiaries}</td>
                        <td>${Number(row.total_amount).toLocaleString()}</td>
                    </tr>
                `;
            });

            html += "</tbody></table>";

            document.getElementById("appContainer").innerHTML = html;
        });
}

function openDistrictSummary(refNo) {

    breadcrumbStack.push({
        label: refNo,
        action: () => openDistrictSummary(refNo)
    });

    renderBreadcrumbs();
    renderTabs([], "District Summary");

    document.getElementById("appContainer").innerHTML = `<h3>Loading district summary...</h3>`;

    fetch(`/api/zispis/v1/pg-grant-summary`)
        .then(res => res.json())
        .then(json => {

            if (!json.success || json.data.length === 0) {
                document.getElementById("appContainer").innerHTML =
                    `<div class="empty-state">No district summary data found.</div>`;
                return;
            }

            let html = `
                <h3>District Grant Summary</h3>
                <table>
                    <thead>
                        <tr>
                            <th>District Name</th>
                            <th>Total Beneficiaries</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            json.data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.district_name}</td>
                        <td>${row.total_beneficiaries}</td>
                        <td>${Number(row.total_amount).toLocaleString()}</td>
                    </tr>
                `;
            });

            html += "</tbody></table>";

            document.getElementById("appContainer").innerHTML = html;
        });
}


// ─────────────────────────────────────────────
//  LEVEL 3 — BENEFICIARY DETAIL SCREEN
// ─────────────────────────────────────────────
function openBeneficiary(refNo, benId, displayName) {

    breadcrumbStack.push({
        label: displayName || `Beneficiary ${benId}`,
        action: () => openBeneficiary(refNo, benId, displayName)
    });

    renderBreadcrumbs();

    renderTabs([
        { title: "Details", action: `openBeneficiary('${refNo}', '${benId}', '${displayName}')` },
        { title: "Checklist", action: `loadChecklist('${benId}')` },
        { title: "Images", action: `loadImages('${benId}')` },
    ], "Details");

    document.getElementById("appContainer").innerHTML =
        `<h3>Loading beneficiary details...</h3>`;
}


// ─────────────────────────────────────────────
//  LEVEL 4 — CHECKLIST
// ─────────────────────────────────────────────
function loadChecklist(benId) {
    breadcrumbStack.push({
        label: "Checklist",
        action: () => loadChecklist(benId)
    });

    renderBreadcrumbs();

    document.getElementById("appContainer").innerHTML =
        `<h3>Checklist for Beneficiary ${benId}</h3>`;
}


// PLACEHOLDER FUNCTIONS FOR FUTURE USE
function loadPaymentInfo(refNo) {
    document.getElementById('appContainer').innerHTML = `<h3>Payment Info for ${refNo}</h3>`;
}

function loadPaymentLogs(refNo) {
    document.getElementById('appContainer').innerHTML = `<h3>Logs for ${refNo}</h3>`;
}

function loadImages(benId) {
    document.getElementById('appContainer').innerHTML = `<h3>Images for Beneficiary ${benId}</h3>`;
}

function openPaymentPhases(refNo) {

    breadcrumbStack.push({
        label: refNo,
        action: () => openPaymentPhases(refNo)
    });

    renderBreadcrumbs();
    renderTabs([], "Phases");

    document.getElementById("appContainer").innerHTML = `<h3>Loading phases...</h3>`;

    fetch(`/api/zispis/v1/payment-phases?payment_ref_no=${refNo}`)
        .then(res => res.json())
        .then(json => {

            if (!json.status || json.data.length === 0) {
                document.getElementById("appContainer").innerHTML = `
                    <div class="empty-state">No phase data available for this payment.</div>
                `;
                return;
            }

            let html = `
                <h3>Payment Phases for ${refNo}</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Phase</th>
                            <th>Total Schools</th>
                            <th>Total Beneficiaries</th>
                            <th>Amount</th>
                            <th> </th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            json.data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.payment_phase}</td>
                        <td>${row.total_schools}</td>
                        <td>${row.total_beneficiaries}</td>
                        <td>${Number(row.amount).toLocaleString()}</td>
                         <td>
                            <button onclick="openPhaseSchools('${refNo}', ${row.payment_phase})">View</button>
                        </td>
                    </tr>
                `;
            });

            html += "</tbody></table>";

            document.getElementById("appContainer").innerHTML = html;
        })
        .catch(err => {
            document.getElementById("appContainer").innerHTML =
                `<div class="empty-state">Error loading phases</div>`;
        });
}

function openPhaseSchools(refNo, phase) {

    breadcrumbStack.push({
        label: `Phase ${phase}`,
        action: () => openPhaseSchools(refNo, phase)
    });

    renderBreadcrumbs();
    renderTabs([], "Schools");

    document.getElementById("appContainer").innerHTML =
        `<h3>Loading schools for Phase ${phase}...</h3>`;

    fetch(`/api/zispis/v1/payment-phase-schools?payment_ref_no=${refNo}&payment_phase=${phase}`)
        .then(res => res.json())
        .then(json => {

            if (!json.status || json.data.length === 0) {
                document.getElementById("appContainer").innerHTML = `
                    <div class="empty-state">No schools found for this phase.</div>
                `;
                return;
            }

            let html = `
                <h3>Schools in Phase ${phase}</h3>
                <table>
                    <thead>
                        <tr>
                            <th>School Name</th>
                            <th>District</th>
                            <th>Total Beneficiaries</th>
                            <th>Total Amount</th>
                            <th>Bank Name</th>
                            <th>Branch</th>
                            <th>Account Number</th>
                            <th>Sort Code</th>
                            <th>Options</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            json.data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.school_name}</td>
                        <td>${row.district_name ?? row.school_district ?? '-'}</td>
                        <td>${row.total_beneficiaries}</td>
                        <td>${Number(row.total_amount).toLocaleString()}</td>

                        <!-- New Bank Info Fields -->
                        <td>${row.bank_name ?? '-'}</td>
                        <td>${row.branch_name ?? '-'}</td>
                        <td>${row.account_number ?? '-'}</td>
                        <td>${row.sort_code ?? '-'}</td>

                        <td>
                            <button onclick="openSchoolBeneficiaries('${refNo}', ${phase}, '${row.school_id ?? ''}')">
                                View
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += "</tbody></table>";

            document.getElementById("appContainer").innerHTML = html;
        })
        .catch(err => {
            document.getElementById("appContainer").innerHTML =
                `<div class="empty-state">Error loading schools</div>`;
        });
}


function openSchoolBeneficiaries(refNo, phase, schoolId) {

    // Add breadcrumb level
    breadcrumbStack.push({
        label: `School ${schoolId}`,
        action: () => openSchoolBeneficiaries(refNo, phase, schoolId)
    });

    renderBreadcrumbs();
    renderTabs([], "Beneficiaries");

    document.getElementById("appContainer").innerHTML =
        `<h3>Loading beneficiaries...</h3>`;

    // Fetch beneficiaries for this school + phase + payment
    fetch(`/api/zispis/v1/payment-beneficiaries?payment_ref_no=${refNo}&payment_phase=${phase}&school_id=${schoolId}`)
        .then(res => res.json())
        .then(json => {

            if (!json.status || json.data.length === 0) {
                document.getElementById("appContainer").innerHTML = `
                    <div class="empty-state">No beneficiaries found for this school.</div>
                `;
                return;
            }

            let html = `
                <h3>Beneficiaries (School ${schoolId})</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Beneficiary No</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>School</th>
                            <th>HHH NRC</th>
                            <th>HHH First Name</th>
                            <th>HHH Last Name</th>
                            <th>Grant Amount</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            json.data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.beneficiary_no}</td>
                        <td>${row.first_name}</td>
                        <td>${row.last_name}</td>
                        <td>${row.school_name}</td>
                        <td>${row.hhh_nrc_number}</td>
                        <td>${row.hhh_fname}</td>
                        <td>${row.hhh_lname}</td>
                        <td>${Number(row.grant_amount).toLocaleString()}</td>
                    </tr>
                `;
            });

            html += "</tbody></table>";

            document.getElementById("appContainer").innerHTML = html;
        })
        .catch(err => {
            document.getElementById("appContainer").innerHTML =
                `<div class="empty-state">Error loading beneficiaries</div>`;
        });
}






// ─────────────────────────────────────────────
//  INIT
// ─────────────────────────────────────────────
loadSummary();

</script>

<!-- Toast Container -->
<div id="toastContainer"></div>

<!-- Modern Confirm Modal -->
<div id="confirmModal" class="confirm-overlay">
    <div class="confirm-box">
        <h3 id="confirmTitle">Confirm Action</h3>
        <p id="confirmMessage">Are you sure?</p>

        <div class="confirm-buttons">
            <button class="btn-cancel" onclick="closeConfirm()">Cancel</button>
            <button id="confirmYesBtn" class="btn-proceed">Yes, Proceed</button>
        </div>
    </div>
</div>

</body>
</html>
