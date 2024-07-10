<table id="work_order-item-table" class="table display dataTable text-right strong table-responsive" style="width: 100px;">
    <tr>
        <td style="width: 85px;"><?= lang("sub_total"); ?></td>
        <!--td style="width: 96px;"><?= to_currency($work_order_total_summary->estimate_subtotal, $work_order_total_summary->currency_symbol); ?></td-->
        <td style="width: 96px;"><?= to_currency($work_order_total_summary->estimate_quantity_subtotal, $work_order_total_summary->currency_symbol); ?></td>
        <td style="width: 80px;"><?= to_currency($work_order_total_summary->estimate_tax_subtotal, $work_order_total_summary->currency_symbol); ?></td>
        <td style="width: 36px;"><?= to_currency($work_order_total_summary->estimate_subtotal, $work_order_total_summary->currency_symbol); ?></td>
        <td style="width: 30px;"></td>
        <!--td style="width: 36px;"><?= to_currency($work_order_total_summary->estimate_net_subtotal, $work_order_total_summary->currency_symbol); ?></td>
        <td style="width: 30px;"></td-->
    </tr>

    <?php
    $freight_row = "<tr>
                        <td></td>
                        <td></td>
                        <td style='padding-top:13px;'>" . lang("freight") . "</td>
                        <td style='padding-top:13px;'>" . to_currency($work_order_total_summary->freight_rate_amount, $work_order_total_summary->currency_symbol) . "</td>
                        <td class='text-center option w10p'>" . modal_anchor(get_uri("work_orders/freight_modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "data-post-work_order_id" => $work_order_info->id, "title" => lang('edit_freight')]) . "<span class='p20'>&nbsp;&nbsp;&nbsp;</span></td>
                    </tr>";

    echo $freight_row;
    ?>

    <!-- <tr>
        <td style="width: 85px;"><?= lang("igst_output"); ?></td>
        <td style="width: 96px;"><?= to_currency($invoice_total_summary->freight_tax, $invoice_total_summary->currency_symbol); ?></td>-->
   
     <!--tr>
        <td style="width: 85px;"></td>
        <td style="width: 96px;"></td>
        <td style="width: 80px;"><?= lang("igst_output"); ?> </td>
        <td style="width: 36px;"><?= to_currency($work_order_total_summary->igst_total, $work_order_total_summary->currency_symbol); ?></td>
        <td style="width: 36px;"></td>
    </tr-->

    <?php 
    $company_setup_country = get_setting("company_setup_country");
    if ($company_setup_country == $vendor_info->country) {
        if (!empty($vendor_info->gstin_number_first_two_digits)) {
            $company_gstin_number_first_two_digits = get_setting("company_gstin_number_first_two_digits");
            if ($company_gstin_number_first_two_digits !== $vendor_info->gstin_number_first_two_digits) { ?>
                <tr>
                    <td style="width: 85px;"></td>
                    <td style="width: 96px;"></td>
                    <td style="width: 80px;"><?= lang("igst_output"); ?></td>
                    <td style="width: 36px;"><?= to_currency($work_order_total_summary->igst_total + $work_order_total_summary->freight_tax_amount, $work_order_total_summary->currency_symbol); ?></td>
                    <td style="width: 36px;"></td>
                </tr>
            <?php } 
        } else if (empty($client_info->gstin_number_first_two_digits)) {
            $company_state = get_setting("company_state");
            if ($company_state !== $vendor_info->state) { ?>
                <tr>
                    <td style="width: 85px;"></td>
                    <td style="width: 96px;"></td>
                    <td style="width: 80px;"><?= lang("igst_output"); ?></td>
                    <td style="width: 36px;"><?= to_currency($work_order_total_summary->igst_total + $work_order_total_summary->freight_tax_amount, $work_order_total_summary->currency_symbol); ?></td>
                    <td style="width: 36px;"></td>
                </tr>
            <?php } 
        }

        if (!empty($vendor_info->gstin_number_first_two_digits)) {
            $company_gstin_number_first_two_digits = get_setting("company_gstin_number_first_two_digits");
            if ($company_gstin_number_first_two_digits == $vendor_info->gstin_number_first_two_digits) { ?>
                <tr>
                    <td style="width: 85px;"></td>
                    <td style="width: 96px;"></td>
                    <td style="width: 80px;"><?= lang("cgst_output"); ?></td>
                    <td style="width: 36px;"><?= to_currency($work_order_total_summary->igst_total / 2 + $work_order_total_summary->freight_tax_amount / 2, $work_order_total_summary->currency_symbol); ?></td>
                    <td style="width: 36px;"></td>
                </tr>
                <tr>
                    <td style="width: 85px;"></td>
                    <td style="width: 96px;"></td>
                    <td style="width: 80px;"><?= lang("sgst_output"); ?></td>
                    <td style="width: 36px;"><?= to_currency($work_order_total_summary->igst_total / 2 + $work_order_total_summary->freight_tax_amount / 2, $work_order_total_summary->currency_symbol); ?></td>
                    <td style="width: 36px;"></td>
                </tr>
            <?php } 
        } else if (empty($vendor_info->gstin_number_first_two_digits)) {
            $company_state = get_setting("company_state");
            if ($company_state == $vendor_info->state) { ?>
                <tr>
                    <td style="width: 85px;"></td>
                    <td style="width: 96px;"></td>
                    <td style="width: 80px;"><?= lang("cgst_output"); ?></td>
                    <td style="width: 36px;"><?= to_currency($work_order_total_summary->igst_total / 2 + $work_order_total_summary->freight_tax_amount / 2, $work_order_total_summary->currency_symbol); ?></td>
                    <td style="width: 36px;"></td>
                </tr>
                <tr>
                    <td style="width: 85px;"></td>
                    <td style="width: 96px;"></td>
                    <td style="width: 80px;"><?= lang("sgst_output"); ?></td>
                    <td style="width: 36px;"><?= to_currency($work_order_total_summary->igst_total / 2 + $work_order_total_summary->freight_tax_amount / 2, $work_order_total_summary->currency_symbol); ?></td>
                    <td style="width: 36px;"></td>
                </tr>
            <?php } 
        }
    } ?>

    <tr>
        <td></td>
        <td></td>
        <td><?= lang("total"); ?></td>
        <td><?= to_currency($work_order_total_summary->estimate_net_subtotal_default, $work_order_total_summary->currency_symbol); ?></td>
        <td></td>
    </tr>
    <tr>
        <td></td>
        <td></td>
        <td><?= lang("round_off"); ?></td>
        <td><?php $c = to_currency($work_order_total_summary->estimate_net_subtotal_default); $d = substr($c, -2); if ($d >= 50) {
            $e = (100 - $d);
            echo "(+)0." . $e;
        } elseif ($d < 50) {
            echo "(-)0." . $d;
        } ?></td>
        <td></td> 
    </tr>
    <?php if ($work_order_total_summary->total_paid) { ?>
        <tr>
            <td></td>
            <td></td>
            <td><?= lang("paid"); ?></td>
            <td><?= to_currency($work_order_total_summary->total_paid, $work_order_total_summary->currency_symbol); ?></td>
            <td></td>
        </tr>
    <?php } ?>
    <tr>
        <td></td>
        <td></td>
        <td><?= lang("balance_due"); ?></td>
        <td><?= to_currency($work_order_total_summary->balance_due, $work_order_total_summary->currency_symbol); ?></td>
        <td></td>
    </tr>
</table>
