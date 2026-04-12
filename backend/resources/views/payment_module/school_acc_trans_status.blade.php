<!DOCTYPE html>
<html>
<head>
    <title>School Accountant Submissions</title>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg,#eef2f7,#f9fbfd);
            margin: 0;
            padding: 30px;
        }

        h1 {
            color:#1b5e20;
            margin-bottom: 20px;
        }

        /* CARD */
        .card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        select {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 15px;
        }

        /* TABLE */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }

        /* HEADER */
        th {
            background: #1b5e20;
            color: white;
            text-align: left;              /* 🔥 FIX */
            padding: 12px 14px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        /* CELLS */
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;              /* 🔥 FIX */
            color: #333;
        }

        /* ROWS */
        tbody tr {
            transition: background 0.2s ease;
        }

        /* ZEBRA STRIPES */
        tbody tr:nth-child(even) {
            background: #fafafa;
        }

        /* HOVER */
        tbody tr:hover {
            background: #e8f5e9;
            cursor: pointer;
        }

        /* OPTIONAL: tighter numeric columns */
        td:last-child,
        th:last-child {
            text-align: right;             /* numbers align right */
        }

        /* BUTTONS */
        button {
            padding:8px 14px;
            border:none;
            border-radius:6px;
            background:#1b5e20;
            color:white;
            cursor:pointer;
        }

        button:disabled {
            opacity:0.4;
            cursor:not-allowed;
        }

        /* LOADER */
        .loader-overlay {
            position: fixed;
            top:0; left:0;
            width:100%;
            height:100%;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(6px);
            display:flex;
            align-items:center;
            justify-content:center;
            z-index:9999;
            opacity:0;
            pointer-events:none;
            transition:0.3s;
        }

        .loader-overlay.active {
            opacity:1;
            pointer-events:all;
        }

        .loader-box {
            background:white;
            padding:30px;
            border-radius:12px;
            box-shadow:0 10px 30px rgba(0,0,0,0.1);
            text-align:center;
        }

        .spinner {
            width:40px;
            height:40px;
            border:4px solid #eee;
            border-top:4px solid #1b5e20;
            border-radius:50%;
            animation:spin 0.8s linear infinite;
            margin:auto;
        }

        @keyframes spin {
            from {transform:rotate(0deg);}
            to {transform:rotate(360deg);}
        }

        .loader-text {
            margin-top:10px;
            font-weight:500;
            color:#555;
        }

        .empty {
            text-align:center;
            padding:40px;
            color:#777;
        }
    </style>
</head>

<body>

<h1>School Accountant Submissions Dashboard</h1>

<div class="card">

    <select id="districtSelect" onchange="loadSchools()">
        <option value="">Select District</option>
    </select>

    <div id="content" class="empty">
        Please select a district to view data
    </div>

</div>

<!-- LOADER -->
<div id="loader" class="loader-overlay">
    <div class="loader-box">
        <div class="spinner"></div>
        <div id="loaderText" class="loader-text">Loading...</div>
    </div>
</div>

<script>

let currentSchool = null;
let currentPage = 1;
let loaderStart = 0;

/* ======================
   ENTERPRISE LOADER
====================== */
function showLoader(msg="Loading..."){
    loaderStart = Date.now();
    document.getElementById("loaderText").innerText = msg;
    document.getElementById("loader").classList.add("active");
}

function hideLoader(){
    let elapsed = Date.now() - loaderStart;

    let delay = Math.max(0, 3000 - elapsed); // 🔥 FORCE MIN 3s

    setTimeout(()=>{
        document.getElementById("loader").classList.remove("active");
    }, delay);
}

/* ======================
   LOAD DISTRICTS
====================== */
async function loadDistricts(){

    showLoader("Loading districts...");

    let res = await fetch('/api/zispis/v1/sa-districts');
    let data = await res.json();

    let select = document.getElementById("districtSelect");

    data.districts.forEach(d=>{
        let opt = document.createElement("option");
        opt.value = d.id;
        opt.textContent = d.name;
        select.appendChild(opt);
    });

    hideLoader();
}

/* ======================
   LOAD SCHOOLS
====================== */
async function loadSchools(){

    let districtId = document.getElementById("districtSelect").value;
    if(!districtId) return;

    showLoader("Fetching schools...");

    let res = await fetch(`/api/zispis/v1/sa-schools?district_id=${districtId}`);
    let data = await res.json();

    let html = `<h3>Schools</h3><table>
        <tr><th>Name</th><th>EMIS</th><th>Total</th></tr>`;

    data.schools.forEach(s=>{
        html += `
            <tr onclick="openSchool(${s.id})">
                <td>${s.name}</td>
                <td>${s.code}</td>
                <td>${s.total}</td>
            </tr>`;
    });

    html += "</table>";

    document.getElementById("content").innerHTML = html;

    hideLoader();
}

/* ======================
   OPEN SCHOOL
====================== */
function openSchool(id){
    currentSchool = id;
    currentPage = 1;
    loadBeneficiaries();
}

