/**
 * jQuery Ajax Stream Support
 *
 * This jQuery function extends the standard AJAX function to allow progress events for streaming over HTTP.
 *
 * @param {string} url The URL
 * @param {object} options Stream options
 * @returns {object} The object itself
 */
jQuery.stream = function (url, options) {
    var callbacks = {};
    if (typeof url === 'object') {
        options = url;
        url = options.url;
    }
    var worker = {
        readOffset: 0,
        xhr: null,
        read: function () {
            if (this.xhr.response.length <= this.readOffset)
                return null;
            var pos = this.xhr.response.substr(this.readOffset).indexOf("\0");
            if (pos <= 0)
                return null;
            var len = parseInt('0x' + this.xhr.response.substr(this.readOffset, pos));
            var type = this.xhr.response.substr(this.readOffset + pos + 1, 1);
            var part = this.xhr.response.substr(this.readOffset + pos + 2, len);
            if (part.length < len)
                return null;
            this.readOffset += part.length + pos + 2;
            if (type === 'a') part = JSON.parse(part);
            return part;
        },
        lastType: function (xhr) {
            return this.xhr.response.substr(this.readOffset + 1, 1);
        },
        last: function (xhr) {
            var type = this.xhr.response.substr(this.readOffset + 1, 1);
            var part = this.xhr.response.substr(this.readOffset + 2);
            if (type === 'a' || type === 'e') part = JSON.parse(part);
            return part;
        }
    };
    var ajax = jQuery.ajax(url, jQuery.extend(options, {
        beforeSend: function (request) {
            request.setRequestHeader("X-Request-Type", "stream");
        },
        xhr: function () {
            worker.xhr = new XMLHttpRequest();
            worker.xhr.upload.onprogress = function (event) {
                if (typeof callbacks.uploadProgress === 'function') callbacks.uploadProgress(event);
            };
            worker.xhr.onprogress = function (event) {
                var response;
                while ((response = worker.read()) !== null) {
                    if (typeof callbacks.progress === 'function')
                        callbacks.progress(response, event.statusText, ajax);
                }
            };
            worker.xhr.onloadend = function (event) {
                if (worker.lastType() === 'e') {
                    ajax.responseJSON = worker.last();
                    if (typeof callbacks.error === 'function')
                        callbacks.error(ajax, 'error', 'Internal Error');
                } else if (typeof callbacks.done === 'function')
                    callbacks.done(worker.last(), event.statusText, ajax);
            };
            return worker.xhr;
        }
    }));
    ajax.uploadProgress = function (cb) { callbacks.uploadProgress = cb; return this; };
    ajax.progress = function (cb) { callbacks.progress = cb; return this; };
    ajax.done = function (cb) { callbacks.done = cb; return this; };
    ajax.error = function (cb) { callbacks.error = cb; return this; };
    ajax.fail(function (xhr, status, statusText) {
        if (typeof callbacks.error === 'function')
            callbacks.error(xhr, status, statusText);
    });
    return ajax;
};

/**
 * The Hazaar MVC Data binder
 *
 * This is a simple JavaScript/jQuery data bindering function to bind object data to
 * HTML elements with automatic updates on change.
 * 
 * @param {object} data Object data
 * @param {string} name The name of the object (used internally for recursion)
 * @param {object} parent Parent object reference (used internally for recursion)
 * @returns {object} A new dataBinder object.
 */
var dataBinder = function (data, name, parent) {
    if (this === window) return new dataBinder(data);
    this._init(data, name, parent);
};

var dataBinderArray = function (data, name, parent) {
    if (this === window) return new dataBinderArray(data, name, parent);
    this._init(data, name, parent);
};

