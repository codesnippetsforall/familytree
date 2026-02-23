<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$suggestion = null;
if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM admin_suggestions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $suggestion = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$suggestion) {
    header('Location: suggestions.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $status = $_POST['status'] ?? 'Pending';

    if ($title !== '' && $remarks !== '' && in_array($status, ['Pending', 'In Progress', 'Implemented', 'Rejected'])) {
        $stmt = $db->prepare("UPDATE admin_suggestions SET title = ?, remarks = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $remarks, $status, $suggestion['id']]);
        header('Location: suggestions.php?saved=1');
        exit();
    }
    $error = 'Please provide both title and remarks.';
    $suggestion = array_merge($suggestion, ['title' => $title, 'remarks' => $remarks, 'status' => $status]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Suggestion - Family Tree Admin</title>
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
                <h1 class="h2">Edit Suggestion</h1>
                <a href="suggestions.php" class="btn btn-outline-secondary">Back to List</a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required
                                   placeholder="Short title for the suggestion"
                                   value="<?php echo htmlspecialchars($suggestion['title']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks / Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="5" required
                                      placeholder="Describe the feature improvement or suggestion in detail"><?php echo htmlspecialchars($suggestion['remarks']); ?></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Pending" <?php echo $suggestion['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $suggestion['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Implemented" <?php echo $suggestion['status'] === 'Implemented' ? 'selected' : ''; ?>>Implemented</option>
                                <option value="Rejected" <?php echo $suggestion['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Suggestion</button>
                        <a href="suggestions.php" class="btn btn-link">Cancel</a>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
