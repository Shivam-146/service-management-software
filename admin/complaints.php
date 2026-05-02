<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';

// Handle technician assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_tech'])) {
    $complaintId = $_POST['complaint_id'];
    $techId = $_POST['tech_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE complaints SET assigned_tech_id = ?, status = 'Assigned', assigned_at = CURRENT_TIMESTAMP, started_at = NULL, completed_at = NULL WHERE id = ?");
        $stmt->execute([$techId, $complaintId]);
        $successMsg = "Technician assigned successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Handle Add Customer (Inline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = trim($_POST['customer_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $gst = trim($_POST['gst_number']);
    $amc = isset($_POST['has_active_amc']) ? 1 : 0;
    try {
        $stmt = $pdo->prepare("INSERT INTO customers (customer_name, phone, address, gst_number, has_active_amc) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $address, $gst, $amc]);
        $successMsg = "Customer added successfully! You can now select them to register a complaint.";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Handle Add Complaint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_complaint'])) {
    $custId = $_POST['customer_id'];
    $issue = trim($_POST['issue_description']);
    $priority = $_POST['priority'];
    try {
        $stmt = $pdo->prepare("INSERT INTO complaints (customer_id, issue_description, priority, status) VALUES (?, ?, ?, 'Open')");
        $stmt->execute([$custId, $issue, $priority]);
        $successMsg = "Complaint registered successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

require_once '../includes/header.php';

// Fetch filters
$filterStatus = $_GET['status'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Fetch all complaints with filtering
try {
    $where = [];
    $params = [];

    if ($filterStatus) {
        $where[] = "c.status = ?";
        $params[] = $filterStatus;
    }
    if ($searchTerm) {
        $where[] = "(cust.customer_name LIKE ? OR c.issue_description LIKE ? OR c.id LIKE ?)";
        $searchAttr = "%$searchTerm%";
        $params[] = $searchAttr;
        $params[] = $searchAttr;
        $params[] = $searchAttr;
    }

    $query = "SELECT c.*, cust.customer_name, u.fullname as tech_name 
              FROM complaints c 
              LEFT JOIN customers cust ON c.customer_id = cust.id 
              LEFT JOIN users u ON c.assigned_tech_id = u.id";
    
    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    
    $query .= " ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();

    // Fetch technicians for modal
    $techStmt = $pdo->query("SELECT id, fullname FROM users WHERE role = 'technician' AND status = 1");
    $technicians = $techStmt->fetchAll();

    // Fetch customers for new complaint modal
    $custStmt = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name ASC");
    $customers = $custStmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <h2 class="text-2xl font-bold text-slate-800">Service Desk</h2>
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search & Filter Form -->
            <form method="GET" class="flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-3 py-1.5 shadow-sm">
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search customer, issue..." class="text-sm outline-none w-40 md:w-64 bg-transparent">
                <div class="w-px h-6 bg-slate-200"></div>
                <select name="status" class="text-sm bg-transparent border-none outline-none text-slate-600 font-medium pr-2">
                    <option value="">All Status</option>
                    <option value="Open" <?php echo $filterStatus === 'Open' ? 'selected' : ''; ?>>Open</option>
                    <option value="Assigned" <?php echo $filterStatus === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                    <option value="In-Progress" <?php echo $filterStatus === 'In-Progress' ? 'selected' : ''; ?>>In-Progress</option>
                    <option value="Completed" <?php echo $filterStatus === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Closed" <?php echo $filterStatus === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
                <button type="submit" class="text-indigo-600 hover:text-indigo-800 ml-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </button>
                <?php if ($searchTerm || $filterStatus): ?>
                    <a href="complaints.php" class="text-slate-400 hover:text-slate-600" title="Clear Filters">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </a>
                <?php endif; ?>
            </form>
            <button onclick="openComplaintModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Register New Complaint</button>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-100">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold">ID</th>
                        <th class="px-6 py-4 font-semibold">Customer</th>
                        <th class="px-6 py-4 font-semibold">Issue</th>
                        <th class="px-6 py-4 font-semibold">Tech Assigned</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($complaints as $complaint): ?>
                    <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="toggleTimeline(<?php echo $complaint['id']; ?>)">
                        <td class="px-6 py-4 text-sm font-medium text-slate-900">#<?php echo $complaint['id']; ?></td>
                        <td class="px-6 py-4 text-sm text-slate-600 font-semibold"><?php echo htmlspecialchars($complaint['customer_name']); ?></td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <div class="max-w-xs truncate"><?php echo htmlspecialchars($complaint['issue_description']); ?></div>
                            <span class="text-[10px] uppercase font-bold text-slate-400"><?php echo $complaint['priority']; ?> Priority</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600 italic">
                            <?php echo $complaint['tech_name'] ?? '<span class="text-red-400">Not Assigned</span>'; ?>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <?php 
                                $statusClasses = [
                                    'Open' => 'bg-red-100 text-red-700',
                                    'Assigned' => 'bg-blue-100 text-blue-700',
                                    'In-Progress' => 'bg-yellow-100 text-yellow-700',
                                    'Completed' => 'bg-green-100 text-green-700',
                                    'Closed' => 'bg-slate-100 text-slate-700',
                                ];
                                $class = $statusClasses[$complaint['status']] ?? 'bg-slate-100 text-slate-700';
                            ?>
                            <span class="px-2 py-1 rounded-full text-xs font-bold <?php echo $class; ?>">
                                <?php echo $complaint['status']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm" onclick="event.stopPropagation()">
                            <div class="flex items-center gap-2">
                                <?php if ($complaint['status'] === 'Open'): ?>
                                    <button onclick="openDispatchModal(<?php echo $complaint['id']; ?>, '<?php echo addslashes($complaint['customer_name']); ?>')" class="text-white bg-indigo-500 hover:bg-indigo-600 px-3 py-1 rounded-md text-xs font-medium transition">Dispatch</button>
                                <?php endif; ?>
                                <button onclick="viewComplaint(<?php echo $complaint['id']; ?>)" class="text-slate-500 hover:text-indigo-600 transition" title="View History/Details">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                                <a href="invoice_gen.php?id=<?php echo $complaint['id']; ?>" class="text-slate-400 hover:text-indigo-600 transition" title="Generate Invoice"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg></a>
                            </div>
                        </td>
                    </tr>
                    <tr id="timeline-<?php echo $complaint['id']; ?>" class="hidden bg-slate-50/80 border-t border-slate-100 shadow-inner">
                        <td colspan="6" class="px-6 py-4">
                            <div class="flex items-center gap-12 text-sm">
                                <div>
                                    <span class="text-slate-400 uppercase tracking-widest block text-[10px] font-bold mb-1">Time Assigned</span>
                                    <span class="<?php echo $complaint['assigned_at'] ? 'text-indigo-600 font-bold' : 'text-slate-400 font-medium italic'; ?>"><?php echo $complaint['assigned_at'] ? date('M j, Y - g:i A', strtotime($complaint['assigned_at'])) : 'Pending Assignment'; ?></span>
                                </div>
                                <div class="w-8 h-px bg-slate-200"></div>
                                <div>
                                    <span class="text-slate-400 uppercase tracking-widest block text-[10px] font-bold mb-1">Work Started</span>
                                    <span class="<?php echo $complaint['started_at'] ? 'text-amber-600 font-bold' : 'text-slate-400 font-medium italic'; ?>"><?php echo $complaint['started_at'] ? date('M j, Y - g:i A', strtotime($complaint['started_at'])) : 'Pending Work'; ?></span>
                                </div>
                                <div class="w-8 h-px bg-slate-200"></div>
                                <div>
                                    <span class="text-slate-400 uppercase tracking-widest block text-[10px] font-bold mb-1">Work Completed</span>
                                    <span class="<?php echo $complaint['completed_at'] ? 'text-green-600 font-bold' : 'text-slate-400 font-medium italic'; ?>"><?php echo $complaint['completed_at'] ? date('M j, Y - g:i A', strtotime($complaint['completed_at'])) : 'Pending Completion'; ?></span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Dispatch Modal -->
<div id="dispatchModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900">Assign Technician</h3>
            <button onclick="closeDispatchModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <p class="text-sm text-slate-500 mb-6">Assigning technician for complaint from: <span id="modalCustomerName" class="font-bold text-slate-800"></span></p>
        <form method="POST">
            <input type="hidden" name="complaint_id" id="modalComplaintId">
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-2">Select Technician</label>
                <select name="tech_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <option value="">-- Choose Tech --</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['fullname']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="closeDispatchModal()" class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="assign_tech" class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Assign Now</button>
            </div>
        </form>
    </div>
</div>

<!-- Complaint Modal -->
<div id="complaintModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900">Register New Complaint</h3>
            <button onclick="closeComplaintModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Select Customer *</label>
                <div class="flex gap-2">
                    <select name="customer_id" required class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                        <option value="">-- Choose Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?> (<?php echo htmlspecialchars($c['phone']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="closeComplaintModal(); openCustomerModal();" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 font-medium text-sm transition">
                        + New
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Issue Description *</label>
                <textarea name="issue_description" required rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Priority Level *</label>
                <select name="priority" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                </select>
            </div>
            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeComplaintModal()" class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="add_complaint" class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Register Ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Customer Modal -->
<div id="customerModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900">Add New Customer</h3>
            <button type="button" onclick="closeCustomerModal(); openComplaintModal();" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Customer/Company Name *</label>
                <input type="text" name="customer_name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number *</label>
                <input type="text" name="phone" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Full Service Address *</label>
                <textarea name="address" required rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">GST Number (Optional)</label>
                <input type="text" name="gst_number" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="e.g. 29GGGGG1314R9Z6">
            </div>
            <div class="flex items-start mb-4">
                <div class="flex items-center h-5">
                    <input id="amc" name="has_active_amc" type="checkbox" value="1" class="w-4 h-4 border border-slate-300 rounded bg-slate-50 focus:ring-3 focus:ring-indigo-300">
                </div>
                <label for="amc" class="ml-2 text-sm font-medium text-slate-900">Has Active AMC Contract right now</label>
            </div>
            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeCustomerModal(); openComplaintModal();" class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="add_customer" class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- View Detail Modal -->
<div id="viewModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[60] p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900">Complaint Details #<span id="vID"></span></h3>
            <button onclick="closeViewModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <div id="vContent">
            <!-- Content will be loaded via AJAX -->
            <div class="animate-pulse flex space-y-4 flex-col">
                <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                <div class="h-4 bg-slate-200 rounded"></div>
                <div class="h-4 bg-slate-200 rounded w-5/6"></div>
            </div>
        </div>
    </div>
</div>

<script>
function openDispatchModal(id, customer) {
    document.getElementById('modalComplaintId').value = id;
    document.getElementById('modalCustomerName').innerText = customer;
    document.getElementById('dispatchModal').classList.remove('hidden');
}
function closeDispatchModal() {
    document.getElementById('dispatchModal').classList.add('hidden');
}
function openComplaintModal() {
    document.getElementById('complaintModal').classList.remove('hidden');
}
function closeComplaintModal() {
    document.getElementById('complaintModal').classList.add('hidden');
}
function openCustomerModal() {
    document.getElementById('customerModal').classList.remove('hidden');
}
function closeCustomerModal() {
    document.getElementById('customerModal').classList.add('hidden');
}

function toggleTimeline(id) {
    const el = document.getElementById('timeline-' + id);
    if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
    } else {
        el.classList.add('hidden');
    }
}

async function viewComplaint(id) {
    document.getElementById('viewModal').classList.remove('hidden');
    const content = document.getElementById('vContent');
    document.getElementById('vID').innerText = id;
    
    content.innerHTML = '<div class="flex justify-center py-10"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>';

    try {
        const response = await fetch('get_complaint_details.php?id=' + id);
        const data = await response.json();
        
        if (data.error) {
            content.innerHTML = `<div class="text-red-500 font-bold">${data.error}</div>`;
            return;
        }

        let partsHtml = '';
        if (data.resolved_parts && data.resolved_parts.length > 0) {
            partsHtml = `
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 mt-4">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 italic">Parts Consumed</h4>
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-200">
                                <th class="pb-2 font-bold uppercase">Item Name</th>
                                <th class="pb-2 text-center font-bold uppercase">Qty</th>
                                <th class="pb-2 text-right font-bold uppercase">Unit Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.resolved_parts.map(p => `
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-2.5 text-slate-800 font-bold">${p.name}</td>
                                    <td class="py-2.5 text-center text-slate-600 font-medium">${p.qty}</td>
                                    <td class="py-2.5 text-right text-slate-600 font-medium">₹${parseFloat(p.price).toLocaleString()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            partsHtml = '<div class="mt-4 p-4 rounded-xl bg-slate-50 border border-dashed border-slate-200 text-center text-xs text-slate-400 italic">No inventory parts consumed during this visit.</div>';
        }

        const photosHtml = `
            <div class="grid grid-cols-2 gap-4 mt-6">
                <div>
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Photo: Before</h4>
                    ${data.photo_before ? `<img src="${data.photo_before}" class="w-full h-40 object-cover rounded-xl border border-slate-200 shadow-sm transition hover:scale-[1.02] cursor-pointer" onclick="window.open(this.src)">` : '<div class="h-40 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 text-[10px] font-bold uppercase tracking-widest border border-dashed border-slate-200">No Image</div>'}
                </div>
                <div>
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Photo: After</h4>
                    ${data.photo_after ? `<img src="${data.photo_after}" class="w-full h-40 object-cover rounded-xl border border-slate-200 shadow-sm transition hover:scale-[1.02] cursor-pointer" onclick="window.open(this.src)">` : '<div class="h-40 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 text-[10px] font-bold uppercase tracking-widest border border-dashed border-slate-200">No Image</div>'}
                </div>
            </div>
        `;

        content.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-slate-100">
                <div class="space-y-1">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Customer</h4>
                    <p class="font-black text-slate-800">${data.customer_name}</p>
                    <p class="text-[11px] text-slate-500 font-medium">${data.customer_phone}</p>
                    <p class="text-[11px] text-slate-400 leading-relaxed">${data.customer_address}</p>
                </div>
                <div class="md:text-right space-y-1">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Tech Assigned</h4>
                    <p class="font-black text-indigo-600 underline underline-offset-4 decoration-indigo-200">${data.tech_name || 'Not Yet Assigned'}</p>
                    <p class="text-[11px] font-bold text-slate-500">Service Status: <span class="text-green-600">${data.status}</span></p>
                </div>
            </div>

            <div class="py-6">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3">Technician Remarks & Closing Notes</h4>
                <div class="bg-indigo-50/30 text-indigo-900/80 p-5 rounded-2xl border border-indigo-100/50 text-sm whitespace-pre-wrap italic font-medium leading-relaxed">
                    ${data.tech_remarks ? data.tech_remarks : 'The technician has not entered any closing remarks for this service ticket yet.'}
                </div>
            </div>

            ${partsHtml}
            ${photosHtml}
            
            <div class="pt-8 mt-8 border-t border-slate-100 flex justify-between items-center">
                <span class="text-[10px] text-slate-400 font-bold italic uppercase tracking-widest">End of History Report</span>
                <button onclick="closeViewModal()" class="px-8 py-2.5 bg-slate-900 text-white rounded-xl hover:bg-slate-800 font-black text-[11px] uppercase tracking-widest shadow-lg shadow-slate-200 transition-all">Close History</button>
            </div>
        `;
    } catch (e) {
        content.innerHTML = `<div class="text-red-500 font-bold p-10 text-center">Error communicating with Server. Please check connection.</div>`;
    }
}
function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}
</script>

<?php require_once '../includes/footer.php'; ?>
