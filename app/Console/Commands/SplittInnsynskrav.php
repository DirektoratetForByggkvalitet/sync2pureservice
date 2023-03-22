<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Pureservice, Tools};
use Illuminate\Support\{Str, Arr};


class SplittInnsynskrav extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:splittInnsynskrav {requestNumber}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Splitter et innsynskrav i Pureservice gitt med requestNumber';
    protected $version = '0.1';

    protected $start;
    protected $reqNo;
    protected $reqNos_created = [];
    protected $ticketId;
    protected $orderId;
    protected $orderDate;
    protected $msg;
    protected $introText;

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

        $this->info(Tools::ts().'Starter opp...');
        $this->reqNo = $this->argument('requestNumber');
        $this->ps = new Pureservice();
        $this->ps->setTicketOptions('innsyn');
        $this->line(Tools::l1().'Henter vedlegg til sak '.$this->reqNo);
        $result = $this->ps->apiGet('/attachment/?include=ticket&filter=ticket.requestNumber == '.$this->reqNo);
        $attachments = $result['attachments'];
        $this->msg = $result['linked']['tickets'][0]['description'];
        $this->ticketId = $result['linked']['tickets'][0]['id'];
        unset($result);
        if (count($attachments) > 0):
            foreach ($attachments as $a):
                if ($a['fileName'] == 'order.xml'):
                    $response = $this->ps->apiGet('/attachment/download/'.$a['id'], true);
                    $bestilling = simplexml_load_string($response->getBody()->getContents());
                    unset($response);
                endif;
            endforeach;
        endif;
        if (!isset($bestilling)):
            $this->error('Kunne ikke finne order.xml. Avbryter...');
            return Command::FAILURE;
        endif;
        $this->line('');

        $this->line(Tools::l1().'Leser inn data fra innsynskravet');
        $this->orderId = $bestilling->id->__toString();
        $this->line(Tools::l2().'Bestillings-ID: '.$this->orderId);
        $this->orderDate = $bestilling->bestillingsdato->__toString();
        $this->line(Tools::l2().'Bestillingsdato: '.$this->orderDate);

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
        $requests = json_decode(json_encode($bestilling->dokumenter), true)['dokument'];
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
            $this->line(Tools::l2().'Fant brukeren '.$user['fullName']);
        else:
            $user = $this->ps->addCompanyUser($company, $kontaktinfo['e-post'], $kontaktinfo['navn']);
        endif;
        if (!$user):
            $this->error('Bruker finnes ikke i Pureservice. Avbryter...');
            return Command::FAILURE;
        endif;

        $this->line('');
        $this->line(Tools::ts().'Oppretter ett innsynskrav for hver unike sak');
        // Rydder i meldingsteksten. Tar bort linjeskift.
        $this->msg = preg_replace('/\\n/', '', $this->msg);
        // Setter introteksten
        $this->introText = preg_replace('/:/', ': ', preg_replace('/<br>/', '<br />'.PHP_EOL, Str::before($this->msg, 'Dokumenter:')));
        $this->introText = preg_replace('/bestilt av: /', 'bestilt av:<br />'.PHP_EOL, $this->introText);
        $docs = Str::between($this->msg, 'Dokumenter:<br>', '--------------------------------------');
        $aOrderLines = explode('--------------------------------------<br>', $docs);
        $aDocs = [];
        // Sorterer etter saksnr
        $requests = Arr::sort($requests, function (array $value) {
            return $value['saksnr'];
        });
        foreach ($aOrderLines as $orderLine):
            $head = explode(' | ', Str::before($orderLine, '<br>'));
            $body = Str::after($orderLine, '<br>');
            $aBody = [];
            foreach ($head as $l):
                $aBody[trim(preg_replace('/(.*): (.*)/', '\1', $l))] = preg_replace('/(.*): (.*)/', '\2', $l);
            endforeach;
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
        // dd($aDocs);
        $saksnr = '';
        foreach($requests as $request):
            if ($saksnr !== $request['saksnr']):
                $saksnr = $request['saksnr'];
                $uri = '/ticket/';
                $subject = 'Innsynskrav for sak '.$request['saksnr'];
                $this->line(Tools::l2().'Emne: "'.$subject.'".');
                $aRequestOrders = $aDocs[$request['saksnr']];

                $description = $this->introText . PHP_EOL;
                $firstline = true;
                $orderCount = count($aRequestOrders);
                foreach ($aRequestOrders as $doc):
                    if ($firstline):
                        $firstline = false;
                        $description .= '<p>Innsynskrav for '.$orderCount.' ';
                        $description .= $orderCount > 1 ? 'dokumenter': 'dokument';
                        $description .= ' i sak '.$doc['Saksnr'].'<br/>'.PHP_EOL.'<strong>'.$doc['Sak'].'</strong></p>'.PHP_EOL;
                        $description .= '</strong></p>';
                        $description .= PHP_EOL;
                    endif;
                    $description .= '<p>'.PHP_EOL;
                    $description .= 'Dokumentnr: '.$doc['Dok nr.'].'<br />'.PHP_EOL;
                    $description .= 'Navn: '.$doc['Dokument'].'<br />'.PHP_EOL;
                    $description .= 'Sekvensnr: '.$doc['Sekvensnr.'].'<br />'.PHP_EOL;
                    $description .= 'Dokumentdato: '.$doc['Dok.dato'].'<br />'.PHP_EOL;
                    $description .= 'Journaldato: '.$doc['Journaldato'].'<br />'.PHP_EOL;
                    $description .= 'Saksbehandler: '.$doc['Saksbehandler'].'<br />'.PHP_EOL;
                    $description .= 'Enhet: '.$doc['Enhet'].'<br />'.PHP_EOL;
                    $description .= '</p>'.PHP_EOL;
                endforeach;

                /*
                $description .= '<p>'.implode('</p><p>', Arr::where($aDocs, function (string $value, int $key) use ($request) : bool {
                    return Str::startsWith($value, 'Saksnr: '.$request['saksnr']);
                }));
                */
                $description .= '<p>eInnsyn-ID: '.$this->orderId.'</p>';
                //dd($description);
                if ($ticket = $this->ps->createTicket($subject, $description, $user['id'], config('pureservice.visibility.invisible'))):
                    $this->line(Tools::l2().'Opprettet saken "'.$ticket['subject'].'" med saksnr '.$ticket['requestNumber']);
                    $this->reqNos_created[] = $ticket['requestNumber'];
                endif;
                // Endrer sakens synlighet til synlig
                $ticketOptions = $this->ps->getTicketOptions();
                $body = [
                    'visibility' => config('pureservice.visibility.visible'),
                    'statusId' => $ticketOptions['statusId'],
                ];
                if ($updated = $this->ps->apiPATCH($uri.$ticket['id'], $body, true)):
                    $this->line(Tools::l2().'Sak '.$ticket['requestNumber'].' satt til Synlig');
                    $this->line('');
                endif;
            else:
                continue;
            endif;
        endforeach;
        // Endre på det originale innsynskravet, slik at det ikke er i veien for senere

        // 1. Opprett et internt notat
        /*
        $message = 'Splittet opp i enkeltsaker av Bitbucket Pipelines';
        if ($internalNote = $this->ps->createInternalNote($message, $this->ticketId)):
            $this->line(Tools::ts().'Internt notat opprettet på det opprinnelige Innsynskravet');
        endif;
        */
        // 2. Løs saken
        $statusId = $this->ps->getEntityId('status', config('pureservice.ticket.status_solved'));
        $ticketTypeId = $this->ps->getEntityId('tickettype', config('innsyn.ticketType_finished'));
        $uri = '/ticket/'.$this->ticketId;
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

        $this->line('');
        $time = round(microtime(true) - $this->start, 2);
        $this->info('Ferdig, prosessen tok til sammen '.$time.' sekunder');

        return Command::SUCCESS;
    }

}
