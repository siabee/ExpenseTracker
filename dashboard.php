<?php
include("config.php");
session_start();

if (!isset($_SESSION['login_user'])) {
    header("location: login.php");
    exit();
}

$mynickname = $_SESSION['login_user'];

// handle background AJAX requests for updating the bio
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_bio'])) {
    $updated_bio = mysqli_real_escape_string($db, $_POST['bio_text']);
    $update_sql = "UPDATE userDetails_TB SET bio = '$updated_bio' WHERE nickName = '$mynickname'";
    
    if (mysqli_query($db, $update_sql)) {
        echo "Success";
    } else {
        echo "Error";
    }
    exit(); 
}

//expense pop-up AJAX portion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['expense_action'])) {
    $action = $_POST['expense_action'];
    $selected_date = mysqli_real_escape_string($db, $_POST['expense_date']);
    $myUserID = $_SESSION['userID'];

    //fetch items, commentary, the done stamp status
    if ($action === 'fetch') {
        $fetch_sql = "SELECT expenseID, expenseName, amount FROM expenses_TB WHERE userID = '$myUserID' AND expenseDate = '$selected_date'";
        $result = mysqli_query($db, $fetch_sql);
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }

        $note_text = "";
        $is_done = 0;
        $note_sql = "SELECT noteText, isDone FROM dailyNotes_TB WHERE userID = '$myUserID' AND noteDate = '$selected_date'";
        $note_res = mysqli_query($db, $note_sql);
        if ($note_res && mysqli_num_rows($note_res) > 0) {
            $note_row = mysqli_fetch_assoc($note_res);
            $note_text = $note_row['noteText'];
            $is_done = (int)$note_row['isDone'];
        }

        echo json_encode(["status" => "success", "items" => $items, "note" => $note_text, "isDone" => $is_done]);
        exit();
    }

    //save daily note commentary
    if ($action === 'save_note') {
        $note_content = mysqli_real_escape_string($db, $_POST['note_text']);
        $save_note_sql = "INSERT INTO dailyNotes_TB (userID, noteDate, noteText) 
                          VALUES ('$myUserID', '$selected_date', '$note_content') 
                          ON DUPLICATE KEY UPDATE noteText = '$note_content'";
        
        if (mysqli_query($db, $save_note_sql)) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error"]);
        }
        exit();
    }

    //toggle day done stamp status
    if ($action === 'toggle_stamp') {
        $status = intval($_POST['is_done']);
        $stamp_sql = "INSERT INTO dailyNotes_TB (userID, noteDate, isDone) 
                      VALUES ('$myUserID', '$selected_date', $status) 
                      ON DUPLICATE KEY UPDATE isDone = $status";
        
        if (mysqli_query($db, $stamp_sql)) {
            echo json_encode(["status" => "success", "isDone" => $status]);
        } else {
            echo json_encode(["status" => "error"]);
        }
        exit();
    }

    //add an expense 
    if ($action === 'add') {
        $expense_name = mysqli_real_escape_string($db, $_POST['expense_name']);
        $amount = mysqli_real_escape_string($db, $_POST['amount']);
        $insert_sql = "INSERT INTO expenses_TB (userID, expenseDate, expenseName, amount) VALUES ('$myUserID', '$selected_date', '$expense_name', '$amount')";
        if (mysqli_query($db, $insert_sql)) {
            echo json_encode(["status" => "success", "id" => mysqli_insert_id($db)]);
        } else {
            echo json_encode(["status" => "error", "message" => "Insert failed"]);
        }
        exit();
    }

    //update an expense
    if ($action === 'update') {
        $expenseID = intval($_POST['expenseID']);
        $expense_name = mysqli_real_escape_string($db, $_POST['expense_name']);
        $amount = mysqli_real_escape_string($db, $_POST['amount']);
        $update_sql = "UPDATE expenses_TB SET expenseName = '$expense_name', amount = '$amount' WHERE expenseID = $expenseID AND userID = '$myUserID'";
        if (mysqli_query($db, $update_sql)) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Update failed"]);
        }
        exit();
    }

    //delete and expense
    if ($action === 'delete') {
        $expenseID = intval($_POST['expenseID']);
        $delete_sql = "DELETE FROM expenses_TB WHERE expenseID = $expenseID AND userID = '$myUserID'";
        if (mysqli_query($db, $delete_sql)) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Delete failed"]);
        }
        exit();
    }
}

