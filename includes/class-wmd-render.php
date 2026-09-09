<?php
/**
 * Plokkide renderdus meilikõlblikuks HTML-iks (tabelid + reasisesed stiilid).
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderdaja. Sama loogika on JS-versioonis assets/renderer.js elava eelvaate jaoks.
 */
class WMD_Render {

	/**
	 * Kas WooCommerce'i sisu ümber pannakse markerid.
	 *
	 * Kujundaja tõmbab WooCommerce'i osa eraldi, et kanvas saaks näidata päris
	 * sisu. Markerid on ainult selle päringu ajal sees, päris meili nad ei jõua.
	 *
	 * @var bool
	 */
	public static $mark_wc = false;

	/**
	 * Markerid, mille vahelt kujundaja WooCommerce'i osa välja lõikab.
	 */
	const WC_START = '<!--WMD_WC_START-->';
	const WC_END   = '<!--WMD_WC_END-->';

	/**
	 * Meili päis kuni sisuosa avamiseni.
	 *
	 * @param string $email_heading WooCommerce'i pealkiri.
	 * @param string $email_id      WC_Email id.
	 * @param array  $ctx           Märgendite kontekst.
	 * @return string
	 */
	public static function header_html( $email_heading, $email_id, $ctx ) {
		$brand    = WMD_Design::brand();
		$design   = WMD_Design::get();
		$settings = WMD_Design::email( $email_id );

		$width  = (int) $brand['width'];
		$pad    = (int) $brand['pad_x'];
		$radius = (int) $brand['radius'];
		$hdr_bg = '' !== $brand['header_bg'] ? $brand['header_bg'] : $brand['body_bg'];

		$heading = '' !== trim( (string) $settings['heading'] )
			? WMD_Tags::replace( $settings['heading'], $ctx )
			: $email_heading;

		$out  = '<!DOCTYPE html><html ' . get_language_attributes() . '><head>';
		$out .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
		$out .= '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
		$out .= '<title>' . esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '</title>';
		$out .= '</head><body ' . self::attr(
			array(
				'style' => self::style(
					array(
						'margin'                  => '0',
						'padding'                 => '0',
						'background-color'        => $brand['page_bg'],
						'font-family'             => $brand['font_family'],
						'-webkit-font-smoothing'  => 'antialiased',
						'-webkit-text-size-adjust' => '100%',
					)
				),
			)
		) . '>';

		$out .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . esc_html( wp_strip_all_tags( $heading ) ) . '</div>';

		$out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" ' . self::attr(
			array(
				'style' => self::style(
					array(
						'background-color' => $brand['page_bg'],
						'width'            => '100%',
						'margin'           => '0',
						'padding'          => '24px 12px',
					)
				),
			)
		) . '><tr><td align="center" valign="top">';

		// Kaart.
		$out .= '<table role="presentation" width="' . $width . '" cellpadding="0" cellspacing="0" border="0" class="wmd-card" ' . self::attr(
			array(
				'style' => self::style(
					array(
						'width'            => $width . 'px',
						'max-width'        => '100%',
						'background-color' => $brand['body_bg'],
						'border-radius'    => $radius . 'px',
						'border'           => '1px solid ' . $brand['border_color'],
						'overflow'         => 'hidden',
					)
				),
			)
		) . '>';

		// Logo ja päise plokid.
		$head_inner = self::logo( $brand, $ctx ) . self::blocks( $design['header'], $brand, $ctx );
		if ( '' !== $head_inner ) {
			$out .= '<tr><td ' . self::attr(
				array(
					'style' => self::style(
						array(
							'padding'          => $pad . 'px ' . $pad . 'px 0 ' . $pad . 'px',
							'background-color' => $hdr_bg,
						)
					),
				)
			) . '>';
			$out .= $head_inner;
			$out .= '</td></tr>';
		}

		// Sisuosa avamine.
		$out .= '<tr><td ' . self::attr(
			array(
				'style' => self::style(
					array(
						'padding'     => $pad . 'px',
						'font-family' => $brand['font_family'],
						'font-size'   => (int) $brand['base_size'] . 'px',
						'line-height' => '1.6',
						'color'       => $brand['text_color'],
					)
				),
			)
		) . '>';

		if ( '' !== trim( (string) $heading ) ) {
			$out .= '<h1 ' . self::attr(
				array(
					'style' => self::style(
						array(
							'margin'      => '0 0 14px 0',
							'padding'     => '0',
							'font-family' => $brand['font_family'],
							'font-size'   => (int) $brand['heading_size'] . 'px',
							'line-height' => '1.25',
							'font-weight' => '700',
							'color'       => $brand['heading_color'],
						)
					),
				)
			) . '>' . wp_kses_post( $heading ) . '</h1>';
		}

		// Täisrežiimis on kogu sisu keha plokkides, seega ümbritsevaid ei renderdata.
		if ( 'full' !== $settings['mode'] && ! empty( $settings['before'] ) ) {
			$out .= self::blocks( $settings['before'], $brand, $ctx );
		}

		$out .= '<div class="wmd-wc-content">';

		if ( self::$mark_wc ) {
			$out .= self::WC_START;
		}

