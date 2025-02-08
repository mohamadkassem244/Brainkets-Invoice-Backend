<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function index()
    {
        try {
            $payments = Payment::all();
            if ($payments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payments found.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $payments
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
            $payment = Payment::find($id);
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $payment
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
                'customer_id' => 'required|exists:customer,id',
                'invoice_id' => 'required|exists:in_sales_invoice,id',
                'journal' => 'required|exists:account,id',
                'date' => 'required|date_format:Y-m-d',
                'payment_type' => 'required|in:send,receive',
                'payment_method' => 'required|in:cash,bank',
                'amount' => 'required|numeric|min:0',
                'note' => 'nullable|string',
            ]);
            DB::beginTransaction();
            $payment = Payment::create([
                'customer_id' => $validatedData['customer_id'],
                'invoice_id' => $validatedData['invoice_id'],
                'journal' => $validatedData['journal'],
                'date' => $validatedData['date'],
                'payment_type' => $validatedData['payment_type'],
                'payment_method' => $validatedData['payment_method'],
                'amount' => $validatedData['amount'],
                'note' => $validatedData['note'] ?? null,
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully.',
                'data' => $payment
            ], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating payment.',
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
            $payment = Payment::find($id);
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.'
                ], 404);
            }
            $validatedData = $request->validate([
                'customer_id' => 'required|exists:customer,id',
                'invoice_id' => 'required|exists:in_sales_invoice,id',
                'journal' => 'required|exists:account,id',
                'date' => 'required|date_format:Y-m-d',
                'payment_type' => 'required|in:send,receive',
                'payment_method' => 'required|in:cash,bank',
                'amount' => 'required|numeric|min:0',
                'note' => 'nullable|string',
            ]);

            DB::beginTransaction();
            $payment->update([
                'customer_id' => $validatedData['customer_id'],
                'invoice_id' => $validatedData['invoice_id'],
                'journal' => $validatedData['journal'],
                'date' => $validatedData['date'],
                'payment_type' => $validatedData['payment_type'],
                'payment_method' => $validatedData['payment_method'],
                'amount' => $validatedData['amount'],
                'note' => $validatedData['note'] ?? null,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully.',
                'data' => $payment
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating payment.',
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
            $payment = Payment::find($id);
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.'
                ], 404);
            }
            DB::beginTransaction();
            $payment->delete();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully.'
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while deleting payment.',
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

            $totalAmount = Payment::whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

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
}
