<?php

namespace App\Models;

use App\Enums\StatusTaskEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'result_table_id',
        'status',
        'success_count',
        'skipped_count',
        'message'
    ];

    protected $casts = [
        'status' => StatusTaskEnum::class,
    ];
}
