<table id="purchase_order-item-table" class="table display dataTable text-right strong table-responsive"; style="width: 100px;">
    <tr>
        <td style="width: 85px;"><?php echo lang("sub_total"); ?></td>
        <!--td style="width: 96px;"><?php echo to_currency($purchase_order_total_summary->estimate_subtotal, $purchase_order_total_summary->currency_symbol); ?></td-->
        <td style="width: 96px;"><?php echo to_currency($purchase_order_total_summary->estimate_quantity_subtotal, $purchase_order_total_summary->currency_symbol); ?></td>
        <td style="width: 80px;"><?php echo to_currency($purchase_order_total_summary->estimate_tax_subtotal, $purchase_order_total_summary->currency_symbol); ?> </td>
        <!--td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->estimate_net_subtotal, $purchase_order_total_summary->currency_symbol); ?></td>
        <td style="width: 30px;"></td-->
        <td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->estimate_subtotal, $purchase_order_total_summary->currency_symbol); ?></td>
        <td style="width: 30px;"></td>

    </tr>

   <?php
   $optionss = array("id" =>$purchase_order_info->id);
$modifed_data = $this->Purchase_orders_model->get_details($optionss)->row();
   $options = array("purchase_order_id" =>$purchase_order_info->id);
$list_data = $this->Vendors_invoice_list_model->get_details($options)->result();
if($list_data && $modifed_data->modified == '0'){
$freight_row = "<tr>
                        <td></td>
                        <td></td>
                        <td style='padding-top:13px;'>" . lang("freight") . "</td>
                        <td style='padding-top:13px;'>" . to_currency($purchase_order_total_summary->freight_rate_amount, $purchase_order_total_summary->currency_symbol) . "</td>
                         <td class='text-center option w10p'><span class='p20'>&nbsp;&nbsp;&nbsp;</span></td>
                             </tr>";

   
        //when there is discount and type is before tax or no discount
        echo $freight_row;
}else{
    $freight_row = "<tr>
                        <td></td>
                        <td></td>
                        <td style='padding-top:13px;'>" . lang("freight") . "</td>
                        <td style='padding-top:13px;'>" . to_currency($purchase_order_total_summary->freight_rate_amount, $purchase_order_total_summary->currency_symbol) . "</td>
                        <td class='text-center option w10p'>" . modal_anchor(get_uri("purchase_orders/freight_modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "data-post-purchase_order_id" => $purchase_order_info->id, "title" => lang('edit_freight'))) . "<span class='p20'>&nbsp;&nbsp;&nbsp;</span></td>
                    </tr>";

   
        //when there is discount and type is before tax or no discount
        echo $freight_row;
    }
        ?>
   <?php /*
    $freight_row = "<tr>
                        <td></td>
                        <td></td>
                        <td style='padding-top:13px;'>" . lang("freight") . "</td>
                        <td style='padding-top:13px;'>" . to_currency($purchase_order_total_summary->freight_rate_amount, $purchase_order_total_summary->currency_symbol) . "</td>
                        <td class='text-center option w10p'>" . modal_anchor(get_uri("purchase_orders/freight_modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "data-post-purchase_order_id" => $purchase_order_info->id, "title" => lang('edit_freight'))) . "<span class='p20'>&nbsp;&nbsp;&nbsp;</span></td>
                    </tr>";

   
        //when there is discount and type is before tax or no discount
        echo $freight_row;
    
    */?> 

   <!-- <tr>
        <td style="width: 85px;"><?php echo lang("igst_output"); ?></td>
        <td style="width: 96px;"><?php echo to_currency($invoice_total_summary->freight_tax, $invoice_total_summary->currency_symbol); ?></td>-->
   
     <!--tr>
        <td style="width: 85px;"></td>
        <td style="width: 96px;"></td>
        <td style="width: 80px;"><?php echo lang("igst_output"); ?> </td>
        <td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->igst_total, $purchase_order_total_summary->currency_symbol); ?></td>
         <td style="width: 36px;"></td>

    </tr-->
