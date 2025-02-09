<?php

namespace App\Http\Controllers;

use App\Models\AgAttachment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class AgAttachmentController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240',
            'table_name' => 'required|string',
            'row_id' => 'required|integer'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            DB::beginTransaction();
            $file = $request->file('file');
            $attachment = AgAttachment::create([
                'table_name' => $request->table_name,
                'row_id' => $request->row_id,
                'type' => 1,
                'file_path' => null,
                'file_name' => $file->getClientOriginalName(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'cdn_uploaded' => false
            ]);
            $customPath = 'attachments/' . $request->input('table_name');
            $path = $file->store($customPath, 'public');
            $attachment->update([
                'file_path' => $path,
                'cdn_uploaded' => true
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $attachment
            ], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating attachment.',
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
            $attachment = AgAttachment::find($id);
            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment not found'
                ], 404);
            }
            DB::beginTransaction();
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the attachment.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
