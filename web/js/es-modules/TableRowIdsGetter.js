let globalInstance = null;

/**
 * Класс для получения id выбранных чекбоксами строк в таблице
 * 
 * На вход получает селектор таблицы
 */
export default class TableRowIdsGetter {
    /**
     * Создает экземпляр класса
     * @param {string} tableSelector - CSS селектор для таблицы
     */
    constructor(tableSelector) {
        if (!tableSelector) {
            throw new Error('tableSelector обязателен');
        }

        this.tableSelector = tableSelector;
        this.checkedValues = [];
    }

    /**
     * Инициализация класса
     */
    init() {
        this.$table = $(this.tableSelector);
        if (this.$table.length === 0) {
            console.error('Таблица не найдена');
            return;
        }

        this.updateCheckedValues();
        this.addEventListeners();
    }

    /**
     * Обновление массива значений отмеченных чекбоксов
     */
    updateCheckedValues() {
        const checkboxes = $(`${this.tableSelector} input[name="selection[]"]:checked`);
        this.checkedValues = checkboxes.map((_, checkbox) => $(checkbox).val()).get();
    }

    /**
     * Добавление обработчиков событий
     */
    addEventListeners() {
        this.$table.on('change', `input[name="selection[]"]`, () => {
            this.updateCheckedValues();
        });
    }

    /**
     * Получение массива значений отмеченных чекбоксов
     * @returns {Array} Массив значений отмеченных чекбоксов
     */
    getCheckedValues() {
        this.updateCheckedValues();
        return this.checkedValues;
    }

    saveInstance() {
        globalInstance = this
    }

    static getInstance(tableSelector) {
        if(!globalInstance) {
            const checkboxSelector = new TableRowIdsGetter(tableSelector);
            globalInstance = checkboxSelector
        }

        return globalInstance
    }

    /**
     * Инициализация класса при загрузке страницы
     * @param {string} tableSelector - CSS селектор для таблицы
     */
    static initOnPageLoad(tableSelector) {
        $(document).ready(function() {
            const checkboxSelector = TableRowIdsGetter.getInstance(tableSelector);
            checkboxSelector.init();
        });
    }
}
