<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 5); // Default 5 items per page
            
            $branches = Branch::where('business_id', Auth::user()->business_id)
                ->paginate($perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => $branches->items(),
                'pagination' => [
                    'current_page' => $branches->currentPage(),
                    'last_page' => $branches->lastPage(),
                    'per_page' => $branches->perPage(),
                    'total' => $branches->total(),
                    'from' => $branches->firstItem(),
                    'to' => $branches->lastItem(),
                ]
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Branch listing failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branches'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created branch.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'parent_id' => 'nullable|exists:branches,id',
            ]);
            $validatedData['business_id'] = Auth::user()->business_id;

            // Verify the user has permission to add branches to this business
            if (Auth::user()->business_id != $validatedData['business_id'] ) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized to create branches for this business'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // If parent_id is provided, verify it belongs to the same business
            if (isset($validatedData['parent_id'])) {
                $parentBranch = Branch::find($validatedData['parent_id']);
                if ($parentBranch->business_id != $validatedData['business_id']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Parent branch must belong to the same business'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
            
            $branch = Branch::create($validatedData);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Branch created successfully',
                'data' => $branch
            ], Response::HTTP_CREATED);
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            Log::error('Branch creation failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create branch'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified branch with its sub-branches.
     *
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Branch $branch)
    {
        try {
            // Load children relationships
            $branch->load('children');
            
            return response()->json([
                'status' => 'success',
                'data' => $branch
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Branch retrieval failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branch'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show the branch hierarchy.
     *
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function hierarchy(Branch $branch)
    {
        try {
            // Recursively load all children and their children
            $branch->load('children.children.children');
            
            return response()->json([
                'status' => 'success',
                'data' => $branch
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Branch hierarchy retrieval failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branch hierarchy'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified branch.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Branch $branch)
    {
        try {
            // Verify user has permission to update this branch
            if (Auth::user()->business_id != $branch->business_id && Auth::user()->role !== 'admin') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized to update this branch'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'address' => 'sometimes|string|max:255',
                'parent_id' => 'nullable|exists:branches,id',
            ]);
            
            // If parent_id is provided, verify it belongs to the same business
            // and prevent circular references
            if (isset($validatedData['parent_id'])) {
                if ($validatedData['parent_id'] == $branch->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Branch cannot be its own parent'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                
                $parentBranch = Branch::find($validatedData['parent_id']);
                if ($parentBranch->business_id != $branch->business_id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Parent branch must belong to the same business'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                
                // Check if the new parent is actually a descendant of this branch
                // to prevent circular references
                $descendantIds = $this->getAllDescendantIds($branch->id);
                if (in_array($validatedData['parent_id'], $descendantIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot set a descendant branch as parent'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
            
            $branch->update($validatedData);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Branch updated successfully',
                'data' => $branch
            ], Response::HTTP_OK);
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            Log::error('Branch update failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update branch'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified branch.
     *
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Branch $branch)
    {
        try {
            // Verify user has permission to delete this branch
            if (Auth::user()->business_id != $branch->business_id && Auth::user()->role !== 'admin') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized to delete this branch'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Check if branch has child branches
            if ($branch->children()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete branch with sub-branches. Delete sub-branches first or reassign them.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            // Check if branch has staff or users assigned
            if ($branch->users()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete branch with assigned users or staff. Reassign them first.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            $branch->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Branch deleted successfully'
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Branch deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete branch'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Move sub-branches from one branch to another.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function moveSubBranches(Request $request, Branch $branch)
    {
        try {
            $validatedData = $request->validate([
                'target_branch_id' => 'required|exists:branches,id',
                'branch_ids' => 'required|array',
                'branch_ids.*' => 'required|exists:branches,id',
            ]);
            
            
            $targetBranch = Branch::find($validatedData['target_branch_id']);
            
            // Check if target branch is in the same business
            if ($targetBranch->business_id != $branch->business_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Target branch must be in the same business'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            // Check if all branch IDs are children of the current branch
            $childIds = $branch->children()->pluck('id')->toArray();
            foreach ($validatedData['branch_ids'] as $branchId) {
                if (!in_array($branchId, $childIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'One or more branches are not sub-branches of the current branch'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
            
            // Move the branches
            Branch::whereIn('id', $validatedData['branch_ids'])
                ->update(['parent_id' => $validatedData['target_branch_id']]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Sub-branches moved successfully'
            ], Response::HTTP_OK);
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            Log::error('Moving sub-branches failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to move sub-branches'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Get all descendant IDs of a branch.
     *
     * @param  int  $branchId
     * @return array
     */
    private function getAllDescendantIds($branchId)
    {
        $ids = [];
        $childIds = Branch::where('parent_id', $branchId)->pluck('id')->toArray();
        
        if (!empty($childIds)) {
            $ids = array_merge($ids, $childIds);
            
            foreach ($childIds as $childId) {
                $descendantIds = $this->getAllDescendantIds($childId);
                $ids = array_merge($ids, $descendantIds);
            }
        }
        
        return $ids;
    }
} 