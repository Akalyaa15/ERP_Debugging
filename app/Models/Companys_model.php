<?php

class Companys_model extends Crud_model {

    private $table = null;

    function __construct() {
        $this->table = 'companys';
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $clients_table = $this->db->dbprefix('companys');
        $projects_table = $this->db->dbprefix('projects');
        $users_table = $this->db->dbprefix('users');
        $invoices_table = $this->db->dbprefix('invoices');
        $invoice_payments_table = $this->db->dbprefix('invoice_payments');
        $invoice_items_table = $this->db->dbprefix('invoice_items');
        $branches_table = $this->db->dbprefix('branches');
        $country_table = $this->db->dbprefix('country');
        
        $client_groups_table = $this->db->dbprefix('company_groups');

        $where = "";
        $id = get_array_value($options, "id");
        if ($id) {
            $where = " AND $clients_table.id=$id";
        }
        $cr_id = get_array_value($options, "cr_id");
        if ($cr_id) {
            $where = " AND $clients_table.cr_id='$cr_id'";
        }

        $group_id = get_array_value($options, "group_id");
        if ($group_id) {
            $where = " AND FIND_IN_SET('$group_id', $clients_table.group_ids)";
        }


        //prepare custom fild binding query
        $custom_fields = get_array_value($options, "custom_fields");
        $custom_field_query_info = $this->prepare_custom_field_query_string("companys", $custom_fields, $clients_table);
        $select_custom_fieds = get_array_value($custom_field_query_info, "select_string");
        $join_custom_fieds = get_array_value($custom_field_query_info, "join_string");

       

        $freight_amount = " IFNULL($invoices_table.freight_amount,0) ";

        

        $invoice_value_calculation_query = "round(
            SUM(IFNULL(items_table.invoice_value,0)
             +$freight_amount
           ))";

        $this->db->query('SET SQL_BIG_SELECTS=1');

