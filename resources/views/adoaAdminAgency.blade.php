@extends('layouts.layout')

@section('sidebar')
    @include('layouts.sidebar', ['sidebar'=> Menu::get('sidebar_request')])
@endsection
@section('css')
    <link rel="stylesheet" href="{{mix('/css/package.css', 'vendor/processmaker/packages/adoa')}}">
    <link href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.css">
@endsection
@section('content')
<div class="col-sm-12">
    <h3>Agency {{ $agencyName }}</h3>
    <div class="card card-body table-card table-responsive" id="app-adoa">
        <table class="table table-striped table-hover" id="listRequestsAgency" width="100%" style="font-size: 13px">
            <thead class="table-primary">
                <tr>
                    <th scope="col" class="apply-filter" width="5%">#</th>
                    <th scope="col" class="apply-filter" width="15%">Process</th>
                    <th scope="col" class="apply-filter" width="20%">Employee Name</th>
                    <th scope="col" class="apply-filter" width="10%">EIN</th>
                    <th scope="col" class="apply-filter" width="15%">Started</th>
                    <th scope="col" class="apply-filter" width="15%">Completed</th>
                    <th scope="col" class="apply-filter" width="10%">Status</th>
                    <th scope="col" class="text-center" width="10%"><strong>Options</strong></th>
                </tr>
            </thead>
            <tbody>
                @php
                    $count = count($adoaListRequestsAgency);
                @endphp
                @if ($count > 0)
                    @foreach ($adoaListRequestsAgency as $request)
                        @if ($request->name != 'Email Notification Sub Process')
                            @php
                                $createdDate = $request->created_at;
                                $newCreatedDate = new DateTime($createdDate);
                                //$newCreatedDate->setTimezone(new DateTimeZone(Auth::user()->timezone));
                                $newCreatedDate->setTimezone(new DateTimeZone('America/Phoenix'));
                                if($request->completed_at != null) {
                                    $completedDate = $request->completed_at;
                                    $newCompletedDate = new DateTime($completedDate);
                                    //$newCompletedDate->setTimezone(new DateTimeZone(Auth::user()->timezone));
                                    $newCompletedDate->setTimezone(new DateTimeZone('America/Phoenix'));
                                    $newCompletedDateFormat = $newCompletedDate->format('m/d/Y h:i:s A');
                                } else {
                                    $newCompletedDateFormat = '';
                                }
                                $data = $request->data;
                                $newData = json_decode($data);
                                if (!is_null($request->custom_properties)) {
                                    $customProperties = $request->custom_properties;
                                    $newCustomProperties = json_decode($customProperties);
                                }
                                $process_id_terminate_rwa_send_email_and_pdf = 45;
                            @endphp
                            @if (!empty($request->file_id) || !is_null($request->file_id))
                                @if (is_null($newCustomProperties->createdBy))
                                    <tr>
                                        <td class="text-left" style="color: #71A2D4;"><strong>{{ $request->request_id }}</strong></td>
                                        <td class="text-left">@if ($request->process_id == $process_id_terminate_rwa_send_email_and_pdf) Remote Work - Terminate Agreement @else {{ $request->name }} @endif</td>
                                        <td class="text-left">
                                            @if ($request->process_id == $process_id_terminate_rwa_send_email_and_pdf)
                                                @php
                                                    $dataName = $newCustomProperties->data_name;
                                                    $nameFile = explode('_', $dataName);
                                                @endphp
                                                @if (array_key_exists(3, $nameFile) && array_key_exists(4, $nameFile))
                                                    {{ $nameFile[3] }} {{ $nameFile[4] }}
                                                @endif
                                            @else
                                                @if (!empty($newData->EMA_EMPLOYEE_FIRST_NAME))
                                                    {{ $newData->EMA_EMPLOYEE_FIRST_NAME }} {{ $newData->EMA_EMPLOYEE_LAST_NAME }}
                                                @elseif(!empty($newData->CON_EMPLOYEE_FIRST_NAME))
                                                    {{ $newData->CON_EMPLOYEE_FIRST_NAME }} {{ $newData->CON_EMPLOYEE_LAST_NAME }}
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-left">
                                            @if ($request->process_id == $process_id_terminate_rwa_send_email_and_pdf)
                                                @if (array_key_exists(5, $nameFile))
                                                    {{ $nameFile[5] }}
                                                @endif
                                            @else
                                                @if (!empty($newData->EMA_EMPLOYEE_EIN))
                                                    {{ $newData->EMA_EMPLOYEE_EIN }}
                                                @elseif (!empty($newData->CON_EMPLOYEE_EIN))
                                                    {{ $newData->CON_EMPLOYEE_EIN }}
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-left">{{ $newCreatedDate->format('m/d/Y h:i:s A') }}</td>
                                        <td class="text-left">{{ $newCompletedDateFormat }}</td>
                                        <td class="text-left">{{ $request->request_status }}</td>
                                        <td class="text-right">
                                            <a href="#"><i class="fas fa-eye" style="color: #71A2D4;" title="View PDF" onclick="viewPdf({{ $request->request_id }}, {{ $request->file_id }});"></i></a>&nbsp;
                                            <a href="#"><i class="fas fa-print" style="color: #71A2D4;" title="Print PDF" onclick="printPdf({{ $request->request_id }}, {{ $request->file_id }});"></i></a>&nbsp;
                                            <a href="/request/{{ $request->request_id }}/files/{{ $request->file_id }}"><i class="fas fa-download" style="color: #71A2D4;" title="Download PDF"></i></a>&nbsp;
                                        </td>
                                    </tr>
                                @endif
                            @else
                                @if((empty($newData->EMA_EMPLOYEE_EIN) && empty($newData->CON_EMPLOYEE_EIN)) && $request->request_status == 'COMPLETED')
                                @else
                                    <tr>
                                        <td class="text-left" style="color: #71A2D4;"><strong>{{ $request->request_id }}</strong></td>
                                        <td class="text-left">@if ($request->process_id == $process_id_terminate_rwa_send_email_and_pdf) Remote Work - Terminate Agreement @else {{ $request->name }} @endif</td>
                                        <td class="text-left">
                                            @if ($request->process_id != $process_id_terminate_rwa_send_email_and_pdf)
                                                @if (!empty($newData->EMA_EMPLOYEE_FIRST_NAME))
                                                    {{ $newData->EMA_EMPLOYEE_FIRST_NAME }} {{ $newData->EMA_EMPLOYEE_LAST_NAME }}
                                                @elseif(!empty($newData->CON_EMPLOYEE_FIRST_NAME))
                                                    {{ $newData->CON_EMPLOYEE_FIRST_NAME }} {{ $newData->CON_EMPLOYEE_LAST_NAME }}
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-left">
                                            @if ($request->process_id != $process_id_terminate_rwa_send_email_and_pdf)
                                                @if (!empty($newData->EMA_EMPLOYEE_EIN))
                                                    {{ $newData->EMA_EMPLOYEE_EIN }}
                                                @elseif (!empty($newData->CON_EMPLOYEE_EIN))
                                                    {{ $newData->CON_EMPLOYEE_EIN }}
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-left">{{ $newCreatedDate->format('m/d/Y h:i:s A') }}</td>
                                        <td class="text-left">{{ $newCompletedDateFormat }}</td>
                                        <td class="text-left">{{ $request->request_status }}</td>
                                        <td class="text-right">
                                            @if ($request->request_status != 'COMPLETED')
                                                <a href="#"><i class="fas fa-people-arrows" style="color: #71A2D4;" title="Reassign Request" onclick="reassign({{ $request->request_id }});"></i></a>&nbsp;
                                            @endif
                                            <a href="/requests/{{ $request->request_id }}"><i class="fas fa-external-link-square-alt" style="color: #71A2D4;" title="Open request"></i></a>
                                        </td>
                                    </tr>
                                @endif
                            @endif
                        @endif
                    @endforeach
                @endif
          </tbody>
        </table>
    </div>
    <div class="modal fade" id="showPdf" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="showReassing" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reassignTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger alert-dismissible fade show" style="display: none" role="alert" id="divMessageError">
                        The <strong>Reassign to:</strong> field is required.
                    </div>
                    <div class="form-group">
                        <strong>Current User: </strong><br><span id="spanCurrentUser"></span>
                    </div>
                    <div class="form-group">
                        <label for="selectUserId"><strong>Reassign to:</strong></label>
                        <select class="select2 form-control" id="selectUserId" required>

                        </select>
                    </div>
                    <div style="display: none;" id="divTaskId">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="buttonReassign">Reassign</button>
                </div>
            </div>
        </div>
    </div>
