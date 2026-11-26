/**
 * Excel Importer - import screens: start, match columns, preview, importing, result; and the Pro delete preview.
 *
 * Talks to the plugin's AJAX actions named in window.cexlApp (localized by PHP). Every row is still imported by the
 * import action, one row per request, with the same fields the old screen sent.
 *
 * @author WPFactory
 */
( function ( $ ) {
	'use strict';

	var cfg = window.cexlApp;
	if ( ! cfg || ! document.querySelector( '.cexl-app' ) ) {
		return;
	}
	var t = cfg.i18n;
	var state = {};

	function esc( text ) {
		return String( null === text || undefined === text ? '' : text ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function fmt( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( text ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var v = pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
			return undefined === v ? '' : v;
		} );
	}

	function $q( sel ) {
		return $( '.cexl-app ' + sel );
	}

	function show( screen ) {
		$q( '[data-screen]' ).each( function () {
			this.hidden = this.getAttribute( 'data-screen' ) !== screen;
		} );
		window.scrollTo( 0, 0 );
	}

	function banner( $where, kind, text ) {
		$where.html( text ? '<div class="banner ' + kind + '" role="' + ( 'bad' === kind ? 'alert' : 'status' ) + '">' + esc( text ) + '</div>' : '' );
	}

	function post( data, opts ) {
		return $.ajax( $.extend( { url: cfg.ajax, type: 'POST', data: data }, opts || {} ) );
	}

	function failText( xhr ) {
		var r = xhr && xhr.responseJSON;
		if ( r && r.data && r.data.message ) {
			return r.data.message;
		}
		if ( xhr && ( '-1' === xhr.responseText || 403 === xhr.status ) ) {
			return t.expired;
		}
		return t.failed;
	}

	/* ------------------------------------------------------------------ 1 Start */

	var $drop = $q( '.drop[data-drop="import"]' );
	var $file = $q( '#cexl-file' );

	function postType() {
		return $q( 'input[name="cexl_type"]:checked' ).val() || '';
	}

	function upload( file ) {
		if ( ! file ) {
			return;
		}
		var ext = ( file.name.split( '.' ).pop() || '' ).toLowerCase();
		if ( -1 === cfg.extensions.indexOf( ext ) ) {
			banner( $q( '#cexl-start-msg' ), 'bad', fmt( t.badFile, cfg.extensions.join( ', ' ) ) );
			return;
		}
		var data = new FormData();
		data.append( 'action', cfg.actions.upload );
		data.append( 'nonce', cfg.nonces.upload );
		data.append( 'post_type', postType() );
		data.append( 'file', file );
		banner( $q( '#cexl-start-msg' ), 'info', t.reading );
		$q( '#cexl-choose' ).addClass( 'is-busy' );
		post( data, { processData: false, contentType: false } ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				banner( $q( '#cexl-start-msg' ), 'bad', r && r.data && r.data.message ? r.data.message : t.failed );
				return;
			}
			banner( $q( '#cexl-start-msg' ), '', '' );
			state = r.data;
			state.type = postType();
			buildMapping();
			show( 'map' );
		} ).fail( function ( xhr ) {
			banner( $q( '#cexl-start-msg' ), 'bad', failText( xhr ) );
		} ).always( function () {
			$q( '#cexl-choose' ).removeClass( 'is-busy' );
			$file.val( '' );
		} );
	}

	$file.on( 'change', function () {
		upload( this.files && this.files[ 0 ] );
	} );
	$drop.on( 'dragover dragenter', function ( e ) {
		e.preventDefault();
		$drop.addClass( 'is-over' );
	} ).on( 'dragleave drop', function ( e ) {
		e.preventDefault();
		$drop.removeClass( 'is-over' );
		if ( 'drop' === e.type ) {
			var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
			upload( files && files[ 0 ] );
		}
	} );
	$q( 'input[name="cexl_type"]' ).on( 'change', function () {
		var type = postType();
		$q( '[data-for-type]' ).each( function () {
			this.hidden = -1 === this.getAttribute( 'data-for-type' ).split( ' ' ).indexOf( type );
		} );
	} ).filter( ':checked' ).trigger( 'change' );

	/* --------------------------------------------------------- 2 Match columns */

	function fieldLabel( key ) {
		var label = key;
		$.each( state.fields, function ( i, group ) {
			if ( group.fields[ key ] ) {
				label = group.fields[ key ];
			}
		} );
		return label;
	}

	function options( selected ) {
		var html = '<option value="">' + esc( t.skipColumn ) + '</option>';
		$.each( state.fields, function ( i, group ) {
			html += '<optgroup label="' + esc( group.label ) + '">';
			$.each( group.fields, function ( key, label ) {
				html += '<option value="' + esc( key ) + '"' + ( key === selected ? ' selected' : '' ) + '>' + esc( label ) + '</option>';
			} );
			html += '</optgroup>';
		} );
		return html;
	}

	function chipFor( letter, field ) {
		if ( ! field ) {
			return '<span class="chip plain">' + esc( t.skipped ) + '</span>';
		}
		if ( state.touched && state.touched[ letter ] ) {
			return '<span class="chip ok">' + esc( t.chosen ) + '</span>';
		}
		if ( 'saved' === state.suggestion.source ) {
			return '<span class="chip ok">' + esc( t.saved ) + '</span>';
		}
		return state.suggestion.sure && state.suggestion.sure[ field ] ? '<span class="chip ok">' + esc( t.matched ) + '</span>' : '<span class="chip warn">' + esc( t.check ) + '</span>';
	}

	function buildMapping() {
		var byColumn = {};
		state.touched = {};
		$.each( state.suggestion.map || {}, function ( field, letter ) {
			byColumn[ letter ] = field;
		} );
		var rows = '';
		$.each( state.headers, function ( letter, header ) {
			var field = byColumn[ letter ] || '';
			var name = '' === String( header ).trim() ? fmt( t.column, letter ) : header;
			var low = field && 'saved' !== state.suggestion.source && ! ( state.suggestion.sure && state.suggestion.sure[ field ] );
			rows += '<tr data-letter="' + esc( letter ) + '"><td><b>' + esc( name ) + '</b> <span class="muted small">' + esc( letter ) + '</span></td>' +
				'<td class="muted">' + esc( String( state.first[ letter ] || '' ).slice( 0, 60 ) ) + '</td>' +
				'<td><select aria-label="' + esc( fmt( t.fieldFor, name ) ) + '"' + ( low ? ' class="low"' : '' ) + '>' + options( field ) + '</select></td>' +
				'<td class="nowrap" data-chip>' + chipFor( letter, field ) + '</td></tr>';
		} );
		$q( '#cexl-map-rows' ).html( rows );
		$q( '#cexl-map-eyebrow' ).text( fmt( t.stepTwo, state.file_name, state.rows ) );
		banner( $q( '#cexl-map-msg' ), 'saved' === state.suggestion.source ? 'ok' : '', 'saved' === state.suggestion.source ? t.usedSaved : '' );

		// Defaults of the "How to update" card.
		var isProduct = 'product' === state.type;
		$q( '[data-only-product]' ).each( function () {
			this.hidden = ! isProduct;
		} );
		var mapped = currentMap();
		var match = 'title';
		if ( isProduct && mapped._sku && 'mapped' === cfg.updateDefault ) {
			match = 'sku';
		}
		$q( 'input[name="cexl_match"][value="' + match + '"]' ).prop( 'checked', true );
		$q( 'input[name="cexl_update"][value="' + ( 'mapped' === cfg.updateDefault ? 'mapped' : 'all' ) + '"]' ).prop( 'checked', true );
		$q( 'input[name="cexl_missing"][value="create"]' ).prop( 'checked', true );
		$q( '#cexl-attributes-only' ).prop( 'checked', false );
		updateOptions();
	}

	function currentMap() {
		var map = {};
		$q( '#cexl-map-rows tr' ).each( function () {
			var field = $( this ).find( 'select' ).val();
			if ( field ) {
				map[ field ] = this.getAttribute( 'data-letter' );
			}
		} );
		return map;
	}

	$q( '#cexl-map-rows' ).on( 'change', 'select', function () {
		var $tr = $( this ).closest( 'tr' );
		var letter = $tr.attr( 'data-letter' );
		var field = $( this ).val();
		state.touched[ letter ] = true;
		$( this ).removeClass( 'low' );
		// One column per field: a field chosen here is taken away from any other column.
		if ( field ) {
			$q( '#cexl-map-rows tr' ).not( $tr ).each( function () {
				var $s = $( this ).find( 'select' );
				if ( $s.val() === field ) {
					$s.val( '' ).removeClass( 'low' );
					$( this ).find( '[data-chip]' ).html( chipFor( this.getAttribute( 'data-letter' ), '' ) );
				}
			} );
		}
		$tr.find( '[data-chip]' ).html( chipFor( letter, field ) );
		updateOptions();
	} );

	$q( '[data-map-options]' ).on( 'change', 'input', updateOptions );

	function updateOptions() {
		var update = $q( 'input[name="cexl_update"]:checked' ).val();
		$q( '#cexl-remember' ).prop( 'hidden', 'new' === update || update === ( 'mapped' === cfg.updateDefault ? 'mapped' : 'all' ) );
		$q( '[data-when-matching]' ).prop( 'hidden', 'new' === update );
		$q( '#cexl-to-preview' ).prop( 'disabled', !! mappingProblem() );
		banner( $q( '#cexl-map-problem' ), 'warn', mappingProblem() );
	}

	function mappingProblem() {
		var map = currentMap();
		var match = $q( 'input[name="cexl_match"]:checked' ).val();
		if ( ! map.post_title ) {
			return t.needTitle;
		}
		if ( 'new' !== $q( 'input[name="cexl_update"]:checked' ).val() ) {
			if ( 'sku' === match && ! map._sku ) {
				return t.needSku;
			}
			if ( 'id' === match && ! map.cexl_id ) {
				return t.needId;
			}
		}
		return '';
	}

	// The request the import action expects for every row: every field the screen offers (empty when not used).
	function request() {
		var map = currentMap();
		var update = $q( 'input[name="cexl_update"]:checked' ).val();
		var match = $q( 'input[name="cexl_match"]:checked' ).val();
		var data = {
			post_type: state.type,
			cexl_file: state.token,
			cexl_file_name: state.file_name,
			finalupload: state.total,
			secNonce: cfg.nonces.process,
			import: 'import',
			cexl_save_mapping: 1,
			cexl_report: 1,
			cexl_missing: $q( 'input[name="cexl_missing"]:checked' ).val()
		};
		$.each( state.post_fields, function ( i, key ) {
			data[ key ] = '';
		} );
		$.each( state.fields, function ( i, group ) {
			$.each( group.fields, function ( key ) {
				data[ key ] = '';
			} );
		} );
		$.each( map, function ( field, letter ) {
			data[ field ] = letter;
		} );
		if ( 'new' === update ) {
			data.add_always_new = 'yes';
		} else {
			data.cexl_update = update;
		}
		if ( 'sku' === match || 'id' === match ) {
			data.cexl_match = match;
			data.cexl_match_column = 'sku' === match ? map._sku : map.cexl_id;
		}
		if ( cfg.undo ) {
			data.cexl_undo = 1; // Pro keeps what each row changes, for Undo.
		}
		if ( $q( '#cexl-attributes-only' ).is( ':checked' ) && 'product' === state.type ) {
			data.product_type = 'simple';
		}
		return data;
	}

	$q( '[data-go]' ).on( 'click', function ( e ) {
		e.preventDefault();
		show( this.getAttribute( 'data-go' ) );
	} );

	// Match with AI (WPFactory AI credits): fills the selects; the merchant checks them before the preview.
	$q( '#cexl-ai-map' ).on( 'click', function () {
		var $btn = $( this ).addClass( 'is-busy' ).prop( 'disabled', true );
		banner( $q( '#cexl-map-msg' ), 'info', t.aiWorking );
		post( { action: cfg.actions.aiMap, nonce: cfg.ai && cfg.ai.nonce, cexl_file: state.token, post_type: state.type } ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				var d = r && r.data ? r.data : {};
				$q( '#cexl-map-msg' ).html( '<div class="banner warn" role="alert">' + esc( d.message || t.failed ) +
					( d.connect ? ' <a href="' + esc( d.connect ) + '">' + esc( t.aiConnect ) + '</a>' : '' ) + '</div>' );
				return;
			}
			var byColumn = {};
			$.each( r.data.map, function ( field, letter ) {
				byColumn[ letter ] = field;
			} );
			state.suggestion = { source: 'ai', map: r.data.map, sure: r.data.sure };
			state.touched = {};
			$q( '#cexl-map-rows tr' ).each( function () {
				var letter = this.getAttribute( 'data-letter' );
				var field = byColumn[ letter ] || '';
				var sure = field && r.data.sure[ field ];
				$( this ).find( 'select' ).val( field ).toggleClass( 'low', !! field && ! sure );
				$( this ).find( '[data-chip]' ).html( ! field ? chipFor( letter, '' ) : ( sure ? '<span class="chip ok">' + esc( t.aiMatch ) + '</span>' : '<span class="chip warn">' + esc( t.check ) + '</span>' ) );
			} );
			banner( $q( '#cexl-map-msg' ), 'ok', t.aiDone );
			updateOptions();
		} ).fail( function ( xhr ) {
			banner( $q( '#cexl-map-msg' ), 'bad', failText( xhr ) );
		} ).always( function () {
			$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
		} );
	} );

	/* ------------------------------------------------------------- 3 Preview */

	function valueText( value ) {
		return '' === String( value ) ? '<em>' + esc( t.empty ) + '</em>' : esc( String( value ).length > 80 ? String( value ).slice( 0, 80 ) + '...' : value );
	}

	$q( '#cexl-to-preview' ).on( 'click', function () {
		if ( mappingProblem() ) {
			return;
		}
		var $btn = $( this ).addClass( 'is-busy' ).prop( 'disabled', true );
		var data = $.extend( request(), { action: cfg.actions.preview } );
		post( data ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				banner( $q( '#cexl-map-problem' ), 'bad', r && r.data && r.data.message ? r.data.message : t.failed );
				return;
			}
			renderPreview( r.data );
			show( 'preview' );
		} ).fail( function ( xhr ) {
			banner( $q( '#cexl-map-problem' ), 'bad', failText( xhr ) );
		} ).always( function () {
			$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
		} );
	} );

	function renderPreview( p ) {
		var c = p.counts;
		$q( '#cexl-tile-update' ).text( c.update );
		$q( '#cexl-tile-create' ).text( c.create );
		$q( '#cexl-tile-skip' ).text( c.skip );
		$q( '#cexl-tile-check' ).text( c.warnings );
		banner( $q( '#cexl-preview-msg' ), 'warn', c.warnings ? t.someCheck : '' );
		state.previewRows = {};
		var html = '';
		var limit = 300;
		$.each( p.rows, function ( i, row ) {
			state.previewRows[ row.row ] = row;
			if ( i >= limit ) {
				return;
			}
			var what = '';
			$.each( row.changes, function ( j, ch ) {
				what += '<span class="change">' + esc( ch.label ) + ' ' + ( 'create' === row.action ? '' : '<del>' + valueText( ch.old ) + '</del> &rarr; ' ) + '<ins>' + valueText( ch.new ) + '</ins></span>';
			} );
			if ( ! what ) {
				what = '<span class="muted">' + esc( row.note || ( 'same' === row.action ? t.noChange : '' ) ) + '</span>';
			}
			$.each( row.warnings, function ( j, w ) {
				what += '<br><span class="chip warn">' + esc( w ) + '</span>';
			} );
			var chip = { update: [ 'info', t.update ], create: [ 'ok', t.create ], same: [ 'plain', t.same ], skip: [ 'plain', t.skip ] }[ row.action ] || [ 'plain', row.action ];
			if ( row.warnings.length ) {
				chip = [ 'warn', t.check ];
			}
			html += '<tr data-row="' + esc( row.row ) + '"><td class="nowrap">' + esc( row.row ) + '</td><td><b>' + esc( row.title || t.noTitle ) + '</b>' +
				( 'create' === row.action ? ' <span class="muted small">' + esc( t.isNew ) + '</span>' : '' ) +
				( row.id ? '<br><span class="muted small">ID ' + esc( row.id ) + '</span>' : '' ) + '</td><td>' + what + '</td>' +
				'<td class="nowrap"><span class="chip ' + chip[ 0 ] + '">' + esc( chip[ 1 ] ) + '</span></td></tr>';
		} );
		if ( p.rows.length > limit ) {
			html += '<tr><td colspan="4" class="muted">' + esc( fmt( t.moreRows, p.rows.length - limit ) ) + '</td></tr>';
		}
		if ( ! p.rows.length ) {
			html = '<tr><td colspan="4" class="muted">' + esc( t.noRows ) + '</td></tr>';
		}
		$q( '#cexl-preview-rows' ).html( html );
		$q( '#cexl-start-import' ).prop( 'disabled', ! p.rows.length );
		var withChanges = $.grep( p.rows, function ( r ) {
			return r.changes.length;
		} ).length;
		$q( '#cexl-ai-check' ).prop( 'hidden', ! withChanges ).prop( 'disabled', false );
		$q( '#cexl-ai-check-cost' ).text( fmt( t.aiAbout, Math.max( 1, Math.ceil( Math.min( withChanges, 200 ) / 50 ) ) * 2 ) );
	}

	// Check with AI: flags unusual planned changes on the preview; nothing is changed.
	$q( '#cexl-ai-check' ).on( 'click', function () {
		var $btn = $( this ).addClass( 'is-busy' ).prop( 'disabled', true );
		banner( $q( '#cexl-preview-msg' ), 'info', t.aiChecking );
		post( $.extend( request(), { action: cfg.actions.aiCheck, nonce: cfg.ai && cfg.ai.nonce } ) ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				var d = r && r.data ? r.data : {};
				$q( '#cexl-preview-msg' ).html( '<div class="banner warn" role="alert">' + esc( d.message || t.failed ) +
					( d.connect ? ' <a href="' + esc( d.connect ) + '">' + esc( t.aiConnect ) + '</a>' : '' ) + '</div>' );
				$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
				return;
			}
			var rows = {};
			$.each( r.data.flags, function ( i, f ) {
				var $tr = $q( '#cexl-preview-rows tr[data-row="' + f.row + '"]' );
				$tr.find( 'td' ).eq( 2 ).append( '<br><span class="chip warn"><span class="spark" aria-hidden="true">&#10022;</span>&nbsp;' + esc( f.message ) + '</span>' );
				$tr.find( 'td' ).eq( 3 ).html( '<span class="chip warn">' + esc( t.check ) + '</span>' );
				rows[ f.row ] = true;
			} );
			var n = Object.keys( rows ).length;
			banner( $q( '#cexl-preview-msg' ), n ? 'warn' : 'ok', n ? fmt( t.aiFlags, n ) : t.aiNoFlags );
			$btn.removeClass( 'is-busy' );
		} ).fail( function ( xhr ) {
			banner( $q( '#cexl-preview-msg' ), 'bad', failText( xhr ) );
			$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
		} );
	} );

	/* ------------------------------------------------------- 4 Importing, 5 Result */

	var run = {};

	$q( '#cexl-start-import' ).on( 'click', function () {
		var update = $q( 'input[name="cexl_update"]:checked' ).val();
		if ( $q( '#cexl-remember-box' ).is( ':checked' ) && 'new' !== update ) {
			post( { action: cfg.actions.setDefault, nonce: cfg.nonces.setDefault, update: update } );
			cfg.updateDefault = update;
		}
		run = { data: $.extend( request(), { action: cfg.actions.import } ), row: 2, done: 0, paused: false, stopped: false, failed: 0, log: [] };
		$q( '#cexl-run-eyebrow' ).text( state.file_name );
		$q( '#cexl-run-list' ).empty();
		$q( '#cexl-pause' ).text( t.pause ).prop( 'disabled', false );
		$q( '#cexl-stop' ).prop( 'disabled', false );
		banner( $q( '#cexl-run-msg' ), '', '' );
		show( 'run' );
		progress();
		next();
	} );

	function progress() {
		var total = Math.max( 1, state.total - 1 );
		var pct = Math.round( run.done / total * 100 );
		$q( '#cexl-run-count' ).text( fmt( t.rowOf, Math.min( run.done + 1, total ), total ) );
		$q( '#cexl-run-bar' ).attr( 'aria-valuenow', pct ).find( 'i' ).css( 'width', pct + '%' );
	}

	function logRow( rowNo, html ) {
		var row = state.previewRows && state.previewRows[ rowNo ];
		var $p = $( '<div>' ).html( html ).find( 'p' ).first();
		var cls = $p.attr( 'class' ) || '';
		var chip = 'skipped' === cls ? [ 'plain', t.skipped ] : ( 'success' === cls ? [ 'ok', t.created ] : ( 'warning' === cls && ! $p.attr( 'style' ) ? [ 'info', t.updated ] : [ 'bad', t.failedRow ] ) );
		if ( 'bad' === chip[ 0 ] ) {
			run.failed++;
		}
		var title = row ? row.title : $p.text();
		$q( '#cexl-run-list' ).prepend( '<li><span>' + esc( fmt( t.rowN, rowNo ) ) + ' &middot; ' + esc( title ) + '</span><span class="chip ' + chip[ 0 ] + '">' + esc( chip[ 1 ] ) + '</span></li>' );
		$q( '#cexl-run-list li' ).slice( 8 ).remove();
	}

	function next() {
		if ( run.stopped ) {
			return finish( true );
		}
		if ( run.paused ) {
			return;
		}
		if ( run.row > state.total ) {
			return finish( false );
		}
		var rowNo = run.row;
		run.data.start = rowNo;
		post( run.data, { dataType: 'text' } ).done( function ( html ) {
			if ( '-1' === html || '0' === html ) {
				run.stopped = true;
				banner( $q( '#cexl-run-msg' ), 'bad', t.expired );
				return;
			}
			logRow( rowNo, html );
		} ).fail( function () {
			run.failed++;
			$q( '#cexl-run-list' ).prepend( '<li><span>' + esc( fmt( t.rowN, rowNo ) ) + '</span><span class="chip bad">' + esc( t.failedRow ) + '</span></li>' );
		} ).always( function () {
			run.row++;
			run.done++;
			progress();
			next();
		} );
	}

	$q( '#cexl-pause' ).on( 'click', function () {
		run.paused = ! run.paused;
		$( this ).text( run.paused ? t.resume : t.pause );
		if ( ! run.paused ) {
			next();
		}
	} );

	$q( '#cexl-stop' ).on( 'click', function () {
		run.stopped = true;
		$( this ).prop( 'disabled', true );
		if ( run.paused ) {
			finish( true );
		}
	} );

	function finish( stopped ) {
		if ( run.finished ) {
			return;
		}
		run.finished = true;
		$q( '#cexl-result-eyebrow' ).text( state.file_name );
		$q( '#cexl-result-title' ).text( stopped ? t.stoppedTitle : t.doneTitle );
		$q( '#cexl-view-items' ).attr( 'href', cfg.editUrl + encodeURIComponent( state.type || 'post' ) );
		$q( '#cexl-report-csv' ).attr( 'href', cfg.reportCsv + '&report=' + encodeURIComponent( state.token ) );
		$q( '#cexl-undo' ).attr( 'data-undo', state.token ).prop( 'hidden', ! cfg.undo );
		banner( $q( '#cexl-undo-msg' ), '', '' );
		post( { action: cfg.actions.report, nonce: cfg.nonces.report, report: state.token } ).done( function ( r ) {
			var c = r && r.success ? r.data.counts : null;
			$q( '#cexl-res-updated' ).text( c ? c.updated : '-' );
			$q( '#cexl-res-created' ).text( c ? c.created : '-' );
			$q( '#cexl-res-skipped' ).text( c ? c.skipped : '-' );
			$q( '#cexl-res-failed' ).text( c ? c.failed + run.failed : run.failed );
			$q( '#cexl-report-csv' ).prop( 'hidden', ! c );
			describeOffer( r && r.success ? r.data.describe : [] );
		} ).always( function () {
			show( 'result' );
		} );
	}

	// Write missing descriptions with AI: drafts are shown for review; Save writes one item.
	var drafts = [];

	function describeOffer( ids ) {
		drafts = ids && cfg.ai && cfg.ai.available ? ids : [];
		$q( '#cexl-ai-drafts' ).empty();
		banner( $q( '#cexl-ai-describe-msg' ), '', '' );
		$q( '#cexl-ai-describe' ).prop( 'hidden', ! drafts.length );
		$q( '#cexl-ai-write' ).prop( 'disabled', false ).removeClass( 'is-busy' );
		$q( '#cexl-ai-describe-title' ).text( fmt( t.aiDescribeN, drafts.length ) );
		$q( '#cexl-ai-estimate' ).text( fmt( t.aiAbout, drafts.length * 3 ) );
	}

	$q( '#cexl-ai-write' ).on( 'click', function () {
		var $btn = $( this ).addClass( 'is-busy' ).prop( 'disabled', true );
		var queue = drafts.slice();
		var n = 0;
		( function next() {
			if ( ! queue.length ) {
				$btn.removeClass( 'is-busy' );
				banner( $q( '#cexl-ai-describe-msg' ), 'info', t.aiReview );
				return;
			}
			var id = queue.shift();
			n++;
			banner( $q( '#cexl-ai-describe-msg' ), 'info', fmt( t.aiWriting, n, drafts.length ) );
			post( { action: cfg.actions.aiDescribe, nonce: cfg.ai.nonce, id: id } ).done( function ( r ) {
				if ( ! r || ! r.success ) {
					var d = r && r.data ? r.data : {};
					$q( '#cexl-ai-describe-msg' ).html( '<div class="banner warn" role="alert">' + esc( d.message || t.failed ) +
						( d.connect ? ' <a href="' + esc( d.connect ) + '">' + esc( t.aiConnect ) + '</a>' : '' ) + '</div>' );
					$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
					return;
				}
				var x = r.data;
				$q( '#cexl-ai-drafts' ).append( '<li data-draft="' + esc( x.id ) + '" style="display:block"><b>' + esc( x.title ) + '</b>' +
					'<textarea class="cexl-draft-text" rows="5" style="width:100%;margin:8px 0" aria-label="' + esc( x.title ) + '">' + esc( x.description ) + '</textarea>' +
					( x.short ? '<label class="small">' + esc( t.aiShort ) + ' <input type="text" class="cexl-draft-short" style="width:100%" value="' + esc( x.short ) + '"></label>' : '' ) +
					'<span class="row-actions" style="margin-top:8px"><button type="button" class="btn btn-primary" data-draft-save>' + esc( t.aiSave ) + '</button>' +
					'<button type="button" class="btn btn-text" data-draft-skip>' + esc( t.aiSkip ) + '</button></span></li>' );
				next();
			} ).fail( function ( xhr ) {
				banner( $q( '#cexl-ai-describe-msg' ), 'bad', failText( xhr ) );
				$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
			} );
		}() );
	} );

	$q( '#cexl-ai-drafts' ).on( 'click', '[data-draft-save]', function () {
		var $li = $( this ).closest( 'li' );
		var $b = $( this ).addClass( 'is-busy' ).prop( 'disabled', true );
		post( { action: cfg.actions.aiApply, nonce: cfg.ai.nonce, id: $li.attr( 'data-draft' ), description: $li.find( '.cexl-draft-text' ).val(), short: $li.find( '.cexl-draft-short' ).val() || '' } ).done( function ( r ) {
			if ( r && r.success ) {
				$li.find( '.row-actions' ).replaceWith( '<span class="chip ok">' + esc( t.aiSaved ) + '</span>' );
				$li.find( 'textarea, input' ).prop( 'readonly', true );
			} else {
				$b.removeClass( 'is-busy' ).prop( 'disabled', false );
			}
		} ).fail( function () {
			$b.removeClass( 'is-busy' ).prop( 'disabled', false );
		} );
	} ).on( 'click', '[data-draft-skip]', function () {
		var $li = $( this ).closest( 'li' );
		$li.find( '.row-actions' ).replaceWith( '<span class="chip plain">' + esc( t.aiSkipped ) + '</span>' );
		$li.find( 'textarea, input' ).prop( 'readonly', true );
	} );

	$q( '#cexl-again' ).on( 'click', function ( e ) {
		e.preventDefault();
		state = {};
		show( 'start' );
	} );

	/* -------------------------------------------------------------- Pro: undo */

	$( document ).on( 'click', '.cexl-app [data-undo]', function ( e ) {
		e.preventDefault();
		var token = this.getAttribute( 'data-undo' );
		if ( ! token || ! cfg.actions.undo || ! window.confirm( t.undoConfirm ) ) {
			return;
		}
		var $btn = $( this ).addClass( 'is-busy' ).prop( 'disabled', true );
		var $msg = $q( '#cexl-undo-msg' );
		( function step() {
			post( { action: cfg.actions.undo, nonce: cfg.nonces.undo, report: token } ).done( function ( r ) {
				if ( ! r || ! r.success ) {
					banner( $msg, 'bad', r && r.data && r.data.message ? r.data.message : t.failed );
					$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
					return;
				}
				if ( r.data.left ) {
					banner( $msg, 'info', fmt( t.undoing, r.data.done ) );
					step();
					return;
				}
				banner( $msg, 'ok', t.undoDone );
				$btn.replaceWith( '<span class="chip plain">' + esc( t.undoDone ) + '</span>' );
			} ).fail( function ( xhr ) {
				banner( $msg, 'bad', failText( xhr ) );
				$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
			} );
		}() );
	} );

	/* ---------------------------------------------------- Pro: delete preview */

	var del = {};
	var $delFile = $q( '#cexl-delete-file' );

	function deleteUpload( file ) {
		if ( ! file ) {
			return;
		}
		var data = new FormData();
		data.append( 'action', cfg.actions.deletePreview );
		data.append( '_wpnonce', cfg.nonces.delete );
		data.append( 'cexl_delete_type', $q( '#cexl-delete-type' ).val() || '' );
		data.append( 'post_taxonomy', $q( '#cexl-delete-tax' ).val() || '' );
		data.append( 'has_term', $q( '#cexl-delete-term' ).val() || '' );
		data.append( 'file', file );
		banner( $q( '#cexl-delete-msg' ), 'info', t.reading );
		post( data, { processData: false, contentType: false } ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				banner( $q( '#cexl-delete-msg' ), 'bad', r && r.data && r.data.message ? r.data.message : t.failed );
				return;
			}
			banner( $q( '#cexl-delete-msg' ), '', '' );
			del = { file: file, data: r.data };
			renderDelete( r.data );
			show( 'delete-preview' );
		} ).fail( function ( xhr ) {
			banner( $q( '#cexl-delete-msg' ), 'bad', failText( xhr ) );
		} ).always( function () {
			$delFile.val( '' );
		} );
	}

	$delFile.on( 'change', function () {
		deleteUpload( this.files && this.files[ 0 ] );
	} );
	$q( '.drop[data-drop="delete"]' ).on( 'dragover dragenter', function ( e ) {
		e.preventDefault();
		$( this ).addClass( 'is-over' );
	} ).on( 'dragleave drop', function ( e ) {
		e.preventDefault();
		$( this ).removeClass( 'is-over' );
		if ( 'drop' === e.type ) {
			var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
			deleteUpload( files && files[ 0 ] );
		}
	} );

	function renderDelete( d ) {
		var html = '';
		$.each( d.rows, function ( i, row ) {
			var chip = { forever: [ 'bad', t.deleteForGood ], trash: [ 'warn', t.trash ], skip: [ 'plain', t.skip ] }[ row.action ];
			html += '<tr><td class="nowrap">' + esc( row.row ) + '</td><td>' + ( '' === row.value ? '<span class="muted">' + esc( t.empty ) + '</span>' : esc( row.value ) ) + '</td>' +
				'<td>' + ( row.id ? '<b>' + esc( row.title ) + '</b> <span class="muted small">' + esc( row.type_label ) + ' &middot; ID ' + esc( row.id ) + '</span>' : '' ) +
				( row.note ? ( row.id ? '<br>' : '' ) + '<span class="muted">' + esc( row.note ) + '</span>' : '' ) + '</td>' +
				'<td class="nowrap"><span class="chip ' + chip[ 0 ] + '">' + esc( chip[ 1 ] ) + '</span></td></tr>';
		} );
		$q( '#cexl-delete-rows' ).html( html || '<tr><td colspan="4" class="muted">' + esc( t.noRows ) + '</td></tr>' );
		$q( '#cexl-delete-eyebrow' ).text( fmt( t.deleteEyebrow, del.file.name ) );
		$q( '#cexl-del-trash' ).text( d.counts.trash );
		$q( '#cexl-del-forever' ).text( d.counts.forever );
		$q( '#cexl-del-skip' ).text( d.counts.skip );
		$q( '#cexl-delete-go' ).prop( 'disabled', ! ( d.counts.trash + d.counts.forever ) );
		banner( $q( '#cexl-delete-warn' ), d.counts.forever ? 'bad' : 'info', d.counts.forever ? t.foreverWarn : t.trashInfo );
	}

	$q( '#cexl-delete-go' ).on( 'click', function () {
		if ( ! window.confirm( t.confirmDelete ) ) {
			return;
		}
		$( this ).prop( 'disabled', true );
		del.row = 2;
		del.processed = 0;
		del.total = del.data.total;
		$q( '#cexl-delete-run' ).prop( 'hidden', false );
		deleteNext();
	} );

	function deleteNext() {
		if ( del.row > del.total ) {
			banner( $q( '#cexl-delete-warn' ), 'ok', fmt( t.deleteDone, del.processed ) );
			$q( '#cexl-delete-run' ).prop( 'hidden', true );
			return;
		}
		var data = new FormData();
		data.append( 'action', 'deleteProducts' );
		data.append( 'deleteProducts', '1' );
		data.append( '_wpnonce', cfg.nonces.delete );
		data.append( 'start', del.row );
		data.append( 'processed', del.processed );
		data.append( 'finalupload', del.total );
		data.append( 'cexl_delete_type', del.data.only_type || '' );
		data.append( 'post_taxonomy', del.data.taxonomy || '' );
		data.append( 'has_term', del.data.term || '' );
		data.append( 'file', del.file );
		$q( '#cexl-delete-count' ).text( fmt( t.rowOf, del.row - 1, del.total - 1 ) );
		post( data, { processData: false, contentType: false, dataType: 'text' } ).done( function ( html ) {
			var m = /class='processed'>(\d+)</.exec( html );
			if ( m ) {
				del.processed = parseInt( m[ 1 ], 10 );
			}
		} ).always( function () {
			del.row++;
			deleteNext();
		} );
	}
}( jQuery ) );
