<?php

class Vendors_model extends Crud_model {

    private $table = null;

    function __construct() {
        $this->table = 'vendors';
        parent::__construct($this->table);
    }


    // excel file get data
    function get_import_detailss($options = array()) {
        $vendors_table = $this->db->dbprefix('vendors');
        $where = "";
        $id = get_array_value($options, "id");
        if ($id) {
            $where = " AND  $vendors_table.id=$id";
        }
        $company_name = get_array_value($options, "company_name");
        if ($company_name) {
            $where .= " AND  $vendors_table.company_name='$company_name'";
        }
        $city = get_array_value($options, "city");
        if ($city) {
            $where .= " AND  $vendors_table.city='$city'";
        }
        $state = get_array_value($options, "state");
        if ($state) {
            $where .= " AND  $vendors_table.state='$state'";
        }
        $country = get_array_value($options, "country");
        if ($country) {
            $where .= " AND  $vendors_table.country='$country'";
        }
        $website = get_array_value($options, "website");
        if ($website) {
            $where .= " AND  $vendors_table.website='$website'";
        }
        $zip = get_array_value($options, "zip");
        if ($zip) {
            $where .= " AND  $vendors_table.zip='$zip'";
        }
        $phone = get_array_value($options, "phone");
        if ($phone) {
            $where .= " AND  $vendors_table.phone='$phone'";
        }
        $gst_number = get_array_value($options, "gst_number");
        if ($gst_number) {
            $where .= " AND  $vendors_table.gst_number='$gst_number'";
        }
        $currency = get_array_value($options, "currency");
        if ($currency) {
            $where .= " AND  $vendors_table.currency='$currency'";
        }
        $currency_symbol = get_array_value($options, "currency_symbol");
        if ($currency_symbol) {
            $where .= " AND  $vendors_table.currency_symbol='$currency_symbol'";
        }
        $gstin_number_first_two_digits = get_array_value($options, "gstin_number_first_two_digits");
        if ($gstin_number_first_two_digits) {
        $where .= " AND  $vendors_table.gstin_number_first_two_digits='$gstin_number_first_two_digits'";
        }
        /*$currency_symbol = get_array_value($options, "currency_symbol");
        if ($currency_symbol) {
            $where = " AND  $vendors_table.currency_symbol='$currency_symbol'";
        }*/
        
        

        $sql = "SELECT  $vendors_table.*
        FROM  $vendors_table
        WHERE  $vendors_table.deleted=0 $where";
        return $this->db->query($sql);
    }

    function get_details($options = array()) {
        $vendors_table = $this->db->dbprefix('vendors');
       // $projects_table = $this->db->dbprefix('projects');
        $users_table = $this->db->dbprefix('users');
        $purchase_orders_table = $this->db->dbprefix('purchase_orders');
       $purchase_order_payments_table = $this->db->dbprefix('purchase_order_payments');
        $purchase_order_items_table = $this->db->dbprefix('purchase_order_items');
        $work_orders_table = $this->db->dbprefix('work_orders');
       $work_order_payments_table = $this->db->dbprefix('work_order_payments');
        $work_order_items_table = $this->db->dbprefix('work_order_items');
        
        $vendor_groups_table = $this->db->dbprefix('vendor_groups');

        $where = "";
        $id = get_array_value($options, "id");
        if ($id) {
            $where = " AND $vendors_table.id=$id";
        }


        $group_id = get_array_value($options, "group_id");
        if ($group_id) {
            $where = " AND FIND_IN_SET('$group_id', $vendors_table.group_ids)";       
        }


        //prepare custom fild binding query
        $custom_fields = get_array_value($options, "custom_fields");
        $custom_field_query_info = $this->prepare_custom_field_query_string("clients", $custom_fields, $vendors_table);
        $select_custom_fieds = get_array_value($custom_field_query_info, "select_string");
        $join_custom_fieds = get_array_value($custom_field_query_info, "join_string");

       

        $freight_amount = " IFNULL($purchase_orders_table.freight_amount,0) ";

        

        $purchase_order_value_calculation_query = "round(
            SUM(IFNULL(items_table.purchase_order_value,0)
             +$freight_amount
           ))";

           $work_order_freight_amount = " IFNULL($work_orders_table.freight_amount,0) ";

        

