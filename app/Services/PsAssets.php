<?php

namespace App\Services;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PsAssets extends PsApi {
    public bool $up = false;

    public function __construct() {
        parent::__construct();
        $this->fetchTypeIds();
    }


    /** fetchTypeIds
     * Henter inn IDer til forskjellige innholdstyper.
     * Befolker følgende config-verdier med verdier fra Pureservice:
     *  config('pureservice.computer.asset_type_id')
     *  config('pureservice.computer.className')
     *  config('pureservice.computer.status')
     *  config('pureservice.computer.relationship_type_id')
     *  config('pureservice.computer.properties')
     *
     *  config('pureservice.mobile.asset_type_id')
     *  config('pureservice.mobile.className')
     *  config('pureservice.mobile.status')
     *  config('pureservice.mobile.relationship_type_id')
     *  config('pureservice.mobile.properties')
     * @return void
     */
    protected function fetchTypeIds() {
        // Henter ut relasjonstyper allerede i bruk i basen
        $uri = '/relationship/';
        $query = [
            'include' => 'type',
            'filter' => 'toAssetId!=null AND fromUserId!=null AND solvingRelationship == false'
        ];
        $result = $this->apiQuery($uri, $query);
        $relationshipTypes = collect($result['linked']['relationshiptypes']);
        $this->statuses = [];
        foreach(['computer', 'mobile'] as $type):
            // Henter ut ressurstypen basert på displayName
            $uri = '/assettype/';
            $query = [
                'filter' => 'name.equals("'.$this->myConf($type.'.displayName').'")',
                'include' => 'fields,statuses'
            ];
            $result = $this->apiQuery($uri, $query);
            //dd($result);
            if (count($result['assettypes']) > 0):
                // setter asset_type_id og className i config basert på resultatet
                config(['pureservice.'.$type.'.asset_type_id' => $result['assettypes'][0]['id']]);
                config([
                    'pureservice.'.$type.'.className' => '_'.config('pureservice.'.$type.'.asset_type_id').'_Assets_'.config('pureservice.'.$type.'.displayName')
                ]);

                // Henter ut status-IDer
                $raw_statuses = collect($result['linked']['assetstatuses']);
                $this->statuses[$type] = [];
                foreach (config('pureservice.'.$type.'.status') as $key=>$value):
                    $raw_status = $raw_statuses->firstWhere('name', $value);
                    $statusId = $raw_status != null ? $raw_status['id'] : null;
                    $this->statuses[$type][$key] = $statusId;
                endforeach;
            endif;

            // Finner propertyName for feltnavnene definert i config('pureservice.'.$type.'.fields')
            $properties = collect($result['linked']['assettypefields']);
            foreach (config('pureservice.'.$type.'.fields') as $key => $fieldName):
                $property = $properties->firstWhere('name', $fieldName);
                config(['pureservice.'.$type.'.properties.'.$key => lcfirst($property['propertyName'])]);
            endforeach;

            // Finner relasjonstypens ID for brukerkoblingen
            if ($relationshipType = $relationshipTypes->firstWhere('fromAssetTypeId', config('pureservice.'.$type.'.asset_type_id'))):
                config(['pureservice.'.$type.'.relationship_type_id' => $relationshipType['id']]);
            endif;
        endforeach;
        $this->up = true;
    }

    /**
     * Henter alle datamaskin- og mobilenhet-ressurser fra Pureservice
     * @return  Illuminate\Support\Collection     Collection over ressursene
     */
    public function getAllAssets(): Collection {
        $totalAssets = [];
        foreach (['computer', 'mobile'] as $type):
            $uri = '/asset/'.$this->myConf($type.'.className');
            $assets = $this->apiGet($uri)['assets'];
            foreach ($assets as $asset):
                if (!isset($asset['assets_UDF_95_EOL'])):
                    // Korrigerer feil i APIet dersom feltene ikke returneres ved søk
                    $response = $this->apiGet($uri.$asset['id'], true);
                    if ($response->successful()):
                        $asset = $response->json('assets.0');
                    endif;
                endif;
                $asset['type'] = $type;
                $asset['usernames'] = $this->getAssetRelatedUsernames($asset['id']);
                $totalAssets[] = $asset;
            endforeach;
        endforeach;
        return collect($totalAssets);
    }

    public function getAssetByUniqueId(string $uniqueId, string $type = 'computer'): array|false {
        $typeId = $this->myConf($type.'.asset_type_id');
        $uri = '/asset/';
        $query = [
            'filter' => 'uniqueId == "'.$uniqueId.'" AND typeId == '.$typeId,
        ];
        $response = $this->ApiQuery($uri, $query, true);
        if ($response->successful()):
            $asset = $response->json('assets.0');
            if (!isset($asset['assets_UDF_95_EOL'])):
                // Korrigerer feil i APIet dersom feltene ikke returneres ved søk
                $response = $this->apiGet($uri.$asset['id'], true);
                if ($response->successful()):
                    $asset = $response->json('assets.0');
                endif;
            endif;

            $asset['type'] = $type;
            $asset['usernames'] = $this->getAssetRelatedUsernames($asset['id']);
            return $asset;
        endif;
        return false;
    }

