<?php
namespace Wikimedia\Telemetry;

use Wikimedia\Assert\PreconditionException;

/**
 * Base interface for an OpenTelemetry tracer responsible for creating spans.
 * @since 1.43
 */
interface TracerInterface {
	/**
	 * Create a span with the given name and the currently active span as the implicit parent.
	 * This requires a span to be already active and will throw an error otherwise.
	 *
	 * @param string $spanName The descriptive name of this span.
	 * Refer to the <a href="https://opentelemetry.io/docs/specs/otel/trace/api/#span">OTEL Tracing API
	 * spec</a> for recommended naming conventions.
	 * @return SpanInterface
	 * @throws PreconditionException If no span was active
	 */
	public function createSpan( string $spanName ): SpanInterface;

	/**
	 * Create a new root span, i.e. a span with no parent that forms the basis for a new trace.
	 *
	 * @param string $spanName The descriptive name of this span.
	 * Refer to the <a href="https://opentelemetry.io/docs/specs/otel/trace/api/#span">OTEL Tracing API
	 * spec</a> for recommended naming conventions.
	 * @return SpanInterface
	 */
	public function createRootSpan( string $spanName ): SpanInterface;

	/**
	 * Create a span with the given name and parent.
	 *
	 * @param string $spanName The descriptive name of this span.
	 * Refer to the <a href="https://opentelemetry.io/docs/specs/otel/trace/api/#span">OTEL Tracing API
	 * spec</a> for recommended naming conventions.
	 * @param SpanContext $parentSpanContext Context of the parent span this span should be associated with.
	 * @return SpanInterface
	 */
	public function createSpanWithParent( string $spanName, SpanContext $parentSpanContext ): SpanInterface;

	/**
	 * Shut down this tracer and export collected trace data.
	 * @return void
	 */
	public function shutdown(): void;
}
