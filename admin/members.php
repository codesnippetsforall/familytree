<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Calculate age from date of birth using current date (for display)
function getDisplayAge($dateOfBirth, $storedAge = null) {
    if (empty($dateOfBirth) || $dateOfBirth === '0000-00-00') {
        return $storedAge !== null && $storedAge !== '' ? (int)$storedAge : '—';
    }
    try {
        $dob = new DateTime($dateOfBirth);
        $today = new DateTime('today');
        return $today->diff($dob)->y;
    } catch (Exception $e) {
        return $storedAge !== null && $storedAge !== '' ? (int)$storedAge : '—';
    }
}

// Delete member
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $stmt = $db->prepare("DELETE FROM family_members WHERE id = ?");
    $stmt->execute([$id]);
}

// Add this after the initial query setup
$where_conditions = [];
$params = [];

if (isset($_GET['filter_type']) && isset($_GET['filter_value'])) {
    switch ($_GET['filter_type']) {
        case 'gender':
            $where_conditions[] = "m.sex = ?";
            $params[] = $_GET['filter_value'];
            break;
        case 'age_group':
            // Inner subquery restricts to valid DOB only, so TIMESTAMPDIFF never sees '' (avoids error 1525)
            $valid_dob_sub = "(SELECT id, date_of_birth FROM family_members WHERE date_of_birth IS NOT NULL AND date_of_birth != '0000-00-00')";
            switch ($_GET['filter_value']) {
                case 'Under 10':
                    $where_conditions[] = "m.id IN (SELECT id FROM $valid_dob_sub AS v WHERE TIMESTAMPDIFF(YEAR, v.date_of_birth, CURDATE()) < 10)";
                    break;
                case '10-30':
                    $where_conditions[] = "m.id IN (SELECT id FROM $valid_dob_sub AS v WHERE TIMESTAMPDIFF(YEAR, v.date_of_birth, CURDATE()) BETWEEN 10 AND 30)";
                    break;
                case '31-60':
                    $where_conditions[] = "m.id IN (SELECT id FROM $valid_dob_sub AS v WHERE TIMESTAMPDIFF(YEAR, v.date_of_birth, CURDATE()) BETWEEN 31 AND 60)";
                    break;
                case 'Over 60':
                    $where_conditions[] = "m.id IN (SELECT id FROM $valid_dob_sub AS v WHERE TIMESTAMPDIFF(YEAR, v.date_of_birth, CURDATE()) > 60)";
                    break;
                case 'NA':
                    $where_conditions[] = "(m.date_of_birth IS NULL OR m.date_of_birth = '0000-00-00')";
                    break;
            }
            break;
        case 'country':
            $where_conditions[] = "m.country = ?";
            $params[] = $_GET['filter_value'];
            break;
        case 'quality_filter':
            switch ($_GET['filter_value']) {
                case 'missing_dob':
                    $where_conditions[] = "(m.date_of_birth IS NULL OR m.date_of_birth = '0000-00-00')";
                    break;
                case 'missing_photo':
                    $where_conditions[] = "(m.picture IS NULL OR TRIM(m.picture) = '')";
                    break;
                case 'missing_parents':
                    $where_conditions[] = "NOT EXISTS (SELECT 1 FROM member_parents mp2 WHERE mp2.member_id = m.id)";
                    break;
                case 'missing_spouse':
                    $where_conditions[] = "NOT EXISTS (SELECT 1 FROM member_spouses ms2 WHERE ms2.member_id = m.id)";
                    break;
            }
            break;
        case 'parent_coverage':
            switch ($_GET['filter_value']) {
                case '0 Parents':
                    $where_conditions[] = "(SELECT COUNT(*) FROM member_parents mp2 WHERE mp2.member_id = m.id) = 0";
                    break;
                case '1 Parent':
                    $where_conditions[] = "(SELECT COUNT(*) FROM member_parents mp2 WHERE mp2.member_id = m.id) = 1";
                    break;
                case '2+ Parents':
                    $where_conditions[] = "(SELECT COUNT(*) FROM member_parents mp2 WHERE mp2.member_id = m.id) >= 2";
                    break;
            }
            break;
        case 'spouse_coverage':
            switch ($_GET['filter_value']) {
                case 'Has Spouse':
                    $where_conditions[] = "EXISTS (SELECT 1 FROM member_spouses ms2 WHERE ms2.member_id = m.id)";
                    break;
                case 'No Spouse':
                    $where_conditions[] = "NOT EXISTS (SELECT 1 FROM member_spouses ms2 WHERE ms2.member_id = m.id)";
                    break;
            }
            break;
    }
}

// Let's debug the query first
$query = "
    SELECT 
        m.*,
        GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', mp.relationship_type, ')') SEPARATOR ', ') as parents,
        GROUP_CONCAT(DISTINCT CONCAT(
            s.name
        ) SEPARATOR ', ') as spouses
    FROM family_members m
    LEFT JOIN member_parents mp ON m.id = mp.member_id
    LEFT JOIN family_members p ON mp.parent_id = p.id
    LEFT JOIN member_spouses ms ON m.id = ms.member_id
    LEFT JOIN family_members s ON ms.spouse_id = s.id
";

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

$query .= " GROUP BY m.id ORDER BY m.name";

