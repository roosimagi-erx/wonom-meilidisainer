<?php
/**
 * Ühised abifunktsioonid: brändi skeem, plokitüübid, meilide nimekiri, puhastus.
 *
 * @package Wonom_Meilidisainer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kas WooCommerce on aktiivne.
 *
 * @return bool
 */
function wmd_woo_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Poe vaikekeel.
 *
 * Vaikekeele sisu elab kujunduses tipptasemel; teised keeled on selle peal
 * kihina (vt WMD_Design::merge_lang). Nii ei vaja olemasolev kujundus
 * mitmekeelseks minnes mingit ümbertegemist.
 *
 * @return string Kahetäheline kood, nt „et".
 */
function wmd_default_language() {
	$lang = apply_filters( 'wpml_default_language', null );

	if ( is_string( $lang ) && '' !== $lang ) {
		return $lang;
	}

	if ( function_exists( 'pll_default_language' ) ) {
		$lang = pll_default_language();

		if ( is_string( $lang ) && '' !== $lang ) {
			return $lang;
		}
	}

	return substr( get_locale(), 0, 2 );
}

/**
 * Poe keeled kujundaja valikusse.
 *
 * Ilma tõlkepluginata on vastuses üks keel — siis ei näita kujundaja valikut
 * üldse ja miski ei muutu.
 *
 * @return array<string,string> kood => nimi.
 */
function wmd_languages() {
	$out  = array();
	$wpml = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

	if ( is_array( $wpml ) && $wpml ) {
		foreach ( $wpml as $code => $data ) {
			$name = '';

			if ( is_array( $data ) ) {
				$name = ! empty( $data['native_name'] ) ? $data['native_name'] : ( ! empty( $data['translated_name'] ) ? $data['translated_name'] : '' );
			}

			$out[ (string) $code ] = '' !== $name ? $name : (string) $code;
		}

		return $out;
	}

	if ( function_exists( 'pll_languages_list' ) ) {
		$codes = pll_languages_list( array( 'fields' => 'slug' ) );
		$names = pll_languages_list( array( 'fields' => 'name' ) );

		if ( is_array( $codes ) ) {
			foreach ( $codes as $i => $code ) {
				$out[ $code ] = isset( $names[ $i ] ) ? $names[ $i ] : $code;
			}

			return $out;
		}
	}

	$default         = wmd_default_language();
	$out[ $default ] = $default;

	return $out;
}

/**
 * Keele lokaat, nt „en" -> „en_GB".
 *
 * Seda on vaja selleks, et ka WooCommerce'i enda tekstid (nt „sisaldab X KM")
 * tuleksid õiges keeles. Need käivad WordPressi lokaadi, mitte WPML-i keele
 * järgi, seega keelevahetusest üksi ei piisa.
 *
 * @param string $lang Keele kood.
 * @return string Lokaat või sama kood, kui midagi paremat ei leia.
 */
function wmd_locale_for( $lang ) {
	$lang = (string) $lang;

	if ( '' === $lang ) {
		return '';
	}

	$langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

	if ( is_array( $langs ) && ! empty( $langs[ $lang ]['default_locale'] ) ) {
		return $langs[ $lang ]['default_locale'];
	}

	if ( function_exists( 'pll_languages_list' ) ) {
		$codes   = pll_languages_list( array( 'fields' => 'slug' ) );
		$locales = pll_languages_list( array( 'fields' => 'locale' ) );
		$at      = is_array( $codes ) ? array_search( $lang, $codes, true ) : false;

		if ( false !== $at && isset( $locales[ $at ] ) ) {
			return $locales[ $at ];
		}
	}

	return $lang;
}

/**
 * Mis keeles see kiri välja läheb.
 *
 * Tellimuse keel on kirjas tellimusel endal (WooCommerce Multilingual paneb
 * sinna meta „wpml_language"). See on kõige kindlam allikas, sest see ei sõltu
 * sellest, mis keel parasjagu saidil sees on.
 *
 * @param WC_Order|null $order Tellimus, kui on.
 * @return string
 */
function wmd_email_language( $order = null ) {
	if ( $order && is_a( $order, 'WC_Order' ) ) {
		$lang = $order->get_meta( 'wpml_language', true );

		if ( is_string( $lang ) && '' !== $lang ) {
			return $lang;
		}
	}

	$lang = apply_filters( 'wpml_current_language', null );

	if ( is_string( $lang ) && '' !== $lang && 'all' !== $lang ) {
		return $lang;
	}

	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language();

		if ( is_string( $lang ) && '' !== $lang ) {
			return $lang;
		}
	}

	return substr( determine_locale(), 0, 2 );
}

/**
 * Brändi väljade skeem. Sama skeemi järgi ehitab JS parempoolse paneeli.
 *
 * @return array<string,array>
 */
