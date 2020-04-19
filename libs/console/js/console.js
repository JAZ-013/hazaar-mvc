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

var scriptLoader = function () {
    this.scripts = [];
    this.load = function (script_url) {
        if (this.scripts.indexOf(script_url) >= 0) return false;
        this.scripts.push(script_url);
        return true;
    };
};

$.fn.tabs = function (cfg) {
    if (this.length === 0) return this;
    let host = this.get(0), args = arguments;
    if (!host._init) {
        host._select = function (name) {
            if (!(name in this.tabs)) return false;
            if (host.s) {
                host.s.a.removeClass('selected');
                host.s.b.hide();
            }
            this.tabs[name].b.show();
            this.tabs[name].a.addClass('selected');
            host.s = this.tabs[name];
        };
        host._add = function (item) {
            if (!('name' in item)) item.name = Math.random().toString(36).replace(/[^a-z]+/g, '').substr(0, 5);
            if (item.name in this.tabs) {
                this._select(item.name);
            } else {
                let close = $('<div class="tabs-close">').html('&times;').data('item', item).click(function () {
                    host._close($(this).data('item').name)
                });
                item.a = $('<div class="tabs-item">').html([
                    item.label,
                    close
                ]).data('name', item.name).appendTo(this.o.list);
                item.b = $('<div class="tabs-wrapper">').load(item.url).appendTo(this.o.container);
                item.a.click(function (e) {
                    host._select($(e.target).data('name'));
                });
                this.tabs[item.name] = item;
                this._select(item.name);
            }
        };
        host._close = function (name) {
            if (!(name in this.tabs)) return false;
            let tabs = Object.keys(this.tabs), i = tabs.indexOf(name), sel = tabs[i >= tabs.length - 1 ? tabs.length - 2 : i + 1];
            this.tabs[name].a.remove();
            this.tabs[name].b.remove();
            delete this.tabs[name];
            this._select(sel);
        };
        host._init = function (cfg) {
            host.o = {};
            host.tabs = {};
            host.s = null;
            $(this).addClass('tabs').html([
                host.o.list = $('<div class="tabs-list">'),
                host.o.container = $('<div class="tabs-container">')
            ]);
            if ('placeholder' in cfg) host.o.container.append($('<div class="tabs-placeholder">').html(cfg.placeholder));
        };
        host._init(typeof cfg === 'object' ? cfg : null);
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
    if (this.length === 0) return this;
    let host = this.get(0);
    if (!host._init) {
        host.cfg = cfg;
        host._event_nav = function (e) {
            let i = $(e.target), item = i.data('item');
            host.cfg.tabs.tabs('add', item);
        };
        host._render_menu_item = function (item) {
            item.url = this.cfg.url + '/' + item.name;
            let i = $('<div class="tree-item">').html($('<span>').html(item.label).data('item', item).click(host._event_nav));
            if ('items' in item && item.items.length > 0) {
                let s = $('<div class="tree-item-items">').appendTo(i);
                for (x in item.items) s.append(host._render_menu_item(item.items[x]));
            }
            return i;
        };
        host._render_menu = function (menu) {
            for (x in menu) $(this).append(host._render_menu_item(menu[x]));
        };
        host._init = function () {
            $(host).empty().addClass('tree');
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
    let tabs = $('#consoleTabs').tabs({ placeholder: "What would you like to do today?" });
    $('#mainMenu li').click(function (e) {
        $('#ide').attr('data-view', $(e.currentTarget).attr('data-toggle'));
    });
    $('#menuHome').treeMenu({
        'url': hazaar.url('menu'),
        'tabs': tabs
    });
    $('#menuCode').treeMenu({
        'url': hazaar.url('files'),
        'tabs': tabs
    });
});