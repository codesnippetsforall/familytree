<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch statistics
$db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

// Total family members
$stmt = $db->query("SELECT COUNT(*) as total FROM family_members");
$totalMembers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Gender distribution
$stmt = $db->query("SELECT sex, COUNT(*) as count FROM family_members GROUP BY sex");
$genderStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Age groups: Under 10, 10-30, 31-60, Over 60, NA (DOB not set) - age from current date
$ageGroupOrder = ['Under 10', '10-30', '31-60', 'Over 60', 'NA'];
$stmt = $db->query("
    SELECT 
        CASE 
            WHEN date_of_birth IS NULL OR date_of_birth = '0000-00-00' THEN 'NA'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 10 THEN 'Under 10'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 10 AND 30 THEN '10-30'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 60 THEN '31-60'
            ELSE 'Over 60'
        END as age_group,
        COUNT(*) as count
    FROM family_members
    GROUP BY age_group
");
$ageGroupsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ageGroupsMap = array_column($ageGroupsRaw, 'count', 'age_group');
$ageGroups = [];
foreach ($ageGroupOrder as $group) {
    $ageGroups[] = ['age_group' => $group, 'count' => $ageGroupsMap[$group] ?? 0];
}

// Countries distribution
$stmt = $db->query("SELECT country, COUNT(*) as count FROM family_members GROUP BY country");
$countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data quality metrics
$stmt = $db->query("
    SELECT
        SUM(CASE WHEN date_of_birth IS NULL OR date_of_birth = '0000-00-00' THEN 1 ELSE 0 END) AS missing_dob,
        SUM(CASE WHEN picture IS NULL OR TRIM(picture) = '' THEN 1 ELSE 0 END) AS missing_photo
    FROM family_members
");
$dq = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->query("
    SELECT COUNT(*) AS cnt
    FROM family_members fm
    WHERE NOT EXISTS (
        SELECT 1 FROM member_parents mp WHERE mp.member_id = fm.id
    )
");
$missingParents = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

$stmt = $db->query("
    SELECT COUNT(*) AS cnt
    FROM family_members fm
    WHERE NOT EXISTS (
        SELECT 1 FROM member_spouses ms WHERE ms.member_id = fm.id
    )
");
$missingSpouses = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

$dataQuality = [
    ['label' => 'Missing DOB', 'count' => (int)($dq['missing_dob'] ?? 0)],
    ['label' => 'Missing Photo', 'count' => (int)($dq['missing_photo'] ?? 0)],
    ['label' => 'Missing Parents', 'count' => $missingParents],
    ['label' => 'Missing Spouse', 'count' => $missingSpouses],
];

// Relationship coverage - parents
$stmt = $db->query("
    SELECT
        CASE
            WHEN COALESCE(pc.parent_count, 0) = 0 THEN '0 Parents'
            WHEN COALESCE(pc.parent_count, 0) = 1 THEN '1 Parent'
            ELSE '2+ Parents'
        END AS parent_group,
        COUNT(*) AS count
    FROM family_members fm
    LEFT JOIN (
        SELECT member_id, COUNT(*) AS parent_count
        FROM member_parents
        GROUP BY member_id
    ) pc ON fm.id = pc.member_id
    GROUP BY parent_group
");
$parentCoverageRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$parentCoverageMap = array_column($parentCoverageRaw, 'count', 'parent_group');
$parentCoverage = [
    ['group' => '0 Parents', 'count' => (int)($parentCoverageMap['0 Parents'] ?? 0)],
    ['group' => '1 Parent', 'count' => (int)($parentCoverageMap['1 Parent'] ?? 0)],
    ['group' => '2+ Parents', 'count' => (int)($parentCoverageMap['2+ Parents'] ?? 0)],
];

// Relationship coverage - spouse
$stmt = $db->query("
    SELECT
        CASE
            WHEN COALESCE(sc.spouse_count, 0) = 0 THEN 'No Spouse'
            ELSE 'Has Spouse'
        END AS spouse_group,
        COUNT(*) AS count
    FROM family_members fm
    LEFT JOIN (
        SELECT member_id, COUNT(*) AS spouse_count
        FROM member_spouses
        GROUP BY member_id
    ) sc ON fm.id = sc.member_id
    GROUP BY spouse_group
");
$spouseCoverageRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$spouseCoverageMap = array_column($spouseCoverageRaw, 'count', 'spouse_group');
$spouseCoverage = [
    ['group' => 'Has Spouse', 'count' => (int)($spouseCoverageMap['Has Spouse'] ?? 0)],
    ['group' => 'No Spouse', 'count' => (int)($spouseCoverageMap['No Spouse'] ?? 0)],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Tree Admin - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        .stats-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: none;
            padding: 1rem 1.5rem;
        }
        .stats-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        .dashboard-title {
            color: #2c3e50;
            font-weight: 600;
        }
        .chart-container canvas {
            cursor: pointer;
        }
        .stats-card {
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 dashboard-title">Dashboard Overview</h1>
            </div>

            <!-- Statistics Cards Row -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stats-card bg-primary bg-opacity-10 border-0" 
                         onclick="window.location.href='members.php'">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-primary bg-opacity-25 text-primary">
                                    <i class='bx bxs-group bx-sm'></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo $totalMembers; ?></h5>
                                    <small class="text-muted">Total Members</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Add more stat cards here if needed -->
            </div>

            <!-- Charts Row -->
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Gender Distribution</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Age Distribution</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="ageChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Countries Distribution</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="countryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Analytics Row -->
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Data Quality</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="dataQualityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Relationship Coverage (Parents)</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="parentCoverageChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1 mb-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Relationship Coverage (Spouse)</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="spouseCoverageChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart.js Global Configuration
Chart.defaults.font.family = "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.plugins.legend.position = 'bottom';

// Color palette
const colors = {
    primary: ['#4e73df', '#2e59d9', '#224abe'],
    success: ['#1cc88a', '#13855c', '#0f6848'],
    info: ['#36b9cc', '#258391', '#1a6674'],
    warning: ['#f6c23e', '#dda20a', '#c17702'],
    danger: ['#e74a3b', '#be2617', '#922012']
};

// Function to handle chart clicks
function handleChartClick(type, value) {
    window.location.href = `members.php?filter_type=${type}&filter_value=${encodeURIComponent(value)}`;
}

// Gender Distribution Chart (Blue = Male, Pink = Female, consistent with family tree page)
const genderData = <?php echo json_encode($genderStats); ?>;
const genderColors = genderData.map(item => {
    if (item.sex === 'Male') return '#2563eb';
    if (item.sex === 'Female') return '#db2777';
    return '#64748b'; // Other
});
const genderChart = new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: {
        labels: genderData.map(item => item.sex),
        datasets: [{
            data: genderData.map(item => item.count),
            backgroundColor: genderColors,
            borderWidth: 1,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            }
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                handleChartClick('gender', genderData[index].sex);
            }
        }
    }
});

// Age Distribution Chart (age-wise order, distinct colors per bar)
const ageData = <?php echo json_encode($ageGroups); ?>;
const ageBarColors = ['#2563eb', '#0ea5e9', '#10b981', '#f59e0b', '#64748b'];
const ageChart = new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: ageData.map(item => item.age_group),
        datasets: [{
            label: 'Members',
            data: ageData.map(item => item.count),
            backgroundColor: ageData.map((_, i) => ageBarColors[i % ageBarColors.length]),
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                handleChartClick('age_group', ageData[index].age_group);
            }
        }
    }
});

// Countries Distribution Chart
const countryData = <?php echo json_encode($countries); ?>;
const countryChart = new Chart(document.getElementById('countryChart'), {
    type: 'doughnut',
    data: {
        labels: countryData.map(item => item.country),
        datasets: [{
            data: countryData.map(item => item.count),
            backgroundColor: [...colors.primary, ...colors.success, ...colors.info],
            borderWidth: 1,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            }
        },
        cutout: '60%',
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                handleChartClick('country', countryData[index].country);
            }
        }
    }
});

