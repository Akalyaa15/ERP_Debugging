

<script>
$(document).ready(function(){
 $("#select_bank").select2();
 $("#bank_id.select2").select2();
    load_data();

    function load_data()
    {
        $.ajax({
            url:"<?php echo base_url(); ?>index.php/excel_import/fetch",
            method:"POST",
            success:function(data){
                $('#customer_data').html(data);
            }
        })
    }

    $('#import_form').on('submit', function(event){
        event.preventDefault();
        $.ajax({
            url:"<?php echo base_url(); ?>index.php/excel_import/import",
            method:"POST",
            data:new FormData(this),
            contentType:false,
            cache:false,
            processData:false,
            success:function(data){
                $('#file').val('');
                location. reload(true);

            }
        })
    });

$('#import_form_icici').on('submit', function(event){
        event.preventDefault();
        $.ajax({
            url:"<?php echo base_url(); ?>index.php/excel_import/import_icici",
            method:"POST",
            data:new FormData(this),
            contentType:false,
            cache:false,
            processData:false,
            success:function(data){
                $('#file').val('');
                location. reload(true);

            }
        })
    });
});
</script>
<script>
$(document).ready(function(){
    $('#bank_id').on('change', function() {
      if ( this.value == '1')
      //.....................^.......
      {
        $("#icici_bank").hide();
        $("#indian_bank").show();
        $("#bank_name_ids").val(this.value);

      }
      else if ( this.value == '2')
      //.....................^.......
      {
        $("#icici_bank").show();
        $("#indian_bank").hide();
        $("#bank_name_id").val(this.value);
      }
      else
      {
        $("#icici_bank").hide();
        $("#indian_bank").hide();
        $("#bank_name_id").val("");
      }
    });

    //select bank account number

$("#bank_id").on('change',function () {
    $("#account_number").val("").attr('readonly', false)
                    var account_number =$("#account_number").val();

          $("#account_number,#account_numbers").select2({
            showSearchBox: true,
            ajax: {
                url: "<?php echo get_uri("excel_import/get_account_number_suggestion"); ?>",
                dataType: 'json',
               data: function (account_number, page) {
                    return {
                        q: account_number,
                        bank_id:$("#bank_id").val()// search term
                    };
                },
                    cache: false,
                    type: 'POST',
                results: function (data, page) {
                    return {results: data};
                }
            }
        })
        })



});
</script>
<div class="modal-body clearfix">
   <div class="form-group">
            <label for="bank_list" class=" col-md-3"><?php echo lang('select_your_bank'); ?></label>
            <div class="col-md-9" >
                <?php 
                echo form_dropdown("bank_id", $bank_list_dropdown, array($model_info->bank_id), "class='select2'id='bank_id' style='width: 300px'");
                ?>
            </div>
        </div>
        <br/>
       
        <div style='display:none;' id='indian_bank'>
            <form method="post" id="import_form" enctype="multipart/form-data">
                 <input  type="hidden" id="bank_name_ids" name="BankName"   />
        <div class="form-group">
                        <label for="select_bank" class=" col-md-3"><?php echo lang('select_account_number'); ?></label>
                        <div class=" col-md-9">
                       <input  type="text" id="account_numbers" name="account_number"  placeholder="Account Number" style ="width:304px" required="true" /></br><span id ="indianbank_message"></span></br>
    </div>
   </div>
        <div class="form-group">
                        <label for="select_bank" class=" col-md-3"><?php echo lang('select_excel_file'); ?></label>
                        <div class=" col-md-9">
            <p>
            <input type="file" name="file" class="sasa" id="file" required accept=".xls, .xlsx" /></p>
            <br />
        </div>
    </div>
    <div class="form-group">
                        <label for="select_bank" class=" col-md-3"></label>
                        <div class=" col-md-9">
            <input type="submit" name="import" value="Import" class="btn btn-info ss" id="subs" />
          </div>
      </div>  
    </form>
</div>
       

       <div style='display:none;' id='icici_bank'>
         <form method="post" id="import_form_icici" enctype="multipart/form-data">
            <input  type="hidden" id="bank_name_id" name="BankName"  />
        <div class="form-group">
                        <label for="select_bank" class=" col-md-3"><?php echo lang('select_account_number'); ?></label>
                        <div class=" col-md-9">
                            <input type="text" id="account_number" name="account_number"  placeholder="Account Number" style ="width:304px" /></br><span id ="icicibank_message"></span><br>
        </div>
    </div>
        <div class="form-group">
                        <label for="select_bank" class=" col-md-3"><?php echo lang('select_excel_file'); ?></label>
                        <div class=" col-md-9">
                            <p><input type="file" name="file" class="sasa" id="file1" required accept=".xls, .xlsx" /></p><br>
          
        </div>
    </div>

        <div class="form-group">
                        <label for="select_bank" class=" col-md-3"></label>
                        <div class=" col-md-9">
                           <input type="submit" name="import_icici" value="Import" class="btn btn-info ss" id="subss" /><br>
          
        </div>
    </div>
  </form>
</div>  
        
    
    <div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><span class="fa fa-close"></span> <?php echo lang('close'); ?></button>
    <!-- <button type="submit" class="btn btn-primary"><span class="fa fa-check-circle"></span> <?php echo lang('save'); ?></button> -->
  </div>
</div>


<script type="text/javascript">
   
      $('#file').on('change', function() {
     var ext = $('#file').val().split('.').pop().toLowerCase();
if($.inArray(ext, ['xls','xlsx']) == -1) {
   
  $('.ss').prop("disabled", true); // Element(s) are now enabled.
   $('.ss').attr("disabled", true); 
   $(".ss").attr('title', 'Already Delivery challan has been created');
}else{
    $('.ss').prop("disabled", false); // Element(s) are now enabled.
   $('.ss').attr("disabled", false); 
   $(".ss").attr('title', '');
}
    });
$('#file1').on('change', function() {
     var ext = $('#file1').val().split('.').pop().toLowerCase();
if($.inArray(ext, ['xls','xlsx']) == -1) {
   
  $('.ss').prop("disabled", true); // Element(s) are now enabled.
   $('.ss').attr("disabled", true); 
   $(".ss").attr('title', 'Only Excel files can be Imported');
}else{
    $('.ss').prop("disabled", false); // Element(s) are now enabled.
   $('.ss').attr("disabled", false); 
   $(".ss").attr('title', '');
}
    });
</script>

<!-- account number required -->
<script>
 
$('#subs').click(function () {
    var country =$("#account_numbers").val();
    if(country)
    {
    //$('#message').html('Select the State Name').css('color', 'white').hide()
    $('#indianbank_message').html('Select the Account Number').css('color', 'white').hide()
    return true;
    }
    else
    {
    //$('#message').html('Select the State Name').css('color', 'red').show()
    $('#indianbank_message').html('Select the Account Number ').css('color', 'red').show()
    return false;
    }
});

$('#subss').click(function () {
    var country =$("#account_number").val();
    if(country)
    {
    //$('#message').html('Select the State Name').css('color', 'white').hide()
    $('#icicibank_message').html('Select the Account Number').css('color', 'white').hide()
    return true;
    }
    else
    {
    //$('#message').html('Select the State Name').css('color', 'red').show()
    $('#icicibank_message').html('Select the Account Number').css('color', 'red').show()
    return false;
    }
});
    </script>