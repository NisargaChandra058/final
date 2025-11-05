<?php
session_start();
require_once('db.php'); // Uses your new PDO setup

// Only admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = "";

/* -------------------------------------------------------
   HANDLE CSV UPLOAD (BULK ADD)
-------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_csv'])) {
    $fileName = $_FILES['student_csv']['tmp_name'];

    if ($_FILES['student_csv']['size'] > 0) {
        try {
            $pdo->beginTransaction();

            $file = fopen($fileName, "r");
            $header = true;
            $stmt = $pdo->prepare("
                INSERT INTO students (usn, student_name, email, dob, branch, password)
                VALUES (:usn, :student_name, :email, :dob, :branch, :password)
                ON CONFLICT (usn) DO NOTHING
            ");

            while (($data = fgetcsv($file, 1000, ",")) !== false) {
                // Skip header row if present
                if ($header) {
                    $header = false;
                    continue;
                }

                $usn = trim($data[0] ?? '');
                $student_name = trim($data[1] ?? '');
                $email = trim($data[2] ?? '');
                $dob = trim($data[3] ?? null);
                $branch = trim($data[4] ?? '');
                $plain_password = trim($data[5] ?? '12345');
                $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

                if (!empty($usn) && !empty($student_name) && !empty($email)) {
                    $stmt->execute([
                        ':usn' => $usn,
                        ':student_name' => $student_name,
                        ':email' => $email,
                        ':dob' => $dob ?: null,
                        ':branch' => $branch,
                        ':password' => $hashed_password
                    ]);
                }
            }

            fclose($file);
            $pdo->commit();
            $message = "<p class='success-message'>✅ Students added successfully from CSV!</p>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "<p class='error-message'>❌ Error adding students: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        $message = "<p class='error-message'>❌ Please upload a valid CSV file.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; padding: 0; }
        .navbar { background-color: #333; padding: 10px; text-align: center; }
        .navbar a { color: #fff; text-decoration: none; font-size: 18px; }
        .navbar a:hover { color: #f1f1f1; }
        .content { width: 85%; margin: 20px auto; background-color: #fff; padding: 30px;
                   border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2, h3 { text-align: center; color: #333; }
        form { display: flex; flex-direction: column; align-items: center; }
        input, textarea, select, button {
            width: 80%; max-width: 400px; padding: 10px; margin: 10px 0;
            border: 1px solid #ccc; border-radius: 4px; font-size: 16px;
        }
        button { background-color: #4CAF50; color: white; cursor: pointer; transition: 0.3s; }
        button:hover { background-color: #45a049; }
        .message, .success-message, .error-message {
            text-align: center; font-weight: bold; padding: 10px; border-radius: 5px;
        }
        .success-message { background: #d4edda; color: #155724; }
        .error-message { background: #f8d7da; color: #721c24; }
        button[type="button"] { background-color: #007bff; border: none; }
        button[type="button"]:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="admin-panel.php">← Back to Admin Dashboard</a>
    </div>

    <div class="content">
        <h2>Add Students</h2>
        <?= $message ?>

        <h3>📁 Upload CSV for Bulk Registration</h3>
        <form action="add-student.php" method="POST" enctype="multipart/form-data">
            <input type="file" name="student_csv" accept=".csv" required>
            <button type="submit">Upload</button>
            <button type="button" onclick="downloadSample()">Download Sample CSV</button>
        </form>

        <h3>✍️ Manually Add a Student</h3>
        <form action="manual-add-student.php" method="POST">
            <input type="text" name="usn" placeholder="USN" required>
            <input type="text" name="student_name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="date" name="dob" placeholder="Date of Birth">
            <textarea name="address" placeholder="Address"></textarea>

            <select name="branch" required>
                <option value="">-- Select Branch --</option>
                <option value="CSE">CSE</option>
                <option value="ECE">ECE</option>
                <option value="MECH">MECH</option>
                <option value="CIVIL">CIVIL</option>
            </select>

            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Add Student</button>
        </form>
    </div>

    <script>
        function downloadSample() {
            const csvContent = "USN,Full Name,Email,Date of Birth,Branch,Password\n" +
                               "1RV22CS001,John Doe,john@example.com,2003-04-10,CSE,password123\n" +
                               "1RV22CS002,Jane Smith,jane@example.com,2003-06-12,CSE,password456\n";
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'sample.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>