//fetch profile
$user_query = mysqli_query($db, "SELECT userID, firstName, lastName, bio, profile_pic FROM userDetails_TB WHERE nickName = '$mynickname'");

if ($user_query && mysqli_num_rows($user_query) > 0) {
    $user_data = mysqli_fetch_assoc($user_query);
    $_SESSION['userID'] = $user_data['userID']; 
    $firstname = $user_data['firstName'];
    $lastname = $user_data['lastName'];
    $bio = !empty($user_data['bio']) ? $user_data['bio'] : 'Who am I?';
    $profile_pic = !empty($user_data['profile_pic']) ? $user_data['profile_pic'] : 'images/default.jpg';
    $_SESSION['profile_pic'] = $profile_pic;
} else {
    $firstname = ""; 
    $lastname = "";
    $bio = "Who am I?";
    $profile_pic = 'images/default.jpg';
    $_SESSION['profile_pic'] = $profile_pic;
}

//gather array of stamped dates to apply `.stamped` class on page load
$stamped_dates = [];
if (isset($_SESSION['userID'])) {
    $uid = $_SESSION['userID'];
    $stamped_res = mysqli_query($db, "SELECT noteDate FROM dailyNotes_TB WHERE userID = '$uid' AND isDone = 1");
    while ($s_row = mysqli_fetch_assoc($stamped_res)) {
        $stamped_dates[] = $s_row['noteDate'];
    }
}

$error = '';

