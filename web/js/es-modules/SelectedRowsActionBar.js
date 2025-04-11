/**
 * Класс для управления отображением кнопочной панели в зависимости от состояния чекбоксов
 */
class SelectedRowsActionBar {
    /**
     * @type {SelectedRowsActionBar|null}
     * Переменная для хранения единственного экземпляра класса
     */
    static singleton = null

    /**
     * @type {Object}
     * Сообщения по умолчанию
     */
    static defaultMessages = {
    }

    /**
     * @type {Object}
     * Конфигурация по умолчанию
     */
    static defaultConfig = {
        checkboxesContainerSelector: '',
        actionBarSelector: '',
        checkboxSelector: 'input[type="checkbox"][name="selection[]"],:is(.select-on-check-all)',
        messages: {}
    }

    /**
     * @type {Object}
     * DOM элементы
     */
    elements = {
        checkboxesContainer: null,
        buttonBar: null
    }

    /**
     * @type {Object}
     * Обработчики событий
     */
    handlers = {
        onCheckboxChange: (event) => {
            if (event.target.matches(this.config.checkboxSelector)) {
                this.handleCheckboxChange()
            }
        }
    }

    /**
     * @type {Object}
     * Сообщения
     */
    messages = {}

    /**
     * @type {Object}
     * Конфигурация
     */
    config = {}

    /**
     * @param {Object} config - Конфигурационный объект
     * @throws {Error} Если отсутствуют обязательные поля конфигурации
     */
    constructor(config) {
        const requiredFields = ['checkboxesContainerSelector', 'actionBarSelector']
        for (const field of requiredFields) {
            if (!config[field]) {
                throw new Error(`Missing required field: ${field}`)
            }
        }

        this.config = { ...SelectedRowsActionBar.defaultConfig, ...config }
        this.elements.checkboxesContainer = document.querySelector(this.config.checkboxesContainerSelector)
        this.elements.actionBar = document.querySelector(this.config.actionBarSelector)
        this.messages = { ...SelectedRowsActionBar.defaultMessages, ...this.config.messages }

        this.init()
    }

    /**
     * Инициализация класса, добавление обработчиков событий
     */
    init() {
        this.addEventListeners()
    }

    /**
     * Добавление обработчиков событий
     * Добавляет обработчик изменения состояния чекбоксов
     */
    addEventListeners() {
        this.elements.checkboxesContainer.addEventListener('change', (event) => {
            this.handlers.onCheckboxChange(event)
        })
    }

    /**
     * Обработчик изменения состояния чекбоксов
     * Показывает или скрывает кнопочную панель в зависимости от количества выбранных чекбоксов
     */
    handleCheckboxChange() {
        const checkboxes = this.elements.checkboxesContainer.querySelectorAll(this.config.checkboxSelector)
        const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked)
        
        if (anyChecked) {
            this.elements.actionBar.classList.add('show');
        } else {
            this.elements.actionBar.classList.remove('show');
        }
    }

    /**
     * Составляет сообщение с заменой токенов
     * @param {string} message - Сообщение с токенами
     * @param {Object} [substitutions={}] - Объект с заменяемыми значениями
     * @returns {string} Составленное сообщение
     */
    composeMessage(message, substitutions = {}) {
        return Object.keys(substitutions).reduce((msg, key) => msg.replace(new RegExp(`{${key}}`, 'g'), substitutions[key]), message)
    }

    /**
     * Инициализация класса при загрузке страницы
     * @param {Object} config - Конфигурационный объект
     */
    static initOnPageLoad(config) {
        document.addEventListener('DOMContentLoaded', () => {
            SelectedRowsActionBar.getInstance(config)
        })
    }

    /**
     * Получает единственный экземпляр класса
     * @param {Object} config - Конфигурационный объект
     * @returns {SelectedRowsActionBar} Единственный экземпляр класса
     */
    static getInstance(config) {
        if (!SelectedRowsActionBar.singleton) {
            SelectedRowsActionBar.singleton = new SelectedRowsActionBar(config)
        }
        return SelectedRowsActionBar.singleton
    }
}

export default SelectedRowsActionBar
