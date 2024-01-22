<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\PsApi;
use Illuminate\Support\Facades\Storage;
use cardinalby\ContentDisposition\ContentDisposition;

class TicketCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'ticketId',
        'text',
        'subject',
        'changeId',
        'attachments',
        'attachmentIds',
    ];

    protected $hidden = [
        'created_at',
        'updated_ad',
        'internal_id',
        'attachments',
        'attachmentsIds',
    ];

    protected $casts = [
        'attachments' => 'array',
        'attachmentIds' => 'array',
    ];

    public function ticket() {
        return $this->belongsTo(Ticket::class, 'id', 'ticketId');
    }

    /**
     * Henter ned id for vedlegg til denne kommunikasjonen og lagrer dem som attachmentIds
     */
    public function getAttachmentIds(PsApi|null $ps = null): array|false {
        // Kortslutter innhentingen hvis de allerede er hentet
        $attachmentIds = $this->attachmentIds;
        if (is_array($attachmentIds) && count($attachmentIds)) return $attachmentIds;

        // Henter fra Pureservice
        if (!$ps) $ps = new PsApi();
        $uri = '/communication/'.$this->id;
        $params = [
            'include' => 'attachments',
        ];
        $response = $ps->apiQuery($uri, $params, true);
        if ($response->successful()):
            $attachmentIds = $response->json('linked.attachments.ids');
            $this->attachmentIds = $attachmentIds;
            $this->save();
            return $attachmentIds;
        endif;
        return false;
    }

    public function downloadAttachments(PsApi|null $ps): bool {
        if (!$ps) $ps = new PsApi();
        $attachmentIds = $this->getAttachmentIds($ps);
        $dlPath = $this->ticket()->getDownloadPath();
        $attachments = $ps->downloadAttachmentsById($attachmentIds, $dlPath);
        $this->attachments = $attachments ? $attachments : [];
        $this->save();
        return $attachments ? true : false;
    }

    public function addToPS(PsApi|null $ps): null|TicketCommunication {
        if (!$this->id):
            // Oppretter kommunikasjonen i Pureservice
        endif;
        if ($this->id):
            return $this;
        endif;
        return null;
    }

}
