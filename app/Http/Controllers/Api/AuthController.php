<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Generate API credentials for tenant
     */
    public function generateTenantCredentials(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'permissions' => 'required|array',
            'permissions.*' => 'string|in:read,write,delete,admin',
            'expires_at' => 'nullable|date|after:now',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'ip',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $apiKey = ApiKey::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'type' => 'tenant',
            'permissions' => $request->permissions,
            'expires_at' => $request->expires_at,
            'rate_limit' => $request->rate_limit ?? 1000,
            'ip_whitelist' => $request->ip_whitelist,
            'description' => $request->description,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'api_key' => $apiKey->key,
                'api_secret' => $apiKey->secret,
                'name' => $apiKey->name,
                'permissions' => $apiKey->permissions,
                'expires_at' => $apiKey->expires_at,
                'rate_limit' => $apiKey->rate_limit,
            ]
        ], 201);
    }

    /**
     * Generate API credentials for member
     */
    public function generateMemberCredentials(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'name' => 'required|string|max:255',
            'permissions' => 'required|array',
            'permissions.*' => 'string|in:read,write,own_data',
            'expires_at' => 'nullable|date|after:now',
            'rate_limit' => 'nullable|integer|min:1|max:1000',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $member = Member::find($request->member_id);
        
        // Verify member belongs to current tenant
        if ($member->tenant_id !== auth()->user()->tenant_id) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Member does not belong to your organization'
            ], 403);
        }

        $apiKey = ApiKey::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'type' => 'member',
            'permissions' => $request->permissions,
            'expires_at' => $request->expires_at,
            'rate_limit' => $request->rate_limit ?? 100,
            'description' => $request->description,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'api_key' => $apiKey->key,
                'api_secret' => $apiKey->secret,
                'member_id' => $member->id,
                'member_name' => $member->name,
                'permissions' => $apiKey->permissions,
                'expires_at' => $apiKey->expires_at,
                'rate_limit' => $apiKey->rate_limit,
            ]
        ], 201);
    }

    /**
     * List API keys for current tenant
     */
    public function listApiKeys(Request $request)
    {
        $query = ApiKey::where('tenant_id', auth()->user()->tenant_id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $apiKeys = $query->orderBy('created_at', 'desc')
                        ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $apiKeys->items(),
            'pagination' => [
                'current_page' => $apiKeys->currentPage(),
                'last_page' => $apiKeys->lastPage(),
                'per_page' => $apiKeys->perPage(),
                'total' => $apiKeys->total(),
            ]
        ]);
    }

    /**
     * Revoke API key
     */
    public function revokeApiKey(Request $request, $id)
    {
        $apiKey = ApiKey::where('tenant_id', auth()->user()->tenant_id)
                       ->findOrFail($id);

        $apiKey->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'API key revoked successfully'
        ]);
    }

    /**
     * Regenerate API key secret
     */
    public function regenerateSecret(Request $request, $id)
    {
        $apiKey = ApiKey::where('tenant_id', auth()->user()->tenant_id)
                       ->findOrFail($id);

        $apiKey->update([
            'secret' => \Illuminate\Support\Str::random(64)
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'api_key' => $apiKey->key,
                'api_secret' => $apiKey->secret,
            ]
        ]);
    }

    /**
     * Get API key details
     */
    public function getApiKeyDetails(Request $request, $id)
    {
        $apiKey = ApiKey::where('tenant_id', auth()->user()->tenant_id)
                       ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'type' => $apiKey->type,
                'permissions' => $apiKey->permissions,
                'is_active' => $apiKey->is_active,
                'last_used_at' => $apiKey->last_used_at,
                'expires_at' => $apiKey->expires_at,
                'rate_limit' => $apiKey->rate_limit,
                'ip_whitelist' => $apiKey->ip_whitelist,
                'description' => $apiKey->description,
                'created_at' => $apiKey->created_at,
            ]
        ]);
    }
}
