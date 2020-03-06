$.fn.popup = function () {
    var args = arguments;
    this.each(function (index, host) {
        if (!host.show) {
            host.props = $.extend({
                title: "Popup Window",
                buttons: [],
                icon: null,
                iconSize: 45,
                modal: false,
                hideOnClose: false,
                soft: true
            }, args[0]);
            host.center = function () {
                var x = (window.innerWidth / 2) - (this.__window.width() / 2), y = (window.innerHeight / 2) - (this.__window.height() / 2);
                this.__window.css({ top: y, left: x });
            };
            host.close = function () {
                $(this).trigger('beforeClose');
                this.__overlay.fadeOut(function () {
                    $(host).trigger('closed');
                    if (host.props.hideOnClose === false)
                        host.__overlay.remove();
                });
            };
            host.show = function () {
                if (!$(host).is(':visible')) $(host).show();
                if (this.__overlay) {
                    this.__window.show();
                    this.__overlay.fadeIn(function () { $(host).trigger('open'); });
                } else {
                    this.__window.fadeIn(function () { $(host).trigger('open'); });
                }
                this.center();
            };
            host.btnAction = function (event) {
                var btn = $(this).data('btn');
                if (btn.action === 'post' && btn.target) {
                    var data = {}, items = host.__window.find('input,select,textarea').serializeArray();
                    if (btn['data-source']) {
                        $(btn['data-source']).find('input[name],select[name],textarea[name]').each(function (index, item) {
                            var value = item.value;
                            if (item.type === 'checkbox')
                                value = $(item).is(':checked');
                            else if ($(item).attr('aria-hidden') === 'true' && $(item).prev().hasClass('mce-tinymce'))
                                value = tinymce.get('content').getContent();
                            data[item.name] = value;
                        });
                    }
                    for (x in items)
                        data[items[x]['name']] = items[x]['value'];
                    var post = $.post(btn.target, $.extend(btn.args, data));
                    if (host.props.callback)
                        post.done(host.props.callback);
                    if (btn.error)
                        post.error(eval(btn.error));
                    if (btn.done)
                        post.done(eval(btn.done));
                } else {
                    if (typeof btn.action === 'string')
                        btn.action = new Function(btn.action);
                    if (typeof btn.action === 'function')
                        btn.action.apply(host, event);
                }
                host.close();
            };
            host.__setIcon = function (name, color) {
                var icon = 'font-awesome';
                if (typeof color === 'undefined') color = '#000';
                this.__icon.css({ "padding": "0 15px" }).appendTo(this.__container);
                $(this).css({ "margin-left": (this.props.iconSize + 30) + "px" });
                switch (name) {
                    case 'working':
                        icon = 'circle-o-notch fa-spin';
                        color = '#333';
                        break;
                    case 'error':
                    case 'danger':
                        icon = 'times-circle';
                        color = '#cf3838';
                        break;
                    case 'info':
                    case 'notice':
                        icon = 'info-circle';
                        color = '#3A85CF';
                        break;
                    case 'warn':
                    case 'warning':
                        icon = 'exclamation-circle';
                        color = '#FF9900';
                        break;
                    case 'question':
                        icon = 'question-circle';
                        color = '#3A85CF';
                        break;
                    case 'user':
                        icon = 'user-circle';
                        color = '#00B36B';
                        break;
                    case 'success':
                        icon = 'check-circle';
                        color = '#00B36B';
                        break;
                }
                this.__icon.html($('<i class="fa fa-' + icon + '">'));
                if (color)
                    this.__icon.css({ color: color });
            };
            host.render = function () {
                this.__overlay = $('<div class="modal-overlay">')
                    .css({ position: "fixed", "z-index": 9999 }).appendTo(document.body).toggleClass('modal', this.props.modal);
                this.__window = $('<div class="modal-dialog">')
                    .hide().css({ position: "fixed" }).appendTo(this.__overlay);
                this.__content = $('<div class="modal-content">').appendTo(this.__window);
                this.__header = $('<div class="modal-header">').html($('<h5 class="modal-title">').html(this.props.title)).css({ "user-select": "none" }).appendTo(this.__content);
                this.__container = $('<div class="modal-body">').appendTo(this.__content);
                this.__close = $('<button type="button" class="close" aria-label="Close">').html($('<span aria-hidden="true">').html('&times;')).appendTo(this.__header);
                this.__icon = $('<div>').css({ "font-size": this.props.iconSize + "px", "float": "left" }).appendTo(this.__container);
                if (this.props.icon) this.__setIcon(this.props.icon, this.props.iconColor);
                this.props.hideOnClose = ($(this).parent().length > 0);
                this.__container.append(this);
                if (this.props.buttons.length > 0) {
                    this.__buttons = $('<div class="modal-footer">').appendTo(this.__content);
                    for (x in this.props.buttons) {
                        if (typeof this.props.buttons[x] === 'string')
                            this.props.buttons[x] = { label: this.props.buttons[x], action: 'close' };
                        var args = $.extend({ label: 'Button', action: 'close' }, this.props.buttons[x]);
                        var btn = $('<button>').html(args.label).data('btn', args).click(this.btnAction);
                        if (!('class' in args)) args.class = 'btn btn-default';
                        this.__buttons.append(btn.addClass(args.class));
                    }
                }
                if (this.props.id)
                    $(this).attr('id', this.props.id);
                if (this.props.width)
                    this.__window.addClass('static').css({ width: this.props.width });
                if (this.props.height)
                    this.__window.addClass('static').css({ height: this.props.height });
                if (this.props.minWidth)
                    this.__window.css({ 'min-width': this.props.minWidth });
                if (this.props.minHeight)
                    this.__window.css({ 'min-height': this.props.minHeight });
                if (this.props.maxWidth)
                    this.__window.css({ 'max-width': this.props.maxWidth });
                if (this.props.maxHeight)
                    this.__window.css({ 'max-height': this.props.maxHeight });
                if (this.props.soft)
                    this.__content.addClass('soft');
                if (this.props.zindex)
                    this.__overlay.css('z-index', this.props.zindex);
            };
            host.__move = function () {
                host.__window.css({
                    left: event.clientX - host.__offset[0] - parseInt(host.__window.css('marginLeft')),
                    top: event.clientY - host.__offset[1] - parseInt(host.__window.css('marginTop'))
                });
            };
            host.registerEvents = function () {
                this.__close.click(function () {
                    host.close();
                    return false;
                });
                this.__header.on('mousedown', function (event) {
                    if (host.__close.is(event.target)) return false;
                    host.__offset = [event.offsetX, event.offsetY];
                    $(window).on('mousemove', host.__move);
                }).on('mouseup', function (event) {
                    $(window).off('mousemove', host.__move);
                });

            };
            host.render();
            host.registerEvents();
            if (host.props.close) {
                setTimeout(function () {
                    host.close();
                }, host.props.close);
            }
        } else if (typeof args[0] === 'string') {
            switch (args[0]) {
                case 'close':
                    this.close();
                    break;
                case 'center':
                    this.center();
                    break;
                case 'icon':
                    this.__setIcon(args[1], args[2]);
                    break;
            }
        }
        host.show();
    });
    return this;
};

var handleError = function (response, status, xhr) {
    if (typeof status === 'object') {
        response = status;
        status = xhr;
    }
    if (status === 'error') {
        var error = response.responseJSON.error;
        var content = [$('<div class="adm-error-msg">').html(error.str)];
        if (error.line)
            content.push($('<div class="adm-error-line">').html([$('<label>').html('Line:'), $('<span>').html('#' + error.line)]));
        if (error.file)
            content.push($('<div class="adm-error-file">').html([$('<label>').html('File:'), $('<span>').html(error.file)]));
        $('<div>').html($('<div class="adm-error">').html(content)).popup({
            title: "Server Error",
            icon: "error",
            buttons: [
                { label: "OK", action: "close" }
            ]
        });
    }
};