var dataBinderValue = function (name, value, label, parent) {
    this._name = name;
    this._value = parent.__nullify(value);
    this._label = label;
    this._other = null;
    this._enabled = true;
    this._parent = parent;
    Object.defineProperties(this, {
        "value": {
            set: function (value) {
                if (value !== null && typeof value === 'object' || value === this._value) return;
                var attr_name = this._parent._attr_name(this._name);
                this._value = this._parent.__nullify(value);
                this._other = null;
                this._parent._update(attr_name, true);
                this._parent._trigger(this._name, this);
            },
            get: function () {
                return this._value;
            }
        },
        "label": {
            set: function (value) {
                if (typeof value !== 'string' || value === this._label) return;
                this._label = value;
                this._parent._update(this._parent._attr_name(this._name), false);
            },
            get: function () {
                return this._label;
            }
        },
        "other": {
            set: function (value) {
                if (value === this._other) return;
                this._other = value;
                this._parent._update(this._parent._attr_name(this._name), false);
            },
            get: function () {
                return this._other;
            }
        },
        "parent": {
            get: function () {
                return this._parent;
            }
        }
    });
};

dataBinderValue.prototype.__name = function () {
    return this._parent._attr_name(this._name);
};

dataBinderValue.prototype.toString = function () {
    return this.label || this.value || this.other;
};

dataBinderValue.prototype.valueOf = function () {
    return this.value;
};

dataBinderValue.prototype.set = function (value, label, other, update) {
    value = this._parent.__nullify(value);
    if (value !== null && typeof value === 'object'
        || value === this._value && label === this._label
        && (typeof other === 'undefined' || other === this._other)) return;
    var attr_name = this._parent._attr_name(this._name);
    this._value = value;
    this._label = label;
    if (typeof other !== 'undefined') this._other = other;
    if (update !== false) {
        this._parent._update(attr_name, true);
        this._parent._trigger(this._name, this);
    }
    return this;
};

dataBinderValue.prototype.save = function (no_label) {
    if ((this.value !== null && this.label !== null && this.label !== '' || this.value === null && this.other !== null) && no_label !== true)
        return { "__hz_value": this.value, "__hz_label": this.label, "__hz_other": this.other };
    return this.value;
};

dataBinderValue.prototype.empty = function (update) {
    return this.set(null, null, null, update);
};

dataBinderValue.prototype.update = function () {
    var attr_name = this._parent._attr_name(this._name);
    this._parent._update(attr_name, true);
    this._parent._trigger(this._name, this);
};

dataBinderValue.prototype.enabled = function (value) {
    if (typeof value !== 'boolean') return this._enabled;
    return this._enabled = value;
};

dataBinder.prototype._init = function (data, name, parent) {
    this._jquery = jQuery({});
    this._name = name;
    this._parent = parent;
    this._attributes = {};
    this._watchers = {};
    this._watchID = 0;
    this._enabled = true;
    if (Object.keys(data).length > 0)
        for (var key in data) this.add(key, data[key]);
    Object.defineProperty(this, 'length', {
        get: function () {
            return Object.keys(this._attributes).length;
        }
    });
};

dataBinder.prototype.__name = function () {
    return this._attr_name();
};

dataBinder.prototype.__nullify = function (value) {
    if (typeof value === 'string' && value === '') value = null;
    return value;
};

dataBinder.prototype.__convert_type = function (key, value, parent) {
    if (typeof parent === 'undefined') parent = this;
    value = this.__nullify(value);
    if (Array.isArray(value))
        value = new dataBinderArray(value, key, parent);
    else if (value !== null && typeof value === 'object' && '__hz_value' in value) {
        if (typeof value.__hz_value === 'string' && value.__hz_value === '') value = null;
        else {
            let dba = new dataBinderValue(key, value.__hz_value, value.__hz_label, parent);
            if ('__hz_other' in value) dba.other = value.__hz_other;
            value = dba;
        }
    } else if (value !== null && !(value instanceof dataBinder
        || value instanceof dataBinderArray
        || value instanceof dataBinderValue)) {
        if (value !== null && typeof value === 'object' && value.constructor.name === 'Object') value = new dataBinder(value, key, parent);
        else if (typeof value !== 'object') value = new dataBinderValue(key, value, null, parent);
    }
    return value;
};

dataBinder.prototype.add = function (key, value) {
    var attr_name = this._attr_name(key);
    var trigger_name = this._trigger_name(attr_name);
    this._attributes[key] = this.__convert_type(key, value);
    this._update(attr_name, false);
    this._defineProperty(trigger_name, key);
};

