@include("layout.header")
<main class="p-3">
    <div class="container-fluid">
        <div class="mb-3 text-left">
            <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                <ol class="breadcrumb fs-6">
                    <li class="breadcrumb-item fs-6"><a href="#">Bag</a></li>
                    <li class="breadcrumb-item active fs-6" aria-current="page">Godown</li>
                </ol>
            </nav>

        </div>
    </div>
    <div class="container">
        <div class="panel-heading">
            <h5 class="panel-title">List</h5>            
        </div>
        <div class="panel-body">            
            <table class="table table-bordered  table-responsive " id="postsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Packing Date</th>
                        <th>Packing No</th>
                        <th>Client Name</th>
                        <th>Bag Type</th>
                        <th>Bag Unit</th> 
                        <th>Bag Color</th>                      
                        <th>Bag Weight</th>
                        <th>Bag Pice</th>
                        <th>Bag L</th>
                        <th>Bag W</th>
                        <th>Bag G</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
</main>
<script>
    $(document).ready(function(){
        const table = $('#postsTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{route('packing.godown')}}",// The route where you're getting data from
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
                { data: "packing_date", name: "packing_date" },
                { data: "packing_no", name: "packing_no" },
                { data: "client_name", name: "client_name" },
                { data: "bag_type", name: "bag_type" },
                { data: "bag_unit", name: "bag_unit" },
                { data: "bag_color", name: "bag_color" },
                { data: "packing_weight", name: "packing_weight" },
                { data: "packing_bag_pieces", name: "packing_bag_pieces" },
                { data: "l", name: "l" },
                { data: "w", name: "w" },
                { data: "g", name: "g" },
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
                extend: 'csv',
                text: 'Export to Excel',
                className: 'btn btn-success',

            }],            
            initComplete: function () {
                addFilter('postsTable',[0,$('#postsTable thead tr:nth-child(1) th').length - 1]);
            },
        });
    });
</script>
@include("layout.footer")