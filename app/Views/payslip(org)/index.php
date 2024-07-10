<div id="page-content" class="clearfix p20">
    <div class="panel clearfix">
        <ul data-toggle="ajax-tab" class="nav nav-tabs bg-white title" role="tablist">
            <li class="title-tab"><h4 class="pl15 pt10 pr15"><?php echo lang("payslip"); ?></h4></li>
            <li><a id="monthly-payslip-button"  role="presentation" class="active" href="javascript:;" data-target="#monthly-payslip"><?php echo lang("monthly"); ?></a></li>
            <li><a role="presentation" href="<?php echo_uri("payslip/yearly/"); ?>" data-target="#yearly-payslip"><?php echo lang('yearly'); ?></a></li>
            
            <div class="tab-title clearfix no-border">
                <div class="title-button-group">
                    <?php

                if ($this->login_user->is_admin) { 
                    echo modal_anchor(get_uri("payslip/modal_form"), "<i class='fa fa-plus-circle'></i> " . lang('add_payslip'), array("class" => "btn btn-default mb0", "title" => lang('add_payslip'))); }?>
                </div>
            </div>
        </ul>

        <div class="tab-content">
            <div role="tabpanel" class="tab-pane fade" id="monthly-payslip">
                <div class="table-responsive">
                    <table id="monthly-payslip-table" class="display" cellspacing="0" width="100%">
                    </table>
                </div>
                </div>
                 <div role="tabpanel" class="tab-pane fade" id="yearly-payslip"></div>
            
        </div>
    </div>
</div>

<script type="text/javascript">
    loadPayslipTable = function (selector, dateRange) {
        var customDatePicker = "";
        if (dateRange === "custom") {
            customDatePicker = [{startDate: {name: "start_date", value: moment().format("YYYY-MM-DD")}, endDate: {name: "end_date", value: moment().format("YYYY-MM-DD")}, showClearButton: true}];
            dateRange = "";
        }

       $(selector).appTable({
            source: '<?php echo_uri("payslip/list_data") ?>',
            dateRangeType: dateRange,
            filterDropdown: [
                
                {name: "user_id", class: "w200", options: <?php echo $members_dropdown; ?>}
                
            ],
            order: [[0, "asc"]],
            rangeDatepicker: customDatePicker,
            columns: [
                {title: '<?php echo lang("employee_payslip_id") ?>'},
                {title: '<?php echo lang("payslip_date") ?>'},
              
                {title: '<?php echo lang("team_members") ?>'},
                {title: '<?php echo lang("earnings_amount") ?>', "class": "w10p text-right"},
                //{title: '<?php echo lang("deductions_amount") ?>', "class": "w10p text-right"},
                {title: '<?php echo lang("total_deductions") ?>', "class": "w10p text-right"},
                {title: '<?php echo lang("over_time_amount") ?>', "class": "w10p text-right"},
                 {title: '<?php echo lang("netsalary") ?>', "class": "w10p text-right"},
               // {title: '<?php echo lang("total") ?>', "class": "w10p text-right"},
                {title: '<i class="fa fa-bars"></i>', "class": "text-center option w100"}
               ],
            printColumns: [1, 2, 3, 4, 5, 6],
            xlsColumns: [1, 2, 3, 4, 5, 6],
         //  summation: [{column: 3, dataType: 'currency'},{column: 4, dataType: 'currency'}]
          summation: [{column: 3, dataType: 'currency', currencySymbol: AppHelper.settings.currencySymbol}, {column: 4, dataType: 'currency', currencySymbol: AppHelper.settings.currencySymbol},{column: 5, dataType: 'currency', currencySymbol: AppHelper.settings.currencySymbol},{column: 6, dataType: 'currency', currencySymbol: AppHelper.settings.currencySymbol}]
        });
    };

    $(document).ready(function () {
        $("#monthly-payslip-button").trigger("click");
        loadPayslipTable("#monthly-payslip-table", "monthly");
    });
</script>


