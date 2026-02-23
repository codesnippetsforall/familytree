<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

function addUniqueRelation(&$map, $from, $to) {
    if (!isset($map[$from])) {
        $map[$from] = [];
    }
    if (!in_array($to, $map[$from], true)) {
        $map[$from][] = $to;
    }
}

function genderWord($sex, $maleWord, $femaleWord, $neutralWord) {
    if ($sex === 'Male') return $maleWord;
    if ($sex === 'Female') return $femaleWord;
    return $neutralWord;
}

function ordinal($n) {
    $n = (int)$n;
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 13) return $n . 'th';
    switch ($n % 10) {
        case 1: return $n . 'st';
        case 2: return $n . 'nd';
        case 3: return $n . 'rd';
        default: return $n . 'th';
    }
}

function isDirectRelation($map, $a, $b) {
    return isset($map[$a]) && in_array($b, $map[$a], true);
}

function sharesParent($a, $b, $parentsOf) {
    $aParents = $parentsOf[$a] ?? [];
    $bParents = $parentsOf[$b] ?? [];
    return count(array_intersect($aParents, $bParents)) > 0;
}

function ancestorDepths($memberId, $parentsOf, $maxDepth = 10) {
    $depths = [];
    $queue = [[$memberId, 0]];
    $visited = [$memberId => true];

    while (!empty($queue)) {
        [$current, $depth] = array_shift($queue);
        if ($depth >= $maxDepth) continue;

        foreach ($parentsOf[$current] ?? [] as $parentId) {
            $newDepth = $depth + 1;
            if (!isset($depths[$parentId]) || $newDepth < $depths[$parentId]) {
                $depths[$parentId] = $newDepth;
            }
            if (!isset($visited[$parentId])) {
                $visited[$parentId] = true;
                $queue[] = [$parentId, $newDepth];
            }
        }
    }

    return $depths;
}

function ancestorRelationLabel($distance, $ancestorSex) {
    if ($distance <= 0) return 'relative';
    if ($distance === 1) return genderWord($ancestorSex, 'father', 'mother', 'parent');
    if ($distance === 2) return genderWord($ancestorSex, 'grandfather', 'grandmother', 'grandparent');
    return str_repeat('great-', $distance - 2) . genderWord($ancestorSex, 'grandfather', 'grandmother', 'grandparent');
}

function descendantRelationLabel($distance, $descendantSex) {
    if ($distance <= 0) return 'relative';
    if ($distance === 1) return genderWord($descendantSex, 'son', 'daughter', 'child');
    if ($distance === 2) return genderWord($descendantSex, 'grandson', 'granddaughter', 'grandchild');
    return str_repeat('great-', $distance - 2) . genderWord($descendantSex, 'grandson', 'granddaughter', 'grandchild');
}

function getShortestPath($startId, $endId, $adjacency) {
    if ($startId === $endId) {
        return [['id' => $startId, 'edge' => null]];
    }

    $queue = [$startId];
    $visited = [$startId => true];
    $prev = [];

    while (!empty($queue)) {
        $current = array_shift($queue);
        foreach ($adjacency[$current] ?? [] as $edge) {
            $next = $edge['to'];
            if (isset($visited[$next])) continue;
            $visited[$next] = true;
            $prev[$next] = ['from' => $current, 'label' => $edge['label']];
            if ($next === $endId) {
                $path = [];
                $cursor = $endId;
                while ($cursor !== $startId) {
                    $path[] = ['id' => $cursor, 'edge' => $prev[$cursor]['label']];
                    $cursor = $prev[$cursor]['from'];
                }
                $path[] = ['id' => $startId, 'edge' => null];
                return array_reverse($path);
            }
            $queue[] = $next;
        }
    }

    return [];
}

