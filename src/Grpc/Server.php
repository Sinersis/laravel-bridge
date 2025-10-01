<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Grpc;

use Google\Protobuf\Any;
use Google\Rpc\Status;
use Illuminate\Contracts\Container\BindingResolutionException;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\RoadRunner\GRPC\Context;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\GRPCException;
use Spiral\RoadRunner\GRPC\Exception\GRPCExceptionInterface;
use Spiral\RoadRunner\GRPC\Exception\NotFoundException;
use Spiral\RoadRunner\GRPC\Exception\ServiceException;
use Spiral\RoadRunner\GRPC\Internal\Json;
use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\ResponseHeaders;
use Spiral\RoadRunner\GRPC\ResponseTrailers;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\ServiceWrapper;
use Spiral\RoadRunner\GRPC\StatusCode;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\RoadRunner\WorkerInterface;
use Illuminate\Contracts\Container\Container;
use Spiral\Interceptors\Handler\InterceptorPipeline;

/**
 * Manages group of services and communication with RoadRunner server.
 *
 * @psalm-type ServerOptions = array{
 *  debug?: bool
 * }
 */
final class Server
{
    /** @var ServiceWrapper[] */
    private array $services = [];

    /** @var list<class-string<InterceptorInterface>|InterceptorInterface> */
    private array $interceptors = [];

    /**
     * @param ServerOptions $options
     */
    public function __construct(
        private readonly \Laravel\Octane\Contracts\Worker $worker,
        private readonly InvokerInterface                 $invoker = new Invoker(),
        private readonly array                            $options = [],
        private readonly ?Container                       $container = null,
    ) {}

    /**
     * Register new GRPC service.
     *
     * For example:
     * <code>
     *  $server->registerService(EchoServiceInterface::class, new EchoService());
     * </code>
     *
     * @template T of ServiceInterface
     *
     * @param class-string<T> $interface Generated service interface.
     * @param T $service Must implement interface.
     * @param array<class-string<InterceptorInterface>> $interceptors for this service. Must implement InterceptorInterface.
     *
     * @throws ServiceException
     */
    public function registerService(string $interface, ServiceInterface $service, array $interceptors = []): void
    {
        $normalizedInterceptors = [];

        foreach ($interceptors as $interceptor) {
            if (is_string($interceptor) && class_exists($interceptor) && is_subclass_of($interceptor, InterceptorInterface::class)) {
                $normalizedInterceptors[] = $interceptor;
            } elseif (is_object($interceptor) && $interceptor instanceof InterceptorInterface) {
                $normalizedInterceptors[] = $interceptor;
            } else {
                throw new ServiceException(sprintf(
                    'Interceptor must be either a class string implementing %s or an instance of %s, %s given',
                    InterceptorInterface::class,
                    InterceptorInterface::class,
                    is_object($interceptor) ? get_class($interceptor) : gettype($interceptor),
                ));
            }
        }

        $service                                 = new ServiceWrapper($this->invoker, $interface, $service);
        $this->services[$service->getName()]     = $service;
        $this->interceptors[$service->getName()] = $normalizedInterceptors;
    }

    /**
     * Serve GRPC over given RoadRunner worker.
     */
    public function serve(?WorkerInterface $worker = null, ?callable $finalize = null): void
    {
        $worker ??= Worker::create();

        while (true) {
            $request = $worker->waitPayload();

            if ($request === null) {
                return;
            }

            $this->worker->handleTask(function () use ($request, $worker, $finalize): void {
                $responseHeaders  = new ResponseHeaders();
                $responseTrailers = new ResponseTrailers();

                try {
                    $call = CallContext::decode($request->header);

                    $context = new Context(
                        \array_merge(
                            $call->context,
                            [
                                ResponseHeaders::class  => $responseHeaders,
                                ResponseTrailers::class => $responseTrailers,
                            ],
                        ),
                    );

                    $response = $this->invoke($call->service, $call->method, $context, $request->body);

                    $headers = [];
                    $responseHeaders->count() === 0 or $headers['headers'] = $responseHeaders->packHeaders();
                    $responseTrailers->count() === 0 or $headers['trailers'] = $responseTrailers->packTrailers();

                    $this->workerSend(
                        worker: $worker,
                        body: $response,
                        headers: $headers === [] ? '{}' : Json::encode($headers),
                    );
                } catch (GRPCExceptionInterface $e) {
                    $headers = [
                        'error' => $this->createGrpcError($e),
                    ];
                    $responseHeaders->count() === 0 or $headers['headers'] = $responseHeaders->packHeaders();
                    $responseTrailers->count() === 0 or $headers['trailers'] = $responseTrailers->packTrailers();

                    $this->workerSend(
                        worker: $worker,
                        body: '',
                        headers: Json::encode($headers),
                    );
                } catch (\Throwable $e) {
                    report($e);
                    $this->workerError($worker, $this->isDebugMode() ? (string) $e : $e->getMessage());
                } finally {
                    if ($finalize !== null) {
                        isset($e) ? $finalize($e) : $finalize();
                    }
                }
            });
        }
    }

