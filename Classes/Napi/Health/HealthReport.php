<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Napi\Health;

/**
 * Result of a status check over all endpoints
 */
final readonly class HealthReport implements \JsonSerializable
{
    /**
     * @param ProbeResult[] $results
     * @param string[] $missingSettings
     */
    public function __construct(
        public HealthStatus $status,
        public int $checkedAt,
        public array $results,
        public array $missingSettings = [],
        public string $baseUri = '',
    ) {}

    /**
     * @param ProbeResult[] $results
     */
    public static function fromResults(array $results, string $baseUri): self
    {
        return new self(
            HealthStatus::worst(...array_map(static fn(ProbeResult $result): HealthStatus => $result->status, $results)),
            time(),
            $results,
            [],
            $baseUri
        );
    }

    /**
     * @param string[] $missingSettings
     */
    public static function misconfigured(array $missingSettings): self
    {
        return new self(HealthStatus::Config, time(), [], $missingSettings);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            HealthStatus::tryFrom((string)($data['status'] ?? '')) ?? HealthStatus::Down,
            (int)($data['checkedAt'] ?? 0),
            array_map(ProbeResult::fromArray(...), (array)($data['results'] ?? [])),
            array_map('strval', (array)($data['missingSettings'] ?? [])),
            (string)($data['baseUri'] ?? ''),
        );
    }

    public function getAverageMs(): float
    {
        $times = array_map(static fn(ProbeResult $result): float => $result->totalMs, $this->results);
        return $times === [] ? 0.0 : array_sum($times) / count($times);
    }

    public function getMaxMs(): float
    {
        return max([0.0, ...array_map(static fn(ProbeResult $result): float => $result->totalMs, $this->results)]);
    }

    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status->value,
            'state' => $this->status->state(),
            'checkedAt' => $this->checkedAt,
            'averageMs' => round($this->getAverageMs(), 1),
            'maxMs' => round($this->getMaxMs(), 1),
            'results' => $this->results,
            'missingSettings' => $this->missingSettings,
            'baseUri' => $this->baseUri,
        ];
    }
}
