<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Grpc\Context;

use Spiral\Interceptors\Context\AttributedTrait;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\Context\TargetInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;

final class GrpcCallContext implements CallContextInterface
{
    use AttributedTrait;

    public function __construct(
        private readonly string $method,
        private readonly ContextInterface $grpcContext,
        private readonly string $body,
        array $attributes = [],
    ) {
        $this->attributes = $attributes;
    }

    public function getTarget(): TargetInterface
    {
        return new GrpcTarget($this->method);
    }

    public function getArguments(): array
    {
        return [
            'method' => $this->method,
            'context' => $this->grpcContext,
            'body' => $this->body,
        ];
    }

    public function withTarget(TargetInterface $target): static
    {
        $clone = clone $this;
        return $clone;
    }

    public function withArguments(array $arguments): static
    {
        $method = $arguments['method'] ?? $this->method;
        $context = $arguments['context'] ?? $this->grpcContext;
        $body = $arguments['body'] ?? $this->body;

        return new self($method, $context, $body, $this->attributes);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getGrpcContext(): ContextInterface
    {
        return $this->grpcContext;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