// Data Quality Chart
const dataQualityData = <?php echo json_encode($dataQuality); ?>;
const dqColors = ['#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
const dataQualityChart = new Chart(document.getElementById('dataQualityChart'), {
    type: 'bar',
    data: {
        labels: dataQualityData.map(item => item.label),
        datasets: [{
            data: dataQualityData.map(item => item.count),
            backgroundColor: dataQualityData.map((_, i) => dqColors[i % dqColors.length]),
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                const keyMap = {
                    'Missing DOB': 'missing_dob',
                    'Missing Photo': 'missing_photo',
                    'Missing Parents': 'missing_parents',
                    'Missing Spouse': 'missing_spouse'
                };
                const selected = dataQualityData[index].label;
                handleChartClick('quality_filter', keyMap[selected] || selected);
            }
        }
    }
});

// Parent Coverage Chart
const parentCoverageData = <?php echo json_encode($parentCoverage); ?>;
const parentCoverageChart = new Chart(document.getElementById('parentCoverageChart'), {
    type: 'bar',
    data: {
        labels: parentCoverageData.map(item => item.group),
        datasets: [{
            data: parentCoverageData.map(item => item.count),
            backgroundColor: ['#94a3b8', '#3b82f6', '#10b981'],
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                handleChartClick('parent_coverage', parentCoverageData[index].group);
            }
        }
    }
});

// Spouse Coverage Chart
const spouseCoverageData = <?php echo json_encode($spouseCoverage); ?>;
const spouseCoverageChart = new Chart(document.getElementById('spouseCoverageChart'), {
    type: 'doughnut',
    data: {
        labels: spouseCoverageData.map(item => item.group),
        datasets: [{
            data: spouseCoverageData.map(item => item.count),
            backgroundColor: ['#14b8a6', '#cbd5e1'],
            borderWidth: 1,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'bottom' } },
        cutout: '60%',
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                handleChartClick('spouse_coverage', spouseCoverageData[index].group);
            }
        }
    }
});
</script>

</body>
</html>
