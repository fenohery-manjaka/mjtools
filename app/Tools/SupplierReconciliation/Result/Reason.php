<?php

namespace App\Tools\SupplierReconciliation\Result;

/**
 * One human-readable justification produced by the engine.
 */
final readonly class Reason
{
    public function __construct(
        public string $code,
        public Polarity $polarity,
        public string $message,
    ) {}

    public static function agrees(string $code, string $message): self
    {
        return new self($code, Polarity::Agrees, $message);
    }

    public static function partial(string $code, string $message): self
    {
        return new self($code, Polarity::Partial, $message);
    }

    public static function differs(string $code, string $message): self
    {
        return new self($code, Polarity::Differs, $message);
    }

    public static function info(string $code, string $message): self
    {
        return new self($code, Polarity::Info, $message);
    }

    /**
     * @return array{code: string, polarity: string, message: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'polarity' => $this->polarity->value, 'message' => $this->message];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self((string) $data['code'], Polarity::from((string) $data['polarity']), (string) $data['message']);
    }
}
