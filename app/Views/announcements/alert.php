<?php foreach ($announcements as $announcement): ?>
    <div id="announcement-<?php echo $announcement->id; ?>" class="alert alert-warning">
        <i class="fa fa-bullhorn mr10"></i>
        <?php echo anchor("announcements/view/{$announcement->id}", $announcement->title); ?>
        <?php echo ajax_anchor("announcements/mark_as_read/{$announcement->id}", '<span aria-hidden="true">×</span>', array("class" => "close mt-5", "data-remove-on-click" => "#announcement-{$announcement->id}")); ?>
    </div>
<?php endforeach; ?>