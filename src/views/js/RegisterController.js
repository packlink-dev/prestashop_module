if (!window.Packlink) {
    window.Packlink = {};
}

(function () {
    /**
     * Handles register page logic.
     *
     * @param {{getRegistrationData: string, submit: string}} configuration
     * @constructor
     */
    function RegisterController(configuration) {

        const templateService = Packlink.templateService,
            ajaxService = Packlink.ajaxService,
            state = Packlink.state,
            utilityService = Packlink.utilityService,
            translationService = Packlink.translationService,
            validationService = Packlink.validationService,
            responseService = Packlink.responseService,
            templateId = 'pl-register-page';

        let form,
            country;

        /**
         * The main entry point for controller.
         */
        this.display = (additionalConfig) => {
            utilityService.showSpinner();
            templateService.setCurrentTemplate(templateId);
            country = additionalConfig.hasOwnProperty('country') ? additionalConfig.country : 'ES';

            let getDataUrl = configuration.getRegistrationData + '&country=' + country;

            ajaxService.get(getDataUrl, populateInitialValues);

            const registerPage = templateService.getMainPage();

            form = templateService.getComponent('pl-register-form', registerPage);
            form.addEventListener('submit', register);

            templateService.getComponent('pl-go-to-login', registerPage).addEventListener('click', goToLogin);

            templateService.getComponent('pl-register-platform-country', registerPage).value =
                additionalConfig.hasOwnProperty('platform_country') ? additionalConfig.platform_country : 'ES';

            initInputField('pl-register-email');
            initInputField('pl-register-password');
            initInputField('pl-register-phone');
            initInputField('pl-register-shipment-volume');
            initInputField('pl-register-terms-and-conditions');
        };

        /**
         * Populates initial values from the backend.
         *
         * @param {{
         *  email: string,
         *  phone: string,
         *  source: string,
         *  termsAndConditionsUrl: string,
         *  privacyPolicyUrl: string
         *  }} response
         */
        const populateInitialValues = (response) => {
            const emailInput = templateService.getComponent('pl-register-email'),
                phoneInput = templateService.getComponent('pl-register-phone'),
                sourceInput = templateService.getComponent('pl-register-source');

            emailInput.value = response.email;
            phoneInput.value = response.phone;
            sourceInput.value = response.source;

            let termsAndConditionsLabel = templateService.getComponent('pl-register-terms-and-conditions-label'),
                termsTranslation = translationService.translate(
                    'register.termsAndConditions',
                    [response.termsAndConditionsUrl, response.privacyPolicyUrl]
                );

            termsAndConditionsLabel.querySelector('label').innerHTML += termsTranslation;
            utilityService.hideSpinner();
        };

        /**
         * Initializes the input field. Attaches proper event listeners.
         *
         * @param {string} componentSelector
         */
        const initInputField = (componentSelector) => {
            let input = templateService.getComponent(componentSelector);

            input.addEventListener('blur', () => {
                validationService.validateInputField(input);
                enableSubmit();
            }, true);

            input.addEventListener('input', () => {
                validationService.removeError(input);
            });

            input.addEventListener('change', () => {
                enableSubmit();
                if (componentSelector === 'pl-register-terms-and-conditions' && !input.checked) {
                    templateService.getComponent('pl-register-button').disabled = true;
                }
            });
        };

        /**
         * Redirects to login.
         *
         * @param {Event} event
         *
         * @returns {boolean}
         */
        const goToLogin = (event) => {
            event.preventDefault();

            Packlink.state.goToState('login');

            return false;
        };

        /**
         * Enables or disables the submit button.
         */
        const enableSubmit = () => {
            let inputs = form.querySelectorAll('input,select'),
                registerButton = templateService.getComponent('pl-register-button');

            registerButton.disabled = false;
            inputs.forEach((input) => {
                if (input.hasAttribute('data-pl-contains-errors')) {
                    registerButton.disabled = true;
                }
            });
        };

        const validateForm = () => {
            validationService.validateForm(form);

            enableSubmit();
        };

        /**
         * Handles form submit.
         *
         * @param {Event} event
         * @returns {boolean}
         */
        const register = (event) => {
            event.preventDefault();
            validateForm();

            if (form.querySelectorAll('[data-pl-contains-errors]').length === 0) {
                utilityService.showSpinner();
                ajaxService.post(
                    configuration.submit,
                    {
                        'email': event.target['email'].value,
                        'password': event.target['password'].value,
                        'estimated_delivery_volume': event.target['estimated_delivery_volume'].value,
                        'phone': event.target['phone'].value,
                        'platform_country': event.target['platform_country'].value,
                        'source': event.target['source'].value,
                        'terms_and_conditions': !!event.target['terms_and_conditions'].checked,
                        'marketing_emails': !!event.target['marketing_emails'].checked,
                    },
                    successfulRegister,
                    responseService.errorHandler
                );
            }

            return false;
        };

        /**
         * Handles a successful registration request.
         *
         * @param {{success: boolean, message: string}} response
         */
        const successfulRegister = (response) => {
            if (response.success) {
                state.goToState('onboarding-state');
            } else {
                responseService.errorHandler(response);
            }
        };
    }

    Packlink.RegisterController = RegisterController;
})();