        $work_order_value_calculation_query = "round(
            SUM(IFNULL(works_table.work_order_value,0)
             +$work_order_freight_amount
           ))";

        $this->db->query('SET SQL_BIG_SELECTS=1');

        $sql = "SELECT $vendors_table.*, CONCAT($users_table.first_name, ' ', $users_table.last_name) AS primary_contact, $users_table.id AS primary_contact_id, $users_table.image AS contact_avatar ,purchase_orders_count_table.total_purchase_orders, IFNULL(purchase_order_details.purchase_order_value,0) AS purchase_order_value,IFNULL(purchase_order_details.payment_received,0)AS payment_received,work_orders_count_table.total_work_orders,IFNULL(work_order_details.work_order_value,0) AS work_order_value,IFNULL(work_order_details.work_order_payment_received,0)AS work_order_payment_received
           $select_custom_fieds,
                (SELECT GROUP_CONCAT($vendor_groups_table.title) FROM $vendor_groups_table WHERE FIND_IN_SET($vendor_groups_table.id, $vendors_table.group_ids)) AS groups
        FROM $vendors_table
        LEFT JOIN $users_table ON $users_table.vendor_id = $vendors_table.id AND $users_table.deleted=0 AND $users_table.is_primary_contact=1
        LEFT JOIN (SELECT vendor_id, COUNT(id) AS total_purchase_orders FROM $purchase_orders_table WHERE deleted=0 GROUP BY vendor_id) AS purchase_orders_count_table ON purchase_orders_count_table.vendor_id= $vendors_table.id
        LEFT JOIN (SELECT vendor_id, COUNT(id) AS total_work_orders FROM $work_orders_table WHERE deleted=0 GROUP BY vendor_id) AS work_orders_count_table ON work_orders_count_table.vendor_id= $vendors_table.id
      LEFT JOIN (SELECT vendor_id, SUM(payments_table.payment_received) as payment_received, $purchase_order_value_calculation_query as purchase_order_value FROM $purchase_orders_table
        LEFT JOIN (SELECT purchase_order_id, SUM(amount) AS payment_received FROM $purchase_order_payments_table WHERE deleted=0 GROUP BY purchase_order_id) AS payments_table ON payments_table.purchase_order_id=$purchase_orders_table.id AND $purchase_orders_table.deleted=0 AND $purchase_orders_table.status='not_paid'
         
        LEFT JOIN (SELECT purchase_order_id, SUM(net_total) AS purchase_order_value FROM $purchase_order_items_table WHERE deleted=0 GROUP BY purchase_order_id) AS items_table ON items_table.purchase_order_id=$purchase_orders_table.id AND $purchase_orders_table.deleted=0 AND $purchase_orders_table.status='not_paid'  
                   GROUP BY $purchase_orders_table.vendor_id    
                   ) AS purchase_order_details ON purchase_order_details.vendor_id= $vendors_table.id

    LEFT JOIN (SELECT vendor_id, SUM(work_order_payments_table.work_order_payment_received) as work_order_payment_received, $work_order_value_calculation_query as work_order_value FROM $work_orders_table
        LEFT JOIN (SELECT work_order_id, SUM(amount) AS work_order_payment_received FROM $work_order_payments_table WHERE deleted=0 GROUP BY work_order_id) AS work_order_payments_table ON work_order_payments_table.work_order_id=$work_orders_table.id AND $work_orders_table.deleted=0 AND $work_orders_table.status='not_paid'
         
        LEFT JOIN (SELECT work_order_id, SUM(net_total) AS work_order_value FROM $work_order_items_table WHERE deleted=0 GROUP BY work_order_id) AS works_table ON works_table.work_order_id=$work_orders_table.id AND $work_orders_table.deleted=0 AND 
        $work_orders_table.status='not_paid'
                   GROUP BY $work_orders_table.vendor_id    
                   ) AS work_order_details ON work_order_details.vendor_id= $vendors_table.id 

        
        $join_custom_fieds               
        WHERE $vendors_table.deleted=0 $where";
        return $this->db->query($sql);
    }

    function get_primary_contact($vendor_id = 0, $info = false) {
        $users_table = $this->db->dbprefix('users');

        $sql = "SELECT $users_table.id, $users_table.first_name, $users_table.last_name
        FROM $users_table
        WHERE $users_table.deleted=0 AND $users_table.vendor_id=$vendor_id AND $users_table.is_primary_contact=1";
        $result = $this->db->query($sql);
        if ($result->num_rows()) {
            if ($info) {
                return $result->row();
            } else {
                return $result->row()->id;
            }
        }
    }

    function add_remove_star($project_id, $user_id, $type = "add") {
        $vendors_table = $this->db->dbprefix('vendors');

        $action = " CONCAT($vendors_table.starred_by,',',':$user_id:') ";
        $where = " AND FIND_IN_SET(':$user_id:',$vendors_table.starred_by) = 0"; //don't add duplicate

        if ($type != "add") {
            $action = " REPLACE($vendors_table.starred_by, ',:$user_id:', '') ";
            $where = "";
        }

        $sql = "UPDATE $vendors_table SET $vendors_table.starred_by = $action
        WHERE $vendors_table.id=$project_id $where";
        return $this->db->query($sql);
    }

    function get_starred_vendors($user_id) {
        $vendors_table = $this->db->dbprefix('vendors');

        $sql = "SELECT $vendors_table.id,  $vendors_table.company_name
        FROM $vendors_table
        WHERE $vendors_table.deleted=0 AND FIND_IN_SET(':$user_id:',$vendors_table.starred_by)
        ORDER BY $vendors_table.company_name ASC";
        return $this->db->query($sql);
    } 

    function delete_vendor_and_sub_items($vendor_id) {
        $vendors_table = $this->db->dbprefix('vendors');
        $general_files_table = $this->db->dbprefix('general_files');
        $users_table = $this->db->dbprefix('users');


        //get client files info to delete the files from directory 
        $client_files_sql = "SELECT * FROM $general_files_table WHERE $general_files_table.deleted=0 AND $general_files_table.vendor_id=$vendor_id; ";
        $client_files = $this->db->query($client_files_sql)->result();

        //delete the client and sub items
        //delete client
        $delete_client_sql = "UPDATE $vendors_table SET $vendors_table.deleted=1 WHERE $vendors_table.id=$vendor_id; ";
        $this->db->query($delete_client_sql);

        //delete contacts
        $delete_contacts_sql = "UPDATE $users_table SET $users_table.deleted=1 WHERE $users_table.vendor_id=$vendor_id; ";
        $this->db->query($delete_contacts_sql);

        //delete the project files from directory
        $file_path = get_general_file_path("vendor", $vendor_id);
        foreach ($client_files as $file) {
            delete_file_from_directory($file_path . "/" . $file->file_name);
        }

        return true;
    }

    function is_duplicate_company_name($company_name,  $id = 0) {
        
        $result = $this->get_all_where(array("company_name" => $company_name, "deleted" => 0));
        if ($result->num_rows() && $result->row()->id != $id) {
            return $result->row();
        } else {
            return false;
        }
    }


    function get_vendor_country_info_suggestion($item_name = "") {

        $items_table = $this->db->dbprefix('vendors');
        //$states_table = $this->db->dbprefix('states');

        $sql = "SELECT $items_table.*
        FROM $items_table
        /*LEFT JOIN $states_table ON $states_table.country_code= $items_table.numberCode */
        WHERE $items_table.deleted=0  AND $items_table.id = '$item_name'
        ORDER BY id DESC LIMIT 1
        ";
        
        $result = $this->db->query($sql); 

        if ($result->num_rows()) {
            return $result->row();
        }

    }



    

    function insert($data)
    {
        $this->db->insert_batch('vendors', $data);
    }

    function get_search_suggestion($search = "", $options = array()) {
        $clients_table = $this->db->dbprefix('vendors');

        $where = "";
        /*$show_own_clients_only_user_id = get_array_value($options, "show_own_clients_only_user_id");
        if ($show_own_clients_only_user_id) {
            $where .= " AND ($clients_table.created_by=$show_own_clients_only_user_id)";
        }*/

        $search = $this->db->escape_str($search);

        $sql = "SELECT $clients_table.id, $clients_table.company_name AS title
        FROM $clients_table  
        WHERE $clients_table.deleted=0  AND $clients_table.company_name LIKE '%$search%' $where
        ORDER BY $clients_table.company_name ASC
        LIMIT 0, 10";

        return $this->db->query($sql);
    }


}
