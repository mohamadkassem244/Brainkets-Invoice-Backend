<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function index()
    {
        try {
            $invoices = Invoice::all();
            if ($invoices->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No invoices found.'
                ], 404);
            }
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
            $invoice = Invoice::find($id);
            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $invoice
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
        try {
            $validatedData = $request->validate([
                'customer_id'      => 'required|exists:customer,id',
                'currency_id'      => 'required|exists:currency,id',
                'reference'        => 'required|string|unique:in_sales_invoice,reference',
                'date'             => 'required|date_format:Y-m-d',
                'due_date'         => 'nullable|date_format:Y-m-d|after_or_equal:date',
                'status'           => 'nullable|in:pending,paid,overdue,canceled',
                'is_recurring'     => 'nullable|boolean',
                'repeat_cycle'     => 'nullable|in:daily,weekly,monthly,yearly',
                'create_before_days' => 'nullable|integer|min:1',
                'tax_rate'         => 'nullable|numeric|min:0',
                'tax_method'       => 'nullable|in:inclusive,exclusive',
                'shipping'         => 'nullable|numeric|min:0',
                'discount'         => 'nullable|numeric|min:0',
                'note'             => 'nullable|string',
                'created_by'       => 'nullable|integer',
                'updated_by'       => 'nullable|integer',
                'items'            => 'required|array|min:1',
                'items.*.title'    => 'required|string|max:255',
                'items.*.description' => 'nullable|string',
                'items.*.cost'     => 'nullable|numeric|min:0',
                'items.*.price'    => 'nullable|numeric|min:0',
                'items.*.quantity' => 'nullable|integer|min:1',
                'items.*.tax_rate' => 'nullable|numeric|min:0',
                'items.*.tax_method' => 'nullable|in:inclusive,exclusive',
                'items.*.discount' => 'nullable|numeric|min:0',
            ]);
            DB::beginTransaction();
            $invoiceData = $validatedData;
            unset($invoiceData['items']);
            $invoice = Invoice::create($invoiceData);
            $invoice_tax_rate = $invoiceData['tax_rate'] ?? 0;
            $invoice_tax_method = $invoiceData['tax_method'] ?? 'inclusive';
            $invoice_shipping = $invoiceData['shipping'] ?? 0;
            $invoice_discount = $invoiceData['discount'] ?? 0;
            $invoice_total = 0;
            $invoiceItems = [];
            foreach ($validatedData['items'] as $itemData) {
                $item_title = $itemData['title'];
                $item_description = $itemData['description'] ?? null;
                $item_cost = $itemData['cost'] ?? 0;
                $item_price = $itemData['price'] ?? 0;
                $item_quantity = $itemData['quantity'] ?? 1;
                $item_tax_rate = $itemData['tax_rate'] ?? 0;
                $item_tax_method = $itemData['tax_method'] ?? 'inclusive';
                $item_discount = $itemData['discount'] ?? 0;
                $item_total = $item_quantity * $item_cost;
                if ($item_discount > 0) {
                    $item_total -= ($item_total * $item_discount) / 100;
                }
                if ($item_tax_method === 'exclusive' && $item_tax_rate > 0) {
                    $item_total += ($item_total * $item_tax_rate) / 100;
                }
                $invoice_total += $item_total;
                $invoiceItems[] = new InvoiceItem([
                    'title'       => $item_title,
                    'description' => $item_description,
                    'cost'        => $item_cost,
                    'price'       => $item_price,
                    'quantity'    => $item_quantity,
                    'tax_rate'    => $item_tax_rate,
                    'tax_method'  => $item_tax_method,
                    'discount'    => $item_discount,
                ]);
                $invoice->invoiceItems()->saveMany($invoiceItems);
            }
            if ($invoice_discount > 0) {
                $invoice_total -= ($invoice_total * $invoice_discount) / 100;
            }
            if ($invoice_tax_method === 'exclusive' && $invoice_tax_rate > 0) {
                $invoice_total += ($invoice_total * $invoice_tax_rate) / 100;
            }
            $invoice->update([
                'total' => $invoice_total,
                'grand_total' => $invoice_total + $invoice_shipping
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully.',
                'data' => $invoice
            ], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating invoice.',
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

    public function update(Request $request, $id)
    {
        try {
            $invoice = Invoice::find($id);
            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.'
                ], 404);
            }
            $validatedData = $request->validate([
                'customer_id'      => 'required|exists:customer,id',
                'currency_id'      => 'required|exists:currency,id',
                'reference'        => "required|string|unique:in_sales_invoice,reference,{$id}",
                'date'             => 'required|date_format:Y-m-d',
                'due_date'         => 'nullable|date_format:Y-m-d|after_or_equal:date',
                'status'           => 'nullable|in:pending,paid,overdue,canceled',
                'is_recurring'     => 'nullable|boolean',
                'repeat_cycle'     => 'nullable|in:daily,weekly,monthly,yearly',
                'create_before_days' => 'nullable|integer|min:1',
                'tax_rate'         => 'nullable|numeric|min:0',
                'tax_method'       => 'nullable|in:inclusive,exclusive',
                'shipping'         => 'nullable|numeric|min:0',
                'discount'         => 'nullable|numeric|min:0',
                'note'             => 'nullable|string',
                'updated_by'       => 'nullable|integer',
                'items'            => 'required|array|min:1',
                'items.*.id'       => 'nullable|exists:in_sales_invoice_item,id',
                'items.*.title'    => 'required|string|max:255',
                'items.*.description' => 'nullable|string',
                'items.*.cost'     => 'nullable|numeric|min:0',
                'items.*.price'    => 'nullable|numeric|min:0',
                'items.*.quantity' => 'nullable|integer|min:1',
                'items.*.tax_rate' => 'nullable|numeric|min:0',
                'items.*.tax_method' => 'nullable|in:inclusive,exclusive',
                'items.*.discount' => 'nullable|numeric|min:0',
            ]);
            DB::beginTransaction();
            $invoiceData = $validatedData;
            unset($invoiceData['items']);
            $invoice->update($invoiceData);
            $existingItemIds = $invoice->invoiceItems()->pluck('id')->toArray();
            $receivedItemIds = array_filter(array_column($validatedData['items'], 'id'));
            $itemsToDelete = array_diff($existingItemIds, $receivedItemIds);
            InvoiceItem::whereIn('id', $itemsToDelete)->delete();
            $invoice_total = 0;
            $invoice_tax_rate = $invoiceData['tax_rate'] ?? 0;
            $invoice_tax_method = $invoiceData['tax_method'] ?? 'inclusive';
            $invoice_shipping = $invoiceData['shipping'] ?? 0;
            $invoice_discount = $invoiceData['discount'] ?? 0;
            foreach ($validatedData['items'] as $itemData) {
                $item_total = $itemData['quantity'] * $itemData['cost'];
                if ($itemData['discount'] > 0) {
                    $item_total -= ($item_total * $itemData['discount']) / 100;
                }
                if ($itemData['tax_method'] === 'exclusive' && $itemData['tax_rate'] > 0) {
                    $item_total += ($item_total * $itemData['tax_rate']) / 100;
                }
                $invoice_total += $item_total;
                if (isset($itemData['id'])) {
                    InvoiceItem::where('id', $itemData['id'])->update($itemData);
                } else {
                    $invoice->invoiceItems()->create($itemData);
                }
            }
            if ($invoice_discount > 0) {
                $invoice_total -= ($invoice_total * $invoice_discount) / 100;
            }
            if ($invoice_tax_method === 'exclusive' && $invoice_tax_rate > 0) {
                $invoice_total += ($invoice_total * $invoice_tax_rate) / 100;
            }
            $invoice->update([
                'total' => $invoice_total,
                'grand_total' => $invoice_total + $invoice_shipping
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully.',
                'data' => $invoice
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating invoice.',
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

    public function calculateTotalAmount(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'start_date' => 'required|date|before_or_equal:end_date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $startDate = Carbon::parse($validatedData['start_date'])->startOfDay();
            $endDate = Carbon::parse($validatedData['end_date'])->endOfDay();

            $totalAmount = Invoice::whereBetween('date', [$startDate, $endDate])
                ->sum('total');

            return response()->json([
                'success' => true,
                'message' => 'Total amount calculated successfully.',
                'data' => [
                    'total_amount' => $totalAmount
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while calculating the total amount.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCounts()
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