function determineRelation($aId, $bId, $membersById, $parentsOf, $childrenOf, $spousesOf, $adjacency) {
    if ($aId === $bId) {
        return ['label' => 'same person', 'path' => []];
    }

    $aSex = $membersById[$aId]['sex'] ?? null;
    $bSex = $membersById[$bId]['sex'] ?? null;

    // Direct spouse
    if (isDirectRelation($spousesOf, $aId, $bId)) {
        return ['label' => genderWord($bSex, 'husband', 'wife', 'spouse'), 'path' => []];
    }

    // Parent / Child
    if (isDirectRelation($parentsOf, $aId, $bId)) {
        return ['label' => ancestorRelationLabel(1, $bSex), 'path' => []];
    }
    if (isDirectRelation($childrenOf, $aId, $bId)) {
        return ['label' => descendantRelationLabel(1, $bSex), 'path' => []];
    }

    // Sibling
    if (sharesParent($aId, $bId, $parentsOf)) {
        return ['label' => genderWord($bSex, 'brother', 'sister', 'sibling'), 'path' => []];
    }

    // Ancestor / Descendant (multi-generation)
    $ancA = ancestorDepths($aId, $parentsOf);
    $ancB = ancestorDepths($bId, $parentsOf);
    if (isset($ancA[$bId])) {
        return ['label' => ancestorRelationLabel($ancA[$bId], $bSex), 'path' => []];
    }
    if (isset($ancB[$aId])) {
        return ['label' => descendantRelationLabel($ancB[$aId], $bSex), 'path' => []];
    }

    // Uncle / Aunt
    foreach ($parentsOf[$aId] ?? [] as $aParent) {
        if (sharesParent($aParent, $bId, $parentsOf)) {
            return ['label' => genderWord($bSex, 'uncle', 'aunt', 'aunt/uncle'), 'path' => []];
        }
        foreach ($spousesOf[$aParent] ?? [] as $spouseOfParent) {
            if ($spouseOfParent === $bId) {
                return ['label' => genderWord($bSex, 'step-father', 'step-mother', 'step-parent'), 'path' => []];
            }
        }
    }

    // Nephew / Niece
    foreach ($childrenOf[$aId] ?? [] as $aChild) {
        if (sharesParent($aChild, $bId, $parentsOf)) {
            return ['label' => genderWord($bSex, 'nephew', 'niece', 'nibling'), 'path' => []];
        }
    }

    // In-law (common)
    foreach ($spousesOf[$aId] ?? [] as $aSpouse) {
        if (isDirectRelation($parentsOf, $aSpouse, $bId)) {
            return ['label' => genderWord($bSex, 'father-in-law', 'mother-in-law', 'parent-in-law'), 'path' => []];
        }
        if (sharesParent($aSpouse, $bId, $parentsOf)) {
            return ['label' => genderWord($bSex, 'brother-in-law', 'sister-in-law', 'sibling-in-law'), 'path' => []];
        }
    }

    // Cousin / distant cousin using nearest common ancestor
    $commonAncestors = array_intersect_key($ancA, $ancB);
    if (!empty($commonAncestors)) {
        $best = null;
        foreach ($commonAncestors as $ancestorId => $_) {
            $da = $ancA[$ancestorId];
            $db = $ancB[$ancestorId];
            $score = $da + $db;
            if ($best === null || $score < $best['score']) {
                $best = ['ancestor' => $ancestorId, 'da' => $da, 'db' => $db, 'score' => $score];
            }
        }
        if ($best) {
            $degree = max(1, min($best['da'], $best['db']) - 1);
            $removed = abs($best['da'] - $best['db']);
            $label = ordinal($degree) . ' cousin';
            if ($removed > 0) {
                $label .= ' ' . $removed . ' time' . ($removed > 1 ? 's' : '') . ' removed';
            }
            return ['label' => $label, 'path' => []];
        }
    }

    // Fallback path-based explanation
    $path = getShortestPath($aId, $bId, $adjacency);
    if (!empty($path)) {
        return ['label' => 'related (path found)', 'path' => $path];
    }

    return ['label' => 'no relationship found in current data', 'path' => []];
}

// Load members
$stmt = $db->query("SELECT id, name, sex, picture, place_of_birth, living_place FROM family_members ORDER BY name");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
$membersById = [];
foreach ($members as $m) {
    $membersById[(int)$m['id']] = $m;
}

// Load parent relations
$stmt = $db->query("SELECT member_id, parent_id FROM member_parents");
$parentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$parentsOf = [];
$childrenOf = [];
foreach ($parentRows as $row) {
    $child = (int)$row['member_id'];
    $parent = (int)$row['parent_id'];
    addUniqueRelation($parentsOf, $child, $parent);
    addUniqueRelation($childrenOf, $parent, $child);
}

// Load spouse relations
$stmt = $db->query("SELECT member_id, spouse_id FROM member_spouses");
$spouseRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$spousesOf = [];
foreach ($spouseRows as $row) {
    $a = (int)$row['member_id'];
    $b = (int)$row['spouse_id'];
    addUniqueRelation($spousesOf, $a, $b);
    addUniqueRelation($spousesOf, $b, $a);
}

