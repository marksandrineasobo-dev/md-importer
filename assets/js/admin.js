( function() {
    var drop = document.getElementById( 'md-importer-dropzone' );
    var input = document.getElementById( 'md_import_file' );
    var fileList = document.getElementById( 'md-importer-file-list' );

    if ( ! drop || ! input || ! fileList ) {
        return;
    }

    function updateFileList( files ) {
        var output = [];

        for ( var i = 0; i < files.length; i++ ) {
            output.push( files[ i ].name );
        }

        if ( output.length ) {
            fileList.innerHTML = '<strong>' + output.length + ' file(s) selected:</strong><br>' + output.join( '<br>' );
        } else {
            fileList.innerHTML = '';
        }
    }

    function handleFiles( files ) {
        input.files = files;
        updateFileList( files );
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
            handleFiles( event.dataTransfer.files );
        }
    } );

    input.addEventListener( 'change', function() {
        updateFileList( input.files );
    } );
} )();
