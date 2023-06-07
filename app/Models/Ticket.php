<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasOne};
use Illuminate\Support\{Arr, Str, Collection};
use App\Services\{Eformidling, Pureservice, PsApi};
//use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'requestNumber',
        'assignedAgentId',
        'assignedTeamId',
        'assignedDepartmentId',
        'userId',
        'priorityId',
        'statusId',
        'sourceId',
        'category1Id',
        'category2Id',
        'category3Id',
        'ticketTypeId',
        'visibility',
        'emailAddress',
        'subject',
        'description',
        'eFormidling',
        'action',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'internal_id',
        'eForsendelse',
        'action',
    ];

    protected $properties = [
        'eForsendelse' => false,
    ];


    public function user() : HasOne {
        return $this->hasOne(User::class, 'id', 'userId');
    }

    public function communications() : HasMany {
        return $this->hasMany(TicketCommunication::class, 'ticketId', 'id');
    }

    public function recipients() : BelongsToMany {
        return $this->belongsToMany(User::class);
    }

    public function recipientCompanies(): BelongsToMany {
        return $this->belongsToMany(Company::class);
    }

    /**
     * Returnerer sakens ID til e-postens emnefelt
     */
    public function getTicketSlug() : string {
        $replaceString = config('pureservice.ticket.codeTemplate');
        return Str::replace('{{RequestNumber}}', $this->requestNumber, $replaceString);
    }

    public function extractRecipientsFromAsset(PsApi|Pureservice $ps, array $recipientListAssetType) : void {

        $uri = '/relationship/'.$this->id.'/fromTicket?include=toAsset&filter=toAsset.typeId == '.$recipientListAssetType['id'];
        $relatedLists = $ps->apiGet($uri);
        if (count($relatedLists['relationships']) > 0):
            foreach ($relatedLists['linked']['assets'] as $list):
                // Henter ut mottakerlistens relaterte bruker og firma,
                // og kobler dem til saken som mottakere
                $uri = '/relationship/'.$list['id'].'/fromAsset?include=toUser,toCompany,toUser.emailaddresses,toCompany.emailaddresses';
                if ($listRelations = $ps->apiGet($uri)):

                    // 1. Legger sluttbrukere som er relatert til mottakerlisten til saken
                    if (count($listRelations['linked']['users'])):
                        $users = collect($listRelations['linked']['users'])
                        ->mapInto(User::class)
                        ->each(function (User $user, int $key) use ($listRelations) {
                            if ($email = collect($listRelations['linked']['emailaddresses'])->firstWhere('userId', $user->id)):
                                $user->email = $email['email'];
                            endif;
                            if ($existing = User::firstWhere('id', $user->id)):
                                $user = $existing;
                            endif;
                            $user->save();
                            $this->recipients()->attach($user);
                        });
                        unset($users);
                    endif;

                    // 2. Legger firma som er relatert til mottakerlisten til saken
                    if (count($listRelations['linked']['companies'])):
                        $companies = collect($listRelations['linked']['companies'])
                        ->mapInto(Company::class)
                        ->each(function (Company $company, int $key) use ($listRelations) : void {
                            if ($email = collect($listRelations['linked']['companyemailaddresses'])->firstWhere('companyId', $company->id)):
                                $company->email = $email['email'];
                            endif;
                            if ($existing = Company::firstWhere('id', $company->id)) $company = $existing;
                            $company->save();
                            $this->recipientCompanies()->attach($company);
                        });
                        unset($companies);
                    endif;
                endif; // $listRelations
            endforeach; // $relatedLists['linked']['assets'] as $list
        endif; // $relatedLists && count($relatedLists['relationships']

    }

    /**
     * Laster ned og tar vare på eventuelle vedlegg til saken
     */
    public function downloadAttachments(PsApi $ps) {
        $uri = '/attachment/';
        $query = [
            'filter' => 'ticketId == '.$this->id
        ];
        $res = $ps->apiGet($uri, false, null, $query);
        if (count($res['attachments'])):
            $dlPath = config('pureservice.api.dlPath', storage_path('psApi'));
            $dlPath .= '/'.$this->requestNumber;
            mkdir($dlPath, 0755, true);
            $filesToAttach = [];
            foreach ($res['attachments'] as $att):
                $uri = '/attachment/download/'.$att['id'];
                $response = $ps->apiGet($uri, true, '*/*')->toPsrResponse();
                // Henter filnavn fra header content-disposition - 'attachment; filename="dokumenter-7104a48e.zip"'
                $fileName = preg_replace('/.*\"(.*)"/','$1', $response->getHeader('content-disposition')[0]);
                $filePath = $dlPath.'/'.$fileName;
                file_put_contents($filePath, $response->getBody()->getContents());
                $filesToAttach[] = $filePath;
            endforeach;

            // Kobler vedlegget til saken i DB
            if (count($filesToAttach)):
                $this->attachments = $filesToAttach;
                $this->save();
            endif;
        endif;

    }

    /**
     * Hvordan skal saken sendes ut?
     */
    public function decideAction() {
        switch ($this->emailAddress):
            case config('pureservice.dispatch.address_ef'):
                $this->eFormidling = true;
                $this->action = 'normalSend';
                break;
            default:
                $this->eFormidling = false;
                $this->action = 'normalSend';
        endswitch;
        $this->save();
    }

    /**
     * Utsendelse av saken, oppdaterer $results
     */
    public function dispatchMessage(Eformidling $ef, array &$results): void {
        // Først brukere. De har ikke orgnr, og må dermed kontaktes per e-post
        foreach ($this->recipients()->lazy() as $user):
            // Brukeren har ikke en gyldig e-postadresse, hopper over.
            if (Str::endsWith($user->email, 'pureservice.local')):
                $results['ingen adresse']++;
                continue;
            endif;
            
        endforeach;

    }
}