//handle profile pic uploading
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_avatar'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_picture']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'images/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $_SESSION['profile_pic'] = $target_file;
                mysqli_query($db, "UPDATE userDetails_TB SET profile_pic='$target_file' WHERE nickName='$mynickname'");
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "System error shifting files to target folder.";
            }
        } else {
            $error = "Invalid format! Use JPG, PNG, or GIF graphics.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> <?php echo $mynickname;?>'s Dashboard</title>
    <link rel="stylesheet" href="templates/style.css">
</head>
<body>

   <div class="profile-widget" onclick="openProfileModal()">
        <img src="<?php echo isset($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'images/default.jpg'; ?>" alt="Profile Picture" class="profile-img">
   </div>
    
   <div class="main-content">
       <div class="calendar-container"> 
           <div class="calendar-grid">
                <div></div>
                <div></div>
                
                <?php
                //loop to dynamically check database array and persist stamps across loads
                for ($day = 1; $day <= 30; $day++) {
                    $date_string = sprintf("2026-04-%02d", $day);
                    $stamp_class = in_array($date_string, $stamped_dates) ? " stamped" : "";
                    echo "<a href=\"javascript:void(0);\" id=\"cal-day-$date_string\" onclick=\"openExpenseNotepad('$date_string')\" class=\"day-box$stamp_class\">$day</a>";
                }
                ?>
           </div>
       </div>
   </div>

   <div id="profileModal" class="modal-overlay" onclick="closeProfileModal(event)">
        <div class="modal-card" onclick="event.stopPropagation()">
            <span class="close-btn" onclick="closeProfileModal()">&times;</span>
            
            <form action="dashboard.php" method="POST" enctype="multipart/form-data" id="profileUploadForm">
                <div class="modal-header">
                    <div class="profile-upload-wrapper" onclick="document.getElementById('fileInput').click();" title="Click to change photo">
                        <img src="<?php echo isset($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'images/default.jpg'; ?>" alt="User Profile" class="modal-profile-img">
                        <div class="profile-upload-overlay">
                            <span>Change</span>
                        </div>
                    </div>
                    
                    <input type="file" id="fileInput" name="profile_picture" style="display: none;" accept="image/*" onchange="submitProfilePicture()">
                    <input type="hidden" name="update_avatar" value="1">

                    <h3><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></h3>
                    <p class="modal-username"><?php echo htmlspecialchars($mynickname); ?></p>
                </div>
            </form>
            
            <hr class="modal-divider">
        
            <div class="modal-body">
                <div class="bio-container">
                    <label for="bioInput" class="bio-label">It's me!</label>
                    <textarea id="bioInput" class="bio-textarea" rows="3" maxlength="150" placeholder="Who am I?" onblur="saveBio()"><?php echo ($bio !== 'Who am I?') ? htmlspecialchars($bio) : ''; ?></textarea>
                    <div id="bioStatus" class="bio-status"></div>
                </div>
            </div>
            <div class="close-btn-container">
                <button class="close-btn" onclick="closeProfileModal()">✕</button>
            </div>
            
            <div class="modal-footer">
                <a href="logout.php" class="logout-btn">Log Out</a>
            </div>
        </div>
    </div>

    <div id="expenseModal" class="modal-overlay" onclick="closeExpenseModal(event)">
        <div class="notepad" onclick="event.stopPropagation()">
            <div class="notepad-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <h2>[LOG] <span id="modalDateDisplay">-- --- ----</span></h2>
                    <label style="font-size: 12px; display: flex; align-items: center; gap: 4px; cursor: pointer; color: #644117; font-weight: bold;">
                        <input type="checkbox" id="stampCheckbox" onchange="toggleDayStamp()"> Done ★
                    </label>
                </div>
                <button class="close-notepad-btn" onclick="closeExpenseModal(event)">✕</button>
            </div>

            <div class="expense-ledger" id="ledgerContainer"></div>

            <div class="daily-commentary-section" style="margin-left: 30px; margin-top: 10px; z-index: 5;">
                <label for="dailyNote" style="font-family: inherit; font-weight: bold; color: #644117; font-size: 0.9rem; display: block; margin-bottom: 3px;">How did I do?</label>
                <textarea id="dailyNote" placeholder="Share your day... " 
                          style="width: 100%; min-height: 45px; height: 45px; border-radius: 8px; border: 1px dashed #644117; font-family: inherit; padding: 6px; resize: none; box-sizing: border-box; background: rgba(255, 255, 255, 0.4); color: #3c270e; font-size: 0.9rem;"></textarea>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 3px;">
                    <span id="noteStatus" style="font-size: 11px; font-weight: bold; color: #644117;"></span>
                    <button type="button" class="action-btn" onclick="saveDailyNote()" style="padding: 2px 10px; font-size: 11px;">Save</button>
                </div>
            </div>

            <div class="total-box" style="margin-top: 10px;">
                <span>TOTAL:</span>
                <span id="runningTotal" data-value="0">PHP 0.00</span>
            </div>

            <form id="seamlessForm" onsubmit="addEntrySeamless(event)">
                <div class="input-bar" style="margin-top: 15px; padding-top: 15px;">
                    <input type="text" id="itemName" class="input-name" placeholder="What expense is it?" required autocomplete="off">
                    <input type="number" id="itemAmount" class="input-amount" placeholder="0.00" step="0.01" min="0.01" required>
                    <button type="submit" class="add-btn">+</button>
                </div>
            </form>
        </div>
    </div>

<script>
function openProfileModal() {
    const modal = document.getElementById('profileModal');
    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('active'); }, 10);
}

function closeProfileModal(event) {
    if (event && event.target.id !== 'profileModal' && !event.target.classList.contains('close-btn')) {
        return;
    }
    const modal = document.getElementById('profileModal');
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

function submitProfilePicture() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput.files.length > 0) {
        document.getElementById('profileUploadForm').submit();
    }
}

function saveBio() {
    const bioText = document.getElementById('bioInput').value;
    const statusDiv = document.getElementById('bioStatus');
    
    statusDiv.innerText = "Saving...";
    statusDiv.style.color = "#644117";

    const formData = new FormData();
    formData.append('update_bio', '1');
    formData.append('bio_text', bioText);

    fetch('dashboard.php', { method: 'POST', body: formData })
    .then(response => response.text())
    .then(data => {
        if(data.trim() === "Success") {
            statusDiv.innerText = "Saved changes!";
            statusDiv.style.color = "#644117";
            setTimeout(() => { statusDiv.innerText = ""; }, 2000);
        } else {
            statusDiv.innerText = "Failed to save.";
            statusDiv.style.color = "#c0392b";
        }
    })
    .catch(error => {
        statusDiv.innerText = "Connection error.";
        statusDiv.style.color = "#c0392b";
    });
}

