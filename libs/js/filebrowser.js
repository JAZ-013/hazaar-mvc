$.fn.browserDialog = function (params) {
    if (!params.title)
        params.title = 'Dialog box';
    var host = this.get(0);
    if (this.parent().length === 0)
        this.appendTo('body');
    host.winDIV = $('<div class="dialog">').attr('data-close', 'true');
    host.titleDIV = $('<div class="title">').html(params.title);
    host.contentDIV = $('<div class="content">');
    host._close = function (event, action) {
        if (typeof params.onClose === 'function') {
            params.onClose(action);
        }
        host.winDIV.fadeOut(function () {
            if ($(this).attr('data-close') === 'true')
                $(this).remove();
        });
    };
    this.addClass('content-text');
    host.buttonsDIV = $('<div class="buttons">');
    if (!params.buttons) {
        params.buttons = [
            {
                label: 'OK', class: 'primary', action: 'cancel'
            }
        ];
    }
    this.after(host.winDIV);
    host.winDIV.append(host.titleDIV, host.contentDIV.append(this, host.buttonsDIV)).css({
        top: window.innerHeight / 2 - host.winDIV.height() / 2,
        left: window.innerWidth / 2 - host.winDIV.width() / 2
    }).fadeIn();
    for (i in params.buttons) {
        var meta = params.buttons[i];
        if (!meta.label)
            meta.label = 'OK';
        if (!meta.class)
            meta.class = 'default';
        var button = $('<button>').html(meta.label).addClass(meta.class);
        host.buttonsDIV.append(button);
        if (typeof meta.action === 'function') {
            button.click($.proxy(meta.action, this)).click(function (event) {
                host._close(event, this.action);
            });
        } else {
            button.get(0).action = meta.action;
            button.click(function (event) {
                host._close(event, this.action);
            });
        }
        if (meta.default)
            button.attr('data-default', true).focus();
    }
    host.winDIV.keypress(function (event) {
        if (event.keyCode === 13)
            host.buttonsDIV.children('[data-default]').click();
    });
    return host.winDIV;
};

