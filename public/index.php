<?php

declare(strict_types=1);

use Bvf\Auth\User;
use Bvf\Auth\UserRepository;
use Bvf\Db;
use Bvf\EnvConfig;
use Bvf\SchemaEnsure;
use Bvf\Http\Responder;
use Bvf\Services\RestApiService;
use Bvf\Web\AdminRoutes;
use Bvf\Web\Templates;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require dirname( __DIR__ ) . '/vendor/autoload.php';

$root = dirname( __DIR__ );
if ( is_readable( $root . '/.env' ) ) {
	\Dotenv\Dotenv::createImmutable( $root )->load();
}

try {
	SchemaEnsure::ensureLegacyColumns( Db::pdo(), false );
} catch ( \Throwable $e ) {
	if ( function_exists( 'error_log' ) ) {
		error_log( 'BOSVesFinder SchemaEnsure: ' . $e->getMessage() );
	}
}

$cookieDefaults = session_get_cookie_params();
$secureCookie   = EnvConfig::sessionCookieSecure();
$sameSite       = EnvConfig::sessionSameSite();
if ( $sameSite === 'None' && ! $secureCookie ) {
	$secureCookie = true;
}
session_set_cookie_params(
	array(
		'lifetime' => $cookieDefaults['lifetime'],
		'path'     => $cookieDefaults['path'] !== '' ? $cookieDefaults['path'] : '/',
		'domain'   => $cookieDefaults['domain'],
		'secure'   => $secureCookie,
		'httponly' => true,
		'samesite' => $sameSite,
	)
);
session_name( 'bvfsess' );
session_start();

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware( true, true, true );

$apiService = new RestApiService();
$userRepo   = new UserRepository();

$requireWebUser = static function (): ?User {
	if ( empty( $_SESSION['user_id'] ) ) {
		return null;
	}
	$repo = new UserRepository();
	return $repo->findById( (int) $_SESSION['user_id'] );
};

$apiAuthFail = static function ( Response $response, int $status, string $code, string $message ): Response {
	return Responder::apiError(
		$response,
		array(
			'code'    => $code,
			'message' => $message,
			'data'    => array( 'status' => $status ),
		),
		$status
	);
};

$requireApiUser = static function ( Request $request, Response $response ) use ( $apiAuthFail ): array {
	if ( empty( $_SESSION['user_id'] ) ) {
		return array( null, $apiAuthFail( $response, 401, 'bvf_unauthorized', 'Authentication required.' ) );
	}
	$repo = new UserRepository();
	$user = $repo->findById( (int) $_SESSION['user_id'] );
	if ( ! $user ) {
		return array( null, $apiAuthFail( $response, 401, 'bvf_unauthorized', 'Authentication required.' ) );
	}
	$method = $request->getMethod();
	if ( in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
		$nonce = $request->getHeaderLine( 'X-BVF-Nonce' );
		if ( $nonce === '' || empty( $_SESSION['bvf_rest_nonce'] ) || ! hash_equals( (string) $_SESSION['bvf_rest_nonce'], $nonce ) ) {
			return array( null, $apiAuthFail( $response, 403, 'bvf_forbidden', 'Invalid or missing REST nonce.' ) );
		}
	}
	return array( $user, null );
};

$applyApiResult = static function ( Response $response, array $result ): Response {
	if ( isset( $result['error'] ) ) {
		$e = $result['error'];
		return Responder::apiError( $response, $e['body'], $e['status'] );
	}
	$headers = $result['headers'] ?? array();
	foreach ( $headers as $k => $v ) {
		$response = $response->withHeader( (string) $k, (string) $v );
	}
	return Responder::json( $response, $result['data'] );
};

/* --- Web UI --- */
$app->get( '/', function ( Request $request, Response $response ) use ( $requireWebUser ): Response {
	$user = $requireWebUser();
	if ( ! $user ) {
		return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
	}
	return $response->withHeader( 'Location', '/app/fleet' )->withStatus( 302 );
} );

