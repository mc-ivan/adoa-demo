@extends('layouts.layout')

@section('sidebar')
    @include('layouts.sidebar', ['sidebar'=> Menu::get('sidebar_request')])
@endsection
@section('css')
    <script>
        window.temp_define = window['define'];
        window['define']  = undefined;
    </script>
    <!-- Sugest  selectpicker -->
    {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.8/js/select2.min.js" defer></script>
    <link href="https://rawgit.com/select2/select2/master/dist/css/select2.min.css" rel="stylesheet"/> --}}
    <!-- /Sugest selectpicker -->
    <script>
        window['define'] = window.temp_define;
    </script>

    <link rel="stylesheet" href="{{mix('/css/package.css', 'vendor/processmaker/packages/adoa')}}">
    <style>
        [v-cloak] {
            display:none;
        }
        .label-color {
            color:#71A2D4;
        }
        .select2 > span, .select2-selection, .selection, .select2-selection__placeholder, .select2-selection__rendered {
            border-radius:0px !important;
        }
        #select2-adoaEmployee-results > .select2-results__option:hover {
            background-color: #71A2D4 !important;
            color:#fff;
        }
        .btn-primary {
            background-color: #71A2D4 !important;
            border:solid #71A2D4 !important;
        }

        #appraisalList > tbody > tr > td > a > i {
            color: #71A2D4 !important;
        }
        #appraisalList > tbody > tr > td > a > i:hover {
            color: #71A2D4 !important;
        }

        #appraisalList > thead > tr {
            text-align: center;
            background-color: #71A2D4;
            color: #fff;

        }
        #appraisalList > thead > tr > th {
            border-bottom: 3px solid  #505050 !important;
            padding-top:10px;
            padding-bottom:10px;
        }

        td > a :hover{
            color: #71A2D4 !important;
        }
        #appraisalList_paginate > ul > li.paginate_button.page-item.active > a {
            background-color: #71A2D4 !important;
            color: #fff !important;
        }
        #appraisalList_paginate > ul > li > a{
            color: #71A2D4 !important;
        }
        #appraisalList_previous > a {
            color: #71A2D4 !important;
        }
        .page-item.active .page-link  {
            border: #71A2D4;
        }

        #appraisalList_length > label,
        #appraisalList_filter > label {
            color: #71A2D4 !important;
        }
        input[type=date]::-webkit-calendar-picker-indicator {
        }

        #select2-adoaEmployee-results > li:nth-child(1) {
            padding-top: 8px !important;
            padding-bottom: 8px !important;
            border-top: 3px solid #71A2D4 !important;
            border-bottom: 3px solid #71A2D4 !important;
        }



    </style>
@endsection

