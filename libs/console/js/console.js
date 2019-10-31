var handleError = function (response, status, xhr) {
    if (typeof status == 'object') {
        response = status;
        status = xhr;
    }
    if (status == 'error') {
        var error = response.responseJSON.error;
        $('<div>').html($('<div class="hz-error">').html([
            $('<div class="hz-error-msg">').html(error.str),
            $('<div class="hz-error-line">').html([$('<label>').html('Line:'), $('<span>').html('#' + error.line)]),
            $('<div class="hz-error-file">').html([$('<label>').html('File:'), $('<span>').html(error.file)])
        ])).popup({
            title: "Server Error",
            icon: "error",
            modal: true,
            buttons: [
                { label: "OK", action: "close" }
            ]
        });
    }
};

$(document).ready(function () {
    $('#mainMenu li').click(function(e){
        $('#ide').attr('data-view', $(e.currentTarget).attr('data-toggle'));
    });
});