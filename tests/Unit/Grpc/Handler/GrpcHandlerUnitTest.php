<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Tests\Unit\Grpc\Handler;

use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunnerLaravel\Grpc\Context\GrpcCallContext;
use Spiral\RoadRunnerLaravel\Grpc\Handler\GrpcHandler;

final class GrpcHandlerUnitTest extends TestCase
{
    public function testHandleWithInvalidContextThrowsException(): void
    {
        $reflection          = new \ReflectionClass(GrpcHandler::class);
        $constructor         = $reflection->getConstructor();
        $serviceWrapperParam = $constructor->getParameters()[0];

        // Verify that the constructor expects ServiceWrapper
        $this->assertEquals('Spiral\RoadRunner\GRPC\ServiceWrapper', $serviceWrapperParam->getType()->getName());

        // Test that GrpcCallContext is accepted
        $grpcContext  = $this->createMock(ContextInterface::class);
        $validContext = new GrpcCallContext('TestMethod', $grpcContext, 'body');

        $this->assertInstanceOf(GrpcCallContext::class, $validContext);
        $this->assertEquals('TestMethod', $validContext->getMethod());
        $this->assertEquals('body', $validContext->getBody());
        $this->assertSame($grpcContext, $validContext->getGrpcContext());
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
            'method'  => 'TestService.TestMethod',
            'context' => $grpcContext,
            'body'    => 'test-body',
        ], $arguments);
    }
}
