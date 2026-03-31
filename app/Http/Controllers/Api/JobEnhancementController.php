<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\JobAttachment;
use App\Models\JobChecklistEntry;
use App\Models\JobComment;
use App\Models\ServiceJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JobEnhancementController extends Controller
{
    // ── Attachments ─────────────────────────────────────────────

    public function attachments(ServiceJob $serviceJob): JsonResponse
    {
        $attachments = $serviceJob->attachments()
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['attachments' => $attachments]);
    }

    public function uploadAttachment(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'category' => ['sometimes', 'in:before,after,document,other'],
        ]);

        $file = $request->file('file');
        $path = $file->store("job-attachments/{$serviceJob->id}", 'public');

        $attachment = JobAttachment::create([
            'service_job_id' => $serviceJob->id,
            'uploaded_by' => $request->user()->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $request->input('category', 'other'),
        ]);

        $attachment->load('uploader:id,name');

        return response()->json([
            'message' => 'File uploaded successfully.',
            'attachment' => $attachment,
        ], 201);
    }

    public function deleteAttachment(ServiceJob $serviceJob, JobAttachment $attachment): JsonResponse
    {
        if ($attachment->service_job_id !== $serviceJob->id) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    // ── Checklist ───────────────────────────────────────────────

    public function checklist(ServiceJob $serviceJob): JsonResponse
    {
        $serviceJob->loadMissing('service');

        $items = ChecklistItem::where('service_id', $serviceJob->service_id)
            ->orderBy('sort_order')
            ->get();

        $entries = $serviceJob->checklistEntries()
            ->with('completedByUser:id,name')
            ->get()
            ->keyBy('checklist_item_id');

        $checklist = $items->map(fn (ChecklistItem $item) => [
            'id' => $item->id,
            'label' => $item->label,
            'is_required' => $item->is_required,
            'is_completed' => $entries->has($item->id) && $entries[$item->id]->is_completed,
            'completed_by' => $entries->has($item->id) ? $entries[$item->id]->completedByUser : null,
            'completed_at' => $entries->has($item->id) ? $entries[$item->id]->completed_at : null,
        ]);

        return response()->json(['checklist' => $checklist]);
    }

    public function toggleChecklistItem(Request $request, ServiceJob $serviceJob, ChecklistItem $checklistItem): JsonResponse
    {
        $entry = JobChecklistEntry::firstOrNew([
            'service_job_id' => $serviceJob->id,
            'checklist_item_id' => $checklistItem->id,
        ]);

        $entry->is_completed = ! $entry->is_completed;
        $entry->completed_by = $entry->is_completed ? $request->user()->id : null;
        $entry->completed_at = $entry->is_completed ? now() : null;
        $entry->save();

        $entry->load('completedByUser:id,name');

        return response()->json([
            'message' => $entry->is_completed ? 'Item completed.' : 'Item unchecked.',
            'entry' => $entry,
        ]);
    }

    // ── Checklist Template Management (admin) ──────────────────

    public function checklistItems(int $serviceId): JsonResponse
    {
        $items = ChecklistItem::where('service_id', $serviceId)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function storeChecklistItem(Request $request, int $serviceId): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_required' => ['sometimes', 'boolean'],
        ]);

        $data['service_id'] = $serviceId;

        $item = ChecklistItem::create($data);

        return response()->json([
            'message' => 'Checklist item created.',
            'item' => $item,
        ], 201);
    }

    public function deleteChecklistItem(int $serviceId, ChecklistItem $checklistItem): JsonResponse
    {
        if ($checklistItem->service_id !== $serviceId) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $checklistItem->delete();

        return response()->json(['message' => 'Checklist item deleted.']);
    }

    // ── Signature ───────────────────────────────────────────────

    public function storeSignature(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'string'], // base64 image data
            'signed_by_name' => ['required', 'string', 'max:255'],
        ]);

        $imageData = base64_decode(
            preg_replace('#^data:image/\w+;base64,#i', '', $request->input('signature'))
        );

        $fileName = "signatures/{$serviceJob->id}_" . time() . '.png';
        Storage::disk('public')->put($fileName, $imageData);

        $serviceJob->update([
            'signature_path' => $fileName,
            'signed_by_name' => $request->input('signed_by_name'),
            'signed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Signature saved.',
            'signature_url' => Storage::disk('public')->url($fileName),
        ]);
    }

    // ── Comments ────────────────────────────────────────────────

    public function comments(ServiceJob $serviceJob): JsonResponse
    {
        $comments = $serviceJob->comments()
            ->with('user:id,name,role')
            ->get();

        return response()->json(['comments' => $comments]);
    }

    public function storeComment(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $comment = JobComment::create([
            'service_job_id' => $serviceJob->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);

        $comment->load('user:id,name,role');

        return response()->json([
            'message' => 'Comment added.',
            'comment' => $comment,
        ], 201);
    }

    public function deleteComment(ServiceJob $serviceJob, JobComment $comment): JsonResponse
    {
        if ($comment->service_job_id !== $serviceJob->id) {
            return response()->json(['message' => 'Comment not found.'], 404);
        }

        if ($comment->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            return response()->json(['message' => 'You can only delete your own comments.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    // ── Clone Job ───────────────────────────────────────────────

    public function cloneJob(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $request->validate([
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
        ]);

        $newJob = ServiceJob::create([
            'customer_id' => $serviceJob->customer_id,
            'service_id' => $serviceJob->service_id,
            'technician_id' => $serviceJob->technician_id,
            'created_by' => $request->user()->id,
            'status' => $serviceJob->technician_id ? 'assigned' : 'pending',
            'priority' => $serviceJob->priority,
            'description' => $serviceJob->description,
            'address' => $serviceJob->address,
            'scheduled_date' => $request->input('scheduled_date'),
            'scheduled_time' => $request->input('scheduled_time', $serviceJob->scheduled_time),
            'total_cost' => $serviceJob->total_cost,
        ]);

        $newJob->load(['customer', 'service', 'technician', 'creator']);

        return response()->json([
            'message' => 'Job cloned successfully.',
            'job' => new \App\Http\Resources\ServiceJobResource($newJob),
        ], 201);
    }
}
