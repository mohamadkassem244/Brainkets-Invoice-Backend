<?php

namespace App\Http\Controllers;

use App\Models\AgAttachment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function index()
    {
        try {
            $invoices = Invoice::with('invoiceItems')->get();
            if ($invoices->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No invoices found.'
                ], 404);
            }
            $invoices->transform(function ($invoice) {
                $attachments = DB::table('ag_attachment')
                    ->where('table_name', 'in_sales_invoice')
                    ->where('row_id', $invoice->id)
                    ->get();
                $invoice->attachments = $attachments;
                return $invoice;
            });
            return response()->json([
                'success' => true,
                'data' => $invoices
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database query error occurred.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $invoice = Invoice::with('items')->find($id);
            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.'
                ], 404);
            }
            $attachments = DB::table('ag_attachment')
                ->where('table_name', 'in_sales_invoice')
                ->where('row_id', $id)
                ->get();
            $invoiceArray = $invoice->toArray();
            $invoiceArray['attachments'] = $attachments;
            return response()->json([
                'success' => true,
                'data' => $invoiceArray
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database query error occurred.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id'      => 'required|exists:customer,id',
            'currency_id'      => 'required|exists:currency,id',
            'reference'        => 'required|string|unique:in_sales_invoice,reference',
            'date'             => 'required|date_format:Y-m-d',
            'due_date'         => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'status'           => 'required|in:pending,paid,overdue,canceled',
            'is_recurring'     => 'required|boolean',
            'repeat_cycle'     => 'required|in:daily,weekly,monthly,yearly',
            'create_before_days' => 'required|integer|min:1',
            'tax_rate'         => 'required|numeric|min:0',
            'tax_method'       => 'required|in:inclusive,exclusive',
            'shipping'         => 'required|numeric|min:0',
            'discount'         => 'required|numeric|min:0',
            'note'             => 'nullable|string',
            'created_by'       => 'nullable|integer',
            'updated_by'       => 'nullable|integer',
            'items'            => 'required|array|min:1',
            'items.*.title'    => 'required|string|max:255',
            'items.*.description' => 'required|string',
            'items.*.cost'     => 'required|numeric|min:0',
            'items.*.price'    => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.tax_rate' => 'required|numeric|min:0',
            'items.*.tax_method' => 'required|in:inclusive,exclusive',
            'items.*.discount' => 'required|numeric|min:0',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            DB::beginTransaction();
            $validatedData = $validator->validated();
            $invoiceData = $validatedData;
            unset($invoiceData['items']);
            $invoice = Invoice::create($invoiceData);
            $invoice_total = 0;
            $invoice_grand_total = $invoiceData['shipping'];
            foreach ($validatedData['items'] as $itemData) {
                $item_total = $itemData['quantity'] * $itemData['cost'];
                if ($itemData['discount'] > 0) {
                    $item_total -= ($item_total * $itemData['discount']) / 100;
                }
                if ($itemData['tax_method'] === 'exclusive' && !empty($itemData['tax_rate'])) {
                    $item_total += ($item_total * $itemData['tax_rate']) / 100;
                }
                $invoice_total += $item_total;
                $invoice->invoiceItems()->create($itemData);
            }
            $invoice_grand_total += $invoice_total;
            if ($invoiceData['discount'] > 0) {
                $invoice_grand_total -= ($invoice_total * $invoiceData['discount']) / 100;
            }
            if ($invoiceData['tax_method'] === 'exclusive' && $invoiceData['tax_rate'] > 0) {
                $invoice_grand_total += ($invoice_total * $invoiceData['tax_rate']) / 100;
            }
            $invoice->update([
                'total' => $invoice_total,
                'grand_total' => $invoice_grand_total
            ]);
            if (isset($validatedData['attachments'])) {
                foreach ($validatedData['attachments'] as $file) {
                    $attachment = AgAttachment::create([
                        'table_name' => 'in_sales_invoice',
                        'row_id' => $invoice->id,
                        'type' => 1,
                        'file_path' => null,
                        'file_name' => $file->getClientOriginalName(),
                        'file_extension' => $file->getClientOriginalExtension(),
                        'file_size' => $file->getSize(),
                        'cdn_uploaded' => false
                    ]);
                    $path = $file->store('attachments/invoice', 'public');
                    $attachment->update([
                        'file_path' => $path,
                        'cdn_uploaded' => true
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully.',
                'data' => $invoice->load('invoiceItems')
            ], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating invoice.',
                'error'   => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the invoice.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.'
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'customer_id'      => 'required|exists:customer,id',
            'currency_id'      => 'required|exists:currency,id',
            'reference'        => "required|string|unique:in_sales_invoice,reference,{$id}",
            'date'             => 'required|date_format:Y-m-d',
            'due_date'         => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'status'           => 'required|in:pending,paid,overdue,canceled',
            'is_recurring'     => 'required|boolean',
            'repeat_cycle'     => 'required|in:daily,weekly,monthly,yearly',
            'create_before_days' => 'required|integer|min:1',
            'tax_rate'         => 'required|numeric|min:0',
            'tax_method'       => 'required|in:inclusive,exclusive',
            'shipping'         => 'required|numeric|min:0',
            'discount'         => 'required|numeric|min:0',
            'note'             => 'nullable|string',
            'created_by'       => 'nullable|integer',
            'updated_by'       => 'nullable|integer',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|exists:in_sales_invoice_item,id',
            'items.*.title'    => 'required|string|max:255',
            'items.*.description' => 'required|string',
            'items.*.cost'     => 'required|numeric|min:0',
            'items.*.price'    => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.tax_rate' => 'required|numeric|min:0',
            'items.*.tax_method' => 'required|in:inclusive,exclusive',
            'items.*.discount' => 'required|numeric|min:0',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            DB::beginTransaction();
            $validatedData = $validator->validated();
            $invoiceData = $validatedData;
            unset($invoiceData['items']);
            $invoice->update($invoiceData);
            $existingItemIds = $invoice->invoiceItems()->pluck('id')->toArray();
            $receivedItemIds = array_filter(array_column($validatedData['items'], 'id'));
            $itemsToDelete = array_diff($existingItemIds, $receivedItemIds);
            InvoiceItem::whereIn('id', $itemsToDelete)->delete();
            $invoice_total = 0;
            $invoice_grand_total = $invoiceData['shipping'];
            foreach ($validatedData['items'] as $itemData) {
                $item_total = $itemData['quantity'] * ($itemData['cost']);
                if ($itemData['discount'] > 0) {
                    $item_total -= ($item_total * $itemData['discount']) / 100;
                }
                if ($itemData['tax_method'] === 'exclusive' && $itemData['tax_rate'] > 0) {
                    $item_total += ($item_total * $itemData['tax_rate']) / 100;
                }
                $invoice_total += $item_total;
                if (!empty($itemData['id'])) {
                    InvoiceItem::where('id', $itemData['id'])->update($itemData);
                } else {
                    $invoice->invoiceItems()->create($itemData);
                }
            }
            $invoice_grand_total += $invoice_total;
            if ($invoiceData['discount'] > 0) {
                $invoice_grand_total -= ($invoice_total * $invoiceData['discount']) / 100;
            }
            if ($invoiceData['tax_method'] === 'exclusive' && $invoiceData['tax_rate'] > 0) {
                $invoice_grand_total += ($invoice_total * $invoiceData['tax_rate']) / 100;
            }
            $invoice->update([
                'total' => $invoice_total,
                'grand_total' => $invoice_grand_total
            ]);
            if (isset($validatedData['attachments'])) {
                foreach ($validatedData['attachments'] as $file) {
                    $attachment = AgAttachment::create([
                        'table_name' => 'in_sales_invoice',
                        'row_id' => $invoice->id,
                        'type' => 1,
                        'file_path' => null,
                        'file_name' => $file->getClientOriginalName(),
                        'file_extension' => $file->getClientOriginalExtension(),
                        'file_size' => $file->getSize(),
                        'cdn_uploaded' => false
                    ]);
                    $path = $file->store('attachments/invoice', 'public');
                    $attachment->update([
                        'file_path' => $path,
                        'cdn_uploaded' => true
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully.',
                'data' => $invoice->load('invoiceItems')
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating invoice.',
                'error'   => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the invoice.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $invoice = Invoice::find($id);
            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.'
                ], 404);
            }
            DB::beginTransaction();
            $deletedItems = $invoice->invoiceItems()->delete();
            if ($deletedItems === false) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete invoice items. Invoice deletion aborted.'
                ], 500);
            }
            $attachments = AgAttachment::where('table_name', 'in_sales_invoice')
                ->where('row_id', $id)
                ->get();
            if ($attachments->isNotEmpty()) {
                foreach ($attachments as $attachment) {
                    $filePath = $attachment->file_path;
                    if (Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                    $attachment->delete();
                }
            }
            if (!$invoice->delete()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete invoice.'
                ], 500);
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted successfully.'
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while deleting invoice.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function calculateTotalAmountBetweenTwoDates(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date_format:Y-m-d|before:end_date',
                'end_date'   => 'required|date_format:Y-m-d|after:start_date',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }
            $validatedData = $validator->validated();
            $startDate = Carbon::parse($validatedData['start_date'])->startOfDay();
            $endDate = Carbon::parse($validatedData['end_date'])->endOfDay();
            $totalAmount = (float) Invoice::whereBetween('date', [$startDate, $endDate])->sum('total');
            return response()->json([
                'success' => true,
                'message' => 'Total amount calculated successfully.',
                'data'    => compact('totalAmount')
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while calculating total amount.',
                'error'   => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getInvoicesCountsByStatus()
    {
        try {
            $totalInvoices = Invoice::count();
            $pendingCount = Invoice::where('status', 'pending')->count();
            $paidCount = Invoice::where('status', 'paid')->count();
            $overdueCount = Invoice::where('status', 'overdue')->count();
            $canceledCount = Invoice::where('status', 'canceled')->count();
            $pendingPercentage = $totalInvoices ? ($pendingCount / $totalInvoices) * 100 : 0;
            $paidPercentage = $totalInvoices ? ($paidCount / $totalInvoices) * 100 : 0;
            $overduePercentage = $totalInvoices ? ($overdueCount / $totalInvoices) * 100 : 0;
            $canceledPercentage = $totalInvoices ? ($canceledCount / $totalInvoices) * 100 : 0;
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $totalInvoices,
                    'pending_count' => $pendingCount,
                    'pending_percentage' => $pendingPercentage,
                    'paid_count' => $paidCount,
                    'paid_percentage' => $paidPercentage,
                    'overdue_count' => $overdueCount,
                    'overdue_percentage' => $overduePercentage,
                    'canceled_count' => $canceledCount,
                    'canceled_percentage' => $canceledPercentage,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
