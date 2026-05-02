( function() {
    var drop = document.getElementById( 'md-importer-dropzone' );
    var input = document.getElementById( 'md_import_file' );
    var fileList = document.getElementById( 'md-importer-file-list' );
    var pendingUploads = document.getElementById( 'md-importer-pending-uploads' );
    var hiddenFields = document.getElementById( 'md-importer-hidden-fields' );
    var confirmButton = document.getElementById( 'md-importer-confirm' );
    var cancelButton = document.getElementById( 'md-importer-cancel' );
    var uploadForm = document.getElementById( 'md-importer-upload-form' );

    if ( ! drop || ! input || ! fileList || ! pendingUploads || ! hiddenFields || ! confirmButton || ! cancelButton || ! uploadForm ) {
        return;
    }

    var pendingFiles = [];

    function escapeHtml( string ) {
        return String( string )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

    function resetUpload() {
        pendingFiles = [];
        fileList.innerHTML = '';
        pendingUploads.innerHTML = '';
        hiddenFields.innerHTML = '';
        confirmButton.disabled = true;
        cancelButton.disabled = true;
        drop.textContent = 'Drop Markdown files here or click to select them.';
    }

    function renderPendingFiles() {
        hiddenFields.innerHTML = '';

        if ( ! pendingFiles.length ) {
            pendingUploads.innerHTML = '';
            confirmButton.disabled = true;
            cancelButton.disabled = true;
            return;
        }

        var rows = pendingFiles.map( function( item, index ) {
            return '<tr>' +
                '<td>' + ( index + 1 ) + '</td>' +
                '<td>' + escapeHtml( item.name ) + '</td>' +
                '<td><input type="text" class="md-importer-input" data-index="' + index + '" data-field="release_date" value="' + escapeHtml( item.release_date ) + '" /></td>' +
                '<td><input type="text" class="md-importer-input" data-index="' + index + '" data-field="url_slug" value="' + escapeHtml( item.url_slug ) + '" /></td>' +
                '<td><input type="text" class="md-importer-input" data-index="' + index + '" data-field="keyword" value="' + escapeHtml( item.keyword ) + '" /></td>' +
                '</tr>';
        } ).join( '' );

        pendingUploads.innerHTML = '<table class="wp-list-table widefat fixed striped md-importer-table md-importer-pending-table">' +
            '<thead><tr><th>#</th><th>File name</th><th>RELEASE_DATE</th><th>URL Slug</th><th>Keyword</th></tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
            '</table>';

        confirmButton.disabled = false;
        cancelButton.disabled = false;
    }

    function parseFile( file ) {
        var reader = new FileReader();

        reader.onload = function( event ) {
            var lines = event.target.result.split( /\r?\n/ );
            var release_date = '';
            var url_slug = '';
            var keyword = '';

            if ( lines[0] ) {
                var match = lines[0].match( /\[\[([^\]]+)\]\]/ );
                if ( match ) {
                    release_date = match[1];
                }
            }

            if ( lines[4] ) {
                url_slug = lines[4].trim();
            }

            if ( lines[6] && lines[6].indexOf( '# ' ) === 0 ) {
                keyword = lines[6].substr( 2 ).trim();
            }

            pendingFiles.push({
                file: file,
                name: file.name,
                release_date: release_date,
                url_slug: url_slug,
                keyword: keyword,
            });

            renderPendingFiles();
        };

        reader.readAsText( file );
    }

    function handleFiles( files ) {
        resetUpload();

        for ( var i = 0; i < files.length; i++ ) {
            if ( files[i].name.match( /\.(md|markdown|txt)$/i ) ) {
                parseFile( files[i] );
            }
        }

        if ( files.length ) {
            fileList.innerHTML = '<strong>' + files.length + ' file(s) selected.</strong>';
        }
    }

    function buildHiddenFields() {
        hiddenFields.innerHTML = '';

        pendingFiles.forEach( function( item ) {
            var releaseDateField = document.createElement( 'input' );
            releaseDateField.type = 'hidden';
            releaseDateField.name = 'release_date[]';
            releaseDateField.value = item.release_date;

            var urlSlugField = document.createElement( 'input' );
            urlSlugField.type = 'hidden';
            urlSlugField.name = 'url_slug[]';
            urlSlugField.value = item.url_slug;

            var keywordField = document.createElement( 'input' );
            keywordField.type = 'hidden';
            keywordField.name = 'keyword[]';
            keywordField.value = item.keyword;

            hiddenFields.appendChild( releaseDateField );
            hiddenFields.appendChild( urlSlugField );
            hiddenFields.appendChild( keywordField );
        } );
    }

    drop.addEventListener( 'click', function() {
        input.click();
    } );

    drop.addEventListener( 'dragover', function( event ) {
        event.preventDefault();
        drop.classList.add( 'md-importer-dropzone-active' );
    } );

    drop.addEventListener( 'dragleave', function( event ) {
        event.preventDefault();
        drop.classList.remove( 'md-importer-dropzone-active' );
    } );

    drop.addEventListener( 'drop', function( event ) {
        event.preventDefault();
        drop.classList.remove( 'md-importer-dropzone-active' );

        if ( event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length ) {
            handleFiles( event.dataTransfer.files );            try {
                input.files = event.dataTransfer.files;
            } catch ( e ) {
                // Ignore if not supported
            }        }
    } );

    input.addEventListener( 'change', function() {
        if ( input.files && input.files.length ) {
            handleFiles( input.files );
        }
    } );

    pendingUploads.addEventListener( 'input', function( event ) {
        var target = event.target;
        if ( target.classList.contains( 'md-importer-input' ) ) {
            var index = parseInt( target.getAttribute( 'data-index' ), 10 );
            var field = target.getAttribute( 'data-field' );
            if ( pendingFiles[ index ] ) {
                pendingFiles[ index ][ field ] = target.value;
            }
        }
    } );

    uploadForm.addEventListener( 'submit', function( event ) {
        if ( ! pendingFiles.length ) {
            event.preventDefault();
            return;
        }

        buildHiddenFields();
    } );

    confirmButton.addEventListener( 'click', function() {
        if ( ! pendingFiles.length ) {
            return;
        }

        if ( typeof uploadForm.requestSubmit === 'function' ) {
            uploadForm.requestSubmit();
        } else {
            uploadForm.submit();
        }
    } );

    cancelButton.addEventListener( 'click', function() {
        resetUpload();
    } );

    var searchInputs = document.querySelectorAll( '.md-importer-table-toolbar input[type="search"]' );
    Array.prototype.forEach.call( searchInputs, function( searchInput ) {
        searchInput.addEventListener( 'input', function() {
            var filter = searchInput.value.toLowerCase();
            var container = searchInput.closest( '.md-importer-table-wrap' );
            var table = container ? container.querySelector( '.md-importer-table' ) : null;
            var rows = table ? table.querySelectorAll( 'tbody tr' ) : [];

            Array.prototype.forEach.call( rows, function( row ) {
                row.style.display = row.textContent.toLowerCase().indexOf( filter ) !== -1 ? '' : 'none';
            } );
        } );
    } );
} )();
