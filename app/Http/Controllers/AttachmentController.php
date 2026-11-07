<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ThreadMessage;
use App\Services\TicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves ticket attachments from private storage, only to people who may open the ticket.
 */
class AttachmentController extends Controller
{
    public function __construct(protected TicketAccess $access)
    {
    }

    /**
     * A file the student attached when submitting the ticket.
     */
    public function complaint(Request $request, Complaint $complaint, int $index): StreamedResponse
    {
        abort_unless($this->access->canViewComplaint(Auth::user(), $complaint), 403);

        $attachment = $complaint->attachment_files[$index] ?? null;

        abort_unless($attachment, 404);

        return $this->serve(
            $attachment['path'],
            $attachment['name'] ?? basename($attachment['path']),
            $request->boolean('preview'),
        );
    }

    /**
     * A file sent in the ticket's conversation.
     */
    public function message(Request $request, ThreadMessage $message): StreamedResponse
    {
        $complaint = $message->thread?->ticket?->complaint;

        abort_unless($complaint && $this->access->canViewComplaint(Auth::user(), $complaint), 403);
        abort_unless($message->file_attachment, 404);

        return $this->serve(
            $message->file_attachment,
            $message->file_attachment_name ?: basename($message->file_attachment),
            $request->boolean('preview'),
        );
    }

    protected function serve(string $path, string $name, bool $preview = false): StreamedResponse
    {
        // Files uploaded before attachments became private are still on the public disk.
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                if ($preview && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'heic'], true)) {
                    return Storage::disk($disk)->response($path, $name, [
                        'Cache-Control' => 'private, no-store',
                        'X-Content-Type-Options' => 'nosniff',
                    ]);
                }

                return Storage::disk($disk)->download($path, $name, ['Cache-Control' => 'private, no-store']);
            }
        }

        abort(404);
    }
}
