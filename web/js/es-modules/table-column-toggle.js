/**
 * Класс для управления отображением колонок таблицы
 * По умолчанию с использованием bootstrap dropdown
 * 
 * Берет заголовки столбцов таблицы и создает список с чекбоксами, управляющими
 * отображением или скрытием соответствующего столбца
 * 
 * Для каждого столбца добавляет params.templateString в контейнер params.checkboxContainerSelector 
 */
export default class ColumnToggle {
    /**
     * Создает экземпляр ColumnToggle
     * @param {Object} params - Параметры для класса
     * @param {string} params.tableSelector - CSS селектор для таблицы
     * @param {string} params.checkboxContainerSelector - CSS селектор для контейнера чекбоксов
     * @param {string} [params.storageKey='tableColumnsConfig'] - Ключ для хранения конфигурации в localStorage
     * @param {string} [params.templateString=null] - Шаблон строки для элементов выпадающего списка
     * @param {number[]} [params.defaultHideColumnsIds=[]] - Массив индексов колонок, которые должны быть скрыты по умолчанию
     */
    constructor({ tableSelector, checkboxContainerSelector, storageKey = 'tableColumnsConfig', templateString = null, defaultHideColumnsIds = [] }) {
        if (!tableSelector || !checkboxContainerSelector) {
            throw new Error('tableSelector и checkboxContainerSelector обязательны')
        }

        this.tableSelector = tableSelector
        this.checkboxContainerSelector = checkboxContainerSelector
        this.storageKey = storageKey
        this.templateString = templateString || ColumnToggle.DEFAULT_COLUMN_CHECKBOX_TEMPLATE
        this.defaultHideColumnsIds = defaultHideColumnsIds
        this.table = document.querySelector(tableSelector)
        this.checkboxContainer = document.querySelector(checkboxContainerSelector)
    }

    /**
     * Шаблон по умолчанию для одного чекбокса для одного столбца
     * Токены:
     *   {checked} - свойство checked чекбокса: checked | ''
     *   {index} - Порядковый индекс колонки
     *   {headerText} - Заголовок столбца
     */
    static DEFAULT_COLUMN_CHECKBOX_TEMPLATE = `
        <a>
            <label>
                <input type="checkbox" {checked} data-column-index="{index}"> {headerText}
            </label>
        </a>
    `

    /**
     * Инициализация класса
     */
    init() {
        if (!this.table || !this.checkboxContainer) {
            console.error('Table or Checkbox Container not found')
            return
        }

        this.config.load()
        this.createDropdownItems()
        this.addEventListeners()
    }

    /**
     * Методы для загрузки и сохранения конфигурации колонок
     */
    config = {
        columns: {},

        /**
         * Загрузка конфигурации колонок из localStorage
         */
        load: () => {
            const savedConfig = localStorage.getItem(this.storageKey)
            this.config.columns = savedConfig ? JSON.parse(savedConfig) : {}

            // Если нет сохраненной конфигурации, используем defaultHideColumnsIds
            if (!savedConfig) {
                this.defaultHideColumnsIds.forEach(index => {
                    this.config.columns[`column-${index}`] = false
                })
            }
        },

        /**
         * Сохранение конфигурации колонок в localStorage
         */
        save: () => {
            localStorage.setItem(this.storageKey, JSON.stringify(this.config.columns))
        }
    }

    /**
     * Создание элементов выпадающего списка
     */
    createDropdownItems() {
        const headers = this.table.querySelectorAll('thead tr:first-child th')
        headers.forEach((header, index) => {
            if (!header.textContent.trim()) {
                return
            }

            const columnKey = `column-${index}`
            const isChecked = this.config.columns[columnKey] !== false

            const listItem = this.createListItem(isChecked, index, header.textContent.trim())

            this.checkboxContainer.appendChild(listItem)

            if (!isChecked) {
                this.toggleColumn(index, false)
            }
        })
    }

    /**
     * Создание элемента списка
     * @param {boolean} isChecked - Статус чекбокса (отмечен или нет)
     * @param {number} index - Индекс колонки
     * @param {string} headerText - Текст заголовка колонки
     * @returns {HTMLElement} Элемент списка
     */
    createListItem(isChecked, index, headerText) {
        const listItem = document.createElement('li')
        listItem.innerHTML = this.templateString
            .replace('{checked}', isChecked ? 'checked' : '')
            .replace('{index}', index)
            .replace('{headerText}', this.escapeHtml(headerText))

        return listItem
    }

    /**
     * Добавление обработчиков событий
     */
    addEventListeners() {
        this.checkboxContainer.addEventListener('change', (event) => this.handlers.checkboxChangeToggleColumn(event))

        this.checkboxContainer.querySelectorAll('li').forEach((listItem) => {
            listItem.addEventListener('click', (event) => this.handlers.listItemClickToggleCheckbox(listItem, event))
        })

        this.checkboxContainer.addEventListener('click', (event) => this.handlers.preventDropdownClose(event))
    }

    /**
     * Обработчики событий
     */
    handlers = {
        /**
         * Обработчик клика по элементу списка
         * @param {HTMLElement} listItem - Элемент списка
         * @param {Event} event - Событие
         */
        listItemClickToggleCheckbox: (listItem, event) => {
            if(event.target.tagName === 'INPUT' || event.target.tagName === 'LABEL') {
                return
            }
            
            const checkbox = event.currentTarget.querySelector('input[type="checkbox"]')
            checkbox.checked = !checkbox.checked
            checkbox.dispatchEvent(new Event('change', { bubbles: true }))
        },

        /**
         * Обработчик изменения состояния чекбокса
         * @param {Event} event - Событие
         */
        checkboxChangeToggleColumn: (event) => {
            if(event.target.tagName === 'INPUT' && event.target.type === 'checkbox') {
                const columnIndex = event.target.getAttribute('data-column-index')
                const isChecked = event.target.checked
                this.toggleColumn(columnIndex, isChecked)
                this.config.columns[`column-${columnIndex}`] = isChecked
                this.config.save()
            }
        },

        /**
         * Обработчик для предотвращения закрытия выпадающего списка
         * @param {Event} event - Событие
         */
        preventDropdownClose: (event) => {
            if(event.target.tagName !== 'INPUT') {
                event.stopPropagation()
            }
        }
    }

    /**
     * Переключение видимости колонки
     * @param {number} index - Индекс колонки
     * @param {boolean} show - Флаг видимости (true - показать, false - скрыть)
     */
    toggleColumn(index, show) {
        const headerCells = this.table.querySelectorAll(`thead tr th:nth-child(${parseInt(index) + 1}), thead tr td:nth-child(${parseInt(index) + 1})`)
        const bodyCells = this.table.querySelectorAll(`tbody tr td:nth-child(${parseInt(index) + 1})`)

        headerCells.forEach(cell => {
            cell.style.display = show ? '' : 'none'
        })

        bodyCells.forEach(cell => {
            cell.style.display = show ? '' : 'none'
        })
    }

    /**
     * Экранирование HTML
     * @param {string} text - Текст для экранирования
     * @returns {string} Экранированный текст
     */
    escapeHtml(text) {
        return text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;')
    }

    /**
     * Инициализация класса при загрузке страницы
     * @param {Object} params - Параметры для класса
     */
    static initOnPageLoad(params) {
        document.addEventListener('DOMContentLoaded', function() {
            const columnToggle = new ColumnToggle(params)
            columnToggle.init()
        })
    }
}
