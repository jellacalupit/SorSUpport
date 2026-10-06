<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ComplaintCategory extends Model
{
    public const JURISDICTION_SDS = 'sds';

    public const JURISDICTION_RECIPIENT = 'recipient';

    protected $fillable = [
        'name',
        'description',
        'recipient_id',
        'resolution_deadline_days',
        'default_jurisdiction',
        'is_active',
        'is_sensitive',
        'allows_hidden_identity',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sensitive' => 'boolean',
            'allows_hidden_identity' => 'boolean',
        ];
    }

    /**
     * Categories based on the student handbook that the SDS admin can add in one step and then adjust.
     *
     * @return list<array{name: string, description: string, is_sensitive: bool, allows_hidden_identity: bool}>
     */
    public static function starterCategories(): array
    {
        return [
            ['name' => 'Academic Concerns', 'description' => 'Grades, examinations, attendance, enrollment, and crediting of subjects.', 'is_sensitive' => false, 'allows_hidden_identity' => false],
            ['name' => 'Complaint Against a Student', 'description' => 'Misconduct by a fellow student under the Rules of Discipline, such as cheating, theft, vandalism, or disturbance.', 'is_sensitive' => false, 'allows_hidden_identity' => true],
            ['name' => 'Faculty and Personnel Conduct', 'description' => 'Unfair treatment, unprofessional behavior, or neglect of duty by teaching or non-teaching personnel.', 'is_sensitive' => false, 'allows_hidden_identity' => true],
            ['name' => 'Gender-Based Sexual Harassment', 'description' => 'Unwelcome sexual remarks, advances, or acts, in person or online. Handled confidentially and referred to the Committee on Decorum and Investigation.', 'is_sensitive' => true, 'allows_hidden_identity' => true],
            ['name' => 'Bullying and Discrimination', 'description' => 'Bullying, intimidation, or unfair treatment because of gender, religion, disability, ethnicity, or similar grounds.', 'is_sensitive' => true, 'allows_hidden_identity' => true],
            ['name' => 'Student Services', 'description' => 'Guidance, health, library, registrar, cashier, scholarship, and other office services.', 'is_sensitive' => false, 'allows_hidden_identity' => false],
            ['name' => 'Facilities, Safety and Security', 'description' => 'Classrooms, equipment, repairs, cleanliness, and safety or security on campus.', 'is_sensitive' => false, 'allows_hidden_identity' => true],
            ['name' => 'Food and Housing', 'description' => 'Canteen and food outlets, and dormitory or housing concerns.', 'is_sensitive' => false, 'allows_hidden_identity' => true],
            ['name' => 'Student Organizations and Activities', 'description' => 'Student council, organizations, fees they collect, and student activities.', 'is_sensitive' => false, 'allows_hidden_identity' => true],
            ['name' => 'Other Concerns', 'description' => 'Concerns that do not fit any other category. The SDS Office decides where they go.', 'is_sensitive' => false, 'allows_hidden_identity' => true],
        ];
    }

    /**
     * Office/recipient assigned to this category.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Complaints under this category.
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'category_id');
    }

    /**
     * Ordered escalation hierarchy for this category.
     */
    public function escalationHierarchies(): HasMany
    {
        return $this->hasMany(EscalationHierarchy::class)->orderBy('path_number')->orderBy('level');
    }

    /**
     * Recipients suggested for this category.
     */
    public function suggestedRecipients(): BelongsToMany
    {
        return $this->belongsToMany(
            Recipient::class,
            'complaint_category_suggested_recipients',
            'complaint_category_id',
            'recipient_id'
        )->with('user')->orderBy('unit');
    }
}