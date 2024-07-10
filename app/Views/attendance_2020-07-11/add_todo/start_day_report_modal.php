<?php
$url = "attendance/save_sor";

if ($clock_in == "1") {
    $url = "attendance/log_sor_time";
}

echo form_open(get_uri($url), array("id" => "attendance-sor-form", "class" => "general-form", "role" => "form"));
?>
<div class="modal-body clearfix">
    <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

    <div class="form-group">
        <label for="note" class=" col-md-12">Note (Enable location mode in your device to clock in)</label>
        <div class=" col-md-12">
            <?php
            echo form_textarea(array(
                "id" => "sor_note",
                "name" => "sor_note",
                "class" => "form-control",
                "placeholder" => lang('Enable Location in order to clock in'),
                "value" => $model_info->sor_note,
                //"readonly"=>true

            ));
            ?>
        </div>
        <input name="clock_in" type="hidden" value="<?php echo $clock_in; ?>" />
    </div>
</div>



<div class="modal-footer">
<div id="link-of-task-view" class="hide">
            <?php
            echo modal_anchor(get_uri("attendance/todo_view"), "", array());
            ?>
        </div>
    <button type="button" class="btn btn-default" data-dismiss="modal"><span class="fa fa-close"></span> <?php echo lang('close'); ?></button>
    <button id="save-and-show-button"  disabled type="button" class="btn btn-primary" ><span class="fa fa-check-circle"></span> <?php echo lang('save_and_add_todo'); ?></button>
    <!--button type="submit" class="btn btn-primary"><span class="fa fa-check-circle"></span> <?php echo lang('save'); ?></button-->
</div><input type="hidden" name="result" id="result">

<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
      /* $("#attendance-sor-form").appForm({
            onSuccess: function (result) {
                if (result.clock_widget) {
                   $("#timecard-clock-in").closest("#js-clock-in-out").html(result.clock_widget);
                } else {
                    if (result.isUpdate) {
                        $(".dataTable:visible").appTable({newData: result.data, dataId: result.id});
                    } else {
                        $(".dataTable:visible").appTable({reload: true});
                    }
                }
            }
        });  */

       window.showAddNewModal = false;

        $("#save-and-show-button").click(function () {
            window.showAddNewModal = true;
            $(this).trigger("submit");

        });

        var taskInfoText = "<?php echo lang('add_todo') ?>";

window.taskForm = $("#attendance-sor-form").appForm({
            closeModalOnSuccess: false,
            onSuccess: function (result) {
               //$("#monthly-loan-table").appTable({newData: result.data, dataId: result.id});
                //$("#reload-kanban-button").trigger("click");
if (result.clock_widget) {
                   $("#timecard-clock-in").closest("#js-clock-in-out").html(result.clock_widget);
                } else {
                    if (result.isUpdate) {
                        $(".dataTable:visible").appTable({newData: result.data, dataId: result.id});
                    } else {
                        $(".dataTable:visible").appTable({reload: true});
                    }
                }
                $("#save_and_show_value").append(result.save_and_show_link);

                if (window.showAddNewModal) {
                    var $taskViewLink = $("#link-of-task-view").find("a");
                    $taskViewLink.attr("data-title", taskInfoText + "#" + result.id);
                    $taskViewLink.attr("data-post-id", result.id);

                    $taskViewLink.trigger("click");
                } else {
                    window.taskForm.closeModal();
                }
            }
        }); 

        $("#note").focus();
    });
        delay().then(function(response) {
 $.ajax({
  url: 'https://reverse.geocoder.api.here.com/6.2/reversegeocode.json',
  type: 'GET',
  dataType: 'json',
  jsonp: 'jsoncallback',
  data: {
    prox: $("#result").val(),
    mode: 'retrieveAddresses',
    maxresults: '1',
    gen: '9',
    app_id: 'luqHzxdDgQRXukjNkhmd',
    app_code: 'rJm0dYRyV5Cpchlv9SJWzQ'
  },
  success: function (data) {
    var a=JSON.stringify(data.Response.View[0].Result[0].Location.Address.Label);
    $('#result').val(a)
    //$('#sor_note').val(a)
    $("#save-and-show-button").removeAttr("disabled");

  }
});    });

function delay() {
  return new Promise(function(resolve,reject) {
         if ("geolocation" in navigator){ //check geolocation available 
        //try to get user current location using getCurrentPosition() method
        navigator.geolocation.getCurrentPosition(function(position){ 
                $("#result").val(position.coords.latitude+","+ position.coords.longitude);
resolve('success');            });
    }else{
        console.log("Browser doesn't support geolocation!");
    } 

  });
}
</script>
<script type="text/javascript">
    $(document).on("click","#save-and-show-button",function() {
    $("#save-and-show-button").attr('disabled', 'true');
});
</script>