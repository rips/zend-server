(function() {
    // Controller definition
	zsApp.controller('ripsModuleController',
        ['$scope', '$timeout', 'WebAPI', '$rootScope', 'ngDialog', function ($scope, $timeout, WebAPI, $rootScope, ngDialog) {

		$scope.viewScanDetails = function(scan) {
            $scope.issues.initialLoadFinished = false;
		    $scope.scanDetails.load(scan.application.id, scan.id);
			$scope.currentScan = scan;
			ngDialog.open({
				template: '/ZendServer/ModuleResource/RipsModule/templates/scan-details.html',
				scope: $scope,
				closeByEscape: true,
			});
		};

        $scope.ui = {
            activeTab: 'Scans',
            activateTab: function(newTab) {
                $scope.ui.activeTab = newTab;
            },
        };

        $scope.dialogUi = {
            activeTab: 'Summary',
            activateTab: function(newTab) {
                $scope.dialogUi.activeTab = newTab;
                if (newTab === 'Summary') {
                    setTimeout(function() {
                        loadScanDetailsCharts($scope.scanDetails.stats, $scope.scanDetails.types);
                    });
                }
            },
        };
        
        $scope.signinStatus = function () {
            if (!$scope.scan.initialLoadFinished || !$scope.settings.initialLoadFinished) return;
            
            if ($scope.scan.ripsApps.length > 0) return;
            
            if ($scope.settings.email) return;
            
            document.fireEvent('toastWarning', {message: "Cannot find a valid connection to a RIPS server."});
            
            $scope.scan.signedIn = false;
            $scope.scans.signedIn = false;
            $scope.scanFromDocRoot.signedIn = false;
        }
        
        $scope.$watch('scan.initialLoadFinished', function(newValue, oldValue) {
            if ($scope.scan.initialLoadFinished == false) return;

            $scope.signinStatus();
        });
        
        $scope.$watch('settings.initialLoadFinished', function(newValue, oldValue) {
            if ($scope.settings.initialLoadFinished == false) return;

            $scope.signinStatus();
        });

        $scope.scan = {
            zendApps: [],
            ripsApps: [],
            selectedRipsApp: '0',
            selectedZendApp: '0',
            version: new Date().toISOString(),
            newAppName: '',
            signedIn: true,

            // loading
            initialLoadFinished: false,
            loading: false,
            load: function() {
                var errorMessage = 'Error loading applications';
                $scope.scan.loading = true;

                WebAPI({
                    method: 'GET',
                    url: '/ZendServer/Api/ripsCurrentApplications'
                }).then(function(res) {
                    if (res && res.data && res.data.responseData && res.data.responseData.zendApps && res.data.responseData.ripsApps) {
                        $scope.scan.zendApps = res.data.responseData.zendApps || [];
                        $scope.scan.ripsApps = res.data.responseData.ripsApps || [];
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }
                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.scan.initialLoadFinished = true;
                    $scope.scan.loading = false;
                });
            },

            // saving
            isSaving: false,
            save: function() {
                // collect the data
                var data = {
                    'rips_id': $scope.scan.selectedRipsApp,
                    'zend_path': $scope.scan.selectedZendApp,
                    'version': $scope.scan.version,
                    'new_app_name': $scope.scan.newAppName,
                };

                if (data['rips_id'] === '0' && (!data['new_app_name'] || data['new_app_name'] === '')) {
                    document.fireEvent('toastAlert', {message: 'Please enter an application name.'});
                    return;
                }

                // default error message
                var errorMessage = 'Error starting scan';

                $scope.scan.isSaving = true;
                WebAPI({
                    method: 'POST',
                    url: '/ZendServer/Api/ripsScan',
                    data: data
                }).then(function(res) {
                    if (res && res.data && res.data.responseData  && res.data.responseData.success == '1') {
                        document.fireEvent('toastNotification', {message: 'Scan started'});
                        setTimeout(function() {
                            $scope.scans.load(false);
                            $scope.ui.activateTab('Scans');
                        }, 1000);
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }

                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.scan.isSaving = false;
                });
            },
        };

        // Listen to changes to the selected zend app to update the value of the new application name
        // (if it is empty)
        $scope.$watch('scan.selectedZendApp', function(newValue, oldValue) {
            if ($scope.scan.newAppName === '' && newValue != 0) {
                // Search for the path
                var zendApp = $scope.scan.zendApps.find(function (element) {
                    return element.path === newValue;
                });

                if (zendApp) {
                    $scope.scan.newAppName = zendApp.name;
                }
            }
        });

        $scope.$watch('scanFromDocRoot.selectedDocRoot', function(newValue, oldValue) {
            if (newValue == 0) return;

            $scope.scanFromDocRoot.hasScanSpec = false;
            $scope.scanFromDocRoot.loadScanSpec();
        }, true);

        $scope.scanFromDocRoot = {
            ripsApps: [],
            selectedRipsApp: '0',
            selectedDocRoot: '0',
            scanSpec: '',
            version: new Date().toISOString(),
            loading: false,
            newAppName: '',
            signedIn: true,

            // loading
            initialLoadFinished: false,
            loadingrefresh: false,

            load: function() {
                var errorMessage = 'Error loading applications';
                $scope.scanFromDocRoot.loadingrefresh = true;
                $scope.scanFromDocRoot.selectedDocRoot = 0;
                $scope.scanFromDocRoot.hasScanSpec = false;

                WebAPI({
                    method: 'GET',
                    url: '/ZendServer/Api/ripsCurrentDocRoots'
                }).then(function(res) {
                    if (res && res.data && res.data.responseData && res.data.responseData.docRootSet && res.data.responseData.ripsApps) {
                        $scope.scanFromDocRoot.docRootSet = res.data.responseData.docRootSet || [];
                        $scope.scanFromDocRoot.ripsApps = res.data.responseData.ripsApps || [];
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }
                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.scanFromDocRoot.initialLoadFinished = true;
                    $scope.scanFromDocRoot.loadingrefresh = false;
                });
            },

            hasScanSpec: false,
            loadScanSpec: function() {

                // collect the data
                var data = {
                    'vhost_id': $scope.scanFromDocRoot.selectedDocRoot
                };

                // default error message
                var errorMessage = 'Error starting scan';

                WebAPI({
                    method: 'POST',
                    url: '/ZendServer/Api/ripsScanSpec',
                    data: data
                }).then(function(res) {
                    $scope.scanFromDocRoot.scanSpec = res.data.responseData.scanSpec;
                    $scope.scanFromDocRoot.hasScanSpec = true;
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }

                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.scanFromDocRoot.isSaving = false;
                    $scope.scanFromDocRoot.hasScanSpec = true;
                });
            },

            // saving
            isSaving: false,
            save: function() {
                // collect the data
                var data = {
                    'rips_id': $scope.scanFromDocRoot.selectedRipsApp,
                    'scan_spec': $scope.scanFromDocRoot.scanSpec,
                    'vhost_id': $scope.scanFromDocRoot.selectedDocRoot,
                    'version': $scope.scanFromDocRoot.version,
                    'new_app_name': $scope.scanFromDocRoot.newAppName,
                };

                if (data['rips_id'] === '0' && (!data['new_app_name'] || data['new_app_name'] === '')) {
                    document.fireEvent('toastAlert', {message: 'Please enter an application name.'});
                    return;
                }

                // default error message
                var errorMessage = 'Error starting scan';

                $scope.scanFromDocRoot.isSaving = true;
                WebAPI({
                    method: 'POST',
                    url: '/ZendServer/Api/ripsScanDocRoot',
                    data: data
                }).then(function(res) {
                    if (res && res.data && res.data.responseData  && res.data.responseData.success == '1') {
                        document.fireEvent('toastNotification', {message: 'Scan started'});
                        setTimeout(function() {
                            $scope.scans.load(false);
                            $scope.ui.activateTab('Scans');
                        }, 1000);
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }

                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.scanFromDocRoot.isSaving = false;
                });
            },
        };

        $scope.$watch('settings.email', function(newValue, oldValue) {
            if (oldValue == '') return;
            $scope.settings.readyToTest = true;
            $scope.settings.isTestSuccessful = false;
        });
        
        $scope.$watch('settings.password', function(newValue, oldValue) {
            if (oldValue == '') return;
            $scope.settings.readyToTest = true;
            $scope.settings.isTestSuccessful = false;
        });
        
		$scope.settings = {
		    email: '',
		    password: '',
		    api_url: '',
		    ui_url: '',
		    readyToTest: false,

            // loading
            initialLoadFinished: false,
            load: function() {
                var errorMessage = 'Error loading RIPS settings';
                $scope.settings.loading = true;

                WebAPI({
                    method: 'GET',
                    url: '/ZendServer/Api/ripsSettings'
                }).then(function(res) {
                    if (res && res.data && res.data.responseData && res.data.responseData.settings) {
                        $scope.settings.email = res.data.responseData.settings.email || '';
                        $scope.settings.password = res.data.responseData.settings.password || '';
                        $scope.settings.api_url = res.data.responseData.settings.api_url || '';
                        $scope.settings.ui_url = res.data.responseData.settings.ui_url || '';
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }
                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.settings.initialLoadFinished = true;
                });

            },

            // saving
            isSaving: false,
            save: function() {
                // collect the data
                var data = {
                    'email': $scope.settings.email,
                    'password': $scope.settings.password,
                    'api_url': $scope.settings.api_url,
                    'ui_url': $scope.settings.ui_url,
                };

                // default error message
                var errorMessage = 'Error updating RIPS settings';

                $scope.settings.isSaving = true;
                WebAPI({
                    method: 'POST',
                    url: '/ZendServer/Api/ripsStoreSettings',
                    data: data
                }).then(function(res) {
                    if (res && res.data && res.data.responseData  && res.data.responseData.success == '1') {
                        document.fireEvent('toastNotification', {message: 'Settings stored'});
                        
                        $scope.scan.signedIn = true;
                        $scope.scans.signedIn = true;
                        $scope.scanFromDocRoot.signedIn = true;
                        
                        $scope.settings.readyToTest = false;
                        
                        $scope.scan.load();
                        $scope.scans.load();
                        $scope.scanFromDocRoot.load();
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }

                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.settings.isSaving = false;
                });
            },

            // testing connection
            isTesting: false,
            isTestSuccessful: false,
            initialTestFinished: false,
            test: function() {
                // collect the data
                var data = {
                    'email': $scope.settings.email,
                    'password': $scope.settings.password,
                    'api_url': $scope.settings.api_url,
                };

                $scope.settings.isTesting = true;

                var httpRequest = new XMLHttpRequest();
                httpRequest.onreadystatechange = function() {
                    if (httpRequest.readyState === XMLHttpRequest.DONE) {
                        if (httpRequest.status === 200) {
                            var data = JSON.parse(httpRequest.responseText);
                            $scope.settings.isTestSuccessful = (data && data.user);
                        } else {
                            $scope.settings.isTestSuccessful = false;
                        }

                        $scope.settings.isTesting = false;
                        $scope.settings.initialTestFinished = true;
                    }
                };

                httpRequest.open("GET", ripsRemoveTrailingSlash(data.api_url) + "/status", true);
                httpRequest.setRequestHeader('X-API-Username', data.email);
                httpRequest.setRequestHeader('X-API-Password', data.password);
                httpRequest.send(null);
            },
        };

		$scope.scans = {
		    scans: [],
            ui_url: '',
            moreScansAvailable: false,
            signedIn: true,

            // loading
            initialLoadFinished: false,
            loading: false,
            load: function(append) {
                var errorMessage = 'Error loading scans';
                $scope.scans.loading = true;
                var offset = append ? $scope.scans.scans.length : 0;
                var limit = !append ? ($scope.scans.scans.length !== 0 ? $scope.scans.scans.length : 20) : 20;

                WebAPI({
                    method: 'GET',
                    url: '/ZendServer/Api/ripsScans?offset=' + offset + '&limit=' + limit,
                }).then(function(res) {
                    if (res && res.data && res.data.responseData && res.data.responseData.scans && res.data.responseData.ui_url) {
                        var scans = res.data.responseData.scans || [];
                        $scope.scans.moreScansAvailable = res.data.responseData.more;
                        $scope.scans.ui_url = res.data.responseData.ui_url || '';

                        var reload = false;
                        scans.forEach(function(scan) {
                            // Reload after a few seconds if there are still running scans
                            if (scan.percent < 100) {
                                reload = true;
                            }

                            // Add calculated risk data to scans
                            scan['severity_distributions'] = scan.severity_distributions.total;
                            scan['risk'] = Math.floor(
                                (parseInt(scan.severity_distributions['critical'])*5) +
                                (parseInt(scan.severity_distributions['high'])*2) +
                                parseInt(scan.severity_distributions['medium']) +
                                (parseInt(scan.severity_distributions['low'])*0.5)
                            );

                            if (scan['risk'] > 100) {
                                scan['risk'] = 100;
                            }
                        });

                        if (append) {
                            $scope.scans.scans = $scope.scans.scans.concat(scans);
                        } else {
                            $scope.scans.scans = scans;
                        }

                        if (reload) {
                            setTimeout(function() {
                                $scope.scans.load(false);
                            }, 2000);
                        }
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }
                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.scans.initialLoadFinished = true;
                    $scope.scans.loading = false;
                });

            },
        };

		$scope.issues = {
		    issues: [],
            ui_url: '',
            moreIssuesAvailable: false,

            // loading
            initialLoadFinished: false,
            loading: false,
            load: function(applicationId, scanId, append) {
                var errorMessage = 'Error loading issues';
                $scope.issues.loading = true;
                var offset = append ? $scope.issues.issues.length : 0;
                var limit = !append ? ($scope.issues.issues.length !== 0 ? $scope.issues.issues.length : 200) : 200;

                WebAPI({
                    method: 'GET',
                    url: '/ZendServer/Api/ripsIssues?application_id=' + applicationId + '&scan_id=' + scanId + '&offset=' + offset + '&limit=' + limit,
                }).then(function(res) {
                    if (res && res.data && res.data.responseData && res.data.responseData.issues && res.data.responseData.ui_url) {
                        var issues = res.data.responseData.issues || [];
                        $scope.issues.ui_url = res.data.responseData.ui_url || '';
                        $scope.issues.moreIssuesAvailable = issues.length % 200 === 0;

                        if (append) {
                            $scope.issues.issues = $scope.issues.issues.concat(issues);
                        } else {
                            $scope.issues.issues = issues;
                        }
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }
                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.issues.initialLoadFinished = true;
                    $scope.issues.loading = false;
                });

            },
        };

		$scope.scanDetails = {
		    scan: {},
            stats: {},
            ui_url: '',

            // loading
            initialLoadFinished: false,
            loading: false,
            load: function(applicationId, scanId) {
                var errorMessage = 'Error loading scan details';
                $scope.scanDetails.loading = true;
                $scope.issues.issues = [];

                WebAPI({
                    method: 'GET',
                    url: '/ZendServer/Api/ripsScanDetails?application_id='+applicationId+'&scan_id='+scanId
                }).then(function(res) {
                    if (res && res.data && res.data.responseData && res.data.responseData.scan &&
                        res.data.responseData.stats && res.data.responseData.types && res.data.responseData.ui_url
                    ) {
                        $scope.scanDetails.scan = res.data.responseData.scan || {};
                        $scope.scanDetails.stats = res.data.responseData.stats || {};
                        $scope.scanDetails.types = res.data.responseData.types || {};
                        $scope.scanDetails.ui_url = res.data.responseData.ui_url || '';

                        setTimeout(function() {
                            loadScanDetailsCharts($scope.scanDetails.stats, $scope.scanDetails.types);
                        });
                    } else {
                        document.fireEvent('toastAlert', {message: errorMessage});
                    }
                }, function(res) {
                    if (typeof(res.data.errorData.errorMessage) != 'undefined') {
                        errorMessage = res.data.errorData.errorMessage;
                    }
                    document.fireEvent('toastAlert', {message: errorMessage});
                }).finally(function() {
                    $scope.scanDetails.initialLoadFinished = true;
                    $scope.scanDetails.loading = false;
                });

            },
        };

        $scope.scan.load();
        $scope.scanFromDocRoot.load();
        $scope.settings.load();
        $scope.scans.load(false);
    }]);

	// Helper functions

	function loadScanDetailsCharts(stats, types) {
        c3.generate({
            bindto: '#severity-chart',
            data: {
                columns: [
                    ['Critical', stats.issue_severities.total.critical],
                    ['High', stats.issue_severities.total.high],
                    ['Medium', stats.issue_severities.total.medium],
                    ['Low', stats.issue_severities.total.low],
                ],
                colors: {
                    Critical: '#e12e2e',
                    High: '#e15d5d',
                    Medium: '#ffc427',
                    Low: '#a9c171',
                },
                type : 'pie',
            }
        });

        var columns = [];
        var colors = {};
        types.forEach(function(entry) {
            columns.push([entry.type.name, entry.amount]);
            colors[entry.type.name] = SEVERITY_COLOR_GRADIENT[entry.type.severity];
        });

        c3.generate({
            bindto: '#type-chart',
            data: {
                columns: columns,
                colors: colors,
                type : 'donut',
            }
        });
    }

    function ripsRemoveTrailingSlash(value) {
        if (value.substr(-1) === '/') {
            return value.substr(0, value.length - 1);
        }

        return value;
    }

}());

