<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schools Bank Information</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* ─────────────────────────────────────────────
           GLOBAL (same as your reference)
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
           FILTERS
        ─────────────────────────────────────────────── */
        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
            padding: 20px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .select-box,
        .search-box {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: #fff;
            font-size: 14px;
            min-width: 220px;
        }

        /* ─────────────────────────────────────────────
           TABLES (same style)
        ─────────────────────────────────────────────── */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 6px;
            background: white;
        }

        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            margin-top: 0;
            table-layout: fixed;
            border-radius: 6px;
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

        .loading-spinner {
            padding: 80px 20px;
            text-align: center !important;
            color: #555;
        }
        .loading-spinner::before {
            content: '';
            display: inline-block;
            width: 28px;
            height: 28px;
            border: 4px solid #1b5e20;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 12px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ─────────────────────────────────────────────
           BUTTONS & MENU (same as reference)
        ─────────────────────────────────────────────── */
        

        .kgs-menu {
            position: static;
            display: inline-block;
        }

        .kgs-menu-btn {
            background: transparent;
            color: #1b5e20;
            padding: 4px 8px;
            border: 1px solid #1b5e20;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            white-space: nowrap;
            font-weight: 500;
        }

        .kgs-menu-btn:hover {
            background: #f0f7f1;
        }

        .kgs-menu-list {
            display: none;
            position: fixed;
            background: #fff;
            border: 1px solid #ddd;
            min-width: 180px;
            list-style: none;
            padding: 4px 0;
            margin: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            border-radius: 4px;
        }

        .kgs-menu-list li {
            padding: 8px 16px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: left;
            gap: 8px;
            color: #333;
        }

        .kgs-menu-list li:hover {
            background: #f5f5f5;
        }

        .kgs-menu-list li.delete-option {
            color: #d32f2f;
        }

        .kgs-menu-list li.delete-option:hover {
            background: #ffebee;
        }

        /* Make sure Actions column doesn't expand */
        th:last-child,
        td:last-child {
            width: 120px;
            white-space: nowrap;
            position: relative;
        }

        /* ─────────────────────────────────────────────
           MODAL (Edit Bank Info)
        ─────────────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 520px;
            max-width: 92%;
            padding: 28px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.22);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-header h3 {
            margin: 0;
            color: #1b5e20;
            font-weight: 700;
        }

        .modal-body .form-group {
            margin-bottom: 20px;
        }

        .modal-body label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 14px;
        }

        .modal-body input,
        .modal-body select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
        }

        .btn-secondary {
            background: #ddd;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: opacity 0.2s, background 0.2s;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #ccc;
        }

        .btn-secondary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background: #1b5e20;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        /* ─────────────────────────────────────────────
           TOAST (same as reference)
        ─────────────────────────────────────────────── */
        #toastContainer {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 999999;
        }

        .toast {
            padding: 14px 24px;
            border-radius: 8px;
            color: white;
            font-size: 15px;
            margin-top: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
            animation: fadeIn 0.3s ease-out;
            max-width: 90%;
        }

        .toast-success { background: #2E6F40; }
        .toast-error   { background: #d9534f; }

        /* Required field validation */
        input:invalid,
        select:invalid {
            border-color: #d9534f !important;
        }

        input.error,
        select.error {
            border-color: #d9534f !important;
            background-color: #fff5f5;
        }

        .error-message {
            color: #d9534f;
            font-size: 12px;
            margin-top: 4px;
        }

        #editBankModal {
            z-index: 99; /* Ensure dropdown is above the modal */
        }

        #createBranchModal {
            z-index: 101; /* Ensure dropdown is above the modal */
        }

        /* ─────────────────────────────────────────────
            TABS
        ─────────────────────────────────────────────── */
        .tabs-container {
            display: flex;
            gap: 0;
        }

        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            color: #1b5e20;
            background: #f0f7f1;
        }

        .tab-btn.active {
            color: #1b5e20;
            border-bottom-color: #1b5e20;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <h2 id="pageHeading">Schools Bank Information</h2>
        <button class="export-btn" id="exportBtn">Export ▼</button>
    </div>

    <div class="container">

        <!-- Tabs -->
        <div class="tabs-container" style="margin-bottom: 20px; border-bottom: 2px solid #e0e0e0;">
            <button class="tab-btn active" id="tab-bank-info" onclick="switchTab('bank-info')">Bank Information</button>
            <button class="tab-btn" id="tab-consolidation" onclick="switchTab('consolidation')">School Consolidation</button>
        </div>

        <!-- Tab Content: Bank Information -->
        <div id="content-bank-info" class="tab-content active">
        <div id="breadcrumbs">
            <a href="#" onclick="loadSchools(); return false;">Schools Bank Information</a>
        </div>

        <!-- Filters -->
        <div class="filters">
            <select id="districtFilter" class="select-box">
                <option value="">All Districts</option>
                @foreach($districts as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
            </select>

            <input type="text" id="schoolSearch" class="search-box" placeholder="Search school name/emis..." />

            <button class="btn-primary" onclick="currentPage = 1; loadSchools();">Apply Filter</button>
        </div>

        <div id="paginationTop" style="padding: 15px 20px; background: white; border-bottom: 1px solid #e0e0e0; display: none; justify-content: space-between; align-items: center;">
            <div>
                <span id="recordsInfo" style="font-size: 14px; color: #666;"></span>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <label style="font-size: 14px; color: #666;">Per page:</label>
                <select id="perPageSelect" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
            </div>
        </div>

        <!-- Main Content Area -->
        <div id="appContainer" style="overflow: visible; position: relative; z-index: 1;">
            <div class="empty-state">Loading schools...</div>
        </div>

        <div id="paginationBottom" style="padding: 20px; background: white; display: none; justify-content: center; align-items: center; gap: 10px; border-top: 1px solid #e0e0e0; position: relative; z-index: 1;">
            <button id="prevPage" class="btn-secondary" style="padding: 8px 16px;" onclick="changePage(-1); return false;">← Previous</button>
            <span id="pageInfo" style="font-size: 14px; color: #666; padding: 0 20px;"></span>
            <button id="nextPage" class="btn-secondary" style="padding: 8px 16px;" onclick="changePage(1); return false;">Next →</button>
        </div>

        </div> <!-- End tab-content: bank-info -->

        <!-- Tab Content: School Consolidation -->
        <div id="content-consolidation" class="tab-content">
            @include('schoolmanagement::school_consolidation')
        </div>

    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <!-- Create Branch Modal -->
    <div id="createBranchModal" class="modal-overlay">
        <div class="modal-content" style="width: 420px;">
            <div class="modal-header">
                <h3>Create New Branch</h3>
                <button onclick="closeCreateBranchModal()" style="background:none;border:none;font-size:24px;cursor:pointer;">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="newBranchBankId">
                
                <div id="createBranchError" class="error-message" style="display: none; background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 15px;"></div>

                <div class="form-group">
                    <label>Bank</label>
                    <input type="text" id="newBranchBankName" readonly style="background: #f5f5f5;">
                </div>

                <div class="form-group">
                    <label>Branch Name <span style="color: red;">*</span></label>
                    <input type="text" id="newBranchName" placeholder="Enter branch name..." required>
                    <small style="color: #666; font-size: 12px;">Will be auto-capitalized</small>
                </div>

                <div class="form-group">
                    <label>Sort Code</label>
                    <input type="text" id="newBranchSortCode" placeholder="Optional" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeCreateBranchModal()">Cancel</button>
                <button class="btn-primary" onclick="saveNewBranch()">Create Branch</button>
            </div>
        </div>
    </div>

    <!-- Edit Branch Modal -->
    <div id="editBranchModal" class="modal-overlay">
        <div class="modal-content" style="width: 420px;">
            <div class="modal-header">
                <h3>Edit Branch</h3>
                <button onclick="closeEditBranchModal()" style="background:none;border:none;font-size:24px;cursor:pointer;">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editBranchId">
                <input type="hidden" id="editBranchBankId">
                
                <div id="editBranchError" class="error-message" style="display: none; background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 15px;"></div>

                <div class="form-group">
                    <label>Bank</label>
                    <input type="text" id="editBranchBankName" readonly style="background: #f5f5f5;">
                </div>

                <div class="form-group">
                    <label>Branch Name <span style="color: red;">*</span></label>
                    <input type="text" id="editBranchName" placeholder="Enter branch name..." required>
                    <small style="color: #666; font-size: 12px;">Will be auto-capitalized</small>
                </div>

                <div class="form-group">
                    <label>Sort Code</label>
                    <input type="text" id="editBranchSortCode" placeholder="Optional" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeEditBranchModal()">Cancel</button>
                <button class="btn-primary" onclick="saveEditedBranch()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Edit Bank Modal -->
    <div id="editBankModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Bank Information</h3>
                <button onclick="closeModal()" style="background:none;border:none;font-size:24px;cursor:pointer;">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editSchoolId">

                <div class="form-group">
                    <label>School Name</label>
                    <input type="text" id="schoolNameDisplay" readonly>
                </div>

                <div class="form-group">
                    <label>Bank</label>
                    <select id="bankSelect" class="select2" required>
                        <option value="">Select Bank...</option>
                        @foreach($banks as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Branch</label>
                    <select id="branchSelect" class="select2" required>
                        <option value="">Select branch...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" id="accountNumber" maxlength="30" required>
                </div>

                <div class="form-group">
                    <label>Sort Code</label>
                    <input type="text" id="sortCode" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn-primary" onclick="saveBankDetails()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let pendingBranchCallback = null;
        let currentSchoolId = null;
        const BASE_URL = '{{ url('/') }}';

        let currentPage = 1;
        let perPage = 50;
        let totalPages = 1;
        let totalRecords = 0;

        // Tab switching function
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('content-' + tabName).classList.add('active');
            
            // Update page title and heading based on active tab
            const tabTitles = {
                'bank-info': 'Schools Bank Information',
                'consolidation': 'School Consolidation'
            };
            const title = tabTitles[tabName] || 'KGS Schools Management';
            document.title = title;
            document.getElementById('pageHeading').textContent = title;
            
            // If switching to consolidation tab, reinitialize it
            if (tabName === 'consolidation' && typeof window.reinitConsolidationTab === 'function') {
                window.reinitConsolidationTab();
            }
        }

        $(document).ready(function() {
            // Initialize select2 AFTER DOM is ready
            initializeSelect2();

            // Per page change handler
            $('#perPageSelect').on('change', function() {
                perPage = parseInt($(this).val());
                currentPage = 1; // Reset to first page
                loadSchools();
            });

            loadSchools();
        });

        function initializeSelect2() {
                $('.select2').select2({
                placeholder: "Select option",
                allowClear: true,
                width: '100%'
            });
            $('#districtFilter').select2({
                placeholder: "All Districts",
                allowClear: true,
                width: '220px'
            });

            // Load schools when district changes
            $('#districtFilter').on('change', function() {
                currentPage = 1; // Reset to first page
                loadSchools();
            });

            // Load branches when bank changes
            $('#bankSelect').on('change', function() {
                const bankId = $(this).val();
                console.log('Bank changed to:', bankId); // Debug
                loadBranches(bankId);
            });

            // Handle "Create New Branch" selection
            $('#branchSelect').on('change', function() {
                if ($(this).val() === '__CREATE_NEW__') {
                    const bankId = $('#bankSelect').val();
                    if (bankId) {
                        openCreateBranchModal(bankId);
                    } else {
                        showToast("Please select a bank first", "error");
                        $(this).val('').trigger('change');
                    }
                }
            });
        }

        function toggleMenu(event) {
            event.stopPropagation();
            const button = event.target.closest('.kgs-menu-btn');
            const menu = button.nextElementSibling;
            
            // Close all other menus
            document.querySelectorAll('.kgs-menu-list').forEach(m => {
                if (m !== menu) m.style.display = 'none';
            });
            
            // Toggle this menu
            if (menu.style.display === 'none' || menu.style.display === '') {
                menu.style.display = 'block';
                // Position the menu relative to the button
                const rect = button.getBoundingClientRect();
                menu.style.position = 'fixed';
                menu.style.top = (rect.bottom + 4) + 'px';
                menu.style.left = rect.left + 'px';
            } else {
                menu.style.display = 'none';
            }
        }


        function openCreateBranchModal(bankId) {
            const bankName = $('#bankSelect option:selected').text();
            
            document.getElementById('newBranchBankId').value = bankId;
            document.getElementById('newBranchBankName').value = bankName;
            document.getElementById('newBranchName').value = '';
            document.getElementById('newBranchSortCode').value = '';
            
            // Reset branch select to empty
            $('#branchSelect').val('').trigger('change');
            
            document.getElementById('createBranchModal').style.display = 'flex';
            
            // Focus on branch name input
            setTimeout(() => {
                document.getElementById('newBranchName').focus();
            }, 300);
        }

        function closeCreateBranchModal() {
            document.getElementById('createBranchModal').style.display = 'none';
            // Clear error message
            const errorDiv = document.getElementById('createBranchError');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            // Clear form fields
            document.getElementById('newBranchName').value = '';
            document.getElementById('newBranchSortCode').value = '';
            // Reset branch select
            $('#branchSelect').val('').trigger('change');
        }

        function saveNewBranch() {
            // Clear previous errors
            $('.error').removeClass('error');
            $('.error-message').remove();
            const errorDiv = document.getElementById('createBranchError');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }

            const bankId = document.getElementById('newBranchBankId').value;
            let branchName = document.getElementById('newBranchName').value.trim();
            const sortCode = document.getElementById('newBranchSortCode').value.trim();

            if (!branchName) {
                document.getElementById('newBranchName').classList.add('error');
                document.getElementById('newBranchName').insertAdjacentHTML('afterend', 
                    '<div class="error-message">Branch name is required</div>');
                showToast("Branch name is required", "error");
                return;
            }

            // Auto-capitalize
            branchName = branchName.toUpperCase();

            const payload = {
                bank_id: bankId,
                name: branchName,
                sort_code: sortCode
            };

            // Show loading state
            const saveBtn = document.querySelector('#createBranchModal .btn-primary');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Creating...';
            saveBtn.disabled = true;

            fetch(`${BASE_URL}/schoolmanagement/create-branch`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;

                if (data.success) {
                    showToast("Branch created successfully");
                    closeCreateBranchModal();
                    
                    // Reload branches for this bank
                    loadBranches(bankId);
                    
                    // After reload completes, select the new branch
                    setTimeout(() => {
                        $('#branchSelect').val(data.branch_id).trigger('change');
                    }, 500);
                } else {
                    // Show error message in the modal
                    const errorDiv = document.getElementById('createBranchError');
                    if (errorDiv) {
                        errorDiv.textContent = data.message || "Failed to create branch";
                        errorDiv.style.display = 'block';
                    }
                    showToast(data.message || "Failed to create branch", "error");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
                showToast("Network error. Try again.", "error");
            });
        }

        function closeEditBranchModal() {
            document.getElementById('editBranchModal').style.display = 'none';
            // Clear error message
            const errorDiv = document.getElementById('editBranchError');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            // Reset form
            document.getElementById('editBranchId').value = '';
            document.getElementById('editBranchBankId').value = '';
            document.getElementById('editBranchName').value = '';
            document.getElementById('editBranchSortCode').value = '';
        }

        function openEditBranchModal(branchId, bankId, bankName, branchName, sortCode) {
            // Clear error message
            const errorDiv = document.getElementById('editBranchError');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            document.getElementById('editBranchId').value = branchId;
            document.getElementById('editBranchBankId').value = bankId;
            document.getElementById('editBranchBankName').value = bankName;
            document.getElementById('editBranchName').value = branchName || '';
            document.getElementById('editBranchSortCode').value = sortCode || '';
            document.getElementById('editBranchModal').style.display = 'flex';
        }

        function saveEditedBranch() {
            // Clear previous errors
            $('.error').removeClass('error');
            $('.error-message').remove();
            const errorDiv = document.getElementById('editBranchError');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }

            const branchId = document.getElementById('editBranchId').value;
            const bankId = document.getElementById('editBranchBankId').value;
            let branchName = document.getElementById('editBranchName').value.trim();
            const sortCode = document.getElementById('editBranchSortCode').value.trim();

            if (!branchName) {
                document.getElementById('editBranchName').classList.add('error');
                document.getElementById('editBranchName').insertAdjacentElement('afterend', 
                    '<div class="error-message">Branch name is required</div>');
                showToast("Branch name is required", "error");
                return;
            }

            // Auto-capitalize
            branchName = branchName.toUpperCase();

            const payload = {
                branch_id: branchId,
                bank_id: bankId,
                name: branchName,
                sort_code: sortCode
            };

            // Show loading state
            const saveBtn = document.querySelector('#editBranchModal .btn-primary');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            fetch(`${BASE_URL}/schoolmanagement/update-branch`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;

                if (data.success) {
                    showToast("Branch updated successfully");
                    closeEditBranchModal();
                    
                    // Reload branches for this bank
                    loadBranches(bankId);
                } else {
                    // Show error message in the modal
                    const errorDiv = document.getElementById('editBranchError');
                    if (errorDiv) {
                        errorDiv.textContent = data.message || "Failed to update branch";
                        errorDiv.style.display = 'block';
                    }
                    showToast(data.message || "Failed to update branch", "error");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
                showToast("Network error. Try again.", "error");
            });
        }

        // Allow Enter key to submit in edit modal
        document.addEventListener('DOMContentLoaded', function() {
            const branchNameInput = document.getElementById('newBranchName');
            if (branchNameInput) {
                branchNameInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveNewBranch();
                    }
                });
            }
            
            // Add Enter key support for edit branch modal
            const editBranchNameInput = document.getElementById('editBranchName');
            if (editBranchNameInput) {
                editBranchNameInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveEditedBranch();
                    }
                });
            }
            
            // Setup branch dropdown with edit option
            setTimeout(() => {
                if (typeof setupBranchDropdownWithEdit === 'function') {
                    setupBranchDropdownWithEdit();
                }
            }, 1000);
        });

        function deleteSchoolBank(schoolId, schoolName) {
            // TODO: Show toast to tell them to head to consolidation to avoid data then after 3 seconds
            showToast("Please go to Consolidation section in order to avoid beneficiary data loss", "info");
            // if (!confirm(`Are you sure you want to delete this school:\n${schoolName}\n\nThis action cannot be undone.`)) {
            //     return;
            // }

            // fetch(`${BASE_URL}/schoolmanagement/delete-school-bank/${schoolId}`, {
            //     method: 'DELETE',
            //     headers: {
            //         'Content-Type': 'application/json',
            //         'X-CSRF-TOKEN': '{{ csrf_token() }}'
            //     }
            // })
            // .then(res => res.json())
            // .then(data => {
            //     if (data.success) {
            //         showToast(`School ${schoolId}:${schoolName} deleted successfully`);
            //         loadSchools();
            //     } else {
            //         showToast(data.message || "Delete failed", "error");
            //     }
            // })
            // .catch(() => showToast("Network error. Try again.", "error"));
        }

        function showToast(message, type = "success") {
            const container = document.getElementById("toastContainer");
            const toast = document.createElement("div");
            toast.className = `toast ${type === "success" ? "toast-success" : "toast-error"}`;
            toast.textContent = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = "0";
                setTimeout(() => toast.remove(), 400);
            }, 4500);
        }

        function closeModal() {
            document.getElementById("editBankModal").style.display = "none";
            currentSchoolId = null;
        }

        function openEditModal(schoolId, schoolName) {
            currentSchoolId = schoolId;
    
            document.getElementById("editSchoolId").value = schoolId;
            document.getElementById("schoolNameDisplay").value = schoolName;

            // Reinitialize select2 for modal fields
            $('#bankSelect').select2({
                placeholder: "Select Bank...",
                dropdownParent: $('#editBankModal'),  // CRITICAL: Attach to modal
                width: '100%'
            });

            $('#branchSelect').select2({
                placeholder: "Select Branch...",
                dropdownParent: $('#editBankModal'),  // CRITICAL: Attach to modal
                width: '100%'
            });

            // Fetch current bank info
            fetch(`${BASE_URL}/schoolmanagement/school-bank-info/${schoolId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        const info = data.data;
                        $('#bankSelect').val(info.bank_id).trigger('change');
                        setTimeout(() => {
                            $('#branchSelect').val(info.branch_name).trigger('change');
                        }, 600);

                        document.getElementById("accountNumber").value = info.account_no || '';
                        document.getElementById("sortCode").value     = info.sort_code || '';
                    } else {
                        $('#bankSelect').val(null).trigger('change');
                        document.getElementById("accountNumber").value = '';
                        document.getElementById("sortCode").value = '';
                    }
                })
                .catch(() => showToast("Could not load bank information", "error"));

            document.getElementById("editBankModal").style.display = "flex";
        }

        // function loadBranches(bankId) {
        //     if (!bankId) {
        //         $('#branchSelect').empty().append('<option value="">Select branch...</option>').trigger('change');
        //         return;
        //     }

        //     fetch(`${BASE_URL}/schoolmanagement/bank-branches/${bankId}`)
        //         .then(res => res.json())
        //         .then(data => {
        //             const select = $('#branchSelect');
        //             select.empty().append('<option value="">Select branch...</option>');

        //             if (data.success && data.branches) {
        //                 data.branches.forEach(b => {
        //                     select.append(`<option value="${b.id}">${b.name}${b.sort_code ? ' ('+b.sort_code+')' : ''}</option>`);
        //                 });
        //             }
        //             select.trigger('change');
        //         });
        // }

        function loadBranches(bankId) {
            const branchSelect = $('#branchSelect');
            
            if (!bankId) {
                branchSelect.empty()
                    .append('<option value="">Select branch...</option>')
                    .trigger('change');
                return;
            }

            // Show loading
            branchSelect.empty()
                .append('<option value="">Loading branches...</option>')
                .trigger('change')
                .prop('disabled', true);

            fetch(`${BASE_URL}/schoolmanagement/bank-branches/${bankId}`)
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.json();
                })
                .then(data => {
                    branchSelect.empty().prop('disabled', false);
                    
                    // Add default option
                    branchSelect.append('<option value="">Select branch...</option>');
                    
                    // Add "Create New Branch" option
                    branchSelect.append('<option value="__CREATE_NEW__" style="background: #e8f5e9; font-weight: 600;">+ Create New Branch</option>');
                    
                    if (data.success && data.branches && data.branches.length > 0) {
                        data.branches.forEach(b => {
                            const sortCode = b.sort_code ? ` (${b.sort_code})` : '';
                            branchSelect.append(`<option value="${b.id}" data-bank-id="${b.bank_id}" data-bank-name="${data.bankName || ''}" data-branch-name="${b.name}" data-sort-code="${b.sort_code || ''}">${b.name}${sortCode}</option>`);
                        });
                    }
                    
                    branchSelect.trigger('change');
                })
                .catch(error => {
                    console.error('Error loading branches:', error);
                    branchSelect.empty()
                        .append('<option value="">Error loading branches</option>')
                        .append('<option value="__CREATE_NEW__" style="background: #e8f5e9; font-weight: 600;">+ Create New Branch</option>')
                        .prop('disabled', false)
                        .trigger('change');
                    showToast("Failed to load branches", "error");
                });
        }

        // Handle branch select change to detect edit request
        $(document).on('change', '#branchSelect', function() {
            const selectedOption = $(this).find('option:selected');
            const branchId = $(this).val();
            
            // Check if user wants to edit a branch (by checking data attribute)
            if (branchId && branchId !== '__CREATE_NEW__' && branchId !== '') {
                const bankId = selectedOption.data('bank-id');
                const bankName = selectedOption.data('bank-name');
                const branchName = selectedOption.data('branch-name');
                const sortCode = selectedOption.data('sort-code');
                
                // Check if user wants to edit - we'll add a small edit button or use right-click
                // For now, let's use a confirm dialog approach or we could add a custom dropdown item
            }
        });

        // Add custom handler for branch edit - we'll modify the dropdown to include edit option
        function setupBranchDropdownWithEdit() {
            const branchSelect = $('#branchSelect');
            
            // Store original select2 opening
            const originalOpen = branchSelect.select2.prototype.bind;
            
            branchSelect.on('select2:open', function() {
                // Add edit button after dropdown opens
                setTimeout(() => {
                    const dropdown = document.querySelector('.select2-results__options');
                    if (dropdown && !dropdown.querySelector('.branch-edit-btn')) {
                        // Add edit section at the bottom
                        const currentBankId = $('#bankSelect').val();
                        const currentBranchId = $('#branchSelect').val();
                        
                        if (currentBranchId && currentBranchId !== '__CREATE_NEW__' && currentBranchId !== '') {
                            const editLink = document.createElement('div');
                            editLink.className = 'branch-edit-btn';
                            editLink.style.cssText = 'padding: 10px 15px; border-top: 1px solid #acf5ae; cursor: pointer; color: #2e6f40; font-weight: 500;';
                            editLink.textContent = 'Edit Selected Branch';
                            editLink.onclick = function(e) {
                                e.stopPropagation();
                                const option = $(`#branchSelect option[value="${currentBranchId}"]`);
                                const bankId = option.data('bank-id');
                                const bankName = option.data('bank-name');
                                const branchName = option.data('branch-name');
                                const sortCode = option.data('sort-code');
                                branchSelect.select2('close');
                                openEditBranchModal(currentBranchId, bankId, bankName, branchName, sortCode);
                            };
                            dropdown.appendChild(editLink);
                        }
                    }
                }, 100);
            });
        }

        // function saveBankDetails() {
        //     const payload = {
        //         school_id: currentSchoolId,
        //         bank_id: $('#bankSelect').val(),
        //         branch_id: $('#branchSelect').val(),
        //         account_no: document.getElementById("accountNumber").value.trim(),
        //         sort_code: document.getElementById("sortCode").value.trim()
        //     };

        //     if (!payload.bank_id) {
        //         showToast("Please select a bank", "error");
        //         return;
        //     }

        //     fetch(`${BASE_URL}/schoolmanagement/update-school-bank`, {
        //         method: 'POST',
        //         headers: {
        //             'Content-Type': 'application/json',
        //             'X-CSRF-TOKEN': '{{ csrf_token() }}'
        //         },
        //         body: JSON.stringify(payload)
        //     })
        //     .then(res => res.json())
        //     .then(data => {
        //         if (data.success) {
        //             showToast("Bank details updated successfully");
        //             closeModal();
        //             loadSchools(); // refresh list
        //         } else {
        //             showToast(data.message || "Update failed", "error");
        //         }
        //     })
        //     .catch(() => showToast("Network error. Try again.", "error"));
        // }

        function saveBankDetails() {
            // Clear previous errors
            $('.error').removeClass('error');
            $('.error-message').remove();

            const bankId = $('#bankSelect').val();
            const branchId = $('#branchSelect').val();
            const accountNo = document.getElementById("accountNumber").value.trim();

            let hasError = false;

            // Validate bank
            if (!bankId) {
                $('#bankSelect').next('.select2-container').find('.select2-selection').addClass('error');
                $('#bankSelect').after('<div class="error-message">Bank is required</div>');
                hasError = true;
            }

            // Validate branch
            if (!branchId) {
                $('#branchSelect').next('.select2-container').find('.select2-selection').addClass('error');
                $('#branchSelect').after('<div class="error-message">Branch is required</div>');
                hasError = true;
            }

            // Validate account number
            if (!accountNo) {
                document.getElementById("accountNumber").classList.add('error');
                document.getElementById("accountNumber").insertAdjacentHTML('afterend', '<div class="error-message">Account number is required</div>');
                hasError = true;
            }

            if (hasError) {
                showToast("Please fill all required fields", "error");
                return;
            }

            const payload = {
                school_id: currentSchoolId,
                bank_id: bankId,
                branch_id: branchId,
                account_no: accountNo,
                sort_code: document.getElementById("sortCode").value.trim()
            };

            fetch(`${BASE_URL}/schoolmanagement/update-school-bank`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast("Bank details updated successfully");
                    closeModal();
                    loadSchools();
                } else {
                    showToast(data.message || "Update failed", "error");
                }
            })
            .catch(() => showToast("Network error. Try again.", "error"));
        }

        function changePage(direction) {
            const newPage = currentPage + direction;
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                console.log('Changing to page:', currentPage); // Debug
                loadSchools();
                // Scroll to top of table
                setTimeout(() => {
                    document.getElementById('appContainer').scrollIntoView({ behavior: 'smooth' });
                }, 100);
            }
            return false;
        }

        function updatePagination(pagination) {
            totalPages = parseInt(pagination.last_page) || 1;
            totalRecords = parseInt(pagination.total) || 0;
            currentPage = parseInt(pagination.current_page) || 1;
            
            const start = ((currentPage - 1) * perPage) + 1;
            const end = Math.min(currentPage * perPage, totalRecords);
            
            // Update info text
            document.getElementById('recordsInfo').textContent = 
                `Showing ${start} to ${end} of ${totalRecords} schools`;
            document.getElementById('pageInfo').textContent = 
                `Page ${currentPage} of ${totalPages}`;
            
            // Show/hide pagination controls
            if (totalRecords > perPage) {
                document.getElementById('paginationTop').style.display = 'flex';
                document.getElementById('paginationBottom').style.display = 'flex';
            } else {
                document.getElementById('paginationTop').style.display = 'none';
                document.getElementById('paginationBottom').style.display = 'none';
            }
            
            // Enable/disable buttons based on current page
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            
            if (currentPage === 1) {
                prevBtn.disabled = true;
                prevBtn.style.opacity = '0.5';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.opacity = '1';
                prevBtn.style.cursor = 'pointer';
            }
            
            if (currentPage === totalPages) {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.5';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
            }
        }

        function loadSchools() {
            const appContainer = document.getElementById("appContainer");
            console.log('Loading schools with filters - District:', document.getElementById("districtFilter").value, 'Search:', document.getElementById("schoolSearch").value);
            appContainer.innerHTML = `
                <div class="loading-spinner">
                    <span style="display: block; text-align: center;">Loading schools...</span>
                </div>
            `;

            const district = document.getElementById("districtFilter").value;
            const search = document.getElementById("schoolSearch").value.trim();

            let url = `${BASE_URL}/schoolmanagement/schools-bank-list`;
            let params = [];

            if (district) params.push(`district_id=${district}`);
            if (search) params.push(`search=${encodeURIComponent(search)}`);
            params.push(`page=${currentPage}`);
            params.push(`per_page=${perPage}`);

            if (params.length) url += '?' + params.join('&');

            fetch(url)
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    let html = '';

                    if (!data.success || !data.schools?.length) {
                        html = '<div class="empty-state">No schools found matching your filter.</div>';
                        document.getElementById('paginationTop').style.display = 'none';
                        document.getElementById('paginationBottom').style.display = 'none';
                    } else {
                        // Update pagination
                        updatePagination(data.pagination);
                        
                        html = `
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>School Name</th>
                                            <th>EMIS Code</th>
                                            <th>District</th>
                                            <th>Bank</th>
                                            <th>Branch</th>
                                            <th>Account No</th>
                                            <th>Sort Code</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        data.schools.forEach(s => {
                            // Highlight rows without bank details
                            const missingClass = (!s.bank_name || !s.account_no) ? 'style="background: #fff3cd;"' : '';
                            
                            html += `
                                <tr ${missingClass}>
                                    <td>${s.school_name || '-'}</td>
                                    <td>${s.emis || '-'}</td>
                                    <td>${s.district_name || '-'}</td>
                                    <td>${s.bank_name || '<span style="color: #dc3545;">Missing</span>'}</td>
                                    <td>${s.branch_name || '<span style="color: #dc3545;">Missing</span>'}</td>
                                    <td>${s.account_no || '<span style="color: #dc3545;">Missing</span>'}</td>
                                    <td>${s.sort_code || '-'}</td>
                                    <td>
                                        <div class="kgs-menu">
                                            <button class="kgs-menu-btn" onclick="toggleMenu(event)">Actions ▼</button>
                                            <ul class="kgs-menu-list" data-menu-id="menu-${s.school_id}">
                                                <li onclick="event.stopPropagation(); openEditModal('${s.school_id}', '${(s.school_name || '').replace(/'/g,"\\'")}'); closeAllMenus();">
                                                    Edit Bank Details
                                                </li>
                                                <li class="delete-option" onclick="event.stopPropagation(); deleteSchoolBank('${s.school_id}', '${(s.school_name || '').replace(/'/g,"\\'")}'); closeAllMenus();">
                                                    Delete School
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });

                        html += '</tbody></table></div>';
                    }

                    appContainer.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    appContainer.innerHTML = `
                        <div class="empty-state">
                            Error loading schools.<br>
                            <small>${err.message || 'Network or server issue'}</small><br><br>
                            <button onclick="loadSchools()" style="padding:8px 16px; background:#1b5e20; color:white; border:none; border-radius:6px; cursor:pointer;">
                                Try Again
                            </button>
                        </div>
                    `;
                    document.getElementById('paginationTop').style.display = 'none';
                    document.getElementById('paginationBottom').style.display = 'none';
                });
        }

        function closeAllMenus() {
            document.querySelectorAll('.kgs-menu-list').forEach(m => m.style.display = 'none');
        }

    
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.kgs-menu')) {
                document.querySelectorAll('.kgs-menu-list').forEach(m => m.style.display = 'none');
            }
        });

        // Export placeholder (you can implement CSV/Excel later)
        document.getElementById('exportBtn')?.addEventListener('click', () => {
            alert("Export functionality coming soon...");
        });
    </script>
</body>
</html>