// Build display labels to disambiguate duplicate names in dropdowns
$memberDisplayLabel = [];
foreach ($members as $m) {
    $id = (int)$m['id'];
    $name = $m['name'];
    $detail = '';

    // Primary disambiguation: parents
    $parentNames = [];
    foreach ($parentsOf[$id] ?? [] as $pid) {
        if (isset($membersById[$pid])) {
            $parentNames[] = $membersById[$pid]['name'];
        }
    }
    if (!empty($parentNames)) {
        $detail = 'Parents: ' . implode(' & ', $parentNames);
    } else {
        // Fallback 1: spouse
        $spouseNames = [];
        foreach ($spousesOf[$id] ?? [] as $sid) {
            if (isset($membersById[$sid])) {
                $spouseNames[] = $membersById[$sid]['name'];
            }
        }
        if (!empty($spouseNames)) {
            $detail = 'Spouse: ' . implode(' & ', $spouseNames);
        } else {
            // Fallback 2: place
            $place = trim((string)($m['living_place'] ?? ''));
            if ($place === '') {
                $place = trim((string)($m['place_of_birth'] ?? ''));
            }
            if ($place !== '') {
                $detail = 'Place: ' . $place;
            } else {
                // Fallback 3: member id
                $detail = 'ID: ' . $id;
            }
        }
    }

    $memberDisplayLabel[$id] = $name . ' (' . $detail . ')';
}

// Build graph adjacency for fallback path explanation
$adjacency = [];
foreach ($parentsOf as $child => $parents) {
    foreach ($parents as $parent) {
        $adjacency[$child][] = ['to' => $parent, 'label' => 'parent'];
        $adjacency[$parent][] = ['to' => $child, 'label' => 'child'];
    }
}
foreach ($spousesOf as $a => $spouses) {
    foreach ($spouses as $b) {
        $adjacency[$a][] = ['to' => $b, 'label' => 'spouse'];
    }
}

