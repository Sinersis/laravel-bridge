<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Grpc\Interceptors;

use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\RoadRunnerLaravel\Grpc\Context\GrpcCallContext;
use Psr\Log\LoggerInterface;

class LoggingInterceptor implements InterceptorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws \Throwable
     */
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        if (!$context instanceof GrpcCallContext) {
            return $handler->handle($context);
        }

        $method = $context->getMethod();
        $this->logger->info("gRPC call started: {$method}");

        try {
            $response = $handler->handle($context);
            $this->logger->info("gRPC call completed: {$method}");
            return $response;
        } catch (\Throwable $e) {
            $this->logger->error("gRPC call failed: {$method}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