    /**
     * Invoke service method with binary payload and return the response.
     *
     * @param class-string<ServiceInterface> $serviceName
     * @param non-empty-string $method
     *
     * @throws GRPCException
     */
    protected function invoke(string $serviceName, string $method, ContextInterface $context, string $body): string
    {
        if (!isset($this->services[$serviceName])) {
            throw NotFoundException::create("Service `{$serviceName}` not found.", StatusCode::NOT_FOUND);
        }

        $service      = $this->services[$serviceName];
        $interceptors = $this->interceptors[$serviceName] ?? [];

        if (empty($interceptors)) {
            return $service->invoke($method, $context, $body);
        }

        $interceptorInstances = [];
        foreach ($interceptors as $interceptor) {
            $interceptorInstances[] = $this->createInterceptor($interceptor);
        }

        $handler = function (CallContextInterface $_ctx) use ($service, $method, $context, $body) {
            return $service->invoke($method, $context, $body);
        };

        $pipeline = new InterceptorPipeline();
        $pipeline = $pipeline->withInterceptors(...$interceptorInstances);

        $target      = Target::fromPathString($method);
        $callContext = new CallContext($target, [
            'method'  => $method,
            'context' => $context,
            'body'    => $body,
        ]);

        return $pipeline->withHandler($handler)->handle($callContext);
    }

    /**
     * Create interceptor instance using container or direct instantiation.
     * Converts resolution errors into ServiceException for proper gRPC reporting.
     *
     */
    private function createInterceptor(InterceptorInterface|string $interceptor): InterceptorInterface
    {

        if ($interceptor instanceof InterceptorInterface) {
            return $interceptor;
        }
        try {
            if ($this->container !== null) {
                return $this->container->make($interceptor);
            }

            /** @psalm-suppress InvalidStringClass */
            return new $interceptor();
        } catch (BindingResolutionException $e) {
            throw new ServiceException(\sprintf('Failed to resolve interceptor %s: %s', $interceptor, $e->getMessage()), StatusCode::INTERNAL, $e);
        } catch (\Throwable $e) {
            throw new ServiceException(\sprintf('Failed to instantiate interceptor %s: %s', $interceptor, $e->getMessage()), StatusCode::INTERNAL, $e);
        }
    }

    private function workerError(WorkerInterface $worker, string $message): void
    {
        $worker->error($message);
    }

    /**
     * @psalm-suppress InaccessibleMethod
     */
    private function workerSend(WorkerInterface $worker, string $body, string $headers): void
    {
        $worker->respond(new Payload($body, $headers));
    }

    private function createGrpcError(GRPCExceptionInterface $e): string
    {
        $status = new Status([
            'code'    => $e->getCode(),
            'message' => $e->getMessage(),
            'details' => \array_map(
                static function ($detail) {
                    $message = new Any();
                    $message->pack($detail);

                    return $message;
                },
                $e->getDetails(),
            ),
        ]);

        return \base64_encode((string) $status->serializeToString());
    }

    /**
     * Checks if debug mode is enabled.
     */
    private function isDebugMode(): bool
    {
        $debug = false;

        if (isset($this->options['debug'])) {
            $debug = \filter_var($this->options['debug'], \FILTER_VALIDATE_BOOLEAN);
        }

        return $debug;
    }
}
