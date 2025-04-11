import fb from '../form-helpers/form-builder.js'
import BoxSizeTypesSelect from './BoxSizeTypesSelect.js'
import remains from '../remains.js'

const $productSelect = $('#productkitbox-product_id')
const $productReleaseSelect = $('#productkitbox-consignment_id')
const $boxSizeTypesSelect = $('#productkitbox-box_size_type_id')

const boxSizeTypesSelect = new BoxSizeTypesSelect($boxSizeTypesSelect)

const urls = {
    getBoxSizeTypesForProduct: '/manager/product-kit/service/get-box-size-types-list'
}

let productReleasesDataCache = [];

const init = () => {
    $(function() {
        attachEventHandlers()

        if(!$productSelect.val()) {
            fb.disableControl($productReleaseSelect)
            boxSizeTypesSelect.disable()
        } else {
            // Ждем, пока токен не загрузится
            (async() => {
                while(!app.getToken())
                    await new Promise(resolve => setTimeout(resolve, 500));
                loadReleaseDataForProduct($productSelect.val())
            })();
        }
    })
}

const attachEventHandlers = () => {
    $productSelect.on('change', function() {
        const productId = $(this).val()
        loadReleaseDataForProduct(productId)
        loadBoxSizeTypesForProduct(productId)
    })

    $productReleaseSelect.on('change', function() {
        boxSizeTypesSelect.enable()
    })

    $boxSizeTypesSelect.on('change', validateBoxSizeAndAvailiableQuantity)
}

const loadBoxSizeTypesForProduct = (productId) => {
    boxSizeTypesSelect.loadForProduct(productId)
}

const validateBoxSizeAndAvailiableQuantity = function() {
    const productReleaseId = $productReleaseSelect.val()
    const productAvailiableQuantity = getProductAvailiableQuantityForRelease(productReleaseId)
    const $selectedOption = $(this).find('option:selected')
    const selectedBoxSizeTypeProductQuantity = $selectedOption.data('quantity')

    if(selectedBoxSizeTypeProductQuantity > productAvailiableQuantity) {
        boxSizeTypesSelect.setValue(null)
        boxSizeTypesSelect.showCustomError(`Box '${$selectedOption.text()}' is too big`)
    } else {
        boxSizeTypesSelect.showCustomError(false)
    }
}

const loadReleaseDataForProduct = (productId) => {
    fb.resetSelect($productReleaseSelect)
    
    return remains.queryProductReleasesRemains(productkitWarehouseId, productId)
        .then(function(productReleasesData) {
            productReleasesDataCache = productReleasesData
            
            const selectData = productReleasesData.reduce((selectItems, productRelease) => {
                selectItems[productRelease.id] = `Lot: ${productRelease.lot}, exp: ${productRelease.expirationDate}, qnt: ${getProductAvailiableQuantityForRelease(productRelease.id)}`
                return selectItems;
            }, {})

            fb.populateSelectKv($productReleaseSelect, selectData)
            fb.enableControl($productReleaseSelect, true)
            
            return productReleasesData
        })
}

const getProductReleaseFromCache = (productReleaseId) => {
    const productRelease = productReleasesDataCache.filter((productRelease) => { return productRelease.id == productReleaseId })[0]
    return productRelease
}

const getProductAvailiableQuantityForRelease = (productReleaseId) =>  {
    const productId = $productSelect.val()
    const productRelease = getProductReleaseFromCache(productReleaseId)
    const alreadyBoxedQuantity = boxedProductsQuantities[productId] ?? 0;
    const availiableQuantity = productRelease.quantity + alreadyBoxedQuantity
    
    return availiableQuantity
}

export default {
    init
}
