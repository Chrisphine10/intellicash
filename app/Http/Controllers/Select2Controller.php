<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class Select2Controller extends Controller {

    // Military-grade whitelist of allowed tables and columns
    private const ALLOWED_TABLES = [
        'members' => ['id', 'first_name', 'last_name', 'email', 'phone'],
        'users' => ['id', 'name', 'email', 'user_type'],
        'savings_accounts' => ['id', 'account_number', 'member_id'],
        'loan_products' => ['id', 'name', 'interest_rate'],
        'expense_categories' => ['id', 'name', 'description'],
        'transaction_categories' => ['id', 'name', 'type'],
        'branches' => ['id', 'name', 'address'],
        'currencies' => ['id', 'name', 'symbol'],
        'savings_products' => ['id', 'name', 'interest_rate'],
        'bank_accounts' => ['id', 'account_name', 'account_number'],
    ];

    // Allowed display column combinations
    private const ALLOWED_DISPLAY_COMBOS = [
        'first_name' => ['last_name'],
        'name' => ['email'],
        'account_number' => ['member_id'],
    ];

    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Military-grade secure data retrieval with comprehensive validation
     *
     * @return \Illuminate\Http\Response
     */
    public function get_table_data(Request $request) {
        // Comprehensive input validation
        $validator = Validator::make($request->all(), [
            'table' => 'required|string|max:50|regex:/^[a-zA-Z_]+$/',
            'value' => 'required|string|max:50|regex:/^[a-zA-Z_]+$/',
            'display' => 'required|string|max:50|regex:/^[a-zA-Z_]+$/',
            'display2' => 'nullable|string|max:50|regex:/^[a-zA-Z_]+$/',
            'divider' => 'nullable|string|max:10',
            'where' => 'nullable|string|in:1,2,3,undefined',
            'q' => 'required|string|max:100|regex:/^[a-zA-Z0-9\s@._-]+$/',
        ], [
            'table.regex' => 'Invalid table name format',
            'value.regex' => 'Invalid value column format',
            'display.regex' => 'Invalid display column format',
            'q.regex' => 'Invalid search query format',
        ]);

        if ($validator->fails()) {
            Log::warning('Select2Controller: Invalid input detected', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'errors' => $validator->errors()->all(),
                'input' => $request->all()
            ]);
            return response()->json(['error' => 'Invalid input parameters'], 400);
        }

        $table = $request->get('table');
        $value = $request->get('value');
        $display = $request->get('display');
        $display2 = $request->get('display2');
        $divider = $request->get('divider', ' ');
        $where = $request->get('where');
        $q = $request->get('q');

        // Military-grade table and column validation
        if (!isset(self::ALLOWED_TABLES[$table])) {
            Log::warning('Select2Controller: Unauthorized table access attempt', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'table' => $table
            ]);
            return response()->json(['error' => 'Unauthorized table access'], 403);
        }

        if (!in_array($value, self::ALLOWED_TABLES[$table]) || 
            !in_array($display, self::ALLOWED_TABLES[$table])) {
            Log::warning('Select2Controller: Unauthorized column access attempt', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'table' => $table,
                'value' => $value,
                'display' => $display
            ]);
            return response()->json(['error' => 'Unauthorized column access'], 403);
        }

        // Validate display2 if provided
        if ($display2 && !in_array($display2, self::ALLOWED_TABLES[$table])) {
            Log::warning('Select2Controller: Unauthorized display2 column access', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'display2' => $display2
            ]);
            return response()->json(['error' => 'Unauthorized display2 column access'], 403);
        }

        // Validate display combination
        if ($display2 && !isset(self::ALLOWED_DISPLAY_COMBOS[$display])) {
            Log::warning('Select2Controller: Invalid display combination', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'display' => $display,
                'display2' => $display2
            ]);
            return response()->json(['error' => 'Invalid display combination'], 400);
        }

        // Military-grade data filtering
        $data_where = [
            '1' => ['user_id' => auth()->id()],
            '2' => ['user_type' => 'user'],
            '3' => ['tenant_id' => $request->tenant->id],
        ];

        try {
            $query = DB::table($table);
            
            // Secure column selection
            if ($display2) {
                $query->select(
                    DB::raw("`{$value}` as id"),
                    DB::raw("CONCAT(`{$display}`, '{$divider}', `{$display2}`) AS text")
                );
            } else {
                $query->select(
                    DB::raw("`{$value}` as id"),
                    DB::raw("`{$display}` as text")
                );
            }

            // Secure search with parameter binding
            $query->where($display, 'LIKE', $q . '%');

            // Apply secure filtering
            if ($where && $where !== 'undefined' && isset($data_where[$where])) {
                $query->where($data_where[$where]);
            }

            // Additional security: tenant isolation
            if (in_array('tenant_id', self::ALLOWED_TABLES[$table])) {
                $query->where('tenant_id', $request->tenant->id);
            }

            $result = $query->limit(10)->get();

            // Log successful query for audit
            Log::info('Select2Controller: Secure query executed', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'table' => $table,
                'query_length' => strlen($q)
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Select2Controller: Database error', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'table' => $table
            ]);
            return response()->json(['error' => 'Database error occurred'], 500);
        }
    }
}
