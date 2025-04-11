const $findRowForOrderId = (orderId) => {
    return $(`input[value="${orderId}"]`).closest('tr')
}

export default {
    $findRowForOrderId,
}