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
    protected $signature = 'innsynskrav:splitt {requestNumber}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Splitter et innsynskrav i Pureservice gitt med requestNumber';
    protected $version = '0.1';

    protected $start;
    protected $reqNo;
    protected $orderId;
    protected $orderDate;

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
        $this->description = $result['linked']['tickets'][0]['description'];
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
        $this->line(Tools::l2().'Innsenders navn: '.$kontaktinfo['navn']);
        $this->line(Tools::l2().'Organisasjon: '.$kontaktinfo['organisasjon']);
        $this->line(Tools::l2().'Land: '.$kontaktinfo['land']);
        $this->line('');

        $this->line(Tools::l1().'Leser inn dokumentlisten');
        $requests = json_decode(json_encode($bestilling->dokumenter->dokument), true);
        unset($bestilling);
        $this->line(Tools::l2().'Det kreves innsyn i '.count($requests).' dokumenter');

        $this->line('');
        $this->line(Tools::l1().'Henter eller registrerer sluttbruker i Pureservice');
        $company = false;
        if ($kontaktinfo['organisasjon'] != null):
            if ($company = $this->ps->findCompany($kontaktinfo['organisasjon'])):
                $this->line(Tools::l2().'Organisasjon finnes i Pureservice');
            else:
                $this->line(Tools::l2().'Legger til organisasjon');
                $company = $this->ps->addCompany($kontaktinfo['organisasjon']);
            endif;
        endif;
        if ($user = $this->ps->findUser($kontaktinfo['e-post'])):
        else:
            $user = $this->ps->addCompanyUser($company, $kontaktinfo['e-post'], $kontaktinfo['navn']);
        endif;
        if (!$user):
            $this->error('Bruker finnes ikke i Pureservice. Avbryter...');
            return Command::FAILURE;
        endif;

        $this->line('');
        $this->line(Tools::ts().'Oppretter innsynskrav for hvert dokument');
        $lineno = 0;
        foreach($requests as $request):
            $uri = '/ticket/';
            $lineno++;
            $description = Str::before($this->description, 'Dokumenter:');
            $description .= 'Saksnr: '.$request['saksnr'].Str::between($this->description, 'Saksnr: '.$request['saksnr'], '----');
            $ticket = [
                'subject' => 'Innsynskrav for journalpost '.$request['saksnr'].'-'.$request['dokumentnr'],
                'description' => $description,
            ];
            $body = ['tickets' => [$ticket]];
        endforeach;

        return Command::SUCCESS;
    }
}
