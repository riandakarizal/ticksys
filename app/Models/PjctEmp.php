<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;

class PjctEmp extends Model
{
    use LogsSystemActivity;

    protected $table      = 'pjct_emp';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = [
        'id', 'emp_id', 'emp_name', 'emp_level', 'emp_levname',
        'emp_unit', 'emp_div', 'emp_area', 'emp_pjctid',
        'emp_coid', 'emp_contact', 'emp_misc',
    ];
}
