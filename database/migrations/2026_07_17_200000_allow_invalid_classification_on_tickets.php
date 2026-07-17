<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement('CREATE TABLE tickets_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                complaint_id BIGINT UNSIGNED UNIQUE NOT NULL,
                status VARCHAR(255) NOT NULL DEFAULT "pending",
                classification VARCHAR(255) NULL CHECK (classification IS NULL OR classification IN ("informational", "needs_resolution", "invalid")),
                jurisdiction VARCHAR(255) NULL,
                closure_reason TEXT NULL,
                assigned_to BIGINT UNSIGNED NULL,
                deadline DATETIME NULL,
                acknowledged_at TIMESTAMP NULL,
                resolved_at TIMESTAMP NULL,
                closed_at TIMESTAMP NULL,
                forwarded_at DATETIME NULL,
                forwarded_to BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (forwarded_to) REFERENCES recipients(id) ON DELETE SET NULL
            )');

            DB::statement('INSERT INTO tickets_new (id, complaint_id, status, classification, jurisdiction, closure_reason, assigned_to, deadline, acknowledged_at, resolved_at, closed_at, forwarded_at, forwarded_to, created_at, updated_at) SELECT id, complaint_id, status, classification, jurisdiction, closure_reason, assigned_to, deadline, acknowledged_at, resolved_at, closed_at, forwarded_at, forwarded_to, created_at, updated_at FROM tickets');
            DB::statement('DROP TABLE tickets');
            DB::statement('ALTER TABLE tickets_new RENAME TO tickets');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("ALTER TABLE tickets MODIFY classification ENUM('informational','needs_resolution','invalid') NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement('CREATE TABLE tickets_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                complaint_id BIGINT UNSIGNED UNIQUE NOT NULL,
                status VARCHAR(255) NOT NULL DEFAULT "pending",
                classification VARCHAR(255) NULL CHECK (classification IS NULL OR classification IN ("informational", "needs_resolution")),
                jurisdiction VARCHAR(255) NULL,
                closure_reason TEXT NULL,
                assigned_to BIGINT UNSIGNED NULL,
                deadline DATETIME NULL,
                acknowledged_at TIMESTAMP NULL,
                resolved_at TIMESTAMP NULL,
                closed_at TIMESTAMP NULL,
                forwarded_at DATETIME NULL,
                forwarded_to BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (forwarded_to) REFERENCES recipients(id) ON DELETE SET NULL
            )');

            DB::statement('INSERT INTO tickets_new (id, complaint_id, status, classification, jurisdiction, closure_reason, assigned_to, deadline, acknowledged_at, resolved_at, closed_at, forwarded_at, forwarded_to, created_at, updated_at) SELECT id, complaint_id, status, classification, jurisdiction, closure_reason, assigned_to, deadline, acknowledged_at, resolved_at, closed_at, forwarded_at, forwarded_to, created_at, updated_at FROM tickets');
            DB::statement('DROP TABLE tickets');
            DB::statement('ALTER TABLE tickets_new RENAME TO tickets');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("ALTER TABLE tickets MODIFY classification ENUM('informational','needs_resolution') NULL");
        }
    }
};
