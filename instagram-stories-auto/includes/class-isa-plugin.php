<?php
/**
 * Classe core: collega gli hook di pubblicazione/aggiornamento ed esegue la
 * pubblicazione della Storia in modo asincrono via WP-Cron.
 *
 * @package InstagramStoriesAuto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestratore principale del plugin.
 */
class ISA_Plugin {

	const CRON_HOOK      = 'isa_publish_story_event';
	const META_INTENT    = '_isa_pending_intent';

	/**
	 * Inizializza gli hook.
	 */
	public function init() {
		load_plugin_textdomain( 'instagram-stories-auto', false, dirname( plugin_basename( ISA_PLUGIN_FILE ) ) . '/languages' );

		// Admin.
		if ( is_admin() ) {
			$settings = new ISA_Settings();
			$settings->init();

			$meta_box = new ISA_Meta_Box();
			$meta_box->init();

			add_filter( 'plugin_action_links_' . plugin_basename( ISA_PLUGIN_FILE ), array( $this, 'action_links' ) );
		}

		// Rilevamento pubblicazione / aggiornamento.
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 20, 3 );

		// Esecuzione asincrona.
		add_action( self::CRON_HOOK, array( $this, 'run_publish' ), 10, 1 );
	}

	/**
	 * Aggiunge il link alle impostazioni nella lista plugin.
	 *
	 * @param array $links Link esistenti.
	 * @return array
	 */
	public function action_links( $links ) {
		$url  = admin_url( 'options-general.php?page=' . ISA_Settings::PAGE_SLUG );
		$link = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Impostazioni', 'instagram-stories-auto' ) );
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Reagisce ai cambi di stato del post.
	 *
	 * @param string  $new_status Nuovo stato.
	 * @param string  $old_status Stato precedente.
	 * @param WP_Post $post       Post.
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		$settings = ISA_Settings::get_settings();
		if ( ! in_array( $post->post_type, (array) $settings['post_types'], true ) ) {
			return;
		}

		// Determina l'intento in base alla transizione.
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$intent = 'publish';
		} elseif ( 'publish' === $new_status && 'publish' === $old_status ) {
			$intent = 'update';
		} else {
			return; // nessun caso di nostro interesse.
		}

		// Registra l'intento; la decisione finale è presa all'esecuzione del cron,
		// quando anche le meta del box (salvate in una richiesta separata da Gutenberg)
		// sono sicuramente disponibili.
		update_post_meta( $post->ID, self::META_INTENT, $intent );

		if ( ! wp_next_scheduled( self::CRON_HOOK, array( (int) $post->ID ) ) ) {
			// Piccolo margine per far completare l'eventuale seconda richiesta dei meta box.
			wp_schedule_single_event( time() + 10, self::CRON_HOOK, array( (int) $post->ID ) );
		}
	}

	/**
	 * Esegue la pubblicazione (chiamata dal cron).
	 *
	 * @param int $post_id ID del post.
	 */
	public function run_publish( $post_id ) {
		$post_id = (int) $post_id;
		$intent  = get_post_meta( $post_id, self::META_INTENT, true );
		delete_post_meta( $post_id, self::META_INTENT );

		if ( ! $intent ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$settings = ISA_Settings::get_settings();
		if ( ! in_array( $post->post_type, (array) $settings['post_types'], true ) ) {
			return;
		}

		if ( ! $this->should_publish( $post_id, $intent, $settings ) ) {
			return;
		}

		/**
		 * Consente di annullare la pubblicazione della Storia a runtime.
		 *
		 * @param bool   $publish Se procedere.
		 * @param int    $post_id ID del post.
		 * @param string $intent  'publish' oppure 'update'.
		 */
		if ( ! apply_filters( 'isa_should_publish_story', true, $post_id, $intent ) ) {
			return;
		}

		$publisher = new ISA_Publisher( $settings );
		$publisher->publish_for_post( $post_id );
	}

	/**
	 * Decide se pubblicare in base a meta del post e default globali.
	 *
	 * @param int    $post_id  ID del post.
	 * @param string $intent   'publish' | 'update'.
	 * @param array  $settings Impostazioni.
	 * @return bool
	 */
	private function should_publish( $post_id, $intent, $settings ) {
		$enabled = get_post_meta( $post_id, ISA_Meta_Box::META_ENABLED, true );
		if ( '' === $enabled ) {
			$enabled = $settings['default_enabled'] ? '1' : '0';
		}
		if ( '1' !== $enabled ) {
			return false;
		}

		if ( 'update' === $intent ) {
			$republish = get_post_meta( $post_id, ISA_Meta_Box::META_REPUBLISH, true );
			if ( '' === $republish ) {
				$republish = $settings['republish_on_update'] ? '1' : '0';
			}
			if ( '1' !== $republish ) {
				return false;
			}
		}

		return true;
	}
}
