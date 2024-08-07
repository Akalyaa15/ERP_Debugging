<div class="panel">
    <div class="tab-title clearfix">
        <h4><?php echo lang('payments'); ?></h4>
    </div>

    <div class="table-responsive">
        <table id="invoice-payment-table" class="display" width="100%">
        </table>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var currencySymbol = "<?php echo $project_info->currency_symbol; ?>";
        $("#invoice-payment-table").appTable({
            source: '<?php echo_uri("invoice_payments/payment_list_data_of_project/" . $project_id) ?>',
            order: [[0, "asc"]],
            columns: [
                {title: '<?php echo lang("invoice_no") ?> ', "class": "w10p"},
                {title: "<?php echo lang("client") ?>", "class": "w15p"},
                {title: "<?php echo lang("project") ?>", "class": "w15p"},
                {visible: false, searchable: false},
                {title: '<?php echo lang("payment_date") ?> ', "class": "w15p",  "iDataSort": 1},

                {title: '<?php echo lang("payment_method") ?>', "class": "w15p"},
                 {title: '<?php echo lang("reference_number") ?>', "class": "w15p"},
                {title: '<?php echo lang("amount") ?>', "class": "text-right w15p"},
                 {title: '<?php echo lang("files") ?>', "class": "w10p"},
                {title: '<?php echo lang("description") ?>'}
            ],
            printColumns: [0, 1, 2, 3, 4],
            xlsColumns: [0, 1, 2, 3, 4],
            summation: [{column: 7, dataType: 'currency', currencySymbol: currencySymbol}]
        });

    });
</script>