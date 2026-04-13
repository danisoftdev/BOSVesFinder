<?php

declare(strict_types=1);

namespace Bvf\Web;

use Bvf\Auth\User;
use Bvf\Auth\UserRepository;
use Bvf\Db;
use Bvf\Services\FleetCache;
use Bvf\Services\RestApiService;
use Bvf\Services\SettingsRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

final class AdminRoutes {
	/**
	 * @param callable(): ?User $requireWebUser
	 */
	public static function register( App $app, RestApiService $api, UserRepository $userRepo, callable $requireWebUser ): void {
		$layout = static function ( Response $response, User $user, string $title, string $nav, string $innerHtml ): Response {
			$html = Templates::render(
				'_layout.php',
				array(
					'user'       => $user,
					'title'      => $title,
					'nav_active' => $nav,
					'flash'      => Flash::pull(),
					'content'    => $innerHtml,
				)
			);
			$response->getBody()->write( $html );
			return $response->withHeader( 'Content-Type', 'text/html; charset=utf-8' );
		};

		$app->get( '/app/vessels', function ( Request $request, Response $response ) use ( $requireWebUser, $api, $userRepo, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$r = $api->listVessels( $user );
			$rows = $r['data'] ?? array();
			$captains = $userRepo->listCaptainAssignable();
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/vessels.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Vessels', 'vessels', $inner );
		} );

		$app->post( '/app/vessels', function ( Request $request, Response $response ) use ( $requireWebUser, $api, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token. Try again.', 'error' );
				return $response->withHeader( 'Location', '/app/vessels' )->withStatus( 302 );
			}
			$body = array(
				'name'        => $data['name'] ?? '',
				'external_id' => $data['external_id'] ?? '',
				'status'      => $data['status'] ?? 'active',
			);
			if ( ! empty( $data['assigned_captain_user_id'] ) ) {
				$body['assigned_captain_user_id'] = (int) $data['assigned_captain_user_id'];
			}
			$result = $api->createVessel( $user, $body );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Save failed.' ), 'error' );
			} else {
				Flash::set( 'Vessel created.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/vessels' )->withStatus( 302 );
		} );

		$app->get( '/app/trips', function ( Request $request, Response $response ) use ( $requireWebUser, $api, $userRepo, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$r = $api->listTrips( $user, 1, 50, null );
			$trips   = $r['data'] ?? array();
			$vessels = $api->listVessels( $user );
			$vlist   = $vessels['data'] ?? array();
			$captains = $userRepo->listCaptainAssignable();
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/trips.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Trips', 'trips', $inner );
		} );