var fbConnector = function (url, filter, with_meta) {
    this.url = url;
    this.filter = filter;
    this.cwd = null;
    this.events = {};
    this.max_upload_size = 0;
    this.with_meta = with_meta || false;
    var conn = this;
    this._error = function (error) {
        $('<div>').html(error.str).browserDialog({ title: error.status });
    };
    this._send = function (packet) {
        var deferred = $.Deferred();
        var promise = deferred.promise();
        $.stream({
            url: this.url,
            type: 'POST',
            data: packet
        }).progress(function (response, textStatus, jqXHR) {
            if (response.error) {
                conn._error(response.error);
                deferred.reject(this, 'error');
            } else {
                if (response.auth) {
                    document.location = conn.url + '?cmd=authorise&source=' + response.auth + '&state=' + btoa(document.location);
                    return;
                }
                if (response.sys)
                    conn.max_upload_size = response.sys.max_upload_size;
                if (response.cwd)
                    conn.cwd = response.cwd;
                if (response.tree) {
                    $.each(response.tree, function (index, item) {
                        conn._trigger('mkdir', [item]);
                    });
                }
                if (response.items) {
                    $.each(response.items, function (index, item) {
                        if (item.parent === conn.cwd.id)
                            conn._trigger('file', [item]);
                    });
                }
                if (response.unlink) {
                    $.each(response.unlink, function (index, item) {
                        conn._trigger('unlink', [item]);
                    });
                }
                if (response.rmdir) {
                    $.each(response.rmdir, function (index, item) {
                        conn._trigger('rmdir', [item]);
                    });
                }
                if (response.rename) {
                    $.each(response.rename, function (index, item) {
                        conn._trigger('rename', [index, item]);
                    });
                }
                if (response.progress)
                    conn._trigger('progress', [response.progress]);
                deferred.resolve(response, textStatus, jqXHR);
            }
        }).error(function (jqXHR, textStatus, error) {
            conn._error(JSON.parse(jqXHR.responseText).error);
            deferred.reject(jqXHR, textStatus, error);
        });
        return promise;
    };
    this.upload = function (parent, jsFile) {
        if (jsFile.size > this.max_upload_size) {
            alert('File is too big.  Increase your servers maximum allowed file upload size.');
            return false;
        }
        var formData = new FormData();
        var name = jsFile.name;
        var path = this._path(parent).replace(/\/+$/, '') + '/';
        var source = this._source(parent);
        if (jsFile.webkitRelativePath) {
            path += jsFile.webkitRelativePath.substring(0, jsFile.webkitRelativePath.lastIndexOf('/') + 1);
            formData.append('relativePath', jsFile.webkitRelativePath);
        }
        path += name;
        var file = {
            'id': this._target(source, path),
            'kind': 'file',
            'mime': jsFile.type,
            'modified': jsFile.lastModified / 1000,
            'name': jsFile.name,
            'parent': this._target(source, path.substring(0, path.lastIndexOf('/') + 1)),
            'target': parent,
            'path': path,
            'read': true,
            'write': true,
            'size': jsFile.size
        };
        formData.append('cmd', 'upload');
        formData.append('parent', parent);
        formData.append('file', jsFile);
        conn._trigger('upload', [file]);
        return $.ajax({
            url: this.url,
            type: 'POST',
            data: formData,
            cache: false,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (data) {
                if (data.tree) {
                    $.each(data.tree, function (index, item) {
                        conn._trigger('mkdir', [item]);
                    });
                }
                if (data.file)
                    conn._trigger('uploadDone', [file, data.file]);
            },
            error: function (jqXHR, textStatus) {
                conn._trigger('uploadError', [file, textStatus]);
            },
            xhr: function () {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total;
                        conn._trigger('uploadProgress', [file, percentComplete]);
                    }
                }, false);
                return xhr;
            }
        });
    };
    this.snatch = function (url) {
        var packet = {
            cmd: 'snatch',
            url: url,
            target: this.cwd.id
        };
        return this._send(packet);
    };
    this._target = function (source, path) {
        if (!source) return null;
        else if (typeof source === 'object') {
            path = Array.isArray(source) ? source[1] : source.path;
            source = Array.isArray(source) ? source[0] : source.source;
        }
        return btoa(source + ':' + path).replace(/\+/g, '-').replace(/\//g, '_').replace(/\=+$/, '');
    };
    this._source = function (target) {
        target = (target + '==').slice(0, target.length + (4 - target.length % 4) % 4);
        var raw = atob(target.replace(/-/g, '+').replace(/_/g, '/'));
        return raw.substr(0, raw.indexOf(':'));
    };
    this._path = function (target) {
        target = (target + '==').slice(0, target.length + (4 - target.length % 4) % 4);
        var raw = atob(target.replace(/-/g, '+').replace(/_/g, '/'));
        return raw.substr(raw.indexOf(':') + 1);
    };
    this.on = function (event, callback) {
        if (!(typeof callback === 'function'))
            return false;
        if (!this.events[event])
            this.events[event] = [];
        this.events[event].push(callback);
        return this;
    };
    this._trigger = function (event, args) {
        if (this.events[event]) {
            for (x in this.events[event])
                this.events[event][x].apply(this, args);
        }
    };
    this.getURL = function (target) {
        return this.url + '/' + this._source(target) + this._path(target);
    };
    this.get = function (target) {
        var packet = {
            'cmd': 'get',
            'target': target
        };
        window.location.href = this.url + '?' + $.param(packet);
    };
    this.tree = function (target, depth) {
        var packet = { 'cmd': 'tree' };
        if (target)
            packet.target = target;
        if (typeof depth !== 'undefined')
            packet.depth = depth;
        return this._send(packet).done(function (items) {
            $.each(items, function (index, item) {
                conn._trigger('mkdir', [item]);
            });
        });
    };
    this.open = function (target, tree, depth) {
        var packet = {
            'cmd': 'open',
            'tree': typeof tree === 'undefined' ? false : tree
        };
        if (this.filter)
            packet.filter = this.filter;
        if (typeof depth !== 'undefined')
            packet.depth = depth;
        if (target)
            packet.target = target;
        if (this.with_meta)
            packet.with_meta = true;
        return this._send(packet).done(function (data) {
            conn._trigger('open', [data.cwd, data.files]);
        });
    };
    this.mkdir = function (parent, name) {
        var packet = {
            'cmd': 'mkdir',
            'parent': parent,
            'name': name
        };
        return this._send(packet);
    };
    this.rmdir = function (target) {
        var packet = {
            'cmd': 'rmdir',
            'target': target,
            'recurse': true
        };
        return this._send(packet).done(function (data) {
            if (data.ok === true)
                conn._trigger('rmdir', [target]);
        });
    };
    this.unlink = function (target) {
        var packet = {
            'cmd': 'unlink',
            'target': target
        };
        return this._send(packet).done(function (data) {
            if (data.items) {
                $.each(data.items, function (index, item) {
                    conn._trigger('unlink', [item]);
                });
            }
        });
    };
    this.copy = function (fromTarget, toTarget) {
        var packet = {
            'cmd': 'copy',
            'from': fromTarget,
            'to': toTarget
        };
        return this._send(packet);
    };
    this.move = function (fromTarget, toTarget) {
        var packet = {
            'cmd': 'move',
            'from': fromTarget,
            'to': toTarget
        };
        return this._send(packet);
    };
    this.rename = function (target, name) {
        var packet = {
            'cmd': 'rename',
            'target': target,
            'name': name
        };
        if (this.with_meta)
            packet.with_meta = true;
        return this._send(packet);
    };
    this.get_meta = function (target, key) {
        var packet = {
            'cmd': 'get_meta',
            'target': target
        };
        if (key)
            packet.key = key;
        return this._send(packet);
    };
    this.set_meta = function (target, values) {
        var packet = {
            'cmd': 'set_meta',
            'target': target,
            'values': values
        };
        return this._send(packet);
    };
    this.search = function (target, query) {
        if (!target)
            return;
        var packet = {
            'cmd': 'search',
            'target': target,
            'query': query
        };
        return this._send(packet);
    };
};

