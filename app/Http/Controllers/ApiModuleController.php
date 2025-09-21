<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiModuleController extends Controller
{
    /**
     * Display API module management page
     */
    public function index()
    {
        $tenant = app('tenant');
        
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $apiKeys = ApiKey::where('tenant_id', $tenant->id)
                        ->with('createdBy:id,name')
                        ->orderBy('created_at', 'desc')
                        ->paginate(20);

        $stats = [
            'total_keys' => $apiKeys->total(),
            'active_keys' => ApiKey::where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'tenant_keys' => ApiKey::where('tenant_id', $tenant->id)->where('type', 'tenant')->count(),
            'member_keys' => ApiKey::where('tenant_id', $tenant->id)->where('type', 'member')->count(),
        ];

        return view('backend.admin.api.index', compact('apiKeys', 'stats'));
    }

    /**
     * Show API key creation form
     */
    public function create(Request $request)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $type = $request->get('type', 'tenant');
        
        if ($type === 'member') {
            $members = \App\Models\Member::where('tenant_id', app('tenant')->id)
                                       ->where('status', 1)
                                       ->orderBy('first_name')
                                       ->get();
        } else {
            $members = collect();
        }

        return view('backend.admin.api.create', compact('type', 'members'));
    }

    /**
     * Store new API key
     */
    public function store(Request $request)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:tenant,member',
            'permissions' => 'required|array',
            'permissions.*' => 'string|in:read,write,delete,admin,own_data',
            'expires_at' => 'nullable|date|after:now',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'ip_whitelist' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
            'member_id' => 'required_if:type,member|exists:members,id',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $tenant = app('tenant');

        // Validate member belongs to tenant
        if ($request->type === 'member') {
            $member = \App\Models\Member::find($request->member_id);
            if ($member->tenant_id !== $tenant->id) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => ['Member does not belong to your organization']]);
                } else {
                    return back()->withErrors(['member_id' => 'Member does not belong to your organization'])->withInput();
                }
            }
        }

        // Parse IP whitelist
        $ipWhitelist = null;
        if ($request->ip_whitelist) {
            $ipWhitelist = array_filter(array_map('trim', explode(',', $request->ip_whitelist)));
        }

        $apiKey = ApiKey::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'type' => $request->type,
            'permissions' => $request->permissions,
            'expires_at' => $request->expires_at,
            'rate_limit' => $request->rate_limit ?? ($request->type === 'member' ? 100 : 1000),
            'ip_whitelist' => $ipWhitelist,
            'description' => $request->description,
            'created_by' => auth()->id(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('API key created successfully'),
                'data' => [
                    'id' => $apiKey->id,
                    'name' => $apiKey->name,
                    'key' => $apiKey->key,
                    'secret' => $apiKey->secret,
                    'type' => $apiKey->type,
                    'permissions' => $apiKey->permissions,
                    'expires_at' => $apiKey->expires_at,
                    'rate_limit' => $apiKey->rate_limit,
                ]
            ]);
        }

        return redirect()->route('api.index')->with('success', _lang('API key created successfully'));
    }

    /**
     * Show API key details
     */
    public function show($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $apiKey = ApiKey::where('tenant_id', app('tenant')->id)
                       ->with('createdBy:id,name')
                       ->findOrFail($id);

        return view('backend.admin.api.show', compact('apiKey'));
    }

    /**
     * Edit API key
     */
    public function edit($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $apiKey = ApiKey::where('tenant_id', app('tenant')->id)->findOrFail($id);

        return view('backend.admin.api.edit', compact('apiKey'));
    }

    /**
     * Update API key
     */
    public function update(Request $request, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $apiKey = ApiKey::where('tenant_id', app('tenant')->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'permissions' => 'required|array',
            'permissions.*' => 'string|in:read,write,delete,admin,own_data',
            'expires_at' => 'nullable|date|after:now',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'ip_whitelist' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        // Parse IP whitelist
        $ipWhitelist = null;
        if ($request->ip_whitelist) {
            $ipWhitelist = array_filter(array_map('trim', explode(',', $request->ip_whitelist)));
        }

        $apiKey->update([
            'name' => $request->name,
            'permissions' => $request->permissions,
            'expires_at' => $request->expires_at,
            'rate_limit' => $request->rate_limit,
            'ip_whitelist' => $ipWhitelist,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('API key updated successfully'),
                'data' => $apiKey
            ]);
        }

        return redirect()->route('api.index')->with('success', _lang('API key updated successfully'));
    }

    /**
     * Revoke API key
     */
    public function revoke($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $apiKey = ApiKey::where('tenant_id', app('tenant')->id)->findOrFail($id);
        $apiKey->update(['is_active' => false]);

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('API key revoked successfully')
            ]);
        }

        return back()->with('success', _lang('API key revoked successfully'));
    }

    /**
     * Regenerate API key secret
     */
    public function regenerateSecret($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $apiKey = ApiKey::where('tenant_id', app('tenant')->id)->findOrFail($id);
        $apiKey->update(['secret' => \Illuminate\Support\Str::random(64)]);

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('API secret regenerated successfully'),
                'data' => [
                    'secret' => $apiKey->secret
                ]
            ]);
        }

        return back()->with('success', _lang('API secret regenerated successfully'));
    }

    /**
     * Delete API key
     */
    public function destroy($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $apiKey = ApiKey::where('tenant_id', app('tenant')->id)->findOrFail($id);
        $apiKey->delete();

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('API key deleted successfully')
            ]);
        }

        return back()->with('success', _lang('API key deleted successfully'));
    }

    /**
     * Show API documentation
     */
    public function documentation()
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        return view('backend.admin.api.documentation');
    }

    /**
     * Test API endpoint
     */
    public function testEndpoint(Request $request)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
            'method' => 'required|in:GET,POST,PUT,DELETE',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
            'headers' => 'nullable|array',
            'body' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => 'error', 'message' => $validator->errors()->all()], 422);
        }

        try {
            $client = new \GuzzleHttp\Client();
            
            $options = [
                'headers' => [
                    'X-API-Key' => $request->api_key,
                    'X-API-Secret' => $request->api_secret,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ];

            if ($request->headers) {
                $options['headers'] = array_merge($options['headers'], $request->headers);
            }

            if ($request->body) {
                $options['json'] = json_decode($request->body, true);
            }

            $response = $client->request($request->method, $request->endpoint, $options);

            return response()->json([
                'result' => 'success',
                'data' => [
                    'status_code' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'body' => $response->getBody()->getContents(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'result' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