if (!empty($params)) {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
} else {
    $stmt = $db->query($query);
}
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Members - Family Tree Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Family Members</h1>
                <a href="add_member.php" class="btn btn-primary">Add New Member</a>
            </div>

            <?php if (isset($_GET['filter_type']) && isset($_GET['filter_value'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    Showing members filtered by <?php echo htmlspecialchars($_GET['filter_type']); ?>: 
                    <strong><?php echo htmlspecialchars($_GET['filter_value']); ?></strong>
                    <a href="members.php" class="btn btn-sm btn-outline-primary ms-3">Clear Filter</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mb-3">
                <div></div>
                <a href="#bottom-pagination" class="btn btn-outline-primary btn-sm scroll-bottom">
                    <i class='bx bx-down-arrow-alt'></i> Go to Pagination
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped" id="membersTable">
                    <thead>
                        <tr>
                            <th>Picture</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th>Birth Details</th>
                            <th>Current Location</th>
                            <th>Family Relations</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                        <tr>
                            <td>
                                <?php if (!empty($member['picture']) && is_file(__DIR__ . '/../uploads/' . $member['picture'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($member['picture']); ?>" 
                                         alt="Profile" class="rounded-circle profile-lightbox-trigger" data-fullsrc="../uploads/<?php echo htmlspecialchars($member['picture']); ?>" data-title="<?php echo htmlspecialchars($member['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; cursor: zoom-in;">
                                <?php else: ?>
                                    <i class='bx bxs-user-circle' style="font-size: 50px;"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit_member.php?id=<?php echo $member['id']; ?>" title="Edit">
                                 <?php echo htmlspecialchars($member['name']); ?>
                                </a>
                            </td>
                            <td><?php echo getDisplayAge($member['date_of_birth'] ?? '', $member['age']); ?></td>
                            <td><?php echo htmlspecialchars($member['sex']); ?></td>
                            <td>
                                <?php echo !empty($member['mobile_number']) ? htmlspecialchars($member['mobile_number']) : '—'; ?>
                            </td>
                            <td>
                                <strong>DOB:</strong> <?php echo (!empty($member['date_of_birth']) && $member['date_of_birth'] !== '0000-00-00') ? date('d M Y', strtotime($member['date_of_birth'])) : '—'; ?><br>
                                <strong>Place:</strong> <?php echo htmlspecialchars($member['place_of_birth']); ?><br>
                                <strong>Zodiac:</strong> <?php echo htmlspecialchars($member['zodiac']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($member['living_place']); ?><br>
                                <?php echo htmlspecialchars($member['state']); ?><br>
                                <?php echo htmlspecialchars($member['country']); ?>
                            </td>
                            <td>
                                <strong>Parents:</strong> 
                                <?php if (!empty($member['parents'])): ?>
                                    <?php 
                                    $parents = explode(', ', $member['parents']);
                                    $parentLinks = [];
                                    foreach ($parents as $parent) {
                                        preg_match('/(.+?) \((.*?)\)/', $parent, $matches);
                                        if (count($matches) == 3) {
                                            $parentName = $matches[1];
                                            $relationship = $matches[2];
                                            $parentLinks[] = "<a href='member_details.php?name=" . urlencode($parentName) . "' class='text-primary'>" . 
                                                    htmlspecialchars($parentName) . "</a> (" . htmlspecialchars($relationship) . ")";
                                        }
                                    }
                                    echo implode(', ', $parentLinks);
                                    ?>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                                <br>
                                <strong>Spouses:</strong> 
                                <?php if (!empty($member['spouses'])): ?>
                                    <?php 
                                    $spouseNames = array_map('trim', explode(', ', $member['spouses']));
                                    $spouseLinks = [];
                                    foreach ($spouseNames as $spouseName) {
                                        if ($spouseName !== '') {
                                            $spouseLinks[] = "<a href='member_details.php?name=" . urlencode($spouseName) . "' class='text-primary'>" . 
                                                htmlspecialchars($spouseName) . "</a>";
                                        }
                                    }
                                    echo implode(', ', $spouseLinks);
                                    ?>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="member_details.php?name=<?php echo urlencode($member['name']); ?>" class="btn btn-sm btn-outline-secondary" title="View in tree">
                                    <i class='bx bxs-network-chart'></i>
                                </a>
                                <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                    <i class='bx bxs-edit'></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this member?');">
                                    <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                        <i class='bx bxs-trash'></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row" id="bottom-pagination">
                <div class="col-sm-12 col-md-5">
                    <div class="dataTables_info"></div>
                </div>
                <div class="col-sm-12 col-md-7">
                    <div class="dataTables_paginate paging_simple_numbers"></div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div class="modal fade" id="imageLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageLightboxTitle">Profile Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imageLightboxPreview" src="" alt="Profile Preview" class="img-fluid rounded" style="max-height: 75vh; width: auto;">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    const lightboxModalEl = document.getElementById('imageLightboxModal');
    const lightboxModal = new bootstrap.Modal(lightboxModalEl);
    const lightboxPreview = document.getElementById('imageLightboxPreview');
    const lightboxTitle = document.getElementById('imageLightboxTitle');

    var table = $('#membersTable').DataTable({
        pageLength: 25,
        responsive: true,
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                previous: "Previous",
                next: "Next"
            }
        }
    });

    // Smooth scroll to bottom pagination
    $('.scroll-bottom').on('click', function(e) {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: $('#bottom-pagination').offset().top
        }, 500);
    });

    // Open uploaded image in lightbox modal
    $(document).on('click', '.profile-lightbox-trigger', function() {
        const src = $(this).data('fullsrc');
        const title = $(this).data('title') || 'Profile Image';
        if (!src) return;
        lightboxPreview.src = src;
        lightboxTitle.textContent = title;
        lightboxModal.show();
    });
});
</script>

<style>
.scroll-bottom {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.scroll-bottom i {
    font-size: 1.2rem;
}

.scroll-bottom:hover {
    transform: translateY(2px);
    transition: transform 0.2s;
}

.dataTables_wrapper .dataTables_paginate {
    margin-top: 0.5rem;
}
</style>
</body>
</html> 