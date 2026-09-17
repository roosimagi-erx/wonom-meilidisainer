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

	// Tõlked tulevad WordPressilt (wp_set_script_translations). Kui wp.i18n
	// puudub — nt eraldiseisvas demos —, jääb alles lähtetekst inglise keeles.
	var __ = ( window.wp && window.wp.i18n && window.wp.i18n.__ ) || function ( text ) {
		return text;
	};

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
		varQuery: '',
		// Tootekategooriad pildiploki jaoks: laetakse ühe korra ja jäävad
		// nimekirja lahti ka pärast valikut, et neli pilti saaks järjest valida.
		cats: null,
		catsOpen: false,
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
			e = { mode: 'wrap', subject: '', heading: '', before: [], after: [], body: [], additional: 0 };
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

	/**
	 * Plokkide vaikeväärtused tüübi kaupa, renderdaja jaoks.
	 *
	 * Vanas kujunduses salvestatud plokil võivad uued väljad puududa — ilma
	 * vaikeväärtuseta jõuaks kanvasele „undefined".
	 *
	 * @return {Object} type => props.
	 */
	var defaultsCache = null;

	function blockDefaults() {
		if ( defaultsCache ) {
			return defaultsCache;
		}

		defaultsCache = {};

		Object.keys( cfg.blockTypes || {} ).forEach( function ( type ) {
			var props = {};

			Object.keys( cfg.blockTypes[ type ].fields ).forEach( function ( key ) {
				props[ key ] = cfg.blockTypes[ type ].fields[ key ].default;
			} );

			defaultsCache[ type ] = props;
		} );

		return defaultsCache;
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
	/**
	 * Kas see meil käib tellimuse pealt.
	 *
	 * Kontomeilidel (uus konto, parooli lähtestamine) ei ole tellimust: neil ei
	 * tohi tellimuse valik midagi muuta ja seepärast on valik ka peidus.
	 *
	 * @param {string} id Meili id; vaikimisi praegu avatud meil.
	 * @return {boolean}
	 */
	function usesOrder( id ) {
		var meta = cfg.emails[ id || state.email ];

		return ! meta || false !== meta.order;
	}

	/**
	 * Tellimuse id serveri päringutesse ja vahemälu võtmesse.
	 *
	 * Kontomeilidel on see alati 0 — nii ei tekita tellimuse vahetamine neile
	 * uut vahemälukirjet ega uut päringut.
	 *
	 * @return {number}
	 */
	function orderParam() {
		return usesOrder() ? ( state.order || 0 ) : 0;
	}

	/**
	 * Tellimus, mille pealt eelvaadet täidetakse. Kontomeilidel ei ole seda.
	 */
	function currentOrder() {
		var list = cfg.orders || [];

		if ( ! list.length || ! usesOrder() ) {
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
				__addr: ( wc && wc.addr ) || null,
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

	/**
	 * Vahemälu võti. Sisaldab kõike, mis serveri vastust mõjutab — nii ei saa
	 * juhtuda, et seade muutub, aga kanvasele jääb vana vastus.
	 */
	function wcKeyFor( mode, additional ) {
		return [ state.email, orderParam(), mode, additional ? 1 : 0 ].join( '|' );
	}

	function wcKey() {
		var e = emailSettings();

		return wcKeyFor( e.mode, e.additional );
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

		return '<div class="wmd-server-note is-warn">' + __( 'The WooCommerce part of the canvas shows ', 'wonom-meilidisainer' ) + '<strong>' + __( 'sample content', 'wonom-meilidisainer' ) + '</strong>' + __( ', not the real text', 'wonom-meilidisainer' ) + '' +
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
			order: orderParam(),
			design: JSON.stringify( state.design ),
		} ).then( function ( res ) {
			wcCache[ key ] = {
				pending: false,
				html: res.html || '',
				css: res.css || '',
				items: res.items || [],
				totals: res.totals || [],
				fields: res.fields || [],
				addr: res.addr || null,
				ctx: res.ctx || {},
				parts: res.parts || {},
				why: res.why || '',
			};

			if ( wcKey() === key ) {
				render();
			}
		} ).catch( function () {
			wcCache[ key ] = { pending: false, html: '', css: '', items: [], totals: [], fields: [], ctx: {}, parts: {}, addr: null, why: __( 'Could not fetch the WooCommerce content.', 'wonom-meilidisainer' ) };
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
				blockDefaults: blockDefaults(),
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

			// WooCommerce'i praegune sisu plokkidena, sinu enda plokid ümber.
			// Wrap-režiimi plokid jäävad alles, nii et tagasi minnes ei ole
			// midagi kadunud.
			takeOverFromWc( { keepAround: true } );
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

	/**
	 * Valitud tellimuse tegelik väärtus märgendi kohta, kui see on serverist käes.
	 */
	function tagValue( key ) {
		var wc = wcCache[ wcKey() ];

		if ( wc && wc.ctx && typeof wc.ctx[ key ] === 'string' && wc.ctx[ key ] !== '' ) {
			return wc.ctx[ key ];
		}

		return '';
	}

	/**
	 * Märgendite nimekiri rühmade kaupa. Kasutab nii { } menüü kui Muutujad-tab.
	 *
	 * @param {Function} rowFn Ehitab ühe rea HTML-i.
	 * @param {string}   query Otsingusõna või tühi.
	 * @return {string} HTML.
	 */
	function tagListHtml( rowFn, query ) {
		var groups = cfg.tagGroups || {};
		var tags = cfg.tags || {};
		var q = ( query || '' ).toLowerCase();
		var html = '';

		function matches( key, label ) {
			return ! q || key.toLowerCase().indexOf( q ) !== -1 || String( label ).toLowerCase().indexOf( q ) !== -1;
		}

		Object.keys( groups ).forEach( function ( g ) {
			var rows = Object.keys( tags ).filter( function ( key ) {
				return tags[ key ].group === g && matches( key, tags[ key ].label );
			} );

			if ( ! rows.length ) {
				return;
			}

			html += '<div class="wmd-tags-head">' + esc( groups[ g ] ) + '</div>';
			rows.forEach( function ( key ) {
				html += rowFn( key, tags[ key ].label, tagValue( key ) );
			} );
		} );

		// Selle tellimuse enda väljad — nii ei pea võtmeid peast teadma.
		var fields = orderFields().filter( function ( f ) {
			return matches( f.key, f.sample );
		} );

		if ( fields.length ) {
			html += '<div class="wmd-tags-head">' + __( 'Fields on this order', 'wonom-meilidisainer' ) + '</div>';
			fields.forEach( function ( f ) {
				html += rowFn( 'meta:' + f.key, __( 'Order field', 'wonom-meilidisainer' ), f.sample );
			} );
		}

		return html;
	}

	function tagPicker( target ) {
		var items = tagListHtml( function ( key, label, value ) {
			return '<button type="button" class="wmd-tag" data-tag="' + esc( key ) + '">' +
				'<code>{{' + esc( key ) + '}}</code>' +
				'<span>' + esc( value || label ) + '</span></button>';
		}, '' );

		return '<div class="wmd-tags" data-for="' + esc( target ) + '">' +
			'<button type="button" class="wmd-tags-toggle" title="' + __( 'Insert a variable', 'wonom-meilidisainer' ) + '">{ }</button>' +
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
				'<button type="button" data-col-up="' + index + '" title="' + __( 'Up', 'wonom-meilidisainer' ) + '"' + ( index === 0 ? ' disabled' : '' ) + '>↑</button>' +
				'<button type="button" data-col-down="' + index + '" title="' + __( 'Down', 'wonom-meilidisainer' ) + '"' + ( index === cols.length - 1 ? ' disabled' : '' ) + '>↓</button>' +
				'</span></li>';
		} ).join( '' );

		return '<div class="wmd-field"><label class="wmd-label">' + esc( field.label ) + '</label>' +
			'<ul class="wmd-cols" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '">' + rows + '</ul>' +
			'<p class="wmd-hint">' + __( 'The tick shows whether the column goes into the email. You can rewrite the label and reorder it with the arrows.', 'wonom-meilidisainer' ) + '</p></div>';
	}

	/**
	 * Silt-väärtus ridade toimeti. Väärtuse saab valida { } menüüst, kus on
	 * nii üldised märgendid kui selle tellimuse päris väljad.
	 */
	function pairsHtml( scope, key, field, value ) {
		var rows = Array.isArray( value ) ? value : [];

		var items = rows.map( function ( row, index ) {
			var vid = 'wmd-pair-' + index;

			return '<li class="wmd-pair" data-index="' + index + '">' +
				'<div class="wmd-pair-top">' +
				'<input type="text" class="wmd-input wmd-pair-label" data-pair-label="' + index + '" value="' + esc( row.label ) + '" placeholder="' + __( 'Label', 'wonom-meilidisainer' ) + '" />' +
				'<span class="wmd-colmove">' +
				'<button type="button" data-pair-up="' + index + '" title="' + __( 'Up', 'wonom-meilidisainer' ) + '"' + ( index === 0 ? ' disabled' : '' ) + '>↑</button>' +
				'<button type="button" data-pair-down="' + index + '" title="' + __( 'Down', 'wonom-meilidisainer' ) + '"' + ( index === rows.length - 1 ? ' disabled' : '' ) + '>↓</button>' +
				'<button type="button" data-pair-del="' + index + '" title="' + __( 'Delete row', 'wonom-meilidisainer' ) + '">✕</button>' +
				'</span></div>' +
				'<div class="wmd-inline">' +
				'<input type="text" class="wmd-input wmd-small" id="' + esc( vid ) + '" data-pair-value="' + index + '" value="' + esc( row.value ) + '" placeholder="' + __( 'Value or {{variable}}', 'wonom-meilidisainer' ) + '" />' +
				tagPicker( vid ) +
				'</div>' +
				'<input type="text" class="wmd-input wmd-small" data-pair-link="' + index + '" value="' + esc( row.link || '' ) + '" placeholder="' + __( 'Link (optional), {{value}} = the value', 'wonom-meilidisainer' ) + '" />' +
				'</li>';
		} ).join( '' );

		return '<div class="wmd-field"><label class="wmd-label">' + esc( field.label ) + '</label>' +
			'<ul class="wmd-pairs" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '">' + items + '</ul>' +
			'<button type="button" class="wmd-mini wmd-pair-add">' + __( '+ Add row', 'wonom-meilidisainer' ) + '</button>' +
			'<p class="wmd-hint">' + __( 'You can pick the value under the { } button — the real fields of this order are there too, such as the tracking code.', 'wonom-meilidisainer' ) + '</p></div>';
	}

	/**
	 * Pildikaartide toimeti: pilt, link ja nimi ühe rea kohta.
	 *
	 * Ridu saab täita käsitsi või tootekategooriate nimekirjast — siis tulevad
	 * pilt, link ja nimi kohe WooCommerce'ist.
	 *
	 * @param {string} scope Ploki id.
	 * @param {string} key   Välja võti.
	 * @param {Object} field Välja kirjeldus.
	 * @param {Array}  value Read.
	 * @return {string} HTML.
	 */
	function cardsHtml( scope, key, field, value ) {
		var rows = Array.isArray( value ) ? value : [];

		var items = rows.map( function ( row, index ) {
			var thumb = row.image
				? '<img src="' + esc( row.image ) + '" alt="" />'
				: '<div class="wmd-image-empty">—</div>';

			return '<li class="wmd-card-row" data-index="' + index + '">' +
				'<div class="wmd-card-thumb">' + thumb +
				'<button type="button" class="wmd-mini" data-card-media="' + index + '">' + esc( row.image ? __( 'Change', 'wonom-meilidisainer' ) : __( 'Choose image', 'wonom-meilidisainer' ) ) + '</button>' +
				'</div>' +
				'<div class="wmd-card-fields">' +
				'<div class="wmd-card-top">' +
				'<input type="text" class="wmd-input wmd-small" data-card-label="' + index + '" value="' + esc( row.label || '' ) + '" placeholder="' + __( 'Name under the image', 'wonom-meilidisainer' ) + '" />' +
				'<span class="wmd-colmove">' +
				'<button type="button" data-card-up="' + index + '" title="' + __( 'Left', 'wonom-meilidisainer' ) + '"' + ( index === 0 ? ' disabled' : '' ) + '>↑</button>' +
				'<button type="button" data-card-down="' + index + '" title="' + __( 'Right', 'wonom-meilidisainer' ) + '"' + ( index === rows.length - 1 ? ' disabled' : '' ) + '>↓</button>' +
				'<button type="button" data-card-del="' + index + '" title="' + __( 'Delete', 'wonom-meilidisainer' ) + '">✕</button>' +
				'</span></div>' +
				'<input type="text" class="wmd-input wmd-small" data-card-link="' + index + '" value="' + esc( row.link || '' ) + '" placeholder="' + __( 'Link, for example the category address', 'wonom-meilidisainer' ) + '" />' +
				'<input type="text" class="wmd-input wmd-small" data-card-image="' + index + '" value="' + esc( row.image || '' ) + '" placeholder="' + __( 'Image address https://…', 'wonom-meilidisainer' ) + '" />' +
				'</div></li>';
		} ).join( '' );

		// Kategooriate nimekiri jääb lahti ka pärast valikut, nii et neli pilti
		// saab järjest välja klõpsata ilma nimekirja iga kord uuesti avamata.
		var cats = '';

		if ( state.catsOpen && state.cats ) {
			cats = state.cats.length
				? '<p class="wmd-hint">' + __( 'A click adds the category to the next empty slot.', 'wonom-meilidisainer' ) + '</p>' +
					state.cats.map( function ( c, i ) {
						return '<button type="button" class="wmd-cat" data-cat="' + i + '">' +
							( c.image ? '<img src="' + esc( c.image ) + '" alt="" />' : '<span class="wmd-image-empty">—</span>' ) +
							'<em>' + esc( c.name ) + '</em></button>';
					} ).join( '' )
				: '<p class="wmd-hint">' + __( 'No product categories were found.', 'wonom-meilidisainer' ) + '</p>';
		}

		return '<div class="wmd-field"><label class="wmd-label">' + esc( field.label ) + '</label>' +
			'<ul class="wmd-cards-edit" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '">' + items + '</ul>' +
			'<div class="wmd-card-tools">' +
			'<button type="button" class="wmd-mini wmd-card-add">' + __( '+ Add image', 'wonom-meilidisainer' ) + '</button>' +
			'<button type="button" class="wmd-mini wmd-card-cats">' + ( state.catsOpen ? '' + __( 'Hide categories', 'wonom-meilidisainer' ) + '' : __( 'Load product categories', 'wonom-meilidisainer' ) ) + '</button>' +
			'</div>' +
			'<div class="wmd-card-catlist"' + ( cats ? '' : ' hidden' ) + '>' + cats + '</div>' +
			'<p class="wmd-hint">' + __( 'Slots left empty are not put into the real email — they are shown in the designer only so you can see the layout.', 'wonom-meilidisainer' ) + '</p></div>';
	}

	function fieldHtml( scope, key, field, value ) {
		var id = 'wmd-f-' + scope + '-' + key;
		var label = '<label class="wmd-label" for="' + esc( id ) + '">' + esc( field.label ) + '</label>';
		var attrs = 'id="' + esc( id ) + '" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '"';
		var body = '';

		if ( field.type === 'columns' ) {
			return columnsHtml( scope, key, field, value );
		}

		if ( field.type === 'pairs' ) {
			return pairsHtml( scope, key, field, value );
		}

		if ( field.type === 'cards' ) {
			return cardsHtml( scope, key, field, value );
		}

		// Tellimuse välja võti: vaba tekst, aga nimekirjas on selle tellimuse
		// päris võtmed koos väärtusega.
		if ( field.type === 'metakey' ) {
			var listId = 'wmd-metakeys';
			var options = orderFields().map( function ( f ) {
				return '<option value="' + esc( f.key ) + '">' + esc( f.sample ) + '</option>';
			} ).join( '' );

			return '<div class="wmd-field">' +
				'<label class="wmd-label" for="' + esc( id ) + '">' + esc( field.label ) + '</label>' +
				'<input type="text" class="wmd-input" list="' + listId + '" ' + attrs + ' value="' + esc( value ) + '" placeholder="_tracking_number" />' +
				'<datalist id="' + listId + '">' + options + '</datalist>' +
				( field.hint ? '<p class="wmd-hint">' + esc( field.hint ) + '</p>' : '' ) +
				'</div>';
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
					'<button type="button" data-wrap="strong" title="' + __( 'Bold', 'wonom-meilidisainer' ) + '"><b>B</b></button>' +
					'<button type="button" data-wrap="em" title="' + __( 'Italic', 'wonom-meilidisainer' ) + '"><i>I</i></button>' +
					'<button type="button" data-wrap="br" title="' + __( 'Line break', 'wonom-meilidisainer' ) + '">↵</button>' +
					'<button type="button" data-wrap="a" title="' + __( 'Link', 'wonom-meilidisainer' ) + '">🔗</button>' +
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
					'<input type="text" class="wmd-input wmd-hex" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '" value="' + esc( value ) + '" placeholder="' + esc( inherited && field.inherit ? __( 'From brand', 'wonom-meilidisainer' ) : shown ) + '" />' +
					( field.inherit ? '<button type="button" class="wmd-mini" data-clear="' + esc( scope ) + '|' + esc( key ) + '">' + esc( __( 'From brand', 'wonom-meilidisainer' ) ) + '</button>' : '' ) +
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
					'<button type="button" class="wmd-mini" data-media="' + esc( scope ) + '|' + esc( key ) + '">' + esc( value ? __( 'Change', 'wonom-meilidisainer' ) : __( 'Choose image', 'wonom-meilidisainer' ) ) + '</button>' +
					( value ? '<button type="button" class="wmd-mini" data-clear="' + esc( scope ) + '|' + esc( key ) + '">' + esc( __( 'Remove', 'wonom-meilidisainer' ) ) + '</button>' : '' ) +
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
				'<button type="button" class="wmd-icon" data-move="' + esc( zone ) + '|' + esc( b.id ) + '|-1" title="' + __( 'Up', 'wonom-meilidisainer' ) + '"' + ( 0 === index ? ' disabled' : '' ) + '>↑</button>' +
				'<button type="button" class="wmd-icon" data-move="' + esc( zone ) + '|' + esc( b.id ) + '|1" title="' + __( 'Down', 'wonom-meilidisainer' ) + '"' + ( index === list.length - 1 ? ' disabled' : '' ) + '>↓</button>' +
				'<button type="button" class="wmd-icon" data-dup="' + esc( zone ) + '|' + esc( b.id ) + '" title="' + __( 'Duplicate', 'wonom-meilidisainer' ) + '">⧉</button>' +
				'<button type="button" class="wmd-icon" data-del="' + esc( zone ) + '|' + esc( b.id ) + '" title="' + __( 'Delete', 'wonom-meilidisainer' ) + '">✕</button>' +
				'</span></li>';
		} ).join( '' );

		if ( ! items ) {
			items = '<li class="wmd-empty">' + esc( __( 'No blocks here yet. Add one below.', 'wonom-meilidisainer' ) ) + '</li>';
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
			text = p.url ? p.url.split( '/' ).pop() : __( 'no image', 'wonom-meilidisainer' );
		}
		return text.length > 46 ? text.slice( 0, 46 ) + '…' : text;
	}

	function brandPanelHtml() {
		var groups = cfg.brandGroups;
		var schema = cfg.brandSchema;

		var html = '<div class="wmd-intro">' + __( 'These settings apply to ', 'wonom-meilidisainer' ) + '<strong>' + __( 'every', 'wonom-meilidisainer' ) + '</strong>' + __( ' WooCommerce email. Set them once and you are done.', 'wonom-meilidisainer' ) + '</div>';

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

		var html = '<div class="wmd-intro">' + __( 'Pick an email and add to it. A field left empty means the WooCommerce default is used.', 'wonom-meilidisainer' ) + '</div>';
		html += '<div class="wmd-field"><label class="wmd-label" for="wmd-email-pick">' + __( 'Email', 'wonom-meilidisainer' ) + '</label>' +
			'<select class="wmd-input" id="wmd-email-pick">' + opts + '</select></div>';

		html += '<div class="wmd-field"><label class="wmd-label" for="wmd-f-email-subject">' + __( 'Subject line', 'wonom-meilidisainer' ) + '</label>' +
			'<div class="wmd-inline"><input type="text" class="wmd-input" id="wmd-f-email-subject" data-scope="email" data-key="subject" value="' + esc( e.subject ) + '" placeholder="' + esc( wcDefault( state.email, 'subject' ) ) + '" />' +
			tagPicker( 'wmd-f-email-subject' ) + '</div></div>';

		html += '<div class="wmd-field"><label class="wmd-label" for="wmd-f-email-heading">' + __( 'Large heading in the email', 'wonom-meilidisainer' ) + '</label>' +
			'<div class="wmd-inline"><input type="text" class="wmd-input" id="wmd-f-email-heading" data-scope="email" data-key="heading" value="' + esc( e.heading ) + '" placeholder="' + esc( wcDefault( state.email, 'heading' ) ) + '" />' +
			tagPicker( 'wmd-f-email-heading' ) + '</div></div>';

		html += '<div class="wmd-field wmd-field-toggle"><label class="wmd-switch">' +
			'<input type="checkbox" data-scope="email" data-key="additional"' + ( e.additional ? ' checked' : '' ) + ' />' +
			'<span></span>' + __( 'WooCommerce additional content at the end', 'wonom-meilidisainer' ) + '</label>' +
			'<p class="wmd-hint">' + __( 'You will find this text under WooCommerce → Settings → Emails. It cannot be emptied there — an empty field is replaced by the default text ("Thanks for shopping with us."). Here you can switch it off for good.', 'wonom-meilidisainer' ) + '</p></div>';

		html += '<div class="wmd-field"><label class="wmd-label">' + __( 'How the email is put together', 'wonom-meilidisainer' ) + '</label>' +
			'<div class="wmd-segs wmd-modes">' +
			'<button type="button" class="wmd-seg' + ( full ? '' : ' is-active' ) + '" data-mode="wrap">' + __( 'Around WooCommerce content', 'wonom-meilidisainer' ) + '</button>' +
			'<button type="button" class="wmd-seg' + ( full ? ' is-active' : '' ) + '" data-mode="full">' + __( 'Build the whole email', 'wonom-meilidisainer' ) + '</button>' +
			'</div></div>';

		if ( full ) {
			html += '<div class="wmd-intro wmd-warn">' + __( 'This mode does not use the WooCommerce content template. Everything in the email comes from the blocks below — including the order table and the addresses.', 'wonom-meilidisainer' ) + '</div>';
			html += '<div class="wmd-updates-actions"><button type="button" class="button wmd-refill">' + __( 'Load the WooCommerce content as blocks', 'wonom-meilidisainer' ) + '</button></div>' +
				'<p class="wmd-hint">' + __( 'Takes this email\'s current WooCommerce content apart into blocks and replaces the blocks below. Useful when you want to start again from the WooCommerce text.', 'wonom-meilidisainer' ) + '</p>';
			html += blockListHtml( 'body', __( 'Email content', 'wonom-meilidisainer' ), __( 'the whole body', 'wonom-meilidisainer' ) );
		} else {
			html += blockListHtml( 'before', __( 'Content before the order table', 'wonom-meilidisainer' ), __( 'greeting, info', 'wonom-meilidisainer' ) );
			html += blockListHtml( 'after', __( 'Content after the order table', 'wonom-meilidisainer' ), __( 'button, upsell', 'wonom-meilidisainer' ) );
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
	 * Vaikimisi keha, kui WooCommerce'i sisu ei õnnestunud plokkideks võtta.
	 */
	function seedBody() {
		return [
			makeBlock( 'text' ),
			makeBlock( 'order_table' ),
			makeBlock( 'addresses' ),
		];
	}

	/**
	 * Märgendid, mille väärtust ei tohi tekstis tagasi asendada.
	 *
	 * Kas liiga üldised (aastaarv, kogus) või sellised, mille väärtus on
	 * tavaline sõna ja satuks jutu sisse (olek „Töötlemisel", riik „Eesti").
	 */
	var NO_DETOKEN = {
		year: 1,
		item_count: 1,
		order_currency: 1,
		order_id: 1,
		order_status: 1,
		billing_country: 1,
		shipping_country: 1,
		billing_state: 1,
		shipping_state: 1,
	};

	/**
	 * Asendab WooCommerce'i renderdatud tekstis selle tellimuse väärtused tagasi
	 * märgenditeks.
	 *
	 * Ilma selleta läheks „Võta üle" järel iga kliendi kirja ühe konkreetse
	 * tellimuse nimi, number ja kuupäev — täpselt nii, nagu need ülevõtmise
	 * hetkel ekraanil olid.
	 *
	 * @param {string} html Tekst või HTML.
	 * @param {Object} ctx  Serverist tulnud märgendite väärtused.
	 * @return {string} Sama tekst, väärtused märgenditega asendatud.
	 */
	function detokenize( html, ctx ) {
		if ( ! html || ! ctx ) {
			return html;
		}

		var keys = Object.keys( ctx ).filter( function ( key ) {
			var value = ctx[ key ];

			return ! NO_DETOKEN[ key ] &&
				key.indexOf( '__' ) !== 0 &&
				typeof value === 'string' &&
				value.trim().length >= 4;
		} );

		// Pikemad väärtused enne, muidu sööks „Mari" ära „Mari Tamme" algusest
		// ja poolik nimi jääks kirja.
		keys.sort( function ( a, b ) {
			return ctx[ b ].trim().length - ctx[ a ].trim().length;
		} );

		keys.forEach( function ( key ) {
			var value = ctx[ key ].trim();
			var safe = value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

			html = html.replace( new RegExp( safe, 'g' ), '{{' + key + '}}' );
		} );

		return html;
	}

	/**
	 * Võtab WooCommerce'i renderdatud sisu lahti meie plokkideks.
	 *
	 * Nii ei alusta „terve meil ise" tühjalt lehelt, vaid samast kirjast, mille
	 * WooCommerce praegu saadab — edasi saab seda tavaliste plokkidena muuta.
	 *
	 * @param {string} html WooCommerce'i sisuosa.
	 * @param {Object} ctx  Märgendite väärtused, et need tekstist tagasi võtta.
	 * @return {Array} Plokid.
	 */
	function blocksFromWcHtml( html, ctx ) {
		if ( ! html || ! window.DOMParser ) {
			return [];
		}

		var doc = new DOMParser().parseFromString( '<div id="wmd-root">' + html + '</div>', 'text/html' );
		var root = doc.getElementById( 'wmd-root' );

		if ( ! root ) {
			return [];
		}

		var out = [];

		function text( el ) {
			return ( el.textContent || '' ).replace( /\s+/g, ' ' ).trim();
		}

		function clean( value ) {
			return detokenize( String( value == null ? '' : value ), ctx );
		}

		/**
		 * Kas selle elemendi ainus sisu on üks pilt.
		 *
		 * Pilt tekstiplokis ei jääks ellu: richtext-välja puhastus eemaldab
		 * <img> märgendi ja pilt kaoks vaikselt. Seepärast teeme pildiploki.
		 *
		 * @param {Element} node Element.
		 * @return {Object|null} Pildi andmed või null.
		 */
		function onlyImage( node ) {
			if ( text( node ) ) {
				return null;
			}

			var imgs = node.querySelectorAll( 'img' );

			if ( imgs.length !== 1 ) {
				return null;
			}

			var img = imgs[ 0 ];
			var link = img.closest ? img.closest( 'a' ) : null;
			var width = parseInt( img.getAttribute( 'width' ), 10 );

			return {
				url: clean( img.getAttribute( 'src' ) || '' ),
				alt: img.getAttribute( 'alt' ) || '',
				link: ( link && node.contains( link ) && link.getAttribute( 'href' ) ) ? clean( link.getAttribute( 'href' ) ) : '',
				width: width > 0 ? Math.min( 800, Math.max( 40, width ) ) : 240,
				align: 'center',
			};
		}

		function push( type, props ) {
			out.push( makeBlock( type ) );
			var block = out[ out.length - 1 ];
			Object.keys( props || {} ).forEach( function ( k ) {
				block.props[ k ] = props[ k ];
			} );
		}

		function isAddressTable( el ) {
			return el.id === 'addresses' || !! el.querySelector( 'address' );
		}

		function isOrderTable( el ) {
			return !! ( el.querySelector( 'thead' ) || el.querySelector( 'tfoot' ) );
		}

		function walk( node ) {
			Array.prototype.forEach.call( node.childNodes, function ( child ) {
				// Puhas tekst ilma märgendita — harv, aga ei tohi kaduda.
				if ( child.nodeType === 3 ) {
					var raw = ( child.textContent || '' ).trim();
					if ( raw ) {
						push( 'text', { html: clean( raw ) } );
					}
					return;
				}

				if ( child.nodeType !== 1 ) {
					return;
				}

				var tag = child.tagName.toLowerCase();

				if ( tag === 'h1' || tag === 'h2' || tag === 'h3' ) {
					if ( text( child ) ) {
						push( 'heading', {
							text: clean( text( child ) ),
							size: tag === 'h1' ? 'lg' : ( tag === 'h2' ? 'md' : 'sm' ),
						} );
					}
					return;
				}

				// Pilt ilma ümbriseta. Ilma selle haruta kukuks see läbi, sest
				// pildil ei ole teksti ja viimane haru vaatab just teksti.
				if ( tag === 'img' ) {
					var width = parseInt( child.getAttribute( 'width' ), 10 );

					push( 'image', {
						url: clean( child.getAttribute( 'src' ) || '' ),
						alt: child.getAttribute( 'alt' ) || '',
						width: width > 0 ? Math.min( 800, Math.max( 40, width ) ) : 240,
						align: 'center',
					} );
					return;
				}

				if ( tag === 'p' ) {
					var lone = onlyImage( child );

					if ( lone ) {
						push( 'image', lone );
						return;
					}

					if ( text( child ) ) {
						push( 'text', { html: clean( child.innerHTML.trim() ) } );
					}
					return;
				}

				if ( tag === 'ul' || tag === 'ol' ) {
					push( 'html', { code: clean( child.outerHTML ) } );
					return;
				}

				if ( tag === 'table' ) {
					if ( isAddressTable( child ) ) {
						push( 'addresses', {} );
					} else if ( isOrderTable( child ) ) {
						push( 'order_items', {} );
						push( 'order_totals', {} );
					} else if ( text( child ) ) {
						push( 'html', { code: clean( child.outerHTML ) } );
					}
					return;
				}

				// Mähised (div, section) — vaatame nende sisse.
				if ( tag === 'div' || tag === 'section' || tag === 'header' || tag === 'footer' ) {
					walk( child );
					return;
				}

				var picture = onlyImage( child );

				if ( picture ) {
					push( 'image', picture );
					return;
				}

				if ( text( child ) ) {
					push( 'html', { code: clean( child.outerHTML ) } );
				}
			} );
		}

		walk( root );

		return out;
	}

	/**
	 * Küsib serverilt selle meili WooCommerce'i sisu ja annab selle plokkidena.
	 *
	 * Sisu küsitakse alati wrap-kujul: täisrežiimis jätab server WooCommerce'i
	 * sisuosa hoopis renderdamata, sest kirja paneb siis kokku kujundaja. Just
	 * täisrežiimi minnes on seda sisu aga vaja — nii et küsime seda eraldi.
	 *
	 * @param {Function} done Saab plokkide massiivi; tühi massiiv = ei saanud.
	 */
	function loadWcBlocks( done ) {
		var probe = JSON.parse( JSON.stringify( state.design ) );

		probe.emails[ state.email ] = probe.emails[ state.email ] || {};
		probe.emails[ state.email ].mode = 'wrap';
		probe.emails[ state.email ].body = [];

		post( 'wmd_wc_part', {
			email: state.email,
			order: orderParam(),
			design: JSON.stringify( probe ),
		} ).then( function ( res ) {
			// Kontekst on kaasas selleks, et tekstis olevad selle tellimuse
			// väärtused saaks tagasi märgenditeks võtta (vt detokenize).
			done( blocksFromWcHtml( res.html || '', res.ctx || {} ) );
		} ).catch( function ( err ) {
			toast( err || __( 'WooCommerce content could not be loaded', 'wonom-meilidisainer' ), 'error' );
			done( [] );
		} );
	}

	/**
	 * „Võta üle": lülitab meili täisrežiimi ja toob WooCommerce'i sisu sisse.
	 *
	 * Sisu tuuakse serverist just selle vajutuse hetkel, mitte vahemälust — nii
	 * tuleb kaasa täpselt see kiri, mille WooCommerce praegu saadaks, koos
	 * kõigi linkidega. Enne ülekirjutamist küsime kinnitust, kui kehas on juba
	 * plokke; vaikselt tegemata jätta ei tohi, sest siis jääks nupp mõjuta.
	 *
	 * @param {Object} opts keepAround: kas jätta enne-/pärast-plokid ümber,
	 *                      button: nupp, mis ootamise ajaks kinni panna.
	 */
	function takeOverFromWc( opts ) {
		opts = opts || {};

		var e = emailSettings();

		if ( e.body.length && ! window.confirm( __( 'This email already has ', 'wonom-meilidisainer' ) + e.body.length + ' ' + __( 'blocks. Replace them with the current WooCommerce content?', 'wonom-meilidisainer' ) ) ) {
			return;
		}

		var btn   = opts.button || null;
		var label = btn ? btn.textContent : '';

		if ( btn ) {
			btn.disabled = true;
			btn.textContent = __( 'Loading…', 'wonom-meilidisainer' );
		}

		loadWcBlocks( function ( middle ) {
			if ( ! middle.length && ! opts.fallback ) {
				toast( __( 'WooCommerce content could not be turned into blocks', 'wonom-meilidisainer' ), 'error' );

				if ( btn ) {
					btn.disabled = false;
					btn.textContent = label;
				}

				return;
			}

			// Režiimi vahetusel ei tohi jääda tühja lehe peale seisma: kui
			// WooCommerce'i sisu ei saanud, alustame vaikeplokkidest.
			if ( ! middle.length ) {
				middle = seedBody().slice( 1 );
				toast( __( 'Could not get the WooCommerce content — starting from the default blocks', 'wonom-meilidisainer' ), 'error' );
			}

			var body = middle;

			if ( opts.keepAround ) {
				body = JSON.parse( JSON.stringify( e.before || [] ) )
					.concat( middle, JSON.parse( JSON.stringify( e.after || [] ) ) );
			}

			body.forEach( function ( b ) {
				b.id = newId();
			} );

			e.mode = 'full';
			e.body = body;
			state.selected = null;
			markDirty();
			render();
			invalidatePreview();
			toast( body.length + ' ' + __( 'blocks loaded — the WooCommerce text no longer goes into the email', 'wonom-meilidisainer' ), 'ok' );
		} );
	}

	/* ------------------------------------------------------- muutujad */

	function varsPanelHtml() {
		var order = currentOrder();

		var source = order
			? '' + __( 'The value comes from the selected order (', 'wonom-meilidisainer' ) + '<strong>' + esc( order.label ) + '</strong>).'
			: ( usesOrder()
				? '' + __( 'The value comes from the selected order.', 'wonom-meilidisainer' ) + ''
				: '' + __( 'This email does not come from an order, so the order variables have no value here.', 'wonom-meilidisainer' ) + '' );

		var html = '<div class="wmd-intro">' + __( 'Every variable you can use in the email.', 'wonom-meilidisainer' ) + ' ' + source +
			' ' + __( 'A click copies the variable — you can paste it into any field, including the "Custom HTML" block or the extra CSS.', 'wonom-meilidisainer' ) + '</div>';

		html += '<div class="wmd-field"><input type="text" class="wmd-input wmd-var-search" placeholder="' + __( 'Search for a variable…', 'wonom-meilidisainer' ) + '" value="' + esc( state.varQuery ) + '" /></div>';

		var rows = tagListHtml( function ( key, label, value ) {
			return '<button type="button" class="wmd-var" data-copy="{{' + esc( key ) + '}}">' +
				'<code>{{' + esc( key ) + '}}</code>' +
				'<em>' + esc( label ) + '</em>' +
				'<span>' + esc( value || '—' ) + '</span></button>';
		}, state.varQuery );

		html += '<div class="wmd-vars">' + ( rows || '<p class="wmd-hint">' + __( 'Nothing found.', 'wonom-meilidisainer' ) + '</p>' ) + '</div>';

		return html;
	}

	function bindVars() {
		var search = root.querySelector( '.wmd-var-search' );

		if ( search ) {
			search.addEventListener( 'input', function () {
				state.varQuery = search.value;

				// Ainult nimekiri joonistatakse uuesti, et otsinguväli fookust ei kaotaks.
				var list = root.querySelector( '.wmd-vars' );

				if ( list ) {
					list.innerHTML = tagListHtml( function ( key, label, value ) {
						return '<button type="button" class="wmd-var" data-copy="{{' + esc( key ) + '}}">' +
							'<code>{{' + esc( key ) + '}}</code><em>' + esc( label ) + '</em><span>' + esc( value || '—' ) + '</span></button>';
					}, state.varQuery ) || '<p class="wmd-hint">' + __( 'Nothing found.', 'wonom-meilidisainer' ) + '</p>';
					bindVarCopy();
				}
			} );
		}

		bindVarCopy();
	}

	function bindVarCopy() {
		root.querySelectorAll( '[data-copy]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var text = btn.getAttribute( 'data-copy' );

				function done() {
					btn.classList.add( 'is-copied' );
					setTimeout( function () {
						btn.classList.remove( 'is-copied' );
					}, 1200 );
					toast( __( 'Copied: ', 'wonom-meilidisainer' ) + text, 'ok' );
				}

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( done ).catch( fallback );
				} else {
					fallback();
				}

				// Vanemad brauserid ja lubadeta olukorrad.
				function fallback() {
					var tmp = document.createElement( 'textarea' );
					tmp.value = text;
					document.body.appendChild( tmp );
					tmp.select();
					try {
						document.execCommand( 'copy' );
						done();
					} catch ( e ) {
						toast( __( 'Copying failed', 'wonom-meilidisainer' ), 'error' );
					}
					document.body.removeChild( tmp );
				}
			} );
		} );
	}

	/* ---------------------------------------------------- makseviisid */

	function paymentsPanelHtml() {
		var gateways = cfg.gateways || {};
		var keys = Object.keys( gateways );

		if ( ! keys.length ) {
			return '<div class="wmd-intro">' + __( 'No payment methods were found in the shop.', 'wonom-meilidisainer' ) + '</div>';
		}

		if ( ! state.design.payments ) {
			state.design.payments = {};
		}

		var html = '<div class="wmd-intro">' + __( 'Write the instructions for each payment method here once. The block ', 'wonom-meilidisainer' ) + '<strong>' + __( '"Payment instructions (own text)"', 'wonom-meilidisainer' ) + '</strong>' + __( ' brings them into the email — it always shows the text for that order\'s payment method. For a payment method left empty the block simply does not appear.', 'wonom-meilidisainer' ) + '</div>';

		keys.forEach( function ( id ) {
			var value = state.design.payments[ id ] || '';
			var fid = 'wmd-pay-' + id;

			html += '<div class="wmd-field">' +
				'<label class="wmd-label" for="' + esc( fid ) + '">' + esc( gateways[ id ] ) + ' <code>' + esc( id ) + '</code></label>' +
				'<div class="wmd-rich">' +
				'<div class="wmd-rich-bar">' +
				'<button type="button" data-wrap="strong" title="' + __( 'Bold', 'wonom-meilidisainer' ) + '"><b>B</b></button>' +
				'<button type="button" data-wrap="em" title="' + __( 'Italic', 'wonom-meilidisainer' ) + '"><i>I</i></button>' +
				'<button type="button" data-wrap="br" title="' + __( 'Line break', 'wonom-meilidisainer' ) + '">↵</button>' +
				'<button type="button" data-wrap="a" title="' + __( 'Link', 'wonom-meilidisainer' ) + '">🔗</button>' +
				tagPicker( fid ) +
				'</div>' +
				'<textarea class="wmd-input wmd-textarea" rows="5" id="' + esc( fid ) + '" data-scope="payment" data-key="' + esc( id ) + '" ' +
				'placeholder="' + __( 'For example: please transfer the amount from your bank straight to our account…', 'wonom-meilidisainer' ) + '">' + esc( value ) + '</textarea>' +
				'</div></div>';
		} );

		return html;
	}

	/* ------------------------------------------------------ uuendused */

	/**
	 * Kujunduse fail: kogu see, mis kujundajas seadistatud on, koos päisega,
	 * et importimisel oleks näha, kust ja millal see tuli.
	 */
	function exportPayload() {
		return {
			_wmd: {
				version: state.updates.current || '',
				exported: new Date().toISOString(),
				site: window.location.hostname,
			},
			design: state.design,
		};
	}

	function exportFileName() {
		var d = new Date();
		var stamp = d.getFullYear() + '-' +
			String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' +
			String( d.getDate() ).padStart( 2, '0' );

		return 'meilidisainer-' + window.location.hostname + '-' + stamp + '.json';
	}

	function backupPanelHtml() {
		var json = JSON.stringify( exportPayload(), null, 2 );

		return '<details class="wmd-group" open><summary>' + __( 'Export and import the design', 'wonom-meilidisainer' ) + '</summary><div class="wmd-group-body">' +
			'<p class="wmd-hint">' + __( 'The whole design — brand, header, footer, every email and the payment instructions — in one file. In another shop you import it and have the same setup at once.', 'wonom-meilidisainer' ) + '</p>' +

			'<div class="wmd-updates-actions">' +
			'<button type="button" class="button button-primary wmd-export">' + __( 'Download the design', 'wonom-meilidisainer' ) + '</button>' +
			'</div>' +

			'<details class="wmd-group"><summary>' + __( 'Show the JSON', 'wonom-meilidisainer' ) + '</summary><div class="wmd-group-body">' +
			'<textarea class="wmd-input wmd-textarea wmd-mono wmd-export-json" rows="8" readonly>' + esc( json ) + '</textarea>' +
			'</div></details>' +

			'<hr class="wmd-hr" />' +

			'<p class="wmd-hint"><strong>' + __( 'Importing overwrites the whole current design.', 'wonom-meilidisainer' ) + '</strong>' + __( ' Pick a file or paste JSON.', 'wonom-meilidisainer' ) + '</p>' +
			'<div class="wmd-updates-actions">' +
			'<button type="button" class="button wmd-import-pick">' + __( 'Choose a file…', 'wonom-meilidisainer' ) + '</button>' +
			'<input type="file" class="wmd-import-file" accept="application/json,.json" hidden />' +
			'</div>' +
			'<textarea class="wmd-input wmd-textarea wmd-mono wmd-import-json" rows="4" placeholder="' + __( '…or paste JSON here', 'wonom-meilidisainer' ) + '"></textarea>' +
			'<div class="wmd-updates-actions">' +
			'<button type="button" class="button wmd-import-run">' + __( 'Import the pasted JSON', 'wonom-meilidisainer' ) + '</button>' +
			'</div>' +

			'<p class="wmd-hint">' + __( 'The logo still points at the source shop\'s media library — in another shop it is worth uploading it again. Payment instructions carry over by their ID; if the target shop has other payment gateways, those rows are simply left unused.', 'wonom-meilidisainer' ) + '</p>' +
			'</div></details>';
	}

	function updatesPanelHtml() {
		if ( ! cfg.canUpdate ) {
			return backupPanelHtml() + '<div class="wmd-intro">' + __( 'Setting up updates needs permission to update plugins.', 'wonom-meilidisainer' ) + '</div>';
		}

		var u = state.updates;
		var isGithub = u.source === 'github';
		var isJson = u.source === 'json';

		var status;
		if ( u.source === 'off' ) {
			status = '<span class="wmd-status">' + __( 'Automatic updates are switched off.', 'wonom-meilidisainer' ) + '</span>';
		} else if ( ! u.remote ) {
			status = '<span class="wmd-status is-warn">' + __( 'Could not get a version from the source. Check the repository name, the release and the token.', 'wonom-meilidisainer' ) + '</span>';
		} else if ( u.remote === u.current ) {
			status = '<span class="wmd-status is-ok">' + __( 'Everything is up to date — installed ', 'wonom-meilidisainer' ) + '' + esc( u.current ) + '' + __( ', source ', 'wonom-meilidisainer' ) + '' + esc( u.remote ) + '.</span>';
		} else {
			status = '<span class="wmd-status is-new">' + __( 'Available: ', 'wonom-meilidisainer' ) + '<strong>' + esc( u.remote ) + '</strong>' + __( ' (installed ', 'wonom-meilidisainer' ) + '' + esc( u.current ) + ').</span>';
		}

		var canInstall = u.remote && u.remote !== u.current;

		var html = backupPanelHtml();

		html += '<details class="wmd-group" open><summary>' + __( 'Automatic updates', 'wonom-meilidisainer' ) + '</summary><div class="wmd-group-body">';
		html += '<p class="wmd-hint">' + __( 'The plugin is not on WordPress.org, so updates come straight from your GitHub releases. WordPress shows the update notice on the ordinary Plugins page too.', 'wonom-meilidisainer' ) + '</p>';

		html += '<div class="wmd-field"><label class="wmd-label" for="wmd-u-source">' + __( 'Update source', 'wonom-meilidisainer' ) + '</label>' +
			'<select class="wmd-input" id="wmd-u-source">' +
			'<option value="off"' + ( u.source === 'off' ? ' selected' : '' ) + '>' + __( 'Off', 'wonom-meilidisainer' ) + '</option>' +
			'<option value="github"' + ( isGithub ? ' selected' : '' ) + '>' + __( 'GitHub release', 'wonom-meilidisainer' ) + '</option>' +
			'<option value="json"' + ( isJson ? ' selected' : '' ) + '>' + __( 'Own JSON manifest', 'wonom-meilidisainer' ) + '</option>' +
			'</select></div>';

		html += '<div class="wmd-field" data-when="github"' + ( isGithub ? '' : ' hidden' ) + '>' +
			'<label class="wmd-label" for="wmd-u-repo">' + __( 'GitHub repository', 'wonom-meilidisainer' ) + '</label>' +
			'<input type="text" class="wmd-input" id="wmd-u-repo" value="' + esc( u.repo ) + '" placeholder="' + __( 'user/repository', 'wonom-meilidisainer' ) + '" /></div>';

		html += '<div class="wmd-field" data-when="github"' + ( isGithub ? '' : ' hidden' ) + '>' +
			'<label class="wmd-label" for="wmd-u-token">' + __( 'Access token', 'wonom-meilidisainer' ) + '</label>' +
			'<input type="password" class="wmd-input" id="wmd-u-token" value="' + esc( u.token ) + '" placeholder="' + __( 'only for a private repository', 'wonom-meilidisainer' ) + '" autocomplete="off" /></div>';

		html += '<div class="wmd-field" data-when="json"' + ( isJson ? '' : ' hidden' ) + '>' +
			'<label class="wmd-label" for="wmd-u-json">' + __( 'Manifest address', 'wonom-meilidisainer' ) + '</label>' +
			'<input type="text" class="wmd-input" id="wmd-u-json" value="' + esc( u.json ) + '" placeholder="https://…/update.json" /></div>';

		html += '<div class="wmd-updates-actions">' +
			'<button type="button" class="button button-primary wmd-u-save">' + __( 'Save the source', 'wonom-meilidisainer' ) + '</button> ' +
			'<button type="button" class="button wmd-u-check">' + __( 'Check now', 'wonom-meilidisainer' ) + '</button>' +
			'</div>';

		html += '<div class="wmd-update-status">' + status + '</div>';

		if ( canInstall ) {
			html += '<div class="wmd-updates-actions">' +
				'<button type="button" class="button button-primary wmd-u-install">' + __( 'Update now to version ', 'wonom-meilidisainer' ) + '' + esc( u.remote ) + '</button>' +
				'</div>' +
				'<p class="wmd-hint">' + __( 'Installs the new version right here. The page reloads afterwards; save any unsaved changes first.', 'wonom-meilidisainer' ) + '</p>';
		}

		if ( state.updateLog && state.updateLog.length ) {
			html += '<pre class="wmd-log">' + esc( state.updateLog.join( '\n' ) ) + '</pre>';
		}

		return html + '</div></details>';
	}

	/**
	 * Võtab imporditud failist kujunduse. Lubame nii meie enda ümbrisega faili
	 * kui ka paljast kujundust, sest kuskilt kopeerides võib ümbris kaduda.
	 */
	function readImport( text ) {
		var data = JSON.parse( text );
		var design = ( data && data.design ) ? data.design : data;

		if ( ! design || typeof design !== 'object' || ! design.brand || ! design.emails ) {
			throw new Error( __( 'This is not an Email Designer design file.', 'wonom-meilidisainer' ) );
		}

		return design;
	}

	function applyImport( text ) {
		var design;

		try {
			design = readImport( text );
		} catch ( e ) {
			toast( e.message || __( 'The file could not be read', 'wonom-meilidisainer' ), 'error' );
			return;
		}

		if ( ! window.confirm( __( 'Importing overwrites the whole current design — brand, header, footer, every email and the payment instructions. Continue?', 'wonom-meilidisainer' ) ) ) {
			return;
		}

		// Server puhastab sisendi ja tagastab selle, mis päriselt salvestus.
		post( 'wmd_save', { design: JSON.stringify( design ) } ).then( function ( res ) {
			state.design = normalise( res.design );
			state.selected = null;
			state.dirty = false;
			wcCache = {};
			render();
			toast( __( 'Design imported', 'wonom-meilidisainer' ), 'ok' );
		} ).catch( function ( err ) {
			toast( err || __( 'Import failed', 'wonom-meilidisainer' ), 'error' );
		} );
	}

	function bindBackup() {
		var exportBtn = root.querySelector( '.wmd-export' );

		if ( exportBtn ) {
			exportBtn.addEventListener( 'click', function () {
				var blob = new Blob( [ JSON.stringify( exportPayload(), null, 2 ) ], { type: 'application/json' } );
				var url = URL.createObjectURL( blob );
				var a = document.createElement( 'a' );

				a.href = url;
				a.download = exportFileName();
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				setTimeout( function () {
					URL.revokeObjectURL( url );
				}, 1000 );

				toast( __( 'Design downloaded', 'wonom-meilidisainer' ), 'ok' );
			} );
		}

		var pick = root.querySelector( '.wmd-import-pick' );
		var file = root.querySelector( '.wmd-import-file' );

		if ( pick && file ) {
			pick.addEventListener( 'click', function () {
				file.click();
			} );

			file.addEventListener( 'change', function () {
				if ( ! file.files || ! file.files[ 0 ] ) {
					return;
				}

				var reader = new FileReader();
				reader.onload = function () {
					applyImport( String( reader.result ) );
					file.value = '';
				};
				reader.onerror = function () {
					toast( __( 'Reading the file failed', 'wonom-meilidisainer' ), 'error' );
				};
				reader.readAsText( file.files[ 0 ] );
			} );
		}

		var runBtn = root.querySelector( '.wmd-import-run' );
		var paste = root.querySelector( '.wmd-import-json' );

		if ( runBtn && paste ) {
			runBtn.addEventListener( 'click', function () {
				if ( ! paste.value.trim() ) {
					toast( __( 'Paste the JSON first', 'wonom-meilidisainer' ), 'error' );
					return;
				}

				applyImport( paste.value );
			} );
		}
	}

	function bindUpdates() {
		bindBackup();

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
				toast( __( 'Saved', 'wonom-meilidisainer' ), 'ok' );
			} ).catch( function ( err ) {
				toast( err, 'error' );
			} ).then( function () {
				save.disabled = false;
			} );
		} );

		var install = root.querySelector( '.wmd-u-install' );
		if ( install ) {
			install.addEventListener( 'click', function () {
				if ( state.dirty && ! window.confirm( __( 'You have unsaved changes. Updating reloads the page and they will be lost. Continue?', 'wonom-meilidisainer' ) ) ) {
					return;
				}

				install.disabled = true;
				install.textContent = __( 'Installing…', 'wonom-meilidisainer' );

				post( 'wmd_update_now', {} ).then( function ( res ) {
					state.dirty = false;
					state.updateLog = res.log || [];

					if ( res.updated ) {
						toast( res.message + ' ' + __( 'Reloading the page…', 'wonom-meilidisainer' ), 'ok' );
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
					install.textContent = __( 'Try again', 'wonom-meilidisainer' );
				} );
			} );
		}

		var check = root.querySelector( '.wmd-u-check' );
		check.addEventListener( 'click', function () {
			check.disabled = true;
			check.textContent = __( 'Checking…', 'wonom-meilidisainer' );
			post( 'wmd_save_updates', payload() ).then( function () {
				return post( 'wmd_check_update', {} );
			} ).then( function ( res ) {
				state.updates.current = res.current;
				state.updates.remote = res.remote;
				state.updates.source = source.value;
				render();
				toast( res.remote ? ( res.newer ? __( 'Update ', 'wonom-meilidisainer' ) + res.remote + ' ' + __( 'is available', 'wonom-meilidisainer' ) : __( 'Everything is up to date', 'wonom-meilidisainer' ) ) : __( 'The source did not respond', 'wonom-meilidisainer' ), res.remote ? 'ok' : 'error' );
			} ).catch( function ( err ) {
				toast( err, 'error' );
			} ).then( function () {
				check.disabled = false;
				check.textContent = __( 'Check now', 'wonom-meilidisainer' );
			} );
		} );
	}

	/* ------------------------------------------------------ parem paneel */

	function inspectorHtml() {
		var block = findBlock( state.selected );

		if ( ! block ) {
			return '<div class="wmd-inspector-empty">' +
				'<h3>' + __( 'Nothing selected', 'wonom-meilidisainer' ) + '</h3>' +
				'<p>' + __( 'Click a block in the preview, or pick one from the list on the left, to make its settings appear here.', 'wonom-meilidisainer' ) + '</p>' +
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
				'<summary>' + __( 'Visibility', 'wonom-meilidisainer' ) + '' + ( chosen.length ? ' · ' + chosen.length : '' ) + '</summary>' +
				'<div class="wmd-group-body">' +
				'<p class="wmd-hint">' + __( 'Unticked = always show. Tick the payment methods for which the block goes into the email — that way bank transfer instructions can go only to bank transfer orders.', 'wonom-meilidisainer' ) + '</p>' +
				gwKeys.map( function ( id ) {
					var on = chosen.indexOf( id ) !== -1;
					return '<label class="wmd-check"><input type="checkbox" data-pay="' + esc( id ) + '"' + ( on ? ' checked' : '' ) + ' /> ' +
						esc( gateways[ id ] ) + ' <code>' + esc( id ) + '</code></label>';
				} ).join( '' ) +
				'</div></details>';
		}

		html += '<div class="wmd-inspector-foot">' +
			'<button type="button" class="button" data-dup="' + esc( state.selected.zone ) + '|' + esc( block.id ) + '">' + __( 'Duplicate block', 'wonom-meilidisainer' ) + '</button> ' +
			'<button type="button" class="button button-link-delete" data-del="' + esc( state.selected.zone ) + '|' + esc( block.id ) + '">' + __( 'Delete', 'wonom-meilidisainer' ) + '</button>' +
			'</div>';

		return html;
	}

	/* ----------------------------------------------------------- raamistik */

	function render() {
		var tabs = [
			[ 'brand', __( 'Brand', 'wonom-meilidisainer' ) ],
			[ 'header', __( 'Header', 'wonom-meilidisainer' ) ],
			[ 'footer', __( 'Footer', 'wonom-meilidisainer' ) ],
			[ 'emails', __( 'Emails', 'wonom-meilidisainer' ) ],
			[ 'payments', __( 'Payment methods', 'wonom-meilidisainer' ) ],
			[ 'vars', __( 'Variables', 'wonom-meilidisainer' ) ],
			[ 'updates', __( 'Settings', 'wonom-meilidisainer' ) ],
		].map( function ( t ) {
			return '<button type="button" class="wmd-tab' + ( state.tab === t[ 0 ] ? ' is-active' : '' ) + '" data-tab="' + t[ 0 ] + '">' + t[ 1 ] + '</button>';
		} ).join( '' );

		var panel = '';
		if ( state.tab === 'brand' ) {
			panel = brandPanelHtml();
		} else if ( state.tab === 'header' ) {
			panel = '<div class="wmd-intro">' + __( 'The header is the same at the top of every email.', 'wonom-meilidisainer' ) + '</div>' + blockListHtml( 'header', '', '' );
		} else if ( state.tab === 'footer' ) {
			panel = '<div class="wmd-intro">' + __( 'The footer is the same at the bottom of every email.', 'wonom-meilidisainer' ) + '</div>' + blockListHtml( 'footer', '', '' );
		} else if ( state.tab === 'payments' ) {
			panel = paymentsPanelHtml();
		} else if ( state.tab === 'vars' ) {
			panel = varsPanelHtml();
		} else if ( state.tab === 'updates' ) {
			panel = updatesPanelHtml();
		} else {
			panel = emailsPanelHtml();
		}

		var previewOpts = emailIds.map( function ( id ) {
			return '<option value="' + esc( id ) + '"' + ( id === state.email ? ' selected' : '' ) + '>' + esc( cfg.emails[ id ].label ) + '</option>';
		} ).join( '' );

		// Tellimuse valik on ainult neil meilidel, mis tellimuse pealt käivadki.
		var orders = usesOrder() ? ( cfg.orders || [] ) : [];
		var orderPick = '';

		if ( orders.length ) {
			orderPick = '<select class="wmd-input wmd-order-pick" title="' + __( 'Which order to fill the preview with', 'wonom-meilidisainer' ) + '">' +
				'<option value="0"' + ( state.order ? '' : ' selected' ) + '>' + __( 'Latest order in the shop', 'wonom-meilidisainer' ) + '</option>' +
				orders.map( function ( o ) {
					return '<option value="' + esc( o.id ) + '"' + ( String( o.id ) === String( state.order ) ? ' selected' : '' ) + '>' + esc( o.label ) + '</option>';
				} ).join( '' ) +
				'</select>';
		}

		root.innerHTML = '' +
			'<div class="wmd-bar">' +
			'<div class="wmd-bar-left"><span class="wmd-logo">Meilidisainer</span>' +
			'<span class="wmd-dirty" ' + ( state.dirty ? '' : 'hidden' ) + '>' + esc( __( 'Unsaved changes', 'wonom-meilidisainer' ) ) + '</span></div>' +
			'<div class="wmd-bar-mid">' +
			'<select class="wmd-input wmd-preview-pick" title="' + __( 'What to show in the preview', 'wonom-meilidisainer' ) + '">' + previewOpts + '</select>' +
			orderPick +
			'<div class="wmd-segs wmd-device">' +
			'<button type="button" class="wmd-seg' + ( state.device === 'desktop' ? ' is-active' : '' ) + '" data-device="desktop">' + __( 'Desktop', 'wonom-meilidisainer' ) + '</button>' +
			'<button type="button" class="wmd-seg' + ( state.device === 'mobile' ? ' is-active' : '' ) + '" data-device="mobile">' + __( 'Mobile', 'wonom-meilidisainer' ) + '</button>' +
			'</div></div>' +
			'<div class="wmd-bar-right">' +
			'<label class="wmd-switch wmd-switch-inline" title="' + __( 'Whether the design applies to real emails', 'wonom-meilidisainer' ) + '"><input type="checkbox" class="wmd-enabled"' + ( state.enabled ? ' checked' : '' ) + ' /><span></span>' + __( 'Design on', 'wonom-meilidisainer' ) + '</label>' +
			'<button type="button" class="button wmd-test">' + __( 'Send test email', 'wonom-meilidisainer' ) + '</button>' +
			'<button type="button" class="button button-primary wmd-save">' + __( 'Save', 'wonom-meilidisainer' ) + '</button>' +
			'<button type="button" class="button-link wmd-reset" title="' + __( 'Reset the design', 'wonom-meilidisainer' ) + '">' + __( 'Reset', 'wonom-meilidisainer' ) + '</button>' +
			'</div></div>' +
			'<div class="wmd-body">' +
			'<aside class="wmd-left"><div class="wmd-tabs">' + tabs + '</div><div class="wmd-panel">' + panel + '</div></aside>' +
			'<main class="wmd-canvas' + ( state.device === 'mobile' ? ' is-mobile' : '' ) + '">' +
			wcNoteHtml() +
			'<div class="wmd-frame-wrap"><iframe class="wmd-frame" title="' + __( 'Email preview', 'wonom-meilidisainer' ) + '"></iframe></div></main>' +
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
			// emailSettings() teeb puuduva kirje täiskujul — nii ei sõltu
			// tulemus sellest, millist välja juhtuti esimesena muutma.
			emailSettings()[ key ] = value;
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
		root.querySelectorAll( '.wmd-panel [data-scope]:not(.wmd-cols):not(.wmd-pairs):not(.wmd-cards-edit), .wmd-right [data-scope]:not(.wmd-cols):not(.wmd-pairs):not(.wmd-cards-edit)' ).forEach( function ( input ) {
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

		// Silt-väärtus ridade toimeti.
		root.querySelectorAll( '.wmd-pairs' ).forEach( function ( list ) {
			var key = list.getAttribute( 'data-key' );

			function rows() {
				var block = findBlock( state.selected );
				return block ? block.props[ key ] : null;
			}

			function bindField( attr, prop ) {
				list.querySelectorAll( '[' + attr + ']' ).forEach( function ( input ) {
					input.addEventListener( 'input', function () {
						var r = rows();
						if ( r ) {
							r[ parseInt( input.getAttribute( attr ), 10 ) ][ prop ] = input.value;
							markDirty();
							schedulePreview();
						}
					} );
				} );
			}

			bindField( 'data-pair-label', 'label' );
			bindField( 'data-pair-value', 'value' );
			bindField( 'data-pair-link', 'link' );

			function commit() {
				markDirty();
				render();
				invalidatePreview();
			}

			list.querySelectorAll( '[data-pair-del]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var r = rows();
					if ( r ) {
						r.splice( parseInt( btn.getAttribute( 'data-pair-del' ), 10 ), 1 );
						commit();
					}
				} );
			} );

			function move( index, delta ) {
				var r = rows();
				var to = index + delta;

				if ( ! r || to < 0 || to >= r.length ) {
					return;
				}

				r.splice( to, 0, r.splice( index, 1 )[ 0 ] );
				commit();
			}

			list.querySelectorAll( '[data-pair-up]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					move( parseInt( btn.getAttribute( 'data-pair-up' ), 10 ), -1 );
				} );
			} );

			list.querySelectorAll( '[data-pair-down]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					move( parseInt( btn.getAttribute( 'data-pair-down' ), 10 ), 1 );
				} );
			} );

			var add = list.parentNode.querySelector( '.wmd-pair-add' );
			if ( add ) {
				add.addEventListener( 'click', function () {
					var r = rows();
					if ( r ) {
						r.push( { label: '', value: '', link: '' } );
						commit();
					}
				} );
			}
		} );

		// Pildikaartide toimeti.
		root.querySelectorAll( '.wmd-cards-edit' ).forEach( function ( list ) {
			var key = list.getAttribute( 'data-key' );
			var tools = list.parentNode;

			function rows() {
				var block = findBlock( state.selected );
				return block ? block.props[ key ] : null;
			}

			function commit() {
				markDirty();
				render();
				invalidatePreview();
			}

			function bindField( attr, prop ) {
				list.querySelectorAll( '[' + attr + ']' ).forEach( function ( input ) {
					input.addEventListener( 'input', function () {
						var r = rows();

						if ( r && r[ parseInt( input.getAttribute( attr ), 10 ) ] ) {
							r[ parseInt( input.getAttribute( attr ), 10 ) ][ prop ] = input.value;
							markDirty();
							schedulePreview();
						}
					} );
				} );
			}

			bindField( 'data-card-label', 'label' );
			bindField( 'data-card-link', 'link' );
			bindField( 'data-card-image', 'image' );

			list.querySelectorAll( '[data-card-media]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var index = parseInt( btn.getAttribute( 'data-card-media' ), 10 );

					openMediaWith( function ( url ) {
						var r = rows();

						if ( r && r[ index ] ) {
							r[ index ].image = url;
							commit();
						}
					} );
				} );
			} );

			list.querySelectorAll( '[data-card-del]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var r = rows();

					if ( r ) {
						r.splice( parseInt( btn.getAttribute( 'data-card-del' ), 10 ), 1 );
						commit();
					}
				} );
			} );

			function move( index, delta ) {
				var r = rows();
				var to = index + delta;

				if ( ! r || to < 0 || to >= r.length ) {
					return;
				}

				r.splice( to, 0, r.splice( index, 1 )[ 0 ] );
				commit();
			}

			list.querySelectorAll( '[data-card-up]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					move( parseInt( btn.getAttribute( 'data-card-up' ), 10 ), -1 );
				} );
			} );

			list.querySelectorAll( '[data-card-down]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					move( parseInt( btn.getAttribute( 'data-card-down' ), 10 ), 1 );
				} );
			} );

			var add = tools.querySelector( '.wmd-card-add' );

			if ( add ) {
				add.addEventListener( 'click', function () {
					var r = rows();

					if ( r ) {
						r.push( { image: '', link: '', label: '' } );
						commit();
					}
				} );
			}

			// Tootekategooriad WooCommerce'ist: pilt, link ja nimi korraga.
			var cats = tools.querySelector( '.wmd-card-cats' );
			var box = tools.querySelector( '.wmd-card-catlist' );

			if ( cats && box ) {
				cats.addEventListener( 'click', function () {
					if ( state.catsOpen ) {
						state.catsOpen = false;
						render();
						return;
					}

					// Kategooriad küsime serverist ainult esimesel korral.
					if ( state.cats ) {
						state.catsOpen = true;
						render();
						return;
					}

					cats.disabled = true;
					cats.textContent = __( 'Loading…', 'wonom-meilidisainer' );

					post( 'wmd_categories', {} ).then( function ( res ) {
						state.cats = ( res && res.items ) || [];
						state.catsOpen = true;
						render();
					} ).catch( function ( err ) {
						cats.disabled = false;
						cats.textContent = __( 'Load product categories', 'wonom-meilidisainer' );
						toast( err, 'error' );
					} );
				} );

				box.querySelectorAll( '[data-cat]' ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function () {
						var cat = state.cats[ parseInt( btn.getAttribute( 'data-cat' ), 10 ) ];
						var r = rows();

						if ( ! r || ! cat ) {
							return;
						}

						// Esimene tühi koht ära, muidu lisame lõppu.
						var slot = null;

						for ( var i = 0; i < r.length; i++ ) {
							if ( ! r[ i ].image && ! r[ i ].label && ! r[ i ].link ) {
								slot = r[ i ];
								break;
							}
						}

						if ( ! slot ) {
							slot = { image: '', link: '', label: '' };
							r.push( slot );
						}

						slot.image = cat.image || '';
						slot.link = cat.url || '';
						slot.label = cat.name || '';
						commit();
					} );
				} );
			}
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

		// „Lae WooCommerce'i sisu plokkidena" — küsib sisu wrap-režiimi kujul,
		// sest täisrežiimis WooCommerce'i sisu ei renderdatagi.
		var refill = root.querySelector( '.wmd-refill' );

		if ( refill ) {
			refill.addEventListener( 'click', function () {
				takeOverFromWc( { button: refill } );
			} );
		}

		// Meili kokkupaneku režiim.
		root.querySelectorAll( '.wmd-modes [data-mode]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var mode = btn.getAttribute( 'data-mode' );
				var e = emailSettings();

				if ( e.mode === mode ) {
					return;
				}

				// Täisrežiim algab sellest, mida WooCommerce praegu saadab —
				// plokkidena, mida saab kohe edasi muuta. Sisu tuuakse serverist
				// alles nüüd, seega on kaasas ka kõik lingid.
				if ( mode === 'full' && ! e.body.length ) {
					takeOverFromWc( { fallback: true } );
					return;
				}

				e.mode = mode;
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
				if ( index === -1 || ! window.confirm( __( 'Delete this block?', 'wonom-meilidisainer' ) ) ) {
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
		bindVars();
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
					var url = window.prompt( __( 'Link address', 'wonom-meilidisainer' ), 'https://' );
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
				area.dispatchEvent( new Event( 'input', { bubbles: true } ) );
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

					// Laseme väljal endal teatada — nii jõuab väärtus kohale
					// olenemata sellest, kes selle välja sidus.
					target.dispatchEvent( new Event( 'input', { bubbles: true } ) );
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
				if ( ! window.confirm( __( 'Reset the whole design to its defaults? This cannot be undone.', 'wonom-meilidisainer' ) ) ) {
					return;
				}
				post( 'wmd_reset', {} ).then( function ( res ) {
					state.design = normalise( res.design );
					state.selected = null;
					state.dirty = false;
					wcCache = {};
					render();
					toast( __( 'Saved', 'wonom-meilidisainer' ), 'ok' );
				} );
			} );
		}

		var enabled = root.querySelector( '.wmd-enabled' );
		if ( enabled ) {
			enabled.addEventListener( 'change', function () {
				state.enabled = enabled.checked;
				post( 'wmd_toggle', { on: enabled.checked ? 1 : 0 } ).then( function () {
					toast( enabled.checked ? __( 'The design applies to emails', 'wonom-meilidisainer' ) : __( 'The design is switched off', 'wonom-meilidisainer' ), 'ok' );
				} );
			} );
		}

		var test = root.querySelector( '.wmd-test' );
		if ( test ) {
			test.addEventListener( 'click', function () {
				var to = window.prompt( __( 'Where should the test email go?', 'wonom-meilidisainer' ), cfg.testTo || '' );
				if ( ! to ) {
					return;
				}
				test.disabled = true;
				test.textContent = __( 'Sending…', 'wonom-meilidisainer' );
				post( 'wmd_test_email', {
					to: to,
					email: state.email,
					// Ilma selleta saatis server viimase tellimuse pealt, mitte
					// selle, mida ülaribal vaatad.
					order: orderParam(),
					design: JSON.stringify( state.design ),
				} ).then( function ( res ) {
					// Server salvestab kujunduse enne saatmist, et postkasti
					// jõuaks täpselt see, mida ekraanil näed. Ütleme seda ka.
					state.dirty = false;
					render();
					toast( __( 'The design was saved and the test email went to ', 'wonom-meilidisainer' ) + res.to, 'ok' );
				} ).catch( function ( err ) {
					toast( err, 'error' );
				} ).then( function () {
					test.disabled = false;
					test.textContent = __( 'Send test email', 'wonom-meilidisainer' );
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
			toast( __( 'Saved', 'wonom-meilidisainer' ), 'ok' );
		} ).catch( function ( err ) {
			toast( err || __( 'Saving failed', 'wonom-meilidisainer' ), 'error' );
		} ).then( function () {
			if ( btn ) {
				btn.disabled = false;
			}
		} );
	}

	/**
	 * Avab meediateegi ja annab valitud pildi aadressi tagasikutsele.
	 *
	 * @param {Function} done Saab pildi aadressi.
	 */
	function openMediaWith( done ) {
		if ( ! window.wp || ! window.wp.media ) {
			var url = window.prompt( __( 'Image address', 'wonom-meilidisainer' ), '' );

			if ( url ) {
				done( url );
			}

			return;
		}

		var frame = window.wp.media( {
			title: __( 'Choose image', 'wonom-meilidisainer' ),
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', function () {
			done( frame.state().get( 'selection' ).first().toJSON().url );
		} );

		frame.open();
	}

	function openMedia( scope, key ) {
		openMediaWith( function ( url ) {
			setValue( scope, key, url );
			render();
		} );
	}

	/**
	 * Tekst base64-kujule, UTF-8 kaudu (btoa üksi täpitähtedega ei tule toime).
	 *
	 * @param {string} str Tekst.
	 * @return {string} Base64.
	 */
	function toBase64( str ) {
		var bytes = new TextEncoder().encode( str );
		var bin = '';

		// Kaupa, sest String.fromCharCode.apply suure massiiviga jookseb kokku.
		for ( var i = 0; i < bytes.length; i += 8192 ) {
			bin += String.fromCharCode.apply( null, bytes.subarray( i, i + 8192 ) );
		}

		return btoa( bin );
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( key ) {
			// Kujundus läheb base64-kujul. „Oma HTML" plokis võib olla <script>
			// või <style>; serveri tulemüür blokeerib sellise POST-i sageli
			// enne WordPressi ja salvestus katkeks 403-ga.
			if ( 'design' === key ) {
				body.append( 'design_b64', toBase64( String( data[ key ] ) ) );
				return;
			}

			body.append( key, data[ key ] );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} ).then( function ( r ) {
			return r.text().then( function ( text ) {
				var json = null;

				try {
					json = JSON.parse( text );
				} catch ( err ) {
					json = null;
				}

				// Kui vastus ei ole JSON, ei jõudnud päring WordPressini —
				// tavaliselt on vahele tulnud serveri tulemüür. Ütleme seda
				// otse, mitte ei näita kasutajale JSON-i parsimisviga.
				if ( ! json ) {
					throw 403 === r.status
						? __( 'The server firewall blocked the request (HTTP 403). Ask your host to allow this address.', 'wonom-meilidisainer' )
						: __( 'The server replied unexpectedly (HTTP ', 'wonom-meilidisainer' ) + r.status + __( '). Check the server error log.', 'wonom-meilidisainer' );
				}

				if ( ! json.success ) {
					throw ( json.data && json.data.message ) || __( 'Saving failed', 'wonom-meilidisainer' );
				}

				return json.data;
			} );
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
