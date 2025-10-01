<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Tests\Unit\Grpc\Context;

use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunnerLaravel\Grpc\Context\GrpcCallContext;
use Spiral\RoadRunnerLaravel\Grpc\Context\GrpcTarget;

final class GrpcCallContextTest extends TestCase
{
    private ContextInterface $grpcContext;

    public function testConstructor(): void
    {
        $method = 'TestService.TestMethod';
        $body = 'test-body';
        $attributes = ['key' => 'value'];

        $context = new GrpcCallContext($method, $this->grpcContext, $body, $attributes);

        $this->assertEquals($method, $context->getMethod());
        $this->assertSame($this->grpcContext, $context->getGrpcContext());
        $this->assertEquals($body, $context->getBody());
        $this->assertEquals('value', $context->getAttribute('key'));
    }

    public function testGetTarget(): void
    {
        $method = 'TestService.TestMethod';
        $context = new GrpcCallContext($method, $this->grpcContext, 'body');

        $target = $context->getTarget();

        $this->assertInstanceOf(GrpcTarget::class, $target);
        $this->assertEquals($method, $target->getMethod());
        $this->assertEquals([$method], $target->getPath());
    }

    public function testGetArguments(): void
    {
        $method = 'TestService.TestMethod';
        $body = 'test-body';
        $context = new GrpcCallContext($method, $this->grpcContext, $body);

        $arguments = $context->getArguments();

        $this->assertEquals([
            'method' => $method,
            'context' => $this->grpcContext,
            'body' => $body,
        ], $arguments);
    }

    public function testWithArguments(): void
    {
        $originalMethod = 'TestService.TestMethod';
        $originalBody = 'original-body';
        $context = new GrpcCallContext($originalMethod, $this->grpcContext, $originalBody);

        $newMethod = 'NewService.NewMethod';
        $newBody = 'new-body';
        $newGrpcContext = $this->createMock(ContextInterface::class);

        $newContext = $context->withArguments([
            'method' => $newMethod,
            'body' => $newBody,
            'context' => $newGrpcContext,
        ]);

        $this->assertNotSame($context, $newContext);
        $this->assertEquals($originalMethod, $context->getMethod());
        $this->assertEquals($originalBody, $context->getBody());
        $this->assertSame($this->grpcContext, $context->getGrpcContext());

        $this->assertEquals($newMethod, $newContext->getMethod());
        $this->assertEquals($newBody, $newContext->getBody());
        $this->assertSame($newGrpcContext, $newContext->getGrpcContext());
    }

    public function testWithArgumentsPartial(): void
    {
        $originalMethod = 'TestService.TestMethod';
        $originalBody = 'original-body';
        $context = new GrpcCallContext($originalMethod, $this->grpcContext, $originalBody);

        $newMethod = 'NewService.NewMethod';

        $newContext = $context->withArguments([
            'method' => $newMethod,
        ]);

        $this->assertEquals($newMethod, $newContext->getMethod());
        $this->assertEquals($originalBody, $newContext->getBody());
        $this->assertSame($this->grpcContext, $newContext->getGrpcContext());
    }

    public function testWithTarget(): void
    {
        $context = new GrpcCallContext('TestService.TestMethod', $this->grpcContext, 'body');
        $newTarget = new GrpcTarget('NewService.NewMethod');

        $newContext = $context->withTarget($newTarget);

        // withTarget doesn't change anything for GrpcCallContext as target is derived from method
        $this->assertNotSame($context, $newContext);
        $this->assertEquals($context->getMethod(), $newContext->getMethod());
    }

    public function testAttributeManagement(): void
    {
        $context = new GrpcCallContext('TestService.TestMethod', $this->grpcContext, 'body');

        // Test setting attribute
        $contextWithAttr = $context->withAttribute('test', 'value');
        $this->assertNotSame($context, $contextWithAttr);
        $this->assertEquals('value', $contextWithAttr->getAttribute('test'));
        $this->assertNull($context->getAttribute('test'));

        // Test getting non-existent attribute with default
        $this->assertEquals('default', $context->getAttribute('nonexistent', 'default'));

        // Test getting all attributes
        $contextWithMultipleAttrs = $context
            ->withAttribute('attr1', 'value1')
            ->withAttribute('attr2', 'value2');

        $this->assertEquals([
            'attr1' => 'value1',
            'attr2' => 'value2',
        ], $contextWithMultipleAttrs->getAttributes());

        // Test removing attribute
        $contextWithoutAttr = $contextWithMultipleAttrs->withoutAttribute('attr1');
        $this->assertNull($contextWithoutAttr->getAttribute('attr1'));
        $this->assertEquals('value2', $contextWithoutAttr->getAttribute('attr2'));
    }

    protected function setUp(): void
    {
        $this->grpcContext = $this->createMock(ContextInterface::class);
    }
}
