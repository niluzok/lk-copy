import fb from '../form-helpers/form-builder.js'

const productControlSelector = '.product-control'
const boxSizeTypeSelector = '.box-size-type-control'
const boxSizeTypeCreateLinkSelector = '.create-box-size-type-link'
const itemContainerSelector = '.item'
const boxNumberInputSelector = '[id*=box_quantity]'

const urls = {
    getBoxSizeTypesForProduct: '/manager/product-kit/service/get-box-size-types-list'
}

const loadBoxSizeTypesForProduct = ($itemContainer, productId) => {
    const $boxSizeTypeSelect = $itemContainer.find(boxSizeTypeSelector)

    fb.resetSelect($boxSizeTypeSelect)

    return $.get(urls.getBoxSizeTypesForProduct, { productId })
        .then((boxSizeTypes) => {
            fb.populateSelect($boxSizeTypeSelect, boxSizeTypes, true, 'id', 'name', ['quantity'])
        })
}

const updateCreateSizeTypeLink = ($itemContainer, productId) => {
    const $link = $itemContainer.find(boxSizeTypeCreateLinkSelector)
    const baseUrl = $link.data('product-base-url')
    $link.attr('href', `${baseUrl}?id=${productId}#box-size-types`)
}

const enableSizeTypeControl = ($itemContainer) => {
    const $boxSizeTypeSelect = $itemContainer.find(boxSizeTypeSelector)
    const $createLink = $itemContainer.find(boxSizeTypeCreateLinkSelector)
    $boxSizeTypeSelect.prop('disabled', false)
    $createLink.removeClass('hidden')
}

const updateTotalProductCount = ($itemContainer) => {
    const $boxSizeTypeSelect = $itemContainer.find(boxSizeTypeSelector)
    const $numberOfBoxesInput = $itemContainer.find('[id*=box_quantity]')
    const numberOfBoxes = $numberOfBoxesInput.val()
    const productQuantity = $boxSizeTypeSelect.find('option:selected').data('quantity')
    
    if(!isNaN(numberOfBoxes) && numberOfBoxes && !isNaN(productQuantity) && productQuantity) {
        const totalProductQuantity = numberOfBoxes * productQuantity
        $itemContainer.find('.total-product-quantity').text(totalProductQuantity)
    } else {
        $itemContainer.find('.total-product-quantity').text('')
    }
}

const attachEventHandlers = () => {
    $('.container-items').on('change', productControlSelector, (e) => {
        const $itemContainer = $(e.target).closest(itemContainerSelector)
        const productId = $(e.target).val()
        loadBoxSizeTypesForProduct($itemContainer, productId)
            .then(updateCreateSizeTypeLink($itemContainer, productId))
            .then(enableSizeTypeControl($itemContainer))
    })

    $('.container-items').on('change', boxSizeTypeSelector, (e) => {
        const $itemContainer = $(e.target).closest(itemContainerSelector)
        updateTotalProductCount($itemContainer)
    })

    $('.container-items').on('keyup', boxNumberInputSelector, (e) => {
        const $itemContainer = $(e.target).closest(itemContainerSelector)
        updateTotalProductCount($itemContainer)
    })
}

const init = () => {
    attachEventHandlers()
}

export default {
    init
}