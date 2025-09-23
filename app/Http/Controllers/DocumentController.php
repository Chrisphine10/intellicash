<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $assets = ['datatable'];
        $category = $request->get('category', 'all');
        
        return view('backend.admin.document.index', compact('assets', 'category'));
    }

    /**
     * Get documents data for DataTable
     */
    public function getTableData(Request $request)
    {
        $tenant = app('tenant');
        
        $query = Document::where('tenant_id', $tenant->id);
        
        // Apply category filter
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }
        
        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }
        
        $documents = $query->with(['creator', 'updater'])
            ->orderBy('created_at', 'desc')
            ->get();

        return datatables($documents)
            ->editColumn('title', function ($document) {
                return '<a href="' . route('documents.show', $document->id) . '">' . $document->title . '</a>';
            })
            ->editColumn('category', function ($document) {
                $badgeClass = match($document->category) {
                    'terms_and_conditions' => 'primary',
                    'privacy_policy' => 'info',
                    'loan_agreement' => 'success',
                    'legal_document' => 'warning',
                    'policy' => 'secondary',
                    default => 'dark'
                };
                
                return '<span class="badge badge-' . $badgeClass . '">' . $document->category_label . '</span>';
            })
            ->editColumn('file_size', function ($document) {
                return $document->formatted_file_size;
            })
            ->editColumn('is_active', function ($document) {
                return $document->is_active ? 
                    '<span class="badge badge-success">Active</span>' : 
                    '<span class="badge badge-danger">Inactive</span>';
            })
            ->editColumn('is_public', function ($document) {
                return $document->is_public ? 
                    '<span class="badge badge-info">Public</span>' : 
                    '<span class="badge badge-secondary">Private</span>';
            })
            ->editColumn('created_at', function ($document) {
                return $document->created_at->format('M d, Y H:i');
            })
            ->addColumn('actions', function ($document) {
                $actions = '<div class="dropdown">';
                $actions .= '<button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">Actions</button>';
                $actions .= '<div class="dropdown-menu">';
                $actions .= '<a class="dropdown-item" href="' . route('documents.show', $document->id) . '">View</a>';
                $actions .= '<a class="dropdown-item" href="' . route('documents.download', $document->id) . '">Download</a>';
                $actions .= '<a class="dropdown-item" href="' . route('documents.edit', $document->id) . '">Edit</a>';
                $actions .= '<a class="dropdown-item text-danger" href="#" onclick="deleteDocument(' . $document->id . ')">Delete</a>';
                $actions .= '</div></div>';
                return $actions;
            })
            ->rawColumns(['title', 'category', 'is_active', 'is_public', 'actions'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('backend.admin.document.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|in:terms_and_conditions,privacy_policy,loan_agreement,legal_document,policy,other',
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB max
            'version' => 'required|string|max:50',
            'is_public' => 'boolean',
            'tags' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $tenant = app('tenant');
            $file = $request->file('file');
            
            // Generate unique filename
            $filename = Str::slug($request->title) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = 'documents/' . $tenant->id . '/' . $filename;
            
            // Store file
            Storage::putFileAs('public/documents/' . $tenant->id, $file, $filename);
            
            // Create document record
            $document = new Document();
            $document->tenant_id = $tenant->id;
            $document->title = $request->title;
            $document->description = $request->description;
            $document->file_path = $filePath;
            $document->file_name = $file->getClientOriginalName();
            $document->file_size = $file->getSize();
            $document->file_type = $file->getMimeType();
            $document->category = $request->category;
            $document->is_public = $request->boolean('is_public');
            $document->version = $request->version;
            $document->tags = $request->tags ? explode(',', $request->tags) : [];
            $document->created_by = auth()->id();
            $document->save();

            return redirect()->route('documents.index')
                ->with('success', 'Document uploaded successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to upload document: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $tenant = app('tenant');
        $document = Document::where('tenant_id', $tenant->id)->findOrFail($id);
        
        return view('backend.admin.document.show', compact('document'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $tenant = app('tenant');
        $document = Document::where('tenant_id', $tenant->id)->findOrFail($id);
        
        return view('backend.admin.document.edit', compact('document'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $tenant = app('tenant');
        $document = Document::where('tenant_id', $tenant->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|in:terms_and_conditions,privacy_policy,loan_agreement,legal_document,policy,other',
            'file' => 'nullable|file|mimes:pdf|max:10240',
            'version' => 'required|string|max:50',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'tags' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $document->title = $request->title;
            $document->description = $request->description;
            $document->category = $request->category;
            $document->is_active = $request->boolean('is_active');
            $document->is_public = $request->boolean('is_public');
            $document->version = $request->version;
            $document->tags = $request->tags ? explode(',', $request->tags) : [];
            $document->updated_by = auth()->id();

            // Handle file update
            if ($request->hasFile('file')) {
                // Delete old file
                $document->deleteFile();
                
                // Upload new file
                $file = $request->file('file');
                $filename = Str::slug($request->title) . '_' . time() . '.' . $file->getClientOriginalExtension();
                $filePath = 'documents/' . $tenant->id . '/' . $filename;
                
                Storage::putFileAs('public/documents/' . $tenant->id, $file, $filename);
                
                $document->file_path = $filePath;
                $document->file_name = $file->getClientOriginalName();
                $document->file_size = $file->getSize();
                $document->file_type = $file->getMimeType();
            }

            $document->save();

            return redirect()->route('documents.index')
                ->with('success', 'Document updated successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update document: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $tenant = app('tenant');
            $document = Document::where('tenant_id', $tenant->id)->findOrFail($id);
            
            // Delete file from storage
            $document->deleteFile();
            
            // Delete record
            $document->delete();

            return response()->json(['success' => true, 'message' => 'Document deleted successfully.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete document: ' . $e->getMessage()]);
        }
    }

    /**
     * Download the document file
     */
    public function download($id)
    {
        $tenant = app('tenant');
        $document = Document::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$document->fileExists()) {
            return back()->with('error', 'File not found.');
        }
        
        return Storage::download('public/' . $document->file_path, $document->file_name);
    }

    /**
     * View the document in browser
     */
    public function view($id)
    {
        $tenant = app('tenant');
        $document = Document::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$document->fileExists()) {
            return back()->with('error', 'File not found.');
        }
        
        $fileContent = $document->getFileContent();
        
        return response($fileContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->file_name . '"'
        ]);
    }

    /**
     * Get documents by category for public access
     */
    public function getByCategory($category)
    {
        $tenant = app('tenant');
        
        $documents = Document::where('tenant_id', $tenant->id)
            ->where('category', $category)
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('version', 'desc')
            ->get();
        
        return response()->json($documents);
    }

    /**
     * Get latest document for a category
     */
    public function getLatest($category)
    {
        $tenant = app('tenant');
        
        $document = Document::getLatestForCategory($tenant->id, $category);
        
        if (!$document) {
            return response()->json(['error' => 'No document found for this category.'], 404);
        }
        
        return response()->json($document);
    }

    /**
     * Get document statistics
     */
    public function getStats()
    {
        $tenant = app('tenant');
        
        $stats = [
            'total' => Document::where('tenant_id', $tenant->id)->count(),
            'active' => Document::where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'public' => Document::where('tenant_id', $tenant->id)->where('is_public', true)->count(),
            'terms' => Document::where('tenant_id', $tenant->id)->where('category', 'terms_and_conditions')->count(),
        ];
        
        return response()->json($stats);
    }
}
