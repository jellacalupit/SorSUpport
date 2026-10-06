<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The campus is organized into Colleges (with Programs) and Offices, not departments.
 * A college is shared by its students and its academic staff, so unit names are unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->mergeDuplicateDepartments();

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_type_name_unique');
        });

        Schema::rename('departments', 'units');

        DB::table('units')->where('type', 'student')->update(['type' => 'college']);
        DB::table('units')->where('type', 'recipient')->update(['type' => 'office']);

        Schema::table('units', function (Blueprint $table) {
            $table->string('type')->default('office')->change();
            $table->unique('name');
        });

        Schema::rename('department_positions', 'unit_designations');
        Schema::table('unit_designations', function (Blueprint $table) {
            $table->renameColumn('department_id', 'unit_id');
        });

        Schema::rename('department_courses', 'programs');
        Schema::table('programs', function (Blueprint $table) {
            $table->renameColumn('department_id', 'unit_id');
            $table->renameColumn('course', 'name');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('department', 'college');
            $table->renameColumn('course', 'program');
        });

        Schema::table('recipients', function (Blueprint $table) {
            $table->renameColumn('department', 'unit');
        });
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->renameColumn('unit', 'department');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('college', 'department');
            $table->renameColumn('program', 'course');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->renameColumn('unit_id', 'department_id');
            $table->renameColumn('name', 'course');
        });
        Schema::rename('programs', 'department_courses');

        Schema::table('unit_designations', function (Blueprint $table) {
            $table->renameColumn('unit_id', 'department_id');
        });
        Schema::rename('unit_designations', 'department_positions');

        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_name_unique');
            $table->string('type')->default('recipient')->change();
        });

        DB::table('units')->where('type', 'college')->update(['type' => 'student']);
        DB::table('units')->where('type', 'office')->update(['type' => 'recipient']);

        Schema::rename('units', 'departments');

        Schema::table('departments', function (Blueprint $table) {
            $table->unique(['type', 'name']);
        });
    }

    /**
     * A name used by both a student and a recipient department becomes one college that keeps both
     * the courses and the positions.
     */
    protected function mergeDuplicateDepartments(): void
    {
        $recipientDepartments = DB::table('departments')->where('type', 'recipient')->get();

        foreach ($recipientDepartments as $recipientDepartment) {
            $college = DB::table('departments')
                ->where('type', 'student')
                ->where('name', $recipientDepartment->name)
                ->first();

            if (! $college) {
                continue;
            }

            $existingPositions = DB::table('department_positions')
                ->where('department_id', $college->id)
                ->pluck('name')
                ->all();

            DB::table('department_positions')
                ->where('department_id', $recipientDepartment->id)
                ->whereNotIn('name', $existingPositions)
                ->update(['department_id' => $college->id]);

            DB::table('department_positions')->where('department_id', $recipientDepartment->id)->delete();
            DB::table('departments')->where('id', $recipientDepartment->id)->delete();
        }
    }
};
