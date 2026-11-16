<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestAnswer extends Model
{
    protected $fillable = ['category_question_id', 'category_name', 'question', 'answer'];
}
