<?php
session_start();
include('db-config.php'); // Database connection

// --- Check admin session ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- Check student ID ---
if (!isset($_GET['id'])) {
    header("Location: view-students.php");
    exit;
}

$student_id = (int)$_GET['id'];
$error = '';
$student = null;

try {
    // Reset if any previous aborted transaction exists
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // --- Handle Update Request ---
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $usn = trim($_POST['usn']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);

        $conn->beginTransaction();

        $update = $conn->prepare("UPDATE students SET usn = ?, name = ?, email = ? WHERE id = ?");
        $update->execute([$usn, $name, $email, $student_id]);

        $conn->commit();

        header("Location: view-students.php?status=updated");
        exit;
    }

    // --- Fetch student details ---
    $stmt = $conn->prepare("SELECT id, usn, name, email FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: view-students.php");
        exit;
    }

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $error = "Database Error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #e3f2fd, #fefefe);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            width: 400px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        label {
            font-weight: bold;
            color: #555;
            margin-top: 10px;
            display: block;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }
        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: #007bff;
            border: none;
            color: #fff;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background: #0056b3;
        }
        .error {
            color: #b71c1c;
            background: #ffebee;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            text-align: center;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 12px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Student Details</h2>

    <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>

    <?php if ($student): ?>
        <form method="POST">
            <label for="usn">USN</label>
            <input type="text" id="usn" name="usn" value="<?= htmlspecialchars($student['usn']) ?>" required>

            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($student['name']) ?>" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>

            <button type="submit">Update Student</button>
        </form>
    <?php else: ?>
        <p>No student record found.</p>
    <?php endif; ?>

    <a href="view-students.php" class="back-link">← Back to Students List</a>
</div>
</body>
</html>