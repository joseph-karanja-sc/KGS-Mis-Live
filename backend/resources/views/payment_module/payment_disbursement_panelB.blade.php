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
        /* ─────────────────────────────────────────────
        MODAL OVERLAY
        ───────────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.55);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
        }

        /* ─────────────────────────────────────────────
        MODAL BOX
        ───────────────────────────────────────────── */
        /* ---------------------------------------
        MODERN MODAL DESIGN (Green + Orange)
        -----------------------------------------*/
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.55);
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(2px);
            z-index: 99999;
        }

        /* Modal container */
        .modal-box {
            width: 480px;
            background: #ffffff;
            border-radius: 14px;
            padding: 35px 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            animation: zoomIn 0.25s ease-out;
        }

        /* Smooth zoom animation */
        @keyframes zoomIn {
            0% {
                opacity: 0;
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: #2E6F40;
            margin-bottom: 20px;
        }

        /* Form labels */
        .modal-body label {
            font-size: 14px;
            font-weight: 600;
            color: #2E6F40;
            margin-bottom: 6px;
            display: block;
        }

        /* Input fields */
        .input-control {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #d3d3d3;
            font-size: 15px;
            outline: none;
            transition: 0.2s;
        }

        /* Hover + Focus States */
        .input-control:focus {
            border-color: #2E6F40;
            box-shadow: 0 0 0 2px rgba(46, 111, 64, 0.2);
        }

        /* Form group spacing */
        .form-group {
            margin-bottom: 18px;
        }

        /* Footer Buttons */
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        /* Cancel Button */
        .btn-cancel {
            background: #e0e0e0;
            padding: 10px 18px;
            border-radius: 8px;
            color: #333;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-cancel:hover {
            background: #c9c9c9;
        }

        /* Submit Button */
        .btn-submit {
            background: #2E6F40;
            padding: 10px 18px;
            border-radius: 8px;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-submit:hover {
            background: #245b34;
            transform: translateY(-1px);
        }

        /* Secondary “Orange” Button Styling */
        .btn-orange {
            background: #f88f06 !important;
            color: #fff !important;
        }
        .btn-orange:hover {
            background: #dd7e04 !important;
        }

        /* --------------------------------------------
        MODAL INLINE MESSAGE (SUCCESS / ERROR)
        ---------------------------------------------*/
        .modal-message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            position: relative;
            font-size: 14px;
            font-weight: 500;
        }

        /* Error style */
        .modal-message.error {
            background: #ffebee;
            border-left: 5px solid #d32f2f;
            color: #b71c1c;
        }

        /* Success style */
        .modal-message.success {
            background: #e8f5e9;
            border-left: 5px solid #2E6F40;
            color: #1b5e20;
        }

        /* Close (X) button */
        .msg-close {
            position: absolute;
            right: 12px;
            top: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            color: #555;
        }

        .msg-close:hover {
            color: #000;
        }

        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
            position: relative;
        }

        .btn-loading::after {
            content: "";
            width: 16px;
            height: 16px;
            border: 3px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            position: absolute;
            right: -30px;
            top: 50%;
            transform: translateY(-50%);
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin {
            from { transform: translateY(-50%) rotate(0deg); }
            to   { transform: translateY(-50%) rotate(360deg); }
        }

        /* ─────────────────────────────────────────────
        TOAST NOTIFICATIONS
        ────────────────────────────────────────────── */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 999999;
        }

        .toast {
            position: fixed;
            top: 20px;                /* distance from top */
            left: 50%;                /* move to horizontal center */
            transform: translateX(-50%); /* perfectly center it */
            padding: 14px 22px;
            border-radius: 8px;
            color: white;
            font-size: 15px;
            z-index: 999999;
            box-shadow: 0px 4px 14px rgba(0,0,0,0.25);
            animation: fadeIn 0.3s ease-out;
            max-width: 90%;
            text-align: center;
        }


        .toast-success { background: #2E6F40; }  /* primary green */
        .toast-error   { background: #d9534f; }  /* enterprise red */

        .toast-close {
            cursor: pointer;
            margin-left: 12px;
            font-weight: bold;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(40px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translate(-50%, -10px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }


        /* confirmation dialog */
        #confirmModal .modal-box {
            padding: 25px 28px;
        }

        /* -------------------------------
        FULL SCREEN PROCESSING OVERLAY
        --------------------------------*/
        .processing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(2px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999999;
        }

        .processing-box {
            background: white;
            padding: 35px 50px;
            border-radius: 12px;
            text-align: center;
            width: 420px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.25);
            animation: zoomIn 0.25s ease-out;
        }

        .processing-box p {
            font-size: 16px;
            margin-top: 18px;
            color: #2E6F40;
            font-weight: 600;
        }

        /* Loader */
        .loader {
            width: 48px;
            height: 48px;
            border: 5px solid #c8e6c9;
            border-top-color: #2E6F40;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-submit.loading::after {
            content: "";
            width: 14px;
            height: 14px;
            border: 3px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            position: absolute;
            right: -28px;
            top: 50%;
            transform: translateY(-50%);
            animation: spin 0.7s linear infinite;
        }







     </style>

</head>

<body>

    <!-- HEADER -->
    <div class="header">
        <h2>Payment Disbursement(Panel B)</h2>
        <button class="export-btn">Export ▼</button>
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

function panelBApproval(refNo) {

    openConfirmModal(
        "Are you sure you want to approve this payment request?",
        () => submitPanelB(refNo)   // callback
    );
}

function submitPanelB(refNo) {
    fetch('/api/zispis/v1/panelb-approval', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ payment_ref_no: refNo })
    })
    .then(res => res.json())
    .then(json => {

        if (json.status === true) {
            showToast("success", json.message);
            loadSummary();
        } else {
            showToast("error", json.message);
        }
    })
    .catch(err => {
        console.error("Error approving payment:", err);
        showToast("error", "Unexpected system error.");
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
                                    <li onclick="panelBApproval('${row.payment_ref_no}')">Approve Payment Request</li>
                                    ${
                                        row.workflow_status?.toLowerCase() === "pending submission to pg"
                                        ? `<li onclick="openPGFlow('${row.payment_ref_no}', '${row.payment_category}')">
                                                Submit Payment to PG
                                        </li>`
                                        : `<li style="opacity:0.4; pointer-events:none;">
                                                Submit Payment to PG (Locked)
                                        </li>`
                                    }
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

function openPGFlow(refNo, category) {

    // store globally for later use
    window.pgContext = {
        refNo: refNo,
        category: category
    };

    // phase no longer needed → pass null or 0
    showPGModal(refNo, null);
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

            // 🎯 Get global workflow status from backend
            const workflowStatus = json.workflow_status_name?.toLowerCase() ?? "";

            // 🎯 Determine if PG submission should be enabled
            const canSubmitToPG = workflowStatus === "pending submission to pg";

            let html = `
                <h3>Payment Phases for ${refNo}</h3>
                <p><strong>Current Workflow Status:</strong> ${json.workflow_status_name}</p>

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
                            <div class="kgs-menu" onclick="toggleMenu(event, this)">
                                <button class="kgs-menu-btn">Actions ▼</button>

                                <ul class="kgs-menu-list">

                                    <li onclick="openPhaseSchools('${refNo}', ${row.payment_phase})">View</li>
                                    

                                    ${
                                        canSubmitToPG
                                        ? `<li onclick="showPGModal('${refNo}', '${row.payment_phase}')">Submit Payment to PG</li>`
                                        : `<li style="opacity:0.4; pointer-events:none;">Submit Payment to PG (Locked)</li>`
                                    }

                                </ul>
                            </div>
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

// ─────────────────────────────────────────────
// SHOW POPUP WHEN USER CLICKS "Submit to PG"
// ─────────────────────────────────────────────
function showPGModal(refNo, phase) {
    document.getElementById("pgRefNo").value = refNo;
    document.getElementById("pgPhase").value = phase;
    document.getElementById("pgModal").style.display = "flex";

    loadPGCoordinators();
}


function closePGModal() {
    document.getElementById("pgModal").style.display = "none";
}
function loadPGCoordinators() {

    fetch('/api/zispis/v1/pg-coordinators')
        .then(res => res.json())
        .then(json => {

            const select = document.getElementById("pgUserSelect");
            select.innerHTML = `<option value="">-- Select User --</option>`;

            json.data.forEach(u => {
                select.innerHTML += `
                    <option value="${u.id}"
                        data-first="${u.first_name}"
                        data-last="${u.last_name}"
                        data-email="${u.email}">
                        ${u.first_name} ${u.last_name} (${u.email})
                    </option>
                `;
            });
        })
        .catch(err => {
            alert("Error loading coordinators.");
            console.error(err);
        });
}
function onPGUserSelect() {
    const sel = document.getElementById("pgUserSelect");
    const option = sel.options[sel.selectedIndex];

    document.getElementById("pgFirstName").value = option.dataset.first || "";
    document.getElementById("pgLastName").value = option.dataset.last || "";
    document.getElementById("pgEmail").value = option.dataset.email || "";
}
async function submitPGAuthentication() {

    hidePGMessage();
    setPgButtonLoading(true);

    const user_id = document.getElementById("pgUserSelect").value;
    const password = document.getElementById("pgPassword").value;
    const refNo = document.getElementById("pgRefNo").value;

    if (!user_id || !password) {
        showPGMessage("error", "Please select a user and enter password.");
        setPgButtonLoading(false);
        return;
    }

    // get selected user's email
    const userOption = document.querySelector(`#pgUserSelect option[value="${user_id}"]`);
    const email = userOption.dataset.email;

    try {
        const response = await fetch("https://kgsmis.edu.gov.zm/api/api-login", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        });

        const json = await response.json();

        if (!json.success) {
            showPGMessage("error", json.message || "Authentication failed.");
            setPgButtonLoading(false);
            return;
        }

        // SUCCESS
        showPGMessage("success", "Authentication successful! Proceeding...");

        // setTimeout(() => {
        //     closePGModal();
        //     showDisburseModal(refNo);
        // }, 800);

        setTimeout(() => {
            setPgButtonLoading(false); 
            closePGModal();
            showDisburseModal(refNo);
        }, 800);


    } catch (err) {
        showPGMessage("error", "Error contacting authentication server.");
        setPgButtonLoading(false);
        console.error(err);
    }
}

function showDisburseModal(refNo) {
    document.getElementById("disburseRefNo").value = refNo;
    document.getElementById("disburseModal").style.display = "flex";
}

function closeDisburseModal() {
    document.getElementById("disburseModal").style.display = "none";
}

function showPGMessage(type, text) {
    const box = document.getElementById("pgMessageBox");
    const msg = document.getElementById("pgMessageText");

    box.className = "modal-message " + type; // error or success
    msg.textContent = text;
    box.style.display = "block";
}

function hidePGMessage() {
    document.getElementById("pgMessageBox").style.display = "none";
}

function setPgButtonLoading(isLoading) {
    const btn = document.getElementById("pgSubmitBtn");

    if (isLoading) {
        btn.classList.add("btn-loading");
        btn.innerText = "Processing...";
    } else {
        btn.classList.remove("btn-loading");
        btn.innerText = "Submit to PG";
    }
}

function showToast(type, message) {
    const container = document.getElementById("toastContainer");

    const toast = document.createElement("div");
    toast.className = "toast " + (type === "success" ? "toast-success" : "toast-error");

    toast.innerHTML = `
        <span>${message}</span>
        <span class="toast-close" onclick="this.parentElement.remove()">×</span>
    `;

    container.appendChild(toast);

    // Auto remove after 4s
    setTimeout(() => {
        toast.remove();
    }, 4000);
}

function openConfirmModal(message, yesCallback) {
    document.getElementById("confirmMessage").innerText = message;

    const yesButton = document.getElementById("confirmYesBtn");

    // Clear previous onclick to avoid stacking
    yesButton.onclick = function() {
        closeConfirmModal();
        yesCallback();
    };

    document.getElementById("confirmModal").style.display = "flex";
}

function closeConfirmModal() {
    document.getElementById("confirmModal").style.display = "none";
}

function initiateDisbursement() {

    showProcessingOverlay(true);

    // Simulate API call (3 seconds)
    setTimeout(() => {

        showProcessingOverlay(false);

        closeDisburseModal();

        showToast("success", "Funds have been successfully sent to the Payment Gateway.");

    }, 3000);
}

function showProcessingOverlay(state = true) {
    document.getElementById("processingOverlay").style.display = state ? "flex" : "none";
}

function initiateDisbursement() {

    const btn = document.getElementById("disburseBtn");
    btn.classList.add("loading");

    showProcessingOverlay(true);

    setTimeout(() => {

        btn.classList.remove("loading");
        showProcessingOverlay(false);
        closeDisburseModal();
        showToast("success", "Funds have been successfully sent to the Payment Gateway.");

    }, 3000);
}

async function initiateDisbursementOld() {

    const refNo = document.getElementById("disburseRefNo").value;

    // Disable button + show loading spinner
    const btn = event.target;
    btn.classList.add("btn-loading");
    btn.innerText = "Processing...";

    // Optional overlay (if you want opacity effect)
    // showProcessingOverlay(true);

    try {

        const response = await fetch("https://kgsmis.edu.gov.zm/api/zispis/v1/processAllSchoolsForPG", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
            payment_ref_no: refNo,
            payment_phase: document.getElementById("pgPhase").value
        })

        });

        const json = await response.json();

        // Remove loading state
        btn.classList.remove("btn-loading");
        btn.innerText = "Disburse Funds";
        closeDisburseModal();

        if (json.status === true) {
            showToast("success", json.message ?? "Disbursement completed.");
        } else {
            showToast("error", json.message ?? "Disbursement failed.");
        }

    } catch (error) {
        btn.classList.remove("btn-loading");
        btn.innerText = "Disburse Funds";
        closeDisburseModal();

        showToast("error", "Network or server error.");
        console.error("Disbursement error:", error);
    }
}

