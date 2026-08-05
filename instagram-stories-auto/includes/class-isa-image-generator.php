<?php
/**
 * Genera l'immagine 1080x1920 della Storia a partire dall'immagine in evidenza,
 * sovrapponendo titolo, call-to-action e link/handle.
 *
 * @package InstagramStoriesAuto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compositore immagini basato su GD.
 */
class ISA_Image_Generator {

	const WIDTH  = 1080;
	const HEIGHT = 1920;

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
	 * Verifica che GD sia disponibile con il supporto necessario.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' );
	}

	/**
	 * Genera l'immagine della Storia per un post.
	 *
	 * @param int $post_id ID del post.
	 * @return array|WP_Error Array con 'path' e 'url' del file generato, oppure WP_Error.
	 */
	public function generate( $post_id ) {
		if ( ! self::is_supported() ) {
			return new WP_Error( 'isa_no_gd', __( 'L\'estensione GD di PHP non è disponibile: impossibile generare l\'immagine.', 'instagram-stories-auto' ) );
		}

		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			return new WP_Error( 'isa_no_thumbnail', __( 'Il post non ha un\'immagine in evidenza.', 'instagram-stories-auto' ) );
		}

		$source_path = get_attached_file( $thumb_id );
		if ( ! $source_path || ! file_exists( $source_path ) ) {
			return new WP_Error( 'isa_thumbnail_missing', __( 'Il file dell\'immagine in evidenza non è stato trovato sul server.', 'instagram-stories-auto' ) );
		}

		$source = $this->load_image( $source_path );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		// Canvas finale.
		$canvas = imagecreatetruecolor( self::WIDTH, self::HEIGHT );

		// Sfondo: versione "cover" sfocata/scurita dell'immagine, così i bordi non restano neri.
		$this->draw_cover_background( $canvas, $source );

		// Immagine in evidenza inserita "contain" nella metà superiore per non tagliarla.
		$this->draw_contain_foreground( $canvas, $source );

		imagedestroy( $source );

		// Gradiente scuro in basso per la leggibilità del testo.
		$this->draw_bottom_gradient( $canvas );

		// Testi: kicker (handle), titolo, call-to-action con link.
		$this->draw_texts( $canvas, $post_id );

		// Salvataggio su file.
		$saved = $this->save( $canvas, $post_id );
		imagedestroy( $canvas );

		return $saved;
	}

	/**
	 * Carica un'immagine da file rilevandone il tipo.
	 *
	 * @param string $path Percorso file.
	 * @return resource|GdImage|WP_Error
	 */
	private function load_image( $path ) {
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $info ) {
			return new WP_Error( 'isa_unreadable_image', __( 'Impossibile leggere l\'immagine in evidenza.', 'instagram-stories-auto' ) );
		}

		switch ( $info[2] ) {
			case IMAGETYPE_JPEG:
				$img = @imagecreatefromjpeg( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				break;
			case IMAGETYPE_PNG:
				$img = @imagecreatefrompng( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				break;
			case IMAGETYPE_GIF:
				$img = @imagecreatefromgif( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				break;
			case IMAGETYPE_WEBP:
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					$img = @imagecreatefromwebp( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				} else {
					$img = false;
				}
				break;
			default:
				$img = false;
		}

		if ( ! $img ) {
			return new WP_Error( 'isa_unsupported_image', __( 'Formato dell\'immagine in evidenza non supportato.', 'instagram-stories-auto' ) );
		}
		return $img;
	}

	/**
	 * Disegna uno sfondo "cover" scurito che riempie tutto il canvas.
	 *
	 * @param resource|GdImage $canvas Canvas destinazione.
	 * @param resource|GdImage $source Immagine sorgente.
	 */
	private function draw_cover_background( $canvas, $source ) {
		$sw = imagesx( $source );
		$sh = imagesy( $source );

		$scale = max( self::WIDTH / $sw, self::HEIGHT / $sh );
		$dw    = (int) round( $sw * $scale );
		$dh    = (int) round( $sh * $scale );
		$dx    = (int) round( ( self::WIDTH - $dw ) / 2 );
		$dy    = (int) round( ( self::HEIGHT - $dh ) / 2 );

		imagecopyresampled( $canvas, $source, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh );

		// Scurisce lo sfondo con un velo semitrasparente.
		$overlay = imagecolorallocatealpha( $canvas, 8, 10, 14, 75 );
		imagefilledrectangle( $canvas, 0, 0, self::WIDTH, self::HEIGHT, $overlay );
	}

	/**
	 * Inserisce l'immagine in evidenza intera ("contain") nella parte superiore.
	 *
	 * @param resource|GdImage $canvas Canvas destinazione.
	 * @param resource|GdImage $source Immagine sorgente.
	 */
	private function draw_contain_foreground( $canvas, $source ) {
		$sw = imagesx( $source );
		$sh = imagesy( $source );

		// Area riservata all'immagine (lascia spazio in basso per il testo).
		$area_top    = 120;
		$area_height = 1250;
		$area_width  = self::WIDTH - 80; // margini laterali di 40px.
		$area_left   = 40;

		$scale = min( $area_width / $sw, $area_height / $sh );
		$dw    = (int) round( $sw * $scale );
		$dh    = (int) round( $sh * $scale );
		$dx    = (int) round( $area_left + ( $area_width - $dw ) / 2 );
		$dy    = (int) round( $area_top + ( $area_height - $dh ) / 2 );

		imagecopyresampled( $canvas, $source, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh );
	}

	/**
	 * Disegna un gradiente verticale scuro nella parte bassa del canvas.
	 *
	 * @param resource|GdImage $canvas Canvas.
	 */
	private function draw_bottom_gradient( $canvas ) {
		$start = 1080; // inizio del gradiente.
		$end   = self::HEIGHT;
		for ( $y = $start; $y < $end; $y++ ) {
			$ratio = ( $y - $start ) / ( $end - $start );
			$alpha = (int) round( 105 - ( $ratio * 105 ) ); // da semitrasparente a opaco.
			$alpha = max( 0, min( 127, $alpha ) );
			$color = imagecolorallocatealpha( $canvas, 8, 10, 14, $alpha );
			imagefilledrectangle( $canvas, 0, $y, self::WIDTH, $y, $color );
		}
	}

	/**
	 * Disegna i testi sovraimpressi.
	 *
	 * @param resource|GdImage $canvas  Canvas.
	 * @param int              $post_id ID del post.
	 */
	private function draw_texts( $canvas, $post_id ) {
		$font = $this->resolve_font_path();

		$title    = html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' );
		$cta_text = isset( $this->settings['cta_text'] ) ? $this->settings['cta_text'] : __( 'Leggi l\'articolo', 'instagram-stories-auto' );
		$handle   = isset( $this->settings['handle'] ) ? trim( $this->settings['handle'] ) : '';
		$link     = $this->get_display_link( $post_id );

		$accent = $this->hex_to_rgb( isset( $this->settings['accent_color'] ) ? $this->settings['accent_color'] : '#E1306C' );
		$white  = array( 245, 246, 248 );

		if ( $font ) {
			$this->draw_texts_ttf( $canvas, $font, $title, $cta_text, $handle, $link, $accent, $white );
		} else {
			$this->draw_texts_builtin( $canvas, $title, $cta_text, $handle, $link, $accent, $white );
		}
	}

	/**
	 * Rendering testi con font TrueType (percorso ideale).
	 *
	 * @param resource|GdImage $canvas   Canvas.
	 * @param string           $font     Percorso al font TTF.
	 * @param string           $title    Titolo.
	 * @param string           $cta_text Testo CTA.
	 * @param string           $handle   Handle account.
	 * @param string           $link     Link visualizzato.
	 * @param array            $accent   Colore accento RGB.
	 * @param array            $white    Colore chiaro RGB.
	 */
	private function draw_texts_ttf( $canvas, $font, $title, $cta_text, $handle, $link, $accent, $white ) {
		$white_c  = imagecolorallocate( $canvas, $white[0], $white[1], $white[2] );
		$accent_c = imagecolorallocate( $canvas, $accent[0], $accent[1], $accent[2] );

		$margin = 70;
		$max_w  = self::WIDTH - ( $margin * 2 );

		// --- Titolo: scegli una dimensione che entri in massimo 3 righe. ---
		$title_size = 56;
		$max_lines  = 3;
		$lines      = $this->wrap_text_ttf( $font, $title_size, $title, $max_w );
		while ( $title_size > 40 && count( $lines ) > $max_lines ) {
			$title_size -= 4;
			$lines       = $this->wrap_text_ttf( $font, $title_size, $title, $max_w );
		}
		$line_h = (int) round( $title_size * 1.28 );
		if ( count( $lines ) > $max_lines ) {
			$lines                  = array_slice( $lines, 0, $max_lines );
			$lines[ $max_lines - 1 ] = $this->ellipsize_ttf( $font, $title_size, $lines[ $max_lines - 1 ] . '…', $max_w );
		}

		// --- Handle. ---
		$handle_txt  = '';
		$handle_size = 34;
		if ( '' !== $handle ) {
			$handle_txt = ( '@' === substr( $handle, 0, 1 ) ) ? $handle : '@' . $handle;
		}

		// --- Call-to-action: testo + link, ridotto/troncato per non sforare. ---
		$cta_all = trim( $cta_text . ( '' !== $link ? '   ·   ' . $link : '' ) );
		$cta_size = 36;
		$pad_x    = 40;
		$pad_y    = 24;
		while ( $cta_size > 24 && ( $this->measure_ttf( $font, $cta_size, $cta_all ) + ( $pad_x * 2 ) ) > $max_w ) {
			$cta_size -= 2;
		}
		if ( ( $this->measure_ttf( $font, $cta_size, $cta_all ) + ( $pad_x * 2 ) ) > $max_w ) {
			$cta_all = $this->ellipsize_ttf( $font, $cta_size, $cta_all, $max_w - ( $pad_x * 2 ) );
		}
		$cta_box  = imagettfbbox( $cta_size, 0, $font, $cta_all );
		$cta_th   = abs( $cta_box[7] - $cta_box[1] );
		$pill_h   = $cta_th + ( $pad_y * 2 );

		// --- Ancoraggio dal basso: calcola l'altezza del blocco e posizionalo. ---
		$gap_handle    = 22;
		$gap_pill      = 40;
		$bottom_margin = 96;

		$handle_slot = '' !== $handle_txt ? $handle_size + $gap_handle : 0;
		$title_slot  = count( $lines ) * $line_h;
		$block_h     = $handle_slot + $title_slot + $gap_pill + $pill_h;
		$cursor      = self::HEIGHT - $bottom_margin - $block_h;

		// Handle.
		if ( '' !== $handle_txt ) {
			$this->ttf_line( $canvas, $font, $handle_size, $margin, $cursor + $handle_size, $handle_txt, $accent_c, true );
			$cursor += $handle_size + $gap_handle;
		}

		// Righe del titolo.
		foreach ( $lines as $line ) {
			$this->ttf_line( $canvas, $font, $title_size, $margin, $cursor + $title_size, $line, $white_c, true );
			$cursor += $line_h;
		}

		// Pill della call-to-action.
		$cursor += $gap_pill;
		$this->draw_cta_pill( $canvas, $font, $cta_size, $margin, $cursor, $cta_all, $cta_box, $pad_x, $pad_y, $accent );
	}

	/**
	 * Disegna una singola riga TTF con leggera ombra per leggibilità.
	 *
	 * @param resource|GdImage $canvas Canvas.
	 * @param string           $font   Percorso font.
	 * @param int              $size   Dimensione.
	 * @param int              $x      X.
	 * @param int              $y      Y (baseline).
	 * @param string           $text   Testo.
	 * @param int              $color  Colore GD.
	 * @param bool             $shadow Aggiunge ombra.
	 */
	private function ttf_line( $canvas, $font, $size, $x, $y, $text, $color, $shadow = false ) {
		if ( $shadow ) {
			$shadow_c = imagecolorallocatealpha( $canvas, 0, 0, 0, 60 );
			imagettftext( $canvas, $size, 0, $x + 2, $y + 2, $shadow_c, $font, $text );
		}
		imagettftext( $canvas, $size, 0, $x, $y, $color, $font, $text );
	}

	/**
	 * Disegna il "pill" della call-to-action a partire dal bordo superiore.
	 *
	 * @param resource|GdImage $canvas   Canvas.
	 * @param string           $font     Font.
	 * @param int              $size     Dimensione testo (già adattata).
	 * @param int              $x        X sinistra.
	 * @param int              $top      Y del bordo superiore del pill.
	 * @param string           $text     Testo (già adattato).
	 * @param array            $box      Bounding box del testo (imagettfbbox).
	 * @param int              $pad_x    Padding orizzontale.
	 * @param int              $pad_y    Padding verticale.
	 * @param array            $accent   Colore accento RGB.
	 */
	private function draw_cta_pill( $canvas, $font, $size, $x, $top, $text, $box, $pad_x, $pad_y, $accent ) {
		$tw = abs( $box[2] - $box[0] );
		$th = abs( $box[7] - $box[1] );

		$x1 = $x;
		$y1 = $top;
		$x2 = min( self::WIDTH - 40, $x + $tw + ( $pad_x * 2 ) );
		$y2 = $top + $th + ( $pad_y * 2 );

		$accent_c = imagecolorallocate( $canvas, $accent[0], $accent[1], $accent[2] );
		$this->filled_rounded_rect( $canvas, $x1, $y1, $x2, $y2, 22, $accent_c );

		// Baseline del testo, centrato verticalmente nel pill.
		$baseline = $top + $pad_y - $box[7];
		$text_c   = imagecolorallocate( $canvas, 255, 255, 255 );
		imagettftext( $canvas, $size, 0, $x1 + $pad_x, $baseline, $text_c, $font, $text );
	}

	/**
	 * Larghezza in pixel di un testo TTF.
	 *
	 * @param string $font Percorso font.
	 * @param int    $size Dimensione.
	 * @param string $text Testo.
	 * @return int
	 */
	private function measure_ttf( $font, $size, $text ) {
		$box = imagettfbbox( $size, 0, $font, $text );
		return abs( $box[2] - $box[0] );
	}

	/**
	 * Tronca un testo TTF con ellissi finché non entra nella larghezza data.
	 *
	 * @param string $font  Percorso font.
	 * @param int    $size  Dimensione.
	 * @param string $text  Testo.
	 * @param int    $max_w Larghezza massima.
	 * @return string
	 */
	private function ellipsize_ttf( $font, $size, $text, $max_w ) {
		if ( $this->measure_ttf( $font, $size, $text ) <= $max_w ) {
			return $text;
		}
		$text = rtrim( $text, '…' );
		while ( '' !== $text && $this->measure_ttf( $font, $size, $text . '…' ) > $max_w ) {
			$text = mb_substr( $text, 0, mb_strlen( $text ) - 1 );
		}
		return $text . '…';
	}

	/**
	 * Rettangolo pieno con angoli arrotondati.
	 *
	 * @param resource|GdImage $img    Immagine.
	 * @param int              $x1     X1.
	 * @param int              $y1     Y1.
	 * @param int              $x2     X2.
	 * @param int              $y2     Y2.
	 * @param int              $radius Raggio.
	 * @param int              $color  Colore GD.
	 */
	private function filled_rounded_rect( $img, $x1, $y1, $x2, $y2, $radius, $color ) {
		imagefilledrectangle( $img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color );
		imagefilledrectangle( $img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color );
		imagefilledellipse( $img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color );
		imagefilledellipse( $img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color );
		imagefilledellipse( $img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color );
		imagefilledellipse( $img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color );
	}

	/**
	 * A capo automatico per testo TTF.
	 *
	 * @param string $font  Percorso font.
	 * @param int    $size  Dimensione.
	 * @param string $text  Testo.
	 * @param int    $max_w Larghezza massima.
	 * @return array         Righe.
	 */
	private function wrap_text_ttf( $font, $size, $text, $max_w ) {
		$words = preg_split( '/\s+/', trim( $text ) );
		$lines = array();
		$line  = '';

		foreach ( $words as $word ) {
			$try = '' === $line ? $word : $line . ' ' . $word;
			$box = imagettfbbox( $size, 0, $font, $try );
			$w   = abs( $box[2] - $box[0] );
			if ( $w > $max_w && '' !== $line ) {
				$lines[] = $line;
				$line    = $word;
			} else {
				$line = $try;
			}
		}
		if ( '' !== $line ) {
			$lines[] = $line;
		}
		return $lines;
	}

	/**
	 * Fallback: rendering testi con font bitmap integrato (nessun TTF disponibile).
	 *
	 * @param resource|GdImage $canvas   Canvas.
	 * @param string           $title    Titolo.
	 * @param string           $cta_text Testo CTA.
	 * @param string           $handle   Handle.
	 * @param string           $link     Link.
	 * @param array            $accent   Colore accento RGB.
	 * @param array            $white    Colore chiaro RGB.
	 */
	private function draw_texts_builtin( $canvas, $title, $cta_text, $handle, $link, $accent, $white ) {
		$white_c  = imagecolorallocate( $canvas, $white[0], $white[1], $white[2] );
		$accent_c = imagecolorallocate( $canvas, $accent[0], $accent[1], $accent[2] );
		$font     = 5; // font bitmap più grande di GD.
		$char_w   = imagefontwidth( $font );
		$line_h   = imagefontheight( $font ) + 10;
		$margin    = 70;
		$max_chars = (int) floor( ( self::WIDTH - $margin * 2 ) / $char_w );

		$y = 1480;
		if ( '' !== $handle ) {
			$handle_txt = ( '@' === substr( $handle, 0, 1 ) ) ? $handle : '@' . $handle;
			imagestring( $canvas, $font, $margin, $y, $handle_txt, $accent_c );
			$y += $line_h + 10;
		}

		$wrapped = wordwrap( $title, $max_chars, "\n", true );
		foreach ( array_slice( explode( "\n", $wrapped ), 0, 5 ) as $line ) {
			imagestring( $canvas, $font, $margin, $y, $line, $white_c );
			$y += $line_h;
		}

		$y      += 20;
		$cta_all = trim( $cta_text . ( '' !== $link ? '  ·  ' . $link : '' ) );
		imagefilledrectangle( $canvas, $margin, $y, $margin + ( strlen( $cta_all ) * $char_w ) + 40, $y + $line_h + 20, $accent_c );
		imagestring( $canvas, $font, $margin + 20, $y + 12, $cta_all, imagecolorallocate( $canvas, 255, 255, 255 ) );
	}

	/**
	 * Restituisce il link da mostrare (opzione personalizzata o permalink).
	 *
	 * @param int $post_id ID del post.
	 * @return string
	 */
	private function get_display_link( $post_id ) {
		if ( ! empty( $this->settings['link_text'] ) ) {
			return trim( $this->settings['link_text'] );
		}
		$permalink = get_permalink( $post_id );
		$host      = wp_parse_url( $permalink, PHP_URL_HOST );
		return $host ? $host : '';
	}

	/**
	 * Individua un font TTF utilizzabile (impostazione o percorsi comuni).
	 *
	 * @return string|false Percorso font o false.
	 */
	private function resolve_font_path() {
		if ( ! function_exists( 'imagettftext' ) ) {
			return false;
		}

		if ( ! empty( $this->settings['font_path'] ) && file_exists( $this->settings['font_path'] ) ) {
			return $this->settings['font_path'];
		}

		$candidates = array(
			'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
			'/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/dejavu/DejaVuSans.ttf',
			'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
			'/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
			'/Library/Fonts/Arial.ttf',
			'/System/Library/Fonts/Supplemental/Arial.ttf',
			'C:\\Windows\\Fonts\\arialbd.ttf',
			'C:\\Windows\\Fonts\\arial.ttf',
		);
		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
		return false;
	}

	/**
	 * Converte un colore esadecimale in array RGB.
	 *
	 * @param string $hex Colore es. #E1306C.
	 * @return array       [r, g, b].
	 */
	private function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return array( 225, 48, 108 ); // fallback rosa Instagram.
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Salva il canvas su file JPEG nella cartella uploads/isa-stories.
	 *
	 * @param resource|GdImage $canvas  Canvas.
	 * @param int              $post_id ID del post.
	 * @return array|WP_Error           Array con 'path' e 'url' oppure WP_Error.
	 */
	private function save( $canvas, $post_id ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'isa_upload_dir', $upload['error'] );
		}

		$dir = trailingslashit( $upload['basedir'] ) . 'isa-stories';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Nome file unico: evita che Instagram usi una versione in cache a ogni ripubblicazione.
		$filename = sprintf( 'story-%d-%s.jpg', $post_id, wp_generate_password( 8, false, false ) );
		$path     = trailingslashit( $dir ) . $filename;
		$url      = trailingslashit( $upload['baseurl'] ) . 'isa-stories/' . $filename;

		if ( ! imagejpeg( $canvas, $path, 90 ) ) {
			return new WP_Error( 'isa_save_failed', __( 'Impossibile salvare l\'immagine generata.', 'instagram-stories-auto' ) );
		}

		return array(
			'path' => $path,
			'url'  => $url,
		);
	}
}
