<table style="color: #444; width: 100%;">
    <tr>
        <td style="width: 50%; vertical-align: top;"><?php
            $data = array(
                "client_info" => $client_info,
                "color" => $color,
                "invoice_info" => $invoice_info
            );
            $this->load->view('work_orders/work_order_parts/work_order_info', $data);
            ?>
        </td>
        
        
        <td style="width: 50%; vertical-align: top;">
           <?php $this->load->view('work_orders/work_order_parts/company_logo'); ?>
        </td>
    </tr>
    
    <tr>
        <td><?php
            $this->load->view('work_orders/work_order_parts/work_order_to', $data);
            ?>
        </td>
      
        <td><?php
             $this->load->view('work_orders/work_order_parts/work_order_from', $data);
            ?>
        </td>

    </tr>
</table>

