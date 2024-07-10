 
    <?php
    $color = get_setting("work_order_color");
    if (!$color) {
        $color = "#2AA384";
    }
    $style = get_setting("work_order_style");
    ?>
    <?php
    $data = array(
        "vendor_info" => $vendor_info,
        "color" => $color,
        "work_order_info" => $work_order_info
    );
    if ($style === "style_2") {
        $this->load->view('work_orders/work_order_parts/header_style_2.php', $data);
    } else {
        $this->load->view('work_orders/work_order_parts/header_style_1.php', $data);
    }
    ?>


<br />
<br>

<?php 
$DB4 = $this->load->database('default', TRUE);
$DB4->select ("discount_percentage");
 $DB4->from('work_order_items');
 $DB4->where('work_order_items.work_order_id',$work_order_info->id);
  $DB4->where('work_order_items.discount_percentage!=','0');
 $DB4->where('work_order_items.deleted','0');
 
$query=$DB4->get();
$s=$query->result();
$k= sizeof($s);
?>
<?php 
$DB11 = $this->load->database('default', TRUE);
$DB11->select ("hsn_code,hsn_description,gst");
 $DB11->from('work_order_items');
 $DB11->where('work_order_items.work_order_id',$work_order_info->id);
  $DB11->where('work_order_items.gst!=','0');
 $DB11->where('work_order_items.deleted','0');
 
$queryhsns=$DB11->get();
$hsngsts=$queryhsns->result();
$hsn_sizes= sizeof($hsngsts);
if($hsn_sizes>0) {?>

<table style="width: 100%; color: #444;">            
    <tr style="font-weight: bold; background-color: <?php echo $color; ?>; color: #fff;  ">
    <th style="width: 7%; text-align: center; border-right: 1px solid #eee;"> <?php echo lang("s.no"); ?> </th>
        <th style="width: 20%; border-right: 1px solid #eee;"> <?php echo lang("job_name"); ?> </th>
        <th style="text-align: left;  width: 14%; border-right: 1px solid #eee;"> <?php echo lang("category"); ?></th>
        <!--th style="text-align: center;  width: 9%; border-right: 1px solid #eee;"> <?php echo lang("make"); ?></th-->
    <th style="text-align: center;  width: 10%; border-right: 1px solid #eee;"> <?php echo lang("hsn_code"); ?></th>
    <th style="text-align: center;  width: 8%; border-right: 1px solid #eee;"> <?php echo lang("gst"); ?></th>
 <th style="text-align: center;  width: 12%; border-right: 1px solid #eee;font-size:14px;"> <?php echo ("Qty"); ?></th>
        
        <th style="text-align: center;  width: 13%; border-right: 1px solid #eee;"> <?php echo lang("rate"); ?></th>
        <th style="text-align: right;  width: 15%; border-right: 1px solid #eee;"> <?php echo lang("total"); ?></th>
        
        <!--th style="text-align: right;  width: 13%; border-right: 1px solid #eee;"> <?php echo lang("tax_amount"); ?></th-->
       <?php /* if($k>0){?> <th style="text-align: center;  width: 5%; border-right: 1px solid #eee;"> <?php echo ("Disc"); ?></th> <?php } */?>
        <!--th style="text-align: right;  width: 15%; border-right: 1px solid #eee;"> <?php echo lang("net_total"); ?></th-->
        
        
    </tr>
    <?php
    $counter = 0;
    foreach ($work_order_items as $item) {
        ?>
        <tr style="background-color: #f4f4f4; ">
        <td style="width: 7%; border: 1px solid #fff; padding: 10px;"><?php echo ++$counter;  ?>
                </td>
            <td style="width: 20%; border: 1px solid #fff; padding: 10px;"><?php echo $item->title; ?>
                <br />
                <span style="color: #888; font-size: 90%;"><?php echo nl2br($item->description); ?></span>
            </td>
            <td style="width: 14%; border: 1px solid #fff; padding: 10px;max-width: 10px;word-wrap: break-word;"><?php echo $item->category; ?>
                </td>
            <!--td style="width: 9%; border: 1px solid #fff; padding: 10px;max-width: 10px;word-wrap: break-word;"><?php echo $item->make; ?>
                </td-->
            <td style="width: 10%; border: 1px solid #fff; padding: 10px;max-width: 10px;word-wrap: break-word;"><?php echo $item->hsn_code; ?>
                </td>
                 <td style="width: 8%; border: 1px solid #fff; padding: 10px;"><?php echo $item->gst.'%'; ?>
                </td>
            
            <td style="text-align: center; width: 12%; border: 1px solid #fff;"> <?php echo $item->quantity . " " . $item->unit_type; ?></td>
            
            
            <td style="text-align: right; width: 13%; border: 1px solid #fff;max-width:85px;word-wrap: break-word;"> <?php echo to_currency($item->total/$item->quantity, $item->currency_symbol); ?></td>
            <td style="text-align: right; width: 15%; border: 1px solid #fff;max-width:90px;word-wrap: break-word;padding: 10px;"> <?php echo to_currency($item->total, $item->currency_symbol); ?></td>
           
            <!--td style="text-align: right; width: 13%; border: 1px solid #fff;max-width: 80px;word-wrap: break-word;"> <?php echo to_currency($item->tax_amount, $item->currency_symbol); ?></td-->
            <?php /* if($k>0){?><td style="width: 5%; border: 1px solid #fff; padding: 10px;"><?php echo $item->discount_percentage.'%'; ?>
                </td><?php } */?>
        <!--td style="text-align: right; width: 15%; border: 1px solid #fff;max-width:100px;word-wrap: break-word;"> <?php echo to_currency($item->net_total, $item->currency_symbol); ?></td-->
        </tr>
    <?php } ?>
    <tr>
        <td colspan="7" style="text-align: right;color:#181919;"><?php echo lang("sub_total"); ?></td>
        <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->estimate_subtotal, $work_order_total_summary->currency_symbol); ?>
        </td>
        <td colspan="1" ></td>
        <!--td style="text-align:right; width: 13%; border: 1px solid #fff; background-color: #f4f4f4;max-width:80px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->estimate_tax_subtotal, $work_order_total_summary->currency_symbol); ?>
        </td-->
       <?php /* if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */ ?>
        <!--td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:100px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->estimate_net_subtotal, $work_order_total_summary->currency_symbol); ?>
        </td-->
        
    </tr>
   

    <?php if($work_order_total_summary->freight_amount){?>
     <tr>
        <td colspan="7" style="text-align: right;color:#181919;"><?php echo lang("freight"); ?></td>
        <?php /* if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */ ?>
        <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->freight_tax2, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
    <?php } ?>
    <?php 
