<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'employee_id',
        'employee_type',
        'basic_salary',
        'allowances',
        'deductions',
        'net_salary',
        'salary_month',
        'salary_year',
        'remarks'
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    // Relationships
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}

