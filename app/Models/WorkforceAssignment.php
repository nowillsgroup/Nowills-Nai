<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkforceAssignment extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['instruction' => 'encrypted', 'proposal' => 'encrypted:array', 'result' => 'encrypted:array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
}
