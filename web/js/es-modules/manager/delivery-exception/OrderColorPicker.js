/**
 * Класс OrderColorPicker для управления цветами заказов
 */
class OrderColorPicker {
    // Синглтоны, экземпляры класса, ключами являются селекторы
    static singletons = {};

    /**
     * Сообщения об ошибках
     */
    static defaultMessages = {
        reloadError: 'Произошла ошибка при перезагрузке представления сетки',
        orderIdsNotFound: 'Идентификаторы заказов не найдены',
        colorUpdateSuccess: 'Цвет успешно обновлен',
        errorOccurred: 'Ошибка:'
    }

    /**
     * Обработчики событий
     */
    handlers = {
        /**
         * Обработчик события изменения цвета
         * @param {string} value - Новое значение цвета
         */
        onColorChange: (value) => this.handleColorChange(value),
    }

    /**
     * Конструктор класса OrderColorPicker
     * @param {Object} config - Конфигурационный объект
     * @param {string} config.batchColorPickerSelector - Селектор элемента выбора цвета
     * @param {string} config.url - URL для POST запроса
     * @param {Function} [config.getOrderIdsCallback] - Функция для получения идентификаторов заказов
     * @param {Function} [config.onSuccess] - Функция, вызываемая при успешном завершении
     * @param {Function} [config.onError] - Функция, вызываемая при ошибке
     */
    constructor(config) {
        const requiredConfigOptions = [
            'batchColorPickerSelector',
            'url'
        ]

        requiredConfigOptions.forEach(option => {
            if (!config[option]) {
                throw new Error(`Отсутствует обязательное поле конфигурации: ${option}`)
            }
        })

        this.config = {
            getOrderIdsCallback: () => [],
            onSuccess: () => {},
            onError: (error) => {},
            messages: { ...OrderColorPicker.defaultMessages },
            ...config
        }

        this.init()
    }

    /**
     * Инициализация свойств класса и добавление слушателей событий
     */
    init() {
        this.$batchColorPicker = $(this.config.batchColorPickerSelector)
        this.addEventListeners()
    }

    /**
     * Добавление слушателей событий
     */
    addEventListeners() {
        this.$batchColorPicker.on('change', (event) => {
            const value = event.target.value
            this.handlers.onColorChange(value)
        })
    }

    /**
     * Обработчик изменения цвета
     * @param {string} value - Новое значение цвета
     */
    handleColorChange(value) {
        const orderIds = this.config.getOrderIdsCallback()

        if (!orderIds.length) {
            const error = this.config.messages.orderIdsNotFound
            this.handleError(error)
            return
        }

        const url = this.composeUrlWithParams(this.config.url, { orderIds })

        $.post(url, {
            orderIds: orderIds,
            displayColor: value,
        })
        .done((data) => {
            this.config.onSuccess(data)
            this.$batchColorPicker.val(null)
        })
        .fail((jqXHR, textStatus, errorThrown) => {
            this.handleError(this.config.messages.reloadError)
        })

    }
    
    handleError(message) {
        this.config.onError(error)
        console.error(message)
        toastr.error(message)
    }

    /**
     * Вспомогательный метод для создания URL с параметрами поиска
     * @param {string} baseUrl - Базовый URL
     * @param {Object} params - Объект с параметрами поиска
     * @returns {string} - URL с параметрами поиска
     */
    composeUrlWithParams(baseUrl, params) {
        const url = new URL(baseUrl, window.location.origin)
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]))
        return url.toString()
    }

    /**
     * Метод для составления сообщений с подстановками
     * @param {string} message - Шаблон сообщения
     * @param {Object} [substitutions={}] - Объект с подстановками
     * @returns {string} - Сообщение с подстановками
     */
    composeMessage(message, substitutions = {}) {
        return message.replace(/\{(\w+)\}/g, (match, key) => substitutions[key] || match)
    }

    /**
     * Метод для получения или создания единственного экземпляра класса для каждого селектора (синглтон)
     * @param {string} selector - Селектор для инициализации OrderColorPicker
     * @param {Object} initConfig - Конфигурационный объект для инициализации OrderColorPicker
     * @returns {OrderColorPicker} - Экземпляр OrderColorPicker
     */
    static getInstance(selector, initConfig) {
        if (!OrderColorPicker.singletons[selector]) {
            OrderColorPicker.singletons[selector] = new OrderColorPicker({ batchColorPickerSelector: selector, ...initConfig });
        }
        return OrderColorPicker.singletons[selector];
    }

    /**
     * Метод для инициализации экземпляров OrderColorPicker на странице для каждого элемента таблицы
     * @param {string} tableSelector - Селектор таблицы для поиска элементов выбора цвета
     * @param {Object} initConfig - Конфигурационный объект для инициализации OrderColorPicker
     */
    static initOnPageLoad(tableSelector, initConfig) {
        $(document).ready(() => {
            const $table = $(tableSelector);
            OrderColorPicker.getInstance(tableSelector, initConfig);
        });
    }
}

export default OrderColorPicker;
