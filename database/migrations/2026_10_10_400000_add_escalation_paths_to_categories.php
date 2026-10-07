<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives every category an escalation path, so the SDS admin can escalate any unresolved ticket
 * by hand, following the Student Handbook's line of authority: academic matters go from the
 * Program Chair to the College Dean and then the Campus Director (pp. 8-13, 46-48); offices
 * and student services go to the Campus Director, who heads the campus.
 *
 * The SDS Coordinator is the SDS admin's own account, so no path escalates back to them.
 * A category that already has a path is left as it is. Each change is written to the audit
 * trail under the SDS admin, the same entries saving it in System Settings would make.
 */
return new class extends Migration
{
    private const CAMPUS_DIRECTOR = ['200043'];
    private const GAD = ['230839'];
    private const COLLEGES = [
        'CBME' => [['210537', '210418', '220629', '220873'], ['200214']],
        'CICT' => [['210751', '220384', '210612', '220295'], ['200926']],
    ];

    public function up(): void
    {
        $recipientIds = DB::table('recipients')->pluck('id', 'staff_id');
        $admin = DB::table('users')->where('role', 'sds_admin')->orderByRaw("username = '230515' desc")->orderBy('id')->value('id');

        $academic = [];
        foreach (self::COLLEGES as $college => [$chairs, $dean]) {
            $academic[$college] = [1 => $chairs, 2 => $dean, 3 => self::CAMPUS_DIRECTOR];
        }
        $campusDirector = ['Campus Director' => [1 => self::CAMPUS_DIRECTOR]];

        $paths = [
            'Grades and Examinations' => $academic,
            'Class Schedules and Subject Loads' => $academic,
            'Faculty Conduct and Teaching' => $academic,
            'Enrollment and Admission' => $academic,
            'Gender-Based Sexual Harassment' => ['GAD' => [1 => self::GAD, 2 => self::CAMPUS_DIRECTOR]],
            'Gender and Discrimination Concerns' => ['GAD' => [1 => self::GAD, 2 => self::CAMPUS_DIRECTOR]],
            'Bullying and Harassment' => $campusDirector,
            'Guidance and Counseling' => $campusDirector,
            'Health Services' => $campusDirector,
            'Student Records and Documents' => $campusDirector,
            'Fees and Payments' => $campusDirector,
            'Library Services' => $campusDirector,
            'Student Organizations and Activities' => $campusDirector,
            'Non-Teaching Personnel Conduct' => $campusDirector,
            'Other Concerns' => $campusDirector,
        ];

        foreach ($paths as $name => $categoryPaths) {
            $categoryId = DB::table('complaint_categories')->where('name', $name)->value('id');

            if (! $categoryId || DB::table('escalation_hierarchies')->where('complaint_category_id', $categoryId)->exists()) {
                continue;
            }

            $pathNumber = 0;
            $added = false;

            foreach ($categoryPaths as $pathName => $levels) {
                $pathNumber++;

                foreach ($levels as $level => $staffIds) {
                    foreach ($staffIds as $staffId) {
                        if (! isset($recipientIds[$staffId])) {
                            continue;
                        }

                        DB::table('escalation_hierarchies')->insert([
                            'complaint_category_id' => $categoryId,
                            'path_number' => $pathNumber,
                            'path_name' => $pathName,
                            'level' => $level,
                            'recipient_id' => $recipientIds[$staffId],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $added = true;
                    }
                }
            }

            if ($added) {
                $this->audit($admin, 'escalation_hierarchy_updated', sprintf('Updated the escalation hierarchy of "%s".', $name));
            }
        }

        $this->auditEarlierCategoryChanges($admin);
    }

    /**
     * The earlier handbook migrations changed the categories without an audit entry. They are
     * recorded once here, as the SDS admin saving each change would have recorded them.
     */
    protected function auditEarlierCategoryChanges(?int $admin): void
    {
        if (DB::table('audit_logs')->where('details', 'Updated complaint category "Complaint Against a Student".')->exists()) {
            return;
        }

        foreach (['Academic Concerns', 'Registrar and Records'] as $name) {
            if (! DB::table('complaint_categories')->where('name', $name)->exists()) {
                $this->audit($admin, 'category_deleted', sprintf('Deleted complaint category "%s".', $name));
            }
        }

        $updated = DB::table('complaint_categories')
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('complaint_category_suggested_recipients')
                ->whereColumn('complaint_category_suggested_recipients.complaint_category_id', 'complaint_categories.id'))
            ->orderBy('name')
            ->pluck('name');

        foreach ($updated as $name) {
            $this->audit($admin, 'category_updated', sprintf('Updated complaint category "%s".', $name));
        }

        if (DB::table('complaint_categories')->where('name', 'Complaint Against a Student')->exists()) {
            $this->audit($admin, 'escalation_hierarchy_updated', 'Updated the escalation hierarchy of "Complaint Against a Student".');
        }
    }

    protected function audit(?int $admin, string $action, string $details): void
    {
        DB::table('audit_logs')->insert([
            'ticket_id' => null,
            'performed_by' => $admin,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Escalation paths are kept; change them in System Settings if needed.
    }
};
