<?php

namespace App\Http\Controllers;

use App\Models\AgAttachment;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
            $payments->transform(function ($payment) {
                $attachments = DB::table('ag_attachment')
                    ->where('table_name', 'in_payment')
                    ->where('row_id', $payment->id)
                    ->get();
                $payment->attachments = $attachments;
                return $payment;
            });
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
            $attachments = DB::table('ag_attachment')
            ->where('table_name', 'in_payment')
            ->where('row_id', $id)
            ->get();
            $paymentArray = $payment->toArray();
            $paymentArray['attachments'] = $attachments;
            return response()->json([
                'success' => true,
                'data' => $paymentArray
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
            'customer_id'    => 'required|exists:customer,id',
            'invoice_id'     => 'required|exists:in_sales_invoice,id',
            'journal'        => 'required|exists:account,id',
            'date'           => 'required|date_format:Y-m-d',
            'payment_type'   => 'required|in:send,receive',
            'payment_method' => 'required|in:cash,bank',
            'amount'         => 'required|numeric|min:0',
            'note'           => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }
        try {
            DB::beginTransaction();
            $validatedData = $validator->validated();
            $payment = Payment::create($validatedData);
            if (isset($validatedData['attachments'])) {
                foreach ($validatedData['attachments'] as $file) {
                    $attachment = AgAttachment::create([
                        'table_name' => 'in_payment',
                        'row_id' => $payment->id,
                        'type' => 1,
                        'file_path' => null,
                        'file_name' => $file->getClientOriginalName(),
                        'file_extension' => $file->getClientOriginalExtension(),
                        'file_size' => $file->getSize(),
                        'cdn_uploaded' => false
                    ]);
                    $path = $file->store('attachments/payment', 'public');
                    $attachment->update([
                        'file_path' => $path,
                        'cdn_uploaded' => true
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully.',
                'data'    => $payment
            ], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating payment.',
                'error'   => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.'
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'customer_id'    => 'required|exists:customer,id',
            'invoice_id'     => 'required|exists:in_sales_invoice,id',
            'journal'        => 'required|exists:account,id',
            'date'           => 'required|date_format:Y-m-d',
            'payment_type'   => 'required|in:send,receive',
            'payment_method' => 'required|in:cash,bank',
            'amount'         => 'required|numeric|min:0',
            'note'           => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }
        try {
            DB::beginTransaction();
            $validatedData = $validator->validated();
            $payment->update($validatedData);
            if (isset($validatedData['attachments'])) {
                foreach ($validatedData['attachments'] as $file) {
                    $attachment = AgAttachment::create([
                        'table_name' => 'in_payment',
                        'row_id' => $payment->id,
                        'type' => 1,
                        'file_path' => null,
                        'file_name' => $file->getClientOriginalName(),
                        'file_extension' => $file->getClientOriginalExtension(),
                        'file_size' => $file->getSize(),
                        'cdn_uploaded' => false
                    ]);
                    $path = $file->store('attachments/payment', 'public');
                    $attachment->update([
                        'file_path' => $path,
                        'cdn_uploaded' => true
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully.',
                'data'    => $payment
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating payment.',
                'error'   => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error'   => $e->getMessage()
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
            if (!$payment->delete()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete payment.'
                ], 500);
            }
            $attachments = AgAttachment::where('table_name', 'in_payment')
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
            $totalAmount = (float) Payment::whereBetween('date', [$startDate, $endDate])->sum('amount');
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
}
