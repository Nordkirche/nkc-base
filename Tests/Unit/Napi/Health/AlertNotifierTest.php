<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Napi\Health;

use Nordkirche\NkcBase\Napi\Health\AlertNotifier;
use Nordkirche\NkcBase\Napi\Health\HealthReport;
use Nordkirche\NkcBase\Napi\Health\HealthState;
use Nordkirche\NkcBase\Napi\Health\HealthStatus;
use Nordkirche\NkcBase\Tests\Unit\Fixtures\NapiSettingsFixture;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AlertNotifierTest extends UnitTestCase
{
    use NapiSettingsFixture;

    protected bool $resetSingletonInstances = true;

    private HealthState $state;

    /**
     * @var FluidEmail[]
     */
    private array $sentMails = [];

    private ?\Throwable $mailException = null;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'typo3@example.org';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'TYPO3';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = 'Nordkirche';
        $this->state = new HealthState($this->createRegistry());
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function theStateCountsFailuresInRowAndResetsOnSuccess(): void
    {
        self::assertSame(1, $this->state->store($this->report(HealthStatus::Down)));
        self::assertSame(2, $this->state->store($this->report(HealthStatus::Slow)));
        self::assertSame(0, $this->state->store($this->report(HealthStatus::Ok)));
        self::assertSame(1, $this->state->store($this->report(HealthStatus::Auth)));
        self::assertSame(HealthStatus::Auth, $this->state->getLastReport()?->status);
    }

    #[Test]
    public function noAlertBeforeTheConfiguredNumberOfFailures(): void
    {
        self::assertNull($this->createNotifier()->handle($this->report(HealthStatus::Down), 2));
        self::assertSame([], $this->sentMails);
        self::assertFalse($this->state->isAlertOpen());
    }

    #[Test]
    public function oneAlertIsSentAndThenOneRecovery(): void
    {
        $notifier = $this->createNotifier();

        self::assertSame('alert', $notifier->handle($this->report(HealthStatus::Down), 3));
        self::assertTrue($this->state->isAlertOpen());
        self::assertNull($notifier->handle($this->report(HealthStatus::Down), 4), 'No second alert while the problem lasts');
        self::assertSame('recovery', $notifier->handle($this->report(HealthStatus::Ok), 0));
        self::assertFalse($this->state->isAlertOpen());
        self::assertNull($notifier->handle($this->report(HealthStatus::Ok), 0));

        self::assertCount(2, $this->sentMails);
        self::assertSame('[Nordkirche] NAPI-Störung: down', $this->sentMails[0]->getSubject());
        self::assertSame('[Nordkirche] NAPI wieder erreichbar', $this->sentMails[1]->getSubject());
        self::assertSame('NapiAlert', $this->sentMails[0]->templateName);
        self::assertSame('alert', $this->sentMails[0]->variables['type']);
        self::assertSame(3, $this->sentMails[0]->variables['failuresInRow']);
        self::assertSame('down', $this->sentMails[0]->variables['reportData']['status']);
        self::assertSame(['admin@example.org', 'team@example.org'], array_map(
            static fn($address): string => $address->getAddress(),
            $this->sentMails[0]->getTo()
        ));
    }

    #[Test]
    public function noMailWithoutRecipients(): void
    {
        $notifier = $this->createNotifier('');

        self::assertNull($notifier->handle($this->report(HealthStatus::Down), 10));
        self::assertSame([], $this->sentMails);
    }

    #[Test]
    public function aFailedMailKeepsTheAlertClosedToRetryNextTime(): void
    {
        $this->mailException = new \RuntimeException('SMTP not available');

        self::assertNull($this->createNotifier()->handle($this->report(HealthStatus::Down), 3));
        self::assertFalse($this->state->isAlertOpen());
    }

    private function createNotifier(string $recipients = 'admin@example.org,team@example.org'): AlertNotifier
    {
        $settings = $this->createNapiSettings(['NDK_HEALTH_ALERT_RECIPIENTS' => $recipients, 'NDK_HEALTH_ALERT_AFTER' => '3']);
        // FluidEmail needs a rendering environment; the test only checks what is sent
        for ($i = 0; $i < 5; $i++) {
            GeneralUtility::addInstance(FluidEmail::class, new class () extends FluidEmail {
                public array $variables = [];
                public string $templateName = '';

                public function __construct()
                {
                    // Plain message without the Fluid view of FluidEmail
                    Message::__construct();
                }

                public function format(string $format): static
                {
                    return $this;
                }

                public function setTemplate(string $templateName): static
                {
                    $this->templateName = $templateName;
                    return $this;
                }

                public function assignMultiple(array $values): static
                {
                    $this->variables = $values + $this->variables;
                    return $this;
                }
            });
        }
        return new AlertNotifier($settings, $this->state, $this->createMailer(), new NullLogger());
    }

    private function createMailer(): MailerInterface
    {
        return new class ($this) implements MailerInterface {
            public function __construct(private readonly AlertNotifierTest $test) {}

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->test->recordMail($message);
            }

            public function getSentMessage(): ?SentMessage
            {
                return null;
            }

            public function getTransport(): \Symfony\Component\Mailer\Transport\TransportInterface
            {
                throw new \BadMethodCallException('Not used', 1760000000);
            }

            public function getRealTransport(): \Symfony\Component\Mailer\Transport\TransportInterface
            {
                throw new \BadMethodCallException('Not used', 1760000001);
            }
        };
    }

    /**
     * @internal Called by the test mailer
     */
    public function recordMail(RawMessage $message): void
    {
        if ($this->mailException !== null) {
            throw $this->mailException;
        }
        self::assertInstanceOf(FluidEmail::class, $message);
        $this->sentMails[] = $message;
    }

    private function report(HealthStatus $status): HealthReport
    {
        return new HealthReport($status, time(), []);
    }

    private function createRegistry(): Registry
    {
        return new class () extends Registry {
            private array $values = [];

            public function __construct() {}

            public function get($namespace, $key, $defaultValue = null)
            {
                return $this->values[$namespace][$key] ?? $defaultValue;
            }

            public function set($namespace, $key, $value)
            {
                $this->values[$namespace][$key] = $value;
            }
        };
    }
}