    public function getAssetRelatedUsernames(int $assetId): array {
        $relations_full = $this->getAssetRelationships($assetId);

        if (count($relations_full['relationships']) == 0) return [];

        $linkedUsers = &$relations_full['linked']['users'];
        $linkedEmails = collect($relations_full['linked']['emailaddresses']);
        $usernames = [];
        foreach($linkedUsers as $user):
            $usernames[] = $linkedEmails->firstWhere('id', $user['emailAddressId'])['email'];
        endforeach;
        return $usernames;
    }
    /**
     * Henter relasjoner for en gitt ressurs
     * @param string    $assetId    Ressursens ID
     *
     * @return assoc_array  Array over relasjonene knyttet til ressursen
     */
    public function getAssetRelationships($assetId) {
        $uri = '/relationship/' . $assetId .'/fromAsset';
        $query = [
            'include' => 'type,type.relationshipTypeGroup,toUser,toUser.emailaddress',
            'filter' => 'toUserId != NULL'
        ];
        return $this->apiQuery($uri, $query);
    }
    /** getInitialStatus
     * Bestemmer initiell status før oppretting av asset i Pureservice
     * @param   psAsset     assoc_array     Asset-array som følger Pureservice sine felt-definisjoner
     * @return  integer                     Status-ID som kan brukes mot Pureservice
     */
    protected function getInitialStatus($psAsset): int {
        $type = $psAsset['type'];
        $fn = config('pureservice.'.$type.'.properties');
        // Standard status for nye enheter
        $status = $this->statuses[$type]['active_inStorage'];

        $today = Carbon::today();
        $EOL = Carbon::create($psAsset[$fn['EOL']]);
        if (count($psAsset['usernames']) > 0):
            // Enheten er tildelt en bruker
            $status = $this->statuses[$type]['active_deployed'];
        endif;
        // Dersom EOL er mindre enn 3 mnd unna settes status til utfasing
        if ($EOL->lessThanOrEqualTo($today->copy()->addMonth(3))) $status = $this->statuses[$type]['active_phaseOut'];

        return $status;
    }

    /** calculateStatus
     * Bestemmer statusendring for eksisterende asset.
     * @param   psAsset         assoc_array   Asset-array som følger Pureservice sine felt-definisjoner
     * @param   notDeployed     boolean       Bestemmer om vurderingen skal ta høyde for at maskinen ikke skal være tildelt
     *
     * @return  integer                       Status-ID som kan brukes mot Pureservice
     */
    public function calculateStatus($psAsset, $notDeployed=false) {
        $type = $psAsset['type'];
        $fn = config('pureservice.'.$type.'.properties');
        $status = $psAsset['statusId'];;
        $active_statuses = [
            $this->statuses[$type]['active_deployed'],
            $this->statuses[$type]['active_inStorage'],
            $this->statuses[$type]['active_phaseOut']
        ];
        if (in_array($status, $active_statuses)):
            $today = Carbon::today();
            $EOL = Carbon::create($psAsset[$fn['EOL']]);
            if ($EOL->lessThanOrEqualTo($today->copy()->addMonth(3))) $status = $this->statuses[$type]['active_phaseOut'];
            if ($notDeployed && ($status == $this->statuses[$type]['active_deployed'])) $status = $this->statuses[$type]['active_phaseOut'];
        endif;
        return $status;
    }

    /**
     * Kobler en ressurs til brukernavn
     * @param string    $assetId    Ressursens ID
     * @param array     $emails     Array over brukernavn som skal relateres til ressursen
     * @param string    $type       Angir ressurstypen, slik at man bruker korrekt relasjons-ID
     *
     * @return bool     Angir om koblingen ble utført eller ikke
     */
    public function relateAssetToUsernames(int $assetId, array $emails, string $type='computer'): bool {
        $jsonBody = ['relationships' => []];
        if (! is_array($emails)) $emails = [$emails];
        foreach ($emails as $email):
            // Finner userId for brukeren gjennom e-postadressen
            if ($user = $this->findUser($email)):
                $user_relationship = [
                    'main' => 'ToAssetId',
                    'inverseMain' => 'FromAssetId',

                    'links' => [
                        'type' => ['id' => (int) config('pureservice.'.$type.'.relationship_type_id')],
                        'toAsset' => ['id' => $assetId],
                        'fromUser' => ['id' => $user['id']],
                    ]
                ];
                $jsonBody['relationships'][] = $user_relationship;
            endif;
        endforeach;
        //echo json_encode($jsonBody, JSON_PRETTY_PRINT);
        //dd($jsonBody);
        if (count($jsonBody['relationships']) > 0):
            $uri = '/relationship/';
            $result = $this->apiPost($uri, $jsonBody, 'application/json', $this->myConf('api.accept'));
            //dd($result->json());
        endif;
        return false;
    }

