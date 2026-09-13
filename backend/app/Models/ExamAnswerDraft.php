<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAnswerDraft extends Model
{
    protected $fillable = [
        'exam_record_id',
        'question_id',
        'answer',
        'status',
        'updated_client_at',
        'synced_at',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'question_id' => 'integer',
        'updated_client_at' => 'integer',
        'synced_at' => 'datetime',
    ];

    public const STATUS_UNANSWERED = 'unanswered';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_FLAGGED = 'flagged';

    public const STATUSES = [
        self::STATUS_UNANSWERED => '未作答',
        self::STATUS_ANSWERED => '已作答',
        self::STATUS_FLAGGED => '待复查',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
