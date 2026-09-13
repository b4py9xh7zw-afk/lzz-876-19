<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamPaper extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'total_score',
        'total_time',
        'question_count',
        'type',
        'created_by',
        'status',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'total_time' => 'integer',
        'question_count' => 'integer',
        'type' => 'string',
        'created_by' => 'integer',
        'status' => 'boolean',
    ];

    public const TYPE_FIXED = 'fixed';
    public const TYPE_RANDOM = 'random';

    public const TYPES = [
        self::TYPE_FIXED => '固定题目',
        self::TYPE_RANDOM => '随机抽题',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'exam_paper_questions')
            ->withPivot('id', 'sort_order', 'score')
            ->orderBy('exam_paper_questions.sort_order');
    }

    public function examRecords()
    {
        return $this->hasMany(ExamRecord::class, 'exam_paper_id');
    }

    public function updateQuestionCountAndScore()
    {
        $this->question_count = $this->questions()->count();
        $this->total_score = $this->questions()->sum('exam_paper_questions.score');
        $this->save();
    }
}