        $sql = "SELECT $clients_table.*, CONCAT($users_table.first_name, ' ', $users_table.last_name) AS primary_contact, $users_table.id AS primary_contact_id, $users_table.image AS contact_avatar,/*$branches_table.branch_code as branch_code,$branches_table.buid as buid,$country_table.numberCode as countrys,*/  /*project_table.total_projects, IFNULL(invoice_details.invoice_value,0) AS invoice_value, IFNULL(invoice_details.payment_received,0) AS payment_received $select_custom_fieds,*/
                (SELECT GROUP_CONCAT($client_groups_table.title) FROM $client_groups_table WHERE FIND_IN_SET($client_groups_table.id, $clients_table.group_ids) AND $clients_table.partner_id IS NULL) AS groups
        FROM $clients_table
        LEFT JOIN $users_table ON $users_table.company_id = $clients_table.cr_id AND $users_table.deleted=0 AND $users_table.is_primary_contact=1 
       /* LEFT JOIN (SELECT company_id, COUNT(id) AS total_projects FROM $projects_table WHERE deleted=0 GROUP BY company_id) AS project_table ON project_table.company_id= $clients_table.id
        LEFT JOIN (SELECT company_id, SUM(payments_table.payment_received) as payment_received, $invoice_value_calculation_query as invoice_value FROM $invoices_table
                   
                   LEFT JOIN (SELECT invoice_id, SUM(amount) AS payment_received FROM $invoice_payments_table WHERE deleted=0 GROUP BY invoice_id) AS payments_table ON payments_table.invoice_id=$invoices_table.id AND $invoices_table.deleted=0 AND $invoices_table.status='not_paid'
                LEFT JOIN (SELECT invoice_id, SUM(net_total) AS invoice_value FROM $invoice_items_table WHERE deleted=0 GROUP BY invoice_id) AS items_table ON items_table.invoice_id=$invoices_table.id AND $invoices_table.deleted=0 AND $invoices_table.status='not_paid'
                   GROUP BY $invoices_table.company_id    
                   ) AS invoice_details ON invoice_details.company_id= $clients_table.id*/
      /* LEFT JOIN $branches_table ON $branches_table.company_name=$clients_table.cr_id
       LEFT JOIN $country_table ON $country_table.numberCode =$clients_table.country  */    
          $join_custom_fieds               
        WHERE $clients_table.deleted=0 $where";
        return $this->db->query($sql);
    }

    function get_c_details($options = array()) {
        $clients_table = $this->db->dbprefix('companys');
        
        

        $where = "";
        $id = get_array_value($options, "id");
        if ($id) {
            $where = " AND $clients_table.id=$id";
        }
       

        $sql = "SELECT $clients_table.*
        FROM $clients_table
      
        WHERE $clients_table.deleted=0 $where";
        return $this->db->query($sql);
    }

    function get_primary_contact($company_id = 0, $info = false) {
        $users_table = $this->db->dbprefix('users');

        $sql = "SELECT $users_table.id, $users_table.first_name, $users_table.last_name
        FROM $users_table
        WHERE $users_table.deleted=0 AND $users_table.company_id='$company_id' AND $users_table.is_primary_contact=1";
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
        $clients_table = $this->db->dbprefix('companys');

        $action = " CONCAT($clients_table.starred_by,',',':$user_id:') ";
        $where = " AND FIND_IN_SET(':$user_id:',$clients_table.starred_by) = 0"; //don't add duplicate

        if ($type != "add") {
            $action = " REPLACE($clients_table.starred_by, ',:$user_id:', '') ";
            $where = "";
        }

        $sql = "UPDATE $clients_table SET $clients_table.starred_by = $action
        WHERE $clients_table.id=$project_id $where";
        return $this->db->query($sql);
    }

    function get_starred_companys($user_id) {
        $clients_table = $this->db->dbprefix('companys');

        $sql = "SELECT $clients_table.id,  $clients_table.company_name,$clients_table.cr_id
        FROM $clients_table
        WHERE $clients_table.deleted=0 AND FIND_IN_SET(':$user_id:',$clients_table.starred_by)
        ORDER BY $clients_table.company_name ASC";
        return $this->db->query($sql);
    }

  function delete_company_and_sub_items($company_id,$cr_id) {
        $clients_table = $this->db->dbprefix('companys');
        $general_files_table = $this->db->dbprefix('general_files');
        $users_table = $this->db->dbprefix('users');
        $delete_client_sql = "UPDATE $clients_table SET $clients_table.deleted=1 WHERE $clients_table.id=$company_id; ";
        $this->db->query($delete_client_sql);
        $delete_contacts_sql = "UPDATE $users_table SET $users_table.deleted=1 WHERE $users_table.company_id='$cr_id'; ";
        $this->db->query($delete_contacts_sql);

        //delete the project files from directory
        $file_path = get_general_file_path("company", $company_id);
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

    function get_client_country_info_suggestion($item_name = "") {

        $items_table = $this->db->dbprefix('companys');
        //$states_table = $this->db->dbprefix('states');s

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

    function get_proforma_invoice_client_country_info_suggestion($item_name = "") {

        $items_table = $this->db->dbprefix('companys');
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


// excel file get data clents 
    function get_import_detailss($options = array()) {
        $vendors_table = $this->db->dbprefix('companys');
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


    function insert($data)
    {
        $this->db->insert_batch('companys', $data);
    }


 function get_country_info_suggestion($item_name = "") {

        $items_table = $this->db->dbprefix('country');
        $states_table = $this->db->dbprefix('states');
        $vat_types_table = $this->db->dbprefix('vat_types');

        $sql = "SELECT $items_table.*,$vat_types_table.title as vat_name
        FROM $items_table
        /*LEFT JOIN $states_table ON $states_table.country_code= $items_table.numberCode */
        /*WHERE $items_table.deleted=0  AND $items_table.countryName LIKE '%$item_name%'*/
        LEFT JOIN $vat_types_table ON $vat_types_table.id= $items_table.vat_type
        WHERE $items_table.deleted=0  AND $items_table.numberCode = '$item_name'
        ORDER BY id DESC LIMIT 1
        ";
        
        $result = $this->db->query($sql); 

        if ($result->num_rows()) {
            return $result->row();
        }

    }

    function get_item_suggestions_country_name($keyword = "",$keywords = "") {
        $items_table = $this->db->dbprefix('country');
        $states_table = $this->db->dbprefix('states');
        

        $sql = "SELECT $states_table.title,$states_table.id
        FROM $items_table
        LEFT JOIN $states_table ON $states_table.country_code= $items_table.numberCode  
        /*WHERE $items_table.deleted=0  AND $items_table.countryName = '$keywords' AND $states_table.title like '%$keyword%'*/
        WHERE $items_table.deleted=0  AND $items_table.numberCode = '$keywords' AND $states_table.title like '%$keyword%' AND 
        $states_table.deleted=0
        LIMIT 500 
        ";
        return $this->db->query($sql)->result();
     }

     function get_search_suggestion($search = "", $options = array()) {
        $clients_table = $this->db->dbprefix('companys');

        $where = "";
        /*$show_own_clients_only_user_id = get_array_value($options, "show_own_clients_only_user_id");
        if ($show_own_clients_only_user_id) {
            $where .= " AND ($clients_table.created_by=$show_own_clients_only_user_id)";
        }*/

        $search = $this->db->escape_str($search);

        $sql = "SELECT $clients_table.id,$clients_table.cr_id, $clients_table.company_name AS title
        FROM $clients_table  
        WHERE $clients_table.deleted=0  AND $clients_table.company_name LIKE '%$search%' $where
        ORDER BY $clients_table.company_name ASC
        LIMIT 0, 10";

        return $this->db->query($sql);
    }


     



}