dataBinder.prototype._trigger_name = function (attr_name) {
    return attr_name.replace(/[\[\]]/g, '_') + ':change';
};

dataBinder.prototype._defineProperty = function (trigger_name, key) {
    var attr_name = this._attr_name(key);
    Object.defineProperty(this, key, {
        configurable: true,
        set: function (value) {
            var attr = this._attributes[key];
            if (value instanceof dataBinder) value = value.save(); //Export so that we trigger an import to reset the value names
            value = this.__convert_type(key, value);
            if (value === null && attr && attr.other) attr.other = null;
            else if (value === null && attr instanceof dataBinder
                || (attr instanceof dataBinderValue ? attr.value : attr) === (value instanceof dataBinderValue ? value.value : value)
                && (attr && (!(attr instanceof dataBinderValue) || !(value instanceof dataBinderValue) || attr.label === value.label && attr.other === value.other)))
                return; //If the value or label has not changed, then bugger off.
            this._attributes[key] = value;
            this._jquery.trigger(trigger_name, [this, attr_name, value]);
            this._trigger(key, value);
            if (attr instanceof dataBinder && value instanceof dataBinder) {
                value._parent = this;
                value._copy_watchers(attr);
                value._trigger_diff(attr);
            }
        },
        get: function () {
            if (!this._attributes[key])
                this._attributes[key] = new dataBinderValue(key, null, null, this);
            return this._attributes[key];
        },
        parent: {
            get: function () {
                return this._parent;
            }
        }
    });
};

dataBinder.prototype._copy_watchers = function (source) {
    this._watchers = source._watchers;
    for (x in source._attributes) {
        if (source._attributes[x] instanceof dataBinder && x in this._attributes && this._attributes[x] instanceof dataBinder)
            this._attributes[x]._copy_watchers(source._attributes[x]);
    }
};

dataBinder.prototype.remove = function (key) {
    if (!(key in this._attributes))
        return;
    this._jquery.off(this._attr_name(key) + ':change');
    delete this[key];
    delete this._attributes[key];
};

dataBinder.prototype._attr_name = function (attr_name) {
    if (!this._parent) return attr_name;
    return this._parent._attr_name(this._name) + (typeof attr_name === 'undefined' ? '' : '.' + attr_name);
};

dataBinder.prototype._update = function (attr_name, do_update) {
    var attr_item = null;
    if ((pos = attr_name.lastIndexOf('.')) > 0) attr_item = this._attributes[attr_name.substr(pos + 1)];
    else attr_item = this._attributes[attr_name];
    jQuery('[data-bind="' + attr_name + '"]').each(function (index, item) {
        var o = jQuery(item);
        if (o.is("input, textarea, select")) {
            var attr_value = attr_item ? attr_item.value : null;
            if (o.attr('type') === 'checkbox')
                o.prop('checked', attr_value);
            else if (o.attr('data-bind-label') === 'true')
                o.val(attr_item ? attr_item.label : null);
            else if (o.attr('data-bind-other') === 'true')
                o.val(attr_item ? attr_item.other : null);
            else if (o.is("select")) {
                if (o.find('option[value="' + (attr_value === null ? '' : attr_value) + '"]').length > 0) o.val(attr_value);
            } else o.val(attr_value);
            if (do_update === true) o.trigger('update', [attr_name, attr_value]);
        } else {
            if (o.attr('data-bind-label') === 'false')
                o.html(attr_item ? attr_item.value : null);
            else if (o.attr('data-bind-other') === 'true')
                o.html(attr_item ? attr_item.other : null);
            else o.html(attr_item ? attr_item.toString() : '');
        }
    });
};

dataBinder.prototype._trigger = function (key, value) {
    if (key in this._watchers) {
        for (let x in this._watchers[key])
            this._watchers[key][x][0].call(this, key, value, this._watchers[key][x][1]);
    }
};

dataBinder.prototype._trigger_diff = function (source) {
    if (!source instanceof dataBinder) return;
    for (let x in this._attributes) {
        if (this._attributes[x] instanceof dataBinder) this._attributes[x]._trigger_diff(source[x]);
        else if ((this._attributes[x] instanceof dataBinderValue ? this._attributes[x].value : this._attributes[x])
            !== (source[x] instanceof dataBinderValue ? source[x].value : source[x])) {
            this._update(this._attr_name(x), true);
            this._trigger(x, this._attributes[x]);
        }
    }
};

