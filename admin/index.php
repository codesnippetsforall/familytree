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
</script>

</body>
</html>
