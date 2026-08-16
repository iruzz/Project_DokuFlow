<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    /**
     * Return the current user's saved signature data (for AJAX page-load fetch).
     */
    public function show(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->hasSignature()) {
            $sig = $user->signature;
            return response()->json([
                'success' => true,
                'url'        => $sig->url,
                'updated_at' => $sig->updated_at->toISOString(),
            ]);
        }

        return response()->json(['success' => false], 404);
    }

    /**
     * Save or update current user's digital signature drawn on profile canvas.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'signature_data' => ['required', 'string'], // base64 data url
        ]);

        $user = Auth::user();
        $dataUrl = $request->input('signature_data');

        if (!preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $type)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Format gambar tanda tangan tidak valid.'], 422);
            }
            return back()->with('error', 'Format gambar tanda tangan tidak valid.');
        }

        $imageData = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $imageData = base64_decode($imageData);

        if ($imageData === false) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal memproses gambar tanda tangan.'], 422);
            }
            return back()->with('error', 'Gagal memproses gambar tanda tangan.');
        }

        $filename = 'signatures/sig_' . $user->id . '_' . time() . '.png';

        // Delete existing signature file if exists
        if ($user->signature && Storage::disk('public')->exists($user->signature->file_path)) {
            Storage::disk('public')->delete($user->signature->file_path);
        }

        Storage::disk('public')->put($filename, $imageData);

        $signature = Signature::updateOrCreate(
            ['user_id' => $user->id],
            [
                'file_path' => $filename,
                'signature_type' => 'canvas',
            ]
        );

        // updateOrCreate doesn't always bump updated_at when values are identical;
        // refresh to get the final DB state.
        $signature->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'success'    => true,
                'message'    => 'Tanda tangan digital berhasil disimpan.',
                'url'        => $signature->url,
                'updated_at' => $signature->updated_at->toISOString(),
            ]);
        }

        return back()->with('status', 'signature-saved');
    }

    /**
     * Delete current user's signature.
     */
    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        if ($user->signature) {
            if (Storage::disk('public')->exists($user->signature->file_path)) {
                Storage::disk('public')->delete($user->signature->file_path);
            }
            $user->signature->delete();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan digital berhasil dihapus.',
            ]);
        }

        return back()->with('status', 'signature-deleted');
    }

    /**
     * Get list of users with roles for Jodit editor toolbar signature dropdown.
     */
    public function availableUsers(): JsonResponse
    {
        $currentUser = Auth::user();

        $users = User::with('division')
            ->where('is_active', true)
            ->get()
            ->map(function ($u) use ($currentUser) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => match($u->system_role) {
                        'admin' => 'Admin',
                        'head' => 'Kepala Divisi',
                        default => 'Staff',
                    },
                    'division' => $u->division ? $u->division->name : 'Umum',
                    'is_me' => $u->id === $currentUser->id,
                    'has_signature' => $u->hasSignature(),
                    'placeholder' => $u->id === $currentUser->id ? '[ttd.me]' : '[ttd:' . $u->name . ']',
                ];
            })
            ->sortByDesc('is_me')
            ->values();

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Display signature requests list (incoming & outgoing).
     */
    public function requestsIndex()
    {
        $user = Auth::user();

        $incomingRequests = SignatureRequest::with(['requester', 'document'])
            ->where('target_user_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'incoming_page');

        $outgoingRequests = SignatureRequest::with(['targetUser', 'document'])
            ->where('requester_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'outgoing_page');

        return view('signature_requests.index', compact('incomingRequests', 'outgoingRequests'));
    }

    /**
     * Approve signature usage request.
     */
    public function approve(SignatureRequest $signatureRequest): RedirectResponse
    {
        if (Auth::id() !== $signatureRequest->target_user_id) {
            abort(403, 'Anda tidak berhak menyetujui permintaan ini.');
        }

        $signatureRequest->update([
            'is_viewed' => true,
            'status' => 'approved',
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Permintaan tanda tangan telah disetujui.');
    }

    /**
     * Reject signature usage request.
     */
    public function reject(Request $request, SignatureRequest $signatureRequest): RedirectResponse
    {
        if (Auth::id() !== $signatureRequest->target_user_id) {
            abort(403, 'Anda tidak berhak menolak permintaan ini.');
        }

        $signatureRequest->update([
            'status' => 'rejected',
            'rejected_reason' => $request->input('reason', 'Ditolak oleh pemilik tanda tangan.'),
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Permintaan tanda tangan telah ditolak.');
    }

}
