<div id="page-content" class="p20 clearfix">
    <div class="panel panel-default">
        <a class="btn btn-primary" href="javascript:window.history.go(-1);">❮ Go Back</a>
        <div class="page-title clearfix">
            <h1><?= lang('announcements'); ?></h1>
            <div class="title-button-group">
                <?php if ($show_add_button): ?>
                    <?= anchor('announcements/form', '<i class="fa fa-plus-circle"></i> ' . lang('add_announcement'), ['class' => 'btn btn-default', 'data-modal-lg' => '1', 'title' => lang('add_announcement')]); ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table id="announcement-table" class="display" cellspacing="0" width="100%">
            </table>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        var showUserInfo = <?= $this->login_user->user_type === 'client' ? 'false' : 'true'; ?>;
        var showOption = <?= ($this->login_user->user_type === 'client' || !$show_option) ? 'false' : 'true'; ?>;
        
        $('#announcement-table').appTable({
            source: '<?= site_url('announcements/list_data'); ?>',
            order: [[2, 'desc']],
            columns: [
                {title: '<?= lang('id'); ?>'},
                {title: '<?= lang('title'); ?>'},
                {visible: showUserInfo, title: '<?= lang('created_by'); ?>'},
                {visible: showUserInfo, title: '<?= lang('seen'); ?>'},
                {visible: false, searchable: false},
                {title: '<?= lang('start_date'); ?>', iDataSort: 2},
                {visible: false, searchable: false},
                {title: '<?= lang('end_date'); ?>', iDataSort: 4},
                {title: '<i class="fa fa-bars"></i>', class: 'text-center option w100', visible: showOption}
            ],
            printColumns: [0, 1, 3, 5]
        });
    });
</script>
