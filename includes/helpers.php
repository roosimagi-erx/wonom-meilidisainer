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
			'label'   => __( 'Logo laius (px)', 'wonom-meilidisainer' ),
			'group'   => 'head',
			'min'     => 60,
			'max'     => 400,
			'step'    => 10,
			'default' => 160,
		),
		'logo_align'    => array(
			'type'    => 'align',
			'label'   => __( 'Logo joondus', 'wonom-meilidisainer' ),
			'group'   => 'head',
			'default' => 'center',
		),
		'header_bg'     => array(
			'type'    => 'color',
			'label'   => __( 'Päise taust', 'wonom-meilidisainer' ),
			'group'   => 'head',
			'default' => '',
			'inherit' => 'body_bg',
		),
		'width'         => array(
			'type'    => 'range',
			'label'   => __( 'Meili laius (px)', 'wonom-meilidisainer' ),
			'group'   => 'layout',
			'min'     => 480,
			'max'     => 800,
			'step'    => 10,
			'default' => 600,
		),
		'radius'        => array(
			'type'    => 'range',
			'label'   => __( 'Nurkade ümarus (px)', 'wonom-meilidisainer' ),
			'group'   => 'layout',
			'min'     => 0,
			'max'     => 28,
			'step'    => 1,
			'default' => 10,
		),
		'pad_x'         => array(
			'type'    => 'range',
			'label'   => __( 'Sisemine veeris (px)', 'wonom-meilidisainer' ),
			'group'   => 'layout',
			'min'     => 12,
			'max'     => 48,
			'step'    => 2,
			'default' => 28,
		),
		'page_bg'       => array(
			'type'    => 'color',
			'label'   => __( 'Lehe taust', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#f2f4f7',
		),
		'body_bg'       => array(
			'type'    => 'color',
			'label'   => __( 'Meili taust', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#ffffff',
		),
		'text_color'    => array(
			'type'    => 'color',
			'label'   => __( 'Tekst', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#31373d',
		),
		'heading_color' => array(
			'type'    => 'color',
			'label'   => __( 'Pealkirjad', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#12161a',
		),
		'muted_color'   => array(
			'type'    => 'color',
			'label'   => __( 'Hall abitekst', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#7c868e',
		),
		'accent'        => array(
			'type'    => 'color',
			'label'   => __( 'Aktsentvärv (lingid)', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#1f7a5a',
		),
		'border_color'  => array(
			'type'    => 'color',
			'label'   => __( 'Jooned ja piirded', 'wonom-meilidisainer' ),
			'group'   => 'colors',
			'default' => '#e3e7ea',
		),
		'btn_bg'        => array(
			'type'    => 'color',
			'label'   => __( 'Nupu taust', 'wonom-meilidisainer' ),
			'group'   => 'button',
			'default' => '#1f7a5a',
		),
		'btn_text'      => array(
			'type'    => 'color',
			'label'   => __( 'Nupu tekst', 'wonom-meilidisainer' ),
			'group'   => 'button',
			'default' => '#ffffff',
		),
		'btn_radius'    => array(
			'type'    => 'range',
			'label'   => __( 'Nupu ümarus (px)', 'wonom-meilidisainer' ),
			'group'   => 'button',
			'min'     => 0,
			'max'     => 30,
			'step'    => 1,
			'default' => 6,
		),
		'font_family'   => array(
			'type'    => 'select',
			'label'   => __( 'Kirjatüüp', 'wonom-meilidisainer' ),
			'group'   => 'type',
			'options' => array(
				'-apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif' => __( 'Süsteemne (soovitatud)', 'wonom-meilidisainer' ),
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
			'label'   => __( 'Teksti suurus (px)', 'wonom-meilidisainer' ),
			'group'   => 'type',
			'min'     => 12,
			'max'     => 20,
			'step'    => 1,
			'default' => 15,
		),
		'heading_size'  => array(
			'type'    => 'range',
			'label'   => __( 'Peapealkirja suurus (px)', 'wonom-meilidisainer' ),
			'group'   => 'type',
			'min'     => 18,
			'max'     => 40,
			'step'    => 1,
			'default' => 26,
		),
		'custom_css'    => array(
			'type'    => 'textarea',
			'label'   => __( 'Lisa-CSS (edasijõudnutele)', 'wonom-meilidisainer' ),
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
		'head'     => __( 'Logo ja päis', 'wonom-meilidisainer' ),
		'colors'   => __( 'Värvid', 'wonom-meilidisainer' ),
		'type'     => __( 'Kiri', 'wonom-meilidisainer' ),
		'button'   => __( 'Nupud', 'wonom-meilidisainer' ),
		'layout'   => __( 'Paigutus', 'wonom-meilidisainer' ),
		'advanced' => __( 'Muu', 'wonom-meilidisainer' ),
	);
}

/**
 * Plokitüübid ja nende väljad.
 *
 * @return array<string,array>
 */
function wmd_block_types() {
	$align = array(
		'type'    => 'align',
		'label'   => __( 'Joondus', 'wonom-meilidisainer' ),
		'default' => 'left',
	);

	$pad = array(
		'type'    => 'range',
		'label'   => __( 'Vahe ülal/all (px)', 'wonom-meilidisainer' ),
		'min'     => 0,
		'max'     => 48,
		'step'    => 2,
		'default' => 12,
	);

	return array(
		'heading' => array(
			'label'  => __( 'Pealkiri', 'wonom-meilidisainer' ),
			'icon'   => 'H',
			'fields' => array(
				'text'  => array(
					'type'    => 'text',
					'label'   => __( 'Tekst', 'wonom-meilidisainer' ),
					'default' => __( 'Aitäh tellimuse eest!', 'wonom-meilidisainer' ),
					'tags'    => true,
				),
				'size'  => array(
					'type'    => 'select',
					'label'   => __( 'Suurus', 'wonom-meilidisainer' ),
					'options' => array(
						'lg' => __( 'Suur', 'wonom-meilidisainer' ),
						'md' => __( 'Keskmine', 'wonom-meilidisainer' ),
						'sm' => __( 'Väike', 'wonom-meilidisainer' ),
					),
					'default' => 'md',
				),
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Värv', 'wonom-meilidisainer' ),
					'default' => '',
					'inherit' => 'heading_color',
				),
				'align' => $align,
				'pad'   => $pad,
			),
		),
		'text'    => array(
			'label'  => __( 'Tekstilõik', 'wonom-meilidisainer' ),
			'icon'   => 'T',
			'fields' => array(
				'html'  => array(
					'type'    => 'richtext',
					'label'   => __( 'Tekst', 'wonom-meilidisainer' ),
					'default' => __( 'Saime su tellimuse kätte ja asume seda kohe komplekteerima.', 'wonom-meilidisainer' ),
					'tags'    => true,
				),
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Värv', 'wonom-meilidisainer' ),
					'default' => '',
					'inherit' => 'text_color',
				),
				'size'  => array(
					'type'    => 'range',
					'label'   => __( 'Suurus (px)', 'wonom-meilidisainer' ),
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
			'label'  => __( 'Nupp', 'wonom-meilidisainer' ),
			'icon'   => 'B',
			'fields' => array(
				'label' => array(
					'type'    => 'text',
					'label'   => __( 'Nupu tekst', 'wonom-meilidisainer' ),
					'default' => __( 'Vaata tellimust', 'wonom-meilidisainer' ),
					'tags'    => true,
				),
				'url'   => array(
					'type'    => 'url',
					'label'   => __( 'Link', 'wonom-meilidisainer' ),
					'default' => '{{order_url}}',
					'tags'    => true,
				),
				'style' => array(
					'type'    => 'select',
					'label'   => __( 'Stiil', 'wonom-meilidisainer' ),
					'options' => array(
						'solid'   => __( 'Täidetud', 'wonom-meilidisainer' ),
						'outline' => __( 'Raamiga', 'wonom-meilidisainer' ),
					),
					'default' => 'solid',
				),
				'full'  => array(
					'type'    => 'toggle',
					'label'   => __( 'Terve laius', 'wonom-meilidisainer' ),
					'default' => 0,
				),
				'align' => array_merge( $align, array( 'default' => 'center' ) ),
				'pad'   => $pad,
			),
		),
		'image'   => array(
			'label'  => __( 'Pilt', 'wonom-meilidisainer' ),
			'icon'   => 'P',
			'fields' => array(
				'url'   => array(
					'type'    => 'image',
					'label'   => __( 'Pilt', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'alt'   => array(
					'type'    => 'text',
					'label'   => __( 'Alt-tekst', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'link'  => array(
					'type'    => 'url',
					'label'   => __( 'Link klõpsamisel', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'width' => array(
					'type'    => 'range',
					'label'   => __( 'Laius (px)', 'wonom-meilidisainer' ),
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
			'label'  => __( 'Joon', 'wonom-meilidisainer' ),
			'icon'   => '—',
			'fields' => array(
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Värv', 'wonom-meilidisainer' ),
					'default' => '',
					'inherit' => 'border_color',
				),
				'pad'   => array_merge( $pad, array( 'default' => 14 ) ),
			),
		),
		'spacer'  => array(
			'label'  => __( 'Tühi ruum', 'wonom-meilidisainer' ),
			'icon'   => '↕',
			'fields' => array(
				'height' => array(
					'type'    => 'range',
					'label'   => __( 'Kõrgus (px)', 'wonom-meilidisainer' ),
					'min'     => 4,
					'max'     => 80,
					'step'    => 2,
					'default' => 24,
				),
			),
		),
		'columns' => array(
			'label'  => __( 'Kaks veergu', 'wonom-meilidisainer' ),
			'icon'   => '▥',
			'fields' => array(
				'left'  => array(
					'type'    => 'richtext',
					'label'   => __( 'Vasak veerg', 'wonom-meilidisainer' ),
					'default' => __( '<strong>Tarneaeg</strong><br>1–3 tööpäeva', 'wonom-meilidisainer' ),
					'tags'    => true,
				),
				'right' => array(
					'type'    => 'richtext',
					'label'   => __( 'Parem veerg', 'wonom-meilidisainer' ),
					'default' => __( '<strong>Küsimused?</strong><br>Kirjuta meile julgelt.', 'wonom-meilidisainer' ),
					'tags'    => true,
				),
				'pad'   => $pad,
			),
		),
		'social'  => array(
			'label'  => __( 'Sotsiaalmeedia', 'wonom-meilidisainer' ),
			'icon'   => '@',
			'fields' => array(
				'size'      => array(
					'type'    => 'range',
					'label'   => __( 'Ikooni suurus (px)', 'wonom-meilidisainer' ),
					'min'     => 18,
					'max'     => 48,
					'step'    => 2,
					'default' => 28,
				),
				'gap'       => array(
					'type'    => 'range',
					'label'   => __( 'Vahe ikoonide vahel (px)', 'wonom-meilidisainer' ),
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
			'label'  => __( 'Oma HTML', 'wonom-meilidisainer' ),
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
			'label'  => __( 'Tellimuse tabel (WooCommerce)', 'wonom-meilidisainer' ),
			'icon'   => '#',
			'woo'    => true,
			'fields' => array(
				'pad' => $pad,
			),
		),
		'addresses'    => array(
			'label'  => __( 'Aadressid', 'wonom-meilidisainer' ),
			'icon'   => 'A',
			'woo'    => true,
			'fields' => array(
				'show'           => array(
					'type'    => 'select',
					'label'   => __( 'Mida näidata', 'wonom-meilidisainer' ),
					'options' => array(
						'both'     => __( 'Arve- ja tarneaadress', 'wonom-meilidisainer' ),
						'billing'  => __( 'Ainult arveaadress', 'wonom-meilidisainer' ),
						'shipping' => __( 'Ainult tarneaadress', 'wonom-meilidisainer' ),
					),
					'default' => 'both',
				),
				'billing_title'  => array(
					'type'    => 'text',
					'label'   => __( 'Arveaadressi pealkiri', 'wonom-meilidisainer' ),
					'default' => __( 'Arveaadress', 'wonom-meilidisainer' ),
				),
				'shipping_title' => array(
					'type'    => 'text',
					'label'   => __( 'Tarneaadressi pealkiri', 'wonom-meilidisainer' ),
					'default' => __( 'Tarneaadress', 'wonom-meilidisainer' ),
				),
				'contacts'       => array(
					'type'    => 'toggle',
					'label'   => __( 'Näita telefoni ja e-posti', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'box'            => array(
					'type'    => 'toggle',
					'label'   => __( 'Raamitud kastis', 'wonom-meilidisainer' ),
					'default' => 0,
				),
				'pad'            => $pad,
			),
		),
		'customer_note' => array(
			'label'  => __( 'Kliendi märkus', 'wonom-meilidisainer' ),
			'icon'   => '"',
			'woo'    => true,
			'fields' => array(
				'title'      => array(
					'type'    => 'text',
					'label'   => __( 'Pealkiri', 'wonom-meilidisainer' ),
					'default' => __( 'Sinu märkus tellimusele', 'wonom-meilidisainer' ),
				),
				'hide_empty' => array(
					'type'    => 'toggle',
					'label'   => __( 'Peida, kui märkust pole', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'pad'        => $pad,
			),
		),
		// Ise kokku pandav toodete tabel. Erinevalt „Tellimuse tabelist" ei tule
		// see WooCommerce'i mallist, vaid veerud valid ise.
		'order_items'  => array(
			'label'  => __( 'Tooted (oma tabel)', 'wonom-meilidisainer' ),
			'icon'   => '▤',
			'woo'    => true,
			'fields' => array(
				'cols'     => array(
					'type'    => 'columns',
					'label'   => __( 'Veerud', 'wonom-meilidisainer' ),
					'options' => wmd_item_columns(),
					'default' => wmd_default_columns( wmd_item_columns(), array( 'sku', 'unit' ) ),
				),
				'header'   => array(
					'type'    => 'toggle',
					'label'   => __( 'Näita päiserida', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'img_size' => array(
					'type'    => 'range',
					'label'   => __( 'Pildi laius (px)', 'wonom-meilidisainer' ),
					'min'     => 32,
					'max'     => 160,
					'step'    => 4,
					'default' => 64,
				),
				'link'     => array(
					'type'    => 'toggle',
					'label'   => __( 'Toote nimi lingiks', 'wonom-meilidisainer' ),
					'default' => 0,
				),
				'lines'    => array(
					'type'    => 'select',
					'label'   => __( 'Jooned', 'wonom-meilidisainer' ),
					'options' => array(
						'rows' => __( 'Ridade vahel', 'wonom-meilidisainer' ),
						'grid' => __( 'Täisvõrgustik', 'wonom-meilidisainer' ),
						'none' => __( 'Ilma joonteta', 'wonom-meilidisainer' ),
					),
					'default' => 'rows',
				),
				'pad'      => $pad,
			),
		),
		'order_totals' => array(
			'label'  => __( 'Kokkuvõte (oma tabel)', 'wonom-meilidisainer' ),
			'icon'   => 'Σ',
			'woo'    => true,
			'fields' => array(
				'rows'       => array(
					'type'    => 'columns',
					'label'   => __( 'Read', 'wonom-meilidisainer' ),
					'options' => wmd_total_rows(),
					'default' => wmd_default_columns( wmd_total_rows() ),
				),
				'align'      => array(
					'type'    => 'select',
					'label'   => __( 'Paigutus', 'wonom-meilidisainer' ),
					'options' => array(
						'right' => __( 'Paremal', 'wonom-meilidisainer' ),
						'full'  => __( 'Terve laius', 'wonom-meilidisainer' ),
					),
					'default' => 'right',
				),
				'bold_total' => array(
					'type'    => 'toggle',
					'label'   => __( 'Lõppsumma rasvaselt', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'lines'      => array(
					'type'    => 'select',
					'label'   => __( 'Jooned', 'wonom-meilidisainer' ),
					'options' => array(
						'rows' => __( 'Ridade vahel', 'wonom-meilidisainer' ),
						'none' => __( 'Ilma joonteta', 'wonom-meilidisainer' ),
					),
					'default' => 'rows',
				),
				'pad'        => $pad,
			),
		),
		// Sinu enda tekst makseviisi kohta. Sisu kirjutatakse ühe korra
		// vahekaardil „Makseviisid" ja see plokk toob õige teksti kirja.
		'payment_note' => array(
			'label'  => __( 'Makseviisi juhised (oma tekst)', 'wonom-meilidisainer' ),
			'icon'   => '¤',
			'woo'    => true,
			'fields' => array(
				'title' => array(
					'type'    => 'text',
					'label'   => __( 'Pealkiri', 'wonom-meilidisainer' ),
					'default' => __( 'Makse juhised', 'wonom-meilidisainer' ),
					'tags'    => true,
				),
				'box'   => array(
					'type'    => 'toggle',
					'label'   => __( 'Raamitud kastis', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'pad'   => $pad,
			),
		),
		'payment_info' => array(
			'label'  => __( 'Makseviisi juhised (WooCommerce)', 'wonom-meilidisainer' ),
			'icon'   => '€',
			'woo'    => true,
			'fields' => array(
				'pad' => $pad,
			),
		),
		// Mitmerealine andmekast: silt + väärtus, väärtus valitakse nimekirjast
		// või kirjutatakse ise märgenditega.
		'order_details' => array(
			'label'  => __( 'Tellimuse andmed (tabel)', 'wonom-meilidisainer' ),
			'icon'   => '▦',
			'woo'    => true,
			'fields' => array(
				'rows'       => array(
					'type'    => 'pairs',
					'label'   => __( 'Read', 'wonom-meilidisainer' ),
					'default' => array(
						array(
							'label' => __( 'Tellimuse number', 'wonom-meilidisainer' ),
							'value' => '#{{order_number}}',
							'link'  => '',
						),
						array(
							'label' => __( 'Makseviis', 'wonom-meilidisainer' ),
							'value' => '{{payment_method}}',
							'link'  => '',
						),
					),
				),
				'cols'       => array(
					'type'    => 'select',
					'label'   => __( 'Veerge', 'wonom-meilidisainer' ),
					'options' => array(
						'1' => __( 'Üks veerg', 'wonom-meilidisainer' ),
						'2' => __( 'Kaks veergu', 'wonom-meilidisainer' ),
					),
					'default' => '2',
				),
				'hide_empty' => array(
					'type'    => 'toggle',
					'label'   => __( 'Peida tühjaks jäänud read', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'box'        => array(
					'type'    => 'toggle',
					'label'   => __( 'Raamitud kastis', 'wonom-meilidisainer' ),
					'default' => 0,
				),
				'pad'        => $pad,
			),
		),
		'order_meta'   => array(
			'label'  => __( 'Tellimuse väli (üks rida)', 'wonom-meilidisainer' ),
			'icon'   => '»',
			'woo'    => true,
			'fields' => array(
				'key'        => array(
					'type'    => 'metakey',
					'label'   => __( 'Välja võti tellimusel', 'wonom-meilidisainer' ),
					'hint'    => __( 'Vali nimekirjast või kirjuta ise. Töötab ka kujul {{meta:võti}}.', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'title'      => array(
					'type'    => 'text',
					'label'   => __( 'Silt', 'wonom-meilidisainer' ),
					'default' => __( 'Jälgimiskood', 'wonom-meilidisainer' ),
				),
				'link'       => array(
					'type'    => 'text',
					'label'   => __( 'Link (valikuline)', 'wonom-meilidisainer' ),
					'hint'    => __( 'Kasuta {{value}} välja väärtuse kohal.', 'wonom-meilidisainer' ),
					'default' => '',
				),
				'hide_empty' => array(
					'type'    => 'toggle',
					'label'   => __( 'Peida, kui väli on tühi', 'wonom-meilidisainer' ),
					'default' => 1,
				),
				'align'      => $align,
				'pad'        => $pad,
			),
		),
	);
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
			'image' => __( 'Pilt', 'wonom-meilidisainer' ),
			'name'  => __( 'Toode', 'wonom-meilidisainer' ),
			'sku'   => __( 'Tootekood', 'wonom-meilidisainer' ),
			'meta'  => __( 'Variandid ja lisaväljad', 'wonom-meilidisainer' ),
			'qty'   => __( 'Kogus', 'wonom-meilidisainer' ),
			'unit'  => __( 'Ühiku hind', 'wonom-meilidisainer' ),
			'total' => __( 'Rea summa', 'wonom-meilidisainer' ),
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
			'cart_subtotal'  => __( 'Vahesumma', 'wonom-meilidisainer' ),
			'discount'       => __( 'Allahindlus', 'wonom-meilidisainer' ),
			'shipping'       => __( 'Tarne', 'wonom-meilidisainer' ),
			'payment_method' => __( 'Makseviis', 'wonom-meilidisainer' ),
			'tax'            => __( 'Käibemaks', 'wonom-meilidisainer' ),
			'order_total'    => __( 'Kokku', 'wonom-meilidisainer' ),
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
				'' !== $payment ? $payment : __( 'makseviis puudub', 'wonom-meilidisainer' ),
				$date ? ' · ' . $date->date_i18n( 'd.m.Y' ) : ''
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
		'wrap' => __( 'WooCommerce\'i sisu ümber', 'wonom-meilidisainer' ),
		'full' => __( 'Terve meil ise', 'wonom-meilidisainer' ),
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
				'html' => __( 'Tere {{customer_first_name}}! Saime su tellimuse <strong>#{{order_number}}</strong> kätte ja asume seda komplekteerima.', 'wonom-meilidisainer' ),
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
				'label'    => __( 'Tellimus töösse võetud', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-processing-order.php',
			),
			'customer_completed_order'  => array(
				'label'    => __( 'Tellimus täidetud', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-completed-order.php',
			),
			'customer_on_hold_order'    => array(
				'label'    => __( 'Tellimus ootel', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-on-hold-order.php',
			),
			'customer_refunded_order'   => array(
				'label'    => __( 'Tellimus tagastatud', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-refunded-order.php',
			),
			'customer_invoice'          => array(
				'label'    => __( 'Arve / makseootel tellimus', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-invoice.php',
			),
			'customer_note'             => array(
				'label'    => __( 'Märkus kliendile', 'wonom-meilidisainer' ),
				'group'    => 'customer',
				'template' => 'emails/customer-note.php',
			),
			// Kontomeilidel ei ole tellimust: „order => false" peidab kujundajas
			// tellimuse valiku ja hoiab tellimuse ka renderdusest eemal.
			'customer_reset_password'   => array(
				'label'    => __( 'Parooli lähtestamine', 'wonom-meilidisainer' ),
				'group'    => 'account',
				'template' => 'emails/customer-reset-password.php',
				'order'    => false,
			),
			'customer_new_account'      => array(
				'label'    => __( 'Uus konto', 'wonom-meilidisainer' ),
				'group'    => 'account',
				'template' => 'emails/customer-new-account.php',
				'order'    => false,
			),
			'new_order'                 => array(
				'label'    => __( 'Uus tellimus (poele)', 'wonom-meilidisainer' ),
				'group'    => 'admin',
				'template' => 'emails/admin-new-order.php',
			),
			'cancelled_order'           => array(
				'label'    => __( 'Tühistatud tellimus (poele)', 'wonom-meilidisainer' ),
				'group'    => 'admin',
				'template' => 'emails/admin-cancelled-order.php',
			),
			'failed_order'              => array(
				'label'    => __( 'Ebaõnnestunud tellimus (poele)', 'wonom-meilidisainer' ),
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
		'customer' => __( 'Kliendi tellimusmeilid', 'wonom-meilidisainer' ),
		'account'  => __( 'Konto meilid', 'wonom-meilidisainer' ),
		'admin'    => __( 'Poe sisemised meilid', 'wonom-meilidisainer' ),
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
				'html'  => __( 'Said selle kirja, sest tegid meie poes tellimuse.', 'wonom-meilidisainer' ),
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
				'html' => __( 'Tere {{customer_first_name}}! Saime su tellimuse <strong>#{{order_number}}</strong> kätte ja asume seda komplekteerima. Anname teada, kui pakk teele läheb.', 'wonom-meilidisainer' ),
				'pad'  => 8,
			)
		),
	);
	$emails['customer_processing_order']['after'] = array(
		wmd_make_block(
			'button',
			array(
				'label' => __( 'Vaata tellimust', 'wonom-meilidisainer' ),
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
