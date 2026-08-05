<?php
/**
 * Orchestrazione: genera l'immagine della Storia e la pubblica su Instagram,
 * registrando esito e log sul post.
 *
 * @package InstagramStoriesAuto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publisher delle Storie.
 */
class ISA_Publisher {

	const META_LAST_STATUS    = '_isa_last_status';
	const META_LAST_MESSAGE   = '_isa_last_message';
	const META_LAST_TIME      = '_isa_last_time';
	const META_LAST_MEDIA_ID  = '_isa_last_media_id';
	const META_LAST_HASH      = '_isa_last_hash';

	/**
	 * Impostazioni del plugin.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Costruttore.
	 *
	 * @param array $settings Impostazioni del plugin.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Pubblica la Storia per un dato post.
	 *
	 * @param int $post_id ID del post.
	 * @return true|WP_Error
	 */
	public function publish_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'isa_not_published', __( 'Il post non è pubblicato: Storia non inviata.', 'instagram-stories-auto' ) );
		}

		// 1) Genera l'immagine.
		$generator = new ISA_Image_Generator( $this->settings );
		$image     = $generator->generate( $post_id );
		if ( is_wp_error( $image ) ) {
			$this->record_result( $post_id, 'error', $image->get_error_message() );
			return $image;
		}

		// 2) Verifica che l'URL sia pubblicamente raggiungibile (Instagram deve scaricarlo).
		if ( ! $this->url_is_public( $image['url'] ) ) {
			$this->cleanup_file( $image['path'] );
			$message = __( 'L\'immagine generata non è raggiungibile pubblicamente: Instagram non può scaricarla. Il sito deve essere online e accessibile senza autenticazione.', 'instagram-stories-auto' );
			$this->record_result( $post_id, 'error', $message );
			return new WP_Error( 'isa_not_public', $message );
		}

		// 3) Pubblica tramite Graph API.
		$api    = new ISA_Instagram_API(
			isset( $this->settings['ig_user_id'] ) ? $this->settings['ig_user_id'] : '',
			isset( $this->settings['access_token'] ) ? $this->settings['access_token'] : ''
		);
		$result = $api->publish_story( $image['url'] );

		// 4) Pulizia del file locale (Instagram lo ha già scaricato).
		if ( ! empty( $this->settings['delete_after_publish'] ) ) {
			$this->cleanup_file( $image['path'] );
		}

		if ( is_wp_error( $result ) ) {
			$this->record_result( $post_id, 'error', $result->get_error_message() );
			return $result;
		}

		$media_id = isset( $result['id'] ) ? $result['id'] : '';
		update_post_meta( $post_id, self::META_LAST_MEDIA_ID, sanitize_text_field( $media_id ) );
		$this->record_result( $post_id, 'success', __( 'Storia pubblicata con successo.', 'instagram-stories-auto' ) );

		return true;
	}

	/**
	 * Registra l'esito sull'oggetto post e nel log opzionale.
	 *
	 * @param int    $post_id ID del post.
	 * @param string $status  'success' | 'error'.
	 * @param string $message Messaggio.
	 */
	private function record_result( $post_id, $status, $message ) {
		update_post_meta( $post_id, self::META_LAST_STATUS, $status );
		update_post_meta( $post_id, self::META_LAST_MESSAGE, sanitize_text_field( $message ) );
		update_post_meta( $post_id, self::META_LAST_TIME, time() );

		if ( ! empty( $this->settings['enable_logging'] ) ) {
			error_log( sprintf( '[Instagram Stories Auto] Post %d — %s: %s', $post_id, strtoupper( $status ), $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Elimina un file generato, se esiste.
	 *
	 * @param string $path Percorso.
	 */
	private function cleanup_file( $path ) {
		if ( $path && file_exists( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Verifica in modo leggero che un URL sia raggiungibile dall'esterno.
	 *
	 * @param string $url URL da controllare.
	 * @return bool
	 */
	private function url_is_public( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		// Blocca host palesemente locali: Instagram non potrebbe raggiungerli.
		if ( in_array( strtolower( $host ), array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return false;
		}
		if ( preg_match( '/\.(local|test|localhost)$/i', $host ) ) {
			return false;
		}
		// Un HEAD di cortesia; se fallisce per rete non blocchiamo comunque la logica host.
		$response = wp_remote_head( $url, array( 'timeout' => 15, 'redirection' => 3 ) );
		if ( is_wp_error( $response ) ) {
			return true; // non decidiamo sull'errore di rete locale.
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 400;
	}
}
