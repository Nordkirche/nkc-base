<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Command;

use Nordkirche\NkcBase\Napi\Health\AlertNotifier;
use Nordkirche\NkcBase\Napi\Health\HealthState;
use Nordkirche\NkcBase\Napi\Health\HealthStatus;
use Nordkirche\NkcBase\Napi\Health\NapiProbe;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks the NAPI, stores the result for the status module and sends alert mails
 */
#[AsCommand(
    name: 'nkc_base:napi_check',
    description: 'Prüft Erreichbarkeit und Antwortzeiten der NAPI (für Scheduler und Monitoring)',
)]
class NapiCheckCommand extends Command
{
    public function __construct(
        private readonly NapiProbe $probe,
        private readonly HealthState $state,
        private readonly AlertNotifier $alertNotifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Exit-Code 0 = in Ordnung oder langsam, 1 = nicht erreichbar, Zugang ungültig oder Konfiguration unvollständig.')
            ->addOption('no-alert', null, InputOption::VALUE_NONE, 'Keine Alarm-Mails verschicken und den Status nicht speichern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $report = $this->probe->run();

        if ($report->missingSettings !== []) {
            $io->error('Konfiguration unvollständig: ' . implode(', ', $report->missingSettings));
        } else {
            $io->table(
                ['Endpunkt', 'Status', 'HTTP', 'Gesamt', 'Server', 'Datensätze', 'Fehler'],
                array_map(static fn($result): array => [
                    $result->endpoint,
                    $result->status->value,
                    $result->httpStatus ?: '-',
                    sprintf('%.0f ms', $result->totalMs),
                    $result->serverMs !== null ? sprintf('%.0f ms', $result->serverMs) : '-',
                    $result->recordCount ?? '-',
                    $result->error,
                ], $report->results)
            );
        }

        if (!$input->getOption('no-alert')) {
            $failures = $this->state->store($report);
            $mail = $this->alertNotifier->handle($report, $failures);
            if ($mail !== null) {
                $io->note($mail === 'alert' ? 'Alarm-Mail verschickt.' : 'Entwarnungs-Mail verschickt.');
            }
        }

        $message = sprintf('NAPI-Status: %s (⌀ %.0f ms)', $report->status->value, $report->getAverageMs());
        match ($report->status) {
            HealthStatus::Ok => $io->success($message),
            HealthStatus::Slow => $io->warning($message),
            default => $io->error($message),
        };

        return in_array($report->status, [HealthStatus::Ok, HealthStatus::Slow], true) ? Command::SUCCESS : Command::FAILURE;
    }
}