const SEVERITY_COLOR_GRADIENT = [
    '#a7a863',
    '#a8a763',
    '#a8a662',
    '#a9a562',
    '#aaa462',
    '#aaa361',
    '#aba261',
    '#aca161',
    '#aca060',
    '#ad9f60',
    '#ae9e60',
    '#ae9d5f',
    '#af9c5f',
    '#af9b5f',
    '#b09a5e',
    '#b1995e',
    '#b1985e',
    '#b2975d',
    '#b3965d',
    '#b3955d',
    '#b4945c',
    '#b5935c',
    '#b5925c',
    '#b6915b',
    '#b7905b',
    '#b7905b',
    '#b88f5a',
    '#b98e5a',
    '#b98d5a',
    '#ba8c59',
    '#bb8b59',
    '#bb8a59',
    '#bc8958',
    '#bc8858',
    '#bd8758',
    '#be8657',
    '#be8557',
    '#bf8457',
    '#c08356',
    '#c08256',
    '#c18156',
    '#c28055',
    '#c27f55',
    '#c37e55',
    '#c47d54',
    '#c47c54',
    '#c57b54',
    '#c67a53',
    '#c67953',
    '#c77853',
    '#c87753',
    '#c87652',
    '#c97552',
    '#c97452',
    '#ca7351',
    '#cb7251',
    '#cb7151',
    '#cc7050',
    '#cd6f50',
    '#cd6e50',
    '#ce6d4f',
    '#cf6c4f',
    '#cf6b4f',
    '#d06a4e',
    '#d1694e',
    '#d1684e',
    '#d2674d',
    '#d3664d',
    '#d3654d',
    '#d4644c',
    '#d5634c',
    '#d5624c',
    '#d6614b',
    '#d6604b',
    '#d75f4b',
    '#d85f4a',
    '#d85e4a',
    '#d95d4a',
    '#da5c49',
    '#da5b49',
    '#db5a49',
    '#dc5948',
    '#dc5848',
    '#dd5748',
    '#de5647',
    '#de5547',
    '#df5447',
    '#e05346',
    '#e05246',
    '#e15146',
    '#e25045',
    '#e24f45',
    '#e34e45',
    '#e34d44',
    '#e44c44',
    '#e54b44',
    '#e54a43',
    '#e64943',
    '#e74843',
    '#e74742',
];