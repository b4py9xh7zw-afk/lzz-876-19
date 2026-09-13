<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    protected $hidden = [];

    public function parent()
    {
        return $this->belongsTo(QuestionCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(QuestionCategory::class, 'parent_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'category_id');
    }
}
