(function () {

    function CustomAjaxService() {
        const baseService = Packlink.ajaxService;

        for (let key in baseService) {
            if (Object.prototype.hasOwnProperty.call(baseService, key)) {
                this[key] = baseService[key];
            }
        }

        this.internalPerformPost = function (request, data) {
            request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            request.send('plPostData=' + encodeURIComponent(JSON.stringify(data)));
        };
    }

    Packlink.customAjaxService = new CustomAjaxService();
})();