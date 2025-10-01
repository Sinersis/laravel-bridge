<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Tests\Unit\Grpc\Handler;

use PHPUnit\Framework\TestCase;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\ServiceWrapper;
use Spiral\RoadRunnerLaravel\Grpc\Context\GrpcCallContext;
use Spiral\RoadRunnerLaravel\Grpc\Handler\GrpcHandler;
use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunnerLaravel\Tests\Support\TestServiceInterface;

final class GrpcHandlerUnitTest extends TestCase
{
    public function testHandleWithInvalidContextThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected GrpcCallContext, got MockObject_CallContextInterface_');

        // Create a mock that implements both interfaces
        $service = $this->getMockBuilder(TestServiceInterface::class)
            ->getMock();

        $wrapper = new ServiceWrapper(
            new Invoker(),
            TestServiceInterface::class,
            $service
        );

        // Create the handler
        $handler = new GrpcHandler($wrapper);

        // Create an invalid context (not a GrpcCallContext)
        $invalidContext = $this->createMock(CallContextInterface::class);

        // This should throw the expected exception
        $handler->handle($invalidContext);
    }

    public function testGrpcCallContextIntegrationWithHandler(): void
    {
        $grpcContext = $this->createMock(ContextInterface::class);
        $callContext = new GrpcCallContext('TestService.TestMethod', $grpcContext, 'test-body');

        // Verify the context has correct data for handler processing
        $this->assertEquals('TestService.TestMethod', $callContext->getMethod());
        $this->assertEquals('test-body', $callContext->getBody());
        $this->assertSame($grpcContext, $callContext->getGrpcContext());

        // Verify arguments are correctly structured
        $arguments = $callContext->getArguments();
        $this->assertEquals([
            'method' => 'TestService.TestMethod',
            'context' => $grpcContext,
            'body' => 'test-body',
        ], $arguments);
    }
}
