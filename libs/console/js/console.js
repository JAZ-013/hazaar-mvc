$(document).ajaxError(function (event, jqxhr, settings, thrownError) {
    var r = JSON.parse(jqxhr.responseText), error = r.error;
    $('<div class="modal-lg">').html($('<div class="hz-error">').html([
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
});

$.fn.tabs = function () {
    let host = this.get(0), args = arguments;
    if (!host._init) {
        host._add = function (item) {
            this.o.list.append($('<div class="tabs-item">').html(item.label));
            this.o.container.append($('<div class="tabs-wrapper">').load(item.url));
        };
        host._init = function () {
            host.o = {};
            host.tabs = [];
            $(this).addClass('tabs').html([
                host.o.list = $('<div class="tabs-list">'),
                host.o.container = $('<div class="tabs-container">')
            ]);
        };
        host._init();
    } else if (args.length > 0) {
        switch (args[0]) {
            case 'add':
                host._add(args[1]);
                break;
        }
    }
    return this;
};

$.fn.treeMenu = function (cfg) {
    let host = this.get(0);
    if (!host._init) {
        host.cfg = cfg;
        host._event_nav = function (e) {
            let i = $(e.target), item = i.data('item');
            host.cfg.tabs.tabs('add', item);
        };
        host._render_menu_item = function (item) {
            item.url = this.cfg.url + '/' + item.name;
            let i = $('<div class="item">').html($('<span>').html(item.label).data('item', item).click(host._event_nav));
            if ('items' in item && item.items.length > 0) {
                let s = $('<div class="items">').appendTo(i);
                for (x in item.items) s.append(host._render_menu_item(item.items[x]));
            }
            return i;
        };
        host._render_menu = function (menu) {
            for (x in menu) $(this).append(host._render_menu_item(menu[x]));
        };
        host._init = function () {
            $(host).empty();
            $.get(cfg.url).done(function (menu) {
                host.cfg.url = menu.url;
                host._render_menu(menu.items);
            });
        };
    }
    host._init();
    return this;
};

$(document).ready(function () {
    $('#mainMenu li').click(function (e) {
        $('#ide').attr('data-view', $(e.currentTarget).attr('data-toggle'));
    });
    $('#consoleTreeMenu').treeMenu({
        'url': hazaar.url('menu'),
        'tabs': $('#consoleTabs').tabs()
    });
});