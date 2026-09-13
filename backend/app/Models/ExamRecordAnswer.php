<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRecordAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_record_id',
        'question_id',
        'answer',
        'is_correct',
        'score',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'question_id' => 'integer',
        'is_correct' => 'boolean',
        'score' => 'decimal:2',
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
