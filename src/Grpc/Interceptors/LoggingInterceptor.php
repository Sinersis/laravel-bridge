<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Grpc\Interceptors;

use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
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
        $method = (string) $context->getTarget();

        // Only log gRPC calls (they have a method in the format 'Service.Method')
        if (str_contains($method, '.')) {
            $this->logger->info("gRPC call started: {$method}");

            try {
                $response = $handler->handle($context);
                $this->logger->info("gRPC call completed: {$method}");
                return $response;
            } catch (\Throwable $e) {
                $this->logger->error("gRPC call failed: {$method}", ['error' => $e]);
                throw $e;
            }
        }

        return $handler->handle($context);
    }
}
