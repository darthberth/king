<?php
/**
 * Client minimale per la Instagram Graph API (Content Publishing).
 *
 * @package InstagramStoriesAuto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestisce le chiamate alle Graph API necessarie a pubblicare una Storia.
 */
class ISA_Instagram_API {

	/**
	 * Instagram Business Account ID (IG User ID).
	 *
	 * @var string
	 */
	private $ig_user_id;

	/**
	 * Access token long-lived.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Base URL delle Graph API, versione inclusa.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Costruttore.
	 *
	 * @param string $ig_user_id   Instagram Business Account ID.
	 * @param string $access_token Access token.
	 */
	public function __construct( $ig_user_id, $access_token ) {
		$this->ig_user_id   = trim( (string) $ig_user_id );
		$this->access_token = trim( (string) $access_token );
		$this->base_url     = 'https://graph.facebook.com/' . ISA_GRAPH_API_VERSION . '/';
	}

	/**
	 * Indica se le credenziali di base sono presenti.
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return '' !== $this->ig_user_id && '' !== $this->access_token;
	}

	/**
	 * Pubblica una Storia a partire da un URL immagine pubblicamente raggiungibile.
	 *
	 * Esegue il flusso a tre passi: crea container -> attende FINISHED -> pubblica.
	 *
	 * @param string $image_url URL pubblico dell'immagine 1080x1920.
	 * @return array|WP_Error   Array con 'id' del media pubblicato oppure WP_Error.
	 */
	public function publish_story( $image_url ) {
		if ( ! $this->has_credentials() ) {
			return new WP_Error( 'isa_no_credentials', __( 'Credenziali Instagram mancanti (IG User ID o Access Token).', 'instagram-stories-auto' ) );
		}

		// 1) Crea il container media di tipo STORIES.
		$container = $this->create_media_container( $image_url );
		if ( is_wp_error( $container ) ) {
			return $container;
		}
		$creation_id = $container;

		// 2) Attende che il container sia pronto (FINISHED) prima di pubblicare.
		$ready = $this->wait_until_ready( $creation_id );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		// 3) Pubblica il container.
		return $this->publish_media_container( $creation_id );
	}

	/**
	 * Crea il container media (STORIES).
	 *
	 * @param string $image_url URL pubblico dell'immagine.
	 * @return string|WP_Error  creation_id oppure WP_Error.
	 */
	private function create_media_container( $image_url ) {
		$endpoint = $this->base_url . rawurlencode( $this->ig_user_id ) . '/media';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 45,
				'body'    => array(
					'image_url'    => $image_url,
					'media_type'   => 'STORIES',
					'access_token' => $this->access_token,
				),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['id'] ) ) {
			return new WP_Error( 'isa_no_creation_id', __( 'La creazione del container non ha restituito un ID.', 'instagram-stories-auto' ) );
		}
		return (string) $data['id'];
	}

	/**
	 * Attende che il container raggiunga lo stato FINISHED.
	 *
	 * @param string $creation_id ID del container.
	 * @param int    $max_attempts Numero massimo di tentativi.
	 * @param int    $delay_seconds Attesa tra i tentativi.
	 * @return true|WP_Error
	 */
	private function wait_until_ready( $creation_id, $max_attempts = 8, $delay_seconds = 3 ) {
		$endpoint = $this->base_url . rawurlencode( $creation_id );

		for ( $attempt = 0; $attempt < $max_attempts; $attempt++ ) {
			$response = wp_remote_get(
				add_query_arg(
					array(
						'fields'       => 'status_code,status',
						'access_token' => $this->access_token,
					),
					$endpoint
				),
				array( 'timeout' => 30 )
			);

			$data = $this->parse_response( $response );
			if ( is_wp_error( $data ) ) {
				return $data;
			}

			$status = isset( $data['status_code'] ) ? $data['status_code'] : '';

			if ( 'FINISHED' === $status ) {
				return true;
			}
			if ( 'ERROR' === $status || 'EXPIRED' === $status ) {
				$detail = isset( $data['status'] ) ? $data['status'] : $status;
				return new WP_Error(
					'isa_container_error',
					sprintf(
						/* translators: %s: dettaglio stato restituito da Instagram. */
						__( 'Instagram non ha potuto elaborare l\'immagine (stato: %s).', 'instagram-stories-auto' ),
						$detail
					)
				);
			}

			// IN_PROGRESS o stato non ancora pronto: attende e riprova.
			if ( $attempt < $max_attempts - 1 ) {
				sleep( $delay_seconds );
			}
		}

		return new WP_Error( 'isa_container_timeout', __( 'Timeout: il container Instagram non è diventato pronto in tempo.', 'instagram-stories-auto' ) );
	}

	/**
	 * Pubblica un container già pronto.
	 *
	 * @param string $creation_id ID del container.
	 * @return array|WP_Error     Array con 'id' del media oppure WP_Error.
	 */
	private function publish_media_container( $creation_id ) {
		$endpoint = $this->base_url . rawurlencode( $this->ig_user_id ) . '/media_publish';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 45,
				'body'    => array(
					'creation_id'  => $creation_id,
					'access_token' => $this->access_token,
				),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['id'] ) ) {
			return new WP_Error( 'isa_no_media_id', __( 'La pubblicazione non ha restituito un ID media.', 'instagram-stories-auto' ) );
		}
		return array( 'id' => (string) $data['id'] );
	}

	/**
	 * Verifica la validità delle credenziali interrogando l'account.
	 *
	 * @return array|WP_Error Dati dell'account (id, username) oppure WP_Error.
	 */
	public function verify_credentials() {
		if ( ! $this->has_credentials() ) {
			return new WP_Error( 'isa_no_credentials', __( 'Inserisci IG User ID e Access Token.', 'instagram-stories-auto' ) );
		}

		$endpoint = $this->base_url . rawurlencode( $this->ig_user_id );
		$response = wp_remote_get(
			add_query_arg(
				array(
					'fields'       => 'id,username',
					'access_token' => $this->access_token,
				),
				$endpoint
			),
			array( 'timeout' => 20 )
		);

		return $this->parse_response( $response );
	}

	/**
	 * Normalizza la risposta HTTP e gestisce gli errori Graph.
	 *
	 * @param array|WP_Error $response Risposta di wp_remote_*.
	 * @return array|WP_Error          Corpo decodificato oppure WP_Error.
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'isa_invalid_response',
				sprintf(
					/* translators: %d: codice di stato HTTP. */
					__( 'Risposta non valida da Instagram (HTTP %d).', 'instagram-stories-auto' ),
					(int) $code
				)
			);
		}

		if ( isset( $data['error'] ) ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Errore sconosciuto.', 'instagram-stories-auto' );
			$type    = isset( $data['error']['type'] ) ? $data['error']['type'] : 'GraphError';
			return new WP_Error( 'isa_graph_error', sprintf( '%s: %s', $type, $message ), $data['error'] );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'isa_http_error',
				sprintf(
					/* translators: %d: codice di stato HTTP. */
					__( 'Instagram ha risposto con un errore HTTP %d.', 'instagram-stories-auto' ),
					(int) $code
				)
			);
		}

		return $data;
	}
}
