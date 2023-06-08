<?php

namespace App\Services;
use App\Models\{Ticket, TicketCommunication, User, Company};
use Carbon\Carbon;
use Illuminate\Support\{Str, Arr};

/**
 * Versjon 2 av Pureservice sitt API, basert på Laravel sitt HTTP Client-bibliotek
 */
class PsApi extends API {
    protected array $ticketOptions;

    public function __construct() {
        $this->cKey = 'pureservice';
        $this->setProperties();
    }

    /**
     * Oppretter sak i Pureservice fra sak i DB
     */
    public function createTicketFromDB(Ticket $ticket) {

    }

    /**
     * Universell funksjon for å hente ID på et objekt oppgitt med navn
     * @param string    $entity     Objektnavnet, f.eks. 'status', '
     * @param string    $name       Navnet på enheten som skal finnes
     * @param bool      $useKey     Instruerer funksjonen til å lete i 'key' i stedet for 'name'
     *
     * @return mixed      ID for enheten eller null
     */
    public function getEntityId($entity, $name, $useKey = false) {
        $entity = Str::lower($entity);
        $uri = '/'.$entity.'/';
        $query = [];
        if ($useKey):
            $query['filter'] = 'key == "'.$name.'"';
        else:
            $query['filter'] = 'name == "'.$name.'"';
        endif;
        $entities = Str::plural($entity);
        if ($result = $this->apiQuery($uri, $query)):
            if (count($result[$entities])) return $result[$entities][0]['id'];
        endif;
        return null; // Hvis ikke funnet

    }

    /**
     * Universell funksjon for å hente et objekt oppgitt med navn
     * @param string    $entity     Objektnavnet, f.eks. 'status', '
     * @param string    $name       Navnet på enheten som skal finnes
     * @param bool      $useKey     Instruerer funksjonen til å lete i 'key' i stedet for 'name'
     *
     * @return mixed    assoc_array for enheten eller null
     */
    public function getEntityByName($entity, $name, $useKey = false) {
        $entity = Str::lower($entity);
        $uri = '/'.$entity.'/';
        $query = [];
        if ($useKey):
            $query['filter'] = 'key == "'.$name.'"';
        else:
            $query['filter'] = 'name == "'.$name.'"';
        endif;
        $entities = Str::plural($entity);
        if ($result = $this->apiQuery($uri, $query)):
            if (count($result[$entities]) > 0) return $result[$entities][0];
        endif;
        return null; // Hvis ikke funnet
    }

    /**
     * Henter ned standardinnstillinger for å opprette saker i PS
     *
     * Setter variabelen $this->ticketOptions
     */
    public function setTicketOptions(string $prefix = 'ticket') : void {
        $this->ticketOptions = [
            'zoneId' => $this->getEntityId('department', $this->myConf($prefix.'.zone')),
            'teamId' => $this->getEntityId('team', $this->myConf($prefix.'.team')),
            'sourceId' => $this->getEntityId('source', $this->myConf($prefix.'.source')),
            'requestTypeId' => $this->getEntityId('requesttype', $this->myConf($prefix.'.requestType'), true),
            'priorityId' => $this->getEntityId('priority', $this->myConf($prefix.'.priority')),
            'statusId' => $this->getEntityId('status', $this->myConf($prefix.'.status')),
            'ticketTypeId' => $this->getEntityId('tickettype', $this->myConf($prefix.'.ticketType')),
        ];
    }
    /**
     * Returnerer $this->ticketOptions
     */
    public function getTicketOptions() {
        return $this->ticketOptions;
    }

}
