<?php
include("config.php");
session_start();

// Security Guard
if (!isset($_SESSION['login_user']) || !isset($_SESSION['userID'])) {
    header("location: login.php");
    exit();
}

$myUserID = $_SESSION['userID'];
$selected_date = isset($_GET['date']) ? mysqli_real_escape_string($db, $_GET['date']) : date('Y-m-d');

// --- BACKGROUND AJAX OPERATIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. ACTION: ADD EXPENSE
    if ($action === 'add') {
        $expense_name = mysqli_real_escape_string($db, $_POST['expense_name']);
        $amount = mysqli_real_escape_string($db, $_POST['amount']);
        
        if (!empty($expense_name) && !empty($amount)) {
            $insert_sql = "INSERT INTO expenses_TB (userID, expenseDate, expenseName, amount) 
                           VALUES ('$myUserID', '$selected_date', '$expense_name', '$amount')";
            if (mysqli_query($db, $insert_sql)) {
                echo json_encode(["status" => "success", "id" => mysqli_insert_id($db)]);
            } else {
                echo json_encode(["status" => "error", "message" => "Database insert error."]);
            }
        }
        exit();
    }

    // 2. ACTION: UPDATE EXPENSE
    if ($action === 'update') {
        $expenseID = intval($_POST['expenseID']);
        $expense_name = mysqli_real_escape_string($db, $_POST['expense_name']);
        $amount = mysqli_real_escape_string($db, $_POST['amount']);

        $update_sql = "UPDATE expenses_TB SET expenseName = '$expense_name', amount = '$amount' 
                       WHERE expenseID = $expenseID AND userID = '$myUserID'";
        if (mysqli_query($db, $update_sql)) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database update error."]);
        }
        exit();
    }

    // 3. ACTION: DELETE EXPENSE
    if ($action === 'delete') {
        $expenseID = intval($_POST['expenseID']);

        $delete_sql = "DELETE FROM expenses_TB WHERE expenseID = $expenseID AND userID = '$myUserID'";
        if (mysqli_query($db, $delete_sql)) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database delete error."]);
        }
        exit();
    }
}

// --- STANDARD PAGE LOAD PORTION ---
$fetch_sql = "SELECT expenseID, expenseName, amount FROM expenses_TB 
              WHERE userID = '$myUserID' AND expenseDate = '$selected_date'";
$expenses_result = mysqli_query($db, $fetch_sql);

