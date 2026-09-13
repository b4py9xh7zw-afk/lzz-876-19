<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'type',
        'title',
        'options',
        'answer',
        'analysis',
        'difficulty',
        'score',
        'created_by',
        'status',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'type' => 'string',
        'options' => 'array',
        'difficulty' => 'integer',
        'score' => 'decimal:2',
        'created_by' => 'integer',
        'status' => 'boolean',
    ];

    public const TYPE_SINGLE_CHOICE = 'single_choice';
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_FILL_BLANK = 'fill_blank';
    public const TYPE_ESSAY = 'essay';

    public const TYPES = [
        self::TYPE_SINGLE_CHOICE,
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_TRUE_FALSE,
        self::TYPE_FILL_BLANK,
        self::TYPE_ESSAY,
    ];

    public const DIFFICULTY_EASY = 1;
    public const DIFFICULTY_MEDIUM = 2;
    public const DIFFICULTY_HARD = 3;

    public const DIFFICULTIES = [
        self::DIFFICULTY_EASY => '简单',
        self::DIFFICULTY_MEDIUM => '中等',
        self::DIFFICULTY_HARD => '困难',
    ];

    public function category()
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function examPapers()
    {
        return $this->belongsToMany(ExamPaper::class, 'exam_paper_questions')
            ->withPivot('sort_order', 'score');
    }
}
