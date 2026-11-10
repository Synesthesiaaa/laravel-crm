<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSuppression extends Model
{
    protected $fillable = ['email', 'reason', 'purpose', 'source', 'created_by_user_id'];
}
