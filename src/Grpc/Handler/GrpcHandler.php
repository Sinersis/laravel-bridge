<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Grpc\Handler;

use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\RoadRunner\GRPC\ServiceWrapper;
use Spiral\RoadRunnerLaravel\Grpc\Context\GrpcCallContext;

final class GrpcHandler implements HandlerInterface
{
    public function __construct(
        private readonly ServiceWrapper $service,
    ) {}

    public function handle(CallContextInterface $context): mixed
    {
        if (!$context instanceof GrpcCallContext) {
            throw new \InvalidArgumentException('Expected GrpcCallContext, got ' . get_class($context));
        }

        return $this->service->invoke(
            $context->getMethod(),
            $context->getGrpcContext(),
            $context->getBody(),
        );
    }
}
