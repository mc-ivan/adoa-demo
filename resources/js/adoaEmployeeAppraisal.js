import Vue from "vue";
import BootstrapVue from "bootstrap-vue";
import VModal from "vue-js-modal";

Vue.use(VModal);
Vue.use(BootstrapVue);



var app = new Vue({
    el: '#app',
    data() {
        return {

            adoaEmployee: {},
            adoaEmployeeSelected:'',
            adoaEmployeeName : '',
            adoaEin : '',
            documents : [
                {'id' : 1, 'description' : 'Employee Coaching Note' },
                {'id' : 2, 'description' : 'Manager Coaching Note' },
                {'id' : 3, 'description' : 'Employee Self-Appraisal' },
                {'id' : 4, 'description' : 'Informal Manager Appraisal for Employee' },
                {'id' : 5, 'description' : 'Formal Manager Appraisal for Employee' }
            ],
            documentsSelected : [],
            allPerformance :false,
            initDate :'',
            endDate : '',
            appraisalList : [],
            currentUserId : ''
        }
    },
    methods : {
        nameWithLang ({ name, language }) {
            return `${name} — [${language}]`
        },
        populateEmployeeList(){
            ProcessMaker.apiClient
            .get(
                "/adoa/employee-list"
            )
            .then(response => {
                this.adoaEmployee = response.data;
            });
        },
        selectEmployee(event) {
            let employeeId = event.target.value;
            if(employeeId != '') {
                ProcessMaker.apiClient
                .get("/adoa/user/" + employeeId)
                .then(response => {
                    this.adoaEmployeeName = (response.data.firstname + ' ' + response.data.lastname).toUpperCase();
                    this.adoaEin          = response.data.title;
                });
            } else {
                this.adoaEmployeeName = '';
                this.adoaEin          = '';
            }
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
                this.currentUser +
                "&type=" +
                this.documentsSelected.toString()
            )
            .then(response => {
                this.appraisalList = response.data;
            });
        },
        getAppraisalType(value) {
            let type = '';
            switch (value) {
                case 1:
                    type = 'Employee Coaching Note';
                break;
                case 2:
                    type = 'Manager Coaching Note';
                break;
                case 3:
                    type = 'Employee Self-Appraisal';
                break;
                case 4:
                    type = 'Informal Manager Appraisal for Employee';
                break;
                case 5:
                    type = 'Formal Manager Appraisal for Employee';
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
    mounted() {
        let self = this; // ámbito de vue

        // inicializas select2
        $('#adoaEmployeeSelected')
        .select2({
            placeholder: 'Select an option',
            data: self.adoaEmployee, // cargas los datos en vez de usar el loop
        })
        // nos hookeamos en el evento tal y como puedes leer en su documentación
        .on('select2:select', function () {
            var value = $("#adoaEmployeeSelected").select2('data');
        // nos devuelve un array

        // ahora simplemente asignamos el valor a tu variable selected de VUE
            self.adoaEmployeeSelected = value[0].id
        })
    }


});
