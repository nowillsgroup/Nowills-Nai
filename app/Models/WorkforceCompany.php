<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkforceCompany extends Model
{
    protected $fillable = ['name'];
    public function memberships(): HasMany { return $this->hasMany(WorkforceMembership::class, 'company_id'); }
    public function assignments(): HasMany { return $this->hasMany(WorkforceAssignment::class, 'company_id'); }
}
