<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Napi\Health;

use Nordkirche\NkcBase\Service\NapiSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;

/**
 * Sends one mail when the NAPI fails repeatedly, and one when it is back
 */
class AlertNotifier
{
    public function __construct(
        private readonly NapiSettings $settings,
        private readonly HealthState $state,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return string|null "alert" or "recovery" if a mail was sent
     */
    public function handle(HealthReport $report, int $failuresInRow): ?string
    {
        $recipients = $this->settings->getAlertRecipients();
        if ($recipients === []) {
            return null;
        }

        $type = null;
        if ($report->status->isProblem() && $failuresInRow >= $this->settings->getAlertAfter() && !$this->state->isAlertOpen()) {
            $type = 'alert';
        } elseif (!$report->status->isProblem() && $this->state->isAlertOpen()) {
            $type = 'recovery';
        }
        if ($type === null) {
            return null;
        }

        try {
            $this->send($type, $report, $failuresInRow, $recipients);
            $this->state->setAlertOpen($type === 'alert');
            return $type;
        } catch (\Throwable $e) {
            $this->logger->error('NAPI status mail could not be sent: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param string[] $recipients
     */
    private function send(string $type, HealthReport $report, int $failuresInRow, array $recipients): void
    {
        $siteName = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ?? 'TYPO3');
        $subject = $type === 'alert'
            ? sprintf('[%s] NAPI-Störung: %s', $siteName, $report->status->value)
            : sprintf('[%s] NAPI wieder erreichbar', $siteName);

        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $email
            ->to(...array_map(static fn(string $address): Address => new Address($address), $recipients))
            ->from(new Address(MailUtility::getSystemFromAddress(), MailUtility::getSystemFromName() ?? $siteName))
            ->subject($subject)
            ->format(FluidEmail::FORMAT_BOTH)
            ->setTemplate('NapiAlert')
            ->assignMultiple([
                'type' => $type,
                'headline' => $subject,
                'report' => $report,
                'reportData' => json_decode((string)json_encode($report), true),
                'failuresInRow' => $failuresInRow,
                'siteName' => $siteName,
            ]);
        $this->mailer->send($email);
    }
}
