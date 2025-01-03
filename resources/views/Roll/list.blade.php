@include("layout.header")
<!-- Main Component -->


<main class="p-3">
    <div class="container-fluid">
        <div class="mb-3 text-left">
            <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                <ol class="breadcrumb fs-6">
                    <li class="breadcrumb-item fs-6"><a href="#">Roll</a></li>
                    <li class="breadcrumb-item active fs-6" aria-current="page">List</li>
                </ol>
            </nav>

        </div>
    </div>
    <div class="container">
        <div class="panel-heading">
            <h5 class="panel-title">Roll List</h5>
            <div class="panel-control">
                <button id="addRoll" type="button" class="btn btn-primary fa fa-arrow-right" data-bs-toggle="modal" data-bs-target="#rollModal">
                    Add <ion-icon name="add-circle-outline"></ion-icon>
                </button>
                <button id="addRollImport" type="button" class="btn btn-primary fa fa-arrow-right" data-bs-toggle="modal" data-bs-target="#fileImportModal">
                    Add Roll Import Excel <ion-icon name="add-circle-outline"></ion-icon>
                </button>
            </div>
        </div>
        @if($flag && $flag=='history')
        <div class="panel-body">
            <form id="searchForm">
                <div class="row g-3">
                    <!-- From Date -->
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label class="form-label" for="fromDate">From Date</label>
                            <input type="date" name="fromDate" id="fromDate" class="form-control" max="{{date('Y-m-d')}}" />
                        </div>
                    </div>

                    <!-- Upto Date -->
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label class="form-label" for="uptoDate">Upto Date</label>
                            <input type="date" name="uptoDate" id="uptoDate" class="form-control" max="{{date('Y-m-d')}}" />
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-sm-3">
                        <!-- Search Button -->
                        <input type="button" id="btn_search" class="btn btn-primary w-100" onclick="searchData()" value="Search"/>
                    </div>
                </div>
            </form>

        </div>
        @endif
        <div class="panel-body">
            <table id="postsTable" class="table table-striped table-bordered text-center">
                <thead>
                    <tr>
                        <th >#</th>
                        <th>Purchase Date</th>
                        <th>Vendor Name</th>
                        <th>Hardness</th>
                        <th>Roll Type</th>
                        <th>Roll Size</th>
                        <th>GSM</th>
                        <th>Roll Color</th>
                        <th>Length</th>
                        <th>Roll No</th>
                        <th>Gross Weight</th>
                        <th>Net Weight</th>
                        <th>GSM Variation</th>

                        <th>W</th>
                        <th>L</th>
                        <th>G</th>
                        <th>Bag Type</th>
                        <th>Unit</th>
                        <th>Customer</th>
                        <th>Printing Color</th>
                        <th>Loop Color</th>

                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <x-roll-booking />