let currentActiveDate = "";

//open modal, fetch existing entries, daily notes, and stamp state 
function openExpenseNotepad(dateString) {
    currentActiveDate = dateString;
    document.getElementById('modalDateDisplay').innerText = dateString;
    
    const formData = new FormData();
    formData.append('expense_action', 'fetch');
    formData.append('expense_date', dateString);

    fetch('dashboard.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            const ledger = document.getElementById('ledgerContainer');
            ledger.innerHTML = "";
            
            document.getElementById('noteStatus').innerText = "";
            document.getElementById('dailyNote').value = data.note || "";
            
            //sync checkbox display state with the database row value
            document.getElementById('stampCheckbox').checked = (data.isDone === 1);
            
            if(data.items.length === 0) {
                ledger.innerHTML = '<p class="empty-notice" id="emptyNotice">No items listed yet...</p>';
            } else {
                data.items.forEach(item => {
                    ledger.innerHTML += `
                        <div class="ledger-row" id="row-${item.expenseID}">
                            <div class="row-details">
                                <span class="display-name">${escapeHtml(item.expenseName)}</span>
                                <span class="display-amount" data-raw="${item.amount}">PHP ${parseFloat(item.amount).toFixed(2)}</span>
                            </div>
                            <div class="row-actions">
                                <button class="action-btn btn-edit" onclick="enableEdit(${item.expenseID})">Edit</button>
                                <button class="action-btn btn-delete" onclick="deleteEntry(${item.expenseID})">Delete</button>
                            </div>
                        </div>`;
                });
            }
            recalculateTotal();
            
            const expModal = document.getElementById('expenseModal');
            expModal.style.display = 'flex';
            setTimeout(() => { expModal.classList.add('active'); }, 10);
        }
    });
}

//save daily commentary via AJAX
function saveDailyNote() {
    const noteText = document.getElementById('dailyNote').value;
    const statusSpan = document.getElementById('noteStatus');
    
    statusSpan.innerText = "Saving...";
    statusSpan.style.color = "#644117";

    const formData = new FormData();
    formData.append('expense_action', 'save_note');
    formData.append('expense_date', currentActiveDate);
    formData.append('note_text', noteText);

    fetch('dashboard.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            statusSpan.innerText = "Saved! ★";
            setTimeout(() => { statusSpan.innerText = ""; }, 2000);
        } else {
            statusSpan.innerText = "Error saving.";
            statusSpan.style.color = "#c0392b";
        }
    })
    .catch(err => {
        statusSpan.innerText = "Connection error.";
        statusSpan.style.color = "#c0392b";
    });
}

//toggle day stamp via AJAX (updates main calendar cell instantly)
function toggleDayStamp() {
    const isChecked = document.getElementById('stampCheckbox').checked ? 1 : 0;
    const targetCalendarCell = document.getElementById(`cal-day-${currentActiveDate}`);

    const formData = new FormData();
    formData.append('expense_action', 'toggle_stamp');
    formData.append('expense_date', currentActiveDate);
    formData.append('is_done', isChecked);

    fetch('dashboard.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            if(data.isDone === 1) {
                if(targetCalendarCell) targetCalendarCell.classList.add('stamped');
            } else {
                if(targetCalendarCell) targetCalendarCell.classList.remove('stamped');
            }
        }
    })
    .catch(err => console.error("Error updating stamp status:", err));
}

//close modal safely
function closeExpenseModal(event) {
    if (event && event.target.id !== 'expenseModal' && event.target.className !== 'close-notepad-btn') {
        return;
    }
    const expModal = document.getElementById('expenseModal');
    expModal.classList.remove('active');
    setTimeout(() => { expModal.style.display = 'none'; }, 300);
}

