var Packlink = window.Packlink || {};

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        let createDraftEndpoint = document.querySelector('.pl-create-endpoint'),
            checkDraftStatusEndpoint = document.querySelector('.pl-draft-status'),
            createDraftButtons = document.getElementsByClassName('pl-create-draft-button'),
            viewDraftButtons = document.getElementsByClassName('pl-draft-button'),
            draftsInProgress = document.getElementsByClassName('pl-draft-in-progress'),
            draftButtonTemplate = document.querySelector('.pl-draft-button-template'),
            createDraftTemplate = document.querySelector('.pl-create-draft-template'),
            draftInProgressMessage = document.querySelector('.pl-draft-in-progress-message'),
            draftFailedMessage = document.querySelector('.pl-draft-failed-message');

        for (let viewDraftButton of viewDraftButtons) {
            viewDraftButton.addEventListener('click', function (event) {
                event.stopPropagation();
            })
        }

        for (let createDraftButton of createDraftButtons) {
            createDraftButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                createDraft(createDraftButton);
            });
        }

        for (let draftInProgress of draftsInProgress) {
            let orderId = draftInProgress.getAttribute('data-order-id'),
                parent = draftInProgress.parentElement;

            checkDraftStatus(parent, orderId);
        }

        function createDraft(createDraftButton) {
            let orderId = parseInt(createDraftButton.getAttribute('data-order-id')),
                buttonParent = createDraftButton.parentElement;

            buttonParent.removeChild(createDraftButton);
            buttonParent.innerText = draftInProgressMessage.value;

            Packlink.ajaxService.post(createDraftEndpoint.value, {orderId: orderId}, function () {
                checkDraftStatus(buttonParent, orderId);
            });
        }

        function checkDraftStatus(parent, orderId) {
            clearTimeout(function () {
                checkDraftStatus(parent, orderId);
            });

            Packlink.ajaxService.get(checkDraftStatusEndpoint.value + '&orderId=' + orderId, function (response) {
                if (response.status === 'created') {
                    let viewDraftButton = draftButtonTemplate.cloneNode(true);

                    viewDraftButton.href = response.shipmentUrl;
                    viewDraftButton.classList.remove('pl-draft-button-template');
                    viewDraftButton.addEventListener('click', function (event) {
                        event.stopPropagation();
                    });

                    parent.innerHTML = '';
                    parent.appendChild(viewDraftButton);
                } else if (['failed', 'aborted'].includes(response.status)) {
                    parent.innerText = draftFailedMessage.value;
                    setTimeout(function () {
                        displayCreateDraftButton(parent, orderId)
                    }, 5000)
                } else {
                    setTimeout(function () {
                        checkDraftStatus(parent, orderId)
                    }, 1000);
                }
            });
        }

        function displayCreateDraftButton(parent, orderId) {
            clearTimeout(function () {
                displayCreateDraftButton(parent, orderId)
            });

            let createDraftButton = createDraftTemplate.cloneNode(true);

            createDraftButton.classList.remove('pl-create-draft-template');
            createDraftButton.setAttribute('data-order-id', orderId);

            createDraftButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                createDraft(createDraftButton);
            });

            parent.innerHTML = '';
            parent.appendChild(createDraftButton);
        }
    });
})();