$result = null;
$resultReverse = null;
$pathDescription = null;
$pathSteps = [];
$error = null;
$memberAId = null;
$memberBId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberAId = (int)($_POST['member_a_id'] ?? 0);
    $memberBId = (int)($_POST['member_b_id'] ?? 0);

    if ($memberAId <= 0 || $memberBId <= 0) {
        $error = 'Please select both members.';
    } elseif ($memberAId === $memberBId) {
        $error = 'Please choose two different members.';
    } elseif (!isset($membersById[$memberAId]) || !isset($membersById[$memberBId])) {
        $error = 'Selected member is invalid.';
    } else {
        $result = determineRelation($memberAId, $memberBId, $membersById, $parentsOf, $childrenOf, $spousesOf, $adjacency);
        $resultReverse = determineRelation($memberBId, $memberAId, $membersById, $parentsOf, $childrenOf, $spousesOf, $adjacency);

        $path = getShortestPath($memberAId, $memberBId, $adjacency);
        if (!empty($path)) {
            $parts = [];
            for ($i = 0; $i < count($path); $i++) {
                $nodeId = $path[$i]['id'];
                $parts[] = $membersById[$nodeId]['name'] ?? ('#' . $nodeId);
                $pathSteps[] = [
                    'type' => 'node',
                    'name' => $membersById[$nodeId]['name'] ?? ('#' . $nodeId),
                    'sex' => $membersById[$nodeId]['sex'] ?? null,
                    'picture' => $membersById[$nodeId]['picture'] ?? '',
                ];
                if ($i < count($path) - 1) {
                    $edge = $path[$i + 1]['edge'] ?? 'related';
                    $parts[] = '--' . $edge . '-->';
                    $pathSteps[] = [
                        'type' => 'edge',
                        'label' => $edge
                    ];
                }
            }
            $pathDescription = implode(' ', $parts);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Relationship - Family Tree Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .relation-flow-wrap {
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        .relation-flow {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            min-width: max-content;
            padding: 0.5rem 0.25rem;
        }
        .relation-node {
            min-width: 150px;
            max-width: 170px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.6rem 0.55rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
        }
        .relation-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            margin: 0 auto 0.4rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
        }
        .relation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .relation-avatar.male {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .relation-avatar.female {
            background: #fce7f3;
            color: #be185d;
        }
        .relation-avatar.other {
            background: #e2e8f0;
            color: #475569;
        }
        .relation-avatar i {
            font-size: 1.4rem;
        }
        .relation-node-name {
            font-weight: 600;
            color: #0f172a;
            font-size: 0.88rem;
            line-height: 1.2rem;
            word-break: break-word;
        }
        .relation-edge {
            color: #334155;
            font-size: 0.8rem;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.5rem;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .relation-edge.parent { background: #eef2ff; border-color: #c7d2fe; color: #3730a3; }
        .relation-edge.child { background: #ecfeff; border-color: #a5f3fc; color: #0f766e; }
        .relation-edge.spouse { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
        .relation-edge.related { background: #f8fafc; border-color: #e2e8f0; color: #334155; }
        .relation-legend .badge {
            font-weight: 500;
            font-size: 0.78rem;
            margin-right: 0.45rem;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Find Relationship</h1>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Select two different members and click <strong>Search Relationship</strong> to identify how they are related.
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Member 1</label>
                            <select name="member_a_id" class="form-select" required>
                                <option value="">Choose member...</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)$memberAId === (int)$m['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($memberDisplayLabel[(int)$m['id']] ?? $m['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Member 2</label>
                            <select name="member_b_id" class="form-select" required>
                                <option value="">Choose member...</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)$memberBId === (int)$m['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($memberDisplayLabel[(int)$m['id']] ?? $m['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class='bx bx-search-alt-2'></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($result && $resultReverse && !$error): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Relationship Result</h5>
                        <div class="alert alert-success">
                            <strong><?php echo htmlspecialchars($membersById[$memberAId]['name']); ?></strong>
                            is
                            <strong><?php echo htmlspecialchars($resultReverse['label']); ?></strong>
                            of
                            <strong><?php echo htmlspecialchars($membersById[$memberBId]['name']); ?></strong>.
                        </div>

                        <div class="alert alert-primary">
                            <strong><?php echo htmlspecialchars($membersById[$memberBId]['name']); ?></strong>
                            is
                            <strong><?php echo htmlspecialchars($result['label']); ?></strong>
                            of
                            <strong><?php echo htmlspecialchars($membersById[$memberAId]['name']); ?></strong>.
                        </div>

                        <?php if (!empty($pathDescription)): ?>
                            <div class="mt-3">
                                <h6 class="mb-2">How they are connected</h6>
                                <div class="bg-light border rounded p-3 small">
                                    <?php echo htmlspecialchars($pathDescription); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($pathSteps)): ?>
                            <div class="mt-3">
                                <h6 class="mb-2">Visual relationship path</h6>
                                <div class="relation-legend mb-2">
                                    <span class="badge bg-light text-dark border"><i class='bx bx-up-arrow-alt'></i> parent</span>
                                    <span class="badge bg-light text-dark border"><i class='bx bx-down-arrow-alt'></i> child</span>
                                    <span class="badge bg-light text-dark border"><i class='bx bx-heart'></i> spouse</span>
                                </div>
                                <div class="relation-flow-wrap border rounded bg-white p-3">
                                    <div class="relation-flow">
                                        <?php foreach ($pathSteps as $step): ?>
                                            <?php if ($step['type'] === 'node'): ?>
                                                <div class="relation-node">
                                                    <?php
                                                    $sex = $step['sex'] ?? null;
                                                    $avatarClass = ($sex === 'Male') ? 'male' : (($sex === 'Female') ? 'female' : 'other');
                                                    $picture = $step['picture'] ?? '';
                                                    $pictureExists = !empty($picture) && is_file(__DIR__ . '/../uploads/' . $picture);
                                                    ?>
                                                    <div class="relation-avatar <?php echo $avatarClass; ?>">
                                                        <?php if ($pictureExists): ?>
                                                            <img src="../uploads/<?php echo htmlspecialchars($picture); ?>" alt="<?php echo htmlspecialchars($step['name']); ?>">
                                                        <?php else: ?>
                                                            <i class='bx bxs-user-circle'></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="relation-node-name"><?php echo htmlspecialchars($step['name']); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <?php
                                                $edgeLabel = $step['label'] ?? 'related';
                                                $edgeClass = in_array($edgeLabel, ['parent', 'child', 'spouse'], true) ? $edgeLabel : 'related';
                                                $edgeIcon = 'bx-right-arrow-alt';
                                                if ($edgeLabel === 'parent') $edgeIcon = 'bx-up-arrow-alt';
                                                if ($edgeLabel === 'child') $edgeIcon = 'bx-down-arrow-alt';
                                                if ($edgeLabel === 'spouse') $edgeIcon = 'bx-heart';
                                                ?>
                                                <span class="relation-edge <?php echo $edgeClass; ?>">
                                                    <i class='bx <?php echo $edgeIcon; ?>'></i>
                                                    <?php echo htmlspecialchars($edgeLabel); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

