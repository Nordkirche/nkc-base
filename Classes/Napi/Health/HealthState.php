<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Napi\Health;

use TYPO3\CMS\Core\Registry;

/**
 * Last automatic status check and alert state (no history)
 */
class HealthState
{
    private const NAMESPACE = 'nkc_base.napi';

    public function __construct(private readonly Registry $registry) {}

    public function getLastReport(): ?HealthReport
    {
        $data = $this->registry->get(self::NAMESPACE, 'lastReport');
        return is_array($data) ? HealthReport::fromArray($data) : null;
    }

    public function getFailuresInRow(): int
    {
        return (int)$this->registry->get(self::NAMESPACE, 'failuresInRow', 0);
    }

    public function isAlertOpen(): bool
    {
        return (bool)$this->registry->get(self::NAMESPACE, 'alertOpen', false);
    }

    /**
     * Stores the report and returns the number of failed checks in a row
     */
    public function store(HealthReport $report): int
    {
        $this->registry->set(self::NAMESPACE, 'lastReport', json_decode((string)json_encode($report), true));
        $failures = $report->status->isProblem() ? $this->getFailuresInRow() + 1 : 0;
        $this->registry->set(self::NAMESPACE, 'failuresInRow', $failures);
        return $failures;
    }

    public function setAlertOpen(bool $open): void
    {
        $this->registry->set(self::NAMESPACE, 'alertOpen', $open);
    }
}
