<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ProfessionalServiceOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceOrderAttachmentController extends Controller
{
    /**
     * POST /api/{lang?}/service-orders/{id}/attachments
     * Securely upload private attachments for a service order.
     * Allowed for: The ordering customer (User) or assigned practitioner (Admin).
     */
    public function upload(Request $request, $id): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,txt', 'max:10240'],
            'type' => ['nullable', 'string', 'in:deliverable,input'],
        ]);

        $order = ProfessionalServiceOrder::findOrFail($id);
        $authUser = $request->user();

        $isCustomer = $authUser instanceof User && (int) $order->user_id === (int) $authUser->id;
        $isAstrologer = $authUser instanceof Admin && (int) $order->admin_id === (int) $authUser->id;

        if (!$isCustomer && !$isAstrologer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You do not have access to this service order.',
            ], 403);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $sizeBytes = $file->getSize();

        // Stored in private disk storage (outside public web directory)
        $storedPath = Storage::disk('local')->putFile('private/service_orders/' . $order->id, $file);
        $basename = basename($storedPath);
        $downloadUrl = "/api/service-orders/{$order->id}/attachments/{$basename}";

        // Persist attachment into the appropriate order field
        $type = $request->input('type');
        if ($isAstrologer || $type === 'deliverable') {
            $attachments = $order->deliverable_attachments ?? [];
            if (!in_array($downloadUrl, $attachments, true)) {
                $attachments[] = $downloadUrl;
                $order->deliverable_attachments = $attachments;
                $order->save();
            }
        } else {
            $payload = $order->input_payload ?? [];
            $attachments = $payload['attachment_urls'] ?? [];
            if (!in_array($downloadUrl, $attachments, true)) {
                $attachments[] = $downloadUrl;
                $payload['attachment_urls'] = $attachments;
                $order->input_payload = $payload;
                $order->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Attachment uploaded securely.',
            'data' => [
                'filename' => $basename,
                'original_name' => $originalName,
                'url' => $downloadUrl,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
            ],
        ], 201);
    }

    /**
     * GET /api/{lang?}/service-orders/{id}/attachments/{filename}
     * Securely download or view private deliverable/order attachments.
     * Enforces strict Sanctum authorization (Customer who owns order OR assigned Astrologer only).
     */
    public function download(Request $request, $id, string $filename): JsonResponse|StreamedResponse
    {
        // Prevent path traversal
        $sanitized = basename($filename);
        if ($sanitized !== $filename || str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid attachment filename.',
            ], 400);
        }

        $order = ProfessionalServiceOrder::findOrFail($id);
        $authUser = $request->user();

        $isCustomer = $authUser instanceof User && (int) $order->user_id === (int) $authUser->id;
        $isAstrologer = $authUser instanceof Admin && (int) $order->admin_id === (int) $authUser->id;

        if (!$isCustomer && !$isAstrologer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Access to private attachment denied.',
            ], 403);
        }

        $fullPath = 'private/service_orders/' . $order->id . '/' . $sanitized;

        if (!Storage::disk('local')->exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment file not found.',
            ], 404);
        }

        return Storage::disk('local')->response(
            $fullPath,
            $sanitized,
            [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
