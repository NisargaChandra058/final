<?php
// 1. SESSION CONFIG
// Ensure this matches your other files
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', 3600);

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php'; // PDO Connection

echo "<div style='padding:20px; font-family:sans-serif; background:#333; color:white;'>";
echo "<h2>🕵️ Session Debugger</h2>";

// -------------------------------------------------------------------------
// CHECK 1: Is Session Active?
// -------------------------------------------------------------------------
echo "<strong>1. Checking User ID:</strong> ";
if (isset($_SESSION['user_id'])) {
    echo "<span style='color:#4ade80'>OK (ID: " . $_SESSION['user_id'] . ")</span><br>";
} else {
    echo "<span style='color:#f87171'>FAIL (Session Empty)</span><br>";
    echo "<pre>"; print_r($_SESSION); echo "</pre>";
    echo "<p>❌ You are not logged in. <a href='student-login.php' style='color:#60a5fa'>Go to Login</a></p></div>";
    exit;
}

// -------------------------------------------------------------------------
// CHECK 2: Is Role Correct?
// -------------------------------------------------------------------------
echo "<strong>2. Checking Role:</strong> ";
$role = strtolower($_SESSION['role'] ?? '');
echo "Current Role: '<strong>$role</strong>' ... ";

if ($role === 'student') {
    echo "<span style='color:#4ade80'>OK</span><br>";
} else {
    echo "<span style='color:#f87171'>FAIL</span><br>";
    echo "<p>❌ Access Denied. Required role: 'student'. Your role is: '$role'.</p></div>";
    exit;
}

echo "<hr style='border-color:#555'><h3>✅ Authentication Passed. Loading Data...</h3></div>";

// -------------------------------------------------------------------------
// DATA FETCHING (Normal Logic)
// -------------------------------------------------------------------------
$user_id = $_SESSION['user_id'];
$results = [];

try {
    // A. Resolve User -> Student
    $stmt_user = $pdo->prepare("SELECT email FROM users WHERE id = :id");
    $stmt_user->execute(['id' => $user_id]);
    $user_email = $stmt_user->fetchColumn();

    if ($user_email) {
        $stmt_stu = $pdo->prepare("SELECT id FROM students WHERE email = :email");
        $stmt_stu->execute(['email' => $user_email]);
        $student_id = $stmt_stu->fetchColumn();
    }

    if (empty($student_id)) {
        die("<div style='padding:20px; color:red; background:#fee2e2;'><strong>Error:</strong> Student Profile not found for email: $user_email</div>");
    }

    // B. Fetch Results
    $sql = "
        SELECT 
            COALESCE(s.name, 'General') AS subject_name,
            qp.title AS test_name,
            ir.marks,
            ir.max_marks,
            ir.created_at
        FROM ia_results ir
        JOIN question_papers qp ON ir.qp_id = qp.id
        LEFT JOIN subjects s ON qp.subject_id = s.id
        WHERE ir.student_id = :sid
        ORDER BY ir.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['sid' => $student_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div style='padding:20px; color:red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IA Results (Debug)</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #007bff; color: white; }
    </style>
</head>
<body>

<div class="container">
    <h2 style="text-align:center">🏆 Results</h2>

    <?php if (empty($results)): ?>
        <p style="text-align:center; color:#777;">No results found.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Subject</th><th>Test</th><th>Score</th></tr></thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['subject_name']) ?></td>
                        <td><?= htmlspecialchars($r['test_name']) ?></td>
                        <td><strong><?= htmlspecialchars($r['marks']) ?></strong> / <?= htmlspecialchars($r['max_marks']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <center style="margin-top:20px;"><a href="student-dashboard.php">Back to Dashboard</a></center>
</div>

</body>
</html>
