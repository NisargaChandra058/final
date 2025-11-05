<?php
// Get the database connection URL from Render's environment variables
$database_url = getenv('DATABASE_URL');

if ($database_url === false) {
    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '5432';
    $dbname = getenv('DB_NAME') ?: 'admission_db';
    $user = getenv('DB_USER') ?: 'user';
    $password = getenv('DB_PASSWORD') ?: 'password';
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=prefer";
} else {
    $db_parts = parse_url($database_url);
    $host = $db_parts['host'];
    $port = $db_parts['port'] ?? '5432';
    $dbname = ltrim($db_parts['path'], '/');
    $user = $db_parts['user'];
    $password = $db_parts['pass'];
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
}

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Students Table ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id SERIAL PRIMARY KEY,
        student_id_text VARCHAR(20) UNIQUE,
        usn VARCHAR(20) UNIQUE,
        student_name VARCHAR(255),
        dob DATE,
        father_name VARCHAR(255),
        mother_name VARCHAR(255),
        mobile_number VARCHAR(20),
        parent_mobile_number VARCHAR(20),
        email VARCHAR(255) UNIQUE,
        password VARCHAR(255),
        permanent_address TEXT,
        previous_college VARCHAR(255),
        previous_combination VARCHAR(50),
        category VARCHAR(50),
        sub_caste VARCHAR(100),
        admission_through VARCHAR(50),
        cet_number VARCHAR(100),
        seat_allotted VARCHAR(100),
        allotted_branch_kea VARCHAR(100),
        allotted_branch_management VARCHAR(100),
        cet_rank VARCHAR(50),
        photo_url TEXT,
        marks_card_url TEXT,
        aadhaar_front_url TEXT,
        aadhaar_back_url TEXT,
        caste_income_url TEXT,
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255);");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS usn VARCHAR(20);");
    $pdo->exec("ALTER TABLE students ADD CONSTRAINT students_email_unique UNIQUE (email);");
    $pdo->exec("ALTER TABLE students ADD CONSTRAINT students_usn_unique UNIQUE (usn);");

    // --- Users Table (for Staff/Admin) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        first_name VARCHAR(100),
        surname VARCHAR(100),
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'student' -- e.g., 'student', 'staff', 'admin'
    );");

    // --- NEW: Semesters Table ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS semesters (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE -- e.g., '1st Semester', '2nd Semester'
    );");

    // --- Classes Table (NOW LINKED TO SEMESTERS) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        semester_id INT -- This is the new column
    );");
    // Add the foreign key constraint if it doesn't exist
    $pdo->exec("ALTER TABLE classes ADD COLUMN IF NOT EXISTS semester_id INT;");
    // Note: We are not adding a foreign key constraint here to avoid errors if 'semesters' table is created after.
    // In a full migration system, you'd add:
    // FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL

    // --- Subjects Table ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        subject_code VARCHAR(20) UNIQUE NOT NULL
    );");

    // --- Subject Allocation Table ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS subject_allocation (
        id SERIAL PRIMARY KEY,
        staff_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        subject_id INT NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
        UNIQUE(staff_id, subject_id)
    );");
    
    // --- Question Papers Table ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS question_papers (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        subject_id INT REFERENCES subjects(id) ON DELETE SET NULL
    );");
    $pdo->exec("ALTER TABLE question_papers ADD COLUMN IF NOT EXISTS subject_id INT;");

    // --- Test Allocation Table ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_allocation (
        id SERIAL PRIMARY KEY,
        class_id INT NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
        qp_id INT NOT NULL REFERENCES question_papers(id) ON DELETE CASCADE,
        UNIQUE(class_id, qp_id)
    );");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>