dataBinder.prototype.resync = function (name) {
    for (let x in this._attributes)
        this._jquery.off(this._trigger_name(this._attr_name(x)));
    if (typeof name !== 'undefined') this._name = name;
    for (let x in this._attributes) {
        var trigger_name = this._trigger_name(this._attr_name(x));
        this._jquery.on(trigger_name, function (event, binder, attr_name, attr_value) {
            binder._update(attr_name, true);
        });
        this._defineProperty(trigger_name, x);
        if (x in this._attributes) {
            if (this._attributes[x] instanceof dataBinder || this._attributes[x] instanceof dataBinderArray)
                this._attributes[x].resync();
            else this._update(this._attr_name(x), false);
        }
    }
    return this;
};

dataBinder.prototype.save = function (no_label) {
    var attrs = jQuery.extend({}, this._attributes);
    for (let x in attrs) {
        if (attrs[x] instanceof dataBinder
            || attrs[x] instanceof dataBinderArray
            || attrs[x] instanceof dataBinderValue)
            attrs[x] = attrs[x].save(no_label);
    }
    return attrs;
};

dataBinder.prototype.watch = function (key, callback, args) {
    if ((match = key.match(/(\w+)\.([\w\.]*)/)) !== null)
        return match[1] in this._attributes && this._attributes[match[1]] instanceof dataBinder
            ? this._attributes[match[1]].watch(match[2], callback, args) : null;
    if (!(key in this._watchers))
        this._watchers[key] = {};
    var id = "" + this._watchID++;
    this._watchers[key][id] = [callback, args];
    return id;
};

dataBinder.prototype.unwatch = function (key, id) {
    if (typeof key === 'undefined') {
        this._watchers = {};
        return;
    }
    if (!(key in this._watchers))
        return;
    if (typeof id !== 'undefined') {
        if (id in this._watchers[key])
            delete this._watchers[key][id];
    } else delete this._watchers[key];
};

dataBinder.prototype.unwatchAll = function () {
    this._watchers = {};
    for (let x in this._attributes) {
        if (this._attributes[x] instanceof dataBinder)
            this._attributes[x].unwatchAll();
    }
};

dataBinder.prototype.keys = function () {
    return Object.keys(this._attributes);
};

dataBinder.prototype.populate = function (items) {
    this._attributes = {};
    for (let x in items) {
        if (key in this._attributes) continue;
        this.add(items[x]);
    }
};

dataBinder.prototype.extend = function (items) {
    for (let x in items) {
        if (x in this._attributes) {
            if (this._attributes[x] instanceof dataBinder)
                this[x].extend(items[x]);
            else if (this._attributes[x] instanceof dataBinderArray)
                this[x].populate(items[x]);
            else
                this[x] = items[x];
        } else
            this.add(x, items[x]);
    }
};

dataBinder.prototype.get = function (key) {
    if (key in this._attributes)
        return this._attributes[key];
};

dataBinder.prototype.empty = function () {
    for (x in this._attributes)
        if (this._attributes[x] instanceof dataBinder
            || this._attributes[x] instanceof dataBinderArray
            || this._attributes[x] instanceof dataBinderValue)
            this._attributes[x].empty();
        else this._attributes[x] = null;
};

dataBinder.prototype.enabled = function (value) {
    if (typeof value !== 'boolean') return this._enabled;
    return this._enabled = value;
};

dataBinder.prototype.compare = function (value) {
    if (typeof value !== 'object' || !value instanceof dataBinder || value.constructor.name !== 'Object') return false;
    for (x in value) if (!(x in this._attributes)
        || (this._attributes[x] instanceof dataBinderValue ? this._attributes[x].value : this._attributes[x]) !== value[x]) return false;
    for (x in this._attributes) if (!(x in value)) return false;
    return true;
};

