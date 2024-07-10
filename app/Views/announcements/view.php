<div id="page-content" class="clearfix p20">
    <a class="btn btn-primary" href="javascript:window.history.go(-1);">❮ Go Back</a>
    <div class="view-container">
        <div class="pt10 pb10 text-right"> &larr; <?= anchor("announcements", lang("announcements")); ?></div>
        <div class="panel panel-default no-border">
            <div class="panel-body p30">
                <h1 style="color: #555;" class="mt0">
                    <?= $announcement->title; ?>
                </h1>
                <div class="text-off mb15">
                    <?= format_to_date($announcement->start_date); ?>,&nbsp;
                    <?= get_team_member_profile_link($announcement->created_by, $announcement->created_by_user); ?>
                </div>
                <div>
                    <?= $announcement->description; ?>
                </div>
                <div class="mt20">
                    <?php if ($announcement->files) :
                        $files = unserialize($announcement->files);
                        $total_files = count($files);
                        ?>
                        <?= $this->include("includes/timeline_preview", ["files" => $files]); ?>

                        <?php if ($total_files) :
                            $download_caption = lang('download');
                            if ($total_files > 1) {
                                $download_caption = sprintf(lang('download_files'), $total_files);
                            }
                            ?>
                            <?= anchor(route_to('announcements/download_announcement_files', $announcement->id), $download_caption, ['class' => '', 'title' => $download_caption]); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