async function triggerPGSubmissionOld() {

    const refNo = document.getElementById("disburseRefNo").value;

    // 🔥 determine type (IMPORTANT)
    const category = window.currentPaymentCategory || ''; 

    const paymentType =
        category.toLowerCase().includes('school') ? 'school' : 'district';

    const btn = event.target;
    btn.classList.add("btn-loading");
    btn.innerText = "Processing...";

    try {

        const response = await fetch(
            "https://kgsmis.edu.gov.zm/api/zispis/v1/processAllSchoolsForPG",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    payment_ref_no: refNo,
                    payment_type: paymentType   // ✅ NEW
                })
            }
        );

        const json = await response.json();

        btn.classList.remove("btn-loading");
        btn.innerText = "Disburse Funds";
        closeDisburseModal();

        if (json.status === true) {
            showToast("success", json.message ?? "Disbursement completed.");
        } else {
            showToast("error", json.message ?? "Disbursement failed.");
        }

    } catch (error) {
        btn.classList.remove("btn-loading");
        btn.innerText = "Disburse Funds";
        closeDisburseModal();

        showToast("error", "Network or server error.");
        console.error("Disbursement error:", error);
    }
}

async function triggerPGSubmission(refNo, category, btn = null) {

    // determine type directly from parameter
    const paymentType =
        (category || '').toLowerCase().includes('school') ? 'school' : 'district';

    // safe button handling
    if (!btn) {
        btn = event?.target || document.querySelector(".btn-primary");
    }

    btn.classList.add("btn-loading");
    btn.innerText = "Processing...";

    try {

        const response = await fetch(
            "https://kgsmis.edu.gov.zm/api/zispis/v1/processAllSchoolsForPG",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    payment_ref_no: refNo,   // ✅ from param
                    payment_type: paymentType
                })
            }
        );

        const json = await response.json();

        btn.classList.remove("btn-loading");
        btn.innerText = "Disburse Funds";
        closeDisburseModal();

        if (json.status === true) {
            showToast("success", json.message ?? "Disbursement completed.");
        } else {
            showToast("error", json.message ?? "Disbursement failed.");
        }

    } catch (error) {
        btn.classList.remove("btn-loading");
        btn.innerText = "Disburse Funds";
        closeDisburseModal();

        showToast("error", "Network or server error.");
        console.error("Disbursement error:", error);
    }
}