dataBinderArray.prototype._init = function (data, name, parent) {
    if (!parent) throw "dataBinderArray requires a parent!";
    this._name = name;
    this._parent = parent;
    this._elements = [];
    this._template = null;
    this._watchers = [];
    this._enabled = true;
    this.resync();
    if (Array.isArray(data) && data.length > 0)
        for (let x in data) this.push(data[x]);
    Object.defineProperties(this, {
        "length": {
            get: function () {
                return this._elements.length;
            }
        },
        "parent": {
            get: function () {
                return this._parent;
            }
        }
    });
};

dataBinderArray.prototype.__name = function () {
    return this._attr_name();
};

dataBinderArray.prototype._trigger_name = dataBinder.prototype._trigger_name;

dataBinderArray.prototype._attr_name = function (attr_name) {
    if (!this._parent) return attr_name;
    return this._parent._attr_name(this._name) + (typeof attr_name === 'undefined' ? '' : '[' + attr_name + ']');
};

dataBinderArray.prototype.pop = function () {
    var index = this._elements.length - 1;
    var element = this._elements[index];
    this.unset(index);
    return element;
};

dataBinderArray.prototype._newitem = function (index, element) {
    var attr_name = this._attr_name(index), a = this;
    var newitem = this._template.prop('tagName') === 'TEMPLATE'
        ? jQuery(this._template.html()).attr('data-bind', attr_name)
        : this._template.clone(true).attr('data-bind', attr_name);
    newitem.find('[data-bind]').each(function (idx, item) {
        var key = item.attributes['data-bind'].value;
        item.attributes['data-bind'].value = attr_name + '.' + key;
    });
    if (this._watchers.length > 0) for (let x in this._watchers) this._watchers[x](newitem);
    return newitem;
};

dataBinderArray.prototype.__convert_type = function (key, value) {
    return this._parent.__convert_type(key, value, this);
};

dataBinderArray.prototype._update = function (attr_name, attr_element, do_update) {
    var remove = this.indexOf(attr_element) < 0, attr_value = this;
    jQuery('[data-bind="' + attr_name + '"]').each(function (index, item) {
        var o = $(item);
        if (o.is('[data-toggle]')) {
            o.find('[data-bind-value="' + attr_element.value + '"]')
                .toggleClass('active', !remove)
                .children('input[type=checkbox]').prop('checked', !remove);
        }
        if (do_update === true) o.trigger('update', [attr_name, attr_value]);
    });
};

dataBinderArray.prototype._trigger = function (name, obj) {
    this._parent._trigger(this._attr_name(name), obj);
};

dataBinderArray.prototype.push = function (element, no_update) {
    var key = this._elements.length;
    if (!Object.getOwnPropertyDescriptor(this, key)) {
        var trigger_name = this._trigger_name(this._attr_name(key));
        Object.defineProperty(this, key, {
            set: function (value) {
                this._elements[key] = this.__convert_type(key, value);
            },
            get: function () {
                return this._elements[key];
            }
        });
    }
    element = this.__convert_type(key, element);
    this._elements[key] = element;
    jQuery('[data-bind="' + this._attr_name() + '"]').trigger('push', [this._attr_name(), element, key]);
    if (no_update !== true && this._elements[key] instanceof dataBinder) {
        jQuery('[data-bind="' + this._attr_name() + '"]').append(this._newitem(key, this._elements[key]));
        this.resync();
    } else this._update(this._attr_name(), this._elements[key], true);
    return key;
};

dataBinderArray.prototype.indexOf = function (search) {
    if (typeof search === 'function') {
        for (x in this._elements) if (search(this._elements[x], x) === true) return parseInt(x);
    } else {
        if (search instanceof dataBinderValue) search = search.value;
        for (let i in this._elements) {
            if (this._elements[i] instanceof dataBinder && this._elements[i].compare(search) === true
                || (this._elements[i] instanceof dataBinderValue ? this._elements[i].value : this._elements[i]) === search)
                return parseInt(i);
        }
    }
    return -1;
};

dataBinderArray.prototype.remove = function (value, no_update) {
    return this.unset(this.indexOf(value instanceof dataBinderValue ? value.value : value), no_update);
};