		$app->post( '/app/trips', function ( Request $request, Response $response ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/trips' )->withStatus( 302 );
			}
			$body = array(
				'vessel_id'           => (int) ( $data['vessel_id'] ?? 0 ),
				'captain_user_id'     => (int) ( $data['captain_user_id'] ?? 0 ),
				'status'              => $data['status'] ?? 'active',
				'origin_label'        => $data['origin_label'] ?? '',
				'destination_label' => $data['destination_label'] ?? '',
			);
			$result = $api->createTrip( $user, $body );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not create trip.' ), 'error' );
			} else {
				Flash::set( 'Trip created.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/trips' )->withStatus( 302 );
		} );

		$app->post( '/app/trips/{id:[0-9]+}/complete', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/trips' )->withStatus( 302 );
			}
			$result = $api->completeTrip( $user, (int) $args['id'] );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not complete trip.' ), 'error' );
			} else {
				Flash::set( 'Trip completed.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/trips' )->withStatus( 302 );
		} );

		$app->get( '/app/trips/{id:[0-9]+}/fuel-logs.csv', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$tid = (int) $args['id'];
			$r   = $api->listFuelLogs( $user, $tid );
			if ( isset( $r['error'] ) ) {
				return $response->withStatus( 404 );
			}
			$rows = $r['data'] ?? array();
			$h    = fopen( 'php://memory', 'r+' );
			if ( false === $h ) {
				return $response->withStatus( 500 );
			}
			fputcsv( $h, array( 'id', 'trip_id', 'user_id', 'fuel_loaded_litres', 'fuel_consumed_litres', 'notes', 'created_at' ) );
			foreach ( $rows as $row ) {
				fputcsv(
					$h,
					array(
						(int) $row['id'],
						(int) $row['trip_id'],
						(int) $row['user_id'],
						(string) ( $row['fuel_loaded_litres'] ?? '' ),
						(string) ( $row['fuel_consumed_litres'] ?? '' ),
						(string) ( $row['notes'] ?? '' ),
						(string) ( $row['created_at'] ?? '' ),
					)
				);
			}
			rewind( $h );
			$csv = (string) stream_get_contents( $h );
			fclose( $h );
			$response->getBody()->write( $csv );
			$fname = 'trip-' . $tid . '-fuel-logs.csv';
			return $response
				->withHeader( 'Content-Type', 'text/csv; charset=utf-8' )
				->withHeader( 'Content-Disposition', 'attachment; filename="' . $fname . '"' );
		} );

		$app->get( '/app/trips/{id:[0-9]+}/job-certificate/print', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$tid = (int) $args['id'];
			$r   = $api->getTripJobCertificate( $user, $tid );
			if ( isset( $r['error'] ) ) {
				return $response->withHeader( 'Location', '/app/trips' )->withStatus( 302 );
			}
			$certificate = $r['data'];
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/trip-job-certificate-print.php';
			$html = (string) ob_get_clean();
			$response->getBody()->write( $html );
			return $response->withHeader( 'Content-Type', 'text/html; charset=utf-8' );
		} );

		$app->get( '/app/trips/{id:[0-9]+}/job-certificate', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$tid = (int) $args['id'];
			$r   = $api->getTripJobCertificate( $user, $tid );
			if ( isset( $r['error'] ) ) {
				Flash::set( (string) ( $r['error']['body']['message'] ?? 'Cannot open certificate.' ), 'error' );
				return $response->withHeader( 'Location', '/app/trips' )->withStatus( 302 );
			}
			$certificate = $r['data'];
			$csrf        = Csrf::token();
			$navCert     = $user->can( User::CAP_MANAGE_FLEET ) ? 'trips' : 'trips_emergencies';
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/trip-job-certificate.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Job certificate — Trip #' . $tid, $navCert, $inner );
		} );

		$app->post( '/app/trips/{id:[0-9]+}/job-certificate', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/trips/' . (int) $args['id'] . '/job-certificate' )->withStatus( 302 );
			}
			$tid = (int) $args['id'];
			$body = array(
				'client_name'            => $data['client_name'] ?? '',
				'client_address_phone'   => $data['client_address_phone'] ?? '',
				'client_vessel_name'     => $data['client_vessel_name'] ?? '',
				'position_anchorage'     => $data['position_anchorage'] ?? '',
				'service_date'           => $data['service_date'] ?? '',
				'service_boat_name'      => $data['service_boat_name'] ?? '',
				'time_departed_port'     => $data['time_departed_port'] ?? '',
				'time_arrived_alongside' => $data['time_arrived_alongside'] ?? '',
				'time_departed_vessel'   => $data['time_departed_vessel'] ?? '',
				'time_arrived_port'      => $data['time_arrived_port'] ?? '',
				'purpose_remarks'        => $data['purpose_remarks'] ?? '',
				'master_signed_name'     => $data['master_signed_name'] ?? '',
				'coxswain_signed_name'   => $data['coxswain_signed_name'] ?? '',
			);
			$result = $api->saveTripJobCertificate( $user, $tid, $body );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Save failed.' ), 'error' );
			} else {
				Flash::set( 'Job certificate saved.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/trips/' . $tid . '/job-certificate' )->withStatus( 302 );
		} );

		$app->get( '/app/trips/{id:[0-9]+}', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$tid = (int) $args['id'];
			$tr  = $api->getTrip( $user, $tid );
			if ( isset( $tr['error'] ) ) {
				Flash::set( 'Trip not found.', 'error' );
				return $response->withHeader( 'Location', '/app/trips' )->withStatus( 302 );
			}
			$trip = $tr['data'];
			$fuel = $api->listFuelLogs( $user, $tid );
			$tc   = $api->listTripCrew( $user, $tid );
			$cr   = $api->listCrew( $user );
			$fuelLogs = $fuel['data'] ?? array();
			$tripCrew = $tc['data'] ?? array();
			$allCrew  = $cr['data'] ?? array();
			$assignedIds = array_map( static fn( $x ) => (int) $x['crew_id'], $tripCrew );
			$crewPickList  = array_values(
				array_filter(
					$allCrew,
					static fn( $c ) => ! in_array( (int) $c['id'], $assignedIds, true )
				)
			);
			$canManageFleet = $user->can( User::CAP_MANAGE_FLEET );
			$isTripCaptain = $user->can( User::CAP_SUBMIT_TRACKING ) && (int) $trip['captain_user_id'] === $user->id;
			$canManageTripCrew = $canManageFleet || ( $user->can( User::CAP_MANAGE_CREW ) && $isTripCaptain );
			$canPostFuel    = $canManageFleet || (
				$isTripCaptain
				&& ( $trip['status'] ?? '' ) === 'active'
			);
			$canToggleOnboard = $canManageFleet || (
				$user->can( User::CAP_SUBMIT_TRACKING ) && (int) $trip['captain_user_id'] === $user->id
			);
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/trip-detail.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Trip #' . $tid, 'trips', $inner );
		} );

		$app->post( '/app/trips/{id:[0-9]+}/fuel', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/trips/' . (int) $args['id'] )->withStatus( 302 );
			}
			$tid = (int) $args['id'];
			$body = array(
				'fuel_loaded_litres'    => $data['fuel_loaded_litres'] ?? '',
				'fuel_consumed_litres' => $data['fuel_consumed_litres'] ?? '',
				'notes' => $data['notes'] ?? '',
			);
			$result = $api->postFuelLog( $user, $tid, $body );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not save fuel log.' ), 'error' );
			} else {
				Flash::set( 'Fuel log entry added.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/trips/' . $tid )->withStatus( 302 );
		} );

		$app->post( '/app/trips/{id:[0-9]+}/crew/add', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ( ! $user->can( User::CAP_MANAGE_FLEET ) && ! $user->can( User::CAP_MANAGE_CREW ) ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/trips/' . (int) $args['id'] )->withStatus( 302 );
			}
			$tid = (int) $args['id'];
			$result = $api->addTripCrew( $user, $tid, array( 'crew_id' => (int) ( $data['crew_id'] ?? 0 ) ) );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not assign crew.' ), 'error' );
			} else {
				Flash::set( 'Crew assigned to trip.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/trips/' . $tid )->withStatus( 302 );
		} );

		$app->post( '/app/trips/{id:[0-9]+}/crew/remove', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ( ! $user->can( User::CAP_MANAGE_FLEET ) && ! $user->can( User::CAP_MANAGE_CREW ) ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/trips/' . (int) $args['id'] )->withStatus( 302 );
			}
			$tid = (int) $args['id'];
			$crewId = (int) ( $data['crew_id'] ?? 0 );
			$result = $api->removeTripCrew( $user, $tid, $crewId );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not remove crew.' ), 'error' );
			} else {
				Flash::set( 'Crew removed from trip.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/trips/' . $tid )->withStatus( 302 );
		} );

		$app->post( '/app/trips/{id:[0-9]+}/crew/onboard', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/trips/' . (int) $args['id'] )->withStatus( 302 );
			}
			$tid    = (int) $args['id'];
			$crewId = (int) ( $data['crew_id'] ?? 0 );
			$result = $api->setTripCrewOnboard(
				$user,
				$tid,
				$crewId,
				array( 'onboard_confirmed' => (int) ( $data['onboard_confirmed'] ?? 0 ) )
			);
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not update onboard status.' ), 'error' );
			} else {
				Flash::set( 'Onboard status updated.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/trips/' . $tid )->withStatus( 302 );
		} );

		$app->get( '/app/crew', function ( Request $request, Response $response ) use ( $requireWebUser, $api, $userRepo, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_CREW ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$r = $api->listCrew( $user );
			$crewRows      = $r['data'] ?? array();
			$canManageCrew = $user->can( User::CAP_MANAGE_CREW );
			$vlist         = array();
			$appUsers      = array();
			if ( $canManageCrew ) {
				$v       = $api->listVessels( $user );
				$vlist   = $v['data'] ?? array();
				$appUsers = array_map(
					static fn( User $u ) => array(
						'id'           => $u->id,
						'email'        => $u->email,
						'display_name' => $u->displayName,
					),
					$userRepo->listAllByEmail()
				);
			}
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/crew.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Crew', 'crew', $inner );
		} );

		$app->post( '/app/crew', function ( Request $request, Response $response ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			if ( ! $user->can( User::CAP_MANAGE_CREW ) ) {
				Flash::set( 'You do not have permission to change the crew registry.', 'error' );
				return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
			}
			$body = array(
				'display_name' => $data['display_name'] ?? '',
				'role_slug'    => $data['role_slug'] ?? 'crew',
				'vessel_id'    => $data['vessel_id'] ?? '',
				'app_user_id'  => $data['app_user_id'] ?? '',
			);
			$result = $api->createCrew( $user, $body );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not create crew.' ), 'error' );
			} else {
				Flash::set( 'Crew member added.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
		} );

		$app->post( '/app/crew/{id:[0-9]+}/update', function ( Request $request, Response $response, array $args ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			if ( ! $user->can( User::CAP_MANAGE_CREW ) ) {
				Flash::set( 'You do not have permission to change the crew registry.', 'error' );
				return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
			}
			$id = (int) $args['id'];
			$body = array(
				'display_name' => $data['display_name'] ?? '',
				'role_slug'    => $data['role_slug'] ?? '',
				'vessel_id'    => $data['vessel_id'] ?? '',
				'app_user_id'  => $data['app_user_id'] ?? '',
			);
			$result = $api->updateCrew( $user, $id, $body );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not update crew.' ), 'error' );
			} else {
				Flash::set( 'Crew member updated.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
		} );

		$app->post( '/app/crew/delete', function ( Request $request, Response $response ) use ( $requireWebUser, $api ): Response {
			$user = $requireWebUser();
			if ( ! $user ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			if ( ! $user->can( User::CAP_MANAGE_CREW ) ) {
				Flash::set( 'You do not have permission to change the crew registry.', 'error' );
				return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
			}
			$id = (int) ( $data['id'] ?? 0 );
			$result = $api->deleteCrew( $user, $id );
			if ( isset( $result['error'] ) ) {
				Flash::set( (string) ( $result['error']['body']['message'] ?? 'Could not delete crew.' ), 'error' );
			} else {
				Flash::set( 'Crew member removed.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/crew' )->withStatus( 302 );
		} );

		$app->get( '/app/alerts', function ( Request $request, Response $response ) use ( $requireWebUser, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_VIEW_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$st = Db::pdo()->query(
				'SELECT a.*, v.name AS vessel_name FROM bvf_alerts a
				LEFT JOIN bvf_vessels v ON v.id = a.vessel_id
				ORDER BY a.id DESC LIMIT 100'
			);
			$alerts = $st->fetchAll() ?: array();
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/alerts.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Alerts', 'alerts', $inner );
		} );

		$app->get( '/app/geofences', function ( Request $request, Response $response ) use ( $requireWebUser, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$st   = Db::pdo()->query( 'SELECT * FROM bvf_geofences ORDER BY id DESC' );
			$zones = $st->fetchAll() ?: array();
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/geofences.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Geofences', 'geofences', $inner );
		} );

		$app->post( '/app/geofences', function ( Request $request, Response $response ) use ( $requireWebUser ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/geofences' )->withStatus( 302 );
			}
			$name = trim( (string) ( $data['name'] ?? '' ) );
			if ( $name === '' ) {
				Flash::set( 'Name is required.', 'error' );
				return $response->withHeader( 'Location', '/app/geofences' )->withStatus( 302 );
			}
			$type = in_array( (string) ( $data['zone_type'] ?? '' ), array( 'operational', 'restricted' ), true )
				? (string) $data['zone_type'] : 'operational';
			$lat  = $data['center_lat'] !== '' && $data['center_lat'] !== null ? (float) $data['center_lat'] : null;
			$lng  = $data['center_lng'] !== '' && $data['center_lng'] !== null ? (float) $data['center_lng'] : null;
			$rad  = isset( $data['radius_meters'] ) && $data['radius_meters'] !== '' ? (int) $data['radius_meters'] : null;
			$now  = date( 'Y-m-d H:i:s' );
			$ins  = Db::pdo()->prepare(
				'INSERT INTO bvf_geofences (name, zone_type, center_lat, center_lng, radius_meters, active, created_at, updated_at)
				VALUES (?,?,?,?,?,1,?,?)'
			);
			$ins->execute( array( $name, $type, $lat, $lng, $rad, $now, $now ) );
			Flash::set( 'Geofence saved.', 'success' );
			return $response->withHeader( 'Location', '/app/geofences' )->withStatus( 302 );
		} );

		$app->post( '/app/geofences/delete', function ( Request $request, Response $response ) use ( $requireWebUser ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_FLEET ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/geofences' )->withStatus( 302 );
			}
			$id = (int) ( $data['id'] ?? 0 );
			if ( $id > 0 ) {
				$st = Db::pdo()->prepare( 'DELETE FROM bvf_geofences WHERE id = ?' );
				$st->execute( array( $id ) );
				FleetCache::bust();
				Flash::set( 'Geofence deleted.', 'success' );
			}
			return $response->withHeader( 'Location', '/app/geofences' )->withStatus( 302 );
		} );

		$app->get( '/app/users', function ( Request $request, Response $response ) use ( $requireWebUser, $userRepo, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_SETTINGS ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$users = $userRepo->listAllByEmail();
			$roles = User::roleChoices();
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/users.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Users', 'users', $inner );
		} );

		$app->post( '/app/users', function ( Request $request, Response $response ) use ( $requireWebUser, $userRepo ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_SETTINGS ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/users' )->withStatus( 302 );
			}
			try {
				$userRepo->createUser(
					(string) ( $data['email'] ?? '' ),
					(string) ( $data['password'] ?? '' ),
					(string) ( $data['display_name'] ?? '' ),
					(string) ( $data['role'] ?? User::ROLE_VIEWER )
				);
				Flash::set( 'User created.', 'success' );
			} catch ( \Throwable $e ) {
				Flash::set( $e->getMessage(), 'error' );
			}
			return $response->withHeader( 'Location', '/app/users' )->withStatus( 302 );
		} );

		$app->get( '/app/settings', function ( Request $request, Response $response ) use ( $requireWebUser, $layout ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_SETTINGS ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			try {
				$opsEmails = ( new SettingsRepository() )->get( 'ops_notification_emails', '' );
			} catch ( \Throwable $e ) {
				$opsEmails = '';
			}
			ob_start();
			require dirname( __DIR__, 2 ) . '/templates/settings.php';
			$inner = (string) ob_get_clean();
			return $layout( $response, $user, 'Settings', 'settings', $inner );
		} );

		$app->post( '/app/settings', function ( Request $request, Response $response ) use ( $requireWebUser ): Response {
			$user = $requireWebUser();
			if ( ! $user || ! $user->can( User::CAP_MANAGE_SETTINGS ) ) {
				return $response->withHeader( 'Location', '/login' )->withStatus( 302 );
			}
			$data = (array) $request->getParsedBody();
			if ( ! Csrf::verify( $data['csrf'] ?? null ) ) {
				Flash::set( 'Invalid security token.', 'error' );
				return $response->withHeader( 'Location', '/app/settings' )->withStatus( 302 );
			}
			$emails = trim( (string) ( $data['ops_notification_emails'] ?? '' ) );
			try {
				( new SettingsRepository() )->set( 'ops_notification_emails', $emails );
				Flash::set( 'Settings saved.', 'success' );
			} catch ( \Throwable $e ) {
				Flash::set( 'Could not save settings. Is database migrated?', 'error' );
			}
			return $response->withHeader( 'Location', '/app/settings' )->withStatus( 302 );
		} );
	}
}
