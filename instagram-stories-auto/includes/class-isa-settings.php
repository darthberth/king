<?php
/**
 * Pagina delle impostazioni del plugin.
 *
 * @package InstagramStoriesAuto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestisce la pagina di amministrazione e le opzioni.
 */
class ISA_Settings {

	const OPTION_KEY = 'isa_settings';
	const PAGE_SLUG  = 'instagram-stories-auto';
	const NONCE_TEST = 'isa_test_connection';

	/**
	 * Registra hook admin.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_isa_test_connection', array( $this, 'handle_test_connection' ) );
	}

	/**
	 * Valori di default.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'ig_user_id'           => '',
			'access_token'         => '',
			'default_enabled'      => 1,
			'republish_on_update'  => 0,
			'cta_text'             => __( 'Leggi l\'articolo', 'instagram-stories-auto' ),
			'handle'               => '',
			'link_text'            => '',
			'accent_color'         => '#E1306C',
			'font_path'            => '',
			'delete_after_publish' => 1,
			'enable_logging'       => 0,
			'post_types'           => array( 'post' ),
		);
	}

	/**
	 * Restituisce le impostazioni correnti unite ai default.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::get_defaults(), $saved );
	}

	/**
	 * Aggiunge la voce di menu.
	 */
	public function add_menu() {
		add_options_page(
			__( 'Instagram Stories Auto', 'instagram-stories-auto' ),
			__( 'Instagram Stories', 'instagram-stories-auto' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registra l'impostazione e la callback di sanitizzazione.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_KEY . '_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitizza i valori inviati dal form.
	 *
	 * @param array $input Valori grezzi.
	 * @return array        Valori puliti.
	 */
	public function sanitize( $input ) {
		$out = self::get_defaults();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$out['ig_user_id']    = isset( $input['ig_user_id'] ) ? sanitize_text_field( $input['ig_user_id'] ) : '';
		$out['access_token']  = isset( $input['access_token'] ) ? trim( sanitize_textarea_field( $input['access_token'] ) ) : '';
		$out['cta_text']      = isset( $input['cta_text'] ) ? sanitize_text_field( $input['cta_text'] ) : '';
		$out['handle']        = isset( $input['handle'] ) ? sanitize_text_field( $input['handle'] ) : '';
		$out['link_text']     = isset( $input['link_text'] ) ? sanitize_text_field( $input['link_text'] ) : '';
		$out['font_path']     = isset( $input['font_path'] ) ? sanitize_text_field( $input['font_path'] ) : '';

		$accent = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$out['accent_color'] = $accent ? $accent : '#E1306C';

		$out['default_enabled']      = empty( $input['default_enabled'] ) ? 0 : 1;
		$out['republish_on_update']  = empty( $input['republish_on_update'] ) ? 0 : 1;
		$out['delete_after_publish'] = empty( $input['delete_after_publish'] ) ? 0 : 1;
		$out['enable_logging']       = empty( $input['enable_logging'] ) ? 0 : 1;

		$types = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? $input['post_types'] : array( 'post' );
		$out['post_types'] = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = array( 'post' );
		}

		return $out;
	}

	/**
	 * Rende la pagina.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s        = self::get_settings();
		$test_url = wp_nonce_url( admin_url( 'admin-post.php?action=isa_test_connection' ), self::NONCE_TEST );
		$gd_ok    = ISA_Image_Generator::is_supported();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Instagram Stories Auto Publisher', 'instagram-stories-auto' ); ?></h1>

			<?php if ( ! $gd_ok ) : ?>
				<div class="notice notice-error"><p>
					<?php esc_html_e( 'Attenzione: l\'estensione GD di PHP non è attiva. Senza GD non è possibile generare l\'immagine della Storia. Contatta il tuo hosting per abilitarla.', 'instagram-stories-auto' ); ?>
				</p></div>
			<?php endif; ?>

			<?php $this->maybe_render_test_notice(); ?>

			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'Nota: Instagram non consente di aggiungere link-sticker cliccabili tramite API. Questo plugin scrive quindi il link e la call-to-action direttamente sull\'immagine della Storia.', 'instagram-stories-auto' ); ?>
			</p></div>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_KEY . '_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Connessione Instagram', 'instagram-stories-auto' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="isa_ig_user_id"><?php esc_html_e( 'Instagram Business Account ID', 'instagram-stories-auto' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ig_user_id]" id="isa_ig_user_id" type="text" class="regular-text" value="<?php echo esc_attr( $s['ig_user_id'] ); ?>" />
							<p class="description"><?php esc_html_e( 'L\'ID dell\'account Instagram Business/Creator collegato alla tua Pagina Facebook.', 'instagram-stories-auto' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="isa_access_token"><?php esc_html_e( 'Access Token', 'instagram-stories-auto' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[access_token]" id="isa_access_token" rows="3" class="large-text code"><?php echo esc_textarea( $s['access_token'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Token long-lived con i permessi instagram_basic e instagram_content_publish.', 'instagram-stories-auto' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Comportamento', 'instagram-stories-auto' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Pubblicazione automatica', 'instagram-stories-auto' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_enabled]" value="1" <?php checked( $s['default_enabled'], 1 ); ?> />
								<?php esc_html_e( 'Attiva di default su ogni nuovo post (modificabile per singolo post).', 'instagram-stories-auto' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ripubblica agli aggiornamenti', 'instagram-stories-auto' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[republish_on_update]" value="1" <?php checked( $s['republish_on_update'], 1 ); ?> />
								<?php esc_html_e( 'Default per i nuovi post: ripubblica una Storia anche quando il post viene aggiornato (modificabile per singolo post).', 'instagram-stories-auto' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tipi di contenuto', 'instagram-stories-auto' ); ?></th>
						<td>
							<?php
							$public_types = get_post_types( array( 'public' => true ), 'objects' );
							foreach ( $public_types as $type ) {
								if ( 'attachment' === $type->name ) {
									continue;
								}
								printf(
									'<label style="margin-right:16px;"><input type="checkbox" name="%1$s[post_types][]" value="%2$s" %3$s /> %4$s</label>',
									esc_attr( self::OPTION_KEY ),
									esc_attr( $type->name ),
									checked( in_array( $type->name, (array) $s['post_types'], true ), true, false ),
									esc_html( $type->labels->singular_name )
								);
							}
							?>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Aspetto della Storia', 'instagram-stories-auto' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="isa_cta_text"><?php esc_html_e( 'Testo call-to-action', 'instagram-stories-auto' ); ?></label></th>
						<td><input name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cta_text]" id="isa_cta_text" type="text" class="regular-text" value="<?php echo esc_attr( $s['cta_text'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="isa_handle"><?php esc_html_e( 'Handle Instagram', 'instagram-stories-auto' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_KEY ); ?>[handle]" id="isa_handle" type="text" class="regular-text" value="<?php echo esc_attr( $s['handle'] ); ?>" placeholder="@iltuoaccount" />
							<p class="description"><?php esc_html_e( 'Mostrato in alto sulla Storia (facoltativo).', 'instagram-stories-auto' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="isa_link_text"><?php esc_html_e( 'Link mostrato', 'instagram-stories-auto' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_KEY ); ?>[link_text]" id="isa_link_text" type="text" class="regular-text" value="<?php echo esc_attr( $s['link_text'] ); ?>" placeholder="<?php esc_attr_e( 'es. link in bio', 'instagram-stories-auto' ); ?>" />
							<p class="description"><?php esc_html_e( 'Se vuoto, viene mostrato il dominio del sito. Il link non è cliccabile (limite di Instagram): usalo come promemoria visivo.', 'instagram-stories-auto' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="isa_accent_color"><?php esc_html_e( 'Colore accento', 'instagram-stories-auto' ); ?></label></th>
						<td><input name="<?php echo esc_attr( self::OPTION_KEY ); ?>[accent_color]" id="isa_accent_color" type="text" class="regular-text" value="<?php echo esc_attr( $s['accent_color'] ); ?>" placeholder="#E1306C" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="isa_font_path"><?php esc_html_e( 'Percorso font TTF', 'instagram-stories-auto' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_path]" id="isa_font_path" type="text" class="large-text code" value="<?php echo esc_attr( $s['font_path'] ); ?>" placeholder="/percorso/al/font.ttf" />
							<p class="description"><?php esc_html_e( 'Facoltativo. Se vuoto, il plugin cerca un font di sistema. Con un TTF il testo risulta più nitido e grande.', 'instagram-stories-auto' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Avanzate', 'instagram-stories-auto' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Pulizia file', 'instagram-stories-auto' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_after_publish]" value="1" <?php checked( $s['delete_after_publish'], 1 ); ?> /> <?php esc_html_e( 'Elimina l\'immagine generata dopo la pubblicazione.', 'instagram-stories-auto' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Log', 'instagram-stories-auto' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_logging]" value="1" <?php checked( $s['enable_logging'], 1 ); ?> /> <?php esc_html_e( 'Scrivi gli esiti nel log degli errori di PHP (debug).', 'instagram-stories-auto' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Verifica connessione', 'instagram-stories-auto' ); ?></h2>
			<p><?php esc_html_e( 'Controlla che IG User ID e Access Token siano validi.', 'instagram-stories-auto' ); ?></p>
			<a href="<?php echo esc_url( $test_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Verifica ora', 'instagram-stories-auto' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Gestisce l'azione di verifica connessione.
	 */
	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'instagram-stories-auto' ) );
		}
		check_admin_referer( self::NONCE_TEST );

		$s   = self::get_settings();
		$api = new ISA_Instagram_API( $s['ig_user_id'], $s['access_token'] );
		$res = $api->verify_credentials();

		if ( is_wp_error( $res ) ) {
			$status  = 'error';
			$message = $res->get_error_message();
		} else {
			$status  = 'success';
			$username = isset( $res['username'] ) ? $res['username'] : ( isset( $res['id'] ) ? $res['id'] : '' );
			$message = sprintf(
				/* translators: %s: username o id dell'account Instagram. */
				__( 'Connessione riuscita. Account: %s', 'instagram-stories-auto' ),
				$username
			);
		}

		set_transient( 'isa_test_notice', array( 'status' => $status, 'message' => $message ), 60 );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Mostra l'esito della verifica connessione, se presente.
	 */
	private function maybe_render_test_notice() {
		$notice = get_transient( 'isa_test_notice' );
		if ( ! $notice || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( 'isa_test_notice' );
		$class = 'success' === $notice['status'] ? 'notice-success' : 'notice-error';
		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}
}
