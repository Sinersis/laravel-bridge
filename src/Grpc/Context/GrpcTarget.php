<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Grpc\Context;

use Spiral\Interceptors\Context\TargetInterface;

/**
 * @implements TargetInterface<null>
 */
final readonly class GrpcTarget implements TargetInterface
{
    /** @var list<string> */
    private array $path;

    /**
     * @param list<string> $path
     */
    public function __construct(
        private string $method,
        array          $path = [],
        private string $delimiter = '::',
    ) {
        $this->path = empty($path) ? [$method] : $path;
    }

    public function getPath(): array
    {
        return $this->path;
    }

    public function withPath(array $path, ?string $delimiter = null): static
    {
        return new self($this->method, $path, $delimiter ?? $this->delimiter);
    }

    public function getReflection(): ?\ReflectionFunctionAbstract
    {
        return null;
    }

    public function getObject(): ?object
    {
        return null;
    }

    public function getCallable(): callable|array|null
    {
        return null;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function __toString(): string
    {
        return implode($this->delimiter, $this->path);
    }
}