</div>
@section('js')
<script>
    window.temp_define = window['define'];
    window['define']  = undefined;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script>
    window['define'] = window.temp_define;
</script>
<script type="text/javascript">
    $(document).ready( function () {
        $('th').on("click", function (event) {
            if($(event.target).is("input")){
                event.stopImmediatePropagation();
            }
        });

        let currentUser = {!! Auth::user() !!};
        $("#selectUserId").select2({
            ajax: {
                url: '/api/1.0/adoa/get-users-agency',
                dataType: 'json',
                data: function (data) {
                    return {
                        searchTerm: data.term,
                        agency: currentUser.meta.agency,
                        employee_process_level: currentUser.meta.employee_process_level
                    };
                },
                processResults: function (response) {
                    let list = $.map(response, function (obj) {
                        obj.id   = obj.id;
                        obj.text = obj.firstname + ' ' + obj.lastname  + ' - '  + obj.agency + ' - ' + obj.username;
                        return obj;
                       });
                    return {
                        results: list
                    };
                },
                headers: {
                    "X-CSRF-TOKEN" : "{{ csrf_token() }}",
                    "Content-Type" : "application/json",
                },
                cache: true
            },
            placeholder: 'Select user...',
            width: '100%',
            minimumInputLength: 2
        });
        var table = $('#listRequestsAgency').DataTable({
            "initComplete": function () {
                count = 0;
                this.api().columns().every( function () {
                    if(this.index() != 0 && this.index() != 7) {
                        var title = this.header();
                        //replace spaces with dashes
                        title = $(title).html().replace(/[\W]/g, '-');
                        var column = this;
                        var select = $('<select id="' + title + '" class="select2"></select>')
                        .appendTo( $(column.header()).empty() )
                        .on( 'change', function () {
                            //Get the "text" property from each selected data
                            //regex escape the value and store in array
                            var data = $.map( $(this).select2('data'), function( value, key ) {
                                return value.text ? '^' + $.fn.dataTable.util.escapeRegex(value.text) + '$' : null;
                            });

                            //if no data selected use ""
                            if (data.length === 0) {
                                data = [""];
                            }

                            //join array into string with regex or (|)
                            var val = data.join('|');

                            //search for the option(s) selected
                            column.search( val ? val : '', true, false ).draw();
                        });

                        column.data().unique().sort().each(function (d, j) {
                            if (d != "") {
                                select.append( '<option value="' + d + '">' + d + '</option>' );
                            }
                        });

                        //use column title as selector and placeholder
                        $('#' + title).select2({
                            multiple: true,
                            closeOnSelect: true,
                            placeholder: title,
                            width: '100%'
                        });

                        //initially clear select otherwise first option is selected
                        $('.select2').val(null).trigger('change');
                    }
                });
            },
            "order": [[ 0, "desc" ]],
            "pageLength": 25
        });

        window.reassign = function(request) {
            ProcessMaker.apiClient.get('adoa/get-task-agency/' + request).then(responseTask => {
                $('#reassignTitle').html('Reassign request # <strong id="strongRequestId">' + request + '</strong>');
                $('#spanCurrentUser').html('');
                $('#spanCurrentUser').html(responseTask.data[0].firstname + ' ' + responseTask.data[0].lastname);
                $('#divTaskId').html('');
                $('#divTaskId').html(responseTask.data[0].id);
                $('#showReassing').modal('show');
            });
        }

        $('#buttonReassign').click(function(event){
            if ($('#selectUserId').val() == null) {
                $('#divMessageError').css("display", "");
            } else {
                ProcessMaker.confirmModal('Confirm', '<div class="text-left">Are you sure that you want to reassign the request # ' + $('#strongRequestId').text() + ' from ' + $('#spanCurrentUser').text() + ' to ' + $('#selectUserId option:selected').text() + '?</div>', '', () => {
                    ProcessMaker.apiClient.put('tasks/' + $('#divTaskId').text(), {user_id: $('#selectUserId').val()});
                    ProcessMaker.alert('The request was reassigned successfully! Your browser will be reloaded!', 'success');
                    $('#showReassing').modal('hide');
                    setTimeout(function(){
                        location.reload();
                    }, 3000);
                });
                $('#divMessageError').css("display", "none");
            }
        });

        $('#showReassing').on('hidden.bs.modal', function () {
            $('#selectUserId').val(null).trigger('change');
            $('#divMessageError').css("display", "none");
        })
    });
</script>
@endsection
@endsection
