<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Delete suggestion
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $stmt = $db->prepare("DELETE FROM admin_suggestions WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    header('Location: suggestions.php?deleted=1');
    exit();
}

// Fetch all suggestions (newest first)
$stmt = $db->query("
    SELECT s.*, a.username as admin_name
    FROM admin_suggestions s
    LEFT JOIN admins a ON s.admin_id = a.id
    ORDER BY s.updated_at DESC
");
$suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature Suggestions - Family Tree Admin</title>
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
                <h1 class="h2">Feature Improvement &amp; Suggestions</h1>
                <a href="add_suggestion.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Add Suggestion
                </a>
            </div>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    Suggestion deleted.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Suggestion saved successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-4">Enter and manage your UI improvement ideas and remarks. These will be used for future implementation.</p>
                    <?php if (empty($suggestions)): ?>
                        <p class="text-muted">No suggestions yet. <a href="add_suggestion.php">Add your first suggestion</a>.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 2rem;">#</th>
                                        <th>Title</th>
                                        <th>Remarks</th>
                                        <th>Status</th>
                                        <th>Updated</th>
                                        <th style="width: 8rem;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suggestions as $idx => $row): ?>
                                    <tr>
                                        <td class="text-muted"><?php echo $idx + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                        <td>
                                            <span class="text-muted" style="max-width: 300px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($row['remarks']); ?>">
                                                <?php echo htmlspecialchars(mb_strimwidth($row['remarks'], 0, 80, '…')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $badge = match($row['status']) {
                                                'Pending' => 'secondary',
                                                'In Progress' => 'primary',
                                                'Implemented' => 'success',
                                                'Rejected' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                        </td>
                                        <td class="text-muted small"><?php echo date('d M Y, H:i', strtotime($row['updated_at'])); ?></td>
                                        <td>
                                            <a href="edit_suggestion.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class='bx bxs-edit'></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this suggestion?');">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
