<table style="color: #444; width: 100%;">
    <tr>
        <td style="width: 50%; vertical-align: top;">
           <?php $this->load->view('purchase_orders/purchase_order_parts/company_logo'); ?>
        </td>
        <td style="width: 50%; vertical-align: top; text-align: right"><?php
            $data = array(
                "vendor_info" => $vendor_info,
                "color" => $color,
                "invoice_info" => $invoice_info
            );
            $this->load->view('purchase_orders/purchase_order_parts/purchase_order_info', $data);
            ?>
        </td>
    </tr>
    
<tr><td><?php
            $this->load->view('purchase_orders/purchase_order_parts/purchase_order_from', $data);
            ?>
        </td>
        <td><?php
            $this->load->view('purchase_orders/purchase_order_parts/purchase_order_to', $data);
            ?></td>
    </tr>
</table>