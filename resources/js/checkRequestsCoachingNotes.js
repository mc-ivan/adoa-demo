async function getAgencyEnabled(agency) {
    let agencyEnabled = await ProcessMaker.apiClient.get('adoa/get-agency-enabled/' + agency);
    let enabled = await agencyEnabled.data.rows[0][0];
    return enabled;
}

$('head').append('<link rel="stylesheet" type="text/css" href="/vendor/processmaker/packages/adoa/css/CssLibraryExcelBootstrapTableFilter.css">');

$(document).ready(function () {
    setTimeout(function(){
        $("li.list-group-item:contains('Due')").hide();
    }, 1000);

    $("#listRequests tr").dblclick(function(){
        if (window.location.pathname == '/adoa/dashboard/requests') {
            if ($(this).find('td').eq(8)[0].innerText == "COMPLETED") {
                ProcessMaker.alert('The request has been completed!', 'primary', '15');
            } else {
                ProcessMaker.apiClient.get('adoa/get-open-task/' + ProcessMaker.user.id + '/' + $(this).find('td').eq(0)[0].innerText).then(responseTask => {
                    if (responseTask.data.length == 0) {
                        ProcessMaker.alert('You can not open this request, because ' + $(this).find('td').eq(7)[0].innerText + ' is the owner.', 'warning', '15');
                    } else {
                        window.location = $(this).find('.fa-external-link-square-alt').parent().attr('href');
                    }
                });
            }
        } else {
            window.location = $(this).find('.fa-external-link-square-alt').parent().attr('href');
        }
    });

    $("#listRequestsAgency tr").dblclick(function(){
        if ($(this).find('td').eq(5)[0].innerText == "COMPLETED") {
            ProcessMaker.alert('The request has been completed!', 'primary', '15');
        } else {
            ProcessMaker.apiClient.get('adoa/get-open-task/' + ProcessMaker.user.id + '/' + $(this).find('td').eq(0)[0].innerText).then(responseTask => {
                if (responseTask.data.length == 0) {
                    ProcessMaker.alert('You can not open this request, because other user is the owner.', 'warning', '15');
                } else {
                    window.location = $(this).find('.fa-external-link-square-alt').parent().attr('href');
                }
            });
        }
    });

    if (window.location.pathname == '/profile/edit') {
        ProcessMaker.confirmModal('Caution', '<div class="text-left">Any changes you make in this screen will not be reflected in HRIS.<br>Do not change the email information.<div>' , () => {});
        setTimeout(function() {
            $("button:contains('Cancel')").hide();
        }, 10);
    }
});

window.printPdf = function(request, file) {
    window.open('/adoa/view/' + request + '/' + file).print();
}

window.viewPdf = function(request, file) {
    $('.modal-body').html('');
    $('.modal-body').html('<embed src="/adoa/view/' + request + '/' + file + '" frameborder="0" width="100%" height="800px">');
    $('#showPdf').modal('show');
}
