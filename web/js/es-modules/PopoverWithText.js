/**
 * Класс для управления поповером с текстовым полем и отправкой данных на сервер
 * 
 * Поповер содержит текстовое поле и кнопку отправки. При отправке данных на сервер,
 * поповер закрывается и PJAX контейнер обновляется. Если произошла ошибка, 
 * отображается сообщение об ошибке и выводится в консоль.
 * 
 * Перед отправкой запроса может быть выполнена дополнительная логика через 
 * `beforeRequest` функцию. Эта функция принимает два аргумента:
 * - `addData(dataObject)` - добавляет данные в объект запроса.
 * - `abort(message)` - отменяет отправку запроса и показывает сообщение об ошибке.
 */
export default class PopoverWithText {
    /**
     * Создает экземпляр PopoverWithText
     * @param {Object} config - Параметры для класса
     * @param {string} config.triggerPopoverBtnSelector - CSS селектор для кнопки, открывающей поповер
     * @param {string} config.pjaxContainerSelector - CSS селектор для PJAX контейнера
     * @param {string} config.requestUrl - URL для отправки запроса
     * @param {string} [config.textParamName='text'] - Имя параметра для значения textarea в запросе
     * @param {Function} [config.beforeRequest] - Функция, выполняемая перед отправкой запроса. Принимает два аргумента: addData и abort.
     * @param {Object} [config.messages] - Сообщения для отображения пользователю
     */
    constructor(config) {
        const requiredFields = [
            'triggerPopoverBtnSelector',
            'pjaxContainerSelector',
            'requestUrl'
        ];

        for (const field of requiredFields) {
            if (!config[field]) {
                throw new Error(`Поле ${field} обязательно для PopoverWithText`);
            }
        }

        this.triggerPopoverBtnSelector = config.triggerPopoverBtnSelector;
        this.pjaxContainerSelector = config.pjaxContainerSelector;
        this.requestUrl = config.requestUrl;
        this.textParamName = config.textParamName || 'text';
        
        this.beforeRequest = config.beforeRequest || ((addData, abort) => addData({}));
        this.onSuccess = config.onSuccess || ((data) => {});

        this.$triggerPopoverBtn = $(this.triggerPopoverBtnSelector);
        this.$pjaxContainer = $(this.pjaxContainerSelector);

        const popoverSelector = this.$triggerPopoverBtn.data('target');
        this.$popover = $(popoverSelector);
        this.$textarea = this.$popover.find('textarea');
        this.$sendButton = this.$popover.find('.send-btn');
        this.$error = this.$popover.find('.popover-error');

        this.messages = {
            emptyField: 'Поле не должно быть пустым',
            errorOccurred: 'Произошла ошибка: {error}',
            ...config.messages
        };
    }

    /**
     * Инициализация класса
     */
    init() {
        this.addEventListeners();
    }

    /**
     * Добавление обработчиков событий
     */
    addEventListeners() {
        this.$sendButton.on('click', this.handlers.sendButtonClick);
        this.$triggerPopoverBtn.on('click', this.handlers.hideErrorOnPopoverClose);
        this.$popover.on('hidden.bs.popoverX', this.handlers.hideErrorOnPopoverClose);
    }

    /**
     * Обработчики событий
     */
    handlers = {
        /**
         * Обработчик клика по кнопке отправки
         */
        sendButtonClick: () => {
            const textareaValue = this.$textarea.val().trim();
            if (!textareaValue) {
                const errorMessage = this.composeMessage(this.messages.emptyField);
                this.handlers.showErrorAndLogToConsole(errorMessage);
                return;
            }

            const requestData = { [this.textParamName]: textareaValue };
            let abortRequest = false;

            const addData = (data) => {
                Object.assign(requestData, data);
            };

            const abort = (message) => {
                const errorMessage = this.composeMessage(message);
                this.handlers.showErrorAndLogToConsole(errorMessage);
                abortRequest = true;
            };

            this.beforeRequest(addData, abort);

            if (abortRequest) {
                return;
            }

            this.$error.hide(); // Скрываем сообщение об ошибке при отправке

            $.post(this.requestUrl, requestData)
                .done((data) => {
                    if (data.message) {
                        const errorMessage = this.composeMessage(data.message);
                        this.handlers.showErrorAndLogToConsole(errorMessage);
                        return;
                    }

                    this.onSuccess(data);
                })
                .fail((xhr, status, error) => {
                    const errorMessage = this.composeMessage(this.messages.errorOccurred, { error });
                    this.handlers.showErrorAndLogToConsole(errorMessage);
                })
                .always(() => {
                    this.handlers.closePopover();
                })
        },

        /**
         * Закрывает поповер
         */
        closePopover: () => {
            this.$popover.popoverX('hide');
        },

        /**
         * Обработчик ошибки запроса, показывает сообщение об ошибке и логирует ошибку в консоль
         */
        showErrorAndLogToConsole: (error) => {
            const errorMessage = this.composeMessage(this.messages.errorOccurred, { error });
            this.$error.text(errorMessage).show();
            console.error(errorMessage);
        },

        /**
         * Обработчик для скрытия сообщения об ошибке при закрытии поповера
         */
        hideErrorOnPopoverClose: () => {
            this.$error.hide();
        },
    }

    /**
     * Заменяет токены в сообщении на значения из объекта substitutions
     * @param {string} message - Сообщение с токенами
     * @param {Object} [substitutions={}] - Объект с заменами для токенов
     * @returns {string} Сообщение с замененными токенами
     * @throws {Error} Если в сообщении остались незамененные токены
     */
    composeMessage(message, substitutions = {}) {
        let substitutedMessage = message;
        for (const [token, value] of Object.entries(substitutions)) {
            substitutedMessage = substitutedMessage.replace(`{${token}}`, value);
        }

        if (substitutedMessage.includes('{')) {
            throw new Error(`Не удалось заменить все токены в сообщении: ${substitutedMessage}`);
        }

        return substitutedMessage;
    }

    /**
     * Инициализация класса при загрузке страницы
     * @param {Object} config - Параметры для класса
     */
    static initOnPageLoad(config) {
        $(document).ready(function() {
            const popoverWithTextInitialiser = new PopoverWithText(config);
            popoverWithTextInitialiser.init();
        });
    }
}
