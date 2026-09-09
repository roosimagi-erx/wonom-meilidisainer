/**
 * Meili renderdaja brauseris. Peegeldab includes/class-wmd-render.php loogikat,
 * et kujundajas oleks eelvaade kohene. Päris meili renderdab alati PHP.
 */
( function ( global ) {
	'use strict';

	// Makseviiside juhised. Plokk vajab neid, aga plokini kujundust ei anta,
	// seega hoiame neid siin ja full() värskendab iga renderdusega.
	var design_payments = {};

	function esc( str ) {
		return String( str == null ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function escAttr( str ) {
		return esc( str ).replace( /'/g, '&#039;' );
	}

	function style( rules ) {
		var out = '';
		Object.keys( rules ).forEach( function ( key ) {
			var value = rules[ key ];
			if ( value === '' || value === null || value === undefined ) {
				return;
			}
			out += key + ':' + value + ';';
		} );
		return out;
	}

	function tags( text, ctx ) {
		text = String( text == null ? '' : text );
		if ( text.indexOf( '{{' ) === -1 ) {
			return text;
		}
		return text.replace( /\{\{\s*([a-z0-9_]+)\s*\}\}/gi, function ( m, key ) {
			var k = key.toLowerCase();
			return ctx && ctx[ k ] !== undefined ? String( ctx[ k ] ) : '';
		} );
	}

	function linkify( html, brand ) {
		return String( html || '' ).replace(
			/<a(?![^>]*style=)/gi,
			'<a style="color:' + escAttr( brand.accent ) + ';text-decoration:underline;"'
		);
	}

	function num( value, fallback ) {
		var n = parseInt( value, 10 );
		return isNaN( n ) ? fallback : n;
	}

	function headingSizes( brand ) {
		var h = num( brand.heading_size, 26 );
		return {
			lg: h,
			md: Math.max( 17, Math.round( h * 0.72 ) ),
			sm: Math.max( 14, Math.round( h * 0.58 ) ),
		};
	}

	/**
	 * Kas plokk on selle tellimuse puhul nähtav. Sama loogika on PHP pool.
	 * Kui makseviis on teadmata, näitame ploki ära.
	 */
	function visible( b, ctx ) {
		var pay = b.cond && b.cond.pay ? b.cond.pay : [];

		if ( ! pay.length ) {
			return true;
		}

		var current = ctx && ctx.__payment ? ctx.__payment : '';

		return current ? pay.indexOf( current ) !== -1 : true;
	}

	function block( b, brand, ctx, defaultColor ) {
		if ( ! visible( b, ctx ) ) {
			return '';
		}

		var p = b.props || {};
		var pad = num( p.pad, 12 );
		var font = brand.font_family;
		var cell = { padding: pad + 'px 0', 'font-family': font };
		var body = '';
		var sizes = headingSizes( brand );
		var color;
		var size;

		switch ( b.type ) {
			case 'heading':
				size = sizes[ p.size ] || sizes.md;
				color = p.color ? p.color : brand.heading_color;
				cell[ 'text-align' ] = p.align;
				body = '<div style="' + escAttr( style( {
					'font-family': font,
					'font-size': size + 'px',
					'line-height': '1.3',
					'font-weight': '700',
					color: color,
				} ) ) + '">' + esc( tags( p.text, ctx ) ) + '</div>';
				break;

			case 'text':
				size = num( p.size, 0 ) || num( brand.base_size, 15 );
				color = p.color ? p.color : ( defaultColor || brand.text_color );
				cell[ 'text-align' ] = p.align;
				body = '<div style="' + escAttr( style( {
					'font-family': font,
					'font-size': size + 'px',
					'line-height': '1.6',
					color: color,
				} ) ) + '">' + linkify( tags( p.html, ctx ), brand ) + '</div>';
				break;

			case 'button':
				var label = esc( tags( p.label, ctx ) );
				if ( ! label.trim() ) {
					return '';
				}
				var outline = p.style === 'outline';
				var btn = {
					display: p.full ? 'block' : 'inline-block',
					'font-family': font,
					'font-size': Math.max( 14, num( brand.base_size, 15 ) ) + 'px',
					'font-weight': '600',
					'line-height': '1',
					padding: '14px 26px',
					'border-radius': num( brand.btn_radius, 6 ) + 'px',
					'text-decoration': 'none',
					'background-color': outline ? 'transparent' : brand.btn_bg,
					color: outline ? brand.btn_bg : brand.btn_text,
					border: '2px solid ' + brand.btn_bg,
				};
				if ( p.full ) {
					btn[ 'text-align' ] = 'center';
				}
				cell[ 'text-align' ] = p.align;
				body = '<a href="' + escAttr( tags( p.url, ctx ) ) + '" style="' + escAttr( style( btn ) ) + '">' + label + '</a>';
				break;

			case 'image':
				var src = tags( p.url, ctx );
				if ( ! String( src ).trim() ) {
					return '';
				}
				var margin = p.align === 'center' ? '0 auto' : ( p.align === 'right' ? '0 0 0 auto' : '0' );
				var img = '<img src="' + escAttr( src ) + '" alt="' + escAttr( p.alt ) + '" width="' + num( p.width, 240 ) + '" style="' + escAttr( style( {
					width: num( p.width, 240 ) + 'px',
					'max-width': '100%',
					height: 'auto',
					display: 'block',
					border: '0',
					margin: margin,
				} ) ) + '" />';
				if ( String( p.link || '' ).trim() ) {
					img = '<a href="' + escAttr( tags( p.link, ctx ) ) + '">' + img + '</a>';
				}
				cell[ 'text-align' ] = p.align;
				body = img;
				break;

			case 'divider':
				color = p.color ? p.color : brand.border_color;
				body = '<div style="border-top:1px solid ' + escAttr( color ) + ';font-size:0;line-height:0;">&nbsp;</div>';
				break;

			case 'spacer':
				var h = num( p.height, 24 );
				cell = { padding: '0', height: h + 'px' };
				body = '<div style="height:' + h + 'px;font-size:0;line-height:0;">&nbsp;</div>';
				break;

			case 'columns':
				var col = style( {
					'font-family': font,
					'font-size': num( brand.base_size, 15 ) + 'px',
					'line-height': '1.6',
					color: defaultColor || brand.text_color,
					'vertical-align': 'top',
					width: '50%',
				} );
				body = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr>' +
					'<td class="wmd-col" style="' + escAttr( col + 'padding-right:10px;' ) + '">' + linkify( tags( p.left, ctx ), brand ) + '</td>' +
					'<td class="wmd-col" style="' + escAttr( col + 'padding-left:10px;' ) + '">' + linkify( tags( p.right, ctx ), brand ) + '</td>' +
					'</tr></table>';
				break;

			case 'social':
				var nets = { facebook: 'f', instagram: 'in', youtube: 'yt', linkedin: 'li' };
				var cells = '';
				Object.keys( nets ).forEach( function ( key ) {
					if ( ! p[ key ] ) {
						return;
					}
					cells += '<td style="padding:0 4px;"><a href="' + escAttr( p[ key ] ) + '" style="' + escAttr( style( {
						display: 'inline-block',
						width: '30px',
						height: '30px',
						'line-height': '30px',
						'text-align': 'center',
						'border-radius': '15px',
						'background-color': brand.accent,
						color: '#ffffff',
						'font-family': font,
						'font-size': '12px',
						'font-weight': '700',
						'text-decoration': 'none',
					} ) ) + '">' + esc( nets[ key ] ) + '</a></td>';
				} );
				if ( ! cells ) {
					return '';
				}
				cell[ 'text-align' ] = 'center';
				body = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr>' + cells + '</tr></table>';
				break;

			case 'html':
				if ( ! String( p.code || '' ).trim() ) {
					return '';
				}
				body = tags( p.code, ctx );
				break;

			// WooCommerce'i osad: eelvaates näidis, päris meilis renderdab WooCommerce.
			case 'order_table':
				body = sampleOrderTable( brand );
				break;

			case 'addresses':
				body = sampleAddresses( brand );
				break;

			case 'order_items':
				body = itemsTable( ( ctx && ctx.__items ) || sampleItems(), p, brand );
				if ( ! body ) {
					return '';
				}
				break;

			case 'order_totals':
				body = totalsTable( ( ctx && ctx.__totals ) || sampleTotals(), p, brand );
				if ( ! body ) {
					return '';
				}
				break;

			case 'payment_note':
				var gw = ctx && ctx.__payment ? ctx.__payment : '';
				var note = ( design_payments && gw && design_payments[ gw ] ) ? design_payments[ gw ] : '';

				if ( ! String( note ).replace( /<[^>]*>/g, '' ).trim() ) {
					return '';
				}

				var inner = '';
				if ( p.title ) {
					inner += '<div style="font-family:' + font + ';font-size:' + Math.max( 15, num( brand.base_size, 15 ) ) + 'px;font-weight:700;color:' + escAttr( brand.heading_color ) + ';margin-bottom:6px;">' + esc( tags( p.title, ctx ) ) + '</div>';
				}
				inner += '<div style="font-family:' + font + ';font-size:' + num( brand.base_size, 15 ) + 'px;line-height:1.6;color:' + escAttr( brand.text_color ) + ';">' + linkify( tags( note, ctx ), brand ) + '</div>';

				body = p.box
					? '<div style="border:1px solid ' + escAttr( brand.border_color ) + ';border-left:3px solid ' + escAttr( brand.accent ) + ';border-radius:6px;padding:12px 14px;">' + inner + '</div>'
					: inner;
				break;

			case 'payment_info':
				body = '<div style="' + escAttr( style( {
					'font-family': font,
					'font-size': num( brand.base_size, 15 ) + 'px',
					'line-height': '1.6',
					color: brand.muted_color,
					border: '1px dashed ' + brand.border_color,
					padding: '10px 14px',
				} ) ) + '">Makselahenduse juhised (nt pangaülekande rekvisiidid) ilmuvad siia päris meilis, kui makselahendus neid saadab.</div>';
				break;

			case 'customer_note':
				body = '<div style="' + escAttr( style( {
					'border-left': '3px solid ' + brand.accent,
					'background-color': '#00000008',
					padding: '10px 14px',
					'font-family': font,
					'font-size': num( brand.base_size, 15 ) + 'px',
					'line-height': '1.6',
					color: brand.text_color,
				} ) ) + '">' +
					( p.title ? '<strong style="color:' + escAttr( brand.heading_color ) + ';">' + esc( p.title ) + '</strong><br>' : '' ) +
					'Palun jätke pakk pakiautomaati.</div>';
				break;

			case 'order_meta':
				var sample = p.key ? 'CC123456789EE' : '—';
				var shown = p.link ? '<a style="color:' + escAttr( brand.accent ) + ';">' + esc( sample ) + '</a>' : esc( sample );
				cell[ 'text-align' ] = p.align;
				body = '<div style="' + escAttr( style( {
					'font-family': font,
					'font-size': num( brand.base_size, 15 ) + 'px',
					'line-height': '1.6',
					color: brand.text_color,
				} ) ) + '">' +
					( p.title ? '<strong style="color:' + escAttr( brand.heading_color ) + ';">' + esc( p.title ) + ':</strong> ' : '' ) +
					shown + '</div>';
				break;

			default:
				return '';
		}

		return '<tr data-wmd-block="' + escAttr( b.id ) + '"><td style="' + escAttr( style( cell ) ) + '">' + body + '</td></tr>';
	}

	function blocks( list, brand, ctx, defaultColor ) {
		if ( ! list || ! list.length ) {
			return '';
		}
		var rows = '';
		list.forEach( function ( b ) {
			if ( b && b.type ) {
				rows += block( b, brand, ctx, defaultColor );
			}
		} );
		if ( ! rows ) {
			return '';
		}
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">' + rows + '</table>';
	}

	/**
	 * Brändi logo päise ülaosas. Renderdub ainult siis, kui logo on valitud.
	 */
	function logo( brand, ctx ) {
		if ( ! brand.logo_url ) {
			return '';
		}
		var width = num( brand.logo_width, 160 );
		var align = brand.logo_align || 'center';
		var margin = align === 'center' ? '0 auto' : ( align === 'right' ? '0 0 0 auto' : '0' );

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">' +
			'<tr><td style="text-align:' + escAttr( align ) + ';padding:0 0 6px 0;">' +
			'<img src="' + escAttr( brand.logo_url ) + '" alt="' + escAttr( tags( '{{site_title}}', ctx ) ) + '" width="' + width + '" style="' + escAttr( style( {
				width: width + 'px',
				'max-width': '100%',
				height: 'auto',
				display: 'block',
				border: '0',
				margin: margin,
			} ) ) + '" /></td></tr></table>';
	}

	function sampleItems() {
		return [
			{ image: '', name: 'Puuvillane T-särk', url: '', sku: 'TS-100', meta: 'Suurus: M', qty: '2', unit: '19,90 €', total: '39,80 €' },
			{ image: '', name: 'Villane sall', url: '', sku: 'SL-042', meta: 'Värv: hall', qty: '1', unit: '42,60 €', total: '42,60 €' },
		];
	}

	function sampleTotals() {
		return [
			{ key: 'cart_subtotal', label: 'Vahesumma:', value: '82,40 €' },
			{ key: 'discount', label: 'Allahindlus:', value: '-8,00 €' },
			{ key: 'shipping', label: 'Tarne:', value: '5,00 €' },
			{ key: 'payment_method', label: 'Makseviis:', value: 'Panga ülekanne' },
			{ key: 'order_total', label: 'Kokku:', value: '79,40 €' },
		];
	}

	var RIGHT_COLS = [ 'qty', 'unit', 'total' ];

	function itemsTable( rows, p, brand ) {
		var cols = ( p.cols || [] ).filter( function ( c ) {
			return c.on;
		} );

		if ( ! cols.length || ! rows.length ) {
			return '';
		}

		var f = brand.font_family;
		var fs = num( brand.base_size, 15 );
		var border = p.lines === 'none' ? '' : '1px solid ' + brand.border_color;
		var grid = p.lines === 'grid';
		var cell = 'padding:10px 8px;font-family:' + f + ';font-size:' + fs + 'px;line-height:1.5;vertical-align:top;';
		var out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">';

		if ( p.header ) {
			out += '<thead><tr>';
			cols.forEach( function ( c ) {
				var s = cell + 'text-align:' + ( RIGHT_COLS.indexOf( c.key ) !== -1 ? 'right' : 'left' ) + ';font-weight:700;color:' + brand.heading_color + ';';
				if ( border ) {
					s += 'border-bottom:2px solid ' + brand.border_color + ';';
				}
				if ( grid && border ) {
					s += 'border:' + border + ';';
				}
				out += '<th style="' + escAttr( s ) + '">' + esc( c.label ) + '</th>';
			} );
			out += '</tr></thead>';
		}

		out += '<tbody>';

		rows.forEach( function ( row ) {
			out += '<tr>';
			cols.forEach( function ( c ) {
				var s = cell + 'text-align:' + ( RIGHT_COLS.indexOf( c.key ) !== -1 ? 'right' : 'left' ) + ';color:' + brand.text_color + ';';
				if ( border ) {
					s += grid ? 'border:' + border + ';' : 'border-bottom:' + border + ';';
				}
				out += '<td style="' + escAttr( s ) + '">' + itemCell( c.key, row, p, brand ) + '</td>';
			} );
			out += '</tr>';
		} );

		return out + '</tbody></table>';
	}

	function itemCell( key, row, p, brand ) {
		var value = row[ key ] === undefined ? '' : row[ key ];

		if ( key === 'image' ) {
			if ( ! value ) {
				return '<div style="width:' + num( p.img_size, 64 ) + 'px;height:' + num( p.img_size, 64 ) + 'px;background:' + brand.border_color + ';border-radius:4px;"></div>';
			}
			var w = num( p.img_size, 64 );
			return '<img src="' + escAttr( value ) + '" alt="" width="' + w + '" style="width:' + w + 'px;max-width:100%;height:auto;display:block;border:0;border-radius:4px;" />';
		}

		if ( key === 'name' ) {
			var name = esc( value );
			return ( p.link && row.url ) ? '<a href="' + escAttr( row.url ) + '" style="color:' + escAttr( brand.accent ) + ';text-decoration:none;">' + name + '</a>' : name;
		}

		if ( key === 'meta' ) {
			return value ? '<span style="font-size:' + Math.max( 11, num( brand.base_size, 15 ) - 2 ) + 'px;color:' + escAttr( brand.muted_color ) + ';">' + value + '</span>' : '&nbsp;';
		}

		// unit ja total tulevad WooCommerce'ilt juba HTML-ina.
		if ( key === 'unit' || key === 'total' ) {
			return value || '&nbsp;';
		}

		return esc( value );
	}

	function totalsTable( rows, p, brand ) {
		if ( ! rows.length ) {
			return '';
		}

		var wanted = {};
		( p.rows || [] ).forEach( function ( r ) {
			wanted[ r.key ] = r;
		} );

		var f = brand.font_family;
		var fs = num( brand.base_size, 15 );
		var border = p.lines === 'none' ? '' : '1px solid ' + brand.border_color;
		var table = p.align === 'right' ? 'width:60%;margin-left:auto;' : 'width:100%;';
		var out = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="' + escAttr( table ) + 'border-collapse:collapse;">';

		rows.forEach( function ( row ) {
			var w = wanted[ row.key ];

			if ( w && ! w.on ) {
				return;
			}

			var label = ( w && w.label ) ? w.label : row.label;
			var last = row.key === 'order_total' && p.bold_total;
			var cell = 'padding:8px 8px;font-family:' + f + ';font-size:' + fs + 'px;line-height:1.5;';

			if ( border ) {
				cell += 'border-bottom:' + border + ';';
			}

			out += '<tr><th style="' + escAttr( cell + 'text-align:left;font-weight:' + ( last ? '700' : '600' ) + ';color:' + brand.heading_color + ';' ) + '">' +
				esc( String( label ).replace( /<[^>]*>/g, '' ) ) + '</th>' +
				'<td style="' + escAttr( cell + 'text-align:right;color:' + brand.text_color + ';font-weight:' + ( last ? '700' : '400' ) + ';' ) + '">' + row.value + '</td></tr>';
		} );

		return out + '</table>';
	}

	function sampleBody( brand ) {
		return sampleOrderTable( brand ) + sampleAddresses( brand );
	}

	function sampleAddresses( brand ) {
		var h2 = Math.max( 17, Math.round( num( brand.heading_size, 26 ) * 0.72 ) );
		return '<h2 style="font-family:' + brand.font_family + ';color:' + brand.heading_color + ';font-size:' + h2 + 'px;margin:22px 0 10px 0;line-height:1.3;">Arveaadress</h2>' +
			'<p style="font-family:' + brand.font_family + ';font-size:' + num( brand.base_size, 15 ) + 'px;color:' + brand.text_color + ';line-height:1.6;margin:0;">Mari Tamm<br>Pikk 12-4<br>10123 Tallinn<br>Eesti</p>';
	}

	function sampleOrderTable( brand ) {
		var b = brand.border_color;
		var h = brand.heading_color;
		var t = brand.text_color;
		var f = brand.font_family;
		var fs = num( brand.base_size, 15 );
		var h2 = Math.max( 17, Math.round( num( brand.heading_size, 26 ) * 0.72 ) );
		var cellCss = 'text-align:left;border:1px solid ' + b + ';padding:10px;font-family:' + f + ';font-size:' + fs + 'px;';

		function row( name, qty, price ) {
			return '<tr><td style="' + cellCss + 'color:' + t + ';">' + esc( name ) + '</td>' +
				'<td style="' + cellCss + 'color:' + t + ';">' + qty + '</td>' +
				'<td style="' + cellCss + 'color:' + t + ';">' + esc( price ) + '</td></tr>';
		}

		function foot( label, value ) {
			return '<tr><th style="' + cellCss + 'color:' + h + ';">' + esc( label ) + '</th>' +
				'<td colspan="2" style="' + cellCss + 'color:' + t + ';">' + value + '</td></tr>';
		}

		var out = '<h2 style="font-family:' + f + ';color:' + h + ';font-size:' + h2 + 'px;margin:22px 0 10px 0;line-height:1.3;">Tellimus #1042</h2>';
		out += '<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;border-color:' + b + ';margin-bottom:16px;"><thead><tr>' +
			'<th style="' + cellCss + 'color:' + h + ';">Toode</th>' +
			'<th style="' + cellCss + 'color:' + h + ';">Kogus</th>' +
			'<th style="' + cellCss + 'color:' + h + ';">Hind</th>' +
			'</tr></thead><tbody>';
		out += row( 'Puuvillane T-särk, M', 2, '39,80 €' );
		out += row( 'Villane sall', 1, '42,60 €' );
		out += '</tbody><tfoot>';
		out += foot( 'Vahesumma:', '82,40 €' );
		out += foot( 'Tarne:', '5,00 €' );
		out += foot( 'Kokku:', '<strong>87,40 €</strong>' );
		out += '</tfoot></table>';

		return out;
	}

	/**
	 * Terve meil ühe HTML-stringina.
	 *
	 * @param {Object} design  Kujundus.
	 * @param {string} emailId Meili võti.
	 * @param {Object} ctx     Märgendite kontekst.
	 * @param {string} heading Pealkiri, kui kujundajas pole oma.
	 * @param {string} bodyHtml Sisuosa.
	 * @return {string} HTML.
	 */
	/**
	 * Kujundaja märgis WooCommerce'i ala ümber. Ainult kanvasel — päris meili
	 * see ei jõua, sest päris meili renderdab PHP.
	 */
	function wcMark( inner, brand ) {
		return '<div style="position:relative;margin:6px 0;padding:26px 10px 10px 10px;border:1px dashed #c2a561;border-radius:6px;background:#fffdf6;">' +
			'<div style="position:absolute;top:0;left:0;right:0;display:flex;gap:8px;align-items:center;justify-content:space-between;' +
			'padding:3px 8px;background:#f4e9cf;border-radius:5px 5px 0 0;font:600 11px/1.4 -apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#6b5216;">' +
			'<span>WooCommerce\'i enda sisu — seda siit muuta ei saa</span>' +
			'<button type="button" data-wmd-action="takeover" style="border:0;border-radius:4px;background:#6b5216;color:#fff;' +
			'font:600 11px/1 -apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:4px 8px;cursor:pointer;">Võta üle</button>' +
			'</div>' + inner + '</div>';
	}

	function full( design, emailId, ctx, heading, bodyHtml, opts ) {
		opts = opts || {};
		design_payments = design.payments || {};
		var brand = design.brand;
		var settings = ( design.emails && design.emails[ emailId ] ) || { heading: '', before: [], after: [] };
		var width = num( brand.width, 600 );
		var pad = num( brand.pad_x, 28 );
		var radius = num( brand.radius, 10 );
		var headBg = brand.header_bg || brand.body_bg;
		var title = settings.heading && settings.heading.trim() ? tags( settings.heading, ctx ) : ( heading || '' );
		var body = bodyHtml === undefined || bodyHtml === null ? sampleBody( brand ) : bodyHtml;

		// WooCommerce'i enda CSS peab tulema esimesena, meie oma kirjutab üle.
		var css = ( opts.extraCss || '' ) +
			'body{margin:0;padding:0;}' +
			'a{color:' + brand.accent + ';}' +
			'@media only screen and (max-width:620px){' +
			'.wmd-card{width:100% !important;}' +
			'.wmd-col{display:block !important;width:100% !important;padding:0 0 12px 0 !important;}' +
			'}' +
			( brand.custom_css || '' );

		var out = '<!DOCTYPE html><html><head><meta charset="utf-8" />' +
			'<meta name="viewport" content="width=device-width, initial-scale=1.0" />' +
			'<style type="text/css">' + css + '</style></head>';

		out += '<body style="' + escAttr( style( {
			margin: '0',
			padding: '0',
			'background-color': brand.page_bg,
			'font-family': brand.font_family,
		} ) ) + '">';

		out += '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="' + escAttr( style( {
			'background-color': brand.page_bg,
			width: '100%',
			margin: '0',
			padding: '24px 12px',
		} ) ) + '"><tr><td align="center" valign="top">';

		out += '<table role="presentation" width="' + width + '" cellpadding="0" cellspacing="0" border="0" class="wmd-card" style="' + escAttr( style( {
			width: width + 'px',
			'max-width': '100%',
			'background-color': brand.body_bg,
			'border-radius': radius + 'px',
			border: '1px solid ' + brand.border_color,
			overflow: 'hidden',
		} ) ) + '">';

		var headInner = logo( brand, ctx ) + blocks( design.header, brand, ctx );
		if ( headInner ) {
			out += '<tr data-wmd-zone="header"><td style="' + escAttr( style( {
				padding: pad + 'px ' + pad + 'px 0 ' + pad + 'px',
				'background-color': headBg,
			} ) ) + '">' + headInner + '</td></tr>';
		}

		out += '<tr><td style="' + escAttr( style( {
			padding: pad + 'px',
			'font-family': brand.font_family,
			'font-size': num( brand.base_size, 15 ) + 'px',
			'line-height': '1.6',
			color: brand.text_color,
		} ) ) + '">';

		if ( String( title ).trim() ) {
			out += '<h1 style="' + escAttr( style( {
				margin: '0 0 14px 0',
				padding: '0',
				'font-family': brand.font_family,
				'font-size': num( brand.heading_size, 26 ) + 'px',
				'line-height': '1.25',
				'font-weight': '700',
				color: brand.heading_color,
			} ) ) + '">' + esc( title ) + '</h1>';
		}

		if ( settings.mode === 'full' ) {
			// Terve meil tuleb plokkidest — WooCommerce'i enda sisu ei renderdata.
			out += '<div data-wmd-zone="body">' + blocks( settings.body, brand, ctx ) + '</div>';
		} else {
			var wc = '<div class="wmd-wc-content" data-wmd-zone="wc">' + body + '</div>';

			out += '<div data-wmd-zone="before">' + blocks( settings.before, brand, ctx ) + '</div>';
			out += opts.markWc ? wcMark( wc, brand ) : wc;
			out += '<div data-wmd-zone="after">' + blocks( settings.after, brand, ctx ) + '</div>';
		}

		out += '</td></tr></table>';

		if ( design.footer && design.footer.length ) {
			out += '<table role="presentation" width="' + width + '" cellpadding="0" cellspacing="0" border="0" class="wmd-card" style="' + escAttr( style( {
				width: width + 'px',
				'max-width': '100%',
			} ) ) + '"><tr data-wmd-zone="footer"><td style="' + escAttr( style( {
				padding: '18px ' + pad + 'px 4px ' + pad + 'px',
				'font-family': brand.font_family,
				'font-size': '13px',
				'line-height': '1.6',
				color: brand.muted_color,
			} ) ) + '">' + blocks( design.footer, brand, ctx, brand.muted_color ) + '</td></tr></table>';
		}

		out += '</td></tr></table></body></html>';

		return out;
	}

	global.WMDRender = {
		full: full,
		blocks: blocks,
		sampleBody: sampleBody,
		tags: tags,
		esc: esc,
	};
}( window ) );
