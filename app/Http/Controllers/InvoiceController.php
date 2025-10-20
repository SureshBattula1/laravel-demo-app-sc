<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    /**
     * Get all invoices
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Invoice::with(['branch', 'createdBy', 'items', 'transactions']);

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('from_date') && $request->has('to_date')) {
                $query->byDateRange($request->from_date, $request->to_date);
            }

            if ($request->has('overdue') && $request->boolean('overdue')) {
                $query->overdue();
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('invoice_number', 'like', '%' . $search . '%')
                      ->orWhere('customer_name', 'like', '%' . $search . '%');
                });
            }

            $invoices = $query->orderBy('invoice_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $invoices,
                'count' => $invoices->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get invoices error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoices'
            ], 500);
        }
    }

    /**
     * Search transactions for invoice generation
     * Supports: Student name/roll, Teacher name, Expense names, Date range
     */
    public function searchTransactions(Request $request)
    {
        try {
            $query = Transaction::with(['branch', 'category'])
                ->where('status', 'Approved');

            // Filter by transaction type (Income or Expense)
            if ($request->has('type')) {
                $query->where('type', $request->type);
            } else {
                // Default: both income and expense
                $query->whereIn('type', ['Income', 'Expense']);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Enhanced search: Student name, roll number, teacher name, expense name
            if ($request->has('search_term')) {
                $searchTerm = strip_tags($request->search_term);
                
                $query->where(function($q) use ($searchTerm) {
                    // Search by party name
                    $q->where('party_name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('description', 'like', '%' . $searchTerm . '%');
                    
                    // Search by student roll number or name
                    $q->orWhereExists(function($subQ) use ($searchTerm) {
                        $subQ->select(DB::raw(1))
                             ->from('students')
                             ->whereColumn('students.id', 'transactions.party_id')
                             ->where('transactions.party_type', 'Student')
                             ->where(function($studentQ) use ($searchTerm) {
                                 $studentQ->where('roll_number', 'like', '%' . $searchTerm . '%')
                                          ->orWhere('admission_number', 'like', '%' . $searchTerm . '%')
                                          ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $searchTerm . '%');
                             });
                    });
                    
                    // Search by teacher name
                    $q->orWhereExists(function($subQ) use ($searchTerm) {
                        $subQ->select(DB::raw(1))
                             ->from('users')
                             ->whereColumn('users.id', 'transactions.party_id')
                             ->where('transactions.party_type', 'Teacher')
                             ->where('role', 'Teacher')
                             ->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $searchTerm . '%');
                    });
                });
            }

            // Legacy party_name filter (for backward compatibility)
            if ($request->has('party_name')) {
                $query->where('party_name', 'like', '%' . $request->party_name . '%');
            }

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Date range filter
            if ($request->has('from_date') && $request->has('to_date')) {
                $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
            } elseif ($request->has('from_date')) {
                $query->where('transaction_date', '>=', $request->from_date);
            } elseif ($request->has('to_date')) {
                $query->where('transaction_date', '<=', $request->to_date);
            }

            // Exclude transactions already invoiced (unless explicitly requested)
            if (!$request->has('include_invoiced') || !$request->boolean('include_invoiced')) {
                $query->whereDoesntHave('invoices');
            }

            $transactions = $query->orderBy('transaction_date', 'desc')
                                  ->limit($request->get('limit', 100))
                                  ->get();

            // Add additional data for display
            $transactions->each(function($transaction) {
                // Add student or teacher details if available
                if ($transaction->party_type === 'Student' && $transaction->party_id) {
                    $student = DB::table('students')->find($transaction->party_id);
                    if ($student) {
                        $transaction->student_details = [
                            'name' => $student->first_name . ' ' . $student->last_name,
                            'roll_number' => $student->roll_number,
                            'admission_number' => $student->admission_number
                        ];
                    }
                }
                
                if ($transaction->party_type === 'Teacher' && $transaction->party_id) {
                    $teacher = DB::table('users')->find($transaction->party_id);
                    if ($teacher) {
                        $transaction->teacher_details = [
                            'name' => $teacher->first_name . ' ' . $teacher->last_name,
                            'email' => $teacher->email
                        ];
                    }
                }
            });

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'count' => $transactions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Search transactions error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search transactions',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Generate invoice from transactions
     */
    public function generateFromTransactions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'transaction_ids' => 'required|array|min:1',
                'transaction_ids.*' => 'exists:transactions,id',
                'invoice_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:invoice_date',
                'customer_name' => 'required|string',
                'tax_percentage' => 'nullable|numeric|min:0|max:100',
                'discount_percentage' => 'nullable|numeric|min:0|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $transactions = Transaction::with('category')->whereIn('id', $request->transaction_ids)->get();
            
            if ($transactions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid transactions found'
                ], 404);
            }

            // Calculate amounts
            $subtotal = $transactions->sum('amount');
            $taxPercentage = $request->tax_percentage ?? 0;
            $discountPercentage = $request->discount_percentage ?? 0;
            
            $taxAmount = ($subtotal * $taxPercentage) / 100;
            $discountAmount = ($subtotal * $discountPercentage) / 100;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;

            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'branch_id' => $request->branch_id ?? $transactions->first()->branch_id,
                'student_id' => $request->student_id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'invoice_type' => $request->invoice_type ?? 'Fee Payment',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_percentage' => $taxPercentage,
                'discount_amount' => $discountAmount,
                'discount_percentage' => $discountPercentage,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'balance_amount' => $totalAmount,
                'status' => 'Draft',
                'payment_status' => 'Unpaid',
                'notes' => $request->notes,
                'terms_conditions' => $request->terms_conditions ?? 'Payment due within 30 days',
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'created_by' => auth()->id()
            ]);

            // Create invoice items from transactions
            foreach ($transactions as $transaction) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'transaction_id' => $transaction->id,
                    'item_type' => 'Service',
                    'description' => $transaction->description ?? $transaction->category->name,
                    'quantity' => 1,
                    'unit_price' => $transaction->amount,
                    'amount' => $transaction->amount
                ]);

                // Link transaction to invoice
                DB::table('invoice_transaction')->insert([
                    'invoice_id' => $invoice->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            // Manually load student data if needed
            if ($invoice->student_id) {
                $student = DB::table('students')->find($invoice->student_id);
                if ($student) {
                    $invoice->student = [
                        'id' => $student->id,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'admission_number' => $student->admission_number ?? null
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully from ' . count($transactions) . ' transaction(s)',
                'data' => $invoice->load(['items', 'transactions'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Generate invoice error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoice',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create manual invoice (without transactions)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            if (!in_array($user->role, ['SuperAdmin', 'BranchAdmin', 'Accountant'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'customer_name' => 'required|string',
                'invoice_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:invoice_date',
                'invoice_type' => 'required',
                'items' => 'required|array|min:1',
                'items.*.description' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Calculate amounts
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            $taxPercentage = $request->tax_percentage ?? 0;
            $discountPercentage = $request->discount_percentage ?? 0;
            
            $taxAmount = ($subtotal * $taxPercentage) / 100;
            $discountAmount = ($subtotal * $discountPercentage) / 100;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;

            $branchId = $user->role === 'BranchAdmin' ? $user->branch_id : $request->branch_id;

            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'branch_id' => $branchId,
                'student_id' => $request->student_id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'invoice_type' => $request->invoice_type,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_percentage' => $taxPercentage,
                'discount_amount' => $discountAmount,
                'discount_percentage' => $discountPercentage,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'balance_amount' => $totalAmount,
                'status' => 'Draft',
                'payment_status' => 'Unpaid',
                'notes' => $request->notes,
                'terms_conditions' => $request->terms_conditions ?? 'Payment due within 30 days',
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'created_by' => $user->id
            ]);

            // Create items
            foreach ($request->items as $itemData) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => $itemData['item_type'] ?? 'Service',
                    'description' => strip_tags($itemData['description']),
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'amount' => $itemData['quantity'] * $itemData['unit_price']
                ]);
            }

            DB::commit();

            // Manually load student data if needed
            if ($invoice->student_id) {
                $student = DB::table('students')->find($invoice->student_id);
                if ($student) {
                    $invoice->student = [
                        'id' => $student->id,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'admission_number' => $student->admission_number ?? null
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'data' => $invoice->load(['items', 'branch'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create invoice error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice'
            ], 500);
        }
    }

    /**
     * Get single invoice
     */
    public function show($id)
    {
        try {
            $invoice = Invoice::with(['branch', 'createdBy', 'items', 'transactions'])->findOrFail($id);
            
            // Manually load student data if student_id exists
            if ($invoice->student_id) {
                $student = DB::table('students')->find($invoice->student_id);
                if ($student) {
                    $invoice->student = [
                        'id' => $student->id,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'admission_number' => $student->admission_number ?? null,
                        'email' => $student->email ?? null,
                        'phone' => $student->phone ?? null
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);

        } catch (\Exception $e) {
            Log::error('Get invoice error', ['error' => $e->getMessage(), 'line' => $e->getLine()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update invoice
     */
    public function update(Request $request, $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);

            if ($invoice->status === 'Paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit paid invoice'
                ], 403);
            }

            DB::beginTransaction();

            $updateData = $request->only([
                'customer_name', 'customer_email', 'customer_phone', 'customer_address',
                'due_date', 'notes', 'terms_conditions', 'status'
            ]);

            // Update items if provided
            if ($request->has('items')) {
                $invoice->items()->delete();
                
                $subtotal = 0;
                foreach ($request->items as $itemData) {
                    $amount = $itemData['quantity'] * $itemData['unit_price'];
                    $subtotal += $amount;
                    
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => strip_tags($itemData['description']),
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'amount' => $amount
                    ]);
                }
                
                $taxAmount = ($subtotal * $invoice->tax_percentage) / 100;
                $discountAmount = ($subtotal * $invoice->discount_percentage) / 100;
                $totalAmount = $subtotal + $taxAmount - $discountAmount;
                
                $updateData['subtotal'] = $subtotal;
                $updateData['tax_amount'] = $taxAmount;
                $updateData['discount_amount'] = $discountAmount;
                $updateData['total_amount'] = $totalAmount;
                $updateData['balance_amount'] = $totalAmount - $invoice->paid_amount;
            }

            $invoice->update($updateData);

            DB::commit();

            $invoice = $invoice->fresh(['items', 'branch']);
            
            // Manually load student data if needed
            if ($invoice->student_id) {
                $student = DB::table('students')->find($invoice->student_id);
                if ($student) {
                    $invoice->student = [
                        'id' => $student->id,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'admission_number' => $student->admission_number ?? null
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully',
                'data' => $invoice
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice'
            ], 500);
        }
    }

    /**
     * Delete invoice
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();

            if (!in_array($user->role, ['SuperAdmin', 'BranchAdmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            $invoice = Invoice::findOrFail($id);
            
            if ($invoice->status === 'Paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete paid invoice'
                ], 403);
            }

            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete invoice'
            ], 500);
        }
    }

    /**
     * Record payment
     */
    public function recordPayment(Request $request, $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'required|string',
                'payment_date' => 'required|date',
                'payment_reference' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $newPaidAmount = $invoice->paid_amount + $request->amount;
            $newBalance = $invoice->total_amount - $newPaidAmount;

            $paymentStatus = 'Unpaid';
            $status = $invoice->status;
            
            if ($newPaidAmount >= $invoice->total_amount) {
                $paymentStatus = 'Paid';
                $status = 'Paid';
            } elseif ($newPaidAmount > 0) {
                $paymentStatus = 'Partial';
                $status = 'Partial';
            }

            $invoice->update([
                'paid_amount' => $newPaidAmount,
                'balance_amount' => $newBalance,
                'payment_status' => $paymentStatus,
                'status' => $status,
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'payment_date' => $request->payment_date
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $invoice->fresh(['items'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment'
            ], 500);
        }
    }

    /**
     * Send invoice
     */
    public function sendInvoice($id)
    {
        try {
            $invoice = Invoice::findOrFail($id);
            
            $invoice->update([
                'status' => 'Sent',
                'sent_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice marked as sent'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice'
            ], 500);
        }
    }

    /**
     * Get invoice statistics
     */
    public function getStats(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Invoice::query();

            if ($user->role === 'BranchAdmin') {
                $query->where('branch_id', $user->branch_id);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            $totalInvoices = $query->count();
            $totalAmount = $query->sum('total_amount');
            $paidAmount = $query->sum('paid_amount');
            $pendingAmount = $query->sum('balance_amount');
            
            $overdueInvoices = (clone $query)->overdue()->count();
            $draftInvoices = (clone $query)->where('status', 'Draft')->count();
            $paidInvoices = (clone $query)->where('payment_status', 'Paid')->count();
            $partialInvoices = (clone $query)->where('payment_status', 'Partial')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_invoices' => $totalInvoices,
                    'total_amount' => (float)$totalAmount,
                    'paid_amount' => (float)$paidAmount,
                    'pending_amount' => (float)$pendingAmount,
                    'overdue_invoices' => $overdueInvoices,
                    'draft_invoices' => $draftInvoices,
                    'paid_invoices' => $paidInvoices,
                    'partial_invoices' => $partialInvoices
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }
}

