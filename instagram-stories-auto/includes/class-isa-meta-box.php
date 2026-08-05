<?php
/**
 * Meta box sul post: attiva/disattiva la pubblicazione e mostra l'ultimo esito.
 *
 * @package InstagramStoriesAuto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestisce il meta box per singolo post.
 */
class ISA_Meta_Box {

	const META_ENABLED   = '_isa_enabled';
	const META_REPUBLISH = '_isa_republish_on_update';
	const NONCE_FIELD    = 'isa_meta_nonce';
	const NONCE_ACTION   = 'isa_save_meta';

	/**
	 * Registra gli hook.
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Aggiunge il meta box sui tipi di contenuto configurati.
	 */
	public function add_meta_box() {
		$settings = ISA_Settings::get_settings();
		foreach ( (array) $settings['post_types'] as $type ) {
			add_meta_box(
				'isa_story_box',
				__( 'Storia Instagram', 'instagram-stories-auto' ),
				array( $this, 'render' ),
				$type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Rende il contenuto del meta box.
	 *
	 * @param WP_Post $post Post corrente.
	 */
	public function render( $post ) {
		$settings = ISA_Settings::get_settings();

		// Valore corrente: se non impostato usa i default globali.
		$enabled   = get_post_meta( $post->ID, self::META_ENABLED, true );
		$republish = get_post_meta( $post->ID, self::META_REPUBLISH, true );

		if ( '' === $enabled ) {
			$enabled = $settings['default_enabled'] ? '1' : '0';
		}
		if ( '' === $republish ) {
			$republish = $settings['republish_on_update'] ? '1' : '0';
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<p>
			<label>
				<input type="checkbox" name="isa_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
				<?php esc_html_e( 'Pubblica una Storia alla pubblicazione', 'instagram-stories-auto' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="isa_republish" value="1" <?php checked( $republish, '1' ); ?> />
				<?php esc_html_e( 'Ripubblica anche ad ogni aggiornamento', 'instagram-stories-auto' ); ?>
			</label>
		</p>
		<?php if ( ! has_post_thumbnail( $post->ID ) ) : ?>
			<p style="color:#b32d2e;"><?php esc_html_e( 'Nessuna immagine in evidenza impostata: la Storia non potrà essere generata.', 'instagram-stories-auto' ); ?></p>
		<?php endif; ?>
		<?php $this->render_last_status( $post->ID ); ?>
		<?php
	}

	/**
	 * Mostra l'ultimo esito registrato per il post.
	 *
	 * @param int $post_id ID del post.
	 */
	private function render_last_status( $post_id ) {
		$status  = get_post_meta( $post_id, ISA_Publisher::META_LAST_STATUS, true );
		$message = get_post_meta( $post_id, ISA_Publisher::META_LAST_MESSAGE, true );
		$time    = get_post_meta( $post_id, ISA_Publisher::META_LAST_TIME, true );

		if ( ! $status ) {
			return;
		}
		$color = 'success' === $status ? '#1a7f37' : '#b32d2e';
		$when  = $time ? human_time_diff( (int) $time, current_time( 'timestamp' ) ) . ' ' . __( 'fa', 'instagram-stories-auto' ) : '';
		echo '<hr />';
		printf(
			'<p style="color:%1$s;"><strong>%2$s</strong><br />%3$s</p>',
			esc_attr( $color ),
			esc_html( 'success' === $status ? __( 'Ultima Storia: pubblicata', 'instagram-stories-auto' ) : __( 'Ultima Storia: errore', 'instagram-stories-auto' ) ),
			esc_html( trim( $message . ( $when ? ' (' . $when . ')' : '' ) ) )
		);
	}

	/**
	 * Salva le meta del post.
	 *
	 * @param int     $post_id ID del post.
	 * @param WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$settings = ISA_Settings::get_settings();
		if ( ! in_array( $post->post_type, (array) $settings['post_types'], true ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_ENABLED, isset( $_POST['isa_enabled'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_REPUBLISH, isset( $_POST['isa_republish'] ) ? '1' : '0' );
	}
}