$company_setup_country = get_setting("company_setup_country");
if($company_setup_country ==$vendor_info->country) {?>
<?php   if (!empty($vendor_info->gstin_number_first_two_digits)) { ?>
    
    <?php 
$company_gstin_number_first_two_digits= get_setting("company_gstin_number_first_two_digits");
if($company_gstin_number_first_two_digits !==
    $vendor_info->gstin_number_first_two_digits) {?>
        <tr>
            <td colspan="7" style="text-align: right;color:#181919;"><?php echo 'IGST OUTPUT'; ?></td>
            <?php /* if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */ ?>
            <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
                <?php echo to_currency($work_order_total_summary->estimate_tax_subtotal+$work_order_total_summary->freight_tax3, $work_order_total_summary->currency_symbol); ?>
            </td>
        </tr>
    <?php } ?>
    <?php }  else if (empty($vendor_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_state = get_setting("company_state");
if($company_state !==$vendor_info->state) {?>
<tr>
            <td colspan="7" style="text-align: right;color:#181919;"><?php echo 'IGST OUTPUT'; ?></td>
            <?php /* if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */ ?>
            <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
                <?php echo to_currency($work_order_total_summary->estimate_tax_subtotal+$work_order_total_summary->freight_tax3, $work_order_total_summary->currency_symbol); ?>
            </td>
        </tr>
  <?php } ?>
  <?php } ?>

<?php   if (!empty($vendor_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_gstin_number_first_two_digits= get_setting("company_gstin_number_first_two_digits");
if($company_gstin_number_first_two_digits ==$vendor_info->gstin_number_first_two_digits) {?>
        <tr>
            <td colspan="7" style="text-align: right;color:#181919;"><?php echo 'CGST OUTPUT'; ?></td>
            <?php /* if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */?>
            <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
                <?php echo to_currency($work_order_total_summary->estimate_tax_subtotal/2+$work_order_total_summary->freight_tax3/2, $work_order_total_summary->currency_symbol ); ?>
            </td>
        </tr>
        <tr>
            <td colspan="7" style="text-align: right;color:#181919;"><?php echo 'SGST OUTPUT'; ?></td>
            <?php /*if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */ ?>
            <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:80px;word-wrap: break-word;color:#181919;">
                <?php echo to_currency($work_order_total_summary->estimate_tax_subtotal/2+$work_order_total_summary->freight_tax3/2, $work_order_total_summary->currency_symbol ); ?>
            </td>
        </tr>
    <?php } ?>
    <?php } else if (empty($vendor_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_state = get_setting("company_state");
if($company_state ==$vendor_info->state) {?>
   <tr>
            <td colspan="7" style="text-align: right;color:#181919;"><?php echo 'CGST OUTPUT'; ?></td>
            <?php /* if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */?>
            <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
                <?php echo to_currency($work_order_total_summary->estimate_tax_subtotal/2+$work_order_total_summary->freight_tax3/2, $work_order_total_summary->currency_symbol ); ?>
            </td>
        </tr>
        <tr>
            <td colspan="7" style="text-align: right;color:#181919;"><?php echo 'SGST OUTPUT'; ?></td>
            <?php /*if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */ ?>
            <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:80px;word-wrap: break-word;color:#181919;">
                <?php echo to_currency($work_order_total_summary->estimate_tax_subtotal/2+$work_order_total_summary->freight_tax3/2, $work_order_total_summary->currency_symbol ); ?>
            </td>
        </tr>
<?php } ?>
   <?php } ?>
   <?php } ?>

   
   
    <tr>
        <td colspan="7" style="text-align: right;color:#181919;"><?php echo lang("total"); ?></td>
        <?php /*if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */?>
        <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->estimate_net_subtotal_default, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
    <?php $c= to_currency($work_order_total_summary->estimate_net_subtotal_default); $d=substr($c,-2); if($d>0){ ?>
    <tr>
        <td colspan="7" style="text-align: right;color:#181919;"><?php echo lang("round_off"); ?></td>
        <?php /*if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php } */?>
        <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php $c= to_currency($work_order_total_summary->estimate_net_subtotal_default); $d=substr($c,-2); if($d>=50){
            $e=(100-$d);
            echo "(+)0.".$e;
            }elseif($d<50){
                echo "(-)0.".$d;
                } ?>
        </td>
    </tr>
    <?php } ?>
     <?php if ($work_order_total_summary->total_paid) { ?>     
        <tr>
            <td colspan="7" style="text-align: right;color:#181919;"><?php echo lang("paid"); ?></td>
            <?php /* if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;color:black;"></td><?php } */ ?>
            <td style="text-align: right; width: 15%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
                <?php echo to_currency($work_order_total_summary->total_paid, $work_order_total_summary->currency_symbol); ?>
            </td>
        </tr>
    <?php } ?>
    
    <tr>
        <td colspan="7" style="text-align: right;color:#181919;"><?php echo lang("balance_due"); ?></td>
        <?php /*if($k>0){?> <td style="text-align: right; width: 5%; border: 1px solid #fff; background-color: #f4f4f4;"></td><?php }*/?>
        <td style="text-align: right; width: 15%; background-color: <?php echo $color; ?>; color: #fff;max-width:90px;word-wrap: break-word;">
            <?php echo to_currency($work_order_total_summary->balance_due, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
</table>
<?php } else { ?>

<table style="width: 100%; color: #444;">            
    <tr style="font-weight: bold; background-color: <?php echo $color; ?>; color: #fff;  ">
    <th style="width: 10%; text-align: center; border-right: 1px solid #eee;"> <?php echo lang("s.no"); ?> </th>
        <th style="width: 25%; border-right: 1px solid #eee;"> <?php echo lang("job_name"); ?> </th>
        <th style="text-align: left;  width: 16%; border-right: 1px solid #eee;"> <?php echo lang("category"); ?></th>
        
    
      <th style="text-align: center;  width: 12%; border-right: 1px solid #eee;font-size:14px;"> <?php echo ("Qty"); ?></th>
        
        <th style="text-align: center;  width: 17%; border-right: 1px solid #eee;"> <?php echo lang("rate"); ?></th>
        <th style="text-align: right;  width: 20%; border-right: 1px solid #eee;"> <?php echo lang("total"); ?></th>
        
       <!--th style="text-align: right;  width: 15%; border-right: 1px solid #eee;"> <?php echo lang("net_total"); ?></th-->
        
        
    </tr>
    <?php
    $counter = 0;
    foreach ($work_order_items as $item) {
        ?>
        <tr style="background-color: #f4f4f4; ">
        <td style="width: 10%; border: 1px solid #fff; padding: 10px;"><?php echo ++$counter;  ?>
                </td>
            <td style="width: 25%; border: 1px solid #fff; padding: 10px;"><?php echo $item->title; ?>
                <br />
                <span style="color: #888; font-size: 90%;"><?php echo nl2br($item->description); ?></span>
            </td>
            <td style="width: 16%; border: 1px solid #fff; padding: 10px;max-width: 10px;word-wrap: break-word;"><?php echo $item->category; ?>
                </td>
            <!--td style="width: 9%; border: 1px solid #fff; padding: 10px;max-width: 10px;word-wrap: break-word;"><?php echo $item->make; ?>
                </td-->
            
            
            <td style="text-align: center; width: 12%; border: 1px solid #fff;"> <?php echo $item->quantity . " " . $item->unit_type; ?></td>
            
            
            <td style="text-align: right; width: 17%; border: 1px solid #fff;max-width:85px;word-wrap: break-word;"> <?php echo to_currency($item->total/$item->quantity, $item->currency_symbol); ?></td>
            <!--td style="text-align: right; width: 13%; border: 1px solid #fff;max-width:90px;word-wrap: break-word;padding: 10px;"> <?php echo to_currency($item->total, $item->currency_symbol); ?></td-->
            
           
        <td style="text-align: right; width: 20%; border: 1px solid #fff;max-width:100px;word-wrap: break-word;"> <?php echo to_currency($item->net_total, $item->currency_symbol); ?></td>
        </tr>
    <?php } ?>
    <tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("sub_total"); ?></td>
        <!--td style="text-align: right; width: 13%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->estimate_subtotal, $work_order_total_summary->currency_symbol); ?>
        </td-->
        <!--td colspan="1" ></td-->
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:100px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->estimate_net_subtotal, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
   

    <?php if($work_order_total_summary->freight_amount){?>
     <tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("freight"); ?></td>
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->freight_tax2, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
    <?php } ?>

  <?php 
$company_setup_country = get_setting("company_setup_country");
if($company_setup_country ==$vendor_info->country) {?>  
<?php if($work_order_info->gst!=="0") { ?>

<?php if (!empty($vendor_info->gstin_number_first_two_digits)) { ?>

    <?php 
$company_gstin_number_first_two_digits= get_setting("company_gstin_number_first_two_digits");
if($company_gstin_number_first_two_digits !==$vendor_info->gstin_number_first_two_digits) {?>
<?php if($work_order_total_summary->freight_tax3) { ?>
<tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("igst_output"); ?></td>
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->freight_tax3, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
       <?php } ?>
    
    <?php } ?>
    <?php } else if (empty($vendor_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_state = get_setting("company_state");
if($company_state !==$vendor_info->state) {?>
<?php if($work_order_total_summary->freight_tax3) { ?>
<tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("igst_output"); ?></td>
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->freight_tax3, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
       <?php } ?>
    
<?php } ?>
<?php } ?>


<?php if (!empty($vendor_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_gstin_number_first_two_digits= get_setting("company_gstin_number_first_two_digits");
if($company_gstin_number_first_two_digits ==$vendor_info->gstin_number_first_two_digits) {?>
<?php if($work_order_total_summary->freight_tax3) { ?>
<tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("cgst_output"); ?></td>
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency(
            $work_order_total_summary->freight_tax3/2, 
            $work_order_total_summary->currency_symbol ); ?>
        </td>
    </tr>
    <tr>
       <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("sgst_output"); ?></td>
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency(
            $work_order_total_summary->freight_tax3/2, $work_order_total_summary->currency_symbol ); ?>
        </td>
    </tr>
    <?php } ?>
    
    <?php } ?>

    <?php } else if (empty($vendor_info->gstin_number_first_two_digits)) { ?>
<?php 
$company_state = get_setting("company_state");
if($company_state ==$vendor_info->state) {?>
<?php if($work_order_total_summary->freight_tax3) { ?>
<tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("cgst_output"); ?></td>
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->freight_tax3/2, $work_order_total_summary->currency_symbol ); ?>
        </td>
    </tr>
    <tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("sgst_output"); ?></td>
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->freight_tax3/2, $work_order_total_summary->currency_symbol ); ?>
        </td>
    </tr>
    <?php } ?>
    
<?php } ?>
<?php } ?>
<?php } ?>
<?php } ?>  

   
   
    <tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("total"); ?></td>
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php echo to_currency($work_order_total_summary->estimate_net_subtotal_default, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
    <?php $c= to_currency($work_order_total_summary->estimate_net_subtotal_default); $d=substr($c,-2); if($d>0){ ?>
    <tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("round_off"); ?></td>
        
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
            <?php $c= to_currency($work_order_total_summary->estimate_net_subtotal_default); $d=substr($c,-2); if($d>=50){
            $e=(100-$d);
            echo "(+)0.".$e;
            }elseif($d<50){
                echo "(-)0.".$d;
                } ?>
        </td>
    </tr>
    <?php } ?>
     <?php if ($work_order_total_summary->total_paid) { ?>     
        <tr>
            <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("paid"); ?></td>
            
            <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;max-width:90px;word-wrap: break-word;color:#181919;">
                <?php echo to_currency($work_order_total_summary->total_paid, $work_order_total_summary->currency_symbol); ?>
            </td>
        </tr>
    <?php } ?>
    
    <tr>
        <td colspan="5" style="text-align: right;color:#181919;"><?php echo lang("balance_due"); ?></td>
        
        <td style="text-align: right; width: 20%; background-color: <?php echo $color; ?>; color: #fff;max-width:90px;word-wrap: break-word;">
            <?php echo to_currency($work_order_total_summary->balance_due, $work_order_total_summary->currency_symbol); ?>
        </td>
    </tr>
</table>
<?php } ?>
<table style="width: 99%; color: #444;line-height: 15px">
    <tr style="font-weight: bold; background-color:white; color: black;  ">
        <th style="width: 100%; border-right: 1px solid #eee;font-weight: bold;font-size: 14px;"><?php
 if($work_order_total_summary->balance_due){

$numbers = $work_order_total_summary->balance_due;
$number=ltrim($numbers,"-");
function convertToIndianCurrency($number) {

    $company_currency = get_setting("default_currency");
    $no = round($number);
    $decimal = round($number - ($no = floor($number)), 2) * 100;    
    $digits_length = strlen($no);    
    $i = 0;
    $str = array();
    $words = array(
        0 => '',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
        20 => 'Twenty',
        30 => 'Thirty',
        40 => 'Forty',
        50 => 'Fifty',
        60 => 'Sixty',
        70 => 'Seventy',
        80 => 'Eighty',
        90 => 'Ninety');
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    while ($i < $digits_length) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;            
            $str [] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural;
        } else {
            $str [] = null;
        }  
    }
    
    $Rupees = implode(' ', array_reverse($str));
    $paise = ($decimal) ? "And  " . ($words[$decimal - $decimal%10]) ." " .($words[$decimal%10])." Paise"  : '';
    return ($Rupees ? $company_currency." " . $Rupees : '') . $paise . " Only";

}

if($numbers<0){
        echo "Balance Rupees = negative ". convertToIndianCurrency($number);

}else{
echo "Amount Chargeable(in words) : " . convertToIndianCurrency($number);
}
}

?>

</th>
</tr>
</table>
<br>
<br>
<?php /*
$company_gstin_number_first_two_digits= get_setting("company_gstin_number_first_two_digits");
if($company_gstin_number_first_two_digits ==$client_info->gstin_number_first_two_digits) {?>

<table style="width: 100%; color: #444;">
    <tr style="font-weight: bold; background-color: <?php echo $color; ?>; color: #fff;  ">
        <th style="width: 16%; border-right: 1px solid #eee;"> <?php echo lang("hsn_code"); ?> </th>
        <th style="text-align: right;  width: 14%; border-right: 1px solid #eee;"> <?php echo lang("taxable_value"); ?></th>
        <th style="text-align: right;  width: 14%; border-right: 1px solid #eee;"> <?php echo lang("central_tax_rate"); ?></th>
        <th style="text-align: right;  width: 14%; border-right: 1px solid #eee;"> <?php echo lang("central_tax_amount"); ?></th>
        <th style="text-align: right;  width: 14%; border-right: 1px solid #eee;"> <?php echo lang("state_tax_rate"); ?></th>
        <th style="text-align: right;  width: 14%; border-right: 1px solid #eee;"> <?php echo lang("state_tax_amount"); ?></th>
        <th style="text-align: right;  width: 14%; "> <?php echo lang("total_tax_amount"); ?></th>
    </tr>

<?php 
$DB2 = $this->load->database('default', TRUE);
$DB2->select ("total,hsn_code,gst");
 $DB2->from('estimate_items');
 $DB2->where('estimate_items.estimate_id',$estimate_info->id);
 $DB2->where('estimate_items.deleted','0');
 
$query=$DB2->get();
$query->result();
 foreach ($query->result() as $rows) { 
    ?>

      <tr style="background-color: #f4f4f4; ">
            <td style="width: 16%; border: 1px solid #fff; padding: 10px;"><?php echo $rows->hsn_code; ?>
            </td>
            <td style="text-align: right; width: 14%; border: 1px solid #fff;"><?php
         $b=$rows->total ;echo to_currency($b); ?>
        </td>
        <td style="text-align: right; width: 14%; border: 1px solid #fff;"> <?php  $p=$rows->gst;$q=$p/2;echo($q."%"); ?>
            </td>
            <td style="text-align: right; width: 14%; border: 1px solid #fff;"><?php
         $c= $q/100*$rows->total ;echo to_currency($c);
         ?></td>
         <td style="text-align: right; width: 14%; border: 1px solid #fff;"> <?php echo ($q."%"); ?></td>
         <td style="text-align: right; width: 14%; border: 1px solid #fff;"><?php
         $d= $q/100*$rows->total ;echo to_currency($d); ?>
             </td>
        <td style="text-align: right; width: 15%; border: 1px solid #fff;"><?php $e= $c+$d;echo to_currency($e);
         ?></td>
            </tr>
            <?php } ?>
            <?php if($estimate_total_summary->freight_tax) { ?>
<?php 
$DB5 = $this->load->database('default', TRUE);
$DB5->select ("hsn_code,gst,freight_amount");
 $DB5->from('estimates');
 $DB5->where('estimates.id',$estimate_info->id);
 $DB5->where('estimates.deleted','0');
 
$query=$DB5->get();
$query->result();
 foreach ($query->result() as $rows) { 
    ?>

      <tr style="background-color: #f4f4f4; ">
            <td style="width: 16%; border: 1px solid #fff; padding: 10px;"><?php echo $rows->hsn_code; ?>
            </td>
            <td style="text-align: right; width: 14%; border: 1px solid #fff;"><?php echo to_currency($estimate_total_summary->freight_tax2, $estimate_total_summary->currency_symbol); ?>
        </td>
        <td style="text-align: right; width: 14%; border: 1px solid #fff;"> <?php $y=$rows->gst/2; echo($y."%"); ?>
            </td>
            <td style="text-align: right; width: 14%; border: 1px solid #fff;"><?php echo to_currency($estimate_total_summary->freight_tax3/2, $estimate_total_summary->currency_symbol); ?></td>
         <td style="text-align: right; width: 14%; border: 1px solid #fff;"><?php $z=$rows->gst/2; echo($z."%"); ?></td>
         <td style="text-align: right; width: 14%; border: 1px solid #fff;"><?php echo to_currency($estimate_total_summary->freight_tax3/2, $estimate_total_summary->currency_symbol); ?>
             </td>
        <td style="text-align: right; width: 14%; border: 1px solid #fff;"><?php echo to_currency($estimate_total_summary->freight_tax3, $estimate_total_summary->currency_symbol); ?></td>
            </tr>
   
<?php } ?>
   
<?php } ?>



      <tr>
        <td colspan="0" style="text-align: right;color:#181919;"><?php echo lang("total"); ?></td>
        <td style="text-align: right; width: 14%; border: 1px solid #fff; background-color: #f4f4f4;color:#181919;">
            <?php echo to_currency($estimate_total_summary->estimate_subtotal+$estimate_total_summary->freight_tax2, $estimate_total_summary->currency_symbol); ?>
        </td>
        <td colspan="1" style="text-align: right;color:#181919;"><?php echo lang("total"); ?></td>
     <td style="text-align: right; width: 14%; border: 1px solid #fff; background-color: #f4f4f4;color:#181919;">
            <?php echo to_currency($estimate_total_summary->estimate_tax_subtotal/2+$estimate_total_summary->freight_tax3/2, $estimate_total_summary->currency_symbol ); ?>
        </td>
    <td colspan="1" style="text-align: right;color:#181919;"><?php echo lang("total"); ?></td>
     <td style="text-align: right; width: 14%; border: 1px solid #fff; background-color: #f4f4f4;color:#181919;">
            <?php echo to_currency($estimate_total_summary->estimate_tax_subtotal/2+$estimate_total_summary->freight_tax3/2, $_total_summary->currency_symbol ); ?>
        </td>
        
        <td style="text-align: right; width: 14%; border: 1px solid #fff; background-color: #f4f4f4;color:#181919;">
        <?php $h= $estimate_total_summary->estimate_tax_subtotal+$estimate_total_summary->freight_tax3; echo to_currency($h, $estimate_total_summary->currency_symbol); ?>
        </td>

    </tr>

</table>
<br>
<br>
<?php } ?>
<?php 
$company_gstin_number_first_two_digits = get_setting("company_gstin_number_first_two_digits");
if($company_gstin_number_first_two_digits !==$client_info->gstin_number_first_two_digits) {?>
<table style="width: 100%; color: #444;">
    <tr style="font-weight: bold; background-color: <?php echo $color; ?>; color: #fff;  ">
        <th style="width: 25%; border-right: 1px solid #eee;"> <?php echo lang("hsn_code"); ?> </th>
        <th style="text-align: right;  width: 20%; border-right: 1px solid #eee;"> <?php echo lang("taxable_value"); ?></th>
        <th style="text-align: right;  width: 15%; border-right: 1px solid #eee;"> <?php echo lang("igst_rate"); ?></th>
        <th style="text-align: right;  width: 20%; border-right: 1px solid #eee;"> <?php echo lang("igst_amount"); ?></th>
       <th style="text-align: right;  width: 20%; "> <?php echo lang("total_tax_amount"); ?></th>
    </tr>

<?php 
$DB3 = $this->load->database('default', TRUE);
$DB3->select ("total,hsn_code,gst,tax_amount");
 $DB3->from('estimate_items');
 $DB3->where('estimate_items.estimate_id',$estimate_info->id);
 $DB3->where('estimate_items.deleted','0');
 
$query=$DB3->get();
$query->result();
 foreach ($query->result() as $rows) { 
    ?>
<tr style="background-color: #f4f4f4; ">
            <td style="width: 25%; border: 1px solid #fff; padding: 10px;"><?php echo $rows->hsn_code; ?>
            </td>
    <td style="text-align: right; width: 20%; border: 1px solid #fff;"><?php echo to_currency($rows->total); ?>
        </td>
    <td style="text-align: right; width: 15%; border: 1px solid #fff;"> <?php  echo($rows->gst."%"); ?>
            </td>
        <td style="text-align: right; width: 20%; border: 1px solid #fff;"><?php echo to_currency($rows->tax_amount);?></td>
        <td style="text-align: right; width: 20%; border: 1px solid #fff;"><?php $e=$rows->tax_amount ;echo to_currency($e);
         ?></td>
            </tr>
   
<?php } ?>
<?php if($estimate_total_summary->freight_tax) { ?>
<?php 

$DB6 = $this->load->database('default', TRUE);
$DB6->select ("hsn_code,gst,freight_amount");
 $DB6->from('estimates');
 $DB6->where('estimates.id',$estimate_info->id);
 $DB6->where('estimates.deleted','0');
 
$query=$DB6->get();
$query->result();
 foreach ($query->result() as $rows) { 
    ?>
<tr style="background-color: #f4f4f4; ">
            <td style="width: 25%; border: 1px solid #fff; padding: 10px;"><?php echo $rows->hsn_code; ?>
            </td>
    <td style="text-align: right; width: 20%; border: 1px solid #fff;"><?php echo to_currency($estimate_total_summary->freight_tax2, $estimate_total_summary->currency_symbol); ?>
        </td>
    <td style="text-align: right; width: 15%; border: 1px solid #fff;"> <?php  echo($rows->gst."%"); ?>
            </td>
        <td style="text-align: right; width: 20%; border: 1px solid #fff;"><?php echo to_currency($estimate_total_summary->freight_tax3, $estimate_total_summary->currency_symbol); ?></td>
        <td style="text-align: right; width: 20%; border: 1px solid #fff;"><?php echo to_currency($estimate_total_summary->freight_tax, $estimate_total_summary->currency_symbol); ?></td>
            </tr>
   
<?php } ?>
<?php } ?>




      <tr>
        <td colspan="1" style="text-align: right;color:#181919;"><?php echo lang("total"); ?></td>
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;color:#181919;">
            <?php echo to_currency($estimate_total_summary->estimate_subtotal+$estimate_total_summary->freight_tax2, $estimate_total_summary->currency_symbol); ?>
        </td>
        <td colspan="1" style="text-align: right;color:#181919;"><?php echo lang("total"); ?></td>
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;color:#181919;">
        <?php $h= $estimate_total_summary->estimate_tax_subtotal+$estimate_total_summary->freight_tax3; echo to_currency($h, $estimate_total_summary->currency_symbol); ?>
        </td>
        <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;color:#181919;">
        <?php $i= $estimate_total_summary->estimate_tax_subtotal+$estimate_total_summary->freight_tax3; echo to_currency($i, $estimate_total_summary->currency_symbol); ?>
        </td>

    </tr>

</table>
<br>
<br>
<?php } ?>
<table style="width: 99%; color: #444;line-height: 15px">
    <tr style="font-weight: bold; background-color:white; color: black;  ">
        <th style="width: 100%; border-right: 1px solid #eee;font-weight: bold;font-size: 14px;"><?php
 

$number = $estimate_total_summary->estimate_tax_subtotal+$estimate_total_summary->freight_tax3;

function convertToIndianCurrencysTax($number) {

    $company_currency = get_setting("default_currency");
    $no = round($number);
    $decimal = round($number - ($no = floor($number)), 2) * 100;    
    $digits_length = strlen($no);    
    $i = 0;
    $str = array();
    $words = array(
        0 => '',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
        20 => 'Twenty',
        30 => 'Thirty',
        40 => 'Forty',
        50 => 'Fifty',
        60 => 'Sixty',
        70 => 'Seventy',
        80 => 'Eighty',
        90 => 'Ninety');
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    while ($i < $digits_length) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;            
            $str [] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural;
        } else {
            $str [] = null;
        }  
    }
    
    $Rupees = implode(' ', array_reverse($str));
   if($decimal>19){
    $paise = ($decimal) ? "And  " . ($words[$decimal - $decimal%10]) ." " .($words[$decimal%10])." Paise"  : '';
    }else{
       $paise = ($decimal) ? "And  " . ($words[$decimal]) ." Paise"  : ''; 
    }
    return ($Rupees ? $company_currency ." ". $Rupees : '') . $paise . " Only";

}


 echo "Tax Amount(in words) : " . convertToIndianCurrencysTax($number);
?></th>
</tr>
</table> 





<br />
<br />
<!--div style="border-top: 2px solid #f2f2f2; color:#444;">
    <div><?php echo nl2br($estimate_info->note); ?></div>
</div-->

<!--div style="margin-top: 15px;">
    <?php echo get_setting("estimate_footer"); */?>
</div-->

