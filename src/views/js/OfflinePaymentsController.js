var Packlink = window.Packlink || {};

(function () {
    function OfflinePaymentsController(configuration) {

        let offlineModules = [];
        let lastFetchedService = undefined;
        let pendingRequest = false;
        let rafScheduled = false;
        const ajaxService = Packlink.customAjaxService;

        const hideOfflinePayments = () => {
            if (!offlineModules.length) return;
            const paymentMethods = document.querySelectorAll('#checkout-payment-step input[name="payment-option"]');
            paymentMethods.forEach(method => {
                if (offlineModules.includes(method.dataset.moduleName)) {
                    const container = method.closest('.payment-option') || method.closest('.payment__option');
                    if (container && container.style.display !== 'none') {
                        container.style.display = 'none';
                        if (method.checked) {
                            method.checked = false;
                        }
                    }
                }
            });
        };

        const fetchOfflinePayments = (selectedService) => {
            if (selectedService === null || selectedService === undefined) {
                return;
            }
            if (pendingRequest || selectedService === lastFetchedService) {
                hideOfflinePayments();
                return;
            }
            pendingRequest = true;
            ajaxService.post(
                configuration.offlinePaymentMethods,
                {selectedService: selectedService},
                (response) => {
                    pendingRequest = false;
                    try {
                        lastFetchedService = selectedService;
                        offlineModules = response.data.map(module => module.name);
                        hideOfflinePayments();
                    } catch (e) {
                        console.error('Failed to parse offline payment modules:', e, response);
                    }
                }
            );
        };

        this.init = function () {
            const checkoutContainer = document.querySelector('#checkout');
            if (!checkoutContainer) return;

            fetchOfflinePayments(configuration.selectedService);

            const observer = new MutationObserver(() => {
                if (rafScheduled) return;
                rafScheduled = true;
                requestAnimationFrame(() => {
                    rafScheduled = false;
                    if (!document.querySelector('#checkout-payment-step')) return;
                    fetchOfflinePayments(configuration.selectedService);
                });
            });

            observer.observe(checkoutContainer, { childList: true, subtree: true });
        };
    }

    Packlink.OfflinePaymentsController = OfflinePaymentsController;
})();
