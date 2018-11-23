$(function() {
    window.myDropzone = new Dropzone('body.droppableZone', {
        url: 'convert.php'
    });

    window.myDropzone.on('success', function(event, responseText) {
        var blob = new Blob([responseText], {
                type: 'text/csv'
            }),
            fileName = event.name;
        fileName = fileName.replace(/\.csv$/g, '');
        fileName = fileName.replace(/\.txt$/g, '');
        fileName = fileName + '_converted.csv';
        if(window.navigator.msSaveOrOpenBlob) {
            window.navigator.msSaveBlob(blob, fileName);
        }
        else{
            var elem = window.document.createElement('a');
            elem.href = window.URL.createObjectURL(blob);
            elem.download = fileName;
            document.body.appendChild(elem);
            elem.click();
            document.body.removeChild(elem);
        }
    });
});