<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite or MySQL, we need to recreate the column or use a raw query
        // This works for SQLite by using ALTER TABLE to modify the column
        
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support altering enum directly, so we'll use raw SQL
            DB::statement("
                CREATE TABLE tickets_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    complaint_id BIGINT UNSIGNED UNIQUE NOT NULL,
                    status VARCHAR(255) NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'in_progress', 'resolved', 'rejected', 'closed')),
                    classification VARCHAR(255),
                    assigned_to BIGINT UNSIGNED,
                    deadline DATETIME,
                    acknowledged_at TIMESTAMP,
                    resolved_at TIMESTAMP,
                    closed_at TIMESTAMP,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
                    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
            DB::statement("INSERT INTO tickets_new SELECT * FROM tickets");
            DB::statement("DROP TABLE tickets");
            DB::statement("ALTER TABLE tickets_new RENAME TO tickets");
        } else {
            // For MySQL
            DB::statement("ALTER TABLE tickets MODIFY status ENUM('pending', 'in_progress', 'resolved', 'rejected', 'closed') DEFAULT 'pending'");
        }
        
        // Same for complaints table
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE complaints_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    reference_number VARCHAR(255) UNIQUE NOT NULL,
                    student_id BIGINT UNSIGNED NOT NULL,
                    category_id BIGINT UNSIGNED NOT NULL,
                    subject_title VARCHAR(255) NOT NULL,
                    personnel_involved VARCHAR(255),
                    description TEXT NOT NULL,
                    file_attachment VARCHAR(255),
                    is_anonymous BOOLEAN DEFAULT 0,
                    status VARCHAR(255) NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'in_progress', 'resolved', 'rejected', 'closed')),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                    FOREIGN KEY (category_id) REFERENCES complaint_categories(id) ON DELETE RESTRICT
                )
            ");
            DB::statement("INSERT INTO complaints_new SELECT * FROM complaints");
            DB::statement("DROP TABLE complaints");
            DB::statement("ALTER TABLE complaints_new RENAME TO complaints");
        } else {
            // For MySQL
            DB::statement("ALTER TABLE complaints MODIFY status ENUM('pending', 'in_progress', 'resolved', 'rejected', 'closed') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        // Revert to original enum values
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE tickets_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    complaint_id BIGINT UNSIGNED UNIQUE NOT NULL,
                    status VARCHAR(255) NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'in_progress', 'resolved', 'closed')),
                    classification VARCHAR(255),
                    assigned_to BIGINT UNSIGNED,
                    deadline DATETIME,
                    acknowledged_at TIMESTAMP,
                    resolved_at TIMESTAMP,
                    closed_at TIMESTAMP,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
                    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
            DB::statement("INSERT INTO tickets_new SELECT * FROM tickets WHERE status != 'rejected'");
            DB::statement("DROP TABLE tickets");
            DB::statement("ALTER TABLE tickets_new RENAME TO tickets");
        } else {
            // For MySQL
            DB::statement("ALTER TABLE tickets MODIFY status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending'");
        }
        
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE complaints_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    reference_number VARCHAR(255) UNIQUE NOT NULL,
                    student_id BIGINT UNSIGNED NOT NULL,
                    category_id BIGINT UNSIGNED NOT NULL,
                    subject_title VARCHAR(255) NOT NULL,
                    personnel_involved VARCHAR(255),
                    description TEXT NOT NULL,
                    file_attachment VARCHAR(255),
                    is_anonymous BOOLEAN DEFAULT 0,
                    status VARCHAR(255) NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'in_progress', 'resolved', 'closed')),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                    FOREIGN KEY (category_id) REFERENCES complaint_categories(id) ON DELETE RESTRICT
                )
            ");
            DB::statement("INSERT INTO complaints_new SELECT * FROM complaints WHERE status != 'rejected'");
            DB::statement("DROP TABLE complaints");
            DB::statement("ALTER TABLE complaints_new RENAME TO complaints");
        } else {
            // For MySQL
            DB::statement("ALTER TABLE complaints MODIFY status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending'");
        }
    }
};
