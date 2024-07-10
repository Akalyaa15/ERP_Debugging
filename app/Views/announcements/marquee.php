<?php if (!empty($marquee_announcements)): ?>
<div class="row">
    <div class="col-md-1" style="z-index: 1">
        <button type="button" class="btn btn-primary"><span class="fa fa-bullhorn mr10"></span> Announcements</button>
    </div>
    <div class="col-md-11" style="padding-top: 6px">
        <marquee behavior="scroll" direction="left" onmouseover="this.stop();" onmouseout="this.start();">
            <?php
            $total_announce = count($marquee_announcements);
            foreach ($marquee_announcements as $key => $marquee_announcement) {
                echo anchor('announcements/view/' . $marquee_announcement->id, $marquee_announcement->title);
                if ($key < $total_announce - 1) {
                    echo "&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;";
                } else {
                    echo ".";
                }
            }
            ?>
        </marquee>
    </div>
</div>
<br>
<?php endif; ?>

