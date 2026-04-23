/**
 * Caricamento multiplo: layout explorer, albero cartelle, drag-drop file e righe.
 *
 * @param {object} cfg Configurazione da wp_localize_script (fpdmkBulk).
 */
(function () {
	'use strict';

	if ( typeof fpdmkBulk === 'undefined' || typeof wp === 'undefined' || ! wp.media || ! wp.template ) {
		return;
	}

	var cfg = fpdmkBulk;
	var i18n = cfg.i18n || {};

	var $pickBtn = document.getElementById( 'fpdmk-bulk-pick' );
	var $submit = document.getElementById( 'fpdmk-bulk-submit' );
	var $tbody = document.querySelector( '#fpdmk-bulk-table tbody' );
	var $table = document.getElementById( 'fpdmk-bulk-table' );
	var $empty = document.getElementById( 'fpdmk-bulk-empty' );
	var $count = document.getElementById( 'fpdmk-bulk-count' );
	var $defLang = document.getElementById( 'fpdmk_bulk_default_language' );
	var $defFold = document.getElementById( 'fpdmk_bulk_default_folder' );
	var $defCats = document.getElementById( 'fpdmk_bulk_default_categories' );
	var $dropzone = document.getElementById( 'fpdmk-bulk-dropzone' );
	var $fileInput = document.getElementById( 'fpdmk-bulk-file-input' );
	var $treeRoot = document.getElementById( 'fpdmk-bulk-tree' );
	var $filterFolder = document.getElementById( 'fpdmk-bulk-filter-folder' );
	var $status = document.getElementById( 'fpdmk-bulk-status' );
	var tpl = wp.template( 'fpdmk-bulk-row' );
	var rowSeq = 0;
	var addedIds = {};
	var frame;
	var dragSourceRow = null;
	var MAX_PARALLEL = 3;
	var selectedFolderId = $defFold ? parseInt( $defFold.value, 10 ) || 0 : 0;

	function setStatus( msg, type ) {
		if ( ! $status ) {
			return;
		}
		$status.textContent = msg || '';
		$status.className = 'fpdmk-bulk-status';
		if ( type === 'error' ) {
			$status.classList.add( 'fpdmk-bulk-status-error' );
		} else if ( type === 'success' ) {
			$status.classList.add( 'fpdmk-bulk-status-success' );
		} else if ( type === 'info' ) {
			$status.classList.add( 'fpdmk-bulk-status-info' );
		}
	}

	function countReadyRows() {
		return $tbody.querySelectorAll( 'tr.fpdmk-bulk-row:not(.fpdmk-bulk-placeholder)' ).length;
	}

	function hasAnyRows() {
		return $tbody.querySelectorAll( 'tr' ).length > 0;
	}

	function refreshUI() {
		var n = countReadyRows();
		$count.textContent = n + ' ' + ( i18n.filesLabel || '' );
		$table.classList.toggle( 'is-hidden', ! hasAnyRows() );
		$empty.classList.toggle( 'is-hidden', hasAnyRows() );
		$submit.disabled = n === 0;
		applyFolderFilter();
		if ( typeof updateBulkbar === 'function' ) {
			updateBulkbar();
		}
	}

	function applyFolderFilter() {
		if ( ! $filterFolder || ! $filterFolder.checked ) {
			$tbody.querySelectorAll( 'tr' ).forEach( function ( tr ) {
				tr.classList.remove( 'is-filtered-out' );
			} );
			return;
		}
		var fid = String( selectedFolderId );
		$tbody.querySelectorAll( 'tr' ).forEach( function ( tr ) {
			var sel = tr.querySelector( 'select[name$="[folder_term]"]' );
			var v = sel ? String( sel.value ) : '0';
			tr.classList.toggle( 'is-filtered-out', v !== fid );
		} );
	}

	function syncTreeSelection( folderId ) {
		selectedFolderId = folderId;
		if ( $defFold ) {
			$defFold.value = String( folderId );
		}
		if ( $treeRoot ) {
			$treeRoot.querySelectorAll( '.fpdmk-tree-node' ).forEach( function ( btn ) {
				var id = parseInt( btn.getAttribute( 'data-folder-id' ), 10 );
				if ( isNaN( id ) ) {
					id = 0;
				}
				btn.classList.toggle( 'is-active', id === folderId );
			} );
		}
		applyFolderFilter();
	}

	function renderTreeNodes( nodes, ul, depth ) {
		nodes.forEach( function ( node ) {
			var li = document.createElement( 'li' );
			li.className = 'fpdmk-tree-item';
			li.setAttribute( 'role', 'none' );

			var row = document.createElement( 'div' );
			row.className = 'fpdmk-tree-row';
			row.style.setProperty( '--fpdmk-tree-depth', String( depth ) );
			row.setAttribute( 'data-depth', String( depth ) );

			var hasKids = node.children && node.children.length > 0;
			var toggle = document.createElement( 'span' );
			toggle.className = 'fpdmk-tree-toggle' + ( hasKids ? '' : ' is-leaf' );
			toggle.setAttribute( 'aria-hidden', 'true' );
			if ( hasKids ) {
				toggle.textContent = '▾';
				toggle.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					var childUl = li.querySelector( ':scope > .fpdmk-tree-children' );
					if ( ! childUl ) {
						return;
					}
					var collapsed = childUl.classList.toggle( 'is-collapsed' );
					toggle.textContent = collapsed ? '▸' : '▾';
				} );
			} else {
				toggle.textContent = '·';
			}

			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'fpdmk-tree-node';
			btn.setAttribute( 'data-folder-id', String( node.id ) );
			btn.setAttribute( 'draggable', 'false' );
			var nameSpan = document.createElement( 'span' );
			nameSpan.className = 'fpdmk-tree-node-name';
			nameSpan.textContent = node.name;
			btn.appendChild( nameSpan );
			var totalCount = typeof node.count_deep === 'number' ? node.count_deep : ( typeof node.count === 'number' ? node.count : 0 );
			if ( totalCount > 0 ) {
				var countEl = document.createElement( 'span' );
				countEl.className = 'fpdmk-tree-node-count';
				countEl.textContent = String( totalCount );
				btn.appendChild( countEl );
			}
			btn.addEventListener( 'click', function () {
				syncTreeSelection( node.id );
			} );
			btn.addEventListener( 'dragover', function ( e ) {
				if ( dragSourceRow ) {
					e.preventDefault();
					e.dataTransfer.dropEffect = 'move';
					btn.classList.add( 'is-drop-target' );
				}
			} );
			btn.addEventListener( 'dragleave', function () {
				btn.classList.remove( 'is-drop-target' );
			} );
			btn.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				btn.classList.remove( 'is-drop-target' );
				if ( ! dragSourceRow ) {
					return;
				}
				var sel = dragSourceRow.querySelector( 'select[name$="[folder_term]"]' );
				if ( sel ) {
					sel.value = String( node.id );
				}
				dragSourceRow = null;
			} );

			row.appendChild( toggle );
			row.appendChild( btn );
			li.appendChild( row );

			if ( hasKids ) {
				var sub = document.createElement( 'ul' );
				sub.className = 'fpdmk-tree-children';
				sub.setAttribute( 'role', 'group' );
				renderTreeNodes( node.children, sub, depth + 1 );
				li.appendChild( sub );
			}
			ul.appendChild( li );
		} );
	}

	function buildFolderTree() {
		if ( ! $treeRoot ) {
			return;
		}
		$treeRoot.innerHTML = '';
		$treeRoot.setAttribute( 'role', 'tree' );

		var rootBtn = document.createElement( 'button' );
		rootBtn.type = 'button';
		rootBtn.className = 'fpdmk-tree-node fpdmk-tree-root-node';
		rootBtn.setAttribute( 'data-folder-id', '0' );
		rootBtn.textContent = i18n.rootFolder || '—';
		rootBtn.addEventListener( 'click', function () {
			syncTreeSelection( 0 );
		} );
		rootBtn.addEventListener( 'dragover', function ( e ) {
			if ( dragSourceRow ) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';
				rootBtn.classList.add( 'is-drop-target' );
			}
		} );
		rootBtn.addEventListener( 'dragleave', function () {
			rootBtn.classList.remove( 'is-drop-target' );
		} );
		rootBtn.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			rootBtn.classList.remove( 'is-drop-target' );
			if ( ! dragSourceRow ) {
				return;
			}
			var sel = dragSourceRow.querySelector( 'select[name$="[folder_term]"]' );
			if ( sel ) {
				sel.value = '0';
			}
			dragSourceRow = null;
		} );
		$treeRoot.appendChild( rootBtn );

		var ul = document.createElement( 'ul' );
		ul.className = 'fpdmk-tree-root-ul';
		ul.setAttribute( 'role', 'group' );
		renderTreeNodes( cfg.folderTree || [], ul, 0 );
		$treeRoot.appendChild( ul );
		syncTreeSelection( selectedFolderId );
	}

	function cloneSelect( sourceSelect, namePrefix, rowId, multiple ) {
		var s = document.createElement( 'select' );
		s.name = 'items[' + rowId + ']' + namePrefix + ( multiple ? '[]' : '' );
		if ( multiple ) {
			s.multiple = true;
			s.size = 3;
		}
		Array.prototype.forEach.call( sourceSelect.options, function ( o ) {
			var opt = document.createElement( 'option' );
			opt.value = o.value;
			opt.textContent = o.textContent;
			if ( o.hasAttribute( 'data-depth' ) ) {
				opt.setAttribute( 'data-depth', o.getAttribute( 'data-depth' ) );
			} else if ( o.value === '0' ) {
				opt.setAttribute( 'data-depth', '0' );
			}
			if ( multiple ) {
				if ( o.selected ) {
					opt.selected = true;
				}
			} else if ( o.selected ) {
				opt.selected = true;
			}
			s.appendChild( opt );
		} );
		return s;
	}

	/**
	 * Immagine miniatura (se disponibile) per la prima colonna.
	 */
	function thumbUrlFromAtt( att ) {
		if ( ! att ) {
			return '';
		}
		if ( att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url ) {
			return att.sizes.thumbnail.url;
		}
		if ( att.media_details && att.media_details.sizes ) {
			var s = att.media_details.sizes;
			if ( s.thumbnail && s.thumbnail.source_url ) {
				return s.thumbnail.source_url;
			}
			if ( s.medium && s.medium.source_url ) {
				return s.medium.source_url;
			}
		}
		var mime = att.mime || att.mime_type || '';
		if ( mime.indexOf( 'image/' ) === 0 ) {
			if ( att.source_url ) {
				return att.source_url;
			}
			if ( att.url ) {
				return att.url;
			}
		}
		return '';
	}

	function addRow( att, preset ) {
		if ( ! att || ! att.id ) {
			return;
		}
		if ( addedIds[ att.id ] ) {
			return;
		}
		addedIds[ att.id ] = true;
		rowSeq++;
		var rowId = 'r' + rowSeq;
		var filename = att.filename || att.title || 'attachment-' + att.id;
		var mime = att.mime || att.mime_type || '';
		var html = tpl( {
			rowId: rowId,
			id: att.id,
			filename: filename,
			mime: mime,
			title: att.title || filename,
			description: att.caption || '',
		} );
		var tmp = document.createElement( 'tbody' );
		tmp.innerHTML = html.trim();
		var tr = tmp.firstChild;
		tr.classList.add( 'fpdmk-bulk-row' );
		tr.setAttribute( 'draggable', 'true' );
		tr.dataset.rowId = rowId;
		var fileCell = tr.querySelector( 'td:first-child' );
		if ( fileCell ) {
			var thumbUrl = thumbUrlFromAtt( att );
			var thumb = document.createElement( 'span' );
			thumb.className = 'fpdmk-bulk-thumb' + ( thumbUrl ? '' : ' is-placeholder' );
			if ( thumbUrl ) {
				var img = document.createElement( 'img' );
				img.src = thumbUrl;
				img.alt = '';
				img.loading = 'lazy';
				thumb.appendChild( img );
			} else {
				var icon = document.createElement( 'span' );
				icon.className = 'dashicons ' + ( mime.indexOf( 'video/' ) === 0 ? 'dashicons-format-video' : 'dashicons-media-default' );
				icon.setAttribute( 'aria-hidden', 'true' );
				thumb.appendChild( icon );
			}
			fileCell.insertBefore( thumb, fileCell.firstChild );
		}
		tr.addEventListener( 'dragstart', function ( e ) {
			dragSourceRow = tr;
			e.dataTransfer.effectAllowed = 'move';
			try {
				e.dataTransfer.setData( 'text/plain', rowId );
			} catch ( err ) {
				/* ignore */
			}
			tr.classList.add( 'is-dragging' );
		} );
		tr.addEventListener( 'dragend', function () {
			tr.classList.remove( 'is-dragging' );
			dragSourceRow = null;
			if ( $treeRoot ) {
				$treeRoot.querySelectorAll( '.is-drop-target' ).forEach( function ( el ) {
					el.classList.remove( 'is-drop-target' );
				} );
			}
			var rootNode = document.querySelector( '.fpdmk-tree-root-node' );
			if ( rootNode ) {
				rootNode.classList.remove( 'is-drop-target' );
			}
		} );
		tr.querySelector( '.fpdmk-bulk-lang-cell' ).appendChild( cloneSelect( $defLang, '[language]', rowId, false ) );
		var folderSel = cloneSelect( $defFold, '[folder_term]', rowId, false );
		tr.querySelector( '.fpdmk-bulk-folder-cell' ).appendChild( folderSel );
		tr.querySelector( '.fpdmk-bulk-cats-cell' ).appendChild( cloneSelect( $defCats, '[categories]', rowId, true ) );
		if ( preset && preset.folderId ) {
			folderSel.value = String( preset.folderId );
		}
		$tbody.appendChild( tr );
		refreshUI();
	}

	function isFileAllowed( file ) {
		var t = file.type || '';
		if ( t && cfg.allowedMimes.indexOf( t ) !== -1 ) {
			return true;
		}
		var name = ( file.name || '' ).toLowerCase();
		var ext = name.indexOf( '.' ) !== -1 ? name.slice( name.lastIndexOf( '.' ) ) : '';
		var map = {
			'.pdf': 'application/pdf',
			'.jpg': 'image/jpeg',
			'.jpeg': 'image/jpeg',
			'.png': 'image/png',
			'.gif': 'image/gif',
			'.webp': 'image/webp',
			'.svg': 'image/svg+xml',
			'.mp4': 'video/mp4',
			'.webm': 'video/webm',
			'.txt': 'text/plain',
		};
		var mime = map[ ext ];
		return mime && cfg.allowedMimes.indexOf( mime ) !== -1;
	}

	function mapRestToAtt( json, file ) {
		var title = '';
		if ( json.title ) {
			if ( typeof json.title === 'string' ) {
				title = json.title;
			} else if ( json.title.rendered ) {
				title = json.title.rendered;
			}
		}
		var caption = '';
		if ( json.caption ) {
			if ( typeof json.caption === 'string' ) {
				caption = json.caption;
			} else if ( json.caption.rendered ) {
				caption = json.caption.rendered;
			}
		}
		return {
			id: json.id,
			filename: file.name,
			title: title || file.name.replace( /\.[^/.]+$/, '' ),
			mime: json.mime_type || file.type || '',
			caption: caption,
		};
	}

	/**
	 * Upload via XMLHttpRequest per avere i progress events (fetch non li supporta).
	 * Risolve con il JSON della risposta; rigetta con Error con messaggio leggibile.
	 */
	function uploadOne( file, onProgress ) {
		return new Promise( function ( resolve, reject ) {
			var fd = new FormData();
			fd.append( 'file', file, file.name );
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', cfg.restMediaUrl, true );
			xhr.setRequestHeader( 'X-WP-Nonce', cfg.restNonce );
			xhr.withCredentials = true;
			if ( typeof onProgress === 'function' && xhr.upload ) {
				xhr.upload.addEventListener( 'progress', function ( ev ) {
					if ( ev.lengthComputable ) {
						onProgress( ev.loaded / ev.total );
					}
				} );
			}
			xhr.onload = function () {
				var body = null;
				try {
					body = xhr.responseText ? JSON.parse( xhr.responseText ) : null;
				} catch ( err ) {
					/* leave body null */
				}
				if ( xhr.status >= 200 && xhr.status < 300 && body ) {
					if ( typeof onProgress === 'function' ) {
						onProgress( 1 );
					}
					resolve( body );
					return;
				}
				var msg = ( body && body.message ) ? body.message : ( xhr.statusText || 'Upload error' );
				reject( new Error( msg ) );
			};
			xhr.onerror = function () {
				reject( new Error( i18n.networkError || 'Network error' ) );
			};
			xhr.onabort = function () {
				reject( new Error( 'aborted' ) );
			};
			xhr.send( fd );
		} );
	}

	/**
	 * Crea/rimuove una riga placeholder visualizzata durante l'upload (con progress bar).
	 * Non ha id attachment: serve solo come feedback; sostituita o rimossa al completamento.
	 */
	function makePlaceholderRow( file ) {
		var tr = document.createElement( 'tr' );
		tr.className = 'fpdmk-bulk-row fpdmk-bulk-placeholder';
		var td = document.createElement( 'td' );
		td.colSpan = 8;
		var wrap = document.createElement( 'div' );
		wrap.className = 'fpdmk-bulk-placeholder-inner';
		var label = document.createElement( 'span' );
		label.className = 'fpdmk-bulk-placeholder-name';
		label.textContent = file.name;
		var bar = document.createElement( 'div' );
		bar.className = 'fpdmk-bulk-progress';
		var fill = document.createElement( 'span' );
		fill.className = 'fpdmk-bulk-progress-fill';
		fill.style.width = '0%';
		bar.appendChild( fill );
		var pct = document.createElement( 'span' );
		pct.className = 'fpdmk-bulk-progress-pct';
		pct.textContent = '0%';
		wrap.appendChild( label );
		wrap.appendChild( bar );
		wrap.appendChild( pct );
		td.appendChild( wrap );
		tr.appendChild( td );
		$tbody.appendChild( tr );
		return {
			row: tr,
			setProgress: function ( ratio ) {
				var p = Math.max( 0, Math.min( 1, ratio || 0 ) );
				fill.style.width = ( p * 100 ).toFixed( 1 ) + '%';
				pct.textContent = Math.round( p * 100 ) + '%';
			},
			setError: function ( msg ) {
				tr.classList.add( 'is-error' );
				pct.textContent = '!';
				label.textContent = file.name + ' — ' + msg;
			},
			remove: function () {
				if ( tr.parentNode ) {
					tr.parentNode.removeChild( tr );
				}
			},
		};
	}

	function processFiles( files ) {
		var list = Array.prototype.slice.call( files || [] ).filter( isFileAllowed );
		var skipped = ( files ? files.length : 0 ) - list.length;
		if ( skipped > 0 ) {
			setStatus(
				( i18n.skippedType || '' ).replace( '%d', String( skipped ) ),
				'info'
			);
		}
		if ( list.length === 0 ) {
			if ( skipped === 0 ) {
				setStatus( i18n.noValidFiles || '', 'error' );
			}
			return;
		}
		setStatus( i18n.uploading || '', 'info' );
		$table.classList.remove( 'is-hidden' );
		$empty.classList.add( 'is-hidden' );
		var queue = list.slice();
		var inflight = 0;
		var errors = 0;
		var completed = 0;
		var total = queue.length;

		function startOne( file ) {
			var ph = makePlaceholderRow( file );
			uploadOne( file, ph.setProgress )
				.then( function ( json ) {
					ph.remove();
					addRow( mapRestToAtt( json, file ) );
				} )
				.catch( function ( err ) {
					errors++;
					ph.setError( err.message || 'Error' );
					setStatus( file.name + ': ' + ( err.message || 'Error' ), 'error' );
				} )
				.finally( function () {
					inflight--;
					completed++;
					if ( completed === total ) {
						if ( errors === 0 ) {
							setStatus( i18n.uploadDone || '', 'success' );
						}
					} else {
						pump();
					}
				} );
		}

		function pump() {
			while ( inflight < MAX_PARALLEL && queue.length > 0 ) {
				inflight++;
				startOne( queue.shift() );
			}
		}
		pump();
	}

	function dataTransferHasFiles( dt ) {
		if ( ! dt || ! dt.types ) {
			return false;
		}
		if ( typeof dt.types.contains === 'function' ) {
			return dt.types.contains( 'Files' );
		}
		return Array.prototype.indexOf.call( dt.types, 'Files' ) !== -1;
	}

	function bindDropzone() {
		if ( ! $dropzone || ! $fileInput ) {
			return;
		}
		$dropzone.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				$fileInput.click();
			}
		} );
		$dropzone.addEventListener( 'click', function () {
			$fileInput.click();
		} );
		$fileInput.addEventListener( 'change', function () {
			processFiles( $fileInput.files );
			$fileInput.value = '';
		} );
		[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
			$dropzone.addEventListener( ev, function ( e ) {
				if ( ! dataTransferHasFiles( e.dataTransfer ) ) {
					return;
				}
				e.preventDefault();
				e.dataTransfer.dropEffect = 'copy';
				$dropzone.classList.add( 'is-dragover' );
			} );
		} );
		$dropzone.addEventListener( 'dragleave', function ( e ) {
			if ( e.target === $dropzone ) {
				$dropzone.classList.remove( 'is-dragover' );
			}
		} );
		$dropzone.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			$dropzone.classList.remove( 'is-dragover' );
			if ( ! e.dataTransfer ) {
				return;
			}
			var items = e.dataTransfer.items;
			var canScanDir = items && items.length && typeof items[ 0 ].webkitGetAsEntry === 'function';
			if ( canScanDir ) {
				scanDroppedItems( items ).then( function ( result ) {
					if ( result.hasDirectories ) {
						handleDirectoryDrop( result );
					} else if ( result.files.length > 0 ) {
						processFiles( result.files.map( function ( x ) { return x.file; } ) );
					}
				} );
				return;
			}
			if ( e.dataTransfer.files && e.dataTransfer.files.length ) {
				processFiles( e.dataTransfer.files );
			}
		} );
	}

	/**
	 * Scansione ricorsiva di un DataTransferItemList con webkitGetAsEntry.
	 * Limiti: max 2000 file, profondità massima 20, per evitare abusi.
	 * Ritorna `{files: [{file, folderPath:[string]}], hasDirectories}` con `folderPath` array
	 * dei segmenti (cartella radice + eventuali sottocartelle); vuoto se il file era al top level.
	 */
	function scanDroppedItems( itemList ) {
		var MAX_FILES = 2000;
		var MAX_DEPTH = 20;
		var collected = [];
		var hasDirectories = false;
		var entries = [];
		for ( var i = 0; i < itemList.length; i++ ) {
			var en = itemList[ i ].webkitGetAsEntry();
			if ( en ) {
				entries.push( en );
				if ( en.isDirectory ) {
					hasDirectories = true;
				}
			}
		}

		function readDir( dirReader ) {
			return new Promise( function ( resolve, reject ) {
				var all = [];
				function readBatch() {
					dirReader.readEntries( function ( batch ) {
						if ( ! batch.length ) {
							resolve( all );
							return;
						}
						for ( var j = 0; j < batch.length; j++ ) {
							all.push( batch[ j ] );
						}
						readBatch();
					}, reject );
				}
				readBatch();
			} );
		}

		function walk( entry, path, depth ) {
			if ( collected.length >= MAX_FILES ) {
				return Promise.resolve();
			}
			if ( entry.isFile ) {
				return new Promise( function ( resolve ) {
					entry.file(
						function ( file ) {
							collected.push( { file: file, folderPath: path.slice() } );
							resolve();
						},
						function () { resolve(); }
					);
				} );
			}
			if ( entry.isDirectory ) {
				if ( depth >= MAX_DEPTH ) {
					return Promise.resolve();
				}
				var childPath = path.concat( [ entry.name ] );
				var reader = entry.createReader();
				return readDir( reader ).then( function ( children ) {
					return children.reduce( function ( p, child ) {
						return p.then( function () {
							return walk( child, childPath, depth + 1 );
						} );
					}, Promise.resolve() );
				} );
			}
			return Promise.resolve();
		}

		return entries
			.reduce( function ( p, en ) {
				return p.then( function () {
					return walk( en, [], 0 );
				} );
			}, Promise.resolve() )
			.then( function () {
				return { files: collected, hasDirectories: hasDirectories };
			} );
	}

	/**
	 * Mostra conferma, crea le cartelle server-side, carica i file col folderId corretto.
	 */
	function handleDirectoryDrop( result ) {
		var files = result.files.filter( function ( x ) { return isFileAllowed( x.file ); } );
		if ( files.length === 0 ) {
			setStatus( i18n.noValidFiles || '', 'error' );
			return;
		}
		var uniquePaths = {};
		files.forEach( function ( x ) {
			if ( x.folderPath && x.folderPath.length ) {
				uniquePaths[ x.folderPath.join( '/' ) ] = x.folderPath;
			}
		} );
		var pathsArr = Object.keys( uniquePaths ).map( function ( k ) { return uniquePaths[ k ]; } );
		var foldersCount = pathsArr.length;

		if ( ! cfg.canCreateFolders && foldersCount > 0 ) {
			var msgDenied = ( i18n.folderCreateDenied || 'Permesso negato per creare cartelle. Verranno caricati solo i file al livello principale.' );
			setStatus( msgDenied, 'error' );
			pathsArr = [];
			files = files.filter( function ( x ) { return ! x.folderPath || x.folderPath.length === 0; } );
			if ( files.length === 0 ) {
				return;
			}
		}

		var confirmMsg = ( i18n.confirmDirectoryDrop || '' )
			.replace( '%1$d', String( files.length ) )
			.replace( '%2$d', String( foldersCount ) );
		if ( confirmMsg && ! window.confirm( confirmMsg ) ) {
			return;
		}

		function startUploads( pathMap ) {
			setStatus( i18n.uploading || '', 'info' );
			$table.classList.remove( 'is-hidden' );
			$empty.classList.add( 'is-hidden' );

			var queue = files.slice();
			var inflight = 0;
			var errors = 0;
			var completed = 0;
			var total = queue.length;

			function startOneDir( item ) {
				var ph = makePlaceholderRow( item.file );
				var folderId = 0;
				if ( item.folderPath && item.folderPath.length && pathMap ) {
					var info = pathMap[ item.folderPath.join( '/' ) ];
					if ( info && info.term_id ) {
						folderId = info.term_id;
					}
				}
				uploadOne( item.file, ph.setProgress )
					.then( function ( json ) {
						ph.remove();
						addRow( mapRestToAtt( json, item.file ), { folderId: folderId } );
					} )
					.catch( function ( err ) {
						errors++;
						ph.setError( err.message || 'Error' );
						setStatus( item.file.name + ': ' + ( err.message || 'Error' ), 'error' );
					} )
					.finally( function () {
						inflight--;
						completed++;
						if ( completed === total ) {
							if ( errors === 0 ) {
								setStatus( i18n.uploadDone || '', 'success' );
							}
						} else {
							pumpDir();
						}
					} );
			}
			function pumpDir() {
				while ( inflight < MAX_PARALLEL && queue.length > 0 ) {
					inflight++;
					startOneDir( queue.shift() );
				}
			}
			pumpDir();
		}

		if ( pathsArr.length === 0 ) {
			startUploads( null );
			return;
		}
		setStatus( i18n.creatingFolders || '', 'info' );
		var body = new URLSearchParams();
		body.append( 'action', 'fp_dmk_ensure_folder_paths' );
		body.append( '_nonce', cfg.folderNonce );
		pathsArr.forEach( function ( parts, idx ) {
			parts.forEach( function ( segment ) {
				body.append( 'paths[' + idx + '][]', segment );
			} );
		} );
		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					setStatus(
						res && res.data && res.data.message ? res.data.message : ( i18n.folderCreateError || '' ),
						'error'
					);
					return;
				}
				if ( res.data && Array.isArray( res.data.created ) ) {
					res.data.created.forEach( function ( node ) {
						var padArr = new Array( node.depth + 1 ).join( '— ' );
						appendFolderOption( $defFold, node.term_id, padArr + node.name, node.depth, false );
						$tbody.querySelectorAll( 'select[name$="[folder_term]"]' ).forEach( function ( s ) {
							appendFolderOption( s, node.term_id, padArr + node.name, node.depth, false );
						} );
						var parentSel = document.querySelector( '.fpdmk-folder-new-parent' );
						if ( parentSel ) {
							appendFolderOption( parentSel, node.term_id, padArr + node.name, node.depth, false );
						}
						insertTreeNode( node.term_id, node.name, node.parent || 0 );
					} );
				}
				startUploads( res.data && res.data.paths ? res.data.paths : null );
			} )
			.catch( function () {
				setStatus( i18n.networkError || '', 'error' );
			} );
	}

	function appendFolderOption( select, termId, label, depth, markSelected ) {
		if ( ! select ) {
			return;
		}
		if ( select.querySelector( 'option[value="' + termId + '"]' ) ) {
			if ( markSelected ) {
				select.value = String( termId );
			}
			return;
		}
		var opt = document.createElement( 'option' );
		opt.value = String( termId );
		opt.textContent = label;
		opt.setAttribute( 'data-depth', String( depth ) );
		select.appendChild( opt );
		if ( markSelected ) {
			select.value = String( termId );
		}
	}

	function insertTreeNode( termId, name, parentId ) {
		if ( ! $treeRoot ) {
			return;
		}
		if ( $treeRoot.querySelector( '.fpdmk-tree-node[data-folder-id="' + String( termId ) + '"]' ) ) {
			return;
		}
		var childDepth = 0;
		var ul;
		if ( parentId === 0 ) {
			ul = $treeRoot.querySelector( '.fpdmk-tree-root-ul' );
			childDepth = 0;
		} else {
			var parentBtn = $treeRoot.querySelector( '.fpdmk-tree-node[data-folder-id="' + parentId + '"]' );
			if ( ! parentBtn ) {
				return;
			}
			var parentLi = parentBtn.closest( 'li.fpdmk-tree-item' );
			if ( ! parentLi ) {
				return;
			}
			var parentRow = parentLi.querySelector( ':scope > .fpdmk-tree-row' );
			var pd = parentRow ? parseInt( parentRow.getAttribute( 'data-depth' ), 10 ) : 0;
			childDepth = isNaN( pd ) ? 0 : pd + 1;
			ul = parentLi.querySelector( ':scope > .fpdmk-tree-children' );
			if ( ! ul ) {
				ul = document.createElement( 'ul' );
				ul.className = 'fpdmk-tree-children';
				ul.setAttribute( 'role', 'group' );
				parentLi.appendChild( ul );
				var prow = parentLi.querySelector( ':scope > .fpdmk-tree-row' );
				var ptog = prow && prow.querySelector( '.fpdmk-tree-toggle' );
				if ( ptog ) {
					ptog.classList.remove( 'is-leaf' );
					ptog.textContent = '▾';
					ptog.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						var collapsed = ul.classList.toggle( 'is-collapsed' );
						ptog.textContent = collapsed ? '▸' : '▾';
					} );
				}
			}
			ul.classList.remove( 'is-collapsed' );
		}
		if ( ! ul ) {
			return;
		}
		var node = { id: termId, name: name, slug: '', count: 0, count_deep: 0, children: [] };
		renderTreeNodes( [ node ], ul, childDepth );
	}

	function broadcastFolder( termId, name, depth, parentId ) {
		var pad = '';
		for ( var i = 0; i < depth; i++ ) {
			pad += '— ';
		}
		var label = pad + name;
		appendFolderOption( $defFold, termId, label, depth, true );
		$tbody.querySelectorAll( 'select[name$="[folder_term]"]' ).forEach( function ( s ) {
			appendFolderOption( s, termId, label, depth, false );
		} );
		var parentSel = document.querySelector( '.fpdmk-folder-new-parent' );
		if ( parentSel ) {
			appendFolderOption( parentSel, termId, label, depth, false );
		}
		insertTreeNode( termId, name, typeof parentId === 'number' ? parentId : 0 );
		syncTreeSelection( termId );
	}

	function bindMediaPicker() {
		if ( ! $pickBtn ) {
			return;
		}
		$pickBtn.addEventListener( 'click', function () {
			frame = wp.media( {
				title: i18n.mediaTitle || '',
				button: { text: i18n.mediaButton || '' },
				library: { type: cfg.allowedMimes },
				multiple: 'add',
			} );
			frame.on( 'select', function () {
				var sel = frame.state().get( 'selection' );
				sel.each( function ( att ) {
					addRow( att.toJSON() );
				} );
			} );
			frame.open();
		} );
	}

	function removeRow( tr ) {
		if ( ! tr ) {
			return;
		}
		var hid = tr.querySelector( 'input[type="hidden"]' );
		if ( hid && hid.value ) {
			delete addedIds[ hid.value ];
		}
		tr.parentNode.removeChild( tr );
	}

	function bindRemove() {
		$tbody.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.fpdmk-bulk-remove' );
			if ( ! btn ) {
				return;
			}
			removeRow( btn.closest( 'tr' ) );
			refreshUI();
			updateBulkbar();
		} );
	}

	var $masterCheck = document.getElementById( 'fpdmk-bulk-master-check' );
	var $bulkbar = document.getElementById( 'fpdmk-bulk-bulkbar' );
	var $selCount = document.getElementById( 'fpdmk-bulk-sel-count' );

	function readyRows() {
		return Array.prototype.slice.call(
			$tbody.querySelectorAll( 'tr.fpdmk-bulk-row:not(.fpdmk-bulk-placeholder)' )
		);
	}

	function selectedRows() {
		return readyRows().filter( function ( tr ) {
			var cb = tr.querySelector( '.fpdmk-bulk-row-check' );
			return cb && cb.checked;
		} );
	}

	function updateBulkbar() {
		var sel = selectedRows();
		var n = sel.length;
		if ( $selCount ) {
			$selCount.textContent = String( n );
		}
		if ( $bulkbar ) {
			$bulkbar.classList.toggle( 'is-hidden', n === 0 );
		}
		if ( $masterCheck ) {
			var rows = readyRows();
			if ( rows.length === 0 ) {
				$masterCheck.checked = false;
				$masterCheck.indeterminate = false;
			} else if ( n === 0 ) {
				$masterCheck.checked = false;
				$masterCheck.indeterminate = false;
			} else if ( n === rows.length ) {
				$masterCheck.checked = true;
				$masterCheck.indeterminate = false;
			} else {
				$masterCheck.checked = false;
				$masterCheck.indeterminate = true;
			}
		}
	}

	function bindSelection() {
		$tbody.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.classList && e.target.classList.contains( 'fpdmk-bulk-row-check' ) ) {
				updateBulkbar();
			}
		} );
		if ( $masterCheck ) {
			$masterCheck.addEventListener( 'change', function () {
				var on = $masterCheck.checked;
				readyRows().forEach( function ( tr ) {
					var cb = tr.querySelector( '.fpdmk-bulk-row-check' );
					if ( cb ) {
						cb.checked = on;
					}
				} );
				updateBulkbar();
			} );
		}
	}

	function applyBulkAction( action ) {
		var rows = selectedRows();
		if ( rows.length === 0 ) {
			return;
		}
		if ( action === 'set-folder' ) {
			var fid = String( selectedFolderId );
			rows.forEach( function ( tr ) {
				var sel = tr.querySelector( 'select[name$="[folder_term]"]' );
				if ( sel ) {
					sel.value = fid;
				}
			} );
			applyFolderFilter();
			return;
		}
		if ( action === 'set-language' ) {
			if ( ! $defLang ) {
				return;
			}
			var lang = $defLang.value;
			rows.forEach( function ( tr ) {
				var sel = tr.querySelector( 'select[name$="[language]"]' );
				if ( sel ) {
					sel.value = lang;
				}
			} );
			return;
		}
		if ( action === 'set-categories' ) {
			if ( ! $defCats ) {
				return;
			}
			var selectedCats = [];
			Array.prototype.forEach.call( $defCats.options, function ( o ) {
				if ( o.selected ) {
					selectedCats.push( o.value );
				}
			} );
			rows.forEach( function ( tr ) {
				var sel = tr.querySelector( 'select[name$="[categories][]"]' );
				if ( ! sel ) {
					return;
				}
				Array.prototype.forEach.call( sel.options, function ( o ) {
					o.selected = selectedCats.indexOf( o.value ) !== -1;
				} );
			} );
			return;
		}
		if ( action === 'remove' ) {
			var msg = ( i18n.confirmBulkRemove || '' ).replace( '%d', String( rows.length ) );
			if ( msg && ! window.confirm( msg ) ) {
				return;
			}
			rows.forEach( removeRow );
			refreshUI();
			updateBulkbar();
			return;
		}
	}

	function bindBulkActions() {
		if ( ! $bulkbar ) {
			return;
		}
		$bulkbar.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.fpdmk-bulk-action' );
			if ( ! btn ) {
				return;
			}
			applyBulkAction( btn.getAttribute( 'data-action' ) );
		} );
	}

	function bindFolderCreate() {
		if ( ! cfg.canCreateFolders ) {
			return;
		}
		var widget = document.querySelector( '.fpdmk-folder-new' );
		if ( ! widget ) {
			return;
		}
		var toggle = widget.querySelector( '.fpdmk-folder-new-toggle' );
		var form = widget.querySelector( '.fpdmk-folder-new-form' );
		var nameInput = widget.querySelector( '.fpdmk-folder-new-name' );
		var parentSel = widget.querySelector( '.fpdmk-folder-new-parent' );
		var saveBtn = widget.querySelector( '.fpdmk-folder-new-save' );
		var cancelBtn = widget.querySelector( '.fpdmk-folder-new-cancel' );
		var msg = widget.querySelector( '.fpdmk-folder-new-msg' );
		toggle.addEventListener( 'click', function () {
			form.hidden = false;
			toggle.hidden = true;
			nameInput.focus();
		} );
		cancelBtn.addEventListener( 'click', function () {
			form.hidden = true;
			toggle.hidden = false;
			nameInput.value = '';
			msg.textContent = '';
		} );
		saveBtn.addEventListener( 'click', function () {
			var name = ( nameInput.value || '' ).trim();
			if ( ! name ) {
				nameInput.focus();
				return;
			}
			saveBtn.disabled = true;
			msg.textContent = i18n.creatingFolder || '';
			var body = new URLSearchParams();
			body.append( 'action', 'fp_dmk_create_folder' );
			body.append( '_nonce', cfg.folderNonce );
			body.append( 'name', name );
			body.append( 'parent', parentSel.value || '0' );
			fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( res ) {
					saveBtn.disabled = false;
					if ( ! res || ! res.success ) {
						msg.textContent =
							res && res.data && res.data.message
								? res.data.message
								: i18n.folderCreateError || '';
						return;
					}
					var d = res.data;
					broadcastFolder( d.term_id, d.name, d.depth, d.parent );
					nameInput.value = '';
					parentSel.value = '0';
					form.hidden = true;
					toggle.hidden = false;
					msg.textContent = d.existed ? i18n.folderExists || '' : i18n.folderCreated || '';
				} )
				.catch( function () {
					saveBtn.disabled = false;
					msg.textContent = i18n.networkError || '';
				} );
		} );
	}

	if ( $filterFolder ) {
		$filterFolder.addEventListener( 'change', applyFolderFilter );
	}

	var submittingForm = false;
	var $form = document.getElementById( 'fpdmk-bulk-form' );
	if ( $form ) {
		$form.addEventListener( 'submit', function () {
			submittingForm = true;
		} );
	}
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( submittingForm ) {
			return undefined;
		}
		if ( countReadyRows() === 0 ) {
			return undefined;
		}
		e.preventDefault();
		e.returnValue = '';
		return '';
	} );

	buildFolderTree();
	bindDropzone();
	bindMediaPicker();
	bindRemove();
	bindSelection();
	bindBulkActions();
	bindFolderCreate();
	refreshUI();
	updateBulkbar();
} )();
