<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Napi\Health;

enum HealthStatus: string
{
    case Ok = 'ok';
    case Slow = 'slow';
    case Auth = 'auth';
    case Down = 'down';
    case Config = 'config';

    /**
     * Higher = worse; the overall status of a report is the worst single status
     */
    public function severity(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Slow => 1,
            self::Auth => 3,
            self::Down => 4,
            self::Config => 2,
        };
    }

    /**
     * Severity as used by the TYPO3 backend (callouts, badges)
     */
    public function state(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Slow, self::Config => 'warning',
            self::Auth, self::Down => 'danger',
        };
    }

    public function isProblem(): bool
    {
        return $this !== self::Ok;
    }

    public static function worst(self ...$states): self
    {
        $worst = self::Ok;
        foreach ($states as $state) {
            if ($state->severity() > $worst->severity()) {
                $worst = $state;
            }
        }
        return $worst;
    }
}
