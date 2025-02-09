<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        try {
            $accounts = Account::all();
            if ($accounts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No accounts found.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $accounts
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
            $account = Account::find($id);
            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $account
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