</main>
<script>
    const rules = {
        rollNo: {
            required: true,
        },
        vendorId: {
            required: true,
        },
        purchaseDate: {
            required: true,
        },
        rollSize: {
            required: true,
        },
        rollGsm: {
            required: true,
        },
        rollColor: {
            required: true,
        },
        netWeight: {
            required: true,
        },
        grossWeight: {
            required: true,
        },
        estimatedDespatchDate:{
            required: (element) => {
                return $("#forClientId").val() != "";
            },
        },
        bagUnits: {
            required: (element) => {
                return $("#forClientId").val() != "";
            },
        },
        bagTypeId: {
            required: (element) => {
                return $("#forClientId").val() != "";
            },
        },
        "printingColor[]": {
            required: (element) => {
                return $("#forClientId").val() != "";
            },
        },
    };

    var flag = window.location.pathname.split('/').pop();
    $(document).ready(function() {
        $('#bookingPrintingColor').select2({
            placeholder: "Select tags",
            allowClear: true,
            dropdownParent: $('#rollBookingModal'),
            templateResult: formatOption,
            templateSelection: formatOption 
        });

        $('#clientModal').on('hidden.bs.modal', function() {
            $('#rollBookingModal').css("z-index","");
        });

        if (flag != "stoke") {
            $("#addRoll").hide();
            $("#addRollImport").hide();
        }
        const table = $('#postsTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{route('roll.list',':flag')}}".replace(':flag', flag), // The route where you're getting data from
                data: function(d) {

                    // Add custom form data to the AJAX request
                    var formData = $("#searchForm").serializeArray();
                    $.each(formData, function(i, field) {
                        d[field.name] = field.value; // Corrected: use d[field.name] instead of d.field.name
                    });

                },
                beforeSend: function() {
                    $("#btn_search").val("LOADING ...");
                    $("#loadingDiv").show();
                },
                complete: function() {
                    $("#btn_search").val("SEARCH");
                    $("#loadingDiv").hide();
                },
            },

            columns: [
                { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false },
                { data: "purchase_date", name: "purchase_date" },
                { data: "vendor_name", name: "vendor_name" },
                { data: "hardness", name: "hardness" },
                { data: "roll_type", name: "roll_type" },
                { data: "size", name: "size" },
                { data: "gsm", name: "gsm" },
                { data: "roll_color", name: "roll_color" },
                { data: "length", name: "length" },
                { data: "roll_no", name: "roll_no" },
                { data: "gross_weight", name: "gross_weight" },
                { data: "net_weight", name: "net_weight" },
                { data: "gsm_variation", name: "gsm_variation" },
                { data : "w", name: "w" },
                { data : "l", name: "l" },
                { data : "g", name: "g" },
                { data : "bag_type", name: "bag_type" },
                { data : "bag_unit", name: "bag_unit" },
                { data : "client_name", name: "client_name" },
                { data : "print_color", name: "print_color" },
                { data : "loop_color", name: "loop_color" },
                { data: "action", name: "action", orderable: false, searchable: false },
            ],
            dom: 'lBfrtip', // This enables the buttons
            language: {
                lengthMenu: "Show _MENU_" // Removes the "entries" text
            },
            lengthMenu: [
                [10, 25, 50, 100, -1], // The internal values
                ["10 Row", "25 Row", "50 Row", "100 Row", "All"] // The display values, replace -1 with "All"
            ],
            buttons: [{
                eextend: 'csv',
                text: 'Export to Excel',
                className: 'btn btn-success',
                action: function(e, dt, button, config) {
                    $.ajax({
                        url: "{{ route('roll.list', ':flag') }}".replace(':flag', flag) + "?export=true",
                        xhrFields: {
                            responseType: 'blob' // Important for handling binary data
                        },
                        success: function(data, status, xhr) {
                            // Extract the filename from the response header
                            const filename = xhr.getResponseHeader('Content-Disposition')
                                ?.match(/filename="(.+)"/)?.[1] || 'export.xlsx';

                            // Create a new Blob object for the data
                            const blob = new Blob([data], {
                                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                            });

                            // Create a download link
                            const link = document.createElement('a');
                            link.href = window.URL.createObjectURL(blob);
                            link.download = filename;
                            document.body.appendChild(link);

                            // Trigger the download
                            link.click();

                            // Cleanup
                            document.body.removeChild(link);
                            window.URL.revokeObjectURL(link.href);
                        },
                        error: function(xhr, status, error) {
                            console.error('Error downloading file:', error);
                            alert('Error downloading file. Please try again.');
                        }
                    });

                }


            }],
            createdRow: function(row, data, dataIndex) {
                // Apply the custom class to the row
                if (data.row_color) {
                    $(row).addClass(data.row_color);
                    if(data.row_color=="tr-client"){
                        $(row).attr("title", "book for client");
                    }else if(data.row_color=="tr-client-printed"){
                        $(row).attr("title", "roll have booked and printed");
                    }else if(data.row_color=="tr-printed"){
                        $(row).attr("title", "roll is printed");
                    }else if(data.row_color=="tr-primary-print"){
                        $(row).attr("title", "this roll will be delivering soon");
                    }else if(data.row_color=="tr-expiry-print blink"){
                        $(row).attr("title", "this roll  delivery has been expired");
                    }else if(data.row_color=="tr-argent-print"){
                        $(row).attr("title", "this roll  delivery is urgent");
                    }
                }
            },            
            initComplete: function () {
                addFilter('postsTable',[0,flag=="history"?0:$('#postsTable thead tr:nth-child(1) th').length - 1]);
            },
        });
        if (flag === "history") {
            const columnsToHide = [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21];
            columnsToHide.forEach(index => table.column(index).visible(false));
        }

        

        $("#addMenu").on("click", function() {
            $("#myForm").submit();
        });

        $('button[data-bs-target="#addMenuModel"]').on("click", () => {
            $("#myForm").get(0).reset();
        });
        $("#rollForm").validate({
            rules: rules,
            messages: {
                menu_name: {
                    required: "Please enter a menu name",
                    minlength: "Menu name must be at least 3 characters long"
                },
                order_no: {
                    required: "Please enter an order number",
                    number: "Please enter a valid number for the order"
                },
                parent_menu_mstr_id: {
                    required: "Please select a parent menu"
                },
                parent_sub_menu_mstr_id: {
                    required: "Please select a parent sub-menu"
                },
                url_path: {
                    required: "Please enter the menu path"
                },
                menu_icon: {
                    required: "Please select a menu icon"
                },
                "user_type_mstr_id[]": {
                    required: "Please select at least one user type"
                }
            },
            submitHandler: function(form) {
                // If form is valid, prevent default form submission and submit via AJAX
                addRoll();
            }
        });

        $("#printingScheduleModalForm").validate({
            rules: {
                printingScheduleDate: {
                    required: true,
                }
            },
            submitHandler: function(form) {
                printingScheduleDate();
            }
        });

        $("#printingUpdateModalForm").validate({
            rules: {
                printingUpdateDate: {
                    required: true,
                }
            },
            submitHandler: function(form) {
                printingUpdateModal();
            }
        });

        $("#cuttingScheduleModalForm").validate({
            rules: {
                cuttingScheduleDate: {
                    required: true,
                }
            },
            submitHandler: function(form) {
                cuttingScheduleDate();
            }
        });

        $("#cuttingUpdateModalForm").validate({
            rules: {
                cuttingUpdateDate: {
                    required: true,
                }
            },
            submitHandler: function(form) {
                cuttingUpdateModal();
            }
        });

        $("#rollBookingForm").validate({
            rules: {
                bookingForClientId: {
                    required: true,
                },
                bookingEstimatedDespatchDate:{
                    required:true
                },
                bookingBagUnits: {
                    required: true,
                },
                bookingBagTypeId: {
                    required: true,
                },
                bookingPrintingColor: {
                    required: true,
                },
            },
            submitHandler: function(form) {
                bookForClient();
            }
        });
        addEventListenersToForm();

    });

    function addEventListenersToForm() {
        const form = document.getElementById("rollForm");
        // Loop through all elements in the form
        Array.from(form.elements).forEach((element) => {
            // Add event listeners based on input type
            if (element.tagName === "INPUT" || element.tagName === "SELECT" || element.tagName === "TEXTAREA") {
                element.addEventListener("input", hideErrorMessage);
                element.addEventListener("change", hideErrorMessage);
            }
        });
    }

    function hideErrorMessage(event) {
        const element = event.target;
        // Find and hide the error message associated with the element
        const errorMessage = document.getElementById(`${element.id}-error`);
        if (errorMessage) {
            errorMessage.innerHTML = "";
        }
    }

    function addRoll() {
        $.ajax({
                type: "POST",
                'url': "{{route('roll.add')}}",

                "deferRender": true,
                "dataType": "json",
                'data': $("#rollForm").serialize(),
                beforeSend: function() {
                    $("#loadingDiv").show();
                },
                success: function(data) {
                    $("#loadingDiv").hide();
                    if (data.status) {
                        $("#rollForm").get(0).reset();
                        $("#rollModal").modal('hide');
                        $('#postsTable').DataTable().draw();
                        modelInfo(data.messages);
                    } else if (data?.errors) {
                        let errors = data?.errors;
                        console.log(data?.errors?.rollNo[0]);
                        modelInfo(data.messages);
                        for (field in errors) {
                            console.log(field);
                            $(`#${field}-error`).text(errors[field][0]);
                        }
                    } else {
                        modelInfo("Something Went Wrong!!");
                    }
                },
            }

        )
    }

    var loadSubMenuMstr = () => {
        subMenuLoadCount++;
        if ($('#parent_menu_mstr_id').val() != 0 && $('#parent_menu_mstr_id').val() != -1) {
            $.ajax({
                type: "get",
                url: "{{route('submenu-list')}}",
                dataType: "json",
                data: {
                    "id": $('#parent_menu_mstr_id').val(),
                },
                beforeSend: function() {
                    $("#loadingDiv").show();
                },
                success: function(data) {
                    if (data.status == true) {
                        $("#parent_sub_menu_mstr_id").html(data.data);
                        if (parent_sub_menu_mstr_id != '' && subMenuLoadCount == 1) {
                            $("#parent_sub_menu_mstr_id").val(parent_sub_menu_mstr_id);
                        }
                    } else {
                        $("#parent_sub_menu_mstr_id").html('<option value="0">#</option>');
                    }
                    $("#loadingDiv").hide();
                }
            });
        } else {
            $("#parent_sub_menu_mstr_id").val("0");
        }
    };


    function openModelBookingModel(id) {
        if (id) {
            $("#rollId").val(id);
            $("#rollBookingModal").modal("show");

        }
        return;
    }

    function openPrintingScheduleModel(id){
        $.ajax({
            type:"GET",
            url: "{{ route('roll.dtl', ':id') }}".replace(':id', id),
            dataType: "json",
            beforeSend: function() {
                $("#loadingDiv").show();
            },
            success:function(data){
                if(data.status==true) {
                    rolDtl = data.data;
                    console.log(rolDtl); 
                    $("#printingScheduleRollId").val(rolDtl?.id);
                    $("#printingScheduleDate").val(rolDtl?.schedule_date_for_print);
                    $("#roll_no_display").html(rolDtl?.roll_no);
                    $("#printingScheduleModal").modal("show");
                
                } 
                $("#loadingDiv").hide();
            },
            error:function(error){
                $("#loadingDiv").hide();
            }
        });
    }

    function printingScheduleDate(){
        $.ajax({
                type: "POST",
                'url': "{{route('roll.printing.schedule')}}",

                "deferRender": true,
                "dataType": "json",
                'data': $("#printingScheduleModalForm").serialize(),
                beforeSend: function() {
                    $("#loadingDiv").show();
                },
                success: function(data) {
                    $("#loadingDiv").hide();
                    if (data.status) {
                        $("#printingScheduleModalForm").get(0).reset();
                        $("#roll_no_display").html("");
                        $("#printingScheduleModal").modal('hide');
                        $('#postsTable').DataTable().draw();
                        modelInfo(data.messages);
                    } else if (data?.errors) {
                        let errors = data?.errors;
                        console.log(data?.errors?.rollNo[0]);
                        modelInfo(data.messages);
                        for (field in errors) {
                            console.log(field);
                            $(`#${field}-error`).text(errors[field][0]);
                        }
                    } else {
                        modelInfo("Something Went Wrong!!");
                    }
                },
            }

        ); 
    }

    function openPrintingModel(id){
        $.ajax({
            type:"GET",
            url: "{{ route('roll.dtl', ':id') }}".replace(':id', id),
            dataType: "json",
            beforeSend: function() {
                $("#loadingDiv").show();
            },
            success:function(data){
                if(data.status==true) {
                    rolDtl = data.data;
                    console.log(rolDtl); 
                    $("#printingUpdateRollId").val(rolDtl?.id);
                    $('[display_roll_no="roll_no_display"]').each(function(index, element) {
                        // Use jQuery to wrap the raw DOM element
                        $(element).html(rolDtl?.roll_no || '');
                    });
                    $("#printingUpdateModal").modal("show");
                
                } 
                $("#loadingDiv").hide();
            },
            error:function(error){
                $("#loadingDiv").hide();
            }
        });
    }

    function printingUpdateModal(){
        $.ajax({
                type: "POST",
                'url': "{{route('roll.printing.update')}}",

                "deferRender": true,
                "dataType": "json",
                'data': $("#printingUpdateModalForm").serialize(),
                beforeSend: function() {
                    $("#loadingDiv").show();
                },
                success: function(data) {
                    $("#loadingDiv").hide();
                    if (data.status) {
                        $("#printingUpdateModalForm").get(0).reset();
                        $('[display_roll_no="roll_no_display"]').each(function(index, element) {
                            // Use jQuery to wrap the raw DOM element
                            $(element).html('');
                        });
                        $("#printingUpdateModal").modal('hide');
                        $('#postsTable').DataTable().draw();
                        modelInfo(data.messages);
                    } else if (data?.errors) {
                        let errors = data?.errors;
                        console.log(data?.errors?.rollNo[0]);
                        modelInfo(data.messages);
                        for (field in errors) {
                            console.log(field);
                            $(`#${field}-error`).text(errors[field][0]);
                        }
                    } else {
                        modelInfo("Something Went Wrong!!");
                    }
                },
            }

        );
    }

    function openCuttingScheduleModel(id){
        $.ajax({
            type:"GET",
            url: "{{ route('roll.dtl', ':id') }}".replace(':id', id),
            dataType: "json",
            beforeSend: function() {
                $("#loadingDiv").show();
            },
            success:function(data){
                if(data.status==true) {
                    rolDtl = data.data;
                    console.log(rolDtl); 
                    $("#cuttingScheduleRollId").val(rolDtl?.id);
                    $("#cuttingScheduleDate").val(rolDtl?.schedule_date_for_cutting);                    
                    $('[display_roll_no="roll_no_display"]').each(function(index, element) {
                            // Use jQuery to wrap the raw DOM element
                            $(element).html(rolDtl?.roll_no);
                        });
                    $("#cuttingScheduleModal").modal("show");
                
                } 
                $("#loadingDiv").hide();
            },
            error:function(error){
                $("#loadingDiv").hide();
            }
        });
    }

    function cuttingScheduleDate(){
        $.ajax({
                type: "POST",
                'url': "{{route('roll.cutting.schedule')}}",

                "deferRender": true,
                "dataType": "json",
                'data': $("#cuttingScheduleModalForm").serialize(),
                beforeSend: function() {
                    $("#loadingDiv").show();
                },
                success: function(data) {
                    $("#loadingDiv").hide();
                    if (data.status) {
                        $("#cuttingScheduleModalForm").get(0).reset();
                        $('[display_roll_no="roll_no_display"]').each(function(index, element) {
                            // Use jQuery to wrap the raw DOM element
                            $(element).html('');
                        });
                        $("#cuttingScheduleModal").modal('hide');
                        $('#postsTable').DataTable().draw();
                        modelInfo(data.messages);
                    } else if (data?.errors) {
                        let errors = data?.errors;
                        console.log(data?.errors?.rollNo[0]);
                        modelInfo(data.messages);
                        for (field in errors) {
                            console.log(field);
                            $(`#${field}-error`).text(errors[field][0]);
                        }
                    } else {
                        modelInfo("Something Went Wrong!!");
                    }
                },
            }

        ); 
    }

    function openCuttingModel(id){
        $.ajax({
            type:"GET",
            url: "{{ route('roll.dtl', ':id') }}".replace(':id', id),
            dataType: "json",
            beforeSend: function() {
                $("#loadingDiv").show();
            },
            success:function(data){
                if(data.status==true) {
                    rolDtl = data.data;
                    console.log(rolDtl); 
                    $("#cuttingUpdateRollId").val(rolDtl?.id);
                    $('[display_roll_no="roll_no_display"]').each(function(index, element) {
                        // Use jQuery to wrap the raw DOM element
                        $(element).html(rolDtl?.roll_no || '');
                    });
                    $("#cuttingUpdateModal").modal("show");
                
                } 
                $("#loadingDiv").hide();
            },
            error:function(error){
                $("#loadingDiv").hide();
            }
        });
    }

    function cuttingUpdateModal(){
        $.ajax({
                type: "POST",
                'url': "{{route('roll.cutting.update')}}",

                "deferRender": true,
                "dataType": "json",
                'data': $("#cuttingUpdateModalForm").serialize(),
                beforeSend: function() {
                    $("#loadingDiv").show();
                },
                success: function(data) {
                    $("#loadingDiv").hide();
                    if (data.status) {
                        $("#printingUpdateModalForm").get(0).reset();
                        $('[display_roll_no="roll_no_display"]').each(function(index, element) {
                            // Use jQuery to wrap the raw DOM element
                            $(element).html('');
                        });
                        $("#cuttingUpdateModal").modal('hide');
                        $('#postsTable').DataTable().draw();
                        modelInfo(data.messages);
                    } else if (data?.errors) {
                        let errors = data?.errors;
                        console.log(data?.errors?.rollNo[0]);
                        modelInfo(data.messages);
                        for (field in errors) {
                            console.log(field);
                            $(`#${field}-error`).text(errors[field][0]);
                        }
                    } else {
                        modelInfo("Something Went Wrong!!");
                    }
                },
            }

        );
    }

    function bookForClient() {
        $.ajax({
                type: "POST",
                'url': "{{route('roll.book')}}",

                "deferRender": true,
                "dataType": "json",
                'data': $("#rollBookingForm").serialize(),
                beforeSend: function() {
                    $("#loadingDiv").show();
                },
                success: function(data) {
                    $("#loadingDiv").hide();
                    if (data.status) {
                        $("#rollBookingForm").get(0).reset();
                        $("#rollBookingModal").modal('hide');
                        $('#postsTable').DataTable().ajax.reload();
                        modelInfo(data.messages);
                    } else if (data?.errors) {
                        let errors = data?.errors;
                        console.log(data?.errors?.rollNo[0]);
                        modelInfo(data.messages);
                        for (field in errors) {
                            console.log(field);
                            $(`#${field}-error`).text(errors[field][0]);
                        }
                    } else {
                        modelInfo("Something Went Wrong!!");
                    }
                },
            }

        )
    }
    function searchData(){
        $('#postsTable').DataTable().ajax.reload();
    }
    

</script>
@include("layout.footer")