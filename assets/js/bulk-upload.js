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

	function refreshUI() {
		var n = $tbody.querySelectorAll( 'tr' ).length;
		$count.textContent = n + ' ' + ( i18n.filesLabel || '' );
		$table.classList.toggle( 'is-hidden', n === 0 );
		$empty.classList.toggle( 'is-hidden', n > 0 );
		$submit.disabled = n === 0;
		applyFolderFilter();
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
			btn.textContent = node.name;
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

	function addRow( att ) {
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
		var mime = att.mime || '';
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
		tr.querySelector( '.fpdmk-bulk-folder-cell' ).appendChild( cloneSelect( $defFold, '[folder_term]', rowId, false ) );
		tr.querySelector( '.fpdmk-bulk-cats-cell' ).appendChild( cloneSelect( $defCats, '[categories]', rowId, true ) );
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

	function uploadOne( file ) {
		var fd = new FormData();
		fd.append( 'file', file, file.name );
		return fetch( cfg.restMediaUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.restNonce,
			},
			body: fd,
		} ).then( function ( res ) {
			return res.json().then( function ( body ) {
				if ( ! res.ok ) {
					var msg = ( body && body.message ) ? body.message : res.statusText;
					throw new Error( msg );
				}
				return body;
			} );
		} );
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
		var queue = list.slice();
		var inflight = 0;
		var errors = 0;
		var completed = 0;
		var total = queue.length;

		function pump() {
			while ( inflight < MAX_PARALLEL && queue.length > 0 ) {
				inflight++;
				var file = queue.shift();
				uploadOne( file )
					.then( function ( json ) {
						addRow( mapRestToAtt( json, file ) );
					} )
					.catch( function ( err ) {
						errors++;
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
			if ( e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length ) {
				processFiles( e.dataTransfer.files );
			}
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
		var node = { id: termId, name: name, slug: '', children: [] };
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

	function bindRemove() {
		$tbody.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.fpdmk-bulk-remove' );
			if ( ! btn ) {
				return;
			}
			var tr = btn.closest( 'tr' );
			if ( ! tr ) {
				return;
			}
			var hid = tr.querySelector( 'input[type="hidden"]' );
			if ( hid && hid.value ) {
				delete addedIds[ hid.value ];
			}
			tr.parentNode.removeChild( tr );
			refreshUI();
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

	buildFolderTree();
	bindDropzone();
	bindMediaPicker();
	bindRemove();
	bindFolderCreate();
	refreshUI();
} )();
