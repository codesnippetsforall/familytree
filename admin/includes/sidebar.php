<nav class="col-md-3 col-lg-2 d-md-block sidebar" style="background: var(--ft-sidebar-bg, #f8fafc);">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class='bx bxs-dashboard'></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'active' : ''; ?>" href="members.php">
                    <i class='bx bxs-user-detail'></i> Family Members
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_member.php' ? 'active' : ''; ?>" href="add_member.php">
                    <i class='bx bxs-user-plus'></i> Add Member
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'export.php' ? 'active' : ''; ?>" href="export.php">
                    <i class='bx bxs-download'></i> Export Data & DB
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['suggestions.php', 'add_suggestion.php', 'edit_suggestion.php']) ? 'active' : ''; ?>" href="suggestions.php">
                    <i class='bx bxs-message-square-detail'></i> Suggestions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'relationship_finder.php' ? 'active' : ''; ?>" href="relationship_finder.php">
                    <i class='bx bx-git-compare'></i> Find Relationship
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class='bx bxs-log-out'></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav> 