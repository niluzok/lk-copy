const resetSelect = ($select) => {
    $select.children().remove()
}

const createNewOption = (value, text) => {
    return $('<option>').val(value).text(text)
}

const populateSelectKv = ($select, keyVal, addEmpty) => {
    addEmpty = addEmpty ?? true
    
    if(addEmpty) {
        createNewOption(null, null).appendTo($select)
    }
    
    for(let key in keyVal) {
        let value = keyVal[key]
        createNewOption(key, value).appendTo($select)
    }
}

const populateSelect = ($select, objArr, addEmpty, keyField, valueField, otherFieldsAsDataAttr) => {
    addEmpty = addEmpty ?? true
    
    if(addEmpty) {
        createNewOption(null, null).appendTo($select)
    }
    
    for(let obj of objArr) {
        if(!(keyField in obj)) {
            throw new Error(`Object do not have field ${keyField} to use it as key`)
        }
        
        if(!(valueField in obj)) {
            throw new Error(`Object do not have field ${valueField} to use it as key`)
        }

        let key = obj[keyField]
        let value = obj[valueField]

        let $option = createNewOption(key, value)
        
        if(otherFieldsAsDataAttr) {
            for(let attr of otherFieldsAsDataAttr) {
                if(attr in obj) {
                    $option.attr('data-' + attr, obj[attr])
                }
            }
            for(let k in obj) {
                if(otherFieldsAsDataAttr.includes(k)) {
                }
            }
        }
        
        $option.appendTo($select)

    }
}

const enableControl = ($control, flag) => {
    if(flag === undefined) {
        flag = true
    }

    $control.prop('disabled', !flag)
}

const disableControl = ($control) => {
    $control.prop('disabled', true)
}

export default {
    resetSelect,
    createNewOption,
    populateSelectKv,
    populateSelect,
    enableControl,
    disableControl,
}