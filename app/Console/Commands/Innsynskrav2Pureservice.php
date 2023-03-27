<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Pureservice, Tools};
use Illuminate\Support\{Str, Arr};


class Innsynskrav2Pureservice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:sjekkInnsynskrav';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Søker opp innsynskrav i Pureservice og splitter dem opp';
    protected $version = '0.5';

    protected $start;
    protected $reqNos_created = [];
    protected $tickets;
    protected $attachments;

    protected Pureservice $ps;
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);
        $this->line('');

        $this->info(Tools::ts().'Starter opp...');
        $this->line('');
        $this->ps = new Pureservice();
        $this->ps->setTicketOptions('innsyn');
        $this->line(Tools::l1().'Ser etter innsynskrav...');

        $AND = ' %26%26 ';
        $uri = '/ticket/';
        $uri .= '?include=attachments';
        $uri .= '&filter=status.name=="'.config('innsyn.search.status').'"'.
            $AND.
            'assignedTeam.name=="'.config('innsyn.search.team').'"'.
            $AND.
            'ticketType.name=="'.config('innsyn.search.ticketType').'"';
        //dd($uri);
        if ($result = $this->ps->apiGet($uri)):
            if (count($result['tickets']) > 0):
                $this->tickets = $result['tickets'];
                $this->attachments = $result['linked']['attachments'];
                unset($result);
            else:
                $time = round(microtime(true) - $this->start, 2);
                $this->info('Fant ingen nye innsynskrav. Avslutter etter '.$time.' sekunder');
                return Command::SUCCESS;
            endif;
       else:
            $this->info('Ingen innsynkrav ble funnet. Avslutter.');
            return Command::FAILURE;
        endif;

        $ticketCount = count($this->tickets);
        $this->info('Fant '.$ticketCount.' innsynskrav som skal behandles');
        // Går gjennom innsynskravene fra Pureservice
        foreach ($this->tickets as $ticket):
            $msg = &$ticket['description'];

            if (count($this->attachments) > 0):
                $attachments = Arr::where($this->attachments, function(array $value, int $key) use ($ticket) {
                    if ($value['ticketId'] === $ticket['id']) return true;
                });
                foreach ($attachments as $a):
                    if ($a['fileName'] == 'order.xml'):
                        $response = $this->ps->apiGet('/attachment/download/'.$a['id'], true);
                        $bestilling = simplexml_load_string($response->getBody()->getContents());
                        unset($response);
                    endif;
                endforeach;
            endif;
            if (!isset($bestilling)):
                $this->error('Kunne ikke finne order.xml. Hopper over');
                continue;
            endif;
            $this->line('');

            $this->line(Tools::l1().'Leser inn data fra innsynskravet');
            $orderId = $bestilling->id->__toString();
            $this->line(Tools::l2().'Bestillings-ID: '.$orderId);
            $orderDate = $bestilling->bestillingsdato->__toString();
            $this->line(Tools::l2().'Bestillingsdato: '.$orderDate);

            $kontaktinfo = json_decode(json_encode($bestilling->kontaktinfo), true);
            foreach ($kontaktinfo as $key => $value):
                if (is_array($value)) $kontaktinfo[$key] = null;
            endforeach;
            $this->line(Tools::l2().'Forsendelsesmåte: '.$kontaktinfo['forsendelsesmåte']);
            $this->line(Tools::l2().'E-postadresse: '.$kontaktinfo['e-post']);
            if (strlen($kontaktinfo['navn']) < 3) $kontaktinfo['navn'] = $kontaktinfo['e-post'];
            $this->line(Tools::l2().'Innsenders navn: '.$kontaktinfo['navn']);
            $this->line(Tools::l2().'Organisasjon: '.$kontaktinfo['organisasjon']);
            $this->line(Tools::l2().'Land: '.$kontaktinfo['land']);
            $this->line('');

            $this->line(Tools::l1().'Leser inn dokumentlisten');
            $xml_documents = json_decode(json_encode($bestilling->dokumenter), true)['dokument'];
            unset($bestilling);

            $this->line('');
            $this->line(Tools::l1().'Henter eller registrerer sluttbruker i Pureservice');
            $company = false;
            if ($kontaktinfo['organisasjon'] != null):
                if ($company = $this->ps->findCompany(null, $kontaktinfo['organisasjon'])):
                    $this->line(Tools::l2().'Organisasjon finnes i Pureservice');
                else:
                    $this->line(Tools::l2().'Legger til organisasjon');
                    $company = $this->ps->addCompany($kontaktinfo['organisasjon']);
                endif;
            endif;
            if ($user = $this->ps->findUser($kontaktinfo['e-post'])):
                $this->line(Tools::l2().'Brukeren '.$user['fullName'].' finnes i Pureservice');
            else:
                $this->line(Tools::l2().'Legger til brukeren');
                $user = $this->ps->addCompanyUser($company, $kontaktinfo['e-post'], $kontaktinfo['navn']);
            endif;
            if (!$user):
                $this->error('Bruker finnes ikke i Pureservice. Avbryter...');
                return Command::FAILURE;
            endif;

            $this->line('');
            $this->line(Tools::ts().'Oppretter ett innsynskrav for hver unike sak');
            // Rydder i meldingsteksten. Tar bort linjeskift.
            $msg = preg_replace('/\\n/', '', $msg);

            // Henter ut listen over dokumenter (ordrelinjer)
            $docs = Str::between($msg, 'Dokumenter:<br>', '--------------------------------------');
            $aOrderLines = explode('--------------------------------------<br>', $docs);
            $aDocs = []; // Array som samler ordrelinjene per saksnr og dokumentnr

            /*
            // Sorterer kravene fra order.xml etter saksnr for å kunne samle forespørsler per sak
            $xml_documents = Arr::sort($xml_documents, function (array $value) {
                return $value['saksnr'];
            });
            */
            // Behandler ordrelinjene (i e-posten), slik at de blir klare til bruk
            foreach ($aOrderLines as $orderLine):
                $head = explode(' | ', Str::before($orderLine, '<br>'));
                $body = Str::after($orderLine, '<br>');
                $aBody = [];
                foreach ($head as $l):
                    $aBody[trim(preg_replace('/(.*): (.*)/', '\1', $l))] = preg_replace('/(.*): (.*)/', '\2', $l);
                endforeach;
                // Splitter teksten opp i array der "Feltnavn: verdi" blir til ['feltnavn' => 'verdi']
                foreach (explode('<br>', Str::after($orderLine, '<br>')) as $l):
                    if ($l != ''):
                        $key = trim(preg_replace('/(.*): (.*)/', '\1', $l));
                        $key = preg_replace('/:/', '', $key);
                        $value = preg_replace('/(.*): (.*)/', '\2', $l);
                        $value = preg_replace('/:/', '', $value);
                        $aBody[$key] = $value != $key ? $value : '';
                    endif;
                endforeach;
                if (!isset($aDocs[$aBody['Saksnr']])) $aDocs[$aBody['Saksnr']] = [];
                $aDocs[$aBody['Saksnr']][$aBody['Dok nr.']] = $aBody;
                // dd($meta);
            endforeach;
            unset($aBody);
            // dd($aDocs);
            // Går gjennom xml_documents og lager array over saksnumre
            $aCases = [];
            foreach($xml_documents as $request):
                $saknr = &$request['saksnr'];
                $doknr = &$request['dokumentnr'];
                if (!isset($aCases[$saknr])) $aCases[$saknr] = [];

                // Legger til saksinfo fra emailtext
                $request['dokumentnavn'] = $aDocs[$saknr][$doknr]['Dokument'];
                $request['dokumentdato'] = $aDocs[$saknr][$doknr]['Dok.dato'];
                $request['journaldato'] = $aDocs[$saknr][$doknr]['Journaldato'];
                $request['saksnavn'] = $aDocs[$saknr][$doknr]['Sak'];
                $request['enhet'] = $aDocs[$saknr][$doknr]['Enhet'];

                $aCases[$saknr][] = $request;
            endforeach;
            unset($aDocs);
            //dd($aCases);
            // Bygger sakene
            foreach ($aCases as $saknr => $requests):
                $firstline = true;
                $dokCount = count($requests);
                $uri = '/ticket/';
                $subject = 'Innsynskrav for sak '.$saknr;
                $this->line(Tools::l2().'Emne: "'.$subject.'".');
                foreach ($requests as $request):
                    if ($firstline):
                        $firstline = false; // Slik at vi ikke gjentar denne første delen for hvert dokument

                        $description = '<p>Innsynskrav for '.$dokCount.' ';
                        $description .= $dokCount > 1 ? 'dokumenter ': 'dokument ';
                        $description .= 'i sak '.$saknr.'<br />'.PHP_EOL.'<strong>'.$request['saksnavn'].'</strong></p>'.PHP_EOL;
                        $description .= '<p>Bestillingsdato: '.$orderDate.'</p>'.PHP_EOL;

                        $description .= '<p>Innsynskravet er bestilt av:<br />'.PHP_EOL;
                        if ($kontaktinfo['navn'] != $kontaktinfo['e-post']) $description .= $kontaktinfo['navn'].'<br />'.PHP_EOL;
                        if (strlen($kontaktinfo['organisasjon']) > 3) $description .= $kontaktinfo['organisasjon'].'<br />'.PHP_EOL;
                        $description .= '</p>'.PHP_EOL;
                        $description .= '<p>Svar på dette innsynskravet ønskes sendt med e-post til '.$kontaktinfo['e-post'].'</p>'.PHP_EOL;

                        $description .= '<p><strong>Dokumenter</strong></p>';
                    endif;
                    $description .= '<p>'.PHP_EOL;
                    $description .= 'Dokumentnr: '.$request['dokumentnr'].'<br />'.PHP_EOL;
                    $description .= 'Navn: <strong>'.$request['dokumentnavn'].'</strong><br />'.PHP_EOL;
                    $description .= 'Sekvensnr: '.$request['journalnr'].'<br />'.PHP_EOL;
                    $description .= 'Dokumentdato: '.$request['dokumentdato'].'<br />'.PHP_EOL;
                    $description .= 'Journaldato: '.$request['journaldato'].'<br />'.PHP_EOL;
                    $description .= 'Saksbehandler: '.$request['saksbehandler'].'<br />'.PHP_EOL;
                    $description .= 'Enhet: '.$request['enhet'].'<br />'.PHP_EOL;
                    $description .= '</p>'.PHP_EOL;

                endforeach; // $requests
                $description .= '<p>eInnsyn-ID: '.$orderId.'</p>';

                // Oppretter saken
                if ($newTicket = $this->ps->createTicket($subject, $description, $user['id'], config('pureservice.visibility.invisible'))):
                    $new_reqno = $newTicket['requestNumber'];
                    $this->line(Tools::l2().'Opprettet saken "'.$newTicket['subject'].'" med saksnr '.$new_reqno);
                    $this->reqNos_created[] = $new_reqno;
                endif;
                // Endrer sakens synlighet til synlig
                $ticketOptions = $this->ps->getTicketOptions();
                $body = [
                    'visibility' => config('pureservice.visibility.visible'),
                    'statusId' => $ticketOptions['statusId'],
                ];
                if ($updated = $this->ps->apiPATCH($uri.$newTicket['id'], $body, true)):
                    $this->line(Tools::l2().'Sak '.$new_reqno.' satt til Synlig');
                    $this->line('');
                endif;
            endforeach; // $aCases

            // Endre på det originale innsynskravet, slik at det ikke er i veien for senere

            // 1. Opprett et internt notat
            /*
            $message = 'Splittet opp i enkeltsaker av Bitbucket Pipelines';
            if ($internalNote = $this->ps->createInternalNote($message, $ticket['id'])):
                $this->line(Tools::ts().'Internt notat opprettet på det opprinnelige Innsynskravet');
            endif;
            */
            // 2. Løs saken
            $statusId = $this->ps->getEntityId('status', config('pureservice.ticket.status_solved'));
            $ticketTypeId = $this->ps->getEntityId('tickettype', config('innsyn.ticketType_finished'));
            $uri = '/ticket/'.$ticket['id'];
            $solution = '<p>Innsynskravet ble splittet opp av Bitbucket</p><p>Følgende sak(er) ble opprettet:</p>';
            foreach ($this->reqNos_created as $rn):
                $solution .= '<p><a href="/agent/app#/ticket/'.$rn.'">'.$rn.'</a></p>';
            endforeach;
            $body = [
                'statusId' => $statusId,
                'solution' => $solution,
                'ticketTypeId' => $ticketTypeId,
            ];
            if ($updated = $this->ps->apiPATCH($uri, $body, true)):
                $this->line(Tools::ts().'Det opprinnelige innsynskravet har blitt satt til løst.');
            endif;
        endforeach; // $tickets

        $this->line('');
        $time = round(microtime(true) - $this->start, 2);
        $this->info('Ferdig, prosessen tok til sammen '.$time.' sekunder');

        return Command::SUCCESS;
    }

}
