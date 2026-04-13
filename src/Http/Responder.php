<?php

declare(strict_types=1);

namespace Bvf\Http;

use Psr\Http\Message\ResponseInterface;

final class Responder {
	public static function json( ResponseInterface $response, mixed $data, int $status = 200 ): ResponseInterface {
		$response = $response->withHeader( 'Content-Type', 'application/json' )
			->withHeader( 'X-Content-Type-Options', 'nosniff' )
			->withHeader( 'X-Frame-Options', 'SAMEORIGIN' );
		$response->getBody()->write( json_encode( $data, JSON_UNESCAPED_UNICODE ) );
		return $response->withStatus( $status );
	}

	/** @param array{code:string,message:string,data:array<string,mixed>} $body */
	public static function apiError( ResponseInterface $response, array $body, int $status ): ResponseInterface {
		return self::json( $response, $body, $status );
	}
}
