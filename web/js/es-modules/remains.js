/**
 * Модуль для получения остатков товаров и работы с ними
 */

const config = {
    namespace: 'autocompleteProductReleaseNamespace',
};

const event = {
    change: 'change.' + config.namespace,
};

const params = {
    urls: {
        showWarehouseProductReleaseInventory: '/api/internal/warehouse-product-release-inventory/view',
        productReleasesRemains: '/api/internal/warehouse-product-release-inventory/get-product-release-left',
    },
    errors: {
        errorMessage: 'Is there something wrong',
    },
};

let getProductReleaseSelect = () => { console.error('You should set getProductReleaseSelect method') }
let getWarehouseId = () => { console.error('You should set getWarehouseId method') }

let init = function(inParams) {
    try {
        $.extend(params, inParams || {});
        unbindEvents();
        bindEvents();
    } catch (error) {
        console.log(error);
    }
};

const handlers = {
    onBefore: function() {},
    onChange: function() {},
    onAfter: function() {},
}

let bindEvents = function() {
    $(document).on(event.change, getProductReleaseSelect(), handlers.onChange);
};

let unbindEvents = function() {
    $(document).off('.' + config.namespace);
};

handlers.onChange = function requestRemains(e) {
    const $select = $(e.target)

    $.ajax({
        url: params.urls.showWarehouseProductReleaseInventory,
        type: 'GET',
        data: {
            productReleaseId: $select.val(),
            warehouseId: getWarehouseId(),
        },
        headers: {
            'Authorization': 'Bearer ' + app.getToken(),
        },
        beforeSend: function() {
            handlers.onBefore($select)
        },
        error: function() {
            app.showMessage(params.errors.errorMessage);
        },
        success: function(data) {
            handlers.onSuccess($select, data)
        },
        complete: function() {
            handlers.onAfter($select)
        },
    });
};

function setOnBefore(func) {
    handlers.onBefore = func
    return this
}

function setOnAfter(func) {
    handlers.onAfter = func
    return this
}

function setOnSuccess(func) {
    handlers.onSuccess = func
    return this
}

function setProductReleaseSelectGetter(func) {
    getProductReleaseSelect = func
    return this
}

function setWarehouseIdGetter(func) {
    getWarehouseId = func
    return this
}

async function queryProductReleasesRemains(warehouseId, productId) {
    return $.ajax({
        url: params.urls.productReleasesRemains,
        type: 'GET',
        data: {
            warehouseId,
            productId,
        },
        headers: {
            'Authorization': 'Bearer ' + app.getToken(),
        },
    });
}

export default {
    init,
    setOnBefore,
    setOnAfter,
    setOnSuccess,
    setProductReleaseSelectGetter,
    setWarehouseIdGetter,
    queryProductReleasesRemains,
}