function wmd_brand_schema() {
	return array(
		'logo_url'      => array(
			'type'    => 'image',
			'label'   => __( 'Logo', 'wonom-meilidisainer' ),
			'group'   => 'head',
			'default' => '',
		),
		'logo_width'    => array(
			'type'    => 'range',
			'label'   => __( 'Logo width (px)', 'wonom-meilidisainer' ),
			'group'   => 'head',
			'min'     => 60,
			'max'     => 400,
			'step'    => 10,
			'default' => 160,
		),
		'logo_align'    => array(
			'type'    => 'align',
			'label'   => __( 'Logo alignment', 'wonom-meilidisainer' ),
			'group'   => 'head',
			'default' => 'center',
		),
		'header_bg'     => array(
			'type'    => 'color',
			'label'   => __( 'Header background', 'wonom-meilidisainer' ),
			'group'   => 'head',
			'default' => '',
			'inherit' => 'body_bg',
		),
		'width'         => array(
			'type'    => 'range',
			'label'   => __( 'Email width (px)', 'wonom-meilidisainer' ),
			'group'   => 'layout',
			'min'     => 480,
			'max'     => 800,
			'step'    => 10,
			'default' => 600,
		),
		'radius'        => array(
			'type'    => 'range',
			'label'   => __( 'Corner radius (px)', 'wonom-meilidisainer' ),
			'group'   => 'layout',
			'min'     => 0,
			'max'     => 28,
			'step'    => 1,
			'default' => 10,
		),
		'pad_x'         => array(
			'type'    => 'range',
			'label'   => __( 'Inner padding (px)', 'wonom-meilidisainer' ),
			'group'   => 'layout',
			'min'     => 12,
			'max'     => 48,
			'step'    => 2,
			'default' => 28,
		),
		'page_bg'       => array(
			'type'    => 'color',
			'label'   => __( 'Page background', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#f2f4f7',
		),
		'body_bg'       => array(
			'type'    => 'color',
			'label'   => __( 'Email background', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#ffffff',
		),
		'text_color'    => array(
			'type'    => 'color',
			'label'   => __( 'Text', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#31373d',
		),
		'heading_color' => array(
			'type'    => 'color',
			'label'   => __( 'Headings', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#12161a',
		),
		'muted_color'   => array(
			'type'    => 'color',
			'label'   => __( 'Muted text', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#7c868e',
		),
		'accent'        => array(
			'type'    => 'color',
			'label'   => __( 'Accent colour (links)', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#1f7a5a',
		),
		'border_color'  => array(
			'type'    => 'color',
			'label'   => __( 'Lines and borders', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#e3e7ea',
		),
		'btn_bg'        => array(
			'type'    => 'color',
			'label'   => __( 'Button background', 'wonom-meilidisainer' ),
			'group'   => 'button',
			'default' => '#1f7a5a',
		),
		'btn_text'      => array(
			'type'    => 'color',
			'label'   => __( 'Button text', 'wonom-meilidisainer' ),
			'group'   => 'button',
			'default' => '#ffffff',
		),
		'btn_radius'    => array(
			'type'    => 'range',
			'label'   => __( 'Button radius (px)', 'wonom-meilidisainer' ),
			'group'   => 'button',
			'min'     => 0,
			'max'     => 30,
			'step'    => 1,
			'default' => 6,
		),
		'font_family'   => array(
			'type'    => 'select',
			'label'   => __( 'Font', 'wonom-meilidisainer' ),
			'group'   => 'type',
			'options' => array(
				'-apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif' => __( 'System (recommended)', 'wonom-meilidisainer' ),
				'Arial, Helvetica, sans-serif'                                 => 'Arial',
				'Helvetica Neue, Helvetica, Arial, sans-serif'                  => 'Helvetica',
				'Georgia, Times New Roman, serif'                               => 'Georgia',
				'Trebuchet MS, Tahoma, sans-serif'                              => 'Trebuchet MS',
				'Verdana, Geneva, sans-serif'                                   => 'Verdana',
				'Courier New, Courier, monospace'                               => 'Courier New',
			),
			'default' => '-apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
		),
		'base_size'     => array(
			'type'    => 'range',
			'label'   => __( 'Text size (px)', 'wonom-meilidisainer' ),
			'group'   => 'type',
			'min'     => 12,
			'max'     => 20,
			'step'    => 1,
			'default' => 15,
		),
		'heading_size'  => array(
			'type'    => 'range',
			'label'   => __( 'Main heading size (px)', 'wonom-meilidisainer' ),
			'group'   => 'type',
			'min'     => 18,
			'max'     => 40,
			'step'    => 1,
			'default' => 26,
		),
		'custom_css'    => array(
			'type'    => 'textarea',
			'label'   => __( 'Extra CSS (advanced)', 'wonom-meilidisainer' ),
			'group'   => 'advanced',
			'default' => '',
		),
	);
}

/**
 * Brändi väljade rühmad paneelis.
 *
 * @return array<string,string>
 */
function wmd_brand_groups() {
	return array(
		'head'     => __( 'Logo and header', 'wonom-meilidisainer' ),
		'colors'   => __( 'Colours', 'wonom-meilidisainer' ),
		'type'     => __( 'Type', 'wonom-meilidisainer' ),
		'button'   => __( 'Buttons', 'wonom-meilidisainer' ),
		'layout'   => __( 'Layout', 'wonom-meilidisainer' ),
		'advanced' => __( 'Other', 'wonom-meilidisainer' ),
	);
}

/**
 * Plokitüübid ja nende väljad.
 *
 * @return array<string,array>
 */
function wmd_block_types() {
	// Skeem ei muutu päringu jooksul, aga seda küsitakse iga ploki renderdusel.
	// Ilma vahemäluta ehitaks üks kiri selle kümneid kordi uuesti.
	static $cached = null;

	if ( null !== $cached ) {
		return $cached;
	}

	$align = array(
		'type'    => 'align',
		'label'   => __( 'Alignment', 'wonom-meilidisainer' ),
		'default' => 'left',
	);

	$pad = array(
		'type'    => 'range',
		'label'   => __( 'Space above/below (px)', 'wonom-meilidisainer' ),
		'min'     => 0,
		'max'     => 48,
		'step'    => 2,
		'default' => 12,
	);

	$cached = array(
		'heading' => array(
			'label'  => __( 'Heading', 'wonom-meilidisainer' ),
			'icon'   => 'H',
			'fields' => array(
				'text'  => array(
					'type'    => 'text',
					'label'   => __( 'Text', 'wonom-meilidisainer' ),
					'default' => __( 'Thank you for your order!', 'wonom-meilidisainer' ),
					'tags'    => true,
					'translate' => true,
				),
				'size'  => array(
					'type'    => 'select',
					'label'   => __( 'Size', 'wonom-meilidisainer' ),
					'options' => array(
						'lg' => __( 'Large', 'wonom-meilidisainer' ),
						'md' => __( 'Medium', 'wonom-meilidisainer' ),
						'sm' => __( 'Small', 'wonom-meilidisainer' ),
					),
					'default' => 'md',
				),
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Colour', 'wonom-meilidisainer' ),
					'default' => '',
					'inherit' => 'heading_color',
				),
				'align' => $align,
				'pad'   => $pad,
			),
		),
		'text'    => array(
			'label'  => __( 'Paragraph', 'wonom-meilidisainer' ),
			'icon'   => 'T',
			'fields' => array(
				'html'  => array(
					'type'    => 'richtext',
					'label'   => __( 'Text', 'wonom-meilidisainer' ),
					'default' => __( 'We have received your order and are getting it ready.', 'wonom-meilidisainer' ),
					'tags'    => true,
					'translate' => true,
				),
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Colour', 'wonom-meilidisainer' ),
					'default' => '',
					'inherit' => 'text_color',
				),
				'size'  => array(
					'type'    => 'range',
					'label'   => __( 'Size (px)', 'wonom-meilidisainer' ),
					'min'     => 11,
					'max'     => 22,
					'step'    => 1,
					'default' => 0,
					'inherit' => 'base_size',
				),
				'align' => $align,
				'pad'   => $pad,
			),
		),
		'button'  => array(
			'label'  => __( 'Button', 'wonom-meilidisainer' ),
			'icon'   => 'B',
			'fields' => array(
				'label' => array(
					'type'    => 'text',
					'label'   => __( 'Button text', 'wonom-meilidisainer' ),
					'default' => __( 'View order', 'wonom-meilidisainer' ),
					'tags'    => true,
					'translate' => true,
				),
				'url'   => array(
					'type'    => 'url',
					'label'   => __( 'Link', 'wonom-meilidisainer' ),
					'default' => '{{order_url}}',
					'tags'    => true,
				),
				'style' => array(
					'type'    => 'select',
					'label'   => __( 'Style', 'wonom-meilidisainer' ),
					'options' => array(
						'solid'   => __( 'Solid', 'wonom-meilidisainer' ),
						'outline' => __( 'Outlined', 'wonom-meilidisainer' ),
					),
					'default' => 'solid',
				),
				'full'  => array(
					'type'    => 'toggle',
					'label'   => __( 'Full width', 'wonom-meilidisainer' ),
					'default' => 0,
				),
				'align' => array_merge( $align, array( 'default' => 'center' ) ),
				'pad'   => $pad,
			),
		),
		'image'   => array(
			'label'  => __( 'Image', 'wonom-meilidisainer' ),
			'icon'   => 'P',
			'fields' => array(
				'url'   => array(
					'type'    => 'image',
					'label'   => __( 'Image', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'alt'   => array(
					'type'    => 'text',
					'label'   => __( 'Alt text', 'wonom-meilidisainer' ),
					'translate' => true,
					'default' => '',
				),
				'link'  => array(
					'type'    => 'url',
					'label'   => __( 'Link on click', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'width' => array(
					'type'    => 'range',
					'label'   => __( 'Width (px)', 'wonom-meilidisainer' ),
					'min'     => 40,
					'max'     => 800,
					'step'    => 10,
					'default' => 240,
				),
				'align' => array_merge( $align, array( 'default' => 'center' ) ),
				'pad'   => $pad,
			),
		),
		'divider' => array(
			'label'  => __( 'Divider', 'wonom-meilidisainer' ),
			'icon'   => '—',
			'fields' => array(
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Colour', 'wonom-meilidisainer' ),
					'default' => '',
					'inherit' => 'border_color',
				),
				'pad'   => array_merge( $pad, array( 'default' => 14 ) ),
			),
		),
		'spacer'  => array(
			'label'  => __( 'Spacer', 'wonom-meilidisainer' ),
			'icon'   => '↕',
			'fields' => array(
				'height' => array(
					'type'    => 'range',
					'label'   => __( 'Height (px)', 'wonom-meilidisainer' ),
					'min'     => 4,
					'max'     => 80,
					'step'    => 2,
					'default' => 24,
				),
			),
		),
		'columns' => array(
			'label'  => __( 'Two columns', 'wonom-meilidisainer' ),
			'icon'   => '▥',
			'fields' => array(
				'left'  => array(
					'type'    => 'richtext',
					'label'   => __( 'Left column', 'wonom-meilidisainer' ),
					'default' => __( '<strong>Delivery time</strong><br>1–3 business days', 'wonom-meilidisainer' ),
					'tags'    => true,
					'translate' => true,
				),
				'right' => array(
					'type'    => 'richtext',
					'label'   => __( 'Right column', 'wonom-meilidisainer' ),
					'default' => __( '<strong>Questions?</strong><br>Just write to us.', 'wonom-meilidisainer' ),
					'tags'    => true,
					'translate' => true,
				),
				'pad'   => $pad,
			),
		),
		'cards'   => array(
			'label'  => __( 'Images side by side', 'wonom-meilidisainer' ),
			'icon'   => '▦',
			'fields' => array(
				'items'     => array(
					'type'    => 'cards',
					'label'   => __( 'Images', 'wonom-meilidisainer' ),
					'default' => array(
						array(
							'image' => '',
							'link'  => '',
							'label' => '',
						),
						array(
							'image' => '',
							'link'  => '',
							'label' => '',
						),
						array(
							'image' => '',
							'link'  => '',
							'label' => '',
						),
						array(
							'image' => '',
							'link'  => '',
							'label' => '',
						),
					),
				),
				'cols'      => array(
					'type'    => 'select',
					'label'   => __( 'How many per row', 'wonom-meilidisainer' ),
					'options' => array(
						'2' => __( '2 across', 'wonom-meilidisainer' ),
						'3' => __( '3 across', 'wonom-meilidisainer' ),
						'4' => __( '4 across', 'wonom-meilidisainer' ),
					),
					'default' => '4',
				),
				'ratio'     => array(
					'type'    => 'select',
					'label'   => __( 'Image shape', 'wonom-meilidisainer' ),
					'options' => array(
						'square'    => __( 'Square (1:1)', 'wonom-meilidisainer' ),
						'portrait'  => __( 'Portrait (3:4)', 'wonom-meilidisainer' ),
						'landscape' => __( 'Landscape (4:3)', 'wonom-meilidisainer' ),
						'original'  => __( 'Original (each keeps its shape)', 'wonom-meilidisainer' ),
					),
					'default' => 'square',
					'hint'    => __( 'Images are cropped from the centre to the same size, so the row stays even. "Original" leaves each image its own shape.', 'wonom-meilidisainer' ),
				),
				'gap'       => array(
					'type'    => 'range',
					'label'   => __( 'Gap between images (px)', 'wonom-meilidisainer' ),
					'min'     => 0,
					'max'     => 24,
					'step'    => 2,
					'default' => 10,
				),
				'radius'    => array(
					'type'    => 'range',
					'label'   => __( 'Image corner radius (px)', 'wonom-meilidisainer' ),
					'min'     => 0,
					'max'     => 24,
					'step'    => 2,
					'default' => 0,
				),
				'size'      => array(
					'type'    => 'range',
					'label'   => __( 'Name size (px)', 'wonom-meilidisainer' ),
					'min'     => 10,
					'max'     => 20,
					'step'    => 1,
					'default' => 0,
					'inherit' => 'base_size',
				),
				'color'     => array(
					'type'    => 'color',
					'label'   => __( 'Name colour', 'wonom-meilidisainer' ),
					'default' => '',
					'inherit' => 'text_color',
				),
				'underline' => array(
					'type'    => 'toggle',
					'label'   => __( 'Underline the name', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'pad'       => $pad,
			),
		),
		'social'  => array(
			'label'  => __( 'Social media', 'wonom-meilidisainer' ),
			'icon'   => '@',
			'fields' => array(
				'size'      => array(
					'type'    => 'range',
					'label'   => __( 'Icon size (px)', 'wonom-meilidisainer' ),
					'min'     => 18,
					'max'     => 48,
					'step'    => 2,
					'default' => 28,
				),
				'gap'       => array(
					'type'    => 'range',
					'label'   => __( 'Gap between icons (px)', 'wonom-meilidisainer' ),
					'min'     => 0,
					'max'     => 24,
					'step'    => 2,
					'default' => 10,
				),
				'facebook'  => array(
					'type'    => 'url',
					'label'   => 'Facebook',
					'default' => '',
				),
				'instagram' => array(
					'type'    => 'url',
					'label'   => 'Instagram',
					'default' => '',
				),
				'tiktok'    => array(
					'type'    => 'url',
					'label'   => 'TikTok',
					'default' => '',
				),
				'youtube'   => array(
					'type'    => 'url',
					'label'   => 'YouTube',
					'default' => '',
				),
				'linkedin'  => array(
					'type'    => 'url',
					'label'   => 'LinkedIn',
					'default' => '',
				),
				'pad'       => $pad,
			),
		),
		'html'    => array(
			'label'  => __( 'Custom HTML', 'wonom-meilidisainer' ),
			'icon'   => '<>',
			'fields' => array(
				'code' => array(
					'type'    => 'textarea',
					'label'   => __( 'HTML', 'wonom-meilidisainer' ),
					'default' => '',
					'tags'    => true,
				),
				'pad'  => $pad,
			),
		),

		// WooCommerce'i enda osad. Neid renderdab WooCommerce, meie ütleme ainult,
		// kuhu need meilis lähevad. Eelvaates näidatakse näidisandmeid.
		'order_table'  => array(
			'label'  => __( 'Order table (WooCommerce)', 'wonom-meilidisainer' ),
			'icon'   => '#',
			'woo'    => true,
			'fields' => array(
				'pad' => $pad,
			),
		),
		'addresses'    => array(
			'label'  => __( 'Addresses', 'wonom-meilidisainer' ),
			'icon'   => 'A',
			'woo'    => true,
			'fields' => array(
				'show'           => array(
					'type'    => 'select',
					'label'   => __( 'What to show', 'wonom-meilidisainer' ),
					'options' => array(
						'both'     => __( 'Billing and shipping address', 'wonom-meilidisainer' ),
						'billing'  => __( 'Billing address only', 'wonom-meilidisainer' ),
						'shipping' => __( 'Shipping address only', 'wonom-meilidisainer' ),
					),
					'default' => 'both',
				),
				'billing_title'  => array(
					'type'    => 'text',
					'label'   => __( 'Billing address heading', 'wonom-meilidisainer' ),
					'translate' => true,
					'default' => __( 'Billing address', 'wonom-meilidisainer' ),
				),
				'shipping_title' => array(
					'type'    => 'text',
					'label'   => __( 'Shipping address heading', 'wonom-meilidisainer' ),
					'translate' => true,
					'default' => __( 'Shipping address', 'wonom-meilidisainer' ),
				),
				'contacts'       => array(
					'type'    => 'toggle',
					'label'   => __( 'Show phone and email', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'box'            => array(
					'type'    => 'toggle',
					'label'   => __( 'In a bordered box', 'wonom-meilidisainer' ),
					'default' => 0,
				),
				'pad'            => $pad,
			),
		),
		'customer_note' => array(
			'label'  => __( 'Customer note', 'wonom-meilidisainer' ),
			'icon'   => '"',
			'woo'    => true,
			'fields' => array(
				'title'      => array(
					'type'    => 'text',
					'label'   => __( 'Heading', 'wonom-meilidisainer' ),
					'default' => __( 'Your note to the order', 'wonom-meilidisainer' ),
					'translate' => true,
				),
				'hide_empty' => array(
					'type'    => 'toggle',
					'label'   => __( 'Hide when there is no note', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'pad'        => $pad,
			),
		),
		// Ise kokku pandav toodete tabel. Erinevalt „Tellimuse tabelist" ei tule
		// see WooCommerce'i mallist, vaid veerud valid ise.
		'order_items'  => array(
			'label'  => __( 'Products (own table)', 'wonom-meilidisainer' ),
			'icon'   => '▤',
			'woo'    => true,
			'fields' => array(
				'cols'     => array(
					'type'    => 'columns',
					'label'   => __( 'Columns', 'wonom-meilidisainer' ),
					'options' => wmd_item_columns(),
					'default' => wmd_default_columns( wmd_item_columns(), array( 'sku', 'unit' ) ),
				),
				'header'   => array(
					'type'    => 'toggle',
					'label'   => __( 'Show header row', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'img_size' => array(
					'type'    => 'range',
					'label'   => __( 'Image width (px)', 'wonom-meilidisainer' ),
					'min'     => 32,
					'max'     => 160,
					'step'    => 4,
					'default' => 64,
				),
				'link'     => array(
					'type'    => 'toggle',
					'label'   => __( 'Product name as a link', 'wonom-meilidisainer' ),
					'default' => 0,
				),
				'lines'    => array(
					'type'    => 'select',
					'label'   => __( 'Lines', 'wonom-meilidisainer' ),
					'options' => array(
						'rows' => __( 'Between rows', 'wonom-meilidisainer' ),
						'grid' => __( 'Full grid', 'wonom-meilidisainer' ),
						'none' => __( 'No lines', 'wonom-meilidisainer' ),
					),
					'default' => 'rows',
				),
				'pad'      => $pad,
			),
		),
		'order_totals' => array(
			'label'  => __( 'Totals (own table)', 'wonom-meilidisainer' ),
			'icon'   => 'Σ',
			'woo'    => true,
			'fields' => array(
				'rows'       => array(
					'type'    => 'columns',
					'label'   => __( 'Rows', 'wonom-meilidisainer' ),
					'options' => wmd_total_rows(),
					'default' => wmd_default_columns( wmd_total_rows() ),
				),
				'align'      => array(
					'type'    => 'select',
					'label'   => __( 'Layout', 'wonom-meilidisainer' ),
					'options' => array(
						'right' => __( 'Right', 'wonom-meilidisainer' ),
						'full'  => __( 'Full width', 'wonom-meilidisainer' ),
					),
					'default' => 'right',
				),
				'bold_total' => array(
					'type'    => 'toggle',
					'label'   => __( 'Grand total in bold', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'lines'      => array(
					'type'    => 'select',
					'label'   => __( 'Lines', 'wonom-meilidisainer' ),
					'options' => array(
						'rows' => __( 'Between rows', 'wonom-meilidisainer' ),
						'none' => __( 'No lines', 'wonom-meilidisainer' ),
					),
					'default' => 'rows',
				),
				'pad'        => $pad,
			),
		),
		// Sinu enda tekst makseviisi kohta. Sisu kirjutatakse ühe korra
		// vahekaardil „Makseviisid" ja see plokk toob õige teksti kirja.
		'payment_note' => array(
			'label'  => __( 'Payment instructions (own text)', 'wonom-meilidisainer' ),
			'icon'   => '¤',
			'woo'    => true,
			'fields' => array(
				'title' => array(
					'type'    => 'text',
					'label'   => __( 'Heading', 'wonom-meilidisainer' ),
					'default' => __( 'Payment instructions', 'wonom-meilidisainer' ),
					'tags'    => true,
					'translate' => true,
				),
				'box'   => array(
					'type'    => 'toggle',
					'label'   => __( 'In a bordered box', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'pad'   => $pad,
			),
		),
		'payment_info' => array(
			'label'  => __( 'Payment instructions (WooCommerce)', 'wonom-meilidisainer' ),
			'icon'   => '€',
			'woo'    => true,
			'fields' => array(
				'pad' => $pad,
			),
		),
		// Mitmerealine andmekast: silt + väärtus, väärtus valitakse nimekirjast
		// või kirjutatakse ise märgenditega.
		'order_details' => array(
			'label'  => __( 'Order details (table)', 'wonom-meilidisainer' ),
			'icon'   => '▦',
			'woo'    => true,
			'fields' => array(
				'rows'       => array(
					'type'    => 'pairs',
					'label'   => __( 'Rows', 'wonom-meilidisainer' ),
					'default' => array(
						array(
							'label' => __( 'Order number', 'wonom-meilidisainer' ),
							'value' => '#{{order_number}}',
							'link'  => '',
						),
						array(
							'label' => __( 'Payment method', 'wonom-meilidisainer' ),
							'value' => '{{payment_method}}',
							'link'  => '',
						),
					),
				),
				'cols'       => array(
					'type'    => 'select',
					'label'   => __( 'Number of columns', 'wonom-meilidisainer' ),
					'options' => array(
						'1' => __( 'One column', 'wonom-meilidisainer' ),
						'2' => __( 'Two columns', 'wonom-meilidisainer' ),
					),
					'default' => '2',
				),
				'hide_empty' => array(
					'type'    => 'toggle',
					'label'   => __( 'Hide rows left empty', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'box'        => array(
					'type'    => 'toggle',
					'label'   => __( 'In a bordered box', 'wonom-meilidisainer' ),
					'default' => 0,
				),
				'pad'        => $pad,
			),
		),
		'order_meta'   => array(
			'label'  => __( 'Order field (one line)', 'wonom-meilidisainer' ),
			'icon'   => '»',
			'woo'    => true,
			'fields' => array(
				'key'        => array(
					'type'    => 'metakey',
					'label'   => __( 'Field key on the order', 'wonom-meilidisainer' ),
					'hint'    => __( 'Pick from the list or type your own. The form {{meta:key}} works too.', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'title'      => array(
					'type'    => 'text',
					'label'   => __( 'Label', 'wonom-meilidisainer' ),
					'default' => __( 'Tracking code', 'wonom-meilidisainer' ),
					'translate' => true,
				),
				'link'       => array(
					'type'    => 'text',
					'label'   => __( 'Link (optional)', 'wonom-meilidisainer' ),
					'hint'    => __( 'Use {{value}} where the field value goes.', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'hide_empty' => array(
					'type'    => 'toggle',
					'label'   => __( 'Hide when the field is empty', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'align'      => $align,
				'pad'        => $pad,
			),
		),
	);

	return $cached;
}

/**
 * Toodete tabeli veerud, mida saab ise valida ja järjestada.
 *
 * @return array<string,string>
 */
function wmd_item_columns() {
	return apply_filters(
		'wmd_item_columns',
		array(
			'image' => __( 'Image', 'wonom-meilidisainer' ),
			'name'  => __( 'Product', 'wonom-meilidisainer' ),
			'sku'   => __( 'SKU', 'wonom-meilidisainer' ),
			'meta'  => __( 'Variations and extra fields', 'wonom-meilidisainer' ),
			'qty'   => __( 'Quantity', 'wonom-meilidisainer' ),
			'unit'  => __( 'Unit price', 'wonom-meilidisainer' ),
			'total' => __( 'Line total', 'wonom-meilidisainer' ),
		)
	);
}

/**
 * Kokkuvõtte read. Võtmed tulevad WooCommerce'i get_order_item_totals() pealt.
 *
 * @return array<string,string>
 */
function wmd_total_rows() {
	return apply_filters(
		'wmd_total_rows',
		array(
			'cart_subtotal'  => __( 'Subtotal', 'wonom-meilidisainer' ),
			'discount'       => __( 'Discount', 'wonom-meilidisainer' ),
			// Ei tule WooCommerce'i get_order_item_totals() pealt, vaid tellimuse
			// kupongidelt. Rida tekib ainult siis, kui koodi päriselt kasutati.
			'coupon_codes'   => __( 'Coupon code', 'wonom-meilidisainer' ),
			'shipping'       => __( 'Shipping', 'wonom-meilidisainer' ),
			'payment_method' => __( 'Payment method', 'wonom-meilidisainer' ),
			'tax'            => __( 'VAT', 'wonom-meilidisainer' ),
			'order_total'    => __( 'Total', 'wonom-meilidisainer' ),
		)
	);
}

/**
 * Veergude vaikeväärtus: kõik sisse, WooCommerce'i sildid.
 *
 * @param array $options   Võti => silt.
 * @param array $off       Võtmed, mis on vaikimisi välja lülitatud.
 * @return array
 */
function wmd_default_columns( $options, $off = array() ) {
	$out = array();

	foreach ( $options as $key => $label ) {
		$out[] = array(
			'key'   => $key,
			'label' => $label,
			'on'    => in_array( $key, $off, true ) ? 0 : 1,
		);
	}

	return $out;
}

/**
 * Poe makseviisid tingimuste valikuks.
 *
 * @return array<string,string> id => nimi.
 */
function wmd_payment_gateways() {
	$out = array();

	if ( ! wmd_woo_active() || ! function_exists( 'WC' ) ) {
		return $out;
	}

	$gateways = WC()->payment_gateways();

	if ( ! $gateways ) {
		return $out;
	}

	foreach ( $gateways->payment_gateways() as $gateway ) {
		if ( empty( $gateway->id ) ) {
			continue;
		}

		$title = $gateway->get_title();
		$out[ $gateway->id ] = '' !== $title ? $title : $gateway->id;
	}

	return $out;
}

/**
 * Viimased tellimused eelvaate valikusse.
 *
 * Silt sisaldab makseviisi, sest just selle järgi tahetakse eri variante
 * kontrollida.
 *
 * @param int $limit Mitu tellimust.
 * @return array
 */
function wmd_recent_orders( $limit = 25 ) {
	$out = array();

	if ( ! function_exists( 'wc_get_orders' ) ) {
		return $out;
	}

	$orders = wc_get_orders(
		array(
			'limit'   => absint( $limit ),
			'orderby' => 'date',
			'order'   => 'DESC',
			'type'    => 'shop_order',
		)
	);

	foreach ( $orders as $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			continue;
		}

		$payment = $order->get_payment_method_title();
		$date    = $order->get_date_created();

		$out[] = array(
			'id'      => $order->get_id(),
			'payment' => $order->get_payment_method(),
			'label'   => sprintf(
				'#%s · %s · %s%s',
				$order->get_order_number(),
				wp_strip_all_tags( $order->get_formatted_order_total() ),
				'' !== $payment ? $payment : __( 'no payment method', 'wonom-meilidisainer' ),
				// Poe enda kuupäevaseade, mitte kõvakodeeritud eesti formaat.
				$date ? ' · ' . wc_format_datetime( $date ) : ''
			),
		);
	}

	return $out;
}

/**
 * Kuidas meili kokku pannakse.
 *
 * @return array<string,string>
 */
function wmd_email_modes() {
	return array(
		'wrap' => __( 'Around WooCommerce content', 'wonom-meilidisainer' ),
		'full' => __( 'Build the whole email', 'wonom-meilidisainer' ),
	);
}

/**
 * Näidisplokid, millega „terve meil ise" alustab, et vaade ei jääks tühjaks.
 *
 * @return array
 */
function wmd_default_body() {
	return array(
		wmd_make_block(
			'text',
			array(
				'html' => __( 'Hi {{customer_first_name}}! We have received your order <strong>#{{order_number}}</strong> and are getting it ready.', 'wonom-meilidisainer' ),
				'pad'  => 8,
			)
		),
		wmd_make_block( 'order_table', array( 'pad' => 8 ) ),
		wmd_make_block( 'addresses', array( 'pad' => 8 ) ),
	);
}

/**
 * Ploki vaikeväärtused tüübi järgi.
 *
 * @param string $type Plokitüüp.
 * @return array
 */
function wmd_block_defaults( $type ) {
	$types = wmd_block_types();
	if ( ! isset( $types[ $type ] ) ) {
		return array();
	}

	$props = array();
	foreach ( $types[ $type ]['fields'] as $key => $field ) {
		$props[ $key ] = $field['default'];
	}

	return $props;
}

/**
 * WooCommerce'i meilid, mida kujundaja katab. Võti on WC_Email->id.
 *
 * @return array<string,array>
 */
function wmd_email_list() {
	return apply_filters(
		'wmd_email_list',
		array(
			'customer_processing_order' => array(
				'label'    => __( 'Order processing', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-processing-order.php',
			),
			'customer_completed_order'  => array(
				'label'    => __( 'Order completed', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-completed-order.php',
			),
			'customer_on_hold_order'    => array(
				'label'    => __( 'Order on hold', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-on-hold-order.php',
			),
			'customer_refunded_order'   => array(
				'label'    => __( 'Order refunded', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-refunded-order.php',
			),
			'customer_invoice'          => array(
				'label'    => __( 'Invoice / pending payment', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-invoice.php',
			),
			'customer_note'             => array(
				'label'    => __( 'Note to customer', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-note.php',
			),
			// Kontomeilidel ei ole tellimust: „order => false" peidab kujundajas
			// tellimuse valiku ja hoiab tellimuse ka renderdusest eemal.
			'customer_reset_password'   => array(
				'label'    => __( 'Password reset', 'wonom-meilidisainer' ),
				'group'    => 'account',
				'template' => 'emails/customer-reset-password.php',
				'order'    => false,
			),
			'customer_new_account'      => array(
				'label'    => __( 'New account', 'wonom-meilidisainer' ),
				'group'    => 'account',
				'template' => 'emails/customer-new-account.php',
				'order'    => false,
			),
			'new_order'                 => array(
				'label'    => __( 'New order (to shop)', 'wonom-meilidisainer' ),
				'group'    => 'admin',
				'template' => 'emails/admin-new-order.php',
			),
			'cancelled_order'           => array(
				'label'    => __( 'Cancelled order (to shop)', 'wonom-meilidisainer' ),
				'group'    => 'admin',
				'template' => 'emails/admin-cancelled-order.php',
			),
			'failed_order'              => array(
				'label'    => __( 'Failed order (to shop)', 'wonom-meilidisainer' ),
				'group'    => 'admin',
				'template' => 'emails/admin-failed-order.php',
			),
		)
	);
}

/**
 * Kas see meil käib tellimuse pealt.
 *
 * Kontomeilid (uus konto, parooli lähtestamine) ei tea tellimusest midagi —
 * neil ei tohi tellimuse valik sisu muuta ega neile tellimust kaasa anda.
 *
 * @param string $email_id Meili id.
 * @return bool
 */
function wmd_email_uses_order( $email_id ) {
	$list = wmd_email_list();

	if ( ! isset( $list[ $email_id ] ) ) {
		return true;
	}

	return ! isset( $list[ $email_id ]['order'] ) || (bool) $list[ $email_id ]['order'];
}

/**
 * Meilirühmade nimed.
 *
 * @return array<string,string>
 */
function wmd_email_groups() {
	return array(
		'customer' => __( 'Customer order emails', 'wonom-meilidisainer' ),
		'account'  => __( 'Account emails', 'wonom-meilidisainer' ),
		'admin'    => __( 'Internal shop emails', 'wonom-meilidisainer' ),
	);
}

/**
 * Vaikimisi kujundus.
 *
 * @return array
 */
function wmd_default_design() {
	$brand = array();
	foreach ( wmd_brand_schema() as $key => $field ) {
		$brand[ $key ] = $field['default'];
	}

	$header = array(
		wmd_make_block(
			'heading',
			array(
				'text'  => '{{site_title}}',
				'size'  => 'md',
				'align' => 'center',
				'pad'   => 4,
			)
		),
	);

	$footer = array(
		wmd_make_block( 'divider', array( 'pad' => 10 ) ),
		wmd_make_block(
			'text',
			array(
				'html'  => '{{site_title}} &middot; <a href="{{shop_url}}">{{shop_url}}</a>',
				'align' => 'center',
				'size'  => 13,
				'pad'   => 6,
			)
		),
		wmd_make_block(
			'text',
			array(
				'html'  => __( 'You are getting this email because you placed an order in our shop.', 'wonom-meilidisainer' ),
				'align' => 'center',
				'size'  => 12,
				'pad'   => 2,
			)
		),
		wmd_make_block( 'social', array( 'pad' => 8 ) ),
	);

	$emails = array();
	foreach ( array_keys( wmd_email_list() ) as $id ) {
		$emails[ $id ] = array(
			// Kas kujundus rakendub sellele kirjale. Ülemine „Kujundus sees"
			// on peakraan, see siin on kirja kaupa.
			'enabled'    => 1,
			'mode'       => 'wrap',
			'subject'    => '',
			'heading'    => '',
			'before'     => array(),
			'after'      => array(),
			'body'       => array(),
			// WooCommerce'i „Lisatekst" kirja lõpus. Vaikimisi väljas, sest
			// WooCommerce'i seadetes ei saa seda välja tühjendada — tühi väärtus
			// asendatakse seal vaiketekstiga.
			'additional' => 0,
		);
	}

	// Näidissisu kõige tavalisemas meilis, et kujundaja ei avaneks tühjana.
	$emails['customer_processing_order']['before'] = array(
		wmd_make_block(
			'text',
			array(
				'html' => __( 'Hi {{customer_first_name}}! We have received your order <strong>#{{order_number}}</strong> and are getting it ready. We will let you know when it ships.', 'wonom-meilidisainer' ),
				'pad'  => 8,
			)
		),
	);
	$emails['customer_processing_order']['after'] = array(
		wmd_make_block(
			'button',
			array(
				'label' => __( 'View order', 'wonom-meilidisainer' ),
				'url'   => '{{order_url}}',
			)
		),
	);

	return array(
		'version'  => 1,
		'brand'    => $brand,
		'header'   => $header,
		'footer'   => $footer,
		'emails'   => $emails,
		'payments' => array(),
		// Keelekihid vaikekeele peal. Tühi = kõik kirjad ühes keeles.
		'i18n'     => array(),
	);
}

/**
 * Uus plokk vaikeväärtustega.
 *
 * @param string $type  Plokitüüp.
 * @param array  $props Ülekirjutatavad väärtused.
 * @return array
 */
function wmd_make_block( $type, $props = array() ) {
	return array(
		'id'    => 'b' . substr( md5( $type . microtime( true ) . wp_rand() ), 0, 10 ),
		'type'  => $type,
		'props' => array_merge( wmd_block_defaults( $type ), $props ),
	);
}

/**
 * Lubatud HTML rikkalikus tekstis.
 *
 * @return array
 */
function wmd_allowed_html() {
	return array(
		'a'      => array(
			'href'   => array(),
			'title'  => array(),
			'style'  => array(),
			'target' => array(),
		),
		'strong' => array( 'style' => array() ),
		'b'      => array( 'style' => array() ),
		'em'     => array( 'style' => array() ),
		'i'      => array( 'style' => array() ),
		'u'      => array( 'style' => array() ),
		'br'     => array(),
		'span'   => array( 'style' => array() ),
		'small'  => array( 'style' => array() ),
		'ul'     => array( 'style' => array() ),
		'ol'     => array( 'style' => array() ),
		'li'     => array( 'style' => array() ),
		'p'      => array( 'style' => array() ),
	);
}

/**
 * Teeb tellimuse välja võtmest toore võtme.
 *
 * Muutujate nimekirjast kopeerides tuleb kaasa märgendi kuju
 * `{{meta:_tracking_number}}`. See väli tahab ainult `_tracking_number`,
 * seega lubame mõlemat ja koorime ümbrise ise maha.
 *
 * @param string $key Sisend.
 * @return string
 */
function wmd_meta_key( $key ) {
	$key = trim( (string) $key );

	if ( preg_match( '/^\{\{\s*(?:meta:)?(.+?)\s*\}\}$/', $key, $m ) ) {
		$key = trim( $m[1] );
	}

	// Ka ilma looksulgudeta kirjutatud „meta:võti" on arusaadav.
	if ( 0 === stripos( $key, 'meta:' ) ) {
		$key = trim( substr( $key, 5 ) );
	}

	return $key;
}

/**
 * Pildikaardi kuvasuhted: laius ja kõrgus, mille järgi pilt lõigatakse.
 *
 * @return array<string,array{0:int,1:int}>
 */
function wmd_card_ratios() {
	return array(
		'square'    => array( 400, 400 ),
		'portrait'  => array( 400, 533 ),
		'landscape' => array( 400, 300 ),
	);
}

/**
 * Ühesuuruseks lõigatud pilt kaardiploki jaoks.
 *
 * Meediateegis on pildid eri kuju ja kõrgusega, aga kirjas peavad nad olema
 * ühesugused. CSS-i object-fit lõikab need küll enamikus postkastides, aga
 * Outlooki töölauaversioon seda ei tunne ja venitaks pildi laiaks. Seepärast
 * lõikame pildi serveris päriselt valmis ja anname kirja juba õige faili.
 *
 * Lõigatud fail tehakse ühe korra ja jääb meediateeki alles; tulemus läheb
 * lisaks vahemällu, et iga kirja saatmine ei teeks andmebaasipäringut.
 *
 * @param string $url   Pildi aadress.
 * @param string $ratio Kuvasuhte võti (wmd_card_ratios) või 'original'.
 * @return string Aadress — lõigatud pildile või sisendile, kui lõigata ei saanud.
 */
function wmd_card_image( $url, $ratio ) {
	$sizes = wmd_card_ratios();
	$url   = trim( (string) $url );

	if ( '' === $url || ! isset( $sizes[ $ratio ] ) ) {
		return $url;
	}

	$key    = 'wmd_card_' . md5( $url . '|' . $ratio );
	$cached = get_transient( $key );

	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$done = wmd_crop_attachment( $url, $sizes[ $ratio ][0], $sizes[ $ratio ][1] );

	set_transient( $key, $done, WEEK_IN_SECONDS );

	return $done;
}

/**
 * Lõikab meediateegi pildi soovitud mõõtu ja annab uue aadressi.
 *
 * Väliste piltide puhul ei ole midagi teha — need lõikab postkast ise CSS-iga.
 *
 * @param string $url Pildi aadress.
 * @param int    $w   Laius.
 * @param int    $h   Kõrgus.
 * @return string
 */
function wmd_crop_attachment( $url, $w, $h ) {
	$id = attachment_url_to_postid( $url );

	// Pisipildi aadressi (…-300x300.jpg) järgi manust ei leia — otsime
	// originaali. Ilma selleta jääks nt kategooriapilt serveris lõikamata.
	if ( ! $id ) {
		$full = preg_replace( '/-\d+x\d+(?=\.[a-zA-Z]{3,4}$)/', '', $url );

		if ( $full !== $url ) {
			$id = attachment_url_to_postid( $full );
		}
	}

	if ( ! $id ) {
		return $url;
	}

	$name = 'wmd_card_' . (int) $w . 'x' . (int) $h;
	$meta = wp_get_attachment_metadata( $id );

	// Kas oleme selle juba varem lõiganud?
	if ( isset( $meta['sizes'][ $name ]['file'] ) ) {
		$made = wp_get_attachment_image_src( $id, $name );

		if ( $made && ! empty( $made[0] ) ) {
			return $made[0];
		}
	}

	$file = get_attached_file( $id );

	if ( ! $file || ! file_exists( $file ) ) {
		return $url;
	}

	$editor = wp_get_image_editor( $file );

	if ( is_wp_error( $editor ) ) {
		return $url;
	}

	$resized = $editor->resize( $w, $h, true );

	if ( is_wp_error( $resized ) ) {
		return $url;
	}

	$saved = $editor->save();

	if ( is_wp_error( $saved ) || empty( $saved['file'] ) ) {
		return $url;
	}

	// Paneme suuruse metaandmetesse kirja, et järgmine kord uuesti ei lõikaks
	// ja et pildi kustutamisel läheks ka see fail kaasa.
	if ( is_array( $meta ) ) {
		if ( ! isset( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			$meta['sizes'] = array();
		}

		$meta['sizes'][ $name ] = array(
			'file'      => $saved['file'],
			'width'     => $saved['width'],
			'height'    => $saved['height'],
			'mime-type' => $saved['mime-type'],
		);

		wp_update_attachment_metadata( $id, $meta );
	}

	$base = wp_get_attachment_url( $id );

	return $base ? trailingslashit( dirname( $base ) ) . $saved['file'] : $url;
}

/**
 * Värvi puhastus. Tühi väärtus tähendab, et päritakse brändilt.
 *
 * @param string $value Sisend.
 * @return string
 */
function wmd_sanitize_color( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	$hex = sanitize_hex_color( $value );

	return $hex ? $hex : '';
}
