var Packlink = window.Packlink || {};

(function () {
    function OfflinePaymentsController(configuration) {

        let offlineModules = [];
        const ajaxService = Packlink.customAjaxService;

        const hideOfflinePayments = () => {
            const paymentMethods = document.querySelectorAll('#checkout-payment-step input[name="payment-option"]');
            paymentMethods.forEach(method => {
                if (offlineModules.includes(method.dataset.moduleName)) {
                    const container = method.closest('.payment-option');
                    if (container) {
                        container.style.display = 'none';
                        if (method.checked) {
                            method.checked = false;
                        }
                    }
                }
            });
        };

        this.init = function () {
            const checkoutContainer = document.querySelector('#checkout');
            if (!checkoutContainer) return;

            if (configuration.selectedService === null) {
                offlineModules = [];
            } else {
                let payload = {selectedService: configuration.selectedService};
                    ajaxService.post(
                        configuration.offlinePaymentMethods,
                        payload,
                        (response) => {
                            try {
                                offlineModules = response.data.map(module => module.name);
                                hideOfflinePayments();
                            } catch (e) {
                                console.error('Failed to parse offline payment modules:', e, response);
                            }
                        }
                    );
            }

            const observer = new MutationObserver(() => {
                const paymentStep = document.querySelector('#checkout-payment-step');
                if (paymentStep) {
                    hideOfflinePayments();
                }
            });

            observer.observe(checkoutContainer, { childList: true, subtree: true });
        };
    }

    Packlink.OfflinePaymentsController = OfflinePaymentsController;
})();