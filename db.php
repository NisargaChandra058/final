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

/**
 * A simple function to run a database change (migration) only once.
 *
 * @param PDO $pdo The database connection
 * @param string $migration_id A unique name for the change (e.g., 'add_email_constraint')
 * @param string $sql The SQL query to run
 */
function run_migration(PDO $pdo, string $migration_id, string $sql) {
    try {
        // Check if this migration has already been run
        $stmt = $pdo->prepare("SELECT 1 FROM db_migrations WHERE migration_id = ?");
        $stmt->execute([$migration_id]);

        if ($stmt->fetch() === false) {
            // It has not been run. Run it now.
            $pdo->exec($sql);
            
            // Log that this migration is complete
            $log_stmt = $pdo->prepare("INSERT INTO db_migrations (migration_id) VALUES (?)");
            $log_stmt->execute([$migration_id]);
        }
        // If it was already run (fetch() returned true), do nothing.
    } catch (PDOException $e) {
        // If the migration fails, show a clear error.
        die("Migration failed ($migration_id): " . $e->getMessage());
    }
}


try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- 1. CREATE THE MIGRATIONS TABLE ---
    // This table will keep a log of all changes we make.
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_migrations (
        migration_id VARCHAR(255) PRIMARY KEY,
        run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // --- 2. CREATE ALL OTHER TABLES ---
    // These are safe to run because they already have "IF NOT EXISTS".
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
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        first_name VARCHAR(100),
        surname VARCHAR(100),
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'student'
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS semesters (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        semester_id INT
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        subject_code VARCHAR(20) UNIQUE NOT NULL
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS subject_allocation (
        id SERIAL PRIMARY KEY,
        staff_id INT NOT NULL,
        subject_id INT NOT NULL,
        UNIQUE(staff_id, subject_id)
    );");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS question_papers (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        subject_id INT
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_allocation (
        id SERIAL PRIMARY KEY,
        class_id INT NOT NULL,
        qp_id INT NOT NULL,
        UNIQUE(class_id, qp_id)
    );");

    // --- 3. RUN ALL "ALTER TABLE" MIGRATIONS ---
    // These will now only run ONCE.
    
    // Add columns
    run_migration($pdo, 'add_students_password', "ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255);");
    run_migration($pdo, 'add_students_usn', "ALTER TABLE students ADD COLUMN IF NOT EXISTS usn VARCHAR(20);");
    run_migration($pdo, 'add_classes_semester_id', "ALTER TABLE classes ADD COLUMN IF NOT EXISTS semester_id INT;");
    run_migration($pdo, 'add_qp_subject_id', "ALTER TABLE question_papers ADD COLUMN IF NOT EXISTS subject_id INT;");

    // Add constraints
    run_migration($pdo, 'add_constraint_students_email', "ALTER TABLE students ADD CONSTRAINT students_email_unique UNIQUE (email);");
    run_migration($pdo, 'add_constraint_students_usn', "ALTER TABLE students ADD CONSTRAINT students_usn_unique UNIQUE (usn);");

    // Add Foreign Keys (Example)
    // You can now safely add all your foreign keys here.
    // run_migration($pdo, 'add_fk_classes_semester', "ALTER TABLE classes ADD CONSTRAINT fk_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL;");
    // run_migration($pdo, 'add_fk_subject_alloc_staff', "ALTER TABLE subject_allocation ADD CONSTRAINT fk_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE;");
    // run_migration($pdo, 'add_fk_subject_alloc_subject', "ALTER TABLE subject_allocation ADD CONSTRAINT fk_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE;");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>