$.fn.fileBrowser = function (arg1, arg2, arg3) {
    var host = this.get(0);
    host.roots = {};
    if (host._render) {
        switch (arg1) {
            case 'selected':
                return host.selected();
            case 'get':
                return host;
            case 'selectNextItem':
                return host.selectItem(host.selected(true).next().attr('id'));
            case 'selectPrevItem':
                return host.selectItem(host.selected(true).prev().attr('id'));
            case 'delete':
                host.conn.unlink([arg2]);
                break;
            case 'update':
                host.conn.set_meta(arg2, arg3).done(function (data) {
                    if (data.ok)
                        $.extend($('#' + arg2).data('file').meta, arg3);
                });
                break;
            case 'rename':
                host.conn.rename(arg2, arg3);
                break;
            case 'meta':
                return host.conn.get_meta(arg2);
        }
        return this;
    } else {
        host.focus = null;
        host.cwd = null;
        host.clipboard = [];
        host.datetime = function (time) {
            if (!time)
                return;
            if (typeof time === 'number')
                time = new Date(time * 1000);
            return host.monthNames[time.getMonth()] + ' ' + time.getDay() + ', ' + time.getFullYear() + ' ' + time.getHours() + ':' + time.getMinutes();
        };
        host.selected = function (returnObjects) {
            var selected = [];
            var selectedItems = host.itemsDIV.children('.selected');
            if (returnObjects === true)
                return selectedItems;
            selectedItems.each(function (index, item) {
                var file = $(item).data('file');
                if (file.previewLink) {
                    file.previewLink = file.previewLink.replace(/\{\$(\w)}/g, function (match, sub) {
                        return host.settings.previewsize[sub];
                    });

                }
                selected.push(file);
            });
            return selected;
        };
        host.selectItem = function (target) {
            var item = host.itemsDIV.children().removeClass('selected').filter('#' + target);
            if (item.length > 0) {
                var file = $(item).data('file');
                if (file.previewLink) {
                    file.previewLink = file.previewLink.replace(/\{\$(\w)}/g, function (match, sub) {
                        return host.settings.previewsize[sub];
                    });
                }
                item.addClass('selected');
                return file;
            }
            return false;
        };
        host.select = function () {
            var selected = host.selected();
            var data = [selected];
            if (host.settings.userpanel) {
                var userData = {};
                $(host.settings.userpanel).find('[name]').each(function (index, item) {
                    if (item.type === 'checkbox') {
                        userData[item.name] = item.checked;
                    } else if (item.value) {
                        userData[item.name] = item.value;
                    }
                });
                data.push(userData);
            }
            $(host).trigger('select', data);
        };
        host._upload = function (folder) {
            var selectBUTTON = $('<input type="file" multiple>');
            if (folder === true) {
                selectBUTTON.attr('webkitdirectory', '');
                selectBUTTON.attr('mozdirectory', '');
                selectBUTTON.attr('directory', '');
            }
            selectBUTTON.on('change', function (event) {
                host.initProgress(event.target.files.length);
                $.each(event.target.files, function (index, jsFile) {
                    host.conn.upload(host.conn.cwd.id, jsFile);
                });
            });
            selectBUTTON.click();
        };
        host._snatch = function (url) {
            var statusDIV = $('<div>').append([
                $('<i class="fa fa-spinner fa-spin">').css({ 'font-size': '32px', 'float': 'left' }),
                $('<div>').html('Downloading ' + url)
            ]).browserDialog({
                title: 'Status',
                buttons: []
            });
            this.conn.snatch(url).done(function (data) {
                if (!data.ok)
                    $('<div>').html(data.reason).browserDialog({ title: 'Error snatching file' });
            }).always(function () {
                statusDIV.fadeOut(function () {
                    $(this).remove();
                });
            });
        };
        host._contextMenu = function (event, options) {
            var posX = event.pageX;
            var posY = event.pageY;
            var orgTarget = event.target;
            host.menuDIV.empty();
            $.each(options, function (index, item) {
                var itemDIV = $('<div class="fb-menu-item">');
                if (item === 'spacer') {
                    itemDIV.addClass('spacer');
                } else {
                    itemDIV.append([
                        $('<div class="fb-menu-item-icon fa">').addClass('fa-' + item.icon),
                        $('<div class="fb-menu-item-content">').html(item.label)
                    ]);
                    if (item.action)
                        itemDIV.click(function () {
                            item.action(orgTarget);
                        });
                }
                host.menuDIV.append(itemDIV);
            });
            if (posX + host.menuDIV.width() > window.innerWidth)
                posX = window.innerWidth - host.menuDIV.width();
            if (posY + host.menuDIV.height() > window.innerHeight)
                posY = window.innerHeight - host.menuDIV.height();
            host.menuDIV.css({ left: posX, top: posY });
            if (!host.menuDIV.is(':visible'))
                host.menuDIV.fadeIn();
            return false;
        };
        host._mkdir = function (parent) {
            var folderINPUT = $('<input type="text" placeholder="new folder name">').css('width', '100%');
            $('<div>').append(folderINPUT)
                .browserDialog({
                    title: 'New folder',
                    buttons: [
                        {
                            label: 'Create',
                            action: function () {
                                var name = $(this).children('input').val();
                                if (name) {
                                    var chevronDIV = $('#' + parent).children('.fb-tree-item-chevron');
                                    if (!chevronDIV.hasClass('childless') && !chevronDIV.hasClass('expanded'))
                                        chevronDIV.click();
                                    host.conn.mkdir(parent, name);
                                }
                            },
                            default: true
                        },
                        {
                            label: 'Cancel'
                        }
                    ]
                });
            folderINPUT.focus();
        };
        host._rename = function (target, info) {
            var obj = $('#' + target);
            obj.data('file', info).attr('id', info.id);
            if (obj.hasClass('fb-tree-item'))
                obj.children('.fb-tree-item-content').children('span').html(info.name);
            else if (obj.hasClass('fb-item'))
                obj.children('.fb-item-label').html(info.name);
        };
        host._delete = function (target) {
            $('<div>').html('Are you sure you want to delete this folder and it\'s contents?')
                .browserDialog({
                    title: 'Folder delete confirmation',
                    buttons: [
                        {
                            label: 'Yes',
                            action: function () {
                                host.conn.rmdir(target.id);
                            },
                            default: true
                        },
                        {
                            label: 'No'
                        }
                    ]
                });
        };
        host._render = function (viewMode) {
            host.mainDIV = $('<div class="fb-main">').addClass(viewMode);
            host.layoutDIV = $('<div class="fb-layout">').appendTo(host.mainDIV);
            host.headerDIV = $('<div class="fb-hdr">').appendTo(host.layoutDIV).html([
                $('<div class="fb-hdr-item">'),
                $('<div class="fb-hdr-item">').html('Name'),
                $('<div class="fb-hdr-item">').html('Type'),
                $('<div class="fb-hdr-item">').html('Size'),
                $('<div class="fb-hdr-item">').html('Date')
            ]);
            host.itemsDIV = $('<div class="fb-items">').appendTo(host.layoutDIV);
            host.menuDIV = $('<div class="fb-context-menu">');
            host.titleDIV = $('<div class="fb-topbar-title">').html(host.settings.title);
            host.searchINPUT = $('<input type="text" placeholder="Search...">');
            host.searchBUTTON = $('<button>').html('Search');
            if (host.settings.topbar) {
                host.topbarDIV = $('<div class="fb-topbar">')
                    .append(host.titleDIV, $('<div class="fb-search">').append(host.searchINPUT, host.searchBUTTON));
                if (typeof host.settings.tools === 'object') {
                    host.topbarToolsDIV = $('<div class="fb-topbar-tools">');
                    for (x in host.settings.tools) {
                        var toolDIV = $('<div class="fb-topbar-tool">');
                        toolDIV.append($('<i class="fa">').addClass('fa-' + host.settings.tools[x].icon));
                        toolDIV.click(host.settings.tools[x].click);
                        host.topbarToolsDIV.append(toolDIV);
                    }
                    host.topbarDIV.append(host.topbarToolsDIV);
                }
            }
            host.leftDIV = $('<div class="fb-left">');
            host.controlDIV = $('<div class="fb-tree-control">').appendTo(host.leftDIV);
            host.treeDIV = $('<div class="fb-tree">').appendTo(host.leftDIV);
            host.dropZone = $('<div class="fb-dropzone">').hide().appendTo(host.mainDIV);
            host.dropMsg = $('<div class="fb-dropmsg">').appendTo(host.dropZone);
            host.dropTarget = $('<div class="fb-droptarget">').appendTo(host.dropZone);
            if (host.settings.upload) {
                host.newBUTTON = $('<button class="fb-btn-new">').html('New').appendTo(host.controlDIV);
                host.uploadDIV = $('<div class="fb-upload">').appendTo(host.leftDIV);
                host.uploadDoneSPAN = $('<span>').html(0);
                host.uploadTotalSPAN = $('<span>').html(0);
                host.uploadProgressDIV = $('<div class="pct">').css('width', '0%');
                host.uploadDIV.append([
                    $('<div class="fb-upload-left">').append(host.uploadDoneSPAN, ' of ', host.uploadTotalSPAN),
                    $('<div class="fb-upload-right">').append($('<div class="fb-upload-progress">').append(host.uploadProgressDIV))
                ]);
                host.newBUTTON.click(function (event) {
                    var options = [
                        {
                            icon: 'folder',
                            label: 'Folder',
                            action: function (event) {
                                host._mkdir(host.conn.cwd.id);
                            }
                        },
                        'spacer',
                        {
                            icon: 'upload',
                            label: 'File Upload',
                            action: host._upload
                        },
                        {
                            icon: 'upload',
                            label: 'Folder Upload',
                            action: function () {
                                host._upload(true);
                            }
                        },
                        {
                            icon: 'cloud-download',
                            label: 'Snatch File',
                            action: function () {
                                var urlINPUT = $('<input type="text" placeholder="source URL...">').css('width', '400px');
                                $('<div>').append(urlINPUT).browserDialog({
                                    title: 'Snatch file from URL',
                                    buttons: [
                                        {
                                            label: 'Snatch',
                                            action: function () {
                                                host._snatch(urlINPUT.val());
                                            },
                                            default: true
                                        },
                                        {
                                            label: 'Cancel'
                                        }
                                    ]
                                });
                                urlINPUT.focus();
                            }
                        },
                        'spacer',
                        {
                            icon: 'image',
                            label: 'Image'
                        },
                        {
                            icon: 'file-text',
                            label: 'Document'
                        }
                    ];
                    var testInput = $('<input type="file" directory mozdirectory webkitdirectory>');
                    if (!(testInput.get(0).webkitdirectory || testInput.get(0).mozdirectory || testInput.get(0).directory))
                        options.splice(3, 1);
                    return host._contextMenu(event, options);
                });
            }
            $(host).addClass('fb-container')
                .append(host.menuDIV, host.topbarDIV, host.leftDIV, host.mainDIV)
                .css({ position: "relative", width: host.settings.width, height: host.settings.height });
            host.mainDIV.click(function (event) {
                if (event.target === this && !event.ctrlKey)
                    host.itemsDIV.find('.selected').removeClass('selected');
            }).on('contextmenu', function () {
                if (event.ctrlKey)
                    return;
                if (event.target === this) {
                    var options = [];
                    if (host.itemsDIV.children().length > 0) {
                        options.push({
                            icon: 'check-square-o',
                            label: 'Select All',
                            action: function () {
                                host.itemsDIV.children().addClass('selected');
                            }
                        });
                    }
                    options.push({
                        icon: 'refresh',
                        label: 'Refresh',
                        action: function () {
                            host.conn.open(host.itemsDIV.attr('data-id'));
                        }
                    });
                    if (host.clipboard.length > 0) {
                        options.push('spacer');
                        options.push({
                            icon: 'paste',
                            label: 'Paste',
                            action: function () {
                                host.paste(host.itemsDIV);
                            }
                        });
                    }
                    return host._contextMenu(event, options);
                }
            }).on('dragenter', function (e) {
                e.preventDefault();
                var c = e.originalEvent.dataTransfer.items.length;
                var msg = c + ' file' + (c > 1 ? 's' : '');
                host.dropMsg.html('Release to upload ' + msg);
                host.dropZone.show();
            });
            host.dropTarget.on('dragover', function (e) {
                e.preventDefault();
            }).on('dragleave', function (e) {
                e.preventDefault();
                host.dropZone.hide();
            }).on('drop', function (e) {
                e.preventDefault();
                $.each(e.originalEvent.dataTransfer.files, function (index, jsFile) {
                    host.conn.upload(host.conn.cwd.id, jsFile);
                });
                host.dropZone.hide();
            });
            if (host.settings.showinfo || host.settings.userpanel) {
                host.rightDIV = $('<div class="fb-right">');
                $(host).append(host.rightDIV);
                if (host.settings.showinfo) {
                    host.infoDIV = $('<div class="fb-info">');
                    host.rightDIV.append(host.infoDIV);
                }
                if (host.settings.userpanel) {
                    var userDIV = $('<div class="fb-user">').append(host.settings.userpanel);
                    host.rightDIV.append(userDIV);
                    host.infoDIV.css('bottom', userDIV.height());
                }
            }
            host.searchBUTTON.click(function () {
                host.itemsDIV.empty();
                host._search(host.searchINPUT.val());
            });
            host.searchINPUT.keypress(function (e) {
                if (e.keyCode === 13)
                    host.searchBUTTON.click();
            });
        };
        host._dir = function (item) {
            if ($('#' + item.id).length === 0) {
                var chevronDIV = $('<div class="fb-tree-item-chevron">');
                var itemChildrenDIV = $('<div class="fb-tree-item-children">');
                var itemIconI = $('<i class="fa fa-folder">');
                var itemDIV = $('<div class="fb-tree-item">').append([
                    chevronDIV,
                    $('<div class="fb-tree-item-content">').append([
                        itemIconI,
                        $('<span class="fb-tree-item-label">').html(item.name)
                    ]),
                    itemChildrenDIV
                ]).attr('id', item.id).attr('data-id', item.id).data('file', item).attr('draggable', true);
                var parent = $('#' + item.parent);
                if (parent.length > 0) {
                    var parentChevronDIV = parent.children('.fb-tree-item-chevron');
                    if (parentChevronDIV.hasClass('childless')) {
                        parentChevronDIV.removeClass('childless').click();
                    }
                    parent.children('.fb-tree-item-children')
                        .append(itemDIV)
                        .append(parent.children('.fb-tree-item-children').children('div').sort(function (a, b) {
                            var aStr = $(a).children('.fb-tree-item-content').children('span').html();
                            var bStr = $(b).children('.fb-tree-item-content').children('span').html();
                            return (aStr > bStr) - (aStr < bStr);
                        }));
                } else {
                    host.treeDIV.append(itemDIV);
                }
                itemDIV.children('.fb-tree-item-content').click(function () {
                    host.treeDIV.find('.selected').removeClass('selected');
                    $(this).parent().addClass('selected');
                    host.itemsDIV.html($('<div class="fb-items-loading">'));
                    host.conn.open($(this).parent().attr('id'), false, 0).done(function () {
                        if (host.settings.autoexpand && !chevronDIV.hasClass('expanded') && itemChildrenDIV.children().length > 0)
                            chevronDIV.click();
                    });
                });
                chevronDIV.click(function () {
                    var funcLoaded = function (obj) {
                        obj.toggleClass('expanded').removeClass('dir-loading');
                        if (obj.hasClass('expanded')) {
                            obj.siblings('.fb-tree-item-children').slideDown();
                        } else {
                            obj.siblings('.fb-tree-item-children').slideUp(function () {
                                $(this).find('.fb-tree-item-chevron.expanded').removeClass('expanded').siblings('.fb-tree-item-children').hide();
                            });
                        }
                    };
                    if ($(this).siblings('.fb-tree-item-children').children().length > 0) {
                        funcLoaded($(this));
                    } else {
                        var obj = $(this);
                        var pathID = $(this).parent().attr('id');
                        obj.addClass('dir-loading');
                        host.conn.tree(pathID, 0).done(function () {
                            funcLoaded(obj);
                        });
                    }
                });
                if (!item.dirs > 0)
                    chevronDIV.addClass('childless');
                itemDIV.on('contextmenu', function (event) {
                    if (event.ctrlKey)
                        return;
                    var item = $(this);
                    var file = item.data('file');
                    if (!item.hasClass('selected'))
                        item.click();
                    var options = [];
                    if (file.write) {
                        options.push({
                            icon: 'folder',
                            label: 'New Folder',
                            action: function () {
                                host._mkdir(file.id);
                            }
                        });
                        options.push('spacer');
                    }
                    if (file.read) {
                        options.push({
                            icon: 'copy',
                            label: 'Copy',
                            action: function () {
                                host.copy(itemDIV);
                            }
                        });
                    }
                    if (file.write) {
                        options.push({
                            icon: 'scissors',
                            label: 'Cut',
                            action: function () {
                                host.cut(itemDIV);
                            }
                        });
                        if (host.clipboard.length > 0) {
                            options.push({
                                icon: 'paste',
                                label: 'Paste',
                                action: function () {
                                    host.paste(itemDIV);
                                }
                            });
                        }
                        options.push('spacer');
                        options.push({
                            icon: 'font',
                            label: 'Rename',
                            action: function () {
                                host.rename($('#' + file.id));
                            }
                        });
                        options.push('spacer');
                        options.push({
                            icon: 'trash',
                            label: 'Remove',
                            action: function () {
                                host._delete(file);
                            }
                        });
                    }
                    return host._contextMenu(event, options);
                }).on('dragstart', function (event) {
                    if ($(event.target).hasClass('rename'))
                        return false;
                    event.originalEvent.dataTransfer.setData('text', $(event.target).attr('id'));
                }).children('.fb-tree-item-content')
                    .on('dragover', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        $(this).addClass('highlight');
                    }).on('dragleave', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        $(this).removeClass('highlight');
                    }).on('drop', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        $(this).removeClass('highlight');
                        var selected = event.originalEvent.dataTransfer.getData('text').split(',');
                        var to = $(this).parent().attr('id');
                        $.each(selected, function (index, from) {
                            if (!from || !to || from === to)
                                return;
                            if (event.originalEvent.dataTransfer.dropEffect === 'copy')
                                host.conn.copy(from, to);
                            else
                                host.conn.move(from, to);
                        });
                    });
            }
            if (host.cwd && item.id === host.cwd.id)
                host._expand_dir(item.id);
        };
        host._expand_dir = function (id) {
            var current = $('#' + id).addClass('selected');
            if (!current.length > 0)
                return;
            do {
                var item = current.children('.fb-tree-item-chevron:not(.expanded)');
                item.click();
            } while ((current = current.parent().parent('.fb-tree-item')).length > 0);
        };
        host._rmdir = function (target) {
            var item = $('#' + target);
            var container = item.parent();
            var parent = container.parent();
            if (target === host.conn.cwd.id)
                host.conn.open(parent.attr('id'));
            item.remove();
            if (container.children().length === 0)
                parent.children('.fb-tree-item-chevron').removeClass('expanded').addClass('childless');
        };
        host._open = function (cwd, items) {
            host.focus = null;
            host.cwd = cwd;
            host._expand_dir(cwd.id);
            host.itemsDIV.empty().attr('data-id', host.conn.cwd.id);
            host.treeDIV.find('.selected').removeClass('selected');
            $('#' + cwd.id).addClass('selected');
            $.each(items, function (index, item) {
                host._item(item);
            });
            $(host).trigger('chdir', [cwd]);
            if (host.settings.saveCWD === true) document.cookie = 'filebrowser.' + host.id + '.cwd=' + cwd.id;
        };
        host._date = function (date) {
            var d = new Date(date * 1000);
            return d.toUTCString();
        };
        host._size = function (bytes, type, precision, exclude_suffix) {
            var value = bytes, suffix = 'bytes', prec = 0;
            if (typeof type === 'undefined') {
                if (bytes < Math.pow(2, 10))
                    type = 'B';
                else if (bytes < Math.pow(2, 20))
                    type = 'K';
                else if (bytes < Math.pow(2, 30))
                    type = 'M';
                else
                    type = 'G';
            }
            switch (type.toUpperCase()) {
                case 'K':
                    value = bytes / Math.pow(2, 10);
                    suffix = 'KB';
                    break;
                case 'M':
                    value = bytes / Math.pow(2, 20);
                    suffix = 'MB';
                    prec = 2;
                    break;
                case 'G':
                    value = bytes / Math.pow(2, 30);
                    suffix = 'GB';
                    prec = 2;
                    break;
                case 'T':
                    value = bytes / Math.pow(2, 40);
                    suffix = 'TB';
                    prec = 2;
                    break;
            }
            if (typeof precision !== 'undefined')
                prec = precision;
            return value.toLocaleString('en', { maximumFractionDigits: prec }) + (exclude_suffix === true ? '' : ' ' + suffix);
        };
        host._item = function (item) {
            if (item.kind !== 'file') {
                console.log('Skipping non-file: ' + item.name);
                return;
            }
            var dataType = item.mime ? item.mime.replace(/\./g, '_').replace(/\//g, '-') : null;
            var thumbDIV = $('<div class="fb-item-thumb fileicon">').attr('data-type', dataType);
            var itemDIV = $('<div class="fb-item">').append([
                thumbDIV,
                $('<div class="fb-item-label">').html(item.name),
                $('<div class="fb-item-type">').html(item.mime),
                $('<div class="fb-item-size">').html(host._size(item.size)),
                $('<div class="fb-item-date">').html(host._date(item.modified))
            ]).attr('draggable', true).attr('id', item.id).data('file', item);
            host.itemsDIV.append(itemDIV);
            if (item.downloadLink)
                itemDIV.attr('data-uri', item.downloadLink);
            if (item.previewLink) {
                var image = new Image();
                var params = {
                    w: thumbDIV.width(),
                    h: thumbDIV.height()
                };
                image.onload = function () {
                    thumbDIV.css('background-image', "url('" + this.src + "')");
                };
                image.src = item.previewLink.replace(/\{\$(\w)}/g, function (match, sub) {
                    return params[sub];
                });
            }
            host.itemsDIV.append(host.itemsDIV.children('div.fb-item').sort(function (a, b) {
                return (a.lastChild.innerHTML > b.lastChild.innerHTML) - (a.lastChild.innerHTML < b.lastChild.innerHTML);
            }));
            itemDIV.click(function (event) {
                if (event.ctrlKey && host.settings.allowmultiple) {
                    host.focus = $(this).toggleClass('selected');
                } else if (event.shiftKey && host.focus && host.settings.allowmultiple) {
                    var start = host.itemsDIV.children('.fb-item').index(host.focus);
                    var end = host.itemsDIV.children('.fb-item').index(this);
                    host.itemsDIV.children('.fb-item').slice(end > start ? start + 1 : end, start > end ? start : end + 1).addClass('selected');
                } else {
                    host.itemsDIV.find('.selected').removeClass('selected');
                    host.focus = $(this).addClass('selected');
                }
                var selected = host.itemsDIV.children('.fb-item.selected');
                if (host.infoDIV) {
                    if (selected.length > 1) {
                        host.infoDIV.html($('<div class="fb-info-label">').html(selected.length + ' items selected'));
                    } else {
                        var info = selected.data('file');
                        var dataType = info.mime ? info.mime.replace(/\./g, '_').replace(/\//g, '-') : null;
                        var iconDIV = $('<div class="fb-info-icon fileicon">').attr('data-type', dataType);
                        var nameDIV = $('<div class="fb-info-name">').html(info.name);
                        var previewDIV = $('<div class="fb-info-preview">');
                        var sizeDIV = $('<div class="fb-info-item">').append($('<label>').html('Size:'), $('<span>').html(humanFileSize(info.size)));
                        var typeDIV = $('<div class="fb-info-item">').append($('<label>').html('Type:'), $('<span>').html(info.mime));
                        var modifiedDIV = $('<div class="fb-info-item">').append($('<label>').html('Date Modified:'), $('<span>').html((new Date(info.modified * 1000)).toLocaleString()));
                        host.infoDIV.empty().append(iconDIV, nameDIV, previewDIV, typeDIV, sizeDIV, modifiedDIV);
                        var params = { w: previewDIV.width(), h: null };
                        if (info.previewLink) {
                            var url = info.previewLink.replace(/crop=true/, '').replace(/\{\$(\w)}/g, function (match, sub) {
                                return params[sub];
                            });
                            previewDIV.html($('<img>').attr('src', url));
                        }
                        if (info.write === false)
                            nameDIV.append($('<i class="fa fa-lock">'));
                    }
                }
                $(host).trigger('selection', [selected]);
            }).dblclick(function () {
                host.select();
            }).on('contextmenu', function (event) {
                if (event.ctrlKey)
                    return;
                var item = $(this);
                var file = item.data('file');
                if (!item.hasClass('selected'))
                    item.click();
                var options = [];
                if (file.read) {
                    options.push({
                        icon: 'download',
                        label: 'Download',
                        action: function () {
                            host.conn.get(item.attr('id'));
                        }
                    });
                    options.push('spacer');
                    options.push({
                        icon: 'copy',
                        label: 'Copy',
                        action: function () {
                            host.copy(host.itemsDIV.children('.selected'));
                        }
                    });
                }
                if (file.write) {
                    options.push({
                        icon: 'scissors',
                        label: 'Cut',
                        action: function () {
                            host.cut(host.itemsDIV.children('.selected'));
                        }
                    });
                    options.push('spacer');
                    options.push({
                        icon: 'font',
                        label: 'Rename',
                        action: function () {
                            host.rename($('#' + file.id));
                        }
                    });
                    options.push({
                        icon: 'comment',
                        label: 'Comment',
                        action: function () {
                            var askComment = function (value) {
                                var commentTEXTAREA = $('<textarea>').html(value);
                                $('<div>').append(commentTEXTAREA).browserDialog({
                                    title: 'Comment',
                                    buttons: [
                                        {
                                            label: 'Save',
                                            action: function () {
                                                var comment = commentTEXTAREA.val();
                                                host.conn.set_meta(file.id, { 'comment': comment }).done(function (data) {
                                                    if (data.ok)
                                                        file.meta.comment = comment;
                                                });
                                            }
                                        }
                                    ]
                                });
                            };
                            if (file.meta) {
                                askComment(file.meta.comment);
                            } else {
                                host.conn.get_meta(file.id, 'comment').done(function (data) {
                                    if (data.ok)
                                        askComment(data.meta);
                                });
                            }
                        }
                    });
                    options.push('spacer');
                    options.push({
                        icon: 'trash',
                        label: 'Remove',
                        action: host.delete
                    });
                }
                return host._contextMenu(event, options);
            }).on('dragstart', function (event) {
                var selected = [];
                if (!event.ctrlKey) {
                    if (!$(event.target).hasClass('selected'))
                        host.itemsDIV.children('.selected').removeClass('selected');
                    $(event.target).addClass('selected');
                }
                host.itemsDIV.children('.selected').each(function (index, item) {
                    selected.push(item.id);
                });
                event.originalEvent.dataTransfer.setData('text', selected);
                event.originalEvent.dataTransfer.effectAllowed = 'copyMove';
            });
        };
        host._rmfile = function (target) {
            $('#' + target).remove();
        };
        host._search = function (query) {
            if (!host.cwd)
                return;
            this.conn.search(host.cwd.id, query)
                .done(function (items) {
                    for (x in items)
                        host._item(items[x]);
                });
        };
        host.rename = function (obj) {
            var file = $(obj).data('file');
            var renameINPUT = $('<input type="text" />').val(file.name);
            renameINPUT.click(function (event) {
                event.stopPropagation();
                return false;
            });
            $(obj).addClass('rename').removeClass('selected');
            renameINPUT.data('target', file).on('select', function (event) {
                event.stopPropagation();
                return false;
            }).on('focus', function (event) {
                event.target.select();
            }).on('keypress', function (event) {
                if (event.which === 13) {
                    host.conn.rename($(this).data('target').id, $(event.target).val()).done(function (data) {
                        if (data.ok)
                            renameINPUT.blur();
                    });
                }
            });
            if ($(obj).hasClass('fb-tree-item')) {
                $(obj).children('.fb-tree-item-content').children('span').hide().after(renameINPUT);
                renameINPUT.on('blur', function (event) {
                    $(this).prev('span').show().parent().parent().removeClass('rename');
                    $(this).remove();
                });
            } else if ($(obj).hasClass('fb-item')) {
                $(obj).children('.fb-item-label').hide().after(renameINPUT);
                renameINPUT.on('blur', function (event) {
                    $(this).prev('.fb-item-label').show().parent().removeClass('rename');
                    $(this).remove();
                });
            }
            renameINPUT.focus();
        };
        host.delete = function () {
            var selected = host.itemsDIV.find('.selected');
            if (selected.length > 0) {
                $('<div>').html('Really delete ' + selected.length + ' selected item(s).')
                    .browserDialog({
                        title: 'Delete confirmation',
                        buttons: [
                            {
                                label: 'Yes',
                                action: function () {
                                    var items = [];
                                    selected.each(function (index, item) {
                                        items.push($(item).attr('id'));
                                    });
                                    host.conn.unlink(items);
                                },
                                default: true
                            },
                            { label: 'No' }
                        ]
                    });
            }
        };
        host.cut = function (selected) {
            host.clipboard = [];
            if (selected.length > 0) {
                selected.each(function (idnex, item) {
                    host.clipboard.push(['cut', item.id]);
                    $(item).addClass('cut');
                });
            }
        };
        host.copy = function (selected) {
            host.clipboard = [];
            if (selected.length > 0) {
                selected.each(function (index, item) {
                    host.clipboard.push(['copy', item.id]);
                });
            }
        };
        host.paste = function (target) {
            if (host.clipboard.length > 0) {
                var o = $(target).attr('data-id');
                while ((item = host.clipboard.pop()) !== null) {
                    if (item[0] === 'cut') {
                        host.conn.move(item[1], o);
                    } else {
                        host.conn.copy(item[1], o);
                    }
                }
            }
        };
        host.initProgress = function (total) {
            if (host.uploadDIV.hasClass('working')) {
                host.uploadTotalSPAN.html(parseInt(host.uploadTotalSPAN.html()) + total);
            } else {
                host.uploadDoneSPAN.html(0);
                host.uploadTotalSPAN.html(total);
                host.uploadDIV.addClass('working').slideDown();
            }
        };
        host.progress = function (done) {
            var total = parseInt(host.uploadTotalSPAN.html());
            if (typeof done === 'undefined')
                done = parseInt(host.uploadDoneSPAN.html()) + 1;
            var pct = done / total * 100;
            host.uploadDoneSPAN.html(done);
            host.uploadProgressDIV.css('width', pct + '%');
            if (done >= parseInt(host.uploadTotalSPAN.html())) {
                host.uploadDIV.removeClass('working');
                setTimeout(function () {
                    host.uploadDIV.slideUp();
                }, 3000);
            }
        };
        host._mode = function (defmode) {
            var ca = document.cookie.split(';');
            for (x in ca) {
                var item = ca[x].trim().split('=');
                if (item[0] === 'filebrowser.' + host.id + '.mode')
                    return item[1];
            }
            return defmode;
        };
        host.settings = $.extend(true, {
            title: 'File Browser',
            width: '800px',
            height: '400px',
            mode: 'grid',
            allowmultiple: true,
            autoexpand: false,
            startDirectory: null,
            root: null,
            select: null,
            saveCWD: true,
            showinfo: false,
            userpanel: null,
            defaulttools: true,
            mimeFilter: null,
            previewsize: { w: 100, h: 100 },
            useMeta: false,
            tools: [],
            monthNames: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
            upload: true,
            topbar: true
        }, arg1);
        host.settings.mode = host._mode(host.settings.mode);
        if (host.settings.defaulttools) {
            host.settings.tools.unshift({
                icon: host.settings.mode === 'grid' ? 'th-large' : 'th-list',
                click: function () {
                    if (host.settings.mode === 'list') {
                        host.settings.mode = 'grid';
                        host.mainDIV.removeClass('list').addClass('grid');
                        $(this).children().removeClass('fa-th-list').addClass('fa-th-large');
                    } else {
                        host.settings.mode = 'list';
                        host.mainDIV.removeClass('grid').addClass('list');
                        $(this).children().removeClass('fa-th-large').addClass('fa-th-list');
                    }
                    document.cookie = 'filebrowser.' + host.id + '.mode=' + host.settings.mode;
                }
            });
        }
        host.conn = new fbConnector(host.settings.connect, host.settings.mimeFilter, host.settings.useMeta);
        host.conn.on('mkdir', host._dir)
            .on('rmdir', host._rmdir)
            .on('file', host._item)
            .on('unlink', host._rmfile)
            .on('open', host._open)
            .on('rename', host._rename)
            .on('upload', function (file) {
                host._item(file);
                host.itemsDIV.children('#' + file.id).children('.fb-item-thumb').append($('<div class="fb-item-upload-pct">'));
            }).on('uploadProgress', function (file, pct) {
                var progressDIV = host.itemsDIV.children('#' + file.id).find('.fb-item-upload-pct');
                progressDIV.css('height', (1 - pct) * 100 + '%');
            }).on('uploadDone', function (file, data) {
                var itemDIV = host.itemsDIV.children('#' + file.id);
                if (data.previewLink) {
                    var thumbDIV = itemDIV.children('.fb-item-thumb');
                    var image = new Image();
                    var params = {
                        w: thumbDIV.width(),
                        h: thumbDIV.height()
                    };
                    image.onload = function () {
                        thumbDIV.css('background-image', 'url("' + image.src + '")');
                    };
                    image.src = data.previewLink.replace(/\{\$(\w)}/g, function (match, sub) {
                        return params[sub];
                    });
                }
                itemDIV.data('file', data).find('.fb-item-upload-pct').remove();
                host.progress();
            }).on('uploadError', function (file) {
                host.itemsDIV.children('#' + file.id).addClass('uploadError').find('.fb-item-upload-pct').removeAttr('style');
            }).on('progress', function (packet) {
                if (packet.data.init) {
                    host.initProgress(packet.data.init);
                } else {
                    if (packet.data.kind === 'dir') {
                        host.progress();
                    }
                }
            });
        host._render(host.settings.mode);
        host.conn.tree(host.conn._target(host.settings.root), 0).done(function () {
            var startDir = host.treeDIV.children().first().attr('data-id');
            if (host.settings.startDirectory) {
                let matches = host.settings.startDirectory.match(/^\/(\w+)(\/?.*)/);
                if (matches) startDir = host.conn._target(matches[1], matches[2] || '/');
            } else if (host.settings.saveCWD === true) {
                let matches = document.cookie.match(new RegExp("filebrowser\." + host.id + "\.cwd=([^;\s$]+)"));
                if (matches) startDir = matches[1];
            }
            host.conn.open(startDir, true).done(function (data) {
                if (host.settings.select)
                    $('#' + host.conn._target(host.conn._source(startDir), host.conn._path(startDir) + '/' + host.settings.select)).addClass('selected');
            });
        });
        $(host).click(function (event) {
            if (!event.isTrigger && host.menuDIV.is(':visible')) {
                host.menuDIV.fadeOut();
            }
        });
        $(window).on('keydown', function (event) {
            if (event.target !== window.document.body)
                return;
            if (event.keyCode === 114 || event.ctrlKey && event.keyCode === 70) {
                host.searchINPUT.focus();
                event.preventDefault();
            } else if (event.keyCode === 113) {
                var selected = host.selected(true);
                if (selected.length > 0)
                    host.rename(selected[0]);
            } else if (event.keyCode === 27) {
                $(host).find('input').blur();
            } else if (event.ctrlKey) {
                switch (event.keyCode) {
                    case 65:
                        if (host.settings.allowmultiple)
                            host.itemsDIV.children().addClass('selected');
                        break;
                    case 67:
                        host.copy(host.itemsDIV.children('.selected'));
                        break;
                    case 86:
                        host.paste(host.itemsDIV);
                        break;
                    case 88:
                        host.cut(host.itemsDIV.children('.selected'));
                        break;
                }
                return false;
            } else if (event.keyCode === 46) {
                host.delete();
            }
        });
    }
    return this;
};