$total_amount = 0;
$expense_items = [];
if ($expenses_result) {
    while ($row = mysqli_fetch_assoc($expenses_result)) {
        $expense_items[] = $row;
        $total_amount += $row['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notepad Expenses Ledger</title>
    <link rel="stylesheet" href="templates/style.css"> 
    <style>

        .add-btn:hover { background: #ff6b6b; }
    </style>
</head>
<body>

<div class="notepad">
    <div class="notepad-header">
        <h2>Log: <?php echo htmlspecialchars(date("d M Y", strtotime($selected_date))); ?></h2>
        <a href="dashboard.php" class="back-link">&larr; Close Notepad</a>
    </div>

    <div class="expense-ledger" id="ledgerContainer">
        <?php if (empty($expense_items)): ?>
            <p class="empty-notice" id="emptyNotice">No items listed yet...</p>
        <?php else: ?>
            <?php foreach ($expense_items as $item): ?>
                <div class="ledger-row" id="row-<?php echo $item['expenseID']; ?>">
                    <div class="row-details">
                        <span class="display-name">- <?php echo htmlspecialchars($item['expenseName']); ?></span>
                        <span class="display-amount" data-raw="<?php echo $item['amount']; ?>">$<?php echo number_format($item['amount'], 2); ?></span>
                    </div>
                    <div class="row-actions">
                        <button class="action-btn btn-edit" onclick="enableEdit(<?php echo $item['expenseID']; ?>)" title="Edit">✎</button>
                        <button class="action-btn btn-delete" onclick="deleteEntry(<?php echo $item['expenseID']; ?>)" title="Delete">🗑</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="total-box">
        <span>TOTAL:</span>
        <span id="runningTotal" data-value="<?php echo $total_amount; ?>">$<?php echo number_format($total_amount, 2); ?></span>
    </div>

    <form id="seamlessForm" onsubmit="addEntrySeamless(event)">
        <div class="input-bar">
            <input type="text" id="itemName" class="input-name" placeholder="Write item name..." required autocomplete="off">
            <input type="number" id="itemAmount" class="input-amount" placeholder="0.00" step="0.01" min="0.01" required>
            <button type="submit" class="add-btn">Add</button>
        </div>
    </form>
</div>

<script>
// 1. ADD ENTRY SEAMLESSLY
function addEntrySeamless(event) {
    event.preventDefault();
    const nameInput = document.getElementById('itemName');
    const amountInput = document.getElementById('itemAmount');
    const nameVal = nameInput.value.trim();
    const amountVal = parseFloat(amountInput.value);

    if(!nameVal || isNaN(amountVal)) return;

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('expense_name', nameVal);
    formData.append('amount', amountVal);

    fetch('expense.php?date=<?php echo urlencode($selected_date); ?>', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            const notice = document.getElementById('emptyNotice');
            if(notice) notice.remove();

            const ledger = document.getElementById('ledgerContainer');
            const row = document.createElement('div');
            row.className = 'ledger-row';
            row.id = `row-${data.id}`;
            row.innerHTML = `
                <div class="row-details">
                    <span class="display-name">- ${escapeHtml(nameVal)}</span>
                    <span class="display-amount" data-raw="${amountVal}">$${amountVal.toFixed(2)}</span>
                </div>
                <div class="row-actions">
                    <button class="action-btn btn-edit" onclick="enableEdit(${data.id})">✎</button>
                    <button class="action-btn btn-delete" onclick="deleteEntry(${data.id})">🗑</button>
                </div>`;
            ledger.appendChild(row);

            recalculateTotal();
            nameInput.value = ''; amountInput.value = ''; nameInput.focus();
        }
    });
}

// 2. ENABLE INLINE EDIT MODE
function enableEdit(id) {
    const row = document.getElementById(`row-${id}`);
    const nameSpan = row.querySelector('.display-name');
    const amountSpan = row.querySelector('.display-amount');
    
    // Extract current values (removing leading hyphen from string display)
    const currentName = nameSpan.innerText.replace(/^- /, '');
    const currentAmount = amountSpan.getAttribute('data-raw');

    // Swap row layout with input fields
    row.innerHTML = `
        <form class="edit-mode-form" onsubmit="saveEdit(event, ${id})">
            <input type="text" class="edit-name" value="${escapeHtml(currentName)}" required autocomplete="off">
            <input type="number" class="edit-amount" value="${currentAmount}" step="0.01" min="0.01" required>
            <button type="submit" class="action-btn btn-edit" style="font-size:1.1rem;">✔</button>
            <button type="button" class="action-btn btn-delete" onclick="cancelEdit(${id}, '${escapeHtml(currentName)}', ${currentAmount})">✘</button>
        </form>
    `;
    row.querySelector('.edit-name').focus();
}

// 3. SAVE INLINE EDIT VIA BACKGROUND FETCH
function saveEdit(event, id) {
    event.preventDefault();
    const row = document.getElementById(`row-${id}`);
    const newName = row.querySelector('.edit-name').value.trim();
    const newAmount = parseFloat(row.querySelector('.edit-amount').value);

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('expenseID', id);
    formData.append('expense_name', newName);
    formData.append('amount', newAmount);

    fetch('expense.php?date=<?php echo urlencode($selected_date); ?>', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            renderStandardRow(id, newName, newAmount);
            recalculateTotal();
        }
    });
}

// 4. CANCEL INLINE EDIT
function cancelEdit(id, oldName, oldAmount) {
    renderStandardRow(id, oldName, oldAmount);
}

// Helper to structuralize normal rows back out
function renderStandardRow(id, name, amount) {
    const row = document.getElementById(`row-${id}`);
    row.innerHTML = `
        <div class="row-details">
            <span class="display-name">- ${escapeHtml(name)}</span>
            <span class="display-amount" data-raw="${amount}">$${amount.toFixed(2)}</span>
        </div>
        <div class="row-actions">
            <button class="action-btn btn-edit" onclick="enableEdit(${id})">✎</button>
            <button class="action-btn btn-delete" onclick="deleteEntry(${id})">🗑</button>
        </div>
    `;
}

// 5. DELETE ENTRY VIA BACKGROUND FETCH
function deleteEntry(id) {
    if(!confirm("Erase this item from your notepad?")) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('expenseID', id);

    fetch('expense.php?date=<?php echo urlencode($selected_date); ?>', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            const row = document.getElementById(`row-${id}`);
            row.remove();
            recalculateTotal();
            
            // Put placeholder notice back if everything was erased
            const ledger = document.getElementById('ledgerContainer');
            if (ledger.children.length === 0) {
                ledger.innerHTML = '<p class="empty-notice" id="emptyNotice">No items listed yet...</p>';
            }
        }
    });
}

// 6. RECALCULATE DYNAMIC TOTAL
function recalculateTotal() {
    const amounts = document.querySelectorAll('.display-amount');
    let newTotal = 0;
    
    amounts.forEach(el => {
        newTotal += parseFloat(el.getAttribute('data-raw')) || 0;
    });

    const totalSpan = document.getElementById('runningTotal');
    totalSpan.setAttribute('data-value', newTotal);
    totalSpan.innerText = `$${newTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

function escapeHtml(string) {
    return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

</body>
</html>