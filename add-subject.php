<?php
session_start();
require_once('db.php'); // Use your PDO database connection ($pdo)

// Check if the user is logged in as an admin
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login"); // Use root-relative path
    exit;
}
*/

// Initialize a variable to hold feedback messages
$feedback_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the common data for all subjects
    $subjects = $_POST['subjects'] ?? [];
    $branch = trim(htmlspecialchars($_POST['branch'], ENT_QUOTES, 'UTF-8'));
    $semester_id = filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT); // Changed to semester_id
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);

    // Validate that we have subjects and other required data
    if (empty($subjects) || !$branch || !$semester_id || !$year) {
        $feedback_message = "<p class='message error'>❌ Please fill in all fields, including at least one subject.</p>";
    } else {
        try {
            // Start a transaction
            $pdo->beginTransaction();

            // Prepare the SQL statement once, outside the loop for efficiency
            // Using semester_id to match your new db.php schema
            $sql = "INSERT INTO subjects (name, subject_code, branch, semester_id, year) 
                    VALUES (:subject_name, :subject_code, :branch, :semester_id, :year)
                    ON CONFLICT (subject_code) DO NOTHING"; // PostgreSQL compatible
            
            $stmt = $pdo->prepare($sql);
            $added_count = 0;

            // Loop through each submitted subject and execute the prepared statement
            foreach ($subjects as $subject) {
                $subject_name = trim(htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'));
                $subject_code = trim(htmlspecialchars($subject['subject_code'], ENT_QUOTES, 'UTF-8'));

                // Ensure subject name and code are not empty
                if (!empty($subject_name) && !empty($subject_code)) {
                    $stmt->execute([
                        ':subject_name' => $subject_name,
                        ':subject_code' => $subject_code,
                        ':branch' => $branch,
                        ':semester_id' => $semester_id,
                        ':year' => $year
                    ]);
                    if ($stmt->rowCount() > 0) {
                        $added_count++;
                    }
                }
            }

            // If everything was successful, commit the transaction
            $pdo->commit();
            $feedback_message = "<p class='message success'>✅ $added_count subject(s) added successfully!</p>";

        } catch (PDOException $e) {
            // If any error occurs, roll back the transaction
            $pdo->rollBack();
            $feedback_message = "<p class='message error'>❌ Database Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Fetch semesters for the dropdown
try {
    $semesters = $pdo->query("SELECT id, name FROM semesters ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching semesters: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subjects</title>
    <style>
        /* Using the same color scheme and styles for consistency */
        :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: var(--space-cadet); color: var(--antiflash-white); }
        .back-link { display: block; max-width: 860px; margin: 0 auto 20px auto; text-align: right; font-weight: bold; color: var(--antiflash-white); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .container { max-width: 800px; margin: 20px auto; padding: 30px; background: rgba(141, 153, 174, 0.1); border-radius: 15px; border: 1px solid rgba(141, 153, 174, 0.2); }
        h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 16px; margin-bottom: 8px; color: var(--antiflash-white); font-weight: bold; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; font-size: 16px; border: 1px solid var(--cool-gray); border-radius: 5px; box-sizing: border-box; background: rgba(43, 45, 66, 0.5); color: var(--antiflash-white); }
        button { width: 100%; padding: 12px; font-size: 16px; background-color: var(--fire-engine-red); color: #fff; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; transition: background-color 0.3s ease; font-weight: bold; }
        button:hover { background-color: var(--red-pantone); }
        .subject-form { margin-bottom: 20px; border: 1px solid var(--cool-gray); padding: 15px; border-radius: 5px; position: relative; }
        .add-btn, .remove-btn { font-size: 24px; cursor: pointer; color: var(--cool-gray); border: none; background: none; padding: 5px; line-height: 1; }
        .add-btn { color: var(--antiflash-white); }
        .remove-btn { color: var(--red-pantone); position: absolute; top: 5px; right: 5px; }
        .actions { text-align: right; }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 1em; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <a href="/admin" class="back-link">&laquo; Back to Admin Dashboard</a>

    <div class="container">
        <h2>Add New Subjects (Bulk)</h2>
        
        <!-- Display feedback messages here -->
        <?php if (!empty($feedback_message)) echo $feedback_message; ?>

        <form action="add-subject.php" method="POST">
            <div class="form-group">
                <label for="branch">Branch:</label>
                <select name="branch" id="branch" required>
                    <option value="" disabled selected>-- Select a Branch --</option>
                    <option value="CSE">Computer Science</option>
                    <option value="ECE">Electronics</option>
                    <option value="MECH">Mechanical</option>
                    <option value="CIVIL">Civil</option>
                    <!-- Add other branches as needed -->
                </select>
            </div>
            <div class="form-group">
                <label for="semester">Semester:</label>
                <!-- Use semester_id to match the database schema -->
                <select name="semester" id="semester" required>
                     <option value="">-- Select Semester --</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= htmlspecialchars($sem['id']) ?>"><?= htmlspecialchars($sem['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="year">Year (e.g., 1, 2, 3, 4):</label>
                <input type="number" name="year" id="year" min="1" max="4" required>
            </div>

            <hr style="border-color: var(--cool-gray);">
            <h3>Subjects</h3>
            <div id="subject-form-container">
                <!-- Initial subject form -->
                <div class="subject-form">
                    <div class="form-group">
                        <label>Subject Name:</label>
                        <input type="text" name="subjects[0][subject_name]" required>
                    </div>
                    <div class="form-group">
                        <label>Subject Code:</label>
                        <input type="text" name="subjects[0][subject_code]" required>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="add-btn" onclick="addSubjectForm()">&#43; Add Another Subject</button>
            </div>

            <button type="submit">Add All Subjects</button>
        </form>
    </div>

    <script>
        let subjectCount = 1;
        const maxSubjects = 10;

        function addSubjectForm() {
            if (subjectCount >= maxSubjects) {
                alert('You can add a maximum of ' + maxSubjects + ' subjects at a time.');
                return;
            }

            const container = document.getElementById('subject-form-container');
            const newSubjectForm = document.createElement('div');
            newSubjectForm.classList.add('subject-form');
            newSubjectForm.innerHTML = `
                <div class="form-group">
                    <label>Subject Name:</label>
                    <input type="text" name="subjects[${subjectCount}][subject_name]" required>
                </div>
                <div class="form-group">
                    <label>Subject Code:</label>
                    <input type="text" name="subjects[${subjectCount}][subject_code]" required>
                </div>
                <div class="actions">
                     <button type="button" class="remove-btn" onclick="this.closest('.subject-form').remove()">&#8722;</button>
                </div>
            `;
            container.appendChild(newSubjectForm);
            subjectCount++;
        }
    </script>
</body>
</html>