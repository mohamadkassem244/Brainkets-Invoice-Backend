<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        try {
            $currencies = Currency::all();
            if ($currencies->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No currencies found.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $currencies
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
            $currency = Currency::find($id);
            if (!$currency) {
                return response()->json([
                    'success' => false,
                    'message' => 'Currency not found.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $currency
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
}
