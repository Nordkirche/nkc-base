<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Napi\Health;

/**
 * Result of one request of the status check
 */
final readonly class ProbeResult implements \JsonSerializable
{
    /**
     * @param array{dns: float, connect: float, tls: float, wait: float, transfer: float} $phases Durations in ms
     */
    public function __construct(
        public string $endpoint,
        public HealthStatus $status,
        public int $httpStatus,
        public float $totalMs,
        public array $phases,
        public ?float $serverMs,
        public ?int $recordCount,
        public int $bytes,
        public string $ip,
        public string $error,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['endpoint'] ?? ''),
            HealthStatus::tryFrom((string)($data['status'] ?? '')) ?? HealthStatus::Down,
            (int)($data['httpStatus'] ?? 0),
            (float)($data['totalMs'] ?? 0),
            array_map('floatval', (array)($data['phases'] ?? [])) + ['dns' => 0.0, 'connect' => 0.0, 'tls' => 0.0, 'wait' => 0.0, 'transfer' => 0.0],
            isset($data['serverMs']) ? (float)$data['serverMs'] : null,
            isset($data['recordCount']) ? (int)$data['recordCount'] : null,
            (int)($data['bytes'] ?? 0),
            (string)($data['ip'] ?? ''),
            (string)($data['error'] ?? ''),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'status' => $this->status->value,
            'state' => $this->status->state(),
            'httpStatus' => $this->httpStatus,
            'totalMs' => round($this->totalMs, 1),
            'phases' => array_map(static fn(float $value): float => round($value, 1), $this->phases),
            'serverMs' => $this->serverMs !== null ? round($this->serverMs, 1) : null,
            'recordCount' => $this->recordCount,
            'bytes' => $this->bytes,
            'ip' => $this->ip,
            'error' => $this->error,
        ];
    }
}
