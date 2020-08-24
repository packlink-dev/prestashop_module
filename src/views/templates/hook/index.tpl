<div id="pl-page">
  <header id="pl-main-header">
    <div class="pl-main-logo">
      <img src="https://cdn.packlink.com/apps/giger/logos/packlink-pro.svg" alt="logo">
    </div>
    <div class="pl-header-holder" id="pl-header-section"></div>
  </header>

  <main id="pl-main-page-holder"></main>

  <div class="pl-spinner pl-hidden" id="pl-spinner">
    <div></div>
  </div>

  <template id="pl-alert">
    <div class="pl-alert-wrapper">
      <div class="pl-alert">
        <span class="pl-alert-text"></span>
        <i class="material-icons">close</i>
      </div>
    </div>
  </template>

  <template id="pl-modal">
    <div id="pl-modal-mask" class="pl-modal-mask pl-hidden">
      <div class="pl-modal">
        <div class="pl-modal-close-button">
          <i class="material-icons">close</i>
        </div>
        <div class="pl-modal-title">

        </div>
        <div class="pl-modal-body">

        </div>
        <div class="pl-modal-footer">
        </div>
      </div>
    </div>
  </template>

  <template id="pl-error-template">
    <div class="pl-error-message" data-pl-element="error">
    </div>
  </template>
</div>

<script type="text/javascript" src="{$gridResizerScript}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Packlink.translations = {
            default: {$lang['default']},
            current: {$lang['current']}
        };

        const pageConfiguration = {
            'login': {
                submit: "{$urls['login']['submit']}",
                listOfCountriesUrl: "{$urls['login']['listOfCountriesUrl']}",
                logoPath: "" // Not used. Logos are retrieved based on the base resource url.
            },
            'register': {
                getRegistrationData: "{$urls['register']['getRegistrationData']}",
                submit: "{$urls['register']['submit']}"
            },
            'onboarding-state': {
                getState: "{$urls['onboardingState']['getState']}"
            },
            'onboarding-welcome': {},
            'onboarding-overview': {
                defaultParcelGet: "{$urls['onboardingOverview']['defaultParcelGet']}",
                defaultWarehouseGet: "{$urls['onboardingOverview']['defaultWarehouseGet']}"
            },
            'default-parcel': {
                getUrl: "{$urls['defaultParcel']['getUrl']}",
                submitUrl: "{$urls['defaultParcel']['submitUrl']}"
            },
            'default-warehouse': {
                getUrl: "{$urls['defaultWarehouse']['getUrl']}",
                getSupportedCountriesUrl: "{$urls['defaultWarehouse']['getSupportedCountriesUrl']}",
                submitUrl: "{$urls['defaultWarehouse']['submitUrl']}",
                searchPostalCodesUrl: "{$urls['defaultWarehouse']['searchPostalCodesUrl']}"
            },
            'configuration': {
                getDataUrl: "{$urls['configuration']['getDataUrl']}"
            },
            'system-info': {
                getStatusUrl: "{$urls['systemInfo']['getStatusUrl']}",
                setStatusUrl: "{$urls['systemInfo']['setStatusUrl']}"
            },
            'order-status-mapping': {
                getMappingAndStatusesUrl: "{$urls['orderStatusMapping']['getMappingAndStatusesUrl']}",
                setUrl: "{$urls['orderStatusMapping']['setUrl']}"
            },
            'my-shipping-services': {
                getServicesUrl: "{$urls['myShippingServices']['getServicesUrl']}",
                deleteServiceUrl: "{$urls['myShippingServices']['deleteServiceUrl']}"
            },
            'pick-shipping-service': {
                getServicesUrl: "{$urls['pickShippingService']['getServicesUrl']}",
                getActiveServicesUrl: "{$urls['pickShippingService']['getActiveServicesUrl']}",
                getTaskStatusUrl: "{$urls['pickShippingService']['getTaskStatusUrl']}",
                startAutoConfigureUrl: "{$urls['pickShippingService']['startAutoConfigureUrl']}",
                disableCarriersUrl: "{$urls['pickShippingService']['disableCarriersUrl']}"
            },
            'edit-service': {
                getServiceUrl: "{$urls['editService']['getServiceUrl']}",
                saveServiceUrl: "{$urls['editService']['saveServiceUrl']}",
                getTaxClassesUrl: "{$urls['editService']['getTaxClassesUrl']}",
                getCountriesListUrl: "{$urls['editService']['getCountriesListUrl']}",
                hasTaxConfiguration: true,
                hasCountryConfiguration: true,
                canDisplayCarrierLogos: true
            }
        };

        Packlink.state = new Packlink.StateController(
            {
                baseResourcesUrl: "{$baseResourcesUrl}",
                stateUrl: "{$urls['stateUrl']}",
                pageConfiguration: pageConfiguration,
                templates: {
                    'pl-login-page': {
                        'pl-main-page-holder': {$templates['login']}
                    },
                    'pl-register-page': {
                        'pl-main-page-holder': {$templates['register']}
                    },
                    'pl-register-modal': {$templates['register-modal']}
                    ,
                    'pl-onboarding-welcome-page': {
                        'pl-main-page-holder': {$templates['onboarding-welcome']}
                    },
                    'pl-onboarding-overview-page': {
                        'pl-main-page-holder': {$templates['onboarding-overview']}
                    },
                    'pl-default-parcel-page': {
                        'pl-main-page-holder': {$templates['default-parcel']}
                    },
                    'pl-default-warehouse-page': {
                        'pl-main-page-holder': {$templates['default-warehouse']}
                    },
                    'pl-configuration-page': {
                        'pl-main-page-holder': {$templates['configuration']},
                        'pl-header-section': ''
                    },
                    'pl-order-status-mapping-page': {
                        'pl-main-page-holder': {$templates['order-status-mapping']},
                        'pl-header-section': ''
                    },
                    'pl-system-info-modal': {$templates['system-info-modal']},
                    'pl-my-shipping-services-page': {
                        'pl-main-page-holder': {$templates['my-shipping-services']},
                        'pl-header-section': {$templates['shipping-services-header']},
                        'pl-shipping-services-table': {$templates['shipping-services-table']},
                        'pl-shipping-services-list': {$templates['shipping-services-list']}
                    },
                    'pl-disable-carriers-modal': {$templates['disable-carriers-modal']},
                    'pl-pick-service-page': {
                        'pl-header-section': '',
                        'pl-main-page-holder': {$templates['pick-shipping-services']},
                        'pl-shipping-services-table': {$templates['shipping-services-table']},
                        'pl-shipping-services-list': {$templates['shipping-services-list']}
                    },
                    'pl-edit-service-page': {
                        'pl-header-section': '',
                        'pl-main-page-holder': {$templates['edit-shipping-service']},
                        'pl-pricing-policies': {$templates['pricing-policies-list']}
                    },
                    'pl-pricing-policy-modal': {$templates['pricing-policy-modal']},
                    'pl-countries-selection-modal': {$templates['countries-selection-modal']},
                }
            }
        );

        Packlink.state.display();

        hidePrestaSpinner();
        calculateContentHeight(5);
    });
</script>
