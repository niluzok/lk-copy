import fb from '../form-helpers/form-builder.js'

const urls = {
    getBoxSizeTypesForProduct: '/manager/product-kit/service/get-box-size-types-list'
}

const BoxSizeTypesSelect = function($select) {
    this.$select = $($select)
    addFbData(this.$select, 'BoxSizeTypesSelect')
}

BoxSizeTypesSelect.prototype = {
    loadForProduct: function(productId) {
        fb.resetSelect(this.$select)
    
        return $.get(urls.getBoxSizeTypesForProduct, { productId })
            .then((boxSizeTypesData) => {
                fb.populateSelect(this.$select, boxSizeTypesData, true, 'id', 'name', ['quantity'])
            })
    },
    getCustomErrorElement: function() {
        let $customError = this.$select.closest('.form-group').find('.extra-error')
        
        if($customError.length == 0) {
            $customError = $('<div>').addClass('extra-error').addClass('text-danger')
            $customError.hide()
            this.$select.closest('.form-group').append($customError)
        }

        return $customError;
    },
    getYiiFormControlHelpBlock: function() {
        const $formControlHelpBlock = $boxSizeTypesSelect.closest('.form-group').find('.help-block')
        return $formControlHelpBlock
    },
    enable: function() {
        fb.enableControl(this.$select, true)
    },
    disable: function() {
        fb.enableControl(this.$select, false)
    },
    setValue: function(value) {
        this.$select.val(value)
    },
    showCustomError: function(errorMessage) {
        const $customError = this.getCustomErrorElement()

        if(false === errorMessage) {
            $customError.hide()
            return
        }
        
        $customError.text(errorMessage).show()
        this.getYiiFormControlHelpBlock().hide()
    }
}

const addFbData = ($el, key) => {
    if(!$el.data('fbdata')) {
        $el.data('fbdata', {})
    }

    const fbdata = $el.data('fbdata')
    fbdata[key] = this
}

export default BoxSizeTypesSelect