function handleDisbursementClick(btn) {

    const refNo = document.getElementById("disburseRefNo").value;

    // get category from global context (you already set this earlier)
    const category = window.pgContext?.category || '';

    // call your real API function
    triggerPGSubmission(refNo, category, btn);
}


</script>
<!-- ================================
    SUBMIT TO PG – POPUP MODAL
================================= -->

<div id="pgModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <h3 class="modal-title">Submit Payment to PG</h3>

        <!-- 🚀 INLINE MESSAGE BOX -->
        <div id="pgMessageBox" class="modal-message" style="display:none;">
            <span id="pgMessageText"></span>
            <span class="msg-close" onclick="hidePGMessage()">×</span>
        </div>

        <div class="modal-body">
            <div class="form-group">
                <label>Select Coordinator</label>
                <select id="pgUserSelect" onchange="onPGUserSelect()" class="input-control">
                    <option value="">-- Select User --</option>
                </select>
            </div>

            <div class="form-group">
                <label>First Name</label>
                <input id="pgFirstName" type="text" class="input-control" readonly>
            </div>

            <div class="form-group">
                <label>Last Name</label>
                <input id="pgLastName" type="text" class="input-control" readonly>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input id="pgEmail" type="text" class="input-control" readonly>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input id="pgPassword" type="password" class="input-control" placeholder="Enter password">
            </div>

            <input type="hidden" id="pgRefNo">
            <input type="hidden" id="pgPhase">


        </div>

        <div class="modal-footer">
            <button class="btn-cancel" onclick="closePGModal()">Cancel</button>
            <button id="pgSubmitBtn" class="btn-submit" onclick="submitPGAuthentication()">Submit to PG</button>

        </div>

    </div>
</div>



<!-- ================================
   SECOND POPUP (DISBURSE FUNDS)
================================ -->
<div id="disburseModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <h3>PG Authentication Successful</h3>
        <p>You may now initiate the disbursement of funds.</p>

        <input type="hidden" id="disburseRefNo">

        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeDisburseModal()">Cancel</button>
            <button id="disburseBtn" class="btn-submit" onclick="handleDisbursementClick(this)">Disburse Funds</button>
        </div>

    </div>
</div>


<!-- CONFIRMATION MODAL -->
<div id="confirmModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="width:380px;">

        <h3 class="modal-title">Confirm Action</h3>

        <p id="confirmMessage" style="margin-bottom:20px; font-size:15px;">
            Are you sure you want to continue?
        </p>

        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeConfirmModal()">Cancel</button>
            <button class="btn-submit" id="confirmYesBtn">Yes, Proceed</button>
        </div>

    </div>
</div>

<!-- PROCESSING OVERLAY -->
<div id="processingOverlay" class="processing-overlay" style="display:none;">
    <div class="processing-box">
        <div class="loader"></div>
        <p>Submitting disbursement request to Payment Gateway...</p>
    </div>
</div>



<div id="toastContainer"></div>
</body>
</html>