    public function createAsset(array $psAsset): false|int {
        $type = $psAsset['type'];
        $uri = '/asset/'.config('pureservice.'.$type.'.className');
        $statusId = (int) $this->getInitialStatus($psAsset);
        // $psAsset['links'] = [
        //     'type' => ['id' => config('pureservice.'.$type.'.asset_type_id')],
        //     'status' => ['id' => $statusId]
        // ];
        $psAsset['statusId'] = $statusId;
        $psAsset['typeId'] = config('pureservice.'.$type.'.asset_type_id');
        $psAsset = collect($psAsset)->except([
            'usernames', 'type',
            'modified', 'modifiedById',
            'created', 'createdById',
            'importedById', 'importJobId',
            'restrictedDepartmentId',
            'restrictedTeamId',
            'restrictedUserId',
            'isMarkedForDeletion',
        ]);
        // $body = [
        //     config('pureservice.'.$type.'.className') => [
        //         $psAsset->toArray(),
        //     ]
        // ];
        $body = $psAsset->toArray();
        //dd($body);
        $response = $this->apiPost($uri, $body);
        //dd($response->json());
        if ($response->successful()):
            return $response->json('assets.0.id');
        else:
            return false;
            //dd($response->json());
        endif;
    }

    public function deleteAsset(array $asset): bool {
        $data = ['isMarkedForDeletion' => true];
        $uri =  $uri = '/asset/'.$asset['id'];
        $response = $this->apiPatch($uri, $data, 'application/json');
        return $response->successful();
    }

    public function updateAssetDetail(array $asset, array $data) {
        $uri = '/asset/'.$asset['id'];
        //$typeClass = config('pureservice.'.$updateAsset['type'].'.className');
        $data['name'] = isset($data['name']) ? $data['name'] : $asset['name'];
        $data['imported'] = Carbon::now()->toJSON();
        $data['typeId'] = $asset['typeId'];
        // $updateAsset = collect($updateAsset)
        //     ->except([
        //         'usernames', 'type',
        //         'modified', 'modifiedById',
        //         'created', 'createdById',
        //         'importedById', 'importJobId',
        //         'links.restrictedDepartment',
        //         'restrictedDepartmentId',
        //         'links.restrictedTeam',
        //         'restrictedTeamId',
        //         'links.restrictedUser',
        //         'restrictedUserId',
        //         'isMarkedForDeletion',
        //         'links.modifiedBy',
        //         'links.createdBy',
        //         'links.importedBy',
        //         'links.importJob',
        //         'links',
        //         'imported',
        //     ])
        //     ->toArray();
        // //$updateAsset['links']['type']['id'] = (string) $updateAsset['links']['type']['id'];
        // $body = [
        //     $typeClass => [
        //         $updateAsset
        //     ],
        // ];
        $response = $this->apiPatch($uri, $data, 'application/json');
        if ($response->successful()):
            return $response->json('assets.0');
        else:
            // dd([$uri, $data, $response->json(), $response->status()]);
            return false;
        endif;
    }

    protected function assetUriWithType(array $psAsset, bool $addId): string {
        $uri = '/asset/'.$this->myConf($psAsset['type'].'.className').'/';
        return $addId ? $uri . $psAsset['id'] : $uri;
    }

    /** updateAsset
     * Kjører oppdatering på gitt Puserservice Asset
     *
     */
    public function updateAsset($psAsset) {
        $uri = '/asset/'.$psAsset['id'];
        $typeName = config('pureservice.'.$psAsset['type'].'.className');
        $newPsAsset = collect($psAsset)
            ->except([
                'usernames', 'type',
                'modified', 'modifiedById',
                'created', 'createdById',
                'importedById', 'importJobId',
                'restrictedDepartmentId',
                'restrictedTeamId',
                'restrictedUserId',
                'links',
                'isMarkedForDeletion',
                'id',
            ]);
        // $body = [
        //     $typeName => [
        //         $newPsAsset->toArray(),
        //     ]
        // ];
        $body = $newPsAsset->toArray();
        // dd($uri, $body);
        $response = $this->apiPatch($uri, $body);
        if ($response->failed()) dd($response->json());
        return $response->successful() ? $response->json('assets.0'): false;
    }

    // Oppdaterer statusId for psAsset-array
    public function changeAssetStatus(array &$psAsset, int $statusId): void {
        $psAsset['statusId'] = $statusId;
        $psAsset['links']['status']['id'] = $statusId;
    }

    public function getRelatedUsernames($assetId) {
        $relations_full = $this->getAssetRelationships($assetId);

        if (count($relations_full['relationships']) == 0) return [];

        $linkedUsers = &$relations_full['linked']['users'];
        $linkedEmails = collect($relations_full['linked']['emailaddresses']);
        $usernames = [];
        foreach($linkedUsers as $user):
            $usernames[] = $linkedEmails->firstWhere('id', $user['emailAddressId'])['email'];
        endforeach;
        return $usernames;
    }

}
