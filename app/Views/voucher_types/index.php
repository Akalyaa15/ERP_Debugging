<div id="page-content" class="p20 clearfix">
    <div class="row">
        <div class="col-sm-3 col-lg-2">
            <?= $this->include('settings/tabs', ['active_tab' => 'voucher_types']) ?>
        </div>

        <div class="col-sm-9 col-lg-10">
            <div class="panel panel-default">
                <a class="btn btn-primary" href="javascript:window.history.go(-1);">❮ Go Back</a>
                <div class="page-title clearfix">
                    <h4><?= lang('voucher_types') ?></h4>
                    <div class="title-button-group">
                        <?= modal_anchor(get_uri("voucher_types/modal_form"), "<i class='fa fa-plus-circle'></i> " . lang('add_voucher_type'), ["class" => "btn btn-default", "title" => lang('add_voucher_type')]) ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="category-table" class="display" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#category-table").appTable({
            source: '<?= site_url("voucher_types/list_data") ?>',
            columns: [
                {title: '<?= lang("name") ?>'},
                {title: '<?= lang("description") ?>'},
                {title: '<?= lang("status") ?>'},
                {title: '<i class="fa fa-bars"></i>', "class": "text-center option w100"}
            ],
            printColumns: [0, 1, 2]
        });
    });
</script>
