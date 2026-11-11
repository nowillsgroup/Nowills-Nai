<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkforceWorkItem extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['title' => 'encrypted', 'description' => 'encrypted'];
}
