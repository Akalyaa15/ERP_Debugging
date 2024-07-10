<div id="page-content" class="p20 clearfix">
    <div class="panel panel-default">
            <a class="btn btn-primary" href="javascript:window.history.go(-1);">❮ Go Back</a>
    <div class="page-title clearfix">
            <h1><?php echo lang('companys'); ?></h1>
            <div class="title-button-group">
                <?php echo modal_anchor(get_uri("companys/modal_form"), "<i class='fa fa-plus-circle'></i> " . lang('add_company'), array("class" => "btn btn-default", "title" => lang('add_company'))); ?>
            </div>
            <div class="title-button-group">
            <!-- <a href= "<?php /*echo base_url('assets/template/companys_Template.xlsx');*/ ?>"  download> 
            <button type="button" class="btn btn-default"><i class='fa fa-download'></i> Download Template </button> -->
  <!-- <img src="<?php echo base_url('assets/images/gem.ico'); ?>" alt="W3Schools" width="50" height="50"> -->
</a>
</div>
<div class="title-button-group">
                <!-- <?php /* echo modal_anchor(get_uri("companys/companys_excel_form"), "<i class='fa fa-upload' aria-hidden='true'></i> " . lang('import'), array("class" => "btn btn-default", "title" => lang('import'))); */ ?> -->
            </div>
        </div>
        <div class="table-responsive">
            <table id="company-table" class="display" cellspacing="0" width="100%">            
            </table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        var showInvoiceInfo = true;
        if (!"<?php echo $show_invoice_info; ?>") {
            showInvoiceInfo = false;
        }

        $("#company-table").appTable({
            source: '<?php echo_uri("companys/list_data") ?>',
            filterDropdown: [
                {name: "group_id", class: "w200", options: <?php echo $groups_dropdown; ?>}
            ],
            columns: [
                {title: "<?php echo lang("id") ?>", "class": "text-center w50"},
                {title: "<?php echo lang("company_id") ?>", "class": "text-center w50"},
                {title: "<?php echo lang("company_name") ?>"},
                {title: "<?php echo lang("primary_contact") ?>"},
                {title: "<?php echo lang("company_groups") ?>"}
                /*{visible: showInvoiceInfo, searchable: showInvoiceInfo,title: "<?php echo lang("projects") ?>"},                
                {visible: showInvoiceInfo, searchable: showInvoiceInfo, title: "<?php echo lang("invoice_value") ?>"},
                {visible: showInvoiceInfo, searchable: showInvoiceInfo, title: "<?php echo lang("payment_received") ?>"},
                {visible: showInvoiceInfo, searchable: showInvoiceInfo, title: "<?php echo lang("due") ?>"}*/
                <?php echo $custom_field_headers; ?>,
                {title: '<i class="fa fa-bars"></i>', "class": "text-center option w100"}
            ],
            printColumns: combineCustomFieldsColumns([0, 1, 2, 3, 4, 5, 6], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([0, 1, 2, 3, 4, 5, 6], '<?php echo $custom_field_headers; ?>')
        });
    });
</script>