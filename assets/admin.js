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
		varQuery: '',
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
			wcCache[ key ] = { pending: false, html: '', css: '', items: [], totals: [], fields: [], ctx: {}, parts: {}, addr: null, why: 'Ei saanud WooCommerce\'i sisu kätte.' };
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
			html += '<div class="wmd-tags-head">Selle tellimuse väljad</div>';
			fields.forEach( function ( f ) {
				html += rowFn( 'meta:' + f.key, 'Tellimuse väli', f.sample );
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
				'<input type="text" class="wmd-input wmd-pair-label" data-pair-label="' + index + '" value="' + esc( row.label ) + '" placeholder="Silt" />' +
				'<span class="wmd-colmove">' +
				'<button type="button" data-pair-up="' + index + '" title="Üles"' + ( index === 0 ? ' disabled' : '' ) + '>↑</button>' +
				'<button type="button" data-pair-down="' + index + '" title="Alla"' + ( index === rows.length - 1 ? ' disabled' : '' ) + '>↓</button>' +
				'<button type="button" data-pair-del="' + index + '" title="Kustuta rida">✕</button>' +
				'</span></div>' +
				'<div class="wmd-inline">' +
				'<input type="text" class="wmd-input wmd-small" id="' + esc( vid ) + '" data-pair-value="' + index + '" value="' + esc( row.value ) + '" placeholder="Väärtus või {{muutuja}}" />' +
				tagPicker( vid ) +
				'</div>' +
				'<input type="text" class="wmd-input wmd-small" data-pair-link="' + index + '" value="' + esc( row.link || '' ) + '" placeholder="Link (valikuline), {{value}} = väärtus" />' +
				'</li>';
		} ).join( '' );

		return '<div class="wmd-field"><label class="wmd-label">' + esc( field.label ) + '</label>' +
			'<ul class="wmd-pairs" data-scope="' + esc( scope ) + '" data-key="' + esc( key ) + '">' + items + '</ul>' +
			'<button type="button" class="wmd-mini wmd-pair-add">+ Lisa rida</button>' +
			'<p class="wmd-hint">Väärtuse saab valida { } nupu alt — seal on ka selle tellimuse päris väljad, nagu jälgimiskood.</p></div>';
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

		html += '<div class="wmd-field wmd-field-toggle"><label class="wmd-switch">' +
			'<input type="checkbox" data-scope="email" data-key="additional"' + ( e.additional ? ' checked' : '' ) + ' />' +
			'<span></span>WooCommerce\'i lisatekst kirja lõpus</label>' +
			'<p class="wmd-hint">Selle teksti leiad WooCommerce → Seaded → Meilid alt. Seal ei saa seda tühjendada — tühi väli asendatakse vaiketekstiga („Thanks for shopping with us."). Siit saab selle päriselt välja lülitada.</p></div>';

		html += '<div class="wmd-field"><label class="wmd-label">Kuidas meil kokku pannakse</label>' +
			'<div class="wmd-segs wmd-modes">' +
			'<button type="button" class="wmd-seg' + ( full ? '' : ' is-active' ) + '" data-mode="wrap">WooCommerce\'i sisu ümber</button>' +
			'<button type="button" class="wmd-seg' + ( full ? ' is-active' : '' ) + '" data-mode="full">Terve meil ise</button>' +
			'</div></div>';

		if ( full ) {
			html += '<div class="wmd-intro wmd-warn">Selles režiimis ei kasutata WooCommerce\'i sisumalli. Kõik, mis meilis on, tuleb allolevatest plokkidest — ka tellimuse tabel ja aadressid.</div>';
			html += '<div class="wmd-updates-actions"><button type="button" class="button wmd-refill">Lae WooCommerce\'i sisu plokkidena</button></div>' +
				'<p class="wmd-hint">Võtab selle meili praeguse WooCommerce\'i sisu plokkideks lahti ja asendab allolevad plokid. Kasulik, kui tahad alustada uuesti WooCommerce\'i tekstist.</p>';
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
	 * Võtab WooCommerce'i renderdatud sisu lahti meie plokkideks.
	 *
	 * Nii ei alusta „terve meil ise" tühjalt lehelt, vaid samast kirjast, mille
	 * WooCommerce praegu saadab — edasi saab seda tavaliste plokkidena muuta.
	 *
	 * @param {string} html WooCommerce'i sisuosa.
	 * @return {Array} Plokid.
	 */
	function blocksFromWcHtml( html ) {
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
						push( 'text', { html: raw } );
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
							text: text( child ),
							size: tag === 'h1' ? 'lg' : ( tag === 'h2' ? 'md' : 'sm' ),
						} );
					}
					return;
				}

				if ( tag === 'p' ) {
					if ( text( child ) ) {
						push( 'text', { html: child.innerHTML.trim() } );
					}
					return;
				}

				if ( tag === 'ul' || tag === 'ol' ) {
					push( 'html', { code: child.outerHTML } );
					return;
				}

				if ( tag === 'table' ) {
					if ( isAddressTable( child ) ) {
						push( 'addresses', {} );
					} else if ( isOrderTable( child ) ) {
						push( 'order_items', {} );
						push( 'order_totals', {} );
					} else if ( text( child ) ) {
						push( 'html', { code: child.outerHTML } );
					}
					return;
				}

				// Mähised (div, section) — vaatame nende sisse.
				if ( tag === 'div' || tag === 'section' || tag === 'header' || tag === 'footer' ) {
					walk( child );
					return;
				}

				if ( text( child ) ) {
					push( 'html', { code: child.outerHTML } );
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
			done( blocksFromWcHtml( res.html || '' ) );
		} ).catch( function ( err ) {
			toast( err || 'WooCommerce\'i sisu ei õnnestunud laadida', 'error' );
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

		if ( e.body.length && ! window.confirm( 'Selles meilis on juba ' + e.body.length + ' plokki. Asendan need WooCommerce\'i praeguse sisuga?' ) ) {
			return;
		}

		var btn   = opts.button || null;
		var label = btn ? btn.textContent : '';

		if ( btn ) {
			btn.disabled = true;
			btn.textContent = 'Laen…';
		}

		loadWcBlocks( function ( middle ) {
			if ( ! middle.length && ! opts.fallback ) {
				toast( 'WooCommerce\'i sisu ei õnnestunud plokkideks võtta', 'error' );

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
				toast( 'WooCommerce\'i sisu ei saanud — alustame vaikeplokkidest', 'error' );
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
			toast( body.length + ' plokki laaditud — WooCommerce\'i oma tekst enam kirja ei lähe', 'ok' );
		} );
	}

	/* ------------------------------------------------------- muutujad */

	function varsPanelHtml() {
		var order = currentOrder();

		var source = order
			? 'Väärtus on valitud tellimuse pealt (<strong>' + esc( order.label ) + '</strong>).'
			: ( usesOrder()
				? 'Väärtus on valitud tellimuse pealt.'
				: 'See kiri ei käi tellimuse pealt, seega tellimuse muutujatel siin väärtust ei ole.' );

		var html = '<div class="wmd-intro">Kõik muutujad, mida kirjas kasutada saab. ' + source +
			' Klõps kopeerib muutuja — saad selle kleepida ükskõik millisesse välja, ka „Oma HTML" plokki või lisa-CSS-i.</div>';

		html += '<div class="wmd-field"><input type="text" class="wmd-input wmd-var-search" placeholder="Otsi muutujat…" value="' + esc( state.varQuery ) + '" /></div>';

		var rows = tagListHtml( function ( key, label, value ) {
			return '<button type="button" class="wmd-var" data-copy="{{' + esc( key ) + '}}">' +
				'<code>{{' + esc( key ) + '}}</code>' +
				'<em>' + esc( label ) + '</em>' +
				'<span>' + esc( value || '—' ) + '</span></button>';
		}, state.varQuery );

		html += '<div class="wmd-vars">' + ( rows || '<p class="wmd-hint">Midagi ei leitud.</p>' ) + '</div>';

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
					}, state.varQuery ) || '<p class="wmd-hint">Midagi ei leitud.</p>';
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
					toast( 'Kopeeritud: ' + text, 'ok' );
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
						toast( 'Kopeerimine ei õnnestunud', 'error' );
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

		return '<details class="wmd-group" open><summary>Kujunduse eksport ja import</summary><div class="wmd-group-body">' +
			'<p class="wmd-hint">Kogu kujundus — bränd, päis, jalus, kõik meilid ja makseviiside juhised — ühes failis. Teises poes impordid selle ja oled kohe sama seadistusega.</p>' +

			'<div class="wmd-updates-actions">' +
			'<button type="button" class="button button-primary wmd-export">Laadi kujundus alla</button>' +
			'</div>' +

			'<details class="wmd-group"><summary>Näita JSON-i</summary><div class="wmd-group-body">' +
			'<textarea class="wmd-input wmd-textarea wmd-mono wmd-export-json" rows="8" readonly>' + esc( json ) + '</textarea>' +
			'</div></details>' +

			'<hr class="wmd-hr" />' +

			'<p class="wmd-hint"><strong>Import kirjutab kogu praeguse kujunduse üle.</strong> Vali fail või kleebi JSON.</p>' +
			'<div class="wmd-updates-actions">' +
			'<button type="button" class="button wmd-import-pick">Vali fail…</button>' +
			'<input type="file" class="wmd-import-file" accept="application/json,.json" hidden />' +
			'</div>' +
			'<textarea class="wmd-input wmd-textarea wmd-mono wmd-import-json" rows="4" placeholder="…või kleebi JSON siia"></textarea>' +
			'<div class="wmd-updates-actions">' +
			'<button type="button" class="button wmd-import-run">Impordi kleebitud JSON</button>' +
			'</div>' +

			'<p class="wmd-hint">Logo viitab endiselt lähtepoe meediateegile — teises poes tasub see uuesti üles laadida. Makseviiside juhised kanduvad üle nende tunnuse järgi; kui sihtpoes on teised makselahendused, jäävad need read lihtsalt kasutamata.</p>' +
			'</div></details>';
	}

	function updatesPanelHtml() {
		if ( ! cfg.canUpdate ) {
			return backupPanelHtml() + '<div class="wmd-intro">Uuenduste seadistamiseks on vaja õigust pluginaid uuendada.</div>';
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

		var html = backupPanelHtml();

		html += '<details class="wmd-group" open><summary>Automaatsed uuendused</summary><div class="wmd-group-body">';
		html += '<p class="wmd-hint">Plugin ei ole WordPress.org-is, seega uuendused tulevad otse sinu GitHubi väljalasetest. WordPress näitab uuendusteadet ka tavalisel Pluginad-lehel.</p>';

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
			throw new Error( 'See ei ole Meilidisaineri kujundusfail.' );
		}

		return design;
	}

	function applyImport( text ) {
		var design;

		try {
			design = readImport( text );
		} catch ( e ) {
			toast( e.message || 'Faili ei õnnestunud lugeda', 'error' );
			return;
		}

		if ( ! window.confirm( 'Import kirjutab kogu praeguse kujunduse üle — bränd, päis, jalus, kõik meilid ja makseviiside juhised. Jätkan?' ) ) {
			return;
		}

		// Server puhastab sisendi ja tagastab selle, mis päriselt salvestus.
		post( 'wmd_save', { design: JSON.stringify( design ) } ).then( function ( res ) {
			state.design = normalise( res.design );
			state.selected = null;
			state.dirty = false;
			wcCache = {};
			render();
			toast( 'Kujundus imporditud', 'ok' );
		} ).catch( function ( err ) {
			toast( err || 'Import ebaõnnestus', 'error' );
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

				toast( 'Kujundus laaditi alla', 'ok' );
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
					toast( 'Faili lugemine ebaõnnestus', 'error' );
				};
				reader.readAsText( file.files[ 0 ] );
			} );
		}

		var runBtn = root.querySelector( '.wmd-import-run' );
		var paste = root.querySelector( '.wmd-import-json' );

		if ( runBtn && paste ) {
			runBtn.addEventListener( 'click', function () {
				if ( ! paste.value.trim() ) {
					toast( 'Kleebi kõigepealt JSON', 'error' );
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
			[ 'vars', 'Muutujad' ],
			[ 'updates', 'Seaded' ],
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
		root.querySelectorAll( '.wmd-panel [data-scope]:not(.wmd-cols):not(.wmd-pairs), .wmd-right [data-scope]:not(.wmd-cols):not(.wmd-pairs)' ).forEach( function ( input ) {
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
					// Ilma selleta saatis server viimase tellimuse pealt, mitte
					// selle, mida ülaribal vaatad.
					order: orderParam(),
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
						? 'Serveri tulemüür blokeeris päringu (HTTP 403). Küsi majutajalt, et see aadress lubataks.'
						: 'Server vastas ootamatult (HTTP ' + r.status + '). Vaata serveri vealogi.';
				}

				if ( ! json.success ) {
					throw ( json.data && json.data.message ) || i18n.saveFailed;
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
