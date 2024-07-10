<?php echo form_open(get_uri("estimates/save_item"), array("id" => "estimate-item-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />
    <input type="hidden" name="estimate_id" value="<?php echo $estimate_id; ?>" />
    <input type="hidden" name="add_new_item_to_library" value="" id="add_new_item_to_library" />
    <div class="form-group">
        <label for="estimate_item_title" class=" col-md-3"><?php echo lang('product_id'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "estimate_item_title",
                "name" => "estimate_item_title",
                "value" => $model_info->title,
                "class" => "form-control validate-hidden",
                "placeholder" => lang('select_or_create_new_product'),
                "data-rule-required" => true,
                "data-msg-required" => lang("field_required"),
            ));
            ?>
            <a id="estimate_item_title_dropdwon_icon" tabindex="-1" href="javascript:void(0);" style="color: #B3B3B3;float: right; padding: 5px 7px; margin-top: -35px; font-size: 18px;"><span>×</span></a>
        </div>
    </div>
    <div class="form-group">
        <label for="estimate_item_category" class=" col-md-3"><?php echo lang('category'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "estimate_item_category",
                "name" => "estimate_item_category",
                "value" => $model_info->category,
                "class" => "form-control",
                "placeholder" => lang('category')
                
            ));
            ?>
        </div>
    </div>
    <div class="form-group">
        <label for="estimate_item_make" class=" col-md-3"><?php echo lang('make'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "estimate_item_make",
                "name" => "estimate_item_make",
                "value" => $model_info->make,
                "class" => "form-control",
                "placeholder" => lang('make')
                
            ));
            ?>
        </div>
    </div>
    
    <div class="form-group">
        <label for="estimate_item_description" class="col-md-3"><?php echo lang('description'); ?></label>
        <div class=" col-md-9">
            <?php
            echo form_textarea(array(
                "id" => "estimate_item_description",
                "name" => "estimate_item_description",
                "value" => $model_info->description ? $model_info->description : "",
                "class" => "form-control",
                "placeholder" => lang('description')
            ));
            ?>
        </div>
    </div>
    <div class="form-group">
        <label for="estimate_item_quantity" class=" col-md-3"><?php echo lang('quantity'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "estimate_item_quantity",
                "name" => "estimate_item_quantity",
                "value" => $model_info->quantity ? to_decimal_format($model_info->quantity) : "",
                "class" => "form-control",
                "placeholder" => lang('quantity'),
                "data-rule-required" => true,
                "data-msg-required" => lang("field_required"),
            ));
            ?>
        </div>
    </div>
    <div class="form-group">
        <label for="estimate_unit_type" class=" col-md-3"><?php echo lang('unit_type'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "estimate_unit_type",
                "name" => "estimate_unit_type",
                "value" => $model_info->unit_type,
                "class" => "form-control",
                "placeholder" => lang('unit_type') . ' (Ex: hours, pc, etc.)'
            ));
            ?>
        </div>
    </div>
    <div class="form-group">
        <label for="estimate_item_rate" class=" col-md-3"><?php echo lang('rate'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "estimate_item_rate",
                "name" => "estimate_item_rate",
                "value" => $model_info->rate ? to_decimal_format($model_info->rate) : "",
                "class" => "form-control",
                "placeholder" => lang('rate'),
                "data-rule-required" => true,
                "data-msg-required" => lang("field_required"),
            ));
            ?>
        </div>
    </div>
    <!--div class="form-group">
        <label for="estimate_item_hsn_code" class=" col-md-3"><?php echo lang('hsn_code'); ?></label>
        <div class="col-md-9">
            <?php
           /* echo form_input(array(
                "id" => "estimate_item_hsn_code",
                "name" => "estimate_item_hsn_code",
                "value" => $model_info->hsn_code,
                "class" => "form-control",
                "placeholder" => lang('hsn_code'),
                "readonly" => "true"
            ));
           */ ?>
        </div>
    </div-->
         <input type="hidden" name="add_new_item_to_librarys" value="" id="add_new_item_to_librarys" />
    <div class="form-group">
        <label for="hsn_code" class=" col-md-3"><?php echo lang('hsn_sac_code'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "estimate_item_hsn_code",
                "name" => "estimate_item_hsn_code",
                "value" => $model_info->hsn_code,
                "class" => "form-control validate-hidden",
                "placeholder" => lang('select_or_create_new_hsn_code'),
               "data-rule-required" => true,
                "data-msg-required" => lang("field_required"),
            ));
            ?>
            <a id="hsn_code_dropdwon_icon" tabindex="-1" href="javascript:void(0);" style="color: #B3B3B3;float: right; padding: 5px 7px; margin-top: -35px; font-size: 18px;"><span>×</span></a>
        </div>
    </div>
     <div class="form-group">
        <label for="estimate_item_gst" class=" col-md-3"><?php echo lang('gst'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "estimate_item_gst",
                "name" => "estimate_item_gst",
                "value" => $model_info->gst,
                "class" => "form-control",
                "placeholder" => lang('gst'),
                //"readonly" => "true"
                
            ));
            ?>
        </div>
    </div>
    <div class="form-group">
        <label for="estimate_item_hsn_description" class="col-md-3"><?php echo lang('hsn_description'); ?></label>
        <div class=" col-md-9">
            <?php
            echo form_textarea(array(
             "id" => "estimate_item_hsn_code_description",
            "name" => "estimate_item_hsn_code_description",
             "value" => $model_info->hsn_description ? $model_info->hsn_description : "",
                "class" => "form-control",
                "placeholder" => lang('hsn_description')
            ));
            ?>
        </div>
    </div>
    <div class="form-group">
        <label for="discount" class="col-md-3"><?php echo lang('discount_percentage'); ?></label>
        <div class="col-md-9">
            <?php
            echo form_input(array(
                "id" => "discount_percentage",
                "name" => "discount_percentage",
                "value" => $model_info->discount_percentage ? $model_info->discount_percentage : "",
                "class" => "form-control",
                "placeholder" => lang('discount_percentage'),
                
            ));
            ?>
        </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><span class="fa fa-close"></span> <?php echo lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><span class="fa fa-check-circle"></span> <?php echo lang('save'); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#estimate-item-form").appForm({
            onSuccess: function (result) {
                $("#estimate-item-table").appTable({newData: result.data, dataId: result.id});
                $("#estimate-total-section").html(result.estimate_total_view);
                if (typeof updateInvoiceStatusBar == 'function') {
                    updateInvoiceStatusBar(result.estimate_id);
                }
            }
        });

        //show item suggestion dropdown when adding new item
        var isUpdate = "<?php echo $model_info->id; ?>";
        if (!isUpdate) {
            applySelect2OnItemTitle();
        }

        //re-initialize item suggestion dropdown on request
        $("#estimate_item_title_dropdwon_icon").click(function () {
            applySelect2OnItemTitle();
        })

        var ishsnUpdate = "<?php echo $model_info->id; ?>";
        if (!ishsnUpdate) {
            applySelect2OnHsnTitle();
        }

        //re-initialize item suggestion dropdown on request
        $("#hsn_code_dropdwon_icon").click(function () {
            applySelect2OnHsnTitle();
        })

    });

    function applySelect2OnItemTitle() {
        $("#estimate_item_title").select2({
            showSearchBox: true,
            ajax: {
                url: "<?php echo get_uri("estimates/get_estimate_item_suggestion"); ?>",
                dataType: 'json',
                quietMillis: 250,
                data: function (term, page) {
                    return {
                        q: term // search term
                    };
                },
                results: function (data, page) {
                    return {results: data};
                }
            }
        }).change(function (e) {
            if (e.val === "+") {
                //show simple textbox to input the new item
                $("#estimate_item_title").select2("destroy").val("").focus();
                $("#add_new_item_to_library").val(1); //set the flag to add new item in library
            } else if (e.val) {
                //get existing item info
                $("#add_new_item_to_library").val(""); //reset the flag to add new item in library
                $.ajax({
                    url: "<?php echo get_uri("estimates/get_estimate_item_info_suggestion"); ?>",
                    data: {item_name: e.val},
                    cache: false,
                    type: 'POST',
                    dataType: "json",
                    success: function (response) {

                        //auto fill the description, unit type and rate fields.
                        if (response && response.success) {

                            if (!$("#estimate_item_description").val()) {
                                $("#estimate_item_description").val(response.item_info.description);
                            }

                            if (!$("#estimate_unit_type").val()) {
                                $("#estimate_unit_type").val(response.item_info.unit_type);
                            }

                            if (!$("#estimate_item_rate").val()) {
                                $("#estimate_item_rate").val(response.item_info.rate);
                            }
                            if (!$("#estimate_item_category").val()) {
                                $("#estimate_item_category").val(response.item_info.category);
                            }
                            
                            if (!$("#estimate_item_make").val()) {
                                $("#estimate_item_make").val(response.item_info.make);
                            }
                            if (!$("#estimate_item_hsn_code").val()) {
                                $("#estimate_item_hsn_code").val(response.item_info.hsn_code);
                            }
                            if (!$("#estimate_item_hsn_code_description").val()) {
                                $("#estimate_item_hsn_code_description").val(response.item_info.hsn_description);
                            }
                            if (!$("#estimate_item_gst").val()) {
                                $("#estimate_item_gst").val(response.item_info.gst);
                            }
                        }
                    }
                });
            }

        });
    }

    function applySelect2OnHsnTitle() {
        $("#estimate_item_hsn_code").select2({
            showSearchBox: true,
            ajax: {
                url: "<?php echo get_uri("items/get_invoice_item_suggestion"); ?>",
                dataType: 'json',
                quietMillis: 250,
                data: function (term, page) {
                    return {
                        q: term // search term
                    };
                },
                results: function (data, page) {
                    return {results: data};
                }
            }
        }).change(function (e) {
            if (e.val === "+") {
                //show simple textbox to input the new item
                $("#estimate_item_hsn_code").select2("destroy").val("").focus();
                $("#add_new_item_to_librarys").val(1); //set the flag to add new item in library
            } else if (e.val) {
                //get existing item info
                $("#add_new_item_to_librarys").val(""); //reset the flag to add new item in library
                $.ajax({
                    url: "<?php echo get_uri("items/get_invoice_item_info_suggestion"); ?>",
                    data: {item_name: e.val},
                    cache: false,
                    type: 'POST',
                    dataType: "json",
                    success: function (response) {

                        //auto fill the description, unit type and rate fields.
                        if (response && response.success) {

                            if (!$("#estimate_item_gst").val()) {
                                $("#estimate_item_gst").val(response.item_info.gst);
                            }
                           if (!$("#estimate_item_hsn_code_description").val()) {
                                $("#estimate_item_hsn_code_description").val(response.item_info.hsn_description);
                            }

                            

                            
                            
                            
                            
                            
                        }
                    }
                });
            }

        });
    }






</script>
<script type="text/javascript">
    $("#estimate_item_title").on("click", function() {
   
        $("#estimate_item_gst").val("")
        $("#estimate_item_description").val("")
        $("#estimate_unit_type").val("")
        $("#estimate_item_category").val("")
        $("#estimate_item_rate").val("")
        $("#estimate_item_make").val("")
        $("#estimate_item_hsn_code").select2("destroy").val("")
        $("#estimate_item_hsn_code_description").val("")
});
</script>
<script type="text/javascript">
    $("#estimate_item_hsn_code").on("click", function() {
   
        $("#estimate_item_gst").val("")
       
        $("#estimate_item_hsn_code_description").val("")
});
</script>