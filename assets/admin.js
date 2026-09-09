/**
 * Meilidisaineri kujundaja liides.
 *
 * Kolm tulpa: vasakul sisu, keskel elav eelvaade, paremal valitud elemendi seaded.
 * Eelvaade renderdatakse brauseris (renderer.js), päris meili renderdab PHP.
 */
( function () {
	'use strict';

	var cfg = window.WMD || {};
	var root = document.getElementById( 'wmd-app' );
	if ( ! root || ! window.WMDRender ) {
		return;
	}

	var i18n = cfg.i18n || {};
	var emailIds = Object.keys( cfg.emails || {} );

	/**
	 * PHP tühi massiiv jõuab JSON-is kujul [], mitte {}. JavaScripti massiivile
	 * string-võtme lisamine kaob JSON.stringify käigus vaikselt ära, seega
	 * teeme võtmega kogumid siin kindlasti objektiks.
	 *
	 * @param {Object} design Kujundus.
	 * @return {Object} Sama kujundus, kindlate tüüpidega.
	 */
	function normalise( design ) {
		var d = JSON.parse( JSON.stringify( design ) );

		if ( ! d.payments || Array.isArray( d.payments ) ) {
			var fixed = {};
			Object.keys( d.payments || {} ).forEach( function ( k ) {
				fixed[ k ] = d.payments[ k ];
			} );
			d.payments = fixed;
		}

		return d;
	}

	var state = {
		design: normalise( cfg.design ),
		tab: 'brand',
		email: emailIds[ 0 ] || '',
		selected: null, // { zone: 'header'|'footer'|'before'|'after', id: 'b123' }
		device: 'desktop',
		enabled: !! cfg.enabled,
		dirty: false,
		toast: '',
		scroll: 0,
		updates: cfg.updates || { source: 'off', repo: '', json: '', token: '', current: '', remote: '' },
		updateLog: [],
		// Eelvaate tellimus: 0 = poe viimane.
		order: 0,
	};

	var previewTimer = null;

	/**
	 * WooCommerce'i enda sisu meili ja tellimuse kohta. See ei sõltu plokkidest,
	 * seega piisab ühest päringust — edasi tuleb kanvas mälust ja jääb kiireks.
	 */
	var wcCache = {};

	/* ---------------------------------------------------------------- abi */

	function esc( str ) {
		return String( str == null ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function el( html ) {
		var d = document.createElement( 'div' );
		d.innerHTML = html;
		return d.firstElementChild;
	}

	function zoneList( zone ) {
		if ( zone === 'header' || zone === 'footer' ) {
			return state.design[ zone ];
		}
		var e = state.design.emails[ state.email ];
		if ( ! e ) {
			return [];
		}
		if ( ! e[ zone ] ) {
			e[ zone ] = [];
		}
		return e[ zone ];
	}

	function emailSettings() {
		var e = state.design.emails[ state.email ];
		if ( ! e ) {
			e = { mode: 'wrap', subject: '', heading: '', before: [], after: [], body: [] };
			state.design.emails[ state.email ] = e;
		}
		if ( ! e.mode ) {
			e.mode = 'wrap';
		}
		if ( ! e.body ) {
			e.body = [];
		}
		return e;
	}

	function findBlock( sel ) {
		if ( ! sel ) {
			return null;
		}
		var list = zoneList( sel.zone );
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].id === sel.id ) {
				return list[ i ];
			}
		}
		return null;
	}

	function blockIndex( zone, id ) {
		var list = zoneList( zone );
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].id === id ) {
				return i;
			}
		}
		return -1;
	}

	function newId() {
		return 'b' + Math.random().toString( 36 ).slice( 2, 10 );
	}

	function makeBlock( type ) {
		var def = cfg.blockTypes[ type ];
		var props = {};

		Object.keys( def.fields ).forEach( function ( key ) {
			var value = def.fields[ key ].default;

			// Massiivid (nt veerud) tuleb kopeerida, muidu jagaksid kõik plokid
			// sama nimekirja ja ühe muutmine muudaks kõiki.
			props[ key ] = ( value && typeof value === 'object' ) ? JSON.parse( JSON.stringify( value ) ) : value;
		} );

		return { id: newId(), type: type, props: props, cond: { pay: [] } };
	}

	function blockCond( block ) {
		if ( ! block.cond ) {
			block.cond = { pay: [] };
		}
		if ( ! block.cond.pay ) {
			block.cond.pay = [];
		}
		return block.cond;
	}

	function markDirty() {
		state.dirty = true;
		var badge = root.querySelector( '.wmd-dirty' );
		if ( badge ) {
			badge.hidden = false;
		}
	}

	function toast( message, kind ) {
		var box = root.querySelector( '.wmd-toast' );
		if ( ! box ) {
			return;
		}
		box.textContent = message;
		box.className = 'wmd-toast is-visible' + ( kind ? ' is-' + kind : '' );
		clearTimeout( box._timer );
		box._timer = setTimeout( function () {
			box.className = 'wmd-toast';
		}, 3200 );
	}

	/* ------------------------------------------------------------ eelvaade */

	/**
	 * Valitud eelvaate tellimus, või null kui poes tellimusi pole.
	 */
	function currentOrder() {
		var list = cfg.orders || [];

		if ( ! list.length ) {
			return null;
		}

		if ( ! state.order ) {
			return list[ 0 ];
		}

		for ( var i = 0; i < list.length; i++ ) {
			if ( String( list[ i ].id ) === String( state.order ) ) {
				return list[ i ];
			}
		}

		return list[ 0 ];
	}

	/**
	 * Eelvaate kontekst. Makseviis on siin selleks, et brauser oskaks plokkide
	 * nähtavustingimust sama moodi hinnata nagu server; read ja kokkuvõte
	 * tulevad serverist, kui need on juba käes.
	 *
	 * @param {Object} wc Serverist toodud tellimuse andmed.
	 * @return {Object} Kontekst.
	 */
	function previewCtx( wc ) {
		var order = currentOrder();

		// Näidisväärtused on ainult varuvariant. Kui server on valitud tellimuse
		// andmed saatnud, kirjutavad need näidise üle — muidu jääks kanvasele
		// „Tere Mari, tellimus #1042" ka siis, kui vaatad päris tellimust.
		return Object.assign(
			{},
			cfg.sampleCtx || {},
			( wc && wc.ctx ) || {},
			{
				__payment: order ? order.payment : '',
				__items: wc && wc.items && wc.items.length ? wc.items : null,
				__totals: wc && wc.totals && wc.totals.length ? wc.totals : null,
				__parts: ( wc && wc.parts ) || null,
				__fields: ( wc && wc.fields ) || null,
			}
		);
	}

	/**
	 * WooCommerce'i enda pealkiri või teema. Kui seda kätte ei saa (nt WooCommerce
	 * puudub), langeme tagasi meili nimele, et eelvaade ei jääks tühjaks.
	 *
	 * @param {string} id    Meili võti.
	 * @param {string} field 'heading' või 'subject'.
	 * @return {string} Tekst.
	 */
	function wcDefault( id, field ) {
		var d = cfg.wcDefaults && cfg.wcDefaults[ id ];

		if ( d && d[ field ] ) {
			return d[ field ];
		}

		return cfg.emails[ id ] ? cfg.emails[ id ].label : '';
	}

	function schedulePreview() {
		clearTimeout( previewTimer );
		previewTimer = setTimeout( updatePreview, 180 );
	}

	function wcKey() {
		return state.email + '|' + ( state.order || 0 );
	}

	/**
	 * Teade, kui WooCommerce'i sisu ei õnnestunud kätte saada — siis on kanvasel
	 * näidissisu ja seda peab kasutaja teadma.
	 */
	function wcNoteHtml() {
		if ( emailSettings().mode === 'full' ) {
			return '';
		}

		var wc = wcCache[ wcKey() ];

		if ( ! wc || wc.pending || wc.html ) {
			return '';
		}

		return '<div class="wmd-server-note is-warn">Kanvasel on WooCommerce\'i osas <strong>näidissisu</strong>, mitte päris tekst' +
			( wc.why ? ' — ' + esc( wc.why ) : '' ) + '</div>';
	}

	/**
	 * Toob WooCommerce'i sisuosa, kui seda veel mälus pole. Kuni vastus tuleb,
	 * näitab kanvas näidissisu — nii ei jää vaade tühjaks.
	 */
	function ensureWcPart() {
		var key = wcKey();

		// Ka täisrežiimis on vaja tellimuse ridu, kokkuvõtet ja välju — ainult
		// WooCommerce'i sisuosa jääb seal kasutamata.
		if ( wcCache[ key ] ) {
			return;
		}

		wcCache[ key ] = { pending: true, html: '', css: '' };

		post( 'wmd_wc_part', {
			email: state.email,
			order: state.order || 0,
			design: JSON.stringify( state.design ),
		} ).then( function ( res ) {
			wcCache[ key ] = {
				pending: false,
				html: res.html || '',
				css: res.css || '',
				items: res.items || [],
				totals: res.totals || [],
				fields: res.fields || [],
				ctx: res.ctx || {},
				parts: res.parts || {},
				why: res.why || '',
			};

			if ( wcKey() === key ) {
				render();
			}
		} ).catch( function () {
			wcCache[ key ] = { pending: false, html: '', css: '', items: [], totals: [], fields: [], ctx: {}, parts: {}, why: 'Ei saanud WooCommerce\'i sisu kätte.' };
		} );
	}

	/**
	 * Selle tellimuse päris väljad väljavaliku jaoks.
	 */
	function orderFields() {
		var wc = wcCache[ wcKey() ];

		return wc && wc.fields ? wc.fields : [];
	}


	function updatePreview() {
		var frame = root.querySelector( '.wmd-frame' );
		if ( ! frame ) {
			return;
		}

		ensureWcPart();

		var wc = wcCache[ wcKey() ];
		var full = emailSettings().mode === 'full';

		var html = window.WMDRender.full(
			state.design,
			state.email,
			previewCtx( wc ),
			wcDefault( state.email, 'heading' ),
			// Päris WooCommerce'i sisu, kui see on käes. Muidu näidis.
			( wc && ! wc.pending && wc.html ) ? wc.html : undefined,
			{
				extraCss: wc && wc.css ? wc.css : '',
				markWc: ! full,
				assetsUrl: cfg.assetsUrl || '',
			}
		);

		var doc = frame.contentDocument;
		doc.open();
		doc.write( html );
		doc.close();

		// Hoiame kerimiskoha, et muutmine ei viskaks meili algusesse.
		frame.contentWindow.scrollTo( 0, state.scroll );
		wireFrame( doc );
	}

	function wireFrame( doc ) {
		doc.defaultView.addEventListener( 'scroll', function () {
			state.scroll = doc.defaultView.scrollY || 0;
		} );

		if ( state.serverHtml ) {
			return;
		}

		// „Võta üle" WooCommerce'i ala sildil.
		doc.addEventListener( 'click', function ( ev ) {
			var btn = ev.target.closest ? ev.target.closest( '[data-wmd-action="takeover"]' ) : null;

			if ( ! btn ) {
				return;
			}

			ev.preventDefault();
			ev.stopPropagation();

			var e = emailSettings();
			e.mode = 'full';

			if ( ! e.body.length ) {
				// Olemasolev sisu kopeerime kehasse ja jätame ka originaali alles,
				// et wrap-režiimi tagasi minnes ei oleks midagi kadunud.
				e.body = JSON.parse( JSON.stringify( e.before ) )
					.concat( seedBody().slice( 1 ) )
					.concat( JSON.parse( JSON.stringify( e.after ) ) );

				e.body.forEach( function ( b ) {
					b.id = newId();
				} );
			}

			state.selected = null;
			markDirty();
			render();
			invalidatePreview();
			toast( 'Meil on nüüd täisrežiimis — WooCommerce\'i oma tekst enam kirja ei lähe', 'ok' );
		}, true );

		doc.addEventListener( 'click', function ( ev ) {
			var node = ev.target;
			while ( node && node !== doc.body ) {
				if ( node.hasAttribute && node.hasAttribute( 'data-wmd-block' ) ) {
					var id = node.getAttribute( 'data-wmd-block' );
					var zone = zoneOfId( id );
					if ( zone ) {
						ev.preventDefault();
						selectBlock( zone, id );
					}
					return;
				}
				node = node.parentNode;
			}
		}, true );

		// Lingid eelvaates ei tohi navigeerida.
		doc.addEventListener( 'click', function ( ev ) {
			var a = ev.target.closest ? ev.target.closest( 'a' ) : null;
			if ( a ) {
				ev.preventDefault();
			}
		} );
	}

	function zoneOfId( id ) {
		var zones = [ 'header', 'footer', 'before', 'after', 'body' ];
		for ( var i = 0; i < zones.length; i++ ) {
			if ( blockIndex( zones[ i ], id ) !== -1 ) {
				return zones[ i ];
			}
		}
		return null;
	}

	function selectBlock( zone, id ) {
		state.selected = { zone: zone, id: id };
		if ( zone === 'header' ) {
			state.tab = 'header';
		} else if ( zone === 'footer' ) {
			state.tab = 'footer';
		} else {
			state.tab = 'emails';
		}
		render();
	}

	/* ------------------------------------------------------------- väljad */

	function tagPicker( target ) {
		var items = Object.keys( cfg.tags || {} ).map( function ( key ) {
			return '<button type="button" class="wmd-tag" data-tag="' + esc( key ) + '">' +
				'<code>{{' + esc( key ) + '}}</code><span>' + esc( cfg.tags[ key ].label ) + '</span></button>';
		} ).join( '' );

		// Selle tellimuse enda väljad — nii ei pea võtmeid peast teadma.
		var fields = orderFields();

		if ( fields.length ) {
			items += '<div class="wmd-tags-head">Selle tellimuse väljad</div>' +
				fields.map( function ( f ) {
					return '<button type="button" class="wmd-tag" data-tag="meta:' + esc( f.key ) + '">' +
						'<code>{{meta:' + esc( f.key ) + '}}</code><span>' + esc( f.sample ) + '</span></button>';
				} ).join( '' );
		}

		return '<div class="wmd-tags" data-for="' + esc( target ) + '">' +
			'<button type="button" class="wmd-tags-toggle" title="Lisa muutuja">{ }</button>' +
			'<div class="wmd-tags-menu" hidden>' + items + '</div></div>';
	}

	/**
	 * Veergude toimeti: lülita sisse-välja, muuda silti, tõsta järjekorras.
	 */
	function columnsHtml( scope, key, field, value ) {
		var options = field.options || {};
		var cols = Array.isArray( value ) && value.length ? value : [];

		var rows = cols.map( function ( col, index ) {
			return '<li class="wmd-colrow" data-index="' + index + '">' +
				'<label class="wmd-colon"><input type="checkbox" data-col-on="' + index + '"' + ( col.on ? ' checked' : '' ) + ' /></label>' +
				'<input type="text" class="wmd-input wmd-collabel" data-col-label="' + index + '" value="' + esc( col.label ) + '" ' +
				'placeholder="' + esc( options[ col.key ] || col.key ) + '" />' +
				'<span class="wmd-colmove">' +
				'<button type="button" data-col-up="' + index + '" title="Üles"' + ( index === 0 ? ' disabled' : '' ) + '>↑</button>' +
				'<button type="button" data-col-down="' + index + '" title="Alla"' + ( index === cols.length - 1 ? ' disabled' : '' ) + '>↓</button>' +
				'</span></li>';
		} ).join( '' );

		return '<div class="wmd-field"><label class="wmd-label">' + esc( field.label ) + '</label>' +
			'<ul class="wmd-cols" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '">' + rows + '</ul>' +
			'<p class="wmd-hint">Linnuke näitab, kas veerg läheb kirja. Silti saab ümber kirjutada, nooltega järjekorda muuta.</p></div>';
	}

	function fieldHtml( scope, key, field, value ) {
		var id = 'wmd-f-' + scope + '-' + key;
		var label = '<label class="wmd-label" for="' + esc( id ) + '">' + esc( field.label ) + '</label>';
		var attrs = 'id="' + esc( id ) + '" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '"';
		var body = '';

		if ( field.type === 'columns' ) {
			return columnsHtml( scope, key, field, value );
		}

		switch ( field.type ) {
			case 'text':
			case 'url':
				body = '<div class="wmd-inline">' +
					'<input type="text" class="wmd-input" ' + attrs + ' value="' + esc( value ) + '" />' +
					( field.tags ? tagPicker( id ) : '' ) + '</div>';
				break;

			case 'richtext':
				body = '<div class="wmd-rich">' +
					'<div class="wmd-rich-bar">' +
					'<button type="button" data-wrap="strong" title="Rasvane"><b>B</b></button>' +
					'<button type="button" data-wrap="em" title="Kaldkiri"><i>I</i></button>' +
					'<button type="button" data-wrap="br" title="Reavahetus">↵</button>' +
					'<button type="button" data-wrap="a" title="Link">🔗</button>' +
					( field.tags ? tagPicker( id ) : '' ) +
					'</div>' +
					'<textarea class="wmd-input wmd-textarea" rows="4" ' + attrs + '>' + esc( value ) + '</textarea>' +
					'</div>';
				break;

			case 'textarea':
				body = '<div class="wmd-rich">' +
					( field.tags ? '<div class="wmd-rich-bar">' + tagPicker( id ) + '</div>' : '' ) +
					'<textarea class="wmd-input wmd-textarea wmd-mono" rows="6" ' + attrs + '>' + esc( value ) + '</textarea>' +
					'</div>';
				break;

			case 'select':
				var opts = Object.keys( field.options ).map( function ( optKey ) {
					var sel = String( value ) === String( optKey ) ? ' selected' : '';
					return '<option value="' + esc( optKey ) + '"' + sel + '>' + esc( field.options[ optKey ] ) + '</option>';
				} ).join( '' );
				body = '<select class="wmd-input" ' + attrs + '>' + opts + '</select>';
				break;

			case 'color':
				var inherited = ! value;
				var shown = value || ( field.inherit ? state.design.brand[ field.inherit ] : '#000000' );
				body = '<div class="wmd-color">' +
					'<input type="color" class="wmd-swatch" ' + attrs + ' value="' + esc( shown ) + '" />' +
					'<input type="text" class="wmd-input wmd-hex" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '" value="' + esc( value ) + '" placeholder="' + esc( inherited && field.inherit ? i18n.inherit : shown ) + '" />' +
					( field.inherit ? '<button type="button" class="wmd-mini" data-clear="' + esc( scope ) + '|' + esc( key ) + '">' + esc( i18n.inherit ) + '</button>' : '' ) +
					'</div>';
				break;

			case 'range':
				var shownNum = value || ( field.inherit ? state.design.brand[ field.inherit ] : field.min );
				body = '<div class="wmd-range">' +
					'<input type="range" ' + attrs + ' min="' + field.min + '" max="' + field.max + '" step="' + field.step + '" value="' + esc( shownNum ) + '" />' +
					'<output>' + esc( shownNum ) + '</output>' +
					'</div>';
				break;

			case 'toggle':
				return '<div class="wmd-field wmd-field-toggle"><label class="wmd-switch">' +
					'<input type="checkbox" ' + attrs + ( value ? ' checked' : '' ) + ' />' +
					'<span></span>' + esc( field.label ) + '</label></div>';

			case 'align':
				var opts2 = [ [ 'left', '⟨' ], [ 'center', '≡' ], [ 'right', '⟩' ] ].map( function ( o ) {
					var active = value === o[ 0 ] ? ' is-active' : '';
					return '<button type="button" class="wmd-seg' + active + '" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '" data-value="' + o[ 0 ] + '">' + o[ 1 ] + '</button>';
				} ).join( '' );
				body = '<div class="wmd-segs">' + opts2 + '</div>';
				break;

			case 'image':
				body = '<div class="wmd-image">' +
					( value ? '<img src="' + esc( value ) + '" alt="" />' : '<div class="wmd-image-empty">—</div>' ) +
					'<div class="wmd-image-actions">' +
					'<button type="button" class="wmd-mini" data-media="' + esc( scope ) + '|' + esc( key ) + '">' + esc( value ? i18n.change : i18n.pickImage ) + '</button>' +
					( value ? '<button type="button" class="wmd-mini" data-clear="' + esc( scope ) + '|' + esc( key ) + '">' + esc( i18n.remove ) + '</button>' : '' ) +
					'</div>' +
					'<input type="text" class="wmd-input wmd-small" ' + attrs + ' value="' + esc( value ) + '" placeholder="https://…" />' +
					'</div>';
				break;

			default:
				body = '<input type="text" class="wmd-input" ' + attrs + ' value="' + esc( value ) + '" />';
		}

		var hint = field.hint ? '<p class="wmd-hint">' + esc( field.hint ) + '</p>' : '';

		return '<div class="wmd-field">' + label + body + hint + '</div>';
	}

	/* ------------------------------------------------------- vasak paneel */

	function blockListHtml( zone, title, hint ) {
		var list = zoneList( zone );
		var items = list.map( function ( b, index ) {
			var def = cfg.blockTypes[ b.type ] || { label: b.type, icon: '?' };
			var active = state.selected && state.selected.id === b.id ? ' is-active' : '';
			return '<li class="wmd-item' + active + '" draggable="true" data-zone="' + esc( zone ) + '" data-id="' + esc( b.id ) + '" data-index="' + index + '">' +
				'<span class="wmd-item-icon">' + esc( def.icon ) + '</span>' +
				'<span class="wmd-item-label">' + esc( def.label ) + '<em>' + esc( blockSummary( b ) ) + '</em></span>' +
				'<span class="wmd-item-actions">' +
				'<button type="button" class="wmd-icon" data-move="' + esc( zone ) + '|' + esc( b.id ) + '|-1" title="Üles"' + ( 0 === index ? ' disabled' : '' ) + '>↑</button>' +
				'<button type="button" class="wmd-icon" data-move="' + esc( zone ) + '|' + esc( b.id ) + '|1" title="Alla"' + ( index === list.length - 1 ? ' disabled' : '' ) + '>↓</button>' +
				'<button type="button" class="wmd-icon" data-dup="' + esc( zone ) + '|' + esc( b.id ) + '" title="Kopeeri">⧉</button>' +
				'<button type="button" class="wmd-icon" data-del="' + esc( zone ) + '|' + esc( b.id ) + '" title="Kustuta">✕</button>' +
				'</span></li>';
		} ).join( '' );

		if ( ! items ) {
			items = '<li class="wmd-empty">' + esc( i18n.noBlocks ) + '</li>';
		}

		// WooCommerce'i osi (tellimuse tabel, aadressid, tellimuse väljad) pakume
		// ainult meili sisus — päises ja jaluses pole neil tellimust, mida näidata.
		var allowWoo = zone !== 'header' && zone !== 'footer';

		var palette = Object.keys( cfg.blockTypes ).filter( function ( type ) {
			return allowWoo || ! cfg.blockTypes[ type ].woo;
		} ).map( function ( type ) {
			var def = cfg.blockTypes[ type ];
			return '<button type="button" class="wmd-add" data-add="' + esc( zone ) + '|' + esc( type ) + '">' +
				'<span>' + esc( def.icon ) + '</span>' + esc( def.label ) + '</button>';
		} ).join( '' );

		return '<div class="wmd-section">' +
			( title ? '<h3 class="wmd-h3">' + esc( title ) + ( hint ? '<em>' + esc( hint ) + '</em>' : '' ) + '</h3>' : '' ) +
			'<ul class="wmd-list" data-zone="' + esc( zone ) + '">' + items + '</ul>' +
			'<div class="wmd-palette">' + palette + '</div>' +
			'</div>';
	}

	function blockSummary( b ) {
		var p = b.props || {};
		var text = p.text || p.label || p.html || p.left || p.code || p.alt || '';
		text = String( text ).replace( /<[^>]*>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
		if ( b.type === 'spacer' ) {
			text = p.height + ' px';
		}
		if ( b.type === 'image' ) {
			text = p.url ? p.url.split( '/' ).pop() : 'pilt puudub';
		}
		return text.length > 46 ? text.slice( 0, 46 ) + '…' : text;
	}

	function brandPanelHtml() {
		var groups = cfg.brandGroups;
		var schema = cfg.brandSchema;

		var html = '<div class="wmd-intro">Need seaded kehtivad <strong>kõigile</strong> WooCommerce\'i meilidele. Sea üks kord ja oledki valmis.</div>';

		Object.keys( groups ).forEach( function ( g ) {
			var fields = Object.keys( schema ).filter( function ( key ) {
				return schema[ key ].group === g;
			} );
			if ( ! fields.length ) {
				return;
			}
			var open = g === 'head' || g === 'colors' ? ' open' : '';
			html += '<details class="wmd-group"' + open + '><summary>' + esc( groups[ g ] ) + '</summary><div class="wmd-group-body">';
			fields.forEach( function ( key ) {
				html += fieldHtml( 'brand', key, schema[ key ], state.design.brand[ key ] );
			} );
			html += '</div></details>';
		} );

		return html;
	}

	function emailsPanelHtml() {
		var groups = cfg.emailGroups;
		var opts = '';

		Object.keys( groups ).forEach( function ( g ) {
			var inner = emailIds.filter( function ( id ) {
				return cfg.emails[ id ].group === g;
			} ).map( function ( id ) {
				var sel = id === state.email ? ' selected' : '';
				var mark = hasCustom( id ) ? ' •' : '';
				return '<option value="' + esc( id ) + '"' + sel + '>' + esc( cfg.emails[ id ].label ) + mark + '</option>';
			} ).join( '' );
			if ( inner ) {
				opts += '<optgroup label="' + esc( groups[ g ] ) + '">' + inner + '</optgroup>';
			}
		} );

		var e = emailSettings();
		var full = e.mode === 'full';

		var html = '<div class="wmd-intro">Vali meil ja täienda seda. Tühjaks jäetud väli tähendab, et kasutatakse WooCommerce\'i vaikeväärtust.</div>';
		html += '<div class="wmd-field"><label class="wmd-label" for="wmd-email-pick">Meil</label>' +
			'<select class="wmd-input" id="wmd-email-pick">' + opts + '</select></div>';

		html += '<div class="wmd-field"><label class="wmd-label" for="wmd-f-email-subject">Pealkiri postkastis</label>' +
			'<div class="wmd-inline"><input type="text" class="wmd-input" id="wmd-f-email-subject" data-scope="email" data-key="subject" value="' + esc( e.subject ) + '" placeholder="' + esc( wcDefault( state.email, 'subject' ) ) + '" />' +
			tagPicker( 'wmd-f-email-subject' ) + '</div></div>';

		html += '<div class="wmd-field"><label class="wmd-label" for="wmd-f-email-heading">Suur pealkiri meilis</label>' +
			'<div class="wmd-inline"><input type="text" class="wmd-input" id="wmd-f-email-heading" data-scope="email" data-key="heading" value="' + esc( e.heading ) + '" placeholder="' + esc( wcDefault( state.email, 'heading' ) ) + '" />' +
			tagPicker( 'wmd-f-email-heading' ) + '</div></div>';

		html += '<div class="wmd-field"><label class="wmd-label">Kuidas meil kokku pannakse</label>' +
			'<div class="wmd-segs wmd-modes">' +
			'<button type="button" class="wmd-seg' + ( full ? '' : ' is-active' ) + '" data-mode="wrap">WooCommerce\'i sisu ümber</button>' +
			'<button type="button" class="wmd-seg' + ( full ? ' is-active' : '' ) + '" data-mode="full">Terve meil ise</button>' +
			'</div></div>';

		if ( full ) {
			html += '<div class="wmd-intro wmd-warn">Selles režiimis ei kasutata WooCommerce\'i sisumalli. Kõik, mis meilis on, tuleb allolevatest plokkidest — ka tellimuse tabel ja aadressid.</div>';
			html += blockListHtml( 'body', 'Meili sisu', 'terve keha' );
		} else {
			html += blockListHtml( 'before', 'Sisu enne tellimuse tabelit', 'tervitus, info' );
			html += blockListHtml( 'after', 'Sisu pärast tellimuse tabelit', 'nupp, lisamüük' );
		}

		return html;
	}

	function hasCustom( id ) {
		var e = state.design.emails[ id ];
		if ( ! e ) {
			return false;
		}
		return !! ( e.subject || e.heading || e.mode === 'full' ||
			( e.before && e.before.length ) || ( e.after && e.after.length ) || ( e.body && e.body.length ) );
	}

	/**
	 * Vaikimisi keha, kui „terve meil ise" valitakse esimest korda — nii ei
	 * jää kasutaja tühja lehe ette ja tellimuse tabel ei kao kogemata ära.
	 */
	function seedBody() {
		return [
			makeBlock( 'text' ),
			makeBlock( 'order_table' ),
			makeBlock( 'addresses' ),
		];
	}

	/* ---------------------------------------------------- makseviisid */

	function paymentsPanelHtml() {
		var gateways = cfg.gateways || {};
		var keys = Object.keys( gateways );

		if ( ! keys.length ) {
			return '<div class="wmd-intro">Poes ei leitud ühtegi makseviisi.</div>';
		}

		if ( ! state.design.payments ) {
			state.design.payments = {};
		}

		var html = '<div class="wmd-intro">Kirjuta iga makseviisi juhised üks kord siia. Kirja toob need plokk <strong>„Makseviisi juhised (oma tekst)"</strong> — see näitab alati selle tellimuse makseviisi teksti. Tühjaks jäetud makseviisi puhul plokk lihtsalt ei ilmu.</div>';

		keys.forEach( function ( id ) {
			var value = state.design.payments[ id ] || '';
			var fid = 'wmd-pay-' + id;

			html += '<div class="wmd-field">' +
				'<label class="wmd-label" for="' + esc( fid ) + '">' + esc( gateways[ id ] ) + ' <code>' + esc( id ) + '</code></label>' +
				'<div class="wmd-rich">' +
				'<div class="wmd-rich-bar">' +
				'<button type="button" data-wrap="strong" title="Rasvane"><b>B</b></button>' +
				'<button type="button" data-wrap="em" title="Kaldkiri"><i>I</i></button>' +
				'<button type="button" data-wrap="br" title="Reavahetus">↵</button>' +
				'<button type="button" data-wrap="a" title="Link">🔗</button>' +
				tagPicker( fid ) +
				'</div>' +
				'<textarea class="wmd-input wmd-textarea" rows="5" id="' + esc( fid ) + '" data-scope="payment" data-key="' + esc( id ) + '" ' +
				'placeholder="Nt: Palun tee ülekanne oma pangast otse meie kontole…">' + esc( value ) + '</textarea>' +
				'</div></div>';
		} );

		return html;
	}

	/* ------------------------------------------------------ uuendused */

	function updatesPanelHtml() {
		if ( ! cfg.canUpdate ) {
			return '<div class="wmd-intro">Uuenduste seadistamiseks on vaja õigust pluginaid uuendada.</div>';
		}

		var u = state.updates;
		var isGithub = u.source === 'github';
		var isJson = u.source === 'json';

		var status;
		if ( u.source === 'off' ) {
			status = '<span class="wmd-status">Automaatsed uuendused on välja lülitatud.</span>';
		} else if ( ! u.remote ) {
			status = '<span class="wmd-status is-warn">Allikast ei saanud versiooni kätte. Kontrolli hoidla nime, väljalaset ja võtit.</span>';
		} else if ( u.remote === u.current ) {
			status = '<span class="wmd-status is-ok">Kõik on värske — paigaldatud ' + esc( u.current ) + ', allikas ' + esc( u.remote ) + '.</span>';
		} else {
			status = '<span class="wmd-status is-new">Saadaval on <strong>' + esc( u.remote ) + '</strong> (paigaldatud ' + esc( u.current ) + ').</span>';
		}

		var canInstall = u.remote && u.remote !== u.current;

		var html = '<div class="wmd-intro">Plugin ei ole WordPress.org-is, seega uuendused tulevad otse sinu GitHubi väljalasetest. WordPress näitab uuendusteadet tavalisel Pluginad-lehel.</div>';

		html += '<div class="wmd-field"><label class="wmd-label" for="wmd-u-source">Uuenduste allikas</label>' +
			'<select class="wmd-input" id="wmd-u-source">' +
			'<option value="off"' + ( u.source === 'off' ? ' selected' : '' ) + '>Väljas</option>' +
			'<option value="github"' + ( isGithub ? ' selected' : '' ) + '>GitHubi väljalase</option>' +
			'<option value="json"' + ( isJson ? ' selected' : '' ) + '>Oma JSON-manifest</option>' +
			'</select></div>';

		html += '<div class="wmd-field" data-when="github"' + ( isGithub ? '' : ' hidden' ) + '>' +
			'<label class="wmd-label" for="wmd-u-repo">GitHubi hoidla</label>' +
			'<input type="text" class="wmd-input" id="wmd-u-repo" value="' + esc( u.repo ) + '" placeholder="kasutaja/hoidla" /></div>';

		html += '<div class="wmd-field" data-when="github"' + ( isGithub ? '' : ' hidden' ) + '>' +
			'<label class="wmd-label" for="wmd-u-token">Juurdepääsuvõti</label>' +
			'<input type="password" class="wmd-input" id="wmd-u-token" value="' + esc( u.token ) + '" placeholder="ainult privaatse hoidla puhul" autocomplete="off" /></div>';

		html += '<div class="wmd-field" data-when="json"' + ( isJson ? '' : ' hidden' ) + '>' +
			'<label class="wmd-label" for="wmd-u-json">Manifesti aadress</label>' +
			'<input type="text" class="wmd-input" id="wmd-u-json" value="' + esc( u.json ) + '" placeholder="https://…/update.json" /></div>';

		html += '<div class="wmd-updates-actions">' +
			'<button type="button" class="button button-primary wmd-u-save">Salvesta allikas</button> ' +
			'<button type="button" class="button wmd-u-check">Kontrolli kohe</button>' +
			'</div>';

		html += '<div class="wmd-update-status">' + status + '</div>';

		if ( canInstall ) {
			html += '<div class="wmd-updates-actions">' +
				'<button type="button" class="button button-primary wmd-u-install">Uuenda kohe versioonile ' + esc( u.remote ) + '</button>' +
				'</div>' +
				'<p class="wmd-hint">Paigaldab uue versiooni siinsamas. Leht laaditakse pärast uuesti; salvestamata muudatused salvesta enne ära.</p>';
		}

		if ( state.updateLog && state.updateLog.length ) {
			html += '<pre class="wmd-log">' + esc( state.updateLog.join( '\n' ) ) + '</pre>';
		}

		return html;
	}

	function bindUpdates() {
		var source = root.querySelector( '#wmd-u-source' );
		if ( ! source ) {
			return;
		}

		source.addEventListener( 'change', function () {
			root.querySelectorAll( '[data-when]' ).forEach( function ( field ) {
				field.hidden = field.getAttribute( 'data-when' ) !== source.value;
			} );
		} );

		function payload() {
			return {
				source: source.value,
				repo: ( root.querySelector( '#wmd-u-repo' ) || {} ).value || '',
				json: ( root.querySelector( '#wmd-u-json' ) || {} ).value || '',
				token: ( root.querySelector( '#wmd-u-token' ) || {} ).value || '',
			};
		}

		var save = root.querySelector( '.wmd-u-save' );
		save.addEventListener( 'click', function () {
			save.disabled = true;
			post( 'wmd_save_updates', payload() ).then( function ( res ) {
				state.updates = res.updates;
				render();
				toast( i18n.saved, 'ok' );
			} ).catch( function ( err ) {
				toast( err, 'error' );
			} ).then( function () {
				save.disabled = false;
			} );
		} );

		var install = root.querySelector( '.wmd-u-install' );
		if ( install ) {
			install.addEventListener( 'click', function () {
				if ( state.dirty && ! window.confirm( 'Sul on salvestamata muudatusi. Uuendamine laadib lehe uuesti ja need lähevad kaotsi. Jätkan?' ) ) {
					return;
				}

				install.disabled = true;
				install.textContent = 'Paigaldan…';

				post( 'wmd_update_now', {} ).then( function ( res ) {
					state.dirty = false;
					state.updateLog = res.log || [];

					if ( res.updated ) {
						toast( res.message + ' Laen lehe uuesti…', 'ok' );
						setTimeout( function () {
							window.location.reload();
						}, 1200 );
						return;
					}

					toast( res.message, 'ok' );
					render();
				} ).catch( function ( err ) {
					state.updateLog = [];
					toast( err, 'error' );
					install.disabled = false;
					install.textContent = 'Proovi uuesti';
				} );
			} );
		}

		var check = root.querySelector( '.wmd-u-check' );
		check.addEventListener( 'click', function () {
			check.disabled = true;
			check.textContent = 'Kontrollin…';
			post( 'wmd_save_updates', payload() ).then( function () {
				return post( 'wmd_check_update', {} );
			} ).then( function ( res ) {
				state.updates.current = res.current;
				state.updates.remote = res.remote;
				state.updates.source = source.value;
				render();
				toast( res.remote ? ( res.newer ? 'Uuendus ' + res.remote + ' on saadaval' : 'Kõik on värske' ) : 'Allikast ei saanud vastust', res.remote ? 'ok' : 'error' );
			} ).catch( function ( err ) {
				toast( err, 'error' );
			} ).then( function () {
				check.disabled = false;
				check.textContent = 'Kontrolli kohe';
			} );
		} );
	}

	/* ------------------------------------------------------ parem paneel */

	function inspectorHtml() {
		var block = findBlock( state.selected );

		if ( ! block ) {
			return '<div class="wmd-inspector-empty">' +
				'<h3>Midagi pole valitud</h3>' +
				'<p>Klõpsa eelvaates mõnel plokil või vali see vasakust nimekirjast, et selle seaded siia ilmuksid.</p>' +
				'</div>';
		}

		var def = cfg.blockTypes[ block.type ];
		var html = '<div class="wmd-inspector-head"><span class="wmd-item-icon">' + esc( def.icon ) + '</span>' + esc( def.label ) + '</div>';

		Object.keys( def.fields ).forEach( function ( key ) {
			html += fieldHtml( 'block', key, def.fields[ key ], block.props[ key ] );
		} );

		var gateways = cfg.gateways || {};
		var gwKeys = Object.keys( gateways );

		if ( gwKeys.length ) {
			var chosen = blockCond( block ).pay;

			html += '<details class="wmd-group wmd-cond"' + ( chosen.length ? ' open' : '' ) + '>' +
				'<summary>Nähtavus' + ( chosen.length ? ' · ' + chosen.length : '' ) + '</summary>' +
				'<div class="wmd-group-body">' +
				'<p class="wmd-hint">Märkimata = näita alati. Märgi need makseviisid, mille puhul plokk kirja läheb — nii saab nt pangaülekande juhised panna ainult ülekandega tellimustele.</p>' +
				gwKeys.map( function ( id ) {
					var on = chosen.indexOf( id ) !== -1;
					return '<label class="wmd-check"><input type="checkbox" data-pay="' + esc( id ) + '"' + ( on ? ' checked' : '' ) + ' /> ' +
						esc( gateways[ id ] ) + ' <code>' + esc( id ) + '</code></label>';
				} ).join( '' ) +
				'</div></details>';
		}

		html += '<div class="wmd-inspector-foot">' +
			'<button type="button" class="button" data-dup="' + esc( state.selected.zone ) + '|' + esc( block.id ) + '">Kopeeri plokk</button> ' +
			'<button type="button" class="button button-link-delete" data-del="' + esc( state.selected.zone ) + '|' + esc( block.id ) + '">Kustuta</button>' +
			'</div>';

		return html;
	}

	/* ----------------------------------------------------------- raamistik */

	function render() {
		var tabs = [
			[ 'brand', 'Bränd' ],
			[ 'header', 'Päis' ],
			[ 'footer', 'Jalus' ],
			[ 'emails', 'Meilid' ],
			[ 'payments', 'Makseviisid' ],
			[ 'updates', 'Uuendused' ],
		].map( function ( t ) {
			return '<button type="button" class="wmd-tab' + ( state.tab === t[ 0 ] ? ' is-active' : '' ) + '" data-tab="' + t[ 0 ] + '">' + t[ 1 ] + '</button>';
		} ).join( '' );

		var panel = '';
		if ( state.tab === 'brand' ) {
			panel = brandPanelHtml();
		} else if ( state.tab === 'header' ) {
			panel = '<div class="wmd-intro">Päis on kõigi meilide ülaosas ühesugune.</div>' + blockListHtml( 'header', '', '' );
		} else if ( state.tab === 'footer' ) {
			panel = '<div class="wmd-intro">Jalus on kõigi meilide all ühesugune.</div>' + blockListHtml( 'footer', '', '' );
		} else if ( state.tab === 'payments' ) {
			panel = paymentsPanelHtml();
		} else if ( state.tab === 'updates' ) {
			panel = updatesPanelHtml();
		} else {
			panel = emailsPanelHtml();
		}

		var previewOpts = emailIds.map( function ( id ) {
			return '<option value="' + esc( id ) + '"' + ( id === state.email ? ' selected' : '' ) + '>' + esc( cfg.emails[ id ].label ) + '</option>';
		} ).join( '' );

		var orders = cfg.orders || [];
		var orderPick = '';

		if ( orders.length ) {
			orderPick = '<select class="wmd-input wmd-order-pick" title="Millise tellimuse andmetega eelvaadet täita">' +
				'<option value="0"' + ( state.order ? '' : ' selected' ) + '>Poe viimane tellimus</option>' +
				orders.map( function ( o ) {
					return '<option value="' + esc( o.id ) + '"' + ( String( o.id ) === String( state.order ) ? ' selected' : '' ) + '>' + esc( o.label ) + '</option>';
				} ).join( '' ) +
				'</select>';
		}

		root.innerHTML = '' +
			'<div class="wmd-bar">' +
			'<div class="wmd-bar-left"><span class="wmd-logo">Meilidisainer</span>' +
			'<span class="wmd-dirty" ' + ( state.dirty ? '' : 'hidden' ) + '>' + esc( i18n.unsaved ) + '</span></div>' +
			'<div class="wmd-bar-mid">' +
			'<select class="wmd-input wmd-preview-pick" title="Mida eelvaates näidata">' + previewOpts + '</select>' +
			orderPick +
			'<div class="wmd-segs wmd-device">' +
			'<button type="button" class="wmd-seg' + ( state.device === 'desktop' ? ' is-active' : '' ) + '" data-device="desktop">Arvuti</button>' +
			'<button type="button" class="wmd-seg' + ( state.device === 'mobile' ? ' is-active' : '' ) + '" data-device="mobile">Mobiil</button>' +
			'</div></div>' +
			'<div class="wmd-bar-right">' +
			'<label class="wmd-switch wmd-switch-inline" title="Kas kujundus rakendub päris meilidele"><input type="checkbox" class="wmd-enabled"' + ( state.enabled ? ' checked' : '' ) + ' /><span></span>Kujundus sees</label>' +
			'<button type="button" class="button wmd-refresh" title="Küsi selle tellimuse andmed serverist uuesti">Värskenda serverist</button>' +
			'<button type="button" class="button wmd-test">Saada testmeil</button>' +
			'<button type="button" class="button button-primary wmd-save">Salvesta</button>' +
			'<button type="button" class="button-link wmd-reset" title="Lähtesta kujundus">Lähtesta</button>' +
			'</div></div>' +
			'<div class="wmd-body">' +
			'<aside class="wmd-left"><div class="wmd-tabs">' + tabs + '</div><div class="wmd-panel">' + panel + '</div></aside>' +
			'<main class="wmd-canvas' + ( state.device === 'mobile' ? ' is-mobile' : '' ) + '">' +
			wcNoteHtml() +
			'<div class="wmd-frame-wrap"><iframe class="wmd-frame" title="Meili eelvaade"></iframe></div></main>' +
			'<aside class="wmd-right">' + inspectorHtml() + '</aside>' +
			'</div>' +
			'<div class="wmd-toast"></div>';

		bind();
		updatePreview();
	}

	/* ------------------------------------------------------------- seosed */

	function setValue( scope, key, value ) {
		if ( scope === 'brand' ) {
			state.design.brand[ key ] = value;
		} else if ( scope === 'payment' ) {
			if ( ! state.design.payments ) {
				state.design.payments = {};
			}
			state.design.payments[ key ] = value;
		} else if ( scope === 'email' ) {
			if ( ! state.design.emails[ state.email ] ) {
				state.design.emails[ state.email ] = { subject: '', heading: '', before: [], after: [] };
			}
			state.design.emails[ state.email ][ key ] = value;
		} else {
			var block = findBlock( state.selected );
			if ( block ) {
				block.props[ key ] = value;
			}
		}
		markDirty();
		schedulePreview();
	}

	/**
	 * Struktuurimuudatuse järel piisab tavalisest värskendusest — WooCommerce'i
	 * osa on juba mälus ja ülejäänu joonistab render().
	 */
	function invalidatePreview() {
		schedulePreview();
	}

	function bind() {
		// Vahekaardid.
		root.querySelectorAll( '[data-tab]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				state.tab = btn.getAttribute( 'data-tab' );
				render();
			} );
		} );

		// Eelvaate meilivalik.
		// render() kutsub ensureWcPart(), mis toob uue meili WooCommerce'i sisu
		// serverist, kui seda veel mälus pole.
		function switchEmail( id ) {
			state.email = id;
			state.selected = null;
			render();
		}

		var pick = root.querySelector( '.wmd-preview-pick' );
		if ( pick ) {
			pick.addEventListener( 'change', function () {
				switchEmail( pick.value );
			} );
		}

		var emailPick = root.querySelector( '#wmd-email-pick' );
		if ( emailPick ) {
			emailPick.addEventListener( 'change', function () {
				switchEmail( emailPick.value );
			} );
		}

		var orderPick = root.querySelector( '.wmd-order-pick' );
		if ( orderPick ) {
			orderPick.addEventListener( 'change', function () {
				// Tellimuse vahetus toobki serverist selle tellimuse andmed:
				// WooCommerce'i sisu, tooteread, kokkuvõtte ja väljade nimekirja.
				state.order = parseInt( orderPick.value, 10 ) || 0;
				state.selected = null;
				render();
			} );
		}

		// Seade.
		root.querySelectorAll( '[data-device]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				state.device = btn.getAttribute( 'data-device' );
				render();
			} );
		} );

		// Väljad.
		// Veergude nimekirjal on oma sidumine — muidu püüaks üldine sidumine
		// tema sees toimuva kinni ja kirjutaks väärtuse üle.
		root.querySelectorAll( '.wmd-panel [data-scope]:not(.wmd-cols), .wmd-right [data-scope]:not(.wmd-cols)' ).forEach( function ( input ) {
			var scope = input.getAttribute( 'data-scope' );
			var key = input.getAttribute( 'data-key' );

			if ( input.type === 'checkbox' ) {
				input.addEventListener( 'change', function () {
					setValue( scope, key, input.checked ? 1 : 0 );
				} );
				return;
			}

			if ( input.type === 'range' ) {
				input.addEventListener( 'input', function () {
					var out = input.parentNode.querySelector( 'output' );
					if ( out ) {
						out.textContent = input.value;
					}
					setValue( scope, key, parseInt( input.value, 10 ) );
				} );
				return;
			}

			if ( input.classList.contains( 'wmd-swatch' ) ) {
				input.addEventListener( 'input', function () {
					var hex = input.parentNode.querySelector( '.wmd-hex' );
					if ( hex ) {
						hex.value = input.value;
					}
					setValue( scope, key, input.value );
				} );
				return;
			}

			if ( input.tagName === 'SELECT' ) {
				input.addEventListener( 'change', function () {
					setValue( scope, key, input.value );
				} );
				return;
			}

			input.addEventListener( 'input', function () {
				setValue( scope, key, input.value );
			} );
		} );

		// Veergude toimeti.
		root.querySelectorAll( '.wmd-cols' ).forEach( function ( list ) {
			var scope = list.getAttribute( 'data-scope' );
			var key = list.getAttribute( 'data-key' );

			function cols() {
				var block = findBlock( state.selected );
				return block ? block.props[ key ] : null;
			}

			function commit( rerender ) {
				markDirty();
				schedulePreview();
				if ( rerender ) {
					render();
				}
			}

			list.querySelectorAll( '[data-col-on]' ).forEach( function ( box ) {
				box.addEventListener( 'change', function () {
					var c = cols();
					if ( c ) {
						c[ parseInt( box.getAttribute( 'data-col-on' ), 10 ) ].on = box.checked ? 1 : 0;
						commit( false );
					}
				} );
			} );

			list.querySelectorAll( '[data-col-label]' ).forEach( function ( input ) {
				input.addEventListener( 'input', function () {
					var c = cols();
					if ( c ) {
						c[ parseInt( input.getAttribute( 'data-col-label' ), 10 ) ].label = input.value;
						commit( false );
					}
				} );
			} );

			function move( index, delta ) {
				var c = cols();
				var to = index + delta;

				if ( ! c || to < 0 || to >= c.length ) {
					return;
				}

				var moved = c.splice( index, 1 )[ 0 ];
				c.splice( to, 0, moved );
				commit( true );
			}

			list.querySelectorAll( '[data-col-up]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					move( parseInt( btn.getAttribute( 'data-col-up' ), 10 ), -1 );
				} );
			} );

			list.querySelectorAll( '[data-col-down]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					move( parseInt( btn.getAttribute( 'data-col-down' ), 10 ), 1 );
				} );
			} );
		} );

		// Ploki nähtavustingimus makseviisi järgi.
		root.querySelectorAll( '.wmd-cond [data-pay]' ).forEach( function ( box ) {
			box.addEventListener( 'change', function () {
				var block = findBlock( state.selected );
				if ( ! block ) {
					return;
				}

				var id = box.getAttribute( 'data-pay' );
				var pay = blockCond( block ).pay;
				var at = pay.indexOf( id );

				if ( box.checked && at === -1 ) {
					pay.push( id );
				} else if ( ! box.checked && at !== -1 ) {
					pay.splice( at, 1 );
				}

				markDirty();
				schedulePreview();
			} );
		} );

		// Meili kokkupaneku režiim.
		root.querySelectorAll( '.wmd-modes [data-mode]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var mode = btn.getAttribute( 'data-mode' );
				var e = emailSettings();

				if ( e.mode === mode ) {
					return;
				}

				e.mode = mode;

				if ( mode === 'full' && ! e.body.length ) {
					e.body = seedBody();
				}

				state.selected = null;
				markDirty();
				render();
				invalidatePreview();
			} );
		} );

		// Joondusnupud.
		root.querySelectorAll( '.wmd-segs [data-value]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var group = btn.parentNode;
				group.querySelectorAll( '.wmd-seg' ).forEach( function ( b ) {
					b.classList.remove( 'is-active' );
				} );
				btn.classList.add( 'is-active' );
				setValue( btn.getAttribute( 'data-scope' ), btn.getAttribute( 'data-key' ), btn.getAttribute( 'data-value' ) );
			} );
		} );

		// Tühjenda väärtus (värv brändist / pilt maha).
		root.querySelectorAll( '[data-clear]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var parts = btn.getAttribute( 'data-clear' ).split( '|' );
				setValue( parts[ 0 ], parts[ 1 ], '' );
				render();
			} );
		} );

		// Meediateek.
		root.querySelectorAll( '[data-media]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var parts = btn.getAttribute( 'data-media' ).split( '|' );
				openMedia( parts[ 0 ], parts[ 1 ] );
			} );
		} );

		// Plokid: lisa, kopeeri, kustuta, vali.
		root.querySelectorAll( '[data-add]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var parts = btn.getAttribute( 'data-add' ).split( '|' );
				var block = makeBlock( parts[ 1 ] );
				zoneList( parts[ 0 ] ).push( block );
				state.selected = { zone: parts[ 0 ], id: block.id };
				markDirty();
				render();
				invalidatePreview();
			} );
		} );

		// Nooled plokinimekirjas — lohistamise kõrvale kindel viis järjestada.
		root.querySelectorAll( '[data-move]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.stopPropagation();

				var parts = btn.getAttribute( 'data-move' ).split( '|' );
				var list = zoneList( parts[ 0 ] );
				var index = blockIndex( parts[ 0 ], parts[ 1 ] );
				var to = index + parseInt( parts[ 2 ], 10 );

				if ( index === -1 || to < 0 || to >= list.length ) {
					return;
				}

				list.splice( to, 0, list.splice( index, 1 )[ 0 ] );
				state.selected = { zone: parts[ 0 ], id: parts[ 1 ] };
				markDirty();
				render();
				invalidatePreview();
			} );
		} );

		root.querySelectorAll( '[data-dup]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.stopPropagation();
				var parts = btn.getAttribute( 'data-dup' ).split( '|' );
				var list = zoneList( parts[ 0 ] );
				var index = blockIndex( parts[ 0 ], parts[ 1 ] );
				if ( index === -1 ) {
					return;
				}
				var copy = JSON.parse( JSON.stringify( list[ index ] ) );
				copy.id = newId();
				list.splice( index + 1, 0, copy );
				state.selected = { zone: parts[ 0 ], id: copy.id };
				markDirty();
				render();
				invalidatePreview();
			} );
		} );

		root.querySelectorAll( '[data-del]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.stopPropagation();
				var parts = btn.getAttribute( 'data-del' ).split( '|' );
				var index = blockIndex( parts[ 0 ], parts[ 1 ] );
				if ( index === -1 || ! window.confirm( i18n.confirmDelete ) ) {
					return;
				}
				zoneList( parts[ 0 ] ).splice( index, 1 );
				state.selected = null;
				markDirty();
				render();
				invalidatePreview();
			} );
		} );

		root.querySelectorAll( '.wmd-item' ).forEach( function ( item ) {
			item.addEventListener( 'click', function () {
				selectBlock( item.getAttribute( 'data-zone' ), item.getAttribute( 'data-id' ) );
			} );
		} );

		bindDrag();
		bindRich();
		bindTags();
		bindUpdates();
		bindBar();
	}

	function bindDrag() {
		var dragged = null;

		/**
		 * Kirjutab plokkide järjekorra ümber selle järgi, mis nimekirjas näha on.
		 *
		 * NB: seda kutsutakse dragend-sündmusel, mitte drop-il. Drop käivitub
		 * ainult siis, kui kukutamiskoht on dragover-is lubatud; kui kasutaja
		 * laseb hiire lahti nimekirja serval või väljaspool, jääks drop tulemata
		 * ja nimekiri näeks ümber järjestatud välja, aga andmetes poleks midagi
		 * muutunud. Dragend käivitub alati.
		 *
		 * @param {Element} list Plokinimekiri.
		 */
		function commitOrder( list ) {
			var zone = list.getAttribute( 'data-zone' );
			var current = zoneList( zone );
			var order = Array.prototype.map.call( list.querySelectorAll( '.wmd-item' ), function ( n ) {
				return n.getAttribute( 'data-id' );
			} );

			var sorted = order.map( function ( id ) {
				return current.filter( function ( b ) {
					return b.id === id;
				} )[ 0 ];
			} ).filter( Boolean );

			if ( sorted.length !== current.length ) {
				return;
			}

			var changed = sorted.some( function ( b, i ) {
				return b !== current[ i ];
			} );

			if ( ! changed ) {
				return;
			}

			current.length = 0;
			sorted.forEach( function ( b ) {
				current.push( b );
			} );

			markDirty();
			render();
			invalidatePreview();
		}

		root.querySelectorAll( '.wmd-list' ).forEach( function ( list ) {
			// Ilma selleta ei lubata kukutamist nimekirja servadel ja drop jääks olemata.
			list.addEventListener( 'dragover', function ( ev ) {
				if ( dragged && dragged.parentNode === list ) {
					ev.preventDefault();
					ev.dataTransfer.dropEffect = 'move';
				}
			} );

			list.addEventListener( 'drop', function ( ev ) {
				ev.preventDefault();
			} );

			list.querySelectorAll( '.wmd-item' ).forEach( function ( item ) {
				item.addEventListener( 'dragstart', function ( ev ) {
					dragged = item;
					item.classList.add( 'is-dragging' );
					ev.dataTransfer.effectAllowed = 'move';
					ev.dataTransfer.setData( 'text/plain', item.getAttribute( 'data-id' ) );
				} );

				item.addEventListener( 'dragend', function () {
					item.classList.remove( 'is-dragging' );
					dragged = null;
					commitOrder( list );
				} );

				item.addEventListener( 'dragover', function ( ev ) {
					if ( ! dragged || dragged === item || dragged.parentNode !== item.parentNode ) {
						return;
					}

					ev.preventDefault();

					var rect = item.getBoundingClientRect();
					var after = ( ev.clientY - rect.top ) > rect.height / 2;

					item.parentNode.insertBefore( dragged, after ? item.nextSibling : item );
				} );
			} );
		} );
	}

	function bindRich() {
		root.querySelectorAll( '.wmd-rich-bar [data-wrap]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var area = btn.closest( '.wmd-rich' ).querySelector( 'textarea' );
				var tag = btn.getAttribute( 'data-wrap' );
				var start = area.selectionStart;
				var end = area.selectionEnd;
				var sel = area.value.slice( start, end );
				var insert;

				if ( tag === 'br' ) {
					insert = '<br>';
				} else if ( tag === 'a' ) {
					var url = window.prompt( 'Lingi aadress', 'https://' );
					if ( ! url ) {
						return;
					}
					insert = '<a href="' + url + '">' + ( sel || 'link' ) + '</a>';
				} else {
					insert = '<' + tag + '>' + ( sel || 'tekst' ) + '</' + tag + '>';
				}

				area.value = area.value.slice( 0, start ) + insert + area.value.slice( end );
				area.focus();
				area.selectionStart = area.selectionEnd = start + insert.length;
				setValue( area.getAttribute( 'data-scope' ), area.getAttribute( 'data-key' ), area.value );
			} );
		} );
	}

	function bindTags() {
		root.querySelectorAll( '.wmd-tags' ).forEach( function ( box ) {
			var toggle = box.querySelector( '.wmd-tags-toggle' );
			var menu = box.querySelector( '.wmd-tags-menu' );

			toggle.addEventListener( 'click', function () {
				root.querySelectorAll( '.wmd-tags-menu' ).forEach( function ( m ) {
					if ( m !== menu ) {
						m.hidden = true;
					}
				} );
				menu.hidden = ! menu.hidden;
			} );

			menu.querySelectorAll( '[data-tag]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var target = document.getElementById( box.getAttribute( 'data-for' ) );
					if ( ! target ) {
						return;
					}
					var token = '{{' + btn.getAttribute( 'data-tag' ) + '}}';
					var start = target.selectionStart || 0;
					var end = target.selectionEnd || 0;
					target.value = target.value.slice( 0, start ) + token + target.value.slice( end );
					target.focus();
					target.selectionStart = target.selectionEnd = start + token.length;
					menu.hidden = true;
					setValue( target.getAttribute( 'data-scope' ), target.getAttribute( 'data-key' ), target.value );
				} );
			} );
		} );

		document.addEventListener( 'click', function ( ev ) {
			if ( ! ev.target.closest || ! ev.target.closest( '.wmd-tags' ) ) {
				root.querySelectorAll( '.wmd-tags-menu' ).forEach( function ( m ) {
					m.hidden = true;
				} );
			}
		} );
	}

	function bindBar() {
		var save = root.querySelector( '.wmd-save' );
		if ( save ) {
			save.addEventListener( 'click', doSave );
		}

		var reset = root.querySelector( '.wmd-reset' );
		if ( reset ) {
			reset.addEventListener( 'click', function () {
				if ( ! window.confirm( i18n.confirmReset ) ) {
					return;
				}
				post( 'wmd_reset', {} ).then( function ( res ) {
					state.design = normalise( res.design );
					state.selected = null;
					state.dirty = false;
					wcCache = {};
					render();
					toast( i18n.saved, 'ok' );
				} );
			} );
		}

		var enabled = root.querySelector( '.wmd-enabled' );
		if ( enabled ) {
			enabled.addEventListener( 'change', function () {
				state.enabled = enabled.checked;
				post( 'wmd_toggle', { on: enabled.checked ? 1 : 0 } ).then( function () {
					toast( enabled.checked ? 'Kujundus rakendub meilidele' : 'Kujundus on välja lülitatud', 'ok' );
				} );
			} );
		}

		var refresh = root.querySelector( '.wmd-refresh' );
		if ( refresh ) {
			refresh.addEventListener( 'click', function () {
				delete wcCache[ wcKey() ];
				refresh.disabled = true;
				refresh.textContent = 'Küsin…';
				ensureWcPart();

				setTimeout( function () {
					refresh.disabled = false;
					refresh.textContent = 'Värskenda serverist';
					render();
				}, 900 );
			} );
		}

		var test = root.querySelector( '.wmd-test' );
		if ( test ) {
			test.addEventListener( 'click', function () {
				var to = window.prompt( 'Kuhu testmeil saata?', cfg.testTo || '' );
				if ( ! to ) {
					return;
				}
				test.disabled = true;
				test.textContent = i18n.sending;
				post( 'wmd_test_email', {
					to: to,
					email: state.email,
					design: JSON.stringify( state.design ),
				} ).then( function ( res ) {
					state.dirty = false;
					toast( i18n.sent + ' → ' + res.to, 'ok' );
				} ).catch( function ( err ) {
					toast( err, 'error' );
				} ).then( function () {
					test.disabled = false;
					test.textContent = 'Saada testmeil';
				} );
			} );
		}
	}

	function doSave() {
		var btn = root.querySelector( '.wmd-save' );
		if ( btn ) {
			btn.disabled = true;
		}

		return post( 'wmd_save', { design: JSON.stringify( state.design ) } ).then( function ( res ) {
			state.design = normalise( res.design );
			state.dirty = false;
			render();
			toast( i18n.saved, 'ok' );
		} ).catch( function ( err ) {
			toast( err || i18n.saveFailed, 'error' );
		} ).then( function () {
			if ( btn ) {
				btn.disabled = false;
			}
		} );
	}

	function openMedia( scope, key ) {
		if ( ! window.wp || ! window.wp.media ) {
			var url = window.prompt( 'Pildi aadress', '' );
			if ( url ) {
				setValue( scope, key, url );
				render();
			}
			return;
		}

		var frame = window.wp.media( {
			title: i18n.pickImage,
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			setValue( scope, key, att.url );
			render();
		} );

		frame.open();
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} ).then( function ( r ) {
			return r.json();
		} ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw ( json && json.data && json.data.message ) || i18n.saveFailed;
			}
			return json.data;
		} );
	}

	// Hoiatus salvestamata muudatuste eest.
	window.addEventListener( 'beforeunload', function ( ev ) {
		if ( state.dirty ) {
			ev.preventDefault();
			ev.returnValue = '';
		}
	} );

	// Ctrl/Cmd + S salvestab.
	document.addEventListener( 'keydown', function ( ev ) {
		if ( ( ev.ctrlKey || ev.metaKey ) && ev.key === 's' ) {
			ev.preventDefault();
			doSave();
		}
	} );

	render();
}() );