@section('content')

    <div class="container border" id="app" style="padding:20px;">
        <div class="row" v-cloak>
            <div class="col-lg-12 col-md-12 col-sm-12" style="text-align: center;">
                <p><h2>Print Performance Documentation </h2></p>
            </div>

            <div class="form-group col-lg-8 col-md-8 col-sm-12 offset-lg-2 offset-md-2 " style="display: flex;align-items: right;">
                <label for="adoaEmployee" style="width:50%;" class="label-color">
                    Please select the employee name:
                </label>
                <select class="form-control" id="adoaEmployeeAdmin" style="width:50%;" v-model="adoaEmployeeSelected" v-if="isSysAdmin"></select>
                <select class="form-control" id="adoaEmployee" style="width:50%;" v-model="adoaEmployeeSelected" v-if="!isSysAdmin"></select>
                <div v-if="loading" style="display:inherit;">
                    <button class="btn btn-default">
                        <span class="spinner-border spinner-border-sm text-primary"></span>
                    </button>
                    <small class="text-secondary">We are loading your data, please be patient...</small>
                </div>
            </div>

            <div class="col-lg-12 col-md-12 col-sm-12" style="margin:10px;">
                <div class="row">
                    <div class="form-group col col-lg-4 col-md-4 col-sm-12">
                        <label for="adoaEmployeeName" style="padding:5px;" class="label-color">
                            Employee Name
                        </label>
                        <input type="text" class="form-control"
                        id="adoaEmployeeName"
                        placeholder="Employee Name" disabled
                        v-model="adoaEmployeeName">
                    </div>
                    <div class="form-group col col-lg-4 col-md-4 col-sm-12">
                        <label for="adoaEin" style="padding:5px;" class="label-color">
                            EIN
                        </label>
                        <input type="text" class="form-control" id="adoaEin" placeholder="EIN" disabled v-model="adoaEin">
                    </div>
                    <div class="form-group col col-lg-4 col-md-4 col-sm-12">
                        <label for="agencyName" style="padding:5px;" class="label-color">
                            Agency
                        </label>
                        <input type="text" class="form-control" id="agencyName" placeholder="Agency" disabled v-model="agencyName">
                    </div>
                </div>

                <div  class="row" style="padding:10px;">
                    <label for="" style="margin-left:10px;" class="label-color">
                        Select document(s)
                    </label>
                    <div class="row" style="border: 1px solid #dfdfdf; margin:0 5px 5px 5px; paddind:10px;background-color:#fff;">
                        <div class="col col-lg-6 col-sm-12" v-for="document in documents">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input"
                                :id="document.id"
                                :value="document.id"
                                @change="uncheckAllPerformance($event)"
                                v-model="documentsSelected">
                                <label class="form-check-label" :for="document.id">
                                    @{{ document.description}}
                                </label>
                            </div>
                        </div>
                        <div class="col col-lg-6 col-sm-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input"
                                id="allPerformance"
                                @change="checkAllDocuments($event)"
                                v-model="allPerformance">
                                <label class="form-check-label" for="allPerformance">
                                    All performance documents
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container"  style="margin:10px;">
                    <div class="card col-lg-8 col-md-8 col-sm-12 col offset-lg-2 offset-md-2">
                        <div class="" style="text-align:center;padding-top:5px;">
                            <h4 class="">Print Documents From / To</h4>
                        </div>

                        <div class="row">
                            <div class="form-group col col-lg-6 col-md-6 col-sm-12">
                                <label for="initDate" style="padding:5px;" class="label-color">
                                    From
                                </label>
                                <input type="date" class="form-control" id="initDate" placeholder="Select a init date" v-model="initDate">
                            </div>
                            <div class="form-group col col-lg-6 col-md-6 col-sm-12">
                                <label for="endDate"  style="padding:5px;" class="label-color">
                                    To
                                </label>
                                <input type="date" class="form-control" id="endDate" placeholder="Select a end date" vmodel="endDate">
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-12 col-md-12 col-sm-12" style="text-align: center" v-if="adoaEmployeeSelected != ''">
                    <button id="btnGetList" class="btn btn-primary btn-sm"  @click="getAppraisalList">Get List</button>
                </div>

            </div>

            <div id="listContainer" class="col-lg-12 col-md-12 col-sm-12" v-if="showList == true && appraisalList.length > 0">
                <div class="table-responsive" style="padding:5px; border:1px solid #dfdfdf;boder-radius:5px;margin-top:auto;background-color:#fff;">
                    <div style="text-align:center;"><h2>Appraisal</h2></div>

                    <table id="appraisalList" class="table table-striped table-hover table-sm" style="width: 100%;">
                    </table>

                </div>
                    <div class="col-lg-12 col-md-12 col-sm-12" style="text-align: center;margin-top:20px;">
                        <button id="btnGetCvs" class="btn btn-primary btn-sm" @click="exportPdf">Export Document</button>
                    </div>
            </div>
            <div id="emptyAppraisalList" class="alert alert-info" role="alert" style="width:80%;margin:auto;" v-if="showList == true && appraisalList.length <= 0">
                <span><i class="fas fa-info-circle"></i> There are no results to show.</span>
            </div>
        </div>
        {{-- Modal View PDF --}}
        <div class="modal fade" id="showPdf" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header text-right">
                        <h5 class="modal-title"></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                    </div>
                </div>
            </div>
        </div>



    </div>


    @section('js')
        {{-- JqueryDataTable --}}
        <link href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.css">
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

        <script>
            var app = new Vue({
                el: '#app',

                data() {
                    return {
                        adoaEmployee: {},
                        adoaEmployeeSelected:'',
                        adoaEmployeeName : '',
                        adoaUser : {!! json_encode($adoaUser, true) !!},
                        adoaEin : '',
                        agencyName : '',
                        dataTable: '',
                        documents : [
                            {'id' : 1, 'description' : 'My Coaching Note' },
                            {'id' : 2, 'description' : 'Coaching Note for My Direct Report' },
                            {'id' : 3, 'description' : 'Self-Appraisal' },
                            {'id' : 4, 'description' : 'Informal Appraisal' },
                            {'id' : 5, 'description' : 'Formal Employee Appraisal' }
                        ],
                        documentsSelected : [],
                        allPerformance :false,
                        initDate :'',
                        endDate : '',
                        appraisalList : [],
                        showList : false,
                        currentUserId : {{ auth()->user()->id }},
                        isManager: {{ empty($isManager) ? 'false' : $isManager }},
                        isSysAdmin: {{ empty($isSysAdmin) ? 'false' : $isSysAdmin }},
                        loading:false
                    }
                },
                methods : {
                    populateEmployeeList(){
                        this.loading = true;
                        ProcessMaker.apiClient
                        .get(
                            "/adoa/employee-list/" + this.currentUserId
                        )
                        .then(function(response) {
                            let newData= [{ 'id' : '', 'text' : '- Select -'}];
                            newData = newData.concat(response.data);

                            app.adoaEmployee = newData;

                            $('#adoaEmployee')
                            .select2({
                                placeholder: 'Select an option',
                                data : app.adoaEmployee
                            })
                            .on('select2:select', function () {
                                app.appraisalList = [];
                                app.showList = false;
                                var value = $("#adoaEmployee").select2('data');
                                app.adoaEmployeeSelected = value[0].id;

                                if(self.adoaEmployeeSelected != '') {
                                    ProcessMaker.apiClient
                                    .get("/adoa/user/" + app.adoaEmployeeSelected)
                                    .then(response => {
                                        app.adoaEmployeeName = (response.data.firstname + ' ' + response.data.lastname).toUpperCase();
                                        app.adoaEin          = response.data.meta.ein;
                                        app.agencyName       = response.data.meta.agency_name;
                                    })
                                    .catch(response => {
                                        console.log(response);
                                    });
                                } else {
                                    app.adoaEmployeeName = '';
                                    app.adoaEin          = '';
                                    app.agencyName       = '';
                                }
                            });

                            $('#adoaEmployeeAdmin')
                            .select2({
                                placeholder: 'Select an option',
                                minimumInputLength: 2,
                                ajax: {
                                    url: "{{url('api/1.0/adoa/employee-list')}}/{{ auth()->user()->id }}",
                                    dataType: 'json',
                                    delay: 250,
                                    data: function (params) {
                                        return {
                                        searchTerm: params.term // search term
                                        };
                                    },
                                    headers: {
                                        "X-CSRF-TOKEN" : "{{ csrf_token() }}",
                                        "Content-Type" : "application/json",
                                    },
                                    processResults: function (response) {
                                        return {
                                            results: response
                                        };
                                    }
                                }
                            })
                            .on('select2:select', function () {
                                app.appraisalList = [];
                                app.showList = false;
                                var value = $("#adoaEmployeeAdmin").select2('data');
                                app.adoaEmployeeSelected = value[0].id;

                                if(self.adoaEmployeeSelected != '') {
                                    ProcessMaker.apiClient
                                    .get("/adoa/user/" + app.adoaEmployeeSelected)
                                    .then(response => {
                                        app.adoaEmployeeName = (response.data.firstname + ' ' + response.data.lastname).toUpperCase();
                                        app.adoaEin          = response.data.meta.ein;
                                        app.agencyName       = response.data.meta.agency_name;
                                    })
                                    .catch(response => {
                                        console.log(response);
                                    });
                                } else {
                                    app.adoaEmployeeName = '';
                                    app.adoaEin          = '';
                                    app.agencyName       = '';
                                }
                            });
                            app.loading = false;
                        })
                        .catch(function(response) {
                            app.loading = false;
                        });
                    },
                    checkAllDocuments(event) {
                        let isChecked = event.target.checked;
                        this.documentsSelected = isChecked ? [1,2,3,4,5] : [];
                    },
                    uncheckAllPerformance(event) {
                        let isthisChecked = event.target.checked;
                        this.allPerformance = false;
                    },
                    getAppraisalList() {
                        ProcessMaker.apiClient
                        .get(
                            "/adoa/employee-appraisal?initDate=" +
                            this.initDate +
                            "&endDate=" +
                            this.endDate +
                            "&userId=" +
                            this.adoaEmployeeSelected +
                            "&currentUser=" +
                            this.currentUserId +
                            "&type=" +
                            this.documentsSelected.toString()
                        )
                        .then(response => {
                            console.log(response.data);
                            this.appraisalList = response.data;
                            this.showList = true;
                            $('#appraisalList').DataTable().destroy();
                        })
                        .catch(response => {
                            console.log(response);
                        });
                    },
                    getAppraisalType(value) {
                        let type = '';
                        switch (value) {
                            case 1:
                                type = 'My Coaching Note';
                            break;
                            case 2:
                                type = 'Coaching Note for My Direct Report';
                            break;
                            case 3:
                                type = 'Self-Appraisal';
                            break;
                            case 4:
                                type = 'Informal Appraisal';
                            break;
                            case 5:
                                type = 'Formal Employee Appraisal';
                            break;
                            default:
                                type = '--';
                            break;
                        }
                        return type;
                    },
                    getAppraisalContent(type, contentJson){
                        let content = '';
                        let appraisalContent = '';
                        content = JSON.parse(contentJson);

                        if(contentJson !== null && contentJson.length > 0 && contentJson != '') {

                            switch (type) {
                                case 1:
                                    if (content.commitments != null && content.commitments != '') {
                                        appraisalContent = content.commitments;
                                    }
                                break;
                                case 2:
                                    if (content.commitments != null && content.commitments != '') {
                                        appraisalContent = content.commitments;
                                    }
                                break;
                                case 3:
                                    if (content.section5_comments != null && content.section5_comments != '') {
                                        appraisalContent = content.section5_comments;
                                    }
                                break;
                                case 4:
                                    if (content.section5_comments != null && content.section5_comments != '') {
                                        appraisalContent = content.section5_comments;
                                    }
                                break;
                                case 5:
                                    if (content.section5_comments != null && content.section5_comments != '') {
                                        appraisalContent = content.section5_comments;
                                    }
                                break;
                                default:
                                    appraisalContent = '';
                                break;
                            }
                        }
                        return appraisalContent;
                    },
                    exportPdf() {
                        window.location = "{{ URL::asset('adoa/employee-appraisal/print') }}" + "?initDate=" +
                        this.initDate +
                        "&endDate=" +
                        this.endDate +
                        "&userId=" +
                        this.adoaEmployeeSelected +
                        "&type=" +
                        this.documentsSelected.toString();
                    }
                },
                created () {
                    this.populateEmployeeList();

                },
                mounted: function () {
                },
                watch: {
                    appraisalList() {
                        this.$nextTick(function () {
                            this.dataTable = $('#appraisalList').DataTable({
                                "responsive": true,
                                "processing": true,
                                "order": [[ 1, "asc" ]],
                                "data" : app.appraisalList,
                                "columns": [
                                    { "title": "Id", "data": "id","visible": false, "defaultContent": ""},
                                    { "title": "Request No.",  "data": "request_id", "sortable": true, "defaultContent": "No Regitred", "class": "text-center" },
                                    { "title": "From", "data": "evaluator_fullname", "defaultContent": ""},
                                    { "title": "To", "data": "fullname", "defaultContent": ""},
                                    { "title": "EIN", "data": "user_ein", "defaultContent": "", "sortable": false},
                                    { "title": "Type", "data": "type", "defaultContent": "",
                                        "render": function (data, type, row) {
                                            return app.getAppraisalType(row.type);
                                        }
                                    },
                                    { "title": "Comments", "data": "content", "defaultContent": "",
                                        "render": function (data, type, row, meta) {
                                            return app.getAppraisalContent(row.type, row.content)
                                        }
                                    },
                                    { "title": "Date", "data": "date", "defaultContent": "",
                                        "render": function (data, type, row) {
                                            return (row.date).substring(0, 10);
                                        }
                                    },
                                    {
                                        "title": "Actions", "data" : "", "sortable": false, "defaultContent": "",
                                        "render": function (data, type,row) {
                                            let html = '';
                                            html += '<a href="#"><i class="fas fa-eye" style="color: #71A2D4;" title="View PDF" onclick="viewPdf(' + row.request_id + ', ' + row.file_id + ');"></i></a>&nbsp;';
                                            html += '<a href="#"><i class="fas fa-print" style="color: #71A2D4;" title="Print PDF" onclick="printPdf(' + row.request_id + ', ' + row.file_id + ');"></i></a>&nbsp;';
                                            html += '<a href="/request/' + row.request_id + '/files/' + row.file_id + '"><i class="fas fa-download" style="color: #71A2D4;" title="Download PDF"></i></a>&nbsp;';

                                            return html;
                                        }
                                    }
                                ]
                            }).draw();
                        });
                    }
                }

            }).$mount('#app');

        </script>
    @endsection
@endsection
