<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'branch_id',
        'student_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'invoice_date',
        'due_date',
        'invoice_type',
        'subtotal',
        'tax_amount',
        'tax_percentage',
        'discount_amount',
        'discount_percentage',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'payment_date',
        'notes',
        'terms_conditions',
        'academic_year',
        'created_by',
        'sent_at'
    ];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'payment_date' => 'date',
            'sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Student relationship removed - no Student model exists
    // Student data loaded manually in controller via DB::table('students')

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'invoice_transaction')
                    ->withPivot('amount')
                    ->withTimestamps();
    }

    // Accessors
    public function getIsOverdueAttribute(): bool
    {
        if ($this->payment_status === 'Paid') {
            return false;
        }
        return $this->due_date && $this->due_date->isPast();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['Cancelled']);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopeOverdue($query)
    {
        return $query->where('payment_status', '!=', 'Paid')
                     ->where('due_date', '<', now());
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('invoice_date', [$start, $end]);
    }

    public function scopeByStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    // Helper methods
    public static function generateInvoiceNumber()
    {
        $year = date('Y');
        $month = date('m');
        $lastInvoice = self::whereYear('created_at', $year)
                          ->whereMonth('created_at', $month)
                          ->orderBy('id', 'desc')
                          ->first();
        
        $sequence = $lastInvoice ? (int)substr($lastInvoice->invoice_number, -4) + 1 : 1;
        
        return 'INV-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create invoice from transaction(s)
     */
    public static function createFromTransactions($transactions, $data = [])
    {
        $firstTransaction = is_array($transactions) ? $transactions[0] : $transactions;
        
        $subtotal = 0;
        if (is_array($transactions)) {
            foreach ($transactions as $trans) {
                $subtotal += $trans->amount;
            }
        } else {
            $subtotal = $transactions->amount;
        }

        $taxAmount = $data['tax_amount'] ?? 0;
        $discountAmount = $data['discount_amount'] ?? 0;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;

        $invoice = self::create([
            'invoice_number' => self::generateInvoiceNumber(),
            'branch_id' => $data['branch_id'] ?? $firstTransaction->branch_id,
            'customer_name' => $data['customer_name'] ?? $firstTransaction->party_name,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? now(),
            'due_date' => $data['due_date'] ?? now()->addDays(30),
            'invoice_type' => $data['invoice_type'] ?? 'Fee Payment',
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'balance_amount' => $totalAmount,
            'status' => 'Draft',
            'payment_status' => 'Unpaid',
            'academic_year' => $data['academic_year'] ?? date('Y') . '-' . (date('Y') + 1),
            'created_by' => auth()->id()
        ]);

        return $invoice;
    }
}