/* ======================
   LOAD BENEFICIARIES
====================== */
async function loadBeneficiaries(){

    showLoader(`Loading page ${currentPage}...`);

    let res = await fetch(
        `/api/zispis/v1/sa-beneficiaries?school_id=${currentSchool}&page=${currentPage}`
    );

    let data = await res.json();

    let html = `<h3>Beneficiaries</h3><table>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Beneficiary No</th>
            <th>Status</th>
        </tr>`;

    data.data.forEach((b,i)=>{

        html += `
            <tr 
                ondblclick="openBeneficiary('${b.beneficiary_no}', '${(b.images || '').replace(/'/g, "\\'")}')"
                title="Double click to view details"
            >
                <td>${i+1}</td>
                <td>${b.first_name} ${b.last_name}</td>
                <td>${b.beneficiary_no}</td>
                <td>${b.payment_status}</td>
            </tr>`;
    });

    html += "</table>";

    html += `
        <div style="margin-top:15px;">
            <button onclick="prevPage()" ${currentPage==1?'disabled':''}>Prev</button>
            Page ${currentPage} of ${data.last_page}
            <button onclick="nextPage(${data.last_page})" ${currentPage==data.last_page?'disabled':''}>Next</button>
        </div>
    `;

    document.getElementById("content").innerHTML = html;

    hideLoader();
}
/* ======================
   LOAD BENEFICIARY RECORD
====================== */
async function openBeneficiary(beneficiaryNo, imageString) {

    showLoader("Loading beneficiary details...");

    try {

        /* ======================
           FETCH META DATA
        ====================== */
        let res = await fetch(`/api/zispis/v1/sa-beneficiary-details?beneficiary_no=${beneficiaryNo}`);
        let data = await res.json();

        if (!data.status) {
            document.getElementById("content").innerHTML =
                "<p>No beneficiary data found</p>";
            hideLoader();
            return;
        }

        let b = data.data;

        /* ======================
           FETCH IMAGES
        ====================== */
        let images = [];

        if (imageString && imageString !== "null") {
            let imgRes = await fetch(`/api/zispis/v1/trans-beneficiary-images?uuids=${imageString}`);
            let imgData = await imgRes.json();
            images = imgData.images || [];
        }

        /* ======================
           BUILD UI
        ====================== */

        let html = `
            <h2>Beneficiary Details</h2>

            <div class="detail-grid">

                <div class="field">
                    <label>Full Name</label>
                    <input disabled value="${b.first_name} ${b.last_name}">
                </div>

                <div class="field">
                    <label>Beneficiary Number</label>
                    <input disabled value="${b.beneficiary_no}">
                </div>

                <div class="field">
                    <label>School</label>
                    <input disabled value="${b.school_name || '-'}">
                </div>

                <div class="field">
                    <label>Grade</label>
                    <input disabled value="${b.school_grade || '-'}">
                </div>

                <div class="field">
                    <label>Household Head</label>
                    <input disabled value="${b.hhh_fname} ${b.hhh_lname}">
                </div>

                <div class="field">
                    <label>NRC</label>
                    <input disabled value="${b.hhh_nrc_number || '-'}">
                </div>

                <div class="field">
                    <label>Guardian Phone</label>
                    <input disabled value="${b.mobile_phone_parent_guardian || '-'}">
                </div>

            </div>
        `;

        /* ======================
           IMAGES SECTION
        ====================== */

        if (!images.length) {

            html += `
                <div class="images-box">
                    <h3>Images</h3>
                    <p class="muted">
                        No images found for <strong>${b.first_name} ${b.last_name}</strong>
                    </p>
                </div>
            `;

        } else {

            html += `
                <div class="images-box">
                    <h3>Images</h3>

                    <div style="display:flex; gap:20px; flex-wrap:wrap;">
            `;

            const categoryMap = {
                1: "Beneficiary Image",
                2: "Beneficiary Signature",
                3: "Guardian Image",
                4: "Guardian Signature",
                5: "Teacher Image",
                6: "Teacher Signature"
            };

            images.forEach(img => {

                let label = categoryMap[img.image_category] || "Image";

                html += `
                    <div style="text-align:center;">
                        <img src="${img.image_url}"
                             onclick="openFullImage('${img.image_url}')"
                             style="width:200px; border-radius:8px; border:1px solid #ddd; cursor:zoom-in;">
                        <div style="margin-top:5px; font-weight:600;">${label}</div>
                    </div>
                `;
            });

            html += `</div></div>`;
        }

        document.getElementById("content").innerHTML = html;

    } catch (e) {
        document.getElementById("content").innerHTML =
            "<p style='color:red'>Failed to load beneficiary details</p>";
    }

    hideLoader();
}

/* ======================
   FULL SCREEN IMAGE
====================== */
function openFullImage(src) {

    const overlay = document.createElement("div");

    overlay.style = `
        position:fixed;
        top:0; left:0;
        width:100%; height:100%;
        background:rgba(0,0,0,0.85);
        display:flex;
        align-items:center;
        justify-content:center;
        z-index:99999;
    `;

    overlay.innerHTML = `
        <img src="${src}" 
             style="max-width:90%; max-height:90%; border-radius:8px;"
             onclick="this.parentElement.remove()">
    `;

    document.body.appendChild(overlay);
}

/* ======================
   PAGINATION
====================== */
function nextPage(lastPage){
    if(currentPage < lastPage){
        currentPage++;
        loadBeneficiaries();
    }
}

function prevPage(){
    if(currentPage > 1){
        currentPage--;
        loadBeneficiaries();
    }
}

/* INIT */
loadDistricts();

</script>

</body>
</html>