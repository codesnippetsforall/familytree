<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch member data
if (isset($_GET['id'])) {
    // Fetch member details
    $stmt = $db->prepare("SELECT * FROM family_members WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        header('Location: members.php');
        exit();
    }

    // Fetch member's parents
    $stmt = $db->prepare("SELECT mp.*, f.name as parent_name 
                         FROM member_parents mp 
                         JOIN family_members f ON mp.parent_id = f.id 
                         WHERE mp.member_id = ?");
    $stmt->execute([$_GET['id']]);
    $current_parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add this query to fetch current spouses
    $stmt = $db->prepare("
        SELECT ms.*, f.name as spouse_name 
        FROM member_spouses ms 
        JOIN family_members f ON ms.spouse_id = f.id 
        WHERE ms.member_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $current_spouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    header('Location: members.php');
    exit();
}

// Fetch countries for dropdown
$countries = $db->query("SELECT id, name, has_states FROM countries ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing members for parent and spouse selection
$stmt = $db->query("
    SELECT 
        m.id,
        m.name,
        GROUP_CONCAT(
            DISTINCT CONCAT(p.name, ' (', mp.relationship_type, ')')
            SEPARATOR ' & '
        ) as parents,
        s.name as spouse_name
    FROM family_members m
    LEFT JOIN member_parents mp ON m.id = mp.member_id
    LEFT JOIN family_members p ON mp.parent_id = p.id
    LEFT JOIN family_members s ON m.spouse_id = s.id
    WHERE m.id != " . (int)$_GET['id'] . "
    GROUP BY m.id
    ORDER BY m.name
");
$family_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$member_country_is_india = (strtolower(trim($member['country'] ?? '')) === 'india');
$member_state = trim($member['state'] ?? '');
$member_country_other = $member_country_is_india ? '' : ($member['country'] ?? '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // Handle file upload
        $picture = $member['picture']; // Keep existing picture by default
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['picture']['name'] ?? '';
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                throw new Exception('Invalid image format. Please upload JPG, JPEG, PNG or GIF.');
            }

            $uploadDir = __DIR__ . '/../uploads';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                throw new Exception('Upload folder is not available or writable.');
            }

            $new_filename = uniqid('member_', true) . '.' . $ext;
            $upload_path = $uploadDir . '/' . $new_filename;
            if (!move_uploaded_file($_FILES['picture']['tmp_name'], $upload_path)) {
                throw new Exception('Failed to upload image. Please try again.');
            }

            // Delete old picture if exists
            if (!empty($member['picture'])) {
                $oldPath = $uploadDir . '/' . $member['picture'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $picture = $new_filename;
        }

        // Calculate age from date of birth (optional)
        $dobRaw = trim($_POST['date_of_birth'] ?? '');
        $age = 0;
        $date_of_birth = null;
        if ($dobRaw !== '' && $dobRaw !== '0000-00-00') {
            try {
                $dob = new DateTime($dobRaw);
                $today = new DateTime();
                $age = $today->diff($dob)->y;
                $date_of_birth = $dob->format('Y-m-d');
            } catch (Exception $e) {
                $date_of_birth = $dobRaw;
            }
        }

        // Resolve country and state from dropdown/other fields
        $country_id = (int)($_POST['country_id'] ?? 0);
        if ($country_id === 1) {
            $country = 'India';
            $state = trim($_POST['state_india'] ?? '');
        } else {
            $country = trim($_POST['country_other'] ?? '');
            $state = trim($_POST['state_other'] ?? '');
        }

        // Update member
        $sql = "UPDATE family_members SET 
            name = :name,
            age = :age,
            sex = :sex,
            mobile_number = :mobile_number,
            place_of_birth = :place_of_birth,
            living_place = :living_place,
            state = :state,
            country = :country,
            picture = :picture,
            date_of_birth = :date_of_birth,
            zodiac = :zodiac
            WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'name' => $_POST['name'],
            'age' => $age,
            'sex' => $_POST['sex'],
            'mobile_number' => $_POST['mobile_number'],
            'place_of_birth' => $_POST['place_of_birth'],
            'living_place' => $_POST['living_place'],
            'state' => $state,
            'country' => $country,
            'picture' => $picture,
            'date_of_birth' => $date_of_birth,
            'zodiac' => $_POST['zodiac'],
            'id' => $_GET['id']
        ]);

        // Update parents
        // First, remove all existing parent relationships
        $stmt = $db->prepare("DELETE FROM member_parents WHERE member_id = ?");
        $stmt->execute([$_GET['id']]);

        // Then add the new parent relationships
        if (!empty($_POST['parents'])) {
            $parent_sql = "INSERT INTO member_parents (member_id, parent_id, relationship_type) VALUES (:member_id, :parent_id, :relationship_type)";
            $parent_stmt = $db->prepare($parent_sql);

            foreach ($_POST['parents'] as $parent) {
                if (!empty($parent['id']) && !empty($parent['type'])) {
                    $parent_stmt->execute([
                        'member_id' => $_GET['id'],
                        'parent_id' => $parent['id'],
                        'relationship_type' => $parent['type']
                    ]);
                }
            }
        }

        // Update spouses
        // First remove all existing spouse relationships
        $stmt = $db->prepare("DELETE FROM member_spouses WHERE member_id = ?");
        $stmt->execute([$_GET['id']]);

        // Then add the new spouse relationships
        if (!empty($_POST['spouses'])) {
            $spouse_sql = "INSERT INTO member_spouses (member_id, spouse_id, marriage_date, marriage_status) 
                          VALUES (:member_id, :spouse_id, :marriage_date, :marriage_status)";
            $spouse_stmt = $db->prepare($spouse_sql);

            foreach ($_POST['spouses'] as $spouse) {
                if (!empty($spouse['id'])) {
                    $spouse_stmt->execute([
                        'member_id' => $_GET['id'],
                        'spouse_id' => $spouse['id'],
                        'marriage_date' => !empty($spouse['marriage_date']) ? $spouse['marriage_date'] : null,
                        'marriage_status' => $spouse['status']
                    ]);
                }
            }
        }

        $db->commit();
        header('Location: members.php');
        exit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Error updating member: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Family Member - Family Tree Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Family Member</h1>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($member['name']); ?>" required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="sex" required>
                                    <option value="">Choose...</option>
                                    <option value="Male" <?php echo $member['sex'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $member['sex'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $member['sex'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" value="<?php echo (!empty($member['date_of_birth']) && $member['date_of_birth'] !== '0000-00-00') ? $member['date_of_birth'] : ''; ?>">
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Mobile Number</label>
                                <input type="tel" class="form-control" name="mobile_number" value="<?php echo htmlspecialchars($member['mobile_number']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Location Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">Place of Birth</label>
                                <input type="text" class="form-control" name="place_of_birth" value="<?php echo htmlspecialchars($member['place_of_birth']); ?>">
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Current Living Place</label>
                                <input type="text" class="form-control" name="living_place" value="<?php echo htmlspecialchars($member['living_place']); ?>">
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Country</label>
                                <select class="form-select" id="country_id" name="country_id" required>
                                    <option value="">Choose country...</option>
                                    <?php foreach ($countries as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>" data-has-states="<?php echo (int)$c['has_states']; ?>" <?php echo ($c['id'] == 1 && $member_country_is_india) || ($c['id'] == 2 && !$member_country_is_india) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-sm-6" id="state-india-wrap" style="display: none;">
                                <label class="form-label">State</label>
                                <select class="form-select" id="state_india" name="state_india" data-initial-value="<?php echo $member_country_is_india ? htmlspecialchars($member_state) : ''; ?>">
                                    <option value="">Choose state...</option>
                                </select>
                            </div>
                            <div class="col-sm-6" id="country-other-wrap" style="display: none;">
                                <label class="form-label">Country (other)</label>
                                <input type="text" class="form-control" name="country_other" id="country_other" placeholder="Enter country name" value="<?php echo htmlspecialchars($member_country_other); ?>">
                            </div>
                            <div class="col-sm-6" id="state-other-wrap" style="display: none;">
                                <label class="form-label">State / Region</label>
                                <input type="text" class="form-control" name="state_other" id="state_other" placeholder="Enter state or region" value="<?php echo htmlspecialchars($member_country_is_india ? '' : $member_state); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Additional Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">Zodiac Sign</label>
                                <input type="text" class="form-control" name="zodiac" value="<?php echo htmlspecialchars($member['zodiac']); ?>">
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Picture</label>
                                <?php if (!empty($member['picture']) && is_file(__DIR__ . '/../uploads/' . $member['picture'])): ?>
                                    <div class="mb-2">
                                        <img src="../uploads/<?php echo htmlspecialchars($member['picture']); ?>" alt="Current picture" style="max-width: 100px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="picture">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Family Relations -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Family Relations</h5>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addParentField()">
                            Add Parent
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="parents-container">
                            <?php foreach ($current_parents as $index => $parent): ?>
                                <div class="row g-3 mb-3 align-items-center parent-row">
                                    <div class="col-sm-5">
                                        <select class="form-select" name="parents[<?php echo $index; ?>][id]" required>
                                            <option value="">Select Parent...</option>
                                            <?php foreach ($family_members as $fm): 
                                                $displayName = htmlspecialchars($fm['name']);
                                                if (!empty($fm['parents'])) {
                                                    $displayName .= ' (Child of ' . htmlspecialchars($fm['parents']) . ')';
                                                } elseif (!empty($fm['spouse_name'])) {
                                                    $displayName .= ' (Spouse of ' . htmlspecialchars($fm['spouse_name']) . ')';
                                                }
                                            ?>
                                                <option value="<?php echo $fm['id']; ?>" <?php echo $parent['parent_id'] == $fm['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $displayName; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-5">
                                        <select class="form-select" name="parents[<?php echo $index; ?>][type]" required>
                                            <option value="">Select Relationship...</option>
                                            <option value="Father" <?php echo $parent['relationship_type'] == 'Father' ? 'selected' : ''; ?>>Father</option>
                                            <option value="Mother" <?php echo $parent['relationship_type'] == 'Mother' ? 'selected' : ''; ?>>Mother</option>
                                            <option value="Step-Father" <?php echo $parent['relationship_type'] == 'Step-Father' ? 'selected' : ''; ?>>Step-Father</option>
                                            <option value="Step-Mother" <?php echo $parent['relationship_type'] == 'Step-Mother' ? 'selected' : ''; ?>>Step-Mother</option>
                                            <option value="Adoptive Father" <?php echo $parent['relationship_type'] == 'Adoptive Father' ? 'selected' : ''; ?>>Adoptive Father</option>
                                            <option value="Adoptive Mother" <?php echo $parent['relationship_type'] == 'Adoptive Mother' ? 'selected' : ''; ?>>Adoptive Mother</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-2">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Spouses</label>
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Spouses</h5>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addSpouseField()">
                                        Add Spouse
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="spouses-container">
                                        <?php foreach ($current_spouses as $index => $spouse): ?>
                                            <div class="row g-3 mb-3 align-items-center spouse-row">
                                                <div class="col-sm-5">
                                                    <select class="form-select" name="spouses[<?php echo $index; ?>][id]" required>
                                                        <option value="">Select Spouse...</option>
                                                        <?php foreach ($family_members as $fm): 
                                                            $displayName = htmlspecialchars($fm['name']);
                                                            if (!empty($fm['parents'])) {
                                                                $displayName .= ' (Child of ' . htmlspecialchars($fm['parents']) . ')';
                                                            } elseif (!empty($fm['spouse_name'])) {
                                                                $displayName .= ' (Spouse of ' . htmlspecialchars($fm['spouse_name']) . ')';
                                                            }
                                                        ?>
                                                            <option value="<?php echo $fm['id']; ?>" <?php echo $spouse['spouse_id'] == $fm['id'] ? 'selected' : ''; ?>>
                                                                <?php echo $displayName; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-sm-3">
                                                    <input type="date" class="form-control" name="spouses[<?php echo $index; ?>][marriage_date]" 
                                                           value="<?php echo $spouse['marriage_date']; ?>" placeholder="Marriage Date">
                                                </div>
                                                <div class="col-sm-2">
                                                    <select class="form-select" name="spouses[<?php echo $index; ?>][status]">
                                                        <option value="Current" <?php echo $spouse['marriage_status'] == 'Current' ? 'selected' : ''; ?>>Current</option>
                                                        <option value="Divorced" <?php echo $spouse['marriage_status'] == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                                        <option value="Deceased" <?php echo $spouse['marriage_status'] == 'Deceased' ? 'selected' : ''; ?>>Deceased</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                                                        <i class='bx bx-trash'></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Update Family Member</button>
                <a href="members.php" class="btn btn-link">Cancel</a>
            </form>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let parentCount = <?php echo count($current_parents); ?>;

function addParentField() {
    const container = document.getElementById('parents-container');
    const parentField = document.createElement('div');
    parentField.className = 'row g-3 mb-3 align-items-center parent-row';
    parentField.innerHTML = `
        <div class="col-sm-5">
            <select class="form-select" name="parents[${parentCount}][id]" required>
                <option value="">Select Parent...</option>
                <?php foreach ($family_members as $member): 
                    $displayName = htmlspecialchars($member['name']);
                    if (!empty($member['parents'])) {
                        $displayName .= ' (Child of ' . htmlspecialchars($member['parents']) . ')';
                    } elseif (!empty($member['spouse_name'])) {
                        $displayName .= ' (Spouse of ' . htmlspecialchars($member['spouse_name']) . ')';
                    }
                ?>
                    <option value="<?php echo $member['id']; ?>">
                        <?php echo $displayName; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-5">
            <select class="form-select" name="parents[${parentCount}][type]" required>
                <option value="">Select Relationship...</option>
                <option value="Father">Father</option>
                <option value="Mother">Mother</option>
                <option value="Step-Father">Step-Father</option>
                <option value="Step-Mother">Step-Mother</option>
                <option value="Adoptive Father">Adoptive Father</option>
                <option value="Adoptive Mother">Adoptive Mother</option>
            </select>
        </div>
        <div class="col-sm-2">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                <i class='bx bx-trash'></i>
            </button>
        </div>
    `;
    container.appendChild(parentField);
    parentCount++;
}

// Add one parent field if no parents exist
<?php if (count($current_parents) == 0): ?>
addParentField();
<?php endif; ?>

let spouseCount = <?php echo count($current_spouses); ?>;

function addSpouseField() {
    const container = document.getElementById('spouses-container');
    const spouseField = document.createElement('div');
    spouseField.className = 'row g-3 mb-3 align-items-center spouse-row';
    spouseField.innerHTML = `
        <div class="col-sm-5">
            <select class="form-select" name="spouses[${spouseCount}][id]" required>
                <option value="">Select Spouse...</option>
                <?php foreach ($family_members as $member): 
                    $displayName = htmlspecialchars($member['name']);
                    if (!empty($member['parents'])) {
                        $displayName .= ' (Child of ' . htmlspecialchars($member['parents']) . ')';
                    } elseif (!empty($member['spouse_name'])) {
                        $displayName .= ' (Spouse of ' . htmlspecialchars($member['spouse_name']) . ')';
                    }
                ?>
                    <option value="<?php echo $member['id']; ?>">
                        <?php echo $displayName; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-3">
            <input type="date" class="form-control" name="spouses[${spouseCount}][marriage_date]" placeholder="Marriage Date">
        </div>
        <div class="col-sm-2">
            <select class="form-select" name="spouses[${spouseCount}][status]">
                <option value="Current">Current</option>
                <option value="Divorced">Divorced</option>
                <option value="Deceased">Deceased</option>
            </select>
        </div>
        <div class="col-sm-2">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                <i class='bx bx-trash'></i>
            </button>
        </div>
    `;
    container.appendChild(spouseField);
    spouseCount++;
}

// Add one spouse field if no spouses exist
<?php if (count($current_spouses) == 0): ?>
addSpouseField();
<?php endif; ?>

// Country / State dropdown behaviour
(function() {
    var countrySelect = document.getElementById('country_id');
    var stateIndiaWrap = document.getElementById('state-india-wrap');
    var stateIndiaSelect = document.getElementById('state_india');
    var countryOtherWrap = document.getElementById('country-other-wrap');
    var stateOtherWrap = document.getElementById('state-other-wrap');
    var countryOtherInput = document.getElementById('country_other');
    var stateOtherInput = document.getElementById('state_other');
    var initialStateValue = stateIndiaSelect.getAttribute('data-initial-value') || '';

    function toggleCountryState() {
        var opt = countrySelect.options[countrySelect.selectedIndex];
        var val = countrySelect.value;
        var hasStates = opt ? parseInt(opt.getAttribute('data-has-states'), 10) : 0;
        stateIndiaWrap.style.display = 'none';
        stateIndiaSelect.removeAttribute('required');
        countryOtherWrap.style.display = 'none';
        stateOtherWrap.style.display = 'none';
        countryOtherInput.removeAttribute('required');
        stateOtherInput.removeAttribute('required');
        stateIndiaSelect.innerHTML = '<option value="">Choose state...</option>';
        if (val === '1' && hasStates === 1) {
            stateIndiaWrap.style.display = 'block';
            stateIndiaSelect.setAttribute('required', 'required');
            loadStates(1);
        } else if (val === '2') {
            countryOtherWrap.style.display = 'block';
            stateOtherWrap.style.display = 'block';
            countryOtherInput.setAttribute('required', 'required');
        }
    }
    function loadStates(countryId) {
        fetch('get_states.php?country_id=' + countryId)
            .then(function(r) { return r.json(); })
            .then(function(states) {
                stateIndiaSelect.innerHTML = '<option value="">Choose state...</option>';
                states.forEach(function(s) {
                    var o = document.createElement('option');
                    o.value = s.name;
                    o.textContent = s.name;
                    if (s.name === initialStateValue) o.selected = true;
                    stateIndiaSelect.appendChild(o);
                });
            });
    }
    countrySelect.addEventListener('change', toggleCountryState);
    toggleCountryState();
})();
</script>
</body>
</html> 