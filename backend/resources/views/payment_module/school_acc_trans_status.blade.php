<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Accountant App Submissions</title>

    <!-- Lucide icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: "Inter", sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
        }

        h1 {
            color: #1b5e20;
            margin-bottom: 10px;
        }

        .muted { color: #666; }

        /* ===============================
           FILTERS
        ================================ */
        select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #aaa;
            min-width: 200px;
        }

        /* ===============================
           CARD
        ================================ */
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .card-header {
            padding: 0 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        /* ===============================
           TABS
        ================================ */
        .nav-tabs {
            list-style: none;
            display: flex;
            margin: 0;
            padding-left: 0;
            border-bottom: 1px solid #dee2e6;
        }

        .nav-tabs button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: 1px solid transparent;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            margin-right: 4px;
        }

        .nav-tabs button.active {
            background: #fff;
            color: #1b5e20;
            border-color: #dee2e6 #dee2e6 #fff;
            font-weight: 600;
        }

        .nav-tabs button:hover:not(.active) {
            background: #e9ecef;
        }

        .tab-icon {
            width: 16px;
            height: 16px;
        }

        /* ===============================
           TABLES
        ================================ */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #1b5e20;
            color: white;
            text-align: left;
            font-weight: 600;
        }

        tr:hover {
            background: #e8f5e9;
            cursor: pointer;
        }

        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: white;
        }

        .badge-status-paid { background: #2ecc71; }
        .badge-status-pending { background: #f1c40f; color:#000; }
        .badge-status-other { background: #95a5a6; }

        /* ===============================
           ENTERPRISE LOADER
        ================================ */
        #globalLoader {
            position: fixed;
            top: 0; left: 0;
            height: 100vh; width: 100vw;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            font-size: 20px;
            font-weight: 600;
            color: #1b5e20;
        }

        .spinner {
            border: 4px solid #eee;
            border-top: 4px solid #1b5e20;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            vertical-align: middle;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        .loading-btn {
            opacity: 0.7;
            pointer-events: none;
        }

        /* ===============================
           PAGINATION FOOTER (disabled)
        ================================ */
        .table-footer {
            margin-top: 14px;
            padding: 6px 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #444;
            font-size: 14px;
            font-weight: 500;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            opacity: 0.35;           /* faded to show disabled */
            pointer-events: none;    /* truly disabled */
        }

        .page-arrow,
        .page-number {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px 8px;
            border-radius: 4px;
            background: #fafafa;
            border: 1px solid #ccc;
            font-size: 12px;
        }

        .page-arrow-icon {
            width: 14px;
            height: 14px;
        }

        /* ===============================
           GREEN RELOAD ICON BUTTON
        ================================ */
        .reload-icon-btn {
            background: #1b5e20;
            color: white;
            border: none;
            padding: 6px;
            border-radius: 50%;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .reload-icon-btn:hover {
            background: #145a16;
        }

        .reload-icon {
            width: 18px;
            height: 18px;
        }

        /* Modern Form Styling */
        .detail-section {
            margin-top: 20px;
        }

        .section-title {
            color: #1b5e20;
            font-size: 20px;
            margin: 20px 0 10px;
            font-weight: 600;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .field {
            display: flex;
            flex-direction: column;
        }

        .field label {
            font-size: 13px;
            color: #444;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .field input {
            background: #f9faf9;
            border: 1px solid #d6e0d5;
            border-radius: 6px;
            padding: 10px;
            font-size: 14px;
            color: #333;
        }

        .field input:disabled {
            opacity: 1;
            cursor: not-allowed;
        }

        .images-box {
            background: #eef7ee;
            padding: 18px;
            border-left: 4px solid #1b5e20;
            border-radius: 6px;
            margin-top: 20px;
        }

        /* Modern CTA Button */
        .modern-btn {
            padding: 10px 18px;
            background: #1b5e20;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .modern-btn:hover {
            background: #145a16;
        }

        /* Add comfortable spacing around the form */
        .beneficiary-details-wrapper {
            padding: 0 40px;   /* LEFT & RIGHT space */
            padding-bottom: 30px;
        }

        /* FULLSCREEN IMAGE MODAL */
        .image-modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
        }

        .image-modal-overlay img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(255,255,255,0.3);
            cursor: zoom-out;
        }


    </style>

</head>

<body>

<!-- ENTERPRISE GLOBAL LOADER -->
<div id="globalLoader">
    <span class="spinner"></span> &nbsp; Loading data...
</div>

<h1>School Accountant App Submissions</h1>
<p class="muted">Double-click a record to proceed.</p>

<!-- FILTERS -->
<div style="margin-bottom: 15px;">
    <label><strong>Filter by District:</strong></label>
    <select id="districtFilter" onchange="onDistrictChange()">
        <option value="all">All Districts</option>
    </select>
</div>

<!-- CARD + TABS -->
<div class="card">
    <div class="card-header">
        <ul class="nav-tabs" id="tabsContainer"></ul>
    </div>
    <div class="card-body" id="tabContent">Loading...</div>
</div>

<!-- GLOBAL TABLE FOOTER -->
<div id="tableFooter" class="table-footer"></div>


<!-- ONLY SHOWING CHANGED SCRIPT SECTION, KEEP YOUR HTML/CSS AS IS -->

<script>

/* GLOBALS */
let allSchools = [];
let currentSchoolsView = [];
let tabs = [];
let activeTabIndex = 0;
let currentDistrictFilter = "all";

/* 🔥 NEW PAGINATION STATE */
let currentPage = 1;
let lastPage = 1;

/* helper: render lucide icons safely */
function refreshIcons() {
    if (window.lucide && typeof lucide.createIcons === "function") {
        lucide.createIcons();
    }
}

/* LOADER */
function showLoader() {
    document.getElementById("globalLoader").style.display = "flex";
}
function hideLoader() {
    document.getElementById("globalLoader").style.display = "none";
}

/* INITIAL LOAD WITH PAGINATION */
async function fetchData(page = 1) {

    showLoader();

    currentPage = page;

    let res = await fetch(`/api/zispis/v1/sa-trans-summary?page=${page}`);
    let data = await res.json();

    hideLoader();

    if (!data.status) {
        document.getElementById("tabContent").innerHTML = "<p>No data found.</p>";
        updateFooter(0, 0, 0);
        return;
    }

    lastPage = data.last_page || 1;

    allSchools = data.schools || [];
    populateDistrictDropdown(allSchools);
    resetToBaseTab();
}

/* DISTRICT FILTER */
function populateDistrictDropdown(schools) {
    const select = document.getElementById("districtFilter");

    while (select.options.length > 1) {
        select.remove(1);
    }

    const districts = [...new Set(schools.map(s => s.school_district))];

    districts.forEach(d => {
        const opt = document.createElement("option");
        opt.value = d;
        opt.textContent = d;
        select.appendChild(opt);
    });
}

function onDistrictChange() {
    currentDistrictFilter = document.getElementById("districtFilter").value;
    resetToBaseTab();
}

/* RESET MAIN TAB */
function resetToBaseTab() {

    currentSchoolsView =
        currentDistrictFilter === "all"
            ? allSchools
            : allSchools.filter(s => s.school_district === currentDistrictFilter);

    tabs = [{
        type: "schools",
        label: currentDistrictFilter === "all"
            ? "All Schools"
            : currentDistrictFilter + " Schools"
    }];

    activeTabIndex = 0;
    renderTabs();
    renderActiveTabContent();
}

/* TABS UI */
function renderTabs() {
    let html = "";
    tabs.forEach((tab, index) => {

        let iconName = "layout-grid";
        if (tab.type === "schools") iconName = "building-2";
        if (tab.type === "beneficiaries") iconName = "users";
        if (tab.type === "beneficiaryDetails") iconName = "file-text";
        if (tab.type === "beneficiaryImages") iconName = "image";

        html += `
            <li>
                <button class="${index === activeTabIndex ? 'active' : ''}"
                        onclick="setActiveTab(${index})">
                    <i data-lucide="${iconName}" class="tab-icon"></i>
                    ${tab.label}
                </button>
            </li>`;
    });

    document.getElementById("tabsContainer").innerHTML = html;
    refreshIcons();
}

function setActiveTab(index) {
    tabs = tabs.slice(0, index + 1);
    activeTabIndex = index;
    renderTabs();
    renderActiveTabContent();
}

/* SCHOOLS TABLE */
function renderSchoolsTable() {

    const rows = currentSchoolsView;

    if (!rows.length) {
        document.getElementById("tabContent").innerHTML = "<p>No schools found.</p>";
        updateFooter(0, 0, 0);
        return;
    }

    let html = `
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>School Name</th>
                    <th>EMIS</th>
                    <th>District</th>
                    <th>Total Beneficiaries</th>
                </tr>
            </thead>
            <tbody>`;

    rows.forEach((s, i) => {
        html += `
            <tr ondblclick="openBeneficiariesTab(${i})">
                <td>${i+1}</td>
                <td>${s.school_name}</td>
                <td>${s.school_emis}</td>
                <td>${s.school_district}</td>
                <td>${s.total_beneficiaries}</td>
            </tr>`;
    });

    html += "</tbody></table>";

    document.getElementById("tabContent").innerHTML = html;
    document.getElementById("tableFooter").style.display = "flex";

    updateFooter(1, rows.length, rows.length);
}

/* 🔥 UPDATED FOOTER WITH REAL PAGINATION */
function updateFooter(start, end, total) {

    const div = document.getElementById("tableFooter");

    if (total === 0) {
        div.innerHTML = `No data`;
        return;
    }

    div.innerHTML = `
        <div class="pagination-controls">

            <button onclick="fetchData(1)">⏮</button>

            <button onclick="fetchData(${currentPage - 1})"
                ${currentPage <= 1 ? 'disabled' : ''}>◀</button>

            <span>Page ${currentPage} of ${lastPage}</span>

            <button onclick="fetchData(${currentPage + 1})"
                ${currentPage >= lastPage ? 'disabled' : ''}>▶</button>

            <button onclick="fetchData(${lastPage})">⏭</button>

        </div>

        <div>
            Showing page ${currentPage}
            <button class="reload-icon-btn" onclick="fetchData(currentPage)">
                🔄
            </button>
        </div>
    `;
}

/* START */
fetchData(1);

</script>

</body>
</html>