$app->get( '/login', function ( Request $request, Response $response ) use ( $requireWebUser ): Response {
	if ( $requireWebUser() ) {
		return $response->withHeader( 'Location', '/app/fleet' )->withStatus( 302 );
	}
	$html = Templates::render( 'login.php', array() );
	$response->getBody()->write( $html );
	return $response->withHeader( 'Content-Type', 'text/html; charset=utf-8' );
} );

$app->post( '/login', function ( Request $request, Response $response ) use ( $userRepo ): Response {
	$data = (array) $request->getParsedBody();
	$email = trim( (string) ( $data['email'] ?? '' ) );
	$pass  = (string) ( $data['password'] ?? '' );
	$user  = $userRepo->verifyPassword( $email, $pass );
	if ( ! $user ) {
	$html = Templates::render( 'login.php', array( 'error' => 'Invalid email or password.' ) );
		$response->getBody()->write( $html );
		return $response->withHeader( 'Content-Type', 'text/html; charset=utf-8' )->withStatus( 401 );
	}
	session_regenerate_id( true );
	$_SESSION['user_id']       = $user->id;
	$_SESSION['bvf_rest_nonce'] = bin2hex( random_bytes( 16 ) );
	return $response->withHeader( 'Location', '/app/fleet' )->withStatus( 302 );
} );

$app->get( '/logout', function ( Request $request, Response $response ): Response {
	$_SESSION = array();
	if ( ini_get( 'session.use_cookies' ) ) {
		$p = session_get_cookie_params();
		setcookie(
			session_name(),
			'',
			array(
				'expires'  => time() - 42000,
				'path'     => $p['path'] !== '' ? $p['path'] : '/',
				'domain'   => $p['domain'],
				'secure'   => ! empty( $p['secure'] ),
				'httponly' => true,
				'samesite' => $p['samesite'] ?? 'Lax',
			)
		);
	}
	session_destroy();
	return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
} );

$app->get( '/app/fleet', function ( Request $request, Response $response ) use ( $requireWebUser ): Response {
	$user = $requireWebUser();
	if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
		return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
	}
	$_SESSION['bvf_rest_nonce'] = bin2hex( random_bytes( 16 ) );
	$base = EnvConfig::requestAppBaseUrl();
	$html = Templates::render(
		'fleet.php',
		array(
			'user'       => $user,
			'restUrl'    => $base . '/api/v1/fleet/live',
			'nonce'      => (string) $_SESSION['bvf_rest_nonce'],
			'appUrl'     => $base,
		)
	);
	$response->getBody()->write( $html );
	return $response->withHeader( 'Content-Type', 'text/html; charset=utf-8' );
} );

$app->get( '/app/captain', function ( Request $request, Response $response ) use ( $requireWebUser ): Response {
	$user = $requireWebUser();
	if ( ! $user || ! $user->can( User::CAP_SUBMIT_TRACKING ) ) {
		return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
	}
	$_SESSION['bvf_rest_nonce'] = bin2hex( random_bytes( 16 ) );
	$base = EnvConfig::requestAppBaseUrl();
	$html = Templates::render(
		'captain.php',
		array(
			'user'     => $user,
			'restBase' => $base . '/api/v1/',
			'nonce'    => (string) $_SESSION['bvf_rest_nonce'],
		)
	);
	$response->getBody()->write( $html );
	return $response->withHeader( 'Content-Type', 'text/html; charset=utf-8' );
} );

