<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings the configured complaint categories in line with the Student Handbook (Revised 2024):
 * deadlines the handbook sets, who a student may file with, and which matters stay confidential.
 *
 * Categories are found by name and staff by staff ID, so anything not configured yet is skipped.
 */
return new class extends Migration
{
    // Staff IDs of the Bulan Campus personnel configured in SorSUpport.
    private const SDS = ['230515'];
    private const CAMPUS_DIRECTOR = ['200043'];
    private const DEANS = ['200214', '200926'];
    private const CBME_CHAIRS = ['210537', '210418', '220629', '220873'];
    private const CICT_CHAIRS = ['210751', '220384', '210612', '220295'];
    private const FACULTY = ['240145', '250392', '240573', '260168', '251463'];
    private const GUIDANCE = ['220247'];
    private const NURSE = ['230516'];
    private const REGISTRAR = ['210764'];
    private const CASHIER = ['240431'];
    private const LIBRARIAN = ['220687'];
    private const GAD = ['230839'];

    public function up(): void
    {
        $chairs = [...self::CBME_CHAIRS, ...self::CICT_CHAIRS];

        // name => [deadline in days, sensitive, suggested recipients]
        $categories = [
            // Adding or changing subjects closes 5 days after classes open (p. 11).
            'Class Schedules and Subject Loads' => [3, false, [...$chairs, ...self::DEANS, ...self::REGISTRAR]],
            // Late enrollees have a 5-day grace period (p. 9).
            'Enrollment and Admission' => [3, false, [...self::REGISTRAR, ...self::DEANS]],
            // Refunds must be applied for within two weeks of the opening of classes (p. 14).
            'Fees and Payments' => [3, false, self::CASHIER],
            'Library Services' => [3, false, self::LIBRARIAN],
            'Health Services' => [3, false, self::NURSE],
            // Students have the right to the expeditious issuance of documents (p. 60); Guidance
            // issues the Certificate of Good Moral Character (p. 30).
            'Student Records and Documents' => [5, false, [...self::REGISTRAR, ...self::GUIDANCE]],
            // Counseling records are kept with utmost confidentiality (pp. 28-29).
            'Guidance and Counseling' => [5, true, [...self::GUIDANCE, ...self::SDS]],
            'Grades and Examinations' => [7, false, [...self::FACULTY, ...$chairs, ...self::REGISTRAR]],
            'Student Organizations and Activities' => [7, false, self::SDS],
            'Other Concerns' => [7, false, self::SDS],
            // The Committee on Decorum and Investigation decides within 10 working days (p. 57);
            // complaints may be filed with the Guidance or GAD Office (p. 58).
            'Gender-Based Sexual Harassment' => [10, true, [...self::GAD, ...self::GUIDANCE]],
            'Gender and Discrimination Concerns' => [10, true, [...self::GAD, ...self::GUIDANCE]],
            // A complaint is filed with the SDS Coordinator or the student's teacher or adviser, and
            // disciplinary matters are strictly confidential (pp. 46, 50).
            'Complaint Against a Student' => [15, true, [...self::SDS, ...self::FACULTY]],
            'Bullying and Harassment' => [15, true, [...self::GUIDANCE, ...self::SDS]],
            'Faculty Conduct and Teaching' => [15, false, [...$chairs, ...self::DEANS]],
            'Non-Teaching Personnel Conduct' => [15, false, [...self::CAMPUS_DIRECTOR, ...self::SDS]],
        ];

        $recipientIds = DB::table('recipients')->pluck('id', 'staff_id');
        $idsFor = fn (array $staffIds): array => collect($staffIds)
            ->map(fn (string $staffId) => $recipientIds[$staffId] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($categories as $name => [$days, $sensitive, $suggested]) {
            $categoryId = DB::table('complaint_categories')->where('name', $name)->value('id');

            if (! $categoryId) {
                continue;
            }

            DB::table('complaint_categories')->where('id', $categoryId)->update([
                'resolution_deadline_days' => $days,
                'is_sensitive' => $sensitive,
                // A sensitive category always lets the student hide their name.
                ...($sensitive ? ['allows_hidden_identity' => true] : []),
                'updated_at' => now(),
            ]);

            DB::table('complaint_category_suggested_recipients')->where('complaint_category_id', $categoryId)->delete();
            DB::table('complaint_category_suggested_recipients')->insert(array_map(fn (int $recipientId) => [
                'complaint_category_id' => $categoryId,
                'recipient_id' => $recipientId,
            ], $idsFor($suggested)));
        }

        // A complaint against a student goes from the Program Chair to the College Dean, then to
        // the Campus Disciplinary Committee chaired by the Campus Director (pp. 46, 48).
        $againstStudent = DB::table('complaint_categories')->where('name', 'Complaint Against a Student')->value('id');

        if ($againstStudent) {
            DB::table('escalation_hierarchies')->where('complaint_category_id', $againstStudent)->delete();

            $paths = [
                'CBME' => [self::CBME_CHAIRS, ['200214']],
                'CICT' => [self::CICT_CHAIRS, ['200926']],
            ];
            $pathNumber = 0;

            foreach ($paths as $college => [$collegeChairs, $dean]) {
                $pathNumber++;
                $levels = [1 => $collegeChairs, 2 => $dean, 3 => self::CAMPUS_DIRECTOR];

                foreach ($levels as $level => $staffIds) {
                    foreach ($idsFor($staffIds) as $recipientId) {
                        DB::table('escalation_hierarchies')->insert([
                            'complaint_category_id' => $againstStudent,
                            'path_number' => $pathNumber,
                            'path_name' => $college,
                            'level' => $level,
                            'recipient_id' => $recipientId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        // These two overlap the more specific categories above, so students are not asked to
        // choose between them. They are turned off, not deleted, and can be turned back on.
        DB::table('complaint_categories')
            ->whereIn('name', ['Academic Concerns', 'Registrar and Records'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('complaint_categories')
            ->whereIn('name', ['Academic Concerns', 'Registrar and Records'])
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};
