// needed because this could be loaded before core's service file.
setTimeout(packlinkOverrideCoreAjaxService, 100);

function packlinkOverrideCoreAjaxService() {
    if (Packlink.ajaxService) {
        Packlink.ajaxService.internalPerformPost = function (request, data) {
            request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            request.send('plPostData=' + encodeURIComponent(JSON.stringify(data)));
        };
    } else {
        setTimeout(packlinkOverrideCoreAjaxService, 100);
    }
}