<?php 
$company_setup_country = get_setting("company_setup_country");
if($company_setup_country ==$vendor_info->country) {?>
<?php   if (!empty($vendor_info->gstin_number_first_two_digits)) { ?>
    
    <?php 
$company_gstin_number_first_two_digits= get_setting("company_gstin_number_first_two_digits");
if($company_gstin_number_first_two_digits !==$vendor_info->gstin_number_first_two_digits) {?>
        <tr>
        <td style="width: 85px;"></td>
        <td style="width: 96px;"></td>
        <td style="width: 80px;"><?php echo lang("igst_output"); ?> </td>
        <td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->igst_total+$purchase_order_total_summary->freight_tax_amount, $purchase_order_total_summary->currency_symbol); ?></td>
         <td style="width: 36px;"></td>

    </tr>
    <?php } ?>
    <?php }  else if (empty($client_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_state = get_setting("company_state");
if($company_state !==$vendor_info->state) {?>
<tr>
        <td style="width: 85px;"></td>
        <td style="width: 96px;"></td>
        <td style="width: 80px;"><?php echo lang("igst_output"); ?> </td>
        <td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->igst_total+$purchase_order_total_summary->freight_tax_amount, $purchase_order_total_summary->currency_symbol); ?></td>
         <td style="width: 36px;"></td>

    </tr>
  <?php } ?>
  <?php } ?>

<?php   if (!empty($vendor_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_gstin_number_first_two_digits= get_setting("company_gstin_number_first_two_digits");
if($company_gstin_number_first_two_digits ==$vendor_info->gstin_number_first_two_digits) {?>
        <tr>
        <td style="width: 85px;"></td>
        <td style="width: 96px;"></td>
        <td style="width: 80px;"><?php echo lang("cgst_output"); ?> </td>
        <td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->igst_total/2+$purchase_order_total_summary->freight_tax_amount/2, $purchase_order_total_summary->currency_symbol); ?></td>
         <td style="width: 36px;"></td>

    </tr>
        <tr>
        <td style="width: 85px;"></td>
        <td style="width: 96px;"></td>
        <td style="width: 80px;"><?php echo lang("sgst_output"); ?> </td>
        <td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->igst_total/2+$purchase_order_total_summary->freight_tax_amount/2, $purchase_order_total_summary->currency_symbol); ?></td>
         <td style="width: 36px;"></td>

    </tr>
    <?php } ?>
    <?php } else if (empty($vendor_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_state = get_setting("company_state");
if($company_state ==$vendor_info->state) {?>
   <tr>
        <td style="width: 85px;"></td>
        <td style="width: 96px;"></td>
        <td style="width: 80px;"><?php echo lang("cgst_output"); ?> </td>
        <td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->igst_total/2+$purchase_order_total_summary->freight_tax_amount/2, $purchase_order_total_summary->currency_symbol); ?></td>
         <td style="width: 36px;"></td>

    </tr>
        <tr>
        <td style="width: 85px;"></td>
        <td style="width: 96px;"></td>
        <td style="width: 80px;"><?php echo lang("sgst_output"); ?> </td>
        <td style="width: 36px;"><?php echo to_currency($purchase_order_total_summary->igst_total/2+$purchase_order_total_summary->freight_tax_amount/2, $purchase_order_total_summary->currency_symbol); ?></td>
         <td style="width: 36px;"></td>

    </tr>
<?php } ?>
   <?php } ?>
   <?php } ?>




    <tr>
            <td></td>
            <td></td>
            <td><?php echo lang("total"); ?></td>
            <td><?php echo to_currency($purchase_order_total_summary->estimate_net_subtotal_default, $purchase_order_total_summary->currency_symbol); ?></td>
             <td></td>
        </tr>
        <tr>
        <td></td>
        <td></td>
        <td><?php echo lang("round_off"); ?></td>
        <td><?php $c= to_currency($purchase_order_total_summary->estimate_net_subtotal_default); $d=substr($c,-2); if($d>=50){
            $e=(100-$d);
            echo "(+)0.".$e;
            }elseif($d<50){
                echo "(-)0.".$d;
                } ?></td>
        <td></td> 
    </tr>
    <?php if ($purchase_order_total_summary->total_paid) { ?>
        <tr>
            <td></td>
            <td></td>
            <td><?php echo lang("paid"); ?></td>
            <td><?php echo to_currency($purchase_order_total_summary->total_paid, $purchase_order_total_summary->currency_symbol); ?></td>
            <td></td>
        </tr>
    <?php } ?>
   <tr>
       <td></td>
       <td></td>
       <td><?php echo lang("balance_due"); ?></td>
    <td><?php echo to_currency($purchase_order_total_summary->balance_due, $purchase_order_total_summary->currency_symbol); ?></td>
        <td></td>
    </tr>
    
    </tr>
</table>