<?php

declare(strict_types=1);

namespace WP\MCP\Tests\Integration;

use WP\MCP\Core\McpServer;
use WP\MCP\Handlers\Initialize\InitializeHandler;
use WP\MCP\Handlers\Prompts\PromptsHandler;
use WP\MCP\Handlers\Resources\ResourcesHandler;
use WP\MCP\Handlers\System\SystemHandler;
use WP\MCP\Handlers\Tools\ToolsHandler;
use WP\MCP\Tests\Fixtures\DummyAbility;
use WP\MCP\Tests\Fixtures\DummyErrorHandler;
use WP\MCP\Tests\Fixtures\DummyObservabilityHandler;
use WP\MCP\Tests\Fixtures\DummyTransport;
use WP\MCP\Tests\TestCase;
use WP\MCP\Transport\Infrastructure\McpRequestRouter;
use WP\MCP\Transport\Infrastructure\McpTransportContext;

final class TransportRoutingTest extends TestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		do_action( 'abilities_api_init' );
		DummyAbility::register_all();
	}

	public function test_tools_and_prompts_routed_and_cursor_added(): void {
		$server = new McpServer(
			'srv',
			'mcp/v1',
			'/mcp',
			'Srv',
			'desc',
			'0.0.1',
			array( DummyTransport::class ),
			DummyErrorHandler::class,
			DummyObservabilityHandler::class,
			array( 'test/always-allowed' ),
			array( 'test/resource' ),
			array( 'test/prompt' ),
		);

		// Create transport with proper context
		$context   = $this->createTransportContext( $server );
		$transport = new DummyTransport( $context );

		$res = $transport->test_route_request( 'resources/list', array() );
		$this->assertArrayHasKey( 'resources', $res );
		$this->assertArrayHasKey( 'nextCursor', $res );

		$res2 = $transport->test_route_request( 'prompts/list', array() );
		$this->assertArrayHasKey( 'prompts', $res2 );
	}

	private function createTransportContext( McpServer $server ): McpTransportContext {
		// Create handlers
		$initialize_handler = new InitializeHandler( $server );
		$tools_handler      = new ToolsHandler( $server );
		$resources_handler  = new ResourcesHandler( $server );
		$prompts_handler    = new PromptsHandler( $server );
		$system_handler     = new SystemHandler( $server );

		// Create context for the router first (without router to avoid circular dependency)
		$router_context = new McpTransportContext(
			array(
				'mcp_server'            => $server,
				'initialize_handler'    => $initialize_handler,
				'tools_handler'         => $tools_handler,
				'resources_handler'     => $resources_handler,
				'prompts_handler'       => $prompts_handler,
				'system_handler'        => $system_handler,
				'observability_handler' => DummyObservabilityHandler::class,
				'request_router'        => null,
			)
		);

		// Create the router
		$request_router = new McpRequestRouter( $router_context );

		// Create the final context with the router
		return new McpTransportContext(
			array(
				'mcp_server'            => $server,
				'initialize_handler'    => $initialize_handler,
				'tools_handler'         => $tools_handler,
				'resources_handler'     => $resources_handler,
				'prompts_handler'       => $prompts_handler,
				'system_handler'        => $system_handler,
				'observability_handler' => DummyObservabilityHandler::class,
				'request_router'        => $request_router,
			)
		);
	}
}
