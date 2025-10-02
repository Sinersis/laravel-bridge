<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Tests\Unit\Grpc\Interceptors;

use PHPUnit\Framework\TestCase;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\HandlerInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunnerLaravel\Grpc\Interceptors\LoggingInterceptor;

final class LoggingInterceptorSimpleTest extends TestCase
{
    public function testInterceptWithNonGrpcCallContext(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $handler = $this->createMock(HandlerInterface::class);

        // Create a non-gRPC context (no dot in the target path)
        $target = Target::fromPathString('non_grpc_method');
        $nonGrpcContext = new CallContext($target);

        $expectedResponse = 'non-grpc-response';

        // Logger should not be called for non-gRPC contexts
        $logger->expects($this->never())->method('info');
        $logger->expects($this->never())->method('error');

        // Handler should be called directly
        $handler->expects($this->once())
            ->method('handle')
            ->with($nonGrpcContext)
            ->willReturn($expectedResponse);

        $interceptor = new LoggingInterceptor($logger);
        $result = $interceptor->intercept($nonGrpcContext, $handler);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testInterceptWithGrpcCallContextSuccess(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $handler = $this->createMock(HandlerInterface::class);
        $grpcContext = $this->createMock(ContextInterface::class);

        $method = 'TestService.TestMethod';
        $expectedResponse = 'test-response';

        $target = Target::fromPathString($method);
        $callContext = new CallContext($target, [
            'method' => $method,
            'context' => $grpcContext,
            'body' => 'test-body',
        ]);

        // Expect info logs for start and completion
        $logger->expects($this->exactly(2))
            ->method('info');

        $handler->expects($this->once())
            ->method('handle')
            ->with($callContext)
            ->willReturn($expectedResponse);

        $interceptor = new LoggingInterceptor($logger);
        $result = $interceptor->intercept($callContext, $handler);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testInterceptWithGrpcCallContextException(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $handler = $this->createMock(HandlerInterface::class);
        $grpcContext = $this->createMock(ContextInterface::class);

        $method = 'TestService.TestMethod';
        $exception = new \RuntimeException('Test error');

        $target = Target::fromPathString($method);
        $callContext = new CallContext($target, [
            'method' => $method,
            'context' => $grpcContext,
            'body' => 'test-body',
        ]);

        // Expect info log for start and error log for failure
        $logger->expects($this->once())
            ->method('info')
            ->with("gRPC call started: {$method}");

        $logger->expects($this->once())
            ->method('error')
            ->with("gRPC call failed: {$method}", ['error' => 'Test error']);

        $handler->expects($this->once())
            ->method('handle')
            ->with($callContext)
            ->willThrowException($exception);

        $interceptor = new LoggingInterceptor($logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        $interceptor->intercept($callContext, $handler);
    }
}