dataBinderArray.prototype.unset = function (index, no_update) {
    if (index < 0 || typeof index !== 'number') return;
    var element = this._elements[index];
    if (typeof element === 'undefined') return;
    if (no_update !== true && element instanceof dataBinder) jQuery('[data-bind="' + this._attr_name() + '"]').children().eq(index).remove();
    this._cleanupItem(index);
    jQuery('[data-bind="' + this._attr_name() + '"]').trigger('pop', [this._attr_name(), element, index]);
    this._update(this._attr_name(), element, true);
    return element;
};

dataBinderArray.prototype.save = function (no_label) {
    var elems = this._elements.slice();
    for (let x in elems) {
        if (elems[x] instanceof dataBinder
            || elems[x] instanceof dataBinderArray
            || elems[x] instanceof dataBinderValue)
            elems[x] = elems[x].save(no_label);
    }
    return elems;
};

dataBinderArray.prototype.resync = function () {
    if (!this._template || this._template.length === 0) {
        var host = jQuery('[data-bind="' + this._attr_name() + '"]');
        if (host.attr('data-bind-template') === 'o') {
            this._template = host.data('template');
        } else {
            this._template = host.children('template');
            if (this._template.length > 0) this._template.detach();
        }
    }
    if (this._template && this._template.length > 0) {
        var parent = jQuery('[data-bind="' + this._attr_name() + '"]');
        for (let x in this._elements) {
            var attr_name = this._attr_name(x);
            var item = parent.children('[data-bind="' + attr_name + '"]');
            if (item.length === 0)
                parent.append(this._newitem(x, this._elements[x]));
            if (this._elements[x] instanceof dataBinder || this._elements[x] instanceof dataBinderArray)
                this._elements[x].resync();
        }
    }
};

dataBinderArray.prototype._cleanupItem = function (index) {
    if (!(index in this._elements)) return;
    var attr_name = this._attr_name();
    var reg = new RegExp("(" + attr_name + ")\\[(\\d+)\\]");
    for (var i = index + 1; i < this._elements.length; i++) {
        var new_i = i - 1;
        jQuery('[data-bind^="' + this._attr_name(i) + '"]').each(function (index, item) {
            if (!('data-toggle' in this.attributes))
                this.attributes['data-bind'].value = this.attributes['data-bind'].value.replace(reg, '$1[' + new_i + ']');
        });
        if (i in this._elements && (this._elements[i] instanceof dataBinder || this._elements[i] instanceof dataBinderArray))
            this._elements[i].resync(new_i);
    }
    var elem = this._elements.splice(index, 1);
    return elem;
};

dataBinderArray.prototype.populate = function (elements) {
    this._elements = [];
    if (!elements || typeof elements !== 'object') return;
    else if (!Array.isArray(elements))
        elements = Object.values(elements);
    for (let x in elements) {
        if (elements[x] instanceof dataBinder || elements[x] instanceof dataBinderArray || this._elements.indexOf(elements[x]) < 0)
            this.push(elements[x]);
    }
};

dataBinderArray.prototype.filter = function (cb) {
    var list = [];
    for (let x in this._elements) {
        var value = this._elements[x] instanceof dataBinderValue ? this._elements[x].value : this._elements[x];
        if (cb(value)) list.push(this._elements[x]);
    }
    return list;
};

dataBinderArray.prototype.reduce = function (cb) {
    for (let x in this._elements) if (cb(this._elements[x]) === false) this._elements.splice(x, 1);
    return this;
};

dataBinderArray.prototype.__nullify = function (value) {
    return this._parent.__nullify(value);
};

dataBinderArray.prototype.watch = function (cb) {
    if (typeof cb === 'function') this._watchers.push(cb);
};

dataBinderArray.prototype.empty = function () {
    for (x in this._elements)
        this._elements[x].empty();
    this._elements = [];
};

dataBinderArray.prototype.enabled = function (value) {
    if (typeof value !== 'boolean') return this._enabled;
    return this._enabled = value;
};

dataBinderArray.prototype.each = function (callback) {
    for (x in this._elements) callback(this._elements[x]);
};

dataBinderArray.prototype.find = function (callback) {
    var elements = [];
    for (x in this._elements)
        if (callback(this._elements[x]) === true) elements.push(this._elements[x]);
    return elements;
};