/* --- JSON API --- */
$app->group(
	'/api/v1',
	function ( RouteCollectorProxy $group ) use ( $apiService, $requireApiUser, $applyApiResult ): void {
		$group->get( '/fleet/live', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->getFleetLive( $user ) );
		} );
		$group->get( '/fleet/tracks', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$q     = $request->getQueryParams();
			$hours = (int) ( $q['hours'] ?? 48 );
			return $applyApiResult( $response, $apiService->getFleetTracks( $user, $hours ) );
		} );
		$group->get( '/vessels', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->listVessels( $user ) );
		} );
		$group->get( '/vessels/mine', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->listVesselsForCaptainTrip( $user ) );
		} );
		$group->post( '/vessels', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->createVessel( $user, $body ) );
		} );
		$group->get( '/trips', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$q    = $request->getQueryParams();
			$page = (int) ( $q['page'] ?? 1 );
			$pp   = (int) ( $q['per_page'] ?? 20 );
			$st   = isset( $q['status'] ) ? (string) $q['status'] : null;
			return $applyApiResult( $response, $apiService->listTrips( $user, $page, $pp, $st ) );
		} );
		$group->post( '/trips', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->createTrip( $user, $body ) );
		} );
		$group->get( '/trips/mine/active', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->getMyActiveTrip( $user ) );
		} );
		$group->get( '/trips/mine/current', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->getMyCaptainCurrentTrip( $user ) );
		} );
		$group->get( '/crew', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->listCrew( $user ) );
		} );
		$group->post( '/crew', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->createCrew( $user, $body ) );
		} );
		$group->patch( '/crew/{id:[0-9]+}', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->updateCrew( $user, (int) $args['id'], $body ) );
		} );
		$group->delete( '/crew/{id:[0-9]+}', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->deleteCrew( $user, (int) $args['id'] ) );
		} );
		$group->get( '/trips/{id:[0-9]+}/crew', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->listTripCrew( $user, (int) $args['id'] ) );
		} );
		$group->post( '/trips/{id:[0-9]+}/crew', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->addTripCrew( $user, (int) $args['id'], $body ) );
		} );
		$group->delete( '/trips/{tripId:[0-9]+}/crew/{crewId:[0-9]+}', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult(
				$response,
				$apiService->removeTripCrew( $user, (int) $args['tripId'], (int) $args['crewId'] )
			);
		} );
		$group->patch( '/trips/{tripId:[0-9]+}/crew/{crewId:[0-9]+}', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult(
				$response,
				$apiService->setTripCrewOnboard( $user, (int) $args['tripId'], (int) $args['crewId'], $body )
			);
		} );
		$group->get( '/trips/{id:[0-9]+}/fuel-logs', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->listFuelLogs( $user, (int) $args['id'] ) );
		} );
		$group->post( '/trips/{id:[0-9]+}/fuel-logs', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->postFuelLog( $user, (int) $args['id'], $body ) );
		} );
		$group->get( '/trips/{id:[0-9]+}', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->getTrip( $user, (int) $args['id'] ) );
		} );
		$group->post( '/trips/{id:[0-9]+}/positions', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->postTripPosition( $user, (int) $args['id'], $body ) );
		} );
		$group->post( '/trips/{id:[0-9]+}/activate', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->activateCaptainTrip( $user, (int) $args['id'] ) );
		} );
		$group->post( '/trips/{id:[0-9]+}/complete', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->completeTrip( $user, (int) $args['id'] ) );
		} );
		$group->get( '/trips/{id:[0-9]+}/job-certificate', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			return $applyApiResult( $response, $apiService->getTripJobCertificate( $user, (int) $args['id'] ) );
		} );
		$group->put( '/trips/{id:[0-9]+}/job-certificate', function ( Request $request, Response $response, array $args ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->saveTripJobCertificate( $user, (int) $args['id'], $body ) );
		} );
		$group->post( '/alerts/sos', function ( Request $request, Response $response ) use ( $apiService, $requireApiUser, $applyApiResult ): Response {
			list( $user, $fail ) = $requireApiUser( $request, $response );
			if ( $fail ) {
				return $fail;
			}
			$body = (array) $request->getParsedBody();
			return $applyApiResult( $response, $apiService->postSos( $user, $body ) );
		} );
	}
);

AdminRoutes::register( $app, $apiService, $userRepo, $requireWebUser );

$app->run();
