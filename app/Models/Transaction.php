<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'category_id',
        'transaction_number',
        'transaction_date',
        'type',
        'amount',
        'party_name',
        'party_type',
        'party_id',
        'payment_method',
        'payment_reference',
        'bank_name',
        'description',
        'notes',
        'attachments',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'financial_year',
        'month'
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(AccountCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function salaryPayment()
    {
        return $this->hasOne(SalaryPayment::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_transaction')
                    ->withPivot('amount')
                    ->withTimestamps();
    }

    // Scopes
    public function scopeIncome($query)
    {
        return $query->where('type', 'Income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'Expense');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeFinancialYear($query, $year)
    {
        return $query->where('financial_year', $year);
    }
}