//add an expense via  modal submission 
function addEntrySeamless(event) {
    event.preventDefault();
    const nameInput = document.getElementById('itemName');
    const amountInput = document.getElementById('itemAmount');
    const nameVal = nameInput.value.trim();
    const amountVal = parseFloat(amountInput.value);

    const formData = new FormData();
    formData.append('expense_action', 'add');
    formData.append('expense_date', currentActiveDate);
    formData.append('expense_name', nameVal);
    formData.append('amount', amountVal);

    fetch('dashboard.php', { method: 'POST', body: formData })
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
                    <span class="display-name">${escapeHtml(nameVal)}</span>
                    <span class="display-amount" data-raw="${amountVal}">PHP ${amountVal.toFixed(2)}</span>
                </div>
                <div class="row-actions">
                    <button class="action-btn btn-edit" onclick="enableEdit(${data.id})">Edit</button>
                    <button class="action-btn btn-delete" onclick="deleteEntry(${data.id})">Delete</button>
                </div>`;
            ledger.appendChild(row);

            recalculateTotal();
            nameInput.value = ''; amountInput.value = ''; nameInput.focus();
        }
    });
}

//edit entry
function enableEdit(id) {
    const row = document.getElementById(`row-${id}`);
    const nameSpan = row.querySelector('.display-name');
    const amountSpan = row.querySelector('.display-amount');
    const currentName = nameSpan.innerText;
    const currentAmount = amountSpan.getAttribute('data-raw');

    row.innerHTML = `
        <form class="edit-mode-form" onsubmit="saveEdit(event, ${id})">
            <input type="text" class="edit-name" value="${escapeHtml(currentName)}" required autocomplete="off">
            <input type="number" class="edit-amount" value="${currentAmount}" step="0.01" min="0.01" required>
            <button type="submit" class="action-btn btn-edit">Done</button>
            <button type="button" class="action-btn btn-delete" onclick="renderStandardRow(${id}, '${escapeHtml(currentName)}', ${currentAmount})">Cancel</button>
        </form>`;
}

function saveEdit(event, id) {
    event.preventDefault();
    const row = document.getElementById(`row-${id}`);
    const newName = row.querySelector('.edit-name').value.trim();
    const newAmount = parseFloat(row.querySelector('.edit-amount').value);

    const formData = new FormData();
    formData.append('expense_action', 'update');
    formData.append('expense_date', currentActiveDate);
    formData.append('expenseID', id);
    formData.append('expense_name', newName);
    formData.append('amount', newAmount);

    fetch('dashboard.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            renderStandardRow(id, newName, newAmount);
            recalculateTotal();
        }
    });
}

function renderStandardRow(id, name, amount) {
    const row = document.getElementById(`row-${id}`);
    row.innerHTML = `
        <div class="row-details">
            <span class="display-name">${escapeHtml(name)}</span>
            <span class="display-amount" data-raw="${amount}">PHP ${amount.toFixed(2)}</span>
        </div>
        <div class="row-actions">
            <button class="action-btn btn-edit" onclick="enableEdit(${id})">Edit</button>
            <button class="action-btn btn-delete" onclick="deleteEntry(${id})">Delete</button>
        </div>`;
}

//delete entry
function deleteEntry(id) {
    if(!confirm("Erase this item?")) return;

    const formData = new FormData();
    formData.append('expense_action', 'delete');
    formData.append('expense_date', currentActiveDate);
    formData.append('expenseID', id);

    fetch('dashboard.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            document.getElementById(`row-${id}`).remove();
            recalculateTotal();
            const ledger = document.getElementById('ledgerContainer');
            if (ledger.children.length === 0) {
                ledger.innerHTML = '<p class="empty-notice" id="emptyNotice">No items listed yet...</p>';
            }
        }
    });
}

//dynamic calculation
function recalculateTotal() {
    const amounts = document.querySelectorAll('.display-amount');
    let newTotal = 0;
    amounts.forEach(el => { newTotal += parseFloat(el.getAttribute('data-raw')) || 0; });
    const totalSpan = document.getElementById('runningTotal');
    totalSpan.setAttribute('data-value', newTotal);
    totalSpan.innerText = `PHP ${newTotal.toFixed(2)}`;
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>
</body>
</html>