		return $out;
	}

	/**
	 * Meili jalus alates sisuosa sulgemisest.
	 *
	 * @param string $email_id WC_Email id.
	 * @param array  $ctx      Märgendite kontekst.
	 * @return string
	 */
	public static function footer_html( $email_id, $ctx ) {
		$brand    = WMD_Design::brand();
		$design   = WMD_Design::get();
		$settings = WMD_Design::email( $email_id );

		$width = (int) $brand['width'];
		$pad   = (int) $brand['pad_x'];

		$out = self::$mark_wc ? self::WC_END . '</div>' : '</div>';

		if ( 'full' !== $settings['mode'] && ! empty( $settings['after'] ) ) {
			$out .= self::blocks( $settings['after'], $brand, $ctx );
		}

		$out .= '</td></tr></table>';

		// Jalus kaardi all.
		if ( ! empty( $design['footer'] ) ) {
			$out .= '<table role="presentation" width="' . $width . '" cellpadding="0" cellspacing="0" border="0" class="wmd-card" ' . self::attr(
				array(
					'style' => self::style(
						array(
							'width'     => $width . 'px',
							'max-width' => '100%',
						)
					),
				)
			) . '><tr><td ' . self::attr(
				array(
					'style' => self::style(
						array(
							'padding'     => '18px ' . $pad . 'px 4px ' . $pad . 'px',
							'font-family' => $brand['font_family'],
							'font-size'   => '13px',
							'line-height' => '1.6',
							'color'       => $brand['muted_color'],
						)
					),
				)
			) . '>';
			$out .= self::blocks( $design['footer'], $brand, $ctx, $brand['muted_color'] );
			$out .= '</td></tr></table>';
		}

		$out .= '</td></tr></table></body></html>';

		return $out;
	}

	/**
	 * Brändi logo päise ülaosas. Renderdub ainult siis, kui logo on valitud.
	 *
	 * @param array $brand Bränd.
	 * @param array $ctx   Kontekst.
	 * @return string
	 */
	protected static function logo( $brand, $ctx ) {
		if ( empty( $brand['logo_url'] ) ) {
			return '';
		}

		$width  = (int) $brand['logo_width'];
		$align  = in_array( $brand['logo_align'], array( 'left', 'center', 'right' ), true ) ? $brand['logo_align'] : 'center';
		$margin = 'center' === $align ? '0 auto' : ( 'right' === $align ? '0 0 0 auto' : '0' );

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">'
			. '<tr><td style="text-align:' . esc_attr( $align ) . ';padding:0 0 6px 0;">'
			. '<img src="' . esc_url( $brand['logo_url'] ) . '" alt="' . esc_attr( WMD_Tags::replace( '{{site_title}}', $ctx ) ) . '" width="' . $width . '" ' . self::attr(
				array(
					'style' => self::style(
						array(
							'width'     => $width . 'px',
							'max-width' => '100%',
							'height'    => 'auto',
							'display'   => 'block',
							'border'    => '0',
							'margin'    => $margin,
						)
					),
				)
			) . ' /></td></tr></table>';
	}

	/**
	 * Plokinimekiri tabelina.
	 *
	 * @param array  $blocks        Plokid.
	 * @param array  $brand         Brändi väärtused.
	 * @param array  $ctx           Märgendite kontekst.
	 * @param string $default_color Vaikimisi tekstivärv (jaluses hall).
	 * @return string
	 */
	public static function blocks( $blocks, $brand, $ctx, $default_color = '' ) {
		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return '';
		}

		$rows = '';
		foreach ( $blocks as $block ) {
			if ( empty( $block['type'] ) ) {
				continue;
			}
			$rows .= self::block( $block, $brand, $ctx, $default_color );
		}

		if ( '' === $rows ) {
			return '';
		}

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">' . $rows . '</table>';
	}

	/**
	 * Üks plokk reana.
	 *
	 * @param array  $block         Plokk.
	 * @param array  $brand         Brändi väärtused.
	 * @param array  $ctx           Märgendite kontekst.
	 * @param string $default_color Vaikimisi tekstivärv.
	 * @return string
	 */
	protected static function block( $block, $brand, $ctx, $default_color = '' ) {
		if ( ! self::visible( $block, $ctx ) ) {
			return '';
		}

		$p    = isset( $block['props'] ) ? $block['props'] : array();
		$type = $block['type'];
		$pad  = isset( $p['pad'] ) ? (int) $p['pad'] : 12;
		$font = $brand['font_family'];
		$body = '';
		$cell = array(
			'padding'     => $pad . 'px 0',
			'font-family' => $font,
		);

		switch ( $type ) {
			case 'heading':
				$sizes = array(
					'lg' => (int) $brand['heading_size'],
					'md' => max( 17, (int) round( $brand['heading_size'] * 0.72 ) ),
					'sm' => max( 14, (int) round( $brand['heading_size'] * 0.58 ) ),
				);
				$size  = isset( $sizes[ $p['size'] ] ) ? $sizes[ $p['size'] ] : $sizes['md'];
				$color = '' !== $p['color'] ? $p['color'] : $brand['heading_color'];

				$cell['text-align'] = $p['align'];
				$body               = '<div ' . self::attr(
					array(
						'style' => self::style(
							array(
								'font-family' => $font,
								'font-size'   => $size . 'px',
								'line-height' => '1.3',
								'font-weight' => '700',
								'color'       => $color,
							)
						),
					)
				) . '>' . esc_html( WMD_Tags::replace( $p['text'], $ctx ) ) . '</div>';
				break;

			case 'text':
				$size  = ! empty( $p['size'] ) ? (int) $p['size'] : (int) $brand['base_size'];
				$color = '' !== $p['color'] ? $p['color'] : ( '' !== $default_color ? $default_color : $brand['text_color'] );

				$cell['text-align'] = $p['align'];
				$body               = '<div ' . self::attr(
					array(
						'style' => self::style(
							array(
								'font-family' => $font,
								'font-size'   => $size . 'px',
								'line-height' => '1.6',
								'color'       => $color,
							)
						),
					)
				) . '>' . self::linkify( WMD_Tags::replace( $p['html'], $ctx ), $brand ) . '</div>';
				break;

			case 'button':
				$label = esc_html( WMD_Tags::replace( $p['label'], $ctx ) );
				$url   = WMD_Tags::replace( $p['url'], $ctx );
				if ( '' === trim( $label ) ) {
					return '';
				}

				$outline = 'outline' === $p['style'];
				$btn     = array(
					'display'          => 'inline-block',
					'font-family'      => $font,
					'font-size'        => max( 14, (int) $brand['base_size'] ) . 'px',
					'font-weight'      => '600',
					'line-height'      => '1',
					'padding'          => '14px 26px',
					'border-radius'    => (int) $brand['btn_radius'] . 'px',
					'text-decoration'  => 'none',
					'background-color' => $outline ? 'transparent' : $brand['btn_bg'],
					'color'            => $outline ? $brand['btn_bg'] : $brand['btn_text'],
					'border'           => '2px solid ' . $brand['btn_bg'],
					'mso-padding-alt'  => '14px 26px',
				);

				if ( ! empty( $p['full'] ) ) {
					$btn['display']    = 'block';
					$btn['text-align'] = 'center';
				}

				$cell['text-align'] = $p['align'];
				$body               = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" ' . self::attr( array( 'style' => self::style( $btn ) ) ) . '>' . $label . '</a>';
				break;

			case 'image':
				$src = WMD_Tags::replace( $p['url'], $ctx );
				if ( '' === trim( $src ) ) {
					return '';
				}

				$img = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $p['alt'] ) . '" width="' . (int) $p['width'] . '" ' . self::attr(
					array(
						'style' => self::style(
							array(
								'width'     => (int) $p['width'] . 'px',
								'max-width' => '100%',
								'height'    => 'auto',
								'display'   => 'block',
								'border'    => '0',
								'margin'    => 'center' === $p['align'] ? '0 auto' : ( 'right' === $p['align'] ? '0 0 0 auto' : '0' ),
							)
						),
					)
				) . ' />';

				if ( '' !== trim( (string) $p['link'] ) ) {
					$img = '<a href="' . esc_url( WMD_Tags::replace( $p['link'], $ctx ) ) . '" target="_blank" rel="noopener">' . $img . '</a>';
				}

				$cell['text-align'] = $p['align'];
				$body               = $img;
				break;

			case 'divider':
				$color = '' !== $p['color'] ? $p['color'] : $brand['border_color'];
				$body  = '<div style="border-top:1px solid ' . esc_attr( $color ) . ';font-size:0;line-height:0;">&nbsp;</div>';
				break;

			case 'spacer':
				$h    = (int) $p['height'];
				$cell = array( 'padding' => '0', 'height' => $h . 'px' );
				$body = '<div style="height:' . $h . 'px;font-size:0;line-height:0;">&nbsp;</div>';
				break;

			case 'columns':
				$size  = (int) $brand['base_size'];
				$color = '' !== $default_color ? $default_color : $brand['text_color'];
				$col   = self::style(
					array(
						'font-family' => $font,
						'font-size'   => $size . 'px',
						'line-height' => '1.6',
						'color'       => $color,
						'vertical-align' => 'top',
						'width'       => '50%',
					)
				);

				$body = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr>'
					. '<td class="wmd-col" ' . self::attr( array( 'style' => $col . 'padding-right:10px;' ) ) . '>' . self::linkify( WMD_Tags::replace( $p['left'], $ctx ), $brand ) . '</td>'
					. '<td class="wmd-col" ' . self::attr( array( 'style' => $col . 'padding-left:10px;' ) ) . '>' . self::linkify( WMD_Tags::replace( $p['right'], $ctx ), $brand ) . '</td>'
					. '</tr></table>';
				break;

			case 'social':
				// Päris logod PNG-na. Meilikliendid ei renderda SVG-d ega
				// data-URI-sid, seega peavad ikoonid tulema päris aadressilt.
				$nets  = array(
					'facebook'  => 'Facebook',
					'instagram' => 'Instagram',
					'youtube'   => 'YouTube',
					'linkedin'  => 'LinkedIn',
				);
				$size  = isset( $p['size'] ) ? (int) $p['size'] : 28;
				$gap   = isset( $p['gap'] ) ? (int) $p['gap'] : 10;
				$cells = '';

				foreach ( $nets as $key => $name ) {
					if ( empty( $p[ $key ] ) ) {
						continue;
					}

					$cells .= '<td style="padding:0 ' . (int) round( $gap / 2 ) . 'px;">'
						. '<a href="' . esc_url( $p[ $key ] ) . '" target="_blank" rel="noopener" style="display:block;text-decoration:none;">'
						. '<img src="' . esc_url( WMD_URL . 'assets/social/' . $key . '.png' ) . '" alt="' . esc_attr( $name ) . '" '
						. 'width="' . $size . '" height="' . $size . '" '
						. 'style="width:' . $size . 'px;height:' . $size . 'px;display:block;border:0;" /></a></td>';
				}

				if ( '' === $cells ) {
					return '';
				}

				$cell['text-align'] = 'center';
				$body               = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr>' . $cells . '</tr></table>';
				break;

			case 'html':
				$code = trim( (string) $p['code'] );
				if ( '' === $code ) {
					return '';
				}
				$body = WMD_Tags::replace( $code, $ctx );
				break;

			case 'order_items':
				$order = isset( $ctx['__order'] ) ? $ctx['__order'] : null;
				$rows  = $order ? self::order_items_data( $order ) : self::sample_items( $brand );
				$body  = self::items_table( $rows, $p, $brand );

				if ( '' === $body ) {
					return '';
				}
				break;

			case 'order_totals':
				$order = isset( $ctx['__order'] ) ? $ctx['__order'] : null;
				$rows  = $order ? self::order_totals_data( $order ) : self::sample_totals();
				$body  = self::totals_table( $rows, $p, $brand );

				if ( '' === $body ) {
					return '';
				}
				break;

			case 'payment_note':
				$order   = isset( $ctx['__order'] ) ? $ctx['__order'] : null;
				$gateway = $order ? $order->get_payment_method() : ( isset( $ctx['__payment'] ) ? $ctx['__payment'] : '' );
				$note    = '' !== $gateway ? WMD_Design::payment_note( $gateway ) : '';

				if ( '' === trim( wp_strip_all_tags( $note ) ) ) {
					return '';
				}

				$inner = '';

				if ( '' !== trim( (string) $p['title'] ) ) {
					$inner .= '<div style="font-family:' . $font . ';font-size:' . max( 15, (int) $brand['base_size'] ) . 'px;font-weight:700;color:' . esc_attr( $brand['heading_color'] ) . ';margin-bottom:6px;">'
						. esc_html( WMD_Tags::replace( $p['title'], $ctx ) ) . '</div>';
				}

				$inner .= '<div style="font-family:' . $font . ';font-size:' . (int) $brand['base_size'] . 'px;line-height:1.6;color:' . esc_attr( $brand['text_color'] ) . ';">'
					. self::linkify( WMD_Tags::replace( $note, $ctx ), $brand ) . '</div>';

				$body = empty( $p['box'] )
					? $inner
					: '<div style="border:1px solid ' . esc_attr( $brand['border_color'] ) . ';border-left:3px solid ' . esc_attr( $brand['accent'] ) . ';border-radius:6px;padding:12px 14px;">' . $inner . '</div>';
				break;

			case 'order_table':
			case 'addresses':
			case 'payment_info':
				$body = self::woo_part( $type, $ctx );
				if ( '' === trim( $body ) ) {
					return '';
				}
				break;

			case 'customer_note':
				$order = isset( $ctx['__order'] ) ? $ctx['__order'] : null;
				$note  = $order ? trim( (string) $order->get_customer_note() ) : '';

				if ( '' === $note ) {
					if ( ! empty( $p['hide_empty'] ) ) {
						return '';
					}
					$note = __( '(märkust ei ole)', 'wonom-meilidisainer' );
				}

				$body = '<div ' . self::attr(
					array(
						'style' => self::style(
							array(
								'border-left'      => '3px solid ' . $brand['accent'],
								'background-color' => '#00000008',
								'padding'          => '10px 14px',
								'font-family'      => $font,
								'font-size'        => (int) $brand['base_size'] . 'px',
								'line-height'      => '1.6',
								'color'            => $brand['text_color'],
							)
						),
					)
				) . '>';

				if ( '' !== trim( (string) $p['title'] ) ) {
					$body .= '<strong style="color:' . esc_attr( $brand['heading_color'] ) . ';">' . esc_html( $p['title'] ) . '</strong><br>';
				}

				$body .= nl2br( esc_html( $note ) ) . '</div>';
				break;

			case 'order_meta':
				$key   = trim( (string) $p['key'] );
				$order = isset( $ctx['__order'] ) ? $ctx['__order'] : null;
				$value = ( $key && $order ) ? trim( (string) $order->get_meta( $key, true ) ) : '';

				if ( '' === $value ) {
					if ( ! empty( $p['hide_empty'] ) ) {
						return '';
					}
					$value = '—';
				}

				$shown = esc_html( $value );
				$link  = trim( (string) $p['link'] );

				if ( '' !== $link && '—' !== $value ) {
					$url   = str_replace( '{{value}}', rawurlencode( $value ), $link );
					$shown = '<a href="' . esc_url( WMD_Tags::replace( $url, $ctx ) ) . '" style="color:' . esc_attr( $brand['accent'] ) . ';">' . esc_html( $value ) . '</a>';
				}

				$cell['text-align'] = $p['align'];
				$body               = '<div ' . self::attr(
					array(
						'style' => self::style(
							array(
								'font-family' => $font,
								'font-size'   => (int) $brand['base_size'] . 'px',
								'line-height' => '1.6',
								'color'       => $brand['text_color'],
							)
						),
					)
				) . '>';

				if ( '' !== trim( (string) $p['title'] ) ) {
					$body .= '<strong style="color:' . esc_attr( $brand['heading_color'] ) . ';">' . esc_html( $p['title'] ) . ':</strong> ';
				}

				$body .= $shown . '</div>';
				break;

			default:
				return '';
		}

		return '<tr><td ' . self::attr( array( 'style' => self::style( $cell ) ) ) . '>' . $body . '</td></tr>';
	}

	/**
	 * Tellimuse read kujul, mida nii PHP kui kujundaja oskavad renderdada.
	 *
	 * @param WC_Order $order Tellimus.
	 * @return array
	 */
	public static function order_items_data( $order ) {
		$rows = array();

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return $rows;
		}

		foreach ( $order->get_items() as $item ) {
			$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
			$image   = '';

			if ( $product && $product->get_image_id() ) {
				$image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
			}

			$rows[] = array(
				'image' => $image ? $image : '',
				'name'  => $item->get_name(),
				'url'   => $product ? $product->get_permalink() : '',
				'sku'   => $product ? (string) $product->get_sku() : '',
				'meta'  => wc_display_item_meta( $item, array( 'echo' => false ) ),
				'qty'   => (string) $item->get_quantity(),
				'unit'  => wc_price( $order->get_item_subtotal( $item, false, true ), array( 'currency' => $order->get_currency() ) ),
				'total' => $order->get_formatted_line_subtotal( $item ),
			);
		}

		return $rows;
	}

	/**
	 * Kokkuvõtte read WooCommerce'i enda arvutuse pealt.
	 *
	 * @param WC_Order $order Tellimus.
	 * @return array
	 */
	public static function order_totals_data( $order ) {
		$rows = array();

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return $rows;
		}

		foreach ( $order->get_order_item_totals() as $key => $total ) {
			$rows[] = array(
				'key'   => (string) $key,
				'label' => isset( $total['label'] ) ? $total['label'] : '',
				'value' => isset( $total['value'] ) ? $total['value'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Ise kokku pandud toodete tabel.
	 *
	 * @param array $rows  Tellimuse read.
	 * @param array $p     Ploki seaded.
	 * @param array $brand Bränd.
	 * @return string
	 */
	public static function items_table( $rows, $p, $brand ) {
		$cols = array();

		foreach ( (array) $p['cols'] as $col ) {
			if ( ! empty( $col['on'] ) ) {
				$cols[] = $col;
			}
		}

		if ( empty( $cols ) || empty( $rows ) ) {
			return '';
		}

		$f      = $brand['font_family'];
		$fs     = (int) $brand['base_size'];
		$border = 'none' === $p['lines'] ? '' : '1px solid ' . $brand['border_color'];
		$grid   = 'grid' === $p['lines'];
		$cell   = 'padding:10px 8px;font-family:' . $f . ';font-size:' . $fs . 'px;line-height:1.5;vertical-align:top;';

		$out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">';

		if ( ! empty( $p['header'] ) ) {
			$out .= '<thead><tr>';
			foreach ( $cols as $col ) {
				$align = in_array( $col['key'], array( 'qty', 'unit', 'total' ), true ) ? 'right' : 'left';
				$style = $cell . 'text-align:' . $align . ';font-weight:700;color:' . $brand['heading_color'] . ';';
				if ( $border ) {
					$style .= 'border-bottom:2px solid ' . $brand['border_color'] . ';';
				}
				if ( $grid && $border ) {
					$style .= 'border:' . $border . ';';
				}
				$out .= '<th style="' . esc_attr( $style ) . '">' . esc_html( $col['label'] ) . '</th>';
			}
			$out .= '</tr></thead>';
		}

		$out .= '<tbody>';

		foreach ( $rows as $row ) {
			$out .= '<tr>';

			foreach ( $cols as $col ) {
				$key   = $col['key'];
				$align = in_array( $key, array( 'qty', 'unit', 'total' ), true ) ? 'right' : 'left';
				$style = $cell . 'text-align:' . $align . ';color:' . $brand['text_color'] . ';';

				if ( $border ) {
					$style .= $grid ? 'border:' . $border . ';' : 'border-bottom:' . $border . ';';
				}

				$out .= '<td style="' . esc_attr( $style ) . '">' . self::item_cell( $key, $row, $p, $brand ) . '</td>';
			}

			$out .= '</tr>';
		}

		return $out . '</tbody></table>';
	}

	/**
	 * Ühe lahtri sisu toodete tabelis.
	 *
	 * @param string $key   Veeru võti.
	 * @param array  $row   Rea andmed.
	 * @param array  $p     Ploki seaded.
	 * @param array  $brand Bränd.
	 * @return string
	 */
	protected static function item_cell( $key, $row, $p, $brand ) {
		$value = isset( $row[ $key ] ) ? $row[ $key ] : '';

		switch ( $key ) {
			case 'image':
				if ( '' === $value ) {
					return '&nbsp;';
				}
				$w = (int) $p['img_size'];
				return '<img src="' . esc_url( $value ) . '" alt="" width="' . $w . '" style="width:' . $w . 'px;max-width:100%;height:auto;display:block;border:0;border-radius:4px;" />';

			case 'name':
				$name = esc_html( $value );
				if ( ! empty( $p['link'] ) && ! empty( $row['url'] ) ) {
					$name = '<a href="' . esc_url( $row['url'] ) . '" style="color:' . esc_attr( $brand['accent'] ) . ';text-decoration:none;">' . $name . '</a>';
				}
				return $name;

			case 'meta':
				// wc_display_item_meta annab juba valmis HTML-i.
				return '' !== $value ? '<span style="font-size:' . max( 11, (int) $brand['base_size'] - 2 ) . 'px;color:' . esc_attr( $brand['muted_color'] ) . ';">' . wp_kses_post( $value ) . '</span>' : '&nbsp;';

			case 'unit':
			case 'total':
				return wp_kses_post( $value );

			default:
				return esc_html( $value );
		}
	}

	/**
	 * Ise kokku pandud kokkuvõte.
	 *
	 * @param array $rows  Kokkuvõtte read.
	 * @param array $p     Ploki seaded.
	 * @param array $brand Bränd.
	 * @return string
	 */
	public static function totals_table( $rows, $p, $brand ) {
		if ( empty( $rows ) ) {
			return '';
		}

		$wanted = array();
		foreach ( (array) $p['rows'] as $r ) {
			$wanted[ $r['key'] ] = array(
				'on'    => ! empty( $r['on'] ),
				'label' => $r['label'],
			);
		}

		$f      = $brand['font_family'];
		$fs     = (int) $brand['base_size'];
		$border = 'none' === $p['lines'] ? '' : '1px solid ' . $brand['border_color'];
		$width  = 'full' === $p['align'] ? '100%' : '60%';
		$table  = 'right' === $p['align'] ? 'width:' . $width . ';margin-left:auto;' : 'width:100%;';

		$out = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="' . esc_attr( $table ) . 'border-collapse:collapse;">';

		foreach ( $rows as $row ) {
			$key = $row['key'];

			// Tundmatuid ridu (nt maksuread) näitame alati — parem liiga palju.
			if ( isset( $wanted[ $key ] ) && ! $wanted[ $key ]['on'] ) {
				continue;
			}

			$label = ( isset( $wanted[ $key ] ) && '' !== $wanted[ $key ]['label'] ) ? $wanted[ $key ]['label'] : $row['label'];
			$last  = 'order_total' === $key && ! empty( $p['bold_total'] );
			$cell  = 'padding:8px 8px;font-family:' . $f . ';font-size:' . $fs . 'px;line-height:1.5;';

			if ( $border ) {
				$cell .= 'border-bottom:' . $border . ';';
			}

			$out .= '<tr>'
				. '<th style="' . esc_attr( $cell . 'text-align:left;font-weight:' . ( $last ? '700' : '600' ) . ';color:' . $brand['heading_color'] . ';' ) . '">' . esc_html( wp_strip_all_tags( $label ) ) . '</th>'
				. '<td style="' . esc_attr( $cell . 'text-align:right;color:' . $brand['text_color'] . ';font-weight:' . ( $last ? '700' : '400' ) . ';' ) . '">' . wp_kses_post( $row['value'] ) . '</td>'
				. '</tr>';
		}

		return $out . '</table>';
	}

	/**
	 * Kas plokk on selle tellimuse puhul nähtav.
	 *
	 * Ilma tellimuseta (nt kontomeil või eelvaade) näitame ploki ära — parem
	 * näidata liiga palju kui vaikselt midagi ära kaotada.
	 *
	 * @param array $block Plokk.
	 * @param array $ctx   Kontekst.
	 * @return bool
	 */
	protected static function visible( $block, $ctx ) {
		$pay = isset( $block['cond']['pay'] ) ? (array) $block['cond']['pay'] : array();

		if ( empty( $pay ) ) {
			return true;
		}

		$order = isset( $ctx['__order'] ) ? $ctx['__order'] : null;

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return true;
		}

		return in_array( $order->get_payment_method(), $pay, true );
	}

	/**
	 * WooCommerce'i enda osa — tellimuse tabel või aadressid.
	 *
	 * Renderdame need WooCommerce'i tegevustega, et need püsiksid kooskõlas
	 * poe pluginatega (maksuread, allahindlused, tarnepluginate lisad).
	 * Ilma tellimuseta (eelvaade) anname näidissisu.
	 *
	 * @param string $type Ploki tüüp.
	 * @param array  $ctx  Kontekst.
	 * @return string
	 */
	public static function woo_part( $type, $ctx ) {
		$order = isset( $ctx['__order'] ) ? $ctx['__order'] : null;
		$brand = WMD_Design::brand();

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			if ( 'order_table' === $type ) {
				return self::sample_order_table( $brand );
			}

			return 'addresses' === $type ? self::sample_addresses( $brand ) : '';
		}

		$sent_to_admin = isset( $ctx['__sent_to_admin'] ) ? (bool) $ctx['__sent_to_admin'] : false;
		$email         = isset( $ctx['__email'] ) ? $ctx['__email'] : null;

		ob_start();

		if ( 'order_table' === $type ) {
			do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, false, $email );
		} elseif ( 'payment_info' === $type ) {
			// Siia riputavad makselahendused oma meilisisu, nt pangaülekande rekvisiidid.
			do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, false, $email );
		} else {
			do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, false, $email );
		}

		return (string) ob_get_clean();
	}

	/**
	 * Meili keha, kui kasutaja paneb meili tervikuna ise kokku.
	 *
	 * @param string $email_id WC_Email id.
	 * @param array  $ctx      Kontekst.
	 * @return string
	 */
	public static function body_html( $email_id, $ctx ) {
		$brand    = WMD_Design::brand();
		$settings = WMD_Design::email( $email_id );

		return self::blocks( $settings['body'], $brand, $ctx );
	}

	/**
	 * Annab linkidele brändi aktsentvärvi, kui neil pole oma stiili.
	 *
	 * @param string $html  Sisu.
	 * @param array  $brand Bränd.
	 * @return string
	 */
	protected static function linkify( $html, $brand ) {
		return preg_replace(
			'/<a(?![^>]*style=)/i',
			'<a style="color:' . esc_attr( $brand['accent'] ) . ';text-decoration:underline;"',
			(string) $html
		);
	}

	/**
	 * CSS, mis läheb WooCommerce'i enda sisu (tellimustabel jms) peale.
	 *
	 * @param array|null $brand Bränd.
	 * @return string
	 */
	public static function email_css( $brand = null ) {
		if ( null === $brand ) {
			$brand = WMD_Design::brand();
		}

		$css = '
#wrapper, #template_container { background: transparent !important; border: 0 !important; box-shadow: none !important; }
.wmd-wc-content { font-family: ' . $brand['font_family'] . '; font-size: ' . (int) $brand['base_size'] . 'px; line-height: 1.6; color: ' . $brand['text_color'] . '; }
.wmd-wc-content p { margin: 0 0 14px 0; }
.wmd-wc-content a { color: ' . $brand['accent'] . '; }
.wmd-wc-content h2, .wmd-wc-content h3 { font-family: ' . $brand['font_family'] . '; color: ' . $brand['heading_color'] . '; margin: 22px 0 10px 0; line-height: 1.3; }
.wmd-wc-content h2 { font-size: ' . max( 17, (int) round( $brand['heading_size'] * 0.72 ) ) . 'px; }
.wmd-wc-content h3 { font-size: ' . max( 14, (int) round( $brand['heading_size'] * 0.58 ) ) . 'px; }
.wmd-wc-content table.td, .wmd-wc-content table { border-color: ' . $brand['border_color'] . '; }
.wmd-wc-content table th, .wmd-wc-content table td { border-color: ' . $brand['border_color'] . ' !important; color: ' . $brand['text_color'] . '; font-family: ' . $brand['font_family'] . '; }
.wmd-wc-content table th { color: ' . $brand['heading_color'] . '; }
.wmd-wc-content .text { color: ' . $brand['text_color'] . '; }
@media only screen and (max-width: 620px) {
	.wmd-card { width: 100% !important; }
	.wmd-col { display: block !important; width: 100% !important; padding: 0 0 12px 0 !important; }
}
';

		if ( ! empty( $brand['custom_css'] ) ) {
			$css .= "\n" . $brand['custom_css'];
		}

		return $css;
	}

	/**
	 * Näidissisu WooCommerce'i tellimustabeli asemel (eelvaates ilma tellimuseta).
	 *
	 * @param array $brand Bränd.
	 * @return string
	 */
	public static function sample_body( $brand ) {
		return self::sample_order_table( $brand ) . self::sample_addresses( $brand );
	}

	/**
	 * Näidis-tellimusetabel eelvaatesse, kui päris tellimust pole.
	 *
	 * @param array $brand Bränd.
	 * @return string
	 */
	public static function sample_order_table( $brand ) {
		$b  = $brand['border_color'];
		$h  = $brand['heading_color'];
		$t  = $brand['text_color'];
		$f  = $brand['font_family'];
		$fs = (int) $brand['base_size'];

		$row = function ( $name, $qty, $price ) use ( $b, $t, $f, $fs ) {
			return '<tr>'
				. '<td style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $t . ';">' . esc_html( $name ) . '</td>'
				. '<td style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $t . ';">' . (int) $qty . '</td>'
				. '<td style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $t . ';">' . esc_html( $price ) . '</td>'
				. '</tr>';
		};

		$out  = '<h2 style="font-family:' . $f . ';color:' . $h . ';font-size:' . max( 17, (int) round( $brand['heading_size'] * 0.72 ) ) . 'px;margin:22px 0 10px 0;">' . esc_html__( 'Tellimus #1042', 'wonom-meilidisainer' ) . '</h2>';
		$out .= '<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;border-color:' . $b . ';margin-bottom:16px;">';
		$out .= '<thead><tr>'
			. '<th style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $h . ';">' . esc_html__( 'Toode', 'wonom-meilidisainer' ) . '</th>'
			. '<th style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $h . ';">' . esc_html__( 'Kogus', 'wonom-meilidisainer' ) . '</th>'
			. '<th style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $h . ';">' . esc_html__( 'Hind', 'wonom-meilidisainer' ) . '</th>'
			. '</tr></thead><tbody>';
		$out .= $row( __( 'Puuvillane T-särk, M', 'wonom-meilidisainer' ), 2, '39,80 €' );
		$out .= $row( __( 'Villane sall', 'wonom-meilidisainer' ), 1, '42,60 €' );
		$out .= '</tbody><tfoot>';
		$out .= '<tr><th style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $h . ';">' . esc_html__( 'Vahesumma:', 'wonom-meilidisainer' ) . '</th><td colspan="2" style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $t . ';">82,40 €</td></tr>';
		$out .= '<tr><th style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $h . ';">' . esc_html__( 'Tarne:', 'wonom-meilidisainer' ) . '</th><td colspan="2" style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $t . ';">5,00 €</td></tr>';
		$out .= '<tr><th style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $h . ';">' . esc_html__( 'Kokku:', 'wonom-meilidisainer' ) . '</th><td colspan="2" style="text-align:left;border:1px solid ' . $b . ';padding:10px;font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $t . ';"><strong>87,40 €</strong></td></tr>';
		$out .= '</tfoot></table>';

		return $out;
	}

	/**
	 * Näidistooted, kui tellimust pole.
	 *
	 * @param array $brand Bränd.
	 * @return array
	 */
	public static function sample_items( $brand ) {
		return array(
			array(
				'image' => '',
				'name'  => __( 'Puuvillane T-särk', 'wonom-meilidisainer' ),
				'url'   => '',
				'sku'   => 'TS-100',
				'meta'  => __( 'Suurus: M', 'wonom-meilidisainer' ),
				'qty'   => '2',
				'unit'  => '19,90 €',
				'total' => '39,80 €',
			),
			array(
				'image' => '',
				'name'  => __( 'Villane sall', 'wonom-meilidisainer' ),
				'url'   => '',
				'sku'   => 'SL-042',
				'meta'  => __( 'Värv: hall', 'wonom-meilidisainer' ),
				'qty'   => '1',
				'unit'  => '42,60 €',
				'total' => '42,60 €',
			),
		);
	}

	/**
	 * Näidiskokkuvõte, kui tellimust pole.
	 *
	 * @return array
	 */
	public static function sample_totals() {
		return array(
			array( 'key' => 'cart_subtotal', 'label' => __( 'Vahesumma:', 'wonom-meilidisainer' ), 'value' => '82,40 €' ),
			array( 'key' => 'discount', 'label' => __( 'Allahindlus:', 'wonom-meilidisainer' ), 'value' => '-8,00 €' ),
			array( 'key' => 'shipping', 'label' => __( 'Tarne:', 'wonom-meilidisainer' ), 'value' => '5,00 €' ),
			array( 'key' => 'payment_method', 'label' => __( 'Makseviis:', 'wonom-meilidisainer' ), 'value' => __( 'Panga ülekanne', 'wonom-meilidisainer' ) ),
			array( 'key' => 'order_total', 'label' => __( 'Kokku:', 'wonom-meilidisainer' ), 'value' => '79,40 €' ),
		);
	}

	/**
	 * Näidisaadressid eelvaatesse.
	 *
	 * @param array $brand Bränd.
	 * @return string
	 */
	public static function sample_addresses( $brand ) {
		$h  = $brand['heading_color'];
		$t  = $brand['text_color'];
		$f  = $brand['font_family'];
		$fs = (int) $brand['base_size'];
		$h2 = max( 17, (int) round( $brand['heading_size'] * 0.72 ) );

		$out  = '<h2 style="font-family:' . $f . ';color:' . $h . ';font-size:' . $h2 . 'px;margin:22px 0 10px 0;line-height:1.3;">' . esc_html__( 'Arveaadress', 'wonom-meilidisainer' ) . '</h2>';
		$out .= '<p style="font-family:' . $f . ';font-size:' . $fs . 'px;color:' . $t . ';line-height:1.6;margin:0;">Mari Tamm<br>Pikk 12-4<br>10123 Tallinn<br>Eesti</p>';

		return $out;
	}

	/**
	 * Terve meil ühes tükis (eelvaate jaoks).
	 *
	 * @param string $email_id WC_Email id.
	 * @param array  $ctx      Kontekst.
	 * @param string $body     Sisuosa HTML.
	 * @return string
	 */
	public static function full( $email_id, $ctx, $body = null ) {
		$brand = WMD_Design::brand();
		$list  = wmd_email_list();
		$label = isset( $list[ $email_id ] ) ? $list[ $email_id ]['label'] : '';

		if ( null === $body ) {
			$body = WMD_Design::is_full( $email_id )
				? self::body_html( $email_id, $ctx )
				: self::sample_body( $brand );
		}

		$html  = self::header_html( $label, $email_id, $ctx );
		$html .= $body;
		$html .= self::footer_html( $email_id, $ctx );

		// Eelvaates paneme CSS-i sisse, sest WooCommerce'i inliner siin ei jookse.
		$style = '<style type="text/css">' . self::email_css( $brand ) . '</style>';
		$html  = str_replace( '</head>', $style . '</head>', $html );

		return $html;
	}

	/**
	 * Stiiliatribuudi ehitus.
	 *
	 * @param array $rules CSS-reeglid.
	 * @return string
	 */
	public static function style( $rules ) {
		$out = '';
		foreach ( $rules as $prop => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			$out .= $prop . ':' . $value . ';';
		}

		return $out;
	}

	/**
	 * Atribuutide ehitus koos põgenemisega.
	 *
	 * @param array $attrs Atribuudid.
	 * @return string
	 */
	public static function attr( $attrs ) {
		$out = array();
		foreach ( $attrs as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			$out[] = $key . '="' . esc_attr( $value ) . '"';
		}

		return implode( ' ', $out );
	}
}
