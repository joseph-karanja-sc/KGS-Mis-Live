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


<script>
/* GLOBALS */
let allSchools = [];
let currentSchoolsView = [];
let tabs = [];
let activeTabIndex = 0;
let currentDistrictFilter = "all";

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

/* RELOAD MAIN UI */
function reloadMainUI() {
    location.reload();
}

/* INITIAL LOAD */
async function fetchData() {
    showLoader();

    let res = await fetch("/api/zispis/v1/sa-trans-summary");
    let data = await res.json();

    hideLoader();

    if (!data.status) {
        document.getElementById("tabContent").innerHTML = "<p>No data found.</p>";
        updateFooter(0, 0, 0);
        return;
    }

    allSchools = data.schools || [];
    populateDistrictDropdown(allSchools);
    resetToBaseTab();
}

/* DISTRICT FILTER */
function populateDistrictDropdown(schools) {
    const select = document.getElementById("districtFilter");

    // Clear any old options except "all"
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
        label: currentDistrictFilter === "all" ? "All Schools" : currentDistrictFilter + " Schools"
    }];

    activeTabIndex = 0;
    renderTabs();
    renderActiveTabContent();
}

/* TABS UI */
function renderTabs() {
    let html = "";
    tabs.forEach((tab, index) => {

        // pick icon per tab type
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

/* MAIN TAB RENDERING */
function renderActiveTabContent() {
    const tab = tabs[activeTabIndex];

    switch (tab.type) {
        case "schools": renderSchoolsTable(); break;
        case "beneficiaries": renderBeneficiariesTable(tab.payload); break;
        case "beneficiaryDetails": renderBeneficiaryDetails(tab.payload); break;
        case "beneficiaryImages": renderImagesTab(tab.payload); break;
    }
}

/* =========================================================
   SCHOOLS TABLE
========================================================= */
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

/* =========================================================
   BENEFICIARIES TABLE
========================================================= */
function openBeneficiariesTab(i) {
    const school = currentSchoolsView[i];
    const beneficiaries = [...(school.beneficiaries || [])];

    // sort by date_received (latest first)
    beneficiaries.sort((a,b) => new Date(b.date_received) - new Date(a.date_received));

    tabs = tabs.slice(0, activeTabIndex + 1);
    tabs.push({
        type: "beneficiaries",
        label: `${school.school_name} Beneficiaries`,
        payload: { school, beneficiaries }
    });

    activeTabIndex = tabs.length - 1;
    renderTabs();
    renderActiveTabContent();
}

function renderBeneficiariesTable({ school, beneficiaries }) {

    if (!beneficiaries.length) {
        document.getElementById("tabContent").innerHTML = "<p>No beneficiaries found.</p>";
        return;
    }

    window.currentBeneficiaryRows = beneficiaries;

    let html = `
        <p><strong>School:</strong> ${school.school_name} (${school.school_emis})<br>
           <strong>District:</strong> ${school.school_district}</p>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Beneficiary No</th>
                    <th>Name</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date Received</th>
                </tr>
            </thead>
            <tbody>`;

    beneficiaries.forEach((b, idx) => {
        const status = b.payment_status.toLowerCase();
        let sClass = "badge-status-other";
        if (status === "paid") sClass = "badge-status-paid";
        if (status === "pending") sClass = "badge-status-pending";

        html += `
            <tr ondblclick="openBeneficiaryDetails(${idx})">
                <td>${idx + 1}</td>
                <td>${b.beneficiary_no}</td>
                <td>${b.first_name} ${b.last_name}</td>
                <td>${b.grant_amount}</td>
                <td><span class="badge ${sClass}">${b.payment_status}</span></td>
                <td>${b.date_received}</td>
            </tr>`;
    });

    html += "</tbody></table>";
    document.getElementById("tabContent").innerHTML = html;

    updateFooter(1, beneficiaries.length, beneficiaries.length);
    document.getElementById("tableFooter").style.display = "flex";

}

/* FOOTER UPDATE */
function updateFooter(start, end, total) {
    const div = document.getElementById("tableFooter");

    const paginationHTML = `
        <div class="pagination-controls">
            <div class="page-arrow"><i data-lucide="chevrons-left" class="page-arrow-icon"></i></div>
            <div class="page-arrow"><i data-lucide="chevron-left" class="page-arrow-icon"></i></div>
            <div class="page-number">Page 1 of 1</div>
            <div class="page-arrow"><i data-lucide="chevron-right" class="page-arrow-icon"></i></div>
            <div class="page-arrow"><i data-lucide="chevrons-right" class="page-arrow-icon"></i></div>
        </div>
    `;

    if (total === 0) {
        div.innerHTML = `
            ${paginationHTML}
            <button class="reload-icon-btn" onclick="reloadMainUI()">
                <i data-lucide="refresh-ccw" class="reload-icon"></i>
            </button>
        `;
        refreshIcons();
        return;
    }

    div.innerHTML = `
        ${paginationHTML}
        <div>
            Showing ${start} - ${end} of ${total} records
            <button class="reload-icon-btn" onclick="reloadMainUI()">
                <i data-lucide="refresh-ccw" class="reload-icon"></i>
            </button>
        </div>
    `;

    refreshIcons();
}


/* =========================================================
   BENEFICIARY DETAILS
========================================================= */
function openBeneficiaryDetails(index) {
    const b = window.currentBeneficiaryRows[index];
    const school = tabs[activeTabIndex].payload.school;
    const fullName = `${b.first_name} ${b.last_name}`.trim();

    tabs = tabs.slice(0, activeTabIndex + 1);
    tabs.push({
        type: "beneficiaryDetails",
        label: `${fullName} Details`,
        payload: { beneficiary: b, school }
    });

    activeTabIndex = tabs.length - 1;
    renderTabs();
    renderActiveTabContent();
}

function renderBeneficiaryDetails({ beneficiary: b, school }) {

    const fullName = `${b.first_name} ${b.last_name}`.trim();

    // Extract image UUIDs safely
    const raw = (b.images || "").trim().toLowerCase();
    const invalid = ["", "n/a", "na", "none", "null", "undefined"];
    let uuids = [];

    if (!invalid.includes(raw)) {
        uuids = raw
            .split(",")
            .map(x => x.trim())
            .filter(x => x.length > 0 && !invalid.includes(x));
    }

    /* ---------------------------------------------
       BUILD MODERN UI HTML
    ---------------------------------------------- */

    let html = `
        <div class="beneficiary-details-wrapper">

            <div class="detail-section">

                <!-- SCHOOL INFO -->
                <h2 class="section-title">School Information</h2>

                <div class="detail-grid">
                    <div class="field">
                        <label>School</label>
                        <input disabled value="${school.school_name} (${school.school_emis})">
                    </div>

                    <div class="field">
                        <label>District</label>
                        <input disabled value="${school.school_district}">
                    </div>
                </div>

                <!-- BENEFICIARY INFO -->
                <h2 class="section-title">Beneficiary Information</h2>

                <div class="detail-grid">

                    <!-- NEW: Beneficiary Name (replaces ID) -->
                    <div class="field">
                        <label>Beneficiary Name</label>
                        <input disabled value="${fullName}">
                    </div>

                    <div class="field">
                        <label>Beneficiary No</label>
                        <input disabled value="${b.beneficiary_no}">
                    </div>

                    <div class="field">
                        <label>Grant Amount</label>
                        <input disabled value="${b.grant_amount}">
                    </div>

                    <div class="field">
                        <label>Status</label>
                        <input disabled value="${b.payment_status}">
                    </div>

                    <div class="field">
                        <label>Head of Household</label>
                        <input disabled value="${b.hhh_fname} ${b.hhh_lname}">
                    </div>

                    <div class="field">
                        <label>HHH NRC</label>
                        <input disabled value="${b.hhh_nrc_number}">
                    </div>
                </div>

                <!-- IMAGES SECTION -->
                <div class="images-box">
                    <h2 class="section-title">Photos & Supporting Images</h2>

                    ${
                        !uuids.length
                        ? `<p class='muted'>No images available.</p>`
                        : `
                            <p>This beneficiary has <strong>${uuids.length}</strong> image(s).</p>
                            <button id="viewImagesBtn" class="modern-btn"
                                    onclick='openImagesTab(${JSON.stringify(b)}, "${b.images}")'>
                                View ${fullName}'s Images →
                            </button>
                        `
                    }
                </div>

            </div>
        </div>
    `;

    document.getElementById("tabContent").innerHTML = html;
    document.getElementById("tableFooter").style.display = "none";

}



/* =========================================================
   IMAGES TAB
========================================================= */
function openImagesTab(beneficiary, uuidString) {

    let btn = document.getElementById("viewImagesBtn");
    if (btn) {
        btn.innerHTML = "<span class='spinner'></span> &nbsp; Loading...";
        btn.classList.add("loading-btn");
    }

    let uuids = uuidString.split(",")
        .map(x => x.trim())
        .filter(x => x);

    const fullName = `${beneficiary.first_name} ${beneficiary.last_name}`.trim();

    fetch(`/api/zispis/v1/trans-beneficiary-images?uuids=${uuids.join(",")}`)
        .then(res => res.json())
        .then(data => {

            tabs = tabs.slice(0, activeTabIndex + 1);
            tabs.push({
                type: "beneficiaryImages",
                label: `${fullName} Images`,
                payload: data.images || []
            });

            activeTabIndex = tabs.length - 1;
            renderTabs();
            renderActiveTabContent();
        });
}

function renderImagesTabv1(images) {

    if (!images.length) {
        document.getElementById("tabContent").innerHTML = `
            <h2 style="color:#1b5e20;">No Images Found</h2>
            <p class="muted">This beneficiary has no uploaded images.</p>`;
        return;
    }

    let html = `
        <h2 style="color:#1b5e20;">Images</h2>
        <p class="muted">Below are images uploaded for this beneficiary.</p>

        <div style="display:flex; gap:40px; flex-wrap:wrap; margin-top:20px;">`;

    images.forEach(img => {

        let label = (img.image_category === 1) ? "Beneficiary" : "Guardian";

        html += `
            <div style="text-align:center;">
                <img src="data:image/jpeg;base64,${img.image_url}"
                    onclick="openFullImage('data:image/jpeg;base64,${img.image_url}')"
                    style="width:220px; border-radius:6px; border:1px solid #ccc; cursor: zoom-in;">


                <div style="margin-top:6px; font-weight:600;">${label}</div>
            </div>`;
    });

    html += "</div>";
    document.getElementById("tabContent").innerHTML = html;
    document.getElementById("tableFooter").style.display = "none";

}

function renderImagesTab(images) {

    if (!images.length) {
        document.getElementById("tabContent").innerHTML = `
            <h2 style="color:#1b5e20;">No Images Found</h2>
            <p class="muted">This beneficiary has no uploaded images.</p>`;
        return;
    }

    let html = `
        <h2 style="color:#1b5e20;">Images</h2>
        <p class="muted">Below are images uploaded for this beneficiary.</p>

        <div style="display:flex; gap:40px; flex-wrap:wrap; margin-top:20px;">`;

    images.forEach(img => {

        let label = (img.image_category === 1) ? "Beneficiary" : "Guardian";

        html += `
            <div style="text-align:center;">
                <img src="${img.image_url}"
                    onclick="openFullImage('${img.image_url}')"
                    style="width:220px; border-radius:6px; border:1px solid #ccc; cursor: zoom-in;">

                <div style="margin-top:6px; font-weight:600;">${label}</div>
            </div>`;
    });

    html += "</div>";
    document.getElementById("tabContent").innerHTML = html;
    document.getElementById("tableFooter").style.display = "none";
}

function openFullImage(src) {
    const overlay = document.createElement("div");
    overlay.className = "image-modal-overlay";

    overlay.innerHTML = `
        <img src="${src}" onclick="this.parentElement.remove()" />
    `;

    document.body.appendChild(overlay);
}


/* START */
fetchData();
</script>

</body>
</html>
