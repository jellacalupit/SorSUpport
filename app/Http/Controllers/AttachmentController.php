<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ThreadMessage;
use App\Services\TicketAccess;
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
    public function complaint(Complaint $complaint, int $index): StreamedResponse
    {
        abort_unless($this->access->canViewComplaint(Auth::user(), $complaint), 403);

        $attachment = $complaint->attachment_files[$index] ?? null;

        abort_unless($attachment, 404);

        return $this->serve($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
    }

    /**
     * A file sent in the ticket's conversation.
     */
    public function message(ThreadMessage $message): StreamedResponse
    {
        $complaint = $message->thread?->ticket?->complaint;

        abort_unless($complaint && $this->access->canViewComplaint(Auth::user(), $complaint), 403);
        abort_unless($message->file_attachment, 404);

        return $this->serve($message->file_attachment, $message->file_attachment_name ?: basename($message->file_attachment));
    }

    protected function serve(string $path, string $name): StreamedResponse
    {
        // Files uploaded before attachments became private are still on the public disk.
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, $name, ['Cache-Control' => 'private, no-store']);
            }
        }

        abort(404);
    }
}
